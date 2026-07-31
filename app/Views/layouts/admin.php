<?php
$session = session();
$viewMode = $session->get('view_mode') ?: ($_COOKIE['view_mode'] ?? null);
$agent = \Config\Services::request()->getUserAgent();
$isMobileMode = ($viewMode === 'mobile' || ($agent->isMobile() && $viewMode !== 'desktop'));

// Gabung & Minifikasi CSS/JS via AssetMinifier
$cssFiles = [
    'plugins/tabler.min.css',
    'plugins/fontawesome-free/css/all.min.css',
    'plugins/animate.min.css',
    'plugins/dataTables.bootstrap5.min.css',
    'plugins/leaflet.css',
    'plugins/select2/css/select2.min.css',
    'plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
    'dist/css/custom_modern.css',
];
$combinedCss = \App\Libraries\AssetMinifier::css($cssFiles);

$jsFiles = [
    'plugins/jquery/jquery.min.js',
    'plugins/tabler.min.js',
    'plugins/datatables/jquery.dataTables.min.js',
    'plugins/dataTables.bootstrap5.min.js',
    'plugins/alert.js',
    'plugins/chart.js',
    'plugins/leaflet.js',
    'plugins/select2/js/select2.full.min.js',
];
$combinedJs = \App\Libraries\AssetMinifier::js($jsFiles);
?>
<!-- ROUTE_TRACE_2026: <?= esc($_SERVER['REQUEST_URI'] ?? 'N/A') ?> -->
<!-- CONTROLLER_TRACE_2026: <?= esc($trace['CONTROLLER_TRACE_2026'] ?? 'App\Controllers\Temuan::detail') ?> -->
<!-- VIEW_TRACE_2026: app/Views/temuan/detail.php (<?= esc(realpath(APPPATH . 'Views/temuan/detail.php') ?: 'N/A') ?>) -->
<!-- BUILD_TRACE_2026: BUILD_5E22D5D_ENTERPRISE_PLN_MOBILE -->
<!-- HTML_TRACE_2026: RUNTIME_HTML_TRACE_ACTIVE -->
<!-- DOC_ROOT_TRACE: <?= esc($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') ?> -->
<!-- FCPATH_TRACE: <?= esc(defined('FCPATH') ? FCPATH : 'N/A') ?> -->
<!-- APPPATH_TRACE: <?= esc(defined('APPPATH') ? APPPATH : 'N/A') ?> -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SIDAK TEJO - Sistem Inspeksi Jaringan Operasi Sidoarjo - Tema Tabler Modern">
    <title>SIDAK TEJO | <?= $this->renderSection('title') ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon_sidak.png') ?>">
    <link rel="shortcut icon" href="<?= base_url('assets/img/favicon_sidak.png') ?>">

    <!-- Local Google Fonts: Outfit & Inter -->
    <link rel="stylesheet" href="<?= base_url('assets/fonts/fonts.css') ?>">

    <!-- Local CSS Files (Offline-Safe & Correct Pathing) -->
    <?php foreach ($cssFiles as $file): ?>
        <link rel="stylesheet" href="<?= base_url($file) ?>">
    <?php endforeach; ?>

    <style>
        :root {
            --tblr-font-sans-serif: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --tblr-primary: #00B5B8; /* Warna Utama */
            --tblr-primary-rgb: 0, 181, 184;
            --tblr-success: #22C55E; /* Status Selesai / Success */
            --tblr-success-rgb: 34, 197, 94;
            --tblr-warning: #F59E0B; /* Status Proses / Warning */
            --tblr-warning-rgb: 245, 158, 11;
            --tblr-danger: #EF4444; /* Status Emergency / Danger */
            --tblr-danger-rgb: 239, 68, 68;
            --tblr-bg-surface: #ffffff; /* Surface/Card Background (White) */
            --tblr-bg-page: #f4f6fa; /* Page Background (Light Gray) */
            --color-accent: #FF6B35; /* Aksen */
            --color-tosca-dark: #004d4f; /* Sidebar & Header Biru Tosca Gelap */
        }

        body {
            font-family: var(--tblr-font-sans-serif);
            background-color: var(--tblr-bg-page);
            color: #333333; /* Dark gray text */
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3, h4, h5, h6, .navbar-brand, .card-title {
            font-family: 'Outfit', sans-serif !important;
            font-weight: 600;
        }

        /* Sidebar & Menu Premium Styling (Biru Tosca Gelap) */
        .navbar-vertical {
            background-color: var(--color-tosca-dark) !important;
            border-right: 1px solid rgba(0, 0, 0, 0.05) !important;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.05);
        }
        
        .navbar-vertical .navbar-brand {
            padding: 1.25rem 1rem !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
        }

        .navbar-vertical .nav-item .nav-link {
            color: rgba(255, 255, 255, 0.85) !important; /* White contrast text */
            font-weight: 600;
            padding: 0.6rem 1rem;
            border-radius: 6px;
            margin: 0.15rem 0.75rem;
            transition: all 0.2s ease;
        }

        .navbar-vertical .nav-item .nav-link:hover {
            color: #ffffff !important;
            background-color: rgba(255, 255, 255, 0.1) !important;
        }

        .navbar-vertical .nav-item.active > .nav-link,
        .navbar-vertical .nav-link.active {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.15) !important;
            font-weight: 700;
            border-left: 4px solid var(--color-accent) !important;
        }

        .navbar-vertical .nav-link i.nav-icon {
            font-size: 1.05rem;
            width: 24px;
            text-align: center;
            margin-right: 8px;
            transition: transform 0.2s ease;
            color: rgba(255, 255, 255, 0.7) !important;
        }

        .navbar-vertical .nav-item:hover i.nav-icon,
        .navbar-vertical .nav-item.active i.nav-icon,
        .navbar-vertical .nav-link.active i.nav-icon {
            color: #ffffff !important;
            transform: scale(1.15);
        }

        /* Styling dropdowns di sidebar Tabler */
        .navbar-vertical .dropdown-menu {
            background-color: #003637 !important; /* Darker tosca for submenus */
            border: none !important;
            padding: 0.35rem 0;
            margin: 0 0.75rem !important;
            border-radius: 6px;
        }

        .navbar-vertical .dropdown-item {
            color: rgba(255, 255, 255, 0.85) !important;
            padding: 0.5rem 1rem 0.5rem 2.5rem !important;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border-radius: 4px;
        }

        .navbar-vertical .dropdown-item:hover,
        .navbar-vertical .dropdown-item.active {
            color: #ffffff !important;
            background-color: rgba(255, 255, 255, 0.1) !important;
        }

        /* Green Energy Accent Touch */
        .green-energy-accent {
            color: #FF6B35 !important; /* Accent coral orange */
            text-shadow: 0 0 10px rgba(255, 107, 53, 0.3);
        }

        /* Custom Button Colors */
        .btn-primary {
            background-color: #009A9D !important;
            border-color: #009A9D !important;
            color: #ffffff !important;
        }

        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: #008183 !important;
            border-color: #008183 !important;
        }

        .btn-success {
            background-color: #22C55E !important;
            border-color: #22C55E !important;
            color: #ffffff !important;
        }

        .btn-success:hover {
            background-color: #1aa14c !important;
            border-color: #1aa14c !important;
        }

        .btn-warning {
            background-color: #F59E0B !important;
            border-color: #F59E0B !important;
            color: #ffffff !important;
        }

        .btn-warning:hover {
            background-color: #d97706 !important;
            border-color: #d97706 !important;
        }

        .btn-danger {
            background-color: #EF4444 !important;
            border-color: #EF4444 !important;
            color: #ffffff !important;
        }

        .btn-danger:hover {
            background-color: #dc2626 !important;
            border-color: #dc2626 !important;
        }

        /* Status colors */
        .badge-success, .bg-success, .badge.bg-success {
            background-color: #22C55E !important;
            color: #ffffff !important;
        }

        .badge-warning, .bg-warning, .badge.bg-warning {
            background-color: #F59E0B !important;
            color: #ffffff !important;
        }

        .badge-danger, .bg-danger, .badge.bg-danger {
            background-color: #EF4444 !important;
            color: #ffffff !important;
        }

        /* Cards Modern (Light Mode Reset) */
        .card {
            border: 1px solid rgba(0, 0, 0, 0.06) !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02) !important;
            background-color: #ffffff !important;
            color: #333333 !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.04) !important;
        }

        .card-header {
            background-color: transparent !important;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06) !important;
            padding: 1rem 1.25rem !important;
            color: #333333 !important;
        }

        .card-title {
            color: #333333 !important;
        }

        /* Top Header Styling (Biru Tosca Gelap Desktop) */
        .navbar-top-wrapper {
            background-color: var(--color-tosca-dark) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
        }
        .navbar-top-wrapper .text-dark,
        .navbar-top-wrapper .navbar-brand,
        .navbar-top-wrapper .nav-link,
        .navbar-top-wrapper .nav-item {
            color: #ffffff !important;
        }
        .navbar-top-wrapper .nav-link:hover {
            color: var(--color-accent) !important;
        }

        /* Mobile Top Header (Biru Tosca Gelap Mobile) */
        .mobile-app-header {
            background: var(--color-tosca-dark) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
        }

        .mobile-bottom-nav {
            background-color: #ffffff !important;
            border-top: 1px solid rgba(0, 0, 0, 0.08) !important;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05) !important;
        }
        body.is-mobile-app .navbar-vertical {
            display: none !important;
        }
        body.is-mobile-app .navbar-top-wrapper {
            display: none !important;
        }
        body.is-mobile-app .page-wrapper {
            margin-left: 0 !important;
            padding-top: 65px !important;
            padding-bottom: 75px !important;
        }
        body.is-mobile-app .mobile-app-header {
            display: flex !important;
        }
        body.is-mobile-app .mobile-bottom-nav {
            display: flex !important;
        }

        .mobile-app-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: var(--color-tosca-dark) !important;
            color: #ffffff;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            z-index: 1040;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }

        .mobile-back-btn, .mobile-desktop-btn {
            color: #ffffff;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            text-decoration: none;
            border: none;
        }

        .mobile-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 0.2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 60%;
        }

        /* Mobile Smooth Horizontal Table Touch Scroll Fix */
        @media (max-width: 768px) {
            .table-responsive {
                display: block !important;
                width: 100% !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                touch-action: pan-x pan-y !important;
            }
            .table-responsive > table,
            .table-responsive > .table,
            #table-temuan {
                min-width: 750px !important;
            }
        }

        @media (max-width: 576px) {
            .stats-card {
                padding: 12px 10px !important;
                min-height: 95px !important;
                margin-bottom: 12px !important;
            }
            .stats-card h3 {
                font-size: 1.35rem !important;
            }
            .stats-card p {
                font-size: 0.65rem !important;
                letter-spacing: 0.2px !important;
                line-height: 1.2 !important;
                word-break: break-word !important;
            }
            .stats-card .icon {
                font-size: 26px !important;
                top: 8px !important;
                right: 8px !important;
            }
        }

        /* Mobile Bottom Navigation */
        .mobile-bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: #ffffff;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
            z-index: 1040;
            justify-content: space-around;
            align-items: center;
        }

        .mob-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.65rem;
            font-weight: 600;
            height: 100%;
            transition: all 0.2s ease;
        }

        .mob-nav-item i {
            font-size: 1.25rem;
            margin-bottom: 2px;
        }

        .mob-nav-item.active, .mob-nav-item:active {
            color: #00B5B8;
        }

        .mob-nav-center {
            width: 48px;
            height: 48px;
            background: #FF6B35;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(255, 107, 53, 0.35);
            font-size: 1.3rem;
            transform: translateY(-12px);
            border: 3px solid #ffffff;
            transition: transform 0.2s;
        }

        .mob-nav-center:active {
            transform: translateY(-12px) scale(0.95);
        }

        /* Floating Mic Voice Assistant */
        #btn-global-mic.listening {
            background: #dc3545 !important;
            animation: global-mic-pulse 1.4s infinite !important;
        }
        @keyframes global-mic-pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { transform: scale(1.15); box-shadow: 0 0 0 15px rgba(220, 53, 69, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }

        /* Select2 Tabler Theme Fixes */
        .select2-container--bootstrap4 .select2-selection {
            border: 1px solid rgba(0, 0, 0, 0.16) !important;
            border-radius: 6px !important;
            height: calc(1.5em + 0.75rem + 2px) !important;
        }
        .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
            line-height: calc(1.5em + 0.75rem) !important;
            color: #333 !important;
            padding-left: 0.75rem !important;
        }

        /* Custom Page Header styling */
        .page-header {
            margin-bottom: 1.25rem !important;
        }

        .breadcrumb {
            background: transparent !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .breadcrumb-item a {
            color: #6c757d;
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            color: #005eb8;
        }

        /* Table responsive fixes */
        .table-responsive {
            border: none !important;
        }

        /* Custom stats card inside dashboard compatibility */
        .stats-card {
            border-radius: 10px;
            color: white;
            padding: 16px;
            margin-bottom: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 105px;
            border: none !important;
        }
        .stats-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.1;
        }
        .stats-card p {
            font-size: 0.78rem;
            font-weight: 600;
            margin: 4px 0 0 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.9;
        }
        .stats-card .icon {
            position: absolute;
            top: 5px;
            right: 10px;
            font-size: 40px;
            opacity: 0.18;
        }
        .bg-gradient-blue {
            background: linear-gradient(135deg, #007bff 0%, #00c6fb 100%) !important;
        }
        .bg-gradient-red {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%) !important;
        }
        .bg-gradient-green {
            background: linear-gradient(135deg, #0575e6 0%, #00f2fe 100%) !important;
        }
        .bg-gradient-orange {
            background: linear-gradient(135deg, #f12711 0%, #f5af19 100%) !important;
        }
        .bg-gradient-purple {
            background: linear-gradient(135deg, #6441a5 0%, #2a0845 100%) !important;
        }
        .bg-gradient-info-modern {
            background: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%) !important;
        }
        .bg-gradient-warning-modern {
            background: linear-gradient(135deg, #f857a6 0%, #ff5858 100%) !important;
        }
        .bg-gradient-teal-modern {
            background: linear-gradient(135deg, #4776e6 0%, #8e54e9 100%) !important;
        }
        .stats-card h3, 
        .stats-card p, 
        .stats-card span, 
        .stats-card a, 
        .stats-card i {
            color: #ffffff !important;
        }

        /* Top Header Styling */
        .navbar-top-wrapper {
            background-color: #00B5B8 !important;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08) !important;
        }
        
        .navbar-top-wrapper .btn-outline-primary {
            color: #ffffff !important;
            border-color: #ffffff !important;
            background-color: rgba(255, 255, 255, 0.15) !important;
        }
        
        .navbar-top-wrapper .btn-outline-primary:hover {
            background-color: #ffffff !important;
            color: #009A9D !important;
        }

        .navbar-top-wrapper .text-dark,
        .navbar-top-wrapper .fw-bold {
            color: #ffffff !important;
        }

        .navbar-top-wrapper .navbar-nav i {
            color: #ffffff !important;
        }

        .navbar-top-wrapper .btn-outline-danger {
            color: #ffffff !important;
            border-color: #ffffff !important;
            background-color: rgba(255, 255, 255, 0.15) !important;
        }

        .navbar-top-wrapper .btn-outline-danger:hover {
            background-color: #EF4444 !important;
            border-color: #EF4444 !important;
            color: #ffffff !important;
        }

        .navbar-top-wrapper .badge.bg-blue-lt {
            background-color: #ffffff !important;
            color: #009A9D !important;
        }

        /* Voice Assistant Mic Override */
        #btn-global-mic {
            background: #FF6B35 !important;
            box-shadow: 0 4px 10px rgba(255, 107, 53, 0.4) !important;
        }

        /* Status Text Colors */
        .text-success {
            color: #22C55E !important;
        }
        .text-warning {
            color: #F59E0B !important;
        }
        .text-danger {
            color: #EF4444 !important;
        }
    @keyframes tickerAnimation {
    0% { transform: translate3d(0, 0, 0); }
    100% { transform: translate3d(-100%, 0, 0); }
}
.ticker-wrapper:hover .ticker-content {
    animation-play-state: paused !important;
}
</style>
</head>
<body class="<?= $isMobileMode ? 'is-mobile-app' : '' ?>">

    <!-- Loading Spinner -->
    <div id="loading-spinner" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #ffffff; z-index: 9999; display: flex; align-items: center; justify-content: center;">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Memuat...</span>
        </div>
    </div>

    <!-- Mobile Top Header -->
    <div class="mobile-app-header">
        <a href="javascript:smartBack('<?= site_url('dashboard') ?>');" class="mobile-back-btn" title="Kembali">
            <i class="fas fa-chevron-left"></i>
        </a>
        <a href="<?= site_url('dashboard') ?>" class="mobile-title text-white text-decoration-none d-flex align-items-center" style="cursor: pointer;" title="Ke Dashboard">
            <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="Logo" style="height: 22px; margin-right: 6px;">
            <span>SIDAK TEJO</span>
        </a>
        <a href="<?= site_url('dashboard/toggle-view?t=' . time()) ?>" class="mobile-desktop-btn" title="Ganti ke Versi Desktop">
            <i class="fas fa-desktop"></i>
        </a>
    </div>

    <div class="page">
        <!-- Sidebar Menu (Tabler Vertical Navbar) -->
        <aside class="navbar navbar-vertical navbar-expand-lg navbar-dark d-print-none">
            <div class="container-fluid">
                <!-- Sidebar Toggle Button -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <!-- Brand Logo & Identity -->
                <h1 class="navbar-brand navbar-brand-autodark">
                    <a href="<?= site_url('dashboard') ?>" class="d-flex align-items-center text-white" style="text-decoration: none;">
                        <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="Logo" class="navbar-brand-image me-2" style="max-height: 38px;">
                        <div class="text-start">
                            <div class="font-weight-black lh-1" style="font-size: 1.0rem; letter-spacing: 1.5px; background: linear-gradient(135deg, #ffffff 0%, #93c5fd 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">SIDAK TEJO</div>
                            <div class="lh-1" style="font-size: 0.45rem; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; color: rgba(255,255,255,0.75); line-height: 1.3;">Sistem Data dan Tindak Lanjut<br>Temuan Inspeksi Sidoarjo</div>
                        </div>
                    </a>
                </h1>

                <!-- Realtime Digital Clock & Shift Widget (Phase 15) -->
                <div class="px-3 py-2 my-2 rounded-3 text-white border border-secondary shadow-xs d-none d-lg-block" style="font-size: 11px; background: rgba(15, 23, 42, 0.8) !important;">
                    <div class="d-flex align-items-center justify-content-between text-info font-weight-bold">
                        <span><i class="fas fa-clock me-1"></i> Live WIB</span>
                        <span class="badge bg-primary" style="font-size: 9px;"><?= shift() ?></span>
                    </div>
                    <div class="fw-bold text-white fs-6 mt-1" id="live-sidebar-clock"><?= date('H:i:s') ?> WIB</div>
                    <div class="text-muted" style="font-size: 10px;"><?= indo_date(date('Y-m-d'), true) ?></div>
                </div>

                <!-- Notification Bell Icon (Phase 31 Smart Notification Center) -->
                <div class="px-2 d-none d-lg-block dropdown">
                    <a href="#" class="btn btn-outline-light btn-sm position-relative rounded-circle me-2 dropdown-toggle text-decoration-none" id="bellNotifDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notification Center">
                        <i class="fas fa-bell text-warning"></i>
                        <span id="nav-unread-count-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 9px;">0</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-0" aria-labelledby="bellNotifDropdown" style="width: 320px; border-radius: 16px; overflow: hidden; z-index: 1050;">
                        <div class="p-3 bg-dark text-white d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0" style="font-size: 13px;"><i class="fas fa-bell text-warning me-1"></i> Notifikasi Center</h6>
                            <a href="<?= site_url('notifications') ?>" class="text-info text-decoration-none small">Lihat Semua &rarr;</a>
                        </div>
                        <div id="dropdown-notif-list" style="max-height: 280px; overflow-y: auto;">
                            <div class="text-center text-muted p-3 small"><i class="fas fa-spinner fa-spin me-1"></i> Memuat notifikasi...</div>
                        </div>
                    </div>
                </div>

                <!-- User Profile info on Mobile menu toggle -->
                <div class="navbar-nav flex-row d-lg-none">
                    <div class="nav-item">
                        <span class="nav-link text-white font-weight-bold p-0 me-2" style="font-size: 0.8rem;">
                            <i class="fas fa-user-circle me-1"></i> <?= esc(session()->get('user_name')) ?>
                        </span>
                    </div>
                </div>

                <!-- Navigation List with Enterprise Categories (Phase 30 UX/UI Reborn) -->
                <div class="collapse navbar-collapse" id="sidebar-menu">
                    <ul class="navbar-nav pt-lg-3">

                        <!-- CATEGORY: ANALYTICS -->
                        <li class="nav-header text-uppercase text-muted px-3 mt-2 mb-1" style="font-size: 10px; font-weight: 800; letter-spacing: 1px;">
                            <i class="fas fa-chart-pie me-1 text-primary"></i> ANALYTICS
                        </li>
                        <li class="nav-item <?= (url_is('dashboard') && !url_is('executive-dashboard')) ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('dashboard') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-gauge-high text-primary"></i></span>
                                <span class="nav-link-title">Dashboard Utama</span>
                            </a>
                        </li>
                        <li class="nav-item <?= (url_is('executive-dashboard') || url_is('dashboard/executive')) ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('executive-dashboard') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-chart-line text-warning"></i></span>
                                <span class="nav-link-title">Executive Analytics</span>
                            </a>
                        </li>
                        <li class="nav-item <?= (url_is('ai-copilot*') || url_is('sidak-ai*')) ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('ai-copilot') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-robot text-info"></i></span>
                                <span class="nav-link-title">🤖 SIDAK AI Copilot</span>
                            </a>
                        </li>
                        <li class="nav-item <?= (url_is('ai-predictive*') || url_is('ai-center*')) ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('ai-center') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-brain text-purple"></i></span>
                                <span class="nav-link-title">AI Center & Predictive Engine</span>
                            </a>
                        </li>
                        <li class="nav-item <?= (url_is('asset-health*') || url_is('penyulang/health-index*')) ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('asset-health') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-heart-pulse text-danger"></i></span>
                                <span class="nav-link-title">Asset Health Index</span>
                            </a>
                        </li>
                        <li class="nav-item <?= url_is('ecc*') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('ecc') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-tv text-danger"></i></span>
                                <span class="nav-link-title">Command Center (ECC)</span>
                            </a>
                        </li>
                        <li class="nav-item <?= url_is('notifications*') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('notifications') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-bell text-warning"></i></span>
                                <span class="nav-link-title">Notifikasi Center</span>
                            </a>
                        </li>

                        <li class="nav-item <?= url_is('my-dashboard*') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('my-dashboard') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-user-circle text-success"></i></span>
                                <span class="nav-link-title">My Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item <?= url_is('ranking*') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('ranking') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-ranking-star text-warning"></i></span>
                                <span class="nav-link-title">Auto Ranking</span>
                            </a>
                        </li>

                        <!-- CATEGORY: OPERASIONAL -->
                        <li class="nav-header text-uppercase text-muted px-3 mt-3 mb-1" style="font-size: 10px; font-weight: 800; letter-spacing: 1px;">
                            <i class="fas fa-screwdriver-wrench me-1 text-info"></i> OPERASIONAL
                        </li>
                        <?php if (check_role(['administrator', 'admin_ulp', 'inspeksi'])): ?>
                        <li class="nav-item <?= url_is('temuan/create') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('temuan/create') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-plus-circle text-success"></i></span>
                                <span class="nav-link-title">Input Temuan</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item <?= (url_is('temuan') && !url_is('temuan/terdekat') && !url_is('temuan/create') && !url_is('temuan/update-pekerjaan') ? 'active' : '') ?>">
                            <a class="nav-link" href="<?= site_url('temuan') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-list-check text-primary"></i></span>
                                <span class="nav-link-title">Data Temuan</span>
                            </a>
                        </li>
                        <li class="nav-item <?= url_is('work-orders*') && !url_is('work-orders/smart') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('work-orders') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-file-invoice text-info"></i></span>
                                <span class="nav-link-title">Work Orders (WO)</span>
                            </a>
                        </li>
                        <li class="nav-item <?= (url_is('smart-wo*') || url_is('work-orders/smart*')) ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('smart-wo') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-wand-magic-sparkles text-warning"></i></span>
                                <span class="nav-link-title">Smart WO Center</span>
                            </a>
                        </li>
                        <?php if (!check_role(['supervisor_up3'])): ?>
                        <li class="nav-item <?= url_is('temuan/update-pekerjaan') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('temuan/update-pekerjaan') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-pen-to-square text-warning"></i></span>
                                <span class="nav-link-title">Update Pekerjaan</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <!-- CATEGORY: DATA -->
                        <li class="nav-header text-uppercase text-muted px-3 mt-3 mb-1" style="font-size: 10px; font-weight: 800; letter-spacing: 1px;">
                            <i class="fas fa-database me-1 text-success"></i> DATA & GIS
                        </li>
                        <?php if (!check_role(['har_row'])): ?>
                        <li class="nav-item dropdown <?= url_is('eviden*') ? 'show active' : '' ?>">
                            <a class="nav-link dropdown-toggle" href="#menu-eviden" data-bs-toggle="collapse" role="button" aria-expanded="<?= url_is('eviden*') ? 'true' : 'false' ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-folder-open text-primary"></i></span>
                                <span class="nav-link-title">Eviden Lapangan</span>
                            </a>
                            <div class="collapse <?= url_is('eviden*') ? 'show' : '' ?>" id="menu-eviden">
                                <ul class="navbar-nav">
                                    <li><a class="dropdown-item <?= url_is('eviden/kubikel*') ? 'active' : '' ?>" href="<?= site_url('eviden/kubikel') ?>">Eviden Kubikel</a></li>
                                    <li><a class="dropdown-item <?= url_is('eviden/trafo*') ? 'active' : '' ?>" href="<?= site_url('eviden/trafo') ?>">Eviden Trafo</a></li>
                                    <li><a class="dropdown-item <?= url_is('eviden/management*') ? 'active' : '' ?>" href="<?= site_url('eviden/management') ?>">Management Trafo</a></li>
                                </ul>
                            </div>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item <?= url_is('assets*') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('assets') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-boxes-stacked text-warning"></i></span>
                                <span class="nav-link-title">Master Asset PLN</span>
                            </a>
                        </li>
                        <li class="nav-item <?= (url_is('gis*') || url_is('peta-jaringan*') ? 'active' : '') ?>">
                            <a class="nav-link" href="<?= site_url('gis') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-map-marked-alt text-success"></i></span>
                                <span class="nav-link-title">Peta Jaringan (GIS)</span>
                            </a>
                        </li>
                        <li class="nav-item <?= url_is('temuan/terdekat') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('temuan/terdekat') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-location-crosshairs text-danger"></i></span>
                                <span class="nav-link-title">Temuan Terdekat</span>
                            </a>
                        </li>
                        <?php if (check_role(['administrator', 'admin_ulp'])): ?>
                        <li class="nav-item dropdown <?= (url_is('ulps*') || url_is('penyulang*') || url_is('sections*') || url_is('users*') || url_is('import*') ? 'show active' : '') ?>">
                            <a class="nav-link dropdown-toggle" href="#menu-master" data-bs-toggle="collapse" role="button">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-layer-group"></i></span>
                                <span class="nav-link-title">Master Referensi</span>
                            </a>
                            <div class="collapse <?= (url_is('ulps*') || url_is('penyulang*') || url_is('sections*') || url_is('users*') || url_is('import*') ? 'show' : '') ?>" id="menu-master">
                                <ul class="navbar-nav">
                                    <?php if (check_role(['administrator'])): ?>
                                    <li><a class="dropdown-item <?= url_is('ulps*') ? 'active' : '' ?>" href="<?= site_url('ulps') ?>">Data ULP</a></li>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item <?= url_is('penyulang*') ? 'active' : '' ?>" href="<?= site_url('penyulang') ?>">Data Penyulang</a></li>
                                    <li><a class="dropdown-item <?= url_is('sections*') ? 'active' : '' ?>" href="<?= site_url('sections') ?>">Data Section</a></li>
                                    <li><a class="dropdown-item <?= url_is('users*') ? 'active' : '' ?>" href="<?= site_url('users') ?>">Data User</a></li>
                                    <li><a class="dropdown-item <?= url_is('import*') ? 'active' : '' ?>" href="<?= site_url('import') ?>"><i class="fas fa-file-excel me-1 text-success"></i> Impor Excel</a></li>
                                </ul>
                            </div>
                        </li>
                        <?php endif; ?>

                        <!-- CATEGORY: LAPORAN -->
                        <li class="nav-header text-uppercase text-muted px-3 mt-3 mb-1" style="font-size: 10px; font-weight: 800; letter-spacing: 1px;">
                            <i class="fas fa-file-lines me-1 text-warning"></i> LAPORAN & DOKUMEN
                        </li>
                        <li class="nav-item <?= url_is('laporan*') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('laporan') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-print text-primary"></i></span>
                                <span class="nav-link-title">Pusat Laporan</span>
                            </a>
                        </li>
                        <li class="nav-item <?= url_is('documents*') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('documents') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-file-contract text-teal"></i></span>
                                <span class="nav-link-title">Dokumen Center</span>
                            </a>
                        </li>
                        <li class="nav-item <?= (url_is('audit-log*') || url_is('digital-evidence*') || url_is('time-machine*')) ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('audit-log') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-shield-halved text-warning"></i></span>
                                <span class="nav-link-title">Audit Trail & Evidence</span>
                            </a>
                        </li>
                        <li class="nav-item <?= url_is('integration*') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('integration') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-network-wired text-indigo"></i></span>
                                <span class="nav-link-title">Integration Center</span>
                            </a>
                        </li>
                        <li class="nav-item <?= url_is('backup-database*') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('backup-database') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-database text-warning"></i></span>
                                <span class="nav-link-title">Backup Database</span>
                            </a>
                        </li>

                        <!-- CATEGORY: SETTING -->
                        <li class="nav-header text-uppercase text-muted px-3 mt-3 mb-1" style="font-size: 10px; font-weight: 800; letter-spacing: 1px;">
                            <i class="fas fa-sliders me-1 text-secondary"></i> SETTING & AKUN
                        </li>
                        <li class="nav-item <?= url_is('change-password*') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('change-password') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-key text-info"></i></span>
                                <span class="nav-link-title">Ganti Password</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="<?= site_url('logout') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-power-off text-danger"></i></span>
                                <span class="nav-link-title">Logout System</span>
                            </a>
                        </li>

                        <!-- Pusat Laporan Dropdown & Identifikasi Gangguan -->
                        <li class="nav-item dropdown <?= url_is('laporan*') ? 'show active' : '' ?>">
                            <a class="nav-link dropdown-toggle" href="#menu-laporan" data-bs-toggle="collapse" role="button">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-print text-primary"></i></span>
                                <span class="nav-link-title">Pusat Laporan</span>
                            </a>
                            <div class="collapse <?= url_is('laporan*') ? 'show' : '' ?>" id="menu-laporan">
                                <ul class="navbar-nav">
                                    <li><a class="dropdown-item <?= (url_is('laporan/temuan*') || url_is('laporan') ? 'active' : '') ?>" href="<?= site_url('laporan/temuan') ?>">Lap. Temuan</a></li>
                                    <li><a class="dropdown-item <?= url_is('laporan/eviden*') ? 'active' : '' ?>" href="<?= site_url('laporan/eviden') ?>">Lap. Eviden Lapangan</a></li>
                                    <li><a class="dropdown-item <?= url_is('laporan/management*') ? 'active' : '' ?>" href="<?= site_url('laporan/management') ?>">Lap. Management Trafo</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item <?= url_is('identifikasi*') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('identifikasi') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="nav-icon fas fa-bolt text-warning"></i></span>
                                <span class="nav-link-title">Identifikasi Gangguan</span>
                            </a>
                        </li>

                        <!-- Ganti Versi Layout -->
                        <li class="nav-item mt-3 border-top pt-2">
                            <a class="nav-link text-info font-weight-bold" href="<?= site_url('dashboard/toggle-view?t=' . time()) ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="nav-icon fas fa-mobile-screen-button"></i>
                                </span>
                                <span class="nav-link-title">Ganti Versi Mobile</span>
                            </a>
                        </li>

                        <!-- Logout / Keluar -->
                        <li class="nav-item">
                            <a class="nav-link text-danger font-weight-bold" href="<?= site_url('logout') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="nav-icon fas fa-power-off"></i>
                                </span>
                                <span class="nav-link-title">Keluar (Logout)</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="page-wrapper d-flex flex-column" style="min-height: 100vh;">
            <!-- Top Navbar Header -->
            <header class="navbar navbar-expand navbar-light d-flex d-print-none navbar-top-wrapper" style="border-bottom: 1px solid rgba(0, 0, 0, 0.08); background-color: #ffffff; padding: 0.5rem 1rem;">
                <div class="container-xl d-flex justify-content-between align-items-center flex-nowrap" style="gap: 8px;">
                    <div class="d-flex align-items-center flex-shrink-0">
                        <!-- Mobile toggle view for desktop -->
                        <a href="<?= site_url('dashboard/toggle-view?t=' . time()) ?>" class="btn btn-outline-primary btn-sm px-2 py-1" style="font-size: 11px; font-weight: 700; border-radius: 4px; white-space: nowrap;">
                            <i class="fas fa-mobile-screen-button me-1"></i> Versi Mobile
                        </a>
                    </div>

                    <!-- RUNNING TICKER MOTIVATIONAL ANNOUNCEMENT (CENTER HEADER - DESKTOP ONLY) -->
                    <div class="header-ticker-container mx-2 d-none d-xl-flex align-items-center" style="max-width: 28%; min-width: 180px; overflow: hidden; background: linear-gradient(90deg, #004D4F 0%, #007275 100%); border-radius: 20px; padding: 4px 14px; box-shadow: 0 2px 6px rgba(0,77,79,0.15);">
                        <i class="fas fa-bullhorn text-warning me-2 animate__animated animate__pulse animate__infinite" style="font-size: 12px; flex-shrink: 0;"></i>
                        <span class="badge bg-warning text-dark font-weight-bold me-2 px-2" style="font-size: 10px; border-radius: 10px; flex-shrink: 0;">MOTIVASI:</span>
                        <div class="ticker-wrapper flex-grow-1" style="overflow: hidden; white-space: nowrap; position: relative;">
                            <div class="ticker-content d-inline-block font-weight-bold running-announcement-text-target" id="running-announcement-text" style="display: inline-block; padding-left: 100%; animation: tickerAnimation 22s linear infinite; font-size: 12px; color: #ffffff;">
                                <?= esc(get_daily_announcement()) ?>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 6: GLOBAL SMART SEARCH BAR -->
                    <div class="flex-grow-1 mx-2 d-none d-md-block" style="max-width: 340px; position: relative;" id="global-search-wrapper">
                        <form method="GET" action="<?= site_url('smart-search') ?>" autocomplete="off" id="global-search-form">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text" style="border-radius: 10px 0 0 10px; background: #f8fafc; border-color: #e2e8f0;">
                                    <i class="fas fa-search text-muted" style="font-size: 11px;"></i>
                                </span>
                                <input type="text" name="q" id="global-search-input" class="form-control"
                                       placeholder="Cari temuan, WO, penyulang..."
                                       style="border-left: none; border-radius: 0 10px 10px 0; border-color: #e2e8f0; font-size: 12px;"
                                       autocomplete="off">
                            </div>
                        </form>
                        <!-- Autocomplete Dropdown -->
                        <div id="global-search-dropdown" class="shadow-sm" style="display:none; position:absolute; top:calc(100% + 6px); left:0; right:0; background:#fff; border:1px solid #e2e8f0; border-radius:12px; z-index:9999; max-height:320px; overflow-y:auto;"></div>
                    </div>

                    <div class="navbar-nav flex-row align-items-center flex-shrink-0" style="gap: 6px;">
                        <!-- STEP 8: Favorite Menu Button -->
                        <div class="nav-item dropdown">
                            <button class="btn btn-sm btn-outline-warning px-2 py-1 dropdown-toggle" type="button"
                                    id="favMenuToggle" data-bs-toggle="dropdown" aria-expanded="false"
                                    title="Menu Favorit" style="font-size: 11px; border-radius: 8px;">
                                <i class="fas fa-star"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end p-2" id="fav-menu-list" style="min-width: 220px; border-radius: 14px;">
                                <li class="text-muted small px-2 pb-1 fw-bold" style="font-size: 10px; letter-spacing: 1px;">MENU FAVORIT</li>
                                <li><hr class="dropdown-divider mt-0 mb-2"></li>
                                <li><div class="text-center text-muted py-2" style="font-size: 12px;" id="fav-empty-msg">Belum ada menu favorit.<br><small>Klik ⭐ di sidebar untuk tambah.</small></div></li>
                            </ul>
                        </div>
                        <div class="nav-item me-1 d-none d-sm-block">
                            <span class="fw-bold text-dark" style="font-size: 0.8rem;">
                                <i class="fas fa-user-circle me-1" style="color: #005eb8;"></i> <?= esc(session()->get('user_name')) ?> 
                                <span class="badge bg-blue-lt ms-1" style="font-size: 10px; font-weight: 700;"><?= esc(get_role_label(session()->get('user_role'))) ?></span>
                            </span>
                        </div>
                        <div class="nav-item">
                            <a class="btn btn-warning text-dark btn-sm px-2 py-1 font-weight-bold" href="<?= site_url('backup-database') ?>" title="Backup Database Hostinger" style="font-size: 11px; border-radius: 4px; white-space: nowrap;">
                                <i class="fas fa-database me-1"></i> <span class="d-none d-md-inline">Backup Database</span>
                            </a>
                        </div>
                        <div class="nav-item">
                            <a class="btn btn-outline-primary btn-sm px-2 py-1" href="<?= site_url('change-password') ?>" title="Ganti Password Saya" style="font-size: 11px; font-weight: 600; border-radius: 4px; white-space: nowrap;">
                                <i class="fas fa-key"></i> <span class="d-none d-md-inline ms-1">Ganti Password</span>
                            </a>
                        </div>
                        <div class="nav-item">
                            <a class="btn btn-outline-danger btn-icon-only rounded-circle" href="<?= site_url('logout') ?>" title="Keluar" style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="fas fa-power-off"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page header -->
            <div class="page-header d-print-none px-4 pt-3">
                <div class="container-xl">
                    <div class="row g-2 align-items-center justify-content-between">
                        <div class="col">
                            <h2 class="page-title text-dark font-weight-bold" style="font-size: 1.5rem;">
                                <?= $this->renderSection('page_title') ?>
                            </h2>
                        </div>
                        <div class="col-auto">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">SIDAK TEJO</a></li>
                                <?= $this->renderSection('breadcrumb') ?>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page body -->
            <div class="page-body px-4 flex-grow-1">
                <div class="container-xl animate__animated animate__fadeIn animate__faster">
                    <?= $this->renderSection('content') ?>
                </div>
            </div>

            <!-- Footer -->
            <footer class="footer footer-transparent d-print-none bg-white border-top py-3 text-center small text-muted">
                <div class="container-xl d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <strong>Copyright &copy; 2026 <span style="color: #005eb8;">SIDAK TEJO</span>.</strong> All rights reserved.
                    </div>
                    <div>
                        <span class="badge bg-primary text-white" style="font-size: 11px; padding: 4px 8px;">Build: 998b582 - Enterprise PLN Mobile</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- ===== MOBILE BOTTOM NAVIGATION BAR ===== -->
    <nav class="mobile-bottom-nav" id="mobile-bottom-nav">
        <!-- Dashboard -->
        <a href="<?= site_url('dashboard') ?>" class="mob-nav-item <?= url_is('dashboard') ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <!-- Data Temuan -->
        <a href="<?= site_url('temuan') ?>" class="mob-nav-item <?= url_is('temuan') ? 'active' : '' ?>">
            <i class="fas fa-list-check"></i>
            <span>Temuan</span>
        </a>
        <!-- Input Temuan (Center FAB) -->
        <?php if (check_role(['administrator', 'admin_ulp', 'inspeksi'])): ?>
        <a href="<?= site_url('temuan/create') ?>" class="mob-nav-item" style="flex:0 0 60px;">
            <div class="mob-nav-center">
                <i class="fas fa-plus"></i>
            </div>
        </a>
        <?php else: ?>
        <a href="<?= site_url('eviden/trafo') ?>" class="mob-nav-item" style="flex:0 0 60px;">
            <div class="mob-nav-center">
                <i class="fas fa-folder-open"></i>
            </div>
        </a>
        <?php endif; ?>
        <!-- Eviden Lapangan -->
        <a href="<?= site_url('eviden/kubikel') ?>" class="mob-nav-item <?= url_is('eviden*') ? 'active' : '' ?>">
            <i class="fas fa-folder-open"></i>
            <span>Eviden</span>
        </a>
        <!-- Laporan -->
        <a href="<?= site_url('laporan/temuan') ?>" class="mob-nav-item <?= url_is('laporan*') ? 'active' : '' ?>">
            <i class="fas fa-print"></i>
            <span>Laporan</span>
        </a>
    </nav>

    <!-- Local JS Files (Offline-Safe & Correct Scope) -->
    <?php foreach ($jsFiles as $file): ?>
        <script src="<?= base_url($file) ?>"></script>
    <?php endforeach; ?>

    <script>
        // Sembunyikan spinner pemuatan (Clean & Consolidated)
        $(function() {
            $('#loading-spinner').fadeOut(150);
        });

        // Register Service Worker for caching maps & assets with auto-update
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?= base_url("service-worker.js") ?>?v=8-enterprise')
                    .then(function(registration) {
                        registration.update();
                        console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    }, function(err) {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }

        // Function for Admin to edit daily motivational announcement
        function promptEditAnnouncement() {
            var currentMsg = $('.running-announcement-text-target').first().text().trim();
            if (!currentMsg) {
                currentMsg = $('#running-announcement-text').text().trim();
            }
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
                if (result.isConfirmed && result.value) {
                    var newText = result.value.trim();
                    var targetUrl = "<?= site_url('setting/update-announcement') ?>";
                    
                    function updateDOM(textMsg) {
                        var $targets = $('.running-announcement-text-target, #running-announcement-text');
                        $targets.text(textMsg);
                        $targets.css('animation', 'none');
                        $targets.each(function() { void this.offsetWidth; });
                        $targets.css('animation', 'tickerAnimation 22s linear infinite');
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: 'Kata-kata motivasi harian berhasil diperbarui!',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }

                    $.ajax({
                        url: targetUrl,
                        type: 'POST',
                        data: {
                            message: newText,
                            "<?= csrf_token() ?>": "<?= csrf_hash() ?>"
                        },
                        dataType: 'json',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        success: function(res) {
                            if (res && res.success) {
                                updateDOM(res.announcement || newText);
                            } else {
                                // Retry via GET
                                $.get(targetUrl, { message: newText }, function(res2) {
                                    updateDOM(newText);
                                }).fail(function() {
                                    Swal.fire('Gagal', (res && res.message) ? res.message : 'Gagal menyimpan.', 'error');
                                });
                            }
                        },
                        error: function() {
                            // Fallback to GET
                            $.get(targetUrl, { message: newText }, function(res2) {
                                updateDOM(newText);
                            }).fail(function() {
                                Swal.fire('Error', 'Gagal menghubungi server.', 'error');
                            });
                        }
                    });
                }
            });
        }
        $(function () {
            if ($('.select2').length) {
                $('.select2').select2({
                    theme: 'bootstrap4'
                });
            }
        });

        // Inisialisasi Notifikasi Swal / Toast
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            background: '#ffffff',
            color: '#333333',
            iconColor: '#005eb8'
        });

        <?php if (session()->getFlashdata('success')): ?>
            Swal.fire({
                title: 'Berhasil',
                text: '<?= esc(session()->getFlashdata('success')) ?>',
                icon: 'success',
                confirmButtonText: 'OK',
                confirmButtonColor: '#005eb8'
            });
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
            Swal.fire({
                title: 'Gagal',
                text: '<?= esc(session()->getFlashdata('error')) ?>',
                icon: 'error',
                confirmButtonText: 'OK',
                confirmButtonColor: '#005eb8'
            });
        <?php endif; ?>
    </script>

    <!-- Floating Voice Assistant -->
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
            animation: pulseMicAnimation 1.2s infinite;
        }
        @keyframes pulseMicAnimation {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 12px rgba(239, 68, 68, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
    </style>
    <div id="global-voice-container">
        <!-- Status Bubble -->
        <div id="global-voice-bubble" class="shadow-sm d-none animate__animated animate__fadeInRight" 
             style="background: #0f172a; color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.4); padding: 8px 14px; border-radius: 20px; font-size: 0.82rem; font-weight: bold; white-space: nowrap; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);">
            <i class="fas fa-brain fa-pulse me-1 text-warning"></i> <span id="global-voice-text">Mendengarkan ("Halo SIDAK")...</span>
        </div>
        
        <!-- Floating Mic Button -->
        <button type="button" id="btn-global-mic" class="btn btn-primary shadow" title="SIDAK TEJO Voice AI Assistant (Halo SIDAK)">
            <i class="fas fa-microphone" id="global-mic-icon"></i>
        </button>
    </div>

    <script>
        $(function() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            let recognition = null;
            let isListening = false;
            let isContinuous = true;

            // Load Proactive Smart Notifications on page load (FITUR 8)
            fetchSmartNotifications();

            if (SpeechRecognition) {
                try {
                    recognition = new SpeechRecognition();
                    recognition.lang = 'id-ID';
                    recognition.interimResults = false;
                    recognition.continuous = isContinuous;

                    recognition.onstart = function() {
                        isListening = true;
                        $('#btn-global-mic').addClass('listening');
                        $('#global-voice-bubble').removeClass('d-none');
                        $('#global-voice-text').text('AI Mendengarkan... (Ucapkan: Halo SIDAK)');
                    };

                    recognition.onerror = function(event) {
                        isListening = false;
                        $('#btn-global-mic').removeClass('listening');
                        if (event.error === 'not-allowed') {
                            const isHttp = !window.isSecureContext && location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1';
                            const msg = isHttp 
                                ? 'Fitur Voice AI membutuhkan koneksi HTTPS. Peramban memblokir akses mikrofon pada koneksi HTTP.' 
                                : 'Harap izinkan akses mikrofon untuk menggunakan perintah suara SIDAK TEJO.';
                            Swal.fire({ icon: 'warning', title: 'Akses Mikrofon Ditolak', text: msg, confirmButtonColor: '#005eb8' });
                        }
                    };

                    recognition.onend = function() {
                        isListening = false;
                        $('#btn-global-mic').removeClass('listening');
                        if (isContinuous) {
                            setTimeout(() => { try { recognition.start(); } catch(e){} }, 1000);
                        }
                    };

                    recognition.onresult = function(event) {
                        const lastIndex = event.results.length - 1;
                        const resultText = event.results[lastIndex][0].transcript.toLowerCase().trim();
                        
                        $('#global-voice-bubble').removeClass('d-none');
                        $('#global-voice-text').html('<i class="fas fa-quote-left me-1 text-warning"></i> "' + resultText + '"');
                        
                        setTimeout(function() {
                            $('#global-voice-bubble').addClass('d-none');
                        }, 4000);

                        // Wake Word Detector ("Halo SIDAK", "Hai SIDAK", "SIDAK") (FITUR 1)
                        if (resultText.includes('halo sidak') || resultText.includes('hai sidak') || resultText.startsWith('sidak')) {
                            const commandText = resultText.replace(/\b(halo sidak|hai sidak|sidak)\b/gi, '').trim();
                            if (commandText.length > 0) {
                                executeAiCommand(commandText);
                            } else {
                                speakAiFeedback("Ya, ada yang bisa SIDAK AI bantu?");
                            }
                        } else {
                            executeAiCommand(resultText);
                        }
                    };
                } catch(err) {
                    console.error('SpeechRecognition init error:', err);
                }
            }

            window.triggerGlobalVoiceMic = function() {
                if (!recognition) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Fitur Perintah Suara',
                        text: 'Peramban ini belum mendukung Speech Recognition. Disarankan menggunakan Google Chrome versi terbaru.',
                        confirmButtonColor: '#005eb8'
                    });
                    return;
                }

                if (!isListening) {
                    try { recognition.start(); } catch(err) {
                        try { recognition.stop(); setTimeout(() => recognition.start(), 150); } catch(ex) {}
                    }
                } else {
                    try { recognition.stop(); } catch(err) {}
                }
            };

            let lastMicTap = 0;
            $(document).on('click touchstart', '#btn-global-mic', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const now = Date.now();
                if (now - lastMicTap < 400) return;
                lastMicTap = now;
                window.triggerGlobalVoiceMic();
            });

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

            function executeAiCommand(text) {
                if (!text || text.length < 2) return;

                // 1. Smart DataTable Filter Integration (FITUR 6)
                if (window.location.href.includes('/temuan') && $.fn.DataTable && $.fn.DataTable.isDataTable('#tableTemuan')) {
                    if (text.includes('minggu ini') || text.includes('hari ini') || text.includes('belum selesai') || text.includes('emergency') || text.includes('hotspot') || text.includes('row')) {
                        const table = $('#tableTemuan').DataTable();
                        table.search(text).draw();
                        speakAiFeedback("Memfilter tabel data temuan untuk kata kunci " + text);
                        Swal.fire({ toast: true, position: 'top', icon: 'success', title: 'Smart DataTable Filter', text: 'Tabel difilter: "' + text + '"', showConfirmButton: false, timer: 2500 });
                        return;
                    }
                }

                // 2. Send command to backend AI API
                $.ajax({
                    url: "<?= site_url('api/v1/voice-ai/process') ?>",
                    type: "POST",
                    contentType: "application/json",
                    data: JSON.stringify({ text: text, channel: 'voice' }),
                    dataType: "json",
                    success: function(res) {
                        if (res.success) {
                            speakAiFeedback(res.response_text);
                            Swal.fire({
                                toast: true,
                                position: 'top',
                                icon: 'info',
                                title: '🤖 SIDAK AI Assistant',
                                text: res.response_text,
                                showConfirmButton: false,
                                timer: 4500,
                                timerProgressBar: true
                            });

                            // Action Handling
                            if (res.action) {
                                if (res.action.type === 'NAVIGATE' && res.action.url) {
                                    setTimeout(() => window.location.href = res.action.url, 1200);
                                }
                            }
                        }
                    },
                    error: function() {
                        // Fallback client-side matching
                        processVoiceCommandFallback(text);
                    }
                });
            }

            function processVoiceCommandFallback(text) {
                text = text.toLowerCase().trim();
                let rawTokens = text.replace(/\b(tolong|mohon|coba|tampilkan|lihat|buka|cari|temukan|filter|saring|data|tabel|daftar)\b/gi, '').trim();

                if (rawTokens.includes('dashboard')) {
                    speakAiFeedback('Membuka Dashboard');
                    setTimeout(() => window.location.href = '<?= site_url("executive-dashboard") ?>', 800);
                    return;
                }
                if (rawTokens.includes('input') || rawTokens.includes('tambah')) {
                    speakAiFeedback('Membuka Form Input Temuan Baru');
                    setTimeout(() => window.location.href = '<?= site_url("temuan/create") ?>', 800);
                    return;
                }
                if (rawTokens.includes('laporan') || rawTokens.includes('report')) {
                    speakAiFeedback('Membuka Pusat Laporan Temuan');
                    setTimeout(() => window.location.href = '<?= site_url("laporan") ?>', 800);
                    return;
                }
                if (rawTokens.includes('eviden')) {
                    speakAiFeedback('Membuka Eviden');
                    setTimeout(() => window.location.href = '<?= site_url("eviden") ?>', 800);
                    return;
                }
                speakAiFeedback('Perintah ' + text + ' tidak dikenali.');
            }

            function fetchSmartNotifications() {
                $.ajax({
                    url: "<?= site_url('api/v1/voice-ai/notifications') ?>",
                    type: "GET",
                    dataType: "json",
                    success: function(res) {
                        if (res.success && res.notifications && res.notifications.length > 0) {
                            res.notifications.forEach(n => {
                                Swal.fire({
                                    toast: true,
                                    position: 'top-end',
                                    icon: n.type === 'EMERGENCY' ? 'error' : 'warning',
                                    title: n.title,
                                    text: n.message,
                                    showConfirmButton: true,
                                    confirmButtonText: 'Tinjau',
                                    timer: 8000
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        window.location.href = '<?= site_url("executive-dashboard#tab-monitoring") ?>';
                                    }
                                });
                            });
                        }
                    }
                });
            }
        });

        // Global DataTables Accessibility Fix (Fix <label for="..."> and missing id/name)
        $(document).on('init.dt', function (e, settings) {
            var api = new $.fn.dataTable.Api(settings);
            var $table = $(api.table().node());
            var tableId = $table.attr('id') || 'dt_' + Math.random().toString(36).substr(2, 5);
            var $wrapper = $table.closest('.dataTables_wrapper');
            
            var $searchContainer = $wrapper.find('.dataTables_filter');
            var $searchInput = $searchContainer.find('input');
            var $searchLabel = $searchContainer.find('label');
            if ($searchInput.length && $searchLabel.length) {
                var sId = tableId + '_search_input';
                $searchInput.attr('id', sId).attr('name', sId);
                $searchLabel.attr('for', sId);
            }
            
            var $lengthContainer = $wrapper.find('.dataTables_length');
            var $lengthSelect = $lengthContainer.find('select');
            var $lengthLabel = $lengthContainer.find('label');
            if ($lengthSelect.length && $lengthLabel.length) {
                var lId = tableId + '_length_select';
                $lengthSelect.attr('id', lId).attr('name', lId);
                $lengthLabel.attr('for', lId);
            }
        });

        $.ajaxSetup({
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            statusCode: {
                401: function() {
                    if (navigator.onLine) {
                        Swal.fire({
                            title: 'Sesi Berakhir!',
                            text: 'Sesi login Anda telah habis setelah 8 jam tidak aktif. Silakan login kembali.',
                            icon: 'warning',
                            confirmButtonColor: '#005eb8',
                            confirmButtonText: '<i class="fas fa-right-to-bracket mr-1"></i> Login Kembali'
                        }).then(() => {
                            window.location.href = '<?= site_url('login') ?>';
                        });
                    }
                }
            }
        });

        // ============================================================
        // ENTERPRISE SESSION MANAGEMENT - AUTO KEEP-ALIVE & RESILIENCE
        // ============================================================
        function sendSessionKeepAlivePing() {
            if (!navigator.onLine) return; // Do not touch session when offline

            $.ajax({
                url: "<?= site_url('auth/ping') ?>",
                type: "GET",
                dataType: "json",
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(res) {
                    // Session successfully extended
                },
                error: function(xhr) {
                    if (xhr.status === 401 && navigator.onLine) {
                        Swal.fire({
                            title: 'Sesi Telah Berakhir',
                            text: 'Sesi Anda telah berakhir setelah 8 jam tidak aktif. Silakan login kembali.',
                            icon: 'info',
                            confirmButtonColor: '#005eb8',
                            confirmButtonText: '<i class="fas fa-right-to-bracket mr-1"></i> Login Kembali'
                        }).then(() => {
                            window.location.href = '<?= site_url("login") ?>';
                        });
                    }
                }
            });
        }

        // Auto ping every 60 seconds
        setInterval(sendSessionKeepAlivePing, 60000);

        // Instant ping on tab focus, screen unlock, or online reconnect (Tasks 7 & 8)
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                sendSessionKeepAlivePing();
            }
        });

        window.addEventListener('focus', sendSessionKeepAlivePing);
        window.addEventListener('online', sendSessionKeepAlivePing);

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
        // Zero-Lag Realtime Digital Clock Engine (Phase 15)
        (function() {
            let serverTimestamp = <?= time() ?>;
            function updateLiveClock() {
                serverTimestamp++;
                const date = new Date(serverTimestamp * 1000);
                
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                const seconds = String(date.getSeconds()).padStart(2, '0');
                const clockString = hours + ':' + minutes + ':' + seconds + ' WIB';
                
                const $clockEl = $('#live-sidebar-clock, #live-header-clock, #live-mobile-clock');
                if ($clockEl.length) {
                    $clockEl.text(clockString);
                }
            }
            setInterval(updateLiveClock, 1000);
        })();

        // Phase 31 Smart Notification Center AJAX Poller
        function fetchNavbarNotifications() {
            fetch("<?= site_url('notifications/api-unread-list') ?>")
                .then(res => res.json())
                .then(data => {
                    var badge = $('#nav-unread-count-badge');
                    if (badge.length) badge.text(data.unread_count || 0);

                    var container = $('#dropdown-notif-list');
                    if (container.length) {
                        if (!data.items || data.items.length === 0) {
                            container.html('<div class="text-center text-muted p-3 small">Tidak ada notifikasi baru.</div>');
                            return;
                        }

                        var html = '';
                        data.items.forEach(function(item) {
                            var icon = 'fa-info-circle text-info';
                            if (item.type === 'EMERGENCY') icon = 'fa-triangle-exclamation text-danger';
                            else if (item.type === 'WARNING') icon = 'fa-bolt text-warning';
                            else if (item.type === 'SUCCESS') icon = 'fa-circle-check text-success';

                            html += '<a href="' + item.target + '" class="dropdown-item p-2 border-bottom d-flex align-items-start gap-2" style="white-space: normal;">';
                            html += '<i class="fas ' + icon + ' mt-1 fs-6"></i>';
                            html += '<div><span class="fw-bold d-block text-dark small mb-0">' + item.title + '</span>';
                            html += '<small class="text-muted d-block" style="font-size: 10px;">' + item.time_ago + '</small></div>';
                            html += '</a>';
                        });
                        container.html(html);
                    }
                })
                .catch(err => console.log('Notif error:', err));
        }

        fetchNavbarNotifications();
        setInterval(fetchNavbarNotifications, 30000);

        // STEP 14: Online / Offline Network Status Badge Engine
        function updateNetworkStatus() {
            var $badge = $('#network-status-badge');
            if (!$badge.length) {
                $('body').append('<div id="network-status-badge" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; font-size: 11px; font-weight: 800; padding: 6px 14px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: all 0.3s ease;"></div>');
                $badge = $('#network-status-badge');
            }

            if (navigator.onLine) {
                $badge.css({ 'background': '#10b981', 'color': '#ffffff' })
                      .html('<i class="fas fa-wifi me-1"></i> ONLINE');
                setTimeout(function() { $badge.fadeOut(); }, 4000);
            } else {
                $badge.css({ 'background': '#ef4444', 'color': '#ffffff' })
                      .html('<i class="fas fa-plane me-1"></i> OFFLINE (Disimpan Lokal)').fadeIn();
            }
        }
        window.addEventListener('online', updateNetworkStatus);
        window.addEventListener('offline', updateNetworkStatus);
        updateNetworkStatus();

        // STEP 10: Keyboard Shortcuts Engine
        document.addEventListener('keydown', function(e) {
            // CTRL + N: Input Temuan Baru
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'n') {
                e.preventDefault();
                window.location.href = "<?= site_url('temuan/create') ?>";
            }
            // CTRL + F: Focus Global Smart Search or local input
            else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
                e.preventDefault();
                var $localSearch = $('#gis-search-input, input[type="search"][id!="global-search-input"], input[name="search"]').first();
                var $globalInput = $('#global-search-input');
                if ($localSearch.length) {
                    $localSearch.focus().select();
                } else if ($globalInput.length) {
                    $globalInput.focus().select();
                } else {
                    window.location.href = '<?= site_url('smart-search') ?>';
                }
            }
            // CTRL + S: Save Active Form
            else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                var $activeForm = $('form:visible').first();
                if ($activeForm.length) {
                    e.preventDefault();
                    $activeForm.submit();
                }
            }
            // ESC: Close Modals
            else if (e.key === 'Escape') {
                $('.modal.show').modal('hide');
                $('#global-search-dropdown').hide();
            }
        });

        // STEP 5: Auto Save Form Drafts (localStorage)
        $('form input, form textarea, form select').on('input change', function() {
            var $form = $(this).closest('form');
            if ($form.attr('action') && $form.attr('action').indexOf('temuan') !== -1) {
                var formData = $form.serializeArray();
                localStorage.setItem('sidak_form_draft', JSON.stringify(formData));
            }
        });

        // STEP 11: Session Restore — reload saved draft on create form page
        (function restoreFormDraft() {
            if (window.location.pathname.indexOf('/create') === -1) return;
            var saved = localStorage.getItem('sidak_form_draft');
            if (!saved) return;
            try {
                var arr = JSON.parse(saved);
                arr.forEach(function(field) {
                    var $el = $('[name="' + field.name + '"]');
                    if ($el.length && field.value) $el.val(field.value).trigger('change');
                });
                console.log('[SIDAK] Draft form dipulihkan.');
            } catch(e) { localStorage.removeItem('sidak_form_draft'); }
        })();

        // STEP 6: Global Smart Search Autocomplete
        (function initGlobalSearch() {
            var $input  = $('#global-search-input');
            var $drop   = $('#global-search-dropdown');
            var delay;
            var searchUrl = '<?= site_url('smart-search/api') ?>';
            var fullUrl   = '<?= site_url('smart-search') ?>';

            $input.on('input', function() {
                clearTimeout(delay);
                var q = $(this).val().trim();
                if (q.length < 2) { $drop.hide(); return; }
                delay = setTimeout(function() {
                    fetch(searchUrl + '?q=' + encodeURIComponent(q))
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (!data.results || data.results.length === 0) {
                                $drop.html('<div class="p-3 text-muted text-center small">Tidak ditemukan. Tekan Enter untuk pencarian penuh.</div>').show();
                                return;
                            }
                            var html = '';
                            data.results.slice(0, 8).forEach(function(item) {
                                var colorClass = item._color || 'text-secondary';
                                var icon = item._icon || 'fa-file';
                                html += '<a href="' + (item._url || '#') + '" class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none border-bottom smart-search-item">';
                                html += '<i class="fas ' + icon + ' ' + colorClass + '" style="width:16px;"></i>';
                                html += '<div class="flex-grow-1"><div class="fw-semibold small text-dark text-truncate" style="max-width:260px;">' + (item._label || '') + '</div>';
                                if (item.prioritas) html += '<small class="badge badge-sm" style="font-size:9px;background:#e2e8f0;color:#374151;">' + item.prioritas + '</small>';
                                html += '</div></a>';
                            });
                            html += '<a href="' + fullUrl + '?q=' + encodeURIComponent(q) + '" class="d-block text-center py-2 text-primary small fw-bold border-top">Lihat semua hasil →</a>';
                            $drop.html(html).show();
                        })
                        .catch(function() { $drop.hide(); });
                }, 280);
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('#global-search-wrapper').length) $drop.hide();
            });

            $('#global-search-form').on('submit', function() {
                $drop.hide();
                return true;
            });
        })();

        // STEP 8: Favorite Menu Engine (localStorage)
        (function initFavMenu() {
            var FAV_KEY = 'sidak_fav_menu_v1';
            var $list   = $('#fav-menu-list');
            var $empty  = $('#fav-empty-msg');

            function loadFavs() {
                var favs = [];
                try { favs = JSON.parse(localStorage.getItem(FAV_KEY) || '[]'); } catch(e) {}
                if (favs.length === 0) { $empty.show(); return; }
                $empty.hide();
                var html = '';
                favs.forEach(function(f, i) {
                    html += '<li><a class="dropdown-item rounded-2 d-flex align-items-center gap-2" href="' + f.url + '" style="font-size: 12px;">';
                    html += '<i class="fas ' + (f.icon || 'fa-link') + ' text-warning"></i>' + f.label;
                    html += '<button class="btn btn-sm btn-link text-danger ms-auto p-0" onclick="removeFav(' + i + '); event.preventDefault();" style="font-size:10px;"><i class="fas fa-times"></i></button>';
                    html += '</a></li>';
                });
                $list.find('li').filter(':not(:first):not(:nth-child(2))').remove();
                $list.append(html);
            }

            window.removeFav = function(idx) {
                var favs = [];
                try { favs = JSON.parse(localStorage.getItem(FAV_KEY) || '[]'); } catch(e) {}
                favs.splice(idx, 1);
                localStorage.setItem(FAV_KEY, JSON.stringify(favs));
                $list.find('li').filter(':not(:first):not(:nth-child(2))').remove();
                loadFavs();
            };

            window.addFav = function(label, url, icon) {
                var favs = [];
                try { favs = JSON.parse(localStorage.getItem(FAV_KEY) || '[]'); } catch(e) {}
                var exists = favs.some(function(f) { return f.url === url; });
                if (!exists) {
                    favs.push({ label: label, url: url, icon: icon || 'fa-star' });
                    localStorage.setItem(FAV_KEY, JSON.stringify(favs));
                    loadFavs();
                    if (typeof Toastify !== 'undefined') {
                        Toastify({ text: '⭐ Ditambahkan ke Favorit!', duration: 2500, gravity: 'bottom', position: 'right', style: { background: '#10b981' } }).showToast();
                    }
                }
            };

            loadFavs();
        })();

        // STEP 12: Auto Refresh Dashboard KPI Stats (AJAX, every 60s)
        (function initDashboardRefresh() {
            if (!document.getElementById('kpi-total-temuan')) return;
            function refreshKpi() {
                fetch('<?= site_url('dashboard/analytics-data') ?>')
                    .then(function(r) { return r.json(); })
                    .then(function(resp) {
                        if (!resp || !resp.data) return;
                        var d = resp.data;
                        // Map total_temuan
                        var el = document.getElementById('kpi-total-temuan');
                        if (el && d.total_temuan !== undefined) el.textContent = d.total_temuan;
                        // Map prioritas_breakdown
                        var pb = d.prioritas_breakdown || {};
                        var pMap = { 'kpi-emergency': 'EMERGENCY', 'kpi-high': 'HIGH', 'kpi-medium': 'MEDIUM' };
                        Object.keys(pMap).forEach(function(id) {
                            var pEl = document.getElementById(id);
                            if (pEl) pEl.textContent = pb[pMap[id]] || 0;
                        });
                        // Map status_breakdown
                        var sb = d.status_breakdown || {};
                        var sMap = { 'kpi-selesai': 'SELESAI', 'kpi-belum': 'BELUM', 'kpi-proses': 'PROSES' };
                        Object.keys(sMap).forEach(function(id) {
                            var sEl = document.getElementById(id);
                            if (sEl) sEl.textContent = sb[sMap[id]] || 0;
                        });
                    })
                    .catch(function() {});
            }
            setInterval(refreshKpi, 60000);
        })();
        // STEP 11: Smart Filter Global Component (Phase 32)
        (function initSmartFilter() {
            // Inject Quick Filter Bar into any element with class .smart-filter-target
            var targets = document.querySelectorAll('.smart-filter-target');
            if (!targets.length) return;

            var filterHtml = '<div class="smart-filter-bar d-flex flex-wrap gap-1 mb-3 align-items-center" style="background:#f8fafc;padding:10px 14px;border-radius:12px;border:1px solid #e2e8f0;">' +
                '<span style="font-size:10px;font-weight:800;color:#64748b;margin-right:6px;">FILTER CEPAT:</span>' +
                '<button class="btn btn-xs btn-outline-primary sf-btn" data-range="today" style="font-size:11px;border-radius:8px;padding:3px 10px;">Hari Ini</button>' +
                '<button class="btn btn-xs btn-outline-secondary sf-btn" data-range="yesterday" style="font-size:11px;border-radius:8px;padding:3px 10px;">Kemarin</button>' +
                '<button class="btn btn-xs btn-outline-secondary sf-btn" data-range="7days" style="font-size:11px;border-radius:8px;padding:3px 10px;">7 Hari</button>' +
                '<button class="btn btn-xs btn-outline-secondary sf-btn" data-range="30days" style="font-size:11px;border-radius:8px;padding:3px 10px;">30 Hari</button>' +
                '<button class="btn btn-xs btn-outline-secondary sf-btn" data-range="thismonth" style="font-size:11px;border-radius:8px;padding:3px 10px;">Bulan Ini</button>' +
                '<button class="btn btn-xs btn-outline-secondary sf-btn" data-range="thisyear" style="font-size:11px;border-radius:8px;padding:3px 10px;">Tahun Ini</button>' +
                '<button class="btn btn-xs btn-danger sf-btn" data-range="emergency" style="font-size:11px;border-radius:8px;padding:3px 10px;">Emergency</button>' +
                '<button class="btn btn-xs btn-success sf-btn" data-range="selesai" style="font-size:11px;border-radius:8px;padding:3px 10px;">Selesai</button>' +
                '<button class="btn btn-xs btn-outline-danger sf-btn" data-range="belum" style="font-size:11px;border-radius:8px;padding:3px 10px;">Belum</button>' +
            '</div>';

            targets.forEach(function(el) {
                el.insertAdjacentHTML('beforebegin', filterHtml);
            });

            function getDateRange(range) {
                var today = new Date();
                var fmt = function(d) { return d.toISOString().slice(0,10); };
                switch(range) {
                    case 'today':     return { from: fmt(today), to: fmt(today) };
                    case 'yesterday': var y=new Date(today); y.setDate(y.getDate()-1); return { from: fmt(y), to: fmt(y) };
                    case '7days':     var s=new Date(today); s.setDate(s.getDate()-6); return { from: fmt(s), to: fmt(today) };
                    case '30days':    var s30=new Date(today); s30.setDate(s30.getDate()-29); return { from: fmt(s30), to: fmt(today) };
                    case 'thismonth': return { from: fmt(today).slice(0,7)+'-01', to: fmt(today) };
                    case 'thisyear':  return { from: fmt(today).slice(0,4)+'-01-01', to: fmt(today) };
                    default: return null;
                }
            }

            document.querySelectorAll('.sf-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.sf-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    var range = btn.getAttribute('data-range');

                    // Status filter
                    if (range === 'emergency' || range === 'selesai' || range === 'belum') {
                        var statusMap = { emergency: 'EMERGENCY', selesai: 'SELESAI', belum: 'BELUM' };
                        var curUrl = new URL(window.location.href);
                        if (range === 'emergency') curUrl.searchParams.set('prioritas', 'EMERGENCY');
                        else curUrl.searchParams.set('status', statusMap[range]);
                        window.location.href = curUrl.toString();
                        return;
                    }

                    var dr = getDateRange(range);
                    if (!dr) return;

                    // Try to fill form inputs first
                    var fromInput = document.querySelector('input[name="tanggal_awal"], input[name="start_date"], input[name="from"]');
                    var toInput   = document.querySelector('input[name="tanggal_akhir"], input[name="end_date"], input[name="to"]');
                    if (fromInput && toInput) {
                        fromInput.value = dr.from;
                        toInput.value   = dr.to;
                        var form = fromInput.closest('form');
                        if (form) { form.submit(); return; }
                    }

                    // Fallback: URL params
                    var curUrl2 = new URL(window.location.href);
                    curUrl2.searchParams.set('tanggal_awal', dr.from);
                    curUrl2.searchParams.set('tanggal_akhir', dr.to);
                    window.location.href = curUrl2.toString();
                });
            });
        })();
    </script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
