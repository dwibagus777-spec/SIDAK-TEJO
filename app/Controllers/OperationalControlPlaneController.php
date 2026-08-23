<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\OperationalControlPlaneService;
use App\Services\OperationalDecisionInboxService;
use App\Services\HumanDecisionGovernanceService;
use CodeIgniter\HTTP\ResponseInterface;

class OperationalControlPlaneController extends BaseController
{
    protected OperationalControlPlaneService $controlPlaneService;
    protected OperationalDecisionInboxService $decisionInboxService;
    protected HumanDecisionGovernanceService $humanGovernanceService;

    public function __construct()
    {
        $this->controlPlaneService    = new OperationalControlPlaneService();
        $this->decisionInboxService   = new OperationalDecisionInboxService();
        $this->humanGovernanceService = new HumanDecisionGovernanceService();
    }

    /**
     * GET /control-plane/situation-model
     * Unified Operational Situation Model API (Phase 3D)
     */
    public function situationModel(): ResponseInterface
    {
        $model = $this->controlPlaneService->getOperationalSituationModel();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $model,
        ]);
    }

    /**
     * GET /control-plane/decision-inbox
     * Unified Decision Inbox Queue API (Phase 3D)
     */
    public function decisionInbox(): ResponseInterface
    {
        $inbox = $this->decisionInboxService->getUnifiedDecisionQueue();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $inbox,
        ]);
    }

    /**
     * POST /control-plane/record-decision
     * Record Human-in-the-Loop Approval & Override API (Phase 3D)
     */
    public function recordDecision(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];

        $assetId         = (int)($json['asset_id'] ?? 1);
        $actionType       = $json['action_type'] ?? 'PRESCRIPTIVE_APPROVAL';
        $decisionOutcome  = $json['decision_outcome'] ?? 'APPROVED';
        $overrideReason   = $json['override_reason'] ?? null;
        $userId          = (int)($json['user_id'] ?? 1);

        $result = $this->humanGovernanceService->recordHumanDecision(
            $assetId,
            $actionType,
            $decisionOutcome,
            $overrideReason,
            $userId
        );

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
