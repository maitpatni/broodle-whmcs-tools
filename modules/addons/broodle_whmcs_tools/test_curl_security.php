<?php
/**
 * Direct cURL test for WP Toolkit Security API
 * 
 * Run on the WHMCS server:
 *   php test_curl_security.php
 * 
 * This script:
 * 1. Reads WHMCS DB config from configuration.php
 * 2. Finds jp3.broodlepro.com server credentials
 * 3. Tests all WP Toolkit security endpoints with raw cURL
 * 4. Dumps everything so we can see the exact API response format
 */

// Load WHMCS config to get DB credentials
$configFile = __DIR__ . '/../../../configuration.php';
if (!file_exists($configFile)) {
    die("ERROR: configuration.php not found at {$configFile}\n");
}
require $configFile;

echo "=== WP Toolkit Security API - Direct cURL Test ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Connect to WHMCS database
$db = new mysqli($db_host, $db_username, $db_password, $db_name);
if ($db->connect_error) {
    die("DB connection failed: " . $db->connect_error . "\n");
}
echo "[OK] Connected to WHMCS database: {$db_name}\n\n";

// Find the server jp3.broodlepro.com
$serverHostname = 'jp3.broodlepro.com';
$stmt = $db->prepare("SELECT id, name, hostname, username, accesshash, password FROM tblservers WHERE hostname = ? OR name LIKE ? LIMIT 1");
$like = "%jp3%";
$stmt->bind_param("ss", $serverHostname, $like);
$stmt->execute();
$result = $stmt->get_result();
$server = $result->fetch_assoc();

if (!$server) {
    // Try broader search
    echo "Server not found by hostname. Listing all servers:\n";
    $res = $db->query("SELECT id, name, hostname, username FROM tblservers");
    while ($row = $res->fetch_assoc()) {
        echo "  ID={$row['id']} name={$row['name']} hostname={$row['hostname']} user={$row['username']}\n";
    }
    die("\nPlease update the script with the correct hostname.\n");
}

echo "Server found:\n";
echo "  ID: {$server['id']}\n";
echo "  Name: {$server['name']}\n";
echo "  Hostname: {$server['hostname']}\n";
echo "  Username: {$server['username']}\n";
echo "  AccessHash length: " . strlen($server['accesshash']) . "\n";
echo "  Password length: " . strlen($server['password']) . "\n\n";

$hostname   = $server['hostname'];
$serverUser = $server['username'];

// WHMCS encrypts credentials - we need to decrypt them
// Load WHMCS framework for decrypt()
define('CLIENTAREA', true);
require_once __DIR__ . '/../../../init.php';

$accessHash = '';
$password   = '';

if (!empty($server['accesshash'])) {
    $raw = trim($server['accesshash']);
    if (preg_match('/^[A-Za-z0-9]{10,64}$/', $raw)) {
        $accessHash = $raw;
    } else {
        $accessHash = trim(decrypt($raw));
        if (empty($accessHash) || !preg_match('/^[A-Za-z0-9]{10,64}$/', $accessHash)) {
            $accessHash = '';
        }
    }
}
if (empty($accessHash) && !empty($server['password'])) {
    $password = trim(decrypt($server['password']));
}

echo "Auth method: " . (!empty($accessHash) ? "API Token (len=" . strlen($accessHash) . ")" : (!empty($password) ? "Password" : "NONE")) . "\n\n";

if (empty($accessHash) && empty($password)) {
    die("No credentials available!\n");
}

// Find a WordPress installation on this server
// First find a service/account on this server
$res = $db->query("SELECT id, username, domain FROM tblhosting WHERE server = {$server['id']} AND domainstatus = 'Active' LIMIT 5");
echo "Active services on this server:\n";
$services = [];
while ($row = $res->fetch_assoc()) {
    echo "  Service ID={$row['id']} user={$row['username']} domain={$row['domain']}\n";
    $services[] = $row;
}
echo "\n";


