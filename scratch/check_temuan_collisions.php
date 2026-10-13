<?php

$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init($baseUrl . '/api/temuan');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) SIDAK-TEJO-D1-1/1.0',
    'X-Requested-With: XMLHttpRequest',
]);
$res = curl_exec($ch);
curl_close($ch);

$json = json_decode($res, true);
$temuanRows = is_array($json) ? ($json['data'] ?? $json) : [];
echo "Total temuan fetched: " . count($temuanRows) . "\n";

$targetAssetIds = [5512, 5513, 5514, 5903, 5904, 5611, 5613, 5541, 5543, 5509, 5744, 5511, 5516, 5528, 5717, 5529, 5610];
$temuanIds = [];
$temuanCollisions = [];

foreach ($temuanRows as $t) {
    if (isset($t['id'])) {
        $tid = (int)$t['id'];
        $temuanIds[$tid] = $t;
        if (in_array($tid, $targetAssetIds, true)) {
            $temuanCollisions[] = $tid;
        }
    }
}

echo "Temuan ID range: " . (min(array_keys($temuanIds)) ?? 0) . " to " . (max(array_keys($temuanIds)) ?? 0) . "\n";
echo "Collisions between 17 Endpoint IDs and temuan.id: " . count($temuanCollisions) . "\n";
if (!empty($temuanCollisions)) {
    echo "Colliding IDs: " . implode(', ', $temuanCollisions) . "\n";
} else {
    echo "ZERO ID COLLISION: None of the 17 transline endpoint IDs exist in temuan.id!\n";
}
