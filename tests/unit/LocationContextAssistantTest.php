<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\LocationContextService;

/**
 * MAP-03: Read-Only Location Context Assistant Test Suite
 *
 * Covers all 27 architectural and governance requirements:
 * 1. Valid GPS resolves context correctly (ULP -> Feeder -> Section)
 * 2. Missing coordinates returns GPS_UNAVAILABLE
 * 3. Lat/Lng out of bounds returns INVALID_COORDINATES
 * 4. Coordinate at Null Island (0, 0) rejected as INVALID_COORDINATES
 * 5. GPS accuracy <= 20m classified as HIGH_QUALITY
 * 6. GPS accuracy 21m - 50m classified as ACCEPTABLE
 * 7. GPS accuracy > 50m classified as LOW_ACCURACY
 * 8. Missing accuracy defaults to UNKNOWN
 * 9. Spatial bounding box filters out candidates > 1 km
 * 10. Assets within 1 km correctly evaluated by Haversine formula
 * 11. Nearest asset identified accurately by minimum distance
 * 12. Tie-breaking between equidistant assets is deterministic
 * 13. Hierarchy resolved when asset has section_id
 * 14. Hierarchy resolved when asset lacks section_id but has penyulang_id
 * 15. Hierarchy resolved when asset lacks penyulang_id but has ulp_id
 * 16. No assets within 1 km returns NO_CONTEXT status
 * 17. Empty assets table handled gracefully with NO_CONTEXT
 * 18. Soft-deleted assets (deleted_at IS NOT NULL) ignored
 * 19. User ULP scoping enforced for admin_ulp role
 * 20. Out-of-scope asset for admin_ulp returns FORBIDDEN
 * 21. Superadmin role bypasses ULP scoping restrictions
 * 22. Nearest asset returned as suggestion only (unpersisted)
 * 23. Suggestion does NOT mutate assets table (0 mutations)
 * 24. Suggestion does NOT mutate sections table (0 mutations)
 * 25. Suggestion does NOT mutate penyulang table (0 mutations)
 * 26. Suggestion does NOT mutate gis_translines or gis_transline_proposals
 * 27. Ambiguous feeder boundary flagged with AMBIGUOUS_FEEDER_BOUNDARY
 */
