<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Controllers\GisController;
use Config\Services;

/**
 * E2E & Unit Test Suite for GIS Topology Live Reconciliation & Active Version Invariant
 * Tests:
 * 1. Connection A -> B creates Active Version 1
 * 2. Route Edit A -> C (Replace) supersedes v1, creates Active Version 2 with updated geometry
 * 3. Invariant: COUNT(is_active = 1) === 1 for every feeder
 * 4. Geometry Mutation: G1 != G2
 * 5. AR-01 Section Gate Guard: assets.section_id remains 100% untouched
 */
class GisTopologyReconciliationE2ETest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->setupSchema();
        $this->setUpFixtures();
    }

    private function setupSchema(): void
    {
        $forge = \Config\Database::forge();

        // 1. penyulangs
        if (!$this->db->tableExists('penyulangs')) {
            $forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 150],
                'ulp_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'is_active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('penyulangs', true);
        }

        // 2. assets
        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'kode_asset'      => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'      => ['type' => 'VARCHAR', 'constraint' => 255],
                'jenis_asset'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'penyulang_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'ulp_id'          => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'parent_asset_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'sequence_no'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'latitude'        => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'longitude'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        } else {
            $colsToAdd = [
                'sequence_no'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'parent_asset_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'ulp_id'          => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
            ];
            foreach ($colsToAdd as $colName => $colDef) {
                try {
                    $forge->addColumn('assets', [$colName => $colDef]);
                } catch (\Throwable $e) {
                    // Column might already exist in memory DB
                }
            }
        }

        // 3. asset_relationships
        if (!$this->db->tableExists('asset_relationships')) {
            $forge->addField([
                'id'                 => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'parent_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'child_asset_id'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'source_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'target_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id'       => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'relationship_type'  => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'NETWORK'],
                'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'AAAC'],
                'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => '150 mm²'],
                'conductor_material' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'installation_type'  => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'circuit_config'     => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'distance_meters'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'source'             => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'VERIFIED'],
                'verified_by'        => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'verified_at'        => ['type' => 'DATETIME', 'null' => true],
                'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'updated_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('asset_relationships', true);
        }

        // 4. network_topology_versions
        if (!$this->db->tableExists('network_topology_versions')) {
            $forge->addField([
                'id'               => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'penyulang_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'version_no'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'correction_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'geojson_topology' => ['type' => 'TEXT', 'null' => true],
                'nodes_count'      => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'segments_count'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'is_active'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'version_status'   => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'ACTIVE'],
                'created_by'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
                'superseded_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('network_topology_versions', true);
        }

        // 5. field_corrections
        if (!$this->db->tableExists('field_corrections')) {
            $forge->addField([
                'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'correction_code' => ['type' => 'VARCHAR', 'constraint' => 60],
                'correction_type' => ['type' => 'VARCHAR', 'constraint' => 50],
                'asset_id'        => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'ulp_id'          => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'before_payload'  => ['type' => 'TEXT', 'null' => true],
                'after_payload'   => ['type' => 'TEXT', 'null' => true],
                'rationale'       => ['type' => 'TEXT', 'null' => true],
                'reporter_name'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'reporter_role'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'status'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'APPROVED'],
                'reviewer_name'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'reviewer_role'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'review_notes'    => ['type' => 'TEXT', 'null' => true],
                'reviewed_at'     => ['type' => 'DATETIME', 'null' => true],
                'applied_at'      => ['type' => 'DATETIME', 'null' => true],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
                'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('field_corrections', true);
        }
    }

    private function setUpFixtures(): void
    {
        // Setup session for Admin Actor
        $session = Services::session();
        $session->set([
            'isLoggedIn' => true,
            'user_id'    => 1,
            'username'   => 'admin_gis',
            'role'       => 'SUPER_ADMIN',
            'nama'       => 'Super Administrator GIS',
            'ulp_id'     => 1,
        ]);

        // Clean up test data for pilot feeder 999
        $this->db->table('network_topology_versions')->where('penyulang_id', 999)->delete();
        $this->db->table('asset_relationships')->where('parent_asset_id', 9991)->delete();
        $this->db->table('assets')->where('penyulang_id', 999)->delete();
        $this->db->table('penyulangs')->where('id', 999)->delete();

        // Create Pilot Feeder 999
        $this->db->table('penyulangs')->insert([
            'id'             => 999,
            'kode_penyulang' => 'PYL-TEST-999',
            'nama_penyulang' => 'TEST FEEDER RECONCILIATION',
            'ulp_id'         => 1,
            'is_active'      => 1,
        ]);

        // Insert 3 Test Assets (A: 9991, B: 9992, C: 9993)
        $this->db->table('assets')->insert([
            'id'             => 9991,
            'kode_asset'     => 'AST-TEST-A',
            'nama_asset'     => 'Tiang Sumber A',
            'jenis_asset'    => 'TIANG_BETON',
            'penyulang_id'   => 999,
            'ulp_id'         => 1,
            'section_id'     => 101, // AR-01 Section Baseline
            'sequence_no'    => 1,
            'latitude'       => -7.450000,
            'longitude'      => 112.710000,
            'parent_asset_id'=> null,
        ]);

        $this->db->table('assets')->insert([
            'id'             => 9992,
            'kode_asset'     => 'AST-TEST-B',
            'nama_asset'     => 'Tiang Tujuan B',
            'jenis_asset'    => 'TIANG_BETON',
            'penyulang_id'   => 999,
            'ulp_id'         => 1,
            'section_id'     => 101,
            'sequence_no'    => 2,
            'latitude'       => -7.451000,
            'longitude'      => 112.711000,
            'parent_asset_id'=> null,
        ]);

        $this->db->table('assets')->insert([
            'id'             => 9993,
            'kode_asset'     => 'AST-TEST-C',
            'nama_asset'     => 'Tiang Alternatif C',
            'jenis_asset'    => 'TIANG_BETON',
            'penyulang_id'   => 999,
            'ulp_id'         => 1,
            'section_id'     => 101,
            'sequence_no'    => 3,
            'latitude'       => -7.452000,
            'longitude'      => 112.712000,
            'parent_asset_id'=> null,
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->db->table('network_topology_versions')->where('penyulang_id', 999)->delete();
        $this->db->table('asset_relationships')->where('parent_asset_id', 9991)->delete();
        $this->db->table('assets')->where('penyulang_id', 999)->delete();
        $this->db->table('penyulangs')->where('id', 999)->delete();
    }

    /**
     * Test E2E: Connect A -> B, then Edit A -> C, verify geometry changes and invariant COUNT(is_active=1) == 1
     */
    public function testRouteEditReconciliationAndSingleActiveVersionInvariant(): void
    {
        $controller = new GisController();

        // 1. Initial State: 0 topology versions
        $initCount = $this->db->table('network_topology_versions')
            ->where('penyulang_id', 999)
            ->countAllResults();
        $this->assertSame(0, $initCount);

        // 2. Action 1: Connect A (9991) -> B (9992)
        $request = Services::request();
        $request->setBody(json_encode([
            'source_asset_id' => 9991,
            'target_asset_id' => 9992,
            'connection_mode' => 'REPLACE',
            'conductor_type'  => 'AAAC',
            'conductor_size'  => '150 mm²',
        ]));
        $controller->initController($request, Services::response(), Services::logger());

        $response1 = $controller->apiConnectTopology();
        $this->assertSame(200, $response1->getStatusCode());
        $resBody1 = json_decode($response1->getBody(), true);

        $this->assertTrue($resBody1['is_direct_commit']);
        $this->assertNotEmpty($resBody1['topology']);

        // Verify Version 1 in Database
        $v1 = $this->db->table('network_topology_versions')
            ->where('penyulang_id', 999)
            ->where('version_no', 1)
            ->get()->getRowArray();
        $this->assertNotNull($v1);
        $this->assertSame(1, (int)$v1['is_active']);
        $this->assertSame('ACTIVE', $v1['version_status']);

        // Verify Invariant: COUNT(is_active = 1) === 1
        $activeCount1 = $this->db->table('network_topology_versions')
            ->where('penyulang_id', 999)
            ->where('is_active', 1)
            ->countAllResults();
        $this->assertSame(1, $activeCount1);

        $geo1 = json_decode($v1['geojson_topology'], true);
        $coords1 = $geo1['coordinates'] ?? [];

        // 3. Action 2: Edit Jalur: Change connection from A -> B to A -> C (9991 -> 9993)
        $request->setBody(json_encode([
            'source_asset_id' => 9991,
            'target_asset_id' => 9993,
            'connection_mode' => 'REPLACE',
            'conductor_type'  => 'AAAC',
            'conductor_size'  => '150 mm²',
        ]));
        $controller->initController($request, Services::response(), Services::logger());

        $response2 = $controller->apiConnectTopology();
        $this->assertSame(200, $response2->getStatusCode());
        $resBody2 = json_decode($response2->getBody(), true);

        $this->assertTrue($resBody2['is_direct_commit']);
        $this->assertNotEmpty($resBody2['topology']);

        // Verify Version 2 in Database
        $v2 = $this->db->table('network_topology_versions')
            ->where('penyulang_id', 999)
            ->where('version_no', 2)
            ->get()->getRowArray();
        $this->assertNotNull($v2);
        $this->assertSame(1, (int)$v2['is_active']);
        $this->assertSame('ACTIVE', $v2['version_status']);

        // Verify Version 1 is now inactive/superseded
        $v1Updated = $this->db->table('network_topology_versions')
            ->where('penyulang_id', 999)
            ->where('version_no', 1)
            ->get()->getRowArray();
        $this->assertSame(0, (int)$v1Updated['is_active']);
        $this->assertContains($v1Updated['version_status'], ['HISTORICAL', 'SUPERSEDED']);

        // CRITICAL INVARIANT: EXACTLY 1 ACTIVE VERSION
        $activeCount2 = $this->db->table('network_topology_versions')
            ->where('penyulang_id', 999)
            ->where('is_active', 1)
            ->countAllResults();
        $this->assertSame(1, $activeCount2);

        // GEOMETRY MUTATION CHECK: G1 != G2
        $geo2 = json_decode($v2['geojson_topology'], true);
        $coords2 = $geo2['coordinates'] ?? [];
        $this->assertNotEquals($coords1, $coords2);

        // Verify target in asset_relationships is now C (9993)
        $rel = $this->db->table('asset_relationships')
            ->where('source_asset_id', 9991)
            ->where('is_active', 1)
            ->get()->getRowArray();
        $this->assertNotNull($rel);
        $this->assertSame(9993, (int)$rel['target_asset_id']);

        // 4. AR-01 GOVERNANCE GUARD: Verify assets.section_id is 100% UNTOUCHED
        $assetA = $this->db->table('assets')->where('id', 9991)->get()->getRowArray();
        $assetB = $this->db->table('assets')->where('id', 9992)->get()->getRowArray();
        $assetC = $this->db->table('assets')->where('id', 9993)->get()->getRowArray();

        $this->assertSame(101, (int)$assetA['section_id'], 'AR-01 Guard: Asset A section_id must be untouched');
        $this->assertSame(101, (int)$assetB['section_id'], 'AR-01 Guard: Asset B section_id must be untouched');
        $this->assertSame(101, (int)$assetC['section_id'], 'AR-01 Guard: Asset C section_id must be untouched');
    }

    /**
     * Test Disconnect Topology and Invariant Conservation
     */
    public function testDisconnectTopologyMaintainsSingleActiveVersionInvariant(): void
    {
        $controller = new GisController();
        $request = Services::request();

        // 1. Create Connection A -> B (v1)
        $request->setBody(json_encode([
            'source_asset_id' => 9991,
            'target_asset_id' => 9992,
            'connection_mode' => 'REPLACE',
            'conductor_type'  => 'AAAC',
            'conductor_size'  => '150 mm²',
        ]));
        $controller->initController($request, Services::response(), Services::logger());
        $controller->apiConnectTopology();

        // 2. Disconnect A -> B
        $request->setBody(json_encode([
            'source_asset_id' => 9991,
            'target_asset_id' => 9992,
        ]));
        $controller->initController($request, Services::response(), Services::logger());
        $responseDisc = $controller->apiDisconnectTopology();

        $this->assertSame(200, $responseDisc->getStatusCode());
        $resBody = json_decode($responseDisc->getBody(), true);
        $this->assertTrue($resBody['is_direct_commit']);

        // Verify Invariant: COUNT(is_active = 1) <= 1
        $activeCount = $this->db->table('network_topology_versions')
            ->where('penyulang_id', 999)
            ->where('is_active', 1)
            ->countAllResults();
        $this->assertLessThanOrEqual(1, $activeCount);

        // Verify relationship deleted
        $activeRels = $this->db->table('asset_relationships')
            ->where('source_asset_id', 9991)
            ->where('target_asset_id', 9992)
            ->countAllResults();
        $this->assertSame(0, $activeRels);
    }
}
