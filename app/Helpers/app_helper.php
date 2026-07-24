<?php

use App\Models\AuditLogModel;

if (!function_exists('log_activity')) {
    /**
     * Catat aktivitas pengguna ke tabel audit_logs
     *
     * @param string $activity
     * @param string|null $detail
     * @return bool
     */
    function log_activity(string $activity, ?string $detail = null): bool
    {
        $session = session();
        $userId = $session->get('user_id');
        $username = $session->get('user_name') ?: 'Guest';
        $role = $session->get('user_role') ?: 'guest';

        $request = \Config\Services::request();
        $ip = $request->getIPAddress();
        $userAgent = $request->getUserAgent()->getAgentString();

        $auditLogModel = new AuditLogModel();
        
        try {
            $auditLogModel->insert([
                'user_id' => $userId,
                'username' => $username,
                'role' => $role,
                'aktivitas' => $activity,
                'detail' => $detail,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            return true;
        } catch (\Exception $e) {
            log_message('error', 'Gagal menulis audit log: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('check_role')) {
    /**
     * Periksa apakah pengguna memiliki salah satu role yang diizinkan
     *
     * @param array|string $allowedRoles
     * @return bool
     */
    function check_role($allowedRoles): bool
    {
        $session = session();
        if (!$session->has('user_id')) {
            return false;
        }

        $userRole = strtolower(trim((string)$session->get('user_role')));
        if (empty($userRole)) {
            return false;
        }

        if (is_array($allowedRoles)) {
            $allowed = array_map(function($r) { return strtolower(trim((string)$r)); }, $allowedRoles);
            return in_array($userRole, $allowed, true);
        }

        return $userRole === strtolower(trim((string)$allowedRoles));
    }
}

if (!function_exists('get_role_label')) {
    /**
     * Dapatkan label role dalam format yang ramah dibaca
     *
     * @param string $role
     * @return string
     */
    function get_role_label(string $role): string
    {
        $labels = [
            'administrator' => 'Administrator',
            'admin' => 'Administrator',
            'admin_pusat' => 'Admin Pusat',
            'admin_ulp' => 'Admin ULP',
            'inspeksi' => 'Inspeksi',
            'pdkb' => 'PDKB',
            'har_gardu' => 'HAR Gardu',
            'har_konstruksi' => 'HAR Konstruksi',
            'har_row' => 'HAR ROW',
            'har_crane' => 'HAR Crane',
            'yantek' => 'Yantek',
            'supervisor_ulp' => 'Supervisor ULP',
            'supervisor_up3' => 'Supervisor UP3',
        ];

        return $labels[strtolower($role)] ?? ucfirst($role);
    }
}

if (!function_exists('apply_role_scoping')) {
    /**
     * Terapkan penyaringan otomatis berdasarkan Role dan ULP pengguna pada Query Builder
     */
    function apply_role_scoping($builder, $userRole = null, $userUlpId = null)
    {
        $session = session();
        if (!$userRole) {
            $userRole = strtolower((string)$session->get('user_role'));
        } else {
            $userRole = strtolower($userRole);
        }
        if (!$userUlpId) {
            $userUlpId = $session->get('ulp_id');
        }

        // Full access roles across all ULPs & Pelaksana
        if (in_array($userRole, ['administrator', 'admin', 'admin_pusat', 'supervisor_up3'])) {
            return $builder;
        }

        // Filter ULP (jika user memiliki ulp_id)
        if (!empty($userUlpId) && in_array($userRole, ['admin_ulp', 'inspeksi', 'yantek', 'har_gardu', 'har_konstruksi', 'har_row', 'har_crane', 'supervisor_ulp'])) {
            $builder->where('temuan.ulp_id', $userUlpId);
        }

        // Filter Pelaksana khusus untuk role teknis
        if ($userRole === 'pdkb') {
            $builder->where('temuan.pelaksana', 'PDKB');
        } elseif ($userRole === 'har_gardu') {
            $builder->where('temuan.pelaksana', 'HAR GARDU');
        } elseif ($userRole === 'har_konstruksi') {
            $builder->where('temuan.pelaksana', 'HAR KONSTRUKSI');
        } elseif ($userRole === 'har_row') {
            $builder->where('temuan.pelaksana', 'HAR ROW');
        } elseif ($userRole === 'har_crane') {
            $builder->where('temuan.pelaksana', 'HAR CRANE');
        }

        return $builder;
    }
}

if (!function_exists('get_sla_status')) {
    /**
     * Hitung status SLA temuan berdasarkan prioritas
     * Emergency: 1x24 jam
     * High: 3 hari
     * Medium: 7 hari
     *
     * @param string $priority
     * @param string $tanggalTemuan format Y-m-d
     * @param string $status BELUM / SELESAI
     * @param string|null $tanggalSelesai format Y-m-d
     * @return array [is_overdue, badge_html, text, deadline]
     */
    function get_sla_status(string $priority, string $tanggalTemuan, string $status, ?string $tanggalSelesai = null): array
    {
        $priority = strtoupper($priority);
        $status = strtoupper($status);
        
        $start = new \DateTime($tanggalTemuan . ' 00:00:00');
        $deadline = clone $start;

        switch ($priority) {
            case 'EMERGENCY':
                // 1 x 24 jam (tambah 1 hari)
                $deadline->modify('+1 day');
                break;
            case 'HIGH':
                // 3 hari
                $deadline->modify('+3 days');
                break;
            case 'MEDIUM':
            default:
                // 7 hari
                $deadline->modify('+7 days');
                break;
        }

        $now = new \DateTime();
        
        if ($status === 'BUTUH PADAM') {
            $badge = '<span class="badge bg-purple animate__animated animate__pulse animate__infinite" style="background-color: #6f42c1 !important;"><i class="fas fa-power-off"></i> BUTUH PADAM</span>';
            return [
                'is_overdue' => false,
                'badge_html' => $badge,
                'text' => 'Membutuhkan pemadaman jaringan (Dipindahkan ke HAR Konstruksi)',
                'deadline' => $deadline->format('Y-m-d H:i:s'),
            ];
        }
        
        if ($status === 'SELESAI') {
            $end = new \DateTime(($tanggalSelesai ?: $tanggalTemuan) . ' 23:59:59');
            $isOverdue = $end > $deadline;
            
            if ($isOverdue) {
                $badge = '<span class="badge bg-warning"><i class="fas fa-exclamation-triangle"></i> SELESAI (OVERDUE)</span>';
                $text = 'Selesai melewati batas SLA';
            } else {
                $badge = '<span class="badge bg-success"><i class="fas fa-check-circle"></i> SELESAI (SLA OK)</span>';
                $text = 'Selesai dalam batas SLA';
            }
        } else {
            // BELUM SELESAI
            $isOverdue = $now > $deadline;
            
            if ($isOverdue) {
                $diff = $now->diff($deadline);
                $days = $diff->days;
                $badge = '<span class="badge bg-danger animate__animated animate__flash animate__infinite"><i class="fas fa-hourglass-end"></i> OVERDUE (' . $days . ' hari)</span>';
                $text = 'Melewati batas waktu ' . $days . ' hari';
            } else {
                $diff = $now->diff($deadline);
                $days = $diff->days;
                $hours = $diff->h;
                $timeLeft = $days > 0 ? "$days hari $hours jam" : "$hours jam";
                $badge = '<span class="badge bg-info"><i class="fas fa-hourglass-half"></i> AKTIF (' . $timeLeft . ' sisa)</span>';
                $text = 'Dalam SLA (sisa ' . $timeLeft . ')';
            }
        }

        return [
            'is_overdue' => $isOverdue,
            'badge_html' => $badge,
            'text' => $text,
            'deadline' => $deadline->format('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('get_user_role_scoping')) {
    /**
     * Dapatkan scope ULP ID & Kategori Temuan (jenis_temuan) berdasarkan role user
     * 
     * Rules:
     * - PDKB, Administrator, HAR Crane: Lintas ULP ($ulpIdFilter = null).
     * - HAR ROW: Terbatas ULP sendiri & khusus jenis_temuan = 'ROW'.
     * - HAR Gardu / HAR Konstruksi: Terbatas ULP sendiri, bisa akses semua jenis temuan (ROW, KONSTRUKSI, HOTSPOT).
     * - Admin ULP, Inspeksi, Yantek: Terbatas ULP sendiri, semua jenis temuan.
     * 
     * @return array ['ulp_id' => ?int, 'jenis_temuan' => ?string, 'role' => string]
     */
    function get_user_role_scoping(): array
    {
        $session = session();
        $role = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id');

        $ulpIdFilter = null;
        $jenisTemuanFilter = null;

        // Cross-ULP roles: administrator, pdkb, har_crane
        if (!in_array($role, ['administrator', 'pdkb', 'har_crane'])) {
            if ($userUlpId !== null) {
                $ulpIdFilter = (int)$userUlpId;
            }
        }

        // HAR ROW is restricted to ROW category only
        if ($role === 'har_row') {
            $jenisTemuanFilter = 'ROW';
        }

        return [
            'ulp_id' => $ulpIdFilter,
            'jenis_temuan' => $jenisTemuanFilter,
            'role' => $role
        ];
    }
}

if (!function_exists('get_daily_announcement')) {
    /**
     * Dapatkan kata-kata motivasi harian untuk running ticker
     */
    function get_daily_announcement(): string
    {
        $paths = [
            defined('WRITEPATH') ? WRITEPATH . 'announcement.json' : null,
            defined('ROOTPATH') ? ROOTPATH . 'writable/announcement.json' : null,
            defined('FCPATH') ? FCPATH . '../writable/announcement.json' : null,
            __DIR__ . '/../../writable/announcement.json'
        ];

        foreach ($paths as $filePath) {
            if ($filePath && file_exists($filePath)) {
                $content = @file_get_contents($filePath);
                if ($content) {
                    $data = @json_decode($content, true);
                    if (!empty($data['message'])) {
                        return $data['message'];
                    }
                }
            }
        }
        return "⚡ Tetap Utamakan K3 & Keselamatan Kerja! Semangat Petugas Inspeksi & HAR PLN UP3 Sidoarjo! Bekerja Keras, Pulang Selamat! ⚡";
    }
}
