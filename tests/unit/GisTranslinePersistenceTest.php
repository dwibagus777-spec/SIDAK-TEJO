<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\GisTranslineService;
use App\Models\GisTranslineModel;
use Config\Database;

/**
 * Unit Tests for GIS Transline Persistence & Authoritative Segment CRUD
 */
class GisTranslinePersistenceTest extends CIUnitTestCase
{
    protected $db;
    protected GisTranslineService $service;
    protected GisTranslineModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->service = new GisTranslineService($this->db);
        $this->model = new GisTranslineModel();

        $this->setupSchema();
    }

    protected function setupSchema(): void
    {
        $forge = \Config\Database::forge();

        // 1. assets table
        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'   => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_asset'   => ['type' => 'VARCHAR', 'constraint' => 100],
                'jenis_asset'  => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 4],
                'ulp_id'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'parent_asset_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'latitude'     => ['type' => 'DECIMAL', 'constraint' => '10,8', 'default' => -7.42617],
                'longitude'    => ['type' => 'DECIMAL', 'constraint' => '11,8', 'default' => 112.73619],
                'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'NORMAL'],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        // 2. asset_relationships table
        if (!$this->db->tableExists('asset_relationships')) {
            $forge->addField([
                'id'                 => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'parent_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'child_asset_id'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'source_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'target_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id'       => ['type' => 'INT', 'constraint' => 11, 'default' => 4],
                'relationship_type'  => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'NETWORK'],
                'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'conductor_material' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'ALUMINUM_ALLOY'],
                'installation_type'  => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'OVERHEAD'],
                'circuit_config'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '3_PHASE'],
                'distance_meters'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0.00],
                'source'             => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'TEST'],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'VERIFIED'],
                'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by'         => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'TEST'],
                'verified_by'        => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'verified_at'        => ['type' => 'DATETIME', 'null' => true],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
                'updated_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('asset_relationships', true);
        }

        // 3. network_topology_versions table
        if (!$this->db->tableExists('network_topology_versions')) {
            $forge->addField([
                'id'               => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id'     => ['type' => 'INT', 'constraint' => 11, 'default' => 4],
                'version_no'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'geojson_topology' => ['type' => 'TEXT', 'null' => true],
                'nodes_count'      => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'segments_count'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'is_active'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'version_status'   => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVE'],
                'superseded_at'    => ['type' => 'DATETIME', 'null' => true],
                'created_by'       => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'SYSTEM'],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('network_topology_versions', true);
        }

        // 4. Clean tables for test isolation
        $this->db->table('gis_translines')->emptyTable();
        $this->db->table('asset_relationships')->emptyTable();
        $this->db->table('network_topology_versions')->emptyTable();
        $this->db->table('assets')->emptyTable();

        // Seed 4 Mock Assets: A (101), B (102), C (103), D (104), E (105)
        $this->db->table('assets')->insertBatch([
            ['id' => 101, 'kode_asset' => 'AST-101', 'nama_asset' => 'POLE_A', 'jenis_asset' => 'JTM', 'ulp_id' => 1, 'status' => 'NORMAL', 'penyulang_id' => 4, 'latitude' => -7.42600, 'longitude' => 112.73600],
            ['id' => 102, 'kode_asset' => 'AST-102', 'nama_asset' => 'POLE_B', 'jenis_asset' => 'JTM', 'ulp_id' => 1, 'status' => 'NORMAL', 'penyulang_id' => 4, 'latitude' => -7.42620, 'longitude' => 112.73620],
            ['id' => 103, 'kode_asset' => 'AST-103', 'nama_asset' => 'POLE_C', 'jenis_asset' => 'JTM', 'ulp_id' => 1, 'status' => 'NORMAL', 'penyulang_id' => 4, 'latitude' => -7.42640, 'longitude' => 112.73640],
            ['id' => 104, 'kode_asset' => 'AST-104', 'nama_asset' => 'POLE_D', 'jenis_asset' => 'JTM', 'ulp_id' => 1, 'status' => 'NORMAL', 'penyulang_id' => 4, 'latitude' => -7.42660, 'longitude' => 112.73660],
            ['id' => 105, 'kode_asset' => 'AST-105', 'nama_asset' => 'POLE_E', 'jenis_asset' => 'JTM', 'ulp_id' => 1, 'status' => 'NORMAL', 'penyulang_id' => 4, 'latitude' => -7.42680, 'longitude' => 112.73680],
            ['id' => 201, 'kode_asset' => 'AST-201', 'nama_asset' => 'POLE_F5_A', 'jenis_asset' => 'JTM', 'ulp_id' => 1, 'status' => 'NORMAL', 'penyulang_id' => 5, 'latitude' => -7.43000, 'longitude' => 112.74000],
            ['id' => 202, 'kode_asset' => 'AST-202', 'nama_asset' => 'POLE_F5_B', 'jenis_asset' => 'JTM', 'ulp_id' => 1, 'status' => 'NORMAL', 'penyulang_id' => 5, 'latitude' => -7.43050, 'longitude' => 112.74050],
        ]);
    }

    /**
     * TEST 1: CREATE MULTIPLE TRANSLINES (T1: A->B, T2: B->C, T3: C->D)
     */
    public function testCreateMultipleTranslinesPersistsAllEntities()
    {
        $res1 = $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 101, 'target_asset_id' => 102]);
        $this->assertEquals('success', $res1['status'], $res1['message'] ?? 'No message');
        $t1Id = $res1['transline_id'];

        $res2 = $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 102, 'target_asset_id' => 103]);
        $this->assertEquals('success', $res2['status']);
        $t2Id = $res2['transline_id'];

        $res3 = $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 103, 'target_asset_id' => 104]);
        $this->assertEquals('success', $res3['status']);
        $t3Id = $res3['transline_id'];

        // Assert 3 distinct records in gis_translines
        $activeRows = $this->model->getActiveTranslinesByFeeder(4);
        $this->assertCount(3, $activeRows);
        $this->assertEquals($t1Id, $activeRows[0]['id']);
        $this->assertEquals($t2Id, $activeRows[1]['id']);
        $this->assertEquals($t3Id, $activeRows[2]['id']);

        // Assert relationships table also contains all 3 edges
        $rBuilder = $this->db->table('asset_relationships');
        if ($this->db->fieldExists('penyulang_id', 'asset_relationships')) {
            $rBuilder->where('penyulang_id', 4);
        }
        if ($this->db->fieldExists('is_active', 'asset_relationships')) {
            $rBuilder->where('is_active', 1);
        }
        $rels = $rBuilder->get()->getResultArray();
        $this->assertCount(3, $rels);
    }

    /**
     * TEST 2: RELOAD FROM API / SERVICE RETURNS ALL ACTIVE SEGMENTS
     */
    public function testReloadReturnsAllActiveSegments()
    {
        $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 101, 'target_asset_id' => 102]);
        $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 102, 'target_asset_id' => 103]);
        $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 103, 'target_asset_id' => 104]);

        $reloaded = $this->service->getFeederTranslines(4);
        $this->assertCount(3, $reloaded);
        $this->assertEquals(101, $reloaded[0]['source_asset_id']);
        $this->assertEquals(102, $reloaded[0]['target_asset_id']);
        $this->assertEquals(102, $reloaded[1]['source_asset_id']);
        $this->assertEquals(103, $reloaded[1]['target_asset_id']);
        $this->assertEquals(103, $reloaded[2]['source_asset_id']);
        $this->assertEquals(104, $reloaded[2]['target_asset_id']);
    }

    /**
     * TEST 3: UPDATE SINGLE SEGMENT PRESERVES OTHER SEGMENTS
     */
    public function testUpdateSingleSegmentPreservesOtherSegments()
    {
        $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 101, 'target_asset_id' => 102]);
        $res2 = $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 102, 'target_asset_id' => 103]);
        $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 103, 'target_asset_id' => 104]);

        $t2Id = $res2['transline_id'];

        // Custom polyline update on T2 (102 -> 103)
        $customGeometry = [
            'type' => 'LineString',
            'coordinates' => [
                [112.73620, -7.42620],
                [112.73630, -7.42630],
                [112.73640, -7.42640]
            ]
        ];

        $updateRes = $this->service->updateSegmentGeometry(4, 102, 103, $customGeometry);
        $this->assertEquals('success', $updateRes['status']);

        // Assert all 3 segments still exist
        $activeRows = $this->service->getFeederTranslines(4);
        $this->assertCount(3, $activeRows);

        // Assert T2 geometry has been updated
        $updatedT2 = $this->model->find($t2Id);
        $this->assertStringContainsString('112.7363', $updatedT2['geometry']);
    }

    /**
     * TEST 4: DELETE SINGLE SEGMENT LEAVES OTHER SEGMENTS INTACT
     */
    public function testDeleteSingleSegmentLeavesOtherSegmentsIntact()
    {
        $res1 = $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 101, 'target_asset_id' => 102]);
        $res2 = $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 102, 'target_asset_id' => 103]);
        $res3 = $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 103, 'target_asset_id' => 104]);

        $t2Id = $res2['transline_id'];

        // Delete T2
        $delRes = $this->service->deleteTransline($t2Id);
        $this->assertEquals('success', $delRes['status']);

        // Assert remaining active count is 2 (T1 and T3)
        $remaining = $this->service->getFeederTranslines(4);
        $this->assertCount(2, $remaining);
        $this->assertEquals(101, $remaining[0]['source_asset_id']);
        $this->assertEquals(102, $remaining[0]['target_asset_id']);
        $this->assertEquals(103, $remaining[1]['source_asset_id']);
        $this->assertEquals(104, $remaining[1]['target_asset_id']);

        // Assert T2 is marked inactive / deleted
        $t2Row = $this->model->find($t2Id);
        $this->assertEquals(0, $t2Row['is_active']);
        $this->assertNotNull($t2Row['deleted_at']);
    }

    /**
     * TEST 5: FEEDER ISOLATION
     */
    public function testFeederIsolation()
    {
        // Feeder 4: 2 segments
        $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 101, 'target_asset_id' => 102]);
        $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 102, 'target_asset_id' => 103]);

        // Feeder 5: 1 segment
        $this->service->saveTransline(['penyulang_id' => 5, 'source_asset_id' => 201, 'target_asset_id' => 202]);

        $feeder4Translines = $this->service->getFeederTranslines(4);
        $feeder5Translines = $this->service->getFeederTranslines(5);

        $this->assertCount(2, $feeder4Translines);
        $this->assertCount(1, $feeder5Translines);
    }

    /**
     * TEST 6: TOPOLOGY SNAPSHOT REBUILD CONTAINS ALL ACTIVE GEOMETRIES
     */
    public function testTopologySnapshotRebuildContainsAllActiveGeometries()
    {
        $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 101, 'target_asset_id' => 102]);
        $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 102, 'target_asset_id' => 103]);
        $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 103, 'target_asset_id' => 104]);

        $snapshot = $this->service->rebuildFeederTopologySnapshot(4, 'TEST_SUITE');

        $this->assertEquals('MultiLineString', $snapshot['type']);
        $this->assertCount(3, $snapshot['coordinates']);
        $this->assertCount(3, $snapshot['edges']);
    }

    /**
     * TEST 7: ATOMIC ROLLBACK ON INVALID TARGET ASSET
     */
    public function testAtomicRollbackOnInvalidData()
    {
        // Try saving with non-existent target asset (ID 99999)
        $res = $this->service->saveTransline(['penyulang_id' => 4, 'source_asset_id' => 101, 'target_asset_id' => 99999]);
        $this->assertEquals('error', $res['status']);

        // Assert 0 translines created
        $translines = $this->service->getFeederTranslines(4);
        $this->assertCount(0, $translines);
    }
}
