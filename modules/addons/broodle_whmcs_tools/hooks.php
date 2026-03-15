<?php
/**
 * Broodle WHMCS Tools — Hooks
 *
 * @package    BroodleWHMCSTools
 * @author     Broodle
 * @link       https://broodle.host
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

use WHMCS\Database\Capsule;
use WHMCS\View\Menu\Item as MenuItem;

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

add_hook('ClientAreaProductDetailsOutput', 1, function ($vars) {
    broodle_tools_ensure_defaults();
    $serviceId = broodle_tools_get_service_id($vars);
    if (!$serviceId) return '';
    $cpData = broodle_tools_get_cpanel_service($serviceId);
    if (!$cpData) return '';

    // Gather all data
    $nsData = broodle_tools_ns_enabled() ? broodle_tools_get_ns_for_service($serviceId) : ['ns' => [], 'ip' => ''];
    $emails = broodle_tools_email_enabled() ? broodle_tools_get_emails($serviceId) : [];
    $domains = broodle_tools_domain_enabled() ? broodle_tools_get_domains_detailed($serviceId) : null;
    $wpEnabled = broodle_tools_wp_enabled();
    $dbEnabled = broodle_tools_db_enabled();

    // JSON data for JS
    $jsData = json_encode([
        'serviceId' => $serviceId,
        'ns' => $nsData,
        'emails' => $emails,
        'domains' => $domains,
        'wpEnabled' => $wpEnabled,
        'dbEnabled' => $dbEnabled,
        'nsEnabled' => broodle_tools_ns_enabled(),
        'emailEnabled' => broodle_tools_email_enabled(),
        'domainEnabled' => broodle_tools_domain_enabled(),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

    $output = '<div id="bt-data" style="display:none" data-config=\'' . $jsData . '\'></div>';
    $output .= broodle_tools_modals();
    $output .= broodle_tools_wp_detail_modal();
    $output .= broodle_tools_shared_styles();
    $output .= broodle_tools_shared_script();
    return $output;
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
    return broodle_tools_css_hide() . broodle_tools_css_tabs() . broodle_tools_css_overview() . broodle_tools_css_cards() . broodle_tools_css_modals() . broodle_tools_css_wp() . broodle_tools_css_dark() . broodle_tools_css_responsive();
}

function broodle_tools_css_hide()
{
    return '<style>
.product-details-tab-container,#Primary_Sidebar-productdetails_addons_and_extras,.quick-create-email,.quick-create-email-section,[class*="quick-create-email"],.quick-shortcut-container,.quick-shortcut,.module-quick-create-email,#tabAddonsExtras,.addons-and-extras-section,[id*="addons_and_extras"],[class*="addons-extras"],.product-details-tab-container+.tab-content,.quick-create-section,.module-quick-shortcuts,.quick-shortcuts-container,.quick-shortcuts,.sidebar-shortcuts,.sidebar-quick-create{display:none!important}
.panel-body .quick-create-email,.panel-body .quick-create-email-section,.panel-body [class*="quick-create"],.panel-body .quick-shortcut,.panel-body .quick-shortcuts{display:none!important}
.sidebar-right .quick-create-email,.sidebar-right [class*="quick-create"],.sidebar-right .quick-shortcut{display:none!important}
#Primary_Sidebar .panel:has([class*="quick-create"]),#Primary_Sidebar .panel:has([class*="addons_and_extras"]),#Primary_Sidebar .panel:has([class*="addons-extras"]){display:none!important}
#cPanelQuickEmailPanel,#cPanelExtrasPurchasePanel{display:none!important}
.bt-hidden-section{display:none!important}
</style>';
}

function broodle_tools_css_tabs()
{
    return '<style>
.bt-wrap{margin-top:15px;font-family:inherit}
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
.bt-addons-section{margin-top:20px}
.bt-addon-wrap{position:relative;padding:0 0 6px}
.bt-addon-scroll{display:flex;gap:0;overflow-x:auto;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:none;padding:0}
.bt-addon-scroll::-webkit-scrollbar{display:none}
.bt-addon-page{min-width:100%;flex-shrink:0;scroll-snap-align:start;display:grid;grid-template-columns:1fr 1fr;gap:0;padding:4px 8px}
.bt-addon-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;transition:background .12s}
.bt-addon-item:hover{background:var(--input-bg,#f5f7fa)}
.bt-addon-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.bt-addon-icon.addon{background:rgba(124,58,237,.08);color:#7c3aed}
.bt-addon-icon.upgrade{background:rgba(5,150,105,.08);color:#059669}
.bt-addon-name{font-size:13px;font-weight:500;color:var(--heading-color,#111827);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bt-addon-btn{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);color:var(--heading-color,#374151);transition:all .12s;white-space:nowrap;text-decoration:none;flex-shrink:0}
.bt-addon-btn:hover{border-color:#0a5ed3;color:#0a5ed3;background:rgba(10,94,211,.04);text-decoration:none}
.bt-addon-dots{display:flex;justify-content:center;gap:6px;padding:8px 0 2px}
.bt-addon-dot{width:6px;height:6px;border-radius:50%;background:var(--border-color,#d1d5db);border:none;padding:0;cursor:pointer;transition:all .2s}
.bt-addon-dot.active{background:#0a5ed3;width:16px;border-radius:3px}
.bt-addon-nav{position:absolute;top:50%;transform:translateY(-50%);width:28px;height:28px;border-radius:50%;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);color:var(--text-muted,#6b7280);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;z-index:2;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.bt-addon-nav:hover{border-color:#0a5ed3;color:#0a5ed3}
.bt-addon-nav.prev{left:-6px}
.bt-addon-nav.next{right:-6px}
.bt-addon-nav.hidden{opacity:0;pointer-events:none}
.bt-addon-info-btn{width:22px;height:22px;border-radius:50%;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);color:var(--text-muted,#9ca3af);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;transition:all .12s;position:relative}
.bt-addon-info-btn:hover{border-color:#0a5ed3;color:#0a5ed3}
.bt-addon-info-btn.loading{opacity:.5;pointer-events:none}
.bt-addon-tooltip{position:absolute;bottom:calc(100% + 8px);left:50%;transform:translateX(-50%);width:260px;padding:10px 12px;background:var(--heading-color,#1f2937);color:#f3f4f6;font-size:11px;line-height:1.5;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.2);z-index:100;display:none;pointer-events:none;word-wrap:break-word}
.bt-addon-tooltip::after{content:"";position:absolute;top:100%;left:50%;transform:translateX(-50%);border:5px solid transparent;border-top-color:var(--heading-color,#1f2937)}
.bt-addon-info-btn:hover .bt-addon-tooltip,.bt-addon-info-btn.show-tip .bt-addon-tooltip{display:block}
.bt-addon-tooltip.right{left:auto;right:0;transform:none}
.bt-addon-tooltip.right::after{left:auto;right:12px;transform:none}
.bt-addon-tooltip.left{left:0;right:auto;transform:none}
.bt-addon-tooltip.left::after{left:12px;right:auto;transform:none}
@media(max-width:600px){.bt-addon-page{grid-template-columns:1fr}.bt-addon-tooltip{width:200px}}
</style>';
}

function broodle_tools_css_modals()
{
    return '<style>
.bt-overlay{position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:20px;animation:btFadeIn .2s}
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
.bt-btn-primary:disabled,.bt-btn-danger:disabled,.bt-btn-add:disabled{opacity:.5;cursor:not-allowed}
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
</style>';
}

function broodle_tools_css_dark()
{
    return '<style>
[data-theme="dark"] .bt-card,.dark-mode .bt-card{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-ov-card,.dark-mode .bt-ov-card{background:var(--input-bg,#111827);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-row:hover,.dark-mode .bt-row:hover{background:var(--input-bg,#111827)}
[data-theme="dark"] .bt-row-btn,.dark-mode .bt-row-btn{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-copy,.dark-mode .bt-copy{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-modal,.dark-mode .bt-modal{background:var(--card-bg,#1f2937)}
[data-theme="dark"] .bt-field input,[data-theme="dark"] .bt-field select,.dark-mode .bt-field input,.dark-mode .bt-field select{background:var(--input-bg,#111827);border-color:var(--border-color,#374151);color:var(--heading-color,#f3f4f6)}
[data-theme="dark"] .bt-input-group,.dark-mode .bt-input-group{border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-at,[data-theme="dark"] .bt-prefix,.dark-mode .bt-at,.dark-mode .bt-prefix{background:var(--input-bg,#111827);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-upgrades,.dark-mode .bt-upgrades{background:var(--input-bg,#111827);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-addon-item:hover,.dark-mode .bt-addon-item:hover{background:var(--input-bg,#111827)}
[data-theme="dark"] .bt-addon-btn,.dark-mode .bt-addon-btn{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-addon-nav,.dark-mode .bt-addon-nav{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-addon-info-btn,.dark-mode .bt-addon-info-btn{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bt-addon-tooltip,.dark-mode .bt-addon-tooltip{background:#111827;color:#e5e7eb}
[data-theme="dark"] .bt-addon-tooltip::after,.dark-mode .bt-addon-tooltip::after{border-top-color:#111827}
[data-theme="dark"] .bt-btn-outline,.dark-mode .bt-btn-outline{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bwp-detail-panel,.dark-mode .bwp-detail-panel{background:var(--card-bg,#1f2937)}
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
</style>';
}

/* ─── Shared Script ───────────────────────────────────────── */

