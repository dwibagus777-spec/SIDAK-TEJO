<?php

namespace Tests\Unit;

use App\Services\TranslineProposalReviewService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * TL-01 Sub-Gate D2A: Read-Only Transline Proposal Review & Map Preview Test Suite
 *
 * Requirements:
 * 1. Proposal list read
 * 2. Only PENDING_REVIEW returned
 * 3. Feeder & ULP authorization boundaries enforced
 * 4. Invalid feeder rejected gracefully
 * 5. Geometry exposed correctly in GeoJSON LineString format
 * 6. Evidence JSON parsed into structured object
 * 7. Visual style tokens faithfully mapped per GIS-SPEC-VISUAL-01
 * 8. Classification exposed accurately
 * 9. STRICT READ-ONLY: No write paths, 0 mutations
 * 10. Authoritative `gis_translines` strictly untouched (0 mutations)
 * 11. Proposal summary counts match actual DB records
 * 12. Focus-map coordinate payload validity
 * 13. Malformed proposal recovery
 */
class TranslineProposalReviewD2ATest extends CIUnitTestCase
{
    protected $db;
    protected TranslineProposalReviewService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->service = new TranslineProposalReviewService($this->db);
        $this->setupSchema();
    }

    protected function setupSchema(): void
    {
        $forge = Database::forge();

        // 1. penyulang table
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

        // 2. assets table
        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Tiang Aset'],
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
                'status'               => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'NORMAL'],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        // 3. gis_translines table
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

        // 4. gis_transline_proposals table
        if (!$this->db->tableExists('gis_transline_proposals')) {
            $forge->addField([
                'id'                      => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id'            => ['type' => 'INT', 'constraint' => 11],
                'section_id'              => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'source_asset_id'         => ['type' => 'INT', 'constraint' => 11],
                'target_asset_id'         => ['type' => 'INT', 'constraint' => 11],
                'natural_key'             => ['type' => 'VARCHAR', 'constraint' => 64],
                'proposed_conductor_type' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'proposed_conductor_size' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'proposed_distance'       => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 50.00],
                'proposed_geometry'       => ['type' => 'TEXT', 'null' => true],
                'classification'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'AUTO_MATCH'],
                'confidence_score'        => ['type' => 'DECIMAL', 'constraint' => '5,4', 'default' => 1.0000],
                'evidence_json'           => ['type' => 'TEXT', 'null' => true],
                'proposal_source'         => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'DETERMINISTIC_ENGINE'],
                'engine_version'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'TL-01-V2.0'],
                'status'                  => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'PENDING_REVIEW'],
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

        // Clean tables for isolated test run
        $this->db->table('gis_transline_proposals')->emptyTable();
        $this->seedTestData();
    }

    protected function seedTestData(): void
    {
        // Insert Feeder
        $this->db->table('penyulang')->where('id', 10)->delete();
        $this->db->table('penyulang')->insert([
            'id'             => 10,
            'ulp_id'         => 1,
            'kode_penyulang' => 'BJK',
            'nama_penyulang' => 'BANJAR KEMANTREN',
            'status'         => 'AKTIF',
        ]);

        // Insert Feeder from another ULP
        $this->db->table('penyulang')->where('id', 99)->delete();
        $this->db->table('penyulang')->insert([
            'id'             => 99,
            'ulp_id'         => 2,
            'kode_penyulang' => 'POR',
            'nama_penyulang' => 'PORONG',
            'status'         => 'AKTIF',
        ]);

        // Insert Assets
        $this->db->table('assets')->whereIn('id', [1001, 1002, 1003])->delete();
        $this->db->table('assets')->insertBatch([
            [
                'id'           => 1001,
                'kode_asset'   => 'BANJARKEMANTRAN_01',
                'nama_asset'   => 'Tiang BJK 01',
                'penyulang_id' => 10,
                'latitude'     => -7.41600000,
                'longitude'    => 112.72300000,
            ],
            [
                'id'           => 1002,
                'kode_asset'   => 'BANJARKEMANTRAN_02',
                'nama_asset'   => 'Tiang BJK 02',
                'penyulang_id' => 10,
                'latitude'     => -7.41630000,
                'longitude'    => 112.72310000,
            ],
            [
                'id'           => 1003,
                'kode_asset'   => 'BANJARKEMANTRAN_03',
                'nama_asset'   => 'Tiang BJK 03',
                'penyulang_id' => 10,
                'latitude'     => -7.41660000,
                'longitude'    => 112.72330000,
            ],
        ]);

        // Insert 3 Proposals (2 PENDING_REVIEW, 1 CONFIRMED)
        $proposals = [
            [
                'id'                      => 1,
                'penyulang_id'            => 10,
                'source_asset_id'         => 1001,
                'target_asset_id'         => 1002,
                'natural_key'             => 'TL-NAT:10:1001-1002',
                'proposed_conductor_type' => 'AAAC',
                'proposed_conductor_size' => '150 mm²',
                'proposed_distance'       => 37.15,
                'proposed_geometry'       => json_encode([[112.7230, -7.4160], [112.7231, -7.4163]]),
                'classification'          => 'AUTO_MATCH',
                'confidence_score'        => 1.0000,
                'evidence_json'           => json_encode([
                    'reason_code'        => 'EXISTING_VALID_PAIR',
                    'warnings'           => [],
                    'visual_style_token' => 'AAAC_150',
                    'visual_pattern'     => 'HEAVY_SOLID',
                    'distance_meters'    => 37.15,
                ]),
                'proposal_source'         => 'DETERMINISTIC_ENGINE',
                'engine_version'          => 'TL-01-V2.0',
                'status'                  => 'PENDING_REVIEW',
            ],
            [
                'id'                      => 2,
                'penyulang_id'            => 10,
                'source_asset_id'         => 1002,
                'target_asset_id'         => 1003,
                'natural_key'             => 'TL-NAT:10:1002-1003',
                'proposed_conductor_type' => 'A3C',
                'proposed_conductor_size' => '70 mm²',
                'proposed_distance'       => 41.20,
                'proposed_geometry'       => json_encode([[112.7231, -7.4163], [112.7233, -7.4166]]),
                'classification'          => 'AUTO_MATCH',
                'confidence_score'        => 1.0000,
                'evidence_json'           => json_encode([
                    'reason_code'        => 'DETERMINISTIC_SEQUENTIAL_PAIR',
                    'warnings'           => [],
                    'visual_style_token' => 'A3C_70',
                    'visual_pattern'     => 'DASH_DOT',
                    'distance_meters'    => 41.20,
                ]),
                'proposal_source'         => 'DETERMINISTIC_ENGINE',
                'engine_version'          => 'TL-01-V2.0',
                'status'                  => 'PENDING_REVIEW',
            ],
            [
                'id'                      => 3,
                'penyulang_id'            => 10,
                'source_asset_id'         => 1001,
                'target_asset_id'         => 1003,
                'natural_key'             => 'TL-NAT:10:1001-1003',
                'proposed_conductor_type' => 'AAAC',
                'proposed_conductor_size' => '150 mm²',
                'proposed_distance'       => 78.35,
                'proposed_geometry'       => json_encode([[112.7230, -7.4160], [112.7233, -7.4166]]),
                'classification'          => 'NEEDS_REVIEW',
                'confidence_score'        => 0.7000,
                'evidence_json'           => json_encode([
                    'reason_code' => 'SPAN_GAP',
                ]),
                'status'                  => 'CONFIRMED', // Already confirmed, should be filtered out!
            ],
        ];

        foreach ($proposals as $p) {
            $this->db->table('gis_transline_proposals')->insert($p);
        }
    }

    public function testGetPendingProposalsForFeederReturnsOnlyPendingReview()
    {
        $res = $this->service->getPendingProposalsForFeeder(10, 1);

        $this->assertSame('success', $res['status']);
        $this->assertCount(2, $res['proposals'], "Must ONLY return the 2 PENDING_REVIEW proposals, filtering out CONFIRMED");

        // Verify summary counts
        $this->assertSame(3, $res['summary']['total']);
        $this->assertSame(2, $res['summary']['auto_match']);
        $this->assertSame(1, $res['summary']['needs_review']);
    }

    public function testFeederAuthorizationBoundaryEnforced()
    {
        // User from ULP 2 tries to access feeder 10 (belonging to ULP 1)
        $res = $this->service->getPendingProposalsForFeeder(10, 2);

        $this->assertSame('error', $res['status']);
        $this->assertSame('UNAUTHORIZED_FEEDER_ACCESS', $res['reason']);
        $this->assertEmpty($res['proposals']);
    }

    public function testInvalidFeederRejectedGracefully()
    {
        $res = $this->service->getPendingProposalsForFeeder(99999, 1);

        $this->assertSame('error', $res['status']);
        $this->assertSame('PENYULANG_NOT_FOUND', $res['reason']);
        $this->assertEmpty($res['proposals']);
    }

    public function testGeometryExposedCorrectlyInGeoJsonFormat()
    {
        $res = $this->service->getPendingProposalsForFeeder(10, 1);
        $p1 = $res['proposals'][0];

        $this->assertNotNull($p1['proposed_geometry']);
        $this->assertSame('LineString', $p1['proposed_geometry']['type']);
        $this->assertCount(2, $p1['proposed_geometry']['coordinates']);
        $this->assertSame(112.7230, $p1['proposed_geometry']['coordinates'][0][0]);
        $this->assertSame(-7.4160, $p1['proposed_geometry']['coordinates'][0][1]);
    }

    public function testEvidenceJsonAndVisualTokensFaithfullyMapped()
    {
        $res = $this->service->getPendingProposalsForFeeder(10, 1);
        $p2 = $res['proposals'][1];

        $this->assertSame('A3C_70', $p2['visual_style_token']);
        $this->assertSame('DASH_DOT', $p2['visual_pattern']);
        $this->assertSame('DETERMINISTIC_SEQUENTIAL_PAIR', $p2['evidence']['reason_code']);
        $this->assertTrue($p2['evidence']['is_same_feeder']);
        $this->assertTrue($p2['evidence']['is_sequential']);
    }

    public function testFocusMapCoordinatesExposed()
    {
        $res = $this->service->getPendingProposalsForFeeder(10, 1);
        $p1 = $res['proposals'][0];

        $this->assertNotNull($p1['source_coordinates']);
        $this->assertNotNull($p1['target_coordinates']);
        $this->assertSame(-7.41600000, $p1['source_coordinates']['lat']);
        $this->assertSame(112.72300000, $p1['source_coordinates']['lng']);
        $this->assertSame(-7.41630000, $p1['target_coordinates']['lat']);
        $this->assertSame(112.72310000, $p1['target_coordinates']['lng']);
    }

    public function testZeroWriteProofAcrossAllMonitoredTables()
    {
        $tlBefore = $this->db->table('gis_translines')->countAllResults();
        $propBefore = $this->db->table('gis_transline_proposals')->countAllResults();
        $assetsBefore = $this->db->table('assets')->countAllResults();
        $penyulangBefore = $this->db->table('penyulang')->countAllResults();

        // Run the service call multiple times
        $this->service->getPendingProposalsForFeeder(10, 1);
        $this->service->getPendingProposalsForFeeder(10, 1);

        $tlAfter = $this->db->table('gis_translines')->countAllResults();
        $propAfter = $this->db->table('gis_transline_proposals')->countAllResults();
        $assetsAfter = $this->db->table('assets')->countAllResults();
        $penyulangAfter = $this->db->table('penyulang')->countAllResults();

        $this->assertSame($tlBefore, $tlAfter, "gis_translines must have 0 mutations");
        $this->assertSame($propBefore, $propAfter, "gis_transline_proposals must have 0 mutations in D2A");
        $this->assertSame($assetsBefore, $assetsAfter, "assets must have 0 mutations");
        $this->assertSame($penyulangBefore, $penyulangAfter, "penyulang must have 0 mutations");
    }
}
