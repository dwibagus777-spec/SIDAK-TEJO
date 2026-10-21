<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\FaultLocatorWorkspaceService;
use App\Services\FaultFeedbackCalibrationService;
use App\Services\FeederNetworkReadinessService;
use CodeIgniter\HTTP\ResponseInterface;

class FaultLocatorController extends BaseController
{
    protected $service;
    protected $calibrationService;
    protected $readinessService;

    public function __construct()
    {
        $this->service = new FaultLocatorWorkspaceService();
        $this->calibrationService = new FaultFeedbackCalibrationService();
        $this->readinessService = new FeederNetworkReadinessService();
    }

    /**
     * Render Fault Locator Command Center Workspace UI
     */
    public function workspace()
    {
        $summary = $this->service->getOperationalSummary();
        $ulps = $this->service->getUlps();

        $requestedUlp = $this->request->getGet('ulp_id');
        $defaultUlpId = null;
        if ($requestedUlp !== null && $requestedUlp !== '') {
            $resolved = $this->service->resolveUlpId($requestedUlp);
            if ($resolved !== false) {
                $defaultUlpId = $resolved;
            }
        }
        if ($defaultUlpId === null) {
            $defaultUlpId = !empty($ulps) ? (int)$ulps[0]['id'] : null;
        }

        $feeders = $this->service->getFeeders($defaultUlpId);

        $requestedFeeder = $this->request->getGet('feeder_id');
        $defaultFeederId = null;
        if ($requestedFeeder !== null && $requestedFeeder !== '') {
            foreach ($feeders as $f) {
                if ($f['id'] == $requestedFeeder) {
                    $defaultFeederId = (int)$f['id'];
                    break;
                }
            }
        }
        if ($defaultFeederId === null) {
            $defaultFeederId = !empty($feeders) ? (int)$feeders[0]['id'] : 1;
        }

        $protectionDevices = $this->service->getProtectionDevices($defaultFeederId);
        $interruptions = $this->service->getRecentInterruptions(20);

        $defaultTelemetry = [
            'ulp_id'               => $defaultUlpId,
            'feeder_id'            => $defaultFeederId,
            'source_type'          => 'GI',
            'protection_device_id' => !empty($protectionDevices) ? $protectionDevices[0]['id'] : null,
            'fault_time'           => date('Y-m-d H:i:s'),
            'current_r'            => 1450.0,
            'current_s'            => 1420.0,
            'current_t'            => 1440.0,
            'current_n'            => 15.0,
            'relay_distance_m'     => null,
            'relay_type'           => 'OCR_GFR',
            'conductor_type'       => 'AAAC 150 mm²',
        ];

        $initialResult = $this->service->locateFault($defaultTelemetry);
        $caseHistory = $this->service->getCaseHistory(null, 15);
        $calibrationSummary = $this->calibrationService->getFeedbackDatasetSummary();
        $readinessSummary = $this->readinessService->auditAllFeeders($defaultUlpId);

        $data = [
            'title'              => 'Fault Locator Command Center Workspace (D3)',
            'summary'            => $summary,
            'ulps'               => $ulps,
            'feeders'            => $feeders,
            'protectionDevices'  => $protectionDevices,
            'conductorCatalog'   => FaultLocatorWorkspaceService::CONDUCTOR_CATALOG,
            'interruptions'      => $interruptions,
            'telemetry'          => $defaultTelemetry,
            'result'             => $initialResult,
            'caseHistory'        => $caseHistory,
            'calibrationSummary' => $calibrationSummary,
            'readinessSummary'   => $readinessSummary,
        ];

        return view('fault_locator/workspace', $data);
    }

