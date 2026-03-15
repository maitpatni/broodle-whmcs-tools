<?php
// Add debug logging to the deployed hooks.php
$file = 'C:/aapanel/BtSoft/wwwroot/whmcs/modules/addons/broodle_whmcs_tools/hooks.php';
$content = file_get_contents($file);

// Add debug at the very TOP of the file, right after the WHMCS check
$marker = "use WHMCS\\Database\\Capsule;\nuse WHMCS\\View\\Menu\\Item as MenuItem;";
$debug = "use WHMCS\\Database\\Capsule;\nuse WHMCS\\View\\Menu\\Item as MenuItem;\n\n// TEMPORARY TOP-LEVEL DEBUG\nfile_put_contents(__DIR__ . '/debug_load.log', date('Y-m-d H:i:s') . \" | hooks.php LOADED | URI=\" . (\$_SERVER['REQUEST_URI'] ?? 'N/A') . \"\\n\", FILE_APPEND);";

if (strpos($content, $marker) !== false) {
    $content = str_replace($marker, $debug, $content);
    echo "Top-level debug added\n";
} else {
    echo "Top-level marker not found\n";
    // Try with \r\n
    $marker2 = str_replace("\n", "\r\n", $marker);
    if (strpos($content, $marker2) !== false) {
        $debug2 = str_replace("\n", "\r\n", $debug);
        $content = str_replace($marker2, $debug2, $content);
        echo "Top-level debug added (CRLF)\n";
    } else {
        echo "CRLF marker also not found\n";
    }
}

// Also add debug inside the HeadOutput hook
$marker3 = "function broodle_tools_is_product_details_page";
$pos3 = strpos($content, $marker3);
if ($pos3 !== false) {
    $debugHead = "// TEMPORARY HEAD HOOK DEBUG\nadd_hook('ClientAreaHeadOutput', 0, function (\$vars) {\n    file_put_contents(__DIR__ . '/debug_head.log', date('Y-m-d H:i:s') . \" | HeadOutput | filename=\" . (\$vars['filename'] ?? 'NOTSET') . \" | templatefile=\" . (\$vars['templatefile'] ?? 'NOTSET') . \" | URI=\" . (\$_SERVER['REQUEST_URI'] ?? 'N/A') . \"\\n\", FILE_APPEND);\n    return '';\n});\n\nadd_hook('ClientAreaFooterOutput', 0, function (\$vars) {\n    file_put_contents(__DIR__ . '/debug_footer.log', date('Y-m-d H:i:s') . \" | FooterOutput | filename=\" . (\$vars['filename'] ?? 'NOTSET') . \" | templatefile=\" . (\$vars['templatefile'] ?? 'NOTSET') . \" | URI=\" . (\$_SERVER['REQUEST_URI'] ?? 'N/A') . \"\\n\", FILE_APPEND);\n    return '';\n});\n\n";
    $content = substr($content, 0, $pos3) . $debugHead . substr($content, $pos3);
    echo "Hook debug added\n";
} else {
    echo "Hook marker not found\n";
}

file_put_contents($file, $content);

// Verify syntax
echo "\nVerifying syntax...\n";
$tmpFile = tempnam(sys_get_temp_dir(), 'php_lint_');
file_put_contents($tmpFile, $content);
$output = [];
// Can't use exec, so just check file size
echo "File size: " . strlen($content) . " bytes\n";
echo "Has debug_load: " . (strpos($content, 'debug_load') !== false ? 'YES' : 'NO') . "\n";
echo "Has debug_head: " . (strpos($content, 'debug_head') !== false ? 'YES' : 'NO') . "\n";
echo "Has debug_footer: " . (strpos($content, 'debug_footer') !== false ? 'YES' : 'NO') . "\n";
