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
            <form id="form-create-temuan" action="<?= site_url('temuan/store') ?>" method="post" enctype="multipart/form-data">
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

                        <!-- Jenis Temuan -->
                        <div class="col-md-6 form-group mb-3">
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
                    </div>

                    <div class="row">
                        <!-- Pelaksana -->
                        <div class="col-md-4 form-group mb-3">
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
                        <div class="col-md-4 form-group mb-3">
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
                        <div class="col-md-4 form-group mb-3">
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

                    <!-- Material Dibutuhkan (Dynamic Repeater UI) -->
                    <div class="form-group mb-4 p-3 rounded" style="background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 14px;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="font-weight-bold text-dark mb-0" style="font-size: 14px;">
                                <i class="fas fa-screwdriver-wrench text-primary me-1"></i> Material Dibutuhkan / Pohon <small class="text-muted">(Opsional)</small>
                            </label>
                        </div>
                        
                        <div id="material-repeater-container" class="mb-2">
                            <!-- Items added dynamically -->
                        </div>

                        <!-- Pill Add Button matching user screenshot -->
                        <div class="text-center mt-2">
                            <button type="button" id="btn-add-material" class="btn btn-outline-primary w-100 py-2 rounded-pill font-weight-bold" style="border-width: 2px; font-size: 14px; box-shadow: 0 2px 6px rgba(0, 94, 184, 0.08);">
                                <i class="fas fa-plus-circle me-1"></i> Tambah Material
                            </button>
                        </div>
                        <input type="hidden" name="material" id="material-hidden-field" value="">
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
                            <input type="date" name="tanggal_temuan" id="tanggal_temuan" class="form-control <?= ($validation && $validation->hasError('tanggal_temuan')) ? 'is-invalid' : '' ?>" value="<?= old('tanggal_temuan', date('Y-m-d')) ?>" required>
                            <?php if ($validation && $validation->hasError('tanggal_temuan')): ?>
                                <div class="invalid-feedback"><?= $validation->getError('tanggal_temuan') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Upload Foto (Pilihan Galeri / Berkas & Kamera Direct) -->
                    <div class="form-group mb-3">
                        <label class="font-weight-bold">Unggah Foto Temuan (Minimal 1, Maksimal 10) <span class="text-danger">*</span></label>
                        
                        <div class="p-3 border rounded bg-light shadow-sm">
                            <div class="row g-2 mb-2">
                                <div class="col-sm-6 col-12 mb-2 mb-sm-0">
                                    <button type="button" class="btn btn-outline-primary btn-block py-2 font-weight-bold shadow-sm" id="btn-pick-gallery">
                                        <i class="fas fa-folder-open text-primary mr-1"></i> 📁 Pilih dari Galeri / Berkas
                                    </button>
                                </div>
                                <div class="col-sm-6 col-12">
                                    <button type="button" class="btn btn-outline-success btn-block py-2 font-weight-bold shadow-sm" id="btn-pick-camera">
                                        <i class="fas fa-camera text-success mr-1"></i> 📷 Ambil Foto via Kamera
                                    </button>
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
                    <a href="<?= site_url('temuan') ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Peta Selector Koordinat -->
    <div class="col-lg-4 col-12">
        <div class="card" style="position: sticky; top: 1rem;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-map-pin text-danger mr-1"></i> Pemilih Koordinat Peta</h3>
            </div>
            <div class="card-body p-0">
                <div id="selector-map" style="height: 480px; width: 100%; border-radius: 0 0 12px 12px;"></div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function() {
        // --- 1. CASCADING DROPDOWNS ---
        
        const oldPenyulangId = "<?= old('penyulang_id') ?>";
        const oldSectionId = "<?= old('section_id') ?>";

        function loadPenyulang(ulpId, callback) {
            if (!ulpId) {
                $('#penyulang_id').html('<option value="">-- Pilih ULP Dahulu --</option>');
                $('#section_id').html('<option value="">-- Pilih Penyulang Dahulu --</option>');
                return;
            }

            $('#penyulang_id').html('<option value="">Sedang memuat...</option>');
            $.ajax({
                url: "<?= site_url('temuan/ajax-penyulang/') ?>" + ulpId,
                type: "GET",
                dataType: "json",
                success: function(data) {
                    let html = '<option value="">-- Pilih Penyulang --</option>';
                    data.forEach(function(item) {
                        html += `<option value="${item.id}">${item.nama_penyulang}</option>`;
                    });
                    $('#penyulang_id').html(html);
                    if (callback) callback();
                }
            });
        }

        function loadSection(penyulangId, callback) {
            if (!penyulangId) {
                $('#section_id').html('<option value="">-- Pilih Penyulang Dahulu --</option>');
                return;
            }

            $('#section_id').html('<option value="">Sedang memuat...</option>');
            $.ajax({
                url: "<?= site_url('temuan/ajax-section/') ?>" + penyulangId,
                type: "GET",
                dataType: "json",
                success: function(data) {
                    let html = '<option value="">-- Pilih Section --</option>';
                    data.forEach(function(item) {
                        html += `<option value="${item.id}">${item.nama_section}</option>`;
                    });
                    $('#section_id').html(html);
                    if (callback) callback();
                }
            });
        }

        // Dropdown triggers
        $('#ulp_id').change(function() {
            loadPenyulang($(this).val());
        });

        $('#penyulang_id').change(function() {
            loadSection($(this).val());
        });

        // Restore old input cascade (if validation fails)
        if ($('#ulp_id').val()) {
            loadPenyulang($('#ulp_id').val(), function() {
                if (oldPenyulangId) {
                    $('#penyulang_id').val(oldPenyulangId);
                    loadSection(oldPenyulangId, function() {
                        if (oldSectionId) {
                            $('#section_id').val(oldSectionId);
                        }
                    });
                }
            });
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

        function handleIncomingFiles(incomingFiles) {
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
                createPhotoStore.items.add(f);
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

        // Intercept form submit to compress all images & validate photo selection
        $('#form-create-temuan').submit(async function(e) {
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

            const btnSubmit = $('#btn-submit');
            if (btnSubmit.data('compressed')) {
                return true; // Already processed compression, proceed with native submit
            }

            e.preventDefault();
            btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Mengompres foto & menyimpan...');

            try {
                const dt = new DataTransfer();
                for (let i = 0; i < createPhotoStore.files.length; i++) {
                    const compressed = await compressSingleImage(createPhotoStore.files[i]);
                    dt.items.add(compressed);
                }
                const fileInput = document.getElementById('foto');
                if (fileInput) {
                    fileInput.files = dt.files;
                }

                btnSubmit.data('compressed', true);
                this.submit();
            } catch(err) {
                console.error('Photo compression error:', err);
                btnSubmit.data('compressed', true);
                this.submit();
            }
        });

        // --- 3. GEOLOCATION & LEAFLET SELECTOR MAP ---
        const defaultLat = -7.4478;
        const defaultLng = 112.7183;

        // Initialize Selector Map
        const map = L.map('selector-map').setView([defaultLat, defaultLng], 12);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 20
        }).addTo(map);

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
        // ============================================================
        // MATERIAL REPEATER JS (Custom Add & Remove Row UI)
        // ============================================================
        function addMaterialRow(nama = '', jumlah = '') {
            const rowHtml = `
                <div class="material-item-row card mb-2 p-2 shadow-sm border-0 animate__animated animate__fadeIn" style="background: #ffffff; border-radius: 12px; border-left: 4px solid #005eb8 !important;">
                    <div class="row g-2 align-items-center">
                        <div class="col-6 col-md-7">
                            <label class="small text-muted font-weight-bold mb-1">Nama Material / Pohon</label>
                            <input type="text" class="form-control form-control-sm input-nama-material" value="${nama}" placeholder="Contoh: Isolator Tumpu / Pohon Mangga">
                        </div>
                        <div class="col-4 col-md-4">
                            <label class="small text-muted font-weight-bold mb-1">Jumlah</label>
                            <input type="text" class="form-control form-control-sm input-jumlah-material" value="${jumlah}" placeholder="Contoh: 2 buah / 5 m">
                        </div>
                        <div class="col-2 col-md-1 text-end">
                            <label class="small d-block mb-1">&nbsp;</label>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-material border-0" title="Hapus"><i class="fas fa-trash-can"></i></button>
                        </div>
                    </div>
                </div>
            `;
            $('#material-repeater-container').append(rowHtml);
        }

        $('#btn-add-material').click(function() {
            addMaterialRow();
        });

        $(document).on('click', '.btn-remove-material', function() {
            $(this).closest('.material-item-row').remove();
        });

        $('form').on('submit', function() {
            let materialItems = [];
            $('.material-item-row').each(function() {
                const nama = $(this).find('.input-nama-material').val().trim();
                const qty = $(this).find('.input-jumlah-material').val().trim();
                if (nama !== '') {
                    materialItems.push(qty ? `- ${qty} ${nama}` : `- ${nama}`);
                }
            });

            if (materialItems.length > 0) {
                $('#material-hidden-field').val(materialItems.join("\n"));
            } else {
                $('#material-hidden-field').val('Tidak ada spesifikasi material');
            }
        });

    });
</script>
<?= $this->endSection() ?>
