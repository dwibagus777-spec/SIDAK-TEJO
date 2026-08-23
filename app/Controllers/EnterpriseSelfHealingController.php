<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\SelfHealingAnomalyDetectionService;
use App\Services\AutoRecoveryOrchestrationService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseSelfHealingController extends BaseController
{
    protected SelfHealingAnomalyDetectionService $anomalyService;
    protected AutoRecoveryOrchestrationService $recoveryService;

    public function __construct()
    {
        $this->anomalyService  = new SelfHealingAnomalyDetectionService();
        $this->recoveryService = new AutoRecoveryOrchestrationService();
    }

    /**
     * GET /self-healing/control-center
     * Enterprise Self-Healing Control View (Phase 7I)
     */
    public function index()
    {
        $anomalyRes  = $this->anomalyService->detectTelemetryAnomalies(1);
        $recoveryRes = $this->recoveryService->proposeSelfHealingRecovery(1);

        return view('enterprise_self_healing/index', [
            'title'            => 'SIDAK TEJO v3.0.0 — Enterprise AI Predictive Anomaly & Governed Self-Healing Control Center',
            'anomalyAudit'     => $anomalyRes['anomaly_audit'] ?? [],
            'recoveryProposal' => $recoveryRes['recovery_proposal'] ?? [],
        ]);
    }

    /**
     * GET /self-healing/anomaly-snapshot
     * Telemetry Anomaly Audit Snapshot API (Phase 7I)
     */
    public function anomalySnapshot(): ResponseInterface
    {
        $result = $this->anomalyService->detectTelemetryAnomalies(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * POST /self-healing/propose-recovery
     * Propose Self-Healing Recovery Proposal API (Phase 7I)
     */
    public function proposeRecovery(): ResponseInterface
    {
        $result = $this->recoveryService->proposeSelfHealingRecovery(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
