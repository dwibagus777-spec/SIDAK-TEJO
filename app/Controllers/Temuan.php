<?php

namespace App\Controllers;

use App\Services\TemuanService;
use App\Repositories\TemuanRepository;
use App\Repositories\UlpRepository;
use App\Repositories\PenyulangRepository;
use App\Repositories\SectionRepository;
use App\Repositories\TindakLanjutRepository;

class Temuan extends BaseController
{
    private TemuanService $temuanService;
    private TemuanRepository $temuanRepository;
    private UlpRepository $ulpRepository;
    private PenyulangRepository $penyulangRepository;
    private SectionRepository $sectionRepository;
    private TindakLanjutRepository $tindakLanjutRepository;

    public function __construct()
    {
        $this->temuanService = new TemuanService();
        $this->temuanRepository = new TemuanRepository();
        $this->ulpRepository = new UlpRepository();
        $this->penyulangRepository = new PenyulangRepository();
        $this->sectionRepository = new SectionRepository();
        $this->tindakLanjutRepository = new TindakLanjutRepository();
    }

    public function index()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');
        $isRestricted = ($userUlpId !== null && !in_array($role, ['administrator', 'har_crane', 'pdkb', 'inspeksi']));

        if ($isRestricted) {
            $ulps = [$this->ulpRepository->find($userUlpId)];
            $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($userUlpId);
        } else {
            $ulps = $this->ulpRepository->getActiveUlps();
            $penyulangs = $this->penyulangRepository->getActivePenyulangs();
        }

