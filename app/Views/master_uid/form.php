<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>
<?= $this->section('page_title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item"><a href="<?= site_url('master-uid') ?>">Master UID</a></li>
<li class="breadcrumb-item active"><?= $uid ? 'Edit' : 'Tambah' ?></li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 rounded-top-4 border-bottom">
                <h5 class="card-title mb-0 font-weight-bold text-dark">
                    <i class="fas <?= $uid ? 'fa-edit text-warning' : 'fa-plus-circle text-primary' ?> me-2"></i>
                    <?= esc($title) ?>
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?= session()->getFlashdata('error') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="<?= $uid ? site_url('master-uid/update/' . $uid['id']) : site_url('master-uid/store') ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="kode_uid" class="form-label fw-bold">Kode UID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control font-monospace" id="kode_uid" name="kode_uid" placeholder="Contoh: UID_JATIM, UID_JAYA, UID_JABAR" value="<?= old('kode_uid', $uid['kode_uid'] ?? '') ?>" required>
                        <small class="text-muted">Kode unik pengenal UID huruf kapital tanpa spasi.</small>
                    </div>

                    <div class="mb-3">
                        <label for="nama_uid" class="form-label fw-bold">Nama Unit Induk (UID) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_uid" name="nama_uid" placeholder="Contoh: UID Jawa Timur" value="<?= old('nama_uid', $uid['nama_uid'] ?? '') ?>" required>
                    </div>

                    <div class="mb-4">
                        <label for="status" class="form-label fw-bold">Status Operasional</label>
                        <select class="form-select" id="status" name="status">
                            <option value="AKTIF" <?= old('status', $uid['status'] ?? 'AKTIF') === 'AKTIF' ? 'selected' : '' ?>>AKTIF</option>
                            <option value="NONAKTIF" <?= old('status', $uid['status'] ?? '') === 'NONAKTIF' ? 'selected' : '' ?>>NONAKTIF</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-between pt-2 border-top">
                        <a href="<?= site_url('master-uid') ?>" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-save me-1"></i> Simpan Data UID
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
