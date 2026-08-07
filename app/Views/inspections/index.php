<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Riwayat Inspeksi Guided<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Guided Inspection Execution Engine<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Inspeksi Jaringan</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12 mb-3 d-flex justify-content-between align-items-center">
        <h4 class="fw-bold text-dark mb-0"><i class="fas fa-clipboard-check text-primary me-2"></i> Daftar Sesi Inspeksi Guided</h4>
        <a href="<?= site_url('inspections/start') ?>" class="btn btn-primary rounded-pill px-4 font-weight-bold">
            <i class="fas fa-play me-1"></i> Mulai Inspeksi Baru
        </a>
    </div>

    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">No. Inspeksi</th>
                                <th>Jenis Pekerjaan</th>
                                <th>Feeder Baseline</th>
                                <th class="text-center">Progress Titik Aset</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Waktu Mulai</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($inspections)): ?>
                                <?php foreach ($inspections as $ins): ?>
                                    <?php 
                                        $total  = (int)($ins['total_points'] ?? 0);
                                        $passed = (int)($ins['passed_points'] ?? 0);
                                        $failed = (int)($ins['failed_points'] ?? 0);
                                        $done   = $passed + $failed;
                                        $percent = $total > 0 ? round(($done / $total) * 100) : 0;
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-bold font-monospace text-primary">
                                            <?= esc($ins['nomor_inspeksi']) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle">
                                                <?= esc($ins['type_name'] ?? 'Inspeksi Visual JTM') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <i class="fas fa-route text-muted me-1"></i> <?= esc($ins['baseline_name'] ?: 'Penyulang Standar') ?>
                                        </td>
                                        <td class="text-center" style="min-width: 180px;">
                                            <div class="small fw-bold mb-1"><?= $done ?> / <?= $total ?> Aset (<?= $percent ?>%)</div>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $percent ?>%"></div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($ins['status'] === 'COMPLETED'): ?>
                                                <span class="badge bg-success">SELESAI</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">IN PROGRESS</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center small text-muted">
                                            <?= !empty($ins['start_time']) ? date('d-m-Y H:i', strtotime($ins['start_time'])) : '-' ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="<?= site_url('inspections/guided/' . $ins['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 font-weight-bold">
                                                <i class="fas fa-mobile-screen me-1"></i> Buka Mobile Guided &rarr;
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-clipboard-list fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                        Belum ada sesi inspeksi guided yang terdaftar. Klik <strong>Mulai Inspeksi Baru</strong> untuk memulai pemeriksaan.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
