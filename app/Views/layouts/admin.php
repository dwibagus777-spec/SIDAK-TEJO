<?php
$session = session();
$viewMode = $session->get('view_mode');
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
<!DOCTYPE html>
<html lang="id">
<head>
    <script>
        window.addEventListener('error', function(e) {
            var errData = {
                message: e.message,
                filename: e.filename,
                lineno: e.lineno,
                colno: e.colno,
                stack: e.error ? e.error.stack : ''
            };
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?= base_url('log_js_error.php') ?>', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify(errData));
        });
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SIDAK TEJO - Sistem Inspeksi Jaringan Operasi Sidoarjo - Tema Tabler Modern">
    <title>SIDAK TEJO | <?= $this->renderSection('title') ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon_sidak.png') ?>">
    <link rel="shortcut icon" href="<?= base_url('assets/img/favicon_sidak.png') ?>">

    <!-- Google Fonts: Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
            .table-responsive > .table {
                min-width: 750px !important;
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
        <a href="javascript:window.history.back();" class="mobile-back-btn" title="Kembali">
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

                <!-- User Profile info on Mobile menu toggle -->
                <div class="navbar-nav flex-row d-lg-none">
                    <div class="nav-item">
                        <span class="nav-link text-white font-weight-bold p-0 me-2" style="font-size: 0.8rem;">
                            <i class="fas fa-user-circle me-1"></i> <?= esc(session()->get('user_name')) ?>
                        </span>
                    </div>
                </div>

                <!-- Navigation List -->
                <div class="collapse navbar-collapse" id="sidebar-menu">
                    <ul class="navbar-nav pt-lg-3">
                        <!-- Dashboard -->
                        <li class="nav-item <?= url_is('dashboard') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('dashboard') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="nav-icon fas fa-tachometer-alt"></i>
                                </span>
                                <span class="nav-link-title">Dashboard</span>
                            </a>
                        </li>

                        <!-- Input Temuan -->
                        <?php if (check_role(['administrator', 'admin_ulp', 'inspeksi'])): ?>
                        <li class="nav-item <?= url_is('temuan/create') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('temuan/create') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="nav-icon fas fa-plus-circle"></i>
                                </span>
                                <span class="nav-link-title">Input Temuan</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <!-- Data Temuan -->
                        <li class="nav-item <?= (url_is('temuan') && !url_is('temuan/terdekat') && !url_is('temuan/create') && !url_is('temuan/update-pekerjaan') ? 'active' : '') ?>">
                            <a class="nav-link" href="<?= site_url('temuan') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="nav-icon fas fa-list-check"></i>
                                </span>
                                <span class="nav-link-title">Data Temuan</span>
                            </a>
                        </li>

                        <!-- Update Pekerjaan -->
                        <?php if (!check_role(['supervisor_up3'])): ?>
                        <li class="nav-item <?= url_is('temuan/update-pekerjaan') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('temuan/update-pekerjaan') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="nav-icon fas fa-edit text-warning"></i>
                                </span>
                                <span class="nav-link-title">Update Pekerjaan</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <!-- Temuan Terdekat -->
                        <li class="nav-item d-none d-md-block <?= url_is('temuan/terdekat') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('temuan/terdekat') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="nav-icon fas fa-map-marked-alt"></i>
                                </span>
                                <span class="nav-link-title">Temuan Terdekat</span>
                            </a>
                        </li>

                        <!-- Eviden Lapangan Dropdown (Khusus HAR Gardu, PDKB, Admin ULP & Admin) -->
                        <?php if (!check_role(['har_row'])): ?>
                        <li class="nav-item dropdown <?= url_is('eviden*') ? 'show active' : '' ?>">
                            <a class="nav-link dropdown-toggle" href="#menu-eviden" data-bs-toggle="collapse" role="button" aria-expanded="<?= url_is('eviden*') ? 'true' : 'false' ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="nav-icon fas fa-folder-open"></i>
                                </span>
                                <span class="nav-link-title">Eviden Lapangan</span>
                            </a>
                            <div class="collapse <?= url_is('eviden*') ? 'show' : '' ?>" id="menu-eviden">
                                <ul class="navbar-nav">
                                    <li>
                                        <a class="dropdown-item <?= url_is('eviden/kubikel*') ? 'active' : '' ?>" href="<?= site_url('eviden/kubikel') ?>">
                                            Eviden Kubikel
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item <?= url_is('eviden/trafo*') ? 'active' : '' ?>" href="<?= site_url('eviden/trafo') ?>">
                                            Eviden Trafo
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item <?= url_is('eviden/management*') ? 'active' : '' ?>" href="<?= site_url('eviden/management') ?>">
                                            Management Trafo
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <?php endif; ?>

                        <!-- Data Master Dropdown -->
                        <?php if (check_role(['administrator', 'admin_ulp'])): ?>
                        <li class="nav-item dropdown <?= (url_is('ulps*') || url_is('penyulang*') || url_is('sections*') || url_is('users*') || url_is('import*') ? 'show active' : '') ?>">
                            <a class="nav-link dropdown-toggle" href="#menu-master" data-bs-toggle="collapse" role="button" aria-expanded="<?= (url_is('ulps*') || url_is('penyulang*') || url_is('sections*') || url_is('users*') || url_is('import*') ? 'true' : 'false') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="nav-icon fas fa-database"></i>
                                </span>
                                <span class="nav-link-title">Data Master</span>
                            </a>
                            <div class="collapse <?= (url_is('ulps*') || url_is('penyulang*') || url_is('sections*') || url_is('users*') || url_is('import*') ? 'show' : '') ?>" id="menu-master">
                                <ul class="navbar-nav">
                                    <?php if (check_role(['administrator'])): ?>
                                    <li>
                                        <a class="dropdown-item <?= url_is('ulps*') ? 'active' : '' ?>" href="<?= site_url('ulps') ?>">
                                            Data ULP
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <li>
                                        <a class="dropdown-item <?= url_is('penyulang*') ? 'active' : '' ?>" href="<?= site_url('penyulang') ?>">
                                            Data Penyulang
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item <?= url_is('sections*') ? 'active' : '' ?>" href="<?= site_url('sections') ?>">
                                            Data Section
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item <?= url_is('users*') ? 'active' : '' ?>" href="<?= site_url('users') ?>">
                                            Data User
                                        </a>
                                    </li>
                                     <li>
                                         <a class="dropdown-item <?= url_is('setting/announcement*') ? 'active' : '' ?>" href="<?= site_url('setting/announcement') ?>">
                                             <i class="fas fa-bullhorn text-warning me-1"></i> Motivasi Harian
                                         </a>
                                     </li>
                                    <li>
                                        <a class="dropdown-item <?= url_is('import*') ? 'active' : '' ?>" href="<?= site_url('import') ?>">
                                            <i class="fas fa-file-excel me-1" style="color:#1d6f42;"></i> Impor Excel
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <?php endif; ?>

                        <!-- Pusat Laporan Dropdown -->
                        <li class="nav-item dropdown <?= url_is('laporan*') ? 'show active' : '' ?>">
                            <a class="nav-link dropdown-toggle" href="#menu-laporan" data-bs-toggle="collapse" role="button" aria-expanded="<?= url_is('laporan*') ? 'true' : 'false' ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="nav-icon fas fa-print"></i>
                                </span>
                                <span class="nav-link-title">Pusat Laporan</span>
                            </a>
                            <div class="collapse <?= url_is('laporan*') ? 'show' : '' ?>" id="menu-laporan">
                                <ul class="navbar-nav">
                                    <li>
                                        <a class="dropdown-item <?= (url_is('laporan/temuan*') || url_is('laporan') ? 'active' : '') ?>" href="<?= site_url('laporan/temuan') ?>">
                                            Lap. Temuan
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item <?= url_is('laporan/eviden*') ? 'active' : '' ?>" href="<?= site_url('laporan/eviden') ?>">
                                            Lap. Eviden
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item <?= url_is('laporan/management*') ? 'active' : '' ?>" href="<?= site_url('laporan/management') ?>">
                                            Lap. Management Trafo
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <!-- Identifikasi Gangguan -->
                        <li class="nav-item <?= url_is('identifikasi*') ? 'active' : '' ?>">
                            <a class="nav-link" href="<?= site_url('identifikasi') ?>">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <i class="nav-icon fas fa-bolt text-warning"></i>
                                </span>
                                <span class="nav-link-title">Identifikasi Gangguan</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="page-wrapper d-flex flex-column" style="min-height: 100vh;">
            <!-- Top Navbar Header (Desktop Only) -->
            <header class="navbar navbar-expand-md navbar-light d-none d-lg-flex d-print-none navbar-top-wrapper" style="border-bottom: 1px solid rgba(0, 0, 0, 0.08); background-color: #ffffff; padding: 0.6rem 1.5rem;">
                <div class="container-xl justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <!-- Mobile toggle view for desktop -->
                        <a href="<?= site_url('dashboard/toggle-view?t=' . time()) ?>" class="btn btn-outline-primary btn-sm px-2 py-1" style="font-size: 11px; font-weight: 600; border-radius: 4px;">
                            <i class="fas fa-mobile-screen-button me-1"></i> Versi Mobile
                        </a>
                    </div>

                    <!-- RUNNING TICKER MOTIVATIONAL ANNOUNCEMENT (CENTER HEADER) -->
                    <div class="header-ticker-container flex-grow-1 mx-4 d-flex align-items-center" style="max-width: 55%; overflow: hidden; background: linear-gradient(90deg, #004D4F 0%, #007275 100%); border-radius: 20px; padding: 4px 14px; box-shadow: 0 2px 6px rgba(0,77,79,0.15);">
                        <i class="fas fa-bullhorn text-warning me-2 animate__animated animate__pulse animate__infinite" style="font-size: 12px; flex-shrink: 0;"></i>
                        <span class="badge bg-warning text-dark font-weight-bold me-2 px-2" style="font-size: 10px; border-radius: 10px; flex-shrink: 0;">MOTIVASI:</span>
                        <div class="ticker-wrapper flex-grow-1" style="overflow: hidden; white-space: nowrap; position: relative;">
                            <div class="ticker-content d-inline-block font-weight-bold running-announcement-text-target" id="running-announcement-text" style="display: inline-block; padding-left: 100%; animation: tickerAnimation 22s linear infinite; font-size: 12px; color: #ffffff;">
                                <?= esc(get_daily_announcement()) ?>
                            </div>
                        </div>
                    </div>

                    <div class="navbar-nav flex-row align-items-center">
                        <div class="nav-item me-3">
                            <span class="fw-bold text-dark">
                                <i class="fas fa-user-circle me-1" style="color: #005eb8;"></i> <?= esc(session()->get('user_name')) ?> 
                                <span class="badge bg-blue-lt ms-1" style="font-size: 10px; font-weight: 700;"><?= esc(get_role_label(session()->get('user_role'))) ?></span>
                            </span>
                        </div>
                        <div class="nav-item me-2">
                            <a class="btn btn-outline-primary btn-sm px-2 py-1" href="<?= site_url('change-password') ?>" title="Ganti Password Saya" style="font-size: 11px; font-weight: 600; border-radius: 4px;">
                                <i class="fas fa-key me-1"></i> Ganti Password
                            </a>
                        </div>
                        <div class="nav-item">
                            <a class="btn btn-outline-danger btn-icon-only rounded-circle" href="<?= site_url('logout') ?>" title="Keluar" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;">
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
                <div class="container-xl">
                    <strong>Copyright &copy; 2026 <span style="color: #005eb8;">SIDAK TEJO</span>.</strong> All rights reserved.
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
        // Sembunyikan spinner pemuatan setelah halaman selesai dimuat
        $(window).on('load', function() {
            $('#loading-spinner').fadeOut(100);
        });

        // Register Service Worker for caching maps & assets
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?= base_url("service-worker.js") ?>')
                    .then(function(registration) {
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
            bottom: 30px !important;
            right: 18px !important;
            z-index: 999999 !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            pointer-events: auto !important;
            touch-action: manipulation !important;
        }
        #btn-global-mic {
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
            <i class="fas fa-circle-notch fa-spin mr-1"></i> <span id="global-voice-text">Mendengarkan...</span>
        </div>
        
        <!-- Floating Mic Button -->
        <button type="button" id="btn-global-mic" class="btn btn-primary" title="Perintah Suara"
                style="width: 58px; height: 58px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.45rem; border: 2px solid rgba(255,255,255,0.25); box-shadow: 0 6px 18px rgba(0, 0, 0, 0.45) !important; background: linear-gradient(135deg, #005eb8 0%, #003f8a 100%) !important;">
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
                            Swal.fire({
                                icon: 'warning',
                                title: 'Akses Mikrofon Ditolak',
                                text: msg,
                                confirmButtonColor: '#005eb8'
                            });
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
                        
                        setTimeout(function() {
                            $('#global-voice-bubble').addClass('d-none');
                        }, 3500);
                        
                        const voiceEvent = new CustomEvent('appVoiceCommand', {
                            detail: { transcript: resultText },
                            cancelable: true
                        });
                        const isHandled = !window.dispatchEvent(voiceEvent);
                        if (isHandled) {
                            return;
                        }

                        processVoiceCommand(resultText);
                    };
                } catch(err) {
                    console.error('SpeechRecognition init error:', err);
                }
            }

            let lastMicTap = 0;
            $(document).on('click touchstart', '#btn-global-mic', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const now = Date.now();
                if (now - lastMicTap < 400) return;
                lastMicTap = now;

                if (!recognition) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Fitur Perintah Suara',
                        text: 'Peramban Web ini belum mendukung Speech Recognition. Disarankan menggunakan Google Chrome versi terbaru.',
                        confirmButtonColor: '#005eb8'
                    });
                    return;
                }

                if (!isListening) {
                    try {
                        recognition.start();
                    } catch(err) {
                        try {
                            recognition.stop();
                            setTimeout(function() { recognition.start(); }, 150);
                        } catch(ex) {}
                    }
                } else {
                    try {
                        recognition.stop();
                    } catch(err) {}
                }
            });
            
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
                    Swal.fire({
                        icon: 'warning',
                        title: 'Akses Mikrofon Ditolak',
                        text: msg,
                        confirmButtonColor: '#005eb8'
                    });
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
                
                setTimeout(function() {
                    $('#global-voice-bubble').addClass('d-none');
                }, 3500);
                
                const voiceEvent = new CustomEvent('appVoiceCommand', {
                    detail: { transcript: resultText },
                    cancelable: true
                });
                const isHandled = !window.dispatchEvent(voiceEvent);
                if (isHandled) {
                    return;
                }

                processVoiceCommand(resultText);
            };

            // High Precision Voice Command Processor with Toast Feedback
            function processVoiceCommand(text) {
                function showVoiceToast(msg, icon) {
                    Swal.fire({
                        toast: true,
                        position: 'top',
                        icon: icon || 'info',
                        title: '🎤 Perintah Suara',
                        text: '"' + text + '" → ' + msg,
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                }

                text = text.toLowerCase().trim();

                // 1. TEMUAN TERDEKAT / PETA (Matches "terdekat", "peta", "gps")
                if (text.includes('terdekat') || text.includes('peta') || text.includes('gps')) {
                    let penyulangMatch = '';
                    if (text.includes('penyulang')) {
                        let parts = text.split('penyulang');
                        penyulangMatch = parts[1] ? parts[1].trim() : '';
                    }
                    showVoiceToast('Membuka Temuan Terdekat' + (penyulangMatch ? ' (' + penyulangMatch + ')' : ''), 'success');
                    let targetUrl = '<?= site_url("temuan/terdekat") ?>';
                    if (penyulangMatch) {
                        targetUrl += '?penyulang=' + encodeURIComponent(penyulangMatch);
                    } else if (text.includes('gps')) {
                        targetUrl += '?gps=true';
                    }
                    setTimeout(() => window.location.href = targetUrl, 600);
                    return true;
                }

                // 2. FILTER TEMUAN BERDASARKAN PENYULANG (e.g. "penyulang candi", "temuan penyulang klurak")
                if (text.includes('penyulang')) {
                    let parts = text.split('penyulang');
                    let penyulangName = parts[1] ? parts[1].replace(/^(data|tabel|temuan|master)\s*/i, '').trim() : '';
                    if (penyulangName) {
                        showVoiceToast('Menyaring Penyulang: "' + penyulangName + '"...', 'info');
                        setTimeout(() => window.location.href = '<?= site_url("temuan?q=") ?>' + encodeURIComponent(penyulangName), 600);
                        return true;
                    }
                }

                // 3. FILTER TEMUAN BERDASARKAN JENIS (e.g. "jenis row", "jenis hotspot", "jenis konstruksi")
                if (text.includes('jenis')) {
                    let parts = text.split('jenis');
                    let jenisName = parts[1] ? parts[1].replace(/^(temuan|data)\s*/i, '').trim() : '';
                    if (jenisName) {
                        showVoiceToast('Menyaring Jenis Temuan: "' + jenisName + '"...', 'info');
                        setTimeout(() => window.location.href = '<?= site_url("temuan?q=") ?>' + encodeURIComponent(jenisName), 600);
                        return true;
                    }
                }

                // 4. FILTER TEMUAN BERDASARKAN ULP (e.g. "ulp sidoarjo", "ulp porong")
                if (text.includes('ulp') && !text.includes('master ulp') && !text.includes('data ulp')) {
                    let parts = text.split('ulp');
                    let ulpName = parts[1] ? parts[1].trim() : '';
                    if (ulpName) {
                        showVoiceToast('Menyaring ULP: "' + ulpName + '"...', 'info');
                        setTimeout(() => window.location.href = '<?= site_url("temuan?q=") ?>' + encodeURIComponent(ulpName), 600);
                        return true;
                    }
                }

                // 5. INPUT / TAMBAH TEMUAN
                if (text.includes('input') || text.includes('tambah temuan') || text.includes('buat temuan') || text === 'tambah') {
                    showVoiceToast('Membuka Input Temuan Baru', 'success');
                    setTimeout(() => window.location.href = '<?= site_url("temuan/create") ?>', 600);
                    return true;
                }

                // 6. UPDATE PEKERJAAN / PROGRES
                if (text.includes('update') || text.includes('progres') || text.includes('tindak lanjut') || text.includes('pekerjaan')) {
                    showVoiceToast('Membuka Update Pekerjaan', 'success');
                    setTimeout(() => window.location.href = '<?= site_url("temuan/update-pekerjaan") ?>', 600);
                    return true;
                }

                // 7. EVIDEN (KUBIKEL, TRAFO, SAKLAR, MANAGEMENT)
                if (text.includes('kubikel')) {
                    showVoiceToast('Membuka Eviden Kubikel', 'success');
                    setTimeout(() => window.location.href = '<?= site_url("eviden/kubikel") ?>', 600);
                    return true;
                }
                if (text.includes('trafo')) {
                    showVoiceToast('Membuka Eviden Trafo', 'success');
                    setTimeout(() => window.location.href = '<?= site_url("eviden/trafo") ?>', 600);
                    return true;
                }
                if (text.includes('saklar')) {
                    showVoiceToast('Membuka Eviden Saklar', 'success');
                    setTimeout(() => window.location.href = '<?= site_url("eviden/saklar") ?>', 600);
                    return true;
                }
                if (text.includes('management') || text.includes('manajemen')) {
                    showVoiceToast('Membuka Eviden Management', 'success');
                    setTimeout(() => window.location.href = '<?= site_url("eviden/management") ?>', 600);
                    return true;
                }
                if (text.includes('eviden')) {
                    showVoiceToast('Membuka Eviden Lapangan', 'success');
                    setTimeout(() => window.location.href = '<?= site_url("eviden/kubikel") ?>', 600);
                    return true;
                }

                // 8. LAPORAN / REKAP
                if (text.includes('laporan') || text.includes('rekap') || text.includes('pusat laporan')) {
                    showVoiceToast('Membuka Pusat Laporan', 'success');
                    setTimeout(() => window.location.href = '<?= site_url("laporan/temuan") ?>', 600);
                    return true;
                }

                // 9. DASHBOARD / BERANDA
                if (text.includes('dashboard') || text.includes('beranda') || text.includes('home')) {
                    showVoiceToast('Membuka Dashboard', 'success');
                    setTimeout(() => window.location.href = '<?= site_url("dashboard") ?>', 600);
                    return true;
                }

                // 10. MASTER DATA
                if (text.includes('master user') || text.includes('data user') || text.includes('pengguna') || text === 'user') {
                    showVoiceToast('Membuka Master User', 'success');
                    setTimeout(() => window.location.href = '<?= site_url("users") ?>', 600);
                    return true;
                }
                if (text.includes('master ulp') || text.includes('data ulp')) {
                    showVoiceToast('Membuka Master ULP', 'success');
                    setTimeout(() => window.location.href = '<?= site_url("ulps") ?>', 600);
                    return true;
                }
                if (text.includes('master penyulang') || text.includes('data penyulang')) {
                    showVoiceToast('Membuka Master Penyulang', 'success');
                    setTimeout(() => window.location.href = '<?= site_url("penyulang") ?>', 600);
                    return true;
                }
                if (text.includes('master section') || text.includes('data section')) {
                    showVoiceToast('Membuka Master Section', 'success');
                    setTimeout(() => window.location.href = '<?= site_url("sections") ?>', 600);
                    return true;
                }

                // 11. DATA TEMUAN (Without filters)
                if (text === 'temuan' || text === 'data temuan' || text === 'daftar temuan' || text === 'tabel temuan') {
                    showVoiceToast('Membuka Data Temuan', 'success');
                    setTimeout(() => window.location.href = '<?= site_url("temuan") ?>', 600);
                    return true;
                }

                // 12. LOGOUT & UBAH PASSWORD
                if (text.includes('keluar') || text.includes('logout')) {
                    showVoiceToast('Proses Keluar Sistem...', 'warning');
                    setTimeout(() => window.location.href = '<?= site_url("logout") ?>', 600);
                    return true;
                }
                if (text.includes('password') || text.includes('sandi')) {
                    showVoiceToast('Membuka Ubah Password', 'success');
                    setTimeout(() => window.location.href = '<?= site_url("change-password") ?>', 600);
                    return true;
                }

                // 13. PENCARIAN AUTOMATIS (e.g. "cari hotspot", "temukan klurak")
                if (text.startsWith('cari ') || text.startsWith('temukan ')) {
                    let keyword = text.replace('cari', '').replace('temukan', '').trim();
                    if (keyword) {
                        showVoiceToast('Mencari "' + keyword + '"...', 'info');
                        setTimeout(() => window.location.href = '<?= site_url("temuan?q=") ?>' + encodeURIComponent(keyword), 600);
                        return true;
                    }
                }

                // 14. NOTIFIKASI PERINTAH UNTUK SPEECH YANG TIDAK SESUAI (EXACT USER REQUIREMENT)
                showVoiceToast('Perintah kurang jelas. Silahkan berikan perintah dengan format yang sesuai (Penyulang / Jenis Temuan / Terdekat)', 'warning');
                return false;
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
            statusCode: {
                401: function() {
                    Swal.fire({
                        title: 'Sesi Berakhir!',
                        text: 'Sesi login Anda telah habis. Silakan login kembali.',
                        icon: 'warning',
                        confirmButtonText: 'Ke Halaman Login'
                    }).then(() => {
                        window.location.href = '<?= site_url('login') ?>';
                    });
                }
            }
        });
    </script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
