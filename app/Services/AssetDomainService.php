<?php

namespace App\Services;

use App\Repositories\AssetRepository;

class AssetDomainService
{
    private AssetRepository $assetRepository;

    public function __construct()
    {
        $this->assetRepository = new AssetRepository();
    }

    /**
     * Generate unique PLN standard Asset Code
     */
    public function generateKodeAsset(string $jenis): string
    {
        $prefix = match(strtoupper($jenis)) {
            'GARDU'     => 'AST-GRD-',
            'TRAFO'     => 'AST-TRF-',
            'KUBIKEL'   => 'AST-KUB-',
            'LBS'       => 'AST-LBS-',
            'RECLOSER'  => 'AST-REC-',
            'SECTION'   => 'AST-SEC-',
            'PENYULANG' => 'AST-PYL-',
            'TIANG'     => 'AST-TNG-',
            'JTM'       => 'AST-JTM-',
            'JTR'       => 'AST-JTR-',
            'PHB'       => 'AST-PHB-',
            'APP'       => 'AST-APP-',
            'METER'     => 'AST-MTR-',
            'GROUNDING' => 'AST-GND-',
            default     => 'AST-PLN-'
        };

        $rand = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 4));
        return $prefix . date('Ym') . '-' . $rand;
    }

    /**
     * Validate Asset Code / Serial Uniqueness
     */
    public function isKodeAssetUnique(string $kodeAsset, ?int $excludeId = null): bool
    {
        $db = \Config\Database::connect();
        $builder = $db->table('assets');
        $builder->where('kode_asset', trim($kodeAsset));
        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }
        return $builder->countAllResults() === 0;
    }

    /**
     * Build Digital Twin Meta Specification Schema
     */
    public function buildDigitalTwinSpec(array $asset): array
    {
        $tahunInstalasi = (int)($asset['tahun_instalasi'] ?? date('Y'));
        $umurTahun = max(0, (int)date('Y') - $tahunInstalasi);

        return [
            'meta' => [
                'kode'       => $asset['kode_asset'] ?? '',
                'nama'       => $asset['nama_asset'] ?? '',
                'jenis'      => $asset['jenis_asset'] ?? '',
                'merk'       => $asset['merk'] ?? '-',
                'type'       => $asset['type'] ?? '-',
                'nomor_seri' => $asset['nomor_seri'] ?? '-',
                'kapasitas'  => $asset['kapasitas'] ?? '-',
                'tahun'      => $tahunInstalasi,
                'umur'       => "{$umurTahun} Tahun",
            ],
            'gis' => [
                'latitude'  => $asset['latitude'] ?? null,
                'longitude' => $asset['longitude'] ?? null,
                'lokasi'    => $asset['lokasi'] ?? '-',
            ],
            'codes' => [
                'qr_code' => $asset['qr_code'] ?? null,
                'barcode' => $asset['barcode'] ?? null,
            ]
        ];
    }
}
