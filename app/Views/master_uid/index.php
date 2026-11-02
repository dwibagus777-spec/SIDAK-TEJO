<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Master UID (Unit Induk Distribusi)<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Master Hierarki: Unit Induk Distribusi (UID)<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item active">Master UID</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center rounded-top-4 border-bottom">
                <div>
                    <h5 class="card-title mb-0 font-weight-bold text-dark"><i class="fas fa-sitemap text-primary me-2"></i> Master Data UID</h5>
                    <small class="text-muted">Unit Induk Distribusi sebagai tingkat induk hierarki organisasi dan wilayah jaringan</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= site_url('master-hierarchy/unconnected') ?>" class="btn btn-outline-warning rounded-pill px-3">
                        <i class="fas fa-link-slash me-1"></i> Audit Data Unlinked
                    </a>
                    <a href="<?= site_url('master-uid/create') ?>" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-plus me-1"></i> Tambah UID Baru
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
                                <th>Kode UID</th>
                                <th>Nama Unit Induk (UID)</th>
                                <th>Jumlah UP / UP3 Terkait</th>
                                <th>Pengguna Terdaftar</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($uids)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">Belum ada data UID.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($uids as $idx => $uid): ?>
                                    <tr>
                                        <td class="ps-4 font-monospace"><?= $idx + 1 ?></td>
                                        <td><span class="badge bg-secondary font-monospace"><?= esc($uid['kode_uid']) ?></span></td>
                                        <td class="fw-bold text-dark"><?= esc($uid['nama_uid']) ?></td>
                                        <td>
                                            <a href="<?= site_url('master-up3') ?>?uid_id=<?= $uid['id'] ?>" class="badge bg-info text-dark text-decoration-none">
                                                <i class="fas fa-building me-1"></i> <?= $uid['total_up'] ?> Unit Pelaksana
                                            </a>
                                        </td>
                                        <td>
                                            <span class="text-muted"><i class="fas fa-users me-1"></i> <?= $uid['total_users'] ?> Users</span>
                                        </td>
                                        <td>
                                            <?php if ($uid['status'] === 'AKTIF'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill">Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= site_url('master-uid/edit/' . $uid['id']) ?>" class="btn btn-outline-secondary" title="Edit UID">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="<?= site_url('master-uid/delete/' . $uid['id']) ?>" class="btn btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus UID ini?')" title="Hapus UID">
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
