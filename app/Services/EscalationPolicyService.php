<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class EscalationPolicyService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Evaluate Alert Escalation Policy & Acknowledgment Tracking (Phase 3F)
     */
    public function evaluateAlertEscalationPolicy(int $caseId, int $elapsedHours = 5): array
    {
        $db = $this->db;

        $case = $db->table('observation_action_cases')->where('id', $caseId)->get()->getRowArray();
        $severity = $case['severity_at_open'] ?? 'HIGH';

        $prio = OperationalIntelligenceService::resolveRiskPriority($severity);
        $slaHrs = (int)($prio['resolution_sla_hrs'] ?? 72);

        $slaRatio = $slaHrs > 0 ? ($elapsedHours / $slaHrs) : 0;

        if ($slaRatio >= 1.0) {
            $escalationLevel  = 'LEVEL_2_MANAGER_ESCALATION';
            $targetRole       = 'MANAJER_ULP_DAN_DALOPS';
            $escalationStatus = 'ESCALATED_TO_EXECUTIVE';
        } elseif ($slaRatio >= 0.85) {
            $escalationLevel  = 'LEVEL_1_SUPERVISOR_WARNING';
            $targetRole       = 'SUPERVISOR_ULP';
            $escalationStatus = 'WARNING_DISPATCHED';
        } else {
            $escalationLevel  = 'LEVEL_0_ON_TRACK';
            $targetRole       = 'PETUGAS_LAPANGAN';
            $escalationStatus = 'NORMAL_MONITORING';
        }

        return [
            'status'                     => 'success',
            'case_id'                    => $caseId,
            'severity'                   => $severity,
            'sla_hours_threshold'        => $slaHrs,
            'elapsed_hours'              => $elapsedHours,
            'sla_ratio_pct'              => round($slaRatio * 100, 2) . '%',
            'escalation_level'           => $escalationLevel,
            'target_recipient_role'      => $targetRole,
            'escalation_status'          => $escalationStatus,
            'escalation_policy_version'  => 'ESCALATION_POLICY_v1.0',
            'certified_escalation'       => 'ESCALATION_EVALUATED_CLEANLY',
        ];
    }
}
