<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Services\AssetCorpusCompletionService;

echo "=========================================================================\n";
echo "  TRACK D1 ASSET CORPUS PRODUCTION PRE-FLIGHT VERIFICATION SUITE       \n";
echo "=========================================================================\n\n";

$service = new AssetCorpusCompletionService();
$passed = 0;
$total = 5;

function reportCheck($id, $title, $condition) {
    global $passed;
    if ($condition) {
        $passed++;
        echo sprintf("[PASS] %-10s : %s\n", $id, $title);
    } else {
        echo sprintf("[FAIL] %-10s : %s\n", $id, $title);
    }
}

// 1. Service Operational Baseline
$summary = $service->getOperationalSummary();
reportCheck('PRE-01', 'Active Assets Target Coverage (5,236 / 5,236 assets)', $summary['active_assets'] === 5236);

// 2. Physical Total Assets & Historical Invariant
$physicalOk = ($summary['physical_assets'] === 5549) && ($summary['deleted_assets'] === 313);
reportCheck('PRE-02', 'Physical Total Assets & Historical Baseline (5,549 total, 313 deleted)', $physicalOk);

// 3. Topology & GIS Invariant
$topoOk = ($summary['topology_snapshot'] === 'TOPOLOGY-20260928-245-81c43a7f') && ($summary['delta_topology'] === 0);
reportCheck('PRE-03', 'Master Topology Invariant (TOPOLOGY-20260928-245-81c43a7f, Delta=0)', $topoOk);

// 4. Canonical Audit Engine
$audit = $service->runCanonicalReconciliationAudit();
$auditOk = !empty($audit['audit_id']) && (strlen($audit['bundle_hash']) === 64);
reportCheck('PRE-04', 'Canonical Asset Reconciliation Audit Engine & SHA-256 Bundle', $auditOk);

// 5. Hostinger Auto-Deploy Endpoint Sync Call
$autoDeployUrl = 'https://sidaktejo.site/auto-deploy';
$ch = curl_init($autoDeployUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AntigravityD1/1.0');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$deployOk = ($httpCode === 200) && (strpos($response, 'synced') !== false || strpos($response, 'success') !== false || strpos($response, 'Hostinger') !== false);
reportCheck('PRE-05', 'Hostinger Auto-Deploy Webhook Sync (HTTP 200 Success)', $deployOk);

echo "\n-------------------------------------------------------------------------\n";
echo sprintf("  PRODUCTION PRE-FLIGHT VERIFICATION: %d / %d PASSED\n", $passed, $total);
echo "=========================================================================\n";

if ($passed !== $total) {
    exit(1);
}
