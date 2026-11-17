<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Unified Interruption Analytics Service shared between `/gangguan` and `/dashboard`.
 *
 * Guarantees identical year selection, ULP scoping, equipment family classification,
 * cause code resolution, and metric calculations across both pages.
 */
class GangguanAnalyticsService
{
    public const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    public const MONTH_ABBR_EN = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

    private const CANONICAL_CAUSE_META = [
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

    protected BaseConnection $db;
    protected CauseCodeResolutionService $causeResolver;

    /** @var array<int, array>|null */
    protected ?array $allRowsCache = null;
    protected ?array $ulpCatalogCache = null;
    protected ?array $feederCatalogCache = null;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db            = $db ?? \Config\Database::connect();
        $this->causeResolver = new CauseCodeResolutionService($this->db);
    }

    /**
     * Resolve shared filter context (role-enforced ULP scope, year_a, year_b, penyulang_id, scope).
     */
    public function resolveContext(array $queryParams, string $role, ?int $userUlpId): array
    {
        $isGlobalRole = in_array(strtolower($role), ['administrator', 'admin', 'admin_pusat', 'supervisor_up3'], true);

        $ulpIdFilter = null;
        if (!$isGlobalRole && !empty($userUlpId)) {
            $ulpIdFilter = (int)$userUlpId;
        }
        $reqUlp = isset($queryParams['ulp_id']) && $queryParams['ulp_id'] !== '' ? (int)$queryParams['ulp_id'] : null;
        if ($reqUlp && $isGlobalRole) {
            $ulpIdFilter = $reqUlp;
        }

        $availableYears = $this->getAvailableYears();
        $latestYear     = $availableYears[0] ?? (int)date('Y');

        $rawYearA = $queryParams['year_a'] ?? $queryParams['trend_year'] ?? $queryParams['year'] ?? null;
        $yearA    = ($rawYearA !== null && $rawYearA !== '') ? (int)$rawYearA : $latestYear;
        if (!in_array($yearA, $availableYears, true)) {
            $yearA = $latestYear;
        }

        $defaultYearB = $availableYears[1] ?? ($yearA - 1);
        $rawYearB     = $queryParams['year_b'] ?? null;
        $yearB        = ($rawYearB !== null && $rawYearB !== '') ? (int)$rawYearB : $defaultYearB;
        if (!in_array($yearB, $availableYears, true) && count($availableYears) > 1) {
            $yearB = $defaultYearB;
        }

        $penyulangId = !empty($queryParams['penyulang_id']) ? (int)$queryParams['penyulang_id'] : null;

        $scope = strtolower(trim((string)($queryParams['scope'] ?? 'year_a')));
        if (!in_array($scope, ['year_a', 'year_b', 'combined'], true)) {
            $scope = 'year_a';
        }

        return [
            'available_years' => $availableYears,
            'year_a'          => $yearA,
            'year_b'          => $yearB,
            'ulp_id'          => $ulpIdFilter,
            'penyulang_id'    => $penyulangId,
            'scope'           => $scope,
            'month'           => !empty($queryParams['month']) ? max(1, min(12, (int)$queryParams['month'])) : null,
            'category'        => !empty($queryParams['category']) ? strtoupper(trim((string)$queryParams['category'])) : null,
            'relay'           => !empty($queryParams['relay']) ? strtoupper(trim((string)$queryParams['relay'])) : null,
            'cause'           => !empty($queryParams['cause']) ? strtoupper(trim((string)$queryParams['cause'])) : null,
            'is_global_role'  => $isGlobalRole,
        ];
    }

    /**
     * Distinct years descending from historical_feeder_interruptions.
     */
    public function getAvailableYears(): array
    {
        $years = [];
        foreach ($this->loadAllNormalizedRows() as $r) {
            $yr = $r['year'];
            if ($yr > 0) {
                $years[$yr] = true;
            }
        }
        $list = array_keys($years);
        rsort($list);
        return !empty($list) ? $list : [(int)date('Y'), (int)date('Y') - 1];
    }

    /**
     * Shared YoY summary & monthly distributions used by both `/gangguan` and `/dashboard`.
     */
    public function getSharedYoYSummary(int $yearA, int $yearB, ?int $ulpId = null, ?int $penyulangId = null): array
    {
        $rows = $this->filterRows($this->loadAllNormalizedRows(), $ulpId, $penyulangId);

        $monthlyA = array_fill(1, 12, 0);
        $monthlyB = array_fill(1, 12, 0);
        $maxMoA   = 0;
        $maxMoB   = 0;
        $totalA   = 0;
        $totalB   = 0;

        foreach ($rows as $r) {
            if ($r['year'] === $yearA) {
                $monthlyA[$r['month']]++;
                $totalA++;
                if ($r['month'] > $maxMoA) {
                    $maxMoA = $r['month'];
                }
            }
            if ($r['year'] === $yearB) {
                $monthlyB[$r['month']]++;
                $totalB++;
                if ($r['month'] > $maxMoB) {
                    $maxMoB = $r['month'];
                }
            }
        }

        $effMaxA          = $maxMoA > 0 ? $maxMoA : 12;
        $effMaxB          = $maxMoB > 0 ? $maxMoB : 12;
        $comparableMonths = min($effMaxA, $effMaxB);

        $compTotalA = 0;
        $compTotalB = 0;
        for ($m = 1; $m <= $comparableMonths; $m++) {
            $compTotalA += $monthlyA[$m];
            $compTotalB += $monthlyB[$m];
        }

        $compDiff = $compTotalA - $compTotalB;
        $compPct  = $compTotalB > 0 ? round(($compDiff / $compTotalB) * 100, 1) : null;

        $fullDiff = $totalA - $totalB;
        $fullPct  = $totalB > 0 ? round(($fullDiff / $totalB) * 100, 1) : null;

        $yoyText = 'Data pembanding n/a';
        $yoyClass = 'text-muted';
        if ($compPct !== null) {
            if ($compPct < 0) {
                $yoyText  = "{$compPct}% YoY (Membaik)";
                $yoyClass = 'text-success';
            } elseif ($compPct > 0) {
                $yoyText  = "+{$compPct}% YoY (Meningkat)";
                $yoyClass = 'text-danger';
            } else {
                $yoyText  = '0% YoY (Sama)';
            }
        }

        return [
            'year_a'              => $yearA,
            'year_b'              => $yearB,
            'monthly_a'           => $monthlyA,
            'monthly_b'           => $monthlyB,
            'total_a'             => $totalA,
            'total_b'             => $totalB,
            'max_month_a'         => $maxMoA,
            'max_month_b'         => $maxMoB,
            'comparable_months'   => $comparableMonths,
            'comparable_period'   => 'Jan–' . self::MONTH_LABELS[max(0, min(11, $comparableMonths - 1))],
            'yearA_period'        => 'Jan–' . self::MONTH_LABELS[max(0, min(11, $effMaxA - 1))] . ' ' . $yearA,
            'yearB_period'        => 'Jan–' . self::MONTH_LABELS[max(0, min(11, $effMaxB - 1))] . ' ' . $yearB,
            'comparable_total_a'  => $compTotalA,
            'comparable_total_b'  => $compTotalB,
            'comparable_diff'     => $compDiff,
            'comparable_pct'      => $compPct,
            'full_diff'           => $fullDiff,
            'full_pct'            => $fullPct,
            'yoy_text'            => $yoyText,
            'yoy_class'           => $yoyClass,
        ];
    }

    /**
     * Complete 12-item analytics payload for `/gangguan` page.
     */
    public function getGangguanAnalytics12Items(array $ctx): array
    {
        $yearA = (int)$ctx['year_a'];
        $yearB = (int)$ctx['year_b'];
        $ulpId = $ctx['ulp_id'];
        $pid   = $ctx['penyulang_id'];
        $scope = (string)($ctx['scope'] ?? 'year_a');

        $scopedRows = $this->filterRows(
            $this->loadAllNormalizedRows(),
            $ulpId,
            $pid,
            $ctx['month'] ?? null,
            $ctx['category'] ?? null,
            $ctx['relay'] ?? null,
            $ctx['cause'] ?? null
        );

        $rowsA = [];
        $rowsB = [];
        $rowsCombined = [];
        foreach ($scopedRows as $r) {
            if ($r['year'] === $yearA) {
                $rowsA[] = $r;
                $rowsCombined[] = $r;
            } elseif ($r['year'] === $yearB) {
                $rowsB[] = $r;
                $rowsCombined[] = $r;
            }
        }

        if ($yearA === $yearB) {
            $rowsB = $rowsA;
        }

        $rowsFocus = match ($scope) {
            'year_b'   => $rowsB,
            'combined' => $rowsCombined,
            default    => $rowsA,
        };

        $focusLabel = match ($scope) {
            'year_b'   => "Tahun {$yearB}",
            'combined' => ($yearA === $yearB ? "Tahun {$yearA}" : "Gabungan {$yearA} & {$yearB}"),
            default    => "Tahun {$yearA}",
        };

        $yoySummary = $this->getSharedYoYSummary($yearA, $yearB, $ulpId, $pid);

        // Item 1: Permanen vs Temporer
        $catFocus = $this->countByCategory($rowsFocus);
        $catA     = $this->countByCategory($rowsA);
        $catB     = $this->countByCategory($rowsB);

        // Item 2: Gangguan per ULP
        $ulpSummary = $this->buildUlpComparison($rowsFocus, $rowsA, $rowsB);

        // Item 3: Total Gangguan + Monthly
        $totalGangguan = [
            'focus_total'    => count($rowsFocus),
            'focus_label'    => $focusLabel,
            'combined_total' => count($rowsCombined),
            'year_a_total'   => count($rowsA),
            'year_b_total'   => count($rowsB),
            'yoy'            => $yoySummary,
            'monthly_a'      => array_values($this->countMonthly($rowsA)),
            'monthly_b'      => array_values($this->countMonthly($rowsB)),
        ];

        // Item 4: Permanen vs Temporer per ULP
        $permTempPerUlp = $this->buildCategoryByUlp($rowsFocus);

        // Item 5: Relai yang bekerja
        $relaySummary = $this->buildRelayComparison($rowsFocus, $rowsA, $rowsB);

        // Item 6: Rasio PMT vs REC/PMCB
        $deviceRatio = $this->buildSwitchingTypeComparison($rowsFocus, $rowsA, $rowsB);

        // Item 7: Pareto penyebab gangguan (PENYEBAB SESUAI KODE GANGGUAN)
        $paretoPenyebab = $this->buildParetoCauses($rowsFocus);

        // Item 8: Top 10 gangguan penyulang
        $top10Base = ($scope === 'year_b') ? $rowsB : (($scope === 'combined') ? $rowsCombined : $rowsA);
        $top10 = $this->buildTop10Feeders($top10Base, $rowsA, $rowsB, 10);

        // Item 9: Sebaran gangguan PMT dan Recloser
        $sebaranAlat = $this->buildDeviceDistribution($rowsFocus);

        // Item 10: Detail gangguan penyulang per bulan (Year A vs Year B)
        $detailPenyulangBulanan = $this->buildFeederMonthlyPivot($rowsCombined, $yearA, $yearB);

        // Item 11: Gangguan penyulang vs penyebab gangguan
        $penyulangVsPenyebab = $this->buildFeederVsCausePivot($rowsFocus);

        // Item 12: Detail bulanan gangguan PMT, Recloser, PMCB, dan Key Point
        $detailBulananPeralatan = $this->buildEquipmentMonthlyPivot($rowsFocus);

        // ENS (kWh) Reliability Analysis
        $ensAnalysis = $this->buildEnsComparison($rowsA, $rowsB, $rowsFocus, $yearA, $yearB, $scope, $focusLabel);

        return [
            'focus_label'                  => $focusLabel,
            'scope'                        => $scope,
            'permanen_vs_temporer'         => ['focus' => $catFocus, 'year_a' => $catA, 'year_b' => $catB],
            'gangguan_per_ulp'             => $ulpSummary,
            'total_gangguan'               => $totalGangguan,
            'permanen_vs_temporer_per_ulp' => $permTempPerUlp,
            'relai_bekerja'                => $relaySummary,
            'rasio_pmt_vs_recl_pmcb'       => $deviceRatio,
            'pareto_penyebab'              => $paretoPenyebab,
            'top_10_penyulang'             => $top10,
            'sebaran_pmt_recloser'         => $sebaranAlat,
            'detail_penyulang_bulanan'     => $detailPenyulangBulanan,
            'penyulang_vs_penyebab'        => $penyulangVsPenyebab,
            'detail_bulanan_peralatan'     => $detailBulananPeralatan,
            'ens_analysis'                 => $ensAnalysis,
            'filtered_rows'                => $scopedRows,
        ];
    }

