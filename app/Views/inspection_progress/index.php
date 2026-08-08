<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Progress Inspeksi Jaringan (Live)<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Monitoring Progress Inspeksi Real-Time<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item active">Progress Inspeksi</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center rounded-top-4 border-bottom">
                <div>
                    <h5 class="card-title mb-0 font-weight-bold text-dark"><i class="fas fa-chart-line text-primary me-2"></i> Progress Inspeksi Lapangan (Live Dashboard)</h5>
                    <small class="text-muted">Pantau progres pekerjaan, persentase pencapaian, dan rasio PASS/FAIL secara real-time</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted uppercase small">
                            <tr>
                                <th class="ps-4">No</th>
                                <th>Nomor Inspeksi</th>
                                <th>Jenis Pekerjaan</th>
                                <th>Lokasi Jaringan</th>
                                <th>Petugas Lapangan</th>
                                <th>Target & Progress</th>
                                <th>Rasio Hasil (PASS / FAIL)</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inspections)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="fas fa-chart-line fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                        Belum ada inspeksi yang berjalan saat ini.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($inspections as $idx => $run): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-muted"><?= $idx + 1 ?></td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill font-monospace fw-bold">
                                                <?= esc($run['nomor_inspeksi']) ?>
                                            </span>
                                        </td>
                                        <td><strong class="text-dark"><?= esc($run['type_name'] ?? 'Inspeksi Visual') ?></strong></td>
                                        <td class="small">
                                            <div class="fw-bold text-dark"><?= esc($run['nama_penyulang'] ?: 'Feeder') ?></div>
                                            <span class="text-muted"><?= esc($run['nama_ulp'] ?: 'ULP') ?></span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-dark"><i class="fas fa-user text-primary me-1"></i> <?= esc($run['inspector_name'] ?: 'Petugas') ?></span>
                                        </td>
                                        <td style="width: 180px;">
                                            <div class="d-flex justify-content-between small fw-bold mb-1">
                                                <span><?= esc($run['completed_count']) ?> / <?= esc($run['total_points']) ?> Asset</span>
                                                <span class="text-primary"><?= esc($run['progress_percent']) ?>%</span>
                                            </div>
                                            <div class="progress rounded-pill" style="height: 8px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= esc($run['progress_percent']) ?>%;"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success me-1"><i class="fas fa-check me-1"></i> <?= esc($run['passed_points']) ?> PASS</span>
                                            <span class="badge bg-danger-subtle text-danger"><i class="fas fa-times me-1"></i> <?= esc($run['failed_points']) ?> FAIL</span>
                                        </td>
                                        <td>
                                            <?php
                                            $st = strtoupper($run['status']);
                                            $bClass = ($st === 'COMPLETED') ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle';
                                            ?>
                                            <span class="badge <?= $bClass ?> px-3 py-1 rounded-pill"><?= esc($st) ?></span>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <a href="<?= site_url('inspection-progress/detail/' . $run['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                <i class="fas fa-search me-1"></i> Live Detail
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
</div>
<?= $this->endSection() ?>
