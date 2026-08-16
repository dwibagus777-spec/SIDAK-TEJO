<?php

namespace App\Controllers;

use App\Repositories\AssetRepository;
use App\Services\AssetService;
use App\Models\UlpModel;
use App\Models\PenyulangModel;
use App\Models\SectionModel;

class AssetController extends BaseController
{
    private AssetRepository $repository;
    private AssetService $service;

    public function __construct()
    {
        $this->repository = new AssetRepository();
        $this->service = new AssetService();
    }

    public function index()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($userUlpId)) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $filters = [
            'ulp_id'      => $this->request->getGet('ulp_id'),
            'penyulang_id'=> $this->request->getGet('penyulang_id'),
            'section_id'  => $this->request->getGet('section_id'),
            'jenis_asset' => $this->request->getGet('jenis_asset'),
            'status'      => $this->request->getGet('status'),
            'search'      => $this->request->getGet('search'),
        ];

        $assets = $this->repository->getFilteredAssets($filters, $ulpIdFilter);
        $stats  = $this->repository->getAssetStats($ulpIdFilter);

        $ulpModel = new UlpModel();
        $penyulangModel = new PenyulangModel();

        return view('assets/index', [
            'assets'     => $assets,
            'stats'      => $stats,
            'filters'    => $filters,
            'ulps'       => $ulpModel->where('status', 'AKTIF')->findAll(),
            'penyulangs' => $penyulangModel->where('status', 'AKTIF')->findAll(),
            'userRole'   => $role,
        ]);
    }

    public function detail(int $id)
    {
        $asset = $this->service->getAssetDetail($id);
        if (!$asset) {
            return redirect()->to(site_url('master-assets'))->with('error', 'Data asset tidak ditemukan.');
        }

        $topologyService = new \App\Services\AssetTopologyService();
        $topologyTree = $topologyService->getTopologyTree($id);

        return view('assets/detail', [
            'asset'        => $asset,
            'topologyTree' => $topologyTree,
            'userRole'     => session()->get('user_role'),
        ]);
    }

    public function create()
    {
        if (!check_role(['administrator', 'admin_ulp', 'inspeksi'])) {
            return redirect()->to(site_url('master-assets'))->with('error', 'Akses ditolak.');
        }

        $ulpModel = new UlpModel();
        $penyulangModel = new PenyulangModel();
        $sectionModel = new SectionModel();
        $constructionService = new \App\Services\ConstructionService();

        return view('assets/form', [
            'asset'             => null,
            'ulps'              => $ulpModel->where('status', 'AKTIF')->findAll(),
            'penyulangs'        => $penyulangModel->where('status', 'AKTIF')->findAll(),
            'sections'          => $sectionModel->findAll(),
            'assetTypes'        => $constructionService->getAssetTypes(),
            'constructionTypes' => $constructionService->getConstructionTypes(),
            'parentAssets'      => $this->repository->getFilteredAssets([], null),
            'jenisList'         => ['JTM', 'Gardu', 'Trafo', 'Kubikel', 'LBS', 'LBSM', 'Recloser', 'Sectionalizer', 'Section', 'Penyulang', 'JTR', 'PHB', 'APP', 'Meter', 'Grounding'],
            'generatedKode'     => $this->service->generateKodeAsset('Gardu'),
        ]);
    }

    public function store()
    {
        if (!check_role(['administrator', 'admin_ulp', 'inspeksi'])) {
            return redirect()->to(site_url('master-assets'))->with('error', 'Akses ditolak.');
        }

        $jenis = $this->request->getPost('jenis_asset');
        $kodeAsset = $this->request->getPost('kode_asset') ?: $this->service->generateKodeAsset($jenis);

        $foto = $this->request->getFile('foto');
        $fotoName = null;
        if ($foto && $foto->isValid() && !$foto->hasMoved()) {
            $targetAssetDir = (defined('SIDAK_STORAGE_PATH') ? SIDAK_STORAGE_PATH : FCPATH) . 'uploads/assets/';
            if (!is_dir($targetAssetDir)) {
                mkdir($targetAssetDir, 0755, true);
            }
            $fotoName = 'asset_' . time() . '_' . $foto->getRandomName();
            $foto->move($targetAssetDir, $fotoName);
            $fotoName = 'uploads/assets/' . $fotoName;
        }

        $data = [
            'kode_asset'           => $kodeAsset,
            'nama_asset'           => $this->request->getPost('nama_asset'),
            'jenis_asset'          => $jenis,
            'ulp_id'               => $this->request->getPost('ulp_id') ?: null,
            'penyulang_id'         => $this->request->getPost('penyulang_id') ?: null,
            'section_id'           => $this->request->getPost('section_id') ?: null,
            'parent_asset_id'      => $this->request->getPost('parent_asset_id') ?: null,
            'asset_type_id'        => $this->request->getPost('asset_type_id') ?: null,
            'construction_type_id' => $this->request->getPost('construction_type_id') ?: null,
            'sequence_no'          => $this->request->getPost('sequence_no') ?: null,
            'lokasi'               => $this->request->getPost('lokasi'),
            'latitude'             => $this->request->getPost('latitude'),
            'longitude'            => $this->request->getPost('longitude'),
            'tahun_instalasi'      => $this->request->getPost('tahun_instalasi') ?: date('Y'),
            'installation_date'    => $this->request->getPost('installation_date') ?: null,
            'merk'                 => $this->request->getPost('merk'),
            'type'                 => $this->request->getPost('type'),
            'nomor_seri'           => $this->request->getPost('nomor_seri'),
            'kapasitas'            => $this->request->getPost('kapasitas'),
            'status'               => strtoupper($this->request->getPost('status') ?: 'NORMAL'),
            'foto'                 => $fotoName,
            'qr_code'              => site_url('assets/detail/' . $kodeAsset),
            'barcode'              => $kodeAsset,
            'created_at'           => date('Y-m-d H:i:s'),
            'updated_at'           => date('Y-m-d H:i:s'),
        ];

        $id = $this->repository->insert($data);
        log_activity('CREATE_ASSET', 'Menambah master asset baru: ' . $kodeAsset . ' (' . $data['nama_asset'] . ')');

        // Log initial creation in Asset History
        try {
            (new \App\Services\AssetHistoryService())->logEvent(
                (int)$id,
                \Config\AssetEvent::CREATED,
                null,
                $data['status'],
                $kodeAsset,
                'Master Aset baru berhasil didaftarkan ke sistem SIDAK TEJO.',
                (int)session()->get('user_id')
            );
            (new \App\Services\HealthScoreService())->refreshCachedHealthScore((int)$id);
        } catch (\Throwable $e) {
            log_message('warning', '[AssetHistory] store hook: ' . $e->getMessage());
        }

        return redirect()->to(site_url('assets/detail/' . $id))->with('success', 'Master Asset baru berhasil ditambahkan!');
    }

    public function edit(int $id)
    {
        if (!check_role(['administrator', 'admin_ulp', 'inspeksi'])) {
            return redirect()->to(site_url('master-assets'))->with('error', 'Akses ditolak.');
        }

        $asset = $this->repository->find($id);
        if (!$asset) {
            return redirect()->to(site_url('master-assets'))->with('error', 'Data asset tidak ditemukan.');
        }

        $ulpModel = new UlpModel();
        $penyulangModel = new PenyulangModel();
        $sectionModel = new SectionModel();
        $constructionService = new \App\Services\ConstructionService();

        return view('assets/form', [
            'asset'             => $asset,
            'isEdit'            => true,
            'ulps'              => $ulpModel->where('status', 'AKTIF')->findAll(),
            'penyulangs'        => $penyulangModel->where('status', 'AKTIF')->findAll(),
            'sections'          => $sectionModel->findAll(),
            'assetTypes'        => $constructionService->getAssetTypes(),
            'constructionTypes' => $constructionService->getConstructionTypes(),
            'parentAssets'      => $this->repository->getFilteredAssets([], null),
            'jenisList'         => ['JTM', 'Gardu', 'Trafo', 'Kubikel', 'LBS', 'LBSM', 'Recloser', 'Sectionalizer', 'Section', 'Penyulang', 'JTR', 'PHB', 'APP', 'Meter', 'Grounding'],
            'generatedKode'     => $asset['kode_asset'],
        ]);
    }

    public function update(int $id)
    {
        if (!check_role(['administrator', 'admin_ulp', 'inspeksi'])) {
            return redirect()->to(site_url('master-assets'))->with('error', 'Akses ditolak.');
        }

        $asset = $this->repository->find($id);
        if (!$asset) {
            return redirect()->to(site_url('master-assets'))->with('error', 'Data asset tidak ditemukan.');
        }

        $foto = $this->request->getFile('foto');
        $fotoName = $asset['foto'];
        if ($foto && $foto->isValid() && !$foto->hasMoved()) {
            $targetAssetDir = (defined('SIDAK_STORAGE_PATH') ? SIDAK_STORAGE_PATH : FCPATH) . 'uploads/assets/';
            if (!is_dir($targetAssetDir)) {
                mkdir($targetAssetDir, 0755, true);
            }
            $fotoName = 'asset_' . time() . '_' . $foto->getRandomName();
            $foto->move($targetAssetDir, $fotoName);
            $fotoName = 'uploads/assets/' . $fotoName;
        }

        $data = [
            'nama_asset'           => $this->request->getPost('nama_asset'),
            'jenis_asset'          => $this->request->getPost('jenis_asset'),
            'ulp_id'               => $this->request->getPost('ulp_id') ?: null,
            'penyulang_id'         => $this->request->getPost('penyulang_id') ?: null,
            'section_id'           => $this->request->getPost('section_id') ?: null,
            'parent_asset_id'      => $this->request->getPost('parent_asset_id') ?: null,
            'asset_type_id'        => $this->request->getPost('asset_type_id') ?: null,
            'construction_type_id' => $this->request->getPost('construction_type_id') ?: null,
            'sequence_no'          => $this->request->getPost('sequence_no') ?: null,
            'lokasi'               => $this->request->getPost('lokasi'),
            'latitude'             => $this->request->getPost('latitude'),
            'longitude'            => $this->request->getPost('longitude'),
            'tahun_instalasi'      => $this->request->getPost('tahun_instalasi') ?: date('Y'),
            'installation_date'    => $this->request->getPost('installation_date') ?: null,
            'merk'                 => $this->request->getPost('merk'),
            'type'                 => $this->request->getPost('type'),
            'nomor_seri'           => $this->request->getPost('nomor_seri'),
            'kapasitas'            => $this->request->getPost('kapasitas'),
            'foto'                 => $fotoName,
            'updated_at'           => date('Y-m-d H:i:s'),
        ];

        $this->repository->update($id, $data);
        log_activity('UPDATE_ASSET', 'Mengubah data master asset: ' . $asset['kode_asset'] . ' (' . $data['nama_asset'] . ')');

        // Log EDIT event in Asset History (State Machine status remains unchanged)
        try {
            (new \App\Services\AssetHistoryService())->logEvent(
                $id,
                \Config\AssetEvent::EDIT,
                $asset['status'],
                $asset['status'],
                $asset['kode_asset'],
                'Perubahan spesifikasi/data master aset oleh user.',
                (int)session()->get('user_id')
            );
            (new \App\Services\HealthScoreService())->refreshCachedHealthScore($id);
        } catch (\Throwable $e) {
            log_message('warning', '[AssetHistory] update hook: ' . $e->getMessage());
        }

        return redirect()->to(site_url('assets/detail/' . $id))->with('success', 'Data Master Asset berhasil diperbarui!');
    }

    /**
     * Supervisor Verification PASS Workflow (Transition MENUNGGU_VERIFIKASI -> NORMAL)
     */
    public function verifyPass(int $id)
    {
        $supervisorId = (int)session()->get('user_id');
        $catatan = $this->request->getPost('catatan');

        $service = new \App\Services\AssetVerificationService();
        if ($service->verifyInspectionPass($id, $supervisorId, $catatan)) {
            log_activity('VERIFY_ASSET_PASS', "Inspeksi Supervisor LULUS untuk Asset ID {$id}");
            if ($this->request->isAJAX()) {
                return $this->jsonResponse(['success' => true, 'message' => 'Verifikasi Inspeksi LULUS. Status aset kembali NORMAL.']);
            }
            return redirect()->to(site_url('assets/detail/' . $id))->with('success', 'Verifikasi Inspeksi LULUS. Status aset kembali NORMAL.');
        }

        if ($this->request->isAJAX()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Gagal memproses verifikasi.'], 400);
        }
        return redirect()->to(site_url('assets/detail/' . $id))->with('error', 'Gagal memproses verifikasi.');
    }

    /**
     * Supervisor Verification FAIL Workflow (Transition MENUNGGU_VERIFIKASI -> BERMASALAH)
     */
    public function verifyFail(int $id)
    {
        $supervisorId = (int)session()->get('user_id');
        $catatan = $this->request->getPost('catatan');

        $service = new \App\Services\AssetVerificationService();
        if ($service->verifyInspectionFail($id, $supervisorId, $catatan)) {
            log_activity('VERIFY_ASSET_FAIL', "Inspeksi Supervisor GAGAL untuk Asset ID {$id}");
            if ($this->request->isAJAX()) {
                return $this->jsonResponse(['success' => true, 'message' => 'Inspeksi GAGAL. Status aset dikembalikan ke BERMASALAH.']);
            }
            return redirect()->to(site_url('assets/detail/' . $id))->with('success', 'Inspeksi GAGAL. Status aset dikembalikan ke BERMASALAH.');
        }

        if ($this->request->isAJAX()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Gagal memproses verifikasi.'], 400);
        }
        return redirect()->to(site_url('assets/detail/' . $id))->with('error', 'Gagal memproses verifikasi.');
    }

    /**
     * Fetch Timeline Audit History via AJAX
     */
    public function history(int $id)
    {
        $service = new \App\Services\AssetHistoryService();
        $timeline = $service->getAssetTimeline($id);
        return $this->jsonResponse(['success' => true, 'timeline' => $timeline]);
    }

    /**
     * Soft Delete Asset with Mandatory Reason
     */
    public function softDelete(int $id)
    {
        $userId = (int)session()->get('user_id');
        $reason = $this->request->getPost('reason') ?: 'Soft delete by user';

        $service = new \App\Services\AssetLifecycleService();
        if ($service->softDeleteAsset($id, $userId, $reason)) {
            log_activity('DELETE_ASSET', "Soft delete asset ID {$id}. Alasan: {$reason}");
            if ($this->request->isAJAX()) {
                return $this->jsonResponse(['success' => true, 'message' => 'Master Asset berhasil di-soft delete.']);
            }
            return redirect()->to(site_url('master-assets'))->with('success', 'Master Asset berhasil di-soft delete.');
        }

        if ($this->request->isAJAX()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Gagal menghapus asset.'], 400);
        }
        return redirect()->to(site_url('master-assets'))->with('error', 'Gagal menghapus asset.');
    }

    /**
     * Restore Soft-Deleted Asset (Admin only)
     */
    public function restore(int $id)
    {
        if (!check_role(['administrator', 'admin'])) {
            if ($this->request->isAJAX()) {
                return $this->jsonResponse(['success' => false, 'message' => 'Akses ditolak.'], 403);
            }
            return redirect()->to(site_url('master-assets'))->with('error', 'Akses ditolak.');
        }

        $adminId = (int)session()->get('user_id');
        $service = new \App\Services\AssetLifecycleService();
        if ($service->restoreAsset($id, $adminId)) {
            log_activity('RESTORE_ASSET', "Restore asset ID {$id} oleh Admin");
            if ($this->request->isAJAX()) {
                return $this->jsonResponse(['success' => true, 'message' => 'Master Asset berhasil dipulihkan.']);
            }
            return redirect()->to(site_url('assets/detail/' . $id))->with('success', 'Master Asset berhasil dipulihkan.');
        }

        if ($this->request->isAJAX()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Gagal memulihkan asset.'], 400);
        }
        return redirect()->to(site_url('master-assets'))->with('error', 'Gagal memulihkan asset.');
    }
}