    /**
     * Payload for Main Dashboard (`/dashboard`) — strictly matching `/gangguan` definitions.
     */
    public function getDashboardSyncPayload(array $ctx, array $allTrendYears): array
    {
        $yearA       = (int)$ctx['year_a'];
        $yearB       = (int)$ctx['year_b'];
        $ulpId       = $ctx['ulp_id'];
        $penyulangId = !empty($ctx['penyulang_id']) ? (int)$ctx['penyulang_id'] : null;

        $availableYears = $ctx['available_years'];
        $yoySummary     = $this->getSharedYoYSummary($yearA, $yearB, $ulpId, $penyulangId);

        $gangguanStats = [
            'current_ytd'       => $yoySummary['total_a'],
            'last_ytd'          => $yoySummary['total_b'],
            'comparable_a'      => $yoySummary['comparable_total_a'],
            'comparable_b'      => $yoySummary['comparable_total_b'],
            'comparable_period' => $yoySummary['comparable_period'],
            'yoy_pct'           => $yoySummary['comparable_pct'] ?? 0,
            'yoy_text'          => $yoySummary['yoy_text'],
            'yoy_class'         => $yoySummary['yoy_class'],
            'year'              => $yearA,
            'last_year'         => $yearB,
        ];

        $rows = $this->filterRows($this->loadAllNormalizedRows(), $ulpId, $penyulangId);

        $cumulativeGangguanYoY     = [];
        $monthlyFaultCurrentByYear = [];
        $faultCurrentAvgByYear     = [];
        $monthlyRelayByYear        = [];
        $monthlyCauseByYear        = [];
        $monthlyDeviceByYear       = [];
        $monthlyEnsByYear          = [];
        $monthlyEnsCntByYear       = [];
        $annualEnsByYear           = [];
        $categoryByYear            = [];
        $switchingByYear           = [];
        $monthlyCategoryByYear     = [];
        $topFeedersByYear          = [];

        foreach ($availableYears as $yr) {
            $yr = (int)$yr;
            $yrRows = array_values(array_filter($rows, static fn(array $r): bool => $r['year'] === $yr));
            if (empty($yrRows) && $yr !== (int)date('Y')) {
                continue;
            }

            $ensSumYr = $this->summarizeEnsByMonth($yrRows);
            $monthlyEnsByYear[$yr]    = $ensSumYr['monthly'];
            $monthlyEnsCntByYear[$yr] = $ensSumYr['monthly_cnt'];
            $annualEnsByYear[$yr]     = [
                'year'         => $yr,
                'total_kwh'    => $ensSumYr['total_kwh'],
                'valid_events' => $ensSumYr['valid_events'],
                'total_events' => $ensSumYr['total_events'],
                'max_month'    => $ensSumYr['max_month'],
                'avg_per_trip' => $ensSumYr['avg_per_trip'],
            ];
            $categoryByYear[$yr]  = $this->countByCategory($yrRows);
            $switchingByYear[$yr] = $this->buildSwitchingTypeComparison($yrRows, $yrRows, []);

            $moCounts = $this->countMonthly($yrRows);
            $maxMo = 0;
            foreach ($moCounts as $m => $c) {
                if ($c > 0 && $m > $maxMo) {
                    $maxMo = $m;
                }
            }

            $runSum = 0;
            $cumArr = [];
            for ($m = 1; $m <= 12; $m++) {
                if ($m <= $maxMo) {
                    $runSum += $moCounts[$m];
                    $cumArr[] = $runSum;
                } else {
                    $cumArr[] = null;
                }
            }

            $cumulativeGangguanYoY[$yr] = [
                'year'       => $yr,
                'total'      => $runSum,
                'max_month'  => $maxMo,
                'has_source' => $runSum > 0,
                'monthly'    => array_values($moCounts),
                'cumulative' => $cumArr,
            ];

            // Fault Current (A)
            $currSum = array_fill(1, 12, 0.0);
            $currCnt = array_fill(1, 12, 0);
            foreach ($yrRows as $r) {
                if ($r['fault_current_amperes'] !== null && $r['fault_current_amperes'] > 0) {
                    $currSum[$r['month']] += $r['fault_current_amperes'];
                    $currCnt[$r['month']]++;
                }
            }
            $currAvgs = [];
            for ($m = 1; $m <= 12; $m++) {
                $currAvgs[] = $currCnt[$m] > 0 ? round($currSum[$m] / $currCnt[$m], 1) : null;
            }
            $monthlyFaultCurrentByYear[$yr] = $currAvgs;
            $yrAmpSum = array_sum($currSum);
            $yrAmpCnt = array_sum($currCnt);
            $faultCurrentAvgByYear[$yr] = $yrAmpCnt > 0 ? round($yrAmpSum / $yrAmpCnt, 1) : null;

            // Relay, Cause Category, Device Type
            foreach ($yrRows as $r) {
                $moIdx = $r['month'] - 1;
                $rtype = $r['relay_label'];
                if (!isset($monthlyRelayByYear[$yr][$rtype])) {
                    $monthlyRelayByYear[$yr][$rtype] = array_fill(0, 12, null);
                }
                $monthlyRelayByYear[$yr][$rtype][$moIdx] = ($monthlyRelayByYear[$yr][$rtype][$moIdx] ?? 0) + 1;

                $ccat = $r['cause_category'] ?: 'UNKNOWN_INVESTIGATION';
                if (!isset($monthlyCauseByYear[$yr][$ccat])) {
                    $monthlyCauseByYear[$yr][$ccat] = array_fill(0, 12, null);
                }
                $monthlyCauseByYear[$yr][$ccat][$moIdx] = ($monthlyCauseByYear[$yr][$ccat][$moIdx] ?? 0) + 1;

                $dtype = $r['switching_device_type'] ?: 'RECL-PMCB';
                if (!isset($monthlyDeviceByYear[$yr][$dtype])) {
                    $monthlyDeviceByYear[$yr][$dtype] = array_fill(0, 12, null);
                }
                $monthlyDeviceByYear[$yr][$dtype][$moIdx] = ($monthlyDeviceByYear[$yr][$dtype][$moIdx] ?? 0) + 1;

                $icat = $r['interruption_category'] ?: 'TEMPORARY';
                if (!isset($monthlyCategoryByYear[$yr][$icat])) {
                    $monthlyCategoryByYear[$yr][$icat] = array_fill(0, 12, null);
                }
                $monthlyCategoryByYear[$yr][$icat][$moIdx] = ($monthlyCategoryByYear[$yr][$icat][$moIdx] ?? 0) + 1;
            }

            // Top 10 Feeders for this year
            $topFeedersByYear[$yr] = $this->buildTop10Feeders($yrRows, $yrRows, [], 10)['rows'];
        }

        krsort($cumulativeGangguanYoY);

        $activeUlpName = 'Semua ULP';
        if ($ulpId !== null) {
            $ulps = $this->getUlpCatalog();
            $activeUlpName = $ulps['by_id'][$ulpId]['nama_ulp'] ?? ('ULP #' . $ulpId);
        }
        $activeFeederName = 'Semua Penyulang';
        if ($penyulangId !== null) {
            $feeders = $this->getFeederCatalog();
            $activeFeederName = $feeders['by_id'][$penyulangId]['nama_penyulang'] ?? ('Penyulang #' . $penyulangId);
        }

        $interruptionMetricsPayload = [
            'yoy'               => $cumulativeGangguanYoY,
            'fault_current'     => $monthlyFaultCurrentByYear,
            'fault_current_avg' => $faultCurrentAvgByYear,
            'ens_monthly'       => $monthlyEnsByYear,
            'ens_monthly_cnt'   => $monthlyEnsCntByYear,
            'ens_annual'        => $annualEnsByYear,
            'ens_meta'          => $this->getEnsFormulaMeta(),
            'ens_scope'         => $ctx['scope'] ?? 'year_a',
            'ens_penyulang_id'  => $penyulangId,
            'category_summary'  => $categoryByYear,
            'switching_summary' => $switchingByYear,
            'category_monthly'  => $monthlyCategoryByYear,
            'filter_context'    => [
                'ulp_label'       => $activeUlpName,
                'penyulang_label' => $activeFeederName,
                'scope'           => $ctx['scope'] ?? 'year_a',
            ],
            'relay'             => $monthlyRelayByYear,
            'cause'             => $monthlyCauseByYear,
            'device'            => $monthlyDeviceByYear,
            'years'             => array_values($availableYears),
        ];

        $causeBreakdownByYear = $this->buildDashboardCauseBreakdown($rows, $allTrendYears, $availableYears);

        return [
            'gangguanStats'              => $gangguanStats,
            'availableInterruptionYears' => $availableYears,
            'cumulativeGangguan'         => $cumulativeGangguanYoY[$yearA]['cumulative'] ?? array_fill(0, 12, null),
            'cumulativeGangguanYoY'      => $cumulativeGangguanYoY,
            'interruptionMetricsPayload' => $interruptionMetricsPayload,
            'causeBreakdownByYear'       => $causeBreakdownByYear,
            'topFeedersGangguan'         => $topFeedersByYear[$yearA] ?? [],
            'topFeedersGangguanByYear'   => $topFeedersByYear,
        ];
    }

