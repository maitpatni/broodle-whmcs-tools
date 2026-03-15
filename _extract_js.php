<?php
// Extract the JS code from hooks.php nowdoc into a standalone file
$content = file_get_contents('modules/addons/broodle_whmcs_tools/hooks.php');

// Find the nowdoc content between <<<'BTSCRIPT' and BTSCRIPT;
if (preg_match("/<<<'BTSCRIPT'\r?\n(.*?)\r?\nBTSCRIPT;/s", $content, $match)) {
    $js = $match[1];
    
    // The JS is wrapped in (function(){...})(); — we need to modify it slightly
    // to read config from window.__btConfig (set by the hook before this loads)
    // The existing code already does this in init(), so it should work as-is.
    
    file_put_contents('modules/addons/broodle_whmcs_tools/bt_client.js', $js);
    echo "Extracted " . strlen($js) . " bytes of JS\n";
    echo "First 200 chars:\n" . substr($js, 0, 200) . "\n";
    echo "Last 100 chars:\n" . substr($js, -100) . "\n";
} else {
    echo "NOWDOC not found!\n";
}
