<?php

namespace App\Controllers;

use App\Services\MasterDataService;
use App\Repositories\PenyulangRepository;
use App\Repositories\UlpRepository;

class Penyulang extends BaseController
{
    private MasterDataService $masterDataService;
    private PenyulangRepository $penyulangRepository;
    private UlpRepository $ulpRepository;

    public function __construct()
    {
        $this->masterDataService = new MasterDataService();
        $this->penyulangRepository = new PenyulangRepository();
        $this->ulpRepository = new UlpRepository();
    }

    public function index()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $penyulangs = $this->penyulangRepository->getAllWithUlp();

        // Admin ULP hanya bisa melihat penyulang di ULP-nya sendiri
        if ($role === 'admin_ulp' && $userUlpId !== null) {
            $penyulangs = array_filter($penyulangs, function ($p) use ($userUlpId) {
                return (int)$p['ulp_id'] === (int)$userUlpId;
            });
        }

        return view('penyulang/index', ['penyulangs' => $penyulangs]);
    }

    public function create()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        if ($role === 'admin_ulp' && $userUlpId !== null) {
            $ulps = [$this->ulpRepository->find($userUlpId)];
        } else {
            $ulps = $this->ulpRepository->getActiveUlps();
        }

        return view('penyulang/create', ['ulps' => $ulps]);
    }

    public function store()
    {
        $rules = [
            'id_unik_penyulang' => 'required|is_unique[penyulang.id_unik_penyulang]|max_length[100]',
            'kode_penyulang'    => 'required|max_length[100]',
            'nama_penyulang'    => 'required|max_length[150]',
            'ulp_id'            => 'required|is_not_unique[ulps.id]',
            'status'            => 'required|in_list[AKTIF,NONAKTIF]'
        ];

        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        if (!$this->validate($rules)) {
            $ulps = ($role === 'admin_ulp' && $userUlpId !== null) 
                ? [$this->ulpRepository->find($userUlpId)] 
                : $this->ulpRepository->getActiveUlps();

            return view('penyulang/create', [
                'ulps' => $ulps,
                'validation' => $this->validator
            ]);
        }

        // Admin ULP hanya boleh mengisi data untuk ULP-nya sendiri
        $ulpIdInput = (int)$this->request->getPost('ulp_id');
        if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== $ulpIdInput) {
            return redirect()->to(site_url('penyulang/create'))->with('error', 'Anda hanya diizinkan menambahkan Penyulang untuk ULP Anda.');
        }

        // === CEK DUPLIKAT (Jangan sampai double) ===
        $existing = $this->penyulangRepository->where('nama_penyulang', trim($this->request->getPost('nama_penyulang')))
                                              ->where('ulp_id', $ulpIdInput)
                                              ->first();
        if ($existing) {
            $ulps = ($role === 'admin_ulp' && $userUlpId !== null) 
                ? [$this->ulpRepository->find($userUlpId)] 
                : $this->ulpRepository->getActiveUlps();

            return view('penyulang/create', [
                'ulps' => $ulps,
                'error' => 'Penyulang dengan nama tersebut sudah ada di ULP yang dipilih.'
            ]);
        }

        $data = [
            'id_unik_penyulang' => trim($this->request->getPost('id_unik_penyulang')),
            'kode_penyulang'    => trim($this->request->getPost('kode_penyulang')),
            'nama_penyulang'    => trim($this->request->getPost('nama_penyulang')),
            'ulp_id'            => $ulpIdInput,
            'status'            => $this->request->getPost('status')
        ];

        if ($this->masterDataService->createPenyulang($data)) {
            return redirect()->to(site_url('penyulang'))->with('success', 'Master Penyulang berhasil ditambahkan.');
        }

        return redirect()->to(site_url('penyulang/create'))->with('error', 'Gagal menambahkan Penyulang.');
    }

    public function edit(int $id)
    {
        $penyulang = $this->penyulangRepository->find($id);
        if (!$penyulang) {
            return redirect()->to(site_url('penyulang'))->with('error', 'Penyulang tidak ditemukan.');
        }

        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        // Batasan Admin ULP
        if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== (int)$penyulang['ulp_id']) {
            return redirect()->to(site_url('penyulang'))->with('error', 'Anda tidak memiliki akses ke data Penyulang ini.');
        }

        $ulps = ($role === 'admin_ulp' && $userUlpId !== null) 
            ? [$this->ulpRepository->find($userUlpId)] 
            : $this->ulpRepository->getActiveUlps();

        return view('penyulang/edit', [
            'penyulang' => $penyulang,
            'ulps' => $ulps
        ]);
    }

    public function update(int $id)
    {
        $penyulang = $this->penyulangRepository->find($id);
        if (!$penyulang) {
            return redirect()->to(site_url('penyulang'))->with('error', 'Penyulang tidak ditemukan.');
        }

        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        // Batasan Admin ULP
        if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== (int)$penyulang['ulp_id']) {
            return redirect()->to(site_url('penyulang'))->with('error', 'Anda tidak memiliki akses untuk mengubah data Penyulang ini.');
        }

        $rules = [
            'kode_penyulang'    => 'required|max_length[100]',
            'nama_penyulang'    => 'required|max_length[150]',
            'ulp_id'            => 'required|is_not_unique[ulps.id]',
            'status'            => 'required|in_list[AKTIF,NONAKTIF]'
        ];

        if (!$this->validate($rules)) {
            $ulps = ($role === 'admin_ulp' && $userUlpId !== null) 
                ? [$this->ulpRepository->find($userUlpId)] 
                : $this->ulpRepository->getActiveUlps();

            return view('penyulang/edit', [
                'penyulang' => $penyulang,
                'ulps' => $ulps,
                'validation' => $this->validator
            ]);
        }

        $ulpIdInput = (int)$this->request->getPost('ulp_id');
        if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== $ulpIdInput) {
            return redirect()->to(site_url("penyulang/edit/{$id}"))->with('error', 'Anda hanya diizinkan memilih ULP Anda.');
        }

        // === CEK DUPLIKAT (Jangan sampai double) ===
        $existing = $this->penyulangRepository->where('nama_penyulang', trim($this->request->getPost('nama_penyulang')))
                                              ->where('ulp_id', $ulpIdInput)
                                              ->where('id !=', $id)
                                              ->first();
        if ($existing) {
            $ulps = ($role === 'admin_ulp' && $userUlpId !== null) 
                ? [$this->ulpRepository->find($userUlpId)] 
                : $this->ulpRepository->getActiveUlps();

            return view('penyulang/edit', [
                'penyulang' => $penyulang,
                'ulps' => $ulps,
                'error' => 'Penyulang dengan nama tersebut sudah ada di ULP yang dipilih.'
            ]);
        }

        // Note: id_unik_penyulang tidak boleh diubah (PERMANEN)
        $data = [
            'kode_penyulang' => trim($this->request->getPost('kode_penyulang')),
            'nama_penyulang' => trim($this->request->getPost('nama_penyulang')),
            'ulp_id'         => $ulpIdInput,
            'status'         => $this->request->getPost('status')
        ];

        if ($this->masterDataService->updatePenyulang($id, $data)) {
            return redirect()->to(site_url('penyulang'))->with('success', 'Master Penyulang berhasil diperbarui.');
        }

        return redirect()->to(site_url('penyulang/edit/' . $id))->with('error', 'Gagal memperbarui Penyulang.');
    }

    public function delete(int $id)
    {
        $penyulang = $this->penyulangRepository->find($id);
        if (!$penyulang) {
            return redirect()->to(site_url('penyulang'))->with('error', 'Penyulang tidak ditemukan.');
        }

        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== (int)$penyulang['ulp_id']) {
            return redirect()->to(site_url('penyulang'))->with('error', 'Anda tidak memiliki hak akses untuk menghapus data Penyulang ini.');
        }

        if ($this->masterDataService->deletePenyulang($id)) {
            return redirect()->to(site_url('penyulang'))->with('success', 'Master Penyulang berhasil dihapus.');
        }

        return redirect()->to(site_url('penyulang'))->with('error', 'Gagal menghapus Penyulang.');
    }
}
