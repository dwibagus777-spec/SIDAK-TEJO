<?php
$baseUrl = 'https://sidaktejo.site/master-assets/b2-atomic-commit';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];
$cookieFile = __DIR__ . '/prod_cookie.txt';

$params = [
    'key'         => 'sidak_transline_audit_2026',
    'fingerprint' => 'ad2c9fcb833ca2680d00bb45adfa27a4e87730745aa65bf1c3419c89cb713397',
    'commit'      => 0,
];

$url = $baseUrl . '?' . http_build_query($params);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($err) {
    echo "CURL Error: $err\n";
}
echo "Response Body:\n$res\n";

$json = json_decode($res, true);
if ($json) {
    echo "\nDecoded Status: " . ($json['status'] ?? 'unknown') . "\n";
    echo "Execution State: " . ($json['execution_state'] ?? 'unknown') . "\n";
    echo "Existing Preserved: " . ($json['existing_tl_preserved'] ?? 'n/a') . "\n";
    echo "New Added: " . ($json['new_authoritative_tl_added'] ?? 'n/a') . "\n";
    echo "Total Now: " . ($json['total_authoritative_tl_now'] ?? 'n/a') . "\n";
    echo "DB Total Distance (m): " . ($json['db_total_distance_meters'] ?? 'n/a') . "\n";
    echo "DB Total Distance (km): " . ($json['db_total_distance_km'] ?? 'n/a') . "\n";
}
