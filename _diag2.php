<?php
$pdo = new PDO('mysql:host=localhost;dbname=whmcs', 'whmcs', 'jMiHiXK7L6A5MMeK');

// Check license status
echo "=== License Info ===\n";
$stmt = $pdo->query("SELECT setting, value FROM tblconfiguration WHERE setting LIKE '%icense%' OR setting LIKE '%License%'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['setting'] . ' = ' . substr($row['value'], 0, 100) . "\n";
}

// Check recent activity
echo "\n=== Recent Activity ===\n";
$stmt2 = $pdo->query("SELECT id, date, description FROM tblactivitylog ORDER BY id DESC LIMIT 5");
while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    echo $row['date'] . ' | ' . substr($row['description'], 0, 120) . "\n";
}

// Check addon module status
echo "\n=== Addon Module Status ===\n";
$stmt3 = $pdo->query("SELECT * FROM tbladdonmodules WHERE module='broodle_whmcs_tools'");
while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
    echo $row['setting'] . ' = ' . $row['value'] . "\n";
}

// Check if client area is accessible at all (not just license error)
echo "\n=== WHMCS Config ===\n";
$stmt4 = $pdo->query("SELECT setting, value FROM tblconfiguration WHERE setting IN ('Version','SystemURL','MaintenanceMode','MaintenanceModeMessage','DisableClientArea')");
while ($row = $stmt4->fetch(PDO::FETCH_ASSOC)) {
    echo $row['setting'] . ' = ' . substr($row['value'], 0, 200) . "\n";
}

// Check if the product details page is even reachable
echo "\n=== Service 601 Details ===\n";
$stmt5 = $pdo->query("SELECT id, userid, domain, domainstatus, server, packageid, username FROM tblhosting WHERE id=601");
$svc = $stmt5->fetch(PDO::FETCH_ASSOC);
if ($svc) {
    foreach ($svc as $k => $v) echo "  $k = $v\n";
} else {
    echo "  Service 601 NOT FOUND\n";
}

// Check the Hook Manager source to understand how addon hooks are loaded
echo "\n=== Hook Manager Loading ===\n";
$hookManager = 'C:/aapanel/BtSoft/wwwroot/whmcs/vendor/whmcs/whmcs-foundation/lib/Hook/Manager.php';
if (file_exists($hookManager)) {
    $content = file_get_contents($hookManager);
    // Find the method that loads addon hooks
    if (preg_match('/function\s+loadAddon/i', $content)) {
        echo "Has loadAddon method\n";
    }
    // Find any reference to addon hooks loading
    if (preg_match_all('/function\s+(\w+)\s*\(/', $content, $matches)) {
        echo "Methods in Manager.php:\n";
        foreach ($matches[1] as $m) echo "  - $m()\n";
    }
    // Check for addon module hook loading logic
    if (preg_match('/modules.addons|addons.*hooks/i', $content)) {
        echo "References addon hooks loading\n";
    }
    // Show the class size
    echo "Manager.php size: " . strlen($content) . " bytes\n";
}

// Check HookServiceProvider
$hookSP = 'C:/aapanel/BtSoft/wwwroot/whmcs/vendor/whmcs/whmcs-foundation/lib/Hook/HookServiceProvider.php';
if (file_exists($hookSP)) {
    $content = file_get_contents($hookSP);
    if (preg_match_all('/function\s+(\w+)\s*\(/', $content, $matches)) {
        echo "\nMethods in HookServiceProvider.php:\n";
        foreach ($matches[1] as $m) echo "  - $m()\n";
    }
    echo "HookServiceProvider.php size: " . strlen($content) . " bytes\n";
}

// Check if there's a cached hooks file or registry
echo "\n=== Checking for hook cache/registry ===\n";
$cachePaths = [
    'C:/aapanel/BtSoft/wwwroot/whmcs/whmcsdata',
    'C:/aapanel/BtSoft/wwwroot/whmcs/templates_c',
];
foreach ($cachePaths as $cp) {
    if (is_dir($cp)) {
        echo "$cp exists\n";
        $files = scandir($cp);
        $count = count($files) - 2;
        echo "  Contains $count items\n";
        // Look for hook-related cache files
        foreach ($files as $f) {
            if (stripos($f, 'hook') !== false || stripos($f, 'addon') !== false || stripos($f, 'module') !== false) {
                echo "  MATCH: $f\n";
            }
        }
    }
}

// CRITICAL: Check if the client area is even working (not stuck on license error)
echo "\n=== Is client area working? ===\n";
echo "Last web log entries show: licenseerror.php redirects\n";
echo "This means WHMCS license is INVALID - client area may not load addon hooks!\n";

// Check if there's a configuration.php we can read
$configFile = 'C:/aapanel/BtSoft/wwwroot/whmcs/configuration.php';
if (file_exists($configFile)) {
    echo "\nconfiguration.php exists\n";
    $configContent = file_get_contents($configFile);
    // Check for customadminpath
    if (preg_match('/customadminpath\s*=\s*[\'"]([^\'"]+)/', $configContent, $m)) {
        echo "Custom admin path: " . $m[1] . "\n";
    }
    // Check for display_errors
    if (preg_match('/display_errors/i', $configContent)) {
        echo "Has display_errors setting\n";
    }
}
