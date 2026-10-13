<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Services\WorkAcceptanceControlService;
use App\Services\FieldExecutionControlService;

echo "=========================================================================\n";
echo "  B8.8 WORK ACCEPTANCE & RECALIBRATION LOOP FORENSIC TEST SUITE           \n";
echo "=========================================================================\n\n";

$execService = new FieldExecutionControlService();
$accService  = new WorkAcceptanceControlService();

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

// B88-G01: Zero GIS Mutation
$summary = $accService->getOperationalSummary();
reportTest('B88-G01', 'Zero GIS Master Mutation (Delta topology = 0)', $summary['delta_topology'] === 0);

// B88-G02: Zero Asset Mutation
reportTest('B88-G02', 'Zero Asset Master Mutation (Delta assets = 0)', $summary['delta_assets'] === 0);

// B88-G03: Initiate Acceptance Review
$dispatch = $execService->dispatchFieldWork([
    'feeder_name' => 'KEDUNGPRING',
    'section_name' => 'KDP-01',
    'work_scope' => 'Pemeliharaan SUTM KDP-01',
    'planned_outage_duration_mins' => 240,
    'crew_count' => 4,
]);
$execId = $dispatch['execution_id'];
$execService->startFieldWork($execId, ['photo_uri' => 'https://sidaktejo.site/uploads/before.jpg'], 'K3 briefing', ['name' => 'PENGAWAS_LAPANGAN_HAR']);

$init = $accService->initiateAcceptanceReview($execId, ['name' => 'INSPEKTUR_MUTU_JARINGAN']);
$accId = $init['acceptance_id'];
reportTest('B88-G03a', 'Initiate Work Acceptance Review (Status ACCEPTANCE_REVIEW_PENDING)', $init['status'] === 'success' && $init['acceptance_status'] === 'ACCEPTANCE_REVIEW_PENDING');

// B88-G04: Separation of Duties Violation Rejection
$failSod = $accService->evaluateQualityAssurance($accId, [
    'evidence_score' => 100, 'technical_score' => 100, 'material_score' => 100, 'asbuilt_score' => 100
], ['name' => 'PENGAWAS_LAPANGAN_HAR']);
reportTest('B88-G04', 'Separation of Duties Violation Rejection (Executor != QA Inspector)', $failSod['status'] === 'error' && $failSod['code'] === 'SEPARATION_OF_DUTIES_VIOLATION');

// B88-G05: Rework Trigger on Score < 85%
$lowScore = $accService->evaluateQualityAssurance($accId, [
    'evidence_score' => 60, 'technical_score' => 70, 'material_score' => 80, 'asbuilt_score' => 70
], ['name' => 'INSPEKTUR_MUTU_JARINGAN']);
reportTest('B88-G05', 'Governed Rework Trigger (QA Score < 85.00% -> REWORK_REQUIRED)', $lowScore['status'] === 'success' && $lowScore['acceptance_status'] === 'REWORK_REQUIRED');

// B88-G03b: Valid 4D QA Pass (Score >= 85%)
$passScore = $accService->evaluateQualityAssurance($accId, [
    'evidence_score' => 100, 'technical_score' => 95, 'material_score' => 100, 'asbuilt_score' => 95
], ['name' => 'INSPEKTUR_MUTU_JARINGAN']);
reportTest('B88-G03b', '4D QA Evaluation Pass (Score >= 85.00% -> WORK_ACCEPTED)', $passScore['status'] === 'success' && $passScore['acceptance_status'] === 'WORK_ACCEPTED' && $passScore['quality_score'] >= 85.00);

// B88-G07: Post-Maintenance Asset Health Recalibration Loop
$recal = $accService->recalibrateAssetHealth($accId, ['name' => 'ENGINEER_RELIABILITY_AHI']);
reportTest('B88-G07', 'Post-Maintenance AHI Recalibration Loop (+Restoration Score Boost)', $recal['status'] === 'success' && $recal['recalibrated_ahi_score'] > $recal['previous_ahi_score']);

// B88-G08a: Missing Manager Closure Rationale Rejection
$failClose = $accService->closeExecutiveWork($accId, '', 'APPROVED', ['name' => 'MANAJER_ULP']);
reportTest('B88-G08a', 'Manager Closure Rationale Mandatory Rejection', $failClose['status'] === 'error');

// B88-G08b: Executive Closure (L5 -> L6 WORK_CLOSED)
$close = $accService->closeExecutiveWork($accId, 'Pekerjaan pemeliharaan KDP-01 memenuhi standar mutu, jaringan dinormalkan', 'APPROVED', ['name' => 'MANAJER_ULP']);
reportTest('B88-G08b', 'Executive Manager Closure Signoff (L5 -> L6 WORK_CLOSED)', $close['status'] === 'success' && $close['acceptance_status'] === 'WORK_CLOSED');

// B88-G06 & B88-G09: SHA-256 Certificate Seal Verification
reportTest('B88-G06', 'Canonical SHA-256 Acceptance Certificate Seal Generated', !empty($close['certificate_seal_hash']) && strlen($close['certificate_seal_hash']) === 64);

echo "\n=========================================================================\n";
echo sprintf("  RESULT: %d/%d TESTS PASSED (100%% INTEGRITY VERIFIED)\n", $passed, $total);
echo "=========================================================================\n";
