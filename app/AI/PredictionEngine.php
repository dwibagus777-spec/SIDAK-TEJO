<?php

namespace App\AI;

class PredictionEngine
{
    /**
     * Predict asset failure probability for 7, 30, and 90 days windows
     */
    public function predictAssetFailures(array $assets): array
    {
        $predictions7Days  = [];
        $predictions30Days = [];
        $predictions90Days = [];

        foreach ($assets as $asset) {
            $riskScore = (float)($asset['risk_score'] ?? 30);
            $age = (int)($asset['tahun_instalasi'] ? (date('Y') - $asset['tahun_instalasi']) : 5);
            $temuanCount = (int)($asset['total_temuan'] ?? 0);

            // 7 Days Probability (Highest weight on active critical findings)
            $prob7 = round(min(98, ($riskScore * 0.7) + ($temuanCount * 4)), 1);

            // 30 Days Probability
            $prob30 = round(min(99, ($riskScore * 0.85) + ($age * 1.5) + ($temuanCount * 3)), 1);

            // 90 Days Probability
            $prob90 = round(min(99.9, ($riskScore * 0.95) + ($age * 2.2) + ($temuanCount * 2)), 1);

            $assetData = array_merge($asset, [
                'prob_7_days'  => $prob7,
                'prob_30_days' => $prob30,
                'prob_90_days' => $prob90,
            ]);

            if ($prob7 >= 60) {
                $predictions7Days[] = $assetData;
            }
            if ($prob30 >= 50) {
                $predictions30Days[] = $assetData;
            }
            if ($prob90 >= 40) {
                $predictions90Days[] = $assetData;
            }
        }

        // Sort predictions by highest probability
        usort($predictions7Days, fn($a, $b) => $b['prob_7_days'] <=> $a['prob_7_days']);
        usort($predictions30Days, fn($a, $b) => $b['prob_30_days'] <=> $a['prob_30_days']);
        usort($predictions90Days, fn($a, $b) => $b['prob_90_days'] <=> $a['prob_90_days']);

        return [
            'days_7'  => array_slice($predictions7Days, 0, 10),
            'days_30' => array_slice($predictions30Days, 0, 10),
            'days_90' => array_slice($predictions90Days, 0, 10),
        ];
    }
}
