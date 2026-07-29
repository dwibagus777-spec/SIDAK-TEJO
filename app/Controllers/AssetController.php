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
            return redirect()->to(site_url('assets'))->with('error', 'Data asset tidak ditemukan.');
        }

        return view('assets/detail', [
            'asset'    => $asset,
            'userRole' => session()->get('user_role'),
        ]);
    }

    public function create()
    {
        if (!check_role(['administrator', 'admin_ulp', 'inspeksi'])) {
            return redirect()->to(site_url('assets'))->with('error', 'Akses ditolak.');
        }

        $ulpModel = new UlpModel();
        $penyulangModel = new PenyulangModel();
        $sectionModel = new SectionModel();

        return view('assets/form', [
            'asset'      => null,
            'ulps'       => $ulpModel->where('status', 'AKTIF')->findAll(),
            'penyulangs' => $penyulangModel->where('status', 'AKTIF')->findAll(),
            'sections'   => $sectionModel->findAll(),
            'jenisList'  => ['Gardu', 'Trafo', 'Kubikel', 'LBS', 'Recloser', 'Section', 'Penyulang', 'Tiang', 'JTM', 'JTR', 'PHB', 'APP', 'Meter', 'Grounding'],
            'generatedKode' => $this->service->generateKodeAsset('Gardu'),
        ]);
    }

    public function store()
    {
        if (!check_role(['administrator', 'admin_ulp', 'inspeksi'])) {
            return redirect()->to(site_url('assets'))->with('error', 'Akses ditolak.');
        }

        $jenis = $this->request->getPost('jenis_asset');
        $kodeAsset = $this->request->getPost('kode_asset') ?: $this->service->generateKodeAsset($jenis);

        $foto = $this->request->getFile('foto');
        $fotoName = null;
        if ($foto && $foto->isValid() && !$foto->hasMoved()) {
            $fotoName = 'asset_' . time() . '_' . $foto->getRandomName();
            $foto->move(FCPATH . 'uploads/assets/', $fotoName);
            $fotoName = 'uploads/assets/' . $fotoName;
        }

        $data = [
            'kode_asset'      => $kodeAsset,
            'nama_asset'      => $this->request->getPost('nama_asset'),
            'jenis_asset'     => $jenis,
            'ulp_id'          => $this->request->getPost('ulp_id') ?: null,
            'penyulang_id'    => $this->request->getPost('penyulang_id') ?: null,
            'section_id'      => $this->request->getPost('section_id') ?: null,
            'lokasi'          => $this->request->getPost('lokasi'),
            'latitude'        => $this->request->getPost('latitude'),
            'longitude'       => $this->request->getPost('longitude'),
            'tahun_instalasi' => $this->request->getPost('tahun_instalasi') ?: date('Y'),
            'merk'            => $this->request->getPost('merk'),
            'type'            => $this->request->getPost('type'),
            'nomor_seri'      => $this->request->getPost('nomor_seri'),
            'kapasitas'       => $this->request->getPost('kapasitas'),
            'status'          => strtoupper($this->request->getPost('status') ?: 'NORMAL'),
            'foto'            => $fotoName,
            'qr_code'         => site_url('assets/detail/' . $kodeAsset),
            'barcode'         => $kodeAsset,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ];

        $id = $this->repository->insert($data);
        log_activity('CREATE_ASSET', 'Menambah master asset baru: ' . $kodeAsset . ' (' . $data['nama_asset'] . ')');

        return redirect()->to(site_url('assets/detail/' . $id))->with('success', 'Master Asset baru berhasil ditambahkan!');
    }
}
