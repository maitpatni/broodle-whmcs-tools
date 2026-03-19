<?php
/**
 * Broodle WHMCS Tools — Hooks
 *
 * @package    BroodleWHMCSTools
 * @author     Broodle
 * @link       https://broodle.host
 */

if (!defined('BROODLE_TOOLS_VERSION')) {
    // Read version from main module file dynamically
    $btMainFile = __DIR__ . '/broodle_whmcs_tools.php';
    $btVer = '0.0.0';
    if (file_exists($btMainFile)) {
        $btContent = @file_get_contents($btMainFile, false, null, 0, 2048);
        if ($btContent && preg_match("/define\(\s*'BROODLE_TOOLS_VERSION'\s*,\s*'([^']+)'/", $btContent, $btM)) {
            $btVer = $btM[1];
        }
    }
    define('BROODLE_TOOLS_VERSION', $btVer);
}

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

use WHMCS\Database\Capsule;
use WHMCS\View\Menu\Item as MenuItem;

/* ─── DIAGNOSTIC: Remove after confirming hooks load ─── */
/* (diagnostic removed — hooks confirmed working) */

/* ─── Helpers ─────────────────────────────────────────────── */

function broodle_tools_setting_enabled($key)
{
    try {
        return Capsule::table('mod_broodle_tools_settings')
            ->where('setting_key', $key)
            ->value('setting_value') === '1';
    } catch (\Exception $e) {
        return false;
    }
}

function broodle_tools_ns_enabled() { return broodle_tools_setting_enabled('tweak_nameservers_tab'); }
function broodle_tools_email_enabled() { return broodle_tools_setting_enabled('tweak_email_list'); }
function broodle_tools_wp_enabled() { return broodle_tools_setting_enabled('tweak_wordpress_toolkit'); }
function broodle_tools_domain_enabled() { return broodle_tools_setting_enabled('tweak_domain_management'); }
function broodle_tools_db_enabled() { return broodle_tools_setting_enabled('tweak_database_management'); }
function broodle_tools_ssl_enabled() { return broodle_tools_setting_enabled('tweak_ssl_management'); }
function broodle_tools_dns_enabled() { return broodle_tools_setting_enabled('tweak_dns_management'); }
function broodle_tools_cron_enabled() { return broodle_tools_setting_enabled('tweak_cron_management'); }
function broodle_tools_php_enabled() { return broodle_tools_setting_enabled('tweak_php_version'); }
function broodle_tools_logs_enabled() { return broodle_tools_setting_enabled('tweak_error_logs'); }
function broodle_tools_fm_enabled() { return broodle_tools_setting_enabled('tweak_file_manager'); }
function broodle_tools_analytics_enabled() { return broodle_tools_setting_enabled('tweak_analytics'); }
function broodle_tools_upgrade_list_enabled() { return broodle_tools_setting_enabled('tweak_upgrade_list_layout'); }
function broodle_tools_v2_dropdown_enabled() { return broodle_tools_setting_enabled('tweak_manage_v2_dropdown'); }
function broodle_tools_v2_banner_enabled() { return broodle_tools_setting_enabled('tweak_manage_v2_banner'); }

function broodle_tools_get_mail_app_settings() {
    try {
        $rows = Capsule::table('mod_broodle_tools_settings')
            ->whereIn('setting_key', ['mail_app_enabled','mail_app_title','mail_app_description','mail_app_icon_url','mail_app_playstore','mail_app_appstore'])
            ->pluck('setting_value', 'setting_key');
        return [
            'enabled'     => !empty($rows['mail_app_enabled']) && $rows['mail_app_enabled'] === '1',
            'title'       => $rows['mail_app_title'] ?? 'Broodle Mail App',
            'description' => $rows['mail_app_description'] ?? '',
            'iconUrl'     => $rows['mail_app_icon_url'] ?? '',
            'playstore'   => $rows['mail_app_playstore'] ?? '',
            'appstore'    => $rows['mail_app_appstore'] ?? '',
        ];
    } catch (\Exception $e) {
        return ['enabled' => true, 'title' => 'Broodle Mail App', 'description' => '', 'iconUrl' => '', 'playstore' => '', 'appstore' => ''];
    }
}

function broodle_tools_get_service_id($vars)
{
    if (!empty($vars['serviceid'])) return (int) $vars['serviceid'];
    if (!empty($vars['id'])) return (int) $vars['id'];
    if (isset($vars['service']) && is_object($vars['service'])) return (int) $vars['service']->id;
    if (!empty($_GET['id'])) return (int) $_GET['id'];
    return 0;
}

function broodle_tools_get_cpanel_service($serviceId)
{
    if (!$serviceId) return null;
    try {
        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$service) return null;
        $product = Capsule::table('tblproducts')->where('id', $service->packageid)->first();
        if (!$product || strtolower($product->servertype) !== 'cpanel') return null;
        if (!$service->server) return null;
        $server = Capsule::table('tblservers')->where('id', $service->server)->first();
        if (!$server) return null;
        return ['service' => $service, 'server' => $server, 'product' => $product];
    } catch (\Exception $e) {
        return null;
    }
}

function broodle_tools_get_ns_for_service($serviceId)
{
    $data = broodle_tools_get_cpanel_service($serviceId);
    if (!$data) return ['ns' => [], 'ip' => ''];
    $server = $data['server'];
    $service = $data['service'];
    $ns = [];
    for ($i = 1; $i <= 5; $i++) {
        $f = 'nameserver' . $i;
        if (!empty($server->$f)) $ns[] = $server->$f;
    }
    $ip = '';
    if (!empty($service->dedicatedip)) $ip = $service->dedicatedip;
    elseif (!empty($server->ipaddress)) $ip = $server->ipaddress;
    return ['ns' => $ns, 'ip' => $ip];
}

function broodle_tools_get_emails($serviceId)
{
    $data = broodle_tools_get_cpanel_service($serviceId);
    if (!$data) return [];
    $server = $data['server']; $service = $data['service'];
    $username = $service->username;
    if (empty($username)) return [];
    $hostname = $server->hostname;
    $port = !empty($server->port) ? (int) $server->port : 2087;
    $serverUser = $server->username;
    $secure = !empty($server->secure) && ($server->secure === 'on' || $server->secure === '1' || $server->secure === 1);
    $protocol = $secure ? 'https' : 'http';
    $accessHash = ''; $password = '';
    if (!empty($server->accesshash)) {
        $raw = trim($server->accesshash);
        if (preg_match('/^[A-Za-z0-9]{10,64}$/', $raw)) { $accessHash = $raw; }
        else { $accessHash = trim(decrypt($raw)); if (empty($accessHash) || !preg_match('/^[A-Za-z0-9]{10,64}$/', $accessHash)) $accessHash = ''; }
    }
    if (empty($accessHash) && !empty($server->password)) $password = trim(decrypt($server->password));
    if (empty($accessHash) && empty($password)) return [];
    $headers = [];
    if (!empty($accessHash)) $headers[] = "Authorization: whm {$serverUser}:{$accessHash}";
    $emails = [];
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel?cpanel_jsonapi_user=" . urlencode($username) . "&cpanel_jsonapi_apiversion=3&cpanel_jsonapi_module=Email&cpanel_jsonapi_func=list_pops";
    $emails = broodle_tools_parse_email_response(broodle_tools_whm_get($url, $headers, $serverUser, $password), $username);
    if (empty($emails)) {
        $url2 = "{$protocol}://{$hostname}:{$port}/json-api/list_pops_for?api.version=1&user=" . urlencode($username);
        $r2 = broodle_tools_whm_get($url2, $headers, $serverUser, $password);
        if ($r2) { $pops = $r2['data']['pops'] ?? ($r2['pops'] ?? []); foreach ($pops as $e) { $em = is_string($e) ? $e : ($e['email'] ?? ($e['user'] ?? '')); if ($em && $em !== $username && strpos($em, '@') !== false) $emails[] = $em; } }
    }
    if (empty($emails)) {
        $url3 = "{$protocol}://{$hostname}:{$port}/json-api/cpanel?cpanel_jsonapi_user=" . urlencode($username) . "&cpanel_jsonapi_apiversion=3&cpanel_jsonapi_module=Email&cpanel_jsonapi_func=list_pops_with_disk";
        $emails = broodle_tools_parse_email_response(broodle_tools_whm_get($url3, $headers, $serverUser, $password), $username);
    }
    $emails = array_unique($emails); sort($emails); return $emails;
}

function broodle_tools_whm_get($url, $headers, $serverUser, $password)
{
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false]);
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    elseif (!empty($password)) curl_setopt($ch, CURLOPT_USERPWD, "{$serverUser}:{$password}");
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code !== 200 || !$resp) return null;
    return json_decode($resp, true);
}

function broodle_tools_parse_email_response($json, $username)
{
    if (empty($json)) return [];
    $pops = $json['result']['data'] ?? ($json['cpanelresult']['data'] ?? ($json['data'] ?? []));
    $emails = [];
    foreach ($pops as $entry) {
        $em = is_string($entry) ? $entry : ($entry['email'] ?? ($entry['login'] ?? ($entry['user'] ?? '')));
        if ($em && $em !== $username && strpos($em, '@') !== false) $emails[] = $em;
    }
    return $emails;
}

/* ─── Ensure defaults ─────────────────────────────────────── */

