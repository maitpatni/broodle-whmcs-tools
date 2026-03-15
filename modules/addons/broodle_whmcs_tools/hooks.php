<?php
/**
 * Broodle WHMCS Tools — Hooks (TEST VERSION)
 *
 * This is a diagnostic version that prints visible markers from every
 * possible hook point. Check the product details page to see which
 * markers appear, then we know exactly where to inject our real code.
 *
 * @package    BroodleWHMCSTools
 * @author     Broodle
 * @link       https://broodle.host
 * @version    TEST-3.10.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

use WHMCS\Database\Capsule;
use WHMCS\View\Menu\Item as MenuItem;

/*
 * ============================================================
 *  MARKER STYLE — bright colored banners so they're impossible
 *  to miss on the page, no matter where they render.
 * ============================================================
 */
$_btMarkerCSS = '<style>.bt-marker{padding:12px 20px;margin:10px 0;border-radius:6px;font-family:monospace;font-size:14px;font-weight:700;color:#fff;text-align:center;}.bt-m-red{background:#dc2626;}.bt-m-blue{background:#2563eb;}.bt-m-green{background:#059669;}.bt-m-orange{background:#d97706;}.bt-m-purple{background:#7c3aed;}.bt-m-pink{background:#db2777;}.bt-m-teal{background:#0d9488;}.bt-m-indigo{background:#4f46e5;}</style>';

function bt_marker($label, $color) {
    return '<div class="bt-marker bt-m-' . $color . '">🔵 BROODLE TEST: ' . htmlspecialchars($label) . '</div>';
}

/*
 * ============================================================
 *  HOOK 1: ClientAreaHeadOutput
 *  Injects into <head> via {$headoutput} in header.tpl
 * ============================================================
 */
add_hook('ClientAreaHeadOutput', 1, function ($vars) {
    global $_btMarkerCSS;
    $page = $vars['filename'] ?? ($vars['templatefile'] ?? 'unknown');
    // CSS goes in head always, plus an HTML comment marker
    return $_btMarkerCSS . '<!-- BT_TEST_HEAD_OUTPUT page=' . $page . ' -->';
});

/*
 * ============================================================
 *  HOOK 2: ClientAreaFooterOutput
 *  Injects before </body> via {$footeroutput} in footer.tpl
 * ============================================================
 */
add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    $page = $vars['filename'] ?? ($vars['templatefile'] ?? 'unknown');
    return bt_marker('HOOK: ClientAreaFooterOutput | page=' . $page, 'blue');
});

/*
 * ============================================================
 *  HOOK 3: ClientAreaPage
 *  Fires on every client area page. Can add template variables.
 *  Returns array of vars to assign to Smarty.
 * ============================================================
 */
add_hook('ClientAreaPage', 1, function ($vars) {
    // This hook can't output HTML directly, but we can set a Smarty var
    return ['bt_test_marker' => 'HOOK_ClientAreaPage_FIRED'];
});

/*
 * ============================================================
 *  HOOK 4: ClientAreaPageProductDetails
 *  Fires specifically on the product details page.
 *  Returns array of vars to assign to Smarty.
 * ============================================================
 */
add_hook('ClientAreaPageProductDetails', 1, function ($vars) {
    return ['bt_test_product_marker' => 'HOOK_ClientAreaPageProductDetails_FIRED'];
});

/*
 * ============================================================
 *  HOOK 5: ClientAreaProductDetailsOutput
 *  Injects HTML into the product details page output area.
 *  This is rendered via {foreach $hookOutput} in the template.
 * ============================================================
 */
add_hook('ClientAreaProductDetailsOutput', 1, function ($vars) {
    return bt_marker('HOOK: ClientAreaProductDetailsOutput | svcid=' . ($vars['serviceid'] ?? 'N/A'), 'green');
});

/*
 * ============================================================
 *  HOOK 6: AdminAreaHeadOutput (admin only — should NOT show
 *  on client area, but included as control test)
 * ============================================================
 */
add_hook('AdminAreaHeadOutput', 1, function ($vars) {
    return '<!-- BT_TEST_ADMIN_HEAD -->';
});

/*
 * ============================================================
 *  HOOK 7: ClientAreaHeaderOutput
 *  Some WHMCS versions support this — injects in header area.
 * ============================================================
 */
add_hook('ClientAreaHeaderOutput', 1, function ($vars) {
    return bt_marker('HOOK: ClientAreaHeaderOutput', 'orange');
});

/*
 * ============================================================
 *  HOOK 8: ClientAreaPrimarySidebar
 *  Modifies the primary (left) sidebar. We'll add a panel.
 * ============================================================
 */
add_hook('ClientAreaPrimarySidebar', 1, function (MenuItem $sidebar) {
    $page = $_GET['action'] ?? '';
    if ($page !== 'productdetails') return;

    $panel = $sidebar->addChild('bt_test_sidebar', [
        'label' => '🔵 Broodle Test Sidebar',
        'order' => 1,
    ]);
    $panel->addChild('bt_test_sidebar_item', [
        'label' => 'HOOK: ClientAreaPrimarySidebar FIRED',
        'uri' => '#',
        'order' => 1,
    ]);
});

/*
 * ============================================================
 *  HOOK 9: ClientAreaSecondarySidebar
 *  Modifies the secondary (right) sidebar.
 * ============================================================
 */
add_hook('ClientAreaSecondarySidebar', 1, function (MenuItem $sidebar) {
    $page = $_GET['action'] ?? '';
    if ($page !== 'productdetails') return;

    $panel = $sidebar->addChild('bt_test_sidebar2', [
        'label' => '🔵 Broodle Test Secondary',
        'order' => 1,
    ]);
    $panel->addChild('bt_test_sidebar2_item', [
        'label' => 'HOOK: ClientAreaSecondarySidebar FIRED',
        'uri' => '#',
        'order' => 1,
    ]);
});

/*
 * ============================================================
 *  HOOK 10: ClientAreaPrimaryNavbar
 *  Adds item to the primary navigation bar.
 * ============================================================
 */
add_hook('ClientAreaPrimaryNavbar', 1, function (MenuItem $navbar) {
    $navbar->addChild('bt_test_nav', [
        'label' => '🔵 BT-TEST',
        'uri' => '#bt-test',
        'order' => 9999,
    ]);
});

/*
 * ============================================================
 *  HOOK 11: ClientAreaSecondaryNavbar
 *  Adds item to the secondary navigation bar.
 * ============================================================
 */
add_hook('ClientAreaSecondaryNavbar', 1, function (MenuItem $navbar) {
    $navbar->addChild('bt_test_nav2', [
        'label' => '🔵 BT-TEST-2',
        'uri' => '#bt-test-2',
        'order' => 9999,
    ]);
});
