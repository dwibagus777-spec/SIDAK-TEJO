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

function testScore($c) {
    $anchorPts = in_array('AUTHORITATIVE_ANCHOR_CONTINUATION', $c['evidence']) ? 35 : 0;

    // Sequence
    $seqPts = 10; // baseline same series
    $sCode = $c['source_asset_code'];
    $tCode = $c['target_asset_code'];
    preg_match('/(\d+)$/', $sCode, $m1);
    preg_match('/(\d+)$/', $tCode, $m2);
    if (isset($m1[1]) && isset($m2[1])) {
        $dSeq = abs((int)$m1[1] - (int)$m2[1]);
        if ($dSeq === 1) $seqPts = 25;
        elseif ($dSeq === 2) $seqPts = 18;
        elseif ($dSeq <= 5) $seqPts = 15;
        elseif ($dSeq <= 15) $seqPts = 10;
        else $seqPts = 10;
    }

    // Bearing
    $bearingPts = 10;
    if (in_array('COLLINEAR_BEARING_CONTINUITY', $c['evidence'])) $bearingPts = 20;
    elseif (in_array('GENTLE_CURVE_BEARING', $c['evidence'])) $bearingPts = 10;
    elseif (in_array('SHARP_BEARING_CHANGE_WARNING', $c['evidence'])) $bearingPts = 0;

    // Degree: Mainline extension = 10
    $degPts = ($anchorPts > 0) ? 10 : 3;

    // Distance
    $distPts = 0;
    $dist = $c['distance_meters'];
    if ($dist >= 20.0 && $dist <= 55.0) $distPts = 10;
    elseif ($dist >= 5.0 && $dist < 20.0) $distPts = 7;
    elseif ($dist >= 2.0 && $dist < 5.0) $distPts = 5;
    elseif ($dist > 55.0 && $dist <= 85.0) $distPts = 4;

    return $anchorPts + $seqPts + $bearingPts + $degPts + $distPts;
}

$eligible = [];
foreach ($candidates as $c) {
    if (!empty($c['is_existing'])) continue;
    $s = testScore($c);
    if ($s >= 85) {
        $eligible[] = ['candidate' => $c, 'score' => $s];
    }
}

echo "Total AUTO_COMPLETE eligible candidates on production: " . count($eligible) . "\n";
foreach (array_slice($eligible, 0, 15) as $e) {
    $c = $e['candidate'];
    echo "  {$c['source_asset_code']} <--> {$c['target_asset_code']} | Dist: {$c['distance_meters']}m | Score: {$e['score']}\n";
}
