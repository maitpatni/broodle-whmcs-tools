<?php
$configFile = 'C:/aapanel/BtSoft/wwwroot/whmcs/configuration.php';
require_once $configFile;

$pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_username, $db_password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check ALL module rows
$stmt = $pdo->query("SELECT * FROM tbladdonmodules WHERE module = 'broodle_whmcs_tools'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "All tbladdonmodules rows for broodle_whmcs_tools:" . PHP_EOL;
foreach ($rows as $r) {
    echo "  " . json_encode($r) . PHP_EOL;
}

// Check the settings that are missing
echo PHP_EOL . "Missing settings check:" . PHP_EOL;
$needed = ['tweak_ssl_management', 'tweak_dns_management'];
foreach ($needed as $key) {
    $stmt = $pdo->prepare("SELECT * FROM mod_broodle_tools_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  $key: " . ($row ? $row['setting_value'] : 'MISSING') . PHP_EOL;
}

// Now let's actually test what the hook returns by simulating it
echo PHP_EOL . "Testing hook output simulation..." . PHP_EOL;

// Check if broodle_tools_get_cpanel_service would work for service 89
$service = $pdo->query("SELECT * FROM tblhosting WHERE id = 89")->fetch(PDO::FETCH_ASSOC);
echo "Service 89 domain: " . $service['domain'] . PHP_EOL;
echo "Service 89 packageid: " . $service['packageid'] . PHP_EOL;
echo "Service 89 server: " . $service['server'] . PHP_EOL;

$product = $pdo->query("SELECT * FROM tblproducts WHERE id = " . $service['packageid'])->fetch(PDO::FETCH_ASSOC);
echo "Product servertype: " . $product['servertype'] . PHP_EOL;

$server = $pdo->query("SELECT * FROM tblservers WHERE id = " . $service['server'])->fetch(PDO::FETCH_ASSOC);
echo "Server exists: " . ($server ? 'yes' : 'no') . PHP_EOL;
if ($server) {
    echo "Server hostname: " . $server['hostname'] . PHP_EOL;
    echo "Server has accesshash: " . (!empty($server['accesshash']) ? 'yes (' . strlen($server['accesshash']) . ' chars)' : 'no') . PHP_EOL;
    echo "Server has password: " . (!empty($server['password']) ? 'yes' : 'no') . PHP_EOL;
}

// Now let's fetch the actual page HTML to see what's rendered
echo PHP_EOL . "Fetching client area page..." . PHP_EOL;
$ch = curl_init();
// First, we need to login as a client
$loginUrl = "https://host.broodle.host/clientarea.php";
$detailsUrl = "https://host.broodle.host/clientarea.php?action=productdetails&id=89";

// Try fetching the page source to see if hook output is present
curl_setopt_array($ch, [
    CURLOPT_URL => $detailsUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 15,
]);
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP status: $code" . PHP_EOL;
echo "Page length: " . strlen($html) . " chars" . PHP_EOL;
echo "Contains bt-data: " . (strpos($html, 'bt-data') !== false ? 'YES' : 'NO') . PHP_EOL;
echo "Contains bt-wrap: " . (strpos($html, 'bt-wrap') !== false ? 'YES' : 'NO') . PHP_EOL;
echo "Contains broodle: " . (strpos($html, 'broodle') !== false ? 'YES' : 'NO') . PHP_EOL;
echo "Contains section-hook-output: " . (strpos($html, 'section-hook-output') !== false ? 'YES' : 'NO') . PHP_EOL;
echo "Contains hideDefaultTabs: " . (strpos($html, 'hideDefaultTabs') !== false ? 'YES' : 'NO') . PHP_EOL;
echo "Contains login form: " . (strpos($html, 'loginform') !== false || strpos($html, 'inputEmail') !== false ? 'YES (redirected to login)' : 'NO') . PHP_EOL;
