<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\JtmAccessoryService;
use App\Models\MasterJtmAccessoryModel;
use App\Models\TemuanAccessoryModel;

/**
 * CR-ACCESSORY-PHASE-01: JTM Accessories Phase Configuration & Canonical Units Test
 *
 * Verifies:
 * 1. 10 canonical accessories exist with correct phase applicability and canonical units.
 * 2. Canonical material unit invariant: individual items use 'buah'.
 * 3. Configuration & Observation domain separation with phase positions normalization.
 * 4. Immutable snapshot invariant protects historical records from master catalog mutation.
 * 5. Semantic object representation for UI, AI, and GIS consumption.
 *
 * @internal
 */
final class JtmAccessoryPhaseConfigurationTest extends CIUnitTestCase
{
    protected $db;
    private JtmAccessoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $forge    = \Config\Database::forge();

        // 1. Ensure master_jtm_accessories has required columns
        $forge->dropTable('master_jtm_accessories', true);
        $forge->addField([
            'id'                      => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'code'                    => ['type' => 'VARCHAR', 'constraint' => 50],
            'name'                    => ['type' => 'VARCHAR', 'constraint' => 100],
            'category'                => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'CONDUCTOR_GROUNDING'],
            'sub_category'            => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'CONDUCTOR_GROUNDING'],
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

