<?php

date_default_timezone_set('Asia/Jakarta');

use App\Models\AuditLogModel;

if (!function_exists('log_activity')) {
    /**
     * Catat aktivitas pengguna ke Log File CodeIgniter 4 dan Tabel Audit Log Database
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
        $userAgent = (string)$request->getUserAgent();

        // 1. Catat ke File Log CodeIgniter 4 (writable/logs/)
        log_message('info', "[AUDIT] User: {$username} ({$role}) | IP: {$ip} | Action: {$activity} | Detail: " . ($detail ?? 'N/A'));

        // 2. Catat ke Database AuditLogModel
        try {
            $auditLogModel = new AuditLogModel();
            $auditLogModel->insert([
                'user_id'    => $userId,
                'username'  => $username,
                'role'      => $role,
                'aktivitas' => $activity,
                'detail'    => $detail,
                'ip_address'=> $ip,
                'user_agent'=> $userAgent,
                'created_at'=> date('Y-m-d H:i:s')
            ]);
            return true;
        } catch (\Throwable $e) {
            log_message('error', '[AUDIT_DB_FAIL] Gagal menulis audit log ke DB: ' . $e->getMessage());
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
     * Dapatkan kata-kata motivasi harian untuk running ticker (database-driven dengan fallback)
     */
    function get_daily_announcement(): string
    {
        try {
            $settingService = new \App\Services\SystemSettingService();
            $val = $settingService->get('daily_motivation');
            if (!empty($val)) {
                return $val;
            }
        } catch (\Throwable $e) {
            log_message('error', '[get_daily_announcement] Gagal mengambil dari DB: ' . $e->getMessage());
        }

        return "⚡ Tetap Utamakan K3 & Keselamatan Kerja! Semangat Petugas Inspeksi & HAR PLN UP3 Sidoarjo! Bekerja Keras, Pulang Selamat! ⚡";
    }
}

if (!function_exists('get_photo_url')) {
    /**
     * Helper tunggal untuk mendapatkan URL foto dengan toleransi berkas hilang
     */
    function get_photo_url(?string $photoName, ?string $fotoPath = 'foto/'): string
    {
        $placeholder = base_url('assets/img/no-image.png');

        if (empty($photoName)) {
            return $placeholder;
        }

        $photoName = trim($photoName);
        if ($photoName === '') {
            return $placeholder;
        }

        // Bersihkan prefix "public/" atau "/public/" jika terbawa
        $photoName = preg_replace('/^\/?public\//', '', $photoName);

        // Jika photoName berupa URL eksternal lengkap
        if (str_starts_with($photoName, 'http://') || str_starts_with($photoName, 'https://')) {
            return $photoName;
        }

        // Tentukan relative path lokal
        if (str_starts_with($photoName, 'foto/') || str_starts_with($photoName, 'uploads/')) {
            $relativePath = $photoName;
        } else {
            $dir = (!empty($fotoPath) && trim($fotoPath, '/') !== '') ? rtrim($fotoPath, '/') . '/' : 'foto/';
            $dir = preg_replace('/^\/?public\//', '', $dir);
            $relativePath = $dir . $photoName;
        }

        // Verifikasi keberadaan berkas fisik di server
        if (defined('FCPATH') && file_exists(FCPATH . $relativePath)) {
            $mtime = filemtime(FCPATH . $relativePath);
            return base_url($relativePath) . '?v=' . ($mtime ?: time());
        }

        // Fallback jika berkas fisik tidak ditemukan di disk server
        return $placeholder;
    }
}

if (!function_exists('indo_date')) {
    /**
     * Format tanggal ke Bahasa Indonesia (Contoh: 29 Juli 2026 atau Rabu, 29 Juli 2026)
     */
    function indo_date(?string $dateStr, bool $includeDay = false): string
    {
        if (empty($dateStr) || $dateStr === '0000-00-00' || $dateStr === '0000-00-00 00:00:00') {
            return '-';
        }

        $time = strtotime($dateStr);
        if (!$time) return '-';

        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $dayName = $days[date('w', $time)];
        $dayNum = date('j', $time);
        $monthName = $months[(int)date('n', $time)];
        $year = date('Y', $time);

        if ($includeDay) {
            return "{$dayName}, {$dayNum} {$monthName} {$year}";
        }
        return "{$dayNum} {$monthName} {$year}";
    }
}

if (!function_exists('indo_datetime')) {
    /**
     * Format datetime lengkap Bahasa Indonesia dengan Timezone WIB (Contoh: 29 Juli 2026 08:24:16 WIB)
     */
    function indo_datetime(?string $datetimeStr, bool $includeSeconds = true, bool $includeDay = false): string
    {
        if (empty($datetimeStr) || $datetimeStr === '0000-00-00 00:00:00') {
            return '-';
        }

        $time = strtotime($datetimeStr);
        if (!$time) return '-';

        $dateFormatted = indo_date($datetimeStr, $includeDay);
        $timeFormatted = $includeSeconds ? date('H:i:s', $time) : date('H:i', $time);

        return "{$dateFormatted} {$timeFormatted} WIB";
    }
}

