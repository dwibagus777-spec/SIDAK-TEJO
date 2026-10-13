<?php
// Diagnostic: Try HTTP (port 80), direct IP, and with CURLOPT_RESOLVE
$token = 'sidak_transline_audit_2026';
$path  = '/remediation/investigate/topology-delta';

function tryGet(string $label, string $url, array $opts = []): void {
    echo "\n--- {$label} ---\n";
    echo "URL: {$url}\n";
    $c = curl_init($url);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($c, CURLOPT_TIMEOUT, 15);
    curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($c, CURLOPT_HTTPHEADER, ['X-Audit-Token: sidak_transline_audit_2026', 'Accept: application/json']);
    foreach ($opts as $k => $v) curl_setopt($c, $k, $v);
    $body = curl_exec($c);
    $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
    $err  = curl_error($c);
    $errno = curl_errno($c);
    curl_close($c);
    echo "HTTP: {$code} | errno: {$errno} | err: {$err}\n";
    echo "Body: " . substr((string)$body, 0, 300) . "\n";
}

// 1. HTTPS via hostname
tryGet('HTTPS via hostname', 'https://sidaktejo.site' . $path);

// 2. HTTP via hostname
tryGet('HTTP via hostname', 'http://sidaktejo.site' . $path);

// 3. HTTP direct IP
tryGet('HTTP direct IP (2.57.91.151)', 'http://2.57.91.151' . $path, [
    CURLOPT_HTTPHEADER => ['Host: sidaktejo.site', 'X-Audit-Token: sidak_transline_audit_2026', 'Accept: application/json']
]);

// 4. HTTPS via CURLOPT_RESOLVE
tryGet('HTTPS with CURLOPT_RESOLVE', 'https://sidaktejo.site' . $path, [
    CURLOPT_RESOLVE => ['sidaktejo.site:443:2.57.91.151']
]);

echo "\n=== DONE ===\n";
