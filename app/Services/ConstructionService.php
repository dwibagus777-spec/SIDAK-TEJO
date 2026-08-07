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
     * Pastikan tabel katalog terisi data awal standar PLN (Auto-Seed Idempotent)
     */
    public function ensureStandardCatalogsSeeded(): void
    {
        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('asset_types') || !$db->tableExists('construction_types')) {
                return;
            }

            // 1. Seed Asset Types
            if ($this->assetTypeModel->countAllResults() === 0) {
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
                    $this->assetTypeModel->insert($at);
                }
            }

            // 2. Seed Construction Types
            if ($this->constructionTypeModel->countAllResults() === 0) {
                $constructions = [
                    ['code' => 'TM1', 'name' => 'Konstruksi TM-1 (Tumpu 1 Phasa)', 'network_type' => 'JTM', 'asset_category' => 'TIANG', 'construction_group' => 'TUMPU', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Tumpu Saluran Udara Tegangan Menengah 20kV', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-1', 'sort_order' => 1],
                    ['code' => 'TM2', 'name' => 'Konstruksi TM-2 (Tumpu Sudut Kecil)', 'network_type' => 'JTM', 'asset_category' => 'TIANG', 'construction_group' => 'TUMPU', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Sudut Kecil (5-15 Derajat)', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-2', 'sort_order' => 2],
                    ['code' => 'TM4', 'name' => 'Konstruksi TM-4 (Tarik Akhir)', 'network_type' => 'JTM', 'asset_category' => 'TIANG', 'construction_group' => 'TARIK', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Akhir Saluran Udara Tegangan Menengah', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-4', 'sort_order' => 3],
                    ['code' => 'TM5', 'name' => 'Konstruksi TM-5 (Tarik Ganda Sudut Besar)', 'network_type' => 'JTM', 'asset_category' => 'TIANG', 'construction_group' => 'TARIK', 'voltage_level' => '20kV', 'description' => 'Konstruksi Tiang Sudut Besar (30-90 Derajat)', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-5', 'sort_order' => 4],
                    ['code' => 'TM8', 'name' => 'Konstruksi TM-8 (Portal Trafo Distribusi)', 'network_type' => 'JTM', 'asset_category' => 'TRAFO', 'construction_group' => 'PORTAL', 'voltage_level' => '20kV', 'description' => 'Konstruksi Gardu Tiang Portal Trafo Distribusi', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-8', 'sort_order' => 5],
                    ['code' => 'TM11', 'name' => 'Konstruksi TM-11 (Cantol Trafo Single Pole)', 'network_type' => 'JTM', 'asset_category' => 'TRAFO', 'construction_group' => 'CANTOL', 'voltage_level' => '20kV', 'description' => 'Konstruksi Gardu Tiang Cantol (Single Pole)', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - Gambar TM-11', 'sort_order' => 6],
                    ['code' => 'TMMVTIC', 'name' => 'Konstruksi TM-MVTIC (Kabel Pilin 20kV)', 'network_type' => 'JTM', 'asset_category' => 'KONDUKTOR', 'construction_group' => 'KABEL', 'voltage_level' => '20kV', 'description' => 'Konstruksi Kabel Pilin Udara Tegangan Menengah (MVTIC)', 'standard_reference' => 'Buku Standar Konstruksi PLN 20kV - MVTIC', 'sort_order' => 7],
                    ['code' => 'TR1', 'name' => 'Konstruksi TR-1 (Tumpu JTR 380/220V)', 'network_type' => 'JTR', 'asset_category' => 'TIANG', 'construction_group' => 'TUMPU', 'voltage_level' => '380V', 'description' => 'Konstruksi Tiang Tumpu Saluran Udara Tegangan Rendah (LVTC)', 'standard_reference' => 'Buku Standar Konstruksi PLN JTR - TR-1', 'sort_order' => 8],
                    ['code' => 'TR3', 'name' => 'Konstruksi TR-3 (Tarik Akhir JTR)', 'network_type' => 'JTR', 'asset_category' => 'TIANG', 'construction_group' => 'TARIK', 'voltage_level' => '380V', 'description' => 'Konstruksi Tiang Akhir/Tarik JTR Kabel LVTC', 'standard_reference' => 'Buku Standar Konstruksi PLN JTR - TR-3', 'sort_order' => 9],
                    ['code' => 'TR6', 'name' => 'Konstruksi TR-6 (Sambungan Pelanggan TR)', 'network_type' => 'JTR', 'asset_category' => 'TIANG', 'construction_group' => 'SAMBUNGAN', 'voltage_level' => '220V', 'description' => 'Konstruksi Tiang Percabangan Sambungan Rumah Pelanggan', 'standard_reference' => 'Buku Standar Konstruksi PLN JTR - TR-6', 'sort_order' => 10],
                ];
                foreach ($constructions as $ct) {
                    $this->constructionTypeModel->insert($ct);
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
