<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Eviden Kubikel<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Eviden Lapangan Kubikel<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Eviden Kubikel</li>
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
<!-- PANEL FILTER DATA EVIDEN KUBIKEL -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card card-outline card-info shadow-sm">
            <div class="card-header py-2">
                <h3 class="card-title font-weight-bold text-info"><i class="fas fa-filter mr-1"></i> Penyaringan Data Eviden</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body py-3">
                <form method="GET" action="<?= site_url('eviden/kubikel') ?>" id="form-filter-eviden">
                    <div class="row">
                        <!-- Filter ULP -->
                        <div class="col-md-3 form-group mb-2">
                            <label for="filter_ulp_id" class="small font-weight-bold">Unit ULP</label>
                            <select name="ulp_id" id="filter_ulp_id" class="form-control form-control-sm select2">
                                <option value="">-- Semua ULP --</option>
                                <?php foreach ($ulps as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($filterUlp == $u['id']) ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Filter Penyulang -->
                        <div class="col-md-3 form-group mb-2">
                            <label for="filter_penyulang_id" class="small font-weight-bold">Penyulang</label>
                            <select name="penyulang_id" id="filter_penyulang_id" class="form-control form-control-sm select2">
                                <option value="">-- Semua Penyulang --</option>
                                <?php foreach ($penyulangs as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= ($filterPenyulang == $p['id']) ? 'selected' : '' ?>><?= esc($p['nama_penyulang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Tanggal Mulai -->
                        <div class="col-md-2 form-group mb-2">
                            <label for="filter_tgl_mulai" class="small font-weight-bold">Tanggal Mulai</label>
                            <input type="date" id="filter_tgl_mulai" name="tgl_mulai" class="form-control form-control-sm" value="<?= esc($filterTglMulai) ?>">
                        </div>
                        <!-- Tanggal Selesai -->
                        <div class="col-md-2 form-group mb-2">
                            <label for="filter_tgl_selesai" class="small font-weight-bold">Tanggal Selesai</label>
                            <input type="date" id="filter_tgl_selesai" name="tgl_selesai" class="form-control form-control-sm" value="<?= esc($filterTglSelesai) ?>">
                        </div>
                        <!-- Tombol Aksi Filter -->
                        <div class="col-md-2 form-group mb-2 d-flex align-items-end" style="gap: 6px;">
                            <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-search mr-1"></i> Filter</button>
                            <a href="<?= site_url('eviden/kubikel') ?>" class="btn btn-secondary btn-sm btn-block"><i class="fas fa-undo mr-1"></i> Reset</a>
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
            <input type="hidden" name="kategori" value="KUBIKEL">
            <div id="bulk-ids-container"></div>
        </form>

        <div class="card card-modern">
            <div class="card-header card-header-modern d-flex align-items-center justify-content-between">
                <h3 class="card-title text-white font-weight-bold">
                    <i class="fas fa-cubes mr-2"></i> Data Eviden Pemeliharaan Kubikel
                </h3>
                <div class="d-flex" style="gap: 8px;">
                    <button type="button" id="btn-download-pdf" class="btn btn-sm btn-danger font-weight-bold"><i class="fas fa-file-pdf mr-1"></i> Cetak PDF</button>
                    <button type="button" id="btn-download-zip" class="btn btn-sm btn-warning text-white font-weight-bold"><i class="fas fa-file-archive mr-1"></i> Unduh Foto (ZIP)</button>
                    <a href="<?= site_url('eviden/export-kubikel?' . http_build_query(service('request')->getGet())) ?>" class="btn btn-sm btn-success font-weight-bold"><i class="fas fa-download mr-1"></i> Download CSV</a>
                    <a href="<?= site_url('eviden/kubikel/create') ?>" class="btn btn-sm btn-light font-weight-bold text-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Tambah Data
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="table-eviden-kubikel" class="table table-hover table-modern text-center">
                        <thead>
                            <tr>
                                <th width="3%"><input type="checkbox" id="check-all"></th>
                                <th width="5%">No</th>
                                <th>Penyulang</th>
                                <th>Section</th>
                                <th>Nama Gardu</th>
                                <th>ID Pelanggan</th>
                                <th>Tanggal Input</th>
                                <th>Keterangan</th>
                                <th>Foto Eviden</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($dataList as $row): ?>
                                <tr>
                                    <td><input type="checkbox" class="check-item" value="<?= $row['id_kubikel'] ?>"></td>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= esc($row['nama_penyulang'] ?: '-') ?></strong></td>
                                    <td><?= esc($row['nama_section'] ?: '-') ?></td>
                                    <td><span class="badge bg-secondary p-2"><?= esc($row['nama_gardu'] ?: '-') ?></span></td>
                                    <td><span class="text-primary font-weight-bold"><?= esc($row['id_pel'] ?: '-') ?></span></td>
                                    <td><?= date('d-m-Y', strtotime($row['tgl_input'])) ?></td>
                                    <td class="text-left" style="min-width: 200px; font-size: 0.85rem;"><?= nl2br(esc($row['keterangan'])) ?></td>
                                    
                                    <!-- Foto Eviden Badge (Ringan tanpa render gambar langsung) -->
                                    <td>
                                        <?php if ($row['foto_count'] > 0): ?>
                                            <button type="button" class="btn btn-sm btn-info btn-view-fotos" data-parent-id="<?= $row['id_kubikel'] ?>" data-kategori="KUBIKEL" title="Lihat Galeri Foto">
                                                <i class="fas fa-images mr-1"></i> <?= $row['foto_count'] ?> Foto
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small"><i class="fas fa-image mr-1"></i> Tidak ada</span>
                                        <?php endif; ?>
                                    </td>
 
                                    <td>
                                        <a href="<?= site_url('eviden/kubikel/edit/' . $row['id_kubikel']) ?>" class="btn btn-action btn-detail-action mr-1" title="Ubah">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0)" onclick="confirmDelete(<?= $row['id_kubikel'] ?>)" class="btn btn-action btn-delete-action" title="Hapus">
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

<!-- ============================================================ -->
<!-- CUSTOM DYNAMIC GALLERY MODAL (Bebas Konflik Bootstrap JS)    -->
<!-- ============================================================ -->
<div id="modalEvidenFotos" class="custom-modal-backdrop" style="z-index: 1060;">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="background:#111827; color:#f3f4f6; border:1px solid #374151; max-height:85vh; display:flex; flex-direction:column; width:100%;">
            <div class="modal-header" style="background:linear-gradient(135deg,#005eb8,#003f8a); border-bottom:1px solid #374151; padding: 15px 20px;">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-images mr-2"></i> Galeri Foto Eviden</h5>
                <button type="button" class="btn-custom-close-header btn-custom-close" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="overflow-y:auto; padding:20px;">
                <div id="gallery-loading" class="text-center py-4">
                    <div class="spinner-border text-info" role="status"></div>
                    <p class="mt-2 text-muted">Mengambil galeri foto...</p>
                </div>
                <div id="gallery-content" class="d-none">
                    <!-- Dynamic categories and photos will be appended here -->
                </div>
            </div>
            <div class="modal-footer" style="background:#0b0f19; border-top:1px solid #374151; padding: 12px 20px;">
                <button type="button" class="btn btn-secondary btn-sm btn-custom-close">Tutup</button>
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
        const table = $('#table-eviden-kubikel').DataTable({
            "responsive": true,
            "autoWidth": false,
            "columnDefs": [
                { "orderable": false, "targets": 0 }
            ],
            "order": [[1, 'asc']],
            "language": {
                "url": "<?= base_url('plugins/datatables/i18n/id.json') ?>",
                "emptyTable": "Tidak ada data eviden kubikel"
            }
        });

        // Handle check-all checkbox
        $('#check-all').on('click', function() {
            const rows = table.rows({ 'search': 'applied' }).nodes();
            $('input[type="checkbox"]', rows).prop('checked', this.checked);
        });

        // Handle individual check change to update check-all state
        $('#table-eviden-kubikel tbody').on('change', 'input[type="checkbox"]', function() {
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

        // VIEW FOTOS AJAX
        $(document).on('click', '.btn-view-fotos', function() {
            const parentId = $(this).data('parent-id');
            const kategori = $(this).data('kategori');
            
            $('#gallery-loading').removeClass('d-none');
            $('#gallery-content').addClass('d-none').empty();
            $('#modalEvidenFotos').css('display', 'flex');
            $('body').css('overflow', 'hidden');
            
            $.ajax({
                url: '<?= site_url('eviden/ajax-get-fotos') ?>',
                type: 'GET',
                data: { id_parent: parentId, kategori: kategori },
                dataType: 'JSON',
                success: function(data) {
                    $('#gallery-loading').addClass('d-none');
                    $('#gallery-content').removeClass('d-none');
                    
                    if (!data || data.length === 0) {
                        $('#gallery-content').html('<div class="text-center py-4 text-muted"><i class="fas fa-image fa-2x mb-2"></i><br>Tidak ada foto eviden untuk data ini.</div>');
                        return;
                    }
                    
                    const groups = {};
                    data.forEach(function(item) {
                        const cat = item.jenis_foto || 'LAIN-LAIN';
                        if (!groups[cat]) groups[cat] = [];
                        groups[cat].push(item);
                    });
                    
                    for (const cat in groups) {
                        let catHtml = '<div class="mb-4">';
                        catHtml += '<h6 class="font-weight-bold text-info border-bottom pb-1 border-secondary" style="font-size:0.9rem; letter-spacing:0.5px; text-transform: uppercase;"><i class="fas fa-folder-open mr-1"></i> ' + cat + '</h6>';
                        catHtml += '<div class="row px-1">';
                        
                        groups[cat].forEach(function(photo) {
                            const url = '<?= base_url('foto/') ?>' + photo.nama_file;
                            catHtml += '<div class="col-4 col-sm-3 col-md-2 mb-2 px-1">';
                            catHtml += '<div style="height:75px; overflow:hidden; border-radius:6px; cursor:pointer; background:#000; border:1px solid #374151;" ';
                            catHtml += 'class="d-flex align-items-center justify-content-center" onclick="openLightbox(\'' + url + '\')">';
                            catHtml += '<img src="' + url + '" style="max-height:100%; max-width:100%; object-fit:cover;">';
                            catHtml += '</div></div>';
                        });
                        
                        catHtml += '</div></div>';
                        $('#gallery-content').append(catHtml);
                    }
                },
                error: function() {
                    $('#gallery-loading').addClass('d-none');
                    $('#gallery-content').removeClass('d-none').html('<div class="alert alert-danger m-2">Gagal mengambil galeri foto.</div>');
                }
            });
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

    function openLightbox(url) {
        $('#lightbox-img').attr('src', url);
        $('#modalFotoLightbox').css('display', 'flex');
        $('body').css('overflow', 'hidden');
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Apakah Anda Yakin?',
            text: "Data eviden kubikel beserta seluruh berkas foto terkait akan dihapus secara permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '<?= site_url('eviden/kubikel/delete/') ?>' + id,
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
