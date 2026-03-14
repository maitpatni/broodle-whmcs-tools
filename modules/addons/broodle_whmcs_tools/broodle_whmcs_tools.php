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

define('BROODLE_TOOLS_VERSION', '1.1.0');
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
    $autoUpdateEnabled = !empty($settings['auto_update_enabled']) && $settings['auto_update_enabled'] === '1';

    $html = '
    <style>
        .broodle-admin-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .broodle-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 25px 30px; border-radius: 10px; margin-bottom: 25px; }
        .broodle-header h2 { margin: 0 0 5px; font-size: 22px; font-weight: 600; }
        .broodle-header p { margin: 0; opacity: 0.9; font-size: 14px; }
        .broodle-card { background: #fff; border: 1px solid #e3e6f0; border-radius: 8px; padding: 25px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .broodle-card h3 { margin: 0 0 20px; font-size: 17px; font-weight: 600; color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .broodle-tweak-row { display: flex; align-items: center; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #f0f0f0; }
        .broodle-tweak-row:last-child { border-bottom: none; }
        .broodle-tweak-info h4 { margin: 0 0 4px; font-size: 15px; color: #333; }
        .broodle-tweak-info p { margin: 0; font-size: 13px; color: #777; }
        .broodle-toggle { position: relative; width: 50px; height: 26px; }
        .broodle-toggle input { opacity: 0; width: 0; height: 0; }
        .broodle-toggle .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #ccc; border-radius: 26px; transition: 0.3s; }
        .broodle-toggle .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.3s; }
        .broodle-toggle input:checked + .slider { background: #667eea; }
        .broodle-toggle input:checked + .slider:before { transform: translateX(24px); }
        .broodle-btn { display: inline-block; padding: 10px 24px; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; color: #fff; transition: 0.2s; }
        .broodle-btn-primary { background: #667eea; }
        .broodle-btn-primary:hover { background: #5a6fd6; color: #fff; text-decoration: none; }
        .broodle-btn-secondary { background: #6c757d; }
        .broodle-btn-secondary:hover { background: #5a6268; color: #fff; text-decoration: none; }
        .broodle-btn-success { background: #28a745; }
        .broodle-btn-success:hover { background: #218838; color: #fff; text-decoration: none; }
        .broodle-version-info { display: flex; align-items: center; gap: 15px; margin-top: 10px; }
        .broodle-footer { text-align: center; padding: 15px; color: #999; font-size: 12px; }
        .broodle-footer a { color: #667eea; text-decoration: none; }
    </style>

    <div class="broodle-admin-wrap">
        <div class="broodle-header">
            <h2>🛠 Broodle WHMCS Tools</h2>
            <p>Version ' . BROODLE_TOOLS_VERSION . ' &mdash; Manage your WHMCS tweaks and enhancements</p>
        </div>

        <form method="post" action="' . $moduleLink . '&action=save_settings">
            <div class="broodle-card">
                <h3>⚡ Tweaks</h3>

                <div class="broodle-tweak-row">
                    <div class="broodle-tweak-info">
                        <h4>Nameservers Tab</h4>
                        <p>Adds a "Nameservers" tab on the cPanel product details page showing the service\'s nameservers in a clean UI.</p>
                    </div>
                    <label class="broodle-toggle">
                        <input type="checkbox" name="tweak_nameservers_tab" value="1" ' . ($nameserversEnabled ? 'checked' : '') . '>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="broodle-card">
                <h3>🔄 Updates</h3>

                <div class="broodle-tweak-row">
                    <div class="broodle-tweak-info">
                        <h4>Auto Update</h4>
                        <p>Automatically check for and notify about new versions from the GitHub repository.</p>
                    </div>
                    <label class="broodle-toggle">
                        <input type="checkbox" name="auto_update_enabled" value="1" ' . ($autoUpdateEnabled ? 'checked' : '') . '>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="broodle-version-info">
                    <a href="' . $moduleLink . '&action=check_update" class="broodle-btn broodle-btn-secondary">Check for Update</a>
                    <span style="color:#888; font-size:13px;">Current: v' . BROODLE_TOOLS_VERSION . '</span>
                </div>
            </div>

            <button type="submit" class="broodle-btn broodle-btn-primary">Save Settings</button>
        </form>

        <div class="broodle-footer">
            <p>Broodle WHMCS Tools &copy; ' . date('Y') . ' <a href="https://broodle.host" target="_blank">Broodle</a> &mdash;
            <a href="https://github.com/' . BROODLE_TOOLS_GITHUB_REPO . '" target="_blank">GitHub</a></p>
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
