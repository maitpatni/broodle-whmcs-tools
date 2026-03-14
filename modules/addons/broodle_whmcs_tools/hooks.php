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

/**
 * Helper: check if nameservers tweak is enabled.
 */
function broodle_tools_ns_enabled()
{
    try {
        return Capsule::table('mod_broodle_tools_settings')
            ->where('setting_key', 'tweak_nameservers_tab')
            ->value('setting_value') === '1';
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * Helper: get nameservers for a service.
 */
function broodle_tools_get_ns_for_service($serviceId)
{
    if (!$serviceId) return ['ns' => [], 'ip' => ''];

    try {
        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$service) return ['ns' => [], 'ip' => ''];

        $product = Capsule::table('tblproducts')->where('id', $service->packageid)->first();
        if (!$product || strtolower($product->servertype) !== 'cpanel') return ['ns' => [], 'ip' => ''];

        if (!$service->server) return ['ns' => [], 'ip' => ''];

        $server = Capsule::table('tblservers')->where('id', $service->server)->first();
        if (!$server) return ['ns' => [], 'ip' => ''];

        $ns = [];
        for ($i = 1; $i <= 5; $i++) {
            $f = 'nameserver' . $i;
            if (!empty($server->$f)) $ns[] = $server->$f;
        }

        // Get the dedicated IP for this service, fallback to server IP
        $ip = '';
        if (!empty($service->dedicatedip)) {
            $ip = $service->dedicatedip;
        } elseif (!empty($server->ipaddress)) {
            $ip = $server->ipaddress;
        }

        return ['ns' => $ns, 'ip' => $ip];
    } catch (\Exception $e) {
        return ['ns' => [], 'ip' => ''];
    }
}

/**
 * Inject nameservers tab into the product details page.
 *
 * Lagom uses this structure for the billing/domain tabs:
 *   <div class="section-body">
 *     <div class="panel panel-default">
 *       <ul class="panel-tabs nav nav-tabs">
 *         <li><a href="#billingInfo" data-toggle="tab">Billing Overview</a></li>
 *         <li><a href="#domainInfo" data-toggle="tab">Domain</a></li>
 *       </ul>
 *       <div class="tab-content">
 *         <div class="panel-body tab-pane active" id="billingInfo">...</div>
 *         <div class="panel-body tab-pane" id="domainInfo">...</div>
 *       </div>
 *     </div>
 *   </div>
 *
 * We inject JS that adds a "Nameservers" tab + pane into that exact structure.
 * For Six/Twenty-One, falls back to #tabOverview sibling approach.
 */
add_hook('ClientAreaProductDetailsOutput', 1, function ($vars) {
    if (!broodle_tools_ns_enabled()) return '';

    $serviceId = 0;
    if (!empty($vars['serviceid'])) {
        $serviceId = (int) $vars['serviceid'];
    } elseif (!empty($vars['id'])) {
        $serviceId = (int) $vars['id'];
    } elseif (isset($vars['service']) && is_object($vars['service'])) {
        $serviceId = (int) $vars['service']->id;
    } elseif (!empty($_GET['id'])) {
        $serviceId = (int) $_GET['id'];
    }
    if (!$serviceId) return '';

    $data = broodle_tools_get_ns_for_service($serviceId);
    $nameservers = $data['ns'];
    $serverIp = $data['ip'];
    if (empty($nameservers)) return '';

    // Build IP row
    $ipRow = '';
    if (!empty($serverIp)) {
        $eIp = htmlspecialchars($serverIp);
        $ipRow = '<div class="bns-row">'
            . '<div class="bns-badge" style="background:rgba(5,150,105,.08);color:#059669">IP</div>'
            . '<div class="bns-host">' . $eIp . '</div>'
            . '<button type="button" class="bns-copy" data-ns="' . $eIp . '" title="Copy">'
            . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
            . '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>'
            . '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>'
            . '</svg></button></div>';
    }

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

    $allNsJs = htmlspecialchars(json_encode(implode("\n", $nameservers)), ENT_QUOTES);

    return broodle_tools_ns_css()
         . broodle_tools_ns_card($rows, $ipRow, $allNsJs)
         . broodle_tools_ns_script();
});

/** CSS for the nameservers card. */
function broodle_tools_ns_css()
{
    return '
<style>
.bns-card{background:var(--card-bg,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:12px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
.bns-card-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border-color,#f3f4f6)}
.bns-card-head-left{display:flex;align-items:center;gap:12px}
.bns-icon-circle{width:38px;height:38px;background:#0a5ed3;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0}
.bns-card-head h5{margin:0;font-size:15px;font-weight:600;color:var(--heading-color,#111827)}
.bns-card-head p{margin:2px 0 0;font-size:12px;color:var(--text-muted,#6b7280)}
.bns-copy-all{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;font-size:12px;font-weight:600;color:#0a5ed3;background:rgba(10,94,211,.08);border:1px solid rgba(10,94,211,.18);border-radius:7px;cursor:pointer;transition:all .15s}
.bns-copy-all:hover{background:#0a5ed3;color:#fff;border-color:#0a5ed3}
.bns-list{padding:8px 10px}
.bns-row{display:flex;align-items:center;gap:14px;padding:13px 14px;border-radius:9px;transition:background .15s}
.bns-row:hover{background:var(--input-bg,#f9fafb)}
.bns-row+.bns-row{border-top:1px solid var(--border-color,#f3f4f6)}
.bns-badge{width:38px;height:28px;background:rgba(10,94,211,.08);color:#0a5ed3;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;letter-spacing:.3px;flex-shrink:0}
.bns-host{flex:1;font-size:14px;font-weight:600;color:var(--heading-color,#111827);font-family:"SFMono-Regular",Consolas,"Liberation Mono",Menlo,monospace}
.bns-copy{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid var(--border-color,#e5e7eb);border-radius:7px;background:var(--card-bg,#fff);color:var(--text-muted,#9ca3af);cursor:pointer;transition:all .15s;flex-shrink:0}
.bns-copy:hover{color:#0a5ed3;border-color:#0a5ed3}
.bns-copy.copied{color:#fff;background:#059669;border-color:#059669}
[data-theme="dark"] .bns-card,.dark-mode .bns-card{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bns-row:hover,.dark-mode .bns-row:hover{background:var(--input-bg,#111827)}
[data-theme="dark"] .bns-copy,.dark-mode .bns-copy{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
</style>';
}

/** Hidden card HTML. */
function broodle_tools_ns_card($rows, $ipRow, $allNsJs)
{
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
      <button type="button" class="bns-copy-all" data-all-ns="' . $allNsJs . '">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        Copy All
      </button>
    </div>
    <div class="bns-list">' . $rows . $ipRow . '</div>
  </div>
</div>';
}

/** JavaScript to inject tab into Lagom panel-tabs nav. */
function broodle_tools_ns_script()
{
    return '
<script>
(function(){
    "use strict";
    function broodleInit(){
        var src=document.getElementById("broodle-ns-source");
        if(!src)return;
        var html=src.innerHTML;
        src.parentNode.removeChild(src);
        if(document.getElementById("broodleNsInfo"))return;

        // === LAGOM: find ul.panel-tabs that contains #billingInfo or #domainInfo ===
        var tabNav=document.querySelector("ul.panel-tabs.nav.nav-tabs");
        if(!tabNav){
            // fallback: any ul.nav-tabs inside .section-body
            tabNav=document.querySelector(".section-body ul.nav.nav-tabs");
        }
        if(tabNav){
            // Find the sibling .tab-content
            var panel=tabNav.closest(".panel")||tabNav.parentNode;
            var tabContent=panel.querySelector(".tab-content");
            if(tabContent){
                // Add the nav tab
                var li=document.createElement("li");
                li.innerHTML="<a href=\"#broodleNsInfo\" data-toggle=\"tab\"><i class=\"fas fa-globe\"></i> Nameservers</a>";
                tabNav.appendChild(li);

                // Add the tab pane
                var pane=document.createElement("div");
                pane.className="panel-body tab-pane";
                pane.id="broodleNsInfo";
                pane.innerHTML=html;
                tabContent.appendChild(pane);

                broodleBindCopy(pane);
                return;
            }
        }

        // === SIX / TWENTY-ONE fallback: #tabOverview sibling ===
        var tabOverview=document.getElementById("tabOverview");
        if(tabOverview&&tabOverview.parentNode){
            var pane2=document.createElement("div");
            pane2.id="broodleNsInfo";
            pane2.className="tab-pane fade";
            pane2.innerHTML=html;
            tabOverview.parentNode.appendChild(pane2);
            broodleBindCopy(pane2);

            // Try to add nav item to sidebar
            var ovLink=document.querySelector("a[href=\"#tabOverview\"]");
            if(ovLink){
                var navC=ovLink.parentNode.parentNode;
                var newLi=ovLink.parentNode.cloneNode(true);
                var newA=newLi.querySelector("a");
                if(newA){
                    newA.setAttribute("href","#broodleNsInfo");
                    newA.setAttribute("data-toggle","tab");
                    newA.innerHTML="<i class=\"fas fa-globe fa-fw\"></i> Nameservers";
                    newLi.classList.remove("active");
                }
                navC.appendChild(newLi);
            }
            return;
        }
    }

    function broodleBindCopy(c){
        var btns=c.querySelectorAll(".bns-copy");
        for(var i=0;i<btns.length;i++){
            btns[i].addEventListener("click",function(){
                doCopy(this.getAttribute("data-ns"),this);
            });
        }
        var ca=c.querySelector(".bns-copy-all");
        if(ca){
            ca.addEventListener("click",function(){
                var r=this.getAttribute("data-all-ns");
                try{r=JSON.parse(r);}catch(e){}
                doCopy(r,this);
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
            document.execCommand("copy");document.body.removeChild(ta);
            done(btn);
        }
    }

    function done(btn){
        btn.classList.add("copied");
        var o=btn.innerHTML;
        if(btn.classList.contains("bns-copy-all")){
            btn.innerHTML="<svg width=\"13\" height=\"13\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"20 6 9 17 4 12\"/></svg> Copied!";
        }
        setTimeout(function(){
            btn.classList.remove("copied");
            if(btn.classList.contains("bns-copy-all"))btn.innerHTML=o;
        },1500);
    }

    if(document.readyState==="loading"){
        document.addEventListener("DOMContentLoaded",broodleInit);
    }else{
        setTimeout(broodleInit,150);
    }
})();
</script>';
}
