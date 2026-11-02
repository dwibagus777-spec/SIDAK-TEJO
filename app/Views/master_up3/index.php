<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Master Unit Pelaksana (UP / UP3 / UP2D)<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Master Hierarki: Unit Pelaksana (UP)<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item active">Master Unit Pelaksana</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center rounded-top-4 border-bottom">
                <div>
                    <h5 class="card-title mb-0 font-weight-bold text-dark"><i class="fas fa-building text-primary me-2"></i> Master Data Unit Pelaksana (UP / UP3 / UP2D)</h5>
                    <small class="text-muted">Unit Pelaksana Pelayanan Pelanggan (UP3) atau Pengatur Distribusi (UP2D) di bawah UID</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= site_url('master-hierarchy/unconnected') ?>" class="btn btn-outline-warning rounded-pill px-3">
                        <i class="fas fa-link-slash me-1"></i> Audit Data Unlinked
                    </a>
                    <a href="<?= site_url('master-up3/create') ?>" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-plus me-1"></i> Tambah UP Baru
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (session()->getFlashdata('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show m-3 rounded-3" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?= session()->getFlashdata('success') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show m-3 rounded-3" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?= session()->getFlashdata('error') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted uppercase small">
                            <tr>
                                <th class="ps-4">No</th>
                                <th>Kode UP</th>
                                <th>Nama Unit Pelaksana</th>
                                <th>Induk UID</th>
                                <th>Jumlah ULP</th>
                                <th>Jumlah Gardu Induk</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($up3s)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">Belum ada data Unit Pelaksana.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($up3s as $idx => $up): ?>
                                    <tr>
                                        <td class="ps-4 font-monospace"><?= $idx + 1 ?></td>
                                        <td><span class="badge bg-secondary font-monospace"><?= esc($up['kode_up3']) ?></span></td>
                                        <td class="fw-bold text-dark"><?= esc($up['nama_up3']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <i class="fas fa-sitemap me-1 text-primary"></i> <?= esc($up['nama_uid'] ?: 'Belum Terhubung') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                                <i class="fas fa-store me-1"></i> <?= $up['total_ulp'] ?> ULP
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1">
                                                <i class="fas fa-charging-station me-1"></i> <?= $up['total_gi'] ?> GI
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($up['status'] === 'AKTIF'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill">Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= site_url('master-up3/edit/' . $up['id']) ?>" class="btn btn-outline-secondary" title="Edit UP">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="<?= site_url('master-up3/delete/' . $up['id']) ?>" class="btn btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus UP ini?')" title="Hapus UP">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
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
