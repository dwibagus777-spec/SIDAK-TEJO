<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Mission Control Executive Dashboard<?= $this->endSection() ?>
<?= $this->section('page_title') ?>SIDAK TEJO Smart Executive Mission Control<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Phase 31.1: Smart Executive Mission Control System Design */
    .mission-control {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    /* Glassmorphism Card System */
    .mc-card {
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: 20px;
        box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05), 0 8px 12px -6px rgba(15, 23, 42, 0.02);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
    }
    .mc-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 22px 35px -10px rgba(15, 23, 42, 0.12);
        border-color: rgba(148, 163, 184, 0.5);
    }

    /* KPI Cards Modern with Ripple Hover */
    .kpi-mc-card {
        padding: 20px;
        border-radius: 18px;
        position: relative;
        text-decoration: none;
        color: inherit;
        display: block;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    .kpi-mc-card:hover {
        transform: translateY(-5px) scale(1.02);
        color: inherit;
    }
    .kpi-mc-card .kpi-number {
        font-size: 32px;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -1px;
    }
    .kpi-mc-card .kpi-title {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        opacity: 0.85;
    }
    .kpi-mc-card .kpi-icon-bg {
        position: absolute;
        right: -10px;
        bottom: -15px;
        font-size: 80px;
        opacity: 0.15;
        transition: all 0.3s ease;
    }
    .kpi-mc-card:hover .kpi-icon-bg {
        transform: scale(1.2) rotate(-8deg);
        opacity: 0.25;
    }

    /* Quick Action Grid Max 2 Rows */
    .quick-grid-2rows {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
    }
    @media (max-width: 1200px) {
        .quick-grid-2rows { grid-template-columns: repeat(4, 1fr); }
    }
    @media (max-width: 768px) {
        .quick-grid-2rows { grid-template-columns: repeat(3, 1fr); }
    }
    .quick-act-item {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 14px 10px;
        text-align: center;
        text-decoration: none;
        color: #0f172a;
        transition: all 0.25s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }
    .quick-act-item:hover {
        transform: translateY(-3px);
        border-color: #0284c7;
        color: #0284c7;
        box-shadow: 0 10px 20px -5px rgba(2, 132, 199, 0.15);
    }
    .quick-act-item i {
        font-size: 22px;
    }
    .quick-act-item span {
        font-size: 11px;
        font-weight: 700;
        line-height: 1.2;
    }

    /* Live Activity Feed Item */
    .live-feed-line {
        position: relative;
        padding-left: 24px;
        padding-bottom: 14px;
        border-left: 2px solid #e2e8f0;
    }
    .live-feed-line:last-child {
        padding-bottom: 0;
        border-left: 2px solid transparent;
    }
    .live-feed-line::before {
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

    /* Status Pulse Animation */
    .status-pulse-live {
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

<div class="mission-control container-fluid py-3">

    <!-- 1. MISSION CONTROL HEADER & REALTIME CLOCK -->
    <div class="mc-card p-4 mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff;">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <!-- User Avatar / Photo -->
                <div class="position-relative">
                    <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="User Avatar" style="width: 56px; height: 56px; object-fit: contain;" class="bg-white p-1 rounded-circle border border-2 border-warning shadow-sm">
                    <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-white rounded-circle"></span>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <?php
                            $hour = (int)date('H');
                            if ($hour >= 0 && $hour < 11) $greeting = '🌅 Selamat Pagi';
                            elseif ($hour >= 11 && $hour < 15) $greeting = '☀️ Selamat Siang';
                            elseif ($hour >= 15 && $hour < 18) $greeting = '🌇 Selamat Sore';
                            else $greeting = '🌙 Selamat Malam';
                        ?>
                        <h4 class="fw-bold mb-0 text-white"><?= $greeting ?>, <span class="text-warning"><?= esc(session()->get('user_name') ?: 'Executive') ?></span></h4>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small">
                            <span class="status-pulse-live me-1"></span> Live System
                        </span>
                    </div>
                    <p class="text-white-50 small mb-0">
                        <i class="fas fa-shield-halved me-1 text-info"></i> Role: <strong><?= esc(get_role_label(session()->get('user_role'))) ?></strong>
                        &middot; ULP: <strong><?= esc(session()->get('user_ulp_nama') ?: 'UP3 Sidoarjo') ?></strong>
                        &middot; Shift: <span class="badge bg-primary-subtle text-primary ms-1">Pagi (07:00 - 15:00)</span>
                    </p>
                </div>
            </div>

            <!-- Realtime Clock & Weather Info -->
            <div class="text-end d-none d-md-block">
                <h2 class="fw-bold font-monospace mb-0 text-warning" id="mc-clock"><?= date('H:i:s') ?></h2>
                <small class="text-white-50"><i class="fas fa-calendar-day me-1"></i> <?= date('l, d F Y') ?></small>
                <div class="mt-1" style="font-size: 11px;">
                    <span class="badge bg-dark border border-secondary text-info me-1"><i class="fas fa-cloud-sun me-1"></i> Cerah &middot; 29°C</span>
                    <span class="badge bg-dark border border-secondary text-success"><i class="fas fa-shield-heart me-1"></i> Aman Inspeksi</span>
                </div>
            </div>
        </div>

        <!-- Motivation & Daily Target Banner -->
        <hr class="border-secondary opacity-25 my-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2" style="font-size: 12px;">
            <div class="text-white-50">
                <i class="fas fa-quote-left me-1 text-warning"></i>
                <span class="fst-italic text-white">"Keselamatan kerja dan keandalan pasokan listrik Sidoarjo adalah prioritas utama kita bersama."</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="text-white-50">Target Hari Ini:</span>
                <span class="fw-bold text-warning">15 / 20 Pekerjaan (75%)</span>
                <div class="progress" style="width: 100px; height: 6px;">
                    <div class="progress-bar bg-warning" style="width: 75%;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. AI MISSION CONTROL RECOMMENDATION PANEL -->
    <div class="mc-card p-3 mb-4 border-start border-4 border-info" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);">
        <div class="d-flex align-items-start gap-3">
            <div class="badge bg-info text-white rounded-circle p-2 fs-4"><i class="fas fa-brain"></i></div>
            <div class="flex-fill">
                <h6 class="fw-bold text-dark mb-1"><i class="fas fa-sparkles text-warning me-1"></i> AI Mission Control Insight</h6>
                <p class="text-secondary small mb-0">
                    Hari ini terdapat <strong><?= number_format($stats['emergency']) ?> pekerjaan Emergency</strong>. Disarankan diselesaikan sebelum pukul 11.00 WIB.
                    <strong>ULP Sidoarjo Kota</strong> memiliki tingkat risiko kegagalan tertinggi pada Penyulang Klurak.
                </p>
            </div>
            <a href="<?= site_url('ai-predictive') ?>" class="btn btn-info text-white btn-sm rounded-pill font-weight-bold px-3 ms-auto" style="white-space: nowrap;">
                <i class="fas fa-arrow-right me-1"></i> Detail AI Risk
            </a>
        </div>
    </div>

    <!-- 3. QUICK KPI CARDS (8 Interactive Cards with Animated Counter Effects) -->
    <div class="row g-3 mb-4">
        <!-- Total Temuan -->
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('temuan') ?>" class="mc-card kpi-mc-card bg-primary text-white">
                <span class="kpi-title text-white-50">Jumlah Temuan</span>
                <div class="kpi-number mt-1 count-up" data-value="<?= $stats['total'] ?>"><?= number_format($stats['total']) ?></div>
                <small class="text-white-50 d-block mt-1">Inspeksi Fisik</small>
                <i class="fas fa-search kpi-icon-bg"></i>
            </a>
        </div>
        <!-- Emergency -->
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('temuan?prioritas=EMERGENCY') ?>" class="mc-card kpi-mc-card bg-danger text-white">
                <span class="kpi-title text-white-50">Emergency</span>
                <div class="kpi-number mt-1 count-up" data-value="<?= $stats['emergency'] ?>"><?= number_format($stats['emergency']) ?></div>
                <small class="text-white-50 d-block mt-1">Tindak Segera</small>
                <i class="fas fa-triangle-exclamation kpi-icon-bg"></i>
            </a>
        </div>
        <!-- High Priority -->
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('temuan?prioritas=HIGH') ?>" class="mc-card kpi-mc-card text-white" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <span class="kpi-title text-white-50">High Priority</span>
                <div class="kpi-number mt-1 count-up" data-value="<?= $stats['high'] ?>"><?= number_format($stats['high']) ?></div>
                <small class="text-white-50 d-block mt-1">SLA 3 Hari</small>
                <i class="fas fa-bolt kpi-icon-bg"></i>
            </a>
        </div>
        <!-- Medium Priority -->
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('temuan?prioritas=MEDIUM') ?>" class="mc-card kpi-mc-card text-white" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                <span class="kpi-title text-white-50">Medium Priority</span>
                <div class="kpi-number mt-1 count-up" data-value="<?= $stats['medium'] ?>"><?= number_format($stats['medium']) ?></div>
                <small class="text-white-50 d-block mt-1">SLA 7 Hari</small>
                <i class="fas fa-circle-info kpi-icon-bg"></i>
            </a>
        </div>
        <!-- Sudah Selesai -->
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('temuan?status=SELESAI') ?>" class="mc-card kpi-mc-card bg-success text-white">
                <span class="kpi-title text-white-50">Sudah Selesai</span>
                <div class="kpi-number mt-1 count-up" data-value="<?= $stats['selesai'] ?>"><?= number_format($stats['selesai']) ?></div>
                <small class="text-white-50 d-block mt-1">Tuntas 100%</small>
                <i class="fas fa-circle-check kpi-icon-bg"></i>
            </a>
        </div>
        <!-- Belum Selesai -->
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('temuan?status=BELUM') ?>" class="mc-card kpi-mc-card bg-dark text-white">
                <span class="kpi-title text-white-50">Belum Selesai</span>
                <div class="kpi-number mt-1 count-up" data-value="<?= $stats['belum'] ?>"><?= number_format($stats['belum']) ?></div>
                <small class="text-white-50 d-block mt-1">Outstanding</small>
                <i class="fas fa-hourglass-half kpi-icon-bg"></i>
            </a>
        </div>
        <!-- WO Aktif -->
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('work-orders') ?>" class="mc-card kpi-mc-card text-white" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                <span class="kpi-title text-white-50">Work Order (WO) Aktif</span>
                <div class="kpi-number mt-1 count-up" data-value="<?= $woStats['aktif'] ?? 0 ?>"><?= number_format($woStats['aktif'] ?? 0) ?></div>
                <small class="text-white-50 d-block mt-1">Status Progress</small>
                <i class="fas fa-file-invoice kpi-icon-bg"></i>
            </a>
        </div>
        <!-- Asset Bermasalah -->
        <div class="col-lg-3 col-md-4 col-6">
            <a href="<?= site_url('assets') ?>" class="mc-card kpi-mc-card text-white" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);">
                <span class="kpi-title text-white-50">Asset Bermasalah</span>
                <div class="kpi-number mt-1 count-up" data-value="<?= $assetStats['bermasalah'] ?? 0 ?>"><?= number_format($assetStats['bermasalah'] ?? 0) ?></div>
                <small class="text-white-50 d-block mt-1">Perlu Maintenance</small>
                <i class="fas fa-boxes-stacked kpi-icon-bg"></i>
            </a>
        </div>
    </div>

    <!-- 4. QUICK ACTION GRID (Max 2 Rows) -->
    <div class="mc-card p-3 mb-4">
        <h6 class="fw-bold text-dark mb-3"><i class="fas fa-grid-2 text-primary me-2"></i> Quick Action Menu</h6>
        <div class="quick-grid-2rows">
            <?php if ($canInput): ?>
            <a href="<?= site_url('temuan/create') ?>" class="quick-act-item">
                <i class="fas fa-plus-circle text-success"></i><span>Input Temuan</span>
            </a>
            <?php endif; ?>
            <a href="<?= site_url('temuan') ?>" class="quick-act-item">
                <i class="fas fa-list-check text-primary"></i><span>Data Temuan</span>
            </a>
            <a href="<?= site_url('temuan/update-pekerjaan') ?>" class="quick-act-item">
                <i class="fas fa-pen-to-square text-warning"></i><span>Update Work</span>
            </a>
            <a href="<?= site_url('work-orders') ?>" class="quick-act-item">
                <i class="fas fa-file-invoice text-info"></i><span>Work Order</span>
            </a>
            <a href="<?= site_url('gis') ?>" class="quick-act-item">
                <i class="fas fa-map-marked-alt text-success"></i><span>Peta GIS</span>
            </a>
            <a href="<?= site_url('ai-predictive') ?>" class="quick-act-item">
                <i class="fas fa-brain text-purple"></i><span>AI Analytics</span>
            </a>
            <a href="<?= site_url('laporan') ?>" class="quick-act-item">
                <i class="fas fa-print text-slate"></i><span>Pusat Laporan</span>
            </a>
            <a href="<?= site_url('assets') ?>" class="quick-act-item">
                <i class="fas fa-boxes-stacked text-amber"></i><span>Master Asset</span>
            </a>
            <a href="<?= site_url('ecc') ?>" class="quick-act-item">
                <i class="fas fa-tv text-danger"></i><span>ECC Control</span>
            </a>
            <a href="<?= site_url('documents') ?>" class="quick-act-item">
                <i class="fas fa-file-contract text-teal"></i><span>Dokumen</span>
            </a>
            <a href="<?= site_url('notifications') ?>" class="quick-act-item">
                <i class="fas fa-bell text-warning"></i><span>Notifikasi</span>
            </a>
            <a href="<?= site_url('integration') ?>" class="quick-act-item">
                <i class="fas fa-network-wired text-indigo"></i><span>Integrasi</span>
            </a>
        </div>
    </div>

    <!-- 5. LIVE GIS MAP & TARGET SLA PANEL -->
    <div class="row g-4 mb-4">
        <!-- Live Mini GIS Map -->
        <div class="col-lg-8 col-12">
            <div class="mc-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-map-marked-alt text-success me-2"></i> Live Mini GIS Map Mission Control</h5>
                        <small class="text-muted">Sebaran titik inspeksi & emergency di wilayah UP3 Sidoarjo</small>
                    </div>
                    <a href="<?= site_url('gis') ?>" class="btn btn-outline-primary btn-sm rounded-pill font-weight-bold">
                        <i class="fas fa-expand me-1"></i> Full GIS Mode
                    </a>
                </div>
                <div id="mc-gis-map" style="height: 360px; border-radius: 14px;" class="border shadow-sm"></div>
            </div>
        </div>

        <!-- Target & SLA Progress Meters -->
        <div class="col-lg-4 col-12">
            <div class="mc-card p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-gauge-high text-info me-2"></i> SLA & Progress Meter</h5>

                <!-- SLA Met vs Overdue -->
                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <span class="small fw-bold text-secondary d-block mb-1">Penyelesaian SLA (Met vs Overdue)</span>
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> SLA Met: 88.5%</span>
                        <span class="badge bg-danger"><i class="fas fa-exclamation me-1"></i> Overdue: 11.5%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: 88.5%;"></div>
                        <div class="progress-bar bg-danger" style="width: 11.5%;"></div>
                    </div>
                </div>

                <!-- Realtime Activity Feed -->
                <h6 class="fw-bold text-dark mb-3"><i class="fas fa-clock-rotate-left text-primary me-1"></i> Live Activity Feed</h6>
                <div class="live-activity-feed">
                    <div class="live-feed-line">
                        <span class="fw-bold text-dark d-block small">Input Temuan (STJ-2026-000418)</span>
                        <small class="text-muted">Petugas Inspeksi &middot; 08:12 WIB</small>
                    </div>
                    <div class="live-feed-line">
                        <span class="fw-bold text-dark d-block small">Update Work Order (WO-00088)</span>
                        <small class="text-muted">Tim PDKB &middot; 08:30 WIB</small>
                    </div>
                    <div class="live-feed-line">
                        <span class="fw-bold text-dark d-block small">Upload Eviden Foto Gardu</span>
                        <small class="text-muted">HAR Gardu &middot; 09:10 WIB</small>
                    </div>
                    <div class="live-feed-line">
                        <span class="fw-bold text-dark d-block small">Complete Job Inspection</span>
                        <small class="text-muted">Supervisor ULP &middot; 09:15 WIB</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. TREND HARIAN APEXCHARTS & STATUS PIE CHART -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8 col-12">
            <div class="mc-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-chart-area text-primary me-2"></i> Trend Inspeksi & Penyelesaian</h5>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary active">7 Hari</button>
                        <button type="button" class="btn btn-outline-primary">30 Hari</button>
                        <button type="button" class="btn btn-outline-primary">12 Bulan</button>
                    </div>
                </div>
                <div id="mc-trend-chart" style="min-height: 290px;"></div>
            </div>
        </div>

        <div class="col-lg-4 col-12">
            <div class="mc-card p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-chart-pie text-warning me-2"></i> Status Pekerjaan</h5>
                <div id="mc-status-donut" style="min-height: 290px;"></div>
            </div>
        </div>
    </div>

    <!-- 7. TOP OFFICERS LEADERBOARD -->
    <div class="row g-4">
        <div class="col-lg-6 col-12">
            <div class="mc-card p-4">
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
                                        <td>
                                            <?php if ($idx === 0): ?>🥇
                                            <?php elseif ($idx === 1): ?>🥈
                                            <?php elseif ($idx === 2): ?>🥉
                                            <?php else: ?><span class="badge bg-secondary"><?= $idx + 1 ?></span>
                                            <?php endif; ?>
                                        </td>
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
            <div class="mc-card p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-circle-check text-success me-2"></i> Top 10 Petugas Penyelesaian</h5>
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
                                        <td>
                                            <?php if ($idx === 0): ?>🥇
                                            <?php elseif ($idx === 1): ?>🥈
                                            <?php elseif ($idx === 2): ?>🥉
                                            <?php else: ?><span class="badge bg-secondary"><?= $idx + 1 ?></span>
                                            <?php endif; ?>
                                        </td>
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

<!-- LEAFLET & APEXCHARTS LIBRARIES -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Realtime Server Clock Update
    setInterval(function() {
        var now = new Date();
        var h = String(now.getHours()).padStart(2, '0');
        var m = String(now.getMinutes()).padStart(2, '0');
        var s = String(now.getSeconds()).padStart(2, '0');
        var clockEl = document.getElementById('mc-clock');
        if (clockEl) clockEl.innerText = h + ':' + m + ':' + s;
    }, 1000);

    // Leaflet Mini GIS Map Initializer
    var map = L.map('mc-gis-map').setView([-7.4478, 112.7183], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; SIDAK TEJO Mission Control'
    }).addTo(map);

    var mapPins = <?= json_encode($mapPins ?? []) ?>;
    mapPins.forEach(function(pin) {
        if (pin.latitude && pin.longitude) {
            var color = '#10b981';
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

    // ApexCharts Trend Area
    var monthlyData = <?= json_encode($monthlyData ?? []) ?>;
    var monthlyLabels = monthlyData.map(function(d){ return d.bulan; });
    var monthlyTotals = monthlyData.map(function(d){ return parseInt(d.total); });

    new ApexCharts(document.querySelector("#mc-trend-chart"), {
        chart: { type: 'area', height: 290, toolbar: { show: false } },
        stroke: { curve: 'smooth', width: 3 },
        colors: ['#0284c7'],
        series: [{ name: 'Jumlah Temuan', data: monthlyTotals }],
        xaxis: { categories: monthlyLabels },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.55, opacityTo: 0.05 } }
    }).render();

    // ApexCharts Status Donut
    new ApexCharts(document.querySelector("#mc-status-donut"), {
        chart: { type: 'donut', height: 290 },
        labels: ['Selesai', 'Belum Selesai', 'Work Order Aktif'],
        series: [
            <?= (int)($stats['selesai'] ?? 0) ?>,
            <?= (int)($stats['belum'] ?? 0) ?>,
            <?= (int)($woStats['aktif'] ?? 0) ?>
        ],
        colors: ['#10b981', '#0f172a', '#0284c7']
    }).render();
});
</script>
<?= $this->endSection() ?>
