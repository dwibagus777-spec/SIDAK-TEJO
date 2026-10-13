<?php

require_once 'e:/XAMPP/htdocs/SIDAK TEJO/vendor/autoload.php';

use App\Services\OneShotNetworkIngestionService;

$h = fopen('e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_assets.csv', 'r');
$header = fgetcsv($h);

$bhgAssets = [];
while (($row = fgetcsv($h)) !== false) {
    if (trim($row[5]) === 'BAHAGIA STEEL 1') {
        $bhgAssets[] = [
            'no'         => $row[0],
            'kode_asset' => $row[1],
            'nama_asset' => $row[2],
            'lat'        => (float)$row[13],
            'lng'        => (float)$row[14],
        ];
    }
}
fclose($h);

echo "Total Bahagia Steel 1 Assets in CSV: " . count($bhgAssets) . "\n";
echo "First 10 as dumped in CSV:\n";
for ($i = 0; $i < min(10, count($bhgAssets)); $i++) {
    echo "  [{$bhgAssets[$i]['no']}] {$bhgAssets[$i]['kode_asset']} | {$bhgAssets[$i]['nama_asset']} | Lat: {$bhgAssets[$i]['lat']} | Lng: {$bhgAssets[$i]['lng']}\n";
}

// Sort by pole numbering extracted from nama_asset
usort($bhgAssets, function ($a, $b) {
    preg_match('/(\d+)$/', $a['nama_asset'], $mA);
    preg_match('/(\d+)$/', $b['nama_asset'], $mB);
    $nA = isset($mA[1]) ? (int)$mA[1] : 0;
    $nB = isset($mB[1]) ? (int)$mB[1] : 0;
    return $nA <=> $nB;
});

echo "\nFirst 10 sorted by pole number in nama_asset:\n";
for ($i = 0; $i < min(10, count($bhgAssets)); $i++) {
    echo "  [{$bhgAssets[$i]['no']}] {$bhgAssets[$i]['kode_asset']} | {$bhgAssets[$i]['nama_asset']} | Lat: {$bhgAssets[$i]['lat']} | Lng: {$bhgAssets[$i]['lng']}\n";
}

// Calculate distances between consecutive sorted poles
echo "\nConsecutive distances along sorted poles:\n";
$distances = [];
for ($i = 0; $i < count($bhgAssets) - 1; $i++) {
    $p1 = $bhgAssets[$i];
    $p2 = $bhgAssets[$i + 1];
    $d = \App\Services\OneShotNetworkIngestionService::haversineDistance($p1['lat'], $p1['lng'], $p2['lat'], $p2['lng']);
    $distances[] = $d;
    if ($i < 10) {
        echo "  {$p1['nama_asset']} -> {$p2['nama_asset']}: {$d} meters\n";
    }
}
echo "Min distance: " . min($distances) . " m | Max distance: " . max($distances) . " m | Avg: " . round(array_sum($distances)/count($distances), 2) . " m\n";
