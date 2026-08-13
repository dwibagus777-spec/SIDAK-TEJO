<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Planning Inspeksi<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Kelola & Register Planning Inspeksi<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('planning') ?>">Planning Inspeksi</a></li>
<li class="breadcrumb-item active">Perencanaan Baru</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid px-0">
    <form action="<?= site_url('planning/store') ?>" method="post" id="formPlanningStreamlined">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="publish">

        <!-- TOP HEADER & CONTEXT FILTER PANEL -->
        <div class="card shadow-sm border-0 rounded-4 mb-4" style="background: linear-gradient(135deg, #005EB8 0%, #003B73 100%);">
            <div class="card-body p-4 text-white">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="fw-bold font-outfit mb-1"><i class="fas fa-calendar-alt me-2"></i> Planning Inspeksi JTM</h4>
                        <p class="mb-0 text-white-50 small">Rencanakan kegiatan inspeksi berdasarkan penyulang, jenis pekerjaan, dan jenis asset.</p>
                    </div>
                    <a href="<?= site_url('planning') ?>" class="btn btn-outline-light btn-sm rounded-pill px-3">
                        <i class="fas fa-list me-1"></i> Daftar Planning
                    </a>
                </div>

                <div class="row g-3 bg-white text-dark rounded-4 p-3 shadow-sm mx-0">
                    <!-- 1. ULP -->
                    <div class="col-md-3 col-6">
                        <label class="form-label fw-bold small text-secondary mb-1"><i class="fas fa-network-wired text-primary me-1"></i> ULP</label>
                        <select name="ulp_id" id="filter_ulp_id" class="form-select fw-bold">
                            <option value="">-- Pilih ULP --</option>
                            <?php foreach ($ulps as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= esc($u['nama_ulp']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Penyulang -->
                    <div class="col-md-3 col-6">
                        <label class="form-label fw-bold small text-secondary mb-1"><i class="fas fa-bolt text-primary me-1"></i> Penyulang <span class="text-danger">*</span></label>
                        <select name="penyulang_id" id="filter_penyulang_id" class="form-select fw-bold" required disabled>
                            <option value="">-- Pilih ULP Terlebih Dahulu --</option>
                        </select>
                    </div>

                    <!-- 3. Jenis Pekerjaan -->
                    <div class="col-md-3 col-6">
                        <label class="form-label fw-bold small text-secondary mb-1"><i class="fas fa-tasks text-primary me-1"></i> Pekerjaan <span class="text-danger">*</span></label>
                        <select name="inspection_type_id" id="inspection_type_id" class="form-select fw-bold" required>
                            <option value="">-- Pilih Jenis Pekerjaan --</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= esc($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 4. Jenis Asset -->
                    <div class="col-md-3 col-6">
                        <label class="form-label fw-bold small text-secondary mb-1"><i class="fas fa-cubes text-primary me-1"></i> Jenis Asset <span class="text-danger">*</span></label>
                        <select name="jenis_asset" id="filter_jenis_asset" class="form-select fw-bold text-primary">
                            <option value="TIANG">Tiang</option>
                            <option value="GARDU">Gardu</option>
                            <option value="TRAFO">Trafo</option>
                            <option value="RECLOSER">Recloser</option>
                            <option value="PMCB">PMCB</option>
                            <option value="POHON">Pohon</option>
                            <option value="SEMUA">Semua Asset</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- MAIN LAYOUT: ASSET TABLE (COL 8) vs DETAIL PLANNING PANEL (COL 4) -->
        <div class="row">
            <!-- LEFT COLUMN: ASSET LIST TABLE -->
            <div class="col-lg-8 col-12 mb-4">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary text-white p-2 rounded-3 font-outfit" id="headerContextBadge">
                                <i class="fas fa-map-marker-alt me-1"></i> Pilih Context
                            </span>
                            <span class="badge bg-light text-dark border p-2 rounded-3 small" id="selectedCounterBadge">
                                0 Asset Dipilih
                            </span>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="checkSelectAll" style="cursor: pointer;">
                            <label class="form-check-label fw-bold small cursor-pointer" for="checkSelectAll">Pilih Semua</label>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0" id="tablePlanningAssets">
                                <thead class="table-light sticky-top" style="z-index: 5;">
                                    <tr class="small text-secondary">
                                        <th style="width: 40px;" class="text-center">#</th>
                                        <th style="width: 50px;">No</th>
                                        <th>Nama Asset</th>
                                        <th>Section</th>
                                        <th>Latitude</th>
                                        <th>Longitude</th>
                                        <th>Konstruksi</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyPlanningAssets">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-5" id="assetTableEmptyState">
                                            <i class="fas fa-mouse-pointer fa-2x mb-3 text-secondary opacity-50 d-block"></i>
                                            Silakan pilih <strong>ULP</strong> dan <strong>Penyulang</strong> di atas untuk memuat daftar asset.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: DETAIL PLANNING PANEL -->
            <div class="col-lg-4 col-12 mb-4">
                <div class="card shadow-sm border-0 rounded-4 position-sticky" style="top: 80px;">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="card-title mb-0 fw-bold font-outfit text-dark">
                            <i class="fas fa-edit text-primary me-2"></i> Detail Planning
                        </h6>
                    </div>
                    <div class="card-body p-3">
                        <!-- Judul Planning -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary mb-1">Judul Planning <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="planning_title" class="form-control" placeholder="Contoh: Planning Inspeksi JTM Banjar Kemantren" required>
                        </div>

                        <!-- Range Dari Nomor - Ke Nomor -->
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold small text-secondary mb-1">Dari Nomor</label>
                                <input type="text" id="dari_nomor" class="form-control bg-light" placeholder="1" readonly>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold small text-secondary mb-1">Ke Nomor</label>
                                <input type="text" id="ke_nomor" class="form-control bg-light" placeholder="1" readonly>
                            </div>
                        </div>

                        <!-- Tanggal Mulai -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary mb-1">Tanggal Mulai <span class="text-danger">*</span></label>
                            <input type="date" name="scheduled_date" id="scheduled_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <!-- Lama Pekerjaan & Periodik -->
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold small text-secondary mb-1">Lama Pekerjaan (Hari)</label>
                                <input type="number" name="lama_pekerjaan" id="lama_pekerjaan" class="form-control" value="31" min="1">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold small text-secondary mb-1">Periodik (Bulan)</label>
                                <select name="periodik" id="periodik" class="form-select">
                                    <option value="0">0 (Sekali)</option>
                                    <option value="1">1 Bulan</option>
                                    <option value="3" selected>3 Bulan</option>
                                    <option value="6">6 Bulan</option>
                                    <option value="12">12 Bulan</option>
                                </select>
                            </div>
                        </div>

                        <!-- Penugasan Petugas -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary mb-1">Petugas Inspeksi (Inspector)</label>
                            <select name="assigned_inspector_id" id="assigned_inspector_id" class="form-select">
                                <option value="">-- Otomatis (Pool ULP) --</option>
                                <?php foreach ($inspectors as $usr): ?>
                                    <option value="<?= $usr['id'] ?>"><?= esc($usr['nama'] ?: $usr['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Action Submit Button -->
                        <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-pill font-weight-bold shadow-sm mb-3" id="btnSubmitPlanning">
                            <i class="fas fa-calendar-check me-1"></i> Rencanakan Inspeksi
                        </button>

                        <!-- Context-Aware Download Template Links -->
                        <div class="border-top pt-3 text-center">
                            <span class="small text-muted d-block mb-2 font-monospace">DOWNLOAD TEMPLATE ASSET:</span>
                            <div class="d-flex justify-content-center gap-2">
                                <a href="#" id="btnDownloadTemplateCsv" class="btn btn-sm btn-outline-info rounded-pill flex-fill">
                                    <i class="fas fa-file-csv me-1"></i> Template CSV
                                </a>
                                <a href="#" id="btnDownloadTemplateXlsx" class="btn btn-sm btn-outline-success rounded-pill flex-fill">
                                    <i class="fas fa-file-excel me-1"></i> Template Excel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- SCRIPT STREAMLINED PLANNING -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ulpSelect = document.getElementById('filter_ulp_id');
    const penyulangSelect = document.getElementById('filter_penyulang_id');
    const jenisAssetSelect = document.getElementById('filter_jenis_asset');
    const typeSelect = document.getElementById('inspection_type_id');
    const tbodyAssets = document.getElementById('tbodyPlanningAssets');
    const checkSelectAll = document.getElementById('checkSelectAll');
    const selectedCounterBadge = document.getElementById('selectedCounterBadge');
    const headerContextBadge = document.getElementById('headerContextBadge');
    const dariNomorInput = document.getElementById('dari_nomor');
    const keNomorInput = document.getElementById('ke_nomor');
    const planningTitleInput = document.getElementById('planning_title');
    const btnDownloadCsv = document.getElementById('btnDownloadTemplateCsv');
    const btnDownloadXlsx = document.getElementById('btnDownloadTemplateXlsx');

    let loadedAssets = [];

    // 1. Cascading ULP -> Penyulang
    ulpSelect.addEventListener('change', function() {
        const ulpId = this.value;
        penyulangSelect.innerHTML = '<option value="">-- Memuat Penyulang... --</option>';
        penyulangSelect.disabled = true;
        resetAssetTable();

        if (!ulpId) {
            penyulangSelect.innerHTML = '<option value="">-- Pilih ULP Terlebih Dahulu --</option>';
            return;
        }

        fetch('<?= site_url('master-assets/penyulang-by-ulp/') ?>' + ulpId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            let options = '<option value="">-- Pilih Penyulang --</option>';
            if (Array.isArray(data) && data.length > 0) {
                data.forEach(p => {
                    options += `<option value="${p.id}">${p.nama_penyulang}</option>`;
                });
                penyulangSelect.disabled = false;
            } else {
                options = '<option value="">-- Tidak ada penyulang --</option>';
                penyulangSelect.disabled = true;
            }
            penyulangSelect.innerHTML = options;
        });
    });

    // 2. Load Assets dynamically on Penyulang / Jenis Asset change
    penyulangSelect.addEventListener('change', loadAssets);
    jenisAssetSelect.addEventListener('change', loadAssets);

    function loadAssets() {
        const penyulangId = penyulangSelect.value;
        const jenisAsset = jenisAssetSelect.value;

        updateContextTitle();
        updateDownloadTemplateLinks();

        if (!penyulangId) {
            resetAssetTable();
            return;
        }

        tbodyAssets.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-muted py-5">
                    <i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i><br>Memuat data asset...
                </td>
            </tr>
        `;

        fetch(`<?= site_url('planning/ajax-assets') ?>?penyulang_id=${penyulangId}&jenis_asset=${jenisAsset}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            loadedAssets = data || [];
            renderAssetTable(loadedAssets);
        })
        .catch(err => {
            tbodyAssets.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-danger py-4">
                        <i class="fas fa-exclamation-triangle me-1"></i> Gagal memuat data asset.
                    </td>
                </tr>
            `;
        });
    }

    function renderAssetTable(assets) {
        if (!assets || assets.length === 0) {
            tbodyAssets.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="fas fa-exclamation-circle fa-2x mb-2 text-secondary opacity-50 d-block"></i>
                        Belum ada asset untuk kombinasi Penyulang & Jenis Asset ini.
                    </td>
                </tr>
            `;
            updateSelectedCounter();
            return;
        }

        let html = '';
        assets.forEach((ast, idx) => {
            html += `
                <tr>
                    <td class="text-center">
                        <input class="form-check-input chk-asset-item" type="checkbox" name="asset_ids[]" value="${ast.id}" data-seq="${idx + 1}" checked>
                    </td>
                    <td class="fw-bold small text-secondary">${idx + 1}</td>
                    <td>
                        <strong class="text-dark d-block mb-0">${ast.nama_asset}</strong>
                        <small class="text-muted font-monospace">${ast.kode_asset || ''}</small>
                    </td>
                    <td><span class="badge bg-light text-dark border">${ast.nama_section || 'Utama'}</span></td>
                    <td class="small font-monospace">${ast.latitude || '-'}</td>
                    <td class="small font-monospace">${ast.longitude || '-'}</td>
                    <td class="small text-uppercase">${ast.jenis_asset || 'Tiang'}</td>
                    <td><span class="badge bg-success text-white">NORMAL</span></td>
                </tr>
            `;
        });

        tbodyAssets.innerHTML = html;
        checkSelectAll.checked = true;

        // Add event listeners to checkboxes
        document.querySelectorAll('.chk-asset-item').forEach(chk => {
            chk.addEventListener('change', updateSelectedCounter);
        });

        updateSelectedCounter();
    }

    function resetAssetTable() {
        loadedAssets = [];
        tbodyAssets.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-muted py-5">
                    <i class="fas fa-mouse-pointer fa-2x mb-3 text-secondary opacity-50 d-block"></i>
                    Silakan pilih <strong>ULP</strong> dan <strong>Penyulang</strong> di atas untuk memuat daftar asset.
                </td>
            </tr>
        `;
        checkSelectAll.checked = false;
        updateSelectedCounter();
    }

    // 3. Select All Toggle
    checkSelectAll.addEventListener('change', function() {
        const isChecked = this.checked;
        document.querySelectorAll('.chk-asset-item').forEach(chk => {
            chk.checked = isChecked;
        });
        updateSelectedCounter();
    });

    // 4. Update Counter & Smart Range (Dari Nomor - Ke Nomor)
    function updateSelectedCounter() {
        const checkedItems = document.querySelectorAll('.chk-asset-item:checked');
        const total = loadedAssets.length;
        const count = checkedItems.length;

        selectedCounterBadge.textContent = `${count} dari ${total} Asset Dipilih`;

        if (count > 0) {
            const seqs = Array.from(checkedItems).map(c => parseInt(c.getAttribute('data-seq'))).sort((a,b) => a - b);
            dariNomorInput.value = seqs[0];
            keNomorInput.value = seqs[seqs.length - 1];
        } else {
            dariNomorInput.value = '-';
            keNomorInput.value = '-';
        }
    }

    // 5. Update Context Title & Download Template Links
    function updateContextTitle() {
        const ulpText = ulpSelect.options[ulpSelect.selectedIndex]?.text || '';
        const penyulangText = penyulangSelect.options[penyulangSelect.selectedIndex]?.text || '';
        const jenisAsset = jenisAssetSelect.value;
        const typeText = typeSelect.options[typeSelect.selectedIndex]?.text || '';

        if (penyulangSelect.value) {
            headerContextBadge.innerHTML = `<i class="fas fa-map-marker-alt me-1"></i> ${penyulangText} &bull; ${jenisAsset}`;
            if (!planningTitleInput.value || planningTitleInput.value.startsWith('Planning Inspeksi')) {
                planningTitleInput.value = `Planning Inspeksi ${typeText || 'JTM'} - ${penyulangText}`;
            }
        } else {
            headerContextBadge.innerHTML = `<i class="fas fa-map-marker-alt me-1"></i> Pilih Context`;
        }
    }

    function updateDownloadTemplateLinks() {
        const ulpId = ulpSelect.value;
        const penyulangId = penyulangSelect.value;
        const jenis = jenisAssetSelect.value;

        if (ulpId && penyulangId) {
            btnDownloadCsv.href = `<?= site_url('master-assets/template') ?>?up3=UP3+Sidoarjo&ulp_id=${ulpId}&penyulang_id=${penyulangId}&jenis_asset=${jenis}&format=csv`;
            btnDownloadXlsx.href = `<?= site_url('master-assets/template') ?>?up3=UP3+Sidoarjo&ulp_id=${ulpId}&penyulang_id=${penyulangId}&jenis_asset=${jenis}&format=xlsx`;
            btnDownloadCsv.target = '_blank';
            btnDownloadXlsx.target = '_blank';
        } else {
            btnDownloadCsv.href = '#';
            btnDownloadXlsx.href = '#';
            btnDownloadCsv.removeAttribute('target');
            btnDownloadXlsx.removeAttribute('target');
        }
    }

    typeSelect.addEventListener('change', updateContextTitle);
});
</script>
<?= $this->endSection() ?>
