<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Management Trafo<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Management Trafo<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Management Trafo</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
.custom-modal-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 1060;
    align-items: center;
    justify-content: center;
}
.custom-modal-backdrop .modal-dialog {
    margin: auto;
    max-height: 90vh;
    width: 95%;
    max-width: 800px;
    display: flex;
    align-items: center;
    animation: premiumModalEntrance 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}
@keyframes premiumModalEntrance {
    from {
        opacity: 0;
        transform: scale(0.92) translateY(10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
.btn-custom-close-header {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 50%;
    color: #fff;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    font-size: 1.2rem;
    line-height: 1;
    cursor: pointer;
}
.btn-custom-close-header:hover {
    background: rgba(239, 68, 68, 0.9);
    transform: rotate(90deg);
}
</style>
<!-- PANEL FILTER DATA MANAGEMENT TRAFO -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card card-outline card-info shadow-sm">
            <div class="card-header py-2">
                <h3 class="card-title font-weight-bold text-info"><i class="fas fa-filter mr-1"></i> Penyaringan Data Management Trafo</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body py-3">
                <form method="GET" action="<?= site_url('eviden/management') ?>" id="form-filter-eviden">
                    <div class="row">
                        <!-- Filter ULP -->
                        <div class="col-md-3 form-group mb-2">
                            <label class="small font-weight-bold">Unit ULP</label>
                            <select name="ulp_id" id="filter_ulp_id" class="form-control form-control-sm select2">
                                <option value="">-- Semua ULP --</option>
                                <?php foreach ($ulps as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($filterUlp == $u['id']) ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Filter Penyulang -->
                        <div class="col-md-3 form-group mb-2">
                            <label class="small font-weight-bold">Penyulang</label>
                            <select name="penyulang_id" id="filter_penyulang_id" class="form-control form-control-sm select2">
                                <option value="">-- Semua Penyulang --</option>
                                <?php foreach ($penyulangs as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= ($filterPenyulang == $p['id']) ? 'selected' : '' ?>><?= esc($p['nama_penyulang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Tanggal Mulai -->
                        <div class="col-md-2 form-group mb-2">
                            <label class="small font-weight-bold">Tanggal Mulai</label>
                            <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= esc($filterTglMulai) ?>">
                        </div>
                        <!-- Tanggal Selesai -->
                        <div class="col-md-2 form-group mb-2">
                            <label class="small font-weight-bold">Tanggal Selesai</label>
                            <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= esc($filterTglSelesai) ?>">
                        </div>
                        <!-- Tombol Aksi Filter -->
                        <div class="col-md-2 form-group mb-2 d-flex align-items-end" style="gap: 6px;">
                            <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-search mr-1"></i> Filter</button>
                            <a href="<?= site_url('eviden/management') ?>" class="btn btn-secondary btn-sm btn-block"><i class="fas fa-undo mr-1"></i> Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <!-- Form for Bulk Action -->
        <form id="form-bulk-action" method="POST" style="display:none;">
            <?= csrf_field() ?>
            <input type="hidden" name="kategori" value="MANAGEMENT">
            <div id="bulk-ids-container"></div>
        </form>

        <div class="card card-modern">
            <div class="card-header card-header-modern d-flex align-items-center justify-content-between">
                <h3 class="card-title text-white font-weight-bold">
                    <i class="fas fa-folder-tree mr-2"></i> Data Management Nameplate Trafo
                </h3>
                <div class="d-flex" style="gap: 8px;">
                    <button type="button" id="btn-download-pdf" class="btn btn-sm btn-danger font-weight-bold"><i class="fas fa-file-pdf mr-1"></i> Cetak PDF</button>
                    <button type="button" id="btn-download-zip" class="btn btn-sm btn-warning text-white font-weight-bold"><i class="fas fa-file-archive mr-1"></i> Unduh Foto (ZIP)</button>
                    <a href="<?= site_url('eviden/export-management?' . http_build_query(service('request')->getGet())) ?>" class="btn btn-sm btn-success font-weight-bold"><i class="fas fa-download mr-1"></i> Download CSV</a>
                    <a href="<?= site_url('eviden/management/create') ?>" class="btn btn-sm btn-light font-weight-bold text-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Tambah Data
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="table-management-trafo" class="table table-hover table-modern text-center">
                        <thead>
                            <tr>
                                <th width="3%"><input type="checkbox" id="check-all"></th>
                                <th width="5%">No</th>
                                <th>Penyulang</th>
                                <th>Section</th>
                                <th>Nama Gardu</th>
                                <th>Tanggal Input</th>
                                <th>Nameplate Lama</th>
                                <th>Nameplate Baru</th>
                                <th>Keterangan</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($dataList as $row): ?>
                                <tr>
                                    <td><input type="checkbox" class="check-item" value="<?= $row['id_management'] ?>"></td>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= esc($row['nama_penyulang'] ?: '-') ?></strong></td>
                                    <td><?= esc($row['nama_section'] ?: '-') ?></td>
                                    <td><span class="badge bg-secondary p-2"><?= esc($row['nama_gardu'] ?: '-') ?></span></td>
                                    <td><?= date('d-m-Y', strtotime($row['tgl_input'])) ?></td>
                                    
                                    <!-- Nameplate Lama (Ringan) -->
                                    <td>
                                        <?php if (!empty($row['foto_nameplate_lama'])): ?>
                                            <button type="button" class="btn btn-xs btn-outline-info btn-view-single-foto" data-url="<?= base_url('foto/management/' . $row['foto_nameplate_lama']) ?>">
                                                <i class="fas fa-eye mr-1"></i> Lihat Foto
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Nameplate Baru (Ringan) -->
                                    <td>
                                        <?php if (!empty($row['foto_nameplate_baru'])): ?>
                                            <button type="button" class="btn btn-xs btn-outline-success btn-view-single-foto" data-url="<?= base_url('foto/management/' . $row['foto_nameplate_baru']) ?>">
                                                <i class="fas fa-eye mr-1"></i> Lihat Foto
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
 
                                    <td class="text-left" style="min-width: 200px; font-size: 0.85rem;"><?= nl2br(esc($row['keterangan'])) ?></td>
 
                                    <td>
                                        <a href="<?= site_url('eviden/management/edit/' . $row['id_management']) ?>" class="btn btn-action btn-detail-action mr-1" title="Ubah">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0)" onclick="confirmDelete(<?= $row['id_management'] ?>)" class="btn btn-action btn-delete-action" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </a>
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

<!-- Custom Lightbox Modal -->
<div id="modalFotoLightbox" class="custom-modal-backdrop" style="z-index:1100;">
    <div class="modal-dialog modal-dialog-centered" style="max-width:90vw; margin:0;">
        <div class="modal-content bg-dark border-0">
            <div class="modal-body p-1 text-center">
                <img id="lightbox-img" src="" style="max-height:85vh; max-width:100%; border-radius:4px;">
            </div>
            <button type="button" class="close btn-close btn-close-white position-absolute text-white btn-custom-close" aria-label="Close"
                    style="top:8px;right:12px;font-size:1.8rem;z-index:10; background: none; border: none; outline: none; opacity: 1;">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function () {
        // Init DataTable
        const table = $('#table-management-trafo').DataTable({
            "responsive": true,
            "autoWidth": false,
            "columnDefs": [
                { "orderable": false, "targets": 0 }
            ],
            "order": [[1, 'asc']],
            "language": {
                "url": "<?= base_url('plugins/datatables/i18n/id.json') ?>",
                "emptyTable": "Tidak ada data management trafo"
            }
        });

        // Handle check-all checkbox
        $('#check-all').on('click', function() {
            const rows = table.rows({ 'search': 'applied' }).nodes();
            $('input[type="checkbox"]', rows).prop('checked', this.checked);
        });

        // Handle individual check change to update check-all state
        $('#table-management-trafo tbody').on('change', 'input[type="checkbox"]', function() {
            if (!this.checked) {
                const el = $('#check-all').get(0);
                if (el && el.checked && ('indeterminate' in el)) {
                    el.checked = false;
                }
            }
        });

        // Helper to process bulk actions
        function submitBulkAction(actionUrl, isNewTab) {
            const container = $('#bulk-ids-container');
            container.empty();
            
            // Get checked inputs even from other pages in DataTable
            let count = 0;
            table.$('input[type="checkbox"]:checked').each(function() {
                container.append('<input type="hidden" name="selected_ids[]" value="' + $(this).val() + '">');
                count++;
            });

            if (count === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Perhatian',
                    text: 'Silakan pilih setidaknya satu data gardu terlebih dahulu.'
                });
                return;
            }

            const form = $('#form-bulk-action');
            form.attr('action', actionUrl);
            if (isNewTab) {
                form.attr('target', '_blank');
            } else {
                form.removeAttr('target');
            }
            form.submit();
        }

        // Action button click handlers
        $('#btn-download-pdf').on('click', function() {
            submitBulkAction('<?= site_url('eviden/download-pdf') ?>', true);
        });

        $('#btn-download-zip').on('click', function() {
            submitBulkAction('<?= site_url('eviden/download-foto') ?>', false);
        });

        // Initialize select2
        if ($('.select2').length) {
            $('.select2').select2({
                theme: 'bootstrap4'
            });
        }

        // Cascade ULP -> Penyulang
        $('#filter_ulp_id').change(function() {
            const ulpId = $(this).val();
            const penyulangSelect = $('#filter_penyulang_id');
            penyulangSelect.empty().append('<option value="">-- Semua Penyulang --</option>').trigger('change.select2');
            
            if (ulpId) {
                $.ajax({
                    url: '<?= site_url('import/ajax-penyulang') ?>',
                    type: 'GET',
                    data: { ulp_id: ulpId },
                    dataType: 'JSON',
                    success: function(data) {
                        $.each(data, function(index, item) {
                            penyulangSelect.append('<option value="' + item.id + '">' + item.nama_penyulang + '</option>');
                        });
                        penyulangSelect.trigger('change.select2');
                    }
                });
            }
        });

        // VIEW SINGLE FOTO (Lightbox)
        $(document).on('click', '.btn-view-single-foto', function() {
            const url = $(this).data('url');
            $('#lightbox-img').attr('src', url);
            $('#modalFotoLightbox').css('display', 'flex');
            $('body').css('overflow', 'hidden');
        });

        // Close modal handlers
        $(document).on('click', '.btn-custom-close', function () {
            $(this).closest('.custom-modal-backdrop').css('display', 'none');
            if ($('#modalEvidenFotos').css('display') === 'flex') {
                $('body').css('overflow', 'hidden');
            } else {
                $('body').css('overflow', '');
            }
        });

        $(document).on('click', '.custom-modal-backdrop', function (e) {
            if ($(e.target).hasClass('custom-modal-backdrop')) {
                $(e.target).css('display', 'none');
                if ($('#modalEvidenFotos').css('display') === 'flex') {
                    $('body').css('overflow', 'hidden');
                } else {
                    $('body').css('overflow', '');
                }
            }
        });
    });

    function confirmDelete(id) {
        Swal.fire({
            title: 'Apakah Anda Yakin?',
            text: "Data management trafo beserta berkas foto nameplate terkait akan dihapus secara permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '<?= site_url('eviden/management/delete/') ?>' + id,
                    type: 'GET',
                    dataType: 'JSON',
                    success: function(response) {
                        if(response.success) {
                            Swal.fire('Terhapus!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Gagal!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Gagal!', 'Terjadi kesalahan sistem saat menghapus data.', 'error');
                    }
                });
            }
        });
    }
</script>
<?= $this->endSection() ?>
