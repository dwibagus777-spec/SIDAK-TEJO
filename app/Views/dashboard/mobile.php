<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SIDAK TEJO Mobile Live Operation Center</title>
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

        /* Mobile Header Command Center */
        .oc-mobile-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 20px 18px 24px 18px;
            border-bottom-left-radius: 24px;
            border-bottom-right-radius: 24px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.25);
        }

        .oc-m-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            padding: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
            margin-bottom: 14px;
        }

        /* Blinking Emergency Badge */
        .blink-emergency {
            animation: pulse-red 1.2s infinite;
        }
        @keyframes pulse-red {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        /* Quick Action Grid Mobile */
        .quick-grid-mobile {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 14px;
        }
        .quick-btn-item {
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
        }
        .quick-btn-item:active {
            transform: scale(0.94);
        }
        .quick-btn-item i { font-size: 20px; }
        .quick-btn-item span { font-size: 10px; font-weight: 700; }

        /* Bottom Nav Mobile */
        .bottom-nav-oc {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: 68px;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-around;
            z-index: 1000;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.06);
        }
        .bottom-nav-oc-link {
            text-align: center;
            text-decoration: none;
            color: #64748b;
            font-size: 10px;
            font-weight: 600;
            flex: 1;
        }
        .bottom-nav-oc-link.active {
            color: #0284c7;
            font-weight: 800;
        }
        .bottom-nav-oc-link i { font-size: 18px; display: block; margin-bottom: 2px; }

        /* Voice AI Draggable Button */
        #voice-ai-draggable-mobile {
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

    <!-- 1. HEADER MOBILE -->
    <div class="oc-mobile-header">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white text-dark rounded-circle p-2 fs-5 border border-warning" style="width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-shield-halved text-primary"></i></div>
                <div>
                    <?php
                        $hour = (int)date('H');
                        if ($hour >= 0 && $hour < 11) $greeting = '🌅 Selamat Pagi';
                        elseif ($hour >= 11 && $hour < 15) $greeting = '☀️ Selamat Siang';
                        elseif ($hour >= 15 && $hour < 18) $greeting = '🌇 Selamat Sore';
                        else $greeting = '🌙 Selamat Malam';
                    ?>
                    <small class="text-white-50 d-block" style="font-size: 11px;"><?= $greeting ?></small>
                    <h6 class="fw-bold mb-0 text-white"><?= esc($userName ?: 'Petugas Live Operation') ?></h6>
                </div>
            </div>
            <a href="<?= site_url('notifications') ?>" class="text-white position-relative fs-5">
                <i class="fas fa-bell"></i>
                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
            </a>
        </div>

        <div class="d-flex align-items-center justify-content-between pt-2 border-top border-secondary border-opacity-25" style="font-size: 11px;">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                <i class="fas fa-user-gear me-1"></i> <?= esc(get_role_label($userRole ?? 'inspeksi')) ?>
            </span>
            <span class="text-white-50"><i class="fas fa-clock me-1"></i> <?= date('d M Y') ?> &middot; <strong id="oc-m-clock"><?= date('H:i') ?></strong></span>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill"><i class="fas fa-wifi me-1"></i> Live</span>
        </div>
    </div>

    <div class="px-3 mt-3">

        <!-- 2. AI SUMMARY CARD (FIRST CARD) -->
        <div class="oc-m-card border-start border-4 border-info" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);">
            <div class="d-flex align-items-start gap-2">
                <i class="fas fa-robot text-info fs-4"></i>
                <div>
                    <h6 class="fw-bold text-dark mb-1" style="font-size: 12px;"><i class="fas fa-sparkles text-warning me-1"></i> AI Operation Insight</h6>
                    <p class="text-secondary small mb-0" style="font-size: 11px; line-height: 1.4;">
                        Disarankan inspeksi fisik pada <strong>ULP Sidoarjo Kota & Penyulang Klurak</strong>.
                    </p>
                </div>
            </div>
        </div>

        <!-- 3. EMERGENCY BOARD CARD -->
        <div class="oc-m-card border-start border-4 border-danger bg-danger-subtle text-dark">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-danger mb-1 blink-emergency">🔴 Emergency Aktif</span>
                    <h4 class="fw-bold mb-0 text-danger"><?= number_format($stats['emergency'] ?? 0) ?> <small style="font-size: 12px; color: #1e293b;">Titik Kritis</small></h4>
                </div>
                <a href="<?= site_url('temuan?prioritas=EMERGENCY') ?>" class="btn btn-danger btn-sm rounded-pill px-3 font-weight-bold" style="font-size: 11px;">Tindak Lanjut &rarr;</a>
            </div>
        </div>

        <!-- 4. TARGET HARI INI -->
        <div class="oc-m-card">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold text-dark" style="font-size: 12px;"><i class="fas fa-bullseye text-warning me-1"></i> Target Hari Ini</span>
                <span class="badge bg-success">75%</span>
            </div>
            <p class="text-muted mb-2" style="font-size: 11px;">15 / 20 Selesai &middot; Est. 15:30 WIB</p>
            <div class="progress" style="height: 8px;"><div class="progress-bar bg-success" style="width: 75%;"></div></div>
        </div>

        <!-- 5. QUICK ACTION GRID -->
        <div class="quick-grid-mobile">
            <?php if ($canInput): ?>
            <a href="<?= site_url('temuan/create') ?>" class="quick-btn-item">
                <i class="fas fa-plus-circle text-success"></i><span>Input</span>
            </a>
            <?php endif; ?>
            <a href="<?= site_url('temuan') ?>" class="quick-btn-item">
                <i class="fas fa-list-check text-primary"></i><span>Temuan</span>
            </a>
            <a href="<?= site_url('temuan/update-pekerjaan') ?>" class="quick-btn-item">
                <i class="fas fa-pen-to-square text-warning"></i><span>Update</span>
            </a>
            <a href="<?= site_url('work-orders') ?>" class="quick-btn-item">
                <i class="fas fa-file-invoice text-info"></i><span>WO</span>
            </a>
            <a href="<?= site_url('gis') ?>" class="quick-btn-item">
                <i class="fas fa-map-marked-alt text-success"></i><span>GIS</span>
            </a>
            <a href="<?= site_url('ai-predictive') ?>" class="quick-btn-item">
                <i class="fas fa-brain text-purple"></i><span>AI Risk</span>
            </a>
            <a href="<?= site_url('laporan') ?>" class="quick-btn-item">
                <i class="fas fa-print text-secondary"></i><span>Laporan</span>
            </a>
            <a href="<?= site_url('eviden/kubikel') ?>" class="quick-btn-item">
                <i class="fas fa-folder-open text-purple"></i><span>Eviden</span>
            </a>
        </div>

        <!-- 6. MISSION CONTROL LIVE STATUS PETUGAS -->
        <div class="oc-m-card">
            <h6 class="fw-bold text-dark mb-2" style="font-size: 12px;"><i class="fas fa-radar text-primary me-1"></i> Live Status Petugas</h6>
            <div class="d-flex flex-wrap gap-1" style="font-size: 10px;">
                <span class="badge bg-success-subtle text-success border border-success-subtle">🟢 Online: 14</span>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">🟡 Bekerja: 8</span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">🔵 Input: 3</span>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">🔴 Emergency: <?= number_format($stats['emergency'] ?? 0) ?></span>
            </div>
        </div>

        <!-- 7. TIMELINE RECENT ACTIVITY -->
        <div class="oc-m-card">
            <h6 class="fw-bold text-dark mb-2" style="font-size: 12px;"><i class="fas fa-clock-rotate-left text-info me-1"></i> Timeline Activity Stream</h6>
            <div style="font-size: 11px;" class="text-secondary">
                <div class="mb-1"><strong>08:00</strong> &middot; Input Temuan Baru (STJ-0418)</div>
                <div class="mb-1"><strong>08:10</strong> &middot; Update Work Order (WO-00088)</div>
                <div class="mb-1"><strong>08:20</strong> &middot; Upload Eviden Gardu SDJ-14</div>
            </div>
        </div>

        <!-- 8. MINI GIS MAP -->
        <div class="oc-m-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-dark mb-0" style="font-size: 12px;"><i class="fas fa-map-marked-alt text-success me-1"></i> Live Map Status</h6>
                <a href="<?= site_url('gis') ?>" class="small text-primary font-weight-bold text-decoration-none" style="font-size: 11px;">Full Map &rarr;</a>
            </div>
            <div id="oc-mini-mobile-map" style="height: 160px; border-radius: 12px;"></div>
        </div>

        <!-- 9. NOTIFICATION PANEL -->
        <div class="oc-m-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-dark mb-0" style="font-size: 12px;"><i class="fas fa-bell text-warning me-1"></i> Realtime Notifications</h6>
                <a href="<?= site_url('notifications') ?>" class="small text-primary text-decoration-none" style="font-size: 11px;">Lihat Semua &rarr;</a>
            </div>
            <div style="font-size: 11px;" class="text-muted">
                <i class="fas fa-circle-exclamation text-danger me-1"></i> Emergency baru pada Penyulang Klurak.
            </div>
        </div>

    </div>

    <!-- 10. DRAGGABLE VOICE AI WIDGET -->
    <div id="voice-ai-draggable-mobile" title="Voice Command">
        <i class="fas fa-microphone"></i>
    </div>

    <!-- 11. FIXED BOTTOM NAVIGATION BAR -->
    <div class="bottom-nav-oc">
        <a href="<?= site_url('dashboard') ?>" class="bottom-nav-oc-link active">
            <i class="fas fa-house"></i><span>Home</span>
        </a>
        <a href="<?= site_url('temuan/create') ?>" class="bottom-nav-oc-link">
            <i class="fas fa-plus-circle"></i><span>Input</span>
        </a>
        <a href="<?= site_url('gis') ?>" class="bottom-nav-oc-link">
            <i class="fas fa-map-marked-alt"></i><span>Map</span>
        </a>
        <a href="<?= site_url('ai-predictive') ?>" class="bottom-nav-oc-link">
            <i class="fas fa-brain"></i><span>AI</span>
        </a>
        <a href="<?= site_url('change-password') ?>" class="bottom-nav-oc-link">
            <i class="fas fa-user-circle"></i><span>Profile</span>
        </a>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var mmap = L.map('oc-mini-mobile-map', { zoomControl: false }).setView([-7.4478, 112.7183], 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mmap);

            // Draggable Voice AI
            var el = document.getElementById('voice-ai-draggable-mobile');
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
