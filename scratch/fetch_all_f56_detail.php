<?php

$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init($baseUrl . '/gis/api-translines?penyulang_id=56');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) SIDAK-TEJO-D1-1/1.0',
    'X-Requested-With: XMLHttpRequest',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "ERROR: HTTP $httpCode\n$response\n";
    exit(1);
}

$data = json_decode($response, true);
file_put_contents(__DIR__ . '/f56_translines_live.json', json_encode($data, JSON_PRETTY_PRINT));

echo "Fetched " . count($data['data'] ?? []) . " translines for Feeder 56.\n\n";

foreach ($data['data'] ?? [] as $t) {
    echo "============================================================\n";
    echo "PK: " . $t['id'] . " | Code: " . $t['code'] . "\n";
    echo "Source Asset ID: " . $t['source_asset_id'] . "\n";
    echo "  -> Code: " . ($t['source_asset']['code'] ?? 'N/A') . "\n";
    echo "  -> Name: " . ($t['source_asset']['name'] ?? 'N/A') . "\n";
    echo "  -> Lat:  " . ($t['source_asset']['latitude'] ?? 'N/A') . "\n";
    echo "  -> Lng:  " . ($t['source_asset']['longitude'] ?? 'N/A') . "\n";
    echo "Target Asset ID: " . $t['target_asset_id'] . "\n";
    echo "  -> Code: " . ($t['target_asset']['code'] ?? 'N/A') . "\n";
    echo "  -> Name: " . ($t['target_asset']['name'] ?? 'N/A') . "\n";
    echo "  -> Lat:  " . ($t['target_asset']['latitude'] ?? 'N/A') . "\n";
    echo "  -> Lng:  " . ($t['target_asset']['longitude'] ?? 'N/A') . "\n";
    echo "Coordinates in Geometry: " . json_encode($t['coordinates'] ?? []) . "\n";
    echo "Length (DB): " . ($t['length_m'] ?? $t['length_meter'] ?? 'N/A') . " m\n";
    echo "Created By: " . ($t['created_by'] ?? 'N/A') . "\n";
}
