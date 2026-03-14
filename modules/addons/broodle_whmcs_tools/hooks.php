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
 * Injects a "Nameservers" tab into the cPanel product details page
 * showing the service's assigned nameservers in a polished UI.
 * Works properly within the existing Bootstrap tab system used by
 * Lagom, Six, and Twenty-One themes.
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

    // Resolve service ID
    $serviceId = 0;
    if (!empty($vars['serviceid'])) {
        $serviceId = (int) $vars['serviceid'];
    } elseif (!empty($vars['id'])) {
        $serviceId = (int) $vars['id'];
    } elseif (!empty($_GET['id'])) {
        $serviceId = (int) $_GET['id'];
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

        // Build nameserver row markup
        $nsRowsHtml = '';
        foreach ($nameservers as $index => $ns) {
            $num = $index + 1;
            $nsEsc = htmlspecialchars($ns);
            $nsRowsHtml .= '
                <div class="bns-row">
                    <div class="bns-badge">NS' . $num . '</div>
                    <div class="bns-host">' . $nsEsc . '</div>
                    <button type="button" class="bns-copy" data-ns="' . $nsEsc . '" title="Copy">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    </button>
                </div>';
        }

        $allNsJson = htmlspecialchars(json_encode(implode("\n", $nameservers)), ENT_QUOTES);

        $output = '
<style>
/* ── Broodle Nameservers Tab ── */
#broodleNsTab .bns-wrap {
    padding: 24px 0 0;
}
#broodleNsTab .bns-card {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: 12px;
    overflow: hidden;
}
#broodleNsTab .bns-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 22px;
    border-bottom: 1px solid var(--border-color, #f3f4f6);
}
#broodleNsTab .bns-card-head-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
#broodleNsTab .bns-icon-circle {
    width: 38px;
    height: 38px;
    background: #0a5ed3;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    flex-shrink: 0;
}
#broodleNsTab .bns-card-head h5 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: var(--heading-color, #111827);
}
#broodleNsTab .bns-card-head p {
    margin: 2px 0 0;
    font-size: 12px;
    color: var(--text-muted, #6b7280);
}
#broodleNsTab .bns-copy-all {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 7px 14px;
    font-size: 12px;
    font-weight: 600;
    color: #0a5ed3;
    background: rgba(10,94,211,0.08);
    border: 1px solid rgba(10,94,211,0.18);
    border-radius: 7px;
    cursor: pointer;
    transition: all 0.15s;
}
#broodleNsTab .bns-copy-all:hover {
    background: #0a5ed3;
    color: #fff;
    border-color: #0a5ed3;
}
#broodleNsTab .bns-list {
    padding: 8px 10px;
}
#broodleNsTab .bns-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 13px 14px;
    border-radius: 9px;
    transition: background 0.15s;
}
#broodleNsTab .bns-row:hover {
    background: var(--input-bg, #f9fafb);
}
#broodleNsTab .bns-row + .bns-row {
    border-top: 1px solid var(--border-color, #f3f4f6);
}
#broodleNsTab .bns-badge {
    width: 38px;
    height: 28px;
    background: rgba(10,94,211,0.08);
    color: #0a5ed3;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.3px;
    flex-shrink: 0;
}
#broodleNsTab .bns-host {
    flex: 1;
    font-size: 14px;
    font-weight: 600;
    color: var(--heading-color, #111827);
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
    letter-spacing: 0.2px;
}
#broodleNsTab .bns-copy {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: 7px;
    background: var(--card-bg, #fff);
    color: var(--text-muted, #9ca3af);
    cursor: pointer;
    transition: all 0.15s;
    flex-shrink: 0;
}
#broodleNsTab .bns-copy:hover {
    color: #0a5ed3;
    border-color: #0a5ed3;
}
#broodleNsTab .bns-copy.copied {
    color: #fff;
    background: #059669;
    border-color: #059669;
}
#broodleNsTab .bns-note {
    padding: 12px 22px 16px;
    font-size: 12px;
    color: var(--text-muted, #9ca3af);
    display: flex;
    align-items: center;
    gap: 6px;
    border-top: 1px solid var(--border-color, #f3f4f6);
}

/* Dark mode */
[data-theme="dark"] #broodleNsTab .bns-card,
.dark-mode #broodleNsTab .bns-card {
    background: var(--card-bg, #1f2937);
    border-color: var(--border-color, #374151);
}
[data-theme="dark"] #broodleNsTab .bns-row:hover,
.dark-mode #broodleNsTab .bns-row:hover {
    background: var(--input-bg, #111827);
}
</style>

<!-- Hidden source: JS will move this into the proper tab pane -->
<div id="broodleNsSource" style="display:none;">
    <div class="bns-wrap">
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
                <button type="button" class="bns-copy-all" id="bnsCopyAll">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Copy All
                </button>
            </div>
            <div class="bns-list">' . $nsRowsHtml . '</div>
            <div class="bns-note">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                DNS changes may take up to 24–48 hours to propagate worldwide.
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var allNs = JSON.parse(\'' . $allNsJson . '\');

    function initBroodleNsTab() {
        var source = document.getElementById("broodleNsSource");
        if (!source) return;

        // ── Find the existing tab navigation and content containers ──
        // Lagom uses .nav-tabs inside various wrappers; Six/Twenty-One use similar structures.
        // We search broadly but pick the one closest to the product details context.
        var tabsNav = document.querySelector(
            ".service-detail .nav-tabs, " +
            ".product-details-tab-container .nav-tabs, " +
            ".main-content .nav-tabs, " +
            "#Primary_Sidebar ~ .content-padded .nav-tabs, " +
            ".nav-tabs"
        );
        var tabsContent = tabsNav
            ? tabsNav.parentElement.querySelector(".tab-content")
              || tabsNav.closest(".card, .card-body, .panel, .tab-container, .service-detail, section, .content-padded, main")?.querySelector(".tab-content")
              || document.querySelector(".tab-content")
            : null;

        if (!tabsNav || !tabsContent) {
            // Fallback: just show it inline below the hook output area
            source.style.display = "block";
            source.id = "broodleNsTab";
            return;
        }

        // ── Add the tab link ──
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
        // Support both Bootstrap 4 (data-toggle) and Bootstrap 5 (data-bs-toggle)
        a.setAttribute("data-toggle", "tab");
        a.setAttribute("data-bs-toggle", "tab");
        a.innerHTML = \'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;vertical-align:-2px;"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>Nameservers\';

        li.appendChild(a);
        tabsNav.appendChild(li);

        // ── Add the tab pane ──
        var pane = document.createElement("div");
        pane.className = "tab-pane fade";
        pane.id = "broodleNsTab";
        pane.setAttribute("role", "tabpanel");
        pane.setAttribute("aria-labelledby", "broodleNsTabLink");
        // Move the content from the hidden source into the pane
        pane.innerHTML = source.innerHTML;
        tabsContent.appendChild(pane);

        // Remove the hidden source element so it does not interfere
        source.parentNode.removeChild(source);

        // ── Wire up copy buttons inside the new pane ──
        pane.querySelectorAll(".bns-copy").forEach(function(btn) {
            btn.addEventListener("click", function() {
                var ns = this.getAttribute("data-ns");
                copyText(ns, this);
            });
        });

        var copyAllBtn = pane.querySelector("#bnsCopyAll");
        if (copyAllBtn) {
            // Remove duplicate id to keep DOM valid
            copyAllBtn.removeAttribute("id");
            copyAllBtn.addEventListener("click", function() {
                copyText(allNs, this);
            });
        }
    }

    function copyText(text, btn) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() { flashCopied(btn); });
        } else {
            // Fallback for older browsers / non-HTTPS
            var ta = document.createElement("textarea");
            ta.value = text;
            ta.style.position = "fixed";
            ta.style.opacity = "0";
            document.body.appendChild(ta);
            ta.select();
            document.execCommand("copy");
            document.body.removeChild(ta);
            flashCopied(btn);
        }
    }

    function flashCopied(btn) {
        btn.classList.add("copied");
        setTimeout(function() { btn.classList.remove("copied"); }, 1400);
    }

    // Run after DOM is ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initBroodleNsTab);
    } else {
        initBroodleNsTab();
    }
})();
</script>';

        return $output;

    } catch (\Exception $e) {
        return '';
    }
});
