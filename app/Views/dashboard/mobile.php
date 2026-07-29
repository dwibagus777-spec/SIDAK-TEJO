<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SIDAK TEJO Mobile Enterprise</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --primary-blue: #0284c7;
            --emerald-green: #10b981;
            --amber-yellow: #f59e0b;
            --crimson-red: #ef4444;
            --slate-dark: #0f172a;
        }

        body {
            background-color: #f8fafc;
            font-family: 'Outfit', sans-serif;
            padding-bottom: 90px;
            -webkit-tap-highlight-color: transparent;
        }

        /* Glassmorphism Header Mobile */
        .mobile-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 20px 18px 26px 18px;
            border-bottom-left-radius: 24px;
            border-bottom-right-radius: 24px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2);
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #38bdf8;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        /* Cards Style */
        .m-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            padding: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
            margin-bottom: 16px;
        }

        /* 4 Quick Action Big Buttons */
        .quick-action-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .quick-action-btn {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 14px 8px;
            text-align: center;
            text-decoration: none;
            color: #1e293b;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }
        .quick-action-btn:active {
            transform: scale(0.95);
            background: #f1f5f9;
        }
        .quick-action-btn i {
            font-size: 22px;
            display: block;
            margin-bottom: 6px;
        }
        .quick-action-btn span {
            font-size: 11px;
            font-weight: 700;
            display: block;
        }

        /* 4 KPI Grid */
        .kpi-mobile-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .kpi-mobile-card {
            padding: 14px 16px;
            border-radius: 16px;
            color: #ffffff;
        }

        /* 2-Column Operational Grid */
        .menu-grid-2col {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        .menu-card-item {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #0f172a;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .menu-card-item:active {
            transform: scale(0.98);
        }

        /* Fixed Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 68px;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-around;
            z-index: 1000;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.06);
        }
        .bottom-nav-item {
            text-align: center;
            text-decoration: none;
            color: #64748b;
            font-size: 10px;
            font-weight: 600;
            flex: 1;
        }
        .bottom-nav-item.active {
            color: #0284c7;
            font-weight: 800;
        }
        .bottom-nav-item i {
            font-size: 18px;
            display: block;
            margin-bottom: 2px;
        }
        /* FAB Input Center Button */
        .fab-center-btn {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 8px 16px -2px rgba(2, 132, 199, 0.4);
            margin-top: -24px;
            border: 3px solid #ffffff;
            text-decoration: none;
        }

        /* Draggable Floating Voice AI Widget */
        #voice-ai-widget {
            position: fixed;
            bottom: 80px;
            right: 16px;
            z-index: 999;
            background: linear-gradient(135deg, #7e22ce 0%, #6b21a8 100%);
            color: #ffffff;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 8px 20px rgba(126, 34, 206, 0.4);
            cursor: grab;
            touch-action: none;
        }
    </style>
</head>
<body>

    <!-- 1. MOBILE HEADER -->
    <div class="mobile-header">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center gap-3">
                <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
                <div>
                    <?php
                        $hour = (int)date('H');
                        if ($hour >= 4 && $hour < 11) $greeting = 'Selamat Pagi';
                        elseif ($hour >= 11 && $hour < 15) $greeting = 'Selamat Siang';
                        elseif ($hour >= 15 && $hour < 18) $greeting = 'Selamat Sore';
                        else $greeting = 'Selamat Malam';
                    ?>
                    <small class="text-white-50 d-block" style="font-size: 11px;"><?= $greeting ?></small>
                    <h6 class="fw-bold mb-0 text-white"><?= esc($userName ?: 'Petugas Inspeksi') ?></h6>
                </div>
            </div>
            <a href="<?= site_url('notifications') ?>" class="text-white position-relative fs-5">
                <i class="fas fa-bell"></i>
                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
            </a>
        </div>

        <div class="d-flex align-items-center justify-content-between pt-2 border-top border-secondary border-opacity-25" style="font-size: 11px;">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                <i class="fas fa-shield-halved me-1"></i> <?= esc(get_role_label($userRole ?? 'inspeksi')) ?>
            </span>
            <span class="text-white-50"><i class="fas fa-clock me-1"></i> <?= date('d M Y') ?> &middot; <strong id="mobile-clock"><?= date('H:i') ?></strong></span>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill"><i class="fas fa-wifi me-1"></i> Online</span>
        </div>
    </div>

    <div class="px-3 mt-3">

        <!-- 2. AI SUMMARY CARD (FIRST CARD) -->
        <div class="m-card border-start border-4 border-info" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);">
            <div class="d-flex align-items-start gap-2">
                <i class="fas fa-robot text-info fs-4"></i>
                <div>
                    <h6 class="fw-bold text-dark mb-1" style="font-size: 13px;"><i class="fas fa-sparkles text-warning me-1"></i> AI Intelligence Digest</h6>
                    <p class="text-secondary small mb-0" style="font-size: 11px; line-height: 1.4;">
                        Inspeksi fisik disarankan prioritas tinggi pada <strong>ULP Sidoarjo Kota</strong>. Terdeteksi potensi hotspot pada Penyulang Klurak.
                    </p>
                </div>
            </div>
        </div>

        <!-- 3. QUICK ACTION 4 BIG BUTTONS -->
        <div class="quick-action-grid">
            <?php if ($canInput): ?>
            <a href="<?= site_url('temuan/create') ?>" class="quick-action-btn">
                <i class="fas fa-plus-circle text-success"></i>
                <span>Input</span>
            </a>
            <?php endif; ?>
            <a href="<?= site_url('temuan') ?>" class="quick-action-btn">
                <i class="fas fa-list-check text-primary"></i>
                <span>Temuan</span>
            </a>
            <a href="<?= site_url('temuan/update-pekerjaan') ?>" class="quick-action-btn">
                <i class="fas fa-pen-to-square text-warning"></i>
                <span>Update</span>
            </a>
            <a href="<?= site_url('work-orders') ?>" class="quick-action-btn">
                <i class="fas fa-file-invoice text-info"></i>
                <span>WO</span>
            </a>
        </div>

        <!-- 4. KPI 4 CARDS -->
        <div class="kpi-mobile-grid">
            <div class="kpi-mobile-card bg-primary shadow-sm">
                <small class="text-white-50 fw-bold d-block" style="font-size: 10px;">TOTAL TEMUAN</small>
                <h3 class="fw-bold mb-0 mt-1">1,248</h3>
            </div>
            <div class="kpi-mobile-card bg-danger shadow-sm">
                <small class="text-white-50 fw-bold d-block" style="font-size: 10px;">EMERGENCY</small>
                <h3 class="fw-bold mb-0 mt-1">101</h3>
            </div>
            <div class="kpi-mobile-card bg-dark shadow-sm">
                <small class="text-white-50 fw-bold d-block" style="font-size: 10px;">BELUM SELESAI</small>
                <h3 class="fw-bold mb-0 mt-1">340</h3>
            </div>
            <div class="kpi-mobile-card bg-success shadow-sm">
                <small class="text-white-50 fw-bold d-block" style="font-size: 10px;">SUDAH SELESAI</small>
                <h3 class="fw-bold mb-0 mt-1">908</h3>
            </div>
        </div>

        <!-- 5. MINI GIS MAP -->
        <div class="m-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-dark mb-0" style="font-size: 13px;"><i class="fas fa-map-marked-alt text-success me-1"></i> Mini GIS Lapangan</h6>
                <a href="<?= site_url('gis') ?>" class="small text-primary font-weight-bold text-decoration-none" style="font-size: 11px;">Buka Map &rarr;</a>
            </div>
            <div id="mini-mobile-map" style="height: 160px; border-radius: 12px;"></div>
        </div>

        <!-- 6. OPERATIONAL MENU GRID (2 Columns) -->
        <h6 class="fw-bold text-secondary mb-2" style="font-size: 11px; letter-spacing: 0.5px;">MENU OPERASIONAL</h6>
        <div class="menu-grid-2col mb-3">
            <a href="<?= site_url('eviden/kubikel') ?>" class="menu-card-item">
                <i class="fas fa-folder-open text-purple fs-4"></i>
                <div><span class="fw-bold d-block" style="font-size: 12px;">Eviden Kubikel</span><small class="text-muted" style="font-size: 10px;">Inspeksi Gardu</small></div>
            </a>
            <a href="<?= site_url('eviden/trafo') ?>" class="menu-card-item">
                <i class="fas fa-bolt text-warning fs-4"></i>
                <div><span class="fw-bold d-block" style="font-size: 12px;">Eviden Trafo</span><small class="text-muted" style="font-size: 10px;">Pengukuran Load</small></div>
            </a>
            <a href="<?= site_url('assets') ?>" class="menu-card-item">
                <i class="fas fa-boxes-stacked text-primary fs-4"></i>
                <div><span class="fw-bold d-block" style="font-size: 12px;">Master Asset</span><small class="text-muted" style="font-size: 10px;">Asset Jaringan</small></div>
            </a>
            <a href="<?= site_url('laporan') ?>" class="menu-card-item">
                <i class="fas fa-print text-success fs-4"></i>
                <div><span class="fw-bold d-block" style="font-size: 12px;">Pusat Laporan</span><small class="text-muted" style="font-size: 10px;">Export Rekap</small></div>
            </a>
        </div>

        <!-- 7. ANALYTICS MENU GRID (2 Columns) -->
        <h6 class="fw-bold text-secondary mb-2" style="font-size: 11px; letter-spacing: 0.5px;">ANALYTICS & COMMAND</h6>
        <div class="menu-grid-2col mb-4">
            <a href="<?= site_url('executive-dashboard') ?>" class="menu-card-item">
                <i class="fas fa-chart-line text-warning fs-4"></i>
                <div><span class="fw-bold d-block" style="font-size: 12px;">Executive Analytics</span><small class="text-muted" style="font-size: 10px;">KPI Dashboard</small></div>
            </a>
            <a href="<?= site_url('ecc') ?>" class="menu-card-item">
                <i class="fas fa-tv text-danger fs-4"></i>
                <div><span class="fw-bold d-block" style="font-size: 12px;">Command Center</span><small class="text-muted" style="font-size: 10px;">TV & Video Wall</small></div>
            </a>
        </div>
    </div>

    <!-- 8. DRAGGABLE FLOATING VOICE AI WIDGET -->
    <div id="voice-ai-widget" title="Voice AI Assistant">
        <i class="fas fa-microphone"></i>
    </div>

    <!-- 9. FIXED BOTTOM NAVIGATION BAR -->
    <div class="bottom-nav">
        <a href="<?= site_url('dashboard') ?>" class="bottom-nav-item active">
            <i class="fas fa-house"></i>
            <span>Home</span>
        </a>
        <a href="<?= site_url('temuan') ?>" class="bottom-nav-item">
            <i class="fas fa-list-check"></i>
            <span>Temuan</span>
        </a>
        <?php if ($canInput): ?>
        <a href="<?= site_url('temuan/create') ?>" class="fab-center-btn">
            <i class="fas fa-plus"></i>
        </a>
        <?php endif; ?>
        <a href="<?= site_url('work-orders') ?>" class="bottom-nav-item">
            <i class="fas fa-file-invoice"></i>
            <span>WO</span>
        </a>
        <a href="<?= site_url('change-password') ?>" class="bottom-nav-item">
            <i class="fas fa-user-circle"></i>
            <span>Profil</span>
        </a>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Leaflet Mini Map
            var mmap = L.map('mini-mobile-map', { zoomControl: false }).setView([-7.4478, 112.7183], 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mmap);

            // Draggable Voice AI Widget
            var el = document.getElementById('voice-ai-widget');
            var isDragging = false, currentX, currentY, initialX, initialY, xOffset = 0, yOffset = 0;

            el.addEventListener('touchstart', dragStart, false);
            el.addEventListener('touchend', dragEnd, false);
            el.addEventListener('touchmove', drag, false);

            function dragStart(e) {
                initialX = e.touches[0].clientX - xOffset;
                initialY = e.touches[0].clientY - yOffset;
                isDragging = true;
            }
            function dragEnd() {
                initialX = currentX;
                initialY = currentY;
                isDragging = false;
            }
            function drag(e) {
                if (isDragging) {
                    e.preventDefault();
                    currentX = e.touches[0].clientX - initialX;
                    currentY = e.touches[0].clientY - initialY;
                    xOffset = currentX;
                    yOffset = currentY;
                    el.style.transform = "translate3d(" + currentX + "px, " + currentY + "px, 0)";
                }
            }
        });
    </script>
</body>
</html>
