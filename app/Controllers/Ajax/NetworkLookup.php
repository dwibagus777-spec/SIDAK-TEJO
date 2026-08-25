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
}
