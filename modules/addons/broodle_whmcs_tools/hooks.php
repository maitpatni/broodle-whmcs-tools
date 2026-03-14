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

    // Only apply to cPanel module services
    // WHMCS passes the service ID in different keys depending on version/context
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

        // Check if this is a cPanel product
        $product = Capsule::table('tblproducts')
            ->where('id', $service->packageid)
            ->first();

        if (!$product || !in_array($product->servertype, ['cpanel', 'cPanel'])) {
            return '';
        }

        // Get the server's nameservers
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

        // Build the nameserver items HTML
        $nsItemsHtml = '';
        foreach ($nameservers as $index => $ns) {
            $num = $index + 1;
            $nsEscaped = htmlspecialchars($ns);
            $nsItemsHtml .= '
                <div class="broodle-ns-item">
                    <div class="broodle-ns-left">
                        <div class="broodle-ns-icon">NS' . $num . '</div>
                        <div class="broodle-ns-details">
                            <span class="broodle-ns-label">Nameserver ' . $num . '</span>
                            <span class="broodle-ns-value" id="broodle-ns-' . $num . '">' . $nsEscaped . '</span>
                        </div>
                    </div>
                    <button type="button" class="broodle-ns-copy" onclick="broodleCopyNS(\'' . $nsEscaped . '\', this)" title="Copy to clipboard">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                    </button>
                </div>';
        }

        // Copy all button
        $allNs = implode("\n", $nameservers);
        $allNsEscaped = htmlspecialchars($allNs);

        // Return the full HTML/CSS/JS to inject
        $output = '