    /**
     * Authoritative definition & formula metadata for Energy Not Supplied (ENS).
     * Mirrors spreadsheet `DATA_GANGGUAN` column `( Kwh )` (`energy_not_supplied_kwh`).
     */
    public function getEnsFormulaMeta(): array
    {
        return [
            'source_column' => '( Kwh )',
            'db_field'      => 'energy_not_supplied_kwh',
            'unit'          => 'kWh',
            'formula_text'  => '√3 × 20 kV × Arus Gangguan (A) × 0,85 × Lama Padam (jam)',
        ];
    }

    /**
     * Summarize monthly and annual ENS (kWh) from normalized interruption rows.
     * Monthly totals are rounded to 2 decimals; annual total equals exact sum of monthly bars.
     */
    protected function summarizeEnsByMonth(array $rows): array
    {
        $moSums   = array_fill(1, 12, 0.0);
        $moCounts = array_fill(1, 12, 0);
        $validCnt = 0;
        $maxMo    = 0;

        foreach ($rows as $r) {
            $mo = (int)($r['month'] ?? 0);
            if ($mo < 1 || $mo > 12) {
                continue;
            }
            if ($mo > $maxMo) {
                $maxMo = $mo;
            }
            $kwh = $r['energy_not_supplied_kwh'] ?? null;
            if ($kwh !== null) {
                $moSums[$mo] += max(0.0, (float)$kwh);
                $moCounts[$mo]++;
                $validCnt++;
            }
        }

        $monthly = [];
        $annual  = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            if ($m <= $maxMo) {
                $rounded   = round($moSums[$m], 2);
                $monthly[] = $rounded;
                $annual   += $rounded;
            } else {
                $monthly[] = null;
            }
        }

        $annual = round($annual, 2);
        $totalEvents = count($rows);

