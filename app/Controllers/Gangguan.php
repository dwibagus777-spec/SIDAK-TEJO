<?php

namespace App\Controllers;

use App\Models\InterruptionModel;
use App\Repositories\PenyulangRepository;
use App\Repositories\UlpRepository;

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
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($userUlpId)) {
            $ulpIdFilter = (int)$userUlpId;
        }

        $urlUlpId = $this->request->getGet('ulp_id');
        if ($urlUlpId && in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3'])) {
            $ulpIdFilter = (int)$urlUlpId;
        }

        $penyulangId = $this->request->getGet('penyulang_id') ? (int)$this->request->getGet('penyulang_id') : null;
        $yearFilter = $this->request->getGet('year') ? (int)$this->request->getGet('year') : null;
        $categoryFilter = $this->request->getGet('category') ? strtoupper(trim((string)$this->request->getGet('category'))) : null;
        $search = trim((string)$this->request->getGet('search'));

        $availableYears = $this->interruptionModel->getAvailableYears();
        if (empty($availableYears)) {
            $availableYears = [(int)date('Y'), (int)date('Y') - 1];
        }

        $builder = $this->interruptionModel->builder();
        $builder->select('historical_feeder_interruptions.*, ulps.nama_ulp as master_ulp, penyulang.nama_penyulang as master_penyulang')
            ->join('ulps', 'ulps.id = historical_feeder_interruptions.ulp_id', 'left')
            ->join('penyulang', 'penyulang.id = historical_feeder_interruptions.penyulang_id', 'left');

        if ($ulpIdFilter !== null) {
            $builder->where('historical_feeder_interruptions.ulp_id', $ulpIdFilter);
        }
        if ($penyulangId !== null && $penyulangId > 0) {
            $builder->where('historical_feeder_interruptions.penyulang_id', $penyulangId);
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

        // Stats summary
        $statsBuilder = clone $builder;
        $allRows = $statsBuilder->get()->getResultArray();
        $totalCount = count($allRows);
        $totalKwh = 0;
        $dgrCount = 0;
        $ocrCount = 0;
        $tempCount = 0;
        $permCount = 0;

        foreach ($allRows as $r) {
            $totalKwh += (float)($r['energy_not_supplied_kwh'] ?? 0);
            $relay = strtoupper((string)($r['relay_trip_type'] ?? ''));
            if (str_contains($relay, 'DGR')) $dgrCount++;
            if (str_contains($relay, 'OCR')) $ocrCount++;
            $cat = strtoupper((string)($r['interruption_category'] ?? ''));
            if ($cat === 'PERMANENT') $permCount++;
            else $tempCount++;
        }

        // Pagination: 25 items per page
        $page = max(1, (int)$this->request->getGet('page'));
        $perPage = 25;
        $totalFiltered = $totalCount;
        $totalPages = max(1, (int)ceil($totalFiltered / $perPage));
        $offset = ($page - 1) * $perPage;

        $items = array_slice($allRows, $offset, $perPage);

        // ULP and Feeder master lists
        $ulps = $this->ulpRepository->getActiveUlps();
        $penyulangs = [];
        if ($ulpIdFilter) {
            $penyulangs = $this->penyulangRepository->getActivePenyulangsByUlp($ulpIdFilter);
        } else {
            $db = \Config\Database::connect();
            $penyulangs = $db->table('penyulang')->select('id, nama_penyulang, ulp_id')->orderBy('nama_penyulang', 'ASC')->get()->getResultArray();
        }

        $canManage = check_role(['administrator', 'admin', 'admin_ulp', 'supervisor_up3', 'supervisor_ulp', 'inspeksi']);

        return view('gangguan/index', [
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
            'availableYears' => $availableYears,
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
            'field_narrative_raw'      => trim((string)$this->request->getPost('field_narrative_raw')),
            'restoration_action_raw'   => trim((string)$this->request->getPost('restoration_action_raw')),
            'extracted_zone_section'   => trim((string)$this->request->getPost('extracted_zone_section')),
            'data_quality_score'       => 1.00,
            'ingestion_status'         => 'VALID',
        ];

        $this->interruptionModel->insert($data);

        return redirect()->to(site_url('gangguan'))->with('success', 'Data kejadian gangguan penyulang berhasil disimpan.');
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
