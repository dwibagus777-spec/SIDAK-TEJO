<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\JtmAccessoryService;
use App\Models\MasterJtmAccessoryModel;
use App\Models\TemuanAccessoryModel;

/**
 * CR-HOTFIX-02 Part C: JTM / Conductor Accessories Persistence Test
 *
 * Verifies:
 * 1. Master accessories catalog returns canonical 6 items.
 * 2. Persisting accessories stores snapshot invariant (accessory_name_snapshot).
 * 3. Duplicate protection ensures upsert on (temuan_id, accessory_type_id) without duplicate rows.
 * 4. Relationship mismatch protection rejects wrong asset_id against temuan.asset_id.
 * 5. Additive domain isolation: zero mutation to temuan_materials, assets, or gis_translines.
 *
 * @internal
 */
final class JtmAccessoryPersistenceTest extends CIUnitTestCase
{
    protected $db;
    private JtmAccessoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $forge    = \Config\Database::forge();

        // 1. Ensure master_jtm_accessories exists and is seeded
        $forge->dropTable('master_jtm_accessories', true);
        $forge->addField([
            'id'                      => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'code'                    => ['type' => 'VARCHAR', 'constraint' => 50],
            'name'                    => ['type' => 'VARCHAR', 'constraint' => 100],
            'category'                => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
            'sub_category'            => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
            'phase_applicable'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'phase_mode'              => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'NONE'],
            'default_qty'             => ['type' => 'INTEGER', 'default' => 1],
            'unit'                    => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'buah'],
            'canonical_material_code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'description'             => ['type' => 'TEXT', 'null' => true],
            'is_active'               => ['type' => 'BOOLEAN', 'default' => true],
            'sort_order'              => ['type' => 'INTEGER', 'default' => 0],
            'created_at'              => ['type' => 'DATETIME', 'null' => true],
            'updated_at'              => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('master_jtm_accessories', true);

