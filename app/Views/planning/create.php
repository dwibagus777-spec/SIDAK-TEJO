<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Buat Planning Inspeksi Baru<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Form Planning & Assignment Inspeksi Jaringan<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('planning') ?>">Planning Inspeksi</a></li>
<li class="breadcrumb-item active">Buat Baru</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-10 col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-primary text-white py-3 rounded-top-4">
                <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-edit me-2"></i> Form Perencanaan & Penugasan Inspeksi (WHAT + WHO)</h5>
            </div>
            <form action="<?= site_url('planning/store') ?>" method="post" id="formPlanning">
                <?= csrf_field() ?>
                <div class="card-body p-4">
                    <?php if (session()->getFlashdata('error')): ?>
                        <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i> <?= session()->getFlashdata('error') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- STEP 1: Identitas Planning -->
                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="fas fa-info-circle me-1"></i> STEP 1: Identitas & Jadwal Inspeksi</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-dark">Judul Planning Inspeksi <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control form-control-lg" placeholder="Contoh: Planning Inspeksi JTM Feeder GEDANGAN" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold text-dark">Jenis Pekerjaan <span class="text-danger">*</span></label>
                            <select name="inspection_type_id" class="form-select form-select-lg" required>
                                <option value="">-- Pilih Jenis Inspeksi --</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t['id'] ?>">[<?= esc($t['code']) ?>] <?= esc($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold text-dark">Jadwal Pelaksanaan <span class="text-danger">*</span></label>
                            <input type="date" name="scheduled_date" class="form-control form-control-lg" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- STEP 2: Scope Jaringan -->
                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="fas fa-network-wired me-1"></i> STEP 2: Scope Jaringan (GI ➔ ULP ➔ Penyulang)</h6>
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-dark">Gardu Induk (GI)</label>
                            <select name="gi_id" id="gi_id" class="form-select">
                                <option value="">-- Semua Gardu Induk --</option>
                                <?php foreach ($garduInduk as $gi): ?>
                                    <option value="<?= $gi['id'] ?>">[<?= esc($gi['kode_gi']) ?>] <?= esc($gi['nama_gi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-dark">ULP</label>
                            <select name="ulp_id" id="ulp_id" class="form-select">
                                <option value="">-- Semua ULP --</option>
                                <?php foreach ($ulps as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= esc($u['nama_ulp']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-dark">Penyulang / Feeder <span class="text-danger">*</span></label>
                            <select name="penyulang_id" id="penyulang_id" class="form-select" required>
                                <option value="">-- Pilih Penyulang --</option>
                                <?php foreach ($penyulangs as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-gi="<?= $p['gi_id'] ?? '' ?>" data-ulp="<?= $p['ulp_id'] ?? '' ?>">
                                        [<?= esc($p['kode_penyulang']) ?>] <?= esc($p['nama_penyulang']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- STEP 3: Objek & STEP 4: Asset Checkbox List -->
                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="fas fa-boxes-stacked me-1"></i> STEP 3 & 4: Objek & Target Asset (Snapshot List)</h6>
                    <div class="row mb-3">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-dark">Kategori Objek Asset</label>
                            <select name="jenis_asset" id="jenis_asset" class="form-select">
                                <option value="SEMUA">Semua Objek (Tiang, Gardu, Trafo, Keypoint)</option>
                                <option value="TIANG">Khusus TIANG</option>
                                <option value="GARDU">Khusus GARDU</option>
                                <option value="TRAFO">Khusus TRAFO</option>
                                <option value="KEYPOINT">Khusus KEYPOINT</option>
                            </select>
                        </div>
                        <div class="col-md-8 mb-3 d-flex align-items-end justify-content-end gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" id="btnSelectAll"><i class="fas fa-check-square me-1"></i> Pilih Semua</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="btnDeselectAll"><i class="fas fa-square me-1"></i> Hapus Semua</button>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" id="btnLoadAssets"><i class="fas fa-sync me-1"></i> Muat Asset Lapangan</button>
                        </div>
                    </div>

                    <div class="border rounded-4 p-3 bg-light mb-4" style="max-height: 280px; overflow-y: auto;" id="assetListContainer">
                        <div class="text-center text-muted py-4" id="assetPlaceholder">
                            <i class="fas fa-search-location fa-2x mb-2 text-secondary opacity-50 d-block"></i>
                            Pilih <strong>Penyulang</strong> dan klik <strong>Muat Asset Lapangan</strong> untuk memilih daftar asset snapshot.
                        </div>
                        <div class="row row-cols-1 row-cols-md-2 g-2" id="assetCheckboxList"></div>
                    </div>

                    <!-- STEP 5: Assign Inspector -->
                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="fas fa-user-check me-1"></i> STEP 5: Penugasan Petugas Inspeksi (WHO)</h6>
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-dark">Petugas Inspeksi Lapangan (Assigned Inspector)</label>
                            <select name="assigned_inspector_id" class="form-select form-select-lg">
                                <option value="">-- Pilih Petugas Inspeksi --</option>
                                <?php foreach ($inspectors as $usr): ?>
                                    <option value="<?= $usr['id'] ?>"><?= esc($usr['nama_lengkap']) ?> (<?= esc($usr['username']) ?> &bull; <?= esc($usr['role'] ?? 'Petugas') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center rounded-bottom-4">
                    <a href="<?= site_url('planning') ?>" class="btn btn-outline-secondary rounded-pill px-4">Batal</a>
                    <div>
                        <button type="submit" name="action" value="draft" class="btn btn-secondary rounded-pill px-4 me-2">
                            <i class="fas fa-save me-1"></i> Simpan Draft
                        </button>
                        <button type="submit" name="action" value="publish" class="btn btn-primary rounded-pill px-5 font-weight-bold">
                            <i class="fas fa-paper-plane me-1"></i> Publish & Assign Planning
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const giSelect = document.getElementById('gi_id');
    const ulpSelect = document.getElementById('ulp_id');
    const penyulangSelect = document.getElementById('penyulang_id');
    const jenisAssetSelect = document.getElementById('jenis_asset');
    const btnLoadAssets = document.getElementById('btnLoadAssets');
    const btnSelectAll = document.getElementById('btnSelectAll');
    const btnDeselectAll = document.getElementById('btnDeselectAll');
    const assetCheckboxList = document.getElementById('assetCheckboxList');
    const assetPlaceholder = document.getElementById('assetPlaceholder');

    // Cascading Penyulang Filter
    const origPenyulangOpts = Array.from(penyulangSelect.options);
    function filterPenyulang() {
        const selGi = giSelect.value;
        const selUlp = ulpSelect.value;

        penyulangSelect.innerHTML = '';
        const defOpt = document.createElement('option');
        defOpt.value = '';
        defOpt.textContent = '-- Pilih Penyulang --';
        penyulangSelect.appendChild(defOpt);

        origPenyulangOpts.forEach(opt => {
            if (!opt.value) return;
            const matchGi = !selGi || (opt.getAttribute('data-gi') === selGi);
            const matchUlp = !selUlp || (opt.getAttribute('data-ulp') === selUlp);
            if (matchGi && matchUlp) {
                penyulangSelect.appendChild(opt.cloneNode(true));
            }
        });
    }
    giSelect.addEventListener('change', filterPenyulang);
    ulpSelect.addEventListener('change', filterPenyulang);

    // AJAX Load Assets for Selection
    btnLoadAssets.addEventListener('click', function() {
        const penyulangId = penyulangSelect.value;
        if (!penyulangId) {
            alert('Pilih Penyulang terlebih dahulu.');
            return;
        }

        assetPlaceholder.style.display = 'block';
        assetPlaceholder.innerHTML = '<i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i><br>Memuat daftar asset lapangan...';
        assetCheckboxList.innerHTML = '';

        const jenis = jenisAssetSelect.value;

        fetch(`<?= site_url('assets/geojson') ?>?penyulang_id=${penyulangId}`)
            .then(res => res.json())
            .then(res => {
                const features = (res.features || []);
                let filtered = features;
                if (jenis !== 'SEMUA') {
                    filtered = features.filter(f => (f.properties.jenis_asset || '').toUpperCase() === jenis);
                }

                if (filtered.length === 0) {
                    assetPlaceholder.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> Tidak ada asset yang sesuai dengan kriteria scope terpilih.</span>';
                    return;
                }

                assetPlaceholder.style.display = 'none';
                let html = '';
                filtered.forEach((f, idx) => {
                    const prop = f.properties;
                    const astId = prop.id;
                    const kode = prop.kode_asset || `AST-${astId}`;
                    const nama = prop.nama_asset || `Asset ${kode}`;
                    const jns = prop.jenis_asset || 'TIANG';

                    html += `
                        <div class="col">
                            <div class="form-check card p-2 mb-0 border-0 shadow-sm rounded-3 bg-white">
                                <input class="form-check-input asset-checkbox me-2" type="checkbox" name="asset_ids[]" value="${astId}" id="chk_ast_${astId}" checked>
                                <label class="form-check-label w-100" for="chk_ast_${astId}">
                                    <strong class="text-dark d-block mb-0">${idx + 1}. [${kode}] ${nama}</strong>
                                    <small class="text-muted">${jns} &bull; ${prop.lokasi || 'Jalur Feeder'}</small>
                                </label>
                            </div>
                        </div>
                    `;
                });
                assetCheckboxList.innerHTML = html;
            })
            .catch(err => {
                assetPlaceholder.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Gagal memuat daftar asset. Silakan coba lagi.</span>';
            });
    });

    btnSelectAll.addEventListener('click', function() {
        document.querySelectorAll('.asset-checkbox').forEach(c => c.checked = true);
    });

    btnDeselectAll.addEventListener('click', function() {
        document.querySelectorAll('.asset-checkbox').forEach(c => c.checked = false);
    });
});
</script>
<?= $this->endSection() ?>
