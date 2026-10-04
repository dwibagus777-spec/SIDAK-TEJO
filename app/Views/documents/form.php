<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap" style="gap: 10px;">
        <div>
            <h3 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-file-contract text-primary me-2"></i> Terbitkan Dokumen Resmi Baru
            </h3>
            <small class="text-secondary">Pusat Penerbitan Laporan Resmi Berbasis Master Database & Integritas Kriptografis SHA-256</small>
        </div>
        <a href="<?= site_url('documents') ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
    </div>

    <!-- Generator Pintar: Tarik Data Otomatis dari Database -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-light border-start border-4 border-warning">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap" style="gap: 10px;">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark p-2 rounded-3"><i class="fas fa-database fs-6"></i></span>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Tarik Data Otomatis dari Database Operasional</h6>
                        <small class="text-secondary">Pilih rentang periode dan jenis pekerjaan untuk menyusun isi laporan secara otomatis.</small>
                    </div>
                </div>
                <span class="badge bg-primary rounded-pill px-3 py-2 font-weight-normal" style="font-size: 11px;">
                    <i class="fas fa-bolt me-1"></i> Auto-Generate Engine
                </span>
            </div>

            <div class="row g-3 align-items-end">
                <!-- Mode Periode -->
                <div class="col-md-3 col-12">
                    <label class="form-label fw-bold text-secondary small">Mode Periode</label>
                    <select id="gen_periode" class="form-select form-select-sm">
                        <option value="BULANAN" selected>Bulanan</option>
                        <option value="HARIAN">Harian</option>
                        <option value="SEMESTER">Semester</option>
                    </select>
                </div>

                <!-- Input Tanggal Harian -->
                <div class="col-md-3 col-12 d-none" id="wrap_harian">
                    <label class="form-label fw-bold text-secondary small">Pilih Tanggal</label>
                    <input type="date" id="gen_tanggal" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                </div>

                <!-- Input Bulan Bulanan -->
                <div class="col-md-3 col-6" id="wrap_bulanan">
                    <label class="form-label fw-bold text-secondary small">Pilih Bulan</label>
                    <select id="gen_bulan" class="form-select form-select-sm">
                        <?php
                        $blnList = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
                        $curBln = (int)date('n');
                        foreach ($blnList as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $k === $curBln ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Input Semester -->
                <div class="col-md-3 col-6 d-none" id="wrap_semester">
                    <label class="form-label fw-bold text-secondary small">Pilih Semester</label>
                    <select id="gen_semester" class="form-select form-select-sm">
                        <option value="1" <?= date('n') <= 6 ? 'selected' : '' ?>>Semester 1 (Jan - Jun)</option>
                        <option value="2" <?= date('n') > 6 ? 'selected' : '' ?>>Semester 2 (Jul - Des)</option>
                    </select>
                </div>

                <!-- Tahun -->
                <div class="col-md-2 col-6" id="wrap_tahun">
                    <label class="form-label fw-bold text-secondary small">Tahun</label>
                    <select id="gen_tahun" class="form-select form-select-sm">
                        <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                            <option value="<?= $y ?>"><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Tim Pelaksana / Pekerjaan -->
                <div class="col-md-4 col-12">
                    <label class="form-label fw-bold text-secondary small">Jenis Pekerjaan (Tim Pelaksana)</label>
                    <select id="gen_pekerjaan" class="form-select form-select-sm">
                        <option value="SEMUA" selected>-- Seluruh Pekerjaan (Semua Tim) --</option>
                        <option value="HAR ROW">HAR ROW (Pemotongan / Rabas Pohon)</option>
                        <option value="HAR GARDU">HAR GARDU (Pemeliharaan Gardu Distribusi)</option>
                        <option value="HAR KONSTRUKSI">HAR KONSTRUKSI (Perbaikan Konstruksi Tiang/JTM)</option>
                        <option value="PDKB">PDKB (Pekerjaan Dalam Keadaan Bertegangan)</option>
                        <option value="YANTEK">YANTEK (Pelayanan Teknik & Gangguan)</option>
                        <option value="HAR CRANE">HAR CRANE (Pekerjaan Alat Berat Crane)</option>
                    </select>
                </div>

                <!-- Action Button -->
                <div class="col-md-12 col-12 mt-3">
                    <button type="button" id="btn_generate_content" class="btn btn-warning btn-sm rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-wand-magic-sparkles me-1"></i> Tarik & Susun Laporan dari Database
                    </button>
                    <span id="gen_status" class="small text-secondary ms-2"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Dokumen Resmi -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form method="POST" action="<?= site_url('documents/store') ?>">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-6 col-12">
                        <label class="form-label fw-bold text-secondary small">Jenis Dokumen <span class="text-danger">*</span></label>
                        <select name="jenis_dokumen" id="jenis_dokumen" class="form-select form-select-sm" required>
                            <option value="">-- Pilih Jenis Dokumen --</option>
                            <option value="Berita Acara">Berita Acara</option>
                            <option value="Laporan Inspeksi">Laporan Inspeksi</option>
                            <option value="Laporan Harian">Laporan Harian</option>
                            <option value="Laporan Mingguan">Laporan Mingguan</option>
                            <option value="Laporan Bulanan" selected>Laporan Bulanan</option>
                            <option value="Laporan Tahunan">Laporan Tahunan</option>
                            <option value="Surat Tugas">Surat Tugas</option>
                            <option value="Work Order">Work Order</option>
                            <option value="Rekap Temuan">Rekap Temuan</option>
                            <option value="Rekap Penyelesaian">Rekap Penyelesaian</option>
                        </select>
                    </div>
                    <div class="col-md-6 col-12">
                        <label class="form-label fw-bold text-secondary small">Status Awal</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="DRAFT">DRAFT</option>
                            <option value="REVIEW">REVIEW (Kirim ke Supervisor)</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary small">Judul Dokumen <span class="text-danger">*</span></label>
                        <input type="text" name="judul_dokumen" id="judul_dokumen" class="form-control form-control-sm" placeholder="Contoh: Laporan Pekerjaan HAR GARDU - Bulan Oktober 2026" required>
                    </div>
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold text-secondary small mb-0">Isi Dokumen (HTML & Tabel Rinci)</label>
                            <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Dapat diedit manual sebelum diterbitkan</small>
                        </div>
                        <textarea name="content_html" id="content_html" class="form-control form-control-sm font-monospace" rows="12" placeholder="Konten dokumen resmi akan muncul di sini setelah ditarik dari database..."></textarea>
                    </div>
                </div>

                <!-- Preview Area -->
                <div class="mt-4 d-none" id="preview_card">
                    <label class="form-label fw-bold text-secondary small"><i class="fas fa-eye me-1"></i> Preview Tampilan Dokumen</label>
                    <div class="border rounded-4 p-4 bg-white shadow-sm" id="preview_container" style="max-height: 450px; overflow-y: auto;"></div>
                </div>

                <hr class="my-4">

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill font-weight-bold px-4">
                        <i class="fas fa-file-circle-plus me-1"></i> Terbitkan Dokumen & Generate QR + SHA256
                    </button>
                    <a href="<?= site_url('documents') ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    // Mode Periode Switcher
    $('#gen_periode').on('change', function() {
        var mode = $(this).val();
        if (mode === 'HARIAN') {
            $('#wrap_harian').removeClass('d-none');
            $('#wrap_bulanan').addClass('d-none');
            $('#wrap_semester').addClass('d-none');
            $('#jenis_dokumen').val('Laporan Harian');
        } else if (mode === 'SEMESTER') {
            $('#wrap_harian').addClass('d-none');
            $('#wrap_bulanan').addClass('d-none');
            $('#wrap_semester').removeClass('d-none');
            $('#jenis_dokumen').val('Laporan Bulanan');
        } else {
            // BULANAN
            $('#wrap_harian').addClass('d-none');
            $('#wrap_bulanan').removeClass('d-none');
            $('#wrap_semester').addClass('d-none');
            $('#jenis_dokumen').val('Laporan Bulanan');
        }
    });

    // Generate Button AJAX Handler
    $('#btn_generate_content').on('click', function() {
        var btn = $(this);
        var status = $('#gen_status');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Mengambil data dari database...');
        status.html('');

        var payload = {
            periode: $('#gen_periode').val(),
            tanggal: $('#gen_tanggal').val(),
            bulan: $('#gen_bulan').val(),
            semester: $('#gen_semester').val(),
            tahun: $('#gen_tahun').val(),
            pekerjaan: $('#gen_pekerjaan').val()
        };

        // CSRF Token
        var csrfName = '<?= csrf_token() ?>';
        var csrfHash = $('input[name="' + csrfName + '"]').val() || '<?= csrf_hash() ?>';
        payload[csrfName] = csrfHash;

        $.ajax({
            url: '<?= site_url('documents/generate-content') ?>',
            method: 'POST',
            data: payload,
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false).html('<i class="fas fa-wand-magic-sparkles me-1"></i> Tarik & Susun Laporan dari Database');
                if (res.success) {
                    $('#judul_dokumen').val(res.judul_dokumen);
                    $('#content_html').val(res.content_html);

                    // Show Preview
                    $('#preview_container').html(res.content_html);
                    $('#preview_card').removeClass('d-none');

                    status.html('<span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Sukses! ' + res.total_items + ' item pekerjaan berhasil ditarik ke dokumen.</span>');
                } else {
                    status.html('<span class="text-danger fw-bold"><i class="fas fa-triangle-exclamation me-1"></i> Gagal menyusun laporan.</span>');
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="fas fa-wand-magic-sparkles me-1"></i> Tarik & Susun Laporan dari Database');
                status.html('<span class="text-danger fw-bold"><i class="fas fa-circle-xmark me-1"></i> Terjadi kesalahan saat memproses data.</span>');
            }
        });
    });

    // Live update preview when textarea is manually edited
    $('#content_html').on('input', function() {
        var val = $(this).val();
        if (val.trim() !== '') {
            $('#preview_container').html(val);
            $('#preview_card').removeClass('d-none');
        }
    });
});
</script>
<?= $this->endSection() ?>
