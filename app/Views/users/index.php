<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Master User<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Master Pengguna (User)<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="#">Master Data</a></li>
<li class="breadcrumb-item active">Master User</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title"><i class="fas fa-users text-primary me-1"></i> Daftar User</h3>
                <a href="<?= site_url('users/create') ?>" class="btn btn-primary btn-sm ms-auto"><i class="fas fa-plus me-1"></i> Tambah User</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="table-users">
                        <thead>
                            <tr>
                                <th style="width: 40px;">No</th>
                                <th>Nama Pegawai / NIP</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Relasi ULP</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th style="width: 180px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <div class="font-weight-bold text-dark"><?= esc($user['nama_pegawai'] ?: $user['nama']) ?></div>
                                        <small class="text-muted" style="font-size: 11px;">NIP: <?= esc($user['nip'] ?: '-') ?></small>
                                    </td>
                                    <td><code><?= esc($user['username']) ?></code></td>
                                    <td>
                                        <span class="badge bg-info text-dark text-uppercase"><?= esc(get_role_label($user['role'])) ?></span>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= esc($user['ulp'] ?: ($user['nama_ulp'] ?: 'ADMIN')) ?></span></td>
                                    <td>
                                        <?php if ($user['status'] === 'AKTIF'): ?>
                                            <span class="badge bg-success">AKTIF</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">NONAKTIF</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $user['last_login'] ? date('d-m-Y H:i', strtotime($user['last_login'])) : '<span class="text-muted small">Belum pernah</span>' ?></td>
                                    <td>
                                        <button onclick="promptResetPassword(<?= $user['id'] ?>, '<?= esc($user['username'], 'js') ?>')" class="btn btn-xs btn-info text-white me-1" title="Reset Password User">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <a href="<?= site_url('users/edit/' . $user['id']) ?>" class="btn btn-xs btn-warning text-dark me-1" title="Ubah User"><i class="fas fa-edit"></i></a>
                                        <?php if ((int)session()->get('user_id') !== (int)$user['id']): ?>
                                            <a href="javascript:void(0)" onclick="confirmDelete(<?= $user['id'] ?>)" class="btn btn-xs btn-danger" title="Hapus User"><i class="fas fa-trash"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Form for Reset Password POST -->
<form id="form-reset-password" action="" method="post" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="new_password" id="reset_new_password_val">
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function () {
        $('#table-users').DataTable({
            "responsive": true,
            "autoWidth": false,
            "language": {
                "url": "<?= base_url('plugins/datatables/id.json') ?>"
            }
        });
    });

    function promptResetPassword(id, username) {
        Swal.fire({
            title: 'Reset Password User',
            text: 'Masukkan password baru untuk user "' + username + '":',
            input: 'text',
            inputValue: 'admin123',
            showCancelButton: true,
            confirmButtonText: 'Reset Password',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#0082C8',
            inputValidator: (value) => {
                if (!value || value.trim().length < 6) {
                    return 'Password baru minimal 6 karakter!';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                var form = document.getElementById('form-reset-password');
                form.action = "<?= site_url('users/reset-password/') ?>" + id;
                document.getElementById('reset_new_password_val').value = result.value;
                form.submit();
            }
        });
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "User ini akan dihapus dari sistem!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Menghapus User...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: "<?= site_url('users/delete/') ?>" + id,
                    type: "GET",
                    dataType: "JSON",
                    success: function(response) {
                        if (response && response.success) {
                            Swal.fire({
                                title: 'Terhapus!',
                                text: response.message,
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Gagal!',
                                text: (response && response.message) ? response.message : 'Gagal menghapus User.',
                                icon: 'error'
                            });
                        }
                    },
                    error: function(xhr) {
                        let msg = 'Gagal menghapus User dari server.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        Swal.fire({
                            title: 'Gagal!',
                            text: msg,
                            icon: 'error'
                        });
                    }
                });
            }
        });
    }
</script>
<?= $this->endSection() ?>