        return view('temuan/index', [
            'ulps' => $ulps,
            'penyulangs' => $penyulangs,
            'isRestricted' => $isRestricted
        ]);
    }

    /**
     * Endpoint DataTables Server Side
     */
    public function ajaxDataTables()
    {
        $scoping = get_user_role_scoping();

        $postData = $this->request->getPost();
        $result = $this->temuanRepository->getDataTables($postData, $scoping['ulp_id'], $scoping['jenis_temuan']);

        // Format data sebelum dikirim kembali
        $formattedData = [];
        foreach ($result['data'] as $row) {
            
            // Prioritas Badge
            $prio = strtoupper($row['prioritas']);
            $prioBadge = '<span class="badge bg-secondary">' . $prio . '</span>';
            if ($prio === 'EMERGENCY') {
                $prioBadge = '<span class="badge bg-danger animate__animated animate__flash animate__infinite">' . $prio . '</span>';
            } elseif ($prio === 'HIGH') {
                $prioBadge = '<span class="badge bg-warning text-dark">' . $prio . '</span>';
            } elseif ($prio === 'MEDIUM') {
                $prioBadge = '<span class="badge bg-primary">' . $prio . '</span>';
            }

            // Potensi Gangguan Badge
            $pot = strtoupper($row['potensi_gangguan']);
            $potBadge = '<span class="badge bg-info text-dark">' . $pot . '</span>';

            // SLA & Status Badge
            $sla = get_sla_status($row['prioritas'], $row['tanggal_temuan'], $row['status']);
            $statusBadge = $sla['badge_html'];

            // Tombol Aksi - gunakan modal AJAX (lebih cepat dari pindah halaman)
            $btnDetail = '<button type="button" class="btn btn-sm btn-info text-white btn-detail-modal" data-id="' . $row['id'] . '" title="Lihat Detail & Foto"><i class="fas fa-eye"></i></button>';
            
            $btnDelete = '';
            if (check_role(['administrator', 'admin', 'admin_pusat', 'admin_ulp', 'inspeksi', 'pdkb', 'har_gardu', 'har_konstruksi', 'har_row', 'har_crane', 'yantek', 'supervisor_ulp', 'supervisor_up3'])) {
                $deleteUrl = site_url('temuan/delete/' . $row['id']);
                $btnDelete = ' <a href="' . $deleteUrl . '" onclick="return window.confirm(\'Hapus temuan ' . esc(addslashes($row['nomor_temuan']), 'attr') . '?\');" class="btn btn-sm btn-danger" title="Hapus"><i class="fas fa-trash"></i></a>';
            }

            $actions = $btnDetail . $btnDelete;

            // Detail Kerusakan (truncated for neatness)
            $detailKerusakan = esc($row['detail_temuan'] ?? '');
            if (mb_strlen($detailKerusakan) > 50) {
                $detailKerusakan = '<span title="' . esc($row['detail_temuan'] ?? '') . '">' . mb_strimwidth($detailKerusakan, 0, 50, '...') . '</span>';
            }

            // Foto Column (Render small thumbnail)
            $fotoHtml = '<span class="text-muted small">Tidak ada</span>';
            $photos = json_decode($row['foto'] ?? '', true) ?: [];
            if (!empty($photos) && !empty($row['foto_path'])) {
                $photoUrl = base_url($row['foto_path'] . $photos[0]);
                $fotoHtml = '<img src="' . $photoUrl . '" class="img-thumbnail" style="max-height: 45px; max-width: 45px; cursor: pointer; object-fit: cover; border-radius: 4px;" onclick="openLightbox(\'' . $photoUrl . '\')" title="Klik untuk memperbesar">';
                if (count($photos) > 1) {
                    $fotoHtml .= '<br><span class="badge bg-secondary font-weight-normal mt-1" style="font-size: 8px; padding: 2px 4px;">+' . (count($photos) - 1) . ' foto</span>';
                }
            }

            $formattedData[] = [
                $row['nomor_temuan'],
                $row['nama_penyulang'],
                $row['nama_section'],
                $row['jenis_temuan'],
                $fotoHtml,
                $prioBadge,
                date('d-m-Y', strtotime($row['tanggal_temuan'])),
                $statusBadge,
                $actions
            ];
        }

        $result['data'] = $formattedData;

        return $this->response->setJSON($result);
    }

    public function create()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $ulps = ($role === 'admin_ulp' && $userUlpId !== null) 
            ? [$this->ulpRepository->find($userUlpId)] 
            : $this->ulpRepository->getActiveUlps();

        return view('temuan/create', [
            'ulps' => $ulps
        ]);
    }

    public function store()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        // Validasi input
        $rules = [
            'ulp_id'           => 'required|is_not_unique[ulps.id]',
            'penyulang_id'     => 'required|is_not_unique[penyulang.id]',
            'section_id'       => 'required|is_not_unique[sections.id]',
            'jenis_temuan'     => 'required|in_list[KONSTRUKSI,HOTSPOT,ROW]',
            'pelaksana'        => 'required|in_list[PDKB,HAR GARDU,HAR GTT,HAR KONSTRUKSI,HAR ROW,HAR CRANE]',
            'prioritas'        => 'required|in_list[EMERGENCY,HIGH,MEDIUM]',
            'potensi_gangguan' => 'required|in_list[DGR,OCR,OCRDGR]',
            'konduktor'        => 'required|max_length[100]',
            'noga'             => 'permit_empty|max_length[100]',
            'material'         => 'required',
            'detail_temuan'    => 'required',
            'alamat'           => 'required',
            'tanggal_temuan'   => 'required|valid_date[Y-m-d]',
        ];

        // Custom validation error message
        if (!$this->validate($rules)) {
            $ulps = ($role === 'admin_ulp' && $userUlpId !== null) 
                ? [$this->ulpRepository->find($userUlpId)] 
                : $this->ulpRepository->getActiveUlps();

            return view('temuan/create', [
                'ulps' => $ulps,
                'validation' => $this->validator
            ]);
        }

        $ulpIdInput = (int)$this->request->getPost('ulp_id');
        if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== $ulpIdInput) {
            return redirect()->to(site_url('temuan/create'))->with('error', 'Anda hanya diizinkan menginput temuan untuk ULP Anda.');
        }

        // Kumpulkan data temuan
        $data = [
            'ulp_id'           => $ulpIdInput,
            'penyulang_id'     => (int)$this->request->getPost('penyulang_id'),
            'section_id'       => (int)$this->request->getPost('section_id'),
            'jenis_temuan'     => $this->request->getPost('jenis_temuan'),
            'pelaksana'        => $this->request->getPost('pelaksana'),
            'prioritas'        => $this->request->getPost('prioritas'),
            'potensi_gangguan' => $this->request->getPost('potensi_gangguan'),
            'konduktor'        => trim($this->request->getPost('konduktor')),
            'noga'             => trim($this->request->getPost('noga')) ?: null,
            'material'         => trim($this->request->getPost('material')),
            'detail_temuan'    => trim($this->request->getPost('detail_temuan')),
            'alamat'           => trim($this->request->getPost('alamat')),
            'latitude'         => $this->request->getPost('latitude') !== '' ? (float)$this->request->getPost('latitude') : null,
            'longitude'        => $this->request->getPost('longitude') !== '' ? (float)$this->request->getPost('longitude') : null,
            'tanggal_temuan'   => $this->request->getPost('tanggal_temuan'),
        ];

        // Ambil file foto (multi-upload)
        $files = $this->request->getFileMultiple('foto');

        // Panggil Service
        $res = $this->temuanService->createTemuan($data, $files);

        if ($res['success']) {
            return redirect()->to(site_url('temuan'))->with('success', $res['message']);
        }

        return redirect()->to(site_url('temuan/create'))->withInput()->with('error', $res['message']);
    }

    public function detail(int $id)
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if ($role !== 'administrator' && $role !== 'har_crane' && $role !== 'inspeksi' && $userUlpId !== null) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $temuan = $this->temuanRepository->getDetail($id, $ulpIdFilter);
        if (!$temuan) {
            return redirect()->to(site_url('temuan'))->with('error', 'Temuan tidak ditemukan atau Anda tidak memiliki akses ke data tersebut.');
        }

        // Hitung SLA status
        $sla = get_sla_status($temuan['prioritas'], $temuan['tanggal_temuan'], $temuan['status'], $temuan['tanggal_selesai']);

        // Ambil histori progress tindak lanjut
        $history = $this->tindakLanjutRepository->getHistoryByTemuan($id);

        return view('temuan/detail', [
            'temuan'  => $temuan,
            'sla'     => $sla,
            'history' => $history
        ]);
    }

    /**
     * AJAX endpoint untuk modal detail cepat di halaman index
     */
    public function ajaxDetail(int $id)
    {
        try {
            $session = session();
            $role = strtolower((string)$session->get('user_role'));

            // Detail temuan bersifat READ-ONLY, sehingga diizinkan untuk dibaca seluruh user
            $temuan = $this->temuanRepository->getDetail($id, null);
            if (!$temuan) {
                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Data temuan tidak ditemukan.']);
            }

            $sla     = get_sla_status($temuan['prioritas'], $temuan['tanggal_temuan'], $temuan['status'], $temuan['tanggal_selesai']);
            $history = $this->tindakLanjutRepository->getHistoryByTemuan($id);

            return $this->response->setJSON([
                'success'   => true,
                'temuan'    => $temuan,
                'sla'       => $sla,
                'history'   => $history,
                'canEdit'   => in_array($role, ['administrator', 'admin', 'admin_pusat', 'admin_ulp', 'inspeksi', 'pdkb', 'har_gardu', 'har_konstruksi', 'har_row', 'har_crane', 'yantek']),
                'editUrl'   => site_url('temuan/edit/' . $id),
                'detailUrl' => site_url('temuan/detail/' . $id),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'ajaxDetail Error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Input progress tindak lanjut
     */
    public function tindakLanjut(int $id)
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if ($role !== 'administrator' && $role !== 'har_crane' && $role !== 'inspeksi' && $userUlpId !== null) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $isAjax = $this->request->isAJAX();

        $temuan = $this->temuanRepository->getDetail($id, $ulpIdFilter);
        if (!$temuan) {
            if ($isAjax) {
                return $this->response->setJSON(['success' => false, 'message' => 'Temuan tidak ditemukan atau Anda tidak memiliki akses.']);
            }
            return redirect()->to(site_url('temuan'))->with('error', 'Temuan tidak ditemukan atau Anda tidak memiliki akses.');
        }

        $rules = [
            'status_progress' => 'required|in_list[PROSES,SELESAI,BUTUH PADAM,TERKENDALA]',
            'komentar'        => 'required'
        ];

        if (!$this->validate($rules)) {
            if ($isAjax) {
                return $this->response->setJSON(['success' => false, 'message' => 'Status dan komentar tindak lanjut wajib diisi.']);
            }
            return redirect()->to(site_url('temuan/detail/' . $id))->with('error', 'Status dan komentar tindak lanjut wajib diisi.');
        }

        $progressData = [
            'status_progress' => $this->request->getPost('status_progress'),
            'komentar'        => trim($this->request->getPost('komentar')),
            'pelaksana'       => $session->get('user_name') ?: 'Petugas'
        ];

        $uploadFiles = [];
        
        $fotoSebelum = $this->request->getFile('foto_sebelum');
        if ($fotoSebelum && $fotoSebelum->isValid()) {
            $uploadFiles['foto_sebelum'] = $fotoSebelum;
        }

        $fotoProses = $this->request->getFile('foto_proses');
        if ($fotoProses && $fotoProses->isValid()) {
            $uploadFiles['foto_proses'] = $fotoProses;
        }

        $fotoSesudah = $this->request->getFile('foto_sesudah');
        if ($fotoSesudah && $fotoSesudah->isValid()) {
            $uploadFiles['foto_sesudah'] = $fotoSesudah;
        }

        $res = $this->temuanService->addTindakLanjut($id, $progressData, $uploadFiles);

        if ($isAjax) {
            return $this->response->setJSON($res);
        }

        if ($res['success']) {
            return redirect()->to(site_url('temuan/detail/' . $id))->with('success', $res['message']);
        }

        return redirect()->to(site_url('temuan/detail/' . $id))->with('error', $res['message']);
    }

    public function edit(int $id)
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if ($role === 'admin_ulp' && $userUlpId !== null) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $temuan = $this->temuanRepository->getDetail($id, $ulpIdFilter);
        if (!$temuan) {
            return redirect()->to(site_url('temuan'))->with('error', 'Temuan tidak ditemukan atau Anda tidak memiliki akses.');
        }

        $ulps = ($role === 'admin_ulp' && $userUlpId !== null)
            ? [$this->ulpRepository->find($userUlpId)]
            : $this->ulpRepository->getActiveUlps();

        // Ambil penyulang & section yang sudah terpilih untuk pre-populate
        $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($temuan['ulp_id']);
        $sections   = $this->sectionRepository->getActiveSectionsByPenyulang($temuan['penyulang_id']);

        return view('temuan/edit', [
            'temuan'    => $temuan,
            'ulps'      => $ulps,
            'penyulangs'=> $penyulangs,
            'sections'  => $sections,
        ]);
    }

    public function update(int $id)
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if ($role === 'admin_ulp' && $userUlpId !== null) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $temuan = $this->temuanRepository->getDetail($id, $ulpIdFilter);
        if (!$temuan) {
            return redirect()->to(site_url('temuan'))->with('error', 'Temuan tidak ditemukan atau Anda tidak memiliki akses.');
        }

        // Validasi input
        $rules = [
            'ulp_id'           => 'required|is_not_unique[ulps.id]',
            'penyulang_id'     => 'required|is_not_unique[penyulang.id]',
            'section_id'       => 'required|is_not_unique[sections.id]',
            'jenis_temuan'     => 'required|in_list[KONSTRUKSI,HOTSPOT,ROW]',
            'pelaksana'        => 'required|in_list[PDKB,HAR GARDU,HAR GTT,HAR KONSTRUKSI,HAR ROW,HAR CRANE]',
            'prioritas'        => 'required|in_list[EMERGENCY,HIGH,MEDIUM]',
            'potensi_gangguan' => 'required|in_list[DGR,OCR,OCRDGR]',
            'konduktor'        => 'required|max_length[100]',
            'noga'             => 'permit_empty|max_length[100]',
            'material'         => 'required',
            'detail_temuan'    => 'required',
            'alamat'           => 'required',
            'tanggal_temuan'   => 'required|valid_date[Y-m-d]',
        ];

        if (!$this->validate($rules)) {
            $ulps = ($role === 'admin_ulp' && $userUlpId !== null)
                ? [$this->ulpRepository->find($userUlpId)]
                : $this->ulpRepository->getActiveUlps();

            $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($temuan['ulp_id']);
            $sections   = $this->sectionRepository->getActiveSectionsByPenyulang($temuan['penyulang_id']);

            return view('temuan/edit', [
                'temuan'     => $temuan,
                'ulps'       => $ulps,
                'penyulangs' => $penyulangs,
                'sections'   => $sections,
                'validation' => $this->validator
            ]);
        }

        $data = [
            'ulp_id'           => (int)$this->request->getPost('ulp_id'),
            'penyulang_id'     => (int)$this->request->getPost('penyulang_id'),
            'section_id'       => (int)$this->request->getPost('section_id'),
            'jenis_temuan'     => $this->request->getPost('jenis_temuan'),
            'pelaksana'        => $this->request->getPost('pelaksana'),
            'prioritas'        => $this->request->getPost('prioritas'),
            'potensi_gangguan' => $this->request->getPost('potensi_gangguan'),
            'konduktor'        => trim($this->request->getPost('konduktor')),
            'noga'             => trim($this->request->getPost('noga')) ?: null,
            'material'         => trim($this->request->getPost('material')),
            'detail_temuan'    => trim($this->request->getPost('detail_temuan')),
            'alamat'           => trim($this->request->getPost('alamat')),
            'latitude'         => $this->request->getPost('latitude') !== '' ? (float)$this->request->getPost('latitude') : null,
            'longitude'        => $this->request->getPost('longitude') !== '' ? (float)$this->request->getPost('longitude') : null,
            'tanggal_temuan'   => $this->request->getPost('tanggal_temuan'),
        ];

        // Cek apakah ada foto baru yang diupload
        $newFiles = $this->request->getFileMultiple('foto');
        $res = $this->temuanService->updateTemuan($id, $data, $newFiles);

        if ($res['success']) {
            return redirect()->to(site_url('temuan/detail/' . $id))->with('success', $res['message']);
        }

        return redirect()->to(site_url('temuan/edit/' . $id))->withInput()->with('error', $res['message']);
    }

    public function delete(int $id)
    {
        $isAjax = $this->request->isAJAX() || $this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest' || str_contains($this->request->getHeaderLine('Accept'), 'json');
        
        log_message('info', "[DELETE_TEMUAN] Controller dipanggil | ID Received: {$id} | Method: " . $this->request->getMethod());

        try {
            $db = \Config\Database::connect();
            $temuan = $db->table('temuan')->where('id', $id)->where('deleted_at IS NULL')->get()->getRowArray();
            
            if (!$temuan) {
                log_message('warning', "[DELETE_TEMUAN] Data tidak ditemukan atau sudah terhapus | ID: {$id}");
                if ($isAjax) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Data temuan tidak ditemukan atau sudah dihapus.']);
                }
                return redirect()->to(site_url('temuan'))->with('error', 'Data temuan tidak ditemukan.');
            }

            $session = session();
            $role = strtolower((string)$session->get('user_role'));
            $userUlpId = $session->get('user_ulp_id');

            if ($role === 'admin_ulp' && $userUlpId !== null && (int)$temuan['ulp_id'] !== (int)$userUlpId) {
                log_message('warning', "[DELETE_TEMUAN] Akses ditolak untuk role admin_ulp | User ULP: {$userUlpId} | Temuan ULP: {$temuan['ulp_id']}");
                if ($isAjax) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Anda tidak memiliki hak akses untuk menghapus data temuan ULP lain.']);
                }
                return redirect()->to(site_url('temuan'))->with('error', 'Anda tidak memiliki hak akses.');
            }

            // Eksekusi Soft Delete Query
            $now = date('Y-m-d H:i:s');
            $db->table('temuan')->where('id', $id)->update(['deleted_at' => $now]);
            $affectedRows = $db->affectedRows();

            log_message('info', "[DELETE_TEMUAN] Query UPDATE executed | ID: {$id} | Affected Rows: {$affectedRows}");

            if ($affectedRows > 0) {
                log_activity('DELETE_TEMUAN', 'Menghapus temuan: ' . $temuan['nomor_temuan']);
                if ($isAjax) {
                    return $this->response->setJSON(['success' => true, 'message' => 'Temuan ' . esc($temuan['nomor_temuan']) . ' berhasil dihapus.']);
                }
                return redirect()->to(site_url('temuan'))->with('success', 'Temuan ' . esc($temuan['nomor_temuan']) . ' berhasil dihapus.');
            }

            $dbError = $db->error();
            log_message('error', "[DELETE_TEMUAN_FAIL] DB Error Code: {$dbError['code']} | DB Error Msg: {$dbError['message']} | ID: {$id}");

            if ($isAjax) {
                return $this->response->setJSON(['success' => false, 'message' => 'Gagal menghapus data dari database. Error Code: ' . $dbError['code']]);
            }
            return redirect()->to(site_url('temuan'))->with('error', 'Gagal menghapus data.');

        } catch (\Throwable $e) {
            log_message('error', "[DELETE_TEMUAN_EXCEPTION] " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            if ($isAjax) {
                return $this->response->setJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
            }
            return redirect()->to(site_url('temuan'))->with('error', 'Server error: ' . $e->getMessage());
        }
    }

    // --- AJAX Cascades ---

    public function ajaxGetPenyulang(int $ulpId)
    {
        $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($ulpId);
        return $this->response->setJSON($penyulangs);
    }

    public function ajaxGetSection(int $penyulangId)
    {
        $sections = $this->sectionRepository->getActiveSectionsByPenyulang($penyulangId);
        return $this->response->setJSON($sections);
    }

    public function terdekat()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');
        $isRestricted = ($userUlpId !== null && !in_array($role, ['administrator', 'har_crane', 'pdkb', 'inspeksi']));

        if ($isRestricted) {
            $ulps = [$this->ulpRepository->find($userUlpId)];
            $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($userUlpId);
        } else {
            $ulps = $this->ulpRepository->getActiveUlps();
            $penyulangs = $this->penyulangRepository->getActivePenyulangs();
        }

        return view('temuan/terdekat', [
            'ulps' => $ulps,
            'penyulangs' => $penyulangs,
            'isRestricted' => $isRestricted
        ]);
    }

    public function ajaxTerdekat()
    {
        try {
            $scoping = get_user_role_scoping();

            $lat = $this->request->getGet('latitude');
            $lng = $this->request->getGet('longitude');
            $radius = $this->request->getGet('radius'); // in meters
            
            if (empty($lat) || empty($lng)) {
                return $this->response->setJSON([]);
            }
            
            $lat = (float)$lat;
            $lng = (float)$lng;
            $radius = (float)($radius ?: 1000) / 1000; // convert to km
            
            $db = \Config\Database::connect();
            
            $sql = "SELECT * FROM (
                        SELECT t.*, p.nama_penyulang, s.nama_section, u.nama_ulp,
                            (6371 * acos(
                                LEAST(1.0, GREATEST(-1.0, 
                                    cos(radians(?)) * cos(radians(t.latitude)) * cos(radians(t.longitude) - radians(?)) + 
                                    sin(radians(?)) * sin(radians(t.latitude))
                                ))
                            )) AS distance_km
                        FROM temuan t
                        LEFT JOIN penyulang p ON t.penyulang_id = p.id
                        LEFT JOIN sections s ON t.section_id = s.id
                        LEFT JOIN ulps u ON t.ulp_id = u.id
                        WHERE t.latitude IS NOT NULL 
                          AND t.longitude IS NOT NULL
                          AND t.deleted_at IS NULL";
                      
            $params = [$lat, $lng, $lat];

            // Apply ULP restriction if restricted
            if ($scoping['ulp_id'] !== null) {
                $sql .= " AND t.ulp_id = ?";
                $params[] = (int)$scoping['ulp_id'];
            }

            // Apply category restriction (e.g. har_row -> ROW)
            if ($scoping['jenis_temuan'] !== null) {
                $sql .= " AND t.jenis_temuan = ?";
                $params[] = $scoping['jenis_temuan'];
            }

            $sql .= ") AS sub_temuan
                      WHERE distance_km <= ?
                      ORDER BY distance_km ASC
                      LIMIT 50";
            $params[] = $radius;
                      
            $query = $db->query($sql, $params);
            $results = $query->getResultArray();
            
            foreach ($results as &$row) {
                $distMeters = $row['distance_km'] * 1000;
                if ($distMeters < 1000) {
                    $row['distance_text'] = round($distMeters) . ' m';
                } else {
                    $row['distance_text'] = round($row['distance_km'], 2) . ' km';
                }
            }
            
            return $this->response->setJSON($results);
        } catch (\Throwable $e) {
            log_message('error', 'ajaxTerdekat Error: ' . $e->getMessage());
            return $this->response->setJSON([]);
        }
    }

    public function updatePekerjaan()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');
        
        $unrestrictedRoles = ['administrator', 'har_crane', 'pdkb', 'inspeksi'];
        $isRestricted = ($userUlpId !== null && !in_array($role, $unrestrictedRoles));

        if ($isRestricted) {
            $ulps = [$this->ulpRepository->find($userUlpId)];
            $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($userUlpId);
        } else {
            $ulps = $this->ulpRepository->getActiveUlps();
            $penyulangs = $this->penyulangRepository->getActivePenyulangs();
        }

        $rolePelaksanaMap = [
            'pdkb' => 'PDKB',
            'har_gardu' => 'HAR GARDU',
            'har_row' => 'HAR ROW',
            'har_crane' => 'HAR CRANE',
            'har_konstruksi' => 'HAR KONSTRUKSI',
            'yantek' => 'YANTEK'
        ];
        $lockedPelaksana = isset($rolePelaksanaMap[$role]) ? $rolePelaksanaMap[$role] : null;

        return view('temuan/update_pekerjaan', [
            'ulps' => $ulps,
            'penyulangs' => $penyulangs,
            'isRestricted' => $isRestricted,
            'lockedPelaksana' => $lockedPelaksana
        ]);
    }

    public function ajaxUpdatePekerjaan()
    {
        $session = session();
        $role = $session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        $unrestrictedRoles = ['administrator', 'har_crane', 'pdkb', 'inspeksi'];
        if (!in_array($role, $unrestrictedRoles) && $userUlpId !== null) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $postData = $this->request->getPost();

        // Inject pelaksana filter
        $rolePelaksanaMap = [
            'pdkb' => 'PDKB',
            'har_gardu' => 'HAR GARDU',
            'har_row' => 'HAR ROW',
            'har_crane' => 'HAR CRANE',
            'har_konstruksi' => 'HAR KONSTRUKSI',
            'yantek' => 'YANTEK'
        ];
        if (isset($rolePelaksanaMap[$role])) {
            $postData['pelaksana'] = $rolePelaksanaMap[$role];
        }

        $result = $this->temuanRepository->getDataTables($postData, $ulpIdFilter);

        // Format data
        $formattedData = [];
        foreach ($result['data'] as $row) {
            
            $prio = strtoupper($row['prioritas']);
            $prioBadge = '<span class="badge bg-secondary">' . $prio . '</span>';
            if ($prio === 'EMERGENCY') {
                $prioBadge = '<span class="badge bg-danger animate__animated animate__flash animate__infinite">' . $prio . '</span>';
            } elseif ($prio === 'HIGH') {
                $prioBadge = '<span class="badge bg-warning text-dark">' . $prio . '</span>';
            } elseif ($prio === 'MEDIUM') {
                $prioBadge = '<span class="badge bg-primary">' . $prio . '</span>';
            }

            $pot = strtoupper($row['potensi_gangguan']);
            $potBadge = '<span class="badge bg-info text-dark">' . $pot . '</span>';

            $sla = get_sla_status($row['prioritas'], $row['tanggal_temuan'], $row['status']);
            $statusBadge = $sla['badge_html'];

            $detailKerusakan = esc($row['detail_temuan'] ?? '');
            if (mb_strlen($detailKerusakan) > 50) {
                $detailKerusakan = '<span title="' . esc($row['detail_temuan'] ?? '') . '">' . mb_strimwidth($detailKerusakan, 0, 50, '...') . '</span>';
            }

            $fotoHtml = '<span class="text-muted small">Tidak ada</span>';
            $photos = json_decode($row['foto'] ?? '', true) ?: [];
            if (!empty($photos) && !empty($row['foto_path'])) {
                $photoUrl = base_url($row['foto_path'] . $photos[0]);
                $fotoHtml = '<img src="' . $photoUrl . '" class="img-thumbnail" style="max-height: 45px; max-width: 45px; cursor: pointer; object-fit: cover; border-radius: 4px;" onclick="openLightbox(\'' . $photoUrl . '\')" title="Klik untuk memperbesar">';
                if (count($photos) > 1) {
                    $fotoHtml .= '<br><span class="badge bg-secondary font-weight-normal mt-1" style="font-size: 8px; padding: 2px 4px;">+' . (count($photos) - 1) . ' foto</span>';
                }
            }

            $btnDetail = '<button type="button" class="btn btn-sm btn-info text-white btn-detail-modal" data-id="' . $row['id'] . '" title="Lihat Detail & Foto"><i class="fas fa-eye"></i></button>';
            
            $canTindakLanjut = in_array($role, ['administrator', 'admin_ulp', 'pdkb', 'har_gardu', 'har_konstruksi', 'har_row', 'har_crane', 'yantek']);
            $btnUpdate = '';
            if ($canTindakLanjut) {
                $btnUpdate = ' <button type="button" class="btn btn-sm btn-warning text-dark btn-update-status" data-id="' . $row['id'] . '" data-nomor="' . $row['nomor_temuan'] . '" title="Update Progress/Pekerjaan"><i class="fas fa-edit"></i></button>';
            }

            $actions = $btnDetail . $btnUpdate;

            $formattedData[] = [
                $row['nomor_temuan'],
                $row['nama_penyulang'],
                $row['nama_section'],
                $row['jenis_temuan'],
                $fotoHtml,
                $prioBadge,
                date('d-m-Y', strtotime($row['tanggal_temuan'])),
                $statusBadge,
                $actions
            ];
        }

        $result['data'] = $formattedData;

        return $this->response->setJSON($result);
    }
}
