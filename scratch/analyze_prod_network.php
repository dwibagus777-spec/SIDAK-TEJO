<?php

$cookieFile = __DIR__ . '/prod_cookie.txt';
$resolve = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init('https://sidaktejo.site/gis/api-network?penyulang_id=15');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
$res = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true)['data'] ?? [];
$assets = $data['assets'] ?? [];
$translines = $data['translines'] ?? [];

echo "Total assets in API: " . count($assets) . "\n";
echo "Total translines in API: " . count($translines) . "\n";

// Count degree of each asset from translines
$degrees = [];
foreach ($assets as $a) {
    $degrees[(int)$a['id']] = 0;
}
foreach ($translines as $tl) {
    $s = (int)($tl['source_asset_id'] ?? $tl['from_asset_id']);
    $t = (int)($tl['target_asset_id'] ?? $tl['to_asset_id']);
    if (isset($degrees[$s])) $degrees[$s]++;
    if (isset($degrees[$t])) $degrees[$t]++;
}

$degCounts = array_count_values($degrees);
ksort($degCounts);
echo "\nDegree distribution:\n";
foreach ($degCounts as $deg => $count) {
    echo "  Degree {$deg}: {$count} assets\n";
}

// Check degree 1 terminal anchors
$anchors = array_filter($degrees, fn($d) => $d === 1);
echo "\nTotal degree-1 anchors: " . count($anchors) . "\n";

// For each degree-1 anchor, find nearest unconnected (degree 0) asset
$assetMap = [];
foreach ($assets as $a) {
    $assetMap[(int)$a['id']] = $a;
}

function hDist($lat1, $lon1, $lat2, $lon2) {
    $r = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return round(2 * $r * atan2(sqrt($a), sqrt(1 - $a)), 2);
}

echo "\n--- ANCHOR EXTENSION OPPORTUNITIES ---\n";
foreach (array_slice(array_keys($anchors), 0, 15) as $aId) {
    $a = $assetMap[$aId];
    $nearest = null;
    $minD = 999999;
    foreach ($degrees as $oId => $deg) {
        if ($deg === 0) {
            $o = $assetMap[$oId];
            $d = hDist((float)$a['latitude'], (float)$a['longitude'], (float)$o['latitude'], (float)$o['longitude']);
            if ($d < $minD) {
                $minD = $d;
                $nearest = $o;
            }
        }
    }
    if ($nearest) {
        echo "Anchor {$a['kode_asset']} ({$aId}) -> Nearest Isolated: {$nearest['kode_asset']} ({$nearest['id']}) at {$minD}m\n";
    }
}
