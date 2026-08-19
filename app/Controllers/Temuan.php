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

    // ==========================================
    // PRIVATE HELPER METHODS (SOLID REFACTORING)
    // ==========================================

    /**
     * Dapatkan ULP dan Penyulang ter-scope berdasarkan role user
     */
    private function getScopedUlpsAndPenyulangs(): array
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

        return [$ulps, $penyulangs, $isRestricted];
    }

    /**
     * Rules validasi form input temuan
     */
    private function getTemuanFormRules(): array
    {
        return [
            'ulp_id'           => 'required|is_not_unique[ulps.id]',
            'penyulang_id'     => 'required|is_not_unique[penyulang.id]',
            'section_id'       => 'required|is_not_unique[sections.id]',
            'jenis_temuan'     => 'required|in_list[KONSTRUKSI,HOTSPOT,ROW]',
            'pelaksana'        => 'required|in_list[PDKB,HAR GARDU,HAR GTT,HAR KONSTRUKSI,HAR ROW,HAR CRANE]',
            'prioritas'        => 'required|in_list[EMERGENCY,HIGH,MEDIUM]',
            'potensi_gangguan' => 'required|in_list[DGR,OCR,OCRDGR]',
            'konduktor'        => 'required|max_length[100]',
            'noga'             => 'permit_empty|max_length[100]',
            'material'         => 'permit_empty',
            'detail_temuan'    => 'required',
            'alamat'           => 'required',
            'tanggal_temuan'   => 'required',
        ];
    }

    /**
     * Format data baris untuk DataTables
     */
    private function formatDataTablesRow(array $row, string $role, bool $includeUpdateBtn = false): array
    {
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

        // SLA & Status Badge
        $sla = get_sla_status($row['prioritas'], $row['tanggal_temuan'], $row['status']);
        $statusBadge = $sla['badge_html'];

        // Tombol Aksi - Direct link to Enterprise Detail View
        $detailUrl = site_url('temuan/detail/' . $row['id']);
        $btnDetail = '<a href="' . $detailUrl . '" class="btn btn-sm btn-info text-white" title="Lihat Detail Enterprise"><i class="fas fa-eye"></i></a>';
        
        $actions = $btnDetail;

        if ($includeUpdateBtn) {
            $canTindakLanjut = in_array($role, ['administrator', 'admin_ulp', 'pdkb', 'har_gardu', 'har_konstruksi', 'har_row', 'har_crane', 'yantek']);
            if ($canTindakLanjut) {
                $actions .= ' <button type="button" class="btn btn-sm btn-warning text-dark btn-update-status" data-id="' . $row['id'] . '" data-nomor="' . $row['nomor_temuan'] . '" title="Update Progress/Pekerjaan"><i class="fas fa-edit"></i></button>';
            }
        } else {
            if (check_role(['administrator', 'admin', 'admin_pusat', 'admin_ulp', 'inspeksi', 'pdkb', 'har_gardu', 'har_konstruksi', 'har_row', 'har_crane', 'yantek', 'supervisor_ulp', 'supervisor_up3'])) {
                $deleteUrl = site_url('temuan/delete/' . $row['id']);
                $actions .= ' <a href="' . $deleteUrl . '" onclick="return confirm(\'Apakah Anda yakin ingin menghapus temuan ' . esc(addslashes($row['nomor_temuan']), 'attr') . '?\');" class="btn btn-sm btn-danger" title="Hapus"><i class="fas fa-trash"></i></a>';
            }
        }

        // Foto Column Thumbnail
        $fotoHtml = '<span class="text-muted small">Tidak ada</span>';
        $photos = json_decode($row['foto'] ?? '', true) ?: [];
        if (is_string($row['foto'] ?? null) && empty($photos) && !empty($row['foto'])) {
            $photos = [$row['foto']];
        }

        if (!empty($photos) && !empty($photos[0])) {
            $photoUrl = get_photo_url($photos[0], $row['foto_path'] ?? 'foto/');
            $fotoHtml = '<img loading="lazy" src="' . $photoUrl . '" class="img-thumbnail" style="max-height: 45px; max-width: 45px; cursor: pointer; object-fit: cover; border-radius: 4px;" onclick="openLightbox(\'' . $photoUrl . '\')" onerror="this.onerror=null; this.parentElement.innerHTML=\'<span class=&quot;text-muted small&quot;>Tidak ada foto</span>\';" title="Klik untuk memperbesar">';
            if (count($photos) > 1) {
                $fotoHtml .= '<br><span class="badge bg-secondary font-weight-normal mt-1" style="font-size: 8px; padding: 2px 4px;">+' . (count($photos) - 1) . ' foto</span>';
            }
        }

        $rawDate = !empty($row['created_at']) ? $row['created_at'] : (!empty($row['tanggal_temuan']) ? $row['tanggal_temuan'] : null);
        $tglStr = '-';
        if ($rawDate) {
            $ts = strtotime($rawDate);
            if ($ts !== false && $ts > 0) {
                $tglStr = date('d-m-Y H:i', $ts) . ' WIB';
            }
        }

        return [
            '<a href="' . $detailUrl . '" class="font-weight-bold text-primary text-decoration-none"><i class="fas fa-file-invoice me-1"></i>' . esc($row['nomor_temuan']) . '</a>',
            $row['nama_penyulang'],
            $row['nama_section'],
            $row['jenis_temuan'],
            $fotoHtml,
            $prioBadge,
            $tglStr,
            $statusBadge,
            $actions
        ];
    }

    /**
     * Ekstrak file upload yang valid dari request
     */
    private function extractUploadFiles(array $keys): array
    {
        $uploadFiles = [];
        foreach ($keys as $key) {
            $file = $this->request->getFile($key);
            if ($file && $file->isValid() && !$file->hasMoved()) {
                $uploadFiles[$key] = $file;
            }
        }
        return $uploadFiles;
    }


    // ==========================================
    // PUBLIC CONTROLLER ACTIONS
    // ==========================================

    public function index()
    {
        [$ulps, $penyulangs, $isRestricted] = $this->getScopedUlpsAndPenyulangs();

        return view('temuan/index', [
            'ulps'         => $ulps,
            'penyulangs'   => $penyulangs,
            'isRestricted' => $isRestricted
        ]);
    }

    /**
     * Endpoint DataTables Server Side
     */
    public function ajaxDataTables()
    {
        $scoping = get_user_role_scoping();
        $role = (string)session()->get('user_role');

        $postData = $this->request->getPost();
        $result = $this->temuanRepository->getDataTables($postData, $scoping['ulp_id'], $scoping['jenis_temuan']);

        $formattedData = [];
        foreach ($result['data'] as $row) {
            $formattedData[] = $this->formatDataTablesRow($row, $role, false);
        }

        $result['data'] = $formattedData;
        return $this->jsonResponse($result);
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

        if (!$this->validate($this->getTemuanFormRules())) {
            $ulps = ($role === 'admin_ulp' && $userUlpId !== null) 
                ? [$this->ulpRepository->find($userUlpId)] 
                : $this->ulpRepository->getActiveUlps();

            return view('temuan/create', [
                'ulps'       => $ulps,
                'validation' => $this->validator
            ]);
        }

        $materialInput = trim($this->request->getPost('material') ?? '');
        if (empty($materialInput)) {
            $materialInput = 'Tidak ada spesifikasi material';
        }

        $ulpIdInput = (int)$this->request->getPost('ulp_id');
        if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== $ulpIdInput) {
            return redirect()->to(site_url('temuan/create'))->with('error', 'Anda hanya diizinkan menginput temuan untuk ULP Anda.');
        }

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
            'material'         => $materialInput,
            'detail_temuan'    => trim($this->request->getPost('detail_temuan')),
            'alamat'           => trim($this->request->getPost('alamat')),
            'latitude'         => $this->request->getPost('latitude') !== '' ? (float)$this->request->getPost('latitude') : null,
            'longitude'        => $this->request->getPost('longitude') !== '' ? (float)$this->request->getPost('longitude') : null,
            'tanggal_temuan'   => $this->request->getPost('tanggal_temuan'),
        ];

        $files = $this->request->getFileMultiple('foto');
        $res = $this->temuanService->createTemuan($data, $files);

        if ($res['success']) {
            $insertedId = $res['id'] ?? null;
            if ($insertedId) {
                return redirect()->to(site_url('temuan/detail/' . $insertedId))->with('success', $res['message'] . ' Berhasil dialihkan ke Detail Temuan.');
            }
            return redirect()->to(site_url('temuan'))->with('success', $res['message']);
        }

        return redirect()->to(site_url('temuan/create'))->withInput()->with('error', $res['message']);
    }

    /**
     * Smart QR Code Lookup Dispatcher for Temuan
     */
    public function lookup()
    {
        $code = trim((string)$this->request->getGet('code'));
        if (empty($code)) {
            return redirect()->to(site_url('temuan'))->with('error', 'Silakan ketik atau scan Kode Temuan.');
        }

        $session = session();
        $role = (string)$session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3', 'har_crane', 'inspeksi']) && $userUlpId !== null) {
            $ulpIdFilter = (int)$userUlpId;
        }

        // 1. If numeric ID passed directly
        if (is_numeric($code) && (int)$code > 0) {
            $t = $this->temuanRepository->getDetail((int)$code, $ulpIdFilter);
            if ($t) {
                return redirect()->to(site_url('temuan/detail/' . $t['id']));
            }
        }

        // 2. Query DB by nomor_temuan
        $db = \Config\Database::connect();
        $builder = $db->table('temuan t')
            ->select('t.id')
            ->where('t.deleted_at IS NULL')
            ->groupStart()
                ->where('t.nomor_temuan', $code)
                ->orLike('t.nomor_temuan', $code)
            ->groupEnd();

        if ($ulpIdFilter !== null) {
            $builder->where('t.ulp_id', $ulpIdFilter);
        }

        $row = $builder->get()->getRowArray();
        if ($row && !empty($row['id'])) {
            return redirect()->to(site_url('temuan/detail/' . $row['id']));
        }

        // 3. Fallback to /temuan?search=code
        return redirect()->to(site_url('temuan') . '?search=' . urlencode($code))
            ->with('info', 'Mencari data temuan dengan kata kunci: ' . esc($code));
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

        $sla = get_sla_status($temuan['prioritas'], $temuan['tanggal_temuan'], $temuan['status'], $temuan['tanggal_selesai']);
        $history = $this->tindakLanjutRepository->getHistoryByTemuan($id);

        $trace = [
            'ROUTE_TRACE_2026'      => 'temuan/detail/' . $id,
            'CONTROLLER_TRACE_2026' => __METHOD__ . ' (' . realpath(__FILE__) . ')',
            'VIEW_TRACE_2026'       => 'app/Views/temuan/detail.php (' . realpath(APPPATH . 'Views/temuan/detail.php') . ')',
            'BUILD_TRACE_2026'      => 'BUILD_2026_ENTERPRISE_PLN_MOBILE',
            'HTML_TRACE_2026'       => 'RUNTIME_HTML_TRACE_ACTIVE',
            'DOC_ROOT_TRACE'        => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
            'FCPATH_TRACE'          => defined('FCPATH') ? FCPATH : 'N/A',
            'APPPATH_TRACE'         => defined('APPPATH') ? APPPATH : 'N/A',
            'WRITEPATH_TRACE'       => defined('WRITEPATH') ? WRITEPATH : 'N/A',
        ];

        return view('temuan/detail', [
            'temuan'  => $temuan,
            'sla'     => $sla,
            'history' => $history,
            'trace'   => $trace
        ]);
    }

    /**
     * AJAX endpoint untuk modal detail cepat
     */
    public function ajaxDetail(int $id)
    {
        try {
            $session = session();
            $role = strtolower((string)$session->get('user_role'));

            $temuan = $this->temuanRepository->getDetail($id, null);
            if (!$temuan) {
                return $this->jsonResponse(['success' => false, 'error' => 'Data temuan tidak ditemukan.'], 404);
            }

            $sla     = get_sla_status($temuan['prioritas'], $temuan['tanggal_temuan'], $temuan['status'], $temuan['tanggal_selesai']);
            $history = $this->tindakLanjutRepository->getHistoryByTemuan($id);

            return $this->jsonResponse([
                'success'   => true,
                'temuan'    => $temuan,
                'sla'       => $sla,
                'history'   => $history,
                'canEdit'   => in_array($role, ['administrator', 'admin', 'admin_pusat', 'admin_ulp', 'inspeksi', 'pdkb', 'har_gardu', 'har_konstruksi', 'har_row', 'har_crane', 'yantek']),
                'canDelete' => in_array($role, ['administrator', 'admin', 'admin_pusat', 'admin_ulp']),
                'editUrl'   => site_url('temuan/edit/' . $id),
                'detailUrl' => site_url('temuan/detail/' . $id),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'ajaxDetail Error: ' . $e->getMessage());
            return $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
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
                return $this->jsonResponse(['success' => false, 'message' => 'Temuan tidak ditemukan atau Anda tidak memiliki akses.']);
            }
            return redirect()->to(site_url('temuan'))->with('error', 'Temuan tidak ditemukan atau Anda tidak memiliki akses.');
        }

        $rules = [
            'status_progress' => 'required|in_list[PROSES,SELESAI,BUTUH PADAM,TERKENDALA]',
            'komentar'        => 'required'
        ];

        if (!$this->validate($rules)) {
            if ($isAjax) {
                return $this->jsonResponse(['success' => false, 'message' => 'Status dan komentar tindak lanjut wajib diisi.']);
            }
            return redirect()->to(site_url('temuan/detail/' . $id))->with('error', 'Status dan komentar tindak lanjut wajib diisi.');
        }

        $progressData = [
            'status_progress' => $this->request->getPost('status_progress'),
            'komentar'        => trim($this->request->getPost('komentar')),
            'pelaksana'       => $session->get('user_name') ?: 'Petugas'
        ];

        $uploadFiles = $this->extractUploadFiles(['foto_sebelum', 'foto_proses', 'foto_sesudah']);

        $res = $this->temuanService->updateTemuanPekerjaan($id, $progressData, $uploadFiles);

        if ($isAjax) {
            return $this->jsonResponse($res);
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

        if (!$this->validate($this->getTemuanFormRules())) {
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

        $materialInput = trim($this->request->getPost('material') ?? '');
        if (empty($materialInput)) {
            $materialInput = 'Tidak ada spesifikasi material';
        }

        $ulpIdInput = (int)$this->request->getPost('ulp_id');
        if ($role === 'admin_ulp' && $userUlpId !== null && (int)$userUlpId !== $ulpIdInput) {
            return redirect()->to(site_url("temuan/edit/{$id}"))->with('error', 'Anda hanya diizinkan memilih ULP Anda.');
        }

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
            'material'         => $materialInput,
            'detail_temuan'    => trim($this->request->getPost('detail_temuan')),
            'alamat'           => trim($this->request->getPost('alamat')),
            'latitude'         => $this->request->getPost('latitude') !== '' ? (float)$this->request->getPost('latitude') : null,
            'longitude'        => $this->request->getPost('longitude') !== '' ? (float)$this->request->getPost('longitude') : null,
            'tanggal_temuan'   => $this->request->getPost('tanggal_temuan'),
        ];

        $newFiles = $this->request->getFileMultiple('foto');
        $replaceOld = $this->request->getPost('replace_photos') !== '0';
        $res = $this->temuanService->updateTemuan($id, $data, $newFiles, $replaceOld);

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
                    return $this->jsonResponse(['success' => false, 'message' => 'Data temuan tidak ditemukan atau sudah dihapus.']);
                }
                return redirect()->to(site_url('temuan'))->with('error', 'Data temuan tidak ditemukan.');
            }

            $session = session();
            $role = strtolower((string)$session->get('user_role'));
            $userUlpId = $session->get('user_ulp_id');

            if ($role === 'admin_ulp' && $userUlpId !== null && (int)$temuan['ulp_id'] !== (int)$userUlpId) {
                log_message('warning', "[DELETE_TEMUAN] Akses ditolak untuk role admin_ulp | User ULP: {$userUlpId} | Temuan ULP: {$temuan['ulp_id']}");
                if ($isAjax) {
                    return $this->jsonResponse(['success' => false, 'message' => 'Anda tidak memiliki hak akses untuk menghapus data temuan ULP lain.']);
                }
                return redirect()->to(site_url('temuan'))->with('error', 'Anda tidak memiliki hak akses.');
            }

            $now = date('Y-m-d H:i:s');
            $db->table('temuan')->where('id', $id)->update(['deleted_at' => $now]);
            $affectedRows = $db->affectedRows();

            log_message('info', "[DELETE_TEMUAN] Query UPDATE executed | ID: {$id} | Affected Rows: {$affectedRows}");

            if ($affectedRows > 0) {
                log_activity('DELETE_TEMUAN', 'Menghapus temuan: ' . $temuan['nomor_temuan']);
                if ($isAjax) {
                    return $this->jsonResponse(['success' => true, 'message' => 'Temuan ' . esc($temuan['nomor_temuan']) . ' berhasil dihapus.']);
                }
                return redirect()->to(site_url('temuan'))->with('success', 'Temuan ' . esc($temuan['nomor_temuan']) . ' berhasil dihapus.');
            }

            $dbError = $db->error();
            log_message('error', "[DELETE_TEMUAN_FAIL] DB Error Code: {$dbError['code']} | DB Error Msg: {$dbError['message']} | ID: {$id}");

            if ($isAjax) {
                return $this->jsonResponse(['success' => false, 'message' => 'Gagal menghapus data dari database. Error Code: ' . $dbError['code']]);
            }
            return redirect()->to(site_url('temuan'))->with('error', 'Gagal menghapus data.');

        } catch (\Throwable $e) {
            log_message('error', "[DELETE_TEMUAN_EXCEPTION] " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            if ($isAjax) {
                return $this->jsonResponse(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
            }
            return redirect()->to(site_url('temuan'))->with('error', 'Server error: ' . $e->getMessage());
        }
    }

    // --- AJAX Cascades ---

    public function ajaxGetPenyulang(int $ulpId)
    {
        $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($ulpId);
        return $this->jsonResponse($penyulangs);
    }

    public function ajaxGetSection(int $penyulangId)
    {
        $sections = $this->sectionRepository->getActiveSectionsByPenyulang($penyulangId);
        return $this->jsonResponse($sections);
    }

    public function terdekat()
    {
        [$ulps, $penyulangs, $isRestricted] = $this->getScopedUlpsAndPenyulangs();

        return view('temuan/terdekat', [
            'ulps'         => $ulps,
            'penyulangs'   => $penyulangs,
            'isRestricted' => $isRestricted
        ]);
    }

    public function ajaxTerdekat()
    {
        try {
            $scoping = get_user_role_scoping();

            $lat = $this->request->getGet('latitude');
            $lng = $this->request->getGet('longitude');
            $radius = $this->request->getGet('radius');
            
            if (empty($lat) || empty($lng)) {
                return $this->jsonResponse([]);
            }
            
            $lat = (float)$lat;
            $lng = (float)$lng;
            $radius = (float)($radius ?: 1000) / 1000;
            
            $q = trim((string)($this->request->getGet('q') ?: $this->request->getGet('penyulang')));
            if (!empty($q)) {
                $radius = 200;
            }

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

            if (!empty($q)) {
                $sql .= " AND (p.nama_penyulang LIKE ? OR s.nama_section LIKE ? OR t.nomor_temuan LIKE ? OR t.detail_temuan LIKE ? OR t.jenis_temuan LIKE ?)";
                $like = '%' . $q . '%';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            if ($scoping['ulp_id'] !== null) {
                $sql .= " AND t.ulp_id = ?";
                $params[] = (int)$scoping['ulp_id'];
            }

            if ($scoping['jenis_temuan'] !== null) {
                $sql .= " AND t.jenis_temuan = ?";
                $params[] = $scoping['jenis_temuan'];
            }

            $sql .= ") AS sub_temuan
                      WHERE distance_km <= ?
                      ORDER BY distance_km ASC
                      LIMIT 100";
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
            
            return $this->jsonResponse($results);
        } catch (\Throwable $e) {
            log_message('error', 'ajaxTerdekat Error: ' . $e->getMessage());
            return $this->jsonResponse([]);
        }
    }

    public function updatePekerjaan()
    {
        [$ulps, $penyulangs, $isRestricted] = $this->getScopedUlpsAndPenyulangs();
        $role = session()->get('user_role');

        $rolePelaksanaMap = [
            'pdkb'           => 'PDKB',
            'har_gardu'      => 'HAR GARDU',
            'har_row'        => 'HAR ROW',
            'har_crane'      => 'HAR CRANE',
            'har_konstruksi' => 'HAR KONSTRUKSI',
            'yantek'         => 'YANTEK'
        ];
        $lockedPelaksana = $rolePelaksanaMap[$role] ?? null;

        return view('temuan/update_pekerjaan', [
            'ulps'            => $ulps,
            'penyulangs'      => $penyulangs,
            'isRestricted'    => $isRestricted,
            'lockedPelaksana' => $lockedPelaksana
        ]);
    }

    public function ajaxUpdatePekerjaan()
    {
        $session = session();
        $role = (string)$session->get('user_role');
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        $unrestrictedRoles = ['administrator', 'har_crane', 'pdkb', 'inspeksi'];
        if (!in_array($role, $unrestrictedRoles) && $userUlpId !== null) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $postData = $this->request->getPost();

        $rolePelaksanaMap = [
            'pdkb'           => 'PDKB',
            'har_gardu'      => 'HAR GARDU',
            'har_row'        => 'HAR ROW',
            'har_crane'      => 'HAR CRANE',
            'har_konstruksi' => 'HAR KONSTRUKSI',
            'yantek'         => 'YANTEK'
        ];
        if (isset($rolePelaksanaMap[$role])) {
            $postData['pelaksana'] = $rolePelaksanaMap[$role];
        }

        $result = $this->temuanRepository->getDataTables($postData, $ulpIdFilter);

        $formattedData = [];
        foreach ($result['data'] as $row) {
            $formattedData[] = $this->formatDataTablesRow($row, $role, true);
        }

        $result['data'] = $formattedData;
        return $this->jsonResponse($result);
    }
}
