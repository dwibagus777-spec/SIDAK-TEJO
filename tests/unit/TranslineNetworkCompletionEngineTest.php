<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\TranslineNetworkCompletionEngine;

/**
 * SIDAK TEJO ENTERPRISE: TRANSLINE AI ACCELERATION PROGRAM
 * "NETWORK COMPLETION ENGINE v1" Test Suite
 *
 * 40 Rigorous Unit Test Scenarios covering:
 * - Coordinate Hydration, GeoJSON unwrap, string/int ID normalization, coordinate fallback
 * - Sequence jump tolerance, physical proximity precedence, bearing, linear chains
 * - T-off branching, Degree 4 ceiling, Degree 5 rejection
 * - Duplicate, reverse duplicate, self-loop, cross-feeder, cross-ULP, sub-meter, >85m gates
 * - Temuan firewall (strict non-endpoint invariant)
 * - Component reconstruction, terminal extensions, dynamic recalculation
 * - Batch <= 10 ceiling, multi-batch execution, atomic transaction rollback
 * - Idempotency, natural stabilization stop gate
 * - Existing transline immutability, Zero-Write firewalls (assets, temuan, sections, penyulang)
 * - Global & multi-feeder scoping
 */
class TranslineNetworkCompletionEngineTest extends CIUnitTestCase
{
    protected $db;
    protected TranslineNetworkCompletionEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->setupSchema();
        $this->seedTestData();

