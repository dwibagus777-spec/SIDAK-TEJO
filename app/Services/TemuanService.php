<?php

namespace App\Services;

use App\Repositories\TemuanRepository;
use App\Repositories\TindakLanjutRepository;
use App\Services\GamificationService;
use CodeIgniter\HTTP\Files\UploadedFile;

class TemuanService
{
    private TemuanRepository $temuanRepository;
    private TindakLanjutRepository $tindakLanjutRepository;

    public function __construct()
    {
        $this->temuanRepository = new TemuanRepository();
        $this->tindakLanjutRepository = new TindakLanjutRepository();
    }

    /**
     * Kompresi Gambar Lokal Menggunakan GD Library
     */
    private function compressImage(string $sourcePath, int $quality = 75): string
    {
        if (!function_exists('imagecreatefromjpeg')) {
            return $sourcePath;
        }

        @ini_set('memory_limit', '256M');
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $image = null;

        try {
            if ($extension === 'jpg' || $extension === 'jpeg') {
                $image = @imagecreatefromjpeg($sourcePath);
            } elseif ($extension === 'png') {
                $image = @imagecreatefrompng($sourcePath);
            } elseif ($extension === 'webp') {
                $image = @imagecreatefromwebp($sourcePath);
            }

            if (!$image) {
                return $sourcePath;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            if ($width > 1920) {
                $newWidth = 1920;
                $newHeight = (int)($height * 1920 / $width);
                $resized = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resized;
            }

            $compressedPath = $sourcePath . '.compressed.jpg';
            imagejpeg($image, $compressedPath, $quality);
            imagedestroy($image);

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            if (file_exists($compressedPath) && filesize($compressedPath) < filesize($sourcePath)) {
                return $compressedPath;
            }

            @unlink($compressedPath);
        } catch (\Throwable $e) {
            log_message('warning', '[compressImage] Gagal kompresi: ' . $e->getMessage());
        }

        return $sourcePath;
    }

    /**
     * Mengunggah Berkas Foto Tunggal Ke Folder public/foto/
     */
    private function uploadPhoto(UploadedFile $file): array
    {
        $generatedName = $file->getRandomName();

        if (!$file->isValid()) {
            $errorMessage = $file->getErrorString() . ' (Code: ' . $file->getError() . ')';
            log_message('error', '[uploadPhoto] Validasi gagal: ' . $errorMessage);
            return ['name' => '', 'path' => 'error', 'error' => $errorMessage];
        }

        $destinationDir = FCPATH . 'foto/';
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0777, true);
        }

        $file->move($destinationDir, $generatedName);

        $diskPath = $destinationDir . $generatedName;
        $compressed = $this->compressImage($diskPath, 85);
        if ($compressed !== $diskPath && file_exists($compressed)) {
            @rename($compressed, $diskPath);
        }

        // Apply Watermark Metadata Overlay (Phase 38 Hotfix)
        $session = session();
        $wmService = new ImageWatermarkService();
        $wmService->applyWatermark($diskPath, [
            'up3'    => 'UP3 SIDOARJO',
            'ulp'    => $session->get('user_ulp_nama') ?: 'Sidoarjo',
            'user'   => $session->get('nama_pegawai') ?: ($session->get('user_name') ?: 'User'),
            'status' => 'INSPEKSI',
            'device' => (str_contains(strtolower($_SERVER['HTTP_USER_AGENT'] ?? ''), 'android')) ? 'Android / APK' : 'Web / Browser'
        ]);

