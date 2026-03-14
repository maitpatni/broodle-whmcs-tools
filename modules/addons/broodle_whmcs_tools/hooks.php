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

/** Get service ID from hook vars / request. */
function broodle_tools_get_service_id($vars)
{
    if (!empty($vars['serviceid'])) return (int) $vars['serviceid'];
    if (!empty($vars['id'])) return (int) $vars['id'];
    if (isset($vars['service']) && is_object($vars['service'])) return (int) $vars['service']->id;
    if (!empty($_GET['id'])) return (int) $_GET['id'];
    return 0;
}

/** Get service + server + product rows for a cPanel service. */
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
    $port = 2087; // WHM port
    $serverUser = $server->username;

    // Decrypt credentials using WHMCS decrypt() function
    $accessHash = '';
    $password = '';

    if (!empty($server->accesshash)) {
        $accessHash = trim(decrypt($server->accesshash));
    }
    if (empty($accessHash) && !empty($server->password)) {
        $password = trim(decrypt($server->password));
    }

    if (empty($accessHash) && empty($password)) {
        // Try localAPI as fallback
        try {
            if (!empty($server->accesshash)) {
                $result = localAPI('DecryptPassword', ['password2' => $server->accesshash]);
                if (!empty($result['password'])) {
                    $accessHash = trim($result['password']);
                }
            }
            if (empty($accessHash) && !empty($server->password)) {
                $result = localAPI('DecryptPassword', ['password2' => $server->password]);
                if (!empty($result['password'])) {
                    $password = trim($result['password']);
                }
            }
        } catch (\Exception $e) {
            // ignore
        }
    }

    if (empty($accessHash) && empty($password)) return [];

    // Use WHM API 1 list_pops_for — the correct endpoint
    $url = "https://{$hostname}:{$port}/json-api/list_pops_for"
         . "?api.version=1"
         . "&user=" . urlencode($username);

    $headers = [];
    if (!empty($accessHash)) {
        $cleanHash = preg_replace('/\s+/', '', $accessHash);
        $headers[] = "Authorization: whm {$serverUser}:{$cleanHash}";
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
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

    if ($httpCode !== 200 || !$response) return [];

    $json = json_decode($response, true);
    if (empty($json)) return [];

    $emails = [];

    // WHM API 1 list_pops_for returns: data.pops[]
    $pops = [];
    if (isset($json['data']['pops'])) {
        $pops = $json['data']['pops'];
    } elseif (isset($json['pops'])) {
        $pops = $json['pops'];
    }

    foreach ($pops as $entry) {
        $email = '';
        if (is_string($entry)) {
            $email = $entry;
        } elseif (is_array($entry)) {
            $email = $entry['email'] ?? ($entry['user'] ?? ($entry['login'] ?? ''));
        }
        if (empty($email)) continue;
        // Skip main account and entries without @
        if ($email === $username) continue;
        if (strpos($email, '@') === false) continue;
        $emails[] = $email;
    }

    sort($emails);
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

    // ── Email List Tab ──
    if (broodle_tools_email_enabled()) {
        $cpData = broodle_tools_get_cpanel_service($serviceId);
        if ($cpData) {
            $emails = broodle_tools_get_emails($serviceId);
            $output .= broodle_tools_build_email_output($emails, $serviceId);
        }
    }

    // ── Domain tab center fix ──
    if (!empty($output)) {
        $output .= '<style>.cpanel-actions-btn{text-align:center}</style>';
    }

    // ── Shared JS ──
    if (!empty($output)) {
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
            . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
            . '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>'
            . '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>'
            . '</svg></button></div>';
    }

    if (!empty($serverIp)) {
        $eIp = htmlspecialchars($serverIp);
        $rows .= '<div class="bns-row">'
            . '<div class="bns-badge" style="background:rgba(5,150,105,.08);color:#059669">IP</div>'
            . '<div class="bns-host">' . $eIp . '</div>'
            . '<button type="button" class="bns-copy" data-ns="' . $eIp . '" title="Copy">'
            . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
            . '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>'
            . '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>'
            . '</svg></button></div>';
    }

    return '
<div id="broodle-ns-source" style="display:none">
  <div class="bns-card" style="margin-top:20px">
    <div class="bns-card-head">
      <div class="bns-card-head-left">
        <div class="bns-icon-circle">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z"/></svg>
        </div>
        <div>
          <h5>Nameservers</h5>
          <p>Point your domain to these nameservers</p>
        </div>
      </div>
    </div>
    <div class="bns-list">' . $rows . '</div>
  </div>
</div>';
}

/* ─── Email List Output Builder ───────────────────────────── */

