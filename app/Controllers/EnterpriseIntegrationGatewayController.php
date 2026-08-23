<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\FederatedIntegrationGatewayService;
use App\Services\ExternalEnterpriseAdapterRegistryService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseIntegrationGatewayController extends BaseController
{
    protected FederatedIntegrationGatewayService $gatewayService;
    protected ExternalEnterpriseAdapterRegistryService $adapterService;

    public function __construct()
    {
        $this->gatewayService = new FederatedIntegrationGatewayService();
        $this->adapterService = new ExternalEnterpriseAdapterRegistryService();
    }

    /**
     * GET /integration/federated-gateway
     * Enterprise Integration Gateway Control View (Phase 7F)
     */
    public function index()
    {
        $gwRes      = $this->gatewayService->processInboundIntegrationRequest([]);
        $adapterRes = $this->adapterService->getAdapterHealthStatus();

        return view('enterprise_integration_gateway/index', [
            'title'         => 'SIDAK TEJO v3.0.0 — Enterprise Federated Integration Gateway & Adapter Control Center',
            'gatewayStatus' => $gwRes['gateway_result'] ?? [],
            'adapterHealth' => $adapterRes['adapter_health'] ?? [],
        ]);
    }

    /**
     * POST /integration/inbound-request
     * Inbound Integration Gateway Request API (Phase 7F)
     */
    public function inboundRequest(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? [];
        $result  = $this->gatewayService->processInboundIntegrationRequest($payload);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /integration/adapter-health
     * External Enterprise Adapter Health API (Phase 7F)
     */
    public function adapterHealth(): ResponseInterface
    {
        $result = $this->adapterService->getAdapterHealthStatus();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
