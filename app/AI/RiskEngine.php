<?php

namespace App\AI;

class RiskEngine
{
    /**
     * Calculate Risk Score (0 - 100) for an asset or finding
     * 
     * Factors:
     * - Temuan count (30%)
     * - Priority weight (Emergency/High) (25%)
     * - Asset age (20%)
     * - WO failure frequency (15%)
     * - Status pending (10%)
     */
    public function calculateRiskScore(array $metrics): array
    {
        $temuanCount = (int)($metrics['temuan_count'] ?? 0);
        $emergencyCount = (int)($metrics['emergency_count'] ?? 0);
        $highCount = (int)($metrics['high_count'] ?? 0);
        $assetAgeYears = (int)($metrics['asset_age_years'] ?? 5);
        $woFailureCount = (int)($metrics['wo_failure_count'] ?? 0);
        $statusPending = !empty($metrics['is_pending']) ? 1 : 0;

        // 1. Temuan Score (max 30 pts)
        $scoreTemuan = min(30, $temuanCount * 6);

        // 2. Priority Weight (max 25 pts)
        $scorePriority = min(25, ($emergencyCount * 15) + ($highCount * 7));

        // 3. Asset Age Score (max 20 pts)
        $scoreAge = min(20, $assetAgeYears * 1.2);

        // 4. WO Failure Score (max 15 pts)
        $scoreWo = min(15, $woFailureCount * 5);

        // 5. Status Pending Score (max 10 pts)
        $scoreStatus = $statusPending ? 10 : 0;

        $totalScore = round(min(100, $scoreTemuan + $scorePriority + $scoreAge + $scoreWo + $scoreStatus), 1);

        $category = match(true) {
            $totalScore >= 76 => 'CRITICAL',
            $totalScore >= 51 => 'HIGH',
            $totalScore >= 26 => 'MEDIUM',
            default           => 'LOW'
        };

        $badgeClass = match($category) {
            'CRITICAL' => 'bg-danger text-white animate__animated animate__pulse animate__infinite',
            'HIGH'     => 'bg-warning text-dark',
            'MEDIUM'   => 'bg-info text-white',
            default    => 'bg-success text-white'
        };

        return [
            'score'       => $totalScore,
            'category'    => $category,
            'badge_class' => $badgeClass,
            'breakdown'   => [
                'temuan_score'   => $scoreTemuan,
                'priority_score' => $scorePriority,
                'age_score'      => $scoreAge,
                'wo_score'       => $scoreWo,
                'status_score'   => $scoreStatus,
            ]
        ];
    }
}
