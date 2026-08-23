<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class UnifiedOperationalWorkspaceService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Build 12-Layer Unified 360° Asset Action Workspace (Phase 4B)
     */
    public function getAssetActionWorkspace(int $assetId): array
    {
        $db = $this->db;

        $digitalTwin  = new OperationalDigitalTwinService($db);
        $simImpact    = new InterventionImpactSimulationService($db);
        $dataTrust    = new DataTrustQualityService($db);
        $knowledge    = new OperationalKnowledgeService($db);
        $policyServ   = new PolicyGovernanceService($db);
        $authorityServ= new OrganizationalAuthorityService($db);
        $orchestration= new ExecutionOrchestrationService($db);

        $twinState    = $digitalTwin->getDigitalTwinState($assetId);
        $simComparison= $simImpact->compareInterventionScenarios($assetId);
        $trustScore   = $dataTrust->getAssetDataTrustScore($assetId);
        $similarCases = $knowledge->findSimilarHistoricalCases($assetId);
        $activePolicy = $policyServ->getActivePolicyConfigurations();
        $orgMatrix    = $authorityServ->getOrganizationalStructureAndAuthorityMatrix();
        $workPackage  = $orchestration->generateWorkPackage($assetId);

        $actionWorkspace = [
            'asset_id'                   => $assetId,
            'nama_asset'                 => $twinState['digital_twin_model']['nama_asset'],
            'current_health_score'       => $twinState['digital_twin_model']['digital_twin_health_score'],
            'current_health_category'    => $twinState['digital_twin_model']['digital_twin_category'],
            'connected_load_kva'         => $twinState['digital_twin_model']['connected_load_kva'],
            'customer_count_impact'      => $twinState['digital_twin_model']['customer_count_impact'],
            'data_trust_index'           => $trustScore['data_quality_index'],
            'data_certification_status'  => $trustScore['data_certification_status'],
            'similar_historical_cases'   => $similarCases['similar_cases'],
            'active_resolution_policy'   => $activePolicy['policy_registry']['SLA_RESOLUTION_POLICY']['active_version'],
            'digital_twin_status'        => $twinState['digital_twin_model']['digital_twin_status'],
            'scenario_simulation_matrix' => $simComparison['comparative_matrix'],
            'recommended_intervention'   => $simComparison['optimal_recommended_option'],
            'approval_authority_required'=> 'SUPERVISOR_ULP',
            'escalation_target_role'     => 'SUPERVISOR_ULP',
            'generated_work_package'     => $workPackage['work_package'] ?? [],
            'workspace_status'           => 'ACTION_WORKSPACE_SYNCHRONIZED',
        ];

        return [
            'status'                     => 'success',
            'action_workspace'           => $actionWorkspace,
            'workspace_engine_version'   => 'UNIFIED_ACTION_WORKSPACE_v1.0',
            'certified_workspace_status' => 'ACTION_WORKSPACE_ACTIVE',
        ];
    }
}
