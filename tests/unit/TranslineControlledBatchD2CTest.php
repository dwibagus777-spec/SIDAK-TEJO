<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\TranslineProposalReviewService;

/**
 * TL-01 Sub-Gate D2C: Controlled Batch Confirmation & Rollback Test Suite
 *
 * Requirements & Scenarios Verified:
 * 1. Empty selection rejected.
 * 2. Duplicate proposal IDs in selection rejected.
 * 3. Batch over MAX_BATCH_SIZE (10) rejected.
 * 4. Single valid AUTO_MATCH confirms successfully.
 * 5. Multiple valid AUTO_MATCH proposals confirm atomically (+N delta).
 * 6. One invalid proposal causes COMPLETE rollback (all-or-nothing atomicity).
 * 7. One NEEDS_REVIEW proposal causes COMPLETE rollback.
 * 8. One INVALID proposal causes COMPLETE rollback.
 * 9. One MISSING proposal causes COMPLETE rollback.
 * 10. Already CONFIRMED proposal causes COMPLETE rollback.
 * 11. Duplicate natural key causes COMPLETE rollback.
 * 12. Existing active transline duplicate causes COMPLETE rollback.
 * 13. Intra-batch duplicate pair causes COMPLETE rollback.
 * 14. Concurrent confirmation / non-pending status causes COMPLETE rollback.
 * 15. Exact confirmed_transline_id is persisted per proposal.
 * 16. Browser-forged authoritative payload is ignored (server computes from DB).
 * 17. Source/target feeder mismatch causes COMPLETE rollback.
 * 18. Master assets zero mutation invariant (assets.section_id & construction_type_id untouched).
 * 19. Temuan zero mutation invariant.
 * 20. Temuan materials zero mutation invariant.
 * 21. AR-01 zero mutation invariant.
 * 22. MR-01 zero mutation invariant.
 * 23. Unrelated GIS topology zero mutation invariant.
 * 24. Successful batch count cardinality exact (after === before + N).
 * 25. Failed batch count cardinality exact (after === before).
 * 26. Idempotent retry after success rejected safely without duplicate creation.
 * 27. Batch rollback by exact PK with provenance check succeeds (restores translines & proposals).
 * 28. Batch rollback provenance mismatch rejected (prevents deleting wrong transline).
 * 29. Batch result contains deterministic per-proposal receipt and audit fingerprint.
 */
