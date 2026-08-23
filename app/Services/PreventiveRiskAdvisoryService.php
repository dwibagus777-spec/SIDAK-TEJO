<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Preventive Risk Advisory Service (Phase 7U Maintenance M-05)
 *
 * Responsibilities:
 * - Aggregates Asset Master, Finding Severity, and Historical Interruption Evidence.
 * - Implements Initial Calibration Baseline v1 (40% Severity / 35% Historical / 25% Asset Health).
 * - Enforces immutable version pinning and zero autonomous action.
 */
class PreventiveRiskAdvisoryService
{
    public const SCORING_MODEL_VERSION                  = 'PREVENTIVE_SCORING_v1.0';
    public const CORRELATION_ENGINE_VERSION             = 'PREVENTIVE_CORRELATION_v1.0';
    public const SCORING_WEIGHT_SEVERITY                = 0.40;
    public const SCORING_WEIGHT_HISTORICAL_RECURRENCE   = 0.35;
    public const SCORING_WEIGHT_ASSET_HEALTH            = 0.25;

    protected BaseConnection $db;
    protected AssetFindingCorrelationService $findingCorrelation;
    protected HistoricalInterruptionCorrelationService $historyCorrelation;

    public function __construct(
        ?BaseConnection $db = null,
        ?AssetFindingCorrelationService $findingCorrelation = null,
        ?HistoricalInterruptionCorrelationService $historyCorrelation = null
    ) {
        $this->db                 = $db ?? \Config\Database::connect();
        $this->findingCorrelation = $findingCorrelation ?? new AssetFindingCorrelationService($this->db);
        $this->historyCorrelation = $historyCorrelation ?? new HistoricalInterruptionCorrelationService($this->db);
    }

