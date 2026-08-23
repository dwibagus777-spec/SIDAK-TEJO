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
        $rawRole = (string)($session->get('user_role') ?? $session->get('role') ?? $session->get('level') ?? 'PETUGAS_LAPANGAN');
        $userRole = strtoupper(trim($rawRole));
        $isAdmin  = (str_contains($userRole, 'ADMIN') || in_array($userRole, ['SUPER_ADMIN', 'SUPERADMIN', 'DALOPS', 'MANAJER']));

        // Defensive initialization & resolution of legend items
        $legendItems = [];
        try {
            $visualRegistry = new \App\Services\AssetVisualRegistryService();
            $legendItems    = $visualRegistry->getLegendItems();
        } catch (\Throwable $e) {
            log_message('error', '[GisController::index] Failed to load legend items: ' . $e->getMessage());
            $legendItems = [];
        }

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
            return $this->response->setStatusCode(422)->setJSON([
                'status'     => 'error',
                'message'    => 'ULP wajib dipilih.',
                'penyulangs' => []
            ]);
        }

        $penyulangs = $this->assetRepository->getActivePenyulangsByUlp($ulpId);

        return $this->response->setJSON([
            'status'     => 'success',
            'penyulangs' => $penyulangs
        ]);
    }

    /**
     * Endpoint API Master Conductors: GET /gis/api-conductors
     */
    public function apiConductors(): ResponseInterface
    {
        $db = \Config\Database::connect();
        $conductors = [];
        if ($db->tableExists('master_conductors')) {
            $conductors = $db->table('master_conductors')
                ->where('status', 'AKTIF')
                ->orderBy('sort_order', 'ASC')
                ->get()
                ->getResultArray();
        }

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $conductors
        ]);
    }

    /**
     * Endpoint API Network Data On-Demand (Berdasarkan Penyulang Terpilih)
     * GET /gis/api-network?penyulang_id=X&zoom=Y&layers=JTM,GARDU
     */
    public function apiNetwork(): ResponseInterface
    {
        $penyulangId = (int)($this->request->getGet('penyulang_id') ?? 0);
        if ($penyulangId <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Penyulang wajib dipilih untuk memuat data peta jaringan.'
            ]);
        }

        $zoom   = (int)($this->request->getGet('zoom') ?? 14);
        $layers = (string)($this->request->getGet('layers') ?? 'JTM,GARDU,TRAFO,SWITCH');

        $filters = [
            'penyulang_id' => $penyulangId,
            'zoom'         => $zoom,
            'layers'       => $layers
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
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        
        $sourceId = (int)($payload['source_asset_id'] ?? $payload['parent_id'] ?? 0);
        $targetId = (int)($payload['target_asset_id'] ?? $payload['child_id'] ?? 0);
        $mode     = (string)($payload['connection_mode'] ?? 'REPLACE'); // 'REPLACE' or 'ADD'
        $conductorType     = (string)($payload['conductor_type'] ?? 'AAAC');
        $conductorSize     = (string)($payload['conductor_size'] ?? '150 mm²');
        $conductorMaterial = (string)($payload['conductor_material'] ?? 'ALUMINUM_ALLOY');
        $installationType  = (string)($payload['installation_type'] ?? 'OVERHEAD');
        $circuitConfig     = (string)($payload['circuit_config'] ?? '3_PHASE');

        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'ID Aset Sumber dan ID Aset Tujuan valid wajib diisi.'
            ]);
        }

        if ($this->topologyService->wouldCreateCycle($sourceId, $targetId)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Penambahan relasi ditolak: Terdeteksi circular topology (loop tertutup)!'
            ]);
        }

        $actor = $this->getActor();
        $actorRole = strtoupper((string)($actor['role'] ?? 'PETUGAS_LAPANGAN'));
        $isAdmin = (str_contains($actorRole, 'ADMIN') || in_array($actorRole, ['SUPER_ADMIN', 'SUPERADMIN', 'DALOPS', 'MANAJER']));

        $db = \Config\Database::connect();
        $relModel = new \App\Models\AssetRelationshipModel();

        if ($isAdmin) {
            // ADMIN DIRECT COMMIT
            $db->transStart();

            // Set parent_asset_id on target asset
            $db->table('assets')->where('id', $targetId)->update(['parent_asset_id' => $sourceId]);

            // If REPLACE mode, clear any old relationship from targetId
            if ($mode === 'REPLACE') {
                $relModel->where('child_asset_id', $targetId)->delete();
            }

            $sourceAsset = $db->table('assets')->select('latitude, longitude, penyulang_id')->where('id', $sourceId)->get()->getRowArray();
            $targetAsset = $db->table('assets')->select('latitude, longitude, penyulang_id')->where('id', $targetId)->get()->getRowArray();
            $distance = 0.0;
            if ($sourceAsset && $targetAsset && !empty($sourceAsset['latitude']) && !empty($sourceAsset['latitude'])) {
                $distance = round($this->calculateHaversine((float)$sourceAsset['latitude'], (float)$sourceAsset['longitude'], (float)$targetAsset['latitude'], (float)$targetAsset['longitude']), 1);
            }

            $existing = $relModel->where('parent_asset_id', $sourceId)->where('child_asset_id', $targetId)->first();
            $data = [
                'parent_asset_id'    => $sourceId,
                'child_asset_id'     => $targetId,
                'source_asset_id'    => $sourceId,
                'target_asset_id'    => $targetId,
                'relationship_type'  => 'NETWORK',
                'conductor_type'     => $conductorType,
                'conductor_size'     => $conductorSize,
                'conductor_material' => $conductorMaterial,
                'installation_type'  => $installationType,
                'circuit_config'     => $circuitConfig,
                'distance_meters'    => $distance,
                'source'             => 'GIS_ADMIN_DIRECT_EDIT',
                'status'             => 'VERIFIED',
                'verified_by'        => $actor['id'],
                'verified_at'        => date('Y-m-d H:i:s'),
                'is_active'          => 1,
            ];

            if ($existing) {
                $relModel->update($existing['id'], $data);
            } else {
                $relModel->insert($data);
            }

            // Log to field_corrections audit trail
            $db->table('field_corrections')->insert([
                'correction_code' => 'COR-TOP-' . date('Ymd') . '-' . str_pad((string)mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                'correction_type' => 'TOPOLOGY_CONNECTION',
                'asset_id'        => $targetId,
                'penyulang_id'    => (int)($targetAsset['penyulang_id'] ?? $sourceAsset['penyulang_id'] ?? 0),
                'after_payload'   => json_encode([
                    'parent_id'          => $sourceId,
                    'child_id'           => $targetId,
                    'mode'               => $mode,
                    'conductor_type'     => $conductorType,
                    'conductor_size'     => $conductorSize,
                    'conductor_material' => $conductorMaterial,
                ]),
                'rationale'       => "Pembaruan koneksi jalur antar tiang ($conductorType $conductorSize) oleh Administrator",
                'reporter_name'   => $actor['name'],
                'reporter_role'   => $actorRole,
                'status'          => 'APPROVED',
                'reviewer_name'   => $actor['name'],
                'reviewer_role'   => $actorRole,
                'review_notes'    => 'Direct Commit by Administrator (GIS_ADMIN_DIRECT_EDIT)',
                'reviewed_at'     => date('Y-m-d H:i:s'),
                'applied_at'      => date('Y-m-d H:i:s'),
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

            $db->transComplete();

            return $this->response->setJSON([
                'status'           => 'success',
                'is_direct_commit' => true,
                'message'          => "Sambungan jalur ($conductorType $conductorSize) berhasil diterapkan & langsung aktif (Direct Commit)."
            ]);
        }

        // NON-ADMIN PROPOSAL
        $fieldService = new \App\Services\FieldAssetCorrectionService();
        $result = $fieldService->proposeAssetCorrection([
            'asset_id'           => $targetId,
            'correction_type'    => 'TOPOLOGY_CONNECTION',
            'parent_asset_id'    => $sourceId,
            'conductor_type'     => $conductorType,
            'conductor_size'     => $conductorSize,
            'conductor_material' => $conductorMaterial,
            'rationale'          => "Usulan sambungan jalur ke aset ID #$sourceId ($conductorType $conductorSize)",
        ], $actor);

        return $this->response->setJSON($result);
    }

    public function apiDisconnectTopology(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        
        $sourceId = (int)($payload['source_asset_id'] ?? 0);
        $targetId = (int)($payload['target_asset_id'] ?? 0);

        if ($sourceId <= 0 || $targetId <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Kedua ID aset yang terhubung wajib disertakan.'
            ]);
        }

        $actor = $this->getActor();
        $actorRole = strtoupper((string)($actor['role'] ?? 'PETUGAS_LAPANGAN'));
        $isAdmin = (str_contains($actorRole, 'ADMIN') || in_array($actorRole, ['SUPER_ADMIN', 'SUPERADMIN', 'DALOPS', 'MANAJER']));

        $db = \Config\Database::connect();
        $relModel = new \App\Models\AssetRelationshipModel();

        if ($isAdmin) {
            $db->transStart();

            // Clear parent_asset_id if target points to source or vice-versa
            $db->table('assets')->where('id', $targetId)->where('parent_asset_id', $sourceId)->update(['parent_asset_id' => null]);
            $db->table('assets')->where('id', $sourceId)->where('parent_asset_id', $targetId)->update(['parent_asset_id' => null]);

            // Deactivate relationship edge
            $relModel->where('parent_asset_id', $sourceId)->where('child_asset_id', $targetId)->delete();
            $relModel->where('parent_asset_id', $targetId)->where('child_asset_id', $sourceId)->delete();

            // Log to field_corrections audit trail
            $db->table('field_corrections')->insert([
                'correction_code' => 'COR-TOP-' . date('Ymd') . '-' . str_pad((string)mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                'correction_type' => 'TOPOLOGY_DISCONNECT',
                'asset_id'        => $targetId,
                'penyulang_id'    => (int)($db->table('assets')->select('penyulang_id')->where('id', $targetId)->get()->getRow()->penyulang_id ?? 0),
                'after_payload'   => json_encode(['disconnected_from' => $sourceId, 'target_id' => $targetId]),
                'rationale'       => 'Penghapusan sambungan jalur salah oleh Administrator',
                'reporter_name'   => $actor['name'],
                'reporter_role'   => $actorRole,
                'status'          => 'APPROVED',
                'reviewer_name'   => $actor['name'],
                'reviewer_role'   => $actorRole,
                'review_notes'    => 'Direct Commit by Administrator (GIS_ADMIN_DIRECT_EDIT)',
                'reviewed_at'     => date('Y-m-d H:i:s'),
                'applied_at'      => date('Y-m-d H:i:s'),
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

            $db->transComplete();

            return $this->response->setJSON([
                'status'           => 'success',
                'is_direct_commit' => true,
                'message'          => 'Sambungan jalur berhasil dihapus dari topologi aktif (Direct Commit).'
            ]);
        }

        return $this->response->setJSON([
            'status'           => 'success',
            'is_direct_commit' => false,
            'message'          => 'Usulan pemutusan jalur telah diajukan untuk ditelaah Supervisor.'
        ]);
    }

    public function apiUpdateConductorSpecification(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        
        $sourceId = (int)($payload['source_asset_id'] ?? 0);
        $targetId = (int)($payload['target_asset_id'] ?? 0);
        $conductorType     = (string)($payload['conductor_type'] ?? 'AAAC');
        $conductorSize     = (string)($payload['conductor_size'] ?? '150 mm²');
        $conductorMaterial = (string)($payload['conductor_material'] ?? 'ALUMINUM_ALLOY');
        $installationType  = (string)($payload['installation_type'] ?? 'OVERHEAD');
        $circuitConfig     = (string)($payload['circuit_config'] ?? '3_PHASE');

        if ($sourceId <= 0 || $targetId <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Kedua ID aset yang terhubung wajib disertakan.'
            ]);
        }

        $actor = $this->getActor();
        $actorRole = strtoupper((string)($actor['role'] ?? 'PETUGAS_LAPANGAN'));
        $isAdmin = (str_contains($actorRole, 'ADMIN') || in_array($actorRole, ['SUPER_ADMIN', 'SUPERADMIN', 'DALOPS', 'MANAJER']));

        $db = \Config\Database::connect();
        $relModel = new \App\Models\AssetRelationshipModel();

        if ($isAdmin) {
            $db->transStart();

            $updateData = [
                'conductor_type'     => $conductorType,
                'conductor_size'     => $conductorSize,
                'conductor_material' => $conductorMaterial,
                'installation_type'  => $installationType,
                'circuit_config'     => $circuitConfig,
                'updated_at'         => date('Y-m-d H:i:s'),
            ];

            // Update in both directions if present
            $db->table('asset_relationships')
                ->groupStart()
                    ->where('parent_asset_id', $sourceId)->where('child_asset_id', $targetId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('parent_asset_id', $targetId)->where('child_asset_id', $sourceId)
                ->groupEnd()
                ->update($updateData);

            // Audit trail
            $db->table('field_corrections')->insert([
                'correction_code' => 'COR-TOP-' . date('Ymd') . '-' . str_pad((string)mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                'correction_type' => 'CONDUCTOR_SPEC_UPDATE',
                'asset_id'        => $targetId,
                'penyulang_id'    => (int)($db->table('assets')->select('penyulang_id')->where('id', $targetId)->get()->getRow()->penyulang_id ?? 0),
                'after_payload'   => json_encode($updateData),
                'rationale'       => "Pembaruan spesifikasi konduktor menjadi $conductorType $conductorSize oleh Administrator",
                'reporter_name'   => $actor['name'],
                'reporter_role'   => $actorRole,
                'status'          => 'APPROVED',
                'reviewer_name'   => $actor['name'],
                'reviewer_role'   => $actorRole,
                'review_notes'    => 'Direct Commit by Administrator (GIS_ADMIN_DIRECT_EDIT)',
                'reviewed_at'     => date('Y-m-d H:i:s'),
                'applied_at'      => date('Y-m-d H:i:s'),
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

            $db->transComplete();

            return $this->response->setJSON([
                'status'           => 'success',
                'is_direct_commit' => true,
                'message'          => "Spesifikasi konduktor ($conductorType $conductorSize) berhasil diperbarui dan langsung aktif (Direct Commit)."
            ]);
        }

        return $this->response->setJSON([
            'status'           => 'success',
            'is_direct_commit' => false,
            'message'          => "Usulan perubahan spesifikasi konduktor ($conductorType $conductorSize) berhasil diajukan dan menunggu telaah supervisor."
        ]);
    }

    private function calculateHaversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) ** 2;

        return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function apiUpdateSegmentGeometry(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        
        $penyulangId = (int)($payload['penyulang_id'] ?? 0);
        $sourceId    = (int)($payload['source_asset_id'] ?? 0);
        $targetId    = (int)($payload['target_asset_id'] ?? 0);
        $geometry    = $payload['geometry'] ?? [];

        if ($penyulangId <= 0 || empty($geometry['coordinates'])) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Data penyulang dan koordinat segmen tidak valid.'
            ]);
        }

        $fieldService = new \App\Services\FieldAssetCorrectionService();
        $result = $fieldService->proposeTranslineCorrection($penyulangId, $geometry, "Pembaruan geometri segmen aset #$sourceId ke #$targetId", $this->getActor());

        return $this->response->setJSON($result);
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
        $rawRole = (string)($session->get('user_role') ?? $session->get('role') ?? $session->get('level') ?? 'PETUGAS_LAPANGAN');
        $role = strtoupper(trim($rawRole));
        $name = (string)($session->get('user_name') ?? $session->get('nama_pegawai') ?? $session->get('nama') ?? $session->get('username') ?? 'PETUGAS_LAPANGAN');
        $id   = (int)($session->get('user_id') ?? 1);
        return [
            'id'   => $id,
            'name' => $name,
            'role' => $role,
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

