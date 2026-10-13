<?php

$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init($baseUrl . '/gis/api-network?penyulang_id=56&zoom=18&layers=JTM');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) SIDAK-TEJO-D1-1/1.0',
    'X-Requested-With: XMLHttpRequest',
]);
$res = curl_exec($ch);
curl_close($ch);

$json = json_decode($res, true);
$features = $json['data']['features'] ?? [];
echo "Total JTM Features fetched for Feeder 56: " . count($features) . "\n";

$assetMap = [];
foreach ($features as $f) {
    $props = $f['properties'] ?? [];
    $id = (int)($props['id'] ?? $props['asset_id'] ?? 0);
    if ($id > 0) {
        $assetMap[$id] = [
            'id'           => $id,
            'kode_asset'   => $props['kode_asset'] ?? 'N/A',
            'nama_asset'   => $props['nama_asset'] ?? 'N/A',
            'penyulang_id' => (int)($props['penyulang_id'] ?? 0),
            'ulp_id'       => (int)($props['ulp_id'] ?? 0),
            'latitude'     => (float)($props['latitude'] ?? 0),
            'longitude'    => (float)($props['longitude'] ?? 0),
            'construction' => $props['construction_type'] ?? 'N/A',
            'status'       => $props['status'] ?? 'N/A',
        ];
    }
}

file_put_contents(__DIR__ . '/f56_assets_cache.json', json_encode($assetMap, JSON_PRETTY_PRINT));

echo "Saved " . count($assetMap) . " unique assets to f56_assets_cache.json\n\n";

// Target asset IDs in PK 227-235
$targetAssetIds = [5512, 5513, 5514, 5903, 5904, 5611, 5613, 5541, 5543, 5509, 5744, 5511, 5516, 5528, 5717, 5529, 5610];

echo "=========================================================================================\n";
echo "AUTHORITATIVE DATABASE RECORDS FOR ALL ENDPOINT ASSETS IN PK 227-235:\n";
echo "=========================================================================================\n";
printf("%-6s | %-28s | %-20s | %-5s | %-4s | %-12s | %-12s\n", "ID", "Kode Asset", "Nama Asset", "Peny", "ULP", "Latitude", "Longitude");
echo str_repeat("-", 95) . "\n";

foreach (array_unique($targetAssetIds) as $aid) {
    if (isset($assetMap[$aid])) {
        $a = $assetMap[$aid];
        printf("%-6d | %-28s | %-20s | %-5d | %-4d | %-12.7f | %-12.7f\n",
            $a['id'], $a['kode_asset'], $a['nama_asset'], $a['penyulang_id'], $a['ulp_id'], $a['latitude'], $a['longitude']
        );
    } else {
        echo "ID $aid: NOT FOUND IN FEEDER 56 ASSETS!\n";
    }
}
