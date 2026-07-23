<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Ganti Password<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Ubah Password Akun<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Ganti Password</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">
                <h3 class="card-title m-0 text-white"><i class="fas fa-key me-2"></i> Ganti Password Akun Saya</h3>
            </div>
            <div class="card-body">

                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= session()->getFlashdata('error') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= esc($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($validation)): ?>
                    <div class="alert alert-danger">
                        <?= $validation->listErrors() ?>
                    </div>
                <?php endif; ?>

                <form action="<?= site_url('change-password') ?>" method="post">
                    <?= csrf_field() ?>

                    <!-- Password Saat Ini -->
                    <div class="mb-3">
                        <label class="form-label font-weight-bold" for="current_password">Password Lama Saat Ini <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="current_password" id="current_password" class="form-control" required placeholder="Masukkan password saat ini">
                            <button class="btn btn-outline-secondary toggle-pwd" type="button" data-target="current_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Password Baru -->
                    <div class="mb-3">
                        <label class="form-label font-weight-bold" for="new_password">Password Baru <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="new_password" class="form-control" required minlength="6" placeholder="Minimal 6 karakter">
                            <button class="btn btn-outline-secondary toggle-pwd" type="button" data-target="new_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Password minimal 6 karakter. Kombinasi huruf & angka direkomendasikan.</small>
                    </div>

                    <!-- Konfirmasi Password Baru -->
                    <div class="mb-4">
                        <label class="form-label font-weight-bold" for="confirm_password">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="6" placeholder="Ketik ulang password baru">
                            <button class="btn btn-outline-secondary toggle-pwd" type="button" data-target="confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary font-weight-bold py-2">
                            <i class="fas fa-save me-1"></i> SIMPAN PASSWORD BARU
                        </button>
                        <a href="<?= site_url('dashboard') ?>" class="btn btn-light text-muted">
                            Batal / Kembali ke Dashboard
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toggle-pwd').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var targetId = this.getAttribute('data-target');
            var input = document.getElementById(targetId);
            var icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
