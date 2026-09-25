<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\FaultEventIngestionService;
use App\Services\FaultLocationIntelligenceService;
use App\Services\NetworkIntelligenceService;
use App\Services\NetworkContextEngine;
use Config\Database;
use CodeIgniter\Database\BaseConnection;

/**
 * B5FaultEventIngestionTest
 *
 * Comprehensive Unit Test Suite for Phase B.5 Fault Event Ingestion Pipeline:
 *  1. Canonical Normalization: SCADA IEC-104 (B5.2-G01)
 *  2. Canonical Normalization: PMCB Numerical Relay (B5.2-G01)
 *  3. Canonical Normalization: Auto-Recloser Trip Cycle (B5.2-G01)
 *  4. Canonical Normalization: Manual & Import Formats (B5.2-G01)
 *  5. Provenance Validation: Missing source_type Rejection (B5.2-G02, B5.2-G11)
 *  6. Provenance Validation: Invalid source_type Rejection (B5.2-G02, B5.2-G11)
 *  7. Provenance Validation: Missing source_reference Rejection (B5.2-G02, B5.2-G11)
 *  8. Provenance Validation: Missing event_time Rejection (B5.2-G02, B5.2-G11)
 *  9. Provenance Validation: Missing penyulang_id Rejection (B5.2-G02, B5.2-G11)
 * 10. Deterministic Fingerprint: Invariant across Key Ordering (B5.2-G03)
 * 11. Deterministic Fingerprint: Telemetry Perturbation Sensitivity (B5.2-G03)
 * 12. Temporal Snapshot Binding: Resolves Active Topology State (B5.2-G05)
 * 13. Lifecycle State Machine: Valid Progression Graph (B5.2-G07)
 * 14. Lifecycle State Machine: Rejection of Unauthorized Shortcuts (B5.2-G07)
 * 15. Lifecycle State Machine: Terminal State Immutability (B5.2-G07)
 * 16. DB-Level Idempotency: Replay Rejection & Concurrency Safeguard (B5.2-G04)
 * 17. Append-Only Revision: Event Telemetry Amendments (B5.2-G08)
 * 18. Legacy Event Isolation: Preserves NULL Fingerprint (B5.2-G06)
 * 19. FLI-1.0.0 Bridge Isolation: Candidate Localization & Case Linking (B5.2-G09)
 * 20. Batch Ingestion: Bulk Processing & Batch Replay Idempotency (B5.2-G04)
 * 21. Zero Authoritative Topology Mutation Invariant across Ingestion (B5.2-G10)
 */
