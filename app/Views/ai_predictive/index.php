<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <!-- Top Header & Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-brain text-warning me-2 fs-3"></i> AI PREDICTIVE MAINTENANCE & DECISION SUPPORT
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">ENTERPRISE V19</span>
            </h3>
            <p class="text-muted small mb-0">Prediksi Potensi Kegagalan Aset 7/30/90 Hari, Risk Scoring Engine, Anomaly Detection & Export Machine Learning Dataset</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-success btn-sm rounded-pill dropdown-toggle font-weight-bold" type="button" id="exportDatasetBtn" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-file-export me-1"></i> Export ML Dataset
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3" aria-labelledby="exportDatasetBtn">
                    <li><a class="dropdown-item" href="<?= site_url('ai-predictive/export-dataset?format=csv') ?>"><i class="fas fa-file-csv text-success me-2"></i> Export ke Format CSV</a></li>
                    <li><a class="dropdown-item" href="<?= site_url('ai-predictive/export-dataset?format=json') ?>"><i class="fas fa-file-code text-warning me-2"></i> Export ke Format JSON</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Stat KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small font-weight-bold d-block">TOTAL ASET DIPANTAU</span>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format($analytics['total_assets']) ?></h3>
                    </div>
                    <div class="p-3 rounded-circle bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-cubes fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small font-weight-bold d-block">RISIKO CRITICAL</span>
                        <h3 class="fw-bold mb-0 text-danger"><?= number_format($analytics['critical_count']) ?></h3>
                    </div>
                    <div class="p-3 rounded-circle bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-radiation fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small font-weight-bold d-block">RISIKO HIGH</span>
                        <h3 class="fw-bold mb-0 text-warning"><?= number_format($analytics['high_risk_count']) ?></h3>
                    </div>
                    <div class="p-3 rounded-circle bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-triangle-exclamation fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small font-weight-bold d-block">DETEKSI ANOMALI</span>
                        <h3 class="fw-bold mb-0 text-info"><?= number_format(count($analytics['anomalies'])) ?></h3>
                    </div>
                    <div class="p-3 rounded-circle bg-info bg-opacity-10 text-info">
                        <i class="fas fa-shield-cat fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Top 10 High Risk Assets Table -->
        <div class="col-lg-8 col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-fire-flame-curved text-danger me-2"></i> Top 10 Asset Berisiko Tinggi (Risk Score)</h5>
                    <span class="badge bg-danger">AI Live Score</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Kode / Nama Asset</th>
                                    <th>Jenis</th>
                                    <th>ULP</th>
                                    <th>Risk Score</th>
                                    <th>Kategori AI</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($analytics['top_risk_assets'])): ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">Semua aset dalam kondisi normal.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($analytics['top_risk_assets'] as $a): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <a href="<?= site_url('assets/detail/' . $a['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none d-block">
                                                    <?= esc($a['kode_asset']) ?>
                                                </a>
                                                <span class="fw-bold text-dark"><?= esc($a['nama_asset']) ?></span>
                                            </td>
                                            <td><span class="badge bg-secondary"><?= esc($a['jenis_asset']) ?></span></td>
                                            <td><small class="fw-bold text-secondary"><?= esc($a['nama_ulp'] ?: '-') ?></small></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="fw-bold text-dark font-monospace"><?= number_format($a['risk_score'], 1) ?></span>
                                                    <div class="progress flex-grow-1" style="height: 6px; min-width: 60px;">
                                                        <div class="progress-bar <?= $a['risk_score'] >= 76 ? 'bg-danger' : ($a['risk_score'] >= 51 ? 'bg-warning' : 'bg-primary') ?>" style="width: <?= $a['risk_score'] ?>%;"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?= $a['badge_class'] ?>"><?= $a['risk_category'] ?></span>
                                            </td>
                                            <td class="text-center pe-3">
                                                <a href="<?= site_url('work-orders/create?asset_id=' . $a['id']) ?>" class="btn btn-xs btn-outline-danger rounded-pill px-2">
                                                    <i class="fas fa-screwdriver-wrench me-1"></i> Terbitkan WO
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Predictive Failure Windows (7 / 30 / 90 Days) -->
        <div class="col-lg-4 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-clock-rotate-left text-primary me-2"></i> Prediksi Kegagalan Aset</h5>

                <div class="mb-3 p-3 bg-danger bg-opacity-10 rounded-3 border border-danger border-opacity-25">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold text-danger small"><i class="fas fa-radiation me-1"></i> Potensi Padam 7 Hari</span>
                        <span class="badge bg-danger"><?= count($analytics['predictions']['days_7']) ?> Aset</span>
                    </div>
                    <small class="text-muted d-block mb-2">Aset berisiko tinggi padam dalam 1 minggu ke depan.</small>
                    <?php if (!empty($analytics['predictions']['days_7'])): ?>
                        <ul class="list-unstyled mb-0" style="font-size: 12px;">
                            <?php foreach (array_slice($analytics['predictions']['days_7'], 0, 3) as $p7): ?>
                                <li class="py-1 border-bottom border-danger border-opacity-10 d-flex justify-content-between">
                                    <span class="fw-bold text-dark"><?= esc($p7['kode_asset']) ?></span>
                                    <span class="fw-bold text-danger"><?= $p7['prob_7_days'] ?>% Prob.</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="mb-3 p-3 bg-warning bg-opacity-10 rounded-3 border border-warning border-opacity-25">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold text-warning text-dark small"><i class="fas fa-triangle-exclamation me-1"></i> Potensi Padam 30 Hari</span>
                        <span class="badge bg-warning text-dark"><?= count($analytics['predictions']['days_30']) ?> Aset</span>
                    </div>
                    <small class="text-muted d-block">Probabilitas kegagalan komponen dalam 1 bulan.</small>
                </div>

                <div class="p-3 bg-info bg-opacity-10 rounded-3 border border-info border-opacity-25">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold text-info small"><i class="fas fa-calendar-check me-1"></i> Potensi Padam 90 Hari</span>
                        <span class="badge bg-info text-white"><?= count($analytics['predictions']['days_90']) ?> Aset</span>
                    </div>
                    <small class="text-muted d-block">Perencanaan HAR Preventif triwulan.</small>
                </div>
            </div>

            <!-- Anomaly Detection Alert Feed -->
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-shield-cat text-info me-2"></i> Deteksi Anomali Realtime</h5>
                <?php if (empty($analytics['anomalies'])): ?>
                    <p class="text-muted small mb-0">Tidak terdeteksi anomali pada sistem saat ini.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($analytics['anomalies'] as $anom): ?>
                            <div class="list-group-item px-0 py-2 border-bottom">
                                <div class="fw-bold text-dark small"><i class="fas fa-triangle-exclamation text-warning me-1"></i> <?= esc($anom['title']) ?></div>
                                <small class="text-muted d-block"><?= esc($anom['detail']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