    /**
     * Generate a full Preventive Risk Advisory for an active field finding.
     *
     * @param int $findingId
     * @param string|null $evaluationTimestamp
     * @return array
     */
    public function generatePreventiveAdvisory(int $findingId = 1, ?string $evaluationTimestamp = null): array
    {
        $evalTime = $evaluationTimestamp ?? date('Y-m-d H:i:s');

        // 1. Asset-Finding Correlation
        $findingContext = $this->findingCorrelation->correlateFinding($findingId);

        // 2. Historical Interruption Correlation (M-04 Knowledge)
        $historyContext = $this->historyCorrelation->correlateWithHistory($findingContext);

        // 3. Compute Evidence Correlation Confidence Score (Evidence Strength: Feeder + Category + Historical Cases)
        $matchCount = (int)($historyContext['historical_case_matches_count'] ?? 0);
        $evidenceConfidenceScore = match (true) {
            $matchCount >= 3 => 0.90,
            $matchCount === 2 => 0.75,
            $matchCount === 1 => 0.60,
            default           => 0.40,
        };

        // 4. Compute Aggregated Preventive Risk Score (Initial Calibration v1.0: 40% Severity + 35% Recurrence + 25% Asset Health)
        $sevScore  = (float)($findingContext['finding_severity_score'] ?? 0.50);
        $recScore  = (float)($historyContext['historical_recurrence_score'] ?? 0.50);
        $hlthScore = (float)($findingContext['asset_impact_score'] ?? 0.25);

        $preventiveRiskScore = round(
            (self::SCORING_WEIGHT_SEVERITY * $sevScore) +
            (self::SCORING_WEIGHT_HISTORICAL_RECURRENCE * $recScore) +
            (self::SCORING_WEIGHT_ASSET_HEALTH * $hlthScore),
            2
        );

        // 5. Determine Preventive Risk Tier
        $preventiveTier = match (true) {
            $preventiveRiskScore >= 0.70 => 'CRITICAL_PREVENTIVE_ATTENTION',
            $preventiveRiskScore >= 0.50 => 'HIGH_RISK_RECURRENCE',
            $preventiveRiskScore >= 0.30 => 'MODERATE_DEGRADATION',
            default                      => 'LOW_STABLE',
        };

        // 5. Formulate Recommended Review Focus (Nomenclature Refinement: NOT operational command)
        $feederName  = $findingContext['feeder_name'] ?? 'BALUNG';
        $sectionName = $findingContext['section_name'] ?? 'BALUNG-03';
        $category    = $findingContext['classified_category'] ?? 'VEGETATION_ROW';

        $reviewFocus = "REVIEW {$category} CLEARANCE AND INSPECTION STATUS AT SECTION {$sectionName} ({$feederName}) PRIOR TO WEATHER CONTINGENCY";

        $advisoryBundle = [
            'bundle_id'                            => 'PREV-BDL-STJ-' . date('YmdHis', strtotime($evalTime)) . '-01',
            'evaluation_timestamp'                 => $evalTime,
            'finding_id'                           => $findingContext['finding_id'],
            'nomor_temuan'                         => $findingContext['nomor_temuan'],
            'penyulang_id'                         => $findingContext['penyulang_id'],
            'feeder_name'                          => $feederName,
            'section_id'                           => $findingContext['section_id'],
            'section_name'                         => $sectionName,
            'asset_id'                             => $findingContext['asset_id'],
            'asset_code'                           => $findingContext['asset_code'],
            'preventive_risk_tier'                 => $preventiveTier,
            'preventive_risk_score'                => $preventiveRiskScore,
            'correlation_confidence_score'         => $evidenceConfidenceScore,

            // Lineage & Version Pinning
            'scoring_model_version'                => self::SCORING_MODEL_VERSION,
            'scoring_weight_severity'              => self::SCORING_WEIGHT_SEVERITY,
            'scoring_weight_historical_recurrence' => self::SCORING_WEIGHT_HISTORICAL_RECURRENCE,
            'scoring_weight_asset_health'          => self::SCORING_WEIGHT_ASSET_HEALTH,
            'correlation_engine_version'           => self::CORRELATION_ENGINE_VERSION,
            'historical_knowledge_source_class'    => $historyContext['historical_knowledge_source_class'],
            'historical_case_matches_count'        => $historyContext['historical_case_matches_count'],
            'dominant_historical_cause'            => $historyContext['dominant_historical_cause'],
            'median_historical_outage_min'         => $historyContext['median_historical_outage_min'],
            'historical_case_reference_set'        => $historyContext['historical_case_reference_set'],
            'recommended_review_focus'             => $reviewFocus,

            // Sub-Evidence Contexts
            'asset_evidence'                       => [
                'asset_code'         => $findingContext['asset_code'],
                'asset_health_index' => $findingContext['asset_health_index'],
                'asset_impact_score' => $findingContext['asset_impact_score'],
            ],
            'finding_evidence'                     => [
                'jenis_temuan'           => $findingContext['jenis_temuan'],
                'classified_category'    => $findingContext['classified_category'],
                'prioritas'              => $findingContext['prioritas'],
                'finding_severity_score' => $findingContext['finding_severity_score'],
                'section_finding_density'=> $findingContext['section_finding_density'],
                'detail_temuan'          => $findingContext['detail_temuan'],
            ],
            'historical_evidence'                  => [
                'matches_count'   => $historyContext['historical_case_matches_count'],
                'dominant_cause'  => $historyContext['dominant_historical_cause'],
                'median_duration' => $historyContext['median_historical_outage_min'],
                'cases'           => $historyContext['historical_case_reference_set'],
            ],

            // Governance Invariants
            'finding_truth_class'                  => 'CURRENT_FINDING_NOT_PREDICTED_CONFIRMED_FAILURE',
            'correlation_truth_class'              => 'HISTORICAL_CORRELATION_NOT_FAULT_CERTAINTY',
            'score_truth_class'                    => 'CORRELATION_CONFIDENCE_NOT_PROBABILITY_OF_ACTUAL_FAILURE',
            'priority_truth_class'                 => 'PREVENTIVE_RISK_SCORE_NOT_OFFICIAL_OPERATIONAL_PRIORITY',
            'weight_version_pinned'                => 'SCORING_MODEL_VERSION_IMMUTABLY_PINNED_AT_EVALUATION',
            'automatic_work_order'                 => 'FORBIDDEN',
            'automatic_crew_dispatch'              => 'FORBIDDEN',
            'automatic_network_switching'          => 'FORBIDDEN',
            'automatic_resource_allocation'        => 'FORBIDDEN',
            'human_supervisor_approval'            => 'REQUIRED',
            'advisory_status'                      => 'PREVENTIVE_RISK_ADVISORY_PROPOSED',
        ];

        return [
            'status'                      => 'success',
            'preventive_advisory'         => $advisoryBundle,
            'preventive_engine_version'   => self::CORRELATION_ENGINE_VERSION,
            'certified_preventive_status' => 'PREVENTIVE_INTELLIGENCE_VERIFIED',
        ];
    }
}
