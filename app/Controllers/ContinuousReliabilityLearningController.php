<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\ContinuousReliabilityLearningService;

/**
 * Continuous Reliability Intelligence & Closed-Loop Learning Controller (B8.9 Web & API Surface)
 */
class ContinuousReliabilityLearningController extends ResourceController
{
    protected $format = 'json';
    protected ContinuousReliabilityLearningService $learningService;

    public function __construct()
    {
        $this->learningService = new ContinuousReliabilityLearningService();
    }

    /**
     * GET /reliability-learning/workspace
     */
    public function workspace()
    {
        $summary = $this->learningService->getOperationalSummary();
        $advisories = $this->learningService->generateReliabilityAdvisories('KEDUNGPRING');

        $data = [
            'title'      => 'B8.9 Continuous Reliability Intelligence & Closed-Loop Learning Center',
            'summary'    => $summary,
            'advisories' => $advisories['advisories'] ?? [],
        ];

        return view('reliability_learning/workspace', $data);
    }

    /**
     * GET /api/reliability-learning/summary
     */
    public function apiSummary()
    {
        $summary = $this->learningService->getOperationalSummary();
        return $this->respond($summary);
    }

    /**
     * POST /api/reliability-learning/recalibrate-lineage
     */
    public function apiRecalibrateLineage()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $actor = ['name' => session()->get('nama_lengkap') ?? 'ENGINEER_RELIABILITY_AHI'];

        $result = $this->learningService->record7StageRecalibrationLineage($json, $actor);

        if ($result['status'] === 'success') {
            return $this->respondCreated($result);
        }

        return $this->fail($result['message'] ?? 'Gagal mencatat silsilah rekalibrasi AHI 7-Stage.', 400);
    }

    /**
     * GET /api/reliability-learning/effectiveness
     */
    public function apiEffectiveness()
    {
        $execId = (int)($this->request->getGet('execution_id') ?? 1);
        $accId  = (int)($this->request->getGet('acceptance_id') ?? 1);

        $result = $this->learningService->calculateJobEffectiveness($execId, $accId);
        return $this->respond($result);
    }

    /**
     * GET /api/reliability-learning/advisory
     */
    public function apiAdvisory()
    {
        $feederName = (string)($this->request->getGet('feeder_name') ?? 'KEDUNGPRING');
        $result = $this->learningService->generateReliabilityAdvisories($feederName);
        return $this->respond($result);
    }
}