if (!function_exists('greeting')) {
    /**
     * Smart Automatic Greeting berdasarkan Waktu Asia/Jakarta
     */
    function greeting(?int $hour = null): string
    {
        if ($hour === null) {
            $hour = (int)date('H');
        }

        if ($hour >= 0 && $hour < 10) {
            return '🌅 Selamat Pagi';
        } elseif ($hour >= 10 && $hour < 15) {
            return '☀ Selamat Siang';
        } elseif ($hour >= 15 && $hour < 18) {
            return '🌤 Selamat Sore';
        } else {
            return '🌙 Selamat Malam';
        }
    }
}

if (!function_exists('time_ago')) {
    /**
     * Relative Human Time
     */
    function time_ago(?string $datetimeStr): string
    {
        if (empty($datetimeStr)) return '-';
        $time = strtotime($datetimeStr);
        if (!$time) return '-';

        $diff = time() - $time;
        if ($diff < 60) return 'Baru saja';
        if ($diff < 3600) return floor($diff / 60) . ' menit yang lalu';
        if ($diff < 86400) return floor($diff / 3600) . ' jam yang lalu';
        if ($diff < 604800) return floor($diff / 86400) . ' hari yang lalu';

        return indo_date($datetimeStr);
    }
}

if (!function_exists('formatDuration')) {
    /**
     * Format detik ke durasi yang ramah dibaca
     */
    function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) return '0 menit';

        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days > 0) $parts[] = "{$days} hari";
        if ($hours > 0) $parts[] = "{$hours} jam";
        if ($minutes > 0 && $days == 0) $parts[] = "{$minutes} menit";

        return !empty($parts) ? implode(' ', $parts) : '1 menit';
    }
}

if (!function_exists('duration')) {
    /**
     * Hitung durasi antara dua timestamp
     */
    function duration(string $startStr, ?string $endStr = null): string
    {
        $start = strtotime($startStr);
        $end = !empty($endStr) ? strtotime($endStr) : time();

        if (!$start) return '-';
        return formatDuration(max(0, $end - $start));
    }
}

if (!function_exists('shift')) {
    /**
     * Tentukan Shift Kerja berdasarkan Jam
     */
    function shift(?string $timeStr = null): string
    {
        $hour = !empty($timeStr) ? (int)date('H', strtotime($timeStr)) : (int)date('H');

        if ($hour >= 7 && $hour < 15) {
            return 'Shift Pagi (07.00 - 15.00)';
        } elseif ($hour >= 15 && $hour < 23) {
            return 'Shift Siang (15.00 - 23.00)';
        } else {
            return 'Shift Malam (23.00 - 07.00)';
        }
    }
}

if (!function_exists('sla_remaining_info')) {
    /**
     * Hitung sisa SLA / Overdue secara presisi
     */
    function sla_remaining_info(string $priority, string $createdStr, string $status, ?string $finishedStr = null): array
    {
        $prio = strtoupper($priority);
        $st   = strtoupper($status);

        $start = strtotime($createdStr);
        $end   = ($st === 'SELESAI' && !empty($finishedStr)) ? strtotime($finishedStr) : time();

        $maxDays = match($prio) {
            'EMERGENCY' => 1,
            'HIGH'      => 3,
            'MEDIUM'    => 7,
            default     => 14
        };

        $deadline = $start + ($maxDays * 86400);
        $diff     = $deadline - $end;

        if ($st === 'SELESAI') {
            if ($diff >= 0) {
                return [
                    'is_overdue' => false,
                    'badge'      => '<span class="badge bg-success"><i class="fas fa-circle-check me-1"></i> SELESAI (SLA OK)</span>',
                    'text'       => 'Selesai tepat waktu'
                ];
            } else {
                return [
                    'is_overdue' => true,
                    'badge'      => '<span class="badge bg-warning text-dark"><i class="fas fa-triangle-exclamation me-1"></i> SELESAI (OVERDUE ' . formatDuration(abs($diff)) . ')</span>',
                    'text'       => 'Selesai melewati SLA ' . formatDuration(abs($diff))
                ];
            }
        } else {
            if ($diff >= 0) {
                return [
                    'is_overdue' => false,
                    'badge'      => '<span class="badge bg-info text-white"><i class="fas fa-hourglass-half me-1"></i> SISA ' . formatDuration($diff) . '</span>',
                    'text'       => 'Sisa SLA ' . formatDuration($diff)
                ];
            } else {
                return [
                    'is_overdue' => true,
                    'badge'      => '<span class="badge bg-danger animate__animated animate__flash animate__infinite"><i class="fas fa-hourglass-end me-1"></i> OVERDUE ' . formatDuration(abs($diff)) . '</span>',
                    'text'       => 'Terlambat ' . formatDuration(abs($diff))
                ];
            }
        }
    }
}
