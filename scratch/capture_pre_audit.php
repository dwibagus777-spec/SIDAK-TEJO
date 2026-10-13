<?php

$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init($baseUrl . '/gis/api-global-topology-audit');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) SIDAK-TEJO-PHASE-D1/1.0',
    'X-Requested-With: XMLHttpRequest',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200) {
    echo "ERROR: HTTP $httpCode\n$err\n$response\n";
    exit(1);
}

$data = json_decode($response, true);
file_put_contents(__DIR__ . '/feeder56_pre_audit.json', json_encode($data, JSON_PRETTY_PRINT));

echo "PRE-EXECUTION AUDIT CAPTURED:\n";
echo "Timestamp: " . ($data['timestamp'] ?? date('c')) . "\n";
echo "Active Translines: " . ($data['authoritative_active_translines'] ?? 'N/A') . "\n";
echo "Physical Rows: " . ($data['gis_translines_count'] ?? 'N/A') . "\n";
echo "Tables Fingerprint:\n";
foreach ($data['tables'] ?? [] as $t => $info) {
    echo sprintf(" - %-25s : %6d rows | %s\n", $t, $info['count'], $info['sha256']);
}
echo "Feeder 15 Active : " . ($data['feeder_breakdown'][15]['active'] ?? 'N/A') . "\n";
echo "Feeder 4  Active : " . ($data['feeder_breakdown'][4]['active'] ?? 'N/A') . "\n";
echo "Feeder 131 Active: " . ($data['feeder_breakdown'][131]['active'] ?? 'N/A') . "\n";
echo "Feeder 56  Active: " . ($data['feeder_breakdown'][56]['active'] ?? '0') . "\n";
