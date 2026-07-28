<?php

namespace App\Controllers;

use App\Services\BackupService;

class Backup extends BaseController
{
    private BackupService $backupService;

    public function __construct()
    {
        $this->backupService = new BackupService();
    }

    public function index()
    {
        if (!check_role(['administrator', 'admin_ulp'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Hanya Administrator yang memiliki akses ke Manajemen Backup.');
        }

        $data = [
            'title'   => 'Manajemen Backup & Restore Sistem',
            'backups' => $this->backupService->getBackupList(),
            'cronUrl' => site_url('backup/cron?key=sidaktejo_cron_secret')
        ];

        return view('setting/backup', $data);
    }

    public function create()
    {
        if (!check_role(['administrator', 'admin_ulp'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $filename = $this->backupService->createBackupZip();

        if ($filename) {
            return redirect()->to(site_url('backup'))->with('success', 'Full Backup ZIP (' . $filename . ') berhasil dibuat!');
        }

        return redirect()->to(site_url('backup'))->with('error', 'Gagal membuat backup ZIP.');
    }

    public function download(string $filename)
    {
        if (!check_role(['administrator', 'admin_ulp'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $filename = basename($filename);
        $filePath = WRITEPATH . 'backups/' . $filename;

        if (file_exists($filePath) && str_ends_with($filename, '.zip')) {
            return $this->response->download($filePath, null);
        }

        return redirect()->to(site_url('backup'))->with('error', 'Berkas backup tidak ditemukan.');
    }

    public function restore()
    {
        if (!check_role(['administrator', 'admin_ulp'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $file = $this->request->getFile('backup_zip');
        if (!$file || !$file->isValid() || strtolower($file->getClientExtension()) !== 'zip') {
            return redirect()->to(site_url('backup'))->with('error', 'Pilih berkas ZIP backup yang valid (.zip).');
        }

        $tempPath = WRITEPATH . 'backups/uploaded_restore_' . time() . '.zip';
        $file->move(WRITEPATH . 'backups/', basename($tempPath));

        $res = $this->backupService->restoreFromZip($tempPath);
        @unlink($tempPath);

        if ($res['success']) {
            return redirect()->to(site_url('backup'))->with('success', $res['message']);
        }

        return redirect()->to(site_url('backup'))->with('error', $res['message']);
    }

    public function delete(string $filename)
    {
        if (!check_role(['administrator', 'admin_ulp'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        if ($this->backupService->deleteBackup($filename)) {
            return redirect()->to(site_url('backup'))->with('success', 'Berkas backup ' . esc($filename) . ' berhasil dihapus.');
        }

        return redirect()->to(site_url('backup'))->with('error', 'Gagal menghapus berkas backup.');
    }

    /**
     * Endpoint Cron Job Otomatis Harian (Hostinger Compatible)
     * URL: domain.com/backup/cron?key=sidaktejo_cron_secret
     */
    public function cron()
    {
        $key = $this->request->getGet('key') ?: $this->request->getPost('key');
        if ($key !== 'sidaktejo_cron_secret') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Invalid secret key.']);
        }

        $filename = $this->backupService->createBackupZip();
        if ($filename) {
            log_activity('CRON_BACKUP', 'Automated Daily Backup success: ' . $filename);
            return $this->response->setJSON([
                'success'  => true,
                'message'  => 'Automated daily backup created successfully.',
                'filename' => $filename,
                'time'     => date('Y-m-d H:i:s')
            ]);
        }

        return $this->response->setStatusCode(500)->setJSON([
            'success' => false,
            'message' => 'Automated backup failed.'
        ]);
    }
}
