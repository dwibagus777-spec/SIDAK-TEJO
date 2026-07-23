<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Tambah Penyulang<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Tambah Penyulang<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('penyulang') ?>">Master Penyulang</a></li>
<li class="breadcrumb-item active">Tambah</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-6 col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-plus mr-1"></i> Form Tambah Penyulang</h3>
            </div>
            <form action="<?= site_url('penyulang/store') ?>" method="post">
                <?= csrf_field() ?>
                <div class="card-body">
                    
                    <?php $validation = isset($validation) ? $validation : null; ?>

                    <div class="form-group mb-3">
                        <label for="id_unik_penyulang">ID Unik Penyulang (Permanen)</label>
                        <input type="text" name="id_unik_penyulang" id="id_unik_penyulang" class="form-control <?= ($validation && $validation->hasError('id_unik_penyulang')) ? 'is-invalid' : '' ?>" placeholder="Contoh: P_GJM_01" value="<?= old('id_unik_penyulang') ?>" required>
                        <small class="form-text text-muted">ID Unik ini bersifat permanen dan tidak dapat diubah setelah disimpan.</small>
                        <?php if ($validation && $validation->hasError('id_unik_penyulang')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('id_unik_penyulang') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="kode_penyulang">Kode Penyulang</label>
                        <input type="text" name="kode_penyulang" id="kode_penyulang" class="form-control <?= ($validation && $validation->hasError('kode_penyulang')) ? 'is-invalid' : '' ?>" placeholder="Contoh: GJM01" value="<?= old('kode_penyulang') ?>" required>
                        <?php if ($validation && $validation->hasError('kode_penyulang')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('kode_penyulang') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="nama_penyulang">Nama Penyulang</label>
                        <input type="text" name="nama_penyulang" id="nama_penyulang" class="form-control <?= ($validation && $validation->hasError('nama_penyulang')) ? 'is-invalid' : '' ?>" placeholder="Contoh: Penyulang Gajah Mada" value="<?= old('nama_penyulang') ?>" required>
                        <?php if ($validation && $validation->hasError('nama_penyulang')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('nama_penyulang') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="ulp_id">ULP</label>
                        <select name="ulp_id" id="ulp_id" class="form-control select2 <?= ($validation && $validation->hasError('ulp_id')) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Pilih ULP --</option>
                            <?php foreach ($ulps as $ulp): ?>
                                <option value="<?= $ulp['id'] ?>" <?= old('ulp_id') == $ulp['id'] ? 'selected' : '' ?>><?= esc($ulp['nama_ulp']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($validation && $validation->hasError('ulp_id')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('ulp_id') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control select2 <?= ($validation && $validation->hasError('status')) ? 'is-invalid' : '' ?>" required>
                            <option value="AKTIF" <?= old('status') === 'AKTIF' ? 'selected' : '' ?>>AKTIF</option>
                            <option value="NONAKTIF" <?= old('status') === 'NONAKTIF' ? 'selected' : '' ?>>NONAKTIF</option>
                        </select>
                        <?php if ($validation && $validation->hasError('status')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('status') ?></div>
                        <?php endif; ?>
                    </div>

                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                    <a href="<?= site_url('penyulang') ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
