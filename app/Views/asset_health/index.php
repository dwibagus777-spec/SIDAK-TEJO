<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Asset Health Index & Predictive Maintenance<?= $this->endSection() ?>
<?= $this->section('page_title') ?>SIDAK TEJO Asset Health Index & Predictive Maintenance Hub<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Phase 40 Asset Health Index Design System */
    .ah-container {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .ah-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: 20px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
        transition: all 0.28s ease;
    }

    .health-score-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 18px;
        color: #ffffff;
    }
</style>

<div class="ah-container container-fluid py-3">

    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center">
                <i class="fas fa-heart-pulse text-danger me-2 fs-3"></i> ASSET HEALTH INDEX & PREDICTIVE MAINTENANCE
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">ENTERPRISE V26</span>
            </h3>
            <p class="text-muted small mb-0">Health Index 0-100, AI Failure Probabilities (Trip, OCR, DGR), Risk Leaderboards, & Trend Forecasting</p>
        </div>
    </div>

    <!-- Health Categories Legend Bar -->
    <div class="ah-card p-3 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2" style="font-size: 11px;">
            <span class="fw-bold text-dark"><i class="fas fa-layer-group text-primary me-1"></i> Kategori Health Score:</span>
            <span class="badge" style="background:#10b981;">90-100: Sangat Baik</span>
            <span class="badge" style="background:#84cc16;">75-89: Baik</span>
            <span class="badge" style="background:#f59e0b; color:#000;">60-74: Perlu Monitoring</span>
            <span class="badge" style="background:#f97316;">40-59: Kurang Baik</span>
            <span class="badge" style="background:#ef4444;">0-39: Kritis</span>
        </div>
    </div>

    <!-- TOP 10 PENYULANG BERISIKO LEADERBOARD -->
    <div class="row g-4 mb-4">
        <div class="col-lg-7 col-12">
            <div class="ah-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-ranking-star text-warning me-2"></i> Top 10 Penyulang Berisiko Tinggi</h5>
                    <span class="badge bg-danger rounded-pill">Priority Inspection Target</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Nama Penyulang</th>
                                <th>Health Score</th>
                                <th>Status AI</th>
                                <th>Temuan Aktif</th>
                                <th>Prob. Trip</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($analytics['top_penyulang'])): ?>
                                <tr><td colspan="7" class="text-center py-3 text-muted">Belum ada data penyulang.</td></tr>
                            <?php else: ?>
                                <?php foreach ($analytics['top_penyulang'] as $idx => $p): ?>
                                    <tr>
                                        <td><span class="badge bg-dark font-monospace"><?= $idx + 1 ?></span></td>
                                        <td>
                                            <span class="fw-bold text-primary d-block"><?= esc($p['nama_penyulang']) ?></span>
                                            <small class="text-muted"><?= esc($p['nama_ulp'] ?? 'ULP Sidoarjo') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge font-monospace fs-6" style="background:<?= $p['color'] ?>; color:#fff;">
                                                <?= $p['score'] ?> / 100
                                            </span>
                                        </td>
                                        <td><span class="badge <?= $p['badge'] ?>"><?= $p['category'] ?></span></td>
                                        <td><span class="badge bg-light text-dark border"><?= $p['active_findings'] ?> Active</span></td>
                                        <td><span class="text-danger fw-bold font-monospace"><?= $p['prob_trip'] ?>%</span></td>
                                        <td class="text-center">
                                            <a href="<?= site_url('penyulang') ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2">Detail &rarr;</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- AI FORECAST & RESOURCE ESTIMATION -->
        <div class="col-lg-5 col-12">
            <div class="ah-card p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-wand-magic-sparkles text-purple me-2"></i> AI Forecast & Maintenance Recommendation</h5>
                <div class="p-3 bg-light rounded-3 border mb-3" style="font-size: 12px;">
                    <small class="text-muted d-block mb-1">Target Inspeksi Prioritas AI:</small>
                    <h6 class="fw-bold text-primary mb-2"><i class="fas fa-bullseye text-danger me-1"></i> <?= esc($analytics['forecast']['rec_inspection_target']) ?></h6>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Prediksi Temuan Baru (30 Hari):</span>
                        <strong class="text-dark font-monospace"><?= $analytics['forecast']['est_new_findings_30d'] ?> Temuan</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Estimasi Work Order Maintenance:</span>
                        <strong class="text-warning font-monospace"><?= $analytics['forecast']['est_maintenance_wos'] ?> WO</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Kebutuhan SDM Tim:</span>
                        <strong class="text-success"><?= $analytics['forecast']['est_required_sdm'] ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Estimasi Durasi Pengerjaan:</span>
                        <strong class="text-info font-monospace"><?= $analytics['forecast']['est_duration'] ?></strong>
                    </div>
                </div>
                <div class="alert alert-info py-2 px-3 mb-0" style="font-size: 11px;">
                    <i class="fas fa-info-circle me-1"></i> Rekomendasi dihasilkan oleh Rule-Based AI Engine berbasis histori gangguan & SLA.
                </div>
            </div>
        </div>
    </div>

    <!-- TOP 10 SECTION LEADERBOARD -->
    <div class="ah-card p-4">
        <h5 class="fw-bold text-dark mb-3"><i class="fas fa-diagram-project text-success me-2"></i> Top 10 Section Berisiko Tinggi</h5>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Kode & Nama Section</th>
                        <th>Penyulang Parent</th>
                        <th>Health Score</th>
                        <th>Status AI</th>
                        <th>Risk Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($analytics['top_sections'])): ?>
                        <tr><td colspan="6" class="text-center py-3 text-muted">Belum ada data section.</td></tr>
                    <?php else: ?>
                        <?php foreach ($analytics['top_sections'] as $idx => $s): ?>
                            <tr>
                                <td><span class="badge bg-dark font-monospace"><?= $idx + 1 ?></span></td>
                                <td class="fw-bold text-dark"><?= esc($s['nama_section']) ?></td>
                                <td><small class="text-muted"><?= esc($s['nama_penyulang'] ?? '-') ?></small></td>
                                <td><span class="badge" style="background:<?= $s['color'] ?>; color:#fff;"><?= $s['score'] ?> / 100</span></td>
                                <td><span class="badge <?= $s['badge'] ?>"><?= $s['category'] ?></span></td>
                                <td><span class="text-danger fw-bold font-monospace"><?= $s['risk_score'] ?>%</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<?= $this->endSection() ?>
