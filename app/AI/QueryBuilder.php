<?php

namespace App\AI;

use Config\Database;

class QueryBuilder
{
    /**
     * Build and execute database query based on intent & user role scoping
     */
    public function executeQuery(array $intentData, ?int $ulpIdFilter = null): array
    {
        $db = Database::connect();
        $intent = $intentData['intent'] ?? 'SUMMARY';

        switch ($intent) {
            case 'EMERGENCY_COUNT':
                $builder = $db->table('temuan t');
                $builder->where('t.deleted_at IS NULL');
                $builder->where('t.prioritas', 'EMERGENCY');
                $builder->where('t.status !=', 'SELESAI');
                if ($ulpIdFilter) $builder->where('t.ulp_id', $ulpIdFilter);
                $cnt = $builder->countAllResults();

                return [
                    'title' => 'Jumlah Temuan Emergency Aktif',
                    'count' => $cnt,
                    'action_url' => site_url('temuan?prioritas=EMERGENCY'),
                    'action_label' => 'Buka Detail Emergency'
                ];

            case 'HIGH_PRIORITY_COUNT':
                $builder = $db->table('temuan t');
                $builder->where('t.deleted_at IS NULL');
                $builder->where('t.prioritas', 'HIGH');
                $builder->where('t.status !=', 'SELESAI');
                if ($ulpIdFilter) $builder->where('t.ulp_id', $ulpIdFilter);
                $cnt = $builder->countAllResults();

                return [
                    'title' => 'Jumlah Temuan High Priority Aktif',
                    'count' => $cnt,
                    'action_url' => site_url('temuan?prioritas=HIGH'),
                    'action_label' => 'Buka High Priority'
                ];

            case 'COMPLETED_COUNT':
                $builder = $db->table('temuan t');
                $builder->where('t.deleted_at IS NULL');
                $builder->where('t.status', 'SELESAI');
                if ($ulpIdFilter) $builder->where('t.ulp_id', $ulpIdFilter);
                $cnt = $builder->countAllResults();

                return [
                    'title' => 'Jumlah Inspeksi Selesai (Tuntas)',
                    'count' => $cnt,
                    'action_url' => site_url('temuan?status=SELESAI'),
                    'action_label' => 'Lihat Temuan Selesai'
                ];

            case 'TOP_OFFICER':
                $builder = $db->table('temuan t');
                $builder->select('created_by_name, COUNT(id) as total_input');
                $builder->where('t.deleted_at IS NULL');
                if ($ulpIdFilter) $builder->where('t.ulp_id', $ulpIdFilter);
                $builder->groupBy('created_by_name');
                $builder->orderBy('total_input', 'DESC');
                $builder->limit(3);
                $query = $builder->get();
                $rows = $query ? $query->getResultArray() : [];

                return [
                    'title' => 'Top Petugas Inspeksi Teraktif',
                    'top_officers' => $rows,
                    'action_url' => site_url('dashboard'),
                    'action_label' => 'Buka Leaderboard'
                ];

            default:
                $totalTemuan = $db->table('temuan')->where('deleted_at IS NULL')->countAllResults();
                $totalSelesai = $db->table('temuan')->where('deleted_at IS NULL')->where('status', 'SELESAI')->countAllResults();

                return [
                    'title' => 'Ringkasan Sistem SIDAK TEJO',
                    'total_temuan' => $totalTemuan,
                    'total_selesai' => $totalSelesai,
                    'action_url' => site_url('dashboard'),
                    'action_label' => 'Buka Dashboard Utama'
                ];
        }
    }
}
