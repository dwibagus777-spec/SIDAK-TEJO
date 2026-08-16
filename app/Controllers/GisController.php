<?php

namespace App\Controllers;

use App\Services\GISService;
use App\Services\AssetTopologyService;
use App\Services\BaselineService;
use CodeIgniter\HTTP\ResponseInterface;

class GisController extends BaseController
{
    private GISService $gisService;
    private AssetTopologyService $topologyService;
    private BaselineService $baselineService;

    public function __construct()
    {
        $this->gisService = new GISService();
        $this->topologyService = new AssetTopologyService();
        $this->baselineService = new BaselineService();
    }

    public function index()
    {
        $db = \Config\Database::connect();
        $penyulangs = [];
        if ($db->tableExists('penyulang')) {
            $penyulangs = $db->table('penyulang p')
                ->select('p.id, p.kode_penyulang, p.nama_penyulang, p.ulp_id, u.nama_ulp')
                ->join('ulps u', 'p.ulp_id = u.id', 'left')
                ->get()
                ->getResultArray();
        }

        $ulps = [];
        if ($db->tableExists('ulps')) {
            $ulps = $db->table('ulps')
                ->select('id, kode_ulp, nama_ulp')
                ->where('status', 'AKTIF')
                ->get()
                ->getResultArray();
        }

        $selectedPenyulangId = (int)($this->request->getGet('penyulang_id') ?? 0);
        $selectedPlanningId  = (int)($this->request->getGet('planning_id') ?? 0);

        return view('gis/index', [
            'ulps'                => $ulps,
            'penyulangs'          => $penyulangs,
            'selectedPenyulangId' => $selectedPenyulangId,
            'selectedPlanningId'  => $selectedPlanningId,
        ]);
    }

    public function apiData(): ResponseInterface
    {
        $penyulangId = (int)($this->request->getGet('penyulang_id') ?? 0);
        $userUlpId = session()->get('ulp_id');

        $collection = $this->gisService->getGeoJsonCollection(
            $penyulangId > 0 ? ['penyulang_id' => $penyulangId] : [],
            $userUlpId
        );

        return $this->response->setJSON([
            'status' => 'success',
            'stats'  => [
                'total_pins' => count($collection['features'] ?? [])
            ],
            'features' => $collection['features'] ?? []
        ]);
    }

    public function checkin(): ResponseInterface
    {
        return $this->response->setJSON(['status' => 'success', 'message' => 'Check-in recorded']);
    }

    public function geoJson(): ResponseInterface
    {
        $session = session();
        $userUlpId = $session->get('ulp_id');

        $filters = [
            'ulp_id'       => $this->request->getGet('ulp_id'),
            'penyulang_id' => $this->request->getGet('penyulang_id'),
            'section_id'   => $this->request->getGet('section_id'),
            'jenis_asset'  => $this->request->getGet('jenis_asset'),
            'status'       => $this->request->getGet('status'),
            'search'       => $this->request->getGet('search'),
        ];

        $collection = $this->gisService->getGeoJsonCollection($filters, $userUlpId);

        return $this->response->setJSON($collection);
    }

    public function network(int $assetId): ResponseInterface
    {
        $tree = $this->topologyService->getTopologyTree($assetId);
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $tree
        ]);
    }

    public function baseline(int $baselineId): ResponseInterface
    {
        $route = $this->baselineService->getOrderedBaselineAssets($baselineId);
        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $route
        ]);
    }

    /**
     * Release B - Step B2: GET master-assets/feeder-network?penyulang_id=X
     */
    public function feederNetwork(): ResponseInterface
    {
        $penyulangId = (int)($this->request->getGet('penyulang_id') ?? 0);
        if ($penyulangId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['status' => 'error', 'message' => 'penyulang_id required.']);
        }

        $res = $this->gisService->getFeederNetwork($penyulangId);
        return $this->response->setJSON($res);
    }

    /**
     * Release D: GET master-assets/feeder-assets?penyulang_id=X&planning_id=Y&min_lat=...
     */
    public function feederAssets(): ResponseInterface
    {
        $penyulangId = (int)($this->request->getGet('penyulang_id') ?? 0);
        if ($penyulangId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['status' => 'error', 'message' => 'penyulang_id required.']);
        }

        $planningId = $this->request->getGet('planning_id') !== null ? (int)$this->request->getGet('planning_id') : null;
        $minLat     = $this->request->getGet('min_lat') !== null ? (float)$this->request->getGet('min_lat') : null;
        $maxLat     = $this->request->getGet('max_lat') !== null ? (float)$this->request->getGet('max_lat') : null;
        $minLng     = $this->request->getGet('min_lng') !== null ? (float)$this->request->getGet('min_lng') : null;
        $maxLng     = $this->request->getGet('max_lng') !== null ? (float)$this->request->getGet('max_lng') : null;

        $res = $this->gisService->getFeederViewportAssets($penyulangId, $minLat, $maxLat, $minLng, $maxLng, $planningId);
        return $this->response->setJSON($res);
    }
}
