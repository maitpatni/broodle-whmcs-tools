<?php
/**
 * Broodle WHMCS Tools — WordPress Toolkit AJAX Handler
 *
 * Uses the WP Toolkit REST API via WHM session (NOT the cPanel UAPI WordPressInstanceManager).
 * WP Toolkit API endpoint: /cgi/wpt/index.php/v1/
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
$serverUser = $server->username;
$cpUsername  = $service->username;

// Resolve auth credentials (accesshash = API token, or password)
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

// ── WP Toolkit API Helper Functions ──

/**
 * Create a WHM session and call the WP Toolkit REST API.
 *
 * Flow:
 * 1. create_user_session (root, whostmgrd) → get cp_security_token + login URL
 * 2. Activate session → get whostmgrsession cookie
 * 3. Call /cgi/wpt/index.php{$endpoint} with the session cookie
 */
function broodle_wpt_call($hostname, $serverUser, $accessHash, $password, $endpoint, $method = 'GET', $postData = null, $timeout = 90)
{
    // Step 1: Create WHM session
    $sessionUrl = "https://{$hostname}:2087/json-api/create_user_session?api.version=1&user={$serverUser}&service=whostmgrd";

    $ch = curl_init($sessionUrl);
    $headers = [];
    if (!empty($accessHash)) {
        $headers[] = "Authorization: whm {$serverUser}:{$accessHash}";
    }
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ];
    if (!empty($headers)) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    } elseif (!empty($password)) {
        $opts[CURLOPT_USERPWD] = "{$serverUser}:{$password}";
    }
    curl_setopt_array($ch, $opts);
    $sessResp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($sessResp === false || $httpCode !== 200) {
        return ['success' => false, 'message' => 'Failed to create WHM session (HTTP ' . $httpCode . ')'];
    }

    $sessData = json_decode($sessResp, true);
    $cpSessToken = $sessData['data']['cp_security_token'] ?? '';
    $loginUrl    = $sessData['data']['url'] ?? '';

    if (empty($cpSessToken) || empty($loginUrl)) {
        return ['success' => false, 'message' => 'Invalid WHM session response'];
    }

    // Step 2: Activate session and get cookie
    $ch = curl_init($loginUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_NOBODY         => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $loginResp = curl_exec($ch);
    curl_close($ch);

    $sessionCookie = '';
    if (preg_match('/Set-Cookie:\s*whostmgrsession=([^;]+)/i', $loginResp, $m)) {
        $sessionCookie = $m[1];
    }

    if (empty($sessionCookie)) {
        return ['success' => false, 'message' => 'Failed to activate WHM session'];
    }

    // Step 3: Call WP Toolkit API
    $wptUrl = "https://{$hostname}:2087{$cpSessToken}/cgi/wpt/index.php{$endpoint}";
    $ch = curl_init($wptUrl);
    $apiHeaders = [
        "Cookie: whostmgrsession={$sessionCookie}",
        'Content-Type: application/json',
        'Accept-Encoding: gzip, deflate',
    ];
    $apiOpts = [
        CURLOPT_HTTPHEADER     => $apiHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ];

    if ($method === 'POST') {
        $apiOpts[CURLOPT_POST] = true;
        if ($postData !== null) {
            $apiOpts[CURLOPT_POSTFIELDS] = json_encode($postData);
        }
    } elseif ($method === 'PUT') {
        $apiOpts[CURLOPT_CUSTOMREQUEST] = 'PUT';
        if ($postData !== null) {
            $apiOpts[CURLOPT_POSTFIELDS] = json_encode($postData);
        }
    } elseif ($method === 'PATCH') {
        $apiOpts[CURLOPT_CUSTOMREQUEST] = 'PATCH';
        if ($postData !== null) {
            $apiOpts[CURLOPT_POSTFIELDS] = json_encode($postData);
        }
    } elseif ($method === 'DELETE') {
        $apiOpts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
    }

    curl_setopt_array($ch, $apiOpts);
    $apiResp    = curl_exec($ch);
    $apiHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError  = curl_error($ch);
    curl_close($ch);

    if ($apiResp === false) {
        return ['success' => false, 'message' => 'WP Toolkit API request failed: ' . $curlError];
    }

    $decoded = json_decode($apiResp, true);
    return [
        'success'   => ($apiHttpCode >= 200 && $apiHttpCode < 400),
        'status'    => $apiHttpCode,
        'data'      => $decoded,
        'raw'       => is_array($decoded) ? null : $apiResp,
    ];
}

