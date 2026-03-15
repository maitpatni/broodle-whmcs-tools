<?php
$file = 'C:/aapanel/BtSoft/wwwroot/whmcs/modules/addons/broodle_whmcs_tools/hooks.php';
$content = file_get_contents($file);

// Find the comment "// Only run on the product details page" and insert debug before it
$marker = '// Only run on the product details page';
$pos = strpos($content, $marker);
if ($pos === false) {
    echo "Marker not found!\n";
    exit(1);
}

$debugCode = '// TEMPORARY DEBUG - remove after testing
    $debugLog = __DIR__ . \'/debug_hook.log\';
    $dd = date(\'Y-m-d H:i:s\') . \' | FIRED | filename=\' . ($vars[\'filename\'] ?? \'NOTSET\') . \' | templatefile=\' . ($vars[\'templatefile\'] ?? \'NOTSET\') . \' | URI=\' . ($_SERVER[\'REQUEST_URI\'] ?? \'NOTSET\') . "\n";
    file_put_contents($debugLog, $dd, FILE_APPEND);

    ';

$content = substr($content, 0, $pos) . $debugCode . substr($content, $pos);
file_put_contents($file, $content);
echo "Debug code inserted at position $pos\n";

// Verify syntax
$output = [];
$ret = 0;
exec('"C:/aapanel/BtSoft/php/83/php.exe" -l "' . $file . '" 2>&1', $output, $ret);
echo "PHP lint: " . implode("\n", $output) . "\n";
echo "Exit code: $ret\n";
