<?php
$auditToken = 'sidak_transline_audit_2026';
$url = "https://sidaktejo.site/mobile-sync/audit?key={$auditToken}";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "Code: {$code}\n";
echo "Error: {$err}\n";
echo "Body: " . substr((string)$res, 0, 500) . "\n";
