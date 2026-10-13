<?php
// Minimal cURL diagnostic
$url = 'https://sidaktejo.site/remediation/investigate/topology-delta';
$c = curl_init($url);
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($c, CURLOPT_TIMEOUT, 30);
curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($c, CURLOPT_HTTPHEADER, ['X-Audit-Token: sidak_transline_audit_2026', 'Accept: application/json']);
curl_setopt($c, CURLOPT_VERBOSE, false);
$body = curl_exec($c);
$code = curl_getinfo($c, CURLINFO_HTTP_CODE);
$err  = curl_error($c);
$errno = curl_errno($c);
curl_close($c);

echo "PHP version : " . phpversion() . "\n";
echo "cURL version: " . curl_version()['version'] . "\n";
echo "HTTP code   : {$code}\n";
echo "cURL errno  : {$errno}\n";
echo "cURL error  : {$err}\n";
echo "Body length : " . strlen((string)$body) . "\n";
echo "Body (first 500): " . substr((string)$body, 0, 500) . "\n";
