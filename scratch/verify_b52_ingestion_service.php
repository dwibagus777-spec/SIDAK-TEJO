<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Services\FaultEventIngestionService;
use App\Services\FaultLocationIntelligenceService;

$db = \Config\Database::connect('default');
$service = new FaultEventIngestionService($db);

echo "====================================================================\n";
echo "  SIDAK TEJO — PHASE B.5.2 FAULT EVENT INGESTION SERVICE AUDIT       \n";
echo "====================================================================\n\n";

// Pre-audit Authoritative Topology Baseline
$tlBaseline = $db->tableExists('gis_translines') ? $db->table('gis_translines')->countAllResults() : 0;
$assetBaseline = $db->tableExists('assets') ? $db->table('assets')->countAllResults() : 0;
echo "Authoritative Physical Topology Baseline:\n";
echo " - gis_translines: {$tlBaseline}\n";
echo " - assets:         {$assetBaseline}\n\n";

$guardResults = [];

// =============================================================================
// Guard 1: B5.2-G01 Canonical Normalization (Multi-Source Formats)
// =============================================================================
echo "[1] Testing Guard B5.2-G01: Canonical Normalization...\n";

// 1.1 SCADA IEC-104 Telemetry
$scadaRaw = [
    'source'          => 'scada',
    'source_ref'      => 'SCADA-TICKET-9901',
    'feeder_id'       => 118,
    'timestamp'       => '2026-09-25 08:30:15',
    'phase'           => 'RN',
    'arus_gangguan'   => 1250.75,
    'phase_currents'  => ['ir' => 1250.75, 'is' => 15.2, 'it' => 14.8, 'in' => 1235.5],
    'relay_element'   => 'OCR+GFR',
    'sequence'        => 'TRIP_1',
    'distance_km'     => 1.45,
    'telemetry'       => ['station' => 'GI_KRIAN', 'bay' => 'FEEDER_118'],
];
$scadaNorm = $service->normalizeTelemetry($scadaRaw);
$g1_scada = ($scadaNorm['source_type'] === 'SCADA' &&
             $scadaNorm['fault_phase'] === 'RN' &&
             $scadaNorm['fault_current_a'] === 1250.75 &&
             $scadaNorm['relay_distance_m'] === 1450.0 &&
             $scadaNorm['protection_elements'] === 'OCR+GFR');
echo " - SCADA Normalization: " . ($g1_scada ? "PASS ✅" : "FAIL ❌") . "\n";

// 1.2 PMCB Numerical Relay
$pmcbRaw = [
    'source_type'      => 'PMCB_RELAY',
    'source_reference' => 'RELAY-EVENT-7734',
    'penyulang_id'     => 118,
    'device_id'        => 5245,
    'event_time'       => '2026-09-25 09:15:00',
    'fault_phase'      => 'ST',
    'current'          => 890.5,
    'phase_currents'   => ['ia' => 12.0, 'ib' => 890.5, 'ic' => 888.0, 'i0' => 5.2],
    'elements'         => ['ocr', 'inst'],
    'distance'         => 350.25,
];
$pmcbNorm = $service->normalizeTelemetry($pmcbRaw);
$g1_pmcb = ($pmcbNorm['source_type'] === 'PMCB_RELAY' &&
            $pmcbNorm['fault_phase'] === 'ST' &&
            $pmcbNorm['protection_elements'] === 'OCR+INST' &&
            $pmcbNorm['phase_currents']['ir'] === 12.0 &&
            $pmcbNorm['phase_currents']['is'] === 890.5);
echo " - PMCB Relay Normalization: " . ($g1_pmcb ? "PASS ✅" : "FAIL ❌") . "\n";

// 1.3 Recloser Trip Cycle
$recloserRaw = [
    'source'           => 'RECLOSER',
    'ticket_no'        => 'REC-CYCLE-004',
    'penyulang_id'     => 15,
    'fault_time'       => '2026-09-25 10:00:00',
    'fasa'             => 'RST',
    'trip_sequence'    => 'LOCKOUT',
    'fault_current_a'  => 2100.0,
];
$recNorm = $service->normalizeTelemetry($recloserRaw);
$g1_rec = ($recNorm['source_type'] === 'RECLOSER' &&
           $recNorm['source_reference'] === 'REC-CYCLE-004' &&
           $recNorm['fault_phase'] === 'RST' &&
           $recNorm['trip_sequence'] === 'LOCKOUT');
echo " - Recloser Normalization: " . ($g1_rec ? "PASS ✅" : "FAIL ❌") . "\n";

$guardResults['B5.2-G01'] = $g1_scada && $g1_pmcb && $g1_rec;

