<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\WorkAcceptanceControlService;

/**
 * Work Acceptance, QA & Asset Health Recalibration Controller (B8.8 Web & API Surface)
 */
class WorkAcceptanceControlController extends ResourceController
{
    protected $format = 'json';
    protected WorkAcceptanceControlService $acceptanceService;

    public function __construct()
    {
        $this->acceptanceService = new WorkAcceptanceControlService();
    }

    /**
     * GET /work-acceptance/workspace
     */
    public function workspace()
    {
        $summary = $this->acceptanceService->getOperationalSummary();

        $data = [
            'title'   => 'B8.8 Work Acceptance, QA & Asset Health Recalibration Center',
            'summary' => $summary,
        ];

        return view('work_acceptance/workspace', $data);
    }

    /**
     * GET /api/work-acceptance/summary
     */
    public function apiSummary()
    {
        $summary = $this->acceptanceService->getOperationalSummary();
        return $this->respond($summary);
    }

    /**
     * POST /api/work-acceptance/initiate
     */
    public function apiInitiate()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $execId = (int)($json['execution_id'] ?? 0);
        $actor  = ['name' => session()->get('nama_lengkap') ?? 'INSPEKTUR_MUTU_JARINGAN'];

        $result = $this->acceptanceService->initiateAcceptanceReview($execId, $actor);

        if ($result['status'] === 'success') {
            return $this->respondCreated($result);
        }

        return $this->fail($result['message'] ?? 'Gagal inisiasi evaluasi penerimaan.', 400);
    }

    /**
     * POST /api/work-acceptance/evaluate
     */
    public function apiEvaluate()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $accId = (int)($json['acceptance_id'] ?? 0);
        $checklist4D = [
            'evidence_score'  => (float)($json['evidence_score'] ?? 100.0),
            'technical_score' => (float)($json['technical_score'] ?? 100.0),
            'material_score'  => (float)($json['material_score'] ?? 100.0),
            'asbuilt_score'   => (float)($json['asbuilt_score'] ?? 100.0),
        ];
        $actor = ['name' => session()->get('nama_lengkap') ?? 'INSPEKTUR_MUTU_JARINGAN'];

        $result = $this->acceptanceService->evaluateQualityAssurance($accId, $checklist4D, $actor);

        if ($result['status'] === 'success') {
            return $this->respond($result);
        }

        return $this->fail($result['message'] ?? 'Gagal evaluasi QA 4D.', 400);
    }

    /**
     * POST /api/work-acceptance/recalibrate-health
     */
    public function apiRecalibrateHealth()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $accId = (int)($json['acceptance_id'] ?? 0);
        $actor = ['name' => session()->get('nama_lengkap') ?? 'ENGINEER_RELIABILITY_AHI'];

        $result = $this->acceptanceService->recalibrateAssetHealth($accId, $actor);

        if ($result['status'] === 'success') {
            return $this->respond($result);
        }

        return $this->fail($result['message'] ?? 'Gagal rekalibrasi AHI.', 400);
    }

    /**
     * POST /api/work-acceptance/close
     */
    public function apiClose()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $accId = (int)($json['acceptance_id'] ?? 0);
        $closureRationale = (string)($json['closure_rationale'] ?? '');
        $managerSignoff   = (string)($json['manager_signoff'] ?? 'APPROVED');
        $actor = ['name' => session()->get('nama_lengkap') ?? 'MANAJER_ULP'];

        $result = $this->acceptanceService->closeExecutiveWork($accId, $closureRationale, $managerSignoff, $actor);

        if ($result['status'] === 'success') {
            return $this->respond($result);
        }

        return $this->fail($result['message'] ?? 'Gagal penutupan eksekutif pekerjaan.', 400);
    }
}
