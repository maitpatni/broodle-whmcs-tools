<?php
/**
 * Broodle WHMCS Tools — WordPress Toolkit AJAX Handler
 *
 * Handles all WordPress management operations via cPanel UAPI and WP Toolkit REST API.
 *
 * @package    BroodleWHMCSTools
 * @author     Broodle
 * @link       https://broodle.host
 */

define('CLIENTAREA', true);

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/clientfunctions.php';

use WHMCS\Database\Capsule;

header('Content-Type: application/json');

// Verify logged-in client
$ca = new WHMCS\ClientArea();
$ca->initPage();
if (!$ca->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
$clientId = (int) $ca->getUserID();

$action    = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
$serviceId = isset($_POST['service_id']) ? (int) $_POST['service_id'] : (isset($_GET['service_id']) ? (int) $_GET['service_id'] : 0);

if (!$serviceId || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Verify the service belongs to this client
$service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
if (!$service || (int) $service->userid !== $clientId) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Check WP toolkit tweak is enabled
$enabled = Capsule::table('mod_broodle_tools_settings')
    ->where('setting_key', 'tweak_wordpress_toolkit')
    ->value('setting_value');
if ($enabled !== '1') {
    echo json_encode(['success' => false, 'message' => 'WordPress Toolkit feature is disabled']);
    exit;
}

// Get server info
$server = Capsule::table('tblservers')->where('id', $service->server)->first();
if (!$server) {
    echo json_encode(['success' => false, 'message' => 'Server not found']);
    exit;
}

$hostname   = $server->hostname;
$port       = !empty($server->port) ? (int) $server->port : 2087;
$serverUser = $server->username;
$secure     = !empty($server->secure) && ($server->secure === 'on' || $server->secure === '1' || $server->secure === 1);
$protocol   = $secure ? 'https' : 'http';
$cpUsername  = $service->username;

// Resolve auth credentials
$accessHash = '';
$password   = '';
if (!empty($server->accesshash)) {
    $raw = trim($server->accesshash);
    if (preg_match('/^[A-Za-z0-9]{10,64}$/', $raw)) {
        $accessHash = $raw;
    } else {
        $accessHash = trim(decrypt($raw));
        if (empty($accessHash) || !preg_match('/^[A-Za-z0-9]{10,64}$/', $accessHash)) {
            $accessHash = '';
        }
    }
}
if (empty($accessHash) && !empty($server->password)) {
    $password = trim(decrypt($server->password));
}
if (empty($accessHash) && empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Server credentials unavailable']);
    exit;
}

/**
 * Make a WHM API call (GET or POST).
 */
function broodle_wp_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url, $method = 'GET', $postData = null)
{
    $headers = [];
    if (!empty($accessHash)) {
        $headers[] = "Authorization: whm {$serverUser}:{$accessHash}";
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($postData !== null) {
            if (is_array($postData)) {
                $postData = http_build_query($postData);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    } elseif (!empty($password)) {
        curl_setopt($ch, CURLOPT_USERPWD, "{$serverUser}:{$password}");
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => $response];
}

/**
 * Call cPanel UAPI via WHM proxy.
 */
function broodle_wp_uapi_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, $module, $func, $params = [])
{
    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=3"
         . "&cpanel_jsonapi_module=" . urlencode($module)
         . "&cpanel_jsonapi_func=" . urlencode($func);

    foreach ($params as $k => $v) {
        $url .= "&" . urlencode($k) . "=" . urlencode($v);
    }

    return broodle_wp_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
}

/**
 * Execute WP-CLI command via cPanel UAPI Terminal (Shell::exec) or via WHM API.
 */
function broodle_wp_exec_wpcli($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, $wpPath, $command)
{
    // Use WHM API to execute command as the cPanel user
    $fullCmd = "cd " . escapeshellarg($wpPath) . " && wp " . $command . " --format=json 2>/dev/null";

    $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
         . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
         . "&cpanel_jsonapi_apiversion=3"
         . "&cpanel_jsonapi_module=Shell"
         . "&cpanel_jsonapi_func=exec"
         . "&command=" . urlencode($fullCmd);

    return broodle_wp_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
}

// ── Route actions ──

switch ($action) {

    case 'get_wp_instances':
        // Use cPanel UAPI WordPressInstanceManager::get_instances
        $r = broodle_wp_uapi_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, 'WordPressInstanceManager', 'get_instances');

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $instances = [];

            $data = $json['result']['data'] ?? [];
            $rawInstances = $data['instances'] ?? [];

            foreach ($rawInstances as $inst) {
                $siteUrl = $inst['site_url'] ?? $inst['domain'] ?? '';
                $adminUrl = $inst['admin_url'] ?? '';
                if (empty($adminUrl) && !empty($siteUrl)) {
                    $adminUrl = rtrim($siteUrl, '/') . '/wp-login.php';
                }

                $instances[] = [
                    'id'              => $inst['id'] ?? '',
                    'domain'          => $inst['domain'] ?? '',
                    'site_url'        => $siteUrl,
                    'admin_url'       => $adminUrl,
                    'admin_username'  => $inst['admin_username'] ?? '',
                    'full_path'       => $inst['full_path'] ?? '',
                    'rel_path'        => trim($inst['rel_path'] ?? ''),
                    'current_version' => $inst['current_version'] ?? ($inst['initial_install_version'] ?? 'Unknown'),
                    'addon_type'      => $inst['addon_type'] ?? 'unknown',
                    'db_name'         => $inst['db_name'] ?? '',
                    'created_on'      => $inst['created_on'] ?? 0,
                ];
            }

            echo json_encode(['success' => true, 'instances' => $instances]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to retrieve WordPress installations. The server may not have WordPress Manager installed.']);
        }
        break;

    case 'get_wp_details':
        // Get detailed info for a specific WP instance
        $instanceId = isset($_POST['instance_id']) ? trim($_POST['instance_id']) : '';
        if (empty($instanceId)) {
            echo json_encode(['success' => false, 'message' => 'Missing instance ID']);
            exit;
        }

        $r = broodle_wp_uapi_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, 'WordPressInstanceManager', 'get_instance_by_id', ['id' => $instanceId]);

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $data = $json['result']['data'] ?? [];

            if (!empty($data)) {
                echo json_encode(['success' => true, 'instance' => $data]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Instance not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to get instance details']);
        }
        break;

    case 'wp_autologin':
        // Create a cPanel session and redirect to WP admin
        $instanceId = isset($_POST['instance_id']) ? trim($_POST['instance_id']) : '';
        if (empty($instanceId)) {
            echo json_encode(['success' => false, 'message' => 'Missing instance ID']);
            exit;
        }

        // First get the instance details to find the admin URL
        $r = broodle_wp_uapi_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, 'WordPressInstanceManager', 'get_instance_by_id', ['id' => $instanceId]);

        $adminUrl = '';
        $siteUrl = '';
        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $data = $json['result']['data'] ?? [];
            $siteUrl = $data['site_url'] ?? $data['domain'] ?? '';
            $adminUrl = $data['admin_url'] ?? '';
        }

        // Create a cPanel user session
        $sessionUrl = "{$protocol}://{$hostname}:{$port}/json-api/create_user_session"
                    . "?api.version=1"
                    . "&user=" . urlencode($cpUsername)
                    . "&service=cpaneld";

        $sr = broodle_wp_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $sessionUrl);

        if ($sr['code'] === 200 && $sr['body']) {
            $sJson = json_decode($sr['body'], true);
            $cpanelUrl = $sJson['data']['url'] ?? '';

            if (!empty($cpanelUrl)) {
                // Build the WP Toolkit SSO URL through cPanel
                // The cPanel session URL gives us access, we redirect to the WP admin
                echo json_encode([
                    'success'    => true,
                    'cpanel_url' => $cpanelUrl,
                    'admin_url'  => $adminUrl,
                    'site_url'   => $siteUrl,
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Could not create cPanel session']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create login session']);
        }
        break;

    case 'wp_list_plugins':
        $wpPath = isset($_POST['wp_path']) ? trim($_POST['wp_path']) : '';
        if (empty($wpPath)) {
            echo json_encode(['success' => false, 'message' => 'Missing WordPress path']);
            exit;
        }

        $r = broodle_wp_exec_wpcli($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, $wpPath, 'plugin list');

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            // UAPI Shell::exec returns result.data with output
            $output = '';
            if (isset($json['result']['data'])) {
                $output = $json['result']['data'];
            } elseif (isset($json['cpanelresult']['data'])) {
                $output = $json['cpanelresult']['data'];
            }

            // Try to parse the output as JSON (wp-cli --format=json)
            if (is_string($output)) {
                $plugins = json_decode($output, true);
                if (is_array($plugins)) {
                    echo json_encode(['success' => true, 'plugins' => $plugins]);
                    break;
                }
            }

            // Fallback: try to parse from nested structure
            if (is_array($output) && isset($output['output'])) {
                $plugins = json_decode($output['output'], true);
                if (is_array($plugins)) {
                    echo json_encode(['success' => true, 'plugins' => $plugins]);
                    break;
                }
            }

            echo json_encode(['success' => false, 'message' => 'Could not parse plugin list. WP-CLI may not be available on this server.', 'raw' => $output]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to retrieve plugins']);
        }
        break;

    case 'wp_list_themes':
        $wpPath = isset($_POST['wp_path']) ? trim($_POST['wp_path']) : '';
        if (empty($wpPath)) {
            echo json_encode(['success' => false, 'message' => 'Missing WordPress path']);
            exit;
        }

        $r = broodle_wp_exec_wpcli($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, $wpPath, 'theme list');

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $output = '';
            if (isset($json['result']['data'])) {
                $output = $json['result']['data'];
            } elseif (isset($json['cpanelresult']['data'])) {
                $output = $json['cpanelresult']['data'];
            }

            if (is_string($output)) {
                $themes = json_decode($output, true);
                if (is_array($themes)) {
                    echo json_encode(['success' => true, 'themes' => $themes]);
                    break;
                }
            }

            if (is_array($output) && isset($output['output'])) {
                $themes = json_decode($output['output'], true);
                if (is_array($themes)) {
                    echo json_encode(['success' => true, 'themes' => $themes]);
                    break;
                }
            }

            echo json_encode(['success' => false, 'message' => 'Could not parse theme list. WP-CLI may not be available.', 'raw' => $output]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to retrieve themes']);
        }
        break;

    case 'wp_toggle_plugin':
        $wpPath = isset($_POST['wp_path']) ? trim($_POST['wp_path']) : '';
        $pluginSlug = isset($_POST['plugin']) ? trim($_POST['plugin']) : '';
        $pluginAction = isset($_POST['plugin_action']) ? trim($_POST['plugin_action']) : '';

        if (empty($wpPath) || empty($pluginSlug) || !in_array($pluginAction, ['activate', 'deactivate'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }

        $cmd = "plugin {$pluginAction} " . escapeshellarg($pluginSlug);
        $r = broodle_wp_exec_wpcli($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, $wpPath, $cmd);

        if ($r['code'] === 200) {
            echo json_encode(['success' => true, 'message' => 'Plugin ' . $pluginAction . 'd successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to ' . $pluginAction . ' plugin']);
        }
        break;

    case 'wp_update_plugin':
        $wpPath = isset($_POST['wp_path']) ? trim($_POST['wp_path']) : '';
        $pluginSlug = isset($_POST['plugin']) ? trim($_POST['plugin']) : '';

        if (empty($wpPath) || empty($pluginSlug)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }

        $cmd = "plugin update " . escapeshellarg($pluginSlug);
        $r = broodle_wp_exec_wpcli($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, $wpPath, $cmd);

        if ($r['code'] === 200) {
            echo json_encode(['success' => true, 'message' => 'Plugin updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update plugin']);
        }
        break;

    case 'wp_delete_plugin':
        $wpPath = isset($_POST['wp_path']) ? trim($_POST['wp_path']) : '';
        $pluginSlug = isset($_POST['plugin']) ? trim($_POST['plugin']) : '';

        if (empty($wpPath) || empty($pluginSlug)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }

        $cmd = "plugin deactivate " . escapeshellarg($pluginSlug) . " && wp plugin delete " . escapeshellarg($pluginSlug);
        // Use raw command for chained operations
        $fullCmd = "cd " . escapeshellarg($wpPath) . " && wp plugin deactivate " . escapeshellarg($pluginSlug) . " 2>/dev/null; wp plugin delete " . escapeshellarg($pluginSlug) . " 2>/dev/null";

        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=Shell"
             . "&cpanel_jsonapi_func=exec"
             . "&command=" . urlencode($fullCmd);

        $r = broodle_wp_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);

        if ($r['code'] === 200) {
            echo json_encode(['success' => true, 'message' => 'Plugin deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete plugin']);
        }
        break;

    case 'wp_toggle_theme':
        $wpPath = isset($_POST['wp_path']) ? trim($_POST['wp_path']) : '';
        $themeSlug = isset($_POST['theme']) ? trim($_POST['theme']) : '';

        if (empty($wpPath) || empty($themeSlug)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }

        $cmd = "theme activate " . escapeshellarg($themeSlug);
        $r = broodle_wp_exec_wpcli($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, $wpPath, $cmd);

        if ($r['code'] === 200) {
            echo json_encode(['success' => true, 'message' => 'Theme activated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to activate theme']);
        }
        break;

    case 'wp_update_theme':
        $wpPath = isset($_POST['wp_path']) ? trim($_POST['wp_path']) : '';
        $themeSlug = isset($_POST['theme']) ? trim($_POST['theme']) : '';

        if (empty($wpPath) || empty($themeSlug)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }

        $cmd = "theme update " . escapeshellarg($themeSlug);
        $r = broodle_wp_exec_wpcli($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, $wpPath, $cmd);

        if ($r['code'] === 200) {
            echo json_encode(['success' => true, 'message' => 'Theme updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update theme']);
        }
        break;

    case 'wp_delete_theme':
        $wpPath = isset($_POST['wp_path']) ? trim($_POST['wp_path']) : '';
        $themeSlug = isset($_POST['theme']) ? trim($_POST['theme']) : '';

        if (empty($wpPath) || empty($themeSlug)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }

        $cmd = "theme delete " . escapeshellarg($themeSlug);
        $r = broodle_wp_exec_wpcli($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, $wpPath, $cmd);

        if ($r['code'] === 200) {
            echo json_encode(['success' => true, 'message' => 'Theme deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete theme']);
        }
        break;

    case 'wp_security_scan':
        $wpPath = isset($_POST['wp_path']) ? trim($_POST['wp_path']) : '';
        if (empty($wpPath)) {
            echo json_encode(['success' => false, 'message' => 'Missing WordPress path']);
            exit;
        }

        $checks = [];

        // Check WP version
        $r1 = broodle_wp_exec_wpcli($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, $wpPath, 'core version');
        $wpVersion = 'Unknown';
        if ($r1['code'] === 200 && $r1['body']) {
            $j = json_decode($r1['body'], true);
            $out = $j['result']['data'] ?? '';
            if (is_array($out) && isset($out['output'])) $out = $out['output'];
            if (is_string($out)) $wpVersion = trim(str_replace('"', '', $out));
        }

        // Check for core updates
        $r2 = broodle_wp_exec_wpcli($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, $wpPath, 'core check-update');
        $coreUpdateAvailable = false;
        if ($r2['code'] === 200 && $r2['body']) {
            $j = json_decode($r2['body'], true);
            $out = $j['result']['data'] ?? '';
            if (is_array($out) && isset($out['output'])) $out = $out['output'];
            if (is_string($out) && !empty(trim($out)) && $out !== '[]') {
                $coreUpdateAvailable = true;
            }
        }

        // Check file permissions on wp-config.php
        $permCmd = "stat -c '%a' " . escapeshellarg($wpPath . '/wp-config.php') . " 2>/dev/null";
        $url = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
             . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
             . "&cpanel_jsonapi_apiversion=3"
             . "&cpanel_jsonapi_module=Shell"
             . "&cpanel_jsonapi_func=exec"
             . "&command=" . urlencode($permCmd);
        $r3 = broodle_wp_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url);
        $configPerms = 'Unknown';
        $configPermsOk = false;
        if ($r3['code'] === 200 && $r3['body']) {
            $j = json_decode($r3['body'], true);
            $out = $j['result']['data'] ?? '';
            if (is_array($out) && isset($out['output'])) $out = $out['output'];
            if (is_string($out)) {
                $configPerms = trim($out);
                $configPermsOk = in_array($configPerms, ['400', '440', '444', '600', '640', '644']);
            }
        }

        // Check if debug mode is off
        $debugCmd = "grep -c \"define.*WP_DEBUG.*true\" " . escapeshellarg($wpPath . '/wp-config.php') . " 2>/dev/null || echo 0";
        $url4 = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
              . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
              . "&cpanel_jsonapi_apiversion=3"
              . "&cpanel_jsonapi_module=Shell"
              . "&cpanel_jsonapi_func=exec"
              . "&command=" . urlencode($debugCmd);
        $r4 = broodle_wp_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url4);
        $debugOff = true;
        if ($r4['code'] === 200 && $r4['body']) {
            $j = json_decode($r4['body'], true);
            $out = $j['result']['data'] ?? '';
            if (is_array($out) && isset($out['output'])) $out = $out['output'];
            if (is_string($out) && (int) trim($out) > 0) {
                $debugOff = false;
            }
        }

        // Check if file editor is disabled
        $editorCmd = "grep -c \"DISALLOW_FILE_EDIT.*true\" " . escapeshellarg($wpPath . '/wp-config.php') . " 2>/dev/null || echo 0";
        $url5 = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
              . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
              . "&cpanel_jsonapi_apiversion=3"
              . "&cpanel_jsonapi_module=Shell"
              . "&cpanel_jsonapi_func=exec"
              . "&command=" . urlencode($editorCmd);
        $r5 = broodle_wp_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url5);
        $fileEditorDisabled = false;
        if ($r5['code'] === 200 && $r5['body']) {
            $j = json_decode($r5['body'], true);
            $out = $j['result']['data'] ?? '';
            if (is_array($out) && isset($out['output'])) $out = $out['output'];
            if (is_string($out) && (int) trim($out) > 0) {
                $fileEditorDisabled = true;
            }
        }

        // Check DB prefix
        $prefixCmd = "grep \"table_prefix\" " . escapeshellarg($wpPath . '/wp-config.php') . " 2>/dev/null | head -1";
        $url6 = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
              . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
              . "&cpanel_jsonapi_apiversion=3"
              . "&cpanel_jsonapi_module=Shell"
              . "&cpanel_jsonapi_func=exec"
              . "&command=" . urlencode($prefixCmd);
        $r6 = broodle_wp_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url6);
        $dbPrefix = 'wp_';
        $prefixChanged = false;
        if ($r6['code'] === 200 && $r6['body']) {
            $j = json_decode($r6['body'], true);
            $out = $j['result']['data'] ?? '';
            if (is_array($out) && isset($out['output'])) $out = $out['output'];
            if (is_string($out) && preg_match("/table_prefix\s*=\s*['\"]([^'\"]+)['\"]/", $out, $m)) {
                $dbPrefix = $m[1];
                $prefixChanged = ($dbPrefix !== 'wp_');
            }
        }

        // Check .htaccess for directory listing protection
        $htaccessCmd = "grep -c 'Options.*-Indexes' " . escapeshellarg($wpPath . '/.htaccess') . " 2>/dev/null || echo 0";
        $url7 = "{$protocol}://{$hostname}:{$port}/json-api/cpanel"
              . "?cpanel_jsonapi_user=" . urlencode($cpUsername)
              . "&cpanel_jsonapi_apiversion=3"
              . "&cpanel_jsonapi_module=Shell"
              . "&cpanel_jsonapi_func=exec"
              . "&command=" . urlencode($htaccessCmd);
        $r7 = broodle_wp_whm_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $url7);
        $dirListingProtected = false;
        if ($r7['code'] === 200 && $r7['body']) {
            $j = json_decode($r7['body'], true);
            $out = $j['result']['data'] ?? '';
            if (is_array($out) && isset($out['output'])) $out = $out['output'];
            if (is_string($out) && (int) trim($out) > 0) {
                $dirListingProtected = true;
            }
        }

        // Count plugins needing updates
        $r8 = broodle_wp_exec_wpcli($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, $wpPath, 'plugin list --update=available');
        $pluginUpdates = 0;
        if ($r8['code'] === 200 && $r8['body']) {
            $j = json_decode($r8['body'], true);
            $out = $j['result']['data'] ?? '';
            if (is_array($out) && isset($out['output'])) $out = $out['output'];
            if (is_string($out)) {
                $arr = json_decode($out, true);
                if (is_array($arr)) $pluginUpdates = count($arr);
            }
        }

        echo json_encode([
            'success' => true,
            'security' => [
                ['label' => 'WordPress Version', 'value' => $wpVersion, 'status' => !$coreUpdateAvailable ? 'ok' : 'warning', 'detail' => $coreUpdateAvailable ? 'Update available' : 'Up to date'],
                ['label' => 'wp-config.php Permissions', 'value' => $configPerms, 'status' => $configPermsOk ? 'ok' : 'warning', 'detail' => $configPermsOk ? 'Secure' : 'Consider restricting to 644 or lower'],
                ['label' => 'Debug Mode', 'value' => $debugOff ? 'Disabled' : 'Enabled', 'status' => $debugOff ? 'ok' : 'danger', 'detail' => $debugOff ? 'Good — debug is off' : 'WP_DEBUG is enabled in production'],
                ['label' => 'File Editor', 'value' => $fileEditorDisabled ? 'Disabled' : 'Enabled', 'status' => $fileEditorDisabled ? 'ok' : 'warning', 'detail' => $fileEditorDisabled ? 'DISALLOW_FILE_EDIT is set' : 'Consider disabling the built-in file editor'],
                ['label' => 'Database Prefix', 'value' => $dbPrefix, 'status' => $prefixChanged ? 'ok' : 'warning', 'detail' => $prefixChanged ? 'Custom prefix in use' : 'Default wp_ prefix — consider changing'],
                ['label' => 'Directory Listing', 'value' => $dirListingProtected ? 'Protected' : 'Not Protected', 'status' => $dirListingProtected ? 'ok' : 'warning', 'detail' => $dirListingProtected ? 'Options -Indexes is set' : 'Add "Options -Indexes" to .htaccess'],
                ['label' => 'Plugin Updates', 'value' => $pluginUpdates . ' pending', 'status' => $pluginUpdates === 0 ? 'ok' : 'warning', 'detail' => $pluginUpdates === 0 ? 'All plugins up to date' : $pluginUpdates . ' plugin(s) need updating'],
            ],
        ]);
        break;

    case 'wp_update_core':
        $wpPath = isset($_POST['wp_path']) ? trim($_POST['wp_path']) : '';
        if (empty($wpPath)) {
            echo json_encode(['success' => false, 'message' => 'Missing WordPress path']);
            exit;
        }

        $cmd = "core update";
        $r = broodle_wp_exec_wpcli($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, $wpPath, $cmd);

        if ($r['code'] === 200) {
            echo json_encode(['success' => true, 'message' => 'WordPress core updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update WordPress core']);
        }
        break;

    case 'wp_scan':
        // Trigger a scan for new WP installations
        $r = broodle_wp_uapi_call($protocol, $hostname, $port, $serverUser, $accessHash, $password, $cpUsername, 'WordPressInstanceManager', 'start_scan');

        if ($r['code'] === 200 && $r['body']) {
            $json = json_decode($r['body'], true);
            $status = $json['result']['status'] ?? 0;
            if ($status == 1) {
                echo json_encode(['success' => true, 'message' => 'Scan started. Refresh in a few seconds to see new installations.']);
            } else {
                $err = $json['result']['errors'][0] ?? 'Scan failed';
                echo json_encode(['success' => false, 'message' => $err]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to start scan']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
