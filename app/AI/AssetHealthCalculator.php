<?php

namespace App\AI;

class AssetHealthCalculator
{
    /**
     * Calculate Asset Health Index (0 - 100) and Category Label & Color
     */
    public function calculateHealthIndex(int $activeEmergency, int $activeHigh, int $activeMedium, int $activeLow, int $totalDisruptions = 0, int $ageYears = 5): array
    {
        $score = 100;

        // Deductions based on finding severity
        $score -= ($activeEmergency * 25);
        $score -= ($activeHigh * 12);
        $score -= ($activeMedium * 5);
        $score -= ($activeLow * 2);

        // Deductions based on disruption history & age
        $score -= min(15, $totalDisruptions * 3);
        if ($ageYears > 10) {
            $score -= min(10, ($ageYears - 10) * 1.5);
        }

        $score = max(0, min(100, (int)round($score)));

        if ($score >= 90) {
            $category = 'Sangat Baik'; $color = '#10b981'; $badge = 'bg-success';
        } elseif ($score >= 75) {
            $category = 'Baik'; $color = '#84cc16'; $badge = 'bg-lime';
        } elseif ($score >= 60) {
            $category = 'Perlu Monitoring'; $color = '#f59e0b'; $badge = 'bg-warning text-dark';
        } elseif ($score >= 40) {
            $category = 'Kurang Baik'; $color = '#f97316'; $badge = 'bg-orange';
        } else {
            $category = 'Kritis'; $color = '#ef4444'; $badge = 'bg-danger';
        }

        return [
            'score'    => $score,
            'category' => $category,
            'color'    => $color,
            'badge'    => $badge,
        ];
    }

    /**
     * Calculate Risk Score (0 - 100) and AI Failure Probabilities
     */
    public function calculateRiskProbabilities(int $healthScore, int $rowFindings = 0, int $hotspotFindings = 0, int $constructionFindings = 0): array
    {
        $riskScore = max(0, min(100, 100 - $healthScore));

        $probGangguan = min(98, round($riskScore * 0.95));
        $probTrip     = min(95, round($riskScore * 0.85));
        $probOcr      = min(90, round($riskScore * 0.75));
        $probDgr      = min(85, round($riskScore * 0.65));
        $probOcrdgr   = min(80, round($riskScore * 0.55));

        return [
            'risk_score'       => $riskScore,
            'prob_gangguan'    => $probGangguan,
            'prob_trip'        => $probTrip,
            'prob_ocr'         => $probOcr,
            'prob_dgr'         => $probDgr,
            'prob_ocrdgr'      => $probOcrdgr,
            'risk_pohon'       => $rowFindings > 0 ? 'TINGGI (ROW Pruning Required)' : 'RENDAH',
            'risk_hotspot'     => $hotspotFindings > 0 ? 'KRITIS (Thermovision Check Needed)' : 'NORMAL',
            'risk_konstruksi'  => $constructionFindings > 0 ? 'PERLU REPAIR' : 'BAIK',
        ];
    }
}
