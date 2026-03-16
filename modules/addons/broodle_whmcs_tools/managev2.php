<?php
/**
 * Broodle WHMCS Tools — Manage V2 (Client Area Page)
 *
 * Renders inside the WHMCS client area with proper header/footer/theme.
 * URL: modules/addons/broodle_whmcs_tools/managev2.php?id=SERVICE_ID
 */

define('CLIENTAREA', true);

$whmcsRoot = realpath(__DIR__ . '/../../../');
require_once $whmcsRoot . '/init.php';
require_once $whmcsRoot . '/includes/clientfunctions.php';

use WHMCS\Database\Capsule;

/* ── Auth ── */
$ca = new WHMCS\ClientArea();
$ca->initPage();
$ca->requireLogin();

$clientId  = (int) ($_SESSION['uid'] ?? 0);
$serviceId = (int) ($_GET['id'] ?? 0);
if (!$clientId || !$serviceId) {
    header('Location: clientarea.php');
    exit;
}

/* ── Verify ownership ── */
$service = Capsule::table('tblhosting')
    ->where('id', $serviceId)
    ->where('userid', $clientId)
    ->first();
if (!$service) {
    header('Location: clientarea.php');
    exit;
}

/* ── Gather data ── */
if (!defined('BROODLE_TOOLS_VERSION')) {
    define('BROODLE_TOOLS_VERSION', '3.10.46');
}
require_once __DIR__ . '/hooks.php';

$vars = ['serviceid' => $serviceId, 'userid' => $clientId];
$data = broodle_tools_gather_data($vars);
if (!$data) {
    header('Location: clientarea.php?action=productdetails&id=' . $serviceId);
    exit;
}

$jsData  = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$version = BROODLE_TOOLS_VERSION;
$ts      = time();

$product     = Capsule::table('tblproducts')->where('id', $service->packageid)->first();
$productName = $product ? $product->name : 'Service';

/* ── Page setup ── */
$ca->setPageTitle(htmlspecialchars($productName) . ' — Manage V2');
$ca->addToBreadCrumb('index.php', 'Home');
$ca->addToBreadCrumb('clientarea.php', 'Client Area');
$ca->addToBreadCrumb('clientarea.php?action=productdetails&id=' . $serviceId, htmlspecialchars($productName));
$ca->addToBreadCrumb('#', 'Manage V2');

$btContent = '<div id="bt-page-wrap"></div>';

// Script tags are added after $ca->output() to avoid Smarty parsing issues with JSON curly braces
$scriptTags  = '<script>window.__btConfig=' . $jsData . ';</script>';
$scriptTags .= '<script src="modules/addons/broodle_whmcs_tools/bt_client.js?v=' . $version . '&t=' . $ts . '"></script>';

/* ── Determine active template ── */
$templateName = '';
try {
    $templateName = Capsule::table('tblconfiguration')
        ->where('setting', 'Template')
        ->value('value');
} catch (\Exception $e) {}
if (empty($templateName)) $templateName = 'lagom2';

$tplDir  = $whmcsRoot . '/templates/' . $templateName;
$tplFile = $tplDir . '/managev2.tpl';

/* ── Create template file if missing ── */
if (!file_exists($tplFile)) {
    // For Lagom2 and most modern themes, the page content goes in the main body area.
    // We just need a template that outputs our content variable.
    // Lagom2 wraps this automatically with header/footer/sidebar via its master layout.
    $tplContent = '{$bt_content}';
    $written = @file_put_contents($tplFile, $tplContent);
    if ($written === false) {
        // Cannot write template — render standalone
        managev2_standalone($btContent, $scriptTags, $productName);
        exit;
    }
}

$ca->assign('bt_content', $btContent);
$ca->setTemplate('managev2');

// Capture output, inject our scripts before </body>, then send
ob_start();
$ca->output();
$html = ob_get_clean();

// If WHMCS output is empty, fall back to standalone render
if (empty(trim($html))) {
    managev2_standalone($btContent, $scriptTags, $productName);
    exit;
}

// Inject config + JS before closing </body>
$pos = strripos($html, '</body>');
if ($pos !== false) {
    $html = substr($html, 0, $pos) . $scriptTags . substr($html, $pos);
} else {
    // No </body> found — just append
    $html .= $scriptTags;
}
echo $html;
exit;

/**
 * Fallback: render page without WHMCS template engine.
 * Still loads inside a basic HTML shell with theme CSS.
 */
function managev2_standalone($content, $scripts, $title)
{
    $sysUrl = '/';
    try {
        $sysUrl = \WHMCS\Config\Setting::getValue('SystemURL') ?: '/';
    } catch (\Exception $e) {}

    echo '<!DOCTYPE html><html lang="en"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . htmlspecialchars($title) . ' — Manage V2</title>';
    echo '<link rel="stylesheet" href="' . $sysUrl . 'templates/lagom2/css/all.min.css">';
    echo '<link rel="stylesheet" href="' . $sysUrl . 'assets/css/fontawesome-all.min.css">';
    echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;margin:0;padding:20px;background:#f5f7fa}</style>';
    echo '</head><body>';
    echo '<div style="max-width:1200px;margin:0 auto">';
    echo '<p><a href="clientarea.php">&larr; Back to Client Area</a></p>';
    echo $content;
    echo '</div>';
    echo $scripts;
    echo '</body></html>';
}
