<?php

namespace App\Controllers;

use App\Services\JtmConstructionBomService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * JTM Construction Taxonomy & Bill of Materials Controller (CR-07 Phase 2)
 *
 * Responsibilities:
 * - Serve JTM Construction Taxonomy & BOM Workspace UI.
 * - Serve JSON APIs for canonical materials, BOM mappings, field alias resolution, and work order estimation.
 */
class JtmConstructionBomController extends BaseController
{
    protected JtmConstructionBomService $bomService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->bomService = new JtmConstructionBomService();
    }

    /**
     * Render JTM Construction & BOM Workspace Dashboard.
     */
    public function index()
    {
        $summary = $this->bomService->getWorkspaceSummary();

        return view('bom/index', [
            'title'   => 'JTM Construction Taxonomy & BOM Intelligence | SIDAK TEJO',
            'summary' => $summary,
        ]);
    }

    /**
     * API: Get BOM Workspace Summary.
     * GET /api/bom/summary
     */
    public function apiSummary(): ResponseInterface
    {
        $data = $this->bomService->getWorkspaceSummary();
        return $this->response->setJSON($data);
    }

    /**
     * API: Get Canonical Materials List.
     * GET /api/bom/materials
     */
    public function apiMaterials(): ResponseInterface
    {
        $summary = $this->bomService->getWorkspaceSummary();
        return $this->response->setJSON([
            'status'    => 'success',
            'total'     => count($summary['materials']),
            'materials' => array_values($summary['materials']),
        ]);
    }

    /**
     * API: Get JTM Constructions List.
     * GET /api/bom/constructions
     */
    public function apiConstructions(): ResponseInterface
    {
        $summary = $this->bomService->getWorkspaceSummary();
        return $this->response->setJSON([
            'status'        => 'success',
            'total'         => count($summary['constructions']),
            'constructions' => array_values($summary['constructions']),
        ]);
    }

    /**
     * API: Get BOM Detail by Construction Code.
     * GET /api/bom/detail/(:segment)
     */
    public function apiBomDetail(string $code): ResponseInterface
    {
        $code = strtoupper(trim($code));
        $summary = $this->bomService->getWorkspaceSummary();
        $bom = $summary['boms'][$code] ?? null;

        if (!$bom) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => "BOM for construction code '{$code}' not found.",
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'bom'    => $bom,
        ]);
    }

    /**
     * API: Resolve Informal Field Alias to Canonical Material.
     * POST /api/bom/resolve-alias
     */
    public function apiResolveAlias(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $term = $json['term'] ?? '';

        $result = $this->bomService->resolveFieldAlias($term);
        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }

    /**
     * API: Estimate Material Requirements for Work Order.
     * POST /api/bom/estimate
     */
    public function apiEstimate(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $payload = $json['payload'] ?? $json;
        $actor   = $json['actor'] ?? [
            'actor_id'   => 1,
            'actor_name' => 'SUPERVISOR_TEKNIK_DISTRIBUSI',
            'actor_nip'  => '198607122010011002',
            'actor_role' => 'TECHNICAL_SUPERVISOR',
        ];

        $result = $this->bomService->estimateWorkOrderMaterials($payload, $actor);
        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }
}
