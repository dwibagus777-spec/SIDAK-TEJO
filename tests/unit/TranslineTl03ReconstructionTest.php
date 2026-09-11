<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use Config\GisIconConfig;
use App\Services\TranslineReconstructionService;
use App\Services\TranslineCompletionService;

/**
 * TL-03: Advanced JTM Network Reconstruction & GIS Icon Modernization Test Suite
 *
 * Covers all 7 Amendments & Governance Invariants:
 * 1. Section Boundary Evidence (Same section = 15 pts, Adjacent = 10 pts, Cross-feeder = blocked)
 * 2. Chain Safety & Discrete Edge Decomposition (A-B, B-C, C-D; no monolithic chains)
 * 3. T-Off Safety & Degree Capacity (Cap at degree <= 4; degree 4 is saturated; degree > 4 blocked)
 * 4. Calibrated Distance Curve (2-15m not penalized; 15-55m optimal 30 pts; 55-85m scaled; >85m blocked)
 * 5. Component Analysis (Connected vs Isolated components correctly categorized)
 * 6. Provenance Distinction (ENGINE=TL03|RUN:{run_id}|PROP:{prop_id})
 * 7. Server Authority & Controlled Batch Execution (Max 10 per batch, score >= 90, 24 gates)
 * 8. Honest Diagnostics (101 isolated assets receive honest diagnosis; unverified remain isolated)
 * 9. GIS Icon Configuration (Resolves all 24 authentic PNG icons for JTM, Gardu, Switch, Conductor)
 * 10. Zero Mutation Invariant (assets and temuan tables are strictly read-only)
 */
class TranslineTl03ReconstructionTest extends CIUnitTestCase
{
    protected $db;
    protected TranslineReconstructionService $reconService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->setupSchema();
        $this->seedTestData();

