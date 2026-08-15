<?php

namespace App\Services;

/**
 * Enterprise Metadata Class for Master Asset PLN Templates
 * Metadata-driven architecture: zero hardcoded if/else branching for asset types.
 */
class AssetTemplateMetadata
{
    /**
     * Standard List of Official PLN Asset Types
     */
    public const ASSET_TYPES = [
        'Gardu',
        'Trafo',
        'Kubikel',
        'LBS',
        'Recloser',
        'Section',
        'Penyulang',
        'Tiang',
        'JTM',
        'JTR',
        'PHB',
        'APP',
        'Meter',
        'Grounding',
    ];

    /**
     * Template Field Definitions per Asset Type
     */
    private const TEMPLATE_METADATA = [
        'GARDU' => [
            ['key' => 'up3',             'label' => 'UP3',                 'required' => true],
            ['key' => 'ulp',             'label' => 'ULP',                 'required' => true],
            ['key' => 'jenis_asset',     'label' => 'Jenis Asset',         'required' => true],
            ['key' => 'nama_asset',      'label' => 'Nama Gardu',          'required' => true],
            ['key' => 'penyulang',       'label' => 'Penyulang',           'required' => false],
            ['key' => 'merk',            'label' => 'Merk Gardu',          'required' => false],
            ['key' => 'kapasitas',       'label' => 'Kapasitas (kVA)',     'required' => false],
            ['key' => 'tahun_instalasi', 'label' => 'Tahun Instalasi',     'required' => false],
            ['key' => 'lokasi',          'label' => 'Alamat / Lokasi',     'required' => false],
            ['key' => 'latitude',        'label' => 'Latitude',            'required' => false],
            ['key' => 'longitude',       'label' => 'Longitude',           'required' => false],
        ],
        'TIANG' => [
            ['key' => 'up3',             'label' => 'UP3',                 'required' => true],
            ['key' => 'ulp',             'label' => 'ULP',                 'required' => true],
            ['key' => 'jenis_asset',     'label' => 'Jenis Asset',         'required' => true],
            ['key' => 'nama_asset',      'label' => 'Nama / No Tiang',     'required' => true],
            ['key' => 'penyulang',       'label' => 'Penyulang',           'required' => false],
            ['key' => 'type',            'label' => 'Material Tiang',      'required' => false],
            ['key' => 'kapasitas',       'label' => 'Tinggi Tiang (M)',    'required' => false],
            ['key' => 'lokasi',          'label' => 'Alamat / Lokasi',     'required' => false],
            ['key' => 'latitude',        'label' => 'Latitude',            'required' => false],
            ['key' => 'longitude',       'label' => 'Longitude',           'required' => false],
        ],
        'TRAFO' => [
            ['key' => 'up3',             'label' => 'UP3',                 'required' => true],
            ['key' => 'ulp',             'label' => 'ULP',                 'required' => true],
            ['key' => 'jenis_asset',     'label' => 'Jenis Asset',         'required' => true],
            ['key' => 'nama_asset',      'label' => 'Nama Trafo',          'required' => true],
            ['key' => 'penyulang',       'label' => 'Penyulang',           'required' => false],
            ['key' => 'merk',            'label' => 'Merk Trafo',          'required' => false],
            ['key' => 'type',            'label' => 'Tipe Trafo',          'required' => false],
            ['key' => 'nomor_seri',      'label' => 'Nomor Seri',          'required' => false],
            ['key' => 'kapasitas',       'label' => 'Kapasitas (kVA)',     'required' => false],
            ['key' => 'tahun_instalasi', 'label' => 'Tahun Instalasi',     'required' => false],
            ['key' => 'lokasi',          'label' => 'Alamat / Lokasi',     'required' => false],
            ['key' => 'latitude',        'label' => 'Latitude',            'required' => false],
            ['key' => 'longitude',       'label' => 'Longitude',           'required' => false],
        ],
        'DEFAULT' => [
            ['key' => 'up3',             'label' => 'UP3',                 'required' => true],
            ['key' => 'ulp',             'label' => 'ULP',                 'required' => true],
            ['key' => 'jenis_asset',     'label' => 'Jenis Asset',         'required' => true],
            ['key' => 'nama_asset',      'label' => 'Nama Asset',          'required' => true],
            ['key' => 'penyulang',       'label' => 'Penyulang',           'required' => false],
            ['key' => 'merk',            'label' => 'Merk',                'required' => false],
            ['key' => 'type',            'label' => 'Tipe',                'required' => false],
            ['key' => 'nomor_seri',      'label' => 'Nomor Seri',          'required' => false],
            ['key' => 'kapasitas',       'label' => 'Kapasitas',           'required' => false],
            ['key' => 'tahun_instalasi', 'label' => 'Tahun Instalasi',     'required' => false],
            ['key' => 'lokasi',          'label' => 'Alamat / Lokasi',     'required' => false],
            ['key' => 'latitude',        'label' => 'Latitude',            'required' => false],
            ['key' => 'longitude',       'label' => 'Longitude',           'required' => false],
        ],
    ];

    /**
     * Get Header Definitions for a specific asset type
     */
    public static function getHeaderDefinition(string $jenisAsset): array
    {
        $key = strtoupper(trim($jenisAsset));
        return self::TEMPLATE_METADATA[$key] ?? self::TEMPLATE_METADATA['DEFAULT'];
    }

    /**
     * Get Sample Row Data for a specific asset type
     */
    public static function getSampleRow(string $jenisAsset, string $up3, string $ulp, ?string $penyulang = null): array
    {
        return [
            'up3'             => $up3 ?: 'UP3 Sidoarjo',
            'ulp'             => $ulp ?: 'Sidoarjo Kota',
            'jenis_asset'     => $jenisAsset,
            'nama_asset'      => $jenisAsset . ' SDJ-001',
            'penyulang'       => !empty($penyulang) ? $penyulang : 'BY PASS',
            'merk'            => 'Schneider Electric',
            'type'            => 'Portal 20KV',
            'nomor_seri'      => 'SN-' . date('Y') . '-001',
            'kapasitas'       => '250 kVA',
            'tahun_instalasi' => '2021',
            'lokasi'          => 'Jl. Raya Pahlawan No. 45, Sidoarjo',
            'latitude'        => '-7.4478',
            'longitude'       => '112.7183',
            'section'         => 'LBSM SIDOMULYO',
        ];
    }
}
