<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Ubah ULP<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Ubah Unit Layanan Pelanggan (ULP)<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('ulps') ?>">Master ULP</a></li>
<li class="breadcrumb-item active">Ubah</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-6 col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Form Ubah ULP</h3>
            </div>
            <form action="<?= site_url('ulps/update/' . $ulp['id']) ?>" method="post">
                <?= csrf_field() ?>
                <div class="card-body">
                    
                    <?php $validation = isset($validation) ? $validation : null; ?>

                    <div class="form-group mb-3">
                        <label for="kode_ulp">Kode ULP</label>
                        <input type="text" name="kode_ulp" id="kode_ulp" class="form-control <?= ($validation && $validation->hasError('kode_ulp')) ? 'is-invalid' : '' ?>" value="<?= old('kode_ulp', $ulp['kode_ulp']) ?>" required>
                        <?php if ($validation && $validation->hasError('kode_ulp')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('kode_ulp') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="nama_ulp">Nama ULP</label>
                        <input type="text" name="nama_ulp" id="nama_ulp" class="form-control <?= ($validation && $validation->hasError('nama_ulp')) ? 'is-invalid' : '' ?>" value="<?= old('nama_ulp', $ulp['nama_ulp']) ?>" required>
                        <?php if ($validation && $validation->hasError('nama_ulp')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('nama_ulp') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control select2 <?= ($validation && $validation->hasError('status')) ? 'is-invalid' : '' ?>" required>
                            <option value="AKTIF" <?= old('status', $ulp['status']) === 'AKTIF' ? 'selected' : '' ?>>AKTIF</option>
                            <option value="NONAKTIF" <?= old('status', $ulp['status']) === 'NONAKTIF' ? 'selected' : '' ?>>NONAKTIF</option>
                        </select>
                        <?php if ($validation && $validation->hasError('status')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('status') ?></div>
                        <?php endif; ?>
                    </div>

                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                    <a href="<?= site_url('ulps') ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
