<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Sidak Tejo - Mobile Dashboard</title>
    <!-- Local Google Fonts: Outfit & Inter -->
    <link rel="stylesheet" href="<?= base_url('assets/fonts/fonts.css') ?>">
    <!-- FontAwesome 6 Local -->
    <link rel="stylesheet" href="<?= base_url('plugins/fontawesome-free/css/all.min.css') ?>">
    <!-- Bootstrap 5 Local CSS -->
    <link rel="stylesheet" href="<?= base_url('plugins/bootstrap/css/bootstrap.min.css') ?>">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f6f9;
            color: #1e293b;
            padding-bottom: 30px;
            position: relative;
        }
        
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('<?= base_url("assets/img/logo_sidak.png") ?>');
            background-repeat: no-repeat;
            background-position: center 40%;
            background-size: 280px;
            opacity: 0.035; /* Ultra faint watermark for mobile layout */
            z-index: -1;
            pointer-events: none;
        }
        
        /* Mobile Header Banner */
        /* Mobile Header Banner - Maximum 170px */
        .mobile-header {
            background: linear-gradient(135deg, #003f8a 0%, #005eb8 50%, #007275 100%);
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
            padding: 12px 16px 14px 16px;
            color: #ffffff;
            position: relative;
            box-shadow: 0 4px 20px rgba(0, 63, 138, 0.25);
            max-height: 170px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .brand-logo img {
            height: 26px;
        }
        
        .brand-text {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 0.95rem;
            line-height: 1.1;
        }
        
        .btn-logout-mobile {
            background-color: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            transition: background-color 0.2s;
            text-decoration: none;
        }
        
        .btn-logout-mobile:active {
            background-color: rgba(255, 255, 255, 0.35);
        }
        
        .user-info-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 6px;
        }
        
        .welcome-text {
            font-size: 0.75rem;
            opacity: 0.9;
            margin: 0;
            line-height: 1;
        }
        
        .user-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.15rem;
            margin: 0;
            letter-spacing: 0.2px;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        
        .status-badge-container {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
        }

        .badge-role {
            background: rgba(255, 255, 255, 0.22);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #ffffff;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 3px 7px;
            border-radius: 10px;
            letter-spacing: 0.3px;
        }
        
        .badge-online {
            background: #10b981;
            color: #ffffff;
            font-size: 9px;
            font-weight: 700;
            padding: 3px 7px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .badge-online.offline {
            background: #ef4444;
        }

        .last-sync-tag {
            font-size: 9px;
            opacity: 0.85;
            color: #ffffff;
            white-space: nowrap;
        }
        
        /* Grid Menu Container - Clean Margin to Prevent Overlap */
        .menu-container {
            margin-top: 14px;
            padding: 0 16px;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        /* Menu Card Item - MD3 Rounded 18px & Glass Accent */
        .menu-card {
            background-color: #ffffff;
            border-radius: 18px;
            padding: 16px 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            text-decoration: none;
            color: #1e293b;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.9);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            position: relative;
            overflow: hidden;
        }
        
        .menu-card:active, .menu-card:hover {
            transform: scale(0.97);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
        }
        
        .icon-circle {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            transition: transform 0.2s ease;
        }

        .menu-card:hover .icon-circle {
            transform: scale(1.08);
        }
        
        .card-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #334155;
            line-height: 1.25;
        }
        
        /* Version Toggle Footer */
        .footer-toggle {
            text-align: center;
            margin-top: 30px;
            font-size: 0.8rem;
        }
        
        .btn-toggle-version {
            color: #005eb8;
            text-decoration: underline;
            background: none;
            border: none;
            font-weight: 600;
        }

        @keyframes tickerAnimation {
            0% { transform: translate3d(0, 0, 0); }
            100% { transform: translate3d(-100%, 0, 0); }
        }
    </style>
</head>
<body>

    <!-- Header Banner - Maksimal 170px -->
    <div class="mobile-header">
        <div class="header-top">
            <a href="<?= site_url('dashboard') ?>" class="brand-logo text-decoration-none text-white" style="cursor: pointer;" title="Ke Dashboard">
                <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="Logo">
                <div class="brand-text">
                    SIDAK TEJO<br>
                    <span style="font-size: 0.65rem; font-weight: 500; opacity: 0.9;">PLN UP3 SIDOARJO</span>
                </div>
            </a>
            <div class="d-flex align-items-center gap-2">
                <span class="last-sync-tag" id="header-last-sync" title="Last Sync"><i class="fas fa-sync-alt me-1"></i> <span id="sync-time-str"><?= date('H:i') ?></span></span>
                <a href="<?= site_url('auth/logout') ?>" class="btn-logout-mobile" title="Keluar">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
        
        <div class="user-info-bar">
            <div>
                <p class="welcome-text mb-0" style="font-size: 0.75rem; color: #fde047; font-weight: 600;"><?= greeting() ?>,</p>
                <h2 class="user-name my-0" style="font-size: 1.05rem;"><?= esc($userName) ?></h2>
                <div class="text-white opacity-90 mt-1" style="font-size: 0.65rem; font-weight: 500;">
                    <i class="fas fa-calendar-day me-1 text-warning"></i> <?= indo_date(date('Y-m-d'), true) ?> • <span id="live-mobile-clock" class="fw-bold text-white"><?= date('H:i:s') ?> WIB</span>
                </div>
            </div>
            <div class="status-badge-container d-flex flex-column align-items-end" style="gap: 2px;">
                <span class="badge-role"><?= esc(get_role_label(session()->get('user_role') ?: 'USER')) ?></span>
                <span class="badge bg-light text-dark font-weight-bold" style="font-size: 9px; padding: 2px 6px; border-radius: 4px;"><?= shift() ?></span>
                <span class="badge-online" id="net-status-pill"><i class="fas fa-wifi"></i> Online</span>
            </div>
        </div>
        
        <!-- Running Motivational Ticker Mobile -->
        <div class="p-1 px-2 rounded-3 d-flex align-items-center" style="background: rgba(255,255,255,0.18); backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.3); border-radius: 8px; overflow: hidden; height: 24px;">
            <i class="fas fa-bullhorn text-warning me-2" style="font-size: 11px; flex-shrink: 0;"></i>
            <div style="overflow: hidden; flex-grow: 1; display: flex; align-items: center;">
                <marquee scrollamount="4" behavior="scroll" direction="left" style="font-size: 10px; font-weight: 700; color: #ffffff; margin: 0; line-height: 1.2;">
                    <?= esc(get_daily_announcement()) ?>
                </marquee>
            </div>
        </div>
    </div>

    <!-- Menu Grid -->
    <div class="menu-container">
            <?php 
            $userRole = $userRole ?? (session()->get('user_role') ?: 'inspeksi');
            $canInput = $canInput ?? check_role(['administrator', 'admin_ulp', 'inspeksi']);
            $canEdit = $canEdit ?? check_role(['administrator', 'admin_ulp', 'inspeksi', 'yantek', 'pdkb', 'har_gardu', 'har_konstruksi', 'har_row', 'har_crane']);
            $canDelete = $canDelete ?? check_role(['administrator']);
            $canApprove = $canApprove ?? check_role(['administrator', 'supervisor_ulp', 'supervisor_up3']);
            $canMonitoring = $canMonitoring ?? check_role(['administrator', 'admin_pusat', 'supervisor_up3', 'manager']);
            ?>

            <!-- Executive Dashboard Analytics -->
            <a href="<?= site_url('executive-dashboard') ?>" class="menu-card" style="background: linear-gradient(135deg, #003f8a 0%, #005eb8 100%); color: #ffffff;">
                <div class="icon-circle" style="background-color: rgba(255, 255, 255, 0.2);">
                    <i class="fas fa-chart-line text-warning" style="font-size: 20px;"></i>
                </div>
                <div class="card-label text-white">Executive Analytics</div>
            </a>

            <!-- 1. Input Temuan -->
            <?php if ($canInput): ?>
            <a href="<?= site_url('temuan/create') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #fef3c7;">
                    <i class="fas fa-plus-circle" style="color: #d97706; font-size: 20px;"></i>
                </div>
                <div class="card-label">Input Temuan</div>
            </a>
            <?php endif; ?>
            
            <!-- 2. Data Temuan -->
            <a href="<?= site_url('temuan') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #dbeafe;">
                    <i class="fas fa-list-check" style="color: #2563eb; font-size: 20px;"></i>
                </div>
                <div class="card-label">Data Temuan</div>
            </a>
            
            <!-- Update Pekerjaan -->
            <a href="<?= site_url('temuan/update-pekerjaan') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #fff7ed;">
                    <i class="fas fa-edit" style="color: #ea580c; font-size: 20px;"></i>
                </div>
                <div class="card-label">Update Pekerjaan</div>
            </a>
            
            <!-- 3. Temuan Terdekat -->
            <a href="<?= site_url('temuan/terdekat') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #e0f2fe;">
                    <i class="fas fa-map-location-dot" style="color: #0284c7; font-size: 20px;"></i>
                </div>
                <div class="card-label">Temuan Terdekat</div>
            </a>
            
            <!-- 4. Eviden Kubikel -->
            <a href="<?= site_url('eviden/kubikel') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #ffedd5;">
                    <i class="fas fa-cubes" style="color: #ea580c; font-size: 20px;"></i>
                </div>
                <div class="card-label">Eviden Kubikel</div>
            </a>
            
            <!-- 5. Eviden Trafo -->
            <a href="<?= site_url('eviden/trafo') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #f3e8ff;">
                    <i class="fas fa-bolt" style="color: #9333ea; font-size: 20px;"></i>
                </div>
                <div class="card-label">Eviden Trafo</div>
            </a>
            
            <!-- 6. Management Trafo -->
            <a href="<?= site_url('eviden/management') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #dcfce7;">
                    <i class="fas fa-folder-tree" style="color: #16a34a; font-size: 20px;"></i>
                </div>
                <div class="card-label">Management Trafo</div>
            </a>
            
            <!-- 7. Lap. Temuan -->
            <a href="<?= site_url('laporan/temuan') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #f1f5f9;">
                    <i class="fas fa-print" style="color: #475569; font-size: 20px;"></i>
                </div>
                <div class="card-label">Lap. Temuan</div>
            </a>
            
            <!-- 8. Lap. Eviden -->
            <a href="<?= site_url('laporan/eviden') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #fee2e2;">
                    <i class="fas fa-images" style="color: #dc2626; font-size: 20px;"></i>
                </div>
                <div class="card-label">Lap. Eviden</div>
            </a>
            
            <!-- 9. Lap. Management Trafo -->
            <a href="<?= site_url('laporan/management') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #ecfeff;">
                    <i class="fas fa-file-invoice" style="color: #0891b2; font-size: 20px;"></i>
                </div>
                <div class="card-label">Lap. Management</div>
            </a>
            
            <!-- Master Asset PLN -->
            <a href="<?= site_url('assets') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #fef9c3;">
                    <i class="fas fa-boxes-stacked" style="color: #ca8a04; font-size: 20px;"></i>
                </div>
                <div class="card-label">Master Asset</div>
            </a>
            
            <!-- Work Orders (WO) -->
            <a href="<?= site_url('work-orders') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #e0f2fe;">
                    <i class="fas fa-screwdriver-wrench" style="color: #0284c7; font-size: 20px;"></i>
                </div>
                <div class="card-label">Work Orders</div>
            </a>

            <!-- Peta Jaringan GIS -->
            <a href="<?= site_url('gis') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #dcfce7;">
                    <i class="fas fa-map-marked-alt" style="color: #16a34a; font-size: 20px;"></i>
                </div>
                <div class="card-label">Peta Jaringan</div>
            </a>

            <!-- AI Predictive Maintenance -->
            <a href="<?= site_url('ai-predictive') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #f3e8ff;">
                    <i class="fas fa-brain" style="color: #9333ea; font-size: 20px;"></i>
                </div>
                <div class="card-label">AI Predictive</div>
            </a>

            <!-- Notifikasi Center -->
            <a href="<?= site_url('notifications') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #fef3c7;">
                    <i class="fas fa-bell" style="color: #d97706; font-size: 20px;"></i>
                </div>
                <div class="card-label">Notifikasi</div>
            </a>

            <!-- Executive Command Center (ECC) -->
            <a href="<?= site_url('ecc') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #ffe4e6;">
                    <i class="fas fa-tv" style="color: #e11d48; font-size: 20px;"></i>
                </div>
                <div class="card-label">Command Center</div>
            </a>

            <!-- 10. Identifikasi Gangguan -->
            <a href="<?= site_url('identifikasi') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #fdf2f8;">
                    <i class="fas fa-bolt-lightning" style="color: #db2777; font-size: 20px;"></i>
                </div>
                <div class="card-label">Identifikasi Gangguan</div>
            </a>

            <!-- Dokumen Center (Phase 23) -->
            <a href="<?= site_url('documents') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #e0f2fe;">
                    <i class="fas fa-file-contract" style="color: #0284c7; font-size: 20px;"></i>
                </div>
                <div class="card-label">Dokumen</div>
            </a>

            <!-- Integration Center (Phase 24) -->
            <a href="<?= site_url('integration') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #f3e8ff;">
                    <i class="fas fa-network-wired" style="color: #7e22ce; font-size: 20px;"></i>
                </div>
                <div class="card-label">Integration</div>
            </a>

        </div>
    </div>

    <!-- Top 10 Leaderboard Section Mobile -->
    <div class="px-3 mt-4 mb-2">
        <h6 class="text-secondary font-weight-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px; font-family: 'Outfit', sans-serif;"><i class="fas fa-trophy text-warning me-1"></i> Rekap Kinerja & Top 10</h6>
    </div>
    <div class="px-3 mb-3">
        <!-- Top 10 Input -->
        <div class="card border-0 shadow-sm rounded-3 mb-3 overflow-hidden">
            <div class="card-header py-2 px-3" style="background: linear-gradient(135deg, #004D4F 0%, #007275 100%); color: #ffffff;">
                <span class="font-weight-bold" style="font-size: 12px;"><i class="fas fa-file-signature text-warning me-1"></i> Top 10 Input Temuan</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topInputOfficers)): ?>
                    <div class="text-center py-3 text-muted small">Belum ada data input pada periode ini.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush" style="font-size: 12px;">
                        <?php foreach ($topInputOfficers as $idx => $officer): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                <div>
                                    <span class="fw-bold me-1"><?= $idx + 1 ?>.</span>
                                    <span class="fw-bold text-dark"><?= esc($officer['created_by_name']) ?></span>
                                    <small class="text-muted d-block" style="font-size: 10px;">NIP: <?= esc($officer['created_by_nip'] ?: '-') ?></small>
                                </div>
                                <span class="badge bg-primary rounded-pill"><?= number_format($officer['total_input']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top 10 Update -->
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header py-2 px-3" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #ffffff;">
                <span class="font-weight-bold" style="font-size: 12px;"><i class="fas fa-check-circle text-white me-1"></i> Top 10 Update & Eksekusi</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topUpdateOfficers)): ?>
                    <div class="text-center py-3 text-muted small">Belum ada data update pada periode ini.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush" style="font-size: 12px;">
                        <?php foreach ($topUpdateOfficers as $idx => $officer): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                <div>
                                    <span class="fw-bold me-1"><?= $idx + 1 ?>.</span>
                                    <span class="fw-bold text-dark"><?= esc($officer['updated_by_name']) ?></span>
                                    <small class="text-muted d-block" style="font-size: 10px;">NIP: <?= esc($officer['updated_by_nip'] ?: '-') ?></small>
                                </div>
                                <span class="badge bg-success rounded-pill" style="background-color: #059669 !important;"><?= number_format($officer['total_update']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Data Master Section (For Admins) -->
    <?php if (check_role(['administrator', 'admin', 'admin_pusat', 'admin_ulp'])): ?>
        <div class="px-3 mt-4 mb-2">
            <h6 class="text-secondary font-weight-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px; font-family: 'Outfit', sans-serif;"><i class="fas fa-database mr-1"></i> Data Master</h6>
        </div>
        <div class="menu-container">
            <div class="menu-grid">
                <?php if (session()->get('user_role') === 'administrator'): ?>
                    <a href="<?= site_url('ulps') ?>" class="menu-card py-3">
                        <div class="icon-circle mb-2" style="width: 36px; height: 36px; margin-bottom: 8px; background-color: #f1f5f9;">
                            <i class="fas fa-building text-secondary" style="font-size: 14px;"></i>
                        </div>
                        <div class="card-label" style="font-size: 0.75rem;">Data ULP</div>
                    </a>
                <?php endif; ?>
                
                <a href="<?= site_url('penyulang') ?>" class="menu-card py-3">
                    <div class="icon-circle mb-2" style="width: 36px; height: 36px; margin-bottom: 8px; background-color: #f1f5f9;">
                        <i class="fas fa-network-wired text-secondary" style="font-size: 14px;"></i>
                    </div>
                    <div class="card-label" style="font-size: 0.75rem;">Data Penyulang</div>
                </a>
                
                <a href="<?= site_url('sections') ?>" class="menu-card py-3">
                    <div class="icon-circle mb-2" style="width: 36px; height: 36px; margin-bottom: 8px; background-color: #f1f5f9;">
                        <i class="fas fa-route text-secondary" style="font-size: 14px;"></i>
                    </div>
                    <div class="card-label" style="font-size: 0.75rem;">Data Section</div>
                </a>
                
                <a href="<?= site_url('users') ?>" class="menu-card py-3">
                    <div class="icon-circle mb-2" style="width: 36px; height: 36px; margin-bottom: 8px; background-color: #f1f5f9;">
                        <i class="fas fa-users text-secondary" style="font-size: 14px;"></i>
                    </div>
                    <div class="card-label" style="font-size: 0.75rem;">Data User</div>
                </a>

                <a href="<?= site_url('import') ?>" class="menu-card py-3">
                    <div class="icon-circle mb-2" style="width: 36px; height: 36px; margin-bottom: 8px; background-color: #f0fdf4;">
                        <i class="fas fa-file-excel text-success" style="font-size: 14px;"></i>
                    </div>
                    <div class="card-label" style="font-size: 0.75rem;">Impor Excel</div>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Toggle Version Footer -->
    <div class="footer-toggle text-muted px-3">
        <p class="mb-2">Menampilkan versi mobile khusus lapangan.</p>
        <a href="<?= site_url('dashboard/toggle-view?t=' . time()) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold text-primary shadow-sm" style="font-size: 11px; border-color: #005eb8;">
            <i class="fas fa-desktop mr-1"></i> Beralih ke Versi Desktop
        </a>
    </div>

    <!-- Local Scripts for jQuery & SweetAlert2 -->
    <script src="<?= base_url('plugins/jquery/jquery.min.js') ?>"></script>
    <script src="<?= base_url('plugins/sweetalert2/sweetalert2.all.min.js') ?>"></script>
    <script>
        function promptEditAnnouncement() {
            var currentMsg = $('.running-announcement-text-target').first().text().trim();
            Swal.fire({
                title: 'Edit Kata-Kata Motivasi Harian',
                text: 'Masukkan pesan motivasi atau pengumuman harian untuk seluruh tim:',
                input: 'textarea',
                inputValue: currentMsg,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-paper-plane me-1"></i> Simpan & Tampilkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#004D4F',
                inputValidator: (value) => {
                    if (!value || value.trim().length === 0) {
                        return 'Kata-kata motivasi harian tidak boleh kosong!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "<?= site_url('setting/update-announcement') ?>",
                        type: 'POST',
                        data: {
                            message: result.value,
                            "<?= csrf_token() ?>": "<?= csrf_hash() ?>"
                        },
                        dataType: 'json',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        success: function(res) {
                            if (res.success) {
                                var $targets = $('.running-announcement-text-target');
                                $targets.text(res.announcement);
                                $targets.css('animation', 'none');
                                $targets.each(function() { void this.offsetWidth; });
                                $targets.css('animation', 'tickerAnimation 20s linear infinite');

                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: res.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            } else {
                                Swal.fire('Gagal', res.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Gagal menyimpan kata-kata motivasi harian.', 'error');
                        }
                    });
                }
            });
        }
    </script>

    <!-- Floating Voice Assistant (Mobile Dashboard) -->
    <style>
        #global-voice-container {
            position: fixed !important;
            bottom: 90px !important;
            right: 20px !important;
            z-index: 999999 !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            pointer-events: auto !important;
            touch-action: manipulation !important;
            transition: bottom 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        #global-voice-container.shifted {
            bottom: 160px !important;
            transform: translateY(-20px) !important;
        }
        #btn-global-mic {
            width: 64px !important;
            height: 64px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 1.6rem !important;
            border: 2px solid rgba(255,255,255,0.3) !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4) !important;
            background: linear-gradient(135deg, #005eb8 0%, #003f8a 100%) !important;
            pointer-events: auto !important;
            cursor: pointer !important;
            touch-action: manipulation !important;
            -webkit-tap-highlight-color: rgba(0,0,0,0) !important;
        }
        #btn-global-mic.listening {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
            border-color: #fca5a5 !important;
            animation: pulseMicAnimationMobile 1.2s infinite;
        }
        @keyframes pulseMicAnimationMobile {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 14px rgba(239, 68, 68, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
    </style>
    <div id="global-voice-container">
        <div id="global-voice-bubble" class="shadow-sm d-none animate__animated animate__fadeInRight" 
             style="background: #0f172a; color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.4); padding: 8px 14px; border-radius: 20px; font-size: 0.82rem; font-weight: bold; white-space: nowrap; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);">
            <i class="fas fa-circle-notch fa-spin mr-1"></i> <span id="global-voice-text">Mendengarkan...</span>
        </div>
        
        <button type="button" id="btn-global-mic" class="btn btn-primary" title="Perintah Suara">
            <i class="fas fa-microphone" id="global-mic-icon"></i>
        </button>
    </div>


    <script>
        $(function() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            let recognition = null;
            let isListening = false;

            if (SpeechRecognition) {
                try {
                    recognition = new SpeechRecognition();
                    recognition.lang = 'id-ID';
                    recognition.interimResults = false;

                    recognition.onstart = function() {
                        isListening = true;
                        $('#btn-global-mic').addClass('listening');
                        $('#global-voice-bubble').removeClass('d-none');
                        $('#global-voice-text').text('Mendengarkan...');
                    };

                    recognition.onerror = function(event) {
                        isListening = false;
                        $('#btn-global-mic').removeClass('listening');
                        $('#global-voice-bubble').addClass('d-none');
                        if (event.error === 'not-allowed') {
                            const isHttp = !window.isSecureContext && location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1';
                            const msg = isHttp 
                                ? 'Fitur Suara membutuhkan koneksi HTTPS. Peramban memblokir akses mikrofon pada koneksi HTTP (bukan HTTPS). Harap aktifkan SSL/HTTPS pada server.' 
                                : 'Harap izinkan akses mikrofon untuk menggunakan perintah suara.';
                            Swal.fire({ icon: 'warning', title: 'Akses Mikrofon Ditolak', text: msg, confirmButtonColor: '#005eb8' });
                        }
                    };

                    recognition.onend = function() {
                        isListening = false;
                        $('#btn-global-mic').removeClass('listening');
                    };

                    recognition.onresult = function(event) {
                        const resultText = event.results[0][0].transcript.toLowerCase().trim();
                        $('#global-voice-bubble').removeClass('d-none');
                        $('#global-voice-text').html('<i class="fas fa-quote-left mr-1"></i> "' + resultText + '"');
                        
                        setTimeout(function() { $('#global-voice-bubble').addClass('d-none'); }, 3500);
                        processVoiceCommandMobile(resultText);
                    };
                } catch(err) {
                    console.error('SpeechRecognition init error:', err);
                }
            }

            $(document).on('click', '#btn-global-mic', function(e) {
                e.preventDefault();
                if (!recognition) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Fitur Perintah Suara',
                        text: 'Peramban ini belum mendukung Speech Recognition. Disarankan memakai Google Chrome terbaru.',
                        confirmButtonColor: '#005eb8'
                    });
                    return;
                }

                if (!isListening) {
                    try { recognition.start(); } catch(err) {
                        try { recognition.stop(); setTimeout(() => recognition.start(), 150); } catch(ex){}
                    }
                } else {
                    try { recognition.stop(); } catch(err){}
                }
            });

            function processVoiceCommandMobile(text) {
                function speakAiFeedback(msg) {
                    if ('speechSynthesis' in window) {
                        try {
                            window.speechSynthesis.cancel();
                            const utterance = new SpeechSynthesisUtterance(msg);
                            utterance.lang = 'id-ID';
                            utterance.rate = 1.0;
                            window.speechSynthesis.speak(utterance);
                        } catch(e) {}
                    }
                }

                function showVoiceToast(msg, icon, aiSpeak) {
                    if (aiSpeak) speakAiFeedback(aiSpeak);
                    Swal.fire({
                        toast: true,
                        position: 'top',
                        icon: icon || 'info',
                        title: '🎤 AI Voice Assistant',
                        text: '"' + text + '" → ' + msg,
                        showConfirmButton: false,
                        timer: 3500,
                        timerProgressBar: true
                    });
                }

                text = text.toLowerCase().trim();

                // 1. TEMUAN TERDEKAT / PETA
                if (text.includes('terdekat') || text.includes('peta') || text.includes('gps')) {
                    let penyulangMatch = '';
                    if (text.includes('penyulang')) {
                        let parts = text.split('penyulang');
                        penyulangMatch = parts[1] ? parts[1].replace(/\b(data|tabel|temuan|master|tampilkan|lihat|buka|cari|saring|filter)\b/gi, '').trim() : '';
                    }
                    showVoiceToast('Membuka Temuan Terdekat' + (penyulangMatch ? ' (' + penyulangMatch + ')' : ''), 'success', 'Membuka peta temuan terdekat');
                    let targetUrl = '<?= site_url("temuan/terdekat") ?>';
                    if (penyulangMatch) {
                        targetUrl += '?penyulang=' + encodeURIComponent(penyulangMatch);
                    }
                    setTimeout(() => window.location.href = targetUrl, 800);
                    return true;
                }

                // 2. FILTER TEMUAN BERDASARKAN PENYULANG
                if (text.includes('penyulang')) {
                    let parts = text.split('penyulang');
                    let penyulangName = parts[1] ? parts[1].replace(/\b(data|tabel|temuan|master|tampilkan|lihat|buka|cari|saring|filter)\b/gi, '').trim() : '';
                    if (penyulangName) {
                        showVoiceToast('Menyaring Penyulang: "' + penyulangName + '"...', 'info', 'Menyaring penyulang ' + penyulangName);
                        setTimeout(() => window.location.href = '<?= site_url("temuan?q=") ?>' + encodeURIComponent(penyulangName), 800);
                        return true;
                    }
                }

                // 3. FILTER TEMUAN BERDASARKAN JENIS
                if (text.includes('jenis')) {
                    let parts = text.split('jenis');
                    let jenisName = parts[1] ? parts[1].replace(/\b(data|tabel|temuan|master|tampilkan|lihat|buka|cari|saring|filter)\b/gi, '').trim() : '';
                    if (jenisName) {
                        showVoiceToast('Menyaring Jenis Temuan: "' + jenisName + '"...', 'info', 'Menyaring jenis temuan ' + jenisName);
                        setTimeout(() => window.location.href = '<?= site_url("temuan?q=") ?>' + encodeURIComponent(jenisName), 800);
                        return true;
                    }
                }

                // 4. FILTER TEMUAN BERDASARKAN ULP
                if (text.includes('ulp') && !text.includes('master ulp') && !text.includes('data ulp')) {
                    let parts = text.split('ulp');
                    let ulpName = parts[1] ? parts[1].replace(/\b(data|tabel|temuan|master|tampilkan|lihat|buka|cari|saring|filter)\b/gi, '').trim() : '';
                    if (ulpName) {
                        showVoiceToast('Menyaring ULP: "' + ulpName + '"...', 'info', 'Menyaring ULP ' + ulpName);
                        setTimeout(() => window.location.href = '<?= site_url("temuan?q=") ?>' + encodeURIComponent(ulpName), 800);
                        return true;
                    }
                }

                // 5. INPUT / TAMBAH TEMUAN
                if (text.includes('input') || text.includes('tambah temuan') || text.includes('buat temuan') || text === 'tambah') {
                    showVoiceToast('Membuka Input Temuan Baru', 'success', 'Membuka form input temuan baru');
                    setTimeout(() => window.location.href = '<?= site_url("temuan/create") ?>', 800);
                    return true;
                }

                // 6. UPDATE PEKERJAAN / PROGRES
                if (text.includes('update') || text.includes('progres') || text.includes('tindak lanjut') || text.includes('pekerjaan')) {
                    showVoiceToast('Membuka Update Pekerjaan', 'success', 'Membuka update pekerjaan');
                    setTimeout(() => window.location.href = '<?= site_url("temuan/update-pekerjaan") ?>', 800);
                    return true;
                }

                // 7. EVIDEN (KUBIKEL, TRAFO, SAKLAR, MANAGEMENT)
                if (text.includes('kubikel')) {
                    showVoiceToast('Membuka Eviden Kubikel', 'success', 'Membuka eviden kubikel');
                    setTimeout(() => window.location.href = '<?= site_url("eviden/kubikel") ?>', 800);
                    return true;
                }
                if (text.includes('trafo')) {
                    showVoiceToast('Membuka Eviden Trafo', 'success', 'Membuka eviden trafo');
                    setTimeout(() => window.location.href = '<?= site_url("eviden/trafo") ?>', 800);
                    return true;
                }
                if (text.includes('saklar')) {
                    showVoiceToast('Membuka Eviden Saklar', 'success', 'Membuka eviden saklar');
                    setTimeout(() => window.location.href = '<?= site_url("eviden/saklar") ?>', 800);
                    return true;
                }
                if (text.includes('management') || text.includes('manajemen')) {
                    showVoiceToast('Membuka Eviden Management', 'success', 'Membuka eviden manajemen');
                    setTimeout(() => window.location.href = '<?= site_url("eviden/management") ?>', 800);
                    return true;
                }

                // 8. LAPORAN
                if (text.includes('laporan') || text.includes('rekap')) {
                    showVoiceToast('Membuka Pusat Laporan', 'success', 'Membuka pusat laporan temuan');
                    setTimeout(() => window.location.href = '<?= site_url("laporan/temuan") ?>', 800);
                    return true;
                }

                // 9. DASHBOARD
                if (text.includes('dashboard') || text.includes('beranda') || text.includes('home')) {
                    showVoiceToast('Membuka Dashboard', 'success', 'Membuka dashboard');
                    setTimeout(() => window.location.href = '<?= site_url("dashboard") ?>', 800);
                    return true;
                }

                // 10. LOGOUT
                if (text.includes('keluar') || text.includes('logout')) {
                    showVoiceToast('Proses Keluar Sistem...', 'warning', 'Proses keluar dari sistem');
                    setTimeout(() => window.location.href = '<?= site_url("logout") ?>', 800);
                    return true;
                }

                // 11. CARI KEYWORD
                if (text.includes('cari') || text.includes('tampilkan') || text.includes('lihat')) {
                    let cleanKw = text.replace(/\b(tolong|mohon|coba|tampilkan|lihat|buka|cari|temukan|filter|saring)\b/gi, '').trim();
                    if (cleanKw) {
                        showVoiceToast('Mencari "' + cleanKw + '"...', 'info', 'Mencari ' + cleanKw);
                        setTimeout(() => window.location.href = '<?= site_url("temuan?q=") ?>' + encodeURIComponent(cleanKw), 800);
                        return true;
                    }
                }

                // 12. FALLBACK
                showVoiceToast('Perintah kurang jelas. Silakan berikan perintah yang sesuai (Penyulang / Jenis Temuan / Terdekat)', 'warning', 'Perintah kurang jelas. Silakan sebutkan nama penyulang, jenis temuan, atau temuan terdekat.');
                return false;
            }

            // Global Smart Back Helper (Task 4)
            window.smartBack = function(fallbackUrl) {
                if (window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1) {
                    window.history.back();
                } else if (fallbackUrl) {
                    window.location.href = fallbackUrl;
                } else {
                    window.history.back();
                }
            };

            // Smart Floating Voice AI Position Adjustment (Task 2)
            $(document).on('focusin', 'input, textarea, select', function() {
                $('#global-voice-container').addClass('shifted');
            });
            $(document).on('focusout', 'input, textarea, select', function() {
                setTimeout(function() {
                    if (!$('input:focus, textarea:focus, select:focus').length) {
                        $('#global-voice-container').removeClass('shifted');
                    }
                }, 200);
            });
            $(document).on('show.bs.modal', function() {
                $('#global-voice-container').addClass('shifted');
            });
            $(document).on('hidden.bs.modal', function() {
                $('#global-voice-container').removeClass('shifted');
            });
        });
    </script>
</body>
</html>
