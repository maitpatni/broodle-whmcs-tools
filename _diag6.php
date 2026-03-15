<?php
$pdo = new PDO('mysql:host=localhost;dbname=whmcs', 'whmcs', 'jMiHiXK7L6A5MMeK');

// Check ALL rows for broodle_whmcs_tools in tbladdonmodules
echo "=== ALL broodle_whmcs_tools rows in tbladdonmodules ===\n";
$stmt = $pdo->query("SELECT * FROM tbladdonmodules WHERE module='broodle_whmcs_tools'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Row count: " . count($rows) . "\n";
foreach ($rows as $row) {
    echo "  module={$row['module']} | setting={$row['setting']} | value={$row['value']}\n";
}

// Compare with a known working module like RSThemes
echo "\n=== RSThemes rows in tbladdonmodules ===\n";
$stmt2 = $pdo->query("SELECT * FROM tbladdonmodules WHERE module='RSThemes'");
$rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "Row count: " . count($rows2) . "\n";
foreach ($rows2 as $row) {
    echo "  module={$row['module']} | setting={$row['setting']} | value=" . substr($row['value'], 0, 80) . "\n";
}

// Compare with creditmanager
echo "\n=== creditmanager rows in tbladdonmodules ===\n";
$stmt3 = $pdo->query("SELECT * FROM tbladdonmodules WHERE module='creditmanager'");
$rows3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);
echo "Row count: " . count($rows3) . "\n";
foreach ($rows3 as $row) {
    echo "  module={$row['module']} | setting={$row['setting']} | value=" . substr($row['value'], 0, 80) . "\n";
}

// Check the tbladdonmodules table structure
echo "\n=== tbladdonmodules table structure ===\n";
$stmt4 = $pdo->query("DESCRIBE tbladdonmodules");
while ($row = $stmt4->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['Field']} | {$row['Type']} | {$row['Key']}\n";
}

// Check if there's a separate table for active/inactive status
echo "\n=== Tables with 'addon' in name ===\n";
$stmt5 = $pdo->query("SHOW TABLES LIKE '%addon%'");
while ($row = $stmt5->fetch(PDO::FETCH_NUM)) {
    echo "  {$row[0]}\n";
}

// Check if there's a tblmodules or similar
echo "\n=== Tables with 'module' in name ===\n";
$stmt6 = $pdo->query("SHOW TABLES LIKE '%module%'");
while ($row = $stmt6->fetch(PDO::FETCH_NUM)) {
    echo "  {$row[0]}\n";
}

// CRITICAL: Check broodle_app_connector (a working Broodle module) vs broodle_whmcs_tools
echo "\n=== broodle_app_connector rows ===\n";
$stmt7 = $pdo->query("SELECT * FROM tbladdonmodules WHERE module='broodle_app_connector'");
$rows7 = $stmt7->fetchAll(PDO::FETCH_ASSOC);
echo "Row count: " . count($rows7) . "\n";
foreach ($rows7 as $row) {
    echo "  module={$row['module']} | setting={$row['setting']} | value={$row['value']}\n";
}

// Check if broodle_whmcs_tools has the right directory name
echo "\n=== Module directory check ===\n";
$addonsDir = 'C:/aapanel/BtSoft/wwwroot/whmcs/modules/addons';
echo "broodle_whmcs_tools dir exists: " . (is_dir($addonsDir . '/broodle_whmcs_tools') ? 'YES' : 'NO') . "\n";
echo "Contents:\n";
foreach (scandir($addonsDir . '/broodle_whmcs_tools') as $f) {
    if ($f === '.' || $f === '..') continue;
    $path = $addonsDir . '/broodle_whmcs_tools/' . $f;
    if (is_dir($path)) {
        echo "  [DIR] $f/\n";
    } else {
        echo "  $f (" . filesize($path) . " bytes)\n";
    }
}

// Check if the main module file returns the right config
echo "\n=== Module config function check ===\n";
// We can't call it directly (needs WHMCS defined), but check if the function exists
$mainFile = file_get_contents($addonsDir . '/broodle_whmcs_tools/broodle_whmcs_tools.php');
if (preg_match('/function\s+broodle_whmcs_tools_config\s*\(/', $mainFile)) {
    echo "broodle_whmcs_tools_config() function EXISTS\n";
}
if (preg_match('/function\s+broodle_whmcs_tools_activate\s*\(/', $mainFile)) {
    echo "broodle_whmcs_tools_activate() function EXISTS\n";
}

// IMPORTANT: Check if the version mismatch matters
// DB says version=1.3.1, code says 3.9.6
echo "\n=== Version mismatch ===\n";
echo "DB version: 1.3.1\n";
echo "Code version: 3.9.6\n";
echo "This mismatch might cause WHMCS to skip loading hooks!\n";

// Check what version other modules have in DB vs code
echo "\n=== broodle_app_connector version check ===\n";
$appConnFile = $addonsDir . '/broodle_app_connector/broodle_app_connector.php';
if (file_exists($appConnFile)) {
    $appContent = file_get_contents($appConnFile);
    if (preg_match("/['\"]version['\"]\s*=>\s*['\"]([^'\"]+)['\"]/", $appContent, $m)) {
        echo "Code version: {$m[1]}\n";
    }
}
