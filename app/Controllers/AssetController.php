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

        $showAll = (int)($this->request->getGet('show_all') ?? 0);

        $hasFilter = !empty($filters['ulp_id']) ||
                     !empty($filters['penyulang_id']) ||
                     !empty($filters['section_id']) ||
                     !empty($filters['jenis_asset']) ||
                     !empty($filters['status']) ||
                     !empty($filters['search']) ||
                     $showAll === 1;

        $page    = max(1, (int)($this->request->getGet('page') ?? 1));
        $perPage = max(10, min(200, (int)($this->request->getGet('per_page') ?? 50)));

        $paginationRes = [
            'data'      => [],
            'total'     => 0,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => 1
        ];

        if ($hasFilter) {
            $paginationRes = $this->repository->getFilteredAssetsPaginated($filters, $ulpIdFilter, $page, $perPage);
        }

        $stats = $this->repository->getAssetStats($ulpIdFilter);

        $ulpModel = new UlpModel();
        $penyulangModel = new PenyulangModel();

        $selectedUlpId = !empty($filters['ulp_id']) ? (int)$filters['ulp_id'] : $ulpIdFilter;
        $penyulangs = [];
        if ($selectedUlpId > 0) {
            $penyulangs = $penyulangModel->where('ulp_id', $selectedUlpId)->where('status', 'AKTIF')->orderBy('nama_penyulang', 'ASC')->findAll();
        }

        return view('assets/index', [
            'assets'     => $paginationRes['data'],
            'pagination' => $paginationRes,
            'hasFilter'  => $hasFilter,
            'showAll'    => $showAll,
            'stats'      => $stats,
            'filters'    => $filters,
            'ulps'       => $ulpModel->where('status', 'AKTIF')->orderBy('nama_ulp', 'ASC')->findAll(),
            'penyulangs' => $penyulangs,
            'userRole'   => $role,
        ]);
    }

    /**
     * Mass Soft-Delete Endpoint (Bulk Delete by Selection or Feeder Filter with Strict ULP Authorization)
     */
    public function bulkDelete()
    {
        if (!check_role(['administrator', 'admin_ulp'])) {
            return redirect()->back()->with('error', 'Akses ditolak. Anda tidak memiliki wewenang untuk hapus massal.');
        }

        $session   = session();
        $userRole  = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');
        $ulpScope  = !in_array($userRole, ['administrator', 'admin_pusat']) ? (int)$userUlpId : null;

        $deleteType  = $this->request->getPost('delete_type');
        $confirmTxt  = strtoupper(trim((string)$this->request->getPost('confirm_text')));
        $userId      = (int)$session->get('user_id');

        $selectedRaw = $this->request->getPost('selected_ids') ?? $this->request->getPost('asset_ids');
        $assetIds = [];
        if (is_string($selectedRaw)) {
            $assetIds = array_filter(array_map('intval', explode(',', $selectedRaw)));
        } elseif (is_array($selectedRaw)) {
            $assetIds = array_filter(array_map('intval', $selectedRaw));
        }

        if ($deleteType === 'feeder') {
            $penyulangId = (int)$this->request->getPost('penyulang_id');
            if ($penyulangId <= 0) {
                return redirect()->back()->with('error', 'Pilih penyulang terlebih dahulu untuk menghapus aset penyulang.');
            }
            if ($confirmTxt !== 'HAPUS') {
                return redirect()->back()->with('error', 'Konfirmasi gagal. Anda harus mengetik kata "HAPUS" untuk mengonfirmasi hapus massal penyulang.');
            }

            $filters = ['penyulang_id' => $penyulangId];
            $affected = $this->repository->bulkSoftDeleteByFilter($filters, $ulpScope, $userId, 'MASS_FEEDER_DELETE');
            return redirect()->back()->with('success', "Berhasil melakukan Soft Delete massal pada penyulang. Total {$affected} aset berhasil di-soft delete secara aman.");
        } elseif ($deleteType === 'selected') {
            if (empty($assetIds)) {
                return redirect()->back()->with('error', 'Pilih minimal satu aset untuk dihapus.');
            }

            $affected = $this->repository->bulkSoftDeleteByIds($assetIds, $ulpScope, $userId, 'MASS_SELECTED_DELETE');
            return redirect()->back()->with('success', "Berhasil melakukan Soft Delete massal. Total {$affected} aset terpilih berhasil dihapus secara aman.");
        }

        return redirect()->back()->with('error', 'Metode hapus massal tidak valid.');
    }

    /**
     * Import Batches List Endpoint (ULP Scoped)
     */
    public function importBatches()
    {
        $session   = session();
        $userRole  = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');
        $ulpScope  = !in_array($userRole, ['administrator', 'admin_pusat']) ? (int)$userUlpId : null;

        $db = \Config\Database::connect();
        $batches = [];
        if ($db->tableExists('asset_import_batches')) {
            $builder = $db->table('asset_import_batches b');
            $builder->select('b.*, u.nama_ulp, p.nama_penyulang');
            $builder->join('ulps u', 'b.ulp_id = u.id', 'left');
            $builder->join('penyulang p', 'b.penyulang_id = p.id', 'left');
            if (!empty($ulpScope)) {
                $builder->where('b.ulp_id', $ulpScope);
            }
            $builder->orderBy('b.id', 'DESC');
            $batches = $builder->get()->getResultArray();
        }

        return view('assets/import_batches', [
            'batches'  => $batches,
            'userRole' => session()->get('user_role'),
        ]);
    }

    /**
     * Rollback Specific Import Batch Endpoint (ULP Scoped)
     */
    public function rollbackBatch(int $id)
    {
        if (!check_role(['administrator', 'admin_ulp'])) {
            return redirect()->back()->with('error', 'Akses ditolak.');
        }

        $session   = session();
        $userRole  = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');
        $ulpScope  = !in_array($userRole, ['administrator', 'admin_pusat']) ? (int)$userUlpId : null;

        $userId = (int)$session->get('user_id');
        $res = $this->repository->rollbackImportBatch($id, $userId, $ulpScope);

        if ($res['success'] ?? false) {
            return redirect()->back()->with('success', $res['message']);
        }
        return redirect()->back()->with('error', $res['message'] ?? 'Rollback gagal.');
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
