<?php

namespace App\Controllers;

use App\Services\SpatialPreventiveMaterialBridgeService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Spatial BOM & Finding-to-BOM Intelligence Controller (CR-08 Phase 2)
 *
 * Responsibilities:
 * - Render Spatial BOM & Preventive Material Intelligence Workspace Dashboard.
 * - Serve APIs for Spatial Asset BOM drill-down and Feeder Material Estimation.
 */
class SpatialPreventiveMaterialController extends BaseController
{
    protected SpatialPreventiveMaterialBridgeService $bridgeService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->bridgeService = new SpatialPreventiveMaterialBridgeService();
    }

    /**
     * Render Spatial BOM & Preventive Material Bridge Dashboard.
     */
    public function index()
    {
        $summary = $this->bridgeService->getWorkspaceSummary();

        return view('spatial_bom/index', [
            'title'   => 'Spatial BOM & Finding-to-Material Bridge | SIDAK TEJO',
            'summary' => $summary,
        ]);
    }

    /**
     * API: Get Workspace Summary.
     * GET /api/spatial-bom/summary
     */
    public function apiSummary(): ResponseInterface
    {
        $data = $this->bridgeService->getWorkspaceSummary();
        return $this->response->setJSON($data);
    }

    /**
     * API: Get Spatial BOM Detail for an Asset.
     * GET /api/spatial-bom/asset/(:num)
     */
    public function apiAssetDetail(int $assetId): ResponseInterface
    {
        $result = $this->bridgeService->getAssetSpatialBomDetail($assetId);
        if (!$result['success']) {
            return $this->response->setStatusCode(404)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }

    /**
     * API: Generate Preventive Material Recommendation for a Feeder.
     * POST /api/spatial-bom/feeder-recommendation
     */
    public function apiFeederRecommendation(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $feederId = (int)($json['penyulang_id'] ?? 15);
        $actor    = $json['actor'] ?? [
            'actor_id'   => 1,
            'actor_name' => 'SUPERVISOR_PEMELIHARAAN_JARINGAN',
            'actor_nip'  => '198403152008121003',
            'actor_role' => 'MAINTENANCE_SUPERVISOR',
        ];

        $result = $this->bridgeService->generateFeederMaterialRecommendation($feederId, $actor);
        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }
}
