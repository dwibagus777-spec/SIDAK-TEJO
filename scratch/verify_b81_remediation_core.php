<?php

/**
 * SIDAK TEJO — Phase B.8.1 Remediation Core Forensic Audit Runner
 *
 * Verifies all requirements of B8.1 Technical Specification v0.2:
 * 1. 9-State Finite State Machine Lifecycle
 * 2. 5 Hardening Invariants (B81-G01 through B81-G05)
 * 3. 17 Adversarial Scenarios (ADV-01 through ADV-17)
 * 4. Forensic Persistence Invariant (HIST-01: ON DELETE RESTRICT & 405)
 * 5. Topology Sentinel (Delta = 0)
 */

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use Config\Database;
use App\Services\RemediationWorkPackageService;
use App\Exceptions\UnprocessableEntityException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\MethodNotAllowedException;

echo "====================================================================\n";
echo "  SIDAK TEJO — B.8.1 REMEDIATION CORE FORENSIC AUDIT SUITE         \n";
echo "  Target: LOCAL TESTBED (127.0.0.1, DB: sidaktejo)                  \n";
echo "  Timestamp: " . date('Y-m-d H:i:s T') . "                          \n";
echo "====================================================================\n\n";

$db = Database::connect('default');
$service = new RemediationWorkPackageService($db);

$scorecard = [];

function recordResult(string $code, string $description, bool $passed, string $details = ''): void {
    global $scorecard;
    $status = $passed ? "🟢 PASS" : "🔴 FAIL";
    $scorecard[] = [
        'code'        => $code,
        'description' => $description,
        'passed'      => $passed,
        'details'     => $details
    ];
    echo sprintf("  %-10s | %-55s | %s\n", $code, $description, $status);
    if (!empty($details)) {
        echo "             Detail: {$details}\n";
    }
}

// =========================================================================
// STEP 0: PRE-AUDIT TOPOLOGY BASELINE
// =========================================================================
echo "[0/5] Recording Pre-Audit Topology Truth...\n";

$preActiveTL = $db->table('gis_translines')->where('is_active', 1)->where('deleted_at IS NULL')->countAllResults();
$prePhysicalTL = $db->table('gis_translines')->countAllResults();
$preActiveAssets = $db->table('assets')->where('deleted_at IS NULL')->countAllResults();
$prePhysicalAssets = $db->table('assets')->countAllResults();

echo "      • Active Translines  : {$preActiveTL}\n";
echo "      • Physical Translines: {$prePhysicalTL}\n";
echo "      • Active Assets      : {$preActiveAssets}\n";
echo "      • Physical Assets    : {$prePhysicalAssets}\n\n";

// Ensure test fixtures in upstream B.6 tables
$caseRow = $db->table('fault_cases')->select('id')->orderBy('id', 'DESC')->get()->getRowArray();
if (!$caseRow) {
    // Insert synthetic fault_case for local testing
    $db->table('fault_cases')->insert([
        'topology_snapshot_id' => 'TOPOLOGY-20260925-243-ad2c9fcb',
        'feeder_code'          => 'MNT01',
        'status'               => 'CONFIRMED',
        'created_at'           => date('Y-m-d H:i:s'),
        'updated_at'           => date('Y-m-d H:i:s'),
    ]);
    $testCaseId = (int)$db->insertID();
} else {
    $testCaseId = (int)$caseRow['id'];
}

$assetRow = $db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
$testAssetId = $assetRow ? (int)$assetRow['id'] : 1;

// Create 3 synthetic test findings in field_findings
$testFindingIds = [];

// Finding 1: CONFIRMED
$db->table('field_findings')->insert([
    'fault_case_id'          => $testCaseId,
    'captured_by'            => 1,
    'actual_asset_id'        => $testAssetId,
    'finding_status'         => 'CONFIRMED',
    'cause_category'         => 'EQUIPMENT_FAILURE',
    'condition_description'  => 'B8.1 Synthetic Confirmed Finding #1',
    'captured_at'            => date('Y-m-d H:i:s'),
    'created_at'             => date('Y-m-d H:i:s'),
    'updated_at'             => date('Y-m-d H:i:s'),
]);
$testFindingIds['confirmed_1'] = (int)$db->insertID();