function broodle_tools_ensure_defaults()
{
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        if (!Capsule::schema()->hasTable('mod_broodle_tools_settings')) return;
        $defaults = [
            'tweak_nameservers_tab'    => '1',
            'tweak_email_list'         => '1',
            'tweak_wordpress_toolkit'  => '0',
            'tweak_domain_management'  => '1',
            'tweak_database_management'=> '1',
            'tweak_ssl_management'     => '1',
            'tweak_dns_management'     => '1',
            'tweak_cron_management'    => '1',
            'tweak_php_version'        => '1',
            'tweak_error_logs'         => '1',
            'tweak_file_manager'       => '1',
            'tweak_analytics'          => '1',
            'tweak_upgrade_list_layout'=> '0',
            'tweak_manage_v2_dropdown' => '1',
            'tweak_manage_v2_banner'   => '1',
            'auto_update_enabled'      => '0',
        ];
        foreach ($defaults as $key => $value) {
            if (!Capsule::table('mod_broodle_tools_settings')->where('setting_key', $key)->exists()) {
                Capsule::table('mod_broodle_tools_settings')->insert(['setting_key' => $key, 'setting_value' => $value, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
    } catch (\Exception $e) {}
}

/* ─── Domain helpers ──────────────────────────────────────── */

function broodle_tools_get_domains_detailed($serviceId)
{
    $data = broodle_tools_get_cpanel_service($serviceId);
    if (!$data) return ['main' => '', 'addon' => [], 'sub' => [], 'parked' => []];
    $server = $data['server']; $service = $data['service'];
    $username = $service->username;
    if (empty($username)) return ['main' => $service->domain ?? '', 'addon' => [], 'sub' => [], 'parked' => []];
    $hostname = $server->hostname; $port = !empty($server->port) ? (int) $server->port : 2087;
    $serverUser = $server->username;
    $secure = !empty($server->secure) && ($server->secure === 'on' || $server->secure === '1' || $server->secure === 1);
    $protocol = $secure ? 'https' : 'http';
    $accessHash = ''; $password = '';
    if (!empty($server->accesshash)) {
        $raw = trim($server->accesshash);
        if (preg_match('/^[A-Za-z0-9]{10,64}$/', $raw)) $accessHash = $raw;
        else { $accessHash = trim(decrypt($raw)); if (empty($accessHash) || !preg_match('/^[A-Za-z0-9]{10,64}$/', $accessHash)) $accessHash = ''; }
    }
    if (empty($accessHash) && !empty($server->password)) $password = trim(decrypt($server->password));
    if (empty($accessHash) && empty($password)) return ['main' => $service->domain ?? '', 'addon' => [], 'sub' => [], 'parked' => []];
    $headers = [];
    if (!empty($accessHash)) $headers[] = "Authorization: whm {$serverUser}:{$accessHash}";
    $result = ['main' => '', 'addon' => [], 'sub' => [], 'parked' => []];
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel?cpanel_jsonapi_user=" . urlencode($username) . "&cpanel_jsonapi_apiversion=3&cpanel_jsonapi_module=DomainInfo&cpanel_jsonapi_func=list_domains";
    $r = broodle_tools_whm_get($url, $headers, $serverUser, $password);
    if ($r) {
        $d = $r['result']['data'] ?? [];
        $result['main'] = $d['main_domain'] ?? ($service->domain ?? '');
        $result['addon'] = $d['addon_domains'] ?? [];
        $result['parked'] = $d['parked_domains'] ?? [];
        $subs = $d['sub_domains'] ?? [];
        $addonSet = array_map('strtolower', $result['addon']);
        $mainDomain = strtolower($result['main']);
        $filtered = [];
        foreach ($subs as $sd) {
            $sdLower = strtolower($sd); $isAddonSub = false;
            foreach ($addonSet as $ad) { if ($sdLower === strtolower($ad) . '.' . $mainDomain) { $isAddonSub = true; break; } }
            if (!$isAddonSub) $filtered[] = $sd;
        }
        $result['sub'] = $filtered;
    }
    return $result;
}

/* ─── Main Output Hook ────────────────────────────────────── */

/*
 * Single-hook approach via ClientAreaProductDetailsOutput.
 *
 * This is the ONLY hook that reliably fires on the product details page
 * with Lagom2 theme. It renders via {foreach $hookOutput} in the template.
 *
 * We return ALL output (CSS + config + modals + JS) as one HTML string.
 * The CSS is inlined here (not in <head>) but that's fine — browsers
 * handle <style> tags in <body> without issues.
 */

function broodle_tools_gather_data($vars)
{
    static $cache = null;
    if ($cache !== null) return $cache;

    broodle_tools_ensure_defaults();
    $serviceId = broodle_tools_get_service_id($vars);
    if (!$serviceId) { $cache = false; return false; }
    $cpData = broodle_tools_get_cpanel_service($serviceId);
    if (!$cpData) { $cache = false; return false; }

    $nsData = broodle_tools_ns_enabled() ? broodle_tools_get_ns_for_service($serviceId) : ['ns' => [], 'ip' => ''];
    $emails = broodle_tools_email_enabled() ? broodle_tools_get_emails($serviceId) : [];
    $domains = broodle_tools_domain_enabled() ? broodle_tools_get_domains_detailed($serviceId) : null;

    $service = $cpData['service'];
    $product = $cpData['product'];
    $server  = $cpData['server'];

    // Billing info
    $regDate     = $service->regdate ?? '';
    $nextDueDate = $service->nextduedate ?? '';
    $amount      = $service->amount ?? '0.00';
    $billingCycle = $service->billingcycle ?? '';
    $firstPayment = $service->firstpaymentamount ?? $amount;
    $paymentMethod = $service->paymentmethod ?? '';
    // Look up the display name for the payment gateway
    $paymentMethodDisplay = $paymentMethod;
    if ($paymentMethod) {
        try {
            $gwSetting = Capsule::table('tblpaymentgateways')
                ->where('gateway', $paymentMethod)
                ->where('setting', 'name')
                ->value('value');
            if ($gwSetting) $paymentMethodDisplay = $gwSetting;
        } catch (\Exception $e) {}
    }
    $dedicatedIp  = $service->dedicatedip ?? '';
    $assignedIps  = $service->assignedips ?? '';
    $username     = $service->username ?? '';
    $serverName   = $server->name ?? ($server->hostname ?? '');
    $serverIp     = $dedicatedIp ?: ($server->ipaddress ?? '');

    // Currency
    $clientCurrency = Capsule::table('tblclients')->where('id', $vars['userid'] ?? ($service->userid ?? 0))->value('currency') ?: 1;
    $currency = Capsule::table('tblcurrencies')->where('id', $clientCurrency)->first();
    $currPrefix = $currency->prefix ?? '';
    $currSuffix = $currency->suffix ?? '';

    // Format price
    $priceFormatted = $currPrefix . number_format((float)$amount, 2) . $currSuffix;
    $firstPayFormatted = $currPrefix . number_format((float)$firstPayment, 2) . $currSuffix;

    // Addon domains / subdomains / email counts from gathered data
    $addonCount = ($domains && isset($domains['addon'])) ? count($domains['addon']) : 0;
    $subCount   = ($domains && isset($domains['sub'])) ? count($domains['sub']) : 0;
    $emailCount = count($emails);

    // Fetch cPanel account limits (maxaddons, maxpop, maxsub, maxftp, maxparked) via WHM accountsummary
    $domainLimit = 0;
    $emailLimit = 0;
    $subdomainLimit = 0;
    $parkedLimit = 0;
    $ftpLimit = 0;
    $totalDomainCount = $addonCount + $subCount + (($domains && !empty($domains['parked'])) ? count($domains['parked']) : 0);
    if (!empty($username) && !empty($server)) {
        $hostname = $server->hostname;
        $port = !empty($server->port) ? (int) $server->port : 2087;
        $serverUser = $server->username;
        $secure = !empty($server->secure) && ($server->secure === 'on' || $server->secure === '1' || $server->secure === 1);
        $protocol = $secure ? 'https' : 'http';
        $accessHash = ''; $password = '';
        if (!empty($server->accesshash)) {
            $raw = trim($server->accesshash);
            if (preg_match('/^[A-Za-z0-9]{10,64}$/', $raw)) $accessHash = $raw;
            else { $accessHash = trim(decrypt($raw)); if (empty($accessHash) || !preg_match('/^[A-Za-z0-9]{10,64}$/', $accessHash)) $accessHash = ''; }
        }
        if (empty($accessHash) && !empty($server->password)) $password = trim(decrypt($server->password));
        if (!empty($accessHash) || !empty($password)) {
            $headers = [];
            if (!empty($accessHash)) $headers[] = "Authorization: whm {$serverUser}:{$accessHash}";
            $acctUrl = "{$protocol}://{$hostname}:{$port}/json-api/accountsummary?api.version=1&user=" . urlencode($username);
            $acctResp = broodle_tools_whm_get($acctUrl, $headers, $serverUser, $password);
            if ($acctResp) {
                $acct = $acctResp['data']['acct'][0] ?? ($acctResp['acct'][0] ?? []);
                if (!empty($acct)) {
                    $domainLimit = $acct['maxaddons'] ?? 0;
                    if ($domainLimit === 'unlimited' || $domainLimit === '*unlimited*') $domainLimit = -1;
                    else $domainLimit = (int) $domainLimit;
                    $emailLimit = $acct['maxpop'] ?? 0;
                    if ($emailLimit === 'unlimited' || $emailLimit === '*unlimited*') $emailLimit = -1;
                    else $emailLimit = (int) $emailLimit;
                    $subdomainLimit = $acct['maxsub'] ?? 0;
                    if ($subdomainLimit === 'unlimited' || $subdomainLimit === '*unlimited*') $subdomainLimit = -1;
                    else $subdomainLimit = (int) $subdomainLimit;
                    $parkedLimit = $acct['maxparked'] ?? 0;
                    if ($parkedLimit === 'unlimited' || $parkedLimit === '*unlimited*') $parkedLimit = -1;
                    else $parkedLimit = (int) $parkedLimit;
                }
            }
        }
    }

    $cache = [
        'serviceId' => $serviceId,
        'productName' => $product->name ?? '',
        'domain' => $service->domain ?? '',
        'status' => ucfirst($service->domainstatus ?? ''),
        'diskUsed' => (int) ($service->diskusage ?? 0),
        'diskLimit' => (int) ($service->disklimit ?? 0),
        'bwUsed' => (int) ($service->bwusage ?? 0),
        'bwLimit' => (int) ($service->bwlimit ?? 0),
        // Billing
        'regDate' => $regDate,
        'nextDueDate' => $nextDueDate,
        'billingCycle' => $billingCycle,
        'price' => $priceFormatted,
        'firstPayment' => $firstPayFormatted,
        'paymentMethod' => $paymentMethodDisplay,
        // Server
        'username' => $username,
        'serverName' => $serverName,
        'serverIp' => $serverIp,
        'dedicatedIp' => $dedicatedIp,
        // Counts & Limits
        'addonDomainCount' => $addonCount,
        'subdomainCount' => $subCount,
        'emailCount' => $emailCount,
        'totalDomainCount' => $totalDomainCount,
        'domainLimit' => $domainLimit,
        'emailLimit' => $emailLimit,
        'subdomainLimit' => $subdomainLimit,
        // Existing
        'ns' => $nsData,
        'emails' => $emails,
        'domains' => $domains,
        'cpanelPassword' => decrypt($service->password),
        'cpanelUrl' => 'https://' . ($server->hostname ?? '') . ':2083',
        'wpEnabled' => broodle_tools_wp_enabled(),
        'dbEnabled' => broodle_tools_db_enabled(),
        'sslEnabled' => broodle_tools_ssl_enabled(),
        'dnsEnabled' => broodle_tools_dns_enabled(),
        'cronEnabled' => broodle_tools_cron_enabled(),
        'phpEnabled' => broodle_tools_php_enabled(),
        'logsEnabled' => broodle_tools_logs_enabled(),
        'nsEnabled' => broodle_tools_ns_enabled(),
        'emailEnabled' => broodle_tools_email_enabled(),
        'domainEnabled' => broodle_tools_domain_enabled(),
        'fmEnabled' => broodle_tools_fm_enabled(),
        'analyticsEnabled' => broodle_tools_analytics_enabled(),
        'version' => BROODLE_TOOLS_VERSION,
        'mailApp' => broodle_tools_get_mail_app_settings(),
    ];
    return $cache;
}

/* ─── Manage V2 banner: inject via ClientAreaProductDetailsOutput ─── */
/* Shows a beta banner at the top of the cPanel product details page */
add_hook('ClientAreaProductDetailsOutput', 1, function ($vars) {
    if (!broodle_tools_v2_banner_enabled()) return '';
    $serviceId = broodle_tools_get_service_id($vars);
    if (!$serviceId) return '';
    $cpData = broodle_tools_get_cpanel_service($serviceId);
    if (!$cpData) return '';
    $url = 'index.php?m=broodle_whmcs_tools&id=' . $serviceId;
    return '
    <style>
    .bt-v2-banner{display:flex;align-items:center;gap:16px;padding:16px 22px;margin:0 0 20px;border-radius:12px;background:linear-gradient(135deg,#0a5ed3 0%,#2563eb 50%,#7c3aed 100%);color:#fff;text-decoration:none;transition:transform .15s,box-shadow .15s;position:relative;overflow:hidden}
    .bt-v2-banner:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(10,94,211,.3);color:#fff;text-decoration:none}
    .bt-v2-banner::before{content:"";position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.08) 0%,transparent 60%);pointer-events:none}
    .bt-v2-banner-icon{width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .bt-v2-banner-icon svg{width:22px;height:22px;color:#fff}
    .bt-v2-banner-text{flex:1;min-width:0}
    .bt-v2-banner-title{display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;margin:0 0 3px;color:#fff}
    .bt-v2-banner-badge{padding:2px 8px;border-radius:6px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;background:rgba(255,255,255,.2);color:#fff}
    .bt-v2-banner-desc{font-size:13px;color:rgba(255,255,255,.85);margin:0;line-height:1.4}
    .bt-v2-banner-arrow{width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .15s}
    .bt-v2-banner:hover .bt-v2-banner-arrow{background:rgba(255,255,255,.25)}
    .bt-v2-banner-arrow svg{width:18px;height:18px;color:#fff}
    [data-theme="dark"] .bt-v2-banner,.dark-mode .bt-v2-banner{background:linear-gradient(135deg,#1e40af 0%,#1d4ed8 50%,#6d28d9 100%)}
    [data-theme="dark"] .bt-v2-banner:hover,.dark-mode .bt-v2-banner:hover{box-shadow:0 8px 24px rgba(30,64,175,.4)}
    @media(max-width:600px){.bt-v2-banner{flex-wrap:wrap;gap:12px;padding:14px 16px}.bt-v2-banner-text{width:100%;order:2}.bt-v2-banner-arrow{order:3}}
    </style>
    <a href="' . $url . '" class="bt-v2-banner">
        <div class="bt-v2-banner-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        </div>
        <div class="bt-v2-banner-text">
            <p class="bt-v2-banner-title">Manage V2 <span class="bt-v2-banner-badge">Beta</span></p>
            <p class="bt-v2-banner-desc">Experience a better way to manage your cPanel hosting — faster, cleaner, and more powerful.</p>
        </div>
        <div class="bt-v2-banner-arrow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
    </a>
    <script>
    (function(){
        function moveBanner(){
            var banner=document.querySelector(".bt-v2-banner");
            if(!banner) return;
            // Lagom2 main content selectors (try multiple for compatibility)
            var target=document.querySelector(".main-content .container-fluid")
                ||document.querySelector(".main-content .container")
                ||document.querySelector(".main-content")
                ||document.querySelector("#main-body .container")
                ||document.querySelector("#main-body");
            if(target && target!==banner.parentNode){
                target.insertBefore(banner,target.firstChild);
            }
        }
        if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",moveBanner);}
        else{moveBanner();}
    })();
    </script>';
});

/* ─── Remove Webmail sidebar button ───────────────────────── */
add_hook('ClientAreaPrimarySidebar', 1, function ($primarySidebar) {
    try {
        $actions = $primarySidebar->getChild('Service Details Actions');
        if ($actions) {
            $actions->removeChild('Login to Webmail');
            $actions->removeChild('Login to Webmail ');
            $actions->removeChild('Webmail Login');
            $actions->removeChild('Webmail');
            $actions->removeChild('webmail');
            // Iterate children and remove any with webmail in the name
            if (is_object($actions) && method_exists($actions, 'getChildren')) {
                $children = $actions->getChildren();
                if ($children) {
                    foreach ($children as $name => $child) {
                        if (stripos($name, 'webmail') !== false || stripos($name, 'Webmail') !== false) {
                            $actions->removeChild($name);
                        }
                    }
                }
            }
        }
        // Also try Service Details Overview
        $overview = $primarySidebar->getChild('Service Details Overview');
        if ($overview) {
            $overview->removeChild('Login to Webmail');
            $overview->removeChild('Webmail Login');
            $overview->removeChild('Webmail');
            $overview->removeChild('webmail');
        }
    } catch (\Exception $e) {}
});

/* ─── Manage V2 dropdown on Dashboard & Services List ─── */
/* Injects JS/CSS via ClientAreaHeadOutput to add "Manage V2" to the Lagom2
   dropdown menu on the Manage button for cPanel services. */
add_hook('ClientAreaHeadOutput', 1, function ($vars) {
    if (!broodle_tools_v2_dropdown_enabled()) return '';

    // Only inject on dashboard and services list pages
    $filename = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $action = $_GET['action'] ?? '';
    $isDashboard = ($filename === 'clientarea.php' && empty($action));
    $isServicesList = ($filename === 'clientarea.php' && $action === 'services');
    $isIndex = ($filename === 'index.php' && empty($_GET['m']));

    if (!$isDashboard && !$isServicesList && !$isIndex) return '';

    // Get the logged-in client's cPanel service IDs
    $clientId = (int) ($_SESSION['uid'] ?? 0);
    if (!$clientId) return '';

    try {
        $cpanelServiceIds = Capsule::table('tblhosting')
            ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
            ->where('tblhosting.userid', $clientId)
            ->where('tblproducts.servertype', 'cpanel')
            ->whereIn('tblhosting.domainstatus', ['Active', 'Suspended'])
            ->pluck('tblhosting.id')
            ->toArray();
    } catch (\Exception $e) {
        $cpanelServiceIds = [];
    }

    if (empty($cpanelServiceIds)) return '';

    $idsJson = json_encode(array_map('intval', $cpanelServiceIds));

    return '
<style>
.bt-v2-dropdown-divider{border-top:1px solid #e5e7eb;margin:4px 0}
.bt-v2-beta{display:inline-block;padding:1px 6px;border-radius:4px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;background:rgba(10,94,211,.1);color:#0a5ed3;margin-left:6px;vertical-align:middle;line-height:1.4}
[data-theme="dark"] .bt-v2-dropdown-divider{border-color:#374151}
[data-theme="dark"] .bt-v2-beta{background:rgba(91,156,246,.15);color:#5b9cf6}
</style>
<script>
document.addEventListener("DOMContentLoaded",function(){
    var cpanelIds=' . $idsJson . ';
    var dropdowns=document.querySelectorAll("ul.dropdown-menu[data-service-id]");
    dropdowns.forEach(function(menu){
        var serviceId=parseInt(menu.getAttribute("data-service-id"));
        if(!serviceId) return;
        if(cpanelIds.indexOf(serviceId)===-1) return;
        if(menu.querySelector(".bt-v2-dropdown-item")) return;
        var divider=document.createElement("li");
        divider.className="bt-v2-dropdown-divider";
        divider.setAttribute("role","separator");
        var li=document.createElement("li");
        li.className="bt-v2-dropdown-item";
        var link=document.createElement("a");
        link.className="dropdown-item";
        link.href="index.php?m=broodle_whmcs_tools&id="+serviceId;
        link.innerHTML=\'Manage V2 <span class="bt-v2-beta">Beta</span>\';
        li.appendChild(link);
        menu.appendChild(divider);
        menu.appendChild(li);
    });
});
</script>';
});

/* ─── Modals (Email, Domain, Database) ────────────────────── */

function broodle_tools_modals()
{
    return '
<!-- Create Email Modal -->
<div class="bt-overlay" id="bemCreateModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Create Email Account</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Email Address</label><div class="bt-input-group"><input type="text" id="bemNewUser" placeholder="username" autocomplete="off"><span class="bt-at">@</span><select id="bemNewDomain"><option>Loading...</option></select></div></div><div class="bt-field"><label>Password</label><div class="bt-pass-wrap"><input type="password" id="bemNewPass" placeholder="Strong password" autocomplete="new-password"><button type="button" class="bt-pass-toggle" data-toggle-pass="bemNewPass"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div><div class="bt-field"><label>Quota (MB)</label><input type="number" id="bemNewQuota" value="250" min="1"></div><div class="bt-msg" id="bemCreateMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bemCreateSubmit">Create Account</button></div></div></div>
<!-- Change Password Modal -->
<div class="bt-overlay" id="bemPassModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Change Password</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Email</label><input type="text" id="bemPassEmail" readonly></div><div class="bt-field"><label>New Password</label><div class="bt-pass-wrap"><input type="password" id="bemPassNew" placeholder="New password" autocomplete="new-password"><button type="button" class="bt-pass-toggle" data-toggle-pass="bemPassNew"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div><div class="bt-msg" id="bemPassMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bemPassSubmit">Update Password</button></div></div></div>
<!-- Delete Email Modal -->
<div class="bt-overlay" id="bemDelModal" style="display:none"><div class="bt-modal bt-modal-sm"><div class="bt-modal-head"><h5>Delete Email Account</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body" style="text-align:center"><div style="margin:8px 0 16px"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.5" style="margin:0 auto;display:block"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><p style="margin:0 0 4px;font-size:14px">Are you sure you want to delete</p><p style="margin:0;font-size:15px;font-weight:600;color:#ef4444" id="bemDelEmail"></p><div class="bt-msg" id="bemDelMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-danger" id="bemDelSubmit">Delete</button></div></div></div>
<!-- Add Addon Domain Modal -->
<div class="bt-overlay" id="bdmAddonModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Add Addon Domain</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Domain Name</label><input type="text" id="bdmAddonDomain" placeholder="example.com" autocomplete="off"></div><div class="bt-field"><label>Document Root</label><div class="bt-docroot-wrap"><span class="bt-docroot-prefix">/home/user/</span><input type="text" id="bdmAddonDocroot" placeholder="example.com"></div></div><div class="bt-msg" id="bdmAddonMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdmAddonSubmit">Add Domain</button></div></div></div>
<!-- Add Subdomain Modal -->
<div class="bt-overlay" id="bdmSubModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Add Subdomain</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Subdomain</label><div class="bt-input-group"><input type="text" id="bdmSubName" placeholder="blog" autocomplete="off"><span class="bt-at">.</span><select id="bdmSubParent"><option>Loading...</option></select></div></div><div class="bt-field"><label>Document Root</label><div class="bt-docroot-wrap"><span class="bt-docroot-prefix">/home/user/</span><input type="text" id="bdmSubDocroot" placeholder="blog.example.com"></div></div><div class="bt-msg" id="bdmSubMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdmSubSubmit">Add Subdomain</button></div></div></div>
<!-- Delete Domain Modal -->
<div class="bt-overlay" id="bdmDelModal" style="display:none"><div class="bt-modal bt-modal-sm"><div class="bt-modal-head"><h5>Delete Domain</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body" style="text-align:center"><div style="margin:8px 0 16px"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.5" style="margin:0 auto;display:block"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><p style="margin:0 0 4px;font-size:14px">Are you sure you want to delete</p><p style="margin:0;font-size:15px;font-weight:600;color:#ef4444" id="bdmDelDomain"></p><div class="bt-msg" id="bdmDelMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-danger" id="bdmDelSubmit">Delete</button></div></div></div>
<!-- Create Database Modal -->
<div class="bt-overlay" id="bdbCreateModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Create Database</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Database Name</label><div class="bt-input-group"><span class="bt-prefix" id="bdbPrefix">user_</span><input type="text" id="bdbNewName" placeholder="mydb" autocomplete="off"></div></div><div class="bt-msg" id="bdbCreateMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdbCreateSubmit">Create Database</button></div></div></div>
<!-- Create DB User Modal -->
<div class="bt-overlay" id="bdbUserModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Create Database User</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Username</label><div class="bt-input-group"><span class="bt-prefix" id="bdbUserPrefix">user_</span><input type="text" id="bdbNewUser" placeholder="dbuser" autocomplete="off"></div></div><div class="bt-field"><label>Password</label><div class="bt-pass-wrap"><input type="password" id="bdbUserPass" placeholder="Strong password" autocomplete="new-password"><button type="button" class="bt-pass-toggle" data-toggle-pass="bdbUserPass"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div><div class="bt-msg" id="bdbUserMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdbUserSubmit">Create User</button></div></div></div>
<!-- Assign User to DB Modal -->
<div class="bt-overlay" id="bdbAssignModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Assign User to Database</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Database</label><select id="bdbAssignDb" class="bt-select"></select></div><div class="bt-field"><label>User</label><select id="bdbAssignUser" class="bt-select"></select></div><div class="bt-field"><label>Privileges</label><label class="bt-checkbox"><input type="checkbox" id="bdbAssignAll" checked> All Privileges</label></div><div class="bt-msg" id="bdbAssignMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdbAssignSubmit">Assign Privileges</button></div></div></div>
';
}

function broodle_tools_wp_detail_modal()
{
    return '
<div class="bt-overlay" id="bwpDetailOverlay" style="display:none">
  <div class="bwp-detail-panel">
    <div class="bwp-detail-head"><h5 id="bwpDetailTitle">Site Details</h5><button type="button" class="bt-modal-close" id="bwpDetailClose">&times;</button></div>
    <div class="bwp-detail-tabs"><button type="button" class="bwp-tab active" data-tab="overview">Overview</button><button type="button" class="bwp-tab" data-tab="plugins">Plugins</button><button type="button" class="bwp-tab" data-tab="themes">Themes</button><button type="button" class="bwp-tab" data-tab="security">Security</button></div>
    <div class="bwp-detail-body" id="bwpDetailBody">
      <div class="bwp-tab-content active" id="bwpTabOverview"></div>
      <div class="bwp-tab-content" id="bwpTabPlugins"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading plugins...</span></div></div>
      <div class="bwp-tab-content" id="bwpTabThemes"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading themes...</span></div></div>
      <div class="bwp-tab-content" id="bwpTabSecurity"><div class="bt-loading"><div class="bt-spinner"></div><span>Running security scan...</span></div></div>
    </div>
  </div>
</div>';
}

/* ─── Shared Styles ───────────────────────────────────────── */

function broodle_tools_shared_styles()
{
    return broodle_tools_css_hide() . broodle_tools_css_tabs() . broodle_tools_css_overview() . broodle_tools_css_cards() . broodle_tools_css_modals() . broodle_tools_css_wp() . broodle_tools_css_dns() . broodle_tools_css_dark() . broodle_tools_css_responsive();
}

function broodle_tools_css_hide()
{
    return '<style>
.product-details-tab-container,.product-details-tab-container+.tab-content,ul.panel-tabs.nav.nav-tabs{display:none!important}
.quick-create-email,.quick-create-email-section,[class*="quick-create-email"],.module-quick-create-email,#cPanelQuickEmailPanel{display:none!important}
#Primary_Sidebar-productdetails_addons_and_extras,#cPanelExtrasPurchasePanel,#tabAddonsExtras,.addons-and-extras-section,[id*="addons_and_extras"],[class*="addons-extras"]{display:none!important}
.bt-hidden-section{display:none!important}
.list-group-tab-nav .list-group-item[id*="webmail"],.list-group-tab-nav .list-group-item[id*="Webmail"],.list-group-tab-nav a[href*="webmail"]{display:none!important}
</style>';
}

function broodle_tools_css_tabs()
{
    return '<style>
.bt-wrap{margin-top:15px;margin-bottom:24px;font-family:inherit}
.bt-wrap *{font-family:inherit}
.bt-tabs-nav{display:flex;gap:0;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;border-bottom:2px solid var(--border-color,#e5e7eb);padding:0;margin:0}
.bt-tabs-nav::-webkit-scrollbar{display:none}
.bt-tab-btn{display:inline-flex;align-items:center;gap:7px;padding:12px 18px;font-size:13px;font-weight:600;color:var(--text-muted,#6b7280);cursor:pointer;border:none;background:none;white-space:nowrap;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s,border-color .15s;flex-shrink:0}
.bt-tab-btn:hover{color:var(--heading-color,#111827)}
.bt-tab-btn.active{color:#0a5ed3;border-bottom-color:#0a5ed3}
.bt-tab-btn svg{width:16px;height:16px;flex-shrink:0}
.bt-tab-pane{display:none;padding:20px 0 0}
.bt-tab-pane.active{display:block}
</style>';
}

function broodle_tools_css_overview()
{
    return '<style>
.bt-ov-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.bt-ov-card{background:var(--input-bg,#f8fafc);border:1px solid var(--border-color,#e5e7eb);border-radius:10px;padding:16px 18px;transition:border-color .15s,box-shadow .15s}
.bt-ov-card:hover{border-color:rgba(10,94,211,.25);box-shadow:0 2px 8px rgba(10,94,211,.06)}
.bt-ov-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted,#9ca3af);margin:0 0 6px}
.bt-ov-value{font-size:14px;font-weight:600;color:var(--heading-color,#111827);margin:0;word-break:break-word}
.bt-ov-value a{color:#0a5ed3;text-decoration:none}
.bt-ov-value .label,.bt-ov-value .badge{font-size:12px;padding:3px 10px;border-radius:6px;font-weight:600}
.bt-ov-due-ok{color:#059669}.bt-ov-due-warn{color:#d97706}.bt-ov-due-danger{color:#ef4444}.bt-ov-due-past{color:#ef4444;font-weight:700}
.bt-ov-days{display:block;font-size:11px;font-weight:500;margin-top:2px}
@media(max-width:768px){.bt-ov-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.bt-ov-grid{grid-template-columns:1fr}}
.bt-card{background:var(--card-bg,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:12px;overflow:hidden}
.bt-card-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border-color,#f3f4f6)}
.bt-card-head-left{display:flex;align-items:center;gap:12px}
.bt-icon-circle{width:36px;height:36px;background:#0a5ed3;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0}
.bt-card-head h5{margin:0;font-size:15px;font-weight:600;color:var(--heading-color,#111827)}
.bt-card-head p{margin:2px 0 0;font-size:12px;color:var(--text-muted,#6b7280)}
.bt-card-head-right{display:flex;gap:8px}
.bt-list{padding:6px 8px}
.bt-ns-section{margin-top:20px}
.bt-upgrades{margin-top:20px;padding:18px 20px;background:var(--input-bg,#f8fafc);border:1px solid var(--border-color,#e5e7eb);border-radius:12px}
.bt-upgrades h4{margin:0 0 14px;font-size:14px;font-weight:700;color:var(--heading-color,#111827);display:flex;align-items:center;gap:8px}
.bt-upgrades h4 svg{color:#0a5ed3}
.bt-upgrades .panel,.bt-upgrades .card{border:none;box-shadow:none;margin:0;background:transparent}
.bt-upgrades .panel-heading,.bt-upgrades .card-header{display:none}
.bt-upgrades .panel-body,.bt-upgrades .card-body{padding:0}
</style>';
}

function broodle_tools_css_cards()
{
    return '<style>
.bt-row{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:9px;transition:background .15s}
.bt-row:hover{background:var(--input-bg,#f9fafb)}
.bt-row+.bt-row{border-top:1px solid var(--border-color,#f3f4f6)}
.bt-row-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.bt-row-icon.ns{background:rgba(10,94,211,.08);color:#0a5ed3}
.bt-row-icon.ip{background:rgba(5,150,105,.08);color:#059669}
.bt-row-icon.email{background:rgba(10,94,211,.08);color:#0a5ed3;border-radius:50%}
.bt-row-icon.main{background:rgba(10,94,211,.08);color:#0a5ed3}
.bt-row-icon.addon{background:rgba(5,150,105,.08);color:#059669}
.bt-row-icon.sub{background:rgba(124,58,237,.08);color:#7c3aed}
.bt-row-icon.parked{background:rgba(217,119,6,.08);color:#d97706}
.bt-row-icon.db{background:rgba(10,94,211,.08);color:#0a5ed3}
.bt-row-icon.dbuser{background:rgba(124,58,237,.08);color:#7c3aed}
.bt-row-info{flex:1;min-width:0;display:flex;align-items:center;gap:8px;overflow:hidden}
.bt-row-name{font-size:14px;font-weight:500;color:var(--heading-color,#111827);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bt-row-name.mono{font-family:"SFMono-Regular",Consolas,"Liberation Mono",Menlo,monospace}
.bt-row-badge{padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;flex-shrink:0}
.bt-badge-primary{background:rgba(10,94,211,.08);color:#0a5ed3}
.bt-badge-green{background:rgba(5,150,105,.08);color:#059669}
.bt-badge-purple{background:rgba(124,58,237,.08);color:#7c3aed}
.bt-badge-amber{background:rgba(217,119,6,.08);color:#d97706}
.bt-row-actions{display:flex;gap:6px;flex-shrink:0}
.bt-row-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);transition:all .15s;white-space:nowrap;text-decoration:none;color:var(--heading-color,#374151)}
.bt-row-btn span{display:none}
.bt-row-btn:hover span{display:inline}
.bt-row-btn:hover{border-color:#0a5ed3;color:#0a5ed3}
.bt-row-btn.login{color:#0a5ed3}.bt-row-btn.login:hover{background:rgba(10,94,211,.06);border-color:#0a5ed3}
.bt-row-btn.visit{color:#0a5ed3}.bt-row-btn.visit:hover{background:rgba(10,94,211,.06);border-color:#0a5ed3;text-decoration:none;color:#0a5ed3}
.bt-row-btn.pass{color:#d97706}.bt-row-btn.pass:hover{background:rgba(217,119,6,.06);border-color:#d97706}
.bt-row-btn.del{color:#ef4444}.bt-row-btn.del:hover{background:rgba(239,68,68,.06);border-color:#ef4444}
.bt-copy{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid var(--border-color,#e5e7eb);border-radius:7px;background:var(--card-bg,#fff);color:var(--text-muted,#9ca3af);cursor:pointer;transition:all .15s;flex-shrink:0}
.bt-copy:hover{color:#0a5ed3;border-color:#0a5ed3}
.bt-copy.copied{color:#fff;background:#059669;border-color:#059669}
.bt-empty{padding:30px 22px;text-align:center;color:var(--text-muted,#9ca3af);font-size:14px;display:flex;flex-direction:column;align-items:center;gap:10px}
.bt-empty svg{opacity:.4}
.bt-btn-add{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:#0a5ed3;color:#fff;transition:background .15s}
.bt-btn-add:hover{background:#0950b3}
.bt-btn-outline{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#d1d5db);background:var(--card-bg,#fff);color:var(--heading-color,#374151);transition:all .15s}
.bt-btn-outline:hover{border-color:#0a5ed3;color:#0a5ed3;background:rgba(10,94,211,.04)}
.bt-accordion{margin-top:20px;border:1px solid var(--border-color,#e5e7eb);border-radius:12px;overflow:visible;background:var(--card-bg,#fff)}
.bt-accordion-head{display:flex;align-items:center;gap:12px;padding:14px 18px;cursor:pointer;user-select:none;transition:background .12s;border-radius:12px}
.bt-accordion-head:hover{background:var(--input-bg,#f9fafb)}
.bt-accordion-icon{width:36px;height:36px;border-radius:10px;background:#0a5ed3;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0}
.bt-accordion-info{flex:1;min-width:0}
.bt-accordion-info h5{margin:0;font-size:14px;font-weight:600;color:var(--heading-color,#111827)}
.bt-accordion-info p{margin:2px 0 0;font-size:12px;color:var(--text-muted,#6b7280)}
.bt-accordion-arrow{width:20px;height:20px;color:var(--text-muted,#9ca3af);transition:transform .25s ease;flex-shrink:0}
.bt-accordion.open .bt-accordion-arrow{transform:rotate(180deg)}
.bt-accordion-body{max-height:0;overflow:hidden;transition:max-height .3s ease,overflow 0s .3s}
.bt-accordion.open .bt-accordion-body{max-height:800px;overflow:visible;transition:max-height .3s ease,overflow 0s 0s}
.bt-addons-section{margin-top:20px}
.bt-addon-wrap{position:relative;padding:0 36px 6px}
.bt-addon-scroll{display:flex;gap:0;overflow-x:auto;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:none;padding:0;cursor:grab;user-select:none}
.bt-addon-scroll::-webkit-scrollbar{display:none}
.bt-addon-scroll.dragging{cursor:grabbing;scroll-snap-type:none;scroll-behavior:auto}
.bt-addon-page{min-width:100%;flex-shrink:0;scroll-snap-align:start;display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr;gap:2px 0;padding:6px 4px}
.bt-addon-item{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:10px;transition:background .12s;min-height:54px}
.bt-addon-item:hover{background:var(--input-bg,#f5f7fa)}
.bt-addon-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.bt-addon-icon.addon{background:rgba(124,58,237,.08);color:#7c3aed}
.bt-addon-icon.upgrade{background:rgba(5,150,105,.08);color:#059669}
.bt-addon-text{flex:1;min-width:0;overflow:hidden}
.bt-addon-name{font-size:13px;font-weight:500;color:var(--heading-color,#111827);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bt-addon-price{font-size:11px;color:#0a5ed3;font-weight:600;margin-top:1px;display:none}
.bt-addon-price.visible{display:block}
.bt-addon-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:7px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);color:var(--heading-color,#374151);transition:all .12s;white-space:nowrap;text-decoration:none;flex-shrink:0}
.bt-addon-btn:hover{border-color:#0a5ed3;color:#0a5ed3;background:rgba(10,94,211,.04);text-decoration:none}
.bt-addon-dots{display:flex;justify-content:center;gap:6px;padding:10px 0 4px}
.bt-addon-dot{width:6px;height:6px;border-radius:50%;background:var(--border-color,#d1d5db);border:none;padding:0;cursor:pointer;transition:all .2s}
.bt-addon-dot.active{background:#0a5ed3;width:18px;border-radius:3px}
.bt-addon-nav{position:absolute;top:50%;transform:translateY(-60%);width:30px;height:30px;border-radius:50%;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);color:var(--text-muted,#6b7280);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;z-index:2;box-shadow:0 2px 6px rgba(0,0,0,.1)}
.bt-addon-nav:hover{border-color:#0a5ed3;color:#0a5ed3;box-shadow:0 2px 8px rgba(10,94,211,.15)}
.bt-addon-nav.prev{left:0}
.bt-addon-nav.next{right:0}
.bt-addon-nav.hidden{opacity:0;pointer-events:none}
.bt-addon-tip-wrap{position:relative;flex-shrink:0;margin-right:2px}
.bt-addon-tip-btn{width:18px;height:18px;border-radius:50%;border:none;background:transparent;color:var(--text-muted,#c0c5cc);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:color .12s;padding:0}
.bt-addon-tip-btn:hover{color:#0a5ed3}
.bt-addon-tip-btn.loading{opacity:.4}
.bt-addon-tooltip{position:fixed;width:340px;max-width:90vw;max-height:200px;overflow-y:auto;overflow-x:hidden;padding:12px 15px;background:#1f2937;color:#f3f4f6;font-size:12px;line-height:1.55;border-radius:9px;box-shadow:0 8px 28px rgba(0,0,0,.22);z-index:99999;word-wrap:break-word;opacity:0;visibility:hidden;transition:opacity .12s,visibility .12s;pointer-events:none}
.bt-addon-tooltip.visible{opacity:1;visibility:visible;pointer-events:auto}
.bt-addon-tooltip::-webkit-scrollbar{display:none}
.bt-addon-tooltip{scrollbar-width:none}
.bt-addon-tooltip::after{display:none}
@media(max-width:600px){.bt-addon-page{grid-template-columns:1fr;grid-template-rows:repeat(4,1fr)}.bt-addon-wrap{padding:0 30px 6px}.bt-addon-tooltip{width:280px}}
/* SSL Pane */
.bt-ssl-row .bt-row-info{flex-wrap:wrap}
.bt-ssl-meta{display:flex;align-items:center;gap:12px;flex-shrink:0;font-size:11px;color:var(--text-muted,#6b7280)}
.bt-ssl-meta span{display:inline-flex;align-items:center;gap:4px;white-space:nowrap}
.bt-ssl-issuer{color:var(--text-muted,#6b7280)}
.bt-ssl-days-ok{color:#059669}.bt-ssl-days-warn{color:#d97706}.bt-ssl-days-danger{color:#ef4444}
.bt-row-icon.ssl-valid{background:rgba(5,150,105,.08);color:#059669}
.bt-row-icon.ssl-selfsigned{background:rgba(217,119,6,.08);color:#d97706}
.bt-row-icon.ssl-expired{background:rgba(239,68,68,.08);color:#ef4444}
.bt-row-icon.ssl-expiring{background:rgba(217,119,6,.08);color:#d97706}
.bt-badge-red{background:rgba(239,68,68,.08);color:#ef4444}
.bt-ssl-generate:hover{background:rgba(5,150,105,.06)!important}
@media(max-width:600px){.bt-ssl-meta{flex-direction:column;align-items:flex-start;gap:4px}}
</style>';
}

function broodle_tools_css_modals()
{
    return '<style>
.bt-overlay{position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:20px;animation:btFadeIn .2s}
@keyframes btFadeIn{from{opacity:0}to{opacity:1}}
.bt-modal{background:var(--card-bg,#fff);border-radius:14px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:btSlideUp .25s}
.bt-modal-sm{max-width:380px}
@keyframes btSlideUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.bt-modal-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border-color,#f3f4f6)}
.bt-modal-head h5{margin:0;font-size:16px;font-weight:600;color:var(--heading-color,#111827)}
.bt-modal-close{width:30px;height:30px;display:flex;align-items:center;justify-content:center;border:none;background:none;font-size:20px;color:var(--text-muted,#9ca3af);border-radius:6px;cursor:pointer;transition:all .15s}
.bt-modal-close:hover{background:var(--input-bg,#f3f4f6);color:var(--heading-color,#111827)}
.bt-modal-body{padding:20px 22px}
.bt-modal-foot{display:flex;justify-content:flex-end;gap:8px;padding:14px 22px;border-top:1px solid var(--border-color,#f3f4f6)}
.bt-field{margin-bottom:16px}.bt-field:last-child{margin-bottom:0}
.bt-field label{display:block;font-size:12px;font-weight:600;color:var(--text-muted,#6b7280);margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px}
.bt-field input,.bt-field select,.bt-select{width:100%;padding:9px 12px;border:1px solid var(--border-color,#d1d5db);border-radius:8px;font-size:14px;color:var(--heading-color,#111827);background:var(--input-bg,#fff);outline:none;transition:border-color .15s;box-sizing:border-box}
.bt-field input:focus,.bt-field select:focus,.bt-select:focus{border-color:#0a5ed3;box-shadow:0 0 0 3px rgba(10,94,211,.1)}
.bt-field input[readonly]{background:var(--input-bg,#f9fafb);color:var(--text-muted,#6b7280)}
.bt-input-group{display:flex;align-items:center;border:1px solid var(--border-color,#d1d5db);border-radius:8px;overflow:hidden;transition:border-color .15s}
.bt-input-group:focus-within{border-color:#0a5ed3;box-shadow:0 0 0 3px rgba(10,94,211,.1)}
.bt-input-group input{border:none;border-radius:0;flex:1;min-width:0}.bt-input-group input:focus{box-shadow:none}
.bt-at,.bt-prefix{padding:0 10px;font-size:13px;color:var(--text-muted,#9ca3af);font-weight:600;background:var(--input-bg,#f9fafb);border-right:1px solid var(--border-color,#e5e7eb);height:100%;display:flex;align-items:center;white-space:nowrap;flex-shrink:0}
.bt-input-group select{border:none;border-radius:0;flex:1;min-width:0}.bt-input-group select:focus{box-shadow:none}
.bt-pass-wrap{position:relative}.bt-pass-wrap input{width:100%;padding-right:40px}
.bt-pass-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted,#9ca3af);cursor:pointer;padding:4px}
.bt-pass-toggle:hover{color:var(--heading-color,#111827)}
.bt-docroot-wrap{display:flex;align-items:center;border:1px solid var(--border-color,#d1d5db);border-radius:8px;overflow:hidden;transition:border-color .15s}
.bt-docroot-wrap:focus-within{border-color:#0a5ed3;box-shadow:0 0 0 3px rgba(10,94,211,.1)}
.bt-docroot-prefix{padding:9px 10px;font-size:13px;color:var(--text-muted,#9ca3af);background:var(--input-bg,#f9fafb);border-right:1px solid var(--border-color,#e5e7eb);white-space:nowrap;flex-shrink:0}
.bt-docroot-wrap input{border:none;border-radius:0;flex:1;min-width:0;padding:9px 12px;font-size:14px;outline:none}
.bt-docroot-wrap input:focus{box-shadow:none}
.bt-btn-cancel{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:var(--input-bg,#f3f4f6);color:var(--heading-color,#374151);transition:all .15s}
.bt-btn-cancel:hover{background:#e5e7eb}
.bt-btn-primary{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:#0a5ed3;color:#fff;transition:all .15s}
.bt-btn-primary:hover{background:#0950b3}
.bt-btn-danger{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:#ef4444;color:#fff;transition:all .15s}
.bt-btn-danger:hover{background:#dc2626}
.bt-btn-primary:disabled,.bt-btn-danger:disabled,.bt-btn-add:disabled,.bt-btn-outline:disabled,.bt-row-btn:disabled{opacity:.5;cursor:not-allowed;pointer-events:none}
.bt-btn-spin{display:inline-block;vertical-align:middle}
.bt-msg{margin-top:12px;padding:8px 12px;border-radius:6px;font-size:13px;display:none}
.bt-msg.success{display:block;background:rgba(5,150,105,.08);color:#059669}
.bt-msg.error{display:block;background:rgba(239,68,68,.08);color:#ef4444}
.bt-checkbox{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:500;color:var(--heading-color,#111827);cursor:pointer;text-transform:none;letter-spacing:0}
.bt-checkbox input{width:16px;height:16px;accent-color:#0a5ed3}
.bt-loading{padding:40px 22px;text-align:center;color:var(--text-muted,#9ca3af);font-size:14px;display:flex;flex-direction:column;align-items:center;gap:12px}
.bt-spinner{width:28px;height:28px;border:3px solid var(--border-color,#e5e7eb);border-top-color:#0a5ed3;border-radius:50%;animation:btSpin .7s linear infinite}
@keyframes btSpin{to{transform:rotate(360deg)}}
</style>';
}

function broodle_tools_css_wp()
{
    return '<style>
.bwp-detail-panel{width:100%;max-width:900px;max-height:90vh;background:var(--card-bg,#fff);border-radius:16px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,.25);animation:btSlideUp .3s}
.bwp-detail-head{display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid var(--border-color,#f3f4f6);flex-shrink:0}
.bwp-detail-head h5{flex:1;margin:0;font-size:14px;font-weight:700;color:var(--heading-color,#111827);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bwp-detail-tabs{display:flex;gap:0;padding:0 20px;border-bottom:1px solid var(--border-color,#f3f4f6);flex-shrink:0;overflow-x:auto}
.bwp-tab{padding:10px 14px;font-size:12px;font-weight:600;color:var(--text-muted,#6b7280);cursor:pointer;border:none;background:none;border-bottom:2px solid transparent;transition:all .15s;white-space:nowrap}
.bwp-tab:hover{color:var(--heading-color,#111827)}
.bwp-tab.active{color:#0a5ed3;border-bottom-color:#0a5ed3}
.bwp-detail-body{flex:1;overflow-y:auto;padding:0}
.bwp-tab-content{display:none;padding:18px 20px}.bwp-tab-content.active{display:block}
.bwp-site{display:flex;align-items:center;gap:14px;padding:14px;border-radius:9px;transition:background .15s;cursor:pointer}
.bwp-site:hover{background:var(--input-bg,#f9fafb)}
.bwp-site+.bwp-site{border-top:1px solid var(--border-color,#f3f4f6)}
.bwp-site-icon{width:40px;height:40px;border-radius:10px;background:rgba(10,94,211,.08);color:#0a5ed3;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.bwp-site-info{flex:1;min-width:0}
.bwp-site-domain{font-size:14px;font-weight:600;color:var(--heading-color,#111827);margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bwp-site-meta{font-size:12px;color:var(--text-muted,#6b7280);margin:2px 0 0;display:flex;gap:12px;flex-wrap:wrap}
.bwp-site-meta span{display:inline-flex;align-items:center;gap:4px}
.bwp-site-actions{display:flex;gap:6px;flex-shrink:0}
.bwp-status-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.bwp-status-badge.active{background:rgba(5,150,101,.08);color:#059669}
.bwp-status-badge.inactive{background:rgba(217,119,6,.08);color:#d97706}
.bwp-status-badge.update-available{background:rgba(10,94,211,.08);color:#0a5ed3}
.bwp-overview-hero{display:flex;gap:18px;align-items:flex-start}
.bwp-preview-col{flex-shrink:0;width:300px;display:flex;flex-direction:column;gap:8px}
.bwp-overview-right{flex:1;min-width:0}
.bwp-preview-wrap{border-radius:10px;overflow:hidden;border:1px solid var(--border-color,#e5e7eb);background:#f9fafb}
.bwp-preview-bar{display:flex;align-items:center;gap:6px;padding:5px 10px;background:var(--input-bg,#f3f4f6);border-bottom:1px solid var(--border-color,#e5e7eb)}
.bwp-preview-dots{display:flex;gap:4px}.bwp-preview-dots span{width:7px;height:7px;border-radius:50%}
.bwp-preview-dots span:nth-child(1){background:#ef4444}.bwp-preview-dots span:nth-child(2){background:#f59e0b}.bwp-preview-dots span:nth-child(3){background:#22c55e}
.bwp-preview-url{flex:1;font-size:9px;color:var(--text-muted,#6b7280);background:var(--card-bg,#fff);padding:2px 7px;border-radius:4px;border:1px solid var(--border-color,#e5e7eb);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:monospace}
.bwp-preview-frame-wrap{width:100%;height:187px;overflow:hidden;position:relative;background:var(--input-bg,#f3f4f6)}
.bwp-quick-actions{display:flex;gap:6px;flex-wrap:wrap}
.bwp-site-header{display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--input-bg,#f9fafb);border-radius:10px;border:1px solid var(--border-color,#f3f4f6);margin-bottom:12px}
.bwp-site-header-icon{width:40px;height:40px;border-radius:10px;background:#0a5ed3;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.bwp-site-header-info{flex:1;min-width:0}
.bwp-site-header-info h4{margin:0;font-size:14px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bwp-site-header-info p{margin:2px 0 0;font-size:11px;color:var(--text-muted,#6b7280);display:flex;gap:8px;flex-wrap:wrap}
.bwp-overview-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}
.bwp-stat{padding:10px 12px;background:var(--input-bg,#f9fafb);border-radius:8px;border:1px solid var(--border-color,#f3f4f6)}
.bwp-stat-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted,#9ca3af);margin:0 0 2px}
.bwp-stat-value{font-size:13px;font-weight:600;color:var(--heading-color,#111827);margin:0;word-break:break-all}
.bwp-item-row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border-color,#f3f4f6)}.bwp-item-row:last-child{border-bottom:none}
.bwp-item-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px}
.bwp-item-icon.plugin{background:rgba(10,94,211,.08);color:#0a5ed3}
.bwp-item-icon.theme{background:rgba(124,58,237,.08);color:#7c3aed}
.bwp-item-info{flex:1;min-width:0}
.bwp-item-name{font-size:12px;font-weight:600;color:var(--heading-color,#111827);margin:0}
.bwp-item-detail{font-size:10px;color:var(--text-muted,#6b7280);margin:2px 0 0}
.bwp-item-actions{display:flex;gap:4px;flex-shrink:0}
.bwp-item-btn{padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);transition:all .15s}
.bwp-item-btn:hover{border-color:#0a5ed3;color:#0a5ed3}
.bwp-item-btn.active-state{color:#059669;border-color:#059669}
.bwp-item-btn.inactive-state{color:#d97706;border-color:#d97706}
.bwp-item-btn.update{color:#0a5ed3;border-color:#0a5ed3}
.bwp-item-btn.delete{color:#ef4444;border-color:#ef4444}
.bwp-item-btn:disabled{opacity:.5;cursor:not-allowed}
.bwp-theme-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
.bwp-theme-card{border:1px solid var(--border-color,#e5e7eb);border-radius:12px;overflow:hidden;background:var(--card-bg,#fff);transition:box-shadow .2s}
.bwp-theme-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08)}
.bwp-theme-active{border-color:#0a5ed3;box-shadow:0 0 0 2px rgba(10,94,211,.15)}
.bwp-theme-screenshot{position:relative;width:100%;padding-top:66%;background:var(--input-bg,#f3f4f6);overflow:hidden}
.bwp-theme-screenshot img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.bwp-theme-active-badge{position:absolute;top:8px;right:8px;background:#0a5ed3;color:#fff;padding:3px 10px;border-radius:6px;font-size:10px;font-weight:700;text-transform:uppercase}
.bwp-theme-info{padding:12px 14px}
.bwp-theme-name{font-size:13px;font-weight:600;margin:0 0 4px}
.bwp-theme-ver{font-size:11px;color:var(--text-muted,#6b7280);margin:0 0 8px}
.bwp-theme-actions{display:flex;gap:6px}
.bwp-sec-summary{margin-bottom:20px;padding:16px;background:var(--input-bg,#f9fafb);border-radius:12px;border:1px solid var(--border-color,#f3f4f6)}
.bwp-sec-summary-bar{height:8px;background:var(--border-color,#e5e7eb);border-radius:4px;overflow:hidden;margin-bottom:10px}
.bwp-sec-summary-fill{height:100%;background:linear-gradient(90deg,#059669,#22c55e);border-radius:4px;transition:width .5s}
.bwp-sec-summary-text{display:flex;gap:16px;font-size:13px}
.bwp-security-item{display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid var(--border-color,#f3f4f6)}.bwp-security-item:last-child{border-bottom:none}
.bwp-sec-icon{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.bwp-sec-icon.ok{background:rgba(5,150,101,.08);color:#059669}
.bwp-sec-icon.warning{background:rgba(217,119,6,.08);color:#d97706}
.bwp-sec-info{flex:1}
.bwp-sec-label{font-size:13px;font-weight:600;margin:0}
.bwp-sec-detail{font-size:12px;color:var(--text-muted,#6b7280);margin:2px 0 0}
.bwp-sec-value{font-size:12px;font-weight:600;flex-shrink:0}
.bwp-sec-value.ok{color:#059669}.bwp-sec-value.warning{color:#d97706}
.bwp-msg{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:12px}
.bwp-msg.success{background:rgba(5,150,101,.08);color:#059669}
.bwp-msg.error{background:rgba(239,68,68,.08);color:#ef4444}
.bwp-msg.info{background:rgba(10,94,211,.08);color:#0a5ed3}

/* Sidebar — icon box buttons */
.list-group-tab-nav{display:flex;flex-direction:column;gap:10px;padding:10px 12px}
.list-group-tab-nav .list-group-item{display:flex!important;align-items:center;gap:10px;padding:11px 14px;border-radius:8px;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);color:var(--heading-color,#1f2937);font-size:13px;font-weight:600;text-decoration:none;transition:all .15s ease;box-shadow:0 1px 2px rgba(0,0,0,.03);position:relative}
.list-group-tab-nav .list-group-item:hover{border-color:rgba(10,94,211,.25);box-shadow:0 2px 8px rgba(10,94,211,.08);transform:translateY(-1px);color:#0a5ed3}
.list-group-tab-nav .list-group-item .fas,.list-group-tab-nav .list-group-item .fa,.list-group-tab-nav .list-group-item .far,.list-group-tab-nav .list-group-item .fab,.list-group-tab-nav .list-group-item .fal,.list-group-tab-nav .list-group-item .lm,.list-group-tab-nav .list-group-item .ls{display:none!important}
.list-group-tab-nav .list-group-item .loading{position:absolute;right:14px;top:50%;transform:translateY(-50%)}
.list-group-tab-nav .list-group-item .bt-action-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.list-group-tab-nav .list-group-item .bt-action-icon svg{width:18px;height:18px}
.list-group-tab-nav .list-group-item .bt-action-label{display:flex;flex-direction:column;gap:1px}
.list-group-tab-nav .list-group-item .bt-action-label span{font-size:10px;font-weight:500;color:var(--text-muted,#6b7280)}
/* Actions panel: CSS-only icons via ::before */
.list-group-tab-nav .bt-act-cpanel::before,.list-group-tab-nav .bt-act-password::before,.list-group-tab-nav .bt-act-cancel::before,.list-group-tab-nav .bt-act-upgrade::before,.list-group-tab-nav .bt-act-renew::before{content:"";width:34px;height:34px;border-radius:8px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background-size:24px 24px;background-repeat:no-repeat;background-position:center}
.list-group-tab-nav .bt-act-cpanel::before{background-color:#ff6c2c;background-image:url(modules/addons/broodle_whmcs_tools/cpanel-icon.png);background-size:22px 22px;background-repeat:no-repeat;background-position:center;border-radius:8px}
.list-group-tab-nav .bt-act-password::before{background-color:#ff6c2c;background-image:url("data:image/svg+xml,%3Csvg viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%23fff%27 stroke-width=%272%27 xmlns=%27http://www.w3.org/2000/svg%27%3E%3Crect x=%273%27 y=%2711%27 width=%2718%27 height=%2711%27 rx=%272%27/%3E%3Cpath d=%27M7 11V7a5 5 0 0 1 10 0v4%27/%3E%3Ccircle cx=%2712%27 cy=%2716%27 r=%271%27/%3E%3C/svg%3E");background-size:18px 18px}
.list-group-tab-nav .bt-act-cancel::before{background-color:rgba(239,68,68,.1);background-image:url("data:image/svg+xml,%3Csvg viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%23ef4444%27 stroke-width=%272%27 xmlns=%27http://www.w3.org/2000/svg%27%3E%3Ccircle cx=%2712%27 cy=%2712%27 r=%2710%27/%3E%3Cline x1=%2715%27 y1=%279%27 x2=%279%27 y2=%2715%27/%3E%3Cline x1=%279%27 y1=%279%27 x2=%2715%27 y2=%2715%27/%3E%3C/svg%3E");background-size:18px 18px}
.list-group-tab-nav .bt-act-upgrade::before{background-color:#ff6c2c;background-image:url("data:image/svg+xml,%3Csvg viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%23fff%27 stroke-width=%272%27 xmlns=%27http://www.w3.org/2000/svg%27%3E%3Cpolyline points=%2717 1 21 5 17 9%27/%3E%3Cpath d=%27M3 11V9a4 4 0 0 1 4-4h14%27/%3E%3Cpolyline points=%277 23 3 19 7 15%27/%3E%3Cpath d=%27M21 13v2a4 4 0 0 1-4 4H3%27/%3E%3C/svg%3E");background-size:18px 18px}
.list-group-tab-nav .bt-act-renew::before{background-color:#ff6c2c;background-image:url("data:image/svg+xml,%3Csvg viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%23fff%27 stroke-width=%272%27 xmlns=%27http://www.w3.org/2000/svg%27%3E%3Cpolyline points=%2723 4 23 10 17 10%27/%3E%3Cpath d=%27M20.49 15a9 9 0 1 1-2.12-9.36L23 10%27/%3E%3C/svg%3E");background-size:18px 18px}
.list-group-tab-nav .bt-act-cpanel:hover::before{background-color:#e55e22}
.list-group-tab-nav .bt-act-password:hover::before{background-color:#e55e22}
.list-group-tab-nav .bt-act-cancel:hover::before{background-color:rgba(239,68,68,.16)}
.list-group-tab-nav .bt-act-upgrade:hover::before{background-color:#e55e22}
.list-group-tab-nav .bt-act-renew:hover::before{background-color:#e55e22}
/* Overview panel items (DOM-modified) */
.list-group-tab-nav .list-group-item[id*="cpanel"] .bt-action-icon{background:rgba(255,106,0,.08);color:#ff6a00}
.list-group-tab-nav .list-group-item[id*="Change_Password"] .bt-action-icon{background:rgba(124,58,237,.08);color:#7c3aed}
.list-group-tab-nav .list-group-item[id*="Cancel"] .bt-action-icon{background:rgba(239,68,68,.1);color:#ef4444}
.panel-actions .panel-heading,.sidebar-header-wrapper{border-bottom:none;padding:12px 14px 2px}
.panel-actions .panel-heading .panel-title{font-size:11px;font-weight:700;color:var(--text-muted,#6b7280);letter-spacing:.4px;text-transform:uppercase}
.panel-actions .panel-heading .panel-title .fas.fa-wrench{color:var(--text-muted,#9ca3af)}
.panel-default>.panel-heading{border-bottom:none;padding:12px 14px 2px}
.panel-default>.panel-heading .panel-title{font-size:11px;font-weight:700;color:var(--text-muted,#6b7280);letter-spacing:.4px;text-transform:uppercase}
</style>';
}

function broodle_tools_css_dns()
{
    return '<style>
.bt-dns-toolbar{border-bottom:1px solid var(--border-color,#f3f4f6)}
.bt-dns-filter-bar{border-bottom:1px solid var(--border-color,#f3f4f6);background:var(--input-bg,#fafbfc)}
.bt-dns-record-row{gap:10px;align-items:flex-start;padding:10px 14px}
.bt-dns-record-row .bt-row-info{gap:2px}
.bt-dns-record-row .bt-row-actions{margin-top:2px}
.bt-dns-domain-row:hover{background:var(--input-bg,#f5f7fa)}
.bt-dns-filter-btn:hover{border-color:#0a5ed3!important;color:#0a5ed3!important}
#btDnsAddFields textarea,#btDnsEditFields textarea{outline:none;transition:border-color .15s}
#btDnsAddFields textarea:focus,#btDnsEditFields textarea:focus{border-color:#0a5ed3;box-shadow:0 0 0 3px rgba(10,94,211,.1)}
</style>';
}

function broodle_tools_css_dark()
{
    // Use D as shorthand for the two dark mode selectors Lagom uses
    return '<style>
/* ─── Dark Mode: Lagom theme compatibility ─── */
/* Shorthand: D = [data-theme="dark"], DM = .dark-mode (legacy) */

/* --- Tabs --- */
[data-theme="dark"] .bt-tabs-nav,.dark-mode .bt-tabs-nav{border-bottom-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-tab-btn,.dark-mode .bt-tab-btn{color:var(--text-muted,#9ca3af)}
[data-theme="dark"] .bt-tab-btn:hover,.dark-mode .bt-tab-btn:hover{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bt-tab-btn.active,.dark-mode .bt-tab-btn.active{color:#5b9cf6;border-bottom-color:#5b9cf6}

/* --- Overview cards --- */
[data-theme="dark"] .bt-ov-card,.dark-mode .bt-ov-card{background:var(--input-bg,#111827);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-ov-card:hover,.dark-mode .bt-ov-card:hover{border-color:rgba(91,156,246,.3);box-shadow:0 2px 8px rgba(91,156,246,.08)}
[data-theme="dark"] .bt-ov-label,.dark-mode .bt-ov-label{color:var(--text-muted,#6b7280)}
[data-theme="dark"] .bt-ov-value,.dark-mode .bt-ov-value{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bt-ov-value a,.dark-mode .bt-ov-value a{color:#5b9cf6}

/* --- Cards --- */
[data-theme="dark"] .bt-card,.dark-mode .bt-card{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-card-head,.dark-mode .bt-card-head{border-bottom-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-card-head h5,.dark-mode .bt-card-head h5{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bt-card-head p,.dark-mode .bt-card-head p{color:var(--text-muted,#9ca3af)}

/* --- Rows --- */
[data-theme="dark"] .bt-row+.bt-row,.dark-mode .bt-row+.bt-row{border-top-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-row:hover,.dark-mode .bt-row:hover{background:var(--input-bg,#111827)}
[data-theme="dark"] .bt-row-name,.dark-mode .bt-row-name{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bt-row-btn,.dark-mode .bt-row-btn{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151);color:var(--heading-color,#d1d5db)}
[data-theme="dark"] .bt-row-btn:hover,.dark-mode .bt-row-btn:hover{border-color:#5b9cf6;color:#5b9cf6}
[data-theme="dark"] .bt-row-btn.login,.dark-mode .bt-row-btn.login{color:#5b9cf6}
[data-theme="dark"] .bt-row-btn.visit,.dark-mode .bt-row-btn.visit{color:#5b9cf6}
[data-theme="dark"] .bt-row-btn.pass,.dark-mode .bt-row-btn.pass{color:#fbbf24}
[data-theme="dark"] .bt-row-btn.del,.dark-mode .bt-row-btn.del{color:#f87171}
[data-theme="dark"] .bt-copy,.dark-mode .bt-copy{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151);color:var(--text-muted,#6b7280)}
[data-theme="dark"] .bt-copy:hover,.dark-mode .bt-copy:hover{color:#5b9cf6;border-color:#5b9cf6}
[data-theme="dark"] .bt-empty,.dark-mode .bt-empty{color:var(--text-muted,#6b7280)}

/* --- Buttons --- */
[data-theme="dark"] .bt-btn-add,.dark-mode .bt-btn-add{background:#2563eb;color:#fff}
[data-theme="dark"] .bt-btn-add:hover,.dark-mode .bt-btn-add:hover{background:#1d4ed8}
[data-theme="dark"] .bt-btn-outline,.dark-mode .bt-btn-outline{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151);color:var(--heading-color,#d1d5db)}
[data-theme="dark"] .bt-btn-outline:hover,.dark-mode .bt-btn-outline:hover{border-color:#5b9cf6;color:#5b9cf6;background:rgba(91,156,246,.06)}
[data-theme="dark"] .bt-btn-cancel,.dark-mode .bt-btn-cancel{background:var(--input-bg,#374151);color:var(--heading-color,#d1d5db)}
[data-theme="dark"] .bt-btn-cancel:hover,.dark-mode .bt-btn-cancel:hover{background:#4b5563}
[data-theme="dark"] .bt-btn-primary,.dark-mode .bt-btn-primary{background:#2563eb}
[data-theme="dark"] .bt-btn-primary:hover,.dark-mode .bt-btn-primary:hover{background:#1d4ed8}

/* --- Modals --- */
[data-theme="dark"] .bt-overlay,.dark-mode .bt-overlay{background:rgba(0,0,0,.6)}
[data-theme="dark"] .bt-modal,.dark-mode .bt-modal{background:var(--card-bg,#1f2937);box-shadow:0 20px 60px rgba(0,0,0,.5)}
[data-theme="dark"] .bt-modal-head,.dark-mode .bt-modal-head{border-bottom-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-modal-head h5,.dark-mode .bt-modal-head h5{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bt-modal-close,.dark-mode .bt-modal-close{color:var(--text-muted,#6b7280)}
[data-theme="dark"] .bt-modal-close:hover,.dark-mode .bt-modal-close:hover{background:var(--input-bg,#374151);color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bt-modal-foot,.dark-mode .bt-modal-foot{border-top-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-modal-body p,.dark-mode .bt-modal-body p{color:var(--heading-color,#d1d5db)}

/* --- Form fields --- */
[data-theme="dark"] .bt-field label,.dark-mode .bt-field label{color:var(--text-muted,#9ca3af)}
[data-theme="dark"] .bt-field input,.dark-mode .bt-field input,
[data-theme="dark"] .bt-field select,.dark-mode .bt-field select,
[data-theme="dark"] .bt-select,.dark-mode .bt-select{background:var(--input-bg,#111827);border-color:var(--border-color,#374151);color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bt-field input::placeholder,.dark-mode .bt-field input::placeholder{color:#6b7280}
[data-theme="dark"] .bt-field input[readonly],.dark-mode .bt-field input[readonly]{background:var(--input-bg,#1a2332);color:var(--text-muted,#9ca3af)}
[data-theme="dark"] .bt-input-group,.dark-mode .bt-input-group{border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-at,.dark-mode .bt-at,
[data-theme="dark"] .bt-prefix,.dark-mode .bt-prefix{background:var(--input-bg,#111827);border-color:var(--border-color,#374151);color:var(--text-muted,#9ca3af)}
[data-theme="dark"] .bt-docroot-wrap,.dark-mode .bt-docroot-wrap{border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-docroot-prefix,.dark-mode .bt-docroot-prefix{background:var(--input-bg,#111827);border-color:var(--border-color,#374151);color:var(--text-muted,#9ca3af)}
[data-theme="dark"] .bt-docroot-wrap input,.dark-mode .bt-docroot-wrap input{background:var(--input-bg,#111827);color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bt-pass-toggle,.dark-mode .bt-pass-toggle{color:var(--text-muted,#6b7280)}
[data-theme="dark"] .bt-pass-toggle:hover,.dark-mode .bt-pass-toggle:hover{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bt-checkbox,.dark-mode .bt-checkbox{color:var(--heading-color,#e5e7eb)}

/* --- Messages --- */
[data-theme="dark"] .bt-msg.success,.dark-mode .bt-msg.success{background:rgba(5,150,105,.15);color:#34d399}
[data-theme="dark"] .bt-msg.error,.dark-mode .bt-msg.error{background:rgba(239,68,68,.15);color:#f87171}

/* --- Loading / Spinner --- */
[data-theme="dark"] .bt-loading,.dark-mode .bt-loading{color:var(--text-muted,#6b7280)}
[data-theme="dark"] .bt-spinner,.dark-mode .bt-spinner{border-color:var(--border-color,#374151);border-top-color:#5b9cf6}

/* --- Upgrades section --- */
[data-theme="dark"] .bt-upgrades,.dark-mode .bt-upgrades{background:var(--input-bg,#111827);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-upgrades h4,.dark-mode .bt-upgrades h4{color:var(--heading-color,#e5e7eb)}

/* --- Accordion --- */
[data-theme="dark"] .bt-accordion,.dark-mode .bt-accordion{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-accordion-head:hover,.dark-mode .bt-accordion-head:hover{background:var(--input-bg,#111827)}
[data-theme="dark"] .bt-accordion-info h5,.dark-mode .bt-accordion-info h5{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bt-accordion-info p,.dark-mode .bt-accordion-info p{color:var(--text-muted,#9ca3af)}
[data-theme="dark"] .bt-accordion-arrow,.dark-mode .bt-accordion-arrow{color:var(--text-muted,#6b7280)}

/* --- Addon carousel --- */
[data-theme="dark"] .bt-addon-item:hover,.dark-mode .bt-addon-item:hover{background:var(--input-bg,#111827)}
[data-theme="dark"] .bt-addon-name,.dark-mode .bt-addon-name{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bt-addon-price,.dark-mode .bt-addon-price{color:#5b9cf6}
[data-theme="dark"] .bt-addon-btn,.dark-mode .bt-addon-btn{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151);color:var(--heading-color,#d1d5db)}
[data-theme="dark"] .bt-addon-btn:hover,.dark-mode .bt-addon-btn:hover{border-color:#5b9cf6;color:#5b9cf6;background:rgba(91,156,246,.06)}
[data-theme="dark"] .bt-addon-nav,.dark-mode .bt-addon-nav{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151);color:var(--text-muted,#9ca3af);box-shadow:0 2px 6px rgba(0,0,0,.3)}
[data-theme="dark"] .bt-addon-nav:hover,.dark-mode .bt-addon-nav:hover{border-color:#5b9cf6;color:#5b9cf6}
[data-theme="dark"] .bt-addon-dot,.dark-mode .bt-addon-dot{background:var(--border-color,#4b5563)}
[data-theme="dark"] .bt-addon-dot.active,.dark-mode .bt-addon-dot.active{background:#5b9cf6}
[data-theme="dark"] .bt-addon-tooltip,.dark-mode .bt-addon-tooltip{background:#111827;color:#e5e7eb;box-shadow:0 8px 28px rgba(0,0,0,.4)}
[data-theme="dark"] .bt-addon-tip-btn,.dark-mode .bt-addon-tip-btn{color:var(--text-muted,#6b7280)}
[data-theme="dark"] .bt-addon-tip-btn:hover,.dark-mode .bt-addon-tip-btn:hover{color:#5b9cf6}

/* --- WordPress detail panel --- */
[data-theme="dark"] .bwp-detail-panel,.dark-mode .bwp-detail-panel{background:var(--card-bg,#1f2937);box-shadow:0 25px 60px rgba(0,0,0,.5)}
[data-theme="dark"] .bwp-detail-head,.dark-mode .bwp-detail-head{border-bottom-color:var(--border-color,#374151)}
[data-theme="dark"] .bwp-detail-head h5,.dark-mode .bwp-detail-head h5{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bwp-detail-tabs,.dark-mode .bwp-detail-tabs{border-bottom-color:var(--border-color,#374151)}
[data-theme="dark"] .bwp-tab,.dark-mode .bwp-tab{color:var(--text-muted,#9ca3af)}
[data-theme="dark"] .bwp-tab:hover,.dark-mode .bwp-tab:hover{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bwp-tab.active,.dark-mode .bwp-tab.active{color:#5b9cf6;border-bottom-color:#5b9cf6}

/* WP site list */
[data-theme="dark"] .bwp-site:hover,.dark-mode .bwp-site:hover{background:var(--input-bg,#111827)}
[data-theme="dark"] .bwp-site+.bwp-site,.dark-mode .bwp-site+.bwp-site{border-top-color:var(--border-color,#374151)}
[data-theme="dark"] .bwp-site-domain,.dark-mode .bwp-site-domain{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bwp-site-meta,.dark-mode .bwp-site-meta{color:var(--text-muted,#9ca3af)}

/* WP overview */
[data-theme="dark"] .bwp-site-header,.dark-mode .bwp-site-header{background:var(--input-bg,#111827);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bwp-site-header-info h4,.dark-mode .bwp-site-header-info h4{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bwp-site-header-info p,.dark-mode .bwp-site-header-info p{color:var(--text-muted,#9ca3af)}
[data-theme="dark"] .bwp-stat,.dark-mode .bwp-stat{background:var(--input-bg,#111827);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bwp-stat-label,.dark-mode .bwp-stat-label{color:var(--text-muted,#6b7280)}
[data-theme="dark"] .bwp-stat-value,.dark-mode .bwp-stat-value{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bwp-overview-grid,.dark-mode .bwp-overview-grid{color:var(--heading-color,#e5e7eb)}

/* WP preview */
[data-theme="dark"] .bwp-preview-wrap,.dark-mode .bwp-preview-wrap{border-color:var(--border-color,#374151);background:#111827}
[data-theme="dark"] .bwp-preview-bar,.dark-mode .bwp-preview-bar{background:var(--input-bg,#1a2332);border-bottom-color:var(--border-color,#374151)}
[data-theme="dark"] .bwp-preview-url,.dark-mode .bwp-preview-url{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151);color:var(--text-muted,#9ca3af)}
[data-theme="dark"] .bwp-preview-frame-wrap,.dark-mode .bwp-preview-frame-wrap{background:#111827}

/* WP plugins/themes items */
[data-theme="dark"] .bwp-item-row,.dark-mode .bwp-item-row{border-bottom-color:var(--border-color,#374151)}
[data-theme="dark"] .bwp-item-name,.dark-mode .bwp-item-name{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bwp-item-detail,.dark-mode .bwp-item-detail{color:var(--text-muted,#9ca3af)}
[data-theme="dark"] .bwp-item-btn,.dark-mode .bwp-item-btn{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151);color:var(--heading-color,#d1d5db)}
[data-theme="dark"] .bwp-item-btn:hover,.dark-mode .bwp-item-btn:hover{border-color:#5b9cf6;color:#5b9cf6}
[data-theme="dark"] .bwp-item-btn.active-state,.dark-mode .bwp-item-btn.active-state{color:#34d399;border-color:#34d399}
[data-theme="dark"] .bwp-item-btn.inactive-state,.dark-mode .bwp-item-btn.inactive-state{color:#fbbf24;border-color:#fbbf24}
[data-theme="dark"] .bwp-item-btn.update,.dark-mode .bwp-item-btn.update{color:#5b9cf6;border-color:#5b9cf6}
[data-theme="dark"] .bwp-item-btn.delete,.dark-mode .bwp-item-btn.delete{color:#f87171;border-color:#f87171}

/* WP theme cards */
[data-theme="dark"] .bwp-theme-card,.dark-mode .bwp-theme-card{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bwp-theme-card:hover,.dark-mode .bwp-theme-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.3)}
[data-theme="dark"] .bwp-theme-active,.dark-mode .bwp-theme-active{border-color:#5b9cf6;box-shadow:0 0 0 2px rgba(91,156,246,.2)}
[data-theme="dark"] .bwp-theme-screenshot,.dark-mode .bwp-theme-screenshot{background:#111827}
[data-theme="dark"] .bwp-theme-name,.dark-mode .bwp-theme-name{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bwp-theme-ver,.dark-mode .bwp-theme-ver{color:var(--text-muted,#9ca3af)}

/* WP security */
[data-theme="dark"] .bwp-sec-summary,.dark-mode .bwp-sec-summary{background:var(--input-bg,#111827);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bwp-sec-summary-bar,.dark-mode .bwp-sec-summary-bar{background:var(--border-color,#374151)}
[data-theme="dark"] .bwp-sec-summary-text,.dark-mode .bwp-sec-summary-text{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bwp-security-item,.dark-mode .bwp-security-item{border-bottom-color:var(--border-color,#374151)}
[data-theme="dark"] .bwp-sec-label,.dark-mode .bwp-sec-label{color:var(--heading-color,#e5e7eb)}
[data-theme="dark"] .bwp-sec-detail,.dark-mode .bwp-sec-detail{color:var(--text-muted,#9ca3af)}

/* WP messages */
[data-theme="dark"] .bwp-msg.success,.dark-mode .bwp-msg.success{background:rgba(5,150,101,.15);color:#34d399}
[data-theme="dark"] .bwp-msg.error,.dark-mode .bwp-msg.error{background:rgba(239,68,68,.15);color:#f87171}
[data-theme="dark"] .bwp-msg.info,.dark-mode .bwp-msg.info{background:rgba(91,156,246,.12);color:#5b9cf6}

/* --- Database section inline text --- */
[data-theme="dark"] .bt-list [style*="text-transform:uppercase"],.dark-mode .bt-list [style*="text-transform:uppercase"]{color:var(--text-muted,#6b7280)!important}

/* --- Badges in dark mode (softer backgrounds) --- */
[data-theme="dark"] .bt-badge-primary,.dark-mode .bt-badge-primary{background:rgba(91,156,246,.12);color:#5b9cf6}
[data-theme="dark"] .bt-badge-green,.dark-mode .bt-badge-green{background:rgba(52,211,153,.12);color:#34d399}
[data-theme="dark"] .bt-badge-purple,.dark-mode .bt-badge-purple{background:rgba(167,139,250,.12);color:#a78bfa}
[data-theme="dark"] .bt-badge-amber,.dark-mode .bt-badge-amber{background:rgba(251,191,36,.12);color:#fbbf24}

/* --- Row icon backgrounds in dark mode --- */
[data-theme="dark"] .bt-row-icon.ns,.dark-mode .bt-row-icon.ns{background:rgba(91,156,246,.12);color:#5b9cf6}
[data-theme="dark"] .bt-row-icon.ip,.dark-mode .bt-row-icon.ip{background:rgba(52,211,153,.12);color:#34d399}
[data-theme="dark"] .bt-row-icon.email,.dark-mode .bt-row-icon.email{background:rgba(91,156,246,.12);color:#5b9cf6}
[data-theme="dark"] .bt-row-icon.main,.dark-mode .bt-row-icon.main{background:rgba(91,156,246,.12);color:#5b9cf6}
[data-theme="dark"] .bt-row-icon.addon,.dark-mode .bt-row-icon.addon{background:rgba(52,211,153,.12);color:#34d399}
[data-theme="dark"] .bt-row-icon.sub,.dark-mode .bt-row-icon.sub{background:rgba(167,139,250,.12);color:#a78bfa}
[data-theme="dark"] .bt-row-icon.parked,.dark-mode .bt-row-icon.parked{background:rgba(251,191,36,.12);color:#fbbf24}
[data-theme="dark"] .bt-row-icon.db,.dark-mode .bt-row-icon.db{background:rgba(91,156,246,.12);color:#5b9cf6}
[data-theme="dark"] .bt-row-icon.dbuser,.dark-mode .bt-row-icon.dbuser{background:rgba(167,139,250,.12);color:#a78bfa}

/* --- WP site icon / status badges in dark --- */
[data-theme="dark"] .bwp-site-icon,.dark-mode .bwp-site-icon{background:rgba(91,156,246,.12);color:#5b9cf6}
[data-theme="dark"] .bwp-item-icon.plugin,.dark-mode .bwp-item-icon.plugin{background:rgba(91,156,246,.12);color:#5b9cf6}
[data-theme="dark"] .bwp-item-icon.theme,.dark-mode .bwp-item-icon.theme{background:rgba(167,139,250,.12);color:#a78bfa}
[data-theme="dark"] .bwp-sec-icon.ok,.dark-mode .bwp-sec-icon.ok{background:rgba(52,211,153,.12);color:#34d399}
[data-theme="dark"] .bwp-sec-icon.warning,.dark-mode .bwp-sec-icon.warning{background:rgba(251,191,36,.12);color:#fbbf24}

/* --- Addon icon backgrounds in dark --- */
[data-theme="dark"] .bt-addon-icon.addon,.dark-mode .bt-addon-icon.addon{background:rgba(167,139,250,.12);color:#a78bfa}
[data-theme="dark"] .bt-addon-icon.upgrade,.dark-mode .bt-addon-icon.upgrade{background:rgba(52,211,153,.12);color:#34d399}

/* --- Delete modal inline-styled text --- */
[data-theme="dark"] .bt-modal-body p,.dark-mode .bt-modal-body p{color:var(--heading-color,#d1d5db)}
[data-theme="dark"] .bt-modal-body,.dark-mode .bt-modal-body{color:var(--heading-color,#d1d5db)}

/* --- Database section inline heading --- */
[data-theme="dark"] .bt-list div[style],.dark-mode .bt-list div[style]{color:var(--text-muted,#6b7280)!important}

/* --- WP status badges in dark --- */
[data-theme="dark"] .bwp-status-badge.active,.dark-mode .bwp-status-badge.active{background:rgba(52,211,153,.12);color:#34d399}
[data-theme="dark"] .bwp-status-badge.inactive,.dark-mode .bwp-status-badge.inactive{background:rgba(251,191,36,.12);color:#fbbf24}
[data-theme="dark"] .bwp-status-badge.update-available,.dark-mode .bwp-status-badge.update-available{background:rgba(91,156,246,.12);color:#5b9cf6}

/* --- Quick action buttons in WP panel --- */
[data-theme="dark"] .bwp-quick-actions .bt-row-btn,.dark-mode .bwp-quick-actions .bt-row-btn{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151);color:var(--heading-color,#d1d5db)}

/* --- Ensure SVG strokes are visible in dark --- */
[data-theme="dark"] .bt-empty svg,.dark-mode .bt-empty svg{opacity:.3}

/* --- SSL Pane dark mode --- */
[data-theme="dark"] .bt-row-icon.ssl-valid,.dark-mode .bt-row-icon.ssl-valid{background:rgba(52,211,153,.12);color:#34d399}
[data-theme="dark"] .bt-row-icon.ssl-selfsigned,.dark-mode .bt-row-icon.ssl-selfsigned{background:rgba(251,191,36,.12);color:#fbbf24}
[data-theme="dark"] .bt-row-icon.ssl-expired,.dark-mode .bt-row-icon.ssl-expired{background:rgba(248,113,113,.12);color:#f87171}
[data-theme="dark"] .bt-row-icon.ssl-expiring,.dark-mode .bt-row-icon.ssl-expiring{background:rgba(251,191,36,.12);color:#fbbf24}
[data-theme="dark"] .bt-badge-red,.dark-mode .bt-badge-red{background:rgba(248,113,113,.12);color:#f87171}
[data-theme="dark"] .bt-ssl-meta,.dark-mode .bt-ssl-meta{color:var(--text-muted,#6b7280)}
[data-theme="dark"] .bt-ssl-issuer,.dark-mode .bt-ssl-issuer{color:var(--text-muted,#9ca3af)}
[data-theme="dark"] .bt-ssl-days-ok,.dark-mode .bt-ssl-days-ok{color:#34d399}
[data-theme="dark"] .bt-ssl-days-warn,.dark-mode .bt-ssl-days-warn{color:#fbbf24}
[data-theme="dark"] .bt-ssl-days-danger,.dark-mode .bt-ssl-days-danger{color:#f87171}
[data-theme="dark"] .bt-ssl-generate,.dark-mode .bt-ssl-generate{color:#34d399!important;border-color:#34d399!important}
[data-theme="dark"] .bt-ssl-generate:hover,.dark-mode .bt-ssl-generate:hover{background:rgba(52,211,153,.08)!important}
[data-theme="dark"] #btSslRunAutossl,.dark-mode #btSslRunAutossl{background:#059669}

/* --- DNS Manager dark mode --- */
[data-theme="dark"] .bt-dns-toolbar,.dark-mode .bt-dns-toolbar{border-bottom-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-dns-filter-bar,.dark-mode .bt-dns-filter-bar{border-bottom-color:var(--border-color,#374151);background:var(--input-bg,#111827)}
[data-theme="dark"] .bt-dns-filter-btn,.dark-mode .bt-dns-filter-btn{background:var(--card-bg,#1f2937)!important;border-color:var(--border-color,#374151)!important;color:var(--heading-color,#d1d5db)!important}
[data-theme="dark"] .bt-dns-filter-btn.active,.dark-mode .bt-dns-filter-btn.active{background:#2563eb!important;color:#fff!important;border-color:#2563eb!important}
[data-theme="dark"] .bt-dns-filter-btn:hover,.dark-mode .bt-dns-filter-btn:hover{border-color:#5b9cf6!important;color:#5b9cf6!important}
[data-theme="dark"] .bt-dns-domain-row:hover,.dark-mode .bt-dns-domain-row:hover{background:var(--input-bg,#111827)}
[data-theme="dark"] #btDnsAddFields textarea,.dark-mode #btDnsAddFields textarea,[data-theme="dark"] #btDnsEditFields textarea,.dark-mode #btDnsEditFields textarea{background:var(--input-bg,#111827);border-color:var(--border-color,#374151);color:var(--heading-color,#e5e7eb)}

/* Sidebar dark mode */
[data-theme="dark"] .list-group-tab-nav .list-group-item,.dark-mode .list-group-tab-nav .list-group-item{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151);color:var(--heading-color,#e5e7eb);box-shadow:0 1px 2px rgba(0,0,0,.12)}
[data-theme="dark"] .list-group-tab-nav .list-group-item:hover,.dark-mode .list-group-tab-nav .list-group-item:hover{border-color:rgba(91,156,246,.3);box-shadow:0 2px 8px rgba(91,156,246,.1);color:#5b9cf6}
[data-theme="dark"] .panel-actions .panel-heading .panel-title,.dark-mode .panel-actions .panel-heading .panel-title,[data-theme="dark"] .panel-default>.panel-heading .panel-title,.dark-mode .panel-default>.panel-heading .panel-title{color:var(--text-muted,#9ca3af)}
</style>';
}


function broodle_tools_css_responsive()
{
    return '<style>
@media(max-width:600px){
.bt-row-btn span{display:none!important}.bt-row-actions{gap:4px}.bt-row-btn{padding:5px 7px}
.bt-card-head{flex-direction:column;align-items:flex-start;gap:10px}
.bt-card-head-right{width:100%}.bt-card-head-right>*{flex:1}
.bwp-overview-hero{flex-direction:column}.bwp-preview-col{width:100%}
.bwp-theme-grid{grid-template-columns:1fr 1fr}
.bwp-detail-panel{max-width:100%;max-height:100vh;border-radius:0}
.bwp-site-actions{flex-direction:column}
}
@media(max-width:400px){.bwp-theme-grid{grid-template-columns:1fr}}
.bt-wp-sidebar-item.active{border-color:#0a5ed3!important;background:rgba(10,94,211,.06)!important;color:#0a5ed3!important;box-shadow:0 2px 8px rgba(10,94,211,.12)!important}
</style>';
}

/* ─── Shared Script ───────────────────────────────────────── */

function broodle_tools_shared_script_raw()
{
    return <<<'BTSCRIPT'
(function(){
"use strict";
var ajaxUrl="modules/addons/broodle_whmcs_tools/ajax.php";
var wpAjaxUrl="modules/addons/broodle_whmcs_tools/ajax_wordpress.php";
var C={};
var wpInstances=[];var currentWpInstance=null;

function esc(s){var d=document.createElement("div");d.textContent=s;return d.innerHTML;}
function $(id){return document.getElementById(id);}
function showMsg(el,msg,ok){el.textContent=msg;el.className="bt-msg "+(ok?"success":"error");el.style.display="block";}
function ajax(url,data,cb){
    var fd=new FormData();for(var k in data)fd.append(k,data[k]);
    fd.append("service_id",C.serviceId);
    var x=new XMLHttpRequest();x.open("POST",url,true);
    x.onload=function(){try{cb(JSON.parse(x.responseText));}catch(e){cb({success:false,message:"Invalid response"});}};
    x.onerror=function(){cb({success:false,message:"Network error"});};
    x.send(fd);
}
function post(data,cb){ajax(ajaxUrl,data,cb);}
function wpPost(data,cb){ajax(wpAjaxUrl,data,cb);}
function doCopy(t,btn){if(navigator.clipboard){navigator.clipboard.writeText(t).then(function(){btn.classList.add("copied");setTimeout(function(){btn.classList.remove("copied");},1500);});}else{var ta=document.createElement("textarea");ta.value=t;ta.style.cssText="position:fixed;opacity:0";document.body.appendChild(ta);ta.select();document.execCommand("copy");document.body.removeChild(ta);btn.classList.add("copied");setTimeout(function(){btn.classList.remove("copied");},1500);}}

var wpSvg16="<svg width=\\"16\\" height=\\"16\\" viewBox=\\"0 0 16 16\\" fill=\\"currentColor\\"><path d=\\"M12.633 7.653c0-.848-.305-1.435-.566-1.892l-.08-.13c-.317-.51-.594-.958-.594-1.48 0-.63.478-1.218 1.152-1.218q.03 0 .058.003l.031.003A6.84 6.84 0 0 0 8 1.137 6.86 6.86 0 0 0 2.266 4.23c.16.005.313.009.442.009.717 0 1.828-.087 1.828-.087.37-.022.414.521.044.565 0 0-.371.044-.785.065l2.5 7.434 1.5-4.506-1.07-2.929c-.369-.022-.719-.065-.719-.065-.37-.022-.326-.588.043-.566 0 0 1.134.087 1.808.087.718 0 1.83-.087 1.83-.087.37-.022.413.522.043.566 0 0-.372.043-.785.065l2.48 7.377.684-2.287.054-.173c.27-.86.469-1.495.469-2.046zM1.137 8a6.86 6.86 0 0 0 3.868 6.176L1.73 5.206A6.8 6.8 0 0 0 1.137 8\\"/><path d=\\"M6.061 14.583 8.121 8.6l2.109 5.78q.02.05.049.094a6.85 6.85 0 0 1-4.218.109m7.96-9.876q.046.328.047.706c0 .696-.13 1.479-.522 2.458l-2.096 6.06a6.86 6.86 0 0 0 2.572-9.224z\\"/><path fill-rule=\\"evenodd\\" d=\\"M0 8c0-4.411 3.589-8 8-8s8 3.589 8 8-3.59 8-8 8-8-3.589-8-8m.367 0c0 4.209 3.424 7.633 7.633 7.633S15.632 12.209 15.632 8C15.632 3.79 12.208.367 8 .367 3.79.367.367 3.79.367 8\\"/></svg>";
var wpSvg20=wpSvg16.replace(/width=\\"16\\"/g,"width=\\"20\\"").replace(/height=\\"16\\"/g,"height=\\"20\\"");
var wpSvg32=wpSvg16.replace(/width=\\"16\\"/g,"width=\\"32\\"").replace(/height=\\"16\\"/g,"height=\\"32\\"");

/* ─── Addon/Upgrade Icon Helpers ─── */
function btAddonIcon(name){
    var n=name.toLowerCase();
    if(n.indexOf("wordpress")!==-1||n.indexOf("wp ")!==-1||n.indexOf("wp manager")!==-1) return wpSvg16;
    if(n.indexOf("site builder")!==-1||n.indexOf("sitebuilder")!==-1||n.indexOf("weebly")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x273\\x27 y=\\x273\\x27 width=\\x2718\\x27 height=\\x2718\\x27 rx=\\x272\\x27/><path d=\\x27M3 9h18M9 21V9\\x27/></svg>";
    if(n.indexOf("ssl")!==-1||n.indexOf("certificate")!==-1||n.indexOf("digicert")!==-1||n.indexOf("geotrust")!==-1||n.indexOf("rapidssl")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><path d=\\x27M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z\\x27/></svg>";
    if(n.indexOf("aapanel")!==-1||n.indexOf("cyberpanel")!==-1||n.indexOf("control panel")!==-1||n.indexOf("cpanel")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x272\\x27 y=\\x273\\x27 width=\\x2720\\x27 height=\\x2714\\x27 rx=\\x272\\x27/><line x1=\\x278\\x27 y1=\\x2721\\x27 x2=\\x2716\\x27 y2=\\x2721\\x27/><line x1=\\x2712\\x27 y1=\\x2717\\x27 x2=\\x2712\\x27 y2=\\x2721\\x27/></svg>";
    if(n.indexOf("store")!==-1||n.indexOf("ecommerce")!==-1||n.indexOf("woocommerce")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><circle cx=\\x279\\x27 cy=\\x2721\\x27 r=\\x271\\x27/><circle cx=\\x2720\\x27 cy=\\x2721\\x27 r=\\x271\\x27/><path d=\\x271 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6\\x27/></svg>";
    if(n.indexOf("ip address")!==-1||n.indexOf("public ip")!==-1||n.indexOf("additional ip")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><circle cx=\\x2712\\x27 cy=\\x2712\\x27 r=\\x2710\\x27/><line x1=\\x272\\x27 y1=\\x2712\\x27 x2=\\x2722\\x27 y2=\\x2712\\x27/><path d=\\x27M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z\\x27/></svg>";
    return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><path d=\\x27M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z\\x27/></svg>";
}
function btUpgradeIcon(name){
    var n=name.toLowerCase();
    if(n.indexOf("ram")!==-1||n.indexOf("memory")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x272\\x27 y=\\x276\\x27 width=\\x2720\\x27 height=\\x2712\\x27 rx=\\x272\\x27/><path d=\\x276 6V4M10 6V4M14 6V4M18 6V4\\x27/></svg>";
    if(n.indexOf("backup")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><path d=\\x27M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\\x27/><polyline points=\\x277 10 12 15 17 10\\x27/><line x1=\\x2712\\x27 y1=\\x2715\\x27 x2=\\x2712\\x27 y2=\\x273\\x27/></svg>";
    if(n.indexOf("cpu")!==-1||n.indexOf("vcpu")!==-1||n.indexOf("core")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x274\\x27 y=\\x274\\x27 width=\\x2716\\x27 height=\\x2716\\x27 rx=\\x272\\x27/><rect x=\\x279\\x27 y=\\x279\\x27 width=\\x276\\x27 height=\\x276\\x27/><path d=\\x272 9h2M2 15h2M20 9h2M20 15h2M9 2v2M15 2v2M9 20v2M15 20v2\\x27/></svg>";
    if(n.indexOf("disk")!==-1||n.indexOf("storage")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><ellipse cx=\\x2712\\x27 cy=\\x275\\x27 rx=\\x279\\x27 ry=\\x273\\x27/><path d=\\x27M21 12c0 1.66-4 3-9 3s-9-1.34-9-3\\x27/><path d=\\x27M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5\\x27/></svg>";
    if(n.indexOf("bandwidth")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><path d=\\x27M22 12h-4l-3 9L9 3l-3 9H2\\x27/></svg>";
    return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><polyline points=\\x2723 6 13.5 15.5 8.5 10.5 1 18\\x27/><polyline points=\\x2717 6 23 6 23 12\\x27/></svg>";
}

/* ─── Init ─── */
function init(){
    var dataEl=$("bt-data");
    if(dataEl){
        try{C=JSON.parse(dataEl.getAttribute("data-config"));}catch(e){return;}
    }else if(window.__btConfig){
        C=window.__btConfig;
    }else{
        return;
    }
    // Inject modals dynamically if they weren't rendered by the footer hook
    if(!$("bemCreateModal")&&C.serviceId){
        var modalsHtml=buildModalsHtml();
        var tmp=document.createElement("div");
        tmp.innerHTML=modalsHtml;
        while(tmp.firstChild) document.body.appendChild(tmp.firstChild);
    }
    hideDefaultTabs();
    buildTabs();
    bindModals();
}

function buildModalsHtml(){
    return '<div class="bt-overlay" id="bemCreateModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Create Email Account</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Email Address</label><div class="bt-input-group"><input type="text" id="bemNewUser" placeholder="username" autocomplete="off"><span class="bt-at">@</span><select id="bemNewDomain"><option>Loading...</option></select></div></div><div class="bt-field"><label>Password</label><div class="bt-pass-wrap"><input type="password" id="bemNewPass" placeholder="Strong password" autocomplete="new-password"><button type="button" class="bt-pass-toggle" data-toggle-pass="bemNewPass">&#128065;</button></div></div><div class="bt-field"><label>Quota (MB)</label><input type="number" id="bemNewQuota" value="250" min="1"></div><div class="bt-msg" id="bemCreateMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bemCreateSubmit">Create Account</button></div></div></div>'
    +'<div class="bt-overlay" id="bemPassModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Change Password</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Email</label><input type="text" id="bemPassEmail" readonly></div><div class="bt-field"><label>New Password</label><div class="bt-pass-wrap"><input type="password" id="bemPassNew" placeholder="New password" autocomplete="new-password"><button type="button" class="bt-pass-toggle" data-toggle-pass="bemPassNew">&#128065;</button></div></div><div class="bt-msg" id="bemPassMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bemPassSubmit">Update Password</button></div></div></div>'
    +'<div class="bt-overlay" id="bemDelModal" style="display:none"><div class="bt-modal bt-modal-sm"><div class="bt-modal-head"><h5>Delete Email Account</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body" style="text-align:center"><p style="margin:0 0 4px;font-size:14px">Are you sure you want to delete</p><p style="margin:0;font-size:15px;font-weight:600;color:#ef4444" id="bemDelEmail"></p><div class="bt-msg" id="bemDelMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-danger" id="bemDelSubmit">Delete</button></div></div></div>'
    +'<div class="bt-overlay" id="bdmAddonModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Add Addon Domain</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Domain Name</label><input type="text" id="bdmAddonDomain" placeholder="example.com" autocomplete="off"></div><div class="bt-field"><label>Document Root</label><div class="bt-docroot-wrap"><span class="bt-docroot-prefix">/home/user/</span><input type="text" id="bdmAddonDocroot" placeholder="example.com"></div></div><div class="bt-msg" id="bdmAddonMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdmAddonSubmit">Add Domain</button></div></div></div>'
    +'<div class="bt-overlay" id="bdmSubModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Add Subdomain</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Subdomain</label><div class="bt-input-group"><input type="text" id="bdmSubName" placeholder="blog" autocomplete="off"><span class="bt-at">.</span><select id="bdmSubParent"><option>Loading...</option></select></div></div><div class="bt-field"><label>Document Root</label><div class="bt-docroot-wrap"><span class="bt-docroot-prefix">/home/user/</span><input type="text" id="bdmSubDocroot" placeholder="blog.example.com"></div></div><div class="bt-msg" id="bdmSubMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdmSubSubmit">Add Subdomain</button></div></div></div>'
    +'<div class="bt-overlay" id="bdmDelModal" style="display:none"><div class="bt-modal bt-modal-sm"><div class="bt-modal-head"><h5>Delete Domain</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body" style="text-align:center"><p style="margin:0 0 4px;font-size:14px">Are you sure you want to delete</p><p style="margin:0;font-size:15px;font-weight:600;color:#ef4444" id="bdmDelDomain"></p><div class="bt-msg" id="bdmDelMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-danger" id="bdmDelSubmit">Delete</button></div></div></div>'
    +'<div class="bt-overlay" id="bdbCreateModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Create Database</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Database Name</label><div class="bt-input-group"><span class="bt-prefix" id="bdbPrefix">user_</span><input type="text" id="bdbNewName" placeholder="mydb" autocomplete="off"></div></div><div class="bt-msg" id="bdbCreateMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdbCreateSubmit">Create Database</button></div></div></div>'
    +'<div class="bt-overlay" id="bdbUserModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Create Database User</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Username</label><div class="bt-input-group"><span class="bt-prefix" id="bdbUserPrefix">user_</span><input type="text" id="bdbNewUser" placeholder="dbuser" autocomplete="off"></div></div><div class="bt-field"><label>Password</label><div class="bt-pass-wrap"><input type="password" id="bdbUserPass" placeholder="Strong password" autocomplete="new-password"><button type="button" class="bt-pass-toggle" data-toggle-pass="bdbUserPass">&#128065;</button></div></div><div class="bt-msg" id="bdbUserMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdbUserSubmit">Create User</button></div></div></div>'
    +'<div class="bt-overlay" id="bdbAssignModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Assign User to Database</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Database</label><select id="bdbAssignDb" class="bt-select"></select></div><div class="bt-field"><label>User</label><select id="bdbAssignUser" class="bt-select"></select></div><div class="bt-field"><label>Privileges</label><label class="bt-checkbox"><input type="checkbox" id="bdbAssignAll" checked> All Privileges</label></div><div class="bt-msg" id="bdbAssignMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdbAssignSubmit">Assign Privileges</button></div></div></div>'
    +'<div class="bt-overlay" id="bwpDetailOverlay" style="display:none"><div class="bwp-detail-panel"><div class="bwp-detail-head"><h5 id="bwpDetailTitle">Site Details</h5><button type="button" class="bt-modal-close" id="bwpDetailClose">&times;</button></div><div class="bwp-detail-tabs"><button type="button" class="bwp-tab active" data-tab="overview">Overview</button><button type="button" class="bwp-tab" data-tab="plugins">Plugins</button><button type="button" class="bwp-tab" data-tab="themes">Themes</button><button type="button" class="bwp-tab" data-tab="security">Security</button></div><div class="bwp-detail-body" id="bwpDetailBody"><div class="bwp-tab-content active" id="bwpTabOverview"></div><div class="bwp-tab-content" id="bwpTabPlugins"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading plugins...</span></div></div><div class="bwp-tab-content" id="bwpTabThemes"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading themes...</span></div></div><div class="bwp-tab-content" id="bwpTabSecurity"><div class="bt-loading"><div class="bt-spinner"></div><span>Running security scan...</span></div></div></div></div></div>';
}

function hideDefaultTabs(){
    // Hide default WHMCS tab navigation
    var selectors=["ul.panel-tabs.nav.nav-tabs",".product-details-tab-container",".section-body > ul.nav.nav-tabs",".panel > ul.nav.nav-tabs"];
    selectors.forEach(function(sel){
        document.querySelectorAll(sel).forEach(function(el){
            el.style.display="none";
            var sib=el.nextElementSibling;
            while(sib){if(sib.classList&&(sib.classList.contains("tab-content")||sib.classList.contains("product-details-tab-container"))){sib.style.display="none";break;}sib=sib.nextElementSibling;}
        });
    });
    ["billingInfo","tabOverview","domainInfo","tabAddons"].forEach(function(id){var el=$(id);if(el)el.style.display="none";});
    // Hide the panel containing the default tabs
    var panelTabs=document.querySelector("ul.panel-tabs");
    if(panelTabs){var panel=panelTabs.closest(".panel");if(panel) panel.style.display="none";}
    // Hide the cPanel overview content (product-details, usage stats, shortcuts, etc.)
    // We replace all of this with our custom tabbed UI
    var overviewPane=document.getElementById("Overview");
    if(overviewPane){
        // Hide all direct children of #Overview except our bt-wrap
        var children=overviewPane.children;
        for(var i=0;i<children.length;i++){
            if(children[i].id!=="bt-wrap") children[i].style.display="none";
        }
    }
    // Hide Quick Create Email section
    document.querySelectorAll(".quick-create-email,.quick-create-email-section,[class*=quick-create-email],.module-quick-create-email,.quick-create-section,.module-quick-shortcuts,.quick-shortcuts-container,.quick-shortcuts,.quick-shortcut-container,.quick-shortcut,.sidebar-shortcuts,.sidebar-quick-create,[class*=quick-create],[class*=quick-shortcut],#cPanelQuickEmailPanel,#cPanelExtrasPurchasePanel").forEach(function(el){el.style.display="none";});
    // Hide .section elements by title text (Lagom theme uses .section > .section-header > h2.section-title)
    document.querySelectorAll(".section").forEach(function(sec){
        var title=sec.querySelector(".section-title,h2,h3");
        if(!title) return;
        var t=(title.textContent||"").toLowerCase().trim();
        if(t.indexOf("quick create email")!==-1||t.indexOf("addons and extras")!==-1||t.indexOf("addon")!==-1&&t.indexOf("extra")!==-1){
            sec.classList.add("bt-hidden-section");sec.style.display="none";
            sec.setAttribute("data-bt-hidden","1");
        }
    });
    // Hide Addons & Extras panels (we move content to overview)
    document.querySelectorAll(".panel,.card,.sidebar-box,.sidebar-panel").forEach(function(p){
        var h=p.querySelector(".panel-heading,.card-header,h3,h4,h5,.panel-title,.sidebar-header,.sidebar-title");
        if(!h) return;
        var t=(h.textContent||"").toLowerCase();
        if(t.indexOf("addon")!==-1||t.indexOf("extra")!==-1||t.indexOf("configurable")!==-1||t.indexOf("quick create email")!==-1||t.indexOf("quick create")!==-1||t.indexOf("shortcut")!==-1){
            p.setAttribute("data-bt-hidden","1");p.style.display="none";
        }
    });
    // Also hide by sidebar menu item IDs
    ["Primary_Sidebar-productdetails_addons_and_extras"].forEach(function(id){var el=$(id);if(el)el.style.display="none";});
}

function buildTabs(){
    // Find the best insertion point:
    // 1. The #Overview pane (Lagom2 with cPanel overview.tpl)
    // 2. The .tab-content.margin-bottom container
    // 3. Fallback to first .panel or .section-body
    var overviewPane=document.getElementById("Overview");
    var tabContent=document.querySelector(".tab-content.margin-bottom");
    var insertTarget=null;var insertMode="after";
    if(overviewPane){
        // Insert our tabs as the first child of #Overview, before the cPanel overview content
        insertTarget=overviewPane;insertMode="prepend";
    }else if(tabContent){
        insertTarget=tabContent;insertMode="after";
    }else{
        var hiddenPanel=document.querySelector("ul.panel-tabs");
        insertTarget=hiddenPanel?hiddenPanel.closest(".panel"):null;
        if(!insertTarget) insertTarget=document.querySelector(".panel")||document.querySelector(".section-body");
        insertMode="after";
    }
    if(!insertTarget) return;

    var wrap=document.createElement("div");
    wrap.className="bt-wrap";wrap.id="bt-wrap";

    // WordPress icon — official WP logo
    var wpIcon="<svg viewBox=\\x270 0 16 16\\x27 fill=\\x27currentColor\\x27><path d=\\x27M12.633 7.653c0-.848-.305-1.435-.566-1.892l-.08-.13c-.317-.51-.594-.958-.594-1.48 0-.63.478-1.218 1.152-1.218q.03 0 .058.003l.031.003A6.84 6.84 0 0 0 8 1.137 6.86 6.86 0 0 0 2.266 4.23c.16.005.313.009.442.009.717 0 1.828-.087 1.828-.087.37-.022.414.521.044.565 0 0-.371.044-.785.065l2.5 7.434 1.5-4.506-1.07-2.929c-.369-.022-.719-.065-.719-.065-.37-.022-.326-.588.043-.566 0 0 1.134.087 1.808.087.718 0 1.83-.087 1.83-.087.37-.022.413.522.043.566 0 0-.372.043-.785.065l2.48 7.377.684-2.287.054-.173c.27-.86.469-1.495.469-2.046zM1.137 8a6.86 6.86 0 0 0 3.868 6.176L1.73 5.206A6.8 6.8 0 0 0 1.137 8\\x27/><path d=\\x27M6.061 14.583 8.121 8.6l2.109 5.78q.02.05.049.094a6.85 6.85 0 0 1-4.218.109m7.96-9.876q.046.328.047.706c0 .696-.13 1.479-.522 2.458l-2.096 6.06a6.86 6.86 0 0 0 2.572-9.224z\\x27/><path fill-rule=\\x27evenodd\\x27 d=\\x27M0 8c0-4.411 3.589-8 8-8s8 3.589 8 8-3.59 8-8 8-8-3.589-8-8m.367 0c0 4.209 3.424 7.633 7.633 7.633S15.632 12.209 15.632 8C15.632 3.79 12.208.367 8 .367 3.79.367.367 3.79.367 8\\x27/></svg>";

    var tabs=[
        {id:"overview",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x273\\x27 y=\\x273\\x27 width=\\x2718\\x27 height=\\x2718\\x27 rx=\\x272\\x27/><path d=\\x27M3 9h18M9 21V9\\x27/></svg>",label:"Overview"},
        {id:"domains",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><circle cx=\\x2712\\x27 cy=\\x2712\\x27 r=\\x2710\\x27/><line x1=\\x272\\x27 y1=\\x2712\\x27 x2=\\x2722\\x27 y2=\\x2712\\x27/><path d=\\x27M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z\\x27/></svg>",label:"Domains",check:"domainEnabled"},
        {id:"ssl",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><path d=\\x27M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z\\x27/></svg>",label:"SSL",check:"sslEnabled"},
        {id:"email",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x272\\x27 y=\\x274\\x27 width=\\x2720\\x27 height=\\x2716\\x27 rx=\\x272\\x27/><path d=\\x27m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7\\x27/></svg>",label:"Email Accounts",check:"emailEnabled"},
        {id:"databases",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><ellipse cx=\\x2712\\x27 cy=\\x275\\x27 rx=\\x279\\x27 ry=\\x273\\x27/><path d=\\x27M21 12c0 1.66-4 3-9 3s-9-1.34-9-3\\x27/><path d=\\x27M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5\\x27/></svg>",label:"Databases",check:"dbEnabled"},
        {id:"dns",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><path d=\\x27M12 2L2 7l10 5 10-5-10-5z\\x27/><path d=\\x27M2 17l10 5 10-5\\x27/><path d=\\x27M2 12l10 5 10-5\\x27/></svg>",label:"DNS Manager",check:"dnsEnabled"},
        {id:"cronjobs",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><circle cx=\\x2712\\x27 cy=\\x2712\\x27 r=\\x2710\\x27/><polyline points=\\x2712 6 12 12 16 14\\x27/></svg>",label:"Cron Jobs",check:"cronEnabled"},
        {id:"phpversion",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><polyline points=\\x2716 18 22 12 16 6\\x27/><polyline points=\\x278 6 2 12 8 18\\x27/><line x1=\\x2714\\x27 y1=\\x274\\x27 x2=\\x2710\\x27 y2=\\x2720\\x27/></svg>",label:"PHP",check:"phpEnabled"},
        {id:"errorlogs",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><path d=\\x27M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z\\x27/><polyline points=\\x2714 2 14 8 20 8\\x27/><line x1=\\x2716\\x27 y1=\\x2713\\x27 x2=\\x278\\x27 y2=\\x2713\\x27/><line x1=\\x2716\\x27 y1=\\x2717\\x27 x2=\\x278\\x27 y2=\\x2717\\x27/></svg>",label:"Error Logs",check:"logsEnabled"},
        {id:"analytics",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><path d=\\x27M18 20V10\\x27/><path d=\\x27M12 20V4\\x27/><path d=\\x27M6 20v-6\\x27/></svg>",label:"Analytics",check:"analyticsEnabled"}
    ];

    var nav=document.createElement("div");nav.className="bt-tabs-nav";
    var panes=document.createElement("div");
    var firstTab=true;

    tabs.forEach(function(t){
        if(t.check&&!C[t.check]) return;
        var btn=document.createElement("button");
        btn.type="button";
        btn.className="bt-tab-btn"+(firstTab?" active":"");
        btn.setAttribute("data-tab",t.id);
        btn.innerHTML=t.icon+" "+t.label;
        btn.addEventListener("click",function(){
            nav.querySelectorAll(".bt-tab-btn").forEach(function(b){b.classList.remove("active");});
            panes.querySelectorAll(".bt-tab-pane").forEach(function(p){p.classList.remove("active");});
            btn.classList.add("active");
            var pane=$("bt-pane-"+t.id);if(pane) pane.classList.add("active");
            // Deactivate WordPress sidebar item when switching to a regular tab
            var wpSb=document.querySelector(".bt-wp-sidebar-item");
            if(wpSb) wpSb.classList.remove("active");
            if(t.id==="databases"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadDatabases();}
            if(t.id==="ssl"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadSSLStatus();}
            if(t.id==="dns"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadDnsDomains();}
            if(t.id==="cronjobs"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadCronJobs();}
            if(t.id==="phpversion"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadPhpVersions();}
            if(t.id==="errorlogs"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadErrorLogs();}
            if(t.id==="analytics"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadAnalytics();}
        });
        nav.appendChild(btn);

        var pane=document.createElement("div");
        pane.className="bt-tab-pane"+(firstTab?" active":"");
        pane.id="bt-pane-"+t.id;
        panes.appendChild(pane);
        firstTab=false;
    });

    // WordPress is now a top-level page (sibling of #Overview), not inside bt-wrap

    wrap.appendChild(nav);wrap.appendChild(panes);
    if(insertMode==="prepend"&&insertTarget){
        insertTarget.insertBefore(wrap,insertTarget.firstChild);
    }else if(insertTarget&&insertTarget.parentNode){
        insertTarget.parentNode.insertBefore(wrap,insertTarget.nextSibling);
    }else{
        var fallback=document.querySelector(".main-content,.content-padded,.section-body,.container");
        if(fallback) fallback.appendChild(wrap);
    }

    buildOverviewPane();
    if(C.domainEnabled) buildDomainsPane();
    if(C.emailEnabled) buildEmailPane();
    if(C.dbEnabled) buildDatabasesPane();
    if(C.sslEnabled) buildSSLPane();
    if(C.dnsEnabled) buildDnsPane();
    if(C.cronEnabled) buildCronPane();
    if(C.phpEnabled) buildPhpPane();
    if(C.logsEnabled) buildLogsPane();
    if(C.analyticsEnabled) buildAnalyticsPane();
    // WordPress pane is built lazily when sidebar item is clicked (top-level page)
}

/* ─── Overview Pane (improved) ─── */
function buildOverviewPane(){
    var pane=$("bt-pane-overview");if(!pane) return;
    var pairs=[];
    var billingEl=$("billingInfo")||$("tabOverview");
    // Lagom2: billing info is in the billingInfo tab-pane with .col-md-6.col-lg-3 > .row > .col-12 > .gray-base (label) + sibling .col-12 (value)
    if(billingEl){
        // Strategy 1: Lagom2 layout — .col-md-6.col-lg-3 > .row > .col-12 pairs
        billingEl.querySelectorAll(".col-md-6.col-lg-3,.col-lg-3").forEach(function(col){
            var row=col.querySelector(".row");if(!row) return;
            var cols=row.querySelectorAll(".col-12,.col-xs-12");
            if(cols.length>=2){
                var labelEl=cols[0].querySelector(".gray-base,span");
                var label=labelEl?(labelEl.textContent||"").trim():(cols[0].textContent||"").trim();
                label=label.replace(/:$/,"");
                var val=cols[1].innerHTML.trim();
                if(label&&val) pairs.push({label:label,value:val});
            }
        });
        // Strategy 2: Old WHMCS layout — .col-sm-6.col-md-3 with .text-faded
        if(!pairs.length){
            billingEl.querySelectorAll(".col-sm-6.col-md-3.m-b-2x,.col-sm-6.col-md-3").forEach(function(col){
                var lbl=col.querySelector(".text-faded.text-small,.text-faded");if(!lbl) return;
                var label=lbl.textContent.trim().replace(/:$/,"");
                var sib=lbl.nextElementSibling;var val=sib?sib.innerHTML.trim():"";
                if(!val){var c=col.cloneNode(true);var l2=c.querySelector(".text-faded");if(l2)l2.remove();val=c.innerHTML.trim();}
                if(label&&val) pairs.push({label:label,value:val});
            });
        }
        // Strategy 3: h4-based layout
        if(!pairs.length){var rc=billingEl.querySelector(".col-md-6.text-center");
            if(rc){rc.querySelectorAll("h4").forEach(function(h4){
                var label=h4.textContent.trim().replace(/:$/,"");var val="";var s=h4.nextSibling;
                while(s&&!(s.nodeType===1&&s.tagName==="H4")){if(s.nodeType===3)val+=s.textContent.trim();else if(s.nodeType===1)val+=s.outerHTML;s=s.nextSibling;}
                val=val.trim();if(label&&val)pairs.push({label:label,value:val});
            });}}
        // Strategy 4: row-based layout
        if(!pairs.length){billingEl.querySelectorAll(".row").forEach(function(r){
            var l=r.querySelector(".col-sm-5,.col-md-5");var v=r.querySelector(".col-sm-7,.col-md-7");
            if(l&&v)pairs.push({label:l.textContent.trim().replace(/:$/,""),value:v.innerHTML.trim()});
        });}
        billingEl.style.display="none";
    }
    // Lagom2: Also try to get product info from .product-info .list-info (status, reg date, billing cycle, etc.)
    if(!pairs.length){
        var productInfo=document.querySelector(".product-info .list-info");
        if(productInfo){
            productInfo.querySelectorAll("li").forEach(function(li){
                var titleEl=li.querySelector(".list-info-title");
                var textEl=li.querySelector(".list-info-text");
                if(titleEl&&textEl){
                    var label=(titleEl.textContent||"").trim().replace(/:$/,"");
                    var val=textEl.innerHTML.trim();
                    if(label&&val) pairs.push({label:label,value:val});
                }
            });
            // Hide the product-info section since we're showing it in our overview
            var productInfoSection=productInfo.closest(".product-info");
            if(productInfoSection){productInfoSection.style.display="none";}
        }
    }

    var html="";
    if(pairs.length){
        html+="<div class=\"bt-ov-grid\">";
        pairs.forEach(function(p){
            var lbl=p.label.toLowerCase();
            var extra="";
            // Detect due date / next due date fields and add color + days remaining
            if(lbl.indexOf("due")!==-1||lbl.indexOf("renewal")!==-1||lbl.indexOf("expir")!==-1){
                var dateMatch=p.value.match(/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/);
                if(!dateMatch) dateMatch=p.value.match(/(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/);
                if(dateMatch){
                    var dueDate;
                    if(dateMatch[3]&&dateMatch[3].length===4) dueDate=new Date(parseInt(dateMatch[3]),parseInt(dateMatch[1])-1,parseInt(dateMatch[2]));
                    else dueDate=new Date(parseInt(dateMatch[1]),parseInt(dateMatch[2])-1,parseInt(dateMatch[3]));
                    var now=new Date();now.setHours(0,0,0,0);dueDate.setHours(0,0,0,0);
                    var diff=Math.ceil((dueDate-now)/(1000*60*60*24));
                    var cls="bt-ov-due-ok";
                    if(diff<0){cls="bt-ov-due-past";extra="<span class=\"bt-ov-days "+cls+"\">Overdue by "+Math.abs(diff)+" day"+(Math.abs(diff)!==1?"s":"")+"</span>";}
                    else if(diff===0){cls="bt-ov-due-danger";extra="<span class=\"bt-ov-days "+cls+"\">Due today</span>";}
                    else if(diff<=7){cls="bt-ov-due-danger";extra="<span class=\"bt-ov-days "+cls+"\">"+diff+" day"+(diff!==1?"s":"")+" left</span>";}
                    else if(diff<=30){cls="bt-ov-due-warn";extra="<span class=\"bt-ov-days "+cls+"\">"+diff+" days left</span>";}
                    else{extra="<span class=\"bt-ov-days "+cls+"\">"+diff+" days left</span>";}
                    p.value="<span class=\""+cls+"\">"+p.value+"</span>";
                }
            }
            html+="<div class=\"bt-ov-card\"><div class=\"bt-ov-label\">"+esc(p.label)+"</div><div class=\"bt-ov-value\">"+p.value+extra+"</div></div>";
        });
        html+="</div>";
    }

    // Nameservers (accordion, closed by default)
    if(C.nsEnabled&&C.ns&&C.ns.ns&&C.ns.ns.length){
        html+="<div class=\"bt-accordion\" id=\"btAccNs\"><div class=\"bt-accordion-head\" onclick=\"this.parentElement.classList.toggle(\\x27open\\x27)\"><div class=\"bt-accordion-icon\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"2\" y1=\"12\" x2=\"22\" y2=\"12\"/><path d=\"M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z\"/></svg></div><div class=\"bt-accordion-info\"><h5>Nameservers</h5><p>Point your domain to these nameservers</p></div><svg class=\"bt-accordion-arrow\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"6 9 12 15 18 9\"/></svg></div><div class=\"bt-accordion-body\"><div class=\"bt-list\" style=\"padding:4px 10px 10px\">";
        C.ns.ns.forEach(function(ns,i){
            html+="<div class=\"bt-row\"><div class=\"bt-row-icon ns\">NS"+(i+1)+"</div><div class=\"bt-row-info\"><span class=\"bt-row-name mono\">"+esc(ns)+"</span></div><button type=\"button\" class=\"bt-copy\" data-copy=\""+esc(ns)+"\"><svg width=\"15\" height=\"15\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><rect x=\"9\" y=\"9\" width=\"13\" height=\"13\" rx=\"2\" ry=\"2\"/><path d=\"M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1\"/></svg></button></div>";
        });
        if(C.ns.ip){
            html+="<div class=\"bt-row\"><div class=\"bt-row-icon ip\">IP</div><div class=\"bt-row-info\"><span class=\"bt-row-name mono\">"+esc(C.ns.ip)+"</span></div><button type=\"button\" class=\"bt-copy\" data-copy=\""+esc(C.ns.ip)+"\"><svg width=\"15\" height=\"15\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><rect x=\"9\" y=\"9\" width=\"13\" height=\"13\" rx=\"2\" ry=\"2\"/><path d=\"M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1\"/></svg></button></div>";
        }
        html+="</div></div></div>";
    }

    // Addons/Upgrades — parse from hidden WHMCS panels into clean UI
    var addonItems=[];var upgradeItems=[];
    // Parse the Addons & Extras select options
    var extrasPanel=$("cPanelExtrasPurchasePanel");
    if(extrasPanel){
        var form=extrasPanel.querySelector("form");
        var tokenInput=form?form.querySelector("input[name=token]"):null;
        var svcInput=form?form.querySelector("input[name=serviceid]"):null;
        var token=tokenInput?tokenInput.value:"";
        var svcId=svcInput?svcInput.value:"";
        extrasPanel.querySelectorAll("select[name=aid] option").forEach(function(opt){
            var name=opt.textContent.trim();var aid=opt.value;
            if(!name||!aid) return;
            // Categorize: backup/ram/resource = upgrade, rest = addon
            var nl=name.toLowerCase();
            if(nl.indexOf("backup")!==-1||nl.indexOf("ram")!==-1||nl.indexOf("cpu")!==-1||nl.indexOf("disk")!==-1||nl.indexOf("bandwidth")!==-1||nl.indexOf("upgrade")!==-1||nl.indexOf("resource")!==-1){
                upgradeItems.push({name:name,aid:aid,token:token,svcId:svcId});
            }else{
                addonItems.push({name:name,aid:aid,token:token,svcId:svcId});
            }
        });
    }
    // Also check tabAddons for configurable options / upgrade links (not email forms)
    var addonsEl=$("tabAddons");
    if(addonsEl){
        addonsEl.querySelectorAll("a[href*=upgrade],a[href*=configoptions],.btn[href*=upgrade]").forEach(function(a){
            var txt=a.textContent.trim();var href=a.getAttribute("href")||"";
            if(txt&&href) upgradeItems.push({name:txt,link:href});
        });
        addonsEl.style.display="none";
    }
    // Render combined addons & upgrades carousel
    var allItems=[];
    addonItems.forEach(function(a){allItems.push({name:a.name,aid:a.aid,token:a.token,svcId:a.svcId,type:"addon"});});
    upgradeItems.forEach(function(u){allItems.push({name:u.name,aid:u.aid||"",token:u.token||"",svcId:u.svcId||"",link:u.link||"",type:"upgrade"});});
    if(allItems.length){
        var perPage=window.innerWidth<=600?4:4;
        var pages=[];for(var pi=0;pi<allItems.length;pi+=perPage){pages.push(allItems.slice(pi,pi+perPage));}
        html+="<div class=\"bt-accordion\" id=\"btAccAddons\"><div class=\"bt-accordion-head\" onclick=\"this.parentElement.classList.toggle(\\x27open\\x27)\"><div class=\"bt-accordion-icon\" style=\"background:rgba(124,58,237,1)\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z\"/></svg></div><div class=\"bt-accordion-info\"><h5>Addons &amp; Upgrades</h5><p>"+allItems.length+" available · Enhance your hosting</p></div><svg class=\"bt-accordion-arrow\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"6 9 12 15 18 9\"/></svg></div><div class=\"bt-accordion-body\"><div class=\"bt-addon-wrap\"><button type=\"button\" class=\"bt-addon-nav prev"+(pages.length<=1?" hidden":"")+"\" id=\"btAddonPrev\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"15 18 9 12 15 6\"/></svg></button><button type=\"button\" class=\"bt-addon-nav next"+(pages.length<=1?" hidden":"")+"\" id=\"btAddonNext\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"9 18 15 12 9 6\"/></svg></button><div class=\"bt-addon-scroll\" id=\"btAddonScroll\">";
        pages.forEach(function(page){
            html+="<div class=\"bt-addon-page\">";
            page.forEach(function(item){
                var icon=item.type==="upgrade"?btUpgradeIcon(item.name):btAddonIcon(item.name);
                var iconCls=item.type==="upgrade"?"upgrade":"addon";
                var btnHtml="";
                if(item.link){
                    btnHtml="<a href=\""+esc(item.link)+"\" class=\"bt-addon-btn\">Get</a>";
                }else{
                    btnHtml="<form method=\"post\" action=\"cart.php?a=add\" style=\"margin:0\"><input type=\"hidden\" name=\"token\" value=\""+esc(item.token)+"\"><input type=\"hidden\" name=\"serviceid\" value=\""+esc(item.svcId)+"\"><input type=\"hidden\" name=\"aid\" value=\""+esc(item.aid)+"\"><button type=\"submit\" class=\"bt-addon-btn\">Get</button></form>";
                }
                html+="<div class=\"bt-addon-item\"><div class=\"bt-addon-icon "+iconCls+"\">"+icon+"</div><div class=\"bt-addon-text\"><span class=\"bt-addon-name\" title=\""+esc(item.name)+"\">"+esc(item.name)+"</span><span class=\"bt-addon-price\" data-aid=\""+(item.aid||"")+"\"></span></div><div class=\"bt-addon-tip-wrap\"><button type=\"button\" class=\"bt-addon-tip-btn\" data-aid=\""+(item.aid||"")+"\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><path d=\"M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3\"/><line x1=\"12\" y1=\"17\" x2=\"12.01\" y2=\"17\"/></svg></button><div class=\"bt-addon-tooltip\">Loading...</div></div>"+btnHtml+"</div>";
            });
            html+="</div>";
        });
        html+="</div>";
        if(pages.length>1){
            html+="<div class=\"bt-addon-dots\" id=\"btAddonDots\">";
            for(var di=0;di<pages.length;di++) html+="<button type=\"button\" class=\"bt-addon-dot"+(di===0?" active":"")+"\" data-page=\""+di+"\"></button>";
            html+="</div>";
        }
        html+="</div></div></div>";
    }

    pane.innerHTML=html;
    pane.querySelectorAll(".bt-copy").forEach(function(b){b.addEventListener("click",function(){doCopy(this.getAttribute("data-copy"),this);});});
    // Carousel nav for addons
    var scroller=$("btAddonScroll");
    if(scroller){
        var curPage=0;var totalPages=scroller.querySelectorAll(".bt-addon-page").length;
        function goToPage(p){
            if(p<0||p>=totalPages) return;
            curPage=p;
            scroller.children[p].scrollIntoView({behavior:"smooth",block:"nearest",inline:"start"});
            var dots=$("btAddonDots");
            if(dots) dots.querySelectorAll(".bt-addon-dot").forEach(function(d,i){d.classList.toggle("active",i===p);});
            var prev=$("btAddonPrev");var next=$("btAddonNext");
            if(prev) prev.classList.toggle("hidden",p===0);
            if(next) next.classList.toggle("hidden",p===totalPages-1);
        }
        var prev=$("btAddonPrev");if(prev) prev.addEventListener("click",function(){goToPage(curPage-1);});
        var next=$("btAddonNext");if(next) next.addEventListener("click",function(){goToPage(curPage+1);});
        var dots=$("btAddonDots");
        if(dots) dots.querySelectorAll(".bt-addon-dot").forEach(function(d){d.addEventListener("click",function(){goToPage(parseInt(this.getAttribute("data-page")));});});
        // Drag support
        var dragStartX=0;var dragScrollLeft=0;var isDragging=false;
        function onDragStart(e){
            isDragging=true;scroller.classList.add("dragging");
            dragStartX=(e.touches?e.touches[0].pageX:e.pageX)-scroller.offsetLeft;
            dragScrollLeft=scroller.scrollLeft;
        }
        function onDragMove(e){
            if(!isDragging) return;
            var x=(e.touches?e.touches[0].pageX:e.pageX)-scroller.offsetLeft;
            scroller.scrollLeft=dragScrollLeft-(x-dragStartX);
        }
        function onDragEnd(){
            if(!isDragging) return;
            isDragging=false;scroller.classList.remove("dragging");
            // Snap to nearest page
            var w=scroller.offsetWidth;var nearest=Math.round(scroller.scrollLeft/w);
            goToPage(Math.max(0,Math.min(nearest,totalPages-1)));
        }
        scroller.addEventListener("mousedown",onDragStart);
        scroller.addEventListener("mousemove",onDragMove);
        scroller.addEventListener("mouseup",onDragEnd);
        scroller.addEventListener("mouseleave",onDragEnd);
        scroller.addEventListener("touchstart",onDragStart,{passive:true});
        scroller.addEventListener("touchmove",onDragMove,{passive:true});
        scroller.addEventListener("touchend",onDragEnd);
        // Tooltip + pricing: fetch on hover/click
        var tipCache={};
        var activeTip=null;
        var tipHideTimer=null;
        function showTip(anchor,tip){
            if(tipHideTimer){clearTimeout(tipHideTimer);tipHideTimer=null;}
            if(activeTip&&activeTip!==tip) activeTip.classList.remove("visible");
            activeTip=tip;
            // Move to body if not already there
            if(tip.parentNode!==document.body) document.body.appendChild(tip);
            tip.classList.add("visible");
            // Position: always above the anchor, fallback below if no room
            requestAnimationFrame(function(){
                var r=anchor.getBoundingClientRect();
                var tw=tip.offsetWidth||340;var th=tip.offsetHeight||60;
                var left=r.left+r.width/2-tw/2;
                if(left<8) left=8;
                if(left+tw>window.innerWidth-8) left=window.innerWidth-tw-8;
                var top=r.top-th-8;
                if(top<8) top=r.bottom+8;
                tip.style.left=left+"px";tip.style.top=top+"px";
            });
        }
        function hideTip(tip){
            tipHideTimer=setTimeout(function(){
                if(tip) tip.classList.remove("visible");
                if(activeTip===tip) activeTip=null;
                tipHideTimer=null;
            },120);
        }
        pane.querySelectorAll(".bt-addon-tip-wrap").forEach(function(wrap){
            var btn=wrap.querySelector(".bt-addon-tip-btn");
            var tip=wrap.querySelector(".bt-addon-tooltip");
            var aid=btn?btn.getAttribute("data-aid"):"";
            function loadTip(){
                if(!aid||!tip) return;
                if(tipCache[aid]){tip.textContent=tipCache[aid];return;}
                btn.classList.add("loading");
                post({action:"get_addon_description",addon_id:aid},function(r){
                    btn.classList.remove("loading");
                    var desc=(r.success&&r.description)?r.description:"No description available";
                    tipCache[aid]=desc;tip.textContent=desc;
                    if(r.price){
                        pane.querySelectorAll(".bt-addon-price[data-aid=\\x22"+aid+"\\x22]").forEach(function(pe){pe.textContent=r.price;pe.classList.add("visible");});
                    }
                });
            }
            wrap.addEventListener("mouseenter",function(){loadTip();showTip(btn,tip);});
            wrap.addEventListener("mouseleave",function(){hideTip(tip);});
            if(tip){tip.addEventListener("mouseenter",function(){if(tipHideTimer){clearTimeout(tipHideTimer);tipHideTimer=null;}});tip.addEventListener("mouseleave",function(){hideTip(tip);});}
            btn.addEventListener("click",function(e){e.stopPropagation();loadTip();if(tip.classList.contains("visible")){tip.classList.remove("visible");activeTip=null;}else{showTip(btn,tip);}});
        });
        document.addEventListener("click",function(e){if(activeTip&&!e.target.closest(".bt-addon-tip-wrap")&&!e.target.closest(".bt-addon-tooltip")){if(tipHideTimer){clearTimeout(tipHideTimer);tipHideTimer=null;}activeTip.classList.remove("visible");activeTip=null;}});
        // Prefetch pricing for visible page
        var firstPageItems=pane.querySelectorAll(".bt-addon-page:first-child .bt-addon-tip-btn[data-aid]");
        firstPageItems.forEach(function(btn){
            var aid=btn.getAttribute("data-aid");if(!aid||tipCache[aid]) return;
            post({action:"get_addon_description",addon_id:aid},function(r){
                if(r.success){
                    tipCache[aid]=r.description||"No description available";
                    if(r.price){
                        pane.querySelectorAll(".bt-addon-price[data-aid=\\x22"+aid+"\\x22]").forEach(function(pe){pe.textContent=r.price;pe.classList.add("visible");});
                    }
                }
            });
        });
    }
}

/* ─── Domains Pane ─── */
function buildDomainsPane(){
    var pane=$("bt-pane-domains");if(!pane||!C.domains) return;
    var d=C.domains;var total=1+(d.addon?d.addon.length:0)+(d.sub?d.sub.length:0)+(d.parked?d.parked.length:0);
    var html="<div class=\"bt-card\"><div class=\"bt-card-head\"><div class=\"bt-card-head-left\"><div class=\"bt-icon-circle\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"2\" y1=\"12\" x2=\"22\" y2=\"12\"/><path d=\"M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z\"/></svg></div><div><h5>Domains</h5><p class=\"bt-dom-count\">"+total+" domain"+(total!==1?"s":"")+"</p></div></div><div class=\"bt-card-head-right\"><button type=\"button\" class=\"bt-btn-add\" id=\"bdmAddAddonBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><line x1=\"12\" y1=\"5\" x2=\"12\" y2=\"19\"/><line x1=\"5\" y1=\"12\" x2=\"19\" y2=\"12\"/></svg> Add Domain</button><button type=\"button\" class=\"bt-btn-outline\" id=\"bdmAddSubBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"16 3 21 3 21 8\"/><line x1=\"4\" y1=\"20\" x2=\"21\" y2=\"3\"/></svg> Add Subdomain</button></div></div><div class=\"bt-list\" id=\"bt-dom-list\">";
    if(d.main) html+=domRow(d.main,"main","Primary",false);
    if(d.addon) d.addon.forEach(function(dm){html+=domRow(dm,"addon","Addon",true);});
    if(d.sub) d.sub.forEach(function(dm){html+=domRow(dm,"sub","Subdomain",true);});
    if(d.parked) d.parked.forEach(function(dm){html+=domRow(dm,"parked","Alias",true);});
    html+="</div></div>";
    pane.innerHTML=html;
    bindDomainActions(pane);
    $("bdmAddAddonBtn").addEventListener("click",openAddonModal);
    $("bdmAddSubBtn").addEventListener("click",openSubModal);
}
function domRow(name,type,badge,canDel){
    var e=esc(name);var badgeClass=type==="main"?"bt-badge-primary":type==="addon"?"bt-badge-green":type==="sub"?"bt-badge-purple":"bt-badge-amber";
    return "<div class=\"bt-row\" data-domain=\""+e+"\" data-type=\""+type+"\"><div class=\"bt-row-icon "+type+"\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"2\" y1=\"12\" x2=\"22\" y2=\"12\"/></svg></div><div class=\"bt-row-info\"><span class=\"bt-row-name\">"+e+"</span><span class=\"bt-row-badge "+badgeClass+"\">"+badge+"</span></div><div class=\"bt-row-actions\"><a href=\"https://"+e+"\" target=\"_blank\" class=\"bt-row-btn visit\"><svg width=\"13\" height=\"13\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6\"/><polyline points=\"15 3 21 3 21 9\"/><line x1=\"10\" y1=\"14\" x2=\"21\" y2=\"3\"/></svg><span>Visit</span></a>"+(canDel?"<button type=\"button\" class=\"bt-row-btn del\" data-domain=\""+e+"\" data-type=\""+type+"\"><svg width=\"13\" height=\"13\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"3 6 5 6 21 6\"/><path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\"/></svg><span>Delete</span></button>":"")+"</div></div>";
}

/* ─── Email Pane ─── */
function buildEmailPane(){
    var pane=$("bt-pane-email");if(!pane) return;
    var emails=C.emails||[];var count=emails.length;
    var html="<div class=\"bt-card\"><div class=\"bt-card-head\"><div class=\"bt-card-head-left\"><div class=\"bt-icon-circle\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><rect x=\"2\" y=\"4\" width=\"20\" height=\"16\" rx=\"2\"/><path d=\"m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7\"/></svg></div><div><h5>Email Accounts</h5><p class=\"bt-email-count\">"+(count===1?"1 account":count+" accounts")+"</p></div></div><div class=\"bt-card-head-right\"><button type=\"button\" class=\"bt-btn-add\" id=\"bemCreateBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><line x1=\"12\" y1=\"5\" x2=\"12\" y2=\"19\"/><line x1=\"5\" y1=\"12\" x2=\"19\" y2=\"12\"/></svg> Create Email</button></div></div><div class=\"bt-list\" id=\"bt-email-list\">";
    if(!count) html+="<div class=\"bt-empty\"><svg width=\"32\" height=\"32\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.5\"><rect x=\"2\" y=\"4\" width=\"20\" height=\"16\" rx=\"2\"/><path d=\"m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7\"/></svg><span>No email accounts found</span></div>";
    else emails.forEach(function(em){html+=emailRow(em);});
    html+="</div></div>";
    pane.innerHTML=html;bindEmailActions(pane);
    $("bemCreateBtn").addEventListener("click",openCreateEmailModal);
}
function emailRow(email){
    var e=esc(email);var ini=email.charAt(0).toUpperCase();
    return "<div class=\"bt-row\" data-email=\""+e+"\"><div class=\"bt-row-icon email\">"+ini+"</div><div class=\"bt-row-info\"><span class=\"bt-row-name\">"+e+"</span></div><div class=\"bt-row-actions\"><button type=\"button\" class=\"bt-row-btn login\" data-email=\""+e+"\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4\"/><polyline points=\"10 17 15 12 10 7\"/><line x1=\"15\" y1=\"12\" x2=\"3\" y2=\"12\"/></svg><span>Login</span></button><button type=\"button\" class=\"bt-row-btn pass\" data-email=\""+e+"\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><rect x=\"3\" y=\"11\" width=\"18\" height=\"11\" rx=\"2\" ry=\"2\"/><path d=\"M7 11V7a5 5 0 0 1 10 0v4\"/></svg><span>Password</span></button><button type=\"button\" class=\"bt-row-btn del\" data-email=\""+e+"\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"3 6 5 6 21 6\"/><path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\"/></svg><span>Delete</span></button></div></div>";
}

/* ─── Databases Pane ─── */
function buildDatabasesPane(){
    var pane=$("bt-pane-databases");if(!pane) return;
    pane.innerHTML="<div class=\"bt-card\"><div class=\"bt-card-head\"><div class=\"bt-card-head-left\"><div class=\"bt-icon-circle\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><ellipse cx=\"12\" cy=\"5\" rx=\"9\" ry=\"3\"/><path d=\"M21 12c0 1.66-4 3-9 3s-9-1.34-9-3\"/><path d=\"M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5\"/></svg></div><div><h5>Databases</h5><p class=\"bt-db-count\">Loading...</p></div></div><div class=\"bt-card-head-right\"><button type=\"button\" class=\"bt-btn-add\" id=\"bdbCreateBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><line x1=\"12\" y1=\"5\" x2=\"12\" y2=\"19\"/><line x1=\"5\" y1=\"12\" x2=\"19\" y2=\"12\"/></svg> New Database</button><button type=\"button\" class=\"bt-btn-outline\" id=\"bdbUserBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2\"/><circle cx=\"12\" cy=\"7\" r=\"4\"/></svg> New User</button><button type=\"button\" class=\"bt-btn-outline\" id=\"bdbAssignBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2\"/><circle cx=\"8.5\" cy=\"7\" r=\"4\"/><line x1=\"20\" y1=\"8\" x2=\"20\" y2=\"14\"/><line x1=\"23\" y1=\"11\" x2=\"17\" y2=\"11\"/></svg> Assign</button><a class=\"bt-btn-outline\" id=\"bdbPmaBtn\" href=\"#\" target=\"_blank\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6\"/><polyline points=\"15 3 21 3 21 9\"/><line x1=\"10\" y1=\"14\" x2=\"21\" y2=\"3\"/></svg> phpMyAdmin</a></div></div><div class=\"bt-list\" id=\"bt-db-list\"><div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading databases...</span></div></div></div>";
    $("bdbCreateBtn").addEventListener("click",function(){$("bdbCreateModal").style.display="flex";$("bdbNewName").value="";$("bdbCreateMsg").style.display="none";});
    $("bdbUserBtn").addEventListener("click",function(){$("bdbUserModal").style.display="flex";$("bdbNewUser").value="";$("bdbUserPass").value="";$("bdbUserMsg").style.display="none";});
    $("bdbAssignBtn").addEventListener("click",openAssignModal);
    post({action:"get_phpmyadmin_url"},function(r){if(r.success&&r.url) $("bdbPmaBtn").href=r.url;});
    $("bdbCreateSubmit").addEventListener("click",submitCreateDb);
    $("bdbUserSubmit").addEventListener("click",submitCreateDbUser);
    $("bdbAssignSubmit").addEventListener("click",submitAssignDb);
}

function loadDatabases(){
    var list=$("bt-db-list");if(!list) return;
    list.innerHTML="<div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading databases...</span></div>";
    post({action:"list_databases"},function(r){
        if(!r.success){list.innerHTML="<div class=\"bt-empty\"><span>"+(r.message||"Failed to load")+"</span></div>";return;}
        var dbs=r.databases||[];var users=r.users||[];var mappings=r.mappings||[];
        var countEl=document.querySelector(".bt-db-count");
        if(countEl) countEl.textContent=dbs.length+" database"+(dbs.length!==1?"s":"")+", "+users.length+" user"+(users.length!==1?"s":"");
        if(r.prefix){var pe=$("bdbPrefix");if(pe)pe.textContent=r.prefix;var upe=$("bdbUserPrefix");if(upe)upe.textContent=r.prefix;}
        var html="";
        if(!dbs.length&&!users.length){
            html="<div class=\"bt-empty\"><svg width=\"32\" height=\"32\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.5\"><ellipse cx=\"12\" cy=\"5\" rx=\"9\" ry=\"3\"/><path d=\"M21 12c0 1.66-4 3-9 3s-9-1.34-9-3\"/><path d=\"M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5\"/></svg><span>No databases found</span></div>";
        }else{
            dbs.forEach(function(db){
                var dbUsers=[];
                mappings.forEach(function(m){if(m.db===db&&m.user)dbUsers.push(m.user);});
                var userBadges=dbUsers.length?dbUsers.map(function(u){return "<span class=\"bt-row-badge bt-badge-purple\">"+esc(u)+"</span>";}).join(""):"<span class=\"bt-row-badge bt-badge-amber\">No users</span>";
                html+="<div class=\"bt-row\" data-db=\""+esc(db)+"\"><div class=\"bt-row-icon db\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><ellipse cx=\"12\" cy=\"5\" rx=\"9\" ry=\"3\"/><path d=\"M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5\"/></svg></div><div class=\"bt-row-info\" style=\"flex-wrap:wrap\"><span class=\"bt-row-name mono\">"+esc(db)+"</span>"+userBadges+"</div><div class=\"bt-row-actions\"><button type=\"button\" class=\"bt-row-btn del\" data-db=\""+esc(db)+"\"><svg width=\"13\" height=\"13\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"3 6 5 6 21 6\"/><path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\"/></svg><span>Delete</span></button></div></div>";
            });
            if(users.length){
                html+="<div style=\"padding:12px 14px 4px;font-size:12px;font-weight:700;color:var(--text-muted,#9ca3af);text-transform:uppercase;letter-spacing:.5px\">Database Users</div>";
                users.forEach(function(u){
                    html+="<div class=\"bt-row\" data-dbuser=\""+esc(u)+"\"><div class=\"bt-row-icon dbuser\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2\"/><circle cx=\"12\" cy=\"7\" r=\"4\"/></svg></div><div class=\"bt-row-info\"><span class=\"bt-row-name mono\">"+esc(u)+"</span><span class=\"bt-row-badge bt-badge-purple\">User</span></div><div class=\"bt-row-actions\"><button type=\"button\" class=\"bt-row-btn del\" data-dbuser=\""+esc(u)+"\"><svg width=\"13\" height=\"13\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"3 6 5 6 21 6\"/><path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\"/></svg><span>Delete</span></button></div></div>";
                });
            }
        }
        list.innerHTML=html;
        list.querySelectorAll(".bt-row-btn.del[data-db]").forEach(function(b){b.addEventListener("click",function(){
            if(confirm("Delete database "+this.getAttribute("data-db")+"?")){var btn=this;btn.disabled=true;post({action:"delete_database",database:this.getAttribute("data-db")},function(r){btn.disabled=false;if(r.success)loadDatabases();else alert(r.message||"Failed");});}
        });});
        list.querySelectorAll(".bt-row-btn.del[data-dbuser]").forEach(function(b){b.addEventListener("click",function(){
            if(confirm("Delete user "+this.getAttribute("data-dbuser")+"?")){var btn=this;btn.disabled=true;post({action:"delete_db_user",dbuser:this.getAttribute("data-dbuser")},function(r){btn.disabled=false;if(r.success)loadDatabases();else alert(r.message||"Failed");});}
        });});
        updateAssignSelects(dbs,users);
    });
}

function submitCreateDb(){
    var name=$("bdbNewName").value.trim();var msg=$("bdbCreateMsg");msg.style.display="none";
    if(!name){showMsg(msg,"Please enter a database name",false);return;}
    $("bdbCreateSubmit").disabled=true;
    post({action:"create_database",dbname:name},function(r){
        $("bdbCreateSubmit").disabled=false;
        showMsg(msg,r.message||"Done",r.success);
        if(r.success){setTimeout(function(){$("bdbCreateModal").style.display="none";loadDatabases();},800);}
    });
}

function submitCreateDbUser(){
    var name=$("bdbNewUser").value.trim();var pass=$("bdbUserPass").value;var msg=$("bdbUserMsg");msg.style.display="none";
    if(!name||!pass){showMsg(msg,"Please fill in all fields",false);return;}
    $("bdbUserSubmit").disabled=true;
    post({action:"create_db_user",dbuser:name,dbpass:pass},function(r){
        $("bdbUserSubmit").disabled=false;
        showMsg(msg,r.message||"Done",r.success);
        if(r.success){setTimeout(function(){$("bdbUserModal").style.display="none";loadDatabases();},800);}
    });
}

function openAssignModal(){
    $("bdbAssignModal").style.display="flex";$("bdbAssignMsg").style.display="none";
}
function updateAssignSelects(dbs,users){
    var dbSel=$("bdbAssignDb");var uSel=$("bdbAssignUser");
    if(!dbSel||!uSel) return;
    dbSel.innerHTML="";uSel.innerHTML="";
    dbs.forEach(function(d){var o=document.createElement("option");o.value=d;o.textContent=d;dbSel.appendChild(o);});
    users.forEach(function(u){var o=document.createElement("option");o.value=u;o.textContent=u;uSel.appendChild(o);});
}
function submitAssignDb(){
    var db=$("bdbAssignDb").value;var user=$("bdbAssignUser").value;var msg=$("bdbAssignMsg");msg.style.display="none";
    var priv=$("bdbAssignAll").checked?"ALL PRIVILEGES":"SELECT,INSERT,UPDATE,DELETE";
    if(!db||!user){showMsg(msg,"Select a database and user",false);return;}
    $("bdbAssignSubmit").disabled=true;
    post({action:"assign_db_user",database:db,dbuser:user,privileges:priv},function(r){
        $("bdbAssignSubmit").disabled=false;
        showMsg(msg,r.message||"Done",r.success);
        if(r.success){setTimeout(function(){$("bdbAssignModal").style.display="none";loadDatabases();},800);}
    });
}

/* ─── SSL Pane ─── */
function buildSSLPane(){
    var pane=$("bt-pane-ssl");if(!pane) return;
    pane.innerHTML="<div class=\"bt-card\"><div class=\"bt-card-head\"><div class=\"bt-card-head-left\"><div class=\"bt-icon-circle\" style=\"background:#059669\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z\"/></svg></div><div><h5>SSL Certificates</h5><p class=\"bt-ssl-count\">Loading...</p></div></div><div class=\"bt-card-head-right\"><button type=\"button\" class=\"bt-btn-add\" id=\"btSslRunAutossl\" style=\"background:#059669\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z\"/></svg> Run AutoSSL</button></div></div><div class=\"bt-list\" id=\"bt-ssl-list\"><div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading SSL status...</span></div></div></div>";
    $("btSslRunAutossl").addEventListener("click",function(){startAutoSSL(this);});
}

function loadSSLStatus(){
    var list=$("bt-ssl-list");if(!list) return;
    list.innerHTML="<div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading SSL status...</span></div>";
    post({action:"ssl_status"},function(r){
        if(!r.success){list.innerHTML="<div class=\"bt-empty\"><span>"+(r.message||"Failed to load SSL status")+"</span></div>";return;}
        var certs=r.certificates||[];
        var countEl=document.querySelector(".bt-ssl-count");
        if(countEl) countEl.textContent=certs.length+" domain"+(certs.length!==1?"s":"");
        if(!certs.length){list.innerHTML="<div class=\"bt-empty\"><svg width=\"32\" height=\"32\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.5\"><path d=\"M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z\"/></svg><span>No SSL data found</span></div>";return;}
        var html="";
        certs.forEach(function(c){
            var statusCls="bt-ssl-valid";var statusTxt="Valid";var statusIcon="check";var badgeCls="bt-badge-green";
            var issuer=c.issuer||"Unknown";
            var isAutoSSL=issuer.toLowerCase().indexOf("cpanel")!==-1||issuer.toLowerCase().indexOf("autossl")!==-1||issuer.toLowerCase().indexOf("comodo")!==-1||issuer.toLowerCase().indexOf("sectigo")!==-1||issuer.toLowerCase().indexOf("let\\x27s encrypt")!==-1||issuer.toLowerCase().indexOf("letsencrypt")!==-1;
            var isSelfSigned=c.is_self_signed||issuer.toLowerCase().indexOf("self-signed")!==-1||issuer.toLowerCase().indexOf("cpanel")!==-1&&c.type==="self-signed"||c.self_signed;
            if(isSelfSigned){statusCls="bt-ssl-selfsigned";statusTxt="Self-Signed";statusIcon="warning";badgeCls="bt-badge-amber";}
            var daysLeft=null;
            if(c.expiry_epoch){
                var now=Math.floor(Date.now()/1000);
                daysLeft=Math.floor((c.expiry_epoch-now)/86400);
                if(daysLeft<0){statusCls="bt-ssl-expired";statusTxt="Expired";statusIcon="expired";badgeCls="bt-badge-red";}
                else if(daysLeft<=7&&!isSelfSigned){statusCls="bt-ssl-expiring";statusTxt="Expiring Soon";statusIcon="warning";badgeCls="bt-badge-amber";}
            }
            if(!c.has_cert){statusCls="bt-ssl-none";statusTxt="No SSL";statusIcon="none";badgeCls="bt-badge-red";}
            var iconSvg="";
            if(statusIcon==="check") iconSvg="<svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M22 11.08V12a10 10 0 1 1-5.93-9.14\"/><polyline points=\"22 4 12 14.01 9 11.01\"/></svg>";
            else if(statusIcon==="warning") iconSvg="<svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z\"/><line x1=\"12\" y1=\"9\" x2=\"12\" y2=\"13\"/><line x1=\"12\" y1=\"17\" x2=\"12.01\" y2=\"17\"/></svg>";
            else if(statusIcon==="expired") iconSvg="<svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"15\" y1=\"9\" x2=\"9\" y2=\"15\"/><line x1=\"9\" y1=\"9\" x2=\"15\" y2=\"15\"/></svg>";
            else iconSvg="<svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"12\" y1=\"8\" x2=\"12\" y2=\"12\"/><line x1=\"12\" y1=\"16\" x2=\"12.01\" y2=\"16\"/></svg>";
            var rowIconCls=statusCls==="bt-ssl-valid"?"ssl-valid":statusCls==="bt-ssl-selfsigned"?"ssl-selfsigned":statusCls==="bt-ssl-expired"||statusCls==="bt-ssl-none"?"ssl-expired":"ssl-expiring";
            html+="<div class=\"bt-row bt-ssl-row\" data-domain=\""+esc(c.domain)+"\"><div class=\"bt-row-icon "+rowIconCls+"\">"+iconSvg+"</div><div class=\"bt-row-info\" style=\"flex-wrap:wrap;gap:4px 8px\"><span class=\"bt-row-name\">"+esc(c.domain)+"</span><span class=\"bt-row-badge "+badgeCls+"\">"+statusTxt+"</span></div><div class=\"bt-ssl-meta\">";
            if(c.has_cert&&!isSelfSigned){
                html+="<span class=\"bt-ssl-issuer\" title=\"Issuer: "+esc(issuer)+"\"><svg width=\"12\" height=\"12\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z\"/></svg> "+esc(issuer)+"</span>";
                if(daysLeft!==null){
                    var daysCls=daysLeft<=7?"bt-ssl-days-danger":daysLeft<=30?"bt-ssl-days-warn":"bt-ssl-days-ok";
                    html+="<span class=\"bt-ssl-expiry "+daysCls+"\"><svg width=\"12\" height=\"12\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><polyline points=\"12 6 12 12 16 14\"/></svg> "+daysLeft+"d left</span>";
                }
            }
            html+="</div><div class=\"bt-row-actions\">";
            if(isSelfSigned||!c.has_cert||statusCls==="bt-ssl-expired"){
                html+="<button type=\"button\" class=\"bt-row-btn bt-ssl-generate\" data-domain=\""+esc(c.domain)+"\" style=\"color:#059669;border-color:#059669\"><svg width=\"13\" height=\"13\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z\"/></svg><span>Generate SSL</span></button>";
            }
            html+="</div></div>";
        });
        list.innerHTML=html;
        // Bind generate buttons
        list.querySelectorAll(".bt-ssl-generate").forEach(function(b){b.addEventListener("click",function(){startAutoSSL($("btSslRunAutossl"));});});
    });
}

function startAutoSSL(btn){
    if(!btn) return;
    var origHtml=btn.innerHTML;
    btn.disabled=true;
    btn.innerHTML="<div class=\"bt-spinner\" style=\"width:14px;height:14px;border-width:2px;display:inline-block;vertical-align:middle\"></div> Running AutoSSL...";
    post({action:"start_autossl"},function(r){
        if(!r.success){btn.disabled=false;btn.innerHTML=origHtml;alert(r.message||"Failed to start AutoSSL");return;}
        // Poll for progress
        pollAutoSSL(btn,origHtml);
    });
}

function pollAutoSSL(btn,origHtml){
    var pollCount=0;var maxPolls=60;
    function doPoll(){
        pollCount++;
        if(pollCount>maxPolls){btn.disabled=false;btn.innerHTML=origHtml;loadSSLStatus();return;}
        post({action:"autossl_progress"},function(r){
            if(r.in_progress){
                btn.innerHTML="<div class=\"bt-spinner\" style=\"width:14px;height:14px;border-width:2px;display:inline-block;vertical-align:middle\"></div> AutoSSL in progress... ("+pollCount+"/"+maxPolls+")";
                setTimeout(doPoll,5000);
            }else{
                btn.disabled=false;btn.innerHTML=origHtml;
                // Reload SSL status to show updated certs
                var pane=$("bt-pane-ssl");if(pane) pane.dataset.loaded="";
                loadSSLStatus();
                // Check for problems
                post({action:"autossl_problems"},function(pr){
                    if(pr.success&&pr.problems&&pr.problems.length){
                        var list=$("bt-ssl-list");
                        if(list){
                            var msgHtml="<div class=\"bt-msg error\" style=\"display:block;margin:10px 14px\"><strong>AutoSSL Issues:</strong><ul style=\"margin:6px 0 0;padding-left:18px\">";
                            pr.problems.forEach(function(p){msgHtml+="<li>"+esc(p.domain||p)+": "+esc(p.problem||p.message||"Unknown issue")+"</li>";});
                            msgHtml+="</ul></div>";
                            list.insertAdjacentHTML("afterbegin",msgHtml);
                        }
                    }
                });
            }
        });
    }
    setTimeout(doPoll,5000);
}

/* ─── WordPress Pane ─── */
function buildWpPane(){
    var pane=$("bt-pane-wordpress");if(!pane) return;
    pane.innerHTML="<div class=\"bt-card\"><div class=\"bt-card-head\"><div class=\"bt-card-head-left\"><div class=\"bt-icon-circle\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 16 16\" fill=\"currentColor\"><path d=\"M12.633 7.653c0-.848-.305-1.435-.566-1.892l-.08-.13c-.317-.51-.594-.958-.594-1.48 0-.63.478-1.218 1.152-1.218q.03 0 .058.003l.031.003A6.84 6.84 0 0 0 8 1.137 6.86 6.86 0 0 0 2.266 4.23c.16.005.313.009.442.009.717 0 1.828-.087 1.828-.087.37-.022.414.521.044.565 0 0-.371.044-.785.065l2.5 7.434 1.5-4.506-1.07-2.929c-.369-.022-.719-.065-.719-.065-.37-.022-.326-.588.043-.566 0 0 1.134.087 1.808.087.718 0 1.83-.087 1.83-.087.37-.022.413.522.043.566 0 0-.372.043-.785.065l2.48 7.377.684-2.287.054-.173c.27-.86.469-1.495.469-2.046zM1.137 8a6.86 6.86 0 0 0 3.868 6.176L1.73 5.206A6.8 6.8 0 0 0 1.137 8\"/><path d=\"M6.061 14.583 8.121 8.6l2.109 5.78q.02.05.049.094a6.85 6.85 0 0 1-4.218.109m7.96-9.876q.046.328.047.706c0 .696-.13 1.479-.522 2.458l-2.096 6.06a6.86 6.86 0 0 0 2.572-9.224z\"/><path fill-rule=\"evenodd\" d=\"M0 8c0-4.411 3.589-8 8-8s8 3.589 8 8-3.59 8-8 8-8-3.589-8-8m.367 0c0 4.209 3.424 7.633 7.633 7.633S15.632 12.209 15.632 8C15.632 3.79 12.208.367 8 .367 3.79.367.367 3.79.367 8\"/></svg></div><div><h5>WordPress Manager</h5><p class=\"bt-wp-count\">Loading...</p></div></div></div><div class=\"bt-list\" id=\"bt-wp-list\"><div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading WordPress installations...</span></div></div></div>";
}

function loadWpInstances(){
    var list=$("bt-wp-list");if(!list) return;
    list.innerHTML="<div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading WordPress installations...</span></div>";
    wpPost({action:"get_wp_instances"},function(r){
        if(!r.success){list.innerHTML="<div class=\"bt-empty\"><span>"+(r.message||"Failed to load")+"</span></div>";return;}
        wpInstances=r.instances||[];
        var countEl=document.querySelector(".bt-wp-count");
        if(countEl) countEl.textContent=wpInstances.length+" site"+(wpInstances.length!==1?"s":"");
        if(!wpInstances.length){list.innerHTML="<div class=\"bt-empty\">"+wpSvg32+"<span>No WordPress installations found</span></div>";return;}
        var html="";
        wpInstances.forEach(function(inst){
            var statusCls=inst.alive?"active":"inactive";var statusTxt=inst.alive?"Active":"Inactive";
            var meta="<span>WP "+esc(inst.version)+"</span>";
            if(inst.pluginUpdates>0) meta+="<span style=\"color:#0a5ed3\">"+inst.pluginUpdates+" plugin update"+(inst.pluginUpdates>1?"s":"")+"</span>";
            if(inst.themeUpdates>0) meta+="<span style=\"color:#7c3aed\">"+inst.themeUpdates+" theme update"+(inst.themeUpdates>1?"s":"")+"</span>";
            if(inst.availableUpdate) meta+="<span style=\"color:#d97706\">Core update: "+esc(inst.availableUpdate)+"</span>";
            html+="<div class=\"bwp-site\" data-id=\""+inst.id+"\"><div class=\"bwp-site-icon\">"+wpSvg20+"</div><div class=\"bwp-site-info\"><p class=\"bwp-site-domain\">"+esc(inst.displayTitle||inst.domain)+"</p><div class=\"bwp-site-meta\"><span class=\"bwp-status-badge "+statusCls+"\"><span style=\"width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block\"></span> "+statusTxt+"</span>"+meta+"</div></div><div class=\"bwp-site-actions\"><button type=\"button\" class=\"bt-row-btn login\" data-wpid=\""+inst.id+"\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4\"/><polyline points=\"10 17 15 12 10 7\"/><line x1=\"15\" y1=\"12\" x2=\"3\" y2=\"12\"/></svg><span>Login</span></button><a href=\""+esc(inst.site_url)+"\" target=\"_blank\" class=\"bt-row-btn visit\"><svg width=\"13\" height=\"13\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6\"/><polyline points=\"15 3 21 3 21 9\"/><line x1=\"10\" y1=\"14\" x2=\"21\" y2=\"3\"/></svg><span>Visit</span></a><button type=\"button\" class=\"bt-row-btn\" data-wpdetail=\""+inst.id+"\" style=\"color:#0a5ed3\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"3\"/><path d=\"M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z\"/></svg><span>Manage</span></button></div></div>";
        });
        list.innerHTML=html;
        list.querySelectorAll(".bt-row-btn.login[data-wpid]").forEach(function(b){b.addEventListener("click",function(){bwpAutoLogin(parseInt(this.getAttribute("data-wpid")));});});
        list.querySelectorAll("[data-wpdetail]").forEach(function(b){b.addEventListener("click",function(){bwpOpenDetail(parseInt(this.getAttribute("data-wpdetail")));});});
    });
}

function bwpAutoLogin(id){
    wpPost({action:"wp_autologin",instance_id:id},function(r){
        if(r.success&&r.login_url) window.open(r.login_url,"_blank");
        else alert(r.message||"Could not generate login link");
    });
}
window.bwpDoLogin=bwpAutoLogin;

function bwpOpenDetail(id){
    currentWpInstance=null;
    for(var i=0;i<wpInstances.length;i++){if(wpInstances[i].id===id){currentWpInstance=wpInstances[i];break;}}
    if(!currentWpInstance) return;
    var ov=$("bwpDetailOverlay");ov.style.display="flex";
    $("bwpDetailTitle").textContent=currentWpInstance.displayTitle||currentWpInstance.domain;
    // Reset tabs to overview
    ov.querySelectorAll(".bwp-tab").forEach(function(t,i){t.classList.toggle("active",i===0);});
    ov.querySelectorAll(".bwp-tab-content").forEach(function(c,i){c.classList.toggle("active",i===0);});
    // Build overview
    var ovTab=$("bwpTabOverview");
    var siteUrl=currentWpInstance.site_url||"";
    var html="<div class=\"bwp-overview-hero\"><div class=\"bwp-preview-col\"><div class=\"bwp-preview-wrap\"><div class=\"bwp-preview-bar\"><div class=\"bwp-preview-dots\"><span></span><span></span><span></span></div><div class=\"bwp-preview-url\">"+esc(siteUrl)+"</div></div><div class=\"bwp-preview-frame-wrap\"><iframe src=\""+esc(siteUrl)+"\" style=\"width:200%;height:200%;transform:scale(.5);transform-origin:0 0;border:none;pointer-events:none\" loading=\"lazy\" sandbox=\"allow-same-origin\"></iframe></div></div><div class=\"bwp-quick-actions\"><button type=\"button\" class=\"bt-btn-add\" onclick=\"bwpDoLogin("+id+")\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4\"/><polyline points=\"10 17 15 12 10 7\"/><line x1=\"15\" y1=\"12\" x2=\"3\" y2=\"12\"/></svg> WP Admin</button><a href=\""+esc(siteUrl)+"\" target=\"_blank\" class=\"bt-btn-outline\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6\"/><polyline points=\"15 3 21 3 21 9\"/><line x1=\"10\" y1=\"14\" x2=\"21\" y2=\"3\"/></svg> Visit Site</a></div></div>";
    html+="<div class=\"bwp-overview-right\"><div class=\"bwp-site-header\"><div class=\"bwp-site-header-icon\">"+wpSvg20+"</div><div class=\"bwp-site-header-info\"><h4>"+esc(currentWpInstance.displayTitle||currentWpInstance.domain)+"</h4><p><span>WP "+esc(currentWpInstance.version)+"</span><span>"+esc(currentWpInstance.path)+"</span></p></div></div>";
    html+="<div class=\"bwp-overview-grid\">";
    html+="<div class=\"bwp-stat\"><div class=\"bwp-stat-label\">Status</div><div class=\"bwp-stat-value\">"+(currentWpInstance.alive?"<span style=\"color:#059669\">Active</span>":"<span style=\"color:#ef4444\">Inactive</span>")+"</div></div>";
    html+="<div class=\"bwp-stat\"><div class=\"bwp-stat-label\">SSL</div><div class=\"bwp-stat-value\">"+(currentWpInstance.ssl?"<span style=\"color:#059669\">Enabled</span>":"<span style=\"color:#d97706\">Disabled</span>")+"</div></div>";
    html+="<div class=\"bwp-stat\"><div class=\"bwp-stat-label\">Plugin Updates</div><div class=\"bwp-stat-value\">"+(currentWpInstance.pluginUpdates>0?"<span style=\"color:#0a5ed3\">"+currentWpInstance.pluginUpdates+" available</span>":"<span style=\"color:#059669\">Up to date</span>")+"</div></div>";
    html+="<div class=\"bwp-stat\"><div class=\"bwp-stat-label\">Theme Updates</div><div class=\"bwp-stat-value\">"+(currentWpInstance.themeUpdates>0?"<span style=\"color:#7c3aed\">"+currentWpInstance.themeUpdates+" available</span>":"<span style=\"color:#059669\">Up to date</span>")+"</div></div>";
    html+="</div>";
    if(currentWpInstance.availableUpdate) html+="<div class=\"bwp-msg info\">Core update available: WordPress "+esc(currentWpInstance.availableUpdate)+"</div>";
    html+="</div></div>";
    ovTab.innerHTML=html;
    // Mark sub-tabs as not loaded so they load on first view
    ["bwpTabPlugins","bwpTabThemes","bwpTabSecurity"].forEach(function(tid){
        var t=$(tid);if(t){t.removeAttribute("data-loaded");t.innerHTML="<div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading...</span></div>";}
    });
    // Pre-load plugins since it is the next likely tab
    bwpLoadPlugins();
}

/* ─── WP Detail Tab Handlers (lazy load, no reset on switch) ─── */
(function(){
    var overlay=$("bwpDetailOverlay");if(!overlay) return;
    overlay.querySelectorAll(".bwp-tab").forEach(function(tab){
        tab.addEventListener("click",function(){
            overlay.querySelectorAll(".bwp-tab").forEach(function(t){t.classList.remove("active");});
            overlay.querySelectorAll(".bwp-tab-content").forEach(function(c){c.classList.remove("active");});
            tab.classList.add("active");
            var tabName=tab.getAttribute("data-tab");
            var target=$("bwpTab"+tabName.charAt(0).toUpperCase()+tabName.slice(1));
            if(target){
                target.classList.add("active");
                // Lazy load: only load if not already loaded
                if(!target.getAttribute("data-loaded")){
                    if(tabName==="plugins") bwpLoadPlugins();
                    else if(tabName==="themes") bwpLoadThemes();
                    else if(tabName==="security") bwpLoadSecurity();
                }
            }
        });
    });
    $("bwpDetailClose").addEventListener("click",function(){overlay.style.display="none";});
    overlay.addEventListener("click",function(e){if(e.target===overlay) overlay.style.display="none";});
})();

function bwpLoadPlugins(){
    if(!currentWpInstance) return;
    wpPost({action:"wp_list_plugins",instance_id:currentWpInstance.id},function(r){
        var el=$("bwpTabPlugins");
        if(!r.success){el.innerHTML="<div class=\"bt-empty\"><span>"+(r.message||"Failed")+"</span></div>";return;}
        var plugins=r.plugins||[];
        if(!plugins.length){el.innerHTML="<div class=\"bt-empty\"><span>No plugins found</span></div>";return;}
        var html="";
        plugins.forEach(function(p){
            var active=p.active||p.status==="active";
            var hasUpdate=!!p.availableVersion;
            html+="<div class=\"bwp-item-row\"><div class=\"bwp-item-icon plugin\">"+esc((p.title||p.name||p.slug||"P").charAt(0).toUpperCase())+"</div><div class=\"bwp-item-info\"><p class=\"bwp-item-name\">"+esc(p.title||p.name||p.slug)+"</p><p class=\"bwp-item-detail\">v"+esc(p.version||"?")+(hasUpdate?" → "+esc(p.availableVersion):"")+"</p></div><div class=\"bwp-item-actions\">";
            html+="<button type=\"button\" class=\"bwp-item-btn "+(active?"active-state":"inactive-state")+"\" onclick=\"bwpTogglePlugin(\\x27"+esc(p.slug)+"\\x27,"+(!active)+")\">"+(active?"Deactivate":"Activate")+"</button>";
            if(hasUpdate) html+="<button type=\"button\" class=\"bwp-item-btn update\" onclick=\"bwpUpdatePlugin(\\x27"+esc(p.slug)+"\\x27,this)\">Update</button>";
            html+="</div></div>";
        });
        el.innerHTML=html;
        el.setAttribute("data-loaded","1");
    });
}
window.bwpTogglePlugin=function(slug,activate){
    if(!currentWpInstance) return;
    wpPost({action:"wp_toggle_plugin",instance_id:currentWpInstance.id,slug:slug,activate:activate?"1":"0"},function(r){
        if(r.success) bwpLoadPlugins(); else alert(r.message||"Failed");
    });
};
window.bwpUpdatePlugin=function(slug,btn){
    if(!currentWpInstance) return;btn.disabled=true;btn.textContent="Updating...";
    wpPost({action:"wp_update",instance_id:currentWpInstance.id,type:"plugins",slug:slug},function(r){
        btn.disabled=false;
        if(r.success){btn.textContent="Updated";btn.style.color="#059669";setTimeout(function(){bwpLoadPlugins();},1000);}
        else{btn.textContent="Update";alert(r.message||"Failed");}
    });
};

function bwpLoadThemes(){
    if(!currentWpInstance) return;
    wpPost({action:"wp_list_themes",instance_id:currentWpInstance.id},function(r){
        var el=$("bwpTabThemes");
        if(!r.success){el.innerHTML="<div class=\"bt-empty\"><span>"+(r.message||"Failed")+"</span></div>";return;}
        var themes=r.themes||[];
        if(!themes.length){el.innerHTML="<div class=\"bt-empty\"><span>No themes found</span></div>";return;}
        var html="<div class=\"bwp-theme-grid\">";
        themes.forEach(function(t){
            var active=t.active||t.status==="active";
            var hasUpdate=!!t.availableVersion;
            var screenshot=t.screenshot||t.screenshotUrl||"";
            html+="<div class=\"bwp-theme-card"+(active?" bwp-theme-active":"")+"\"><div class=\"bwp-theme-screenshot\">"+(screenshot?"<img src=\""+esc(screenshot)+"\" alt=\""+esc(t.title||t.name||t.slug)+"\" loading=\"lazy\">":"")+(active?"<div class=\"bwp-theme-active-badge\">Active</div>":"")+"</div><div class=\"bwp-theme-info\"><p class=\"bwp-theme-name\">"+esc(t.title||t.name||t.slug)+"</p><p class=\"bwp-theme-ver\">v"+esc(t.version||"?")+(hasUpdate?" → "+esc(t.availableVersion):"")+"</p><div class=\"bwp-theme-actions\">";
            if(!active) html+="<button type=\"button\" class=\"bwp-item-btn\" onclick=\"bwpActivateTheme(\\x27"+esc(t.slug)+"\\x27,this)\">Activate</button>";
            if(hasUpdate) html+="<button type=\"button\" class=\"bwp-item-btn update\" onclick=\"bwpUpdateTheme(\\x27"+esc(t.slug)+"\\x27,this)\">Update</button>";
            html+="</div></div></div>";
        });
        html+="</div>";
        el.innerHTML=html;
        el.setAttribute("data-loaded","1");
    });
}
window.bwpActivateTheme=function(slug,btn){
    if(!currentWpInstance) return;btn.disabled=true;btn.textContent="Activating...";
    wpPost({action:"wp_toggle_theme",instance_id:currentWpInstance.id,slug:slug},function(r){
        btn.disabled=false;
        if(r.success) bwpLoadThemes(); else{btn.textContent="Activate";alert(r.message||"Failed");}
    });
};
window.bwpUpdateTheme=function(slug,btn){
    if(!currentWpInstance) return;btn.disabled=true;btn.textContent="Updating...";
    wpPost({action:"wp_update",instance_id:currentWpInstance.id,type:"themes",slug:slug},function(r){
        btn.disabled=false;
        if(r.success){btn.textContent="Updated";btn.style.color="#059669";setTimeout(function(){bwpLoadThemes();},1000);}
        else{btn.textContent="Update";alert(r.message||"Failed");}
    });
};

function bwpLoadSecurity(){
    if(!currentWpInstance) return;
    wpPost({action:"wp_security_scan",instance_id:currentWpInstance.id},function(r){
        var el=$("bwpTabSecurity");
        if(!r.success){el.innerHTML="<div class=\"bt-empty\"><span>"+(r.message||"Security scan failed")+"</span></div>";el.setAttribute("data-loaded","1");return;}
        var measures=r.security||[];
        if(!measures.length){el.innerHTML="<div class=\"bt-empty\"><span>No security data available</span></div>";el.setAttribute("data-loaded","1");return;}
        var applied=0;measures.forEach(function(m){if(m.status==="applied"||m.status==="true"||m.status===true) applied++;});
        var pct=Math.round(applied/measures.length*100);
        var html="<div class=\"bwp-sec-summary\"><div class=\"bwp-sec-summary-bar\"><div class=\"bwp-sec-summary-fill\" style=\"width:"+pct+"%\"></div></div><div class=\"bwp-sec-summary-text\"><span><strong>"+applied+"</strong> of <strong>"+measures.length+"</strong> measures applied</span><span><strong>"+pct+"%</strong> secure</span></div></div>";
        measures.forEach(function(m){
            var ok=m.status==="applied"||m.status==="true"||m.status===true;
            var mid=esc(m.id);
            html+="<div class=\"bwp-security-item\" data-measure=\""+mid+"\"><div class=\"bwp-sec-icon "+(ok?"ok":"warning")+"\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\">"+(ok?"<polyline points=\"20 6 9 17 4 12\"/>":"<circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"12\" y1=\"8\" x2=\"12\" y2=\"12\"/><line x1=\"12\" y1=\"16\" x2=\"12.01\" y2=\"16\"/>")+"</svg></div><div class=\"bwp-sec-info\"><p class=\"bwp-sec-label\">"+esc(m.title||m.id)+"</p><p class=\"bwp-sec-detail\">"+mid+"</p></div><div class=\"bwp-sec-actions\" style=\"display:flex;gap:6px;flex-shrink:0\">"+(ok?"<button type=\"button\" class=\"bwp-item-btn inactive-state\" onclick=\"bwpRevertSecurity(\\x27"+mid+"\\x27,this)\">Revert</button>":"<button type=\"button\" class=\"bwp-item-btn active-state\" onclick=\"bwpApplySecurity(\\x27"+mid+"\\x27,this)\">Apply</button>")+"</div></div>";
        });
        el.innerHTML=html;
        el.setAttribute("data-loaded","1");
    });
}
window.bwpApplySecurity=function(measureId,btn){
    if(!currentWpInstance) return;btn.disabled=true;btn.textContent="Applying...";
    wpPost({action:"wp_security_apply",instance_id:currentWpInstance.id,measure_id:measureId},function(r){
        btn.disabled=false;
        if(r.success){$("bwpTabSecurity").removeAttribute("data-loaded");bwpLoadSecurity();}
        else{btn.textContent="Apply";alert(r.message||"Failed to apply security measure");}
    });
};
window.bwpRevertSecurity=function(measureId,btn){
    if(!currentWpInstance) return;btn.disabled=true;btn.textContent="Reverting...";
    wpPost({action:"wp_security_revert",instance_id:currentWpInstance.id,measure_id:measureId},function(r){
        btn.disabled=false;
        if(r.success){$("bwpTabSecurity").removeAttribute("data-loaded");bwpLoadSecurity();}
        else{btn.textContent="Revert";alert(r.message||"Failed to revert security measure");}
    });
};

/* ─── Email Actions ─── */
function bindEmailActions(pane){
    pane.querySelectorAll(".bt-row-btn.login[data-email]").forEach(function(b){b.addEventListener("click",function(){
        var email=this.getAttribute("data-email");var btn=this;btn.disabled=true;
        post({action:"webmail_login",email:email},function(r){btn.disabled=false;if(r.success&&r.url) window.open(r.url,"_blank");else alert(r.message||"Failed");});
    });});
    pane.querySelectorAll(".bt-row-btn.pass[data-email]").forEach(function(b){b.addEventListener("click",function(){
        $("bemPassEmail").value=this.getAttribute("data-email");$("bemPassNew").value="";$("bemPassMsg").style.display="none";$("bemPassModal").style.display="flex";
    });});
    pane.querySelectorAll(".bt-row-btn.del[data-email]").forEach(function(b){b.addEventListener("click",function(){
        $("bemDelEmail").textContent=this.getAttribute("data-email");$("bemDelMsg").style.display="none";$("bemDelModal").style.display="flex";
    });});
}

function openCreateEmailModal(){
    $("bemNewUser").value="";$("bemNewPass").value="";$("bemNewQuota").value="250";$("bemCreateMsg").style.display="none";
    var sel=$("bemNewDomain");sel.innerHTML="<option>Loading...</option>";
    $("bemCreateModal").style.display="flex";
    post({action:"get_domains"},function(r){
        sel.innerHTML="";
        var doms=r.domains||[];
        if(!doms.length&&C.domains&&C.domains.main) doms=[C.domains.main];
        doms.forEach(function(d){var o=document.createElement("option");o.value=d;o.textContent=d;sel.appendChild(o);});
    });
}

$("bemCreateSubmit").addEventListener("click",function(){
    var user=$("bemNewUser").value.trim();var pass=$("bemNewPass").value;var domain=$("bemNewDomain").value;var quota=$("bemNewQuota").value;var msg=$("bemCreateMsg");msg.style.display="none";
    if(!user||!pass||!domain){showMsg(msg,"Please fill in all fields",false);return;}
    this.disabled=true;var btn=this;
    post({action:"create_email",email_user:user,email_pass:pass,domain:domain,quota:quota},function(r){
        btn.disabled=false;showMsg(msg,r.message||"Done",r.success);
        if(r.success){C.emails=C.emails||[];C.emails.push(r.email);setTimeout(function(){$("bemCreateModal").style.display="none";buildEmailPane();},800);}
    });
});

$("bemPassSubmit").addEventListener("click",function(){
    var email=$("bemPassEmail").value;var pass=$("bemPassNew").value;var msg=$("bemPassMsg");msg.style.display="none";
    if(!pass){showMsg(msg,"Please enter a new password",false);return;}
    this.disabled=true;var btn=this;
    post({action:"change_password",email:email,new_pass:pass},function(r){btn.disabled=false;showMsg(msg,r.message||"Done",r.success);if(r.success) setTimeout(function(){$("bemPassModal").style.display="none";},800);});
});

$("bemDelSubmit").addEventListener("click",function(){
    var email=$("bemDelEmail").textContent;var msg=$("bemDelMsg");msg.style.display="none";
    this.disabled=true;var btn=this;
    post({action:"delete_email",email:email},function(r){
        btn.disabled=false;showMsg(msg,r.message||"Done",r.success);
        if(r.success){C.emails=(C.emails||[]).filter(function(e){return e!==email;});setTimeout(function(){$("bemDelModal").style.display="none";buildEmailPane();},800);}
    });
});

/* ─── Domain Actions ─── */
function bindDomainActions(pane){
    pane.querySelectorAll(".bt-row-btn.del[data-domain]").forEach(function(b){b.addEventListener("click",function(){
        openDelDomainModal(this.getAttribute("data-domain"),this.getAttribute("data-type"));
    });});
}

function openAddonModal(){
    $("bdmAddonDomain").value="";$("bdmAddonDocroot").value="";$("bdmAddonMsg").style.display="none";$("bdmAddonModal").style.display="flex";
}
function openSubModal(){
    $("bdmSubName").value="";$("bdmSubDocroot").value="";$("bdmSubMsg").style.display="none";
    var sel=$("bdmSubParent");sel.innerHTML="<option>Loading...</option>";
    $("bdmSubModal").style.display="flex";
    post({action:"get_parent_domains"},function(r){
        sel.innerHTML="";
        var doms=r.domains||[];
        doms.forEach(function(d){var o=document.createElement("option");o.value=d;o.textContent=d;sel.appendChild(o);});
    });
}

$("bdmAddonSubmit").addEventListener("click",function(){
    var domain=$("bdmAddonDomain").value.trim();var docroot=$("bdmAddonDocroot").value.trim();var msg=$("bdmAddonMsg");msg.style.display="none";
    if(!domain){showMsg(msg,"Please enter a domain name",false);return;}
    this.disabled=true;var btn=this;
    post({action:"add_addon_domain",domain:domain,docroot:docroot},function(r){
        btn.disabled=false;showMsg(msg,r.message||"Done",r.success);
        if(r.success){if(C.domains) C.domains.addon=(C.domains.addon||[]).concat([domain]);setTimeout(function(){$("bdmAddonModal").style.display="none";buildDomainsPane();},800);}
    });
});

$("bdmSubSubmit").addEventListener("click",function(){
    var sub=$("bdmSubName").value.trim();var parent=$("bdmSubParent").value;var docroot=$("bdmSubDocroot").value.trim();var msg=$("bdmSubMsg");msg.style.display="none";
    if(!sub||!parent){showMsg(msg,"Please fill in all fields",false);return;}
    this.disabled=true;var btn=this;
    post({action:"add_subdomain",subdomain:sub,domain:parent,docroot:docroot},function(r){
        btn.disabled=false;showMsg(msg,r.message||"Done",r.success);
        if(r.success){if(C.domains) C.domains.sub=(C.domains.sub||[]).concat([r.domain||sub+"."+parent]);setTimeout(function(){$("bdmSubModal").style.display="none";buildDomainsPane();},800);}
    });
});

function openDelDomainModal(domain,type){
    $("bdmDelDomain").textContent=domain;$("bdmDelMsg").style.display="none";$("bdmDelModal").style.display="flex";
    $("bdmDelSubmit").onclick=function(){
        var msg=$("bdmDelMsg");msg.style.display="none";this.disabled=true;var btn=this;
        post({action:"delete_domain",domain:domain,type:type},function(r){
            btn.disabled=false;showMsg(msg,r.message||"Done",r.success);
            if(r.success){
                if(C.domains){
                    if(type==="addon") C.domains.addon=(C.domains.addon||[]).filter(function(d){return d!==domain;});
                    if(type==="sub") C.domains.sub=(C.domains.sub||[]).filter(function(d){return d!==domain;});
                    if(type==="parked") C.domains.parked=(C.domains.parked||[]).filter(function(d){return d!==domain;});
                }
                setTimeout(function(){$("bdmDelModal").style.display="none";buildDomainsPane();},800);
            }
        });
    };
}

/* ─── DNS Manager Pane ─── */
var dnsCurrentDomain="";
var dnsRecords=[];
var dnsSelectedLines={};
var dnsActiveFilter="ALL";

function buildDnsPane(){
    var pane=$("bt-pane-dns");if(!pane) return;
    pane.innerHTML='<div class="bt-card"><div class="bt-card-head"><div class="bt-card-head-left"><div class="bt-icon-circle" style="background:#7c3aed"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div><div><h5>DNS Manager</h5><p id="bt-dns-subtitle">Select a domain to manage DNS records</p></div></div></div><div id="bt-dns-body"><div id="bt-dns-domain-list" class="bt-list"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading domains...</span></div></div><div id="bt-dns-records-view" style="display:none"><div class="bt-dns-toolbar" id="bt-dns-toolbar"></div><div class="bt-dns-filter-bar" id="bt-dns-filter-bar"></div><div class="bt-list" id="bt-dns-records-list"></div></div></div></div>';
}

function loadDnsDomains(){
    var list=$("bt-dns-domain-list");if(!list) return;
    list.innerHTML='<div class="bt-loading"><div class="bt-spinner"></div><span>Loading domains...</span></div>';
    post({action:"dns_list_domains"},function(r){
        if(!r.success||!r.domains||!r.domains.length){
            list.innerHTML='<div class="bt-empty"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span>No domains found</span></div>';
            return;
        }
        var html="";
        var typeIcons={main:"main",addon:"addon",sub:"sub",parked:"parked"};
        var typeBadges={main:"bt-badge-primary",addon:"bt-badge-green",sub:"bt-badge-purple",parked:"bt-badge-amber"};
        r.domains.forEach(function(d){
            html+='<div class="bt-row bt-dns-domain-row" data-domain="'+esc(d.domain)+'" style="cursor:pointer">'
                +'<div class="bt-row-icon '+(typeIcons[d.type]||"main")+'"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z"/></svg></div>'
                +'<div class="bt-row-info"><span class="bt-row-name">'+esc(d.domain)+'</span><span class="bt-row-badge '+(typeBadges[d.type]||"bt-badge-primary")+'">'+esc(d.type)+'</span></div>'
                +'<div class="bt-row-actions"><span style="color:var(--text-muted,#9ca3af)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></span></div>'
                +'</div>';
        });
        list.innerHTML=html;
        list.querySelectorAll(".bt-dns-domain-row").forEach(function(row){
            row.addEventListener("click",function(){
                var domain=this.getAttribute("data-domain");
                dnsCurrentDomain=domain;
                $("bt-dns-domain-list").style.display="none";
                $("bt-dns-records-view").style.display="block";
                $("bt-dns-subtitle").textContent=domain;
                loadDnsRecords(domain);
            });
        });
    });
}

function loadDnsRecords(domain){
    var list=$("bt-dns-records-list");if(!list) return;
    list.innerHTML='<div class="bt-loading"><div class="bt-spinner"></div><span>Loading DNS records...</span></div>';
    dnsSelectedLines={};
    post({action:"dns_fetch_records",domain:domain},function(r){
        if(!r.success){
            list.innerHTML='<div class="bt-empty"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span>'+(r.message||"Failed to load records")+'</span></div>';
            return;
        }
        dnsRecords=r.records||[];
        renderDnsToolbar();
        renderDnsFilterBar();
        renderDnsRecords();
    });
}

function renderDnsToolbar(){
    var tb=$("bt-dns-toolbar");if(!tb) return;
    tb.innerHTML='<div style="display:flex;align-items:center;gap:8px;padding:12px 14px;flex-wrap:wrap">'
        +'<button type="button" class="bt-btn-outline bt-dns-back-btn" style="padding:6px 12px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg> Domains</button>'
        +'<div style="flex:1"></div>'
        +'<button type="button" class="bt-btn-outline bt-dns-bulk-del-btn" style="padding:6px 12px;display:none;color:#ef4444;border-color:#ef4444"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Delete Selected (<span class="bt-dns-sel-count">0</span>)</button>'
        +'<button type="button" class="bt-btn-add bt-dns-add-btn" style="padding:7px 14px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Record</button>'
        +'</div>';
    tb.querySelector(".bt-dns-back-btn").addEventListener("click",function(){
        $("bt-dns-records-view").style.display="none";
        $("bt-dns-domain-list").style.display="block";
        $("bt-dns-subtitle").textContent="Select a domain to manage DNS records";
        dnsCurrentDomain="";
    });
    tb.querySelector(".bt-dns-add-btn").addEventListener("click",function(){
        openDnsAddModal();
    });
    tb.querySelector(".bt-dns-bulk-del-btn").addEventListener("click",function(){
        dnsHandleBulkDelete();
    });
}

function renderDnsFilterBar(){
    var fb=$("bt-dns-filter-bar");if(!fb) return;
    var types=["ALL"];
    var typeCounts={ALL:0};
    dnsRecords.forEach(function(r){
        typeCounts.ALL++;
        if(!typeCounts[r.type]) typeCounts[r.type]=0;
        typeCounts[r.type]++;
        if(types.indexOf(r.type)===-1) types.push(r.type);
    });
    // Sort types: ALL first, then SOA, NS, A, AAAA, CNAME, MX, TXT, SRV, CAA, rest
    var order=["ALL","SOA","NS","A","AAAA","CNAME","MX","TXT","SRV","CAA"];
    types.sort(function(a,b){
        var ia=order.indexOf(a),ib=order.indexOf(b);
        if(ia===-1) ia=99;if(ib===-1) ib=99;
        return ia-ib;
    });
    var html='<div style="display:flex;gap:4px;padding:8px 14px;overflow-x:auto;flex-wrap:wrap">';
    types.forEach(function(t){
        var active=t===dnsActiveFilter?" active":"";
        html+='<button type="button" class="bt-dns-filter-btn'+active+'" data-filter="'+t+'" style="padding:4px 12px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#e5e7eb);background:'+(active?"#0a5ed3":"var(--card-bg,#fff)")+';color:'+(active?"#fff":"var(--heading-color,#374151)")+';transition:all .15s;white-space:nowrap">'+t+' <span style="opacity:.6;font-size:11px">('+typeCounts[t]+')</span></button>';
    });
    html+='</div>';
    fb.innerHTML=html;
    fb.querySelectorAll(".bt-dns-filter-btn").forEach(function(btn){
        btn.addEventListener("click",function(){
            dnsActiveFilter=this.getAttribute("data-filter");
            renderDnsFilterBar();
            renderDnsRecords();
        });
    });
}

function dnsRecordValue(r){
    switch(r.type){
        case "A":case "AAAA": return r.address||"";
        case "CNAME": return r.cname||"";
        case "MX": return (r.preference||0)+" "+( r.exchange||"");
        case "TXT": return r.txtdata||"";
        case "NS": return r.nsdname||"";
        case "SRV": return (r.priority||0)+" "+(r.weight||0)+" "+(r.port||0)+" "+(r.target||"");
        case "CAA": return (r.flag||0)+" "+(r.tag||"")+" "+(r.value||"");
        case "SOA": return (r.mname||"")+" "+(r.rname||"");
        default: return "";
    }
}

function dnsTypeColor(type){
    var colors={A:"#0a5ed3",AAAA:"#7c3aed",CNAME:"#059669",MX:"#d97706",TXT:"#6366f1",NS:"#0891b2",SRV:"#be185d",CAA:"#dc2626",SOA:"#6b7280"};
    return colors[type]||"#6b7280";
}

function renderDnsRecords(){
    var list=$("bt-dns-records-list");if(!list) return;
    var filtered=dnsActiveFilter==="ALL"?dnsRecords:dnsRecords.filter(function(r){return r.type===dnsActiveFilter;});
    if(!filtered.length){
        list.innerHTML='<div class="bt-empty"><span>No '+dnsActiveFilter+' records found</span></div>';
        return;
    }
    var html="";
    filtered.forEach(function(r){
        var val=dnsRecordValue(r);
        var isEditable=["A","AAAA","CNAME","MX","TXT","SRV","CAA"].indexOf(r.type)!==-1;
        var isDeletable=["A","AAAA","CNAME","MX","TXT","SRV","CAA"].indexOf(r.type)!==-1;
        var checked=dnsSelectedLines[r.line]?" checked":"";
        var color=dnsTypeColor(r.type);
        html+='<div class="bt-row bt-dns-record-row" data-line="'+r.line+'">'
            +(isDeletable?'<label style="display:flex;align-items:center;cursor:pointer;flex-shrink:0"><input type="checkbox" class="bt-dns-check" data-line="'+r.line+'"'+checked+' style="width:16px;height:16px;accent-color:#0a5ed3;cursor:pointer"></label>':'<div style="width:16px"></div>')
            +'<div style="min-width:56px;flex-shrink:0"><span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:'+color+'15;color:'+color+';letter-spacing:.3px">'+esc(r.type)+'</span></div>'
            +'<div class="bt-row-info" style="flex-direction:column;align-items:flex-start;gap:2px;min-width:0">'
            +'<span class="bt-row-name mono" style="font-size:13px">'+esc(r.name)+'</span>'
            +'<span style="font-size:12px;color:var(--text-muted,#6b7280);word-break:break-all;max-width:100%;overflow:hidden;text-overflow:ellipsis">'+esc(val)+'</span>'
            +'</div>'
            +'<div style="font-size:11px;color:var(--text-muted,#9ca3af);white-space:nowrap;flex-shrink:0">TTL: '+r.ttl+'</div>'
            +'<div class="bt-row-actions">'
            +(isEditable?'<button type="button" class="bt-row-btn pass bt-dns-edit-btn" data-line="'+r.line+'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg><span>Edit</span></button>':'')
            +(isDeletable?'<button type="button" class="bt-row-btn del bt-dns-del-btn" data-line="'+r.line+'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><span>Delete</span></button>':'')
            +'</div></div>';
    });
    list.innerHTML=html;
    // Bind checkboxes
    list.querySelectorAll(".bt-dns-check").forEach(function(cb){
        cb.addEventListener("change",function(){
            var line=parseInt(this.getAttribute("data-line"));
            if(this.checked) dnsSelectedLines[line]=true;
            else delete dnsSelectedLines[line];
            updateDnsBulkBtn();
        });
    });
    // Bind edit buttons
    list.querySelectorAll(".bt-dns-edit-btn").forEach(function(btn){
        btn.addEventListener("click",function(){
            var line=parseInt(this.getAttribute("data-line"));
            var rec=dnsRecords.find(function(r){return r.line===line;});
            if(rec) openDnsEditModal(rec);
        });
    });
    // Bind delete buttons
    list.querySelectorAll(".bt-dns-del-btn").forEach(function(btn){
        btn.addEventListener("click",function(){
            var line=parseInt(this.getAttribute("data-line"));
            var rec=dnsRecords.find(function(r){return r.line===line;});
            if(rec) openDnsDeleteConfirm(rec);
        });
    });
}

function updateDnsBulkBtn(){
    var count=Object.keys(dnsSelectedLines).length;
    var btn=document.querySelector(".bt-dns-bulk-del-btn");
    if(!btn) return;
    btn.style.display=count>0?"inline-flex":"none";
    var span=btn.querySelector(".bt-dns-sel-count");
    if(span) span.textContent=count;
}

function dnsHandleBulkDelete(){
    var lines=Object.keys(dnsSelectedLines).map(Number);
    if(!lines.length) return;
    if(!confirm("Delete "+lines.length+" selected DNS record(s)? This cannot be undone.")) return;
    var btn=document.querySelector(".bt-dns-bulk-del-btn");
    if(btn) btn.disabled=true;
    post({action:"dns_bulk_delete",domain:dnsCurrentDomain,lines:lines.join(",")},function(r){
        if(btn) btn.disabled=false;
        if(r.success||r.deleted>0){
            dnsSelectedLines={};
            loadDnsRecords(dnsCurrentDomain);
        } else {
            alert(r.message||"Failed to delete records");
        }
    });
}

function openDnsAddModal(){
    var overlay=document.createElement("div");
    overlay.className="bt-overlay";overlay.id="btDnsAddOverlay";
    overlay.innerHTML='<div class="bt-modal" style="max-width:520px"><div class="bt-modal-head"><h5>Add DNS Record</h5><button type="button" class="bt-modal-close" data-dns-close>&times;</button></div><div class="bt-modal-body">'
        +'<div class="bt-field"><label>Record Type</label><select id="btDnsAddType" class="bt-select"><option value="A">A</option><option value="AAAA">AAAA</option><option value="CNAME">CNAME</option><option value="MX">MX</option><option value="TXT">TXT</option><option value="SRV">SRV</option><option value="CAA">CAA</option></select></div>'
        +'<div class="bt-field"><label>Name</label><input type="text" id="btDnsAddName" placeholder="subdomain.'+esc(dnsCurrentDomain)+'." autocomplete="off"></div>'
        +'<div class="bt-field"><label>TTL</label><input type="number" id="btDnsAddTtl" value="14400" min="60"></div>'
        +'<div id="btDnsAddFields"></div>'
        +'<div class="bt-msg" id="btDnsAddMsg"></div>'
        +'</div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-dns-close>Cancel</button><button type="button" class="bt-btn-primary" id="btDnsAddSubmit">Add Record</button></div></div>';
    document.body.appendChild(overlay);
    overlay.style.display="flex";
    var typeSelect=$("btDnsAddType");
    function updateFields(){dnsRenderTypeFields("btDnsAddFields",typeSelect.value,{});}
    typeSelect.addEventListener("change",updateFields);
    updateFields();
    overlay.querySelector("[data-dns-close]").addEventListener("click",function(){overlay.remove();});
    overlay.querySelectorAll("[data-dns-close]").forEach(function(b){b.addEventListener("click",function(){overlay.remove();});});
    overlay.addEventListener("click",function(e){if(e.target===overlay) overlay.remove();});
    $("btDnsAddSubmit").addEventListener("click",function(){
        var data={action:"dns_add_record",domain:dnsCurrentDomain,type:typeSelect.value,name:$("btDnsAddName").value.trim(),ttl:$("btDnsAddTtl").value};
        Object.assign(data,dnsCollectTypeFields("btDnsAddFields",typeSelect.value));
        var msg=$("btDnsAddMsg");msg.style.display="none";
        this.disabled=true;var btn=this;
        post(data,function(r){
            btn.disabled=false;
            showMsg(msg,r.message||"Done",r.success);
            if(r.success) setTimeout(function(){overlay.remove();loadDnsRecords(dnsCurrentDomain);},600);
        });
    });
}

function openDnsEditModal(rec){
    var overlay=document.createElement("div");
    overlay.className="bt-overlay";overlay.id="btDnsEditOverlay";
    overlay.innerHTML='<div class="bt-modal" style="max-width:520px"><div class="bt-modal-head"><h5>Edit '+esc(rec.type)+' Record</h5><button type="button" class="bt-modal-close" data-dns-close>&times;</button></div><div class="bt-modal-body">'
        +'<div class="bt-field"><label>Name</label><input type="text" id="btDnsEditName" value="'+esc(rec.name)+'" autocomplete="off"></div>'
        +'<div class="bt-field"><label>TTL</label><input type="number" id="btDnsEditTtl" value="'+rec.ttl+'" min="60"></div>'
        +'<div id="btDnsEditFields"></div>'
        +'<div class="bt-msg" id="btDnsEditMsg"></div>'
        +'</div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-dns-close>Cancel</button><button type="button" class="bt-btn-primary" id="btDnsEditSubmit">Save Changes</button></div></div>';
    document.body.appendChild(overlay);
    overlay.style.display="flex";
    dnsRenderTypeFields("btDnsEditFields",rec.type,rec);
    overlay.querySelector("[data-dns-close]").addEventListener("click",function(){overlay.remove();});
    overlay.querySelectorAll("[data-dns-close]").forEach(function(b){b.addEventListener("click",function(){overlay.remove();});});
    overlay.addEventListener("click",function(e){if(e.target===overlay) overlay.remove();});
    $("btDnsEditSubmit").addEventListener("click",function(){
        var data={action:"dns_edit_record",domain:dnsCurrentDomain,line:rec.line,type:rec.type,name:$("btDnsEditName").value.trim(),ttl:$("btDnsEditTtl").value};
        Object.assign(data,dnsCollectTypeFields("btDnsEditFields",rec.type));
        var msg=$("btDnsEditMsg");msg.style.display="none";
        this.disabled=true;var btn=this;
        post(data,function(r){
            btn.disabled=false;
            showMsg(msg,r.message||"Done",r.success);
            if(r.success) setTimeout(function(){overlay.remove();loadDnsRecords(dnsCurrentDomain);},600);
        });
    });
}

function openDnsDeleteConfirm(rec){
    if(!confirm("Delete this "+rec.type+" record for "+rec.name+"?")) return;
    post({action:"dns_delete_record",domain:dnsCurrentDomain,line:rec.line},function(r){
        if(r.success){
            loadDnsRecords(dnsCurrentDomain);
        } else {
            alert(r.message||"Failed to delete record");
        }
    });
}

function dnsRenderTypeFields(containerId,type,rec){
    var c=$(containerId);if(!c) return;
    var html="";
    switch(type){
        case "A":
            html='<div class="bt-field"><label>IPv4 Address</label><input type="text" class="dns-field" data-key="address" value="'+esc(rec.address||"")+'" placeholder="192.168.1.1"></div>';
            break;
        case "AAAA":
            html='<div class="bt-field"><label>IPv6 Address</label><input type="text" class="dns-field" data-key="address" value="'+esc(rec.address||"")+'" placeholder="2001:db8::1"></div>';
            break;
        case "CNAME":
            html='<div class="bt-field"><label>Target</label><input type="text" class="dns-field" data-key="cname" value="'+esc(rec.cname||"")+'" placeholder="target.example.com"></div>';
            break;
        case "MX":
            html='<div class="bt-field"><label>Priority</label><input type="number" class="dns-field" data-key="preference" value="'+(rec.preference||10)+'" min="0"></div>'
                +'<div class="bt-field"><label>Mail Server</label><input type="text" class="dns-field" data-key="exchange" value="'+esc(rec.exchange||"")+'" placeholder="mail.example.com"></div>';
            break;
        case "TXT":
            html='<div class="bt-field"><label>TXT Data</label><textarea class="dns-field" data-key="txtdata" rows="3" style="width:100%;padding:9px 12px;border:1px solid var(--border-color,#d1d5db);border-radius:8px;font-size:14px;color:var(--heading-color,#111827);background:var(--input-bg,#fff);resize:vertical;font-family:monospace;box-sizing:border-box">'+esc(rec.txtdata||"")+'</textarea></div>';
            break;
        case "SRV":
            html='<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">'
                +'<div class="bt-field"><label>Priority</label><input type="number" class="dns-field" data-key="priority" value="'+(rec.priority||0)+'" min="0"></div>'
                +'<div class="bt-field"><label>Weight</label><input type="number" class="dns-field" data-key="weight" value="'+(rec.weight||0)+'" min="0"></div>'
                +'<div class="bt-field"><label>Port</label><input type="number" class="dns-field" data-key="port" value="'+(rec.port||0)+'" min="0"></div>'
                +'</div>'
                +'<div class="bt-field"><label>Target</label><input type="text" class="dns-field" data-key="target" value="'+esc(rec.target||"")+'" placeholder="target.example.com"></div>';
            break;
        case "CAA":
            html='<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">'
                +'<div class="bt-field"><label>Flag</label><input type="number" class="dns-field" data-key="flag" value="'+(rec.flag||0)+'" min="0" max="255"></div>'
                +'<div class="bt-field"><label>Tag</label><select class="dns-field bt-select" data-key="tag"><option value="issue"'+(rec.tag==="issue"?" selected":"")+'>issue</option><option value="issuewild"'+(rec.tag==="issuewild"?" selected":"")+'>issuewild</option><option value="iodef"'+(rec.tag==="iodef"?" selected":"")+'>iodef</option></select></div>'
                +'</div>'
                +'<div class="bt-field"><label>Value</label><input type="text" class="dns-field" data-key="value" value="'+esc(rec.value||"")+'" placeholder="letsencrypt.org"></div>';
            break;
    }
    c.innerHTML=html;
}

function dnsCollectTypeFields(containerId,type){
    var c=$(containerId);if(!c) return {};
    var data={};
    c.querySelectorAll(".dns-field").forEach(function(f){
        var key=f.getAttribute("data-key");
        if(key) data[key]=f.value;
    });
    return data;
}

/* ─── Modal Bindings ─── */
function bindModals(){
    document.querySelectorAll(".bt-overlay").forEach(function(ov){
        ov.addEventListener("click",function(e){if(e.target===ov) ov.style.display="none";});
    });
    document.querySelectorAll("[data-close]").forEach(function(b){
        b.addEventListener("click",function(){var ov=this.closest(".bt-overlay");if(ov) ov.style.display="none";});
    });
    document.querySelectorAll("[data-toggle-pass]").forEach(function(b){
        b.addEventListener("click",function(){
            var inp=$(this.getAttribute("data-toggle-pass"));
            if(inp) inp.type=inp.type==="password"?"text":"password";
        });
    });
    document.querySelectorAll(".bt-copy").forEach(function(b){
        b.addEventListener("click",function(){doCopy(this.getAttribute("data-copy"),this);});
    });
}

/* ─── Boot ─── */
if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",init);
else init();

})();
BTSCRIPT;
}

function broodle_tools_shared_script()
{
    return '<script>' . broodle_tools_shared_script_raw() . '</script>';
}

/* ─── Upgrade Page List Layout ────────────────────────────── */

add_hook('ClientAreaHeadOutput', 1, function ($vars) {
    if (!broodle_tools_upgrade_list_enabled()) {
        return '';
    }

    // Only run on upgrade.php with type=package
    $filename = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
    if ($filename !== 'upgrade.php') {
        return '';
    }
    $type = $_GET['type'] ?? '';
    if ($type !== 'package') {
        return '';
    }

    $css = '
<style id="bt-upgrade-list">
/* ── Broodle: Upgrade page list layout ── */

/* Reset any grid/flex card layout Lagom2 applies to upgrade packages */
.upgrade-products,
.products-boxes,
.product-boxes,
.row:has(> [class*="col"] form[action*="upgrade"]),
.row:has(> [class*="col"] input[name="pid"]),
.content-padded .row:has(> div > .panel),
.main-content .row:has(> div > .product-box) {
    display: block !important;
}

/* Force full-width on each package column */
.upgrade-products > [class*="col"],
.products-boxes > [class*="col"],
.product-boxes > [class*="col"],
.row > [class*="col"]:has(form[action*="upgrade"]),
.row > [class*="col"]:has(input[name="pid"]) {
    width: 100% !important;
    max-width: 100% !important;
    flex: none !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-bottom: 0 !important;
}

/* ── List-row styling for each package card ── */
.bt-upgrade-list-row {
    display: flex !important;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: 8px;
    margin-bottom: 8px;
    background: var(--card-bg, #fff);
    transition: border-color 0.15s, box-shadow 0.15s;
    min-height: 0 !important;
    text-align: left !important;
}
.bt-upgrade-list-row:hover {
    border-color: var(--primary-color, #0a5ed3);
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}

/* Package info takes remaining space */
.bt-upgrade-list-row .bt-upg-info {
    flex: 1;
    min-width: 0;
}
.bt-upgrade-list-row .bt-upg-info .bt-upg-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--heading-color, #111827);
    margin: 0 0 2px;
}
.bt-upgrade-list-row .bt-upg-info .bt-upg-desc {
    font-size: 13px;
    color: var(--text-muted, #6b7280);
    margin: 0;
    line-height: 1.6;
}
.bt-upgrade-list-row .bt-upg-info .bt-upg-desc p {
    margin: 0 0 6px;
}
.bt-upgrade-list-row .bt-upg-info .bt-upg-desc p:last-child {
    margin-bottom: 0;
}
.bt-upgrade-list-row .bt-upg-info .bt-upg-desc ul,
.bt-upgrade-list-row .bt-upg-info .bt-upg-desc ol {
    margin: 4px 0 6px 18px;
    padding: 0;
}
.bt-upgrade-list-row .bt-upg-info .bt-upg-desc li {
    margin-bottom: 3px;
}
.bt-upgrade-list-row .bt-upg-info .bt-upg-desc strong,
.bt-upgrade-list-row .bt-upg-info .bt-upg-desc b {
    color: var(--heading-color, #374151);
    font-weight: 600;
}
.bt-upgrade-list-row .bt-upg-info .bt-upg-desc a {
    color: var(--primary-color, #0a5ed3);
    text-decoration: none;
}
.bt-upgrade-list-row .bt-upg-info .bt-upg-desc a:hover {
    text-decoration: underline;
}

/* Pricing + button area */
.bt-upgrade-list-row .bt-upg-action {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}
.bt-upgrade-list-row .bt-upg-action select {
    padding: 6px 10px;
    border: 1px solid var(--border-color, #d1d5db);
    border-radius: 6px;
    font-size: 13px;
    background: var(--input-bg, #fff);
    color: var(--heading-color, #111827);
    min-width: 160px;
}
.bt-upgrade-list-row .bt-upg-action .bt-upg-price {
    font-size: 14px;
    font-weight: 600;
    color: var(--heading-color, #111827);
    white-space: nowrap;
}
.bt-upgrade-list-row .bt-upg-action button,
.bt-upgrade-list-row .bt-upg-action input[type="submit"],
.bt-upgrade-list-row .bt-upg-action .btn {
    padding: 8px 18px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
}

/* Dark mode */
[data-theme="dark"] .bt-upgrade-list-row,
.dark-mode .bt-upgrade-list-row {
    border-color: var(--border-color, #374151);
    background: var(--card-bg, #1f2937);
}

/* Responsive */
@media (max-width: 640px) {
    .bt-upgrade-list-row {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    .bt-upgrade-list-row .bt-upg-action {
        flex-wrap: wrap;
        justify-content: flex-end;
    }
}
</style>';

    $js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Detect upgrade page forms
    var forms = document.querySelectorAll("form");
    var upgradeForms = [];
    forms.forEach(function(f) {
        var pidInput = f.querySelector("input[name=pid]");
        var typeInput = f.querySelector("input[name=type][value=package]");
        if (pidInput && typeInput) {
            upgradeForms.push(f);
        }
    });

    if (!upgradeForms.length) return;

    // Helper: strip price patterns from text/HTML
    function stripPrices(str) {
        if (!str) return str;
        // Remove patterns like $X.XX, $X.XX/mo, $X,XXX.XX/yr, €X.XX, £X.XX, etc.
        str = str.replace(/[\\$€£¥₹]\\s*[\\d,]+\\.?\\d*\\s*(\\/\\s*(mo|month|monthly|qtr|quarterly|yr|year|annually|semi-annually|biennially|triennially|one[- ]?time))?/gi, "");
        // Remove patterns like "X.XX USD", "X.XX EUR" etc.
        str = str.replace(/[\\d,]+\\.\\d{2}\\s*(USD|EUR|GBP|INR|AUD|CAD|NZD|SGD|HKD|JPY|CNY|BRL|MXN|ZAR|AED|SAR|KWD|BHD|OMR|QAR|TRY|RUB|PLN|CZK|HUF|SEK|NOK|DKK|CHF|ILS|THB|MYR|PHP|IDR|VND|KRW|TWD|PKR|BDT|LKR|NGN|KES|GHS|TZS|UGX|EGP|MAD|DZD|TND|LYD|JOD|IQD|AFN|MMK|KHR|LAK|MNT|NPR|BTN|MVR|SCR|MUR|BWP|NAD|SZL|LSL|ZMW|MWK|MZN|AOA|CDF|XOF|XAF|XPF|FJD|PGK|WST|TOP|VUV|SBD|KPW|ERN|ETB|DJF|SOS|SDG|SSP|GMD|SLL|GNF|LRD|CVE|STN|CUP|HTG|NIO|HNL|GTQ|BZD|JMD|TTD|BBD|BSD|KYD|BMD|AWG|ANG|SRD|GYD|FKP|SHP|GIP|XCD)\\b/gi, "");
        // Remove "Starting from", "From", "Price:" prefixes left orphaned
        str = str.replace(/(starting\\s+from|from|price\\s*:?)\\s*$/gi, "").trim();
        // Clean up double spaces and orphaned separators
        str = str.replace(/\\s*[\\-–—|]\\s*$/g, "").replace(/^\\s*[\\-–—|]\\s*/g, "").replace(/\\s{2,}/g, " ").trim();
        return str;
    }

    upgradeForms.forEach(function(form) {
        // Walk up to find the card/box container
        var container = form.closest("[class*=col]") || form.closest(".product-box") || form.closest(".panel") || form.closest("td") || form.parentElement;
        if (!container) return;

        // Extract package name
        var nameEl = container.querySelector("h3, h4, h5, .product-name, .panel-title, strong");
        var name = nameEl ? nameEl.textContent.trim() : "";

        // Extract description — prefer innerHTML to preserve HTML formatting
        var desc = "";
        // Strategy 1: explicit description elements (preserve HTML)
        var descEl = container.querySelector(".product-description, .product-desc, .product-info, .package-description, .card-text, .panel-body p, .product-details");
        if (descEl && descEl.textContent.trim() && descEl.textContent.trim() !== name) {
            desc = descEl.innerHTML.trim();
        }
        // Strategy 2: walk the container children outside the form for HTML content
        if (!desc) {
            var htmlParts = [];
            var children = container.childNodes;
            for (var ci = 0; ci < children.length; ci++) {
                var child = children[ci];
                if (form.contains(child) && child !== container) continue;
                if (nameEl && (child === nameEl || (child.contains && child.contains(nameEl)))) continue;
                if (child.nodeType === 1) {
                    if (child.tagName === "FORM") continue;
                    var h = child.innerHTML || child.textContent || "";
                    if (h.trim()) htmlParts.push(child.outerHTML || h);
                } else if (child.nodeType === 3) {
                    var t = child.textContent.trim();
                    if (t && t !== name) htmlParts.push(t);
                }
            }
            desc = htmlParts.join(" ").trim();
        }
        // Strategy 3: look for content after name element
        if (!desc && nameEl) {
            var sib = nameEl.nextSibling;
            var parts2 = [];
            while (sib) {
                if (sib.nodeType === 1 && sib.tagName === "FORM") break;
                if (sib.nodeType === 1 && sib.tagName === "BR") { sib = sib.nextSibling; continue; }
                if (sib.nodeType === 1) {
                    parts2.push(sib.outerHTML || sib.textContent || "");
                } else if (sib.nodeType === 3) {
                    var txt = sib.textContent.trim();
                    if (txt) parts2.push(txt);
                }
                sib = sib.nextSibling;
            }
            if (parts2.length) desc = parts2.join(" ").trim();
        }
        // Strategy 4: inside the form itself
        if (!desc) {
            var formChildren = form.childNodes;
            var parts3 = [];
            for (var fi = 0; fi < formChildren.length; fi++) {
                var fc = formChildren[fi];
                if (fc.nodeType === 1 && (fc.tagName === "INPUT" || fc.tagName === "SELECT" || fc.tagName === "BUTTON" || fc.classList.contains("form-group"))) continue;
                if (fc.nodeType === 1 && fc.tagName === "BR") continue;
                if (fc.nodeType === 1) {
                    parts3.push(fc.outerHTML || fc.textContent || "");
                } else {
                    var ftxt = (fc.textContent || "").trim();
                    if (ftxt && ftxt !== name) parts3.push(ftxt);
                }
            }
            if (parts3.length) desc = parts3.join(" ").trim();
        }

        // Strip prices from description
        desc = stripPrices(desc);

        // Build list row
        var row = document.createElement("div");
        row.className = "bt-upgrade-list-row";

        var infoDiv = document.createElement("div");
        infoDiv.className = "bt-upg-info";
        if (name) {
            var nameP = document.createElement("p");
            nameP.className = "bt-upg-name";
            nameP.textContent = name;
            infoDiv.appendChild(nameP);
        }
        if (desc) {
            var descP = document.createElement("p");
            descP.className = "bt-upg-desc";
            descP.innerHTML = desc;
            infoDiv.appendChild(descP);
        }

        var actionDiv = document.createElement("div");
        actionDiv.className = "bt-upg-action";

        // Move select (billing cycle) if present
        var select = form.querySelector("select[name=billingcycle]");
        if (select) {
            actionDiv.appendChild(select);
        }

        // Show static price for free/onetime
        var hiddenCycle = form.querySelector("input[type=hidden][name=billingcycle]");
        if (hiddenCycle) {
            // Find price text in the form
            var priceText = "";
            var formTexts = form.childNodes;
            for (var i = 0; i < formTexts.length; i++) {
                var n = formTexts[i];
                if (n.nodeType === 3 && n.textContent.trim()) {
                    priceText += n.textContent.trim() + " ";
                }
            }
            // Also check .form-group or parent for price text
            var fg = form.querySelector(".form-group");
            if (fg) {
                var fgTexts = fg.childNodes;
                for (var j = 0; j < fgTexts.length; j++) {
                    var m = fgTexts[j];
                    if (m.nodeType === 3 && m.textContent.trim()) {
                        priceText += m.textContent.trim() + " ";
                    }
                }
            }
            priceText = priceText.trim();
            if (priceText) {
                var priceSpan = document.createElement("span");
                priceSpan.className = "bt-upg-price";
                priceSpan.textContent = priceText;
                actionDiv.appendChild(priceSpan);
            }
            actionDiv.appendChild(hiddenCycle);
        }

        // Move submit button
        var submit = form.querySelector("input[type=submit], button[type=submit]");
        if (submit) {
            actionDiv.appendChild(submit);
        }

        // Move hidden inputs into a hidden container inside the row
        var hiddens = form.querySelectorAll("input[type=hidden]");
        var hiddenWrap = document.createElement("div");
        hiddenWrap.style.display = "none";
        hiddens.forEach(function(h) { hiddenWrap.appendChild(h); });

        row.appendChild(infoDiv);
        row.appendChild(actionDiv);

        // Replace form content
        form.innerHTML = "";
        form.appendChild(hiddenWrap);
        form.appendChild(row);
        form.style.margin = "0";
        form.style.padding = "0";

        // Reset container styling
        if (container.classList) {
            container.style.padding = "0";
            container.style.margin = "0";
            container.style.border = "none";
            container.style.boxShadow = "none";
            container.style.background = "none";
        }
    });

    // Also hide any Lagom2 product-box specific wrappers
    var boxes = document.querySelectorAll(".product-box, .panel.product-panel");
    boxes.forEach(function(box) {
        if (box.querySelector(".bt-upgrade-list-row")) {
            box.style.border = "none";
            box.style.boxShadow = "none";
            box.style.background = "none";
            box.style.padding = "0";
            box.style.margin = "0";
        }
    });
});
</script>';

    return $css . $js;
});
