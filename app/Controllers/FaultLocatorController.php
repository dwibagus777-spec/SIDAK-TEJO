<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\FaultLocatorWorkspaceService;
use CodeIgniter\HTTP\ResponseInterface;

class FaultLocatorController extends BaseController
{
    protected $service;

    public function __construct()
    {
        $this->service = new FaultLocatorWorkspaceService();
    }

    /**
     * Render Fault Locator Command Center Workspace UI (D3-G01 & D3-G02 & D3-G03)
     */
    public function workspace()
    {
        $summary = $this->service->getOperationalSummary();
        $feeders = $this->service->getFeeders();
        $interruptions = $this->service->getRecentInterruptions(20);

        $defaultFeederId = !empty($feeders) ? $feeders[0]['id'] : '1';

        $defaultTelemetry = [
            'feeder_id'        => $defaultFeederId,
            'timestamp'        => date('Y-m-d H:i:s'),
            'phase'            => 'R-N',
            'fault_current_a'  => 1450.0,
            'relay_distance_m' => 1250.0,
            'relay_type'       => 'OCR_GFR',
            'device_asset_id'  => 'GI_OUTGOING'
        ];

        $initialResult = $this->service->locateFault($defaultTelemetry);

        $data = [
            'title'         => 'Fault Locator Command Center Workspace (D3)',
            'summary'       => $summary,
            'feeders'       => $feeders,
            'interruptions' => $interruptions,
            'telemetry'     => $defaultTelemetry,
            'result'        => $initialResult
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
     * API: Execute FLI-1.0.0 Fault Location Calculation
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
}
