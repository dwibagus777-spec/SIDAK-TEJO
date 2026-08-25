<?php

namespace App\Controllers;

use App\Services\AssetIntelligenceService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Asset Intelligence Controller (CR-05 Phase 2)
 *
 * Responsibilities:
 * - Serve Physical Asset Truth Layer dashboard UI.
 * - Serve read-only JSON APIs for Asset summaries and feeder topology trees.
 * - Handle staging and dry-run ingestion validation.
 * - Handle controlled batch commit with confirmation token validation.
 */
class AssetIntelligenceController extends BaseController
{
    protected AssetIntelligenceService $assetService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->assetService = new AssetIntelligenceService();
    }

    /**
     * Render Asset Explorer & Population Workspace UI.
     */
    public function index()
    {
        $summary = $this->assetService->getAssetSummary();
        $db = \Config\Database::connect();
        $masterFeeders = $db->table('penyulang')
            ->select('id, nama_penyulang, ulp_id, status')
            ->orderBy('nama_penyulang', 'ASC')
            ->get()
            ->getResultArray();

        return view('assets/index', [
            'title'         => 'Physical Asset Truth Layer & GIS Intelligence | SIDAK TEJO',
            'summary'       => $summary,
            'masterFeeders' => $masterFeeders,
        ]);
    }

    /**
     * API: Get Physical Asset Truth Summary.
     * GET /api/assets/summary
     */
    public function apiSummary(): ResponseInterface
    {
        $data = $this->assetService->getAssetSummary();
        return $this->response->setJSON($data);
    }

    /**
     * API: Get Feeder Asset Hierarchy Tree.
     * GET /api/assets/tree/(:num)
     */
    public function apiTree(int $feederId): ResponseInterface
    {
        $data = $this->assetService->getAssetTree($feederId);
        if (!$data['success']) {
            return $this->response->setStatusCode(404)->setJSON($data);
        }
        return $this->response->setJSON($data);
    }

    /**
     * API: Execute Staging & Dry-Run Ingestion Validation.
     * POST /api/assets/dry-run
     */
    public function apiDryRun(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $rows = $json['rows'] ?? [];
        $actor = $json['actor'] ?? [
            'actor_id'   => 1,
            'actor_name' => 'SUPERVISOR_ASET_JARINGAN',
            'actor_nip'  => '198709182011011003',
            'actor_role' => 'ASSET_ENGINEER',
        ];

        $result = $this->assetService->dryRunImport($rows, $actor);
        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }

    /**
     * API: Execute Controlled Commit with Confirmation Token.
     * POST /api/assets/controlled-commit
     */
    public function apiCommit(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $planId = $json['plan_id'] ?? '';
        $token  = $json['confirmation_token'] ?? '';
        $actor  = $json['actor'] ?? [
            'actor_id'   => 1,
            'actor_name' => 'MANAJER_BAGIAN_JARINGAN',
            'actor_nip'  => '198204152006041001',
            'actor_role' => 'MANAGER_UP3',
        ];

        $result = $this->assetService->controlledCommit($planId, $token, $actor);
        if (!$result['success']) {
            return $this->response->setStatusCode(422)->setJSON($result);
        }
        return $this->response->setJSON($result);
    }
}
