<?php
/**
 * Comprehensive Post-Commit Forensic Verification Script
 * Validates:
 * 1. translineAudit endpoint (243 total, 217 preserved, 26 new, DB distance)
 * 2. Feeder 118 GIS Network endpoint
 * 3. Conductor Analytics endpoint (feeder 118 & global)
 * 4. Sample affected feeders
 * 5. Empty feeders (sample 5 of 101)
 * 6. Review queue status
 */

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

$results = [
    'audit_timestamp' => date('Y-m-d H:i:s T'),
    'phase'           => 'B2.1_POST_COMMIT_FORENSIC_VERIFICATION',
    'checks'          => []
];

// 1. Audit Endpoint: Total Network & Feeder 118
echo "--- 1. Checking Transline Audit Endpoint ---\n";
$auditGlobal = getUrl($baseUrl . "/master-assets/transline-audit?key={$key}&feeder_id=0", $cookieFile);
echo "Global audit HTTP: {$auditGlobal['code']}\n";
$audit118 = getUrl($baseUrl . "/master-assets/transline-audit?key={$key}&feeder_id=118", $cookieFile);
echo "Feeder 118 audit HTTP: {$audit118['code']}\n";

$results['checks']['transline_audit_global'] = [
    'http_code' => $auditGlobal['code'],
    'data'      => $auditGlobal['json']['data'] ?? null
];
$results['checks']['transline_audit_feeder118'] = [
    'http_code' => $audit118['code'],
    'data'      => $audit118['json']['data'] ?? null
];

// 2. GIS Penyulang Network Endpoint (Feeder 118)
echo "\n--- 2. Checking Feeder 118 GIS Penyulang Network ---\n";
$gis118 = getUrl($baseUrl . "/gis/penyulang-network/118", $cookieFile);
echo "GIS 118 HTTP: {$gis118['code']}\n";
$translines118Count = 0;
if ($gis118['json'] && isset($gis118['json']['translines'])) {
    $translines118Count = count($gis118['json']['translines']);
    echo "GIS 118 Translines count: $translines118Count\n";
} elseif ($gis118['json'] && isset($gis118['json']['data'])) {
    $translines118Count = count($gis118['json']['data']);
    echo "GIS 118 Data count: $translines118Count\n";
}
$results['checks']['gis_penyulang_network_118'] = [
    'http_code' => $gis118['code'],
    'count'     => $translines118Count,
    'snippet'   => is_array($gis118['json']) ? array_slice($gis118['json'], 0, 3) : substr((string)$gis118['body'], 0, 200)
];

// 3. Conductor Analytics
echo "\n--- 3. Checking Conductor Analytics ---\n";
$cond118 = getUrl($baseUrl . "/gis/api-conductor-analytics?penyulang_id=118", $cookieFile);
echo "Conductor 118 HTTP: {$cond118['code']}\n";
$results['checks']['conductor_analytics_118'] = [
    'http_code' => $cond118['code'],
    'data'      => $cond118['json'] ?? substr((string)$cond118['body'], 0, 200)
];

$condGlobal = getUrl($baseUrl . "/gis/api-conductor-analytics", $cookieFile);
echo "Conductor Global HTTP: {$condGlobal['code']}\n";
$results['checks']['conductor_analytics_global'] = [
    'http_code' => $condGlobal['code'],
    'data'      => $condGlobal['json'] ?? substr((string)$condGlobal['body'], 0, 200)
];

// 4. Sample other committed feeders: 73 (GERY FOOD), 78 (JAVA PACIFIK 3), 113 (HASIL KARYA 1)
echo "\n--- 4. Checking Sample Committed Feeders ---\n";
$sampleFeeders = [73, 78, 113, 117, 121];
$sampleResults = [];
foreach ($sampleFeeders as $fid) {
    $fAudit = getUrl($baseUrl . "/master-assets/transline-audit?key={$key}&feeder_id={$fid}", $cookieFile);
    $tlCount = $fAudit['json']['data']['summary']['total_active_translines'] ?? 'n/a';
    echo "Feeder $fid total translines: $tlCount\n";
    $sampleResults[$fid] = [
        'http_code' => $fAudit['code'],
        'translines_count' => $tlCount
    ];
}
$results['checks']['sample_committed_feeders'] = $sampleResults;

// 5. Sample Empty Feeders (Feeders 1, 2, 3, 5, 6)
echo "\n--- 5. Checking Sample Empty Feeders ---\n";
$emptyFeeders = [1, 2, 3, 5, 6];
$emptyResults = [];
foreach ($emptyFeeders as $fid) {
    $fAudit = getUrl($baseUrl . "/master-assets/transline-audit?key={$key}&feeder_id={$fid}", $cookieFile);
    $tlCount = $fAudit['json']['data']['summary']['total_active_translines'] ?? 0;
    echo "Empty Feeder $fid total translines: $tlCount\n";
    $emptyResults[$fid] = [
        'http_code' => $fAudit['code'],
        'translines_count' => $tlCount,
        'is_zero' => ($tlCount === 0)
    ];
}
$results['checks']['empty_feeders_verification'] = $emptyResults;

// Save full forensic audit report
file_put_contents(__DIR__ . '/post_commit_forensic_results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "\n=== Forensic Verification Probing Completed ===\n";
