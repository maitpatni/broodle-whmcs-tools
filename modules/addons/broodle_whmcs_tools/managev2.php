<?php
/**
 * Broodle WHMCS Tools — Manage V2 (Standalone Page)
 *
 * Renders a self-contained service management page.
 * URL: modules/addons/broodle_whmcs_tools/managev2.php?id=SERVICE_ID
 */

// Bootstrap WHMCS
define('CLIENTAREA', true);
$whmcsRoot = realpath(__DIR__ . '/../../../');
$initFile = $whmcsRoot . '/init.php';
if (!file_exists($initFile)) { die('WHMCS init not found'); }
require_once $initFile;

use WHMCS\Database\Capsule;

// Auth check
if (!isset($_SESSION['uid']) || !$_SESSION['uid']) {
    header('Location: ../../../clientarea.php');
    exit;
}
$clientId = (int) $_SESSION['uid'];
$serviceId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$serviceId) { header('Location: ../../../clientarea.php'); exit; }

// Verify ownership
$service = Capsule::table('tblhosting')
    ->where('id', $serviceId)
    ->where('userid', $clientId)
    ->first();
if (!$service) { header('Location: ../../../clientarea.php'); exit; }

// Load module
if (!defined('BROODLE_TOOLS_VERSION')) {
    define('BROODLE_TOOLS_VERSION', '3.10.44');
}

// Load hooks helpers (setting checkers, data gatherers)
require_once __DIR__ . '/hooks.php';

$vars = ['serviceid' => $serviceId, 'userid' => $clientId];
$data = broodle_tools_gather_data($vars);
if (!$data) {
    header('Location: ../../../clientarea.php?action=productdetails&id=' . $serviceId);
    exit;
}

$jsData = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$version = BROODLE_TOOLS_VERSION;
$ts = time();

// Product info
$product = Capsule::table('tblproducts')->where('id', $service->packageid)->first();
$productName = $product ? $product->name : 'Service';
$domain = $service->domain ?: '';
$status = ucfirst($service->domainstatus);
$whmcsUrl = rtrim(\WHMCS\Config\Setting::getValue('SystemURL') ?: '/', '/');
$backUrl = $whmcsUrl . '/clientarea.php?action=productdetails&id=' . $serviceId;

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($productName); ?> — Manage Service</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#f3f4f6;--card-bg:#fff;--border-color:#e5e7eb;
    --heading-color:#111827;--text-color:#374151;--text-muted:#6b7280;
    --primary-color:#0a5ed3;--input-bg:#fff;
}
@media(prefers-color-scheme:dark){
    :root{
        --bg:#0f172a;--card-bg:#1e293b;--border-color:#334155;
        --heading-color:#f1f5f9;--text-color:#cbd5e1;--text-muted:#94a3b8;
        --primary-color:#3b82f6;--input-bg:#1e293b;
    }
}
body{font-family:'Inter',system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--text-color);line-height:1.5;min-height:100vh}
a{color:var(--primary-color);text-decoration:none}
a:hover{text-decoration:underline}

/* Top bar */
.mv2-topbar{background:var(--card-bg);border-bottom:1px solid var(--border-color);padding:12px 24px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:100}
.mv2-topbar-back{display:inline-flex;align-items:center;gap:6px;color:var(--text-muted);font-size:13px;font-weight:500;padding:6px 12px;border-radius:8px;border:1px solid var(--border-color);background:var(--card-bg);cursor:pointer;transition:all .15s}
.mv2-topbar-back:hover{color:var(--heading-color);border-color:var(--heading-color);text-decoration:none}
.mv2-topbar-title{font-size:15px;font-weight:600;color:var(--heading-color)}
.mv2-topbar-domain{font-size:13px;color:var(--text-muted);margin-left:4px}
.mv2-topbar-status{font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;margin-left:auto}
.mv2-topbar-status.active{background:#dcfce7;color:#166534}
.mv2-topbar-status.suspended{background:#fef3c7;color:#92400e}
.mv2-topbar-status.terminated{background:#fee2e2;color:#991b1b}

/* Main shell — bt_client.js will populate #bt-page-wrap */
.mv2-shell{max-width:1400px;margin:0 auto;padding:24px}
</style>
</head>
<body>

<div class="mv2-topbar">
    <a href="<?php echo htmlspecialchars($backUrl); ?>" class="mv2-topbar-back">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Back
    </a>
    <span class="mv2-topbar-title"><?php echo htmlspecialchars($productName); ?></span>
    <?php if ($domain): ?>
        <span class="mv2-topbar-domain"><?php echo htmlspecialchars($domain); ?></span>
    <?php endif; ?>
    <span class="mv2-topbar-status <?php echo strtolower($status); ?>"><?php echo htmlspecialchars($status); ?></span>
</div>

<div class="mv2-shell">
    <div id="bt-page-wrap"></div>
</div>

<div id="bt-data" style="display:none" data-config='<?php echo $jsData; ?>'></div>
<script src="bt_client.js?v=<?php echo $version; ?>&t=<?php echo $ts; ?>"></script>

</body>
</html>