class LocationContextAssistantTest extends CIUnitTestCase
{
    protected $db;
    protected LocationContextService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->setupTestSchema();
        $this->seedTestData();
        $this->service = new LocationContextService($this->db);
    }

    protected function setupTestSchema(): void
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
                'ulp_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => 1],
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
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('sections', true);
        }

        if (!$this->db->tableExists('construction_types')) {
            $forge->addField([
                'id'                  => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'code'                => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'name'                => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'voltage_level'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '20kV'],
                'construction_family' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'approval_status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
                'standard_reference'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'governance_status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
                'is_active'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('construction_types', true);
        }

        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 255],
                'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'type'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'ulp_id'               => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'sequence_no'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'construction_type_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'latitude'             => ['type' => 'DECIMAL', 'constraint' => '10,8', 'null' => true],
                'longitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'lokasi'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
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
                'source_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'target_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'from_asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'to_asset_id'        => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'geometry'           => ['type' => 'TEXT', 'null' => true],
                'geometry_type'      => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'LineString'],
                'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'conductor_material' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'ALUMINUM_ALLOY'],
                'installation_type'  => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'OVERHEAD'],
                'circuit_config'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '3_PHASE'],
                'distance_meters'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 50.00],
                'length_m'           => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'source'             => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'MANUAL'],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVE'],
                'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        }

        if (!$this->db->tableExists('gis_transline_proposals')) {
            $forge->addField([
                'id'                      => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id'            => ['type' => 'INT', 'constraint' => 11],
                'section_id'              => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'source_asset_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'target_asset_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'from_asset_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'to_asset_id'             => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'from_asset_code'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'to_asset_code'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'natural_key'             => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'proposed_conductor_type' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'proposed_conductor_size' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'conductor_type'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'conductor_size'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'proposed_distance'       => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 50.00],
                'length_m'                => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'proposed_geometry'       => ['type' => 'TEXT', 'null' => true],
                'geometry_json'           => ['type' => 'TEXT', 'null' => true],
                'classification'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'AUTO_MATCH'],
                'confidence_score'        => ['type' => 'DECIMAL', 'constraint' => '5,4', 'default' => 1.0000],
                'confidence'              => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'HIGH'],
                'visual_tier'             => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AUTO_READY'],
                'evidence_json'           => ['type' => 'TEXT', 'null' => true],
                'proposal_source'         => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'DETERMINISTIC_ENGINE'],
                'engine_version'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'TL-01-V2.0'],
                'status'                  => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'PENDING_REVIEW'],
                'review_status'           => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'PENDING_REVIEW'],
                'reviewed_by'             => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'reviewed_at'             => ['type' => 'DATETIME', 'null' => true],
                'review_note'             => ['type' => 'TEXT', 'null' => true],
                'confirmed_transline_id'  => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at'              => ['type' => 'DATETIME', 'null' => true],
                'updated_at'              => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'              => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_transline_proposals', true);
        }

        if (!$this->db->tableExists('temuan')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'ulp_id'       => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_id'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'asset_id'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'judul'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan', true);
        }

        if (!$this->db->tableExists('temuan_materials')) {
            $forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'temuan_id'   => ['type' => 'INT', 'constraint' => 11],
                'material_id' => ['type' => 'INT', 'constraint' => 11],
                'quantity'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 1.0],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan_materials', true);
        }
    }

    protected function seedTestData(): void
    {
        $this->db->table('ulps')->emptyTable();
        $this->db->table('penyulang')->emptyTable();
        $this->db->table('sections')->emptyTable();
        $this->db->table('assets')->emptyTable();
        $this->db->table('gis_translines')->emptyTable();
        $this->db->table('gis_transline_proposals')->emptyTable();
        $this->db->table('temuan')->emptyTable();
        $this->db->table('temuan_materials')->emptyTable();

        // Seed 2 ULPs
        $this->db->table('ulps')->insertBatch([
            ['id' => 1, 'kode_ulp' => 'ULP01', 'nama_ulp' => 'ULP SIDOARJO KOTA', 'status' => 'AKTIF'],
            ['id' => 2, 'kode_ulp' => 'ULP02', 'nama_ulp' => 'ULP KRIAN', 'status' => 'AKTIF'],
        ]);

        // Seed Feeders
        $this->db->table('penyulang')->insertBatch([
            ['id' => 10, 'ulp_id' => 1, 'kode_penyulang' => 'FDR-BLT', 'nama_penyulang' => 'BULU TRENGGULIS', 'status' => 'AKTIF'],
            ['id' => 20, 'ulp_id' => 2, 'kode_penyulang' => 'FDR-KRN', 'nama_penyulang' => 'KRIAN RAYA', 'status' => 'AKTIF'],
        ]);

        // Seed Sections
        $this->db->table('sections')->insertBatch([
            ['id' => 100, 'penyulang_id' => 10, 'nama_section' => 'SECTION PANGKAL BLT'],
            ['id' => 101, 'penyulang_id' => 10, 'nama_section' => 'SECTION PERCABANGAN BLT'],
            ['id' => 200, 'penyulang_id' => 20, 'nama_section' => 'SECTION UTAMA KRIAN'],
        ]);

        // Seed Reference Assets around Sidoarjo (-7.4478, 112.7183)
        $this->db->table('assets')->insertBatch([
            [
                'id'           => 1,
                'kode_asset'   => 'TIANG-BLT-001',
                'nama_asset'   => 'Tiang Distribusi BLT 01',
                'jenis_asset'  => 'TIANG',
                'type'         => 'POLE',
                'section_id'   => 100,
                'penyulang_id' => 10,
                'ulp_id'       => 1,
                'latitude'     => -7.44790000,
                'longitude'    => 112.71830000,
                'status'       => 'NORMAL',
                'deleted_at'   => null,
            ],
            [
                'id'           => 2,
                'kode_asset'   => 'TIANG-BLT-002',
                'nama_asset'   => 'Tiang Distribusi BLT 02',
                'jenis_asset'  => 'TIANG',
                'type'         => 'POLE',
                'section_id'   => 100,
                'penyulang_id' => 10,
                'ulp_id'       => 1,
                'latitude'     => -7.44880000,
                'longitude'    => 112.71830000,
                'status'       => 'NORMAL',
                'deleted_at'   => null,
            ],
            [
                'id'           => 3,
                'kode_asset'   => 'TIANG-KRN-001',
                'nama_asset'   => 'Tiang Distribusi Krian 01',
                'jenis_asset'  => 'TIANG',
                'type'         => 'POLE',
                'section_id'   => 200,
                'penyulang_id' => 20,
                'ulp_id'       => 2,
                'latitude'     => -7.45200000,
                'longitude'    => 112.71830000,
                'status'       => 'NORMAL',
                'deleted_at'   => null,
            ],
            [
                'id'           => 4,
                'kode_asset'   => 'TIANG-FAR-001',
                'nama_asset'   => 'Tiang Jauh > 1 km',
                'jenis_asset'  => 'TIANG',
                'type'         => 'POLE',
                'section_id'   => 101,
                'penyulang_id' => 10,
                'ulp_id'       => 1,
                'latitude'     => -7.49000000,
                'longitude'    => 112.71830000,
                'status'       => 'NORMAL',
                'deleted_at'   => null,
            ],
            [
                'id'           => 5,
                'kode_asset'   => 'TIANG-DELETED-001',
                'nama_asset'   => 'Tiang Dihapus',
                'jenis_asset'  => 'TIANG',
                'type'         => 'POLE',
                'section_id'   => 100,
                'penyulang_id' => 10,
                'ulp_id'       => 1,
                'latitude'     => -7.44785000,
                'longitude'    => 112.71830000,
                'status'       => 'NORMAL',
                'deleted_at'   => '2026-01-01 00:00:00',
            ],
        ]);
    }

    public function test01_ValidGpsResolvesContextCorrectly(): void
    {
        $res = $this->service->resolveContext(-7.4478, 112.7183, 10.0);

        $this->assertEquals(LocationContextService::STATUS_READY, $res['status']);
        $this->assertEquals(LocationContextService::QUALITY_HIGH_QUALITY, $res['gps_quality']);
        $this->assertNotNull($res['context']);
        $this->assertEquals(1, $res['context']['ulp']['id']);
        $this->assertEquals('ULP SIDOARJO KOTA', $res['context']['ulp']['nama_ulp']);
        $this->assertEquals(10, $res['context']['penyulang']['id']);
        $this->assertEquals('BULU TRENGGULIS', $res['context']['penyulang']['nama_penyulang']);
        $this->assertEquals(100, $res['context']['section']['id']);
        $this->assertEquals('SECTION PANGKAL BLT', $res['context']['section']['nama_section']);
    }

    public function test02_MissingCoordinatesReturnsGpsUnavailable(): void
    {
        $res1 = $this->service->resolveContext(null, 112.7183);
        $this->assertEquals(LocationContextService::STATUS_GPS_UNAVAILABLE, $res1['status']);
        $this->assertNull($res1['context']);

        $res2 = $this->service->resolveContext(-7.4478, null);
        $this->assertEquals(LocationContextService::STATUS_GPS_UNAVAILABLE, $res2['status']);
        $this->assertNull($res2['context']);
    }

    public function test03_CoordinatesOutOfBoundsReturnsInvalid(): void
    {
        $resLatHigh = $this->service->resolveContext(95.0, 112.7183);
        $this->assertEquals(LocationContextService::STATUS_INVALID_COORDINATES, $resLatHigh['status']);

        $resLatLow = $this->service->resolveContext(-95.0, 112.7183);
        $this->assertEquals(LocationContextService::STATUS_INVALID_COORDINATES, $resLatLow['status']);

        $resLngHigh = $this->service->resolveContext(-7.4478, 185.0);
        $this->assertEquals(LocationContextService::STATUS_INVALID_COORDINATES, $resLngHigh['status']);

        $resLngLow = $this->service->resolveContext(-7.4478, -185.0);
        $this->assertEquals(LocationContextService::STATUS_INVALID_COORDINATES, $resLngLow['status']);
    }

    public function test04_NullIslandCoordinatesRejected(): void
    {
        $res = $this->service->resolveContext(0.0, 0.0);
        $this->assertEquals(LocationContextService::STATUS_INVALID_COORDINATES, $res['status']);
        $this->assertEquals('COORDINATES_OUT_OF_BOUNDS', $res['diagnostic']);
    }

    public function test05_GpsAccuracyHighQuality(): void
    {
        $res = $this->service->resolveContext(-7.4478, 112.7183, 15.0);
        $this->assertEquals(LocationContextService::QUALITY_HIGH_QUALITY, $res['gps_quality']);
        $this->assertEquals(LocationContextService::STATUS_READY, $res['status']);
    }

    public function test06_GpsAccuracyAcceptable(): void
    {
        $res = $this->service->resolveContext(-7.4478, 112.7183, 35.0);
        $this->assertEquals(LocationContextService::QUALITY_ACCEPTABLE, $res['gps_quality']);
        $this->assertEquals(LocationContextService::STATUS_READY, $res['status']);
    }

    public function test07_GpsAccuracyLowAccuracy(): void
    {
        $res = $this->service->resolveContext(-7.4478, 112.7183, 75.0);
        $this->assertEquals(LocationContextService::QUALITY_LOW_ACCURACY, $res['gps_quality']);
        $this->assertEquals(LocationContextService::STATUS_LOW_ACCURACY, $res['status']);
        $this->assertStringContainsString('rendah', strtolower($res['message']));
    }

    public function test08_MissingAccuracyDefaultsToUnknown(): void
    {
        $res = $this->service->resolveContext(-7.4478, 112.7183, null);
        $this->assertEquals(LocationContextService::QUALITY_UNKNOWN, $res['gps_quality']);
        $this->assertEquals(LocationContextService::STATUS_READY, $res['status']);
    }

    public function test09_SpatialFilterExcludesFarCandidates(): void
    {
        $res = $this->service->resolveContext(-7.4899, 112.7183, 10.0);
        $this->assertEquals(LocationContextService::STATUS_READY, $res['status']);
        $this->assertEquals(4, $res['nearest_asset']['id']);
        $this->assertEquals('TIANG-FAR-001', $res['nearest_asset']['kode_asset']);
    }

    public function test10_HaversineDistanceCalculationAccuracy(): void
    {
        $dist = $this->service->haversineDistanceMeters(-7.4478, 112.7183, -7.4479, 112.7183);
        $this->assertGreaterThan(10.0, $dist);
        $this->assertLessThan(15.0, $dist);
    }

    public function test11_NearestAssetIdentifiedCorrectly(): void
    {
        $res = $this->service->resolveContext(-7.4478, 112.7183, 5.0);
        $this->assertNotNull($res['nearest_asset']);
        $this->assertEquals(1, $res['nearest_asset']['id']);
        $this->assertEquals('TIANG-BLT-001', $res['nearest_asset']['kode_asset']);
        $this->assertLessThan(20.0, $res['nearest_asset']['distance_m']);
    }

    public function test12_DeterministicTieBreaking(): void
    {
        $this->db->table('assets')->insertBatch([
            [
                'id'           => 101,
                'kode_asset'   => 'TIANG-TIE-A',
                'nama_asset'   => 'Tiang Tie A',
                'jenis_asset'  => 'TIANG',
                'type'         => 'POLE',
                'section_id'   => 100,
                'penyulang_id' => 10,
                'ulp_id'       => 1,
                'latitude'     => -7.44700000,
                'longitude'    => 112.71800000,
                'status'       => 'NORMAL',
            ],
            [
                'id'           => 102,
                'kode_asset'   => 'TIANG-TIE-B',
                'nama_asset'   => 'Tiang Tie B',
                'jenis_asset'  => 'TIANG',
                'type'         => 'POLE',
                'section_id'   => 100,
                'penyulang_id' => 10,
                'ulp_id'       => 1,
                'latitude'     => -7.44700000,
                'longitude'    => 112.71800000,
                'status'       => 'NORMAL',
            ],
        ]);

        $res1 = $this->service->resolveContext(-7.4470, 112.7180, 5.0);
        $res2 = $this->service->resolveContext(-7.4470, 112.7180, 5.0);

        $this->assertEquals($res1['nearest_asset']['id'], $res2['nearest_asset']['id']);
    }

    public function test13_HierarchyResolvedWithSectionId(): void
    {
        $res = $this->service->resolveContext(-7.4478, 112.7183, 10.0);
        $this->assertEquals(100, $res['context']['section']['id']);
        $this->assertEquals(10, $res['context']['penyulang']['id']);
        $this->assertEquals(1, $res['context']['ulp']['id']);
    }

    public function test14_HierarchyResolvedWhenAssetLacksSectionId(): void
    {
        $this->db->table('assets')->insert([
            'id'           => 201,
            'kode_asset'   => 'TIANG-NO-SECTION',
            'nama_asset'   => 'Tiang Tanpa Section',
            'jenis_asset'  => 'TIANG',
            'type'         => 'POLE',
            'section_id'   => null,
            'penyulang_id' => 10,
            'ulp_id'       => 1,
            'latitude'     => -7.44000000,
            'longitude'    => 112.71830000,
            'status'       => 'NORMAL',
        ]);

        $res = $this->service->resolveContext(-7.4400, 112.7183, 10.0);
        $this->assertNull($res['context']['section']);
        $this->assertEquals(10, $res['context']['penyulang']['id']);
        $this->assertEquals(1, $res['context']['ulp']['id']);
    }

    public function test15_HierarchyResolvedWhenAssetLacksPenyulangId(): void
    {
        $this->db->table('assets')->insert([
            'id'           => 202,
            'kode_asset'   => 'TIANG-NO-PENYULANG',
            'nama_asset'   => 'Tiang Tanpa Penyulang',
            'jenis_asset'  => 'TIANG',
            'type'         => 'POLE',
            'section_id'   => null,
            'penyulang_id' => null,
            'ulp_id'       => 1,
            'latitude'     => -7.43000000,
            'longitude'    => 112.71830000,
            'status'       => 'NORMAL',
        ]);

        $res = $this->service->resolveContext(-7.4300, 112.7183, 10.0);
        $this->assertNull($res['context']['section']);
        $this->assertNull($res['context']['penyulang']);
        $this->assertEquals(1, $res['context']['ulp']['id']);
    }

    public function test16_NoAssetsWithin1KmReturnsNoContext(): void
    {
        $res = $this->service->resolveContext(-7.8000, 112.9000, 10.0);
        $this->assertEquals(LocationContextService::STATUS_NO_CONTEXT, $res['status']);
        $this->assertNull($res['context']);
        $this->assertNull($res['nearest_asset']);
        $this->assertEquals('NO_ASSETS_WITHIN_BOUND', $res['diagnostic']);
    }

    public function test17_EmptyAssetsTableHandledGracefully(): void
    {
        $this->db->table('assets')->emptyTable();
        $res = $this->service->resolveContext(-7.4478, 112.7183, 10.0);
        $this->assertEquals(LocationContextService::STATUS_NO_CONTEXT, $res['status']);
        $this->assertNull($res['context']);
    }

    public function test18_SoftDeletedAssetsIgnored(): void
    {
        $res = $this->service->resolveContext(-7.44784, 112.7183, 5.0);
        $this->assertEquals(1, $res['nearest_asset']['id']);
        $this->assertNotEquals(5, $res['nearest_asset']['id']);
    }

    public function test19_UserUlpScopingPermitsSameUlp(): void
    {
        $res = $this->service->resolveContext(-7.4478, 112.7183, 10.0, 1, 'admin_ulp');
        $this->assertEquals(LocationContextService::STATUS_READY, $res['status']);
        $this->assertEquals(1, $res['context']['ulp']['id']);
    }

    public function test20_OutOfScopeAssetReturnsForbidden(): void
    {
        $res = $this->service->resolveContext(-7.4520, 112.7183, 10.0, 1, 'admin_ulp');
        $this->assertEquals(LocationContextService::STATUS_FORBIDDEN, $res['status']);
        $this->assertNull($res['context']);
        $this->assertEquals('OUT_OF_SCOPE_ULP', $res['diagnostic']);
    }

    public function test21_SuperadminBypassesUlpScoping(): void
    {
        $res = $this->service->resolveContext(-7.4520, 112.7183, 10.0, 1, 'superadmin');
        $this->assertEquals(LocationContextService::STATUS_READY, $res['status']);
        $this->assertEquals(2, $res['context']['ulp']['id']);
    }

    public function test22_NearestAssetIsSuggestionOnly(): void
    {
        $res = $this->service->resolveContext(-7.4478, 112.7183, 10.0);
        $this->assertArrayHasKey('nearest_asset', $res);
        $this->assertArrayHasKey('id', $res['nearest_asset']);
        $this->assertArrayHasKey('kode_asset', $res['nearest_asset']);
        $this->assertArrayHasKey('distance_m', $res['nearest_asset']);

        $temuanCount = $this->db->table('temuan')->countAllResults();
        $this->assertEquals(0, $temuanCount);
    }

    public function test23_ZeroMutationOnAssetsTable(): void
    {
        $initialAsset = $this->db->table('assets')->where('id', 1)->get()->getRowArray();
        $this->service->resolveContext(-7.4478, 112.7183, 10.0);
        $afterAsset = $this->db->table('assets')->where('id', 1)->get()->getRowArray();

        $this->assertEquals($initialAsset['section_id'], $afterAsset['section_id']);
        $this->assertEquals($initialAsset['penyulang_id'], $afterAsset['penyulang_id']);
        $this->assertEquals($initialAsset['construction_type_id'], $afterAsset['construction_type_id']);
    }

    public function test24_ZeroMutationOnSectionsTable(): void
    {
        $countBefore = $this->db->table('sections')->countAllResults();
        $this->service->resolveContext(-7.4478, 112.7183, 10.0);
        $countAfter = $this->db->table('sections')->countAllResults();

        $this->assertEquals($countBefore, $countAfter);
    }

    public function test25_ZeroMutationOnPenyulangTable(): void
    {
        $countBefore = $this->db->table('penyulang')->countAllResults();
        $this->service->resolveContext(-7.4478, 112.7183, 10.0);
        $countAfter = $this->db->table('penyulang')->countAllResults();

        $this->assertEquals($countBefore, $countAfter);
    }

    public function test26_ZeroMutationOnTranslinesAndProposals(): void
    {
        $transBefore = $this->db->table('gis_translines')->countAllResults();
        $propBefore  = $this->db->table('gis_transline_proposals')->countAllResults();

        $this->service->resolveContext(-7.4478, 112.7183, 10.0);

        $transAfter = $this->db->table('gis_translines')->countAllResults();
        $propAfter  = $this->db->table('gis_transline_proposals')->countAllResults();

        $this->assertEquals($transBefore, $transAfter);
        $this->assertEquals($propBefore, $propAfter);
    }

    public function test27_AmbiguousFeederBoundaryDiagnostic(): void
    {
        $this->db->table('assets')->insertBatch([
            [
                'id'           => 301,
                'kode_asset'   => 'AMB-FDR10',
                'nama_asset'   => 'Aset Feeder 10',
                'jenis_asset'  => 'TIANG',
                'type'         => 'POLE',
                'section_id'   => 100,
                'penyulang_id' => 10,
                'ulp_id'       => 1,
                'latitude'     => -7.46000000,
                'longitude'    => 112.71800000,
                'status'       => 'NORMAL',
            ],
            [
                'id'           => 302,
                'kode_asset'   => 'AMB-FDR20',
                'nama_asset'   => 'Aset Feeder 20',
                'jenis_asset'  => 'TIANG',
                'type'         => 'POLE',
                'section_id'   => 200,
                'penyulang_id' => 20,
                'ulp_id'       => 2,
                'latitude'     => -7.46004000,
                'longitude'    => 112.71800000,
                'status'       => 'NORMAL',
            ],
        ]);

        $res = $this->service->resolveContext(-7.460005, 112.718000, 5.0);
        $this->assertEquals('AMBIGUOUS_FEEDER_BOUNDARY', $res['diagnostic']);
    }
}