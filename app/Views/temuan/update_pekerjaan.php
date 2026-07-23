<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Update Pekerjaan<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Update Progress Pekerjaan Lapangan<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('temuan') ?>">Temuan</a></li>
<li class="breadcrumb-item active">Update Pekerjaan</li>
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
    z-index: 2000;
    align-items: center;
    justify-content: center;
    padding: 12px;
}

.custom-modal-backdrop.active {
    display: flex !important;
}

.custom-modal-backdrop .modal-dialog {
    margin: auto;
    max-height: 92vh;
    width: 100%;
    max-width: 750px;
    display: flex;
    flex-direction: column;
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

.custom-modal-backdrop .modal-content {
    background: #ffffff !important;
    border: 1px solid rgba(0, 0, 0, 0.15) !important;
    border-radius: 14px !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 92vh;
    width: 100%;
}

.custom-modal-backdrop form {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

.custom-modal-backdrop .modal-header {
    flex-shrink: 0;
    padding: 14px 20px !important;
}

.custom-modal-backdrop .modal-body {
    padding: 20px !important;
    flex: 1;
    overflow-y: auto;
    max-height: calc(92vh - 130px);
}

.custom-modal-backdrop .modal-footer {
    flex-shrink: 0;
    padding: 14px 20px !important;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    background: #f8f9fa !important;
    border-top: 1px solid #dee2e6 !important;
}

@media (max-width: 576px) {
    .custom-modal-backdrop {
        padding: 8px;
    }
    .custom-modal-backdrop .modal-dialog {
        max-height: 96vh;
    }
    .custom-modal-backdrop .modal-body {
        padding: 15px !important;
        max-height: calc(96vh - 140px);
    }
    .custom-modal-backdrop .modal-footer {
        padding: 10px 15px !important;
        flex-wrap: nowrap;
    }
    .custom-modal-backdrop .modal-footer .btn {
        flex: 1;
        padding: 8px 12px;
        font-size: 0.875rem;
    }
}

.btn-custom-close-header {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-custom-close-header:hover {
    background: #ef4444;
    transform: scale(1.1);
    color: #fff;
}
</style>

<!-- PANEL FILTER DATA TEMUAN -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card card-outline card-warning shadow-sm">
            <div class="card-header py-2">
                <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-filter text-warning mr-1"></i> Penyaringan Tugas Pelaksana</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body py-3">
                <div class="row">
                    <!-- 1. Filter ULP -->
                    <div class="col-md-2 form-group mb-2">
                        <label class="small font-weight-bold text-dark">Unit ULP</label>
                        <?php if ($isRestricted): ?>
                            <select id="filter_ulp_id" class="form-control form-control-sm select2" disabled autocomplete="off">
                                <option value="<?= $ulps[0]['id'] ?>"><?= esc($ulps[0]['nama_ulp']) ?></option>
                            </select>
                        <?php else: ?>
                            <select id="filter_ulp_id" class="form-control form-control-sm select2" autocomplete="off">
                                <option value="">-- Semua ULP --</option>
                                <?php foreach ($ulps as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= esc($u['nama_ulp']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <!-- 2. Filter Penyulang -->
                    <div class="col-md-2 form-group mb-2">
                        <label class="small font-weight-bold text-dark">Penyulang</label>
                        <select id="filter_penyulang_id" class="form-control form-control-sm select2" autocomplete="off">
                            <option value="">-- Semua Penyulang --</option>
                            <?php foreach ($penyulangs as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= esc($p['nama_penyulang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter Section -->
                    <div class="col-md-2 form-group mb-2">
                        <label class="small font-weight-bold text-dark">Section</label>
                        <select id="filter_section_id" class="form-control form-control-sm select2" autocomplete="off">
                            <option value="">-- Semua Section --</option>
                        </select>
                    </div>

                    <!-- 3. Filter Pelaksana (Locked if specific role) -->
                    <div class="col-md-2 form-group mb-2">
                        <label class="small font-weight-bold text-dark">Pelaksana</label>
                        <?php if ($lockedPelaksana): ?>
                            <select id="filter_pelaksana" class="form-control form-control-sm select2" disabled>
                                <option value="<?= $lockedPelaksana ?>"><?= $lockedPelaksana ?></option>
                            </select>
                        <?php else: ?>
                            <select id="filter_pelaksana" class="form-control form-control-sm select2">
                                <option value="">-- Semua --</option>
                                <option value="PDKB">PDKB</option>
                                <option value="HAR GARDU">HAR GARDU</option>
                                <option value="HAR KONSTRUKSI">HAR KONSTRUKSI</option>
                                <option value="HAR ROW">HAR ROW</option>
                                <option value="HAR CRANE">HAR CRANE</option>
                                <option value="YANTEK">YANTEK</option>
                            </select>
                        <?php endif; ?>
                    </div>

                    <!-- 4. Filter Status -->
                    <div class="col-md-3 form-group mb-2">
                        <label class="small font-weight-bold text-dark">Status Pekerjaan</label>
                        <select id="filter_status" class="form-control form-control-sm select2">
                            <option value="BELUM SELESAI" selected>BELUM SELESAI</option>
                            <option value="PROSES">SEDANG PROSES</option>
                            <option value="TERKENDALA">TERKENDALA</option>
                            <option value="BUTUH PADAM">BUTUH PADAM</option>
                            <option value="BELUM">BELUM DIKERJAKAN</option>
                            <option value="SELESAI">SUDAH SELESAI</option>
                            <option value="">-- Semua Status --</option>
                        </select>
                    </div>

                    <!-- 5. Button Reset -->
                    <div class="col-md-1 form-group mb-2 d-flex align-items-end">
                        <button type="button" id="btn-reset-filter" class="btn btn-sm btn-secondary btn-block font-weight-bold w-100">
                            <i class="fas fa-sync mr-1"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DATA TABLE TEMUAN -->
<div class="row">
    <div class="col-12">
        <div class="card card-dark card-outline shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="table-update-pekerjaan" class="table table-modern" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>No Temuan</th>
                                <th>Penyulang</th>
                                <th>Section</th>
                                <th>Jenis</th>
                                <th>Foto</th>
                                <th>Prioritas</th>
                                <th>Tgl Temuan</th>
                                <th>Status SLA</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Loaded via DataTables AJAX Server-Side -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- ============================================================ -->
<!-- MODAL UPDATE STATUS (TINDAK LANJUT QUICK UPDATE - CUSTOM BACKDROP) -->
<!-- ============================================================ -->
<div class="custom-modal-backdrop" id="modal-update-status">
    <div class="modal-dialog">
        <div class="modal-content border-0 bg-white text-dark">
            <div class="modal-header py-3 bg-warning" style="background:#ffc107 !important; border-bottom: none !important;">
                <h5 class="modal-title font-weight-bold text-dark" id="modalUpdateLabel">
                    <i class="fas fa-edit mr-1"></i> Update Status Pekerjaan
                </h5>
                <button type="button" class="btn-custom-close-header" id="btn-close-update" style="background:rgba(0,0,0,0.1); color:#333;"><i class="fas fa-times"></i></button>
            </div>
            <form id="form-update-status" action="" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-body bg-white text-dark">
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Nomor Temuan</label>
                        <input type="text" id="update-nomor-temuan" class="form-control bg-light" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status_progress" class="form-label font-weight-bold">Status Progress <span class="text-danger">*</span></label>
                        <select name="status_progress" id="status_progress" class="form-select form-control" required>
                            <option value="">-- Pilih Status --</option>
                            <option value="PROSES">PROSES (Sedang Dikerjakan)</option>
                            <option value="TERKENDALA">TERKENDALA (Terkendala Bahan / Akses / Cuaca)</option>
                            <option value="BUTUH PADAM">BUTUH PADAM (Butuh Pemadaman Listrik)</option>
                            <option value="SELESAI">SELESAI (Pekerjaan Selesai)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="komentar" class="form-label font-weight-bold">Keterangan / Catatan Tindak Lanjut <span class="text-danger">*</span></label>
                        <textarea name="komentar" id="komentar" class="form-control" rows="3" placeholder="Masukkan detail perkembangan pekerjaan..." required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label small font-weight-bold">Foto Sebelum</label>
                            <input type="file" name="foto_sebelum" class="form-control form-control-sm" accept="image/*">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label small font-weight-bold">Foto Proses</label>
                            <input type="file" name="foto_proses" class="form-control form-control-sm" accept="image/*">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label small font-weight-bold">Foto Sesudah</label>
                            <input type="file" name="foto_sesudah" class="form-control form-control-sm" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top" style="background:#f8f9fa !important; border-top: 1px solid #dee2e6 !important;">
                    <button type="button" class="btn btn-secondary font-weight-bold btn-sm" id="btn-close-update-footer">Batal</button>
                    <button type="submit" class="btn btn-warning text-dark font-weight-bold btn-sm">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- GLASSMORPHIC DETAIL MODAL                                    -->
<!-- ============================================================ -->
<div class="custom-modal-backdrop" id="modal-detail">
    <div class="modal-dialog">
        <div class="modal-content shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold text-white"><i class="fas fa-info-circle text-info mr-2"></i> Rincian Temuan & Histori Tindak Lanjut</h5>
                <button type="button" class="btn-custom-close-header" id="btn-close-detail"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div id="detail-loading" class="text-center py-5">
                    <div class="spinner-border text-info mb-2" role="status"></div>
                    <p class="text-muted small">Mengambil rincian data...</p>
                </div>
                <div id="detail-content" class="d-none">
                    <!-- Dynamic details populated via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary font-weight-bold px-4" id="btn-close-detail-footer">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- LIGHTBOX PREVIEW -->
<div id="custom-lightbox" onclick="closeLightbox()" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.9); z-index: 999999; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
    <button type="button" onclick="closeLightbox()" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.25); border: 1px solid rgba(255,255,255,0.4); color: #fff; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; cursor: pointer; transition: all 0.2s ease; z-index: 1000000; box-shadow: 0 4px 12px rgba(0,0,0,0.5);" onmouseover="this.style.background='rgba(255,255,255,0.4)'" onmouseout="this.style.background='rgba(255,255,255,0.25)'" title="Tutup Preview (Atau klik latar belakang)">
        <i class="fas fa-times"></i>
    </button>
    <div style="position: relative; max-width: 90%; max-height: 88%; display: flex; flex-direction: column; align-items: center;" onclick="event.stopPropagation();">
        <img id="lightbox-img" src="" style="max-width: 100%; max-height: 75vh; object-fit: contain; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.8); border: 2px solid rgba(255,255,255,0.2);">
        <button type="button" onclick="closeLightbox()" class="btn btn-sm btn-secondary font-weight-bold mt-3 px-4" style="border-radius: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.4);">
            <i class="fas fa-times mr-1"></i> Tutup Gambar
        </button>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    let dataTable;

    $(function() {
        // Initialize DataTable server-side
        dataTable = $('#table-update-pekerjaan').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '<?= site_url('temuan/ajax-update-pekerjaan') ?>',
                type: 'POST',
                data: function(d) {
                    d.<?= csrf_token() ?> = "<?= csrf_hash() ?>";
                    d.ulp_id = $('#filter_ulp_id').val();
                    d.penyulang_id = $('#filter_penyulang_id').val();
                    d.section_id = $('#filter_section_id').val();
                    d.pelaksana = $('#filter_pelaksana').val();
                    d.status = $('#filter_status').val();
                }
            },
            order: [[0, 'desc']], // Order by ID/Nomor Temuan desc
            responsive: true,
            autoWidth: false,
            columns: [
                { data: 0, className: 'font-monospace font-weight-bold' },
                { data: 1 },
                { data: 2 },
                { data: 3 },
                { data: 4, orderable: false },
                { data: 5 },
                { data: 6 },
                { data: 7 },
                { data: 8, orderable: false }
            ],
            language: {
                url: "<?= base_url('plugins/datatables/id.json') ?>"
            }
        });

        // Trigger filters redraw
        $('#filter_ulp_id, #filter_penyulang_id, #filter_section_id, #filter_pelaksana, #filter_status').change(function() {
            dataTable.ajax.reload();
        });

        // Dynamic Penyulang loading on ULP change
        $('#filter_ulp_id').change(function() {
            const ulpId = $(this).val();
            const penyulangSelect = $('#filter_penyulang_id');
            const sectionSelect = $('#filter_section_id');
            penyulangSelect.empty().append('<option value="">-- Semua Penyulang --</option>').trigger('change.select2');
            sectionSelect.empty().append('<option value="">-- Semua Section --</option>').trigger('change.select2');
            
            if (ulpId) {
                $.ajax({
                    url: '<?= site_url('temuan/ajax-penyulang/') ?>' + ulpId,
                    type: 'GET',
                    dataType: 'JSON',
                    success: function(data) {
                        $.each(data, function(i, item) {
                            penyulangSelect.append('<option value="' + item.id + '">' + item.nama_penyulang + '</option>');
                        });
                        penyulangSelect.trigger('change.select2');
                    }
                });
            }
        });

        // Sync ULP filter dropdown values on load to prevent browser cache mismatch
        if (!$('#filter_ulp_id').is(':disabled')) {
            $('#filter_ulp_id').trigger('change.select2');
        }

        // Cascade Penyulang -> Section
        $('#filter_penyulang_id').change(function() {
            const penyulangId = $(this).val();
            const sectionSelect = $('#filter_section_id');
            
            sectionSelect.empty().append('<option value="">-- Semua Section --</option>').trigger('change.select2');
            
            if (penyulangId) {
                $.ajax({
                    url: '<?= site_url('temuan/ajax-section/') ?>' + penyulangId,
                    type: 'GET',
                    dataType: 'JSON',
                    success: function(data) {
                        $.each(data, function(i, item) {
                            sectionSelect.append('<option value="' + item.id + '">' + item.nama_section + '</option>');
                        });
                        sectionSelect.trigger('change.select2');
                    }
                });
            }
        });

        // Reset filter
        $('#btn-reset-filter').click(function() {
            if (!$('#filter_ulp_id').is(':disabled')) {
                $('#filter_ulp_id').val('').trigger('change.select2');
            }
            $('#filter_penyulang_id').val('').trigger('change.select2');
            $('#filter_section_id').val('').trigger('change.select2');
            if (!$('#filter_pelaksana').is(':disabled')) {
                $('#filter_pelaksana').val('').trigger('change.select2');
            }
            $('#filter_status').val('BELUM SELESAI').trigger('change.select2'); // default back to incomplete
            dataTable.draw();
        });

        // Detail modal trigger via ajax
        $(document).on('click', '.btn-detail-modal', function() {
            const id = $(this).data('id');
            $('#detail-loading').removeClass('d-none');
            $('#detail-content').addClass('d-none');
            $('#modal-detail').addClass('active');

            $.ajax({
                url: '<?= site_url('temuan/ajax-detail/') ?>' + id,
                type: 'GET',
                dataType: 'JSON',
                success: function(data) {
                    $('#detail-loading').addClass('d-none');
                    $('#detail-content').removeClass('d-none');

                    const temuan = data.temuan;
                    const sla = data.sla;
                    const history = data.history;

                    let photosHtml = '<div class="row">';
                    const photos = JSON.parse(temuan.foto || '[]');
                    if (photos.length > 0) {
                        photos.forEach(p => {
                            const url = '<?= base_url() ?>' + temuan.foto_path + p;
                            photosHtml += `
                                <div class="col-md-3 col-6 mb-2">
                                    <img src="${url}" class="img-fluid rounded border shadow-sm" style="cursor:pointer; max-height:120px; object-fit:cover; width:100%;" onclick="openLightbox('${url}')">
                                </div>
                            `;
                        });
                    } else {
                        photosHtml += '<div class="col-12"><p class="text-secondary small italic">Tidak ada foto terlampir.</p></div>';
                    }
                    photosHtml += '</div>';

                    // Histori Progress
                    let historyHtml = '<div class="timeline timeline-inverse">';
                    if (history.length > 0) {
                        history.forEach(h => {
                            let progBadge = `<span class="badge bg-info">${h.status_progress || 'PROSES'}</span>`;
                            if (h.status_progress === 'SELESAI') progBadge = `<span class="badge bg-success">${h.status_progress}</span>`;
                            else if (h.status_progress === 'BUTUH PADAM') progBadge = `<span class="badge bg-danger">${h.status_progress}</span>`;
                            else if (h.status_progress === 'TERKENDALA') progBadge = `<span class="badge bg-warning text-dark">${h.status_progress}</span>`;

                            let progressPhotos = '';
                            if (h.foto_sebelum) progressPhotos += `<img src="<?= base_url() ?>/${h.foto_sebelum}" style="height:50px; max-width: 80px; object-fit:cover; margin-right:5px; border-radius:4px; cursor:pointer;" onclick="openLightbox('<?= base_url() ?>/${h.foto_sebelum}')" title="Foto Sebelum">`;
                            if (h.foto_proses) progressPhotos += `<img src="<?= base_url() ?>/${h.foto_proses}" style="height:50px; max-width: 80px; object-fit:cover; margin-right:5px; border-radius:4px; cursor:pointer;" onclick="openLightbox('<?= base_url() ?>/${h.foto_proses}')" title="Foto Proses">`;
                            if (h.foto_sesudah) progressPhotos += `<img src="<?= base_url() ?>/${h.foto_sesudah}" style="height:50px; max-width: 80px; object-fit:cover; margin-right:5px; border-radius:4px; cursor:pointer;" onclick="openLightbox('<?= base_url() ?>/${h.foto_sesudah}')" title="Foto Sesudah">`;

                            historyHtml += `
                                <div>
                                    <i class="fas fa-circle-info bg-secondary"></i>
                                    <div class="timeline-item bg-dark border-secondary text-white">
                                        <span class="time text-muted"><i class="far fa-clock mr-1"></i>${h.created_at}</span>
                                        <h4 class="timeline-header font-weight-bold" style="font-size:0.95rem;">Oleh: ${h.pelaksana}</h4>
                                        <div class="timeline-body small" style="line-height:1.4;">
                                            Status: ${progBadge}<br>
                                            Komentar: <span class="italic text-light font-weight-bold">"${h.komentar}"</span>
                                            ${progressPhotos ? '<div class="mt-2">' + progressPhotos + '</div>' : ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        historyHtml += '<div class="text-center py-3 text-secondary small">Belum ada histori tindak lanjut.</div>';
                    }
                    historyHtml += '</div>';

                    $('#detail-content').html(`
                        <div class="row">
                            <div class="col-md-6 border-right border-secondary">
                                <h6 class="font-weight-bold text-info"><i class="fas fa-circle-info mr-1"></i> Informasi Temuan</h6>
                                <table class="table table-sm table-borderless text-white small">
                                    <tr><td style="width:140px;">No Temuan</td><td>: <b>${temuan.nomor_temuan}</b></td></tr>
                                    <tr><td>ULP</td><td>: ${temuan.nama_ulp}</td></tr>
                                    <tr><td>Penyulang</td><td>: ${temuan.nama_penyulang}</td></tr>
                                    <tr><td>Section</td><td>: ${temuan.nama_section}</td></tr>
                                    <tr><td>Jenis Temuan</td><td>: ${temuan.jenis_temuan}</td></tr>
                                    <tr><td>Pelaksana</td><td>: ${temuan.pelaksana}</td></tr>
                                    <tr><td>Prioritas</td><td>: <span class="badge bg-secondary">${temuan.prioritas}</span></td></tr>
                                    <tr><td>Potensi Gangguan</td><td>: <span class="badge bg-info text-dark">${temuan.potensi_gangguan}</span></td></tr>
                                    <tr><td>SLA & Status</td><td>: ${sla.badge_html}</td></tr>
                                    <tr><td>Tanggal Temuan</td><td>: ${temuan.tanggal_temuan}</td></tr>
                                    <tr><td>Alamat / Lokasi</td><td>: ${temuan.alamat}</td></tr>
                                    <tr><td>Detail Kerusakan</td><td>: <span class="text-warning font-weight-bold">${temuan.detail_temuan}</span></td></tr>
                                    <tr><td>Kebutuhan Material</td><td>: ${temuan.material}</td></tr>
                                </table>
                                <h6 class="font-weight-bold text-info mt-3"><i class="fas fa-camera mr-1"></i> Galeri Foto Temuan</h6>
                                ${photosHtml}
                            </div>
                            <div class="col-md-6 pl-md-4">
                                <h6 class="font-weight-bold text-info"><i class="fas fa-clock-rotate-left mr-1"></i> Histori Tindak Lanjut</h6>
                                <div style="max-height: 450px; overflow-y: auto;">
                                    ${historyHtml}
                                </div>
                            </div>
                        </div>
                    `);
                }
            });
        });

        // Close detail modal
        $('#btn-close-detail, #btn-close-detail-footer').click(function() {
            $('#modal-detail').removeClass('active');
        });

        // Update status modal trigger
        $(document).on('click', '.btn-update-status', function() {
            const id = $(this).data('id');
            const nomor = $(this).data('nomor');
            
            // Set form action and nomor temuan
            $('#form-update-status').attr('action', '<?= site_url('temuan/tindak-lanjut/') ?>' + id);
            $('#update-nomor-temuan').val(nomor);
            
            // Reset input values
            $('#status_progress').val('');
            $('#komentar').val('');
            $('#form-update-status').find('input[type="file"]').val('');

            // Show custom Modal
            $('#modal-update-status').addClass('active');
            $('body').css('overflow', 'hidden');
        });

        // Close update status modal
        $('#btn-close-update, #btn-close-update-footer').click(function() {
            $('#modal-update-status').removeClass('active');
            $('body').css('overflow', 'auto');
        });

        // Helper for image compression
        function compressImage(file) {
            return new Promise((resolve) => {
                if (!file || !file.type.startsWith('image/') || file.size <= 400 * 1024) {
                    resolve(file);
                    return;
                }
                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = function(e) {
                    const img = new Image();
                    img.src = e.target.result;
                    img.onload = function() {
                        let w = img.width, h = img.height;
                        const maxDim = 1600;
                        if (w > maxDim || h > maxDim) {
                            if (w > h) { h = Math.round((h * maxDim) / w); w = maxDim; }
                            else { w = Math.round((w * maxDim) / h); h = maxDim; }
                        }
                        const canvas = document.createElement('canvas');
                        canvas.width = w; canvas.height = h;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, w, h);
                        canvas.toBlob(function(blob) {
                            if (blob && blob.size < file.size) {
                                const newFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", {
                                    type: 'image/jpeg',
                                    lastModified: Date.now()
                                });
                                resolve(newFile);
                            } else {
                                resolve(file);
                            }
                        }, 'image/jpeg', 0.8);
                    };
                    img.onerror = function() { resolve(file); };
                };
                reader.onerror = function() { resolve(file); };
            });
        }

        // Fast AJAX form submit with compressed FormData
        $('#form-update-status').submit(async function(e) {
            e.preventDefault();
            const form = $(this);
            const actionUrl = form.attr('action');
            const submitBtn = form.find('button[type="submit"]');
            const originalBtnHtml = submitBtn.html();

            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Mengompres & Menyimpan...');

            try {
                const formData = new FormData();
                formData.append('csrf_test_name', form.find('input[name="csrf_test_name"]').val() || '');
                formData.append('status_progress', $('#status_progress').val());
                formData.append('komentar', $('#komentar').val());

                const fSebelum = form.find('input[name="foto_sebelum"]')[0]?.files[0];
                const fProses  = form.find('input[name="foto_proses"]')[0]?.files[0];
                const fSesudah = form.find('input[name="foto_sesudah"]')[0]?.files[0];

                if (fSebelum) formData.append('foto_sebelum', await compressImage(fSebelum));
                if (fProses)  formData.append('foto_proses',  await compressImage(fProses));
                if (fSesudah) formData.append('foto_sesudah', await compressImage(fSesudah));

                $.ajax({
                    url: actionUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'JSON',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    success: function(res) {
                        submitBtn.prop('disabled', false).html(originalBtnHtml);
                        if (res.success) {
                            $('#modal-update-status').removeClass('active');
                            $('body').css('overflow', 'auto');
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: res.message || 'Progress tindak lanjut berhasil disimpan.',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            } else {
                                alert(res.message || 'Progress tindak lanjut berhasil disimpan.');
                            }
                            dataTable.ajax.reload();
                        } else {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Gagal',
                                    text: res.message || 'Terjadi kesalahan saat menyimpan.'
                                });
                            } else {
                                alert(res.message || 'Terjadi kesalahan saat menyimpan.');
                            }
                        }
                    },
                    error: function(xhr) {
                        submitBtn.prop('disabled', false).html(originalBtnHtml);
                        let errText = 'Terjadi kesalahan sistem atau koneksi.';
                        if (xhr.status === 413) {
                            errText = 'Ukuran berkas/foto terlalu besar (Error 413: Request Entity Too Large). Harap kurangi ukuran foto atau naikkan limit upload pada Nginx server.';
                        }
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal Upload (Error ' + xhr.status + ')',
                                text: errText
                            });
                        } else {
                            alert(errText);
                        }
                    }
                });
            } catch(err) {
                console.error(err);
                submitBtn.prop('disabled', false).html(originalBtnHtml);
            }
        });
    });

    function openLightbox(url) {
        $('#lightbox-img').attr('src', url);
        $('#custom-lightbox').css('display', 'flex');
    }

    function closeLightbox() {
        $('#custom-lightbox').css('display', 'none');
    }
</script>
<?= $this->endSection() ?>
