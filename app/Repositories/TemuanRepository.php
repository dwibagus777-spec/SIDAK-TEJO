<?php

namespace App\Repositories;

use App\Models\TemuanModel;
use CodeIgniter\Database\BaseBuilder;

class TemuanRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new TemuanModel());
    }

    /**
     * Helper Query Builder Terpusat: Join ULP, Penyulang, & Section
     */
    protected function getJoinedBuilder(bool $includeUsers = false): BaseBuilder
    {
        $builder = $this->getBuilder('temuan')
            ->join('ulps', 'ulps.id = temuan.ulp_id', 'left')
            ->join('penyulang', 'penyulang.id = temuan.penyulang_id', 'left')
            ->join('sections', 'sections.id = temuan.section_id', 'left');

        if ($includeUsers) {
            $builder->join('users c', 'c.id = temuan.created_by', 'left')
                    ->join('users u', 'u.id = temuan.updated_by', 'left');
        }

        return $builder;
    }

    /**
     * Helper Filter Terpusat untuk Temuan
     */
    protected function applyTemuanFilters(BaseBuilder $builder, array $filters, ?int $ulpIdFilter = null, ?string $jenisTemuanFilter = null): BaseBuilder
    {
        $builder->where('temuan.deleted_at IS NULL');

        if ($ulpIdFilter !== null) {
            $builder->where('temuan.ulp_id', $ulpIdFilter);
        } elseif (!empty($filters['ulp_id'])) {
            $builder->where('temuan.ulp_id', (int)$filters['ulp_id']);
        }

        if ($jenisTemuanFilter !== null) {
            $builder->where('temuan.jenis_temuan', $jenisTemuanFilter);
        } elseif (!empty($filters['jenis_temuan'])) {
            $builder->where('temuan.jenis_temuan', $filters['jenis_temuan']);
        }

        if (!empty($filters['pelaksana'])) {
            $builder->where('temuan.pelaksana', $filters['pelaksana']);
        }

        if (!empty($filters['prioritas'])) {
            $builder->where('temuan.prioritas', $filters['prioritas']);
        }

        if (!empty($filters['potensi_gangguan'])) {
            $builder->where('temuan.potensi_gangguan', $filters['potensi_gangguan']);
        }

        if (!empty($filters['penyulang_id'])) {
            $builder->where('temuan.penyulang_id', (int)$filters['penyulang_id']);
        }

        if (!empty($filters['section_id'])) {
            $builder->where('temuan.section_id', (int)$filters['section_id']);
        }

        if (!empty($filters['start_date'])) {
            $builder->where('temuan.created_at >=', $filters['start_date'] . (strlen($filters['start_date']) === 10 ? ' 00:00:00' : ''));
        }

        if (!empty($filters['end_date'])) {
            $builder->where('temuan.created_at <=', $filters['end_date'] . (strlen($filters['end_date']) === 10 ? ' 23:59:59' : ''));
        }

        if (empty($filters['start_date']) && !empty($filters['tanggal_awal'])) {
            $builder->where('temuan.tanggal_temuan >=', $filters['tanggal_awal']);
        }

        if (empty($filters['end_date']) && !empty($filters['tanggal_akhir'])) {
            $builder->where('temuan.tanggal_temuan <=', $filters['tanggal_akhir']);
        }

        if (!empty($filters['shift'])) {
            $shiftVal = strtolower($filters['shift']);
            if (str_contains($shiftVal, 'pagi')) {
                $builder->where('HOUR(temuan.created_at) >=', 7)->where('HOUR(temuan.created_at) <', 15);
            } elseif (str_contains($shiftVal, 'siang')) {
                $builder->where('HOUR(temuan.created_at) >=', 15)->where('HOUR(temuan.created_at) <', 23);
            } elseif (str_contains($shiftVal, 'malam')) {
                $builder->groupStart()->where('HOUR(temuan.created_at) >=', 23)->orWhere('HOUR(temuan.created_at) <', 7)->groupEnd();
            }
        }

        if (!empty($filters['status'])) {
            $statusVal = strtoupper($filters['status']);
            if ($statusVal === 'BELUM SELESAI' || $statusVal === 'BELUM') {
                $builder->where('temuan.status !=', 'SELESAI');
            } elseif ($statusVal === 'SUDAH SELESAI' || $statusVal === 'SELESAI') {
                $builder->where('temuan.status', 'SELESAI');
            } elseif ($statusVal === 'PROSES' || $statusVal === 'DALAM_PROSES' || $statusVal === 'DALAM PROSES') {
                $builder->whereIn('temuan.status', ['PROSES', 'DALAM_PROSES', 'TINDAK_LANJUT', 'PROSES PEKERJAAN']);
            } else {
                $builder->where('temuan.status', $statusVal);
            }
        }

        return $builder;
    }

    /**
     * Dapatkan nomor temuan berikutnya berdasarkan tahun (Contoh: STJ-2026-000001)
     */
    public function generateNomorTemuan(): string
    {
        $year = date('Y');
        $prefix = "STJ-" . $year . "-";
        
        $lastTemuan = $this->model
            ->select('nomor_temuan')
            ->like('nomor_temuan', $prefix, 'after')
            ->orderBy('id', 'DESC')
            ->first();

        $nextNum = $lastTemuan ? ((int)substr($lastTemuan['nomor_temuan'], -6)) + 1 : 1;
        return $prefix . str_pad((string)$nextNum, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Detail Temuan dengan Join ULP, Penyulang, Section, User Pembuat/Pengupdate
     */
    public function getDetail(int $id, ?int $ulpIdFilter = null): ?array
    {
        $builder = $this->getJoinedBuilder(true)
            ->select('temuan.*, ulps.nama_ulp, penyulang.nama_penyulang, sections.nama_section, 
                      c.nama as creator_name, u.nama as updater_name')
            ->where('temuan.id', $id);

        if ($ulpIdFilter !== null) {
            $builder->where('temuan.ulp_id', $ulpIdFilter);
        }

        return self::safeRow($builder, 'TemuanRepository::getDetail');
    }

    /**
     * Query Server-Side DataTables untuk Temuan (Hostinger MySQL Optimized)
     */
    public function getDataTables(array $postData, ?int $ulpIdFilter = null, ?string $jenisTemuanFilter = null): array
    {
        try {
            $builder = $this->getJoinedBuilder(false)
                ->select('temuan.id, temuan.nomor_temuan, temuan.jenis_temuan, temuan.pelaksana, 
                          temuan.prioritas, temuan.potensi_gangguan, temuan.tanggal_temuan, temuan.status, 
                          temuan.detail_temuan, temuan.foto, temuan.foto_path, temuan.created_at,
                          ulps.nama_ulp, penyulang.nama_penyulang, sections.nama_section');

            $this->applyTemuanFilters($builder, $postData, $ulpIdFilter, $jenisTemuanFilter);

            // Count Total Records (Unfiltered Base Count for scoping)
            $baseTotalQuery = $this->getBuilder('temuan')->where('deleted_at IS NULL');
            if ($ulpIdFilter !== null) {
                $baseTotalQuery->where('ulp_id', $ulpIdFilter);
            }
            if ($jenisTemuanFilter !== null) {
                $baseTotalQuery->where('jenis_temuan', $jenisTemuanFilter);
            }
            $totalRecords = $baseTotalQuery->countAllResults();

            // Search Filter
            $searchValue = '';
            if (isset($postData['search']) && is_array($postData['search'])) {
                $searchValue = trim((string)($postData['search']['value'] ?? ''));
            } elseif (isset($postData['search']) && is_string($postData['search'])) {
                $searchValue = trim($postData['search']);
            }

            if ($searchValue !== '') {
                $builder->groupStart()
                    ->like('temuan.nomor_temuan', $searchValue)
                    ->orLike('temuan.detail_temuan', $searchValue)
                    ->orLike('temuan.alamat', $searchValue)
                    ->orLike('temuan.jenis_temuan', $searchValue)
                    ->orLike('temuan.pelaksana', $searchValue)
                    ->orLike('temuan.prioritas', $searchValue)
                    ->orLike('temuan.potensi_gangguan', $searchValue)
                    ->orLike('ulps.nama_ulp', $searchValue)
                    ->orLike('penyulang.nama_penyulang', $searchValue)
                    ->orLike('sections.nama_section', $searchValue)
                    ->groupEnd();
            }

            // Count Filtered Records
            $totalFiltered = (int)$builder->countAllResults(false);

            // Order & Pagination
            $orderColumnIdx = isset($postData['order'][0]['column']) ? (int)$postData['order'][0]['column'] : 6;
            $orderDir = isset($postData['order'][0]['dir']) && strtolower($postData['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';
            
            $columnsMap = [
                0 => 'temuan.nomor_temuan',
                1 => 'penyulang.nama_penyulang',
                2 => 'sections.nama_section',
                3 => 'temuan.jenis_temuan',
                4 => 'temuan.id',
                5 => 'temuan.prioritas',
                6 => 'temuan.created_at',
                7 => 'temuan.status',
                8 => 'temuan.id',
            ];
            
            $orderColumn = $columnsMap[$orderColumnIdx] ?? 'temuan.created_at';
            $builder->orderBy($orderColumn, $orderDir);
            if ($orderColumn !== 'temuan.id') {
                $builder->orderBy('temuan.id', 'DESC');
            }

            $start = max(0, (int)($postData['start'] ?? 0));
            $length = (int)($postData['length'] ?? 10);
            if ($length > 0) {
                $builder->limit($length, $start);
            }

            $data = self::safeGet($builder, null, 'TemuanRepository::getDataTables');

            return [
                'draw'            => (int)($postData['draw'] ?? 0),
                'recordsTotal'    => (int)$totalRecords,
                'recordsFiltered' => (int)$totalFiltered,
                'data'            => $data
            ];
        } catch (\Throwable $e) {
            log_message('error', '[TemuanRepository::getDataTables] Exception: ' . $e->getMessage() . ' | SQL: ' . (string)$this->model->db->getLastQuery());
            return [
                'draw'            => (int)($postData['draw'] ?? 0),
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => []
            ];
        }
    }

    /**
     * Dapatkan data temuan terfilter untuk Pusat Laporan
     */
    public function getFilteredTemuan(array $filters, ?int $ulpIdFilter = null): array
    {
        $builder = $this->getJoinedBuilder(false)
            ->select('temuan.*, ulps.nama_ulp, penyulang.nama_penyulang, sections.nama_section');

        $this->applyTemuanFilters($builder, $filters, $ulpIdFilter, null);

        return self::safeGet($builder->orderBy('temuan.tanggal_temuan', 'DESC'), null, 'TemuanRepository::getFilteredTemuan');
    }

    /**
     * Identifikasi Gangguan: cari temuan berdasarkan penyulang dan potensi gangguan
     */
    public function getIdentifikasiGangguan(int $penyulangId, string $potensiGangguan, ?string $jenisTemuanFilter = null): array
    {
        $builder = $this->getBuilder('temuan')
            ->select('temuan.*, sections.nama_section')
            ->join('sections', 'sections.id = temuan.section_id')
            ->where('temuan.penyulang_id', $penyulangId)
            ->where('temuan.potensi_gangguan', $potensiGangguan)
            ->where('temuan.deleted_at IS NULL');

        if ($jenisTemuanFilter !== null) {
            $builder->where('temuan.jenis_temuan', $jenisTemuanFilter);
        }

        return self::safeGet($builder->orderBy('temuan.id', 'DESC'), null, 'TemuanRepository::getIdentifikasiGangguan');
    }

    /**
     * Identifikasi Gangguan: ranking section berdasarkan jumlah temuan
     */
    public function getRankingSectionsForIdentifikasi(int $penyulangId, string $potensiGangguan, ?string $jenisTemuanFilter = null): array
    {
        $builder = $this->getBuilder('temuan')
            ->select('sections.nama_section, COUNT(temuan.id) as total_temuan')
            ->join('sections', 'sections.id = temuan.section_id')
            ->where('temuan.penyulang_id', $penyulangId)
            ->where('temuan.potensi_gangguan', $potensiGangguan)
            ->where('temuan.deleted_at IS NULL');

        if ($jenisTemuanFilter !== null) {
            $builder->where('temuan.jenis_temuan', $jenisTemuanFilter);
        }

        return self::safeGet($builder->groupBy('temuan.section_id')->orderBy('total_temuan', 'DESC'), null, 'TemuanRepository::getRankingSectionsForIdentifikasi');
    }

    /**
     * Statistik Umum Dashboard (Single Query Hostinger MySQL Aggregation Optimization)
     * Mengganti 12 query terpisah menjadi 1 QUERY TUNGGAL untuk kecepatan maksimal
     */
    public function getDashboardStats(?int $ulpIdFilter = null, ?string $roleFilter = null): array
    {
        $cacheKey = "dashboard_stats_" . ($ulpIdFilter ?? 'all') . "_" . ($roleFilter ?? 'all');
        return cache()->remember($cacheKey, 600, function() use ($ulpIdFilter, $roleFilter) {
            $builder = $this->getBuilder('temuan')->where('deleted_at IS NULL');

            if ($ulpIdFilter !== null) {
                $builder->where('ulp_id', $ulpIdFilter);
            }

            $roleMap = [
                'pdkb'           => 'PDKB',
                'har_gardu'      => 'HAR GARDU',
                'har_konstruksi' => 'HAR KONSTRUKSI',
                'har_row'        => 'HAR ROW',
                'yantek'         => 'YANTEK'
            ];
            if ($roleFilter && isset($roleMap[$roleFilter])) {
                $builder->where('pelaksana', $roleMap[$roleFilter]);
            }

            $builder->select("
                COUNT(*) AS total,
                SUM(CASE WHEN pelaksana = 'PDKB' THEN 1 ELSE 0 END) AS pdkb,
                SUM(CASE WHEN pelaksana = 'HAR GARDU' THEN 1 ELSE 0 END) AS har_gardu,
                SUM(CASE WHEN pelaksana = 'HAR KONSTRUKSI' THEN 1 ELSE 0 END) AS har_konstruksi,
                SUM(CASE WHEN pelaksana = 'HAR ROW' THEN 1 ELSE 0 END) AS har_row,
                SUM(CASE WHEN pelaksana = 'HAR CRANE' THEN 1 ELSE 0 END) AS har_crane,
                SUM(CASE WHEN pelaksana = 'YANTEK' THEN 1 ELSE 0 END) AS yantek,
                SUM(CASE WHEN prioritas = 'EMERGENCY' THEN 1 ELSE 0 END) AS emergency,
                SUM(CASE WHEN prioritas = 'HIGH' THEN 1 ELSE 0 END) AS high,
                SUM(CASE WHEN prioritas = 'MEDIUM' THEN 1 ELSE 0 END) AS medium,
                SUM(CASE WHEN status = 'BELUM' THEN 1 ELSE 0 END) AS belum,
                SUM(CASE WHEN status = 'SELESAI' THEN 1 ELSE 0 END) AS selesai
            ");

            $row = self::safeRow($builder, 'TemuanRepository::getDashboardStats') ?: [];

            return [
                'total'          => (int)($row['total'] ?? 0),
                'pdkb'           => (int)($row['pdkb'] ?? 0),
                'har_gardu'      => (int)($row['har_gardu'] ?? 0),
                'har_gtt'        => (int)($row['har_gtt'] ?? 0),
                'har_konstruksi' => (int)($row['har_konstruksi'] ?? 0),
                'har_row'        => (int)($row['har_row'] ?? 0),
                'har_crane'      => (int)($row['har_crane'] ?? 0),
                'yantek'         => (int)($row['yantek'] ?? 0),
                'emergency'      => (int)($row['emergency'] ?? 0),
                'high'           => (int)($row['high'] ?? 0),
                'medium'         => (int)($row['medium'] ?? 0),
                'belum'          => (int)($row['belum'] ?? 0),
                'proses'         => (int)($row['proses'] ?? 0),
                'padam'          => (int)($row['padam'] ?? 0),
                'selesai'        => (int)($row['selesai'] ?? 0)
            ];
        });
    }

    /**
     * Temuan Bulanan untuk Grafik (Chart.js)
     */
    public function getMonthlyStats(?int $ulpIdFilter = null): array
    {
        $builder = $this->getBuilder('temuan')
            ->select("DATE_FORMAT(tanggal_temuan, '%Y-%m') as bulan, COUNT(id) as total")
            ->where('deleted_at IS NULL');

        if ($ulpIdFilter !== null) {
            $builder->where('ulp_id', $ulpIdFilter);
        }

        return self::safeGet($builder->groupBy('bulan')->orderBy('bulan', 'ASC')->limit(12), null, 'TemuanRepository::getMonthlyStats');
    }

    /**
     * Temuan per ULP untuk Grafik (Chart.js)
     */
    public function getUlpStats(?int $ulpIdFilter = null): array
    {
        $builder = $this->getBuilder('temuan')
            ->select('ulps.nama_ulp, COUNT(temuan.id) as total')
            ->join('ulps', 'ulps.id = temuan.ulp_id')
            ->where('temuan.deleted_at IS NULL');

        if ($ulpIdFilter !== null) {
            $builder->where('temuan.ulp_id', $ulpIdFilter);
        }

        return self::safeGet($builder->groupBy('temuan.ulp_id'), null, 'TemuanRepository::getUlpStats');
    }

    /**
     * Temuan per Penyulang untuk Grafik (Chart.js)
     */
    public function getPenyulangStats(?int $ulpIdFilter = null): array
    {
        $builder = $this->getBuilder('temuan')
            ->select('penyulang.nama_penyulang, COUNT(temuan.id) as total')
            ->join('penyulang', 'penyulang.id = temuan.penyulang_id')
            ->where('temuan.deleted_at IS NULL')
            ->where("temuan.status != 'SELESAI'");

        if ($ulpIdFilter !== null) {
            $builder->where('temuan.ulp_id', $ulpIdFilter);
        }

        return self::safeGet($builder->groupBy('temuan.penyulang_id')->orderBy('total', 'DESC')->limit(10), null, 'TemuanRepository::getPenyulangStats');
    }

    /**
     * Temuan per Pelaksana untuk Grafik (Chart.js)
     */
    public function getPelaksanaStats(?int $ulpIdFilter = null): array
    {
        $builder = $this->getBuilder('temuan')
            ->select('pelaksana, COUNT(id) as total')
            ->where('deleted_at IS NULL');

        if ($ulpIdFilter !== null) {
            $builder->where('ulp_id', $ulpIdFilter);
        }

        return self::safeGet($builder->groupBy('pelaksana'), null, 'TemuanRepository::getPelaksanaStats');
    }

    /**
     * Temuan per Prioritas untuk Grafik (Chart.js)
     */
    public function getPrioritasStats(?int $ulpIdFilter = null): array
    {
        $builder = $this->getBuilder('temuan')
            ->select('prioritas, COUNT(id) as total')
            ->where('deleted_at IS NULL');

        if ($ulpIdFilter !== null) {
            $builder->where('ulp_id', $ulpIdFilter);
        }

        return self::safeGet($builder->groupBy('prioritas'), null, 'TemuanRepository::getPrioritasStats');
    }

    /**
     * Temuan per Potensi Gangguan untuk Grafik (Chart.js)
     */
    public function getPotensiGangguanStats(?int $ulpIdFilter = null): array
    {
        $builder = $this->getBuilder('temuan')
            ->select('potensi_gangguan, COUNT(id) as total')
            ->where('deleted_at IS NULL');

        if ($ulpIdFilter !== null) {
            $builder->where('ulp_id', $ulpIdFilter);
        }

        return self::safeGet($builder->groupBy('potensi_gangguan'), null, 'TemuanRepository::getPotensiGangguanStats');
    }

    /**
     * Dapatkan titik-titik koordinat peta (GIS)
     */
    public function getMapPins(?int $ulpIdFilter = null): array
    {
        $builder = $this->model
            ->select('id, nomor_temuan, prioritas, status, latitude, longitude, alamat, detail_temuan')
            ->where('latitude IS NOT NULL')
            ->where('longitude IS NOT NULL')
            ->where("latitude != ''")
            ->where("longitude != ''")
            ->where("latitude != '0'")
            ->where("longitude != '0'")
            ->where('deleted_at IS NULL');

        if ($ulpIdFilter !== null) {
            $builder->where('ulp_id', $ulpIdFilter);
        }

        return $builder->findAll();
    }

    /**
     * Comprehensive Role-Based Real-time Analytics for Dashboard
     */
    public function getComprehensiveAnalytics(string $role, ?int $ulpIdFilter = null): array
    {
        $db = \Config\Database::connect();
        
        // Scope builder based on Role and ULP
        $applyScope = function($builder) use ($role, $ulpIdFilter) {
            $roleLower = strtolower($role);
            if (!in_array($roleLower, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($ulpIdFilter)) {
                $builder->where('temuan.ulp_id', $ulpIdFilter);
            }
            $roleMap = [
                'pdkb'           => 'PDKB',
                'har_gardu'      => 'HAR GARDU',
                'har_konstruksi' => 'HAR KONSTRUKSI',
                'har_row'        => 'HAR ROW',
                'yantek'         => 'YANTEK'
            ];
            if (isset($roleMap[$roleLower])) {
                $builder->where('temuan.pelaksana', $roleMap[$roleLower]);
            }
            if ($roleLower === 'inspeksi') {
                $builder->where("temuan.status != 'SELESAI'");
            }
            return $builder;
        };

        // 1. Overview Totals
        $b1 = $db->table('temuan')->where('deleted_at IS NULL');
        $b1 = $applyScope($b1);
        $totalTemuan = $b1->countAllResults();

        // 2. Realisasi Selesai
        $b2 = $db->table('temuan')->where('deleted_at IS NULL')->where('status', 'SELESAI');
        $b2 = $applyScope($b2);
        $totalRealisasi = $b2->countAllResults();

        // 3. Temuan Hari Ini
        $b3 = $db->table('temuan')->where('deleted_at IS NULL')->where('DATE(tanggal_temuan)', date('Y-m-d'));
        $b3 = $applyScope($b3);
        $temuanHariIni = $b3->countAllResults();

        // 4. Realisasi Hari Ini
        $b4 = $db->table('temuan')->where('deleted_at IS NULL')->where('status', 'SELESAI')->where('DATE(updated_at)', date('Y-m-d'));
        $b4 = $applyScope($b4);
        $realisasiHariIni = $b4->countAllResults();

        // 5. Temuan Mingguan (Last 7 Days)
        $b5 = $db->table('temuan')
            ->select("DATE(tanggal_temuan) as tgl, COUNT(id) as total")
            ->where('deleted_at IS NULL')
            ->where('tanggal_temuan >=', date('Y-m-d', strtotime('-6 days')));
        $b5 = $applyScope($b5);
        $temuanMingguanRaw = self::safeGet($b5->groupBy('tgl')->orderBy('tgl', 'ASC'), null, 'TemuanRepository::getComprehensiveAnalytics_weeklyTemuan');

        $temuanMingguan = ['labels' => [], 'data' => []];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $val = 0;
            foreach ($temuanMingguanRaw as $r) {
                if ($r['tgl'] === $d) { $val = (int)$r['total']; break; }
            }
            $temuanMingguan['labels'][] = date('d M', strtotime($d));
            $temuanMingguan['data'][] = $val;
        }

        // 6. Realisasi Harian (Last 7 Days)
        $b6 = $db->table('temuan')
            ->select("DATE(updated_at) as tgl, COUNT(id) as total")
            ->where('deleted_at IS NULL')
            ->where('status', 'SELESAI')
            ->where('updated_at >=', date('Y-m-d 00:00:00', strtotime('-6 days')));
        $b6 = $applyScope($b6);
        $realisasiHarianRaw = self::safeGet($b6->groupBy('tgl')->orderBy('tgl', 'ASC'), null, 'TemuanRepository::getComprehensiveAnalytics_dailyRealisasi');

        $realisasiHarian = ['labels' => [], 'data' => []];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $val = 0;
            foreach ($realisasiHarianRaw as $r) {
                if ($r['tgl'] === $d) { $val = (int)$r['total']; break; }
            }
            $realisasiHarian['labels'][] = date('d M', strtotime($d));
            $realisasiHarian['data'][] = $val;
        }

        // 7. Temuan Bulanan (12 Months)
        $b7 = $db->table('temuan')
            ->select("DATE_FORMAT(tanggal_temuan, '%Y-%m') as bln, COUNT(id) as total")
            ->where('deleted_at IS NULL');
        $b7 = $applyScope($b7);
        $temuanBulananRaw = self::safeGet($b7->groupBy('bln')->orderBy('bln', 'ASC')->limit(12), null, 'TemuanRepository::getComprehensiveAnalytics_monthlyTemuan');

        $temuanBulanan = ['labels' => [], 'data' => []];
        foreach ($temuanBulananRaw as $r) {
            $temuanBulanan['labels'][] = date('M Y', strtotime($r['bln'] . '-01'));
            $temuanBulanan['data'][] = (int)$r['total'];
        }

        // 8. Realisasi Bulanan (12 Months)
        $b8 = $db->table('temuan')
            ->select("DATE_FORMAT(updated_at, '%Y-%m') as bln, COUNT(id) as total")
            ->where('deleted_at IS NULL')
            ->where('status', 'SELESAI');
        $b8 = $applyScope($b8);
        $realisasiBulananRaw = self::safeGet($b8->groupBy('bln')->orderBy('bln', 'ASC')->limit(12), null, 'TemuanRepository::getComprehensiveAnalytics_monthlyRealisasi');

        $realisasiBulanan = ['labels' => [], 'data' => []];
        foreach ($realisasiBulananRaw as $r) {
            $realisasiBulanan['labels'][] = date('M Y', strtotime($r['bln'] . '-01'));
            $realisasiBulanan['data'][] = (int)$r['total'];
        }

        // 9. Status Breakdown
        $b9 = $db->table('temuan')->select("status, COUNT(id) as total")->where('deleted_at IS NULL');
        $b9 = $applyScope($b9);
        $statusRaw = self::safeGet($b9->groupBy('status'), null, 'TemuanRepository::getComprehensiveAnalytics_status');
        $statusBreakdown = ['BELUM' => 0, 'PROSES' => 0, 'SELESAI' => 0, 'TERKENDALA' => 0];
        foreach ($statusRaw as $r) {
            $st = strtoupper($r['status']);
            $statusBreakdown[$st] = (int)$r['total'];
        }

        // 10. Prioritas Breakdown (EMERGENCY, HIGH, MEDIUM)
        $b10 = $db->table('temuan')->select("prioritas, COUNT(id) as total")->where('deleted_at IS NULL');
        $b10 = $applyScope($b10);
        $prioritasRaw = self::safeGet($b10->groupBy('prioritas'), null, 'TemuanRepository::getComprehensiveAnalytics_prioritas');
        $prioritasBreakdown = ['EMERGENCY' => 0, 'HIGH' => 0, 'MEDIUM' => 0];
        foreach ($prioritasRaw as $r) {
            $pr = strtoupper($r['prioritas']);
            $prioritasBreakdown[$pr] = (int)$r['total'];
        }

        // 11. Jenis Temuan Breakdown (ROW, HOTSPOT, KONSTRUKSI)
        $b11 = $db->table('temuan')->select("jenis_temuan, COUNT(id) as total")->where('deleted_at IS NULL');
        $b11 = $applyScope($b11);
        $jenisRaw = self::safeGet($b11->groupBy('jenis_temuan'), null, 'TemuanRepository::getComprehensiveAnalytics_jenis');
        $jenisBreakdown = ['KONSTRUKSI' => 0, 'HOTSPOT' => 0, 'ROW' => 0];
        foreach ($jenisRaw as $r) {
            $jt = strtoupper($r['jenis_temuan']);
            $jenisBreakdown[$jt] = (int)$r['total'];
        }

        // 12. Pelaksana Breakdown
        $b12 = $db->table('temuan')->select("pelaksana, COUNT(id) as total")->where('deleted_at IS NULL');
        $b12 = $applyScope($b12);
        $pelaksanaRaw = self::safeGet($b12->groupBy('pelaksana'), null, 'TemuanRepository::getComprehensiveAnalytics_pelaksana');

        // 13. ULP Breakdown
        $b13 = $db->table('temuan')->select("ulps.nama_ulp, COUNT(temuan.id) as total")
            ->join('ulps', 'ulps.id = temuan.ulp_id')
            ->where('temuan.deleted_at IS NULL');
        $b13 = $applyScope($b13);
        $ulpRaw = self::safeGet($b13->groupBy('temuan.ulp_id'), null, 'TemuanRepository::getComprehensiveAnalytics_ulp');

        // 14. Penyulang Breakdown
        $b14 = $db->table('temuan')->select("penyulang.nama_penyulang, COUNT(temuan.id) as total")
            ->join('penyulang', 'penyulang.id = temuan.penyulang_id')
            ->where('temuan.deleted_at IS NULL');
        $b14 = $applyScope($b14);
        $penyulangRaw = self::safeGet($b14->groupBy('temuan.penyulang_id')->orderBy('total', 'DESC')->limit(10), null, 'TemuanRepository::getComprehensiveAnalytics_penyulang');

        // 15. SLA Calculation (Met SLA vs Overdue)
        $b15 = $db->table('temuan')->select("status, prioritas, tanggal_temuan, updated_at")->where('deleted_at IS NULL');
        $b15 = $applyScope($b15);
        $slaRows = self::safeGet($b15, null, 'TemuanRepository::getComprehensiveAnalytics_sla');
        $slaMet = 0;
        $slaOverdue = 0;

        foreach ($slaRows as $sr) {
            $created = strtotime($sr['tanggal_temuan']);
            $finished = ($sr['status'] === 'SELESAI' && !empty($sr['updated_at'])) ? strtotime($sr['updated_at']) : time();
            $daysDiff = floor(($finished - $created) / 86400);

            $maxDays = match(strtoupper($sr['prioritas'])) {
                'EMERGENCY' => 1,
                'HIGH'      => 3,
                'MEDIUM'    => 7,
                default     => 14
            };

            if ($daysDiff <= $maxDays) {
                $slaMet++;
            } else {
                $slaOverdue++;
            }
        }

        return [
            'role'              => $role,
            'scope'             => !empty($ulpIdFilter) ? "ULP ID #{$ulpIdFilter}" : 'Nasional / All ULP',
            'total_temuan'      => $totalTemuan,
            'total_realisasi'   => $totalRealisasi,
            'temuan_hari_ini'   => $temuanHariIni,
            'realisasi_hari_ini'=> $realisasiHariIni,
            'temuan_mingguan'   => $temuanMingguan,
            'realisasi_harian'  => $realisasiHarian,
            'temuan_bulanan'    => $temuanBulanan,
            'realisasi_bulanan' => $realisasiBulanan,
            'status_breakdown'  => $statusBreakdown,
            'prioritas_breakdown'=> $prioritasBreakdown,
            'jenis_breakdown'   => $jenisBreakdown,
            'pelaksana_raw'     => $pelaksanaRaw,
            'ulp_raw'           => $ulpRaw,
            'penyulang_raw'     => $penyulangRaw,
            'sla'               => [
                'met'     => $slaMet,
                'overdue' => $slaOverdue
            ]
        ];
    }

    /**
     * Phase 14 Enterprise Executive Dashboard Analytics Engine
     */
    public function getExecutiveAnalyticsData(array $filters = [], string $role = 'administrator', ?int $userUlpId = null): array
    {
        $db = \Config\Database::connect();
        $roleLower = strtolower($role);

        // Helper to apply dynamic multi-filters + role scoping
        $applyFilters = function($builder) use ($filters, $roleLower, $userUlpId) {
            $builder->where('temuan.deleted_at IS NULL');

            // Role Scoping
            if (!in_array($roleLower, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3']) && !empty($userUlpId)) {
                $builder->where('temuan.ulp_id', $userUlpId);
            }

            // Dynamic Filters
            if (!empty($filters['ulp_id'])) {
                $builder->where('temuan.ulp_id', (int)$filters['ulp_id']);
            }
            if (!empty($filters['penyulang_id'])) {
                $builder->where('temuan.penyulang_id', (int)$filters['penyulang_id']);
            }
            if (!empty($filters['section_id'])) {
                $builder->where('temuan.section_id', (int)$filters['section_id']);
            }
            if (!empty($filters['jenis_temuan'])) {
                $builder->where('temuan.jenis_temuan', $filters['jenis_temuan']);
            }
            if (!empty($filters['pelaksana'])) {
                $builder->where('temuan.pelaksana', $filters['pelaksana']);
            }
            if (!empty($filters['prioritas'])) {
                $builder->where('temuan.prioritas', $filters['prioritas']);
            }
            if (!empty($filters['status'])) {
                $builder->where('temuan.status', strtoupper($filters['status']));
            }
            if (!empty($filters['tanggal_mulai'])) {
                $builder->where('temuan.tanggal_temuan >=', $filters['tanggal_mulai']);
            }
            if (!empty($filters['tanggal_selesai'])) {
                $builder->where('temuan.tanggal_temuan <=', $filters['tanggal_selesai']);
            }
            return $builder;
        };

        // 1. Realtime KPI Calculations
        $today = date('Y-m-d');
        $thisWeekStart = date('Y-m-d', strtotime('monday this week'));
        $thisMonthStart = date('Y-m-01');
        $thisYearStart = date('Y-01-01');

        $bTotal = $applyFilters($db->table('temuan'));
        $totalTemuan = $bTotal->countAllResults();

        $bHariIni = $applyFilters($db->table('temuan'))->where('DATE(tanggal_temuan)', $today);
        $temuanHariIni = $bHariIni->countAllResults();

        $bMingguIni = $applyFilters($db->table('temuan'))->where('tanggal_temuan >=', $thisWeekStart);
        $temuanMingguIni = $bMingguIni->countAllResults();

        $bBulanIni = $applyFilters($db->table('temuan'))->where('tanggal_temuan >=', $thisMonthStart);
        $temuanBulanIni = $bBulanIni->countAllResults();

        $bTahunIni = $applyFilters($db->table('temuan'))->where('tanggal_temuan >=', $thisYearStart);
        $temuanTahunIni = $bTahunIni->countAllResults();

        $bBelum = $applyFilters($db->table('temuan'))->where('status', 'BELUM');
        $temuanBelum = $bBelum->countAllResults();

        $bProses = $applyFilters($db->table('temuan'))->where('status', 'PROSES');
        $temuanProses = $bProses->countAllResults();

        $bSelesai = $applyFilters($db->table('temuan'))->where('status', 'SELESAI');
        $temuanSelesai = $bSelesai->countAllResults();

        // Overdue & SLA Calculation
        $bSla = $applyFilters($db->table('temuan'))->select('id, status, prioritas, tanggal_temuan, updated_at');
        $slaRows = self::safeGet($bSla, null, 'TemuanRepository::getExecutiveAnalyticsData_sla');

        $overdueCount = 0;
        $metCount = 0;
        $slaByPrioritas = [
            'EMERGENCY' => ['total' => 0, 'met' => 0, 'overdue' => 0],
            'HIGH'      => ['total' => 0, 'met' => 0, 'overdue' => 0],
            'MEDIUM'    => ['total' => 0, 'met' => 0, 'overdue' => 0],
            'LOW'       => ['total' => 0, 'met' => 0, 'overdue' => 0],
        ];

        foreach ($slaRows as $row) {
            $prio = strtoupper($row['prioritas'] ?: 'MEDIUM');
            if (!isset($slaByPrioritas[$prio])) $slaByPrioritas[$prio] = ['total' => 0, 'met' => 0, 'overdue' => 0];

            $created = strtotime($row['tanggal_temuan']);
            $finished = ($row['status'] === 'SELESAI' && !empty($row['updated_at'])) ? strtotime($row['updated_at']) : time();
            $daysDiff = max(0, floor(($finished - $created) / 86400));

            $maxDays = match($prio) {
                'EMERGENCY' => 1,
                'HIGH'      => 3,
                'MEDIUM'    => 7,
                default     => 14
            };

            $slaByPrioritas[$prio]['total']++;
            if ($daysDiff <= $maxDays) {
                $metCount++;
                $slaByPrioritas[$prio]['met']++;
            } else {
                $overdueCount++;
                $slaByPrioritas[$prio]['overdue']++;
            }
        }

        $persentaseSelesai = $totalTemuan > 0 ? round(($temuanSelesai / $totalTemuan) * 100, 1) : 0;
        $persentaseSlaMet = ($metCount + $overdueCount) > 0 ? round(($metCount / ($metCount + $overdueCount)) * 100, 1) : 0;

        // Targets & Achievements
        $targetHarian = 15;
        $targetBulanan = 350;
        $targetTahunan = 4000;
        $achHarian = round(($temuanHariIni / max(1, $targetHarian)) * 100, 1);
        $achBulanan = round(($temuanBulanIni / max(1, $targetBulanan)) * 100, 1);
        $achTahunan = round(($temuanTahunIni / max(1, $targetTahunan)) * 100, 1);

        // 2. Chart Analytics Data
        $chartHarianLabels = [];
        $chartHarianTemuan = [];
        $chartHarianSelesai = [];
        for ($i = 13; $i >= 0; $i--) {
            $dt = date('Y-m-d', strtotime("-{$i} days"));
            $chartHarianLabels[] = date('d M', strtotime($dt));

            $bH = $applyFilters($db->table('temuan'))->where('DATE(tanggal_temuan)', $dt);
            $chartHarianTemuan[] = $bH->countAllResults();

            $bR = $applyFilters($db->table('temuan'))->where('status', 'SELESAI')->where('DATE(updated_at)', $dt);
            $chartHarianSelesai[] = $bR->countAllResults();
        }

        // Temuan per ULP
        $bUlp = $applyFilters($db->table('temuan'))
            ->select('ulps.nama_ulp, COUNT(temuan.id) as total, SUM(CASE WHEN temuan.status="SELESAI" THEN 1 ELSE 0 END) as selesai, SUM(CASE WHEN temuan.status="PROSES" THEN 1 ELSE 0 END) as proses, SUM(CASE WHEN temuan.status="BELUM" THEN 1 ELSE 0 END) as belum')
            ->join('ulps', 'ulps.id = temuan.ulp_id')
            ->groupBy('temuan.ulp_id');
        $ulpRankingRaw = self::safeGet($bUlp->orderBy('total', 'DESC'), null, 'TemuanRepository::getExecutiveAnalyticsData_ulp');

        // Temuan per Penyulang (Top 10)
        $bPenyulang = $applyFilters($db->table('temuan'))
            ->select('penyulang.nama_penyulang, COUNT(temuan.id) as total')
            ->join('penyulang', 'penyulang.id = temuan.penyulang_id')
            ->groupBy('temuan.penyulang_id');
        $penyulangChartRaw = self::safeGet($bPenyulang->orderBy('total', 'DESC')->limit(10), null, 'TemuanRepository::getExecutiveAnalyticsData_penyulang');

        // Temuan per Jenis
        $bJenis = $applyFilters($db->table('temuan'))->select('jenis_temuan, COUNT(id) as total')->groupBy('jenis_temuan');
        $jenisChartRaw = self::safeGet($bJenis, null, 'TemuanRepository::getExecutiveAnalyticsData_jenis');

        // Temuan per Pelaksana
        $bPelaksana = $applyFilters($db->table('temuan'))->select('pelaksana, COUNT(id) as total, SUM(CASE WHEN status="SELESAI" THEN 1 ELSE 0 END) as selesai')->groupBy('pelaksana');
        $pelaksanaChartRaw = self::safeGet($bPelaksana, null, 'TemuanRepository::getExecutiveAnalyticsData_pelaksana');

        // Temuan per Prioritas
        $bPrioritas = $applyFilters($db->table('temuan'))->select('prioritas, COUNT(id) as total')->groupBy('prioritas');
        $prioritasChartRaw = self::safeGet($bPrioritas, null, 'TemuanRepository::getExecutiveAnalyticsData_prioritas');

        // 3. Petugas Ranking
        $monthVal = !empty($filters['tanggal_mulai']) ? (int)date('m', strtotime($filters['tanggal_mulai'])) : date('n');
        $yearVal = !empty($filters['tanggal_mulai']) ? (int)date('Y', strtotime($filters['tanggal_mulai'])) : date('Y');
        $temuanModel = new \App\Models\TemuanModel();
        $topInputOfficers = $temuanModel->getTopInputOfficers($monthVal, $yearVal, $userUlpId);
        $topUpdateOfficers = $temuanModel->getTopUpdateOfficers($monthVal, $yearVal, $userUlpId);

        // 4. GIS Heatmap & Marker Pins
        $bMap = $applyFilters($db->table('temuan'))
            ->select('temuan.id, temuan.nomor_temuan, temuan.jenis_temuan, temuan.pelaksana, temuan.prioritas, temuan.status, temuan.latitude, temuan.longitude, temuan.alamat, temuan.detail_temuan, ulps.nama_ulp, penyulang.nama_penyulang')
            ->join('ulps', 'ulps.id = temuan.ulp_id', 'left')
            ->join('penyulang', 'penyulang.id = temuan.penyulang_id', 'left')
            ->where('temuan.latitude IS NOT NULL')
            ->where('temuan.longitude IS NOT NULL');
        $mapPins = self::safeGet($bMap, null, 'TemuanRepository::getExecutiveAnalyticsData_map');

        return [
            'timestamp'             => date('Y-m-d H:i:s'),
            'kpi'                   => [
                'total_temuan'         => $totalTemuan,
                'temuan_hari_ini'      => $temuanHariIni,
                'temuan_minggu_ini'    => $temuanMingguIni,
                'temuan_bulan_ini'     => $temuanBulanIni,
                'temuan_tahun_ini'     => $temuanTahunIni,
                'belum_dikerjakan'     => $temuanBelum,
                'proses'               => $temuanProses,
                'selesai'              => $temuanSelesai,
                'overdue'              => $overdueCount,
                'persentase_selesai'   => $persentaseSelesai,
                'persentase_sla_met'   => $persentaseSlaMet,
                'target_harian'        => $targetHarian,
                'target_bulanan'       => $targetBulanan,
                'target_tahunan'       => $targetTahunan,
                'ach_harian'           => $achHarian,
                'ach_bulanan'          => $achBulanan,
                'ach_tahunan'          => $achTahunan,
            ],
            'charts'                => [
                'harian'               => [
                    'labels'  => $chartHarianLabels,
                    'temuan'  => $chartHarianTemuan,
                    'selesai' => $chartHarianSelesai
                ],
                'ulp'                  => $ulpRankingRaw,
                'penyulang'            => $penyulangChartRaw,
                'jenis'                => $jenisChartRaw,
                'pelaksana'            => $pelaksanaChartRaw,
                'prioritas'            => $prioritasChartRaw,
                'status'               => [
                    'BELUM'   => $temuanBelum,
                    'PROSES'  => $temuanProses,
                    'SELESAI' => $temuanSelesai
                ]
            ],
            'sla'                   => [
                'met'      => $metCount,
                'overdue'  => $overdueCount,
                'details'  => $slaByPrioritas
            ],
            'ulp_ranking'           => $ulpRankingRaw,
            'top_input_officers'    => array_slice($topInputOfficers, 0, 10),
            'bottom_input_officers' => array_slice(array_reverse($topInputOfficers), 0, 10),
            'top_update_officers'   => array_slice($topUpdateOfficers, 0, 10),
            'map_pins'              => $mapPins
        ];
    }
}