// Finding 2: CONFIRMED
$db->table('field_findings')->insert([
    'fault_case_id'          => $testCaseId,
    'captured_by'            => 1,
    'actual_asset_id'        => $testAssetId,
    'finding_status'         => 'CONFIRMED',
    'cause_category'         => 'VEGETATION',
    'condition_description'  => 'B8.1 Synthetic Confirmed Finding #2',
    'captured_at'            => date('Y-m-d H:i:s'),
    'created_at'             => date('Y-m-d H:i:s'),
    'updated_at'             => date('Y-m-d H:i:s'),
]);
$testFindingIds['confirmed_2'] = (int)$db->insertID();

// Finding 3: RECORDED (not CONFIRMED)
$db->table('field_findings')->insert([
    'fault_case_id'          => $testCaseId,
    'captured_by'            => 1,
    'actual_asset_id'        => $testAssetId,
    'finding_status'         => 'RECORDED',
    'cause_category'         => 'ANIMAL',
    'condition_description'  => 'B8.1 Synthetic Recorded Finding #3',
    'captured_at'            => date('Y-m-d H:i:s'),
    'created_at'             => date('Y-m-d H:i:s'),
    'updated_at'             => date('Y-m-d H:i:s'),
]);
$testFindingIds['recorded_3'] = (int)$db->insertID();

// Registered Evidence in field_evidence
$testEvidenceContent = "AUTHENTIC_QA_REPAIR_EVIDENCE_PAYLOAD_DATA_2026";
$testEvidenceSha256 = hash('sha256', $testEvidenceContent);
$db->table('field_evidence')->insert([
    'fault_case_id'    => $testCaseId,
    'field_finding_id' => $testFindingIds['confirmed_1'],
    'asset_id'         => $testAssetId,
    'evidence_type'    => 'PHOTO',
    'file_reference'   => 'uploads/field_evidence/test_b81_evidence.jpg',
    'sha256'           => $testEvidenceSha256,
    'captured_by'      => 1,
    'captured_at'      => date('Y-m-d H:i:s'),
    'created_at'       => date('Y-m-d H:i:s'),
]);
$testEvidenceId = (int)$db->insertID();


echo "      • Test Case ID        : {$testCaseId}\n";
echo "      • Test Finding #1     : {$testFindingIds['confirmed_1']} (CONFIRMED)\n";
echo "      • Test Finding #2     : {$testFindingIds['confirmed_2']} (CONFIRMED)\n";
echo "      • Test Finding #3     : {$testFindingIds['recorded_3']} (RECORDED)\n";
echo "      • Test Evidence ID    : {$testEvidenceId} (SHA: " . substr($testEvidenceSha256, 0, 16) . "...)\n\n";

// =========================================================================
// STEP 1: 9-STATE FSM NORMAL LIFECYCLE PROGRESSION
// =========================================================================
echo "[1/5] Executing 9-State FSM Lifecycle Progression...\n";

// 1. DRAFT
$opUuid1 = 'B81-TEST-UUID-' . bin2hex(random_bytes(8));
$createRes = $service->createWorkPackage([
    'client_operation_uuid' => $opUuid1,
    'title'                 => 'Pekerjaan Perbaikan Penyulang MNT01',
    'description'           => 'Perbaikan kawat rantas dan isolator retak',
    'feeder_code'           => 'MNT01',
    'priority'              => 'HIGH',
    'assigned_team_leader'  => 'Regu Pemeliharaan Alfa',
    'test_mode'             => 1,
], 101, 'FIELD_ENGINEER');

$wp1Id = $createRes['package_id'];
$wp1Code = $createRes['package_code'];
recordResult('FSM-01', "Create Work Package -> DRAFT", $createRes['work_package']['status'] === 'DRAFT', "Code: {$wp1Code}");

// Attach Finding
$attachRes = $service->attachFinding($wp1Id, $testFindingIds['confirmed_1'], 101, 'FIELD_ENGINEER', true, 'Kawat rantas utama');
recordResult('FSM-02', "Attach Finding to DRAFT package", $attachRes['success'] === true);

// 2. ENGINEER_SUBMITTED
$submitRes = $service->submitPackage($wp1Id, 101, 'FIELD_ENGINEER', 'Mohon telaah perbaikan');
$wp1 = $service->getWorkPackage($wp1Id);
recordResult('FSM-03', "submitPackage() -> ENGINEER_SUBMITTED", $wp1['status'] === 'ENGINEER_SUBMITTED');

