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

        if ($format === 'json') {
            return $this->response->setHeader('Content-Type', 'application/json')
                ->setHeader('Content-Disposition', 'attachment; filename="ml_dataset_' . date('Ymd_His') . '.json"')
                ->setBody($content);
        }

        return $this->response->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', 'attachment; filename="ml_dataset_' . date('Ymd_His') . '.csv"')
            ->setBody($content);
    }

    public function recommendation()
    {
        $input = [
            'jenis_temuan'     => $this->request->getVar('jenis_temuan'),
            'prioritas'        => $this->request->getVar('prioritas'),
            'potensi_gangguan' => $this->request->getVar('potensi_gangguan'),
            'pelaksana'        => $this->request->getVar('pelaksana'),
            'detail_temuan'    => $this->request->getVar('detail_temuan'),
        ];

        $recService = new \App\Services\SmartRecommendationService();
        $recommendation = $recService->getRecommendation($input);

        return $this->response->setStatusCode(200)->setJSON([
            'success' => true,
            'data'    => $recommendation
        ]);
    }
}
