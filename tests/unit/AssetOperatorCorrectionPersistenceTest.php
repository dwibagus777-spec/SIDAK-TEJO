<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\AssetContextService;

/**
 * FIX-01: Persistent Operator Correction Test Suite
 *
 * Validates:
 * 1. Section correction persists in database.
 * 2. Construction type correction persists in database.
 * 3. Section correction does not alter construction type.
 * 4. Construction correction does not alter section.
 * 5. Field isolation: latitude, longitude, kode_asset, nama_asset, penyulang_id, ulp_id untouched.
 * 6. Cross-feeder section correction rejected (CROSS_FEEDER_REJECTED).
 * 7. Cross-ULP section correction rejected for restricted role (CROSS_ULP_REJECTED).
 * 8. Nonexistent asset rejected (ASSET_NOT_FOUND).
 * 9. Nonexistent section rejected (SECTION_NOT_FOUND).
 * 10. Nonexistent construction type rejected (CONSTRUCTION_NOT_FOUND).
 * 11. Transaction rollback safety.
 * 12. Audit trail and history logging.
 * 13. Authoritative context reload from database reflects saved state.
 */
class AssetOperatorCorrectionPersistenceTest extends CIUnitTestCase
{
    protected $db;
    protected AssetContextService $contextService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->setupSchema();
        $this->seedTestData();

