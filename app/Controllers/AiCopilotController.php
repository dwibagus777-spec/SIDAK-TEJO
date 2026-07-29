<?php

namespace App\Controllers;

use App\Services\AiCopilotService;

class AiCopilotController extends BaseController
{
    private AiCopilotService $service;

    public function __construct()
    {
        $this->service = new AiCopilotService();
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

        return view('ai_copilot/index', [
            'userRole' => $role,
            'userName' => session()->get('user_name') ?: 'User',
            'userUlpId' => $ulpIdFilter,
        ]);
    }

    public function ask()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($userUlpId)) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $prompt = trim((string)$this->request->getPost('prompt'));
        if (empty($prompt)) {
            return $this->response->setJSON([
                'type' => 'CARD',
                'title' => 'Silakan Masukkan Pertanyaan',
                'body' => 'Ketikkan pertanyaan atau gunakan tombol suara Voice AI.',
                'confidence' => 100,
            ]);
        }

        $response = $this->service->processPrompt($prompt, $role, $ulpIdFilter, session()->get('user_name') ?: 'User');

        return $this->response->setJSON($response);
    }
}
