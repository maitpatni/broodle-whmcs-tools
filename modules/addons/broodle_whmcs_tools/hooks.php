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
    return '
<style>
/* ─── Hide ALL default WHMCS product detail tabs ─── */
.product-details-tab-container,
#Primary_Sidebar-productdetails_addons_and_extras,
.quick-create-email,.quick-create-email-section,[class*="quick-create-email"]{display:none!important}

/* ─── Broodle Tab System ─── */
.bt-wrap{margin-top:15px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
.bt-tabs-nav{display:flex;gap:0;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;border-bottom:2px solid var(--border-color,#e5e7eb);padding:0;margin:0 0 0}
.bt-tabs-nav::-webkit-scrollbar{display:none}
.bt-tab-btn{display:inline-flex;align-items:center;gap:7px;padding:12px 18px;font-size:13px;font-weight:600;color:var(--text-muted,#6b7280);cursor:pointer;border:none;background:none;white-space:nowrap;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s,border-color .15s;flex-shrink:0}
.bt-tab-btn:hover{color:var(--heading-color,#111827)}
.bt-tab-btn.active{color:#0a5ed3;border-bottom-color:#0a5ed3}
.bt-tab-btn svg{width:16px;height:16px;flex-shrink:0}
.bt-tab-pane{display:none;padding:20px 0 0}
.bt-tab-pane.active{display:block}

/* ─── Overview Grid ─── */
.bt-ov-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.bt-ov-card{background:var(--input-bg,#f8fafc);border:1px solid var(--border-color,#e5e7eb);border-radius:10px;padding:16px 18px;transition:border-color .15s,box-shadow .15s}
.bt-ov-card:hover{border-color:rgba(10,94,211,.25);box-shadow:0 2px 8px rgba(10,94,211,.06)}
.bt-ov-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted,#9ca3af);margin:0 0 6px}
.bt-ov-value{font-size:14px;font-weight:600;color:var(--heading-color,#111827);margin:0;word-break:break-word}
.bt-ov-value a{color:#0a5ed3;text-decoration:none}
.bt-ov-value .label,.bt-ov-value .badge{font-size:12px;padding:3px 10px;border-radius:6px;font-weight:600}
@media(max-width:768px){.bt-ov-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.bt-ov-grid{grid-template-columns:1fr}}

/* ─── Section Cards ─── */
.bt-card{background:var(--card-bg,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:12px;overflow:hidden}
.bt-card-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border-color,#f3f4f6)}
.bt-card-head-left{display:flex;align-items:center;gap:12px}
.bt-icon-circle{width:36px;height:36px;background:#0a5ed3;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0}
.bt-card-head h5{margin:0;font-size:15px;font-weight:600;color:var(--heading-color,#111827)}
.bt-card-head p{margin:2px 0 0;font-size:12px;color:var(--text-muted,#6b7280)}
.bt-card-head-right{display:flex;gap:8px}
.bt-list{padding:6px 8px}

/* ─── Rows ─── */
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
.bt-row-name{font-size:14px;font-weight:600;color:var(--heading-color,#111827);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
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
.bt-row-btn:hover{bord
er-color:#0a5ed3;color:#0a5ed3}
.bt-row-btn.login{color:#0a5ed3}.bt-row-btn.login:hover{background:rgba(10,94,211,.06);border-color:#0a5ed3}
.bt-row-btn.visit{color:#0a5ed3}.bt-row-btn.visit:hover{background:rgba(10,94,211,.06);border-color:#0a5ed3;text-decoration:none;color:#0a5ed3}
.bt-row-btn.pass{color:#d97706}.bt-row-btn.pass:hover{background:rgba(217,119,6,.06);border-color:#d97706}
.bt-row-btn.del{color:#ef4444}.bt-row-btn.del:hover{background:rgba(239,68,68,.06);border-color:#ef4444}
.bt-copy{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid var(--border-color,#e5e7eb);border-radius:7px;background:var(--card-bg,#fff);color:var(--text-muted,#9ca3af);cursor:pointer;transition:all .15s;flex-shrink:0}
.bt-copy:hover{color:#0a5ed3;border-color:#0a5ed3}
.bt-copy.copied{color:#fff;background:#059669;border-color:#059669}
.bt-empty{padding:30px 22px;text-align:center;color:var(--text-muted,#9ca3af);font-size:14px;display:flex;flex-direction:column;align-items:center;gap:10px}
.bt-empty svg{opacity:.4}

/* ─── Buttons ─── */
.bt-btn-add{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:#0a5ed3;color:#fff;transition:background .15s}
.bt-btn-add:hover{background:#0950b3}
.bt-btn-outline{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#d1d5db);background:var(--card-bg,#fff);color:var(--heading-color,#374151);transition:all .15s}
.bt-btn-outline:hover{border-color:#0a5ed3;color:#0a5ed3;background:rgba(10,94,211,.04)}

/* ─── Modals ─── */
.bt-overlay{position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:20px;animation:btFadeIn .2s}
@keyframes btFadeIn{from{opacity:0}to{opacity:1}}
.bt-modal{background:var(--card-bg,#fff);border-radius:14px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:btSlideUp .25s}
.bt-modal-sm{max-width:380px}
@keyframes btSlideUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.bt-modal-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border-color,#f3f4f6)}
.bt-modal-head h5{margin:0;font-size:16px;font-weight:600;color:var(--heading-color,#111827)}
.bt-modal-close{width:30px;height:30px;display:flex;align-items:center;justify-content:center;border:none;background:none;font-size:20px;color:var(--text-muted,#9ca3af);cursor:pointer;border-radius:6px}
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

/* ─── Loading / Spinner ─── */
.bt-loading{padding:40px 22px;text-align:center;color:var(--text-muted,#9ca3af);font-size:14px;display:flex;flex-direction:column;align-items:center;gap:12px}
.bt-spinner{width:28px;height:28px;border:3px solid var(--border-color,#e5e7eb);border-top-color:#0a5ed3;border-radius:50%;animation:btSpin .7s linear infinite}
@keyframes btSpin{to{transform:rotate(360deg)}}

/* ─── Nameservers in Overview ─── */
.bt-ns-section{margin-top:20px}

/* ─── Upgrades in Overview ─── */
.bt-upgrades{margin-top:20px;padding:18px 20px;background:var(--input-bg,#f8fafc);border:1px solid var(--border-color,#e5e7eb);border-radius:12px}
.bt-upgrades h4{margin:0 0 14px;font-size:14px;font-weight:700;color:var(--heading-color,#111827);display:flex;align-items:center;gap:8px}
.bt-upgrades h4 svg{color:#0a5ed3}
.bt-upgrades .panel,.bt-upgrades .card{border:none;box-shadow:none;margin:0;background:transparent}
.bt-upgrades .panel-heading,.bt-upgrades .card-header{display:none}
.bt-upgrades .panel-body,.bt-upgrades .card-body{padding:0}

/* ─── WP Toolkit ─── */
.bwp-detail-panel{width:100%;max-width:900px;max-height:90vh;background:var(--card-bg,#fff);border-radius:16px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,.25);animation:btSlideUp .3s}
.bwp-detail-head{display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid var(--border-color,#f3f4f6);flex-shrink:0}
.bwp-detail-head h5{flex:1;margin:0;font-size:14px;font-weight:700;color:var(--heading-color,#111827);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bwp-detail-tabs{display:flex;gap:0;padding:0 20px;border-bottom:1px solid var(--border-color,#f3f4f6);flex-shrink:0;overflow-x:auto}
.bwp-tab{padding:10px 14px;font-size:12px;font-weight:600;color:var(--text-muted,#6b7280);cursor:pointer;border:none;background:none;border-bottom:2px solid transparent;transition:all .15s;white-space:nowrap}
.bwp-tab:hover{color:var(--heading-color,#111827)}
.bwp-tab.active{color:#0a5ed3;border-bottom-color:#0a5ed3}
.bwp-detail-body{flex:1;overflow-y:auto;padding:0}
.bwp-tab-content{display:none;padding:18px 20px}
.bwp-tab-content.active{display:block}
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
.bwp-item-row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border-color,#f3f4f6)}
.bwp-item-row:last-child{border-bottom:none}
.bwp-item-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px}
.bwp-item-icon.plugin{background:rgba(10,94,211,.08);color:#0a5ed3}
.bwp-item-icon.theme{background:rgba(124,58,237,.08);color:#7c3aed}
.bwp-item-icon-img{width:40px;height:40px;border-radius:10px;overflow:hidden;flex-shrink:0}
.bwp-item-icon-img img{width:100%;height:100%;object-fit:cover;border-radius:10px}
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
.bwp-tab-summary{display:flex;gap:16px;padding:12px 16px;background:var(--input-bg,#f9fafb);border-radius:10px;margin-bottom:16px;border:1px solid var(--border-color,#f3f4f6)}
.bwp-tab-stat{font-size:13px;color:var(--text-muted,#6b7280);display:flex;align-items:center;gap:5px}
.bwp-tab-stat-num{font-weight:700;font-size:15px}
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
.bwp-security-item{display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid var(--border-color,#f3f4f6)}
.bwp-security-item:last-child{border-bottom:none}
.bwp-sec-icon{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.bwp-sec-icon.ok{background:rgba(5,150,101,.08);color:#059669}
.bwp-sec-icon.warning{background:rgba(217,119,6,.08);color:#d97706}
.bwp-sec-icon.danger{background:rgba(239,68,68,.08);color:#ef4444}
.bwp-sec-info{flex:1}
.bwp-sec-label{font-size:13px;font-weight:600;margin:0}
.bwp-sec-detail{font-size:12px;color:var(--text-muted,#6b7280);margin:2px 0 0}
.bwp-sec-value{font-size:12px;font-weight:600;flex-shrink:0}
.bwp-sec-value.ok{color:#059669}.bwp-sec-value.warning{color:#d97706}.bwp-sec-value.danger{color:#ef4444}
.bwp-msg{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:12px}
.bwp-msg.success{background:rgba(5,150,101,.08);color:#059669}
.bwp-msg.error{background:rgba(239,68,68,.08);color:#ef4444}
.bwp-msg.info{background:rgba(10,94,211,.08);color:#0a5ed3}

/* ─── Dark Mode ─── */
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
[data-theme="dark"] .bt-btn-outline,.dark-mode .bt-btn-outline{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bwp-detail-panel,.dark-mode .bwp-detail-panel{background:var(--card-bg,#1f2937)}

/* ─── Responsive ─── */
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
var C={}; // config
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

/* ─── Init ─── */
function init(){
    var dataEl=$("bt-data");
    if(!dataEl) return;
    try{C=JSON.parse(dataEl.getAttribute("data-config"));}catch(e){return;}

    // Hide ALL default WHMCS product detail tabs
    hideDefaultTabs();

    // Build our tab system
    buildTabs();

    // Bind modals
    bindModals();
}

function hideDefaultTabs(){
    // Hide the entire default tab nav + content
    var selectors=[
        "ul.panel-tabs.nav.nav-tabs",
        ".product-details-tab-container",
        ".section-body > ul.nav.nav-tabs",
        ".panel > ul.nav.nav-tabs"
    ];
    selectors.forEach(function(sel){
        document.querySelectorAll(sel).forEach(function(el){
            // Hide the nav
            el.style.display="none";
            // Hide sibling tab-content
            var sib=el.nextElementSibling;
            while(sib){
                if(sib.classList&&(sib.classList.contains("tab-content")||sib.classList.contains("product-details-tab-container"))){
                    sib.style.display="none";break;
                }
                sib=sib.nextElementSibling;
            }
        });
    });
    // Also hide individual tab panes by known IDs
    ["billingInfo","tabOverview","domainInfo","tabAddons"].forEach(function(id){
        var el=$(id);if(el)el.style.display="none";
    });
    // Hide the panel that contains the tabs
    var panelTabs=document.querySelector("ul.panel-tabs");
    if(panelTabs){
        var panel=panelTabs.closest(".panel");
        if(panel) panel.style.display="none";
    }
}

function buildTabs(){
    // Find insertion point — after the product status section
    var target=document.querySelector(".panel");
    if(!target) target=document.querySelector(".section-body");
    if(!target) return;

    // Find the hidden panel we just hid, insert after it
    var hiddenPanel=document.querySelector("ul.panel-tabs");
    var insertAfter=hiddenPanel?hiddenPanel.closest(".panel"):null;
    if(!insertAfter) insertAfter=target;

    var wrap=document.createElement("div");
    wrap.className="bt-wrap";
    wrap.id="bt-wrap";

    // Tab nav
    var tabs=[
        {id:"overview",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x273\\x27 y=\\x273\\x27 width=\\x2718\\x27 height=\\x2718\\x27 rx=\\x272\\x27/><path d=\\x27M3 9h18M9 21V9\\x27/></svg>",label:"Overview"},
        {id:"domains",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><circle cx=\\x2712\\x27 cy=\\x2712\\x27 r=\\x2710\\x27/><line x1=\\x272\\x27 y1=\\x2712\\x27 x2=\\x2722\\x27 y2=\\x2712\\x27/><path d=\\x27M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z\\x27/></svg>",label:"Domains",check:"domainEnabled"},
        {id:"email",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x272\\x27 y=\\x274\\x27 width=\\x2720\\x27 height=\\x2716\\x27 rx=\\x272\\x27/><path d=\\x27m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7\\x27/></svg>",label:"Email Accounts",check:"emailEnabled"},
        {id:"databases",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><ellipse cx=\\x2712\\x27 cy=\\x275\\x27 rx=\\x279\\x27 ry=\\x273\\x27/><path d=\\x27M21 12c0 1.66-4 3-9 3s-9-1.34-9-3\\x27/><path d=\\x27M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5\\x27/></svg>",label:"Databases",check:"dbEnabled"},
        {id:"wordpress",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27currentColor\\x27><path d=\\x27M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zM3.443 12c0-1.178.25-2.296.69-3.313l3.8 10.411A8.57 8.57 0 0 1 3.443 12zm8.557 8.557c-.82 0-1.613-.12-2.363-.34l2.51-7.29 2.57 7.04c.017.04.037.078.058.115a8.523 8.523 0 0 1-2.775.475z\\x27/></svg>",label:"WordPress",check:"wpEnabled"}
    ];

    var nav=document.createElement("div");
    nav.className="bt-tabs-nav";
    var panes=document.createElement("div");

    tabs.forEach(function(t,i){
        if(t.check&&!C[t.check]) return;
        var btn=document.createElement("button");
        btn.type="button";
        btn.className="bt-tab-btn"+(i===0?" active":"");
        btn.setAttribute("data-tab",t.id);
        btn.innerHTML=t.icon+" "+t.label;
        btn.addEventListener("click",function(){
            nav.querySelectorAll(".bt-tab-btn").forEach(function(b){b.classList.remove("active");});
            panes.querySelectorAll(".bt-tab-pane").forEach(function(p){p.classList.remove("active");});
            btn.classList.add("active");
            var pane=$("bt-pane-"+t.id);
            if(pane) pane.classList.add("active");
            // Lazy load
            if(t.id==="databases"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadDatabases();}
            if(t.id==="wordpress"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadWpInstances();}
        });
        nav.appendChild(btn);

        var pane=document.createElement("div");
        pane.className="bt-tab-pane"+(i===0?" active":"");
        pane.id="bt-pane-"+t.id;
        panes.appendChild(pane);
    });

    wrap.appendChild(nav);
    wrap.appendChild(panes);

    if(insertAfter&&insertAfter.parentNode){
        insertAfter.parentNode.insertBefore(wrap,insertAfter.nextSibling);
    }else{
        document.querySelector(".main-content,.content-padded,.section-body,.container").appendChild(wrap);
    }

    // Populate tabs
    buildOverviewPane();
    if(C.domainEnabled) buildDomainsPane();
    if(C.emailEnabled) buildEmailPane();
    if(C.dbEnabled) buildDatabasesPane();
    if(C.wpEnabled) buildWpPane();
}

/* ─── Overview Pane ─── */
function buildOverviewPane(){
    var pane=$("bt-pane-overview");
    if(!pane) return;

    // Scrape billing details from hidden default tabs
    var pairs=[];
    var billingEl=$("billingInfo")||$("tabOverview");
    if(billingEl){
        // Lagom: .col-sm-6.col-md-3 with .text-faded.text-small
        billingEl.querySelectorAll(".col-sm-6.col-md-3.m-b-2x,.col-sm-6.col-md-3").forEach(function(col){
            var lbl=col.querySelector(".text-faded.text-small,.text-faded");
            if(!lbl) return;
            var label=lbl.textContent.trim().replace(/:$/,"");
            var sib=lbl.nextElementSibling;
            var val=sib?sib.innerHTML.trim():"";
            if(!val){var c=col.cloneNode(true);var l2=c.querySelector(".text-faded");if(l2)l2.remove();val=c.innerHTML.trim();}
            if(label&&val) pairs.push({label:label,value:val});
        });
        // twenty-one: h4 + text
        if(!pairs.length){
            var rc=billingEl.querySelector(".col-md-6.text-center");
            if(rc){rc.querySelectorAll("h4").forEach(function(h4){
                var label=h4.textContent.trim().replace(/:$/,"");var val="";var s=h4.nextSibling;
                while(s&&!(s.nodeType===1&&s.tagName==="H4")){if(s.nodeType===3)val+=s.textContent.trim();else if(s.nodeType===1)val+=s.outerHTML;s=s.nextSibling;}
                val=val.trim();if(label&&val)pairs.push({label:label,value:val});
            });}
        }
        // col-sm-5 + col-sm-7
        if(!pairs.length){billingEl.querySelectorAll(".row").forEach(function(r){
            var l=r.querySelector(".col-sm-5,.col-md-5");var v=r.querySelector(".col-sm-7,.col-md-7");
            if(l&&v)pairs.push({label:l.textContent.trim().replace(/:$/,""),value:v.innerHTML.trim()});
        });}
        // Make billing visible temporarily to read, then hide
        billingEl.style.display="none";
    }

    var html="";
    if(pairs.length){
        html+="<div class=\"bt-ov-grid\">";
        pairs.forEach(function(p){
            html+="<div class=\"bt-ov-card\"><div class=\"bt-ov-label\">"+esc(p.label)+"</div><div class=\"bt-ov-value\">"+p.value+"</div></div>";
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

    // Addons/Upgrades — scrape from hidden tabs
    var addonsEl=$("tabAddons");
    if(addonsEl&&addonsEl.innerHTML.trim()){
        html+="<div class=\"bt-upgrades\"><h4><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z\"/></svg> Addons &amp; Available Upgrades</h4>"+addonsEl.innerHTML+"</div>";
        addonsEl.style.display="none";
    }
    // Also check for addon panels in the page
    document.querySelectorAll(".panel,.card").forEach(function(p){
        if(pane.contains(p)) return;
        var h=p.querySelector(".panel-heading,.card-header,h3,h4,h5,.panel-title");
        if(!h) return;
        var t=(h.textContent||"").toLowerCase();
        if((t.indexOf("addon")!==-1||t.indexOf("extra")!==-1||t.indexOf("configurable")!==-1)&&!p.closest("#bt-wrap")){
            html+="<div class=\"bt-upgrades\"><h4><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z\"/></svg> Addons &amp; Available Upgrades</h4>"+p.innerHTML+"</div>";
            p.style.display="none";
        }
    });

    pane.innerHTML=html;
    // Bind copy buttons
    pane.querySelectorAll(".bt-copy").forEach(function(b){b.addEventListener("click",function(){doCopy(this.getAttribute("data-copy"),this);});});
}

/* ─── Domains Pane ─── */
function buildDomainsPane(){
    var pane=$("bt-pane-domains");
    if(!pane||!C.domains) return;
    var d=C.domains;
    var total=1+(d.addon?d.addon.length:0)+(d.sub?d.sub.length:0)+(d.parked?d.parked.length:0);
    var html="<div class=\"bt-card\"><div class=\"bt-card-head\"><div class=\"bt-card-head-left\"><div class=\"bt-icon-circle\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"2\" y1=\"12\" x2=\"22\" y2=\"12\"/><path d=\"M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z\"/></svg></div><div><h5>Domains</h5><p class=\"bt-dom-count\">"+total+" domain"+(total!==1?"s":"")+"</p></div></div><div class=\"bt-card-head-right\"><button type=\"button\" class=\"bt-btn-add\" id=\"bdmAddAddonBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><line x1=\"12\" y1=\"5\" x2=\"12\" y2=\"19\"/><line x1=\"5\" y1=\"12\" x2=\"19\" y2=\"12\"/></svg> Add Domain</button><button type=\"button\" class=\"bt-btn-outline\" id=\"bdmAddSubBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"16 3 21 3 21 8\"/><line x1=\"4\" y1=\"20\" x2=\"21\" y2=\"3\"/></svg> Add Subdomain</button></div></div><div class=\"bt-list\" id=\"bt-dom-list\">";
    // Main
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
    var e=esc(name);
    var badgeClass=type==="main"?"bt-badge-primary":type==="addon"?"bt-badge-green":type==="sub"?"bt-badge-purple":"bt-badge-amber";
    return "<div class=\"bt-row\" data-domain=\""+e+"\" data-type=\""+type+"\"><div class=\"bt-row-icon "+type+"\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"2\" y1=\"12\" x2=\"22\" y2=\"12\"/></svg></div><div class=\"bt-row-info\"><span class=\"bt-row-name\">"+e+"</span><span class=\"bt-row-badge "+badgeClass+"\">"+badge+"</span></div><div class=\"bt-row-actions\"><a href=\"https://"+e+"\" target=\"_blank\" class=\"bt-row-btn visit\"><svg width=\"13\" height=\"13\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6\"/><polyline points=\"15 3 21 3 21 9\"/><line x1=\"10\" y1=\"14\" x2=\"21\" y2=\"3\"/></svg><span>Visit</span></a>"+(canDel?"<button type=\"button\" class=\"bt-row-btn del\" data-domain=\""+e+"\" data-type=\""+type+"\"><svg width=\"13\" height=\"13\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"3 6 5 6 21 6\"/><path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\"/></svg><span>Delete</span></button>":"")+"</div></div>";
}

/* ─── Email Pane ─── */
function buildEmailPane(){
    var pane=$("bt-pane-email");
    if(!pane) return;
    var emails=C.emails||[];
    var count=emails.length;
    var html="<div class=\"bt-card\"><div class=\"bt-card-head\"><div class=\"bt-card-head-left\"><div class=\"bt-icon-circle\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><rect x=\"2\" y=\"4\" width=\"20\" height=\"16\" rx=\"2\"/><path d=\"m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7\"/></svg></div><div><h5>Email Accounts</h5><p class=\"bt-email-count\">"+(count===1?"1 account":count+" accounts")+"</p></div></div><div class=\"bt-card-head-right\"><button type=\"button\" class=\"bt-btn-add\" id=\"bemCreateBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><line x1=\"12\" y1=\"5\" x2=\"12\" y2=\"19\"/><line x1=\"5\" y1=\"12\" x2=\"19\" y2=\"12\"/></svg> Create Email</button></div></div><div class=\"bt-list\" id=\"bt-email-list\">";
    if(!count){
        html+="<div class=\"bt-empty\"><svg width=\"32\" height=\"32\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.5\"><rect x=\"2\" y=\"4\" width=\"20\" height=\"16\" rx=\"2\"/><path d=\"m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7\"/></svg><span>No email accounts found</span></div>";
    }else{
        emails.forEach(function(em){html+=emailRow(em);});
    }
    html+="</div></div>";
    pane.innerHTML=html;
    bindEmailActions(pane);
    $("bemCreateBtn").addEventListener("click",openCreateEmailModal);
}
function emailRow(email){
    var e=esc(email);var ini=email.charAt(0).toUpperCase();
    return "<div class=\"bt-row\" data-email=\""+e+"\"><div class=\"bt-row-icon email\">"+ini+"</div><div class=\"bt-row-info\"><span class=\"bt-row-name\">"+e+"</span></div><div class=\"bt-row-actions\"><button type=\"button\" class=\"bt-row-btn login\" data-email=\""+e+"\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4\"/><polyline points=\"10 17 15 12 10 7\"/><line x1=\"15\" y1=\"12\" x2=\"3\" y2=\"12\"/></svg><span>Login</span></button><button type=\"button\" class=\"bt-row-btn pass\" data-email=\""+e+"\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><rect x=\"3\" y=\"11\" width=\"18\" height=\"11\" rx=\"2\" ry=\"2\"/><path d=\"M7 11V7a5 5 0 0 1 10 0v4\"/></svg><span>Password</span></button><button type=\"button\" class=\"bt-row-btn del\" data-email=\""+e+"\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"3 6 5 6 21 6\"/><path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\"/></svg><span>Delete</span></button></div></div>";
}

/* ─── Databases Pane ─── */
function buildDatabasesPane(){
    var pane=$("bt-pane-databases");
    if(!pane) return;
    pane.innerHTML="<div class=\"bt-card\"><div class=\"bt-card-head\"><div class=\"bt-card-head-left\"><div class=\"bt-icon-circle\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><ellipse cx=\"12\" cy=\"5\" rx=\"9\" ry=\"3\"/><path d=\"M21 12c0 1.66-4 3-9 3s-9-1.34-9-3\"/><path d=\"M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5\"/></svg></div><div><h5>Databases</h5><p class=\"bt-db-count\">Loading...</p></div></div><div class=\"bt-card-head-right\"><button type=\"button\" class=\"bt-btn-add\" id=\"bdbCreateBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><line x1=\"12\" y1=\"5\" x2=\"12\" y2=\"19\"/><line x1=\"5\" y1=\"12\" x2=\"19\" y2=\"12\"/></svg> New Database</button><button type=\"button\" class=\"bt-btn-outline\" id=\"bdbUserBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2\"/><circle cx=\"12\" cy=\"7\" r=\"4\"/></svg> New User</button><button type=\"button\" class=\"bt-btn-outline\" id=\"bdbAssignBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2\"/><circle cx=\"8.5\" cy=\"7\" r=\"4\"/><line x1=\"20\" y1=\"8\" x2=\"20\" y2=\"14\"/><line x1=\"23\" y1=\"11\" x2=\"17\" y2=\"11\"/></svg> Assign</button><a class=\"bt-btn-outline\" id=\"bdbPmaBtn\" href=\"#\" target=\"_blank\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6\"/><polyline points=\"15 3 21 3 21 9\"/><line x1=\"10\" y1=\"14\" x2=\"21\" y2=\"3\"/></svg> phpMyAdmin</a></div></div><div class=\"bt-list\" id=\"bt-db-list\"><div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading databases...</span></div></div></div>";
    $("bdbCreateBtn").addEventListener("click",function(){$("bdbCreateModal").style.display="flex";$("bdbNewName").value="";$("bdbCreateMsg").style.display="none";});
    $("bdbUserBtn").addEventListener("click",function(){$("bdbUserModal").style.display="flex";$("bdbNewUser").value="";$("bdbUserPass").value="";$("bdbUserMsg").style.display="none";});
    $("bdbAssignBtn").addEventListener("click",openAssignModal);
    // phpMyAdmin link — will be set after loading
    post({action:"get_phpmyadmin_url"},function(r){
        if(r.success&&r.url) $("bdbPmaBtn").href=r.url;
    });
    $("bdbCreateSubmit").addEventListener("click",submitCreateDb);
    $("bdbUserSubmit").addEventListener("click",submitCreateDbUser);
    $("bdbAssignSubmit").addEventListener("click",submitAssignDb);
}

function loadDatabases(){
    var list=$("bt-db-list");
    if(!list) return;
    list.innerHTML="<div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading databases...</span></div>";
    post({action:"list_databases"},function(r){
        if(!r.success){list.innerHTML="<div class=\"bt-empty\"><span>"+(r.message||"Failed to load")+"</span></div>";return;}
        var dbs=r.databases||[];var users=r.users||[];
        var countEl=document.querySelector(".bt-db-count");
        if(countEl) countEl.textContent=dbs.length+" database"+(dbs.length!==1?"s":"")+", "+users.length+" user"+(users.length!==1?"s":"");
        // Set prefix
        if(r.prefix){$("bdbPrefix").textContent=r.prefix;$("bdbUserPrefix").textContent=r.prefix;}
        var html="";
        if(!dbs.length&&!users.length){
            html="<div class=\"bt-empty\"><svg width=\"32\" height=\"32\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.5\"><ellipse cx=\"12\" cy=\"5\" rx=\"9\" ry=\"3\"/><path d=\"M21 12c0 1.66-4 3-9 3s-9-1.34-9-3\"/><path d=\"M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5\"/></svg><span>No databases found</span></div>";
        }else{
            dbs.forEach(function(db){
                var dbUsers=[];
                if(r.mappings){r.mappings.forEach(function(m){if(m.db===db)dbUsers.push(m.user);});}
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
        // Bind delete
        list.querySelectorAll(".bt-row-btn.del[data-db]").forEach(function(b){b.addEventListener("click",function(){
            if(confirm("Delete database "+this.getAttribute("data-db")+"?")){
                var btn=this;btn.disabled=true;
                post({action:"delete_database",database:this.getAttribute("data-db")},function(r){btn.disabled=false;if(r.success)loadDatabases();else alert(r.message||"Failed");});
            }
        });});
        list.querySelectorAll(".bt-row-btn.del[data-dbuser]").forEach(function(b){b.addEventListener("click",function(){
            if(confirm("Delete user "+this.getAttribute("data-dbuser")+"?")){
                var btn=this;btn.disabled=true;
                post({action:"delete_db_user",dbuser:this.getAttribute("data-dbuser")},function(r){btn.disabled=false;if(r.success)loadDatabases();else alert(r.message||"Failed");});
            }
        });});
        // Update assign modal selects
        updateAssignSelects(dbs,users);
    });
}
function submitCreateDb(){
    var btn=$("bdbCreateSubmit"),msg=$("bdbCreateMsg");
    var name=$("bdbNewName").value.trim();
    if(!name){showMsg(msg,"Enter a database name",false);return;}
    btn.disabled=true;btn.textContent="Creating...";
    post({action:"create_database",dbname:name},function(r){
        btn.disabled=false;btn.textContent="Create Database";showMsg(msg,r.message,r.success);
        if(r.success){setTimeout(function(){$("bdbCreateModal").style.display="none";},600);loadDatabases();}
    });
}
function submitCreateDbUser(){
    var btn=$("bdbUserSubmit"),msg=$("bdbUserMsg");
    var name=$("bdbNewUser").value.trim();var pass=$("bdbUserPass").value;
    if(!name||!pass){showMsg(msg,"Fill in all fields",false);return;}
    btn.disabled=true;btn.textContent="Creating...";
    post({action:"create_db_user",dbuser:name,dbpass:pass},function(r){
        btn.disabled=false;btn.textContent="Create User";showMsg(msg,r.message,r.success);
        if(r.success){setTimeout(function(){$("bdbUserModal").style.display="none";},600);loadDatabases();}
    });
}
function openAssignModal(){
    $("bdbAssignModal").style.display="flex";$("bdbAssignMsg").style.display="none";$("bdbAssignAll").checked=true;
}
function updateAssignSelects(dbs,users){
    var dbSel=$("bdbAssignDb"),uSel=$("bdbAssignUser");
    if(dbSel){dbSel.innerHTML="";dbs.forEach(function(d){var o=document.createElement("option");o.value=d;o.textContent=d;dbSel.appendChild(o);});}
    if(uSel){uSel.innerHTML="";users.forEach(function(u){var o=document.createElement("option");o.value=u;o.textContent=u;uSel.appendChild(o);});}
}
function submitAssignDb(){
    var btn=$("bdbAssignSubmit"),msg=$("bdbAssignMsg");
    var db=$("bdbAssignDb").value,user=$("bdbAssignUser").value;
    var allPriv=$("bdbAssignAll").checked?"ALL PRIVILEGES":"ALL PRIVILEGES";
    if(!db||!user){showMsg(msg,"Select a database and user",false);return;}
    btn.disabled=true;btn.textContent="Assigning...";
    post({action:"assign_db_user",database:db,dbuser:user,privileges:allPriv},function(r){
        btn.disabled=false;btn.textContent="Assign Privileges";showMsg(msg,r.message,r.success);
        if(r.success){setTimeout(function(){$("bdbAssignModal").style.display="none";},600);loadDatabases();}
    });
}

/* ─── WordPress Pane ─── */
function buildWpPane(){
    var pane=$("bt-pane-wordpress");
    if(!pane) return;
    pane.innerHTML="<div class=\"bt-card\"><div class=\"bt-card-head\"><div class=\"bt-card-head-left\"><div class=\"bt-icon-circle\"><svg width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"currentColor\"><path d=\"M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2z\"/></svg></div><div><h5>WordPress Manager</h5><p>Manage your WordPress installations</p></div></div><div class=\"bt-card-head-right\"><button type=\"button\" class=\"bt-btn-outline\" id=\"bwpScanBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M21.21 15.89A10 10 0 1 1 8 2.83\"/><path d=\"M22 12A10 10 0 0 0 12 2v10z\"/></svg> Refresh</button></div></div><div class=\"bt-list\" id=\"bwpList\"><div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading WordPress installations...</span></div></div></div>";
    $("bwpScanBtn").addEventListener("click",function(){loadWpInstances();});
}

function loadWpInstances(){
    var list=$("bwpList");
    if(!list) return;
    list.innerHTML="<div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading WordPress installations...</span></div>";
    wpPost({action:"get_wp_instances"},function(r){
        if(!r.success||!r.instances||!r.instances.length){
            list.innerHTML="<div class=\"bt-empty\"><svg width=\"40\" height=\"40\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.5\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><path d=\"M8 12h8M12 8v8\"/></svg><span>"+(r.message||"No WordPress installations found")+"</span></div>";
            return;
        }
        wpInstances=r.instances;
        var html="";
        r.instances.forEach(function(inst){
            var statusBadge=inst.alive?"<span class=\"bwp-status-badge active\">Online</span>":"<span class=\"bwp-status-badge inactive\">Offline</span>";
            var sslBadge=inst.ssl?"<span title=\"SSL\">&#128274;</span>":"";
            html+="<div class=\"bwp-site\" data-id=\""+inst.id+"\"><div class=\"bwp-site-icon\"><svg width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"currentColor\"><path d=\"M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2z\"/></svg></div><div class=\"bwp-site-info\"><p class=\"bwp-site-domain\">"+esc(inst.displayTitle||inst.site_url||inst.domain)+" "+sslBadge+"</p><div class=\"bwp-site-meta\"><span>WP "+esc(inst.version)+"</span><span>"+esc(inst.path||"/")+"</span><span>"+statusBadge+"</span></div></div><div class=\"bwp-site-actions\"><button type=\"button\" class=\"bt-btn-add bwp-login-btn\" data-id=\""+inst.id+"\">Login</button><button type=\"button\" class=\"bt-btn-outline bwp-manage-btn\" data-id=\""+inst.id+"\">Manage</button></div></div>";
        });
        list.innerHTML=html;
        list.querySelectorAll(".bwp-login-btn").forEach(function(b){b.addEventListener("click",function(e){e.stopPropagation();bwpAutoLogin(parseInt(this.getAttribute("data-id")),this);});});
        list.querySelectorAll(".bwp-manage-btn").forEach(function(b){b.addEventListener("click",function(e){e.stopPropagation();bwpOpenDetail(parseInt(this.getAttribute("data-id")));});});
        list.querySelectorAll(".bwp-site").forEach(function(s){s.addEventListener("click",function(){bwpOpenDetail(parseInt(this.getAttribute("data-id")));});});
    });
}

function bwpAutoLogin(id,btn){
    if(btn){var orig=btn.innerHTML;btn.disabled=true;btn.innerHTML="<div class=\"bt-spinner\" style=\"width:14px;height:14px;border-width:2px;display:inline-block;vertical-align:middle\"></div>";}
    wpPost({action:"wp_autologin",instance_id:id},function(r){
        if(btn){btn.disabled=false;btn.innerHTML=orig;}
        if(r.success&&r.login_url) window.open(r.login_url,"_blank");
        else alert(r.message||"Could not login");
    });
}

function bwpOpenDetail(id){
    var inst=null;for(var i=0;i<wpInstances.length;i++){if(wpInstances[i].id===id){inst=wpInstances[i];break;}}
    if(!inst) return;
    currentWpInstance=inst;
    var overlay=$("bwpDetailOverlay");if(!overlay) return;
    overlay.style.display="flex";
    $("bwpDetailTitle").textContent=inst.displayTitle||inst.site_url||inst.domain;
    overlay.querySelectorAll(".bwp-tab").forEach(function(t,i){t.classList.toggle("active",i===0);});
    overlay.querySelectorAll(".bwp-tab-content").forEach(function(c,i){c.classList.toggle("active",i===0);});
    var siteUrl=inst.site_url||("http://"+inst.domain);var safeUrl=esc(siteUrl);
    var statusBadge=inst.alive?"<span class=\"bwp-status-badge active\">Online</span>":"<span class=\"bwp-status-badge inactive\">Offline</span>";
    var sslBadge=inst.ssl?"<span class=\"bwp-status-badge active\">SSL</span>":"<span class=\"bwp-status-badge inactive\">No SSL</span>";
    var screenshotSrc="https://image.thum.io/get/width/600/crop/375/"+encodeURIComponent(siteUrl);
    $("bwpTabOverview").innerHTML="<div class=\"bwp-overview-hero\"><div class=\"bwp-preview-col\"><div class=\"bwp-preview-wrap\"><div class=\"bwp-preview-bar\"><div class=\"bwp-preview-dots\"><span></span><span></span><span></span></div><div class=\"bwp-preview-url\">"+safeUrl+"</div></div><div class=\"bwp-preview-frame-wrap\" style=\"cursor:pointer\" onclick=\"window.open(\\x27"+safeUrl+"\\x27,\\x27_blank\\x27)\"><img src=\""+esc(screenshotSrc)+"\" style=\"width:100%;height:100%;object-fit:cover\" onerror=\"this.style.display=\\x27none\\x27\" alt=\"Preview\"></div></div><div class=\"bwp-quick-actions\"><button type=\"button\" class=\"bt-btn-add\" onclick=\"window.bwpDoLogin(event)\">WP Admin</button><button type=\"button\" class=\"bt-btn-outline\" onclick=\"window.open(\\x27"+safeUrl+"\\x27,\\x27_blank\\x27)\">Visit</button></div></div><div class=\"bwp-overview-right\"><div class=\"bwp-site-header\"><div class=\"bwp-site-header-icon\"><svg width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"currentColor\"><path d=\"M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2z\"/></svg></div><div class=\"bwp-site-header-info\"><h4>"+esc(inst.displayTitle||inst.domain)+"</h4><p><span>"+statusBadge+"</span><span>"+sslBadge+"</span></p></div></div><div class=\"bwp-overview-grid\"><div class=\"bwp-stat\"><p class=\"bwp-stat-label\">WP Version</p><p class=\"bwp-stat-value\">"+esc(inst.version)+"</p></div><div class=\"bwp-stat\"><p class=\"bwp-stat-label\">Owner</p><p class=\"bwp-stat-value\">"+esc(inst.owner)+"</p></div><div class=\"bwp-stat\"><p class=\"bwp-stat-label\">SSL</p><p class=\"bwp-stat-value\">"+(inst.ssl?"Enabled":"Disabled")+"</p></div><div class=\"bwp-stat\"><p class=\"bwp-stat-label\">Path</p><p class=\"bwp-stat-value\">"+esc(inst.path)+"</p></div></div></div></div>";
    $("bwpTabPlugins").innerHTML="<div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading plugins...</span></div>";
    $("bwpTabThemes").innerHTML="<div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading themes...</span></div>";
    $("bwpTabSecurity").innerHTML="<div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Running security scan...</span></div>";
}
window.bwpDoLogin=function(){if(currentWpInstance)bwpAutoLogin(currentWpInstance.id,null);};

/* ─── WP Detail Tab Handlers ─── */
(function(){
    // Bind WP detail tabs after DOM ready
    setTimeout(function(){
        var overlay=$("bwpDetailOverlay");
        if(!overlay) return;
        overlay.addEventListener("click",function(e){if(e.target===overlay)overlay.style.display="none";});
        var closeBtn=$("bwpDetailClose");
        if(closeBtn) closeBtn.addEventListener("click",function(){overlay.style.display="none";});
        overlay.querySelectorAll(".bwp-tab").forEach(function(tab){
            tab.addEventListener("click",function(){
                overlay.querySelectorAll(".bwp-tab").forEach(function(t){t.classList.remove("active");});
                overlay.querySelectorAll(".bwp-tab-content").forEach(function(c){c.classList.remove("active");});
                tab.classList.add("active");
                var target=tab.getAttribute("data-tab");
                var content=$("bwpTab"+target.charAt(0).toUpperCase()+target.slice(1));
                if(content) content.classList.add("active");
                if(target==="plugins"&&currentWpInstance) bwpLoadPlugins();
                if(target==="themes"&&currentWpInstance) bwpLoadThemes();
                if(target==="security"&&currentWpInstance) bwpLoadSecurity();
            });
        });
    },200);
})();

function bwpLoadPlugins(){
    if(!currentWpInstance) return;
    var c=$("bwpTabPlugins");
    c.innerHTML="<div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading plugins...</span></div>";
    wpPost({action:"wp_list_plugins",instance_id:currentWpInstance.id},function(r){
        if(!r.success||!r.plugins){c.innerHTML="<div class=\"bwp-msg error\">"+(r.message||"Failed")+"</div>";return;}
        if(!r.plugins.length){c.innerHTML="<div class=\"bt-empty\"><span>No plugins</span></div>";return;}
        var html="";
        r.plugins.forEach(function(p){
            var isActive=p.active||p.selected||p.status==="active";
            var hasUpdate=!!p.availableVersion;
            html+="<div class=\"bwp-item-row\"><div class=\"bwp-item-icon plugin\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z\"/></svg></div><div class=\"bwp-item-info\"><p class=\"bwp-item-name\">"+esc(p.title||p.slug)+"</p><p class=\"bwp-item-detail\"><span class=\"bwp-status-badge "+(isActive?"active":"inactive")+"\">"+(isActive?"Active":"Inactive")+"</span> v"+esc(p.version)+(hasUpdate?" <span class=\"bwp-status-badge update-available\">&rarr; "+esc(p.availableVersion)+"</span>":"")+"</p></div><div class=\"bwp-item-actions\"><button class=\"bwp-item-btn "+(isActive?"active":"inactive")+"-state\" data-slug=\""+esc(p.slug)+"\" data-activate=\""+(isActive?"0":"1")+"\" onclick=\"bwpTogglePlugin(this)\">"+(isActive?"Deactivate":"Activate")+"</button>"+(hasUpdate?"<button class=\"bwp-item-btn update\" data-slug=\""+esc(p.slug)+"\" onclick=\"bwpUpdatePlugin(this)\">Update</button>":"")+"</div></div>";
        });
        c.innerHTML=html;
    });
}
window.bwpTogglePlugin=function(btn){if(!currentWpInstance)return;var slug=btn.getAttribute("data-slug"),act=btn.getAttribute("data-activate");btn.disabled=true;wpPost({action:"wp_toggle_plugin",instance_id:currentWpInstance.id,slug:slug,activate:act},function(r){if(r.success)bwpLoadPlugins();else{alert(r.message||"Failed");btn.disabled=false;}});};
window.bwpUpdatePlugin=function(btn){if(!currentWpInstance)return;var slug=btn.getAttribute("data-slug");btn.disabled=true;btn.textContent="Updating...";wpPost({action:"wp_update",instance_id:currentWpInstance.id,type:"plugins",slug:slug},function(r){if(r.success){btn.textContent="Done";setTimeout(bwpLoadPlugins,1500);}else{btn.disabled=false;btn.textContent="Failed";}});};

function bwpLoadThemes(){
    if(!currentWpInstance) return;
    var c=$("bwpTabThemes");
    c.innerHTML="<div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading themes...</span></div>";
    wpPost({action:"wp_list_themes",instance_id:currentWpInstance.id},function(r){
        if(!r.success||!r.themes){c.innerHTML="<div class=\"bwp-msg error\">"+(r.message||"Failed")+"</div>";return;}
        if(!r.themes.length){c.innerHTML="<div class=\"bt-empty\"><span>No themes</span></div>";return;}
        var html="<div class=\"bwp-theme-grid\">";
        r.themes.forEach(function(t){
            var isActive=t.active||t.selected;
            var hasUpdate=!!t.availableVersion;
            html+="<div class=\"bwp-theme-card"+(isActive?" bwp-theme-active":"")+"\"><div class=\"bwp-theme-screenshot\"><img src=\""+(t.screenshot||"")+"\" onerror=\"this.style.display=\\x27none\\x27\" alt=\"\"><div style=\"display:flex;align-items:center;justify-content:center;position:absolute;inset:0;background:var(--input-bg,#f3f4f6)\"><svg width=\"32\" height=\"32\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.5\" opacity=\".3\"><rect x=\"3\" y=\"3\" width=\"18\" height=\"18\" rx=\"2\"/></svg></div>"+(isActive?"<div class=\"bwp-theme-active-badge\">Active</div>":"")+"</div><div class=\"bwp-theme-info\"><p class=\"bwp-theme-name\">"+esc(t.title||t.slug)+"</p><p class=\"bwp-theme-ver\">v"+esc(t.version)+(hasUpdate?" <span class=\"bwp-status-badge update-available\">&rarr; "+esc(t.availableVersion)+"</span>":"")+"</p><div class=\"bwp-theme-actions\">"+(!isActive?"<button class=\"bwp-item-btn active-state\" data-slug=\""+esc(t.slug)+"\" onclick=\"bwpActivateTheme(this)\">Activate</button>":"")+(hasUpdate?"<button class=\"bwp-item-btn update\" data-slug=\""+esc(t.slug)+"\" onclick=\"bwpUpdateTheme(this)\">Update</button>":"")+"</div></div></div>";
        });
        html+="</div>";c.innerHTML=html;
    });
}
window.bwpActivateTheme=function(btn){if(!currentWpInstance)return;btn.disabled=true;wpPost({action:"wp_toggle_theme",instance_id:currentWpInstance.id,slug:btn.getAttribute("data-slug")},function(r){if(r.success)bwpLoadThemes();else{alert(r.message||"Failed");btn.disabled=false;}});};
window.bwpUpdateTheme=function(btn){if(!currentWpInstance)return;btn.disabled=true;btn.textContent="Updating...";wpPost({action:"wp_update",instance_id:currentWpInstance.id,type:"themes",slug:btn.getAttribute("data-slug")},function(r){if(r.success){btn.textContent="Done";setTimeout(bwpLoadThemes,1500);}else{btn.disabled=false;btn.textContent="Failed";}});};

function bwpLoadSecurity(){
    if(!currentWpInstance) return;
    var c=$("bwpTabSecurity");
    c.innerHTML="<div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Running security scan...</span></div>";
    wpPost({action:"wp_security_scan",instance_id:currentWpInstance.id},function(r){
        if(!r.success||!r.security){c.innerHTML="<div class=\"bwp-msg error\">"+(r.message||"Scan failed")+"</div>";return;}
        var items=r.security;if(!Array.isArray(items)){var arr=[];for(var k in items){if(items.hasOwnProperty(k)){var v=items[k];arr.push({id:v.id||k,title:v.title||k,status:v.status||"unknown"});}}items=arr;}
        if(!items.length){c.innerHTML="<div class=\"bt-empty\"><span>No security data</span></div>";return;}
        var applied=0;items.forEach(function(it){var s=(it.status||"").toLowerCase();if(s==="applied"||s==="ok"||s==="true")applied++;});
        var pct=items.length?Math.round(applied/items.length*100):0;
        var html="<div class=\"bwp-sec-summary\"><div class=\"bwp-sec-summary-bar\"><div class=\"bwp-sec-summary-fill\" style=\"width:"+pct+"%\"></div></div><div class=\"bwp-sec-summary-text\"><span style=\"color:#059669\"><strong>"+applied+"</strong> Applied</span><span style=\"color:#d97706\"><strong>"+(items.length-applied)+"</strong> Not Applied</span></div></div>";
        items.forEach(function(it){
            var s=(it.status||"").toLowerCase();var ok=s==="applied"||s==="ok"||s==="true";
            html+="<div class=\"bwp-security-item\"><div class=\"bwp-sec-icon "+(ok?"ok":"warning")+"\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\">"+(ok?"<path d=\"M22 11.08V12a10 10 0 1 1-5.93-9.14\"/><polyline points=\"22 4 12 14.01 9 11.01\"/>":"<path d=\"M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z\"/><line x1=\"12\" y1=\"9\" x2=\"12\" y2=\"13\"/><line x1=\"12\" y1=\"17\" x2=\"12.01\" y2=\"17\"/>")+"</svg></div><div class=\"bwp-sec-info\"><p class=\"bwp-sec-label\">"+esc(it.title||it.id)+"</p><p class=\"bwp-sec-detail\">"+(ok?"Applied":"Not Applied")+"</p></div><span class=\"bwp-sec-value "+(ok?"ok":"warning")+"\">"+(ok?"Applied":"Not Applied")+"</span></div>";
        });
        c.innerHTML=html;
    });
}

/* ─── Email Actions ─── */
function bindEmailActions(c){
    c.querySelectorAll(".bt-row-btn.login").forEach(function(b){b.addEventListener("click",function(){post({action:"webmail_login",email:this.getAttribute("data-email")},function(r){if(r.success&&r.url)window.open(r.url,"_blank");else alert(r.message||"Failed");});});});
    c.querySelectorAll(".bt-row-btn.pass").forEach(function(b){b.addEventListener("click",function(){openPassModal(this.getAttribute("data-email"));});});
    c.querySelectorAll(".bt-row-btn.del[data-email]").forEach(function(b){b.addEventListener("click",function(){openDelEmailModal(this.getAttribute("data-email"));});});
}
var domainsLoaded=false;
function openCreateEmailModal(){
    $("bemCreateModal").style.display="flex";$("bemNewUser").value="";$("bemNewPass").value="";$("bemNewQuota").value="250";$("bemCreateMsg").style.display="none";
    if(!domainsLoaded){post({action:"get_domains"},function(r){var sel=$("bemNewDomain");sel.innerHTML="";if(r.success&&r.domains){r.domains.forEach(function(d){var o=document.createElement("option");o.value=d;o.textContent=d;sel.appendChild(o);});}domainsLoaded=true;});}
}
$("bemCreateSubmit")&&$("bemCreateSubmit").addEventListener("click",function(){
    var btn=$("bemCreateSubmit"),msg=$("bemCreateMsg");
    var user=$("bemNewUser").value.trim(),pass=$("bemNewPass").value,domain=$("bemNewDomain").value,quota=$("bemNewQuota").value;
    if(!user||!pass){showMsg(msg,"Fill in all fields",false);return;}
    btn.disabled=true;btn.textContent="Creating...";
    post({action:"create_email",email_user:user,email_pass:pass,domain:domain,quota:quota},function(r){
        btn.disabled=false;btn.textContent="Create Account";showMsg(msg,r.message,r.success);
        if(r.success){setTimeout(function(){$("bemCreateModal").style.display="none";},600);
            var list=$("bt-email-list");if(list){var empty=list.querySelector(".bt-empty");if(empty)empty.remove();var div=document.createElement("div");div.innerHTML=emailRow(r.email||user+"@"+domain);list.appendChild(div.firstChild);bindEmailActions(list);}
            var cnt=document.querySelector(".bt-email-count");if(cnt){var m=cnt.textContent.match(/(\d+)/);var c=m?parseInt(m[1])+1:1;cnt.textContent=c===1?"1 account":c+" accounts";}
        }
    });
});
function openPassModal(email){$("bemPassModal").style.display="flex";$("bemPassEmail").value=email;$("bemPassNew").value="";$("bemPassMsg").style.display="none";}
$("bemPassSubmit")&&$("bemPassSubmit").addEventListener("click",function(){
    var btn=$("bemPassSubmit"),msg=$("bemPassMsg");var email=$("bemPassEmail").value,pass=$("bemPassNew").value;
    if(!pass){showMsg(msg,"Enter a password",false);return;}
    btn.disabled=true;btn.textContent="Updating...";
    post({action:"change_password",email:email,new_pass:pass},function(r){btn.disabled=false;btn.textContent="Update Password";showMsg(msg,r.message,r.success);if(r.success)setTimeout(function(){$("bemPassModal").style.display="none";},800);});
});
var delEmailTarget="";
function openDelEmailModal(email){delEmailTarget=email;$("bemDelModal").style.display="flex";$("bemDelEmail").textContent=email;$("bemDelMsg").style.display="none";}
$("bemDelSubmit")&&$("bemDelSubmit").addEventListener("click",function(){
    var btn=$("bemDelSubmit"),msg=$("bemDelMsg");btn.disabled=true;btn.textContent="Deleting...";
    post({action:"delete_email",email:delEmailTarget},function(r){btn.disabled=false;btn.textContent="Delete";
        if(r.success){showMsg(msg,r.message,true);var row=document.querySelector(".bt-row[data-email=\""+delEmailTarget+"\"]");if(row){row.style.opacity="0";row.style.transition="opacity .3s";setTimeout(function(){row.remove();},300);}setTimeout(function(){$("bemDelModal").style.display="none";},600);}
        else showMsg(msg,r.message,false);
    });
});

/* ─── Domain Actions ─── */
function bindDomainActions(c){
    c.querySelectorAll(".bt-row-btn.del[data-domain]").forEach(function(b){b.addEventListener("click",function(){openDelDomainModal(this.getAttribute("data-domain"),this.getAttribute("data-type"));});});
}
var domDelTarget="",domDelType="",domDomainsLoaded=false;
function openAddonModal(){$("bdmAddonModal").style.display="flex";$("bdmAddonDomain").value="";$("bdmAddonDocroot").value="";$("bdmAddonMsg").style.display="none";$("bdmAddonDomain").oninput=function(){$("bdmAddonDocroot").value=this.value;};}
function openSubModal(){
    $("bdmSubModal").style.display="flex";$("bdmSubName").value="";$("bdmSubDocroot").value="";$("bdmSubMsg").style.display="none";
    if(!domDomainsLoaded){post({action:"get_parent_domains"},function(r){var sel=$("bdmSubParent");sel.innerHTML="";if(r.success&&r.domains){r.domains.forEach(function(d){var o=document.createElement("option");o.value=d;o.textContent=d;sel.appendChild(o);});}domDomainsLoaded=true;});}
    $("bdmSubName").oninput=function(){$("bdmSubDocroot").value=this.value+"."+$("bdmSubParent").value;};
}
$("bdmAddonSubmit")&&$("bdmAddonSubmit").addEventListener("click",function(){
    var btn=$("bdmAddonSubmit"),msg=$("bdmAddonMsg");var domain=$("bdmAddonDomain").value.trim(),docroot=$("bdmAddonDocroot").value.trim();
    if(!domain){showMsg(msg,"Enter a domain",false);return;}if(!docroot)docroot=domain;
    btn.disabled=true;btn.textContent="Adding...";
    post({action:"add_addon_domain",domain:domain,docroot:docroot},function(r){btn.disabled=false;btn.textContent="Add Domain";showMsg(msg,r.message,r.success);
        if(r.success){setTimeout(function(){$("bdmAddonModal").style.display="none";},600);var list=$("bt-dom-list");if(list){var div=document.createElement("div");div.innerHTML=domRow(r.domain||domain,"addon","Addon",true);list.appendChild(div.firstChild);bindDomainActions(list);}}
    });
});
$("bdmSubSubmit")&&$("bdmSubSubmit").addEventListener("click",function(){
    var btn=$("bdmSubSubmit"),msg=$("bdmSubMsg");var name=$("bdmSubName").value.trim(),parent=$("bdmSubParent").value,docroot=$("bdmSubDocroot").value.trim();
    if(!name){showMsg(msg,"Enter a subdomain",false);return;}if(!docroot)docroot=name+"."+parent;
    btn.disabled=true;btn.textContent="Adding...";
    post({action:"add_subdomain",subdomain:name,domain:parent,docroot:docroot},function(r){btn.disabled=false;btn.textContent="Add Subdomain";showMsg(msg,r.message,r.success);
        if(r.success){setTimeout(function(){$("bdmSubModal").style.display="none";},600);var list=$("bt-dom-list");if(list){var div=document.createElement("div");div.innerHTML=domRow(r.domain||name+"."+parent,"sub","Subdomain",true);list.appendChild(div.firstChild);bindDomainActions(list);}}
    });
});
function openDelDomainModal(domain,type){domDelTarget=domain;domDelType=type;$("bdmDelModal").style.display="flex";$("bdmDelDomain").textContent=domain;$("bdmDelMsg").style.display="none";}
$("bdmDelSubmit")&&$("bdmDelSubmit").addEventListener("click",function(){
    var btn=$("bdmDelSubmit"),msg=$("bdmDelMsg");btn.disabled=true;btn.textContent="Deleting...";
    post({action:"delete_domain",domain:domDelTarget,type:domDelType},function(r){btn.disabled=false;btn.textContent="Delete";
        if(r.success){showMsg(msg,r.message,true);var row=document.querySelector(".bt-row[data-domain=\""+domDelTarget+"\"]");if(row){row.style.opacity="0";row.style.transition="opacity .3s";setTimeout(function(){row.remove();},300);}setTimeout(function(){$("bdmDelModal").style.display="none";},600);}
        else showMsg(msg,r.message,false);
    });
});

/* ─── Modal Bindings ─── */
function bindModals(){
    document.querySelectorAll("[data-close]").forEach(function(b){b.addEventListener("click",function(){var m=this.closest(".bt-overlay");if(m)m.style.display="none";});});
    document.querySelectorAll(".bt-overlay").forEach(function(o){o.addEventListener("click",function(e){if(e.target===o)o.style.display="none";});});
    document.querySelectorAll("[data-toggle-pass]").forEach(function(b){b.addEventListener("click",function(){var id=this.getAttribute("data-toggle-pass");var inp=$(id);if(inp)inp.type=inp.type==="password"?"text":"password";});});
}

/* ─── Boot ─── */
if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",init);
else setTimeout(init,150);
})();
</script>';
}
