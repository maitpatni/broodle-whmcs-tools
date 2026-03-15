<?php
// Check modulefunctions.php for addon hook loading logic
$content = file_get_contents('C:/aapanel/BtSoft/wwwroot/whmcs/includes/modulefunctions.php');

echo "=== modulefunctions.php analysis ===\n";
echo "Size: " . strlen($content) . " bytes\n";

// Check if it's encoded
$isEncoded = strpos($content, 'ionCube') !== false || strpos($content, 'sg_load') !== false;
echo "Encoded: " . ($isEncoded ? "YES" : "NO") . "\n";

if (!$isEncoded) {
    // Search for addon hook loading
    $lines = explode("\n", $content);
    echo "Total lines: " . count($lines) . "\n\n";
    
    // Find references to hooks.php loading for addons
    foreach ($lines as $i => $line) {
        if (preg_match('/hooks\.php|add_hook|loadHook|registerHook|addonHook/i', $line)) {
            $start = max(0, $i - 2);
            $end = min(count($lines) - 1, $i + 2);
            for ($j = $start; $j <= $end; $j++) {
                echo "L" . ($j+1) . ": " . rtrim($lines[$j]) . "\n";
            }
            echo "---\n";
        }
    }
    
    // Find function definitions
    echo "\n=== Functions in modulefunctions.php ===\n";
    preg_match_all('/function\s+(\w+)\s*\(/', $content, $funcs);
    foreach ($funcs[1] as $f) {
        if (preg_match('/addon|hook|module/i', $f)) {
            echo "  $f()\n";
        }
    }
}

// Also check Module/Addon.php
echo "\n=== Module/Addon.php ===\n";
$addonFile = 'C:/aapanel/BtSoft/wwwroot/whmcs/vendor/whmcs/whmcs-foundation/lib/Module/Addon.php';
$content2 = file_get_contents($addonFile);
$isEncoded2 = strpos($content2, 'ionCube') !== false || strpos($content2, 'sg_load') !== false;
echo "Encoded: " . ($isEncoded2 ? "YES" : "NO") . "\n";
echo "Size: " . strlen($content2) . " bytes\n";

// Check Module/Module.php
echo "\n=== Module/Module.php ===\n";
$moduleFile = 'C:/aapanel/BtSoft/wwwroot/whmcs/vendor/whmcs/whmcs-foundation/lib/Module/Module.php';
$content3 = file_get_contents($moduleFile);
$isEncoded3 = strpos($content3, 'ionCube') !== false || strpos($content3, 'sg_load') !== false;
echo "Encoded: " . ($isEncoded3 ? "YES" : "NO") . "\n";
echo "Size: " . strlen($content3) . " bytes\n";

// Let's try a completely different approach: 
// Create a minimal test hook file in includes/hooks/ to see if THAT gets loaded
echo "\n=== Alternative: includes/hooks/ approach ===\n";
echo "The README says files in includes/hooks/ are auto-loaded.\n";
echo "Files starting with _ are NOT loaded.\n";
echo "We could place a hook file there instead of relying on addon module hook loading.\n";

// Check what other addon modules have hooks
echo "\n=== Other addon modules with hooks.php ===\n";
$addonsDir = 'C:/aapanel/BtSoft/wwwroot/whmcs/modules/addons';
foreach (scandir($addonsDir) as $addon) {
    if ($addon === '.' || $addon === '..') continue;
    $hooksFile = $addonsDir . '/' . $addon . '/hooks.php';
    if (file_exists($hooksFile)) {
        $size = filesize($hooksFile);
        $content = file_get_contents($hooksFile);
        $isEnc = strpos($content, 'ionCube') !== false || strpos($content, 'sg_load') !== false;
        echo "  $addon/hooks.php ($size bytes" . ($isEnc ? ", ionCube" : ", plain") . ")\n";
        
        // Check if it has the WHMCS defined check
        if (!$isEnc && strpos($content, "defined('WHMCS')") !== false) {
            echo "    Has WHMCS defined check\n";
        }
    }
}

// Check tbladdonmodules for ALL active addons
echo "\n=== All active addon modules ===\n";
$pdo = new PDO('mysql:host=localhost;dbname=whmcs', 'whmcs', 'jMiHiXK7L6A5MMeK');
$stmt = $pdo->query("SELECT DISTINCT module FROM tbladdonmodules");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $mod = $row['module'];
    $hasHooks = file_exists($addonsDir . '/' . $mod . '/hooks.php');
    echo "  $mod" . ($hasHooks ? " [has hooks.php]" : "") . "\n";
}
