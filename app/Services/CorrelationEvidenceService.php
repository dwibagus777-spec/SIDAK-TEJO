<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Correlation Evidence Service (Phase 7U Maintenance M-05)
 *
 * Responsibilities:
 * - Persists and retrieves auditable Preventive Risk Advisory Snapshots.
 * - Stores immutable evidence lineage (Asset, Finding, Historical Matches, Scoring Weights).
 */
class CorrelationEvidenceService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Save an immutable advisory snapshot to the database.
     *
     * @param array $bundle Output from PreventiveRiskAdvisoryService->generatePreventiveAdvisory()['preventive_advisory']
     * @return int Snapshot ID
     */
    public function saveSnapshot(array $bundle): int
    {
        if (!$this->db->tableExists('preventive_risk_advisory_snapshots')) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $evalTime = $bundle['evaluation_timestamp'] ?? $now;

        $referenceSetString = !empty($bundle['historical_case_reference_set'])
            ? json_encode($bundle['historical_case_reference_set'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $payloadJson = json_encode([
            'bundle_id'            => $bundle['bundle_id'],
            'evaluation_timestamp' => $evalTime,
            'asset_evidence'       => $bundle['asset_evidence'] ?? [],
            'finding_evidence'     => $bundle['finding_evidence'] ?? [],
            'historical_evidence'  => $bundle['historical_evidence'] ?? [],
            'scoring_weights'      => [
                'model_version'         => $bundle['scoring_model_version'],
                'severity_weight'       => $bundle['scoring_weight_severity'],
                'recurrence_weight'     => $bundle['scoring_weight_historical_recurrence'],
                'asset_health_weight'   => $bundle['scoring_weight_asset_health'],
            ],
            'governance'           => [
                'automatic_work_order'        => $bundle['automatic_work_order'] ?? 'FORBIDDEN',
                'automatic_crew_dispatch'     => $bundle['automatic_crew_dispatch'] ?? 'FORBIDDEN',
                'automatic_network_switching' => $bundle['automatic_network_switching'] ?? 'FORBIDDEN',
                'human_supervisor_approval'   => $bundle['human_supervisor_approval'] ?? 'REQUIRED',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $data = [
            'snapshot_code'                        => $bundle['bundle_id'],
            'asset_id'                             => $bundle['asset_id'] ?? null,
            'temuan_id'                            => $bundle['finding_id'] ?? null,
            'penyulang_id'                         => (int)($bundle['penyulang_id'] ?? 1),
            'section_id'                           => $bundle['section_id'] ?? null,
            'feeder_name'                          => $bundle['feeder_name'] ?? 'BALUNG',
            'section_name'                         => $bundle['section_name'] ?? 'BALUNG-03',
            'preventive_risk_tier'                 => $bundle['preventive_risk_tier'] ?? 'MODERATE_DEGRADATION',
            'correlation_confidence_score'         => (float)($bundle['correlation_confidence_score'] ?? 0.50),
            'scoring_model_version'                => $bundle['scoring_model_version'] ?? 'PREVENTIVE_SCORING_v1.0',
            'scoring_weight_severity'              => (float)($bundle['scoring_weight_severity'] ?? 0.40),
            'scoring_weight_historical_recurrence' => (float)($bundle['scoring_weight_historical_recurrence'] ?? 0.35),
            'scoring_weight_asset_health'          => (float)($bundle['scoring_weight_asset_health'] ?? 0.25),
            'active_findings_count'                => (int)($bundle['finding_evidence']['section_finding_density'] ?? 1),
            'historical_case_matches_count'        => (int)($bundle['historical_case_matches_count'] ?? 0),
            'historical_case_reference_set'        => $referenceSetString,
            'dominant_historical_cause'            => $bundle['dominant_historical_cause'] ?? 'ROW',
            'median_historical_outage_min'         => (float)($bundle['median_historical_outage_min'] ?? 45.0),
            'recommended_review_focus'             => $bundle['recommended_review_focus'] ?? 'REVIEW VEGETATION CLEARANCE',
            'historical_knowledge_source_class'    => $bundle['historical_knowledge_source_class'] ?? 'EXTERNAL_HISTORICAL_INTERRUPTION_KNOWLEDGE',
            'correlation_engine_version'           => $bundle['correlation_engine_version'] ?? 'PREVENTIVE_CORRELATION_v1.0',
            'evaluation_timestamp'                 => $evalTime,
            'advisory_payload_json'                => $payloadJson,
            'governance_status'                    => 'ADVISORY_PROPOSED',
            'created_at'                           => $now,
            'updated_at'                           => $now,
        ];

        $this->db->table('preventive_risk_advisory_snapshots')->insert($data);
        return (int)$this->db->insertID();
    }

    /**
     * Retrieve snapshot by ID.
     */
    public function getSnapshot(int $snapshotId): ?array
    {
        return $this->db->table('preventive_risk_advisory_snapshots')
                        ->where('id', $snapshotId)
                        ->get()
                        ->getRowArray();
    }

    /**
     * Retrieve snapshots by feeder.
     */
    public function getSnapshotsByFeeder(int $feederId, int $limit = 10): array
    {
        return $this->db->table('preventive_risk_advisory_snapshots')
                        ->where('penyulang_id', $feederId)
                        ->orderBy('id', 'DESC')
                        ->limit($limit)
                        ->get()
                        ->getResultArray();
    }
}
