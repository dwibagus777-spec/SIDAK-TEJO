<?php

$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init($baseUrl . '/gis/api-transline-tl04-preview?penyulang_id=56');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 90);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) SIDAK-TEJO-PHASE-D1/1.0',
    'X-Requested-With: XMLHttpRequest',
]);

$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP Code: {$code}\n";
if ($err) {
    echo "cURL Error: {$err}\n";
}

$data = json_decode($res, true);
if (!$data || ($data['status'] ?? '') !== 'success') {
    echo "Failed to load Feeder 56 preview:\n" . substr($res, 0, 500) . "\n";
    exit(1);
}

$inv = $data['inventory'] ?? [];
$sum = $data['summary'] ?? [];
$batch = $data['defensible_batch_preview'] ?? [];

echo "\n============================================================\n";
echo "FEEDER #56 (BANJARAN, ULP Krian) LIVE TOPOLOGY INVENTORY\n";
echo "============================================================\n";
echo "Total Master Assets      : " . ($inv['total_master_assets'] ?? 0) . "\n";
echo "Authoritative Translines : " . ($inv['authoritative_translines'] ?? 0) . "\n";
echo "Connected Assets Count   : " . ($inv['connected_assets_count'] ?? 0) . "\n";
echo "Isolated Assets Count    : " . ($inv['isolated_assets_count'] ?? 0) . "\n";
echo "Total Candidates Analyzed: " . ($sum['total_candidates_analyzed'] ?? 0) . "\n";
echo "Defensible Count         : " . ($sum['defensible_count'] ?? 0) . "\n";
echo "Batch Capacity           : " . ($sum['batch_capacity'] ?? 0) . "\n";

echo "\nDEFENSIBLE BATCH 1 PREVIEW (" . count($batch) . " candidates):\n";
printf("%-4s | %-24s | %-16s -> %-16s | Dist (m) | Score | Type\n",
    "#", "Natural Key", "Source Asset", "Target Asset");
echo str_repeat('-', 95) . "\n";

foreach ($batch as $idx => $c) {
    $num = $idx + 1;
    printf("[%02d] | %-24s | %-16s -> %-16s | %8.2f | %5d | %s\n",
        $num,
        $c['natural_key'],
        $c['source_asset_code'] ?? $c['source_asset_id'],
        $c['target_asset_code'] ?? $c['target_asset_id'],
        (float)$c['distance_meters'],
        (int)$c['total_score'],
        $c['candidate_type'] ?? 'UNKNOWN'
    );
}

file_put_contents(__DIR__ . '/feeder_56_live_preview.json', json_encode($data, JSON_PRETTY_PRINT));
echo "\nSaved full preview JSON to scratch/feeder_56_live_preview.json\n";
