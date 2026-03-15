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
 * @version    3.5.2
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

use WHMCS\Database\Capsule;

define('BROODLE_TOOLS_VERSION', '3.5.2');
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
        return ['status' => 'success', 'description' => 'Broodle WHMCS Tools deactivated successfully.'];
    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => 'Error: ' . $e->getMessage()];
    }
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

        /* ─── Dark Mode (WHMCS Admin) ─── */
        @media (prefers-color-scheme: dark) {
            .bt-head { border-bottom-color: #374151; }
            .bt-head-text h2 { color: #e5e7eb; }
            .bt-head-text span { color: #9ca3af; }
            .bt-section-label { color: #6b7280; }
            .bt-card { background: #1f2937; border-color: #374151; }
            .bt-row { border-top-color: #374151 !important; }
            .bt-row + .bt-row { border-top-color: #374151; }
            .bt-row-info h4 { color: #e5e7eb; }
            .bt-row-info p { color: #9ca3af; }
            .bt-toggle .bt-slider { background: #4b5563; }
            .bt-toggle input:checked + .bt-slider { background: #2563eb; }
            .bt-update-bar { background: #111827; border-top-color: #374151; }
            .bt-update-bar span { color: #9ca3af; }
            .bt-update-bar span strong { color: #e5e7eb; }
            .bt-btn-primary { background: #2563eb; }
            .bt-btn-primary:hover { background: #1d4ed8; }
            .bt-btn-outline { background: #1f2937; color: #d1d5db; border-color: #374151; }
            .bt-btn-outline:hover { background: #111827; border-color: #6b7280; color: #e5e7eb; }
            .bt-footer { border-top-color: #374151; }
            .bt-footer span { color: #6b7280; }
            .bt-footer a { color: #5b9cf6; }
        }
        /* Also support WHMCS admin dark class if present */
        body.dark-mode .bt-head, body[data-theme="dark"] .bt-head { border-bottom-color: #374151; }
        body.dark-mode .bt-head-text h2, body[data-theme="dark"] .bt-head-text h2 { color: #e5e7eb; }
        body.dark-mode .bt-head-text span, body[data-theme="dark"] .bt-head-text span { color: #9ca3af; }
        body.dark-mode .bt-section-label, body[data-theme="dark"] .bt-section-label { color: #6b7280; }
        body.dark-mode .bt-card, body[data-theme="dark"] .bt-card { background: #1f2937; border-color: #374151; }
        body.dark-mode .bt-row + .bt-row, body[data-theme="dark"] .bt-row + .bt-row { border-top-color: #374151; }
        body.dark-mode .bt-row-info h4, body[data-theme="dark"] .bt-row-info h4 { color: #e5e7eb; }
        body.dark-mode .bt-row-info p, body[data-theme="dark"] .bt-row-info p { color: #9ca3af; }
        body.dark-mode .bt-toggle .bt-slider, body[data-theme="dark"] .bt-toggle .bt-slider { background: #4b5563; }
        body.dark-mode .bt-toggle input:checked + .bt-slider, body[data-theme="dark"] .bt-toggle input:checked + .bt-slider { background: #2563eb; }
        body.dark-mode .bt-update-bar, body[data-theme="dark"] .bt-update-bar { background: #111827; border-top-color: #374151; }
        body.dark-mode .bt-update-bar span, body[data-theme="dark"] .bt-update-bar span { color: #9ca3af; }
        body.dark-mode .bt-update-bar span strong, body[data-theme="dark"] .bt-update-bar span strong { color: #e5e7eb; }
        body.dark-mode .bt-btn-primary, body[data-theme="dark"] .bt-btn-primary { background: #2563eb; }
        body.dark-mode .bt-btn-primary:hover, body[data-theme="dark"] .bt-btn-primary:hover { background: #1d4ed8; }
        body.dark-mode .bt-btn-outline, body[data-theme="dark"] .bt-btn-outline { background: #1f2937; color: #d1d5db; border-color: #374151; }
        body.dark-mode .bt-btn-outline:hover, body[data-theme="dark"] .bt-btn-outline:hover { background: #111827; border-color: #6b7280; color: #e5e7eb; }
        body.dark-mode .bt-footer, body[data-theme="dark"] .bt-footer { border-top-color: #374151; }
        body.dark-mode .bt-footer span, body[data-theme="dark"] .bt-footer span { color: #6b7280; }
        body.dark-mode .bt-footer a, body[data-theme="dark"] .bt-footer a { color: #5b9cf6; }
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
        $moduleSrc = null;

        // Check direct path first (release asset structure)
        if (is_dir($extractDir . '/modules/addons/broodle_whmcs_tools')) {
            $moduleSrc = $extractDir . '/modules/addons/broodle_whmcs_tools';
        } else {
            // Check inside top-level directory (GitHub zipball structure)
            $topDirs = glob($extractDir . '/*', GLOB_ONLYDIR);
            foreach ($topDirs as $topDir) {
                if (is_dir($topDir . '/modules/addons/broodle_whmcs_tools')) {
                    $moduleSrc = $topDir . '/modules/addons/broodle_whmcs_tools';
                    break;
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
