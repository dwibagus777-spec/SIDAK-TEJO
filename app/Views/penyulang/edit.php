<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Ubah Penyulang<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Ubah Penyulang<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('penyulang') ?>">Master Penyulang</a></li>
<li class="breadcrumb-item active">Ubah</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-6 col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Form Ubah Penyulang</h3>
            </div>
            <form action="<?= site_url('penyulang/update/' . $penyulang['id']) ?>" method="post">
                <?= csrf_field() ?>
                <div class="card-body">
                    
                    <?php $validation = isset($validation) ? $validation : null; ?>

                    <div class="form-group mb-3">
                        <label for="id_unik_penyulang">ID Unik Penyulang (Permanen)</label>
                        <input type="text" id="id_unik_penyulang" class="form-control" value="<?= esc($penyulang['id_unik_penyulang']) ?>" readonly disabled>
                        <small class="form-text text-muted">ID Unik ini bersifat permanen dan tidak dapat diubah.</small>
                    </div>

                    <div class="form-group mb-3">
                        <label for="kode_penyulang">Kode Penyulang</label>
                        <input type="text" name="kode_penyulang" id="kode_penyulang" class="form-control <?= ($validation && $validation->hasError('kode_penyulang')) ? 'is-invalid' : '' ?>" value="<?= old('kode_penyulang', $penyulang['kode_penyulang']) ?>" required>
                        <?php if ($validation && $validation->hasError('kode_penyulang')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('kode_penyulang') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="nama_penyulang">Nama Penyulang</label>
                        <input type="text" name="nama_penyulang" id="nama_penyulang" class="form-control <?= ($validation && $validation->hasError('nama_penyulang')) ? 'is-invalid' : '' ?>" value="<?= old('nama_penyulang', $penyulang['nama_penyulang']) ?>" required>
                        <?php if ($validation && $validation->hasError('nama_penyulang')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('nama_penyulang') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="ulp_id">ULP</label>
                        <select name="ulp_id" id="ulp_id" class="form-control select2 <?= ($validation && $validation->hasError('ulp_id')) ? 'is-invalid' : '' ?>" required>
                            <?php foreach ($ulps as $ulp): ?>
                                <option value="<?= $ulp['id'] ?>" <?= old('ulp_id', $penyulang['ulp_id']) == $ulp['id'] ? 'selected' : '' ?>><?= esc($ulp['nama_ulp']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($validation && $validation->hasError('ulp_id')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('ulp_id') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control select2 <?= ($validation && $validation->hasError('status')) ? 'is-invalid' : '' ?>" required>
                            <option value="AKTIF" <?= old('status', $penyulang['status']) === 'AKTIF' ? 'selected' : '' ?>>AKTIF</option>
                            <option value="NONAKTIF" <?= old('status', $penyulang['status']) === 'NONAKTIF' ? 'selected' : '' ?>>NONAKTIF</option>
                        </select>
                        <?php if ($validation && $validation->hasError('status')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('status') ?></div>
                        <?php endif; ?>
                    </div>

                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                    <a href="<?= site_url('penyulang') ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