<style>
    /* Broodle Nameservers Tab — Lagom Compatible */
    .broodle-ns-tab-trigger {
        cursor: pointer;
    }
    .broodle-ns-panel {
        display: none;
        animation: broodleFadeIn 0.3s ease;
    }
    .broodle-ns-panel.active {
        display: block;
    }
    @keyframes broodleFadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .broodle-ns-container {
        background: var(--card-bg, #ffffff);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 12px;
        padding: 24px;
        margin-top: 0;
    }
    .broodle-ns-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border-color, #e5e7eb);
    }
    .broodle-ns-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .broodle-ns-header-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 18px;
    }
    .broodle-ns-header h4 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: var(--heading-color, #1f2937);
    }
    .broodle-ns-header p {
        margin: 2px 0 0;
        font-size: 13px;
        color: var(--text-muted, #6b7280);
    }
    .broodle-ns-copy-all {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: var(--card-bg, #f9fafb);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 8px;
        color: var(--text-color, #374151);
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    .broodle-ns-copy-all:hover {
        background: #667eea;
        color: #fff;
        border-color: #667eea;
    }
    .broodle-ns-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .broodle-ns-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        background: var(--input-bg, #f9fafb);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 10px;
        transition: all 0.2s;
    }
    .broodle-ns-item:hover {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    .broodle-ns-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .broodle-ns-icon {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .broodle-ns-details {
        display: flex;
        flex-direction: column;
    }
    .broodle-ns-label {
        font-size: 12px;
        color: var(--text-muted, #6b7280);
        font-weight: 500;
        margin-bottom: 2px;
    }
    .broodle-ns-value {
        font-size: 15px;
        font-weight: 600;
        color: var(--heading-color, #1f2937);
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
        letter-spacing: 0.3px;
    }
    .broodle-ns-copy {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 8px;
        background: var(--card-bg, #ffffff);
        color: var(--text-muted, #6b7280);
        cursor: pointer;
        transition: all 0.2s;
    }
    .broodle-ns-copy:hover {
        background: #667eea;
        color: #fff;
        border-color: #667eea;
    }
    .broodle-ns-copy.copied {
        background: #10b981;
        color: #fff;
        border-color: #10b981;
    }
    .broodle-ns-footer {
        margin-top: 16px;
        padding-top: 12px;
        border-top: 1px solid var(--border-color, #e5e7eb);
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: var(--text-muted, #9ca3af);
    }
    .broodle-ns-footer svg {
        flex-shrink: 0;
    }

    /* Dark mode support for Lagom */
    [data-theme="dark"] .broodle-ns-container,
    .dark-mode .broodle-ns-container {
        background: var(--card-bg, #1f2937);
        border-color: var(--border-color, #374151);
    }
    [data-theme="dark"] .broodle-ns-item,
    .dark-mode .broodle-ns-item {
        background: var(--input-bg, #111827);
        border-color: var(--border-color, #374151);
    }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Try multiple selectors for different WHMCS templates (Lagom, Six, Twenty-One, etc.)
    var tabSelectors = [
        ".nav-tabs",
        ".tabs-nav",
        ".service-tabs .nav",
        "[role=tablist]",
        "ul.nav.nav-tabs",
        "#tabsNav"
    ];
    var contentSelectors = [
        ".tab-content",
        ".tabs-content",
        ".service-tabs .tab-content",
        "#tabsContent"
    ];

    var tabsNav = null;
    var tabsContent = null;

    for (var i = 0; i < tabSelectors.length; i++) {
        tabsNav = document.querySelector(tabSelectors[i]);
        if (tabsNav) break;
    }
    for (var j = 0; j < contentSelectors.length; j++) {
        tabsContent = document.querySelector(contentSelectors[j]);
        if (tabsContent) break;
    }

    var source = document.getElementById("broodle-ns-content-source");
    if (!source) return;

    if (tabsNav && tabsContent) {
        // Create the tab link
        var tabLi = document.createElement("li");
        tabLi.className = "nav-item";
        tabLi.setAttribute("role", "presentation");

        var tabLink = document.createElement("a");
        tabLink.className = "nav-link";
        tabLink.setAttribute("data-toggle", "tab");
        tabLink.setAttribute("data-bs-toggle", "tab");
        tabLink.setAttribute("href", "#broodleNameserversTab");
        tabLink.setAttribute("role", "tab");
        tabLink.setAttribute("aria-controls", "broodleNameserversTab");
        tabLink.innerHTML = \'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:5px;vertical-align:middle;"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>Nameservers\';

        tabLi.appendChild(tabLink);
        tabsNav.appendChild(tabLi);

        // Create the tab pane
        var tabPane = document.createElement("div");
        tabPane.className = "tab-pane fade";
        tabPane.id = "broodleNameserversTab";
        tabPane.setAttribute("role", "tabpanel");
        tabPane.innerHTML = source.innerHTML;

        tabsContent.appendChild(tabPane);

        // Remove the hidden source
        source.parentNode.removeChild(source);
    } else {
        // No tabs found — render as a standalone panel below product details
        source.style.display = "block";
        source.style.marginTop = "20px";
    }
});

function broodleCopyNS(text, btn) {
    navigator.clipboard.writeText(text).then(function() {
        btn.classList.add("copied");
        setTimeout(function() { btn.classList.remove("copied"); }, 1500);
    });
}

function broodleCopyAllNS(text, btn) {
    navigator.clipboard.writeText(text).then(function() {
        var orig = btn.innerHTML;
        btn.innerHTML = \'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Copied!\';
        btn.style.background = "#10b981";
        btn.style.color = "#fff";
        btn.style.borderColor = "#10b981";
        setTimeout(function() {
            btn.innerHTML = orig;
            btn.style.background = "";
            btn.style.color = "";
            btn.style.borderColor = "";
        }, 2000);
    });
}
</script>

<div id="broodle-ns-content-source" style="display:none; margin-top: 20px;">
    <div class="broodle-ns-container">
        <div class="broodle-ns-header">
            <div class="broodle-ns-header-left">
                <div class="broodle-ns-header-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                    </svg>
                </div>
                <div>
                    <h4>Nameservers</h4>
                    <p>Point your domain to these nameservers to connect it to your hosting.</p>
                </div>
            </div>
            <button type="button" class="broodle-ns-copy-all" onclick="broodleCopyAllNS(\'' . str_replace("'", "\\'", $allNsEscaped) . '\', this)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
                Copy All
            </button>
        </div>

        <div class="broodle-ns-list">
            ' . $nsItemsHtml . '
        </div>

        <div class="broodle-ns-footer">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            DNS changes may take up to 24–48 hours to propagate worldwide.
        </div>
    </div>
</div>';

        return $output;

    } catch (\Exception $e) {
        return '';
    }
});
