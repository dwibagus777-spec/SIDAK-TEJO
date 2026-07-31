<?php

namespace App\Services;

use CodeIgniter\Database\ConnectionInterface;

/**
 * GamificationService — Phase 32
 * Handles: Points, Levels, Achievements, Rankings, Timeline logging
 */
class GamificationService
{
    private ConnectionInterface $db;

    // Point values per action
    private const POINTS = [
        'INPUT_TEMUAN'       => 10,
        'UPDATE_TEMUAN'      => 5,
        'SELESAI_TEMUAN'     => 20,
        'EMERGENCY_SELESAI'  => 50,
        'HIGH_SELESAI'       => 30,
        'UPLOAD_FOTO'        => 3,
        'REVIEW'             => 8,
        'VALIDASI'           => 10,
        'SLA_MET'            => 15,
        'LOGIN'              => 1,
    ];

    // Level thresholds
    private const LEVELS = [
        'diamond'  => 5000,
        'platinum' => 2000,
        'gold'     => 800,
        'silver'   => 250,
        'bronze'   => 0,
    ];

    // Achievement definitions [key => [name, icon, desc, points, condition]]
    private const ACHIEVEMENTS = [
        'first_temuan'     => ['name' => 'Temuan Pertama', 'icon' => 'fa-seedling', 'desc' => 'Input temuan pertama Anda', 'points' => 20, 'type' => 'count', 'field' => 'temuan_count', 'target' => 1],
        'temuan_10'        => ['name' => '10 Temuan', 'icon' => 'fa-fire', 'desc' => 'Berhasil input 10 temuan', 'points' => 50, 'type' => 'count', 'field' => 'temuan_count', 'target' => 10],
        'temuan_100'       => ['name' => '100 Temuan', 'icon' => 'fa-star', 'desc' => 'Berhasil input 100 temuan', 'points' => 200, 'type' => 'count', 'field' => 'temuan_count', 'target' => 100],
        'temuan_500'       => ['name' => '500 Temuan', 'icon' => 'fa-crown', 'desc' => 'Berhasil input 500 temuan', 'points' => 500, 'type' => 'count', 'field' => 'temuan_count', 'target' => 500],
        'temuan_1000'      => ['name' => '1000 Temuan', 'icon' => 'fa-medal', 'desc' => 'Legenda! 1000 temuan tercatat', 'points' => 1000, 'type' => 'count', 'field' => 'temuan_count', 'target' => 1000],
        'emergency_hero'   => ['name' => 'Emergency Hero', 'icon' => 'fa-bolt', 'desc' => 'Selesaikan 10 temuan Emergency', 'points' => 300, 'type' => 'count', 'field' => 'emergency_selesai', 'target' => 10],
        'fast_worker'      => ['name' => 'Fast Worker', 'icon' => 'fa-gauge-high', 'desc' => '50 pekerjaan selesai tepat SLA', 'points' => 200, 'type' => 'count', 'field' => 'sla_met_count', 'target' => 50],
        'accuracy_master'  => ['name' => 'Accuracy Master', 'icon' => 'fa-bullseye', 'desc' => '100 pekerjaan selesai tepat SLA', 'points' => 500, 'type' => 'count', 'field' => 'sla_met_count', 'target' => 100],
        'streak_7'         => ['name' => 'Seminggu Penuh', 'icon' => 'fa-calendar-check', 'desc' => '7 hari berturut-turut aktif', 'points' => 100, 'type' => 'streak', 'target' => 7],
        'streak_30'        => ['name' => '30 Hari Konsisten', 'icon' => 'fa-fire-flame-curved', 'desc' => '30 hari berturut-turut aktif', 'points' => 500, 'type' => 'streak', 'target' => 30],
        'gold_level'       => ['name' => 'Gold Member', 'icon' => 'fa-trophy', 'desc' => 'Mencapai level Gold', 'points' => 100, 'type' => 'level', 'target' => 'gold'],
        'diamond_level'    => ['name' => 'Diamond Elite', 'icon' => 'fa-gem', 'desc' => 'Mencapai level Diamond', 'points' => 1000, 'type' => 'level', 'target' => 'diamond'],
    ];

    public function __construct()
    {
        $this->db = db_connect();
    }

    // -------------------------------------------------------------------------
    // Core: Add Points & Timeline Log
    // -------------------------------------------------------------------------

