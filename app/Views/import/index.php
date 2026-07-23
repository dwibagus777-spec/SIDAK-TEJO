<?= $this->extend('layouts/admin') ?>
<?= $this->section('title') ?>Impor Data CSV<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Impor Data CSV<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">

    <!-- Header Section -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap" style="gap:8px;">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#005eb8;">
                <i class="fas fa-file-csv mr-2" style="color:#217346;"></i> Impor Data dari CSV
            </h4>
            <small class="text-muted">Unggah file CSV untuk memasukkan data massal ke database secara cepat</small>
        </div>
    </div>

    <!-- Alert -->
    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert"
             style="border-left: 4px solid #28a745; border-radius: 10px;">
            <i class="fas fa-check-circle mr-2"></i> <?= session()->getFlashdata('success') ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert"
             style="border-left: 4px solid #dc3545; border-radius: 10px;">
            <i class="fas fa-exclamation-circle mr-2"></i> <?= session()->getFlashdata('error') ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>

    <div class="row">

        <!-- ======================== Card: Temuan ======================== -->
        <div class="col-lg-6 col-12 mb-4">
            <div class="card card-modern h-100" style="border-top: 4px solid #005eb8;">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center mr-3 flex-shrink-0"
                             style="width:48px; height:48px; background:linear-gradient(135deg,#005eb8,#00c6fb);">
                            <i class="fas fa-clipboard-list text-white"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 font-weight-bold">Data Temuan</h5>
                            <small class="text-muted">Import hasil temuan inspeksi lapangan</small>
                        </div>
                    </div>
                    <div class="p-3 mb-3 rounded" style="background:#f0f7ff; border:1px dashed #005eb8; font-size:0.8rem;">
                        <strong class="text-primary"><i class="fas fa-table mr-1"></i> Format Kolom CSV:</strong><br>
                        <span class="text-muted">No | <strong>Nomor Temuan*</strong> | Nama ULP | Nama Penyulang | Jenis Temuan | Pelaksana | Prioritas | Potensi Gangguan | Detail Temuan | Alamat | Latitude | Longitude | Tanggal (YYYY-MM-DD) | Status</span>
                    </div>
                    <form action="<?= site_url('import/process') ?>" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="modul" value="temuan">
                        <div class="input-group mb-3">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="file-temuan" name="file_csv" accept=".csv" required>
                                <label class="custom-file-label text-truncate" for="file-temuan">Pilih file CSV...</label>
                            </div>
                        </div>
                        <div class="d-flex" style="gap:8px;">
                            <a href="<?= site_url('import/template/temuan') ?>" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-download mr-1"></i> Unduh Template CSV
                            </a>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-upload mr-1"></i> Upload & Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ======================== Card: Penyulang ======================== -->
        <div class="col-12 mb-4">
            <div class="card card-modern" style="border-top: 4px solid #6f42c1;">
                <div class="card-body">
                    <div class="row align-items-start">
                        <!-- Icon + Title -->
                        <div class="col-md-12 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center mr-3 flex-shrink-0"
                                     style="width:48px; height:48px; background:linear-gradient(135deg,#6f42c1,#a855f7);">
                                    <i class="fas fa-bolt text-white"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 font-weight-bold">Data Penyulang</h5>
                                    <small class="text-muted">Import master data penyulang — pilih ULP terlebih dahulu agar template otomatis sesuai ULP yang dipilih</small>
                                </div>
                            </div>
                        </div>

                        <!-- Step 1: Pilih ULP -->
                        <div class="col-md-5">
                            <div class="p-3 rounded h-100" style="background:#f8f0ff; border:1px dashed #6f42c1;">
                                <p class="font-weight-bold mb-2" style="color:#6f42c1; font-size:0.85rem;">
                                    <i class="fas fa-step-forward mr-1"></i> LANGKAH 1 — Pilih ULP
                                </p>
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold text-muted">Pilih ULP</label>
                                    <select id="penyulang-ulp" class="form-control form-control-sm">
                                        <option value="">-- Pilih ULP --</option>
                                        <?php foreach ($ulps as $ulp): ?>
                                            <option value="<?= $ulp['id'] ?>"><?= esc($ulp['nama_ulp']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <span class="badge mb-3 d-block text-left" style="background:#6f42c1; color:#fff; font-size:0.75rem; padding:6px 10px; border-radius:6px;">
                                    <i class="fas fa-magic mr-1"></i> Kode Penyulang dibuat OTOMATIS oleh sistem sesuai ULP
                                </span>
                                <a id="btn-template-penyulang" href="#" class="btn btn-sm w-100 disabled"
                                   style="background:linear-gradient(135deg,#6f42c1,#a855f7); color:#fff; opacity:0.6;">
                                    <i class="fas fa-download mr-1"></i> Unduh Template CSV (sesuai ULP)
                                </a>
                            </div>
                        </div>

                        <!-- Step 2: Upload CSV -->
                        <div class="col-md-7 mt-3 mt-md-0">
                            <div class="p-3 rounded h-100" style="background:#fdf8ff; border:1px dashed #adb5bd;">
                                <p class="font-weight-bold mb-2" style="color:#6c757d; font-size:0.85rem;">
                                    <i class="fas fa-step-forward mr-1"></i> LANGKAH 2 — Upload File CSV yang Sudah Diisi
                                </p>
                                <div class="p-2 mb-3 rounded" style="background:#f0e8ff; border:1px dashed #6f42c1; font-size:0.8rem;">
                                    <strong style="color:#6f42c1;"><i class="fas fa-table mr-1"></i> Format Kolom CSV:</strong><br>
                                    <span class="text-muted">No | <strong>Nama Penyulang*</strong> | Nama ULP (jangan diubah) | Status</span>
                                </div>
                                <form action="<?= site_url('import/process') ?>" method="post" enctype="multipart/form-data">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="modul" value="penyulang">
                                    <div class="input-group mb-3">
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="file-penyulang" name="file_csv" accept=".csv" required>
                                            <label class="custom-file-label text-truncate" for="file-penyulang">Pilih file CSV...</label>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#6f42c1,#a855f7); color:#fff;">
                                        <i class="fas fa-upload mr-1"></i> Upload & Import Penyulang
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- ======================== Card: ULP ======================== -->
        <div class="col-lg-6 col-12 mb-4">
            <div class="card card-modern h-100" style="border-top: 4px solid #f12711;">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center mr-3 flex-shrink-0"
                             style="width:48px; height:48px; background:linear-gradient(135deg,#f12711,#f5af19);">
                            <i class="fas fa-building text-white"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 font-weight-bold">Data ULP</h5>
                            <small class="text-muted">Import master data Unit Layanan Pelanggan</small>
                        </div>
                    </div>
                    <div class="p-3 mb-3 rounded" style="background:#fff8f0; border:1px dashed #f12711; font-size:0.8rem;">
                        <strong style="color:#f12711;"><i class="fas fa-table mr-1"></i> Format Kolom CSV:</strong><br>
                        <span class="text-muted">No | Kode ULP | <strong>Nama ULP*</strong> | Status</span>
                    </div>
                    <form action="<?= site_url('import/process') ?>" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="modul" value="ulp">
                        <div class="input-group mb-3">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="file-ulp" name="file_csv" accept=".csv" required>
                                <label class="custom-file-label text-truncate" for="file-ulp">Pilih file CSV...</label>
                            </div>
                        </div>
                        <div class="d-flex" style="gap:8px;">
                            <a href="<?= site_url('import/template/ulp') ?>" class="btn btn-sm" style="border:1px solid #f12711; color:#f12711; background:transparent;">
                                <i class="fas fa-download mr-1"></i> Unduh Template CSV
                            </a>
                            <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#f12711,#f5af19); color:#fff;">
                                <i class="fas fa-upload mr-1"></i> Upload & Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ======================== Card: User ======================== -->
        <div class="col-lg-6 col-12 mb-4">
            <div class="card card-modern h-100" style="border-top: 4px solid #17a2b8;">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center mr-3 flex-shrink-0"
                             style="width:48px; height:48px; background:linear-gradient(135deg,#17a2b8,#00c6fb);">
                            <i class="fas fa-users text-white"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 font-weight-bold">Data Pengguna</h5>
                            <small class="text-muted">Import akun pengguna sistem</small>
                        </div>
                    </div>
                    <div class="p-3 mb-3 rounded" style="background:#f0fbff; border:1px dashed #17a2b8; font-size:0.8rem;">
                        <strong style="color:#17a2b8;"><i class="fas fa-table mr-1"></i> Format Kolom CSV:</strong><br>
                        <span class="text-muted"><strong>Nama*</strong> | <strong>Username*</strong> | <strong>Password*</strong> | Role | Nama ULP | Status</span>
                    </div>
                    <form action="<?= site_url('import/process') ?>" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="modul" value="user">
                        <div class="input-group mb-3">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="file-user" name="file_csv" accept=".csv" required>
                                <label class="custom-file-label text-truncate" for="file-user">Pilih file CSV...</label>
                            </div>
                        </div>
                        <div class="d-flex" style="gap:8px;">
                            <a href="<?= site_url('import/template/user') ?>" class="btn btn-sm" style="border:1px solid #17a2b8; color:#17a2b8; background:transparent;">
                                <i class="fas fa-download mr-1"></i> Unduh Template CSV
                            </a>
                            <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#17a2b8,#00c6fb); color:#fff;">
                                <i class="fas fa-upload mr-1"></i> Upload & Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ======================== Card: Section ======================== -->
        <div class="col-12 mb-4">
            <div class="card card-modern" style="border-top: 4px solid #20c997;">
                <div class="card-body">
                    <div class="row align-items-start">
                        <!-- Icon + Title -->
                        <div class="col-md-12 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center mr-3 flex-shrink-0"
                                     style="width:48px; height:48px; background:linear-gradient(135deg,#20c997,#0dcaf0);">
                                    <i class="fas fa-sitemap text-white"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 font-weight-bold">Data Section</h5>
                                    <small class="text-muted">Import data section penyulang — pilih ULP & Penyulang terlebih dahulu untuk generate template yang sesuai</small>
                                </div>
                            </div>
                        </div>

                        <!-- Step 1: Pilih ULP & Penyulang -->
                        <div class="col-md-5">
                            <div class="p-3 rounded h-100" style="background:#f0fefb; border:1px dashed #20c997;">
                                <p class="font-weight-bold mb-2" style="color:#20c997; font-size:0.85rem;">
                                    <i class="fas fa-step-forward mr-1"></i> LANGKAH 1 — Pilih ULP & Penyulang
                                </p>
                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold text-muted">Pilih ULP</label>
                                    <select id="section-ulp" class="form-control form-control-sm">
                                        <option value="">-- Pilih ULP --</option>
                                        <?php foreach ($ulps as $ulp): ?>
                                            <option value="<?= $ulp['id'] ?>"><?= esc($ulp['nama_ulp']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold text-muted">Pilih Penyulang</label>
                                    <select id="section-penyulang" class="form-control form-control-sm" disabled>
                                        <option value="">-- Pilih ULP dulu --</option>
                                    </select>
                                    <small id="penyulang-loading" class="text-muted d-none">
                                        <i class="fas fa-spinner fa-spin mr-1"></i> Memuat...
                                    </small>
                                </div>
                                <a id="btn-template-section" href="#" class="btn btn-sm w-100 disabled"
                                   style="background:linear-gradient(135deg,#20c997,#0dcaf0); color:#fff; opacity:0.6;">
                                    <i class="fas fa-download mr-1"></i> Unduh Template CSV (sesuai Penyulang)
                                </a>
                            </div>
                        </div>

                        <!-- Step 2: Upload CSV -->
                        <div class="col-md-7 mt-3 mt-md-0">
                            <div class="p-3 rounded h-100" style="background:#f8fffd; border:1px dashed #adb5bd;">
                                <p class="font-weight-bold mb-2" style="color:#6c757d; font-size:0.85rem;">
                                    <i class="fas fa-step-forward mr-1"></i> LANGKAH 2 — Upload File CSV yang Sudah Diisi
                                </p>
                                <div class="p-2 mb-3 rounded" style="background:#e8f5f1; border:1px dashed #20c997; font-size:0.8rem;">
                                    <strong style="color:#20c997;"><i class="fas fa-table mr-1"></i> Format Kolom CSV:</strong><br>
                                    <span class="text-muted">No | <strong>Nama Section*</strong> | Nama ULP (jangan diubah) | Nama Penyulang (jangan diubah) | Status</span>
                                </div>
                                <form action="<?= site_url('import/process') ?>" method="post" enctype="multipart/form-data">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="modul" value="section">
                                    <div class="input-group mb-3">
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="file-section" name="file_csv" accept=".csv" required>
                                            <label class="custom-file-label text-truncate" for="file-section">Pilih file CSV...</label>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#20c997,#0dcaf0); color:#fff;">
                                        <i class="fas fa-upload mr-1"></i> Upload & Import Section
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.row -->

    <!-- Panduan -->
    <div class="card card-modern mt-1">
        <div class="card-header border-bottom bg-transparent">
            <h6 class="card-title mb-0 font-weight-bold text-dark">
                <i class="fas fa-circle-question mr-1 text-warning"></i> Panduan Cara Impor CSV
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <ol class="mb-0 pl-4" style="line-height:2.1; font-size:0.9rem;">
                        <li>Klik tombol <strong>"Unduh Template CSV"</strong> pada modul yang sesuai.</li>
                        <li>Buka file CSV menggunakan Excel atau Google Sheets.</li>
                        <li>Isi data mulai dari <strong>baris ke-2</strong> (baris ke-1 adalah header).</li>
                        <li>Kolom bertanda <strong>(*)</strong> bersifat wajib diisi.</li>
                        <li>Simpan sebagai format <strong>CSV (Comma Separated Values)</strong>.</li>
                        <li>Upload file CSV menggunakan form di atas.</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <div class="p-3 rounded" style="background:#fff8e1; border-left:4px solid #ffc107;">
                        <strong><i class="fas fa-triangle-exclamation mr-1 text-warning"></i> Catatan Penting:</strong>
                        <ul class="mb-0 mt-2 pl-3" style="font-size:0.85rem; line-height:1.9;">
                            <li>Format file harus <strong>.csv</strong></li>
                            <li>Gunakan <strong>koma (,)</strong> sebagai pemisah kolom</li>
                            <li><strong>Nama ULP</strong> harus persis sama dengan data di sistem</li>
                            <li>Tanggal: format <strong>YYYY-MM-DD</strong> (contoh: 2026-07-17)</li>
                            <li>Password user akan otomatis <strong>dienkripsi</strong></li>
                            <li>Username duplikat akan <strong>dilewati</strong> otomatis</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    // Show filename on custom file input
    document.querySelectorAll('.custom-file-input').forEach(function(input) {
        input.addEventListener('change', function() {
            var fileName = this.files[0] ? this.files[0].name : 'Pilih file CSV...';
            this.nextElementSibling.textContent = fileName;
        });
    });

    // ============================================================
    // SECTION: Cascading Dropdown ULP → Penyulang (AJAX)
    // ============================================================
    const ulpSelect      = document.getElementById('section-ulp');
    const penyulangSelect = document.getElementById('section-penyulang');
    const loadingEl      = document.getElementById('penyulang-loading');
    const btnTemplate    = document.getElementById('btn-template-section');
    const ajaxUrl        = '<?= site_url('import/ajax-penyulang') ?>';
    const templateBase   = '<?= site_url('import/template-section') ?>';

    ulpSelect.addEventListener('change', function () {
        const ulpId = this.value;

        // Reset penyulang
        penyulangSelect.innerHTML = '<option value="">-- Pilih Penyulang --</option>';
        penyulangSelect.disabled = true;
        btnTemplate.href = '#';
        btnTemplate.classList.add('disabled');
        btnTemplate.style.opacity = '0.6';

        if (!ulpId) return;

        // Show loading
        loadingEl.classList.remove('d-none');

        fetch(ajaxUrl + '?ulp_id=' + ulpId)
            .then(res => res.json())
            .then(data => {
                loadingEl.classList.add('d-none');
                if (data.length === 0) {
                    penyulangSelect.innerHTML = '<option value="">-- Tidak ada penyulang --</option>';
                    return;
                }
                penyulangSelect.innerHTML = '<option value="">-- Pilih Penyulang --</option>';
                data.forEach(function (p) {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.nama_penyulang;
                    penyulangSelect.appendChild(opt);
                });
                penyulangSelect.disabled = false;
            })
            .catch(() => {
                loadingEl.classList.add('d-none');
                penyulangSelect.innerHTML = '<option value="">-- Gagal memuat --</option>';
            });
    });

    penyulangSelect.addEventListener('change', function () {
        const penyulangId = this.value;
        const ulpId = ulpSelect.value;

        if (!penyulangId || !ulpId) {
            btnTemplate.href = '#';
            btnTemplate.classList.add('disabled');
            btnTemplate.style.opacity = '0.6';
            return;
        }

        // Enable template download button with correct URL
        const url = templateBase + '?ulp_id=' + ulpId + '&penyulang_id=' + penyulangId;
        btnTemplate.href = url;
        btnTemplate.classList.remove('disabled');
        btnTemplate.style.opacity = '1';
    });

    // ============================================================
    // PENYULANG: Dropdown ULP → aktifkan tombol download template
    // ============================================================
    const penyulangUlpSelect  = document.getElementById('penyulang-ulp');
    const btnTemplatePenyulang = document.getElementById('btn-template-penyulang');
    const templatePenyulangBase = '<?= site_url('import/template-penyulang') ?>';

    penyulangUlpSelect.addEventListener('change', function () {
        const ulpId = this.value;

        if (!ulpId) {
            btnTemplatePenyulang.href = '#';
            btnTemplatePenyulang.classList.add('disabled');
            btnTemplatePenyulang.style.opacity = '0.6';
            return;
        }

        // Aktifkan tombol template penyulang dengan URL sesuai ULP
        btnTemplatePenyulang.href = templatePenyulangBase + '?ulp_id=' + ulpId;
        btnTemplatePenyulang.classList.remove('disabled');
        btnTemplatePenyulang.style.opacity = '1';
    });
</script>
<?= $this->endSection() ?>