// ── Helper: Create WHM session and call WPT API ──
function wpt_curl($hostname, $serverUser, $accessHash, $password, $endpoint, $method = 'GET', $postData = null) {
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
    if (!empty($headers)) $opts[CURLOPT_HTTPHEADER] = $headers;
    elseif (!empty($password)) $opts[CURLOPT_USERPWD] = "{$serverUser}:{$password}";
    curl_setopt_array($ch, $opts);
    $sessResp = curl_exec($ch);
    $sessHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $sessErr  = curl_error($ch);
    curl_close($ch);

    if ($sessResp === false || $sessHttp !== 200) {
        return ['step' => 'session', 'http' => $sessHttp, 'error' => $sessErr, 'raw' => substr($sessResp, 0, 500)];
    }

    $sessData = json_decode($sessResp, true);
    $cpSessToken = $sessData['data']['cp_security_token'] ?? '';
    $loginUrl    = $sessData['data']['url'] ?? '';

    if (empty($cpSessToken) || empty($loginUrl)) {
        return ['step' => 'session_parse', 'raw' => substr($sessResp, 0, 500)];
    }

    // Step 2: Activate session
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
        return ['step' => 'activate', 'error' => 'No session cookie'];
    }

    // Step 3: Call WPT API
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
        CURLOPT_TIMEOUT        => 30,
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
    $apiHttp    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $apiErr     = curl_error($ch);
    curl_close($ch);

    return [
        'url'     => $wptUrl,
        'http'    => $apiHttp,
        'error'   => $apiErr,
        'raw'     => $apiResp,
        'decoded' => json_decode($apiResp, true),
    ];
}

