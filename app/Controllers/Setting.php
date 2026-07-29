<?php

namespace App\Controllers;

use App\Services\SystemSettingService;

class Setting extends BaseController
{
    private SystemSettingService $settingService;

    public function __construct()
    {
        $this->settingService = new SystemSettingService();
    }

    public function index()
    {
        if (!check_role(['administrator', 'admin_ulp'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Hanya Administrator yang memiliki akses ke halaman ini.');
        }

        $allSettings = $this->settingService->getAll();
        $auditHistory = $this->settingService->getAuditHistory();

        $data = [
            'title'              => 'Pengaturan Pengumuman & Sistem',
            'announcement'       => $this->settingService->get('daily_motivation'),
            'daily_motivation'   => $this->settingService->get('daily_motivation'),
            'running_text'       => $this->settingService->get('running_text'),
            'dashboard_message'  => $this->settingService->get('dashboard_message'),
            'dashboard_title'    => $this->settingService->get('dashboard_title'),
            'dashboard_subtitle' => $this->settingService->get('dashboard_subtitle'),
            'allSettings'        => $allSettings,
            'auditHistory'       => $auditHistory,
        ];

        return view('setting/announcement', $data);
    }

    public function updateAnnouncement()
    {
        if (!check_role(['administrator', 'admin_ulp'])) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['success' => false, 'message' => 'Akses ditolak.']);
            }
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $session = session();
        $userName = $session->get('user_name') ?: 'Administrator';

        // Support both single "message" update and full form update
        $message = trim((string)(
            $this->request->getPost('message') ?: 
            $this->request->getGet('message') ?: 
            $this->request->getVar('message')
        ));
        
        if (empty($message)) {
            $jsonInput = $this->request->getJSON(true);
            if (!empty($jsonInput['message'])) {
                $message = trim((string)$jsonInput['message']);
            }
        }

        // Check if full settings form submitted
        $dailyMotivation = trim((string)$this->request->getPost('daily_motivation'));
        $runningText     = trim((string)$this->request->getPost('running_text'));
        $dashboardMessage= trim((string)$this->request->getPost('dashboard_message'));
        $dashboardTitle  = trim((string)$this->request->getPost('dashboard_title'));
        $dashboardSub    = trim((string)$this->request->getPost('dashboard_subtitle'));

        // If single message param passed
        if (!empty($message) && empty($dailyMotivation)) {
            $dailyMotivation = $message;
        }

        if (empty($dailyMotivation) && empty($runningText) && empty($dashboardMessage)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => false, 
                    'message' => 'Pengaturan tidak boleh kosong.'
                ]);
            }
            return redirect()->back()->with('error', 'Pengaturan tidak boleh kosong.');
        }

        // Save to Database via SystemSettingService
        if (!empty($dailyMotivation)) {
            $this->settingService->set('daily_motivation', $dailyMotivation, $userName);
        }
        if (!empty($runningText)) {
            $this->settingService->set('running_text', $runningText, $userName);
        }
        if (!empty($dashboardMessage)) {
            $this->settingService->set('dashboard_message', $dashboardMessage, $userName);
        }
        if (!empty($dashboardTitle)) {
            $this->settingService->set('dashboard_title', $dashboardTitle, $userName);
        }
        if (!empty($dashboardSub)) {
            $this->settingService->set('dashboard_subtitle', $dashboardSub, $userName);
        }

        try {
            log_activity('UPDATE_SYSTEM_SETTINGS', 'Memperbarui motivasi harian & sistem: ' . mb_strimwidth($dailyMotivation ?: $message, 0, 60, '...'));
        } catch (\Throwable $e) {
            // Ignore audit log exceptions
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success'      => true, 
                'message'      => 'Pengaturan & Motivasi Harian berhasil tersimpan permanen di database!',
                'announcement' => $dailyMotivation ?: $message,
                'updated_by'   => $userName,
                'updated_at'   => indo_datetime(date('Y-m-d H:i:s'))
            ]);
        }

        return redirect()->to(site_url('setting/announcement'))->with('success', 'Pengaturan & Motivasi Harian berhasil tersimpan permanen di database!');
    }
}
