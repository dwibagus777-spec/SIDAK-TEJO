<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\DataTrustQualityService;
use App\Services\DataAnomalyDetectionService;
use App\Services\ConfidencePropagationService;
use CodeIgniter\HTTP\ResponseInterface;

class DataTrustController extends BaseController
{
    protected DataTrustQualityService $trustService;
    protected DataAnomalyDetectionService $anomalyService;
    protected ConfidencePropagationService $confidenceService;

    public function __construct()
    {
        $this->trustService      = new DataTrustQualityService();
        $this->anomalyService    = new DataAnomalyDetectionService();
        $this->confidenceService = new ConfidencePropagationService();
    }

    /**
     * GET /data-trust/quality-score/(:num)
     * Data Quality Index & Lineage Certification API (Phase 3I)
     */
    public function qualityScore(int $assetId): ResponseInterface
    {
        $score = $this->trustService->getAssetDataTrustScore($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $score,
        ]);
    }

    /**
     * GET /data-trust/anomaly-audit/(:num)
     * Data Anomaly Audit API (Phase 3I)
     */
    public function anomalyAudit(int $assetId): ResponseInterface
    {
        $audit = $this->anomalyService->auditDataAnomalies($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $audit,
        ]);
    }

    /**
     * GET /data-trust/confidence-tree/(:num)
     * Confidence Propagation Tree API (Phase 3I)
     */
    public function confidenceTree(int $assetId): ResponseInterface
    {
        $tree = $this->confidenceService->propagateConfidenceMetrics($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $tree,
        ]);
    }
}
