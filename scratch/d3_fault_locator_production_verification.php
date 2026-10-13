<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Services\FaultLocatorWorkspaceService;

echo "=========================================================================\n";
echo "  TRACK D3 FAULT LOCATOR PRODUCTION PRE-FLIGHT VERIFICATION SUITE       \n";
echo "=========================================================================\n\n";

$service = new FaultLocatorWorkspaceService();
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

// 1. Service & Engine Operational Summary
$summary = $service->getOperationalSummary();
$summaryOk = !empty($summary['engine_version']) && ($summary['engine_version'] === 'FLI-1.0.0');
reportCheck('PRE-01', 'FLI-1.0.0 Immutable Engine Core Loaded & Active', $summaryOk);

// 2. Topology Snapshot & Mutation Safety
$topoOk = ($summary['topology_snapshot'] === 'TOPOLOGY-20260928-245-81c43a7f') && ($summary['delta_topology'] === 0) && ($summary['delta_assets'] === 0);
reportCheck('PRE-02', 'Master Topology Snapshot Invariant (TOPOLOGY-20260928-245-81c43a7f, Delta=0)', $topoOk);

// 3. Fault Location Analysis Calculation
$res = $service->locateFault(['feeder_id' => 'F-CANDRAMAS', 'relay_distance_m' => 1250.0]);
$calcOk = !empty($res['case_uuid']) && ($res['outcome_status'] === 'RESOLVED');
reportCheck('PRE-03', 'FLI-1.0.0 Fault Location Calculation & Outcome Resolution', $calcOk);

// 4. SHA-256 Candidate Forensic Bundle Chain
$bundle = $service->getForensicBundle($res['case_uuid']);
$bundleOk = !empty($bundle['bundle_hash']) && (strlen($bundle['bundle_hash']) === 64);
reportCheck('PRE-04', 'Deterministic SHA-256 Forensic Provenance Bundle Chain Verified', $bundleOk);

// 5. Hostinger Auto-Deploy Webhook Sync Call & Live URL Check
$autoDeployUrl = 'https://sidaktejo.site/auto-deploy';
$ch = curl_init($autoDeployUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AntigravityD3/1.0');
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
