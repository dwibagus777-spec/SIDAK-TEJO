<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\FeederHealthIntelligenceService;
use App\Services\ExecutiveAiAdvisoryService;
use App\Database\Seeds\ConstructionIntelligenceSeeder;

/**
 * Unit Test Suite for Phase CC-04 Executive Decision Fabric (Contract v1.2)
 * Tests Gates E0 through E9 and Invariants E2-A, E3-A, E6-A, E9-A.
 */
class ExecutiveIntelligenceTest extends CIUnitTestCase
{
    protected FeederHealthIntelligenceService $fhiService;
    protected ExecutiveAiAdvisoryService $aiService;

    protected function setUp(): void
    {
        parent::setUp();

        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        $this->ensureTablesExist($forge, $db);

        $this->fhiService = new FeederHealthIntelligenceService($db);
        $this->aiService  = new ExecutiveAiAdvisoryService();

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
        if (!$db->tableExists('executive_decision_logs')) {
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

        // historical_feeder_interruptions
        if (!$db->tableExists('historical_feeder_interruptions')) {
            $forge->addField([
                'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'penyulang_id' => ['type' => 'INTEGER'],
                'duration_minutes' => ['type' => 'DECIMAL', 'default' => 0.0],
                'start_time' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('historical_feeder_interruptions', true);
        }
    }

    public function testGateE0AndE2AWeightConservationAndDeterministicFhiCalculation(): void
    {
        $policy = $this->fhiService->ensureDefaultPolicy();
        $this->assertEquals('FHI-v1.0', $policy['policy_code']);

        $db = \Config\Database::connect();
        $rules = $db->table('feeder_health_policy_rules')->where('policy_version_id', $policy['id'])->get()->getResultArray();
        
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

    public function testGateE5RankedDecisionMatrixConflictResolution(): void
    {
        $db = \Config\Database::connect();

        // 1. Insert critical equipment finding (Trigger: CRITICAL_EQUIPMENT_DEFECT)
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

        // 2. Insert 4 interruptions (Trigger: UNSTABLE_TRIP_FREQUENCY)
        for ($i = 0; $i < 4; $i++) {
            $db->table('historical_feeder_interruptions')->insert([
                'penyulang_id'     => 1,
                'duration_minutes' => 30.0,
                'start_time'       => date('Y-m-d H:i:s'),
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
        }

        $res = $this->fhiService->calculateFeederHealth(1);
        $exp = json_decode($res['explanation_json'], true);
        $dec = $exp['decision_matrix'];

        // Critical Equipment defect has higher severity base (85 + 5 = 90) vs Trip frequency (70 + 16 = 86)
        $this->assertEquals('CRITICAL_EQUIPMENT_DEFECT', $dec['primary_driver']['driver_code']);
        $this->assertEquals('P1 - IMMEDIATE', $dec['primary_driver']['priority']);
        $this->assertEquals('PDKB-TM', $dec['primary_driver']['assigned_unit']);
        $this->assertNotEmpty($dec['secondary_drivers']);
        $this->assertEquals('UNSTABLE_TRIP_FREQUENCY', $dec['secondary_drivers'][0]['driver_code']);
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
}
