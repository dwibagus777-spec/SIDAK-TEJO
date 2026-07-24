<?php

namespace App\Services;

use App\Repositories\TemuanRepository;
use App\Repositories\TindakLanjutRepository;

class TemuanService
{
    private TemuanRepository $temuanRepository;
    private TindakLanjutRepository $tindakLanjutRepository;
    private CloudinaryService $cloudinary;

    public function __construct()
    {
        $this->temuanRepository = new TemuanRepository();
        $this->tindakLanjutRepository = new TindakLanjutRepository();
        $this->cloudinary = new CloudinaryService();
    }

    /**
     * Upload file to Cloudinary if configured, otherwise save to local disk
     * Returns URL (Cloudinary) or filename (local) + updated foto_path
     */
    /**
     * Kompres gambar sebelum upload ke Cloudinary
     * Menghasilkan file JPEG berkualitas rendah untuk menghemat bandwidth
     */
    private function compressImage(string $sourcePath, int $quality = 75): string
    {
        if (!function_exists('imagecreatefromjpeg')) {
            return $sourcePath; // GD tidak tersedia, skip kompresi
        }

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

            // Resize jika terlalu besar (max 1920px wide)
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

            // Hanya gunakan yang dikompres jika lebih kecil
            if (file_exists($compressedPath) && filesize($compressedPath) < filesize($sourcePath)) {
                return $compressedPath;
            }
            @unlink($compressedPath);
        } catch (\Throwable $e) {
            log_message('warning', '[compressImage] Error: ' . $e->getMessage());
        }