    /**
     * API: Get Summary & Baseline Status
     */
    public function summary(): ResponseInterface
    {
        $summary = $this->service->getOperationalSummary();
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $summary
        ]);
    }

    /**
     * API: Cascading Feeders by ULP
     */
    public function feedersByUlp($ulpId = null): ResponseInterface
    {
        if ($ulpId === null || $ulpId === '') {
            $ulpId = $this->request->getGet('ulp_id');
        }

        if ($ulpId === null || $ulpId === '') {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Parameter ULP wajib diisi.',
                'data'    => []
            ]);
        }

        $resolvedId = $this->service->resolveUlpId($ulpId);
        if ($resolvedId === false) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'ULP tidak ditemukan.',
                'data'    => []
            ]);
        }

        $feeders = $this->service->getFeeders($resolvedId);

        return $this->response->setJSON([
            'status'  => 'success',
            'ulp_id'  => $resolvedId,
            'count'   => count($feeders),
            'data'    => $feeders,
            'message' => empty($feeders) ? 'Tidak ada penyulang aktif untuk ULP yang dipilih.' : null,
        ]);
    }

    /**
     * API: Cascading Protection Devices by Feeder
     */
    public function protectionDevices(int $penyulangId): ResponseInterface
    {
        $devices = $this->service->getProtectionDevices($penyulangId);
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $devices
        ]);
    }

    /**
     * API: Execute Fault Location Calculation
     */
    public function locateFault(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();

        if (empty($json['feeder_id'])) {
            $json['feeder_id'] = '1';
        }

        $result = $this->service->locateFault($json);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result
        ]);
    }

    /**
     * API: Create B6 Field Dispatch Package
     */
    public function dispatch(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $caseUuid = $json['case_uuid'] ?? 'FLI-CASE-DEFAULT';

        $dispatch = $this->service->createFieldDispatchPackage($caseUuid);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $dispatch
        ]);
    }

    /**
     * API: Get Forensic Bundle
     */
    public function forensicBundle(string $uuid): ResponseInterface
    {
        $bundle = $this->service->getForensicBundle($uuid);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $bundle
        ]);
    }

    /**
     * API: Save Fault Analysis Case (Track A)
     */
    public function saveCase(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        if (empty($json)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Data hasil analisis gangguan wajib dikirim.'
            ]);
        }

        $user = session()->get('user') ?? ['username' => 'OPERATOR'];
        $result = $this->service->saveFaultCase($json, $user);

        return $this->response->setJSON($result);
    }

    /**
     * API: Record Field Inspection Outcome (Track A & B)
     * Positive: CONFIRMED (found_asset_id, component_damaged, etc.)
     * Negative: NOT_FOUND (inspected_area_notes, not_found_reason)
     * Ambiguous: AMBIGUOUS
     */
    public function recordOutcome(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $caseId = !empty($json['case_id']) ? (int)$json['case_id'] : 0;

        if ($caseId <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'ID kasus gangguan (case_id) tidak valid.'
            ]);
        }

        $user = session()->get('user') ?? ['username' => 'PETUGAS_PATROLI'];
        $result = $this->service->recordFieldOutcome($caseId, $json, $user);

        return $this->response->setJSON($result);
    }

    /**
     * API: Get Historical Cases List
     */
    public function caseHistory(): ResponseInterface
    {
        $penyulangId = $this->request->getGet('penyulang_id');
        $limit = (int)($this->request->getGet('limit') ?? 25);
        $cases = $this->service->getCaseHistory($penyulangId ? (int)$penyulangId : null, $limit);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $cases
        ]);
    }

    /**
     * API: Get Detailed Case Information
     */
    public function caseDetails(int $id): ResponseInterface
    {
        $details = $this->service->getCaseDetails($id);
        if (!$details) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Kasus gangguan tidak ditemukan.'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $details
        ]);
    }

    /**
     * API: Controlled AI Feedback Calibration Status (Track B)
     */
    public function calibrationStatus(): ResponseInterface
    {
        $summary = $this->calibrationService->getFeedbackDatasetSummary();
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $summary
        ]);
    }

    /**
     * API: Feeder Readiness and Transline Integrity Audit (Track C)
     */
    public function feederReadiness(): ResponseInterface
    {
        $ulpId = $this->request->getGet('ulp_id');
        $report = $this->readinessService->auditAllFeeders($ulpId ? (int)$ulpId : null);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $report
        ]);
    }

    /**
     * API: Automatic Feeder Network Context & Conductor Resolution
     */
    public function feederNetworkContext(int $penyulangId): ResponseInterface
    {
        $context = $this->service->getFeederNetworkContext($penyulangId);
        $status = ($context['status'] ?? '') === 'error' ? 404 : 200;
        return $this->response->setStatusCode($status)->setJSON($context);
    }

    /**
     * API: Archive Prototype Dummy Test Cases
     */
    public function archiveProtoCases(): ResponseInterface
    {
        $isExecute = (bool)($this->request->getGetPost('execute') ?? false);
        $result = $this->service->archiveProtoTestCases($isExecute);
        return $this->response->setJSON($result);
    }
}
