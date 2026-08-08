<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Detail Progress Inspeksi<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Detail Monitoring Progress Inspeksi Lapangan<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('inspection-progress') ?>">Progress Inspeksi</a></li>
<li class="breadcrumb-item active">Live Detail</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-4 col-12 mb-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-primary text-white py-3 rounded-top-4">
                <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-info-circle me-2"></i> Ringkasan Inspeksi</h5>
            </div>
            <div class="card-body p-4">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill font-monospace fw-bold mb-3">
                    <?= esc($inspection['nomor_inspeksi']) ?>
                </span>

                <?php
                $tot = (int)($inspection['total_points'] ?? 0);
                $pass = (int)($inspection['passed_points'] ?? 0);
                $fail = (int)($inspection['failed_points'] ?? 0);
                $done = $pass + $fail;
                $pct = ($tot > 0) ? round(($done / $tot) * 100) : 0;
                ?>

                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-bold mb-1">
                        <span>Pencapaian Progress</span>
                        <span class="text-primary"><?= $pct ?>% (<?= $done ?>/<?= $tot ?> Asset)</span>
                    </div>
                    <div class="progress rounded-pill" style="height: 10px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $pct ?>%;"></div>
                    </div>
                </div>

                <div class="bg-light p-3 rounded-3 mb-3 small">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Aset PASS (Normal):</span>
                        <strong class="text-success"><?= $pass ?> Asset</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Aset FAIL (Abnormal):</span>
                        <strong class="text-danger"><?= $fail ?> Asset</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Belum Diperiksa:</span>
                        <strong class="text-secondary"><?= max(0, $tot - $done) ?> Asset</strong>
                    </div>
                </div>

                <a href="<?= site_url('inspection-progress') ?>" class="btn btn-outline-secondary w-100 rounded-pill">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Progress Monitoring
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-8 col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 border-bottom rounded-top-4">
                <h5 class="card-title mb-0 font-weight-bold text-dark"><i class="fas fa-list-check text-primary me-2"></i> Progress Status Per Titik Aset</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted uppercase small">
                            <tr>
                                <th class="ps-4">No</th>
                                <th>Kode Asset</th>
                                <th>Nama Asset</th>
                                <th>Jenis</th>
                                <th>Status Pemeriksaan</th>
                                <th class="pe-4 text-end">Aksi Asset</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($points)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Belum ada titik aset pada inspeksi ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($points as $pt): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-muted">#<?= esc($pt['sequence_no']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-2 py-1 font-monospace fw-bold">
                                                <?= esc($pt['kode_asset']) ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-dark"><?= esc($pt['nama_asset']) ?></td>
                                        <td><span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 rounded-pill"><?= esc($pt['jenis_asset']) ?></span></td>
                                        <td>
                                            <?php
                                            $st = strtoupper($pt['status']);
                                            if ($st === 'PASSED' || $st === 'PASS') {
                                                echo '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill"><i class="fas fa-check-circle me-1"></i> PASS</span>';
                                            } else if ($st === 'FAILED' || $st === 'FAIL') {
                                                echo '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill"><i class="fas fa-exclamation-triangle me-1"></i> FAIL (Abnormal)</span>';
                                            } else {
                                                echo '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1 rounded-pill"><i class="far fa-clock me-1"></i> PENDING</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <a href="<?= site_url('assets/detail/' . $pt['asset_id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3" title="Buka Detail & Riwayat Asset">
                                                <i class="fas fa-box-archive me-1"></i> Audit Asset
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
