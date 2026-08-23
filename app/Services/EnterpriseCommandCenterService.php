<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class EnterpriseCommandCenterService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Build Unified Enterprise Operational Workspace Aggregation Model (Phase 4A)
     */
    public function getUnifiedEnterpriseOperationalWorkspace(int $assetId = 1): array
    {
        $db = $this->db;

        $controlPlane = new OperationalControlPlaneService($db);
        $decisionInbox= new OperationalDecisionInboxService($db);
        $digitalTwin  = new OperationalDigitalTwinService($db);
        $simImpact    = new InterventionImpactSimulationService($db);
        $dataTrust    = new DataTrustQualityService($db);
        $knowledge    = new OperationalKnowledgeService($db);
        $authority    = new OrganizationalAuthorityService($db);

        $situationModel = $controlPlane->getOperationalSituationModel();
        $inboxQueue     = $decisionInbox->getUnifiedDecisionQueue();
        $twinState      = $digitalTwin->getDigitalTwinState($assetId);
        $simComparison  = $simImpact->compareInterventionScenarios($assetId);
        $trustScore     = $dataTrust->getAssetDataTrustScore($assetId);
        $similarCases   = $knowledge->findSimilarHistoricalCases($assetId);
        $orgStructure   = $authority->getOrganizationalStructureAndAuthorityMatrix();

        return [
            'status'                       => 'success',
            'enterprise_unit_hierarchy'    => $orgStructure['organization_hierarchy'],
            'situation_overview'           => 'Active Cases: ' . ($situationModel['system_health_metrics']['total_active_cases'] ?? 0) . ', Resilience: ' . ($situationModel['operational_resilience'] ?? 'RESILIENT'),
            'decision_inbox_counts'        => [
                'action_required_now' => $inboxQueue['summary_counts']['action_required_now_cnt'] ?? 0,
                'decision_required'   => $inboxQueue['summary_counts']['decision_required_cnt'] ?? 0,
                'work_in_progress'    => $inboxQueue['summary_counts']['work_in_progress_cnt'] ?? 0,
                'verified_recovery'   => $inboxQueue['summary_counts']['verified_recovery_cnt'] ?? 0,
            ],
            'digital_twin_model'           => $twinState['digital_twin_model'],
            'scenario_simulation_matrix'   => $simComparison['comparative_matrix'],
            'recommended_intervention'     => $simComparison['optimal_recommended_option'],
            'data_trust_metrics'           => [
                'quality_index' => $trustScore['data_quality_index'],
                'freshness_hrs' => $trustScore['data_freshness_hrs'],
                'certification' => $trustScore['data_certification_status'],
            ],
            'similar_cases_count'          => $similarCases['similar_cases_found_cnt'],
            'command_center_version'       => 'ENTERPRISE_COMMAND_CENTER_v1.0',
            'certified_enterprise_status'  => 'ENTERPRISE_WORKSPACE_SYNCHRONIZED',
        ];
    }
}
