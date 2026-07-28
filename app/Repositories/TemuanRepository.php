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
            ->join('ulps', 'ulps.id = temuan.ulp_id')
            ->join('penyulang', 'penyulang.id = temuan.penyulang_id')
            ->join('sections', 'sections.id = temuan.section_id');

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

        if (!empty($filters['tanggal_awal'])) {
            $builder->where('temuan.tanggal_temuan >=', $filters['tanggal_awal']);
        }

        if (!empty($filters['tanggal_akhir'])) {
            $builder->where('temuan.tanggal_temuan <=', $filters['tanggal_akhir']);
        }

        if (!empty($filters['status'])) {
            $statusVal = strtoupper($filters['status']);
            if ($statusVal === 'BELUM SELESAI') {
                $builder->where('temuan.status !=', 'SELESAI');
            } elseif ($statusVal === 'SUDAH SELESAI' || $statusVal === 'SELESAI') {
                $builder->where('temuan.status', 'SELESAI');
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

        return $builder->get()->getRowArray();
    }

    /**
     * Query Server-Side DataTables untuk Temuan (Hostinger MySQL Optimized)
     */
    public function getDataTables(array $postData, ?int $ulpIdFilter = null, ?string $jenisTemuanFilter = null): array
    {
        $builder = $this->getJoinedBuilder(false)
            ->select('temuan.id, temuan.nomor_temuan, temuan.jenis_temuan, temuan.pelaksana, 
                      temuan.prioritas, temuan.potensi_gangguan, temuan.tanggal_temuan, temuan.status, 
                      temuan.detail_temuan, temuan.foto, temuan.foto_path, temuan.created_at,
                      ulps.nama_ulp, penyulang.nama_penyulang, sections.nama_section');

        $this->applyTemuanFilters($builder, $postData, $ulpIdFilter, $jenisTemuanFilter);

        // Count Total Records (Unfiltered Base Count)
        $baseTotalQuery = $this->getBuilder('temuan')->where('deleted_at IS NULL');
        if ($ulpIdFilter !== null) {
            $baseTotalQuery->where('ulp_id', $ulpIdFilter);
        }
        $totalRecords = $baseTotalQuery->countAllResults();

        // Search Filter
        $searchValue = $postData['search']['value'] ?? '';
        if ($searchValue !== '') {
            $builder->groupStart()
                ->like('temuan.nomor_temuan', $searchValue)
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
        $totalFiltered = $builder->countAllResults(false);

        // Order & Pagination
        $orderColumnIdx = $postData['order'][0]['column'] ?? 0;
        $orderDir = $postData['order'][0]['dir'] ?? 'desc';
        
        $columnsMap = [
            0 => 'temuan.nomor_temuan',
            1 => 'penyulang.nama_penyulang',
            2 => 'sections.nama_section',
            3 => 'temuan.jenis_temuan',
            4 => 'temuan.id',
            5 => 'temuan.prioritas',
            6 => 'temuan.tanggal_temuan',
            7 => 'temuan.status',
        ];
        
        $orderColumn = $columnsMap[$orderColumnIdx] ?? 'temuan.id';
        $builder->orderBy($orderColumn, $orderDir);

        $start = (int)($postData['start'] ?? 0);
        $length = (int)($postData['length'] ?? 10);
        if ($length != -1) {
            $builder->limit($length, $start);
        }

        return [
            'draw'            => (int)($postData['draw'] ?? 0),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data'            => $builder->get()->getResultArray()
        ];
    }

    /**
     * Dapatkan data temuan terfilter untuk Pusat Laporan
     */
    public function getFilteredTemuan(array $filters, ?int $ulpIdFilter = null): array
    {
        $builder = $this->getJoinedBuilder(false)
            ->select('temuan.*, ulps.nama_ulp, penyulang.nama_penyulang, sections.nama_section');

        $this->applyTemuanFilters($builder, $filters, $ulpIdFilter, null);

        return $builder->orderBy('temuan.tanggal_temuan', 'DESC')->get()->getResultArray();
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

        return $builder->orderBy('temuan.id', 'DESC')->get()->getResultArray();
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

        return $builder->groupBy('temuan.section_id')
            ->orderBy('total_temuan', 'DESC')
            ->get()->getResultArray();
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

            $row = $builder->get()->getRowArray() ?: [];

            return [
                'total'          => (int)($row['total'] ?? 0),
                'pdkb'           => (int)($row['pdkb'] ?? 0),
                'har_gardu'      => (int)($row['har_gardu'] ?? 0),
                'har_konstruksi' => (int)($row['har_konstruksi'] ?? 0),
                'har_row'        => (int)($row['har_row'] ?? 0),
                'har_crane'      => (int)($row['har_crane'] ?? 0),
                'yantek'         => (int)($row['yantek'] ?? 0),
                'emergency'      => (int)($row['emergency'] ?? 0),
                'high'           => (int)($row['high'] ?? 0),
                'medium'         => (int)($row['medium'] ?? 0),
                'belum'          => (int)($row['belum'] ?? 0),
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

        return $builder->groupBy("bulan")
            ->orderBy("bulan", "ASC")
            ->limit(12)
            ->get()
            ->getResultArray();
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

        return $builder->groupBy('temuan.ulp_id')
            ->get()
            ->getResultArray();
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

        return $builder->groupBy('temuan.penyulang_id')
            ->orderBy('total', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();
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

        return $builder->groupBy('pelaksana')
            ->get()
            ->getResultArray();
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

        return $builder->groupBy('prioritas')
            ->get()
            ->getResultArray();
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

        return $builder->groupBy('potensi_gangguan')
            ->get()
            ->getResultArray();
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
            ->where('deleted_at IS NULL');

        if ($ulpIdFilter !== null) {
            $builder->where('ulp_id', $ulpIdFilter);
        }

        return $builder->findAll();
    }
}
