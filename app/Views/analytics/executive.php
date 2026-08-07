<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Executive Analytics & Intelligence<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Executive Decision Support Center<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Executive Analytics (v2.3.0)</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <!-- TOP STATS CARDS -->
    <div class="col-lg-3 col-6 mb-3">
        <div class="card shadow-sm border-0 rounded-4 bg-primary text-white">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-white-50">COMPLETION RATE</div>
                        <h3 class="fw-bold mb-0 text-white"><?= $kpis['inspection_completion_rate'] ?>%</h3>
                    </div>
                    <i class="fas fa-chart-line fa-2x text-white-50"></i>
                </div>
                <div class="small mt-2 text-white-50"><?= $kpis['completed_inspections'] ?> dari <?= $kpis['total_inspections'] ?> Sesi</div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6 mb-3">
        <div class="card shadow-sm border-0 rounded-4 bg-success text-white">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-white-50">POINT PASS RATE</div>
                        <h3 class="fw-bold mb-0 text-white"><?= $kpis['point_pass_rate'] ?>%</h3>
                    </div>
                    <i class="fas fa-check-circle fa-2x text-white-50"></i>
                </div>
                <div class="small mt-2 text-white-50"><?= $kpis['passed_points'] ?> Poin Lolos</div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6 mb-3">
        <div class="card shadow-sm border-0 rounded-4 bg-danger text-white">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-white-50">FAILURE RATE</div>
                        <h3 class="fw-bold mb-0 text-white"><?= $kpis['point_failure_rate'] ?>%</h3>
                    </div>
                    <i class="fas fa-triangle-exclamation fa-2x text-white-50"></i>
                </div>
                <div class="small mt-2 text-white-50"><?= $kpis['failed_points'] ?> Poin Abnormal</div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6 mb-3">
        <div class="card shadow-sm border-0 rounded-4 bg-dark text-white">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">SCORING ENGINE</div>
                        <h3 class="fw-bold mb-0 text-warning"><?= esc($scoringVersion) ?></h3>
                    </div>
                    <i class="fas fa-shield-halved fa-2x text-warning"></i>
                </div>
                <div class="small mt-2 text-muted">Read-Only Decision Support</div>
            </div>
        </div>
    </div>
</div>

<!-- TOP PROBLEMATIC ASSET HEALTH MATRIX -->
<div class="row">
    <div class="col-lg-7 col-12 mb-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-triangle-exclamation text-danger me-2"></i> Top Assets Berisiko Tinggi</h5>
                <span class="badge bg-danger">Unresolved Defect Matrix</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Kode Aset</th>
                                <th>Nama Aset</th>
                                <th>Jenis</th>
                                <th class="text-center">Status EAM</th>
                                <th class="text-center">Health Score</th>
                                <th class="text-end pe-4">Klasifikasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($healthList)): ?>
                                <?php foreach ($healthList as $h): ?>
                                    <tr>
                                        <td class="ps-4 font-monospace fw-bold text-primary">
                                            <a href="<?= site_url('assets/detail/' . $h['asset_id']) ?>" class="text-decoration-none text-primary">
                                                <?= esc($h['kode_asset']) ?>
                                            </a>
                                        </td>
                                        <td class="fw-bold text-dark"><?= esc($h['nama_asset']) ?></td>
                                        <td><span class="badge bg-secondary"><?= esc($h['jenis_asset']) ?></span></td>
                                        <td class="text-center"><span class="badge bg-danger"><?= esc($h['eam_status']) ?></span></td>
                                        <td class="text-center">
                                            <span class="fw-bold font-monospace fs-5" style="color: <?= $h['health_color'] ?>;">
                                                <?= $h['health_score'] ?> / 100
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <span class="badge px-3 py-1 font-weight-bold" style="background: <?= $h['health_color'] ?>; color: #fff;">
                                                <?= esc($h['classification']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Belum ada data temuan aktif pada master aset.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5 col-12 mb-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-chart-pie text-info me-2"></i> Distribusi Keparahan Temuan Aktif</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1 fw-bold">
                        <span class="text-danger">KRITIS</span>
                        <span><?= $severityBreakdown['KRITIS'] ?? 0 ?> Temuan</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-danger" style="width: <?= min(100, ($severityBreakdown['KRITIS'] ?? 0) * 10) ?>%"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1 fw-bold">
                        <span class="text-warning">TINGGI</span>
                        <span><?= $severityBreakdown['TINGGI'] ?? 0 ?> Temuan</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-warning" style="width: <?= min(100, ($severityBreakdown['TINGGI'] ?? 0) * 10) ?>%"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1 fw-bold">
                        <span class="text-primary">SEDANG</span>
                        <span><?= $severityBreakdown['SEDANG'] ?? 0 ?> Temuan</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-primary" style="width: <?= min(100, ($severityBreakdown['SEDANG'] ?? 0) * 10) ?>%"></div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 border border-secondary-subtle small mt-4 text-dark">
                    <i class="fas fa-shield-halved text-success me-1"></i>
                    <strong>Catatan Otoritas EAM:</strong> Seluruh metrik analitik dan Asset Health Score (v1.0) berstatus <em>100% Read-Only Decision Support</em>. Otoritas perubahan status EAM aset tetap dipegang secara tunggal oleh <strong>AssetLifecycleService</strong>.
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
