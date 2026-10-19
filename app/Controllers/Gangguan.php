<?php

namespace App\Controllers;

use App\Models\InterruptionModel;
use App\Repositories\PenyulangRepository;
use App\Repositories\UlpRepository;
use App\Services\InterruptionExcelService;

class Gangguan extends BaseController
{
    private InterruptionModel $interruptionModel;
    private UlpRepository $ulpRepository;
    private PenyulangRepository $penyulangRepository;

    public function __construct()
    {
        $this->interruptionModel = new InterruptionModel();
        $this->ulpRepository = new UlpRepository();
        $this->penyulangRepository = new PenyulangRepository();
    }

    public function index()
    {
        $session    = session();
        $role       = strtolower((string)$session->get('user_role'));
        $userUlpId  = $session->get('user_ulp_id');

        // ─── Role-based ULP scope ───────────────────────────────────────────
        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($userUlpId)) {
            $ulpIdFilter = (int)$userUlpId;
        }
        $urlUlpId = $this->request->getGet('ulp_id');
        if ($urlUlpId && in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3'])) {
            $ulpIdFilter = (int)$urlUlpId;
        }

        // ─── Available years from DB (no hardcode) ──────────────────────────
        $availableYears = $this->interruptionModel->getAvailableYears();
        if (empty($availableYears)) {
            $availableYears = [(int)date('Y'), (int)date('Y') - 1];
        }
        $latestYear = $availableYears[0] ?? (int)date('Y');

        // ─── Year A / Year B (analytics selectors) ─────────────────────────
        $yearA = $this->request->getGet('year_a') ? (int)$this->request->getGet('year_a') : $latestYear;
        $yearB = $this->request->getGet('year_b') ? (int)$this->request->getGet('year_b') : ($yearA - 1);
        // Make sure they are within available set (fallback gracefully)
        if (!in_array($yearA, $availableYears)) $yearA = $latestYear;
        if (!in_array($yearB, $availableYears) && count($availableYears) > 1) $yearB = $availableYears[1];

        $penyulangId = $this->request->getGet('penyulang_id') ? (int)$this->request->getGet('penyulang_id') : null;

        // ─── Analytics: Monthly breakdown per year ──────────────────────────
        $monthlyA = $this->interruptionModel->getMonthlyByYear($yearA, $ulpIdFilter, $penyulangId);
        $monthlyB = $this->interruptionModel->getMonthlyByYear($yearB, $ulpIdFilter, $penyulangId);

        // ─── Analytics: Comparable period description ───────────────────────
        $periodInfo       = $this->interruptionModel->getComparablePeriod($yearA, $yearB, $ulpIdFilter, $penyulangId);
        $comparableMonths = $periodInfo['comparable_months'];

        $totalA = $this->interruptionModel->getTotalByYearUpToMonth($yearA, $comparableMonths, $ulpIdFilter, $penyulangId);
        $totalB = $this->interruptionModel->getTotalByYearUpToMonth($yearB, $comparableMonths, $ulpIdFilter, $penyulangId);
        $selisih = $totalA - $totalB;
        $pctChange = ($totalB > 0) ? round(($selisih / $totalB) * 100, 1) : null;

        // ─── Analytics: Top 10 penyulang ────────────────────────────────────
        $top10A = $this->interruptionModel->getTop10ByYear($yearA, $ulpIdFilter, 10);
        $top10B = $this->interruptionModel->getTop10ByYear($yearB, $ulpIdFilter, 10);

        // Build indexed lookup for yearB by penyulang_id for easy join
        $top10BIdx = [];
        foreach ($top10B as $row) {
            if ($row['penyulang_id']) $top10BIdx[(int)$row['penyulang_id']] = (int)$row['cnt'];
        }

        // Merge: use yearA list as base, append yearB-only entries
        $top10Merged = [];
        foreach ($top10A as $row) {
            $pid  = (int)$row['penyulang_id'];
            $cntB = $top10BIdx[$pid] ?? 0;
            $top10Merged[] = [
                'penyulang_id'   => $pid,
                'nama_penyulang' => $row['nama_penyulang'],
                'nama_ulp'       => $row['nama_ulp'],
                'cnt_a'          => (int)$row['cnt'],
                'cnt_b'          => $cntB,
                'selisih'        => (int)$row['cnt'] - $cntB,
            ];
            unset($top10BIdx[$pid]);
        }

        // ─── List / filter (existing table view) ───────────────────────────
        $yearFilter   = $this->request->getGet('year') ? (int)$this->request->getGet('year') : null;
        $categoryFilter = $this->request->getGet('category') ? strtoupper(trim((string)$this->request->getGet('category'))) : null;
        $search       = trim((string)$this->request->getGet('search'));

