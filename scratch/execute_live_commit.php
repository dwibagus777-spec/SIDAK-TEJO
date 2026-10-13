<?php
$baseUrl = 'https://sidaktejo.site/master-assets/b2-atomic-commit';
$cookieFile = __DIR__ . '/prod_cookie.txt';

$postData = [
    'key'         => 'sidak_transline_audit_2026',
    'fingerprint' => 'ad2c9fcb833ca2680d00bb45adfa27a4e87730745aa65bf1c3419c89cb713397',
    'commit'      => 1,
];

$ch = curl_init($baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$startTime = microtime(true);
$res = curl_exec($ch);
$elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Elapsed: {$elapsedMs} ms\n";
if ($err) {
    echo "CURL Error: $err\n";
}
echo "Response Body:\n$res\n";

$json = json_decode($res, true);
if ($json) {
    echo "\n=== PRODUCTION COMMIT RESULTS ===\n";
    echo "Status: " . ($json['status'] ?? 'unknown') . "\n";
    echo "Execution State: " . ($json['execution_state'] ?? 'unknown') . "\n";
    echo "Batch ID: " . ($json['batch_id'] ?? 'unknown') . "\n";
    echo "Existing Preserved: " . ($json['existing_tl_preserved'] ?? 'n/a') . "\n";
    echo "New Added: " . ($json['new_authoritative_tl_added'] ?? 'n/a') . "\n";
    echo "Total Authoritative TL Now: " . ($json['total_authoritative_tl_now'] ?? 'n/a') . "\n";
    echo "DB Total Distance (m): " . ($json['db_total_distance_meters'] ?? 'n/a') . "\n";
    echo "DB Total Distance (km): " . ($json['db_total_distance_km'] ?? 'n/a') . "\n";
    echo "Parent Asset ID Touched: " . ($json['parent_asset_id_touched'] ? 'TRUE (BREACH!)' : 'FALSE (PRESERVED)') . "\n";
    echo "Feeder 118 Authoritative TL: " . ($json['feeder_118_authoritative_tl'] ?? 'n/a') . "\n";
    echo "Executed At: " . ($json['executed_at'] ?? 'unknown') . "\n";
    
    // Save live execution result to scratch and audits
    file_put_contents(__DIR__ . '/b2_1_live_commit_response.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents(__DIR__ . '/../writable/audits/B2_1_PRODUCTION_ATOMIC_COMMIT_REPORT.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
