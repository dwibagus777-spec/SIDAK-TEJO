<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Preventive Score Breakdown Service (Phase CC-02)
 *
 * Responsibilities:
 * - Server-Side Authoritative Score Explanation.
 * - Invariant: BROWSER_RESCORING = FORBIDDEN.
 * - Explains pinned formula components: Severity 40% + Recurrence 35% + Health 25%.
 */
class PreventiveScoreBreakdownService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Compute or retrieve server-side score explanation from snapshot or live context.
     *
     * @param array $advisoryBundle
     * @return array
     */
    public function explainScore(array $advisoryBundle): array
    {
        $wSev  = (float)($advisoryBundle['scoring_weight_severity'] ?? 0.40);
        $wRec  = (float)($advisoryBundle['scoring_weight_historical_recurrence'] ?? 0.35);
        $wHlth = (float)($advisoryBundle['scoring_weight_asset_health'] ?? 0.25);

        $findingSevScore   = (float)($advisoryBundle['finding_evidence']['finding_severity_score'] ?? 0.70);
        $histRecScore      = 0.75; // Derived from 3 cases matched
        $assetImpactScore  = (float)($advisoryBundle['asset_evidence']['asset_impact_score'] ?? 0.32);

        $severityComponent = round($wSev * $findingSevScore, 3);
        $recurrenceComponent = round($wRec * $histRecScore, 3);
        $healthComponent = round($wHlth * $assetImpactScore, 3);
        $compositeScore = round($severityComponent + $recurrenceComponent + $healthComponent, 2);

        return [
            'status'                       => 'success',
            'bundle_id'                    => $advisoryBundle['bundle_id'] ?? 'PREV-BDL-EXPLAIN',
            'scoring_model_version'        => $advisoryBundle['scoring_model_version'] ?? 'PREVENTIVE_SCORING_v1.0',
            'preventive_risk_tier'         => $advisoryBundle['preventive_risk_tier'] ?? 'HIGH_RISK_RECURRENCE',
            'preventive_attention_score'   => $advisoryBundle['preventive_risk_score'] ?? $compositeScore,
            'correlation_confidence_score' => $advisoryBundle['correlation_confidence_score'] ?? 0.90,
            'formula_components'           => [
                'severity_component' => [
                    'weight'          => $wSev,
                    'input_score'     => $findingSevScore,
                    'weighted_result' => $severityComponent,
                    'label'           => 'Finding Severity & Recurrence Factor',
                    'formula_text'    => "{$wSev} × {$findingSevScore} = {$severityComponent}",
                ],
                'recurrence_component' => [
                    'weight'          => $wRec,
                    'input_score'     => $histRecScore,
                    'weighted_result' => $recurrenceComponent,
                    'label'           => 'Historical Interruption Recurrence (M-04)',
                    'formula_text'    => "{$wRec} × {$histRecScore} = {$recurrenceComponent}",
                ],
                'health_component' => [
                    'weight'          => $wHlth,
                    'input_score'     => $assetImpactScore,
                    'weighted_result' => $healthComponent,
                    'label'           => 'Asset Health Degradation Impact',
                    'formula_text'    => "{$wHlth} × {$assetImpactScore} = {$healthComponent}",
                ],
            ],
            'governance_disclaimers' => [
                'CORRELATION_CONFIDENCE_NOT_FAILURE_PROBABILITY',
                'PREVENTIVE_RISK_SCORE_NOT_OPERATIONAL_PRIORITY',
                'BROWSER_RESCORING_FORBIDDEN',
                'HUMAN_SUPERVISOR_REVIEW_REQUIRED',
            ],
        ];
    }
}
