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

add_hook('ClientAreaProductDetailsOutput', 1, function ($vars) {
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

    // ── Email Accounts Section (standalone, not a tab) ──
    if (broodle_tools_email_enabled()) {
        $cpData = broodle_tools_get_cpanel_service($serviceId);
        if ($cpData) {
            $emails = broodle_tools_get_emails($serviceId);
            $output .= broodle_tools_build_email_output($emails, $serviceId);
        }
    }

    // ── WordPress Toolkit Tab ──
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

/* ─── Email Accounts Section Builder (standalone) ─────────── */

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
<div id="broodle-email-section" style="display:none" data-service-id="' . (int) $serviceId . '">
  <div class="bns-card">
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

        // Email → standalone section after Quick Shortcuts
        var emSrc=document.getElementById("broodle-email-section");
        if(emSrc){
            serviceId=emSrc.getAttribute("data-service-id")||0;
            emSrc.style.display="block";
            // Find insertion point: after Quick Shortcuts / sidebar section
            var inserted=false;
            // Lagom: look for .quick-shortcut-container or section with shortcuts
            var shortcuts=document.querySelector(".quick-shortcut-container,.shortcuts-container,.panel-shortcuts");
            if(shortcuts){
                var parent=shortcuts.closest(".section-body")||shortcuts.closest(".panel")||shortcuts.parentNode;
                if(parent){parent.parentNode.insertBefore(emSrc,parent.nextSibling);inserted=true;}
            }
            // Fallback: insert after the panel that contains the tabs
            if(!inserted&&panel){
                var panelParent=panel.closest(".section-body")||panel.parentNode;
                if(panelParent&&panelParent.parentNode){panelParent.parentNode.insertBefore(emSrc,panelParent.nextSibling);inserted=true;}
            }
            // Fallback: insert before the first .section-body
            if(!inserted){
                var sb=document.querySelector(".section-body");
                if(sb&&sb.parentNode){sb.parentNode.insertBefore(emSrc,sb.nextSibling);inserted=true;}
            }
            emSrc.style.margin="20px 0";
            bindCopy(emSrc);bindEmailActions(emSrc);
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

    if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",broodleInit);}
    else{setTimeout(broodleInit,150);}
})();
</script>';
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
        <button type="button" class="bwp-scan-btn" id="bwpScanBtn" title="Scan for new installations">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
          Scan
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
        <button type="button" class="bwp-scan-btn" onclick="bwpScan()">Scan for Installations</button>
      </div>
    </div>
  </div>
</div>

<!-- WP Detail Panel (slides in) -->
<div class="bwp-overlay" id="bwpDetailOverlay" style="display:none">
  <div class="bwp-detail-panel">
    <div class="bwp-detail-head">
      <button type="button" class="bwp-back-btn" id="bwpBackBtn">&larr; Back</button>
      <h5 id="bwpDetailTitle">Site Details</h5>
      <button type="button" class="bem-modal-close" id="bwpDetailClose">&times;</button>
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

function broodle_tools_shared_script()
{
    return '
<style>
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
.bem-avatar{width:34px;height:34px;border-radius:50%;background:rgba(124,58,237,.08);color:#7c3aed;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}
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
.bem-create-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:#7c3aed;color:#fff;transition:background .15s}
.bem-create-btn:hover{background:#6d28d9}
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
.bem-field{margin-bottom:16px}
.bem-field:last-child{margin-bottom:0}
.bem-field label{display:block;font-size:12px;font-weight:600;color:var(--text-muted,#6b7280);margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px}
.bem-field input,.bem-field select{width:100%;padding:9px 12px;border:1px solid var(--border-color,#d1d5db);border-radius:8px;font-size:14px;color:var(--heading-color,#111827);background:var(--input-bg,#fff);outline:none;transition:border-color .15s}
.bem-field input:focus,.bem-field select:focus{border-color:#0a5ed3;box-shadow:0 0 0 3px rgba(10,94,211,.1)}
.bem-field input[readonly]{background:var(--input-bg,#f9fafb);color:var(--text-muted,#6b7280)}
.bem-input-group{display:flex;align-items:center;border:1px solid var(--border-color,#d1d5db);border-radius:8px;overflow:hidden;transition:border-color .15s}
.bem-input-group:focus-within{border-color:#0a5ed3;box-shadow:0 0 0 3px rgba(10,94,211,.1)}
.bem-input-group input{border:none;border-radius:0;flex:1;min-width:0}
.bem-input-group input:focus{box-shadow:none}
.bem-at{padding:0 8px;font-size:14px;color:var(--text-muted,#9ca3af);font-weight:600;background:var(--input-bg,#f9fafb);border-left:1px solid var(--border-color,#e5e7eb);border-right:1px solid var(--border-color,#e5e7eb);height:100%;display:flex;align-items:center}
.bem-input-group select{border:none;border-radius:0;flex:1;min-width:0}
.bem-input-group select:focus{box-shadow:none}
.bem-pass-wrap{position:relative}
.bem-pass-wrap input{width:100%;padding-right:40px}
.bem-pass-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted,#9ca3af);cursor:pointer;padding:4px}
.bem-pass-toggle:hover{color:var(--heading-color,#111827)}
.bem-mbtn{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .15s}
.bem-mbtn-cancel{background:var(--input-bg,#f3f4f6);color:var(--heading-color,#374151)}
.bem-mbtn-cancel:hover{background:#e5e7eb}
.bem-mbtn-primary{background:#0a5ed3;color:#fff}
.bem-mbtn-primary:hover{background:#0950b3}
.bem-mbtn-danger{background:#ef4444;color:#fff}
.bem-mbtn-danger:hover{background:#dc2626}
.bem-mbtn:disabled{opacity:.5;cursor:not-allowed}
.bem-modal-msg{margin-top:12px;padding:8px 12px;border-radius:6px;font-size:13px;display:none}
.bem-modal-msg.success{display:block;background:rgba(5,150,105,.08);color:#059669}
.bem-modal-msg.error{display:block;background:rgba(239,68,68,.08);color:#ef4444}
@media(max-width:600px){.bem-btn span{display:none!important}.bem-actions{gap:4px}.bem-btn{padding:5px 7px}}
</style>
';
}
