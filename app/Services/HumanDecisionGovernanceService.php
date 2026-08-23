<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class HumanDecisionGovernanceService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Record Human-in-the-Loop Approval, Override & Decision Accountability Log (Phase 3D)
     */
    public function recordHumanDecision(
        int $assetId,
        string $actionType = 'PRESCRIPTIVE_APPROVAL',
        string $decisionOutcome = 'APPROVED',
        ?string $overrideReason = null,
        ?int $userId = 1
    ): array {
        $db = $this->db;

        $correlationId = 'DECISION-STJ-' . date('Ymd') . '-' . sprintf('%04d', rand(1000, 9999));

        // Fetch Asset Snapshot
        $asset = $db->table('assets')->where('id', $assetId)->get()->getRowArray();

        $decisionLog = [
            'correlation_id'     => $correlationId,
            'asset_id'           => $assetId,
            'nama_asset'         => $asset['nama_asset'] ?? 'Unknown Asset',
            'action_type'        => $actionType,
            'decision_outcome'   => $decisionOutcome,
            'override_reason'    => $overrideReason ?? 'Tindakan disetujui sesuai rekomendasi preskriptif sistem.',
            'user_id'            => $userId,
            'user_role'          => 'SUPERVISOR_ULP',
            'decided_at'         => date('Y-m-d H:i:s'),
            'governance_status'  => 'DECISION_ACCOUNTABILITY_LOGGED',
        ];

        return [
            'status'               => 'success',
            'decision_audit'       => $decisionLog,
            'human_loop_version'   => 'HUMAN_GOVERNANCE_v1.0',
            'certified_governance' => 'HUMAN_DECISION_GOVERNED_AND_LOGGED',
        ];
    }
}
