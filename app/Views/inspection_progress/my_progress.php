<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Progress Inspeksi Saya<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Progress Inspeksi Yang Sedang Berjalan<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item active">Progress Inspeksi Saya</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-primary text-white py-3 rounded-top-4 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-spinner fa-spin me-2"></i> Inspeksi Aktif Berjalan</h5>
                <span class="badge bg-white text-primary rounded-pill px-3 py-2 fw-bold"><?= count($runs) ?> Sesi Berjalan</span>
            </div>
            <div class="card-body p-4">
                <?php if (empty($runs)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-clipboard-check fa-4x mb-3 text-secondary opacity-50 d-block"></i>
                        <h5 class="fw-bold text-dark">Tidak ada sesi inspeksi yang sedang berlangsung saat ini.</h5>
                        <p class="small text-muted mb-0">Buka menu <strong>Tugas Inspeksi Saya</strong> untuk memulai sesi pekerjaan baru.</p>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                        <?php foreach ($runs as $r): ?>
                            <div class="col">
                                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
                                    <div class="card-header bg-light py-3 border-bottom d-flex justify-content-between align-items-center">
                                        <span class="badge bg-primary text-white font-monospace rounded-pill px-3 py-1">
                                            <?= esc($r['nomor_inspeksi']) ?>
                                        </span>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 small">
                                            <?= esc(strtoupper($r['status'])) ?>
                                        </span>
                                    </div>
                                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-1"><?= esc($r['type_name'] ?? 'Inspeksi Visual JTM') ?></h6>
                                            <p class="small text-muted mb-3"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?= esc($r['nama_penyulang'] ?: 'Feeder') ?> &bull; <?= esc($r['nama_ulp'] ?: 'ULP') ?></p>

                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between small fw-bold mb-1">
                                                    <span>Progres Lapangan</span>
                                                    <span class="text-primary"><?= esc($r['progress_percent']) ?>%</span>
                                                </div>
                                                <div class="progress rounded-pill" style="height: 8px;">
                                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= esc($r['progress_percent']) ?>%;"></div>
                                                </div>
                                            </div>

                                            <div class="bg-light p-3 rounded-3 mb-3 small d-flex justify-content-around">
                                                <span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> <?= esc($r['passed_points']) ?> PASS</span>
                                                <span class="text-danger fw-bold"><i class="fas fa-times-circle me-1"></i> <?= esc($r['failed_points']) ?> FAIL</span>
                                            </div>
                                        </div>
                                        <div>
                                            <a href="<?= site_url('inspections/guided/' . $r['id']) ?>" class="btn btn-primary w-100 rounded-pill py-2 font-weight-bold">
                                                <i class="fas fa-play me-1"></i> Lanjutkan Inspeksi &rarr;
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
