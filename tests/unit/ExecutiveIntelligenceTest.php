<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\FeederHealthIntelligenceService;
use App\Services\ExecutiveAiAdvisoryService;
use App\Services\ConstructionAssetIntelligenceService;
use App\Database\Seeds\ConstructionIntelligenceSeeder;

/**
 * Unit Test Suite for Phase CC-04 Executive Decision Fabric (Contract v1.2)
 * Tests Gates E0 through E9 and Invariants E2-A, E3-A, E6-A, E9-A.
 */
class ExecutiveIntelligenceTest extends CIUnitTestCase
{
    protected FeederHealthIntelligenceService $fhiService;
    protected ExecutiveAiAdvisoryService $aiService;
    protected ConstructionAssetIntelligenceService $assetIntelService;

    protected function setUp(): void
    {
        parent::setUp();

        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        $this->ensureTablesExist($forge, $db);

        $this->fhiService        = new FeederHealthIntelligenceService($db);
        $this->aiService         = new ExecutiveAiAdvisoryService();
        $this->assetIntelService = new ConstructionAssetIntelligenceService($db);

        // Seed initial data
        $seeder = new ConstructionIntelligenceSeeder(new \Config\Database());
        $seeder->run();
    }

    private function ensureTablesExist($forge, $db): void
    {
        $forge->dropTable('executive_decision_logs', true);
        $forge->dropTable('feeder_health_classifications', true);
        $forge->dropTable('feeder_health_policy_rules', true);
        $forge->dropTable('feeder_health_policy_versions', true);

        // feeder_health_policy_versions
        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'policy_code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'policy_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'ACTIVE'],
            'effective_from' => ['type' => 'DATETIME', 'null' => true],
            'effective_to' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('feeder_health_policy_versions', true);

        // feeder_health_policy_rules
        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'policy_version_id' => ['type' => 'INTEGER'],
            'metric_key' => ['type' => 'VARCHAR', 'constraint' => 50],
            'weight' => ['type' => 'DECIMAL', 'default' => 0.2],
            'threshold_sempurna_min' => ['type' => 'DECIMAL', 'default' => 85.00],
            'threshold_sakit_min' => ['type' => 'DECIMAL', 'default' => 70.00],
            'threshold_kronis_min' => ['type' => 'DECIMAL', 'default' => 50.00],
            'threshold_kritis_max' => ['type' => 'DECIMAL', 'default' => 49.99],
            'rule_params_json' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('feeder_health_policy_rules', true);

