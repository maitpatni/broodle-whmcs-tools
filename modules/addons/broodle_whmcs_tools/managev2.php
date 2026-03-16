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
use WHMCS\ClientArea;

$ca = new ClientArea();
$ca->setPageTitle('Manage Service');
$ca->addToBreadCrumb('index.php', 'Home');
$ca->addToBreadCrumb('clientarea.php', 'Client Area');
$ca->initPage();
$ca->requireLogin();

$clientId = (int) ($_SESSION['uid'] ?? 0);
$serviceId = (int) ($_GET['id'] ?? 0);
if (!$clientId || !$serviceId) {
    header('Location: ' . $whmcsRoot . '/clientarea.php');
    exit;
}

$service = Capsule::table('tblhosting')
    ->where('id', $serviceId)
    ->where('userid', $clientId)
    ->first();
if (!$service) {
    header('Location: ' . $whmcsRoot . '/clientarea.php');
    exit;
}

if (!defined('BROODLE_TOOLS_VERSION')) {
    define('BROODLE_TOOLS_VERSION', '3.10.45');
}
require_once __DIR__ . '/hooks.php';

$vars = ['serviceid' => $serviceId, 'userid' => $clientId];
$data = broodle_tools_gather_data($vars);
if (!$data) {
    header('Location: ' . $whmcsRoot . '/clientarea.php?action=productdetails&id=' . $serviceId);
    exit;
}

$jsData = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$version = BROODLE_TOOLS_VERSION;
$ts = time();

$product = Capsule::table('tblproducts')->where('id', $service->packageid)->first();
$productName = $product ? $product->name : 'Service';
$ca->setPageTitle(htmlspecialchars($productName) . ' — Manage V2');
$ca->addToBreadCrumb('clientarea.php?action=productdetails&id=' . $serviceId, htmlspecialchars($productName));
$ca->addToBreadCrumb('#', 'Manage V2');

// Assign content to a generic template variable
$html  = '<div id="bt-page-wrap"></div>';
$html .= '<div id="bt-data" style="display:none" data-config=\'' . $jsData . '\'></div>';
$html .= '<script src="modules/addons/broodle_whmcs_tools/bt_client.js?v=' . $version . '&t=' . $ts . '"></script>';

$ca->assign('content', $html);

// Determine the active template directory safely
$templateName = '';
try {
    // WHMCS 7.x+
    if (method_exists($ca, 'getTemplateName')) {
        $templateName = $ca->getTemplateName();
    }
} catch (\Exception $e) {}

// Fallback: read from WHMCS system settings
if (empty($templateName)) {
    try {
        $templateName = \WHMCS\Database\Capsule::table('tblconfiguration')
            ->where('setting', 'Template')
            ->value('value') ?: 'lagom2';
    } catch (\Exception $e) {
        $templateName = 'lagom2';
    }
}

// Create a minimal template that just outputs {$content} if it doesn't exist
$templateDir = $whmcsRoot . '/templates/' . $templateName;
$tplFile = $templateDir . '/managev2.tpl';
if (!file_exists($tplFile)) {
    @file_put_contents($tplFile, '{$content}');
}
$ca->setTemplate('managev2');
$ca->output();
