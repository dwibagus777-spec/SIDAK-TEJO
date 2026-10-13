<?php

require_once 'e:/XAMPP/htdocs/SIDAK TEJO/vendor/autoload.php';

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';

function callProdApi(string $urlPath, string $cookieFile): array {
    $cmd = sprintf(
        'curl.exe -s -k --resolve sidaktejo.site:443:91.108.119.5 -b %s --max-time 15 "https://sidaktejo.site%s"',
        escapeshellarg($cookieFile),
        $urlPath
    );
    $out = shell_exec($cmd);
    if (!$out) return ['error' => 'No response'];
    $json = json_decode($out, true);
    return is_array($json) ? $json : ['error' => 'Invalid JSON', 'raw' => $out];
}

echo "======================================================================\n";
echo "🔍 AUDIT: UI/GIS PREVIEW LAYER VS AUTHORITATIVE LAYER ISOLATION\n";
echo "======================================================================\n\n";

// 1. Feeder 118 (Bahagia Steel 1) - Preview Network API
echo "[TEST 1] Querying /gis/api-network?penyulang_id=118 on Live Production...\n";
$f118Net = callProdApi('/gis/api-network?penyulang_id=118', $cookieFile);
$statusNet = $f118Net['status'] ?? 'FAIL';
$networkStatus = $f118Net['data']['network_status'] ?? 'UNKNOWN';
$edgesCount = count($f118Net['data']['edges'] ?? []);
$tlCount = count($f118Net['data']['translines'] ?? []);
$previewCount = count($f118Net['data']['preview_segments'] ?? []);

echo "  • HTTP / API Status     : {$statusNet}\n";
echo "  • Network Status Label  : {$networkStatus}\n";
echo "  • Authoritative Edges   : {$edgesCount} (MUST BE 0)\n";
echo "  • Authoritative TL      : {$tlCount} (MUST BE 0)\n";
echo "  • Preview Segments      : {$previewCount}\n";

$allPreviewUnpersisted = true;
foreach ($f118Net['data']['preview_segments'] ?? [] as $seg) {
    if ($seg['persisted'] !== false || $seg['authoritative'] !== false || $seg['topology'] !== false) {
        $allPreviewUnpersisted = false;
        break;
    }
}
echo "  • Preview Flags Check   : " . ($allPreviewUnpersisted ? "PASS (persisted=false, authoritative=false, topology=false)" : "FAIL") . "\n\n";

// 2. Feeder 118 - Conductor Analytics API
echo "[TEST 2] Querying /gis/api-conductor-analytics?penyulang_id=118&scope=feeder on Live Production...\n";
$f118Ana = callProdApi('/gis/api-conductor-analytics?penyulang_id=118&scope=feeder', $cookieFile);
$anaStatus = $f118Ana['status'] ?? 'FAIL';
$anaTlCount = $f118Ana['summary']['transline_resmi_count'] ?? -1;
$anaLengthM = $f118Ana['summary']['total_panjang_meter'] ?? -1;
$previewInc = $f118Ana['summary']['preview_included'] ?? true;
$authOnly = $f118Ana['summary']['is_authoritative_only'] ?? false;

echo "  • HTTP / API Status     : {$anaStatus}\n";
echo "  • Official TL Count     : {$anaTlCount} (MUST BE 0)\n";
echo "  • Total Length (m)      : {$anaLengthM} (MUST BE 0)\n";
echo "  • Preview Included      : " . ($previewInc ? "TRUE (VIOLATION)" : "FALSE (PROTECTED)") . "\n";
echo "  • Authoritative Only    : " . ($authOnly ? "TRUE (VERIFIED)" : "FALSE") . "\n\n";

// 3. Feeder 23 (Gedangan) - Positive Control
echo "[TEST 3] Querying /gis/api-conductor-analytics?penyulang_id=23&scope=feeder on Live Production...\n";
$f23Ana = callProdApi('/gis/api-conductor-analytics?penyulang_id=23&scope=feeder', $cookieFile);
$f23TlCount = $f23Ana['summary']['transline_resmi_count'] ?? -1;
$f23LengthM = $f23Ana['summary']['total_panjang_meter'] ?? -1;
$f23ItemsCount = count($f23Ana['items'] ?? []);

echo "  • Official TL Count     : {$f23TlCount} (MUST BE 1)\n";
echo "  • Total Length (m)      : {$f23LengthM} m\n";
echo "  • Items Returned        : {$f23ItemsCount} items\n";
if ($f23ItemsCount > 0) {
    $item0 = $f23Ana['items'][0];
    echo "  • Conductor Spec        : " . ($item0['conductor_label'] ?? '') . " (" . ($item0['length_meter'] ?? '') . "m)\n";
    echo "  • Is Authoritative      : " . (($item0['is_authoritative'] ?? false) ? "TRUE" : "FALSE") . "\n";
}

$isolationPass = (
    $edgesCount === 0 &&
    $tlCount === 0 &&
    $allPreviewUnpersisted &&
    $anaTlCount === 0 &&
    $anaLengthM === 0 &&
    !$previewInc &&
    $authOnly &&
    $f23TlCount === 1
);

echo "\n======================================================================\n";
echo "UI/GIS PREVIEW ISOLATION VERDICT: " . ($isolationPass ? "PASS (100% SECURE FIREWALL)" : "FAIL") . "\n";
echo "======================================================================\n";

file_put_contents('e:/XAMPP/htdocs/SIDAK TEJO/scratch/forensic_ui_preflight_isolation.json', json_encode([
    'timestamp'      => date('c'),
    'isolation_pass' => $isolationPass,
    'feeder_118_net' => [
        'network_status'       => $networkStatus,
        'edges_count'          => $edgesCount,
        'translines_count'     => $tlCount,
        'preview_count'        => $previewCount,
        'preview_unpersisted'  => $allPreviewUnpersisted
    ],
    'feeder_118_analytics' => [
        'transline_resmi_count' => $anaTlCount,
        'total_panjang_meter'   => $anaLengthM,
        'preview_included'      => $previewInc,
        'is_authoritative_only' => $authOnly
    ],
    'feeder_23_positive_control' => [
        'transline_resmi_count' => $f23TlCount,
        'total_panjang_meter'   => $f23LengthM,
        'items_count'           => $f23ItemsCount
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
