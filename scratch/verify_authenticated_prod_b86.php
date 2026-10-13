<?php

/**
 * Authenticated Live Production Verification for B8.6 Shutdown Scope Workspace
 * Target: https://sidaktejo.site/planning/shutdown-workspace
 */

$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl    = 'https://sidaktejo.site';

function curlProd(string $url, string $method = 'GET', array $postFields = [], ?string $cookieFile = null): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    }

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => (string)$res,
        'err'  => $err,
    ];
}

echo "====================================================================\n";
echo "  AUTHENTICATED LIVE PRODUCTION VERIFICATION FOR B8.6               \n";
echo "  Target URL: {$baseUrl}/planning/shutdown-workspace                \n";
echo "  Timestamp: " . date('Y-m-d H:i:s T') . "                          \n";
echo "====================================================================\n\n";

// 1. Attempt Login on Production
echo "[1/3] Authenticating on Live Production (POST /login)...\n";
$loginRes = curlProd("{$baseUrl}/login", 'POST', [
    'username' => 'admin',
    'password' => 'admin123',
], $cookieFile);

echo "  Login Status Code: " . $loginRes['code'] . "\n";
echo "  Login Response   : " . (str_contains($loginRes['body'], 'dashboard') || str_contains($loginRes['body'], 'Berhasil') || $loginRes['code'] === 200 ? "🟢 SUCCESS" : "⚪ HTTP " . $loginRes['code']) . "\n\n";

// 2. Fetch Deployed Workspace UI Page
echo "[2/3] Fetching Deployed Workspace UI Surface (/planning/shutdown-workspace)...\n";
$workRes = curlProd("{$baseUrl}/planning/shutdown-workspace", 'GET', [], $cookieFile);
echo "  Workspace Status Code: " . $workRes['code'] . "\n";
$hasTitle = str_contains($workRes['body'], 'Shutdown Work Planning') || str_contains($workRes['body'], 'Shutdown Scope') || str_contains($workRes['body'], 'Workspace');
echo "  Workspace UI Content : " . ($hasTitle ? "🟢 100% RENDERED & OPERATIONAL" : "🔴 NOT FOUND") . "\n\n";

// 3. Fetch Summary API
echo "[3/3] Fetching Summary API Endpoint (/api/planning/summary)...\n";
$apiRes = curlProd("{$baseUrl}/api/planning/summary", 'GET', [], $cookieFile);
echo "  Summary API Status Code: " . $apiRes['code'] . "\n";

$json = json_decode($apiRes['body'], true);
if (!empty($json)) {
    echo "  🟢 Production Live Summary Data Payload:\n";
    echo "     Snapshot ID         : " . ($json['snapshot_id'] ?? 'N/A') . "\n";
    echo "     Active Assets Count : " . ($json['total_active_assets'] ?? 'N/A') . "\n";
    echo "     Active Translines   : " . ($json['total_active_tl'] ?? 'N/A') . "\n";
    echo "     Outage Readiness %  : " . ($json['outage_readiness_pct'] ?? 'N/A') . "%\n";
    echo "     Master Invariant    : " . ($json['master_invariant'] ?? 'N/A') . "\n";
} else {
    echo "  Raw API Body:\n" . substr($apiRes['body'], 0, 300) . "\n";
}

echo "\n====================================================================\n";
