<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\FieldOfflineSyncService;
use App\Services\MeshTelemetrySyncService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseMobilityController extends BaseController
{
    protected FieldOfflineSyncService $syncService;
    protected MeshTelemetrySyncService $meshService;

    public function __construct()
    {
        $this->syncService = new FieldOfflineSyncService();
        $this->meshService = new MeshTelemetrySyncService();
    }

    /**
     * GET /mobility/offline-sync
     * Enterprise Mobility & Offline Sync Control View (Phase 7E)
     */
    public function index()
    {
        $syncRes = $this->syncService->processOfflineSyncEnvelope([]);
        $meshRes = $this->meshService->bufferMeshTelemetryQueue([]);

        return view('enterprise_mobility/index', [
            'title'      => 'SIDAK TEJO v3.0.0 — Advanced Field Mobility & Offline Sync Center',
            'syncStatus' => $syncRes['sync_resolution'] ?? [],
            'meshStatus' => $meshRes['mesh_queue_buffer'] ?? [],
        ]);
    }

    /**
     * POST /mobility/sync-envelope
     * Process Offline Inspection Sync Envelope API (Phase 7E)
     */
    public function syncEnvelope(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? [];
        $result  = $this->syncService->processOfflineSyncEnvelope($payload);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /mobility/telemetry-status
     * Mesh Telemetry Buffer Status API (Phase 7E)
     */
    public function telemetryStatus(): ResponseInterface
    {
        $result = $this->meshService->bufferMeshTelemetryQueue([]);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