        return [
            'monthly'      => $monthly,
            'monthly_cnt'  => array_values($moCounts),
            'total_kwh'    => $annual,
            'valid_events' => $validCnt,
            'total_events' => $totalEvents,
            'max_month'    => $maxMo,
            'avg_per_trip' => $totalEvents > 0 ? round($annual / $totalEvents, 2) : 0.0,
        ];
    }

    /**
     * Build two-year & active-scope ENS (kWh) monthly + annual comparison payload.
     */
    protected function buildEnsComparison(
        array $rowsA,
        array $rowsB,
        array $rowsFocus,
        int $yearA,
        int $yearB,
        string $scope,
        string $focusLabel
    ): array {
        $sumA     = $this->summarizeEnsByMonth($rowsA);
        $sumB     = $this->summarizeEnsByMonth($rowsB);
        $sumFocus = $this->summarizeEnsByMonth($rowsFocus);

        $effMaxA = $sumA['max_month'] > 0 ? $sumA['max_month'] : 12;
        $effMaxB = $sumB['max_month'] > 0 ? $sumB['max_month'] : 12;
        $compMo  = min($effMaxA, $effMaxB);

        $compA = 0.0;
        $compB = 0.0;
        for ($i = 0; $i < $compMo; $i++) {
            $compA += (float)($sumA['monthly'][$i] ?? 0.0);
            $compB += (float)($sumB['monthly'][$i] ?? 0.0);
        }
        $compA    = round($compA, 2);
        $compB    = round($compB, 2);
        $compDiff = round($compA - $compB, 2);
        $compPct  = $compB > 0 ? round(($compDiff / $compB) * 100, 1) : null;

        $fullDiff = round($sumA['total_kwh'] - $sumB['total_kwh'], 2);
        $fullPct  = $sumB['total_kwh'] > 0 ? round(($fullDiff / $sumB['total_kwh']) * 100, 1) : null;
        $combTot  = ($yearA === $yearB) ? $sumA['total_kwh'] : round($sumA['total_kwh'] + $sumB['total_kwh'], 2);

        return [
            'year_a'             => $yearA,
            'year_b'             => $yearB,
            'scope'              => $scope,
            'focus_label'        => $focusLabel,
            'monthly_a'          => $sumA['monthly'],
            'monthly_b'          => $sumB['monthly'],
            'monthly_cnt_a'      => $sumA['monthly_cnt'],
            'monthly_cnt_b'      => $sumB['monthly_cnt'],
            'monthly_focus'      => $sumFocus['monthly'],
            'total_a'            => $sumA['total_kwh'],
            'total_b'            => $sumB['total_kwh'],
            'total_focus'        => $sumFocus['total_kwh'],
            'combined_total'     => $combTot,
            'valid_a'            => $sumA['valid_events'],
            'valid_b'            => $sumB['valid_events'],
            'valid_focus'        => $sumFocus['valid_events'],
            'count_a'            => $sumA['total_events'],
            'count_b'            => $sumB['total_events'],
            'count_focus'        => $sumFocus['total_events'],
            'avg_a'              => $sumA['avg_per_trip'],
            'avg_b'              => $sumB['avg_per_trip'],
            'full_diff'          => $fullDiff,
            'full_pct'           => $fullPct,
            'comparable_months'  => $compMo,
            'comparable_period'  => 'Jan–' . self::MONTH_LABELS[max(0, min(11, $compMo - 1))],
            'comparable_a'       => $compA,
            'comparable_b'       => $compB,
            'comparable_diff'    => $compDiff,
            'comparable_pct'     => $compPct,
            'meta'               => $this->getEnsFormulaMeta(),
        ];
    }

    /**
     * Classify equipment family explicitly from `RECLOSER` and `PMT-RECL/PMCB`.
     */
    public static function classifyEquipmentFamily(string $deviceName, string $switchingDeviceType): string
    {
        $dev  = strtoupper(trim($deviceName));
        $type = strtoupper(trim($switchingDeviceType));

        if ($dev === '' && $type === 'PMT') {
            return 'PMT';
        }
        if (str_starts_with($dev, 'PMT')) {
            return 'PMT';
        }
        if (str_starts_with($dev, 'RECL')) {
            return 'Recloser';
        }
        if (str_starts_with($dev, 'PMCB')) {
            return 'PMCB';
        }
        if ($dev !== '' && preg_match('/^(GH|KP|KEY|LBS|ABS|ASS|SEC)\b/', $dev)) {
            return 'Key Point';
        }

        return 'Lainnya';
    }

    /**
     * Load and normalize all rows once per service instance.
     */
    protected function loadAllNormalizedRows(): array
    {
        if ($this->allRowsCache !== null) {
            return $this->allRowsCache;
        }

        if (!$this->db->tableExists('historical_feeder_interruptions')) {
            $this->allRowsCache = [];
            return [];
        }

        $fields = array_flip($this->db->getFieldNames('historical_feeder_interruptions'));
        $selectCols = [
            'id', 'source_system', 'event_date', 'interruption_started_at', 'interruption_ended_at',
            'outage_duration_minutes', 'substation_name', 'ulp_name', 'feeder_name',
            'switching_device_type', 'device_name', 'relay_trip_type', 'faulted_phase',
            'weather_condition', 'fault_current_amperes', 'energy_not_supplied_kwh',
            'interruption_category', 'cause_raw', 'cause_canonical_code', 'cause_category',
            'cause_mapping_status', 'field_narrative_raw', 'restoration_action_raw', 'extracted_zone_section',
        ];
        if (isset($fields['ulp_id'])) {
            $selectCols[] = 'ulp_id';
        }
        if (isset($fields['penyulang_id'])) {
            $selectCols[] = 'penyulang_id';
        }
        if (isset($fields['kategori_raw'])) {
            $selectCols[] = 'kategori_raw';
        }
        if (isset($fields['batch_id'])) {
            $selectCols[] = 'batch_id';
        }

        $rawRows = $this->db->table('historical_feeder_interruptions')
            ->select(implode(', ', $selectCols))
            ->where('event_date IS NOT NULL')
            ->orderBy('event_date', 'DESC')
            ->orderBy('interruption_started_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();

        $ulps    = $this->getUlpCatalog();
        $feeders = $this->getFeederCatalog();

        $normalized = [];
        foreach ($rawRows as $r) {
            $dateStr = trim((string)($r['event_date'] ?? ''));
            if (strlen($dateStr) < 7) {
                continue;
            }
            $yr = (int)substr($dateStr, 0, 4);
            $mo = (int)substr($dateStr, 5, 2);
            if ($yr <= 0 || $mo < 1 || $mo > 12) {
                continue;
            }

            $rawUlp    = strtoupper(preg_replace('/\s+/', ' ', trim(str_ireplace('ULP', '', (string)($r['ulp_name'] ?? '')))) ?? '');
            $ulpId     = !empty($r['ulp_id']) ? (int)$r['ulp_id'] : ($ulps['by_clean'][$rawUlp]['id'] ?? null);
            $dispUlp   = $ulpId && isset($ulps['by_id'][$ulpId])
                ? $ulps['by_id'][$ulpId]['clean_name']
                : ($rawUlp !== '' ? $rawUlp : 'TIDAK TERCATAT');

            $rawFeeder  = strtoupper(preg_replace('/\s+/', ' ', trim((string)($r['feeder_name'] ?? ''))) ?? '');
            $fMatch     = $feeders['by_name'][$rawFeeder] ?? null;
            $pid        = !empty($r['penyulang_id']) ? (int)$r['penyulang_id'] : ($fMatch['id'] ?? null);
            $dispFeeder = $fMatch['nama_penyulang'] ?? ($rawFeeder !== '' ? $rawFeeder : 'TIDAK TERCATAT');

            $devTypeRaw = strtoupper(preg_replace('/\s+/', ' ', trim((string)($r['switching_device_type'] ?? ''))) ?? '');
            $devType    = $devTypeRaw !== '' ? $devTypeRaw : 'RECL-PMCB';
            $devName    = strtoupper(preg_replace('/\s+/', ' ', trim((string)($r['device_name'] ?? ''))) ?? '');
            $family     = self::classifyEquipmentFamily($devName, $devType);
            $devLabel   = $devName !== '' ? $devName : ($devType === 'PMT' ? 'PMT' : '(Kosong)');

            $catRaw = strtoupper(trim((string)($r['interruption_category'] ?? '')));
            if ($catRaw === 'PERMANENT' || str_contains($catRaw, 'PERMANEN')) {
                $cat = 'PERMANENT';
            } elseif ($catRaw === 'TEMPORARY' || str_contains($catRaw, 'TEMPORER')) {
                $cat = 'TEMPORARY';
            } else {
                $cat = 'UNKNOWN';
            }

            $relayRaw   = strtoupper(preg_replace('/\s+/', ' ', trim((string)($r['relay_trip_type'] ?? ''))) ?? '');
            $relayLabel = $relayRaw !== '' ? $relayRaw : 'TIDAK TERCATAT';

            $causeRaw = strtoupper(preg_replace('/\s+/', ' ', trim((string)($r['cause_raw'] ?? ''))) ?? '');
            if ($causeRaw === '') {
                $causeRaw = 'TIDAK TERCATAT';
            }

            $cCode = trim((string)($r['cause_canonical_code'] ?? ''));
            $cCat  = trim((string)($r['cause_category'] ?? ''));
            $cStat = trim((string)($r['cause_mapping_status'] ?? ''));
            if ($cCode === '' || $cCode === 'CAUSE_UNMAPPED_ADVISORY') {
                $res   = $this->causeResolver->resolveCause($causeRaw);
                $cCode = $res['cause_canonical_code'];
                $cCat  = $res['cause_category'];
                $cStat = $res['cause_mapping_status'];
            }

            $normalized[] = [
                'id'                      => (int)$r['id'],
                'batch_id'                => (string)($r['batch_id'] ?? ''),
                'source_system'           => (string)($r['source_system'] ?? ''),
                'event_date'              => $dateStr,
                'year'                    => $yr,
                'month'                   => $mo,
                'interruption_started_at' => (string)($r['interruption_started_at'] ?? ''),
                'interruption_ended_at'   => (string)($r['interruption_ended_at'] ?? ''),
                'outage_duration_minutes' => $r['outage_duration_minutes'] !== null ? (float)$r['outage_duration_minutes'] : null,
                'substation_name'         => (string)($r['substation_name'] ?? ''),
                'ulp_id'                  => $ulpId,
                'ulp_name'                => $dispUlp,
                'master_ulp'              => 'ULP ' . $dispUlp,
                'penyulang_id'            => $pid,
                'feeder_name'             => $dispFeeder,
                'master_penyulang'        => $dispFeeder,
                'switching_device_type'   => $devType,
                'device_name'             => $devName,
                'device_label'            => $devLabel,
                'equipment_family'        => $family,
                'relay_trip_type'         => $relayRaw,
                'relay_label'             => $relayLabel,
                'faulted_phase'           => (string)($r['faulted_phase'] ?? ''),
                'weather_condition'       => (string)($r['weather_condition'] ?? ''),
                'fault_current_amperes'   => $r['fault_current_amperes'] !== null ? (float)$r['fault_current_amperes'] : null,
                'energy_not_supplied_kwh' => $r['energy_not_supplied_kwh'] !== null ? (float)$r['energy_not_supplied_kwh'] : null,
                'interruption_category'   => $cat,
                'kategori_raw'            => (string)($r['kategori_raw'] ?? $cat),
                'cause_raw'               => $causeRaw,
                'cause_canonical_code'    => $cCode,
                'cause_category'          => $cCat ?: 'UNKNOWN_INVESTIGATION',
                'cause_mapping_status'    => $cStat ?: 'RESOLVED',
                'field_narrative_raw'     => (string)($r['field_narrative_raw'] ?? ''),
                'restoration_action_raw'  => (string)($r['restoration_action_raw'] ?? ''),
                'extracted_zone_section'  => (string)($r['extracted_zone_section'] ?? ''),
            ];
        }

        $this->allRowsCache = $this->filterActiveSourceRows($normalized);
        return $this->allRowsCache;
    }

    /**
     * Exclude superseded pre-sync spreadsheet snapshot duplicates in synced years
     * while preserving all manual entries, older years, and standalone non-Google-Sheets data.
     */
    protected function filterActiveSourceRows(array $rows): array
    {
        $syncedYears       = [];
        $syncedDateFeeders = [];
        $syncedEventKeys   = [];

        foreach ($rows as $r) {
            if (strtoupper(trim($r['source_system'])) !== GoogleSheetsGangguanSyncService::SOURCE_SYSTEM_SYNC) {
                continue;
            }
            $yr = (int)$r['year'];
            $syncedYears[$yr] = true;
            $dfKey = $r['event_date'] . '|' . strtoupper(trim($r['feeder_name']));
            $syncedDateFeeders[$dfKey] = true;

            $hhmm = strlen((string)$r['interruption_started_at']) >= 16
                ? substr((string)$r['interruption_started_at'], 11, 5)
                : '00:00';
            $syncedEventKeys[$dfKey . '|' . $hhmm] = true;
        }

        if (empty($syncedYears)) {
            return $rows;
        }

        // Detect overlapping legacy EXCEL_WORKBOOK_IMPORT snapshot batches in synced years
        $excelBatchStats = [];
        foreach ($rows as $r) {
            $src = strtoupper(trim($r['source_system']));
            if ($src !== 'EXCEL_WORKBOOK_IMPORT' || empty($syncedYears[$r['year']])) {
                continue;
            }
            $bKey = (string)($r['batch_id'] ?? '');
            if (!isset($excelBatchStats[$bKey])) {
                $excelBatchStats[$bKey] = ['total' => 0, 'matched' => 0];
            }
            $excelBatchStats[$bKey]['total']++;
            $dfKey = $r['event_date'] . '|' . strtoupper(trim($r['feeder_name']));
            if (isset($syncedDateFeeders[$dfKey])) {
                $excelBatchStats[$bKey]['matched']++;
            }
        }

        $overlappingExcelBatches = [];
        foreach ($excelBatchStats as $bKey => $st) {
            if ($st['total'] >= 5 && ($st['matched'] / $st['total']) >= 0.5) {
                $overlappingExcelBatches[$bKey] = true;
            }
        }

        return array_values(array_filter($rows, static function (array $r) use ($syncedYears, $syncedEventKeys, $overlappingExcelBatches): bool {
            $src = strtoupper(trim($r['source_system']));

            if ($src === GoogleSheetsGangguanSyncService::SOURCE_SYSTEM_SYNC || $src === 'SIDAK_DIRECT_ENTRY') {
                return true;
            }

            if (empty($syncedYears[$r['year']])) {
                return true;
            }

            // Legacy Google Sheet snapshots superseded in analytics for years covered by live sync
            if ($src === 'GOOGLE_SPREADSHEET_PLN_SDA' || $src === 'SPREADSHEET_CONTROLLED_IMPORT') {
                return false;
            }

            if ($src === 'EXCEL_WORKBOOK_IMPORT') {
                $bKey = (string)($r['batch_id'] ?? '');
                if (isset($overlappingExcelBatches[$bKey])) {
                    return false;
                }
            }

            // For non-bulk/manual XLSX or NULL/empty sources, keep unless exact event timestamp duplicates live sync
            $dfKey = $r['event_date'] . '|' . strtoupper(trim($r['feeder_name']));
            $hhmm  = strlen((string)$r['interruption_started_at']) >= 16
                ? substr((string)$r['interruption_started_at'], 11, 5)
                : '00:00';
            if ($src === '' && isset($syncedEventKeys[$dfKey . '|' . $hhmm])) {
                return false;
            }
            if ($src === 'EXCEL_WORKBOOK_IMPORT' && isset($syncedEventKeys[$dfKey . '|' . $hhmm])) {
                return false;
            }

            return true;
        }));
    }

    protected function filterRows(
        array $rows,
        ?int $ulpId = null,
        ?int $penyulangId = null,
        ?int $month = null,
        ?string $category = null,
        ?string $relay = null,
        ?string $cause = null
    ): array {
        $ulpTargetClean    = null;
        $feederTargetUpper = null;

        if ($ulpId !== null) {
            $ulps = $this->getUlpCatalog();
            $ulpTargetClean = $ulps['by_id'][$ulpId]['clean_name'] ?? null;
        }
        if ($penyulangId !== null && $penyulangId > 0) {
            $feeders = $this->getFeederCatalog();
            $feederTargetUpper = $feeders['by_id'][$penyulangId]['upper_name'] ?? null;
        }

        return array_values(array_filter($rows, static function (array $r) use ($ulpId, $ulpTargetClean, $penyulangId, $feederTargetUpper, $month, $category, $relay, $cause): bool {
            if ($ulpId !== null) {
                $matchUlpId   = ($r['ulp_id'] !== null && $r['ulp_id'] === $ulpId);
                $matchUlpName = ($ulpTargetClean !== null && str_contains($r['ulp_name'], $ulpTargetClean));
                if (!$matchUlpId && !$matchUlpName) {
                    return false;
                }
            }
            if ($penyulangId !== null && $penyulangId > 0) {
                $matchPid   = ($r['penyulang_id'] !== null && $r['penyulang_id'] === $penyulangId);
                $matchFName = ($feederTargetUpper !== null && strtoupper($r['feeder_name']) === $feederTargetUpper);
                if (!$matchPid && !$matchFName) {
                    return false;
                }
            }
            if ($month !== null && $r['month'] !== $month) {
                return false;
            }
            if ($category !== null && $category !== '' && $r['interruption_category'] !== $category) {
                return false;
            }
            if ($relay !== null && $relay !== '' && $r['relay_label'] !== $relay) {
                return false;
            }
            if ($cause !== null && $cause !== '' && $r['cause_raw'] !== $cause) {
                return false;
            }
            return true;
        }));
    }

    protected function countMonthly(array $rows): array
    {
        $monthly = array_fill(1, 12, 0);
        foreach ($rows as $r) {
            $monthly[$r['month']]++;
        }
        return $monthly;
    }

    protected function countByCategory(array $rows): array
    {
        $perm = 0;
        $temp = 0;
        $unk  = 0;
        foreach ($rows as $r) {
            if ($r['interruption_category'] === 'PERMANENT') {
                $perm++;
            } elseif ($r['interruption_category'] === 'TEMPORARY') {
                $temp++;
            } else {
                $unk++;
            }
        }
        $tot = $perm + $temp + $unk;
        return [
            'TEMPORARY' => $temp,
            'PERMANENT' => $perm,
            'UNKNOWN'   => $unk,
            'total'     => $tot,
            'temp_pct'  => $tot > 0 ? round(($temp / $tot) * 100, 1) : 0.0,
            'perm_pct'  => $tot > 0 ? round(($perm / $tot) * 100, 1) : 0.0,
            'unk_pct'   => $tot > 0 ? round(($unk / $tot) * 100, 1) : 0.0,
        ];
    }

    protected function buildUlpComparison(array $rowsFocus, array $rowsA, array $rowsB): array
    {
        $countsF = [];
        $countsA = [];
        $countsB = [];
        foreach ($rowsFocus as $r) {
            $countsF[$r['ulp_name']] = ($countsF[$r['ulp_name']] ?? 0) + 1;
        }
        foreach ($rowsA as $r) {
            $countsA[$r['ulp_name']] = ($countsA[$r['ulp_name']] ?? 0) + 1;
        }
        foreach ($rowsB as $r) {
            $countsB[$r['ulp_name']] = ($countsB[$r['ulp_name']] ?? 0) + 1;
        }

        $allUlps = array_unique(array_merge(array_keys($countsF), array_keys($countsA), array_keys($countsB)));
        usort($allUlps, static fn(string $u1, string $u2): int => ($countsF[$u2] ?? 0) <=> ($countsF[$u1] ?? 0) ?: strcmp($u1, $u2));

        $rows = [];
        foreach ($allUlps as $u) {
            $rows[] = [
                'ulp'     => $u,
                'focus'   => $countsF[$u] ?? 0,
                'year_a'  => $countsA[$u] ?? 0,
                'year_b'  => $countsB[$u] ?? 0,
            ];
        }

        return [
            'labels'        => $allUlps,
            'focus_counts'  => array_map(static fn(string $u): int => $countsF[$u] ?? 0, $allUlps),
            'year_a_counts' => array_map(static fn(string $u): int => $countsA[$u] ?? 0, $allUlps),
            'year_b_counts' => array_map(static fn(string $u): int => $countsB[$u] ?? 0, $allUlps),
            'rows'          => $rows,
        ];
    }

    protected function buildCategoryByUlp(array $rows): array
    {
        $byUlp = [];
        foreach ($rows as $r) {
            $u = $r['ulp_name'];
            if (!isset($byUlp[$u])) {
                $byUlp[$u] = ['ulp' => $u, 'TEMPORARY' => 0, 'PERMANENT' => 0, 'UNKNOWN' => 0, 'total' => 0];
            }
            $byUlp[$u][$r['interruption_category']]++;
            $byUlp[$u]['total']++;
        }
        uasort($byUlp, static fn(array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['ulp'], $b['ulp']));
        $list = array_values($byUlp);

        return [
            'labels'   => array_column($list, 'ulp'),
            'temporer' => array_column($list, 'TEMPORARY'),
            'permanen' => array_column($list, 'PERMANENT'),
            'unknown'  => array_column($list, 'UNKNOWN'),
            'rows'     => $list,
        ];
    }

    protected function buildRelayComparison(array $rowsFocus, array $rowsA, array $rowsB): array
    {
        $countsF = [];
        $countsA = [];
        $countsB = [];
        foreach ($rowsFocus as $r) {
            $countsF[$r['relay_label']] = ($countsF[$r['relay_label']] ?? 0) + 1;
        }
        foreach ($rowsA as $r) {
            $countsA[$r['relay_label']] = ($countsA[$r['relay_label']] ?? 0) + 1;
        }
        foreach ($rowsB as $r) {
            $countsB[$r['relay_label']] = ($countsB[$r['relay_label']] ?? 0) + 1;
        }

        $keys = array_unique(array_merge(array_keys($countsF), array_keys($countsA), array_keys($countsB)));
        usort($keys, static fn(string $k1, string $k2): int => ($countsF[$k2] ?? 0) <=> ($countsF[$k1] ?? 0) ?: strcmp($k1, $k2));

        $rows = [];
        foreach ($keys as $k) {
            $rows[] = [
                'relay'  => $k,
                'focus'  => $countsF[$k] ?? 0,
                'year_a' => $countsA[$k] ?? 0,
                'year_b' => $countsB[$k] ?? 0,
            ];
        }

        return [
            'labels'        => $keys,
            'focus_counts'  => array_map(static fn(string $k): int => $countsF[$k] ?? 0, $keys),
            'year_a_counts' => array_map(static fn(string $k): int => $countsA[$k] ?? 0, $keys),
            'year_b_counts' => array_map(static fn(string $k): int => $countsB[$k] ?? 0, $keys),
            'rows'          => $rows,
        ];
    }

    protected function buildSwitchingTypeComparison(array $rowsFocus, array $rowsA, array $rowsB): array
    {
        $countsF = [];
        $countsA = [];
        $countsB = [];
        foreach ($rowsFocus as $r) {
            $countsF[$r['switching_device_type']] = ($countsF[$r['switching_device_type']] ?? 0) + 1;
        }
        foreach ($rowsA as $r) {
            $countsA[$r['switching_device_type']] = ($countsA[$r['switching_device_type']] ?? 0) + 1;
        }
        foreach ($rowsB as $r) {
            $countsB[$r['switching_device_type']] = ($countsB[$r['switching_device_type']] ?? 0) + 1;
        }

        $keys = array_unique(array_merge(['RECL-PMCB', 'PMT'], array_keys($countsF), array_keys($countsA), array_keys($countsB)));
        $keys = array_values(array_filter($keys, static fn(string $k): bool => ($countsF[$k] ?? 0) > 0 || ($countsA[$k] ?? 0) > 0 || ($countsB[$k] ?? 0) > 0));
        usort($keys, static fn(string $k1, string $k2): int => ($countsF[$k2] ?? 0) <=> ($countsF[$k1] ?? 0));

        $totalF = array_sum($countsF);
        $rows = [];
        foreach ($keys as $k) {
            $cntF = $countsF[$k] ?? 0;
            $rows[] = [
                'type'      => $k,
                'label'     => $k === 'PMT' ? 'PMT (Gardu Induk)' : ($k === 'RECL-PMCB' ? 'REC / PMCB (Jaringan JTM)' : $k),
                'focus'     => $cntF,
                'focus_pct' => $totalF > 0 ? round(($cntF / $totalF) * 100, 1) : 0.0,
                'year_a'    => $countsA[$k] ?? 0,
                'year_b'    => $countsB[$k] ?? 0,
            ];
        }

        return [
            'labels'        => array_column($rows, 'label'),
            'keys'          => $keys,
            'focus_counts'  => array_column($rows, 'focus'),
            'focus_pcts'    => array_column($rows, 'focus_pct'),
            'year_a_counts' => array_column($rows, 'year_a'),
            'year_b_counts' => array_column($rows, 'year_b'),
            'focus_total'   => $totalF,
            'rows'          => $rows,
        ];
    }

    protected function buildParetoCauses(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $r) {
            $raw = $r['cause_raw'];
            if (!isset($grouped[$raw])) {
                $cCode = $r['cause_canonical_code'];
                $meta  = self::CANONICAL_CAUSE_META[$cCode] ?? ['category' => $r['cause_category'], 'label' => $cCode];
                $grouped[$raw] = [
                    'cause_raw'      => $raw,
                    'canonical_code' => $cCode,
                    'category'       => $r['cause_category'],
                    'category_label' => $meta['label'],
                    'count'          => 0,
                ];
            }
            $grouped[$raw]['count']++;
        }

        $items = array_values($grouped);
        usort($items, static fn(array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['cause_raw'], $b['cause_raw']));

        $total        = (int)array_sum(array_column($items, 'count'));
        $running      = 0;
        $sumPct       = 0.0;
        $vitalCount   = 0;
        $vitalEvents  = 0;
        $vitalLastPct = 0.0;

        foreach ($items as $idx => &$item) {
            // Exact integer cross-multiplication avoids IEEE-754 drift at 80.0% cutoff
            $isVital = $total > 0 && (($running * 100) < ($total * 80));
            $running += $item['count'];
            $pct      = $total > 0 ? round(($item['count'] / $total) * 100, 1) : 0.0;
            $cumPct   = $total > 0 ? min(100.0, round(($running / $total) * 100, 1)) : 0.0;
            $sumPct   = round($sumPct + $pct, 1);

            $item['rank']             = $idx + 1;
            $item['pct']              = $pct;
            $item['cumulative_count'] = $running;
            $item['cumulative_pct']   = $cumPct;
            $item['is_vital_80']      = $isVital;

            if ($isVital) {
                $vitalCount++;
                $vitalEvents  = $running;
                $vitalLastPct = $cumPct;
            }
        }
        unset($item);

        $finalCum = !empty($items) ? end($items)['cumulative_pct'] : 0.0;

        return [
            'total'                => $total,
            'category_count'       => count($items),
            'sum_pct'              => $sumPct,
            'final_cumulative_pct' => $finalCum,
            'vital_80_count'       => $vitalCount,
            'vital_80_events'      => $vitalEvents,
            'vital_80_pct'         => $vitalLastPct,
            'threshold_pct'        => 80.0,
            'items'                => $items,
        ];
    }

    protected function buildTop10Feeders(array $rowsFocus, array $rowsA, array $rowsB, int $limit = 10): array
    {
        $cntF = [];
        $cntA = [];
        $cntB = [];
        $meta = [];

        foreach ($rowsFocus as $r) {
            $f = $r['feeder_name'];
            $cntF[$f] = ($cntF[$f] ?? 0) + 1;
            $meta[$f] ??= ['penyulang_id' => $r['penyulang_id'], 'nama_ulp' => $r['master_ulp']];
        }
        foreach ($rowsA as $r) {
            $f = $r['feeder_name'];
            $cntA[$f] = ($cntA[$f] ?? 0) + 1;
            $meta[$f] ??= ['penyulang_id' => $r['penyulang_id'], 'nama_ulp' => $r['master_ulp']];
        }
        foreach ($rowsB as $r) {
            $f = $r['feeder_name'];
            $cntB[$f] = ($cntB[$f] ?? 0) + 1;
            $meta[$f] ??= ['penyulang_id' => $r['penyulang_id'], 'nama_ulp' => $r['master_ulp']];
        }

        arsort($cntF);
        $topKeys = array_slice(array_keys($cntF), 0, $limit);

        $rows = [];
        foreach ($topKeys as $f) {
            $a = $cntA[$f] ?? 0;
            $b = $cntB[$f] ?? 0;
            $rows[] = [
                'penyulang_id'   => (int)($meta[$f]['penyulang_id'] ?? 0),
                'nama_penyulang' => $f,
                'feeder_name'    => $f,
                'nama_ulp'       => $meta[$f]['nama_ulp'] ?? '—',
                'cnt'            => $cntF[$f] ?? 0,
                'cnt_focus'      => $cntF[$f] ?? 0,
                'cnt_a'          => $a,
                'cnt_b'          => $b,
                'selisih'        => $a - $b,
            ];
        }

        return ['rows' => $rows];
    }

    protected function buildDeviceDistribution(array $rows): array
    {
        $byDevice = [];
        foreach ($rows as $r) {
            $key = $r['device_label'] . '|' . $r['equipment_family'];
            if (!isset($byDevice[$key])) {
                $byDevice[$key] = [
                    'device_name'           => $r['device_label'],
                    'equipment_family'      => $r['equipment_family'],
                    'switching_device_type' => $r['switching_device_type'],
                    'ulps'                  => [],
                    'count'                 => 0,
                ];
            }
            $byDevice[$key]['count']++;
            $byDevice[$key]['ulps'][$r['ulp_name']] = true;
        }

        $all = [];
        foreach ($byDevice as $item) {
            $item['ulp_name'] = implode(', ', array_keys($item['ulps']));
            unset($item['ulps']);
            $all[] = $item;
        }
        usort($all, static fn(array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['device_name'], $b['device_name']));

        $nonPmt = array_values(array_filter($all, static fn(array $d): bool => $d['equipment_family'] !== 'PMT'));

        return [
            'all_devices'     => $all,
            'non_pmt_devices' => $nonPmt,
        ];
    }

    protected function buildFeederMonthlyPivot(array $rowsCombined, int $yearA, int $yearB): array
    {
        $years = ($yearA === $yearB) ? [$yearA] : [$yearA, $yearB];
        $pivot = [];
        $moTotalsA = array_fill(1, 12, 0);
        $moTotalsB = array_fill(1, 12, 0);

        foreach ($rowsCombined as $r) {
            $key = $r['feeder_name'] . '|' . $r['ulp_name'] . '|' . $r['device_label'];
            if (!isset($pivot[$key])) {
                $pivot[$key] = [
                    'feeder_name' => $r['feeder_name'],
                    'ulp_name'    => $r['ulp_name'],
                    'device_name' => $r['device_label'],
                    'months_a'    => array_fill(1, 12, 0),
                    'months_b'    => array_fill(1, 12, 0),
                    'total_a'     => 0,
                    'total_b'     => 0,
                    'total'       => 0,
                ];
            }
            if ($r['year'] === $yearA) {
                $pivot[$key]['months_a'][$r['month']]++;
                $pivot[$key]['total_a']++;
                $pivot[$key]['total']++;
                $moTotalsA[$r['month']]++;
            } elseif ($r['year'] === $yearB) {
                $pivot[$key]['months_b'][$r['month']]++;
                $pivot[$key]['total_b']++;
                $pivot[$key]['total']++;
                $moTotalsB[$r['month']]++;
            }
        }

        $rows = array_values($pivot);
        usort($rows, static fn(array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['feeder_name'], $b['feeder_name']));

        return [
            'years'                => $years,
            'rows'                 => $rows,
            'month_totals_a'       => $moTotalsA,
            'month_totals_b'       => $moTotalsB,
            'grand_total_a'        => array_sum($moTotalsA),
            'grand_total_b'        => array_sum($moTotalsB),
            'grand_total_combined' => array_sum($moTotalsA) + ($yearA === $yearB ? 0 : array_sum($moTotalsB)),
        ];
    }

    protected function buildFeederVsCausePivot(array $rows): array
    {
        $causeTotals = [];
        $pivot = [];

        foreach ($rows as $r) {
            $c = $r['cause_raw'];
            $causeTotals[$c] = ($causeTotals[$c] ?? 0) + 1;

            $key = $r['feeder_name'] . '|' . $r['ulp_name'] . '|' . $r['device_label'];
            if (!isset($pivot[$key])) {
                $pivot[$key] = [
                    'feeder_name' => $r['feeder_name'],
                    'ulp_name'    => $r['ulp_name'],
                    'device_name' => $r['device_label'],
                    'causes'      => [],
                    'total'       => 0,
                ];
            }
            $pivot[$key]['causes'][$c] = ($pivot[$key]['causes'][$c] ?? 0) + 1;
            $pivot[$key]['total']++;
        }

        arsort($causeTotals);
        $causes = array_keys($causeTotals);

        $pivotRows = array_values($pivot);
        usort($pivotRows, static fn(array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['feeder_name'], $b['feeder_name']));

        return [
            'causes'       => $causes,
            'cause_totals' => $causeTotals,
            'rows'         => $pivotRows,
            'grand_total'  => array_sum($causeTotals),
        ];
    }

    protected function buildEquipmentMonthlyPivot(array $rows): array
    {
        $familiesOrder = ['PMT', 'Recloser', 'PMCB', 'Key Point', 'Lainnya'];
        $familyPivot   = [];
        foreach ($familiesOrder as $fam) {
            $familyPivot[$fam] = [
                'family' => $fam,
                'months' => array_fill(1, 12, 0),
                'total'  => 0,
            ];
        }

        $detailPivot = [];
        $monthTotals = array_fill(1, 12, 0);

        foreach ($rows as $r) {
            $fam = $r['equipment_family'];
            if (!isset($familyPivot[$fam])) {
                $familyPivot[$fam] = ['family' => $fam, 'months' => array_fill(1, 12, 0), 'total' => 0];
            }
            $familyPivot[$fam]['months'][$r['month']]++;
            $familyPivot[$fam]['total']++;
            $monthTotals[$r['month']]++;

            $key = $fam . '|' . $r['device_label'] . '|' . $r['ulp_name'];
            if (!isset($detailPivot[$key])) {
                $detailPivot[$key] = [
                    'equipment_family' => $fam,
                    'device_name'      => $r['device_label'],
                    'ulp_name'         => $r['ulp_name'],
                    'months'           => array_fill(1, 12, 0),
                    'total'            => 0,
                ];
            }
            $detailPivot[$key]['months'][$r['month']]++;
            $detailPivot[$key]['total']++;
        }

        $familySummary = array_values(array_filter(
            $familyPivot,
            static fn(array $f): bool => $f['total'] > 0 || $f['family'] !== 'Lainnya'
        ));

        $detailRows = array_values($detailPivot);
        usort($detailRows, static fn(array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['device_name'], $b['device_name']));

        return [
            'family_summary' => $familySummary,
            'rows'           => $detailRows,
            'month_totals'   => $monthTotals,
            'grand_total'    => array_sum($monthTotals),
        ];
    }

    protected function buildDashboardCauseBreakdown(array $rows, array $allTrendYears, array $availableInterruptionYears): array
    {
        $canonicalMap = self::CANONICAL_CAUSE_META;
        if ($this->db->tableExists('cause_code_dictionary')) {
            $dictRows = $this->db->table('cause_code_dictionary')
                ->select('canonical_cause_code, MAX(cause_category) AS cat, MIN(cause_label) AS lbl')
                ->where('is_active', 1)
                ->groupBy('canonical_cause_code')
                ->get()
                ->getResultArray();
            foreach ($dictRows as $dr) {
                $cCode = (string)$dr['canonical_cause_code'];
                if ($cCode !== '' && !isset($canonicalMap[$cCode])) {
                    $canonicalMap[$cCode] = [
                        'category' => (string)$dr['cat'],
                        'label'    => (string)$dr['lbl'],
                        'badge'    => 'info',
                    ];
                }
            }
        }

        $years = array_values(array_unique(array_merge($allTrendYears, $availableInterruptionYears)));
        rsort($years);

        $groupedByYear = [];
        foreach ($years as $yr) {
            $groupedByYear[(int)$yr] = [];
        }

        foreach ($rows as $r) {
            $yr     = $r['year'];
            $code   = $r['cause_canonical_code'] ?: 'CAUSE_UNKNOWN_UNDER_INVESTIGATION';
            $status = $r['cause_mapping_status'] ?: 'RESOLVED';
            $meta   = $canonicalMap[$code] ?? [
                'category' => $r['cause_category'],
                'label'    => 'Kode Belum Terpetakan: ' . $code,
                'badge'    => 'dark',
            ];
            $key = $code . '|' . $status;
            if (!isset($groupedByYear[$yr][$key])) {
                $groupedByYear[$yr][$key] = [
                    'canonical_code' => $code,
                    'category'       => $meta['category'],
                    'label'          => $meta['label'],
                    'badge'          => $meta['badge'],
                    'mapping_status' => $status,
                    'raw_samples_map'=> [],
                    'count'          => 0,
                ];
            }
            $groupedByYear[$yr][$key]['count']++;
            if ($r['cause_raw'] !== '') {
                $groupedByYear[$yr][$key]['raw_samples_map'][$r['cause_raw']] = true;
            }
        }

        $result = [];
        foreach ($groupedByYear as $yr => $bucket) {
            $list = array_values($bucket);
            usort($list, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);
            $totYr = (int)array_sum(array_column($list, 'count'));

            foreach ($list as &$it) {
                $samples = array_keys($it['raw_samples_map']);
                sort($samples);
                $it['raw_samples'] = implode(', ', $samples);
                unset($it['raw_samples_map']);
                $it['total'] = $it['count'];
                $it['pct']   = $totYr > 0 ? round(($it['count'] / $totYr) * 100, 1) : 0.0;
            }
            unset($it);

            $hasSource = in_array((int)$yr, $availableInterruptionYears, true);
            $result[(int)$yr] = [
                'year'         => (int)$yr,
                'total_events' => $totYr,
                'has_source'   => $hasSource,
                'source_note'  => $hasSource
                    ? null
                    : 'Tahun ' . (int)$yr . ' tercatat pada data Temuan Inspeksi, namun belum ada histori trip pada sumber data Gangguan Penyulang.',
                'items'        => $list,
            ];
        }

        return $result;
    }

    protected function getUlpCatalog(): array
    {
        if ($this->ulpCatalogCache !== null) {
            return $this->ulpCatalogCache;
        }
        $byId    = [];
        $byClean = [];
        if ($this->db->tableExists('ulps')) {
            $rows = $this->db->table('ulps')->select('id, nama_ulp')->get()->getResultArray();
            foreach ($rows as $r) {
                $id    = (int)$r['id'];
                $clean = strtoupper(trim(str_ireplace('ULP', '', (string)$r['nama_ulp'])));
                $entry = ['id' => $id, 'nama_ulp' => (string)$r['nama_ulp'], 'clean_name' => $clean];
                $byId[$id] = $entry;
                if ($clean !== '') {
                    $byClean[$clean] = $entry;
                }
            }
        }
        $this->ulpCatalogCache = ['by_id' => $byId, 'by_clean' => $byClean];
        return $this->ulpCatalogCache;
    }

    protected function getFeederCatalog(): array
    {
        if ($this->feederCatalogCache !== null) {
            return $this->feederCatalogCache;
        }
        $byId   = [];
        $byName = [];
        if ($this->db->tableExists('penyulang')) {
            $rows = $this->db->table('penyulang')->select('id, nama_penyulang, ulp_id')->get()->getResultArray();
            foreach ($rows as $r) {
                $id    = (int)$r['id'];
                $upper = strtoupper(trim((string)$r['nama_penyulang']));
                $entry = ['id' => $id, 'nama_penyulang' => (string)$r['nama_penyulang'], 'upper_name' => $upper, 'ulp_id' => (int)($r['ulp_id'] ?? 0)];
                $byId[$id] = $entry;
                if ($upper !== '') {
                    $byName[$upper] = $entry;
                }
            }
        }
        $this->feederCatalogCache = ['by_id' => $byId, 'by_name' => $byName];
        return $this->feederCatalogCache;
    }

    /**
     * Unified drill-down details endpoint shared by all `/gangguan` and `/dashboard` charts.
     * Strictly uses the same filtered row pool and field definitions as the charts themselves.
     */
    public function getDrilldownDetails(array $ctx, string $dimension, string $key, array $extra = []): array
    {
        $yearA = (int)$ctx['year_a'];
        $yearB = (int)$ctx['year_b'];
        $scope = (string)($ctx['scope'] ?? 'year_a');
        $ulpId = $ctx['ulp_id'];
        $pid   = $ctx['penyulang_id'];

        $scopedRows = $this->filterRows(
            $this->loadAllNormalizedRows(),
            $ulpId,
            $pid,
            $ctx['month'] ?? null,
            $ctx['category'] ?? null,
            $ctx['relay'] ?? null,
            $ctx['cause'] ?? null
        );

        $targetYear = !empty($extra['target_year']) ? (int)$extra['target_year'] : null;
        if ($targetYear !== null && $targetYear > 0) {
            $yearPoolRows = array_values(array_filter($scopedRows, static fn(array $r): bool => $r['year'] === $targetYear));
            $yearLabel    = 'Tahun ' . $targetYear;
        } elseif ($scope === 'year_b') {
            $yearPoolRows = array_values(array_filter($scopedRows, static fn(array $r): bool => $r['year'] === $yearB));
            $yearLabel    = 'Tahun ' . $yearB;
        } elseif ($scope === 'combined') {
            $yearPoolRows = array_values(array_filter($scopedRows, static fn(array $r): bool => $r['year'] === $yearA || $r['year'] === $yearB));
            $yearLabel    = ($yearA === $yearB) ? ('Tahun ' . $yearA) : ("Gabungan {$yearA} & {$yearB}");
        } else {
            $yearPoolRows = array_values(array_filter($scopedRows, static fn(array $r): bool => $r['year'] === $yearA));
            $yearLabel    = 'Tahun ' . $yearA;
        }

        $totalScope     = count($yearPoolRows);
        $dimUpper       = strtolower(trim($dimension));
        $keyUpper       = strtoupper(preg_replace('/\s+/', ' ', trim($key)) ?? '');
        $subKey         = trim((string)($extra['sub_key'] ?? ''));
        $offset         = max(0, (int)($extra['offset'] ?? 0));
        $limit          = max(10, min(1000, (int)($extra['limit'] ?? 100)));
        $search         = trim((string)($extra['search'] ?? ''));
        $eventCategory  = strtoupper(trim((string)($extra['event_category'] ?? '')));
        $eventUlp       = strtoupper(preg_replace('/\s+/', ' ', trim(str_ireplace('ULP', '', (string)($extra['event_ulp'] ?? '')))) ?? '');
        $paretoSequence = [];
        $paretoPoint    = null;
        $title          = 'Detail Gangguan';
        $subtitle       = $yearLabel;

        $matchingRows = [];

        switch ($dimUpper) {
            case 'category':
                $catTarget = match ($keyUpper) {
                    'TEMPORER', 'TEMPORARY' => 'TEMPORARY',
                    'PERMANEN', 'PERMANENT' => 'PERMANENT',
                    default => $keyUpper,
                };
                $matchingRows = array_values(array_filter($yearPoolRows, static fn(array $r): bool => $r['interruption_category'] === $catTarget));
                $title        = 'Kategori: ' . ($catTarget === 'TEMPORARY' ? 'Temporer' : ($catTarget === 'PERMANENT' ? 'Permanen' : $catTarget));
                $subtitle     = "Distribusi berdasarkan kolom KATEGORI • {$yearLabel}";
                break;

            case 'ulp':
                $matchingRows = array_values(array_filter($yearPoolRows, static fn(array $r): bool => strtoupper($r['ulp_name']) === $keyUpper));
                $title        = 'ULP ' . $keyUpper;
                $subtitle     = "Rincian kejadian berdasarkan kolom ULP • {$yearLabel}";
                break;

            case 'ulp_category':
                $parts     = explode('|', $key, 2);
                $ulpTarget = strtoupper(preg_replace('/\s+/', ' ', trim($parts[0] ?? '')) ?? '');
                $catPart   = strtoupper(trim($parts[1] ?? $subKey));
                $catTarget = ($catPart === 'TEMPORER' || $catPart === 'TEMPORARY') ? 'TEMPORARY' : (($catPart === 'PERMANEN' || $catPart === 'PERMANENT') ? 'PERMANENT' : $catPart);
                $matchingRows = array_values(array_filter(
                    $yearPoolRows,
                    static fn(array $r): bool => strtoupper($r['ulp_name']) === $ulpTarget && $r['interruption_category'] === $catTarget
                ));
                $catLabel = $catTarget === 'TEMPORARY' ? 'Temporer' : ($catTarget === 'PERMANENT' ? 'Permanen' : $catTarget);
                $title    = "ULP {$ulpTarget} — {$catLabel}";
                $subtitle = "Irisan ULP x Kategori • {$yearLabel}";
                break;

            case 'relay':
                $matchingRows = array_values(array_filter($yearPoolRows, static fn(array $r): bool => strtoupper($r['relay_label']) === $keyUpper));
                $title        = 'Relai Proteksi: ' . $keyUpper;
                $subtitle     = "Rincian kerja proteksi (RELE KERJA) • {$yearLabel}";
                break;

            case 'switching_type':
                $typeTarget = ($keyUpper === 'PMT' || str_contains($keyUpper, 'PMT (GARDU INDUK)')) ? 'PMT' : ((str_contains($keyUpper, 'RECL')) ? 'RECL-PMCB' : $keyUpper);
                $matchingRows = array_values(array_filter($yearPoolRows, static fn(array $r): bool => strtoupper($r['switching_device_type']) === $typeTarget));
                $title        = $typeTarget === 'PMT' ? 'Peralatan PMT (Gardu Induk)' : 'Peralatan REC / PMCB (Jaringan JTM)';
                $subtitle     = "Rasio peralatan switching (PMT-RECL/PMCB) • {$yearLabel}";
                break;

            case 'month':
                $mo = max(1, min(12, (int)$key));
                $matchingRows = array_values(array_filter($yearPoolRows, static fn(array $r): bool => $r['month'] === $mo));
                $moName   = self::MONTH_LABELS[$mo - 1] ?? ('Bulan ' . $mo);
                $title    = "Bulan {$moName} ({$yearLabel})";
                $subtitle = (($extra['metric_focus'] ?? '') === 'current')
                    ? "Rincian arus gangguan & kejadian trip bulan {$moName} • {$yearLabel}"
                    : "Distribusi kejadian trip bulan {$moName} • {$yearLabel}";
                break;

            case 'pareto_cause':
                $matchingRows = array_values(array_filter($yearPoolRows, static fn(array $r): bool => strtoupper($r['cause_raw']) === $keyUpper));
                $pareto       = $this->buildParetoCauses($yearPoolRows);
                foreach ($pareto['items'] as $pItem) {
                    if (strtoupper($pItem['cause_raw']) === $keyUpper) {
                        $paretoPoint = $pItem;
                        break;
                    }
                }
                $title    = 'Pareto Penyebab: ' . $keyUpper;
                $subtitle = $paretoPoint
                    ? sprintf(
                        'Peringkat #%d dari %d • %s • Kumulatif %s%% (%s)',
                        $paretoPoint['rank'],
                        $pareto['category_count'],
                        $paretoPoint['category_label'],
                        number_format($paretoPoint['cumulative_pct'], 1),
                        $paretoPoint['is_vital_80'] ? '80% Vital Few' : 'Tail 20%'
                    )
                    : "Kode sumber PENYEBAB SESUAI KODE GANGGUAN • {$yearLabel}";
                break;

            case 'pareto_cumulative':
                $pareto = $this->buildParetoCauses($yearPoolRows);
                $items  = $pareto['items'];
                $rank   = (int)($extra['rank'] ?? (is_numeric($key) ? (int)$key : 0));
                if ($rank <= 0) {
                    foreach ($items as $pItem) {
                        if (strtoupper($pItem['cause_raw']) === $keyUpper) {
                            $rank = (int)$pItem['rank'];
                            break;
                        }
                    }
                }
                $rank = max(1, min(count($items), $rank));
                $paretoSequence = array_slice($items, 0, $rank);
                $paretoPoint    = $paretoSequence[$rank - 1] ?? null;
                $allowedCauses  = array_flip(array_map(static fn(array $it): string => strtoupper($it['cause_raw']), $paretoSequence));
                $matchingRows   = array_values(array_filter($yearPoolRows, static fn(array $r): bool => isset($allowedCauses[strtoupper($r['cause_raw'])])));
                $title          = $paretoPoint
                    ? "Titik Kumulatif Pareto #{$rank}: {$paretoPoint['cause_raw']}"
                    : "Titik Kumulatif Pareto #{$rank}";
                $subtitle = $paretoPoint
                    ? sprintf(
                        'Kumulatif %d dari %d kategori = %d / %d kejadian (%.1f%%) • %s',
                        $rank,
                        $pareto['category_count'],
                        $paretoPoint['cumulative_count'],
                        $totalScope,
                        $paretoPoint['cumulative_pct'],
                        $paretoPoint['is_vital_80'] ? 'Masuk zona Vital <= 80%' : 'Setelah ambang batas 80%'
                    )
                    : "Urutan akumulasi Pareto • {$yearLabel}";
                break;

            case 'feeder':
                $matchingRows = array_values(array_filter($yearPoolRows, static fn(array $r): bool => strtoupper($r['feeder_name']) === $keyUpper));
                $title        = 'Penyulang: ' . $keyUpper;
                $subtitle     = "Rincian gangguan penyulang (PENYULANG) • {$yearLabel}";
                break;

            case 'device':
                $famUpper = strtoupper($subKey);
                $matchingRows = array_values(array_filter(
                    $yearPoolRows,
                    static fn(array $r): bool => strtoupper($r['device_label']) === $keyUpper && ($famUpper === '' || strtoupper($r['equipment_family']) === $famUpper)
                ));
                $title    = 'Peralatan: ' . $keyUpper;
                $subtitle = ($famUpper !== '' ? "Famili {$famUpper} • " : '') . "Titik peralatan switching (RECLOSER) • {$yearLabel}";
                break;

            case 'cause_category':
                $matchingRows = array_values(array_filter(
                    $yearPoolRows,
                    static fn(array $r): bool => strtoupper($r['cause_category'] ?: 'UNKNOWN_INVESTIGATION') === $keyUpper
                ));
                $title    = 'Kategori Kanonik: ' . $keyUpper;
                $subtitle = "Kelompok kode penyebab gangguan terpetakan • {$yearLabel}";
                break;

            case 'canonical_cause':
                $matchingRows = array_values(array_filter(
                    $yearPoolRows,
                    static fn(array $r): bool => strtoupper($r['cause_canonical_code']) === $keyUpper
                ));
                $meta     = self::CANONICAL_CAUSE_META[$keyUpper] ?? null;
                $title    = $meta ? $meta['label'] : $keyUpper;
                $subtitle = "Kode kanonik {$keyUpper} • {$yearLabel}";
                break;

            case 'ens_month':
                $mo           = max(1, min(12, (int)$key));
                $matchingRows = array_values(array_filter($yearPoolRows, static fn(array $r): bool => $r['month'] === $mo));
                usort($matchingRows, static function (array $a, array $b): int {
                    $cmp = ((float)($b['energy_not_supplied_kwh'] ?? 0) <=> (float)($a['energy_not_supplied_kwh'] ?? 0));
                    return $cmp !== 0 ? $cmp : strcmp((string)$b['interruption_started_at'], (string)$a['interruption_started_at']);
                });
                $moName   = self::MONTH_LABELS[$mo - 1] ?? ('Bulan ' . $mo);
                $moKwh    = round(array_sum(array_map(static fn(array $r): float => (float)($r['energy_not_supplied_kwh'] ?? 0), $matchingRows)), 2);
                $title    = "ENS (kWh) Bulan {$moName} ({$yearLabel})";
                $subtitle = 'Total ENS: ' . number_format($moKwh, 2, ',', '.') . ' kWh dari ' . count($matchingRows) . ' trip • Kolom ( Kwh ): √3 × 20 kV × I × 0,85 × jam';
                break;

            case 'ens_year':
                if ($keyUpper === 'COMBINED' || $keyUpper === 'GABUNGAN') {
                    $matchingRows = array_values(array_filter(
                        $scopedRows,
                        static fn(array $r): bool => $r['year'] === $yearA || $r['year'] === $yearB
                    ));
                    $yrLabel = ($yearA === $yearB) ? "Tahun {$yearA}" : "Gabungan {$yearA} & {$yearB}";
                } else {
                    $yrTarget     = is_numeric($key) && (int)$key > 0 ? (int)$key : ($targetYear ?: $yearA);
                    $matchingRows = array_values(array_filter($scopedRows, static fn(array $r): bool => $r['year'] === $yrTarget));
                    $yrLabel      = "Tahun {$yrTarget}";
                }
                $totalScope = count($matchingRows);
                usort($matchingRows, static function (array $a, array $b): int {
                    $cmp = ((float)($b['energy_not_supplied_kwh'] ?? 0) <=> (float)($a['energy_not_supplied_kwh'] ?? 0));
                    return $cmp !== 0 ? $cmp : strcmp((string)$b['interruption_started_at'], (string)$a['interruption_started_at']);
                });
                $yrKwh        = round(array_sum(array_map(static fn(array $r): float => (float)($r['energy_not_supplied_kwh'] ?? 0), $matchingRows)), 2);
                $yrMonthlySum = ($keyUpper === 'COMBINED' || $keyUpper === 'GABUNGAN')
                    ? round(
                        $this->summarizeEnsByMonth(array_filter($matchingRows, static fn(array $r): bool => $r['year'] === $yearA))['total_kwh']
                        + ($yearA === $yearB ? 0.0 : $this->summarizeEnsByMonth(array_filter($matchingRows, static fn(array $r): bool => $r['year'] === $yearB))['total_kwh']),
                        2
                    )
                    : $this->summarizeEnsByMonth($matchingRows)['total_kwh'];
                $roundDiff    = round(abs($yrMonthlySum - $yrKwh), 2);
                $title        = "Total ENS (kWh) — {$yrLabel}";
                $subtitle     = 'Total ENS baris: ' . number_format($yrKwh, 2, ',', '.') . ' kWh dari ' . count($matchingRows) . ' trip'
                    . ($roundDiff >= 0.01 ? (' • Total chart (sum 2-desimal bulanan): ' . number_format($yrMonthlySum, 2, ',', '.') . ' kWh (selisih pembulatan: ' . number_format($roundDiff, 2, ',', '.') . ' kWh)') : '')
                    . ' • Kolom ( Kwh )';
                break;
        }

        $totalMatching = count($matchingRows);
        $sharePct      = $totalScope > 0 ? round(($totalMatching / $totalScope) * 100, 1) : 0.0;

        $ampValues = [];
        $kwhSum    = 0.0;
        foreach ($matchingRows as $mr) {
            if ($mr['fault_current_amperes'] !== null && $mr['fault_current_amperes'] > 0) {
                $ampValues[] = (float)$mr['fault_current_amperes'];
            }
            if ($mr['energy_not_supplied_kwh'] !== null) {
                $kwhSum += (float)$mr['energy_not_supplied_kwh'];
            }
        }

        $filteredRows = $matchingRows;
        if ($eventCategory !== '' && in_array($eventCategory, ['TEMPORARY', 'PERMANENT', 'UNKNOWN'], true)) {
            $filteredRows = array_values(array_filter(
                $filteredRows,
                static fn(array $r): bool => strtoupper((string)$r['interruption_category']) === $eventCategory
            ));
        }
        if ($eventUlp !== '') {
            $filteredRows = array_values(array_filter(
                $filteredRows,
                static fn(array $r): bool => strtoupper((string)$r['ulp_name']) === $eventUlp || str_contains(strtoupper((string)$r['ulp_name']), $eventUlp)
            ));
        }
        if ($search !== '') {
            $needle = strtolower($search);
            $filteredRows = array_values(array_filter($filteredRows, static function (array $r) use ($needle): bool {
                $hay = strtolower(
                    ($r['event_date'] ?? '') . ' ' .
                    ($r['interruption_started_at'] ?? '') . ' ' .
                    ($r['ulp_name'] ?? '') . ' ' .
                    ($r['feeder_name'] ?? '') . ' ' .
                    ($r['device_label'] ?? '') . ' ' .
                    ($r['equipment_family'] ?? '') . ' ' .
                    ($r['switching_device_type'] ?? '') . ' ' .
                    ($r['relay_label'] ?? '') . ' ' .
                    ($r['interruption_category'] ?? '') . ' ' .
                    ($r['cause_raw'] ?? '') . ' ' .
                    ($r['field_narrative_raw'] ?? '') . ' ' .
                    ($r['restoration_action_raw'] ?? '')
                );
                return str_contains($hay, $needle);
            }));
        }

        $filteredMatching = count($filteredRows);
        $slicedRows       = array_slice($filteredRows, $offset, $limit);
        $events           = [];
        foreach ($slicedRows as $idx => $r) {
            $events[] = [
                'row_num'                 => $offset + $idx + 1,
                'event_date'              => $r['event_date'],
                'interruption_started_at' => $r['interruption_started_at'],
                'outage_duration_minutes' => $r['outage_duration_minutes'],
                'ulp_name'                => $r['ulp_name'],
                'feeder_name'             => $r['feeder_name'],
                'device_label'            => $r['device_label'],
                'equipment_family'        => $r['equipment_family'],
                'switching_device_type'   => $r['switching_device_type'],
                'relay_label'             => $r['relay_label'],
                'interruption_category'   => $r['interruption_category'],
                'fault_current_amperes'   => $r['fault_current_amperes'],
                'energy_not_supplied_kwh' => $r['energy_not_supplied_kwh'],
                'cause_raw'               => $r['cause_raw'],
                'cause_canonical_code'    => $r['cause_canonical_code'],
                'field_narrative_raw'     => $r['field_narrative_raw'],
                'restoration_action_raw'  => $r['restoration_action_raw'] ?? '',
                'source_system'           => $r['source_system'],
            ];
        }

        $activeUlpName = 'Semua ULP';
        if ($ulpId !== null) {
            $ulps = $this->getUlpCatalog();
            $activeUlpName = $ulps['by_id'][$ulpId]['nama_ulp'] ?? ('ULP #' . $ulpId);
        }

        $activeFeederName = 'Semua Penyulang';
        if ($pid !== null) {
            $feeders = $this->getFeederCatalog();
            $activeFeederName = $feeders['by_id'][$pid]['nama_penyulang'] ?? ('Penyulang #' . $pid);
        }

        $returnedCount = count($events);
        $hasMore       = ($offset + $returnedCount) < $filteredMatching;

        return [
            'dimension'         => $dimUpper,
            'key'               => $key,
            'title'             => $title,
            'subtitle'          => $subtitle,
            'total_matching'    => $totalMatching,
            'filtered_matching' => $filteredMatching,
            'total_scope'       => $totalScope,
            'share_pct'         => $sharePct,
            'offset'            => $offset,
            'limit'             => $limit,
            'events_returned'   => $returnedCount,
            'has_more'          => $hasMore,
            'remaining_count'   => max(0, $filteredMatching - ($offset + $returnedCount)),
            'events_capped'     => $hasMore,
            'filter_context'  => [
                'year_label'      => $yearLabel,
                'ulp_label'       => $activeUlpName,
                'penyulang_label' => $activeFeederName,
                'scope'           => $scope,
            ],
            'pareto_point'    => $paretoPoint,
            'pareto_sequence' => $paretoSequence,
            'breakdowns'      => [
                'by_ulp'              => $this->summarizeColumnCounts($matchingRows, 'ulp_name', 10),
                'by_category'         => $this->summarizeColumnCounts($matchingRows, 'interruption_category', 5),
                'by_equipment_family' => $this->summarizeColumnCounts($matchingRows, 'equipment_family', 6),
                'by_relay'            => $this->summarizeColumnCounts($matchingRows, 'relay_label', 10),
                'top_feeders'         => $this->summarizeColumnCounts($matchingRows, 'feeder_name', 8),
                'top_causes'          => $this->summarizeColumnCounts($matchingRows, 'cause_raw', 10),
                'energy_kwh_total'    => round($kwhSum, 2),
                'fault_current'       => [
                    'count' => count($ampValues),
                    'avg'   => !empty($ampValues) ? round(array_sum($ampValues) / count($ampValues), 1) : null,
                    'min'   => !empty($ampValues) ? round(min($ampValues), 1) : null,
                    'max'   => !empty($ampValues) ? round(max($ampValues), 1) : null,
                ],
            ],
            'events' => $events,
        ];
    }

    /**
     * Summarize grouped frequency and share (%) for a single normalized row field.
     */
    protected function summarizeColumnCounts(array $rows, string $field, int $limit = 10): array
    {
        $counts = [];
        foreach ($rows as $r) {
            $val = (string)($r[$field] ?? 'TIDAK TERCATAT');
            $counts[$val] = ($counts[$val] ?? 0) + 1;
        }
        arsort($counts);
        $total = count($rows);
        $out   = [];
        foreach (array_slice($counts, 0, $limit, true) as $k => $cnt) {
            $out[] = [
                'label' => $k,
                'count' => $cnt,
                'pct'   => $total > 0 ? round(($cnt / $total) * 100, 1) : 0.0,
            ];
        }
        return $out;
    }
}
