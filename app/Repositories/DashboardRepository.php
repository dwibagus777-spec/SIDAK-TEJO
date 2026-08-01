<?php

namespace App\Repositories;

use CodeIgniter\Database\BaseBuilder;

class DashboardRepository
{
    private function getBuilder(): BaseBuilder
    {
        return \Config\Database::connect()->table('temuan');
    }

    /**
     * Fetch KPI Summary & Stats filtered by ULP, Month, and Year
     */
    public function getDashboardKpi(?int $ulpId = null, ?int $bulan = null, ?int $tahun = null): array
    {
        $cacheKey = "exec_kpi_" . ($ulpId ?? 'all') . "_" . ($bulan ?? 'all') . "_" . ($tahun ?? 'all');

        return cache()->remember($cacheKey, 60, function () use ($ulpId, $bulan, $tahun) {
            $builder = $this->getBuilder()->where('deleted_at IS NULL');

            if (!empty($ulpId)) {
                $builder->where('ulp_id', $ulpId);
            }

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
     * Line Chart: Daily Trend Temuan Baru vs Realisasi Selesai
     */
    public function getLineChartData(?int $ulpId = null, ?int $bulan = null, ?int $tahun = null): array
    {
        $cacheKey = "exec_line_" . ($ulpId ?? 'all') . "_" . ($bulan ?? 'all') . "_" . ($tahun ?? 'all');

        return cache()->remember($cacheKey, 60, function () use ($ulpId, $bulan, $tahun) {
            $db = \Config\Database::connect();
            $m  = $bulan ?: (int)date('n');
            $y  = $tahun ?: (int)date('Y');

            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $m, $y);
            $labels  = [];
            $temuan  = [];
            $selesai = [];

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr  = sprintf('%04d-%02d-%02d', $y, $m, $d);
                $labels[] = sprintf('%02d %s', $d, date('M', mktime(0, 0, 0, $m, 1)));

                // Count Temuan Baru on $dateStr
                $b1 = $db->table('temuan')->where('deleted_at IS NULL')->where('DATE(tanggal_temuan)', $dateStr);
                if (!empty($ulpId)) {
                    $b1->where('ulp_id', $ulpId);
                }
                $tCount = $b1->countAllResults();

                // Count Selesai on $dateStr
                $b2 = $db->table('temuan')->where('deleted_at IS NULL')->where('status', 'SELESAI')->where('DATE(updated_at)', $dateStr);
                if (!empty($ulpId)) {
                    $b2->where('ulp_id', $ulpId);
                }
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
    public function getUlpBarChartData(?int $bulan = null, ?int $tahun = null): array
    {
        $cacheKey = "exec_ulp_bar_" . ($bulan ?? 'all') . "_" . ($tahun ?? 'all');

        return cache()->remember($cacheKey, 60, function () use ($bulan, $tahun) {
            $db = \Config\Database::connect();
            $builder = $db->table('ulps')
                ->select('ulps.id, ulps.nama_ulp, COUNT(temuan.id) AS total_temuan')
                ->join('temuan', 'temuan.ulp_id = ulps.id AND temuan.deleted_at IS NULL', 'left');

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
    public function getStatusDonutData(?int $ulpId = null, ?int $bulan = null, ?int $tahun = null): array
    {
        $kpi = $this->getDashboardKpi($ulpId, $bulan, $tahun);
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
    public function getJenisPieData(?int $ulpId = null, ?int $bulan = null, ?int $tahun = null): array
    {
        $cacheKey = "exec_jenis_pie_" . ($ulpId ?? 'all') . "_" . ($bulan ?? 'all') . "_" . ($tahun ?? 'all');

        return cache()->remember($cacheKey, 60, function () use ($ulpId, $bulan, $tahun) {
            $builder = $this->getBuilder()
                ->select('jenis_temuan, COUNT(id) AS total')
                ->where('deleted_at IS NULL');

            if (!empty($ulpId)) {
                $builder->where('ulp_id', $ulpId);
            }
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
    public function getPrioritasPieData(?int $ulpId = null, ?int $bulan = null, ?int $tahun = null): array
    {
        $cacheKey = "exec_prio_pie_" . ($ulpId ?? 'all') . "_" . ($bulan ?? 'all') . "_" . ($tahun ?? 'all');

        return cache()->remember($cacheKey, 60, function () use ($ulpId, $bulan, $tahun) {
            $builder = $this->getBuilder()
                ->select('prioritas, COUNT(id) AS total')
                ->where('deleted_at IS NULL');

            if (!empty($ulpId)) {
                $builder->where('ulp_id', $ulpId);
            }
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
}
