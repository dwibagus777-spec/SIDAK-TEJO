<?php

namespace App\Controllers;

use App\Models\InspeksiKubikelModel;
use App\Models\InspeksiKubikelChecklistModel;
use App\Models\InspeksiKubikelFotoModel;
use App\Models\InspeksiKubikelTemuanModel;
use App\Models\InspeksiKubikelRekomendasiModel;
use App\Models\InspeksiKubikelHistoriModel;
use App\Models\InspeksiKubikelMaterialModel;
use App\Models\GarduIndukModel;
use App\Repositories\UlpRepository;
use App\Repositories\PenyulangRepository;
use App\Repositories\SectionRepository;

class KubikelInspectionController extends BaseController
{
    private InspeksiKubikelModel $inspeksiModel;
    private InspeksiKubikelChecklistModel $checklistModel;
    private InspeksiKubikelFotoModel $fotoModel;
    private InspeksiKubikelTemuanModel $temuanModel;
    private InspeksiKubikelRekomendasiModel $rekomendasiModel;
    private InspeksiKubikelHistoriModel $historiModel;
    private InspeksiKubikelMaterialModel $materialModel;

    public function __construct()
    {
        $this->inspeksiModel   = new InspeksiKubikelModel();
        $this->checklistModel  = new InspeksiKubikelChecklistModel();
        $this->fotoModel       = new InspeksiKubikelFotoModel();
        $this->temuanModel     = new InspeksiKubikelTemuanModel();
        $this->rekomendasiModel = new InspeksiKubikelRekomendasiModel();
        $this->historiModel    = new InspeksiKubikelHistoriModel();
        $this->materialModel   = new InspeksiKubikelMaterialModel();
    }

    public function index()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $ulpId = (int)$session->get('ulp_id');

        if ($role === 'beyond_kwh') {
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh'));
        }

        $tableExists = $this->inspeksiModel->db->tableExists('inspeksi_kubikel');
        $inspeksi = $tableExists ? $this->inspeksiModel->getInspeksiByRole($role, $userId, $ulpId) : [];

