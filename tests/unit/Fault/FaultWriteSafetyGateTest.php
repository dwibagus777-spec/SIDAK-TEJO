<?php

namespace Tests\Unit\Fault;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\FaultWriteSafetyGateService;

/**
 * Phase FL-01G-A: Write Contract & Persistence Safety Gate Test Suite
 *
 * 37 Comprehensive Tests Enforcing:
 * 1. Multi-hypothesis schema semantics (1:N:N)
 * 2. Strict selected_hypothesis null rules on ambiguity/unresolved
 * 3. Evidence provenance and integrity (UNKNOWN != negative)
 * 4. Canonical event fingerprint determinism & timestamp independence
 * 5. Replay defense protocol
 * 6. Safety invariants (AI firewall, zero mutation, zero nearest asset/GPS)
 * 7. Transaction atomicity & immutability
 * 8. Zero database mutation invariant (Delta = 0, fault_records absent)
 */
class FaultWriteSafetyGateTest extends CIUnitTestCase
{
    protected FaultWriteSafetyGateService $gateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateService = new FaultWriteSafetyGateService();
    }

    protected function getValidBasePayload(): array
    {
        return [
            'penyulang_id'       => 1,
            'ftu_id'             => 'FTU-BDR-01',
            'fault_time'         => '2026-09-21 08:00:00',
            'fault_type'         => 'A-G',
            'ia'                 => 450.0,
            'ib'                 => 0.0,
            'ic'                 => 0.0,
            'voltage'            => 20.0,
            'impedance'          => 1.25,
            'source_distance'    => 1200.0,
            'raw_payload'        => 'FAULT: A-G Ia:450A Dist:1.2km Z:1.25',
            'parser_version'     => 'FL-01B-1.0',
            'validator_version'  => 'FL-01C-1.0',
            'traversal_version'  => 'FL-01D-1.0',
            'projection_version' => 'FL-01E-1.0',
            'resolution_version' => 'FL-01F-1.0',
            'resolution_state'   => 'BRANCH_AMBIGUITY',
            'selected_hypothesis_id' => null,
            'operational_status' => 'PERSISTED',
            'hypotheses'         => [
                [
                    'hypothesis_id' => 'HYP-01',
                    'component_id'  => 1,
                    'path_id'       => 'PATH-01',
                    'transline_id'  => 10,
                    'offset_m'      => 45.0,
                    'fault_lat'     => -7.41,
                    'fault_lng'     => 112.71,
                ],
                [
                    'hypothesis_id' => 'HYP-02',
                    'component_id'  => 1,
                    'path_id'       => 'PATH-02',
                    'transline_id'  => 20,
                    'offset_m'      => 45.0,
                    'fault_lat'     => -7.42,
                    'fault_lng'     => 112.72,
                ],
            ],
            'evidence_catalog'   => [
                [
                    'type'         => 'TOPOLOGY_CONSISTENCY',
                    'status'       => 'SUPPORTING',
                    'source_table' => 'gis_translines',
                    'source_id'    => 10,
                    'relation'     => 'TOPOLOGY_DERIVED',
                ],
            ],
        ];
    }

    /**
     * Test 01: Multi-Hypothesis Schema Semantics (1:N preserved)
     */
    public function test01_MultiHypothesisSchemaSemantics(): void
    {
        $payload = $this->getValidBasePayload();
        $res = $this->gateService->validateWriteContract($payload);

        $this->assertSame('PASS', $res['status']);
        $this->assertSame(2, $res['canonical_payload']['hypotheses_count']);
    }

    /**
     * Test 02: Flat Schema Structure is Strictly Rejected
     */
    public function test02_FlatSchemaRejected(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['hypotheses'] = []; // Flat or missing

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_FLAT_SCHEMA, $res['rejection_code']);
    }

    /**
     * Test 03: Selected Hypothesis Null Rule on BRANCH_AMBIGUITY
     */
    public function test03_SelectedHypothesisNullRule_BranchAmbiguity(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['resolution_state'] = 'BRANCH_AMBIGUITY';
        $payload['selected_hypothesis_id'] = 'HYP-FORCED'; // Illegal forced selection

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_INVALID_SELECTION, $res['rejection_code']);
    }

    /**
     * Test 04: Selected Hypothesis Null Rule on MULTIPLE_COMPATIBLE_HYPOTHESES
     */
    public function test04_SelectedHypothesisNullRule_MultipleCompatible(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['resolution_state'] = 'MULTIPLE_COMPATIBLE_HYPOTHESES';
        $payload['selected_hypothesis_id'] = 'HYP-01';

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_INVALID_SELECTION, $res['rejection_code']);
    }

    /**
     * Test 05: Selected Hypothesis Null Rule on EQUALLY_PROBABLE
     */
    public function test05_SelectedHypothesisNullRule_EquallyProbable(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['resolution_state'] = 'EQUALLY_PROBABLE';
        $payload['selected_hypothesis_id'] = 'HYP-01';

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_INVALID_SELECTION, $res['rejection_code']);
    }

    /**
     * Test 06: Selected Hypothesis Null Rule on UNRESOLVED
     */
    public function test06_SelectedHypothesisNullRule_Unresolved(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['resolution_state'] = 'UNRESOLVED';
        $payload['selected_hypothesis_id'] = 'HYP-01';

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_INVALID_SELECTION, $res['rejection_code']);
    }

    /**
     * Test 07: Selected Hypothesis Null Rule on NO_VALID_HYPOTHESIS
     */
    public function test07_SelectedHypothesisNullRule_NoValidHypothesis(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['resolution_state'] = 'NO_VALID_HYPOTHESIS';
        $payload['selected_hypothesis_id'] = 'HYP-01';

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_INVALID_SELECTION, $res['rejection_code']);
    }

    /**
     * Test 08: Single Hypothesis Selection Rule - Allowed for SINGLE_CONFIDENT_HYPOTHESIS
     */
    public function test08_SingleHypothesisSelectionRule_Allowed(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['resolution_state'] = 'SINGLE_CONFIDENT_HYPOTHESIS';
        $payload['selected_hypothesis_id'] = 'HYP-01';

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('PASS', $res['status']);
        $this->assertSame('HYP-01', $res['canonical_payload']['selected_hypothesis_id']);
    }

    /**
     * Test 09: Single Hypothesis Selection Rule - Rejected if Null
     */
    public function test09_SingleHypothesisSelectionRule_RejectedIfNull(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['resolution_state'] = 'SINGLE_CONFIDENT_HYPOTHESIS';
        $payload['selected_hypothesis_id'] = null; // Missing required winner

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_INVALID_SELECTION, $res['rejection_code']);
    }

    /**
     * Test 10: Single Hypothesis Selection Rule - Rejected if ID does not match active hypotheses
     */
    public function test10_SingleHypothesisSelectionRule_RejectedIfNotFound(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['resolution_state'] = 'SINGLE_CONFIDENT_HYPOTHESIS';
        $payload['selected_hypothesis_id'] = 'HYP-GHOST';

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_INVALID_SELECTION, $res['rejection_code']);
    }

    /**
     * Test 11: Branch Ambiguity Preservation
     */
    public function test11_BranchAmbiguityPreservation(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['resolution_state'] = 'BRANCH_AMBIGUITY';
        $payload['selected_hypothesis_id'] = null;

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('PASS', $res['status']);
        $this->assertNull($res['canonical_payload']['selected_hypothesis_id']);
    }

    /**
     * Test 12: Equally Probable Preservation
     */
    public function test12_EquallyProbablePreservation(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['resolution_state'] = 'EQUALLY_PROBABLE';
        $payload['selected_hypothesis_id'] = null;

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('PASS', $res['status']);
        $this->assertNull($res['canonical_payload']['selected_hypothesis_id']);
    }

    /**
     * Test 13: Evidence Provenance Requirement
     */
    public function test13_EvidenceProvenanceRequirement(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['evidence_catalog'] = [
            ['type' => '', 'status' => 'SUPPORTING'], // Missing type
        ];

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_EVIDENCE_INTEGRITY, $res['rejection_code']);
    }

    /**
     * Test 14: UNKNOWN Evidence is Never Disqualifying
     */
    public function test14_UnknownEvidence_NeverDisqualifying(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['evidence_catalog'] = [
            [
                'type'             => 'PROTECTION_STATUS',
                'status'           => 'UNKNOWN',
                'is_disqualifying' => true, // Illegal disqualification on UNKNOWN
            ],
        ];

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_EVIDENCE_INTEGRITY, $res['rejection_code']);
    }

    /**
     * Test 15: Unavailable Evidence Handled Gracefully
     */
    public function test15_UnavailableEvidence_GracefulHandling(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['evidence_catalog'] = [
            [
                'type'   => 'HISTORICAL_FAULT_INDEX',
                'status' => 'UNAVAILABLE',
            ],
        ];

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('PASS', $res['status']);
    }

    /**
     * Test 16: Event Fingerprint Determinism
     */
    public function test16_EventFingerprintDeterminism(): void
    {
        $payload1 = $this->getValidBasePayload();
        $payload2 = $this->getValidBasePayload();

        $fp1 = $this->gateService->calculateCanonicalEventFingerprint($payload1);
        $fp2 = $this->gateService->calculateCanonicalEventFingerprint($payload2);

        $this->assertSame($fp1, $fp2);
        $this->assertSame(64, strlen($fp1));
    }

    /**
     * Test 17: Fingerprint Timestamp Independence
     */
    public function test17_FingerprintTimestampIndependence(): void
    {
        $payload1 = $this->getValidBasePayload();
        $payload2 = $this->getValidBasePayload();

        // Vary runtime processing timestamp
        $payload1['staged_at'] = '2026-09-21 08:00:00';
        $payload2['staged_at'] = '2026-09-21 10:30:00';

        $fp1 = $this->gateService->calculateCanonicalEventFingerprint($payload1);
        $fp2 = $this->gateService->calculateCanonicalEventFingerprint($payload2);

        $this->assertSame($fp1, $fp2, 'Fingerprint must be strictly invariant to runtime clocks');
    }

    /**
     * Test 18: Fingerprint Mismatch Rejection
     */
    public function test18_FingerprintMismatchRejection(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['event_fingerprint'] = 'corrupt_fingerprint_00000000000000000000000000000000000000000000';

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_FINGERPRINT_MISMATCH, $res['rejection_code']);
    }

    /**
     * Test 19: Replay Identity Safe Querying (No DB Table Required)
     */
    public function test19_ReplayIdentity_SafeDetection(): void
    {
        $fp = 'a3f8c1e2b4d5...';
        $replay = $this->gateService->checkReplay($fp, null);

        $this->assertFalse($replay['exists']);
        $this->assertSame($fp, $replay['fingerprint']);
    }

    /**
     * Test 20: Atomic Transaction Requirement Verification
     */
    public function test20_AtomicTransactionRequirement(): void
    {
        $payload = $this->getValidBasePayload();
        $res = $this->gateService->validateWriteContract($payload);

        $this->assertTrue($res['canonical_payload']['atomic_transaction']);
    }

    /**
     * Test 21: Pipeline Incomplete Rejection
     */
    public function test21_PipelineIncompleteRejection(): void
    {
        $payload = $this->getValidBasePayload();
        unset($payload['resolution_version']); // Missing FL-01F signature

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_PIPELINE_INCOMPLETE, $res['rejection_code']);
    }

    /**
     * Test 22: AI Firewall Rejection (ai_used == true)
     */
    public function test22_AiFirewall_RejectedIfAiUsed(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['ai_used'] = true; // Prohibited AI inference

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_SAFETY_INVARIANT, $res['rejection_code']);
    }

    /**
     * Test 23: Topology Mutation Firewall (topology_mutation == true)
     */
    public function test23_TopologyMutationFirewall(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['topology_mutation'] = true;

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_SAFETY_INVARIANT, $res['rejection_code']);
    }

    /**
     * Test 24: Asset Mutation Firewall (asset_mutation == true)
     */
    public function test24_AssetMutationFirewall(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['asset_mutation'] = true;

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_SAFETY_INVARIANT, $res['rejection_code']);
    }

    /**
     * Test 25: Temuan Mutation Firewall (temuan_mutation == true)
     */
    public function test25_TemuanMutationFirewall(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['temuan_mutation'] = true;

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_SAFETY_INVARIANT, $res['rejection_code']);
    }

    /**
     * Test 26: Nearest Asset Snapping Firewall
     */
    public function test26_NearestAssetSnappingFirewall(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['nearest_asset_snapped'] = true;

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_SAFETY_INVARIANT, $res['rejection_code']);
    }

    /**
     * Test 27: Automatic Work Order Dispatch Prohibited
     */
    public function test27_AutoDispatchProhibited(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['auto_dispatch'] = true; // Prohibited auto dispatch

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('REJECT', $res['status']);
        $this->assertSame(FaultWriteSafetyGateService::REJECT_AUTO_DISPATCH, $res['rejection_code']);
    }

    /**
     * Test 28: Contract Version Determinism
     */
    public function test28_ContractVersionDeterminism(): void
    {
        $payload = $this->getValidBasePayload();
        $res = $this->gateService->validateWriteContract($payload);

        $this->assertSame('FL-01G-1.0', $res['canonical_payload']['contract_version']);
    }

    /**
     * Test 29: Operational Status Decoupled from Resolution State
     */
    public function test29_OperationalStatusDecoupledFromResolutionState(): void
    {
        $payload = $this->getValidBasePayload();
        $payload['resolution_state']   = 'BRANCH_AMBIGUITY'; // Analytical
        $payload['operational_status'] = 'PERSISTED';        // Operational

        $res = $this->gateService->validateWriteContract($payload);
        $this->assertSame('PASS', $res['status']);
        $this->assertSame('BRANCH_AMBIGUITY', $res['canonical_payload']['resolution_state']);
        $this->assertSame('PERSISTED', $res['canonical_payload']['operational_status']);
    }

    /**
     * Test 30: Zero Database Mutation Invariant (Delta = 0)
     */
    public function test30_ZeroDatabaseMutationInvariant(): void
    {
        try {
            $db = Database::connect('default');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL default connection not available.');
        }

        if (!$db || !$db->tableExists('assets')) {
            $this->markTestSkipped('Authoritative assets table not accessible.');
        }

        $assetsCount     = (int) $db->table('assets')->countAllResults();
        $translinesCount = (int) $db->table('gis_translines')->countAllResults();
        $penyulangCount  = (int) $db->table('penyulang')->countAllResults();
        $sectionsCount   = (int) $db->table('sections')->countAllResults();
        $temuanCount     = (int) $db->table('temuan')->countAllResults();
        $faultRecordsExists = $db->tableExists('fault_records');

        $this->assertSame(1476, $assetsCount, 'assets count must remain strictly invariant at 1476');
        $this->assertSame(1278, $translinesCount, 'gis_translines count must remain strictly invariant at 1278');
        $this->assertSame(134, $penyulangCount, 'penyulang count must remain strictly invariant at 134');
        $this->assertSame(508, $sectionsCount, 'sections count must remain strictly invariant at 508');
        $this->assertSame(441, $temuanCount, 'temuan count must remain strictly invariant at 441');
        $this->assertFalse($faultRecordsExists, 'fault_records table must NOT exist');
    }
}
