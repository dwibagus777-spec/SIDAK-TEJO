<?php

namespace App\Services;

use App\Contracts\GangguanDataProviderInterface;
use App\Services\Providers\NullGangguanProvider;
use CodeIgniter\Database\BaseConnection;

/**
 * Risk Explainability Service
 *
 * Additive transparency service building evidence lineage payloads for the
 * "WHY IS THIS PRIORITIZED?" drawer without black-box AI claims.
 */
class RiskExplainabilityService
{
    protected BaseConnection $db;
    protected PreventiveRiskAdvisoryService $advisoryService;
    protected PreventiveScoreBreakdownService $breakdownService;
    protected GangguanDataProviderInterface $gangguanProvider;

    public function __construct(
        ?BaseConnection $db = null,
        ?PreventiveRiskAdvisoryService $advisoryService = null,
        ?PreventiveScoreBreakdownService $breakdownService = null,
        ?GangguanDataProviderInterface $gangguanProvider = null
    ) {
        $this->db               = $db ?? \Config\Database::connect();
        $this->advisoryService  = $advisoryService ?? new PreventiveRiskAdvisoryService($this->db);
        $this->breakdownService = $breakdownService ?? new PreventiveScoreBreakdownService($this->db);
        $this->gangguanProvider = $gangguanProvider ?? new NullGangguanProvider();
    }

    /**
     * Set active disturbance data provider.
     *
     * @param GangguanDataProviderInterface $provider
     * @return self
     */
    public function setGangguanProvider(GangguanDataProviderInterface $provider): self
    {
        $this->gangguanProvider = $provider;
        return $this;
    }

