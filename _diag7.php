<?php
$pdo = new PDO('mysql:host=localhost;dbname=whmcs', 'whmcs', 'jMiHiXK7L6A5MMeK');

// 1. First, let's check if the issue is that WHMCS checks the version match
// Update the DB version to match the code version
echo "=== Updating DB version to match code ===\n";
$stmt = $pdo->prepare("UPDATE tbladdonmodules SET value = ? WHERE module = 'broodle_whmcs_tools' AND setting = 'version'");
$stmt->execute(['3.9.6']);
echo "Updated version to 3.9.6\n";

// Verify
$stmt2 = $pdo->query("SELECT * FROM tbladdonmodules WHERE module='broodle_whmcs_tools'");
while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['setting']} = {$row['value']}\n";
}

// 2. Check if there's a tblmodule_configuration entry needed
echo "\n=== tblmodule_configuration check ===\n";
$stmt3 = $pdo->query("SELECT * FROM tblmodule_configuration WHERE entity_type='addon' LIMIT 10");
$rows = $stmt3->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "No addon entries in tblmodule_configuration\n";
    // Check what's in there
    $stmt4 = $pdo->query("SELECT DISTINCT entity_type FROM tblmodule_configuration");
    while ($row = $stmt4->fetch(PDO::FETCH_ASSOC)) {
        echo "  entity_type: {$row['entity_type']}\n";
    }
} else {
    foreach ($rows as $row) {
        echo "  " . json_encode($row) . "\n";
    }
}

// 3. Check the tblmodule_configuration structure
echo "\n=== tblmodule_configuration structure ===\n";
$stmt5 = $pdo->query("DESCRIBE tblmodule_configuration");
while ($row = $stmt5->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['Field']} | {$row['Type']} | {$row['Key']}\n";
}

// 4. Check if there's a module activation/registration table
echo "\n=== Checking for module registry ===\n";
$tables = ['tblmodule_configuration', 'tblmodulelog', 'tblmodulequeue'];
foreach ($tables as $t) {
    $stmt6 = $pdo->query("SELECT COUNT(*) as cnt FROM $t");
    $cnt = $stmt6->fetchColumn();
    echo "  $t: $cnt rows\n";
}

// 5. Let's try the includes/hooks approach as a workaround
// Create a bridge hook file that loads our addon hooks
echo "\n=== Creating bridge hook in includes/hooks/ ===\n";
$bridgeContent = '<?php
/**
 * Broodle WHMCS Tools - Hook Bridge
 * This file bridges the addon module hooks into the includes/hooks system.
 * WHMCS auto-loads all .php files in includes/hooks/ (except those starting with _).
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly.");
}

$broodleHooksFile = ROOTDIR . "/modules/addons/broodle_whmcs_tools/hooks.php";
if (file_exists($broodleHooksFile)) {
    require_once $broodleHooksFile;
}
';

$bridgePath = 'C:/aapanel/BtSoft/wwwroot/whmcs/includes/hooks/broodle_whmcs_tools.php';
file_put_contents($bridgePath, $bridgeContent);
echo "Created: $bridgePath\n";
echo "Size: " . filesize($bridgePath) . " bytes\n";

// Verify it was created
echo "File exists: " . (file_exists($bridgePath) ? 'YES' : 'NO') . "\n";

echo "\n=== DONE ===\n";
echo "The bridge hook file will force WHMCS to load our addon hooks.\n";
echo "Visit clientarea.php?action=productdetails&id=601 to test.\n";
echo "Check for debug log files in the module directory.\n";
