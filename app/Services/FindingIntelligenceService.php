<?php

namespace App\Services;

use App\Models\TemuanModel;

class FindingIntelligenceService
{
    private TemuanModel $temuanModel;

    // Active unresolved finding statuses that impact asset health
    public const UNRESOLVED_STATUSES = ['TEMUAN_CREATED', 'BERMASALAH', 'WO_CREATED', 'MAINTENANCE', 'MENUNGGU_VERIFIKASI'];

    public function __construct()
    {
        $this->temuanModel = new TemuanModel();
    }

    /**
     * Get Top 10 Most Problematic Assets ranked by active unresolved finding count
     */
    public function getTopProblematicAssets(int $limit = 10): array
    {
        return $this->temuanModel->builder()
            ->select('assets.id as asset_id, assets.kode_asset, assets.nama_asset, assets.jenis_asset, assets.status as eam_status, COUNT(data_temuan.id) as active_finding_count')
            ->join('assets', 'assets.id = data_temuan.asset_id')
            ->whereIn('data_temuan.status', self::UNRESOLVED_STATUSES)
            ->where('assets.deleted_at', null)
            ->groupBy('data_temuan.asset_id')
            ->orderBy('active_finding_count', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Detect Repeat Defects (2+ findings in same category within last 90 days) vs Multiple Defect Categories
     */
    public function detectDefectPatterns(int $assetId): array
    {
        $ninetyDaysAgo = date('Y-m-d H:i:s', strtotime('-90 days'));

        $findings = $this->temuanModel->builder()
            ->select('jenis_temuan, COUNT(id) as cnt')
            ->where('asset_id', $assetId)
            ->where('tanggal_temuan >=', $ninetyDaysAgo)
            ->groupBy('jenis_temuan')
            ->get()
            ->getResultArray();

        $categoryCounts = [];
        $isRepeatDefect  = false;
        $totalFindings   = 0;

        foreach ($findings as $f) {
            $cat = $f['jenis_temuan'] ?: 'KONSTRUKSI';
            $cnt = (int)$f['cnt'];
            $categoryCounts[$cat] = $cnt;
            $totalFindings += $cnt;

            if ($cnt >= 2) {
                $isRepeatDefect = true;
            }
        }

        $distinctCategoriesCount = count($categoryCounts);
        $isMultipleCategories    = ($distinctCategoriesCount >= 2);

        return [
            'asset_id'                  => $assetId,
            'total_90d_findings'        => $totalFindings,
            'distinct_categories_count' => $distinctCategoriesCount,
            'is_repeat_defect'          => $isRepeatDefect,
            'is_multiple_categories'    => $isMultipleCategories,
            'category_breakdown'        => $categoryCounts,
        ];
    }

    /**
     * Get Defect Severity Matrix Breakdown for Unresolved Findings
     */
    public function getSeverityBreakdown(): array
    {
        $results = $this->temuanModel->builder()
            ->select('prioritas, COUNT(id) as total')
            ->whereIn('status', self::UNRESOLVED_STATUSES)
            ->groupBy('prioritas')
            ->get()
            ->getResultArray();

        $breakdown = [
            'KRITIS'  => 0,
            'TINGGI'  => 0,
            'SEDANG'  => 0,
            'RENDAH'  => 0,
        ];

        foreach ($results as $r) {
            $prio = strtoupper((string)($r['prioritas'] ?? 'SEDANG'));
            if (isset($breakdown[$prio])) {
                $breakdown[$prio] = (int)$r['total'];
            }
        }

        return $breakdown;
    }
}
