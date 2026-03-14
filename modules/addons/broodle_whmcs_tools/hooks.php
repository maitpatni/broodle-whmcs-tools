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

/**
 * Nameservers Tab Tweak
 *
 * Injects a "Nameservers" tab into the cPanel product details page.
 * Uses ClientAreaProductDetailsOutput to inject HTML/CSS/JS.
 * The JS uses multiple strategies to find the tab container across
 * Lagom, Six, Twenty-One and other WHMCS themes.
 */
add_hook('ClientAreaProductDetailsOutput', 1, function ($vars) {
    // Check if the tweak is enabled
    try {
        $enabled = Capsule::table('mod_broodle_tools_settings')
            ->where('setting_key', 'tweak_nameservers_tab')
            ->value('setting_value');

        if ($enabled !== '1') {
            return '';
        }
    } catch (\Exception $e) {
        return '';
    }

    // Resolve service ID — try every known key
    $serviceId = 0;
    if (!empty($vars['serviceid'])) {
        $serviceId = (int) $vars['serviceid'];
    }
    if (!$serviceId && !empty($vars['id'])) {
        $serviceId = (int) $vars['id'];
    }
    if (!$serviceId && isset($vars['service']) && is_object($vars['service'])) {
        $serviceId = (int) $vars['service']->id;
    }
    if (!$serviceId && !empty($_GET['id'])) {
        $serviceId = (int) $_GET['id'];
    }
    if (!$serviceId && !empty($_REQUEST['id'])) {
        $serviceId = (int) $_REQUEST['id'];
    }

    if (!$serviceId) {
        return '';
    }

    try {
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first();

        if (!$service) {
            return '';
        }

        $product = Capsule::table('tblproducts')
            ->where('id', $service->packageid)
            ->first();

        if (!$product || !in_array(strtolower($product->servertype), ['cpanel'])) {
            return '';
        }

        $serverId = $service->server;
        $nameservers = [];

        if ($serverId) {
            $server = Capsule::table('tblservers')
                ->where('id', $serverId)
                ->first();

            if ($server) {
                for ($i = 1; $i <= 5; $i++) {
                    $nsField = 'nameserver' . $i;
                    if (!empty($server->$nsField)) {
                        $nameservers[] = $server->$nsField;
                    }
                }
            }
        }

        if (empty($nameservers)) {
            return '';
        }

        // Build nameserver rows
        $nsRowsHtml = '';
        foreach ($nameservers as $index => $ns) {
            $num = $index + 1;
            $nsEsc = htmlspecialchars($ns);
            $nsRowsHtml .= '<div class="bns-row">'
                . '<div class="bns-badge">NS' . $num . '</div>'
                . '<div class="bns-host">' . $nsEsc . '</div>'
                . '<button type="button" class="bns-copy" data-ns="' . $nsEsc . '" title="Copy">'
                . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>'
                . '</button></div>';
        }

        $allNsJson = htmlspecialchars(json_encode(implode("\n", $nameservers)), ENT_QUOTES);

        $output = '
<style>
/* ── Broodle Nameservers ── */
.bns-standalone,
#broodleNsTab {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}
.bns-standalone { margin-top: 24px; }
.bns-card {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: 12px;
    overflow: hidden;
}
.bns-card-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 22px;
    border-bottom: 1px solid var(--border-color, #f3f4f6);
}
.bns-card-head-left { display: flex; align-items: center; gap: 12px; }
.bns-icon-circle {
    width: 38px; height: 38px; background: #0a5ed3; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0;
}
.bns-card-head h5 { margin: 0; font-size: 15px; font-weight: 600; color: var(--heading-color, #111827); }
.bns-card-head p { margin: 2px 0 0; font-size: 12px; color: var(--text-muted, #6b7280); }
.bns-copy-all {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 14px; font-size: 12px; font-weight: 600;
    color: #0a5ed3; background: rgba(10,94,211,0.08);
    border: 1px solid rgba(10,94,211,0.18); border-radius: 7px;
    cursor: pointer; transition: all 0.15s;
}
.bns-copy-all:hover { background: #0a5ed3; color: #fff; border-color: #0a5ed3; }
.bns-list { padding: 8px 10px; }
.bns-row {
    display: flex; align-items: center; gap: 14px;
    padding: 13px 14px; border-radius: 9px; transition: background 0.15s;
}
.bns-row:hover { background: var(--input-bg, #f9fafb); }
.bns-row + .bns-row { border-top: 1px solid var(--border-color, #f3f4f6); }
.bns-badge {
    width: 38px; height: 28px; background: rgba(10,94,211,0.08); color: #0a5ed3;
    border-radius: 6px; display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; letter-spacing: 0.3px; flex-shrink: 0;
}
.bns-host {
    flex: 1; font-size: 14px; font-weight: 600; color: var(--heading-color, #111827);
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
}
.bns-copy {
    width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
    border: 1px solid var(--border-color, #e5e7eb); border-radius: 7px;
    background: var(--card-bg, #fff); color: var(--text-muted, #9ca3af);
    cursor: pointer; transition: all 0.15s; flex-shrink: 0;
}
.bns-copy:hover { color: #0a5ed3; border-color: #0a5ed3; }
.bns-copy.copied { color: #fff; background: #059669; border-color: #059669; }
.bns-note {
    padding: 12px 22px 16px; font-size: 12px; color: var(--text-muted, #9ca3af);
    display: flex; align-items: center; gap: 6px;
    border-top: 1px solid var(--border-color, #f3f4f6);
}
/* Dark mode */
[data-theme="dark"] .bns-card, .dark-mode .bns-card { background: var(--card-bg, #1f2937); border-color: var(--border-color, #374151); }
[data-theme="dark"] .bns-row:hover, .dark-mode .bns-row:hover { background: var(--input-bg, #111827); }
</style>

<div id="broodleNsSource" style="display:none;">
    <div class="bns-card">
        <div class="bns-card-head">
            <div class="bns-card-head-left">
                <div class="bns-icon-circle">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </div>
                <div>
                    <h5>Nameservers</h5>
                    <p>Point your domain to these nameservers to connect it to your hosting.</p>
                </div>
            </div>
            <button type="button" class="bns-copy-all" data-allns="">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                Copy All
            </button>
        </div>
        <div class="bns-list">' . $nsRowsHtml . '</div>
        <div class="bns-note">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            DNS changes may take up to 24-48 hours to propagate worldwide.
        </div>
    </div>
</div>

<script>
(function() {
    var ALL_NS = JSON.parse(\'' . $allNsJson . '\');
    var MAX_RETRIES = 20;
    var RETRY_MS = 300;
    var attempt = 0;
    var done = false;

    /* ── Copy helper ── */
    function doCopy(text, btn) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() { flash(btn); });
        } else {
            var t = document.createElement("textarea");
            t.value = text; t.style.cssText = "position:fixed;opacity:0";
            document.body.appendChild(t); t.select();
            document.execCommand("copy"); document.body.removeChild(t);
            flash(btn);
        }
    }
    function flash(b) { b.classList.add("copied"); setTimeout(function(){ b.classList.remove("copied"); }, 1400); }

    function wireButtons(container) {
        container.querySelectorAll(".bns-copy").forEach(function(b) {
            b.addEventListener("click", function() { doCopy(this.getAttribute("data-ns"), this); });
        });
        var ca = container.querySelector(".bns-copy-all");
        if (ca) ca.addEventListener("click", function() { doCopy(ALL_NS, this); });
    }

    /* ── Find tabs: try many selectors across themes ── */
    function findTabs() {
        // Ordered from most specific (Lagom) to generic
        var navSelectors = [
            "ul.nav-tabs",
            ".nav.nav-tabs",
            "[role=tablist]",
            ".tabs-nav"
        ];
        var contentSelectors = [
            ".tab-content",
            ".tabs-content"
        ];

        var nav = null, content = null;

        // Strategy 1: Find nav-tabs, then look for sibling/nearby tab-content
        for (var i = 0; i < navSelectors.length; i++) {
            var candidates = document.querySelectorAll(navSelectors[i]);
            for (var c = 0; c < candidates.length; c++) {
                var n = candidates[c];
                // Must have at least one existing tab link to be the right one
                if (n.querySelector("a.nav-link, a[data-toggle=tab], a[data-bs-toggle=tab], li a")) {
                    nav = n;
                    break;
                }
            }
            if (nav) break;
        }

        if (nav) {
            // Look for tab-content: sibling, parent child, or walk up
            for (var j = 0; j < contentSelectors.length; j++) {
                // Direct sibling
                content = nav.parentElement ? nav.parentElement.querySelector(contentSelectors[j]) : null;
                if (content) break;
                // Walk up a few levels
                var parent = nav.parentElement;
                for (var k = 0; k < 5 && parent; k++) {
                    content = parent.querySelector(contentSelectors[j]);
                    if (content && content !== nav) break;
                    content = null;
                    parent = parent.parentElement;
                }
                if (content) break;
            }
        }

        return { nav: nav, content: content };
    }

    /* ── Inject tab ── */
    function injectTab(nav, content) {
        var source = document.getElementById("broodleNsSource");
        if (!source) return;

        // Create tab link
        var li = document.createElement("li");
        li.className = "nav-item";
        li.setAttribute("role", "presentation");

        var a = document.createElement("a");
        a.className = "nav-link";
        a.id = "broodleNsTabLink";
        a.setAttribute("href", "#broodleNsTab");
        a.setAttribute("role", "tab");
        a.setAttribute("aria-controls", "broodleNsTab");
        a.setAttribute("aria-selected", "false");
        a.setAttribute("data-toggle", "tab");
        a.setAttribute("data-bs-toggle", "tab");
        a.innerHTML = \'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;vertical-align:-2px;"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>Nameservers\';

        li.appendChild(a);
        nav.appendChild(li);

        // Create tab pane
        var pane = document.createElement("div");
        pane.className = "tab-pane fade";
        pane.id = "broodleNsTab";
        pane.setAttribute("role", "tabpanel");
        pane.setAttribute("aria-labelledby", "broodleNsTabLink");
        pane.innerHTML = source.innerHTML;
        content.appendChild(pane);

        // Remove hidden source
        source.parentNode.removeChild(source);

        // Wire copy buttons
        wireButtons(pane);
        done = true;
    }

    /* ── Fallback: show as standalone panel ── */
    function showStandalone() {
        var source = document.getElementById("broodleNsSource");
        if (!source) return;
        source.style.display = "block";
        source.className = "bns-standalone";
        source.id = "broodleNsTab";
        wireButtons(source);
        done = true;
    }

    /* ── Main: retry until tabs appear or give up ── */
    function tryInit() {
        if (done) return;
        var source = document.getElementById("broodleNsSource");
        if (!source) return;

        var tabs = findTabs();
        if (tabs.nav && tabs.content) {
            injectTab(tabs.nav, tabs.content);
            return;
        }

        attempt++;
        if (attempt < MAX_RETRIES) {
            setTimeout(tryInit, RETRY_MS);
        } else {
            // After ~6 seconds, give up on tabs and show standalone
            showStandalone();
        }
    }

    // Start after DOM ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", tryInit);
    } else {
        tryInit();
    }
})();
</script>';

        return $output;

    } catch (\Exception $e) {
        return '';
    }
});
