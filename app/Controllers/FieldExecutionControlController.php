<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\FieldExecutionControlService;

/**
 * Field Execution Control & Outage Realization Controller (B8.7 Web & API Surface)
 */
class FieldExecutionControlController extends ResourceController
{
    protected $format = 'json';
    protected FieldExecutionControlService $executionService;

    public function __construct()
    {
        $this->executionService = new FieldExecutionControlService();
    }

    /**
     * GET /field-execution/workspace
     */
    public function workspace()
    {
        $summary = $this->executionService->getOperationalSummary();

        $data = [
            'title'   => 'B8.7 Field Execution & Outage Realization Control Center',
            'summary' => $summary,
        ];

        return view('field_execution/workspace', $data);
    }

    /**
     * GET /api/field-execution/summary
     */
    public function apiSummary()
    {
        $summary = $this->executionService->getOperationalSummary();
        return $this->respond($summary);
    }

    /**
     * POST /api/field-execution/dispatch
     */
    public function apiDispatch()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $actor = [
            'name' => session()->get('nama_lengkap') ?? 'SUPERVISOR_TEKNIK_ULP',
        ];

        $result = $this->executionService->dispatchFieldWork($json, $actor);

        if ($result['status'] === 'success') {
            return $this->respondCreated($result);
        }

        return $this->fail($result['message'] ?? 'Gagal dispatch pekerjaan.', 400);
    }

    /**
     * POST /api/field-execution/start
     */
    public function apiStart()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $execId = (int)($json['execution_id'] ?? 0);
        $beforeEvidence = [
            'photo_uri' => $json['before_photo_uri'] ?? '',
            'notes'     => $json['before_photo_notes'] ?? '',
        ];
        $rationale = (string)($json['start_rationale'] ?? '');
        $actor = ['name' => session()->get('nama_lengkap') ?? 'PENGAWAS_LAPANGAN'];

        $result = $this->executionService->startFieldWork($execId, $beforeEvidence, $rationale, $actor);

        if ($result['status'] === 'success') {
            return $this->respond($result);
        }

        return $this->fail($result['message'] ?? 'Gagal memulai pekerjaan fisik.', 400);
    }

    /**
     * POST /api/field-execution/realize
     */
    public function apiRealize()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $execId = (int)($json['execution_id'] ?? 0);
        $progressPct = (float)($json['progress_percentage'] ?? 50.0);
        $outageStart = $json['outage_start_at'] ?? null;
        $outageEnd   = $json['outage_end_at'] ?? null;
        $plannedMins = (int)($json['planned_outage_duration_mins'] ?? 240);
        $description = (string)($json['description'] ?? '');
        $rationale   = (string)($json['rationale'] ?? '');
        $actor = ['name' => session()->get('nama_lengkap') ?? 'PENGAWAS_LAPANGAN'];

        $result = $this->executionService->logProgressAndOutage($execId, $progressPct, $outageStart, $outageEnd, $plannedMins, $description, $rationale, $actor);

        if ($result['status'] === 'success') {
            return $this->respond($result);
        }

        return $this->fail($result['message'] ?? 'Gagal memuat realisasi pemadaman.', 400);
    }

    /**
     * POST /api/field-execution/reconcile-materials
     */
    public function apiReconcileMaterials()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $execId = (int)($json['execution_id'] ?? 0);
        $actualMaterials = (array)($json['actual_materials'] ?? []);
        $rationale = (string)($json['rationale'] ?? 'Rekonsiliasi material lapangan');
        $actor = ['name' => session()->get('nama_lengkap') ?? 'PETUGAS_LOGISTIK_LAPANGAN'];

        $result = $this->executionService->reconcileMaterialConsumption($execId, $actualMaterials, $rationale, $actor);

        if ($result['status'] === 'success') {
            return $this->respond($result);
        }

        return $this->fail($result['message'] ?? 'Gagal rekonsiliasi material.', 400);
    }

    /**
     * POST /api/field-execution/safety-hold
     */
    public function apiSafetyHold()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $execId = (int)($json['execution_id'] ?? 0);
        $action = (string)($json['action'] ?? 'DECLARE');
        $reason = (string)($json['reason_or_rationale'] ?? '');
        $actor = ['name' => session()->get('nama_lengkap') ?? 'PENGAWAS_K3_LAPANGAN'];

        $result = $this->executionService->manageSafetyHold($execId, $action, $reason, $actor);

        if ($result['status'] === 'success') {
            return $this->respond($result);
        }

        return $this->fail($result['message'] ?? 'Gagal memproses Safety Hold.', 400);
    }

    /**
     * POST /api/field-execution/verify
     */
    public function apiVerify()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $execId = (int)($json['execution_id'] ?? 0);
        $afterEvidence = [
            'photo_uri' => $json['after_photo_uri'] ?? '',
            'notes'     => $json['after_photo_notes'] ?? '',
        ];
        $declarationRationale = (string)($json['declaration_rationale'] ?? '');
        $supervisorSignoff   = $json['supervisor_signoff'] ?? null;
        $actor = ['name' => session()->get('nama_lengkap') ?? 'SUPERVISOR_TEKNIK_ULP'];

        $result = $this->executionService->completeAndVerify($execId, $afterEvidence, $declarationRationale, $supervisorSignoff, $actor);

        if ($result['status'] === 'success') {
            return $this->respond($result);
        }

        return $this->fail($result['message'] ?? 'Gagal verifikasi penutupan eksekusi.', 400);
    }
}