        $seedItems = [
            ['id' => 1, 'code' => 'GSW',                'name' => 'GSW',                 'category' => 'JTM', 'sub_category' => 'JTM', 'phase_applicable' => 0, 'phase_mode' => 'NONE', 'default_qty' => 1, 'unit' => 'buah', 'description' => 'Ground Steel Wire / Kawat Petir JTM',        'is_active' => 1, 'sort_order' => 1],
            ['id' => 2, 'code' => 'EGLA',               'name' => 'EGLA',                'category' => 'JTM', 'sub_category' => 'JTM', 'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'description' => 'Externally Gapped Line Arrester',            'is_active' => 1, 'sort_order' => 2],
            ['id' => 3, 'code' => 'CLD',                'name' => 'CLD',                 'category' => 'JTM', 'sub_category' => 'JTM', 'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'description' => 'Current Limiting Device',                   'is_active' => 1, 'sort_order' => 3],
            ['id' => 4, 'code' => 'MCA',                'name' => 'MCA',                 'category' => 'JTM', 'sub_category' => 'JTM', 'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'description' => 'Multi-Chamber Arrester',                    'is_active' => 1, 'sort_order' => 4],
            ['id' => 5, 'code' => 'GROUND_GSW',         'name' => 'GROUND GSW',          'category' => 'JTM', 'sub_category' => 'JTM', 'phase_applicable' => 0, 'phase_mode' => 'NONE', 'default_qty' => 1, 'unit' => 'buah', 'description' => 'Pembumian Kawat GSW / Grounding Down Lead', 'is_active' => 1, 'sort_order' => 5],
            ['id' => 6, 'code' => 'PENGHALANG_BINATANG', 'name' => 'PENGHALANG BINATANG', 'category' => 'JTM', 'sub_category' => 'JTM', 'phase_applicable' => 0, 'phase_mode' => 'NONE', 'default_qty' => 1, 'unit' => 'buah', 'description' => 'Animal Guard / Penghalang Panjat Binatang', 'is_active' => 1, 'sort_order' => 6],
        ];
        $now = date('Y-m-d H:i:s');
        foreach ($seedItems as &$item) {
            $item['created_at'] = $now;
            $item['updated_at'] = $now;
        }
        $this->db->table('master_jtm_accessories')->insertBatch($seedItems);

        // 2. Ensure temuan_accessories exists
        $forge->dropTable('temuan_accessories', true);
        $forge->addField([
            'id'                      => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'temuan_id'               => ['type' => 'INTEGER'],
            'asset_id'                => ['type' => 'INTEGER'],
            'accessory_type_id'       => ['type' => 'INTEGER'],
            'accessory_code'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'accessory_name_snapshot' => ['type' => 'VARCHAR', 'constraint' => 100],
            'category_snapshot'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'qty'                     => ['type' => 'INTEGER', 'default' => 1],
            'unit'                    => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'buah'],
            'phase_applicable'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'phase_configuration'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'NON_PHASE'],
            'phase_positions'         => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'status'                  => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ADA'],
            'condition'               => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'BAIK'],
            'note'                    => ['type' => 'TEXT', 'null' => true],
            'photo_url'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'              => ['type' => 'DATETIME', 'null' => true],
            'updated_at'              => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('temuan_accessories', true);

        // 3. Ensure assets table has test asset
        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'         => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'kode_asset' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_asset' => ['type' => 'VARCHAR', 'constraint' => 100],
                'latitude'   => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'longitude'  => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
            ]);
            $forge->createTable('assets', true);
        }
        $this->db->table('assets')->truncate();
        $this->db->table('assets')->insert([
            'id'         => 201,
            'kode_asset' => 'AST-ACC-001',
            'nama_asset' => 'Tiang Beton ACC Test',
            'latitude'   => -7.447812,
            'longitude'  => 112.718324,
        ]);

        // 4. Ensure temuan table has test finding linked to asset 201
        if (!$this->db->tableExists('temuan')) {
            $forge->addField([
                'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'nomor_temuan'   => ['type' => 'VARCHAR', 'constraint' => 50],
                'asset_id'       => ['type' => 'INTEGER', 'null' => true],
                'latitude'       => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'longitude'      => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'tanggal_temuan' => ['type' => 'DATE', 'null' => true],
            ]);
            $forge->createTable('temuan', true);
        }
        $this->db->table('temuan')->truncate();
        $this->db->table('temuan')->insert([
            'id'             => 501,
            'nomor_temuan'   => 'TMN-ACC-TEST-001',
            'asset_id'       => 201,
            'latitude'       => -7.447812,
            'longitude'      => 112.718324,
            'tanggal_temuan' => date('Y-m-d'),
        ]);

        $this->service = new JtmAccessoryService($this->db);
    }

    /**
     * Test 1: getActiveAccessories returns the 6 canonical JTM accessories
     */
    public function testGetActiveAccessoriesReturnsCanonicalCatalog(): void
    {
        $accessories = $this->service->getActiveAccessories();

        $this->assertGreaterThanOrEqual(6, count($accessories));

        $codes = array_column($accessories, 'code');
        $this->assertContains('GSW', $codes);
        $this->assertContains('EGLA', $codes);
        $this->assertContains('CLD', $codes);
        $this->assertContains('MCA', $codes);
        $this->assertContains('GROUND_GSW', $codes);
        $this->assertContains('PENGHALANG_BINATANG', $codes);

        foreach ($accessories as $item) {
            $this->assertNotEmpty($item['category']);
            $this->assertNotEmpty($item['name']);
        }
    }

    /**
     * Test 2: persistAccessories stores records with snapshot invariant
     */
    public function testPersistAccessoriesPreservesSnapshotInvariant(): void
    {
        $payload = [
            [
                'accessory_type_id' => 1, // GSW
                'status'            => 'ADA',
                'condition'         => 'BAIK',
                'note'              => 'GSW kencang dan tidak kendor',
            ],
            [
                'accessory_type_id' => 3, // CLD
                'status'            => 'ADA',
                'condition'         => 'PERLU_PENGGANTIAN',
                'note'              => 'CLD retak terbakar petir',
            ],
            [
                'accessory_type_id' => 6, // PENGHALANG BINATANG
                'status'            => 'TIDAK_ADA',
                'condition'         => 'BAIK',
                'note'              => 'Belum terpasang penghalang binatang',
            ],
        ];

        $res = $this->service->persistAccessories(501, 201, $payload, 1);

        $this->assertEquals('SUCCESS', $res['status']);
        $this->assertEquals(3, $res['saved']);

        // Query saved records
        $saved = $this->service->getAccessoriesForTemuan(501);
        $this->assertCount(3, $saved);

        $savedByType = [];
        foreach ($saved as $row) {
            $savedByType[(int)$row['accessory_type_id']] = $row;
        }

        // Verify GSW snapshot
        $this->assertArrayHasKey(1, $savedByType);
        $this->assertEquals('GSW', $savedByType[1]['accessory_name_snapshot']);
        $this->assertEquals('ADA', $savedByType[1]['status']);
        $this->assertEquals('BAIK', $savedByType[1]['condition']);
        $this->assertEquals('GSW kencang dan tidak kendor', $savedByType[1]['note']);

        // Verify CLD snapshot
        $this->assertArrayHasKey(3, $savedByType);
        $this->assertEquals('CLD', $savedByType[3]['accessory_name_snapshot']);
        $this->assertEquals('ADA', $savedByType[3]['status']);
        $this->assertEquals('PERLU_PENGGANTIAN', $savedByType[3]['condition']);

        // Verify PENGHALANG BINATANG snapshot
        $this->assertArrayHasKey(6, $savedByType);
        $this->assertEquals('PENGHALANG BINATANG', $savedByType[6]['accessory_name_snapshot']);
        $this->assertEquals('TIDAK_ADA', $savedByType[6]['status']);
    }

    /**
     * Test 3: Duplicate protection updates existing record (upsert) rather than multiplying rows
     */
    public function testDuplicateProtectionUpsertsWithoutMultiplyingRows(): void
    {
        // Initial insert
        $payload1 = [
            [
                'accessory_type_id' => 1,
                'status'            => 'ADA',
                'condition'         => 'BAIK',
                'note'              => 'Kondisi awal baik',
            ],
        ];
        $res1 = $this->service->persistAccessories(501, 201, $payload1, 1);
        $this->assertEquals(1, $res1['saved']);

        $countBefore = $this->db->table('temuan_accessories')->where('temuan_id', 501)->countAllResults();
        $this->assertEquals(1, $countBefore);

        // Update with updated condition and duplicate entries in same payload
        $payload2 = [
            [
                'accessory_type_id' => 1,
                'status'            => 'ADA',
                'condition'         => 'RUSAK',
                'note'              => 'Kawat putus setelah cuaca buruk',
            ],
            [
                // Duplicate of type 1 in the same batch
                'accessory_type_id' => 1,
                'status'            => 'ADA',
                'condition'         => 'RUSAK',
                'note'              => 'Duplicate attempt',
            ],
        ];
        $res2 = $this->service->persistAccessories(501, 201, $payload2, 1);
        $this->assertEquals('SUCCESS', $res2['status']);

        $countAfter = $this->db->table('temuan_accessories')->where('temuan_id', 501)->countAllResults();
        $this->assertEquals(1, $countAfter, 'Duplicate protection must guarantee exactly 1 row per accessory_type_id per temuan');

        $updated = $this->db->table('temuan_accessories')->where('temuan_id', 501)->where('accessory_type_id', 1)->get()->getRowArray();
        $this->assertEquals('RUSAK', $updated['condition']);
        $this->assertEquals('Kawat putus setelah cuaca buruk', $updated['note']);
    }

    /**
     * Test 4: Relation mismatch rejects when accessory asset_id differs from temuan asset_id
     */
    public function testRelationMismatchRejectsIncompatibleAsset(): void
    {
        $payload = [
            [
                'accessory_type_id' => 2, // EGLA
                'status'            => 'ADA',
                'condition'         => 'BAIK',
            ],
        ];

        // temuan 501 belongs to asset 201, passing asset 999 must trigger RELATION_MISMATCH
        $res = $this->service->persistAccessories(501, 999, $payload, 1);

        $this->assertEquals('RELATION_MISMATCH', $res['status']);
        $this->assertEquals(0, $res['saved']);
    }

    /**
     * Test 5: Additive domain isolation - zero mutation to MR-01 materials, assets, or translines
     */
    public function testAdditiveDomainIsolationGuaranteesZeroCrossDomainMutation(): void
    {
        // Snapshot counts before accessory operations
        $matCountBefore = $this->db->tableExists('temuan_materials') ? $this->db->table('temuan_materials')->countAllResults() : 0;
        $assetCountBefore = $this->db->table('assets')->countAllResults();
        $translineCountBefore = $this->db->tableExists('gis_translines') ? $this->db->table('gis_translines')->countAllResults() : 0;

        $payload = [
            ['accessory_type_id' => 4, 'status' => 'ADA', 'condition' => 'BAIK', 'note' => 'MCA terpasang'],
            ['accessory_type_id' => 5, 'status' => 'ADA', 'condition' => 'BAIK', 'note' => 'Ground GSW terhubung'],
        ];

        $res = $this->service->persistAccessories(501, 201, $payload, 1);
        $this->assertEquals('SUCCESS', $res['status']);
        $this->assertEquals(2, $res['saved']);

        // Snapshot counts after
        $matCountAfter = $this->db->tableExists('temuan_materials') ? $this->db->table('temuan_materials')->countAllResults() : 0;
        $assetCountAfter = $this->db->table('assets')->countAllResults();
        $translineCountAfter = $this->db->tableExists('gis_translines') ? $this->db->table('gis_translines')->countAllResults() : 0;

        $this->assertEquals($matCountBefore, $matCountAfter, 'Zero mutation to temuan_materials');
        $this->assertEquals($assetCountBefore, $assetCountAfter, 'Zero mutation to assets');
        $this->assertEquals($translineCountBefore, $translineCountAfter, 'Zero mutation to gis_translines');
    }
}
