<?php

namespace App\Controllers;

use App\Models\EvidenKubikelModel;
use App\Models\EvidenTrafoModel;
use App\Models\FotoEvidenModel;
use App\Models\ManagementTrafoModel;
use App\Repositories\PenyulangRepository;
use App\Repositories\SectionRepository;
use App\Repositories\UlpRepository;

class Eviden extends BaseController
{
    private EvidenKubikelModel $kubikelModel;
    private EvidenTrafoModel $trafoModel;
    private FotoEvidenModel $fotoModel;
    private ManagementTrafoModel $managementModel;
    private UlpRepository $ulpRepository;
    private PenyulangRepository $penyulangRepository;
    private SectionRepository $sectionRepository;

    public function __construct()
    {
        $this->kubikelModel = new EvidenKubikelModel();
        $this->trafoModel = new EvidenTrafoModel();
        $this->fotoModel = new FotoEvidenModel();
        $this->managementModel = new ManagementTrafoModel();
        $this->ulpRepository = new UlpRepository();
        $this->penyulangRepository = new PenyulangRepository();
        $this->sectionRepository = new SectionRepository();
    }

    // ==========================================
    // EVIDEN KUBIKEL CRUD
    // ==========================================

    public function kubikel()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('tb_eviden_kubikel k');
        $builder->select('k.*, p.nama_penyulang, s.nama_section, p.ulp_id');
        $builder->join('penyulang p', 'k.id_penyulang = p.id', 'left');
        $builder->join('sections s', 'k.id_section = s.id', 'left');
        $builder->orderBy('k.id_kubikel', 'DESC');

        // Apply filters
        $ulpId      = $this->request->getGet('ulp_id');
        $penyulangId = $this->request->getGet('penyulang_id');
        $tglMulai   = $this->request->getGet('tgl_mulai');
        $tglSelesai = $this->request->getGet('tgl_selesai');

        if (!empty($ulpId)) {
            $builder->where('p.ulp_id', (int)$ulpId);
        }
        if (!empty($penyulangId)) {
            $builder->where('k.id_penyulang', (int)$penyulangId);
        }
        if (!empty($tglMulai)) {
            $builder->where('k.tgl_input >=', $tglMulai);
        }
        if (!empty($tglSelesai)) {
            $builder->where('k.tgl_input <=', $tglSelesai);
        }
        
        $dataList = $builder->get()->getResultArray();

        // Count photos instead of loading full array to make it light
        foreach ($dataList as &$item) {
            $item['foto_count'] = $this->fotoModel->where('id_parent', $item['id_kubikel'])->where('kategori', 'KUBIKEL')->countAllResults();
        }

        $ulpModel = new \App\Models\UlpModel();
        $penyulangModel = new \App\Models\PenyulangModel();