    /**
     * Add points for a user action. Creates gamification row if not exist.
     */
    public function addPoints(int $userId, string $action, string $description = '', int $refId = 0, string $refType = ''): void
    {
        try {
            $points = self::POINTS[$action] ?? 0;

            // Ensure gamification row exists
            $row = $this->db->table('user_gamification')->where('user_id', $userId)->get()->getRowArray();
            if (!$row) {
                $this->db->table('user_gamification')->insert([
                    'user_id'     => $userId,
                    'total_points'=> 0,
                    'level'       => 'bronze',
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
                $row = ['user_id' => $userId, 'total_points' => 0, 'level' => 'bronze',
                        'temuan_count' => 0, 'selesai_count' => 0, 'emergency_selesai' => 0,
                        'sla_met_count' => 0, 'sla_overdue_count' => 0, 'streak_days' => 0];
            }

            // Increment action-specific counters
            $updates = [
                'total_points' => ($row['total_points'] ?? 0) + $points,
                'updated_at'   => date('Y-m-d H:i:s'),
                'last_activity_date' => date('Y-m-d'),
            ];

            switch ($action) {
                case 'INPUT_TEMUAN':
                    $updates['temuan_count'] = ($row['temuan_count'] ?? 0) + 1;
                    break;
                case 'SELESAI_TEMUAN':
                case 'EMERGENCY_SELESAI':
                    $updates['selesai_count'] = ($row['selesai_count'] ?? 0) + 1;
                    if ($action === 'EMERGENCY_SELESAI') {
                        $updates['emergency_selesai'] = ($row['emergency_selesai'] ?? 0) + 1;
                    }
                    break;
                case 'SLA_MET':
                    $updates['sla_met_count'] = ($row['sla_met_count'] ?? 0) + 1;
                    break;
            }

            // Calculate streak
            $lastDate = $row['last_activity_date'] ?? null;
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            if ($lastDate === $yesterday) {
                $updates['streak_days'] = ($row['streak_days'] ?? 0) + 1;
            } elseif ($lastDate !== $today) {
                $updates['streak_days'] = 1;
            }

            // Calculate new level
            $newTotal = $updates['total_points'];
            $newLevel = $this->calculateLevel($newTotal);
            $updates['level'] = $newLevel;

            $this->db->table('user_gamification')->where('user_id', $userId)->update($updates);

            // Log to timeline
            if ($points > 0 || !empty($description)) {
                $this->logTimeline($userId, $action, $description ?: $action, $refId, $refType, $points);
            }

            // Check achievements with merged row data
            $mergedRow = array_merge($row, $updates);
            $this->checkAchievements($userId, $mergedRow);

        } catch (\Throwable $e) {
            log_message('error', '[Gamification] addPoints error: ' . $e->getMessage());
        }
    }

    /**
     * Log to user_activity_timeline
     */
    public function logTimeline(int $userId, string $actionType, string $description, int $refId = 0, string $refType = '', int $points = 0): void
    {
        try {
            $this->db->table('user_activity_timeline')->insert([
                'user_id'     => $userId,
                'action_type' => $actionType,
                'description' => mb_substr($description, 0, 255),
                'ref_id'      => $refId ?: null,
                'ref_type'    => $refType ?: null,
                'points'      => $points,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[Gamification] logTimeline error: ' . $e->getMessage());
        }
    }

    /**
     * Calculate level from total points
     */
    public function calculateLevel(int $totalPoints): string
    {
        foreach (self::LEVELS as $level => $threshold) {
            if ($totalPoints >= $threshold) return $level;
        }
        return 'bronze';
    }

    /**
     * Check & award achievements for a user
     */
    public function checkAchievements(int $userId, array $gamRow): void
    {
        try {
            // Get already earned achievements
            $earned = $this->db->table('user_achievements')
                ->where('user_id', $userId)
                ->select('achievement_key')
                ->get()->getResultArray();
            $earnedKeys = array_column($earned, 'achievement_key');

            foreach (self::ACHIEVEMENTS as $key => $ach) {
                if (in_array($key, $earnedKeys)) continue;

                $earned_flag = false;

                switch ($ach['type']) {
                    case 'count':
                        $val = (int)($gamRow[$ach['field']] ?? 0);
                        $earned_flag = $val >= $ach['target'];
                        break;
                    case 'streak':
                        $earned_flag = ($gamRow['streak_days'] ?? 0) >= $ach['target'];
                        break;
                    case 'level':
                        $lvlOrder = ['bronze' => 0, 'silver' => 1, 'gold' => 2, 'platinum' => 3, 'diamond' => 4];
                        $curLvl = $lvlOrder[$gamRow['level'] ?? 'bronze'] ?? 0;
                        $tgtLvl = $lvlOrder[$ach['target']] ?? 0;
                        $earned_flag = $curLvl >= $tgtLvl;
                        break;
                }

                if ($earned_flag) {
                    $this->db->table('user_achievements')->insert([
                        'user_id'          => $userId,
                        'achievement_key'  => $key,
                        'achievement_name' => $ach['name'],
                        'achievement_icon' => $ach['icon'],
                        'achievement_desc' => $ach['desc'],
                        'points_awarded'   => $ach['points'],
                        'achieved_at'      => date('Y-m-d H:i:s'),
                        'created_at'       => date('Y-m-d H:i:s'),
                    ]);
                    // Award bonus points for achievement
                    if ($ach['points'] > 0) {
                        $this->db->table('user_gamification')
                            ->where('user_id', $userId)
                            ->increment('total_points', $ach['points']);
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', '[Gamification] checkAchievements error: ' . $e->getMessage());
        }
    }

    /**
     * Get user gamification data (with fallback if row doesn't exist)
     */
    public function getUserGamification(int $userId): array
    {
        $row = $this->db->table('user_gamification')->where('user_id', $userId)->get()->getRowArray();
        if (!$row) {
            return [
                'user_id' => $userId, 'total_points' => 0, 'level' => 'bronze',
                'streak_days' => 0, 'temuan_count' => 0, 'selesai_count' => 0,
                'emergency_selesai' => 0, 'sla_met_count' => 0, 'sla_overdue_count' => 0,
            ];
        }
        return $row;
    }

    /**
     * Get user's achievements list
     */
    public function getUserAchievements(int $userId): array
    {
        return $this->db->table('user_achievements')
            ->where('user_id', $userId)
            ->orderBy('achieved_at', 'DESC')
            ->get()->getResultArray();
    }

    /**
     * Get user's today timeline
     */
    public function getUserTimeline(int $userId, string $date = ''): array
    {
        if (!$date) $date = date('Y-m-d');
        return $this->db->table('user_activity_timeline')
            ->where('user_id', $userId)
            ->where("DATE(created_at)", $date)
            ->orderBy('created_at', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * Get leaderboard — top users by points
     */
    public function getLeaderboard(int $limit = 10, ?int $ulpId = null): array
    {
        $builder = $this->db->table('user_gamification ug')
            ->select('ug.*, u.nama_pegawai, u.role, u.ulp, ug.total_points')
            ->join('users u', 'u.id = ug.user_id', 'left')
            ->orderBy('ug.total_points', 'DESC')
            ->limit($limit);

        if ($ulpId) $builder->where('u.ulp_id', $ulpId);

        return $builder->get()->getResultArray();
    }

    /**
     * Get ULP Ranking by temuan selesai count
     */
    public function getUlpRanking(): array
    {
        return $this->db->query(
            "SELECT u.nama_ulp AS ulp_name, u.id AS ulp_id,
                    COUNT(t.id) AS total_temuan,
                    SUM(CASE WHEN t.status = 'SELESAI' THEN 1 ELSE 0 END) AS selesai,
                    ROUND(SUM(CASE WHEN t.status = 'SELESAI' THEN 1 ELSE 0 END) / NULLIF(COUNT(t.id),0) * 100, 1) AS pct
             FROM ulps u
             LEFT JOIN temuan t ON t.ulp_id = u.id AND t.deleted_at IS NULL
             WHERE u.status = 'AKTIF'
             GROUP BY u.id, u.nama_ulp
             ORDER BY selesai DESC, pct DESC
             LIMIT 10"
        )->getResultArray();
    }

    /**
     * Get Penyulang Ranking by temuan count
     */
    public function getPenyulangRanking(): array
    {
        return $this->db->query(
            "SELECT p.nama_penyulang AS penyulang_name, COUNT(t.id) AS total_temuan,
                    SUM(CASE WHEN t.status = 'SELESAI' THEN 1 ELSE 0 END) AS selesai,
                    SUM(CASE WHEN t.prioritas = 'EMERGENCY' THEN 1 ELSE 0 END) AS emergency
             FROM penyulang p
             LEFT JOIN temuan t ON t.penyulang_id = p.id AND t.deleted_at IS NULL
             WHERE p.status = 'AKTIF'
             GROUP BY p.id, p.nama_penyulang
             ORDER BY total_temuan DESC
             LIMIT 10"
        )->getResultArray();
    }

    /**
     * Get Today's personal KPI for a user
     */
    public function getTodayStats(int $userId, string $userRole, ?int $ulpId = null): array
    {
        $today = date('Y-m-d');
        $db = $this->db;

        // Count temuan hari ini by this user
        $temuanHariIni = (int)$db->query(
            "SELECT COUNT(*) as c FROM temuan WHERE deleted_at IS NULL AND DATE(tanggal_temuan) = ? AND (created_by = ? OR user_id = ?)",
            [$today, $userId, $userId]
        )->getRowArray()['c'] ?? 0;

        // Count selesai hari ini
        $selesaiHariIni = (int)$db->query(
            "SELECT COUNT(*) as c FROM temuan WHERE deleted_at IS NULL AND status = 'SELESAI' AND DATE(updated_at) = ? AND (created_by = ? OR user_id = ?)",
            [$today, $userId, $userId]
        )->getRowArray()['c'] ?? 0;

        // Count pekerjaan belum selesai (scoped to role)
        $whereExtra = '';
        if (!in_array(strtolower($userRole), ['administrator', 'admin_pusat'])) {
            $whereExtra = " AND (created_by = $userId OR user_id = $userId)";
        }
        $belum = (int)$db->query(
            "SELECT COUNT(*) as c FROM temuan WHERE deleted_at IS NULL AND status != 'SELESAI'$whereExtra"
        )->getRowArray()['c'] ?? 0;

        // SLA terdekat
        $slaDekat = $db->query(
            "SELECT id, nomor_temuan, prioritas, tanggal_temuan,
                    DATEDIFF(DATE_ADD(tanggal_temuan, INTERVAL CASE prioritas
                        WHEN 'EMERGENCY' THEN 3
                        WHEN 'HIGH' THEN 7
                        WHEN 'MEDIUM' THEN 31
                        ELSE 90 END DAY), CURDATE()) AS sisa_hari
             FROM temuan
             WHERE deleted_at IS NULL AND status != 'SELESAI'$whereExtra
             ORDER BY sisa_hari ASC
             LIMIT 5"
        )->getResultArray();

        return [
            'temuan_hari_ini' => $temuanHariIni,
            'selesai_hari_ini'=> $selesaiHariIni,
            'belum_selesai'   => $belum,
            'target_harian'   => 5,
            'sla_terdekat'    => $slaDekat,
            'pct_hari_ini'    => $temuanHariIni > 0 ? min(100, round($temuanHariIni / 5 * 100)) : 0,
        ];
    }

    /**
     * Get level metadata (label, color, icon, next_level_threshold)
     */
    public static function getLevelMeta(string $level): array
    {
        $meta = [
            'bronze'   => ['label' => 'Bronze', 'color' => '#cd7f32', 'bg' => '#fef3c7', 'icon' => 'fa-medal', 'next' => 250],
            'silver'   => ['label' => 'Silver', 'color' => '#9ca3af', 'bg' => '#f3f4f6', 'icon' => 'fa-medal', 'next' => 800],
            'gold'     => ['label' => 'Gold',   'color' => '#d97706', 'bg' => '#fef3c7', 'icon' => 'fa-trophy', 'next' => 2000],
            'platinum' => ['label' => 'Platinum','color' => '#7c3aed','bg' => '#ede9fe', 'icon' => 'fa-gem',   'next' => 5000],
            'diamond'  => ['label' => 'Diamond', 'color' => '#0ea5e9','bg' => '#e0f2fe', 'icon' => 'fa-gem',   'next' => 9999],
        ];
        return $meta[$level] ?? $meta['bronze'];
    }

    /**
     * Return all achievement definitions (for display)
     */
    public static function getAllAchievementDefs(): array
    {
        return self::ACHIEVEMENTS;
    }
}
