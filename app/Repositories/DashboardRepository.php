<?php

namespace App\Repositories;

use CodeIgniter\Database\BaseBuilder;

class DashboardRepository
{
    private function getBuilder(): BaseBuilder
    {
        return \Config\Database::connect()->table('temuan');
    }

    private function applyUlpFilter(BaseBuilder $builder, $ulpFilter): BaseBuilder
    {
        if (is_array($ulpFilter)) {
            $ids = array_values(array_filter(array_map('intval', $ulpFilter)));
            if (!empty($ids)) {
                $builder->whereIn('temuan.ulp_id', $ids);
            } else {
                $builder->where('1 = 0');
            }
        } elseif (!empty($ulpFilter)) {
            $builder->where('temuan.ulp_id', (int)$ulpFilter);
        }
        return $builder;
    }

    private function getFilterCacheKey($ulpFilter): string
    {
        if (is_array($ulpFilter)) {
            $ids = array_values(array_filter(array_map('intval', $ulpFilter)));
            sort($ids);
            return 'arr_' . md5(implode(',', $ids));
        }
        return (string)($ulpFilter ?? 'all');
    }

    /**
     * Fetch KPI Summary & Stats filtered by ULP (single or array), Month, and Year
     */
    public function getDashboardKpi($ulpFilter = null, ?int $bulan = null, ?int $tahun = null): array
    {
        $cacheKey = "exec_kpi_" . $this->getFilterCacheKey($ulpFilter) . "_" . ($bulan ?? 'all') . "_" . ($tahun ?? 'all');

        return cache()->remember($cacheKey, 60, function () use ($ulpFilter, $bulan, $tahun) {
            $builder = $this->getBuilder()->where('deleted_at IS NULL');
            $this->applyUlpFilter($builder, $ulpFilter);

            if (!empty($bulan)) {
                $builder->where('MONTH(tanggal_temuan)', $bulan);
            }

            if (!empty($tahun)) {
                $builder->where('YEAR(tanggal_temuan)', $tahun);
            }

            $builder->select("
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'SELESAI' THEN 1 ELSE 0 END) AS selesai,
                SUM(CASE WHEN status = 'PROSES' THEN 1 ELSE 0 END) AS proses,
                SUM(CASE WHEN status = 'BELUM' OR status IS NULL THEN 1 ELSE 0 END) AS belum,
                SUM(CASE WHEN prioritas = 'EMERGENCY' THEN 1 ELSE 0 END) AS emergency,
                SUM(CASE WHEN prioritas = 'HIGH' THEN 1 ELSE 0 END) AS high,
                SUM(CASE WHEN prioritas = 'MEDIUM' THEN 1 ELSE 0 END) AS medium
            ");

            $query = $builder->get();
            $row = ($query && method_exists($query, 'getRowArray')) ? ($query->getRowArray() ?? []) : [];

            $total   = (int)($row['total'] ?? 0);
            $selesai = (int)($row['selesai'] ?? 0);
            $proses  = (int)($row['proses'] ?? 0);
            $belum   = (int)($row['belum'] ?? 0);

            $rate = $total > 0 ? round(($selesai / $total) * 100, 1) : 0.0;

            return [
                'total'     => $total,
                'selesai'   => $selesai,
                'proses'    => $proses,
                'belum'     => $belum,
                'emergency' => (int)($row['emergency'] ?? 0),
                'high'      => (int)($row['high'] ?? 0),
                'medium'    => (int)($row['medium'] ?? 0),
                'rate'      => $rate,
            ];
        });
    }

