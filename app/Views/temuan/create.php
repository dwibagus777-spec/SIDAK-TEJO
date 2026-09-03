<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Input Temuan<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Input Temuan Baru<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('temuan') ?>">Data Temuan</a></li>
<li class="breadcrumb-item active">Input</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-8 col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-plus mr-1"></i> Form Temuan Baru</h3>
            </div>
            <!-- enctype="multipart/form-data" is required for file uploads -->
            <form id="form-create-temuan" action="<?= site_url('temuan/store') ?>" method="post" enctype="multipart/form-data" autocomplete="off">
                <?= csrf_field() ?>
                <div class="card-body">
                    
                    <?php $validation = isset($validation) ? $validation : null; ?>

                    <div class="row">
                        <!-- ULP Selection -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="ulp_id">Unit Layanan Pelanggan (ULP) <span class="text-danger">*</span></label>
                            <select name="ulp_id" id="ulp_id" class="form-control select2 <?= ($validation && $validation->hasError('ulp_id')) ? 'is-invalid' : '' ?>" required>
                                <option value="">-- Pilih ULP --</option>
                                <?php foreach ($ulps as $ulp): ?>
                                    <option value="<?= $ulp['id'] ?>" <?= old('ulp_id') == $ulp['id'] ? 'selected' : '' ?>><?= esc($ulp['nama_ulp']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($validation && $validation->hasError('ulp_id')): ?>
                                <div class="invalid-feedback"><?= $validation->getError('ulp_id') ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Penyulang Selection (Cascaded) -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="penyulang_id">Penyulang <span class="text-danger">*</span></label>
                            <select name="penyulang_id" id="penyulang_id" class="form-control select2 <?= ($validation && $validation->hasError('penyulang_id')) ? 'is-invalid' : '' ?>" required>
                                <option value="">-- Pilih ULP Dahulu --</option>
                            </select>
                            <?php if ($validation && $validation->hasError('penyulang_id')): ?>
                                <div class="invalid-feedback"><?= $validation->getError('penyulang_id') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Section Selection (Cascaded) -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="section_id">Section / Ruas <span class="text-danger">*</span></label>
                            <select name="section_id" id="section_id" class="form-control select2 <?= ($validation && $validation->hasError('section_id')) ? 'is-invalid' : '' ?>" required>
                                <option value="">-- Pilih Penyulang Dahulu --</option>
                            </select>
                            <?php if ($validation && $validation->hasError('section_id')): ?>
                                <div class="invalid-feedback"><?= $validation->getError('section_id') ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Asset Selection (Cascaded from Section) - Default Optional, Required only when material requested -->
                        <div class="col-md-6 form-group mb-3" id="mr01_asset_form_group">
                            <label for="mr01_asset_id" id="mr01_asset_label">Aset Jaringan (Opsional) <span id="mr01_asset_req_indicator"><span class="badge bg-light text-muted border ms-1 fw-normal" style="font-size: 11px;">Opsional</span></span></label>
                            <select name="asset_id" id="mr01_asset_id" class="form-control select2">
                                <option value="">-- Tidak dipilih / Opsional --</option>
                            </select>
                            <small class="text-muted d-block mt-1" id="mr01_asset_help_text">
                                Aset jaringan tidak wajib diisi. Hanya diperlukan jika menambahkan kebutuhan material konstruksi.
                            </small>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Jenis Temuan -->
                        <div class="col-md-3 form-group mb-3">
                            <label for="jenis_temuan">Jenis Temuan <span class="text-danger">*</span></label>
                            <select name="jenis_temuan" id="jenis_temuan" class="form-control select2 <?= ($validation && $validation->hasError('jenis_temuan')) ? 'is-invalid' : '' ?>" required>
                                <option value="">-- Pilih Jenis --</option>
                                <option value="KONSTRUKSI" <?= old('jenis_temuan') === 'KONSTRUKSI' ? 'selected' : '' ?>>KONSTRUKSI</option>
                                <option value="HOTSPOT" <?= old('jenis_temuan') === 'HOTSPOT' ? 'selected' : '' ?>>HOTSPOT</option>
                                <option value="ROW" <?= old('jenis_temuan') === 'ROW' ? 'selected' : '' ?>>ROW (Right of Way)</option>
                            </select>
                            <?php if ($validation && $validation->hasError('jenis_temuan')): ?>
                                <div class="invalid-feedback"><?= $validation->getError('jenis_temuan') ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Pelaksana -->
                        <div class="col-md-3 form-group mb-3">
                            <label for="pelaksana">Pelaksana Pekerjaan <span class="text-danger">*</span></label>
                            <select name="pelaksana" id="pelaksana" class="form-control select2 <?= ($validation && $validation->hasError('pelaksana')) ? 'is-invalid' : '' ?>" required>
                                <option value="">-- Pilih Pelaksana --</option>
                                <option value="PDKB" <?= old('pelaksana') === 'PDKB' ? 'selected' : '' ?>>PDKB</option>
                                <option value="HAR GARDU" <?= old('pelaksana') === 'HAR GARDU' ? 'selected' : '' ?>>HAR GARDU</option>
                                <option value="HAR GTT" <?= old('pelaksana') === 'HAR GTT' ? 'selected' : '' ?>>HAR GTT</option>
                                <option value="HAR KONSTRUKSI" <?= old('pelaksana') === 'HAR KONSTRUKSI' ? 'selected' : '' ?>>HAR KONSTRUKSI</option>
                                <option value="HAR ROW" <?= old('pelaksana') === 'HAR ROW' ? 'selected' : '' ?>>HAR ROW</option>
                                <option value="HAR CRANE" <?= old('pelaksana') === 'HAR CRANE' ? 'selected' : '' ?>>HAR CRANE</option>
                            </select>
                            <?php if ($validation && $validation->hasError('pelaksana')): ?>
                                <div class="invalid-feedback"><?= $validation->getError('pelaksana') ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Prioritas -->
                        <div class="col-md-3 form-group mb-3">
                            <label for="prioritas">Prioritas SLA <span class="text-danger">*</span></label>
                            <select name="prioritas" id="prioritas" class="form-control select2 <?= ($validation && $validation->hasError('prioritas')) ? 'is-invalid' : '' ?>" required>
                                <option value="">-- Pilih Prioritas --</option>
                                <option value="EMERGENCY" <?= old('prioritas') === 'EMERGENCY' ? 'selected' : '' ?>>EMERGENCY (1x24 Jam)</option>
                                <option value="HIGH" <?= old('prioritas') === 'HIGH' ? 'selected' : '' ?>>HIGH (3 Hari)</option>
                                <option value="MEDIUM" <?= old('prioritas') === 'MEDIUM' ? 'selected' : '' ?>>MEDIUM (7 Hari)</option>
                            </select>
                            <?php if ($validation && $validation->hasError('prioritas')): ?>
                                <div class="invalid-feedback"><?= $validation->getError('prioritas') ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Potensi Gangguan -->
                        <div class="col-md-3 form-group mb-3">
                            <label for="potensi_gangguan">Potensi Gangguan <span class="text-danger">*</span></label>
                            <select name="potensi_gangguan" id="potensi_gangguan" class="form-control select2 <?= ($validation && $validation->hasError('potensi_gangguan')) ? 'is-invalid' : '' ?>" required>
                                <option value="">-- Pilih Potensi --</option>
                                <option value="DGR" <?= old('potensi_gangguan') === 'DGR' ? 'selected' : '' ?>>DGR (Directional Ground Relays)</option>
                                <option value="OCR" <?= old('potensi_gangguan') === 'OCR' ? 'selected' : '' ?>>OCR (Over Current Relays)</option>
                                <option value="OCRDGR" <?= old('potensi_gangguan') === 'OCRDGR' ? 'selected' : '' ?>>OCRDGR</option>
                            </select>
                            <?php if ($validation && $validation->hasError('potensi_gangguan')): ?>
                                <div class="invalid-feedback"><?= $validation->getError('potensi_gangguan') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Konduktor -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="konduktor">Nama Konduktor <span class="text-danger">*</span></label>
                            <input type="text" name="konduktor" id="konduktor" class="form-control <?= ($validation && $validation->hasError('konduktor')) ? 'is-invalid' : '' ?>" placeholder="Contoh: A3CS 150mm" value="<?= old('konduktor') ?>" required>
                            <?php if ($validation && $validation->hasError('konduktor')): ?>
                                <div class="invalid-feedback"><?= $validation->getError('konduktor') ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- NOGA -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="noga">Nomor Gardu (NOGA)</label>
                            <input type="text" name="noga" id="noga" class="form-control <?= ($validation && $validation->hasError('noga')) ? 'is-invalid' : '' ?>" placeholder="Contoh: G.123 (Boleh kosong)" value="<?= old('noga') ?>">
                            <?php if ($validation && $validation->hasError('noga')): ?>
                                <div class="invalid-feedback"><?= $validation->getError('noga') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- MR-01: Governed Material Requirement Section (Triggered by operator request) -->
                    <div class="form-group mb-4 p-3 rounded" id="mr01-material-picker-section" style="background-color: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 14px;">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <div>
                                <label class="font-weight-bold text-dark mb-0" style="font-size: 14px;">
                                    <i class="fas fa-boxes-stacked text-primary me-2"></i> Material yang Dibutuhkan
                                </label>
                                <small class="text-muted d-block" style="font-size: 11px;">
                                    Opsional — Ditambahkan jika temuan membutuhkan penggantian / pemasangan material konstruksi resmi PLN.
                                </small>
                            </div>
                            <div id="mr01_construction_badge_container">
                                <span class="badge bg-light text-muted border p-2" id="mr01_construction_badge" style="font-size: 12px; font-weight: 600;">
                                    <i class="fas fa-info-circle me-1"></i> Tidak ada material diminta
                                </span>
                            </div>
                        </div>

                        <!-- STATE A: No Material Requested (Default Initial State) -->
                        <div class="text-center py-4" id="mr01_state_no_material">
                            <i class="fas fa-box-open fa-2x mb-2 text-secondary opacity-50"></i>
                            <h6 class="fw-bold mb-1 text-dark" style="font-size: 13px;">Tidak Ada Kebutuhan Material</h6>
                            <p class="small mb-3 text-secondary" style="font-size: 12px;">Temuan dapat langsung disimpan tanpa memilih aset maupun material.</p>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm fw-bold" id="btn-enable-material-flow">
                                <i class="fas fa-plus me-1"></i> Tambah Material yang Dibutuhkan
                            </button>
                        </div>

                        <!-- MATERIAL REQUEST FLOW CONTAINER (Active when user clicks Tambah Material) -->
                        <div id="mr01_material_flow_container" style="display: none;">
                            <!-- Flow Notice / Banner -->
                            <div class="alert alert-info py-2 px-3 mb-3 d-flex justify-content-between align-items-center" id="mr01_material_notice_banner">
                                <div class="small">
                                    <i class="fas fa-info-circle me-1"></i> <strong>Mode Kebutuhan Material Aktif</strong>: Aset Jaringan diperlukan untuk menentukan konstruksi dan material resmi.
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1 ms-2" id="btn-cancel-material-flow" style="font-size: 11px;">
                                    <i class="fas fa-times me-1"></i> Batal Tambah Material
                                </button>
                            </div>

                            <!-- State B: Asset belum dipilih saat flow material aktif -->
                            <div class="text-center py-3 text-warning border rounded bg-white mb-2" id="mr01_empty_asset_state">
                                <i class="fas fa-arrow-pointer fa-2x mb-2 text-warning opacity-75"></i>
                                <h6 class="fw-bold mb-1 text-dark" style="font-size: 13px;">Pilih asset terlebih dahulu untuk melihat material</h6>
                                <p class="small mb-0 text-secondary" style="font-size: 12px;">Untuk menambahkan material konstruksi, pilih Aset Jaringan terlebih dahulu.</p>
                            </div>

                            <!-- State C: Sedang memuat BOM -->
                            <div class="text-center py-3 text-primary" id="mr01_loading_state" style="display: none;">
                                <i class="fas fa-circle-notch fa-spin fa-2x mb-2"></i>
                                <p class="small fw-bold mb-0">Mengidentifikasi standar konstruksi & memuat material BOM...</p>
                            </div>

                            <!-- Alert container (NO_CONSTRUCTION / NO_BOM / PROVISIONAL_BLOCKED / ERROR) -->
                            <div id="mr01_picker_alert" class="mt-2 mb-2" style="display: none;"></div>

                            <!-- State D/E: Selectable BOM Material Rows Container -->
                            <div id="mr01_bom_preview_container" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="fw-bold text-secondary">
                                        <i class="fas fa-list-check me-1"></i> Centang material yang dibutuhkan & masukkan jumlah kebutuhan:
                                    </small>
                                    <small class="text-muted" id="mr01_bom_count_info"></small>
                                </div>
                                <div id="mr01_bom_chips_container" class="p-2 bg-white rounded border" style="max-height: 380px; overflow-y: auto;">
                                    <!-- Material rows dynamically populated -->
                                </div>
                            </div>
                        </div>

                        <!-- Hidden JSON input for structured material transactions -->
                        <input type="hidden" name="structured_materials_json" id="structured_materials_json" value="">
                        <!-- Hidden material input for legacy text compatibility -->
                        <input type="hidden" name="material" id="material-hidden-field" value="">
                    </div>

                    <!-- Dedicated ROW Fallback Container (Only active if jenis_temuan === 'ROW') -->
                    <div class="form-group mb-3 p-3 rounded" id="row-vegetasi-container" style="display: none; background-color: #f0fdf4; border: 1px solid #86efac; border-radius: 12px;">
                        <label class="fw-bold text-success mb-1" style="font-size: 13px;">
                            <i class="fas fa-tree me-1"></i> Catatan Vegetasi / Pohon (Khusus Temuan ROW)
                        </label>
                        <small class="text-muted d-block mb-2">Keterangan jenis pohon, diameter, atau jarak bebas dahan terhadap kabel/jaringan.</small>
                        <input type="text" name="catatan_row" id="catatan_row" class="form-control form-control-sm" placeholder="Contoh: Pohon Sengon diameter 30cm mendekati konduktor 1.5 meter">
                    </div>

                    <!-- Detail Temuan -->
                    <div class="form-group mb-3">
                        <label for="detail_temuan">Detail Temuan Inspeksi <span class="text-danger">*</span></label>
                        <textarea name="detail_temuan" id="detail_temuan" class="form-control <?= ($validation && $validation->hasError('detail_temuan')) ? 'is-invalid' : '' ?>" rows="3" placeholder="Jelaskan detail kerusakan/temuan di lapangan..." required><?= old('detail_temuan') ?></textarea>
                        <?php if ($validation && $validation->hasError('detail_temuan')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('detail_temuan') ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Alamat -->
                    <div class="form-group mb-3">
                        <label for="alamat">Alamat Lokasi Temuan <span class="text-danger">*</span></label>
                        <textarea name="alamat" id="alamat" class="form-control <?= ($validation && $validation->hasError('alamat')) ? 'is-invalid' : '' ?>" rows="2" placeholder="Contoh: Jl. Ahmad Yani No. 12, Sidoarjo" required><?= old('alamat') ?></textarea>
                        <?php if ($validation && $validation->hasError('alamat')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('alamat') ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Geolocation / Coordinates -->
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="latitude">Latitude <small class="text-muted">(manual atau klik peta)</small></label>
                            <input type="text" name="latitude" id="latitude" class="form-control <?= ($validation && $validation->hasError('latitude')) ? 'is-invalid' : '' ?>" placeholder="Contoh: -7.447812" value="<?= old('latitude') ?>">
                            <?php if ($validation && $validation->hasError('latitude')): ?>
                                <div class="invalid-feedback"><?= $validation->getError('latitude') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="longitude">Longitude <small class="text-muted">(manual atau klik peta)</small></label>
                            <input type="text" name="longitude" id="longitude" class="form-control <?= ($validation && $validation->hasError('longitude')) ? 'is-invalid' : '' ?>" placeholder="Contoh: 112.718324" value="<?= old('longitude') ?>">
                            <?php if ($validation && $validation->hasError('longitude')): ?>
                                <div class="invalid-feedback"><?= $validation->getError('longitude') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3 d-flex gap-2 align-items-center flex-wrap">
                        <button type="button" class="btn btn-info text-white btn-sm" id="btn-geolocation"><i class="fas fa-location-crosshairs mr-1"></i> Ambil Lokasi Saya</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-sync-map" title="Perbarui posisi pin peta sesuai koordinat yang diketik">
                            <i class="fas fa-map-pin mr-1"></i> Sinkronkan ke Peta
                        </button>
                        <span class="text-muted small align-self-center">*Atau klik/geser pin pada peta di sebelah kanan.</span>
                    </div>

                    <div class="row">
                        <!-- Tanggal Temuan -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="tanggal_temuan">Tanggal Temuan <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="date"
                                       name="tanggal_temuan"
                                       id="tanggal_temuan"
                                       class="form-control <?= ($validation && $validation->hasError('tanggal_temuan')) ? 'is-invalid' : '' ?>"
                                       value="<?= esc($defaultTanggal ?? old('tanggal_temuan', date('Y-m-d'))) ?>"
                                       max="<?= esc($serverToday ?? date('Y-m-d')) ?>"
                                       autocomplete="off"
                                       required>
                                <button type="button" class="btn btn-outline-primary shadow-xs" id="btn-set-today" title="Set tanggal ke hari ini">
                                    <i class="fas fa-calendar-day me-1"></i> Hari Ini
                                </button>
                                <?php if ($validation && $validation->hasError('tanggal_temuan')): ?>
                                    <div class="invalid-feedback"><?= $validation->getError('tanggal_temuan') ?></div>
                                <?php endif; ?>
                            </div>
                            <small class="form-text text-muted mt-1 d-block">
                                <i class="fas fa-info-circle me-1 text-info"></i> Tanggal kejadian/temuan di lapangan, bukan tanggal input sistem.
                            </small>
                        </div>
                    </div>

                    <!-- Upload Foto (Pilihan Galeri / Berkas & Kamera Direct) -->
                    <div class="form-group mb-3">
                        <label class="font-weight-bold">Unggah Foto Temuan (Minimal 1, Maksimal 10) <span class="text-danger">*</span></label>
                        
                        <div class="p-3 border rounded bg-light shadow-sm">
                            <div class="row g-2 mb-2">
                                <div class="col-sm-6 col-12 mb-2 mb-sm-0">
                                    <label for="foto" class="btn btn-outline-primary btn-block w-100 py-2 font-weight-bold shadow-sm mb-0 text-center" style="cursor: pointer;">
                                        <i class="fas fa-folder-open text-primary mr-1"></i> 📁 Pilih dari Galeri / Berkas
                                    </label>
                                </div>
                                <div class="col-sm-6 col-12">
                                    <label for="foto_camera" class="btn btn-outline-success btn-block w-100 py-2 font-weight-bold shadow-sm mb-0 text-center" style="cursor: pointer;">
                                        <i class="fas fa-camera text-success mr-1"></i> 📷 Ambil Foto via Kamera
                                    </label>
                                </div>
                            </div>

                            <input type="file" name="foto[]" id="foto" class="d-none" multiple accept="image/*">
                            <input type="file" id="foto_camera" class="d-none" multiple accept="image/*" capture="environment">

                            <div id="file-selection-info" class="small text-muted mt-2">
                                <i class="fas fa-info-circle mr-1"></i> Format berkas: JPG, JPEG, PNG, WEBP. Bisa memilih dari Galeri atau ambil langsung via Kamera.
                            </div>
                        </div>
                    </div>

                    <!-- Pratinjau Foto Upload -->
                    <div class="row mt-3 px-2" id="preview-container">
                        <!-- Pratinjau gambar akan disuntikkan secara dinamis di sini -->
                    </div>

                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" id="btn-submit"><i class="fas fa-save mr-1"></i> Simpan Temuan</button>
                    <a href="javascript:smartBack('<?= site_url('temuan') ?>');" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Peta Selector Koordinat & Card AI Recommendation -->
    <div class="col-lg-4 col-12">
        <!-- Card AI Recommendation Modern (Phase 38) -->
        <div class="card mb-3 shadow-sm border-0" style="border-left: 4px solid #7e22ce !important; border-radius: 16px; background: #ffffff;">
            <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center" style="font-size: 14px;">
                    <i class="fas fa-brain text-purple me-2"></i> AI Recommendation Engine
                </h5>
                <span class="badge bg-purple text-white rounded-pill px-2" style="background:#7e22ce; font-size:10px;">Rule-Based Engine</span>
            </div>
            <div class="card-body pt-2" style="font-size: 12px;">
                <div id="ai-rec-box">
                    <div class="text-muted text-center py-3">
                        <i class="fas fa-sparkles text-warning me-1"></i> Isi form untuk menghasilkan rekomendasi AI otomatis...
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="position: sticky; top: 1rem;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-map-pin text-danger mr-1"></i> Pemilih Koordinat Peta</h3>
            </div>
            <div class="card-body p-0">
                <div id="selector-map" style="height: 380px; width: 100%; border-radius: 0 0 12px 12px;"></div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function() {
    function initTemuanCreate() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initTemuanCreate, 50);
            return;
        }
        var $ = jQuery;
        $(function() {
            // --- CR-06: TEMUAN DATE STALE-FORM HARDENING ---
            const isValidationReturn = <?= (!empty($isValidationReturn)) ? 'true' : 'false' ?>;
            const serverToday = "<?= esc($serverToday ?? date('Y-m-d')) ?>";

            // Track intentional user edits so tab switching / browser events don't wipe historical input
            $('#tanggal_temuan').on('input change', function() {
                this.dataset.userEdited = 'true';
            });

            // If page is restored from bfcache or tab-restore, ensure fresh form has today's date
            // but NEVER overwrite an intentional user edit or a validation-return state
            window.addEventListener('pageshow', function(event) {
                const tglInput = document.getElementById('tanggal_temuan');
                if (!tglInput) return;

                // Strict invariant: NEVER overwrite intentional user input, even if event.persisted is true
                if (!isValidationReturn && !tglInput.dataset.userEdited) {
                    tglInput.value = serverToday;
                }
            });

            // Quick Action: [ 📅 Hari Ini ] button
            $('#btn-set-today').on('click', function() {
                const tglInput = document.getElementById('tanggal_temuan');
                if (tglInput) {
                    tglInput.value = serverToday;
                    tglInput.dataset.userEdited = 'true';
                    $(tglInput).removeClass('is-invalid');
                }
            });

            // --- 1. CASCADING DROPDOWNS WITH REQUEST TOKEN GUARD ---
            const urlParams = new URLSearchParams(window.location.search);
            const oldPenyulangId = "<?= old('penyulang_id') ?>" || urlParams.get('penyulang_id') || "";
            const oldSectionId = "<?= old('section_id') ?>" || urlParams.get('section_id') || "";
            const oldAssetId = "<?= old('asset_id') ?>" || urlParams.get('asset_id') || "";
            const urlUlpId = urlParams.get('ulp_id') || "";
            let penyulangRequestToken = 0;
            let sectionRequestToken = 0;

            function refreshSelect2($element) {
                if ($.fn.select2 && $element.hasClass('select2-hidden-accessible')) {
                    $element.trigger('change.select2');
                } else {
                    $element.trigger('change');
                }
            }

            function loadPenyulang(ulpId, callback) {
                const $penyulang = $('#penyulang_id');
                const $section = $('#section_id');
                const currentToken = ++penyulangRequestToken;

                if (!ulpId) {
                    $penyulang.html('<option value="">-- Pilih ULP Dahulu --</option>');
                    $section.html('<option value="">-- Pilih Penyulang Dahulu --</option>');
                    refreshSelect2($penyulang);
                    refreshSelect2($section);
                    return;
                }

                $penyulang.html('<option value="">Sedang memuat...</option>');
                refreshSelect2($penyulang);

                const requestUrl = "<?= site_url('ajax/network/penyulang') ?>/" + ulpId;

                $.ajax({
                    url: requestUrl,
                    type: "GET",
                    dataType: "json",
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    success: function(data) {
                        if (currentToken !== penyulangRequestToken) return;

                        const totalPenyulang = Array.isArray(data) ? data.length : 0;
                        let html = '<option value="">-- Pilih Penyulang --</option>';
                        if (totalPenyulang > 0) {
                            data.forEach(function(item) {
                                html += `<option value="${item.id}">${item.nama_penyulang}</option>`;
                            });
                        } else {
                            html = '<option value="">-- Tidak ada penyulang aktif --</option>';
                        }
                        $penyulang.html(html);
                        refreshSelect2($penyulang);

                        $section.html('<option value="">-- Pilih Penyulang Dahulu --</option>');
                        refreshSelect2($section);

                        if (callback) callback();
                    },
                    error: function(xhr, status, err) {
                        if (currentToken !== penyulangRequestToken) return;
                        $penyulang.html('<option value="">Gagal memuat penyulang (Status: ' + xhr.status + ')</option>');
                        refreshSelect2($penyulang);
                    }
                });
            }

        function loadSection(penyulangId, callback) {
            const $section = $('#section_id');

            console.log("[SIDAK TEJO] Penyulang berubah:", penyulangId);

            if (!penyulangId) {
                $section.html('<option value="">-- Pilih Penyulang Dahulu --</option>');
                refreshSelect2($section);
                return;
            }

            $section.html('<option value="">Sedang memuat...</option>');
            refreshSelect2($section);

            const requestUrl = "<?= site_url('ajax/network/section') ?>/" + penyulangId;
            console.log("[SIDAK TEJO] Request AJAX Section URL:", requestUrl);

            $.ajax({
                url: requestUrl,
                type: "GET",
                dataType: "json",
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(data) {
                    console.log("[SIDAK TEJO] Response JSON Section:", data);
                    const totalSection = Array.isArray(data) ? data.length : 0;
                    console.log("[SIDAK TEJO] Jumlah Section:", totalSection);

                    let html = '<option value="">-- Pilih Section --</option>';
                    if (totalSection > 0) {
                        data.forEach(function(item) {
                            html += `<option value="${item.id}">${item.nama_section}</option>`;
                        });
                    } else {
                        html = '<option value="">-- Tidak ada section aktif --</option>';
                    }
                    $section.html(html);
                    refreshSelect2($section);

                    if (callback) callback();
                },
                error: function(xhr, status, err) {
                    console.error("[SIDAK TEJO] Gagal AJAX Section!", {
                        url: requestUrl,
                        statusCode: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: err
                    });
                    $section.html('<option value="">Gagal memuat section (Status: ' + xhr.status + ')</option>');
                    refreshSelect2($section);
                }
            });
        }

        // Dropdown triggers
        $('#ulp_id').on('change', function() {
            loadPenyulang($(this).val());
        });

        $('#penyulang_id').on('change', function() {
            loadSection($(this).val());
        });

        // Restore old input cascade (if validation fails or pre-selected from map/URL)
        let initialUlpId = $('#ulp_id').val();
        if (!initialUlpId && urlUlpId) {
            $('#ulp_id').val(urlUlpId);
            refreshSelect2($('#ulp_id'));
            initialUlpId = urlUlpId;
        }

        if (initialUlpId) {
            loadPenyulang(initialUlpId, function() {
                if (oldPenyulangId) {
                    $('#penyulang_id').val(oldPenyulangId);
                    refreshSelect2($('#penyulang_id'));
                    loadSection(oldPenyulangId, function() {
                        if (oldSectionId) {
                            $('#section_id').val(oldSectionId);
                            refreshSelect2($('#section_id'));
                            loadAssetsForSection(oldSectionId, function() {
                                if (oldAssetId) {
                                    $('#mr01_asset_id').val(oldAssetId);
                                    refreshSelect2($('#mr01_asset_id'));
                                    $('#mr01_asset_id').trigger('change');
                                }
                            });
                        }
                    });
                }
            });
        }

        // ====================================================================
        // MR-01 STATE MACHINE: GOVERNED MATERIAL REQUIREMENT & ASSET BINDING
        //
        // Invariant: All Temuan -> asset is OPTIONAL by default.
        // Asset becomes REQUIRED ONLY when operator requests governed materials.
        //
        // State A: NO_MATERIAL_REQUEST (asset optional, "+ Tambah Material" visible)
        // State B: MATERIAL_REQUEST_OPEN (asset required for material flow)
        // State C: ASSET_SELECTED (resolving construction)
        // State D: MATERIAL_SELECTION (BOM whitelist loaded, user selects materials)
        // State E: MATERIAL_CONFIRMED (quantities verified, ready to submit)
        // ====================================================================
        let isMaterialFlowActive = false;

        function setMaterialFlowState(active, autoFocusAsset = false) {
            isMaterialFlowActive = !!active;
            const $reqIndicator = $('#mr01_asset_req_indicator');
            const $assetHelp = $('#mr01_asset_help_text');
            const $badge = $('#mr01_construction_badge');
            const $stateNoMaterial = $('#mr01_state_no_material');
            const $flowContainer = $('#mr01_material_flow_container');
            const $emptyAssetState = $('#mr01_empty_asset_state');
            const $assetSelect = $('#mr01_asset_id');
            const currentAsset = $assetSelect.val();

            if (!isMaterialFlowActive) {
                // --- STATE A: NO_MATERIAL_REQUEST ---
                $stateNoMaterial.show();
                $flowContainer.hide();
                $reqIndicator.html('<span class="badge bg-light text-muted border ms-1 fw-normal" style="font-size: 11px;">Opsional</span>');
                $assetHelp.html('Aset jaringan tidak wajib diisi. Hanya diperlukan jika menambahkan kebutuhan material konstruksi.');
                $badge.attr('class', 'badge bg-light text-muted border p-2')
                      .html('<i class="fas fa-info-circle me-1"></i> Tidak ada material diminta');

                // Clear material selections and reset hidden fields
                $('.mr01-mat-check').prop('checked', false);
                $('.mr01-mat-input-row').hide();
                $('.mr01-mat-qty').val('').removeClass('is-invalid');
                $('.mr01-mat-note').val('');
                $('#structured_materials_json').val('');
                $('#material-hidden-field').val('Tidak ada spesifikasi material');
            } else {
                // --- STATE B / C / D / E: MATERIAL_REQUEST_ACTIVE ---
                $stateNoMaterial.hide();
                $flowContainer.slideDown(200);
                $reqIndicator.html('<span class="badge bg-warning text-dark border ms-1 fw-bold" style="font-size: 11px;"><i class="fas fa-exclamation-circle me-1"></i>Diperlukan untuk Material</span>');
                $assetHelp.html('<span class="text-primary fw-bold"><i class="fas fa-info-circle me-1"></i>Aset Jaringan diperlukan untuk menentukan konstruksi dan material resmi.</span>');

                if (!currentAsset) {
                    // State B: Asset not yet chosen
                    $emptyAssetState.show();
                    $('#mr01_bom_preview_container').hide();
                    $('#mr01_picker_alert').hide();
                    $badge.attr('class', 'badge bg-warning text-dark p-2')
                          .html('<i class="fas fa-arrow-pointer me-1"></i> Pilih Aset Jaringan Dahulu');

                    if (autoFocusAsset) {
                        $('html, body').animate({
                            scrollTop: $('#mr01_asset_form_group').offset().top - 100
                        }, 300);
                        setTimeout(function() {
                            $assetSelect.select2('open');
                        }, 350);
                    }
                } else {
                    // State C/D: Asset already selected, trigger BOM picker
                    loadMaterialPickerForAsset(currentAsset, $('#section_id').val());
                }
            }
        }

        // Enable material flow button
        $('#btn-enable-material-flow').on('click', function(e) {
            e.preventDefault();
            setMaterialFlowState(true, true);
        });

        // Cancel material flow button
        $('#btn-cancel-material-flow').on('click', function(e) {
            e.preventDefault();
            setMaterialFlowState(false);
        });

        // --- Load Assets for Section (Default: Optional) ---
        function loadAssetsForSection(sectionId, callback) {
            const $assetSelect = $('#mr01_asset_id');
            const $emptyState = $('#mr01_empty_asset_state');
            const $bomContainer = $('#mr01_bom_preview_container');
            const $chips = $('#mr01_bom_chips_container');

            $bomContainer.hide();
            $chips.empty();
            $('#structured_materials_json').val('');
            $('#material-hidden-field').val('Tidak ada spesifikasi material');

            if (!sectionId) {
                $assetSelect.html('<option value="">-- Tidak dipilih / Opsional --</option>');
                refreshSelect2($assetSelect);
                if (isMaterialFlowActive) $emptyState.show();
                return;
            }

            $assetSelect.html('<option value="">Sedang memuat aset...</option>');
            refreshSelect2($assetSelect);

            $.ajax({
                url: "<?= site_url('ajax/network/asset') ?>/" + sectionId,
                type: "GET",
                dataType: "json",
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(data) {
                    let html = '<option value="">-- Tidak dipilih / Opsional --</option>';
                    if (Array.isArray(data) && data.length > 0) {
                        data.forEach(function(a) {
                            const label = (a.kode_asset ? `[${a.kode_asset}] ` : '') + (a.nama_asset || 'Asset #' + a.id);
                            html += `<option value="${a.id}">${label}</option>`;
                        });
                    } else {
                        html = '<option value="">-- Tidak ada aset pada section ini --</option>';
                    }
                    $assetSelect.html(html);
                    refreshSelect2($assetSelect);
                    if (isMaterialFlowActive && !$assetSelect.val()) {
                        $emptyState.show();
                    }
                    if (callback) callback();
                },
                error: function() {
                    $assetSelect.html('<option value="">-- Tidak dipilih / Opsional --</option>');
                    refreshSelect2($assetSelect);
                }
            });
        }

        $('#section_id').on('change', function() {
            loadAssetsForSection($(this).val());
        });

        function loadMaterialPickerForAsset(assetId, sectionId) {
            const $badge = $('#mr01_construction_badge');
            const $bomContainer = $('#mr01_bom_preview_container');
            const $chips = $('#mr01_bom_chips_container');
            const $countInfo = $('#mr01_bom_count_info');
            const $alert = $('#mr01_picker_alert');
            const $emptyState = $('#mr01_empty_asset_state');
            const $loadingState = $('#mr01_loading_state');

            $bomContainer.hide();
            $alert.hide();
            $chips.empty();
            $('#structured_materials_json').val('');
            $('#material-hidden-field').val('Tidak ada spesifikasi material');

            if (!assetId) {
                $loadingState.hide();
                $emptyState.show();
                $badge.attr('class', 'badge bg-warning text-dark p-2')
                      .html('<i class="fas fa-arrow-pointer me-1"></i> Pilih Aset Jaringan Dahulu');
                return;
            }

            $emptyState.hide();
            $loadingState.show();
            $badge.attr('class', 'badge bg-light text-primary border p-2')
                  .html('<i class="fas fa-spinner fa-spin me-1"></i> Mengidentifikasi konstruksi...');

            $.ajax({
                url: "<?= site_url('temuan/ajax-material-picker') ?>",
                type: "GET",
                data: { asset_id: assetId, section_id: sectionId },
                dataType: "json",
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(res) {
                    $loadingState.hide();
                    if (!res) return;

                    if (res.status === 'READY') {
                        $badge.attr('class', 'badge bg-success text-white p-2')
                              .html('<i class="fas fa-lock me-1"></i> Konstruksi: ' + (res.construction.code ? res.construction.code + ' &mdash; ' : '') + res.construction.name);

                        if (Array.isArray(res.materials) && res.materials.length > 0) {
                            $countInfo.text(res.materials.length + ' material eligible');
                            res.materials.forEach(function(m) {
                                const row = `
                                <div class="mr01-material-item border-bottom py-2" data-material-id="${m.id}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input mr01-mat-check" type="checkbox" id="mat_chk_${m.id}" value="${m.id}" data-name="${m.name}" data-code="${m.code || ''}" data-unit="${m.unit || 'SET'}">
                                            <label class="form-check-label fw-bold text-dark" for="mat_chk_${m.id}" style="font-size: 13px; cursor: pointer;">
                                                ${m.name}
                                                ${m.code ? '<code class="ms-1 small text-secondary">[' + m.code + ']</code>' : ''}
                                                ${m.field_alias ? '<small class="text-muted ms-1">(' + m.field_alias + ')</small>' : ''}
                                            </label>
                                        </div>
                                        <span class="badge bg-secondary text-white">${m.unit || 'SET'}</span>
                                    </div>
                                    <div class="mr01-mat-input-row mt-2" id="mat_input_row_${m.id}" style="display: none; padding-left: 24px;">
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text bg-light text-secondary fw-bold">Qty</span>
                                                    <input type="number" step="0.01" min="0.01" class="form-control mr01-mat-qty" placeholder="Jumlah (misal: 1.00)" id="mat_qty_${m.id}">
                                                    <span class="input-group-text bg-light">${m.unit || 'SET'}</span>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <input type="text" class="form-control form-control-sm mr01-mat-note" placeholder="Catatan alasan penggantian / justifikasi teknis (opsional)" id="mat_note_${m.id}">
                                            </div>
                                        </div>
                                    </div>
                                </div>`;
                                $chips.append(row);
                            });
                            $bomContainer.slideDown(200);
                        } else {
                            $alert.attr('class', 'alert alert-info py-2 px-3 mb-0 mt-2')
                                  .html('<i class="fas fa-info-circle me-1"></i> Tidak ada material eligible terdaftar untuk konstruksi ini.')
                                  .show();
                        }
                    } else if (res.status === 'NO_CONSTRUCTION') {
                        $badge.attr('class', 'badge bg-warning text-dark p-2')
                              .html('<i class="fas fa-exclamation-triangle me-1"></i> NON-KONSTRUKSI');
                        $alert.attr('class', 'alert alert-warning py-2 px-3 mb-0 mt-2')
                              .html('<i class="fas fa-exclamation-triangle me-1"></i> <strong>KONSTRUKSI BELUM TERPETAKAN</strong>: Aset ini belum memiliki standar konstruksi PLN terdaftar. Material picker tidak tersedia.')
                              .show();
                    } else if (res.status === 'NO_BOM') {
                        $badge.attr('class', 'badge bg-info text-white p-2')
                              .html('<i class="fas fa-info-circle me-1"></i> ' + (res.construction ? res.construction.code : 'KONSTRUKSI'));
                        $alert.attr('class', 'alert alert-info py-2 px-3 mb-0 mt-2')
                              .html('<i class="fas fa-info-circle me-1"></i> <strong>BOM KONSTRUKSI BELUM TERSEDIA</strong>: Standar konstruksi terdaftar' + (res.construction ? ' (' + res.construction.name + ')' : '') + ', namun rincian material BOM belum tersedia di sistem.')
                              .show();
                    } else if (res.status === 'PROVISIONAL_BLOCKED') {
                        $badge.attr('class', 'badge bg-secondary text-white p-2')
                              .html('<i class="fas fa-ban me-1"></i> DRAFT (BELUM FIX)');
                        $alert.attr('class', 'alert alert-secondary py-2 px-3 mb-0 mt-2')
                              .html('<i class="fas fa-ban me-1"></i> <strong>DRAFT / PROVISIONAL</strong>: ' + res.message)
                              .show();
                    } else {
                        $badge.attr('class', 'badge bg-danger text-white p-2')
                              .html('<i class="fas fa-times-circle me-1"></i> ' + (res.status || 'ERROR'));
                        $alert.attr('class', 'alert alert-danger py-2 px-3 mb-0 mt-2')
                              .html('<i class="fas fa-times-circle me-1"></i> ' + (res.message || 'Terjadi kesalahan sistem.'))
                              .show();
                    }
                },
                error: function(xhr) {
                    $loadingState.hide();
                    $badge.attr('class', 'badge bg-danger text-white p-2')
                          .html('<i class="fas fa-times-circle me-1"></i> Gagal Memuat');
                    $alert.attr('class', 'alert alert-danger py-2 px-3 mb-0 mt-2')
                          .html('<i class="fas fa-times-circle me-1"></i> Gagal memverifikasi konstruksi aset (Status: ' + xhr.status + ')')
                          .show();
                }
            });
        }

        $('#mr01_asset_id').on('change', function() {
            const assetId = $(this).val();
            const sectionId = $('#section_id').val();
            if (isMaterialFlowActive) {
                loadMaterialPickerForAsset(assetId, sectionId);
            }
        });

        // Dynamic toggle of material inputs and serialization to JSON
        $(document).on('change', '.mr01-mat-check', function() {
            const matId = $(this).val();
            if ($(this).is(':checked')) {
                $('#mat_input_row_' + matId).slideDown(150);
                $('#mat_qty_' + matId).focus();
            } else {
                $('#mat_input_row_' + matId).slideUp(150);
                $('#mat_qty_' + matId).val('').removeClass('is-invalid');
                $('#mat_note_' + matId).val('');
            }
            updateStructuredMaterialsJson();
        });

        $(document).on('input', '.mr01-mat-qty, .mr01-mat-note', function() {
            if ($(this).hasClass('mr01-mat-qty')) {
                const q = parseFloat($(this).val());
                if (!isNaN(q) && q > 0) {
                    $(this).removeClass('is-invalid');
                }
            }
            updateStructuredMaterialsJson();
        });

        function updateStructuredMaterialsJson() {
            const assetId = $('#mr01_asset_id').val();
            if (!assetId || !isMaterialFlowActive) {
                $('#structured_materials_json').val('');
                $('#material-hidden-field').val('Tidak ada spesifikasi material');
                return;
            }

            const materials = [];
            const textSummaries = [];

            $('.mr01-mat-check:checked').each(function() {
                const matId = $(this).val();
                const matName = $(this).data('name') || '';
                const matUnit = $(this).data('unit') || '';
                const qtyVal = $('#mat_qty_' + matId).val();
                const qty = parseFloat(qtyVal);
                const note = $('#mat_note_' + matId).val();

                if (!isNaN(qty) && qty > 0) {
                    materials.push({
                        material_id: parseInt(matId),
                        quantity: qty,
                        justification_note: note && note.trim() ? note.trim() : null
                    });
                    textSummaries.push(`${matName}: ${qty} ${matUnit}` + (note && note.trim() ? ` (${note.trim()})` : ''));
                }
            });

            if (materials.length > 0) {
                $('#structured_materials_json').val(JSON.stringify({
                    asset_id: parseInt(assetId),
                    materials: materials
                }));
                $('#material-hidden-field').val(textSummaries.join(', '));
            } else {
                $('#structured_materials_json').val('');
                $('#material-hidden-field').val('Tidak ada spesifikasi material');
            }
        }

        // Dedicated ROW Fallback listener
        $('#jenis_temuan').on('change', function() {
            const val = $(this).val();
            if (val === 'ROW') {
                $('#row-vegetasi-container').slideDown(150);
            } else {
                $('#row-vegetasi-container').slideUp(150);
                $('#catatan_row').val('');
            }
        });

        // Initialize state on page load
        setMaterialFlowState(false);
        if ($('#jenis_temuan').val() === 'ROW') {
            $('#row-vegetasi-container').show();
        }

        // --- 2. MULTI-PHOTO UPLOAD PREVIEW & COMPRESSION ---
        function compressSingleImage(file, maxWidth = 1600, quality = 0.8) {
            return new Promise((resolve) => {
                if (!file || !file.type.startsWith('image/') || file.size <= 400 * 1024) {
                    resolve(file);
                    return;
                }
                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = (e) => {
                    const img = new Image();
                    img.src = e.target.result;
                    img.onload = () => {
                        let w = img.width, h = img.height;
                        const maxDim = maxWidth;
                        if (w > maxDim || h > maxDim) {
                            if (w > h) { h = Math.round((h * maxDim) / w); w = maxDim; }
                            else { w = Math.round((w * maxDim) / h); h = maxDim; }
                        }
                        const canvas = document.createElement('canvas');
                        canvas.width = w; canvas.height = h;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, w, h);
                        canvas.toBlob((blob) => {
                            if (blob && blob.size < file.size) {
                                const newFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", {
                                    type: 'image/jpeg',
                                    lastModified: Date.now()
                                });
                                resolve(newFile);
                            } else {
                                resolve(file);
                            }
                        }, 'image/jpeg', quality);
                    };
                    img.onerror = () => resolve(file);
                };
                reader.onerror = () => resolve(file);
            });
        }

        // Store for accumulating files from both Galeri & Kamera
        let createPhotoStore = new DataTransfer();

        $('#btn-pick-gallery').click(function() {
            $('#foto').trigger('click');
        });

        $('#btn-pick-camera').click(function() {
            $('#foto_camera').trigger('click');
        });

        function renderPhotoPreviews() {
            const container = $('#preview-container');
            container.empty();
            const count = createPhotoStore.files.length;

            if (count > 0) {
                $('#file-selection-info').html('<span class="badge bg-success text-white p-2" style="font-size:12px;"><i class="fas fa-check-circle mr-1"></i> ' + count + ' foto dipilih dan siap diunggah</span>');
            } else {
                $('#file-selection-info').html('<i class="fas fa-info-circle mr-1"></i> Format berkas: JPG, JPEG, PNG, WEBP. Bisa memilih dari Galeri atau ambil langsung via Kamera.');
            }

            // Sync store files to hidden input #foto
            const fileInput = document.getElementById('foto');
            if (fileInput) {
                fileInput.files = createPhotoStore.files;
            }

            for (let i = 0; i < count; i++) {
                const file = createPhotoStore.files[i];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const html = `
                        <div class="col-md-3 col-6 mb-3 position-relative animate__animated animate__fadeIn">
                            <div class="img-thumbnail bg-dark p-1" style="border-color: #3d3d3d; border-radius: 8px; overflow: hidden; height: 110px; display: flex; align-items: center; justify-content: center; position: relative;">
                                <img src="${e.target.result}" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                <button type="button" class="btn btn-danger btn-sm btn-remove-item position-absolute" data-index="${i}" style="top: 4px; right: 4px; border-radius: 50%; width: 24px; height: 24px; padding: 0; line-height: 24px; font-size: 11px;" title="Hapus foto ini">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    container.append(html);
                };
                reader.readAsDataURL(file);
            }
        }

        async function handleIncomingFiles(incomingFiles) {
            const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            for (let i = 0; i < incomingFiles.length; i++) {
                const f = incomingFiles[i];
                if (!allowed.includes(f.type)) {
                    Toast.fire({ icon: 'error', title: 'Format berkas "' + f.name + '" tidak diizinkan!' });
                    continue;
                }
                if (createPhotoStore.files.length >= 10) {
                    Toast.fire({ icon: 'warning', title: 'Maksimal upload 10 foto.' });
                    break;
                }
                try {
                    const compressedFile = await compressImageFile(f, 1200, 0.8);
                    createPhotoStore.items.add(compressedFile);
                } catch (e) {
                    createPhotoStore.items.add(f);
                }
            }
            renderPhotoPreviews();
        }

        $('#foto, #foto_camera').change(function() {
            if (this.files && this.files.length > 0) {
                handleIncomingFiles(this.files);
                if (this.id === 'foto_camera') {
                    this.value = '';
                }
            }
        });

        $(document).on('click', '.btn-remove-item', function() {
            const idx = $(this).data('index');
            const newStore = new DataTransfer();
            for (let i = 0; i < createPhotoStore.files.length; i++) {
                if (i !== idx) {
                    newStore.items.add(createPhotoStore.files[i]);
                }
            }
            createPhotoStore = newStore;
            renderPhotoPreviews();
        });

        // Ensure photo selection validation and material JSON sync before submit
        $('#form-create-temuan').submit(function(e) {
            // Sync files from createPhotoStore to input #foto
            const fileInput = document.getElementById('foto');
            if (fileInput && createPhotoStore.files.length > 0) {
                fileInput.files = createPhotoStore.files;
            }

            if (createPhotoStore.files.length === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Foto Belum Dipilih',
                    text: 'Harap unggah minimal 1 foto temuan sebelum menyimpan!',
                    confirmButtonColor: '#005eb8'
                });
                return false;
            }

            // If material flow is active and user checked materials:
            const checkedMaterials = $('.mr01-mat-check:checked');
            if (isMaterialFlowActive && checkedMaterials.length > 0) {
                const assetId = $('#mr01_asset_id').val();
                if (!assetId) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Aset Jaringan Diperlukan',
                        text: 'Pilih Aset Jaringan terlebih dahulu untuk menambahkan material konstruksi.',
                        confirmButtonColor: '#005eb8'
                    });
                    return false;
                }

                // Validate that every checked material has a valid quantity > 0
                let invalidQty = false;
                checkedMaterials.each(function() {
                    const matId = $(this).val();
                    const qtyVal = $('#mat_qty_' + matId).val();
                    const qty = parseFloat(qtyVal);
                    if (isNaN(qty) || qty <= 0) {
                        invalidQty = true;
                        $('#mat_qty_' + matId).addClass('is-invalid');
                    } else {
                        $('#mat_qty_' + matId).removeClass('is-invalid');
                    }
                });

                if (invalidQty) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Jumlah Material Belum Diisi',
                        text: 'Harap masukkan jumlah kebutuhan (angka lebih dari 0) untuk setiap material yang dicentang!',
                        confirmButtonColor: '#005eb8'
                    });
                    return false;
                }
            } else {
                // If material flow is not active or 0 materials checked:
                $('#structured_materials_json').val('');
                $('#material-hidden-field').val('Tidak ada spesifikasi material');
            }

            // Sync structured materials JSON and summary
            updateStructuredMaterialsJson();

            // If ROW notes are provided, append to legacy text field
            if ($('#jenis_temuan').val() === 'ROW') {
                const rowNote = ($('#catatan_row').val() || '').trim();
                if (rowNote) {
                    const currentMat = $('#material-hidden-field').val();
                    if (currentMat && currentMat !== 'Tidak ada spesifikasi material') {
                        $('#material-hidden-field').val(currentMat + ' | Vegetasi ROW: ' + rowNote);
                    } else {
                        $('#material-hidden-field').val('Vegetasi ROW: ' + rowNote);
                    }
                }
            }

            const btnSubmit = $('#btn-submit');
            btnSubmit.html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan data...');
            // Clear draft storage so next fresh visit gets today's date
            try { localStorage.removeItem('sidak_form_draft'); } catch(e) {}
            // Allow native form submission to proceed with multipart payload intact
            return true;
        });

        // --- 3. GEOLOCATION & LEAFLET SELECTOR MAP ---
        if (typeof L !== 'undefined' && L.Icon && L.Icon.Default) {
            L.Icon.Default.imagePath = '<?= base_url('plugins/images/') ?>/';
        }

        const defaultLat = -7.4478;
        const defaultLng = 112.7183;

        // Initialize Selector Map
        const map = L.map('selector-map').setView([defaultLat, defaultLng], 12);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 20
        }).addTo(map);

        setTimeout(function() {
            if (map) {
                map.invalidateSize();
            }
        }, 300);

        const customIcon = L.icon({
            iconUrl: '<?= base_url('assets/img/logo_sidak.png') ?>',
            iconSize: [36, 36],
            iconAnchor: [18, 36],
            popupAnchor: [0, -38]
        });

        // Marker (draggable)
        let marker = L.marker([defaultLat, defaultLng], {
            draggable: true,
            icon: customIcon
        }).addTo(map);
        marker.bindPopup('<b>Geser pin untuk menetapkan lokasi</b>').openPopup();

        function updateCoordinates(lat, lng) {
            $('#latitude').val(lat.toFixed(8));
            $('#longitude').val(lng.toFixed(8));
        }

        // Trigger on marker drag end
        marker.on('dragend', function(e) {
            const position = marker.getLatLng();
            updateCoordinates(position.lat, position.lng);
        });

        // Trigger on map click
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            updateCoordinates(e.latlng.lat, e.latlng.lng);
        });

        // Geolocation trigger
        $('#btn-geolocation').click(function() {
            if (navigator.geolocation) {
                $('#btn-geolocation').html('<i class="fas fa-spinner fa-spin mr-1"></i> Mendapatkan Lokasi...');
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        marker.setLatLng([lat, lng]);
                        map.setView([lat, lng], 16);
                        updateCoordinates(lat, lng);
                        
                        $('#btn-geolocation').html('<i class="fas fa-location-crosshairs mr-1"></i> Ambil Lokasi Saya');
                        Toast.fire({
                            icon: 'success',
                            title: 'Lokasi Anda berhasil didapatkan!'
                        });
                    },
                    function(error) {
                        $('#btn-geolocation').html('<i class="fas fa-location-crosshairs mr-1"></i> Ambil Lokasi Saya');
                        let errMsg = 'Gagal mendapatkan lokasi.';
                        if (error.code === error.PERMISSION_DENIED) {
                            const isHttp = !window.isSecureContext && location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1';
                            errMsg = isHttp
                                ? 'Akses lokasi diblokir peramban pada koneksi HTTP (bukan HTTPS). Harap pasang SSL/HTTPS pada server.'
                                : 'Izin lokasi ditolak oleh pengguna.';
                        }
                        Toast.fire({
                            icon: 'error',
                            title: errMsg
                        });
                    },
                    { enableHighAccuracy: true, timeout: 8000 }
                );
            } else {
                Toast.fire({
                    icon: 'error',
                    title: 'Browser Anda tidak mendukung HTML5 Geolocation.'
                });
            }
        });

        // Manual coordinate input: "Sinkronkan ke Peta" button
        $('#btn-sync-map').click(function() {
            const lat = parseFloat($('#latitude').val());
            const lng = parseFloat($('#longitude').val());
            if (isNaN(lat) || isNaN(lng)) {
                Toast.fire({ icon: 'warning', title: 'Masukkan Latitude dan Longitude yang valid terlebih dahulu.' });
                return;
            }
            if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                Toast.fire({ icon: 'error', title: 'Nilai koordinat di luar rentang yang valid.' });
                return;
            }
            marker.setLatLng([lat, lng]);
            map.setView([lat, lng], 16);
            Toast.fire({ icon: 'success', title: 'Pin peta diperbarui ke koordinat yang dimasukkan.' });
        });

        // Auto-sync when user presses Enter on lat/lng fields
        $('#latitude, #longitude').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#btn-sync-map').trigger('click');
            }
        });


        // Phase 38 Smart AI Recommendation Engine AJAX Listener
        function triggerAiRecommendation() {
            var params = new URLSearchParams({
                'jenis_temuan': $('#jenis_temuan').val() || '',
                'prioritas': $('#prioritas').val() || '',
                'potensi_gangguan': $('#potensi_gangguan').val() || '',
                'pelaksana': $('#pelaksana').val() || '',
                'detail_temuan': $('#detail_temuan').val() || ''
            });

            fetch("<?= site_url('ai/recommendation') ?>?" + params.toString(), {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    var d = res.data;
                    var html = '<div class="mb-2 d-flex justify-content-between align-items-center">';
                    html += '<span class="fw-bold">Estimasi Risiko:</span>';
                    html += '<span class="badge" style="background:' + d.risk_color + '; color:#fff;">' + d.risk_level + '</span></div>';
                    html += '<div class="mb-2"><strong>Waktu SLA:</strong> ' + d.sla_time + '</div>';
                    html += '<div class="mb-2"><strong>Rekomendasi Tim:</strong> <span class="badge bg-primary">' + d.team + '</span></div>';

                    // Checklist
                    html += '<div class="mb-2"><strong class="d-block mb-1">Checklist Pekerjaan:</strong>';
                    d.checklist.forEach(function(item) {
                        html += '<div class="form-check"><input class="form-check-input" type="checkbox" checked><label class="form-check-label small">' + item + '</label></div>';
                    });
                    html += '</div>';

                    // Material
                    html += '<div class="mb-2"><strong>Material:</strong> <span class="text-muted">' + d.materials.join(', ') + '</span></div>';

                    // Impacts & SOP
                    html += '<div class="p-2 bg-light rounded border mb-2"><i class="fas fa-shield-halved text-warning me-1"></i> <strong>SOP PLN:</strong> ' + d.sop + '</div>';
                    html += '<div><strong>Peralatan:</strong> <small class="text-secondary">' + d.tools.join(', ') + '</small></div>';

                    $('#ai-rec-box').html(html);
                }
            });
        }

        $('#jenis_temuan, #prioritas, #potensi_gangguan, #pelaksana').on('change', triggerAiRecommendation);
        $('#detail_temuan').on('blur', triggerAiRecommendation);
        triggerAiRecommendation();


    });
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTemuanCreate);
} else {
    initTemuanCreate();
}
})();
</script>
<?= $this->endSection() ?>