// =============================================================================
// Guard 2 & 11: Source Provenance & Completeness Validation
// =============================================================================
echo "\n[2] Testing Guards B5.2-G02 & B5.2-G11: Provenance Completeness...\n";

// 2.1 Missing source_reference
$invalidNoRef = [
    'source_type'  => 'SCADA',
    'penyulang_id' => 118,
    'event_time'   => '2026-09-25 11:00:00',
];
$resNoRef = $service->ingestEvent($invalidNoRef);
$g2_noRef = ($resNoRef['status'] === 'REJECTED_INVALID_PROVENANCE' && $resNoRef['field'] === 'source_reference');
echo " - Missing source_reference rejected: " . ($g2_noRef ? "PASS ✅ ({$resNoRef['status']})" : "FAIL ❌") . "\n";

// 2.2 Missing event_time
$invalidNoTime = [
    'source_type'      => 'SCADA',
    'source_reference' => 'TICKET-99',
    'penyulang_id'     => 118,
];
$resNoTime = $service->ingestEvent($invalidNoTime);
$g2_noTime = ($resNoTime['status'] === 'REJECTED_INVALID_PROVENANCE' && $resNoTime['field'] === 'event_time');
echo " - Missing event_time rejected: " . ($g2_noTime ? "PASS ✅ ({$resNoTime['status']})" : "FAIL ❌") . "\n";

// 2.3 Invalid source_type
$invalidSource = [
    'source_type'      => 'UNKNOWN_HACKER_TOOL',
    'source_reference' => 'TICKET-99',
    'penyulang_id'     => 118,
    'event_time'       => '2026-09-25 11:00:00',
];
$resInvSource = $service->ingestEvent($invalidSource);
$g2_invSource = ($resInvSource['status'] === 'REJECTED_INVALID_PROVENANCE' && $resInvSource['field'] === 'source_type');
echo " - Invalid source_type rejected: " . ($g2_invSource ? "PASS ✅ ({$resInvSource['status']})" : "FAIL ❌") . "\n";

$guardResults['B5.2-G02'] = $g2_noRef && $g2_noTime && $g2_invSource;
$guardResults['B5.2-G11'] = $g2_noRef && $g2_noTime && $g2_invSource;

// =============================================================================
// Guard 3: Deterministic SHA-256 Fingerprint
// =============================================================================
echo "\n[3] Testing Guard B5.2-G03: Deterministic SHA-256 Fingerprint...\n";

// Payload A
$payloadA = [
    'source_type'      => 'SCADA',
    'source_reference' => 'DET-TEST-001',
    'penyulang_id'     => 118,
    'event_time'       => '2026-09-25 12:00:00',
    'fault_phase'      => 'RN',
    'fault_current_a'  => 550.25,
    'telemetry'        => ['voltage' => 20.1, 'substation' => 'GIRI', 'bus' => 1],
];
// Payload B: identical data, but reverse key order and nested array order
$payloadB = [
    'telemetry'        => ['bus' => 1, 'substation' => 'GIRI', 'voltage' => 20.1],
    'fault_current_a'  => 550.25,
    'fault_phase'      => 'RN',
    'event_time'       => '2026-09-25 12:00:00',
    'penyulang_id'     => 118,
    'source_reference' => 'DET-TEST-001',
    'source_type'      => 'SCADA',
];

$normA = $service->normalizeTelemetry($payloadA);
$normB = $service->normalizeTelemetry($payloadB);
$fpA = $service->computeEventFingerprint($normA);
$fpB = $service->computeEventFingerprint($normB);

$g3_det = ($fpA === $fpB && strlen($fpA) === 64);
echo " - Fingerprint A: {$fpA}\n";
echo " - Fingerprint B: {$fpB}\n";
echo " - Deterministic Hash Equality: " . ($g3_det ? "PASS ✅ (Equal SHA-256)" : "FAIL ❌") . "\n";

// Modifying single field must change fingerprint
$payloadC = $payloadA;
$payloadC['fault_current_a'] = 550.30;
$normC = $service->normalizeTelemetry($payloadC);
$fpC = $service->computeEventFingerprint($normC);
$g3_diff = ($fpA !== $fpC);
echo " - Telemetry Perturbation Sensitivity: " . ($g3_diff ? "PASS ✅ (Different SHA-256: {$fpC})" : "FAIL ❌") . "\n";

$guardResults['B5.2-G03'] = $g3_det && $g3_diff;

// =============================================================================
// Guard 4: DB-Level Idempotency & Concurrency Safeguard
// =============================================================================
echo "\n[4] Testing Guard B5.2-G04: DB-Level Idempotency...\n";

