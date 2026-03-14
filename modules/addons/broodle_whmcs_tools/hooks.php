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
    if (!$serviceId) return [];

    try {
        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
        if (!$service) return [];

        $product = Capsule::table('tblproducts')->where('id', $service->packageid)->first();
        if (!$product || strtolower($product->servertype) !== 'cpanel') return [];

        if (!$service->server) return [];

        $server = Capsule::table('tblservers')->where('id', $service->server)->first();
        if (!$server) return [];

        $ns = [];
        for ($i = 1; $i <= 5; $i++) {
            $f = 'nameserver' . $i;
            if (!empty($server->$f)) $ns[] = $server->$f;
        }
        return $ns;
    } catch (\Exception $e) {
        return [];
    }
}

/**
 * HOOK 1: Add "Nameservers" to the sidebar navigation.
 *
 * WHMCS renders the product details page with sidebar items that act as
 * tab toggles. Lagom transforms these into its own nav component.
 * By adding our item the same way WHMCS core does, it works natively.
 */
add_hook('ClientAreaPrimarySidebar', 1, function (MenuItem $sidebar) {
    if (!broodle_tools_ns_enabled()) return;

    $serviceId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if (!$serviceId) return;

    $ns = broodle_tools_get_ns_for_service($serviceId);
    if (empty($ns)) return;

    // Find the sidebar panel that contains the product detail tabs
    $panel = null;
    $panelNames = [
        'Service Details Overview',
        'Service Details Actions',
    ];

    foreach ($panelNames as $name) {
        $panel = $sidebar->getChild($name);
        if ($panel) break;
    }

    if (!$panel) {
        foreach ($sidebar->getChildren() as $child) {
            foreach ($child->getChildren() as $grandchild) {
                if ($grandchild->getAttribute('dataToggleTab')) {
                    $panel = $child;
                    break 2;
                }
            }
        }
    }

    if (!$panel) return;

    $panel->addChild('Nameservers', [
        'label' => 'Nameservers',
        'uri' => '#tabNameservers',
        'order' => 50,
        'icon' => 'fas fa-globe',
        'attributes' => [
            'dataToggleTab' => true,
        ],
    ]);
});

/**
 * HOOK 2: Inject the #tabNameservers tab pane content.
 *
 * ClientAreaProductDetailsOutput returns HTML rendered inside #tabOverview.
 * We output a hidden container + JS that moves it to be a sibling tab pane
 * of #tabOverview, #tabDomains, etc. — so it works as a proper tab.
 */
