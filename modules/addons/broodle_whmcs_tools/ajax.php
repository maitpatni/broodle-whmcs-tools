<?php
/**
 * Broodle WHMCS Tools — AJAX Handler for Email & Domain Actions
 *
 * @package    BroodleWHMCSTools
 * @author     Broodle
 * @link       https://broodle.host
 */

define('CLIENTAREA', true);

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/clientfunctions.php';

use WHMCS\Database\Capsule;

header('Content-Type: application/json');

// Verify logged-in client
$ca = new WHMCS\ClientArea();
$ca->initPage();
if (!$ca->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
$clientId = (int) $ca->getUserID();

$action    = isset($_POST['action']) ? $_POST['action'] : '';
$serviceId = isset($_POST['service_id']) ? (int) $_POST['service_id'] : 0;

if (!$serviceId || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Verify the service belongs to this client
$service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
if (!$service || (int) $service->userid !== $clientId) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Check feature toggle based on action
$domainActions = ['get_parent_domains', 'add_addon_domain', 'add_subdomain', 'delete_domain'];
$dbActions = ['list_databases', 'create_database', 'create_db_user', 'delete_database', 'delete_db_user', 'assign_db_user', 'get_phpmyadmin_url'];
$sslActions = ['ssl_status', 'start_autossl', 'autossl_progress', 'autossl_problems'];
$dnsActions = ['dns_list_domains', 'dns_fetch_records', 'dns_add_record', 'dns_edit_record', 'dns_delete_record', 'dns_bulk_delete'];

// Handle addon description lookup (no cPanel needed)
if ($action === 'get_addon_description') {
    $addonId = isset($_POST['addon_id']) ? (int) $_POST['addon_id'] : 0;
    if (!$addonId) {
        echo json_encode(['success' => false, 'message' => 'Missing addon ID']);
        exit;
    }
    $addon = Capsule::table('tbladdons')->where('id', $addonId)->first();
    if (!$addon) {
        echo json_encode(['success' => false, 'message' => 'Addon not found']);
        exit;
    }
    $desc = strip_tags($addon->description ?? '');
    // Get pricing — use client's currency, fallback to currency 1
    $clientCurrency = Capsule::table('tblclients')->where('id', $clientId)->value('currency') ?: 1;
    $pricing = Capsule::table('tblpricing')
        ->where('type', 'addon')
        ->where('relid', $addonId)
        ->where('currency', $clientCurrency)
        ->first();
    $currency = Capsule::table('tblcurrencies')->where('id', $clientCurrency)->first();
    $prefix = $currency->prefix ?? '';
    $suffix = $currency->suffix ?? '';
    $price = '';
    $cycle = $addon->billingcycle ?? '';
    if ($pricing) {
        if ($pricing->monthly > 0) $price = $prefix . number_format($pricing->monthly, 2) . $suffix . '/mo';
        elseif ($pricing->quarterly > 0) $price = $prefix . number_format($pricing->quarterly, 2) . $suffix . '/qtr';
        elseif ($pricing->semiannually > 0) $price = $prefix . number_format($pricing->semiannually, 2) . $suffix . '/6mo';
        elseif ($pricing->annually > 0) $price = $prefix . number_format($pricing->annually, 2) . $suffix . '/yr';
        elseif ($pricing->biennially > 0) $price = $prefix . number_format($pricing->biennially, 2) . $suffix . '/2yr';
        elseif ($pricing->triennially > 0) $price = $prefix . number_format($pricing->triennially, 2) . $suffix . '/3yr';
        if (strtolower($cycle) === 'onetime' && $pricing->monthly > 0) $price = $prefix . number_format($pricing->monthly, 2) . $suffix . ' one-time';
    }
    echo json_encode(['success' => true, 'description' => $desc, 'price' => trim($price)]);
    exit;
}

if (in_array($action, $domainActions)) {
    $featureKey = 'tweak_domain_management';
} elseif (in_array($action, $dbActions)) {
    $featureKey = 'tweak_database_management';
} elseif (in_array($action, $sslActions)) {
    $featureKey = 'tweak_ssl_management';
} elseif (in_array($action, $dnsActions)) {
    $featureKey = 'tweak_dns_management';
} else {
    $featureKey = 'tweak_email_list';
}

$enabled = Capsule::table('mod_broodle_tools_settings')
    ->where('setting_key', $featureKey)
    ->value('setting_value');
if ($enabled !== '1') {
    echo json_encode(['success' => false, 'message' => 'Feature disabled']);
    exit;
}

// Get server info
$server = Capsule::table('tblservers')->where('id', $service->server)->first();
if (!$server) {
    echo json_encode(['success' => false, 'message' => 'Server not found']);
    exit;
}

$hostname   = $server->hostname;
$port       = !empty($server->port) ? (int) $server->port : 2087;
$serverUser = $server->username;
$secure     = !empty($server->secure) && ($server->secure === 'on' || $server->secure === '1' || $server->secure === 1);
$protocol   = $secure ? 'https' : 'http';
$cpUsername  = $service->username;

// Resolve auth
$accessHash = '';
$password   = '';
if (!empty($server->accesshash)) {
    $raw = trim($server->accesshash);
    if (preg_match('/^[A-Za-z0-9]{10,64}$/', $raw)) {
        $accessHash = $raw;
    } else {
        $accessHash = trim(decrypt($raw));
        if (empty($accessHash) || !preg_match('/^[A-Za-z0-9]{10,64}$/', $accessHash)) {
            $accessHash = '';
        }
    }
}
if (empty($accessHash) && !empty($server->password)) {
    $password = trim(decrypt($server->password));
}
if (empty($accessHash) && empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Server credentials unavailable']);
    exit;
}

/** Make a WHM API call. */
function broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url, $timeout = 20)
{
    $headers = [];
    if (!empty($accessHash)) {
        $headers[] = "Authorization: whm {$serverUser}:{$accessHash}";
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    } elseif (!empty($password)) {
        curl_setopt($ch, CURLOPT_USERPWD, "{$serverUser}:{$password}");
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => $response];
}

/** Helper to parse UAPI result status. */
function broodle_ajax_parse_result($r)
{
    if ($r['code'] !== 200 || !$r['body']) return ['ok' => false, 'error' => 'Failed to connect to server'];
    $json = json_decode($r['body'], true);
    $status = $json['result']['status'] ?? ($json['cpanelresult']['data'][0]['result'] ?? null);
    if ($status == 1 || $status === true) return ['ok' => true];
    $err = $json['result']['errors'][0] ?? ($json['cpanelresult']['data'][0]['reason'] ?? 'Unknown error');
    return ['ok' => false, 'error' => $err];
}

// ── Route actions ──

switch ($action) {

    case 'create_email':
        $emailUser = isset($_POST['email_user']) ? trim($_POST['email_user']) : '';
        $emailPass = isset($_POST['email_pass']) ? $_POST['email_pass'] : '';
        $domain    = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        $quota     = isset($_POST['quota']) ? (int) $_POST['quota'] : 250;

        if (empty($emailUser) || empty($emailPass) || empty($domain)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
            exit;
        }
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $emailUser)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email username']);
            exit;
        }

        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=Email"
             . "&cpanel_jsonapi_func=add_pop"
             . "&email=" . urlencode($emailUser)
             . "&password=" . urlencode($emailPass)
             . "&domain=" . urlencode($domain)
             . "&quota=" . $quota;

        $p = broodle_ajax_parse_result(broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url));
        echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'Email account created' : $p['error'], 'email' => $emailUser . '@' . $domain]);
        break;

    case 'change_password':
        $emailFull = isset($_POST['email']) ? trim($_POST['email']) : '';
        $newPass   = isset($_POST['new_pass']) ? $_POST['new_pass'] : '';

        if (empty($emailFull) || empty($newPass)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
            exit;
        }
        $parts = explode('@', $emailFull, 2);
        if (count($parts) !== 2) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            exit;
        }

        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=Email"
             . "&cpanel_jsonapi_func=passwd_pop"
             . "&email=" . urlencode($parts[0])
             . "&password=" . urlencode($newPass)
             . "&domain=" . urlencode($parts[1]);

        $p = broodle_ajax_parse_result(broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url));
        echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'Password changed successfully' : $p['error']]);
        break;

    case 'delete_email':
        $emailFull = isset($_POST['email']) ? trim($_POST['email']) : '';
        if (empty($emailFull)) { echo json_encode(['success' => false, 'message' => 'Missing email']); exit; }
        $parts = explode('@', $emailFull, 2);
        if (count($parts) !== 2) { echo json_encode(['success' => false, 'message' => 'Invalid email']); exit; }

        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=Email"
             . "&cpanel_jsonapi_func=delete_pop"
             . "&email=" . urlencode($parts[0])
             . "&domain=" . urlencode($parts[1]);

        $p = broodle_ajax_parse_result(broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url));
        echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'Email account deleted' : $p['error']]);
        break;

    case 'webmail_login':
        $emailFull = isset($_POST['email']) ? trim($_POST['email']) : '';
        if (empty($emailFull)) { echo json_encode(['success' => false, 'message' => 'Missing email']); exit; }

        // Create a cPanel session for the email account owner, then redirect straight to Roundcube inbox
        $url = "{$protocol}://{$hostname}:{$port}/json-api/create_user_session"
             . "?api.version=1"
             . "&user=" . urlencode($cpUsername)
             . "&service=cpaneld"
             . "&locale=en";

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $sessionUrl = $json['data']['url'] ?? '';
            if (!empty($sessionUrl)) {
                // Parse the session URL to extract the cpsess token and build a Roundcube URL
                // Session URL looks like: https://host:2083/cpsess1234567890/...
                if (preg_match('#(https?://[^/]+/cpsess[^/]+)#', $sessionUrl, $m)) {
                    $baseSession = $m[1];
                    $roundcubeUrl = $baseSession . '/3rdparty/roundcube/?_task=mail&_mbox=INBOX&_user=' . urlencode($emailFull);
                    echo json_encode(['success' => true, 'url' => $roundcubeUrl]);
                    break;
                }
                // If we can't parse, still use the session URL
                echo json_encode(['success' => true, 'url' => $sessionUrl]);
                break;
            }
        }

        // Fallback: direct webmail URL with Roundcube path
        $webmailPort = $secure ? 2096 : 2095;
        echo json_encode(['success' => true, 'url' => "{$protocol}://{$hostname}:{$webmailPort}/3rdparty/roundcube/?_task=mail&_mbox=INBOX"]);
        break;

    case 'get_domains':
        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=DomainInfo"
             . "&cpanel_jsonapi_func=list_domains";

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
        $domains = [];
        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $data = $json['result']['data'] ?? ($json['cpanelresult']['data'] ?? []);
            if (!empty($data)) {
                if (isset($data['main_domain'])) $domains[] = $data['main_domain'];
                if (!empty($data['addon_domains'])) $domains = array_merge($domains, $data['addon_domains']);
                if (!empty($data['parked_domains'])) $domains = array_merge($domains, $data['parked_domains']);
                if (!empty($data['sub_domains'])) {
                    foreach ($data['sub_domains'] as $sd) {
                        if (!in_array($sd, $domains)) $domains[] = $sd;
                    }
                }
            }
        }
        if (empty($domains) && !empty($service->domain)) $domains[] = $service->domain;
        $domains = array_unique($domains);
        sort($domains);
        echo json_encode(['success' => true, 'domains' => $domains]);
        break;

    case 'get_parent_domains':
        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=DomainInfo"
             . "&cpanel_jsonapi_func=list_domains";

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
        $domains = [];
        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $data = $json['result']['data'] ?? ($json['cpanelresult']['data'] ?? []);
            if (!empty($data)) {
                if (isset($data['main_domain'])) $domains[] = $data['main_domain'];
                if (!empty($data['addon_domains'])) $domains = array_merge($domains, $data['addon_domains']);
            }
        }
        if (empty($domains) && !empty($service->domain)) $domains[] = $service->domain;
        $domains = array_unique($domains);
        sort($domains);
        echo json_encode(['success' => true, 'domains' => $domains]);
        break;

    case 'add_addon_domain':
        $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        $docroot = isset($_POST['docroot']) ? trim($_POST['docroot']) : '';

        if (empty($domain)) { echo json_encode(['success' => false, 'message' => 'Please enter a domain name']); exit; }
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $domain)) {
            echo json_encode(['success' => false, 'message' => 'Invalid domain name']); exit;
        }
        if (empty($docroot)) $docroot = $domain;

        // Use AddonDomain::addaddondomain (cPanel API 2)
        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=2"
             . "&cpanel_jsonapi_module=AddonDomain"
             . "&cpanel_jsonapi_func=addaddondomain"
             . "&newdomain=" . urlencode($domain)
             . "&subdomain=" . urlencode(str_replace('.', '', $domain))
             . "&dir=" . urlencode($docroot);

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
        $p = broodle_ajax_parse_result($r);

        // If legacy module fails, try SubDomain::addsubdomain as fallback (some cPanel versions treat addons as subdomains)
        if (!$p['ok'] && strpos($p['error'], 'module') !== false) {
            $url2 = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
                  . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
                  . "&cpanel_jsonapi_apiversion=2"
                  . "&cpanel_jsonapi_module=SubDomain"
                  . "&cpanel_jsonapi_func=addsubdomain"
                  . "&domain=" . urlencode($domain)
                  . "&rootdomain=" . urlencode($service->domain)
                  . "&dir=" . urlencode($docroot);
            $r2 = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url2);
            $p = broodle_ajax_parse_result($r2);
        }

        echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'Domain added successfully' : $p['error'], 'domain' => $domain, 'type' => 'addon']);
        break;

    case 'add_subdomain':
        $subdomain = isset($_POST['subdomain']) ? trim($_POST['subdomain']) : '';
        $domain    = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        $docroot   = isset($_POST['docroot']) ? trim($_POST['docroot']) : '';

        if (empty($subdomain) || empty($domain)) { echo json_encode(['success' => false, 'message' => 'Please fill in all fields']); exit; }
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?$/', $subdomain)) {
            echo json_encode(['success' => false, 'message' => 'Invalid subdomain name']); exit;
        }
        if (empty($docroot)) $docroot = $subdomain . '.' . $domain;

        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=2"
             . "&cpanel_jsonapi_module=SubDomain"
             . "&cpanel_jsonapi_func=addsubdomain"
             . "&domain=" . urlencode($subdomain)
             . "&rootdomain=" . urlencode($domain)
             . "&dir=" . urlencode($docroot);

        $p = broodle_ajax_parse_result(broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url));
        $fullSub = $subdomain . '.' . $domain;
        echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'Subdomain created successfully' : $p['error'], 'domain' => $fullSub, 'type' => 'sub']);
        break;

    case 'delete_domain':
        $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        $type   = isset($_POST['type']) ? trim($_POST['type']) : '';

        if (empty($domain) || empty($type)) { echo json_encode(['success' => false, 'message' => 'Missing parameters']); exit; }
        if ($type === 'main') { echo json_encode(['success' => false, 'message' => 'Cannot delete the primary domain']); exit; }

        $url = '';
        if ($type === 'addon') {
            // AddonDomain::deladdondomain (cPanel API 2)
            $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
                 . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
                 . "&cpanel_jsonapi_apiversion=2"
                 . "&cpanel_jsonapi_module=AddonDomain"
                 . "&cpanel_jsonapi_func=deladdondomain"
                 . "&domain=" . urlencode($domain)
                 . "&subdomain=" . urlencode(str_replace('.', '', $domain) . '.' . ($service->domain ?? ''));
        } elseif ($type === 'sub') {
            // SubDomain::delsubdomain (cPanel API 2)
            $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
                 . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
                 . "&cpanel_jsonapi_apiversion=2"
                 . "&cpanel_jsonapi_module=SubDomain"
                 . "&cpanel_jsonapi_func=delsubdomain"
                 . "&domain=" . urlencode($domain);
        } elseif ($type === 'parked') {
            // Park::unpark (cPanel API 2)
            $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
                 . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
                 . "&cpanel_jsonapi_apiversion=2"
                 . "&cpanel_jsonapi_module=Park"
                 . "&cpanel_jsonapi_func=unpark"
                 . "&domain=" . urlencode($domain);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unknown domain type']); exit;
        }

        $p = broodle_ajax_parse_result(broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url));
        echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'Domain deleted successfully' : $p['error']]);
        break;

    // ── Database Management Actions ──

    case 'list_databases':
        $databases = [];
        $users = [];
        $mappings = [];
        $prefix = $cpUsername . '_';

        // Primary: UAPI Mysql::list_databases — returns {database, users[], disk_usage} per entry
        $urlDbs = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=Mysql"
             . "&cpanel_jsonapi_func=list_databases";
        $rDbs = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $urlDbs);

        $gotMappings = false;
        if ($rDbs['code'] === 200 && $rDbs['body']) {
            $json = json_decode($rDbs['body'], true);
            $dbList = $json['result']['data'] ?? [];
            if (is_array($dbList)) {
                foreach ($dbList as $db) {
                    if (!is_array($db)) continue;
                    $dbName = $db['database'] ?? ($db['db'] ?? '');
                    if (!$dbName) continue;
                    $databases[] = $dbName;
                    // UAPI returns users as a plain string array: ["user1", "user2"]
                    if (!empty($db['users']) && is_array($db['users'])) {
                        $gotMappings = true;
                        foreach ($db['users'] as $u) {
                            $uName = is_string($u) ? $u : ($u['user'] ?? '');
                            if ($uName) $mappings[] = ['db' => $dbName, 'user' => $uName];
                        }
                    }
                }
            }
        }

        // Fallback: cPanel API 2 MysqlFE::listdbs — returns {db, userlist: [{db,user}], usercount}
        if (empty($databases)) {
            $urlListDbs = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
                 . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
                 . "&cpanel_jsonapi_apiversion=2"
                 . "&cpanel_jsonapi_module=MysqlFE"
                 . "&cpanel_jsonapi_func=listdbs";
            $rListDbs = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $urlListDbs);
            if ($rListDbs['code'] === 200 && $rListDbs['body']) {
                $json = json_decode($rListDbs['body'], true);
                $dbList = $json['cpanelresult']['data'] ?? [];
                if (is_array($dbList) && !empty($dbList)) {
                    foreach ($dbList as $db) {
                        $dbName = $db['db'] ?? '';
                        if (!$dbName) continue;
                        $databases[] = $dbName;
                        // userlist is an array of {db, user} objects
                        $userList = $db['userlist'] ?? [];
                        if (is_array($userList)) {
                            $gotMappings = true;
                            foreach ($userList as $entry) {
                                $uName = is_array($entry) ? ($entry['user'] ?? '') : '';
                                if ($uName) $mappings[] = ['db' => $dbName, 'user' => $uName];
                            }
                        }
                    }
                }
            }
        }

        // UAPI Mysql::list_users — returns {user, shortuser, databases[]}
        $urlUsers = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=Mysql"
             . "&cpanel_jsonapi_func=list_users";
        $rUsers = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $urlUsers);
        if ($rUsers['code'] === 200 && $rUsers['body']) {
            $json = json_decode($rUsers['body'], true);
            $uList = $json['result']['data'] ?? [];
            if (is_array($uList)) {
                foreach ($uList as $u) {
                    if (!is_array($u)) continue;
                    $uName = $u['user'] ?? '';
                    if ($uName) {
                        $users[] = $uName;
                        // list_users also returns databases per user — use as fallback mappings
                        if (!$gotMappings && !empty($u['databases']) && is_array($u['databases'])) {
                            foreach ($u['databases'] as $dbName) {
                                if (is_string($dbName) && $dbName) {
                                    $mappings[] = ['db' => $dbName, 'user' => $uName];
                                }
                            }
                        }
                    }
                }
            }
        }

        echo json_encode([
            'success' => true,
            'databases' => array_values(array_unique($databases)),
            'users' => array_values(array_unique($users)),
            'mappings' => $mappings,
            'prefix' => $prefix,
        ]);
        break;

    case 'create_database':
        $dbname = isset($_POST['dbname']) ? trim($_POST['dbname']) : '';
        if (empty($dbname)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a database name']);
            exit;
        }
        // Prepend prefix if not already present
        $prefix = $cpUsername . '_';
        $fullName = (strpos($dbname, $prefix) === 0) ? $dbname : $prefix . $dbname;

        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=Mysql"
             . "&cpanel_jsonapi_func=create_database"
             . "&name=" . urlencode($fullName);

        $p = broodle_ajax_parse_result(broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url));
        echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'Database created: ' . $fullName : $p['error']]);
        break;

    case 'create_db_user':
        $dbuser = isset($_POST['dbuser']) ? trim($_POST['dbuser']) : '';
        $dbpass = isset($_POST['dbpass']) ? $_POST['dbpass'] : '';
        if (empty($dbuser) || empty($dbpass)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
            exit;
        }
        $prefix = $cpUsername . '_';
        $fullUser = (strpos($dbuser, $prefix) === 0) ? $dbuser : $prefix . $dbuser;

        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=Mysql"
             . "&cpanel_jsonapi_func=create_user"
             . "&name=" . urlencode($fullUser)
             . "&password=" . urlencode($dbpass);

        $p = broodle_ajax_parse_result(broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url));
        echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'User created: ' . $fullUser : $p['error']]);
        break;

    case 'delete_database':
        $database = isset($_POST['database']) ? trim($_POST['database']) : '';
        if (empty($database)) {
            echo json_encode(['success' => false, 'message' => 'Missing database name']);
            exit;
        }

        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=Mysql"
             . "&cpanel_jsonapi_func=delete_database"
             . "&name=" . urlencode($database);

        $p = broodle_ajax_parse_result(broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url));
        echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'Database deleted' : $p['error']]);
        break;

    case 'delete_db_user':
        $dbuser = isset($_POST['dbuser']) ? trim($_POST['dbuser']) : '';
        if (empty($dbuser)) {
            echo json_encode(['success' => false, 'message' => 'Missing username']);
            exit;
        }

        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=Mysql"
             . "&cpanel_jsonapi_func=delete_user"
             . "&name=" . urlencode($dbuser);

        $p = broodle_ajax_parse_result(broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url));
        echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'User deleted' : $p['error']]);
        break;

    case 'assign_db_user':
        $database = isset($_POST['database']) ? trim($_POST['database']) : '';
        $dbuser   = isset($_POST['dbuser']) ? trim($_POST['dbuser']) : '';
        $privileges = isset($_POST['privileges']) ? trim($_POST['privileges']) : 'ALL PRIVILEGES';

        if (empty($database) || empty($dbuser)) {
            echo json_encode(['success' => false, 'message' => 'Select a database and user']);
            exit;
        }

        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=Mysql"
             . "&cpanel_jsonapi_func=set_privileges_on_database"
             . "&user=" . urlencode($dbuser)
             . "&database=" . urlencode($database)
             . "&privileges=" . urlencode($privileges);

        $p = broodle_ajax_parse_result(broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url));
        echo json_encode(['success' => $p['ok'], 'message' => $p['ok'] ? 'Privileges assigned successfully' : $p['error']]);
        break;

    case 'get_phpmyadmin_url':
        // Create a cPanel session and return phpMyAdmin URL
        $url = "{$protocol}://{$hostname}:{$port}/json-api/create_user_session"
             . "?api.version=1"
             . "&user=" . urlencode($cpUsername)
             . "&service=cpaneld"
             . "&locale=en";

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $sessionUrl = $json['data']['url'] ?? '';
            if (!empty($sessionUrl)) {
                if (preg_match('#(https?://[^/]+/cpsess[^/]+)#', $sessionUrl, $m)) {
                    $pmaUrl = $m[1] . '/3rdparty/phpMyAdmin/index.php';
                    echo json_encode(['success' => true, 'url' => $pmaUrl]);
                    break;
                }
                echo json_encode(['success' => true, 'url' => $sessionUrl]);
                break;
            }
        }

        // Fallback: direct cPanel URL
        $cpPort = $secure ? 2083 : 2082;
        echo json_encode(['success' => true, 'url' => "{$protocol}://{$hostname}:{$cpPort}/3rdparty/phpMyAdmin/index.php"]);
        break;

    // ── SSL Management Actions ──

    case 'ssl_status':
        // Step 1: Get all domains for this account
        $urlDomains = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=DomainInfo"
             . "&cpanel_jsonapi_func=list_domains";
        $rDom = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $urlDomains);
        $allDomains = [];
        if ($rDom['code'] === 200 && $rDom['body']) {
            $jsonDom = json_decode($rDom['body'], true);
            $domData = $jsonDom['result']['data'] ?? [];
            if (!empty($domData['main_domain'])) $allDomains[] = $domData['main_domain'];
            if (!empty($domData['addon_domains'])) $allDomains = array_merge($allDomains, $domData['addon_domains']);
            if (!empty($domData['parked_domains'])) $allDomains = array_merge($allDomains, $domData['parked_domains']);
        }
        if (empty($allDomains) && !empty($service->domain)) $allDomains[] = $service->domain;
        $allDomains = array_unique($allDomains);

        // Step 2: Get SSL info via installed_hosts
        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=SSL"
             . "&cpanel_jsonapi_func=installed_hosts";

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url, 30);

        // Build a map of domain => cert info from installed_hosts
        $sslMap = [];
        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $hosts = $json['result']['data'] ?? [];

            if (is_array($hosts)) {
                foreach ($hosts as $host) {
                    if (!is_array($host)) continue;
                    $domain = $host['servername'] ?? '';
                    if (!$domain) continue;

                    $cert = [];
                    $cert['domain'] = $domain;
                    $cert['has_cert'] = true;

                    // Certificate details are nested under 'certificate' object
                    $certObj = $host['certificate'] ?? [];
                    if (!is_array($certObj)) $certObj = [];

                    // Issuer is a nested object: certificate.issuer.organizationName / certificate.issuer.commonName
                    $issuerObj = $certObj['issuer'] ?? [];
                    if (is_array($issuerObj)) {
                        $issuerOrg = $issuerObj['organizationName'] ?? '';
                        $issuerCN = $issuerObj['commonName'] ?? '';
                    } else {
                        // Fallback if issuer is a string
                        $issuerOrg = '';
                        $issuerCN = is_string($issuerObj) ? $issuerObj : '';
                    }
                    $cert['issuer'] = $issuerOrg ?: $issuerCN ?: 'Unknown';

                    // Self-signed: certificate.is_self_signed (integer 0/1)
                    $isSelfSigned = !empty($certObj['is_self_signed']);

                    $cert['is_self_signed'] = $isSelfSigned;
                    $cert['self_signed'] = $isSelfSigned;

                    // Expiry: certificate.not_after (epoch)
                    $expiryEpoch = $certObj['not_after'] ?? null;
                    if ($expiryEpoch) {
                        $cert['expiry_epoch'] = (int) $expiryEpoch;
                        $cert['expiry_date'] = date('Y-m-d', (int) $expiryEpoch);
                    }

                    // AutoSSL flag: certificate.is_autossl (integer 0/1)
                    $isAutoSSL = !empty($certObj['is_autossl']);

                    // Type detection
                    $issuerLower = strtolower($cert['issuer']);
                    if ($isSelfSigned) {
                        $cert['type'] = 'self-signed';
                    } elseif ($isAutoSSL) {
                        // Use the autossl provider display name if available
                        $providerName = $certObj['auto_ssl_provider_display_name'] ?? ($certObj['auto_ssl_provider'] ?? '');
                        if ($providerName) {
                            $cert['issuer'] = $providerName . ' (AutoSSL)';
                        } elseif (strpos($issuerLower, 'let\'s encrypt') !== false || strpos($issuerLower, 'letsencrypt') !== false) {
                            $cert['issuer'] = "Let's Encrypt";
                        } elseif (strpos($issuerLower, 'comodo') !== false || strpos($issuerLower, 'sectigo') !== false) {
                            $cert['issuer'] = 'Sectigo (AutoSSL)';
                        } else {
                            $cert['issuer'] = $cert['issuer'] ?: 'AutoSSL';
                        }
                        $cert['type'] = 'autossl';
                    } elseif (strpos($issuerLower, 'let\'s encrypt') !== false || strpos($issuerLower, 'letsencrypt') !== false) {
                        $cert['type'] = 'autossl';
                        $cert['issuer'] = "Let's Encrypt";
                    } elseif (strpos($issuerLower, 'comodo') !== false || strpos($issuerLower, 'sectigo') !== false) {
                        $cert['type'] = 'autossl';
                        $cert['issuer'] = 'Sectigo (AutoSSL)';
                    } elseif (strpos($issuerLower, 'cpanel') !== false) {
                        $cert['type'] = 'autossl';
                        $cert['issuer'] = 'cPanel AutoSSL';
                    } else {
                        $cert['type'] = 'commercial';
                    }

                    $sslMap[strtolower($domain)] = $cert;

                    // Also map all covered domains from certificate.domains array
                    $coveredDomains = $certObj['domains'] ?? [];
                    if (is_array($coveredDomains)) {
                        foreach ($coveredDomains as $covDom) {
                            if (is_string($covDom) && !isset($sslMap[strtolower($covDom)])) {
                                $covCert = $cert;
                                $covCert['domain'] = $covDom;
                                $sslMap[strtolower($covDom)] = $covCert;
                            }
                        }
                    }
                }
            }
        }

        // Step 3: Merge — every domain gets an entry, with SSL info if available
        $certificates = [];
        foreach ($allDomains as $dom) {
            $key = strtolower($dom);
            if (isset($sslMap[$key])) {
                $certificates[] = $sslMap[$key];
                unset($sslMap[$key]);
            } else {
                $certificates[] = [
                    'domain' => $dom,
                    'has_cert' => false,
                    'issuer' => '',
                    'is_self_signed' => false,
                    'self_signed' => false,
                    'type' => 'none',
                ];
            }
        }
        // Add any remaining SSL hosts not in the domain list (e.g. mail/cpanel subdomains)
        foreach ($sslMap as $extra) {
            $certificates[] = $extra;
        }

        echo json_encode(['success' => true, 'certificates' => $certificates]);
        break;

    case 'start_autossl':
        // Use WHM API start_autossl_check_for_one_user (more reliable than UAPI when called via WHM)
        $url = "{$protocol}://{$hostname}:{$port}/json-api/start_autossl_check_for_one_user"
             . "?api.version=1"
             . "&username=" . urlencode($cpUsername);

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url, 30);

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $meta = $json['metadata'] ?? [];
            $status = $meta['result'] ?? ($json['result']['status'] ?? 0);
            if ($status == 1) {
                echo json_encode(['success' => true, 'message' => 'AutoSSL check started']);
            } else {
                $err = $meta['reason'] ?? ($json['result']['errors'][0] ?? 'Failed to start AutoSSL');
                // Fallback: try UAPI method
                $urlUapi = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
                     . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
                     . "&cpanel_jsonapi_apiversion=3"
                     . "&cpanel_jsonapi_module=SSL"
                     . "&cpanel_jsonapi_func=start_autossl_check";
                $r2 = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $urlUapi, 30);
                if ($r2['code'] === 200 && $r2['body']) {
                    $json2 = json_decode($r2['body'], true);
                    if (($json2['result']['status'] ?? 0) == 1) {
                        echo json_encode(['success' => true, 'message' => 'AutoSSL check started']);
                        break;
                    }
                }
                echo json_encode(['success' => false, 'message' => $err]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to connect to server']);
        }
        break;

    case 'autossl_progress':
        // Use UAPI SSL::is_autossl_check_in_progress
        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=SSL"
             . "&cpanel_jsonapi_func=is_autossl_check_in_progress";

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $inProgress = $json['result']['data'] ?? false;
            // The API returns 1 or true if in progress
            echo json_encode(['success' => true, 'in_progress' => (bool) $inProgress]);
        } else {
            echo json_encode(['success' => true, 'in_progress' => false]);
        }
        break;

    case 'autossl_problems':
        // Use UAPI SSL::get_autossl_problems
        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=SSL"
             . "&cpanel_jsonapi_func=get_autossl_problems";

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
        $problems = [];

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $data = $json['result']['data'] ?? [];
            if (is_array($data)) {
                foreach ($data as $item) {
                    if (!is_array($item)) continue;
                    $problems[] = [
                        'domain' => $item['domain'] ?? ($item['vhost_name'] ?? 'Unknown'),
                        'problem' => $item['problem'] ?? ($item['message'] ?? 'Unknown issue'),
                    ];
                }
            }
        }

        echo json_encode(['success' => true, 'problems' => $problems]);
        break;

    // ── DNS Management Actions ──

    case 'dns_list_domains':
        // Get all domains for this cPanel account
        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=DomainInfo"
             . "&cpanel_jsonapi_func=list_domains";

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
        $domains = [];
        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $data = $json['result']['data'] ?? [];
            if (!empty($data['main_domain'])) $domains[] = ['domain' => $data['main_domain'], 'type' => 'main'];
            if (!empty($data['addon_domains'])) {
                foreach ($data['addon_domains'] as $d) $domains[] = ['domain' => $d, 'type' => 'addon'];
            }
            if (!empty($data['parked_domains'])) {
                foreach ($data['parked_domains'] as $d) $domains[] = ['domain' => $d, 'type' => 'parked'];
            }
            if (!empty($data['sub_domains'])) {
                $addonSet = array_map('strtolower', $data['addon_domains'] ?? []);
                $mainDomain = strtolower($data['main_domain'] ?? '');
                foreach ($data['sub_domains'] as $sd) {
                    $sdLower = strtolower($sd);
                    $isAddonSub = false;
                    foreach ($addonSet as $ad) {
                        if ($sdLower === strtolower($ad) . '.' . $mainDomain) { $isAddonSub = true; break; }
                    }
                    if (!$isAddonSub) $domains[] = ['domain' => $sd, 'type' => 'sub'];
                }
            }
        }
        if (empty($domains) && !empty($service->domain)) {
            $domains[] = ['domain' => $service->domain, 'type' => 'main'];
        }
        echo json_encode(['success' => true, 'domains' => $domains]);
        break;

    case 'dns_fetch_records':
        $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        if (empty($domain)) {
            echo json_encode(['success' => false, 'message' => 'Missing domain']);
            exit;
        }

        // Use cPanel API 2 ZoneEdit::fetchzone_records (no UAPI equivalent exists)
        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=2"
             . "&cpanel_jsonapi_module=ZoneEdit"
             . "&cpanel_jsonapi_func=fetchzone_records"
             . "&domain=" . urlencode($domain)
             . "&customonly=0";

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url, 30);
        $records = [];
        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $data = $json['cpanelresult']['data'] ?? [];
            foreach ($data as $rec) {
                if (!is_array($rec)) continue;
                $type = $rec['type'] ?? '';
                // Skip raw/comment lines and $TTL directives
                if ($type === ':RAW' || $type === '$TTL' || $type === '') continue;
                $record = [
                    'line'    => $rec['Line'] ?? ($rec['line'] ?? 0),
                    'type'    => $type,
                    'name'    => $rec['name'] ?? '',
                    'ttl'     => (int)($rec['ttl'] ?? 14400),
                    'class'   => $rec['class'] ?? 'IN',
                ];
                switch ($type) {
                    case 'A':
                    case 'AAAA':
                        $record['address'] = $rec['address'] ?? '';
                        break;
                    case 'CNAME':
                        $record['cname'] = $rec['cname'] ?? '';
                        break;
                    case 'MX':
                        $record['exchange'] = $rec['exchange'] ?? '';
                        $record['preference'] = (int)($rec['preference'] ?? 0);
                        break;
                    case 'TXT':
                        $record['txtdata'] = $rec['txtdata'] ?? '';
                        break;
                    case 'SRV':
                        $record['priority'] = (int)($rec['priority'] ?? 0);
                        $record['weight'] = (int)($rec['weight'] ?? 0);
                        $record['port'] = (int)($rec['port'] ?? 0);
                        $record['target'] = $rec['target'] ?? '';
                        break;
                    case 'CAA':
                        $record['flag'] = (int)($rec['flag'] ?? 0);
                        $record['tag'] = $rec['tag'] ?? '';
                        $record['value'] = $rec['value'] ?? '';
                        break;
                    case 'NS':
                        $record['nsdname'] = $rec['nsdname'] ?? '';
                        break;
                    case 'SOA':
                        $record['mname'] = $rec['mname'] ?? '';
                        $record['rname'] = $rec['rname'] ?? '';
                        $record['serial'] = $rec['serial'] ?? '';
                        $record['refresh'] = (int)($rec['refresh'] ?? 0);
                        $record['retry'] = (int)($rec['retry'] ?? 0);
                        $record['expire'] = (int)($rec['expire'] ?? 0);
                        $record['minimum'] = (int)($rec['minimum'] ?? 0);
                        break;
                }
                $records[] = $record;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch DNS records']);
            exit;
        }
        echo json_encode(['success' => true, 'records' => $records, 'domain' => $domain]);
        break;

    case 'dns_add_record':
        $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        $type   = isset($_POST['type']) ? strtoupper(trim($_POST['type'])) : '';
        $name   = isset($_POST['name']) ? trim($_POST['name']) : '';
        $ttl    = isset($_POST['ttl']) ? (int) $_POST['ttl'] : 14400;

        if (empty($domain) || empty($type)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        // Build the API URL based on record type
        $params = "&domain=" . urlencode($domain)
                . "&name=" . urlencode($name)
                . "&type=" . urlencode($type)
                . "&ttl=" . $ttl
                . "&class=IN";

        switch ($type) {
            case 'A':
            case 'AAAA':
                $address = isset($_POST['address']) ? trim($_POST['address']) : '';
                if (empty($address)) { echo json_encode(['success' => false, 'message' => 'IP address is required']); exit; }
                $params .= "&address=" . urlencode($address);
                break;
            case 'CNAME':
                $cname = isset($_POST['cname']) ? trim($_POST['cname']) : '';
                if (empty($cname)) { echo json_encode(['success' => false, 'message' => 'CNAME target is required']); exit; }
                $params .= "&cname=" . urlencode($cname);
                break;
            case 'MX':
                $exchange = isset($_POST['exchange']) ? trim($_POST['exchange']) : '';
                $preference = isset($_POST['preference']) ? (int) $_POST['preference'] : 10;
                if (empty($exchange)) { echo json_encode(['success' => false, 'message' => 'Mail server is required']); exit; }
                $params .= "&exchange=" . urlencode($exchange) . "&preference=" . $preference;
                break;
            case 'TXT':
                $txtdata = isset($_POST['txtdata']) ? trim($_POST['txtdata']) : '';
                if (empty($txtdata)) { echo json_encode(['success' => false, 'message' => 'TXT data is required']); exit; }
                $params .= "&txtdata=" . urlencode($txtdata);
                break;
            case 'SRV':
                $priority = isset($_POST['priority']) ? (int) $_POST['priority'] : 0;
                $weight = isset($_POST['weight']) ? (int) $_POST['weight'] : 0;
                $srvPort = isset($_POST['port']) ? (int) $_POST['port'] : 0;
                $target = isset($_POST['target']) ? trim($_POST['target']) : '';
                if (empty($target)) { echo json_encode(['success' => false, 'message' => 'Target is required']); exit; }
                $params .= "&priority=" . $priority . "&weight=" . $weight . "&port=" . $srvPort . "&target=" . urlencode($target);
                break;
            case 'CAA':
                $flag = isset($_POST['flag']) ? (int) $_POST['flag'] : 0;
                $tag = isset($_POST['tag']) ? trim($_POST['tag']) : 'issue';
                $value = isset($_POST['value']) ? trim($_POST['value']) : '';
                if (empty($value)) { echo json_encode(['success' => false, 'message' => 'CAA value is required']); exit; }
                $params .= "&flag=" . $flag . "&tag=" . urlencode($tag) . "&value=" . urlencode($value);
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Unsupported record type: ' . $type]);
                exit;
        }

        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=2"
             . "&cpanel_jsonapi_module=ZoneEdit"
             . "&cpanel_jsonapi_func=add_zone_record"
             . $params;

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $cpResult = $json['cpanelresult']['data'][0] ?? [];
            $status = $cpResult['result']['status'] ?? ($cpResult['status'] ?? ($cpResult['result'] ?? 0));
            if ($status == 1 || $status === true) {
                $newLine = $cpResult['result']['newserial'] ?? ($cpResult['newserial'] ?? '');
                echo json_encode(['success' => true, 'message' => 'DNS record added successfully', 'newserial' => $newLine]);
            } else {
                $err = $cpResult['result']['statusmsg'] ?? ($cpResult['statusmsg'] ?? ($cpResult['result']['message'] ?? 'Failed to add record'));
                echo json_encode(['success' => false, 'message' => $err]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to connect to server']);
        }
        break;

    case 'dns_edit_record':
        $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        $line   = isset($_POST['line']) ? (int) $_POST['line'] : 0;
        $type   = isset($_POST['type']) ? strtoupper(trim($_POST['type'])) : '';
        $name   = isset($_POST['name']) ? trim($_POST['name']) : '';
        $ttl    = isset($_POST['ttl']) ? (int) $_POST['ttl'] : 14400;

        if (empty($domain) || !$line || empty($type)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $params = "&domain=" . urlencode($domain)
                . "&Line=" . $line
                . "&name=" . urlencode($name)
                . "&type=" . urlencode($type)
                . "&ttl=" . $ttl
                . "&class=IN";

        switch ($type) {
            case 'A':
            case 'AAAA':
                $address = isset($_POST['address']) ? trim($_POST['address']) : '';
                $params .= "&address=" . urlencode($address);
                break;
            case 'CNAME':
                $cname = isset($_POST['cname']) ? trim($_POST['cname']) : '';
                $params .= "&cname=" . urlencode($cname);
                break;
            case 'MX':
                $exchange = isset($_POST['exchange']) ? trim($_POST['exchange']) : '';
                $preference = isset($_POST['preference']) ? (int) $_POST['preference'] : 10;
                $params .= "&exchange=" . urlencode($exchange) . "&preference=" . $preference;
                break;
            case 'TXT':
                $txtdata = isset($_POST['txtdata']) ? trim($_POST['txtdata']) : '';
                $params .= "&txtdata=" . urlencode($txtdata);
                break;
            case 'SRV':
                $priority = isset($_POST['priority']) ? (int) $_POST['priority'] : 0;
                $weight = isset($_POST['weight']) ? (int) $_POST['weight'] : 0;
                $srvPort = isset($_POST['port']) ? (int) $_POST['port'] : 0;
                $target = isset($_POST['target']) ? trim($_POST['target']) : '';
                $params .= "&priority=" . $priority . "&weight=" . $weight . "&port=" . $srvPort . "&target=" . urlencode($target);
                break;
            case 'CAA':
                $flag = isset($_POST['flag']) ? (int) $_POST['flag'] : 0;
                $tag = isset($_POST['tag']) ? trim($_POST['tag']) : 'issue';
                $value = isset($_POST['value']) ? trim($_POST['value']) : '';
                $params .= "&flag=" . $flag . "&tag=" . urlencode($tag) . "&value=" . urlencode($value);
                break;
        }

        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=2"
             . "&cpanel_jsonapi_module=ZoneEdit"
             . "&cpanel_jsonapi_func=edit_zone_record"
             . $params;

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $cpResult = $json['cpanelresult']['data'][0] ?? [];
            $status = $cpResult['result']['status'] ?? ($cpResult['status'] ?? ($cpResult['result'] ?? 0));
            if ($status == 1 || $status === true) {
                echo json_encode(['success' => true, 'message' => 'DNS record updated successfully']);
            } else {
                $err = $cpResult['result']['statusmsg'] ?? ($cpResult['statusmsg'] ?? 'Failed to update record');
                echo json_encode(['success' => false, 'message' => $err]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to connect to server']);
        }
        break;

    case 'dns_delete_record':
        $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        $line   = isset($_POST['line']) ? (int) $_POST['line'] : 0;

        if (empty($domain) || !$line) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=2"
             . "&cpanel_jsonapi_module=ZoneEdit"
             . "&cpanel_jsonapi_func=remove_zone_record"
             . "&domain=" . urlencode($domain)
             . "&line=" . $line;

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $cpResult = $json['cpanelresult']['data'][0] ?? [];
            $status = $cpResult['result']['status'] ?? ($cpResult['status'] ?? ($cpResult['result'] ?? 0));
            if ($status == 1 || $status === true) {
                echo json_encode(['success' => true, 'message' => 'DNS record deleted']);
            } else {
                $err = $cpResult['result']['statusmsg'] ?? ($cpResult['statusmsg'] ?? 'Failed to delete record');
                echo json_encode(['success' => false, 'message' => $err]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to connect to server']);
        }
        break;

    case 'dns_bulk_delete':
        $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        $lines  = isset($_POST['lines']) ? trim($_POST['lines']) : '';

        if (empty($domain) || empty($lines)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $lineArr = array_filter(array_map('intval', explode(',', $lines)));
        if (empty($lineArr)) {
            echo json_encode(['success' => false, 'message' => 'No valid line numbers']);
            exit;
        }

        // Delete in reverse order to avoid line number shifts
        rsort($lineArr);
        $deleted = 0;
        $errors = [];
        foreach ($lineArr as $line) {
            $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
                 . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
                 . "&cpanel_jsonapi_apiversion=2"
                 . "&cpanel_jsonapi_module=ZoneEdit"
                 . "&cpanel_jsonapi_func=remove_zone_record"
                 . "&domain=" . urlencode($domain)
                 . "&line=" . $line;

            $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
            if ($r['code'] === 200 && $r['body']) {
                $json = json_decode($r['body'], true);
                $cpResult = $json['cpanelresult']['data'][0] ?? [];
                $status = $cpResult['result']['status'] ?? ($cpResult['status'] ?? ($cpResult['result'] ?? 0));
                if ($status == 1 || $status === true) {
                    $deleted++;
                } else {
                    $errors[] = "Line {$line}: " . ($cpResult['result']['statusmsg'] ?? 'Failed');
                }
            } else {
                $errors[] = "Line {$line}: Connection failed";
            }
        }

        $msg = "Deleted {$deleted} of " . count($lineArr) . " records";
        if (!empty($errors)) $msg .= '. Errors: ' . implode('; ', array_slice($errors, 0, 3));
        echo json_encode(['success' => $deleted > 0, 'message' => $msg, 'deleted' => $deleted, 'total' => count($lineArr)]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

