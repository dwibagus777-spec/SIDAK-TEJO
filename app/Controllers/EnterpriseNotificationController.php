<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\MultiChannelNotificationService;
use App\Services\DispatchAdapterRegistryService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseNotificationController extends BaseController
{
    protected MultiChannelNotificationService $notifService;
    protected DispatchAdapterRegistryService $registryService;

    public function __construct()
    {
        $this->notifService    = new MultiChannelNotificationService();
        $this->registryService = new DispatchAdapterRegistryService();
    }

    /**
     * GET /notification/dispatch
     * Enterprise Multi-Channel Dispatch & Adapter Control View (Phase 7B)
     */
    public function index()
    {
        $dispatch  = $this->notifService->dispatchNotification('WHATSAPP', 'PETUGAS_LAPANGAN_ULP', 'Peringatan EMERGENCY: Isolator Retak Gardu SDJ-045', 'EVT-STJ-20260822-001');
        $adapters  = $this->registryService->getAvailableAdapters();
        $execRes   = $this->registryService->executeAdapterDispatch('WHATSAPP', $dispatch['dispatch_intent'] ?? []);

        return view('enterprise_notification/index', [
            'title'     => 'SIDAK TEJO v3.0.0 — Enterprise Multi-Channel Dispatch & Field Notification',
            'dispatch'  => $dispatch['dispatch_intent'] ?? [],
            'adapters'  => $adapters,
            'execRes'   => $execRes['dispatch_result'] ?? [],
        ]);
    }

    /**
     * POST /notification/send
     * Dispatch Notification API (Phase 7B)
     */
    public function sendNotification(): ResponseInterface
    {
        $json       = $this->request->getJSON(true) ?? [];
        $channel    = $json['channel'] ?? 'WHATSAPP';
        $recipient  = $json['recipient'] ?? 'PETUGAS_LAPANGAN_ULP';
        $message    = $json['message'] ?? 'Notifikasi Lapangan SIDAK TEJO';
        $ref        = $json['correlation_ref'] ?? 'EVT-STJ-20260822-001';

        $result = $this->notifService->dispatchNotification($channel, $recipient, $message, $ref);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /notification/adapters
     * Adapter Registry Status API (Phase 7B)
     */
    public function adapterStatus(): ResponseInterface
    {
        $adapters = $this->registryService->getAvailableAdapters();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $adapters,
        ]);
    }
}
