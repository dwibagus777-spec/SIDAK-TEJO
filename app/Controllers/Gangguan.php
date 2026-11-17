<?php

namespace App\Controllers;

use App\Models\InterruptionModel;
use App\Repositories\PenyulangRepository;
use App\Repositories\UlpRepository;
use App\Services\GangguanAnalyticsService;
use App\Services\GoogleSheetsGangguanSyncService;
use App\Services\InterruptionExcelService;

class Gangguan extends BaseController
{
    private InterruptionModel $interruptionModel;
    private UlpRepository $ulpRepository;
    private PenyulangRepository $penyulangRepository;
    private GoogleSheetsGangguanSyncService $syncService;
    private GangguanAnalyticsService $analyticsService;

    public function __construct()
    {
        $this->interruptionModel   = new InterruptionModel();
        $this->ulpRepository       = new UlpRepository();
        $this->penyulangRepository = new PenyulangRepository();
        $this->syncService         = new GoogleSheetsGangguanSyncService();
        $this->analyticsService    = new GangguanAnalyticsService();
    }

    public function index()
    {
        $session   = session();
        $role      = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id') ? (int)$session->get('user_ulp_id') : null;
        $canManage = check_role(['administrator', 'admin', 'admin_pusat', 'admin_ulp', 'supervisor_up3', 'supervisor_ulp', 'inspeksi']);

        // Synchronize from Google Sheets (TTL-cached auto-sync, or explicit refresh when permitted)
        $forceRefresh = $canManage && ((string)$this->request->getGet('refresh_sync') === '1');
        $syncStatus   = $this->syncService->ensureSynced($forceRefresh);

        // Unified context resolution shared with Dashboard Utama
        $ctx = $this->analyticsService->resolveContext($this->request->getGet(), $role, $userUlpId);
        $yearA          = $ctx['year_a'];
        $yearB          = $ctx['year_b'];
        $availableYears = $ctx['available_years'];
        $ulpIdFilter    = $ctx['ulp_id'];
        $penyulangId    = $ctx['penyulang_id'];
        $scope          = $ctx['scope'];

        // All 12 Pareto & reliability analytics items computed from unified dataset
        $analytics12 = $this->analyticsService->getGangguanAnalytics12Items($ctx);
        $yoyInfo     = $analytics12['total_gangguan']['yoy'];
        $periodInfo  = [
            'yearA_period'      => $yoyInfo['yearA_period'],
            'yearB_period'      => $yoyInfo['yearB_period'],
            'comparable_months' => $yoyInfo['comparable_months'],
            'comparable_period' => $yoyInfo['comparable_period'],
        ];

        $totalA    = (int)$yoyInfo['comparable_total_a'];
        $totalB    = (int)$yoyInfo['comparable_total_b'];
        $selisih   = (int)$yoyInfo['comparable_diff'];
        $pctChange = $yoyInfo['comparable_pct'];

        // ─── Detailed Event Table Filtering (uses same scoped dataset) ─────
        $yearFilter     = $this->request->getGet('year') ? (int)$this->request->getGet('year') : null;
        $categoryFilter = $this->request->getGet('category') ? strtoupper(trim((string)$this->request->getGet('category'))) : null;
        $search         = trim((string)$this->request->getGet('search'));

        $allRows = array_values(array_filter($analytics12['filtered_rows'], static function (array $r) use ($yearFilter, $categoryFilter, $search): bool {
            if ($yearFilter !== null && $yearFilter > 0 && (int)$r['year'] !== $yearFilter) {
                return false;
            }
            if ($categoryFilter && strtoupper((string)$r['interruption_category']) !== $categoryFilter) {
                return false;
            }
            if ($search !== '') {
                $haystack = strtolower(
                    ($r['feeder_name'] ?? '') . ' ' .
                    ($r['field_narrative_raw'] ?? '') . ' ' .
                    ($r['device_name'] ?? '') . ' ' .
                    ($r['relay_trip_type'] ?? '') . ' ' .
                    ($r['cause_raw'] ?? '')
                );
                if (!str_contains($haystack, strtolower($search))) {
                    return false;
                }
            }
            return true;
        }));

        $totalCount = count($allRows);
        $totalKwh = 0.0; $dgrCount = 0; $ocrCount = 0; $tempCount = 0; $permCount = 0;
        foreach ($allRows as $r) {
            $totalKwh += (float)($r['energy_not_supplied_kwh'] ?? 0);
            $relay = strtoupper((string)($r['relay_trip_type'] ?? ''));
            if (str_contains($relay, 'DGR')) $dgrCount++;
            if (str_contains($relay, 'OCR')) $ocrCount++;
            if (($r['interruption_category'] ?? '') === 'PERMANENT') $permCount++;
            else $tempCount++;
        }

        $page          = max(1, (int)$this->request->getGet('page'));
        $perPage       = 25;
        $totalFiltered = $totalCount;
        $totalPages    = max(1, (int)ceil($totalFiltered / $perPage));
        $offset        = ($page - 1) * $perPage;
        $items         = array_slice($allRows, $offset, $perPage);

        $ulps = $this->ulpRepository->getActiveUlps();
        if ($ulpIdFilter) {
            $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($ulpIdFilter);
        } else {
            $db = \Config\Database::connect();
            $penyulangs = $db->table('penyulang')->select('id, nama_penyulang, ulp_id')->orderBy('nama_penyulang', 'ASC')->get()->getResultArray();
        }

        return view('gangguan/index', [
            // ── Sync & Context
            'syncStatus'     => $syncStatus,
            'ctx'            => $ctx,
            'scope'          => $scope,
            'analytics12'    => $analytics12,
            // ── Analytics (kept compatible)
            'yearA'          => $yearA,
            'yearB'          => $yearB,
            'availableYears' => $availableYears,
            'monthlyA'       => $analytics12['total_gangguan']['monthly_a'],
            'monthlyB'       => $analytics12['total_gangguan']['monthly_b'],
            'periodInfo'     => $periodInfo,
            'totalA'         => $totalA,
            'totalB'         => $totalB,
            'selisih'        => $selisih,
            'pctChange'      => $pctChange,
            'top10Merged'    => $analytics12['top_10_penyulang']['rows'],
            // ── List / filter
            'items'          => $items,
            'totalCount'     => $totalCount,
            'totalKwh'       => $totalKwh,
            'dgrCount'       => $dgrCount,
            'ocrCount'       => $ocrCount,
            'tempCount'      => $tempCount,
            'permCount'      => $permCount,
            'page'           => $page,
            'perPage'        => $perPage,
            'totalPages'     => $totalPages,
            'offset'         => $offset,
            'yearFilter'     => $yearFilter,
            'ulpIdFilter'    => $ulpIdFilter,
            'penyulangId'    => $penyulangId,
            'categoryFilter' => $categoryFilter,
            'search'         => $search,
            'ulps'           => $ulps,
            'penyulangs'     => $penyulangs,
            'canManage'      => $canManage,
        ]);
    }

