<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Tambah User<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Tambah User Baru<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('users') ?>">Master User</a></li>
<li class="breadcrumb-item active">Tambah</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-7 col-12">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-white py-3 border-0">
                <h3 class="card-title text-dark font-weight-bold m-0"><i class="fas fa-user-plus text-primary me-2"></i> Form Tambah User</h3>
            </div>
            <form action="<?= site_url('users/store') ?>" method="post">
                <?= csrf_field() ?>
                <div class="card-body">
                    
                    <?php $validation = isset($validation) ? $validation : null; ?>

                    <div class="row">
                        <div class="col-md-7 col-12 mb-3">
                            <label for="nama_pegawai" class="font-weight-bold">Nama Pegawai <span class="text-danger">*</span></label>
                            <input type="text" name="nama_pegawai" id="nama_pegawai" class="form-control <?= ($validation && $validation->hasError('nama_pegawai')) ? 'is-invalid' : '' ?>" placeholder="Contoh: Budi Santoso" value="<?= old('nama_pegawai') ?>" required>
                            <input type="hidden" name="nama" id="nama" value="<?= old('nama') ?>">
                        </div>
                        <div class="col-md-5 col-12 mb-3">
                            <label for="nip" class="font-weight-bold">NIP Pegawai</label>
                            <input type="text" name="nip" id="nip" class="form-control <?= ($validation && $validation->hasError('nip')) ? 'is-invalid' : '' ?>" placeholder="Contoh: 921710123" value="<?= old('nip') ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 col-12 mb-3">
                            <label for="username" class="font-weight-bold">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="username" class="form-control <?= ($validation && $validation->hasError('username')) ? 'is-invalid' : '' ?>" placeholder="Contoh: budi_sda" value="<?= old('username') ?>" required>
                        </div>
                        <div class="col-md-6 col-12 mb-3">
                            <label for="password" class="font-weight-bold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="password" class="form-control <?= ($validation && $validation->hasError('password')) ? 'is-invalid' : '' ?>" placeholder="Minimal 6 karakter" required>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="role" class="font-weight-bold">Hak Akses (Role) <span class="text-danger">*</span></label>
                        <select name="role" id="role" class="form-select select2 <?= ($validation && $validation->hasError('role')) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Pilih Role --</option>
                            <option value="administrator" <?= old('role') === 'administrator' ? 'selected' : '' ?>>Admin Pusat / Administrator (Semua Fitur)</option>
                            <option value="admin_ulp" <?= old('role') === 'admin_ulp' ? 'selected' : '' ?>>Admin ULP (Fitur per ULP)</option>
                            <option value="inspeksi" <?= old('role') === 'inspeksi' ? 'selected' : '' ?>>Inspeksi (Input Temuan & Fitur per ULP)</option>
                            <option value="yantek" <?= old('role') === 'yantek' ? 'selected' : '' ?>>Yantek (Update Pekerjaan, Temuan Terdekat, Gangguan)</option>
                            <option value="har_gardu" <?= old('role') === 'har_gardu' ? 'selected' : '' ?>>HAR Gardu (Data, Update, Terdekat, Eviden, Laporan)</option>
                            <option value="pdkb" <?= old('role') === 'pdkb' ? 'selected' : '' ?>>PDKB (Data, Update, Terdekat, Gangguan - PDKB)</option>
                            <option value="har_konstruksi" <?= old('role') === 'har_konstruksi' ? 'selected' : '' ?>>HAR Konstruksi (Data, Update, Terdekat, Gangguan)</option>
                            <option value="har_row" <?= old('role') === 'har_row' ? 'selected' : '' ?>>HAR ROW (Data, Update, Terdekat, Gangguan)</option>
                            <option value="supervisor_ulp" <?= old('role') === 'supervisor_ulp' ? 'selected' : '' ?>>Supervisor ULP (Monitoring per ULP)</option>
                            <option value="supervisor_up3" <?= old('role') === 'supervisor_up3' ? 'selected' : '' ?>>Supervisor UP3 (Cross ULP Monitoring)</option>
                            <option value="har_crane" <?= old('role') === 'har_crane' ? 'selected' : '' ?>>HAR Crane (Semua ULP)</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 col-12 mb-3">
                            <label for="ulp" class="font-weight-bold">Wilayah ULP <span class="text-danger">*</span></label>
                            <select name="ulp" id="ulp" class="form-select select2" required>
                                <option value="ADMIN" <?= old('ulp') === 'ADMIN' ? 'selected' : '' ?>>ADMIN (Pusat / UP3)</option>
                                <option value="SIDOARJO KOTA" <?= old('ulp') === 'SIDOARJO KOTA' ? 'selected' : '' ?>>SIDOARJO KOTA</option>
                                <option value="KRIAN" <?= old('ulp') === 'KRIAN' ? 'selected' : '' ?>>KRIAN</option>
                                <option value="PORONG" <?= old('ulp') === 'PORONG' ? 'selected' : '' ?>>PORONG</option>
                                <option value="AREA" <?= old('ulp') === 'AREA' ? 'selected' : '' ?>>AREA</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-12 mb-3">
                            <label for="ulp_id" class="font-weight-bold">ID Relasi Database ULP</label>
                            <select name="ulp_id" id="ulp_id" class="form-select select2">
                                <option value="">-- Semua ULP (Cross-ULP) --</option>
                                <?php foreach ($ulps as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= old('ulp_id') == $u['id'] ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="status" class="font-weight-bold">Status Akun</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="AKTIF" <?= old('status') === 'AKTIF' ? 'selected' : '' ?>>AKTIF</option>
                            <option value="NONAKTIF" <?= old('status') === 'NONAKTIF' ? 'selected' : '' ?>>NONAKTIF</option>
                        </select>
                    </div>

                </div>
                <div class="card-footer bg-white border-top py-3">
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Simpan User</button>
                    <a href="<?= site_url('users') ?>" class="btn btn-outline-secondary px-4"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function() {
        $('#nama_pegawai').on('input', function() {
            $('#nama').val($(this).val());
        });
    });
</script>
<?= $this->endSection() ?>
