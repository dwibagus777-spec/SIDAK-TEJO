<?php

namespace Tests\Unit;

use App\Services\TranslineCompletionService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * TL-01 Sub-Gate D0 Dedicated Unit Test Suite
 *
 * Guaranteed Invariants:
 * - Deterministic Feeder Scan (BANJAR KEMANTREN)
 * - Strict Read-Only: 0 Operational Mutations
 * - Natural Key: TL-NAT:{penyulang_id}:{min}-{max}
 * - Visual Registry Tokens: per GIS-SPEC-VISUAL-01
 * - Idempotency: Run 1 === Run 2
 * - Classification: AUTO_MATCH, NEEDS_REVIEW, INVALID, MISSING
 */
class GisTranslineSubGateD0Test extends CIUnitTestCase
{
    protected $db;
    protected TranslineCompletionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->service = new TranslineCompletionService($this->db);
        $this->setupFeederData();
    }

    protected function setupFeederData(): void
    {
        $forge = Database::forge();

        // Ensure tables exist with canonical columns
        if (!$this->db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
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
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('sections', true);
        }

        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Aset Test'],
                'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_BETON'],
                'type'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'ulp_id'               => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'sequence_no'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'parent_asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
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

        if (!$this->db->fieldExists('sequence_no', 'assets')) {
            try {
                $forge->addColumn('assets', ['sequence_no' => ['type' => 'INT', 'constraint' => 11, 'default' => 0]]);
            } catch (\Throwable $e) {}
        }
        if (!$this->db->fieldExists('parent_asset_id', 'assets')) {
            try {
                $forge->addColumn('assets', ['parent_asset_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true]]);
            } catch (\Throwable $e) {}
        }

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

        // Clean up previous test feeder rows
        $this->db->table('gis_translines')->where('penyulang_id', 701)->delete();
        $this->db->table('assets')->where('penyulang_id', 701)->delete();
        $this->db->table('sections')->where('penyulang_id', 701)->delete();
        $this->db->table('penyulang')->where('id', 701)->delete();

        // Seed target feeder: BANJAR KEMANTREN (ID: 701)
        $this->db->table('penyulang')->insert([
            'id'             => 701,
            'ulp_id'         => 1,
            'kode_penyulang' => 'BJK',
            'nama_penyulang' => 'BANJAR KEMANTREN',
            'status'         => 'AKTIF',
        ]);

        $this->db->table('sections')->insert([
            'id'           => 7011,
            'penyulang_id' => 701,
            'nama_section' => 'Section Banjar Utama',
        ]);

        // Seed sequential assets along street
        $this->db->table('assets')->insert([
            'id'           => 7001,
            'kode_asset'   => 'BANJARKEMANTRAN_01',
            'nama_asset'   => 'Tiang BJK 01',
            'jenis_asset'  => 'TIANG_BETON',
            'ulp_id'       => 1,
            'penyulang_id' => 701,
            'section_id'   => 7011,
            'sequence_no'  => 1,
            'latitude'     => -7.4160000,
            'longitude'    => 112.7230000,
            'deleted_at'   => null,
        ]);

        $this->db->table('assets')->insert([
            'id'           => 7002,
            'kode_asset'   => 'BANJARKEMANTRAN_02',
            'nama_asset'   => 'Tiang BJK 02',
            'jenis_asset'  => 'TIANG_BETON',
            'ulp_id'       => 1,
            'penyulang_id' => 701,
            'section_id'   => 7011,
            'sequence_no'  => 2,
            'latitude'     => -7.4163000,
            'longitude'    => 112.7231500,
            'deleted_at'   => null,
        ]);

        $this->db->table('assets')->insert([
            'id'           => 7003,
            'kode_asset'   => 'BANJARKEMANTRAN_03',
            'nama_asset'   => 'Tiang BJK 03',
            'jenis_asset'  => 'TIANG_BETON',
            'ulp_id'       => 1,
            'penyulang_id' => 701,
            'section_id'   => 7011,
            'sequence_no'  => 3,
            'latitude'     => -7.4166000,
            'longitude'    => 112.7233000,
            'deleted_at'   => null,
        ]);

        // Seed one existing transline between 7001 and 7002
        $this->db->table('gis_translines')->insert([
            'id'                 => 70001,
            'transline_code'     => 'TL-701-7001-7002',
            'penyulang_id'       => 701,
            'source_asset_id'    => 7001,
            'target_asset_id'    => 7002,
            'geometry'           => json_encode([[112.7230000, -7.4160000], [112.7231500, -7.4163000]]),
            'conductor_type'     => 'AAAC',
            'conductor_size'     => '150 mm²',
            'conductor_material' => 'ALUMINUM_ALLOY',
            'distance_meters'    => 37.15,
            'status'             => 'ACTIVE',
            'is_active'          => 1,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->db->tableExists('gis_translines')) {
            $this->db->table('gis_translines')->where('penyulang_id', 701)->delete();
        }
        if ($this->db->tableExists('assets')) {
            $this->db->table('assets')->where('penyulang_id', 701)->delete();
        }
        if ($this->db->tableExists('sections')) {
            $this->db->table('sections')->where('penyulang_id', 701)->delete();
        }
        if ($this->db->tableExists('penyulang')) {
            $this->db->table('penyulang')->where('id', 701)->delete();
        }
        parent::tearDown();
    }

    public function testFeederCandidateScanExecutesCleanly()
    {
        $result = $this->service->getPenyulangCompletionCandidates(701);

        $this->assertSame('penyulang', $result['scope']['type']);
        $this->assertSame(701, $result['scope']['id']);
        $this->assertSame('BANJAR KEMANTREN', $result['scope']['name']);
        $this->assertSame(3, $result['scope']['asset_count']);
        $this->assertGreaterThan(0, $result['summary']['total_candidates']);
    }

    public function testNaturalKeyIsUndirectedAndCanonical()
    {
        $result = $this->service->getPenyulangCompletionCandidates(701);
        $candidates = $result['candidates'];

        foreach ($candidates as $c) {
            $sId = $c['source_asset_id'];
            $tId = $c['target_asset_id'];
            $expectedKey = 'TL-NAT:701:' . min($sId, $tId) . '-' . max($sId, $tId);
            $this->assertSame($expectedKey, $c['natural_key'], "Candidate natural key must be undirected canonical format");
        }
    }

    public function testClassificationContainsOnlyValidContractValues()
    {
        $result = $this->service->getPenyulangCompletionCandidates(701);
        $validStatuses = [
            TranslineCompletionService::STATUS_AUTO_MATCH,
            TranslineCompletionService::STATUS_NEEDS_REVIEW,
            TranslineCompletionService::STATUS_INVALID,
            TranslineCompletionService::STATUS_MISSING,
        ];

        foreach ($result['candidates'] as $c) {
            $this->assertContains($c['status'], $validStatuses);
            $this->assertContains($c['classification'], $validStatuses);
            $this->assertNotSame('PROPOSED', $c['classification']);
        }
    }

    public function testVisualStyleTokenResolutionPerGisSpecVisual01()
    {
        $token1 = $this->service->resolveVisualStyleToken('A3C', '70 mm²');
        $this->assertSame('A3C_70', $token1['token']);
        $this->assertSame('DASH_DOT', $token1['pattern']);

        $token2 = $this->service->resolveVisualStyleToken('A3CS', '150 mm²');
        $this->assertSame('A3CS_150', $token2['token']);
        $this->assertSame('PROTECTED_SOLID', $token2['pattern']);

        $token3 = $this->service->resolveVisualStyleToken('A3CS', '240 mm²');
        $this->assertSame('A3CS_240', $token3['token']);
        $this->assertSame('DOUBLE_STRIPED', $token3['pattern']);

        $token4 = $this->service->resolveVisualStyleToken('AAAC', '150 mm²');
        $this->assertSame('AAAC_150', $token4['token']);
        $this->assertSame('HEAVY_SOLID', $token4['pattern']);

        $token5 = $this->service->resolveVisualStyleToken('MVTIC', '3x150 mm²');
        $this->assertSame('MVTIC', $token5['token']);
        $this->assertSame('TWISTED_CHAIN', $token5['pattern']);
    }

    public function testScanIsStrictlyIdempotentAcrossMultipleRuns()
    {
        $run1 = $this->service->getPenyulangCompletionCandidates(701);
        $run2 = $this->service->getPenyulangCompletionCandidates(701);

        $hash1 = hash('sha256', json_encode($run1));
        $hash2 = hash('sha256', json_encode($run2));

        $this->assertSame($hash1, $hash2, "Scan output must be 100% bit-for-bit identical across runs (Idempotent)");
    }

    public function testZeroOperationalMutationsDuringScan()
    {
        $tables = ['gis_translines', 'assets', 'sections'];
        if ($this->db->tableExists('gis_transline_proposals')) {
            $tables[] = 'gis_transline_proposals';
        }
        if ($this->db->tableExists('temuan')) {
            $tables[] = 'temuan';
        }

        $countsBefore = [];
        foreach ($tables as $t) {
            $countsBefore[$t] = (int)$this->db->table($t)->countAllResults();
        }

        // Run scan multiple times
        $this->service->getPenyulangCompletionCandidates(701);
        $this->service->getPenyulangCompletionCandidates(701);

        foreach ($tables as $t) {
            $countAfter = (int)$this->db->table($t)->countAllResults();
            $this->assertSame($countsBefore[$t], $countAfter, "Table {$t} must have zero mutations");
        }
    }
}
