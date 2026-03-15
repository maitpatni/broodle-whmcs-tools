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

function broodle_tools_ns_enabled()
{
    return broodle_tools_setting_enabled('tweak_nameservers_tab');
}

function broodle_tools_email_enabled()
{
    return broodle_tools_setting_enabled('tweak_email_list');
}

function broodle_tools_wp_enabled()
{
    return broodle_tools_setting_enabled('tweak_wordpress_toolkit');
}

function broodle_tools_domain_enabled()
{
    return broodle_tools_setting_enabled('tweak_domain_management');
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
    if (!empty($service->dedicatedip)) {
        $ip = $service->dedicatedip;
    } elseif (!empty($server->ipaddress)) {
        $ip = $server->ipaddress;
    }
    return ['ns' => $ns, 'ip' => $ip];
}

/** Fetch email accounts from cPanel via WHM API. */
function broodle_tools_get_emails($serviceId)
{
    $data = broodle_tools_get_cpanel_service($serviceId);
    if (!$data) return [];
    $server = $data['server'];
    $service = $data['service'];
    $username = $service->username;
    if (empty($username)) return [];

    $hostname = $server->hostname;
    $port = !empty($server->port) ? (int) $server->port : 2087;
    $serverUser = $server->username;
    $secure = !empty($server->secure) && ($server->secure === 'on' || $server->secure === '1' || $server->secure === 1);
    $protocol = $secure ? 'https' : 'http';

    $accessHash = '';
    $password = '';
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
    if (empty($accessHash) && empty($password)) return [];

    $headers = [];
    if (!empty($accessHash)) {
        $headers[] = "Authorization: whm {$serverUser}:{$accessHash}";
    }

    $emails = [];

    // Try UAPI Email::list_pops
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($username)
         . "&cpanel_jsonapi_apiversion=3"
         . "&cpanel_jsonapi_module=Email"
         . "&cpanel_jsonapi_func=list_pops";
    $emails = broodle_tools_parse_email_response(
        broodle_tools_whm_get($url, $headers, $serverUser, $password), $username
    );

    // Fallback: WHM list_pops_for
    if (empty($emails)) {
        $url2 = "{$protocol}://{$hostname}:{$port}/json-api/list_pops_for?api.version=1&user=" . urlencode($username);
        $r2 = broodle_tools_whm_get($url2, $headers, $serverUser, $password);
        if ($r2) {
            $pops = $r2['data']['pops'] ?? ($r2['pops'] ?? []);
            foreach ($pops as $e) {
                $em = is_string($e) ? $e : ($e['email'] ?? ($e['user'] ?? ''));
                if ($em && $em !== $username && strpos($em, '@') !== false) $emails[] = $em;
            }
        }
    }

    // Fallback: UAPI list_pops_with_disk
    if (empty($emails)) {
        $url3 = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
              . "?cpanel_jsonapi_user=" . urlencode($username)
              . "&cpanel_jsonapi_apiversion=3&cpanel_jsonapi_module=Email&cpanel_jsonapi_func=list_pops_with_disk";
        $emails = broodle_tools_parse_email_response(
            broodle_tools_whm_get($url3, $headers, $serverUser, $password), $username
        );
    }

    $emails = array_unique($emails);
    sort($emails);
    return $emails;
}

