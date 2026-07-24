<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Master ULP<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Master Unit Layanan Pelanggan (ULP)<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="#">Master Data</a></li>
<li class="breadcrumb-item active">Master ULP</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title"><i class="fas fa-network-wired text-primary mr-1"></i> Daftar ULP</h3>
                <a href="<?= site_url('ulps/create') ?>" class="btn btn-primary btn-sm ml-auto"><i class="fas fa-plus mr-1"></i> Tambah ULP</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="table-ulp">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Kode ULP</th>
                                <th>Nama ULP</th>
                                <th>Status</th>
                                <th style="width: 150px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($ulps as $ulp): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= esc($ulp['kode_ulp']) ?></td>
                                    <td><?= esc($ulp['nama_ulp']) ?></td>
                                    <td>
                                        <?php if ($ulp['status'] === 'AKTIF'): ?>
                                            <span class="badge bg-success">AKTIF</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">NONAKTIF</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= site_url('ulps/edit/' . $ulp['id']) ?>" class="btn btn-sm btn-warning text-dark"><i class="fas fa-edit mr-1"></i> Ubah</a>
                                        <a href="<?= site_url('ulps/delete/' . $ulp['id']) ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus ULP <?= esc(addslashes($ulp['nama_ulp']), 'js') ?>?');" class="btn btn-sm btn-danger"><i class="fas fa-trash mr-1"></i> Hapus</a>
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
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function () {
        $('#table-ulp').DataTable({
            "responsive": true,
            "autoWidth": false,
            "language": {
                "url": "<?= base_url('plugins/datatables/id.json') ?>"
            }
        });
    });

    function confirmDelete(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Data ULP ini akan dihapus dari sistem!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Menghapus ULP...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                $.ajax({
                    url: "<?= site_url('ulps/delete/') ?>" + id,
                    type: "POST",
                    data: { "<?= csrf_token() ?>": "<?= csrf_hash() ?>" },
                    dataType: "JSON",
                    success: function(res) {
                        if (res && res.success) {
                            Swal.fire({
                                title: 'Terhapus!',
                                text: res.message,
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => { window.location.reload(); });
                        } else {
                            Swal.fire({ title: 'Gagal!', text: (res && res.message) ? res.message : 'Gagal menghapus ULP.', icon: 'error' });
                        }
                    },
                    error: function(xhr) {
                        let msg = 'Gagal menghapus ULP dari server.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        Swal.fire({ title: 'Gagal!', text: msg, icon: 'error' });
                    }
                });
            }
        });
    }
</script>
<?= $this->endSection() ?>