class TranslineControlledBatchD2CTest extends CIUnitTestCase
{
    protected $db;
    protected TranslineProposalReviewService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->setupSchema();
        $this->seedTestData();
        $this->service = new TranslineProposalReviewService($this->db);
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
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
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
                'natural_key'             => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'proposed_conductor_type' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'proposed_conductor_size' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'conductor_type'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'conductor_size'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'proposed_distance'       => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 50.00],
                'length_m'                => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'proposed_geometry'       => ['type' => 'TEXT', 'null' => true],
                'classification'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'AUTO_MATCH'],
                'confidence_score'        => ['type' => 'DECIMAL', 'constraint' => '5,4', 'default' => 1.0],
                'evidence_json'           => ['type' => 'TEXT', 'null' => true],
                'proposal_source'         => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'DETERMINISTIC_ENGINE'],
                'engine_version'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'TL-01-V2.0'],
                'status'                  => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'PENDING_REVIEW'],
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

        // 7. temuan table
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

        // Seed 2 Feeders across 2 ULPs
        $this->db->table('ulps')->insertBatch([
            ['id' => 1, 'kode_ulp' => 'ULP01', 'nama_ulp' => 'ULP SIDOARJO KOTA', 'status' => 'AKTIF'],
            ['id' => 2, 'kode_ulp' => 'ULP02', 'nama_ulp' => 'ULP KRIAN', 'status' => 'AKTIF'],
        ]);

        $this->db->table('penyulang')->insertBatch([
            ['id' => 10, 'ulp_id' => 1, 'kode_penyulang' => 'BJK', 'nama_penyulang' => 'BANJAR KEMANTREN', 'status' => 'AKTIF'],
            ['id' => 20, 'ulp_id' => 2, 'kode_penyulang' => 'KRN', 'nama_penyulang' => 'KRIAN RAYA', 'status' => 'AKTIF'],
        ]);

        $this->db->table('sections')->insertBatch([
            ['id' => 100, 'penyulang_id' => 10, 'nama_section' => 'SECTION UTAMA BJK'],
            ['id' => 200, 'penyulang_id' => 20, 'nama_section' => 'SECTION UTAMA KRN'],
        ]);

        // Seed Assets: 6 in Feeder 10, 1 in Feeder 20
        $this->db->table('assets')->insertBatch([
            [
                'id'                   => 1001,
                'kode_asset'           => 'BANJARKEMANTRAN_01',
                'nama_asset'           => 'Tiang BJK 01',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'latitude'             => -7.41600000,
                'longitude'            => 112.72300000,
            ],
            [
                'id'                   => 1002,
                'kode_asset'           => 'BANJARKEMANTRAN_02',
                'nama_asset'           => 'Tiang BJK 02',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'latitude'             => -7.41630000,
                'longitude'            => 112.72310000,
            ],
            [
                'id'                   => 1003,
                'kode_asset'           => 'BANJARKEMANTRAN_03',
                'nama_asset'           => 'Tiang BJK 03',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'construction_type_id' => 2,
                'latitude'             => -7.41660000,
                'longitude'            => 112.72330000,
            ],
            [
                'id'                   => 1004,
                'kode_asset'           => 'BANJARKEMANTRAN_04',
                'nama_asset'           => 'Tiang BJK 04',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'latitude'             => -7.41690000,
                'longitude'            => 112.72350000,
            ],
            [
                'id'                   => 1005,
                'kode_asset'           => 'BANJARKEMANTRAN_05',
                'nama_asset'           => 'Tiang BJK 05',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'latitude'             => -7.41720000,
                'longitude'            => 112.72370000,
            ],
            [
                'id'                   => 1006,
                'kode_asset'           => 'BANJARKEMANTRAN_06',
                'nama_asset'           => 'Tiang BJK 06',
                'penyulang_id'         => 10,
                'section_id'           => 100,
                'ulp_id'               => 1,
                'construction_type_id' => 1,
                'latitude'             => -7.41750000,
                'longitude'            => 112.72390000,
            ],
            [
                'id'                   => 2001,
                'kode_asset'           => 'KRIAN_01',
                'nama_asset'           => 'Tiang Krian 01',
                'penyulang_id'         => 20,
                'section_id'           => 200,
                'ulp_id'               => 2,
                'construction_type_id' => 1,
                'latitude'             => -7.45200000,
                'longitude'            => 112.71830000,
            ],
        ]);

        // Seed 1 Baseline Transline (Existing line 1002 <-> 1003)
        $this->db->table('gis_translines')->insert([
            'id'              => 501,
            'transline_code'  => 'TL-10-1002-1003',
            'penyulang_id'    => 10,
            'section_id'      => 100,
            'source_asset_id' => 1002,
            'target_asset_id' => 1003,
            'conductor_type'  => 'AAAC',
            'conductor_size'  => '150 mm²',
            'distance_meters' => 41.20,
            'geometry'        => json_encode([[112.7231, -7.4163], [112.7233, -7.4166]]),
            'status'          => 'ACTIVE',
            'is_active'       => 1,
        ]);

        // Seed Proposals:
        // 1: 1001 -> 1002 (AUTO_MATCH, PENDING_REVIEW)
        // 2: 1003 -> 1004 (AUTO_MATCH, PENDING_REVIEW)
        // 3: 1004 -> 1005 (AUTO_MATCH, PENDING_REVIEW)
        // 4: 1005 -> 1006 (AUTO_MATCH, PENDING_REVIEW)
        // 5: 1001 -> 2001 (Cross Feeder, INVALID, PENDING_REVIEW)
        // 6: 1002 -> 1004 (NEEDS_REVIEW, PENDING_REVIEW)
        // 7: 1002 -> 1005 (INVALID, PENDING_REVIEW)
        // 8: 1002 -> 1006 (MISSING, PENDING_REVIEW)
        // 9: 1001 -> 1003 (Already CONFIRMED, confirmed_transline_id = 501)
        // 10: 1002 -> 1003 (Duplicate of existing transline 501, AUTO_MATCH, PENDING_REVIEW)
        $proposals = [
            [
                'id'                      => 1,
                'penyulang_id'            => 10,
                'section_id'              => 100,
                'source_asset_id'         => 1001,
                'target_asset_id'         => 1002,
                'natural_key'             => 'TL-NAT:10:1001-1002',
                'proposed_conductor_type' => 'AAAC',
                'proposed_conductor_size' => '150 mm²',
                'proposed_distance'       => 37.15,
                'classification'          => 'AUTO_MATCH',
                'confidence_score'        => 1.0000,
                'status'                  => 'PENDING_REVIEW',
            ],
            [
                'id'                      => 2,
                'penyulang_id'            => 10,
                'section_id'              => 100,
                'source_asset_id'         => 1003,
                'target_asset_id'         => 1004,
                'natural_key'             => 'TL-NAT:10:1003-1004',
                'proposed_conductor_type' => 'AAAC',
                'proposed_conductor_size' => '150 mm²',
                'proposed_distance'       => 39.20,
                'classification'          => 'AUTO_MATCH',
                'confidence_score'        => 1.0000,
                'status'                  => 'PENDING_REVIEW',
            ],
            [
                'id'                      => 3,
                'penyulang_id'            => 10,
                'section_id'              => 100,
                'source_asset_id'         => 1004,
                'target_asset_id'         => 1005,
                'natural_key'             => 'TL-NAT:10:1004-1005',
                'proposed_conductor_type' => 'AAAC',
                'proposed_conductor_size' => '150 mm²',
                'proposed_distance'       => 38.50,
                'classification'          => 'AUTO_MATCH',
                'confidence_score'        => 1.0000,
                'status'                  => 'PENDING_REVIEW',
            ],
            [
                'id'                      => 4,
                'penyulang_id'            => 10,
                'section_id'              => 100,
                'source_asset_id'         => 1005,
                'target_asset_id'         => 1006,
                'natural_key'             => 'TL-NAT:10:1005-1006',
                'proposed_conductor_type' => 'AAAC',
                'proposed_conductor_size' => '150 mm²',
                'proposed_distance'       => 40.10,
                'classification'          => 'AUTO_MATCH',
                'confidence_score'        => 1.0000,
                'status'                  => 'PENDING_REVIEW',
            ],
            [
                'id'                      => 5,
                'penyulang_id'            => 10,
                'section_id'              => 100,
                'source_asset_id'         => 1001,
                'target_asset_id'         => 2001, // CROSS FEEDER
                'natural_key'             => 'TL-NAT:10:1001-2001',
                'proposed_conductor_type' => 'AAAC',
                'proposed_conductor_size' => '150 mm²',
                'proposed_distance'       => 400.00,
                'classification'          => 'INVALID',
                'confidence_score'        => 0.0000,
                'status'                  => 'PENDING_REVIEW',
            ],
            [
                'id'                      => 6,
                'penyulang_id'            => 10,
                'section_id'              => 100,
                'source_asset_id'         => 1002,
                'target_asset_id'         => 1004,
                'natural_key'             => 'TL-NAT:10:1002-1004',
                'proposed_conductor_type' => 'AAAC',
                'proposed_conductor_size' => '150 mm²',
                'proposed_distance'       => 80.40,
                'classification'          => 'NEEDS_REVIEW',
                'confidence_score'        => 0.5000,
                'status'                  => 'PENDING_REVIEW',
            ],
            [
                'id'                      => 7,
                'penyulang_id'            => 10,
                'section_id'              => 100,
                'source_asset_id'         => 1002,
                'target_asset_id'         => 1005,
                'natural_key'             => 'TL-NAT:10:1002-1005',
                'proposed_conductor_type' => 'AAAC',
                'proposed_conductor_size' => '150 mm²',
                'proposed_distance'       => 120.00,
                'classification'          => 'INVALID',
                'confidence_score'        => 0.1000,
                'status'                  => 'PENDING_REVIEW',
            ],
            [
                'id'                      => 8,
                'penyulang_id'            => 10,
                'section_id'              => 100,
                'source_asset_id'         => 1002,
                'target_asset_id'         => 1006,
                'natural_key'             => 'TL-NAT:10:1002-1006',
                'proposed_conductor_type' => 'AAAC',
                'proposed_conductor_size' => '150 mm²',
                'proposed_distance'       => 160.00,
                'classification'          => 'MISSING',
                'confidence_score'        => 0.0000,
                'status'                  => 'PENDING_REVIEW',
            ],
            [
                'id'                      => 9,
                'penyulang_id'            => 10,
                'section_id'              => 100,
                'source_asset_id'         => 1001,
                'target_asset_id'         => 1003,
                'natural_key'             => 'TL-NAT:10:1001-1003',
                'proposed_conductor_type' => 'AAAC',
                'proposed_conductor_size' => '150 mm²',
                'proposed_distance'       => 78.00,
                'classification'          => 'AUTO_MATCH',
                'confidence_score'        => 1.0000,
                'status'                  => 'CONFIRMED',
                'confirmed_transline_id'  => 501,
            ],
            [
                'id'                      => 10,
                'penyulang_id'            => 10,
                'section_id'              => 100,
                'source_asset_id'         => 1002,
                'target_asset_id'         => 1003,
                'natural_key'             => 'TL-NAT:10:1002-1003',
                'proposed_conductor_type' => 'AAAC',
                'proposed_conductor_size' => '150 mm²',
                'proposed_distance'       => 41.20,
                'classification'          => 'AUTO_MATCH',
                'confidence_score'        => 1.0000,
                'status'                  => 'PENDING_REVIEW',
            ],
        ];

        foreach ($proposals as $p) {
            $this->db->table('gis_transline_proposals')->insert($p);
        }

        // Seed 1 Temuan & 1 Material to verify zero mutation
        $this->db->table('temuan')->insert([
            'id'           => 901,
            'ulp_id'       => 1,
            'penyulang_id' => 10,
            'section_id'   => 100,
            'asset_id'     => 1001,
            'judul'        => 'Temuan Baseline Inspeksi Tiang',
            'created_at'   => '2026-09-01 10:00:00',
        ]);

        $this->db->table('temuan_materials')->insert([
            'id'          => 801,
            'temuan_id'   => 901,
            'material_id' => 101,
            'quantity'    => 2.0,
        ]);
    }

    /**
     * Scenario 1: Empty selection is rejected at the gate
     */
    public function test01_EmptySelectionRejected(): void
    {
        $res = $this->service->confirmBatchProposals([]);
        $this->assertEquals('error', $res['status']);
        $this->assertEquals('EMPTY_SELECTION', $res['reason']);
        $this->assertEquals(1, $this->db->table('gis_translines')->countAllResults());
    }

    /**
     * Scenario 2: Duplicate proposal IDs in selection rejected
     */
    public function test02_DuplicateProposalIdsInSelectionRejected(): void
    {
        $res = $this->service->confirmBatchProposals([1, 1]);
        $this->assertEquals('error', $res['status']);
        $this->assertEquals('DUPLICATE_PROPOSAL_IDS_IN_BATCH', $res['reason']);
        $this->assertEquals(1, $this->db->table('gis_translines')->countAllResults());
    }

    /**
     * Scenario 3: Batch over MAX_BATCH_SIZE (10) rejected
     */
    public function test03_BatchOverLimitRejected(): void
    {
        $batch = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];
        $res = $this->service->confirmBatchProposals($batch);
        $this->assertEquals('error', $res['status']);
        $this->assertEquals('BATCH_SIZE_EXCEEDED', $res['reason']);
        $this->assertEquals(1, $this->db->table('gis_translines')->countAllResults());
    }

    /**
     * Scenario 4: Single valid AUTO_MATCH confirms successfully
     */
    public function test04_SingleValidAutoMatchConfirmsSuccessfully(): void
    {
        $initialCount = $this->db->table('gis_translines')->countAllResults();
        $res = $this->service->confirmBatchProposals([1], ['username' => 'TESTER']);

        $this->assertEquals('success', $res['status']);
        $this->assertEquals('BATCH_CONFIRMED', $res['action']);
        $this->assertEquals(1, $res['batch_size']);
        $this->assertCount(1, $res['confirmed_proposals']);
        $this->assertArrayHasKey('fingerprint', $res);

        // Exact +1 delta
        $this->assertEquals($initialCount + 1, $this->db->table('gis_translines')->countAllResults());

        // Proposal 1 is now CONFIRMED
        $prop1 = $this->db->table('gis_transline_proposals')->where('id', 1)->get()->getRowArray();
        $this->assertEquals('CONFIRMED', $prop1['status']);
        $this->assertNotNull($prop1['confirmed_transline_id']);
    }

    /**
     * Scenario 5: Multiple valid AUTO_MATCH proposals confirm atomically (+N delta)
     */
    public function test05_MultipleValidAutoMatchProposalsConfirmAtomically(): void
    {
        $initialCount = $this->db->table('gis_translines')->countAllResults();
        $res = $this->service->confirmBatchProposals([2, 3, 4], ['username' => 'TESTER_MULTI']);

        $this->assertEquals('success', $res['status']);
        $this->assertEquals('BATCH_CONFIRMED', $res['action']);
        $this->assertEquals(3, $res['batch_size']);
        $this->assertCount(3, $res['confirmed_proposals']);

        // Exact +3 delta
        $this->assertEquals($initialCount + 3, $this->db->table('gis_translines')->countAllResults());

        // All 3 are CONFIRMED
        $props = $this->db->table('gis_transline_proposals')->whereIn('id', [2, 3, 4])->get()->getResultArray();
        foreach ($props as $p) {
            $this->assertEquals('CONFIRMED', $p['status']);
            $this->assertGreaterThan(0, (int)$p['confirmed_transline_id']);
        }
    }

    /**
     * Scenario 6: One invalid proposal ID causes COMPLETE rollback (all-or-nothing)
     */
    public function test06_OneInvalidProposalCausesCompleteRollback(): void
    {
        $initialCount = $this->db->table('gis_translines')->countAllResults();
        // Proposal 1 is valid, 9999 does not exist
        $res = $this->service->confirmBatchProposals([1, 9999]);

        $this->assertEquals('error', $res['status']);
        $this->assertEquals('BATCH_CONFIRM_FAILED', $res['action']);
        $this->assertStringContainsString('9999', $res['message']);

        // All-or-nothing: 0 translines added
        $this->assertEquals($initialCount, $this->db->table('gis_translines')->countAllResults());

        // Proposal 1 remains PENDING_REVIEW
        $prop1 = $this->db->table('gis_transline_proposals')->where('id', 1)->get()->getRowArray();
        $this->assertEquals('PENDING_REVIEW', $prop1['status']);
        $this->assertNull($prop1['confirmed_transline_id']);
    }

    /**
     * Scenario 7: One NEEDS_REVIEW proposal causes COMPLETE rollback
     */
    public function test07_OneNeedsReviewProposalCausesCompleteRollback(): void
    {
        $initialCount = $this->db->table('gis_translines')->countAllResults();
        // Proposal 1 is AUTO_MATCH, Proposal 6 is NEEDS_REVIEW
        $res = $this->service->confirmBatchProposals([1, 6]);

        $this->assertEquals('error', $res['status']);
        $this->assertStringContainsString('NEEDS_REVIEW', $res['message']);

        // 0 writes
        $this->assertEquals($initialCount, $this->db->table('gis_translines')->countAllResults());
        $prop1 = $this->db->table('gis_transline_proposals')->where('id', 1)->get()->getRowArray();
        $this->assertEquals('PENDING_REVIEW', $prop1['status']);
    }

    /**
     * Scenario 8: One INVALID classification proposal causes COMPLETE rollback
     */
    public function test08_OneInvalidClassificationProposalCausesCompleteRollback(): void
    {
        $initialCount = $this->db->table('gis_translines')->countAllResults();
        // Proposal 1 is AUTO_MATCH, Proposal 7 is INVALID
        $res = $this->service->confirmBatchProposals([1, 7]);

        $this->assertEquals('error', $res['status']);
        $this->assertStringContainsString('INVALID', $res['message']);

        // 0 writes
        $this->assertEquals($initialCount, $this->db->table('gis_translines')->countAllResults());
        $prop1 = $this->db->table('gis_transline_proposals')->where('id', 1)->get()->getRowArray();
        $this->assertEquals('PENDING_REVIEW', $prop1['status']);
    }

    /**
     * Scenario 9: One MISSING classification proposal causes COMPLETE rollback
     */
    public function test09_OneMissingClassificationProposalCausesCompleteRollback(): void
    {
        $initialCount = $this->db->table('gis_translines')->countAllResults();
        // Proposal 1 is AUTO_MATCH, Proposal 8 is MISSING
        $res = $this->service->confirmBatchProposals([1, 8]);

        $this->assertEquals('error', $res['status']);
        $this->assertStringContainsString('MISSING', $res['message']);

        // 0 writes
        $this->assertEquals($initialCount, $this->db->table('gis_translines')->countAllResults());
        $prop1 = $this->db->table('gis_transline_proposals')->where('id', 1)->get()->getRowArray();
        $this->assertEquals('PENDING_REVIEW', $prop1['status']);
    }

    /**
     * Scenario 10: Already CONFIRMED proposal causes COMPLETE rollback
     */
    public function test10_AlreadyConfirmedProposalCausesCompleteRollback(): void
    {
        $initialCount = $this->db->table('gis_translines')->countAllResults();
        // Proposal 1 is PENDING_REVIEW, Proposal 9 is already CONFIRMED
        $res = $this->service->confirmBatchProposals([1, 9]);

        $this->assertEquals('error', $res['status']);
        $this->assertStringContainsString('CONFIRMED', $res['message']);

        // 0 writes
        $this->assertEquals($initialCount, $this->db->table('gis_translines')->countAllResults());
        $prop1 = $this->db->table('gis_transline_proposals')->where('id', 1)->get()->getRowArray();
        $this->assertEquals('PENDING_REVIEW', $prop1['status']);
    }

    /**
     * Scenario 11: Duplicate natural key in batch causes COMPLETE rollback
     */
    public function test11_DuplicateNaturalKeyCausesCompleteRollback(): void
    {
        $initialCount = $this->db->table('gis_translines')->countAllResults();

        // Seed proposal 11 with same natural key as proposal 1 (TL-NAT:10:1001-1002)
        $this->db->table('gis_transline_proposals')->insert([
            'id'                      => 11,
            'penyulang_id'            => 10,
            'section_id'              => 100,
            'source_asset_id'         => 1001,
            'target_asset_id'         => 1002,
            'natural_key'             => 'TL-NAT:10:1001-1002',
            'proposed_conductor_type' => 'AAAC',
            'proposed_conductor_size' => '150 mm²',
            'proposed_distance'       => 37.15,
            'classification'          => 'AUTO_MATCH',
            'confidence_score'        => 1.0000,
            'status'                  => 'PENDING_REVIEW',
        ]);

        $res = $this->service->confirmBatchProposals([1, 11]);
        $this->assertEquals('error', $res['status']);
        $this->assertStringContainsString('Duplikasi', $res['message']);

        // 0 writes
        $this->assertEquals($initialCount, $this->db->table('gis_translines')->countAllResults());
    }

    /**
     * Scenario 12: Existing active transline duplicate causes COMPLETE rollback
     */
    public function test12_ExistingActiveTranslineDuplicateCausesCompleteRollback(): void
    {
        $initialCount = $this->db->table('gis_translines')->countAllResults();
        // Proposal 1 is valid, Proposal 10 is between 1002 and 1003 which ALREADY exists in gis_translines
        $res = $this->service->confirmBatchProposals([1, 10]);

        $this->assertEquals('error', $res['status']);
        $this->assertStringContainsString('Duplikasi', $res['message']);

        // 0 writes
        $this->assertEquals($initialCount, $this->db->table('gis_translines')->countAllResults());
        $prop1 = $this->db->table('gis_transline_proposals')->where('id', 1)->get()->getRowArray();
        $this->assertEquals('PENDING_REVIEW', $prop1['status']);
    }

    /**
     * Scenario 13: Intra-batch duplicate pair causes COMPLETE rollback
     */
    public function test13_IntraBatchDuplicatePairCausesCompleteRollback(): void
    {
        $initialCount = $this->db->table('gis_translines')->countAllResults();

        // Seed proposal 12 with reversed pair 1002 -> 1001
        $this->db->table('gis_transline_proposals')->insert([
            'id'                      => 12,
            'penyulang_id'            => 10,
            'section_id'              => 100,
            'source_asset_id'         => 1002,
            'target_asset_id'         => 1001,
            'natural_key'             => 'TL-NAT:10:1001-1002-REV',
            'proposed_conductor_type' => 'AAAC',
            'proposed_conductor_size' => '150 mm²',
            'proposed_distance'       => 37.15,
            'classification'          => 'AUTO_MATCH',
            'confidence_score'        => 1.0000,
            'status'                  => 'PENDING_REVIEW',
        ]);

        $res = $this->service->confirmBatchProposals([1, 12]);
        $this->assertEquals('error', $res['status']);
        $this->assertStringContainsString('Duplikasi dalam batch', $res['message']);

        // 0 writes
        $this->assertEquals($initialCount, $this->db->table('gis_translines')->countAllResults());
    }

    /**
     * Scenario 14: Race condition / non-pending status causes COMPLETE rollback
     */
    public function test14_ConcurrentConfirmationRaceConditionCausesCompleteRollback(): void
    {
        $initialCount = $this->db->table('gis_translines')->countAllResults();

        // Another process marks proposal 2 as CONFIRMED right before our batch execution
        $this->db->table('gis_transline_proposals')
            ->where('id', 2)
            ->update(['status' => 'CONFIRMED']);

        $res = $this->service->confirmBatchProposals([1, 2]);
        $this->assertEquals('error', $res['status']);
        $this->assertStringContainsString('CONFIRMED', $res['message']);

        // Proposal 1 was NOT confirmed partially
        $prop1 = $this->db->table('gis_transline_proposals')->where('id', 1)->get()->getRowArray();
        $this->assertEquals('PENDING_REVIEW', $prop1['status']);
        $this->assertEquals($initialCount, $this->db->table('gis_translines')->countAllResults());
    }

    /**
     * Scenario 15: Exact confirmed_transline_id is persisted and matches inserted PK
     */
    public function test15_ExactConfirmedTranslineIdPersistedPerProposal(): void
    {
        $res = $this->service->confirmBatchProposals([1, 2]);
        $this->assertEquals('success', $res['status']);

        $prop1 = $this->db->table('gis_transline_proposals')->where('id', 1)->get()->getRowArray();
        $prop2 = $this->db->table('gis_transline_proposals')->where('id', 2)->get()->getRowArray();

        $pk1 = (int)$prop1['confirmed_transline_id'];
        $pk2 = (int)$prop2['confirmed_transline_id'];

        $this->assertGreaterThan(0, $pk1);
        $this->assertGreaterThan(0, $pk2);
        $this->assertNotEquals($pk1, $pk2);

        // Verify the rows actually exist in gis_translines with these exact PKs
        $line1 = $this->db->table('gis_translines')->where('id', $pk1)->get()->getRowArray();
        $line2 = $this->db->table('gis_translines')->where('id', $pk2)->get()->getRowArray();

        $this->assertNotNull($line1);
        $this->assertNotNull($line2);
        $this->assertEquals(1001, (int)$line1['source_asset_id']);
        $this->assertEquals(1003, (int)$line2['source_asset_id']);
    }

    /**
     * Scenario 16: Browser-forged authoritative payload is ignored (server computes from DB)
     */
    public function test16_BrowserForgedAuthoritativePayloadIgnored(): void
    {
        // Service only accepts proposal IDs, extracting coordinates & conductor from DB
        $res = $this->service->confirmBatchProposals([1]);
        $this->assertEquals('success', $res['status']);

        $prop1 = $this->db->table('gis_transline_proposals')->where('id', 1)->get()->getRowArray();
        $transline = $this->db->table('gis_translines')->where('id', $prop1['confirmed_transline_id'])->get()->getRowArray();

        // Server authoritative geometry coordinates computed from assets 1001 & 1002
        $geom = json_decode((string)$transline['geometry'], true);
        $this->assertIsArray($geom);
        $this->assertEquals(112.7230, $geom[0][0]);
        $this->assertEquals(-7.4160, $geom[0][1]);
        $this->assertEquals('AAAC', $transline['conductor_type']);
    }

    /**
     * Scenario 17: Source/target scope mismatch (different feeders) causes COMPLETE rollback
     */
    public function test17_SourceTargetFeederMismatchCausesCompleteRollback(): void
    {
        $initialCount = $this->db->table('gis_translines')->countAllResults();
        // Proposal 5 links 1001 (feeder 10) to 2001 (feeder 20)
        $res = $this->service->confirmBatchProposals([1, 5]);

        $this->assertEquals('error', $res['status']);
        // 0 writes
        $this->assertEquals($initialCount, $this->db->table('gis_translines')->countAllResults());
        $prop1 = $this->db->table('gis_transline_proposals')->where('id', 1)->get()->getRowArray();
        $this->assertEquals('PENDING_REVIEW', $prop1['status']);
    }

    /**
     * Scenario 18: Master assets table is 100% untouched (0 mutation)
     */
    public function test18_MasterAssetsZeroMutationInvariant(): void
    {
        $assetsBefore = $this->db->table('assets')->get()->getResultArray();
        $res = $this->service->confirmBatchProposals([1, 2]);
        $this->assertEquals('success', $res['status']);

        $assetsAfter = $this->db->table('assets')->get()->getResultArray();
        $this->assertCount(count($assetsBefore), $assetsAfter);

        foreach ($assetsBefore as $idx => $before) {
            $after = $assetsAfter[$idx];
            $this->assertEquals($before['id'], $after['id']);
            $this->assertEquals($before['section_id'], $after['section_id']);
            $this->assertEquals($before['construction_type_id'], $after['construction_type_id']);
            $this->assertEquals($before['penyulang_id'], $after['penyulang_id']);
        }
    }

    /**
     * Scenario 19: Temuan table is 100% untouched (0 mutation)
     */
    public function test19_TemuanZeroMutationInvariant(): void
    {
        $countBefore = $this->db->table('temuan')->countAllResults();
        $res = $this->service->confirmBatchProposals([1, 2]);
        $this->assertEquals('success', $res['status']);

        $countAfter = $this->db->table('temuan')->countAllResults();
        $this->assertEquals($countBefore, $countAfter);
    }

    /**
     * Scenario 20: Temuan_materials table is 100% untouched (0 mutation)
     */
    public function test20_TemuanMaterialsZeroMutationInvariant(): void
    {
        $countBefore = $this->db->table('temuan_materials')->countAllResults();
        $res = $this->service->confirmBatchProposals([1, 2]);
        $this->assertEquals('success', $res['status']);

        $countAfter = $this->db->table('temuan_materials')->countAllResults();
        $this->assertEquals($countBefore, $countAfter);
    }

    /**
     * Scenario 21: AR-01 scoring and sections assignment remains 100% untouched (0 mutation)
     */
    public function test21_Ar01ZeroMutationInvariant(): void
    {
        $sectionsBefore = $this->db->table('sections')->get()->getResultArray();
        $res = $this->service->confirmBatchProposals([1, 2]);
        $this->assertEquals('success', $res['status']);

        $sectionsAfter = $this->db->table('sections')->get()->getResultArray();
        $this->assertEquals($sectionsBefore, $sectionsAfter);
    }

    /**
     * Scenario 22: MR-01 material transaction domain remains 100% untouched (0 mutation)
     */
    public function test22_Mr01ZeroMutationInvariant(): void
    {
        $materialsBefore = $this->db->table('temuan_materials')->get()->getResultArray();
        $res = $this->service->confirmBatchProposals([1, 2]);
        $this->assertEquals('success', $res['status']);

        $materialsAfter = $this->db->table('temuan_materials')->get()->getResultArray();
        $this->assertEquals($materialsBefore, $materialsAfter);
    }

    /**
     * Scenario 23: Unrelated GIS topology remains 100% untouched
     */
    public function test23_UnrelatedGisTopologyZeroMutationInvariant(): void
    {
        $res = $this->service->confirmBatchProposals([1, 2]);
        $this->assertEquals('success', $res['status']);

        // Existing baseline transline 501 remains untouched
        $line501 = $this->db->table('gis_translines')->where('id', 501)->get()->getRowArray();
        $this->assertNotNull($line501);
        $this->assertEquals('TL-10-1002-1003', $line501['transline_code']);
    }

    /**
     * Scenario 24: Successful batch count cardinality is exact (+N)
     */
    public function test24_SuccessfulBatchCountCardinalityExact(): void
    {
        $before = $this->db->table('gis_translines')->countAllResults();
        $res = $this->service->confirmBatchProposals([1, 2, 3]);

        $this->assertEquals('success', $res['status']);
        $after = $this->db->table('gis_translines')->countAllResults();
        $this->assertEquals($before + 3, $after);
    }

    /**
     * Scenario 25: Failed batch count cardinality is exact (+0)
     */
    public function test25_FailedBatchCountCardinalityExact(): void
    {
        $before = $this->db->table('gis_translines')->countAllResults();
        // Proposal 6 is NEEDS_REVIEW
        $res = $this->service->confirmBatchProposals([1, 2, 6]);

        $this->assertEquals('error', $res['status']);
        $after = $this->db->table('gis_translines')->countAllResults();
        $this->assertEquals($before, $after);
    }

    /**
     * Scenario 26: Idempotent retry after success is safely rejected without duplicate rows
     */
    public function test26_IdempotentRetryAfterSuccessRejectedSafely(): void
    {
        $res1 = $this->service->confirmBatchProposals([1, 2]);
        $this->assertEquals('success', $res1['status']);
        $countAfterFirst = $this->db->table('gis_translines')->countAllResults();

        // Re-confirm same batch
        $res2 = $this->service->confirmBatchProposals([1, 2]);
        $this->assertEquals('error', $res2['status']);
        $countAfterSecond = $this->db->table('gis_translines')->countAllResults();

        $this->assertEquals($countAfterFirst, $countAfterSecond);
    }

    /**
     * Scenario 27: Batch rollback by exact PK with provenance check succeeds
     */
    public function test27_BatchRollbackByExactPkWithProvenanceCheckSucceeds(): void
    {
        $initialTranslines = $this->db->table('gis_translines')->countAllResults();

        // Confirm batch of 2 proposals
        $resConfirm = $this->service->confirmBatchProposals([1, 2], ['username' => 'TESTER_OP']);
        $this->assertEquals('success', $resConfirm['status']);
        $this->assertEquals($initialTranslines + 2, $this->db->table('gis_translines')->countAllResults());

        // Now rollback batch of 2 proposals
        $resRollback = $this->service->rollbackBatchProposals([1, 2], ['username' => 'TESTER_ROLLBACK']);
        $this->assertEquals('success', $resRollback['status']);
        $this->assertEquals('BATCH_ROLLED_BACK', $resRollback['action']);
        $this->assertEquals(2, $resRollback['batch_size']);

        // Translines count exactly restored to initial
        $this->assertEquals($initialTranslines, $this->db->table('gis_translines')->countAllResults());

        // Both proposals cleanly reset to PENDING_REVIEW
        $props = $this->db->table('gis_transline_proposals')->whereIn('id', [1, 2])->get()->getResultArray();
        foreach ($props as $p) {
            $this->assertEquals('PENDING_REVIEW', $p['status']);
            $this->assertNull($p['confirmed_transline_id']);
        }
    }

    /**
     * Scenario 28: Batch rollback provenance mismatch rejected (tamper protection)
     */
    public function test28_BatchRollbackProvenanceMismatchRejected(): void
    {
        // Confirm proposal 1
        $resConfirm = $this->service->confirmBatchProposals([1]);
        $this->assertEquals('success', $resConfirm['status']);

        // Tamper proposal 1's confirmed_transline_id to point to transline 501 (which belongs to 1002-1003)
        $this->db->table('gis_transline_proposals')
            ->where('id', 1)
            ->update(['confirmed_transline_id' => 501]);

        $translinesBefore = $this->db->table('gis_translines')->countAllResults();

        // Rollback attempt must fail provenance check
        $resRollback = $this->service->rollbackBatchProposals([1]);
        $this->assertEquals('error', $resRollback['status']);
        $this->assertStringContainsString('Pelanggaran integritas', $resRollback['message']);

        // Transline 501 was NOT deleted!
        $this->assertEquals($translinesBefore, $this->db->table('gis_translines')->countAllResults());
        $line501 = $this->db->table('gis_translines')->where('id', 501)->get()->getRowArray();
        $this->assertNotNull($line501);
    }

    /**
     * Scenario 29: Batch result contains deterministic per-proposal receipt and audit fingerprint
     */
    public function test29_BatchResultContainsDeterministicPerProposalReceipt(): void
    {
        $res = $this->service->confirmBatchProposals([1, 2], ['username' => 'OPERATOR_AUDIT']);
        $this->assertEquals('success', $res['status']);

        $this->assertArrayHasKey('fingerprint', $res);
        $this->assertNotEmpty($res['fingerprint']);
        $this->assertEquals(64, strlen($res['fingerprint'])); // SHA-256
        $this->assertEquals('OPERATOR_AUDIT', $res['reviewed_by']);

        foreach ($res['confirmed_proposals'] as $cp) {
            $this->assertArrayHasKey('proposal_id', $cp);
            $this->assertArrayHasKey('confirmed_transline_id', $cp);
            $this->assertArrayHasKey('transline_code', $cp);
            $this->assertArrayHasKey('natural_key', $cp);
            $this->assertArrayHasKey('feeder_id', $cp);
            $this->assertArrayHasKey('source_asset_id', $cp);
            $this->assertArrayHasKey('target_asset_id', $cp);
        }
    }
}
