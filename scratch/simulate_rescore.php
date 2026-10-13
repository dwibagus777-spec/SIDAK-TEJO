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

echo "Total candidates from preview: " . count($candidates) . "\n";

// Function to calculate score with user-specified mainline extension degree scoring
function reScore($c) {
    $anchorPts = in_array('AUTHORITATIVE_ANCHOR_CONTINUATION', $c['evidence']) ? 35 : 0;

    // Sequence
    $seqPts = 0;
    if (in_array('CONSECUTIVE_CODE_SEQUENCE', $c['evidence'])) $seqPts = 25;
    elseif (in_array('ADJACENT_CODE_SEQUENCE_DELTA2', $c['evidence'])) $seqPts = 18;
    elseif (in_array('PROXIMAL_CODE_SEQUENCE', $c['evidence'])) $seqPts = 10;
    elseif (in_array('CODE_SEQUENCE_DELTA_LE10', $c['evidence'])) $seqPts = 5;

    // Bearing
    $bearingPts = 10;
    if (in_array('COLLINEAR_BEARING_CONTINUITY', $c['evidence'])) $bearingPts = 20;
    elseif (in_array('GENTLE_CURVE_BEARING', $c['evidence'])) $bearingPts = 10;
    elseif (in_array('SHARP_BEARING_CHANGE_WARNING', $c['evidence'])) $bearingPts = 0;

    // Degree: Mainline extension (d=1 -> 2) = 10 pts
    $degPts = 10; // since it connects to anchor

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
    $newScore = reScore($c);
    if ($newScore >= 85) {
        $eligible[] = [
            'candidate' => $c,
            'newScore'  => $newScore,
        ];
    }
}

echo "Eligible with corrected degree scoring (Score >= 85): " . count($eligible) . "\n";
foreach ($eligible as $e) {
    $c = $e['candidate'];
    echo "  {$c['source_asset_code']} <--> {$c['target_asset_code']} | Dist: {$c['distance_meters']}m | Score: {$e['newScore']} | Evidence: " . implode(', ', $c['evidence']) . "\n";
}
