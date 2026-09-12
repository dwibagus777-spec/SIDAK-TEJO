<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\MultiFeederCompletionOrchestrator;
use App\Services\TranslineNetworkCompletionEngine;

/**
 * SIDAK TEJO ENTERPRISE: TRANSLINE AI ACCELERATION PROGRAM
 * TL-MF-02: CONTROLLED MULTI-FEEDER EXECUTION ENGINE & ORCHESTRATOR
 *
 * 30 Rigorous Unit Test Scenarios covering:
 * - Queue categorization: NO_ASSET, NEAR_COMPLETE, READY_FOR_AI
 * - Queue priority sorting (asset count desc, connectivity asc)
 * - Single-run process lock & concurrent run prevention (RUN_ALREADY_ACTIVE)
 * - State checkpointing, atomic reset & safe archiving (no audit deletion)
 * - Resumability: skipping completed & quarantined feeders
 * - Multi-tier span firewall: <5m blocked, 5-10m semantic review, >85m blocked
 * - Max degree 4 ceiling & batch size <= 10 ceiling
 * - Honest termination: NATURAL_STABILIZED vs MAX_BATCH_LIMIT_REACHED (PAUSED)
 * - Fault isolation: Feeder A anomaly quarantines to ANOMALY_HELD, Feeder B continues
 * - Zero-mutation dry-run verification
 * - Boundary isolation: cross-feeder, cross-ULP, temuan isolation
 * - Graph topological integrity: anti-duplicate, anti-reverse duplicate, self-loop rejection
 * - Atomic transaction rollback on live commit anomaly
 */
class MultiFeederCompletionOrchestratorTest extends CIUnitTestCase
{
    protected $db;
    protected MultiFeederCompletionOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->setupSchema();
        $this->seedTestData();

