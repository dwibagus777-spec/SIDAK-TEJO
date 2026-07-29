<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Enterprise Monitoring Center PLN<?= $this->endSection() ?>
<?= $this->section('page_title') ?>SIDAK TEJO Enterprise Monitoring Center PLN<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Phase 37 Enterprise Monitoring Center PLN System */
    .emc-container {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .emc-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: 20px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
        transition: all 0.28s ease;
        overflow: hidden;
    }
    .emc-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 18px 30px -8px rgba(15, 23, 42, 0.1);
    }

    /* KPI Modern Cards */
    .kpi-emc-card {
        padding: 20px;
        border-radius: 18px;
        color: #ffffff;
        position: relative;
        overflow: hidden;
    }
    .kpi-emc-val {
        font-size: 32px;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -1px;
    }
    .kpi-emc-lbl {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        opacity: 0.85;
    }

    /* Floating Quick Action */
    .quick-action-bar-emc {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
    }
    .quick-emc-btn {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 10px 16px;
        font-size: 12px;
        font-weight: 700;
        color: #0f172a;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        transition: all 0.25s ease;
    }
    .quick-emc-btn:hover {
        transform: translateY(-2px);
        border-color: #0284c7;
        color: #0284c7;
    }

    /* Activity Stream Line */
    .activity-stream-item {
        position: relative;
        padding-left: 24px;
        padding-bottom: 14px;
        border-left: 2px solid #e2e8f0;
    }
    .activity-stream-item:last-child {
        padding-bottom: 0;
        border-left: 2px solid transparent;
    }
    .activity-stream-item::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 2px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #0284c7;
        border: 2px solid #ffffff;
    }
</style>