    /**
     * Line Chart: Trend Temuan Baru vs Realisasi Selesai (Daily for a Month or Monthly for Full Year)
     */
    public function getLineChartData($ulpFilter = null, ?int $bulan = null, ?int $tahun = null): array
    {
        $cacheKey = "exec_line_" . $this->getFilterCacheKey($ulpFilter) . "_" . ($bulan ?? 'all') . "_" . ($tahun ?? 'all');

        return cache()->remember($cacheKey, 60, function () use ($ulpFilter, $bulan, $tahun) {
            $db = \Config\Database::connect();
            $y  = $tahun ?: (int)date('Y');

            if (empty($bulan)) {
                // Annual View: Aggregate Jan - Dec
                $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                $temuan = [];
                $selesai = [];

                for ($mo = 1; $mo <= 12; $mo++) {
                    $b1 = $db->table('temuan')->where('deleted_at IS NULL')->where('MONTH(tanggal_temuan)', $mo)->where('YEAR(tanggal_temuan)', $y);
                    $this->applyUlpFilter($b1, $ulpFilter);
                    $tCount = $b1->countAllResults();

                    $b2 = $db->table('temuan')->where('deleted_at IS NULL')->where('status', 'SELESAI')->where('MONTH(updated_at)', $mo)->where('YEAR(updated_at)', $y);
                    $this->applyUlpFilter($b2, $ulpFilter);
                    $sCount = $b2->countAllResults();

                    $temuan[]  = $tCount;
                    $selesai[] = $sCount;
                }

                return [
                    'labels'  => $labels,
                    'temuan'  => $temuan,
                    'selesai' => $selesai,
                ];
            }

            // Monthly View: Daily Spans
            $m = (int)$bulan;
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $m, $y);
            $labels  = [];
            $temuan  = [];
            $selesai = [];

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr  = sprintf('%04d-%02d-%02d', $y, $m, $d);
                $labels[] = sprintf('%02d %s', $d, date('M', mktime(0, 0, 0, $m, 1)));

                $b1 = $db->table('temuan')->where('deleted_at IS NULL')->where('DATE(tanggal_temuan)', $dateStr);
                $this->applyUlpFilter($b1, $ulpFilter);
                $tCount = $b1->countAllResults();

                $b2 = $db->table('temuan')->where('deleted_at IS NULL')->where('status', 'SELESAI')->where('DATE(updated_at)', $dateStr);
                $this->applyUlpFilter($b2, $ulpFilter);
                $sCount = $b2->countAllResults();

                $temuan[]  = $tCount;
                $selesai[] = $sCount;
            }

            return [
                'labels'  => $labels,
                'temuan'  => $temuan,
                'selesai' => $selesai,
            ];
        });
    }

    /**
     * Bar Chart: Performa per ULP dengan label nilai angka
     */
    public function getUlpBarChartData(?int $bulan = null, ?int $tahun = null, ?array $allowedUlpIds = null): array
    {
        $scopeKey = !empty($allowedUlpIds) ? 'scope_' . md5(implode(',', $allowedUlpIds)) : 'all';
        $cacheKey = "exec_ulp_bar_" . ($bulan ?? 'all') . "_" . ($tahun ?? 'all') . "_" . $scopeKey;

        return cache()->remember($cacheKey, 60, function () use ($bulan, $tahun, $allowedUlpIds) {
            $db = \Config\Database::connect();
            $builder = $db->table('ulps')
                ->select('ulps.id, ulps.nama_ulp, COUNT(temuan.id) AS total_temuan')
                ->join('temuan', 'temuan.ulp_id = ulps.id AND temuan.deleted_at IS NULL', 'left')
                ->where('ulps.status', 'AKTIF');

            if (!empty($allowedUlpIds)) {
                $builder->whereIn('ulps.id', array_map('intval', $allowedUlpIds));
            }
            if (!empty($bulan)) {
                $builder->where('MONTH(temuan.tanggal_temuan)', $bulan);
            }
            if (!empty($tahun)) {
                $builder->where('YEAR(temuan.tanggal_temuan)', $tahun);
            }

            $builder->groupBy('ulps.id')->orderBy('total_temuan', 'DESC');
            $rows = $builder->get()->getResultArray();

            $labels = [];
            $values = [];

            foreach ($rows as $r) {
                $labels[] = $r['nama_ulp'];
                $values[] = (int)$r['total_temuan'];
            }

            return [
                'labels' => $labels,
                'values' => $values,
            ];
        });
    }

    /**
     * Donut Chart: Status Breakdown (Belum, Proses, Selesai) dengan Total di Tengah & Percentages
     */
    public function getStatusDonutData($ulpFilter = null, ?int $bulan = null, ?int $tahun = null): array
    {
        $kpi = $this->getDashboardKpi($ulpFilter, $bulan, $tahun);
        $total = $kpi['total'];

        $belum   = $kpi['belum'];
        $proses  = $kpi['proses'];
        $selesai = $kpi['selesai'];

        $pctBelum   = $total > 0 ? round(($belum / $total) * 100, 1) : 0;
        $pctProses  = $total > 0 ? round(($proses / $total) * 100, 1) : 0;
        $pctSelesai = $total > 0 ? round(($selesai / $total) * 100, 1) : 0;

        return [
            'total'       => $total,
            'labels'      => ['Belum', 'Proses', 'Selesai'],
            'values'      => [$belum, $proses, $selesai],
            'percentages' => [$pctBelum, $pctProses, $pctSelesai],
        ];
    }

    /**
     * Pie Chart: Jenis Temuan (HOTSPOT, ROW, KONSTRUKSI, etc.)
     */
    public function getJenisPieData($ulpFilter = null, ?int $bulan = null, ?int $tahun = null): array
    {
        $cacheKey = "exec_jenis_pie_" . $this->getFilterCacheKey($ulpFilter) . "_" . ($bulan ?? 'all') . "_" . ($tahun ?? 'all');

        return cache()->remember($cacheKey, 60, function () use ($ulpFilter, $bulan, $tahun) {
            $builder = $this->getBuilder()
                ->select('jenis_temuan, COUNT(id) AS total')
                ->where('deleted_at IS NULL');

            $this->applyUlpFilter($builder, $ulpFilter);

            if (!empty($bulan)) {
                $builder->where('MONTH(tanggal_temuan)', $bulan);
            }
            if (!empty($tahun)) {
                $builder->where('YEAR(tanggal_temuan)', $tahun);
            }

            $rows = $builder->groupBy('jenis_temuan')->orderBy('total', 'DESC')->get()->getResultArray();
            $grandTotal = array_sum(array_column($rows, 'total'));

            $labels      = [];
            $values      = [];
            $percentages = [];

            foreach ($rows as $r) {
                $jName = !empty($r['jenis_temuan']) ? strtoupper($r['jenis_temuan']) : 'LAINNYA';
                $val   = (int)$r['total'];
                $pct   = $grandTotal > 0 ? round(($val / $grandTotal) * 100, 1) : 0;

                $labels[]      = $jName;
                $values[]      = $val;
                $percentages[] = $pct;
            }

            return [
                'total'       => $grandTotal,
                'labels'      => $labels,
                'values'      => $values,
                'percentages' => $percentages,
            ];
        });
    }

    /**
     * Pie Chart: Prioritas Breakdown (EMERGENCY, HIGH, MEDIUM, LOW)
     */
    public function getPrioritasPieData($ulpFilter = null, ?int $bulan = null, ?int $tahun = null): array
    {
        $cacheKey = "exec_prio_pie_" . $this->getFilterCacheKey($ulpFilter) . "_" . ($bulan ?? 'all') . "_" . ($tahun ?? 'all');

        return cache()->remember($cacheKey, 60, function () use ($ulpFilter, $bulan, $tahun) {
            $builder = $this->getBuilder()
                ->select('prioritas, COUNT(id) AS total')
                ->where('deleted_at IS NULL');

            $this->applyUlpFilter($builder, $ulpFilter);

            if (!empty($bulan)) {
                $builder->where('MONTH(tanggal_temuan)', $bulan);
            }
            if (!empty($tahun)) {
                $builder->where('YEAR(tanggal_temuan)', $tahun);
            }

            $rows = $builder->groupBy('prioritas')->orderBy('total', 'DESC')->get()->getResultArray();
            $grandTotal = array_sum(array_column($rows, 'total'));

            $labels      = [];
            $values      = [];
            $percentages = [];

            foreach ($rows as $r) {
                $pName = !empty($r['prioritas']) ? strtoupper($r['prioritas']) : 'MEDIUM';
                $val   = (int)$r['total'];
                $pct   = $grandTotal > 0 ? round(($val / $grandTotal) * 100, 1) : 0;

                $labels[]      = $pName;
                $values[]      = $val;
                $percentages[] = $pct;
            }

            return [
                'total'       => $grandTotal,
                'labels'      => $labels,
                'values'      => $values,
                'percentages' => $percentages,
            ];
        });
    }

    /**
     * Fetch SLA aging backlog for open findings (>30 days, 15-30 days, 8-14 days, <=7 days)
     */
    public function getSlaAgingBacklog($ulpFilter = null, ?int $bulan = null, ?int $tahun = null): array
    {
        $cacheKey = "exec_aging_" . $this->getFilterCacheKey($ulpFilter) . "_" . ($bulan ?? 'all') . "_" . ($tahun ?? 'all');

        return cache()->remember($cacheKey, 60, function () use ($ulpFilter, $bulan, $tahun) {
            $builder = $this->getBuilder()
                ->where('deleted_at IS NULL')
                ->where('status !=', 'SELESAI');

            $this->applyUlpFilter($builder, $ulpFilter);

            if (!empty($bulan)) {
                $builder->where('MONTH(tanggal_temuan)', $bulan);
            }
            if (!empty($tahun)) {
                $builder->where('YEAR(tanggal_temuan)', $tahun);
            }

            $builder->select("
                COUNT(*) AS total_open,
                SUM(CASE WHEN DATEDIFF(CURRENT_DATE, tanggal_temuan) > 30 THEN 1 ELSE 0 END) AS over_30,
                SUM(CASE WHEN DATEDIFF(CURRENT_DATE, tanggal_temuan) BETWEEN 15 AND 30 THEN 1 ELSE 0 END) AS days_15_30,
                SUM(CASE WHEN DATEDIFF(CURRENT_DATE, tanggal_temuan) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) AS days_8_14,
                SUM(CASE WHEN DATEDIFF(CURRENT_DATE, tanggal_temuan) <= 7 THEN 1 ELSE 0 END) AS under_7
            ");

            $query = $builder->get();
            $row = ($query && method_exists($query, 'getRowArray')) ? ($query->getRowArray() ?? []) : [];

            return [
                'total_open' => (int)($row['total_open'] ?? 0),
                'over_30'    => (int)($row['over_30'] ?? 0),
                'days_15_30' => (int)($row['days_15_30'] ?? 0),
                'days_8_14'  => (int)($row['days_8_14'] ?? 0),
                'under_7'    => (int)($row['under_7'] ?? 0),
            ];
        });
    }

    /**
     * Fetch top critical feeders with highest backlog & emergency findings
     */
    public function getTopCriticalFeeders($ulpFilter = null, ?int $bulan = null, ?int $tahun = null, int $limit = 5): array
    {
        $cacheKey = "exec_top_feeders_" . $this->getFilterCacheKey($ulpFilter) . "_" . ($bulan ?? 'all') . "_" . ($tahun ?? 'all') . "_{$limit}";

        return cache()->remember($cacheKey, 60, function () use ($ulpFilter, $bulan, $tahun, $limit) {
            $builder = $this->getBuilder()
                ->select("
                    temuan.penyulang_id,
                    COALESCE(penyulang.nama_penyulang, CONCAT('Penyulang #', temuan.penyulang_id)) AS nama_penyulang,
                    temuan.ulp_id,
                    COUNT(temuan.id) AS total_temuan,
                    SUM(CASE WHEN temuan.status = 'SELESAI' THEN 1 ELSE 0 END) AS total_selesai,
                    SUM(CASE WHEN temuan.status != 'SELESAI' THEN 1 ELSE 0 END) AS total_backlog,
                    SUM(CASE WHEN temuan.prioritas = 'EMERGENCY' THEN 1 ELSE 0 END) AS total_emergency
                ")
                ->join('penyulang', 'penyulang.id = temuan.penyulang_id', 'left')
                ->where('temuan.deleted_at IS NULL')
                ->where('temuan.penyulang_id IS NOT NULL');

            $this->applyUlpFilter($builder, $ulpFilter);

            if (!empty($bulan)) {
                $builder->where('MONTH(temuan.tanggal_temuan)', $bulan);
            }
            if (!empty($tahun)) {
                $builder->where('YEAR(temuan.tanggal_temuan)', $tahun);
            }

            return $builder->groupBy('temuan.penyulang_id, penyulang.nama_penyulang, temuan.ulp_id')
                ->orderBy('total_backlog', 'DESC')
                ->orderBy('total_emergency', 'DESC')
                ->orderBy('total_temuan', 'DESC')
                ->limit($limit)
                ->get()
                ->getResultArray();
        });
    }

    /**
     * Fetch findings breakdown by pelaksana
     */
    public function getPelaksanaBreakdown($ulpFilter = null, ?int $bulan = null, ?int $tahun = null): array
    {
        $cacheKey = "exec_pelaksana_" . $this->getFilterCacheKey($ulpFilter) . "_" . ($bulan ?? 'all') . "_" . ($tahun ?? 'all');

        return cache()->remember($cacheKey, 60, function () use ($ulpFilter, $bulan, $tahun) {
            $builder = $this->getBuilder()
                ->select("
                    COALESCE(pelaksana, 'LAIN-LAIN') AS pelaksana,
                    COUNT(id) AS total,
                    SUM(CASE WHEN status = 'SELESAI' THEN 1 ELSE 0 END) AS selesai,
                    SUM(CASE WHEN status != 'SELESAI' THEN 1 ELSE 0 END) AS open
                ")
                ->where('deleted_at IS NULL');

            $this->applyUlpFilter($builder, $ulpFilter);

            if (!empty($bulan)) {
                $builder->where('MONTH(tanggal_temuan)', $bulan);
            }
            if (!empty($tahun)) {
                $builder->where('YEAR(tanggal_temuan)', $tahun);
            }

            return $builder->groupBy('pelaksana')->orderBy('total', 'DESC')->get()->getResultArray();
        });
    }
}