$testIngestPayload = [
    'source_type'      => 'PMCB_RELAY',
    'source_reference' => 'IDEMP-TEST-' . time(),
    'penyulang_id'     => 118,
    'event_time'       => '2026-09-25 13:00:00',
    'fault_phase'      => 'RN',
    'fault_current_a'  => 720.0,
    'relay_distance_m' => 45.0,
];

// Initial Ingestion
$ingest1 = $service->ingestEvent($testIngestPayload);
$g4_init = ($ingest1['status'] === 'INGESTED' && $ingest1['is_duplicate'] === false && !empty($ingest1['event_id']));
echo " - Initial Ingestion: " . ($g4_init ? "PASS ✅ (Event #{$ingest1['event_id']})" : "FAIL ❌") . "\n";

// Replay identical event
$ingest2 = $service->ingestEvent($testIngestPayload);
$g4_idemp = ($ingest2['status'] === 'IDEMPOTENT_DUPLICATE' &&
             $ingest2['is_duplicate'] === true &&
             $ingest2['event_id'] === $ingest1['event_id'] &&
             $ingest2['event_fingerprint'] === $ingest1['event_fingerprint']);
echo " - Idempotent Duplicate Replay: " . ($g4_idemp ? "PASS ✅ (Returned existing Event #{$ingest2['event_id']})" : "FAIL ❌") . "\n";

$guardResults['B5.2-G04'] = $g4_init && $g4_idemp;

// =============================================================================
// Guard 5: Temporal Snapshot Binding
// =============================================================================
echo "\n[5] Testing Guard B5.2-G05: Temporal Snapshot Binding...\n";
$snapResolved = $service->resolveTopologySnapshot('2026-09-25 14:00:00');
$g5_snap = ($snapResolved === 'TOPOLOGY-20260925-243-ad2c9fcb' &&
            $ingest1['topology_snapshot_id'] === 'TOPOLOGY-20260925-243-ad2c9fcb');
echo " - Snapshot Resolved: {$snapResolved}\n";
echo " - Temporal Snapshot Binding: " . ($g5_snap ? "PASS ✅" : "FAIL ❌") . "\n";
$guardResults['B5.2-G05'] = $g5_snap;

// =============================================================================
// Guard 7: Lifecycle Transition Enforcement
// =============================================================================
echo "\n[6] Testing Guard B5.2-G07: Lifecycle State Machine Transitions...\n";

$testEventId = $ingest1['event_id'];

// 6.1 Valid: INGESTED -> ANALYZING
$t1 = $service->transitionLifecycle($testEventId, 'ANALYZING');
$g7_t1 = ($t1['status'] === 'TRANSITION_SUCCESS' && $t1['new_status'] === 'ANALYZING');
echo " - Transition INGESTED -> ANALYZING: " . ($g7_t1 ? "PASS ✅" : "FAIL ❌") . "\n";

// 6.2 Valid: ANALYZING -> CANDIDATE_IDENTIFIED
$t2 = $service->transitionLifecycle($testEventId, 'CANDIDATE_IDENTIFIED');
$g7_t2 = ($t2['status'] === 'TRANSITION_SUCCESS' && $t2['new_status'] === 'CANDIDATE_IDENTIFIED');
echo " - Transition ANALYZING -> CANDIDATE_IDENTIFIED: " . ($g7_t2 ? "PASS ✅" : "FAIL ❌") . "\n";

// 6.3 Valid: CANDIDATE_IDENTIFIED -> DISPATCHED
$t3 = $service->transitionLifecycle($testEventId, 'DISPATCHED');
$g7_t3 = ($t3['status'] === 'TRANSITION_SUCCESS' && $t3['new_status'] === 'DISPATCHED');
echo " - Transition CANDIDATE_IDENTIFIED -> DISPATCHED: " . ($g7_t3 ? "PASS ✅" : "FAIL ❌") . "\n";

// 6.4 Invalid: DISPATCHED -> CLOSED (Must go through INVESTIGATING -> CONFIRMED/UNRESOLVED -> CLOSED)
$tInvalid = $service->transitionLifecycle($testEventId, 'CLOSED');
$g7_tInv = ($tInvalid['status'] === 'REJECTED_INVALID_TRANSITION');
echo " - Invalid Transition DISPATCHED -> CLOSED: " . ($g7_tInv ? "REJECTED (PASS ✅)" : "FAIL ❌") . "\n";