    public function syncSheets()
    {
        $canManage = check_role(['administrator', 'admin', 'admin_pusat', 'admin_ulp', 'supervisor_up3', 'supervisor_ulp', 'inspeksi']);
        if (!$canManage) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['status' => 'error', 'message' => 'Akses ditolak.'])->setStatusCode(403);
            }
            return redirect()->to(site_url('gangguan'))->with('error', 'Anda tidak memiliki hak akses untuk sinkronisasi Google Sheets.');
        }

        $syncStatus = $this->syncService->ensureSynced(true);

        if ($this->request->isAJAX()) {
            $ok = ($syncStatus['status'] ?? '') === 'SYNCED';
            return $this->response->setJSON([
                'status'         => $ok ? 'success' : 'error',
                'message'        => $ok
                    ? sprintf('Sinkronisasi Google Sheets selesai (%d kejadian terbaca).', (int)($syncStatus['row_count'] ?? 0))
                    : ($syncStatus['last_error'] ?? 'Sinkronisasi gagal.'),
                'row_count'      => (int)($syncStatus['row_count'] ?? 0),
                'last_synced_at' => $syncStatus['last_synced_at'] ?? null,
                'changed'        => (bool)($syncStatus['changed'] ?? false),
            ])->setStatusCode($ok ? 200 : 502);
        }

        $redirectTo = trim((string)$this->request->getPost('redirect_to'));
        $targetPath = str_starts_with($redirectTo, 'dashboard') ? 'dashboard' : 'gangguan';
        $queryStr   = trim((string)$this->request->getPost('redirect_query'));
        $targetUrl  = site_url($targetPath) . ($queryStr !== '' ? '?' . ltrim($queryStr, '?') : '');

        if (($syncStatus['status'] ?? '') === 'SYNCED') {
            $msg = sprintf(
                'Data gangguan berhasil diperbarui dari %s (%d baris kejadian).',
                $syncStatus['source_label'] ?? 'Google Sheets',
                (int)($syncStatus['row_count'] ?? 0)
            );
            return redirect()->to($targetUrl)->with('success', $msg);
        }

        return redirect()->to($targetUrl)->with('error', 'Gagal memperbarui dari Google Sheets: ' . ($syncStatus['last_error'] ?? 'kesalahan tidak diketahui'));
    }


    public function create()
    {
        $canManage = check_role(['administrator', 'admin', 'admin_ulp', 'supervisor_up3', 'supervisor_ulp', 'inspeksi']);
        if (!$canManage) {
            return redirect()->to(site_url('gangguan'))->with('error', 'Anda tidak memiliki hak akses untuk menambah data gangguan.');
        }

        $db = \Config\Database::connect();
        $ulps = $db->table('ulps')->select('id, nama_ulp')->orderBy('nama_ulp', 'ASC')->get()->getResultArray();
        $penyulangs = $db->table('penyulang')->select('id, nama_penyulang, ulp_id')->orderBy('nama_penyulang', 'ASC')->get()->getResultArray();

        return view('gangguan/create', [
            'ulps'       => $ulps,
            'penyulangs' => $penyulangs,
        ]);
    }

    public function store()
    {
        $canManage = check_role(['administrator', 'admin', 'admin_ulp', 'supervisor_up3', 'supervisor_ulp', 'inspeksi']);
        if (!$canManage) {
            return redirect()->to(site_url('gangguan'))->with('error', 'Akses ditolak.');
        }

        $rules = [
            'event_date'              => 'required|valid_date[Y-m-d]',
            'interruption_started_at' => 'required',
            'penyulang_id'            => 'required|is_not_unique[penyulang.id]',
            'ulp_id'                  => 'required|is_not_unique[ulps.id]',
            'relay_trip_type'         => 'permit_empty|max_length[50]',
            'outage_duration_minutes' => 'permit_empty|numeric',
            'fault_current_amperes'   => 'permit_empty|numeric',
            'energy_not_supplied_kwh' => 'permit_empty|numeric',
        ];

        $messages = [
            'event_date' => [
                'required'   => 'Tanggal gangguan wajib diisi.',
                'valid_date' => 'Format tanggal gangguan tidak valid (YYYY-MM-DD).'
            ],
            'interruption_started_at' => [
                'required' => 'Waktu mulai trip/gangguan wajib diisi.'
            ],
            'penyulang_id' => [
                'required'      => 'Penyulang wajib dipilih.',
                'is_not_unique' => 'Penyulang yang dipilih tidak terdaftar di sistem.'
            ],
            'ulp_id' => [
                'required'      => 'ULP wajib dipilih.',
                'is_not_unique' => 'ULP yang dipilih tidak valid.'
            ]
        ];

        if (!$this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $penyulangId = (int)$this->request->getPost('penyulang_id');
        $ulpId = (int)$this->request->getPost('ulp_id');

        $penyulang = $db->table('penyulang')->select('nama_penyulang, ulp_id')->where('id', $penyulangId)->get()->getRowArray();
        $ulp = $db->table('ulps')->select('nama_ulp')->where('id', $ulpId)->get()->getRowArray();

        $startedAt = $this->request->getPost('interruption_started_at');
        $endedAt = $this->request->getPost('interruption_ended_at') ?: null;
        $duration = $this->request->getPost('outage_duration_minutes');

        if (empty($duration) && !empty($endedAt) && !empty($startedAt)) {
            $t1 = strtotime($startedAt);
            $t2 = strtotime($endedAt);
            if ($t2 > $t1) {
                $duration = round(($t2 - $t1) / 60, 2);
            }
        }

        $cleanUlpName = trim(str_ireplace('ULP ', '', (string)($ulp['nama_ulp'] ?? '')));

        $data = [
            'batch_id'                 => 0,
            'source_system'            => 'SIDAK_DIRECT_ENTRY',
            'source_sheet_name'        => 'WEB_FORM',
            'source_record_hash'       => hash('sha256', uniqid('ggn_', true)),
            'event_date'               => $this->request->getPost('event_date'),
            'interruption_started_at'  => $startedAt,
            'interruption_ended_at'    => $endedAt,
            'outage_duration_minutes'  => !empty($duration) ? (float)$duration : null,
            'substation_name'          => trim((string)$this->request->getPost('substation_name')) ?: 'GI SIDOARJO',
            'ulp_id'                   => $ulpId,
            'ulp_name'                 => $cleanUlpName,
            'penyulang_id'             => $penyulangId,
            'feeder_name'              => $penyulang['nama_penyulang'] ?? '',
            'switching_device_type'    => trim((string)$this->request->getPost('switching_device_type')) ?: 'RECL-PMCB',
            'device_name'              => trim((string)$this->request->getPost('device_name')),
            'relay_trip_type'          => strtoupper(trim((string)$this->request->getPost('relay_trip_type'))),
            'faulted_phase'            => trim((string)$this->request->getPost('faulted_phase')),
            'weather_condition'        => trim((string)$this->request->getPost('weather_condition')),
            'fault_current_amperes'    => $this->request->getPost('fault_current_amperes') !== '' ? (float)$this->request->getPost('fault_current_amperes') : null,
            'energy_not_supplied_kwh'  => $this->request->getPost('energy_not_supplied_kwh') !== '' ? (float)$this->request->getPost('energy_not_supplied_kwh') : null,
            'interruption_category'    => strtoupper(trim((string)$this->request->getPost('interruption_category'))) ?: 'TEMPORARY',
            'interruption_group'       => 'GGN_PENYULANG',
            'cause_raw'                => trim((string)$this->request->getPost('cause_raw')),
            'field_narrative_raw'      => trim((string)$this->request->getPost('field_narrative_raw')),
            'restoration_action_raw'   => trim((string)$this->request->getPost('restoration_action_raw')),
            'extracted_zone_section'   => trim((string)$this->request->getPost('extracted_zone_section')),
            'data_quality_score'       => 1.00,
            'ingestion_status'         => 'VALID',
        ];

        $this->interruptionModel->insert($data);

        return redirect()->to(site_url('gangguan'))->with('success', 'Data kejadian gangguan penyulang berhasil disimpan.');
    }

    public function previewExcel()
    {
        $canManage = check_role(['administrator', 'admin', 'admin_ulp', 'supervisor_up3']);
        if (!$canManage) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Anda tidak memiliki hak akses untuk mengunggah atau meninjau berkas gangguan.'
            ])->setStatusCode(403);
        }

        $session = session();
        $importDir = WRITEPATH . 'uploads/interruption_imports/';
        if (!is_dir($importDir)) {
            mkdir($importDir, 0755, true);
        }

        $useServer = (bool)$this->request->getPost('use_server_file');
        $token = 'import_' . bin2hex(random_bytes(16));
        $targetFile = $importDir . $token . '.xlsx';

        if ($useServer) {
            // Canonical allowed server paths only - strictly constrained
            $allowedServerPaths = [
                'E:\\GANGGUAN PENYULANG.xlsx',
                'E:/GANGGUAN PENYULANG.xlsx',
                ROOTPATH . 'GANGGUAN PENYULANG.xlsx',
                ROOTPATH . '../GANGGUAN PENYULANG.xlsx',
            ];
            $serverPathFound = null;
            foreach ($allowedServerPaths as $p) {
                if (file_exists($p)) {
                    $serverPathFound = $p;
                    break;
                }
            }

            if (!$serverPathFound) {
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'Berkas kanonik server E:\\GANGGUAN PENYULANG.xlsx tidak ditemukan pada path yang diizinkan.'
                ])->setStatusCode(404);
            }

            if (!copy($serverPathFound, $targetFile)) {
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'Gagal menyalin berkas server ke direktori isolasi impor.'
                ])->setStatusCode(500);
            }
        } else {
            $file = $this->request->getFile('excel_file');
            if (!$file || !$file->isValid()) {
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'Berkas Excel tidak valid atau gagal diunggah: ' . ($file ? $file->getErrorString() : 'Berkas tidak ditemukan')
                ])->setStatusCode(400);
            }

            $ext = strtolower($file->getClientExtension());
            if ($ext !== 'xlsx') {
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'Hanya berkas berekstensi .xlsx yang didukung oleh parser XLSX native.'
                ])->setStatusCode(400);
            }

            $file->move($importDir, $token . '.xlsx');
        }

        try {
            $excelService = new InterruptionExcelService();
            $previewResult = $excelService->previewXlsx($targetFile);

            // Register valid token in user session for import authorization
            $pending = $session->get('pending_excel_imports') ?? [];
            $pending[$token] = [
                'path'       => $targetFile,
                'created_at' => time(),
                'ready'      => $previewResult['ready_to_import'],
            ];
            $session->set('pending_excel_imports', $pending);

            return $this->response->setJSON([
                'status'       => 'success',
                'import_token' => $token,
                'data'         => $previewResult,
            ]);
        } catch (\Throwable $e) {
            if (file_exists($targetFile)) {
                @unlink($targetFile);
            }
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat memeriksa berkas Excel: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    public function importExcel()
    {
        $canManage = check_role(['administrator', 'admin', 'admin_ulp', 'supervisor_up3']);
        if (!$canManage) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Anda tidak memiliki wewenang untuk mengeksekusi impor data gangguan.'
            ])->setStatusCode(403);
        }

        $session = session();
        $token = trim((string)$this->request->getPost('import_token'));
        $pending = $session->get('pending_excel_imports') ?? [];

        if (empty($token) || !isset($pending[$token])) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Sesi impor tidak valid atau telah kedaluwarsa. Silakan lakukan pratinjau berkas kembali.'
            ])->setStatusCode(400);
        }

        $filePath = $pending[$token]['path'] ?? '';
        if (empty($filePath) || !file_exists($filePath)) {
            unset($pending[$token]);
            $session->set('pending_excel_imports', $pending);
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Berkas fisik impor pada direktori isolasi tidak ditemukan.'
            ])->setStatusCode(404);
        }

        $userId = (int)($session->get('user_id') ?? 1);
        $batchCode = 'BATCH-' . date('Ymd-His') . '-' . substr($token, 7, 6);

        try {
            $excelService = new InterruptionExcelService();
            $result = $excelService->importXlsx($filePath, $userId, $batchCode);

            // Clean up temporary file and session token after successful import
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            unset($pending[$token]);
            $session->set('pending_excel_imports', $pending);

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => "Impor berhasil diselesaikan: {$result['inserted_count']} data baru disimpan, {$result['duplicate_count']} duplikat dilewati, {$result['rejected_count']} baris ditolak.",
                'result'  => $result
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Validasi struktur gagal: ' . $e->getMessage()
            ])->setStatusCode(422);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Eksekusi transaksi impor gagal: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    public function delete($id)
    {
        $canManage = check_role(['administrator', 'admin']);
        if (!$canManage) {
            return redirect()->to(site_url('gangguan'))->with('error', 'Hanya Administrator yang berwenang menghapus histori gangguan.');
        }

        $item = $this->interruptionModel->find($id);
        if (!$item) {
            return redirect()->to(site_url('gangguan'))->with('error', 'Data gangguan tidak ditemukan.');
        }

        $this->interruptionModel->delete($id);

        return redirect()->to(site_url('gangguan'))->with('success', 'Data gangguan berhasil dihapus.');
    }

    public function apiFeedersByUlp($ulpId)
    {
        $db = \Config\Database::connect();
        $feeders = $db->table('penyulang')
            ->select('id, nama_penyulang')
            ->where('ulp_id', (int)$ulpId)
            ->where('deleted_at IS NULL')
            ->orderBy('nama_penyulang', 'ASC')
            ->get()->getResultArray();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $feeders
        ]);
    }
}
