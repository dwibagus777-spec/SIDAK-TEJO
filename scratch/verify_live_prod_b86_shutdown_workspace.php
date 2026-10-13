<?php

/**
 * Live Production Verification for B8.6 Planned Shutdown Scope Workspace
 * Target: https://sidaktejo.site/planning/shutdown-workspace
 */

$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl    = 'https://sidaktejo.site';

echo "====================================================================\n";
echo "  LIVE PRODUCTION DEPLOYMENT & CAPABILITY VERIFICATION              \n";
echo "  Target URL: {$baseUrl}/planning/shutdown-workspace                \n";
echo "  Timestamp: " . date('Y-m-d H:i:s T') . "                          \n";
echo "====================================================================\n\n";

function makeProdCall(string $url, string $cookieFile): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151']);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

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

// 1. Trigger Auto-Deploy
echo "[1/3] Triggering Hostinger Production Git Auto-Deploy...\n";
$deployRes = makeProdCall("{$baseUrl}/auto-deploy", $cookieFile);
echo "  Auto-Deploy Status Code: " . $deployRes['code'] . "\n";
echo "  Auto-Deploy Response   : " . substr(strip_tags($deployRes['body']), 0, 180) . "\n\n";

// 2. Query Live Workspace UI
echo "[2/3] Querying Deployed Workspace UI Surface (/planning/shutdown-workspace)...\n";
$workRes = makeProdCall("{$baseUrl}/planning/shutdown-workspace", $cookieFile);
echo "  UI HTTP Status Code: " . $workRes['code'] . "\n";
echo "  Workspace Title Match: " . (str_contains($workRes['body'], 'Shutdown Work Planning') || str_contains($workRes['body'], 'Shutdown Scope') ? "🟢 MATCHED LIVE UI" : "⚪ HTTP " . $workRes['code']) . "\n\n";

// 3. Query Live Summary API
echo "[3/3] Querying Deployed Summary API Endpoint (/api/planning/summary)...\n";
$apiRes = makeProdCall("{$baseUrl}/api/planning/summary", $cookieFile);
echo "  API HTTP Status Code: " . $apiRes['code'] . "\n";

$json = json_decode($apiRes['body'], true);
if (!empty($json)) {
    echo "  🟢 Production Live API Response Payload:\n";
    echo "     Snapshot ID         : " . ($json['snapshot_id'] ?? 'N/A') . "\n";
    echo "     Active Assets Count : " . ($json['total_active_assets'] ?? 'N/A') . "\n";
    echo "     Active Translines   : " . ($json['total_active_tl'] ?? 'N/A') . "\n";
    echo "     Outage Readiness %  : " . ($json['outage_readiness_pct'] ?? 'N/A') . "%\n";
    echo "     Master Invariant    : " . ($json['master_invariant'] ?? 'N/A') . "\n";
} else {
    echo "  Body Preview:\n" . substr($apiRes['body'], 0, 400) . "\n";
}

echo "\n====================================================================\n";
