<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\GisTranslineService;
use App\Services\TranslineAutoCompletionService;
use Config\Database;

class TranslineJtmPoleRestrictionTest extends CIUnitTestCase
{
    protected $db;
    protected GisTranslineService $translineService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->translineService = new GisTranslineService($this->db);
    }

    public function testIsJtmPoleAssetAcceptsValidPoleTypes(): void
    {
        $validTypes = ['TIANG_BETON', 'TIANG_BESI', 'TIANG_KAYU', 'TIANG', 'JTM', 'POLE'];
        foreach ($validTypes as $type) {
            $asset = [
                'id'          => 101,
                'kode_asset'  => 'T-101',
                'nama_asset'  => 'Tiang 101',
                'jenis_asset' => $type
            ];
            $check = GisTranslineService::isJtmPoleAsset($asset);
            $this->assertTrue($check['valid'], "Tipe pole {$type} harus diterima.");
            $this->assertSame('VALID_JTM_POLE', $check['reason']);
        }
    }

    public function testIsJtmPoleAssetRejectsNonPoleAssets(): void
    {
        $forbiddenAssets = [
            ['id' => 201, 'kode_asset' => 'TREE-01', 'nama_asset' => 'Pohon Sengon ROW', 'jenis_asset' => 'POHON_ROW'],
            ['id' => 202, 'kode_asset' => 'GTT-01', 'nama_asset' => 'Gardu Trafo Tiang', 'jenis_asset' => 'GARDU_PORTAL'],
            ['id' => 203, 'kode_asset' => 'TRF-01', 'nama_asset' => 'Trafo Distribusi 100kVA', 'jenis_asset' => 'TRAFO'],
            ['id' => 204, 'kode_asset' => 'REC-01', 'nama_asset' => 'Recloser Section 2', 'jenis_asset' => 'RECLOSER'],
            ['id' => 205, 'kode_asset' => 'LBS-01', 'nama_asset' => 'LBS Motorized', 'jenis_asset' => 'LBS'],
            ['id' => 206, 'kode_asset' => 'PMCB-01', 'nama_asset' => 'PMCB Outgoing', 'jenis_asset' => 'PMCB'],
            ['id' => 207, 'kode_asset' => 'KBL-01', 'nama_asset' => 'Konduktor AAAC 150', 'jenis_asset' => 'KONDUKTOR'],
            ['id' => 208, 'kode_asset' => 'GI-01', 'nama_asset' => 'Gardu Induk Buduran', 'jenis_asset' => 'GI'],
        ];

        foreach ($forbiddenAssets as $asset) {
            $check = GisTranslineService::isJtmPoleAsset($asset);
            $this->assertFalse($check['valid'], "Aset non-pole {$asset['jenis_asset']} harus ditolak.");
        }
    }

    public function testSaveTranslineRejectsNonPoleEndpoints(): void
    {
        // Insert dummy assets for testing
        $this->db->table('assets')->insert([
            'id'          => 888801,
            'kode_asset'  => 'TEST-POLE-01',
            'nama_asset'  => 'Tiang Beton 1',
            'jenis_asset' => 'TIANG_BETON',
            'latitude'    => -7.4480,
            'longitude'   => 112.7180,
            'penyulang_id'=> 9999,
        ]);

        $this->db->table('assets')->insert([
            'id'          => 888802,
            'kode_asset'  => 'TEST-TREE-01',
            'nama_asset'  => 'Pohon ROW Sengon',
            'jenis_asset' => 'POHON_ROW',
            'latitude'    => -7.4485,
            'longitude'   => 112.7185,
            'penyulang_id'=> 9999,
        ]);

        try {
            $result = $this->translineService->saveTransline([
                'penyulang_id'    => 9999,
                'source_asset_id' => 888801,
                'target_asset_id' => 888802,
            ]);

            $this->assertSame('error', $result['status']);
            $this->assertStringContainsString('ditolak', $result['message']);
        } finally {
            $this->db->table('assets')->whereIn('id', [888801, 888802])->delete();
        }
    }
}