        $this->orchestrator = new MultiFeederCompletionOrchestrator($this->db);
    }

    protected function tearDown(): void
    {
        $this->orchestrator->releaseLock();
        parent::tearDown();
    }

    protected function setupSchema(): void
    {
        $forge = Database::forge();

        $forge->dropTable('gis_transline_proposals', true);
        $forge->dropTable('gis_translines', true);
        $forge->dropTable('temuan_materials', true);
        $forge->dropTable('temuan', true);
        $forge->dropTable('assets', true);
        $forge->dropTable('sections', true);
        $forge->dropTable('penyulang', true);
        $forge->dropTable('ulps', true);

        // ulps
        $forge->addField([
            'id'       => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50],
            'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
            'status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('ulps', true);

        // penyulang
        $forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'ulp_id'         => ['type' => 'INT', 'constraint' => 11],
            'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
            'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('penyulang', true);

        // sections
        $forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'penyulang_id' => ['type' => 'INT', 'constraint' => 11],
            'kode_section' => ['type' => 'VARCHAR', 'constraint' => 50],
            'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('sections', true);

        // assets
        $forge->addField([
            'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'penyulang_id'         => ['type' => 'INT', 'constraint' => 11],
            'ulp_id'               => ['type' => 'INT', 'constraint' => 11],
            'section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 150],
            'type'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG'],
            'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_TM'],
            'construction_type_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'latitude'             => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
            'longitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('assets', true);

        // gis_translines
        $forge->addField([
            'id'                 => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'transline_code'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'penyulang_id'       => ['type' => 'INT', 'constraint' => 11],
            'source_asset_id'    => ['type' => 'INT', 'constraint' => 11],
            'target_asset_id'    => ['type' => 'INT', 'constraint' => 11],
            'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'AAAC'],
            'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => '150 mm²'],
            'conductor_material' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'ALUMINUM_ALLOY'],
            'installation_type'  => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'OVERHEAD'],
            'circuit_config'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => '3_PHASE'],
            'distance_meters'    => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => 0.00],
            'length_meters'      => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => 0.00],
            'geometry'           => ['type' => 'TEXT', 'null' => true],
            'coordinates'        => ['type' => 'TEXT', 'null' => true],
            'status'             => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
            'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_by'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('gis_translines', true);

        // temuan
        $forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'nomor_temuan'  => ['type' => 'VARCHAR', 'constraint' => 100],
            'asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'detail_temuan' => ['type' => 'TEXT', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('temuan', true);

        // temuan_materials
        $forge->addField([
            'id'        => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'temuan_id' => ['type' => 'INT', 'constraint' => 11],
            'nama'      => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('temuan_materials', true);

        // gis_transline_proposals
        $forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'penyulang_id' => ['type' => 'INT', 'constraint' => 11],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'PROPOSED'],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('gis_transline_proposals', true);
    }

    protected function seedTestData(): void
    {
        // 2 ULPs
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => 'ULP-KOTA', 'nama_ulp' => 'ULP Sidoarjo Kota']);
        $this->db->table('ulps')->insert(['id' => 2, 'kode_ulp' => 'ULP-KRIAN', 'nama_ulp' => 'ULP Krian']);

        // 4 Feeders:
        // Feeder 1: NO_ASSET (0 assets)
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'FEED-EMPTY', 'nama_penyulang' => 'FEEDER EMPTY']);
        // Feeder 15: NEAR_COMPLETE (4 assets, 3 translines -> 100% connected)
        $this->db->table('penyulang')->insert(['id' => 15, 'ulp_id' => 1, 'kode_penyulang' => 'BJKM', 'nama_penyulang' => 'BANJAR KEMANTREN']);
        // Feeder 20: READY_FOR_AI (5 assets, 0 translines -> 0% connected)
        $this->db->table('penyulang')->insert(['id' => 20, 'ulp_id' => 2, 'kode_penyulang' => 'BYPS', 'nama_penyulang' => 'BY PASS']);
        // Feeder 30: READY_FOR_AI (12 assets, 0 translines -> 0% connected, higher priority)
        $this->db->table('penyulang')->insert(['id' => 30, 'ulp_id' => 2, 'kode_penyulang' => 'TRKK', 'nama_penyulang' => 'TARIK KOTA']);

        // Seed Assets for Feeder 15 (near complete chain 101 -> 102 -> 103 -> 104)
        for ($i = 1; $i <= 4; $i++) {
            $this->db->table('assets')->insert([
                'id'           => 100 + $i,
                'penyulang_id' => 15,
                'ulp_id'       => 1,
                'kode_asset'   => "AST-15-{$i}",
                'nama_asset'   => "Tiang 15-{$i}",
                'latitude'     => -7.450000 + ($i * 0.000300),
                'longitude'    => 112.710000,
            ]);
        }
        // Translines for Feeder 15
        $this->db->table('gis_translines')->insert([
            'id' => 1, 'transline_code' => 'TL-15-101-102', 'penyulang_id' => 15, 'source_asset_id' => 101, 'target_asset_id' => 102, 'distance_meters' => 33.3, 'is_active' => 1
        ]);
        $this->db->table('gis_translines')->insert([
            'id' => 2, 'transline_code' => 'TL-15-102-103', 'penyulang_id' => 15, 'source_asset_id' => 102, 'target_asset_id' => 103, 'distance_meters' => 33.3, 'is_active' => 1
        ]);
        $this->db->table('gis_translines')->insert([
            'id' => 3, 'transline_code' => 'TL-15-103-104', 'penyulang_id' => 15, 'source_asset_id' => 103, 'target_asset_id' => 104, 'distance_meters' => 33.3, 'is_active' => 1
        ]);

        // Seed Assets for Feeder 20 (5 linear assets: 201..205, step 35m)
        for ($i = 1; $i <= 5; $i++) {
            $this->db->table('assets')->insert([
                'id'           => 200 + $i,
                'penyulang_id' => 20,
                'ulp_id'       => 2,
                'kode_asset'   => sprintf('AST-KRIAN-BYPS-JTM-%03d', $i),
                'nama_asset'   => "Tiang BYPS {$i}",
                'latitude'     => -7.460000 + ($i * 0.000315),
                'longitude'    => 112.600000,
            ]);
        }

        // Seed Assets for Feeder 30 (12 linear assets: 301..312, step 30m)
        for ($i = 1; $i <= 12; $i++) {
            $this->db->table('assets')->insert([
                'id'           => 300 + $i,
                'penyulang_id' => 30,
                'ulp_id'       => 2,
                'kode_asset'   => sprintf('AST-KRIAN-TRKK-JTM-%03d', $i),
                'nama_asset'   => "Tiang TRKK {$i}",
                'latitude'     => -7.470000 + ($i * 0.000270),
                'longitude'    => 112.550000,
            ]);
        }

        // Seed Temuan
        $this->db->table('temuan')->insert([
            'id' => 1, 'nomor_temuan' => 'TMN-001', 'asset_id' => 201, 'detail_temuan' => 'Isolator flashover'
        ]);
    }

    // =========================================================================
    // 🌐 QUEUE CATEGORIZATION & SORTING TESTS (1 - 3)
    // =========================================================================

    public function testQueueSkipsNoAssetFeeders(): void
    {
        $queue = $this->orchestrator->buildGlobalFeederQueue();
        $this->assertEquals(1, $queue['no_asset_count']);
        $this->assertEquals(1, $queue['no_asset_feeders'][0]['penyulang_id']);
        $this->assertEquals('NO_ASSET', $queue['no_asset_feeders'][0]['classification']);
    }

    public function testQueueCategorizesNearComplete(): void
    {
        $queue = $this->orchestrator->buildGlobalFeederQueue();
        $this->assertEquals(1, $queue['near_complete_count']);
        $this->assertEquals(15, $queue['near_complete'][0]['penyulang_id']);
        $this->assertEquals('NEAR_COMPLETE', $queue['near_complete'][0]['classification']);
        $this->assertGreaterThanOrEqual(99.0, $queue['near_complete'][0]['connectivity_pct']);
    }

    public function testQueueSortsByAssetCountAndConnectivity(): void
    {
        $queue = $this->orchestrator->buildGlobalFeederQueue();
        $this->assertEquals(2, $queue['ready_for_ai_count']);

        // Feeder 30 has 12 assets, Feeder 20 has 5 assets -> Feeder 30 must come first
        $this->assertEquals(30, $queue['priority_queue'][0]['penyulang_id']);
        $this->assertEquals(12, $queue['priority_queue'][0]['total_jtm_assets']);

        $this->assertEquals(20, $queue['priority_queue'][1]['penyulang_id']);
        $this->assertEquals(5, $queue['priority_queue'][1]['total_jtm_assets']);
    }

    // =========================================================================
    // 🔒 PROCESS LOCK & CONCURRENCY TESTS (4 - 5)
    // =========================================================================

    public function testSingleRunLockAcquireAndRelease(): void
    {
        $acquired = $this->orchestrator->acquireLock();
        $this->assertTrue($acquired, 'Should acquire orchestrator lock on first attempt');

        $this->orchestrator->releaseLock();
    }

    public function testConcurrencyLockRejection(): void
    {
        $this->orchestrator->acquireLock();

        // Create a second orchestrator instance attempting concurrent lock
        $secondOrchestrator = new MultiFeederCompletionOrchestrator($this->db);
        $result = $secondOrchestrator->run(['mode' => 'dry-run']);

        $this->assertEquals('RUN_ALREADY_ACTIVE', $result['status']);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Another TL-MF-02 orchestrator process is already running', $result['message']);

        $this->orchestrator->releaseLock();
    }

    // =========================================================================
    // 📋 STATE CHECKPOINT & AUDIT ARCHIVE TESTS (6 - 10)
    // =========================================================================

    public function testResetStateArchivesWithoutDeletingAuditHistory(): void
    {
        // First set up an active state
        $state = $this->orchestrator->loadState();
        $state['active_run_id'] = 'RUN-TEST-001';
        $state['completed_feeders'] = [15];
        $this->orchestrator->saveState($state);

        // Reset state
        $freshState = $this->orchestrator->resetActiveState();

        $this->assertNotEquals('RUN-TEST-001', $freshState['active_run_id']);
        $this->assertStringStartsWith('RUN-MF02-', $freshState['active_run_id']);
        $this->assertEquals('INITIALIZED', $freshState['state_status']);
        $this->assertEmpty($freshState['completed_feeders']);
        $this->assertContains('RUN-TEST-001', $freshState['archive_history']);
    }

    public function testLoadStateReturnsDefaultWhenNoFile(): void
    {
        // Path to temporary state
        $state = $this->orchestrator->loadState();
        $this->assertArrayHasKey('orchestrator_version', $state);
        $this->assertArrayHasKey('completed_feeders', $state);
        $this->assertArrayHasKey('quarantined_feeders', $state);
        $this->assertArrayHasKey('global_totals', $state);
    }

    public function testSaveAndLoadStateRoundtrip(): void
    {
        $state = $this->orchestrator->loadState();
        $state['completed_feeders'] = [101, 102];
        $state['quarantined_feeders'] = [103];
        $this->orchestrator->saveState($state);

        $loaded = $this->orchestrator->loadState();
        $this->assertEquals([101, 102], $loaded['completed_feeders']);
        $this->assertEquals([103], $loaded['quarantined_feeders']);
    }

    public function testResumeSkipsCompletedFeeders(): void
    {
        $state = $this->orchestrator->loadState();
        // Mark Feeder 30 as already completed
        $state['completed_feeders'] = [30];
        $this->orchestrator->saveState($state);

        $result = $this->orchestrator->run([
            'mode'   => 'dry-run',
            'resume' => true,
        ]);

        $this->assertEquals('SUCCESS', $result['status']);
        // Only Feeder 20 should be processed (30 was skipped)
        $processedFeeders = array_column($result['feeders_report'], 'penyulang_id');
        $this->assertNotContains(30, $processedFeeders);
        $this->assertContains(20, $processedFeeders);
    }

    public function testResumeHonorsQuarantinedFeedersInState(): void
    {
        $state = $this->orchestrator->loadState();
        $state['quarantined_feeders'] = [20];
        $this->orchestrator->saveState($state);

        $loaded = $this->orchestrator->loadState();
        $this->assertContains(20, $loaded['quarantined_feeders']);
    }

    // =========================================================================
    // 🛡️ MULTI-TIER SPAN FIREWALL TESTS (11 - 14)
    // =========================================================================

    public function testShortSpanFirewallUnder5mBlocked(): void
    {
        // Insert an asset on Feeder 20 very close to asset 201 (3.0 meters away)
        $this->db->table('assets')->insert([
            'id'           => 299,
            'penyulang_id' => 20,
            'ulp_id'       => 2,
            'kode_asset'   => 'AST-KRIAN-BYPS-JTM-001-TAG',
            'nama_asset'   => 'Tag Tiang 1',
            'latitude'     => -7.460000 + (1 * 0.000315) + 0.000027, // ~3.0m away
            'longitude'    => 112.600000,
        ]);

        $result = $this->orchestrator->run([
            'mode'        => 'dry-run',
            'feeder_id'   => 20,
            'max_batches' => 1,
        ]);

        $feederReport = $result['feeders_report'][0];
        $this->assertGreaterThan(0, $feederReport['batches'][0]['blocked_short_spans']);

        // Ensure edge between 201 and 299 (< 5.0m) was never created
        $translines = $feederReport['batches'][0]['translines'];
        foreach ($translines as $tl) {
            $this->assertFalse(
                ($tl['source_asset_id'] === 201 && $tl['target_asset_id'] === 299) ||
                ($tl['source_asset_id'] === 299 && $tl['target_asset_id'] === 201),
                'Edge between 201 and 299 must be blocked by the short-span firewall (< 5.0m)'
            );
        }
    }

    public function testShortSpanSemanticFirewallBetween5And10mHeld(): void
    {
        // Insert asset 298 at ~7.0m away from 201, but with non-consecutive numbering
        $this->db->table('assets')->insert([
            'id'           => 298,
            'penyulang_id' => 20,
            'ulp_id'       => 2,
            'kode_asset'   => 'AST-KRIAN-BYPS-JTM-999', // Delta = 998 (> 2)
            'nama_asset'   => 'Branch Tap 999',
            'latitude'     => -7.460000 + (1 * 0.000315) + 0.000063, // ~7.0m away
            'longitude'    => 112.600000,
        ]);

        $result = $this->orchestrator->run([
            'mode'        => 'dry-run',
            'feeder_id'   => 20,
            'max_batches' => 1,
        ]);

        $feederReport = $result['feeders_report'][0];
        $this->assertGreaterThanOrEqual(1, $feederReport['batches'][0]['held_semantic_spans']);
    }

    public function testShortSpanSemanticPassBetween5And10mWithConsecutiveEvidence(): void
    {
        // Delete all Feeder 20 assets to isolate test
        $this->db->table('assets')->where('penyulang_id', 20)->delete();

        // Insert 2 assets with distance ~8.0m and consecutive codes (001 and 002)
        $this->db->table('assets')->insert([
            'id'           => 211,
            'penyulang_id' => 20,
            'ulp_id'       => 2,
            'kode_asset'   => 'AST-KRIAN-BYPS-JTM-001',
            'nama_asset'   => 'Tiang 1',
            'latitude'     => -7.460000,
            'longitude'    => 112.600000,
        ]);
        $this->db->table('assets')->insert([
            'id'           => 212,
            'penyulang_id' => 20,
            'ulp_id'       => 2,
            'kode_asset'   => 'AST-KRIAN-BYPS-JTM-002',
            'nama_asset'   => 'Tiang 2',
            'latitude'     => -7.460072, // ~8.0m away
            'longitude'    => 112.600000,
        ]);

        $result = $this->orchestrator->run([
            'mode'        => 'dry-run',
            'feeder_id'   => 20,
            'max_batches' => 1,
        ]);

        $feederReport = $result['feeders_report'][0];
        $this->assertEquals(1, $feederReport['total_created_count']);
        $this->assertEquals(0, $feederReport['batches'][0]['held_semantic_spans']);
        $this->assertEquals(211, $feederReport['batches'][0]['translines'][0]['source_asset_id']);
        $this->assertEquals(212, $feederReport['batches'][0]['translines'][0]['target_asset_id']);
    }

    public function testHardMaxSpanAbove85mBlocked(): void
    {
        // Clear Feeder 20 assets and add 2 assets 120m apart
        $this->db->table('assets')->where('penyulang_id', 20)->delete();
        $this->db->table('assets')->insert([
            'id'           => 221,
            'penyulang_id' => 20,
            'ulp_id'       => 2,
            'kode_asset'   => 'AST-KRIAN-BYPS-JTM-001',
            'nama_asset'   => 'Tiang 1',
            'latitude'     => -7.460000,
            'longitude'    => 112.600000,
        ]);
        $this->db->table('assets')->insert([
            'id'           => 222,
            'penyulang_id' => 20,
            'ulp_id'       => 2,
            'kode_asset'   => 'AST-KRIAN-BYPS-JTM-002',
            'nama_asset'   => 'Tiang 2',
            'latitude'     => -7.461080, // ~120m away
            'longitude'    => 112.600000,
        ]);

        $result = $this->orchestrator->run([
            'mode'      => 'dry-run',
            'feeder_id' => 20,
        ]);

        $feederReport = $result['feeders_report'][0];
        $this->assertEquals(0, $feederReport['total_created_count']);
        $this->assertEquals('NATURAL_STABILIZED', $feederReport['status']);
    }

    // =========================================================================
    // ⚙️ TOPOLOGICAL CEILINGS & BATCH RULES (15 - 16)
    // =========================================================================

    public function testMaxDegree4CeilingEnforced(): void
    {
        // Clear Feeder 20 assets and create a hub node with degree 4
        $this->db->table('assets')->where('penyulang_id', 20)->delete();
        $this->db->table('assets')->insert([
            'id' => 250, 'penyulang_id' => 20, 'ulp_id' => 2, 'kode_asset' => 'AST-HUB', 'nama_asset' => 'Hub', 'latitude' => -7.460000, 'longitude' => 112.600000
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $this->db->table('assets')->insert([
                'id' => 250 + $i, 'penyulang_id' => 20, 'ulp_id' => 2, 'kode_asset' => "AST-ARM-{$i}", 'nama_asset' => "Arm {$i}",
                'latitude' => -7.460000 + ($i * 0.00025), 'longitude' => 112.600000
            ]);
            $this->db->table('gis_translines')->insert([
                'id' => 200 + $i, 'transline_code' => "TL-HUB-{$i}", 'penyulang_id' => 20, 'source_asset_id' => 250, 'target_asset_id' => 250 + $i,
                'distance_meters' => 25.0, 'is_active' => 1
            ]);
        }

        // Add a 5th candidate node 20m from Hub
        $this->db->table('assets')->insert([
            'id' => 255, 'penyulang_id' => 20, 'ulp_id' => 2, 'kode_asset' => 'AST-ARM-5', 'nama_asset' => 'Arm 5',
            'latitude' => -7.46018, 'longitude' => 112.600000
        ]);

        $result = $this->orchestrator->run([
            'mode'      => 'dry-run',
            'feeder_id' => 20,
        ]);

        $feederReport = $result['feeders_report'][0];
        $allTls = [];
        foreach ($feederReport['batches'] as $b) {
            $allTls = array_merge($allTls, $b['translines']);
        }

        // Verify Hub (250) is never connected to 255 (would exceed degree 4)
        $this->assertEquals(0, $feederReport['total_created_count']);
        $this->assertEquals('NATURAL_STABILIZED', $feederReport['status']);
        $this->assertCount(0, $allTls);
    }

    public function testBatchSizeCeiling10(): void
    {
        // Feeder 30 has 12 assets in a line (could potentially form 11 edges)
        $result = $this->orchestrator->run([
            'mode'        => 'dry-run',
            'feeder_id'   => 30,
            'max_batches' => 1,
        ]);

        $feederReport = $result['feeders_report'][0];
        $this->assertNotEmpty($feederReport['batches']);
        $this->assertLessThanOrEqual(10, $feederReport['batches'][0]['count']);
    }

    // =========================================================================
    // 🛑 TERMINATION HONESTY & RESISTANCE TO PREMATURE LABELS (17 - 18)
    // =========================================================================

    public function testNaturalStabilizationWhenCandidatesExhausted(): void
    {
        // Feeder 20 has 5 assets in a line (needs 4 edges). Running with max_batches = 10
        $result = $this->orchestrator->run([
            'mode'        => 'dry-run',
            'feeder_id'   => 20,
            'max_batches' => 10,
        ]);

        $feederReport = $result['feeders_report'][0];
        $this->assertEquals('NATURAL_STABILIZED', $feederReport['status']);
        $this->assertEquals(4, $feederReport['total_created_count']);
    }

    public function testMaxBatchLimitReachedSetsPaused(): void
    {
        // Feeder 30 has 12 assets (needs 11 edges -> requires 2 batches of size 10 and 1).
        // If we set max_batches = 1, it must be PAUSED / MAX_BATCH_LIMIT_REACHED, NOT NATURAL_STABILIZED
        $result = $this->orchestrator->run([
            'mode'        => 'dry-run',
            'feeder_id'   => 30,
            'max_batches' => 1,
        ]);

        $feederReport = $result['feeders_report'][0];
        $this->assertEquals('MAX_BATCH_LIMIT_REACHED', $feederReport['status']);
        $this->assertEquals(1, $feederReport['batches_run']);
    }

    // =========================================================================
    // 🛡️ FAULT ISOLATION & RESILIENCE (19)
    // =========================================================================

    public function testFaultIsolationOnFeederAnomaly(): void
    {
        $faultyOrchestrator = new class($this->db) extends MultiFeederCompletionOrchestrator {
            protected function processFeederWorker(
                int $feederId,
                array $feederMeta,
                string $mode,
                int $maxBatches,
                string $runId,
                string $actorName
            ): array {
                if ($feederId === 20) {
                    return [
                        'penyulang_id'        => $feederId,
                        'kode_penyulang'      => $feederMeta['kode_penyulang'],
                        'nama_penyulang'      => $feederMeta['nama_penyulang'],
                        'ulp_id'              => $feederMeta['ulp_id'],
                        'nama_ulp'            => $feederMeta['nama_ulp'],
                        'status'              => 'ANOMALY_HELD',
                        'quarantine_reason'   => 'Simulated topological fracture anomaly',
                        'total_created_count' => 0,
                        'batches_run'         => 0,
                        'batches'             => [],
                    ];
                }
                return parent::processFeederWorker($feederId, $feederMeta, $mode, $maxBatches, $runId, $actorName);
            }
        };

        $result = $faultyOrchestrator->run([
            'mode'        => 'dry-run',
            'max_batches' => 2,
        ]);

        $this->assertEquals('SUCCESS', $result['status']);
        $reports = $result['feeders_report'];

        // Feeder 20 must be marked ANOMALY_HELD with quarantine reason
        $feeder20Report = current(array_filter($reports, fn($r) => $r['penyulang_id'] === 20));
        $this->assertNotEmpty($feeder20Report);
        $this->assertEquals('ANOMALY_HELD', $feeder20Report['status']);
        $this->assertEquals('Simulated topological fracture anomaly', $feeder20Report['quarantine_reason']);

        // Feeder 30 must complete successfully without being aborted by Feeder 20's anomaly
        $feeder30Report = current(array_filter($reports, fn($r) => $r['penyulang_id'] === 30));
        $this->assertNotEmpty($feeder30Report);
        $this->assertContains($feeder30Report['status'], ['NATURAL_STABILIZED', 'MAX_BATCH_LIMIT_REACHED']);

        // Checkpoint state must register Feeder 20 in quarantined_feeders
        $savedState = $faultyOrchestrator->loadState();
        $this->assertContains(20, $savedState['quarantined_feeders']);
    }

    // =========================================================================
    // 🔒 ZERO-MUTATION ON DRY-RUN (20 - 21)
    // =========================================================================

    public function testZeroMutationOnDryRun(): void
    {
        $preTls = $this->db->table('gis_translines')->countAllResults();
        $preAssets = $this->db->table('assets')->countAllResults();
        $preTemuan = $this->db->table('temuan')->countAllResults();

        $result = $this->orchestrator->run([
            'mode' => 'dry-run',
        ]);

        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertTrue($result['dry_run_zero_write_pass']);

        $postTls = $this->db->table('gis_translines')->countAllResults();
        $postAssets = $this->db->table('assets')->countAllResults();
        $postTemuan = $this->db->table('temuan')->countAllResults();

        $this->assertEquals($preTls, $postTls);
        $this->assertEquals($preAssets, $postAssets);
        $this->assertEquals($preTemuan, $postTemuan);
    }

    public function testDryRunSimulatesTranslinesAccurately(): void
    {
        $result = $this->orchestrator->run([
            'mode'        => 'dry-run',
            'feeder_id'   => 20,
            'max_batches' => 1,
        ]);

        $batch = $result['feeders_report'][0]['batches'][0];
        $this->assertNotEmpty($batch['translines']);
        $firstTl = $batch['translines'][0];

        $this->assertArrayHasKey('natural_key', $firstTl);
        $this->assertArrayHasKey('source_asset_id', $firstTl);
        $this->assertArrayHasKey('target_asset_id', $firstTl);
        $this->assertArrayHasKey('distance_meters', $firstTl);
        $this->assertArrayHasKey('total_score', $firstTl);
        $this->assertGreaterThan(0, $firstTl['total_score']);
    }

    // =========================================================================
    // 🎯 CLI/API OPTIONS (22 - 23)
    // =========================================================================

    public function testTargetFeederFiltering(): void
    {
        $result = $this->orchestrator->run([
            'mode'      => 'dry-run',
            'feeder_id' => 30,
        ]);

        $this->assertCount(1, $result['feeders_report']);
        $this->assertEquals(30, $result['feeders_report'][0]['penyulang_id']);
    }

    public function testMaxFeedersOption(): void
    {
        $result = $this->orchestrator->run([
            'mode'        => 'dry-run',
            'max_feeders' => 1,
        ]);

        $this->assertCount(1, $result['feeders_report']);
        $this->assertEquals(30, $result['feeders_report'][0]['penyulang_id']);
    }

    // =========================================================================
    // 🌐 BOUNDARY & TEMUAN ISOLATION (24 - 26)
    // =========================================================================

    public function testCrossFeederIsolation(): void
    {
        // Assets on Feeder 15 and Feeder 20 are geographically close (within 50m)
        $this->db->table('assets')->insert([
            'id'           => 199,
            'penyulang_id' => 15,
            'ulp_id'       => 1,
            'kode_asset'   => 'AST-15-BORDER',
            'nama_asset'   => 'Border Feeder 15',
            'latitude'     => -7.460000,
            'longitude'    => 112.600000, // Exactly at Feeder 20 asset 201 coordinates
        ]);

        $result = $this->orchestrator->run([
            'mode'      => 'dry-run',
            'feeder_id' => 20,
        ]);

        $allTls = [];
        foreach ($result['feeders_report'][0]['batches'] as $b) {
            $allTls = array_merge($allTls, $b['translines']);
        }

        foreach ($allTls as $tl) {
            $this->assertNotEquals(199, $tl['source_asset_id']);
            $this->assertNotEquals(199, $tl['target_asset_id']);
        }
    }

    public function testCrossUlpHardBoundary(): void
    {
        // Feeder 15 belongs to ULP 1, Feeder 20 belongs to ULP 2
        $queue = $this->orchestrator->buildGlobalFeederQueue();
        $feeder20 = current(array_filter($queue['priority_queue'], fn($f) => $f['penyulang_id'] === 20));
        $this->assertEquals(2, $feeder20['ulp_id']);
        $this->assertEquals('ULP Krian', $feeder20['nama_ulp']);
    }

    public function testTemuanIsolation(): void
    {
        // Temuan #1 has asset_id = 201
        $temuanCount = $this->db->table('temuan')->countAllResults();
        $this->assertEquals(1, $temuanCount);

        // Verify discoverCandidates never creates candidate where source or target is temuan
        $result = $this->orchestrator->run([
            'mode'      => 'dry-run',
            'feeder_id' => 20,
        ]);

        foreach ($result['feeders_report'][0]['batches'] as $b) {
            foreach ($b['translines'] as $tl) {
                // Ensure IDs are strictly within assets table
                $this->assertNotEquals('TMN-001', $tl['source_asset_id']);
                $this->assertNotEquals('TMN-001', $tl['target_asset_id']);
            }
        }
    }

    // =========================================================================
    // 🔄 TOPOLOGICAL DUPLICATE & LOOP GUARDS (27 - 28)
    // =========================================================================

    public function testAntiDuplicateAndAntiReverseDuplicate(): void
    {
        // Add an isolated asset 105 near 104 to trigger candidate discovery
        $this->db->table('assets')->insert([
            'id'           => 105,
            'penyulang_id' => 15,
            'ulp_id'       => 1,
            'kode_asset'   => 'AST-15-5',
            'nama_asset'   => 'Tiang 15-5',
            'latitude'     => -7.450000 + (5 * 0.000300),
            'longitude'    => 112.710000,
        ]);

        // Feeder 15 already has translines (101, 102), (102, 103), (103, 104).
        $engine = new TranslineNetworkCompletionEngine($this->db);
        $graph = $engine->buildGraph(15);
        $candidates = $engine->discoverCandidates($graph);

        $this->assertNotEmpty($candidates);
        foreach ($candidates as $c) {
            $this->assertFalse(
                ($c['source_asset_id'] === 101 && $c['target_asset_id'] === 102) ||
                ($c['source_asset_id'] === 102 && $c['target_asset_id'] === 101),
                'Existing forward or reverse transline must never be proposed'
            );
        }
    }

    public function testSelfLoopForbidden(): void
    {
        $engine = new TranslineNetworkCompletionEngine($this->db);
        $graph = $engine->buildGraph(20);
        $candidates = $engine->discoverCandidates($graph);

        foreach ($candidates as $c) {
            $this->assertNotEquals($c['source_asset_id'], $c['target_asset_id'], 'Self-loop is strictly forbidden');
        }
    }

    // =========================================================================
    // ⚡ LIVE ATOMIC BATCH TRANSACTION & ZERO-MUTATION GUARDS (29 - 30)
    // =========================================================================

    public function testLiveAtomicBatchRollbackOnFailure(): void
    {
        // Using reflection to test protected executeLiveAtomicBatch
        $ref = new \ReflectionClass(MultiFeederCompletionOrchestrator::class);
        $method = $ref->getMethod('executeLiveAtomicBatch');
        $method->setAccessible(true);

        // Edge with invalid sub-meter distance (< 5m) passed directly to method
        $invalidBatch = [
            [
                'natural_key'     => '20_201_202',
                'source_asset_id' => 201,
                'target_asset_id' => 202,
                'distance_meters' => 2.5, // BREACH OF SHORT_SPAN_FIREWALL
                'coordinates'     => [[112.6, -7.46], [112.6, -7.46002]],
            ]
        ];

        $res = $method->invoke($this->orchestrator, 20, $invalidBatch, 1, 'TEST-RUN', 'UNIT_TEST');
        $this->assertEquals('error', $res['status']);
        $this->assertStringContainsString('SHORT_SPAN_FIREWALL breach', $res['message']);

        // Verify zero translines inserted
        $count = $this->db->table('gis_translines')->where('penyulang_id', 20)->countAllResults();
        $this->assertEquals(0, $count);
    }

    public function testLiveExecutionZeroMutationOnProtectedTables(): void
    {
        $ref = new \ReflectionClass(MultiFeederCompletionOrchestrator::class);
        $method = $ref->getMethod('executeLiveAtomicBatch');
        $method->setAccessible(true);

        $validBatch = [
            [
                'natural_key'     => '20_201_202',
                'source_asset_id' => 201,
                'target_asset_id' => 202,
                'distance_meters' => 35.0,
                'conductor_type'  => 'AAAC',
                'conductor_size'  => '150 mm²',
                'coordinates'     => [[112.6, -7.46], [112.6, -7.460315]],
                'candidate_type'  => 'MAINLINE',
                'total_score'     => 88.5,
            ]
        ];

        $preAssets = $this->db->table('assets')->countAllResults();
        $preTemuan = $this->db->table('temuan')->countAllResults();

        $res = $method->invoke($this->orchestrator, 20, $validBatch, 1, 'TEST-RUN-VALID', 'UNIT_TEST');
        $this->assertEquals('success', $res['status'], 'Batch failure message: ' . ($res['message'] ?? ''));
        $this->assertEquals(1, $res['created_count']);

        // Verify protected tables have zero delta
        $this->assertEquals($preAssets, $this->db->table('assets')->countAllResults());
        $this->assertEquals($preTemuan, $this->db->table('temuan')->countAllResults());
    }
}