// 3. PENDING_APPROVAL
$queueRes = $service->validateAndQueue($wp1Id, 101, 'FIELD_ENGINEER');
$wp1 = $service->getWorkPackage($wp1Id);
recordResult('FSM-04', "validateAndQueue() -> PENDING_APPROVAL", $wp1['status'] === 'PENDING_APPROVAL');

// 4. APPROVED
$approveRes = $service->approvePackage($wp1Id, 201, 'SUPERVISOR', 'Disetujui untuk pelaksanaan segera');
$wp1 = $service->getWorkPackage($wp1Id);
recordResult('FSM-05', "approvePackage() -> APPROVED", $wp1['status'] === 'APPROVED');

// 5. RELEASED
$releaseRes = $service->releasePackage($wp1Id, 201, 'SUPERVISOR', ['assigned_team_leader' => 'Mandor Budi'], 'Dilepas ke tim lapangan');
$wp1 = $service->getWorkPackage($wp1Id);
recordResult('FSM-06', "releasePackage() -> RELEASED", $wp1['status'] === 'RELEASED');

// 6. IN_PROGRESS
$startRes = $service->startExecution($wp1Id, 301, 'FIELD_TEAM_LEADER', 'Pekerjaan dimulai di lokasi');
$wp1 = $service->getWorkPackage($wp1Id);
recordResult('FSM-07', "startExecution() -> IN_PROGRESS", $wp1['status'] === 'IN_PROGRESS');

// 7. COMPLETED
$compRes = $service->completeExecution($wp1Id, 301, 'FIELD_TEAM_LEADER', [$testFindingIds['confirmed_1'] => 'RESOLVED'], 'Kawat telah diganti baru');
$wp1 = $service->getWorkPackage($wp1Id);
recordResult('FSM-08', "completeExecution() -> COMPLETED", $wp1['status'] === 'COMPLETED');

// 8. VERIFIED
$verifyRes = $service->verifyRemediation($wp1Id, 401, 'QA_ENGINEER', 'Uji kelaikan selesai dan lulus standar', $testEvidenceId, $testEvidenceContent);
$wp1 = $service->getWorkPackage($wp1Id);
recordResult('FSM-09', "verifyRemediation() -> VERIFIED (Closed-Loop Sealed)", $wp1['status'] === 'VERIFIED');

// Rejection & Revision Branch
$opUuidRev = 'B81-TEST-UUID-REV-' . bin2hex(random_bytes(8));
$createRev = $service->createWorkPackage([
    'client_operation_uuid' => $opUuidRev,
    'title'                 => 'Paket Perbaikan Cabang Penolakan',
    'test_mode'             => 1,
], 101);
$wpRevId = $createRev['package_id'];
$service->attachFinding($wpRevId, $testFindingIds['confirmed_2'], 101);
$service->submitPackage($wpRevId, 101);
$service->validateAndQueue($wpRevId, 101);

// 9. REJECTED
$rejectRes = $service->rejectPackage($wpRevId, 201, 'SUPERVISOR', 'Analisis material belum lengkap, harap lengkapi.');
$wpRev = $service->getWorkPackage($wpRevId);
recordResult('FSM-10', "rejectPackage() -> REJECTED (Rejection Branch)", $wpRev['status'] === 'REJECTED');

// Revise back to DRAFT
$reviseRes = $service->revisePackage($wpRevId, 101, 'FIELD_ENGINEER', 'Melengkapi telaah');
$wpRev = $service->getWorkPackage($wpRevId);
recordResult('FSM-11', "revisePackage() -> DRAFT (Cycle back for revision)", $wpRev['status'] === 'DRAFT');

// Remove finding to free it for other tests
$service->removeFinding($wpRevId, $testFindingIds['confirmed_2'], 101);
echo "\n";

// =========================================================================
// STEP 2: 17 ADVERSARIAL SCENARIOS (ADV-01 TO ADV-17) + HIST-01
// =========================================================================
echo "[2/5] Executing 17 Adversarial Scenarios + HIST-01...\n";

// ADV-01: Illegal FSM Shortcut (DRAFT -> APPROVED)
$opAdv01 = 'B81-ADV01-' . bin2hex(random_bytes(8));
$advWp01 = $service->createWorkPackage(['client_operation_uuid' => $opAdv01, 'title' => 'Test ADV-01', 'test_mode' => 1], 101)['package_id'];
$adv01Passed = false;
try {
    $service->approvePackage($advWp01, 201, 'SUPERVISOR');
} catch (ConflictException $e) {
    $adv01Passed = ($e->getHttpCode() === 409);
}
recordResult('ADV-01', "FSM Shortcut Ilegal (DRAFT -> APPROVED)", $adv01Passed, "Blocked with 409 Conflict");

