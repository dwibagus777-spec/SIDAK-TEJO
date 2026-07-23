<?php

namespace App\Services;

use App\Repositories\TemuanRepository;
use App\Repositories\TindakLanjutRepository;

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

        // 3. Pindahkan file foto ke direktori public/uploads/temuan/{nomor_temuan}/
        $uploadedNames = [];
        $uploadDir = 'uploads/temuan/' . str_replace('-', '_', $nomorTemuan) . '/';
        $fullPath = FCPATH . $uploadDir;

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        foreach ($files as $file) {
            if ($file->isValid() && !$file->hasMoved()) {
                $newName = $file->getRandomName();
                $file->move($fullPath, $newName);
                $uploadedNames[] = $newName;
            }
        }

        // Simpan nama-nama berkas sebagai JSON
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
     * Tambahkan progress tindak lanjut (Riwayat Tindak Lanjut)
     */
    public function addTindakLanjut(int $temuanId, array $progressData, ?array $uploadFiles): array
    {
        $temuan = $this->temuanRepository->find($temuanId);
        if (!$temuan) {
            return [
                'success' => false,
                'message' => 'Temuan tidak ditemukan.'
            ];
        }

        $nomorTemuan = $temuan['nomor_temuan'];
        $uploadDir = 'uploads/temuan/' . str_replace('-', '_', $nomorTemuan) . '/tindak_lanjut/';
        $fullPath = FCPATH . $uploadDir;

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        $progressData['temuan_id'] = $temuanId;
        $progressData['tanggal'] = date('Y-m-d H:i:s');

        // Handle foto_sebelum (opsional, defaults to current temuan photo if first time)
        if (isset($uploadFiles['foto_sebelum']) && $uploadFiles['foto_sebelum']->isValid() && !$uploadFiles['foto_sebelum']->hasMoved()) {
            $nameSebelum = $uploadFiles['foto_sebelum']->getRandomName();
            $uploadFiles['foto_sebelum']->move($fullPath, $nameSebelum);
            $progressData['foto_sebelum'] = $uploadDir . $nameSebelum;
        }

        // Handle foto_proses (opsional)
        if (isset($uploadFiles['foto_proses']) && $uploadFiles['foto_proses']->isValid() && !$uploadFiles['foto_proses']->hasMoved()) {
            $nameProses = $uploadFiles['foto_proses']->getRandomName();
            $uploadFiles['foto_proses']->move($fullPath, $nameProses);
            $progressData['foto_proses'] = $uploadDir . $nameProses;
        }

        // Handle foto_sesudah (opsional)
        if (isset($uploadFiles['foto_sesudah']) && $uploadFiles['foto_sesudah']->isValid() && !$uploadFiles['foto_sesudah']->hasMoved()) {
            $nameSesudah = $uploadFiles['foto_sesudah']->getRandomName();
            $uploadFiles['foto_sesudah']->move($fullPath, $nameSesudah);
            $progressData['foto_sesudah'] = $uploadDir . $nameSesudah;
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

            $uploadDir = $temuan['foto_path'];
            $fullPath  = FCPATH . $uploadDir;

            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0777, true);
            }

            // Ambil foto lama dan gabungkan dengan baru
            $existingPhotos = json_decode($temuan['foto'], true) ?: [];
            $newNames = [];
            foreach ($newFiles as $file) {
                if ($file->isValid() && !$file->hasMoved()) {
                    $newName = $file->getRandomName();
                    $file->move($fullPath, $newName);
                    $newNames[] = $newName;
                }
            }

            $allPhotos = array_merge($existingPhotos, $newNames);
            $data['foto'] = json_encode($allPhotos);
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
