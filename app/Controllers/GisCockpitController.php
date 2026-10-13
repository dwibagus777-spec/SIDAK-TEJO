<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use App\Services\GisSldCockpitService;

/**
 * B8.4 GIS Decision Cockpit Controller
 * Handles visual spatial decision surface & API endpoints.
 */
class GisCockpitController extends BaseController
{
    protected GisSldCockpitService $cockpitService;

    public function __construct()
    {
        $this->cockpitService = new GisSldCockpitService();
    }

    /**
     * Web Page Surface: /gis/cockpit
     */
    public function index()
    {
        $data = [
            'title'       => 'GIS Decision Cockpit — SIDAK TEJO',
            'snapshot_id' => GisSldCockpitService::SNAPSHOT_ID,
            'version'     => GisSldCockpitService::SPEC_VERSION,
        ];

        return view('gis_cockpit/index', $data);
    }

    /**
     * Spatial GeoJSON Endpoint: GET /gis/cockpit/spatial-geojson
     */
    public function getSpatialGeoJson(): ResponseInterface
    {
        $filters = [
            'ulp'             => $this->request->getGet('ulp'),
            'penyulang'       => $this->request->getGet('penyulang'),
            'health_category' => $this->request->getGet('health_category'),
            'is_active_only'  => $this->request->getGet('is_active_only') ?? 1,
        ];

        $geojson = $this->cockpitService->getSpatialGeoJson($filters);

        return $this->response->setJSON($geojson);
    }

    /**
     * 360-Degree Asset Context Profile: GET /gis/cockpit/asset-profile/(:num)
     */
    public function getAssetProfile($assetId = null): ResponseInterface
    {
        if (!$assetId) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Missing Asset ID.'
            ]);
        }

        $profile = $this->cockpitService->getAsset360Context((int)$assetId);

        if (!$profile['success']) {
            return $this->response->setStatusCode(404)->setJSON($profile);
        }

        return $this->response->setJSON($profile);
    }
}