        return ['name' => $generatedName, 'path' => 'foto/', 'error' => ''];
    }

    /**
     * Validasi Batasan Jumlah & Ekstensi Berkas Foto
     */
    private function validatePhotos(?array $files, bool $required = true): ?string
    {
        if (empty($files) || count($files) === 0 || !$files[0]->isValid()) {
            if ($required) {
                $errDetail = (!empty($files) && isset($files[0])) ? $files[0]->getErrorString() : 'File foto kosong.';
                return 'Unggah foto minimal 1 foto. Detail: ' . $errDetail;
            }
            return null;
        }

        if (count($files) > 10) {
            return 'Maksimal foto yang diunggah adalah 10 foto.';
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        foreach ($files as $file) {
            if ($file->isValid()) {
                $ext = strtolower($file->getExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions)) {
                    return 'Format file "' . $file->getName() . '" tidak diizinkan. Hanya jpg, jpeg, png, webp.';
                }
            }
        }

        return null;
    }

    /**
     * Pengunggahan Jamak Berkas Foto (Multiple Upload)
     */
    private function uploadMultiplePhotos(?array $files, bool $required = true): array
    {
        $validationError = $this->validatePhotos($files, $required);
        if ($validationError !== null) {
            return ['success' => false, 'message' => $validationError, 'names' => []];
        }

        if (empty($files)) {
            return ['success' => true, 'message' => '', 'names' => []];
        }

        $uploadedNames = [];
        foreach ($files as $file) {
            if ($file->isValid() && !$file->hasMoved()) {
                $uploaded = $this->uploadPhoto($file);
                if ($uploaded['path'] === 'error' || empty($uploaded['name'])) {
                    return [
                        'success' => false,
                        'message' => 'Gagal mengunggah foto: ' . ($uploaded['error'] ?? 'Terjadi kesalahan.'),
                        'names'   => []
                    ];
                }
                $uploadedNames[] = $uploaded['name'];
            }
        }

        if ($required && empty($uploadedNames)) {
            return [
                'success' => false,
                'message' => 'Tidak ada foto yang berhasil terunggah.',
                'names'   => []
            ];
        }

        return ['success' => true, 'message' => '', 'names' => $uploadedNames];
    }

    /**
     * Membuat Temuan Baru Beserta Pengunggahan Foto
     */
    public function createTemuan(array $data, ?array $files): array
    {
        $nomorTemuan = $this->temuanRepository->generateNomorTemuan();
        $session = session();
        $data['nomor_temuan'] = $nomorTemuan;
        $data['status'] = 'BELUM';
        $data['created_by'] = $session->get('user_id');
        $data['created_by_name'] = $session->get('nama_pegawai') ?: $session->get('user_name');
        $data['created_by_nip'] = $session->get('nip') ?: '';

        $uploadResult = $this->uploadMultiplePhotos($files, true);
        if (!$uploadResult['success']) {
            return [
                'success' => false,
                'message' => $uploadResult['message']
            ];
        }

        $data['foto'] = json_encode($uploadResult['names']);
        $data['foto_path'] = 'foto/';

        $insertId = $this->temuanRepository->insert($data);

        if ($insertId) {
            log_activity('CREATE_TEMUAN', 'Menambahkan temuan baru: ' . $nomorTemuan);
            
            // Enterprise Asset Lifecycle Hook: Update Asset Status to BERMASALAH & log history
            try {
                if (!empty($data['asset_id'])) {
                    (new \App\Services\AssetLifecycleService())->triggerTemuanCreated(
                        (int)$data['asset_id'],
                        $nomorTemuan,
                        (int)$session->get('user_id'),
                        $data['deskripsi_temuan'] ?? null
                    );
                }
            } catch (\Throwable $e) {
                log_message('warning', '[AssetLifecycle] createTemuan hook: ' . $e->getMessage());
            }

            // Phase 32 — Gamification: award points for INPUT_TEMUAN
            try {
                $userId = (int)$session->get('user_id');
                if ($userId > 0) {
                    (new GamificationService())->addPoints(
                        $userId, 'INPUT_TEMUAN',
                        'Input Temuan: ' . $nomorTemuan, $insertId, 'temuan'
                    );
                }
            } catch (\Throwable $e) {
                log_message('warning', '[Gamification] createTemuan hook: ' . $e->getMessage());
            }
            return [
                'success' => true,
                'message' => 'Temuan berhasil disimpan.',
                'id'      => $insertId
            ];
        }

        log_message('error', '[createTemuan] Gagal insert ke database.');
        return [
            'success' => false,
            'message' => 'Gagal menyimpan temuan ke database.'
        ];
    }

    /**
     * Memperbarui Data Temuan (Hotfix Edit Foto & Unlink Foto Lama)
     */
    public function updateTemuan(int $id, array $data, ?array $newFiles, bool $replaceOldPhotos = true): array
    {
        $temuan = $this->temuanRepository->find($id);
        if (!$temuan) {
            return ['success' => false, 'message' => 'Temuan tidak ditemukan.'];
        }

        $data['updated_by'] = session()->get('user_id');

        $hasNewFiles = !empty($newFiles) && isset($newFiles[0]) && $newFiles[0]->isValid();
        if ($hasNewFiles) {
            $uploadResult = $this->uploadMultiplePhotos($newFiles, false);
            if (!$uploadResult['success']) {
                return ['success' => false, 'message' => $uploadResult['message']];
            }

            if (!empty($uploadResult['names'])) {
                // Decode foto lama
                $existingPhotos = [];
                if (!empty($temuan['foto'])) {
                    if (is_array($temuan['foto'])) {
                        $existingPhotos = $temuan['foto'];
                    } else {
                        $decoded = json_decode((string)($temuan['foto'] ?? ''), true);
                        $existingPhotos = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', (string)$temuan['foto'])));
                    }
                }

                if ($replaceOldPhotos) {
                    // Unlink berkas foto lama secara fisik dari server
                    foreach ($existingPhotos as $oldPhoto) {
                        $oldPath = FCPATH . 'foto/' . $oldPhoto;
                        if (file_exists($oldPath) && is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                    $data['foto'] = json_encode($uploadResult['names']);
                } else {
                    // Mode Tambah: Gabungkan foto lama dan foto baru
                    $mergedPhotos = array_merge($existingPhotos, $uploadResult['names']);
                    $data['foto'] = json_encode($mergedPhotos);
                }

                $data['foto_path'] = 'foto/';
            }
        }

        $result = $this->temuanRepository->update($id, $data);

        if ($result !== false) {
            // Flush Cache CI4
            if (function_exists('cache')) {
                @cache()->clean();
            }

            log_activity('UPDATE_TEMUAN', 'Mengubah data temuan ID: ' . $id . ' (' . $temuan['nomor_temuan'] . ')');
            return ['success' => true, 'message' => 'Data temuan & foto berhasil diperbarui.'];
        }

        return ['success' => false, 'message' => 'Gagal memperbarui data temuan.'];
    }

    /**
     * Memperbarui Progres Pekerjaan (Tindak Lanjut)
     */
    public function updateTemuanPekerjaan(int $temuanId, array $progressData, array $uploadFiles): array
    {
        $temuan = $this->temuanRepository->find($temuanId);
        if (!$temuan) {
            return [
                'success' => false,
                'message' => 'Temuan tidak ditemukan.'
            ];
        }

        $nomorTemuan = $temuan['nomor_temuan'];
        $progressData['temuan_id'] = $temuanId;
        $progressData['tanggal'] = date('Y-m-d H:i:s');

        // Pengunggahan foto_sebelum, foto_proses, foto_sesudah
        foreach (['foto_sebelum', 'foto_proses', 'foto_sesudah'] as $key) {
            if (isset($uploadFiles[$key]) && $uploadFiles[$key]->isValid() && !$uploadFiles[$key]->hasMoved()) {
                $uploaded = $this->uploadPhoto($uploadFiles[$key]);
                if (!empty($uploaded['name'])) {
                    $progressData[$key] = 'foto/' . $uploaded['name'];
                }
            }
        }

        // Simpan histori tindak lanjut
        $this->tindakLanjutRepository->insert($progressData);

        // Update status & metadata pengguna di tabel temuan utama
        $session = session();
        $updatePayload = [
            'updated_by'      => $session->get('user_id'),
            'updated_by_name' => $session->get('nama_pegawai') ?: $session->get('user_name'),
            'updated_by_nip'  => $session->get('nip') ?: ''
        ];

        $statusProgress = $progressData['status_progress'] ?? 'PROSES';
        if ($statusProgress === 'SELESAI') {
            $updatePayload['status'] = 'SELESAI';
            $updatePayload['tanggal_selesai'] = date('Y-m-d');
            $updatePayload['tindak_lanjut'] = $progressData['komentar'];
            log_activity('UPDATE_TEMUAN_STATUS', 'Temuan ' . $nomorTemuan . ' diselesaikan oleh ' . $progressData['pelaksana']);
        } elseif ($statusProgress === 'BUTUH PADAM') {
            $updatePayload['status'] = 'BUTUH PADAM';
            $updatePayload['pelaksana'] = 'HAR KONSTRUKSI';
            $updatePayload['catatan_tindak_lanjut'] = $progressData['komentar'];
            log_activity('UPDATE_TEMUAN_STATUS', 'Temuan ' . $nomorTemuan . ' diset BUTUH PADAM (dialihkan ke HAR KONSTRUKSI) oleh ' . $progressData['pelaksana']);
        } elseif ($statusProgress === 'TERKENDALA') {
            $updatePayload['status'] = 'TERKENDALA';
            $updatePayload['catatan_tindak_lanjut'] = $progressData['komentar'];
            log_activity('UPDATE_TEMUAN_STATUS', 'Temuan ' . $nomorTemuan . ' diset TERKENDALA oleh ' . $progressData['pelaksana']);
        } else {
            $updatePayload['status'] = 'PROSES';
            $updatePayload['catatan_tindak_lanjut'] = $progressData['komentar'];
            log_activity('UPDATE_TEMUAN_PROGRESS', 'Menambahkan progress untuk temuan: ' . $nomorTemuan);
        }

        $this->temuanRepository->update($temuanId, $updatePayload);

        // Phase 32 — Gamification: award points
        try {
            $userId = (int)$session->get('user_id');
            if ($userId > 0) {
                $gamSvc = new GamificationService();
                if ($statusProgress === 'SELESAI') {
                    $prioritas = strtoupper($temuan['prioritas'] ?? '');
                    $action = ($prioritas === 'EMERGENCY') ? 'EMERGENCY_SELESAI' : 'SELESAI_TEMUAN';
                    $gamSvc->addPoints($userId, $action, 'Selesai: ' . $nomorTemuan, $temuanId, 'temuan');
                    // Check SLA compliance
                    $tanggalTemuan = !empty($temuan['tanggal_temuan']) ? strtotime($temuan['tanggal_temuan']) : (!empty($temuan['created_at']) ? strtotime($temuan['created_at']) : null);
                    if ($tanggalTemuan !== null && $tanggalTemuan > 0) {
                        $daysDiff = floor((time() - $tanggalTemuan) / 86400);
                        $maxDays = match($prioritas) {
                            'EMERGENCY' => 3, 'HIGH' => 7, 'MEDIUM' => 31, default => 90
                        };
                        if ($daysDiff <= $maxDays) {
                            $gamSvc->addPoints($userId, 'SLA_MET', 'SLA Met: ' . $nomorTemuan, $temuanId, 'temuan');
                        }
                    }
                } else {
                    $gamSvc->addPoints($userId, 'UPDATE_TEMUAN', 'Update progress: ' . $nomorTemuan, $temuanId, 'temuan');
                }
            }
        } catch (\Throwable $e) {
            log_message('warning', '[Gamification] updateTemuanPekerjaan hook: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'message' => 'Progress tindak lanjut berhasil ditambahkan.'
        ];
    }

    /**
     * Menghapus Data Temuan (Soft Delete)
     */
    public function deleteTemuan(int $id): bool
    {
        $temuan = $this->temuanRepository->find($id);
        if ($temuan) {
            $this->temuanRepository->delete($id);
            log_activity('DELETE_TEMUAN', 'Menghapus temuan: ' . $temuan['nomor_temuan']);
            return true;
        }
        return false;
    }
}