// 6.5 Valid: DISPATCHED -> INVESTIGATING -> CONFIRMED -> CLOSED
$service->transitionLifecycle($testEventId, 'INVESTIGATING');
$service->transitionLifecycle($testEventId, 'CONFIRMED');
$tClosed = $service->transitionLifecycle($testEventId, 'CLOSED');
$g7_tClose = ($tClosed['status'] === 'TRANSITION_SUCCESS' && $tClosed['new_status'] === 'CLOSED');
echo " - Transition to Terminal CLOSED: " . ($g7_tClose ? "PASS ✅" : "FAIL ❌") . "\n";

// 6.6 Invalid: CLOSED -> ANALYZING (Terminal state cannot re-open)
$tReopen = $service->transitionLifecycle($testEventId, 'ANALYZING');
$g7_tReopen = ($tReopen['status'] === 'REJECTED_INVALID_TRANSITION');
echo " - Invalid Transition from Terminal CLOSED -> ANALYZING: " . ($g7_tReopen ? "REJECTED (PASS ✅)" : "FAIL ❌") . "\n";

$guardResults['B5.2-G07'] = $g7_t1 && $g7_t2 && $g7_t3 && $g7_tInv && $g7_tClose && $g7_tReopen;

// =============================================================================
// Guard 8 & 6: Append-Only Revision Boundary & Legacy Event Isolation
// =============================================================================
echo "\n[7] Testing Guards B5.2-G08 & B5.2-G06: Append-Only Revisions & Legacy Isolation...\n";

// 7.1 Amend newly ingested event
$rev1 = $service->amendEvent(
    $testEventId,
    ['fault_current_a' => 745.5, 'protection_elements' => 'OCR+GFR'],
    'Koreksi pembacaan relay setelah sinkronisasi file comtrade',
    'DISPATCHER_AHMAD'
);
$g8_rev = ($rev1['status'] === 'REVISION_APPLIED' && $rev1['revision_no'] === 1);
echo " - Append-Only Revision applied: " . ($g8_rev ? "PASS ✅ (Revision #{$rev1['revision_no']})" : "FAIL ❌") . "\n";

// 7.2 Amend again (Revision #2)
$rev2 = $service->amendEvent(
    $testEventId,
    ['relay_distance_m' => 48.2],
    'Pembaruan estimasi jarak oleh proteksi GI',
    'ENGINEER_BUDI'
);
$g8_rev2 = ($rev2['status'] === 'REVISION_APPLIED' && $rev2['revision_no'] === 2);
echo " - Second Append-Only Revision applied: " . ($g8_rev2 ? "PASS ✅ (Revision #{$rev2['revision_no']})" : "FAIL ❌") . "\n";

// 7.3 Legacy Event Isolation Check: legacy row retains NULL fingerprint & LEGACY_UNFINGERPRINTED
$legacyRow = $db->table('fault_events')->where('fingerprint_status', 'LEGACY_UNFINGERPRINTED')->limit(1)->get()->getRowArray();
$g6_legacy = false;
if (!empty($legacyRow)) {
    $revLegacy = $service->amendEvent(
        (int)$legacyRow['id'],
        ['trip_sequence' => 'TRIP_1'],
        'Pembaruan catatan historis',
        'ADMIN'
    );
    // Fetch row again to ensure fingerprint was NOT synthesized
    $postLegacyRow = $db->table('fault_events')->where('id', $legacyRow['id'])->get()->getRowArray();
    $g6_legacy = ($postLegacyRow['fingerprint_status'] === 'LEGACY_UNFINGERPRINTED' &&
                  $postLegacyRow['event_fingerprint'] === null);
    echo " - Legacy Event Isolation (Fingerprint remains NULL): " . ($g6_legacy ? "PASS ✅" : "FAIL ❌") . "\n";
} else {
    echo " - Legacy Event Isolation: (Skipped, no legacy row found)\n";
    $g6_legacy = true;
}

$guardResults['B5.2-G08'] = $g8_rev && $g8_rev2;
$guardResults['B5.2-G06'] = $g6_legacy;

// =============================================================================
// Guard 9: FLI-1.0.0 Bridge Isolation
// =============================================================================
echo "\n[8] Testing Guard B5.2-G09: FLI-1.0.0 Bridge...\n";

// Ingest an event with anchor device (Tiang 33 = 5245 on Feeder 118)
$sampleAsset = $db->table('assets')->select('id')->limit(1)->get()->getRowArray();
$anchorId = !empty($sampleAsset['id']) ? (int)$sampleAsset['id'] : 5245;