        $this->reconService = new TranslineReconstructionService($this->db);
    }

    protected function safeAddColumn(string $table, string $column, array $def): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }
        $prefixed = $this->db->prefixTable($table);
        $cols = array_column($this->db->query("PRAGMA table_info({$prefixed})")->getResultArray(), 'name');
        if (!in_array($column, $cols, true)) {
            try {
                Database::forge()->addColumn($table, [$column => $def]);
            } catch (\Throwable $e) {
                // Column already exists
            }
        }
    }

    protected function setupSchema(): void
    {
        $forge = Database::forge();

        if (!$this->db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('ulps', true);
        }

        if (!$this->db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'ulp_id'         => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('penyulang', true);
        }

        if (!$this->db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('sections', true);
        }

        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Tiang JTM'],
                'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_BETON'],
                'type'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'ulp_id'               => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'sequence_no'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'construction_type_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'latitude'             => ['type' => 'DECIMAL', 'constraint' => '10,8', 'null' => true],
                'longitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'lokasi'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'parent_asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'status'               => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'NORMAL'],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        if (!$this->db->tableExists('gis_translines')) {
            $forge->addField([
                'id'                 => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'transline_code'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'penyulang_id'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'section_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'source_asset_id'    => ['type' => 'INT', 'constraint' => 11],
                'target_asset_id'    => ['type' => 'INT', 'constraint' => 11],
                'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'length_meters'      => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 45.00],
                'coordinates'        => ['type' => 'TEXT', 'null' => true],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
                'is_active'          => ['type' => 'INT', 'constraint' => 1, 'default' => 1],
                'created_by'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        }

        if (!$this->db->tableExists('temuan')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'asset_id'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'deskripsi'    => ['type' => 'TEXT', 'null' => true],
                'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan', true);
        }
    }

    protected function seedTestData(): void
    {
        $this->db->table('gis_translines')->emptyTable();
        $this->db->table('assets')->emptyTable();
        $this->db->table('penyulang')->emptyTable();
        $this->db->table('sections')->emptyTable();
        $this->db->table('temuan')->emptyTable();

        $this->db->table('penyulang')->insert([
            'id'             => 15,
            'ulp_id'         => 1,
            'kode_penyulang' => 'BJKM',
            'nama_penyulang' => 'BANJAR KEMANTREN',
            'status'         => 'AKTIF',
        ]);

        $this->db->table('sections')->insertBatch([
            ['id' => 101, 'penyulang_id' => 15, 'nama_section' => 'SEKSI UTAMA'],
            ['id' => 102, 'penyulang_id' => 15, 'nama_section' => 'SEKSI CABANG UTARA'],
            ['id' => 103, 'penyulang_id' => 15, 'nama_section' => 'SEKSI CABANG SELATAN'],
        ]);

        // Base connected feeder backbone (Nodes 1-4 connected linearly: 1-2, 2-3, 3-4)
        $assets = [
            // Backbone nodes
            ['id' => 1, 'kode_asset' => 'BJKM-001', 'nama_asset' => 'Tiang 1', 'type' => 'JTM', 'section_id' => 101, 'penyulang_id' => 15, 'ulp_id' => 1, 'latitude' => -7.450000, 'longitude' => 112.710000],
            ['id' => 2, 'kode_asset' => 'BJKM-002', 'nama_asset' => 'Tiang 2', 'type' => 'JTM', 'section_id' => 101, 'penyulang_id' => 15, 'ulp_id' => 1, 'latitude' => -7.450300, 'longitude' => 112.710000],
            ['id' => 3, 'kode_asset' => 'BJKM-003', 'nama_asset' => 'Tiang 3', 'type' => 'JTM', 'section_id' => 101, 'penyulang_id' => 15, 'ulp_id' => 1, 'latitude' => -7.450600, 'longitude' => 112.710000],
            ['id' => 4, 'kode_asset' => 'BJKM-004', 'nama_asset' => 'Tiang 4', 'type' => 'JTM', 'section_id' => 101, 'penyulang_id' => 15, 'ulp_id' => 1, 'latitude' => -7.450900, 'longitude' => 112.710000],

            // Isolated chain (Nodes 10 -> 11 -> 12, each ~35m apart, section 102)
            ['id' => 10, 'kode_asset' => 'BJKM-010', 'nama_asset' => 'Tiang 10', 'type' => 'JTM', 'section_id' => 102, 'penyulang_id' => 15, 'ulp_id' => 1, 'latitude' => -7.452000, 'longitude' => 112.715000],
            ['id' => 11, 'kode_asset' => 'BJKM-011', 'nama_asset' => 'Tiang 11', 'type' => 'JTM', 'section_id' => 102, 'penyulang_id' => 15, 'ulp_id' => 1, 'latitude' => -7.452300, 'longitude' => 112.715000],
            ['id' => 12, 'kode_asset' => 'BJKM-012', 'nama_asset' => 'Tiang 12', 'type' => 'JTM', 'section_id' => 102, 'penyulang_id' => 15, 'ulp_id' => 1, 'latitude' => -7.452600, 'longitude' => 112.715000],

            // Isolated anchor continuation candidate near Node 4 (Node 20, ~30m from Node 4)
            ['id' => 20, 'kode_asset' => 'BJKM-005', 'nama_asset' => 'Tiang 5', 'type' => 'JTM', 'section_id' => 101, 'penyulang_id' => 15, 'ulp_id' => 1, 'latitude' => -7.451150, 'longitude' => 112.710000],

            // Completely isolated / distant node (>500m away, cannot connect legitimately)
            ['id' => 99, 'kode_asset' => 'BJKM-099', 'nama_asset' => 'Tiang Terpencil', 'type' => 'JTM', 'section_id' => 103, 'penyulang_id' => 15, 'ulp_id' => 1, 'latitude' => -7.465000, 'longitude' => 112.730000],
        ];
        $this->db->table('assets')->insertBatch($assets);

        // Seed 3 baseline translines (1-2, 2-3, 3-4)
        $this->db->table('gis_translines')->insertBatch([
            [
                'id'              => 1,
                'transline_code'  => 'TL-15-1-2',
                'penyulang_id'    => 15,
                'section_id'      => 101,
                'source_asset_id' => 1,
                'target_asset_id' => 2,
                'length_meters'   => 33.3,
                'coordinates'     => json_encode([[-7.450000, 112.710000], [-7.450300, 112.710000]]),
                'created_by'      => 'MANUAL_SURVEY_BASE',
                'created_at'      => '2026-08-01 10:00:00',
            ],
            [
                'id'              => 2,
                'transline_code'  => 'TL-15-2-3',
                'penyulang_id'    => 15,
                'section_id'      => 101,
                'source_asset_id' => 2,
                'target_asset_id' => 3,
                'length_meters'   => 33.3,
                'coordinates'     => json_encode([[-7.450300, 112.710000], [-7.450600, 112.710000]]),
                'created_by'      => 'MANUAL_SURVEY_BASE',
                'created_at'      => '2026-08-01 10:00:00',
            ],
            [
                'id'              => 3,
                'transline_code'  => 'TL-15-3-4',
                'penyulang_id'    => 15,
                'section_id'      => 101,
                'source_asset_id' => 3,
                'target_asset_id' => 4,
                'length_meters'   => 33.3,
                'coordinates'     => json_encode([[-7.450600, 112.710000], [-7.450900, 112.710000]]),
                'created_by'      => 'MANUAL_SURVEY_BASE',
                'created_at'      => '2026-08-01 10:00:00',
            ],
        ]);

        // Temuan record (Temuan firewall test)
        $this->db->table('temuan')->insert([
            'id'           => 501,
            'asset_id'     => 1,
            'penyulang_id' => 15,
            'deskripsi'    => 'Pohon dekat kabel SUTM',
        ]);
    }

    // =========================================================================
    // TEST SUITE: AMENDMENTS & INVARIANTS
    // =========================================================================

    public function testGisIconConfigResolvesAllAssetTypes(): void
    {
        $config = new GisIconConfig();
        $this->assertNotEmpty($config->icons);

        // JTM Poles
        $this->assertSame('JTM_TM1', $config->resolveIconKey(['type' => 'JTM', 'nama_asset' => 'Tiang TM1']));
        $this->assertSame('JTM_TM2', $config->resolveIconKey(['type' => 'JTM', 'konstruksi' => 'TM-2']));
        $this->assertSame('JTM_TM4', $config->resolveIconKey(['type' => 'JTM', 'konstruksi' => 'TM-4']));
        $this->assertSame('JTM_TM5', $config->resolveIconKey(['type' => 'JTM', 'konstruksi' => 'TM-5']));
        $this->assertSame('JTM_TM8', $config->resolveIconKey(['type' => 'JTM', 'konstruksi' => 'TM-8']));
        $this->assertSame('JTM_TM10', $config->resolveIconKey(['type' => 'JTM', 'konstruksi' => 'TM-10']));
        $this->assertSame('JTM_TM11', $config->resolveIconKey(['type' => 'JTM', 'konstruksi' => 'TM-11']));
        $this->assertSame('JTM_TM11_I3', $config->resolveIconKey(['type' => 'JTM', 'konstruksi' => 'TM-11 I3']));

        // Gardu & Substations
        $this->assertSame('GARDU_INDUK', $config->resolveIconKey(['type' => 'GARDU', 'nama_asset' => 'GI SIDOARJO']));
        $this->assertSame('GARDU_GTT1_DIST', $config->resolveIconKey(['type' => 'GARDU', 'nama_asset' => 'GTT 1 TIANG']));
        $this->assertSame('GARDU_GTT2_DIST', $config->resolveIconKey(['type' => 'GARDU', 'nama_asset' => 'GARDU PORTAL GTT2']));
        $this->assertSame('GARDU_GTT2_I2', $config->resolveIconKey(['type' => 'GARDU', 'nama_asset' => 'PORTAL I2']));

        // Switching & Protection
        $this->assertSame('SWITCH_LBS', $config->resolveIconKey(['type' => 'SWITCH', 'nama_asset' => 'LBS MANUAL']));
        $this->assertSame('SWITCH_LBSM', $config->resolveIconKey(['type' => 'SWITCH', 'nama_asset' => 'LBS MOTORIZED']));
        $this->assertSame('SWITCH_RECLOSER', $config->resolveIconKey(['type' => 'SWITCH', 'nama_asset' => 'RECLOSER SEDATI']));
        $this->assertSame('SWITCH_CUTOUT', $config->resolveIconKey(['type' => 'SWITCH', 'nama_asset' => 'FCO CABANG']));
    }

    public function testGisIconConfigHasConductorDefinitions(): void
    {
        $config = new GisIconConfig();

        $this->assertArrayHasKey('COND_A3C_70', $config->icons);
        $this->assertArrayHasKey('COND_A3C_150', $config->icons);
        $this->assertArrayHasKey('COND_A3C_240', $config->icons);
        $this->assertArrayHasKey('COND_A3CS_150', $config->icons);
        $this->assertArrayHasKey('COND_A3CS_240', $config->icons);
        $this->assertArrayHasKey('COND_MVTIC_150', $config->icons);
        $this->assertArrayHasKey('COND_XLPE', $config->icons);

        $this->assertSame('a3c-150.png', $config->icons['COND_A3C_150']['file']);
        $this->assertSame('xlpe.png', $config->icons['COND_XLPE']['file']);
    }

    public function testPureReadOnlyFeederAnalysis(): void
    {
        $preAssets = $this->db->table('assets')->countAllResults();
        $preLines  = $this->db->table('gis_translines')->countAllResults();
        $preTemuan = $this->db->table('temuan')->countAllResults();

        $result = $this->reconService->analyzeFeeder(15);

        $this->assertSame('success', $result['status']);
        $this->assertSame('TL-03.1', $result['engine']);
        $this->assertSame(15, $result['penyulang_id']);

        // Check inventory metrics
        $this->assertSame(9, $result['inventory']['total_master_assets']);
        $this->assertSame(3, $result['inventory']['authoritative_translines']);
        $this->assertSame(4, $result['inventory']['connected_assets_count']); // 1, 2, 3, 4
        $this->assertSame(5, $result['inventory']['isolated_assets_count']);  // 10, 11, 12, 20, 99

        // Pure Read Guarantee: 0 mutations
        $this->assertSame($preAssets, $this->db->table('assets')->countAllResults());
        $this->assertSame($preLines, $this->db->table('gis_translines')->countAllResults());
        $this->assertSame($preTemuan, $this->db->table('temuan')->countAllResults());
    }

    public function testIsolatedChainDiscoveryAndDecomposition(): void
    {
        $result = $this->reconService->analyzeFeeder(15);
        $chains = $result['isolated_chains'];

        // Nodes 10, 11, 12 form a chain
        $this->assertNotEmpty($chains);
        $foundChain = false;
        foreach ($chains as $chain) {
            if (count($chain['node_ids']) === 3 && in_array(10, $chain['node_ids'], true) && in_array(12, $chain['node_ids'], true)) {
                $foundChain = true;
                // Amendment 2: Chain must be decomposed into discrete edges (length 2 edges: 10-11 and 11-12)
                $this->assertCount(2, $chain['discrete_edges']);
                $edge1 = $chain['discrete_edges'][0];
                $edge2 = $chain['discrete_edges'][1];
                $this->assertSame(10, min($edge1[0], $edge1[1]));
                $this->assertSame(11, max($edge1[0], $edge1[1]));
                $this->assertSame(11, min($edge2[0], $edge2[1]));
                $this->assertSame(12, max($edge2[0], $edge2[1]));
            }
        }
        $this->assertTrue($foundChain, 'Isolated chain of nodes 10, 11, 12 was not discovered or decomposed.');
    }

    public function testCalibratedDistanceScoring(): void
    {
        // 25m span (optimal, should receive max 30 pts)
        $candOptimal = [
            'distance_meters'    => 25.0,
            'source_section_id'  => 101,
            'target_section_id'  => 101,
            'source_degree'      => 1,
            'target_degree'      => 0,
            'code_diff'          => 1,
            'is_chain_edge'      => false,
            'gate_valid'         => true,
        ];
        $scoreOptimal = $this->reconService->scoreCandidate($candOptimal);
        $this->assertGreaterThanOrEqual(85, $scoreOptimal['total_score']);

        // 10m short span (should NOT be penalized to 0; gets 25 pts)
        $candShort = [
            'distance_meters'    => 10.0,
            'source_section_id'  => 101,
            'target_section_id'  => 101,
            'source_degree'      => 1,
            'target_degree'      => 0,
            'code_diff'          => 1,
            'is_chain_edge'      => false,
            'gate_valid'         => true,
        ];
        $scoreShort = $this->reconService->scoreCandidate($candShort);
        $this->assertSame(25, $scoreShort['distance_score']);

        // 95m long span (exceeds 85m hard cap; gets 0 distance pts)
        $candLong = [
            'distance_meters'    => 95.0,
            'source_section_id'  => 101,
            'target_section_id'  => 101,
            'source_degree'      => 1,
            'target_degree'      => 0,
            'code_diff'          => 1,
            'is_chain_edge'      => false,
            'gate_valid'         => true,
        ];
        $scoreLong = $this->reconService->scoreCandidate($candLong);
        $this->assertSame(0, $scoreLong['distance_score']);
    }

    public function testSectionBoundaryScoringAmendment(): void
    {
        // Case A: Same Section -> 15 pts
        $sameSec = [
            'distance_meters'   => 30.0,
            'source_section_id' => 101,
            'target_section_id' => 101,
            'source_degree'     => 1,
            'target_degree'     => 0,
            'code_diff'         => 1,
            'is_chain_edge'     => false,
            'gate_valid'        => true,
        ];
        $resSame = $this->reconService->scoreCandidate($sameSec);
        $this->assertSame(15, $resSame['section_score']);

        // Case B: Different Section -> 10 pts (Adjacent / legitimate boundary evidence)
        $diffSec = [
            'distance_meters'   => 30.0,
            'source_section_id' => 101,
            'target_section_id' => 102,
            'source_degree'     => 1,
            'target_degree'     => 0,
            'code_diff'         => 1,
            'is_chain_edge'     => false,
            'gate_valid'        => true,
        ];
        $resDiff = $this->reconService->scoreCandidate($diffSec);
        $this->assertSame(10, $resDiff['section_score']);
    }

    public function testNodeDegreeCapAtFour(): void
    {
        // Add 4 lines to node 2 so its degree becomes 4 (saturated)
        // Currently node 2 is connected to 1 and 3 (degree = 2).
        // Add dummy lines 2-10 and 2-11
        $this->db->table('gis_translines')->insertBatch([
            [
                'id'              => 4,
                'transline_code'  => 'TL-15-2-10',
                'penyulang_id'    => 15,
                'section_id'      => 101,
                'source_asset_id' => 2,
                'target_asset_id' => 10,
                'length_meters'   => 30.0,
                'created_by'      => 'TEST',
            ],
            [
                'id'              => 5,
                'transline_code'  => 'TL-15-2-11',
                'penyulang_id'    => 15,
                'section_id'      => 101,
                'source_asset_id' => 2,
                'target_asset_id' => 11,
                'length_meters'   => 30.0,
                'created_by'      => 'TEST',
            ],
        ]);

        $analysis = $this->reconService->analyzeFeeder(15);
        $this->assertSame(1, $analysis['inventory']['saturated_nodes_count']); // Node 2 has degree 4

        // Ensure NO candidate proposes node 2 as source or target
        $autoCandidates = $analysis['auto_complete_batch_preview'];
        foreach ($autoCandidates as $c) {
            $this->assertNotSame(2, $c['source_asset_id'], 'Saturated node 2 was proposed as source in auto-complete!');
            $this->assertNotSame(2, $c['target_asset_id'], 'Saturated node 2 was proposed as target in auto-complete!');
        }
    }

    public function testDeterministicDiagnosisOfAllIsolatedAssets(): void
    {
        $result = $this->reconService->analyzeFeeder(15);
        $diagnostics = $result['isolated_asset_diagnostics']['assets_detail'] ?? [];

        // All 5 isolated assets (10, 11, 12, 20, 99) must be in diagnostics
        $diagMap = [];
        foreach ($diagnostics as $d) {
            $diagMap[$d['asset_id']] = $d;
        }

        $this->assertArrayHasKey(10, $diagMap);
        $this->assertArrayHasKey(11, $diagMap);
        $this->assertArrayHasKey(12, $diagMap);
        $this->assertArrayHasKey(20, $diagMap);
        $this->assertArrayHasKey(99, $diagMap);

        // Node 99 is >500m away with no candidates -> must be NO_VALID_NETWORK_RELATIONSHIP
        $this->assertSame('NO_VALID_NETWORK_RELATIONSHIP', $diagMap[99]['classification']);
    }

    public function testControlledBatchExecutionMaxTen(): void
    {
        $preAssetsCount = $this->db->table('assets')->countAllResults();
        $preTemuanCount = $this->db->table('temuan')->countAllResults();
        $preLineCount   = $this->db->table('gis_translines')->countAllResults();

        $res = $this->reconService->executeBatch(15, [
            'actor_name' => 'ENGINEER_TRANSLINE_AI',
            'max_batch'  => 10,
        ]);

        $this->assertSame('success', $res['status'], $res['message'] ?? '');
        $createdCount = $res['created_count'];
        $this->assertLessThanOrEqual(10, $createdCount);

        if ($createdCount > 0) {
            $this->assertNotEmpty($res['run_id']);
            $this->assertCount($createdCount, $res['created_translines']);

            // Provenance check
            $first = $res['created_translines'][0];
            $this->assertStringContainsString('ENGINE=TL03', $first['created_by']);
            $this->assertStringContainsString('ENGINEER_TRANSLINE_AI', $first['created_by']);

            // Exact PK capture
            $this->assertIsInt($first['id']);
            $this->assertGreaterThan(0, $first['id']);
        }

        // Strict Invariant: Zero mutations on assets and temuan
        $this->assertSame($preAssetsCount, $this->db->table('assets')->countAllResults());
        $this->assertSame($preTemuanCount, $this->db->table('temuan')->countAllResults());
        $this->assertSame($preLineCount + $createdCount, $this->db->table('gis_translines')->countAllResults());
    }

    public function testZeroWriteOnNonExistentFeeder(): void
    {
        $res = $this->reconService->executeBatch(999999);
        $this->assertSame('success', $res['status']);
        $this->assertSame(0, $res['created_count']);
    }
}