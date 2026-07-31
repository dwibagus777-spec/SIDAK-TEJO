<?php

namespace App\Repositories;

use CodeIgniter\Database\BaseResult;

class EccRepository
{
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function getKpiMetrics(?int $ulpIdFilter = null): array
    {
        $builder = $this->db->table('temuan t')->where('t.deleted_at IS NULL');
        if ($ulpIdFilter) {
            $builder->where('t.ulp_id', $ulpIdFilter);
        }

        $totalTemuan = (clone $builder)->countAllResults();
        $hariIni     = (clone $builder)->where('DATE(t.created_at)', date('Y-m-d'))->countAllResults();
        $mingguIni  = (clone $builder)->where('t.created_at >=', date('Y-m-d', strtotime('-7 days')))->countAllResults();
        $bulanIni   = (clone $builder)->where('MONTH(t.created_at)', date('n'))->where('YEAR(t.created_at)', date('Y'))->countAllResults();

        $emergency   = (clone $builder)->where('t.prioritas', 'EMERGENCY')->countAllResults();
        $high        = (clone $builder)->where('t.prioritas', 'HIGH')->countAllResults();
        $medium      = (clone $builder)->where('t.prioritas', 'MEDIUM')->countAllResults();

        $woAktif = 0;
        $woSelesai = 0;
        $woOverdue = 0;
        if ($this->db->tableExists('work_orders')) {
            $woBuilder   = $this->db->table('work_orders w')->where('w.deleted_at IS NULL');
            $woAktif     = (clone $woBuilder)->whereIn('w.status', ['OPEN', 'ASSIGNED', 'PROGRESS', 'WAITING_MATERIAL', 'WAITING_PADAM'])->countAllResults();
            $woSelesai   = (clone $woBuilder)->where('w.status', 'COMPLETED')->countAllResults();
            $woOverdue   = (clone $woBuilder)->where('w.status !=', 'COMPLETED')->where('w.target_selesai <', date('Y-m-d H:i:s'))->countAllResults();
        }

        return [
            'total_temuan' => $totalTemuan,
            'hari_ini'     => $hariIni,
            'minggu_ini'   => $mingguIni,
            'bulan_ini'    => $bulanIni,
            'wo_aktif'     => $woAktif,
            'wo_selesai'   => $woSelesai,
            'wo_overdue'   => $woOverdue,
            'emergency'    => $emergency,
            'high'         => $high,
            'medium'       => $medium,
        ];
    }

    public function getEmergencyWallItems(?int $ulpIdFilter = null): array
    {
        try {
            $builder = $this->db->table('temuan t');
            $builder->select('t.id, t.nomor_temuan, t.jenis_temuan, t.detail_temuan, t.status, t.created_at, u.nama_ulp, p.nama_penyulang');
            $builder->join('ulps u', 't.ulp_id = u.id', 'left');
            $builder->join('penyulang p', 't.penyulang_id = p.id', 'left');
            $builder->where('t.deleted_at IS NULL');
            $builder->where('t.prioritas', 'EMERGENCY');
            $builder->where('t.status !=', 'SELESAI');
            if ($ulpIdFilter) {
                $builder->where('t.ulp_id', $ulpIdFilter);
            }
            $builder->orderBy('t.id', 'DESC');

            $query = $builder->get(10);
            if ($query === false || !($query instanceof BaseResult)) {
                $error = $this->db->error();
                log_message('error', '[EccRepository::getEmergencyWallItems] Query gagal | Code: ' . ($error['code'] ?? 'N/A') . ' | Message: ' . ($error['message'] ?? 'Unknown'));
                return [];
            }

            return $query->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[EccRepository::getEmergencyWallItems] Exception: ' . $e->getMessage());
            return [];
        }
    }

    public function getUlpRankings(?int $ulpIdFilter = null): array
    {
        try {
            $builder = $this->db->table('ulps u');
            $builder->select('u.id, u.nama_ulp, COUNT(t.id) as total_temuan, SUM(CASE WHEN t.status = "SELESAI" THEN 1 ELSE 0 END) as total_selesai');
            $builder->join('temuan t', 't.ulp_id = u.id AND t.deleted_at IS NULL', 'left');
            if ($ulpIdFilter) {
                $builder->where('u.id', $ulpIdFilter);
            }
            $builder->groupBy('u.id, u.nama_ulp');
            $builder->orderBy('total_temuan', 'DESC');

            $query = $builder->get();
            if ($query === false || !($query instanceof BaseResult)) {
                $error = $this->db->error();
                log_message('error', '[EccRepository::getUlpRankings] Query gagal | Code: ' . ($error['code'] ?? 'N/A') . ' | Message: ' . ($error['message'] ?? 'Unknown'));
                return [];
            }

            return $query->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[EccRepository::getUlpRankings] Exception: ' . $e->getMessage());
            return [];
        }
    }
}