        // Seed 10 canonical items
        $seedItems = [
            ['id' => 1,  'code' => 'GSW',                 'name' => 'GSW',                  'category' => 'CONDUCTOR_GROUNDING', 'sub_category' => 'CONDUCTOR_GROUNDING', 'phase_applicable' => 0, 'phase_mode' => 'NONE',        'default_qty' => 1, 'unit' => 'buah', 'canonical_material_code' => null,             'description' => 'Ground Steel Wire / Kawat Petir JTM',        'is_active' => 1, 'sort_order' => 1],
            ['id' => 2,  'code' => 'GROUND_GSW',          'name' => 'GROUND GSW',           'category' => 'CONDUCTOR_GROUNDING', 'sub_category' => 'CONDUCTOR_GROUNDING', 'phase_applicable' => 0, 'phase_mode' => 'NONE',        'default_qty' => 1, 'unit' => 'buah', 'canonical_material_code' => null,             'description' => 'Pembumian Kawat GSW / Grounding Down Lead', 'is_active' => 1, 'sort_order' => 2],
            ['id' => 3,  'code' => 'PENGHALANG_BINATANG', 'name' => 'PENGHALANG BINATANG',  'category' => 'CONDUCTOR_GROUNDING', 'sub_category' => 'CONDUCTOR_GROUNDING', 'phase_applicable' => 0, 'phase_mode' => 'NONE',        'default_qty' => 1, 'unit' => 'buah', 'canonical_material_code' => null,             'description' => 'Animal Guard / Penghalang Panjat Binatang', 'is_active' => 1, 'sort_order' => 3],
            ['id' => 4,  'code' => 'EGLA',                'name' => 'EGLA',                 'category' => 'PROTECTION',          'sub_category' => 'PROTECTION',          'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'canonical_material_code' => null,             'description' => 'Externally Gapped Line Arrester',            'is_active' => 1, 'sort_order' => 4],
            ['id' => 5,  'code' => 'CLD',                 'name' => 'CLD',                  'category' => 'PROTECTION',          'sub_category' => 'PROTECTION',          'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'canonical_material_code' => null,             'description' => 'Current Limiting Device',                   'is_active' => 1, 'sort_order' => 5],
            ['id' => 6,  'code' => 'MCA',                 'name' => 'MCA',                  'category' => 'PROTECTION',          'sub_category' => 'PROTECTION',          'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'canonical_material_code' => null,             'description' => 'Multi-Chamber Arrester',                    'is_active' => 1, 'sort_order' => 6],
            ['id' => 7,  'code' => 'ARRESTER',            'name' => 'Lightning Arrester',   'category' => 'PROTECTION',          'sub_category' => 'PROTECTION',          'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'canonical_material_code' => 'MAT-PROT-ARR',     'description' => 'Lightning Arrester Proteksi Tegangan Lebih', 'is_active' => 1, 'sort_order' => 7],
            ['id' => 8,  'code' => 'FCO',                 'name' => 'Fuse Cut Out (FCO)',   'category' => 'PROTECTION',          'sub_category' => 'PROTECTION',          'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'canonical_material_code' => 'MAT-PROT-FCO',     'description' => 'Fuse Cut Out Gardu / Induk',                'is_active' => 1, 'sort_order' => 8],
            ['id' => 9,  'code' => 'FCO_BRANCH',          'name' => 'FCO Percabangan',      'category' => 'PROTECTION',          'sub_category' => 'PROTECTION',          'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'canonical_material_code' => 'MAT-PROT-FCO-LAT', 'description' => 'Fuse Cut Out Percabangan / Lateral',        'is_active' => 1, 'sort_order' => 9],
            ['id' => 10, 'code' => 'FIOHL',               'name' => 'FIOHL',                'category' => 'MONITORING',          'sub_category' => 'MONITORING',          'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'canonical_material_code' => 'MAT-IND-FIOHL',    'description' => 'Fault Indicator Over Head Line',             'is_active' => 1, 'sort_order' => 10],
        ];
        $now = date('Y-m-d H:i:s');
        foreach ($seedItems as &$item) {
            $item['created_at'] = $now;
            $item['updated_at'] = $now;
        }
        $this->db->table('master_jtm_accessories')->insertBatch($seedItems);

        // 2. Ensure temuan_accessories table exists with snapshot and phase columns
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

        // 3. Ensure test asset & temuan
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
            'id'         => 301,
            'kode_asset' => 'AST-PHASE-001',
            'nama_asset' => 'Tiang SUTM Fasa Test',
            'latitude'   => -7.447812,
            'longitude'  => 112.718324,
        ]);

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
            'id'             => 701,
            'nomor_temuan'   => 'TMN-PHASE-TEST-001',
            'asset_id'       => 301,
            'latitude'       => -7.447812,
            'longitude'      => 112.718324,
            'tanggal_temuan' => date('Y-m-d'),
        ]);

        $this->service = new JtmAccessoryService($this->db);
    }

    /**
     * Test 1: Canonical 10 items catalog with phase applicability
     */
    public function testCanonicalCatalogContains10ItemsWithPhaseApplicability(): void
    {
        $items = $this->service->getActiveAccessories();
        $this->assertCount(10, $items);

        $codes = array_column($items, 'code');
        $expectedCodes = ['GSW', 'GROUND_GSW', 'PENGHALANG_BINATANG', 'EGLA', 'CLD', 'MCA', 'ARRESTER', 'FCO', 'FCO_BRANCH', 'FIOHL'];
        foreach ($expectedCodes as $expected) {
            $this->assertContains($expected, $codes);
        }

        $byCode = [];
        foreach ($items as $item) {
            $byCode[$item['code']] = $item;
        }

        // Conductor/Grounding items are non-phase
        $this->assertEquals(0, $byCode['GSW']['phase_applicable']);
        $this->assertEquals(0, $byCode['GROUND_GSW']['phase_applicable']);
        $this->assertEquals(0, $byCode['PENGHALANG_BINATANG']['phase_applicable']);

        // Protection & Monitoring items are multi-phase applicable
        $this->assertEquals(1, $byCode['EGLA']['phase_applicable']);
        $this->assertEquals(1, $byCode['CLD']['phase_applicable']);
        $this->assertEquals(1, $byCode['MCA']['phase_applicable']);
        $this->assertEquals(1, $byCode['ARRESTER']['phase_applicable']);
        $this->assertEquals(1, $byCode['FCO']['phase_applicable']);
        $this->assertEquals(1, $byCode['FCO_BRANCH']['phase_applicable']);
        $this->assertEquals(1, $byCode['FIOHL']['phase_applicable']);

        // All accessories must use canonical unit 'buah'
        foreach ($items as $item) {
            $this->assertEquals('buah', $item['unit'], "Item {$item['code']} must have canonical unit 'buah'");
        }
    }

    /**
     * Test 2: Position normalization helper
     */
    public function testNormalizePositionsHandlesVariousFormats(): void
    {
        // Array format
        $this->assertEquals(['R', 'S', 'T'], $this->service->normalizePositions(['r', 'S', 't']));
        $this->assertEquals(['R', 'S'], $this->service->normalizePositions(['R', 'S']));
        $this->assertEquals(['T'], $this->service->normalizePositions(['t']));

        // CSV string format
        $this->assertEquals(['R', 'S', 'T'], $this->service->normalizePositions('r,s,t'));
        $this->assertEquals(['R', 'T'], $this->service->normalizePositions('R, T'));
        $this->assertEquals(['S'], $this->service->normalizePositions('S'));

        // Invalid or empty
        $this->assertEquals([], $this->service->normalizePositions(''));
        $this->assertEquals([], $this->service->normalizePositions(null));
        $this->assertEquals([], $this->service->normalizePositions(['X', 'Y']));
    }

    /**
     * Test 3: Persisting phase configuration & quantities preserves Configuration domain
     */
    public function testPersistAccessoriesWithPhaseConfiguration(): void
    {
        $payload = [
            [
                'accessory_type_id'   => 10, // FIOHL
                'status'              => 'ADA',
                'condition'           => 'BAIK',
                'note'                => 'FIOHL terpasang lengkap 3 fasa',
                'qty'                 => 3,
                'unit'                => 'buah',
                'phase_applicable'    => 1,
                'phase_configuration' => '3_PHASE',
                'phase_positions'     => ['R', 'S', 'T'],
            ],
            [
                'accessory_type_id'   => 8, // FCO
                'status'              => 'ADA',
                'condition'           => 'PERLU_PENGGANTIAN',
                'note'                => 'FCO fasa R & S saja, fasa T tidak terpasang',
                'qty'                 => 2,
                'unit'                => 'buah',
                'phase_applicable'    => 1,
                'phase_configuration' => '2_PHASE',
                'phase_positions'     => ['R', 'S'],
            ],
            [
                'accessory_type_id'   => 1, // GSW (non-phase)
                'status'              => 'ADA',
                'condition'           => 'BAIK',
                'note'                => 'GSW kencang',
                'qty'                 => 1,
                'unit'                => 'buah',
                'phase_applicable'    => 0,
                'phase_configuration' => 'NON_PHASE',
                'phase_positions'     => [],
            ],
        ];

        $res = $this->service->persistAccessories(701, 301, $payload, 1);
        $this->assertEquals('SUCCESS', $res['status'], $res['message'] ?? 'no message');
        $this->assertEquals(3, $res['saved']);

        // Query structured semantic objects
        $saved = $this->service->getAccessoriesForTemuan(701);
        $this->assertCount(3, $saved);

        $byCode = [];
        foreach ($saved as $item) {
            $byCode[$item['accessory_code']] = $item;
        }

        // FIOHL verification
        $this->assertArrayHasKey('FIOHL', $byCode);
        $fiohl = $byCode['FIOHL'];
        $this->assertEquals(3, $fiohl['configuration']['qty']);
        $this->assertEquals('buah', $fiohl['configuration']['unit']);
        $this->assertTrue($fiohl['configuration']['phase_applicable']);
        $this->assertEquals('3_PHASE', $fiohl['configuration']['phase_configuration']);
        $this->assertEquals(['R', 'S', 'T'], $fiohl['configuration']['positions']);
        $this->assertEquals('3 PHASE (R-S-T)', $fiohl['configuration']['display_phase']);
        $this->assertEquals('ADA', $fiohl['observation']['status']);
        $this->assertEquals('BAIK', $fiohl['observation']['condition']);

        // FCO verification
        $this->assertArrayHasKey('FCO', $byCode);
        $fco = $byCode['FCO'];
        $this->assertEquals(2, $fco['configuration']['qty']);
        $this->assertEquals('buah', $fco['configuration']['unit']);
        $this->assertTrue($fco['configuration']['phase_applicable']);
        $this->assertEquals('2_PHASE', $fco['configuration']['phase_configuration']);
        $this->assertEquals(['R', 'S'], $fco['configuration']['positions']);
        $this->assertEquals('2 PHASE (R-S)', $fco['configuration']['display_phase']);
        $this->assertEquals('PERLU_PENGGANTIAN', $fco['observation']['condition']);

        // GSW verification
        $this->assertArrayHasKey('GSW', $byCode);
        $gsw = $byCode['GSW'];
        $this->assertEquals(1, $gsw['configuration']['qty']);
        $this->assertFalse($gsw['configuration']['phase_applicable']);
        $this->assertNull($gsw['configuration']['display_phase']);
    }

    /**
     * Test 4: Snapshot immutability - mutations in master catalog do NOT alter historical findings
     */
    public function testSnapshotImmutabilityGuaranteesHistoricalIntegrity(): void
    {
        // 1. Save an accessory
        $payload = [
            [
                'accessory_type_id'   => 10, // FIOHL
                'status'              => 'ADA',
                'condition'           => 'BAIK',
                'note'                => 'Snapshot historical baseline',
                'qty'                 => 3,
                'unit'                => 'buah',
                'phase_applicable'    => 1,
                'phase_configuration' => '3_PHASE',
                'phase_positions'     => ['R', 'S', 'T'],
            ]
        ];
        $this->service->persistAccessories(701, 301, $payload, 1);

        // Verify snapshot in DB
        $rowBefore = $this->db->table('temuan_accessories')->where('temuan_id', 701)->get()->getRowArray();
        $this->assertEquals('FIOHL', $rowBefore['accessory_name_snapshot']);
        $this->assertEquals('MONITORING', $rowBefore['category_snapshot']);
        $this->assertEquals('buah', $rowBefore['unit']);

        // 2. Mutate master catalog (rename item, change category, change unit)
        $this->db->table('master_jtm_accessories')
            ->where('id', 10)
            ->update([
                'name'     => 'MUTATED NAME FROM FUTURE',
                'category' => 'FUTURE_CATEGORY',
                'unit'     => 'set',
            ]);

        // 3. Re-read finding accessories: MUST preserve snapshot values
        $saved = $this->service->getAccessoriesForTemuan(701);
        $this->assertCount(1, $saved);
        $item = $saved[0];

        $this->assertEquals('FIOHL', $item['accessory_name'], 'Historical finding name must not be mutated by master catalog changes');
        $this->assertEquals('MONITORING', $item['category'], 'Historical finding category must reflect snapshot');
        $this->assertEquals('buah', $item['configuration']['unit'], 'Historical finding unit must reflect snapshot unit');
    }
}
