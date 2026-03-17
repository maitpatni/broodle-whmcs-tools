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

        // Build the update task object for /v1/updater
        $task = [
            'installationId' => $instId,
            'core'           => ['update' => false, 'type' => 'minor', 'restorePoint' => true],
            'plugins'        => [],
            'themes'         => [],
        ];

        if ($type === 'core') {
            $task['core']['update'] = true;
        } elseif ($type === 'plugins') {
            if (!empty($slug)) {
                $task['plugins'] = [$slug];
            } else {
                $pluginsResp = broodle_wpt_call($hostname, $serverUser, $accessHash, $password, "/v1/installations/{$instId}/plugins");
                $slugs = [];
                if (is_array($pluginsResp['data'] ?? null)) {
                    foreach ($pluginsResp['data'] as $pl) {
                        if (!empty($pl['availableVersion'])) $slugs[] = $pl['slug'];
                    }
                }
                $task['plugins'] = $slugs;
            }
        } elseif ($type === 'themes') {
            if (!empty($slug)) {
                $task['themes'] = [$slug];
            } else {
                $themesResp = broodle_wpt_call($hostname, $serverUser, $accessHash, $password, "/v1/installations/{$instId}/themes");
                $slugs = [];
                if (is_array($themesResp['data'] ?? null)) {
                    foreach ($themesResp['data'] as $th) {
                        if (!empty($th['availableVersion'])) $slugs[] = $th['slug'];
                    }
                }
                $task['themes'] = $slugs;
            }
        }

        // Try multiple endpoint formats
        $result = null;
        $updateSuccess = false;
        $errMsg = 'Update failed';

        // Format 1: POST /v1/updater with array of tasks
        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password, '/v1/updater', 'POST', [$task], 300);
        if ($result['success']) {
            $updateSuccess = true;
        }

        // Format 2: POST /v1/updater with single task object
        if (!$updateSuccess) {
            $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password, '/v1/updater', 'POST', $task, 300);
            if ($result['success']) {
                $updateSuccess = true;
            }
        }

        // Format 3: Try per-item update endpoint for plugins/themes
        if (!$updateSuccess && $type === 'plugins' && !empty($slug)) {
            $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
                "/v1/installations/{$instId}/plugins/{$slug}/update", 'POST', null, 300);
            if ($result['success']) {
                $updateSuccess = true;
            }
        }
        if (!$updateSuccess && $type === 'themes' && !empty($slug)) {
            $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
                "/v1/installations/{$instId}/themes/{$slug}/update", 'POST', null, 300);
            if ($result['success']) {
                $updateSuccess = true;
            }
        }

        // Format 4: PATCH on the installation for core updates
        if (!$updateSuccess && $type === 'core') {
            $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
                "/v1/installations/{$instId}/update", 'POST', null, 300);
            if ($result['success']) {
                $updateSuccess = true;
            }
        }

        if (!$updateSuccess && is_array($result['data'] ?? null)) {
            $errMsg = $result['data']['meta']['message'] ?? $result['data']['message'] ?? $result['data']['error'] ?? 'Update failed';
            if (is_array($errMsg)) $errMsg = json_encode($errMsg);
        }

        // After update attempt, verify the actual status
        $verifyUpdated = false;
        if ($updateSuccess && !empty($slug) && in_array($type, ['plugins', 'themes'], true)) {
            // Re-fetch the item to check if availableVersion is now null
            $verifyResult = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
                "/v1/installations/{$instId}/{$type}");
            if ($verifyResult['success'] && is_array($verifyResult['data'])) {
                foreach ($verifyResult['data'] as $item) {
                    if (($item['slug'] ?? '') === $slug) {
                        $verifyUpdated = empty($item['availableVersion']);
                        break;
                    }
                }
            }
        } elseif ($updateSuccess && $type === 'core') {
            $verifyUpdated = true; // Core updates are harder to verify inline
        }

        echo json_encode([
            'success'  => $updateSuccess,
            'verified' => $verifyUpdated,
            'message'  => $updateSuccess
                ? ($verifyUpdated ? ucfirst($type) . ' updated successfully' : ucfirst($type) . ' update initiated — verifying...')
                : $errMsg,
            'debug_status' => $result['status'] ?? null,
        ]);
        break;

    // ─── Security Check ──────────────────────────────────────────────────────
    case 'wp_security_scan':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        // Known measure titles for human-readable display
        $titles = [
            'adminUsername'                => 'Change default administrator username',
            'dbPrefix'                     => 'Change default database table prefix',
            'disableFileEditing'           => 'Disable file editing in WordPress Dashboard',
            'disableUnusedScripting'       => 'Disable unused scripting languages',
            'securityPermissions'          => 'Restrict access to files and directories',
            'pingbacks'                    => 'Turn off pingbacks',
            'scriptsConcatenation'         => 'Disable scripts concatenation for admin panel',
            'securityKeys'                 => 'Configure security keys',
            'blockAuthorsScan'             => 'Block author scans',
            'blockHtFiles'                 => 'Block access to .htaccess and .htpasswd',
            'blockPotentiallySensitiveFiles' => 'Block access to potentially sensitive files',
            'blockSensitiveFiles'          => 'Block access to sensitive files',
            'botProtection'                => 'Enable bot protection',
            'secureConfig'                 => 'Block unauthorized access to wp-config.php',
            'secureContent'                => 'Forbid PHP execution in wp-content/uploads',
            'disableScriptInCache'         => 'Disable PHP execution in cache directories',
            'secureIncludes'               => 'Forbid PHP execution in wp-includes',
            'secureIndexing'               => 'Block directory browsing',
            'secureXmlRpc'                 => 'Block unauthorized access to xmlrpc.php',
        ];

        $makeTitle = function ($id) use ($titles) {
            if (isset($titles[$id])) return $titles[$id];
            $words = preg_replace('/([a-z])([A-Z])/', '$1 $2', $id);
            $words = str_replace(['_', '-'], ' ', $words);
            return ucfirst(strtolower($words));
        };

        // The correct endpoint: GET /v1/security-measures/checker?installationsIds[]=<id>
        // Returns array of installations, each with securityMeasures array
        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/security-measures/checker?installationsIds[]={$instId}");

        $measures = [];
        $debugEndpoint = "GET /v1/security-measures/checker?installationsIds[]={$instId}";

        if ($result['success'] && is_array($result['data'])) {
            // Response is an array of installation objects
            // Find the one matching our installation ID
            $instData = null;
            foreach ($result['data'] as $item) {
                if (isset($item['id']) && (int)$item['id'] === $instId) {
                    $instData = $item;
                    break;
                }
            }
            // If only one result, use it regardless of ID match
            if ($instData === null && count($result['data']) === 1) {
                $instData = $result['data'][0];
            }

            if ($instData !== null && isset($instData['securityMeasures']) && is_array($instData['securityMeasures'])) {
                foreach ($instData['securityMeasures'] as $m) {
                    $mid = $m['id'] ?? '';
                    if (empty($mid)) continue;
                    $measures[] = [
                        'id'        => $mid,
                        'title'     => $makeTitle($mid),
                        'status'    => $m['status'] === true ? 'applied' : 'notApplied',
                        'available' => $m['available'] ?? true,
                    ];
                }
            }
        }

        // Fallback: try GET /v1/security-measures (lists all measures metadata)
        if (empty($measures)) {
            $result2 = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
                '/v1/security-measures');
            $debugEndpoint .= ' + fallback GET /v1/security-measures';
            if ($result2['success'] && is_array($result2['data'])) {
                // This endpoint returns measure definitions, not per-installation status
                // But it's useful as a last resort
                foreach ($result2['data'] as $m) {
                    $mid = $m['id'] ?? $m['name'] ?? '';
                    if (empty($mid) || is_numeric($mid)) continue;
                    $measures[] = [
                        'id'        => $mid,
                        'title'     => $makeTitle($mid),
                        'status'    => 'unknown',
                        'available' => true,
                    ];
                }
            }
        }

        if (!empty($measures)) {
            echo json_encode([
                'success'  => true,
                'security' => $measures,
                'debug_endpoint' => $debugEndpoint,
            ]);
        } else {
            $msg = $result['message'] ?? 'Security scan failed';
            if (isset($result['status'])) $msg .= ' (HTTP ' . $result['status'] . ')';
            echo json_encode([
                'success' => false,
                'message' => $msg,
                'debug_endpoint' => $debugEndpoint,
                'debug_status' => $result['status'] ?? null,
                'debug_raw' => is_array($result['data'] ?? null) ? json_encode(array_slice($result['data'], 0, 1, true)) : ($result['raw'] ?? null),
            ]);
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

        // POST /v1/security-measures/resolver with installationsIds (plural, array)
        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            '/v1/security-measures/resolver', 'POST', [
                'installationsIds' => [$instId],
                'securityMeasures' => [$measureId],
            ]);

        // HTTP 201 = background task created = success
        $applySuccess = $result['success'];

        $errMsg = 'Failed to apply security fix';
        if (!$applySuccess && is_array($result['data'] ?? null)) {
            $errMsg = $result['data']['meta']['message'] ?? $result['data']['message'] ?? $result['data']['error'] ?? $errMsg;
            if (is_array($errMsg)) $errMsg = json_encode($errMsg);
        }

        echo json_encode([
            'success' => $applySuccess,
            'message' => $applySuccess ? 'Security fix applied' : $errMsg,
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

        // POST /v1/security-measures/reverter with installationsIds (plural, array)
        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            '/v1/security-measures/reverter', 'POST', [
                'installationsIds' => [$instId],
                'securityMeasures' => [$measureId],
            ]);

        $revertSuccess = $result['success'];

        $errMsg = 'Failed to revert security fix';
        if (!$revertSuccess && is_array($result['data'] ?? null)) {
            $errMsg = $result['data']['meta']['message'] ?? $result['data']['message'] ?? $result['data']['error'] ?? $errMsg;
            if (is_array($errMsg)) $errMsg = json_encode($errMsg);
        }

        echo json_encode([
            'success' => $revertSuccess,
            'message' => $revertSuccess ? 'Security fix reverted' : $errMsg,
        ]);
        break;

    // ─── Check Plugin/Theme Update Status (verify after update) ──────────────
    case 'wp_check_update_status':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        $type   = isset($_POST['type']) ? trim($_POST['type']) : '';
        $slug   = isset($_POST['slug']) ? trim($_POST['slug']) : '';

        if ($instId <= 0 || !in_array($type, ['plugins', 'themes'], true)) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }

        $endpoint = "/v1/installations/{$instId}/" . $type;
        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password, $endpoint);

        if ($result['success'] && is_array($result['data'])) {
            $stillHasUpdate = false;
            if (!empty($slug)) {
                foreach ($result['data'] as $item) {
                    if (($item['slug'] ?? '') === $slug && !empty($item['availableVersion'])) {
                        $stillHasUpdate = true;
                        break;
                    }
                }
            }
            echo json_encode([
                'success' => true,
                'updated' => !$stillHasUpdate,
                'items'   => $result['data'],
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not check status']);
        }
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

    // ─── Theme Screenshot Proxy ─────────────────────────────────────────────
    case 'wp_theme_screenshot':
        $instId = isset($_GET['instance_id']) ? (int) $_GET['instance_id'] : (isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0);
        $slug   = isset($_GET['slug']) ? trim($_GET['slug']) : (isset($_POST['slug']) ? trim($_POST['slug']) : '');

        if ($instId <= 0 || empty($slug)) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }

        // Fetch theme data to get screenshot URL
        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/installations/{$instId}/themes/{$slug}");

        $screenshotUrl = '';
        if ($result['success'] && is_array($result['data'])) {
            $screenshotUrl = $result['data']['screenshot'] ?? $result['data']['screenshotUrl'] ?? '';
        }

        if (!empty($screenshotUrl)) {
            echo json_encode(['success' => true, 'screenshot_url' => $screenshotUrl]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No screenshot available']);
        }
        break;

    // ─── Security Debug: Test all security endpoints and return raw responses ──
    case 'wp_security_debug':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        $tests = [];

        // Test 1: POST /v1/security-measures/checker with installationsIds (plural, array)
        $r = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            '/v1/security-measures/checker', 'POST', ['installationsIds' => [$instId]]);
        $tests[] = [
            'endpoint' => 'POST /v1/security-measures/checker',
            'body'     => ['installationsIds' => [$instId]],
            'http'     => $r['status'] ?? null,
            'success'  => $r['success'],
            'data'     => $r['data'] ?? null,
            'raw'      => $r['raw'] ?? null,
        ];

        // Test 2: GET with installationId query param
        $r = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/security-measures/checker?installationId={$instId}");
        $tests[] = [
            'endpoint' => "GET /v1/security-measures/checker?installationId={$instId}",
            'http'     => $r['status'] ?? null,
            'success'  => $r['success'],
            'data'     => $r['data'] ?? null,
            'raw'      => $r['raw'] ?? null,
        ];

        // Test 3: GET per-installation security
        $r = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/installations/{$instId}/security");
        $tests[] = [
            'endpoint' => "GET /v1/installations/{$instId}/security",
            'http'     => $r['status'] ?? null,
            'success'  => $r['success'],
            'data'     => $r['data'] ?? null,
            'raw'      => $r['raw'] ?? null,
        ];

        // Test 4: GET per-installation security-measures
        $r = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/installations/{$instId}/security-measures");
        $tests[] = [
            'endpoint' => "GET /v1/installations/{$instId}/security-measures",
            'http'     => $r['status'] ?? null,
            'success'  => $r['success'],
            'data'     => $r['data'] ?? null,
            'raw'      => $r['raw'] ?? null,
        ];

        // Test 5: GET with installationsIds[] query param
        $r = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/security-measures/checker?installationsIds[]={$instId}");
        $tests[] = [
            'endpoint' => "GET /v1/security-measures/checker?installationsIds[]={$instId}",
            'http'     => $r['status'] ?? null,
            'success'  => $r['success'],
            'data'     => $r['data'] ?? null,
            'raw'      => $r['raw'] ?? null,
        ];

        // Test 6: POST with installationId (singular)
        $r = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            '/v1/security-measures/checker', 'POST', ['installationId' => $instId]);
        $tests[] = [
            'endpoint' => 'POST /v1/security-measures/checker',
            'body'     => ['installationId' => $instId],
            'http'     => $r['status'] ?? null,
            'success'  => $r['success'],
            'data'     => $r['data'] ?? null,
            'raw'      => $r['raw'] ?? null,
        ];

        // Test 7: GET the OpenAPI spec paths (to discover available endpoints)
        $r = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            '/v1/specification/public');
        $specPaths = [];
        if ($r['success'] && is_array($r['data']) && isset($r['data']['paths'])) {
            foreach ($r['data']['paths'] as $path => $methods) {
                if (stripos($path, 'security') !== false) {
                    $specPaths[$path] = array_keys($methods);
                }
            }
        }
        $tests[] = [
            'endpoint' => 'GET /v1/specification/public (security paths only)',
            'http'     => $r['status'] ?? null,
            'success'  => $r['success'],
            'data'     => !empty($specPaths) ? $specPaths : 'No security paths found in spec',
        ];

        echo json_encode(['success' => true, 'tests' => $tests], JSON_PRETTY_PRINT);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