        return view('eviden/kubikel_index', [
            'dataList'         => $dataList,
            'ulps'             => $ulpModel->where('status', 'AKTIF')->findAll(),
            'penyulangs'       => $penyulangModel->where('status', 'AKTIF')->findAll(),
            'filterUlp'        => $ulpId,
            'filterPenyulang'  => $penyulangId,
            'filterTglMulai'   => $tglMulai,
            'filterTglSelesai' => $tglSelesai
        ]);
    }

    public function kubikelCreate()
    {
        $ulps = $this->ulpRepository->getActiveUlps();
        return view('eviden/kubikel_create', ['ulps' => $ulps]);
    }

    public function kubikelStore()
    {
        $rules = [
            'id_penyulang' => 'required',
            'id_section'   => 'required',
            'nama_gardu'   => 'required|max_length[100]',
            'tgl_input'    => 'required|valid_date[Y-m-d]',
            'keterangan'   => 'required'
        ];

        if (!$this->validate($rules)) {
            $ulps = $this->ulpRepository->getActiveUlps();
            return view('eviden/kubikel_create', [
                'ulps' => $ulps,
                'validation' => $this->validator
            ]);
        }

        $data = [
            'id_penyulang' => (int)$this->request->getPost('id_penyulang'),
            'id_section'   => (int)$this->request->getPost('id_section'),
            'nama_gardu'   => trim($this->request->getPost('nama_gardu')),
            'id_pel'       => trim($this->request->getPost('id_pel')),
            'tgl_input'    => $this->request->getPost('tgl_input'),
            'keterangan'   => trim($this->request->getPost('keterangan')),
        ];

        $insertedId = $this->kubikelModel->insert($data);

        if ($insertedId) {
            // Loop 8 Kategori Foto Kubikel (Total 24 Foto)
            $categories = [
                'foto_kubikel'   => 'FOTO KUBIKEL',
                'foto_merek'     => 'FOTO MEREK',
                'foto_ct'        => 'FOTO CT',
                'foto_vt'        => 'FOTO VT',
                'foto_nameplate' => 'NAMEPLATE',
                'foto_relay'     => 'MERK RELAY',
                'foto_temuan'    => 'TEMUAN',
                'foto_perbaikan' => 'PERBAIKAN'
            ];

            foreach ($categories as $inputName => $jenisFotoLabel) {
                $files = $this->request->getFileMultiple($inputName);
                if ($files) {
                    foreach ($files as $file) {
                        if ($file->isValid() && !$file->hasMoved()) {
                            $newName = $file->getRandomName();
                            $file->move(FCPATH . 'foto/', $newName);

                            $this->fotoModel->insert([
                                'id_parent' => $insertedId,
                                'kategori'  => 'KUBIKEL',
                                'jenis_foto' => $jenisFotoLabel,
                                'nama_file' => $newName
                            ]);
                        }
                    }
                }
            }

            log_activity('CREATE_EVIDEN_KUBIKEL', 'Menambahkan eviden kubikel gardu: ' . $data['nama_gardu']);
            return redirect()->to(site_url('eviden/kubikel'))->with('success', 'Data Eviden Kubikel berhasil ditambahkan.');
        }

        return redirect()->to(site_url('eviden/kubikel/create'))->with('error', 'Gagal menyimpan data eviden kubikel.');
    }

    public function kubikelEdit(int $id)
    {
        $kubikel = $this->kubikelModel->find($id);
        if (!$kubikel) {
            return redirect()->to(site_url('eviden/kubikel'))->with('error', 'Data tidak ditemukan.');
        }

        $penyulang = $this->penyulangRepository->find($kubikel['id_penyulang']);
        $ulpId = $penyulang ? $penyulang['ulp_id'] : null;

        $ulps = $this->ulpRepository->getActiveUlps();
        $penyulangs = $ulpId ? $this->penyulangRepository->getActivePenyulangsByUlp($ulpId) : [];
        $sections = $kubikel['id_penyulang'] ? $this->sectionRepository->getActiveSectionsByPenyulang($kubikel['id_penyulang']) : [];

        $fotos = $this->fotoModel->where('id_parent', $id)->where('kategori', 'KUBIKEL')->findAll();

        return view('eviden/kubikel_edit', [
            'kubikel' => $kubikel,
            'ulps' => $ulps,
            'penyulangs' => $penyulangs,
            'sections' => $sections,
            'fotos' => $fotos,
            'currentUlpId' => $ulpId
        ]);
    }

    public function kubikelUpdate(int $id)
    {
        $kubikel = $this->kubikelModel->find($id);
        if (!$kubikel) {
            return redirect()->to(site_url('eviden/kubikel'))->with('error', 'Data tidak ditemukan.');
        }

        $rules = [
            'id_penyulang' => 'required',
            'id_section'   => 'required',
            'nama_gardu'   => 'required|max_length[100]',
            'tgl_input'    => 'required|valid_date[Y-m-d]',
            'keterangan'   => 'required'
        ];

        if (!$this->validate($rules)) {
            return redirect()->to(site_url('eviden/kubikel/edit/' . $id))->withInput()->with('error', 'Semua kolom wajib diisi.');
        }

        $data = [
            'id_penyulang' => (int)$this->request->getPost('id_penyulang'),
            'id_section'   => (int)$this->request->getPost('id_section'),
            'nama_gardu'   => trim($this->request->getPost('nama_gardu')),
            'id_pel'       => trim($this->request->getPost('id_pel')),
            'tgl_input'    => $this->request->getPost('tgl_input'),
            'keterangan'   => trim($this->request->getPost('keterangan')),
        ];

        if ($this->kubikelModel->update($id, $data)) {
            // Upload Foto Tambahan Baru
            $categories = [
                'foto_kubikel'   => 'FOTO KUBIKEL',
                'foto_merek'     => 'FOTO MEREK',
                'foto_ct'        => 'FOTO CT',
                'foto_vt'        => 'FOTO VT',
                'foto_nameplate' => 'NAMEPLATE',
                'foto_relay'     => 'MERK RELAY',
                'foto_temuan'    => 'TEMUAN',
                'foto_perbaikan' => 'PERBAIKAN'
            ];

            foreach ($categories as $inputName => $jenisFotoLabel) {
                $files = $this->request->getFileMultiple($inputName);
                if ($files) {
                    foreach ($files as $file) {
                        if ($file->isValid() && !$file->hasMoved()) {
                            $newName = $file->getRandomName();
                            $file->move(FCPATH . 'foto/', $newName);

                            $this->fotoModel->insert([
                                'id_parent' => $id,
                                'kategori'  => 'KUBIKEL',
                                'jenis_foto' => $jenisFotoLabel,
                                'nama_file' => $newName
                            ]);
                        }
                    }
                }
            }

            log_activity('UPDATE_EVIDEN_KUBIKEL', 'Memperbarui data eviden kubikel ID: ' . $id);
            return redirect()->to(site_url('eviden/kubikel'))->with('success', 'Data Eviden Kubikel berhasil diperbarui.');
        }

        return redirect()->to(site_url('eviden/kubikel/edit/' . $id))->with('error', 'Gagal memperbarui data.');
    }

    public function kubikelDelete(int $id)
    {
        log_message('info', "[DELETE_EVIDEN_KUBIKEL] Controller dipanggil | ID Received: {$id} | Method: " . $this->request->getMethod());
        try {
            $kubikel = $this->kubikelModel->find($id);
            if (!$kubikel) {
                log_message('warning', "[DELETE_EVIDEN_KUBIKEL] Data tidak ditemukan | ID: {$id}");
                return $this->response->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.']);
            }

            $fotos = $this->fotoModel->where('id_parent', $id)->where('kategori', 'KUBIKEL')->findAll();
            foreach ($fotos as $f) {
                if (file_exists(FCPATH . 'foto/' . $f['nama_file'])) {
                    @unlink(FCPATH . 'foto/' . $f['nama_file']);
                }
                $this->fotoModel->delete($f['id_foto']);
            }

            if ($this->kubikelModel->delete($id)) {
                log_activity('DELETE_EVIDEN_KUBIKEL', 'Menghapus data eviden kubikel ID: ' . $id);
                log_message('info', "[DELETE_EVIDEN_KUBIKEL] Berhasil dihapus | ID: {$id}");
                return $this->response->setJSON(['success' => true, 'message' => 'Data eviden kubikel berhasil dihapus.']);
            }

            log_message('error', "[DELETE_EVIDEN_KUBIKEL_FAIL] Gagal menghapus ID: {$id}");
            return $this->response->setJSON(['success' => false, 'message' => 'Gagal menghapus data.']);
        } catch (\Throwable $e) {
            log_message('error', "[DELETE_EVIDEN_KUBIKEL_EXCEPTION] " . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }
    }

    // ==========================================
    // EVIDEN TRAFO CRUD
    // ==========================================

    public function trafo()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('tb_eviden_trafo t');
        $builder->select('t.*, p.nama_penyulang, s.nama_section, p.ulp_id');
        $builder->join('penyulang p', 't.id_penyulang = p.id', 'left');
        $builder->join('sections s', 't.id_section = s.id', 'left');
        $builder->orderBy('t.id_trafo', 'DESC');
        
        // Apply filters
        $ulpId      = $this->request->getGet('ulp_id');
        $penyulangId = $this->request->getGet('penyulang_id');
        $tglMulai   = $this->request->getGet('tgl_mulai');
        $tglSelesai = $this->request->getGet('tgl_selesai');

        if (!empty($ulpId)) {
            $builder->where('p.ulp_id', (int)$ulpId);
        }
        if (!empty($penyulangId)) {
            $builder->where('t.id_penyulang', (int)$penyulangId);
        }
        if (!empty($tglMulai)) {
            $builder->where('t.tgl_input >=', $tglMulai);
        }
        if (!empty($tglSelesai)) {
            $builder->where('t.tgl_input <=', $tglSelesai);
        }

        $dataList = $builder->get()->getResultArray();

        // Count photos instead of loading full array to make it light
        foreach ($dataList as &$item) {
            $item['foto_count'] = $this->fotoModel->where('id_parent', $item['id_trafo'])->where('kategori', 'TRAFO')->countAllResults();
        }

        $ulpModel = new \App\Models\UlpModel();
        $penyulangModel = new \App\Models\PenyulangModel();

        return view('eviden/trafo_index', [
            'dataList'         => $dataList,
            'ulps'             => $ulpModel->where('status', 'AKTIF')->findAll(),
            'penyulangs'       => $penyulangModel->where('status', 'AKTIF')->findAll(),
            'filterUlp'        => $ulpId,
            'filterPenyulang'  => $penyulangId,
            'filterTglMulai'   => $tglMulai,
            'filterTglSelesai' => $tglSelesai
        ]);
    }

    public function trafoCreate()
    {
        $ulps = $this->ulpRepository->getActiveUlps();
        return view('eviden/trafo_create', ['ulps' => $ulps]);
    }

    public function trafoStore()
    {
        $rules = [
            'id_penyulang' => 'required',
            'id_section'   => 'required',
            'nama_gardu'   => 'required|max_length[100]',
            'tgl_input'    => 'required|valid_date[Y-m-d]',
            'keterangan'   => 'required'
        ];

        if (!$this->validate($rules)) {
            $ulps = $this->ulpRepository->getActiveUlps();
            return view('eviden/trafo_create', [
                'ulps' => $ulps,
                'validation' => $this->validator
            ]);
        }

        $data = [
            'id_penyulang' => (int)$this->request->getPost('id_penyulang'),
            'id_section'   => (int)$this->request->getPost('id_section'),
            'nama_gardu'   => trim($this->request->getPost('nama_gardu')),
            'tgl_input'    => $this->request->getPost('tgl_input'),
            'keterangan'   => trim($this->request->getPost('keterangan')),
        ];

        $insertedId = $this->trafoModel->insert($data);

        if ($insertedId) {
            // Loop 6 Kategori Foto Trafo (Total 16 Foto)
            $categories = [
                'foto_nameplate' => 'FOTO NAMEPLATE',
                'foto_phbtr'     => 'FOTO PHBTR',
                'foto_trafo'     => 'FOTO TRAFO',
                'foto_temuan'    => 'TEMUAN',
                'foto_perbaikan' => 'PERBAIKAN',
                'foto_megger'    => 'HASIL MEGGER'
            ];

            foreach ($categories as $inputName => $jenisFotoLabel) {
                $files = $this->request->getFileMultiple($inputName);
                if ($files) {
                    foreach ($files as $file) {
                        if ($file->isValid() && !$file->hasMoved()) {
                            $newName = $file->getRandomName();
                            $file->move(FCPATH . 'foto/', $newName);

                            $this->fotoModel->insert([
                                'id_parent' => $insertedId,
                                'kategori'  => 'TRAFO',
                                'jenis_foto' => $jenisFotoLabel,
                                'nama_file' => $newName
                            ]);
                        }
                    }
                }
            }

            log_activity('CREATE_EVIDEN_TRAFO', 'Menambahkan eviden trafo gardu: ' . $data['nama_gardu']);
            return redirect()->to(site_url('eviden/trafo'))->with('success', 'Data Eviden Trafo berhasil ditambahkan.');
        }

        return redirect()->to(site_url('eviden/trafo/create'))->with('error', 'Gagal menyimpan data eviden trafo.');
    }

    public function trafoEdit(int $id)
    {
        $trafo = $this->trafoModel->find($id);
        if (!$trafo) {
            return redirect()->to(site_url('eviden/trafo'))->with('error', 'Data tidak ditemukan.');
        }

        $penyulang = $this->penyulangRepository->find($trafo['id_penyulang']);
        $ulpId = $penyulang ? $penyulang['ulp_id'] : null;

        $ulps = $this->ulpRepository->getActiveUlps();
        $penyulangs = $ulpId ? $this->penyulangRepository->getActivePenyulangsByUlp($ulpId) : [];
        $sections = $trafo['id_penyulang'] ? $this->sectionRepository->getActiveSectionsByPenyulang($trafo['id_penyulang']) : [];

        $fotos = $this->fotoModel->where('id_parent', $id)->where('kategori', 'TRAFO')->findAll();

        return view('eviden/trafo_edit', [
            'trafo' => $trafo,
            'ulps' => $ulps,
            'penyulangs' => $penyulangs,
            'sections' => $sections,
            'fotos' => $fotos,
            'currentUlpId' => $ulpId
        ]);
    }

    public function trafoUpdate(int $id)
    {
        $trafo = $this->trafoModel->find($id);
        if (!$trafo) {
            return redirect()->to(site_url('eviden/trafo'))->with('error', 'Data tidak ditemukan.');
        }

        $rules = [
            'id_penyulang' => 'required',
            'id_section'   => 'required',
            'nama_gardu'   => 'required|max_length[100]',
            'tgl_input'    => 'required|valid_date[Y-m-d]',
            'keterangan'   => 'required'
        ];

        if (!$this->validate($rules)) {
            return redirect()->to(site_url('eviden/trafo/edit/' . $id))->withInput()->with('error', 'Semua kolom wajib diisi.');
        }

        $data = [
            'id_penyulang' => (int)$this->request->getPost('id_penyulang'),
            'id_section'   => (int)$this->request->getPost('id_section'),
            'nama_gardu'   => trim($this->request->getPost('nama_gardu')),
            'tgl_input'    => $this->request->getPost('tgl_input'),
            'keterangan'   => trim($this->request->getPost('keterangan')),
        ];

        if ($this->trafoModel->update($id, $data)) {
            // Upload Foto Tambahan Baru
            $categories = [
                'foto_nameplate' => 'FOTO NAMEPLATE',
                'foto_phbtr'     => 'FOTO PHBTR',
                'foto_trafo'     => 'FOTO TRAFO',
                'foto_temuan'    => 'TEMUAN',
                'foto_perbaikan' => 'PERBAIKAN',
                'foto_megger'    => 'HASIL MEGGER'
            ];

            foreach ($categories as $inputName => $jenisFotoLabel) {
                $files = $this->request->getFileMultiple($inputName);
                if ($files) {
                    foreach ($files as $file) {
                        if ($file->isValid() && !$file->hasMoved()) {
                            $newName = $file->getRandomName();
                            $file->move(FCPATH . 'foto/', $newName);

                            $this->fotoModel->insert([
                                'id_parent' => $id,
                                'kategori'  => 'TRAFO',
                                'jenis_foto' => $jenisFotoLabel,
                                'nama_file' => $newName
                            ]);
                        }
                    }
                }
            }

            log_activity('UPDATE_EVIDEN_TRAFO', 'Memperbarui data eviden trafo ID: ' . $id);
            return redirect()->to(site_url('eviden/trafo'))->with('success', 'Data Eviden Trafo berhasil diperbarui.');
        }

        return redirect()->to(site_url('eviden/trafo/edit/' . $id))->with('error', 'Gagal memperbarui data.');
    }

    public function trafoDelete(int $id)
    {
        $trafo = $this->trafoModel->find($id);
        if (!$trafo) {
            return $this->response->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.']);
        }

        $fotos = $this->fotoModel->where('id_parent', $id)->where('kategori', 'TRAFO')->findAll();
        foreach ($fotos as $f) {
            if (file_exists(FCPATH . 'foto/' . $f['nama_file'])) {
                @unlink(FCPATH . 'foto/' . $f['nama_file']);
            }
            $this->fotoModel->delete($f['id_foto']);
        }

        if ($this->trafoModel->delete($id)) {
            log_activity('DELETE_EVIDEN_TRAFO', 'Menghapus data eviden trafo ID: ' . $id);
            return $this->response->setJSON(['success' => true, 'message' => 'Data eviden trafo berhasil dihapus.']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Gagal menghapus data.']);
    }

    // ==========================================
    // MANAGEMENT TRAFO CRUD
    // ==========================================

    public function management()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('tb_management_trafo m');
        $builder->select('m.*, p.nama_penyulang, s.nama_section, p.ulp_id');
        $builder->join('penyulang p', 'm.id_penyulang = p.id', 'left');
        $builder->join('sections s', 'm.id_section = s.id', 'left');
        $builder->orderBy('m.id_management', 'DESC');
        
        // Apply filters
        $ulpId      = $this->request->getGet('ulp_id');
        $penyulangId = $this->request->getGet('penyulang_id');
        $tglMulai   = $this->request->getGet('tgl_mulai');
        $tglSelesai = $this->request->getGet('tgl_selesai');

        if (!empty($ulpId)) {
            $builder->where('p.ulp_id', (int)$ulpId);
        }
        if (!empty($penyulangId)) {
            $builder->where('m.id_penyulang', (int)$penyulangId);
        }
        if (!empty($tglMulai)) {
            $builder->where('m.tgl_input >=', $tglMulai);
        }
        if (!empty($tglSelesai)) {
            $builder->where('m.tgl_input <=', $tglSelesai);
        }

        $dataList = $builder->get()->getResultArray();

        $ulpModel = new \App\Models\UlpModel();
        $penyulangModel = new \App\Models\PenyulangModel();

        return view('eviden/management_index', [
            'dataList'         => $dataList,
            'ulps'             => $ulpModel->where('status', 'AKTIF')->findAll(),
            'penyulangs'       => $penyulangModel->where('status', 'AKTIF')->findAll(),
            'filterUlp'        => $ulpId,
            'filterPenyulang'  => $penyulangId,
            'filterTglMulai'   => $tglMulai,
            'filterTglSelesai' => $tglSelesai
        ]);
    }

    public function managementCreate()
    {
        $ulps = $this->ulpRepository->getActiveUlps();
        return view('eviden/management_create', ['ulps' => $ulps]);
    }

    public function managementStore()
    {
        $rules = [
            'id_penyulang' => 'required',
            'id_section'   => 'required',
            'nama_gardu'   => 'required|max_length[100]',
            'tgl_input'    => 'required|valid_date[Y-m-d]',
            'keterangan'   => 'required'
        ];

        if (!$this->validate($rules)) {
            $ulps = $this->ulpRepository->getActiveUlps();
            return view('eviden/management_create', [
                'ulps' => $ulps,
                'validation' => $this->validator
            ]);
        }

        $data = [
            'id_penyulang' => (int)$this->request->getPost('id_penyulang'),
            'id_section'   => (int)$this->request->getPost('id_section'),
            'nama_gardu'   => trim($this->request->getPost('nama_gardu')),
            'tgl_input'    => $this->request->getPost('tgl_input'),
            'keterangan'   => trim($this->request->getPost('keterangan')),
            'foto_nameplate_lama' => '',
            'foto_nameplate_baru' => '',
        ];

        $fileLama = $this->request->getFile('foto_nameplate_lama');
        if ($fileLama && $fileLama->isValid() && !$fileLama->hasMoved()) {
            $newNameLama = $fileLama->getRandomName();
            $dir = FCPATH . 'foto/management/';
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $fileLama->move($dir, $newNameLama);
            $data['foto_nameplate_lama'] = $newNameLama;
        }

        $fileBaru = $this->request->getFile('foto_nameplate_baru');
        if ($fileBaru && $fileBaru->isValid() && !$fileBaru->hasMoved()) {
            $newNameBaru = $fileBaru->getRandomName();
            $dir = FCPATH . 'foto/management/';
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $fileBaru->move($dir, $newNameBaru);
            $data['foto_nameplate_baru'] = $newNameBaru;
        }

        $insertedId = $this->managementModel->insert($data);

        if ($insertedId) {
            log_activity('CREATE_MANAGEMENT_TRAFO', 'Menambahkan management trafo gardu: ' . $data['nama_gardu']);
            return redirect()->to(site_url('eviden/management'))->with('success', 'Data Management Trafo berhasil ditambahkan.');
        }

        return redirect()->to(site_url('eviden/management/create'))->with('error', 'Gagal menyimpan data.');
    }

    public function managementEdit(int $id)
    {
        $management = $this->managementModel->find($id);
        if (!$management) {
            return redirect()->to(site_url('eviden/management'))->with('error', 'Data tidak ditemukan.');
        }

        $penyulang = $this->penyulangRepository->find($management['id_penyulang']);
        $ulpId = $penyulang ? $penyulang['ulp_id'] : null;

        $ulps = $this->ulpRepository->getActiveUlps();
        $penyulangs = $ulpId ? $this->penyulangRepository->getActivePenyulangsByUlp($ulpId) : [];
        $sections = $management['id_penyulang'] ? $this->sectionRepository->getActiveSectionsByPenyulang($management['id_penyulang']) : [];

        return view('eviden/management_edit', [
            'management' => $management,
            'ulps' => $ulps,
            'penyulangs' => $penyulangs,
            'sections' => $sections,
            'currentUlpId' => $ulpId
        ]);
    }

    public function managementUpdate(int $id)
    {
        $management = $this->managementModel->find($id);
        if (!$management) {
            return redirect()->to(site_url('eviden/management'))->with('error', 'Data tidak ditemukan.');
        }

        $rules = [
            'id_penyulang' => 'required',
            'id_section'   => 'required',
            'nama_gardu'   => 'required|max_length[100]',
            'tgl_input'    => 'required|valid_date[Y-m-d]',
            'keterangan'   => 'required'
        ];

        if (!$this->validate($rules)) {
            return redirect()->to(site_url('eviden/management/edit/' . $id))->withInput()->with('error', 'Semua kolom wajib diisi.');
        }

        $data = [
            'id_penyulang' => (int)$this->request->getPost('id_penyulang'),
            'id_section'   => (int)$this->request->getPost('id_section'),
            'nama_gardu'   => trim($this->request->getPost('nama_gardu')),
            'tgl_input'    => $this->request->getPost('tgl_input'),
            'keterangan'   => trim($this->request->getPost('keterangan')),
        ];

        $fileLama = $this->request->getFile('foto_nameplate_lama');
        if ($fileLama && $fileLama->isValid() && !$fileLama->hasMoved()) {
            $newNameLama = $fileLama->getRandomName();
            $fileLama->move(FCPATH . 'foto/management/', $newNameLama);
            $data['foto_nameplate_lama'] = $newNameLama;

            if (!empty($management['foto_nameplate_lama']) && file_exists(FCPATH . 'foto/management/' . $management['foto_nameplate_lama'])) {
                @unlink(FCPATH . 'foto/management/' . $management['foto_nameplate_lama']);
            }
        }

        $fileBaru = $this->request->getFile('foto_nameplate_baru');
        if ($fileBaru && $fileBaru->isValid() && !$fileBaru->hasMoved()) {
            $newNameBaru = $fileBaru->getRandomName();
            $fileBaru->move(FCPATH . 'foto/management/', $newNameBaru);
            $data['foto_nameplate_baru'] = $newNameBaru;

            if (!empty($management['foto_nameplate_baru']) && file_exists(FCPATH . 'foto/management/' . $management['foto_nameplate_baru'])) {
                @unlink(FCPATH . 'foto/management/' . $management['foto_nameplate_baru']);
            }
        }

        if ($this->managementModel->update($id, $data)) {
            log_activity('UPDATE_MANAGEMENT_TRAFO', 'Memperbarui data management trafo ID: ' . $id);
            return redirect()->to(site_url('eviden/management'))->with('success', 'Data Management Trafo berhasil diperbarui.');
        }

        return redirect()->to(site_url('eviden/management/edit/' . $id))->with('error', 'Gagal memperbarui data.');
    }

    public function managementDelete(int $id)
    {
        $management = $this->managementModel->find($id);
        if (!$management) {
            return $this->response->setJSON(['success' => false, 'message' => 'Data tidak ditemukan.']);
        }

        if (!empty($management['foto_nameplate_lama']) && file_exists(FCPATH . 'foto/management/' . $management['foto_nameplate_lama'])) {
            @unlink(FCPATH . 'foto/management/' . $management['foto_nameplate_lama']);
        }
        if (!empty($management['foto_nameplate_baru']) && file_exists(FCPATH . 'foto/management/' . $management['foto_nameplate_baru'])) {
            @unlink(FCPATH . 'foto/management/' . $management['foto_nameplate_baru']);
        }

        if ($this->managementModel->delete($id)) {
            log_activity('DELETE_MANAGEMENT_TRAFO', 'Menghapus data management trafo ID: ' . $id);
            return $this->response->setJSON(['success' => true, 'message' => 'Data management trafo berhasil dihapus.']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Gagal menghapus data.']);
    }

    // ==========================================
    // DELETE SINGLE PHOTO
    // ==========================================

    public function deleteFoto(int $id)
    {
        $foto = $this->fotoModel->find($id);
        if ($foto) {
            $filePath = FCPATH . 'foto/' . $foto['nama_file'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            $this->fotoModel->delete($id);
            return redirect()->back()->with('success', 'Foto berhasil dihapus.');
        }
        return redirect()->back()->with('error', 'Foto tidak ditemukan.');
    }

    // ==========================================
    // AJAX GET FOTOS (Dynamically loaded)
    // ==========================================
    public function ajaxGetFotos()
    {
        $idParent = $this->request->getGet('id_parent');
        $kategori = $this->request->getGet('kategori');
        
        $fotos = $this->fotoModel->where('id_parent', $idParent)->where('kategori', $kategori)->findAll();
        return $this->response->setJSON($fotos);
    }

    // ==========================================
    // EXPORT EVIDEN DATA AS CSV
    // ==========================================
    public function exportKubikel()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('tb_eviden_kubikel k');
        $builder->select('k.*, p.nama_penyulang, s.nama_section, u.nama_ulp');
        $builder->join('penyulang p', 'k.id_penyulang = p.id', 'left');
        $builder->join('sections s', 'k.id_section = s.id', 'left');
        $builder->join('ulps u', 'p.ulp_id = u.id', 'left');
        $builder->orderBy('k.id_kubikel', 'DESC');

        // Apply same filters
        $ulpId      = $this->request->getGet('ulp_id');
        $penyulangId = $this->request->getGet('penyulang_id');
        $tglMulai   = $this->request->getGet('tgl_mulai');
        $tglSelesai = $this->request->getGet('tgl_selesai');

        if (!empty($ulpId)) $builder->where('p.ulp_id', (int)$ulpId);
        if (!empty($penyulangId)) $builder->where('k.id_penyulang', (int)$penyulangId);
        if (!empty($tglMulai)) $builder->where('k.tgl_input >=', $tglMulai);
        if (!empty($tglSelesai)) $builder->where('k.tgl_input <=', $tglSelesai);

        $data = $builder->get()->getResultArray();

        $headers = ['No', 'Nama ULP', 'Penyulang', 'Section', 'Nama Gardu', 'ID Pelanggan', 'Tanggal Input', 'Keterangan'];
        $rows = [];
        $no = 1;
        foreach ($data as $r) {
            $rows[] = [
                $no++,
                $r['nama_ulp'] ?: '-',
                $r['nama_penyulang'] ?: '-',
                $r['nama_section'] ?: '-',
                $r['nama_gardu'] ?: '-',
                $r['id_pel'] ?: '-',
                date('d-m-Y', strtotime($r['tgl_input'])),
                $r['keterangan'] ?: '-'
            ];
        }

        $this->downloadCsv('export_eviden_kubikel_' . date('Ymd_His') . '.csv', $headers, $rows);
    }

    public function exportTrafo()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('tb_eviden_trafo t');
        $builder->select('t.*, p.nama_penyulang, s.nama_section, u.nama_ulp');
        $builder->join('penyulang p', 't.id_penyulang = p.id', 'left');
        $builder->join('sections s', 't.id_section = s.id', 'left');
        $builder->join('ulps u', 'p.ulp_id = u.id', 'left');
        $builder->orderBy('t.id_trafo', 'DESC');

        // Apply same filters
        $ulpId      = $this->request->getGet('ulp_id');
        $penyulangId = $this->request->getGet('penyulang_id');
        $tglMulai   = $this->request->getGet('tgl_mulai');
        $tglSelesai = $this->request->getGet('tgl_selesai');

        if (!empty($ulpId)) $builder->where('p.ulp_id', (int)$ulpId);
        if (!empty($penyulangId)) $builder->where('t.id_penyulang', (int)$penyulangId);
        if (!empty($tglMulai)) $builder->where('t.tgl_input >=', $tglMulai);
        if (!empty($tglSelesai)) $builder->where('t.tgl_input <=', $tglSelesai);

        $data = $builder->get()->getResultArray();

        $headers = ['No', 'Nama ULP', 'Penyulang', 'Section', 'Nama Gardu', 'Tanggal Input', 'Keterangan'];
        $rows = [];
        $no = 1;
        foreach ($data as $r) {
            $rows[] = [
                $no++,
                $r['nama_ulp'] ?: '-',
                $r['nama_penyulang'] ?: '-',
                $r['nama_section'] ?: '-',
                $r['nama_gardu'] ?: '-',
                date('d-m-Y', strtotime($r['tgl_input'])),
                $r['keterangan'] ?: '-'
            ];
        }

        $this->downloadCsv('export_eviden_trafo_' . date('Ymd_His') . '.csv', $headers, $rows);
    }

    public function exportManagement()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('tb_management_trafo m');
        $builder->select('m.*, p.nama_penyulang, s.nama_section, u.nama_ulp');
        $builder->join('penyulang p', 'm.id_penyulang = p.id', 'left');
        $builder->join('sections s', 'm.id_section = s.id', 'left');
        $builder->join('ulps u', 'p.ulp_id = u.id', 'left');
        $builder->orderBy('m.id_management', 'DESC');

        // Apply same filters
        $ulpId      = $this->request->getGet('ulp_id');
        $penyulangId = $this->request->getGet('penyulang_id');
        $tglMulai   = $this->request->getGet('tgl_mulai');
        $tglSelesai = $this->request->getGet('tgl_selesai');

        if (!empty($ulpId)) $builder->where('p.ulp_id', (int)$ulpId);
        if (!empty($penyulangId)) $builder->where('m.id_penyulang', (int)$penyulangId);
        if (!empty($tglMulai)) $builder->where('m.tgl_input >=', $tglMulai);
        if (!empty($tglSelesai)) $builder->where('m.tgl_input <=', $tglSelesai);

        $data = $builder->get()->getResultArray();

        $headers = ['No', 'Nama ULP', 'Penyulang', 'Section', 'Nama Gardu', 'Tanggal Input', 'Foto Nameplate Lama', 'Foto Nameplate Baru', 'Keterangan'];
        $rows = [];
        $no = 1;
        foreach ($data as $r) {
            $rows[] = [
                $no++,
                $r['nama_ulp'] ?: '-',
                $r['nama_penyulang'] ?: '-',
                $r['nama_section'] ?: '-',
                $r['nama_gardu'] ?: '-',
                date('d-m-Y', strtotime($r['tgl_input'])),
                $r['foto_nameplate_lama'] ? base_url('foto/management/' . $r['foto_nameplate_lama']) : '-',
                $r['foto_nameplate_baru'] ? base_url('foto/management/' . $r['foto_nameplate_baru']) : '-',
                $r['keterangan'] ?: '-'
            ];
        }

        $this->downloadCsv('export_management_trafo_' . date('Ymd_His') . '.csv', $headers, $rows);
    }

    public function downloadPdf()
    {
        $kategori = $this->request->getPost('kategori');
        $selectedIds = $this->request->getPost('selected_ids') ?: [];

        if (empty($selectedIds)) {
            return redirect()->back()->with('error', 'Silakan pilih setidaknya satu data terlebih dahulu.');
        }

        $db = \Config\Database::connect();
        if ($kategori === 'KUBIKEL') {
            $builder = $db->table('tb_eviden_kubikel k');
            $builder->select('k.*, p.nama_penyulang, s.nama_section');
            $builder->join('penyulang p', 'k.id_penyulang = p.id', 'left');
            $builder->join('sections s', 'k.id_section = s.id', 'left');
            $builder->whereIn('k.id_kubikel', $selectedIds);
            $dataList = $builder->get()->getResultArray();
            
            foreach ($dataList as &$item) {
                $item['fotos'] = $this->fotoModel->where('id_parent', $item['id_kubikel'])->where('kategori', 'KUBIKEL')->findAll();
            }
        } elseif ($kategori === 'TRAFO') {
            $builder = $db->table('tb_eviden_trafo t');
            $builder->select('t.*, p.nama_penyulang, s.nama_section');
            $builder->join('penyulang p', 't.id_penyulang = p.id', 'left');
            $builder->join('sections s', 't.id_section = s.id', 'left');
            $builder->whereIn('t.id_trafo', $selectedIds);
            $dataList = $builder->get()->getResultArray();

            foreach ($dataList as &$item) {
                $item['fotos'] = $this->fotoModel->where('id_parent', $item['id_trafo'])->where('kategori', 'TRAFO')->findAll();
            }
        } elseif ($kategori === 'MANAGEMENT') {
            $builder = $db->table('tb_management_trafo m');
            $builder->select('m.*, p.nama_penyulang, s.nama_section');
            $builder->join('penyulang p', 'm.id_penyulang = p.id', 'left');
            $builder->join('sections s', 'm.id_section = s.id', 'left');
            $builder->whereIn('m.id_management', $selectedIds);
            $dataList = $builder->get()->getResultArray();

            foreach ($dataList as &$item) {
                $item['fotos'] = [];
                if (!empty($item['foto_nameplate_lama'])) {
                    $item['fotos'][] = [
                        'nama_file' => 'management/' . $item['foto_nameplate_lama'],
                        'jenis_foto' => 'NAMEPLATE LAMA'
                    ];
                }
                if (!empty($item['foto_nameplate_baru'])) {
                    $item['fotos'][] = [
                        'nama_file' => 'management/' . $item['foto_nameplate_baru'],
                        'jenis_foto' => 'NAMEPLATE BARU'
                    ];
                }
            }
        } else {
            return redirect()->back()->with('error', 'Kategori tidak valid.');
        }

        log_activity('DOWNLOAD_PDF_EVIDEN', 'Mengunduh PDF eviden ' . $kategori);

        return view('eviden/print_pdf', [
            'dataList' => $dataList,
            'kategori' => $kategori
        ]);
    }

    public function downloadFoto()
    {
        $kategori = $this->request->getPost('kategori');
        $selectedIds = $this->request->getPost('selected_ids') ?: [];

        if (empty($selectedIds)) {
            return redirect()->back()->with('error', 'Silakan pilih setidaknya satu data terlebih dahulu.');
        }

        $db = \Config\Database::connect();
        $filesToZip = [];

        if ($kategori === 'KUBIKEL') {
            $builder = $db->table('tb_eviden_kubikel k');
            $builder->select('k.id_kubikel, k.nama_gardu');
            $builder->whereIn('k.id_kubikel', $selectedIds);
            $dataList = $builder->get()->getResultArray();

            foreach ($dataList as $item) {
                $garduClean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $item['nama_gardu']);
                $fotos = $this->fotoModel->where('id_parent', $item['id_kubikel'])->where('kategori', 'KUBIKEL')->findAll();
                foreach ($fotos as $f) {
                    $filePath = FCPATH . 'foto/' . $f['nama_file'];
                    if (file_exists($filePath)) {
                        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                        $jenisClean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $f['jenis_foto']);
                        $localName = $garduClean . '/' . $jenisClean . '_' . $f['id_foto'] . '.' . $ext;
                        $filesToZip[] = [
                            'path' => $filePath,
                            'name' => $localName
                        ];
                    }
                }
            }
        } elseif ($kategori === 'TRAFO') {
            $builder = $db->table('tb_eviden_trafo t');
            $builder->select('t.id_trafo, t.nama_gardu');
            $builder->whereIn('t.id_trafo', $selectedIds);
            $dataList = $builder->get()->getResultArray();

            foreach ($dataList as $item) {
                $garduClean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $item['nama_gardu']);
                $fotos = $this->fotoModel->where('id_parent', $item['id_trafo'])->where('kategori', 'TRAFO')->findAll();
                foreach ($fotos as $f) {
                    $filePath = FCPATH . 'foto/' . $f['nama_file'];
                    if (file_exists($filePath)) {
                        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                        $jenisClean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $f['jenis_foto']);
                        $localName = $garduClean . '/' . $jenisClean . '_' . $f['id_foto'] . '.' . $ext;
                        $filesToZip[] = [
                            'path' => $filePath,
                            'name' => $localName
                        ];
                    }
                }
            }
        } elseif ($kategori === 'MANAGEMENT') {
            $builder = $db->table('tb_management_trafo m');
            $builder->select('m.id_management, m.nama_gardu, m.foto_nameplate_lama, m.foto_nameplate_baru');
            $builder->whereIn('m.id_management', $selectedIds);
            $dataList = $builder->get()->getResultArray();

            foreach ($dataList as $item) {
                $garduClean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $item['nama_gardu']);
                if (!empty($item['foto_nameplate_lama'])) {
                    $filePath = FCPATH . 'foto/management/' . $item['foto_nameplate_lama'];
                    if (file_exists($filePath)) {
                        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                        $localName = $garduClean . '/NAMEPLATE_LAMA.' . $ext;
                        $filesToZip[] = [
                            'path' => $filePath,
                            'name' => $localName
                        ];
                    }
                }
                if (!empty($item['foto_nameplate_baru'])) {
                    $filePath = FCPATH . 'foto/management/' . $item['foto_nameplate_baru'];
                    if (file_exists($filePath)) {
                        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                        $localName = $garduClean . '/NAMEPLATE_BARU.' . $ext;
                        $filesToZip[] = [
                            'path' => $filePath,
                            'name' => $localName
                        ];
                    }
                }
            }
        }

        if (empty($filesToZip)) {
            return redirect()->back()->with('error', 'Tidak ada file foto untuk diunduh dari data yang dipilih.');
        }

        $zip = new \ZipArchive();
        $zipFilename = 'Foto_Eviden_' . $kategori . '_' . date('Ymd_His') . '.zip';
        $zipPath = WRITEPATH . 'uploads/' . $zipFilename;

        if (!is_dir(WRITEPATH . 'uploads/')) {
            mkdir(WRITEPATH . 'uploads/', 0777, true);
        }

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            foreach ($filesToZip as $f) {
                $zip->addFile($f['path'], $f['name']);
            }
            $zip->close();

            log_activity('DOWNLOAD_ZIP_FOTO_EVIDEN', 'Mengunduh ZIP foto eviden ' . $kategori);

            return $this->response->download($zipPath, null)->deleteFileAfterSend(true);
        } else {
            return redirect()->back()->with('error', 'Gagal membuat file arsip ZIP.');
        }
    }

    private function downloadCsv(string $filename, array $headers, array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM

        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
}
