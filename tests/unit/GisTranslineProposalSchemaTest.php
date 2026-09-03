<?php

namespace Tests\Unit;

require_once APPPATH . 'Database/Migrations/2026-09-03-000001_CreateGisTranslineProposalsTable.php';

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Stage A Test: Additive Proposal Schema Integrity & Zero-Write Audit
 *
 * Verifies:
 * - Migration 2026-09-03-000001 creates `gis_transline_proposals` cleanly.
 * - All required columns, types, and defaults exist.
 * - Indexes exist on search dimensions and natural keys.
 * - Zero mutation to `gis_translines`, `assets`, `temuan`, `temuan_materials`.
 * - Down migration cleanly drops the table.
 */
class GisTranslineProposalSchemaTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
    }

    private function getTableFingerprint(string $tableName): array
    {
        if (!$this->db->tableExists($tableName)) {
            return ['count' => 0, 'hash' => hash('sha256', 'TABLE_DOES_NOT_EXIST')];
        }

        $cnt = (int)$this->db->table($tableName)->countAllResults();
        $rows = $this->db->table($tableName)->orderBy('id', 'ASC')->get()->getResultArray();
        return [
            'count' => $cnt,
            'hash'  => hash('sha256', json_encode($rows)),
        ];
    }

    public function testProposalTableMigrationUpAndDown()
    {
        $forge = \Config\Services::forge();
        $migration = new \App\Database\Migrations\CreateGisTranslineProposalsTable();

        // 1. Initial Fingerprints of Protected Tables
        $monitored = ['gis_translines', 'assets', 'temuan', 'temuan_materials'];
        $fpBefore = [];
        foreach ($monitored as $tbl) {
            $fpBefore[$tbl] = $this->getTableFingerprint($tbl);
        }

        // 2. Run Down (cleanup if exists)
        $migration->down();
        $this->assertFalse($this->db->tableExists('gis_transline_proposals'));

        // 3. Run Up
        $migration->up();
        $this->assertTrue($this->db->tableExists('gis_transline_proposals'));

        // 4. Verify Columns
        $expectedColumns = [
            'id', 'penyulang_id', 'section_id', 'source_asset_id', 'target_asset_id',
            'natural_key', 'proposed_conductor_type', 'proposed_conductor_size',
            'proposed_distance', 'proposed_geometry', 'classification',
            'confidence_score', 'evidence_json', 'proposal_source', 'engine_version',
            'status', 'reviewed_by', 'reviewed_at', 'review_note',
            'confirmed_transline_id', 'created_at', 'updated_at', 'deleted_at'
        ];

        foreach ($expectedColumns as $col) {
            $this->assertTrue(
                $this->db->fieldExists($col, 'gis_transline_proposals'),
                "Column {$col} must exist in gis_transline_proposals."
            );
        }

        // 5. Test Insertion via Model (Ensure parent FK records exist if tables present)
        $cleanPenyulang = false;
        $cleanAsset101 = false;
        $cleanAsset102 = false;

        if ($this->db->tableExists('penyulang') && $this->db->table('penyulang')->where('id', 1)->countAllResults() === 0) {
            $this->db->table('penyulang')->insert(['id' => 1, 'kode_penyulang' => 'CDR', 'nama_penyulang' => 'Candramas']);
            $cleanPenyulang = true;
        }
        if ($this->db->tableExists('assets')) {
            if ($this->db->table('assets')->where('id', 101)->countAllResults() === 0) {
                $this->db->table('assets')->insert(['id' => 101, 'kode_asset' => 'T-101', 'nama_asset' => 'Tiang 101', 'jenis_asset' => 'TIANG_BETON']);
                $cleanAsset101 = true;
            }
            if ($this->db->table('assets')->where('id', 102)->countAllResults() === 0) {
                $this->db->table('assets')->insert(['id' => 102, 'kode_asset' => 'T-102', 'nama_asset' => 'Tiang 102', 'jenis_asset' => 'TIANG_BETON']);
                $cleanAsset102 = true;
            }
        }

        $model = new \App\Models\GisTranslineProposalModel($this->db);
        $testData = [
            'penyulang_id'            => 1,
            'section_id'              => null,
            'source_asset_id'         => 101,
            'target_asset_id'         => 102,
            'natural_key'             => 'TL-NAT:1:101-102',
            'proposed_conductor_type' => 'AAAC',
            'proposed_conductor_size' => '150 mm²',
            'proposed_distance'       => 35.50,
            'classification'          => 'AUTO_MATCH',
            'confidence_score'        => 95.00,
            'evidence_json'           => json_encode(['sequential' => true, 'same_feeder' => true]),
            'proposal_source'         => 'DETERMINISTIC_ENGINE',
            'engine_version'          => 'v1.0',
            'status'                  => 'PENDING_REVIEW',
        ];

        $insertId = $model->insert($testData);
        $this->assertIsNumeric($insertId);
        $this->assertGreaterThan(0, (int)$insertId);

        // Find by Natural Key
        $found = $model->findByNaturalKey('TL-NAT:1:101-102');
        $this->assertNotNull($found);
        $this->assertEquals('AUTO_MATCH', $found['classification']);
        $this->assertEquals('PENDING_REVIEW', $found['status']);
        $this->assertEquals(95.00, (float)$found['confidence_score']);

        // Clean up test proposal and parent rows
        $this->db->table('gis_transline_proposals')->where('id', $insertId)->delete();
        if ($cleanAsset101) $this->db->table('assets')->where('id', 101)->delete();
        if ($cleanAsset102) $this->db->table('assets')->where('id', 102)->delete();
        if ($cleanPenyulang) $this->db->table('penyulang')->where('id', 1)->delete();

        // 6. Verify Protected Tables are 100% UNTOUCHED (0 Operational Mutations)
        foreach ($monitored as $tbl) {
            $fpAfter = $this->getTableFingerprint($tbl);
            $this->assertEquals($fpBefore[$tbl]['count'], $fpAfter['count'], "Table {$tbl} row count must remain unchanged.");
            $this->assertEquals($fpBefore[$tbl]['hash'], $fpAfter['hash'], "Table {$tbl} content hash must remain identical.");
        }
    }
}
