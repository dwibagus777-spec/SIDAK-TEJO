<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\SldTopologyReadModelService;
use App\Services\SldSemanticClassificationService;
use App\Services\SldLayoutCoordinateEngineService;

/**
 * SLD API Controller
 *
 * Exposes clean, strict read-only endpoints:
 * - GET /api/sld/feeder/(:num)          : SLD-02 Topology Graph Read Model
 * - GET /api/sld/feeder/(:num)/semantic : SLD-03 Semantic Network Hierarchy & Device Classification
 * - GET /api/sld/feeder/(:num)/layout   : SLD-04 Deterministic Layout & Coordinate Model
 *
 * Zero database writes: Delta = 0 (INSERT=0, UPDATE=0, DELETE=0, DDL=0).
 */
class SldApiController extends BaseController
{
    protected SldTopologyReadModelService $sldService;
    protected SldSemanticClassificationService $semanticService;
    protected SldLayoutCoordinateEngineService $layoutService;

    public function __construct(
        ?SldTopologyReadModelService $sldService = null,
        ?SldSemanticClassificationService $semanticService = null,
        ?SldLayoutCoordinateEngineService $layoutService = null
    ) {
        if ($sldService === null) {
            $db = null;
            try {
                $db = \Config\Database::connect();
            } catch (\Throwable $e) {
                $db = null;
            }
            $this->sldService = new SldTopologyReadModelService($db);
        } else {
            $this->sldService = $sldService;
        }

        $this->semanticService = $semanticService ?? new SldSemanticClassificationService($this->sldService);
        $this->layoutService = $layoutService ?? new SldLayoutCoordinateEngineService($this->semanticService);
    }

    /**
     * GET /api/sld/feeder/(:num)
     *
     * Returns the authoritative Topology Graph Read Model for a feeder.
     * Optional query param: ?include_gtt=1
     */
    public function getFeederTopology(int $penyulangId)
    {
        $includeGtt = (bool)($this->request->getGet('include_gtt') ?? false);
        
        $result = $this->sldService->buildFeederGraph($penyulangId, [
            'include_gtt' => $includeGtt,
        ]);

        $status = $result['status'] ?? '';
        $statusCode = ($status === 'success' || $status === 'DATA_NOT_READY') ? 200 : ($result['code'] ?? 400);

        return $this->response->setStatusCode($statusCode)->setJSON($result);
    }

    /**
     * GET /api/sld/feeder/(:num)/semantic
     *
     * Returns the authoritative Semantic Network Hierarchy & Device Classification for a feeder.
     * Optional query param: ?include_gtt=1
     */
    public function getFeederSemanticHierarchy(int $penyulangId)
    {
        $includeGtt = (bool)($this->request->getGet('include_gtt') ?? false);

        $result = $this->semanticService->buildSemanticHierarchy($penyulangId, [
            'include_gtt' => $includeGtt,
        ]);

        $status = $result['status'] ?? '';
        $statusCode = ($status === 'success' || $status === 'DATA_NOT_READY') ? 200 : ($result['code'] ?? 400);

        return $this->response->setStatusCode($statusCode)->setJSON($result);
    }

    /**
     * GET /api/sld/feeder/(:num)/layout
     *
     * Returns the authoritative Layout & Coordinate Model for a feeder.
     * Optional query param: ?include_gtt=1
     */
    public function getFeederLayout(int $penyulangId)
    {
        $includeGtt = (bool)($this->request->getGet('include_gtt') ?? false);

        $result = $this->layoutService->buildFeederLayout($penyulangId, [
            'include_gtt' => $includeGtt,
        ]);

        $status = $result['status'] ?? '';
        $statusCode = ($status === 'success' || $status === 'DATA_NOT_READY') ? 200 : ($result['code'] ?? 400);

        return $this->response->setStatusCode($statusCode)->setJSON($result);
    }

    /**
     * GET /api/sld/feeder/(:num)/fingerprint
     *
     * Returns the lightweight cryptographic SHA-256 change-detection fingerprint for a feeder.
     * Used by client-side dynamic auto-refresh polling without loading full layout.
     */
    public function getFeederFingerprint(int $penyulangId)
    {
        $result = $this->sldService->getFeederFingerprint($penyulangId);
        return $this->response->setStatusCode(200)->setJSON($result);
    }
}


