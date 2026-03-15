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
 * @version    1.0.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

use WHMCS\Database\Capsule;

define('BROODLE_TOOLS_VERSION', '2.6.0');
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
        if ($updateInfo['available']) {
            echo '<div class="infobox"><strong>Update Available!</strong> Version '
                . htmlspecialchars($updateInfo['latest_version'])
                . ' is available. You are running ' . BROODLE_TOOLS_VERSION . '.'
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
    $result = ['available' => false, 'latest_version' => BROODLE_TOOLS_VERSION, 'download_url' => ''];

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
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (!empty($data['tag_name'])) {
                $latestVersion = ltrim($data['tag_name'], 'v');
                if (version_compare($latestVersion, BROODLE_TOOLS_VERSION, '>')) {
                    $result['available'] = true;
                    $result['latest_version'] = $latestVersion;
                    $result['download_url'] = $data['zipball_url'] ?? '';
                }
            }
        }
    } catch (\Exception $e) {
        // Silently fail — user can retry
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

        $tmpFile = tempnam(sys_get_temp_dir(), 'broodle_update_');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $updateInfo['download_url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: BroodleWHMCSTools/' . BROODLE_TOOLS_VERSION,
                'Accept: application/vnd.github.v3+json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $zipData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$zipData) {
            return ['success' => false, 'message' => 'Failed to download update package.'];
        }

        file_put_contents($tmpFile, $zipData);

        $zip = new \ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            unlink($tmpFile);
            return ['success' => false, 'message' => 'Failed to open update package.'];
        }

        // Find the module directory inside the zip
        $extractDir = sys_get_temp_dir() . '/broodle_update_' . time();
        $zip->extractTo($extractDir);
        $zip->close();
        unlink($tmpFile);

        // GitHub zips have a top-level directory — find the module files inside
        $dirs = glob($extractDir . '/*', GLOB_ONLYDIR);
        $sourceDir = !empty($dirs) ? $dirs[0] : $extractDir;

        // Look for the addon module path inside the extracted content
        $moduleSrc = $sourceDir . '/modules/addons/broodle_whmcs_tools';
        if (!is_dir($moduleSrc)) {
            $moduleSrc = $sourceDir;
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
