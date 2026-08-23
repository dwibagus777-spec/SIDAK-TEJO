<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class PredictiveRiskService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Calculate Predictive Risk Forecast for a specific Asset (Phase 2N)
     */
    public function predictAssetRiskForecast(int $assetId): array
    {
        $asset = $this->db->table('assets')
            ->where('id', $assetId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if (!$asset) {
            return [
                'status'  => 'error',
                'message' => "Asset #{$assetId} not found.",
            ];
        }

        $currentScore = (float)($asset['health_score'] ?? 100.0);
        $currentCategory = $asset['health_category'] ?? 'EXCELLENT';

        // Fetch historical audit logs for asset to compute deterioration velocity
        $historyLogs = $this->db->table('asset_health_history')
            ->where('asset_id', $assetId)
            ->orderBy('id', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        $historyCnt = count($historyLogs);

        // Deterioration Velocity (% degradation per 30 days)
        $deteriorationRatePerMonth = 1.5; // Base default 1.5% per month
        if ($historyCnt >= 2) {
            $latestScore = (float)($historyLogs[0]['score'] ?? $historyLogs[0]['health_score'] ?? $historyLogs[0]['final_score'] ?? $currentScore);
            $olderScore  = (float)($historyLogs[$historyCnt - 1]['score'] ?? $historyLogs[$historyCnt - 1]['health_score'] ?? $historyLogs[$historyCnt - 1]['final_score'] ?? $currentScore);
            $delta       = $olderScore - $latestScore;
            if ($delta > 0) {
                $deteriorationRatePerMonth = min(15.0, max(0.5, round($delta * 1.2, 2)));
            }
        }

        // Projections
        $projected7Days  = max(0.0, round($currentScore - ($deteriorationRatePerMonth * (7 / 30)), 2));
        $projected30Days = max(0.0, round($currentScore - $deteriorationRatePerMonth, 2));
        $projected90Days = max(0.0, round($currentScore - ($deteriorationRatePerMonth * 3), 2));

        // Fetch active action cases & recurring observations
        $activeCases = $this->db->table('observation_action_cases')
            ->where('asset_id', $assetId)
            ->whereNotIn('status', ['VERIFIED', 'SUPERSEDED'])
            ->get()
            ->getResultArray();

        $activeCaseCnt = count($activeCases);
        $hasEmergency  = false;
        foreach ($activeCases as $c) {
            if (($c['priority'] ?? 5) === 1) {
                $hasEmergency = true;
                break;
            }
        }

        // Escalation Probability Calculation
        $escalationProb = 10.0; // Base 10%
        if ($hasEmergency) {
            $escalationProb = 95.0;
        } elseif ($activeCaseCnt > 0) {
            $escalationProb = min(90.0, 35.0 + ($activeCaseCnt * 20.0));
        } elseif ($currentScore < 60.0) {
            $escalationProb = 65.0;
        }

        // Predictive Maintenance Priority (PMP Index 0 - 100)
        $pmpIndex = min(100.0, round((100.0 - $currentScore) * 0.5 + ($escalationProb * 0.3) + ($deteriorationRatePerMonth * 2.0), 2));

        // Confidence Contract
        $confidenceScore = 0.85;
        $sufficiency     = 'HIGH';
        if ($historyCnt < 3) {
            $confidenceScore = 0.65;
            $sufficiency     = 'MEDIUM';
        }

        $predictionBasis = [
            "Historical HI Trend ({$historyCnt} audit records analyzed)",
            "Deterioration Velocity: {$deteriorationRatePerMonth}% / month",
            "Active Action Cases Count: {$activeCaseCnt}",
        ];

        if ($hasEmergency) {
            $predictionBasis[] = "Active Emergency (P1) Operational Case Triggered";
        }

        return [
            'status'                         => 'success',
            'asset_id'                       => $assetId,
            'nama_asset'                     => $asset['nama_asset'],
            'current_health_score'           => $currentScore,
            'current_health_category'        => $currentCategory,
            'deterioration_rate_per_month'   => $deteriorationRatePerMonth,
            'projected_score_7d'             => $projected7Days,
            'projected_score_30d'            => $projected30Days,
            'projected_score_90d'            => $projected90Days,
            'escalation_probability_pct'     => $escalationProb,
            'predictive_maintenance_pmp'     => $pmpIndex,
            'confidence_contract'            => [
                'prediction_score'  => $projected30Days,
                'confidence_score'  => $confidenceScore,
                'data_sufficiency'  => $sufficiency,
                'prediction_basis'  => $predictionBasis,
                'engine_version'    => 'PREDICTIVE_ENGINE_v1.0',
            ],
        ];
    }

    /**
     * Predict Feeder Risk Concentration for 30-Day Horizon
     */
    public function predictFeederRiskConcentration(string $feederCode): array
    {
        $db = $this->db;
        $topoService = new NetworkTopologyService($db);

        $nriV2 = $topoService->calculateFeederNetworkRiskIndex($feederCode);

        $currentAvgHi   = (float)($nriV2['avg_health_index'] ?? 100.0);
        $activeP1Cnt    = (int)($nriV2['active_p1_cases'] ?? 0);
        $activeP2Cnt    = (int)($nriV2['active_p2_cases'] ?? 0);

        // Projected 30-Day Average HI Drop
        $projectedHiDrop = round(1.5 + ($activeP1Cnt * 4.0) + ($activeP2Cnt * 2.0), 2);
        $projected30dHi  = max(0.0, round($currentAvgHi - $projectedHiDrop, 2));

        $riskTrend = 'STABLE';
        if ($projectedHiDrop >= 5.0) {
            $riskTrend = 'HIGH_DETERIORATION_RISK';
        } elseif ($projectedHiDrop >= 2.5) {
            $riskTrend = 'MODERATE_DETERIORATION_RISK';
        }

        return [
            'feeder_code'                 => $feederCode,
            'current_avg_health_index'    => $currentAvgHi,
            'projected_30d_avg_hi'        => $projected30dHi,
            'projected_hi_degradation'    => $projectedHiDrop,
            'feeder_risk_trend'           => $riskTrend,
            'predictive_engine_version'   => 'PREDICTIVE_FEEDER_v1.0',
        ];
    }
}
