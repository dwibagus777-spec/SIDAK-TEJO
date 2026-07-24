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

        if ($this->cloudinary->isEnabled()) {
            // === CLOUDINARY PATH ===
            // Get PHP's original temp file path directly - no move(), no filesystem write needed
            $phpTempPath = $file->getTempName();

            log_message('info', '[uploadFotoFile] PHP temp: ' . $phpTempPath
                . ' | exists: ' . (file_exists($phpTempPath) ? 'YES (' . filesize($phpTempPath) . 'B)' : 'NO'));

            if (!empty($phpTempPath) && file_exists($phpTempPath)) {
                // Upload directly from PHP temp file (base64 in CloudinaryService, no disk write)
                $result = $this->cloudinary->upload($phpTempPath, 'sidak-tejo/temuan');

                if ($result['success']) {
                    log_message('info', '[uploadFotoFile] ✅ Cloudinary OK: ' . $result['url']);
                    return ['name' => $result['url'], 'path' => 'cloudinary'];
                }

                log_message('error', '[uploadFotoFile] ❌ Cloudinary FAILED: ' . ($result['error'] ?? '?'));
            } else {
                log_message('error', '[uploadFotoFile] ❌ getTempName() kosong/tidak ada: ' . $phpTempPath);
            }
            // Fall through to local storage as last resort
        }

        // === LOCAL STORAGE (no Cloudinary / fallback) ===
        $fullLocalPath = FCPATH . $localDir;
        if (!is_dir($fullLocalPath)) {
            mkdir($fullLocalPath, 0777, true);
        }
        $file->move($fullLocalPath, $newName);

        // Compress local file
        $diskPath  = $fullLocalPath . $newName;
        $compressed = $this->compressImage($diskPath, 80);
        if ($compressed !== $diskPath && file_exists($compressed)) {
            // Replace original with compressed version
            @rename($compressed, $diskPath);
        }

        return ['name' => $newName, 'path' => $localDir];
    }


    /**
     * Simpan Temuan Baru beserta Unggahan Foto
     */
    public function createTemuan(array $data, ?array $files): array
    {

        // 1. Generate nomor temuan otomatis
        $nomorTemuan = $this->temuanRepository->generateNomorTemuan();
        $session = session();
        $data['nomor_temuan'] = $nomorTemuan;
        $data['status'] = 'BELUM';
        $data['created_by'] = $session->get('user_id');
        $data['created_by_name'] = $session->get('nama_pegawai') ?: $session->get('user_name');
        $data['created_by_nip'] = $session->get('nip') ?: '';

        // 2. Validasi file upload (Minimal 1, Maksimal 10)
        if (empty($files) || count($files) === 0 || !$files[0]->isValid()) {
            return [
                'success' => false,
                'message' => 'Unggah foto minimal 1 foto.'
            ];
        }

        if (count($files) > 10) {
            return [
                'success' => false,
                'message' => 'Maksimal foto yang diunggah adalah 10 foto.'
            ];
        }

        // Validasi format file
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        foreach ($files as $file) {
            if ($file->isValid()) {
                $ext = $file->getExtension();
                if (!in_array(strtolower($ext), $allowedExtensions)) {
                    return [
                        'success' => false,
                        'message' => 'Format file tidak diizinkan. Hanya jpg, jpeg, png, webp.'
                    ];
                }
            }
        }

        // 3. Upload foto (Cloudinary jika dikonfigurasi, atau simpan ke disk lokal)
        $uploadedNames = [];
        $uploadDir = 'foto/';

        foreach ($files as $file) {
            if ($file->isValid() && !$file->hasMoved()) {
                $uploaded = $this->uploadFotoFile($file, $uploadDir);
                $uploadedNames[] = $uploaded['name'];
                $uploadDir = $uploaded['path'] === 'cloudinary' ? 'cloudinary' : $uploaded['path'];
            }
        }

        // Simpan nama-nama berkas (URL Cloudinary atau filename lokal) sebagai JSON
        $data['foto'] = json_encode($uploadedNames);
        $data['foto_path'] = $uploadDir;

        // 4. Masukkan ke database
        $insertId = $this->temuanRepository->insert($data);

        if ($insertId) {
            log_activity('CREATE_TEMUAN', 'Menambahkan temuan baru: ' . $nomorTemuan);
            return [
                'success' => true,
                'message' => 'Temuan berhasil disimpan.',
                'id' => $insertId
            ];
        }

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
