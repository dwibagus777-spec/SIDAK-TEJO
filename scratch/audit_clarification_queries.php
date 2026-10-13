<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

function getApi($url) {
    global $baseUrl, $resolveOpt, $cookieFile;
    $ch = curl_init($baseUrl . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($res, true), 'raw' => $res];
}

echo "=== 1. FETCHING FEEDER 15 AUTHORITATIVE TRANSLINES ===\n";
$tlRes = getApi('/gis/api-translines?penyulang_id=15');
echo "HTTP Code: " . $tlRes['code'] . "\n";
if ($tlRes['code'] === 200 && isset($tlRes['data']['data'])) {
    $tls = $tlRes['data']['data'];
    echo "Total Feeder 15 Authoritative Translines returned: " . count($tls) . "\n";
    // Analyze baseline vs AI
    $baselineCount = 0;
    $aiCount = 0;
    $otherCount = 0;
    foreach ($tls as $t) {
        $src = $t['source_type'] ?? $t['source'] ?? '';
        if (str_contains(strtoupper($src), 'AI') || str_contains(strtoupper($src), 'AUTO')) {
            $aiCount++;
        } elseif (str_contains(strtoupper($src), 'BASELINE') || str_contains(strtoupper($src), 'PLN') || str_contains(strtoupper($src), 'SURVEY') || $src === '') {
            $baselineCount++;
        } else {
            $otherCount++;
        }
    }
    echo "  - AI Generated / Completed: {$aiCount}\n";
    echo "  - Baseline / Survey / Default: {$baselineCount}\n";
    echo "  - Other: {$otherCount}\n";
} else {
    echo "Failed or empty: " . substr($tlRes['raw'], 0, 300) . "\n";
}

echo "\n=== 2. FETCHING FEEDER 15 NETWORK AUDIT ===\n";
$auditRes = getApi('/gis/api-network-audit?penyulang_id=15');
echo "HTTP Code: " . $auditRes['code'] . "\n";
if ($auditRes['code'] === 200) {
    echo json_encode($auditRes['data'], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "Failed: " . substr($auditRes['raw'], 0, 300) . "\n";
}

echo "\n=== 3. FETCHING FEEDER 15 PROPOSALS ===\n";
$propRes = getApi('/gis/api-proposals?penyulang_id=15');
echo "HTTP Code: " . $propRes['code'] . "\n";
if ($propRes['code'] === 200 && isset($propRes['data']['data'])) {
    echo "Pending proposals count: " . count($propRes['data']['data']) . "\n";
} else {
    echo "Proposals response: " . substr($propRes['raw'], 0, 300) . "\n";
}
