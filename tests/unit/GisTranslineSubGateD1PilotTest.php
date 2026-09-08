<?php

namespace Tests\Unit;

use App\Services\TranslineCompletionService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * TL-01 Sub-Gate D1 Controlled 5-Row Pilot Test Suite
 *
 * Covers:
 * 1. Exactly 5 AUTO_MATCH pilot candidate selection
 * 2. Run 1 persists exactly 5 proposals into `gis_transline_proposals`
 * 3. Status is strictly `PENDING_REVIEW`
 * 4. Run 2 idempotency: 0 duplicates created, logical count remains 5
 * 5. Firewall: `gis_translines` remains 100% untouched
 * 6. Evidence JSON and visual style token faithfully recorded
 * 7. Dry-run safety (0 rows written)
 * 8. Atomic transaction rollback
 */
class GisTranslineSubGateD1PilotTest extends CIUnitTestCase
{
    protected $db;
    protected TranslineCompletionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->service = new TranslineCompletionService($this->db);
        $this->setupSchema();
    }

    protected function setupSchema(): void
    {
        $forge = Database::forge();

        // 1. gis_translines table
        if (!$this->db->tableExists('gis_translines')) {
            $forge->addField([
                'id'                 => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'transline_code'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'penyulang_id'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'source_asset_id'    => ['type' => 'INT', 'constraint' => 11],
                'target_asset_id'    => ['type' => 'INT', 'constraint' => 11],
                'geometry'           => ['type' => 'TEXT', 'null' => true],
                'geometry_type'      => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'LineString'],
                'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'conductor_material' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'ALUMINUM_ALLOY'],
                'installation_type'  => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'OVERHEAD'],
                'circuit_config'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '3_PHASE'],
                'distance_meters'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 50.00],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVE'],
                'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        }

        // 2. gis_transline_proposals table
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
    }

    protected function getSamplePilotCandidates(): array
    {
        return [
            [
                'natural_key'              => 'TL-NAT:10:1001-1002',
                'penyulang_id'             => 10,
                'section_id'               => 20,
                'source_asset_id'          => 1001,
                'target_asset_id'          => 1002,
                'source_asset_code'        => 'BANJARKEMANTRAN_01',
                'target_asset_code'        => 'BANJARKEMANTRAN_02',
                'distance_meters'          => 37.15,
                'expected_distance_meters' => 37.15,
                'conductor_type'           => 'AAAC',
                'conductor_size'           => '150 mm²',
                'visual_style_token'       => 'AAAC_150',
                'visual_pattern'           => 'HEAVY_SOLID',
                'classification'           => 'AUTO_MATCH',
                'confidence_score'         => 1.0,
                'reason_code'              => 'EXISTING_VALID_PAIR',
                'warnings'                 => [],
                'source_coordinates'       => ['lat' => -7.4160, 'lng' => 112.7230],
                'target_coordinates'       => ['lat' => -7.4163, 'lng' => 112.7231],
            ],
            [
                'natural_key'              => 'TL-NAT:10:1002-1003',
                'penyulang_id'             => 10,
                'section_id'               => 20,
                'source_asset_id'          => 1002,
                'target_asset_id'          => 1003,
                'source_asset_code'        => 'BANJARKEMANTRAN_02',
                'target_asset_code'        => 'BANJARKEMANTRAN_03',
                'distance_meters'          => 41.20,
                'expected_distance_meters' => 41.20,
                'conductor_type'           => 'AAAC',
                'conductor_size'           => '150 mm²',
                'visual_style_token'       => 'AAAC_150',
                'visual_pattern'           => 'HEAVY_SOLID',
                'classification'           => 'AUTO_MATCH',
                'confidence_score'         => 1.0,
                'reason_code'              => 'DETERMINISTIC_SEQUENTIAL_PAIR',
                'warnings'                 => [],
                'source_coordinates'       => ['lat' => -7.4163, 'lng' => 112.7231],
                'target_coordinates'       => ['lat' => -7.4166, 'lng' => 112.7233],
            ],
            [
                'natural_key'              => 'TL-NAT:10:1003-1004',
                'penyulang_id'             => 10,
                'section_id'               => 20,
                'source_asset_id'          => 1003,
                'target_asset_id'          => 1004,
                'source_asset_code'        => 'BANJARKEMANTRAN_03',
                'target_asset_code'        => 'BANJARKEMANTRAN_04',
                'distance_meters'          => 39.80,
                'expected_distance_meters' => 39.80,
                'conductor_type'           => 'AAAC',
                'conductor_size'           => '150 mm²',
                'visual_style_token'       => 'AAAC_150',
                'visual_pattern'           => 'HEAVY_SOLID',
                'classification'           => 'AUTO_MATCH',
                'confidence_score'         => 1.0,
                'reason_code'              => 'DETERMINISTIC_SEQUENTIAL_PAIR',
                'warnings'                 => [],
                'source_coordinates'       => ['lat' => -7.4166, 'lng' => 112.7233],
                'target_coordinates'       => ['lat' => -7.4169, 'lng' => 112.7235],
            ],
            [
                'natural_key'              => 'TL-NAT:10:1004-1005',
                'penyulang_id'             => 10,
                'section_id'               => 20,
                'source_asset_id'          => 1004,
                'target_asset_id'          => 1005,
                'source_asset_code'        => 'BANJARKEMANTRAN_04',
                'target_asset_code'        => 'BANJARKEMANTRAN_05',
                'distance_meters'          => 45.10,
                'expected_distance_meters' => 45.10,
                'conductor_type'           => 'A3C',
                'conductor_size'           => '70 mm²',
                'visual_style_token'       => 'A3C_70',
                'visual_pattern'           => 'DASH_DOT',
                'classification'           => 'AUTO_MATCH',
                'confidence_score'         => 1.0,
                'reason_code'              => 'DETERMINISTIC_SEQUENTIAL_PAIR',
                'warnings'                 => [],
                'source_coordinates'       => ['lat' => -7.4169, 'lng' => 112.7235],
                'target_coordinates'       => ['lat' => -7.4172, 'lng' => 112.7237],
            ],
            [
                'natural_key'              => 'TL-NAT:10:1005-1006',
                'penyulang_id'             => 10,
                'section_id'               => 20,
                'source_asset_id'          => 1005,
                'target_asset_id'          => 1006,
                'source_asset_code'        => 'BANJARKEMANTRAN_05',
                'target_asset_code'        => 'BANJARKEMANTRAN_06',
                'distance_meters'          => 48.30,
                'expected_distance_meters' => 48.30,
                'conductor_type'           => 'MVTIC',
                'conductor_size'           => '3x150 mm²',
                'visual_style_token'       => 'MVTIC',
                'visual_pattern'           => 'TWISTED_CHAIN',
                'classification'           => 'AUTO_MATCH',
                'confidence_score'         => 1.0,
                'reason_code'              => 'DETERMINISTIC_SEQUENTIAL_PAIR',
                'warnings'                 => [],
                'source_coordinates'       => ['lat' => -7.4172, 'lng' => 112.7237],
                'target_coordinates'       => ['lat' => -7.4175, 'lng' => 112.7239],
            ],
        ];
    }

    public function testDryRunProducesZeroDatabaseMutations()
    {
        $candidates = $this->getSamplePilotCandidates();
        $result = $this->service->persistProposalBatch($candidates, ['dry_run' => true]);

        $this->assertSame('dry_run_success', $result['status']);
        $this->assertSame(5, $result['to_insert_count']);
        $this->assertSame(0, $result['skipped_existing_count']);
        $this->assertSame(0, $this->db->table('gis_transline_proposals')->countAllResults());
    }

    public function testRunOnePersistsExactlyFiveProposals()
    {
        $candidates = $this->getSamplePilotCandidates();
        $tlBefore = $this->db->table('gis_translines')->countAllResults();

        $result = $this->service->persistProposalBatch($candidates, ['dry_run' => false]);

        $this->assertSame('success', $result['status']);
        $this->assertSame(5, $result['inserted_count']);
        $this->assertSame(0, $result['skipped_existing_count']);

        // Check proposal rows
        $this->assertSame(5, $this->db->table('gis_transline_proposals')->countAllResults());

        // Check firewall on gis_translines
        $tlAfter = $this->db->table('gis_translines')->countAllResults();
        $this->assertSame($tlBefore, $tlAfter, "gis_translines must have ZERO mutations");

        // Verify status is PENDING_REVIEW and classifications are AUTO_MATCH
        $rows = $this->db->table('gis_transline_proposals')->get()->getResultArray();
        foreach ($rows as $row) {
            $this->assertSame('PENDING_REVIEW', $row['status']);
            $this->assertSame('AUTO_MATCH', $row['classification']);
            $this->assertSame('DETERMINISTIC_ENGINE', $row['proposal_source']);
            $this->assertStringStartsWith('TL-NAT:10:', $row['natural_key']);
        }
    }

    public function testRunTwoIsStrictlyIdempotentWithZeroDuplicates()
    {
        $candidates = $this->getSamplePilotCandidates();

        // RUN #1
        $res1 = $this->service->persistProposalBatch($candidates, ['dry_run' => false]);
        $this->assertSame(5, $res1['inserted_count']);

        // RUN #2 (Repeated Execution)
        $res2 = $this->service->persistProposalBatch($candidates, ['dry_run' => false]);
        $this->assertSame(0, $res2['inserted_count'], "Run 2 must insert ZERO rows");
        $this->assertSame(5, $res2['skipped_existing_count'], "Run 2 must skip all 5 existing proposals");

        // Assert database row count remains strictly 5
        $this->assertSame(5, $this->db->table('gis_transline_proposals')->countAllResults());
    }

    public function testIllegalClassificationAbortsWithoutMutation()
    {
        $candidates = $this->getSamplePilotCandidates();
        $candidates[0]['classification'] = 'PROPOSED'; // ILLEGAL under current contract

        $result = $this->service->persistProposalBatch($candidates, ['dry_run' => false]);

        $this->assertSame('error', $result['status']);
        $this->assertSame('ILLEGAL_CLASSIFICATION', $result['reason']);
        $this->assertSame(0, $this->db->table('gis_transline_proposals')->countAllResults());
    }

    public function testEvidenceJsonAndVisualStyleTokenPersistedFaithfully()
    {
        $candidates = $this->getSamplePilotCandidates();
        $this->service->persistProposalBatch($candidates, ['dry_run' => false]);

        $rowMvtic = $this->db->table('gis_transline_proposals')
            ->where('natural_key', 'TL-NAT:10:1005-1006')
            ->get()
            ->getRowArray();

        $this->assertNotNull($rowMvtic);
        $this->assertSame('MVTIC', $rowMvtic['proposed_conductor_type']);

        $evidence = json_decode($rowMvtic['evidence_json'], true);
        $this->assertIsArray($evidence);
        $this->assertSame('MVTIC', $evidence['visual_style_token']);
        $this->assertSame('TWISTED_CHAIN', $evidence['visual_pattern']);
    }
}
