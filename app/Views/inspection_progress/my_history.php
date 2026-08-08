<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Riwayat Inspeksi Saya<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Riwayat Inspeksi Selesai (Completed Runs)<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item active">Riwayat Inspeksi Saya</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 border-bottom rounded-top-4 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 font-weight-bold text-dark"><i class="fas fa-history text-success me-2"></i> Riwayat Pekerjaan Inspeksi Yang Selesai</h5>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-bold"><?= count($runs) ?> Selesai</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted uppercase small">
                            <tr>
                                <th class="ps-4">No</th>
                                <th>Nomor Inspeksi</th>
                                <th>Jenis Inspeksi</th>
                                <th>Penyulang & ULP</th>
                                <th>Target Asset</th>
                                <th>Hasil (PASS / FAIL)</th>
                                <th>Waktu Selesai</th>
                                <th class="pe-4 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($runs)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fas fa-check-double fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                        Belum ada riwayat inspeksi yang selesai.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($runs as $idx => $r): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-muted"><?= $idx + 1 ?></td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill font-monospace fw-bold">
                                                <?= esc($r['nomor_inspeksi']) ?>
                                            </span>
                                        </td>
                                        <td><strong class="text-dark"><?= esc($r['type_name'] ?? 'Inspeksi Visual') ?></strong></td>
                                        <td class="small">
                                            <div class="fw-bold text-dark"><?= esc($r['nama_penyulang'] ?: 'Feeder') ?></div>
                                            <span class="text-muted"><?= esc($r['nama_ulp'] ?: 'ULP') ?></span>
                                        </td>
                                        <td><span class="badge bg-info-subtle text-info rounded-pill px-3 py-1 fw-bold"><?= esc($r['total_points']) ?> Asset</span></td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success me-1"><i class="fas fa-check me-1"></i> <?= esc($r['passed_points']) ?> PASS</span>
                                            <span class="badge bg-danger-subtle text-danger"><i class="fas fa-times me-1"></i> <?= esc($r['failed_points']) ?> FAIL</span>
                                        </td>
                                        <td class="small text-muted">
                                            <i class="far fa-clock me-1"></i> <?= date('d M Y H:i', strtotime($r['end_time'] ?: $r['updated_at'])) ?>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <a href="<?= site_url('inspection-progress/detail/' . $r['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                <i class="fas fa-eye me-1"></i> Detail
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
