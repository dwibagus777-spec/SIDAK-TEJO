<?php

namespace App\Controllers;

use App\Services\EccService;

class ExecutiveCommandCenter extends BaseController
{
    private EccService $service;

    public function __construct()
    {
        $this->service = new EccService();
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

        $data = $this->service->getCommandCenterData($ulpIdFilter);

        return view('ecc/index', array_merge($data, [
            'userRole' => $role,
            'userName' => session()->get('user_name') ?: 'Executive',
            'isTvMode' => false,
        ]));
    }

    public function tvMode()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($userUlpId)) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $data = $this->service->getCommandCenterData($ulpIdFilter);

        return view('ecc/index', array_merge($data, [
            'userRole' => $role,
            'userName' => session()->get('user_name') ?: 'Smart TV Wall',
            'isTvMode' => true,
        ]));
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

        $data = $this->service->getCommandCenterData($ulpIdFilter);

        return $this->response->setStatusCode(200)->setJSON([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Realtime Server-Sent Events (SSE) Stream Endpoint for Video Wall
     */
    public function sseStream()
    {
        response()->setHeader('Content-Type', 'text/event-stream')
                  ->setHeader('Cache-Control', 'no-cache')
                  ->setHeader('Connection', 'keep-alive')
                  ->setHeader('X-Accel-Buffering', 'no');

        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($userUlpId)) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $data = $this->service->getCommandCenterData($ulpIdFilter);

        echo "event: eccUpdate\n";
        echo "data: " . json_encode($data) . "\n\n";
        flush();
        exit();
    }
}
