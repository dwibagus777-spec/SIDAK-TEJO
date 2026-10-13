<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init($baseUrl . '/gis/api-transline-tl04-preview?penyulang_id=131');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "HTTP Code: {$code}\n";
echo "cURL Error: {$err}\n";
echo "Length: " . strlen($res) . "\n";
echo "Content: " . substr($res, 0, 500) . "\n";
$data = json_decode($res, true);

if (!$data || ($data['status'] ?? '') !== 'success') {
    echo "Failed JSON parse or status not success\n";
    exit(1);
}

$inv = $data['inventory'] ?? [];
$sum = $data['summary'] ?? [];
$batch = $data['defensible_batch_preview'] ?? [];

echo "FEEDER 131 INVENTORY:\n";
echo "  Total Assets: " . ($inv['total_master_assets'] ?? 0) . "\n";
echo "  Existing Translines: " . ($inv['authoritative_translines'] ?? 0) . "\n";
echo "  Connected Assets: " . ($inv['connected_assets_count'] ?? 0) . "\n";
echo "  Isolated Assets: " . ($inv['isolated_assets_count'] ?? 0) . "\n";

echo "\nSUMMARY:\n";
echo "  Total Candidates: " . ($sum['total_candidates_analyzed'] ?? 0) . "\n";
echo "  Defensible Count: " . ($sum['defensible_count'] ?? 0) . "\n";
echo "  Batch Capacity: " . ($sum['batch_capacity'] ?? 0) . "\n";

echo "\nDEFAULT DEFENSIBLE BATCH PREVIEW (" . count($batch) . " candidates):\n";
foreach ($batch as $idx => $b) {
    $n = $idx + 1;
    printf("[%02d] %-24s | %-12s -> %-12s | Dist: %6.2fm | Score: %3d | Type: %s\n",
        $n,
        $b['natural_key'],
        $b['source_asset_name'] ?? $b['source_asset_code'],
        $b['target_asset_name'] ?? $b['target_asset_code'],
        $b['distance_meters'],
        $b['total_score'],
        $b['candidate_type']
    );
}

file_put_contents(__DIR__ . '/feeder_131_prod_preview.json', json_encode($data, JSON_PRETTY_PRINT));
