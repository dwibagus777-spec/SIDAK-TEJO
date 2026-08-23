<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\RealTimeSldTopologyService;
use App\Services\GridDisasterImmunityService;
use CodeIgniter\HTTP\ResponseInterface;

class EnterpriseDisasterImmunityController extends BaseController
{
    protected RealTimeSldTopologyService $topologyService;
    protected GridDisasterImmunityService $immunityService;

    public function __construct()
    {
        $this->topologyService = new RealTimeSldTopologyService();
        $this->immunityService = new GridDisasterImmunityService();
    }

    /**
     * GET /disaster-immunity/control-center
     * Enterprise Disaster Immunity Control View (Phase 7N)
     */
    public function index()
    {
        $topoRes     = $this->topologyService->reconstructSldTopology(1);
        $immunityRes = $this->immunityService->assessGridDisasterImmunity(1);

        return view('enterprise_disaster_immunity/index', [
            'title'            => 'SIDAK TEJO v3.0.0 — Enterprise SLD Topology & Disaster Immunity Center',
            'sldTopology'      => $topoRes['sld_topology'] ?? [],
            'immunityAdvisory' => $immunityRes['immunity_advisory'] ?? [],
        ]);
    }

    /**
     * GET /disaster-immunity/topology-snapshot
     * SLD Dynamic Topology Snapshot API (Phase 7N)
     */
    public function topologySnapshot(): ResponseInterface
    {
        $result = $this->topologyService->reconstructSldTopology(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * GET /disaster-immunity/immunity-advisory
     * Grid Disaster Immunity Advisory API (Phase 7N)
     */
    public function immunityAdvisory(): ResponseInterface
    {
        $result = $this->immunityService->assessGridDisasterImmunity(1);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