function broodle_tools_shared_script()
{
    return '
<script>
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
    if(n.indexOf("wordpress")!==-1||n.indexOf("wp ")!==-1) return wpSvg16;
    if(n.indexOf("site builder")!==-1||n.indexOf("sitebuilder")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x273\\x27 y=\\x273\\x27 width=\\x2718\\x27 height=\\x2718\\x27 rx=\\x272\\x27/><path d=\\x27M3 9h18M9 21V9\\x27/></svg>";
    if(n.indexOf("ssl")!==-1||n.indexOf("certificate")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x273\\x27 y=\\x2711\\x27 width=\\x2718\\x27 height=\\x2711\\x27 rx=\\x272\\x27/><path d=\\x27M7 11V7a5 5 0 0 1 10 0v4\\x27/></svg>";
    if(n.indexOf("email")!==-1||n.indexOf("mail")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x272\\x27 y=\\x274\\x27 width=\\x2720\\x27 height=\\x2716\\x27 rx=\\x272\\x27/><path d=\\x27m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7\\x27/></svg>";
    if(n.indexOf("store")!==-1||n.indexOf("ecommerce")!==-1||n.indexOf("woocommerce")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><circle cx=\\x279\\x27 cy=\\x2721\\x27 r=\\x271\\x27/><circle cx=\\x2720\\x27 cy=\\x2721\\x27 r=\\x271\\x27/><path d=\\x271 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6\\x27/></svg>";
    return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><path d=\\x27M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z\\x27/></svg>";
}
function btUpgradeIcon(name){
    var n=name.toLowerCase();
    if(n.indexOf("ram")!==-1||n.indexOf("memory")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x272\\x27 y=\\x276\\x27 width=\\x2720\\x27 height=\\x2712\\x27 rx=\\x272\\x27/><path d=\\x276 6V4\\x27/><path d=\\x2710 6V4\\x27/><path d=\\x2714 6V4\\x27/><path d=\\x2718 6V4\\x27/></svg>";
    if(n.indexOf("backup")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><path d=\\x27M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\\x27/><polyline points=\\x277 10 12 15 17 10\\x27/><line x1=\\x2712\\x27 y1=\\x2715\\x27 x2=\\x2712\\x27 y2=\\x273\\x27/></svg>";
    if(n.indexOf("cpu")!==-1||n.indexOf("core")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x274\\x27 y=\\x274\\x27 width=\\x2716\\x27 height=\\x2716\\x27 rx=\\x272\\x27/><rect x=\\x279\\x27 y=\\x279\\x27 width=\\x276\\x27 height=\\x276\\x27/><path d=\\x272 9h2M2 15h2M20 9h2M20 15h2M9 2v2M15 2v2M9 20v2M15 20v2\\x27/></svg>";
    if(n.indexOf("disk")!==-1||n.indexOf("storage")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><ellipse cx=\\x2712\\x27 cy=\\x275\\x27 rx=\\x279\\x27 ry=\\x273\\x27/><path d=\\x27M21 12c0 1.66-4 3-9 3s-9-1.34-9-3\\x27/><path d=\\x27M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5\\x27/></svg>";
    return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><polyline points=\\x2723 6 13.5 15.5 8.5 10.5 1 18\\x27/><polyline points=\\x2717 6 23 6 23 12\\x27/></svg>";
}

/* ─── Init ─── */
function init(){
    var dataEl=$("bt-data");
    if(!dataEl) return;
    try{C=JSON.parse(dataEl.getAttribute("data-config"));}catch(e){return;}
    hideDefaultTabs();
    buildTabs();
    bindModals();
}

function hideDefaultTabs(){
    var selectors=["ul.panel-tabs.nav.nav-tabs",".product-details-tab-container",".section-body > ul.nav.nav-tabs",".panel > ul.nav.nav-tabs"];
    selectors.forEach(function(sel){
        document.querySelectorAll(sel).forEach(function(el){
            el.style.display="none";
            var sib=el.nextElementSibling;
            while(sib){if(sib.classList&&(sib.classList.contains("tab-content")||sib.classList.contains("product-details-tab-container"))){sib.style.display="none";break;}sib=sib.nextElementSibling;}
        });
    });
    ["billingInfo","tabOverview","domainInfo","tabAddons"].forEach(function(id){var el=$(id);if(el)el.style.display="none";});
    var panelTabs=document.querySelector("ul.panel-tabs");
    if(panelTabs){var panel=panelTabs.closest(".panel");if(panel) panel.style.display="none";}
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
    var target=document.querySelector(".panel");
    if(!target) target=document.querySelector(".section-body");
    if(!target) return;
    var hiddenPanel=document.querySelector("ul.panel-tabs");
    var insertAfter=hiddenPanel?hiddenPanel.closest(".panel"):null;
    if(!insertAfter) insertAfter=target;

    var wrap=document.createElement("div");
    wrap.className="bt-wrap";wrap.id="bt-wrap";

    // WordPress icon — official WP logo
    var wpIcon="<svg viewBox=\\x270 0 16 16\\x27 fill=\\x27currentColor\\x27><path d=\\x27M12.633 7.653c0-.848-.305-1.435-.566-1.892l-.08-.13c-.317-.51-.594-.958-.594-1.48 0-.63.478-1.218 1.152-1.218q.03 0 .058.003l.031.003A6.84 6.84 0 0 0 8 1.137 6.86 6.86 0 0 0 2.266 4.23c.16.005.313.009.442.009.717 0 1.828-.087 1.828-.087.37-.022.414.521.044.565 0 0-.371.044-.785.065l2.5 7.434 1.5-4.506-1.07-2.929c-.369-.022-.719-.065-.719-.065-.37-.022-.326-.588.043-.566 0 0 1.134.087 1.808.087.718 0 1.83-.087 1.83-.087.37-.022.413.522.043.566 0 0-.372.043-.785.065l2.48 7.377.684-2.287.054-.173c.27-.86.469-1.495.469-2.046zM1.137 8a6.86 6.86 0 0 0 3.868 6.176L1.73 5.206A6.8 6.8 0 0 0 1.137 8\\x27/><path d=\\x27M6.061 14.583 8.121 8.6l2.109 5.78q.02.05.049.094a6.85 6.85 0 0 1-4.218.109m7.96-9.876q.046.328.047.706c0 .696-.13 1.479-.522 2.458l-2.096 6.06a6.86 6.86 0 0 0 2.572-9.224z\\x27/><path fill-rule=\\x27evenodd\\x27 d=\\x27M0 8c0-4.411 3.589-8 8-8s8 3.589 8 8-3.59 8-8 8-8-3.589-8-8m.367 0c0 4.209 3.424 7.633 7.633 7.633S15.632 12.209 15.632 8C15.632 3.79 12.208.367 8 .367 3.79.367.367 3.79.367 8\\x27/></svg>";

    var tabs=[
        {id:"overview",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x273\\x27 y=\\x273\\x27 width=\\x2718\\x27 height=\\x2718\\x27 rx=\\x272\\x27/><path d=\\x27M3 9h18M9 21V9\\x27/></svg>",label:"Overview"},
        {id:"domains",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><circle cx=\\x2712\\x27 cy=\\x2712\\x27 r=\\x2710\\x27/><line x1=\\x272\\x27 y1=\\x2712\\x27 x2=\\x2722\\x27 y2=\\x2712\\x27/><path d=\\x27M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z\\x27/></svg>",label:"Domains",check:"domainEnabled"},
        {id:"email",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x272\\x27 y=\\x274\\x27 width=\\x2720\\x27 height=\\x2716\\x27 rx=\\x272\\x27/><path d=\\x27m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7\\x27/></svg>",label:"Email Accounts",check:"emailEnabled"},
        {id:"databases",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><ellipse cx=\\x2712\\x27 cy=\\x275\\x27 rx=\\x279\\x27 ry=\\x273\\x27/><path d=\\x27M21 12c0 1.66-4 3-9 3s-9-1.34-9-3\\x27/><path d=\\x27M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5\\x27/></svg>",label:"Databases",check:"dbEnabled"},
        {id:"wordpress",icon:wpIcon,label:"WordPress",check:"wpEnabled"}
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
            if(t.id==="databases"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadDatabases();}
            if(t.id==="wordpress"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadWpInstances();}
        });
        nav.appendChild(btn);

        var pane=document.createElement("div");
        pane.className="bt-tab-pane"+(firstTab?" active":"");
        pane.id="bt-pane-"+t.id;
        panes.appendChild(pane);
        firstTab=false;
    });

    wrap.appendChild(nav);wrap.appendChild(panes);
    if(insertAfter&&insertAfter.parentNode) insertAfter.parentNode.insertBefore(wrap,insertAfter.nextSibling);
    else document.querySelector(".main-content,.content-padded,.section-body,.container").appendChild(wrap);

    buildOverviewPane();
    if(C.domainEnabled) buildDomainsPane();
    if(C.emailEnabled) buildEmailPane();
    if(C.dbEnabled) buildDatabasesPane();
    if(C.wpEnabled) buildWpPane();
}

/* ─── Overview Pane (improved) ─── */
function buildOverviewPane(){
    var pane=$("bt-pane-overview");if(!pane) return;
    var pairs=[];
    var billingEl=$("billingInfo")||$("tabOverview");
    if(billingEl){
        billingEl.querySelectorAll(".col-sm-6.col-md-3.m-b-2x,.col-sm-6.col-md-3").forEach(function(col){
            var lbl=col.querySelector(".text-faded.text-small,.text-faded");if(!lbl) return;
            var label=lbl.textContent.trim().replace(/:$/,"");
            var sib=lbl.nextElementSibling;var val=sib?sib.innerHTML.trim():"";
            if(!val){var c=col.cloneNode(true);var l2=c.querySelector(".text-faded");if(l2)l2.remove();val=c.innerHTML.trim();}
            if(label&&val) pairs.push({label:label,value:val});
        });
        if(!pairs.length){var rc=billingEl.querySelector(".col-md-6.text-center");
            if(rc){rc.querySelectorAll("h4").forEach(function(h4){
                var label=h4.textContent.trim().replace(/:$/,"");var val="";var s=h4.nextSibling;
                while(s&&!(s.nodeType===1&&s.tagName==="H4")){if(s.nodeType===3)val+=s.textContent.trim();else if(s.nodeType===1)val+=s.outerHTML;s=s.nextSibling;}
                val=val.trim();if(label&&val)pairs.push({label:label,value:val});
            });}}
        if(!pairs.length){billingEl.querySelectorAll(".row").forEach(function(r){
            var l=r.querySelector(".col-sm-5,.col-md-5");var v=r.querySelector(".col-sm-7,.col-md-7");
            if(l&&v)pairs.push({label:l.textContent.trim().replace(/:$/,""),value:v.innerHTML.trim()});
        });}
        billingEl.style.display="none";
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

    // Nameservers
    if(C.nsEnabled&&C.ns&&C.ns.ns&&C.ns.ns.length){
        html+="<div class=\"bt-ns-section\"><div class=\"bt-card\"><div class=\"bt-card-head\"><div class=\"bt-card-head-left\"><div class=\"bt-icon-circle\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"2\" y1=\"12\" x2=\"22\" y2=\"12\"/><path d=\"M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z\"/></svg></div><div><h5>Nameservers</h5><p>Point your domain to these nameservers</p></div></div></div><div class=\"bt-list\">";
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
        var perPage=window.innerWidth<=600?4:6;
        var pages=[];for(var pi=0;pi<allItems.length;pi+=perPage){pages.push(allItems.slice(pi,pi+perPage));}
        html+="<div class=\"bt-addons-section\"><div class=\"bt-card\"><div class=\"bt-card-head\"><div class=\"bt-card-head-left\"><div class=\"bt-icon-circle\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z\"/></svg></div><div><h5>Addons &amp; Upgrades</h5><p>"+allItems.length+" available</p></div></div></div><div class=\"bt-addon-wrap\"><button type=\"button\" class=\"bt-addon-nav prev"+(pages.length<=1?" hidden":"")+"\" id=\"btAddonPrev\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"15 18 9 12 15 6\"/></svg></button><button type=\"button\" class=\"bt-addon-nav next"+(pages.length<=1?" hidden":"")+"\" id=\"btAddonNext\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"9 18 15 12 9 6\"/></svg></button><div class=\"bt-addon-scroll\" id=\"btAddonScroll\">";
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
                html+="<div class=\"bt-addon-item\"><div class=\"bt-addon-icon "+iconCls+"\">"+icon+"</div><span class=\"bt-addon-name\" title=\""+esc(item.name)+"\">"+esc(item.name)+"</span><button type=\"button\" class=\"bt-addon-info-btn\" data-aid=\""+(item.aid||"")+"\" data-tip=\"\"><svg width=\"12\" height=\"12\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"12\" y1=\"16\" x2=\"12\" y2=\"12\"/><line x1=\"12\" y1=\"8\" x2=\"12.01\" y2=\"8\"/></svg><div class=\"bt-addon-tooltip\">Loading...</div></button>"+btnHtml+"</div>";
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
        // Tooltip: fetch description on hover/click
        var tipCache={};
        pane.querySelectorAll(".bt-addon-info-btn[data-aid]").forEach(function(btn){
            function loadTip(){
                var aid=btn.getAttribute("data-aid");
                var tip=btn.querySelector(".bt-addon-tooltip");
                if(!aid||!tip) return;
                if(tipCache[aid]){tip.textContent=tipCache[aid];return;}
                btn.classList.add("loading");
                post({action:"get_addon_description",addon_id:aid},function(r){
                    btn.classList.remove("loading");
                    var desc=(r.success&&r.description)?r.description:"No description available";
                    tipCache[aid]=desc;tip.textContent=desc;
                });
            }
            btn.addEventListener("mouseenter",loadTip);
            btn.addEventListener("click",function(e){e.stopPropagation();loadTip();btn.classList.toggle("show-tip");});
        });
        // Close tooltips on outside click
        document.addEventListener("click",function(){pane.querySelectorAll(".bt-addon-info-btn.show-tip").forEach(function(b){b.classList.remove("show-tip");});});
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
</script>';
}
