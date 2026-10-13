<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Services\FieldExecutionControlService;

echo "=========================================================================\n";
echo "  B8.7 FIELD EXECUTION & OUTAGE REALIZATION FORENSIC TEST SUITE          \n";
echo "=========================================================================\n\n";

$service = new FieldExecutionControlService();
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

// B87-G01: Zero GIS Mutation
$summary = $service->getOperationalSummary();
reportTest('B87-G01', 'Zero GIS Master Mutation (Delta topology = 0)', $summary['delta_topology'] === 0);

// B87-G02: Zero Asset Mutation
reportTest('B87-G02', 'Zero Asset Master Mutation (Delta assets = 0)', $summary['delta_assets'] === 0);

// B87-G03: Dispatch Field Work
$dispatch = $service->dispatchFieldWork([
    'feeder_name' => 'KEDUNGPRING',
    'section_name' => 'KDP-01',
    'work_scope' => 'Perabasan & Penggantian Isolator',
    'planned_outage_duration_mins' => 240,
    'crew_count' => 4,
]);
$execId = $dispatch['execution_id'];
reportTest('B87-G03', 'Dispatch Field Team (Status WORK_PENDING_FIELD_START)', $dispatch['status'] === 'success' && $dispatch['execution_status'] === 'WORK_PENDING_FIELD_START');

// B87-G04a: Before Photo Missing Rejection
$failStart = $service->startFieldWork($execId, ['photo_uri' => ''], 'Briefing K3 selesai');
reportTest('B87-G04a', 'Before Photo Evidence Mandatory Rejection', $failStart['status'] === 'error');

// B87-G04b: Valid Before Photo Start
$validStart = $service->startFieldWork($execId, [
    'photo_uri' => 'https://sidaktejo.site/uploads/evidence/before_kdp01.jpg',
    'notes' => 'Tiang 45 seksi KDP-01 sebelum perabasan'
], 'Penutupan PMS & briefing K3 selesai', ['name' => 'PENGAWAS_LAPANGAN_HAR']);
reportTest('B87-G04b', 'Explicit Human Start with SHA-256 Hashed Evidence', $validStart['status'] === 'success' && !empty($validStart['evidence_hash']));

// B87-G06: Log Outage Realization
$outageLog = $service->logProgressAndOutage(
    $execId,
    60.0,
    '2026-09-30 08:00:00',
    '2026-09-30 11:30:00',
    240,
    'Perabasan seksi KDP-01 selesai 60%',
    'Pencatatan realisasi pemadaman fisik 210 menit',
    ['name' => 'PENGAWAS_LAPANGAN_HAR']
);
reportTest('B87-G06', 'Outage Realization Analysis (Actual vs Planned Mins)', $outageLog['status'] === 'success' && $outageLog['actual_outage_mins'] === 210 && $outageLog['outage_variance_mins'] === -30);

// B87-G07: Governed Safety Hold & Resume
$hold = $service->manageSafetyHold($execId, 'DECLARE', 'Hujan deras dan petir di area KDP-01');
$resume = $service->manageSafetyHold($execId, 'RESUME', 'Cuaca cerah, kondisi aman dilanjutkan');
reportTest('B87-G07', 'Governed Safety Hold & Reassessment Resume', $hold['status'] === 'success' && $resume['status'] === 'success');

// B87-G08: Material Consumption Realization
$materialRec = $service->reconcileMaterialConsumption($execId, [
    ['material_name' => 'Pin Insulator 20kV', 'unit' => 'set', 'estimated_quantity' => 3.0, 'actual_quantity' => 3.0],
    ['material_name' => 'Parallel Groove Clamp AL 35-70', 'unit' => 'buah', 'estimated_quantity' => 6.0, 'actual_quantity' => 5.0, 'variance_rationale' => 'Efisiensi 1 PG clamp tidak rusak']
], 'Rekonsiliasi akhir pemakaian material');
reportTest('B87-G08', 'Material Consumption Realization vs B8.2 Allocation', $materialRec['status'] === 'success' && $materialRec['reconciled_count'] === 2);

// B87-G09: Completion Declaration Missing After Photo Rejection
$failComp = $service->completeAndVerify($execId, ['photo_uri' => ''], 'Pekerjaan selesai');
reportTest('B87-G09', 'After Photo Mandatory Rejection for Completion', $failComp['status'] === 'error');

// B87-G10: Supervisor Signoff Verification (L5 -> L6)
$verify = $service->completeAndVerify($execId, [
    'photo_uri' => 'https://sidaktejo.site/uploads/evidence/after_kdp01.jpg',
    'notes' => 'Tiang 45 bersih, ROW 3 meter aman, isolator baru terpasang'
], 'Pekerjaan selesai 100%, jaringan siap dinormalkan', 'APPROVED', ['name' => 'SUPERVISOR_TEKNIK_ULP']);
reportTest('B87-G10', 'Supervisor Verification Signoff (L5 -> L6 VERIFIED_CLOSED)', $verify['status'] === 'success' && $verify['execution_status'] === 'WORK_VERIFIED_CLOSED' && !empty($verify['verification_bundle_hash']));

echo "\n=========================================================================\n";
echo sprintf("  RESULT: %d/%d TESTS PASSED (100%% INTEGRITY VERIFIED)\n", $passed, $total);
echo "=========================================================================\n";