// ── Route actions ──

switch ($action) {

    // ─── List WP Installations (filtered by cPanel username) ─────────────────
    case 'get_wp_instances':
        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password, '/v1/installations', 'GET', null, 120);

        if (!$result['success'] || !is_array($result['data'])) {
            echo json_encode([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to retrieve WordPress installations. WP Toolkit may not be installed on this server.',
            ]);
            break;
        }

        $instances = [];
        foreach ($result['data'] as $inst) {
            $ownerLogin = $inst['owner']['login'] ?? '';
            // Only show installations belonging to this cPanel user
            if ($ownerLogin !== $cpUsername) continue;

            $url = $inst['url'] ?? '';
            $instances[] = [
                'id'              => (int) ($inst['id'] ?? 0),
                'domain'          => $inst['domain']['name'] ?? ($inst['displayTitle'] ?? ''),
                'site_url'        => $url,
                'admin_url'       => rtrim($url, '/') . '/wp-admin/',
                'version'         => $inst['version'] ?? 'Unknown',
                'path'            => $inst['path'] ?? '',
                'owner'           => $ownerLogin,
                'alive'           => $inst['status']['alive'] ?? false,
                'infected'        => $inst['status']['infected'] ?? false,
                'ssl'             => $inst['domain']['ssl']['enabled'] ?? false,
                'pluginUpdates'   => $inst['features']['updates']['amountOfPluginsWithUpdates'] ?? 0,
                'themeUpdates'    => $inst['features']['updates']['amountOfThemesWithUpdates'] ?? 0,
                'availableUpdate' => $inst['features']['updates']['availableVersion'] ?? null,
                'displayTitle'    => $inst['displayTitle'] ?? $url,
            ];
        }

        echo json_encode(['success' => true, 'instances' => $instances]);
        break;

    // ─── Auto-Login (SSO) via WP Toolkit ─────────────────────────────────────
    case 'wp_autologin':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        // Verify ownership first
        $checkResult = broodle_wpt_call($hostname, $serverUser, $accessHash, $password, "/v1/installations/{$instId}");
        if (!$checkResult['success'] || !is_array($checkResult['data'])) {
            echo json_encode(['success' => false, 'message' => 'Installation not found']);
            exit;
        }
        $ownerLogin = $checkResult['data']['owner']['login'] ?? '';
        if ($ownerLogin !== $cpUsername) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        // Generate SSO login link
        $loginResult = broodle_wpt_call($hostname, $serverUser, $accessHash, $password, "/v1/installations/{$instId}/login", 'POST');
        $loginUrl = $loginResult['data']['link'] ?? '';

        if (!empty($loginUrl)) {
            echo json_encode(['success' => true, 'login_url' => $loginUrl]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not generate login link']);
        }
        break;

    // ─── List Plugins ────────────────────────────────────────────────────────
    case 'wp_list_plugins':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password, "/v1/installations/{$instId}/plugins");
        if ($result['success'] && is_array($result['data'])) {
            echo json_encode(['success' => true, 'plugins' => $result['data']]);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to retrieve plugins']);
        }
        break;

    // ─── List Themes ─────────────────────────────────────────────────────────
    case 'wp_list_themes':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password, "/v1/installations/{$instId}/themes");
        if ($result['success'] && is_array($result['data'])) {
            echo json_encode(['success' => true, 'themes' => $result['data']]);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to retrieve themes']);
        }
        break;

    // ─── Toggle Plugin Status ────────────────────────────────────────────────
    case 'wp_toggle_plugin':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        $slug   = isset($_POST['slug']) ? trim($_POST['slug']) : '';
        $activate = isset($_POST['activate']) ? ($_POST['activate'] === '1' || $_POST['activate'] === 'true') : false;

        if ($instId <= 0 || empty($slug)) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/installations/{$instId}/plugins/{$slug}/status", 'PUT', ['status' => $activate]);

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? ('Plugin ' . ($activate ? 'activated' : 'deactivated') . ' successfully') : 'Failed to toggle plugin',
        ]);
        break;

    // ─── Toggle Theme (Activate) ─────────────────────────────────────────────
    case 'wp_toggle_theme':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        $slug   = isset($_POST['slug']) ? trim($_POST['slug']) : '';

        if ($instId <= 0 || empty($slug)) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/installations/{$instId}/themes/{$slug}/status", 'PUT', ['status' => true]);

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Theme activated successfully' : 'Failed to activate theme',
        ]);
        break;

    // ─── Update Plugin/Theme/Core ────────────────────────────────────────────
    case 'wp_update':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        $type   = isset($_POST['type']) ? trim($_POST['type']) : '';
        $slug   = isset($_POST['slug']) ? trim($_POST['slug']) : '';

        if ($instId <= 0 || !in_array($type, ['plugins', 'themes', 'core'], true)) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }

        $postData = [
            'installationId' => $instId,
            'core'           => ['update' => false, 'type' => 'minor', 'restorePoint' => true],
            'plugins'        => [],
            'themes'         => [],
        ];

        if ($type === 'core') {
            $postData['core']['update'] = true;
        } elseif ($type === 'plugins') {
            if (!empty($slug)) {
                $postData['plugins'] = [$slug];
            } else {
                // Update all plugins with available updates
                $pluginsResp = broodle_wpt_call($hostname, $serverUser, $accessHash, $password, "/v1/installations/{$instId}/plugins");
                $slugs = [];
                if (is_array($pluginsResp['data'] ?? null)) {
                    foreach ($pluginsResp['data'] as $pl) {
                        if (!empty($pl['availableVersion'])) $slugs[] = $pl['slug'];
                    }
                }
                $postData['plugins'] = $slugs;
            }
        } elseif ($type === 'themes') {
            if (!empty($slug)) {
                $postData['themes'] = [$slug];
            } else {
                $themesResp = broodle_wpt_call($hostname, $serverUser, $accessHash, $password, "/v1/installations/{$instId}/themes");
                $slugs = [];
                if (is_array($themesResp['data'] ?? null)) {
                    foreach ($themesResp['data'] as $th) {
                        if (!empty($th['availableVersion'])) $slugs[] = $th['slug'];
                    }
                }
                $postData['themes'] = $slugs;
            }
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password, '/v1/updater', 'POST', [$postData], 300);

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? ucfirst($type) . ' updated successfully' : ($result['data']['meta']['message'] ?? $result['data']['message'] ?? 'Update failed'),
        ]);
        break;

    // ─── Security Check ──────────────────────────────────────────────────────
    case 'wp_security_scan':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/security-measures/checker?installationId={$instId}");

        if ($result['success']) {
            $raw = $result['data'];
            $measures = [];

            // Known measure titles for human-readable display
            $titles = [
                'blockAccessToSensitiveFiles'        => 'Block access to sensitive files',
                'blockAccessToHtaccess'              => 'Block access to .htaccess and .htpasswd',
                'blockAccessToXmlrpc'                => 'Block unauthorized access to xmlrpc.php',
                'blockAuthorScans'                   => 'Block author scans',
                'blockDirectoryBrowsing'             => 'Block directory browsing',
                'blockPhpExecutionInWpContent'       => 'Forbid PHP execution in wp-content/uploads',
                'blockPhpExecutionInWpIncludes'      => 'Forbid PHP execution in wp-includes',
                'blockPhpExecutionInCacheDir'        => 'Disable PHP execution in cache directories',
                'changeDefaultAdminUsername'          => 'Change default administrator username',
                'changeDefaultDbPrefix'              => 'Change default database table prefix',
                'configureSecurityKeys'              => 'Configure security keys',
                'disableFileEditing'                 => 'Disable file editing in WordPress Dashboard',
                'disableScriptsConcatenation'        => 'Disable scripts concatenation for admin panel',
                'enableBotProtection'                => 'Enable bot protection',
                'restrictAccessToFilesAndDirectories' => 'Restrict access to files and directories',
                'turnOffPingbacks'                   => 'Turn off pingbacks',
            ];

            // Helper to make a title from camelCase ID
            $makeTitle = function ($id) use ($titles) {
                if (isset($titles[$id])) return $titles[$id];
                // Convert camelCase to words
                $words = preg_replace('/([a-z])([A-Z])/', '$1 $2', $id);
                $words = str_replace(['_', '-'], ' ', $words);
                return ucfirst($words);
            };

            if (is_array($raw)) {
                foreach ($raw as $key => $val) {
                    if (is_array($val)) {
                        // Could be { "id": "measureName", "status": "applied" } (indexed array)
                        // or { "measureName": { "status": "applied" } } (associative)
                        if (isset($val['id']) && isset($val['status'])) {
                            // Indexed array item with id+status
                            $mid = $val['id'];
                            $measures[] = [
                                'id'     => $mid,
                                'title'  => $makeTitle($mid),
                                'status' => $val['status'],
                            ];
                        } elseif (isset($val['status'])) {
                            // Associative: key is the measure ID
                            $measures[] = [
                                'id'     => $key,
                                'title'  => $makeTitle($key),
                                'status' => $val['status'],
                            ];
                        } else {
                            // Unknown structure, try to extract something useful
                            $measures[] = [
                                'id'     => is_string($key) ? $key : ($val['id'] ?? "measure_{$key}"),
                                'title'  => $makeTitle(is_string($key) ? $key : ($val['id'] ?? "measure_{$key}")),
                                'status' => $val['status'] ?? 'unknown',
                            ];
                        }
                    } elseif (is_string($val)) {
                        // Simple key => status format
                        $measures[] = [
                            'id'     => $key,
                            'title'  => $makeTitle($key),
                            'status' => $val,
                        ];
                    }
                }
            }

            echo json_encode(['success' => true, 'security' => $measures]);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Security scan failed']);
        }
        break;

    // ─── Apply Security Fix ──────────────────────────────────────────────────
    case 'wp_security_apply':
        $instId    = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        $measureId = isset($_POST['measure_id']) ? trim($_POST['measure_id']) : '';

        if ($instId <= 0 || empty($measureId)) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            '/v1/security-measures/resolver', 'POST', [
                'installationsIds' => [$instId],
                'securityMeasures' => [$measureId],
            ]);

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Security fix applied' : ($result['data']['meta']['message'] ?? 'Failed to apply security fix'),
        ]);
        break;

    // ─── Revert Security Fix ─────────────────────────────────────────────────
    case 'wp_security_revert':
        $instId    = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        $measureId = isset($_POST['measure_id']) ? trim($_POST['measure_id']) : '';

        if ($instId <= 0 || empty($measureId)) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            '/v1/security-measures/reverter', 'POST', [
                'installationsIds' => [$instId],
                'securityMeasures' => [$measureId],
            ]);

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Security fix reverted' : 'Failed to revert security fix',
        ]);
        break;

    // ─── Maintenance Mode Toggle ─────────────────────────────────────────────
    case 'wp_maintenance':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        $enable = isset($_POST['enable']) ? ($_POST['enable'] === '1' || $_POST['enable'] === 'true') : false;

        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/installations/{$instId}/features/maintenance/status", 'PUT', ['status' => $enable]);

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? ('Maintenance mode ' . ($enable ? 'enabled' : 'disabled')) : 'Failed to toggle maintenance mode',
        ]);
        break;

    // ─── Debug Mode Toggle ───────────────────────────────────────────────────
    case 'wp_debug_toggle':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        $enable = isset($_POST['enable']) ? ($_POST['enable'] === '1' || $_POST['enable'] === 'true') : false;

        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/installations/{$instId}/features/debug/status", 'PUT', ['status' => $enable]);

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? ('Debug mode ' . ($enable ? 'enabled' : 'disabled')) : 'Failed to toggle debug mode',
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
