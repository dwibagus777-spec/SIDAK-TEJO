<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Master Section<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Master Section<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="#">Master Data</a></li>
<li class="breadcrumb-item active">Master Section</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title"><i class="fas fa-network-wired text-primary mr-1"></i> Daftar Section</h3>
                <div class="ml-auto d-flex" style="gap: 8px;">
                    <a href="<?= site_url('import/export-section') ?>" class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i> Download CSV</a>
                    <a href="<?= site_url('sections/create') ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i> Tambah Section</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="table-sections">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Nama Section</th>
                                <th>Nama Penyulang</th>
                                <th>Nama ULP</th>
                                <th>Status</th>
                                <th style="width: 150px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($sections as $section): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= esc($section['nama_section']) ?></td>
                                    <td><?= esc($section['nama_penyulang']) ?></td>
                                    <td><?= esc($section['nama_ulp']) ?></td>
                                    <td>
                                        <?php if ($section['status'] === 'AKTIF'): ?>
                                            <span class="badge bg-success">AKTIF</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">NONAKTIF</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= site_url('sections/edit/' . $section['id']) ?>" class="btn btn-sm btn-warning text-dark"><i class="fas fa-edit mr-1"></i> Ubah</a>
                                        <button type="button" onclick="executeDelete('<?= site_url('sections/delete/' . $section['id']) ?>')" class="btn btn-sm btn-danger"><i class="fas fa-trash mr-1"></i> Hapus</button>
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

<!-- Hidden Global Form for Delete POST (Outside Table) -->
<form id="global-delete-form-sections" action="" method="post" style="display: none;">
    <?= csrf_field() ?>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function () {
        $('#table-sections').DataTable({
            "responsive": true,
            "autoWidth": false,
            "language": {
                "url": "<?= base_url('plugins/datatables/id.json') ?>"
            }
        });
    });

    window.executeDelete = function(targetUrl) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Data Section ini akan dihapus dari sistem!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Menghapus Section...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                var f = document.getElementById('global-delete-form-sections');
                if (f) {
                    f.action = targetUrl;
                    f.submit();
                }
            }
        });
    };
</script>
<?= $this->endSection() ?>
