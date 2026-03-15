<?php
// Extract JS from hooks.php and check brace balance
$content = file_get_contents('modules/addons/broodle_whmcs_tools/hooks.php');
$start = strpos($content, "return <<<'BTSCRIPT'");
$end = strpos($content, 'BTSCRIPT;', $start + 20);
if ($start === false || $end === false) {
    echo "Could not find BTSCRIPT markers\n";
    exit(1);
}
$js = substr($content, $start, $end - $start);

// Count braces
$open = substr_count($js, '{');
$close = substr_count($js, '}');
echo "JS braces: { = $open, } = $close\n";
echo "Balanced: " . ($open === $close ? 'YES' : 'NO - MISMATCH!') . "\n";

// Count parens
$openP = substr_count($js, '(');
$closeP = substr_count($js, ')');
echo "JS parens: ( = $openP, ) = $closeP\n";
echo "Balanced: " . ($openP === $closeP ? 'YES' : 'NO - MISMATCH!') . "\n";

// Count brackets
$openB = substr_count($js, '[');
$closeB = substr_count($js, ']');
echo "JS brackets: [ = $openB, ] = $closeB\n";
echo "Balanced: " . ($openB === $closeB ? 'YES' : 'NO - MISMATCH!') . "\n";

// Check for the init function
echo "\nHas window.__btConfig check: " . (strpos($js, '__btConfig') !== false ? 'YES' : 'NO') . "\n";
echo "Has buildModalsHtml: " . (strpos($js, 'buildModalsHtml') !== false ? 'YES' : 'NO') . "\n";
echo "Has DOMContentLoaded: " . (strpos($js, 'DOMContentLoaded') !== false ? 'YES' : 'NO') . "\n";
