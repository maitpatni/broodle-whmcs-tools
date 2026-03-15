<?php
$file = 'modules/addons/broodle_whmcs_tools/bt_client.js';
$content = file_get_contents($file);

// The JS was extracted from a PHP nowdoc. In the nowdoc, \\" is literal \\"
// But in a standalone .js file, \\" means: escaped backslash + end of string = syntax error
// We need to replace \\" with \" (just an escaped quote)
$count = substr_count($content, '\\\\"');
echo "Found $count occurrences of \\\\\"\n";

$fixed = str_replace('\\\\"', '\\"', $content);
file_put_contents($file, $fixed);

// Verify by checking line 23
$lines = explode("\n", $fixed);
echo "Line 23: " . substr($lines[22], 0, 80) . "\n";
echo "New size: " . strlen($fixed) . " bytes\n";

// Also check for any other problematic escaping
$otherIssues = preg_match_all('/\\\\\\\\[^n\\\\]/', $fixed, $matches);
echo "Other double-backslash patterns: $otherIssues\n";
if ($otherIssues) {
    foreach (array_unique($matches[0]) as $m) {
        echo "  Pattern: " . json_encode($m) . "\n";
    }
}
