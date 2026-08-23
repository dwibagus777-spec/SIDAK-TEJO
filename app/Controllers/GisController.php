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

    /**
     * Initial GIS Page Load
     * HANYA mengambil daftar ULP aktif.
     * TIDAK MEMUAT penyulang, aset, marker, atau GeoJSON besar saat initial load!
     */
    public function index()
    {
        $db = \Config\Database::connect();
        $ulps = [];

        if ($db->tableExists('ulps')) {
            $ulps = $db->table('ulps')
                ->select('id, kode_ulp, nama_ulp')
                ->where('status', 'AKTIF')
                ->orderBy('nama_ulp', 'ASC')
                ->get()
                ->getResultArray();
        }

        $selectedPenyulangId = (int)($this->request->getGet('penyulang_id') ?? 0);
        $selectedPlanningId  = (int)($this->request->getGet('planning_id') ?? 0);

        $visualRegistry = new \App\Services\AssetVisualRegistryService();
        $legendItems    = $visualRegistry->getLegendItems();

        return view('gis/index', [
            'ulps'                => $ulps,
            'penyulangs'          => [], // Initial load: EMPTY array for Penyulangs!
            'selectedPenyulangId' => $selectedPenyulangId,
            'selectedPlanningId'  => $selectedPlanningId,
            'legendItems'         => $legendItems,
        ]);
    }

    /**
     * Endpoint API Cascading Options: GET /gis/api-penyulangs?ulp_id=X
     * HANYA mengembalikan list penyulang milik ULP terpilih.
     */
    public function apiPenyulangs(): ResponseInterface
    {
        $ulpId = (int)($this->request->getGet('ulp_id') ?? 0);

        if ($ulpId <= 0) {
            return $this->response->setJSON([
                'status'     => 'success',
                'penyulangs' => []
            ]);
        }

        $db = \Config\Database::connect();
        $penyulangs = $db->table('penyulang p')
            ->select('p.id, p.kode_penyulang, p.nama_penyulang, p.ulp_id, u.nama_ulp')
            ->join('ulps u', 'p.ulp_id = u.id', 'left')
            ->where('p.ulp_id', $ulpId)
            ->orderBy('p.nama_penyulang', 'ASC')
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'status'     => 'success',
            'penyulangs' => $penyulangs
        ]);
    }

    /**
     * Endpoint Utama GIS Network On-Demand & Zoom LOD: GET /gis/api-network?penyulang_id=X&layers=JTM,GARDU,TRAFO,SWITCH&zoom=Z
     */
    public function apiNetwork(): ResponseInterface
    {
        $penyulangId = (int)($this->request->getGet('penyulang_id') ?? 0);
        $zoom        = (int)($this->request->getGet('zoom') ?? 14);
        $layers      = (string)($this->request->getGet('layers') ?? 'JTM,GARDU,TRAFO,SWITCH');

        if ($penyulangId <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Penyulang wajib dipilih.'
            ]);
        }

        $filters = [
            'penyulang_id' => $penyulangId,
            'layers'       => explode(',', $layers),
            'zoom'         => $zoom,
        ];

        $userUlpId = session()->get('ulp_id');
        $result = $this->gisService->getNetworkData($filters, $userUlpId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $result
        ]);
    }

    public function apiGenerateCandidates(): ResponseInterface
    {
        $penyulangId = (int)($this->request->getGet('penyulang_id') ?? 0);
        if ($penyulangId <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Penyulang wajib dipilih.'
            ]);
        }

        $userUlpId = session()->get('ulp_id');
        $res = $this->topologyService->generateFeederTopologyCandidates($penyulangId, $userUlpId);

        return $this->response->setJSON($res);
    }

    public function apiConnectTopology(): ResponseInterface
    {
        $parentId = (int)($this->request->getPost('parent_id') ?? $this->request->getVar('parent_id') ?? 0);
        $childId  = (int)($this->request->getPost('child_id') ?? $this->request->getVar('child_id') ?? 0);

        if ($parentId <= 0 || $childId <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Parent ID dan Child ID wajib diisi.'
            ]);
        }

        if ($this->topologyService->wouldCreateCycle($parentId, $childId)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Penambahan relasi ditolak: Terdeteksi circular topology (loop tertutup)!'
            ]);
        }

        $db = \Config\Database::connect();
        $db->table('assets')->where('id', $childId)->update(['parent_asset_id' => $parentId]);

        $relModel = new \App\Models\AssetRelationshipModel();
        $existing = $relModel->where('parent_asset_id', $parentId)->where('child_asset_id', $childId)->first();

        $data = [
            'parent_asset_id' => $parentId,
            'child_asset_id'  => $childId,
            'source'          => 'MANUAL',
            'status'          => 'VERIFIED',
            'verified_at'     => date('Y-m-d H:i:s'),
            'is_active'       => 1,
        ];

        if ($existing) {
            $relModel->update($existing['id'], $data);
        } else {
            $relModel->insert($data);
        }

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => "Relasi topologi berhasil divalidasi & diverifikasi ($parentId -> $childId)."
        ]);
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
}
