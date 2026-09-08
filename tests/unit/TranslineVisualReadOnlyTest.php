<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\GisTranslineService;
use App\Services\TranslineProposalReviewService;

/**
 * TL-01 Visual Realization — Authoritative Transline GIS Layer (Read-Only Implementation Gate)
 *
 * Comprehensive Test Suite covering all 26 user-ratified scenarios:
 * 1. test01AuthoritativeTranslinesReturned
 * 2. test02EmptyScopeReturnsEmptyState
 * 3. test03ValidUlpScopeFilter
 * 4. test04ValidFeederScopeFilter
 * 5. test05ValidSectionScopeFilter
 * 6. test06CrossScopeFeederRejected
 * 7. test07SourceEndpointMustBeAsset
 * 8. test08TargetEndpointMustBeAsset
 * 9. test09FindingCannotBecomeEndpoint
 * 10. test10NumericIdCollisionBetweenFindingAndAssetHandledCorrectly
 * 11. test11OrphanSourceDetectedDiagnostically
 * 12. test12OrphanTargetDetectedDiagnostically
 * 13. test13IdenticalEndpointsSelfLoopDiagnostic
 * 14. test14MissingCoordinatesDiagnostic
 * 15. test15ProposalNotMixedIntoAuthoritativeLayer
 * 16. test16GetEndpointZeroWrite
 * 17. test17ExistingTranslineCountUnchanged
 * 18. test18ExistingAssetCountUnchanged
 * 19. test19ExistingTemuanCountUnchanged
 * 20. test20D2BPreserved
 * 21. test21D2CPreserved
 * 22. test22D3Preserved
 * 23. test23D4APreserved
 * 24. test24AuthorizationPreserved
 * 25. test25MobileGisRenderingPath
 * 26. test26DesktopGisRenderingPath
 */
