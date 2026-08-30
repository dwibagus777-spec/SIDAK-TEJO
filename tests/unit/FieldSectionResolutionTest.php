<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\FieldSectionResolutionService;

/**
 * Unit Tests for AR-01 Phase 5G: Field Section Resolution & Topology Traceability Engine
 */
class FieldSectionResolutionTest extends CIUnitTestCase
{
    protected $db;
    protected FieldSectionResolutionService $sectionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->db->dataCache = [];
        $forge = \Config\Database::forge();

        $this->ensureTablesExist($forge, $this->db);
        $this->sectionService = new FieldSectionResolutionService($this->db);
    }

    private function ensureTablesExist($forge, $db): void
    {
        if (!$db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'ulp_id'         => ['type' => 'INTEGER', 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
                'is_active'      => ['type' => 'INTEGER', 'default' => 1],
            ]);
            $forge->createTable('penyulang', true);
            $db->table('penyulang')->insert([
                'id'             => 1,
                'ulp_id'         => 1,
                'kode_penyulang' => 'PYL-001',
                'nama_penyulang' => 'SIWALAN PANJI',
                'is_active'      => 1,
            ]);
            $db->table('penyulang')->insert([
                'id'             => 2,
                'ulp_id'         => 1,
                'kode_penyulang' => 'PYL-002',
                'nama_penyulang' => 'GADING KIRANA',
                'is_active'      => 1,
            ]);
        }

        if (!$db->tableExists('sections')) {
            $forge->addField([
                'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'penyulang_id'   => ['type' => 'INTEGER'],
                'nama_seksi'     => ['type' => 'VARCHAR', 'constraint' => 100],
                'sequence_order' => ['type' => 'INTEGER'],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
            ]);
            $forge->createTable('sections', true);
            $db->table('sections')->insertBatch([
                ['id' => 1, 'penyulang_id' => 1, 'nama_seksi' => 'GI - LBSM PDAM', 'sequence_order' => 1, 'status' => 'ACTIVE'],
                ['id' => 2, 'penyulang_id' => 1, 'nama_seksi' => 'LBSM PDAM - RECLOSER PANJI', 'sequence_order' => 2, 'status' => 'ACTIVE'],
                ['id' => 3, 'penyulang_id' => 2, 'nama_seksi' => 'GI - SEKSI 1 GADING', 'sequence_order' => 1, 'status' => 'ACTIVE'],
            ]);
        } else {
            try {
                if (!$db->fieldExists('sequence_order', 'sections')) {
                    $forge->addColumn('sections', ['sequence_order' => ['type' => 'INTEGER', 'default' => 1]]);
                }
            } catch (\Throwable $e) {}
            try {
                if (!$db->fieldExists('status', 'sections')) {
                    $forge->addColumn('sections', ['status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE']]);
                }
            } catch (\Throwable $e) {}
        }

        // Ensure sections 1, 2, 3 exist
        $nameCol = $db->fieldExists('nama_seksi', 'sections') ? 'nama_seksi' : ($db->fieldExists('nama_section', 'sections') ? 'nama_section' : 'name');
        $sectionsToSeed = [
            ['id' => 1, 'penyulang_id' => 1, $nameCol => 'GI - LBSM PDAM', 'sequence_order' => 1, 'status' => 'ACTIVE'],
            ['id' => 2, 'penyulang_id' => 1, $nameCol => 'LBSM PDAM - RECLOSER PANJI', 'sequence_order' => 2, 'status' => 'ACTIVE'],
            ['id' => 3, 'penyulang_id' => 2, $nameCol => 'GI - SEKSI 1 GADING', 'sequence_order' => 1, 'status' => 'ACTIVE'],
        ];
        foreach ($sectionsToSeed as $sec) {
            $exists = $db->table('sections')->where('id', $sec['id'])->get()->getRowArray();
            if (!$exists) {
                $db->table('sections')->insert($sec);
            }
        }

        if (!$db->tableExists('assets')) {
            $forge->addField([
                'id'                        => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_asset'                => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'                => ['type' => 'VARCHAR', 'constraint' => 255],
                'jenis_asset'               => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
                'penyulang_id'              => ['type' => 'INTEGER', 'null' => true],
                'section_id'                => ['type' => 'INTEGER', 'null' => true],
                'field_sequence'            => ['type' => 'INTEGER', 'null' => true],
                'section_resolution_method' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'UNRESOLVED'],
                'section_verified_by'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'section_verified_at'       => ['type' => 'DATETIME', 'null' => true],
                'created_at'                => ['type' => 'DATETIME', 'null' => true],
                'updated_at'                => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'                => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('assets', true);
        } else {
            $table = $db->prefixTable('assets');
            $alterQueries = [
                "ALTER TABLE {$table} ADD COLUMN jenis_asset VARCHAR(50) DEFAULT 'JTM'",
                "ALTER TABLE {$table} ADD COLUMN section_resolution_method VARCHAR(50) DEFAULT 'UNRESOLVED'",
                "ALTER TABLE {$table} ADD COLUMN section_verified_by VARCHAR(100) NULL",
                "ALTER TABLE {$table} ADD COLUMN section_verified_at DATETIME NULL",
                "ALTER TABLE {$table} ADD COLUMN field_sequence INT NULL",
            ];
            foreach ($alterQueries as $q) {
                try {
                    $db->query($q);
                } catch (\Throwable $e) {}
            }
        }

        if (!$db->tableExists('asset_section_history')) {
            $forge->addField([
                'id'                       => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'asset_id'                 => ['type' => 'INTEGER'],
                'penyulang_id'             => ['type' => 'INTEGER'],
                'old_section_id'           => ['type' => 'INTEGER', 'null' => true],
                'new_section_id'           => ['type' => 'INTEGER', 'null' => true],
                'old_sequence'             => ['type' => 'INTEGER', 'null' => true],
                'new_sequence'             => ['type' => 'INTEGER', 'null' => true],
                'resolution_method'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'FIELD_VERIFIED'],
                'verified_by'              => ['type' => 'VARCHAR', 'constraint' => 100],
                'reason'                   => ['type' => 'TEXT'],
                'latitude_at_verification' => ['type' => 'DOUBLE', 'null' => true],
                'longitude_at_verification'=> ['type' => 'DOUBLE', 'null' => true],
                'created_at'               => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('asset_section_history', true);
        }
    }

    public function testCrossFeederSectionAssignmentIsBlocked(): void
    {
        // Asset belongs to Feeder 1
        $this->db->table('assets')->insert([
            'id'                        => 101,
            'kode_asset'                => 'AST-PANJI-101',
            'nama_asset'                => 'Tiang Panji 101',
            'jenis_asset'               => 'JTM',
            'penyulang_id'              => 1,
            'section_id'                => null,
            'section_resolution_method' => 'UNRESOLVED',
        ]);

        // Attempt assigning Section 3 (belongs to Feeder 2)
        $res = $this->sectionService->verifyAssetSection(101, 3, '198501012010011001', 'Test cross feeder');

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Invariant 5G-A Violated', $res['error']);
    }

    public function testValidSectionVerificationUpdatesAssetAndRecordsHistory(): void
    {
        $this->db->table('assets')->insert([
            'id'                        => 102,
            'kode_asset'                => 'AST-PANJI-102',
            'nama_asset'                => 'Tiang Panji 102',
            'jenis_asset'               => 'JTM',
            'penyulang_id'              => 1,
            'section_id'                => null,
            'section_resolution_method' => 'UNRESOLVED',
        ]);

        $res = $this->sectionService->verifyAssetSection(102, 1, '198501012010011001', 'Survey Tiang KM 1', 12);

        $this->assertTrue($res['success']);
        $this->assertSame(1, $res['new_section_id']);
        $this->assertSame(12, $res['field_sequence']);
        $this->assertSame('FIELD_VERIFIED', $res['resolution_method']);

        // Check asset updated in DB
        $asset = $this->db->table('assets')->where('id', 102)->get()->getRowArray();
        $this->assertSame(1, (int)$asset['section_id']);
        $seqValue = !empty($asset['field_sequence']) ? (int)$asset['field_sequence'] : (!empty($asset['sequence_no']) ? (int)$asset['sequence_no'] : null);
        $this->assertSame(12, $seqValue);
        $this->assertSame('FIELD_VERIFIED', $asset['section_resolution_method']);

        // Check history recorded
        $history = $this->sectionService->getAssetSectionHistory(102);
        $this->assertCount(1, $history);
        $this->assertSame(1, (int)$history[0]['new_section_id']);
        $this->assertSame('Survey Tiang KM 1', $history[0]['reason']);
    }

    public function testFeederSectionSummaryReflectsResolutionCompleteness(): void
    {
        $summary = $this->sectionService->getFeederSectionResolutionSummary(1);

        $this->assertTrue($summary['success']);
        $this->assertArrayHasKey('completeness_ratio', $summary);
        $this->assertArrayHasKey('section_distribution', $summary);
    }
}