        // feeder_health_classifications
        $forge->addField([
            'id'                            => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'penyulang_id'                  => ['type' => 'INTEGER'],
            'calculation_policy_version'    => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'FHI-v1.0'],
            'period_month'                  => ['type' => 'VARCHAR', 'constraint' => 7],
            'health_score'                  => ['type' => 'DECIMAL', 'null' => true],
            'health_classification'         => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'UNRESOLVED'],
            'fhi_status'                    => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'UNRESOLVED'],
            'data_completeness_ratio'       => ['type' => 'DECIMAL', 'default' => 0.0000],
            'physical_coverage_ratio'       => ['type' => 'DECIMAL', 'default' => 0.0000],
            'asset_health_score'            => ['type' => 'DECIMAL', 'null' => true],
            'finding_severity_score'        => ['type' => 'DECIMAL', 'null' => true],
            'reliability_score'             => ['type' => 'DECIMAL', 'null' => true],
            'recurrence_score'              => ['type' => 'DECIMAL', 'null' => true],
            'primary_driver'                => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'primary_driver_score'          => ['type' => 'DECIMAL', 'null' => true],
            'assigned_unit'                 => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'priority_level'                => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'interruption_count'            => ['type' => 'INTEGER', 'default' => 0],
            'interruption_duration_minutes' => ['type' => 'DECIMAL', 'default' => 0.00],
            'critical_findings_count'       => ['type' => 'INTEGER', 'default' => 0],
            'recurring_findings_count'      => ['type' => 'INTEGER', 'default' => 0],
            'bom_degradation_score'         => ['type' => 'DECIMAL', 'default' => 0.00],
            'overload_events_count'         => ['type' => 'INTEGER', 'default' => 0],
            'fingerprint_json'              => ['type' => 'TEXT', 'null' => true],
            'explanation_json'              => ['type' => 'TEXT', 'null' => true],
            'advisory_narrative'            => ['type' => 'TEXT', 'null' => true],
            'calculated_at'                 => ['type' => 'DATETIME', 'null' => true],
            'created_at'                    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'                    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('feeder_health_classifications', true);

        // executive_decision_logs
        $forge->addField([
            'id'                              => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'penyulang_id'                    => ['type' => 'INTEGER'],
            'feeder_health_classification_id' => ['type' => 'INTEGER', 'null' => true],
            'recommendation_code'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'recommended_action'              => ['type' => 'TEXT'],
            'assigned_unit'                   => ['type' => 'VARCHAR', 'constraint' => 100],
            'priority_level'                  => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'P2 - HIGH'],
            'baseline_fhi'                    => ['type' => 'DECIMAL'],
            'approval_status'                 => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'PENDING'],
            'approved_by'                     => ['type' => 'INTEGER', 'null' => true],
            'approved_at'                     => ['type' => 'DATETIME', 'null' => true],
            'work_order_id'                   => ['type' => 'INTEGER', 'null' => true],
            'outcome_verified_fhi'            => ['type' => 'DECIMAL', 'null' => true],
            'delta_fhi'                       => ['type' => 'DECIMAL', 'null' => true],
            'outcome_notes'                   => ['type' => 'TEXT', 'null' => true],
            'created_at'                      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'                      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('executive_decision_logs', true);
    }

    public function testGateE0DeterministicCalculationAndE2AWeightConservation(): void
    {
        $policy = $this->fhiService->ensureDefaultPolicy();
        $this->assertEquals('FHI-v1.0', $policy['policy_code']);

        $rules = (new \App\Models\FeederHealthPolicyRuleModel())->where('policy_version_id', $policy['id'])->findAll();
        $this->assertCount(5, $rules);
        
        $sumWeights = 0.0;
        foreach ($rules as $r) {
            $sumWeights += (float)$r['weight'];
        }

        // Invariant E2-A: Sum of weights must equal exactly 1.0000
        $this->assertEquals(1.0000, round($sumWeights, 4));

        // Deterministic Calculation Test across multiple invocations
        $res1 = $this->fhiService->calculateFeederHealth(1);
        $res2 = $this->fhiService->calculateFeederHealth(1);

        $this->assertEquals($res1['health_score'], $res2['health_score']);
        $this->assertEquals($res1['health_classification'], $res2['health_classification']);
        $this->assertEquals($res1['primary_driver'], $res2['primary_driver']);
    }

    public function testCanonicalWeightsProviderReturnsExactContractValues(): void
    {
        $weights = $this->fhiService->getFhiWeights();
        $this->assertEquals(0.20, $weights['physical']);
        $this->assertEquals(0.25, $weights['asset']);
        $this->assertEquals(0.25, $weights['finding']);
        $this->assertEquals(0.20, $weights['reliability']);
        $this->assertEquals(0.10, $weights['recurrence']);
        $this->assertEquals(1.0000, round(array_sum($weights), 4));
    }

    public function testAnyOnePointZeroFiveConfigurationFailsGateE2A(): void
    {
        $db = \Config\Database::connect();
        
        // Insert custom policy with non-conserved weights (Sum = 1.0500)
        $db->table('feeder_health_policy_versions')->insert([
            'policy_code' => 'FHI-BAD-WEIGHT-105',
            'policy_name' => 'Bad Weight Policy 1.05',
            'status'      => 'ACTIVE',
        ]);
        $customPolicyId = (int)$db->insertID();

        $rules = [
            ['PHYSICAL_COVERAGE', 0.2000],
            ['BOM_DEGRADATION', 0.2500],
            ['CRITICAL_FINDINGS', 0.2500],
            ['GANGGUAN_FREQUENCY', 0.2500], // 0.25 instead of 0.20 -> Sum = 1.0500
            ['RECURRING_FINDINGS', 0.1000],
        ];

        foreach ($rules as $r) {
            $db->table('feeder_health_policy_rules')->insert([
                'policy_version_id' => $customPolicyId,
                'metric_key'        => $r[0],
                'weight'            => $r[1],
            ]);
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Gate E2-A Violation');

        $this->fhiService->calculateFeederHealth(1, null, 'FHI-BAD-WEIGHT-105');
    }

    public function testMissingPillarDataDoesNotRedistributeWeight(): void
    {
        // For feeder with no assets (subScoreAsset is null)
        $res = $this->fhiService->calculateFeederHealth(1);
        $exp = json_decode($res['explanation_json'], true);
        $breakdown = $exp['score_breakdown'];

        // Asset weight must strictly remain 0.25 and NOT be redistributed to other pillars
        $this->assertEquals(0.25, $breakdown['asset_health']['weight']);
        $this->assertEquals(0.20, $breakdown['physical_coverage']['weight']);
        $this->assertEquals(0.25, $breakdown['finding_severity']['weight']);
        $this->assertEquals(0.20, $breakdown['reliability']['weight']);
        $this->assertEquals(0.10, $breakdown['chronicity']['weight']);
    }

    public function testResolvedAssetSynchronizationBetweenCr06gAndCc04(): void
    {
        $db = \Config\Database::connect();

        // 1. Query feeder 1 assets
        $feederAssets = $db->table('assets')->where('penyulang_id', 1)->where('deleted_at IS NULL')->get()->getResultArray();
        
        $cr06gResolvedCount = 0;
        foreach ($feederAssets as $a) {
            $h = $this->assetIntelService->calculateAssetHealth((int)$a['id']);
            if ($h['resolution_status'] === 'RESOLVED' && $h['asset_health_score'] !== null) {
                $cr06gResolvedCount++;
            }
        }

        // 2. Query CC-04 calculation
        $cc04Data = $this->fhiService->calculateFeederHealth(1);
        $exp = json_decode($cc04Data['explanation_json'], true);
        $cc04ResolvedCount = $exp['score_breakdown']['asset_health']['resolved'];

        // Synchronized contract assertion
        $this->assertEquals($cr06gResolvedCount, $cc04ResolvedCount);
    }

    public function testGateE3AResolutionDenominatorIntegrityAndUnresolvedProtection(): void
    {
        $db = \Config\Database::connect();

        // Create an unconfigured feeder
        $db->table('penyulang')->insert([
            'id'             => 99,
            'ulp_id'         => 1,
            'kode_penyulang' => 'PYL-UNCONFIG',
            'nama_penyulang' => 'Feeder Tanpa Konfigurasi Fisik',
        ]);

        $res = $this->fhiService->calculateFeederHealth(99);

        // Missing physical topology must yield UNRESOLVED, not 100
        $this->assertEquals('UNRESOLVED', $res['fhi_status']);
        $this->assertNull($res['health_score']);
        $this->assertEquals('UNRESOLVED', $res['health_classification']);
    }

    public function testExactPillarContributionCalculation(): void
    {
        $res = $this->fhiService->calculateFeederHealth(1);
        $exp = json_decode($res['explanation_json'], true);
        $breakdown = $exp['score_breakdown'];

        $p1 = $breakdown['physical_coverage'];
        $p2 = $breakdown['asset_health'];
        $p3 = $breakdown['finding_severity'];
        $p4 = $breakdown['reliability'];
        $p5 = $breakdown['chronicity'];

        // Assert exact score * weight calculation
        $this->assertEquals(round(((float)$p1['sub_score']) * ((float)$p1['weight']), 2), $p1['weighted_contribution']);
        $this->assertEquals(round(((float)($p2['sub_score'] ?? 0.0)) * ((float)$p2['weight']), 2), $p2['weighted_contribution']);
        $this->assertEquals(round(((float)$p3['sub_score']) * ((float)$p3['weight']), 2), $p3['weighted_contribution']);
        $this->assertEquals(round(((float)$p4['sub_score']) * ((float)$p4['weight']), 2), $p4['weighted_contribution']);
        $this->assertEquals(round(((float)$p5['sub_score']) * ((float)$p5['weight']), 2), $p5['weighted_contribution']);

        $expectedSum = round(
            $p1['weighted_contribution'] +
            $p2['weighted_contribution'] +
            $p3['weighted_contribution'] +
            $p4['weighted_contribution'] +
            $p5['weighted_contribution'],
            2
        );

        $this->assertEquals($expectedSum, $breakdown['checksum']['computed_fhi_sum']);
    }

    public function testUnresolvedStatusForcesPrerequisiteAndLocksDispatch(): void
    {
        $db = \Config\Database::connect();

        // 1. Insert critical finding on Feeder 1
        $db->table('temuan')->insert([
            'nomor_temuan' => 'TMN-CRIT-001',
            'penyulang_id' => 1,
            'section_id'   => 1,
            'jenis_temuan' => 'KONSTRUKSI',
            'detail_temuan'=> 'Kabel putus fasa S',
            'prioritas'    => 'EMERGENCY',
            'status'       => 'OPEN',
            'deleted_at'   => null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $res = $this->fhiService->calculateFeederHealth(1);
        $exp = json_decode($res['explanation_json'], true);
        $dec = $exp['decision_matrix'];

        // When FHI is UNRESOLVED, Primary Driver is forced to UNCONFIGURED_GRID_PREREQUISITE
        if ($res['fhi_status'] === 'UNRESOLVED') {
            $this->assertEquals('UNCONFIGURED_GRID_PREREQUISITE', $dec['primary_driver']['driver_code']);
            $this->assertEquals('P2 - PREREQUISITE', $dec['primary_driver']['priority']);
            $this->assertFalse($dec['primary_driver']['dispatch_ready']);

            // Critical Equipment defect remains visible as secondary advisory
            $this->assertEquals('CRITICAL_EQUIPMENT_DEFECT', $dec['secondary_drivers'][0]['driver_code']);
            $this->assertFalse($dec['secondary_drivers'][0]['dispatch_ready']);
            $this->assertStringContainsString('NOT DISPATCH-READY', $dec['secondary_drivers'][0]['advisory_label']);
        }
    }

    public function testGateE6AFormulaVersionFingerprintAndExplainabilityJSON(): void
    {
        $res = $this->fhiService->calculateFeederHealth(1);
        $fingerprint = json_decode($res['fingerprint_json'], true);

        $this->assertNotNull($fingerprint);
        $this->assertEquals('FHI-v1.0', $fingerprint['policy_code']);
        $this->assertEquals('FHI_FORMULA_V1.2', $fingerprint['formula_version']);
        $this->assertEquals('CONSERVED', $fingerprint['weight_conservation']);
        $this->assertArrayHasKey('weight_set', $fingerprint);
        $this->assertArrayHasKey('input_snapshot', $fingerprint);
    }

    public function testGateE7AiAdvisoryIsolationPreservesDeterministicState(): void
    {
        $fhiData = $this->fhiService->calculateFeederHealth(1);
        $advisory = $this->aiService->generateExecutiveAdvisory($fhiData);

        $this->assertTrue($advisory['success']);
        $this->assertNotEmpty($advisory['advisory_narrative']);
        $this->assertStringContainsString('PASS', $advisory['isolation_check']);

        // Check that deterministic numbers match exactly
        $this->assertEquals($fhiData['health_classification'], $advisory['classification']);
    }

    public function testGateE9AClosedLoopApprovalAndOutcomeVerification(): void
    {
        $fhiData = $this->fhiService->calculateFeederHealth(1);
        $exp = json_decode($fhiData['explanation_json'], true);
        $dec = $exp['decision_matrix'];

        // 1. Log Decision Recommendation (Status: PENDING)
        $log = $this->fhiService->logDecisionRecommendation(1, $dec, (float)($fhiData['health_score'] ?? 75.0));
        $this->assertEquals('PENDING', $log['approval_status']);

        // 2. Manager Approval Gate (E9-A)
        $approved = $this->fhiService->approveDecision((int)$log['id'], 1, 'Disetujui untuk penugasan tim PDKB');
        $this->assertTrue($approved);

        // 3. Post-execution Outcome Verification (Delta FHI)
        $outcome = $this->fhiService->verifyDecisionOutcome((int)$log['id'], 90.0);
        $this->assertTrue($outcome['success']);
        $this->assertEquals(90.0, $outcome['verified_fhi']);
        $this->assertGreaterThan(0.0, $outcome['delta_fhi']);
        $this->assertTrue($outcome['improved']);
    }

    public function testAuditCc04CommandExecution(): void
    {
        $result = command('audit:cc04');
        $this->assertNotNull($result);
    }

    public function testCalculateFeederHealthAlwaysReturnsArrayEvenOnMissingOrCorruptFeeder(): void
    {
        // Non-existent feeder ID 9999
        $result = $this->fhiService->calculateFeederHealth(9999);
        $this->assertIsArray($result);
        $this->assertEquals('UNRESOLVED', $result['fhi_status']);
        $this->assertNull($result['health_score']);
        $this->assertEquals('UNRESOLVED', $result['health_classification']);
        $this->assertEquals(9999, $result['penyulang_id']);
        $this->assertNotEmpty($result['explanation_json']);
        $this->assertNotEmpty($result['fingerprint_json']);
    }
}
