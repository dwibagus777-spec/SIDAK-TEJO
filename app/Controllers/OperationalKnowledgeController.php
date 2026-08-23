<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\OperationalKnowledgeService;
use App\Services\PolicyGovernanceService;
use App\Services\DecisionOutcomeLearningService;
use CodeIgniter\HTTP\ResponseInterface;

class OperationalKnowledgeController extends BaseController
{
    protected OperationalKnowledgeService $knowledgeService;
    protected PolicyGovernanceService $policyService;
    protected DecisionOutcomeLearningService $outcomeService;

    public function __construct()
    {
        $this->knowledgeService = new OperationalKnowledgeService();
        $this->policyService    = new PolicyGovernanceService();
        $this->outcomeService   = new DecisionOutcomeLearningService();
    }

    /**
     * GET /knowledge/similar-cases/(:num)
     * Retrieve Top 3 Similar Historical Cases API (Phase 3G)
     */
    public function similarCases(int $assetId): ResponseInterface
    {
        $similar = $this->knowledgeService->findSimilarHistoricalCases($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $similar,
        ]);
    }

    /**
     * GET /knowledge/policy-status
     * Versioned Operational Policy Registry API (Phase 3G)
     */
    public function policyStatus(): ResponseInterface
    {
        $policies = $this->policyService->getActivePolicyConfigurations();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $policies,
        ]);
    }

    /**
     * GET /knowledge/decision-outcomes
     * Decision Outcome & Recommendation Analytics API (Phase 3G)
     */
    public function decisionOutcomes(): ResponseInterface
    {
        $outcomes = $this->outcomeService->analyzeDecisionOutcomes();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $outcomes,
        ]);
    }
}
