<?php

namespace App\Controllers\Ajax;

use App\Controllers\BaseController;
use App\Services\NetworkLookupService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * MNF-01: Master Network Fabric Lookup API Controller
 * Provides decoupled, read-only 4-level canonical master lookup:
 * Level 1: ULP
 * Level 2: PENYULANG
 * Level 3: SECTION
 * Level 4: ASSET
 */
class NetworkLookup extends BaseController
{
    protected NetworkLookupService $service;

    public function __construct()
    {
        $this->service = new NetworkLookupService();
    }

    /**
     * Level 1: Get all active ULPs.
     * GET /ajax/network/ulp
     */
    public function ulp(): ResponseInterface
    {
        try {
            $data = $this->service->getUlps();

            return $this->response
                ->setStatusCode(200)
                ->setContentType('application/json')
                ->setJSON($data);

        } catch (\Throwable $e) {
            log_message('error', '[NETWORK LOOKUP ULP] {message}', ['message' => $e->getMessage()]);

            return $this->response
                ->setStatusCode(500)
                ->setContentType('application/json')
                ->setJSON([
                    'success' => false,
                    'message' => 'Failed to load ULP data: ' . $e->getMessage()
                ]);
        }
    }

    /**
     * Level 2: Get active Feeders (Penyulang) by ULP ID.
     * GET /ajax/network/penyulang/{ulpId} or GET /ajax/network/penyulang?ulp_id={ulpId}
     */
    public function penyulang($ulpId = null): ResponseInterface
    {
        try {
            $id = (int)($ulpId ?? $this->request->getGet('ulp_id') ?? $this->request->getGet('id') ?? ($_GET['ulp_id'] ?? ($_GET['id'] ?? 0)));

            if ($id <= 0) {
                return $this->response
                    ->setStatusCode(200)
                    ->setContentType('application/json')
                    ->setJSON([]);
            }

            $data = $this->service->getPenyulangsByUlp($id);

            return $this->response
                ->setStatusCode(200)
                ->setContentType('application/json')
                ->setJSON($data);

        } catch (\Throwable $e) {
            log_message('error', '[NETWORK LOOKUP PENYULANG] {message}', ['message' => $e->getMessage()]);

            return $this->response
                ->setStatusCode(500)
                ->setContentType('application/json')
                ->setJSON([
                    'success' => false,
                    'message' => 'Failed to load feeder data: ' . $e->getMessage()
                ]);
        }
    }

    /**
     * Level 3: Get active Sections by Feeder (Penyulang) ID.
     * GET /ajax/network/section/{penyulangId} or GET /ajax/network/section?penyulang_id={penyulangId}
     */
    public function section($penyulangId = null): ResponseInterface
    {
        try {
            $id = (int)($penyulangId ?? $this->request->getGet('penyulang_id') ?? $this->request->getGet('id_penyulang') ?? $this->request->getGet('id') ?? ($_GET['penyulang_id'] ?? ($_GET['id_penyulang'] ?? ($_GET['id'] ?? 0))));

            if ($id <= 0) {
                return $this->response
                    ->setStatusCode(200)
                    ->setContentType('application/json')
                    ->setJSON([]);
            }

            $data = $this->service->getSectionsByPenyulang($id);

            return $this->response
                ->setStatusCode(200)
                ->setContentType('application/json')
                ->setJSON($data);

        } catch (\Throwable $e) {
            log_message('error', '[NETWORK LOOKUP SECTION] {message}', ['message' => $e->getMessage()]);

            return $this->response
                ->setStatusCode(500)
                ->setContentType('application/json')
                ->setJSON([
                    'success' => false,
                    'message' => 'Failed to load section data: ' . $e->getMessage()
                ]);
        }
    }

