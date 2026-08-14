<?php

namespace App\Controllers;

use App\Repositories\WorkOrderRepository;
use App\Services\WorkOrderService;
use App\Repositories\AssetRepository;
use App\Models\TemuanModel;
use App\Models\UlpModel;

class WorkOrderController extends BaseController
{
    private WorkOrderRepository $repository;
    private WorkOrderService $service;

    public function __construct()
    {
        $this->repository = new WorkOrderRepository();
        $this->service = new WorkOrderService();
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
            'ulp_id'    => $this->request->getGet('ulp_id'),
            'status'    => $this->request->getGet('status'),
            'prioritas' => $this->request->getGet('prioritas'),
            'pelaksana' => $this->request->getGet('pelaksana'),
            'search'    => $this->request->getGet('search'),
        ];

        $workOrders = $this->repository->getFilteredWorkOrders($filters, $ulpIdFilter);
        $stats      = $this->repository->getWOStats($ulpIdFilter);

        $ulpModel = new UlpModel();

        return view('work_orders/index', [
            'workOrders' => $workOrders,
            'stats'      => $stats,
            'filters'    => $filters,
            'ulps'       => $ulpModel->where('status', 'AKTIF')->findAll(),
            'userRole'   => $role,
        ]);
    }

    public function detail(int $id)
    {
        $wo = $this->repository->find($id);
        if (!$wo) {
            return redirect()->to(site_url('work-orders'))->with('error', 'Work Order tidak ditemukan.');
        }

        return view('work_orders/detail', [
            'wo'       => $wo,
            'userRole' => session()->get('user_role'),
        ]);
    }

    public function create()
    {
        if (!check_role(['administrator', 'admin_ulp', 'supervisor_ulp', 'supervisor_up3', 'inspeksi'])) {
            return redirect()->to(site_url('work-orders'))->with('error', 'Akses ditolak.');
        }

        $assetRepo = new AssetRepository();
        $temuanModel = new TemuanModel();

        $assets  = $assetRepo->getFilteredAssets([], null);
        $temuans = $temuanModel->where('deleted_at IS NULL')->orderBy('id', 'DESC')->findAll(100);

        return view('work_orders/form', [
            'wo'             => null,
            'assets'         => $assets,
            'temuans'        => $temuans,
            'generatedNomor' => $this->service->generateNomorWO(),
            'selectedAsset'  => $this->request->getGet('asset_id'),
            'selectedTemuan' => $this->request->getGet('temuan_id'),
        ]);
    }

    public function store()
    {
        if (!check_role(['administrator', 'admin_ulp', 'supervisor_ulp', 'supervisor_up3', 'inspeksi'])) {
            return redirect()->to(site_url('work-orders'))->with('error', 'Akses ditolak.');
        }

        $session = session();
        $userName = $session->get('user_name') ?: 'User';

        $data = [
            'nomor_wo'       => $this->request->getPost('nomor_wo') ?: $this->service->generateNomorWO(),
            'temuan_id'      => $this->request->getPost('temuan_id') ?: null,
            'asset_id'       => $this->request->getPost('asset_id') ?: null,
            'judul_wo'       => $this->request->getPost('judul_wo'),
            'detail_wo'      => $this->request->getPost('detail_wo'),
            'assigned_to'    => $this->request->getPost('assigned_to'),
            'assigned_team'  => $this->request->getPost('assigned_team'),
            'pelaksana'      => $this->request->getPost('pelaksana') ?: 'INSPEKSI',
            'prioritas'      => strtoupper($this->request->getPost('prioritas') ?: 'MEDIUM'),
            'status'         => strtoupper($this->request->getPost('status') ?: 'OPEN'),
            'target_selesai' => $this->request->getPost('target_selesai') ?: null,
            'created_by'     => $userName,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        $checklistsRaw = $this->request->getPost('checklists');
        $checklists = is_array($checklistsRaw) ? array_filter($checklistsRaw) : null;

        $woId = $this->service->createWorkOrder($data, $checklists);
        log_activity('CREATE_WORK_ORDER', 'Menerbitkan Work Order baru: ' . $data['nomor_wo'] . ' (' . $data['judul_wo'] . ')');

        return redirect()->to(site_url('work-orders/detail/' . $woId))->with('success', 'Work Order baru berhasil diterbitkan!');
    }

    public function updateStatus(int $id)
    {
        $status  = $this->request->getPost('status');
        $catatan = $this->request->getPost('catatan');
        $userName= session()->get('user_name') ?: 'User';

        $fotoSebel  = $this->request->getFile('foto_sebelum');
        $fotoProses = $this->request->getFile('foto_proses');
        $fotoSesud  = $this->request->getFile('foto_sesudah');

        $fNameSebel  = null;
        $fNameProses = null;
        $fNameSesud  = null;

        $targetWoDir = (defined('SIDAK_STORAGE_PATH') ? SIDAK_STORAGE_PATH : FCPATH) . 'uploads/wo/';
        if (!is_dir($targetWoDir)) {
            mkdir($targetWoDir, 0755, true);
        }

        if ($fotoSebel && $fotoSebel->isValid() && !$fotoSebel->hasMoved()) {
            $fNameSebel = 'wo_before_' . time() . '_' . $fotoSebel->getRandomName();
            $fotoSebel->move($targetWoDir, $fNameSebel);
            $fNameSebel = 'uploads/wo/' . $fNameSebel;
        }

        if ($fotoProses && $fotoProses->isValid() && !$fotoProses->hasMoved()) {
            $fNameProses = 'wo_process_' . time() . '_' . $fotoProses->getRandomName();
            $fotoProses->move($targetWoDir, $fNameProses);
            $fNameProses = 'uploads/wo/' . $fNameProses;
        }

        if ($fotoSesud && $fotoSesud->isValid() && !$fotoSesud->hasMoved()) {
            $fNameSesud = 'wo_after_' . time() . '_' . $fotoSesud->getRandomName();
            $fotoSesud->move($targetWoDir, $fNameSesud);
            $fNameSesud = 'uploads/wo/' . $fNameSesud;
        }

        $this->service->updateStatus($id, $status, $userName, $catatan, $fNameSebel, $fNameProses, $fNameSesud);
        log_activity('UPDATE_WO_STATUS', 'Update status WO #' . $id . ' menjadi ' . $status);

        return redirect()->to(site_url('work-orders/detail/' . $id))->with('success', 'Status Work Order berhasil diperbarui!');
    }

    public function toggleChecklist(int $chkId)
    {
        $userName = session()->get('user_name') ?: 'User';
        $res = $this->repository->toggleChecklist($chkId, $userName);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => $res]);
        }
        return redirect()->back();
    }

    public function addMaterial(int $woId)
    {
        $data = [
            'wo_id'             => $woId,
            'nama_material'     => $this->request->getPost('nama_material'),
            'jumlah'            => (float)$this->request->getPost('jumlah'),
            'satuan'            => $this->request->getPost('satuan') ?: 'Pcs',
            'harga'             => (float)$this->request->getPost('harga'),
            'status_penggunaan' => strtoupper($this->request->getPost('status_penggunaan') ?: 'TERPAKAI'),
        ];

        $this->repository->addMaterial($data);
        return redirect()->to(site_url('work-orders/detail/' . $woId))->with('success', 'Material pekerjaan berhasil ditambahkan!');
    }
}
