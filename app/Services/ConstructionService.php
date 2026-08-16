<?php

namespace App\Services;

use App\Models\AssetTypeModel;
use App\Models\ConstructionTypeModel;

class ConstructionService
{
    private AssetTypeModel $assetTypeModel;
    private ConstructionTypeModel $constructionTypeModel;

    public function __construct()
    {
        $this->assetTypeModel = new AssetTypeModel();
        $this->constructionTypeModel = new ConstructionTypeModel();
    }

    /**
     * Pastikan tabel katalog terisi data awal standar PLN (Auto-Seed & Upsert Idempotent)
     */
    public function ensureStandardCatalogsSeeded(): void
    {
        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('asset_types') || !$db->tableExists('construction_types')) {
                return;
            }

            // 1. Seed Asset Types
            $assetTypes = [
                ['code' => 'TIANG', 'name' => 'Tiang Listrik (Beton/Besi)', 'network_type' => 'JTM', 'icon' => 'monument', 'marker_shape' => 'circle', 'marker_size' => 20, 'default_color' => '#0284c7', 'sort_order' => 1],
                ['code' => 'GARDU_DISTRIBUSI', 'name' => 'Gardu Distribusi 20kV', 'network_type' => 'GD', 'icon' => 'building-columns', 'marker_shape' => 'square', 'marker_size' => 28, 'default_color' => '#059669', 'sort_order' => 2],
                ['code' => 'TRAFO_DISTRIBUSI', 'name' => 'Trafo Distribusi', 'network_type' => 'GD', 'icon' => 'bolt-lightning', 'marker_shape' => 'diamond', 'marker_size' => 26, 'default_color' => '#d97706', 'sort_order' => 3],
                ['code' => 'LBS', 'name' => 'Load Break Switch (LBS)', 'network_type' => 'JTM', 'icon' => 'toggle-on', 'marker_shape' => 'pentagon', 'marker_size' => 24, 'default_color' => '#7c3aed', 'sort_order' => 4],
                ['code' => 'RECLOSER', 'name' => 'Automatic Circuit Recloser', 'network_type' => 'JTM', 'icon' => 'rotate', 'marker_shape' => 'hexagon', 'marker_size' => 24, 'default_color' => '#dc2626', 'sort_order' => 5],
                ['code' => 'KUBIKEL', 'name' => 'Kubikel 20kV (20kV Switchgear)', 'network_type' => 'GD', 'icon' => 'server', 'marker_shape' => 'square', 'marker_size' => 22, 'default_color' => '#475569', 'sort_order' => 6],
                ['code' => 'FCO', 'name' => 'Fuse Cut Out (FCO)', 'network_type' => 'JTM', 'icon' => 'shield-halved', 'marker_shape' => 'triangle', 'marker_size' => 20, 'default_color' => '#ea580c', 'sort_order' => 7],
                ['code' => 'ISOLATOR', 'name' => 'Isolator Tumpu / Tarik', 'network_type' => 'JTM', 'icon' => 'ring', 'marker_shape' => 'circle', 'marker_size' => 16, 'default_color' => '#64748b', 'sort_order' => 8],
                ['code' => 'KONDUKTOR', 'name' => 'Penghantar (AAAC/AAACS/MVTIC)', 'network_type' => 'JTM', 'icon' => 'ruler-horizontal', 'marker_shape' => 'line', 'marker_size' => 16, 'default_color' => '#2563eb', 'sort_order' => 9],
            ];

            foreach ($assetTypes as $at) {
                $exists = $this->assetTypeModel->where('code', $at['code'])->first();
                if (!$exists) {
                    $this->assetTypeModel->insert($at);
                }
            }

