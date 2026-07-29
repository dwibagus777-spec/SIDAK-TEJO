<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>AI Center: Predictive Maintenance Engine<?= $this->endSection() ?>
<?= $this->section('page_title') ?>AI Predictive Maintenance & Decision Support Center<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Phase 32 AI Predictive Center System */
    .ai-center-container {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .ai-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: 20px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
        transition: all 0.28s ease;
        overflow: hidden;
    }
    .ai-card:hover {
        box-shadow: 0 18px 30px -8px rgba(15, 23, 42, 0.1);
    }
</style>

<div class="ai-center-container container-fluid py-3">

    <!-- 1. TOP HEADER & EXPORT DATASET -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center">
                <i class="fas fa-brain text-warning me-2 fs-3"></i> AI PREDICTIVE MAINTENANCE ENGINE
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">ENTERPRISE V20</span>
            </h3>
            <p class="text-muted small mb-0">Engine Analisis Prediksi Kegagalan Aset, Hotspot & ROW Forecast, Risk Scoring, & ML Dataset Exporter</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-success btn-sm rounded-pill dropdown-toggle font-weight-bold" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-file-export me-1"></i> Export ML Dataset
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                    <li><a class="dropdown-item" href="<?= site_url('ai-predictive/export-dataset?format=csv') ?>"><i class="fas fa-file-csv text-success me-2"></i> Format CSV</a></li>
                    <li><a class="dropdown-item" href="<?= site_url('ai-predictive/export-dataset?format=json') ?>"><i class="fas fa-file-code text-warning me-2"></i> Format JSON</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- AI DATA-DRIVEN DISCLAIMER BANNER -->
    <div class="alert alert-info border-0 rounded-4 shadow-sm mb-4 d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%); color: #0369a1;">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-circle-info fs-4 text-info"></i>
            <span class="small fw-bold">Rekomendasi Berbasis Data - AI Decision Support Engine SIDAK TEJO</span>
        </div>
        <span class="badge bg-info text-white font-monospace">ML Ready</span>
    </div>

    <!-- 2. EXECUTIVE DAILY SUMMARY CARD -->
    <div class="ai-card p-4 mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff;">
        <div class="d-flex align-items-start gap-3">
            <div class="badge bg-warning text-dark rounded-circle p-3 fs-3"><i class="fas fa-robot"></i></div>
            <div class="flex-fill">
                <h5 class="fw-bold text-white mb-1"><i class="fas fa-sparkles text-warning me-1"></i> <?= esc($analytics['executive_summary']['title'] ?? 'Executive Summary AI') ?></h5>
                <p class="text-white-50 small mb-2"><?= esc($analytics['executive_summary']['digest'] ?? 'Sistem AI melakukan pemantauan berkesinambungan seluruh jaringan.') ?></p>
                <div class="d-flex flex-wrap gap-2 text-white-50 small">
                    <span class="badge bg-dark border border-secondary text-info"><i class="fas fa-trophy text-warning me-1"></i> Top Performer: <?= esc($analytics['executive_summary']['top_performer'] ?? 'Team') ?></span>
                    <span class="badge bg-dark border border-secondary text-warning"><i class="fas fa-shield-cat me-1"></i> Mode: Modular ML Ready</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. KPI CARDS GRID -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="ai-card p-3 border-start border-4 border-primary">
                <small class="text-muted fw-bold d-block" style="font-size: 10px;">TOTAL ASET DIPANTAU</small>
                <h3 class="fw-bold mb-0 text-dark mt-1"><?= number_format($analytics['total_assets'] ?? 0) ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="ai-card p-3 border-start border-4 border-danger">
                <small class="text-danger fw-bold d-block" style="font-size: 10px;">RISIKO CRITICAL</small>
                <h3 class="fw-bold mb-0 text-danger mt-1"><?= number_format($analytics['critical_count'] ?? 0) ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="ai-card p-3 border-start border-4 border-warning">
                <small class="text-warning fw-bold d-block" style="font-size: 10px;">RISIKO HIGH</small>
                <h3 class="fw-bold mb-0 text-warning mt-1"><?= number_format($analytics['high_risk_count'] ?? 0) ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="ai-card p-3 border-start border-4 border-info">
                <small class="text-info fw-bold d-block" style="font-size: 10px;">DETEKSI ANOMALI</small>
                <h3 class="fw-bold mb-0 text-info mt-1"><?= number_format(count($analytics['anomalies'] ?? [])) ?></h3>
            </div>
        </div>
    </div>

    <!-- 4. TOP RISK ASSETS RANKING WITH EXPLANATION & CONFIDENCE SCORE -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8 col-12">
            <div class="ai-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-fire-flame-curved text-danger me-2"></i> Top Risk Assets & AI Decision Explanations</h5>
                    <span class="badge bg-danger rounded-pill">Ranked by AI Engine</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                        <thead class="table-light">
                            <tr>
                                <th>Kode / Nama Asset</th>
                                <th>Risk Score</th>
                                <th>Confidence</th>
                                <th>Explanation Reason</th>
                                <th>Recommended Action</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($analytics['top_risk_assets'] ?? []) as $a): ?>
                                <tr>
                                    <td>
                                        <a href="<?= site_url('assets/detail/' . $a['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none d-block">
                                            <?= esc($a['kode_asset']) ?>
                                        </a>
                                        <span class="fw-bold text-dark"><?= esc($a['nama_asset']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $a['badge_class'] ?> font-monospace"><?= number_format($a['risk_score'], 1) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle font-monospace"><?= $a['confidence'] ?>%</span>
                                    </td>
                                    <td><small class="text-muted d-block" style="max-width: 180px;"><?= esc($a['explanation']) ?></small></td>
                                    <td><span class="badge bg-light text-dark border"><i class="fas fa-screwdriver-wrench me-1 text-warning"></i> <?= esc($a['recommendation']) ?></span></td>
                                    <td class="text-center">
                                        <a href="<?= site_url('work-orders/create?asset_id=' . $a['id']) ?>" class="btn btn-xs btn-outline-danger rounded-pill">WO &rarr;</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Hotspot & ROW Predictions per Penyulang -->
        <div class="col-lg-4 col-12">
            <div class="ai-card p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-tree text-success me-2"></i> Prediksi Hotspot & Pemangkasan ROW</h5>
                <?php foreach (($analytics['hotspot_predictions'] ?? []) as $hp): ?>
                    <div class="p-3 border rounded-3 mb-3 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong class="text-dark small"><?= esc($hp['penyulang']) ?></strong>
                            <span class="badge bg-warning text-dark"><?= esc($hp['hotspot_prob']) ?> Hotspot</span>
                        </div>
                        <small class="text-muted d-block mb-1">Status ROW: <strong><?= esc($hp['row_need']) ?></strong></small>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 10px;">
                            <i class="fas fa-hand-holding-hand me-1"></i> Action: <?= esc($hp['action']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 5. GIS RISK HEATMAP & TREND ANALYSIS -->
    <div class="row g-4 mb-4">
        <div class="col-lg-7 col-12">
            <div class="ai-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-map-location-dot text-danger me-2"></i> Spatial Risk Heatmap</h5>
                    <span class="badge bg-outline-danger">Heat Density</span>
                </div>
                <div id="ai-risk-heatmap" style="height: 300px; border-radius: 14px;" class="border shadow-sm"></div>
            </div>
        </div>

        <div class="col-lg-5 col-12">
            <div class="ai-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-chart-line text-primary me-2"></i> Trend Risk Analysis</h5>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary active">1 Minggu</button>
                        <button type="button" class="btn btn-outline-primary">1 Bulan</button>
                    </div>
                </div>
                <div id="ai-trend-chart" style="min-height: 290px;"></div>
            </div>
        </div>
    </div>

</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Leaflet Risk Map
    var map = L.map('ai-risk-heatmap').setView([-7.4478, 112.7183], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    var heatmapData = <?= json_encode($analytics['heatmap_data'] ?? []) ?>;
    heatmapData.forEach(function(pt) {
        L.circleMarker([pt.lat, pt.lng], {
            radius: 9,
            fillColor: pt.weight > 0.7 ? '#ef4444' : (pt.weight > 0.4 ? '#f59e0b' : '#10b981'),
            color: '#ffffff',
            weight: 2,
            fillOpacity: 0.8
        }).addTo(map);
    });

    // ApexCharts Risk Trend
    new ApexCharts(document.querySelector("#ai-trend-chart"), {
        chart: { type: 'area', height: 290, toolbar: { show: false } },
        stroke: { curve: 'smooth', width: 3 },
        colors: ['#ef4444'],
        series: [{ name: 'Intensitas Risiko', data: [12, 15, 10, 18, 14, 9, 21] }],
        xaxis: { categories: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.55, opacityTo: 0.05 } }
    }).render();
});
</script>
<?= $this->endSection() ?>
