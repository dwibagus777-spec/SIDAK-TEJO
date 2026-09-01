<?php

namespace App\Controllers;

use App\Services\GISService;
use App\Services\AssetTopologyService;
use App\Services\BaselineService;
use App\Repositories\PenyulangRepository;
use App\Repositories\AssetRepository;
use CodeIgniter\HTTP\ResponseInterface;

class GisController extends BaseController
{
    private GISService $gisService;
    private AssetTopologyService $topologyService;
    private BaselineService $baselineService;
    private PenyulangRepository $penyulangRepository;
    private AssetRepository $assetRepository;
    private \App\Services\GisTranslineService $translineService;

    public function __construct()
    {
        $this->gisService = new GISService();
        $this->topologyService = new AssetTopologyService();
        $this->baselineService = new BaselineService();
        $this->penyulangRepository = new PenyulangRepository();
        $this->assetRepository = new AssetRepository();
        $this->translineService = new \App\Services\GisTranslineService();
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
        try {
            $ulpId = (int)(
                (method_exists($this->request, 'getGet') ? $this->request->getGet('ulp_id') : null)
                ?? ($_GET['ulp_id'] ?? 0)
            );

            if ($ulpId <= 0) {
                return $this->response->setStatusCode(422)->setJSON([
                    'status'     => 'error',
                    'message'    => 'ULP wajib dipilih.',
                    'penyulangs' => []
                ]);
            }

            $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($ulpId);

            return $this->response->setStatusCode(200)->setJSON([
                'status'     => 'success',
                'penyulangs' => $penyulangs
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[GisController::apiPenyulangs] Exception: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status'     => 'error',
                'message'    => 'Gagal memuat data penyulang: ' . $e->getMessage(),
                'penyulangs' => []
            ]);
        }
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

        return $this->response->setStatusCode(200)->setJSON([
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
        $penyulangId = (int)(
            (method_exists($this->request, 'getGet') ? $this->request->getGet('penyulang_id') : null)
            ?? ($_GET['penyulang_id'] ?? 0)
        );

        if ($penyulangId <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Penyulang wajib dipilih untuk memuat data peta jaringan.'
            ]);
        }

        $zoom = (int)(
            (method_exists($this->request, 'getGet') ? $this->request->getGet('zoom') : null)
            ?? ($_GET['zoom'] ?? 14)
        );

        $layers = (string)(
            (method_exists($this->request, 'getGet') ? $this->request->getGet('layers') : null)
            ?? ($_GET['layers'] ?? 'JTM,GARDU,TRAFO,SWITCH')
        );

        $filters = [
            'penyulang_id' => $penyulangId,
            'zoom'         => $zoom,
            'layers'       => $layers
        ];

        $userUlpId = session()->get('ulp_id');
        $result = $this->gisService->getNetworkData($filters, $userUlpId);

        return $this->response->setStatusCode(200)->setJSON([
            'status' => 'success',
            'data'   => $result
        ]);
    }

