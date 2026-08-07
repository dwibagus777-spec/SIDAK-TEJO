<?php

namespace App\Services;

use Config\Database;

class HealthScoreService
{
    /**
     * Compute comprehensive Asset Health Score (0 - 100), Category, MTBF, and MTTR
     */
    public function computeHealthMetrics(int $assetId): array
    {
        $db = Database::connect();

        $asset = $db->table('assets')->where('id', $assetId)->get()->getRowArray();
        if (!$asset) {
            return [
                'health_score'    => 100,
                'health_category' => 'EXCELLENT',
                'health_color'    => '#10b981',
                'health_bg'       => 'bg-success',
                'mtbf_days'       => 180,
                'mttr_hours'      => 4.0,
            ];
        }

        $healthScore = 100;
        $emergencyCount = 0;
        $highCount = 0;

        // Fetch unresolved temuan linked to asset
        $temuanList = $db->table('temuan')
            ->where('asset_id', $assetId)
            ->where('deleted_at IS NULL')
            ->where('status !=', 'SELESAI')
            ->get()->getResultArray();

        foreach ($temuanList as $t) {
            $prio = strtoupper($t['prioritas'] ?? 'NORMAL');
            if ($prio === 'EMERGENCY') {
                $healthScore -= 25;
                $emergencyCount++;
            } elseif ($prio === 'HIGH') {
                $healthScore -= 15;
                $highCount++;
            } elseif ($prio === 'MEDIUM') {
                $healthScore -= 10;
            } else {
                $healthScore -= 5;
            }
        }

        // Age factor penalty
        $tahunInstalasi = (int)($asset['tahun_instalasi'] ?: date('Y'));
        $ageYears = max(0, (int)date('Y') - $tahunInstalasi);
        if ($ageYears > 5) {
            $healthScore -= min(25, ($ageYears - 5) * 2);
        }

        // Inspection Failures penalty
        $failCount = $db->table('asset_history')
            ->where('asset_id', $assetId)
            ->where('jenis_event', 'INSPECTION_FAIL')
            ->countAllResults();
        $healthScore -= ($failCount * 15);

        $healthScore = max(15, min(100, $healthScore));

        // Category Assignment
        if ($healthScore >= 80) {
            $healthCategory = 'EXCELLENT';
            $healthColor = '#10b981';
            $healthBg = 'bg-success';
        } elseif ($healthScore >= 60) {
            $healthCategory = 'FAIR';
            $healthColor = '#f59e0b';
            $healthBg = 'bg-warning text-dark';
        } elseif ($healthScore >= 40) {
            $healthCategory = 'WARNING';
            $healthColor = '#f97316';
            $healthBg = 'bg-orange';
        } else {
            $healthCategory = 'CRITICAL';
            $healthColor = '#ef4444';
            $healthBg = 'bg-danger';
        }

        // Compute MTBF (Mean Time Between Failures) in Days
        $totalTemuan = $db->table('temuan')->where('asset_id', $assetId)->countAllResults();
        $operatingDays = max(30, $ageYears * 365);
        $mtbfDays = ($totalTemuan > 0) ? round($operatingDays / $totalTemuan) : 365;

        // Compute MTTR (Mean Time To Repair) in Hours
        $completedWos = $db->table('work_orders')
            ->where('asset_id', $assetId)
            ->where('status', 'COMPLETED')
            ->get()->getResultArray();

        $totalRepairHours = 0;
        $woCount = count($completedWos);
        foreach ($completedWos as $wo) {
            if (!empty($wo['created_at']) && !empty($wo['updated_at'])) {
                $start = strtotime($wo['created_at']);
                $end   = strtotime($wo['updated_at']);
                if ($end > $start) {
                    $totalRepairHours += (($end - $start) / 3600);
                }
            }
        }
        $mttrHours = ($woCount > 0) ? round($totalRepairHours / $woCount, 1) : 4.0;

        return [
            'health_score'    => $healthScore,
            'health_category' => $healthCategory,
            'health_color'    => $healthColor,
            'health_bg'       => $healthBg,
            'mtbf_days'       => $mtbfDays,
            'mttr_hours'      => $mttrHours,
            'age_years'       => $ageYears,
        ];
    }

    /**
     * Recalculate & cache Health Score on the assets table
     */
    public function refreshCachedHealthScore(int $assetId): bool
    {
        $metrics = $this->computeHealthMetrics($assetId);
        $db = Database::connect();
        return (bool)$db->table('assets')->where('id', $assetId)->update([
            'health_score'    => $metrics['health_score'],
            'health_category' => $metrics['health_category'],
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
    }
}
