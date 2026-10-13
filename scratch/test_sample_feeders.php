<?php
$baseUrl = 'https://sidaktejo.site';
$cookieFile = __DIR__ . '/prod_cookie.txt';
$key = 'sidak_transline_audit_2026';

function getUrl($url, $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => $res, 'json' => json_decode((string)$res, true)];
}

$feeders = [73, 78, 113, 117, 121];
foreach ($feeders as $f) {
    $r = getUrl($baseUrl . "/master-assets/transline-audit?key={$key}&feeder_id={$f}", $cookieFile);
    $activeEdges = $r['json']['data']['active_edges_count'] ?? '0';
    $isHealthy = ($r['json']['data']['is_healthy'] ?? false) ? 'HEALTHY' : 'UNHEALTHY';
    echo "Feeder $f: active_edges_count = $activeEdges | Status = $isHealthy\n";
}
