<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Buat Inspeksi Gardu Baru<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Buat Inspeksi Gardu Baru<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="bi bi-clipboard-plus me-2"></i>Buat Inspeksi Gardu Baru
        </h1>
        <a href="<?= site_url('kubikel-inspeksi') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>

    <?php if (session()->getFlashdata('errors')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                <?php foreach (session()->getFlashdata('errors') as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= esc(session()->getFlashdata('error')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Informasi Inspeksi</h5>
        </div>
        <div class="card-body">
            <form action="<?= site_url('kubikel-inspeksi/store') ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="ulp_id" class="form-label fw-medium">ULP <span class="text-danger">*</span></label>
                        <select name="ulp_id" id="ulp_id" class="form-select" required>
                            <option value="">Pilih ULP</option>
                            <?php foreach ($ulps as $ulp): ?>
                                <option value="<?= $ulp['id'] ?>" <?= ($defaultUlpId == $ulp['id']) ? 'selected' : '' ?>>
                                    <?= esc($ulp['nama_ulp'] ?? $ulp['nama'] ?? 'ULP ' . $ulp['id']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="penyulang_id" class="form-label fw-medium">Penyulang <span class="text-danger">*</span></label>
                        <select name="penyulang_id" id="penyulang_id" class="form-select" required>
                            <option value="">Pilih Penyulang</option>
                            <?php foreach ($penyulangs as $ps): ?>
                                <option value="<?= $ps['id'] ?>" data-ulp="<?= $ps['ulp_id'] ?? 0 ?>">
                                    <?= esc($ps['nama_penyulang'] ?? $ps['nama'] ?? 'Penyulang ' . $ps['id']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="section_id" class="form-label fw-medium">Section</label>
                        <select name="section_id" id="section_id" class="form-select">
                            <option value="">Pilih Section (Opsional)</option>
                            <?php foreach ($sections as $sec): ?>
                                <option value="<?= $sec['id'] ?>" data-penyulang="<?= $sec['penyulang_id'] ?? 0 ?>">
                                    <?= esc($sec['nama_section'] ?? $sec['nama'] ?? 'Section ' . $sec['id']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="gardu_induk_id" class="form-label fw-medium">Gardu Induk</label>
                        <select name="gardu_induk_id" id="gardu_induk_id" class="form-select">
                            <option value="">Pilih Gardu Induk (Opsional)</option>
                            <?php foreach ($garduInduk as $gi): ?>
                                <option value="<?= $gi['id'] ?>">
                                    <?= esc($gi['nama_gi'] ?? $gi['nama'] ?? 'GI ' . $gi['id']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="asset_id" class="form-label fw-medium">Aset (Opsional)</label>
                        <input type="number" name="asset_id" id="asset_id" class="form-control" placeholder="ID Aset" min="1">
                        <div class="form-text">Masukkan ID aset jika inspeksi terkait aset tertentu</div>
                    </div>

                    <div class="col-md-4">
                        <label for="id_pel" class="form-label fw-medium">ID Pelanggan (Opsional)</label>
                        <input type="text" name="id_pel" id="id_pel" class="form-control" placeholder="ID Pelanggan / IDPEL" maxlength="100" value="<?= esc(old('id_pel', '')) ?>">
                        <div class="form-text">Untuk inspeksi aset pelanggan / Beyond KWH</div>
                    </div>

                    <div class="col-md-4">
                        <label for="nama_pelanggan" class="form-label fw-medium">Nama Pelanggan (Opsional)</label>
                        <input type="text" name="nama_pelanggan" id="nama_pelanggan" class="form-control" placeholder="Nama Pelanggan" maxlength="255" value="<?= esc(old('nama_pelanggan', '')) ?>">
                        <div class="form-text">Nama pemilik gardu / instalasi pelanggan</div>
                    </div>

                    <!-- CR-GIS-FIX-07: Kepemilikan & Jenis Gardu -->
                    <div class="col-md-4">
                        <label for="kepemilikan_gardu" class="form-label fw-medium">Kepemilikan Gardu <span class="text-danger">*</span></label>
                        <select name="kepemilikan_gardu" id="kepemilikan_gardu" class="form-select" required>
                            <option value="PLN" <?= old('kepemilikan_gardu') === 'PLN' ? 'selected' : '' ?>>PLN</option>
                            <option value="PELANGGAN" <?= old('kepemilikan_gardu') === 'PELANGGAN' ? 'selected' : '' ?>>PELANGGAN (Kubikel Pelanggan)</option>
                        </select>
                        <div class="form-text">PLN = gardu sendiri; PELANGGAN = kubikel pelanggan</div>
                    </div>

                    <div class="col-md-4" id="field-jenis-gardu">
                        <label for="jenis_gardu" class="form-label fw-medium">Jenis Gardu <span class="text-danger">*</span></label>
                        <select name="jenis_gardu" id="jenis_gardu" class="form-select" required>
                            <option value="KUBIKEL" <?= old('jenis_gardu') === 'KUBIKEL' ? 'selected' : '' ?>>KUBIKEL</option>
                            <option value="GARDU_KONVENSIONAL" <?= old('jenis_gardu') === 'GARDU_KONVENSIONAL' ? 'selected' : '' ?>>GARDU KONVENSIONAL</option>
                        </select>
                        <div class="form-text">Kubikel = Metal Clad; Konvensional = terbuka/pasangan batu</div>
                    </div>
                    <!-- END CR-GIS-FIX-07 -->

                    <div class="col-md-4">
                        <label for="tgl_inspeksi" class="form-label fw-medium">Tanggal Inspeksi <span class="text-danger">*</span></label>
                        <input type="date" name="tgl_inspeksi" id="tgl_inspeksi" class="form-control" required value="<?= esc(old('tgl_inspeksi', date('Y-m-d'))) ?>">
                    </div>

                    <div class="col-12">
                        <label for="catatan_umum" class="form-label fw-medium">Detail Temuan</label>
                        <textarea name="catatan_umum" id="catatan_umum" class="form-control" rows="3" placeholder="Detail temuan untuk inspeksi gardu ini..."><?= esc(old('catatan_umum', '')) ?></textarea>
                    </div>

                    <!-- Bagian Foto Inspeksi -->
                    <div class="col-12 mt-3">
                        <label class="form-label fw-medium d-flex justify-content-between align-items-center mb-1">
                            <span><i class="bi bi-camera me-1 text-primary"></i>Foto Inspeksi</span>
                            <span class="badge bg-light text-secondary border">Maks. 10 MB per foto (JPEG, PNG, WebP)</span>
                        </label>
                        <div class="border rounded p-3 bg-light text-center position-relative" id="foto-drop-zone">
                            <input type="file" name="fotos[]" id="foto_inspeksi" class="form-control d-none" multiple accept="image/jpeg,image/png,image/webp">
                            <div class="py-3" id="foto-upload-prompt" style="cursor: pointer;" onclick="document.getElementById('foto_inspeksi').click();">
                                <i class="bi bi-cloud-arrow-up text-primary fs-1 mb-2 d-block"></i>
                                <div class="fw-semibold text-dark">Klik untuk memilih foto dari galeri / perangkat</div>
                                <div class="small text-muted">Dapat memilih beberapa foto kondisi gardu / temuan sekaligus</div>
                            </div>
                            <div id="foto-preview-grid" class="row g-2 mt-2 text-start d-none"></div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">
                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= site_url('kubikel-inspeksi') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i>Batal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Buat Inspeksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ulpSelect = document.getElementById('ulp_id');
        const penyulangSelect = document.getElementById('penyulang_id');
        const sectionSelect = document.getElementById('section_id');

        function filterPenyulang() {
            const ulpId = ulpSelect.value;
            Array.from(penyulangSelect.options).forEach(opt => {
                if (opt.value === '') return;
                const optUlp = opt.dataset.ulp;
                opt.style.display = (!ulpId || optUlp == ulpId) ? '' : 'none';
            });
            penyulangSelect.value = '';
        }

        function filterSection() {
            const penyulangId = penyulangSelect.value;
            Array.from(sectionSelect.options).forEach(opt => {
                if (opt.value === '') return;
                const optPs = opt.dataset.penyulang;
                opt.style.display = (!penyulangId || optPs == penyulangId) ? '' : 'none';
            });
            sectionSelect.value = '';
        }

        ulpSelect.addEventListener('change', filterPenyulang);
        penyulangSelect.addEventListener('change', filterSection);

        // Initial filter
        filterPenyulang();

        // Photo preview & removal management
        const fotoInput = document.getElementById('foto_inspeksi');
        const previewGrid = document.getElementById('foto-preview-grid');
        let selectedFiles = new DataTransfer();

        fotoInput.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            const maxSize = 10 * 1024 * 1024; // 10MB

            files.forEach(file => {
                if (!allowedTypes.includes(file.type)) {
                    alert('File "' + file.name + '" bukan format gambar yang didukung (JPEG, PNG, WebP).');
                    return;
                }
                if (file.size > maxSize) {
                    alert('Ukuran file "' + file.name + '" melebihi 10MB.');
                    return;
                }
                // Hindari duplikasi file dengan nama dan ukuran yang sama
                let isDuplicate = false;
                for (let i = 0; i < selectedFiles.files.length; i++) {
                    if (selectedFiles.files[i].name === file.name && selectedFiles.files[i].size === file.size) {
                        isDuplicate = true;
                        break;
                    }
                }
                if (!isDuplicate) {
                    selectedFiles.items.add(file);
                }
            });

            fotoInput.files = selectedFiles.files;
            renderPreviews();
        });

        function renderPreviews() {
            previewGrid.innerHTML = '';
            if (selectedFiles.files.length === 0) {
                previewGrid.classList.add('d-none');
                return;
            }
            previewGrid.classList.remove('d-none');

            Array.from(selectedFiles.files).forEach((file, index) => {
                const col = document.createElement('div');
                col.className = 'col-6 col-sm-4 col-md-3 col-xl-2';

                const card = document.createElement('div');
                card.className = 'card h-100 shadow-sm border position-relative overflow-hidden';

                const img = document.createElement('img');
                img.className = 'card-img-top';
                img.style.height = '110px';
                img.style.objectFit = 'cover';
                img.src = URL.createObjectURL(file);

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-danger btn-sm position-absolute top-0 end-0 m-1 p-0 rounded-circle d-flex align-items-center justify-content-center';
                removeBtn.style.width = '24px';
                removeBtn.style.height = '24px';
                removeBtn.title = 'Hapus foto ini';
                removeBtn.innerHTML = '<i class="bi bi-x fs-6"></i>';
                removeBtn.onclick = function(ev) {
                    ev.stopPropagation();
                    removeFile(index);
                };

                const body = document.createElement('div');
                body.className = 'p-1 text-center bg-white';
                const nameSpan = document.createElement('div');
                nameSpan.className = 'small text-truncate text-muted';
                nameSpan.style.fontSize = '0.75rem';
                nameSpan.textContent = file.name;
                body.appendChild(nameSpan);

                card.appendChild(img);
                card.appendChild(removeBtn);
                card.appendChild(body);
                col.appendChild(card);
                previewGrid.appendChild(col);
            });
        }

        function removeFile(indexToRemove) {
            const newTransfer = new DataTransfer();
            Array.from(selectedFiles.files).forEach((file, idx) => {
                if (idx !== indexToRemove) {
                    newTransfer.items.add(file);
                }
            });
            selectedFiles = newTransfer;
            fotoInput.files = selectedFiles.files;
            renderPreviews();
        }
    });
</script>
<?= $this->endSection() ?>