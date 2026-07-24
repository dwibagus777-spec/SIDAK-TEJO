<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Master Penyulang<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Master Penyulang<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="#">Master Data</a></li>
<li class="breadcrumb-item active">Master Penyulang</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title"><i class="fas fa-plug text-primary mr-1"></i> Daftar Penyulang</h3>
                <div class="ml-auto d-flex" style="gap: 8px;">
                    <a href="<?= site_url('import/export-penyulang') ?>" class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i> Download CSV</a>
                    <a href="<?= site_url('penyulang/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i> Tambah Penyulang</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="table-penyulang">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>ID Unik Penyulang</th>
                                <th>Kode Penyulang</th>
                                <th>Nama Penyulang</th>
                                <th>Nama ULP</th>
                                <th>Status</th>
                                <th style="width: 150px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($penyulangs as $penyulang): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><span class="badge bg-dark border border-secondary text-primary font-monospace"><?= esc($penyulang['id_unik_penyulang']) ?></span></td>
                                    <td><?= esc($penyulang['kode_penyulang']) ?></td>
                                    <td><?= esc($penyulang['nama_penyulang']) ?></td>
                                    <td><?= esc($penyulang['nama_ulp']) ?></td>
                                    <td>
                                        <?php if ($penyulang['status'] === 'AKTIF'): ?>
                                            <span class="badge bg-success">AKTIF</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">NONAKTIF</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form id="delete-form-penyulang-<?= $penyulang['id'] ?>" action="<?= site_url('penyulang/delete/' . $penyulang['id']) ?>" method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="button" onclick="confirmDeleteForm('delete-form-penyulang-<?= $penyulang['id'] ?>', 'Penyulang <?= esc($penyulang['nama_penyulang'], 'js') ?>')" class="btn btn-sm btn-danger">
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
        $('#table-penyulang').DataTable({
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
