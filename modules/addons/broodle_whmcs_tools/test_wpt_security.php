<?php
/**
 * WP Toolkit Security API Test Script
 * 
 * Run from CLI on the WHMCS server:
 *   php test_wpt_security.php <service_id> <instance_id>
 * 
 * Or via browser (must be logged in as admin):
 *   test_wpt_security.php?service_id=XX&instance_id=YY
 * 
 * This will test all security-related WP Toolkit API endpoints
 * and dump the raw responses so we can see the actual data format.
 */

define('CLIENTAREA', true);
require_once __DIR__ . '/../../../init.php';

use WHMCS\Database\Capsule;

header('Content-Type: text/plain; charset=utf-8');

// Get params from CLI or GET
if (php_sapi_name() === 'cli') {
    $serviceId  = (int) ($argv[1] ?? 0);
    $instanceId = (int) ($argv[2] ?? 0);
} else {
    $serviceId  = (int) ($_GET['service_id'] ?? 0);
    $instanceId = (int) ($_GET['instance_id'] ?? 0);
}

if (!$serviceId) {
    die("Usage: php test_wpt_security.php <service_id> <instance_id>\n");
}

echo "=== WP Toolkit Security API Test ===\n";
echo "Service ID: {$serviceId}\n";
echo "Instance ID: {$instanceId}\n\n";

// Get server info from service
$service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
if (!$service) die("Service not found\n");

$server = Capsule::table('tblservers')->where('id', $service->server)->first();
if (!$server) die("Server not found\n");

$hostname   = $server->hostname;
$serverUser = $server->username;
$cpUsername  = $service->username;

echo "Hostname: {$hostname}\n";
echo "Server User: {$serverUser}\n";
echo "cPanel User: {$cpUsername}\n\n";

// Resolve credentials
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
echo "Auth method: " . (!empty($accessHash) ? "API Token" : (!empty($password) ? "Password" : "NONE")) . "\n\n";

if (empty($accessHash) && empty($password)) {
    die("No credentials available\n");
}

// Copy of broodle_wpt_call
function wpt_call($hostname, $serverUser, $accessHash, $password, $endpoint, $method = 'GET', $postData = null, $timeout = 30)
{
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
    if (!empty($headers)) $opts[CURLOPT_HTTPHEADER] = $headers;
    elseif (!empty($password)) $opts[CURLOPT_USERPWD] = "{$serverUser}:{$password}";
    curl_setopt_array($ch, $opts);
    $sessResp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($sessResp === false || $httpCode !== 200) {
        return ['error' => "Session creation failed (HTTP {$httpCode})", 'raw' => $sessResp];
    }

    $sessData = json_decode($sessResp, true);
    $cpSessToken = $sessData['data']['cp_security_token'] ?? '';
    $loginUrl    = $sessData['data']['url'] ?? '';

    if (empty($cpSessToken) || empty($loginUrl)) {
        return ['error' => 'Invalid session response', 'raw' => $sessResp];
    }

    // Activate session
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
        return ['error' => 'Failed to get session cookie'];
    }

    // Call WPT API
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
        if ($postData !== null) $apiOpts[CURLOPT_POSTFIELDS] = json_encode($postData);
    } elseif ($method === 'PUT') {
        $apiOpts[CURLOPT_CUSTOMREQUEST] = 'PUT';
        if ($postData !== null) $apiOpts[CURLOPT_POSTFIELDS] = json_encode($postData);
    }
    curl_setopt_array($ch, $apiOpts);
    $apiResp    = curl_exec($ch);
    $apiHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError  = curl_error($ch);
    curl_close($ch);

    return [
        'http_code' => $apiHttpCode,
        'raw'       => $apiResp,
        'decoded'   => json_decode($apiResp, true),
        'curl_error'=> $curlError,
    ];
}