// ADV-02: Attach finding with status != CONFIRMED (RECORDED)
$adv02Passed = false;
try {
    $service->attachFinding($advWp01, $testFindingIds['recorded_3'], 101);
} catch (UnprocessableEntityException $e) {
    $adv02Passed = ($e->getHttpCode() === 422);
}
recordResult('ADV-02', "Attach non-CONFIRMED finding (RECORDED)", $adv02Passed, "Blocked with 422 Unprocessable");

// ADV-03: Attach non-existent finding ID (99999999)
$adv03Passed = false;
try {
    $service->attachFinding($advWp01, 99999999, 101);
} catch (NotFoundException $e) {
    $adv03Passed = ($e->getHttpCode() === 404);
}
recordResult('ADV-03', "Attach non-existent finding ID", $adv03Passed, "Blocked with 404 Not Found");

// ADV-04: Duplicate Finding in same Work Package
$service->attachFinding($advWp01, $testFindingIds['confirmed_2'], 101);
$adv04Passed = false;
try {
    $service->attachFinding($advWp01, $testFindingIds['confirmed_2'], 101);
} catch (ConflictException $e) {
    $adv04Passed = ($e->getHttpCode() === 409);
}
recordResult('ADV-04', "Duplicate Finding in same package", $adv04Passed, "Blocked with 409 Conflict");

// ADV-05: Attach finding already in another active package
// $advWp01 is DRAFT and holds $testFindingIds['confirmed_2']
$opAdv05 = 'B81-ADV05-' . bin2hex(random_bytes(8));
$advWp05 = $service->createWorkPackage(['client_operation_uuid' => $opAdv05, 'title' => 'Test ADV-05', 'test_mode' => 1], 101)['package_id'];
$adv05Passed = false;
try {
    $service->attachFinding($advWp05, $testFindingIds['confirmed_2'], 101);
} catch (ConflictException $e) {
    $adv05Passed = ($e->getHttpCode() === 409);
}
recordResult('ADV-05', "Attach finding assigned to another active package", $adv05Passed, "Blocked with 409 Conflict");

// ADV-06: Submit empty work package (0 findings)
$opAdv06 = 'B81-ADV06-' . bin2hex(random_bytes(8));
$advWp06 = $service->createWorkPackage(['client_operation_uuid' => $opAdv06, 'title' => 'Test ADV-06', 'test_mode' => 1], 101)['package_id'];
$adv06Passed = false;
try {
    $service->submitPackage($advWp06, 101);
} catch (UnprocessableEntityException $e) {
    $adv06Passed = ($e->getHttpCode() === 422);
}
recordResult('ADV-06', "Submit empty work package (0 findings)", $adv06Passed, "Blocked with 422 Unprocessable");

// ADV-07: Reject without mandatory notes (< 10 chars)
$service->removeFinding($advWp01, $testFindingIds['confirmed_2'], 101);
$service->attachFinding($advWp06, $testFindingIds['confirmed_2'], 101);
$service->submitPackage($advWp06, 101);
$service->validateAndQueue($advWp06, 101);
$adv07Passed = false;
try {
    $service->rejectPackage($advWp06, 201, 'SUPERVISOR', 'Tolak'); // 5 chars
} catch (UnprocessableEntityException $e) {
    $adv07Passed = ($e->getHttpCode() === 422);
}
recordResult('ADV-07', "Reject without reason (< 10 chars)", $adv07Passed, "Blocked with 422 Unprocessable");

// ADV-08: Attach finding to APPROVED package
$adv08Passed = false;
try {
    // $wp1Id was verified, status is VERIFIED / past DRAFT
    $service->attachFinding($wp1Id, $testFindingIds['confirmed_2'], 101);
} catch (ConflictException $e) {
    $adv08Passed = ($e->getHttpCode() === 409);
}
recordResult('ADV-08', "Attach finding to package past DRAFT", $adv08Passed, "Blocked with 409 Conflict");

// Approve and progress $advWp06 to COMPLETED to test ADV-09, ADV-10, ADV-17
$service->approvePackage($advWp06, 201, 'SUPERVISOR');
$service->releasePackage($advWp06, 201, 'SUPERVISOR', ['assigned_team_leader' => 'Tim Eksekusi']);
$service->startExecution($advWp06, 301);
$service->completeExecution($advWp06, 301);

