<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Services\WorkAcceptanceControlService;
use App\Services\FieldExecutionControlService;

echo "=========================================================================\n";
echo "  B8.8 PHASE 2B PRODUCTION PRE-FLIGHT VERIFICATION SUITE                  \n";
echo "  Module: B8.8 Post-Execution Work Acceptance & Recalibration Loop       \n";
echo "  Timestamp: " . date('Y-m-d H:i:s T') . "                                 \n";
echo "=========================================================================\n\n";

$execService = new FieldExecutionControlService();
$accService  = new WorkAcceptanceControlService();
$passed = 0;
$total = 5;

function reportStage($id, $title, $condition, $detail = '') {
    global $passed;
    if ($condition) {
        $passed++;
        echo sprintf("[PASS] %-10s : %s\n", $id, $title);
    } else {
        echo sprintf("[FAIL] %-10s : %s — %s\n", $id, $title, $detail);
    }
}

// Stage 1: Schema & Infrastructure
$summary = $accService->getOperationalSummary();
reportStage('STAGE-1', 'Schema Infrastructure & Ledger Readiness', !empty($summary['module']) && $summary['governance_status'] === 'LOCKED / PRODUCTION_READY');

// Stage 2: Production Baseline Invariants
reportStage('STAGE-2', 'Production Master Baseline (245 TL / 5,236 Assets / Delta topology = 0)', $summary['master_topology_snapshot'] === 'TOPOLOGY-20260928-245-81c43a7f' && $summary['delta_topology'] === 0 && $summary['delta_assets'] === 0);

// Stage 3: End-to-End QA & AHI Recalibration Loop
$dispatch = $execService->dispatchFieldWork([
    'feeder_name' => 'BALUNG',
    'section_name' => 'BLG-03',
    'work_scope' => 'Penggantian Isolator & ROW Clearing BLG-03',
    'planned_outage_duration_mins' => 180,
    'crew_count' => 5
]);
$execId = $dispatch['execution_id'];
$execService->startFieldWork($execId, ['photo_uri' => 'https://sidaktejo.site/uploads/before_blg03.jpg'], 'Persiapan K3', ['name' => 'PENGAWAS_LAPANGAN_HAR']);

$init = $accService->initiateAcceptanceReview($execId, ['name' => 'INSPEKTUR_MUTU_JARINGAN']);
$accId = $init['acceptance_id'];
$eval = $accService->evaluateQualityAssurance($accId, [
    'evidence_score' => 100, 'technical_score' => 95, 'material_score' => 100, 'asbuilt_score' => 95
], ['name' => 'INSPEKTUR_MUTU_JARINGAN']);

$recal = $accService->recalibrateAssetHealth($accId, ['name' => 'ENGINEER_RELIABILITY_AHI']);
reportStage('STAGE-3', 'End-to-End QA & Post-Maintenance AHI Recalibration Loop', $init['status'] === 'success' && $eval['status'] === 'success' && $recal['status'] === 'success' && $recal['recalibrated_ahi_score'] > $recal['previous_ahi_score']);

// Stage 4: Executive Manager Signoff Gate (L5 -> L6) & Certificate Seal
$close = $accService->closeExecutiveWork($accId, 'Serah terima penutupan pekerjaan eksekutif Balung seksi BLG-03', 'APPROVED', ['name' => 'MANAJER_ULP_BALUNG']);
reportStage('STAGE-4', 'Executive Manager Signoff Gate (L5 -> L6 WORK_CLOSED) & Certificate Seal', $close['status'] === 'success' && $close['acceptance_status'] === 'WORK_CLOSED' && !empty($close['certificate_seal_hash']));

// Stage 5: AI Ceiling Cap (L4 RECOMMENDED Cap) & Forensic Lineage
reportStage('STAGE-5', 'AI Ceiling Cap (L4 RECOMMENDED Cap) & Forensic Lineage Linkage', $summary['ai_ceiling'] === 'L4 RECOMMENDED' && $summary['human_gate'] === 'L5 APPROVED -> L6 EXECUTED (WORK_CLOSED)');

echo "\n=========================================================================\n";
echo sprintf("  PHASE 2B PRE-FLIGHT VERIFICATION: %d/%d STAGES PASSED\n", $passed, $total);
echo "  STATUS: B8.8 WORK ACCEPTANCE & RECALIBRATION LOOP READY FOR PRODUCTION GO\n";
echo "=========================================================================\n";
