<?php

$cookieFile = __DIR__ . '/prod_cookie.txt';
$resolve = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init('https://sidaktejo.site/gis/api-translines?penyulang_id=15');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
$res = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true);
$lines = $data['translines'] ?? $data['data'] ?? [];

echo "Existing Authoritative Lines on Feeder 15: " . count($lines) . "\n";

$deltas = [];
foreach ($lines as $l) {
    $sCode = $l['source_asset_code'] ?? '';
    $tCode = $l['target_asset_code'] ?? '';
    preg_match('/(\d+)$/', $sCode, $m1);
    preg_match('/(\d+)$/', $tCode, $m2);
    $n1 = isset($m1[1]) ? (int)$m1[1] : null;
    $n2 = isset($m2[1]) ? (int)$m2[1] : null;
    $dSeq = ($n1 !== null && $n2 !== null) ? abs($n1 - $n2) : 'N/A';
    echo "TL #{$l['id']}: {$sCode} ({$l['source_asset_id']}) <--> {$tCode} ({$l['target_asset_id']}) | Dist: {$l['distance_meters']}m | deltaSeq: {$dSeq} | created_by: {$l['created_by']}\n";
    if (is_int($dSeq)) {
        $bucket = ($dSeq === 1 ? '1' : ($dSeq === 2 ? '2' : ($dSeq <= 5 ? '<=5' : ($dSeq <= 10 ? '<=10' : '>10'))));
        $deltas[$bucket] = ($deltas[$bucket] ?? 0) + 1;
    }
}

echo "\nDelta sequence distribution on 52 existing authoritative lines:\n";
print_r($deltas);
