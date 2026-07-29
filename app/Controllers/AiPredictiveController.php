<?php

namespace App\Controllers;

use App\Services\PredictiveMaintenanceService;

class AiPredictiveController extends BaseController
{
    private PredictiveMaintenanceService $service;

    public function __construct()
    {
        $this->service = new PredictiveMaintenanceService();
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

        $analytics = $this->service->getPredictiveAnalyticsData($ulpIdFilter);

        return view('ai_predictive/index', [
            'analytics' => $analytics,
            'userRole'  => $role,
            'userName'  => session()->get('user_name') ?: 'User',
        ]);
    }

    public function apiData()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($userUlpId)) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $analytics = $this->service->getPredictiveAnalyticsData($ulpIdFilter);

        return $this->response->setStatusCode(200)->setJSON([
            'success'   => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'data'      => $analytics
        ]);
    }

    public function exportDataset()
    {
        $format = strtolower($this->request->getGet('format') ?: 'csv');
        $content = $this->service->exportMlDataset($format);

        $filename = 'ML_Dataset_Sidak_Tejo_' . date('Ymd_His') . '.' . $format;

        if ($format === 'json') {
            return $this->response
                ->setHeader('Content-Type', 'application/json')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->setBody($content);
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($content);
    }
}
