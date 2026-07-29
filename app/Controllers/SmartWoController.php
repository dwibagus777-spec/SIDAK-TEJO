<?php

namespace App\Controllers;

use App\Services\SmartWorkOrderService;

class SmartWoController extends BaseController
{
    private SmartWorkOrderService $service;

    public function __construct()
    {
        $this->service = new SmartWorkOrderService();
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

        $analytics = $this->service->getSmartWoAnalytics($ulpIdFilter);

        return view('smart_wo/index', [
            'analytics' => $analytics,
            'userRole'  => $role,
            'userName'  => session()->get('user_name') ?: 'User',
        ]);
    }
}
