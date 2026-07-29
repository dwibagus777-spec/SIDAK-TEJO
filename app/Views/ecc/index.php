<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<!-- ApexCharts & Extra ECC Libraries -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    .ecc-container {
        background-color: #0f172a;
        color: #f8fafc;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 12px 40px rgba(0,0,0,0.5);
    }
    .ecc-card {
        background: rgba(30, 41, 59, 0.85);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 14px;
        padding: 16px;
    }
    .emergency-ticker {
        background: linear-gradient(90deg, #dc2626 0%, #991b1b 100%);
        color: #ffffff;
        border-radius: 12px;
        padding: 12px 18px;
        animation: pulse-border 2s infinite;
    }
</style>

<div class="ecc-container my-2">
    <!-- Top Bar Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-0 text-white d-flex align-items-center" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-tv text-warning me-2 fs-3"></i> EXECUTIVE COMMAND CENTER (ECC)
                <span class="badge bg-danger ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">LIVE VIDEO WALL</span>
            </h3>
            <small class="text-secondary">Monitoring Realtime Pekerjaan Jaringan 20KV Sidoarjo & AI Forecasting</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= site_url('ecc/tv-mode') ?>" class="btn btn-outline-warning btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-desktop me-1"></i> TV Mode 16:9
            </a>
            <button type="button" onclick="toggleEccFullscreen()" class="btn btn-primary btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-expand me-1"></i> Fullscreen
            </button>
        </div>
    </div>

    <!-- Emergency Wall Panel (Target 18) -->
    <?php if (!empty($emergencyWall)): ?>
        <div class="emergency-ticker mb-4 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-triangle-exclamation fs-4 animate__animated animate__flash animate__infinite"></i>
                <div>
                    <strong class="d-block" style="font-size: 14px;">EMERGENCY WALL PANEL (Urgent Actions)</strong>
                    <span class="small opacity-90"><?= count($emergencyWall) ?> temuan kategori Emergency membutuhkan penanganan segera!</span>
                </div>
            </div>
            <a href="<?= site_url('work-orders/create') ?>" class="btn btn-light btn-sm fw-bold text-danger rounded-pill px-3">Terbitkan WO Darurat</a>
        </div>
    <?php endif; ?>

    <!-- KPI Metrics Grid (Target 4) -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="ecc-card border-start border-4 border-primary">
                <span class="text-secondary small font-weight-bold d-block">TOTAL TEMUAN</span>
                <h2 class="fw-bold mb-0 text-white"><?= number_format($metrics['total_temuan']) ?></h2>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="ecc-card border-start border-4 border-warning">
                <div class="text-secondary small font-weight-bold d-block">WO AKTIF</div>
                <h2 class="fw-bold mb-0 text-warning"><?= number_format($metrics['wo_aktif']) ?></h2>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="ecc-card border-start border-4 border-success">
                <div class="text-secondary small font-weight-bold d-block">WO SELESAI</div>
                <h2 class="fw-bold mb-0 text-success"><?= number_format($metrics['wo_selesai']) ?></h2>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="ecc-card border-start border-4 border-danger">
                <div class="text-secondary small font-weight-bold d-block">OVERDUE SLA</div>
                <h2 class="fw-bold mb-0 text-danger"><?= number_format($metrics['wo_overdue']) ?></h2>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- AI Executive Summary & Forecast (Target 19, 20) -->
        <div class="col-lg-8 col-12">
            <div class="ecc-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-warning d-flex align-items-center">
                        <i class="fas fa-brain me-2"></i> <?= esc($aiSummary['title']) ?>
                    </h5>
                    <span class="badge bg-primary">AI Live Engine</span>
                </div>
                <ul class="mb-0 ps-3 text-light" style="font-size: 13px;">
                    <?php foreach ($aiSummary['bullets'] as $b): ?>
                        <li class="mb-1"><?= esc($b) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- ApexCharts Visualizations (Target 5, 8, 9, 10) -->
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="ecc-card">
                        <h6 class="fw-bold text-white mb-2"><i class="fas fa-chart-pie text-info me-1"></i> SLA Compliance</h6>
                        <div id="chart-sla"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ecc-card">
                        <h6 class="fw-bold text-white mb-2"><i class="fas fa-chart-bar text-warning me-1"></i> Distribusi Tim Pelaksana</h6>
                        <div id="chart-pelaksana"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leaderboards & Performance Score 0-100 (Target 13, 23) -->
        <div class="col-lg-4 col-12">
            <div class="ecc-card h-100">
                <h5 class="fw-bold text-white mb-3"><i class="fas fa-trophy text-warning me-2"></i> Ranking ULP & Performance Score</h5>
                <div class="list-group list-group-flush bg-transparent">
                    <?php foreach ($ulpRankings as $idx => $u): ?>
                        <div class="list-group-item bg-transparent text-white px-0 py-2 border-bottom border-secondary d-flex justify-content-between align-items-center" style="font-size: 12px;">
                            <div>
                                <span class="fw-bold text-warning me-2">#<?= $idx + 1 ?></span>
                                <span class="fw-bold text-white"><?= esc($u['nama_ulp']) ?></span>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary font-monospace"><?= number_format($u['performance_score'], 1) ?> pts</span>
                                <small class="text-secondary d-block"><?= $u['total_selesai'] ?> / <?= $u['total_temuan'] ?> Selesai</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    // 1. Chart SLA Compliance
    var optionsSla = {
        series: [<?= $metrics['wo_selesai'] ?>, <?= $metrics['wo_aktif'] ?>, <?= $metrics['wo_overdue'] ?>],
        labels: ['Selesai Tepat Waktu', 'Dalam Proses', 'Overdue SLA'],
        chart: { type: 'donut', height: 220, foreColor: '#ffffff' },
        colors: ['#10b981', '#3b82f6', '#dc2626'],
        legend: { position: 'bottom' }
    };
    var chartSla = new ApexCharts(document.querySelector("#chart-sla"), optionsSla);
    chartSla.render();

    // 2. Chart Tim Pelaksana
    var optionsPelaksana = {
        series: [{ data: [14, 22, 18, 9, 12, 15] }],
        chart: { type: 'bar', height: 220, foreColor: '#ffffff' },
        xaxis: { categories: ['PDKB', 'HAR ROW', 'HAR KONSTRUKSI', 'HAR GARDU', 'HAR CRANE', 'YANTEK'] },
        colors: ['#eab308']
    };
    var chartPelaksana = new ApexCharts(document.querySelector("#chart-pelaksana"), optionsPelaksana);
    chartPelaksana.render();
});

function toggleEccFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
}
</script>
<?= $this->endSection() ?>
