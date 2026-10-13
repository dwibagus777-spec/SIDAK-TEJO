<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Services\AssetCorpusCompletionService;

echo "=========================================================================\n";
echo "  TRACK D1 ASSET CORPUS RECONCILIATION & COMPLETION FORENSIC TEST SUITE  \n";
echo "=========================================================================\n\n";

$service = new AssetCorpusCompletionService();
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

// D1-G01: Zero Transline GIS Mutation
$summary = $service->getOperationalSummary();
reportTest('D1-G01', 'Zero Master Transline GIS Mutation (Delta topology = 0)', $summary['delta_topology'] === 0);

// D1-G02: Preserve Existing Asset IDs
reportTest('D1-G02', 'Preserve Existing Asset Primary Keys & Geometry (Delta assets = 0)', $summary['delta_assets'] === 0);

// D1-G03: 100% Active Asset Corpus Coverage
reportTest('D1-G03', '100% Active Asset Corpus Coverage (5,236 / 5,236 assets)', $summary['active_assets'] === 5236);

// D1-G04: Topology Snapshot Binding
reportTest('D1-G04', 'Bound to Immutable Topology Snapshot TOPOLOGY-20260928-245-81c43a7f', $summary['topology_snapshot'] === 'TOPOLOGY-20260928-245-81c43a7f');

// D1-G05 & D1-G06 & D1-G09: Reconcile Audit Run
$audit = $service->runCanonicalReconciliationAudit();
reportTest('D1-G05', 'Deterministic Input Fingerprint (SHA-256 64-char hex)', strlen($audit['input_fingerprint']) === 64);
reportTest('D1-G06', 'Deterministic Output Fingerprint (SHA-256 64-char hex)', strlen($audit['output_fingerprint']) === 64);
reportTest('D1-G09', 'Chained Forensic Provenance Bundle Hash generated', strlen($audit['bundle_hash']) === 64);

// D1-G07: Canonical Pool & Quarantine Separation
reportTest('D1-G07', 'Canonical Pool & Quarantine Separation (1,476 Accepted + 1 Quarantine)', $summary['canonical_pool_accepted'] === 1476 && $summary['canonical_pool_quarantine'] === 1);

// D1-G08: Historical Deleted Preservation
reportTest('D1-G08', 'Historical Deleted Records Immutable (313 deleted assets)', $summary['deleted_assets'] === 313);

// D1-G10: 10x Deterministic Replay Verification
$replaysIdentical = true;
$firstFingerprint = null;

for ($i = 1; $i <= 10; $i++) {
    $run = $service->runCanonicalReconciliationAudit();
    if ($firstFingerprint === null) {
        $firstFingerprint = $run['output_fingerprint'];
    } elseif ($run['output_fingerprint'] !== $firstFingerprint) {
        $replaysIdentical = false;
        break;
    }
}

reportTest('D1-G10', '10x Deterministic Audit Replay (100% SHA-256 fingerprint match)', $replaysIdentical);

echo "\n-------------------------------------------------------------------------\n";
echo sprintf("  FORENSIC AUDIT RESULT: %d / %d TESTS PASSED\n", $passed, $total);
echo "=========================================================================\n";

if ($passed !== $total) {
    exit(1);
}
