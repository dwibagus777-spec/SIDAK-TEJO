<?php

namespace App\Services;

use App\Models\InspectionTypeModel;
use App\Models\InspectionTemplateModel;
use App\Models\InspectionTemplateItemModel;

class InspectionCatalogService
{
    private InspectionTypeModel $typeModel;
    private InspectionTemplateModel $templateModel;
    private InspectionTemplateItemModel $itemModel;

    public function __construct()
    {
        $this->typeModel     = new InspectionTypeModel();
        $this->templateModel = new InspectionTemplateModel();
        $this->itemModel     = new InspectionTemplateItemModel();
        $this->ensureCatalogSeeded();
    }

    public function ensureCatalogSeeded(): void
    {
        if ($this->typeModel->countAllResults() > 0) {
            return;
        }

        $defaultTypes = [
            ['code' => 'VISUAL_JTM',           'name' => 'Inspeksi Visual JTM (20 kV)',   'category' => 'JTM',          'default_interval_months' => 3, 'icon' => 'bolt'],
            ['code' => 'VISUAL_JTR',           'name' => 'Inspeksi Visual JTR (380 V)',   'category' => 'JTR',          'default_interval_months' => 3, 'icon' => 'plug'],
            ['code' => 'THERMOVISION',         'name' => 'Inspeksi Thermovision (Infrared)','category' => 'THERMOVISION', 'default_interval_months' => 6, 'icon' => 'temperature-high'],
            ['code' => 'INSPEKSI_GARDU',        'name' => 'Inspeksi Gardu Distribusi',     'category' => 'GD',           'default_interval_months' => 3, 'icon' => 'building-shield'],
            ['code' => 'INSPEKSI_TRAFO',        'name' => 'Inspeksi Trafo Distribusi',     'category' => 'GD',           'default_interval_months' => 3, 'icon' => 'square-poll-vertical'],
            ['code' => 'INSPEKSI_LBS',          'name' => 'Inspeksi LBS (Load Break Switch)','category' => 'JTM',         'default_interval_months' => 6, 'icon' => 'toggle-on'],
            ['code' => 'INSPEKSI_RECLOSER',     'name' => 'Inspeksi Recloser / OCR',       'category' => 'JTM',          'default_interval_months' => 6, 'icon' => 'rotate'],
            ['code' => 'INSPEKSI_KUBIKEL',      'name' => 'Inspeksi Kubikel 20 kV',        'category' => 'GD',           'default_interval_months' => 6, 'icon' => 'box'],
            ['code' => 'INSPEKSI_KONSTRUKSI',   'name' => 'Inspeksi Standar Konstruksi PLN','category' => 'JTM',         'default_interval_months' => 12, 'icon' => 'ruler-combined'],
            ['code' => 'INSPEKSI_KONDISI_TIANG', 'name' => 'Inspeksi Fisik & Pondasi Tiang','category' => 'JTM',         'default_interval_months' => 12, 'icon' => 'monument'],
        ];

        foreach ($defaultTypes as $idx => $t) {
            $typeId = $this->typeModel->insert([
                'code'                    => $t['code'],
                'name'                    => $t['name'],
                'category'                => $t['category'],
                'description'             => 'Standar inspeksi ' . $t['name'] . ' PLN Sidoarjo',
                'default_interval_months' => $t['default_interval_months'],
                'icon'                    => $t['icon'],
                'is_active'               => 1,
                'sort_order'              => $idx + 1,
            ]);

            // Seed default Template for VISUAL_JTM
            if ($t['code'] === 'VISUAL_JTM') {
                $tmplId = $this->templateModel->insert([
                    'inspection_type_id' => $typeId,
                    'title'              => 'Checklist Standar Visual JTM 20 kV',
                    'asset_category'     => 'TIANG',
                    'version'            => 'v1.0',
                    'is_active'          => 1,
                ]);

                $items = [
                    'Kondisi Fisik Tiang (Retak / Miring / Korosi)',
                    'Kondisi Pondasi Tiang (Longsor / Amblas)',
                    'Kondisi Cross Arm (Bengkok / Korosi)',
                    'Kondisi Isolator (Retak / Flashover / Flek)',
                    'Kondisi Konduktor (Rantas / Andongan Kendur)',
                    'Kondisi Grounding (Putus / Terlepas)',
                    'Jarak Aman Pohon / ROW (< 3 Meter)',
                ];

                foreach ($items as $sIdx => $itemName) {
                    $this->itemModel->insert([
                        'template_id'       => $tmplId,
                        'item_name'         => $itemName,
                        'item_type'         => 'CHECKLIST',
                        'is_photo_required' => ($sIdx === 0 || $sIdx === 3) ? 1 : 0,
                        'sort_order'        => $sIdx + 1,
                    ]);
                }
            }

            // Seed default Template for THERMOVISION
            if ($t['code'] === 'THERMOVISION') {
                $tmplId = $this->templateModel->insert([
                    'inspection_type_id' => $typeId,
                    'title'              => 'Pengukuran Thermovision Infrared 3-Fase',
                    'asset_category'     => 'TRAFO',
                    'version'            => 'v1.0',
                    'is_active'          => 1,
                ]);

                $items = [
                    ['item_name' => 'Temperature Phase R (°C)',     'item_type' => 'NUMERIC_MEASUREMENT', 'unit' => '°C', 'min_value' => 0, 'max_value' => 150, 'req_photo' => 1],
                    ['item_name' => 'Temperature Phase S (°C)',     'item_type' => 'NUMERIC_MEASUREMENT', 'unit' => '°C', 'min_value' => 0, 'max_value' => 150, 'req_photo' => 1],
                    ['item_name' => 'Temperature Phase T (°C)',     'item_type' => 'NUMERIC_MEASUREMENT', 'unit' => '°C', 'min_value' => 0, 'max_value' => 150, 'req_photo' => 1],
                    ['item_name' => 'Ambient Temperature (°C)',     'item_type' => 'NUMERIC_MEASUREMENT', 'unit' => '°C', 'min_value' => -10, 'max_value' => 60, 'req_photo' => 0],
                ];

                foreach ($items as $sIdx => $item) {
                    $this->itemModel->insert([
                        'template_id'       => $tmplId,
                        'item_name'         => $item['item_name'],
                        'item_type'         => $item['item_type'],
                        'unit'              => $item['unit'],
                        'min_value'         => $item['min_value'],
                        'max_value'         => $item['max_value'],
                        'is_photo_required' => $item['req_photo'],
                        'sort_order'        => $sIdx + 1,
                    ]);
                }
            }
        }
    }