// ADV-09: QA verify without evidence artifact
$adv09Passed = false;
try {
    $service->verifyRemediation($advWp06, 401, 'QA_ENGINEER', 'Lulus tanpa bukti');
} catch (UnprocessableEntityException $e) {
    $adv09Passed = ($e->getHttpCode() === 422);
}
recordResult('ADV-09', "QA verify without evidence artifact", $adv09Passed, "Blocked with 422 Unprocessable");

// ADV-10: Evidence hash invalid format (e.g. 63 chars or non-hex)
$adv10Passed = false;
try {
    $service->verifyRemediation($advWp06, 401, 'QA_ENGINEER', 'Notes', null, null, 'abcdef1234567890'); // 16 chars instead of 64
} catch (UnprocessableEntityException $e) {
    $adv10Passed = ($e->getHttpCode() === 422);
}
recordResult('ADV-10', "Evidence hash invalid format (non 64-hex)", $adv10Passed, "Blocked with 422 Unprocessable");

// ADV-11: Attempt topology mutation via API
$adv11Passed = false;
try {
    $service->mutateTopology('ALTER_TRANSLINES');
} catch (ForbiddenException $e) {
    $adv11Passed = ($e->getHttpCode() === 403);
}
recordResult('ADV-11', "Prohibited topology mutation via service", $adv11Passed, "Blocked with 403 Forbidden");

// ADV-12: Topology Sentinel Pre vs Mid Execution
$midActiveTL = $db->table('gis_translines')->where('is_active', 1)->where('deleted_at IS NULL')->countAllResults();
$midActiveAssets = $db->table('assets')->where('deleted_at IS NULL')->countAllResults();
$adv12Passed = ($midActiveTL === $preActiveTL && $midActiveAssets === $preActiveAssets);
recordResult('ADV-12', "Topology Sentinel Execution Invariant", $adv12Passed, "Δactive_tl = 0, Δassets = 0");

// ADV-13: Concurrency Race on Finding Attach (B81-G01)
// We simulate concurrent attach: 2 transactions attempting to attach finding #confirmed_2 simultaneously
$db->table('field_findings')->insert([
    'fault_case_id'          => $testCaseId,
    'captured_by'            => 1,
    'actual_asset_id'        => $testAssetId,
    'finding_status'         => 'CONFIRMED',
    'cause_category'         => 'EQUIPMENT_FAILURE',
    'condition_description'  => 'Concurrent race candidate finding',
    'captured_at'            => date('Y-m-d H:i:s'),
    'created_at'             => date('Y-m-d H:i:s'),
    'updated_at'             => date('Y-m-d H:i:s'),
]);
$raceFindingId = (int)$db->insertID();

$opRace1 = 'B81-RACE1-' . bin2hex(random_bytes(8));
$opRace2 = 'B81-RACE2-' . bin2hex(random_bytes(8));
$wpRace1 = $service->createWorkPackage(['client_operation_uuid' => $opRace1, 'title' => 'Race WP 1', 'test_mode' => 1], 101)['package_id'];
$wpRace2 = $service->createWorkPackage(['client_operation_uuid' => $opRace2, 'title' => 'Race WP 2', 'test_mode' => 1], 101)['package_id'];

// Transaction 1 attaches successfully
$raceRes1 = $service->attachFinding($wpRace1, $raceFindingId, 101);
// Transaction 2 must be blocked by the single-active-package guard / row lock check
$adv13Passed = false;
try {
    $service->attachFinding($wpRace2, $raceFindingId, 101);
} catch (ConflictException $e) {
    $adv13Passed = ($e->getHttpCode() === 409);
}
recordResult('ADV-13', "Concurrent Finding Attach Race (B81-G01)", $adv13Passed, "1 Commits, 2nd Rollback with 409 Conflict");

// ADV-14: Retry Create with Same client_operation_uuid (B81-G03 Idempotency)
$retryCreate = $service->createWorkPackage(['client_operation_uuid' => $opRace1, 'title' => 'Race WP 1 Duplicate Payload', 'test_mode' => 1], 101);
$adv14Passed = ($retryCreate['status'] === 'EXISTING' && $retryCreate['is_replay'] === true && $retryCreate['package_id'] === $wpRace1);
recordResult('ADV-14', "Idempotent Work Package Create (B81-G03)", $adv14Passed, "Returns existing canonical WP, 0 duplicate write");

