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
            'id_pel'        => 'permit_empty|max_length[100]',
            'tgl_inspeksi'  => 'required|valid_date',
            'waktu_mulai'   => 'permit_empty',
            'waktu_selesai' => 'permit_empty',
            'catatan_umum'  => 'permit_empty',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $nomorInspeksi = $this->inspeksiModel->generateNomorInspeksi();

        $data = [
            'nomor_inspeksi'     => $nomorInspeksi,
            'ulp_id'             => (int)$this->request->getPost('ulp_id'),
            'penyulang_id'       => (int)$this->request->getPost('penyulang_id'),
            'section_id'         => (int)($this->request->getPost('section_id') ?: 0),
            'gardu_induk_id'     => (int)($this->request->getPost('gardu_induk_id') ?: 0),
            'asset_id'           => (int)($this->request->getPost('asset_id') ?: 0),
            'id_pel'             => $this->request->getPost('id_pel'),
            'inspector_user_id'  => $userId,
            'tgl_inspeksi'       => $this->request->getPost('tgl_inspeksi'),
            'waktu_mulai'        => $this->request->getPost('waktu_mulai'),
            'waktu_selesai'      => $this->request->getPost('waktu_selesai'),
            'catatan_umum'       => $this->request->getPost('catatan_umum'),
            'status'             => 'DRAFT',
            'status_temuan'      => 'TIDAK_ADA',
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

        return redirect()->to(site_url('kubikel-inspeksi/edit/' . $inspeksiId))
            ->with('success', 'Inspeksi berhasil dibuat. Nomor: ' . $nomorInspeksi);
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
            'id_pel'        => 'permit_empty|max_length[100]',
            'tgl_inspeksi'  => 'required|valid_date',
            'waktu_mulai'   => 'permit_empty',
            'waktu_selesai' => 'permit_empty',
            'catatan_umum'  => 'permit_empty',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $data = [
            'ulp_id'             => (int)$this->request->getPost('ulp_id'),
            'penyulang_id'       => (int)$this->request->getPost('penyulang_id'),
            'section_id'         => (int)($this->request->getPost('section_id') ?: 0),
            'gardu_induk_id'     => (int)($this->request->getPost('gardu_induk_id') ?: 0),
            'asset_id'           => (int)($this->request->getPost('asset_id') ?: 0),
            'id_pel'             => $this->request->getPost('id_pel'),
            'tgl_inspeksi'       => $this->request->getPost('tgl_inspeksi'),
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
            'deskripsi_temuan'     => 'required',
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
        if ($this->request->getPost('penerima_rekomendasi') === 'BEYOND_KWH') {
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

        $data = [
            'inspeksi_kubikel_id'  => $id,
            'checklist_id'         => $checklistId ? (int)$checklistId : null,
            'komponen'             => $this->request->getPost('komponen'),
            'fasa'                 => $this->request->getPost('fasa'),
            'posisi'               => $this->request->getPost('posisi'),
            'deskripsi_temuan'     => $this->request->getPost('deskripsi_temuan'),
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
            'deskripsi_temuan'     => 'required',
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
            'deskripsi_temuan' => $temuan['deskripsi_temuan'],
            'urgensi'          => $temuan['urgensi'],
            'rekomendasi'      => $temuan['rekomendasi'],
        ];

        $data = [
            'komponen'             => $this->request->getPost('komponen'),
            'fasa'                 => $this->request->getPost('fasa'),
            'posisi'               => $this->request->getPost('posisi'),
            'deskripsi_temuan'     => $this->request->getPost('deskripsi_temuan'),
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

        if ($isAdmin) {
            $rekomendasi = $this->rekomendasiModel->getAllBeyondRekomendasi();
        } else {
            $rekomendasi = $this->rekomendasiModel->getByPenerimaUser($userId);
        }

        return view('kubikel_inspeksi/beyond_kwh/index', [
            'rekomendasi' => $rekomendasi,
            'role'        => $role,
            'isAdmin'     => $isAdmin,
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

        return view('kubikel_inspeksi/beyond_kwh/detail', [
            'rekomendasi' => $rekomendasi,
            'foto'        => $foto,
            'role'        => $role,
            'isAdmin'     => $isAdmin,
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