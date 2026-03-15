<?php
$pdo = new PDO('mysql:host=localhost;dbname=whmcs', 'whmcs', 'jMiHiXK7L6A5MMeK');

// Check WHMCS version
$stmt = $pdo->query("SELECT value FROM tblconfiguration WHERE setting='Version'");
$version = $stmt->fetchColumn();
echo "WHMCS Version: $version\n";

// Check if there's a different storage path
$stmt2 = $pdo->query("SELECT value FROM tblconfiguration WHERE setting='StoragePath'");
$storagePath = $stmt2->fetchColumn();
echo "Storage Path: " . ($storagePath ?: 'NOT SET') . "\n";

// Check the WHMCS root for any storage-like directories
echo "\nWHMCS root directory:\n";
$whmcsRoot = 'C:/aapanel/BtSoft/wwwroot/whmcs';
foreach (scandir($whmcsRoot) as $item) {
    if ($item === '.' || $item === '..') continue;
    $path = $whmcsRoot . '/' . $item;
    if (is_dir($path)) {
        echo "  [DIR] $item\n";
    }
}

// Check if WHMCS is running on IIS or Apache/Nginx
echo "\nServer software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A (CLI)') . "\n";

// Check if there's a web server error log we can read
$webLogs = [
    'C:/aapanel/BtSoft/wwwlogs/whmcs.log',
    'C:/aapanel/BtSoft/wwwlogs/nginx_error.log',
    'C:/aapanel/BtSoft/wwwlogs/apache_error.log',
    'C:/aapanel/BtSoft/wwwlogs/php_errors.log',
];
echo "\nChecking web logs:\n";
foreach ($webLogs as $log) {
    if (file_exists($log)) {
        $size = filesize($log);
        echo "  FOUND: $log ($size bytes)\n";
        if ($size > 0) {
            // Show last 5 lines
            $lines = file($log);
            $start = max(0, count($lines) - 5);
            echo "  Last 5 lines:\n";
            for ($i = $start; $i < count($lines); $i++) {
                echo "    " . rtrim($lines[$i]) . "\n";
            }
        }
    }
}

// CRITICAL: Check if the hooks.php file has a BOM or encoding issue
$hooksContent = file_get_contents('C:/aapanel/BtSoft/wwwroot/whmcs/modules/addons/broodle_whmcs_tools/hooks.php');
$firstBytes = bin2hex(substr($hooksContent, 0, 10));
echo "\nFirst 10 bytes of hooks.php (hex): $firstBytes\n";
$expectedStart = '3c3f706870'; // <?php
echo "Starts with <?php: " . (substr($firstBytes, 0, 10) === $expectedStart ? 'YES' : 'NO') . "\n";

// Check if there's a BOM
$bom = substr($hooksContent, 0, 3);
if ($bom === "\xEF\xBB\xBF") {
    echo "WARNING: File has UTF-8 BOM!\n";
} else {
    echo "No BOM detected\n";
}

// Check if the file can be parsed by PHP
echo "\nAttempting to tokenize hooks.php...\n";
$tokens = token_get_all($hooksContent);
echo "Token count: " . count($tokens) . "\n";
// Check first few tokens
for ($i = 0; $i < min(5, count($tokens)); $i++) {
    if (is_array($tokens[$i])) {
        echo "  Token $i: " . token_name($tokens[$i][0]) . " = " . json_encode(substr($tokens[$i][1], 0, 30)) . "\n";
    } else {
        echo "  Token $i: " . json_encode($tokens[$i]) . "\n";
    }
}

// Check if maybe WHMCS is loading hooks from a different path
echo "\nChecking includes/hooks/ directory:\n";
$includesHooks = 'C:/aapanel/BtSoft/wwwroot/whmcs/includes/hooks';
if (is_dir($includesHooks)) {
    foreach (scandir($includesHooks) as $f) {
        if ($f === '.' || $f === '..') continue;
        echo "  $f (" . filesize($includesHooks . '/' . $f) . " bytes)\n";
    }
}

// Check if there's a hook loading mechanism we can trace
echo "\nChecking vendor/whmcs for hook loading:\n";
$hookManager = 'C:/aapanel/BtSoft/wwwroot/whmcs/vendor/whmcs/whmcs-foundation/lib/Hook';
if (is_dir($hookManager)) {
    foreach (scandir($hookManager) as $f) {
        if ($f === '.' || $f === '..') continue;
        echo "  $f\n";
    }
}
