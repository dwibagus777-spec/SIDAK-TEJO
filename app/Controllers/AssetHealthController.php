<?php

namespace App\Controllers;

use App\Services\AssetHealthService;
use App\Services\HealthIndexEngine;

class AssetHealthController extends BaseController
{
    private AssetHealthService $service;

    public function __construct()
    {
        $this->service = new AssetHealthService();
    }

    public function index()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($userUlpId)) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $analytics = $this->service->getHealthAnalytics($ulpIdFilter);

        return view('asset_health/index', [
            'analytics' => $analytics,
            'userRole'  => $role,
            'userName'  => session()->get('user_name') ?: 'User',
        ]);
    }

    /**
     * Phase 1D: API Endpoint returning Official Persisted 3-Layer Health Index Explanation JSON for Modal UI
     */
    public function explanation(int $assetId)
    {
        try {
            $db = \Config\Database::connect();

            // Fetch Latest Persisted Audit Record
            $latestHistory = $db->table('asset_health_history')
                ->where('asset_id', $assetId)
                ->orderBy('calculated_at', 'DESC')
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()
                ->getRowArray();

            if (!$latestHistory) {
                // Fallback before initial calculation event: Generate Live Preview Calculation
                $engine = new HealthIndexEngine();
                $calcResult = $engine->calculateAssetHealthIndex($assetId, 'PREVIEW');
                return $this->response->setJSON([
                    'status'  => 'success',
                    'success' => true,
                    'is_live' => true,
                    'data'    => $calcResult,
                ]);
            }

            return $this->response->setJSON([
                'status'  => 'success',
                'success' => true,
                'is_live' => false,
                'data'    => [
                    'asset_id'            => (int)$assetId,
                    'base_score'          => (float)$latestHistory['base_score'],
                    'total_deduction'     => (float)$latestHistory['total_deduction'],
                    'final_score'         => (float)$latestHistory['health_score'],
                    'category'            => $latestHistory['health_category'],
                    'explanation_json'    => json_decode($latestHistory['explanation_json'], true) ?? [],
                    'rules_snapshot_json' => json_decode($latestHistory['rules_snapshot_json'], true) ?? [],
                    'calculation_hash'    => $latestHistory['calculation_hash'],
                    'engine_version'      => $latestHistory['engine_version'],
                    'trigger_event'       => $latestHistory['trigger_event'],
                    'calculated_at'       => $latestHistory['calculated_at'],
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
