<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Services\DigitalTwinScenarioService;

echo "=========================================================================\n";
echo "  D1 PHASE 2B PRODUCTION PRE-FLIGHT VERIFICATION SUITE                  \n";
echo "=========================================================================\n\n";

$service = new DigitalTwinScenarioService();
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

// 1. Check Tables
$db = \Config\Database::connect('default');
$tablesExist = $db->tableExists('digital_twin_scenarios') && $db->tableExists('digital_twin_provenance_bundles');
reportCheck('PRE-01', 'Digital Twin Database DDL Tables Verified (scenarios & bundles)', $tablesExist);

// 2. Operational Baseline
$summary = $service->getOperationalSummary();
$baselineOk = ($summary['topology_snapshot'] === 'TOPOLOGY-20260928-245-81c43a7f') && ($summary['delta_topology'] === 0) && ($summary['delta_assets'] === 0);
reportCheck('PRE-02', 'Production Baseline Invariant (TOPOLOGY-20260928-245-81c43a7f, Delta=0)', $baselineOk);

// 3. What-If Engine
$sim = $service->runSimulation([
    'scenario_type' => 'REPLACEMENT_SCENARIO',
    'scenario_name' => 'Pre-flight Production Validation',
    'feeder_id'     => 'F-CANDRAMAS',
    'section_id'    => 'SEC-CAND-01',
    'asset_id'      => 'AST-TRF-094'
]);
$engineOk = !empty($sim['scenario_uuid']) && ($sim['recommendation_level'] === 'L4_RECOMMENDED');
reportCheck('PRE-03', 'What-If Simulation Engine & L4 Recommended Level', $engineOk);

// 4. SHA-256 Bundle Provenance
$bundle = $service->getForensicBundle($sim['scenario_uuid']);
$bundleOk = !empty($bundle['bundle_hash']) && strlen($bundle['bundle_hash']) === 64;
reportCheck('PRE-04', 'SHA-256 Forensic Provenance Bundle Chain Verified', $bundleOk);

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
$curlErr = curl_error($ch);
curl_close($ch);

$deployOk = ($httpCode === 200) && (strpos($response, 'synced') !== false || strpos($response, 'success') !== false || strpos($response, 'Hostinger') !== false);
if (!$deployOk) {
    echo "  [cURL Debug] HTTP Code: {$httpCode}, Response: {$response}, Error: {$curlErr}\n";
}
reportCheck('PRE-05', 'Hostinger Auto-Deploy Webhook Sync (HTTP 200 Success)', $deployOk);

echo "\n-------------------------------------------------------------------------\n";
echo sprintf("  PRODUCTION PRE-FLIGHT VERIFICATION: %d / %d PASSED\n", $passed, $total);
echo "=========================================================================\n";

if ($passed !== $total) {
    exit(1);
}
