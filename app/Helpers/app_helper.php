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
            'beyond_kwh' => 'Beyond KWH',
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
    function get_sla_status(?string $priority, ?string $tanggalTemuan, ?string $status, ?string $tanggalSelesai = null): array
    {
        $priority = strtoupper((string)($priority ?: 'MEDIUM'));
        $status = strtoupper((string)($status ?: 'BELUM'));
        
        $validDate = !empty($tanggalTemuan) && $tanggalTemuan !== '0000-00-00' ? $tanggalTemuan : date('Y-m-d');
        try {
            $start = new \DateTime($validDate . ' 00:00:00');
        } catch (\Throwable $e) {
            $start = new \DateTime();
        }
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
            $validEnd = !empty($tanggalSelesai) && $tanggalSelesai !== '0000-00-00' ? $tanggalSelesai : $validDate;
            try {
                $end = new \DateTime($validEnd . ' 23:59:59');
            } catch (\Throwable $e) {
                $end = new \DateTime();
            }
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

if (!function_exists('check_photo_access')) {
    /**
     * Check if authenticated user has access to a photo by relative path.
     * Searches temuan, eviden (kubikel/trafo/management), work orders, assets for the photo.
     * Returns true if user's ULP scope matches the record's ULP, or user is cross-ULP role AND photo exists in DB.
     * Uses parameter binding to prevent SQL injection. Checks ALL matching records across ULPs.
     *
     * @param string $photoRelativePath Relative path of photo (e.g., "management/name.jpg" or "name.jpg")
     * @param string|null $dbGroup Optional database group to use (defaults to default group)
     * @return bool True if access allowed, false otherwise
     */
    function check_photo_access(string $photoRelativePath, ?string $dbGroup = null): bool
    {
        $session = session();
        if (!$session->get('logged_in')) {
            return false;
        }

        $scope = get_user_role_scoping();
        $userRole = $scope['role'];
        $userUlpId = $scope['ulp_id'];

        // Cross-ULP roles from get_user_role_scoping(): administrator, pdkb, har_crane
        $crossUlpRoles = ['administrator', 'pdkb', 'har_crane'];

        // Normalize the relative path (decode URL, convert separators, remove traversal attempts)
        $cleanRelativePath = str_replace('\\', '/', rawurldecode(trim($photoRelativePath)));
        if ($cleanRelativePath === '' || $cleanRelativePath === '.' || $cleanRelativePath === '..' || str_contains($cleanRelativePath, "\0")) {
            return false;
        }
        $segments = explode('/', $cleanRelativePath);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }
        $cleanRelativePath = implode('/', $segments);

        // Extract basename for tables that only store filename
        $cleanBasename = basename($cleanRelativePath);

        $db = \Config\Database::connect($dbGroup ?? 'default');

        if (str_starts_with($cleanRelativePath, 'kubikel_inspeksi/')) {
            $photos = $db->table('inspeksi_kubikel_foto foto')
                ->select('foto.checklist_id, inspeksi.id AS inspeksi_id, inspeksi.ulp_id, inspeksi.inspector_user_id')
                ->join('inspeksi_kubikel inspeksi', 'inspeksi.id = foto.inspeksi_kubikel_id')
                ->where('foto.path_file', $cleanRelativePath)
                ->get()->getResultArray();

            foreach ($photos as $photo) {
                if (in_array($userRole, ['administrator', 'admin', 'admin_pusat'], true)
                    || ($userRole !== 'beyond_kwh' && (int)$photo['inspector_user_id'] === (int)$session->get('user_id'))) {
                    return true;
                }

                if (in_array($userRole, ['ulp', 'har_gtt'], true)
                    && $userUlpId !== null
                    && (int)$photo['ulp_id'] === (int)$userUlpId) {
                    return true;
                }

                if ($userRole === 'beyond_kwh' && !empty($photo['checklist_id'])) {
                    $assignedFinding = $db->table('inspeksi_kubikel_temuan temuan')
                        ->join('inspeksi_kubikel_rekomendasi rekomendasi', 'rekomendasi.temuan_id = temuan.id')
                        ->where('temuan.inspeksi_kubikel_id', (int)$photo['inspeksi_id'])
                        ->where('temuan.checklist_id', (int)$photo['checklist_id'])
                        ->where('rekomendasi.penerima', 'BEYOND_KWH')
                        ->where('rekomendasi.penerima_user_id', (int)$session->get('user_id'))
                        ->countAllResults() > 0;
                    if ($assignedFinding) {
                        return true;
                    }
                }
            }

            return false;
        }

        // If no ULP filter and not cross-ULP, deny
        if ($userUlpId === null && !in_array($userRole, $crossUlpRoles, true)) {
            return false;
        }

        // Helper: check if photo is associated with a ULP record that matches user scope
        $checkUlpMatch = function (array $record) use ($userRole, $crossUlpRoles, $userUlpId): bool {
            if (!isset($record['ulp_id']) || $record['ulp_id'] === null) {
                return false;
            }
            if (in_array($userRole, $crossUlpRoles, true)) {
                return true;
            }
            return (int)$record['ulp_id'] === (int)$userUlpId;
        };

        // 1. Check temuan table - match FULL relative path against foto_path + foto JSON array
        // Get records where foto JSON contains the basename, then verify foto_path matches the subfolder
        $escapedBasename = $db->escape($cleanBasename);
        $temuanResults = $db->table('temuan')
            ->select('ulp_id, foto_path')
            ->where('deleted_at IS NULL')
            ->where("JSON_CONTAINS(`foto`, JSON_QUOTE({$escapedBasename}))")
            ->get()->getResultArray();
        foreach ($temuanResults as $record) {
            // Construct full path from foto_path + basename
            $recordFotoPath = rtrim($record['foto_path'] ?? 'foto/', '/') . '/';
            $recordFullPath = $recordFotoPath . $cleanBasename;

            // Match the full relative path
            if ($recordFullPath === $cleanRelativePath && $checkUlpMatch($record)) {
                return true;
            }
            // Also allow match if no subfolder in request but basename matches (backward compat)
            if ($cleanRelativePath === $cleanBasename && $checkUlpMatch($record)) {
                return true;
            }
        }

        // 2. Check eviden kubikel - match basename (no subfolder support in this table)
        $fotoResults = $db->table('tb_foto_eviden fe')
            ->join('tb_eviden_kubikel k', 'k.id_kubikel = fe.id_parent')
            ->join('penyulang p', 'p.id = k.id_penyulang')
            ->select('p.ulp_id')
            ->where('fe.nama_file', $cleanBasename)
            ->where('fe.kategori', 'KUBIKEL')
            ->get()->getResultArray();
        foreach ($fotoResults as $record) {
            if ($checkUlpMatch($record)) {
                return true;
            }
        }

        // 3. Check eviden trafo - match basename
        $fotoResults = $db->table('tb_foto_eviden fe')
            ->join('tb_eviden_trafo t', 't.id_trafo = fe.id_parent')
            ->join('penyulang p', 'p.id = t.id_penyulang')
            ->select('p.ulp_id')
            ->where('fe.nama_file', $cleanBasename)
            ->where('fe.kategori', 'TRAFO')
            ->get()->getResultArray();
        foreach ($fotoResults as $record) {
            if ($checkUlpMatch($record)) {
                return true;
            }
        }

        // 4. Check management trafo (nameplates) - these are in management/ subfolder
        $mgmtResults = $db->table('tb_management_trafo m')
            ->join('penyulang p', 'p.id = m.id_penyulang')
            ->select('p.ulp_id')
            ->where('m.foto_nameplate_lama', $cleanBasename)
            ->orWhere('m.foto_nameplate_baru', $cleanBasename)
            ->get()->getResultArray();
        foreach ($mgmtResults as $record) {
            // For management trafo, the expected path is "management/{basename}"
            $expectedPath = 'management/' . $cleanBasename;
            if (($cleanRelativePath === $expectedPath || $cleanRelativePath === $cleanBasename) && $checkUlpMatch($record)) {
                return true;
            }
        }

        // 5. Check work order photos (riwayat_tindak_lanjut) - only if table exists
        if ($db->tableExists('riwayat_tindak_lanjut')) {
            $woResults = $db->table('riwayat_tindak_lanjut rtl')
                ->join('temuan te', 'te.id = rtl.temuan_id')
                ->select('te.ulp_id')
                ->groupStart()
                    ->where('rtl.foto_sebelum', $cleanBasename)
                    ->orWhere('rtl.foto_proses', $cleanBasename)
                    ->orWhere('rtl.foto_sesudah', $cleanBasename)
                ->groupEnd()
                ->get()->getResultArray();
            foreach ($woResults as $record) {
                if ($checkUlpMatch($record)) {
                    return true;
                }
            }
        }

        // 6. Check asset photos (if assets table has foto column)
        // Assets are scoped by penyulang -> section -> ULP
        $assetResults = $db->table('assets a')
            ->join('sections s', 's.id = a.section_id')
            ->join('penyulang p', 'p.id = s.penyulang_id')
            ->select('p.ulp_id')
            ->where('a.foto', $cleanBasename)
            ->get()->getResultArray();
        foreach ($assetResults as $record) {
            if ($checkUlpMatch($record)) {
                return true;
            }
        }

        // Photo not found in any known record - deny access by default
        return false;
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
     * Helper tunggal untuk mendapatkan URL foto dengan toleransi berkas hilang & multi-resolution (thumb/medium/full)
     * Preserves safe subfolders (e.g., management/name.jpg) while rejecting traversal.
     */
    function get_photo_url(?string $photoName, ?string $fotoPath = 'foto/', string $size = 'full'): string
    {
        $placeholder = base_url('assets/img/no-image.png');

        if (empty($photoName)) {
            return $placeholder;
        }

        $photoName = trim($photoName);
        if ($photoName === '') {
            return $placeholder;
        }

        // Jika photoName berupa URL eksternal lengkap
        if (str_starts_with($photoName, 'http://') || str_starts_with($photoName, 'https://')) {
            return $photoName;
        }

        // Preserve supported subfolders, reject traversal (same logic as resolve_photo_path)
        $relativeName = str_replace('\\', '/', rawurldecode(trim($photoName)));
        if ($relativeName === '' || str_contains($relativeName, "\0") || str_starts_with($relativeName, '/') || preg_match('/^[A-Za-z]:\//', $relativeName)) {
            return $placeholder;
        }

        $segments = explode('/', $relativeName);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return $placeholder;
            }
        }
        $relativeName = implode(DIRECTORY_SEPARATOR, $segments);

        $persistentDir = defined('SIDAK_STORAGE_PATH') ? SIDAK_STORAGE_PATH : WRITEPATH . 'uploads/foto/';
        $candidatePaths = [
            rtrim($persistentDir, '/\\') . DIRECTORY_SEPARATOR . $relativeName,
            rtrim(WRITEPATH . 'uploads/foto/', '/\\') . DIRECTORY_SEPARATOR . $relativeName,
            rtrim(FCPATH . 'foto/', '/\\') . DIRECTORY_SEPARATOR . $relativeName,
            rtrim(dirname(FCPATH) . DIRECTORY_SEPARATOR . 'foto/', '/\\') . DIRECTORY_SEPARATOR . $relativeName,
        ];

        foreach (array_unique($candidatePaths) as $path) {
            if (is_file($path) && is_readable($path)) {
                $mtime = filemtime($path) ?: time();
                return base_url('foto/' . rawurlencode($relativeName)) . '?v=' . $mtime;
            }
        }

        return base_url('foto/' . rawurlencode($relativeName));
    }
}

