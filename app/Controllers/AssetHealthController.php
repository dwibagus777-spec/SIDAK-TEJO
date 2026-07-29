<?php

namespace App\Controllers;

use App\Services\AssetHealthService;

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
}
