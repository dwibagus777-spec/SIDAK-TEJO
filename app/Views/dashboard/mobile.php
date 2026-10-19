<?php
$sidakMenuRegistry = get_sidak_menu_registry();
$categoriesDef = $sidakMenuRegistry['categories'];
$authorizedMenuItems = $sidakMenuRegistry['items'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SIDAK TEJO Mobile Enterprise Monitoring Center</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon_sidak.png') ?>">
    <link rel="shortcut icon" href="<?= base_url('assets/img/favicon_sidak.png') ?>">

    <!-- Bootstrap 5 & Font Awesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <script>
    // Early Theme Application to eliminate flicker
    (function() {
        var savedTheme = localStorage.getItem('sidak_theme');
        if (savedTheme === 'dark') {
            document.documentElement.classList.add('dark-mode');
            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('dark-mode');
                var icon = document.getElementById('m-theme-icon');
                if (icon) icon.className = 'fas fa-sun text-warning';
            });
        }
    })();
    </script>

    <style>
        :root {
            --primary-blue: #0284c7;
            --emerald-green: #10b981;
            --amber-yellow: #f59e0b;
            --crimson-red: #ef4444;
            --purple-accent: #8b5cf6;
            --slate-dark: #0f172a;
        }

        body {
            background-color: #f8fafc;
            font-family: 'Outfit', sans-serif;
            padding-bottom: 90px;
            color: #1e293b;
            -webkit-tap-highlight-color: transparent;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        /* Font Awesome Glyph Protection */
        .fa, .fas, .far, .fal, .fad, .fab, [class^="fa-"], [class*=" fa-"] {
            font-family: "Font Awesome 6 Free", "Font Awesome 5 Free", "FontAwesome" !important;
            font-style: normal;
        }

        /* Mobile Header Command Center */
        .emc-mobile-header {
            background: linear-gradient(135deg, #004d4f 0%, #002b2c 100%);
            color: #ffffff;
            padding: 18px 16px 20px 16px;
            border-bottom-left-radius: 24px;
            border-bottom-right-radius: 24px;
            box-shadow: 0 10px 25px -5px rgba(0, 77, 79, 0.35);
        }

        .emc-m-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            padding: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
            margin-bottom: 14px;
            transition: all 0.2s ease;
        }

        /* Quick Action Grid Mobile 3x2 */
        .quick-grid-emc-mobile {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 14px;
        }
        .quick-emc-m-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 12px 6px;
            text-align: center;
            text-decoration: none;
            color: #1e293b;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            transition: transform 0.15s ease;
        }
        .quick-emc-m-item:active { transform: scale(0.94); }
        .quick-emc-m-item i { font-size: 20px; }
        .quick-emc-m-item span { font-size: 10px; font-weight: 700; }

        /* Responsive 2-Column KPI Card Grid */
        .kpi-mobile-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 14px;
        }
        .kpi-m-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 12px 14px;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .kpi-m-card:active {
            transform: scale(0.96);
        }
        .kpi-m-card-icon {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .kpi-m-card-title {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 2px;
        }
        .kpi-m-card-value {
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.1;
            color: #0f172a;
        }
        .kpi-m-card-sub {
            font-size: 9px;
            color: #94a3b8;
            margin-top: 4px;
        }

        /* Fixed Bottom Navigation */
        .bottom-nav-emc {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: 68px;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-around;
            z-index: 1030;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.06);
        }
        .bottom-nav-emc-link {
            text-align: center;
            text-decoration: none;
            color: #64748b;
            font-size: 10px;
            font-weight: 600;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            transition: color 0.15s ease;
        }
        .bottom-nav-emc-link.active { color: #00B5B8; font-weight: 800; }
        .bottom-nav-emc-link i { font-size: 18px; margin-bottom: 2px; }

        .bottom-nav-emc-menu-btn {
            position: relative;
            transform: translateY(-8px);
            background: linear-gradient(135deg, #00B5B8 0%, #007275 100%);
            color: #ffffff !important;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 3px solid #ffffff;
            box-shadow: 0 4px 12px rgba(0, 181, 184, 0.4);
            transition: transform 0.15s ease;
        }
        .bottom-nav-emc-menu-btn:active {
            transform: translateY(-8px) scale(0.92);
        }
        .bottom-nav-emc-menu-btn i {
            font-size: 18px;
            margin-bottom: 0;
        }

        /* Drawer / All Menus Sheet Styling */
        .m-drawer-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            text-decoration: none;
            color: #1e293b;
            transition: all 0.15s ease;
        }
        .m-drawer-item:hover, .m-drawer-item.active {
            background: #e0f2fe;
            border-color: #0284c7;
            color: #0369a1;
        }
        .m-drawer-item i {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            font-size: 14px;
        }

        /* ================================================================
           DARK MODE OVERRIDES (HIGH CONTRAST & COMPREHENSIVE)
           ================================================================ */
        body.dark-mode {
            background-color: #0b1329 !important;
            color: #f1f5f9 !important;
        }
        body.dark-mode .emc-mobile-header {
            background: linear-gradient(135deg, #062324 0%, #031314 100%) !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5) !important;
        }
        body.dark-mode .emc-m-card {
            background-color: #131f37 !important;
            border-color: #243556 !important;
            color: #f1f5f9 !important;
        }
        body.dark-mode .quick-emc-m-item {
            background-color: #131f37 !important;
            border-color: #243556 !important;
            color: #f1f5f9 !important;
        }
        body.dark-mode .kpi-m-card {
            background-color: #131f37 !important;
            border-color: #243556 !important;
        }
        body.dark-mode .kpi-m-card-value {
            color: #ffffff !important;
        }
        body.dark-mode .kpi-m-card-title {
            color: #94a3b8 !important;
        }
        body.dark-mode .kpi-m-card-sub {
            color: #64748b !important;
        }
        body.dark-mode .bottom-nav-emc {
            background-color: #0f172a !important;
            border-top-color: #243556 !important;
        }
        body.dark-mode .bottom-nav-emc-link {
            color: #94a3b8 !important;
        }
        body.dark-mode .bottom-nav-emc-link.active {
            color: #38bdf8 !important;
        }
        body.dark-mode .bottom-nav-emc-menu-btn {
            border-color: #0f172a !important;
        }
        body.dark-mode .text-dark,
        body.dark-mode h6.text-dark {
            color: #f8fafc !important;
        }
        body.dark-mode .text-muted {
            color: #94a3b8 !important;
        }
        body.dark-mode .bg-light {
            background-color: #131f37 !important;
        }
        body.dark-mode .table {
            color: #f1f5f9 !important;
        }
        body.dark-mode .table thead th {
            background-color: #1a2942 !important;
            color: #38bdf8 !important;
            border-color: #334155 !important;
        }
        body.dark-mode .table tbody td {
            background-color: #131f37 !important;
            color: #f1f5f9 !important;
            border-color: #243556 !important;
        }
        body.dark-mode .offcanvas {
            background-color: #0f172a !important;
            color: #f1f5f9 !important;
        }
        body.dark-mode .offcanvas-header {
            border-bottom-color: #243556 !important;
        }
        body.dark-mode .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        body.dark-mode .m-drawer-item {
            background: #131f37 !important;
            border-color: #243556 !important;
            color: #f1f5f9 !important;
        }
        body.dark-mode .m-drawer-item i {
            background: #1a2942 !important;
            color: #38bdf8 !important;
        }
        body.dark-mode .m-drawer-item:hover,
        body.dark-mode .m-drawer-item.active {
            background: #1e2e4f !important;
            border-color: #0284c7 !important;
            color: #ffffff !important;
        }
        body.dark-mode input.form-control {
            background-color: #1a2942 !important;
            border-color: #334155 !important;
            color: #ffffff !important;
        }
        body.dark-mode input.form-control::placeholder {
            color: #94a3b8 !important;
        }
    </style>
</head>
<body>

    <!-- 1. MOBILE HEADER -->
    <div class="emc-mobile-header">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center gap-2">
                <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="Logo PLN" style="width: 40px; height: 40px; object-fit: contain;" class="bg-white p-1 rounded-circle border border-warning shadow-sm">
                <div>
                    <?php
                        $hour = (int)date('H');
                        if ($hour >= 0 && $hour < 11) $greeting = 'Selamat Pagi';
                        elseif ($hour >= 11 && $hour < 15) $greeting = 'Selamat Siang';
                        elseif ($hour >= 15 && $hour < 18) $greeting = 'Selamat Sore';
                        else $greeting = 'Selamat Malam';
                    ?>
                    <small class="text-white-50 d-block" style="font-size: 10px;"><?= $greeting ?></small>
                    <h6 class="fw-bold mb-0 text-white" style="font-size: 13px;"><?= esc($userName ?: 'Petugas PLN') ?></h6>
                </div>
            </div>

            <!-- Header Quick Actions (Theme, Desktop Switch, All Menu) -->
            <div class="d-flex align-items-center" style="gap: 6px;">
                <button type="button" class="btn btn-sm btn-outline-light rounded-circle" id="m-theme-toggle-btn" onclick="toggleDarkModeMobile()" title="Ganti Tema (Terang / Gelap)" style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                    <i id="m-theme-icon" class="fas fa-moon"></i>
                </button>
                <a href="<?= site_url('dashboard/toggle-view?t=' . time()) ?>" class="btn btn-sm btn-outline-light rounded-circle" title="Ganti ke Versi Desktop" style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                    <i class="fas fa-desktop"></i>
                </a>
                <button type="button" class="btn btn-sm btn-light text-dark font-weight-bold rounded-pill px-2 py-1 shadow-xs" data-bs-toggle="offcanvas" data-bs-target="#mobileAllMenuDrawer" title="Buka Semua Menu (35+ Modul)" style="font-size: 11px;">
                    <i class="fas fa-th-large text-primary me-1"></i> Menu
                </button>
                <a href="<?= site_url('notifications') ?>" class="text-white position-relative fs-6 ms-1" title="Notifikasi">
                    <i class="fas fa-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
                </a>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between pt-2 border-top border-white border-opacity-10" style="font-size: 11px;">
            <span class="badge bg-white bg-opacity-25 text-white border border-white border-opacity-25 rounded-pill">
                <i class="fas fa-user-shield me-1"></i> <?= esc(get_role_label($userRole ?? 'inspeksi')) ?>
            </span>
            <span class="text-white-50"><i class="fas fa-clock me-1"></i> <?= date('d M Y') ?> &middot; <strong id="m-emc-clock"><?= date('H:i') ?></strong></span>
            <span class="badge bg-success bg-opacity-75 text-white rounded-pill"><i class="fas fa-wifi me-1"></i> Live</span>
        </div>
    </div>

    <div class="px-3 mt-3">

        <!-- 2. PERMANENT MOTIVATION BANNER -->
        <div class="emc-m-card border-start border-4 border-warning bg-light">
            <small class="text-muted fw-bold d-block mb-1" style="font-size: 10px;"><i class="fas fa-quote-left me-1 text-warning"></i> MOTIVASI HARIAN</small>
            <p class="fst-italic text-dark small mb-0" id="m-permanent-motivation">
                "<?= esc(get_daily_announcement()) ?>"
            </p>
        </div>

        <!-- 3. TARGET HARIAN PROGRESS BAR -->
        <?php
            $mDailyTarget = max(1, (int)($stats['target_harian'] ?? 25));
            $mDailyDone = (int)($stats['hari_ini'] ?? $stats['selesai_hari_ini'] ?? ($stats['selesai'] ?? 0));
            $mDailyPct = min(100, (int)round(($mDailyDone / $mDailyTarget) * 100));
        ?>
        <div class="emc-m-card">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold text-dark" style="font-size: 12px;"><i class="fas fa-bullseye text-warning me-1"></i> Target Harian Pekerjaan</span>
                <span class="badge bg-success" id="m-kpi-target-harian-pct"><?= $mDailyPct ?>%</span>
            </div>
            <p class="text-muted mb-2" style="font-size: 11px;">Realisasi: <?= number_format($mDailyDone) ?> / <?= number_format($mDailyTarget) ?> Pekerjaan Selesai</p>
            <div class="progress" style="height: 8px;"><div class="progress-bar bg-success" style="width: <?= $mDailyPct ?>%;" id="m-kpi-target-harian-bar"></div></div>
        </div>

        <!-- 4. QUICK ACTION GRID 3x2 -->
        <div class="quick-grid-emc-mobile">
            <?php if ($canInput ?? true): ?>
            <a href="<?= site_url('temuan/create') ?>" class="quick-emc-m-item">
                <i class="fas fa-plus-circle text-success"></i><span>Input</span>
            </a>
            <?php endif; ?>
            <a href="<?= site_url('temuan') ?>" class="quick-emc-m-item">
                <i class="fas fa-tasks text-primary"></i><span>Temuan</span>
            </a>
            <a href="<?= site_url('temuan/update-pekerjaan') ?>" class="quick-emc-m-item">
                <i class="fas fa-wrench text-warning"></i><span>Update</span>
            </a>
            <a href="javascript:void(0)" onclick="triggerQrScanModal()" class="quick-emc-m-item">
                <i class="fas fa-qrcode text-purple"></i><span>QR Scan</span>
            </a>
            <a href="<?= site_url('ai-copilot') ?>" class="quick-emc-m-item">
                <i class="fas fa-robot text-info"></i><span>Voice AI</span>
            </a>
            <a href="<?= site_url('temuan/terdekat') ?>" class="quick-emc-m-item">
                <i class="fas fa-crosshairs text-danger"></i><span>Terdekat</span>
            </a>
            <a href="<?= site_url('captive-non-penugasan') ?>" class="quick-emc-m-item"><i class="fas fa-plug text-warning"></i><span>Captive</span></a>
        </div>

        <div class="emc-m-card"><div class="fw-bold mb-2">Panjang Konduktor per Penyulang</div><div class="small text-muted mb-2">AAAC / AAACS / MVTIC / XLPE — km</div>
            <?php foreach (($networkData['feeders'] ?? []) as $net): ?><div class="d-flex justify-content-between gap-2 border-bottom py-2"><span><?= esc($net['nama_ulp']) ?> · <?= esc($net['nama']) ?></span><span class="text-nowrap"><?= number_format((float)$net['aaac_kms'],1) ?> / <?= number_format((float)$net['aaacs_kms'],1) ?> / <?= number_format((float)$net['mvtic_kms'],1) ?> / <?= number_format((float)$net['xlpe_kms'],1) ?></span></div><?php endforeach ?>
        </div>

        <!-- 5. RESPONSIVE 2-COLUMN KPI GRID (Replaces single-column stack) -->
        <div class="kpi-mobile-grid">
            <!-- 1. Total Temuan -->
            <a href="<?= site_url('temuan') ?>" class="kpi-m-card border-primary-subtle">
                <div>
                    <div class="kpi-m-card-icon bg-primary-subtle text-primary">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="kpi-m-card-title">Total Temuan</div>
                    <div class="kpi-m-card-value text-primary"><?= number_format($stats['total'] ?? 0) ?></div>
                </div>
                <div class="kpi-m-card-sub">Semua status temuan &rarr;</div>
            </a>

            <!-- 2. Emergency -->
            <a href="<?= site_url('temuan?prioritas=EMERGENCY') ?>" class="kpi-m-card border-danger-subtle">
                <div>
                    <div class="kpi-m-card-icon bg-danger-subtle text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="kpi-m-card-title text-danger">Emergency</div>
                    <div class="kpi-m-card-value text-danger"><?= number_format($stats['emergency'] ?? 0) ?></div>
                </div>
                <div class="kpi-m-card-sub text-danger font-weight-bold">Perlu aksi segera &rarr;</div>
            </a>

            <!-- 3. Belum Selesai -->
            <a href="<?= site_url('temuan?status=BELUM') ?>" class="kpi-m-card border-warning-subtle">
                <div>
                    <div class="kpi-m-card-icon bg-warning-subtle text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="kpi-m-card-title">Belum Selesai</div>
                    <div class="kpi-m-card-value text-warning"><?= number_format($stats['belum'] ?? 0) ?></div>
                </div>
                <div class="kpi-m-card-sub">Antrean eksekusi &rarr;</div>
            </a>

            <!-- 4. Sudah Selesai -->
            <a href="<?= site_url('temuan?status=SELESAI') ?>" class="kpi-m-card border-success-subtle">
                <div>
                    <div class="kpi-m-card-icon bg-success-subtle text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="kpi-m-card-title">Sudah Selesai</div>
                    <div class="kpi-m-card-value text-success"><?= number_format($stats['selesai'] ?? 0) ?></div>
                </div>
                <div class="kpi-m-card-sub">Pekerjaan closed &rarr;</div>
            </a>

            <!-- 5. Total Aset Unik -->
            <a href="<?= site_url('master-assets') ?>" class="kpi-m-card">
                <div>
                    <div class="kpi-m-card-icon bg-secondary-subtle text-secondary">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="kpi-m-card-title">Total Aset Unik</div>
                    <div class="kpi-m-card-value"><?= number_format($assetStats['total'] ?? 0) ?></div>
                </div>
                <div class="kpi-m-card-sub">Gardu, tiang, GI &rarr;</div>
            </a>

            <!-- 6. Health Index Sistem -->
            <a href="<?= site_url('asset-health') ?>" class="kpi-m-card border-info-subtle">
                <div>
                    <div class="kpi-m-card-icon bg-info-subtle text-info">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div class="kpi-m-card-title">Health Index</div>
                    <div class="kpi-m-card-value text-info"><?= number_format($assetHealthData['summary']['systemHealthIndex'] ?? 87.4, 1) ?>%</div>
                </div>
                <div class="kpi-m-card-sub">Skor kesehatan sistem &rarr;</div>
            </a>
        </div>

        <!-- 6. MINI MAP MONITORING -->
        <div class="emc-m-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-dark mb-0" style="font-size: 12px;"><i class="fas fa-map-marked-alt text-success me-1"></i> Mini GIS Monitoring Map</h6>
                <a href="<?= site_url('gis') ?>" class="small text-primary font-weight-bold text-decoration-none" style="font-size: 11px;">Full Map &rarr;</a>
            </div>
            <div id="emc-mini-mobile-map" style="height: 160px; border-radius: 12px;"></div>
        </div>

        <!-- Phase 2: Ranking 5 Penyulang by Emergency (Mobile) -->
        <div class="emc-m-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-dark mb-0" style="font-size: 12px;"><i class="fas fa-trophy text-warning me-1"></i> Ranking Emergency - Top 5 Penyulang</h6>
            </div>
            <?php if (!empty($penyulangRanking)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" style="font-size: 11px;">
                    <thead>
                        <tr class="text-uppercase text-muted small" style="font-size: 9px; letter-spacing: 0.5px;">
                            <th>Penyulang</th>
                            <th>ULP</th>
                            <th class="text-center">Emg</th>
                            <th class="text-center">High</th>
                            <th class="text-center">Med</th>
                            <th class="text-center">Belum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($penyulangRanking as $idx => $row): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-light text-dark border rounded-circle fw-bold" style="width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 10px;"><?= $idx + 1 ?></span>
                                    <span class="fw-medium text-dark small"><?= esc($row['nama_penyulang'] ?? 'N/A') ?></span>
                                </div>
                            </td>
                            <td class="text-muted small"><?= esc($row['nama_ulp'] ?? 'N/A') ?></td>
                            <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 fw-bold"><?= number_format($row['emergency']) ?></span></td>
                            <td class="text-center"><span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 fw-bold"><?= number_format($row['high']) ?></span></td>
                            <td class="text-center"><span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 fw-bold"><?= number_format($row['medium']) ?></span></td>
                            <td class="text-center"><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 fw-bold"><?= number_format($row['belum_selesai']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-3">
                <i class="fas fa-info-circle text-muted fs-2 mb-1"></i>
                <p class="text-muted mb-0 small">Belum ada data Emergency untuk ranking</p>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- 7. FIXED BOTTOM NAVIGATION BAR WITH ELEVATED ALL-MENU BUTTON -->
    <div class="bottom-nav-emc">
        <a href="<?= site_url('dashboard') ?>" class="bottom-nav-emc-link active">
            <i class="fas fa-home"></i><span>Home</span>
        </a>
        <a href="<?= site_url('temuan') ?>" class="bottom-nav-emc-link">
            <i class="fas fa-tasks"></i><span>Temuan</span>
        </a>
        <button type="button" class="btn p-0 border-0 bottom-nav-emc-menu-btn" data-bs-toggle="offcanvas" data-bs-target="#mobileAllMenuDrawer" title="Buka Semua Menu (35+ Modul)">
            <i class="fas fa-th-large"></i>
        </button>
        <a href="<?= site_url('gis') ?>" class="bottom-nav-emc-link">
            <i class="fas fa-map-marked-alt"></i><span>Map</span>
        </a>
        <a href="<?= site_url('change-password') ?>" class="bottom-nav-emc-link">
            <i class="fas fa-user-circle"></i><span>Profil</span>
        </a>
    </div>

    <!-- 8. OFFCANVAS DRAWER: ALL 35+ AUTHORIZED OPERATIONAL MENUS -->
    <div class="offcanvas offcanvas-bottom" tabindex="-1" id="mobileAllMenuDrawer" aria-labelledby="mobileAllMenuDrawerLabel" style="height: 85vh; border-top-left-radius: 24px; border-top-right-radius: 24px; z-index: 1060;">
        <div class="offcanvas-header pt-3 pb-2 px-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary rounded-circle p-2"><i class="fas fa-th-large text-white"></i></span>
                <div>
                    <h6 class="offcanvas-title fw-bold mb-0" id="mobileAllMenuDrawerLabel" style="font-size: 15px;">Semua Menu SIDAK TEJO</h6>
                    <small class="text-muted" style="font-size: 11px;"><?= count($authorizedMenuItems) ?> Modul operasional tersedia</small>
                </div>
            </div>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="p-3 border-bottom">
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fas fa-search"></i></span>
                <input type="text" id="mobile-menu-search-input" class="form-control border-start-0" placeholder="Cari menu, modul, laporan..." autocomplete="off">
            </div>
        </div>
        <div class="offcanvas-body p-3" style="overflow-y: auto;">
            <div id="mobile-menu-container">
                <?php foreach ($categoriesDef as $catKey => $cat): ?>
                    <?php
                    $catItems = array_values(array_filter($authorizedMenuItems, fn($m) => $m['category'] === $catKey));
                    if (empty($catItems)) continue;
                    ?>
                    <div class="mb-3 mobile-menu-category-section" data-cat="<?= $catKey ?>">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-bold text-uppercase small" style="font-size: 11px; letter-spacing: 0.5px;">
                                <i class="<?= esc($cat['icon']) ?> me-1 text-primary"></i> <?= esc($cat['name']) ?>
                            </span>
                            <span class="badge bg-secondary-subtle text-secondary rounded-pill" style="font-size: 10px;">
                                <?= count($catItems) ?>
                            </span>
                        </div>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($catItems as $item): ?>
                                <a href="<?= esc($item['href']) ?>" class="m-drawer-item <?= $item['active'] ? 'active' : '' ?>" data-menu-title="<?= strtolower(esc($item['name'] . ' ' . $item['desc'])) ?>">
                                    <i class="<?= esc($item['icon']) ?>"></i>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="fw-bold small text-truncate" style="font-size: 12px;"><?= esc($item['name']) ?></div>
                                        <div class="text-muted text-truncate" style="font-size: 10px;"><?= esc($item['desc']) ?></div>
                                    </div>
                                    <?php if ($item['active']): ?>
                                        <span class="badge bg-primary text-white rounded-pill px-2 py-0" style="font-size: 9px;">Aktif</span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="mobile-menu-empty" class="text-center py-4 d-none">
                <i class="fas fa-search text-muted fs-3 mb-2"></i>
                <p class="text-muted small mb-0">Menu tidak ditemukan</p>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS & Leaflet -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        // Theme Toggle Function for Mobile
        function toggleDarkModeMobile() {
            var body = document.body;
            var docEl = document.documentElement;
            var icon = document.getElementById('m-theme-icon');
            var isDark = body.classList.toggle('dark-mode');
            docEl.classList.toggle('dark-mode', isDark);

            if (isDark) {
                localStorage.setItem('sidak_theme', 'dark');
                if (icon) icon.className = 'fas fa-sun text-warning';
            } else {
                localStorage.setItem('sidak_theme', 'light');
                if (icon) icon.className = 'fas fa-moon';
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            // Leaflet Mini Map Initialization
            var mapEl = document.getElementById('emc-mini-mobile-map');
            if (mapEl) {
                var mmap = L.map('emc-mini-mobile-map', { zoomControl: false }).setView([-7.4478, 112.7183], 11);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mmap);

                var pins = <?= json_encode($mapPins ?? []) ?>;
                pins.forEach(function(p) {
                    var lat = parseFloat(p.latitude);
                    var lng = parseFloat(p.longitude);
                    if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                        var color = '#10b981';
                        if (p.prioritas === 'EMERGENCY') color = '#ef4444';
                        else if (p.prioritas === 'HIGH') color = '#f59e0b';

                        var circle = L.circleMarker([lat, lng], {
                            radius: 7, fillColor: color, color: '#fff', weight: 2, fillOpacity: 0.9
                        }).addTo(mmap);

                        circle.on('click', function() {
                            window.location.href = "<?= site_url('temuan/detail/') ?>" + p.id;
                        });
                    }
                });
            }

            // Realtime clock display
            function updateMobileClock() {
                var d = new Date();
                var h = String(d.getHours()).padStart(2, '0');
                var m = String(d.getMinutes()).padStart(2, '0');
                var clockEl = document.getElementById('m-emc-clock');
                if (clockEl) clockEl.textContent = h + ':' + m;
            }
            setInterval(updateMobileClock, 30000);

            // Client-side Live Search Filter for All Menus Drawer
            var searchInput = document.getElementById('mobile-menu-search-input');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    var query = this.value.toLowerCase().trim();
                    var items = document.querySelectorAll('.m-drawer-item');
                    var categories = document.querySelectorAll('.mobile-menu-category-section');
                    var visibleCount = 0;

                    items.forEach(function(item) {
                        var title = item.getAttribute('data-menu-title') || '';
                        if (!query || title.indexOf(query) !== -1) {
                            item.classList.remove('d-none');
                            visibleCount++;
                        } else {
                            item.classList.add('d-none');
                        }
                    });

                    categories.forEach(function(cat) {
                        var catVisibleItems = cat.querySelectorAll('.m-drawer-item:not(.d-none)');
                        if (catVisibleItems.length > 0) {
                            cat.classList.remove('d-none');
                        } else {
                            cat.classList.add('d-none');
                        }
                    });

                    var emptyEl = document.getElementById('mobile-menu-empty');
                    if (emptyEl) {
                        if (visibleCount === 0) {
                            emptyEl.classList.remove('d-none');
                        } else {
                            emptyEl.classList.add('d-none');
                        }
                    }
                });
            }
        });

        // Trigger QR scan modal
        function triggerQrScanModal() {
            window.location.href = "<?= site_url('smart-search') ?>";
        }
    </script>
</body>
</html>
