<?php

$cookieFile = __DIR__ . '/prod_cookie.txt';
$resolve = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init('https://sidaktejo.site/gis/api-transline-ai-preview?penyulang_id=15');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
$res = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true);
$candidates = $data['candidates'] ?? [];

echo "=== CANDIDATES CONNECTING TO ANCHOR WITH DISTANCE <= 60m ===\n";
foreach ($candidates as $c) {
    if (!empty($c['is_existing'])) continue;
    if (!in_array('AUTHORITATIVE_ANCHOR_CONTINUATION', $c['evidence'])) continue;
    if ($c['distance_meters'] > 60.0) continue;

    $sCode = $c['source_asset_code'];
    $tCode = $c['target_asset_code'];
    preg_match('/(\d+)$/', $sCode, $m1);
    preg_match('/(\d+)$/', $tCode, $m2);
    $dSeq = (isset($m1[1]) && isset($m2[1])) ? abs((int)$m1[1] - (int)$m2[1]) : 'none';

    $hasBearing = in_array('COLLINEAR_BEARING_CONTINUITY', $c['evidence']) ? 'COLLINEAR' :
                 (in_array('GENTLE_CURVE_BEARING', $c['evidence']) ? 'GENTLE' : 'SHARP');

    echo "{$sCode} <--> {$tCode} | Dist: {$c['distance_meters']}m | dSeq: {$dSeq} | Bearing: {$hasBearing}\n";
}
