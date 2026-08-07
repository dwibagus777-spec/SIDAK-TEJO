<?php

namespace App\Services;

use App\Models\AssetModel;
use App\Models\TemuanModel;
use App\Services\FindingIntelligenceService;
use App\Services\ThermovisionAnalyticsService;

class AssetHealthScoringEngine
{
    public const SCORING_VERSION = 'v1.0';

    private AssetModel $assetModel;
    private TemuanModel $temuanModel;
    private ThermovisionAnalyticsService $thermoService;

    public function __construct()
    {
        $this->assetModel    = new AssetModel();
        $this->temuanModel   = new TemuanModel();
        $this->thermoService = new ThermovisionAnalyticsService();
    }

    /**
     * Synthesize Bounded Bounded 0-100 Asset Health Score (v1.0)
     * 100% Read-Only: DOES NOT mutate assets.status in the DB!
     */
    public function calculateAssetHealth(int $assetId): array
    {
        $asset = $this->assetModel->find($assetId);
        if (!$asset) {
            return [
                'asset_id'        => $assetId,
                'health_score'    => 0,
                'classification'  => 'CRITICAL',
                'scoring_version' => self::SCORING_VERSION,
                'deductions'      => [],
            ];
        }

        $baseScore = 100;
        $deductions = [];

        // 1. Active Unresolved Findings Deduction (Cap: Max -25)
        $unresolvedCount = $this->temuanModel->builder()
            ->where('asset_id', $assetId)
            ->whereIn('status', FindingIntelligenceService::UNRESOLVED_STATUSES)
            ->countAllResults();

        $findingDeduction = min(25, $unresolvedCount * 15);
        if ($findingDeduction > 0) {
            $deductions['active_findings'] = [
                'label'          => "Temuan Aktif Belum Selesai ({$unresolvedCount})",
                'deduction_pts'  => $findingDeduction,
                'max_cap'        => 25,
            ];
        }

        // 2. Thermovision Telemetry Hotspot Deduction (Cap: Max -30)
        $thermoTrend = $this->thermoService->getAssetThermovisionTrend($assetId, 1);
        $thermoDeduction = 0;
        if (!empty($thermoTrend)) {
            $latestThermo = $thermoTrend[0];
            if ($latestThermo['severity'] === 'CRITICAL_HOTSPOT') {
                $thermoDeduction = 30;
            } elseif ($latestThermo['severity'] === 'WARNING_HOTSPOT') {
                $thermoDeduction = 15;
            }

            if ($thermoDeduction > 0) {
                $deductions['thermovision_hotspot'] = [
                    'label'         => "Anomali Thermovision ({$latestThermo['severity']})",
                    'deduction_pts' => $thermoDeduction,
                    'max_cap'       => 30,
                ];
            }
        }

        // 3. Asset Age Deduction (Cap: Max -10)
        $installationDate = $asset['installation_date'] ?? null;
        $ageYears = 0;
        if ($installationDate) {
            $ageYears = (int)date_diff(date_create($installationDate), date_create('today'))->y;
        } elseif (!empty($asset['tahun_instalasi'])) {
            $ageYears = max(0, (int)date('Y') - (int)$asset['tahun_instalasi']);
        }

        $ageDeduction = 0;
        if ($ageYears >= 20) {
            $ageDeduction = 10;
        } elseif ($ageYears >= 10) {
            $ageDeduction = 5;
        }

        if ($ageDeduction > 0) {
            $deductions['asset_age'] = [
                'label'         => "Umur Aset ({$ageYears} Tahun)",
                'deduction_pts' => $ageDeduction,
                'max_cap'       => 10,
            ];
        }

        // 4. Overdue Inspection Deduction (Cap: Max -15)
        $updatedAt = $asset['updated_at'] ?? $asset['created_at'] ?? null;
        $monthsSinceInspection = 0;
        if ($updatedAt) {
            $monthsSinceInspection = (int)date_diff(date_create($updatedAt), date_create('today'))->m;
        }

        $overdueDeduction = 0;
        if ($monthsSinceInspection >= 6) {
            $overdueDeduction = 15;
        } elseif ($monthsSinceInspection >= 3) {
            $overdueDeduction = 8;
        }

        if ($overdueDeduction > 0) {
            $deductions['overdue_inspection'] = [
                'label'         => "Jadwal Inspeksi Terlewat ({$monthsSinceInspection} Bulan)",
                'deduction_pts' => $overdueDeduction,
                'max_cap'       => 15,
            ];
        }

        // Total Bounded Calculation
        $totalDeductions = $findingDeduction + $thermoDeduction + $ageDeduction + $overdueDeduction;
        $finalScore = max(0, min(100, $baseScore - $totalDeductions));

        $classification = 'GOOD';
        $healthColor    = '#059669'; // Green
        if ($finalScore < 50) {
            $classification = 'CRITICAL';
            $healthColor    = '#dc2626'; // Red
        } elseif ($finalScore < 80) {
            $classification = 'WATCH';
            $healthColor    = '#d97706'; // Orange/Yellow
        }

        return [
            'asset_id'        => $assetId,
            'kode_asset'      => $asset['kode_asset'] ?? '',
            'nama_asset'      => $asset['nama_asset'] ?? '',
            'jenis_asset'     => $asset['jenis_asset'] ?? '',
            'eam_status'      => $asset['status'] ?? 'NORMAL',
            'health_score'    => $finalScore,
            'classification'  => $classification,
            'health_color'    => $healthColor,
            'scoring_version' => self::SCORING_VERSION,
            'total_deduction' => $totalDeductions,
            'deductions'      => $deductions,
        ];
    }
}
