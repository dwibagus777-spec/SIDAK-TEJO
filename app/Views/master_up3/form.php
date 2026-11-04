<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>
<?= $this->section('page_title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item"><a href="<?= site_url('master-up3') ?>">Master UP</a></li>
<li class="breadcrumb-item active"><?= $up3 ? 'Edit' : 'Tambah' ?></li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 rounded-top-4 border-bottom">
                <h5 class="card-title mb-0 font-weight-bold text-dark">
                    <i class="fas <?= $up3 ? 'fa-edit text-warning' : 'fa-plus-circle text-primary' ?> me-2"></i>
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

                <form action="<?= $up3 ? site_url('master-up3/update/' . $up3['id']) : site_url('master-up3/store') ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="uid_id" class="form-label fw-bold">Unit Induk Distribusi (UID) Induk <span class="text-danger">*</span></label>
                        <select class="form-select" id="uid_id" name="uid_id" required>
                            <option value="">-- Pilih UID Induk --</option>
                            <?php foreach ($uids as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= old('uid_id', $up3['uid_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                                    [<?= esc($u['kode_uid']) ?>] <?= esc($u['nama_uid']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Setiap Unit Pelaksana (UP3 / UP2D) terikat secara hierarki ke UID induknya.</small>
                    </div>

                    <div class="mb-3">
                        <label for="kode_up3" class="form-label fw-bold">Kode UP <span class="text-danger">*</span></label>
                        <input type="text" class="form-control font-monospace" id="kode_up3" name="kode_up3" placeholder="Contoh: UP3_SDA, UP3_SBY_BARAT, UP2D_JATIM" value="<?= old('kode_up3', $up3['kode_up3'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="nama_up3" class="form-label fw-bold">Nama Unit Pelaksana <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_up3" name="nama_up3" placeholder="Contoh: UP3 Sidoarjo / UP3 Surabaya Barat" value="<?= old('nama_up3', $up3['nama_up3'] ?? '') ?>" required>
                    </div>

                    <div class="mb-4">
                        <label for="status" class="form-label fw-bold">Status Operasional</label>
                        <select class="form-select" id="status" name="status">
                            <option value="AKTIF" <?= old('status', $up3['status'] ?? 'AKTIF') === 'AKTIF' ? 'selected' : '' ?>>AKTIF</option>
                            <option value="NONAKTIF" <?= old('status', $up3['status'] ?? '') === 'NONAKTIF' ? 'selected' : '' ?>>NONAKTIF</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-between pt-2 border-top">
                        <a href="<?= site_url('master-up3') ?>" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-save me-1"></i> Simpan Data UP
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
