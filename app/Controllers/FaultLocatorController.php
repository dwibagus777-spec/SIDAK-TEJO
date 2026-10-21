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
     * Render Fault Locator Command Center Workspace UI
     */
    public function workspace()
    {
        $summary = $this->service->getOperationalSummary();
        $ulps = $this->service->getUlps();
        $defaultUlpId = !empty($ulps) ? (int)$ulps[0]['id'] : null;

        $feeders = $this->service->getFeeders($defaultUlpId);
        $defaultFeederId = !empty($feeders) ? (int)$feeders[0]['id'] : 1;

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

        $data = [
            'title'              => 'Fault Locator Command Center Workspace (D3)',
            'summary'            => $summary,
            'ulps'               => $ulps,
            'feeders'            => $feeders,
            'protectionDevices'  => $protectionDevices,
            'conductorCatalog'   => FaultLocatorWorkspaceService::CONDUCTOR_CATALOG,
            'interruptions'      => $interruptions,
            'telemetry'          => $defaultTelemetry,
            'result'             => $initialResult
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
    public function feedersByUlp(int $ulpId): ResponseInterface
    {
        $feeders = $this->service->getFeeders($ulpId);
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $feeders
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
}
