<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SIDAK TEJO Mobile Mission Control</title>
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

        /* Mission Control Mobile Header */
        .mc-mobile-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 20px 18px 24px 18px;
            border-bottom-left-radius: 24px;
            border-bottom-right-radius: 24px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.25);
        }

        .user-avatar-mobile {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #38bdf8;
            border: 2px solid #f59e0b;
        }

        .mc-m-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            padding: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
            margin-bottom: 16px;
        }

        /* 2x4 Quick Action Grid Mobile */
        .quick-grid-2x4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 18px;
        }
        .quick-btn-m {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 14px 6px;
            text-align: center;
            text-decoration: none;
            color: #1e293b;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }
        .quick-btn-m:active {
            transform: scale(0.94);
            background: #f1f5f9;
        }
        .quick-btn-m i {
            font-size: 22px;
        }
        .quick-btn-m span {
            font-size: 10px;
            font-weight: 700;
        }

        /* KPI Mobile Grid */
        .kpi-mobile-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 18px;
        }
        .kpi-m-box {
            padding: 16px;
            border-radius: 16px;
            color: #ffffff;
        }

        /* Fixed Bottom Nav */
        .bottom-nav-mc {
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
        .bottom-nav-link {
            text-align: center;
            text-decoration: none;
            color: #64748b;
            font-size: 10px;
            font-weight: 600;
            flex: 1;
        }
        .bottom-nav-link.active {
            color: #0284c7;
            font-weight: 800;
        }
        .bottom-nav-link i {
            font-size: 18px;
            display: block;
            margin-bottom: 2px;
        }

        /* Center FAB Button */
        .fab-center-input {
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

        /* Draggable Voice AI Floating Button */
        #voice-ai-draggable {
            position: fixed;
            bottom: 82px;
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
    <div class="mc-mobile-header">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center gap-3">
                <div class="user-avatar-mobile"><i class="fas fa-user-shield"></i></div>
                <div>
                    <?php
                        $hour = (int)date('H');
                        if ($hour >= 0 && $hour < 11) $greeting = '🌅 Selamat Pagi';
                        elseif ($hour >= 11 && $hour < 15) $greeting = '☀️ Selamat Siang';
                        elseif ($hour >= 15 && $hour < 18) $greeting = '🌇 Selamat Sore';
                        else $greeting = '🌙 Selamat Malam';
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
            <span class="text-white-50"><i class="fas fa-clock me-1"></i> <?= date('d M Y') ?> &middot; <strong id="m-clock"><?= date('H:i') ?></strong></span>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill"><i class="fas fa-wifi me-1"></i> Live</span>
        </div>
    </div>

    <div class="px-3 mt-3">

        <!-- 2. KPI CARDS GRID -->
        <div class="kpi-mobile-grid">
            <div class="kpi-m-box bg-primary shadow-sm">
                <small class="text-white-50 fw-bold d-block" style="font-size: 10px;">TOTAL TEMUAN</small>
                <h3 class="fw-bold mb-0 mt-1"><?= number_format($stats['total'] ?? 0) ?></h3>
            </div>
            <div class="kpi-m-box bg-danger shadow-sm">
                <small class="text-white-50 fw-bold d-block" style="font-size: 10px;">EMERGENCY</small>
                <h3 class="fw-bold mb-0 mt-1"><?= number_format($stats['emergency'] ?? 0) ?></h3>
            </div>
            <div class="kpi-m-box bg-dark shadow-sm">
                <small class="text-white-50 fw-bold d-block" style="font-size: 10px;">BELUM SELESAI</small>
                <h3 class="fw-bold mb-0 mt-1"><?= number_format($stats['belum'] ?? 0) ?></h3>
            </div>
            <div class="kpi-m-box bg-success shadow-sm">
                <small class="text-white-50 fw-bold d-block" style="font-size: 10px;">SUDAH SELESAI</small>
                <h3 class="fw-bold mb-0 mt-1"><?= number_format($stats['selesai'] ?? 0) ?></h3>
            </div>
        </div>

        <!-- 3. TARGET HARI INI WIDGET -->
        <div class="mc-m-card">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold text-dark" style="font-size: 12px;"><i class="fas fa-bullseye text-warning me-1"></i> Target Inspeksi Hari Ini</span>
                <span class="badge bg-success font-monospace">75%</span>
            </div>
            <p class="text-muted mb-2" style="font-size: 11px;">Target: 20 &middot; Selesai: 15 &middot; Est. Selesai: 15:30 WIB</p>
            <div class="progress" style="height: 8px;">
                <div class="progress-bar bg-success" style="width: 75%;"></div>
            </div>
        </div>

        <!-- 4. QUICK ACTION GRID 2x4 -->
        <div class="quick-grid-2x4">
            <?php if ($canInput): ?>
            <a href="<?= site_url('temuan/create') ?>" class="quick-btn-m">
                <i class="fas fa-plus-circle text-success"></i><span>Input</span>
            </a>
            <?php endif; ?>
            <a href="<?= site_url('temuan') ?>" class="quick-btn-m">
                <i class="fas fa-list-check text-primary"></i><span>Temuan</span>
            </a>
            <a href="<?= site_url('temuan/update-pekerjaan') ?>" class="quick-btn-m">
                <i class="fas fa-pen-to-square text-warning"></i><span>Update</span>
            </a>
            <a href="<?= site_url('work-orders') ?>" class="quick-btn-m">
                <i class="fas fa-file-invoice text-info"></i><span>WO</span>
            </a>
            <a href="<?= site_url('gis') ?>" class="quick-btn-m">
                <i class="fas fa-map-marked-alt text-success"></i><span>GIS</span>
            </a>
            <a href="<?= site_url('ai-predictive') ?>" class="quick-btn-m">
                <i class="fas fa-brain text-purple"></i><span>AI Risk</span>
            </a>
            <a href="<?= site_url('laporan') ?>" class="quick-btn-m">
                <i class="fas fa-print text-secondary"></i><span>Laporan</span>
            </a>
            <a href="<?= site_url('eviden/kubikel') ?>" class="quick-btn-m">
                <i class="fas fa-folder-open text-purple"></i><span>Eviden</span>
            </a>
        </div>

        <!-- 5. AI RECOMMENDATION DIGEST CARD -->
        <div class="mc-m-card border-start border-4 border-info" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);">
            <div class="d-flex align-items-start gap-2">
                <i class="fas fa-robot text-info fs-4"></i>
                <div>
                    <h6 class="fw-bold text-dark mb-1" style="font-size: 13px;"><i class="fas fa-sparkles text-warning me-1"></i> AI Mission Control Digest</h6>
                    <p class="text-secondary small mb-0" style="font-size: 11px; line-height: 1.4;">
                        Disarankan inspeksi fisik prioritas tinggi pada <strong>ULP Sidoarjo Kota & Penyulang Klurak</strong>.
                    </p>
                </div>
            </div>
        </div>

        <!-- 6. MINI GIS MAP -->
        <div class="mc-m-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-dark mb-0" style="font-size: 13px;"><i class="fas fa-map-marked-alt text-success me-1"></i> Mini GIS Map</h6>
                <a href="<?= site_url('gis') ?>" class="small text-primary font-weight-bold text-decoration-none" style="font-size: 11px;">Full Map &rarr;</a>
            </div>
            <div id="mini-mc-map" style="height: 160px; border-radius: 12px;"></div>
        </div>
    </div>

    <!-- 7. DRAGGABLE FLOATING VOICE AI WIDGET -->
    <div id="voice-ai-draggable" title="Voice AI Assistant">
        <i class="fas fa-microphone"></i>
    </div>

    <!-- 8. FIXED BOTTOM NAVIGATION BAR -->
    <div class="bottom-nav-mc">
        <a href="<?= site_url('dashboard') ?>" class="bottom-nav-link active">
            <i class="fas fa-house"></i><span>Home</span>
        </a>
        <a href="<?= site_url('temuan') ?>" class="bottom-nav-link">
            <i class="fas fa-list-check"></i><span>Temuan</span>
        </a>
        <?php if ($canInput): ?>
        <a href="<?= site_url('temuan/create') ?>" class="fab-center-input">
            <i class="fas fa-plus"></i>
        </a>
        <?php endif; ?>
        <a href="<?= site_url('work-orders') ?>" class="bottom-nav-link">
            <i class="fas fa-file-invoice"></i><span>WO</span>
        </a>
        <a href="<?= site_url('change-password') ?>" class="bottom-nav-link">
            <i class="fas fa-user-circle"></i><span>Profil</span>
        </a>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Leaflet Mini Map
            var mmap = L.map('mini-mc-map', { zoomControl: false }).setView([-7.4478, 112.7183], 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mmap);

            // Draggable Voice AI Floating Widget
            var el = document.getElementById('voice-ai-draggable');
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