        $hasUlpId       = $this->interruptionModel->hasColumn('ulp_id');
        $hasPenyulangId = $this->interruptionModel->hasColumn('penyulang_id');

        $builder = $this->interruptionModel->builder();
        if ($hasUlpId && $hasPenyulangId) {
            $builder->select('historical_feeder_interruptions.*, ulps.nama_ulp as master_ulp, penyulang.nama_penyulang as master_penyulang')
                ->join('ulps', 'ulps.id = historical_feeder_interruptions.ulp_id', 'left')
                ->join('penyulang', 'penyulang.id = historical_feeder_interruptions.penyulang_id', 'left');
        } else {
            $builder->select('historical_feeder_interruptions.*, historical_feeder_interruptions.ulp_name as master_ulp, historical_feeder_interruptions.feeder_name as master_penyulang');
        }

        if ($ulpIdFilter !== null) {
            if ($hasUlpId) {
                $builder->where('historical_feeder_interruptions.ulp_id', $ulpIdFilter);
            } else {
                $uRow = $this->ulpRepository->find($ulpIdFilter);
                if ($uRow) {
                    $cleanU = trim(str_ireplace('ULP', '', (string)$uRow['nama_ulp']));
                    $builder->like('historical_feeder_interruptions.ulp_name', $cleanU);
                }
            }
        }
        if ($penyulangId !== null && $penyulangId > 0) {
            if ($hasPenyulangId) {
                $builder->where('historical_feeder_interruptions.penyulang_id', $penyulangId);
            } else {
                $dbTmp = \Config\Database::connect();
                $pRow = $dbTmp->table('penyulang')->select('nama_penyulang')->where('id', $penyulangId)->get()->getRowArray();
                if ($pRow) {
                    $builder->where('historical_feeder_interruptions.feeder_name', $pRow['nama_penyulang']);
                }
            }
        }
        if ($yearFilter !== null && $yearFilter > 0) {
            $builder->where('YEAR(historical_feeder_interruptions.event_date)', $yearFilter);
        }
        if ($categoryFilter) {
            $builder->where('historical_feeder_interruptions.interruption_category', $categoryFilter);
        }
        if ($search !== '') {
            $builder->groupStart()
                ->like('historical_feeder_interruptions.feeder_name', $search)
                ->orLike('historical_feeder_interruptions.field_narrative_raw', $search)
                ->orLike('historical_feeder_interruptions.device_name', $search)
                ->orLike('historical_feeder_interruptions.relay_trip_type', $search)
                ->groupEnd();
        }

        $builder->orderBy('historical_feeder_interruptions.event_date', 'DESC')
                ->orderBy('historical_feeder_interruptions.interruption_started_at', 'DESC');

        $statsBuilder = clone $builder;
        $allRows  = $statsBuilder->get()->getResultArray();
        $totalCount = count($allRows);
        $totalKwh = 0; $dgrCount = 0; $ocrCount = 0; $tempCount = 0; $permCount = 0;
        foreach ($allRows as $r) {
            $totalKwh += (float)($r['energy_not_supplied_kwh'] ?? 0);
            $relay = strtoupper((string)($r['relay_trip_type'] ?? ''));
            if (str_contains($relay, 'DGR')) $dgrCount++;
            if (str_contains($relay, 'OCR')) $ocrCount++;
            $cat = strtoupper((string)($r['interruption_category'] ?? ''));
            if ($cat === 'PERMANENT') $permCount++;
            else $tempCount++;
        }

        $page = max(1, (int)$this->request->getGet('page'));
        $perPage = 25;
        $totalFiltered = $totalCount;
        $totalPages = max(1, (int)ceil($totalFiltered / $perPage));
        $offset = ($page - 1) * $perPage;
        $items  = array_slice($allRows, $offset, $perPage);

        $ulps = $this->ulpRepository->getActiveUlps();
        if ($ulpIdFilter) {
            $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($ulpIdFilter);
        } else {
            $db = \Config\Database::connect();
            $penyulangs = $db->table('penyulang')->select('id, nama_penyulang, ulp_id')->orderBy('nama_penyulang', 'ASC')->get()->getResultArray();
        }

        $canManage = check_role(['administrator', 'admin', 'admin_ulp', 'supervisor_up3', 'supervisor_ulp', 'inspeksi']);

        return view('gangguan/index', [
            // ── Analytics
            'yearA'          => $yearA,
            'yearB'          => $yearB,
            'availableYears' => $availableYears,
            'monthlyA'       => array_values($monthlyA),  // 0-indexed for JS
            'monthlyB'       => array_values($monthlyB),
            'periodInfo'     => $periodInfo,
            'totalA'         => $totalA,
            'totalB'         => $totalB,
            'selisih'        => $selisih,
            'pctChange'      => $pctChange,
            'top10Merged'    => $top10Merged,
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