function broodle_tools_whm_get($url, $headers, $serverUser, $password)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
    ]);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    } elseif (!empty($password)) {
        curl_setopt($ch, CURLOPT_USERPWD, "{$serverUser}:{$password}");
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
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

/* ─── Main Output Hook ────────────────────────────────────── */

/** Ensure all default settings exist (handles upgrades without re-activation). */
function broodle_tools_ensure_defaults()
{
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        if (!Capsule::schema()->hasTable('mod_broodle_tools_settings')) return;
        $defaults = [
            'tweak_nameservers_tab'   => '1',
            'tweak_email_list'        => '1',
            'tweak_wordpress_toolkit' => '0',
            'tweak_domain_management' => '1',
            'auto_update_enabled'     => '0',
        ];
        foreach ($defaults as $key => $value) {
            $exists = Capsule::table('mod_broodle_tools_settings')
                ->where('setting_key', $key)->exists();
            if (!$exists) {
                Capsule::table('mod_broodle_tools_settings')->insert([
                    'setting_key'   => $key,
                    'setting_value' => $value,
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
            }
        }
    } catch (\Exception $e) {
        // Silently fail
    }
}

add_hook('ClientAreaProductDetailsOutput', 1, function ($vars) {
    broodle_tools_ensure_defaults();
    $serviceId = broodle_tools_get_service_id($vars);
    if (!$serviceId) return '';

    $output = '';

    // ── Nameservers Tab ──
    if (broodle_tools_ns_enabled()) {
        $d = broodle_tools_get_ns_for_service($serviceId);
        if (!empty($d['ns'])) {
            $output .= broodle_tools_build_ns_output($d['ns'], $d['ip']);
        }
    }

    // ── Email Accounts Tab ──
    if (broodle_tools_email_enabled()) {
        $cpData = broodle_tools_get_cpanel_service($serviceId);
        if ($cpData) {
            $emails = broodle_tools_get_emails($serviceId);
            $output .= broodle_tools_build_email_output($emails, $serviceId);
        }
    }

    // ── Domain Management Tab ──
    if (broodle_tools_domain_enabled()) {
        $cpData = broodle_tools_get_cpanel_service($serviceId);
        if ($cpData) {
            $output .= broodle_tools_build_domain_output($serviceId, $cpData);
        }
    }

    // ── WordPress Manager Tab ──
    if (broodle_tools_wp_enabled()) {
        $cpData = broodle_tools_get_cpanel_service($serviceId);
        if ($cpData) {
            $output .= broodle_tools_build_wp_output($serviceId);
        }
    }

    if (!empty($output)) {
        $output .= '<style>.cpanel-actions-btn{text-align:center}</style>';
        $output .= broodle_tools_shared_script();
    }

    return $output;
});

/* ─── Nameservers Output Builder ──────────────────────────── */

function broodle_tools_build_ns_output($nameservers, $serverIp)
{
    $rows = '';
    foreach ($nameservers as $i => $ns) {
        $n = $i + 1;
        $e = htmlspecialchars($ns);
        $rows .= '<div class="bns-row">'
            . '<div class="bns-badge">NS' . $n . '</div>'
            . '<div class="bns-host">' . $e . '</div>'
            . '<button type="button" class="bns-copy" data-ns="' . $e . '" title="Copy">'
            . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>'
            . '</button></div>';
    }
    if (!empty($serverIp)) {
        $eIp = htmlspecialchars($serverIp);
        $rows .= '<div class="bns-row">'
            . '<div class="bns-badge" style="background:rgba(5,150,105,.08);color:#059669">IP</div>'
            . '<div class="bns-host">' . $eIp . '</div>'
            . '<button type="button" class="bns-copy" data-ns="' . $eIp . '" title="Copy">'
            . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>'
            . '</button></div>';
    }
    return '<div id="broodle-ns-source" style="display:none"><div class="bns-card" style="margin-top:20px"><div class="bns-card-head"><div class="bns-card-head-left"><div class="bns-icon-circle"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z"/></svg></div><div><h5>Nameservers</h5><p>Point your domain to these nameservers</p></div></div></div><div class="bns-list">' . $rows . '</div></div></div>';
}

/* ─── Email Accounts Tab Builder ──────────────────────────── */

function broodle_tools_build_email_output($emails, $serviceId)
{
    $count = count($emails);
    $countLabel = $count === 1 ? '1 account' : $count . ' accounts';

    $emailRows = '';
    if ($count === 0) {
        $emailRows = '<div class="bem-empty"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg><span>No email accounts found</span></div>';
    } else {
        foreach ($emails as $email) {
            $e = htmlspecialchars($email);
            $initial = strtoupper(substr($email, 0, 1));
            $emailRows .= '<div class="bem-row" data-email="' . $e . '">'
                . '<div class="bem-avatar">' . $initial . '</div>'
                . '<div class="bem-email">' . $e . '</div>'
                . '<div class="bem-actions">'
                . '<button type="button" class="bem-btn bem-btn-login" data-email="' . $e . '" title="Open Webmail"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg><span>Login</span></button>'
                . '<button type="button" class="bem-btn bem-btn-pass" data-email="' . $e . '" title="Change Password"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><span>Password</span></button>'
                . '<button type="button" class="bem-btn bem-btn-del" data-email="' . $e . '" title="Delete"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><span>Delete</span></button>'
                . '</div></div>';
        }
    }

    return '
<div id="broodle-email-source" style="display:none" data-service-id="' . (int) $serviceId . '">
  <div class="bns-card" style="margin-top:20px">
    <div class="bns-card-head">
      <div class="bns-card-head-left">
        <div class="bns-icon-circle">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
        </div>
        <div>
          <h5>Email Accounts</h5>
          <p class="bem-count">' . $countLabel . '</p>
        </div>
      </div>
      <button type="button" class="bem-create-btn" id="bemCreateBtn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Create Email</button>
    </div>
    <div class="bns-list bem-list">' . $emailRows . '</div>
  </div>
</div>
' . broodle_tools_email_modals();
}

function broodle_tools_email_modals()
{
    return '
<!-- Create Email Modal -->
<div class="bem-overlay" id="bemCreateModal" style="display:none">
  <div class="bem-modal">
    <div class="bem-modal-head"><h5>Create Email Account</h5><button type="button" class="bem-modal-close" data-close>&times;</button></div>
    <div class="bem-modal-body">
      <div class="bem-field"><label>Email Address</label><div class="bem-input-group"><input type="text" id="bemNewUser" placeholder="username" autocomplete="off"><span class="bem-at">@</span><select id="bemNewDomain"><option>Loading...</option></select></div></div>
      <div class="bem-field"><label>Password</label><div class="bem-pass-wrap"><input type="password" id="bemNewPass" placeholder="Strong password" autocomplete="new-password"><button type="button" class="bem-pass-toggle" data-toggle-pass="bemNewPass"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
      <div class="bem-field"><label>Quota (MB)</label><input type="number" id="bemNewQuota" value="250" min="1"></div>
      <div class="bem-modal-msg" id="bemCreateMsg"></div>
    </div>
    <div class="bem-modal-foot"><button type="button" class="bem-mbtn bem-mbtn-cancel" data-close>Cancel</button><button type="button" class="bem-mbtn bem-mbtn-primary" id="bemCreateSubmit">Create Account</button></div>
  </div>
</div>
<!-- Change Password Modal -->
<div class="bem-overlay" id="bemPassModal" style="display:none">
  <div class="bem-modal">
    <div class="bem-modal-head"><h5>Change Password</h5><button type="button" class="bem-modal-close" data-close>&times;</button></div>
    <div class="bem-modal-body">
      <div class="bem-field"><label>Email</label><input type="text" id="bemPassEmail" readonly></div>
      <div class="bem-field"><label>New Password</label><div class="bem-pass-wrap"><input type="password" id="bemPassNew" placeholder="New password" autocomplete="new-password"><button type="button" class="bem-pass-toggle" data-toggle-pass="bemPassNew"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
      <div class="bem-modal-msg" id="bemPassMsg"></div>
    </div>
    <div class="bem-modal-foot"><button type="button" class="bem-mbtn bem-mbtn-cancel" data-close>Cancel</button><button type="button" class="bem-mbtn bem-mbtn-primary" id="bemPassSubmit">Update Password</button></div>
  </div>
</div>
<!-- Delete Confirmation Modal -->
<div class="bem-overlay" id="bemDelModal" style="display:none">
  <div class="bem-modal bem-modal-sm">
    <div class="bem-modal-head"><h5>Delete Email Account</h5><button type="button" class="bem-modal-close" data-close>&times;</button></div>
    <div class="bem-modal-body" style="text-align:center">
      <div style="margin:8px 0 16px"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.5" style="margin:0 auto;display:block"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
      <p style="margin:0 0 4px;font-size:14px;color:var(--heading-color,#111827)">Are you sure you want to delete</p>
      <p style="margin:0;font-size:15px;font-weight:600;color:#ef4444" id="bemDelEmail"></p>
      <p style="margin:8px 0 0;font-size:12px;color:var(--text-muted,#9ca3af)">This action cannot be undone.</p>
      <div class="bem-modal-msg" id="bemDelMsg"></div>
    </div>
    <div class="bem-modal-foot"><button type="button" class="bem-mbtn bem-mbtn-cancel" data-close>Cancel</button><button type="button" class="bem-mbtn bem-mbtn-danger" id="bemDelSubmit">Delete</button></div>
  </div>
</div>';
}

/* ─── Shared CSS + JS ─────────────────────────────────────── */

function broodle_tools_shared_script()
{
    return '
<style>
/* Hide default WHMCS Quick Create Email Account section */
.quick-create-email,.quick-create-email-section,[class*="quick-create-email"]{display:none!important}

.bns-card{background:var(--card-bg,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:12px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
.bns-card-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border-color,#f3f4f6)}
.bns-card-head-left{display:flex;align-items:center;gap:12px}
.bns-icon-circle{width:38px;height:38px;background:#0a5ed3;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0}
.bns-card-head h5{margin:0;font-size:15px;font-weight:600;color:var(--heading-color,#111827)}
.bns-card-head p{margin:2px 0 0;font-size:12px;color:var(--text-muted,#6b7280)}
.bns-list{padding:8px 10px}
.bns-row,.bem-row{display:flex;align-items:center;gap:14px;padding:13px 14px;border-radius:9px;transition:background .15s}
.bns-row:hover,.bem-row:hover{background:var(--input-bg,#f9fafb)}
.bns-row+.bns-row,.bem-row+.bem-row{border-top:1px solid var(--border-color,#f3f4f6)}
.bns-badge{width:38px;height:28px;background:rgba(10,94,211,.08);color:#0a5ed3;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;letter-spacing:.3px;flex-shrink:0}
.bns-host{flex:1;font-size:14px;font-weight:600;color:var(--heading-color,#111827);font-family:"SFMono-Regular",Consolas,"Liberation Mono",Menlo,monospace}
.bns-copy{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid var(--border-color,#e5e7eb);border-radius:7px;background:var(--card-bg,#fff);color:var(--text-muted,#9ca3af);cursor:pointer;transition:all .15s;flex-shrink:0}
.bns-copy:hover{color:#0a5ed3;border-color:#0a5ed3}
.bns-copy.copied{color:#fff;background:#059669;border-color:#059669}
.bem-avatar{width:34px;height:34px;border-radius:50%;background:rgba(10,94,211,.08);color:#0a5ed3;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}
.bem-email{flex:1;font-size:14px;font-weight:500;color:var(--heading-color,#111827);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bem-empty{padding:30px 22px;text-align:center;color:var(--text-muted,#9ca3af);font-size:14px;display:flex;flex-direction:column;align-items:center;gap:10px}
.bem-empty svg{opacity:.4}
.bem-actions{display:flex;gap:6px;flex-shrink:0}
.bem-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);transition:all .15s;white-space:nowrap}
.bem-btn span{display:none}
.bem-btn:hover span{display:inline}
.bem-btn-login{color:#0a5ed3}.bem-btn-login:hover{background:rgba(10,94,211,.06);border-color:#0a5ed3}
.bem-btn-pass{color:#d97706}.bem-btn-pass:hover{background:rgba(217,119,6,.06);border-color:#d97706}
.bem-btn-del{color:#ef4444}.bem-btn-del:hover{background:rgba(239,68,68,.06);border-color:#ef4444}
.bem-create-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:#0a5ed3;color:#fff;transition:background .15s}
.bem-create-btn:hover{background:#0950b3}
.bem-overlay{position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:20px;animation:bemFadeIn .2s}
@keyframes bemFadeIn{from{opacity:0}to{opacity:1}}
.bem-modal{background:var(--card-bg,#fff);border-radius:14px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:bemSlideUp .25s}
.bem-modal-sm{max-width:380px}
@keyframes bemSlideUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.bem-modal-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border-color,#f3f4f6)}
.bem-modal-head h5{margin:0;font-size:16px;font-weight:600;color:var(--heading-color,#111827)}
.bem-modal-close{width:30px;height:30px;display:flex;align-items:center;justify-content:center;border:none;background:none;font-size:20px;color:var(--text-muted,#9ca3af);cursor:pointer;border-radius:6px}
.bem-modal-close:hover{background:var(--input-bg,#f3f4f6);color:var(--heading-color,#111827)}
.bem-modal-body{padding:20px 22px}
.bem-modal-foot{display:flex;justify-content:flex-end;gap:8px;padding:14px 22px;border-top:1px solid var(--border-color,#f3f4f6)}
.bem-field{margin-bottom:16px}.bem-field:last-child{margin-bottom:0}
.bem-field label{display:block;font-size:12px;font-weight:600;color:var(--text-muted,#6b7280);margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px}
.bem-field input,.bem-field select{width:100%;padding:9px 12px;border:1px solid var(--border-color,#d1d5db);border-radius:8px;font-size:14px;color:var(--heading-color,#111827);background:var(--input-bg,#fff);outline:none;transition:border-color .15s}
.bem-field input:focus,.bem-field select:focus{border-color:#0a5ed3;box-shadow:0 0 0 3px rgba(10,94,211,.1)}
.bem-field input[readonly]{background:var(--input-bg,#f9fafb);color:var(--text-muted,#6b7280)}
.bem-input-group{display:flex;align-items:center;border:1px solid var(--border-color,#d1d5db);border-radius:8px;overflow:hidden;transition:border-color .15s}
.bem-input-group:focus-within{border-color:#0a5ed3;box-shadow:0 0 0 3px rgba(10,94,211,.1)}
.bem-input-group input{border:none;border-radius:0;flex:1;min-width:0}.bem-input-group input:focus{box-shadow:none}
.bem-at{padding:0 8px;font-size:14px;color:var(--text-muted,#9ca3af);font-weight:600;background:var(--input-bg,#f9fafb);border-left:1px solid var(--border-color,#e5e7eb);border-right:1px solid var(--border-color,#e5e7eb);height:100%;display:flex;align-items:center}
.bem-input-group select{border:none;border-radius:0;flex:1;min-width:0}.bem-input-group select:focus{box-shadow:none}
.bem-pass-wrap{position:relative}.bem-pass-wrap input{width:100%;padding-right:40px}
.bem-pass-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted,#9ca3af);cursor:pointer;padding:4px}
.bem-pass-toggle:hover{color:var(--heading-color,#111827)}
.bem-mbtn{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .15s}
.bem-mbtn-cancel{background:var(--input-bg,#f3f4f6);color:var(--heading-color,#374151)}.bem-mbtn-cancel:hover{background:#e5e7eb}
.bem-mbtn-primary{background:#0a5ed3;color:#fff}.bem-mbtn-primary:hover{background:#0950b3}
.bem-mbtn-danger{background:#ef4444;color:#fff}.bem-mbtn-danger:hover{background:#dc2626}
.bem-mbtn:disabled{opacity:.5;cursor:not-allowed}
.bem-modal-msg{margin-top:12px;padding:8px 12px;border-radius:6px;font-size:13px;display:none}
.bem-modal-msg.success{display:block;background:rgba(5,150,105,.08);color:#059669}
.bem-modal-msg.error{display:block;background:rgba(239,68,68,.08);color:#ef4444}
@media(max-width:600px){.bem-btn span{display:none!important}.bem-actions{gap:4px}.bem-btn{padding:5px 7px}}
[data-theme="dark"] .bns-card,.dark-mode .bns-card{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bns-row:hover,[data-theme="dark"] .bem-row:hover,.dark-mode .bns-row:hover,.dark-mode .bem-row:hover{background:var(--input-bg,#111827)}
[data-theme="dark"] .bns-copy,.dark-mode .bns-copy{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bem-btn,.dark-mode .bem-btn{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bem-modal,.dark-mode .bem-modal{background:var(--card-bg,#1f2937)}
[data-theme="dark"] .bem-field input,[data-theme="dark"] .bem-field select,.dark-mode .bem-field input,.dark-mode .bem-field select{background:var(--input-bg,#111827);border-color:var(--border-color,#374151);color:var(--heading-color,#f3f4f6)}
[data-theme="dark"] .bem-input-group,.dark-mode .bem-input-group{border-color:var(--border-color,#374151)}
[data-theme="dark"] .bem-at,.dark-mode .bem-at{background:var(--input-bg,#111827);border-color:var(--border-color,#374151)}

/* Domain Management */
.bdm-row{display:flex;align-items:center;gap:14px;padding:13px 14px;border-radius:9px;transition:background .15s}
.bdm-row:hover{background:var(--input-bg,#f9fafb)}
.bdm-row+.bdm-row{border-top:1px solid var(--border-color,#f3f4f6)}
.bdm-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.bdm-icon.main{background:rgba(10,94,211,.08);color:#0a5ed3}
.bdm-icon.addon{background:rgba(5,150,105,.08);color:#059669}
.bdm-icon.sub{background:rgba(124,58,237,.08);color:#7c3aed}
.bdm-icon.parked{background:rgba(217,119,6,.08);color:#d97706}
.bdm-info{flex:1;min-width:0;display:flex;align-items:center;gap:8px;overflow:hidden}
.bdm-name{font-size:14px;font-weight:600;color:var(--heading-color,#111827);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bdm-badge{padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;flex-shrink:0}
.bdm-badge-main{background:rgba(10,94,211,.08);color:#0a5ed3}
.bdm-badge-addon{background:rgba(5,150,105,.08);color:#059669}
.bdm-badge-sub{background:rgba(124,58,237,.08);color:#7c3aed}
.bdm-badge-parked{background:rgba(217,119,6,.08);color:#d97706}
.bdm-actions{display:flex;gap:6px;flex-shrink:0}
.bdm-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);transition:all .15s;white-space:nowrap;text-decoration:none}
.bdm-btn span{display:none}
.bdm-btn:hover span{display:inline}
.bdm-btn-visit{color:#0a5ed3}.bdm-btn-visit:hover{background:rgba(10,94,211,.06);border-color:#0a5ed3;text-decoration:none;color:#0a5ed3}
.bdm-btn-del{color:#ef4444}.bdm-btn-del:hover{background:rgba(239,68,68,.06);border-color:#ef4444}
.bdm-empty{padding:30px 22px;text-align:center;color:var(--text-muted,#9ca3af);font-size:14px;display:flex;flex-direction:column;align-items:center;gap:10px}
.bdm-empty svg{opacity:.4}
.bdm-head-btns{display:flex;gap:8px}
.bdm-sub-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#d1d5db);background:var(--card-bg,#fff);color:var(--heading-color,#374151);transition:all .15s}
.bdm-sub-btn:hover{border-color:#0a5ed3;color:#0a5ed3;background:rgba(10,94,211,.04)}
.bdm-docroot-wrap{display:flex;align-items:center;border:1px solid var(--border-color,#d1d5db);border-radius:8px;overflow:hidden;transition:border-color .15s}
.bdm-docroot-wrap:focus-within{border-color:#0a5ed3;box-shadow:0 0 0 3px rgba(10,94,211,.1)}
.bdm-docroot-prefix{padding:9px 10px;font-size:13px;color:var(--text-muted,#9ca3af);background:var(--input-bg,#f9fafb);border-right:1px solid var(--border-color,#e5e7eb);white-space:nowrap;flex-shrink:0}
.bdm-docroot-wrap input{border:none;border-radius:0;flex:1;min-width:0;padding:9px 12px;font-size:14px;color:var(--heading-color,#111827);background:var(--input-bg,#fff);outline:none}
.bdm-docroot-wrap input:focus{box-shadow:none}
[data-theme="dark"] .bdm-row:hover,.dark-mode .bdm-row:hover{background:var(--input-bg,#111827)}
[data-theme="dark"] .bdm-btn,.dark-mode .bdm-btn{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bdm-sub-btn,.dark-mode .bdm-sub-btn{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bdm-docroot-wrap,.dark-mode .bdm-docroot-wrap{border-color:var(--border-color,#374151)}
[data-theme="dark"] .bdm-docroot-prefix,.dark-mode .bdm-docroot-prefix{background:var(--input-bg,#111827);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bdm-docroot-wrap input,.dark-mode .bdm-docroot-wrap input{background:var(--input-bg,#111827);color:var(--heading-color,#f3f4f6)}
@media(max-width:600px){.bdm-btn span{display:none!important}.bdm-actions{gap:4px}.bdm-btn{padding:5px 7px}.bdm-head-btns{flex-direction:column;gap:4px}.bdm-info{flex-direction:column;align-items:flex-start;gap:4px}}

/* WordPress Toolkit */
.bwp-card{background:var(--card-bg,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:12px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
.bwp-card-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border-color,#f3f4f6)}
.bwp-card-head-left{display:flex;align-items:center;gap:12px}
.bwp-icon-circle{width:38px;height:38px;background:#21759b;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0}
.bwp-card-head h5{margin:0;font-size:15px;font-weight:600;color:var(--heading-color,#111827)}
.bwp-subtitle{margin:2px 0 0;font-size:12px;color:var(--text-muted,#6b7280)}
.bwp-head-actions{display:flex;gap:8px}
.bwp-scan-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#d1d5db);background:var(--card-bg,#fff);color:var(--heading-color,#374151);transition:all .15s}
.bwp-scan-btn:hover{border-color:#21759b;color:#21759b;background:rgba(33,117,155,.04)}
.bwp-body{padding:0}
.bwp-loading{padding:40px 22px;text-align:center;color:var(--text-muted,#9ca3af);font-size:14px;display:flex;flex-direction:column;align-items:center;gap:12px}
.bwp-spinner{width:28px;height:28px;border:3px solid var(--border-color,#e5e7eb);border-top-color:#21759b;border-radius:50%;animation:bwpSpin .7s linear infinite}
@keyframes bwpSpin{to{transform:rotate(360deg)}}
.bwp-empty{padding:40px 22px;text-align:center;color:var(--text-muted,#9ca3af);font-size:14px;display:flex;flex-direction:column;align-items:center;gap:12px}
.bwp-empty svg{opacity:.3}
.bwp-list{padding:8px 10px}
.bwp-site{display:flex;align-items:center;gap:14px;padding:14px;border-radius:9px;transition:background .15s;cursor:pointer}
.bwp-site:hover{background:var(--input-bg,#f9fafb)}
.bwp-site+.bwp-site{border-top:1px solid var(--border-color,#f3f4f6)}
.bwp-site-icon{width:40px;height:40px;border-radius:10px;background:rgba(33,117,155,.08);color:#21759b;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.bwp-site-info{flex:1;min-width:0}
.bwp-site-domain{font-size:14px;font-weight:600;color:var(--heading-color,#111827);margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bwp-site-meta{font-size:12px;color:var(--text-muted,#6b7280);margin:2px 0 0;display:flex;gap:12px;flex-wrap:wrap}
.bwp-site-meta span{display:inline-flex;align-items:center;gap:4px}
.bwp-site-actions{display:flex;gap:6px;flex-shrink:0}
.bwp-action-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:7px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);transition:all .15s;white-space:nowrap;color:var(--heading-color,#374151)}
.bwp-action-btn:hover{border-color:#21759b;color:#21759b}
.bwp-action-btn.primary{background:#21759b;color:#fff;border-color:#21759b}
.bwp-action-btn.primary:hover{background:#1a5f7e}
.bwp-overlay{position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;padding:24px;animation:bwpFadeIn .2s;backdrop-filter:blur(4px)}
@keyframes bwpFadeIn{from{opacity:0}to{opacity:1}}
.bwp-detail-panel{width:100%;max-width:900px;max-height:90vh;background:var(--card-bg,#fff);border-radius:16px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,.25),0 0 0 1px rgba(0,0,0,.05);animation:bwpPopIn .3s cubic-bezier(.34,1.56,.64,1)}
@keyframes bwpPopIn{from{opacity:0;transform:scale(.95) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}
.bwp-detail-head{display:flex;align-items:center;gap:12px;padding:18px 24px;border-bottom:1px solid var(--border-color,#f3f4f6);background:var(--card-bg,#fff);flex-shrink:0}
.bwp-detail-head h5{flex:1;margin:0;font-size:17px;font-weight:700;color:var(--heading-color,#111827);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bwp-back-btn{display:none}
.bwp-close-btn{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border:none;background:var(--input-bg,#f3f4f6);color:var(--text-muted,#6b7280);cursor:pointer;border-radius:8px;font-size:18px;transition:all .15s;flex-shrink:0}
.bwp-close-btn:hover{background:rgba(239,68,68,.08);color:#ef4444}
.bwp-detail-tabs{display:flex;gap:0;padding:0 24px;border-bottom:1px solid var(--border-color,#f3f4f6);background:var(--card-bg,#fff);flex-shrink:0;overflow-x:auto}
.bwp-tab{padding:12px 18px;font-size:13px;font-weight:600;color:var(--text-muted,#6b7280);cursor:pointer;border:none;background:none;border-bottom:2px solid transparent;transition:all .15s;white-space:nowrap}
.bwp-tab:hover{color:var(--heading-color,#111827)}
.bwp-tab.active{color:#21759b;border-bottom-color:#21759b}
.bwp-detail-body{flex:1;overflow-y:auto;padding:0}
.bwp-tab-content{display:none;padding:24px}
.bwp-tab-content.active{display:block}
.bwp-preview-wrap{border-radius:12px;overflow:hidden;border:1px solid var(--border-color,#e5e7eb);background:#f9fafb;position:relative;flex-shrink:0;width:340px}
.bwp-preview-bar{display:flex;align-items:center;gap:8px;padding:6px 12px;background:var(--input-bg,#f3f4f6);border-bottom:1px solid var(--border-color,#e5e7eb)}
.bwp-preview-dots{display:flex;gap:5px}
.bwp-preview-dots span{width:8px;height:8px;border-radius:50%}
.bwp-preview-dots span:nth-child(1){background:#ef4444}
.bwp-preview-dots span:nth-child(2){background:#f59e0b}
.bwp-preview-dots span:nth-child(3){background:#22c55e}
.bwp-preview-url{flex:1;font-size:10px;color:var(--text-muted,#6b7280);background:var(--card-bg,#fff);padding:3px 8px;border-radius:4px;border:1px solid var(--border-color,#e5e7eb);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:"SFMono-Regular",Consolas,monospace}
.bwp-preview-frame{width:1280px;height:800px;border:none;background:#fff;transform:scale(.265);transform-origin:top left}
.bwp-preview-frame-wrap{width:100%;height:212px;overflow:hidden;position:relative}
.bwp-preview-overlay{position:absolute;inset:0;top:28px;cursor:pointer;z-index:1}
.bwp-preview-overlay:hover::after{content:"Click to visit site";position:absolute;bottom:8px;right:8px;background:rgba(0,0,0,.7);color:#fff;padding:4px 10px;border-radius:5px;font-size:11px;font-weight:600}
.bwp-overview-hero{display:flex;gap:20px;margin-bottom:20px;align-items:flex-start}
.bwp-overview-right{flex:1;min-width:0}
.bwp-site-header{display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--input-bg,#f9fafb);border-radius:10px;border:1px solid var(--border-color,#f3f4f6);margin-bottom:12px}
.bwp-site-header-icon{width:40px;height:40px;border-radius:10px;background:#21759b;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.bwp-site-header-info{flex:1;min-width:0}
.bwp-site-header-info h4{margin:0;font-size:14px;font-weight:700;color:var(--heading-color,#111827);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bwp-site-header-info p{margin:2px 0 0;font-size:11px;color:var(--text-muted,#6b7280);display:flex;gap:8px;flex-wrap:wrap}
.bwp-site-header-info p span{display:inline-flex;align-items:center;gap:3px}
.bwp-overview-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}
.bwp-stat{padding:10px 12px;background:var(--input-bg,#f9fafb);border-radius:8px;border:1px solid var(--border-color,#f3f4f6)}
.bwp-stat-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted,#9ca3af);margin:0 0 2px}
.bwp-stat-value{font-size:13px;font-weight:600;color:var(--heading-color,#111827);margin:0;word-break:break-all}
.bwp-quick-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.bwp-item-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border-color,#f3f4f6)}
.bwp-item-row:last-child{border-bottom:none}
.bwp-item-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:16px}
.bwp-item-icon.plugin{background:rgba(10,94,211,.08);color:#0a5ed3}
.bwp-item-icon.theme{background:rgba(124,58,237,.08);color:#7c3aed}
.bwp-item-info{flex:1;min-width:0}
.bwp-item-name{font-size:13px;font-weight:600;color:var(--heading-color,#111827);margin:0}
.bwp-item-detail{font-size:11px;color:var(--text-muted,#6b7280);margin:2px 0 0}
.bwp-item-actions{display:flex;gap:4px;flex-shrink:0}
.bwp-item-btn{padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);transition:all .15s}
.bwp-item-btn:hover{border-color:#21759b;color:#21759b}
.bwp-item-btn.active-state{color:#059669;border-color:#059669}
.bwp-item-btn.inactive-state{color:#d97706;border-color:#d97706}
.bwp-item-btn.update{color:#0a5ed3;border-color:#0a5ed3}
.bwp-item-btn.delete{color:#ef4444;border-color:#ef4444}
.bwp-item-btn:disabled{opacity:.5;cursor:not-allowed}
.bwp-status-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.bwp-status-badge.active{background:rgba(5,150,101,.08);color:#059669}
.bwp-status-badge.inactive{background:rgba(217,119,6,.08);color:#d97706}
.bwp-status-badge.update-available{background:rgba(10,94,211,.08);color:#0a5ed3}
.bwp-security-item{display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid var(--border-color,#f3f4f6)}
.bwp-security-item:last-child{border-bottom:none}
.bwp-sec-icon{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px}
.bwp-sec-icon.ok{background:rgba(5,150,101,.08);color:#059669}
.bwp-sec-icon.warning{background:rgba(217,119,6,.08);color:#d97706}
.bwp-sec-icon.danger{background:rgba(239,68,68,.08);color:#ef4444}
.bwp-sec-info{flex:1}
.bwp-sec-label{font-size:13px;font-weight:600;color:var(--heading-color,#111827);margin:0}
.bwp-sec-detail{font-size:12px;color:var(--text-muted,#6b7280);margin:2px 0 0}
.bwp-sec-value{font-size:12px;font-weight:600;flex-shrink:0}
.bwp-sec-value.ok{color:#059669}
.bwp-sec-value.warning{color:#d97706}
.bwp-sec-value.danger{color:#ef4444}
.bwp-msg{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:12px}
.bwp-msg.success{background:rgba(5,150,101,.08);color:#059669}
.bwp-msg.error{background:rgba(239,68,68,.08);color:#ef4444}
.bwp-msg.info{background:rgba(10,94,211,.08);color:#0a5ed3}
@media(max-width:700px){.bwp-overview-grid{grid-template-columns:1fr 1fr}.bwp-detail-panel{max-width:100%;max-height:100vh;border-radius:0}.bwp-preview-frame-wrap{height:160px}.bwp-overview-hero{flex-direction:column}.bwp-preview-wrap{width:100%}}
@media(max-width:500px){.bwp-overview-grid{grid-template-columns:1fr}.bwp-site-actions{flex-direction:column}.bwp-theme-grid{grid-template-columns:1fr}.bwp-overview-hero{flex-direction:column}.bwp-preview-wrap{width:100%}}

/* Plugin icon images */
.bwp-item-icon-img{width:40px;height:40px;border-radius:10px;overflow:hidden;flex-shrink:0;position:relative}
.bwp-item-icon-img img{width:100%;height:100%;object-fit:cover;border-radius:10px}
.bwp-item-icon-img .bwp-item-icon{width:40px;height:40px;border-radius:10px}

/* Tab summary bar */
.bwp-tab-summary{display:flex;gap:16px;padding:12px 16px;background:var(--input-bg,#f9fafb);border-radius:10px;margin-bottom:16px;border:1px solid var(--border-color,#f3f4f6)}
.bwp-tab-stat{font-size:13px;color:var(--text-muted,#6b7280);display:flex;align-items:center;gap:5px}
.bwp-tab-stat-num{font-weight:700;font-size:15px}

/* Theme grid cards */
.bwp-theme-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
.bwp-theme-card{border:1px solid var(--border-color,#e5e7eb);border-radius:12px;overflow:hidden;background:var(--card-bg,#fff);transition:box-shadow .2s,border-color .2s}
.bwp-theme-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08);border-color:var(--border-color,#d1d5db)}
.bwp-theme-active{border-color:#21759b;box-shadow:0 0 0 2px rgba(33,117,155,.15)}
.bwp-theme-screenshot{position:relative;width:100%;padding-top:66%;background:var(--input-bg,#f3f4f6);overflow:hidden}
.bwp-theme-screenshot img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.bwp-theme-placeholder{position:absolute;inset:0;align-items:center;justify-content:center;background:var(--input-bg,#f3f4f6)}
.bwp-theme-active-badge{position:absolute;top:8px;right:8px;background:#21759b;color:#fff;padding:3px 10px;border-radius:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.bwp-theme-info{padding:12px 14px}
.bwp-theme-name{font-size:13px;font-weight:600;color:var(--heading-color,#111827);margin:0 0 4px}
.bwp-theme-ver{font-size:11px;color:var(--text-muted,#6b7280);margin:0 0 8px}
.bwp-theme-actions{display:flex;gap:6px}

/* Security summary */
.bwp-sec-summary{margin-bottom:20px;padding:16px;background:var(--input-bg,#f9fafb);border-radius:12px;border:1px solid var(--border-color,#f3f4f6)}
.bwp-sec-summary-bar{height:8px;background:var(--border-color,#e5e7eb);border-radius:4px;overflow:hidden;margin-bottom:10px}
.bwp-sec-summary-fill{height:100%;background:linear-gradient(90deg,#059669,#22c55e);border-radius:4px;transition:width .5s}
.bwp-sec-summary-text{display:flex;gap:16px;font-size:13px}
.bwp-sec-summary-text span{display:flex;align-items:center;gap:4px}

@media(max-width:700px){.bwp-theme-grid{grid-template-columns:1fr 1fr}}
</style>

<script>
(function(){
    "use strict";
    var ajaxUrl="modules/addons/broodle_whmcs_tools/ajax.php";
    var serviceId=0;

    function broodleInit(){
        // Hide default WHMCS Quick Create Email Account section
        hideDefaultEmailSection();

        var tabNav=document.querySelector("ul.panel-tabs.nav.nav-tabs");
        if(!tabNav) tabNav=document.querySelector(".section-body ul.nav.nav-tabs");
        var panel=tabNav?(tabNav.closest(".panel")||tabNav.parentNode):null;
        var tabContent=panel?panel.querySelector(".tab-content"):null;

        // Nameservers → tab
        var nsSrc=document.getElementById("broodle-ns-source");
        if(nsSrc){
            var nsHtml=nsSrc.innerHTML;nsSrc.parentNode.removeChild(nsSrc);
            if(!document.getElementById("broodleNsInfo")){
                if(tabNav&&tabContent){
                    var li=document.createElement("li");
                    li.innerHTML="<a href=\"#broodleNsInfo\" data-toggle=\"tab\"><i class=\"fas fa-globe\"></i> Nameservers</a>";
                    tabNav.appendChild(li);
                    var p=document.createElement("div");
                    p.className="panel-body tab-pane";p.id="broodleNsInfo";p.innerHTML=nsHtml;
                    tabContent.appendChild(p);bindCopy(p);
                }else{
                    var to=document.getElementById("tabOverview");
                    if(to&&to.parentNode){var p2=document.createElement("div");p2.id="broodleNsInfo";p2.className="tab-pane fade";p2.innerHTML=nsHtml;to.parentNode.appendChild(p2);bindCopy(p2);}
                }
            }
        }

        // Email → tab (before WordPress)
        var emSrc=document.getElementById("broodle-email-source");
        if(emSrc){
            serviceId=emSrc.getAttribute("data-service-id")||0;
            var emHtml=emSrc.innerHTML;emSrc.parentNode.removeChild(emSrc);
            if(!document.getElementById("broodleEmailInfo")){
                if(tabNav&&tabContent){
                    var eLi=document.createElement("li");
                    eLi.innerHTML="<a href=\"#broodleEmailInfo\" data-toggle=\"tab\"><i class=\"fas fa-envelope\"></i> Email Accounts</a>";
                    tabNav.appendChild(eLi);
                    var eP=document.createElement("div");
                    eP.className="panel-body tab-pane";eP.id="broodleEmailInfo";eP.innerHTML=emHtml;
                    tabContent.appendChild(eP);bindCopy(eP);bindEmailActions(eP);
                }else{
                    var to2=document.getElementById("tabOverview");
                    if(to2&&to2.parentNode){var eP2=document.createElement("div");eP2.id="broodleEmailInfo";eP2.className="tab-pane fade";eP2.innerHTML=emHtml;to2.parentNode.appendChild(eP2);bindCopy(eP2);bindEmailActions(eP2);}
                }
            }
        }

        // Domains → replace default WHMCS Domains tab
        var dmSrc=document.getElementById("broodle-domain-source");
        if(dmSrc){
            if(!serviceId) serviceId=dmSrc.getAttribute("data-service-id")||0;
            var dmHtml=dmSrc.innerHTML;dmSrc.parentNode.removeChild(dmSrc);
            if(!document.getElementById("broodleDomainInfo")){
                if(tabNav&&tabContent){
                    // Find and hide the default WHMCS Domains tab
                    var defaultDomainTab=null;
                    var defaultDomainPane=null;
                    var tabLinks=tabNav.querySelectorAll("li");
                    for(var i=0;i<tabLinks.length;i++){
                        var a=tabLinks[i].querySelector("a");
                        if(a){
                            var href=a.getAttribute("href")||"";
                            var txt=(a.textContent||"").toLowerCase().trim();
                            if(href==="#tabDomains"||txt==="domains"||txt.indexOf("domain")!==-1){
                                defaultDomainTab=tabLinks[i];
                                var paneId=href.replace("#","");
                                if(paneId) defaultDomainPane=tabContent.querySelector("#"+paneId);
                                break;
                            }
                        }
                    }
                    // Create our replacement tab
                    var dLi=document.createElement("li");
                    dLi.innerHTML="<a href=\"#broodleDomainInfo\" data-toggle=\"tab\"><i class=\"fas fa-sitemap\"></i> Domains</a>";
                    // Insert in place of default, or append
                    if(defaultDomainTab){
                        defaultDomainTab.parentNode.insertBefore(dLi,defaultDomainTab);
                        defaultDomainTab.style.display="none";
                        if(defaultDomainPane) defaultDomainPane.style.display="none";
                    }else{
                        tabNav.appendChild(dLi);
                    }
                    var dP=document.createElement("div");
                    dP.className="panel-body tab-pane";dP.id="broodleDomainInfo";dP.innerHTML=dmHtml;
                    tabContent.appendChild(dP);bindDomainActions(dP);
                }else{
                    var to3=document.getElementById("tabOverview");
                    if(to3&&to3.parentNode){var dP2=document.createElement("div");dP2.id="broodleDomainInfo";dP2.className="tab-pane fade";dP2.innerHTML=dmHtml;to3.parentNode.appendChild(dP2);bindDomainActions(dP2);}
                }
            }
        }

        // Modal handlers
        document.querySelectorAll("[data-close]").forEach(function(b){b.addEventListener("click",function(){var m=this.closest(".bem-overlay");if(m)m.style.display="none";});});
        document.querySelectorAll(".bem-overlay").forEach(function(o){o.addEventListener("click",function(e){if(e.target===o)o.style.display="none";});});
        document.querySelectorAll("[data-toggle-pass]").forEach(function(b){b.addEventListener("click",function(){var id=this.getAttribute("data-toggle-pass");var inp=document.getElementById(id);if(inp){inp.type=inp.type==="password"?"text":"password";}});});

        var createBtn=document.getElementById("bemCreateBtn");
        if(createBtn)createBtn.addEventListener("click",openCreateModal);
        var createSubmit=document.getElementById("bemCreateSubmit");
        if(createSubmit)createSubmit.addEventListener("click",submitCreate);
        var passSubmit=document.getElementById("bemPassSubmit");
        if(passSubmit)passSubmit.addEventListener("click",submitPassword);
        var delSubmit=document.getElementById("bemDelSubmit");
        if(delSubmit)delSubmit.addEventListener("click",submitDelete);

        // Domain modal bindings
        var addAddonBtn=document.getElementById("bdmAddAddonBtn");
        if(addAddonBtn)addAddonBtn.addEventListener("click",openAddonModal);
        var addSubBtn=document.getElementById("bdmAddSubBtn");
        if(addSubBtn)addSubBtn.addEventListener("click",openSubModal);
        var addonSubmit=document.getElementById("bdmAddonSubmit");
        if(addonSubmit)addonSubmit.addEventListener("click",submitAddonDomain);
        var subSubmit=document.getElementById("bdmSubSubmit");
        if(subSubmit)subSubmit.addEventListener("click",submitSubdomain);
        var domDelSubmit=document.getElementById("bdmDelSubmit");
        if(domDelSubmit)domDelSubmit.addEventListener("click",submitDomainDelete);
    }

    function hideDefaultEmailSection(){
        // Hide WHMCS default "Quick Create Email Account" section by finding it via text content
        var allHeaders=document.querySelectorAll("h3,h4,h5,.panel-heading,.section-header,.card-header");
        allHeaders.forEach(function(h){
            var txt=(h.textContent||"").toLowerCase().trim();
            if(txt.indexOf("quick create email")!==-1||txt.indexOf("create email account")!==-1){
                var sec=h.closest(".panel,.card,.section-body,.form-group")||h.parentNode;
                if(sec)sec.style.display="none";
            }
        });
        // Also try hiding by form action or known class patterns
        var forms=document.querySelectorAll("form[action*=\"emailquickcreate\"],form[action*=\"quickcreate\"],.email-quick-create");
        forms.forEach(function(f){
            var sec=f.closest(".panel,.card,.section-body")||f.parentNode;
            if(sec)sec.style.display="none";
        });
    }

    function bindCopy(c){c.querySelectorAll(".bns-copy").forEach(function(b){b.addEventListener("click",function(){doCopy(this.getAttribute("data-ns"),this);});});}
    function doCopy(t,btn){if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(function(){done(btn);});}else{var ta=document.createElement("textarea");ta.value=t;ta.style.cssText="position:fixed;opacity:0";document.body.appendChild(ta);ta.select();document.execCommand("copy");document.body.removeChild(ta);done(btn);}}
    function done(btn){btn.classList.add("copied");setTimeout(function(){btn.classList.remove("copied");},1500);}

    function bindEmailActions(c){
        c.querySelectorAll(".bem-btn-login").forEach(function(b){b.addEventListener("click",function(){doLogin(this.getAttribute("data-email"));});});
        c.querySelectorAll(".bem-btn-pass").forEach(function(b){b.addEventListener("click",function(){openPassModal(this.getAttribute("data-email"));});});
        c.querySelectorAll(".bem-btn-del").forEach(function(b){b.addEventListener("click",function(){openDelModal(this.getAttribute("data-email"));});});
    }

    function ajaxPost(data,cb){
        var fd=new FormData();for(var k in data)fd.append(k,data[k]);
        fd.append("service_id",serviceId);
        var x=new XMLHttpRequest();x.open("POST",ajaxUrl,true);
        x.onload=function(){try{cb(JSON.parse(x.responseText));}catch(e){cb({success:false,message:"Invalid response"});}};
        x.onerror=function(){cb({success:false,message:"Network error"});};
        x.send(fd);
    }
    function showMsg(el,msg,ok){el.textContent=msg;el.className="bem-modal-msg "+(ok?"success":"error");el.style.display="block";}

    function doLogin(email){ajaxPost({action:"webmail_login",email:email},function(r){if(r.success&&r.url){window.open(r.url,"_blank");}else{alert(r.message||"Could not open webmail");}});}

    var domainsLoaded=false;
    function openCreateModal(){
        document.getElementById("bemCreateModal").style.display="flex";
        document.getElementById("bemNewUser").value="";document.getElementById("bemNewPass").value="";
        document.getElementById("bemNewQuota").value="250";document.getElementById("bemCreateMsg").style.display="none";
        if(!domainsLoaded){ajaxPost({action:"get_domains"},function(r){
            var sel=document.getElementById("bemNewDomain");sel.innerHTML="";
            if(r.success&&r.domains&&r.domains.length){r.domains.forEach(function(d){var o=document.createElement("option");o.value=d;o.textContent=d;sel.appendChild(o);});}
            else{sel.innerHTML="<option>No domains</option>";}domainsLoaded=true;
        });}
    }
    function submitCreate(){
        var btn=document.getElementById("bemCreateSubmit"),msg=document.getElementById("bemCreateMsg");
        var user=document.getElementById("bemNewUser").value.trim(),pass=document.getElementById("bemNewPass").value;
        var domain=document.getElementById("bemNewDomain").value,quota=document.getElementById("bemNewQuota").value;
        if(!user||!pass){showMsg(msg,"Please fill in all fields",false);return;}
        btn.disabled=true;btn.textContent="Creating...";
        ajaxPost({action:"create_email",email_user:user,email_pass:pass,domain:domain,quota:quota},function(r){
            btn.disabled=false;btn.textContent="Create Account";showMsg(msg,r.message,r.success);
            if(r.success)setTimeout(function(){location.reload();},1200);
        });
    }

    function openPassModal(email){
        document.getElementById("bemPassModal").style.display="flex";
        document.getElementById("bemPassEmail").value=email;document.getElementById("bemPassNew").value="";
        document.getElementById("bemPassMsg").style.display="none";
    }
    function submitPassword(){
        var btn=document.getElementById("bemPassSubmit"),msg=document.getElementById("bemPassMsg");
        var email=document.getElementById("bemPassEmail").value,pass=document.getElementById("bemPassNew").value;
        if(!pass){showMsg(msg,"Please enter a new password",false);return;}
        btn.disabled=true;btn.textContent="Updating...";
        ajaxPost({action:"change_password",email:email,new_pass:pass},function(r){
            btn.disabled=false;btn.textContent="Update Password";showMsg(msg,r.message,r.success);
            if(r.success)setTimeout(function(){document.getElementById("bemPassModal").style.display="none";},1500);
        });
    }

    var delTarget="";
    function openDelModal(email){
        delTarget=email;document.getElementById("bemDelModal").style.display="flex";
        document.getElementById("bemDelEmail").textContent=email;document.getElementById("bemDelMsg").style.display="none";
    }
    function submitDelete(){
        var btn=document.getElementById("bemDelSubmit"),msg=document.getElementById("bemDelMsg");
        btn.disabled=true;btn.textContent="Deleting...";
        ajaxPost({action:"delete_email",email:delTarget},function(r){
            btn.disabled=false;btn.textContent="Delete";
            if(r.success){showMsg(msg,r.message,true);var row=document.querySelector(".bem-row[data-email=\""+delTarget+"\"]");if(row)row.style.display="none";setTimeout(function(){location.reload();},1200);}
            else{showMsg(msg,r.message,false);}
        });
    }

    /* ─── Domain Management ─── */
    var domDelTarget="";var domDelType="";
    var domDomainsLoaded=false;

    function bindDomainActions(c){
        c.querySelectorAll(".bdm-btn-del").forEach(function(b){b.addEventListener("click",function(){openDomainDelModal(this.getAttribute("data-domain"),this.getAttribute("data-type"));});});
    }

    function openAddonModal(){
        document.getElementById("bdmAddonModal").style.display="flex";
        document.getElementById("bdmAddonDomain").value="";document.getElementById("bdmAddonDocroot").value="";
        document.getElementById("bdmAddonMsg").style.display="none";
        var domInput=document.getElementById("bdmAddonDomain");
        var docInput=document.getElementById("bdmAddonDocroot");
        domInput.oninput=function(){docInput.value=domInput.value;};
    }

    function submitAddonDomain(){
        var btn=document.getElementById("bdmAddonSubmit"),msg=document.getElementById("bdmAddonMsg");
        var domain=document.getElementById("bdmAddonDomain").value.trim();
        var docroot=document.getElementById("bdmAddonDocroot").value.trim();
        if(!domain){showMsg(msg,"Please enter a domain name",false);return;}
        if(!docroot) docroot=domain;
        btn.disabled=true;btn.textContent="Adding...";
        ajaxPost({action:"add_addon_domain",domain:domain,docroot:docroot},function(r){
            btn.disabled=false;btn.textContent="Add Domain";showMsg(msg,r.message,r.success);
            if(r.success)setTimeout(function(){location.reload();},1200);
        });
    }

    function openSubModal(){
        document.getElementById("bdmSubModal").style.display="flex";
        document.getElementById("bdmSubName").value="";document.getElementById("bdmSubDocroot").value="";
        document.getElementById("bdmSubMsg").style.display="none";
        if(!domDomainsLoaded){
            ajaxPost({action:"get_parent_domains"},function(r){
                var sel=document.getElementById("bdmSubParent");sel.innerHTML="";
                if(r.success&&r.domains&&r.domains.length){r.domains.forEach(function(d){var o=document.createElement("option");o.value=d;o.textContent=d;sel.appendChild(o);});}
                else{sel.innerHTML="<option>No domains</option>";}
                domDomainsLoaded=true;
            });
        }
        var nameInput=document.getElementById("bdmSubName");
        var docInput=document.getElementById("bdmSubDocroot");
        var selParent=document.getElementById("bdmSubParent");
        function updateSubDocroot(){docInput.value=nameInput.value+(nameInput.value?".":"")+selParent.value;}
        nameInput.oninput=updateSubDocroot;
        selParent.onchange=updateSubDocroot;
    }

    function submitSubdomain(){
        var btn=document.getElementById("bdmSubSubmit"),msg=document.getElementById("bdmSubMsg");
        var name=document.getElementById("bdmSubName").value.trim();
        var parent=document.getElementById("bdmSubParent").value;
        var docroot=document.getElementById("bdmSubDocroot").value.trim();
        if(!name){showMsg(msg,"Please enter a subdomain name",false);return;}
        if(!docroot) docroot=name+"."+parent;
        btn.disabled=true;btn.textContent="Adding...";
        ajaxPost({action:"add_subdomain",subdomain:name,domain:parent,docroot:docroot},function(r){
            btn.disabled=false;btn.textContent="Add Subdomain";showMsg(msg,r.message,r.success);
            if(r.success)setTimeout(function(){location.reload();},1200);
        });
    }

    function openDomainDelModal(domain,type){
        domDelTarget=domain;domDelType=type;
        document.getElementById("bdmDelModal").style.display="flex";
        document.getElementById("bdmDelDomain").textContent=domain;
        document.getElementById("bdmDelMsg").style.display="none";
    }

    function submitDomainDelete(){
        var btn=document.getElementById("bdmDelSubmit"),msg=document.getElementById("bdmDelMsg");
        btn.disabled=true;btn.textContent="Deleting...";
        ajaxPost({action:"delete_domain",domain:domDelTarget,type:domDelType},function(r){
            btn.disabled=false;btn.textContent="Delete";
            if(r.success){showMsg(msg,r.message,true);var row=document.querySelector(".bdm-row[data-domain=\""+domDelTarget+"\"]");if(row)row.style.display="none";setTimeout(function(){location.reload();},1200);}
            else{showMsg(msg,r.message,false);}
        });
    }

    /* ─── WordPress Toolkit ─── */
    var wpAjaxUrl="modules/addons/broodle_whmcs_tools/ajax_wordpress.php";
    var wpInstances=[];
    var currentWpInstance=null;
    var wpServiceId=0;

    function bwpInit(){
        var wpSrc=document.getElementById("broodle-wp-source");
        if(!wpSrc) return;
        wpServiceId=wpSrc.getAttribute("data-service-id")||0;

        var tabNav=document.querySelector("ul.panel-tabs.nav.nav-tabs");
        if(!tabNav) tabNav=document.querySelector(".section-body ul.nav.nav-tabs");
        var panel=tabNav?(tabNav.closest(".panel")||tabNav.parentNode):null;

        if(tabNav){
            var li=document.createElement("li");
            li.innerHTML="<a href=\"#broodleWpInfo\" data-toggle=\"tab\"><i class=\"fab fa-wordpress\"></i> WordPress Manager</a>";
            tabNav.appendChild(li);
            var tabContent=panel?panel.querySelector(".tab-content"):null;
            if(tabContent){
                var tp=document.createElement("div");
                tp.className="panel-body tab-pane";tp.id="broodleWpInfo";
                tp.innerHTML=wpSrc.innerHTML;
                wpSrc.parentNode.removeChild(wpSrc);
                tabContent.appendChild(tp);
                bwpBindEvents(tp);
                bwpLoadInstances();
            }
        }
    }

    function bwpBindEvents(container){
        var scanBtn=container.querySelector("#bwpScanBtn");
        if(scanBtn) scanBtn.addEventListener("click",function(){bwpRefresh();});

        var overlay=document.getElementById("bwpDetailOverlay");
        if(overlay){
            overlay.addEventListener("click",function(e){if(e.target===overlay)overlay.style.display="none";});
            var closeBtn=document.getElementById("bwpDetailClose");
            if(closeBtn) closeBtn.addEventListener("click",function(){overlay.style.display="none";});

            overlay.querySelectorAll(".bwp-tab").forEach(function(tab){
                tab.addEventListener("click",function(){
                    overlay.querySelectorAll(".bwp-tab").forEach(function(t){t.classList.remove("active");});
                    overlay.querySelectorAll(".bwp-tab-content").forEach(function(c){c.classList.remove("active");});
                    tab.classList.add("active");
                    var target=tab.getAttribute("data-tab");
                    var content=document.getElementById("bwpTab"+target.charAt(0).toUpperCase()+target.slice(1));
                    if(content) content.classList.add("active");
                    if(target==="plugins"&&currentWpInstance) bwpLoadPlugins();
                    if(target==="themes"&&currentWpInstance) bwpLoadThemes();
                    if(target==="security"&&currentWpInstance) bwpLoadSecurity();
                });
            });
        }
    }

    function bwpAjax(data,cb){
        var fd=new FormData();
        for(var k in data) fd.append(k,data[k]);
        fd.append("service_id",wpServiceId);
        var x=new XMLHttpRequest();x.open("POST",wpAjaxUrl,true);
        x.onload=function(){try{cb(JSON.parse(x.responseText));}catch(e){cb({success:false,message:"Invalid response"});}};
        x.onerror=function(){cb({success:false,message:"Network error"});};
        x.send(fd);
    }

    function bwpLoadInstances(){
        var loading=document.getElementById("bwpLoading");
        var list=document.getElementById("bwpList");
        var empty=document.getElementById("bwpEmpty");
        if(loading) loading.style.display="flex";
        if(list) list.style.display="none";
        if(empty) empty.style.display="none";

        bwpAjax({action:"get_wp_instances"},function(r){
            if(loading) loading.style.display="none";
            if(r.success&&r.instances&&r.instances.length>0){
                wpInstances=r.instances;
                if(list){
                    list.style.display="block";
                    var html="";
                    r.instances.forEach(function(inst){
                        var updates=[];
                        if(inst.availableUpdate) updates.push("Core update");
                        if(inst.pluginUpdates>0) updates.push(inst.pluginUpdates+" plugin"+(inst.pluginUpdates>1?"s":""));
                        if(inst.themeUpdates>0) updates.push(inst.themeUpdates+" theme"+(inst.themeUpdates>1?"s":""));
                        var updateBadge=updates.length?"<span class=\"bwp-status-badge update-available\">"+updates.join(", ")+"</span>":"";
                        var statusBadge=inst.alive?"<span class=\"bwp-status-badge active\">Online</span>":"<span class=\"bwp-status-badge inactive\">Offline</span>";
                        var sslBadge=inst.ssl?"<span title=\"SSL enabled\" style=\"color:#059669\">&#128274;</span>":"";

                        html+="<div class=\"bwp-site\" data-id=\""+inst.id+"\">"
                            +"<div class=\"bwp-site-icon\"><svg width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"currentColor\"><path d=\"M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2z\"/></svg></div>"
                            +"<div class=\"bwp-site-info\">"
                            +"<p class=\"bwp-site-domain\">"+bwpEsc(inst.displayTitle||inst.site_url||inst.domain)+" "+sslBadge+"</p>"
                            +"<div class=\"bwp-site-meta\">"
                            +"<span>WP "+bwpEsc(inst.version)+"</span>"
                            +"<span>"+bwpEsc(inst.path||"/")+"</span>"
                            +"<span>"+statusBadge+"</span>"
                            +(updateBadge?"<span>"+updateBadge+"</span>":"")
                            +"</div></div>"
                            +"<div class=\"bwp-site-actions\">"
                            +"<button type=\"button\" class=\"bwp-action-btn primary bwp-login-btn\" data-id=\""+inst.id+"\">Login</button>"
                            +"<button type=\"button\" class=\"bwp-action-btn bwp-manage-btn\" data-id=\""+inst.id+"\">Manage</button>"
                            +"</div></div>";
                    });
                    list.innerHTML=html;

                    list.querySelectorAll(".bwp-login-btn").forEach(function(btn){
                        btn.addEventListener("click",function(e){e.stopPropagation();bwpAutoLogin(parseInt(this.getAttribute("data-id")),this);});
                    });
                    list.querySelectorAll(".bwp-manage-btn").forEach(function(btn){
                        btn.addEventListener("click",function(e){e.stopPropagation();bwpOpenDetail(parseInt(this.getAttribute("data-id")));});
                    });
                    list.querySelectorAll(".bwp-site").forEach(function(site){
                        site.addEventListener("click",function(){bwpOpenDetail(parseInt(this.getAttribute("data-id")));});
                    });
                }
            } else {
                if(empty) empty.style.display="flex";
                if(r.message&&!r.success){
                    var errEl=document.getElementById("bwpEmpty");
                    if(errEl) errEl.innerHTML="<div class=\"bwp-msg error\">"+bwpEsc(r.message)+"</div>";
                }
            }
        });
    }

    function bwpEsc(s){if(s===null||s===undefined)return"";s=String(s);var d=document.createElement("div");d.textContent=s;return d.innerHTML;}

    function bwpRefresh(){
        var btn=document.getElementById("bwpScanBtn");
        if(btn){btn.disabled=true;btn.innerHTML="Loading...";}
        bwpLoadInstances();
        setTimeout(function(){if(btn){btn.disabled=false;btn.innerHTML="<svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M21.21 15.89A10 10 0 1 1 8 2.83\"/><path d=\"M22 12A10 10 0 0 0 12 2v10z\"/></svg> Refresh";}},2000);
    }

    function bwpAutoLogin(instId,btn){
        if(btn){var origHtml=btn.innerHTML;btn.disabled=true;btn.innerHTML="<div class=\"bwp-spinner\" style=\"width:14px;height:14px;border-width:2px;display:inline-block;vertical-align:middle\"></div> Logging in...";}
        bwpAjax({action:"wp_autologin",instance_id:instId},function(r){
            if(btn){btn.disabled=false;btn.innerHTML=origHtml;}
            if(r.success&&r.login_url){window.open(r.login_url,"_blank");}
            else{alert(r.message||"Could not create login session");}
        });
    }

    function bwpOpenDetail(instId){
        var inst=null;
        for(var i=0;i<wpInstances.length;i++){if(wpInstances[i].id===instId){inst=wpInstances[i];break;}}
        if(!inst) return;
        currentWpInstance=inst;

        var overlay=document.getElementById("bwpDetailOverlay");
        if(!overlay) return;
        overlay.style.display="flex";

        document.getElementById("bwpDetailTitle").textContent=inst.displayTitle||inst.site_url||inst.domain;

        overlay.querySelectorAll(".bwp-tab").forEach(function(t,i){t.classList.toggle("active",i===0);});
        overlay.querySelectorAll(".bwp-tab-content").forEach(function(c,i){c.classList.toggle("active",i===0);});

        // Build overview with preview + site header + 3-col grid
        var ov=document.getElementById("bwpTabOverview");
        var siteUrl=inst.site_url||("http://"+inst.domain);
        var safeUrl=bwpEsc(siteUrl);
        var updateInfo=inst.availableUpdate?"<div class=\"bwp-msg info\">WordPress "+bwpEsc(inst.availableUpdate)+" is available. <button class=\"bwp-item-btn update\" onclick=\"bwpUpdateCore(event)\">Update Core</button></div>":"";

        // Website preview (scaled desktop view)
        var preview="<div class=\"bwp-preview-wrap\">"
            +"<div class=\"bwp-preview-bar\">"
            +"<div class=\"bwp-preview-dots\"><span></span><span></span><span></span></div>"
            +"<div class=\"bwp-preview-url\">"+safeUrl+"</div>"
            +"</div>"
            +"<div class=\"bwp-preview-frame-wrap\">"
            +"<iframe class=\"bwp-preview-frame\" src=\""+safeUrl+"\" sandbox=\"allow-scripts allow-same-origin\" loading=\"lazy\"></iframe>"
            +"</div>"
            +"<div class=\"bwp-preview-overlay\" onclick=\"window.open(\\x27"+safeUrl+"\\x27,\\x27_blank\\x27)\"></div>"
            +"</div>";

        // Site header card
        var statusBadge=inst.alive?"<span class=\"bwp-status-badge active\">Online</span>":"<span class=\"bwp-status-badge inactive\">Offline</span>";
        var sslBadge=inst.ssl?"<span class=\"bwp-status-badge active\">SSL</span>":"<span class=\"bwp-status-badge inactive\">No SSL</span>";
        var infectedBadge=inst.infected?"<span class=\"bwp-status-badge\" style=\"background:rgba(239,68,68,.08);color:#ef4444\">&#9888; Infected</span>":"";
        var header="<div class=\"bwp-site-header\">"
            +"<div class=\"bwp-site-header-icon\"><svg width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"currentColor\"><path d=\"M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zM3.443 12c0-1.178.25-2.296.69-3.313l3.8 10.411A8.57 8.57 0 0 1 3.443 12zm8.557 8.557c-.82 0-1.613-.12-2.363-.34l2.51-7.29 2.57 7.04c.017.04.037.078.058.115a8.523 8.523 0 0 1-2.775.475zm1.166-12.546c.503-.026.956-.078.956-.078.45-.052.397-.715-.053-.69 0 0-1.352.106-2.224.106-.82 0-2.198-.106-2.198-.106-.45-.026-.503.664-.053.69 0 0 .427.052.878.078l1.305 3.575-1.833 5.498L7.34 7.01c.503-.026.956-.078.956-.078.45-.052.397-.715-.053-.69 0 0-1.352.107-2.224.107-.156 0-.34-.004-.535-.012A8.544 8.544 0 0 1 12 3.443c2.1 0 4.017.76 5.5 2.018-.035-.002-.069-.007-.105-.007-.82 0-1.4.715-1.4 1.48 0 .69.397 1.272.82 1.96.318.555.69 1.268.69 2.296 0 .715-.274 1.543-.635 2.7l-.833 2.78-3.015-8.97zm4.394 11.14l2.025-5.852c.378-.945.503-1.7.503-2.374 0-.244-.016-.47-.045-.684A8.544 8.544 0 0 1 20.557 12a8.545 8.545 0 0 1-2.997 6.51z\"/></svg></div>"
            +"<div class=\"bwp-site-header-info\">"
            +"<h4>"+bwpEsc(inst.displayTitle||inst.site_url||inst.domain)+"</h4>"
            +"<p><span>WP "+bwpEsc(inst.version)+"</span><span>"+bwpEsc(inst.path||"/")+"</span><span>"+statusBadge+"</span><span>"+sslBadge+"</span>"+infectedBadge+"</p>"
            +"</div></div>";

        ov.innerHTML="<div class=\"bwp-overview-hero\">"+preview
            +"<div class=\"bwp-overview-right\">"
            +header+updateInfo
            +"<div class=\"bwp-overview-grid\">"
            +"<div class=\"bwp-stat\"><p class=\"bwp-stat-label\">WP Version</p><p class=\"bwp-stat-value\">"+bwpEsc(inst.version)+(inst.availableUpdate?" &rarr; "+bwpEsc(inst.availableUpdate):"")+"</p></div>"
            +"<div class=\"bwp-stat\"><p class=\"bwp-stat-label\">Install Path</p><p class=\"bwp-stat-value\">"+bwpEsc(inst.path)+"</p></div>"
            +"<div class=\"bwp-stat\"><p class=\"bwp-stat-label\">Owner</p><p class=\"bwp-stat-value\">"+bwpEsc(inst.owner)+"</p></div>"
            +"<div class=\"bwp-stat\"><p class=\"bwp-stat-label\">SSL</p><p class=\"bwp-stat-value\">"+(inst.ssl?"&#128274; Enabled":"Disabled")+"</p></div>"
            +"<div class=\"bwp-stat\"><p class=\"bwp-stat-label\">Status</p><p class=\"bwp-stat-value\">"+(inst.alive?"&#9989; Online":"&#10060; Offline")+"</p></div>"
            +"<div class=\"bwp-stat\"><p class=\"bwp-stat-label\">PHP</p><p class=\"bwp-stat-value\">"+bwpEsc(inst.phpVersion||"N/A")+"</p></div>"
            +"</div>"
            +"<div class=\"bwp-quick-actions\">"
            +"<button type=\"button\" class=\"bwp-action-btn primary\" onclick=\"bwpDoLogin(event)\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4\"/><polyline points=\"10 17 15 12 10 7\"/><line x1=\"15\" y1=\"12\" x2=\"3\" y2=\"12\"/></svg> WP Admin Login</button>"
            +"<button type=\"button\" class=\"bwp-action-btn\" onclick=\"window.open(\\x27"+safeUrl+"\\x27,\\x27_blank\\x27)\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6\"/><polyline points=\"15 3 21 3 21 9\"/><line x1=\"10\" y1=\"14\" x2=\"21\" y2=\"3\"/></svg> Visit Site</button>"
            +"</div></div></div>";

        document.getElementById("bwpTabPlugins").innerHTML="<div class=\"bwp-loading\"><div class=\"bwp-spinner\"></div><span>Loading plugins...</span></div>";
        document.getElementById("bwpTabThemes").innerHTML="<div class=\"bwp-loading\"><div class=\"bwp-spinner\"></div><span>Loading themes...</span></div>";
        document.getElementById("bwpTabSecurity").innerHTML="<div class=\"bwp-loading\"><div class=\"bwp-spinner\"></div><span>Running security scan...</span></div>";
    }

    window.bwpDoLogin=function(e){
        if(!currentWpInstance) return;
        var btn=e&&e.target?e.target.closest(".bwp-action-btn"):null;
        bwpAutoLogin(currentWpInstance.id,btn);
    };

    window.bwpUpdateCore=function(e){
        if(!currentWpInstance) return;
        var btn=e&&e.target?e.target.closest(".bwp-item-btn"):null;
        if(btn){btn.disabled=true;btn.innerHTML="<div class=\"bwp-spinner\" style=\"width:12px;height:12px;border-width:2px;display:inline-block;vertical-align:middle\"></div> Updating...";}
        bwpAjax({action:"wp_update",instance_id:currentWpInstance.id,type:"core"},function(r){
            if(btn){btn.disabled=false;btn.textContent="Update Core";}
            if(r.success){bwpLoadInstances();bwpOpenDetail(currentWpInstance.id);}
            else{alert(r.message||"Update failed");}
        });
    };

    function bwpLoadPlugins(){
        if(!currentWpInstance) return;
        var container=document.getElementById("bwpTabPlugins");
        container.innerHTML="<div class=\"bwp-loading\"><div class=\"bwp-spinner\"></div><span>Loading plugins...</span></div>";

        bwpAjax({action:"wp_list_plugins",instance_id:currentWpInstance.id},function(r){
            if(r.success&&r.plugins){
                if(r.plugins.length===0){container.innerHTML="<div class=\"bwp-empty\"><span>No plugins found</span></div>";return;}
                var activeCount=0,inactiveCount=0,updateCount=0;
                r.plugins.forEach(function(p){
                    if(p.active===true||p.active==="true"||p.active===1||p.active==="1") activeCount++; else inactiveCount++;
                    if(p.availableVersion) updateCount++;
                });
                var html="<div class=\"bwp-tab-summary\">"
                    +"<span class=\"bwp-tab-stat\"><span class=\"bwp-tab-stat-num\" style=\"color:#059669\">"+activeCount+"</span> Active</span>"
                    +"<span class=\"bwp-tab-stat\"><span class=\"bwp-tab-stat-num\" style=\"color:#6b7280\">"+inactiveCount+"</span> Inactive</span>"
                    +(updateCount?"<span class=\"bwp-tab-stat\"><span class=\"bwp-tab-stat-num\" style=\"color:#0a5ed3\">"+updateCount+"</span> Updates</span>":"")
                    +"</div>";
                r.plugins.forEach(function(p){
                    var isActive=p.active===true||p.active==="true"||p.active===1||p.active==="1";
                    var statusClass=isActive?"active":"inactive";
                    var hasUpdate=!!p.availableVersion;
                    var iconUrl="https://ps.w.org/"+encodeURIComponent(p.slug)+"/assets/icon-128x128.png";
                    html+="<div class=\"bwp-item-row\">"
                        +"<div class=\"bwp-item-icon-img\"><img src=\""+bwpEsc(iconUrl)+"\" onerror=\"this.style.display=\\x27none\\x27;this.nextElementSibling.style.display=\\x27flex\\x27\" alt=\"\"><div class=\"bwp-item-icon plugin\" style=\"display:none\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z\"/></svg></div></div>"
                        +"<div class=\"bwp-item-info\">"
                        +"<p class=\"bwp-item-name\">"+bwpEsc(p.title||p.slug)+"</p>"
                        +"<p class=\"bwp-item-detail\">"
                        +"<span class=\"bwp-status-badge "+statusClass+"\">"+(isActive?"Active":"Inactive")+"</span>"
                        +(hasUpdate?" <span class=\"bwp-status-badge update-available\">v"+bwpEsc(p.version)+" &rarr; "+bwpEsc(p.availableVersion)+"</span>":"<span style=\"color:var(--text-muted,#6b7280)\"> v"+bwpEsc(p.version)+"</span>")
                        +"</p>"
                        +"</div>"
                        +"<div class=\"bwp-item-actions\">"
                        +"<button class=\"bwp-item-btn "+(isActive?"active":"inactive")+"-state\" data-slug=\""+bwpEsc(p.slug)+"\" data-activate=\""+(isActive?"0":"1")+"\" onclick=\"bwpTogglePlugin(this)\">"+(isActive?"Deactivate":"Activate")+"</button>"
                        +(hasUpdate?"<button class=\"bwp-item-btn update\" data-slug=\""+bwpEsc(p.slug)+"\" onclick=\"bwpUpdatePlugin(this)\"><svg width=\"12\" height=\"12\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" style=\"vertical-align:middle\"><path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\"/><polyline points=\"7 10 12 15 17 10\"/><line x1=\"12\" y1=\"15\" x2=\"12\" y2=\"3\"/></svg> Update</button>":"")
                        +"</div></div>";
                });
                container.innerHTML=html;
            } else {
                container.innerHTML="<div class=\"bwp-msg error\">"+(r.message||"Could not load plugins.")+"</div>";
            }
        });
    }

    window.bwpTogglePlugin=function(btn){
        if(!currentWpInstance) return;
        var slug=btn.getAttribute("data-slug"),activate=btn.getAttribute("data-activate");
        var origText=btn.textContent;
        btn.disabled=true;btn.innerHTML="<div class=\"bwp-spinner\" style=\"width:12px;height:12px;border-width:2px;display:inline-block;vertical-align:middle\"></div>";
        bwpAjax({action:"wp_toggle_plugin",instance_id:currentWpInstance.id,slug:slug,activate:activate},function(r){
            if(r.success){bwpLoadPlugins();}else{alert(r.message||"Failed");btn.disabled=false;btn.textContent=origText;}
        });
    };
    window.bwpUpdatePlugin=function(btn){
        if(!currentWpInstance) return;
        var slug=btn.getAttribute("data-slug");
        btn.disabled=true;btn.innerHTML="<div class=\"bwp-spinner\" style=\"width:12px;height:12px;border-width:2px;display:inline-block;vertical-align:middle\"></div> Updating...";
        bwpAjax({action:"wp_update",instance_id:currentWpInstance.id,type:"plugins",slug:slug},function(r){
            if(r.success){bwpLoadPlugins();}else{alert(r.message||"Failed");btn.disabled=false;btn.innerHTML="<svg width=\"12\" height=\"12\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" style=\"vertical-align:middle\"><path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\"/><polyline points=\"7 10 12 15 17 10\"/><line x1=\"12\" y1=\"15\" x2=\"12\" y2=\"3\"/></svg> Update";}
        });
    };

    function bwpLoadThemes(){
        if(!currentWpInstance) return;
        var container=document.getElementById("bwpTabThemes");
        container.innerHTML="<div class=\"bwp-loading\"><div class=\"bwp-spinner\"></div><span>Loading themes...</span></div>";

        bwpAjax({action:"wp_list_themes",instance_id:currentWpInstance.id},function(r){
            if(r.success&&r.themes){
                if(r.themes.length===0){container.innerHTML="<div class=\"bwp-empty\"><span>No themes found</span></div>";return;}
                var html="<div class=\"bwp-theme-grid\">";
                r.themes.forEach(function(t){
                    var isActive=t.active===true||t.active==="true"||t.active===1||t.active==="1";
                    var hasUpdate=!!t.availableVersion;
                    var screenshotUrl=t.screenshot||("https://i0.wp.com/themes.svn.wordpress.org/"+encodeURIComponent(t.slug)+"/"+encodeURIComponent(t.version)+"/screenshot.png?w=400");
                    html+="<div class=\"bwp-theme-card"+(isActive?" bwp-theme-active":"")+"\">"
                        +"<div class=\"bwp-theme-screenshot\">"
                        +"<img src=\""+bwpEsc(screenshotUrl)+"\" onerror=\"this.style.display=\\x27none\\x27;this.nextElementSibling.style.display=\\x27flex\\x27\" alt=\""+bwpEsc(t.title||t.slug)+"\">"
                        +"<div class=\"bwp-theme-placeholder\" style=\"display:none\"><svg width=\"32\" height=\"32\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.5\" opacity=\".3\"><rect x=\"3\" y=\"3\" width=\"18\" height=\"18\" rx=\"2\" ry=\"2\"/><circle cx=\"8.5\" cy=\"8.5\" r=\"1.5\"/><polyline points=\"21 15 16 10 5 21\"/></svg></div>"
                        +(isActive?"<div class=\"bwp-theme-active-badge\">Active Theme</div>":"")
                        +"</div>"
                        +"<div class=\"bwp-theme-info\">"
                        +"<p class=\"bwp-theme-name\">"+bwpEsc(t.title||t.slug)+"</p>"
                        +"<p class=\"bwp-theme-ver\">v"+bwpEsc(t.version)
                        +(hasUpdate?" <span class=\"bwp-status-badge update-available\">&rarr; "+bwpEsc(t.availableVersion)+"</span>":"")
                        +"</p>"
                        +"<div class=\"bwp-theme-actions\">"
                        +(!isActive?"<button class=\"bwp-item-btn active-state\" data-slug=\""+bwpEsc(t.slug)+"\" onclick=\"bwpActivateTheme(this)\">Activate</button>":"")
                        +(hasUpdate?"<button class=\"bwp-item-btn update\" data-slug=\""+bwpEsc(t.slug)+"\" onclick=\"bwpUpdateTheme(this)\"><svg width=\"12\" height=\"12\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" style=\"vertical-align:middle\"><path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\"/><polyline points=\"7 10 12 15 17 10\"/><line x1=\"12\" y1=\"15\" x2=\"12\" y2=\"3\"/></svg> Update</button>":"")
                        +"</div></div></div>";
                });
                html+="</div>";
                container.innerHTML=html;
            } else {
                container.innerHTML="<div class=\"bwp-msg error\">"+(r.message||"Could not load themes.")+"</div>";
            }
        });
    }

    window.bwpActivateTheme=function(btn){
        if(!currentWpInstance) return;
        var slug=btn.getAttribute("data-slug");
        btn.disabled=true;btn.innerHTML="<div class=\"bwp-spinner\" style=\"width:12px;height:12px;border-width:2px;display:inline-block;vertical-align:middle\"></div>";
        bwpAjax({action:"wp_toggle_theme",instance_id:currentWpInstance.id,slug:slug},function(r){
            if(r.success){bwpLoadThemes();}else{alert(r.message||"Failed");btn.disabled=false;btn.textContent="Activate";}
        });
    };
    window.bwpUpdateTheme=function(btn){
        if(!currentWpInstance) return;
        var slug=btn.getAttribute("data-slug");
        btn.disabled=true;btn.innerHTML="<div class=\"bwp-spinner\" style=\"width:12px;height:12px;border-width:2px;display:inline-block;vertical-align:middle\"></div> Updating...";
        bwpAjax({action:"wp_update",instance_id:currentWpInstance.id,type:"themes",slug:slug},function(r){
            if(r.success){bwpLoadThemes();}else{alert(r.message||"Failed");btn.disabled=false;btn.innerHTML="<svg width=\"12\" height=\"12\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" style=\"vertical-align:middle\"><path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\"/><polyline points=\"7 10 12 15 17 10\"/><line x1=\"12\" y1=\"15\" x2=\"12\" y2=\"3\"/></svg> Update";}
        });
    };

    function bwpLoadSecurity(){
        if(!currentWpInstance) return;
        var container=document.getElementById("bwpTabSecurity");
        container.innerHTML="<div class=\"bwp-loading\"><div class=\"bwp-spinner\"></div><span>Running security scan...</span></div>";

        bwpAjax({action:"wp_security_scan",instance_id:currentWpInstance.id},function(r){
            if(r.success&&r.security){
                // Normalize: if object with keys, convert to array
                var items=r.security;
                if(!Array.isArray(items)){
                    var arr=[];
                    for(var k in items){
                        if(items.hasOwnProperty(k)){
                            var v=items[k];
                            if(typeof v==="object"&&v!==null){
                                v.id=v.id||k;
                                v.title=v.title||k.replace(/([a-z])([A-Z])/g,"$1 $2").replace(/[_-]/g," ").replace(/^./,function(m){return m.toUpperCase();});
                                arr.push(v);
                            } else if(typeof v==="string"){
                                arr.push({id:k,status:v,title:k.replace(/([a-z])([A-Z])/g,"$1 $2").replace(/[_-]/g," ").replace(/^./,function(m){return m.toUpperCase();}),description:""});
                            }
                        }
                    }
                    items=arr;
                }
                if(items.length===0){
                    container.innerHTML="<div class=\"bwp-empty\"><span>No security measures data available</span></div>";
                    return;
                }
                var appliedCount=0,notAppliedCount=0;
                items.forEach(function(item){
                    var s=item.status||"unknown";
                    if(s==="applied"||s==="ok"||s==="success") appliedCount++;
                    else notAppliedCount++;
                });
                var html="<div class=\"bwp-sec-summary\">"
                    +"<div class=\"bwp-sec-summary-bar\">"
                    +"<div class=\"bwp-sec-summary-fill\" style=\"width:"+Math.round(appliedCount/(appliedCount+notAppliedCount)*100)+"%\"></div>"
                    +"</div>"
                    +"<div class=\"bwp-sec-summary-text\">"
                    +"<span style=\"color:#059669\"><strong>"+appliedCount+"</strong> Applied</span>"
                    +"<span style=\"color:#d97706\"><strong>"+notAppliedCount+"</strong> Not Applied</span>"
                    +"<span style=\"color:var(--text-muted,#6b7280)\"><strong>"+items.length+"</strong> Total</span>"
                    +"</div></div>";
                items.forEach(function(item){
                    var status=item.status||"unknown";
                    var iconClass="warning";
                    var icon="<svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z\"/><line x1=\"12\" y1=\"9\" x2=\"12\" y2=\"13\"/><line x1=\"12\" y1=\"17\" x2=\"12.01\" y2=\"17\"/></svg>";
                    if(status==="applied"||status==="ok"||status==="success"){
                        iconClass="ok";
                        icon="<svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M22 11.08V12a10 10 0 1 1-5.93-9.14\"/><polyline points=\"22 4 12 14.01 9 11.01\"/></svg>";
                    } else if(status==="error"||status==="danger"||status==="failed"){
                        iconClass="danger";
                        icon="<svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"15\" y1=\"9\" x2=\"9\" y2=\"15\"/><line x1=\"9\" y1=\"9\" x2=\"15\" y2=\"15\"/></svg>";
                    }
                    var measureId=item.id||item.measureId||"";
                    var canApply=status!=="applied"&&status!=="ok"&&status!=="success"&&measureId;
                    var canRevert=(status==="applied"||status==="ok"||status==="success")&&measureId;
                    var statusLabel=status==="applied"?"Applied":(status==="notApplied"?"Not Applied":status.charAt(0).toUpperCase()+status.slice(1));
                    html+="<div class=\"bwp-security-item\">"
                        +"<div class=\"bwp-sec-icon "+iconClass+"\">"+icon+"</div>"
                        +"<div class=\"bwp-sec-info\">"
                        +"<p class=\"bwp-sec-label\">"+bwpEsc(item.title||"Security Check")+"</p>"
                        +(item.description?"<p class=\"bwp-sec-detail\">"+bwpEsc(item.description)+"</p>":"")
                        +"</div>"
                        +"<div class=\"bwp-item-actions\">"
                        +(canApply?"<button class=\"bwp-item-btn update\" data-measure=\""+bwpEsc(measureId)+"\" onclick=\"bwpSecurityApply(this)\"><svg width=\"12\" height=\"12\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" style=\"vertical-align:middle\"><path d=\"M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z\"/></svg> Apply</button>":"")
                        +(canRevert?"<button class=\"bwp-item-btn inactive-state\" data-measure=\""+bwpEsc(measureId)+"\" onclick=\"bwpSecurityRevert(this)\">Revert</button>":"")
                        +"<span class=\"bwp-sec-value "+iconClass+"\">"+bwpEsc(statusLabel)+"</span>"
                        +"</div></div>";
                });
                container.innerHTML=html;
            } else {
                container.innerHTML="<div class=\"bwp-msg error\">"+(r.message||"Security scan failed.")+"</div>";
            }
        });
    }

    window.bwpSecurityApply=function(btn){
        if(!currentWpInstance) return;
        var measureId=btn.getAttribute("data-measure");
        btn.disabled=true;btn.innerHTML="<div class=\"bwp-spinner\" style=\"width:12px;height:12px;border-width:2px;display:inline-block;vertical-align:middle\"></div> Applying...";
        bwpAjax({action:"wp_security_apply",instance_id:currentWpInstance.id,measure_id:measureId},function(r){
            if(r.success){bwpLoadSecurity();}else{alert(r.message||"Failed");btn.disabled=false;btn.innerHTML="<svg width=\"12\" height=\"12\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" style=\"vertical-align:middle\"><path d=\"M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z\"/></svg> Apply";}
        });
    };
    window.bwpSecurityRevert=function(btn){
        if(!currentWpInstance) return;
        var measureId=btn.getAttribute("data-measure");
        btn.disabled=true;btn.innerHTML="<div class=\"bwp-spinner\" style=\"width:12px;height:12px;border-width:2px;display:inline-block;vertical-align:middle\"></div> Reverting...";
        bwpAjax({action:"wp_security_revert",instance_id:currentWpInstance.id,measure_id:measureId},function(r){
            if(r.success){bwpLoadSecurity();}else{alert(r.message||"Failed");btn.disabled=false;btn.textContent="Revert";}
        });
    };

    // Initialize WP toolkit after main init
    var origInit=broodleInit;
    broodleInit=function(){origInit();bwpInit();};

    if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",broodleInit);}
    else{setTimeout(broodleInit,150);}
})();
</script>';
}

/* ─── Domain Management Tab Builder ───────────────────────── */

function broodle_tools_get_domains_detailed($serviceId)
{
    $data = broodle_tools_get_cpanel_service($serviceId);
    if (!$data) return ['main' => '', 'addon' => [], 'sub' => [], 'parked' => []];
    $server = $data['server'];
    $service = $data['service'];
    $username = $service->username;
    if (empty($username)) return ['main' => $service->domain ?? '', 'addon' => [], 'sub' => [], 'parked' => []];

    $hostname = $server->hostname;
    $port = !empty($server->port) ? (int) $server->port : 2087;
    $serverUser = $server->username;
    $secure = !empty($server->secure) && ($server->secure === 'on' || $server->secure === '1' || $server->secure === 1);
    $protocol = $secure ? 'https' : 'http';

    $accessHash = '';
    $password = '';
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
    if (empty($accessHash) && empty($password)) return ['main' => $service->domain ?? '', 'addon' => [], 'sub' => [], 'parked' => []];

    $headers = [];
    if (!empty($accessHash)) {
        $headers[] = "Authorization: whm {$serverUser}:{$accessHash}";
    }

    $result = ['main' => '', 'addon' => [], 'sub' => [], 'parked' => []];

    // DomainInfo::list_domains
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($username)
         . "&cpanel_jsonapi_apiversion=3"
         . "&cpanel_jsonapi_module=DomainInfo"
         . "&cpanel_jsonapi_func=list_domains";
    $r = broodle_tools_whm_get($url, $headers, $serverUser, $password);
    if ($r) {
        $d = $r['result']['data'] ?? [];
        $result['main'] = $d['main_domain'] ?? ($service->domain ?? '');
        $result['addon'] = $d['addon_domains'] ?? [];
        $result['parked'] = $d['parked_domains'] ?? [];
        // Filter subdomains: exclude system-generated ones for addon domains
        $subs = $d['sub_domains'] ?? [];
        $addonSet = array_map('strtolower', $result['addon']);
        $mainDomain = strtolower($result['main']);
        $filtered = [];
        foreach ($subs as $sd) {
            $sdLower = strtolower($sd);
            // Skip subdomains that are just addon_domain.main_domain
            $isAddonSub = false;
            foreach ($addonSet as $ad) {
                if ($sdLower === str_replace('.', '.', strtolower($ad)) . '.' . $mainDomain) {
                    $isAddonSub = true;
                    break;
                }
            }
            if (!$isAddonSub) {
                $filtered[] = $sd;
            }
        }
        $result['sub'] = $filtered;
    }

    return $result;
}

function broodle_tools_build_domain_output($serviceId, $cpData)
{
    $domains = broodle_tools_get_domains_detailed($serviceId);
    $mainDomain = $domains['main'];
    $addonDomains = $domains['addon'];
    $subDomains = $domains['sub'];
    $parkedDomains = $domains['parked'];
    $totalCount = 1 + count($addonDomains) + count($subDomains) + count($parkedDomains);

    $rows = '';

    // Main domain
    if (!empty($mainDomain)) {
        $e = htmlspecialchars($mainDomain);
        $rows .= '<div class="bdm-row" data-domain="' . $e . '" data-type="main">'
            . '<div class="bdm-icon main"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z"/></svg></div>'
            . '<div class="bdm-info"><span class="bdm-name">' . $e . '</span><span class="bdm-badge bdm-badge-main">Primary</span></div>'
            . '<div class="bdm-actions"><a href="https://' . $e . '" target="_blank" class="bdm-btn bdm-btn-visit" title="Visit"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg><span>Visit</span></a></div>'
            . '</div>';
    }

    // Addon domains
    foreach ($addonDomains as $d) {
        $e = htmlspecialchars($d);
        $rows .= '<div class="bdm-row" data-domain="' . $e . '" data-type="addon">'
            . '<div class="bdm-icon addon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg></div>'
            . '<div class="bdm-info"><span class="bdm-name">' . $e . '</span><span class="bdm-badge bdm-badge-addon">Addon</span></div>'
            . '<div class="bdm-actions">'
            . '<a href="https://' . $e . '" target="_blank" class="bdm-btn bdm-btn-visit" title="Visit"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg><span>Visit</span></a>'
            . '<button type="button" class="bdm-btn bdm-btn-del" data-domain="' . $e . '" data-type="addon" title="Delete"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><span>Delete</span></button>'
            . '</div></div>';
    }

    // Subdomains
    foreach ($subDomains as $d) {
        $e = htmlspecialchars($d);
        $rows .= '<div class="bdm-row" data-domain="' . $e . '" data-type="sub">'
            . '<div class="bdm-icon sub"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/></svg></div>'
            . '<div class="bdm-info"><span class="bdm-name">' . $e . '</span><span class="bdm-badge bdm-badge-sub">Subdomain</span></div>'
            . '<div class="bdm-actions">'
            . '<a href="https://' . $e . '" target="_blank" class="bdm-btn bdm-btn-visit" title="Visit"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg><span>Visit</span></a>'
            . '<button type="button" class="bdm-btn bdm-btn-del" data-domain="' . $e . '" data-type="sub" title="Delete"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><span>Delete</span></button>'
            . '</div></div>';
    }

    // Parked domains
    foreach ($parkedDomains as $d) {
        $e = htmlspecialchars($d);
        $rows .= '<div class="bdm-row" data-domain="' . $e . '" data-type="parked">'
            . '<div class="bdm-icon parked"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></div>'
            . '<div class="bdm-info"><span class="bdm-name">' . $e . '</span><span class="bdm-badge bdm-badge-parked">Alias</span></div>'
            . '<div class="bdm-actions">'
            . '<a href="https://' . $e . '" target="_blank" class="bdm-btn bdm-btn-visit" title="Visit"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg><span>Visit</span></a>'
            . '<button type="button" class="bdm-btn bdm-btn-del" data-domain="' . $e . '" data-type="parked" title="Delete"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><span>Delete</span></button>'
            . '</div></div>';
    }

    if (empty($rows)) {
        $rows = '<div class="bdm-empty"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z"/></svg><span>No domains found</span></div>';
    }

    return '
<div id="broodle-domain-source" style="display:none" data-service-id="' . (int) $serviceId . '">
  <div class="bns-card" style="margin-top:20px">
    <div class="bns-card-head">
      <div class="bns-card-head-left">
        <div class="bns-icon-circle">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z"/></svg>
        </div>
        <div>
          <h5>Domains</h5>
          <p class="bdm-count">' . $totalCount . ' domain' . ($totalCount !== 1 ? 's' : '') . '</p>
        </div>
      </div>
      <div class="bdm-head-btns">
        <button type="button" class="bem-create-btn bdm-add-btn" id="bdmAddAddonBtn" title="Add Addon Domain">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Domain
        </button>
        <button type="button" class="bdm-sub-btn" id="bdmAddSubBtn" title="Add Subdomain">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/></svg>
          Add Subdomain
        </button>
      </div>
    </div>
    <div class="bns-list bdm-list">' . $rows . '</div>
  </div>
</div>
' . broodle_tools_domain_modals();
}

function broodle_tools_domain_modals()
{
    return '
<!-- Add Addon Domain Modal -->
<div class="bem-overlay" id="bdmAddonModal" style="display:none">
  <div class="bem-modal">
    <div class="bem-modal-head"><h5>Add Addon Domain</h5><button type="button" class="bem-modal-close" data-close>&times;</button></div>
    <div class="bem-modal-body">
      <div class="bem-field"><label>Domain Name</label><input type="text" id="bdmAddonDomain" placeholder="example.com" autocomplete="off"></div>
      <div class="bem-field"><label>Document Root</label><div class="bdm-docroot-wrap"><span class="bdm-docroot-prefix">/home/user/</span><input type="text" id="bdmAddonDocroot" placeholder="example.com"></div></div>
      <div class="bem-modal-msg" id="bdmAddonMsg"></div>
    </div>
    <div class="bem-modal-foot"><button type="button" class="bem-mbtn bem-mbtn-cancel" data-close>Cancel</button><button type="button" class="bem-mbtn bem-mbtn-primary" id="bdmAddonSubmit">Add Domain</button></div>
  </div>
</div>
<!-- Add Subdomain Modal -->
<div class="bem-overlay" id="bdmSubModal" style="display:none">
  <div class="bem-modal">
    <div class="bem-modal-head"><h5>Add Subdomain</h5><button type="button" class="bem-modal-close" data-close>&times;</button></div>
    <div class="bem-modal-body">
      <div class="bem-field"><label>Subdomain</label><div class="bem-input-group"><input type="text" id="bdmSubName" placeholder="blog" autocomplete="off"><span class="bem-at">.</span><select id="bdmSubParent"><option>Loading...</option></select></div></div>
      <div class="bem-field"><label>Document Root</label><div class="bdm-docroot-wrap"><span class="bdm-docroot-prefix">/home/user/</span><input type="text" id="bdmSubDocroot" placeholder="blog.example.com"></div></div>
      <div class="bem-modal-msg" id="bdmSubMsg"></div>
    </div>
    <div class="bem-modal-foot"><button type="button" class="bem-mbtn bem-mbtn-cancel" data-close>Cancel</button><button type="button" class="bem-mbtn bem-mbtn-primary" id="bdmSubSubmit">Add Subdomain</button></div>
  </div>
</div>
<!-- Delete Domain Confirmation Modal -->
<div class="bem-overlay" id="bdmDelModal" style="display:none">
  <div class="bem-modal bem-modal-sm">
    <div class="bem-modal-head"><h5>Delete Domain</h5><button type="button" class="bem-modal-close" data-close>&times;</button></div>
    <div class="bem-modal-body" style="text-align:center">
      <div style="margin:8px 0 16px"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.5" style="margin:0 auto;display:block"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
      <p style="margin:0 0 4px;font-size:14px;color:var(--heading-color,#111827)">Are you sure you want to delete</p>
      <p style="margin:0;font-size:15px;font-weight:600;color:#ef4444" id="bdmDelDomain"></p>
      <p style="margin:8px 0 0;font-size:12px;color:var(--text-muted,#9ca3af)">This will remove the domain and its files from the server.</p>
      <div class="bem-modal-msg" id="bdmDelMsg"></div>
    </div>
    <div class="bem-modal-foot"><button type="button" class="bem-mbtn bem-mbtn-cancel" data-close>Cancel</button><button type="button" class="bem-mbtn bem-mbtn-danger" id="bdmDelSubmit">Delete</button></div>
  </div>
</div>';
}

/* ─── WordPress Toolkit Output Builder ────────────────────── */

function broodle_tools_build_wp_output($serviceId)
{
    return '
<div id="broodle-wp-source" style="display:none" data-service-id="' . (int) $serviceId . '">
  <div class="bwp-card" style="margin-top:20px">
    <div class="bwp-card-head">
      <div class="bwp-card-head-left">
        <div class="bwp-icon-circle">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zM3.443 12c0-1.178.25-2.296.69-3.313l3.8 10.411A8.57 8.57 0 0 1 3.443 12zm8.557 8.557c-.82 0-1.613-.12-2.363-.34l2.51-7.29 2.57 7.04c.017.04.037.078.058.115a8.523 8.523 0 0 1-2.775.475zm1.166-12.546c.503-.026.956-.078.956-.078.45-.052.397-.715-.053-.69 0 0-1.352.106-2.224.106-.82 0-2.198-.106-2.198-.106-.45-.026-.503.664-.053.69 0 0 .427.052.878.078l1.305 3.575-1.833 5.498L7.34 7.01c.503-.026.956-.078.956-.078.45-.052.397-.715-.053-.69 0 0-1.352.107-2.224.107-.156 0-.34-.004-.535-.012A8.544 8.544 0 0 1 12 3.443c2.1 0 4.017.76 5.5 2.018-.035-.002-.069-.007-.105-.007-.82 0-1.4.715-1.4 1.48 0 .69.397 1.272.82 1.96.318.555.69 1.268.69 2.296 0 .715-.274 1.543-.635 2.7l-.833 2.78-3.015-8.97zm4.394 11.14l2.025-5.852c.378-.945.503-1.7.503-2.374 0-.244-.016-.47-.045-.684A8.544 8.544 0 0 1 20.557 12a8.545 8.545 0 0 1-2.997 6.51z"/></svg>
        </div>
        <div>
          <h5>WordPress Manager</h5>
          <p class="bwp-subtitle">Manage your WordPress installations</p>
        </div>
      </div>
      <div class="bwp-head-actions">
        <button type="button" class="bwp-scan-btn" id="bwpScanBtn" title="Refresh installations list">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
          Refresh
        </button>
      </div>
    </div>
    <div class="bwp-body" id="bwpBody">
      <div class="bwp-loading" id="bwpLoading">
        <div class="bwp-spinner"></div>
        <span>Loading WordPress installations...</span>
      </div>
      <div class="bwp-list" id="bwpList" style="display:none"></div>
      <div class="bwp-empty" id="bwpEmpty" style="display:none">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2z"/><path d="M8 12h8M12 8v8"/></svg>
        <span>No WordPress installations found</span>
        <button type="button" class="bwp-scan-btn" onclick="bwpRefresh()">Refresh</button>
      </div>
    </div>
  </div>
</div>

<!-- WP Detail Panel (popup modal) -->
<div class="bwp-overlay" id="bwpDetailOverlay" style="display:none">
  <div class="bwp-detail-panel">
    <div class="bwp-detail-head">
      <h5 id="bwpDetailTitle">Site Details</h5>
      <button type="button" class="bwp-close-btn" id="bwpDetailClose">&times;</button>
    </div>
    <div class="bwp-detail-tabs">
      <button type="button" class="bwp-tab active" data-tab="overview">Overview</button>
      <button type="button" class="bwp-tab" data-tab="plugins">Plugins</button>
      <button type="button" class="bwp-tab" data-tab="themes">Themes</button>
      <button type="button" class="bwp-tab" data-tab="security">Security</button>
    </div>
    <div class="bwp-detail-body" id="bwpDetailBody">
      <div class="bwp-tab-content active" id="bwpTabOverview"></div>
      <div class="bwp-tab-content" id="bwpTabPlugins"><div class="bwp-loading"><div class="bwp-spinner"></div><span>Loading plugins...</span></div></div>
      <div class="bwp-tab-content" id="bwpTabThemes"><div class="bwp-loading"><div class="bwp-spinner"></div><span>Loading themes...</span></div></div>
      <div class="bwp-tab-content" id="bwpTabSecurity"><div class="bwp-loading"><div class="bwp-spinner"></div><span>Running security scan...</span></div></div>
    </div>
  </div>
</div>';
}
