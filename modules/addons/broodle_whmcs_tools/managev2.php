<?php
/**
 * Broodle WHMCS Tools — Manage V2 (Client Area Page)
 *
 * Renders inside the WHMCS client area with proper header/footer/theme.
 * URL: /managev2.php?id=SERVICE_ID  (installed at WHMCS root by module activation)
 *
 * Can also be accessed directly from the module directory — it auto-detects
 * the WHMCS root and adjusts paths accordingly.
 */

define('CLIENTAREA', true);

// Detect WHMCS root: if we're in the module dir, go up 3 levels
// If we're already at WHMCS root (symlinked/copied), use current dir
if (file_exists(__DIR__ . '/init.php')) {
    $whmcsRoot = __DIR__;
} else {
    $whmcsRoot = realpath(__DIR__ . '/../../../');
}

require_once $whmcsRoot . '/init.php';
require_once $whmcsRoot . '/includes/clientfunctions.php';

use WHMCS\Database\Capsule;
use WHMCS\ClientArea;

$ca = new ClientArea();
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
    define('BROODLE_TOOLS_VERSION', '3.10.54');
}

$hooksFile = $whmcsRoot . '/modules/addons/broodle_whmcs_tools/hooks.php';
if (file_exists($hooksFile)) {
    require_once $hooksFile;
} else {
    require_once __DIR__ . '/hooks.php';
}

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

/* ── Build page content ── */
$btHtml  = '<div id="bt-page-wrap"></div>' . "\n";
$btHtml .= '<script>window.__btConfig=JSON.parse(atob("' . base64_encode(json_encode($data)) . '"));</script>' . "\n";
$btHtml .= '<script src="modules/addons/broodle_whmcs_tools/bt_client.js?v=' . $version . '&t=' . $ts . '"></script>' . "\n";

$ca->assign('bt_content', $btHtml);

/* ── Ensure template exists ── */
$templateName = '';
try {
    $templateName = Capsule::table('tblconfiguration')
        ->where('setting', 'Template')
        ->value('value');
} catch (\Exception $e) {}
if (empty($templateName)) $templateName = 'lagom2';

$tplDir  = $whmcsRoot . '/templates/' . $templateName;
$tplFile = $tplDir . '/managev2.tpl';

if (!file_exists($tplFile)) {
    @file_put_contents($tplFile, '{$bt_content nofilter}');
}

$ca->setTemplate('managev2');
$ca->output();