    public function getInspectionTypes(): array
    {
        return $this->typeModel->where('is_active', 1)->orderBy('sort_order', 'ASC')->findAll();
    }

    public function getTemplateItemsForType(int $inspectionTypeId, ?string $assetCategory = null): array
    {
        $builder = $this->templateModel->where('inspection_type_id', $inspectionTypeId)->where('is_active', 1);
        if (!empty($assetCategory)) {
            $builder->groupStart()
                ->where('asset_category', $assetCategory)
                ->orWhere('asset_category', null)
                ->groupEnd();
        }

        $template = $builder->first();
        if (!$template) {
            $template = $this->templateModel->where('inspection_type_id', $inspectionTypeId)->where('is_active', 1)->first();
        }

        if (!$template) {
            return [];
        }

        return $this->itemModel->where('template_id', $template['id'])->orderBy('sort_order', 'ASC')->findAll();
    }

    /**
     * Thermovision Calculator Helper
     * Dynamically calculates Delta T (Delta = Phase Temp - Ambient Temp)
     */
    public function calculateThermovisionDelta(float $phaseR, float $phaseS, float $phaseT, float $ambient): array
    {
        $deltaR = max(0, $phaseR - $ambient);
        $deltaS = max(0, $phaseS - $ambient);
        $deltaT = max(0, $phaseT - $ambient);
        $maxDelta = max($deltaR, $deltaS, $deltaT);

        $severity = 'NORMAL';
        if ($maxDelta >= 40.0) {
            $severity = 'CRITICAL_HOTSPOT';
        } elseif ($maxDelta >= 15.0) {
            $severity = 'WARNING_HOTSPOT';
        }

        return [
            'delta_r'        => round($deltaR, 1),
            'delta_s'        => round($deltaS, 1),
            'delta_t'        => round($deltaT, 1),
            'max_delta'      => round($maxDelta, 1),
            'severity'       => $severity,
            'is_abnormal'    => ($maxDelta >= 15.0),
        ];
    }
}
