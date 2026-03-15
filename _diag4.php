<?php
// Check if Hook Manager files are ionCube encoded
echo "=== File encoding check ===\n";
$files = [
    'C:/aapanel/BtSoft/wwwroot/whmcs/vendor/whmcs/whmcs-foundation/lib/Hook/Manager.php',
    'C:/aapanel/BtSoft/wwwroot/whmcs/vendor/whmcs/whmcs-foundation/lib/Hook/HookServiceProvider.php',
];
foreach ($files as $f) {
    $content = file_get_contents($f);
    $first100 = substr($content, 0, 100);
    $isIoncube = strpos($content, 'ionCube') !== false || strpos($content, 'sg_load') !== false || !preg_match('/^<\?php/', trim($content));
    echo basename($f) . ": " . ($isIoncube ? "IONCUBE ENCODED" : "PLAIN PHP") . "\n";
    echo "  First 80 chars: " . substr(preg_replace('/[\x00-\x1f]/', '.', $first100), 0, 80) . "\n";
}

// Check the includes directory for hook bootstrapping
echo "\n=== includes/ directory listing ===\n";
$includesDir = 'C:/aapanel/BtSoft/wwwroot/whmcs/includes';
foreach (scandir($includesDir) as $f) {
    if ($f === '.' || $f === '..') continue;
    if (is_file($includesDir . '/' . $f) && preg_match('/hook|addon|module|boot/i', $f)) {
        echo "  $f (" . filesize($includesDir . '/' . $f) . " bytes)\n";
    }
}

// Check for a hooks bootstrap file
echo "\n=== Looking for hook loading entry points ===\n";
$candidates = [
    'C:/aapanel/BtSoft/wwwroot/whmcs/includes/hooks.php',
    'C:/aapanel/BtSoft/wwwroot/whmcs/includes/hook.php',
    'C:/aapanel/BtSoft/wwwroot/whmcs/includes/modulefunctions.php',
    'C:/aapanel/BtSoft/wwwroot/whmcs/includes/modulefunctions/addon.php',
    'C:/aapanel/BtSoft/wwwroot/whmcs/includes/classes/WHMCS/Module/Addon.php',
];
foreach ($candidates as $c) {
    echo basename($c) . ": " . (file_exists($c) ? "EXISTS (" . filesize($c) . " bytes)" : "NOT FOUND") . "\n";
}

// Check the Module directory structure
echo "\n=== Module class files ===\n";
$moduleDir = 'C:/aapanel/BtSoft/wwwroot/whmcs/vendor/whmcs/whmcs-foundation/lib/Module';
if (is_dir($moduleDir)) {
    foreach (scandir($moduleDir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $moduleDir . '/' . $f;
        if (is_dir($path)) {
            echo "  [DIR] $f/\n";
            foreach (scandir($path) as $f2) {
                if ($f2 === '.' || $f2 === '..') continue;
                echo "    $f2\n";
            }
        } else {
            echo "  $f\n";
        }
    }
}

// CRITICAL: Check the add_hook function - where is it defined?
echo "\n=== add_hook function location ===\n";
$includesDir = 'C:/aapanel/BtSoft/wwwroot/whmcs/includes';
foreach (scandir($includesDir) as $f) {
    if ($f === '.' || $f === '..') continue;
    $path = $includesDir . '/' . $f;
    if (is_file($path) && preg_match('/\.php$/', $f)) {
        $content = @file_get_contents($path);
        if ($content && strpos($content, 'function add_hook') !== false) {
            echo "  FOUND in: $f\n";
        }
    }
}

// Check if there's a function that loads addon module hooks
echo "\n=== Searching for addon hook loader ===\n";
$searchPaths = [
    'C:/aapanel/BtSoft/wwwroot/whmcs/includes',
];
foreach ($searchPaths as $dir) {
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $dir . '/' . $f;
        if (is_file($path) && preg_match('/\.php$/', $f)) {
            $content = @file_get_contents($path);
            if (!$content) continue;
            // Look for code that includes addon hooks.php files
            if (preg_match('/addons.*hooks|modules.*addons.*include|require.*hooks/i', $content)) {
                echo "  $f references addon hooks\n";
                // Show matching lines
                $lines = explode("\n", $content);
                foreach ($lines as $i => $line) {
                    if (preg_match('/addons.*hooks|modules.*addons.*include|require.*hooks/i', $line)) {
                        echo "    L" . ($i+1) . ": " . trim($line) . "\n";
                    }
                }
            }
        }
    }
}

// Check the clientarea.php to see how it bootstraps
echo "\n=== clientarea.php bootstrap ===\n";
$clientarea = 'C:/aapanel/BtSoft/wwwroot/whmcs/clientarea.php';
if (file_exists($clientarea)) {
    $content = file_get_contents($clientarea);
    $isEncoded = strpos($content, 'ionCube') !== false || strpos($content, 'sg_load') !== false;
    echo "Encoded: " . ($isEncoded ? "YES" : "NO") . "\n";
    echo "Size: " . strlen($content) . " bytes\n";
    if (!$isEncoded) {
        // Show first 20 lines
        $lines = explode("\n", $content);
        echo "First 20 lines:\n";
        for ($i = 0; $i < min(20, count($lines)); $i++) {
            echo "  " . trim($lines[$i]) . "\n";
        }
    }
}

// Check the init.php or bootstrap
echo "\n=== init/bootstrap files ===\n";
$bootFiles = [
    'C:/aapanel/BtSoft/wwwroot/whmcs/init.php',
    'C:/aapanel/BtSoft/wwwroot/whmcs/bootstrap.php',
    'C:/aapanel/BtSoft/wwwroot/whmcs/includes/init.php',
    'C:/aapanel/BtSoft/wwwroot/whmcs/includes/bootstrap.php',
    'C:/aapanel/BtSoft/wwwroot/whmcs/includes/clientareafunctions.php',
];
foreach ($bootFiles as $bf) {
    if (file_exists($bf)) {
        $content = file_get_contents($bf);
        $isEncoded = strpos($content, 'ionCube') !== false || strpos($content, 'sg_load') !== false;
        echo basename($bf) . ": EXISTS, " . strlen($content) . " bytes, " . ($isEncoded ? "IONCUBE" : "PLAIN") . "\n";
    } else {
        echo basename($bf) . ": NOT FOUND\n";
    }
}

// Check the README in includes/hooks/ for how custom hooks work
echo "\n=== includes/hooks/README.txt ===\n";
$readme = 'C:/aapanel/BtSoft/wwwroot/whmcs/includes/hooks/README.txt';
if (file_exists($readme)) {
    echo file_get_contents($readme) . "\n";
}
