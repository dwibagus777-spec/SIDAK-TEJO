<?php

namespace App\Repositories;

use App\Models\TemuanModel;

class TemuanRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new TemuanModel());
    }

    /**
     * Dapatkan nomor temuan berikutnya berdasarkan tahun
     * Contoh: STJ-2026-000001
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

        if ($lastTemuan) {
            $lastNum = (int) substr($lastTemuan['nomor_temuan'], -6);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix . str_pad((string) $nextNum, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Detail Temuan dengan Join ULP, Penyulang, Section, User Pembuat/Pengupdate
     */
    public function getDetail(int $id, ?int $ulpIdFilter = null): ?array
    {
        $builder = $this->model
            ->select('temuan.*, ulps.nama_ulp, penyulang.nama_penyulang, sections.nama_section, 
                      c.nama as creator_name, u.nama as updater_name')
            ->join('ulps', 'ulps.id = temuan.ulp_id')
            ->join('penyulang', 'penyulang.id = temuan.penyulang_id')
            ->join('sections', 'sections.id = temuan.section_id')
            ->join('users c', 'c.id = temuan.created_by', 'left')
            ->join('users u', 'u.id = temuan.updated_by', 'left')
            ->where('temuan.id', $id);

        if ($ulpIdFilter !== null) {
            $builder->where('temuan.ulp_id', $ulpIdFilter);
        }

        return $builder->first();
    }

    /**
     * Query Server-Side DataTables untuk Temuan
     */
    public function getDataTables(array $postData, ?int $ulpIdFilter = null, ?string $jenisTemuanFilter = null): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('temuan')
            ->select('temuan.id, temuan.nomor_temuan, temuan.jenis_temuan, temuan.pelaksana, 
                      temuan.prioritas, temuan.potensi_gangguan, temuan.tanggal_temuan, temuan.status, 
                      temuan.detail_temuan, temuan.foto, temuan.foto_path,
                      ulps.nama_ulp, penyulang.nama_penyulang, sections.nama_section')
            ->join('ulps', 'ulps.id = temuan.ulp_id')
            ->join('penyulang', 'penyulang.id = temuan.penyulang_id')
            ->join('sections', 'sections.id = temuan.section_id')
            ->where('temuan.deleted_at IS NULL');

        if ($ulpIdFilter !== null) {
            $builder->where('temuan.ulp_id', $ulpIdFilter);
        } elseif (!empty($postData['ulp_id'])) {
            $builder->where('temuan.ulp_id', (int)$postData['ulp_id']);
        }

        if ($jenisTemuanFilter !== null) {
            $builder->where('temuan.jenis_temuan', $jenisTemuanFilter);
        } elseif (!empty($postData['jenis_temuan'])) {
            $builder->where('temuan.jenis_temuan', $postData['jenis_temuan']);
        }

        if (!empty($postData['pelaksana'])) {
            $builder->where('temuan.pelaksana', $postData['pelaksana']);
        }

        if (!empty($postData['prioritas'])) {
            $builder->where('temuan.prioritas', $postData['prioritas']);
        }

        if (!empty($postData['status'])) {
            $statusVal = strtoupper($postData['status']);
            if ($statusVal === 'BELUM SELESAI') {
                $builder->where('temuan.status !=', 'SELESAI');
            } elseif ($statusVal === 'SUDAH SELESAI' || $statusVal === 'SELESAI') {
                $builder->where('temuan.status', 'SELESAI');
            } else {
                $builder->where('temuan.status', $statusVal);
            }
        }

        if (!empty($postData['penyulang_id'])) {
            $builder->where('temuan.penyulang_id', (int)$postData['penyulang_id']);
        }

        if (!empty($postData['section_id'])) {
            $builder->where('temuan.section_id', (int)$postData['section_id']);
        }

        // Count Total
        $baseTotalQuery = $db->table('temuan')->where('deleted_at IS NULL');
        if ($ulpIdFilter !== null) {
            $baseTotalQuery->where('ulp_id', $ulpIdFilter);
        }
        $totalRecords = $baseTotalQuery->countAllResults();

        // Search
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

        // Count Filtered
        $totalFiltered = $builder->countAllResults(false);

        // Order
        $orderColumnIdx = $postData['order'][0]['column'] ?? 0;
        $orderDir = $postData['order'][0]['dir'] ?? 'desc';
        
        $columnsMap = [
            0 => 'temuan.nomor_temuan',
            1 => 'penyulang.nama_penyulang',
            2 => 'sections.nama_section',
            3 => 'temuan.jenis_temuan',
            4 => 'temuan.id', // for foto
            5 => 'temuan.prioritas',
            6 => 'temuan.tanggal_temuan',
            7 => 'temuan.status',
        ];
        
        $orderColumn = $columnsMap[$orderColumnIdx] ?? 'temuan.id';
        $builder->orderBy($orderColumn, $orderDir);

        // Limit
        $start = $postData['start'] ?? 0;
        $length = $postData['length'] ?? 10;
        if ($length != -1) {
            $builder->limit($length, $start);
        }

        $data = $builder->get()->getResultArray();

        return [
            'draw' => intval($postData['draw'] ?? 0),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data
        ];
    }

    /**
     * Dapatkan data temuan terfilter untuk Pusat Laporan
     */
    public function getFilteredTemuan(array $filters, ?int $ulpIdFilter = null): array
    {
        $builder = $this->model
            ->select('temuan.*, ulps.nama_ulp, penyulang.nama_penyulang, sections.nama_section')
            ->join('ulps', 'ulps.id = temuan.ulp_id')
            ->join('penyulang', 'penyulang.id = temuan.penyulang_id')
            ->join('sections', 'sections.id = temuan.section_id');

        if ($ulpIdFilter !== null) {
            $builder->where('temuan.ulp_id', $ulpIdFilter);
        } elseif (!empty($filters['ulp_id'])) {
            $builder->where('temuan.ulp_id', $filters['ulp_id']);
        }

        if (!empty($filters['tanggal_awal'])) {
            $builder->where('temuan.tanggal_temuan >=', $filters['tanggal_awal']);
        }

        if (!empty($filters['tanggal_akhir'])) {
            $builder->where('temuan.tanggal_temuan <=', $filters['tanggal_akhir']);
        }

        if (!empty($filters['penyulang_id'])) {
            $builder->where('temuan.penyulang_id', $filters['penyulang_id']);
        }

        if (!empty($filters['section_id'])) {
            $builder->where('temuan.section_id', $filters['section_id']);
        }

        if (!empty($filters['pelaksana'])) {
            $builder->where('temuan.pelaksana', $filters['pelaksana']);
        }

        if (!empty($filters['prioritas'])) {
            $builder->where('temuan.prioritas', $filters['prioritas']);
        }

        if (!empty($filters['jenis_temuan'])) {
            $builder->where('temuan.jenis_temuan', $filters['jenis_temuan']);
        }

        if (!empty($filters['potensi_gangguan'])) {
            $builder->where('temuan.potensi_gangguan', $filters['potensi_gangguan']);
        }

        if (!empty($filters['status'])) {
            $builder->where('temuan.status', $filters['status']);
        }

        return $builder->orderBy('temuan.tanggal_temuan', 'DESC')->findAll();
    }

    /**
     * Identifikasi Gangguan: cari temuan berdasarkan penyulang dan potensi gangguan
     */
    public function getIdentifikasiGangguan(int $penyulangId, string $potensiGangguan, ?string $jenisTemuanFilter = null): array
    {
        $builder = $this->model
            ->select('temuan.*, sections.nama_section')
            ->join('sections', 'sections.id = temuan.section_id')
            ->where('temuan.penyulang_id', $penyulangId)
            ->where('temuan.potensi_gangguan', $potensiGangguan)
            ->where('temuan.deleted_at IS NULL');

        if ($jenisTemuanFilter !== null) {
            $builder->where('temuan.jenis_temuan', $jenisTemuanFilter);
        }

        return $builder->orderBy('temuan.id', 'DESC')->findAll();
    }

    /**
     * Identifikasi Gangguan: ranking section berdasarkan jumlah temuan
     */
    public function getRankingSectionsForIdentifikasi(int $penyulangId, string $potensiGangguan, ?string $jenisTemuanFilter = null): array
    {
        $builder = $this->model
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
            ->findAll();
    }

    /**
     * Statistik Umum Dashboard (Card Counters)
     */
    public function getDashboardStats(?int $ulpIdFilter = null, ?string $roleFilter = null): array
    {
        $cacheKey = "dashboard_stats_" . ($ulpIdFilter ?? 'all') . "_" . ($roleFilter ?? 'all');
        return cache()->remember($cacheKey, 600, function() use ($ulpIdFilter, $roleFilter) {
            $db = \Config\Database::connect();
            
            $baseQuery = $db->table('temuan')->where('deleted_at IS NULL');
            
            if ($ulpIdFilter !== null) {
                $baseQuery->where('ulp_id', $ulpIdFilter);
            }

            // PDKB can only see their assigned work
            if ($roleFilter === 'pdkb') {
                $baseQuery->where('pelaksana', 'PDKB');
            } elseif ($roleFilter === 'har_gardu') {
                $baseQuery->where('pelaksana', 'HAR GARDU');
            } elseif ($roleFilter === 'har_konstruksi') {
                $baseQuery->where('pelaksana', 'HAR KONSTRUKSI');
            } elseif ($roleFilter === 'har_row') {
                $baseQuery->where('pelaksana', 'HAR ROW');
            } elseif ($roleFilter === 'yantek') {
                $baseQuery->where('pelaksana', 'YANTEK');
            }

            $totalTemuan = (clone $baseQuery)->countAllResults();
            
            $pdkb = (clone $baseQuery)->where('pelaksana', 'PDKB')->countAllResults();
            $harGardu = (clone $baseQuery)->where('pelaksana', 'HAR GARDU')->countAllResults();
            $harKonstruksi = (clone $baseQuery)->where('pelaksana', 'HAR KONSTRUKSI')->countAllResults();
            $harRow = (clone $baseQuery)->where('pelaksana', 'HAR ROW')->countAllResults();
            $harCrane = (clone $baseQuery)->where('pelaksana', 'HAR CRANE')->countAllResults();
            $yantek = (clone $baseQuery)->where('pelaksana', 'YANTEK')->countAllResults();

            $emergency = (clone $baseQuery)->where('prioritas', 'EMERGENCY')->countAllResults();
            $high = (clone $baseQuery)->where('prioritas', 'HIGH')->countAllResults();
            $medium = (clone $baseQuery)->where('prioritas', 'MEDIUM')->countAllResults();

            $belum = (clone $baseQuery)->where('status', 'BELUM')->countAllResults();
            $selesai = (clone $baseQuery)->where('status', 'SELESAI')->countAllResults();

            return [
                'total' => $totalTemuan,
                'pdkb' => $pdkb,
                'har_gardu' => $harGardu,
                'har_konstruksi' => $harKonstruksi,
                'har_row' => $harRow,
                'har_crane' => $harCrane,
                'yantek' => $yantek,
                'emergency' => $emergency,
                'high' => $high,
                'medium' => $medium,
                'belum' => $belum,
                'selesai' => $selesai
            ];
        });
    }

    /**
     * Temuan Bulanan untuk Grafik (Chart.js)
     */
    public function getMonthlyStats(?int $ulpIdFilter = null): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('temuan')
            ->select("DATE_FORMAT(tanggal_temuan, '%Y-%m') as bulan, COUNT(id) as total")
            ->where('deleted_at IS NULL');

        if ($ulpIdFilter !== null) {
            $builder->where('ulp_id', $ulpIdFilter);
        }

        $result = $builder->groupBy("bulan")
            ->orderBy("bulan", "ASC")
            ->limit(12)
            ->get()
            ->getResultArray();

        return $result;
    }

    /**
     * Temuan per ULP untuk Grafik (Chart.js)
     */
    public function getUlpStats(?int $ulpIdFilter = null): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('temuan')
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
        $db = \Config\Database::connect();
        $builder = $db->table('temuan')
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
        $db = \Config\Database::connect();
        $builder = $db->table('temuan')
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
        $db = \Config\Database::connect();
        $builder = $db->table('temuan')
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
        $db = \Config\Database::connect();
        $builder = $db->table('temuan')
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
            ->where('longitude IS NOT NULL');

        if ($ulpIdFilter !== null) {
            $builder->where('ulp_id', $ulpIdFilter);
        }

        return $builder->findAll();
    }
}