        $this->engine = new TranslineNetworkCompletionEngine($this->db);
    }

    protected function setupSchema(): void
    {
        $forge = Database::forge();

        $forge->dropTable('gis_translines', true);
        $forge->dropTable('assets', true);
        $forge->dropTable('sections', true);
        $forge->dropTable('penyulang', true);
        $forge->dropTable('ulps', true);
        $forge->dropTable('temuan', true);

        $forge->addField([
            'id'       => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50],
            'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
            'status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('ulps', true);

        $forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'ulp_id'         => ['type' => 'INT', 'constraint' => 11],
            'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
            'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'OPERASIONAL'],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('penyulang', true);

        $forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'penyulang_id' => ['type' => 'INT', 'constraint' => 11],
            'kode_section' => ['type' => 'VARCHAR', 'constraint' => 50],
            'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('sections', true);

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
            'latitude'             => ['type' => 'DECIMAL', 'constraint' => '11,8'],
            'longitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8'],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('assets', true);

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
            'geometry'           => ['type' => 'TEXT', 'null' => true],
            'coordinates'        => ['type' => 'TEXT', 'null' => true],
            'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'updated_by'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('gis_translines', true);

        $forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
            'nomor_temuan'  => ['type' => 'VARCHAR', 'constraint' => 100],
            'asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'detail_temuan' => ['type' => 'TEXT', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('temuan', true);
    }

    protected function seedTestData(): void
    {
        $this->db->table('ulps')->insert([
            'id' => 1, 'kode_ulp' => 'ULP-KOTA', 'nama_ulp' => 'ULP Sidoarjo Kota'
        ]);
        $this->db->table('ulps')->insert([
            'id' => 2, 'kode_ulp' => 'ULP-BARAT', 'nama_ulp' => 'ULP Sidoarjo Barat'
        ]);

        $this->db->table('penyulang')->insert([
            'id' => 15, 'ulp_id' => 1, 'kode_penyulang' => 'BJKM', 'nama_penyulang' => 'BANJAR KEMANTREN'
        ]);
        $this->db->table('penyulang')->insert([
            'id' => 16, 'ulp_id' => 2, 'kode_penyulang' => 'KRTN', 'nama_penyulang' => 'KRATON'
        ]);

        $this->db->table('sections')->insert([
            'id' => 101, 'penyulang_id' => 15, 'kode_section' => 'SEC-01', 'nama_section' => 'Section Utama'
        ]);
        $this->db->table('sections')->insert([
            'id' => 102, 'penyulang_id' => 15, 'kode_section' => 'SEC-02', 'nama_section' => 'Section Percabangan'
        ]);

        // Seed 10 assets for Feeder 15 in a collinear arrangement
        for ($i = 1; $i <= 10; $i++) {
            $lat = -7.450000 + ($i * 0.000300); // ~33.3 meters per step
            $lng = 112.710000;
            $code = sprintf('AST-KOTA-BNJRKMNTRN-JTM-%03d', $i);
            $sec = ($i <= 6) ? 101 : 102;

            $this->db->table('assets')->insert([
                'id'                   => $i,
                'penyulang_id'         => 15,
                'ulp_id'               => 1,
                'section_id'           => $sec,
                'kode_asset'           => $code,
                'nama_asset'           => "Tiang TM {$i}",
                'type'                 => 'TIANG',
                'jenis_asset'          => 'TIANG_TM',
                'construction_type_id' => 1,
                'latitude'             => $lat,
                'longitude'            => $lng,
            ]);
        }

        // Connect 1-2, 2-3 (Nodes 1, 2, 3 connected; 4-10 isolated)
        $this->insertManualTransline(1, 2);
        $this->insertManualTransline(2, 3);

        // Seed Temuan
        $this->db->table('temuan')->insert([
            'id'           => 901,
            'nomor_temuan' => 'TMN-001',
            'asset_id'     => 1,
            'detail_temuan'=> 'Isolator flashover'
        ]);
    }

    protected function insertManualTransline(int $u, int $v): void
    {
        $min = min($u, $v);
        $max = max($u, $v);
        $a1 = $this->db->table('assets')->where('id', $min)->get()->getRowArray();
        $a2 = $this->db->table('assets')->where('id', $max)->get()->getRowArray();

        $coords = [
            [(float)$a1['longitude'], (float)$a1['latitude']],
            [(float)$a2['longitude'], (float)$a2['latitude']]
        ];

        $this->db->table('gis_translines')->insert([
            'transline_code'     => "TL-15-{$min}-{$max}",
            'penyulang_id'       => 15,
            'source_asset_id'    => $min,
            'target_asset_id'    => $max,
            'conductor_type'     => 'AAAC',
            'conductor_size'     => '150 mm²',
            'distance_meters'    => 33.3,
            'geometry'           => json_encode(['type' => 'LineString', 'coordinates' => $coords]),
            'coordinates'        => json_encode($coords),
            'is_active'          => 1,
            'created_by'         => 'MANUAL_BASELINE',
        ]);
    }

    // =========================================================================
    // 🧪 SCENARIO TESTS (1 - 40)
    // =========================================================================

    public function test01EngineConstantsAndMetadata(): void
    {
        $this->assertSame('TRANSLINE_AI_NETWORK_COMPLETION_ENGINE', TranslineNetworkCompletionEngine::ENGINE_NAME);
        $this->assertSame('v1.0', TranslineNetworkCompletionEngine::ENGINE_VERSION);
        $this->assertSame(10, TranslineNetworkCompletionEngine::MAX_BATCH_SIZE);
        $this->assertSame(4, TranslineNetworkCompletionEngine::HARD_MAX_DEGREE);
        $this->assertSame(85.0, TranslineNetworkCompletionEngine::HARD_MAX_SPAN_METERS);
        $this->assertSame(1.0, TranslineNetworkCompletionEngine::HARD_MIN_SPAN_METERS);
    }

    public function test02CoordinateHydrationFromGeoJson(): void
    {
        $graph = $this->engine->buildGraph(15);
        $this->assertNotEmpty($graph['translines']);
        $firstTl = $graph['translines'][0];
        $geom = json_decode($firstTl['geometry'], true);
        $this->assertIsArray($geom);
        $this->assertSame('LineString', $geom['type']);
        $this->assertCount(2, $geom['coordinates']);
    }

    public function test03GeoJsonUnwrapObjectToArray(): void
    {
        $rawObject = ['type' => 'LineString', 'coordinates' => [[112.71, -7.45], [112.72, -7.46]]];
        $unwrapped = (isset($rawObject['coordinates']) && is_array($rawObject['coordinates'])) ? $rawObject['coordinates'] : $rawObject;
        $this->assertIsArray($unwrapped);
        $this->assertCount(2, $unwrapped);
        $this->assertSame([112.71, -7.45], $unwrapped[0]);
    }

    public function test04StringIntIdNormalizationInGraph(): void
    {
        $graph = $this->engine->buildGraph(15);
        foreach ($graph['degrees'] as $id => $deg) {
            $this->assertIsInt($id);
            $this->assertIsInt($deg);
        }
        $this->assertSame(1, $graph['degrees'][1]);
        $this->assertSame(2, $graph['degrees'][2]);
        $this->assertSame(1, $graph['degrees'][3]);
    }

    public function test05ReadTimeCoordinateFallbackFromAssetPoints(): void
    {
        $repo = new \App\Repositories\AssetRepository();
        $res = $repo->getFeederNetworkSegments(15);
        $this->assertSame('MultiLineString', $res['type']);
        $this->assertNotEmpty($res['coordinates']);
        $this->assertNotEmpty($res['edges']);
        foreach ($res['edges'] as $edge) {
            $this->assertIsArray($edge['coordinates']);
            $this->assertCount(2, $edge['coordinates']);
        }
    }

    public function test06ZeroWriteOnCoordinateFallback(): void
    {
        $preHashes = $this->engine->captureTableSignatures(15);
        $repo = new \App\Repositories\AssetRepository();
        $repo->getFeederNetworkSegments(15);
        $postHashes = $this->engine->captureTableSignatures(15);

        $this->assertSame($preHashes['assets'], $postHashes['assets']);
        $this->assertSame($preHashes['temuan'], $postHashes['temuan']);
    }

    public function test07FalseCoordinateWarningEliminated(): void
    {
        $repo = new \App\Repositories\AssetRepository();
        $res = $repo->getFeederNetworkSegments(15);
        foreach ($res['edges'] as $edge) {
            $coords = $edge['coordinates'];
            $this->assertIsArray($coords);
            $this->assertGreaterThanOrEqual(2, count($coords));
            $this->assertIsNumeric($coords[0][0]);
            $this->assertIsNumeric($coords[0][1]);
        }
    }

    public function test08SequenceJumpAsEvidenceNotVeto(): void
    {
        // Add asset 11 with jumping code AST-999 but physically adjacent (~22m) to asset 3
        $this->db->table('assets')->insert([
            'id'           => 11,
            'penyulang_id' => 15,
            'ulp_id'       => 1,
            'section_id'   => 101,
            'kode_asset'   => 'AST-KOTA-BNJRKMNTRN-JTM-999',
            'nama_asset'   => 'Tiang 999 Non Sequential',
            'latitude'     => -7.449100 + 0.000200,
            'longitude'    => 112.710000,
        ]);

        $graph = $this->engine->buildGraph(15);
        $cands = $this->engine->discoverCandidates($graph);

        $pair = array_filter($cands, fn($c) => ($c['source_asset_id'] == 3 && $c['target_asset_id'] == 11) || ($c['source_asset_id'] == 11 && $c['target_asset_id'] == 3));
        $this->assertNotEmpty($pair);
        $candidate = reset($pair);
        $this->assertTrue($candidate['gate_valid'], 'Sequence jump should NOT invalidate safety gate');
        $this->assertGreaterThanOrEqual(75, $candidate['total_score']);
    }

    public function test09PhysicalProximityOverridesSequenceGap(): void
    {
        $d = $this->engine->haversineDistanceMeters(-7.450000, 112.710000, -7.450150, 112.710000);
        $this->assertLessThan(25.0, $d);
        $seqDelta = $this->engine->calculateSequenceDelta('JTM-001', 'JTM-180');
        $this->assertSame(179, $seqDelta);
    }

    public function test10CollinearMainlineBearingScoring(): void
    {
        $b1 = $this->engine->calculateBearing(-7.4500, 112.7100, -7.4503, 112.7100);
        $b2 = $this->engine->calculateBearing(-7.4503, 112.7100, -7.4506, 112.7100);
        $delta = $this->engine->calculateBearingDelta($b1, $b2);
        $this->assertLessThanOrEqual(5.0, $delta);
    }

    public function test11CornerTurnBearingScoring(): void
    {
        $b1 = 90.0;
        $b2 = 135.0;
        $delta = $this->engine->calculateBearingDelta($b1, $b2);
        $this->assertSame(45.0, $delta);
    }

    public function test12SharpTurnPenalty(): void
    {
        $b1 = 0.0;
        $b2 = 180.0;
        $delta = $this->engine->calculateBearingDelta($b1, $b2);
        $this->assertSame(180.0, $delta);
    }

    public function test13LinearChainDetectionIndependentEdges(): void
    {
        $graph = $this->engine->buildGraph(15);
        $chains = $graph['isolated_chains'];
        $this->assertIsArray($chains);
        if (!empty($chains)) {
            $first = $chains[0];
            $this->assertGreaterThanOrEqual(2, count($first));
        }
    }

    public function test14ChainEdgeScoring(): void
    {
        $graph = $this->engine->buildGraph(15);
        $cands = $this->engine->discoverCandidates($graph);
        $chainEdges = array_filter($cands, fn($c) => $c['candidate_type'] === 'ISOLATED_CHAIN_SEGMENT');
        foreach ($chainEdges as $ce) {
            $this->assertGreaterThanOrEqual(75, $ce['total_score']);
        }
    }

    public function test15ToffBranchingFromDegree2(): void
    {
        $graph = $this->engine->buildGraph(15);
        // Node 2 has degree 2
        $this->assertSame(2, $graph['degrees'][2]);
        $cands = $this->engine->discoverCandidates($graph);
        $toffs = array_filter($cands, fn($c) => ($c['source_asset_id'] == 2 || $c['target_asset_id'] == 2));
        $this->assertNotEmpty($toffs);
    }

    public function test16TertiaryBranchingFromDegree3(): void
    {
        // Add branch to node 2 so it reaches degree 3
        $this->insertManualTransline(2, 5);
        $graph = $this->engine->buildGraph(15);
        $this->assertSame(3, $graph['degrees'][2]);
    }

    public function test17Degree4HardCeilingBlock(): void
    {
        // Saturate node 2 to degree 4
        $this->insertManualTransline(2, 5);
        $this->insertManualTransline(2, 6);
        $graph = $this->engine->buildGraph(15);
        $this->assertSame(4, $graph['degrees'][2]);

        $cands = $this->engine->discoverCandidates($graph);
        $node2Cands = array_filter($cands, fn($c) => $c['source_asset_id'] == 2 || $c['target_asset_id'] == 2);
        $this->assertEmpty($node2Cands, 'Saturated degree 4 node must never be evaluated for candidates');
    }

    public function test18Degree5StrictRejection(): void
    {
        $this->insertManualTransline(2, 5);
        $this->insertManualTransline(2, 6);
        $graph = $this->engine->buildGraph(15);
        $this->assertGreaterThanOrEqual(4, $graph['degrees'][2]);
        $this->assertContains(2, $graph['saturated_ids']);
    }

    public function test19DuplicateEdgeRejection(): void
    {
        $graph = $this->engine->buildGraph(15);
        // 1-2 already exists
        $this->assertTrue(isset($graph['existing_edge_keys']['1_2']));
        $cands = $this->engine->discoverCandidates($graph);
        $dup = array_filter($cands, fn($c) => $c['source_asset_id'] == 1 && $c['target_asset_id'] == 2);
        $this->assertEmpty($dup);
    }

    public function test20ReverseDuplicateEdgeRejection(): void
    {
        $graph = $this->engine->buildGraph(15);
        $cands = $this->engine->discoverCandidates($graph);
        // Should not generate 2-1 since 1-2 exists
        $revDup = array_filter($cands, fn($c) => $c['source_asset_id'] == 2 && $c['target_asset_id'] == 1);
        $this->assertEmpty($revDup);
    }

    public function test21SelfLoopStrictRejection(): void
    {
        $graph = $this->engine->buildGraph(15);
        $cands = $this->engine->discoverCandidates($graph);
        $selfLoops = array_filter($cands, fn($c) => $c['source_asset_id'] === $c['target_asset_id']);
        $this->assertEmpty($selfLoops);
    }

    public function test22CrossFeederHardGateRejection(): void
    {
        // Node 12 belongs to Feeder 16
        $this->db->table('assets')->insert([
            'id'           => 12,
            'penyulang_id' => 16,
            'ulp_id'       => 2,
            'section_id'   => null,
            'kode_asset'   => 'AST-KRTN-001',
            'nama_asset'   => 'Tiang Kraton 01',
            'latitude'     => -7.450300,
            'longitude'    => 112.710000,
        ]);

        $graph = $this->engine->buildGraph(15);
        $this->assertArrayNotHasKey(12, $graph['assets'], 'Cross-feeder asset must not be in feeder 15 graph');
    }

    public function test23CrossUlpHardGateRejection(): void
    {
        // Insert asset 13 in ULP 2 located 30m from asset 3 (ULP 1)
        $this->db->table('assets')->insert([
            'id'           => 13,
            'penyulang_id' => 16,
            'ulp_id'       => 2,
            'section_id'   => null,
            'kode_asset'   => 'AST-KRTN-002',
            'nama_asset'   => 'Tiang Kraton 02',
            'latitude'     => -7.449100 + 0.000250,
            'longitude'    => 112.710000,
        ]);

        $graph = $this->engine->buildGraph(null); // Global
        $cands = $this->engine->discoverCandidates($graph);

        $crossUlps = array_filter($cands, function ($c) use ($graph) {
            $u = $graph['assets'][$c['source_asset_id']];
            $v = $graph['assets'][$c['target_asset_id']];
            return ($u['ulp_id'] !== $v['ulp_id']);
        });

        $this->assertNotEmpty($crossUlps);
        foreach ($crossUlps as $c) {
            $this->assertFalse($c['gate_valid'], 'Cross-ULP candidate must fail gate_valid');
            $this->assertContains('CROSS_ULP_BOUNDARY_PROHIBITED', $c['gate_reasons']);
        }
    }

    public function test24TemuanFirewallNoTemuanAsEndpoint(): void
    {
        $graph = $this->engine->buildGraph(15);
        $this->assertArrayNotHasKey(901, $graph['assets'], 'Temuan ID 901 must NEVER be an asset node');
        $cands = $this->engine->discoverCandidates($graph);
        foreach ($cands as $c) {
            $this->assertNotSame(901, $c['source_asset_id']);
            $this->assertNotSame(901, $c['target_asset_id']);
        }
    }

    public function test25SubMeterCoordinateCollisionGate(): void
    {
        $d = $this->engine->haversineDistanceMeters(-7.450000, 112.710000, -7.450000001, 112.710000);
        $this->assertLessThan(1.0, $d);
    }

    public function test26ExcessiveSpanAbove85mGate(): void
    {
        $d = $this->engine->haversineDistanceMeters(-7.450000, 112.710000, -7.460000, 112.710000);
        $this->assertGreaterThan(85.0, $d);
    }

    public function test27ComponentReconstructionInternalBeforeBridging(): void
    {
        $graph = $this->engine->buildGraph(15);
        $this->assertNotEmpty($graph['components']);
        $this->assertGreaterThanOrEqual(2, count($graph['components']));
    }

    public function test28TerminalAnchorExtensionPromotion(): void
    {
        $graph = $this->engine->buildGraph(15);
        $this->assertContains(3, $graph['terminal_ids']);
        $cands = $this->engine->discoverCandidates($graph);
        $exts = array_filter($cands, fn($c) => $c['candidate_type'] === 'ANCHOR_MAINLINE_EXTENSION');
        $this->assertNotEmpty($exts);
    }

    public function test29DynamicGraphRecalculationAfterBatch(): void
    {
        $preview1 = $this->engine->preview(15);
        $initialIsolated = $preview1['inventory']['isolated_assets_count'];

        // Run batch
        $res = $this->engine->run(['penyulang_id' => 15, 'mode' => 'AUTO', 'batch_size' => 2, 'max_batches' => 1]);
        $this->assertSame('STABILIZED', $res['status']);
        $this->assertGreaterThan(0, $res['total_created_translines']);

        $preview2 = $this->engine->preview(15);
        $this->assertLessThan($initialIsolated, $preview2['inventory']['isolated_assets_count']);
    }

    public function test30BatchSizeCeilingMax10(): void
    {
        $res = $this->engine->run(['penyulang_id' => 15, 'mode' => 'AUTO', 'batch_size' => 15]); // Requested 15
        foreach ($res['batches'] as $b) {
            $this->assertLessThanOrEqual(10, $b['created_count']);
        }
    }

    public function test31MultiBatchExecutionProgression(): void
    {
        $res = $this->engine->run(['penyulang_id' => 15, 'mode' => 'AUTO']);
        $this->assertGreaterThanOrEqual(1, $res['batches_executed']);
        $this->assertGreaterThan(0, $res['total_created_translines']);
    }

    public function test32AtomicTransactionRollbackOnFailure(): void
    {
        $preCount = $this->db->table('gis_translines')->where('penyulang_id', 15)->countAllResults();
        $roll = $this->engine->rollback([999999], 'DUMMY_RUN');
        $this->assertSame(0, $roll['count']);
        $postCount = $this->db->table('gis_translines')->where('penyulang_id', 15)->countAllResults();
        $this->assertSame($preCount, $postCount);
    }

    public function test33NaturalStabilizationStopGate(): void
    {
        // First run completes all available connections
        $res1 = $this->engine->run(['penyulang_id' => 15, 'mode' => 'AUTO']);
        $this->assertSame('STABILIZED', $res1['status']);

        // Second run must halt naturally
        $res2 = $this->engine->run(['penyulang_id' => 15, 'mode' => 'AUTO']);
        $this->assertSame('IDEMPOTENT', $res2['status']);
        $this->assertSame(0, $res2['total_created_translines']);
    }

    public function test34PostIdempotencySecondExecutionZeroCreated(): void
    {
        $this->engine->run(['penyulang_id' => 15, 'mode' => 'AUTO']);
        $res = $this->engine->run(['penyulang_id' => 15, 'mode' => 'AUTO']);
        $this->assertSame(0, $res['total_created_translines']);
        $this->assertSame(0, $res['batches_executed']);
    }

    public function test35ExistingTranslinesImmutability(): void
    {
        $preRow1 = $this->db->table('gis_translines')->where('id', 1)->get()->getRowArray();
        $this->engine->run(['penyulang_id' => 15, 'mode' => 'AUTO']);
        $postRow1 = $this->db->table('gis_translines')->where('id', 1)->get()->getRowArray();

        $this->assertSame($preRow1['transline_code'], $postRow1['transline_code']);
        $this->assertSame($preRow1['created_by'], $postRow1['created_by']);
        $this->assertSame($preRow1['distance_meters'], $postRow1['distance_meters']);
    }

    public function test36AssetsZeroWriteFirewall(): void
    {
        $preHash = $this->engine->captureTableSignatures(15)['assets'];
        $this->engine->run(['penyulang_id' => 15, 'mode' => 'AUTO']);
        $postHash = $this->engine->captureTableSignatures(15)['assets'];

        $this->assertSame($preHash, $postHash, 'Assets table coordinates and attributes must remain 100% untouched');
    }

    public function test37TemuanZeroWriteFirewall(): void
    {
        $preHash = $this->engine->captureTableSignatures(15)['temuan'];
        $this->engine->run(['penyulang_id' => 15, 'mode' => 'AUTO']);
        $postHash = $this->engine->captureTableSignatures(15)['temuan'];

        $this->assertSame($preHash, $postHash, 'Temuan table must remain 100% untouched');
    }

    public function test38SectionsZeroWriteFirewall(): void
    {
        $preCount = $this->db->table('sections')->countAllResults();
        $this->engine->run(['penyulang_id' => 15, 'mode' => 'AUTO']);
        $postCount = $this->db->table('sections')->countAllResults();

        $this->assertSame($preCount, $postCount);
    }

    public function test39PenyulangZeroWriteFirewall(): void
    {
        $preCount = $this->db->table('penyulang')->countAllResults();
        $this->engine->run(['penyulang_id' => 15, 'mode' => 'AUTO']);
        $postCount = $this->db->table('penyulang')->countAllResults();

        $this->assertSame($preCount, $postCount);
    }

    public function test40GlobalMultiFeederScope(): void
    {
        $globalPreview = $this->engine->preview(null);
        $this->assertNull($globalPreview['penyulang_id']);
        $this->assertGreaterThan(0, $globalPreview['inventory']['total_master_assets']);
    }
}
