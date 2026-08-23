<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\CapacityPlanningService;
use App\Services\PerformanceGuardrailService;
use App\Services\ReadModelAggregatorService;
use App\Services\AutoScalingPolicyService;
use App\Services\SystemStressAuditService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseCapacityController extends BaseController
{
    protected CapacityPlanningService $capacityService;
    protected PerformanceGuardrailService $guardrailService;
    protected ReadModelAggregatorService $readModelService;
    protected AutoScalingPolicyService $scalingService;
    protected SystemStressAuditService $stressService;

    public function __construct()
    {
        $this->capacityService  = new CapacityPlanningService();
        $this->guardrailService = new PerformanceGuardrailService();
        $this->readModelService = new ReadModelAggregatorService();
        $this->scalingService   = new AutoScalingPolicyService();
        $this->stressService    = new SystemStressAuditService();
    }

    /**
     * GET /capacity/performance-status
     * Enterprise Capacity, Performance & Scaling Workspace (Phase 6D)
     */
    public function index()
    {
        $capacity  = $this->capacityService->getCapacitySnapshot();
        $guardrail = $this->guardrailService->auditPerformanceGuardrails();
        $readModel = $this->readModelService->getAggregatedDashboardSnapshot(1);
        $policy    = $this->scalingService->evaluateScalingPolicy();

        return view('enterprise_capacity/index', [
            'title'     => 'SIDAK TEJO v3.0.0 — Enterprise Capacity, Performance & Scaling Control',
            'capacity'  => $capacity['capacity_metrics'] ?? [],
            'guardrail' => $guardrail['guardrail_audit'] ?? [],
            'readModel' => $readModel['read_model_snapshot'] ?? [],
            'policy'    => $policy['scaling_policy'] ?? [],
        ]);
    }

    /**
     * POST /capacity/guardrail-check
     * Audit Performance Guardrails API (Phase 6D)
     */
    public function guardrailCheck(): ResponseInterface
    {
        $result = $this->guardrailService->auditPerformanceGuardrails();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * POST /capacity/stress-audit
     * Run Safe Stress Simulation API (Phase 6D)
     */
    public function stressAudit(): ResponseInterface
    {
        $result = $this->stressService->runStressAuditSimulation();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