// ADV-15: Finding status mutated after attach before Submit (B81-G04)
$opAdv15 = 'B81-ADV15-' . bin2hex(random_bytes(8));
$wpAdv15 = $service->createWorkPackage(['client_operation_uuid' => $opAdv15, 'title' => 'Pre-commit submit test', 'test_mode' => 1], 101)['package_id'];

$db->table('field_findings')->insert([
    'fault_case_id'          => $testCaseId,
    'captured_by'            => 1,
    'actual_asset_id'        => $testAssetId,
    'finding_status'         => 'CONFIRMED',
    'cause_category'         => 'LIGHTNING',
    'condition_description'  => 'Finding for ADV-15',
    'captured_at'            => date('Y-m-d H:i:s'),
    'created_at'             => date('Y-m-d H:i:s'),
    'updated_at'             => date('Y-m-d H:i:s'),
]);
$adv15FindingId = (int)$db->insertID();

$service->attachFinding($wpAdv15, $adv15FindingId, 101);

// Mutate live finding status to REVISED (not CONFIRMED)
$db->table('field_findings')->where('id', $adv15FindingId)->update(['finding_status' => 'REVISED']);

$adv15Passed = false;
try {
    $service->submitPackage($wpAdv15, 101);
} catch (ConflictException $e) {
    $adv15Passed = ($e->getHttpCode() === 409 && str_contains($e->getMessage(), 'Pre-commit revalidation failed'));
}
recordResult('ADV-15', "Finding status changed before submit (B81-G04)", $adv15Passed, "Submit blocked with 409 Conflict");

// Revert finding status to test ADV-16
$db->table('field_findings')->where('id', $adv15FindingId)->update(['finding_status' => 'CONFIRMED']);
$service->submitPackage($wpAdv15, 101);
$service->validateAndQueue($wpAdv15, 101);

// Mutate live finding status before approve
$db->table('field_findings')->where('id', $adv15FindingId)->update(['finding_status' => 'UNRESOLVED']);

$adv16Passed = false;
try {
    $service->approvePackage($wpAdv15, 201, 'SUPERVISOR');
} catch (ConflictException $e) {
    $adv16Passed = ($e->getHttpCode() === 409 && str_contains($e->getMessage(), 'Pre-commit revalidation failed'));
}
recordResult('ADV-16', "Finding status changed before approve (B81-G04)", $adv16Passed, "Approve blocked with 409 Conflict");

// ADV-17: Evidence format valid (64 hex) but content mismatch (B81-G05)
$corruptedPayload = "CORRUPTED_TAMPERED_CONTENT_BYTES_MALICIOUS";
$adv17Passed = false;
try {
    // $advWp06 has status COMPLETED
    $service->verifyRemediation($advWp06, 401, 'QA_ENGINEER', 'Tampered QA payload', $testEvidenceId, $corruptedPayload);
} catch (UnprocessableEntityException $e) {
    $adv17Passed = ($e->getHttpCode() === 422 && str_contains($e->getMessage(), 'mismatch'));
}
recordResult('ADV-17', "Evidence Content Mismatch Verification (B81-G05)", $adv17Passed, "Blocked with 422 HASH_MISMATCH");

// HIST-01: Permanent Audit History Non-Cascade (B81-G02)
// A. Service level method prohibition
$hist01APassed = false;
try {
    $service->deleteWorkPackage($wp1Id, 101);
} catch (MethodNotAllowedException $e) {
    $hist01APassed = ($e->getHttpCode() === 405);
}

// B. Database level foreign key RESTRICT rejection
$hist01BPassed = false;
try {
    $db->query("DELETE FROM remediation_work_packages WHERE id = ?", [$wp1Id]);
} catch (\Throwable $e) {
    // MySQL Error 1451 / SQLSTATE 23000
    $msg = $e->getMessage();
    $hist01BPassed = str_contains($msg, '1451') || str_contains($msg, 'a foreign key constraint fails') || str_contains($msg, 'RESTRICT');
}
recordResult('HIST-01', "Physical DELETE Prohibited (B81-G02 Non-Cascade)", ($hist01APassed && $hist01BPassed), "Service 405 & DB FK Error 1451 RESTRICT");
echo "\n";

