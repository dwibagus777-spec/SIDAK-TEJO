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
                                        <form id="delete-form-ulp-<?= $ulp['id'] ?>" action="<?= site_url('ulps/delete/' . $ulp['id']) ?>" method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="button" onclick="confirmDeleteForm('delete-form-ulp-<?= $ulp['id'] ?>')" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash mr-1"></i> Hapus
                                            </button>
                                        </form>
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

    window.confirmDeleteForm = function(formId, itemName) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: (itemName || 'Data') + ' akan dihapus dari sistem!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Menghapus...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                var f = document.getElementById(formId);
                if (f) f.submit();
            }
        });
    };
</script>
<?= $this->endSection() ?>