        return $sourcePath;
    }

    private function uploadFotoFile(\CodeIgniter\HTTP\Files\UploadedFile $file, string $localDir = 'foto/'): array
    {
        $newName = $file->getRandomName();

        // 1. Log Received File Info
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

        // 2. Process Cloudinary Upload if Enabled
        if ($this->cloudinary->isEnabled()) {
            $phpTempPath = $file->getTempName();
            if (empty($phpTempPath) || !file_exists($phpTempPath)) {
                $phpTempPath = $file->getRealPath();
            }

            if (!empty($phpTempPath) && file_exists($phpTempPath)) {
                $result = $this->cloudinary->upload($phpTempPath, 'sidak-tejo/temuan');

                if ($result['success']) {
                    log_message('info', '[UPLOAD_SUCCESS] Cloudinary URL: ' . $result['url']);
                    return ['name' => $result['url'], 'path' => 'cloudinary', 'error' => ''];
                }

                $err = 'Cloudinary Upload Gagal: ' . ($result['error'] ?? 'Unknown Error');
                log_message('error', '[UPLOAD_FAIL] ' . $err);
                return ['name' => '', 'path' => 'error', 'error' => $err];
            }

            $err = 'Temp file upload tidak dapat dibaca dari server.';
            log_message('error', '[UPLOAD_FAIL] ' . $err);
            return ['name' => '', 'path' => 'error', 'error' => $err];
        }

        // 3. Fallback Local Storage
        $fullLocalPath = FCPATH . $localDir;
        if (!is_dir($fullLocalPath)) {
            mkdir($fullLocalPath, 0777, true);
        }
        $file->move($fullLocalPath, $newName);

        $diskPath = $fullLocalPath . $newName;
        $compressed = $this->compressImage($diskPath, 80);
        if ($compressed !== $diskPath && file_exists($compressed)) {
            @rename($compressed, $diskPath);
        }

        return ['name' => $newName, 'path' => $localDir, 'error' => ''];
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

        // Validasi file upload
        if (empty($files) || count($files) === 0 || !$files[0]->isValid()) {
            $errDetail = (!empty($files) && isset($files[0])) ? $files[0]->getErrorString() : 'File foto kosong.';
            log_message('warning', '[CREATE_TEMUAN_VALIDATION_FAIL] ' . $errDetail);
            return [
                'success' => false,
                'message' => 'Unggah foto minimal 1 foto. Detail: ' . $errDetail
            ];
        }

        if (count($files) > 10) {
            return [
                'success' => false,
                'message' => 'Maksimal foto yang diunggah adalah 10 foto.'
            ];
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        foreach ($files as $file) {
            if ($file->isValid()) {
                $ext = strtolower($file->getExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions)) {
                    return [
                        'success' => false,
                        'message' => 'Format file "' . $file->getName() . '" tidak diizinkan. Hanya jpg, jpeg, png, webp.'
                    ];
                }
            }
        }

        // Upload foto
        $uploadedNames = [];
        $uploadDir = 'foto/';

        foreach ($files as $file) {
            if ($file->isValid() && !$file->hasMoved()) {
                $uploaded = $this->uploadFotoFile($file, $uploadDir);
                if ($uploaded['path'] === 'error' || empty($uploaded['name'])) {
                    return [
                        'success' => false,
                        'message' => 'Gagal mengunggah foto: ' . ($uploaded['error'] ?? 'Terjadi kesalahan.')
                    ];
                }
                $uploadedNames[] = $uploaded['name'];
                $uploadDir = $uploaded['path'] === 'cloudinary' ? 'cloudinary' : $uploaded['path'];
            }
        }

        if (empty($uploadedNames)) {
            return [
                'success' => false,
                'message' => 'Tidak ada foto yang berhasil terunggah.'
            ];
        }

        $data['foto'] = json_encode($uploadedNames);
        $data['foto_path'] = $uploadDir;

        log_message('info', sprintf('[DB_INSERT_TEMUAN] Nomor: %s | Foto JSON: %s', $nomorTemuan, $data['foto']));

        $insertId = $this->temuanRepository->insert($data);

        if ($insertId) {
            log_activity('CREATE_TEMUAN', 'Menambahkan temuan baru: ' . $nomorTemuan);
            return [
                'success' => true,
                'message' => 'Temuan berhasil disimpan ke Cloudinary.',
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
        $uploadDir = 'foto/';
        $fullPath = FCPATH . $uploadDir;

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        $progressData['temuan_id'] = $temuanId;
        $progressData['tanggal'] = date('Y-m-d H:i:s');

        // Handle foto_sebelum (opsional)
        if (isset($uploadFiles['foto_sebelum']) && $uploadFiles['foto_sebelum']->isValid() && !$uploadFiles['foto_sebelum']->hasMoved()) {
            $uploaded = $this->uploadFotoFile($uploadFiles['foto_sebelum'], $uploadDir);
            $progressData['foto_sebelum'] = $uploaded['path'] === 'cloudinary' ? $uploaded['name'] : $uploadDir . $uploaded['name'];
        }

        // Handle foto_proses (opsional)
        if (isset($uploadFiles['foto_proses']) && $uploadFiles['foto_proses']->isValid() && !$uploadFiles['foto_proses']->hasMoved()) {
            $uploaded = $this->uploadFotoFile($uploadFiles['foto_proses'], $uploadDir);
            $progressData['foto_proses'] = $uploaded['path'] === 'cloudinary' ? $uploaded['name'] : $uploadDir . $uploaded['name'];
        }

        // Handle foto_sesudah (opsional)
        if (isset($uploadFiles['foto_sesudah']) && $uploadFiles['foto_sesudah']->isValid() && !$uploadFiles['foto_sesudah']->hasMoved()) {
            $uploaded = $this->uploadFotoFile($uploadFiles['foto_sesudah'], $uploadDir);
            $progressData['foto_sesudah'] = $uploaded['path'] === 'cloudinary' ? $uploaded['name'] : $uploadDir . $uploaded['name'];
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
            $updateTemuan['pelaksana'] = 'HAR KONSTRUKSI'; // Otomatis berpindah ke HAR KONSTRUKSI
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
     * Update Data Temuan (tanpa menghapus foto lama)
     * Foto baru akan di-append ke daftar foto yang ada
     */
    public function updateTemuan(int $id, array $data, ?array $newFiles): array
    {
        $temuan = $this->temuanRepository->find($id);
        if (!$temuan) {
            return ['success' => false, 'message' => 'Temuan tidak ditemukan.'];
        }


        $data['updated_by'] = session()->get('user_id');

        // Jika ada file foto baru yang diunggah, tambahkan ke daftar yang ada
        $hasNewFiles = !empty($newFiles) && isset($newFiles[0]) && $newFiles[0]->isValid();
        if ($hasNewFiles) {
            if (count($newFiles) > 10) {
                return ['success' => false, 'message' => 'Maksimal 10 foto per upload.'];
            }

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            foreach ($newFiles as $file) {
                if ($file->isValid()) {
                    if (!in_array(strtolower($file->getExtension()), $allowedExtensions)) {
                        return ['success' => false, 'message' => 'Format file tidak diizinkan. Hanya jpg, jpeg, png, webp.'];
                    }
                }
            }

            $uploadDir = !empty($temuan['foto_path']) ? rtrim($temuan['foto_path'], '/') . '/' : 'foto/';
            $fullPath  = FCPATH . $uploadDir;
            $data['foto_path'] = $uploadDir;

            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0777, true);
            }

            // Process upload for each new file
            $newNames = [];
            foreach ($newFiles as $file) {
                if ($file->isValid() && !$file->hasMoved()) {
                    $uploaded = $this->uploadFotoFile($file, $uploadDir);
                    $newNames[] = $uploaded['name'];
                    if ($uploaded['path'] === 'cloudinary') {
                        $uploadDir = 'cloudinary';
                    }
                }
            }

            // Gantikan foto lama dengan foto baru yang diunggah
            if (!empty($newNames)) {
                $data['foto'] = json_encode($newNames);
                $data['foto_path'] = $uploadDir;
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