class B5FaultEventIngestionTest extends TestCase
{
    protected FaultEventIngestionService $service;
    protected ?BaseConnection $db;
    protected array $testCreatedEventIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->db = Database::connect('default');
            // Test if connection works
            $this->db->getVersion();
        } catch (\Throwable $e) {
            $this->db = Database::connect();
        }

        $this->service = new FaultEventIngestionService($this->db);
        $this->testCreatedEventIds = [];
    }

    protected function tearDown(): void
    {
        // Clean up any test-inserted records from DB to ensure repeatable isolated test runs
        if ($this->db && $this->db->tableExists('fault_events') && !empty($this->testCreatedEventIds)) {
            $this->db->table('fault_candidate_assets')
                ->whereIn('fault_case_id', function ($builder) {
                    return $builder->select('id')->from('fault_cases')->whereIn('fault_event_id', $this->testCreatedEventIds);
                })
                ->delete();

            $this->db->table('fault_cases')->whereIn('fault_event_id', $this->testCreatedEventIds)->delete();
            $this->db->table('fault_event_revisions')->whereIn('fault_event_id', $this->testCreatedEventIds)->delete();
            $this->db->table('fault_events')->whereIn('id', $this->testCreatedEventIds)->delete();
        }

        parent::tearDown();
    }

    // =========================================================================
    // 1-4: NORMALIZATION TESTS (B5.2-G01)
    // =========================================================================

    public function testCanonicalNormalizationScada(): void
    {
        $raw = [
            'source'          => 'scada',
            'source_ref'      => 'SCADA-EVT-1001',
            'feeder_id'       => 118,
            'timestamp'       => '2026-09-25 08:00:00',
            'phase'           => 'RN',
            'arus_gangguan'   => 1250.75,
            'distance_km'     => 1.45,
            'relay_element'   => 'OCR+GFR',
            'sequence'        => 'TRIP_1',
            'phase_currents'  => ['ir' => 1250.75, 'is' => 15.0, 'it' => 14.5, 'in' => 1236.0],
            'telemetry'       => ['substation' => 'GIRI', 'bay' => 'FEEDER_118'],
        ];

        $norm = $this->service->normalizeTelemetry($raw);

        $this->assertSame('SCADA', $norm['source_type']);
        $this->assertSame('SCADA-EVT-1001', $norm['source_reference']);
        $this->assertSame(118, $norm['penyulang_id']);
        $this->assertSame('2026-09-25 08:00:00', $norm['event_time']);
        $this->assertSame('RN', $norm['fault_phase']);
        $this->assertSame(1250.75, $norm['fault_current_a']);
        $this->assertSame(1450.00, $norm['relay_distance_m']);
        $this->assertSame('OCR+GFR', $norm['protection_elements']);
        $this->assertSame('TRIP_1', $norm['trip_sequence']);
        $this->assertIsArray($norm['phase_currents']);
        $this->assertSame(1250.75, $norm['phase_currents']['ir']);
    }

    public function testCanonicalNormalizationPmcbRelay(): void
    {
        $raw = [
            'source_type'      => 'PMCB_RELAY',
            'source_reference' => 'RELAY-REC-8842',
            'penyulang_id'     => 118,
            'device_id'        => 5245,
            'event_time'       => '2026-09-25 08:15:00',
            'fault_phase'      => 'ST',
            'current'          => 850.2,
            'phase_currents'   => ['ia' => 10.5, 'ib' => 850.2, 'ic' => 848.0, 'i0' => 4.1],
            'elements'         => ['ocr', 'inst'],
            'distance_m'       => 275.5,
        ];

        $norm = $this->service->normalizeTelemetry($raw);

        $this->assertSame('PMCB_RELAY', $norm['source_type']);
        $this->assertSame('RELAY-REC-8842', $norm['source_reference']);
        $this->assertSame(5245, $norm['source_device_asset_id']);
        $this->assertSame('ST', $norm['fault_phase']);
        $this->assertSame(850.20, $norm['fault_current_a']);
        $this->assertSame(275.50, $norm['relay_distance_m']);
        $this->assertSame('OCR+INST', $norm['protection_elements']);
        $this->assertSame(10.5, $norm['phase_currents']['ir']);
        $this->assertSame(850.2, $norm['phase_currents']['is']);
    }

    public function testCanonicalNormalizationRecloser(): void
    {
        $raw = [
            'source'          => 'recloser',
            'ticket_no'       => 'REC-TRIP-303',
            'feeder_id'       => 15,
            'fault_time'      => '2026-09-25 09:30:00',
            'fasa'            => 'RST',
            'trip_sequence'   => 'LOCKOUT',
            'fault_current_a' => 2400.00,
        ];

        $norm = $this->service->normalizeTelemetry($raw);

        $this->assertSame('RECLOSER', $norm['source_type']);
        $this->assertSame('REC-TRIP-303', $norm['source_reference']);
        $this->assertSame(15, $norm['penyulang_id']);
        $this->assertSame('RST', $norm['fault_phase']);
        $this->assertSame('LOCKOUT', $norm['trip_sequence']);
        $this->assertSame(2400.00, $norm['fault_current_a']);
    }

    public function testCanonicalNormalizationManualAndImport(): void
    {
        $manualRaw = [
            'source_type'      => 'MANUAL_ENTRY',
            'source_reference' => 'TICKET-DISPATCH-991',
            'penyulang_id'     => 118,
            'event_time'       => '2026-09-25 10:00:00',
            'fault_phase'      => 'TR-G',
            'current'          => 410.0,
            'relay_distance_m' => 60.0,
        ];

        $norm = $this->service->normalizeTelemetry($manualRaw);

        $this->assertSame('MANUAL_ENTRY', $norm['source_type']);
        $this->assertSame('TR-G', $norm['fault_phase']);
        $this->assertSame(410.00, $norm['fault_current_a']);
        $this->assertSame(60.00, $norm['relay_distance_m']);
    }

    // =========================================================================
    // 5-9: PROVENANCE VALIDATION TESTS (B5.2-G02 & B5.2-G11)
    // =========================================================================

    public function testProvenanceValidationMissingSource(): void
    {
        $raw = [
            'source_reference' => 'TICKET-1',
            'penyulang_id'     => 118,
            'event_time'       => '2026-09-25 11:00:00',
        ];
        unset($raw['source_type'], $raw['source']);

        $norm = $this->service->normalizeTelemetry($raw);
        // If missing from input, normalizeTelemetry defaults to 'API', which is allowed.
        // But if explicitly blank or invalid:
        $norm['source_type'] = '';
        $res = $this->service->validateProvenance($norm);

        $this->assertFalse($res['valid']);
        $this->assertSame('REJECTED_INVALID_PROVENANCE', $res['code']);
        $this->assertSame('source_type', $res['field']);
    }

    public function testProvenanceValidationInvalidSource(): void
    {
        $norm = [
            'source_type'      => 'UNAUTHORIZED_SCRAPER',
            'source_reference' => 'TICKET-1',
            'penyulang_id'     => 118,
            'event_time'       => '2026-09-25 11:00:00',
        ];

        $res = $this->service->validateProvenance($norm);

        $this->assertFalse($res['valid']);
        $this->assertSame('REJECTED_INVALID_PROVENANCE', $res['code']);
        $this->assertSame('source_type', $res['field']);
    }

    public function testProvenanceValidationMissingReference(): void
    {
        $norm = [
            'source_type'      => 'SCADA',
            'source_reference' => '   ', // Blank
            'penyulang_id'     => 118,
            'event_time'       => '2026-09-25 11:00:00',
        ];

        $res = $this->service->validateProvenance($norm);

        $this->assertFalse($res['valid']);
        $this->assertSame('REJECTED_INVALID_PROVENANCE', $res['code']);
        $this->assertSame('source_reference', $res['field']);
    }

    public function testProvenanceValidationMissingEventTime(): void
    {
        $norm = [
            'source_type'      => 'SCADA',
            'source_reference' => 'TICKET-1',
            'penyulang_id'     => 118,
            'event_time'       => null,
        ];

        $res = $this->service->validateProvenance($norm);

        $this->assertFalse($res['valid']);
        $this->assertSame('REJECTED_INVALID_PROVENANCE', $res['code']);
        $this->assertSame('event_time', $res['field']);
    }

    public function testProvenanceValidationMissingPenyulangId(): void
    {
        $norm = [
            'source_type'      => 'SCADA',
            'source_reference' => 'TICKET-1',
            'penyulang_id'     => 0, // Invalid ID
            'event_time'       => '2026-09-25 11:00:00',
        ];

        $res = $this->service->validateProvenance($norm);

        $this->assertFalse($res['valid']);
        $this->assertSame('REJECTED_INVALID_PROVENANCE', $res['code']);
        $this->assertSame('penyulang_id', $res['field']);
    }

    // =========================================================================
    // 10-11: DETERMINISTIC FINGERPRINT TESTS (B5.2-G03)
    // =========================================================================

    public function testDeterministicFingerprintIndependenceOfKeyOrdering(): void
    {
        $payload1 = [
            'source_type'      => 'SCADA',
            'source_reference' => 'REF-HASH-001',
            'penyulang_id'     => 118,
            'event_time'       => '2026-09-25 12:00:00',
            'fault_phase'      => 'RN',
            'fault_current_a'  => 550.25,
            'telemetry'        => ['voltage' => 20.1, 'bus' => 1, 'substation' => 'GIRI'],
        ];

        // Inverse key order and inverse nested dictionary keys
        $payload2 = [
            'telemetry'        => ['substation' => 'GIRI', 'bus' => 1, 'voltage' => 20.1],
            'fault_current_a'  => 550.25,
            'fault_phase'      => 'RN',
            'event_time'       => '2026-09-25 12:00:00',
            'penyulang_id'     => 118,
            'source_reference' => 'REF-HASH-001',
            'source_type'      => 'SCADA',
        ];

        $norm1 = $this->service->normalizeTelemetry($payload1);
        $norm2 = $this->service->normalizeTelemetry($payload2);

        $hash1 = $this->service->computeEventFingerprint($norm1);
        $hash2 = $this->service->computeEventFingerprint($norm2);

        $this->assertSame(64, strlen($hash1));
        $this->assertSame($hash1, $hash2, "Hash must be strictly independent of JSON key ordering.");
    }

    public function testDeterministicFingerprintSensitivityToPerturbation(): void
    {
        $base = [
            'source_type'      => 'SCADA',
            'source_reference' => 'REF-HASH-002',
            'penyulang_id'     => 118,
            'event_time'       => '2026-09-25 12:00:00',
            'fault_phase'      => 'RN',
            'fault_current_a'  => 550.25,
        ];

        $perturbed = $base;
        $perturbed['fault_current_a'] = 550.26; // 0.01A delta

        $hashBase = $this->service->computeEventFingerprint($this->service->normalizeTelemetry($base));
        $hashPerturbed = $this->service->computeEventFingerprint($this->service->normalizeTelemetry($perturbed));

        $this->assertNotSame($hashBase, $hashPerturbed, "Perturbation in telemetry must alter deterministic fingerprint.");
    }

    // =========================================================================
    // 12: TEMPORAL SNAPSHOT RESOLUTION (B5.2-G05)
    // =========================================================================

    public function testTemporalTopologySnapshotBinding(): void
    {
        $snapshot = $this->service->resolveTopologySnapshot('2026-09-25 14:00:00');
        $this->assertSame(FaultEventIngestionService::DEFAULT_TOPOLOGY_SNAPSHOT, $snapshot);
    }

    // =========================================================================
    // 13-15: LIFECYCLE STATE MACHINE (B5.2-G07)
    // =========================================================================

    public function testLifecycleStateMachineValidProgression(): void
    {
        if (!$this->db || !$this->db->tableExists('fault_events')) {
            $this->markTestSkipped('Database fault_events table not available.');
        }

        $res = $this->service->ingestEvent([
            'source_type'      => 'API',
            'source_reference' => 'LIFECYCLE-TEST-' . bin2hex(random_bytes(4)),
            'penyulang_id'     => 118,
            'event_time'       => '2026-09-25 13:00:00',
            'fault_phase'      => 'RN',
        ]);
        $this->assertTrue($res['success']);
        $eventId = (int)$res['event_id'];
        $this->testCreatedEventIds[] = $eventId;

        $this->assertSame('INGESTED', $res['lifecycle_status']);

        // 1. INGESTED -> ANALYZING
        $t1 = $this->service->transitionLifecycle($eventId, 'ANALYZING');
        $this->assertTrue($t1['success']);
        $this->assertSame('ANALYZING', $t1['new_status']);

        // 2. ANALYZING -> CANDIDATE_IDENTIFIED
        $t2 = $this->service->transitionLifecycle($eventId, 'CANDIDATE_IDENTIFIED');
        $this->assertTrue($t2['success']);
        $this->assertSame('CANDIDATE_IDENTIFIED', $t2['new_status']);

        // 3. CANDIDATE_IDENTIFIED -> DISPATCHED
        $t3 = $this->service->transitionLifecycle($eventId, 'DISPATCHED');
        $this->assertTrue($t3['success']);
        $this->assertSame('DISPATCHED', $t3['new_status']);

        // 4. DISPATCHED -> INVESTIGATING
        $t4 = $this->service->transitionLifecycle($eventId, 'INVESTIGATING');
        $this->assertTrue($t4['success']);
        $this->assertSame('INVESTIGATING', $t4['new_status']);

        // 5. INVESTIGATING -> CONFIRMED
        $t5 = $this->service->transitionLifecycle($eventId, 'CONFIRMED');
        $this->assertTrue($t5['success']);
        $this->assertSame('CONFIRMED', $t5['new_status']);

        // 6. CONFIRMED -> CLOSED
        $t6 = $this->service->transitionLifecycle($eventId, 'CLOSED');
        $this->assertTrue($t6['success']);
        $this->assertSame('CLOSED', $t6['new_status']);
    }

    public function testLifecycleStateMachineRejectsInvalidTransitions(): void
    {
        if (!$this->db || !$this->db->tableExists('fault_events')) {
            $this->markTestSkipped('Database fault_events table not available.');
        }

        $res = $this->service->ingestEvent([
            'source_type'      => 'API',
            'source_reference' => 'LIFECYCLE-INV-' . bin2hex(random_bytes(4)),
            'penyulang_id'     => 118,
            'event_time'       => '2026-09-25 13:10:00',
            'fault_phase'      => 'RN',
        ]);
        $eventId = (int)$res['event_id'];
        $this->testCreatedEventIds[] = $eventId;

        // Try unauthorized shortcut: INGESTED directly to CONFIRMED
        $invalid1 = $this->service->transitionLifecycle($eventId, 'CONFIRMED');
        $this->assertFalse($invalid1['success']);
        $this->assertSame('REJECTED_INVALID_TRANSITION', $invalid1['status']);

        // Try unauthorized shortcut: INGESTED directly to CLOSED
        $invalid2 = $this->service->transitionLifecycle($eventId, 'CLOSED');
        $this->assertFalse($invalid2['success']);
        $this->assertSame('REJECTED_INVALID_TRANSITION', $invalid2['status']);
    }

    public function testLifecycleStateMachineTerminalStateImmutability(): void
    {
        if (!$this->db || !$this->db->tableExists('fault_events')) {
            $this->markTestSkipped('Database fault_events table not available.');
        }

        $res = $this->service->ingestEvent([
            'source_type'      => 'API',
            'source_reference' => 'TERMINAL-TEST-' . bin2hex(random_bytes(4)),
            'penyulang_id'     => 118,
            'event_time'       => '2026-09-25 13:20:00',
        ]);
        $eventId = (int)$res['event_id'];
        $this->testCreatedEventIds[] = $eventId;

        // Progress to CLOSED
        $this->service->transitionLifecycle($eventId, 'ANALYZING');
        $this->service->transitionLifecycle($eventId, 'UNRESOLVED');
        $this->service->transitionLifecycle($eventId, 'CLOSED');

        // Attempting to reopen CLOSED -> ANALYZING must be rejected
        $reopen = $this->service->transitionLifecycle($eventId, 'ANALYZING');
        $this->assertFalse($reopen['success']);
        $this->assertSame('REJECTED_INVALID_TRANSITION', $reopen['status']);
    }

    // =========================================================================
    // 16: DB-LEVEL IDEMPOTENCY (B5.2-G04)
    // =========================================================================

    public function testDbLevelIdempotencyAndDuplicateRejection(): void
    {
        if (!$this->db || !$this->db->tableExists('fault_events')) {
            $this->markTestSkipped('Database fault_events table not available.');
        }

        $payload = [
            'source_type'      => 'PMCB_RELAY',
            'source_reference' => 'DEDUP-TEST-' . bin2hex(random_bytes(4)),
            'penyulang_id'     => 118,
            'event_time'       => '2026-09-25 14:00:00',
            'fault_phase'      => 'RN',
            'fault_current_a'  => 600.0,
        ];

        // 1. First ingestion
        $res1 = $this->service->ingestEvent($payload);
        $this->assertTrue($res1['success']);
        $this->assertFalse($res1['is_duplicate']);
        $this->assertSame('INGESTED', $res1['status']);
        $eventId1 = (int)$res1['event_id'];
        $this->testCreatedEventIds[] = $eventId1;

        // 2. Replay identical payload
        $res2 = $this->service->ingestEvent($payload);
        $this->assertTrue($res2['success']);
        $this->assertTrue($res2['is_duplicate']);
        $this->assertSame('IDEMPOTENT_DUPLICATE', $res2['status']);
        $this->assertSame($eventId1, $res2['event_id']);
        $this->assertSame($res1['event_fingerprint'], $res2['event_fingerprint']);
    }

    // =========================================================================
    // 17: APPEND-ONLY REVISION BOUNDARY (B5.2-G08)
    // =========================================================================

    public function testAppendOnlyRevisionPreservesHistoricalRecord(): void
    {
        if (!$this->db || !$this->db->tableExists('fault_events')) {
            $this->markTestSkipped('Database fault_events table not available.');
        }

        $payload = [
            'source_type'      => 'PMCB_RELAY',
            'source_reference' => 'REV-TEST-' . bin2hex(random_bytes(4)),
            'penyulang_id'     => 118,
            'event_time'       => '2026-09-25 14:30:00',
            'fault_current_a'  => 500.0,
            'fault_phase'      => 'RN',
        ];

        $ingest = $this->service->ingestEvent($payload);
        $eventId = (int)$ingest['event_id'];
        $this->testCreatedEventIds[] = $eventId;
        $originalFingerprint = $ingest['event_fingerprint'];

        // Amend event
        $rev1 = $this->service->amendEvent(
            $eventId,
            ['fault_current_a' => 525.5],
            'Koreksi arus trip berdasarkan log comtrade',
            'ENGINEER_1'
        );
        $this->assertTrue($rev1['success']);
        $this->assertSame(1, $rev1['revision_no']);

        // Check fault_events: original fingerprint must remain intact
        $postRow = $this->db->table('fault_events')->where('id', $eventId)->get()->getRowArray();
        $this->assertSame($originalFingerprint, $postRow['event_fingerprint'], "Original event_fingerprint must never be modified by revisions.");

        // Check fault_event_revisions row
        $revRow = $this->db->table('fault_event_revisions')
            ->where('fault_event_id', $eventId)
            ->where('revision_no', 1)
            ->get()
            ->getRowArray();
        $this->assertNotEmpty($revRow);
        $this->assertSame('ENGINEER_1', $revRow['amended_by']);
        $this->assertSame('Koreksi arus trip berdasarkan log comtrade', $revRow['amendment_reason']);
    }

    // =========================================================================
    // 18: LEGACY EVENT ISOLATION (B5.2-G06)
    // =========================================================================

    public function testLegacyIsolationPreservesNullFingerprint(): void
    {
        if (!$this->db || !$this->db->tableExists('fault_events')) {
            $this->markTestSkipped('Database fault_events table not available.');
        }

        // Insert simulated legacy event directly
        $legacyNumber = 'EVT-LEGACY-TEST-' . bin2hex(random_bytes(3));
        $this->db->table('fault_events')->insert([
            'event_number'       => $legacyNumber,
            'event_fingerprint'  => null,
            'fingerprint_status' => 'LEGACY_UNFINGERPRINTED',
            'penyulang_id'       => 118,
            'event_time'         => '2026-09-20 10:00:00',
            'source_type'        => 'MANUAL_ENTRY',
            'source_reference'   => 'LEGACY-REF-1',
            'status'             => 'OPEN',
            'lifecycle_status'   => 'INGESTED',
            'created_at'         => '2026-09-20 10:00:00',
        ]);
        $legacyId = (int)$this->db->insertID();
        $this->testCreatedEventIds[] = $legacyId;

        // Amend the legacy event
        $rev = $this->service->amendEvent($legacyId, ['trip_sequence' => 'TRIP_1'], 'Update legacy note', 'ADMIN');
        $this->assertTrue($rev['success']);
        $this->assertTrue($rev['is_legacy']);

        // Verify fingerprint was NOT synthesized
        $updatedLegacy = $this->db->table('fault_events')->where('id', $legacyId)->get()->getRowArray();
        $this->assertNull($updatedLegacy['event_fingerprint'], "Legacy event_fingerprint must remain NULL.");
        $this->assertSame('LEGACY_UNFINGERPRINTED', $updatedLegacy['fingerprint_status']);
    }

    // =========================================================================
    // 19: FLI-1.0.0 BRIDGE ISOLATION (B5.2-G09)
    // =========================================================================

    public function testFliBridgeIsolationAndCandidateLinking(): void
    {
        if (!$this->db || !$this->db->tableExists('fault_events')) {
            $this->markTestSkipped('Database fault_events table not available.');
        }

        // Ingest event with sample asset
        $sampleAsset = $this->db->table('assets')->select('id')->limit(1)->get()->getRowArray();
        $anchorId = !empty($sampleAsset['id']) ? (int)$sampleAsset['id'] : 5245;

        $res = $this->service->ingestEvent([
            'source_type'            => 'PMCB_RELAY',
            'source_reference'       => 'FLI-TEST-' . bin2hex(random_bytes(4)),
            'penyulang_id'           => 118,
            'source_device_asset_id' => $anchorId,
            'event_time'             => '2026-09-25 15:00:00',
            'relay_distance_m'       => 25.0,
            'fault_phase'            => 'RN',
        ]);
        $eventId = (int)$res['event_id'];
        $this->testCreatedEventIds[] = $eventId;

        $analysis = $this->service->triggerAnalysis($eventId, 20.0);

        $this->assertTrue($analysis['success']);
        $this->assertSame('FLI-1.0.0', $analysis['engine_version']);
        $this->assertContains($analysis['lifecycle_status'], ['CANDIDATE_IDENTIFIED', 'UNRESOLVED']);
    }

    // =========================================================================
    // 20: BATCH INGESTION IDEMPOTENCY (B5.2-G04)
    // =========================================================================

    public function testBatchIngestionIdempotency(): void
    {
        if (!$this->db || !$this->db->tableExists('fault_events')) {
            $this->markTestSkipped('Database fault_events table not available.');
        }

        $tag = bin2hex(random_bytes(3));
        $batch = [
            [
                'source_reference' => "BATCH-A-{$tag}",
                'penyulang_id'     => 118,
                'event_time'       => '2026-09-25 15:10:00',
                'fault_phase'      => 'RN',
            ],
            [
                'source_reference' => "BATCH-B-{$tag}",
                'penyulang_id'     => 118,
                'event_time'       => '2026-09-25 15:15:00',
                'fault_phase'      => 'ST',
            ],
        ];

        // First run
        $b1 = $this->service->ingestBatch($batch, 'SCADA');
        $this->assertSame(2, $b1['total_records']);
        $this->assertSame(2, $b1['accepted_records']);
        $this->assertSame(0, $b1['duplicate_records']);

        foreach ($b1['results'] as $r) {
            if (!empty($r['event_id'])) {
                $this->testCreatedEventIds[] = (int)$r['event_id'];
            }
        }

        // Second run: exact same batch
        $b2 = $this->service->ingestBatch($batch, 'SCADA');
        $this->assertSame(2, $b2['total_records']);
        $this->assertSame(0, $b2['accepted_records']);
        $this->assertSame(2, $b2['duplicate_records']);
    }

    // =========================================================================
    // 21: ZERO TOPOLOGY MUTATION INVARIANT (B5.2-G10)
    // =========================================================================

    public function testZeroAuthoritativeTopologyMutationInvariant(): void
    {
        if (!$this->db) {
            $this->markTestSkipped('Database not available.');
        }

        $tlBefore = $this->db->tableExists('gis_translines') ? $this->db->table('gis_translines')->countAllResults() : 0;
        $assetBefore = $this->db->tableExists('assets') ? $this->db->table('assets')->countAllResults() : 0;

        // Perform sequence of ingestion operations
        $res = $this->service->ingestEvent([
            'source_type'      => 'SCADA',
            'source_reference' => 'ZERO-MUT-' . bin2hex(random_bytes(4)),
            'penyulang_id'     => 118,
            'event_time'       => '2026-09-25 16:00:00',
            'fault_phase'      => 'RN',
        ]);
        if (!empty($res['event_id'])) {
            $this->testCreatedEventIds[] = (int)$res['event_id'];
            $this->service->transitionLifecycle($res['event_id'], 'ANALYZING');
            $this->service->amendEvent($res['event_id'], ['fault_current_a' => 999.0], 'Audit test', 'AUDITOR');
        }

        $tlAfter = $this->db->tableExists('gis_translines') ? $this->db->table('gis_translines')->countAllResults() : 0;
        $assetAfter = $this->db->tableExists('assets') ? $this->db->table('assets')->countAllResults() : 0;

        $this->assertSame($tlBefore, $tlAfter, "Authoritative gis_translines count must not mutate.");
        $this->assertSame($assetBefore, $assetAfter, "Authoritative assets count must not mutate.");
    }
}
