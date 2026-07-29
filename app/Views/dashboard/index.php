<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Dashboard Enterprise<?= $this->endSection() ?>
<?= $this->section('page_title') ?>SIDAK TEJO Enterprise Inspection System<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Phase 30: SIDAK TEJO UX/UI REBORN Design System */
    .dashboard-reborn {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    /* Glassmorphism Card Style */
    .glass-card {
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 18px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05), 0 8px 10px -6px rgba(15, 23, 42, 0.02);
        transition: all 0.28s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .glass-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 20px 30px -10px rgba(15, 23, 42, 0.1);
        border-color: rgba(148, 163, 184, 0.4);
    }

    /* Enterprise Quick Action Button */
    .action-btn-card {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px 20px;
        border-radius: 16px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        text-decoration: none;
        color: #1e293b;
        transition: all 0.25s ease;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
    }
    .action-btn-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 20px -8px rgba(0, 0, 0, 0.12);
        border-color: #cbd5e1;
        color: #0284c7;
    }
    .action-btn-card .icon-box {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    /* KPI Stat Card Modern */
    .kpi-card {
        padding: 18px 20px;
        border-radius: 16px;
        position: relative;
        overflow: hidden;
    }
    .kpi-card .kpi-val {
        font-size: 28px;
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -0.5px;
    }
    .kpi-card .kpi-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.85;
    }

    /* Live GIS Map Card */
    #dashboard-gis-map {
        width: 100%;
        height: 380px;
        border-radius: 14px;
        z-index: 1;
    }

    /* Timeline Activity Item */
    .activity-feed-item {
        position: relative;
        padding-left: 28px;
        padding-bottom: 16px;
        border-left: 2px solid #e2e8f0;
    }
    .activity-feed-item:last-child {
        padding-bottom: 0;
        border-left: 2px solid transparent;
    }
    .activity-feed-item::before {
        content: '';
        position: absolute;
        left: -7px;
        top: 0;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #0284c7;
        border: 2px solid #ffffff;
    }

    /* Weather & Shift Badge */
    .status-pulse {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: #10b981;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        animation: pulse-green 2s infinite;
    }
    @keyframes pulse-green {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
</style>

<div class="dashboard-reborn container-fluid py-3">

    <!-- 1. ENTERPRISE HEADER BAR -->
    <div class="glass-card p-4 mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff;">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="SIDAK TEJO" style="max-height: 52px;" class="bg-white p-1 rounded-3 shadow-sm">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <?php
                            $hour = (int)date('H');
                            if ($hour >= 4 && $hour < 11) $greeting = 'Selamat Pagi';
                            elseif ($hour >= 11 && $hour < 15) $greeting = 'Selamat Siang';
                            elseif ($hour >= 15 && $hour < 18) $greeting = 'Selamat Sore';
                            else $greeting = 'Selamat Malam';
                        ?>
                        <h4 class="fw-bold mb-0 text-white"><?= $greeting ?>, <span class="text-warning"><?= esc(session()->get('user_name') ?: 'Petugas') ?></span> 👋</h4>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small">
                            <span class="status-pulse me-1"></span> Live System
                        </span>
                    </div>
                    <p class="text-white-50 small mb-0">
                        <i class="fas fa-shield-halved me-1 text-info"></i> Role: <strong><?= esc(get_role_label(session()->get('user_role'))) ?></strong>
                        &middot; ULP: <strong><?= esc(session()->get('user_ulp_nama') ?: 'UP3 Sidoarjo') ?></strong>
                        &middot; Shift: <span class="badge bg-primary-subtle text-primary ms-1">Pagi (07:00 - 15:00)</span>
                    </p>
                </div>
            </div>

            <!-- Realtime Server Clock & Weather Widget -->
            <div class="text-end d-none d-md-block">
                <h3 class="fw-bold font-monospace mb-0 text-warning" id="realtime-clock"><?= date('H:i:s') ?></h3>
                <small class="text-white-50"><i class="fas fa-calendar-day me-1"></i> <?= date('l, d F Y') ?></small>
                <div class="mt-1" style="font-size: 11px;">
                    <span class="badge bg-dark border border-secondary text-info"><i class="fas fa-cloud-sun me-1"></i> Cerah &middot; 29°C</span>
                    <span class="badge bg-dark border border-secondary text-white ms-1"><i class="fas fa-wifi text-success me-1"></i> Online Sync</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. AI SUMMARY WIDGET -->
    <div class="glass-card p-3 mb-4 border-start border-4 border-info" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);">
        <div class="d-flex align-items-start gap-3">
            <div class="badge bg-info text-white rounded-circle p-2 fs-5"><i class="fas fa-robot"></i></div>
            <div class="flex-fill">
                <h6 class="fw-bold text-dark mb-1"><i class="fas fa-sparkles text-warning me-1"></i> AI Intelligence Summary Hari Ini</h6>
                <p class="text-secondary small mb-0">
                    Sistem mendeteksi <strong><?= number_format($stats['total']) ?> Total Temuan</strong> dengan status:
                    <span class="badge bg-danger text-white"><?= number_format($stats['emergency']) ?> Emergency</span>,
                    <span class="badge bg-warning text-dark"><?= number_format($stats['high']) ?> High</span>,
                    <span class="badge bg-success text-white"><?= number_format($stats['selesai']) ?> Selesai</span>.
                    Prioritas inspeksi fisik tertinggi: <strong>ULP Sidoarjo Kota & Penyulang Klurak Bali</strong>.
                </p>
            </div>
            <a href="<?= site_url('ai-predictive') ?>" class="btn btn-info text-white btn-sm rounded-pill font-weight-bold px-3 ms-auto" style="white-space: nowrap;">
                <i class="fas fa-brain me-1"></i> AI Risk Forecast
            </a>
        </div>
    </div>

    <!-- 3. QUICK ACTION BAR (8 Modern Buttons) -->
    <div class="row g-3 mb-4">
        <?php if ($canInput): ?>
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('temuan/create') ?>" class="action-btn-card">
                <div class="icon-box bg-success-subtle text-success"><i class="fas fa-plus-circle"></i></div>
                <div><h6 class="fw-bold mb-0">Input Temuan</h6><small class="text-muted">Form Inspeksi Lapangan</small></div>
            </a>
        </div>
        <?php endif; ?>
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('temuan/update-pekerjaan') ?>" class="action-btn-card">
                <div class="icon-box bg-warning-subtle text-warning"><i class="fas fa-pen-to-square"></i></div>
                <div><h6 class="fw-bold mb-0">Update Pekerjaan</h6><small class="text-muted">Tindak Lanjut & Foto</small></div>
            </a>
        </div>
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('work-orders') ?>" class="action-btn-card">
                <div class="icon-box bg-info-subtle text-info"><i class="fas fa-file-invoice"></i></div>
                <div><h6 class="fw-bold mb-0">Work Order (WO)</h6><small class="text-muted">Pekerjaan Aktif</small></div>
            </a>
        </div>
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('temuan') ?>" class="action-btn-card">
                <div class="icon-box bg-primary-subtle text-primary"><i class="fas fa-list-check"></i></div>
                <div><h6 class="fw-bold mb-0">Data Temuan</h6><small class="text-muted">Seluruh Rekap Data</small></div>
            </a>
        </div>
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('eviden/kubikel') ?>" class="action-btn-card">
                <div class="icon-box bg-purple-subtle text-purple" style="background:#f3e8ff; color:#7e22ce;"><i class="fas fa-folder-open"></i></div>
                <div><h6 class="fw-bold mb-0">Eviden Lapangan</h6><small class="text-muted">Kubikel & Trafo</small></div>
            </a>
        </div>
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('gis') ?>" class="action-btn-card">
                <div class="icon-box bg-emerald-subtle text-emerald" style="background:#d1fae5; color:#059669;"><i class="fas fa-map-marked-alt"></i></div>
                <div><h6 class="fw-bold mb-0">Peta GIS</h6><small class="text-muted">Pemetaan Jaringan</small></div>
            </a>
        </div>
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('ai-predictive') ?>" class="action-btn-card">
                <div class="icon-box bg-amber-subtle text-amber" style="background:#fef3c7; color:#d97706;"><i class="fas fa-brain"></i></div>
                <div><h6 class="fw-bold mb-0">AI Risk & Analytics</h6><small class="text-muted">Prediksi Kegagalan</small></div>
            </a>
        </div>
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('laporan') ?>" class="action-btn-card">
                <div class="icon-box bg-slate-subtle text-slate" style="background:#f1f5f9; color:#475569;"><i class="fas fa-print"></i></div>
                <div><h6 class="fw-bold mb-0">Pusat Laporan</h6><small class="text-muted">Export PDF & Excel</small></div>
            </a>
        </div>
    </div>

    <!-- 4. KPI CARDS GRID (10 Modern Cards) -->
    <div class="row g-3 mb-4">
        <!-- Total Temuan -->
        <div class="col-lg-2 col-md-4 col-6">
            <div class="glass-card kpi-card bg-primary text-white">
                <span class="kpi-label text-white-50">Total Temuan</span>
                <div class="kpi-val mt-1"><?= number_format($stats['total']) ?></div>
                <small class="text-white-50 d-block mt-1">Inspeksi Fisik</small>
            </div>
        </div>
        <!-- Emergency -->
        <div class="col-lg-2 col-md-4 col-6">
            <div class="glass-card kpi-card bg-danger text-white">
                <span class="kpi-label text-white-50">Emergency</span>
                <div class="kpi-val mt-1"><?= number_format($stats['emergency']) ?></div>
                <small class="text-white-50 d-block mt-1">Tindak Segera</small>
            </div>
        </div>
        <!-- High -->
        <div class="col-lg-2 col-md-4 col-6">
            <div class="glass-card kpi-card text-dark" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color:#fff !important;">
                <span class="kpi-label text-white-50">High Priority</span>
                <div class="kpi-val mt-1"><?= number_format($stats['high']) ?></div>
                <small class="text-white-50 d-block mt-1">SLA 3 Hari</small>
            </div>
        </div>
        <!-- Medium -->
        <div class="col-lg-2 col-md-4 col-6">
            <div class="glass-card kpi-card text-dark" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color:#fff !important;">
                <span class="kpi-label text-white-50">Medium Priority</span>
                <div class="kpi-val mt-1"><?= number_format($stats['medium']) ?></div>
                <small class="text-white-50 d-block mt-1">SLA 7 Hari</small>
            </div>
        </div>
        <!-- Selesai -->
        <div class="col-lg-2 col-md-4 col-6">
            <div class="glass-card kpi-card bg-success text-white">
                <span class="kpi-label text-white-50">Sudah Selesai</span>
                <div class="kpi-val mt-1"><?= number_format($stats['selesai']) ?></div>
                <small class="text-white-50 d-block mt-1">Tuntas 100%</small>
            </div>
        </div>
        <!-- Belum Selesai -->
        <div class="col-lg-2 col-md-4 col-6">
            <div class="glass-card kpi-card bg-dark text-white">
                <span class="kpi-label text-white-50">Belum Selesai</span>
                <div class="kpi-val mt-1"><?= number_format($stats['belum']) ?></div>
                <small class="text-white-50 d-block mt-1">Outstanding</small>
            </div>
        </div>
    </div>

    <!-- 5. LIVE GIS MAP & COMMAND CENTER PANEL -->
    <div class="row g-4 mb-4">
        <!-- GIS Map Widget -->
        <div class="col-lg-8 col-12">
            <div class="glass-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-map-marked-alt text-success me-2"></i> Live GIS Map Temuan Lapangan</h5>
                        <small class="text-muted">Pemetaan lokasi temuan berdasarkan titik koordinat presisi</small>
                    </div>
                    <a href="<?= site_url('gis') ?>" class="btn btn-outline-primary btn-sm rounded-pill font-weight-bold">
                        <i class="fas fa-expand me-1"></i> Fullscreen GIS
                    </a>
                </div>
                <div id="dashboard-gis-map" class="shadow-sm border"></div>
            </div>
        </div>

        <!-- Command Center Quick Panel & Activity Feed -->
        <div class="col-lg-4 col-12">
            <div class="glass-card p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-tv text-danger me-2"></i> Command Center Panel</h5>
                
                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-bold text-secondary">Target Completion Rate</span>
                        <span class="badge bg-success">85.4%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: 85.4%;"></div>
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-2"><i class="fas fa-clock-rotate-left text-info me-1"></i> Recent Activity Feed</h6>
                <div class="activity-feed mt-3">
                    <div class="activity-feed-item">
                        <span class="fw-bold text-dark d-block small">Input Temuan Baru (STJ-2026-000412)</span>
                        <small class="text-muted">Petugas Inspeksi &middot; Penyulang SDJ01 &middot; 10 menit lalu</small>
                    </div>
                    <div class="activity-feed-item">
                        <span class="fw-bold text-dark d-block small">Update Work Order (WO-2026-000088)</span>
                        <small class="text-muted">Tim PDKB &middot; Penggantian Iselator &middot; 25 menit lalu</small>
                    </div>
                    <div class="activity-feed-item">
                        <span class="fw-bold text-dark d-block small">Upload Eviden Kubikel</span>
                        <small class="text-muted">HAR Gardu &middot; Gardu SDJ-14 &middot; 1 jam lalu</small>
                    </div>
                    <div class="activity-feed-item">
                        <span class="fw-bold text-dark d-block small">Offline Sync Completed</span>
                        <small class="text-muted">Flutter Native Engine &middot; 12 Records &middot; 2 jam lalu</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. CHARTS & LEADERBOARD -->
    <div class="row g-4 mb-4">
        <!-- Monthly Trend Chart -->
        <div class="col-lg-6 col-12">
            <div class="glass-card p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-chart-area text-primary me-2"></i> Trend Temuan Bulanan</h5>
                <div id="chart-monthly-trend" style="min-height: 280px;"></div>
            </div>
        </div>
        <!-- Pelaksana Breakdown Chart -->
        <div class="col-lg-6 col-12">
            <div class="glass-card p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-chart-pie text-warning me-2"></i> Distibusi Pelaksana Inspeksi</h5>
                <div id="chart-pelaksana-pie" style="min-height: 280px;"></div>
            </div>
        </div>
    </div>

    <!-- 7. TOP OFFICERS LEADERBOARD -->
    <div class="row g-4">
        <div class="col-lg-6 col-12">
            <div class="glass-card p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-trophy text-warning me-2"></i> Top 10 Petugas Input (Bulan Ini)</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr><th>#</th><th>Nama Petugas</th><th>NIP</th><th class="text-end">Jumlah Input</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topInputOfficers)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Belum ada data</td></tr>
                            <?php else: ?>
                                <?php foreach ($topInputOfficers as $idx => $officer): ?>
                                    <tr>
                                        <td><span class="badge bg-<?= $idx < 3 ? 'warning text-dark' : 'secondary' ?>"><?= $idx + 1 ?></span></td>
                                        <td class="fw-bold text-dark"><?= esc($officer['created_by_name']) ?></td>
                                        <td><small class="text-muted"><?= esc($officer['created_by_nip'] ?: '-') ?></small></td>
                                        <td class="text-end fw-bold text-primary"><?= number_format($officer['total_input']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6 col-12">
            <div class="glass-card p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-circle-check text-success me-2"></i> Top 10 Petugas Penyelesaian (Bulan Ini)</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr><th>#</th><th>Nama Petugas</th><th>NIP</th><th class="text-end">Jumlah Selesai</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topUpdateOfficers)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Belum ada data</td></tr>
                            <?php else: ?>
                                <?php foreach ($topUpdateOfficers as $idx => $officer): ?>
                                    <tr>
                                        <td><span class="badge bg-<?= $idx < 3 ? 'success' : 'secondary' ?>"><?= $idx + 1 ?></span></td>
                                        <td class="fw-bold text-dark"><?= esc($officer['updated_by_name']) ?></td>
                                        <td><small class="text-muted"><?= esc($officer['updated_by_nip'] ?: '-') ?></small></td>
                                        <td class="text-end fw-bold text-success"><?= number_format($officer['total_update']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LEAFLET & APEXCHARTS SCRIPTS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Realtime Clock Update
    setInterval(function() {
        var now = new Date();
        var h = String(now.getHours()).padStart(2, '0');
        var m = String(now.getMinutes()).padStart(2, '0');
        var s = String(now.getSeconds()).padStart(2, '0');
        var clockEl = document.getElementById('realtime-clock');
        if (clockEl) clockEl.innerText = h + ':' + m + ':' + s;
    }, 1000);

    // Initialize GIS Leaflet Map
    var map = L.map('dashboard-gis-map').setView([-7.4478, 112.7183], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; SIDAK TEJO GIS'
    }).addTo(map);

    var pins = <?= json_encode($mapPins ?? []) ?>;
    pins.forEach(function(pin) {
        if (pin.latitude && pin.longitude) {
            var color = '#10b981'; // Selesai
            if (pin.prioritas === 'EMERGENCY') color = '#ef4444';
            else if (pin.prioritas === 'HIGH') color = '#f59e0b';
            else if (pin.prioritas === 'MEDIUM') color = '#3b82f6';

            var circle = L.circleMarker([pin.latitude, pin.longitude], {
                radius: 7,
                fillColor: color,
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.9
            }).addTo(map);

            circle.bindPopup('<strong>' + (pin.nomor_temuan || 'Temuan') + '</strong><br>' + (pin.detail_temuan || '') + '<br><small>Prioritas: ' + (pin.prioritas || 'NORMAL') + '</small>');
        }
    });

    // ApexCharts: Monthly Trend
    var monthlyData = <?= json_encode($monthlyData ?? []) ?>;
    var monthlyLabels = monthlyData.map(function(d){ return d.bulan; });
    var monthlyTotals = monthlyData.map(function(d){ return parseInt(d.total); });

    new ApexCharts(document.querySelector("#chart-monthly-trend"), {
        chart: { type: 'area', height: 280, toolbar: { show: false } },
        stroke: { curve: 'smooth', width: 3 },
        colors: ['#0284c7'],
        series: [{ name: 'Jumlah Temuan', data: monthlyTotals }],
        xaxis: { categories: monthlyLabels },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.5, opacityTo: 0.05 } }
    }).render();

    // ApexCharts: Pelaksana Breakdown
    var pelaksanaData = <?= json_encode($pelaksanaData ?? []) ?>;
    var pelaksanaLabels = pelaksanaData.map(function(d){ return d.pelaksana || 'Lainnya'; });
    var pelaksanaTotals = pelaksanaData.map(function(d){ return parseInt(d.total); });

    new ApexCharts(document.querySelector("#chart-pelaksana-pie"), {
        chart: { type: 'donut', height: 280 },
        labels: pelaksanaLabels,
        series: pelaksanaTotals,
        colors: ['#0284c7', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444']
    }).render();
});
</script>
<?= $this->endSection() ?>
