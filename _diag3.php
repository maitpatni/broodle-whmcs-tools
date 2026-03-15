<?php
$pdo = new PDO('mysql:host=localhost;dbname=whmcs', 'whmcs', 'jMiHiXK7L6A5MMeK');

// 1. Check Hook Manager for addon loading logic
echo "=== Hook Manager Analysis ===\n";
$hookManager = file_get_contents('C:/aapanel/BtSoft/wwwroot/whmcs/vendor/whmcs/whmcs-foundation/lib/Hook/Manager.php');

// Find all method names
preg_match_all('/(?:public|private|protected|static)\s+function\s+(\w+)/', $hookManager, $methods);
echo "Methods:\n";
foreach ($methods[1] as $m) echo "  $m\n";

// Find addon-related code
echo "\n=== Addon hook loading references ===\n";
$lines = explode("\n", $hookManager);
foreach ($lines as $i => $line) {
    if (preg_match('/addon|module.*hook|hooks\.php|loadModule/i', $line)) {
        $start = max(0, $i - 1);
        $end = min(count($lines) - 1, $i + 1);
        for ($j = $start; $j <= $end; $j++) {
            echo "  L" . ($j+1) . ": " . trim($lines[$j]) . "\n";
        }
        echo "  ---\n";
    }
}

// 2. Check HookServiceProvider for addon loading
echo "\n=== HookServiceProvider Analysis ===\n";
$hookSP = file_get_contents('C:/aapanel/BtSoft/wwwroot/whmcs/vendor/whmcs/whmcs-foundation/lib/Hook/HookServiceProvider.php');
preg_match_all('/(?:public|private|protected|static)\s+function\s+(\w+)/', $hookSP, $methods2);
echo "Methods:\n";
foreach ($methods2[1] as $m) echo "  $m\n";

$lines2 = explode("\n", $hookSP);
foreach ($lines2 as $i => $line) {
    if (preg_match('/addon|module.*hook|hooks\.php|loadModule/i', $line)) {
        $start = max(0, $i - 2);
        $end = min(count($lines2) - 1, $i + 2);
        for ($j = $start; $j <= $end; $j++) {
            echo "  L" . ($j+1) . ": " . trim($lines2[$j]) . "\n";
        }
        echo "  ---\n";
    }
}

// 3. Search for where addon hooks are loaded in the WHMCS codebase
echo "\n=== Searching for addon hook loading ===\n";
$searchDirs = [
    'C:/aapanel/BtSoft/wwwroot/whmcs/includes',
    'C:/aapanel/BtSoft/wwwroot/whmcs/vendor/whmcs/whmcs-foundation/lib',
];

function searchFiles($dir, $pattern, $depth = 0) {
    if ($depth > 3) return;
    $results = [];
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $dir . '/' . $f;
        if (is_dir($path)) {
            $results = array_merge($results, searchFiles($path, $pattern, $depth + 1));
        } elseif (preg_match('/\.php$/', $f)) {
            $content = file_get_contents($path);
            if (preg_match($pattern, $content)) {
                $results[] = $path;
            }
        }
    }
    return $results;
}

// Search for files that load addon hooks
$files = searchFiles('C:/aapanel/BtSoft/wwwroot/whmcs/includes', '/addons.*hooks\.php|hooks\.php.*addons/i');
echo "Files referencing addon hooks in includes/:\n";
foreach ($files as $f) echo "  $f\n";

$files2 = searchFiles('C:/aapanel/BtSoft/wwwroot/whmcs/vendor/whmcs/whmcs-foundation/lib', '/addons.*hooks\.php|hooks\.php.*addons/i');
echo "Files referencing addon hooks in vendor/whmcs/:\n";
foreach ($files2 as $f) echo "  $f\n";

// 4. Check if the client area page actually loads (not just license error)
echo "\n=== Client Area Access Check ===\n";
// Try to curl the product details page
$ch = curl_init('http://localhost:8080/clientarea.php?action=productdetails&id=601');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_HEADER => true,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Final URL: $finalUrl\n";
if ($response) {
    // Check if it's a license error page
    if (strpos($response, 'licenseerror') !== false || strpos($response, 'License') !== false) {
        echo "RESPONSE CONTAINS LICENSE ERROR\n";
    }
    // Check if it contains our hook output
    if (strpos($response, 'bt-data') !== false || strpos($response, '__btConfig') !== false) {
        echo "RESPONSE CONTAINS OUR HOOK OUTPUT!\n";
    }
    // Check if it's a login page
    if (strpos($response, 'login') !== false) {
        echo "RESPONSE IS LOGIN PAGE (need auth)\n";
    }
    // Show first 500 chars of body
    $bodyStart = strpos($response, "\r\n\r\n");
    if ($bodyStart !== false) {
        $body = substr($response, $bodyStart + 4, 500);
        echo "Body preview: " . $body . "\n";
    }
}

// 5. Check the whmcsdata directory contents
echo "\n=== whmcsdata contents ===\n";
$whmcsdata = 'C:/aapanel/BtSoft/wwwroot/whmcs/whmcsdata';
if (is_dir($whmcsdata)) {
    foreach (scandir($whmcsdata) as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $whmcsdata . '/' . $f;
        if (is_dir($path)) {
            echo "  [DIR] $f\n";
            // List contents
            foreach (scandir($path) as $f2) {
                if ($f2 === '.' || $f2 === '..') continue;
                echo "    $f2\n";
            }
        } else {
            echo "  $f (" . filesize($path) . " bytes)\n";
        }
    }
}
