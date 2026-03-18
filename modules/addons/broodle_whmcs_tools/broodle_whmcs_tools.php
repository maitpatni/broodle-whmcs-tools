<?php
/**
 * Broodle WHMCS Tools
 *
 * A comprehensive WHMCS addon module providing various tweaks and enhancements.
 *
 * @package    BroodleWHMCSTools
 * @author     Broodle
 * @copyright  2026 Broodle
 * @link       https://broodle.host
 * @version    3.10.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

use WHMCS\Database\Capsule;

define('BROODLE_TOOLS_VERSION', '3.10.88');
define('BROODLE_TOOLS_GITHUB_REPO', 'maitpatni/broodle-whmcs-tools');
define('BROODLE_TOOLS_MODULE_DIR', __DIR__);

/**
 * Module configuration.
 */
function broodle_whmcs_tools_config()
{
    return [
        'name'        => 'Broodle WHMCS Tools',
        'description' => 'A collection of tweaks and enhancements for WHMCS by Broodle.',
        'version'     => BROODLE_TOOLS_VERSION,
        'author'      => '<a href="https://broodle.host" target="_blank">Broodle</a>',
        'language'    => 'english',
        'fields'      => [],
    ];
}

/**
 * Module activation.
 */
function broodle_whmcs_tools_activate()
{
    try {
        if (!Capsule::schema()->hasTable('mod_broodle_tools_settings')) {
            Capsule::schema()->create('mod_broodle_tools_settings', function ($table) {
                $table->increments('id');
                $table->string('setting_key', 255)->unique();
                $table->text('setting_value')->nullable();
                $table->timestamps();
            });
        }

        // Insert default settings
        $defaults = [
            'tweak_nameservers_tab' => '1',
            'tweak_email_list'      => '1',
            'tweak_wordpress_toolkit' => '0',
            'tweak_domain_management' => '1',
            'tweak_database_management' => '1',
            'tweak_ssl_management'  => '1',
            'tweak_dns_management'  => '1',
            'tweak_cron_management' => '1',
            'tweak_php_version'     => '1',
            'tweak_error_logs'      => '1',
            'tweak_file_manager'    => '1',
            'tweak_analytics'       => '1',
            'tweak_upgrade_list_layout' => '0',
            'tweak_manage_v2_dropdown' => '1',
            'tweak_manage_v2_banner'   => '1',
            'auto_update_enabled'   => '0',
        ];

        foreach ($defaults as $key => $value) {
            $exists = Capsule::table('mod_broodle_tools_settings')
                ->where('setting_key', $key)
                ->first();

            if (!$exists) {
                Capsule::table('mod_broodle_tools_settings')->insert([
                    'setting_key'   => $key,
                    'setting_value' => $value,
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
            }
        }

        // Install bridge hook in includes/hooks/ for reliable hook loading.
        // Some WHMCS versions/configurations don't auto-load addon module hooks.php,
        // but includes/hooks/*.php files are always loaded.
        broodle_tools_install_bridge_hook();
        broodle_tools_install_managev2_page();

        return ['status' => 'success', 'description' => 'Broodle WHMCS Tools activated successfully.'];
    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Module deactivation.
 */
function broodle_whmcs_tools_deactivate()
{
    try {
        Capsule::schema()->dropIfExists('mod_broodle_tools_settings');

        // Remove bridge hook
        $bridgePath = ROOTDIR . '/includes/hooks/broodle_whmcs_tools.php';
        if (file_exists($bridgePath)) {
            @unlink($bridgePath);
        }

        // Remove managev2 page from root
        $managev2Path = ROOTDIR . '/managev2.php';
        if (file_exists($managev2Path)) {
            @unlink($managev2Path);
        }

        return ['status' => 'success', 'description' => 'Broodle WHMCS Tools deactivated successfully.'];
    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Admin area output.
 */
/**
 * Client area output — renders the Manage V2 page.
 * Accessed via: index.php?m=broodle_whmcs_tools&id=SERVICE_ID
 */
function broodle_whmcs_tools_clientarea($vars)
{
    $serviceId = (int) ($_GET['id'] ?? 0);
    $clientId  = (int) ($_SESSION['uid'] ?? 0);

    if (!$serviceId || !$clientId) {
        return [
            'pagetitle'    => 'Manage V2',
            'breadcrumb'   => ['index.php?m=broodle_whmcs_tools' => 'Manage V2'],
            'templatefile' => 'templates/error',
            'requirelogin' => true,
            'vars'         => ['error' => 'Missing service ID.'],
        ];
    }

    $service = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->where('userid', $clientId)
        ->first();

    if (!$service) {
        return [
            'pagetitle'    => 'Manage V2',
            'breadcrumb'   => ['index.php?m=broodle_whmcs_tools' => 'Manage V2'],
            'templatefile' => 'templates/error',
            'requirelogin' => true,
            'vars'         => ['error' => 'Service not found or access denied.'],
        ];
    }

    // Gather data
    require_once __DIR__ . '/hooks.php';
    $data = broodle_tools_gather_data(['serviceid' => $serviceId, 'userid' => $clientId]);

    if (!$data) {
        return [
            'pagetitle'    => 'Manage V2',
            'breadcrumb'   => ['index.php?m=broodle_whmcs_tools' => 'Manage V2'],
            'templatefile' => 'templates/error',
            'requirelogin' => true,
            'vars'         => ['error' => 'Could not load service data. This service may not be a cPanel product.'],
        ];
    }

    $product     = Capsule::table('tblproducts')->where('id', $service->packageid)->first();
    $productName = $product ? $product->name : 'Service';
    $version     = BROODLE_TOOLS_VERSION;
    $ts          = time();

    return [
        'pagetitle'    => htmlspecialchars($productName) . ' — Manage V2',
        'breadcrumb'   => [
            'clientarea.php?action=productdetails&id=' . $serviceId => htmlspecialchars($productName),
            'index.php?m=broodle_whmcs_tools&id=' . $serviceId      => 'Manage V2',
        ],
        'templatefile' => 'templates/managev2',
        'requirelogin' => true,
        'vars'         => [
            'bt_config_b64' => base64_encode(json_encode($data)),
            'bt_js_url'     => 'modules/addons/broodle_whmcs_tools/bt_client.js?v=' . $version . '&t=' . $ts,
            'serviceId'     => $serviceId,
        ],
    ];
}

/**
 * Admin area output.
 */
function broodle_whmcs_tools_output($vars)
{
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

    // Handle settings save
    if ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $tweaks = [
            'tweak_nameservers_tab',
            'tweak_email_list',
            'tweak_wordpress_toolkit',
            'tweak_domain_management',
            'tweak_database_management',
            'tweak_ssl_management',
            'tweak_dns_management',
            'tweak_cron_management',
            'tweak_php_version',
            'tweak_error_logs',
            'tweak_file_manager',
            'tweak_analytics',
            'tweak_upgrade_list_layout',
            'tweak_manage_v2_dropdown',
            'tweak_manage_v2_banner',
            'auto_update_enabled',
        ];

        foreach ($tweaks as $tweak) {
            $value = isset($_POST[$tweak]) ? '1' : '0';
            Capsule::table('mod_broodle_tools_settings')
                ->updateOrInsert(
                    ['setting_key' => $tweak],
                    ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')]
                );
        }

        echo '<div class="successbox"><strong>Settings saved successfully.</strong></div>';
    }

    // Handle update check
    if ($action === 'check_update') {
        $updateInfo = broodle_tools_check_for_update();
        if (!empty($updateInfo['error'])) {
            echo '<div class="errorbox"><strong>Update check failed:</strong> '
                . htmlspecialchars($updateInfo['error']) . '</div>';
        } elseif ($updateInfo['available']) {
            $source = !empty($updateInfo['asset_url']) ? 'release asset' : 'source archive';
            echo '<div class="infobox"><strong>Update Available!</strong> Version '
                . htmlspecialchars($updateInfo['latest_version'])
                . ' is available (via ' . $source . '). You are running ' . BROODLE_TOOLS_VERSION . '.'
                . ' <a href="' . $vars['modulelink'] . '&action=apply_update" class="btn btn-success btn-sm">Apply Update</a></div>';
        } else {
            echo '<div class="successbox"><strong>You are running the latest version (' . BROODLE_TOOLS_VERSION . ').</strong></div>';
        }
    }

    // Handle update apply
    if ($action === 'apply_update') {
        $result = broodle_tools_apply_update();
        if ($result['success']) {
            echo '<div class="successbox"><strong>' . htmlspecialchars($result['message']) . '</strong></div>';
        } else {
            echo '<div class="errorbox"><strong>' . htmlspecialchars($result['message']) . '</strong></div>';
        }
    }

    // Load current settings
    $settings = [];
    $rows = Capsule::table('mod_broodle_tools_settings')->get();
    foreach ($rows as $row) {
        $settings[$row->setting_key] = $row->setting_value;
    }

    // Render admin page
    echo broodle_tools_render_admin($vars, $settings);
}

/**
 * Render the admin settings page.
 */

function broodle_tools_render_admin($vars, $settings)
{
    $moduleLink = $vars['modulelink'];

    // Helper to check setting
    $isOn = function ($key) use ($settings) {
        return !empty($settings[$key]) && $settings[$key] === '1';
    };

    // Build toggle row helper
    $toggle = function ($name, $label, $desc, $icon, $checked) {
        $chk = $checked ? 'checked' : '';
        return '<div class="bt-row"><div class="bt-row-icon">' . $icon . '</div><div class="bt-row-info"><h4>' . $label . '</h4><p>' . $desc . '</p></div><label class="bt-toggle"><input type="checkbox" name="' . $name . '" value="1" ' . $chk . '><span class="bt-slider"></span></label></div>';
    };

    // SVG icons for each feature
    $icons = [
        'ns'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z"/></svg>',
        'email'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
        'wp'       => '<svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor"><path d="M12.633 7.653c0-.848-.305-1.435-.566-1.892l-.08-.13c-.317-.51-.594-.958-.594-1.48 0-.63.478-1.218 1.152-1.218q.03 0 .058.003l.031.003A6.84 6.84 0 0 0 8 1.137 6.86 6.86 0 0 0 2.266 4.23c.16.005.313.009.442.009.717 0 1.828-.087 1.828-.087.37-.022.414.521.044.565 0 0-.371.044-.785.065l2.5 7.434 1.5-4.506-1.07-2.929c-.369-.022-.719-.065-.719-.065-.37-.022-.326-.588.043-.566 0 0 1.134.087 1.808.087.718 0 1.83-.087 1.83-.087.37-.022.413.522.043.566 0 0-.372.043-.785.065l2.48 7.377.684-2.287.054-.173c.27-.86.469-1.495.469-2.046z"/></svg>',
        'domain'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'db'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>',
        'ssl'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        'dns'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',
        'cron'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'php'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/><line x1="14" y1="4" x2="10" y2="20"/></svg>',
        'logs'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'fm'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
        'analytics'=> '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>',
        'upgrade'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
        'dropdown' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>',
        'banner'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
        'update'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
    ];

    $html = '
    <style>
        .bt-wrap{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;max-width:780px;margin:0 auto}
        .bt-wrap *,.bt-wrap *::before,.bt-wrap *::after{box-sizing:border-box}
        .bt-head{display:flex;align-items:center;gap:16px;margin-bottom:28px;padding:24px 28px;background:linear-gradient(135deg,#0a5ed3 0%,#2563eb 50%,#7c3aed 100%);border-radius:16px;color:#fff;position:relative;overflow:hidden}
        .bt-head::before{content:"";position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.08) 0%,transparent 60%);pointer-events:none}
        .bt-logo{width:48px;height:48px;background:rgba(255,255,255,.18);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .bt-logo svg{color:#fff}
        .bt-head-text h2{margin:0;font-size:22px;font-weight:700;color:#fff;letter-spacing:-.3px}
        .bt-head-text span{font-size:13px;color:rgba(255,255,255,.8)}
        .bt-head-text span a{color:rgba(255,255,255,.9);text-decoration:underline;text-underline-offset:2px}
        .bt-head-text span a:hover{color:#fff}
        .bt-section{margin-bottom:24px}
        .bt-section-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#9ca3af;margin-bottom:10px;padding-left:4px}
        .bt-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04)}
        .bt-row{display:flex;align-items:center;gap:14px;padding:14px 20px;transition:background .1s}
        .bt-row:hover{background:#f9fafb}
        .bt-row+.bt-row{border-top:1px solid #f3f4f6}
        .bt-row-icon{width:36px;height:36px;border-radius:10px;background:#f0f4ff;color:#0a5ed3;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .bt-row-info{flex:1;min-width:0}
        .bt-row-info h4{margin:0;font-size:14px;font-weight:600;color:#111827}
        .bt-row-info p{margin:2px 0 0;font-size:12px;color:#6b7280;line-height:1.4}
        .bt-toggle{position:relative;width:44px;height:24px;flex-shrink:0}
        .bt-toggle input{position:absolute;opacity:0;width:0;height:0}
        .bt-toggle .bt-slider{position:absolute;inset:0;background:#d1d5db;border-radius:24px;cursor:pointer;transition:background .2s}
        .bt-toggle .bt-slider::before{content:"";position:absolute;width:18px;height:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.12)}
        .bt-toggle input:checked+.bt-slider{background:#0a5ed3}
        .bt-toggle input:checked+.bt-slider::before{transform:translateX(20px)}
        .bt-actions{display:flex;align-items:center;gap:10px;margin-top:20px}
        .bt-btn{display:inline-flex;align-items:center;gap:6px;padding:10px 22px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .15s;line-height:1}
        .bt-btn-primary{background:#0a5ed3;color:#fff;box-shadow:0 1px 3px rgba(10,94,211,.3)}
        .bt-btn-primary:hover{background:#0950b3;color:#fff;text-decoration:none;box-shadow:0 2px 8px rgba(10,94,211,.35)}
        .bt-btn-outline{background:#fff;color:#374151;border:1px solid #d1d5db}
        .bt-btn-outline:hover{background:#f9fafb;border-color:#9ca3af;color:#111827;text-decoration:none}
        .bt-update-card{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:14px;margin-bottom:24px}
        .bt-update-card .bt-uc-left{display:flex;align-items:center;gap:12px}
        .bt-update-card .bt-uc-icon{width:40px;height:40px;border-radius:10px;background:#059669;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .bt-update-card .bt-uc-text span{font-size:13px;color:#065f46;font-weight:600}
        .bt-update-card .bt-uc-text p{margin:2px 0 0;font-size:12px;color:#047857}
        .bt-footer{margin-top:28px;padding-top:16px;border-top:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between}
        .bt-footer span{font-size:12px;color:#9ca3af}
        .bt-footer a{font-size:12px;color:#0a5ed3;text-decoration:none}
        .bt-footer a:hover{text-decoration:underline}
        .bt-enable-all{font-size:12px;color:#0a5ed3;cursor:pointer;border:none;background:none;font-weight:600;padding:0}
        .bt-enable-all:hover{text-decoration:underline}
    </style>

    <div class="bt-wrap">
        <div class="bt-head">
            <div class="bt-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                </svg>
            </div>
            <div class="bt-head-text">
                <h2>Broodle WHMCS Tools</h2>
                <span>v' . BROODLE_TOOLS_VERSION . ' &middot; <a href="https://broodle.host" target="_blank">broodle.host</a></span>
            </div>
        </div>

        <div class="bt-update-card">
            <div class="bt-uc-left">
                <div class="bt-uc-icon">' . $icons['update'] . '</div>
                <div class="bt-uc-text">
                    <span>Installed Version: v' . BROODLE_TOOLS_VERSION . '</span>
                    <p>Check GitHub for the latest release</p>
                </div>
            </div>
            <a href="' . $moduleLink . '&action=check_update" class="bt-btn bt-btn-outline" style="padding:8px 16px;font-size:12px">' . $icons['update'] . ' Check for Update</a>
        </div>

        <form method="post" action="' . $moduleLink . '&action=save_settings">

            <div class="bt-section">
                <div class="bt-section-label">Hosting Management Tabs</div>
                <div class="bt-card">'
                    . $toggle('tweak_nameservers_tab', 'Nameservers', 'Display assigned nameservers in a dedicated tab', $icons['ns'], $isOn('tweak_nameservers_tab'))
                    . $toggle('tweak_email_list', 'Email Accounts', 'List and manage email accounts with create, delete, and quota controls', $icons['email'], $isOn('tweak_email_list'))
                    . $toggle('tweak_domain_management', 'Domains', 'Manage addon domains, subdomains, and parked domains', $icons['domain'], $isOn('tweak_domain_management'))
                    . $toggle('tweak_database_management', 'Databases', 'MySQL database management with phpMyAdmin access', $icons['db'], $isOn('tweak_database_management'))
                    . $toggle('tweak_ssl_management', 'SSL Certificates', 'View SSL status, expiry dates, and trigger AutoSSL', $icons['ssl'], $isOn('tweak_ssl_management'))
                    . $toggle('tweak_dns_management', 'DNS Manager', 'Full DNS zone editor with bulk operations', $icons['dns'], $isOn('tweak_dns_management'))
                    . $toggle('tweak_cron_management', 'Cron Jobs', 'Schedule and manage cron jobs with common presets', $icons['cron'], $isOn('tweak_cron_management'))
                    . $toggle('tweak_php_version', 'PHP Version', 'View and switch PHP versions per domain', $icons['php'], $isOn('tweak_php_version'))
                    . $toggle('tweak_error_logs', 'Error Logs', 'Live error log viewer with color-coded severity levels', $icons['logs'], $isOn('tweak_error_logs'))
                    . $toggle('tweak_analytics', 'Analytics', 'Bandwidth usage, visitor statistics, and log archives', $icons['analytics'], $isOn('tweak_analytics'))
                . '</div>
            </div>

            <div class="bt-section">
                <div class="bt-section-label">Sidebar Features</div>
                <div class="bt-card">'
                    . $toggle('tweak_file_manager', 'File Manager', 'Browse, edit, upload, and manage files with built-in code editor', $icons['fm'], $isOn('tweak_file_manager'))
                    . $toggle('tweak_wordpress_toolkit', 'WordPress Toolkit', 'Full WP management — plugins, themes, security, auto-login, updates', $icons['wp'], $isOn('tweak_wordpress_toolkit'))
                . '</div>
            </div>

            <div class="bt-section">
                <div class="bt-section-label">UI Enhancements</div>
                <div class="bt-card">'
                    . $toggle('tweak_upgrade_list_layout', 'Upgrade List Layout', 'Convert upgrade/downgrade page from grid to a clean list view', $icons['upgrade'], $isOn('tweak_upgrade_list_layout'))
                    . $toggle('tweak_manage_v2_dropdown', 'Manage V2 Dropdown', 'Add "Manage V2" to the service dropdown menu on Dashboard', $icons['dropdown'], $isOn('tweak_manage_v2_dropdown'))
                    . $toggle('tweak_manage_v2_banner', 'Manage V2 Banner', 'Show a Manage V2 banner on the product details page', $icons['banner'], $isOn('tweak_manage_v2_banner'))
                . '</div>
            </div>

            <div class="bt-section">
                <div class="bt-section-label">System</div>
                <div class="bt-card">'
                    . $toggle('auto_update_enabled', 'Auto Update Check', 'Periodically check GitHub for new versions', $icons['update'], $isOn('auto_update_enabled'))
                . '</div>
            </div>

            <div class="bt-actions">
                <button type="submit" class="bt-btn bt-btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Save Settings
                </button>
            </div>
        </form>

        <div class="bt-footer">
            <span>&copy; ' . date('Y') . ' Broodle &middot; All rights reserved</span>
            <a href="https://github.com/' . BROODLE_TOOLS_GITHUB_REPO . '" target="_blank">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-2px;margin-right:4px"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                GitHub
            </a>
        </div>
    </div>';

    return $html;
}



/**
 * Check GitHub for a newer release.
 */
function broodle_tools_check_for_update()
{
    $result = ['available' => false, 'latest_version' => BROODLE_TOOLS_VERSION, 'download_url' => '', 'asset_url' => ''];

    try {
        $url = 'https://api.github.com/repos/' . BROODLE_TOOLS_GITHUB_REPO . '/releases/latest';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: BroodleWHMCSTools/' . BROODLE_TOOLS_VERSION,
                'Accept: application/vnd.github.v3+json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (!empty($data['tag_name'])) {
                $latestVersion = ltrim($data['tag_name'], 'v');
                if (version_compare($latestVersion, BROODLE_TOOLS_VERSION, '>')) {
                    $result['available'] = true;
                    $result['latest_version'] = $latestVersion;

                    // Prefer the uploaded release asset (proper zip with correct structure)
                    $assetUrl = '';
                    if (!empty($data['assets']) && is_array($data['assets'])) {
                        foreach ($data['assets'] as $asset) {
                            if (
                                isset($asset['browser_download_url']) &&
                                preg_match('/\.zip$/i', $asset['browser_download_url'])
                            ) {
                                $assetUrl = $asset['browser_download_url'];
                                break;
                            }
                        }
                    }

                    // Use release asset if available, otherwise fall back to zipball
                    $result['download_url'] = $assetUrl ?: ($data['zipball_url'] ?? '');
                    $result['asset_url'] = $assetUrl; // track which type we're using
                }
            }
        } else {
            $result['error'] = "GitHub API returned HTTP {$httpCode}" . ($curlError ? ": {$curlError}" : '');
        }
    } catch (\Exception $e) {
        $result['error'] = $e->getMessage();
    }

    return $result;
}

/**
 * Download and apply update from GitHub.
 */
function broodle_tools_apply_update()
{
    try {
        $updateInfo = broodle_tools_check_for_update();

        if (!$updateInfo['available']) {
            return ['success' => false, 'message' => 'No update available. You are on the latest version.'];
        }

        if (empty($updateInfo['download_url'])) {
            return ['success' => false, 'message' => 'Could not determine download URL.'];
        }

        // Download the zip
        $tmpFile = tempnam(sys_get_temp_dir(), 'broodle_update_');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $updateInfo['download_url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: BroodleWHMCSTools/' . BROODLE_TOOLS_VERSION,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $zipData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !$zipData) {
            @unlink($tmpFile);
            return ['success' => false, 'message' => "Failed to download update (HTTP {$httpCode})" . ($curlError ? ": {$curlError}" : '')];
        }

        file_put_contents($tmpFile, $zipData);

        $zip = new \ZipArchive();
        $openResult = $zip->open($tmpFile);
        if ($openResult !== true) {
            @unlink($tmpFile);
            return ['success' => false, 'message' => 'Failed to open update package (ZipArchive error: ' . $openResult . ').'];
        }

        $extractDir = sys_get_temp_dir() . '/broodle_update_' . uniqid();
        $zip->extractTo($extractDir);
        $zip->close();
        @unlink($tmpFile);

        // Find the module source directory inside the extracted content
        // Strategy 1: Release asset zip — has modules/addons/broodle_whmcs_tools/ at root or inside a top-level dir
        // Strategy 2: GitHub zipball — has {user}-{repo}-{hash}/modules/addons/broodle_whmcs_tools/
        // Strategy 3: Windows-created zip may use backslashes — normalize paths
        $moduleSrc = null;
        $moduleRelPath = 'modules' . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'broodle_whmcs_tools';

        // Check direct path first (release asset structure) — try both separators
        if (is_dir($extractDir . '/modules/addons/broodle_whmcs_tools')) {
            $moduleSrc = $extractDir . '/modules/addons/broodle_whmcs_tools';
        } elseif (is_dir($extractDir . DIRECTORY_SEPARATOR . $moduleRelPath)) {
            $moduleSrc = $extractDir . DIRECTORY_SEPARATOR . $moduleRelPath;
        } else {
            // Check inside top-level directory (GitHub zipball structure)
            $topDirs = glob($extractDir . '/*', GLOB_ONLYDIR);
            foreach ($topDirs as $topDir) {
                if (is_dir($topDir . '/modules/addons/broodle_whmcs_tools')) {
                    $moduleSrc = $topDir . '/modules/addons/broodle_whmcs_tools';
                    break;
                }
                if (is_dir($topDir . DIRECTORY_SEPARATOR . $moduleRelPath)) {
                    $moduleSrc = $topDir . DIRECTORY_SEPARATOR . $moduleRelPath;
                    break;
                }
            }
            // Strategy 3: Recursive search for broodle_whmcs_tools.php as last resort
            if (!$moduleSrc) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($extractDir, \RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($it as $file) {
                    if ($file->getFilename() === 'broodle_whmcs_tools.php') {
                        $moduleSrc = $file->getPath();
                        break;
                    }
                }
            }
        }

        if (!$moduleSrc || !is_dir($moduleSrc)) {
            broodle_tools_delete_directory($extractDir);
            return ['success' => false, 'message' => 'Update package does not contain the expected module structure (modules/addons/broodle_whmcs_tools/).'];
        }

        // Verify the source has the main module file
        if (!file_exists($moduleSrc . '/broodle_whmcs_tools.php')) {
            broodle_tools_delete_directory($extractDir);
            return ['success' => false, 'message' => 'Update package is missing the main module file.'];
        }

        $destDir = BROODLE_TOOLS_MODULE_DIR;

        // Copy files recursively
        $copied = broodle_tools_copy_directory($moduleSrc, $destDir);

        // Cleanup
        broodle_tools_delete_directory($extractDir);

        if ($copied === 0) {
            return ['success' => false, 'message' => 'Update failed: no files were copied. Check directory permissions on ' . $destDir];
        }

        // Verify the version actually changed by reading the new file
        $newContent = @file_get_contents($destDir . '/broodle_whmcs_tools.php');
        $actuallyUpdated = false;
        if ($newContent && preg_match("/define\(\s*'BROODLE_TOOLS_VERSION'\s*,\s*'([^']+)'\s*\)/", $newContent, $vm)) {
            if (version_compare($vm[1], BROODLE_TOOLS_VERSION, '>')) {
                $actuallyUpdated = true;
            }
        }

        if (!$actuallyUpdated) {
            return ['success' => false, 'message' => 'Update copied ' . $copied . ' files but version did not change. The server may be caching the old file or permissions prevented overwrite. Try manually uploading the zip.'];
        }

        return ['success' => true, 'message' => 'Updated to version ' . $updateInfo['latest_version'] . ' successfully (' . $copied . ' files). Please refresh the page.'];
    } catch (\Exception $e) {
        return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
    }
}

/**
 * Recursively copy a directory. Returns number of files copied.
 */
function broodle_tools_copy_directory($src, $dst)
{
    $count = 0;
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }

    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        if (is_dir($srcPath)) {
            $count += broodle_tools_copy_directory($srcPath, $dstPath);
        } else {
            if (@copy($srcPath, $dstPath)) {
                $count++;
            }
        }
    }
    closedir($dir);
    return $count;
}

/**
 * Recursively delete a directory.
 */
function broodle_tools_delete_directory($dir)
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getRealPath());
        } else {
            unlink($item->getRealPath());
        }
    }

    rmdir($dir);
}

/**
 * Helper: Get a module setting value.
 */
function broodle_tools_get_setting($key, $default = '')
{
    try {
        $row = Capsule::table('mod_broodle_tools_settings')
            ->where('setting_key', $key)
            ->first();

        return $row ? $row->setting_value : $default;
    } catch (\Exception $e) {
        return $default;
    }
}

/**
 * Install a bridge hook file in includes/hooks/ for reliable hook loading.
 *
 * WHMCS guarantees that all .php files in includes/hooks/ (except those
 * starting with underscore) are loaded on every page request. Some WHMCS
 * versions or configurations may not reliably auto-load addon module
 * hooks.php files, so this bridge ensures our hooks always fire.
 */
function broodle_tools_install_bridge_hook()
{
    $bridgePath = ROOTDIR . '/includes/hooks/broodle_whmcs_tools.php';

    $bridgeContent = '<?php
/**
 * Broodle WHMCS Tools - Hook Bridge
 *
 * This file ensures the addon module hooks are loaded reliably.
 * WHMCS auto-loads all .php files in includes/hooks/ on every request.
 * Auto-generated by Broodle WHMCS Tools — do not edit manually.
 *
 * @see modules/addons/broodle_whmcs_tools/hooks.php
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly.");
}

$broodleHooksFile = ROOTDIR . "/modules/addons/broodle_whmcs_tools/hooks.php";
if (file_exists($broodleHooksFile)) {
    require_once $broodleHooksFile;
}
';

    @file_put_contents($bridgePath, $bridgeContent);
}

/**
 * Install managev2.php bootstrap at WHMCS root.
 * WHMCS custom pages must be in the root directory for $ca->output() to work properly.
 */
function broodle_tools_install_managev2_page()
{
    $rootPage = ROOTDIR . '/managev2.php';
    $modulePage = ROOTDIR . '/modules/addons/broodle_whmcs_tools/managev2.php';

    $content = '<?php
/**
 * Broodle WHMCS Tools — Manage V2 Bootstrap
 * Auto-generated by Broodle WHMCS Tools — do not edit manually.
 * Loads the actual page from the module directory.
 */
require_once __DIR__ . "/modules/addons/broodle_whmcs_tools/managev2.php";
';

    @file_put_contents($rootPage, $content);

    // Also ensure the template file exists
    try {
        $templateName = \WHMCS\Database\Capsule::table("tblconfiguration")
            ->where("setting", "Template")
            ->value("value") ?: "lagom2";
    } catch (\Exception $e) {
        $templateName = "lagom2";
    }

    $tplFile = ROOTDIR . '/templates/' . $templateName . '/managev2.tpl';
    if (!file_exists($tplFile)) {
        @file_put_contents($tplFile, '{$bt_content nofilter}');
    }
}