function dump_test($label, $result) {
    echo "━━━ {$label} ━━━\n";
    if (isset($result['step'])) {
        echo "  FAILED at step: {$result['step']}\n";
        if (isset($result['error'])) echo "  Error: {$result['error']}\n";
        if (isset($result['raw'])) echo "  Raw: {$result['raw']}\n";
        echo "\n";
        return;
    }
    echo "  HTTP: {$result['http']}\n";
    if ($result['error']) echo "  cURL Error: {$result['error']}\n";
    if ($result['decoded'] !== null) {
        $json = json_encode($result['decoded'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($json) > 5000) {
            echo "  Response (first 5000 chars):\n" . substr($json, 0, 5000) . "\n  ...[TRUNCATED]\n";
        } else {
            echo "  Response:\n{$json}\n";
        }
    } else {
        echo "  Raw: " . substr($result['raw'] ?? '(empty)', 0, 2000) . "\n";
    }
    echo "\n";
}

// ── Test 0: Verify WHM connectivity ──
echo "========================================\n";
echo "TEST 0: WHM API connectivity check\n";
echo "========================================\n";
$r = wpt_curl($hostname, $serverUser, $accessHash, $password, '/v1/installations');
if (isset($r['step'])) {
    echo "FATAL: Cannot connect to WHM API!\n";
    dump_test("Installations list", $r);
    die("Fix server connectivity first.\n");
}
echo "[OK] WHM API accessible, HTTP {$r['http']}\n";

// Find WP instances
$instances = [];
if ($r['http'] === 200 && is_array($r['decoded'])) {
    foreach ($r['decoded'] as $inst) {
        $instances[] = [
            'id'     => $inst['id'] ?? 0,
            'domain' => $inst['domain']['name'] ?? ($inst['displayTitle'] ?? '?'),
            'owner'  => $inst['owner']['login'] ?? '?',
            'url'    => $inst['url'] ?? '',
        ];
    }
}
echo "Found " . count($instances) . " WP installations total:\n";
foreach ($instances as $inst) {
    echo "  ID={$inst['id']} owner={$inst['owner']} domain={$inst['domain']} url={$inst['url']}\n";
}
echo "\n";

if (empty($instances)) {
    die("No WordPress installations found on this server.\n");
}

// Use the first instance for testing
$testInstId = $instances[0]['id'];
echo "Using instance ID {$testInstId} ({$instances[0]['domain']}) for security tests\n\n";

// ── Test 1: OpenAPI spec - find security endpoints ──
echo "========================================\n";
echo "TEST 1: OpenAPI Spec - Security endpoints\n";
echo "========================================\n";
$r = wpt_curl($hostname, $serverUser, $accessHash, $password, '/v1/specification/public');
if ($r['http'] === 200 && is_array($r['decoded']) && isset($r['decoded']['paths'])) {
    echo "Security-related paths in API spec:\n";
    $found = false;
    foreach ($r['decoded']['paths'] as $path => $methods) {
        if (stripos($path, 'security') !== false || stripos($path, 'secur') !== false) {
            echo "  {$path}: " . implode(', ', array_map('strtoupper', array_keys($methods))) . "\n";
            $found = true;
        }
    }
    if (!$found) echo "  (none found - WP Toolkit may not expose security via REST API on this version)\n";
    
    // Also show all available paths
    echo "\nAll available API paths:\n";
    foreach ($r['decoded']['paths'] as $path => $methods) {
        echo "  {$path}: " . implode(', ', array_map('strtoupper', array_keys($methods))) . "\n";
    }
} else {
    dump_test("OpenAPI Spec", $r);
}
echo "\n";


// ── Test 2-8: All security endpoint variations ──
echo "========================================\n";
echo "SECURITY ENDPOINT TESTS\n";
echo "========================================\n\n";

dump_test(
    "Test 2: POST /v1/security-measures/checker {installationsIds: [$testInstId]}",
    wpt_curl($hostname, $serverUser, $accessHash, $password,
        '/v1/security-measures/checker', 'POST', ['installationsIds' => [$testInstId]])
);

dump_test(
    "Test 3: POST /v1/security-measures/checker {installationId: $testInstId}",
    wpt_curl($hostname, $serverUser, $accessHash, $password,
        '/v1/security-measures/checker', 'POST', ['installationId' => $testInstId])
);

dump_test(
    "Test 4: GET /v1/security-measures/checker?installationsIds[]=$testInstId",
    wpt_curl($hostname, $serverUser, $accessHash, $password,
        "/v1/security-measures/checker?installationsIds[]={$testInstId}")
);

dump_test(
    "Test 5: GET /v1/security-measures/checker?installationId=$testInstId",
    wpt_curl($hostname, $serverUser, $accessHash, $password,
        "/v1/security-measures/checker?installationId={$testInstId}")
);

dump_test(
    "Test 6: GET /v1/installations/$testInstId/security",
    wpt_curl($hostname, $serverUser, $accessHash, $password,
        "/v1/installations/{$testInstId}/security")
);

dump_test(
    "Test 7: GET /v1/installations/$testInstId/security-measures",
    wpt_curl($hostname, $serverUser, $accessHash, $password,
        "/v1/installations/{$testInstId}/security-measures")
);

dump_test(
    "Test 8: GET /v1/security/checker?installationId=$testInstId",
    wpt_curl($hostname, $serverUser, $accessHash, $password,
        "/v1/security/checker?installationId={$testInstId}")
);

dump_test(
    "Test 9: POST /v1/security/checker {installationsIds: [$testInstId]}",
    wpt_curl($hostname, $serverUser, $accessHash, $password,
        '/v1/security/checker', 'POST', ['installationsIds' => [$testInstId]])
);

dump_test(
    "Test 10: GET /v1/security?installationId=$testInstId",
    wpt_curl($hostname, $serverUser, $accessHash, $password,
        "/v1/security?installationId={$testInstId}")
);

echo "========================================\n";
echo "ALL TESTS COMPLETE\n";
echo "========================================\n";
echo "Copy the output above and share it so we can fix the parsing.\n";