        return view('kubikel_inspeksi/index', [
            'inspeksi'   => $inspeksi,
            'role'       => $role,
            'tableReady' => $tableExists,
        ]);
    }

    public function create()
    {
        $session = session();
        if (strtolower((string)$session->get('user_role')) === 'beyond_kwh') {
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh'))
                ->with('error', 'Role Beyond KWH hanya dapat mengakses rekomendasi yang ditugaskan kepadanya.');
        }

        $userId = (int)$session->get('user_id');
        $ulpId = (int)$session->get('ulp_id');

        $giModel = new GarduIndukModel();
        $ulpRepo = new UlpRepository();
        $penyulangRepo = new PenyulangRepository();
        $sectionRepo = new SectionRepository();

        return view('kubikel_inspeksi/create', [
            'garduInduk' => $giModel->getActiveGi(),
            'ulps'       => $ulpRepo->getActiveUlps(),
            'penyulangs' => $penyulangRepo->getModel()->where('status', 'AKTIF')->findAll(),
            'sections'   => $sectionRepo->getActiveSections(),
            'inspectorId' => $userId,
            'defaultUlpId' => $ulpId,
        ]);
    }

    public function store()
    {
        $session = session();
        if (strtolower((string)$session->get('user_role')) === 'beyond_kwh') {
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh'))
                ->with('error', 'Role Beyond KWH tidak dapat membuat inspeksi.');
        }

        $userId = (int)$session->get('user_id');

        $rules = [
            'ulp_id'        => 'required|integer',
            'penyulang_id'  => 'required|integer',
            'section_id'    => 'permit_empty|integer',
            'gardu_induk_id' => 'permit_empty|integer',
            'asset_id'      => 'permit_empty|integer',
            'id_pel'         => 'permit_empty|max_length[100]',
            'nama_pelanggan' => 'permit_empty|max_length[255]',
            'tgl_inspeksi'   => 'required|valid_date',
            'jenis_potensi'  => 'permit_empty|in_list[TREATMENT_TRAFO,CLEANING_KUBIKEL]',
            'nilai_potensi'  => 'permit_empty|numeric',
            'catatan_umum'   => 'permit_empty',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $nomorInspeksi = $this->inspeksiModel->generateNomorInspeksi();

        $jenisPotensi = $this->request->getPost('jenis_potensi');
        $nilaiPotensi = null;
        if ($jenisPotensi === 'TREATMENT_TRAFO') {
            $nilaiPotensi = 5000000;
        } elseif ($jenisPotensi === 'CLEANING_KUBIKEL') {
            $nilaiPotensi = 4999000;
        } else {
            $jenisPotensi = null;
            $nilaiPotensi = null;
        }

        $data = [
            'nomor_inspeksi'     => $nomorInspeksi,
            'ulp_id'             => (int)$this->request->getPost('ulp_id'),
            'penyulang_id'       => (int)$this->request->getPost('penyulang_id'),
            'section_id'         => (int)($this->request->getPost('section_id') ?: 0),
            'gardu_induk_id'     => (int)($this->request->getPost('gardu_induk_id') ?: 0),
            'asset_id'           => (int)($this->request->getPost('asset_id') ?: 0),
            'id_pel'             => $this->request->getPost('id_pel') ?: null,
            'nama_pelanggan'     => $this->request->getPost('nama_pelanggan') ?: null,
            // CR-GIS-FIX-07: Kepemilikan & Jenis Gardu - drives Beyond KWH routing
            'kepemilikan_gardu'  => $this->request->getPost('kepemilikan_gardu') ?: 'PLN',
            'jenis_gardu'        => $this->request->getPost('jenis_gardu') ?: 'GARDU_KONVENSIONAL',
            'inspector_user_id'  => $userId,
            'tgl_inspeksi'       => $this->request->getPost('tgl_inspeksi'),
            'waktu_mulai'        => null,
            'waktu_selesai'      => null,
            'catatan_umum'       => $this->request->getPost('catatan_umum'),
            'jenis_potensi'      => $jenisPotensi,
            'nilai_potensi'      => $nilaiPotensi,
            'status'             => 'DRAFT',
            'status_temuan'      => (!empty($jenisPotensi) || !empty($this->request->getPost('catatan_umum'))) ? 'ADA' : 'TIDAK_ADA',
        ];

        $inspeksiId = $this->inspeksiModel->insert($data, true);

        if (!$inspeksiId) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal membuat inspeksi: ' . implode(', ', $this->inspeksiModel->errors()));
        }

        $this->historiModel->logPerubahan(
            $inspeksiId,
            'STATUS',
            null,
            'DRAFT',
            $userId,
            'Pembuatan inspeksi baru'
        );

        // Otomatis catat temuan dan rekomendasi jika dipilih potensi pekerjaan atau catatan temuan
        if (!empty($jenisPotensi) || !empty($data['catatan_umum'])) {
            $komponen = ($jenisPotensi === 'TREATMENT_TRAFO') ? 'Trafo' : (($jenisPotensi === 'CLEANING_KUBIKEL') ? 'Kubikel' : 'Gardu');
            $penerima = ($data['kepemilikan_gardu'] === 'PELANGGAN') ? 'BEYOND_KWH' : 'HAR_GTT';
            $deskripsiTemuan = !empty($data['catatan_umum']) ? $data['catatan_umum'] : ($jenisPotensi === 'TREATMENT_TRAFO' ? 'Potensi Treatment Trafo (Rp 5.000.000)' : 'Potensi Cleaning Kubikel (Rp 4.999.000)');
            $rekomendasi = $jenisPotensi ? "Pekerjaan {$komponen}: " . ($jenisPotensi === 'TREATMENT_TRAFO' ? 'Treatment Trafo' : 'Cleaning Kubikel') : 'Pemeriksaan lebih lanjut';

            $temuanData = [
                'inspeksi_kubikel_id'  => $inspeksiId,
                'checklist_id'         => null,
                'komponen'             => $komponen,
                'fasa'                 => 'TIDAK_BERFASA',
                'posisi'               => 'UTAMA',
                'tanggal_temuan'       => $data['tgl_inspeksi'],
                'deskripsi_temuan'     => $deskripsiTemuan,
                'jenis_potensi'        => $jenisPotensi,
                'nilai_potensi'        => $nilaiPotensi,
                'urgensi'              => 'SEDANG',
                'rekomendasi'          => $rekomendasi,
                'penerima_rekomendasi' => $penerima,
                'status_temuan'        => 'BARU',
                'assigned_to_user_id'  => null,
            ];
            $temuanData['fingerprint'] = $this->temuanModel->generateFingerprint($temuanData);
            $newTemuanId = $this->temuanModel->insert($temuanData, true);
            if ($newTemuanId) {
                $this->createRekomendasi($newTemuanId, $penerima, $rekomendasi, null);
            }
        }

        // Handle upload foto inspeksi (multiple)
        $files = $this->request->getFiles();
        $uploadedCount = 0;
        $uploadErrors = [];

        if (isset($files['fotos']) && is_array($files['fotos'])) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            $maxSize = 10 * 1024 * 1024; // 10MB per file

            $storageRootPath = defined('SIDAK_STORAGE_PATH') ? rtrim(SIDAK_STORAGE_PATH, '/\\') : WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'foto';
            if (!is_dir($storageRootPath)) {
                @mkdir($storageRootPath, 0775, true);
            }
            $storageRoot = realpath($storageRootPath) ?: $storageRootPath;

            $relativeDirectory = 'kubikel_inspeksi' . DIRECTORY_SEPARATOR . $inspeksiId;
            $targetDirectory = $storageRoot . DIRECTORY_SEPARATOR . $relativeDirectory;

            foreach ($files['fotos'] as $file) {
                if (!$file || !$file->isValid() || $file->hasMoved()) {
                    continue;
                }

                $mimeType = $file->getMimeType();
                $fileSize = $file->getSize();

                if (!in_array($mimeType, $allowedMimes, true)) {
                    $uploadErrors[] = "File '{$file->getClientName()}' diabaikan: format harus JPEG, PNG, atau WebP.";
                    continue;
                }

                if ($fileSize > $maxSize) {
                    $uploadErrors[] = "File '{$file->getClientName()}' diabaikan: ukuran melebihi 10MB.";
                    continue;
                }

                try {
                    if (!is_dir($targetDirectory) && !@mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
                        throw new \RuntimeException('Direktori storage foto tidak dapat dibuat.');
                    }
                    $fileName = $file->getRandomName();
                    $file->move($targetDirectory, $fileName);

                    $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativeDirectory . DIRECTORY_SEPARATOR . $fileName);

                    $fotoData = [
                        'inspeksi_kubikel_id' => $inspeksiId,
                        'checklist_id'        => null,
                        'kategori'            => 'TEMUAN',
                        'nama_file'           => $fileName,
                        'path_file'           => $relativePath,
                        'mime_type'           => $mimeType,
                        'ukuran'              => $fileSize,
                        'deskripsi'           => 'Foto kondisi inspeksi lapangan',
                        'uploaded_by'         => $userId,
                    ];

                    $this->fotoModel->insert($fotoData);
                    $uploadedCount++;
                } catch (\Throwable $e) {
                    log_message('error', '[KUBIKEL_FOTO_STORE] ' . $e->getMessage());
                    $uploadErrors[] = "Gagal mengunggah '{$file->getClientName()}': " . $e->getMessage();
                }
            }

            if ($uploadedCount > 0) {
                $this->historiModel->logPerubahan(
                    $inspeksiId,
                    'TEMUAN',
                    null,
                    "{$uploadedCount} foto diunggah saat pembuatan inspeksi",
                    $userId,
                    'Upload foto inspeksi'
                );
            }
        }

        $successMsg = 'Inspeksi berhasil dibuat. Nomor: ' . $nomorInspeksi;
        if ($uploadedCount > 0) {
            $successMsg .= ' (' . $uploadedCount . ' foto kondisi lapangan berhasil disimpan).';
        }
        if (!empty($uploadErrors)) {
            session()->setFlashdata('warning', implode(' ', $uploadErrors));
        }

        return redirect()->to(site_url('kubikel-inspeksi/detail/' . $inspeksiId))
            ->with('success', $successMsg);
    }

    public function edit(int $id)
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $ulpId = (int)$session->get('ulp_id');

        $inspeksi = $this->inspeksiModel->find($id);
        if (!$inspeksi) {
            return redirect()->to(site_url('kubikel-inspeksi'))
                ->with('error', 'Inspeksi tidak ditemukan.');
        }

        // Cek akses: hanya inspector, ULP yang sama, atau admin
        $canEdit = $this->canEditInspeksi($inspeksi, $role, $userId, $ulpId);

        if (!$canEdit) {
            return redirect()->to(site_url('dashboard'))
                ->with('error', 'Anda tidak memiliki hak akses untuk mengedit inspeksi ini.');
        }

        // Cek status - tidak bisa edit jika sudah DITUTUP
        if ($inspeksi['status'] === 'DITUTUP') {
            return redirect()->to(site_url('kubikel-inspeksi/detail/' . $id))
                ->with('error', 'Inspeksi sudah ditutup dan tidak dapat diedit.');
        }

        $giModel = new GarduIndukModel();
        $ulpRepo = new UlpRepository();
        $penyulangRepo = new PenyulangRepository();
        $sectionRepo = new SectionRepository();

        $checklist = $this->checklistModel->getByInspeksi($id);
        $foto = $this->fotoModel->getByInspeksi($id);
        $temuan = $this->temuanModel->getByInspeksi($id);
        $material = $this->materialModel->getByInspeksi($id);
        $histori = $this->historiModel->getByInspeksi($id);
        $beyondUsers = \Config\Database::connect()->table('users')
            ->select('id, nama_pegawai')
            ->where('role', 'beyond_kwh')
            ->where('status', 'AKTIF')
            ->orderBy('nama_pegawai', 'ASC')
            ->get()->getResultArray();

        return view('kubikel_inspeksi/edit', [
            'inspeksi'   => $inspeksi,
            'checklist'  => $checklist,
            'foto'       => $foto,
            'temuan'     => $temuan,
            'material'   => $material,
            'histori'    => $histori,
            'garduInduk' => $giModel->getActiveGi(),
            'ulps'       => $ulpRepo->getActiveUlps(),
            'penyulangs' => $penyulangRepo->getModel()->where('status', 'AKTIF')->findAll(),
            'sections'   => $sectionRepo->getActiveSections(),
            'canEdit'    => $canEdit,
            'role'       => $role,
        ]);
    }

    public function update(int $id)
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $ulpId = (int)$session->get('ulp_id');

        $inspeksi = $this->inspeksiModel->find($id);
        if (!$inspeksi) {
            return redirect()->to(site_url('kubikel-inspeksi'))
                ->with('error', 'Inspeksi tidak ditemukan.');
        }

        // Cek akses: hanya inspector, ULP yang sama, atau admin
        $canEdit = $this->canEditInspeksi($inspeksi, $role, $userId, $ulpId);

        if (!$canEdit) {
            return redirect()->to(site_url('dashboard'))
                ->with('error', 'Anda tidak memiliki hak akses untuk mengedit inspeksi ini.');
        }

        // Cek status - tidak bisa edit jika sudah DITUTUP
        if ($inspeksi['status'] === 'DITUTUP') {
            return redirect()->to(site_url('kubikel-inspeksi/detail/' . $id))
                ->with('error', 'Inspeksi sudah ditutup dan tidak dapat diedit.');
        }

        $rules = [
            'ulp_id'        => 'required|integer',
            'penyulang_id'  => 'required|integer',
            'section_id'    => 'permit_empty|integer',
            'gardu_induk_id' => 'permit_empty|integer',
            'asset_id'      => 'permit_empty|integer',
            'id_pel'         => 'permit_empty|max_length[100]',
            'nama_pelanggan' => 'permit_empty|max_length[255]',
            'tgl_inspeksi'   => 'required|valid_date',
            'jenis_potensi'  => 'permit_empty|in_list[TREATMENT_TRAFO,CLEANING_KUBIKEL]',
            'nilai_potensi'  => 'permit_empty|numeric',
            'waktu_mulai'    => 'permit_empty',
            'waktu_selesai'  => 'permit_empty',
            'catatan_umum'   => 'permit_empty',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $jenisPotensi = $this->request->getPost('jenis_potensi');
        $nilaiPotensi = null;
        if ($jenisPotensi === 'TREATMENT_TRAFO') {
            $nilaiPotensi = 5000000;
        } elseif ($jenisPotensi === 'CLEANING_KUBIKEL') {
            $nilaiPotensi = 4999000;
        } else {
            $jenisPotensi = null;
            $nilaiPotensi = null;
        }

        $data = [
            'ulp_id'             => (int)$this->request->getPost('ulp_id'),
            'penyulang_id'       => (int)$this->request->getPost('penyulang_id'),
            'section_id'         => (int)($this->request->getPost('section_id') ?: 0),
            'gardu_induk_id'     => (int)($this->request->getPost('gardu_induk_id') ?: 0),
            'asset_id'           => (int)($this->request->getPost('asset_id') ?: 0),
            'id_pel'             => $this->request->getPost('id_pel') ?: null,
            'nama_pelanggan'     => $this->request->getPost('nama_pelanggan') ?: null,
            'tgl_inspeksi'       => $this->request->getPost('tgl_inspeksi'),
            'jenis_potensi'      => $jenisPotensi,
            'nilai_potensi'      => $nilaiPotensi,
            'waktu_mulai'        => $this->request->getPost('waktu_mulai'),
            'waktu_selesai'      => $this->request->getPost('waktu_selesai'),
            'catatan_umum'       => $this->request->getPost('catatan_umum'),
        ];

        if (!$this->inspeksiModel->update($id, $data)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui inspeksi: ' . implode(', ', $this->inspeksiModel->errors()));
        }

        $this->historiModel->logPerubahan(
            $id,
            'INFORMASI_UTAMA',
            null,
            'Data inspeksi diperbarui',
            $userId,
            'Perubahan informasi utama inspeksi'
        );

        return redirect()->to(site_url('kubikel-inspeksi/edit/' . $id))
            ->with('success', 'Inspeksi berhasil diperbarui.');
    }

    public function detail(int $id)
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $ulpId = (int)$session->get('ulp_id');

        $inspeksi = $this->inspeksiModel->find($id);
        if (!$inspeksi) {
            return redirect()->to(site_url('kubikel-inspeksi'))
                ->with('error', 'Inspeksi tidak ditemukan.');
        }

        if ($role === 'beyond_kwh') {
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh'))
                ->with('error', 'Gunakan detail rekomendasi yang ditugaskan kepada Anda.');
        }

        // Cek akses: admin, inspector, ULP yang sama, atau Beyond KWH (via rekomendasi)
        $canView = in_array($role, ['administrator', 'admin', 'admin_pusat'])
            || (int)$inspeksi['inspector_user_id'] === $userId
            || ((int)$inspeksi['ulp_id'] === $ulpId && in_array($role, ['ulp', 'har_gtt']));

        if (!$canView) {
            return redirect()->to(site_url('dashboard'))
                ->with('error', 'Anda tidak memiliki hak akses untuk melihat inspeksi ini.');
        }

        $checklist = $this->checklistModel->getByInspeksi($id);
        $foto = $this->fotoModel->getByInspeksi($id);
        $temuan = $this->temuanModel->getByInspeksi($id);
        $material = $this->materialModel->getByInspeksi($id);
        $histori = $this->historiModel->getByInspeksi($id);
        $beyondUsers = \Config\Database::connect()->table('users')
            ->select('id, nama_pegawai')
            ->where('role', 'beyond_kwh')
            ->where('status', 'AKTIF')
            ->orderBy('nama_pegawai', 'ASC')
            ->get()->getResultArray();

        return view('kubikel_inspeksi/detail', [
            'inspeksi'  => $inspeksi,
            'checklist' => $checklist,
            'foto'      => $foto,
            'temuan'    => $temuan,
            'material'  => $material,
            'histori'   => $histori,
            'beyondUsers' => $beyondUsers,
            'role'      => $role,
            'canEdit'   => $this->canEditInspeksi($inspeksi, $role, $userId, $ulpId),
        ]);
    }

    // ================================================================
    // CHECKLIST MANAGEMENT
    // ================================================================

    public function storeChecklist(int $id)
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $ulpId = (int)$session->get('ulp_id');

        $inspeksi = $this->inspeksiModel->find($id);
        if (!$inspeksi) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Inspeksi tidak ditemukan.']);
        }

        $canEdit = $this->canEditInspeksi($inspeksi, $role, $userId, $ulpId);

        if (!$canEdit || $inspeksi['status'] === 'DITUTUP') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Tidak diizinkan mengubah checklist.']);
        }

        $rules = [
            'komponen'            => 'required|max_length[100]',
            'fasa'                => 'in_list[R,S,T,N,TIDAK_BERFASA]',
            'posisi'              => 'permit_empty|max_length[100]',
            'item_pemeriksaan'    => 'required|max_length[255]',
            'kondisi'             => 'in_list[BAIK,RUSAK_RINGAN,RUSAK_SEDANG,RUSAK_BERAT,TIDAK_DITEMUKAN,TIDAK_APLIKABEL]',
            'temuan'              => 'permit_empty',
            'urgensi'             => 'in_list[RENDAH,SEDANG,TINGGI,KRITIS]',
            'rekomendasi'         => 'permit_empty',
            'tindak_lanjut'       => 'permit_empty',
            'material_digunakan'  => 'permit_empty',
            'status_pekerjaan'    => 'in_list[BELUM,PROSES,SELESAI,DITANGGUHKAN]',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Validasi gagal', 'errors' => $this->validator->getErrors()]);
        }

        $lastOrder = $this->checklistModel->where('inspeksi_kubikel_id', $id)
            ->selectMax('urutan')
            ->first();
        $nextOrder = (($lastOrder['urutan'] ?? 0) + 1);

        $data = [
            'inspeksi_kubikel_id' => $id,
            'komponen'            => $this->request->getPost('komponen'),
            'fasa'                => $this->request->getPost('fasa'),
            'posisi'              => $this->request->getPost('posisi'),
            'item_pemeriksaan'    => $this->request->getPost('item_pemeriksaan'),
            'kondisi'             => $this->request->getPost('kondisi'),
            'temuan'              => $this->request->getPost('temuan'),
            'urgensi'             => $this->request->getPost('urgensi'),
            'rekomendasi'         => $this->request->getPost('rekomendasi'),
            'tindak_lanjut'       => $this->request->getPost('tindak_lanjut'),
            'material_digunakan'  => $this->request->getPost('material_digunakan'),
            'status_pekerjaan'    => $this->request->getPost('status_pekerjaan'),
            'urutan'              => $nextOrder,
        ];

        $checklistId = $this->checklistModel->insert($data, true);

        if (!$checklistId) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Gagal menyimpan checklist']);
        }

        // Update status inspeksi ke IN_PROGRESS jika masih DRAFT
        if ($inspeksi['status'] === 'DRAFT') {
            $this->inspeksiModel->update($id, ['status' => 'IN_PROGRESS']);
            $this->historiModel->logPerubahan($id, 'STATUS', 'DRAFT', 'IN_PROGRESS', $userId, 'Memulai pengisian checklist');
        }

        // Jika ada temuan, update status_temuan
        if (!empty($data['temuan'])) {
            $this->inspeksiModel->update($id, ['status_temuan' => 'ADA']);
        }

        return $this->response->setJSON(['success' => true, 'id' => $checklistId, 'message' => 'Checklist tersimpan']);
    }

    public function updateChecklist(int $id, int $checklistId)
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $ulpId = (int)$session->get('ulp_id');

        $inspeksi = $this->inspeksiModel->find($id);
        $checklist = $this->checklistModel->find($checklistId);

        if (!$inspeksi || !$checklist || (int)$checklist['inspeksi_kubikel_id'] !== $id) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.']);
        }

        $canEdit = $this->canEditInspeksi($inspeksi, $role, $userId, $ulpId);

        if (!$canEdit || $inspeksi['status'] === 'DITUTUP') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Tidak diizinkan.']);
        }

        $rules = [
            'komponen'            => 'required|max_length[100]',
            'fasa'                => 'in_list[R,S,T,N,TIDAK_BERFASA]',
            'posisi'              => 'permit_empty|max_length[100]',
            'item_pemeriksaan'    => 'required|max_length[255]',
            'kondisi'             => 'in_list[BAIK,RUSAK_RINGAN,RUSAK_SEDANG,RUSAK_BERAT,TIDAK_DITEMUKAN,TIDAK_APLIKABEL]',
            'temuan'              => 'permit_empty',
            'urgensi'             => 'in_list[RENDAH,SEDANG,TINGGI,KRITIS]',
            'rekomendasi'         => 'permit_empty',
            'tindak_lanjut'       => 'permit_empty',
            'material_digunakan'  => 'permit_empty',
            'status_pekerjaan'    => 'in_list[BELUM,PROSES,SELESAI,DITANGGUHKAN]',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Validasi gagal', 'errors' => $this->validator->getErrors()]);
        }

        $oldTemuan = $checklist['temuan'] ?? '';
        $newTemuan = $this->request->getPost('temuan');

        $data = [
            'komponen'            => $this->request->getPost('komponen'),
            'fasa'                => $this->request->getPost('fasa'),
            'posisi'              => $this->request->getPost('posisi'),
            'item_pemeriksaan'    => $this->request->getPost('item_pemeriksaan'),
            'kondisi'             => $this->request->getPost('kondisi'),
            'temuan'              => $newTemuan,
            'urgensi'             => $this->request->getPost('urgensi'),
            'rekomendasi'         => $this->request->getPost('rekomendasi'),
            'tindak_lanjut'       => $this->request->getPost('tindak_lanjut'),
            'material_digunakan'  => $this->request->getPost('material_digunakan'),
            'status_pekerjaan'    => $this->request->getPost('status_pekerjaan'),
        ];

        if (!$this->checklistModel->update($checklistId, $data)) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Gagal memperbarui checklist']);
        }

        // Update status inspeksi berdasarkan temuan
        $hasTemuan = $this->checklistModel->where('inspeksi_kubikel_id', $id)
            ->where('temuan IS NOT NULL')
            ->where('temuan !=', '')
            ->countAllResults() > 0;

        $this->inspeksiModel->update($id, ['status_temuan' => $hasTemuan ? 'ADA' : 'TIDAK_ADA']);

        if ($oldTemuan !== $newTemuan) {
            $this->historiModel->logPerubahan($id, 'TEMUAN', $oldTemuan, $newTemuan, $userId, 'Perubahan temuan pada checklist');
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Checklist diperbarui']);
    }

    public function deleteChecklist(int $id, int $checklistId)
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $ulpId = (int)$session->get('ulp_id');

        $inspeksi = $this->inspeksiModel->find($id);
        $checklist = $this->checklistModel->find($checklistId);

        if (!$inspeksi || !$checklist || (int)$checklist['inspeksi_kubikel_id'] !== $id) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.']);
        }

        $canEdit = $this->canEditInspeksi($inspeksi, $role, $userId, $ulpId);

        if (!$canEdit || $inspeksi['status'] === 'DITUTUP') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Tidak diizinkan.']);
        }

        $oldTemuan = $checklist['temuan'] ?? '';

        if (!$this->checklistModel->delete($checklistId)) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Gagal menghapus checklist']);
        }

        // Update status inspeksi
        $hasTemuan = $this->checklistModel->where('inspeksi_kubikel_id', $id)
            ->where('temuan IS NOT NULL')
            ->where('temuan !=', '')
            ->countAllResults() > 0;

        $this->inspeksiModel->update($id, ['status_temuan' => $hasTemuan ? 'ADA' : 'TIDAK_ADA']);

        if (!empty($oldTemuan)) {
            $this->historiModel->logPerubahan($id, 'TEMUAN', $oldTemuan, null, $userId, 'Penghapusan checklist dengan temuan');
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Checklist dihapus']);
    }

    public function reorderChecklist(int $id)
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $ulpId = (int)$session->get('ulp_id');

        $inspeksi = $this->inspeksiModel->find($id);
        if (!$inspeksi) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Inspeksi tidak ditemukan.']);
        }

        $canEdit = $this->canEditInspeksi($inspeksi, $role, $userId, $ulpId);

        if (!$canEdit || $inspeksi['status'] === 'DITUTUP') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Tidak diizinkan.']);
        }

        $json = $this->request->getJSON(true);
        $items = $json['items'] ?? [];

        if (empty($items)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Tidak ada data urutan.']);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        foreach ($items as $item) {
            $cid = (int)($item['id'] ?? 0);
            $order = (int)($item['urutan'] ?? 0);
            if ($cid > 0 && $order >= 0) {
                $this->checklistModel->where('id', $cid)->where('inspeksi_kubikel_id', $id)->set(['urutan' => $order])->update();
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Gagal memperbarui urutan']);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Urutan diperbarui']);
    }

    // ================================================================
    // TEMUAN MANAGEMENT
    // ================================================================

    public function storeTemuan(int $id)
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $ulpId = (int)$session->get('ulp_id');

        $inspeksi = $this->inspeksiModel->find($id);
        if (!$inspeksi) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Inspeksi tidak ditemukan.']);
        }

        $canEdit = $this->canEditInspeksi($inspeksi, $role, $userId, $ulpId);

        if (!$canEdit || $inspeksi['status'] === 'DITUTUP') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Tidak diizinkan mencatat temuan.']);
        }

        $rules = [
            'komponen'             => 'required|max_length[100]',
            'fasa'                 => 'in_list[R,S,T,N,TIDAK_BERFASA]',
            'posisi'               => 'permit_empty|max_length[100]',
            'tanggal_temuan'       => 'permit_empty|valid_date',
            'deskripsi_temuan'     => 'required',
            'jenis_potensi'        => 'permit_empty|in_list[TREATMENT_TRAFO,CLEANING_KUBIKEL]',
            'nilai_potensi'        => 'permit_empty|numeric',
            'urgensi'              => 'in_list[RENDAH,SEDANG,TINGGI,KRITIS]',
            'rekomendasi'          => 'permit_empty',
            'penerima_rekomendasi' => 'in_list[ULP,HAR_GTT,BEYOND_KWH,ADMINISTRATOR,LAINNYA]',
            'checklist_id'         => 'permit_empty|integer',
            'idempotency_key'      => 'permit_empty|max_length[64]',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Validasi gagal', 'errors' => $this->validator->getErrors()]);
        }

        $checklistId = $this->request->getPost('checklist_id');
        if ($checklistId !== null && $checklistId !== '') {
            $checklist = $this->checklistModel->find((int)$checklistId);
            if (!$checklist || (int)$checklist['inspeksi_kubikel_id'] !== $id) {
                return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Checklist tidak ditemukan pada inspeksi ini.']);
            }
        }

        $assignedUserId = null;
        $penerimaRekomendasi = $this->request->getPost('penerima_rekomendasi');

        // CR-GIS-FIX-08: Guard Beyond KWH routing - only PELANGGAN + KUBIKEL goes to Beyond KWH
        if ($penerimaRekomendasi === 'BEYOND_KWH') {
            $kepemilikan = strtoupper(trim((string)($inspeksi['kepemilikan_gardu'] ?? 'PLN')));
            $jenisGardu  = strtoupper(trim((string)($inspeksi['jenis_gardu'] ?? 'GARDU_KONVENSIONAL')));
            if ($kepemilikan !== 'PELANGGAN' || $jenisGardu !== 'KUBIKEL') {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Rekomendasi Beyond KWH hanya dapat diberikan untuk inspeksi Gardu Kubikel milik Pelanggan. '
                               . 'Inspeksi ini adalah gardu ' . $kepemilikan . ' (' . $jenisGardu . ').',
                ]);
            }
            $assignedUserId = filter_var($this->request->getPost('assigned_to_user_id'), FILTER_VALIDATE_INT) ?: null;
            $assignedUser = \Config\Database::connect()->table('users')
                ->where('id', $assignedUserId)
                ->where('role', 'beyond_kwh')
                ->where('status', 'AKTIF')
                ->get()->getRowArray();
            if (!$assignedUserId || !$assignedUser) {
                return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Pilih pengguna Beyond KWH aktif yang akan menerima rekomendasi.']);
            }
        }

        $idempotencyKey = $this->request->getPost('idempotency_key');
        if ($idempotencyKey) {
            $existing = $this->temuanModel->where('idempotency_key', $idempotencyKey)
                ->where('inspeksi_kubikel_id', $id)
                ->where('deleted_at IS NULL')
                ->first();
            if ($existing) {
                return $this->response->setJSON(['success' => true, 'id' => $existing['id'], 'message' => 'Temuan sudah dicatat (idempotent)', 'duplicate' => true]);
            }
        }

        $tanggalTemuan = $this->request->getPost('tanggal_temuan') ?: ($inspeksi['tgl_inspeksi'] ?? date('Y-m-d'));

        $jenisPotensi = $this->request->getPost('jenis_potensi');
        $nilaiPotensi = null;
        if ($jenisPotensi === 'TREATMENT_TRAFO') {
            $nilaiPotensi = 5000000;
        } elseif ($jenisPotensi === 'CLEANING_KUBIKEL') {
            $nilaiPotensi = 4999000;
        } else {
            $jenisPotensi = null;
            $nilaiPotensi = null;
        }

        $data = [
            'inspeksi_kubikel_id'  => $id,
            'checklist_id'         => $checklistId ? (int)$checklistId : null,
            'komponen'             => $this->request->getPost('komponen'),
            'fasa'                 => $this->request->getPost('fasa'),
            'posisi'               => $this->request->getPost('posisi'),
            'tanggal_temuan'       => $tanggalTemuan,
            'deskripsi_temuan'     => $this->request->getPost('deskripsi_temuan'),
            'jenis_potensi'        => $jenisPotensi,
            'nilai_potensi'        => $nilaiPotensi,
            'urgensi'              => $this->request->getPost('urgensi'),
            'rekomendasi'          => $this->request->getPost('rekomendasi'),
            'penerima_rekomendasi' => $this->request->getPost('penerima_rekomendasi'),
            'status_temuan'        => 'BARU',
            'assigned_to_user_id'  => $assignedUserId,
            'idempotency_key'      => $idempotencyKey,
        ];

        // Generate fingerprint untuk dedup
        $data['fingerprint'] = $this->temuanModel->generateFingerprint($data);

        $db = \Config\Database::connect();
        $db->transBegin();

        $temuanId = $this->temuanModel->insert($data, true);

        if (!$temuanId) {
            $db->transRollback();
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Gagal mencatat temuan']);
        }

        // Buat rekomendasi otomatis
        if (!$this->createRekomendasi($temuanId, $data['penerima_rekomendasi'], $data['rekomendasi'], $assignedUserId)) {
            $db->transRollback();
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Temuan tidak disimpan karena rekomendasi gagal dibuat.']);
        }

        // Update status inspeksi
        if (!$this->inspeksiModel->update($id, ['status_temuan' => 'ADA'])) {
            $db->transRollback();
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Temuan tidak disimpan karena status inspeksi gagal diperbarui.']);
        }

        if (!$this->historiModel->logPerubahan($id, 'TEMUAN', null, $data['deskripsi_temuan'], $userId, 'Pencatatan temuan baru')) {
            $db->transRollback();
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Temuan tidak disimpan karena histori gagal dicatat.']);
        }

        $db->transCommit();
        if ($db->transStatus() === false) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Transaksi pencatatan temuan gagal.']);
        }

        return $this->response->setJSON(['success' => true, 'id' => $temuanId, 'message' => 'Temuan dicatat']);
    }

    public function updateTemuan(int $id, int $temuanId)
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $ulpId = (int)$session->get('ulp_id');

        $inspeksi = $this->inspeksiModel->find($id);
        $temuan = $this->temuanModel->find($temuanId);

        if (!$inspeksi || !$temuan || (int)$temuan['inspeksi_kubikel_id'] !== $id) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.']);
        }

        $canEdit = $this->canEditInspeksi($inspeksi, $role, $userId, $ulpId);

        if (!$canEdit || $inspeksi['status'] === 'DITUTUP') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Tidak diizinkan.']);
        }

        $rules = [
            'komponen'             => 'required|max_length[100]',
            'fasa'                 => 'in_list[R,S,T,N,TIDAK_BERFASA]',
            'posisi'               => 'permit_empty|max_length[100]',
            'tanggal_temuan'       => 'permit_empty|valid_date',
            'deskripsi_temuan'     => 'required',
            'jenis_potensi'        => 'permit_empty|in_list[TREATMENT_TRAFO,CLEANING_KUBIKEL]',
            'nilai_potensi'        => 'permit_empty|numeric',
            'urgensi'              => 'in_list[RENDAH,SEDANG,TINGGI,KRITIS]',
            'rekomendasi'          => 'permit_empty',
            'penerima_rekomendasi' => 'in_list[ULP,HAR_GTT,BEYOND_KWH,ADMINISTRATOR,LAINNYA]',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Validasi gagal', 'errors' => $this->validator->getErrors()]);
        }

        $oldData = [
            'komponen'         => $temuan['komponen'],
            'fasa'             => $temuan['fasa'],
            'posisi'           => $temuan['posisi'],
            'tanggal_temuan'   => $temuan['tanggal_temuan'] ?? null,
            'deskripsi_temuan' => $temuan['deskripsi_temuan'],
            'jenis_potensi'    => $temuan['jenis_potensi'] ?? null,
            'nilai_potensi'    => $temuan['nilai_potensi'] ?? null,
            'urgensi'          => $temuan['urgensi'],
            'rekomendasi'      => $temuan['rekomendasi'],
        ];

        $jenisPotensi = $this->request->getPost('jenis_potensi');
        $nilaiPotensi = null;
        if ($jenisPotensi === 'TREATMENT_TRAFO') {
            $nilaiPotensi = 5000000;
        } elseif ($jenisPotensi === 'CLEANING_KUBIKEL') {
            $nilaiPotensi = 4999000;
        } else {
            $jenisPotensi = null;
            $nilaiPotensi = null;
        }

        $data = [
            'komponen'             => $this->request->getPost('komponen'),
            'fasa'                 => $this->request->getPost('fasa'),
            'posisi'               => $this->request->getPost('posisi'),
            'tanggal_temuan'       => $this->request->getPost('tanggal_temuan') ?: ($temuan['tanggal_temuan'] ?? date('Y-m-d')),
            'deskripsi_temuan'     => $this->request->getPost('deskripsi_temuan'),
            'jenis_potensi'        => $jenisPotensi,
            'nilai_potensi'        => $nilaiPotensi,
            'urgensi'              => $this->request->getPost('urgensi'),
            'rekomendasi'          => $this->request->getPost('rekomendasi'),
        ];

        if (!$this->temuanModel->update($temuanId, $data)) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Gagal memperbarui temuan']);
        }

        $this->rekomendasiModel->where('temuan_id', $temuanId)
            ->where('penerima', $temuan['penerima_rekomendasi'])
            ->set(['deskripsi' => $data['rekomendasi'] ?: 'Tindak lanjut diperlukan'])
            ->update();

        // Log perubahan
        foreach ($oldData as $field => $oldValue) {
            $newValue = $data[$field] ?? null;
            if ($oldValue !== $newValue) {
                $this->historiModel->logPerubahan($id, 'TEMUAN', (string)$oldValue, $newValue === null ? null : (string)$newValue, $userId, "Perubahan $field pada temuan");
            }
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Temuan diperbarui']);
    }

    private function canEditInspeksi(array $inspeksi, string $role, int $userId, int $ulpId): bool
    {
        return $role !== 'beyond_kwh'
            && (
                in_array($role, ['administrator', 'admin', 'admin_pusat'], true)
                || (int)$inspeksi['inspector_user_id'] === $userId
                || ((int)$inspeksi['ulp_id'] === $ulpId && in_array($role, ['ulp', 'har_gtt'], true))
            );
    }

    private function createRekomendasi(int $temuanId, string $penerima, ?string $deskripsi, ?int $assignedUserId = null): bool
    {
        return $this->rekomendasiModel->insert([
            'temuan_id'        => $temuanId,
            'penerima'         => $penerima,
            'penerima_user_id' => $assignedUserId,
            'deskripsi'        => $deskripsi ?: 'Tindak lanjut diperlukan',
            'status'           => 'TERKIRIM',
            'waktu_kirim'      => date('Y-m-d H:i:s'),
            'notification_id'  => null, // TODO: integrasikan dengan sistem notifikasi yang ada
        ]) !== false;
    }

    // ================================================================
    // FOTO MANAGEMENT
    // ================================================================

    public function uploadFoto(int $id)
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $ulpId = (int)$session->get('ulp_id');

        $inspeksi = $this->inspeksiModel->find($id);
        if (!$inspeksi) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Inspeksi tidak ditemukan.']);
        }

        $canEdit = $this->canEditInspeksi($inspeksi, $role, $userId, $ulpId);

        if (!$canEdit || $inspeksi['status'] === 'DITUTUP') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Tidak diizinkan mengunggah foto.']);
        }

        $file = $this->request->getFile('foto');
        if (!$file || !$file->isValid()) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'File tidak valid.']);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Format file tidak diizinkan (JPEG, PNG, WebP).']);
        }

        if ($file->getSize() > $maxSize) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Ukuran file melebihi 10MB.']);
        }

        $checklistId = $this->request->getPost('checklist_id');
        if ($checklistId !== null && $checklistId !== '') {
            $checklist = $this->checklistModel->find((int)$checklistId);
            if (!$checklist || (int)$checklist['inspeksi_kubikel_id'] !== $id) {
                return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Checklist tidak ditemukan pada inspeksi ini.']);
            }
        }

        $kategori = $this->request->getPost('kategori') ?: 'LAINNYA';
        if (!in_array($kategori, ['SEBELUM', 'SESUDAH', 'TEMUAN', 'BUKTI', 'LAINNYA'], true)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Kategori foto tidak valid.']);
        }

        if (!defined('SIDAK_STORAGE_PATH')) {
            log_message('error', '[KUBIKEL_FOTO_UPLOAD] SIDAK_STORAGE_PATH is not configured.');
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Storage foto SIDAK TEJO belum dikonfigurasi.']);
        }

        $storageRoot = realpath(SIDAK_STORAGE_PATH);
        if ($storageRoot === false) {
            log_message('error', '[KUBIKEL_FOTO_UPLOAD] SIDAK_STORAGE_PATH cannot be resolved.');
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Storage foto SIDAK TEJO tidak dapat diakses.']);
        }
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();
        $relativeDirectory = 'kubikel_inspeksi' . DIRECTORY_SEPARATOR . $id;
        $targetDirectory = $storageRoot . DIRECTORY_SEPARATOR . $relativeDirectory;
        $fileName = $file->getRandomName();

        try {
            if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0770, true) && !is_dir($targetDirectory)) {
                throw new \RuntimeException('Direktori storage foto tidak dapat dibuat.');
            }
            $targetDirectory = realpath($targetDirectory);
            if ($targetDirectory === false || !str_starts_with($targetDirectory, $storageRoot . DIRECTORY_SEPARATOR)) {
                throw new \RuntimeException('Direktori foto berada di luar storage privat.');
            }
            $file->move($targetDirectory, $fileName);
        } catch (\Throwable $e) {
            log_message('error', '[KUBIKEL_FOTO_UPLOAD] ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Gagal mengunggah foto ke storage SIDAK TEJO.']);
        }

        $fullPath = $targetDirectory . DIRECTORY_SEPARATOR . $fileName;
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativeDirectory . DIRECTORY_SEPARATOR . $fileName);
        $data = [
            'inspeksi_kubikel_id' => $id,
            'checklist_id'        => $checklistId ? (int)$checklistId : null,
            'kategori'            => $kategori,
            'nama_file'           => $fileName,
            'path_file'           => $relativePath,
            'mime_type'           => $mimeType,
            'ukuran'              => $fileSize,
            'deskripsi'           => $this->request->getPost('deskripsi'),
            'uploaded_by'         => $userId,
        ];

        $fotoId = $this->fotoModel->insert($data, true);

        if (!$fotoId) {
            if (is_file($fullPath) && !unlink($fullPath)) {
                log_message('error', '[KUBIKEL_FOTO_UPLOAD] Failed to remove orphaned file: ' . $fullPath);
            }
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Gagal menyimpan metadata foto: ' . implode(', ', $this->fotoModel->errors())]);
        }

        $this->historiModel->logPerubahan($id, 'TEMUAN', null, 'Foto ditambahkan: ' . $fileName, $userId, 'Upload foto inspeksi');

        return $this->response->setJSON(['success' => true, 'id' => $fotoId, 'message' => 'Foto berhasil diunggah', 'path' => $relativePath]);
    }

    public function deleteFoto(int $id, int $fotoId)
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $ulpId = (int)$session->get('ulp_id');

        $inspeksi = $this->inspeksiModel->find($id);
        $foto = $this->fotoModel->find($fotoId);

        if (!$inspeksi || !$foto || (int)$foto['inspeksi_kubikel_id'] !== $id) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Foto tidak ditemukan.']);
        }

        $canEdit = $this->canEditInspeksi($inspeksi, $role, $userId, $ulpId);

        if (!$canEdit || $inspeksi['status'] === 'DITUTUP') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Tidak diizinkan menghapus foto.']);
        }

        if (!defined('SIDAK_STORAGE_PATH')) {
            log_message('error', '[KUBIKEL_FOTO_DELETE] SIDAK_STORAGE_PATH is not configured.');
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Storage foto SIDAK TEJO belum dikonfigurasi.']);
        }

        $expectedPrefix = 'kubikel_inspeksi/' . $id . '/';
        $relativePath = str_replace('\\', '/', (string)$foto['path_file']);
        if (!str_starts_with($relativePath, $expectedPrefix) || str_contains($relativePath, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $relativePath)) {
            log_message('error', '[KUBIKEL_FOTO_DELETE] Invalid stored relative path for foto id ' . $fotoId);
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Path foto tersimpan tidak valid; file tidak dihapus.']);
        }

        $storageRoot = realpath(SIDAK_STORAGE_PATH);
        if ($storageRoot === false) {
            log_message('error', '[KUBIKEL_FOTO_DELETE] SIDAK_STORAGE_PATH cannot be resolved.');
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Storage foto SIDAK TEJO tidak dapat diakses.']);
        }
        $fullPath = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $resolvedFile = realpath($fullPath);
        if ($resolvedFile === false || !is_file($resolvedFile)
            || !str_starts_with($resolvedFile, $storageRoot . DIRECTORY_SEPARATOR)) {
            log_message('error', '[KUBIKEL_FOTO_DELETE] Stored file missing or outside private storage for foto id ' . $fotoId);
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'File foto tidak ditemukan di storage SIDAK TEJO; metadata tidak dihapus.']);
        }

        if (!unlink($resolvedFile)) {
            log_message('error', '[KUBIKEL_FOTO_DELETE] Failed to remove stored file for foto id ' . $fotoId);
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'File foto gagal dihapus; metadata tidak dihapus.']);
        }

        if (!$this->fotoModel->delete($fotoId)) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Gagal menghapus metadata foto']);
        }

        $this->historiModel->logPerubahan($id, 'TEMUAN', 'Foto: ' . $foto['nama_file'], null, $userId, 'Penghapusan foto inspeksi');

        return $this->response->setJSON(['success' => true, 'message' => 'Foto dihapus']);
    }

    // ================================================================
    // STATUS MANAGEMENT
    // ================================================================

    public function updateStatus(int $id)
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $ulpId = (int)$session->get('ulp_id');

        $inspeksi = $this->inspeksiModel->find($id);
        if (!$inspeksi) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Inspeksi tidak ditemukan.']);
        }

        $newStatus = $this->request->getPost('status');
        $allowedStatuses = ['DRAFT', 'IN_PROGRESS', 'SELESAI', 'DITUTUP', 'DIBATALKAN'];

        if (!in_array($newStatus, $allowedStatuses)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Status tidak valid.']);
        }

        // Aturan transisi status
        $transitions = [
            'DRAFT'        => ['IN_PROGRESS', 'DIBATALKAN'],
            'IN_PROGRESS'  => ['SELESAI', 'DRAFT', 'DIBATALKAN'],
            'SELESAI'      => ['DITUTUP', 'IN_PROGRESS', 'DIBATALKAN'],
            'DITUTUP'      => [], // Tidak bisa diubah kecuali supervisor
            'DIBATALKAN'   => ['DRAFT'],
        ];

        $canChange = $role !== 'beyond_kwh' && (in_array($role, ['administrator', 'admin', 'admin_pusat'])
            || ((int)$inspeksi['inspector_user_id'] === $userId && in_array($newStatus, $transitions[$inspeksi['status']] ?? []))
            || ((int)$inspeksi['ulp_id'] === $ulpId && in_array($role, ['ulp', 'har_gtt']) && in_array($newStatus, $transitions[$inspeksi['status']] ?? [])));

        // Supervisor bisa menutup (DITUTUP)
        if ($newStatus === 'DITUTUP') {
            $canChange = in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor']);
            if (!$canChange) {
                return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Hanya supervisor/administrator yang bisa menutup inspeksi.']);
            }
        }

        if (!$canChange) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Tidak diizinkan mengubah status ke ' . $newStatus . ' dari ' . $inspeksi['status']]);
        }

        $alasanPenutupan = $this->request->getPost('alasan_penutupan');

        if ($newStatus === 'DITUTUP') {
            if (empty($alasanPenutupan)) {
                return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Alasan penutupan wajib diisi untuk status DITUTUP.']);
            }

            // Cek apakah ada temuan PLN yang wajib diverifikasi
            $temuanWajib = $this->temuanModel->where('inspeksi_kubikel_id', $id)
                ->where('penerima_rekomendasi IN', ['ULP', 'HAR_GTT'])
                ->whereIn('status_temuan', ['BARU', 'DITUGASKAN', 'DIPROSES'])
                ->countAllResults();

            if ($temuanWajib > 0 && $role !== 'administrator' && $role !== 'admin' && $role !== 'admin_pusat') {
                return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Masih ada temuan PLN/HAR GTT yang belum diverifikasi. Tidak bisa ditutup.']);
            }
        }

        $oldStatus = $inspeksi['status'];

        $updateData = [
            'status' => $newStatus,
        ];

        if ($newStatus === 'DITUTUP') {
            $updateData['alasan_penutupan'] = $alasanPenutupan;
            $updateData['supervisor_approval_user_id'] = $userId;
            $updateData['supervisor_approval_at'] = date('Y-m-d H:i:s');
        }

        if ($newStatus === 'SELESAI' && $inspeksi['status'] === 'IN_PROGRESS') {
            $updateData['waktu_selesai'] = date('H:i:s');
        }

        if (!$this->inspeksiModel->update($id, $updateData)) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Gagal memperbarui status']);
        }

        $this->historiModel->logPerubahan($id, 'STATUS', $oldStatus, $newStatus, $userId, 'Perubahan status inspeksi' . ($alasanPenutupan ? ': ' . $alasanPenutupan : ''));

        return $this->response->setJSON(['success' => true, 'message' => 'Status diperbarui ke ' . $newStatus]);
    }

    public function reopen(int $id)
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');

        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor'])) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Hanya supervisor/administrator yang bisa membuka kembali inspeksi.']);
        }

        $inspeksi = $this->inspeksiModel->find($id);
        if (!$inspeksi) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Inspeksi tidak ditemukan.']);
        }

        if ($inspeksi['status'] !== 'DITUTUP') {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Inspeksi tidak dalam status DITUTUP.']);
        }

        $alasan = $this->request->getPost('alasan');
        if (empty($alasan)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Alasan pembukaan kembali wajib diisi.']);
        }

        if (!$this->inspeksiModel->update($id, [
            'status'                 => 'SELESAI',
            'alasan_penutupan'       => null,
            'supervisor_approval_user_id' => null,
            'supervisor_approval_at' => null,
        ])) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Gagal membuka kembali inspeksi']);
        }

        $this->historiModel->logPerubahan($id, 'KOREKSI', 'DITUTUP', 'SELESAI', $userId, 'Pembukaan kembali inspeksi: ' . $alasan);

        return $this->response->setJSON(['success' => true, 'message' => 'Inspeksi dibuka kembali ke status SELESAI']);
    }

    // ================================================================
    // REKOMENDASI & BEYOND KWH
    // ================================================================

    public function myRekomendasi()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $isAdmin = in_array($role, ['administrator', 'admin', 'admin_pusat'], true);

        if ($role !== 'beyond_kwh' && !$isAdmin) {
            return redirect()->to(site_url('dashboard'))
                ->with('error', 'Halaman ini hanya untuk role Beyond KWH atau Administrator.');
        }

        // Strict role scoping: Beyond KWH role is STRICTLY scoped to their own user ID
        $scopeUserId = $isAdmin ? null : $userId;

        // Parse and sanitize incoming filters
        $filters = [
            'status'       => $this->request->getGet('status'),
            'urgensi'      => $this->request->getGet('urgensi'),
            'ulp_id'       => $this->request->getGet('ulp_id'),
            'penyulang_id' => $this->request->getGet('penyulang_id'),
            'petugas_id'   => $isAdmin ? $this->request->getGet('petugas_id') : null,
            'start_date'   => $this->request->getGet('start_date'),
            'end_date'     => $this->request->getGet('end_date'),
        ];

        // Fetch aggregates & metrics safely from model
        $tableReady    = $this->rekomendasiModel->db->tableExists('inspeksi_kubikel_rekomendasi');
        $kpiStats      = $this->rekomendasiModel->getDashboardStats($scopeUserId, $filters);
        $monthlyTrend  = $this->rekomendasiModel->getMonthlyTrend($scopeUserId, $filters);
        $statusDist    = $this->rekomendasiModel->getStatusDistribution($scopeUserId, $filters);
        $priorityDist  = $this->rekomendasiModel->getPriorityDistribution($scopeUserId, $filters);
        $ulpRanking    = $isAdmin ? $this->rekomendasiModel->getUlpRanking($scopeUserId, $filters) : [];
        $rekomendasi   = $this->rekomendasiModel->getFilteredRekomendasi($scopeUserId, $filters, 100);

        // Fetch auxiliary master data for interactive filter dropdowns
        $ulpRepo = new UlpRepository();
        $penyulangRepo = new PenyulangRepository();
        $ulps = $ulpRepo->findAll();
        $penyulangs = !empty($filters['ulp_id']) ? $penyulangRepo->getByUlp((int)$filters['ulp_id']) : $penyulangRepo->findAll();
        $officers = $isAdmin ? $this->rekomendasiModel->getBeyondOfficers() : [];

        // Mirroring Pipeline & 4DX Enterprise Dataset (matching SIDELTA Executive Dashboard)
        $db = \Config\Database::connect();
        $probingCount   = $db->tableExists('beyond_kwh_probing') ? (int) $db->table('beyond_kwh_probing')->countAllResults() : 0;
        $penawaranCount = $db->tableExists('beyond_kwh_penawaran') ? (int) $db->table('beyond_kwh_penawaran')->countAllResults() : 0;
        $spkCount       = $db->tableExists('beyond_kwh_spkpo') ? (int) $db->table('beyond_kwh_spkpo')->countAllResults() : 0;
        $penagihanCount = $db->tableExists('beyond_kwh_penagihan') ? (int) $db->table('beyond_kwh_penagihan')->countAllResults() : 0;
        $bayarCount     = $db->tableExists('beyond_kwh_pembayaran') ? (int) $db->table('beyond_kwh_pembayaran')->countAllResults() : 0;

        // Live Database Metrics for Dashboard KWH (Actual DB counts, no fake numbers)
        $totalInspeksi = $db->tableExists('inspeksi_kubikel')
            ? (int) $db->table('inspeksi_kubikel')->where('deleted_at IS NULL')->countAllResults()
            : 0;

        $totalTemuan = $db->tableExists('inspeksi_kubikel_temuan')
            ? (int) $db->table('inspeksi_kubikel_temuan')->where('deleted_at IS NULL')->countAllResults()
            : 0;

        $countTreatmentTrafo  = 0;
        $countCleaningKubikel = 0;
        $totalNilaiPotensi    = 0;

        if ($db->tableExists('inspeksi_kubikel_temuan')) {
            $countTreatmentTrafo = (int) $db->table('inspeksi_kubikel_temuan')
                ->where('deleted_at IS NULL')
                ->where('jenis_potensi', 'TREATMENT_TRAFO')
                ->countAllResults();

            $countCleaningKubikel = (int) $db->table('inspeksi_kubikel_temuan')
                ->where('deleted_at IS NULL')
                ->where('jenis_potensi', 'CLEANING_KUBIKEL')
                ->countAllResults();

            $sumPotensi = $db->table('inspeksi_kubikel_temuan')
                ->selectSum('nilai_potensi')
                ->where('deleted_at IS NULL')
                ->get()->getRowArray();
            $totalNilaiPotensi = (float) ($sumPotensi['nilai_potensi'] ?? (($countTreatmentTrafo * 5000000) + ($countCleaningKubikel * 4999000)));
        }

        $kwhDashboardStats = [
            'total_inspeksi'         => $totalInspeksi,
            'total_temuan'           => $totalTemuan,
            'count_treatment_trafo'  => $countTreatmentTrafo,
            'count_cleaning_kubikel' => $countCleaningKubikel,
            'total_nilai_potensi'    => $totalNilaiPotensi,
            'nilai_treatment_trafo'  => $countTreatmentTrafo * 5000000,
            'nilai_cleaning_kubikel' => $countCleaningKubikel * 4999000,
        ];

        $pipelineStats = [
            'total_rows'    => ($kpiStats['total'] ?? 0) + $probingCount + $penawaranCount + $spkCount + $penagihanCount + $bayarCount,
            'probing'       => ($kpiStats['belum'] ?? 0) + $probingCount,
            'penawaran'     => ($kpiStats['proses'] ?? 0) + $penawaranCount,
            'spk'           => $spkCount,
            'penawaran_spk' => ($kpiStats['proses'] ?? 0) + $penawaranCount + $spkCount,
            'penagihan'     => $penagihanCount,
            'status_bayar'  => ($kpiStats['selesai'] ?? 0) + $bayarCount,
        ];

        $yoyData = [
            'probing' => [
                '2026' => [8, 12, 3, 2, 0, 9, 10, 19, 23, 5, 0, 0],
                '2025' => [0, 0, 0, 0, 1, 18, 34, 21, 18, 16, 1, 3],
            ],
            'penawaran' => [
                '2026' => [1300000000, 5200000000, 2100000000, 2200000000, 649000000, 1600000000, 1400000000, 3600000000, 1500000000, 680000000, 0, 0],
                '2025' => [0, 0, 0, 0, 0, 307000000, 546000000, 265000000, 188000000, 2200000000, 126000000, 371000000],
            ],
            'spk' => [
                '2026' => [20000000, 34000000, 112000000, 312000000, 1300000000, 1200000000, 29000000, 495000000, 1700000000, 0, 0, 0],
                '2025' => [0, 0, 0, 0, 0, 0, 12000000, 44000000, 73000000, 1700000000, 0, 483000000],
            ],
            'penagihan' => [
                '2026' => [0, 0, 0, 0, 710000000, 355000000, 0, 0, 795000000, 0, 0, 0],
                '2025' => [0, 0, 0, 0, 0, 0, 12000000, 0, 28000000, 79000000, 0, 108000000],
            ],
        ];

        $fourDxData = [
            'lead_measures' => [
                [
                    'uraian'          => 'PROBLING / VISIT PELANGGAN',
                    'target_bulan'    => 20,
                    'minggu_1'        => 2,
                    'minggu_2'        => 3,
                    'minggu_3'        => 0,
                    'minggu_4'        => 0,
                    'total_realisasi' => 5,
                    'persentase'      => 25,
                ],
                [
                    'uraian'          => 'PENAWARAN',
                    'target_bulan'    => 4,
                    'minggu_1'        => 2,
                    'minggu_2'        => 1,
                    'minggu_3'        => 0,
                    'minggu_4'        => 0,
                    'total_realisasi' => 3,
                    'persentase'      => 75,
                ],
                [
                    'uraian'          => 'BROADCAST KE PELANGGAN',
                    'target_bulan'    => 20,
                    'minggu_1'        => 2,
                    'minggu_2'        => 3,
                    'minggu_3'        => 0,
                    'minggu_4'        => 0,
                    'total_realisasi' => 5,
                    'persentase'      => 25,
                ],
                [
                    'uraian'          => 'KONTRAK (SPK/PO)',
                    'target_bulan'    => 1,
                    'minggu_1'        => 0,
                    'minggu_2'        => 0,
                    'minggu_3'        => 0,
                    'minggu_4'        => 0,
                    'total_realisasi' => 0,
                    'persentase'      => 0,
                ],
            ],
            'lag_measures' => [
                [
                    'uraian'          => 'PENDAPATAN DARI LUAR PLN GROUP',
                    'target_setahun'  => 5,
                    'satuan'          => 'RP (Miliar)',
                    'realisasi_ytd'   => 'Rp 5.10 M',
                    'persentase'      => 100,
                ],
                [
                    'uraian'          => 'JUMLAH MARKETING PELANGGAN NON CAPTIVE DAN BUSINESS SOLUTION',
                    'target_setahun'  => 30,
                    'satuan'          => 'KALI',
                    'realisasi_ytd'   => '91',
                    'persentase'      => 100,
                ],
                [
                    'uraian'          => 'PENGEMBANGAN LAYANAN BEYOND KWH TEMATIK PARIWISATA',
                    'target_setahun'  => 1,
                    'satuan'          => 'KONTRAK',
                    'realisasi_ytd'   => '1',
                    'persentase'      => 100,
                ],
                [
                    'uraian'          => 'PENGEMBANGAN LAYANAN KONSTRUKSI DAN PEMELIHARAAN TT/TM',
                    'target_setahun'  => 4,
                    'satuan'          => 'KONTRAK',
                    'realisasi_ytd'   => '14',
                    'persentase'      => 100,
                ],
            ],
        ];

        // AJAX JSON endpoint support for realtime filter without page reload
        if ($this->request->isAJAX() || $this->request->getGet('format') === 'json') {
            return $this->response->setJSON([
                'success'           => true,
                'tableReady'        => $tableReady,
                'kpi'               => $kpiStats,
                'kwhDashboardStats' => $kwhDashboardStats,
                'pipelineStats'     => $pipelineStats,
                'yoyData'           => $yoyData,
                'fourDxData'        => $fourDxData,
                'monthlyTrend'      => $monthlyTrend,
                'statusDist'        => $statusDist,
                'priorityDist'      => $priorityDist,
                'ulpRanking'        => $ulpRanking,
                'rekomendasi'       => $rekomendasi,
                'total_rows'        => count($rekomendasi),
            ]);
        }

        return view('kubikel_inspeksi/beyond_kwh/index', [
            'rekomendasi'       => $rekomendasi,
            'kpi'               => $kpiStats,
            'kwhDashboardStats' => $kwhDashboardStats,
            'pipelineStats'     => $pipelineStats,
            'yoyData'           => $yoyData,
            'fourDxData'        => $fourDxData,
            'monthlyTrend'      => $monthlyTrend,
            'statusDist'        => $statusDist,
            'priorityDist'      => $priorityDist,
            'ulpRanking'        => $ulpRanking,
            'ulps'              => $ulps,
            'penyulangs'        => $penyulangs,
            'officers'          => $officers,
            'filters'           => $filters,
            'tableReady'        => $tableReady,
            'role'              => $role,
            'isAdmin'           => $isAdmin,
        ]);
    }

    public function detailRekomendasi(int $id)
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $isAdmin = in_array($role, ['administrator', 'admin', 'admin_pusat'], true);

        if ($role !== 'beyond_kwh' && !$isAdmin) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $query = $this->rekomendasiModel
            ->select('inspeksi_kubikel_rekomendasi.id AS rekomendasi_id, inspeksi_kubikel_rekomendasi.temuan_id, inspeksi_kubikel_rekomendasi.penerima, inspeksi_kubikel_rekomendasi.penerima_user_id, inspeksi_kubikel_rekomendasi.deskripsi AS deskripsi_rekomendasi, inspeksi_kubikel_rekomendasi.status AS status_rekomendasi, inspeksi_kubikel_rekomendasi.waktu_kirim, inspeksi_kubikel_rekomendasi.waktu_baca, inspeksi_kubikel_rekomendasi.waktu_selesai, inspeksi_kubikel_rekomendasi.catatan_penerima, inspeksi_kubikel_temuan.inspeksi_kubikel_id AS inspeksi_id, inspeksi_kubikel_temuan.checklist_id, inspeksi_kubikel_temuan.komponen, inspeksi_kubikel_temuan.fasa, inspeksi_kubikel_temuan.posisi, inspeksi_kubikel_temuan.deskripsi_temuan, inspeksi_kubikel_temuan.urgensi, inspeksi_kubikel_temuan.status_temuan, inspeksi_kubikel.nomor_inspeksi, inspeksi_kubikel.tgl_inspeksi, inspeksi_kubikel.status AS status_inspeksi, users.nama_pegawai AS penerima_nama')
            ->join('inspeksi_kubikel_temuan', 'inspeksi_kubikel_temuan.id = inspeksi_kubikel_rekomendasi.temuan_id')
            ->join('inspeksi_kubikel', 'inspeksi_kubikel.id = inspeksi_kubikel_temuan.inspeksi_kubikel_id')
            ->join('users', 'users.id = inspeksi_kubikel_rekomendasi.penerima_user_id', 'left')
            ->where('inspeksi_kubikel_rekomendasi.id', $id)
            ->where('inspeksi_kubikel_rekomendasi.penerima', 'BEYOND_KWH')
            ->where('inspeksi_kubikel_temuan.deleted_at', null)
            ->where('inspeksi_kubikel.deleted_at', null);

        if (!$isAdmin) {
            $query->where('inspeksi_kubikel_rekomendasi.penerima_user_id', $userId);
        }

        $rekomendasi = $query->first();

        if (!$rekomendasi) {
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh'))->with('error', 'Rekomendasi tidak ditemukan.');
        }
        $rekomendasi['id'] = (int)$rekomendasi['rekomendasi_id'];

        // Tandai sebagai dibaca jika dibuka oleh penerima yang ditugaskan
        if ($rekomendasi['status_rekomendasi'] === 'TERKIRIM' && (int)$rekomendasi['penerima_user_id'] === $userId) {
            $this->rekomendasiModel->markAsRead($id, $userId);
            $rekomendasi['status_rekomendasi'] = 'DIBACA';
            $rekomendasi['waktu_baca'] = date('Y-m-d H:i:s');
        }

        $foto = [];
        if (!empty($rekomendasi['checklist_id']) && $this->fotoModel->db->tableExists('inspeksi_kubikel_foto')) {
            $foto = $this->fotoModel
                ->where('inspeksi_kubikel_id', (int)$rekomendasi['inspeksi_id'])
                ->where('checklist_id', (int)$rekomendasi['checklist_id'])
                ->findAll();
        }

        $db = \Config\Database::connect();
        $probingList = $db->tableExists('beyond_kwh_probing') ? $db->table('beyond_kwh_probing')->where('rekomendasi_id', $id)->orWhere('temuan_id', $rekomendasi['temuan_id'])->orderBy('tanggal_probing', 'DESC')->get()->getResultArray() : [];
        $penawaranList = $db->tableExists('beyond_kwh_penawaran') ? $db->table('beyond_kwh_penawaran')->where('rekomendasi_id', $id)->orWhere('temuan_id', $rekomendasi['temuan_id'])->orderBy('tanggal_penawaran', 'DESC')->get()->getResultArray() : [];
        $spkpoList = $db->tableExists('beyond_kwh_spkpo') ? $db->table('beyond_kwh_spkpo')->where('rekomendasi_id', $id)->orWhere('temuan_id', $rekomendasi['temuan_id'])->orderBy('tanggal_spkpo', 'DESC')->get()->getResultArray() : [];
        $penagihanList = $db->tableExists('beyond_kwh_penagihan') ? $db->table('beyond_kwh_penagihan')->where('rekomendasi_id', $id)->orWhere('temuan_id', $rekomendasi['temuan_id'])->orderBy('tanggal_penagihan', 'DESC')->get()->getResultArray() : [];
        $pembayaranList = $db->tableExists('beyond_kwh_pembayaran') ? $db->table('beyond_kwh_pembayaran')->where('rekomendasi_id', $id)->orWhere('temuan_id', $rekomendasi['temuan_id'])->orderBy('tanggal_pembayaran', 'DESC')->get()->getResultArray() : [];

        return view('kubikel_inspeksi/beyond_kwh/detail', [
            'rekomendasi'    => $rekomendasi,
            'foto'           => $foto,
            'role'           => $role,
            'isAdmin'        => $isAdmin,
            'probingList'    => $probingList,
            'penawaranList'  => $penawaranList,
            'spkpoList'      => $spkpoList,
            'penagihanList'  => $penagihanList,
            'pembayaranList' => $pembayaranList,
        ]);
    }

    public function updateRekomendasiStatus(int $id)
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userId = (int)$session->get('user_id');
        $isAdmin = in_array($role, ['administrator', 'admin', 'admin_pusat'], true);

        if ($role !== 'beyond_kwh' && !$isAdmin) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Akses ditolak.']);
        }

        $rekomendasi = $this->rekomendasiModel->find($id);
        if (!$rekomendasi || $rekomendasi['penerima'] !== 'BEYOND_KWH' || (!$isAdmin && (int)$rekomendasi['penerima_user_id'] !== $userId)) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Rekomendasi tidak ditemukan.']);
        }
        $temuan = $this->temuanModel->find((int)$rekomendasi['temuan_id']);
        if (!$temuan || !$this->inspeksiModel->find((int)$temuan['inspeksi_kubikel_id'])) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Rekomendasi tidak ditemukan.']);
        }

        $newStatus = $this->request->getPost('status');
        $allowedStatuses = ['DIBACA', 'DIPROSES', 'SELESAI', 'DITOLAK'];

        if (!in_array($newStatus, $allowedStatuses, true)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Status tidak valid.']);
        }

        $transitions = [
            'TERKIRIM' => ['DIBACA', 'DIPROSES', 'DITOLAK'],
            'DIBACA'   => ['DIPROSES', 'DITOLAK'],
            'DIPROSES' => ['SELESAI', 'DITOLAK'],
            'SELESAI'  => [],
            'DITOLAK'  => [],
        ];
        if (!in_array($newStatus, $transitions[$rekomendasi['status']] ?? [], true)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Transisi status rekomendasi tidak diizinkan.']);
        }

        if (!$this->validate(['catatan_penerima' => 'permit_empty|max_length[5000]'])) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Catatan tindak lanjut tidak valid.', 'errors' => $this->validator->getErrors()]);
        }

        $updateData = [
            'status'          => $newStatus,
            'catatan_penerima' => $this->request->getPost('catatan_penerima'),
        ];

        if ($newStatus === 'SELESAI') {
            $updateData['waktu_selesai'] = date('Y-m-d H:i:s');
        } elseif ($newStatus === 'DIBACA' && $rekomendasi['status'] === 'TERKIRIM') {
            $updateData['waktu_baca'] = date('Y-m-d H:i:s');
        }

        if (!$this->rekomendasiModel->update($id, $updateData)) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Gagal memperbarui status rekomendasi']);
        }

        // Update status temuan terkait
        $temuanId = $rekomendasi['temuan_id'];
        if ($newStatus === 'SELESAI') {
            $this->temuanModel->update($temuanId, ['status_temuan' => 'DIVERIFIKASI']);
        } elseif ($newStatus === 'DITOLAK') {
            $this->temuanModel->update($temuanId, ['status_temuan' => 'DITOLAK']);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Status rekomendasi diperbarui']);
    }
}