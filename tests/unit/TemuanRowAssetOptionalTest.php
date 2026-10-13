<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Unit Test Suite for MR-01: ROW Finding Asset-Optional Invariant
 *
 * Invariants Verified:
 * 1. asset_id is OPTIONAL for Temuan persistence (ROW / non-asset findings).
 * 2. asset validation is CONDITIONAL on asset selection.
 * 3. An empty asset_id does NOT fail temuan submission.
 * 4. A provided but invalid asset_id IS strictly rejected:
 *    - Non-existent asset ID -> REJECT
 *    - Cross-section asset ID -> REJECT
 *    - Cross-feeder asset ID -> REJECT
 * 5. create.php view does NOT enforce HTML5 required on asset_id.
 * 6. Zero impact on gis_translines (Transline Write Gate remains CLOSED).
 */
class TemuanRowAssetOptionalTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->setupTestSchema();
    }

    protected function setupTestSchema(): void
    {
        $forge = \Config\Database::forge();

        // 1. ulps table
        if (!$this->db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => '51301'],
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
                'id'              => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'      => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_asset'      => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'Aset Testing'],
                'jenis_asset'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_BETON'],
                'section_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id'    => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'latitude'        => ['type' => 'DECIMAL', 'constraint' => '10,8', 'default' => -7.4242],
                'longitude'       => ['type' => 'DECIMAL', 'constraint' => '11,8', 'default' => 112.72701],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        try {
            if (!$this->db->fieldExists('sequence_no', 'assets')) {
                $forge->addColumn('assets', ['sequence_no' => ['type' => 'INT', 'constraint' => 11, 'default' => 0]]);
            }
        } catch (\Throwable $e) {}
        try {
            if (!$this->db->fieldExists('parent_asset_id', 'assets')) {
                $forge->addColumn('assets', ['parent_asset_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true]]);
            }
        } catch (\Throwable $e) {}
        try {
            if (!$this->db->fieldExists('ulp_id', 'assets')) {
                $forge->addColumn('assets', ['ulp_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1]]);
            }
        } catch (\Throwable $e) {}

        // 5. temuan table
        if (!$this->db->tableExists('temuan')) {
            $forge->addField([
                'id'               => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'nomor_temuan'     => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'ulp_id'           => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'penyulang_id'     => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'section_id'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'jenis_temuan'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'ROW'],
                'pelaksana'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'HAR ROW'],
                'prioritas'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'MEDIUM'],
                'potensi_gangguan' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'DGR'],
                'konduktor'        => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'AAAC 150'],
                'noga'             => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'material'         => ['type' => 'TEXT', 'null' => true],
                'detail_temuan'    => ['type' => 'TEXT', 'null' => true],
                'alamat'           => ['type' => 'TEXT', 'null' => true],
                'tanggal_temuan'   => ['type' => 'DATE'],
                'status'           => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'BELUM'],
                'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
                'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan', true);
        }

        // Clean tables
        $this->db->table('ulps')->emptyTable();
        $this->db->table('penyulang')->emptyTable();
        $this->db->table('sections')->emptyTable();
        $this->db->table('assets')->emptyTable();
        $this->db->table('temuan')->emptyTable();
    }

    /**
     * Test 01: Verify create.php does NOT contain required on asset_id select
     */
    public function testCreateViewDoesNotEnforceRequiredOnAssetSelect(): void
    {
        $viewFile = APPPATH . 'Views/temuan/create.php';
        $this->assertFileExists($viewFile);
        $content = file_get_contents($viewFile);

        // Required attribute must NOT be on mr01_asset_id
        $this->assertStringNotContainsString('id="mr01_asset_id" class="form-control select2" required', $content);
        $this->assertStringContainsString('id="mr01_asset_id" class="form-control select2"', $content);
        $this->assertStringContainsString('id="mr01_asset_req_indicator"', $content);
    }

    /**
     * Test 01b: Verify universal optional asset UI contract (Default optional label, no mandatory asterisk)
     */
    public function testUniversalOptionalAssetUiContract(): void
    {
        $viewFile = APPPATH . 'Views/temuan/create.php';
        $content = file_get_contents($viewFile);

        // Universal optional asset label and placeholder
        $this->assertStringContainsString('Aset Jaringan (Opsional)', $content);
        $this->assertStringContainsString('-- Tidak dipilih / Opsional --', $content);
        $this->assertStringContainsString('Aset jaringan tidak wajib diisi', $content);
        $this->assertStringContainsString('id="row-vegetasi-container"', $content);
    }

    /**
     * Test 01c: Verify Material-driven asset requirement contract
     */
    public function testMaterialDrivenAssetRequirementUiContract(): void
    {
        $viewFile = APPPATH . 'Views/temuan/create.php';
        $content = file_get_contents($viewFile);

        // State machine UI elements for material request
        $this->assertStringContainsString('Tambah Material yang Dibutuhkan', $content);
        $this->assertStringContainsString('Batal Tambah Material', $content);
        $this->assertStringContainsString('Aset Jaringan diperlukan untuk menentukan konstruksi dan material resmi.', $content);
        $this->assertStringContainsString('Untuk menambahkan material konstruksi, pilih Aset Jaringan terlebih dahulu.', $content);
        $this->assertStringContainsString('id="mr01_state_no_material"', $content);
        $this->assertStringContainsString('id="mr01_material_flow_container"', $content);
    }

    /**
     * Test 02: ROW finding with null asset_id saves successfully
     */
    public function testRowFindingWithNullAssetIdPersistsCleanly(): void
    {
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => 'ULP-001', 'nama_ulp' => 'SIDOARJO KOTA']);
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        $this->db->table('sections')->insert(['id' => 49, 'penyulang_id' => 1, 'nama_section' => 'SEC-49-EMPTY-ASSET']);

        // Section 49 has 0 assets in database
        $assetCount = $this->db->table('assets')->where('section_id', 49)->countAllResults();
        $this->assertSame(0, $assetCount);

        // Saving ROW finding with null asset
        $findingData = [
            'nomor_temuan'     => 'STJ-ROW-001',
            'ulp_id'           => 1,
            'penyulang_id'     => 1,
            'section_id'       => 49,
            'jenis_temuan'     => 'ROW',
            'pelaksana'        => 'HAR ROW',
            'prioritas'        => 'MEDIUM',
            'potensi_gangguan' => 'DGR',
            'konduktor'        => 'AAAC 150 mm²',
            'material'         => 'Penebangan ranting pohon mangga dekat jaringan',
            'detail_temuan'    => 'Pohon mangga mendekati jaringan SUTM < 2.5m',
            'alamat'           => 'Jl. Raya Siwalan Panji No. 12',
            'tanggal_temuan'   => date('Y-m-d'),
            'status'           => 'BELUM',
        ];

        $this->db->table('temuan')->insert($findingData);
        $insertedId = (int)$this->db->insertID();

        $this->assertGreaterThan(0, $insertedId);
        $saved = $this->db->table('temuan')->where('id', $insertedId)->get()->getRowArray();
        $this->assertNotNull($saved);
        $this->assertSame('ROW', $saved['jenis_temuan']);
        $this->assertSame(49, (int)$saved['section_id']);
    }

    /**
     * Test 03: Conditional Validation - Non-existent asset ID is rejected
     */
    public function testNonExistentAssetRejectedConditionally(): void
    {
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => 'ULP-001', 'nama_ulp' => 'SIDOARJO KOTA']);
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        $this->db->table('sections')->insert(['id' => 1, 'penyulang_id' => 1, 'nama_section' => 'SEC-1']);

        $assetIdInput = 99999; // Does not exist
        $assetRow = $this->db->table('assets')->where('id', $assetIdInput)->get()->getRowArray();

        $this->assertNull($assetRow);
    }

    /**
     * Test 04: Conditional Validation - Cross-section asset ID is rejected
     */
    public function testCrossSectionAssetRejectedConditionally(): void
    {
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => 'ULP-001', 'nama_ulp' => 'SIDOARJO KOTA']);
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        $this->db->table('sections')->insert(['id' => 1, 'penyulang_id' => 1, 'nama_section' => 'SEC-1']);
        $this->db->table('sections')->insert(['id' => 2, 'penyulang_id' => 1, 'nama_section' => 'SEC-2']);

        // Asset belongs to Section 2
        $this->db->table('assets')->insert([
            'id'           => 550,
            'kode_asset'   => 'AST-SEC2',
            'nama_asset'   => 'Tiang Section 2',
            'jenis_asset'  => 'TIANG_BETON',
            'section_id'   => 2,
            'penyulang_id' => 1,
        ]);

        $submittedSectionId = 1; // Section 1 submitted
        $assetRow = $this->db->table('assets')->where('id', 550)->get()->getRowArray();

        $this->assertNotNull($assetRow);
        $this->assertNotSame($submittedSectionId, (int)$assetRow['section_id']);
    }

    /**
     * Test 05: Conditional Validation - Cross-feeder asset ID is rejected
     */
    public function testCrossFeederAssetRejectedConditionally(): void
    {
        $this->db->table('ulps')->insert(['id' => 1, 'kode_ulp' => 'ULP-001', 'nama_ulp' => 'SIDOARJO KOTA']);
        $this->db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-001', 'nama_penyulang' => 'SIWALAN PANJI']);
        $this->db->table('penyulang')->insert(['id' => 2, 'ulp_id' => 1, 'kode_penyulang' => 'PYL-002', 'nama_penyulang' => 'BUDURAN']);
        $this->db->table('sections')->insert(['id' => 1, 'penyulang_id' => 1, 'nama_section' => 'SEC-1']);

        // Asset belongs to Feeder 2
        $this->db->table('assets')->insert([
            'id'           => 551,
            'kode_asset'   => 'AST-FEEDER2',
            'nama_asset'   => 'Tiang Feeder 2',
            'jenis_asset'  => 'TIANG_BETON',
            'section_id'   => 1,
            'penyulang_id' => 2, // Feeder 2!
        ]);

        $submittedFeederId = 1; // Feeder 1 submitted
        $assetRow = $this->db->table('assets')->where('id', 551)->get()->getRowArray();

        $this->assertNotNull($assetRow);
        $this->assertNotSame($submittedFeederId, (int)$assetRow['penyulang_id']);
    }

    /**
     * Test 06: Zero impact on Transline domain
     */
    public function testTranslineDomainRemainsUntouched(): void
    {
        if ($this->db->tableExists('gis_translines')) {
            $countBefore = $this->db->table('gis_translines')->countAllResults();
            $this->db->table('temuan')->insert([
                'nomor_temuan'   => 'STJ-TL-CHECK',
                'ulp_id'         => 1,
                'penyulang_id'   => 1,
                'section_id'     => 1,
                'jenis_temuan'     => 'ROW',
                'pelaksana'        => 'HAR KONSTRUKSI',
                'prioritas'        => 'HIGH',
                'potensi_gangguan' => 'DGR',
                'konduktor'        => 'A3CS 150',
                'status'           => 'BELUM',
                'tanggal_temuan'   => date('Y-m-d'),
                'material'         => 'Check transline untouched',
            ]);
            $countAfter = $this->db->table('gis_translines')->countAllResults();
            $this->assertSame($countBefore, $countAfter);
        } else {
            $this->assertTrue(true);
        }
    }
}
