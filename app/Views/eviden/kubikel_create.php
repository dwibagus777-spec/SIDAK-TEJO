<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Tambah Eviden Kubikel<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Input Eviden Kubikel<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('eviden/kubikel') ?>">Eviden Kubikel</a></li>
<li class="breadcrumb-item active">Tambah</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- SIDAK-KUBIKEL-CREATE-RUNTIME-MARKER: MNF01-DEPLOYED-20260825-B -->
<script>
console.log('%c[SIDAK BUILD] MNF01-DEPLOYED-20260825-B', 'background: #000; color: #00ff00; font-size: 14px; font-weight: bold; padding: 4px;');
</script>
<div class="row">
    <div class="col-md-10 offset-md-1">
        <div class="card card-modern">
            <div class="card-header card-header-modern">
                <h3 class="card-title text-white font-weight-bold">
                    <i class="fas fa-plus-circle mr-2"></i> Formulir Eviden Pemeliharaan Kubikel
                </h3>
            </div>
            
            <form action="<?= site_url('eviden/kubikel/store') ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="card-body">
                    
                    <?php if (isset($validation)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle mr-2"></i> Harap perbaiki kesalahan pengisian form berikut.
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- ULP Dropdown -->
                        <div class="col-md-4 form-group">
                            <label for="ulp_id" class="font-weight-bold">Unit Layanan Pelanggan (ULP) <span class="text-danger">*</span></label>
                            <select id="ulp_id" name="ulp_id" class="form-control select2" required>
                                <option value="">-- Pilih ULP --</option>
                                <?php foreach ($ulps as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= old('ulp_id') == $u['id'] ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Penyulang Dropdown -->
                        <div class="col-md-4 form-group">
                            <label for="id_penyulang" class="font-weight-bold">Penyulang <span class="text-danger">*</span></label>
                            <select id="id_penyulang" name="id_penyulang" class="form-control select2" required disabled>
                                <option value="">-- Pilih Penyulang --</option>
                            </select>
                        </div>

                        <!-- Section Dropdown -->
                        <div class="col-md-4 form-group">
                            <label for="id_section" class="font-weight-bold">Section <span class="text-danger">*</span></label>
                            <select id="id_section" name="id_section" class="form-control select2" required disabled>
                                <option value="">-- Pilih Section --</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <!-- Nama Gardu -->
                        <div class="col-md-4 form-group">
                            <label for="nama_gardu" class="font-weight-bold">Nama Gardu <span class="text-danger">*</span></label>
                            <input type="text" id="nama_gardu" name="nama_gardu" class="form-control" placeholder="Contoh: PA023" value="<?= old('nama_gardu') ?>" required>
                        </div>

                        <!-- ID Pelanggan -->
                        <div class="col-md-4 form-group">
                            <label for="id_pel" class="font-weight-bold">ID Pelanggan (12 Digit)</label>
                            <input type="text" id="id_pel" name="id_pel" class="form-control" placeholder="Masukkan ID Pelanggan" value="<?= old('id_pel') ?>">
                        </div>

                        <!-- Tanggal Input -->
                        <div class="col-md-4 form-group">
                            <label for="tgl_input" class="font-weight-bold">Tanggal Input <span class="text-danger">*</span></label>
                            <input type="date" id="tgl_input" name="tgl_input" class="form-control" value="<?= old('tgl_input') ?: date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- Keterangan -->
                    <div class="form-group mt-2">
                        <label for="keterangan" class="font-weight-bold">Keterangan Pemeliharaan <span class="text-danger">*</span></label>
                        <textarea id="keterangan" name="keterangan" class="form-control" rows="3" placeholder="Tuliskan rincian hasil pemeliharaan kubikel..." required><?= old('keterangan') ?></textarea>
                    </div>

                    <!-- FOTO EVIDEN GRID (TOTAL 24 FOTO) -->
                    <h5 class="mt-4 border-top pt-3 font-weight-bold text-info"><i class="fas fa-camera mr-1"></i> Upload Foto Eviden (Total 24 Foto)</h5>
                    
                    <!-- 1. Foto Kubikel (3 Foto) -->
                    <div class="card card-outline card-info mt-3 shadow-none bg-light">
                        <div class="card-header py-2"><h6 class="mb-0 font-weight-bold text-primary">Foto Kubikel (3 Foto)</h6></div>
                        <div class="card-body py-2">
                            <div class="row">
                                <?php for($i = 1; $i <= 3; $i++): ?>
                                <div class="col-md-4 form-group mb-2">
                                    <label class="small font-weight-bold">Foto Kubikel <?= $i ?></label>
                                    <input type="file" name="foto_kubikel[]" class="form-control-file border p-1 rounded bg-white" accept="image/*">
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Foto Merek Kubikel (1 Foto) -->
                    <div class="card card-outline card-info mt-3 shadow-none bg-light">
                        <div class="card-header py-2"><h6 class="mb-0 font-weight-bold text-primary">Foto Merek Kubikel (1 Foto)</h6></div>
                        <div class="card-body py-2">
                            <div class="row">
                                <div class="col-md-4 form-group mb-2">
                                    <label class="small font-weight-bold">Foto Merek</label>
                                    <input type="file" name="foto_merek[]" class="form-control-file border p-1 rounded bg-white" accept="image/*">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Foto CT (3 Foto) -->
                    <div class="card card-outline card-info mt-3 shadow-none bg-light">
                        <div class="card-header py-2"><h6 class="mb-0 font-weight-bold text-primary">Foto CT (3 Foto)</h6></div>
                        <div class="card-body py-2">
                            <div class="row">
                                <?php for($i = 1; $i <= 3; $i++): ?>
                                <div class="col-md-4 form-group mb-2">
                                    <label class="small font-weight-bold">Foto CT <?= $i ?></label>
                                    <input type="file" name="foto_ct[]" class="form-control-file border p-1 rounded bg-white" accept="image/*">
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Foto VT (3 Foto) -->
                    <div class="card card-outline card-info mt-3 shadow-none bg-light">
                        <div class="card-header py-2"><h6 class="mb-0 font-weight-bold text-primary">Foto VT (3 Foto)</h6></div>
                        <div class="card-body py-2">
                            <div class="row">
                                <?php for($i = 1; $i <= 3; $i++): ?>
                                <div class="col-md-4 form-group mb-2">
                                    <label class="small font-weight-bold">Foto VT <?= $i ?></label>
                                    <input type="file" name="foto_vt[]" class="form-control-file border p-1 rounded bg-white" accept="image/*">
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Nameplate (5 Foto) -->
                    <div class="card card-outline card-info mt-3 shadow-none bg-light">
                        <div class="card-header py-2"><h6 class="mb-0 font-weight-bold text-primary">Nameplate (5 Foto)</h6></div>
                        <div class="card-body py-2">
                            <div class="row">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="col-md-2.4 col-sm-4 form-group mb-2 px-2">
                                    <label class="small font-weight-bold">NP <?= $i ?></label>
                                    <input type="file" name="foto_nameplate[]" class="form-control-file border p-1 rounded bg-white" accept="image/*">
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 6. Foto Merk Relay (2 Foto) -->
                    <div class="card card-outline card-info mt-3 shadow-none bg-light">
                        <div class="card-header py-2"><h6 class="mb-0 font-weight-bold text-primary">Merk Relay (2 Foto)</h6></div>
                        <div class="card-body py-2">
                            <div class="row">
                                <?php for($i = 1; $i <= 2; $i++): ?>
                                <div class="col-md-6 form-group mb-2">
                                    <label class="small font-weight-bold">Relay <?= $i ?></label>
                                    <input type="file" name="foto_relay[]" class="form-control-file border p-1 rounded bg-white" accept="image/*">
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 7. Foto Temuan (3 Foto) -->
                    <div class="card card-outline card-danger mt-3 shadow-none bg-light">
                        <div class="card-header py-2"><h6 class="mb-0 font-weight-bold text-danger">Foto Temuan (3 Foto)</h6></div>
                        <div class="card-body py-2">
                            <div class="row">
                                <?php for($i = 1; $i <= 3; $i++): ?>
                                <div class="col-md-4 form-group mb-2">
                                    <label class="small font-weight-bold">Temuan <?= $i ?></label>
                                    <input type="file" name="foto_temuan[]" class="form-control-file border p-1 rounded bg-white" accept="image/*">
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 8. Foto Perbaikan/Pergantian (4 Foto) -->
                    <div class="card card-outline card-success mt-3 shadow-none bg-light">
                        <div class="card-header py-2"><h6 class="mb-0 font-weight-bold text-success">Foto Perbaikan/Pergantian (4 Foto)</h6></div>
                        <div class="card-body py-2">
                            <div class="row">
                                <?php for($i = 1; $i <= 4; $i++): ?>
                                <div class="col-md-3 form-group mb-2">
                                    <label class="small font-weight-bold">Perbaikan <?= $i ?></label>
                                    <input type="file" name="foto_perbaikan[]" class="form-control-file border p-1 rounded bg-white" accept="image/*">
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                </div>
                
                <div class="card-footer bg-white border-top text-right">
                    <a href="<?= site_url('eviden/kubikel') ?>" class="btn btn-secondary font-weight-bold px-4 mr-2">Batal</a>
                    <button type="submit" class="btn btn-primary font-weight-bold px-4">
                        <i class="fas fa-save mr-1"></i> Simpan Eviden
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const $ = jQuery;
    const ulpSelect = $('#ulp_id');
    const feederSelect = $('#id_penyulang');
    const sectionSelect = $('#id_section');

    if (!ulpSelect.length || !feederSelect.length || !sectionSelect.length) {
        console.error('[KUBIKEL] Cascade select elements not found in DOM');
        return;
    }

    const NETWORK_LOOKUP_URL = <?= json_encode(site_url('ajax/network')) ?>;
    const oldPenyulangId = "<?= old('id_penyulang') ?>";
    const oldSectionId   = "<?= old('id_section') ?>";

    let feederAbortController = null;
    let sectionAbortController = null;
    let feederRequestToken = 0;
    let sectionRequestToken = 0;

    function refreshSelect2($el) {
        if ($.fn.select2 && $el.hasClass('select2-hidden-accessible')) {
            $el.trigger('change.select2');
        } else {
            $el.trigger('change');
        }
    }

    function resetSelect($select, placeholder, disabled = true) {
        $select.empty().append($('<option>', {
            value: '',
            text: placeholder
        })).prop('disabled', disabled);
        refreshSelect2($select);
    }

    async function loadFeeders(ulpId, selectedFeederId = null, callback = null) {
        if (feederAbortController) {
            feederAbortController.abort();
        }
        feederAbortController = new AbortController();
        const currentToken = ++feederRequestToken;

        console.log('[NETWORK] ULP CHANGE', ulpId);
        console.trace('[NETWORK] ULP CHANGE TRACE');

        if (!ulpId) {
            resetSelect(feederSelect, '-- Pilih ULP Dahulu --', true);
            resetSelect(sectionSelect, '-- Pilih Penyulang Dahulu --', true);
            return;
        }

        resetSelect(feederSelect, 'Sedang memuat penyulang...', true);
        resetSelect(sectionSelect, '-- Pilih Penyulang Dahulu --', true);

        const requestUrl = `${NETWORK_LOOKUP_URL}/penyulang/${encodeURIComponent(ulpId)}`;
        console.log('[NETWORK] REQUEST', requestUrl);

        try {
            const response = await fetch(requestUrl, {
                signal: feederAbortController.signal,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status} ${response.statusText}`);
            }

            const data = await response.json();
            if (currentToken !== feederRequestToken) return;

            console.log('[NETWORK] RESPONSE', data);

            feederSelect.empty();
            if (Array.isArray(data) && data.length > 0) {
                feederSelect.append($('<option>', { value: '', text: '-- Pilih Penyulang --' }));
                data.forEach(item => {
                    const opt = $('<option>', {
                        value: item.id,
                        text: item.nama_penyulang || item.nama || item.kode_penyulang
                    });
                    if (selectedFeederId && String(item.id) === String(selectedFeederId)) {
                        opt.prop('selected', true);
                    }
                    feederSelect.append(opt);
                });
                feederSelect.prop('disabled', false);
            } else {
                feederSelect.append($('<option>', { value: '', text: '-- Tidak ada penyulang aktif --' }));
                feederSelect.prop('disabled', true);
            }
            refreshSelect2(feederSelect);

            if (selectedFeederId && feederSelect.val()) {
                loadSections(feederSelect.val(), oldSectionId, callback);
            } else if (callback) {
                callback();
            }

        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('[NETWORK] Feeder request aborted due to newer selection');
                return;
            }
            console.error('[NETWORK] Failed loading feeders:', error);
            if (currentToken === feederRequestToken) {
                resetSelect(feederSelect, 'Gagal memuat data Penyulang. Silakan coba kembali.', true);
            }
        }
    }

    async function loadSections(penyulangId, selectedSectionId = null, callback = null) {
        if (sectionAbortController) {
            sectionAbortController.abort();
        }
        sectionAbortController = new AbortController();
        const currentToken = ++sectionRequestToken;

        console.log('[NETWORK] PENYULANG CHANGE', penyulangId);
        console.trace('[NETWORK] PENYULANG CHANGE TRACE');

        if (!penyulangId) {
            resetSelect(sectionSelect, '-- Pilih Penyulang Dahulu --', true);
            return;
        }

        resetSelect(sectionSelect, 'Sedang memuat section...', true);

        const requestUrl = `${NETWORK_LOOKUP_URL}/section/${encodeURIComponent(penyulangId)}`;
        console.log('[NETWORK] REQUEST', requestUrl);

        try {
            const response = await fetch(requestUrl, {
                signal: sectionAbortController.signal,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status} ${response.statusText}`);
            }

            const data = await response.json();
            if (currentToken !== sectionRequestToken) return;

            console.log('[NETWORK] RESPONSE', data);

            sectionSelect.empty();
            if (Array.isArray(data) && data.length > 0) {
                sectionSelect.append($('<option>', { value: '', text: '-- Pilih Section --' }));
                data.forEach(item => {
                    const opt = $('<option>', {
                        value: item.id,
                        text: item.nama_section || item.nama
                    });
                    if (selectedSectionId && String(item.id) === String(selectedSectionId)) {
                        opt.prop('selected', true);
                    }
                    sectionSelect.append(opt);
                });
                sectionSelect.prop('disabled', false);
            } else {
                sectionSelect.append($('<option>', { value: '', text: '-- Tidak ada section aktif --' }));
                sectionSelect.prop('disabled', true);
            }
            refreshSelect2(sectionSelect);

            if (callback) callback();

        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('[NETWORK] Section request aborted due to newer selection');
                return;
            }
            console.error('[NETWORK] Failed loading sections:', error);
            if (currentToken === sectionRequestToken) {
                resetSelect(sectionSelect, 'Gagal memuat data Section. Silakan coba kembali.', true);
            }
        }
    }

    // Self-Healing: Purge ANY existing unnamespaced legacy change handlers
    ulpSelect.off('change');
    feederSelect.off('change');

    // Canonical MNF-01 Event Listeners
    ulpSelect.on('change.networkLookup', function () {
        loadFeeders($(this).val());
    });

    feederSelect.on('change.networkLookup', function () {
        loadSections($(this).val());
    });

    console.log('[MNF-01] CANONICAL KUBIKEL CREATE BINDING ACTIVE');

    // Handle Pre-selected ULP on Initial Page Load (e.g. from old input, user default, or browser draft)
    const initialUlpId = ulpSelect.val();
    if (initialUlpId) {
        console.log('[NETWORK] Initial ULP detected on page load:', initialUlpId);
        loadFeeders(initialUlpId, oldPenyulangId);
    }
});
</script>
<?= $this->endSection() ?>