    /**
     * Level 4: Get Assets under Section with optional filters.
     * GET /ajax/network/asset/{sectionId} or GET /ajax/network/asset?section_id={sectionId}&type={type}
     */
    public function asset($sectionId = null): ResponseInterface
    {
        try {
            $id = (int)($sectionId ?? $this->request->getGet('section_id') ?? $this->request->getGet('id') ?? ($_GET['section_id'] ?? ($_GET['id'] ?? 0)));

            $filters = $this->request->getGet() ?? [];
            if ($id > 0) {
                $filters['section_id'] = $id;
            }

            $data = $this->service->getAssets($filters);

            return $this->response
                ->setStatusCode(200)
                ->setContentType('application/json')
                ->setJSON($data);

        } catch (\Throwable $e) {
            log_message('error', '[NETWORK LOOKUP ASSET] {message}', ['message' => $e->getMessage()]);

            return $this->response
                ->setStatusCode(500)
                ->setContentType('application/json')
                ->setJSON([
                    'success' => false,
                    'message' => 'Failed to load asset data: ' . $e->getMessage()
                ]);
        }
    }

    /**
     * Level 4 Universal: Master Assets Query Endpoint.
     * GET /ajax/network/assets?ulp_id=1&penyulang_id=15&section_id=46&type=KUBIKEL
     */
    public function assets(): ResponseInterface
    {
        try {
            $filters = $this->request->getGet() ?? [];
            $data = $this->service->getAssets($filters);

            return $this->response
                ->setStatusCode(200)
                ->setContentType('application/json')
                ->setJSON($data);

        } catch (\Throwable $e) {
            log_message('error', '[NETWORK LOOKUP ASSETS UNIVERSAL] {message}', ['message' => $e->getMessage()]);

            return $this->response
                ->setStatusCode(500)
                ->setContentType('application/json')
                ->setJSON([
                    'success' => false,
                    'message' => 'Failed to load assets: ' . $e->getMessage()
                ]);
        }
    }

    /**
     * MAP-02: Authoritative Read-Only Asset Context Drawer API
     * GET /ajax/network/asset-context/{id}
     */
    public function assetContext($id = null): ResponseInterface
    {
        try {
            $assetId = (int)($id ?? $this->request->getGet('asset_id') ?? $this->request->getGet('id') ?? 0);
            if ($assetId <= 0) {
                return $this->response
                    ->setStatusCode(400)
                    ->setContentType('application/json')
                    ->setJSON([
                        'status'  => 'INVALID_ASSET',
                        'message' => 'Asset ID tidak valid.',
                    ]);
            }

            $userUlpId = null;
            $userRole  = '';
            try {
                if (session_status() === PHP_SESSION_ACTIVE || !headers_sent()) {
                    $session = session();
                    $userUlpId = $session->get('user_ulp_id') ? (int)$session->get('user_ulp_id') : null;
                    $userRole  = (string)($session->get('user_role') ?? $session->get('role') ?? '');
                }
            } catch (\Throwable $se) {
                // Ignore CLI session initiation errors
            }

            $workingSectionId = $this->request->getGet('working_section_id');
            $workingSectionId = ($workingSectionId !== null && is_numeric($workingSectionId)) ? (int)$workingSectionId : null;

            $workingConstructionId = $this->request->getGet('working_construction_id') ?? $this->request->getGet('working_construction_type_id');
            $workingConstructionId = ($workingConstructionId !== null && is_numeric($workingConstructionId)) ? (int)$workingConstructionId : null;

            $contextService = new \App\Services\AssetContextService();
            $context = $contextService->getAssetContext($assetId, $userUlpId, $userRole, $workingSectionId, $workingConstructionId);

            $statusCode = 200;
            if ($context['status'] === 'FORBIDDEN') {
                $statusCode = 403;
            } elseif ($context['status'] === 'INVALID_ASSET') {
                $statusCode = 404;
            } elseif (in_array($context['status'], ['INVALID_WORKING_SECTION', 'INVALID_WORKING_CONSTRUCTION'])) {
                $statusCode = 400;
            }

            return $this->response
                ->setStatusCode($statusCode)
                ->setContentType('application/json')
                ->setJSON($context);

        } catch (\Throwable $e) {
            log_message('error', '[MAP02_ASSET_CONTEXT_ERR] {message}', ['message' => $e->getMessage()]);

            return $this->response
                ->setStatusCode(500)
                ->setContentType('application/json')
                ->setJSON([
                    'status'  => 'ERROR',
                    'message' => 'Kendala sistem saat memuat konteks aset: ' . $e->getMessage(),
                ]);
        }
    }

