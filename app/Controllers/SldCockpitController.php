<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use App\Services\GisSldCockpitService;

/**
 * B8.4 SLD Decision Cockpit Controller
 * Handles Single Line Diagram topology cockpit & API endpoints.
 */
class SldCockpitController extends BaseController
{
    protected GisSldCockpitService $cockpitService;

    public function __construct()
    {
        $this->cockpitService = new GisSldCockpitService();
    }

    /**
     * Web Page Surface: /sld/cockpit
     */
    public function index()
    {
        $data = [
            'title'       => 'SLD Decision Cockpit — SIDAK TEJO',
            'snapshot_id' => GisSldCockpitService::SNAPSHOT_ID,
            'version'     => GisSldCockpitService::SPEC_VERSION,
        ];

        return view('sld_cockpit/index', $data);
    }

    /**
     * Topology Overlay API: GET /sld/cockpit/topology-overlay
     */
    public function getTopologyOverlay(): ResponseInterface
    {
        $filters = [
            'ulp'             => $this->request->getGet('ulp'),
            'penyulang'       => $this->request->getGet('penyulang'),
            'health_category' => $this->request->getGet('health_category'),
        ];

        $topology = $this->cockpitService->getSldTopologyOverlay($filters);

        return $this->response->setJSON($topology);
    }
}
