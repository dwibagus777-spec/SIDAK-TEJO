<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Riwayat Batch Import & Rollback<?= $this->endSection() ?>
<?= $this->section('page_title') ?>RIWAYAT IMPORT BATCH & MANAGEMENT ROLLBACK<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">

    <!-- Header Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="fw-bold mb-1 text-primary">
                    <i class="fas fa-boxes-packing text-warning me-2"></i> Management Batch Import Assets
                </h4>
                <p class="text-muted small mb-0">
                    Setiap proses impor Excel tercatat sebagai Batch terisolasi. Jika terjadi kesalahan data, lakukan <strong>Rollback 1-Klik</strong> untuk menghapus aset impor secara aman (Soft Delete).
                </p>
            </div>
            <div>
                <a href="<?= site_url('master-assets') ?>" class="btn btn-outline-secondary rounded-pill font-weight-bold">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Master Aset
                </a>
                <a href="<?= site_url('master-assets/import') ?>" class="btn btn-primary rounded-pill font-weight-bold">
                    <i class="fas fa-file-import me-1"></i> Impor Excel Baru
                </a>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
            <i class="fas fa-check-circle me-2 fs-5"></i> <?= session()->getFlashdata('success') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2 fs-5"></i> <?= session()->getFlashdata('error') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Batches Table Card -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-uppercase small text-muted font-monospace">
                        <tr>
                            <th class="ps-4">Kode Batch</th>
                            <th>Unit (ULP)</th>
                            <th>Penyulang</th>
                            <th>Nama Berkas</th>
                            <th class="text-center">Total Baris</th>
                            <th class="text-center">Sukses</th>
                            <th class="text-center">Gagal</th>
                            <th>Tanggal Impor</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Aksi Rollback</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($batches)): ?>
                            <?php foreach ($batches as $b): ?>
                                <tr>
                                    <td class="ps-4 font-monospace fw-bold text-primary">
                                        <?= esc($b['batch_code']) ?>
                                    </td>
                                    <td><?= esc($b['nama_ulp'] ?? '-') ?></td>
                                    <td><?= esc($b['nama_penyulang'] ?? '-') ?></td>
                                    <td class="small text-muted"><?= esc($b['file_name'] ?? 'Excel') ?></td>
                                    <td class="text-center fw-bold"><?= number_format($b['total_rows'] ?? 0) ?></td>
                                    <td class="text-center text-success fw-bold"><?= number_format($b['success_rows'] ?? 0) ?></td>
                                    <td class="text-center text-danger fw-bold"><?= number_format($b['failed_rows'] ?? 0) ?></td>
                                    <td class="small text-muted"><?= date('d M Y H:i', strtotime($b['imported_at'] ?? $b['created_at'])) ?></td>
                                    <td>
                                        <?php if (($b['status'] ?? 'ACTIVE') === 'ACTIVE'): ?>
                                            <span class="badge bg-success rounded-pill px-3 py-1">AKTIF</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger rounded-pill px-3 py-1">ROLLED BACK</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if (($b['status'] ?? 'ACTIVE') === 'ACTIVE'): ?>
                                            <form action="<?= site_url('master-assets/rollback-batch/' . $b['id']) ?>" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin melakukan ROLLBACK batch impor #<?= esc($b['batch_code']) ?>?\n\nSeluruh aset hasil impor dari berkas ini akan di-soft delete secara aman.');" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">
                                                    <i class="fas fa-rotate-left me-1"></i> Rollback 1-Klik
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="small text-muted fst-italic">Di-rollback</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fs-1 d-block mb-2 text-secondary"></i>
                                    Belum ada riwayat batch impor aset yang tercatat.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
<?= $this->endSection() ?>