if (!function_exists('resolve_photo_path')) {
    /**
     * Resolve actual filesystem path for a photo using the persistent storage fallback chain.
     * Returns the first existing readable file path, or the primary persistent path for new uploads.
     *
     * @param string $photoName Basename or relative path from DB
     * @param bool $forWrite If true, returns primary persistent path even if file doesn't exist (for new uploads)
     * @return string|null Resolved absolute path, or null if invalid name
     */
    function resolve_photo_path(string $photoName, bool $forWrite = false): ?string
    {
        // Preserve supported subfolders such as management/name.jpg, while rejecting traversal.
        $relativeName = str_replace('\\', '/', rawurldecode(trim($photoName)));
        if ($relativeName === '' || str_contains($relativeName, "\0") || str_starts_with($relativeName, '/') || preg_match('/^[A-Za-z]:\//', $relativeName)) {
            return null;
        }

        $segments = explode('/', $relativeName);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }
        $relativeName = implode(DIRECTORY_SEPARATOR, $segments);

        $persistentDir = defined('SIDAK_STORAGE_PATH')
            ? rtrim(SIDAK_STORAGE_PATH, '/\\') . DIRECTORY_SEPARATOR
            : rtrim(WRITEPATH . 'uploads/foto/', '/\\') . DIRECTORY_SEPARATOR;
        $candidatePaths = [
            $persistentDir . $relativeName,
            rtrim(WRITEPATH . 'uploads/foto/', '/\\') . DIRECTORY_SEPARATOR . $relativeName,
            rtrim(FCPATH . 'foto/', '/\\') . DIRECTORY_SEPARATOR . $relativeName,
            rtrim(dirname(FCPATH) . DIRECTORY_SEPARATOR . 'foto/', '/\\') . DIRECTORY_SEPARATOR . $relativeName,
        ];

        if ($forWrite) {
            return $candidatePaths[0];
        }

        foreach (array_unique($candidatePaths) as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}

if (!function_exists('render_photo')) {
    /**
     * Render tag <img> foto secara aman dengan logging error jika foto gagal dimuat
     */
    function render_photo(?string $photoName, string $alt = 'Foto', string $class = 'img-thumbnail', ?string $fotoPath = 'foto/', string $size = 'full', string $extraAttr = ''): string
    {
        $url = get_photo_url($photoName, $fotoPath, $size);
        $placeholder = base_url('assets/img/no-image.png');
        $errorLogJs = "onerror=\"console.warn('Gagal memuat foto: " . addslashes((string)$photoName) . "'); this.onerror=null; this.src='" . $placeholder . "';\"";
        return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($alt) . '" class="' . htmlspecialchars($class) . '" ' . $errorLogJs . ' ' . $extraAttr . ' />';
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

if (!function_exists('get_sidak_menu_registry')) {
    /**
     * Master Menu Registry for SIDAK TEJO across desktop and mobile layouts.
     * Strictly respects roles and returns only authorized routes.
     *
     * @return array ['categories' => array, 'items' => array]
     */
    function get_sidak_menu_registry(): array
    {
        $rawMenuItems = [
            // DASHBOARD
            [
                'id'        => 'dash-utama',
                'name'      => 'Dashboard Utama',
                'category'  => 'DASHBOARD',
                'desc'      => 'Executive overview & live KPIs',
                'href'      => site_url('dashboard'),
                'icon'      => 'fas fa-tachometer-alt',
                'active'    => (bool)(url_is('dashboard') && !url_is('executive-dashboard') && !url_is('dashboard/executive')),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'exec-analytics',
                'name'      => 'Executive Analytics',
                'category'  => 'DASHBOARD',
                'desc'      => 'Board-level insights',
                'href'      => site_url('executive-dashboard'),
                'icon'      => 'fas fa-chart-line',
                'active'    => (bool)(url_is('executive-dashboard') || url_is('dashboard/executive')),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'gis',
                'name'      => 'Peta Jaringan GIS',
                'category'  => 'DASHBOARD',
                'desc'      => 'GIS Network Health Map',
                'href'      => site_url('gis'),
                'icon'      => 'fas fa-map-marked-alt',
                'active'    => (bool)(url_is('gis*') || url_is('peta-jaringan*')),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'sld-feeder',
                'name'      => 'SLD Feeder',
                'category'  => 'DASHBOARD',
                'desc'      => 'Single Line Diagram Feeder',
                'href'      => site_url('sld/view/15'),
                'icon'      => 'fas fa-project-diagram',
                'active'    => (bool)(url_is('sld/view*') || url_is('sld/feeder*')),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'sld-val',
                'name'      => 'SLD Validation',
                'category'  => 'DASHBOARD',
                'desc'      => 'Validasi topologi SLD',
                'href'      => site_url('sld/validation'),
                'icon'      => 'fas fa-shield-alt',
                'active'    => (bool)url_is('sld/validation*'),
                'inRail'    => false,
                'roles'     => null,
            ],

            // OPERATIONS
            [
                'id'        => 'planning',
                'name'      => 'Planning Inspeksi',
                'category'  => 'OPERATIONS',
                'desc'      => 'Jadwal pemeliharaan',
                'href'      => site_url('planning'),
                'icon'      => 'fas fa-clipboard-list',
                'active'    => (bool)url_is('planning*'),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'my-inspections',
                'name'      => 'Tugas Inspeksi Saya',
                'category'  => 'OPERATIONS',
                'desc'      => 'Tugas inspeksi aktif',
                'href'      => site_url('my-inspections'),
                'icon'      => 'fas fa-tasks',
                'active'    => (bool)url_is('my-inspections*'),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'my-history',
                'name'      => 'Riwayat Saya',
                'category'  => 'OPERATIONS',
                'desc'      => 'Riwayat inspeksi lapangan',
                'href'      => site_url('my-history'),
                'icon'      => 'fas fa-history',
                'active'    => (bool)url_is('my-history*'),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'input-temuan',
                'name'      => 'Input Temuan Baru',
                'category'  => 'OPERATIONS',
                'desc'      => 'Quick input temuan',
                'href'      => site_url('temuan/create'),
                'icon'      => 'fas fa-plus-circle',
                'active'    => (bool)url_is('temuan/create'),
                'inRail'    => true,
                'roles'     => ['administrator', 'admin_ulp', 'inspeksi'],
            ],
            [
                'id'        => 'daftar-temuan',
                'name'      => 'Daftar Semua Temuan',
                'category'  => 'OPERATIONS',
                'desc'      => 'Database 500+ temuan',
                'href'      => site_url('temuan'),
                'icon'      => 'fas fa-tasks',
                'active'    => (bool)(url_is('temuan') && !url_is('temuan/terdekat') && !url_is('temuan/create') && !url_is('temuan/update-pekerjaan')),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'har-update',
                'name'      => 'Update Pekerjaan (HAR)',
                'category'  => 'OPERATIONS',
                'desc'      => 'Tindak lanjut HAR & eksekusi',
                'href'      => site_url('temuan/update-pekerjaan'),
                'icon'      => 'fas fa-wrench',
                'active'    => (bool)url_is('temuan/update-pekerjaan*'),
                'inRail'    => true,
                'roles'     => ['administrator', 'admin_ulp', 'pdkb', 'har_gardu', 'har_konstruksi', 'har_row', 'har_crane', 'yantek'],
            ],
            [
                'id'        => 'temuan-terdekat',
                'name'      => 'Temuan Terdekat',
                'category'  => 'OPERATIONS',
                'desc'      => 'Pencarian radius terdekat',
                'href'      => site_url('temuan/terdekat'),
                'icon'      => 'fas fa-crosshairs',
                'active'    => (bool)url_is('temuan/terdekat*'),
                'inRail'    => false,
                'roles'     => null,
            ],
            [
                'id'        => 'work-orders',
                'name'      => 'Work Orders (WO)',
                'category'  => 'OPERATIONS',
                'desc'      => 'Feed perintah kerja tim',
                'href'      => site_url('work-orders'),
                'icon'      => 'fas fa-file-signature',
                'active'    => (bool)url_is('work-orders*'),
                'inRail'    => true,
                'roles'     => null,
            ],

            // REMEDIASI
            [
                'id'        => 'remediasi-wp',
                'name'      => 'Work Package Remediasi',
                'category'  => 'REMEDIASI',
                'desc'      => 'Manajemen work package',
                'href'      => site_url('remediation'),
                'icon'      => 'fas fa-briefcase',
                'active'    => (bool)(url_is('remediation') && !url_is('remediation/*')),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'remediasi-create',
                'name'      => 'Buat Work Package',
                'category'  => 'REMEDIASI',
                'desc'      => 'Paket eksekusi pemulihan',
                'href'      => site_url('remediation/create'),
                'icon'      => 'fas fa-folder-plus',
                'active'    => (bool)url_is('remediation/create*'),
                'inRail'    => false,
                'roles'     => null,
            ],

            // AI
            [
                'id'        => 'ai-copilot',
                'name'      => 'SIDAK AI Copilot',
                'category'  => 'AI',
                'desc'      => 'Rekomendasi & asisten AI',
                'href'      => site_url('ai-copilot'),
                'icon'      => 'fas fa-robot',
                'active'    => (bool)url_is('ai-copilot*'),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'ai-center',
                'name'      => 'AI Center & Predictive',
                'category'  => 'AI',
                'desc'      => 'Model prediksi kegagalan aset',
                'href'      => site_url('ai-center'),
                'icon'      => 'fas fa-brain',
                'active'    => (bool)url_is('ai-center*'),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'ai-ranking',
                'name'      => 'Auto Ranking',
                'category'  => 'AI',
                'desc'      => 'Matriks ranking penyulang',
                'href'      => site_url('ranking'),
                'icon'      => 'fas fa-medal',
                'active'    => (bool)url_is('ranking*'),
                'inRail'    => true,
                'roles'     => null,
            ],

            // MASTER
            [
                'id'        => 'asset-corpus',
                'name'      => 'Asset Corpus',
                'category'  => 'MASTER',
                'desc'      => 'Repositori dokumen & data aset',
                'href'      => site_url('asset-corpus/workspace'),
                'icon'      => 'fas fa-file-csv',
                'active'    => (bool)url_is('asset-corpus*'),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'asset-health',
                'name'      => 'Asset Health Index',
                'category'  => 'MASTER',
                'desc'      => 'Kondisi kesehatan aset',
                'href'      => site_url('asset-health'),
                'icon'      => 'fas fa-heartbeat',
                'active'    => (bool)url_is('asset-health*'),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'master-assets',
                'name'      => 'Master Asset PLN',
                'category'  => 'MASTER',
                'desc'      => 'Data master gardu & tiang',
                'href'      => site_url('master-assets'),
                'icon'      => 'fas fa-boxes',
                'active'    => (bool)url_is('master-assets*'),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'master-gi',
                'name'      => 'Gardu Induk (GI)',
                'category'  => 'MASTER',
                'desc'      => 'Monitoring Gardu Induk',
                'href'      => site_url('master-gi'),
                'icon'      => 'fas fa-charging-station',
                'active'    => (bool)url_is('master-gi*'),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'data-ulp',
                'name'      => 'Data ULP',
                'category'  => 'MASTER',
                'desc'      => 'Unit Layanan Pelanggan',
                'href'      => site_url('ulps'),
                'icon'      => 'fas fa-building',
                'active'    => (bool)url_is('ulps*'),
                'inRail'    => false,
                'roles'     => ['administrator'],
            ],
            [
                'id'        => 'penyulang',
                'name'      => 'Data Penyulang',
                'category'  => 'MASTER',
                'desc'      => '143 penyulang distribusi',
                'href'      => site_url('penyulang'),
                'icon'      => 'fas fa-route',
                'active'    => (bool)url_is('penyulang*'),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'sections',
                'name'      => 'Data Section',
                'category'  => 'MASTER',
                'desc'      => 'Zona section jaringan',
                'href'      => site_url('sections'),
                'icon'      => 'fas fa-map-pin',
                'active'    => (bool)url_is('sections*'),
                'inRail'    => false,
                'roles'     => null,
            ],
            [
                'id'        => 'data-user',
                'name'      => 'Data User',
                'category'  => 'MASTER',
                'desc'      => 'Manajemen pengguna',
                'href'      => site_url('users'),
                'icon'      => 'fas fa-users',
                'active'    => (bool)url_is('users*'),
                'inRail'    => true,
                'roles'     => ['administrator'],
            ],
            [
                'id'        => 'import-excel',
                'name'      => 'Impor Excel',
                'category'  => 'MASTER',
                'desc'      => 'Import data temuan & aset',
                'href'      => site_url('import'),
                'icon'      => 'fas fa-file-excel',
                'active'    => (bool)url_is('import*'),
                'inRail'    => false,
                'roles'     => ['administrator', 'admin_ulp', 'inspeksi'],
            ],

            // SYSTEM
            [
                'id'        => 'ecc',
                'name'      => 'Command Center (ECC)',
                'category'  => 'SYSTEM',
                'desc'      => 'Monitoring layar lebar & peta',
                'href'      => site_url('ecc'),
                'icon'      => 'fas fa-tv',
                'active'    => (bool)url_is('ecc*'),
                'inRail'    => false,
                'roles'     => null,
            ],
            [
                'id'        => 'notifikasi',
                'name'      => 'Notifikasi Center',
                'category'  => 'SYSTEM',
                'desc'      => 'Pusat pemberitahuan',
                'href'      => site_url('notifications'),
                'icon'      => 'fas fa-bell',
                'active'    => (bool)url_is('notifications*'),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'my-dashboard',
                'name'      => 'My Dashboard',
                'category'  => 'SYSTEM',
                'desc'      => 'Personalisasi petugas',
                'href'      => site_url('my-dashboard'),
                'icon'      => 'fas fa-user-circle',
                'active'    => (bool)url_is('my-dashboard*'),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'laporan',
                'name'      => 'Pusat Laporan',
                'category'  => 'SYSTEM',
                'desc'      => 'Cetak & ekspor laporan',
                'href'      => site_url('laporan'),
                'icon'      => 'fas fa-file-alt',
                'active'    => (bool)url_is('laporan*'),
                'inRail'    => true,
                'roles'     => null,
            ],
            [
                'id'        => 'documents',
                'name'      => 'Dokumen',
                'category'  => 'SYSTEM',
                'desc'      => 'Arsip & dokumen teknis',
                'href'      => site_url('documents'),
                'icon'      => 'fas fa-folder',
                'active'    => (bool)url_is('documents*'),
                'inRail'    => false,
                'roles'     => null,
            ],
            [
                'id'        => 'audit-log',
                'name'      => 'Audit Log',
                'category'  => 'SYSTEM',
                'desc'      => 'Log jejak aktivitas',
                'href'      => site_url('audit-log'),
                'icon'      => 'fas fa-history',
                'active'    => (bool)url_is('audit-log*'),
                'inRail'    => false,
                'roles'     => ['administrator'],
            ],
            [
                'id'        => 'integration',
                'name'      => 'Integrasi & API',
                'category'  => 'SYSTEM',
                'desc'      => 'Konfigurasi integrasi eksternal',
                'href'      => site_url('integration'),
                'icon'      => 'fas fa-network-wired',
                'active'    => (bool)url_is('integration*'),
                'inRail'    => false,
                'roles'     => ['administrator'],
            ],
            [
                'id'        => 'backup-database',
                'name'      => 'Backup Database',
                'category'  => 'SYSTEM',
                'desc'      => 'Cadangkan database sistem',
                'href'      => site_url('backup-database'),
                'icon'      => 'fas fa-database',
                'active'    => (bool)url_is('backup-database*'),
                'inRail'    => true,
                'roles'     => ['administrator'],
            ],
            [
                'id'        => 'change-password',
                'name'      => 'Ganti Password',
                'category'  => 'SYSTEM',
                'desc'      => 'Ubah kata sandi akun',
                'href'      => site_url('change-password'),
                'icon'      => 'fas fa-key',
                'active'    => (bool)url_is('change-password*'),
                'inRail'    => false,
                'roles'     => null,
            ],
        ];

        $categoriesDef = [
            'DASHBOARD'   => ['name' => 'Dashboard Utama', 'icon' => 'fas fa-th-large', 'desc' => 'Executive overview & live KPIs - simple, tapi semua menu tetap ada.'],
            'OPERATIONS'  => ['name' => 'Operasional & Lapangan', 'icon' => 'fas fa-tools', 'desc' => 'Perencanaan, tugas inspeksi, temuan & work order.'],
            'REMEDIASI'   => ['name' => 'Remediasi & Tindak Lanjut', 'icon' => 'fas fa-wrench', 'desc' => 'Work package pemulihan & tindak lanjut temuan.'],
            'AI'          => ['name' => 'AI Command Center', 'icon' => 'fas fa-brain', 'desc' => 'SIDAK AI Copilot, auto ranking & predictive analysis.'],
            'MASTER'      => ['name' => 'Data & Referensi', 'icon' => 'fas fa-database', 'desc' => 'Master data aset PLN, penyulang, GI & ULP.'],
            'SYSTEM'      => ['name' => 'System & Laporan', 'icon' => 'fas fa-cogs', 'desc' => 'Pusat laporan, notifikasi, backup & administrasi.'],
        ];

        $authorizedMenuItems = array_values(array_filter($rawMenuItems, function($item) {
            if ($item['roles'] === null) {
                return true;
            }
            return check_role($item['roles']);
        }));

        return [
            'categories' => $categoriesDef,
            'items'      => $authorizedMenuItems,
            'raw_items'  => $rawMenuItems,
        ];
    }
}