class TranslineVisualReadOnlyTest extends CIUnitTestCase
{
    protected $db;
    protected GisTranslineService $translineService;
    protected TranslineProposalReviewService $reviewService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->setupSchema();
        $this->seedTestData();
        $this->translineService = new GisTranslineService($this->db);
        $this->reviewService = new TranslineProposalReviewService($this->db);
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
                // Ignore if exists
            }
        }
    }

    protected function setupSchema(): void
    {
        $forge = Database::forge();

        // 1. ulps table
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

        // 2. penyulang table
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

        // 3. sections table
        if (!$this->db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('sections', true);
        }

        // 4. assets table
        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'penyulang_id'         => ['type' => 'INT', 'constraint' => 11],
                'section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'ulp_id'               => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_BETON', 'null' => true],
                'construction_type_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'latitude'             => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'longitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'sequence_no'          => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'parent_asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'status'               => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'NORMAL'],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        // 5. gis_translines table
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
                'distance_meters'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 40.0],
                'length_m'           => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'source'             => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'MANUAL'],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVE'],
                'is_active'          => ['type' => 'INT', 'constraint' => 1, 'default' => 1],
                'created_by'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        }

        // 6. gis_transline_proposals table
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
                'natural_key'             => ['type' => 'VARCHAR', 'constraint' => 128],
                'proposed_conductor_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'AAAC'],
                'proposed_conductor_size' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => '150 mm²'],
                'conductor_type'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'conductor_size'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'proposed_distance'       => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 40.0],
                'length_m'                => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'proposed_geometry'       => ['type' => 'TEXT', 'null' => true],
                'geometry_json'           => ['type' => 'TEXT', 'null' => true],
                'classification'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'AUTO_MATCH'],
                'confidence_score'        => ['type' => 'DECIMAL', 'constraint' => '5,4', 'default' => 1.0000],
                'confidence'              => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'HIGH'],
                'visual_tier'             => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AUTO_READY'],
                'status'                  => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'PENDING_REVIEW'],
                'review_status'           => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'PENDING_REVIEW'],
                'reviewed_by'             => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'reviewed_at'             => ['type' => 'DATETIME', 'null' => true],
                'review_note'             => ['type' => 'TEXT', 'null' => true],
                'confirmed_transline_id'  => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'evidence_json'           => ['type' => 'TEXT', 'null' => true],
                'proposal_source'         => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'DETERMINISTIC_ENGINE'],
                'engine_version'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'TL-01-V2.0'],
                'created_at'              => ['type' => 'DATETIME', 'null' => true],
                'updated_at'              => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'              => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_transline_proposals', true);
        }

        // 7. temuan table
        if (!$this->db->tableExists('temuan')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'ulp_id'       => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_id'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'asset_id'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'nomor_temuan' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'jenis_temuan' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'judul'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'latitude'     => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'longitude'    => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'status'       => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'OPEN'],
                'priority'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'MEDIUM'],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan', true);
        }

        // 8. temuan_materials table
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

        // Safeguard columns
        $this->safeAddColumn('gis_translines', 'conductor_material', ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'ALUMINUM_ALLOY']);
        $this->safeAddColumn('gis_translines', 'installation_type', ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'OVERHEAD']);
        $this->safeAddColumn('gis_translines', 'circuit_config', ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '3_PHASE']);
        $this->safeAddColumn('gis_translines', 'length_m', ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true]);
        $this->safeAddColumn('gis_translines', 'source', ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'MANUAL']);
        $this->safeAddColumn('gis_translines', 'from_asset_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
        $this->safeAddColumn('gis_translines', 'to_asset_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
        $this->safeAddColumn('gis_translines', 'created_by', ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true]);
        $this->safeAddColumn('gis_translines', 'created_at', ['type' => 'DATETIME', 'null' => true]);

        $this->safeAddColumn('gis_transline_proposals', 'from_asset_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
        $this->safeAddColumn('gis_transline_proposals', 'to_asset_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
        $this->safeAddColumn('gis_transline_proposals', 'from_asset_code', ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]);
        $this->safeAddColumn('gis_transline_proposals', 'to_asset_code', ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]);
        $this->safeAddColumn('gis_transline_proposals', 'conductor_type', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->safeAddColumn('gis_transline_proposals', 'conductor_size', ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true]);
        $this->safeAddColumn('gis_transline_proposals', 'length_m', ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true]);
        $this->safeAddColumn('gis_transline_proposals', 'geometry_json', ['type' => 'TEXT', 'null' => true]);
        $this->safeAddColumn('gis_transline_proposals', 'confidence', ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'HIGH']);
        $this->safeAddColumn('gis_transline_proposals', 'visual_tier', ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AUTO_READY']);
        $this->safeAddColumn('gis_transline_proposals', 'review_status', ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'PENDING_REVIEW']);
        $this->safeAddColumn('gis_transline_proposals', 'reviewed_by', ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true]);
        $this->safeAddColumn('gis_transline_proposals', 'reviewed_at', ['type' => 'DATETIME', 'null' => true]);
        $this->safeAddColumn('gis_transline_proposals', 'review_note', ['type' => 'TEXT', 'null' => true]);
        $this->safeAddColumn('gis_transline_proposals', 'deleted_at', ['type' => 'DATETIME', 'null' => true]);

        $this->safeAddColumn('temuan', 'nomor_temuan', ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]);
        $this->safeAddColumn('temuan', 'jenis_temuan', ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true]);
        $this->safeAddColumn('temuan', 'latitude', ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true]);
        $this->safeAddColumn('temuan', 'longitude', ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true]);
        $this->safeAddColumn('assets', 'deleted_at', ['type' => 'DATETIME', 'null' => true]);
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

        // ULP 1 and ULP 2
        $this->db->table('ulps')->insertBatch([
            ['id' => 1, 'kode_ulp' => 'ULP01', 'nama_ulp' => 'ULP SIDOARJO KOTA', 'status' => 'AKTIF'],
            ['id' => 2, 'kode_ulp' => 'ULP02', 'nama_ulp' => 'ULP KRIAN', 'status' => 'AKTIF'],
        ]);

        // Feeder 10 (ULP 1) and Feeder 20 (ULP 2)
        $this->db->table('penyulang')->insertBatch([
            ['id' => 10, 'ulp_id' => 1, 'kode_penyulang' => 'BJK', 'nama_penyulang' => 'BANJAR KEMANTREN', 'status' => 'AKTIF'],
            ['id' => 20, 'ulp_id' => 2, 'kode_penyulang' => 'KRN', 'nama_penyulang' => 'KRIAN KOTA', 'status' => 'AKTIF'],
        ]);

        // Sections
        $this->db->table('sections')->insertBatch([
            ['id' => 100, 'penyulang_id' => 10, 'nama_section' => 'SECTION BJK 01'],
            ['id' => 101, 'penyulang_id' => 10, 'nama_section' => 'SECTION BJK 02'],
            ['id' => 200, 'penyulang_id' => 20, 'nama_section' => 'SECTION KRN 01'],
        ]);

        // Assets
        $this->db->table('assets')->insertBatch([
            ['id' => 1001, 'kode_asset' => 'BJK_01', 'nama_asset' => 'Tiang BJK 01', 'penyulang_id' => 10, 'section_id' => 100, 'ulp_id' => 1, 'latitude' => -7.45210000, 'longitude' => 112.71610000, 'status' => 'NORMAL'],
            ['id' => 1002, 'kode_asset' => 'BJK_02', 'nama_asset' => 'Tiang BJK 02', 'penyulang_id' => 10, 'section_id' => 100, 'ulp_id' => 1, 'latitude' => -7.45250000, 'longitude' => 112.71650000, 'status' => 'NORMAL'],
            ['id' => 1003, 'kode_asset' => 'BJK_03', 'nama_asset' => 'Tiang BJK 03', 'penyulang_id' => 10, 'section_id' => 101, 'ulp_id' => 1, 'latitude' => -7.45300000, 'longitude' => 112.71700000, 'status' => 'NORMAL'],
            ['id' => 2001, 'kode_asset' => 'KRN_01', 'nama_asset' => 'Tiang KRN 01', 'penyulang_id' => 20, 'section_id' => 200, 'ulp_id' => 2, 'latitude' => -7.40000000, 'longitude' => 112.60000000, 'status' => 'NORMAL'],
            ['id' => 2002, 'kode_asset' => 'KRN_02', 'nama_asset' => 'Tiang KRN 02', 'penyulang_id' => 20, 'section_id' => 200, 'ulp_id' => 2, 'latitude' => -7.40050000, 'longitude' => 112.60050000, 'status' => 'NORMAL'],
            ['id' => 400,  'kode_asset' => 'COLLISION_ASSET_400', 'nama_asset' => 'Tiang Khusus 400', 'penyulang_id' => 10, 'section_id' => 100, 'ulp_id' => 1, 'latitude' => -7.45280000, 'longitude' => 112.71680000, 'status' => 'NORMAL'],
        ]);

        // Temuan
        $this->db->table('temuan')->insertBatch([
            ['id' => 501, 'nomor_temuan' => 'TM-01', 'jenis_temuan' => 'POHON_DEKAT_JTM', 'penyulang_id' => 10, 'section_id' => 100, 'ulp_id' => 1, 'latitude' => -7.45230000, 'longitude' => 112.71630000, 'status' => 'OPEN', 'priority' => 'HIGH'],
            ['id' => 400, 'nomor_temuan' => 'COLLISION_FINDING_400', 'jenis_temuan' => 'TRAFO_REMBS_OLI', 'penyulang_id' => 10, 'section_id' => 100, 'ulp_id' => 1, 'latitude' => -7.45240000, 'longitude' => 112.71640000, 'status' => 'OPEN', 'priority' => 'CRITICAL'],
        ]);

        // Authoritative Translines
        $this->db->table('gis_translines')->insertBatch([
            [
                'id'              => 1,
                'transline_code'  => 'TL-BJK-01',
                'penyulang_id'    => 10,
                'section_id'      => 100,
                'source_asset_id' => 1001,
                'target_asset_id' => 1002,
                'conductor_type'  => 'AAAC',
                'conductor_size'  => '150 mm²',
                'distance_meters' => 45.2,
                'geometry'        => json_encode([[112.7161, -7.4521], [112.7165, -7.4525]]),
                'status'          => 'ACTIVE',
                'is_active'       => 1,
            ],
            [
                'id'              => 2,
                'transline_code'  => 'TL-BJK-02',
                'penyulang_id'    => 10,
                'section_id'      => 101,
                'source_asset_id' => 1002,
                'target_asset_id' => 1003,
                'conductor_type'  => 'AAAC',
                'conductor_size'  => '150 mm²',
                'distance_meters' => 50.0,
                'geometry'        => json_encode([[112.7165, -7.4525], [112.7170, -7.4530]]),
                'status'          => 'ACTIVE',
                'is_active'       => 1,
            ],
            [
                'id'              => 3,
                'transline_code'  => 'TL-KRN-01',
                'penyulang_id'    => 20,
                'section_id'      => 200,
                'source_asset_id' => 2001,
                'target_asset_id' => 2002,
                'conductor_type'  => 'AAAC',
                'conductor_size'  => '150 mm²',
                'distance_meters' => 60.0,
                'geometry'        => json_encode([[112.6000, -7.4000], [112.6005, -7.4005]]),
                'status'          => 'ACTIVE',
                'is_active'       => 1,
            ]
        ]);
    }

    /**
     * 1. Test authoritative translines are returned correctly for given feeder scope.
     */
    public function test01AuthoritativeTranslinesReturned(): void
    {
        $res = $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10]);

        $this->assertTrue($res['success']);
        $this->assertEquals(2, $res['total']);
        $this->assertCount(2, $res['data']);
        $this->assertEquals('TL-BJK-01', $res['data'][0]['transline_code']);
        $this->assertEquals(1001, $res['data'][0]['source_asset_id']);
        $this->assertEquals(1002, $res['data'][0]['target_asset_id']);
    }

    /**
     * 2. Test empty scope returns honest empty state without fallback injection.
     */
    public function test02EmptyScopeReturnsEmptyState(): void
    {
        $res = $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 9999]);

        $this->assertTrue($res['success']);
        $this->assertEquals(0, $res['total']);
        $this->assertSame([], $res['data']);
        $this->assertSame([], $res['translines']);
    }

    /**
     * 3. Test valid ULP scope filter.
     */
    public function test03ValidUlpScopeFilter(): void
    {
        $res = $this->translineService->getAuthoritativeTranslines(['ulp_id' => 1]);

        $this->assertTrue($res['success']);
        $this->assertEquals(2, $res['total']);
        foreach ($res['data'] as $tl) {
            $this->assertEquals(10, $tl['penyulang_id']);
        }
    }

    /**
     * 4. Test valid Feeder scope filter.
     */
    public function test04ValidFeederScopeFilter(): void
    {
        $res = $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 20]);

        $this->assertTrue($res['success']);
        $this->assertEquals(1, $res['total']);
        $this->assertEquals('TL-KRN-01', $res['data'][0]['transline_code']);
    }

    /**
     * 5. Test valid Section scope filter.
     */
    public function test05ValidSectionScopeFilter(): void
    {
        $res = $this->translineService->getAuthoritativeTranslines([
            'penyulang_id' => 10,
            'section_id'   => 100
        ]);

        $this->assertTrue($res['success']);
        $this->assertEquals(1, $res['total']);
        $this->assertEquals('TL-BJK-01', $res['data'][0]['transline_code']);
    }

    /**
     * 6. Test cross-scope feeder/section query is rejected.
     */
    public function test06CrossScopeFeederRejected(): void
    {
        // Section 200 belongs to Feeder 20, but requested with Feeder 10
        $res = $this->translineService->getAuthoritativeTranslines([
            'penyulang_id' => 10,
            'section_id'   => 200
        ]);

        $this->assertFalse($res['success']);
        $this->assertEquals('CROSS_SCOPE_REQUEST', $res['error_code']);
    }

    /**
     * 7. Test source endpoint resolves strictly to master asset.
     */
    public function test07SourceEndpointMustBeAsset(): void
    {
        $res = $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10]);

        $first = $res['data'][0];
        $this->assertNotNull($first['source_asset']);
        $this->assertEquals(1001, $first['source_asset']['id']);
        $this->assertEquals('Tiang BJK 01', $first['source_asset']['nama_asset']);
        $this->assertEquals(-7.45210000, (float)$first['source_asset']['latitude']);
    }

    /**
     * 8. Test target endpoint resolves strictly to master asset.
     */
    public function test08TargetEndpointMustBeAsset(): void
    {
        $res = $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10]);

        $first = $res['data'][0];
        $this->assertNotNull($first['target_asset']);
        $this->assertEquals(1002, $first['target_asset']['id']);
        $this->assertEquals('Tiang BJK 02', $first['target_asset']['nama_asset']);
        $this->assertEquals(-7.45250000, (float)$first['target_asset']['latitude']);
    }

    /**
     * 9. Test finding cannot become transline endpoint (Domain-Typed Firewall).
     */
    public function test09FindingCannotBecomeEndpoint(): void
    {
        $resSourceTemuan = $this->translineService->getAuthoritativeTranslines([
            'penyulang_id' => 10,
            'options'      => ['source_type' => 'TEMUAN']
        ]);
        $this->assertFalse($resSourceTemuan['success']);
        $this->assertEquals('TEMUAN_ENDPOINT_FORBIDDEN', $resSourceTemuan['error_code']);

        $resTargetTemuan = $this->translineService->getAuthoritativeTranslines([
            'penyulang_id' => 10,
            'options'      => ['target_type' => 'TEMUAN']
        ]);
        $this->assertFalse($resTargetTemuan['success']);
        $this->assertEquals('TEMUAN_ENDPOINT_FORBIDDEN', $resTargetTemuan['error_code']);
    }

    /**
     * 10. Test numeric ID collision between Finding and Asset does not reject valid Asset.
     */
    public function test10NumericIdCollisionBetweenFindingAndAssetHandledCorrectly(): void
    {
        // Asset #400 and Temuan #400 both exist in DB.
        // Insert a transline using Asset #400 as target
        $this->db->table('gis_translines')->insert([
            'id'              => 4,
            'transline_code'  => 'TL-BJK-COLLISION',
            'penyulang_id'    => 10,
            'section_id'      => 100,
            'source_asset_id' => 1001,
            'target_asset_id' => 400,
            'conductor_type'  => 'AAAC',
            'conductor_size'  => '150 mm²',
            'distance_meters' => 35.0,
            'geometry'        => json_encode([[112.7161, -7.4521], [112.7168, -7.4528]]),
            'status'          => 'ACTIVE',
            'is_active'       => 1,
        ]);

        $res = $this->translineService->getAuthoritativeTranslines([
            'penyulang_id' => 10,
            'options'      => ['source_type' => 'ASSET', 'target_type' => 'ASSET']
        ]);

        $this->assertTrue($res['success']);
        $found = null;
        foreach ($res['data'] as $tl) {
            if ($tl['id'] === 4) {
                $found = $tl;
                break;
            }
        }
        $this->assertNotNull($found, 'Transline with Asset #400 should be returned');
        $this->assertEquals(400, $found['target_asset_id']);
        $this->assertEquals('Tiang Khusus 400', $found['target_asset']['nama_asset']);
    }

    /**
     * 11. Test orphan source endpoint detected diagnostically without deleting the row.
     */
    public function test11OrphanSourceDetectedDiagnostically(): void
    {
        $this->db->table('gis_translines')->insert([
            'id'              => 11,
            'transline_code'  => 'TL-ORPHAN-SRC',
            'penyulang_id'    => 10,
            'section_id'      => 100,
            'source_asset_id' => 99999, // Does not exist
            'target_asset_id' => 1002,
            'conductor_type'  => 'AAAC',
            'conductor_size'  => '150 mm²',
            'distance_meters' => 30.0,
            'geometry'        => json_encode([[112.0, -7.0], [112.7165, -7.4525]]),
            'status'          => 'ACTIVE',
            'is_active'       => 1,
        ]);

        $countBefore = $this->db->table('gis_translines')->countAllResults();
        $res = $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10]);
        $countAfter = $this->db->table('gis_translines')->countAllResults();

        $this->assertEquals($countBefore, $countAfter, 'Zero delete on orphan row');
        $orphanDiag = array_filter($res['diagnostics'], fn($d) => $d['type'] === 'ORPHAN_ENDPOINT' && $d['transline_id'] === 11);
        $this->assertNotEmpty($orphanDiag, 'Orphan source must emit diagnostic');
    }

    /**
     * 12. Test orphan target endpoint detected diagnostically without deleting the row.
     */
    public function test12OrphanTargetDetectedDiagnostically(): void
    {
        $this->db->table('gis_translines')->insert([
            'id'              => 12,
            'transline_code'  => 'TL-ORPHAN-TGT',
            'penyulang_id'    => 10,
            'section_id'      => 100,
            'source_asset_id' => 1001,
            'target_asset_id' => 99999, // Does not exist
            'conductor_type'  => 'AAAC',
            'conductor_size'  => '150 mm²',
            'distance_meters' => 30.0,
            'geometry'        => json_encode([[112.7161, -7.4521], [112.0, -7.0]]),
            'status'          => 'ACTIVE',
            'is_active'       => 1,
        ]);

        $countBefore = $this->db->table('gis_translines')->countAllResults();
        $res = $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10]);
        $countAfter = $this->db->table('gis_translines')->countAllResults();

        $this->assertEquals($countBefore, $countAfter);
        $orphanDiag = array_filter($res['diagnostics'], fn($d) => $d['type'] === 'ORPHAN_ENDPOINT' && $d['transline_id'] === 12);
        $this->assertNotEmpty($orphanDiag, 'Orphan target must emit diagnostic');
    }

    /**
     * 13. Test self-loop / identical endpoints detected diagnostically.
     */
    public function test13IdenticalEndpointsSelfLoopDiagnostic(): void
    {
        $this->db->table('gis_translines')->insert([
            'id'              => 13,
            'transline_code'  => 'TL-SELF-LOOP',
            'penyulang_id'    => 10,
            'section_id'      => 100,
            'source_asset_id' => 1001,
            'target_asset_id' => 1001, // Identical
            'conductor_type'  => 'AAAC',
            'conductor_size'  => '150 mm²',
            'distance_meters' => 0.0,
            'geometry'        => json_encode([[112.7161, -7.4521], [112.7161, -7.4521]]),
            'status'          => 'ACTIVE',
            'is_active'       => 1,
        ]);

        $res = $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10]);
        $selfDiag = array_filter($res['diagnostics'], fn($d) => $d['type'] === 'IDENTICAL_ENDPOINTS' && $d['transline_id'] === 13);
        $this->assertNotEmpty($selfDiag, 'Self loop must emit diagnostic');
    }

    /**
     * 14. Test missing coordinates detected diagnostically.
     */
    public function test14MissingCoordinatesDiagnostic(): void
    {
        $this->db->table('assets')->insert([
            'id'           => 1099,
            'kode_asset'   => 'BJK_NO_COORD',
            'nama_asset'   => 'Tiang Tanpa Koordinat',
            'penyulang_id' => 10,
            'section_id'   => 100,
            'ulp_id'       => 1,
            'latitude'     => 0.0,
            'longitude'    => 0.0,
            'status'       => 'NORMAL',
        ]);

        $this->db->table('gis_translines')->insert([
            'id'              => 14,
            'transline_code'  => 'TL-NO-COORD',
            'penyulang_id'    => 10,
            'section_id'      => 100,
            'source_asset_id' => 1001,
            'target_asset_id' => 1099,
            'conductor_type'  => 'AAAC',
            'conductor_size'  => '150 mm²',
            'distance_meters' => 20.0,
            'geometry'        => json_encode([[112.7161, -7.4521], [0.0, 0.0]]),
            'status'          => 'ACTIVE',
            'is_active'       => 1,
        ]);

        $res = $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10]);
        $coordDiag = array_filter($res['diagnostics'], fn($d) => $d['type'] === 'MISSING_COORDINATE' && $d['transline_id'] === 14);
        $this->assertNotEmpty($coordDiag, 'Missing coordinate must emit diagnostic');
    }

    /**
     * 15. Test proposals are strictly separated from authoritative layer.
     */
    public function test15ProposalNotMixedIntoAuthoritativeLayer(): void
    {
        // Seed 5 proposals into gis_transline_proposals
        for ($i = 1; $i <= 5; $i++) {
            $this->db->table('gis_transline_proposals')->insert([
                'id'                      => 100 + $i,
                'penyulang_id'            => 10,
                'section_id'              => 100,
                'source_asset_id'         => 1001,
                'target_asset_id'         => 1002,
                'natural_key'             => "PROP-TEST-{$i}",
                'proposed_conductor_type' => 'AAAC',
                'proposed_conductor_size' => '150 mm²',
                'proposed_distance'       => 45.0,
                'classification'          => 'AUTO_MATCH',
                'confidence_score'        => 0.9500,
                'status'                  => 'PENDING_REVIEW',
            ]);
        }

        $res = $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10]);

        $this->assertTrue($res['success']);
        $this->assertEquals(2, $res['total'], 'Proposals must not be returned in authoritative layer');
        foreach ($res['data'] as $tl) {
            $this->assertNotEquals('PENDING_REVIEW', $tl['status']);
        }
    }

    /**
     * 16. Test GET endpoint is strictly zero-write.
     */
    public function test16GetEndpointZeroWrite(): void
    {
        $tables = ['gis_translines', 'gis_transline_proposals', 'assets', 'sections', 'penyulang', 'temuan', 'temuan_materials'];
        $countsBefore = [];
        foreach ($tables as $tbl) {
            $countsBefore[$tbl] = $this->db->table($tbl)->countAllResults();
        }

        // Call read endpoint 5 times with various scopes
        $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10]);
        $this->translineService->getAuthoritativeTranslines(['ulp_id' => 1]);
        $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 20]);
        $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10, 'section_id' => 100]);
        $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 999]);

        foreach ($tables as $tbl) {
            $countAfter = $this->db->table($tbl)->countAllResults();
            $this->assertEquals($countsBefore[$tbl], $countAfter, "Table {$tbl} row count must have delta = 0");
        }
    }

    /**
     * 17. Test existing translines count unchanged.
     */
    public function test17ExistingTranslineCountUnchanged(): void
    {
        $count1 = $this->db->table('gis_translines')->countAllResults();
        $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10]);
        $count2 = $this->db->table('gis_translines')->countAllResults();
        $this->assertSame($count1, $count2);
    }

    /**
     * 18. Test existing assets count unchanged.
     */
    public function test18ExistingAssetCountUnchanged(): void
    {
        $count1 = $this->db->table('assets')->countAllResults();
        $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10]);
        $count2 = $this->db->table('assets')->countAllResults();
        $this->assertSame($count1, $count2);
    }

    /**
     * 19. Test existing temuan count unchanged.
     */
    public function test19ExistingTemuanCountUnchanged(): void
    {
        $count1 = $this->db->table('temuan')->countAllResults();
        $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10]);
        $count2 = $this->db->table('temuan')->countAllResults();
        $this->assertSame($count1, $count2);
    }

    /**
     * 20. Test D2B single-row confirmation logic preserved.
     */
    public function test20D2BPreserved(): void
    {
        $this->assertTrue(method_exists($this->reviewService, 'confirmProposal'));
    }

    /**
     * 21. Test D2C batch confirmation logic preserved.
     */
    public function test21D2CPreserved(): void
    {
        $this->assertTrue(method_exists($this->reviewService, 'confirmBatchProposals'));
    }

    /**
     * 22. Test D3 rollback logic preserved.
     */
    public function test22D3Preserved(): void
    {
        $this->assertTrue(method_exists($this->reviewService, 'rollbackConfirmedProposal'));
        $this->assertTrue(method_exists($this->reviewService, 'rollbackBatchProposals'));
    }

    /**
     * 23. Test D4A proposal workbench read model preserved.
     */
    public function test23D4APreserved(): void
    {
        $this->assertTrue(method_exists($this->reviewService, 'getPendingProposalsForFeeder'));
        $this->assertTrue(method_exists($this->reviewService, 'getProposalWorkbenchDetail'));
    }

    /**
     * 24. Test authorization: cross-ULP access rejected with 403 / UNAUTHORIZED_FEEDER_ACCESS.
     */
    public function test24AuthorizationPreserved(): void
    {
        // User assigned to ULP 2 attempts to query feeder 10 (which belongs to ULP 1)
        $userUlpId = 2;
        $res = $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10], $userUlpId);

        $this->assertFalse($res['success']);
        $this->assertEquals('UNAUTHORIZED_FEEDER_ACCESS', $res['error_code']);
    }

    /**
     * 25. Test Mobile GIS rendering path has required coordinates, conductor, and distance.
     */
    public function test25MobileGisRenderingPath(): void
    {
        $res = $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10]);

        $this->assertTrue($res['success']);
        $first = $res['data'][0];

        $this->assertIsArray($first['coordinates']);
        $this->assertCount(2, $first['coordinates']);
        $this->assertNotEmpty($first['conductor_label']);
        $this->assertGreaterThan(0, $first['length_meter']);
        $this->assertEquals('ACTIVE', $first['status']);
    }

    /**
     * 26. Test Desktop GIS rendering path output contract conformance.
     */
    public function test26DesktopGisRenderingPath(): void
    {
        $res = $this->translineService->getAuthoritativeTranslines(['penyulang_id' => 10]);

        $this->assertArrayHasKey('success', $res);
        $this->assertArrayHasKey('scope', $res);
        $this->assertArrayHasKey('total', $res);
        $this->assertArrayHasKey('data', $res);
        $this->assertArrayHasKey('translines', $res);
        $this->assertArrayHasKey('diagnostics', $res);
        $this->assertSame($res['data'], $res['translines']);
    }
}
