<?php
/**
 * Temporary debug script to test what ClientAreaFooterOutput receives.
 * Run via: php _debug_hook.php
 * Or place in WHMCS and check the log file.
 */

// Simulate checking what WHMCS passes to ClientAreaFooterOutput
// This creates a small test file that logs the $vars keys

$testCode = <<<'PHP'
// Add this temporarily to hooks.php to debug
add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    $logFile = __DIR__ . '/debug_footer_hook.log';
    $data = [
        'time' => date('Y-m-d H:i:s'),
        'vars_keys' => array_keys($vars),
        'filename' => $vars['filename'] ?? 'NOT SET',
        'templatefile' => $vars['templatefile'] ?? 'NOT SET',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'NOT SET',
        'get_action' => $_GET['action'] ?? 'NOT SET',
        'get_id' => $_GET['id'] ?? 'NOT SET',
    ];
    file_put_contents($logFile, json_encode($data, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
    return '';
});
PHP;

echo "Add this debug hook temporarily to test:\n\n";
echo $testCode;
echo "\n\nThen visit the product details page and check debug_footer_hook.log\n";