    /**
     * MAP-02C: Active Canonical Construction Types Lookup Endpoint
     * GET /ajax/network/constructions
     * Returns canonical construction types excluding draft/provisional kubikel items.
     * Guaranteed Read-Only (0 writes).
     */
    public function constructions(): ResponseInterface
    {
        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('construction_types')) {
                return $this->response
                    ->setStatusCode(200)
                    ->setContentType('application/json')
                    ->setJSON([]);
            }

            $builder = $db->table('construction_types');

            if ($db->fieldExists('is_active', 'construction_types')) {
                $builder->where('is_active', 1);
            }
            if ($db->fieldExists('approval_status', 'construction_types')) {
                $builder->where('approval_status !=', 'DRAFT');
            }

            $rows = $builder->get()->getResultArray();
            $results = [];

            foreach ($rows as $row) {
                $cFamily = strtoupper(trim((string)($row['construction_family'] ?? '')));
                $cCode   = strtoupper(trim((string)($row['construction_code'] ?? ($row['code'] ?? ''))));
                $cName   = (string)($row['construction_name'] ?? ($row['name'] ?? $cCode));

                // Provisional Kubikel Firewall: block draft/provisional constructions
                if ($cFamily === 'GARDU_KUBIKEL' || str_contains($cCode, 'KUBIKEL')) {
                    continue;
                }

                $results[] = [
                    'id'                  => (int)$row['id'],
                    'code'                => $cCode,
                    'name'                => $cName,
                    'construction_family' => $cFamily ?: 'JTM',
                    'voltage_level'       => (string)($row['voltage_level'] ?? '20kV'),
                ];
            }

            return $this->response
                ->setStatusCode(200)
                ->setContentType('application/json')
                ->setJSON($results);

        } catch (\Throwable $e) {
            log_message('error', '[NETWORK LOOKUP CONSTRUCTIONS] {message}', ['message' => $e->getMessage()]);

            return $this->response
                ->setStatusCode(500)
                ->setContentType('application/json')
                ->setJSON([
                    'success' => false,
                    'message' => 'Failed to load construction types: ' . $e->getMessage()
                ]);
        }
    }

    /**
     * MAP-03: Read-Only Location Context Endpoint
     * Resolves network context (ULP -> Feeder -> Section) and nearest asset suggestion from GPS.
     */
    public function locationContext()
    {
        try {
            $lat = $this->request->getGet('lat');
            $lng = $this->request->getGet('lng');
            $accuracy = $this->request->getGet('accuracy');

            if ($lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng)) {
                return $this->response
                    ->setStatusCode(400)
                    ->setContentType('application/json')
                    ->setJSON([
                        'status'  => 'INVALID_COORDINATES',
                        'message' => 'Parameter koordinat latitude dan longitude wajib berupa angka valid.',
                    ]);
            }

            $userUlpId = null;
            $userRole  = '';
            try {
                if (session_status() === PHP_SESSION_ACTIVE || !headers_sent()) {
                    $session = session();
                    $userUlpId = $session->get('user_ulp_id') ? (int)$session->get('user_ulp_id') : null;
                    $userRole  = (string)($session->get('user_role') ?? $session->get('role') ?? '');
                }
            } catch (\Throwable $se) {
                // Ignore CLI session initiation errors
            }

            $service = new \App\Services\LocationContextService();
            $result = $service->resolveContext(
                (float)$lat,
                (float)$lng,
                $accuracy !== null && is_numeric($accuracy) ? (float)$accuracy : null,
                $userUlpId,
                $userRole
            );

            $statusCode = 200;
            if ($result['status'] === 'FORBIDDEN') {
                $statusCode = 403;
            } elseif ($result['status'] === 'INVALID_COORDINATES') {
                $statusCode = 400;
            }

            return $this->response
                ->setStatusCode($statusCode)
                ->setContentType('application/json')
                ->setJSON($result);

        } catch (\Throwable $e) {
            log_message('error', '[MAP03_LOCATION_CONTEXT_ERR] {message}', ['message' => $e->getMessage()]);

            return $this->response
                ->setStatusCode(500)
                ->setContentType('application/json')
                ->setJSON([
                    'status'  => 'ERROR',
                    'message' => 'Kendala sistem saat mendeteksi konteks lokasi: ' . $e->getMessage(),
                ]);
        }
    }
}
