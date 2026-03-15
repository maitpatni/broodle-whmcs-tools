<?php
// The bt_client.js was extracted from a PHP nowdoc via git show which
// doubled all backslashes. We need to halve them all.
// In PHP nowdoc, content is literal — no escaping. So the nowdoc content
// IS the exact JS that goes inside <script> tags.
// But git show on Windows may have mangled the encoding.

// Better approach: extract directly from the current hooks.php using PHP
$hooks = file_get_contents('modules/addons/broodle_whmcs_tools/hooks.php');

// Find the nowdoc content
if (preg_match("/<<<'BTSCRIPT'\r?\n(.*?)\r?\nBTSCRIPT;/s", $hooks, $match)) {
    $js = $match[1];
    echo "Extracted " . strlen($js) . " bytes from hooks.php nowdoc\n";
    
    // Check line 23 area
    $lines = explode("\n", $js);
    echo "Total lines: " . count($lines) . "\n";
    echo "Line 23: " . substr($lines[22] ?? 'N/A', 0, 80) . "\n";
    
    // Check for double backslashes
    $dblBackslash = substr_count($js, '\\\\');
    echo "Double backslashes: $dblBackslash\n";
    
    // Check for \x27 patterns
    $hex27 = substr_count($js, '\\x27');
    echo "\\x27 patterns: $hex27\n";
    
    // Check for \\x27 patterns  
    $dblHex27 = substr_count($js, '\\\\x27');
    echo "\\\\x27 patterns: $dblHex27\n";
    
    file_put_contents('modules/addons/broodle_whmcs_tools/bt_client.js', $js);
    echo "\nWrote bt_client.js\n";
    
    // Verify first few lines
    echo "\nFirst 5 lines:\n";
    for ($i = 0; $i < 5; $i++) {
        echo "  " . ($lines[$i] ?? '') . "\n";
    }
} else {
    echo "NOWDOC not found in hooks.php!\n";
    
    // Debug: show what's around the BTSCRIPT marker
    $pos = strpos($hooks, 'BTSCRIPT');
    if ($pos !== false) {
        echo "Found BTSCRIPT at position $pos\n";
        echo "Context: " . substr($hooks, $pos - 20, 60) . "\n";
    }
}
