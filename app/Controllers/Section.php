<?php

namespace App\Controllers;

use App\Services\MasterDataService;
use App\Repositories\SectionRepository;
use App\Repositories\PenyulangRepository;

class Section extends BaseController
{
    private MasterDataService $masterDataService;
    private SectionRepository $sectionRepository;
    private PenyulangRepository $penyulangRepository;

    public function __construct()
    {
        $this->masterDataService = new MasterDataService();
        $this->sectionRepository = new SectionRepository();
        $this->penyulangRepository = new PenyulangRepository();
    }

    public function index()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $sections = $this->sectionRepository->getAllWithPenyulangAndUlp();

        // Admin ULP hanya bisa melihat section untuk ULP-nya sendiri
        if ($role === 'admin_ulp' && $userUlpId !== null) {
            $sections = array_filter($sections, function ($s) use ($userUlpId) {
                return (int)$s['ulp_id'] === (int)$userUlpId;
            });
        }

        return view('sections/index', ['sections' => $sections]);
    }

    public function create()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $penyulangs = ($role === 'admin_ulp' && $userUlpId !== null) 
            ? $this->penyulangRepository->getActivePenyulangsByUlp((int)$userUlpId) 
            : $this->penyulangRepository->getActivePenyulangs();

        return view('sections/create', ['penyulangs' => $penyulangs]);
    }

    public function store()
    {
        $rules = [
            'penyulang_id' => 'required|is_not_unique[penyulang.id]',
            'nama_section' => 'required|max_length[150]',
            'status'       => 'required|in_list[AKTIF,NONAKTIF]'
        ];

        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        if (!$this->validate($rules)) {
            $penyulangs = ($role === 'admin_ulp' && $userUlpId !== null) 
                ? $this->penyulangRepository->getActivePenyulangsByUlp((int)$userUlpId) 
                : $this->penyulangRepository->getActivePenyulangs();

            return view('sections/create', [
                'penyulangs' => $penyulangs,
                'validation' => $this->validator
            ]);
        }

        $penyulangIdInput = (int)$this->request->getPost('penyulang_id');
        $penyulang = $this->penyulangRepository->find($penyulangIdInput);

        // Batasan Admin ULP
        if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== (int)$penyulang['ulp_id']) {
            return redirect()->to(site_url('sections/create'))->with('error', 'Anda hanya diizinkan memilih Penyulang di ULP Anda.');
        }

        // === CEK DUPLIKAT (Jangan sampai double) ===
        $existing = $this->sectionRepository->where('nama_section', trim($this->request->getPost('nama_section')))
                                            ->where('penyulang_id', $penyulangIdInput)
                                            ->first();
        if ($existing) {
            $penyulangs = ($role === 'admin_ulp' && $userUlpId !== null) 
                ? $this->penyulangRepository->getActivePenyulangsByUlp((int)$userUlpId) 
                : $this->penyulangRepository->getActivePenyulangs();

            return view('sections/create', [
                'penyulangs' => $penyulangs,
                'error' => 'Section dengan nama tersebut sudah ada di penyulang yang dipilih.'
            ]);
        }

        $data = [
            'penyulang_id' => $penyulangIdInput,
            'nama_section' => trim($this->request->getPost('nama_section')),
            'status'       => $this->request->getPost('status')
        ];

        if ($this->masterDataService->createSection($data)) {
            return redirect()->to(site_url('sections'))->with('success', 'Master Section berhasil ditambahkan.');
        }

        return redirect()->to(site_url('sections/create'))->with('error', 'Gagal menambahkan Section.');
    }

    public function edit(int $id)
    {
        $section = $this->sectionRepository->find($id);
        if (!$section) {
            return redirect()->to(site_url('sections'))->with('error', 'Section tidak ditemukan.');
        }

        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        // Check if admin_ulp has access to parent penyulang of this section
        $sectionDetail = $this->sectionRepository->findWithPenyulangAndUlp($id);
        if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== (int)$sectionDetail['ulp_id']) {
            return redirect()->to(site_url('sections'))->with('error', 'Anda tidak memiliki akses ke data Section ini.');
        }

        $penyulangs = ($role === 'admin_ulp' && $userUlpId !== null) 
            ? $this->penyulangRepository->getActivePenyulangsByUlp((int)$userUlpId) 
            : $this->penyulangRepository->getActivePenyulangs();

        return view('sections/edit', [
            'section' => $section,
            'penyulangs' => $penyulangs
        ]);
    }

    public function update(int $id)
    {
        $section = $this->sectionRepository->find($id);
        if (!$section) {
            return redirect()->to(site_url('sections'))->with('error', 'Section tidak ditemukan.');
        }

        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        // Check original access
        $sectionDetail = $this->sectionRepository->findWithPenyulangAndUlp($id);
        if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== (int)$sectionDetail['ulp_id']) {
            return redirect()->to(site_url('sections'))->with('error', 'Anda tidak memiliki akses untuk mengubah data Section ini.');
        }

        $rules = [
            'penyulang_id' => 'required|is_not_unique[penyulang.id]',
            'nama_section' => 'required|max_length[150]',
            'status'       => 'required|in_list[AKTIF,NONAKTIF]'
        ];

        if (!$this->validate($rules)) {
            $penyulangs = ($role === 'admin_ulp' && $userUlpId !== null) 
                ? $this->penyulangRepository->getActivePenyulangsByUlp((int)$userUlpId) 
                : $this->penyulangRepository->getActivePenyulangs();

            return view('sections/edit', [
                'section' => $section,
                'penyulangs' => $penyulangs,
                'validation' => $this->validator
            ]);
        }

        $penyulangIdInput = (int)$this->request->getPost('penyulang_id');
        $penyulang = $this->penyulangRepository->find($penyulangIdInput);

        // Batasan Admin ULP untuk tujuan Penyulang baru
        if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== (int)$penyulang['ulp_id']) {
            return redirect()->to(site_url("sections/edit/{$id}"))->with('error', 'Anda hanya diizinkan memilih Penyulang di ULP Anda.');
        }

        // === CEK DUPLIKAT (Jangan sampai double) ===
        $existing = $this->sectionRepository->where('nama_section', trim($this->request->getPost('nama_section')))
                                            ->where('penyulang_id', $penyulangIdInput)
                                            ->where('id !=', $id)
                                            ->first();
        if ($existing) {
            $penyulangs = ($role === 'admin_ulp' && $userUlpId !== null) 
                ? $this->penyulangRepository->getActivePenyulangsByUlp((int)$userUlpId) 
                : $this->penyulangRepository->getActivePenyulangs();

            return view('sections/edit', [
                'section' => $section,
                'penyulangs' => $penyulangs,
                'error' => 'Section dengan nama tersebut sudah ada di penyulang yang dipilih.'
            ]);
        }

        $data = [
            'penyulang_id' => $penyulangIdInput,
            'nama_section' => trim($this->request->getPost('nama_section')),
            'status'       => $this->request->getPost('status')
        ];

        if ($this->masterDataService->updateSection($id, $data)) {
            return redirect()->to(site_url('sections'))->with('success', 'Master Section berhasil diperbarui.');
        }

        return redirect()->to(site_url('sections/edit/' . $id))->with('error', 'Gagal memperbarui Section.');
    }

    public function delete(int $id)
    {
        $isAjax = $this->request->isAJAX() || $this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest' || str_contains($this->request->getHeaderLine('Accept'), 'json');
        log_message('info', "[DELETE_SECTION] Controller dipanggil | ID Received: {$id} | Method: " . $this->request->getMethod());

        try {
            $sectionDetail = $this->sectionRepository->findWithPenyulangAndUlp($id);
            if (!$sectionDetail) {
                log_message('warning', "[DELETE_SECTION] Section tidak ditemukan | ID: {$id}");
                if ($isAjax) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Section tidak ditemukan.']);
                }
                return redirect()->to(site_url('sections'))->with('error', 'Section tidak ditemukan.');
            }

            $session = session();
            $role = strtolower((string)$session->get('user_role'));
            $userUlpId = $session->get('user_ulp_id');

            if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== (int)$sectionDetail['ulp_id']) {
                log_message('warning', "[DELETE_SECTION] Akses ditolak role admin_ulp | ID: {$id}");
                if ($isAjax) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Anda tidak memiliki hak akses untuk menghapus data Section ini.']);
                }
                return redirect()->to(site_url('sections'))->with('error', 'Anda tidak memiliki hak akses.');
            }

            if ($this->masterDataService->deleteSection($id)) {
                log_message('info', "[DELETE_SECTION] Section ID: {$id} berhasil dihapus.");
                if ($isAjax) {
                    return $this->response->setJSON(['success' => true, 'message' => 'Master Section berhasil dihapus.']);
                }
                return redirect()->to(site_url('sections'))->with('success', 'Master Section berhasil dihapus.');
            }

            log_message('error', "[DELETE_SECTION_FAIL] Gagal menghapus Section ID: {$id}");
            if ($isAjax) {
                return $this->response->setJSON(['success' => false, 'message' => 'Gagal menghapus Section.']);
            }
            return redirect()->to(site_url('sections'))->with('error', 'Gagal menghapus Section.');
        } catch (\Throwable $e) {
            log_message('error', "[DELETE_SECTION_EXCEPTION] " . $e->getMessage());
            if ($isAjax) {
                return $this->response->setJSON(['success' => false, 'message' => 'Gagal: Data Section ini masih terhubung dengan Temuan.']);
            }
            return redirect()->to(site_url('sections'))->with('error', 'Gagal: Data Section ini masih terhubung dengan Temuan.');
        }
    }
}
