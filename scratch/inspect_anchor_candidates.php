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

echo "--- CANDIDATES CONNECTING TO ANCHORS ---\n";
$anchorCandidates = [];
foreach ($candidates as $c) {
    if (!empty($c['is_existing'])) continue;
    if (in_array('AUTHORITATIVE_ANCHOR_CONTINUATION', $c['evidence'])) {
        $anchorCandidates[] = $c;
    }
}

echo "Total candidates connecting to an anchor: " . count($anchorCandidates) . "\n";

// Sort by distance ASC
usort($anchorCandidates, fn($a, $b) => $a['distance_meters'] <=> $b['distance_meters']);

foreach (array_slice($anchorCandidates, 0, 25) as $c) {
    echo "Anchor Pair: {$c['source_asset_code']} <--> {$c['target_asset_code']}\n";
    echo "  Dist: {$c['distance_meters']}m | Score: {$c['confidence_score']} | Class: {$c['classification']}\n";
    echo "  Evidence: " . implode(', ', $c['evidence']) . "\n";
}