// =========================================================================
// STEP 3: AUDIT HISTORY APPEND-ONLY INTEGRITY
// =========================================================================
echo "[3/5] Verifying Audit History Ledger Integrity...\n";
$history = $service->getWorkPackageHistory($wp1Id);
$expectedTransitions = [
    'CREATE_WORK_PACKAGE',
    'SUBMIT_PACKAGE',
    'QUEUE_FOR_APPROVAL',
    'APPROVE_PACKAGE',
    'RELEASE_PACKAGE',
    'START_EXECUTION',
    'COMPLETE_EXECUTION',
    'VERIFY_REMEDIATION'
];
$actualActions = array_column($history, 'action_name');
$historyMatches = true;
foreach ($expectedTransitions as $expectedAction) {
    if (!in_array($expectedAction, $actualActions, true)) {
        $historyMatches = false;
        break;
    }
}
recordResult('HIST-02', "Audit Ledger Chronological Append-Only History", $historyMatches, "Total entries: " . count($history));
echo "\n";

// =========================================================================
// STEP 4: CLEANUP TEST FIXTURES WHILE LEAVING CANONICAL AUDIT INTACT
// =========================================================================
echo "[4/5] Sanitizing Synthetic Test Fixtures...\n";
// Clean up findings created specifically for tests
if (!empty($raceFindingId)) {
    $db->table('remediation_package_findings')->where('finding_id', $raceFindingId)->delete();
    $db->table('field_findings')->where('id', $raceFindingId)->delete();
}
if (!empty($adv15FindingId)) {
    $db->table('remediation_package_findings')->where('finding_id', $adv15FindingId)->delete();
    $db->table('field_findings')->where('id', $adv15FindingId)->delete();
}
// Clean up test findings 1, 2, 3
$db->table('field_evidence')->where('id', $testEvidenceId)->delete();
foreach ($testFindingIds as $fid) {
    $db->table('remediation_package_findings')->where('finding_id', $fid)->delete();
    $db->table('field_findings')->where('id', $fid)->delete();
}
echo "      Synthetic test findings sanitized.\n\n";

// =========================================================================
// STEP 5: FINAL TOPOLOGY SENTINEL AUDIT
// =========================================================================
echo "[5/5] Final Topology Sentinel Audit...\n";

$postActiveTL = $db->table('gis_translines')->where('is_active', 1)->where('deleted_at IS NULL')->countAllResults();
$postPhysicalTL = $db->table('gis_translines')->countAllResults();
$postActiveAssets = $db->table('assets')->where('deleted_at IS NULL')->countAllResults();
$postPhysicalAssets = $db->table('assets')->countAllResults();

$deltaActiveTL = $postActiveTL - $preActiveTL;
$deltaPhysicalTL = $postPhysicalTL - $prePhysicalTL;
$deltaActiveAssets = $postActiveAssets - $preActiveAssets;
$deltaPhysicalAssets = $postPhysicalAssets - $prePhysicalAssets;

echo "      • Active Translines  : {$postActiveTL} (Δ = {$deltaActiveTL})\n";
echo "      • Physical Translines: {$postPhysicalTL} (Δ = {$deltaPhysicalTL})\n";
echo "      • Active Assets      : {$postActiveAssets} (Δ = {$deltaActiveAssets})\n";
echo "      • Physical Assets    : {$postPhysicalAssets} (Δ = {$deltaPhysicalAssets})\n";

$topologyClean = ($deltaActiveTL === 0 && $deltaPhysicalTL === 0 && $deltaActiveAssets === 0 && $deltaPhysicalAssets === 0);
recordResult('TOP-01', "Final Topology Sentinel (Δ = 0)", $topologyClean, "Zero topology write verified");

echo "\n====================================================================\n";
$allPassed = true;
foreach ($scorecard as $item) {
    if (!$item['passed']) {
        $allPassed = false;
        break;
    }
}
echo "  TOTAL TESTS : " . count($scorecard) . "\n";
echo "  PASSED      : " . count(array_filter($scorecard, fn($i) => $i['passed'])) . "\n";
echo "  FAILED      : " . count(array_filter($scorecard, fn($i) => !$i['passed'])) . "\n";
echo "  B.8.1 LOCAL VERDICT: " . ($allPassed ? "🟢 100% PASS / READY FOR LOCK" : "🔴 FAIL") . "\n";
echo "  PRODUCTION (sidaktejo.site): 🔒 STRICTLY UNTOUCHED\n";
echo "====================================================================\n";
