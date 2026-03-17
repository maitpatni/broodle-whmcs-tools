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

define('BROODLE_TOOLS_VERSION', '3.10.84');
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
    $nameserversEnabled = !empty($settings['tweak_nameservers_tab']) && $settings['tweak_nameservers_tab'] === '1';
    $emailListEnabled = !empty($settings['tweak_email_list']) && $settings['tweak_email_list'] === '1';
    $wpToolkitEnabled = !empty($settings['tweak_wordpress_toolkit']) && $settings['tweak_wordpress_toolkit'] === '1';
    $domainMgmtEnabled = !empty($settings['tweak_domain_management']) && $settings['tweak_domain_management'] === '1';
    $dbMgmtEnabled = !empty($settings['tweak_database_management']) && $settings['tweak_database_management'] === '1';
    $sslMgmtEnabled = !empty($settings['tweak_ssl_management']) && $settings['tweak_ssl_management'] === '1';
    $dnsMgmtEnabled = !empty($settings['tweak_dns_management']) && $settings['tweak_dns_management'] === '1';
    $cronMgmtEnabled = !empty($settings['tweak_cron_management']) && $settings['tweak_cron_management'] === '1';
    $phpVersionEnabled = !empty($settings['tweak_php_version']) && $settings['tweak_php_version'] === '1';
    $errorLogsEnabled = !empty($settings['tweak_error_logs']) && $settings['tweak_error_logs'] === '1';
    $fileManagerEnabled = !empty($settings['tweak_file_manager']) && $settings['tweak_file_manager'] === '1';
    $upgradeListEnabled = !empty($settings['tweak_upgrade_list_layout']) && $settings['tweak_upgrade_list_layout'] === '1';
    $manageV2DropdownEnabled = !empty($settings['tweak_manage_v2_dropdown']) && $settings['tweak_manage_v2_dropdown'] === '1';
    $manageV2BannerEnabled = !empty($settings['tweak_manage_v2_banner']) && $settings['tweak_manage_v2_banner'] === '1';
    $autoUpdateEnabled = !empty($settings['auto_update_enabled']) && $settings['auto_update_enabled'] === '1';

    $html = '
    <style>
        .bt-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; max-width: 720px; }
        .bt-wrap *, .bt-wrap *::before, .bt-wrap *::after { box-sizing: border-box; }

        /* Header */
        .bt-head { display: flex; align-items: center; gap: 16px; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid #e5e7eb; }
        .bt-logo { width: 44px; height: 44px; background: #0a5ed3; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .bt-logo svg { color: #fff; }
        .bt-head-text h2 { margin: 0; font-size: 20px; font-weight: 700; color: #111827; letter-spacing: -0.3px; }
        .bt-head-text span { font-size: 13px; color: #6b7280; }
        .bt-head-text span a { color: #0a5ed3; text-decoration: none; }

        /* Section */
        .bt-section { margin-bottom: 28px; }
        .bt-section-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; color: #9ca3af; margin-bottom: 12px; }

        /* Card */
        .bt-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }

        /* Row */
        .bt-row { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; }
        .bt-row + .bt-row { border-top: 1px solid #f3f4f6; }
        .bt-row-info { flex: 1; min-width: 0; }
        .bt-row-info h4 { margin: 0; font-size: 14px; font-weight: 600; color: #111827; }
        .bt-row-info p { margin: 3px 0 0; font-size: 13px; color: #6b7280; line-height: 1.4; }

        /* Toggle */
        .bt-toggle { position: relative; width: 44px; height: 24px; flex-shrink: 0; margin-left: 20px; }
        .bt-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
        .bt-toggle .bt-slider { position: absolute; inset: 0; background: #d1d5db; border-radius: 24px; cursor: pointer; transition: background 0.2s; }
        .bt-toggle .bt-slider::before { content: ""; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: transform 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .bt-toggle input:checked + .bt-slider { background: #0a5ed3; }
        .bt-toggle input:checked + .bt-slider::before { transform: translateX(20px); }

        /* Buttons */
        .bt-actions { display: flex; align-items: center; gap: 10px; margin-top: 24px; }
        .bt-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.15s; line-height: 1; }
        .bt-btn-primary { background: #0a5ed3; color: #fff; }
        .bt-btn-primary:hover { background: #0950b3; color: #fff; text-decoration: none; }
        .bt-btn-outline { background: #fff; color: #374151; border: 1px solid #d1d5db; }
        .bt-btn-outline:hover { background: #f9fafb; border-color: #9ca3af; color: #111827; text-decoration: none; }

        /* Update bar */
        .bt-update-bar { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; border-top: 1px solid #f3f4f6; background: #f9fafb; }
        .bt-update-bar span { font-size: 13px; color: #6b7280; }
        .bt-update-bar span strong { color: #111827; font-weight: 600; }

        /* Footer */
        .bt-footer { margin-top: 32px; padding-top: 16px; border-top: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; }
        .bt-footer span { font-size: 12px; color: #9ca3af; }
        .bt-footer a { font-size: 12px; color: #0a5ed3; text-decoration: none; }
        .bt-footer a:hover { text-decoration: underline; }

        /* Toast notification */
        .bt-toast { display: none; position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 12px 20px; border-radius: 8px; font-size: 13px; font-weight: 500; color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.15); animation: btSlideIn 0.3s ease; }
        .bt-toast.success { background: #059669; }
        .bt-toast.error { background: #dc2626; }
        .bt-toast.info { background: #0a5ed3; }
        .bt-toast.show { display: block; }
        @keyframes btSlideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

    </style>

    <div class="bt-wrap">
        <div class="bt-head">
            <div class="bt-logo">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                </svg>
            </div>
            <div class="bt-head-text">
                <h2>Broodle WHMCS Tools</h2>
                <span>v' . BROODLE_TOOLS_VERSION . ' · <a href="https://broodle.host" target="_blank">broodle.host</a></span>
            </div>
        </div>

        <form method="post" action="' . $moduleLink . '&action=save_settings">

            <div class="bt-section">
                <div class="bt-section-label">Tweaks</div>
                <div class="bt-card">
                    <div class="bt-row">
                        <div class="bt-row-info">
                            <h4>Nameservers Tab</h4>
                            <p>Show a Nameservers tab on cPanel product details with the assigned nameservers.</p>
                        </div>
                        <label class="bt-toggle">
                            <input type="checkbox" name="tweak_nameservers_tab" value="1" ' . ($nameserversEnabled ? 'checked' : '') . '>
                            <span class="bt-slider"></span>
                        </label>
                    </div>
                    <div class="bt-row">
                        <div class="bt-row-info">
                            <h4>Email Accounts List</h4>
                            <p>Show an Email Accounts tab on cPanel product details listing all email accounts.</p>
                        </div>
                        <label class="bt-toggle">
                            <input type="checkbox" name="tweak_email_list" value="1" ' . ($emailListEnabled ? 'checked' : '') . '>
                            <span class="bt-slider"></span>
                        </label>
                    </div>
                    <div class="bt-row">
                        <div class="bt-row-info">
                            <h4>WordPress Toolkit</h4>
                            <p>Show a WordPress tab on cPanel product details with full WP management (plugins, themes, security, auto-login).</p>
                        </div>
                        <label class="bt-toggle">
                            <input type="checkbox" name="tweak_wordpress_toolkit" value="1" ' . ($wpToolkitEnabled ? 'checked' : '') . '>
                            <span class="bt-slider"></span>
                        </label>
                    </div>
                    <div class="bt-row">
                        <div class="bt-row-info">
                            <h4>Domain Management</h4>
                            <p>Show a Domains tab on cPanel product details with addon domains, subdomains, and management options.</p>
                        </div>
                        <label class="bt-toggle">
                            <input type="checkbox" name="tweak_domain_management" value="1" ' . ($domainMgmtEnabled ? 'checked' : '') . '>
                            <span class="bt-slider"></span>
                        </label>
                    </div>
                    <div class="bt-row">
                        <div class="bt-row-info">
                            <h4>Database Management</h4>
                            <p>Show a Databases tab on cPanel product details with MySQL database management, phpMyAdmin access, and user privileges.</p>
                        </div>
                        <label class="bt-toggle">
                            <input type="checkbox" name="tweak_database_management" value="1" ' . ($dbMgmtEnabled ? 'checked' : '') . '>
                            <span class="bt-slider"></span>
                        </label>
                    </div>
                    <div class="bt-row">
                        <div class="bt-row-info">
                            <h4>SSL Management</h4>
                            <p>Show an SSL tab on cPanel product details with SSL certificate status, expiry info, and AutoSSL generation.</p>
                        </div>
                        <label class="bt-toggle">
                            <input type="checkbox" name="tweak_ssl_management" value="1" ' . ($sslMgmtEnabled ? 'checked' : '') . '>
                            <span class="bt-slider"></span>
                        </label>
                    </div>
                    <div class="bt-row">
                        <div class="bt-row-info">
                            <h4>DNS Manager</h4>
                            <p>Show a DNS Manager tab on cPanel product details with full DNS zone management, bulk editing, and record creation.</p>
                        </div>
                        <label class="bt-toggle">
                            <input type="checkbox" name="tweak_dns_management" value="1" ' . ($dnsMgmtEnabled ? 'checked' : '') . '>
                            <span class="bt-slider"></span>
                        </label>
                    </div>
                    <div class="bt-row">
                        <div class="bt-row-info">
                            <h4>Cron Jobs</h4>
                            <p>Show a Cron Jobs tab on cPanel product details with cron job management, common presets, and scheduling.</p>
                        </div>
                        <label class="bt-toggle">
                            <input type="checkbox" name="tweak_cron_management" value="1" ' . ($cronMgmtEnabled ? 'checked' : '') . '>
                            <span class="bt-slider"></span>
                        </label>
                    </div>
                    <div class="bt-row">
                        <div class="bt-row-info">
                            <h4>PHP Version</h4>
                            <p>Show a PHP Version tab on cPanel product details to view and switch PHP versions per domain.</p>
                        </div>
                        <label class="bt-toggle">
                            <input type="checkbox" name="tweak_php_version" value="1" ' . ($phpVersionEnabled ? 'checked' : '') . '>
                            <span class="bt-slider"></span>
                        </label>
                    </div>
                    <div class="bt-row">
                        <div class="bt-row-info">
                            <h4>Error Logs</h4>
                            <p>Show an Error Logs tab on cPanel product details with live error log viewing and auto-refresh.</p>
                        </div>
                        <label class="bt-toggle">
                            <input type="checkbox" name="tweak_error_logs" value="1" ' . ($errorLogsEnabled ? 'checked' : '') . '>
                            <span class="bt-slider"></span>
                        </label>
                    </div>
                    <div class="bt-row">
                        <div class="bt-row-info">
                            <h4>File Manager</h4>
                            <p>Add a File Manager to the sidebar for browsing, editing, uploading, and managing files directly via cPanel.</p>
                        </div>
                        <label class="bt-toggle">
                            <input type="checkbox" name="tweak_file_manager" value="1" ' . ($fileManagerEnabled ? 'checked' : '') . '>
                            <span class="bt-slider"></span>
                        </label>
                    </div>
                    <div class="bt-row">
                        <div class="bt-row-info">
                            <h4>Upgrade Page List Layout</h4>
                            <p>Convert the upgrade/downgrade page from grid boxes to a clean list layout for better readability.</p>
                        </div>
                        <label class="bt-toggle">
                            <input type="checkbox" name="tweak_upgrade_list_layout" value="1" ' . ($upgradeListEnabled ? 'checked' : '') . '>
                            <span class="bt-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="bt-section">
                <div class="bt-section-label">Manage V2</div>
                <div class="bt-card">
                    <div class="bt-row">
                        <div class="bt-row-info">
                            <h4>Manage V2 Dropdown</h4>
                            <p>Add a "Manage V2" option to the dropdown menu on the Dashboard and Services List pages for cPanel products.</p>
                        </div>
                        <label class="bt-toggle">
                            <input type="checkbox" name="tweak_manage_v2_dropdown" value="1" ' . ($manageV2DropdownEnabled ? 'checked' : '') . '>
                            <span class="bt-slider"></span>
                        </label>
                    </div>
                    <div class="bt-row">
                        <div class="bt-row-info">
                            <h4>Manage V2 Banner</h4>
                            <p>Show a Manage V2 beta banner at the top of the cPanel product details page instead of buttons and sidebar links.</p>
                        </div>
                        <label class="bt-toggle">
                            <input type="checkbox" name="tweak_manage_v2_banner" value="1" ' . ($manageV2BannerEnabled ? 'checked' : '') . '>
                            <span class="bt-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="bt-section">
                <div class="bt-section-label">Updates</div>
                <div class="bt-card">
                    <div class="bt-row">
                        <div class="bt-row-info">
                            <h4>Auto Update</h4>
                            <p>Check for new versions from the GitHub repository automatically.</p>
                        </div>
                        <label class="bt-toggle">
                            <input type="checkbox" name="auto_update_enabled" value="1" ' . ($autoUpdateEnabled ? 'checked' : '') . '>
                            <span class="bt-slider"></span>
                        </label>
                    </div>
                    <div class="bt-update-bar">
                        <span>Installed: <strong>v' . BROODLE_TOOLS_VERSION . '</strong></span>
                        <a href="' . $moduleLink . '&action=check_update" class="bt-btn bt-btn-outline" style="padding:6px 14px;">Check for Update</a>
                    </div>
                </div>
            </div>

            <div class="bt-actions">
                <button type="submit" class="bt-btn bt-btn-primary">Save Settings</button>
            </div>
        </form>

        <div class="bt-footer">
            <span>&copy; ' . date('Y') . ' Broodle</span>
            <a href="https://github.com/' . BROODLE_TOOLS_GITHUB_REPO . '" target="_blank">GitHub</a>
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
        broodle_tools_copy_directory($moduleSrc, $destDir);

        // Cleanup
        broodle_tools_delete_directory($extractDir);

        return ['success' => true, 'message' => 'Updated to version ' . $updateInfo['latest_version'] . ' successfully. Please refresh the page.'];
    } catch (\Exception $e) {
        return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
    }
}

/**
 * Recursively copy a directory.
 */
function broodle_tools_copy_directory($src, $dst)
{
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
            broodle_tools_copy_directory($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);
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