function broodle_tools_build_email_output($emails, $serviceId)
{
    $count = count($emails);
    $countLabel = $count === 1 ? '1 account' : $count . ' accounts';

    $emailRows = '';
    if ($count === 0) {
        $emailRows = '<div style="padding:30px 22px;text-align:center;color:var(--text-muted,#9ca3af);font-size:14px">'
            . '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 10px;display:block;opacity:.4"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>'
            . 'No email accounts found</div>';
    } else {
        foreach ($emails as $email) {
            $e = htmlspecialchars($email);
            $initial = strtoupper(substr($email, 0, 1));
            $emailRows .= '<div class="bem-row">'
                . '<div class="bem-avatar">' . $initial . '</div>'
                . '<div class="bem-email">' . $e . '</div>'
                . '<button type="button" class="bns-copy" data-ns="' . $e . '" title="Copy">'
                . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
                . '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>'
                . '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>'
                . '</svg></button></div>';
        }
    }

    return '
<div id="broodle-email-source" style="display:none">
  <div class="bns-card" style="margin-top:20px">
    <div class="bns-card-head">
      <div class="bns-card-head-left">
        <div class="bns-icon-circle" style="background:#7c3aed">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
        </div>
        <div>
          <h5>Email Accounts</h5>
          <p>' . $countLabel . '</p>
        </div>
      </div>
    </div>
    <div class="bns-list">' . $emailRows . '</div>
  </div>
</div>';
}

/* ─── Shared CSS + JS ─────────────────────────────────────── */

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
.bem-email{flex:1;font-size:14px;font-weight:500;color:var(--heading-color,#111827)}
[data-theme="dark"] .bns-card,.dark-mode .bns-card{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bns-row:hover,[data-theme="dark"] .bem-row:hover,.dark-mode .bns-row:hover,.dark-mode .bem-row:hover{background:var(--input-bg,#111827)}
[data-theme="dark"] .bns-copy,.dark-mode .bns-copy{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
</style>

<script>
(function(){
    "use strict";
    function broodleInit(){
        // Find Lagom tab nav
        var tabNav=document.querySelector("ul.panel-tabs.nav.nav-tabs");
        if(!tabNav) tabNav=document.querySelector(".section-body ul.nav.nav-tabs");
        var panel=tabNav?(tabNav.closest(".panel")||tabNav.parentNode):null;
        var tabContent=panel?panel.querySelector(".tab-content"):null;

        // Nameservers tab
        var nsSrc=document.getElementById("broodle-ns-source");
        if(nsSrc){
            var nsHtml=nsSrc.innerHTML;
            nsSrc.parentNode.removeChild(nsSrc);
            if(!document.getElementById("broodleNsInfo")){
                if(tabNav&&tabContent){
                    var li=document.createElement("li");
                    li.innerHTML="<a href=\"#broodleNsInfo\" data-toggle=\"tab\"><i class=\"fas fa-globe\"></i> Nameservers</a>";
                    tabNav.appendChild(li);
                    var p=document.createElement("div");
                    p.className="panel-body tab-pane";p.id="broodleNsInfo";p.innerHTML=nsHtml;
                    tabContent.appendChild(p);
                    bindCopy(p);
                } else {
                    var to=document.getElementById("tabOverview");
                    if(to&&to.parentNode){
                        var p2=document.createElement("div");
                        p2.id="broodleNsInfo";p2.className="tab-pane fade";p2.innerHTML=nsHtml;
                        to.parentNode.appendChild(p2);bindCopy(p2);
                    }
                }
            }
        }

        // Email list tab
        var emSrc=document.getElementById("broodle-email-source");
        if(emSrc){
            var emHtml=emSrc.innerHTML;
            emSrc.parentNode.removeChild(emSrc);
            if(!document.getElementById("broodleEmailInfo")){
                if(tabNav&&tabContent){
                    var li2=document.createElement("li");
                    li2.innerHTML="<a href=\"#broodleEmailInfo\" data-toggle=\"tab\"><i class=\"fas fa-envelope\"></i> Emails</a>";
                    tabNav.appendChild(li2);
                    var ep=document.createElement("div");
                    ep.className="panel-body tab-pane";ep.id="broodleEmailInfo";ep.innerHTML=emHtml;
                    tabContent.appendChild(ep);
                    bindCopy(ep);
                } else {
                    var to2=document.getElementById("tabOverview");
                    if(to2&&to2.parentNode){
                        var ep2=document.createElement("div");
                        ep2.id="broodleEmailInfo";ep2.className="tab-pane fade";ep2.innerHTML=emHtml;
                        to2.parentNode.appendChild(ep2);bindCopy(ep2);
                    }
                }
            }
        }
    }

    function bindCopy(c){
        var btns=c.querySelectorAll(".bns-copy");
        for(var i=0;i<btns.length;i++){
            btns[i].addEventListener("click",function(){
                doCopy(this.getAttribute("data-ns"),this);
            });
        }
    }
    function doCopy(t,btn){
        if(navigator.clipboard&&navigator.clipboard.writeText){
            navigator.clipboard.writeText(t).then(function(){done(btn);});
        }else{
            var ta=document.createElement("textarea");
            ta.value=t;ta.style.cssText="position:fixed;opacity:0";
            document.body.appendChild(ta);ta.select();
            document.execCommand("copy");document.body.removeChild(ta);done(btn);
        }
    }
    function done(btn){
        btn.classList.add("copied");
        setTimeout(function(){btn.classList.remove("copied");},1500);
    }

    if(document.readyState==="loading"){
        document.addEventListener("DOMContentLoaded",broodleInit);
    }else{
        setTimeout(broodleInit,150);
    }
})();
</script>';
}
