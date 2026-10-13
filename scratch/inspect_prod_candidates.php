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
echo "Preview Status: " . ($data['status'] ?? 'N/A') . "\n";
echo "Summary: " . json_encode($data['summary'] ?? []) . "\n";

$candidates = $data['candidates'] ?? [];
echo "Total candidates: " . count($candidates) . "\n";

$nonExisting = array_filter($candidates, fn($c) => empty($c['is_existing']));
echo "Non-existing candidates: " . count($nonExisting) . "\n";

// Sort by confidence DESC, distance ASC
usort($nonExisting, function($a, $b) {
    $c = $b['confidence_score'] <=> $a['confidence_score'];
    if ($c !== 0) return $c;
    return $a['distance_meters'] <=> $b['distance_meters'];
});

echo "\n--- TOP 20 NON-EXISTING CANDIDATES ---\n";
$i = 1;
foreach (array_slice($nonExisting, 0, 20) as $c) {
    echo "#{$i}: {$c['source_asset_code']} ({$c['source_asset_id']}) <--> {$c['target_asset_code']} ({$c['target_asset_id']})\n";
    echo "    Dist: {$c['distance_meters']}m | Score: {$c['confidence_score']} | Class: {$c['classification']} | GateValid: " . var_export($c['gate_valid'] ?? null, true) . "\n";
    echo "    GateReason: " . ($c['gate_reason'] ?? 'NONE') . "\n";
    echo "    Evidence: " . implode(', ', $c['evidence'] ?? []) . "\n";
    $i++;
}