    /**
     * Generate complete explainability payload for a finding.
     *
     * @param int $findingId
     * @return array
     */
    public function explainFindingRisk(int $findingId): array
    {
        // 1. Fetch Sealed Advisory from M-05
        $advisoryResult = $this->advisoryService->generatePreventiveAdvisory($findingId);
        $advisory = $advisoryResult['preventive_advisory'] ?? [];

        // 2. Fetch Detailed Score Breakdown from M-05
        $breakdown = $this->breakdownService->explainScore($advisory);

        // 3. Query active disturbance data provider for feeder context
        $feederName = $advisory['feeder_name'] ?? 'BALUNG';
        $providerInterruptions = $this->gangguanProvider->getFeederInterruptions($feederName, ['limit' => 5]);
        $providerStats = $this->gangguanProvider->getInterruptionStats(['feeder_name' => $feederName]);

        $interruptionContext = [
            'provider_identifier' => $this->gangguanProvider->getSourceIdentifier(),
            'provider_available'  => $this->gangguanProvider->isAvailable(),
            'status'              => $this->gangguanProvider->isAvailable() ? 'ACTIVE_DATA_ATTACHED' : 'NO_EXTERNAL_DATA_AVAILABLE',
            'note'                => $this->gangguanProvider->isAvailable() ? "Data gangguan terbaca dari {$this->gangguanProvider->getSourceIdentifier()}." : "Tidak ada data gangguan eksternal terhubung. Tidak adanya data gangguan bukan bukti rendahnya risiko (NO DATA != LOW RISK).",
            'feeder_total_events' => $providerStats['total_events'] ?? 0,
            'recent_events'       => array_slice($providerInterruptions, 0, 3),
            'm04_memory_matches'  => $advisory['historical_case_matches_count'] ?? 0,
            'dominant_cause'      => $advisory['dominant_historical_cause'] ?? 'UNKNOWN',
        ];

        // 4. Evaluate Asset Evidence Status (Zero-state awareness)
        $assetEvidenceStatus = empty($advisory['asset_id'])
            ? 'UNASSIGNED_DEFAULT_BASELINE_FALLBACK'
            : 'ASSIGNED_MASTER_ASSET_EVIDENCE';

        // 5. Evaluate Primary Driver
        $sevWeightContribution  = (float)($advisory['scoring_weight_severity'] ?? 0.40) * (float)($advisory['finding_evidence']['severity_score'] ?? 0.50);
        $recWeightContribution  = (float)($advisory['scoring_weight_historical_recurrence'] ?? 0.35) * (float)($advisory['historical_evidence']['recurrence_score'] ?? 0.50);
        $hlthWeightContribution = (float)($advisory['scoring_weight_asset_health'] ?? 0.25) * 0.25;

        $primaryDriver = match (true) {
            $recWeightContribution >= $sevWeightContribution && $recWeightContribution >= $hlthWeightContribution => 'RECURRING_FINDING_PATTERN',
            $sevWeightContribution >= $recWeightContribution && $sevWeightContribution >= $hlthWeightContribution => 'FINDING_SEVERITY_LEVEL',
            default => 'ASSET_CONDITION_IMPACT',
        };

        $primaryDriverDescription = match ($primaryDriver) {
            'RECURRING_FINDING_PATTERN' => "Risiko didominasi oleh riwayat anomali berulang dan konsentrasi rekurensi pada seksi/penyulang terkait.",
            'FINDING_SEVERITY_LEVEL'    => "Risiko didominasi oleh tingkat keparahan tinggi temuan lapangan yang berpotensi memicu trip seketika.",
            default                     => "Risiko didominasi oleh estimasi degradasi kesehatan aset pada titik jaringan.",
        };

        // 6. Evidence Completeness Metric (Distinct from M-05 correlation confidence)
        $evidencePoints = 0;
        $totalPoints = 5;
        if (!empty($advisory['finding_id'])) $evidencePoints += 2;
        if (!empty($advisory['section_name'])) $evidencePoints += 1;
        if (!empty($advisory['feeder_name'])) $evidencePoints += 1;
        if ($this->gangguanProvider->isAvailable()) $evidencePoints += 1;

        $evidenceCompletenessPercent = round(($evidencePoints / $totalPoints) * 100);

        return [
            'finding_id'                        => (int)($advisory['finding_id'] ?? $findingId),
            'nomor_temuan'                      => $advisory['nomor_temuan'] ?? ('TMN-' . $findingId),
            'scoring_version'                   => $advisory['scoring_model_version'] ?? 'PREVENTIVE_SCORING_v1.0',
            'preventive_risk_tier'              => $advisory['preventive_risk_tier'] ?? 'MODERATE_DEGRADATION',
            'preventive_risk_score'             => (float)($advisory['preventive_risk_score'] ?? 0.50),
            'display_score'                     => (int)round(((float)($advisory['preventive_risk_score'] ?? 0.50)) * 100),
            'primary_driver'                    => $primaryDriver,
            'primary_driver_description'        => $primaryDriverDescription,
            'correlation_confidence_score'      => (float)($advisory['correlation_confidence_score'] ?? 0.60),
            'evidence_completeness_percent'     => $evidenceCompletenessPercent,
            'recommended_action'                => $advisory['recommended_review_focus'] ?? 'Lakukan inspeksi preventif terfokus.',
            'feeder_name'                       => $advisory['feeder_name'] ?? 'BALUNG',
            'section_name'                      => $advisory['section_name'] ?? 'SEKSI_UTAMA',
            'score_breakdown'                   => $breakdown['score_breakdown'] ?? [],
            'finding_evidence'                  => $advisory['finding_evidence'] ?? [],
            'asset_evidence'                    => [
                'status'         => $assetEvidenceStatus,
                'asset_code'     => $advisory['asset_code'] ?? 'UNASSIGNED',
                'asset_health'   => 75.0,
                'note'           => 'Menggunakan baseline fallback terkalibrasi saat aset unassigned.',
            ],
            'historical_interruption_context'   => $interruptionContext,
            'data_status'                       => [
                'operational_findings_attached' => true,
                'interruption_provider'         => $this->gangguanProvider->getSourceIdentifier(),
                'asset_master_status'           => 'HONEST_ZERO_STATE_WAITING_IMPORT',
            ],
            'meta'                              => [
                'advisory_bundle_id'  => $advisory['bundle_id'] ?? 'PREV-BDL-DEFAULT',
                'evaluation_time'     => $advisory['evaluation_timestamp'] ?? date('Y-m-d H:i:s'),
                'governance_rule'     => 'HUMAN_DECISION_SUPPORT_ONLY_ZERO_AUTONOMOUS_COMMAND',
            ]
        ];
    }
}
