<?php

/**
 * Runner for Global Network Topology Coverage Audit
 */

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

echo "Calling https://sidaktejo.site/gis/api-global-topology-audit...\n";

$ch = curl_init($baseUrl . '/gis/api-global-topology-audit');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
curl_setopt($ch, CURLOPT_TIMEOUT, 180);

$t0 = microtime(true);
$rawRes = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

$elapsed = round(microtime(true) - $t0, 2);
echo "Response HTTP Code: {$httpCode} (took {$elapsed}s)\n";

if ($httpCode !== 200) {
    echo "ERROR: cURL error: {$curlErr}\n";
    echo "Raw response: " . substr($rawRes, 0, 1000) . "\n";
    exit(1);
}

$data = json_decode($rawRes, true);
if (!$data || ($data['status'] ?? '') !== 'success') {
    echo "ERROR: Invalid JSON response:\n";
    echo substr($rawRes, 0, 1000) . "\n";
    exit(1);
}

echo "\nAudit executed successfully in {$data['duration_ms']} ms!\n";

// Save JSON to required paths
$jsonPath1 = 'e:/XAMPP/htdocs/SIDAK TEJO/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json';
file_put_contents($jsonPath1, json_encode($data, JSON_PRETTY_PRINT));
echo "Saved JSON to {$jsonPath1}\n";

$jsonPath2 = 'C:/Users/INSPEKSIKOTA/.gemini/antigravity/brain/e5e83a3d-4509-4271-8129-9355c979dd3c/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json';
file_put_contents($jsonPath2, json_encode($data, JSON_PRETTY_PRINT));
echo "Saved JSON to {$jsonPath2}\n";

$jsonPath3 = 'e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json';
@file_put_contents($jsonPath3, json_encode($data, JSON_PRETTY_PRINT));

$sum = $data['global_summary'];
$gov = $data['governance'];

echo "\n============================================================\n";
echo "   GLOBAL NETWORK TOPOLOGY COVERAGE AUDIT RESULTS\n";
echo "============================================================\n";
echo "Zero-Write Firewall: " . $gov['zero_write_firewall'] . "\n";
echo "Operational Writes : INSERTS=" . $gov['operational_inserts'] . ", UPDATES=" . $gov['operational_updates'] . ", DELETES=" . $gov['operational_deletes'] . ", DDL=" . $gov['operational_ddl'] . "\n";
echo "Fingerprint Match  : " . ($data['fingerprints']['match'] ? "YES (100% IDENTICAL)" : "NO (BREACH)") . "\n";
echo "------------------------------------------------------------\n";
echo sprintf("TOTAL FEEDERS                : %d\n", $sum['total_feeders']);
echo sprintf("TOTAL ULPS                   : %d\n", $sum['total_ulps']);
echo sprintf("TOTAL JTM ASSETS             : %d\n", $sum['total_jtm_assets']);
echo sprintf("TOTAL VALID GPS ASSETS       : %d\n", $sum['total_valid_gps_assets']);
echo sprintf("TOTAL EXISTING TRANSLINES    : %d\n", $sum['total_existing_translines']);
echo sprintf("TOTAL CONNECTED ASSETS       : %d\n", $sum['total_connected_assets']);
echo sprintf("TOTAL ISOLATED ASSETS        : %d\n", $sum['total_isolated_assets']);
echo sprintf("TOTAL AUTO_COMPLETE CAND.    : %d\n", $sum['total_auto_complete']);
echo sprintf("TOTAL HIGH_CONFIDENCE CAND.  : %d\n", $sum['total_high_confidence']);
echo sprintf("TOTAL REVIEW REQUIRED CAND.  : %d\n", $sum['total_review_required']);
echo sprintf("TOTAL BLOCKED CANDIDATES     : %d\n", $sum['total_blocked']);
echo "------------------------------------------------------------\n";
echo "CLASSIFICATION BREAKDOWN:\n";
foreach ($data['classification_summary'] as $k => $v) {
    echo sprintf("  - %-32s : %d\n", $k, $v);
}
echo "------------------------------------------------------------\n";
echo "AUTOMATION OPPORTUNITY:\n";
foreach ($data['automation_opportunity'] as $k => $v) {
    echo sprintf("  - %-32s : %d\n", $k, $v);
}
echo "============================================================\n";
