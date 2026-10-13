<?php

namespace App\Models;

use CodeIgniter\Model;

class InspeksiKubikelRekomendasiModel extends Model
{
    protected $table            = 'inspeksi_kubikel_rekomendasi';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'temuan_id',
        'penerima',
        'penerima_user_id',
        'deskripsi',
        'status',
        'waktu_kirim',
        'waktu_baca',
        'waktu_selesai',
        'catatan_penerima',
        'notification_id',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'temuan_id'       => 'required|integer',
        'penerima'        => 'in_list[ULP,HAR_GTT,BEYOND_KWH,ADMINISTRATOR,LAINNYA]',
        'deskripsi'       => 'required',
        'status'          => 'in_list[TERKIRIM,DIBACA,DIPROSES,SELESAI,DITOLAK]',
    ];

    public function getByTemuan(int $temuanId): array
    {
        return $this->where('temuan_id', $temuanId)
            ->orderBy('waktu_kirim', 'DESC')
            ->findAll();
    }

    public function getByPenerimaUser(int $userId, string $status = null): array
    {
        if (!$this->db->tableExists($this->table)) {
            return [];
        }

        $builder = $this
            ->select('inspeksi_kubikel_rekomendasi.*, inspeksi_kubikel_temuan.komponen AS temuan_komponen, inspeksi_kubikel_temuan.fasa AS temuan_fasa, inspeksi_kubikel_temuan.posisi AS temuan_posisi, inspeksi_kubikel_temuan.deskripsi_temuan, inspeksi_kubikel_temuan.urgensi AS temuan_urgensi, inspeksi_kubikel_temuan.status_temuan AS status_temuan, inspeksi_kubikel.nomor_inspeksi, inspeksi_kubikel.tgl_inspeksi')
            ->join('inspeksi_kubikel_temuan', 'inspeksi_kubikel_temuan.id = inspeksi_kubikel_rekomendasi.temuan_id')
            ->join('inspeksi_kubikel', 'inspeksi_kubikel.id = inspeksi_kubikel_temuan.inspeksi_kubikel_id')
            ->where('inspeksi_kubikel_rekomendasi.penerima', 'BEYOND_KWH')
            ->where('inspeksi_kubikel_rekomendasi.penerima_user_id', $userId)
            ->where('inspeksi_kubikel_temuan.deleted_at', null)
            ->where('inspeksi_kubikel.deleted_at', null);
        if ($status) {
            $builder->where('inspeksi_kubikel_rekomendasi.status', $status);
        }
        return $builder->orderBy('inspeksi_kubikel_rekomendasi.waktu_kirim', 'DESC')->findAll();
    }

    public function getAllBeyondRekomendasi(string $status = null): array
    {
        if (!$this->db->tableExists($this->table)) {
            return [];
        }

        $builder = $this
            ->select('inspeksi_kubikel_rekomendasi.*, inspeksi_kubikel_temuan.komponen AS temuan_komponen, inspeksi_kubikel_temuan.fasa AS temuan_fasa, inspeksi_kubikel_temuan.posisi AS temuan_posisi, inspeksi_kubikel_temuan.deskripsi_temuan, inspeksi_kubikel_temuan.urgensi AS temuan_urgensi, inspeksi_kubikel_temuan.status_temuan AS status_temuan, inspeksi_kubikel.nomor_inspeksi, inspeksi_kubikel.tgl_inspeksi, users.nama_pegawai AS penerima_nama')
            ->join('inspeksi_kubikel_temuan', 'inspeksi_kubikel_temuan.id = inspeksi_kubikel_rekomendasi.temuan_id')
            ->join('inspeksi_kubikel', 'inspeksi_kubikel.id = inspeksi_kubikel_temuan.inspeksi_kubikel_id')
            ->join('users', 'users.id = inspeksi_kubikel_rekomendasi.penerima_user_id', 'left')
            ->where('inspeksi_kubikel_rekomendasi.penerima', 'BEYOND_KWH')
            ->where('inspeksi_kubikel_temuan.deleted_at', null)
            ->where('inspeksi_kubikel.deleted_at', null);
        if ($status) {
            $builder->where('inspeksi_kubikel_rekomendasi.status', $status);
        }
        return $builder->orderBy('inspeksi_kubikel_rekomendasi.waktu_kirim', 'DESC')->findAll();
    }

    public function getUnreadByPenerima(int $userId): array
    {
        return $this->where('penerima_user_id', $userId)
            ->where('status', 'TERKIRIM')
            ->where('waktu_baca IS NULL')
            ->orderBy('waktu_kirim', 'DESC')
            ->findAll();
    }

    public function markAsRead(int $id, int $userId): bool
    {
        return $this->update($id, [
            'status'      => 'DIBACA',
            'waktu_baca'  => date('Y-m-d H:i:s'),
            'penerima_user_id' => $userId,
        ]);
    }

    /**
     * Executive KPI Aggregates with optional filters (role-aware)
     */
    public function getDashboardStats(?int $userId = null, array $filters = []): array
    {
        $default = [
            'total'          => 0,
            'belum'          => 0, // TERKIRIM + DIBACA
            'proses'         => 0, // DIPROSES
            'selesai'        => 0, // SELESAI
            'ditolak'        => 0, // DITOLAK
            'kritis_terbuka' => 0, // Urgensi KRITIS/TINGGI belum SELESAI
            'completion_rate'=> 0,
        ];

        if (!$this->db->tableExists($this->table)) {
            return $default;
        }

        $tbl = $this->db->prefixTable($this->table);
        $tblTemuan = $this->db->prefixTable('inspeksi_kubikel_temuan');
        $tblInspeksi = $this->db->prefixTable('inspeksi_kubikel');

        $builder = $this
            ->select("
                COUNT(*) as total,
                SUM(CASE WHEN {$tbl}.status IN ('TERKIRIM', 'DIBACA') THEN 1 ELSE 0 END) as belum,
                SUM(CASE WHEN {$tbl}.status = 'DIPROSES' THEN 1 ELSE 0 END) as proses,
                SUM(CASE WHEN {$tbl}.status = 'SELESAI' THEN 1 ELSE 0 END) as selesai,
                SUM(CASE WHEN {$tbl}.status = 'DITOLAK' THEN 1 ELSE 0 END) as ditolak,
                SUM(CASE WHEN {$tbl}.status != 'SELESAI' AND {$tblTemuan}.urgensi IN ('KRITIS', 'TINGGI') THEN 1 ELSE 0 END) as kritis_terbuka
            ", false)
            ->join($tblTemuan, "{$tblTemuan}.id = {$tbl}.temuan_id")
            ->join($tblInspeksi, "{$tblInspeksi}.id = {$tblTemuan}.inspeksi_kubikel_id")
            ->where("{$tbl}.penerima", 'BEYOND_KWH')
            ->where("{$tblTemuan}.deleted_at", null)
            ->where("{$tblInspeksi}.deleted_at", null);

        if ($userId !== null) {
            $builder->where("{$tbl}.penerima_user_id", $userId);
        }

        $this->applyFiltersToBuilder($builder, $filters);

        $row = $builder->first();
        if (!$row) {
            return $default;
        }

        $total = (int)($row['total'] ?? 0);
        $selesai = (int)($row['selesai'] ?? 0);
        $rate = $total > 0 ? round(($selesai / $total) * 100, 1) : 0;

        return [
            'total'          => $total,
            'belum'          => (int)($row['belum'] ?? 0),
            'proses'         => (int)($row['proses'] ?? 0),
            'selesai'        => $selesai,
            'ditolak'        => (int)($row['ditolak'] ?? 0),
            'kritis_terbuka' => (int)($row['kritis_terbuka'] ?? 0),
            'completion_rate'=> $rate,
        ];
    }

    /**
     * Monthly trend analytics for recommendations and completions (Last 6 Months)
     */
    public function getMonthlyTrend(?int $userId = null, array $filters = []): array
    {
        if (!$this->db->tableExists($this->table)) {
            return ['labels' => [], 'masuk' => [], 'selesai' => []];
        }

        // Generate last 6 months list YYYY-MM
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $mKey = date('Y-m', strtotime("-{$i} month"));
            $mLabel = date('M Y', strtotime("-{$i} month"));
            $months[$mKey] = ['label' => $mLabel, 'masuk' => 0, 'selesai' => 0];
        }

        $builder = $this->builder()
            ->select("
                DATE_FORMAT({$this->table}.waktu_kirim, '%Y-%m') as bulan,
                COUNT(*) as masuk_cnt,
                SUM(CASE WHEN {$this->table}.status = 'SELESAI' THEN 1 ELSE 0 END) as selesai_cnt
            ")
            ->join('inspeksi_kubikel_temuan', "inspeksi_kubikel_temuan.id = {$this->table}.temuan_id")
            ->join('inspeksi_kubikel', 'inspeksi_kubikel.id = inspeksi_kubikel_temuan.inspeksi_kubikel_id')
            ->where("{$this->table}.penerima", 'BEYOND_KWH')
            ->where("{$this->table}.waktu_kirim >=", date('Y-m-01 00:00:00', strtotime('-5 months')))
            ->where('inspeksi_kubikel_temuan.deleted_at', null)
            ->where('inspeksi_kubikel.deleted_at', null);

        if ($userId !== null) {
            $builder->where("{$this->table}.penerima_user_id", $userId);
        }

        $this->applyFiltersToBuilder($builder, $filters);

        $rows = $builder->groupBy("DATE_FORMAT({$this->table}.waktu_kirim, '%Y-%m')")->get()->getResultArray();

        foreach ($rows as $r) {
            $b = $r['bulan'] ?? '';
            if (isset($months[$b])) {
                $months[$b]['masuk'] = (int)$r['masuk_cnt'];
                $months[$b]['selesai'] = (int)$r['selesai_cnt'];
            }
        }

        $labels = [];
        $masuk = [];
        $selesai = [];
        foreach ($months as $item) {
            $labels[] = $item['label'];
            $masuk[] = $item['masuk'];
            $selesai[] = $item['selesai'];
        }

        return [
            'labels'  => $labels,
            'masuk'   => $masuk,
            'selesai' => $selesai,
        ];
    }

    /**
     * Distribution by Status (TERKIRIM, DIBACA, DIPROSES, SELESAI, DITOLAK)
     */
    public function getStatusDistribution(?int $userId = null, array $filters = []): array
    {
        $distribution = [
            'TERKIRIM' => 0,
            'DIBACA'   => 0,
            'DIPROSES' => 0,
            'SELESAI'  => 0,
            'DITOLAK'  => 0,
        ];

        if (!$this->db->tableExists($this->table)) {
            return $distribution;
        }

        $builder = $this->builder()
            ->select("{$this->table}.status, COUNT(*) as cnt")
            ->join('inspeksi_kubikel_temuan', "inspeksi_kubikel_temuan.id = {$this->table}.temuan_id")
            ->join('inspeksi_kubikel', 'inspeksi_kubikel.id = inspeksi_kubikel_temuan.inspeksi_kubikel_id')
            ->where("{$this->table}.penerima", 'BEYOND_KWH')
            ->where('inspeksi_kubikel_temuan.deleted_at', null)
            ->where('inspeksi_kubikel.deleted_at', null);

        if ($userId !== null) {
            $builder->where("{$this->table}.penerima_user_id", $userId);
        }

        $this->applyFiltersToBuilder($builder, $filters);

        $rows = $builder->groupBy("{$this->table}.status")->get()->getResultArray();
        foreach ($rows as $r) {
            if (isset($distribution[$r['status']])) {
                $distribution[$r['status']] = (int)$r['cnt'];
            }
        }

        return $distribution;
    }

    /**
     * Distribution by Urgensi / Prioritas (RENDAH, SEDANG, TINGGI, KRITIS)
     */
    public function getPriorityDistribution(?int $userId = null, array $filters = []): array
    {
        $distribution = [
            'KRITIS'  => 0,
            'TINGGI'  => 0,
            'SEDANG'  => 0,
            'RENDAH'  => 0,
        ];

        if (!$this->db->tableExists($this->table)) {
            return $distribution;
        }

        $builder = $this->builder()
            ->select("inspeksi_kubikel_temuan.urgensi, COUNT(*) as cnt")
            ->join('inspeksi_kubikel_temuan', "inspeksi_kubikel_temuan.id = {$this->table}.temuan_id")
            ->join('inspeksi_kubikel', 'inspeksi_kubikel.id = inspeksi_kubikel_temuan.inspeksi_kubikel_id')
            ->where("{$this->table}.penerima", 'BEYOND_KWH')
            ->where('inspeksi_kubikel_temuan.deleted_at', null)
            ->where('inspeksi_kubikel.deleted_at', null);

        if ($userId !== null) {
            $builder->where("{$this->table}.penerima_user_id", $userId);
        }

        $this->applyFiltersToBuilder($builder, $filters);

        $rows = $builder->groupBy("inspeksi_kubikel_temuan.urgensi")->get()->getResultArray();
        foreach ($rows as $r) {
            $urg = strtoupper((string)($r['urgensi'] ?? ''));
            if (isset($distribution[$urg])) {
                $distribution[$urg] = (int)$r['cnt'];
            }
        }

        return $distribution;
    }

    /**
     * Distribution / Ranking by ULP (if relation exists)
     */
    public function getUlpRanking(?int $userId = null, array $filters = []): array
    {
        if (!$this->db->tableExists($this->table) || !$this->db->tableExists('ulps')) {
            return [];
        }

        $builder = $this->builder()
            ->select("
                COALESCE(ulps.nama_ulp, 'Tanpa ULP') as nama_ulp,
                COUNT(*) as total,
                SUM(CASE WHEN {$this->table}.status = 'SELESAI' THEN 1 ELSE 0 END) as selesai,
                SUM(CASE WHEN {$this->table}.status != 'SELESAI' THEN 1 ELSE 0 END) as pending
            ")
            ->join('inspeksi_kubikel_temuan', "inspeksi_kubikel_temuan.id = {$this->table}.temuan_id")
            ->join('inspeksi_kubikel', 'inspeksi_kubikel.id = inspeksi_kubikel_temuan.inspeksi_kubikel_id')
            ->join('ulps', 'ulps.id = inspeksi_kubikel.ulp_id', 'left')
            ->where("{$this->table}.penerima", 'BEYOND_KWH')
            ->where('inspeksi_kubikel_temuan.deleted_at', null)
            ->where('inspeksi_kubikel.deleted_at', null);

        if ($userId !== null) {
            $builder->where("{$this->table}.penerima_user_id", $userId);
        }

        $this->applyFiltersToBuilder($builder, $filters);

        return $builder->groupBy('inspeksi_kubikel.ulp_id, ulps.nama_ulp')
            ->orderBy('total', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();
    }

    /**
     * Filtered list of recommendations for table display & drill-down
     */
    public function getFilteredRekomendasi(?int $userId = null, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        if (!$this->db->tableExists($this->table)) {
            return [];
        }

        $selectCols = [
            "{$this->table}.*",
            'inspeksi_kubikel_temuan.komponen AS temuan_komponen',
            'inspeksi_kubikel_temuan.fasa AS temuan_fasa',
            'inspeksi_kubikel_temuan.posisi AS temuan_posisi',
            'inspeksi_kubikel_temuan.deskripsi_temuan',
            'inspeksi_kubikel_temuan.urgensi AS temuan_urgensi',
            'inspeksi_kubikel_temuan.status_temuan',
            'inspeksi_kubikel.nomor_inspeksi',
            'inspeksi_kubikel.tgl_inspeksi',
            'inspeksi_kubikel.id_pel',
        ];

        $builder = $this
            ->join('inspeksi_kubikel_temuan', "inspeksi_kubikel_temuan.id = {$this->table}.temuan_id")
            ->join('inspeksi_kubikel', 'inspeksi_kubikel.id = inspeksi_kubikel_temuan.inspeksi_kubikel_id');

        if ($this->db->tableExists('ulps')) {
            $selectCols[] = 'ulps.nama_ulp';
            $builder->join('ulps', 'ulps.id = inspeksi_kubikel.ulp_id', 'left');
        }

        if ($this->db->tableExists('penyulang')) {
            $selectCols[] = 'penyulang.nama_penyulang';
            $builder->join('penyulang', 'penyulang.id = inspeksi_kubikel.penyulang_id', 'left');
        }

        if ($this->db->tableExists('users')) {
            $selectCols[] = 'users.nama_pegawai AS penerima_nama';
            $builder->join('users', "users.id = {$this->table}.penerima_user_id", 'left');
        }

        $builder->select(implode(', ', $selectCols))
            ->where("{$this->table}.penerima", 'BEYOND_KWH')
            ->where('inspeksi_kubikel_temuan.deleted_at', null)
            ->where('inspeksi_kubikel.deleted_at', null);

        if ($userId !== null) {
            $builder->where("{$this->table}.penerima_user_id", $userId);
        }

        $this->applyFiltersToBuilder($builder, $filters);

        // Sorting: Prioritaskan KRITIS & TERKIRIM
        $tblTemuan = $this->db->prefixTable('inspeksi_kubikel_temuan');
        $tbl = $this->db->prefixTable($this->table);
        $builder->orderBy("CASE 
            WHEN {$tblTemuan}.urgensi = 'KRITIS' THEN 1 
            WHEN {$tblTemuan}.urgensi = 'TINGGI' THEN 2 
            WHEN {$tblTemuan}.urgensi = 'SEDANG' THEN 3 
            ELSE 4 END", 'ASC', false);
        $builder->orderBy("{$tbl}.waktu_kirim", 'DESC');

        return $builder->findAll($limit, $offset);
    }

    /**
     * Get active Beyond KWH officers for admin filter dropdown
     */
    public function getBeyondOfficers(): array
    {
        if (!$this->db->tableExists('users')) {
            return [];
        }

        return $this->db->table('users')
            ->select('id, nama_pegawai, nama, username')
            ->where('role', 'beyond_kwh')
            ->where('status', 'AKTIF')
            ->orderBy('nama_pegawai', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Helper to apply sanitized dynamic filters to query builder
     */
    private function applyFiltersToBuilder($builder, array $filters): void
    {
        if (!empty($filters['status'])) {
            $status = strtoupper(trim((string)$filters['status']));
            if ($status === 'BELUM') {
                $builder->whereIn("{$this->table}.status", ['TERKIRIM', 'DIBACA']);
            } elseif (in_array($status, ['TERKIRIM', 'DIBACA', 'DIPROSES', 'SELESAI', 'DITOLAK'], true)) {
                $builder->where("{$this->table}.status", $status);
            }
        }

        if (!empty($filters['urgensi'])) {
            $urgensi = strtoupper(trim((string)$filters['urgensi']));
            if (in_array($urgensi, ['RENDAH', 'SEDANG', 'TINGGI', 'KRITIS'], true)) {
                $builder->where('inspeksi_kubikel_temuan.urgensi', $urgensi);
            }
        }

        if (!empty($filters['ulp_id'])) {
            $builder->where('inspeksi_kubikel.ulp_id', (int)$filters['ulp_id']);
        }

        if (!empty($filters['penyulang_id'])) {
            $builder->where('inspeksi_kubikel.penyulang_id', (int)$filters['penyulang_id']);
        }

        if (!empty($filters['petugas_id'])) {
            $builder->where("{$this->table}.penerima_user_id", (int)$filters['petugas_id']);
        }

        if (!empty($filters['start_date'])) {
            $builder->where("{$this->table}.waktu_kirim >=", $filters['start_date'] . ' 00:00:00');
        }

        if (!empty($filters['end_date'])) {
            $builder->where("{$this->table}.waktu_kirim <=", $filters['end_date'] . ' 23:59:59');
        }
    }
}