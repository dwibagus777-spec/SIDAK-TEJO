<?php

namespace App\Controllers;

use App\Repositories\TemuanRepository;
use App\Models\TemuanModel;

class Dashboard extends BaseController
{
    private TemuanRepository $temuanRepository;
    private TemuanModel $temuanModel;

    public function __construct()
    {
        $this->temuanRepository = new TemuanRepository();
        $this->temuanModel = new TemuanModel();
    }

    public function index()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));

        $ulpId = $session->get('user_ulp_id');

        // Check if user forced a specific view mode ('mobile' or 'desktop')
        $viewMode = $session->get('view_mode') ?: ($_COOKIE['view_mode'] ?? null);

        $agent = $this->request->getUserAgent();
        $isMobile = $agent->isMobile();

        // Determine whether to show the mobile layout
        $showMobile = false;
        if ($viewMode === 'mobile') {
            $showMobile = true;
        } elseif ($viewMode === 'desktop') {
            $showMobile = false;
        } else {
            // Auto detect
            $showMobile = $isMobile;
        }

        // Role-based data scoping
        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($ulpId)) {
            $ulpIdFilter = (int)$ulpId;
        }

        // Admin/supervisor can override via ?ulp_id= URL param
        $urlUlpId = $this->request->getGet('ulp_id');
        if ($urlUlpId && in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3'])) {
            $ulpIdFilter = (int)$urlUlpId;
        }

        // Month & Year Filter for Top 10 Leaderboard & Stats
        $monthFilter = $this->request->getGet('month') ? (int)$this->request->getGet('month') : date('n');
        $yearFilter = $this->request->getGet('year') ? (int)$this->request->getGet('year') : date('Y');

        // Ambil Top 10 Leaderboard
        $topInputOfficers = $this->temuanModel->getTopInputOfficers($monthFilter, $yearFilter, $ulpIdFilter);
        $topUpdateOfficers = $this->temuanModel->getTopUpdateOfficers($monthFilter, $yearFilter, $ulpIdFilter);

        // Permission Flags
        $canInput = check_role(['administrator', 'admin_ulp', 'inspeksi']);
        $canEdit = check_role(['administrator', 'admin_ulp', 'inspeksi', 'yantek', 'pdkb', 'har_gardu', 'har_konstruksi', 'har_row', 'har_crane']);
        $canDelete = check_role(['administrator']);
        $canApprove = check_role(['administrator', 'supervisor_ulp', 'supervisor_up3']);
        $canMonitoring = check_role(['administrator', 'admin_pusat', 'supervisor_up3', 'manager']);

        // Fetch All Dashboard Analytics Data (System-scoped or ULP-scoped, preventing empty role stats)
        $db = \Config\Database::connect();

        // Available years in temuan database
        $yearsQuery = $db->table('temuan')
            ->select('DISTINCT YEAR(tanggal_temuan) as yr')
            ->where('tanggal_temuan IS NOT NULL')
            ->where('deleted_at IS NULL')
            ->orderBy('yr', 'DESC')
            ->get()->getResultArray();
        $availableTrendYears = array_values(array_filter(array_map(fn($r) => (int)$r['yr'], $yearsQuery)));
        if (empty($availableTrendYears)) {
            $availableTrendYears = [(int)date('Y'), (int)date('Y') - 1];
        }

        $trendYear = $this->request->getGet('trend_year') ? (int)$this->request->getGet('trend_year') : ($this->request->getGet('year') ? (int)$this->request->getGet('year') : (int)date('Y'));
        if (!in_array($trendYear, $availableTrendYears)) {
            $trendYear = $availableTrendYears[0] ?? (int)date('Y');
        }

        $stats               = $this->temuanRepository->getDashboardStats($ulpIdFilter, null);
        $monthlyData         = $this->temuanRepository->getMonthlyStats($ulpIdFilter, $trendYear);
        $ulpData             = $this->temuanRepository->getUlpStats($ulpIdFilter);
        $penyulangData       = $this->temuanRepository->getPenyulangStats($ulpIdFilter);
        $pelaksanaData       = $this->temuanRepository->getPelaksanaStats($ulpIdFilter);
        $prioritasData       = $this->temuanRepository->getPrioritasStats($ulpIdFilter);
        $potensiGangguanData = $this->temuanRepository->getPotensiGangguanStats($ulpIdFilter);
        $mapPins             = $this->temuanRepository->getMapPins($ulpIdFilter);

        // Distribution Asset Health Monitoring Calculations
        $feederQuery = $db->table('penyulang p')

            ->select("p.id, p.nama_penyulang, u.nama_ulp,
                      COUNT(t.id) as total_temuan,
                      SUM(CASE WHEN t.prioritas = 'EMERGENCY' AND t.status != 'SELESAI' THEN 1 ELSE 0 END) as emg,
                      SUM(CASE WHEN t.prioritas = 'HIGH' AND t.status != 'SELESAI' THEN 1 ELSE 0 END) as high,
                      SUM(CASE WHEN t.prioritas = 'MEDIUM' AND t.status != 'SELESAI' THEN 1 ELSE 0 END) as med,
                      SUM(CASE WHEN t.prioritas = 'LOW' AND t.status != 'SELESAI' THEN 1 ELSE 0 END) as low,
                      SUM(CASE WHEN t.status != 'SELESAI' THEN 1 ELSE 0 END) as belum_selesai")
            ->join('ulps u', 'p.ulp_id = u.id', 'left')
            ->join('temuan t', 't.penyulang_id = p.id AND t.deleted_at IS NULL', 'left');

        if ($ulpIdFilter !== null) {
            $feederQuery->where('p.ulp_id', $ulpIdFilter);
        }

        $allFeeders = $feederQuery->groupBy('p.id, p.nama_penyulang, u.nama_ulp')->get()->getResultArray();

        $sehatCount = 0;
        $kronisCount = 0;
        $sakitCount = 0;
        $kritisCount = 0;
        $totalHealthScore = 0;
        $rankedFeeders = [];
        $healthCalc = new \App\AI\AssetHealthCalculator();

        foreach ($allFeeders as &$f) {
            $f['id'] = (int)$f['id'];
            $f['total_temuan'] = (int)($f['total_temuan'] ?? 0);
            $f['emg'] = (int)($f['emg'] ?? 0);
            $f['high'] = (int)($f['high'] ?? 0);
            $f['med'] = (int)($f['med'] ?? 0);
            $f['low'] = (int)($f['low'] ?? 0);
            $f['belum_selesai'] = (int)($f['belum_selesai'] ?? 0);
            $f['selesai'] = max(0, $f['total_temuan'] - $f['belum_selesai']);

            $calc = $healthCalc->calculateHealthIndex($f['emg'], $f['high'], $f['med'], $f['low']);
            $score = $calc['score'];
            $f['health_score'] = $score;
            $totalHealthScore += $score;

            // Klasifikasi Status Penyulang:
            // Berdasarkan temuan Emergency aktif (belum selesai) sesuai kebutuhan operasional PLN:
            // - KRITIS: Emergency >= 3
            // - SAKIT: Emergency = 2
            // - KRONIS: Emergency = 1
            // - SEHAT: Emergency = 0
            if ($f['emg'] >= 3) {
                $f['status'] = 'KRITIS';
                $kritisCount++;
            } elseif ($f['emg'] == 2) {
                $f['status'] = 'SAKIT';
                $sakitCount++;
            } elseif ($f['emg'] == 1) {
                $f['status'] = 'KRONIS';
                $kronisCount++;
            } else {
                $f['status'] = 'SEHAT';
                $sehatCount++;
            }
            $rankedFeeders[] = $f;
        }
        unset($f);

        $totalFeeders = count($allFeeders);
        $avgHealthIndex = $totalFeeders > 0 ? (int)round($totalHealthScore / $totalFeeders) : 0;

        // Sort by total findings DESC for Top 10 (konsisten: akumulasi temuan fisik aktif)
        usort($rankedFeeders, fn($a, $b) => $b['total_temuan'] <=> $a['total_temuan']);
        $top10Feeders = array_slice($rankedFeeders, 0, 10);

        // Agregasi Pelaksana Kanonik dari tabel temuan untuk Top 10 Feeders
        $pelaksanaBreakdownRows = $db->table('temuan t')
            ->select("t.penyulang_id,
                      COALESCE(NULLIF(TRIM(t.pelaksana), ''), 'LAINNYA') as pelaksana_name,
                      SUM(CASE WHEN t.prioritas = 'EMERGENCY' AND t.status != 'SELESAI' THEN 1 ELSE 0 END) as emg,
                      SUM(CASE WHEN t.prioritas = 'HIGH' AND t.status != 'SELESAI' THEN 1 ELSE 0 END) as high,
                      SUM(CASE WHEN t.prioritas = 'MEDIUM' AND t.status != 'SELESAI' THEN 1 ELSE 0 END) as med,
                      SUM(CASE WHEN t.prioritas = 'LOW' AND t.status != 'SELESAI' THEN 1 ELSE 0 END) as low,
                      SUM(CASE WHEN t.status != 'SELESAI' THEN 1 ELSE 0 END) as belum_selesai,
                      COUNT(t.id) as total_temuan")
            ->where('t.deleted_at IS NULL')
            ->groupBy('t.penyulang_id, pelaksana_name')
            ->orderBy('belum_selesai', 'DESC')
            ->get()->getResultArray();

        $pelaksanaByFeeder = [];
        foreach ($pelaksanaBreakdownRows as $pRow) {
            $pid = (int)$pRow['penyulang_id'];
            if (!isset($pelaksanaByFeeder[$pid])) {
                $pelaksanaByFeeder[$pid] = [];
            }
            $pelaksanaByFeeder[$pid][] = [
                'pelaksana'     => $pRow['pelaksana_name'],
                'emg'           => (int)$pRow['emg'],
                'high'          => (int)$pRow['high'],
                'med'           => (int)$pRow['med'],
                'low'           => (int)$pRow['low'],
                'belum_selesai' => (int)$pRow['belum_selesai'],
                'total_temuan'  => (int)$pRow['total_temuan'],
            ];
        }

        foreach ($top10Feeders as &$f) {
            $f['pelaksana_breakdown'] = $pelaksanaByFeeder[$f['id']] ?? [];
        }
        unset($f);

        // Real YoY Interruption Stats (historical_feeder_interruptions)
        // Use latest year with actual data — never hardcode calendar year
        $maxYrRow = $db->table('historical_feeder_interruptions')
            ->selectMax('YEAR(event_date)', 'max_yr')
            ->where('event_date IS NOT NULL')
            ->get()->getRowArray();
        $curYear  = (!empty($maxYrRow['max_yr'])) ? (int)$maxYrRow['max_yr'] : (int)date('Y');
        $lastYear = $curYear - 1;
        // For comparing same period: use max month available in curYear
        $maxMonthRow = $db->table('historical_feeder_interruptions')
            ->selectMax('MONTH(event_date)', 'max_mo')
            ->where('YEAR(event_date)', $curYear)
            ->where('event_date IS NOT NULL')
            ->get()->getRowArray();
        $curMonth = (!empty($maxMonthRow['max_mo'])) ? (int)$maxMonthRow['max_mo'] : (int)date('n');


        $ggnCurQuery = $db->table('historical_feeder_interruptions')
            ->where('YEAR(event_date)', $curYear)
            ->where('MONTH(event_date) <=', $curMonth);
        $ggnLastQuery = $db->table('historical_feeder_interruptions')
            ->where('YEAR(event_date)', $lastYear)
            ->where('MONTH(event_date) <=', $curMonth);

        if ($ulpIdFilter !== null) {
            $interruptionFields = $db->getFieldNames('historical_feeder_interruptions');
            if (in_array('ulp_id', $interruptionFields, true)) {
                $ggnCurQuery->where('ulp_id', $ulpIdFilter);
                $ggnLastQuery->where('ulp_id', $ulpIdFilter);
            } else {
                $uRow = $db->table('ulps')->select('nama_ulp')->where('id', $ulpIdFilter)->get()->getRowArray();
                if ($uRow) {
                    $cleanU = trim(str_ireplace('ULP', '', (string)$uRow['nama_ulp']));
                    $ggnCurQuery->like('ulp_name', $cleanU);
                    $ggnLastQuery->like('ulp_name', $cleanU);
                }
            }
        }

        $cntGgnCur = $ggnCurQuery->countAllResults();
        $cntGgnLast = $ggnLastQuery->countAllResults();
        $yoyPct = 0;
        $yoyText = 'Stabil';
        $yoyClass = 'text-muted';

        if ($cntGgnLast > 0) {
            $yoyPct = round((($cntGgnCur - $cntGgnLast) / $cntGgnLast) * 100, 1);
            if ($yoyPct < 0) {
                $yoyText = "{$yoyPct}% YoY (Membaik)";
                $yoyClass = 'text-success';
            } elseif ($yoyPct > 0) {
                $yoyText = "+{$yoyPct}% YoY (Meningkat)";
                $yoyClass = 'text-danger';
            } else {
                $yoyText = '0% YoY (Sama)';
            }
        } else {
            $yoyText = 'Data pembanding n/a';
        }

        $gangguanStats = [
            'current_ytd' => $cntGgnCur,
            'last_ytd'    => $cntGgnLast,
            'yoy_pct'     => $yoyPct,
            'yoy_text'    => $yoyText,
            'yoy_class'   => $yoyClass,
            'year'        => $curYear,
            'last_year'   => $lastYear,
        ];

        // Real Monthly Trend YoY / Comparison Calculation
        $priorYearTotalQuery = $db->table('temuan')
            ->where('deleted_at IS NULL')
            ->where('YEAR(tanggal_temuan)', $trendYear - 1);
        if ($ulpIdFilter !== null) {
            $priorYearTotalQuery->where('ulp_id', $ulpIdFilter);
        }
        if ($trendYear === (int)date('Y')) {
            $priorYearTotalQuery->where('MONTH(tanggal_temuan) <=', (int)date('n'));
        }
        $cntPriorYear = $priorYearTotalQuery->countAllResults();

        $curYearTotalQuery = $db->table('temuan')
            ->where('deleted_at IS NULL')
            ->where('YEAR(tanggal_temuan)', $trendYear);
        if ($ulpIdFilter !== null) {
            $curYearTotalQuery->where('ulp_id', $ulpIdFilter);
        }
        if ($trendYear === (int)date('Y')) {
            $curYearTotalQuery->where('MONTH(tanggal_temuan) <=', (int)date('n'));
        }
        $cntCurYear = $curYearTotalQuery->countAllResults();

        $trendComparison = [
            'available'   => ($cntPriorYear > 0),
            'current'     => $cntCurYear,
            'prior'       => $cntPriorYear,
            'pct'         => 0,
            'text'        => 'Data pembanding tidak tersedia',
            'class'       => 'text-muted',
            'badge_class' => 'bg-light text-muted border',
            'year'        => $trendYear,
            'prior_year'  => $trendYear - 1
        ];

        if ($cntPriorYear > 0) {
            $pct = round((($cntCurYear - $cntPriorYear) / $cntPriorYear) * 100);
            $trendComparison['pct'] = $pct;
            if ($pct < 0) {
                $trendComparison['text'] = abs($pct) . '% Penurunan vs ' . ($trendYear - 1);
                $trendComparison['class'] = 'text-success';
                $trendComparison['badge_class'] = 'bg-success-subtle text-success border border-success-subtle';
            } elseif ($pct > 0) {
                $trendComparison['text'] = '+' . $pct . '% Kenaikan vs ' . ($trendYear - 1);
                $trendComparison['class'] = 'text-danger';
                $trendComparison['badge_class'] = 'bg-danger-subtle text-danger border border-danger-subtle';
            } else {
                $trendComparison['text'] = '0% (Sama dengan ' . ($trendYear - 1) . ')';
                $trendComparison['class'] = 'text-primary';
                $trendComparison['badge_class'] = 'bg-primary-subtle text-primary border border-primary-subtle';
            }
        }


        // Finding types breakdown
        $jenisQuery = $db->table('temuan')
            ->select('jenis_temuan, COUNT(*) as cnt')
            ->where('deleted_at IS NULL');
        if ($ulpIdFilter !== null) {
            $jenisQuery->where('ulp_id', $ulpIdFilter);
        }
        $jenisTemuanStats = $jenisQuery->groupBy('jenis_temuan')->orderBy('cnt', 'DESC')->limit(6)->get()->getResultArray();

        // Recent inspections / activities
        $recentActQuery = $db->table('temuan t')
            ->select('t.id, t.nomor_temuan, t.jenis_temuan, t.prioritas, t.status, t.pelaksana, t.created_at, t.detail_temuan, u.nama_ulp, p.nama_penyulang')
            ->join('ulps u', 't.ulp_id = u.id', 'left')
            ->join('penyulang p', 't.penyulang_id = p.id', 'left')
            ->where('t.deleted_at IS NULL')
            ->orderBy('t.id', 'DESC')
            ->limit(5);
        if ($ulpIdFilter !== null) {
            $recentActQuery->where('t.ulp_id', $ulpIdFilter);
        }
        $recentActivities = $recentActQuery->get()->getResultArray();

        // ULP List for Filter Dropdown
        $ulpsList = $db->table('ulps')->select('id, nama_ulp')->orderBy('nama_ulp', 'ASC')->get()->getResultArray();

        $assetHealthData = [
            'total_penyulang'  => $totalFeeders,
            'avg_health_index' => $avgHealthIndex,
            'sehat_count'      => $sehatCount,
            'sehat_pct'        => $totalFeeders > 0 ? round(($sehatCount / $totalFeeders) * 100) : 0,
            'kronis_count'     => $kronisCount,
            'kronis_pct'       => $totalFeeders > 0 ? round(($kronisCount / $totalFeeders) * 100) : 0,
            'sakit_count'      => $sakitCount,
            'sakit_pct'        => $totalFeeders > 0 ? round(($sakitCount / $totalFeeders) * 100) : 0,
            'kritis_count'     => $kritisCount,
            'kritis_pct'       => $totalFeeders > 0 ? round(($kritisCount / $totalFeeders) * 100) : 0,
            'top10'            => $top10Feeders,
            'all_feeders'      => $rankedFeeders,
            'status_rule'      => 'Berdasarkan temuan Emergency aktif (belum selesai): Sehat=0, Kronis=1, Sakit=2, Kritis=≥3',
            'jenis_temuan'     => $jenisTemuanStats,
            'recent'           => $recentActivities,
            'gangguan'         => $gangguanStats,
        ];

        // Ambil Data Work Order & Asset Stats (Phase 31.1 Mission Control)
        $woRepo = new \App\Repositories\WorkOrderRepository();
        $assetRepo = new \App\Repositories\AssetRepository();
        $woStats = $woRepo->getWOStats($ulpIdFilter);
        $assetStats = $assetRepo->getAssetStats($ulpIdFilter);

        // Phase 2: Penyulang Emergency Ranking
        $penyulangRanking = $this->temuanRepository->getPenyulangEmergencyRanking($ulpIdFilter, 5);

        // Distribution network summary from master feeder/GI relations.
        $giMapRows = $db->tableExists('gardu_induk')
            ? $db->query("SELECT UPPER(TRIM(p.nama_penyulang)) AS fdr, MAX(gi.nama_gi) AS gi_name FROM penyulang p LEFT JOIN gardu_induk gi ON gi.id = p.gi_id GROUP BY UPPER(TRIM(p.nama_penyulang))")->getResultArray()
            : [];
        $giByFeeder = array_column($giMapRows, 'gi_name', 'fdr');

        $translineRows = $db->query("
            SELECT penyulang_id,
                SUM(CASE WHEN UPPER(conductor_type) LIKE '%AAACS%' THEN distance_meters ELSE 0 END)/1000 as aaacs_kms,
                SUM(CASE WHEN UPPER(conductor_type) LIKE '%AAAC%' AND UPPER(conductor_type) NOT LIKE '%AAACS%' THEN distance_meters ELSE 0 END)/1000 as aaac_kms,
                SUM(CASE WHEN UPPER(conductor_type) LIKE '%MVTIC%' THEN distance_meters ELSE 0 END)/1000 as mvtic_kms,
                SUM(CASE WHEN UPPER(conductor_type) LIKE '%XLPE%' THEN distance_meters ELSE 0 END)/1000 as xlpe_kms,
                SUM(CASE WHEN conductor_type IS NULL OR (UPPER(conductor_type) NOT LIKE '%AAAC%' AND UPPER(conductor_type) NOT LIKE '%MVTIC%' AND UPPER(conductor_type) NOT LIKE '%XLPE%') THEN distance_meters ELSE 0 END)/1000 as other_kms,
                COUNT(id) as spans
            FROM gis_translines
            WHERE (status = 'ACTIVE' OR is_active = 1) AND deleted_at IS NULL AND penyulang_id IS NOT NULL
            GROUP BY penyulang_id
        ")->getResultArray();
        $translinesByPenyulang = array_column($translineRows, null, 'penyulang_id');

        $candidateCounts = [];
        if ($db->tableExists('captive_non_penugasan')) {
            $candidateRows = $db->table('captive_non_penugasan')->select('penyulang_id, COUNT(*) AS cnt')->where('jenis', 'COATING')->where('status', 'POTENSI')->where('deleted_at IS NULL')->groupBy('penyulang_id')->get()->getResultArray();
            $candidateCounts = array_column($candidateRows, 'cnt', 'penyulang_id');
        }

        $assetCountRows = $db->query("
            SELECT penyulang_id, COUNT(*) as cnt FROM assets WHERE deleted_at IS NULL AND penyulang_id IS NOT NULL GROUP BY penyulang_id
        ")->getResultArray();
        $assetsByPenyulang = array_column($assetCountRows, 'cnt', 'penyulang_id');

        // Build per-feeder and per-ULP aggregations
        $penyulangQuery = $db->table('penyulang p')
            ->select('p.id, p.kode_penyulang, p.nama_penyulang, p.ulp_id, u.nama_ulp')
            ->join('ulps u', 'p.ulp_id = u.id', 'left')
            ->where('p.status', 'AKTIF');
        if ($ulpIdFilter !== null) {
            $penyulangQuery->where('p.ulp_id', $ulpIdFilter);
        }
        $allPenyulang = $penyulangQuery->orderBy('u.nama_ulp')->orderBy('p.nama_penyulang')->get()->getResultArray();

        $networkFeeders = [];
        $ulpAccum = []; // keyed by ulp_id
        foreach ($allPenyulang as $p) {
            $pid  = $p['id'];
            $uid  = $p['ulp_id'];
            $fKey = strtoupper(trim($p['nama_penyulang']));
            $tl   = $translinesByPenyulang[$pid] ?? null;
            $row  = [
                'id'            => $pid,
                'kode'          => $p['kode_penyulang'],
                'nama'          => $p['nama_penyulang'],
                'ulp_id'        => $uid,
                'nama_ulp'      => $p['nama_ulp'],
                'gardu_induk'   => $giByFeeder[$fKey] ?? '-',
                'aset'          => (int)($assetsByPenyulang[$pid] ?? 0),
                'aaac_kms'      => $tl ? round((float)$tl['aaac_kms'], 2) : 0,
                'aaacs_kms'     => $tl ? round((float)$tl['aaacs_kms'], 2) : 0,
                'mvtic_kms'     => $tl ? round((float)$tl['mvtic_kms'], 2) : 0,
                'xlpe_kms'      => $tl ? round((float)$tl['xlpe_kms'], 2) : 0,
                'other_kms'     => $tl ? round((float)$tl['other_kms'], 2) : 0,
                'spans'         => $tl ? (int)$tl['spans'] : 0,
                'captive_candidates' => (int)($candidateCounts[$pid] ?? 0),
            ];
            $networkFeeders[] = $row;
            if (!isset($ulpAccum[$uid])) {
                $ulpAccum[$uid] = ['ulp_id' => (int)$uid, 'nama_ulp' => $p['nama_ulp'], 'penyulang' => 0, 'aset' => 0, 'aaac_kms' => 0, 'aaacs_kms' => 0, 'mvtic_kms' => 0, 'xlpe_kms' => 0, 'other_kms' => 0, 'gis' => []];
            }
            $ulpAccum[$uid]['penyulang']++;
            $ulpAccum[$uid]['aset']     += $row['aset'];
            $ulpAccum[$uid]['aaac_kms'] += $row['aaac_kms'];
            $ulpAccum[$uid]['aaacs_kms']+= $row['aaacs_kms'];
            $ulpAccum[$uid]['mvtic_kms']+= $row['mvtic_kms'];
            $ulpAccum[$uid]['xlpe_kms'] += $row['xlpe_kms'];
            $ulpAccum[$uid]['other_kms']+= $row['other_kms'];
            if ($row['gardu_induk'] !== '-' && !in_array($row['gardu_induk'], $ulpAccum[$uid]['gis'])) {
                $ulpAccum[$uid]['gis'][] = $row['gardu_induk'];
            }
        }
        // Round ULP totals
        foreach ($ulpAccum as &$u) {
            $u['aaac_kms']  = round($u['aaac_kms'], 2);
            $u['aaacs_kms'] = round($u['aaacs_kms'], 2);
            $u['mvtic_kms'] = round($u['mvtic_kms'], 2);
            $u['xlpe_kms'] = round($u['xlpe_kms'], 2);
            $u['other_kms'] = round($u['other_kms'], 2);
        }
        unset($u);
        $networkData = ['ulp_summary' => array_values($ulpAccum), 'feeders' => $networkFeeders, 'ulp_accum' => $ulpAccum];

        // Asset summary: total per jenis_asset for the dashboard header panel
        $assetSummaryRows = $db->query(
            "SELECT jenis_asset, COUNT(*) cnt FROM assets WHERE deleted_at IS NULL"
            . ($ulpIdFilter !== null ? " AND ulp_id = " . (int)$ulpIdFilter : "")
            . " GROUP BY jenis_asset"
        )->getResultArray();
        $assetByType  = array_column($assetSummaryRows, 'cnt', 'jenis_asset');
        $assetTotalAll = array_sum(array_column($assetSummaryRows, 'cnt'));
        $assetSummary = [
            'total'    => $assetTotalAll,
            'jtm'      => (int)($assetByType['JTM'] ?? 0),
            'keypoint' => (int)($assetByType['RECLOSER_LBS'] ?? 0),
            'gardu'    => (int)(($assetByType['GARDU_DISTRIBUSI_PORTAL'] ?? 0) + ($assetByType['GTT_GARDU_TRAFO_TIANG'] ?? 0)),
            'trafo'    => (int)($assetByType['GTT_GARDU_TRAFO_TIANG'] ?? 0),
            'pohon'    => (int)($db->tableExists('vegetation_observations') ? $db->table('vegetation_observations')->where('is_valid', 1)->countAllResults() : 0),
            'by_type'  => $assetByType,
        ];

        // Cumulative gangguan per month for trend year
        $ggnMonthlyRaw = $db->query(
            "SELECT MONTH(event_date) mo, COUNT(*) cnt FROM historical_feeder_interruptions
             WHERE event_date IS NOT NULL AND YEAR(event_date) = " . (int)$trendYear . "
             GROUP BY mo ORDER BY mo ASC"
        )->getResultArray();
        $monthlyGangguan = array_fill(1, 12, 0);
        foreach ($ggnMonthlyRaw as $row) { $monthlyGangguan[(int)$row['mo']] = (int)$row['cnt']; }
        $cumulativeGangguan = [];
        $cumSum = 0;
        for ($m = 1; $m <= 12; $m++) { $cumSum += $monthlyGangguan[$m]; $cumulativeGangguan[$m] = $cumSum; }

        // Top 10 feeders by interruption count for trend year
        $topFeedersGangguanRows = $db->query(
            "SELECT h.feeder_name, p.id penyulang_id, p.nama_penyulang, u.nama_ulp, COUNT(h.id) cnt
             FROM historical_feeder_interruptions h
             LEFT JOIN penyulang p ON UPPER(TRIM(p.nama_penyulang)) = UPPER(TRIM(h.feeder_name))
             LEFT JOIN ulps u ON p.ulp_id = u.id
             WHERE h.event_date IS NOT NULL AND YEAR(h.event_date) = " . (int)$trendYear
            . ($ulpIdFilter !== null ? " AND (u.id = " . (int)$ulpIdFilter . " OR u.id IS NULL)" : "")
            . " GROUP BY h.feeder_name, p.id, p.nama_penyulang, u.nama_ulp ORDER BY cnt DESC LIMIT 10"
        )->getResultArray();

        // Top 10 feeders by active inspection findings with pelaksana breakdown
        $temuanByPenyulangRaw = $db->query(
            "SELECT t.penyulang_id, p.nama_penyulang,
                    COUNT(*) total,
                    SUM(CASE WHEN UPPER(t.pelaksana) LIKE '%PDKB%' THEN 1 ELSE 0 END) pdkb,
                    SUM(CASE WHEN UPPER(t.pelaksana) LIKE '%HAR%GARDU%' OR t.pelaksana = 'HAR_GARDU' THEN 1 ELSE 0 END) har_gardu,
                    SUM(CASE WHEN UPPER(t.pelaksana) LIKE '%HAR%ROW%' OR t.pelaksana = 'HAR_ROW' THEN 1 ELSE 0 END) har_row
             FROM temuan t
             LEFT JOIN penyulang p ON t.penyulang_id = p.id
             WHERE t.deleted_at IS NULL AND t.status != 'SELESAI'"
            . ($ulpIdFilter !== null ? " AND t.ulp_id = " . (int)$ulpIdFilter : "")
            . " GROUP BY t.penyulang_id, p.nama_penyulang ORDER BY total DESC LIMIT 10"
        )->getResultArray();
        $topInspeksiPenyulang = array_map(fn($r) => [
            'penyulang_id'  => (int)$r['penyulang_id'],
            'nama_penyulang'=> $r['nama_penyulang'],
            'total'         => (int)$r['total'],
            'pdkb'          => (int)$r['pdkb'],
            'har_gardu'     => (int)$r['har_gardu'],
            'har_row'       => (int)$r['har_row'],
        ], $temuanByPenyulangRaw);

        // Captive coating summary
        $coatingSummary = ['total_titik' => 0, 'total_meter' => 0.0];
        if ($db->tableExists('captive_non_penugasan')) {
            $coatingQ = $db->table('captive_non_penugasan')
                ->select('COUNT(*) total_titik, SUM(distance_meters) total_meter')
                ->where('jenis', 'COATING')->where('status', 'POTENSI')->where('deleted_at IS NULL');
            if ($ulpIdFilter !== null) $coatingQ->where('ulp_id', $ulpIdFilter);
            $cr = $coatingQ->get()->getRowArray();
            $coatingSummary = ['total_titik' => (int)($cr['total_titik'] ?? 0), 'total_meter' => (float)($cr['total_meter'] ?? 0)];
        }
        // Sidebar collapse preference: default TRUE (collapsed) unless user explicitly expanded
        $prefersCollapsed = $session->get('sidebar_collapsed') ?? true;

        $viewParams = [
            'userName'            => $session->get('user_name') ?: 'Petugas',
            'userRole'            => $role,
            'canInput'            => $canInput,
            'canEdit'             => $canEdit,
            'canDelete'           => $canDelete,
            'canApprove'          => $canApprove,
            'canMonitoring'       => $canMonitoring,
            'stats'               => $stats,
            'assetHealthData'     => $assetHealthData,
            'gangguanStats'       => $gangguanStats,
            'ulpsList'            => $ulpsList,
            'selectedUlpId'       => $ulpIdFilter,
            'woStats'             => $woStats,
            'assetStats'          => $assetStats,
            'monthlyData'         => $monthlyData,
            'ulpData'             => $ulpData,
            'penyulangData'       => $penyulangData,
            'penyulangRanking'    => $penyulangRanking,
            'pelaksanaData'       => $pelaksanaData,
            'prioritasData'       => $prioritasData,
            'potensiGangguanData' => $potensiGangguanData,
            'mapPins'             => $mapPins,
            'topInputOfficers'    => $topInputOfficers,
            'topUpdateOfficers'   => $topUpdateOfficers,
            'monthFilter'         => $monthFilter,
            'yearFilter'          => $yearFilter,
            'availableTrendYears' => $availableTrendYears,
            'trendYear'           => $trendYear,
            'trendComparison'     => $trendComparison,
            'prefersCollapsed'    => $prefersCollapsed,
            'networkData'         => $networkData,
            'assetSummary'          => $assetSummary,
            'cumulativeGangguan'    => $cumulativeGangguan,
            'topFeedersGangguan'    => $topFeedersGangguanRows,
            'topInspeksiPenyulang'  => $topInspeksiPenyulang,
            'coatingSummary'        => $coatingSummary,
        ];


        if ($showMobile) {
            return view('dashboard/mobile', $viewParams);
        }

        return view('dashboard/index', $viewParams);
    }

    /**
     * AJAX endpoint to save sidebar collapse preference to user session
     */
    public function sidebarPreference()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid request']);
        }

        $collapsed = (bool)$this->request->getPost('collapsed');
        $session = session();
        $session->set('sidebar_collapsed', $collapsed);

        return $this->response->setStatusCode(200)->setJSON(['success' => true, 'collapsed' => $collapsed]);
    }

    public function toggleView()
    {
        $session = session();
        $agent = $this->request->getUserAgent();
        $isMobile = $agent->isMobile();
        $currentMode = $session->get('view_mode') ?: ($_COOKIE['view_mode'] ?? null);

        if ($currentMode === 'desktop') {
            $newMode = 'mobile';
        } elseif ($currentMode === 'mobile') {
            $newMode = 'desktop';
        } else {
            $newMode = $isMobile ? 'desktop' : 'mobile';
        }

        $session->set('view_mode', $newMode);
        setcookie('view_mode', $newMode, time() + (86400 * 30), '/');

        return redirect()->to(site_url('dashboard'));
    }

    /**
     * Real-time AJAX endpoint for role-scoped dashboard charts & analytics
     */
    public function analyticsData()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $ulpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($ulpId)) {
            $ulpIdFilter = (int)$ulpId;
        }

        $analytics = $this->temuanRepository->getComprehensiveAnalytics($role, $ulpIdFilter);

        return $this->response->setStatusCode(200)->setJSON([
            'success'   => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'data'      => $analytics
        ]);
    }

    /**
     * Phase 14 Executive Dashboard View
     */
    public function executive()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $ulpId = $session->get('user_ulp_id');

        $ulpModel = new \App\Models\UlpModel();
        $penyulangModel = new \App\Models\PenyulangModel();

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($ulpId)) {
            $ulpIdFilter = (int)$ulpId;
        }

        $initialData = $this->temuanRepository->getExecutiveAnalyticsData([], $role, $ulpIdFilter);

        return view('dashboard/executive', [
            'initialData' => $initialData,
            'ulps'        => $ulpModel->where('status', 'AKTIF')->findAll(),
            'penyulangs'  => $penyulangModel->where('status', 'AKTIF')->findAll(),
            'userRole'    => $role,
            'userName'    => session()->get('user_name') ?: 'User'
        ]);
    }

    /**
     * Real-time AJAX endpoint for Executive Dashboard filters & 30s auto-refresh
     */
    public function executiveApi()
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $ulpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        if (!in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($ulpId)) {
            $ulpIdFilter = (int)$ulpId;
        }

        $filters = [
            'ulp_id'         => $this->request->getGet('ulp_id'),
            'penyulang_id'   => $this->request->getGet('penyulang_id'),
            'section_id'     => $this->request->getGet('section_id'),
            'jenis_temuan'   => $this->request->getGet('jenis_temuan'),
            'pelaksana'      => $this->request->getGet('pelaksana'),
            'prioritas'      => $this->request->getGet('prioritas'),
            'status'         => $this->request->getGet('status'),
            'tanggal_mulai'  => $this->request->getGet('tanggal_mulai'),
            'tanggal_selesai'=> $this->request->getGet('tanggal_selesai'),
        ];

        $analytics = $this->temuanRepository->getExecutiveAnalyticsData($filters, $role, $ulpIdFilter);

        return $this->response->setStatusCode(200)->setJSON([
            'success'   => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'data'      => $analytics
        ]);
    }
}