<div class="emc-container container-fluid py-3">

    <!-- 1. ENTERPRISE MONITORING CENTER HEADER -->
    <div class="emc-card p-4 mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff;">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="Logo PLN SIDAK TEJO" style="max-height: 52px;" class="bg-white p-1 rounded-3 shadow-sm">
                <div>
                    <?php
                        $hour = (int)date('H');
                        if ($hour >= 0 && $hour < 11) $greeting = 'Selamat Pagi';
                        elseif ($hour >= 11 && $hour < 15) $greeting = 'Selamat Siang';
                        elseif ($hour >= 15 && $hour < 18) $greeting = 'Selamat Sore';
                        else $greeting = 'Selamat Malam';
                    ?>
                    <h4 class="fw-bold mb-1 text-white">
                        <?= $greeting ?>, <span class="text-warning"><?= esc(session()->get('user_name') ?: 'Mas Dwi') ?></span> 👋
                    </h4>
                    <p class="text-white-50 small mb-0">
                        Hari ini ada <strong>18 pekerjaan aktif</strong> &middot; ULP: <strong><?= esc(session()->get('user_ulp_nama') ?: 'UP3 Sidoarjo') ?></strong> &middot; Role: <strong><?= esc(get_role_label(session()->get('user_role'))) ?></strong>
                    </p>
                </div>
            </div>

            <!-- Server Time & Date -->
            <div class="text-end d-none d-md-block">
                <h3 class="fw-bold font-monospace mb-0 text-warning" id="emc-clock"><?= date('H:i:s') ?> WIB</h3>
                <small class="text-white-50"><i class="fas fa-calendar-day me-1"></i> <?= date('l, d F Y') ?></small>
            </div>
        </div>

        <!-- Permanent Admin Motivation Quote Banner -->
        <hr class="border-secondary opacity-25 my-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2" style="font-size: 12px;">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-quote-left text-warning fs-5"></i>
                <span id="permanent-motivation-text" class="fst-italic text-white">"Keselamatan Kerja dan Keandalan Pasokan Listrik Sidoarjo Adalah Prioritas Utama Kita Bersama."</span>
                <button type="button" class="btn btn-xs btn-outline-light rounded-circle ms-2" onclick="editMotivation()" title="Edit Motivasi Admin"><i class="fas fa-pencil"></i></button>
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">
                <span class="status-pulse-live me-1"></span> Live Monitoring Center PLN
            </span>
        </div>
    </div>

    <!-- 2. QUICK ACTION BAR -->
    <div class="quick-action-bar-emc">
        <?php if ($canInput ?? true): ?>
        <a href="<?= site_url('temuan/create') ?>" class="quick-emc-btn">
            <i class="fas fa-plus-circle text-success fs-5"></i> Input Temuan
        </a>
        <?php endif; ?>
        <a href="<?= site_url('temuan') ?>" class="quick-emc-btn">
            <i class="fas fa-list-check text-primary fs-5"></i> Data Temuan
        </a>
        <a href="<?= site_url('temuan/update-pekerjaan') ?>" class="quick-emc-btn">
            <i class="fas fa-pen-to-square text-warning fs-5"></i> Update Pekerjaan
        </a>
        <a href="<?= site_url('assets') ?>" class="quick-emc-btn">
            <i class="fas fa-qrcode text-purple fs-5"></i> QR Scanner
        </a>
        <a href="<?= site_url('ai-copilot') ?>" class="quick-emc-btn">
            <i class="fas fa-robot text-info fs-5"></i> Voice AI
        </a>
        <a href="<?= site_url('temuan/terdekat') ?>" class="quick-emc-btn">
            <i class="fas fa-location-crosshairs text-danger fs-5"></i> Lokasi Terdekat
        </a>
    </div>

    <!-- 3. KPI CARDS GRID (Desktop: 4 Cols, Tablet: 2 Cols, Mobile: 1 Col) -->
    <div class="row g-3 mb-4">
        <!-- Jumlah Temuan -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="emc-card kpi-emc-card bg-primary">
                <span class="kpi-emc-lbl">Jumlah Temuan</span>
                <div class="kpi-emc-val mt-1" id="kpi-total-temuan"><?= number_format($stats['total'] ?? 0) ?></div>
                <small class="text-white-50 d-block mt-1">Total Inspeksi Fisik</small>
            </div>
        </div>
        <!-- Emergency -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="emc-card kpi-emc-card bg-danger">
                <span class="kpi-emc-lbl">Emergency</span>
                <div class="kpi-emc-val mt-1" id="kpi-emergency"><?= number_format($stats['emergency'] ?? 0) ?></div>
                <small class="text-white-50 d-block mt-1">Tindak Lanjut Darurat</small>
            </div>
        </div>
        <!-- High Priority -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="emc-card kpi-emc-card text-white" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <span class="kpi-emc-lbl">High Priority</span>
                <div class="kpi-emc-val mt-1" id="kpi-high"><?= number_format($stats['high'] ?? 0) ?></div>
                <small class="text-white-50 d-block mt-1">SLA 7 Hari</small>
            </div>
        </div>
        <!-- Medium Priority -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="emc-card kpi-emc-card text-white" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                <span class="kpi-emc-lbl">Medium Priority</span>
                <div class="kpi-emc-val mt-1" id="kpi-medium"><?= number_format($stats['medium'] ?? 0) ?></div>
                <small class="text-white-50 d-block mt-1">SLA 31 Hari</small>
            </div>
        </div>
        <!-- Belum Selesai -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="emc-card kpi-emc-card bg-dark">
                <span class="kpi-emc-lbl">Belum Selesai</span>
                <div class="kpi-emc-val mt-1" id="kpi-belum"><?= number_format($stats['belum'] ?? 0) ?></div>
                <small class="text-white-50 d-block mt-1">Outstanding</small>
            </div>
        </div>
        <!-- Proses -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="emc-card kpi-emc-card text-white" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                <span class="kpi-emc-lbl">Dalam Proses</span>
                <div class="kpi-emc-val mt-1"><?= number_format($woStats['aktif'] ?? 0) ?></div>
                <small class="text-white-50 d-block mt-1">Status Progress</small>
            </div>
        </div>
        <!-- Sudah Selesai -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="emc-card kpi-emc-card bg-success">
                <span class="kpi-emc-lbl">Sudah Selesai</span>
                <div class="kpi-emc-val mt-1" id="kpi-selesai"><?= number_format($stats['selesai'] ?? 0) ?></div>
                <small class="text-white-50 d-block mt-1">Tuntas 100%</small>
            </div>
        </div>
        <!-- Target Harian -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="emc-card p-3 bg-white text-dark border border-2 border-primary">
                <span class="small fw-bold text-muted d-block">TARGET HARIAN</span>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <h3 class="fw-bold mb-0 text-primary">18 / 25</h3>
                    <span class="badge bg-success">72%</span>
                </div>
                <div class="progress mt-2" style="height: 6px;">
                    <div class="progress-bar bg-success" style="width: 72%;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. MINI GIS MAP & REALTIME STATUS STREAM -->
    <div class="row g-4 mb-4">
        <!-- Mini GIS Map -->
        <div class="col-lg-8 col-12">
            <div class="emc-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-map-marked-alt text-success me-2"></i> Mini GIS Monitoring Map</h5>
                    <a href="<?= site_url('gis') ?>" class="btn btn-outline-primary btn-sm rounded-pill font-weight-bold">
                        <i class="fas fa-expand me-1"></i> Full Map Mode
                    </a>
                </div>
                <div id="emc-mini-map" style="height: 320px; border-radius: 14px;" class="border shadow-sm"></div>
            </div>
        </div>

        <!-- Realtime Status Stream Panel -->
        <div class="col-lg-4 col-12">
            <div class="emc-card p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-stream text-primary me-2"></i> Realtime Activity Stream</h5>
                
                <div class="activity-stream-item">
                    <span class="fw-bold text-dark d-block small">User Login: Dwi Bagus Arianto</span>
                    <small class="text-muted">Role: Administrator &middot; 08:40 WIB</small>
                </div>
                <div class="activity-stream-item">
                    <span class="fw-bold text-dark d-block small">Input Temuan (STJ-2026-000422)</span>
                    <small class="text-muted">Petugas Inspeksi &middot; 08:42 WIB</small>
                </div>
                <div class="activity-stream-item">
                    <span class="fw-bold text-dark d-block small">Update Pekerjaan Work Order</span>
                    <small class="text-muted">Tim PDKB UP3 &middot; 08:45 WIB</small>
                </div>
                <div class="activity-stream-item">
                    <span class="fw-bold text-dark d-block small">Upload Eviden Foto Gardu SDJ-14</span>
                    <small class="text-muted">HAR Gardu &middot; 08:48 WIB</small>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. SLA MONITOR WIDGET -->
    <div class="emc-card p-4 mb-4">
        <h5 class="fw-bold text-dark mb-3"><i class="fas fa-clock text-warning me-2"></i> SLA Monitoring Widget</h5>
        <div class="row g-3 text-center">
            <div class="col-md-2 col-6">
                <div class="p-3 bg-danger-subtle rounded-3 border border-danger-subtle">
                    <small class="text-danger fw-bold d-block" style="font-size: 10px;">EMERGENCY (SLA 3 Hari)</small>
                    <h4 class="fw-bold text-danger mb-0"><?= number_format($stats['emergency'] ?? 0) ?></h4>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="p-3 bg-warning-subtle rounded-3 border border-warning-subtle">
                    <small class="text-warning fw-bold d-block" style="font-size: 10px;">HIGH (SLA 7 Hari)</small>
                    <h4 class="fw-bold text-warning mb-0"><?= number_format($stats['high'] ?? 0) ?></h4>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="p-3 bg-primary-subtle rounded-3 border border-primary-subtle">
                    <small class="text-primary fw-bold d-block" style="font-size: 10px;">MEDIUM (SLA 31 Hari)</small>
                    <h4 class="fw-bold text-primary mb-0"><?= number_format($stats['medium'] ?? 0) ?></h4>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-amber-subtle rounded-3 border border-warning">
                    <small class="text-warning fw-bold d-block" style="font-size: 10px;">SLA HAMPIR HABIS (< 24 Jam)</small>
                    <h4 class="fw-bold text-warning mb-0">3 Pekerjaan</h4>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-danger-subtle rounded-3 border border-danger">
                    <small class="text-danger fw-bold d-block" style="font-size: 10px;">SLA MELEWATI (OVERDUE)</small>
                    <h4 class="fw-bold text-danger mb-0">1 Pekerjaan</h4>
                </div>
            </div>
        </div>
    </div>

