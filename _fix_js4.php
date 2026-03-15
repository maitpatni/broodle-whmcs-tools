<?php
$file = 'modules/addons/broodle_whmcs_tools/bt_client.js';
$content = file_get_contents($file);

// Replace all \\\\ with \\ (double backslash → single backslash)
// In the file, \\\\ is stored as two backslash characters
// We want to halve them to single backslash characters
$fixed = str_replace('\\\\', '\\', $content);

echo "Before: " . strlen($content) . " bytes, " . substr_count($content, '\\\\') . " double-backslashes\n";
echo "After: " . strlen($fixed) . " bytes, " . substr_count($fixed, '\\\\') . " double-backslashes\n";

// Check line 23
$lines = explode("\n", $fixed);
echo "Line 23: " . substr($lines[22], 0, 100) . "\n";

// Check \x27 is now single-escaped
echo "\\x27 count: " . substr_count($fixed, '\\x27') . "\n";
echo "\\\\x27 count: " . substr_count($fixed, '\\\\x27') . "\n";

file_put_contents($file, $fixed);
echo "\nFixed and saved.\n";
