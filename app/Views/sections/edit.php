<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Ubah Section<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Ubah Section<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('sections') ?>">Master Section</a></li>
<li class="breadcrumb-item active">Ubah</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-6 col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit mr-1"></i> Form Ubah Section</h3>
            </div>
            <form action="<?= site_url('sections/update/' . $section['id']) ?>" method="post">
                <?= csrf_field() ?>
                <div class="card-body">
                    
                    <?php $validation = isset($validation) ? $validation : null; ?>

                    <div class="form-group mb-3">
                        <label for="penyulang_id">Penyulang</label>
                        <select name="penyulang_id" id="penyulang_id" class="form-control select2 <?= ($validation && $validation->hasError('penyulang_id')) ? 'is-invalid' : '' ?>" required>
                            <?php foreach ($penyulangs as $penyulang): ?>
                                <option value="<?= $penyulang['id'] ?>" <?= old('penyulang_id', $section['penyulang_id']) == $penyulang['id'] ? 'selected' : '' ?>><?= esc($penyulang['nama_penyulang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($validation && $validation->hasError('penyulang_id')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('penyulang_id') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="nama_section">Nama Section</label>
                        <input type="text" name="nama_section" id="nama_section" class="form-control <?= ($validation && $validation->hasError('nama_section')) ? 'is-invalid' : '' ?>" value="<?= old('nama_section', $section['nama_section']) ?>" required>
                        <?php if ($validation && $validation->hasError('nama_section')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('nama_section') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control select2 <?= ($validation && $validation->hasError('status')) ? 'is-invalid' : '' ?>" required>
                            <option value="AKTIF" <?= old('status', $section['status']) === 'AKTIF' ? 'selected' : '' ?>>AKTIF</option>
                            <option value="NONAKTIF" <?= old('status', $section['status']) === 'NONAKTIF' ? 'selected' : '' ?>>NONAKTIF</option>
                        </select>
                        <?php if ($validation && $validation->hasError('status')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('status') ?></div>
                        <?php endif; ?>
                    </div>

                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                    <a href="<?= site_url('sections') ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
