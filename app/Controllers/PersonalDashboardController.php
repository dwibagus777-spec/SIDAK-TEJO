<?php

namespace App\Controllers;

use App\Services\GamificationService;
use App\Repositories\TemuanRepository;

/**
 * PersonalDashboardController — Phase 32
 * Steps 1-9: Personal Dashboard, Today Task, Timeline, Achievement, Level, Points, Ranking
 */
class PersonalDashboardController extends BaseController
{
    private GamificationService $gamService;
    private TemuanRepository $temuanRepo;

    public function __construct()
    {
        $this->gamService = new GamificationService();
        $this->temuanRepo = new TemuanRepository();
    }

    // -------------------------------------------------------------------------
    // GET /my-dashboard — Personal Dashboard (Steps 1-7)
    // -------------------------------------------------------------------------
    public function index(): string
    {
        $session  = session();
        $userId   = (int)$session->get('user_id');
        $userName = (string)$session->get('user_name');
        $role     = (string)$session->get('user_role');
        $ulpId    = $session->get('user_ulp_id');
        $nip      = $session->get('user_nip') ?? '-';
        $ulpName  = $session->get('ulp_name') ?? '-';
        $loginAt  = $session->get('login_time') ?? date('H:i');

        // Gamification data
        $gamData      = $this->gamService->getUserGamification($userId);
        $achievements = $this->gamService->getUserAchievements($userId);
        $levelMeta    = GamificationService::getLevelMeta($gamData['level'] ?? 'bronze');
        $todayStats   = $this->gamService->getTodayStats($userId, $role, $ulpId ? (int)$ulpId : null);
        $timeline     = $this->gamService->getUserTimeline($userId, date('Y-m-d'));
        $leaderboard  = $this->gamService->getLeaderboard(10, $ulpId ? (int)$ulpId : null);
        $allAchDefs   = GamificationService::getAllAchievementDefs();

        // Today's tasks (Emergency & High priority)
        $todayTasks = $this->getTodayTasks($userId, $role, $ulpId ? (int)$ulpId : null);

        // Weekly & Monthly achievement stats
        $weekStats  = $this->getWeeklyStats($userId, $role);
        $monthStats = $this->getMonthlyStats($userId, $role);

        // Next level threshold
        $nextLevel = $levelMeta['next'];
        $curPoints = (int)($gamData['total_points'] ?? 0);
        $levelPct  = $nextLevel > 0 ? min(100, round($curPoints / $nextLevel * 100)) : 100;

        return view('personal_dashboard/index', [
            'userName'      => $userName,
            'userRole'      => $role,
            'ulpName'       => $ulpName,
            'nip'           => $nip,
            'loginAt'       => $loginAt,
            'gamData'       => $gamData,
            'levelMeta'     => $levelMeta,
            'levelPct'      => $levelPct,
            'nextLevel'     => $nextLevel,
            'curPoints'     => $curPoints,
            'achievements'  => $achievements,
            'allAchDefs'    => $allAchDefs,
            'todayStats'    => $todayStats,
            'todayTasks'    => $todayTasks,
            'timeline'      => $timeline,
            'leaderboard'   => $leaderboard,
            'weekStats'     => $weekStats,
            'monthStats'    => $monthStats,
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /my-dashboard/api-stats — AJAX live stats
    // -------------------------------------------------------------------------
    public function apiStats(): \CodeIgniter\HTTP\ResponseInterface
    {
        $session  = session();
        $userId   = (int)$session->get('user_id');
        $role     = (string)$session->get('user_role');
        $ulpId    = $session->get('user_ulp_id');

        $todayStats = $this->gamService->getTodayStats($userId, $role, $ulpId ? (int)$ulpId : null);
        $gamData    = $this->gamService->getUserGamification($userId);

        return $this->response->setJSON([
            'success'    => true,
            'today'      => $todayStats,
            'points'     => $gamData['total_points'] ?? 0,
            'level'      => $gamData['level'] ?? 'bronze',
            'streak'     => $gamData['streak_days'] ?? 0,
            'timestamp'  => date('H:i:s'),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /my-dashboard/timeline — Timeline JSON for current user
    // -------------------------------------------------------------------------
    public function timeline(): \CodeIgniter\HTTP\ResponseInterface
    {
        $userId = (int)session()->get('user_id');
        $date   = $this->request->getGet('date') ?: date('Y-m-d');
        $data   = $this->gamService->getUserTimeline($userId, $date);

        return $this->response->setJSON(['success' => true, 'data' => $data, 'date' => $date]);
    }

    // -------------------------------------------------------------------------
    // GET /ranking — Auto Ranking (Step 9)
    // -------------------------------------------------------------------------
    public function ranking(): string
    {
        $ulpRanking       = $this->gamService->getUlpRanking();
        $penyulangRanking = $this->gamService->getPenyulangRanking();
        $leaderboard      = $this->gamService->getLeaderboard(20);

        return view('personal_dashboard/ranking', [
            'ulpRanking'       => $ulpRanking,
            'penyulangRanking' => $penyulangRanking,
            'leaderboard'      => $leaderboard,
        ]);
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    private function getTodayTasks(int $userId, string $role, ?int $ulpId): array
    {
        $db    = db_connect();
        $today = date('Y-m-d');

        $whereUser = '';
        if (!in_array(strtolower($role), ['administrator', 'admin_pusat', 'supervisor_up3'])) {
            $whereUser = " AND t.created_by = $userId";
        } elseif ($ulpId) {
            $whereUser = " AND t.ulp_id = $ulpId";
        }

        return $db->query(
            "SELECT t.id, t.nomor_temuan, t.jenis_temuan, t.prioritas, t.status,
                    t.penyulang_id, t.section_id, t.tanggal_temuan, t.detail_temuan,
                    DATEDIFF(DATE_ADD(t.tanggal_temuan, INTERVAL CASE t.prioritas
                        WHEN 'EMERGENCY' THEN 3
                        WHEN 'HIGH' THEN 7
                        WHEN 'MEDIUM' THEN 31
                        ELSE 90 END DAY), CURDATE()) AS sisa_hari
             FROM temuan t
             WHERE t.deleted_at IS NULL
               AND t.status != 'SELESAI'
               $whereUser
             ORDER BY
               FIELD(t.prioritas, 'EMERGENCY', 'HIGH', 'MEDIUM', 'LOW'),
               sisa_hari ASC
             LIMIT 20"
        )->getResultArray();
    }

    private function getWeeklyStats(int $userId, string $role): array
    {
        $db = db_connect();
        $whereUser = in_array(strtolower($role), ['administrator', 'admin_pusat']) ? '' :
            " AND t.created_by = $userId";

        $rows = $db->query(
            "SELECT DATE(tanggal_temuan) as tgl, COUNT(*) as count
             FROM temuan t
             WHERE t.deleted_at IS NULL
               AND t.tanggal_temuan >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
               $whereUser
             GROUP BY DATE(t.tanggal_temuan)
             ORDER BY tgl ASC"
        )->getResultArray();

        // Fill all 7 days
        $result = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $result[$d] = 0;
        }
        foreach ($rows as $r) {
            $result[$r['tgl']] = (int)$r['count'];
        }

        return [
            'labels' => array_map(fn($d) => date('D d/m', strtotime($d)), array_keys($result)),
            'values' => array_values($result),
            'total'  => array_sum($result),
        ];
    }

    private function getMonthlyStats(int $userId, string $role): array
    {
        $db = db_connect();
        $whereUser = in_array(strtolower($role), ['administrator', 'admin_pusat']) ? '' :
            " AND t.created_by = $userId";

        $thisMonth  = (int)$db->query("SELECT COUNT(*) as c FROM temuan t WHERE t.deleted_at IS NULL AND MONTH(t.tanggal_temuan)=MONTH(CURDATE()) AND YEAR(t.tanggal_temuan)=YEAR(CURDATE())$whereUser")->getRowArray()['c'];
        $lastMonth  = (int)$db->query("SELECT COUNT(*) as c FROM temuan t WHERE t.deleted_at IS NULL AND MONTH(t.tanggal_temuan)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND YEAR(t.tanggal_temuan)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))$whereUser")->getRowArray()['c'];
        $thisYear   = (int)$db->query("SELECT COUNT(*) as c FROM temuan t WHERE t.deleted_at IS NULL AND YEAR(t.tanggal_temuan)=YEAR(CURDATE())$whereUser")->getRowArray()['c'];

        return [
            'this_month'  => $thisMonth,
            'last_month'  => $lastMonth,
            'this_year'   => $thisYear,
            'target_month'=> 100,
            'target_year' => 1000,
            'pct_month'   => $thisMonth > 0 ? min(100, round($thisMonth / 100 * 100)) : 0,
            'pct_year'    => $thisYear > 0  ? min(100, round($thisYear / 1000 * 100)) : 0,
        ];
    }
}
