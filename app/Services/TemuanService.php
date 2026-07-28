<?php

namespace App\Services;

use App\Repositories\TemuanRepository;
use App\Repositories\TindakLanjutRepository;
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
     * Kompres gambar di penyimpanan lokal (GD Library)
     */
    private function compressImage(string $sourcePath, int $quality = 75): string
    {
        if (!function_exists('imagecreatefromjpeg')) {
            return $sourcePath;
        }

        @ini_set('memory_limit', '256M');
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $image = null;

        try {
            if ($ext === 'jpg' || $ext === 'jpeg') {
                $image = @imagecreatefromjpeg($sourcePath);
            } elseif ($ext === 'png') {
                $image = @imagecreatefrompng($sourcePath);
            } elseif ($ext === 'webp') {
                $image = @imagecreatefromwebp($sourcePath);
            }

            if (!$image) return $sourcePath;

            $w = imagesx($image);
            $h = imagesy($image);
            if ($w > 1920) {
                $newW = 1920;
                $newH = (int)($h * 1920 / $w);
                $resized = imagecreatetruecolor($newW, $newH);
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);
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
            log_message('warning', '[compressImage] Error: ' . $e->getMessage());
        }

        return $sourcePath;
    }

    /**
     * Upload single photo file directly to public/foto/
     */
    private function uploadPhoto(UploadedFile $file): array
    {
        $newName = $file->getRandomName();

        log_message('info', sprintf(
            '[FILE_RECEIVED] Name: %s | TempPath: %s | Size: %d bytes | MIME: %s | ErrorCode: %d | IsValid: %s',
            $file->getName(),
            $file->getTempName() ?: 'EMPTY',
            $file->getSize(),
            $file->getClientMimeType(),
            $file->getError(),
            $file->isValid() ? 'YES' : 'NO'
        ));

        if (!$file->isValid()) {
            $errStr = $file->getErrorString() . ' (Code: ' . $file->getError() . ')';
            log_message('error', '[FILE_VALIDATION_ERROR] ' . $errStr);
            return ['name' => '', 'path' => 'error', 'error' => $errStr];
        }

        $fullLocalPath = FCPATH . 'foto/';
        if (!is_dir($fullLocalPath)) {
            mkdir($fullLocalPath, 0777, true);
        }

        $file->move($fullLocalPath, $newName);

        $diskPath = $fullLocalPath . $newName;
        $compressed = $this->compressImage($diskPath, 80);
        if ($compressed !== $diskPath && file_exists($compressed)) {
            @rename($compressed, $diskPath);
        }

        return ['name' => $newName, 'path' => 'foto/', 'error' => ''];
    }

    /**
     * Validasi berkas foto (jumlah & ekstensi)
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
     * Upload multiple photo files
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
     * Simpan Temuan Baru beserta Unggahan Foto
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

        log_message('info', sprintf('[CREATE_TEMUAN_START] Nomor: %s | Files Count: %d', $nomorTemuan, count($files ?? [])));

        $uploadResult = $this->uploadMultiplePhotos($files, true);
        if (!$uploadResult['success']) {
            return [
                'success' => false,
                'message' => $uploadResult['message']
            ];
        }

        $data['foto'] = json_encode($uploadResult['names']);
        $data['foto_path'] = 'foto/';

        log_message('info', sprintf('[DB_INSERT_TEMUAN] Nomor: %s | Foto JSON: %s', $nomorTemuan, $data['foto']));

        $insertId = $this->temuanRepository->insert($data);

        if ($insertId) {
            log_activity('CREATE_TEMUAN', 'Menambahkan temuan baru: ' . $nomorTemuan);
            return [
                'success' => true,
                'message' => 'Temuan berhasil disimpan.',
                'id'      => $insertId
            ];
        }

        log_message('error', '[DB_INSERT_FAIL] Gagal insert ke tabel temuan.');
        return [
            'success' => false,
            'message' => 'Gagal menyimpan temuan ke database.'
        ];
    }

    /**
     * Update Progres Pekerjaan (Tindak Lanjut / SLA Progress)
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
        $uploadDir = FCPATH . 'foto/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $progressData['temuan_id'] = $temuanId;
        $progressData['tanggal'] = date('Y-m-d H:i:s');

        // Handle foto_sebelum, foto_proses, foto_sesudah (opsional)
        foreach (['foto_sebelum', 'foto_proses', 'foto_sesudah'] as $key) {
            if (isset($uploadFiles[$key]) && $uploadFiles[$key]->isValid() && !$uploadFiles[$key]->hasMoved()) {
                $uploaded = $this->uploadPhoto($uploadFiles[$key]);
                if (!empty($uploaded['name'])) {
                    $progressData[$key] = 'foto/' . $uploaded['name'];
                }
            }
        }

        // Simpan progress history
        $this->tindakLanjutRepository->insert($progressData);

        // Update tabel utama temuan
        $session = session();
        $updateTemuan = [];
        $updateTemuan['updated_by'] = $session->get('user_id');
        $updateTemuan['updated_by_name'] = $session->get('nama_pegawai') ?: $session->get('user_name');
        $updateTemuan['updated_by_nip'] = $session->get('nip') ?: '';

        if (isset($progressData['status_progress']) && $progressData['status_progress'] === 'SELESAI') {
            $updateTemuan['status'] = 'SELESAI';
            $updateTemuan['tanggal_selesai'] = date('Y-m-d');
            $updateTemuan['tindak_lanjut'] = $progressData['komentar'];
            $this->temuanRepository->update($temuanId, $updateTemuan);

            log_activity('UPDATE_TEMUAN_STATUS', 'Temuan ' . $nomorTemuan . ' diselesaikan oleh ' . $progressData['pelaksana']);
        } elseif (isset($progressData['status_progress']) && $progressData['status_progress'] === 'BUTUH PADAM') {
            $updateTemuan['status'] = 'BUTUH PADAM';
            $updateTemuan['pelaksana'] = 'HAR KONSTRUKSI';
            $updateTemuan['catatan_tindak_lanjut'] = $progressData['komentar'];
            $this->temuanRepository->update($temuanId, $updateTemuan);

            log_activity('UPDATE_TEMUAN_STATUS', 'Temuan ' . $nomorTemuan . ' diset BUTUH PADAM (dialihkan ke HAR KONSTRUKSI) oleh ' . $progressData['pelaksana']);
        } elseif (isset($progressData['status_progress']) && $progressData['status_progress'] === 'TERKENDALA') {
            $updateTemuan['status'] = 'TERKENDALA';
            $updateTemuan['catatan_tindak_lanjut'] = $progressData['komentar'];
            $this->temuanRepository->update($temuanId, $updateTemuan);

            log_activity('UPDATE_TEMUAN_STATUS', 'Temuan ' . $nomorTemuan . ' diset TERKENDALA oleh ' . $progressData['pelaksana']);
        } else {
            $updateTemuan['status'] = 'PROSES';
            $updateTemuan['catatan_tindak_lanjut'] = $progressData['komentar'];
            $this->temuanRepository->update($temuanId, $updateTemuan);

            log_activity('UPDATE_TEMUAN_PROGRESS', 'Menambahkan progress untuk temuan: ' . $nomorTemuan);
        }

        return [
            'success' => true,
            'message' => 'Progress tindak lanjut berhasil ditambahkan.'
        ];
    }

    /**
     * Update Data Temuan
     */
    public function updateTemuan(int $id, array $data, ?array $newFiles): array
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
                $data['foto'] = json_encode($uploadResult['names']);
                $data['foto_path'] = 'foto/';
            }
        }

        $result = $this->temuanRepository->update($id, $data);

        if ($result !== false) {
            log_activity('UPDATE_TEMUAN', 'Mengubah data temuan ID: ' . $id . ' (' . $temuan['nomor_temuan'] . ')');
            return ['success' => true, 'message' => 'Data temuan berhasil diperbarui.'];
        }

        return ['success' => false, 'message' => 'Gagal memperbarui data temuan.'];
    }

    /**
     * Hapus Temuan (Soft Delete)
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
