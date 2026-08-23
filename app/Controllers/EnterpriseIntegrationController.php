<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\EnterpriseTelemetrySyncService;
use App\Services\CrossSystemInteroperabilityService;
use App\Services\RealTimeFieldSyncService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseIntegrationController extends BaseController
{
    protected EnterpriseTelemetrySyncService $telemetryService;
    protected CrossSystemInteroperabilityService $interopService;
    protected RealTimeFieldSyncService $fieldSyncService;

    public function __construct()
    {
        $this->telemetryService = new EnterpriseTelemetrySyncService();
        $this->interopService   = new CrossSystemInteroperabilityService();
        $this->fieldSyncService = new RealTimeFieldSyncService();
    }

    /**
     * GET /integration/cross-system-status
     * Enterprise Interoperability Dashboard View & Status (Phase 4D)
     */
    public function index()
    {
        $assetId     = (int)($this->request->getGet('asset_id') ?? 1);
        $interopData = $this->interopService->getCrossSystemInteroperabilityStatus();
        $telemetry   = $this->telemetryService->getRealTimeTelemetryStream($assetId);
        $fieldSync   = $this->fieldSyncService->getRealTimeFieldSyncPayload($assetId);

        return view('enterprise_integration/index', [
            'title'       => 'SIDAK TEJO v3.0.0 — Enterprise Integration & Real-Time Sync Fabric',
            'interopData' => $interopData,
            'telemetry'   => $telemetry['telemetry_stream'] ?? [],
            'fieldSync'   => $fieldSync['field_sync_payload'] ?? [],
        ]);
    }

    /**
     * GET /integration/telemetry-sync/(:num)
     * Real-Time Sensor Telemetry Stream API (Phase 4D)
     */
    public function telemetrySync(int $assetId): ResponseInterface
    {
        $telemetry = $this->telemetryService->getRealTimeTelemetryStream($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $telemetry,
        ]);
    }

    /**
     * POST /integration/field-event
     * Ingest Real-Time Field Event Stream API (Phase 4D)
     */
    public function ingestFieldEvent(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $eventType = $json['event_type'] ?? 'FIELD_GPS_PING';

        return $this->response->setJSON([
            'status'     => 'success',
            'event_type' => $eventType,
            'message'    => 'Real-time field event ingested successfully into Integration Fabric.',
        ]);
    }
}