</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Realtime Clock
    setInterval(function() {
        var now = new Date();
        var h = String(now.getHours()).padStart(2, '0');
        var m = String(now.getMinutes()).padStart(2, '0');
        var s = String(now.getSeconds()).padStart(2, '0');
        var clockEl = document.getElementById('emc-clock');
        if (clockEl) clockEl.innerText = h + ':' + m + ':' + s + ' WIB';
    }, 1000);

    // Permanent Motivation Text
    var savedMotiv = localStorage.getItem('sidak_admin_motivation');
    if (savedMotiv) {
        var motivEl = document.getElementById('permanent-motivation-text');
        if (motivEl) motivEl.innerText = '"' + savedMotiv + '"';
    }

    window.editMotivation = function() {
        var input = prompt("Masukkan Kata Motivasi Admin Baru (Disimpan Permanen):");
        if (input && input.trim()) {
            localStorage.setItem('sidak_admin_motivation', input.trim());
            var motivEl = document.getElementById('permanent-motivation-text');
            if (motivEl) motivEl.innerText = '"' + input.trim() + '"';
        }
    };

    // Mini GIS Map
    var map = L.map('emc-mini-map').setView([-7.4478, 112.7183], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    var pins = <?= json_encode($mapPins ?? []) ?>;
    pins.forEach(function(p) {
        if (p.latitude && p.longitude) {
            var color = '#10b981'; // Hijau
            if (p.prioritas === 'EMERGENCY') color = '#ef4444'; // Merah
            else if (p.prioritas === 'HIGH') color = '#f59e0b'; // Kuning

            var circle = L.circleMarker([p.latitude, p.longitude], {
                radius: 7, fillColor: color, color: '#fff', weight: 2, fillOpacity: 0.9
            }).addTo(map);

            circle.on('click', function() {
                window.location.href = "<?= site_url('temuan/detail/') ?>" + p.id;
            });
        }
    });
});
</script>
<?= $this->endSection() ?>
