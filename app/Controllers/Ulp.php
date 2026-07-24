<?php

namespace App\Controllers;

use App\Services\MasterDataService;
use App\Repositories\UlpRepository;

class Ulp extends BaseController
{
    private MasterDataService $masterDataService;
    private UlpRepository $ulpRepository;

    public function __construct()
    {
        $this->masterDataService = new MasterDataService();
        $this->ulpRepository = new UlpRepository();
    }

    public function index()
    {
        $ulps = $this->ulpRepository->findAll();
        return view('ulps/index', ['ulps' => $ulps]);
    }

    public function create()
    {
        return view('ulps/create');
    }

    public function store()
    {
        $rules = [
            'kode_ulp' => 'required|is_unique[ulps.kode_ulp]|max_length[50]',
            'nama_ulp' => 'required|max_length[150]',
            'status'   => 'required|in_list[AKTIF,NONAKTIF]'
        ];

        if (!$this->validate($rules)) {
            return view('ulps/create', [
                'validation' => $this->validator
            ]);
        }

        $data = [
            'kode_ulp' => trim($this->request->getPost('kode_ulp')),
            'nama_ulp' => trim($this->request->getPost('nama_ulp')),
            'status'   => $this->request->getPost('status')
        ];

        if ($this->masterDataService->createUlp($data)) {
            return redirect()->to(site_url('ulps'))->with('success', 'Master ULP berhasil ditambahkan.');
        }

        return redirect()->to(site_url('ulps/create'))->with('error', 'Gagal menambahkan ULP.');
    }

    public function edit(int $id)
    {
        $ulp = $this->ulpRepository->find($id);
        if (!$ulp) {
            return redirect()->to(site_url('ulps'))->with('error', 'ULP tidak ditemukan.');
        }

        return view('ulps/edit', ['ulp' => $ulp]);
    }

    public function update(int $id)
    {
        $ulp = $this->ulpRepository->find($id);
        if (!$ulp) {
            return redirect()->to(site_url('ulps'))->with('error', 'ULP tidak ditemukan.');
        }

        $rules = [
            'kode_ulp' => "required|is_unique[ulps.kode_ulp,id,{$id}]|max_length[50]",
            'nama_ulp' => 'required|max_length[150]',
            'status'   => 'required|in_list[AKTIF,NONAKTIF]'
        ];

        if (!$this->validate($rules)) {
            return view('ulps/edit', [
                'ulp' => $ulp,
                'validation' => $this->validator
            ]);
        }

        $data = [
            'kode_ulp' => trim($this->request->getPost('kode_ulp')),
            'nama_ulp' => trim($this->request->getPost('nama_ulp')),
            'status'   => $this->request->getPost('status')
        ];

        if ($this->masterDataService->updateUlp($id, $data)) {
            return redirect()->to(site_url('ulps'))->with('success', 'Master ULP berhasil diperbarui.');
        }

        return redirect()->to(site_url('ulps/edit/' . $id))->with('error', 'Gagal memperbarui ULP.');
    }

    public function delete(int $id)
    {
        $isAjax = $this->request->isAJAX() || $this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest' || str_contains($this->request->getHeaderLine('Accept'), 'json');
        log_message('info', "[DELETE_ULP] Controller dipanggil | ID Received: {$id} | Method: " . $this->request->getMethod());

        try {
            if ($this->masterDataService->deleteUlp($id)) {
                log_message('info', "[DELETE_ULP] ULP ID: {$id} berhasil dihapus.");
                if ($isAjax) {
                    return $this->response->setJSON(['success' => true, 'message' => 'Master ULP berhasil dihapus.']);
                }
                return redirect()->to(site_url('ulps'))->with('success', 'Master ULP berhasil dihapus.');
            }

            log_message('error', "[DELETE_ULP_FAIL] Gagal menghapus ULP ID: {$id}");
            if ($isAjax) {
                return $this->response->setJSON(['success' => false, 'message' => 'Gagal menghapus ULP. Data mungkin masih digunakan.']);
            }
            return redirect()->to(site_url('ulps'))->with('error', 'Gagal menghapus ULP. Data mungkin masih digunakan.');
        } catch (\Throwable $e) {
            log_message('error', "[DELETE_ULP_EXCEPTION] " . $e->getMessage());
            if ($isAjax) {
                return $this->response->setJSON(['success' => false, 'message' => 'Gagal: Data ULP ini masih terhubung dengan Penyulang/User/Temuan.']);
            }
            return redirect()->to(site_url('ulps'))->with('error', 'Gagal: Data ULP ini masih terhubung dengan Penyulang/User/Temuan.');
        }
    }
}
