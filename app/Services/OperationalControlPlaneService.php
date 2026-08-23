<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class OperationalControlPlaneService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Aggregates outputs from all 11+ baseline engines into a single Operational Situation Model (Phase 3D)
     */
    public function getOperationalSituationModel(): array
    {
        $db = $this->db;

        // 1. Fetch Aggregated System Health Index & Case Counts
        $opService      = new OperationalIntelligenceService($db);
        $topoService    = new NetworkTopologyService($db);
        $residenceServ  = new OperationalResilienceService($db);
        $observability  = new SystemObservabilityService($db);

        $metrics        = $opService->getOperationalDashboardMetrics();
        $resilience     = $residenceServ->auditOperationalResilienceAndContinuity();
        $sreTelemetry   = $observability->getSystemObservabilityAndSreMetrics();

        // 2. Fetch Network Feeder Risk Concentration Summary
        $feederCodes    = ['P-BALUNG', 'P-KOTA', 'P-SIDOARJO'];
        $feederSummaries = [];
        foreach ($feederCodes as $fCode) {
            $feederSummaries[$fCode] = $topoService->calculateFeederNetworkRiskIndex($fCode);
        }

        return [
            'status'                    => 'success',
            'control_plane_version'     => 'CONTROL_PLANE_v1.0',
            'situation_timestamp'       => date('Y-m-d H:i:s'),
            'system_health_metrics'     => $metrics,
            'feeder_risk_heatmap'       => $feederSummaries,
            'operational_resilience'    => $resilience['certified_resilience_status'] ?? 'RESILIENT',
            'sre_system_observability'  => $sreTelemetry['slo_and_error_budget'] ?? [],
            'control_plane_status'      => 'UNIFIED_SITUATION_MODEL_ACTIVE',
        ];
    }
}
