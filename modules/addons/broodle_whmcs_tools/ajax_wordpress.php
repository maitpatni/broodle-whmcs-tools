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
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Verify logged-in client
$ca = new WHMCS\ClientArea();
$ca->initPage();
if (!$ca->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
$clientId = (int) $ca->getUserID();

$action    = isset($_POST['action']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['action']) : (isset($_GET['action']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['action']) : '');
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
                'maintenance'     => $inst['features']['maintenance']['status'] ?? false,
                'debug'           => $inst['features']['debug']['status'] ?? false,
                'securityStatus'  => $inst['features']['security']['status'] ?? null,
                'vulnerability'   => $inst['features']['vulnerability']['status'] ?? null,
                'hotlinkProtection' => $inst['features']['hotlinkProtection']['status'] ?? false,
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

        // POST /v1/updater with array of task objects (confirmed working format)
        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password, '/v1/updater', 'POST', [$task], 300);
        $updateSuccess = $result['success'];
        $errMsg = 'Update failed';

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
            ]);
        } else {
            $msg = $result['message'] ?? 'Security scan failed';
            if (isset($result['status'])) $msg .= ' (HTTP ' . $result['status'] . ')';
            echo json_encode([
                'success' => false,
                'message' => $msg,
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

    // ─── Get Debug Settings ─────────────────────────────────────────────────
    case 'wp_get_debug_settings':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/installations/{$instId}/features/debug/settings");

        if ($result['success'] && is_array($result['data'])) {
            echo json_encode(['success' => true, 'settings' => $result['data']]);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to get debug settings']);
        }
        break;

    // ─── Get Maintenance Settings ────────────────────────────────────────────
    case 'wp_get_maintenance_settings':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/installations/{$instId}/features/maintenance/settings");

        if ($result['success'] && is_array($result['data'])) {
            echo json_encode(['success' => true, 'settings' => $result['data']]);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to get maintenance settings']);
        }
        break;

    // ─── Get Account Info ────────────────────────────────────────────────────
    case 'wp_get_account':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/installations/{$instId}/account");

        if ($result['success'] && is_array($result['data'])) {
            echo json_encode(['success' => true, 'account' => $result['data']]);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to get account info']);
        }
        break;

    // ─── Get Vulnerabilities ─────────────────────────────────────────────────
    case 'wp_get_vulnerabilities':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/vulnerabilities-checker?installationsIds[]={$instId}");

        if ($result['success'] && is_array($result['data'])) {
            echo json_encode(['success' => true, 'vulnerabilities' => $result['data']]);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to check vulnerabilities']);
        }
        break;

    // ─── Toggle Hotlink Protection ───────────────────────────────────────────
    case 'wp_toggle_hotlink':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        $enable = isset($_POST['enable']) ? ($_POST['enable'] === '1' || $_POST['enable'] === 'true') : false;

        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/installations/{$instId}/features/hotlink-protection/status", 'PUT', ['status' => $enable]);

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? ('Hotlink protection ' . ($enable ? 'enabled' : 'disabled')) : 'Failed to toggle hotlink protection',
        ]);
        break;

    // ─── Toggle Indexing ─────────────────────────────────────────────────────
    case 'wp_toggle_indexing':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        $enable = isset($_POST['enable']) ? ($_POST['enable'] === '1' || $_POST['enable'] === 'true') : false;

        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/installations/{$instId}/features/indexing/status", 'PUT', ['status' => $enable]);

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? ('Search engine indexing ' . ($enable ? 'enabled' : 'disabled')) : 'Failed to toggle indexing',
        ]);
        break;

    // ─── Get Auto-Update Settings ────────────────────────────────────────────
    case 'wp_get_autoupdate_settings':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/installations/{$instId}/features/updates/settings");

        if ($result['success'] && is_array($result['data'])) {
            echo json_encode(['success' => true, 'settings' => $result['data']]);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to get auto-update settings']);
        }
        break;

    // ─── Trigger Update Check ────────────────────────────────────────────────
    case 'wp_check_for_updates':
        $instId = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        if ($instId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing installation ID']);
            exit;
        }

        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            '/v1/updates-checker', 'POST', ['installationsIds' => [$instId]]);

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Update check initiated' : 'Failed to trigger update check',
        ]);
        break;

    // ─── Site Screenshot Proxy (cached) ─────────────────────────────────────
    case 'wp_site_screenshot':
        $url = isset($_POST['url']) ? trim($_POST['url']) : '';
        if (empty($url)) {
            echo json_encode(['success' => false, 'message' => 'Missing URL']);
            exit;
        }

        // Cache directory
        $cacheDir = __DIR__ . '/cache/screenshots';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $cacheKey = md5($url);
        $cachePath = $cacheDir . '/' . $cacheKey . '.jpg';
        $cacheMaxAge = 86400; // 24 hours

        // Return cached if fresh
        if (file_exists($cachePath) && (time() - filemtime($cachePath)) < $cacheMaxAge) {
            $base64 = base64_encode(file_get_contents($cachePath));
            echo json_encode(['success' => true, 'image' => 'data:image/jpeg;base64,' . $base64]);
            break;
        }

        // Try multiple screenshot services
        $screenshotUrl = '';
        $imageData = false;

        // Service 1: Google PageSpeed Insights screenshot (works through Cloudflare)
        $googleUrl = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=' . urlencode($url) . '&category=PERFORMANCE&strategy=DESKTOP&fields=lighthouseResult.audits.final-screenshot';
        $ch = curl_init($googleUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $resp) {
            $data = json_decode($resp, true);
            $b64 = $data['lighthouseResult']['audits']['final-screenshot']['details']['data'] ?? '';
            if (!empty($b64)) {
                // It's a data URI like "data:image/jpeg;base64,..."
                $parts = explode(',', $b64, 2);
                if (count($parts) === 2) {
                    $imageData = base64_decode($parts[1]);
                }
            }
        }

        // Service 2: Fallback to microlink
        if ($imageData === false) {
            $mlUrl = 'https://api.microlink.io/?url=' . urlencode($url) . '&screenshot=true&meta=false&embed=screenshot.url';
            $ch = curl_init($mlUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $resp) {
                // microlink embed returns the image directly
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? '';
                if (strpos($resp, "\x89PNG") === 0 || strpos($resp, "\xFF\xD8") === 0) {
                    $imageData = $resp;
                } else {
                    $data = json_decode($resp, true);
                    $ssUrl = $data['data']['screenshot']['url'] ?? '';
                    if (!empty($ssUrl)) {
                        $ch2 = curl_init($ssUrl);
                        curl_setopt_array($ch2, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT        => 15,
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_FOLLOWLOCATION => true,
                        ]);
                        $imageData = curl_exec($ch2);
                        curl_close($ch2);
                    }
                }
            }
        }

        if ($imageData && strlen($imageData) > 1000) {
            @file_put_contents($cachePath, $imageData);
            $base64 = base64_encode($imageData);
            echo json_encode(['success' => true, 'image' => 'data:image/jpeg;base64,' . $base64]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not capture screenshot']);
        }
        break;

    // ─── Change WP Admin Password ────────────────────────────────────────────
    case 'wp_change_password':
        $instId   = isset($_POST['instance_id']) ? (int) $_POST['instance_id'] : 0;
        $login    = isset($_POST['login']) ? trim($_POST['login']) : '';
        $newPass  = isset($_POST['new_password']) ? $_POST['new_password'] : '';

        if ($instId <= 0 || empty($login) || empty($newPass)) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }

        if (strlen($newPass) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
            exit;
        }

        // WP Toolkit doesn't have a direct password change API.
        // We use the WP-CLI approach via cPanel's UAPI Terminal or exec.
        // Alternative: use the installation's SSO to change it.
        // For now, we'll try PATCH /v1/installations/{id}/account
        $result = broodle_wpt_call($hostname, $serverUser, $accessHash, $password,
            "/v1/installations/{$instId}/account", 'PATCH', [
                'currentLogin' => $login,
                'newPassword'  => $newPass,
            ]);

        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        } else {
            // Fallback message — PATCH may not be supported
            $errMsg = 'Password change not supported via API. Use WP Admin to change passwords.';
            if (is_array($result['data'] ?? null)) {
                $errMsg = $result['data']['message'] ?? $result['data']['error'] ?? $errMsg;
            }
            echo json_encode(['success' => false, 'message' => $errMsg]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