function test_endpoint($label, $hostname, $serverUser, $accessHash, $password, $endpoint, $method = 'GET', $postData = null) {
    echo "--- {$label} ---\n";
    echo "  {$method} {$endpoint}\n";
    if ($postData) echo "  Body: " . json_encode($postData) . "\n";
    
    $r = wpt_call($hostname, $serverUser, $accessHash, $password, $endpoint, $method, $postData);
    
    if (isset($r['error'])) {
        echo "  ERROR: {$r['error']}\n\n";
        return null;
    }
    
    echo "  HTTP: {$r['http_code']}\n";
    
    if ($r['decoded'] !== null) {
        $json = json_encode($r['decoded'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        // Truncate if too long
        if (strlen($json) > 3000) {
            echo "  Response (truncated to 3000 chars):\n";
            echo substr($json, 0, 3000) . "\n  ... [TRUNCATED]\n\n";
        } else {
            echo "  Response:\n{$json}\n\n";
        }
    } else {
        $raw = substr($r['raw'] ?? '', 0, 1000);
        echo "  Raw (not JSON): {$raw}\n\n";
    }
    
    return $r;
}

// ─── Test 1: First get the OpenAPI spec to see available endpoints ───
echo "========================================\n";
echo "TEST: Fetch API specification\n";
echo "========================================\n";
$specResult = test_endpoint("OpenAPI Spec (partial)", $hostname, $serverUser, $accessHash, $password, '/v1/specification/public');

// ─── Test 2: List installations to verify connectivity ───
echo "========================================\n";
echo "TEST: List installations (verify connectivity)\n";
echo "========================================\n";
$instResult = test_endpoint("List Installations", $hostname, $serverUser, $accessHash, $password, '/v1/installations');

// If no instance_id provided, try to find one
if ($instanceId <= 0 && $instResult && is_array($instResult['decoded'])) {
    foreach ($instResult['decoded'] as $inst) {
        $ownerLogin = $inst['owner']['login'] ?? '';
        if ($ownerLogin === $cpUsername) {
            $instanceId = (int) ($inst['id'] ?? 0);
            echo "Auto-detected instance ID: {$instanceId}\n\n";
            break;
        }
    }
}

if ($instanceId <= 0) {
    die("No instance ID available. Pass it as second argument.\n");
}

echo "========================================\n";
echo "TESTING SECURITY ENDPOINTS for instance {$instanceId}\n";
echo "========================================\n\n";

// ─── Test 3: All security checker endpoint variations ───

test_endpoint(
    "Security Checker: POST /v1/security-measures/checker {installationsIds: [id]}",
    $hostname, $serverUser, $accessHash, $password,
    '/v1/security-measures/checker', 'POST',
    ['installationsIds' => [$instanceId]]
);

test_endpoint(
    "Security Checker: POST /v1/security-measures/checker {installationId: id}",
    $hostname, $serverUser, $accessHash, $password,
    '/v1/security-measures/checker', 'POST',
    ['installationId' => $instanceId]
);

test_endpoint(
    "Security Checker: GET /v1/security-measures/checker?installationsIds[]={id}",
    $hostname, $serverUser, $accessHash, $password,
    "/v1/security-measures/checker?installationsIds[]={$instanceId}"
);

test_endpoint(
    "Security Checker: GET /v1/security-measures/checker?installationId={id}",
    $hostname, $serverUser, $accessHash, $password,
    "/v1/security-measures/checker?installationId={$instanceId}"
);

test_endpoint(
    "Security Checker: GET /v1/installations/{id}/security",
    $hostname, $serverUser, $accessHash, $password,
    "/v1/installations/{$instanceId}/security"
);

test_endpoint(
    "Security Checker: GET /v1/installations/{id}/security-measures",
    $hostname, $serverUser, $accessHash, $password,
    "/v1/installations/{$instanceId}/security-measures"
);

test_endpoint(
    "Security Checker: GET /v1/security/checker?installationId={id}",
    $hostname, $serverUser, $accessHash, $password,
    "/v1/security/checker?installationId={$instanceId}"
);

echo "\n========================================\n";
echo "DONE — Check the responses above to find the working endpoint\n";
echo "and the exact data format returned.\n";
echo "========================================\n";