add_hook('ClientAreaProductDetailsOutput', 1, function ($vars) {
    if (!broodle_tools_ns_enabled()) return '';

    $serviceId = 0;
    if (!empty($vars['serviceid'])) $serviceId = (int) $vars['serviceid'];
    elseif (!empty($vars['id'])) $serviceId = (int) $vars['id'];
    elseif (isset($vars['service']) && is_object($vars['service'])) $serviceId = (int) $vars['service']->id;
    elseif (!empty($_GET['id'])) $serviceId = (int) $_GET['id'];
    if (!$serviceId) return '';

    $nameservers = broodle_tools_get_ns_for_service($serviceId);
    if (empty($nameservers)) return '';

    // Build NS rows HTML
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

    $allNsText = implode("\n", $nameservers);
    $allNsJs = htmlspecialchars(json_encode($allNsText), ENT_QUOTES);

    $html = '
<style>
/* Broodle Nameservers Tab Styles */
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
.bns-note{padding:12px 22px 16px;font-size:12px;color:var(--text-muted,#9ca3af);display:flex;align-items:center;gap:6px;border-top:1px solid var(--border-color,#f3f4f6)}
/* Dark mode support for Lagom and others */
[data-theme="dark"] .bns-card,.dark-mode .bns-card{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
[data-theme="dark"] .bns-row:hover,.dark-mode .bns-row:hover{background:var(--input-bg,#111827)}
[data-theme="dark"] .bns-copy,.dark-mode .bns-copy{background:var(--card-bg,#1f2937);border-color:var(--border-color,#374151)}
</style>

<!-- Hidden container: JS will move this into the proper tab pane -->
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
        <div class="bns-list">' . $rows . '</div>
        <div class="bns-note">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            DNS changes may take up to 48 hours to propagate worldwide.
        </div>
    </div>
</div>

<script>
(function(){
    "use strict";

    function broodleInitNsTab() {
        var source = document.getElementById("broodle-ns-source");
        if (!source) return;

        var content = source.innerHTML;
        source.parentNode.removeChild(source);

        // Strategy 1: Find #tabOverview and create a sibling tab pane
        var tabOverview = document.getElementById("tabOverview");
        if (tabOverview && tabOverview.parentNode) {
            var pane = document.createElement("div");
            pane.id = "tabNameservers";
            pane.className = "tab-pane fade";
            // Copy any container classes from tabOverview for Lagom compat
            if (tabOverview.classList.contains("container")) {
                pane.classList.add("container");
            }
            pane.innerHTML = content;
            tabOverview.parentNode.appendChild(pane);
            broodleBindCopy(pane);
            return;
        }

        // Strategy 2: Lagom may use different container structure
        // Look for .tab-content that contains product detail panes
        var tabContents = document.querySelectorAll(".tab-content");
        for (var i = 0; i < tabContents.length; i++) {
            var tc = tabContents[i];
            // Check if this tab-content has panes with IDs like tabOverview, tabDomains
            if (tc.querySelector("[id^=tab]")) {
                var pane2 = document.createElement("div");
                pane2.id = "tabNameservers";
                pane2.className = "tab-pane fade";
                pane2.innerHTML = content;
                tc.appendChild(pane2);
                broodleBindCopy(pane2);
                return;
            }
        }

        // Strategy 3: Lagom uses .main-content or similar wrapper
        // Create a standalone section that shows/hides via the sidebar link
        var wrapper = document.querySelector(".main-content") || document.querySelector("#main-body") || document.body;
        var standalone = document.createElement("div");
        standalone.id = "tabNameservers";
        standalone.className = "tab-pane fade";
        standalone.innerHTML = content;
        wrapper.appendChild(standalone);
        broodleBindCopy(standalone);

        // Wire up the sidebar link manually
        var nsLink = document.querySelector("a[href=\"#tabNameservers\"]");
        if (nsLink) {
            nsLink.addEventListener("click", function(e) {
                e.preventDefault();
                // Hide all sibling tab panes
                var allPanes = standalone.parentNode.querySelectorAll(".tab-pane");
                for (var j = 0; j < allPanes.length; j++) {
                    allPanes[j].classList.remove("active", "in", "show");
                }
                standalone.classList.add("active", "in", "show");
                standalone.style.display = "block";
            });
        }
    }

    function broodleBindCopy(container) {
        // Single copy buttons
        var btns = container.querySelectorAll(".bns-copy");
        for (var i = 0; i < btns.length; i++) {
            btns[i].addEventListener("click", function() {
                var btn = this;
                var ns = btn.getAttribute("data-ns");
                broodleCopyText(ns, btn);
            });
        }

        // Copy all button
        var copyAll = container.querySelector(".bns-copy-all");
        if (copyAll) {
            copyAll.addEventListener("click", function() {
                var raw = this.getAttribute("data-all-ns");
                try { raw = JSON.parse(raw); } catch(e) {}
                broodleCopyText(raw, this);
            });
        }
    }

    function broodleCopyText(text, btn) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                broodleCopied(btn);
            });
        } else {
            var ta = document.createElement("textarea");
            ta.value = text;
            ta.style.position = "fixed";
            ta.style.opacity = "0";
            document.body.appendChild(ta);
            ta.select();
            document.execCommand("copy");
            document.body.removeChild(ta);
            broodleCopied(btn);
        }
    }

    function broodleCopied(btn) {
        btn.classList.add("copied");
        var orig = btn.innerHTML;
        if (btn.classList.contains("bns-copy-all")) {
            btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Copied!';
        }
        setTimeout(function() {
            btn.classList.remove("copied");
            if (btn.classList.contains("bns-copy-all")) {
                btn.innerHTML = orig;
            }
        }, 1500);
    }

    // Run when DOM is ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", broodleInitNsTab);
    } else {
        broodleInitNsTab();
    }
})();
</script>';

    return $html;
});