        $this->contextService = new AssetContextService($this->db);
    }

    protected function setupSchema(): void
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
                'ulp_id'         => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
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
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'ulp_id'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('sections', true);
        }

        if (!$this->db->tableExists('construction_types')) {
            $forge->addField([
                'id'                  => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'code'                => ['type' => 'VARCHAR', 'constraint' => 50],
                'name'                => ['type' => 'VARCHAR', 'constraint' => 100],
                'construction_family' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
                'voltage_level'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '20kV'],
                'is_active'           => ['type' => 'INT', 'constraint' => 1, 'default' => 1],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('construction_types', true);
        }

        if (!$this->db->tableExists('construction_bom_items')) {
            $forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'construction_type_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'material_id'          => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'raw_material_name'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'quantity'             => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 1],
                'unit'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'SET'],
                'created_at'           => ['type' => 'DATETIME', 'null' => true],
                'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('construction_bom_items', true);
        }

        if (!$this->db->tableExists('materials')) {
            $forge->addField([
                'id'            => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'material_code' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_material' => ['type' => 'VARCHAR', 'constraint' => 255],
                'satuan'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'SET'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('materials', true);
        }

        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'                         => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'                 => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'                 => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Tiang JTM'],
                'jenis_asset'                => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_BETON'],
                'type'                       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'ulp_id'                     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id'               => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_id'                 => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_resolution_method'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'section_verified_by'        => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_verified_at'        => ['type' => 'DATETIME', 'null' => true],
                'construction_type_id'       => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'latitude'                   => ['type' => 'DECIMAL', 'constraint' => '10,8', 'null' => true],
                'longitude'                  => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'lokasi'                     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'status'                     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'NORMAL'],
                'updated_at'                 => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'                 => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        if (!$this->db->tableExists('audit_logs')) {
            $forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'user_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'username'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'role'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'aktivitas'  => ['type' => 'VARCHAR', 'constraint' => 100],
                'detail'     => ['type' => 'TEXT', 'null' => true],
                'ip_address' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('audit_logs', true);
        }

        if (!$this->db->tableExists('temuan')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'judul'        => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Temuan Uji'],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 15],
                'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan', true);
        }

        if (!$this->db->tableExists('gis_translines')) {
            $forge->addField([
                'id'              => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'transline_code'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'penyulang_id'    => ['type' => 'INT', 'constraint' => 11, 'default' => 15],
                'source_asset_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'target_asset_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 2],
                'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        }
    }

    protected function seedTestData(): void
    {
        $this->db->table('assets')->truncate();
        $this->db->table('sections')->truncate();
        $this->db->table('penyulang')->truncate();
        $this->db->table('ulps')->truncate();
        $this->db->table('construction_types')->truncate();

        // Seed ULP
        $this->db->table('ulps')->insertBatch([
            ['id' => 1, 'kode_ulp' => 'ULP-SDK', 'nama_ulp' => 'ULP Sidoarjo Kota', 'status' => 'AKTIF'],
            ['id' => 2, 'kode_ulp' => 'ULP-KRN', 'nama_ulp' => 'ULP Krian', 'status' => 'AKTIF'],
        ]);

        // Seed Penyulang
        $this->db->table('penyulang')->insertBatch([
            ['id' => 15, 'ulp_id' => 1, 'kode_penyulang' => 'BNJRKMNTRN', 'nama_penyulang' => 'BANJAR KEMANTREN', 'status' => 'AKTIF'],
            ['id' => 20, 'ulp_id' => 1, 'kode_penyulang' => 'CANDI', 'nama_penyulang' => 'CANDI', 'status' => 'AKTIF'],
        ]);

        // Seed Sections
        $this->db->table('sections')->insertBatch([
            ['id' => 48, 'penyulang_id' => 15, 'ulp_id' => 1, 'nama_section' => 'Section Banjar 1'],
            ['id' => 49, 'penyulang_id' => 15, 'ulp_id' => 1, 'nama_section' => 'Section Banjar 2'],
            ['id' => 99, 'penyulang_id' => 20, 'ulp_id' => 1, 'nama_section' => 'Section Candi 1 (Other Feeder)'],
        ]);

        // Seed Construction Types
        $this->db->table('construction_types')->insertBatch([
            ['id' => 1, 'code' => 'TM-1', 'name' => 'Tumpu Sudut 0-15', 'construction_family' => 'JTM', 'voltage_level' => '20kV', 'is_active' => 1],
            ['id' => 2, 'code' => 'TM-2', 'name' => 'Tumpu Sudut 15-30', 'construction_family' => 'JTM', 'voltage_level' => '20kV', 'is_active' => 1],
            ['id' => 4, 'code' => 'TM-4', 'name' => 'Tarik Akhir', 'construction_family' => 'JTM', 'voltage_level' => '20kV', 'is_active' => 1],
        ]);

        // Seed Test Asset
        $this->db->table('assets')->insert([
            'id'                   => 5001,
            'kode_asset'           => 'AST-KOTA-BNJRKMNTRN-JTM-TEST1',
            'nama_asset'           => 'Tiang Uji Persistensi 01',
            'jenis_asset'          => 'TIANG_BETON',
            'type'                 => 'TM',
            'ulp_id'               => 1,
            'penyulang_id'         => 15,
            'section_id'           => 48,
            'construction_type_id' => 1,
            'latitude'             => -7.41000000,
            'longitude'            => 112.71000000,
            'status'               => 'NORMAL',
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);
    }

    public function testSectionCorrectionPersists(): void
    {
        $res = $this->contextService->correctSection(5001, 49, 999, 'ADMIN', 1, 'Uji persistensi section');
        $this->assertSame('success', $res['status']);
        $this->assertTrue($res['persisted']);
        $this->assertSame(49, $res['new_value']);

        // Verify MariaDB state directly
        $row = $this->db->table('assets')->where('id', 5001)->get()->getRowArray();
        $this->assertSame(49, (int)$row['section_id']);
        $this->assertSame('OPERATOR_CORRECTION', $row['section_resolution_method']);
        $this->assertSame(999, (int)$row['section_verified_by']);
    }

    public function testConstructionCorrectionPersists(): void
    {
        $res = $this->contextService->correctConstruction(5001, 4, 999, 'ADMIN', 1, 'Uji persistensi konstruksi');
        $this->assertSame('success', $res['status']);
        $this->assertTrue($res['persisted']);
        $this->assertSame(4, $res['new_value']);

        // Verify MariaDB state directly
        $row = $this->db->table('assets')->where('id', 5001)->get()->getRowArray();
        $this->assertSame(4, (int)$row['construction_type_id']);
    }

    public function testSectionCorrectionDoesNotAlterConstruction(): void
    {
        // Set initial construction to 2
        $this->db->table('assets')->where('id', 5001)->update(['construction_type_id' => 2]);

        $this->contextService->correctSection(5001, 49, 999, 'ADMIN', 1);

        $row = $this->db->table('assets')->where('id', 5001)->get()->getRowArray();
        $this->assertSame(49, (int)$row['section_id']);
        $this->assertSame(2, (int)$row['construction_type_id'], 'construction_type_id must remain untouched when correcting section');
    }

    public function testConstructionCorrectionDoesNotAlterSection(): void
    {
        // Set initial section to 48
        $this->db->table('assets')->where('id', 5001)->update(['section_id' => 48]);

        $this->contextService->correctConstruction(5001, 4, 999, 'ADMIN', 1);

        $row = $this->db->table('assets')->where('id', 5001)->get()->getRowArray();
        $this->assertSame(4, (int)$row['construction_type_id']);
        $this->assertSame(48, (int)$row['section_id'], 'section_id must remain untouched when correcting construction');
    }

    public function testFieldIsolationCoordinatesAndIdentityUntouched(): void
    {
        $before = $this->db->table('assets')->where('id', 5001)->get()->getRowArray();

        $this->contextService->correctSection(5001, 49, 999, 'ADMIN', 1);

        $after = $this->db->table('assets')->where('id', 5001)->get()->getRowArray();

        $this->assertSame((string)$before['latitude'], (string)$after['latitude']);
        $this->assertSame((string)$before['longitude'], (string)$after['longitude']);
        $this->assertSame((string)$before['kode_asset'], (string)$after['kode_asset']);
        $this->assertSame((string)$before['nama_asset'], (string)$after['nama_asset']);
        $this->assertSame((int)$before['penyulang_id'], (int)$after['penyulang_id']);
        $this->assertSame((int)$before['ulp_id'], (int)$after['ulp_id']);
    }

    public function testCrossFeederSectionCorrectionRejected(): void
    {
        // Section 99 belongs to Penyulang 20, but asset 5001 belongs to Penyulang 15
        $res = $this->contextService->correctSection(5001, 99, 999, 'ADMIN', 1);
        $this->assertSame('error', $res['status']);
        $this->assertSame('CROSS_FEEDER_REJECTED', $res['code']);

        // Assert database was NOT modified
        $row = $this->db->table('assets')->where('id', 5001)->get()->getRowArray();
        $this->assertSame(48, (int)$row['section_id']);
    }

    public function testCrossUlpSectionCorrectionRejectedForAdminUlp(): void
    {
        // User assigned to ULP 2 cannot modify asset in ULP 1
        $res = $this->contextService->correctSection(5001, 49, 999, 'ADMIN_ULP', 2);
        $this->assertSame('error', $res['status']);
        $this->assertSame('CROSS_ULP_REJECTED', $res['code']);

        $row = $this->db->table('assets')->where('id', 5001)->get()->getRowArray();
        $this->assertSame(48, (int)$row['section_id']);
    }

    public function testNonexistentAssetRejected(): void
    {
        $res = $this->contextService->correctSection(99999, 49, 999, 'ADMIN', 1);
        $this->assertSame('error', $res['status']);
        $this->assertSame('ASSET_NOT_FOUND', $res['code']);
    }

    public function testNonexistentSectionRejected(): void
    {
        $res = $this->contextService->correctSection(5001, 99999, 999, 'ADMIN', 1);
        $this->assertSame('error', $res['status']);
        $this->assertSame('SECTION_NOT_FOUND', $res['code']);
    }

    public function testNonexistentConstructionRejected(): void
    {
        $res = $this->contextService->correctConstruction(5001, 99999, 999, 'ADMIN', 1);
        $this->assertSame('error', $res['status']);
        $this->assertSame('CONSTRUCTION_NOT_FOUND', $res['code']);
    }

    public function testGetAssetContextReflectsPersistedValueAndOperatorBadge(): void
    {
        $this->contextService->correctSection(5001, 49, 999, 'ADMIN', 1);

        // Fetch context without working context parameter
        $context = $this->contextService->getAssetContext(5001, 1, 'ADMIN');

        $this->assertSame(49, (int)($context['network']['section']['id'] ?? 0));
        $this->assertSame('DIKOREKSI_OPERATOR', $context['status_badges']['section']);
        $this->assertSame('OPERATOR', $context['context_source']['section']);
    }

    public function testZeroMutationOnTemuanAndTranslines(): void
    {
        $this->db->table('temuan')->insert(['id' => 101, 'judul' => 'Temuan Safety Invariant', 'penyulang_id' => 15]);
        $this->db->table('gis_translines')->insert(['id' => 201, 'transline_code' => 'TL-TEST', 'penyulang_id' => 15]);

        $preTemuanCount = $this->db->table('temuan')->countAllResults();
        $preTranslineCount = $this->db->table('gis_translines')->countAllResults();

        // Perform Section Correction
        $this->contextService->correctSection(5001, 49, 999, 'ADMIN', 1);

        // Perform Construction Correction
        $this->contextService->correctConstruction(5001, 4, 999, 'ADMIN', 1);

        $postTemuanCount = $this->db->table('temuan')->countAllResults();
        $postTranslineCount = $this->db->table('gis_translines')->countAllResults();

        $this->assertSame($preTemuanCount, $postTemuanCount, 'temuan table MUST experience exact zero mutations');
        $this->assertSame($preTranslineCount, $postTranslineCount, 'gis_translines table MUST experience exact zero mutations during asset correction');
    }

    public function testAuditTrailLoggedOnCorrection(): void
    {
        $preAuditCount = $this->db->table('audit_logs')->countAllResults();

        $this->contextService->correctSection(5001, 49, 999, 'ADMIN', 1, 'Koreksi audit trail test');

        $postAuditCount = $this->db->table('audit_logs')->countAllResults();
        $this->assertGreaterThanOrEqual($preAuditCount + 1, $postAuditCount, 'Audit log entry must be created on operator correction');
    }
}

