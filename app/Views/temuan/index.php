<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Data Temuan<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Data Temuan Inspeksi<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Data Temuan</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- ============================================================ -->
<!-- STUNNING CUSTOM GLASSMORPHIC MODAL & LIGHTBOX CSS            -->
<!-- ============================================================ -->
<style>
.custom-modal-backdrop {
    display: none; /* Hidden by default */
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.6); /* Sleek dark blue tint */
    backdrop-filter: blur(8px); /* Smooth blurred glassmorphism */
    -webkit-backdrop-filter: blur(8px);
    z-index: 1050;
    align-items: center;
    justify-content: center;
}

/* Premium smooth zoom & fade animation */
.custom-modal-backdrop.active {
    display: flex !important;
}

.custom-modal-backdrop .modal-dialog {
    margin: auto;
    max-height: 90vh;
    width: 90%;
    max-width: 1140px;
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

/* Card glass design with neon-glow border */
.custom-modal-backdrop .modal-content {
    background: #0f172a !important; /* Premium Slate-900 background */
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 14px !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
    overflow: hidden;
}

.custom-modal-backdrop .modal-header {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    padding: 18px 24px !important;
}

.custom-modal-backdrop .modal-body {
    padding: 24px !important;
}

.custom-modal-backdrop .modal-footer {
    background: #090d16 !important;
    border-top: 1px solid rgba(255, 255, 255, 0.06) !important;
    padding: 16px 24px !important;
}

/* Premium zoom effects on close button */
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

/* Horizontal Scroll & Table Compact Layout */
.temuan-table-wrapper {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-gutter: stable;
    padding-bottom: 8px;
}

.temuan-table {
    min-width: 1100px;
    width: 100% !important;
}

.temuan-date {
    width: 88px;
    min-width: 88px;
    font-size: 12px;
    line-height: 1.35;
    white-space: nowrap;
    text-align: center;
    margin: 0 auto;
}

.temuan-date small {
    font-size: 11px;
    color: #64748b;
    display: block;
}
</style>
<!-- PANEL FILTER DATA TEMUAN -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card card-outline card-info shadow-sm">
            <div class="card-header py-2">
                <h3 class="card-title font-weight-bold text-info"><i class="fas fa-filter mr-1"></i> Penyaringan Data Temuan</h3>
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
                        <label for="filter_ulp_id" class="small font-weight-bold">ULP</label>
                        <?php if ($isRestricted): ?>
                            <select id="filter_ulp_id" class="form-control form-control-sm" disabled autocomplete="off">
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
                        <label for="filter_penyulang_id" class="small font-weight-bold">Penyulang</label>
                        <select id="filter_penyulang_id" class="form-control form-control-sm select2" autocomplete="off">
                            <option value="">-- Semua Penyulang --</option>
                            <?php foreach ($penyulangs as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= esc($p['nama_penyulang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter Section -->
                    <div class="col-md-2 form-group mb-2">
                        <label for="filter_section_id" class="small font-weight-bold">Section</label>
                        <select id="filter_section_id" class="form-control form-control-sm select2" autocomplete="off">
                            <option value="">-- Semua Section --</option>
                        </select>
                    </div>

                    <!-- 3. Filter Pelaksana -->
                    <div class="col-md-2 form-group mb-2">
                        <label for="filter_pelaksana" class="small font-weight-bold">Pelaksana</label>
                        <select id="filter_pelaksana" class="form-control form-control-sm select2">
                            <option value="">-- Semua Pelaksana --</option>
                            <option value="PDKB">PDKB</option>
                            <option value="HAR GARDU">HAR GARDU</option>
                            <option value="HAR KONSTRUKSI">HAR KONSTRUKSI</option>
                            <option value="HAR ROW">HAR ROW</option>
                            <option value="HAR CRANE">HAR CRANE</option>
                            <option value="YANTEK">YANTEK</option>
                        </select>
                    </div>

                    <!-- 4. Filter Prioritas -->
                    <div class="col-md-2 form-group mb-2">
                        <label for="filter_prioritas" class="small font-weight-bold">Prioritas</label>
                        <select id="filter_prioritas" class="form-control form-control-sm select2">
                            <option value="">-- Semua Prioritas --</option>
                            <option value="EMERGENCY">EMERGENCY</option>
                            <option value="HIGH">HIGH</option>
                            <option value="MEDIUM">MEDIUM</option>
                        </select>
                    </div>

                    <!-- 5. Filter Status -->
                    <div class="col-md-2 form-group mb-2">
                        <label for="filter_status" class="small font-weight-bold">Status</label>
                        <select id="filter_status" class="form-control form-control-sm select2">
                            <option value="">-- Semua Status --</option>
                            <option value="BELUM">BELUM SELESAI</option>
                            <option value="PROSES">DALAM PROSES</option>
                            <option value="SELESAI">SUDAH SELESAI</option>
                        </select>
                    </div>

                    <!-- 6. Filter Tanggal Awal & Akhir -->
                    <div class="col-md-2 form-group mb-2">
                        <label for="filter_start_date" class="small font-weight-bold">Tgl Input (Awal)</label>
                        <input type="date" id="filter_start_date" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2 form-group mb-2">
                        <label for="filter_end_date" class="small font-weight-bold">Tgl Input (Akhir)</label>
                        <input type="date" id="filter_end_date" class="form-control form-control-sm">
                    </div>

                    <!-- 7. Button Reset -->
                    <div class="col-md-1 form-group mb-2 d-flex align-items-end">
                        <button type="button" id="btn-reset-filter" class="btn btn-sm btn-secondary btn-block font-weight-bold w-100">
                            <i class="fas fa-undo mr-1"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TABEL DATA TEMUAN -->
<div class="row">
    <div class="col-12">
        <div class="card card-modern">
            <div class="card-header border-bottom bg-transparent d-flex justify-content-between align-items-center py-3">
                <h3 class="card-title text-dark font-weight-bold"><i class="fas fa-list-check text-primary mr-1"></i> Tabel Temuan Inspeksi</h3>
                <?php if (check_role(['administrator', 'admin_ulp', 'inspeksi'])): ?>
                    <a href="<?= site_url('temuan/create') ?>" class="btn btn-primary btn-sm ml-auto"><i class="fas fa-plus mr-1"></i> Input Temuan</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="temuan-table-wrapper">
                    <table class="table table-modern temuan-table" id="table-temuan" style="width:100%;">
                        <thead>
                            <tr>
                                <th style="width: 110px; min-width: 110px;">Nomor Temuan</th>
                                <th style="width: 140px; min-width: 140px;">Penyulang</th>
                                <th style="width: 180px; min-width: 180px;">Section</th>
                                <th style="width: 150px; min-width: 150px;">Jenis Temuan</th>
                                <th style="width: 70px; min-width: 70px;" class="text-center">Foto</th>
                                <th style="width: 100px; min-width: 100px;" class="text-center">Prioritas</th>
                                <th style="width: 88px; min-width: 88px;" class="text-center">Tanggal</th>
                                <th style="width: 120px; min-width: 120px;" class="text-center">Status/SLA</th>
                                <th style="width: 100px; min-width: 100px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be loaded via AJAX DataTables -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL DETAIL TEMUAN (Custom CSS/JS - 100% Bebas Konflik)     -->
<!-- ============================================================ -->
<div id="modalDetailTemuan" class="custom-modal-backdrop">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content" style="background:#1a1a2e; color:#e0e0e0; border:1px solid #2d2d4e; max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header" style="background:linear-gradient(135deg,#005eb8,#003f8a); border-bottom:1px solid #2d2d4e;">
                <h5 class="modal-title font-weight-bold" id="modalDetailLabel">
                    <i class="fas fa-circle-info mr-2"></i>
                    <span id="modal-nomor-temuan">Detail Temuan</span>
                </h5>
                <button type="button" class="btn-custom-close-header btn-custom-close" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <!-- Loading Spinner -->
                <div id="modal-loading" class="text-center py-5">
                    <div class="spinner-border text-info" role="status" style="width:3rem;height:3rem;"></div>
                    <p class="mt-3 text-muted">Memuat data...</p>
                </div>
                <!-- Content (hidden until loaded) -->
                <div id="modal-content-area" class="d-none p-3">

                    <!-- Row 1: Status + Info Badge -->
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap" style="gap:8px;">
                        <div id="modal-sla-badge"></div>
                        <div>
                            <span id="modal-prioritas-badge" class="badge badge-secondary mr-1"></span>
                            <span id="modal-potensi-badge" class="badge badge-info"></span>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Kolom Kiri: Info Temuan -->
                        <div class="col-lg-6">
                            <div class="p-3 rounded mb-3" style="background:#12122a; border:1px solid #2d2d4e;">
                                <h6 class="font-weight-bold text-info mb-3"><i class="fas fa-info-circle mr-1"></i> Informasi Temuan</h6>
                                <table class="table table-sm table-borderless mb-0" style="color:#ffffff;">
                                    <tr><td style="width:130px; color:#94a3b8;">ULP</td><td class="font-weight-bold text-white" id="md-ulp">-</td></tr>
                                    <tr><td style="color:#94a3b8;">Penyulang</td><td class="font-weight-bold text-white" id="md-penyulang">-</td></tr>
                                    <tr><td style="color:#94a3b8;">Section</td><td class="text-white" id="md-section">-</td></tr>
                                    <tr><td style="color:#94a3b8;">Jenis</td><td class="text-white" id="md-jenis">-</td></tr>
                                    <tr><td style="color:#94a3b8;">Pelaksana</td><td class="text-white" id="md-pelaksana">-</td></tr>
                                    <tr><td style="color:#94a3b8;">Konduktor</td><td class="text-white" id="md-konduktor">-</td></tr>
                                    <tr><td style="color:#94a3b8;">Noga</td><td class="text-white" id="md-noga">-</td></tr>
                                    <tr><td style="color:#94a3b8;">Tgl Temuan</td><td class="text-white" id="md-tanggal">-</td></tr>
                                    <tr><td style="color:#94a3b8;">Tgl Selesai</td><td class="text-white" id="md-selesai">-</td></tr>
                                </table>
                            </div>

                            <!-- Detail Kerusakan -->
                            <div class="p-3 rounded mb-3" style="background:#1e293b; border:1px solid #334155; border-left:4px solid #ea580c;">
                                <h6 class="font-weight-bold mb-2" style="color:#fb923c; font-size:14px;"><i class="fas fa-triangle-exclamation mr-1"></i> Detail Kerusakan</h6>
                                <p id="md-detail" class="mb-0" style="font-size:14px; white-space:pre-wrap; color:#ffffff !important; font-weight:600; line-height:1.6;"></p>
                            </div>

                            <!-- Material -->
                            <div class="p-3 rounded mb-3" style="background:#1e293b; border:1px solid #334155; border-left:4px solid #0284c7;">
                                <h6 class="font-weight-bold mb-2" style="color:#38bdf8; font-size:14px;"><i class="fas fa-screwdriver-wrench mr-1"></i> Material Dibutuhkan</h6>
                                <p id="md-material" class="mb-0" style="font-size:14px; white-space:pre-wrap; color:#ffffff !important; font-weight:600; line-height:1.6;"></p>
                            </div>

                            <!-- Lokasi -->
                            <div class="p-3 rounded mb-3" style="background:#1e293b; border:1px solid #334155; border-left:4px solid #16a34a;">
                                <h6 class="font-weight-bold mb-2" style="color:#4ade80; font-size:14px;"><i class="fas fa-map-location-dot mr-1"></i> Alamat Lokasi</h6>
                                <p id="md-alamat" class="mb-0" style="font-size:14px; color:#ffffff !important; font-weight:600; line-height:1.6;"></p>
                                <div id="md-koordinat" class="mt-2 d-none">
                                    <a id="md-maps-link" href="#" target="_blank" class="btn btn-sm btn-success">
                                        <i class="fas fa-map-marker-alt mr-1"></i> Buka di Google Maps
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Kolom Kanan: Foto + Histori -->
                        <div class="col-lg-6">
                            <!-- Foto Temuan -->
                            <div class="p-3 rounded mb-3" style="background:#12122a; border:1px solid #2d2d4e;">
                                <h6 class="font-weight-bold mb-2" style="color:#d9534f;"><i class="fas fa-camera mr-1"></i> Foto Temuan Lapangan</h6>
                                <div id="md-foto-grid" class="row px-1">
                                    <!-- foto diisi JS -->
                                </div>
                                <p id="md-no-foto" class="text-muted small d-none"><i class="fas fa-image mr-1"></i> Tidak ada foto.</p>
                            </div>

                            <!-- Histori Tindak Lanjut -->
                            <div class="p-3 rounded" style="background:#12122a; border:1px solid #2d2d4e;">
                                <h6 class="font-weight-bold mb-2 text-warning"><i class="fas fa-history mr-1"></i> Histori Tindak Lanjut</h6>
                                <div id="md-history-list" style="max-height:220px; overflow-y:auto;">
                                    <p class="text-muted small" id="md-no-history">Belum ada tindak lanjut.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="position: sticky; bottom: 0; z-index: 100; background:#12122a; border-top:1px solid #2d2d4e; padding: 10px 16px; margin-top: auto; display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 8px;">
                <a id="modal-btn-wa" href="#" target="_blank" class="btn btn-success btn-sm font-weight-bold shadow-sm" style="background-color: #25D366; border-color: #25D366; color: #ffffff; border-radius: 8px;">
                    <i class="fab fa-whatsapp me-1"></i> Share WA
                </a>
                <a id="modal-btn-detail" href="#" class="btn btn-outline-info btn-sm font-weight-bold" style="border-radius: 8px;">
                    <i class="fas fa-external-link-alt mr-1"></i> Buka Halaman Detail
                </a>
                <a id="modal-btn-edit" href="#" class="btn btn-warning btn-sm text-dark font-weight-bold d-none" style="border-radius: 8px;">
                    <i class="fas fa-edit mr-1"></i> Edit Temuan
                </a>
                <a id="modal-btn-update-progress" href="#" class="btn btn-primary btn-sm font-weight-bold" style="background-color: #0284c7; border-color: #0284c7; color: #ffffff; border-radius: 8px;">
                    <i class="fas fa-tasks mr-1"></i> Update Progress
                </a>
                <button id="modal-btn-delete" type="button" class="btn btn-danger btn-sm font-weight-bold d-none" style="border-radius: 8px;">
                    <i class="fas fa-trash mr-1"></i> Delete
                </button>
                <button type="button" class="btn btn-secondary btn-sm btn-custom-close font-weight-bold" style="border-radius: 8px;">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Foto Lightbox Modal (Custom CSS/JS) -->
<div id="modalFotoLightbox" class="custom-modal-backdrop" style="z-index:1100;">
    <div class="modal-dialog modal-dialog-centered" style="max-width:90vw; margin: 0;">
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
        // 1. Ambil URL parameters untuk pre-populate filter dari dashboard redirect
        const urlParams = new URLSearchParams(window.location.search);
        const urlQ = urlParams.get('q') || urlParams.get('query') || urlParams.get('penyulang') || urlParams.get('jenis') || urlParams.get('search') || '';
        const urlPelaksana = urlParams.get('pelaksana');
        const urlPrioritas = urlParams.get('prioritas');
        const urlStatus = urlParams.get('status');

        if (urlPelaksana) {
            $('#filter_pelaksana').val(urlPelaksana.toUpperCase()).trigger('change.select2');
        }
        if (urlPrioritas) {
            $('#filter_prioritas').val(urlPrioritas.toUpperCase()).trigger('change.select2');
        }
        if (urlStatus) {
            const cleanStatus = urlStatus.toUpperCase();
            if (cleanStatus === 'PROSES' || cleanStatus === 'DALAM_PROSES' || cleanStatus === 'DALAM PROSES') {
                $('#filter_status').val('PROSES').trigger('change.select2');
            } else if (cleanStatus === 'SELESAI' || cleanStatus === 'SUDAH_SELESAI' || cleanStatus === 'SUDAH SELESAI') {
                $('#filter_status').val('SELESAI').trigger('change.select2');
            } else if (cleanStatus === 'BELUM' || cleanStatus === 'BELUM_SELESAI' || cleanStatus === 'BELUM SELESAI') {
                $('#filter_status').val('BELUM').trigger('change.select2');
            } else {
                $('#filter_status').val(urlStatus).trigger('change.select2');
            }
        }

        // 2. Inisialisasi DataTable Server Side
        const table = $('#table-temuan').DataTable({
            "processing": true,
            "serverSide": true,
            "search": {
                "search": urlQ
            },
            "ajax": {
                "url": "<?= site_url('temuan/ajax-datatables') ?>?_=" + Date.now(),
                "type": "POST",
                "cache": false,
                "data": function(d) {
                    d.<?= csrf_token() ?> = "<?= csrf_hash() ?>";
                    d.ulp_id = $('#filter_ulp_id').val();
                    d.penyulang_id = $('#filter_penyulang_id').val();
                    d.section_id = $('#filter_section_id').val();
                    d.pelaksana = $('#filter_pelaksana').val();
                    d.prioritas = $('#filter_prioritas').val();
                    d.status = $('#filter_status').val();
                    d.start_date = $('#filter_start_date').val();
                    d.end_date = $('#filter_end_date').val();
                    if (urlQ && (!d.search || !d.search.value)) {
                        if (!d.search) d.search = {};
                        d.search.value = urlQ;
                    }
                }
            },
            "columns": [
                { "data": 0, "className": "font-weight-bold" }, // Nomor
                { "data": 1 }, // Penyulang
                { "data": 2 }, // Section
                { "data": 3, "render": function(data){ return data; } }, // Jenis
                { "data": 4, "orderable": false, "className": "text-center", "render": function(data){ return data; } }, // Foto
                { "data": 5, "className": "text-center", "render": function(data){ return data; } }, // Prioritas
                { "data": 6, "className": "text-center align-middle", "render": function(data){ return data; } }, // Tanggal
                { "data": 7, "className": "text-center align-middle", "render": function(data){ return data; } }, // Status
                { "data": 8, "orderable": false, "className": "text-center align-middle", "render": function(data){ return data; } } // Aksi
            ],
            "order": [[6, "desc"]], // Default order by Tanggal (column index 6)
            "responsive": false,
            "autoWidth": false,
            "language": window.SIDAK_DATATABLES_ID
        });

        // 3. Trigger reload table on filter change
        $('#filter_ulp_id, #filter_penyulang_id, #filter_section_id, #filter_pelaksana, #filter_prioritas, #filter_status, #filter_start_date, #filter_end_date').change(function() {
            table.ajax.reload();
        });

        // 4. Cascade ULP -> Penyulang (jika ULP tidak di-restricted)
        <?php if (!$isRestricted): ?>
        $('#filter_ulp_id').change(function() {
            const ulpId = $(this).val();
            const penyulangSelect = $('#filter_penyulang_id');
            const sectionSelect = $('#filter_section_id');
            
            penyulangSelect.empty().append('<option value="">-- Semua Penyulang --</option>').trigger('change.select2');
            sectionSelect.empty().append('<option value="">-- Semua Section --</option>').trigger('change.select2');
            
            if (ulpId) {
                $.ajax({
                    url: '<?= site_url('ajax/network/penyulang/') ?>' + ulpId,
                    type: 'GET',
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
        
        // Sync ULP filter dropdown values on load to prevent browser cache mismatch
        $('#filter_ulp_id').trigger('change.select2');
        <?php endif; ?>
 
        // Cascade Penyulang -> Section
        $('#filter_penyulang_id').change(function() {
            const penyulangId = $(this).val();
            const sectionSelect = $('#filter_section_id');
            
            sectionSelect.empty().append('<option value="">-- Semua Section --</option>').trigger('change.select2');
            
            if (penyulangId) {
                $.ajax({
                    url: '<?= site_url('ajax/network/section/') ?>' + penyulangId,
                    type: 'GET',
                    dataType: 'JSON',
                    success: function(data) {
                        $.each(data, function(index, item) {
                            sectionSelect.append('<option value="' + item.id + '">' + item.nama_section + '</option>');
                        });
                        sectionSelect.trigger('change.select2');
                    }
                });
            }
        });

        // 5. Button Reset Filter Click Handler
        $('#btn-reset-filter').click(function() {
            // Bersihkan parameter URL dari browser bar agar bersih saat direfresh
            window.history.replaceState({}, document.title, window.location.pathname);
            
            // Reset input values
            <?php if (!$isRestricted): ?>
                $('#filter_ulp_id').val('').trigger('change.select2');
            <?php endif; ?>
            $('#filter_penyulang_id').val('').trigger('change.select2');
            $('#filter_section_id').val('').trigger('change.select2');
            $('#filter_pelaksana').val('').trigger('change.select2');
            $('#filter_prioritas').val('').trigger('change.select2');
            $('#filter_status').val('').trigger('change.select2');
            
            table.ajax.reload();
        });
    });

    // ============================================================
    // MODAL DETAIL AJAX - Klik tombol detail buka modal (cepat)
    // ============================================================
    const ajaxDetailUrl = '<?= site_url('temuan/ajax-detail/') ?>';

    $(document).on('click', '.btn-detail-modal', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        window.location.href = '<?= site_url('temuan/detail/') ?>' + id;
    });

    // Delete temuan from detail modal
    $(document).on('click', '#modal-btn-delete', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        if (!id) return;

        Swal.fire({
            title: 'Hapus Temuan?',
            text: 'Apakah Anda yakin ingin menghapus data temuan ini?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash me-1"></i> Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '<?= site_url('temuan/delete/') ?>' + id,
                    type: 'POST',
                    data: { '<?= csrf_token() ?>': '<?= csrf_hash() ?>' },
                    dataType: 'json',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    success: function(res) {
                        if (res.success) {
                            $('#modalDetailTemuan').css('display', 'none');
                            $('body').css('overflow', 'auto');
                            Swal.fire({ icon: 'success', title: 'Terhapus!', text: res.message || 'Data temuan berhasil dihapus.', timer: 2000, showConfirmButton: false });
                            if (typeof table !== 'undefined') table.ajax.reload();
                        } else {
                            Swal.fire('Gagal!', res.message || 'Gagal menghapus temuan.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Terjadi kesalahan sistem saat menghapus temuan.', 'error');
                    }
                });
            }
        });
    });

    window.formatTgl = function(str) {
        if (!str) return '-';
        const d = new Date(str);
        if (isNaN(d)) return str;
        const dateStr = ('0'+d.getDate()).slice(-2) + '-' + ('0'+(d.getMonth()+1)).slice(-2) + '-' + d.getFullYear();
        const hrs = ('0'+d.getHours()).slice(-2);
        const mins = ('0'+d.getMinutes()).slice(-2);
        return dateStr + ' ' + hrs + ':' + mins + ' WIB';
    };

    window.openLightbox = function(url) {
        $('#lightbox-img').attr('src', url);
        $('#modalFotoLightbox').css('display', 'flex');
        $('body').css('overflow', 'hidden');
    };

    // Failsafe manual close handler for custom CSS modal backdrop
    $(document).on('click', '.btn-custom-close', function () {
        $(this).closest('.custom-modal-backdrop').css('display', 'none');
        if ($('#modalDetailTemuan').css('display') === 'flex') {
            $('body').css('overflow', 'hidden');
        } else {
            $('body').css('overflow', '');
        }
    });

    // Close on clicking backdrop outside modal-content
    $(document).on('click', '.custom-modal-backdrop', function (e) {
        if ($(e.target).hasClass('custom-modal-backdrop')) {
            $(e.target).css('display', 'none');
            if ($('#modalDetailTemuan').css('display') === 'flex') {
                $('body').css('overflow', 'hidden');
            } else {
                $('body').css('overflow', '');
            }
        }
    });

window.confirmDelete = function(id) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Data temuan ini akan dihapus dari sistem!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (result.isConfirmed) {
            window.location.href = "<?= site_url('temuan/delete/') ?>" + id;
        }
    });
};
</script>
<?= $this->endSection() ?>
