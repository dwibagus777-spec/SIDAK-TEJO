<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Services\FaultLocatorWorkspaceService;

echo "=========================================================================\n";
echo "  TRACK D3 FAULT LOCATOR WORKSPACE & FLI-1.0.0 FORENSIC TEST SUITE      \n";
echo "=========================================================================\n\n";

$service = new FaultLocatorWorkspaceService();
$passed = 0;
$total  = 10;

function reportTest($id, $title, $condition, $detail = '') {
    global $passed;
    if ($condition) {
        $passed++;
        echo sprintf("[PASS] %-10s : %s\n", $id, $title);
    } else {
        echo sprintf("[FAIL] %-10s : %s — %s\n", $id, $title, $detail);
    }
}

// D3-G01 & D3-G02: Controller & Summary Execution
$summary = $service->getOperationalSummary();
reportTest('D3-G01/02', 'Fault Locator Workspace Summary Execution', !empty($summary['engine_version']));

// D3-G04: FLI-1.0.0 Immutable Engine Binding
reportTest('D3-G04', 'FLI-1.0.0 Immutable Engine Binding Active', $summary['engine_version'] === 'FLI-1.0.0');

// D3-G05: Topology Snapshot Binding
reportTest('D3-G05', 'Bound to Topology Snapshot TOPOLOGY-20260928-245-81c43a7f', $summary['topology_snapshot'] === 'TOPOLOGY-20260928-245-81c43a7f');

// D3-G06: 245 Authoritative Translines Baseline
reportTest('D3-G06', 'Authoritative Translines Baseline (245 active TL, 9,418.37m span)', $summary['active_translines'] === 245);

// D3-G10: Zero GIS & Asset Mutation
reportTest('D3-G10', 'Zero GIS Master Mutation (Delta topology = 0, Delta assets = 0)', $summary['delta_topology'] === 0 && $summary['delta_assets'] === 0);

// Single Clear Candidate Run (RESOLVED)
$resResolved = $service->locateFault([
    'feeder_id'        => 'F-CANDRAMAS',
    'relay_distance_m' => 1250.0,
    'device_asset_id'  => 'AST-REC-005'
]);
reportTest('D3-G09', 'Single Clear Fault Candidate Outcome (RESOLVED)', $resResolved['outcome_status'] === 'RESOLVED' && strlen($resResolved['input_fingerprint']) === 64);

// D3-G07: Branch Ambiguity Handling (DISTANCE_AMBIGUOUS)
$resAmbiguous = $service->locateFault([
    'feeder_id'        => 'F-CANDRAMAS',
    'relay_distance_m' => 2500.0,
    'device_asset_id'  => 'AST-REC-005'
]);
reportTest('D3-G07', 'Branch Ambiguity Preserved (DISTANCE_AMBIGUOUS for T-Off branches)', $resAmbiguous['outcome_status'] === 'DISTANCE_AMBIGUOUS' && count($resAmbiguous['candidate_assets']) > 1);

// D3-G08: Out of Bounds Handling (UNRESOLVED)
$resUnresolved = $service->locateFault([
    'feeder_id'        => 'F-CANDRAMAS',
    'relay_distance_m' => 15000.0,
    'device_asset_id'  => 'AST-REC-005'
]);
reportTest('D3-G08', 'Out of Bounds Distance Handling (UNRESOLVED valid outcome)', $resUnresolved['outcome_status'] === 'UNRESOLVED');

// D3-G14: B6 Field Investigation Dispatch Creation
$dispatch = $service->createFieldDispatchPackage($resResolved['case_uuid']);
reportTest('D3-G14', 'B6 Field Investigation Dispatch Package Creation', $dispatch['status'] === 'success' && !empty($dispatch['dispatch_id']));

// D3-G15: 10x Deterministic Replay Verification
$replaysIdentical = true;
$firstFingerprint = null;

for ($i = 1; $i <= 10; $i++) {
    $run = $service->locateFault([
        'feeder_id'        => 'F-CANDRAMAS',
        'relay_distance_m' => 1250.0,
        'device_asset_id'  => 'AST-REC-005'
    ]);
    if ($firstFingerprint === null) {
        $firstFingerprint = $run['output_fingerprint'];
    } elseif ($run['output_fingerprint'] !== $firstFingerprint) {
        $replaysIdentical = false;
        break;
    }
}

reportTest('D3-G15', '10x Deterministic FLI Replay (100% SHA-256 fingerprint match)', $replaysIdentical);

echo "\n-------------------------------------------------------------------------\n";
echo sprintf("  FORENSIC AUDIT RESULT: %d / %d TESTS PASSED\n", $passed, $total);
echo "=========================================================================\n";

if ($passed !== $total) {
    exit(1);
}
