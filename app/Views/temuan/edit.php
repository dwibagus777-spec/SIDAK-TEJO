<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Edit Temuan - <?= esc($temuan['nomor_temuan']) ?><?= $this->endSection() ?>
<?= $this->section('page_title') ?>Edit Data Temuan<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('temuan') ?>">Data Temuan</a></li>
<li class="breadcrumb-item"><a href="<?= site_url('temuan/detail/' . $temuan['id']) ?>"><?= esc($temuan['nomor_temuan']) ?></a></li>
<li class="breadcrumb-item active">Edit</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-8 col-12">
        <div class="card card-outline card-warning">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit mr-1 text-warning"></i> Edit Temuan: <span class="font-weight-bold font-monospace"><?= esc($temuan['nomor_temuan']) ?></span></h3>
            </div>
            <form action="<?= site_url('temuan/update/' . $temuan['id']) ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="card-body">

                    <?php $validation = isset($validation) ? $validation : null; ?>

                    <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-circle-exclamation mr-1"></i> <?= session()->getFlashdata('error') ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- ULP Selection -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="ulp_id">Unit Layanan Pelanggan (ULP) <span class="text-danger">*</span></label>
                            <select name="ulp_id" id="ulp_id" class="form-control select2 <?= ($validation && $validation->hasError('ulp_id')) ? 'is-invalid' : '' ?>" required>
                                <option value="">-- Pilih ULP --</option>
                                <?php foreach ($ulps as $ulp): ?>
                                    <option value="<?= $ulp['id'] ?>" <?= (old('ulp_id', $temuan['ulp_id']) == $ulp['id']) ? 'selected' : '' ?>><?= esc($ulp['nama_ulp']) ?></option>
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
                                <?php foreach ($penyulangs as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= (old('penyulang_id', $temuan['penyulang_id']) == $p['id']) ? 'selected' : '' ?>><?= esc($p['nama_penyulang']) ?></option>
                                <?php endforeach; ?>
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
                                <?php foreach ($sections as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= (old('section_id', $temuan['section_id']) == $s['id']) ? 'selected' : '' ?>><?= esc($s['nama_section']) ?></option>
                                <?php endforeach; ?>
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
                                <?php $jt = old('jenis_temuan', $temuan['jenis_temuan']); ?>
                                <option value="KONSTRUKSI" <?= $jt === 'KONSTRUKSI' ? 'selected' : '' ?>>KONSTRUKSI</option>
                                <option value="HOTSPOT"    <?= $jt === 'HOTSPOT'    ? 'selected' : '' ?>>HOTSPOT</option>
                                <option value="ROW"        <?= $jt === 'ROW'        ? 'selected' : '' ?>>ROW (Right of Way)</option>
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
                                <?php $pl = old('pelaksana', $temuan['pelaksana']); ?>
                                <option value="PDKB"          <?= $pl === 'PDKB'          ? 'selected' : '' ?>>PDKB</option>
                                <option value="HAR GARDU"      <?= $pl === 'HAR GARDU'      ? 'selected' : '' ?>>HAR GARDU</option>
                                <option value="HAR GTT"        <?= $pl === 'HAR GTT'        ? 'selected' : '' ?>>HAR GTT</option>
                                <option value="HAR KONSTRUKSI" <?= $pl === 'HAR KONSTRUKSI' ? 'selected' : '' ?>>HAR KONSTRUKSI</option>
                                <option value="HAR ROW"        <?= $pl === 'HAR ROW'        ? 'selected' : '' ?>>HAR ROW</option>
                                <option value="HAR CRANE"      <?= $pl === 'HAR CRANE'      ? 'selected' : '' ?>>HAR CRANE</option>
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
                                <?php $pr = old('prioritas', $temuan['prioritas']); ?>
                                <option value="EMERGENCY" <?= $pr === 'EMERGENCY' ? 'selected' : '' ?>>EMERGENCY (1x24 Jam)</option>
                                <option value="HIGH"      <?= $pr === 'HIGH'      ? 'selected' : '' ?>>HIGH (3 Hari)</option>
                                <option value="MEDIUM"    <?= $pr === 'MEDIUM'    ? 'selected' : '' ?>>MEDIUM (7 Hari)</option>
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
                                <?php $pg = old('potensi_gangguan', $temuan['potensi_gangguan']); ?>
                                <option value="DGR"    <?= $pg === 'DGR'    ? 'selected' : '' ?>>DGR (Directional Ground Relays)</option>
                                <option value="OCR"    <?= $pg === 'OCR'    ? 'selected' : '' ?>>OCR (Over Current Relays)</option>
                                <option value="OCRDGR" <?= $pg === 'OCRDGR' ? 'selected' : '' ?>>OCRDGR</option>
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
                            <input type="text" name="konduktor" id="konduktor" class="form-control <?= ($validation && $validation->hasError('konduktor')) ? 'is-invalid' : '' ?>"
                                   placeholder="Contoh: A3CS 150mm"
                                   value="<?= old('konduktor', esc($temuan['konduktor'])) ?>" required>
                            <?php if ($validation && $validation->hasError('konduktor')): ?>
                                <div class="invalid-feedback"><?= $validation->getError('konduktor') ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- NOGA -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="noga">Nomor Gardu (NOGA)</label>
                            <input type="text" name="noga" id="noga" class="form-control <?= ($validation && $validation->hasError('noga')) ? 'is-invalid' : '' ?>"
                                   placeholder="Contoh: G.123 (Boleh kosong)"
                                   value="<?= old('noga', esc($temuan['noga'])) ?>">
                            <?php if ($validation && $validation->hasError('noga')): ?>
                                <div class="invalid-feedback"><?= $validation->getError('noga') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Material -->
                    <div class="form-group mb-3">
                        <label for="material">Material <span class="text-danger">*</span></label>
                        <textarea name="material" id="material" class="form-control <?= ($validation && $validation->hasError('material')) ? 'is-invalid' : '' ?>"
                                  rows="2" placeholder="Sebutkan material yang dibutuhkan" required><?= old('material', esc($temuan['material'])) ?></textarea>
                        <?php if ($validation && $validation->hasError('material')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('material') ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Detail Temuan -->
                    <div class="form-group mb-3">
                        <label for="detail_temuan">Detail Temuan Inspeksi <span class="text-danger">*</span></label>
                        <textarea name="detail_temuan" id="detail_temuan" class="form-control <?= ($validation && $validation->hasError('detail_temuan')) ? 'is-invalid' : '' ?>"
                                  rows="3" placeholder="Jelaskan detail kerusakan/temuan di lapangan..." required><?= old('detail_temuan', esc($temuan['detail_temuan'])) ?></textarea>
                        <?php if ($validation && $validation->hasError('detail_temuan')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('detail_temuan') ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Alamat -->
                    <div class="form-group mb-3">
                        <label for="alamat">Alamat Lokasi Temuan <span class="text-danger">*</span></label>
                        <textarea name="alamat" id="alamat" class="form-control <?= ($validation && $validation->hasError('alamat')) ? 'is-invalid' : '' ?>"
                                  rows="2" placeholder="Contoh: Jl. Ahmad Yani No. 12" required><?= old('alamat', esc($temuan['alamat'])) ?></textarea>
                        <?php if ($validation && $validation->hasError('alamat')): ?>
                            <div class="invalid-feedback"><?= $validation->getError('alamat') ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Geolocation / Coordinates -->
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="latitude">Latitude</label>
                            <input type="text" name="latitude" id="latitude" class="form-control"
                                   placeholder="Contoh: -7.447812"
                                   value="<?= old('latitude', $temuan['latitude']) ?>" readonly>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="longitude">Longitude</label>
                            <input type="text" name="longitude" id="longitude" class="form-control"
                                   placeholder="Contoh: 112.718324"
                                   value="<?= old('longitude', $temuan['longitude']) ?>" readonly>
                        </div>
                    </div>

                    <div class="mb-3 d-flex gap-2">
                        <button type="button" class="btn btn-info text-white btn-sm" id="btn-geolocation">
                            <i class="fas fa-location-crosshairs mr-1"></i> Ambil Lokasi Saya
                        </button>
                        <span class="text-muted small align-self-center ml-2">*Atau klik/geser pin pada peta di sebelah kanan.</span>
                    </div>

                    <div class="row">
                        <!-- Tanggal Temuan -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="tanggal_temuan">Tanggal Temuan <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_temuan" id="tanggal_temuan" class="form-control <?= ($validation && $validation->hasError('tanggal_temuan')) ? 'is-invalid' : '' ?>"
                                   value="<?= old('tanggal_temuan', $temuan['tanggal_temuan']) ?>" required>
                            <?php if ($validation && $validation->hasError('tanggal_temuan')): ?>
                                <div class="invalid-feedback"><?= $validation->getError('tanggal_temuan') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Foto yang sudah ada -->
                    <?php
                    $existingPhotos = json_decode($temuan['foto'], true) ?: [];
                    if (!empty($existingPhotos)):
                    ?>
                    <div class="form-group mb-3">
                        <label><i class="fas fa-images mr-1 text-info"></i> Foto Temuan Saat Ini (<?= count($existingPhotos) ?> foto)</label>
                        <div class="row px-2 mt-1">
                            <?php foreach ($existingPhotos as $photo):
                                $filePath = base_url($temuan['foto_path'] . $photo);
                            ?>
                            <div class="col-md-2 col-4 mb-2 px-1">
                                <div class="img-thumbnail bg-dark text-center" style="border-color: #444; border-radius: 8px; overflow: hidden; height: 80px; display: flex; align-items: center; justify-content: center;">
                                    <img src="<?= $filePath ?>" style="max-height: 100%; max-width: 100%; object-fit: cover;">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">* Foto lama akan tetap ada. Upload foto baru di bawah untuk menambahkan.</small>
                    </div>
                    <?php endif; ?>

                    <!-- Upload Foto Tambahan (Pilihan Galeri / Berkas & Kamera Direct) -->
                    <div class="form-group mb-3">
                        <label class="font-weight-bold"><i class="fas fa-camera text-primary mr-1"></i> Tambah Foto Baru (Opsional, Maks 10)</label>
                        
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
                                <i class="fas fa-info-circle mr-1"></i> Foto baru akan ditambahkan ke foto yang sudah ada. Format: JPG, JPEG, PNG, WEBP.
                            </div>
                        </div>
                    </div>

                    <!-- Pratinjau Foto Upload Baru -->
                    <div class="row mt-2 px-2" id="preview-container"></div>

                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <div>
                        <button type="submit" class="btn btn-warning text-dark" id="btn-submit">
                            <i class="fas fa-save mr-1"></i> Simpan Perubahan
                        </button>
                        <a href="<?= site_url('temuan/detail/' . $temuan['id']) ?>" class="btn btn-secondary ml-2">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali
                        </a>
                    </div>
                    <span class="text-muted small"><i class="fas fa-hashtag mr-1"></i><?= esc($temuan['nomor_temuan']) ?></span>
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
        // --- 1. CASCADING DROPDOWNS (pre-filled) ---
        const currentPenyulangId = "<?= $temuan['penyulang_id'] ?>";
        const currentSectionId   = "<?= $temuan['section_id'] ?>";

        function loadPenyulang(ulpId, selectedId) {
            if (!ulpId) {
                $('#penyulang_id').html('<option value="">-- Pilih ULP Dahulu --</option>');
                $('#section_id').html('<option value="">-- Pilih Penyulang Dahulu --</option>');
                return;
            }
            $('#penyulang_id').html('<option value="">Sedang memuat...</option>');
            $.ajax({
                url: "<?= site_url('temuan/ajax-penyulang/') ?>" + ulpId,
                type: "GET", dataType: "json",
                success: function(data) {
                    let html = '<option value="">-- Pilih Penyulang --</option>';
                    data.forEach(function(item) {
                        const sel = item.id == selectedId ? 'selected' : '';
                        html += `<option value="${item.id}" ${sel}>${item.nama_penyulang}</option>`;
                    });
                    $('#penyulang_id').html(html);
                }
            });
        }

        function loadSection(penyulangId, selectedId) {
            if (!penyulangId) {
                $('#section_id').html('<option value="">-- Pilih Penyulang Dahulu --</option>');
                return;
            }
            $('#section_id').html('<option value="">Sedang memuat...</option>');
            $.ajax({
                url: "<?= site_url('temuan/ajax-section/') ?>" + penyulangId,
                type: "GET", dataType: "json",
                success: function(data) {
                    let html = '<option value="">-- Pilih Section --</option>';
                    data.forEach(function(item) {
                        const sel = item.id == selectedId ? 'selected' : '';
                        html += `<option value="${item.id}" ${sel}>${item.nama_section}</option>`;
                    });
                    $('#section_id').html(html);
                }
            });
        }

        // Dropdown triggers
        $('#ulp_id').change(function() {
            loadPenyulang($(this).val(), '');
        });
        $('#penyulang_id').change(function() {
            loadSection($(this).val(), '');
        });

        // Re-init select2 after AJAX load
        // (Dropdowns are already pre-populated from PHP, but keep cascading for changes)

        // --- 2. MULTI-PHOTO UPLOAD PREVIEW (GALERI & KAMERA DIRECT) ---
        let editPhotoStore = new DataTransfer();

        $('#btn-pick-gallery').click(function() {
            $('#foto').trigger('click');
        });

        $('#btn-pick-camera').click(function() {
            $('#foto_camera').trigger('click');
        });

        function renderEditPhotoPreviews() {
            const container = $('#preview-container');
            container.empty();
            const count = editPhotoStore.files.length;

            if (count > 0) {
                $('#file-selection-info').html('<span class="badge bg-success text-white p-2" style="font-size:12px;"><i class="fas fa-check-circle mr-1"></i> ' + count + ' foto baru dipilih dan siap ditambahkan</span>');
            } else {
                $('#file-selection-info').html('<i class="fas fa-info-circle mr-1"></i> Foto baru akan ditambahkan ke foto yang sudah ada. Format: JPG, JPEG, PNG, WEBP.');
            }

            const fileInput = document.getElementById('foto');
            if (fileInput) {
                fileInput.files = editPhotoStore.files;
            }

            for (let i = 0; i < count; i++) {
                const file = editPhotoStore.files[i];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const html = `
                        <div class="col-md-3 col-6 mb-3 position-relative animate__animated animate__fadeIn">
                            <div class="img-thumbnail bg-dark p-1" style="border-color: #3d3d3d; border-radius: 8px; overflow: hidden; height: 110px; display: flex; align-items: center; justify-content: center; position: relative;">
                                <img src="${e.target.result}" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                <button type="button" class="btn btn-danger btn-sm btn-remove-edit-item position-absolute" data-index="${i}" style="top: 4px; right: 4px; border-radius: 50%; width: 24px; height: 24px; padding: 0; line-height: 24px; font-size: 11px;" title="Hapus foto ini">
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

        function handleEditIncomingFiles(incomingFiles) {
            const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            for (let i = 0; i < incomingFiles.length; i++) {
                const f = incomingFiles[i];
                if (!allowed.includes(f.type)) {
                    Toast.fire({ icon: 'error', title: 'Format berkas "' + f.name + '" tidak diizinkan!' });
                    continue;
                }
                if (editPhotoStore.files.length >= 10) {
                    Toast.fire({ icon: 'warning', title: 'Maksimal upload 10 foto tambahan.' });
                    break;
                }
                editPhotoStore.items.add(f);
            }
            renderEditPhotoPreviews();
        }

        $('#foto, #foto_camera').change(function() {
            if (this.files && this.files.length > 0) {
                handleEditIncomingFiles(this.files);
                this.value = '';
            }
        });

        $(document).on('click', '.btn-remove-edit-item', function() {
            const idx = $(this).data('index');
            const newStore = new DataTransfer();
            for (let i = 0; i < editPhotoStore.files.length; i++) {
                if (i !== idx) {
                    newStore.items.add(editPhotoStore.files[i]);
                }
            }
            editPhotoStore = newStore;
            renderEditPhotoPreviews();
        });

        // --- 3. GEOLOCATION & LEAFLET SELECTOR MAP ---
        const initLat = <?= $temuan['latitude'] !== null ? $temuan['latitude'] : '-7.4478' ?>;
        const initLng = <?= $temuan['longitude'] !== null ? $temuan['longitude'] : '112.7183' ?>;

        const map = L.map('selector-map').setView([initLat, initLng], 15);
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

        let marker = L.marker([initLat, initLng], { draggable: true, icon: customIcon }).addTo(map);
        marker.bindPopup('<b>Geser pin untuk memperbarui lokasi</b>').openPopup();

        function updateCoordinates(lat, lng) {
            $('#latitude').val(lat.toFixed(8));
            $('#longitude').val(lng.toFixed(8));
        }

        marker.on('dragend', function() {
            const pos = marker.getLatLng();
            updateCoordinates(pos.lat, pos.lng);
        });

        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            updateCoordinates(e.latlng.lat, e.latlng.lng);
        });

        $('#btn-geolocation').click(function() {
            if (navigator.geolocation) {
                $(this).html('<i class="fas fa-spinner fa-spin mr-1"></i> Mendapatkan Lokasi...');
                const btn = this;
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        marker.setLatLng([lat, lng]);
                        map.setView([lat, lng], 16);
                        updateCoordinates(lat, lng);
                        $(btn).html('<i class="fas fa-location-crosshairs mr-1"></i> Ambil Lokasi Saya');
                        Toast.fire({ icon: 'success', title: 'Lokasi berhasil didapatkan!' });
                    },
                    function(error) {
                        $(btn).html('<i class="fas fa-location-crosshairs mr-1"></i> Ambil Lokasi Saya');
                        const isHttp = !window.isSecureContext && location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1';
                        const errMsg = isHttp 
                            ? 'Akses lokasi diblokir peramban pada koneksi HTTP (bukan HTTPS). Harap pasang SSL/HTTPS pada server.'
                            : 'Gagal mendapatkan lokasi.';
                        Toast.fire({ icon: 'error', title: errMsg });
                    },
                    { enableHighAccuracy: true, timeout: 8000 }
                );
            } else {
                Toast.fire({ icon: 'error', title: 'Browser tidak mendukung Geolocation.' });
            }
        });

    });
</script>
<?= $this->endSection() ?>
