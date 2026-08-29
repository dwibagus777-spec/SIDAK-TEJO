<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\FeederHealthIntelligenceService;
use App\Services\ExecutiveAiAdvisoryService;
use App\Models\PenyulangModel;

/**
 * Executive Intelligence & Decision Fabric Controller (Phase CC-04 Contract v1.2)
 * Governed by Gates E0 - E9.
 */
class ExecutiveIntelligenceController extends ResourceController
{
    protected $format = 'json';
    protected FeederHealthIntelligenceService $fhiService;
    protected ExecutiveAiAdvisoryService $aiService;
    protected PenyulangModel $penyulangModel;

    public function __construct()
    {
        $this->fhiService     = new FeederHealthIntelligenceService();
        $this->aiService      = new ExecutiveAiAdvisoryService();
        $this->penyulangModel = new PenyulangModel();
    }

    /**
     * GET /executive-intelligence
     * Executive Decision Fabric Dashboard View
     */
    public function index($penyulangId = null)
    {
        $feeders = $this->penyulangModel->orderBy('nama_penyulang', 'ASC')->findAll();
        $selectedId = $penyulangId ? (int)$penyulangId : (int)($feeders[0]['id'] ?? 1);

        $fhiData = $this->fhiService->calculateFeederHealth($selectedId);
        $advisory = $this->aiService->generateExecutiveAdvisory($fhiData);

        return view('executive_intelligence/index', [
            'title'            => 'Executive Decision Fabric (CC-04)',
            'feeders'          => $feeders,
            'selectedFeederId' => $selectedId,
            'fhiData'          => $fhiData,
            'advisory'         => $advisory,
        ]);
    }

    /**
     * GET /api/executive-intelligence/feeder/(:num)
     */
    public function apiFeeder($penyulangId)
    {
        $data = $this->fhiService->calculateFeederHealth((int)$penyulangId);
        $advisory = $this->aiService->generateExecutiveAdvisory($data);
        $data['advisory'] = $advisory;

        return $this->respond($data);
    }

    /**
     * POST /api/executive-intelligence/approve-action
     * Manager Approval Gate (Gate E9-A: Human-in-the-Loop)
     */
    public function approveAction()
    {
        $logId = (int)$this->request->getPost('decision_log_id');
        $userId = (int)($this->request->getPost('user_id') ?? 1);
        $notes  = $this->request->getPost('notes') ?? 'Disetujui untuk dispatch operasional.';

        if (!$logId) {
            return $this->fail('Decision log ID required');
        }

        $success = $this->fhiService->approveDecision($logId, $userId, $notes);
        return $this->respond(['success' => $success, 'message' => 'Rekomendasi keputusan berhasil disetujui untuk dispatch.']);
    }

    /**
     * POST /api/executive-intelligence/verify-outcome
     * Closed-Loop Outcome Verification Gate (Gate E9)
     */
    public function verifyOutcome()
    {
        $logId = (int)$this->request->getPost('decision_log_id');
        $newFhi = (float)$this->request->getPost('verified_fhi');

        if (!$logId || !$newFhi) {
            return $this->fail('Decision log ID and verified FHI required');
        }

        $result = $this->fhiService->verifyDecisionOutcome($logId, $newFhi);
        return $this->respond($result);
    }
}
