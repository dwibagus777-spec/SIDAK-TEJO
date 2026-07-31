<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SIDAK TEJO Mobile Enterprise Monitoring Center</title>
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
        .emc-mobile-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 20px 18px 24px 18px;
            border-bottom-left-radius: 24px;
            border-bottom-right-radius: 24px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.25);
        }

        .emc-m-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            padding: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
            margin-bottom: 14px;
        }

        /* Quick Action Grid Mobile 2x3 */
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
        }
        .quick-emc-m-item:active { transform: scale(0.94); }
        .quick-emc-m-item i { font-size: 20px; }
        .quick-emc-m-item span { font-size: 10px; font-weight: 700; }

        /* Single Column Mobile KPI Stack */
        .kpi-mobile-stack {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 14px;
        }
        .kpi-m-row {
            padding: 14px 16px;
            border-radius: 16px;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Bottom Nav Mobile */
        .bottom-nav-emc {
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
        .bottom-nav-emc-link {
            text-align: center;
            text-decoration: none;
            color: #64748b;
            font-size: 10px;
            font-weight: 600;
            flex: 1;
        }
        .bottom-nav-emc-link.active { color: #0284c7; font-weight: 800; }
        .bottom-nav-emc-link i { font-size: 18px; display: block; margin-bottom: 2px; }
    </style>
</head>
<body>

    <!-- 1. MOBILE HEADER -->
    <div class="emc-mobile-header">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center gap-3">
                <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="Logo PLN" style="width: 44px; height: 44px; object-fit: contain;" class="bg-white p-1 rounded-circle border border-warning shadow-sm">
                <div>
                    <?php
                        $hour = (int)date('H');
                        if ($hour >= 0 && $hour < 11) $greeting = 'Selamat Pagi';
                        elseif ($hour >= 11 && $hour < 15) $greeting = 'Selamat Siang';
                        elseif ($hour >= 15 && $hour < 18) $greeting = 'Selamat Sore';
                        else $greeting = 'Selamat Malam';
                    ?>
                    <small class="text-white-50 d-block" style="font-size: 11px;"><?= $greeting ?></small>
                    <h6 class="fw-bold mb-0 text-white"><?= esc($userName ?: 'Mas Dwi') ?></h6>
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
            <span class="text-white-50"><i class="fas fa-clock me-1"></i> <?= date('d M Y') ?> &middot; <strong id="m-emc-clock"><?= date('H:i') ?></strong></span>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill"><i class="fas fa-wifi me-1"></i> Live</span>
        </div>
    </div>

    <div class="px-3 mt-3">

        <!-- 2. PERMANENT MOTIVATION BANNER -->
        <div class="emc-m-card border-start border-4 border-warning bg-light">
            <small class="text-muted fw-bold d-block mb-1" style="font-size: 10px;"><i class="fas fa-quote-left me-1 text-warning"></i> MOTIVASI ADMIN</small>
            <p class="fst-italic text-dark small mb-0" id="m-permanent-motivation">
                "Keselamatan Kerja dan Keandalan Pasokan Listrik Sidoarjo Adalah Prioritas Utama Kita Bersama."
            </p>
        </div>

        <!-- 3. TARGET HARIAN PROGRESS BAR -->
        <div class="emc-m-card">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold text-dark" style="font-size: 12px;"><i class="fas fa-bullseye text-warning me-1"></i> Target Harian Pekerjaan</span>
                <span class="badge bg-success">72%</span>
            </div>
            <p class="text-muted mb-2" style="font-size: 11px;">Realisasi: 18 / 25 Pekerjaan Selesai</p>
            <div class="progress" style="height: 8px;"><div class="progress-bar bg-success" style="width: 72%;"></div></div>
        </div>

        <!-- 4. QUICK ACTION GRID 2x3 -->
        <div class="quick-grid-emc-mobile">
            <?php if ($canInput ?? true): ?>
            <a href="<?= site_url('temuan/create') ?>" class="quick-emc-m-item">
                <i class="fas fa-plus-circle text-success"></i><span>Input</span>
            </a>
            <?php endif; ?>
            <a href="<?= site_url('temuan') ?>" class="quick-emc-m-item">
                <i class="fas fa-list-check text-primary"></i><span>Temuan</span>
            </a>
            <a href="<?= site_url('temuan/update-pekerjaan') ?>" class="quick-emc-m-item">
                <i class="fas fa-pen-to-square text-warning"></i><span>Update</span>
            </a>
            <a href="<?= site_url('assets') ?>" class="quick-emc-m-item">
                <i class="fas fa-qrcode text-purple"></i><span>QR Scan</span>
            </a>
            <a href="<?= site_url('ai-copilot') ?>" class="quick-emc-m-item">
                <i class="fas fa-robot text-info"></i><span>Voice AI</span>
            </a>
            <a href="<?= site_url('temuan/terdekat') ?>" class="quick-emc-m-item">
                <i class="fas fa-location-crosshairs text-danger"></i><span>Terdekat</span>
            </a>
        </div>

        <!-- 5. 1-COLUMN KPI STACK MOBILE -->
        <div class="kpi-mobile-stack">
            <div class="kpi-m-row bg-primary shadow-sm">
                <div><small class="text-white-50 font-weight-bold" style="font-size: 10px;">JUMLAH TEMUAN</small><h4 class="fw-bold mb-0"><?= number_format($stats['total'] ?? 0) ?></h4></div>
                <i class="fas fa-search fs-3 opacity-50"></i>
            </div>
            <div class="kpi-m-row bg-danger shadow-sm">
                <div><small class="text-white-50 font-weight-bold" style="font-size: 10px;">EMERGENCY</small><h4 class="fw-bold mb-0"><?= number_format($stats['emergency'] ?? 0) ?></h4></div>
                <i class="fas fa-triangle-exclamation fs-3 opacity-50"></i>
            </div>
            <div class="kpi-m-row bg-warning text-dark shadow-sm">
                <div><small class="text-dark font-weight-bold" style="font-size: 10px;">HIGH PRIORITY</small><h4 class="fw-bold mb-0"><?= number_format($stats['high'] ?? 0) ?></h4></div>
                <i class="fas fa-bolt fs-3 opacity-50"></i>
            </div>
            <div class="kpi-m-row bg-success shadow-sm">
                <div><small class="text-white-50 font-weight-bold" style="font-size: 10px;">SUDAH SELESAI</small><h4 class="fw-bold mb-0"><?= number_format($stats['selesai'] ?? 0) ?></h4></div>
                <i class="fas fa-circle-check fs-3 opacity-50"></i>
            </div>
        </div>

        <!-- 6. MINI MAP MONITORING -->
        <div class="emc-m-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-dark mb-0" style="font-size: 12px;"><i class="fas fa-map-marked-alt text-success me-1"></i> Mini GIS Monitoring Map</h6>
                <a href="<?= site_url('gis') ?>" class="small text-primary font-weight-bold text-decoration-none" style="font-size: 11px;">Full Map &rarr;</a>
            </div>
            <div id="emc-mini-mobile-map" style="height: 160px; border-radius: 12px;"></div>
        </div>

    </div>

    <!-- 7. FIXED BOTTOM NAVIGATION BAR -->
    <div class="bottom-nav-emc">
        <a href="<?= site_url('dashboard') ?>" class="bottom-nav-emc-link active">
            <i class="fas fa-house"></i><span>Home</span>
        </a>
        <a href="<?= site_url('temuan') ?>" class="bottom-nav-emc-link">
            <i class="fas fa-list-check"></i><span>Temuan</span>
        </a>
        <a href="<?= site_url('gis') ?>" class="bottom-nav-emc-link">
            <i class="fas fa-map-marked-alt"></i><span>Map</span>
        </a>
        <a href="<?= site_url('ai-copilot') ?>" class="bottom-nav-emc-link">
            <i class="fas fa-robot"></i><span>AI</span>
        </a>
        <a href="<?= site_url('change-password') ?>" class="bottom-nav-emc-link">
            <i class="fas fa-user-circle"></i><span>Profil</span>
        </a>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var mmap = L.map('emc-mini-mobile-map', { zoomControl: false }).setView([-7.4478, 112.7183], 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mmap);

            var pins = <?= json_encode($mapPins ?? []) ?>;
            pins.forEach(function(p) {
                var lat = parseFloat(p.latitude);
                var lng = parseFloat(p.longitude);
                if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                    var color = '#10b981'; // Hijau
                    if (p.prioritas === 'EMERGENCY') color = '#ef4444'; // Merah
                    else if (p.prioritas === 'HIGH') color = '#f59e0b'; // Kuning

                    var circle = L.circleMarker([lat, lng], {
                        radius: 7, fillColor: color, color: '#fff', weight: 2, fillOpacity: 0.9
                    }).addTo(mmap);

                    circle.on('click', function() {
                        window.location.href = "<?= site_url('temuan/detail/') ?>" + p.id;
                    });
                }
            });

            var savedMotiv = localStorage.getItem('sidak_admin_motivation');
            if (savedMotiv) {
                var motivEl = document.getElementById('m-permanent-motivation');
                if (motivEl) motivEl.innerText = '"' + savedMotiv + '"';
            }
        });
    </script>
</body>
</html>
