<?php

namespace App\Controllers;

class Setting extends BaseController
{
    public function index()
    {
        if (!check_role(['administrator', 'admin_ulp'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Hanya Administrator yang memiliki akses ke halaman ini.');
        }

        $data = [
            'title' => 'Pengaturan Kata-Kata Motivasi Harian',
            'announcement' => get_daily_announcement()
        ];
        return view('setting/announcement', $data);
    }

    public function updateAnnouncement()
    {
        if (!check_role(['administrator', 'admin_ulp'])) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['success' => false, 'message' => 'Hanya Administrator yang memiliki akses untuk mengubah kata-kata motivasi.']);
            }
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $session = session();
        
        // Support GET, POST, or JSON body
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

        if (empty($message)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => false, 
                    'message' => 'Kata-kata motivasi harian tidak boleh kosong.'
                ]);
            }
            return redirect()->back()->with('error', 'Kata-kata motivasi harian tidak boleh kosong.');
        }

        $data = [
            'message' => $message,
            'updated_by' => $session->get('user_name') ?: 'User',
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $paths = [
            defined('WRITEPATH') ? WRITEPATH : null,
            defined('ROOTPATH') ? ROOTPATH . 'writable/' : null,
            defined('FCPATH') ? FCPATH . '../writable/' : null,
            __DIR__ . '/../../writable/',
            'e:/XAMPP/htdocs/SIDAK TEJO/writable/'
        ];

        $saved = false;
        foreach ($paths as $dir) {
            if ($dir) {
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                $filePath = $dir . 'announcement.json';
                $res = @file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
                if ($res !== false) {
                    $saved = true;
                }
            }
        }

        if (!$saved) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => false, 
                    'message' => 'Gagal menyimpan file pengumuman pada server.'
                ]);
            }
            return redirect()->back()->with('error', 'Gagal menyimpan file pengumuman pada server.');
        }

        try {
            log_activity('UPDATE_ANNOUNCEMENT', 'Memperbarui kata-kata motivasi harian: ' . mb_strimwidth($message, 0, 60, '...'));
        } catch (\Throwable $e) {
            // Ignore log activity errors
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true, 
                'message' => 'Kata-kata motivasi harian berhasil diperbarui!',
                'announcement' => $message
            ]);
        }

        return redirect()->to(site_url('setting/announcement'))->with('success', 'Kata-kata motivasi harian berhasil diperbarui!');
    }
}