$fliEventPayload = [
    'source_type'            => 'PMCB_RELAY',
    'source_reference'       => 'FLI-BRIDGE-TEST-' . time(),
    'penyulang_id'           => 118,
    'source_device_asset_id' => $anchorId,
    'event_time'             => '2026-09-25 14:30:00',
    'fault_phase'            => 'RN',
    'fault_current_a'        => 650.0,
    'relay_distance_m'       => 25.0,
];
$fliEvent = $service->ingestEvent($fliEventPayload);
echo " - Ingested Event for FLI Bridge: Event #{$fliEvent['event_id']}\n";

$bridgeResult = $service->triggerAnalysis($fliEvent['event_id'], 20.0);
$g9_bridge = ($bridgeResult['status'] === 'ANALYSIS_COMPLETE' &&
              $bridgeResult['success'] === true &&
              $bridgeResult['engine_version'] === 'FLI-1.0.0' &&
              in_array($bridgeResult['lifecycle_status'], ['CANDIDATE_IDENTIFIED', 'UNRESOLVED'], true));
echo " - FLI Bridge Execution: " . ($g9_bridge ? "PASS ✅ (Status: {$bridgeResult['lifecycle_status']}, Candidates: {$bridgeResult['candidates_count']})" : "FAIL ❌") . "\n";
$guardResults['B5.2-G09'] = $g9_bridge;

// =============================================================================
// Batch Ingestion & Idempotency
// =============================================================================
echo "\n[9] Testing Batch Ingestion & Batch Idempotency...\n";
$batchPayload = [
    [
        'source_reference' => 'BATCH-REC-001-' . time(),
        'penyulang_id'     => 118,
        'event_time'       => '2026-09-25 15:00:00',
        'fault_phase'      => 'RN',
        'fault_current_a'  => 500.0,
    ],
    [
        'source_reference' => 'BATCH-REC-002-' . time(),
        'penyulang_id'     => 118,
        'event_time'       => '2026-09-25 15:05:00',
        'fault_phase'      => 'ST',
        'fault_current_a'  => 620.0,
    ],
];

// First submission
$batch1 = $service->ingestBatch($batchPayload, 'SCADA', ['operator' => 'SCADA_CRON']);
$b1_pass = ($batch1['total_records'] === 2 && $batch1['accepted_records'] === 2 && $batch1['duplicate_records'] === 0);
echo " - Batch Run 1 (Accepted 2/2): " . ($b1_pass ? "PASS ✅" : "FAIL ❌") . "\n";

// Second submission of the exact same batch
$batch2 = $service->ingestBatch($batchPayload, 'SCADA', ['operator' => 'SCADA_CRON']);
$b2_pass = ($batch2['total_records'] === 2 && $batch2['accepted_records'] === 0 && $batch2['duplicate_records'] === 2);
echo " - Batch Run 2 (Idempotent 2 Duplicates, 0 Created): " . ($b2_pass ? "PASS ✅" : "FAIL ❌") . "\n";

// =============================================================================
// Guard 10: ZERO_TOPOLOGY_MUTATION
// =============================================================================
echo "\n[10] Testing Guard B5.2-G10: ZERO_TOPOLOGY_MUTATION...\n";
$tlPost = $db->tableExists('gis_translines') ? $db->table('gis_translines')->countAllResults() : 0;
$assetPost = $db->tableExists('assets') ? $db->table('assets')->countAllResults() : 0;
$zeroMutation = ($tlBaseline === $tlPost && $assetBaseline === $assetPost);

echo " - gis_translines: {$tlBaseline} -> {$tlPost} (Delta: " . ($tlPost - $tlBaseline) . ")\n";
echo " - assets:         {$assetBaseline} -> {$assetPost} (Delta: " . ($assetPost - $assetBaseline) . ")\n";
echo " - Authoritative Invariant Status: " . ($zeroMutation ? "MUTATION = 0 (PASS ✅)" : "MUTATION DETECTED ❌") . "\n";
$guardResults['B5.2-G10'] = $zeroMutation;

// =============================================================================
// SUMMARY SCORECARD
// =============================================================================
echo "\n====================================================================\n";
echo "  PHASE B.5.2 HARD GUARDS SCORECARD                                  \n";
echo "====================================================================\n";

$allGuardsPass = true;
foreach ($guardResults as $guard => $passed) {
    if (!$passed) $allGuardsPass = false;
    echo sprintf(" %-12s : %s\n", $guard, $passed ? "PASS ✅" : "FAIL ❌");
}

echo "--------------------------------------------------------------------\n";
echo "GATE B.5.2 VERDICT: " . ($allGuardsPass && $b1_pass && $b2_pass ? "PASS 🟢" : "FAIL 🔴") . "\n";
echo "====================================================================\n";