            // 2. Seed Standard PLN Construction Catalog (JTM, JTR, & Gardu Distribusi)
            $constructions = [
                // Standar Konstruksi JTM 20kV (TM-1 s/d TM-12, MVTIC, SKTM)
                ['code' => 'TM1', 'name' => 'Konstruksi TM-1 (Tumpu 1 Phasa / Line Angle 0°-5°)', 'network_type' => 'JTM', 'asset_category' => 'TIANG', 'construction_group' => 'TUMPU', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Tumpu Saluran Udara Tegangan Menengah 20kV', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-1', 'sort_order' => 1],
                ['code' => 'TM2', 'name' => 'Konstruksi TM-2 (Tumpu Sudut Kecil 5°-15°)', 'network_type' => 'JTM', 'asset_category' => 'TIANG', 'construction_group' => 'TUMPU', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Sudut Kecil Saluran Udara 20kV', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-2', 'sort_order' => 2],
                ['code' => 'TM3', 'name' => 'Konstruksi TM-3 (Tumpu Sudut Sedang 15°-30°)', 'network_type' => 'JTM', 'asset_category' => 'TIANG', 'construction_group' => 'TUMPU', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Sudut Sedang Saluran Udara 20kV', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-3', 'sort_order' => 3],
                ['code' => 'TM4', 'name' => 'Konstruksi TM-4 (Tarik Akhir / Dead End)', 'network_type' => 'JTM', 'asset_category' => 'TIANG', 'construction_group' => 'TARIK', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Akhir Saluran Udara Tegangan Menengah', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-4', 'sort_order' => 4],
                ['code' => 'TM5', 'name' => 'Konstruksi TM-5 (Tarik Ganda Sudut Besar 30°-90°)', 'network_type' => 'JTM', 'asset_category' => 'TIANG', 'construction_group' => 'TARIK', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Sudut Besar 20kV', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-5', 'sort_order' => 5],
                ['code' => 'TM6', 'name' => 'Konstruksi TM-6 (Percabangan / Tee-Off Pole)', 'network_type' => 'JTM', 'asset_category' => 'TIANG', 'construction_group' => 'PERCABANGAN', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Percabangan Saluran Udara 20kV', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-6', 'sort_order' => 6],
                ['code' => 'TM7', 'name' => 'Konstruksi TM-7 (Tiang Silang / Cross Pole)', 'network_type' => 'JTM', 'asset_category' => 'TIANG', 'construction_group' => 'SILANG', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Persilangan Saluran Udara 20kV', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-7', 'sort_order' => 7],
                ['code' => 'TM8', 'name' => 'Konstruksi TM-8 (Gardu Tiang Portal Trafo)', 'network_type' => 'JTM', 'asset_category' => 'TRAFO', 'construction_group' => 'PORTAL', 'voltage_level' => '20kV', 'description' => 'Konstruksi Gardu Tiang Portal Trafo Distribusi', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-8', 'sort_order' => 8],
                ['code' => 'TM9', 'name' => 'Konstruksi TM-9 (Tiang Penegang / Section Pole)', 'network_type' => 'JTM', 'asset_category' => 'TIANG', 'construction_group' => 'TARIK', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Penegang Saluran Udara 20kV', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-9', 'sort_order' => 9],
                ['code' => 'TM10', 'name' => 'Konstruksi TM-10 (Tiang Sakelar / LBS Switch 20kV)', 'network_type' => 'JTM', 'asset_category' => 'LBS', 'construction_group' => 'SWITCH', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Load Break Switch (LBS) 20kV', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-10', 'sort_order' => 10],
                ['code' => 'TM11', 'name' => 'Konstruksi TM-11 (Cantol Trafo Single Pole)', 'network_type' => 'JTM', 'asset_category' => 'TRAFO', 'construction_group' => 'CANTOL', 'voltage_level' => '20kV', 'description' => 'Konstruksi Gardu Tiang Cantol (Single Pole)', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-11', 'sort_order' => 11],
                ['code' => 'TM12', 'name' => 'Konstruksi TM-12 (Tiang Recloser / ACR 20kV)', 'network_type' => 'JTM', 'asset_category' => 'RECLOSER', 'construction_group' => 'RECLOSER', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Automatic Circuit Recloser 20kV', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-12', 'sort_order' => 12],
                ['code' => 'TMMVTIC', 'name' => 'Konstruksi TM-MVTIC (Kabel Pilin 20kV)', 'network_type' => 'JTM', 'asset_category' => 'KONDUKTOR', 'construction_group' => 'KABEL', 'voltage_level' => '20kV', 'description' => 'Konstruksi Kabel Pilin Udara Tegangan Menengah (MVTIC)', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - MVTIC', 'sort_order' => 13],
                ['code' => 'SKTM', 'name' => 'Konstruksi SKTM (Kabel Tanah 20kV XLPE)', 'network_type' => 'JTM', 'asset_category' => 'KONDUKTOR', 'construction_group' => 'KABEL_TANAH', 'voltage_level' => '20kV', 'description' => 'Saluran Kabel Tanah Tegangan Menengah 20kV', 'standard_reference' => 'Buku Standar Konstruksi PLN SKTM 20kV', 'sort_order' => 14],

                // Standar Konstruksi JTR 380/220V (TR-1 s/d TR-9, SKTR)
                ['code' => 'TR1', 'name' => 'Konstruksi TR-1 (Tumpu JTR LVTC 380/220V)', 'network_type' => 'JTR', 'asset_category' => 'TIANG', 'construction_group' => 'TUMPU', 'voltage_level' => '380V', 'description' => 'Konstruksi Tiang Tumpu Saluran Udara Tegangan Rendah (LVTC)', 'standard_reference' => 'Buku Standar Konstruksi PLN JTR - TR-1', 'sort_order' => 15],
                ['code' => 'TR2', 'name' => 'Konstruksi TR-2 (Tumpu Sudut JTR 5°-30°)', 'network_type' => 'JTR', 'asset_category' => 'TIANG', 'construction_group' => 'TUMPU', 'voltage_level' => '380V', 'description' => 'Konstruksi Tiang Sudut JTR LVTC', 'standard_reference' => 'Buku Standar Konstruksi PLN JTR - TR-2', 'sort_order' => 16],
                ['code' => 'TR3', 'name' => 'Konstruksi TR-3 (Tarik Akhir JTR / LVTC Dead End)', 'network_type' => 'JTR', 'asset_category' => 'TIANG', 'construction_group' => 'TARIK', 'voltage_level' => '380V', 'description' => 'Konstruksi Tiang Akhir/Tarik JTR Kabel LVTC', 'standard_reference' => 'Buku Standar Konstruksi PLN JTR - TR-3', 'sort_order' => 17],
                ['code' => 'TR4', 'name' => 'Konstruksi TR-4 (Tarik Ganda / Sudut Besar JTR)', 'network_type' => 'JTR', 'asset_category' => 'TIANG', 'construction_group' => 'TARIK', 'voltage_level' => '380V', 'description' => 'Konstruksi Tiang Sudut Besar JTR LVTC', 'standard_reference' => 'Buku Standar Konstruksi PLN JTR - TR-4', 'sort_order' => 18],
                ['code' => 'TR5', 'name' => 'Konstruksi TR-5 (Percabangan JTR / LVTC Tee-Off)', 'network_type' => 'JTR', 'asset_category' => 'TIANG', 'construction_group' => 'PERCABANGAN', 'voltage_level' => '380V', 'description' => 'Konstruksi Tiang Percabangan JTR LVTC', 'standard_reference' => 'Buku Standar Konstruksi PLN JTR - TR-5', 'sort_order' => 19],
                ['code' => 'TR6', 'name' => 'Konstruksi TR-6 (Sambungan Pelanggan TR / SR)', 'network_type' => 'JTR', 'asset_category' => 'TIANG', 'construction_group' => 'SAMBUNGAN', 'voltage_level' => '220V', 'description' => 'Konstruksi Tiang Percabangan Sambungan Rumah Pelanggan', 'standard_reference' => 'Buku Standar Konstruksi PLN JTR - TR-6', 'sort_order' => 20],
                ['code' => 'TR7', 'name' => 'Konstruksi TR-7 (Tiang Akhir Sambungan Rumah)', 'network_type' => 'JTR', 'asset_category' => 'TIANG', 'construction_group' => 'SAMBUNGAN', 'voltage_level' => '220V', 'description' => 'Konstruksi Tiang Ujung Sambungan Rumah Pelanggan', 'standard_reference' => 'Buku Standar Konstruksi PLN JTR - TR-7', 'sort_order' => 21],
                ['code' => 'TR8', 'name' => 'Konstruksi TR-8 (Sambungan Luar Pelanggan / SLP)', 'network_type' => 'JTR', 'asset_category' => 'TIANG', 'construction_group' => 'SAMBUNGAN', 'voltage_level' => '220V', 'description' => 'Konstruksi Sambungan Luar Pelanggan / Kabel Deret TR-8', 'standard_reference' => 'Buku Standar Konstruksi PLN JTR - TR-8', 'sort_order' => 22],
                ['code' => 'TR9', 'name' => 'Konstruksi TR-9 (Pengaman Lebur JTR / PHB-TR)', 'network_type' => 'JTR', 'asset_category' => 'TIANG', 'construction_group' => 'PENGAMAN', 'voltage_level' => '380V', 'description' => 'Konstruksi Perlengkapan Hubung Bagi / Fuse JTR', 'standard_reference' => 'Buku Standar Konstruksi PLN JTR - TR-9', 'sort_order' => 23],
                ['code' => 'SKTR', 'name' => 'Konstruksi SKTR (Kabel Tanah Tegangan Rendah)', 'network_type' => 'JTR', 'asset_category' => 'KONDUKTOR', 'construction_group' => 'KABEL_TANAH', 'voltage_level' => '380V', 'description' => 'Saluran Kabel Tanah Tegangan Rendah JTR', 'standard_reference' => 'Buku Standar Konstruksi PLN SKTR', 'sort_order' => 24],

                // Standar Gardu Distribusi 20kV
                ['code' => 'GD_PORTAL', 'name' => 'Gardu Distribusi Portal (2 Pole 20kV)', 'network_type' => 'GD', 'asset_category' => 'GARDU', 'construction_group' => 'PORTAL', 'voltage_level' => '20kV', 'description' => 'Gardu Distribusi Pasangan Luar Tiang Portal', 'standard_reference' => 'Buku Standar Konstruksi Gardu PLN - Portal', 'sort_order' => 25],
                ['code' => 'GD_CANTOL', 'name' => 'Gardu Distribusi Cantol (Single Pole 20kV)', 'network_type' => 'GD', 'asset_category' => 'GARDU', 'construction_group' => 'CANTOL', 'voltage_level' => '20kV', 'description' => 'Gardu Distribusi Pasangan Luar Tiang Cantol', 'standard_reference' => 'Buku Standar Konstruksi Gardu PLN - Cantol', 'sort_order' => 26],
                ['code' => 'GD_BETON', 'name' => 'Gardu Distribusi Bangunan Beton / Tembok', 'network_type' => 'GD', 'asset_category' => 'GARDU', 'construction_group' => 'BANGUNAN', 'voltage_level' => '20kV', 'description' => 'Gardu Distribusi Pasangan Dalam Bangunan Sipil/Beton', 'standard_reference' => 'Buku Standar Konstruksi Gardu PLN - Beton', 'sort_order' => 27],
                ['code' => 'GD_KIOS', 'name' => 'Gardu Distribusi Kios / Compact Substation', 'network_type' => 'GD', 'asset_category' => 'GARDU', 'construction_group' => 'METAL_CLAD', 'voltage_level' => '20kV', 'description' => 'Gardu Distribusi Kios Metal / Prefabricated Compact Substation', 'standard_reference' => 'Buku Standar Konstruksi Gardu PLN - Kios', 'sort_order' => 28],

                // Additional PLN Distribution Field Equipment & Construction Catalog
                ['code' => 'GTT', 'name' => 'Gardu Trafo Tiang (GTT)', 'network_type' => 'GD', 'asset_category' => 'GARDU', 'construction_group' => 'GARDU_TIANG', 'voltage_level' => '20kV', 'description' => 'Gardu Trafo Tiang Distribusi Standar PLN', 'standard_reference' => 'Buku Standar Inspeksi GTT PLN Jawa Timur', 'sort_order' => 29],
                ['code' => 'GTT1', 'name' => 'Gardu Trafo Tiang 1 Tiang (GTT Single Pole)', 'network_type' => 'GD', 'asset_category' => 'GARDU', 'construction_group' => 'CANTOL', 'voltage_level' => '20kV', 'description' => 'Gardu Trafo Tiang Konstruksi Single Pole / Cantol', 'standard_reference' => 'Buku Standar Inspeksi GTT 1 Tiang PLN', 'sort_order' => 30],
                ['code' => 'GTT2', 'name' => 'Gardu Trafo Tiang 2 Tiang (GTT Portal Pole)', 'network_type' => 'GD', 'asset_category' => 'GARDU', 'construction_group' => 'PORTAL', 'voltage_level' => '20kV', 'description' => 'Gardu Trafo Tiang Konstruksi Double Pole / Portal', 'standard_reference' => 'Buku Standar Inspeksi GTT 2 Tiang PLN', 'sort_order' => 31],
                ['code' => 'PMS', 'name' => 'Peralatan Hubung - Pemisah (Disconnecting Switch / DS)', 'network_type' => 'JTM', 'asset_category' => 'SWITCH', 'construction_group' => 'PEMISAH', 'voltage_level' => '20kV', 'description' => 'Peralatan Sakelar Pemisah Beban / Disconnecting Switch 20kV', 'standard_reference' => 'Standar Peralatan Hubung PLN - PMS', 'sort_order' => 32],
                ['code' => 'PMT', 'name' => 'Peralatan Hubung - Pemutus Tenaga (Circuit Breaker / CB)', 'network_type' => 'JTM', 'asset_category' => 'SWITCH', 'construction_group' => 'PEMUTUS', 'voltage_level' => '20kV', 'description' => 'Peralatan Sakelar Pemutus Utama / Circuit Breaker 20kV', 'standard_reference' => 'Standar Peralatan Hubung PLN - PMT', 'sort_order' => 33],
                ['code' => 'TMTP', 'name' => 'Konstruksi TM-TP (Tiang Khusus / Tiang Portal 3 Pole)', 'network_type' => 'JTM', 'asset_category' => 'TIANG', 'construction_group' => 'PORTAL', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Khusus / Tiang Portal Tiga Pole (TMTP)', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - TMTP/TMTP3', 'sort_order' => 34],
                ['code' => 'TM16', 'name' => 'Konstruksi TM-16 (Tiang Khusus Percabangan 20kV)', 'network_type' => 'JTM', 'asset_category' => 'TIANG', 'construction_group' => 'PORTAL', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Khusus Percabangan / Portal 20kV', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - TM-16', 'sort_order' => 35],
                ['code' => 'TM16A', 'name' => 'Konstruksi TM-16A (Tiang Khusus Peralatan Switch 20kV)', 'network_type' => 'JTM', 'asset_category' => 'TIANG', 'construction_group' => 'PORTAL', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Khusus Dudukan Peralatan Switch 20kV', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - TM-16A', 'sort_order' => 36],
            ];

            foreach ($constructions as $ct) {
                $exists = $this->constructionTypeModel->where('code', $ct['code'])->first();
                if (!$exists) {
                    $this->constructionTypeModel->insert($ct);
                } else {
                    $this->constructionTypeModel->update($exists['id'], $ct);
                }
            }
        } catch (\Throwable $e) {
            log_message('error', '[ConstructionService::ensureStandardCatalogsSeeded] ' . $e->getMessage());
        }
    }

    public function getAssetTypes(?string $networkType = null): array
    {
        $this->ensureStandardCatalogsSeeded();
        $builder = $this->assetTypeModel->where('is_active', 1);
        if ($networkType) {
            $builder->groupStart()
                ->where('network_type', $networkType)
                ->orWhere('network_type', 'GD')
            ->groupEnd();
        }
        return $builder->orderBy('sort_order', 'ASC')->findAll();
    }

    public function getConstructionTypes(?string $networkType = null, ?string $category = null): array
    {
        $this->ensureStandardCatalogsSeeded();
        $builder = $this->constructionTypeModel->where('is_active', 1);
        if ($networkType) {
            $builder->where('network_type', $networkType);
        }
        if ($category) {
            $builder->where('asset_category', $category);
        }
        return $builder->orderBy('sort_order', 'ASC')->findAll();
    }
}
