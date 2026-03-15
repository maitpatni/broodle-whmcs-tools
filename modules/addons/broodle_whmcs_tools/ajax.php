<?php
/**
 * Broodle WHMCS Tools — AJAX Handler for Email Actions
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
$featureKey = in_array($action, $domainActions) ? 'tweak_domain_management' : 'tweak_email_list';
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
function broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url)
{
    $headers = [];
    if (!empty($accessHash)) {
        $headers[] = "Authorization: whm {$serverUser}:{$accessHash}";
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
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

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $status = $json['result']['status'] ?? ($json['cpanelresult']['data'][0]['result'] ?? null);
            if ($status == 1 || $status === true) {
                echo json_encode(['success' => true, 'message' => 'Email account created successfully']);
            } else {
                $err = $json['result']['errors'][0] ?? ($json['cpanelresult']['data'][0]['reason'] ?? 'Unknown error');
                echo json_encode(['success' => false, 'message' => $err]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to connect to server']);
        }
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

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $status = $json['result']['status'] ?? ($json['cpanelresult']['data'][0]['result'] ?? null);
            if ($status == 1 || $status === true) {
                echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
            } else {
                $err = $json['result']['errors'][0] ?? ($json['cpanelresult']['data'][0]['reason'] ?? 'Unknown error');
                echo json_encode(['success' => false, 'message' => $err]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to connect to server']);
        }
        break;

    case 'delete_email':
        $emailFull = isset($_POST['email']) ? trim($_POST['email']) : '';

        if (empty($emailFull)) {
            echo json_encode(['success' => false, 'message' => 'Missing email']);
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
             . "&cpanel_jsonapi_func=delete_pop"
             . "&email=" . urlencode($parts[0])
             . "&domain=" . urlencode($parts[1]);

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $status = $json['result']['status'] ?? ($json['cpanelresult']['data'][0]['result'] ?? null);
            if ($status == 1 || $status === true) {
                echo json_encode(['success' => true, 'message' => 'Email account deleted']);
            } else {
                $err = $json['result']['errors'][0] ?? ($json['cpanelresult']['data'][0]['reason'] ?? 'Unknown error');
                echo json_encode(['success' => false, 'message' => $err]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to connect to server']);
        }
        break;

    case 'webmail_login':
        $emailFull = isset($_POST['email']) ? trim($_POST['email']) : '';

        if (empty($emailFull)) {
            echo json_encode(['success' => false, 'message' => 'Missing email']);
            exit;
        }

        // Create a session token for webmail login via WHM
        $url = "{$protocol}://{$hostname}:{$port}/json-api/create_user_session"
             . "?api.version=1"
             . "&user=" . urlencode($cpUsername)
             . "&service=webmaild";

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $sessionUrl = $json['data']['url'] ?? '';
            if (!empty($sessionUrl)) {
                echo json_encode(['success' => true, 'url' => $sessionUrl]);
            } else {
                // Fallback: direct webmail URL
                $webmailPort = $secure ? 2096 : 2095;
                $webmailUrl = "{$protocol}://{$hostname}:{$webmailPort}/";
                echo json_encode(['success' => true, 'url' => $webmailUrl]);
            }
        } else {
            $webmailPort = $secure ? 2096 : 2095;
            $webmailUrl = "{$protocol}://{$hostname}:{$webmailPort}/";
            echo json_encode(['success' => true, 'url' => $webmailUrl]);
        }
        break;

    case 'get_domains':
        // Get domains associated with this cPanel account
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
                        // sub_domains often include the main domain suffix, skip those
                        if (!in_array($sd, $domains)) $domains[] = $sd;
                    }
                }
            }
        }

        // Fallback: use the service domain
        if (empty($domains) && !empty($service->domain)) {
            $domains[] = $service->domain;
        }

        $domains = array_unique($domains);
        sort($domains);
        echo json_encode(['success' => true, 'domains' => $domains]);
        break;

    case 'get_parent_domains':
        // Get main + addon domains (for subdomain parent selection)
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
        if (empty($domains) && !empty($service->domain)) {
            $domains[] = $service->domain;
        }
        $domains = array_unique($domains);
        sort($domains);
        echo json_encode(['success' => true, 'domains' => $domains]);
        break;

    case 'add_addon_domain':
        $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        $docroot = isset($_POST['docroot']) ? trim($_POST['docroot']) : '';

        if (empty($domain)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a domain name']);
            exit;
        }
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $domain)) {
            echo json_encode(['success' => false, 'message' => 'Invalid domain name']);
            exit;
        }
        if (empty($docroot)) $docroot = $domain;

        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=AddonDomain"
             . "&cpanel_jsonapi_func=addaddondomain"
             . "&newdomain=" . urlencode($domain)
             . "&subdomain=" . urlencode(str_replace('.', '', $domain))
             . "&dir=" . urlencode($docroot);

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $status = $json['result']['status'] ?? ($json['cpanelresult']['data'][0]['result'] ?? null);
            if ($status == 1 || $status === true) {
                echo json_encode(['success' => true, 'message' => 'Addon domain added successfully']);
            } else {
                $err = $json['result']['errors'][0] ?? ($json['cpanelresult']['data'][0]['reason'] ?? 'Failed to add domain. You may have reached your plan limit.');
                echo json_encode(['success' => false, 'message' => $err]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to connect to server']);
        }
        break;

    case 'add_subdomain':
        $subdomain = isset($_POST['subdomain']) ? trim($_POST['subdomain']) : '';
        $domain    = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        $docroot   = isset($_POST['docroot']) ? trim($_POST['docroot']) : '';

        if (empty($subdomain) || empty($domain)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
            exit;
        }
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?$/', $subdomain)) {
            echo json_encode(['success' => false, 'message' => 'Invalid subdomain name']);
            exit;
        }
        if (empty($docroot)) $docroot = $subdomain . '.' . $domain;

        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=SubDomain"
             . "&cpanel_jsonapi_func=addsubdomain"
             . "&domain=" . urlencode($subdomain)
             . "&rootdomain=" . urlencode($domain)
             . "&dir=" . urlencode($docroot);

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $status = $json['result']['status'] ?? ($json['cpanelresult']['data'][0]['result'] ?? null);
            if ($status == 1 || $status === true) {
                echo json_encode(['success' => true, 'message' => 'Subdomain created successfully']);
            } else {
                $err = $json['result']['errors'][0] ?? ($json['cpanelresult']['data'][0]['reason'] ?? 'Failed to create subdomain');
                echo json_encode(['success' => false, 'message' => $err]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to connect to server']);
        }
        break;

    case 'delete_domain':
        $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        $type   = isset($_POST['type']) ? trim($_POST['type']) : '';

        if (empty($domain) || empty($type)) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }

        if ($type === 'main') {
            echo json_encode(['success' => false, 'message' => 'Cannot delete the primary domain']);
            exit;
        }

        $url = '';
        if ($type === 'addon') {
            $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
                 . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
                 . "&cpanel_jsonapi_apiversion=3"
                 . "&cpanel_jsonapi_module=AddonDomain"
                 . "&cpanel_jsonapi_func=deladdondomain"
                 . "&domain=" . urlencode($domain)
                 . "&subdomain=" . urlencode(str_replace('.', '', $domain) . '.' . $service->domain);
        } elseif ($type === 'sub') {
            $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
                 . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
                 . "&cpanel_jsonapi_apiversion=3"
                 . "&cpanel_jsonapi_module=SubDomain"
                 . "&cpanel_jsonapi_func=delsubdomain"
                 . "&domain=" . urlencode($domain);
        } elseif ($type === 'parked') {
            $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
                 . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
                 . "&cpanel_jsonapi_apiversion=3"
                 . "&cpanel_jsonapi_module=Park"
                 . "&cpanel_jsonapi_func=unpark"
                 . "&domain=" . urlencode($domain);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unknown domain type']);
            exit;
        }

        $r = broodle_ajax_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $status = $json['result']['status'] ?? ($json['cpanelresult']['data'][0]['result'] ?? null);
            if ($status == 1 || $status === true) {
                echo json_encode(['success' => true, 'message' => 'Domain deleted successfully']);
            } else {
                $err = $json['result']['errors'][0] ?? ($json['cpanelresult']['data'][0]['reason'] ?? 'Failed to delete domain');
                echo json_encode(['success' => false, 'message' => $err]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to connect to server']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
