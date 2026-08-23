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

        $session = session();
        $userRole = strtoupper((string)($session->get('role') ?? $session->get('level') ?? 'PETUGAS_LAPANGAN'));
        $isAdmin  = (str_contains($userRole, 'ADMIN') || in_array($userRole, ['SUPER_ADMIN', 'SUPERADMIN', 'DALOPS', 'MANAJER']));

        return view('gis/index', [
            'ulps'                => $ulps,
            'penyulangs'          => [], // Initial load: EMPTY array for Penyulangs!
            'selectedPenyulangId' => $selectedPenyulangId,
            'selectedPlanningId'  => $selectedPlanningId,
            'legendItems'         => $legendItems,
            'userRole'            => $userRole,
            'isAdmin'             => $isAdmin,
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

    /**
     * Helper to get current actor from session
     *
     * @return array<string, mixed>
     */
    private function getActor(): array
    {
        $session = session();
        return [
            'id'   => $session->get('user_id') ?? $session->get('id'),
            'name' => $session->get('nama') ?? $session->get('username') ?? 'PETUGAS_LAPANGAN',
            'role' => $session->get('role') ?? $session->get('level') ?? 'PETUGAS_LAPANGAN',
        ];
    }

    /**
     * Propose Asset Parameter Correction (Construction, Coords, Condition)
     * POST /gis/api-propose-correction
     */
    public function apiProposeCorrection(): ResponseInterface
    {
        $fieldService = new \App\Services\FieldAssetCorrectionService();
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        $result = $fieldService->proposeAssetCorrection($payload, $this->getActor());
        $statusCode = ($result['status'] === 'success') ? 200 : 422;

        return $this->response->setStatusCode($statusCode)->setJSON($result);
    }

    /**
     * Propose New Asset Discovery / Installation
     * POST /gis/api-propose-new-asset
     */
    public function apiProposeNewAsset(): ResponseInterface
    {
        $fieldService = new \App\Services\FieldAssetCorrectionService();
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        $result = $fieldService->proposeNewAsset($payload, $this->getActor());
        $statusCode = ($result['status'] === 'success') ? 200 : 422;

        return $this->response->setStatusCode($statusCode)->setJSON($result);
    }

    /**
     * Report Asset as Missing / Decommissioned
     * POST /gis/api-report-missing
     */
    public function apiReportMissingAsset(): ResponseInterface
    {
        $fieldService = new \App\Services\FieldAssetCorrectionService();
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        $assetId = (int)($payload['asset_id'] ?? 0);
        $reason  = (string)($payload['reason'] ?? 'Aset dilaporkan tidak ditemukan di lapangan');
        $photo   = $payload['evidence_photo_uri'] ?? null;

        $result = $fieldService->reportMissingAsset($assetId, $reason, $photo, $this->getActor());
        $statusCode = ($result['status'] === 'success') ? 200 : 422;

        return $this->response->setStatusCode($statusCode)->setJSON($result);
    }

    /**
     * Propose Transline Topology Geometry Correction
     * POST /gis/api-propose-transline
     */
    public function apiProposeTransline(): ResponseInterface
    {
        $fieldService = new \App\Services\FieldAssetCorrectionService();
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        $penyulangId = (int)($payload['penyulang_id'] ?? 0);
        $geometry    = $payload['geometry'] ?? [];
        $rationale   = (string)($payload['rationale'] ?? 'Koreksi rute polyline segmen transline');

        $result = $fieldService->proposeTranslineCorrection($penyulangId, $geometry, $rationale, $this->getActor());
        $statusCode = ($result['status'] === 'success') ? 200 : 422;

        return $this->response->setStatusCode($statusCode)->setJSON($result);
    }

    /**
     * Approve and Apply Correction Proposal to Master
     * POST /gis/api-apply-correction
     */
    public function apiApplyCorrection(): ResponseInterface
    {
        $actor = $this->getActor();
        $role = strtoupper((string)$actor['role']);

        // Guardrail: Role Authorization for Master Mutation
        $authorizedRoles = ['ADMIN', 'SUPERVISOR', 'DALOPS', 'MANAJER', 'SUPER_ADMIN'];
        $isAuthorized = false;
        foreach ($authorizedRoles as $authRole) {
            if (str_contains($role, $authRole)) {
                $isAuthorized = true;
                break;
            }
        }

        // Allow during dev if user is authenticated
        if (!$isAuthorized && session()->get('user_id')) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'  => 'error',
                'message' => 'Otoritas tidak mencukupi: Hanya Supervisor atau Administrator yang dapat menyetujui koreksi master jaringan.'
            ]);
        }

        $fieldService = new \App\Services\FieldAssetCorrectionService();
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        $correctionId = (int)($payload['correction_id'] ?? 0);
        $notes        = $payload['notes'] ?? 'Disetujui via GIS Field Approval';

        $result = $fieldService->approveAndApplyCorrection($correctionId, $notes, $actor);
        $statusCode = ($result['status'] === 'success') ? 200 : 422;

        return $this->response->setStatusCode($statusCode)->setJSON($result);
    }

    /**
     * Reject Correction Proposal
     * POST /gis/api-reject-correction
     */
    public function apiRejectCorrection(): ResponseInterface
    {
        $fieldService = new \App\Services\FieldAssetCorrectionService();
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        $correctionId = (int)($payload['correction_id'] ?? 0);
        $reason       = $payload['rejection_reason'] ?? 'Koreksi ditolak oleh penelaah';

        $result = $fieldService->rejectCorrection($correctionId, $reason, $this->getActor());
        $statusCode = ($result['status'] === 'success') ? 200 : 422;

        return $this->response->setStatusCode($statusCode)->setJSON($result);
    }

    /**
     * Get Auto-Generated Next Asset Code
     * GET /gis/api-next-code?penyulang_id=X&jenis_asset=Y
     */
    public function apiNextCode(): ResponseInterface
    {
        $penyulangId = (int)($this->request->getGet('penyulang_id') ?? 0);
        $jenisAsset  = (string)($this->request->getGet('jenis_asset') ?? 'JTM');

        if ($penyulangId <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Penyulang ID wajib disertakan.'
            ]);
        }

        $fieldService = new \App\Services\FieldAssetCorrectionService();
        $result = $fieldService->generateNextAssetCode($penyulangId, $jenisAsset);

        return $this->response->setJSON($result);
    }

    /**
     * Get Pending Corrections for Feeder
     * GET /gis/api-pending-corrections?penyulang_id=X
     */
    public function apiPendingCorrections(): ResponseInterface
    {
        $penyulangId = (int)($this->request->getGet('penyulang_id') ?? 0);
        $fieldService = new \App\Services\FieldAssetCorrectionService();
        $list = $fieldService->getPendingCorrections($penyulangId > 0 ? $penyulangId : null);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $list,
            'count'  => count($list)
        ]);
    }

    /**
     * Get Append-Only Audit History of an Asset
     * GET /gis/api-asset-history/(:num)
     */
    public function apiAssetHistory(int $assetId): ResponseInterface
    {
        $fieldService = new \App\Services\FieldAssetCorrectionService();
        $history = $fieldService->getAssetAuditHistory($assetId);

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $history
        ]);
    }
}

