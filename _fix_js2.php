<?php
$file = 'modules/addons/broodle_whmcs_tools/bt_client.js';
$content = file_get_contents($file);

// Find all \\ patterns and their context
preg_match_all('/\\\\\\\\/', $content, $matches, PREG_OFFSET_CAPTURE);
echo "Found " . count($matches[0]) . " double-backslash occurrences\n";

// Show unique contexts (5 chars before and after)
$contexts = [];
foreach ($matches[0] as $m) {
    $pos = $m[1];
    $start = max(0, $pos - 10);
    $end = min(strlen($content), $pos + 12);
    $ctx = substr($content, $start, $end - $start);
    $ctx = str_replace(["\n", "\r"], ['\\n', '\\r'], $ctx);
    $contexts[] = $ctx;
}

// Show unique contexts
$unique = array_unique($contexts);
echo "Unique contexts (" . count($unique) . "):\n";
$i = 0;
foreach ($unique as $ctx) {
    echo "  " . json_encode($ctx) . "\n";
    if (++$i > 20) { echo "  ... and more\n"; break; }
}

// The \\\\ in the nowdoc represents literal \\ in the output.
// In a standalone JS file, \\\\ means two literal backslashes.
// But in the original code, these were meant to be single backslashes
// used as regex escapes in JS (like \\d, \\., etc.)
// So \\\\ should become \\ in the .js file

// Let's check: are these in regex patterns?
preg_match_all('/\\\\\\\\[a-zA-Z.]/', $content, $regexMatches);
echo "\nDouble-backslash + letter/dot patterns:\n";
$regexContexts = [];
foreach ($regexMatches[0] as $m) {
    $regexContexts[$m] = ($regexContexts[$m] ?? 0) + 1;
}
foreach ($regexContexts as $pat => $count) {
    echo "  " . json_encode($pat) . " x $count\n";
}