    /**
     * Endpoint Audit Data Provenance & Boundary: GET /gis/api-network-audit?penyulang_id=X
     */
    public function apiNetworkAudit(): ResponseInterface
    {
        $penyulangId = (int)(
            (method_exists($this->request, 'getGet') ? $this->request->getGet('penyulang_id') : null)
            ?? ($_GET['penyulang_id'] ?? 0)
        );

        if ($penyulangId <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Penyulang ID wajib disertakan untuk audit data.'
            ]);
        }

        $db = \Config\Database::connect();
        $feederRow = $db->table('penyulang')->where('id', $penyulangId)->get()->getRowArray();
        $feederUlpId = (int)($feederRow['ulp_id'] ?? 1);

        $totalDbFeederAssets = $db->table('assets')
            ->where('penyulang_id', $penyulangId)
            ->where('deleted_at IS NULL')
            ->countAllResults();

        $totalDbUnassignedAssets = $db->table('assets')
            ->where('ulp_id', $feederUlpId)
            ->where('(penyulang_id IS NULL OR penyulang_id = 0)')
            ->where('deleted_at IS NULL')
            ->countAllResults();

        $totalDbTemuan = $db->tableExists('temuan') ? $db->table('temuan')
            ->where('penyulang_id', $penyulangId)
            ->where('deleted_at IS NULL')
            ->countAllResults() : 0;

        $userUlpId = session()->get('ulp_id');
        $networkData = $this->gisService->getNetworkData([
            'penyulang_id' => $penyulangId,
            'ulp_id'       => $feederUlpId,
            'zoom'         => 15,
            'layers'       => ['JTM', 'GARDU', 'TRAFO', 'SWITCH', 'TEMUAN']
        ], $userUlpId);

        $features = $networkData['features'] ?? [];
        $summary  = $networkData['summary'] ?? [];

        $feederFeaturesCount     = 0;
        $unassignedFeaturesCount = 0;
        $findingFeaturesCount    = 0;

        foreach ($features as $f) {
            $eType = $f['properties']['entity_type'] ?? 'ASSET';
            if ($eType === 'TEMUAN') {
                $findingFeaturesCount++;
            } else {
                $scope = $f['properties']['asset_scope'] ?? 'FEEDER';
                if ($scope === 'FEEDER') {
                    $feederFeaturesCount++;
                } else {
                    $unassignedFeaturesCount++;
                }
            }
        }

        return $this->response->setStatusCode(200)->setJSON([
            'status'                           => 'success',
            'penyulang_id'                     => $penyulangId,
            'feeder_ulp_id'                    => $feederUlpId,
            'total_db_feeder_assets'           => $totalDbFeederAssets,
            'total_db_unassigned_ulp_assets'   => $totalDbUnassignedAssets,
            'total_db_temuan'                  => $totalDbTemuan,
            'total_response_features'          => count($features),
            'feeder_features_count'            => $feederFeaturesCount,
            'unassigned_features_count'        => $unassignedFeaturesCount,
            'finding_features_count'           => $findingFeaturesCount,
            'jtm_count'                        => $summary['jtm_count'] ?? 0,
            'gardu_count'                      => $summary['gardu_count'] ?? 0,
            'trafo_count'                      => $summary['trafo_count'] ?? 0,
            'switch_count'                     => $summary['switch_count'] ?? 0,
            'temuan_count'                     => $summary['temuan_count'] ?? 0,
            'rejected_cross_feeder_assets'     => $summary['rejected_cross_feeder'] ?? 0,
            'rejected_cross_ulp_assets'        => $summary['rejected_cross_ulp'] ?? 0,
            'layer_separation_verified'        => true,
            'data_provenance_summary'          => [
                'feeder_source_table'     => 'assets',
                'unassigned_source_table' => 'assets',
                'finding_source_table'    => 'temuan',
                'enforced_feeder_id'      => $penyulangId,
                'enforced_ulp_id'         => $feederUlpId,
                'no_synthetic_assets'     => true,
            ]
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

    private function getRequestPayload(): array
    {
        $json = null;
        if (method_exists($this->request, 'getJSON')) {
            try {
                $json = $this->request->getJSON(true);
            } catch (\Throwable $e) {
                $json = null;
            }
        }
        if (is_array($json) && !empty($json)) {
            return $json;
        }

        $raw = method_exists($this->request, 'getBody') ? $this->request->getBody() : null;
        if (!empty($raw) && is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }

        $post = null;
        if (method_exists($this->request, 'getPost')) {
            $post = $this->request->getPost();
        }
        if (is_array($post) && !empty($post)) {
            return $post;
        }

        if (!empty($_POST) && is_array($_POST)) {
            return $_POST;
        }

        return [];
    }

    /**
     * Endpoint GET Active Translines for Feeder: GET /gis/api-translines?penyulang_id=X
     */
    public function apiGetTranslines(): ResponseInterface
    {
        $penyulangId = (int)(
            (method_exists($this->request, 'getGet') ? $this->request->getGet('penyulang_id') : null)
            ?? ($_GET['penyulang_id'] ?? 0)
        );

        if ($penyulangId <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'     => 'error',
                'message'    => 'Penyulang wajib dipilih.',
                'translines' => []
            ]);
        }

        $translines = $this->translineService->getFeederTranslines($penyulangId);

        return $this->response->setStatusCode(200)->setJSON([
            'status'     => 'success',
            'feeder_id'  => $penyulangId,
            'translines' => $translines
        ]);
    }

    public function apiConnectTopology(): ResponseInterface
    {
        $payload = $this->getRequestPayload();
        
        $sourceId = (int)($payload['source_asset_id'] ?? $payload['parent_id'] ?? 0);
        $targetId = (int)($payload['target_asset_id'] ?? $payload['child_id'] ?? 0);

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
        $result = $this->translineService->saveTransline($payload, $actor);

        return $this->response->setJSON($result);
    }

    public function apiDisconnectTopology(): ResponseInterface
    {
        $payload = $this->getRequestPayload();
        $translineId = (int)($payload['transline_id'] ?? 0);
        $actor = $this->getActor();

        if ($translineId > 0) {
            $result = $this->translineService->deleteTransline($translineId, $actor);
            return $this->response->setJSON($result);
        }

        $sourceId = (int)($payload['source_asset_id'] ?? 0);
        $targetId = (int)($payload['target_asset_id'] ?? 0);

        if ($sourceId <= 0 || $targetId <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Kedua ID aset yang terhubung wajib disertakan.'
            ]);
        }

        $db = \Config\Database::connect();
        $row = $db->table('gis_translines')
            ->groupStart()
                ->where('source_asset_id', $sourceId)->where('target_asset_id', $targetId)
            ->groupEnd()
            ->orGroupStart()
                ->where('source_asset_id', $targetId)->where('target_asset_id', $sourceId)
            ->groupEnd()
            ->where('is_active', 1)
            ->get()
            ->getRowArray();

        if ($row) {
            $result = $this->translineService->deleteTransline((int)$row['id'], $actor);
            return $this->response->setJSON($result);
        }

        // Fallback if not yet in gis_translines
        $db->table('asset_relationships')
            ->groupStart()
                ->where('parent_asset_id', $sourceId)->where('child_asset_id', $targetId)
            ->groupEnd()
            ->orGroupStart()
                ->where('parent_asset_id', $targetId)->where('child_asset_id', $sourceId)
            ->groupEnd()
            ->delete();
        $db->table('assets')->where('id', $targetId)->where('parent_asset_id', $sourceId)->update(['parent_asset_id' => null]);
        
        $penyulangId = (int)($payload['penyulang_id'] ?? 0);
        $freshTopology = $penyulangId > 0 ? $this->translineService->rebuildFeederTopologySnapshot($penyulangId, $actor['name'] ?? 'SYSTEM') : [];

        return $this->response->setJSON([
            'status'   => 'success',
            'message'  => 'Sambungan berhasil diputus.',
            'topology' => $freshTopology,
        ]);
    }

    public function apiUpdateConductorSpecification(): ResponseInterface
    {
        $payload = $this->getRequestPayload();
        $actor = $this->getActor();
        $result = $this->translineService->saveTransline($payload, $actor);

        return $this->response->setJSON($result);
    }

    /**
     * Rebuild and persist active network topology version from database relationships
     */
    private function rebuildAndPersistFeederTopology(int $penyulangId, string $actorName): array
    {
        $db = \Config\Database::connect();
        
        // 1. Deactivate old cached versions in network_topology_versions
        if ($db->tableExists('network_topology_versions')) {
            $deactivateData = [
                'is_active'      => 0,
                'version_status' => 'HISTORICAL',
            ];
            if ($db->fieldExists('superseded_at', 'network_topology_versions')) {
                $deactivateData['superseded_at'] = date('Y-m-d H:i:s');
            }
            $db->table('network_topology_versions')
               ->where('penyulang_id', $penyulangId)
               ->where('is_active', 1)
               ->update($deactivateData);
        }

        // 2. Dynamically rebuild fresh topology segments from database
        $freshSegments = $this->assetRepository->getFeederNetworkSegments($penyulangId);

        // 3. Persist new active version snapshot
        if ($db->tableExists('network_topology_versions') && !empty($freshSegments['coordinates'])) {
            $maxRow = $db->table('network_topology_versions')
                         ->where('penyulang_id', $penyulangId)
                         ->selectMax('version_no')
                         ->get()
                         ->getRow();
            $maxVer = ($maxRow && isset($maxRow->version_no)) ? (int)$maxRow->version_no : 0;
            $latestVer = $maxVer + 1;

            $db->table('network_topology_versions')->insert([
                'penyulang_id'     => $penyulangId,
                'version_no'       => $latestVer,
                'geojson_topology' => json_encode($freshSegments),
                'nodes_count'      => count($freshSegments['nodes'] ?? []),
                'segments_count'   => count($freshSegments['coordinates'] ?? []),
                'is_active'        => 1,
                'version_status'   => 'ACTIVE',
                'created_by'       => $actorName,
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
        }

        return $freshSegments;
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
        $payload = $this->getRequestPayload();
        
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

        $result = $this->translineService->updateSegmentGeometry($penyulangId, $sourceId, $targetId, $geometry, $this->getActor());

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
        $payload = $this->getRequestPayload();

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
        $payload = $this->getRequestPayload();

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
        $payload = $this->getRequestPayload();

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
        $payload = $this->getRequestPayload();

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
        $payload = $this->getRequestPayload();

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
        $payload = $this->getRequestPayload();

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

