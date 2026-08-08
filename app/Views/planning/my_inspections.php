<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Tugas Inspeksi Saya<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Tugas Inspeksi Saya (Assigned Tasks)<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item active">Tugas Inspeksi Saya</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-primary text-white py-3 rounded-top-4 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-tasks me-2"></i> Daftar Tugas Inspeksi Lapangan Saya</h5>
                <span class="badge bg-white text-primary rounded-pill px-3 py-2 fw-bold"><?= count($tasks) ?> Tugas Aktif</span>
            </div>
            <div class="card-body p-4">
                <?php if (session()->getFlashdata('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?= session()->getFlashdata('success') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?= session()->getFlashdata('error') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (empty($tasks)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-clipboard-check fa-4x mb-3 text-secondary opacity-50 d-block"></i>
                        <h5 class="fw-bold text-dark">Tidak ada penugasan inspeksi aktif saat ini.</h5>
                        <p class="small text-muted mb-0">Seluruh tugas inspeksi Anda telah diselesaikan atau belum dipublikasikan oleh Supervisor/Admin.</p>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                        <?php foreach ($tasks as $t): ?>
                            <div class="col">
                                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden card-hover">
                                    <div class="card-header bg-light py-3 border-bottom d-flex justify-content-between align-items-center">
                                        <span class="badge bg-primary text-white font-monospace rounded-pill px-3 py-1">
                                            <?= esc($t['nomor_planning']) ?>
                                        </span>
                                        <small class="text-muted fw-bold">
                                            <i class="far fa-calendar-alt me-1"></i> <?= date('d M Y', strtotime($t['scheduled_date'] ?: $t['created_at'])) ?>
                                        </small>
                                    </div>
                                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                                        <div>
                                            <h5 class="fw-bold text-dark mb-2"><?= esc($t['title']) ?></h5>
                                            <p class="small text-muted mb-3">
                                                <i class="fas fa-clipboard-list me-1 text-primary"></i> <?= esc($t['type_name'] ?? 'Inspeksi Visual JTM') ?>
                                            </p>
                                            <div class="bg-light p-3 rounded-3 mb-3 small">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">Gardu Induk:</span>
                                                    <strong class="text-dark"><?= esc($t['nama_gi'] ?: 'GI Buduran') ?></strong>
                                                </div>
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">ULP:</span>
                                                    <strong class="text-dark"><?= esc($t['nama_ulp'] ?: 'Sidoarjo Kota') ?></strong>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-muted">Penyulang / Feeder:</span>
                                                    <strong class="text-dark"><?= esc($t['nama_penyulang'] ?: 'GEDANGAN') ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3 py-1 fw-bold">
                                                    <i class="fas fa-boxes-stacked me-1"></i> <?= esc($t['total_assets']) ?> Target Asset
                                                </span>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1 fw-bold">
                                                    <?= esc(strtoupper($t['status'])) ?>
                                                </span>
                                            </div>
                                            <form action="<?= site_url('inspections/start') ?>" method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="planning_id" value="<?= $t['id'] ?>">
                                                <input type="hidden" name="inspection_type_id" value="<?= $t['inspection_type_id'] ?>">
                                                <input type="hidden" name="penyulang_id" value="<?= $t['penyulang_id'] ?>">
                                                <input type="hidden" name="object_type" value="<?= $t['jenis_asset'] ?>">
                                                <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 font-weight-bold">
                                                    <i class="fas fa-play me-1"></i> Mulai Petualangan Inspeksi &rarr;
                                                </button>
                                            </form>
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
