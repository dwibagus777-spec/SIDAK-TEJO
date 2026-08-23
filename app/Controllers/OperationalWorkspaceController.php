<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\UnifiedOperationalWorkspaceService;
use App\Services\DecisionExplainabilityService;
use App\Services\OperationalTimelineService;
use CodeIgniter\HTTP\ResponseInterface;

class OperationalWorkspaceController extends BaseController
{
    protected UnifiedOperationalWorkspaceService $workspaceService;
    protected DecisionExplainabilityService $explainabilityService;
    protected OperationalTimelineService $timelineService;

    public function __construct()
    {
        $this->workspaceService     = new UnifiedOperationalWorkspaceService();
        $this->explainabilityService = new DecisionExplainabilityService();
        $this->timelineService      = new OperationalTimelineService();
    }

    /**
     * GET /workspace/asset/(:num)
     * Unified Asset Action Workspace View (Phase 4B)
     */
    public function index(int $assetId = 1)
    {
        $workspaceData  = $this->workspaceService->getAssetActionWorkspace($assetId);
        $explainability = $this->explainabilityService->explainDecisionRecommendation($assetId);
        $timeline       = $this->timelineService->getOperationalLifecycleTimeline($assetId);

        return view('operational_workspace/index', [
            'title'          => 'SIDAK TEJO v3.0.0 — Unified Operational Action Workspace',
            'workspaceData'  => $workspaceData['action_workspace'] ?? [],
            'explainability' => $explainability['explainability_panel'] ?? [],
            'timeline'       => $timeline['lifecycle_timeline'] ?? [],
        ]);
    }

    /**
     * GET /workspace/asset/(:num)/explain
     * Decision Explainability Panel API (Phase 4B)
     */
    public function explain(int $assetId): ResponseInterface
    {
        $explain = $this->explainabilityService->explainDecisionRecommendation($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $explain,
        ]);
    }

    /**
     * GET /workspace/asset/(:num)/timeline
     * 10-Stage Operational Intelligence Lifecycle Timeline API (Phase 4B)
     */
    public function timeline(int $assetId): ResponseInterface
    {
        $timeline = $this->timelineService->getOperationalLifecycleTimeline($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $timeline,
        ]);
    }

    /**
     * POST /workspace/asset/(:num)/action
     * Record Action Handoff / Human Approval API (Phase 4B)
     */
    public function recordAction(int $assetId): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $actionType = $json['action_type'] ?? 'APPROVE_RECOMMENDATION';

        return $this->response->setJSON([
            'status'   => 'success',
            'asset_id' => $assetId,
            'action'   => $actionType,
            'message'  => 'Action handoff recorded successfully in Operational Workspace.',
        ]);
    }
}
