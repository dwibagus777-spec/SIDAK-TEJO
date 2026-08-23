<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class OperationalDecisionInboxService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Unified Decision Queue for Manager, Supervisor, and Field Operator Decision Inbox (Phase 3D)
     */
    public function getUnifiedDecisionQueue(): array
    {
        $db = $this->db;

        // 1. Fetch Active Action Cases
        $activeCases = $db->table('observation_action_cases c')
            ->select('c.*, a.nama_asset, a.kode_asset, a.health_score')
            ->join('assets a', 'a.id = c.asset_id', 'inner')
            ->whereNotIn('c.status', ['VERIFIED', 'SUPERSEDED'])
            ->orderBy('c.priority', 'ASC')
            ->get()
            ->getResultArray();

        $actionRequiredNow  = [];
        $decisionRequired   = [];
        $workInProgress     = [];

        $prescriptiveEngine = new PrescriptiveDecisionService($db);
        $orchestratorEngine = new ExecutionOrchestrationService($db);

        foreach ($activeCases as $case) {
            $pCode    = 'P' . ($case['priority'] ?? 5);
            $slaHours = ($pCode === 'P5') ? null : (int)OperationalIntelligenceService::resolveRiskPriority($case['severity_at_open'])['resolution_sla_hrs'];
            $slaInfo  = OperationalIntelligenceService::calculateSlaStatus($case['opened_at'], $slaHours);

            $item = [
                'case_id'          => (int)$case['id'],
                'asset_id'         => (int)$case['asset_id'],
                'nama_asset'       => $case['nama_asset'],
                'kode_asset'       => $case['kode_asset'],
                'severity'         => $case['severity_at_open'],
                'priority_code'    => $pCode,
                'status'           => $case['status'],
                'sla_status'       => $slaInfo['sla_status'],
                'escalation_level' => $slaInfo['escalation_level'],
            ];

            if ($case['status'] === 'IN_PROGRESS') {
                $wp = $orchestratorEngine->generateWorkPackage((int)$case['asset_id']);
                $item['work_package'] = $wp['work_package'] ?? [];
                $workInProgress[]     = $item;
            } elseif (in_array($case['severity_at_open'], ['EMERGENCY', 'CRITICAL']) || in_array($slaInfo['sla_status'], ['SLA_WARNING', 'SLA_BREACH'])) {
                $actionRequiredNow[]  = $item;
            } else {
                $rec = $prescriptiveEngine->generatePrescriptiveRecommendation((int)$case['asset_id']);
                $item['prescriptive_recommendation'] = $rec['recommendation'] ?? [];
                $decisionRequired[]  = $item;
            }
        }

        // 2. Fetch Recently Verified Recoveries
        $verifiedRecoveries = $db->table('observation_action_cases c')
            ->select('c.id as case_id, c.asset_id, a.nama_asset, c.resolved_at, c.verified_at')
            ->join('assets a', 'a.id = c.asset_id', 'inner')
            ->where('c.status', 'VERIFIED')
            ->orderBy('c.verified_at', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();

        return [
            'status'                => 'success',
            'inbox_timestamp'       => date('Y-m-d H:i:s'),
            'summary_counts'        => [
                'action_required_now_cnt' => count($actionRequiredNow),
                'decision_required_cnt'    => count($decisionRequired),
                'work_in_progress_cnt'     => count($workInProgress),
                'verified_recovery_cnt'    => count($verifiedRecoveries),
            ],
            'action_required_now'   => $actionRequiredNow,
            'decision_required'    => $decisionRequired,
            'work_in_progress'      => $workInProgress,
            'verified_recovery'     => $verifiedRecoveries,
            'decision_queue_status' => 'DECISION_QUEUE_SYNCHRONIZED',
        ];
    }
}
