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

        // Available years in temuan & historical_feeder_interruptions database
        $yearsQuery = $db->table('temuan')
            ->select('DISTINCT YEAR(tanggal_temuan) as yr')
            ->where('tanggal_temuan IS NOT NULL')
            ->where('deleted_at IS NULL')
            ->get()->getResultArray();
        $yearsGgnQuery = $db->tableExists('historical_feeder_interruptions')
            ? $db->table('historical_feeder_interruptions')
                ->select('DISTINCT YEAR(event_date) as yr')
                ->where('event_date IS NOT NULL')
                ->get()->getResultArray()
            : [];
        $allYears = array_merge(
            array_map(fn($r) => (int)$r['yr'], $yearsQuery),
            array_map(fn($r) => (int)$r['yr'], $yearsGgnQuery)
        );
        $availableTrendYears = array_values(array_unique(array_filter($allYears)));
        rsort($availableTrendYears);
        if (empty($availableTrendYears)) {
            $availableTrendYears = [(int)date('Y'), (int)date('Y') - 1];
        }

        $trendYear = $this->request->getGet('trend_year') ? (int)$this->request->getGet('trend_year') : ($this->request->getGet('year') ? (int)$this->request->getGet('year') : ($availableTrendYears[0] ?? (int)date('Y')));
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

        // Real YoY Interruption Stats via InterruptionModel (identical source & filter contract as /gangguan)
        $interruptionModel = new \App\Models\InterruptionModel();
        $availableInterruptionYears = $interruptionModel->getAvailableYears();
        $curYear  = !empty($availableInterruptionYears) ? (int)$availableInterruptionYears[0] : (int)date('Y');
        $lastYear = isset($availableInterruptionYears[1]) ? (int)$availableInterruptionYears[1] : ($curYear - 1);

        $compPeriod = $interruptionModel->getComparablePeriod($curYear, $lastYear, $ulpIdFilter, null);
        $compMonths = (int)($compPeriod['comparable_months'] ?? 12);
        $cntGgnCur  = $interruptionModel->getTotalByYearUpToMonth($curYear, $compMonths, $ulpIdFilter, null);
        $cntGgnLast = $interruptionModel->getTotalByYearUpToMonth($lastYear, $compMonths, $ulpIdFilter, null);
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
            'current_ytd'       => $cntGgnCur,
            'last_ytd'          => $cntGgnLast,
            'yoy_pct'           => $yoyPct,
            'yoy_text'          => $yoyText,
            'yoy_class'         => $yoyClass,
            'year'              => $curYear,
            'last_year'         => $lastYear,
            'comparable_period' => $compPeriod['comparable_period'] ?? 'YTD',
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

        // Augment GI mapping from historical interruption substations for unmapped or NULL gi_id feeders
        if ($db->tableExists('historical_feeder_interruptions') && $db->fieldExists('substation_name', 'historical_feeder_interruptions')) {
            $hGiRows = $db->query("SELECT UPPER(TRIM(feeder_name)) AS fdr, MAX(substation_name) AS gi_sub FROM historical_feeder_interruptions WHERE substation_name IS NOT NULL AND substation_name != '' GROUP BY UPPER(TRIM(feeder_name))")->getResultArray();
            foreach ($hGiRows as $hr) {
                $f = strtoupper(trim((string)$hr['fdr']));
                $g = trim((string)$hr['gi_sub']);
                if (!empty($g) && (!isset($giByFeeder[$f]) || empty($giByFeeder[$f]) || $giByFeeder[$f] === '-')) {
                    if (!str_starts_with(strtoupper($g), 'GI ') && !str_starts_with(strtoupper($g), 'GARDU INDUK ')) {
                        $g = 'GI ' . $g;
                    }
                    $giByFeeder[$f] = $g;
                }
            }
        }

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
        $hasGi = $db->tableExists('gardu_induk');
        $penyulangQuery = $db->table('penyulang p')
            ->select('p.id, p.kode_penyulang, p.nama_penyulang, p.ulp_id, u.nama_ulp' . ($hasGi ? ', gi.nama_gi as gardu_induk' : ''))
            ->join('ulps u', 'p.ulp_id = u.id', 'left');
        if ($hasGi) {
            $penyulangQuery->join('gardu_induk gi', 'gi.id = p.gi_id', 'left');
        }
        $penyulangQuery->where("(p.status IS NULL OR UPPER(p.status) IN ('AKTIF', 'ACTIVE'))", null, false);
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
            $giResolved = !empty($p['gardu_induk']) ? $p['gardu_induk'] : ($giByFeeder[$fKey] ?? '-');
            if ($giResolved !== '-' && !empty($giResolved) && !str_starts_with(strtoupper($giResolved), 'GI ') && !str_starts_with(strtoupper($giResolved), 'GARDU INDUK ')) {
                $giResolved = 'GI ' . $giResolved;
            }
            $row  = [
                'id'            => $pid,
                'kode'          => $p['kode_penyulang'],
                'nama'          => $p['nama_penyulang'],
                'ulp_id'        => $uid,
                'nama_ulp'      => $p['nama_ulp'],
                'gardu_induk'   => $giResolved,
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
        // Calculate unassigned assets count (assets awaiting feeder mapping)
        $unassignedBuilder = $db->table('assets')
            ->where('deleted_at IS NULL')
            ->groupStart()
                ->where('penyulang_id IS NULL')
                ->orWhere('penyulang_id', 0)
            ->groupEnd();
        if ($ulpIdFilter !== null) {
            $unassignedBuilder->where('ulp_id', $ulpIdFilter);
        }
        $unassignedAssetCount = (int)$unassignedBuilder->countAllResults();

        $networkData = [
            'ulp_summary'       => array_values($ulpAccum),
            'feeders'           => $networkFeeders,
            'ulp_accum'         => $ulpAccum,
            'unassigned_assets' => $unassignedAssetCount,
        ];

        // Asset summary: total per jenis_asset for the dashboard header panel
        $assetSummaryRows = $db->query(
            "SELECT jenis_asset, COUNT(*) cnt FROM assets WHERE deleted_at IS NULL"
            . ($ulpIdFilter !== null ? " AND ulp_id = " . (int)$ulpIdFilter : "")
            . " GROUP BY jenis_asset"
        )->getResultArray();
        $assetByType  = array_column($assetSummaryRows, 'cnt', 'jenis_asset');
        $assetTotalAll = array_sum(array_column($assetSummaryRows, 'cnt'));
        $jtmCount      = (int)($assetByType['jtm_tiang'] ?? $assetByType['TIANG_BETON'] ?? 0);
        $keypointCount = 0;
        $garduCount    = 0;
        $trafoCount    = 0;
        $pohonCount    = 0;

        foreach ($assetSummaryRows as $asr) {
            $rawType = strtoupper(trim((string)($asr['jenis_asset'] ?? '')));
            $c = (int)($asr['cnt'] ?? 0);

            if ($jtmCount === 0 && preg_match('/(TIANG|POLE)/i', $rawType)) {
                $jtmCount += $c;
            }
            if (preg_match('/(KEYPOINT|RECLOSER|LBS|SWITCH|DISCONNECT|SECTIONALIZER|FCO)/i', $rawType)) {
                $keypointCount += $c;
            }
            if (preg_match('/(GARDU|GTT|SUBSTATION|PORTAL|CANTOL|KIOS)/i', $rawType)) {
                $garduCount += $c;
            }
            if (preg_match('/(TRAFO|TRANSFORMATOR|GTT)/i', $rawType)) {
                $trafoCount += $c;
            }
            if (preg_match('/(POHON|TREE|VEGETASI|ROW)/i', $rawType)) {
                $pohonCount += $c;
            }
        }

        if ($jtmCount === 0) {
            $jtmCount = (int)($assetByType['jtm_tiang'] ?? $assetByType['JTM'] ?? 0);
        }
        if ($keypointCount === 0) {
            $keypointCount = (int)($assetByType['RECLOSER_LBS'] ?? $assetByType['KEYPOINT'] ?? 0);
        }
        if ($garduCount === 0) {
            $garduCount = (int)(($assetByType['GARDU_DISTRIBUSI_PORTAL'] ?? 0) + ($assetByType['GTT_GARDU_TRAFO_TIANG'] ?? 0) + ($assetByType['GARDU'] ?? 0));
        }
        if ($trafoCount === 0) {
            $trafoCount = (int)($assetByType['GTT_GARDU_TRAFO_TIANG'] ?? $assetByType['TRAFO'] ?? 0);
        }
        if ($pohonCount === 0 && $db->tableExists('vegetation_observations')) {
            $vegBuilder = $db->table('vegetation_observations');
            if ($db->fieldExists('is_valid', 'vegetation_observations')) {
                $vegBuilder->where('is_valid', 1);
            }
            if ($db->fieldExists('deleted_at', 'vegetation_observations')) {
                $vegBuilder->where('deleted_at IS NULL');
            }
            if ($ulpIdFilter !== null) {
                if ($db->fieldExists('ulp_id', 'vegetation_observations')) {
                    $vegBuilder->where('ulp_id', (int)$ulpIdFilter);
                } elseif ($db->fieldExists('asset_id', 'vegetation_observations') && $db->tableExists('assets')) {
                    $vegBuilder->join('assets', 'assets.id = vegetation_observations.asset_id')
                               ->where('assets.ulp_id', (int)$ulpIdFilter);
                }
            }
            $pohonCount = (int)$vegBuilder->countAllResults();
        }

        $assetSummary = [
            'total'    => $assetTotalAll,
            'jtm'      => $jtmCount,
            'keypoint' => $keypointCount,
            'gardu'    => $garduCount,
            'trafo'    => $trafoCount,
            'pohon'    => $pohonCount,
            'by_type'  => $assetByType,
        ];

        // Cumulative gangguan per month (YoY multi-year series + selected year, using InterruptionModel for 100% parity with /gangguan)
        $ulpWhereClause = "";
        if ($ulpIdFilter !== null) {
            $interruptionFields = $db->getFieldNames('historical_feeder_interruptions');
            if (in_array('ulp_id', $interruptionFields, true)) {
                $ulpWhereClause = " AND h.ulp_id = " . (int)$ulpIdFilter;
            } else {
                $uRow = $db->table('ulps')->select('nama_ulp')->where('id', $ulpIdFilter)->get()->getRowArray();
                $cleanU = $uRow ? $db->escapeLikeString(trim(str_ireplace('ULP', '', (string)$uRow['nama_ulp']))) : '';
                if ($cleanU !== '' && in_array('ulp_name', $interruptionFields, true)) {
                    $ulpWhereClause = " AND h.ulp_name LIKE '%" . $cleanU . "%'";
                } else {
                    $ulpWhereClause = " AND p.ulp_id = " . (int)$ulpIdFilter;
                }
            }
        }

        $monthlyByYear = [];
        foreach ($availableInterruptionYears as $yr) {
            $monthlyByYear[(int)$yr] = $interruptionModel->getMonthlyByYear((int)$yr, $ulpIdFilter, null);
        }
        krsort($monthlyByYear);

        $cumulativeGangguanYoY = [];
        foreach ($monthlyByYear as $yr => $monthsArr) {
            $runSum = 0;
            $cumArr = [];
            for ($m = 1; $m <= 12; $m++) {
                $runSum += (int)($monthsArr[$m] ?? 0);
                $cumArr[$m] = $runSum;
            }
            // Strict YoY parity: Only include years that have actual recorded interruption data or current year
            if ($runSum > 0 || (int)$yr === (int)date('Y')) {
                $cumulativeGangguanYoY[(int)$yr] = [
                    'year'       => (int)$yr,
                    'total'      => (int)$runSum,
                    'has_source' => in_array((int)$yr, $availableInterruptionYears, true),
                    'monthly'    => array_values($monthsArr),
                    'cumulative' => array_values($cumArr),
                ];
            }
        }
        $cumulativeGangguan = $cumulativeGangguanYoY[$trendYear]['cumulative'] ?? array_fill(0, 12, 0);

        // Cause Breakdown ("Penyebab Sesuai Kode Gangguan") per year from official dictionary & canonical taxonomy
        $canonicalCauseMap = [
            'CAUSE_ANIMAL_CONTACT'              => ['category' => 'ANIMAL_CONTACT',          'label' => 'Sentuhan Binatang (Tikus/Burung/Bunglon/Ular)', 'badge' => 'warning'],
            'CAUSE_VEGETATION_ROW'              => ['category' => 'VEGETATION_ROW',          'label' => 'Pohon / Ranting / Vegetasi (ROW)',              'badge' => 'success'],
            'CAUSE_EQUIPMENT_FAILURE'           => ['category' => 'EQUIPMENT_FAILURE',       'label' => 'Kegagalan Komponen / Material Distribusi',      'badge' => 'danger'],
            'CAUSE_THIRD_PARTY_OBJECT'          => ['category' => 'THIRD_PARTY_OBJECT',      'label' => 'Benda Asing / Aktivitas Pihak Ketiga (Layangan/Bangunan)', 'badge' => 'info'],
            'CAUSE_LIGHTNING_SURGE'             => ['category' => 'LIGHTNING_WEATHER',       'label' => 'Sambaran Petir / Force Majeure Cuaca',          'badge' => 'primary'],
            'CAUSE_CUSTOMER_IML_FAULT'          => ['category' => 'CUSTOMER_IML_FAULT',      'label' => 'Instalasi Milik Langganan (IML) / Overload',    'badge' => 'secondary'],
            'CAUSE_CONDUCTOR_GSW_SNAP'          => ['category' => 'CONDUCTOR_GSW_SNAP',      'label' => 'Konduktor SUTM / Jumper / GSW Putus atau Lepas','badge' => 'danger'],
            'CAUSE_CABLE_TERMINATION_FAULT'     => ['category' => 'CABLE_TERMINATION_FAULT', 'label' => 'Kegagalan Isolasi Kabel / Terminasi / Jointing','badge' => 'purple'],
            'CAUSE_UNKNOWN_UNDER_INVESTIGATION' => ['category' => 'UNKNOWN_INVESTIGATION',   'label' => 'Belum Diketahui / Nihil Temuan Patroli',        'badge' => 'secondary'],
            'CAUSE_UNMAPPED_ADVISORY'           => ['category' => 'UNMAPPED',                'label' => 'Belum Terpetakan (Unmapped Kode Sumber)',       'badge' => 'dark'],
        ];
        if ($db->tableExists('cause_code_dictionary')) {
            $dictRows = $db->query("SELECT canonical_cause_code, MAX(cause_category) AS cat, MIN(cause_label) AS lbl FROM cause_code_dictionary WHERE is_active = 1 GROUP BY canonical_cause_code")->getResultArray();
            foreach ($dictRows as $dr) {
                $cCode = (string)$dr['canonical_cause_code'];
                if (!isset($canonicalCauseMap[$cCode]) && $cCode !== '') {
                    $canonicalCauseMap[$cCode] = [
                        'category' => (string)$dr['cat'],
                        'label'    => (string)$dr['lbl'],
                        'badge'    => 'info',
                    ];
                }
            }
        }

        $causeRawRows = $db->tableExists('historical_feeder_interruptions')
            ? $db->query(
                "SELECT YEAR(h.event_date) yr,
                        COALESCE(NULLIF(TRIM(h.cause_canonical_code), ''), 'CAUSE_UNKNOWN_UNDER_INVESTIGATION') AS canonical_code,
                        COALESCE(NULLIF(TRIM(h.cause_category), ''), 'UNKNOWN_INVESTIGATION') AS cause_category,
                        COALESCE(NULLIF(TRIM(h.cause_mapping_status), ''), 'RESOLVED') AS mapping_status,
                        GROUP_CONCAT(DISTINCT NULLIF(TRIM(h.cause_raw), '') ORDER BY h.cause_raw SEPARATOR ', ') AS raw_samples,
                        COUNT(h.id) AS cnt
                 FROM historical_feeder_interruptions h
                 LEFT JOIN penyulang p ON UPPER(TRIM(p.nama_penyulang)) = UPPER(TRIM(h.feeder_name))
                 WHERE h.event_date IS NOT NULL" . $ulpWhereClause . "
                 GROUP BY YEAR(h.event_date), canonical_code, cause_category, mapping_status
                 ORDER BY yr DESC, cnt DESC"
            )->getResultArray()
            : [];

        $causeBreakdownByYear = [];
        foreach ($availableTrendYears as $yr) {
            $causeBreakdownByYear[(int)$yr] = [];
        }
        foreach ($causeRawRows as $cRow) {
            $yr = (int)$cRow['yr'];
            $code = (string)$cRow['canonical_code'];
            $status = (string)$cRow['mapping_status'];
            $cnt = (int)$cRow['cnt'];
            $meta = $canonicalCauseMap[$code] ?? [
                'category' => (string)$cRow['cause_category'],
                'label'    => 'Kode Belum Terpetakan: ' . $code,
                'badge'    => 'dark',
            ];
            $key = $code . '|' . $status;
            if (!isset($causeBreakdownByYear[$yr][$key])) {
                $causeBreakdownByYear[$yr][$key] = [
                    'canonical_code' => $code,
                    'category'       => $meta['category'],
                    'label'          => $meta['label'],
                    'badge'          => $meta['badge'],
                    'mapping_status' => $status,
                    'raw_samples'    => (string)($cRow['raw_samples'] ?? ''),
                    'count'          => 0,
                ];
            }
            $causeBreakdownByYear[$yr][$key]['count'] += $cnt;
        }
        foreach ($causeBreakdownByYear as $yr => $items) {
            $list = array_values($items);
            usort($list, fn($a, $b) => $b['count'] <=> $a['count']);
            $totYr = (int)array_sum(array_column($list, 'count'));
            foreach ($list as &$it) {
                $it['count'] = (int)$it['count'];
                $it['total'] = (int)$it['count'];
                $it['pct']   = $totYr > 0 ? round(($it['count'] / $totYr) * 100, 1) : 0.0;
            }
            unset($it);
            $hasSourceForYear = in_array((int)$yr, $availableInterruptionYears, true);
            $causeBreakdownByYear[(int)$yr] = [
                'year'         => (int)$yr,
                'total_events' => $totYr,
                'has_source'   => $hasSourceForYear,
                'source_note'  => $hasSourceForYear
                    ? null
                    : 'Tahun ' . (int)$yr . ' tercatat pada data Temuan Inspeksi, namun belum ada impor histori trip di tabel Gangguan Penyulang.',
                'items'        => $list,
            ];
        }

        // Top 10 feeders by interruption count for trend year (via InterruptionModel for parity with /gangguan)
        $topFeedersGangguanRows = $interruptionModel->getTop10ByYear((int)$trendYear, $ulpIdFilter, 10);

        // Top 10 feeders by active inspection findings with pelaksana breakdown
        $temuanByPenyulangRaw = $db->query(
            "SELECT t.penyulang_id, p.nama_penyulang,
                    COUNT(*) total,
                    SUM(CASE WHEN UPPER(t.pelaksana) LIKE '%PDKB%' THEN 1 ELSE 0 END) pdkb,
                    SUM(CASE WHEN UPPER(t.pelaksana) LIKE '%HAR%GTT%' OR UPPER(t.pelaksana) LIKE '%HAR%GARDU%' OR t.pelaksana = 'HAR_GARDU' OR t.pelaksana = 'HAR_GTT' THEN 1 ELSE 0 END) har_gardu,
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

        // Tabel Temuan dan Kebutuhan Material (BOM) per GI, ULP, Penyulang, Section + Standar BOM Konstruksi
        $temuanMaterialMatrix = [
            'rows'             => [],
            'summary_by_mat'   => [],
            'bom_constructions'=> [],
            'gis_list'         => [],
            'total_temuan'     => 0,
            'total_items_qty'  => 0.0,
        ];
        if ($db->tableExists('temuan') && $db->tableExists('temuan_materials')) {
            $hasTmCondType = $db->fieldExists('conductor_type', 'temuan');
            $hasTmCondSize = $db->fieldExists('conductor_size', 'temuan');
            $condTypeExpr  = $hasTmCondType ? "COALESCE(NULLIF(TRIM(t.conductor_type), ''), 'AAAC')" : "'AAAC'";
            $condSizeExpr  = $hasTmCondSize ? "COALESCE(NULLIF(TRIM(t.conductor_size), ''), '150')" : "'150'";
            $tmRowsSql = "
                SELECT
                    t.penyulang_id,
                    COALESCE(p.nama_penyulang, '-') AS nama_penyulang,
                    t.section_id,
                    COALESCE(s.nama_section, '-') AS nama_section,
                    t.ulp_id,
                    COALESCE(u.nama_ulp, '-') AS nama_ulp,
                    '-' AS nama_gi,
                    {$condTypeExpr} AS conductor_type,
                    {$condSizeExpr} AS conductor_size,
                    COUNT(DISTINCT t.id) AS total_temuan,
                    SUM(CASE WHEN tm.canonical_code_snapshot = 'CANON-HDW-003' THEN tm.quantity ELSE 0 END) AS qty_armtie,
                    SUM(CASE WHEN tm.canonical_code_snapshot IN ('CANON-HDW-001', 'CANON-HDW-013', 'CANON-HDW-014', 'CANON-HDW-019') THEN tm.quantity ELSE 0 END) AS qty_crossarm,
                    SUM(CASE WHEN tm.canonical_code_snapshot IN ('CANON-MAT-001', 'CANON-ACC-010', 'CANON-ACC-011', 'CANON-ACC-012') THEN tm.quantity ELSE 0 END) AS qty_pin_isolator,
                    SUM(CASE WHEN tm.canonical_code_snapshot IN ('CANON-MAT-004', 'CANON-HDW-007', 'CANON-HDW-010') THEN tm.quantity ELSE 0 END) AS qty_hang_isolator,
                    SUM(CASE WHEN tm.canonical_code_snapshot IN ('CANON-MAT-003', 'MAT-PROT-FCO-LAT') THEN tm.quantity ELSE 0 END) AS qty_fco,
                    SUM(CASE WHEN tm.canonical_code_snapshot = 'CANON-MAT-002' THEN tm.quantity ELSE 0 END) AS qty_arrester,
                    SUM(CASE WHEN tm.canonical_code_snapshot IN ('CANON-HDW-023', 'CANON-HDW-024', 'CANON-HDW-025') THEN tm.quantity ELSE 0 END) AS qty_skun_konektor,
                    SUM(CASE WHEN tm.canonical_code_snapshot IN ('CANON-HDW-015', 'CANON-HDW-016', 'CANON-HDW-022') THEN tm.quantity ELSE 0 END) AS qty_gsw_ground,
                    SUM(CASE WHEN tm.canonical_code_snapshot LIKE 'MAT-COND-%' THEN tm.quantity ELSE 0 END) AS qty_konduktor_m,
                    GROUP_CONCAT(DISTINCT CONCAT(tm.canonical_code_snapshot, ' (', ROUND(tm.quantity, 0), ' ', tm.unit_snapshot, ')') SEPARATOR ', ') AS rincian_material
                FROM temuan t
                LEFT JOIN temuan_materials tm ON tm.temuan_id = t.id
                LEFT JOIN penyulang p ON p.id = t.penyulang_id
                LEFT JOIN sections s ON s.id = t.section_id
                LEFT JOIN ulps u ON u.id = t.ulp_id
                WHERE t.deleted_at IS NULL
                " . ($ulpIdFilter !== null ? " AND t.ulp_id = " . (int)$ulpIdFilter : "") . "
                GROUP BY t.penyulang_id, p.nama_penyulang, t.section_id, s.nama_section, t.ulp_id, u.nama_ulp, conductor_type, conductor_size
                ORDER BY total_temuan DESC, p.nama_penyulang ASC, s.nama_section ASC
            ";
            $tmRowsQuery = $db->query($tmRowsSql);
            $tmMatrixRows = ($tmRowsQuery && is_object($tmRowsQuery)) ? $tmRowsQuery->getResultArray() : [];
            $gisSet = [];
            foreach ($tmMatrixRows as &$tmr) {
                $fKey = strtoupper(trim((string)$tmr['nama_penyulang']));
                if (($tmr['nama_gi'] === '-' || empty($tmr['nama_gi'])) && isset($giByFeeder[$fKey])) {
                    $tmr['nama_gi'] = $giByFeeder[$fKey];
                }
                if ($tmr['nama_gi'] !== '-' && !empty($tmr['nama_gi'])) {
                    $gisSet[$tmr['nama_gi']] = true;
                }
                $tmr['total_temuan']      = (int)$tmr['total_temuan'];
                $tmr['qty_armtie']        = (float)$tmr['qty_armtie'];
                $tmr['qty_crossarm']      = (float)$tmr['qty_crossarm'];
                $tmr['qty_pin_isolator']  = (float)$tmr['qty_pin_isolator'];
                $tmr['qty_hang_isolator'] = (float)$tmr['qty_hang_isolator'];
                $tmr['qty_fco']           = (float)$tmr['qty_fco'];
                $tmr['qty_arrester']      = (float)$tmr['qty_arrester'];
                $tmr['qty_skun_konektor'] = (float)$tmr['qty_skun_konektor'];
                $tmr['qty_gsw_ground']    = (float)$tmr['qty_gsw_ground'];
                $tmr['qty_konduktor_m']   = (float)$tmr['qty_konduktor_m'];
                $temuanMaterialMatrix['total_temuan'] += $tmr['total_temuan'];
                $temuanMaterialMatrix['total_items_qty'] += (float)($tmr['qty_armtie'] + $tmr['qty_crossarm'] + $tmr['qty_pin_isolator'] + $tmr['qty_hang_isolator'] + $tmr['qty_fco'] + $tmr['qty_arrester'] + $tmr['qty_skun_konektor'] + $tmr['qty_gsw_ground']);
            }
            unset($tmr);
            $temuanMaterialMatrix['rows'] = $tmMatrixRows;
            $temuanMaterialMatrix['gis_list'] = array_keys($gisSet);
            sort($temuanMaterialMatrix['gis_list']);

            // Aggregate totals by canonical BOM material code
            $matRecapQuery = $db->query("
                SELECT
                    tm.canonical_code_snapshot AS material_code,
                    tm.canonical_name_snapshot AS nama_material,
                    tm.unit_snapshot AS satuan,
                    COALESCE(mm.nama_lapangan, tm.canonical_name_snapshot) AS nama_lapangan,
                    COALESCE(mm.material_category, 'HARDWARE') AS material_category,
                    COUNT(DISTINCT tm.temuan_id) AS temuan_count,
                    SUM(tm.quantity) AS total_qty
                FROM temuan_materials tm
                INNER JOIN temuan t ON t.id = tm.temuan_id AND t.deleted_at IS NULL
                LEFT JOIN master_materials mm ON mm.id = tm.material_id
                WHERE 1=1
                " . ($ulpIdFilter !== null ? " AND t.ulp_id = " . (int)$ulpIdFilter : "") . "
                GROUP BY tm.canonical_code_snapshot, tm.canonical_name_snapshot, tm.unit_snapshot, mm.nama_lapangan, mm.material_category
                ORDER BY total_qty DESC
            ");
            $temuanMaterialMatrix['summary_by_mat'] = ($matRecapQuery && is_object($matRecapQuery)) ? $matRecapQuery->getResultArray() : [];
            $matSummarySum = 0;
            foreach ($temuanMaterialMatrix['summary_by_mat'] as $smRow) {
                $matSummarySum += (float)($smRow['total_qty'] ?? 0);
            }
            if ($matSummarySum > 0) {
                $temuanMaterialMatrix['total_items_qty'] = $matSummarySum;
            }
        }

        // Resilient fallback: if temuan_materials is empty or being migrated, show temuan rows directly
        if (empty($temuanMaterialMatrix['rows']) && $db->tableExists('temuan')) {
            $fallbackSql = "
                SELECT
                    t.penyulang_id,
                    COALESCE(p.nama_penyulang, '-') AS nama_penyulang,
                    t.section_id,
                    COALESCE(s.nama_section, '-') AS nama_section,
                    t.ulp_id,
                    COALESCE(u.nama_ulp, '-') AS nama_ulp,
                    '-' AS nama_gi,
                    'AAAC' AS conductor_type,
                    '150' AS conductor_size,
                    COUNT(DISTINCT t.id) AS total_temuan,
                    0 AS qty_armtie,
                    0 AS qty_crossarm,
                    0 AS qty_pin_isolator,
                    0 AS qty_hang_isolator,
                    0 AS qty_fco,
                    0 AS qty_arrester,
                    0 AS qty_skun_konektor,
                    0 AS qty_gsw_ground,
                    0 AS qty_konduktor_m,
                    '' AS rincian_material
                FROM temuan t
                LEFT JOIN penyulang p ON p.id = t.penyulang_id
                LEFT JOIN sections s ON s.id = t.section_id
                LEFT JOIN ulps u ON u.id = t.ulp_id
                WHERE t.deleted_at IS NULL
                " . ($ulpIdFilter !== null ? " AND t.ulp_id = " . (int)$ulpIdFilter : "") . "
                GROUP BY t.penyulang_id, p.nama_penyulang, t.section_id, s.nama_section, t.ulp_id, u.nama_ulp
                ORDER BY total_temuan DESC, p.nama_penyulang ASC, s.nama_section ASC
            ";
            $fbQuery = $db->query($fallbackSql);
            if ($fbQuery && is_object($fbQuery)) {
                $tmMatrixRows = $fbQuery->getResultArray();
                $gisSet = [];
                foreach ($tmMatrixRows as &$tmr) {
                    $fKey = strtoupper(trim((string)$tmr['nama_penyulang']));
                    if (($tmr['nama_gi'] === '-' || empty($tmr['nama_gi'])) && isset($giByFeeder[$fKey])) {
                        $tmr['nama_gi'] = $giByFeeder[$fKey];
                    }
                    if ($tmr['nama_gi'] !== '-' && !empty($tmr['nama_gi'])) {
                        $gisSet[$tmr['nama_gi']] = true;
                    }
                    $tmr['total_temuan'] = (int)$tmr['total_temuan'];
                    $temuanMaterialMatrix['total_temuan'] += $tmr['total_temuan'];
                }
                unset($tmr);
                $temuanMaterialMatrix['rows'] = $tmMatrixRows;
                $temuanMaterialMatrix['gis_list'] = array_keys($gisSet);
                sort($temuanMaterialMatrix['gis_list']);
            }
        }

        if ($db->tableExists('construction_types') && $db->tableExists('construction_bom_items')) {
            $bomCtQuery = $db->query("
                SELECT ct.id, ct.code, ct.name,
                       b.material_id, m.material_code, b.raw_material_name, b.material_alias, b.quantity, b.unit, b.component_category
                FROM construction_types ct
                INNER JOIN construction_bom_items b ON b.construction_type_id = ct.id
                LEFT JOIN master_materials m ON m.id = b.material_id
                WHERE ct.is_active = 1
                ORDER BY ct.sort_order ASC, ct.id ASC, b.sort_order ASC, b.id ASC
            ");
            $bomCtRows = ($bomCtQuery && is_object($bomCtQuery)) ? $bomCtQuery->getResultArray() : [];
            $groupedBomCt = [];
            foreach ($bomCtRows as $bcr) {
                $cCode = (string)$bcr['code'];
                if (!isset($groupedBomCt[$cCode])) {
                    $groupedBomCt[$cCode] = [
                        'code'  => $cCode,
                        'name'  => (string)$bcr['name'],
                        'items' => [],
                    ];
                }
                $groupedBomCt[$cCode]['items'][] = [
                    'material_code' => (string)($bcr['material_code'] ?? '-'),
                    'name'          => (string)$bcr['raw_material_name'],
                    'alias'         => (string)($bcr['material_alias'] ?? ''),
                    'quantity'      => (float)$bcr['quantity'],
                    'unit'          => (string)($bcr['unit'] ?: 'buah'),
                    'category'      => (string)($bcr['component_category'] ?? ''),
                ];
            }
            $temuanMaterialMatrix['bom_constructions'] = array_values($groupedBomCt);
        }

        // Captive coating summary + Konduktor Tidak Standar (< 150 mm²: 70 & 110 mm² vs Standar 150 & 240 mm²)
        $coatingSummary = [
            'total_titik'     => 0,
            'total_meter'     => 0.0,
            'total_penyulang' => 0,
            'by_feeder'       => [],
            'aaac_pool_spans' => 0,
            'aaac_pool_kms'   => 0.0,
            'aaac_top_feeders'=> [],
            'non_std_titik'   => 0,
            'non_std_meter'   => 0.0,
            'non_std_rows'    => [],
            'size_recap'      => [
                '70'  => ['spans' => 0, 'kms' => 0.0, 'temuan_cnt' => 0, 'is_standard' => false],
                '110' => ['spans' => 0, 'kms' => 0.0, 'temuan_cnt' => 0, 'is_standard' => false],
                '150' => ['spans' => 0, 'kms' => 0.0, 'temuan_cnt' => 0, 'is_standard' => true],
                '240' => ['spans' => 0, 'kms' => 0.0, 'temuan_cnt' => 0, 'is_standard' => true],
            ],
        ];
        if ($db->tableExists('captive_non_penugasan')) {
            $coatingWhere = "c.jenis = 'COATING' AND c.status = 'POTENSI' AND c.deleted_at IS NULL"
                . " AND (c.conductor_type IS NULL OR (UPPER(c.conductor_type) LIKE '%AAAC%' AND UPPER(c.conductor_type) NOT LIKE '%AAACS%'))";
            if ($ulpIdFilter !== null) {
                $coatingWhere .= " AND c.ulp_id = " . (int)$ulpIdFilter;
            }
            $coatingByFeederRows = $db->query(
                "SELECT c.penyulang_id,
                        COALESCE(p.nama_penyulang, CONCAT('Penyulang #', c.penyulang_id)) AS nama_penyulang,
                        p.kode_penyulang,
                        c.ulp_id,
                        COALESCE(u.nama_ulp, '-') AS nama_ulp,
                        COUNT(c.id) AS total_titik,
                        SUM(COALESCE(c.distance_meters, 0)) AS total_meter,
                        MAX(c.conductor_size) AS conductor_size,
                        MAX(c.target_conductor_type) AS target_conductor_type
                 FROM captive_non_penugasan c
                 LEFT JOIN penyulang p ON p.id = c.penyulang_id
                 LEFT JOIN ulps u ON u.id = COALESCE(c.ulp_id, p.ulp_id)
                 WHERE " . $coatingWhere . "
                 GROUP BY c.penyulang_id, p.nama_penyulang, p.kode_penyulang, c.ulp_id, u.nama_ulp
                 ORDER BY total_meter DESC, total_titik DESC"
            )->getResultArray();

            $totTitik = 0;
            $totMeter = 0.0;
            foreach ($coatingByFeederRows as &$cf) {
                $cf['penyulang_id'] = (int)$cf['penyulang_id'];
                $cf['ulp_id']       = (int)$cf['ulp_id'];
                $cf['total_titik']  = (int)$cf['total_titik'];
                $cf['total_meter']  = (float)$cf['total_meter'];
                $cf['total_km']     = round($cf['total_meter'] / 1000, 3);
                $totTitik += $cf['total_titik'];
                $totMeter += $cf['total_meter'];
            }
            unset($cf);
            $coatingSummary['total_titik']     = $totTitik;
            $coatingSummary['total_meter']     = $totMeter;
            $coatingSummary['total_penyulang'] = count($coatingByFeederRows);
            $coatingSummary['by_feeder']       = $coatingByFeederRows;

            // Non-standard conductor (< 150 mm² / KONDUKTOR) candidates from captive_non_penugasan
            $nonStdWhere = "c.jenis != 'COATING' AND c.status = 'POTENSI' AND c.deleted_at IS NULL";
            if ($ulpIdFilter !== null) {
                $nonStdWhere .= " AND c.ulp_id = " . (int)$ulpIdFilter;
            }
            $nonStdCaptiveRows = $db->query(
                "SELECT c.penyulang_id,
                        COALESCE(p.nama_penyulang, '-') AS nama_penyulang,
                        COALESCE(u.nama_ulp, '-') AS nama_ulp,
                        COALESCE(c.conductor_type, 'AAAC') AS conductor_type,
                        COALESCE(c.conductor_size, '70 mm²') AS conductor_size,
                        COALESCE(c.target_conductor_size, '150 mm²') AS target_conductor_size,
                        COUNT(c.id) AS total_titik,
                        SUM(COALESCE(c.distance_meters, 0)) AS total_meter
                 FROM captive_non_penugasan c
                 LEFT JOIN penyulang p ON p.id = c.penyulang_id
                 LEFT JOIN ulps u ON u.id = COALESCE(c.ulp_id, p.ulp_id)
                 WHERE " . $nonStdWhere . "
                 GROUP BY c.penyulang_id, p.nama_penyulang, u.nama_ulp, c.conductor_type, c.conductor_size, c.target_conductor_size
                 ORDER BY total_meter DESC"
            )->getResultArray();
            foreach ($nonStdCaptiveRows as $nsr) {
                $coatingSummary['non_std_titik'] += (int)$nsr['total_titik'];
                $coatingSummary['non_std_meter'] += (float)$nsr['total_meter'];
                $coatingSummary['non_std_rows'][] = $nsr;
            }
        }
        // Populate size_recap from gis_translines & temuan
        if ($db->tableExists('gis_translines') && $db->fieldExists('conductor_size', 'gis_translines')) {
            $tlSizeQuery = $db->query("
                SELECT gt.conductor_size, COUNT(gt.id) AS spans, SUM(COALESCE(gt.distance_meters, 0))/1000 AS kms
                FROM gis_translines gt
                LEFT JOIN penyulang p ON p.id = gt.penyulang_id
                WHERE gt.deleted_at IS NULL
                " . ($ulpIdFilter !== null ? " AND p.ulp_id = " . (int)$ulpIdFilter : "") . "
                GROUP BY gt.conductor_size
            ");
            foreach (($tlSizeQuery && is_object($tlSizeQuery) ? $tlSizeQuery->getResultArray() : []) as $tsr) {
                $sz = preg_replace('/[^0-9]/', '', (string)($tsr['conductor_size'] ?? '150'));
                if (isset($coatingSummary['size_recap'][$sz])) {
                    $coatingSummary['size_recap'][$sz]['spans'] += (int)$tsr['spans'];
                    $coatingSummary['size_recap'][$sz]['kms']   = round($coatingSummary['size_recap'][$sz]['kms'] + (float)$tsr['kms'], 2);
                }
            }
        }
        if ($db->tableExists('temuan') && $db->fieldExists('conductor_size', 'temuan')) {
            $tmSizeQuery = $db->query("
                SELECT COALESCE(NULLIF(TRIM(conductor_size), ''), '150') AS sz, COUNT(id) AS cnt
                FROM temuan
                WHERE deleted_at IS NULL
                " . ($ulpIdFilter !== null ? " AND ulp_id = " . (int)$ulpIdFilter : "") . "
                GROUP BY sz
            ");
            foreach (($tmSizeQuery && is_object($tmSizeQuery) ? $tmSizeQuery->getResultArray() : []) as $tmsr) {
                $sz = preg_replace('/[^0-9]/', '', (string)$tmsr['sz']);
                if (isset($coatingSummary['size_recap'][$sz])) {
                    $coatingSummary['size_recap'][$sz]['temuan_cnt'] += (int)$tmsr['cnt'];
                }
            }
        }
        // Calculate total eligible AAAC pool from gis_translines (respecting ULP filter)
        foreach ($networkFeeders as $nf) {
            if (($nf['aaac_kms'] ?? 0) > 0) {
                $coatingSummary['aaac_pool_kms']   += (float)$nf['aaac_kms'];
                $coatingSummary['aaac_pool_spans'] += (int)($nf['spans'] ?? 0);
                $coatingSummary['aaac_top_feeders'][] = $nf;
            }
        }
        $coatingSummary['aaac_pool_kms'] = round($coatingSummary['aaac_pool_kms'], 2);

        // Sidebar collapse preference: default TRUE (collapsed) unless user explicitly expanded
        $prefersCollapsed = $session->get('sidebar_collapsed') ?? true;

        $viewParams = [
            'userName'              => $session->get('user_name') ?: 'Petugas',
            'userRole'              => $role,
            'canInput'              => $canInput,
            'canEdit'               => $canEdit,
            'canDelete'             => $canDelete,
            'canApprove'            => $canApprove,
            'canMonitoring'         => $canMonitoring,
            'stats'                 => $stats,
            'assetHealthData'       => $assetHealthData,
            'gangguanStats'         => $gangguanStats,
            'ulpsList'              => $ulpsList,
            'selectedUlpId'         => $ulpIdFilter,
            'woStats'               => $woStats,
            'assetStats'            => $assetStats,
            'monthlyData'           => $monthlyData,
            'ulpData'               => $ulpData,
            'penyulangData'         => $penyulangData,
            'penyulangRanking'      => $penyulangRanking,
            'pelaksanaData'         => $pelaksanaData,
            'prioritasData'         => $prioritasData,
            'potensiGangguanData'   => $potensiGangguanData,
            'mapPins'               => $mapPins,
            'topInputOfficers'      => $topInputOfficers,
            'topUpdateOfficers'     => $topUpdateOfficers,
            'monthFilter'           => $monthFilter,
            'yearFilter'            => $yearFilter,
            'availableTrendYears'   => $availableTrendYears,
            'trendYear'             => $trendYear,
            'trendComparison'       => $trendComparison,
            'prefersCollapsed'      => $prefersCollapsed,
            'networkData'           => $networkData,
            'assetSummary'          => $assetSummary,
            'availableInterruptionYears' => $availableInterruptionYears,
            'cumulativeGangguan'         => $cumulativeGangguan,
            'cumulativeGangguanYoY'      => $cumulativeGangguanYoY,
            'causeBreakdownByYear'       => $causeBreakdownByYear,
            'topFeedersGangguan'    => $topFeedersGangguanRows,
            'topInspeksiPenyulang'  => $topInspeksiPenyulang,
            'temuanMaterialMatrix'  => $temuanMaterialMatrix,
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
