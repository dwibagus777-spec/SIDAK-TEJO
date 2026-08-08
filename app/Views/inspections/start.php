<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Mulai Inspeksi Guided Baru<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Persiapan Inspeksi Jaringan Lapangan<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('inspections') ?>">Inspeksi Jaringan</a></li>
<li class="breadcrumb-item active">Mulai Baru</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-9 col-lg-8 col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-primary text-white py-3 rounded-top-4">
                <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-network-wired me-2"></i> Inisialisasi Guided Inspection (Cascading Selection)</h5>
            </div>
            <form action="<?= site_url('inspections/start') ?>" method="post">
                <?= csrf_field() ?>
                <div class="card-body p-4">
                    <p class="text-muted small mb-4">
                        Tentukan hirarki lokasi jaringan mulai dari <strong>Gardu Induk (GI)</strong>, <strong>ULP</strong>, <strong>Penyulang</strong>, hingga <strong>Objek Pemeriksaan</strong>. Aplikasi akan menyiapkan lintasan Guided Inspection secara presisi.
                    </p>

                    <?php if (session()->getFlashdata('error')): ?>
                        <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i> <?= session()->getFlashdata('error') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Step 1: Jenis Pekerjaan Inspeksi -->
                    <div class="mb-4">
                        <label for="inspection_type_id" class="form-label fw-bold text-dark">1. Jenis Pekerjaan Inspeksi <span class="text-danger">*</span></label>
                        <select name="inspection_type_id" id="inspection_type_id" class="form-select form-select-lg rounded-3" required>
                            <option value="">-- Pilih Jenis Pekerjaan --</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= $t['id'] ?>">
                                    [<?= esc($t['code']) ?>] <?= esc($t['name']) ?> (Interval: <?= esc($t['default_interval_months']) ?> Bulan)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Step 2: Gardu Induk (GI) -->
                    <div class="mb-4">
                        <label for="gi_id" class="form-label fw-bold text-dark">2. Gardu Induk (GI) <span class="text-danger">*</span></label>
                        <select name="gi_id" id="gi_id" class="form-select rounded-3">
                            <option value="">-- Semua Gardu Induk (GI) --</option>
                            <?php if (!empty($garduInduk)): ?>
                                <?php foreach ($garduInduk as $gi): ?>
                                    <option value="<?= $gi['id'] ?>">
                                        [<?= esc($gi['kode_gi']) ?>] <?= esc($gi['nama_gi']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Step 3: ULP (Unit Layanan) -->
                    <div class="mb-4">
                        <label for="ulp_id" class="form-label fw-bold text-dark">3. Unit Layanan Pelanggan (ULP) <span class="text-danger">*</span></label>
                        <select name="ulp_id" id="ulp_id" class="form-select rounded-3">
                            <option value="">-- Semua ULP --</option>
                            <?php if (!empty($ulps)): ?>
                                <?php foreach ($ulps as $u): ?>
                                    <option value="<?= $u['id'] ?>">
                                        <?= esc($u['nama_ulp']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Step 4: Penyulang (Feeder) -->
                    <div class="mb-4">
                        <label for="penyulang_id" class="form-label fw-bold text-dark">4. Rute Penyulang / Feeder <span class="text-danger">*</span></label>
                        <select name="penyulang_id" id="penyulang_id" class="form-select form-select-lg rounded-3" required>
                            <option value="">-- Pilih Penyulang / Feeder --</option>
                            <?php if (!empty($penyulangs)): ?>
                                <?php foreach ($penyulangs as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-gi="<?= $p['gi_id'] ?? '' ?>" data-ulp="<?= $p['ulp_id'] ?? '' ?>">
                                        [<?= esc($p['kode_penyulang']) ?>] <?= esc($p['nama_penyulang']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted" id="penyulang_count_info">Menampilkan seluruh penyulang aktif.</small>
                    </div>

                    <!-- Step 5: Objek Pemeriksaan -->
                    <div class="mb-4">
                        <label for="object_type" class="form-label fw-bold text-dark">5. Objek Pemeriksaan Aset <span class="text-danger">*</span></label>
                        <select name="object_type" id="object_type" class="form-select rounded-3" required>
                            <option value="SEMUA" selected>Semua Objek (Tiang, Gardu, Trafo, Keypoint)</option>
                            <option value="TIANG">Khusus TIANG JTM / JTR</option>
                            <option value="GARDU">Khusus GARDU (Portal / Cantol / Kubikel)</option>
                            <option value="TRAFO">Khusus TRAFO Distribusi</option>
                            <option value="KEYPOINT">Khusus KEYPOINT (LBS / Recloser / Fused Cutout)</option>
                        </select>
                    </div>

                    <div class="alert alert-info rounded-3 small border-0 mb-0">
                        <i class="fas fa-shield-alt me-1 text-primary"></i> Seluruh data Guided Inspection tersimpan otomatis pada memori lokal dan disinkronisasi ke server secara aman.
                    </div>
                </div>

                <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center rounded-bottom-4">
                    <a href="<?= site_url('inspections') ?>" class="btn btn-outline-secondary rounded-pill px-4">Batal</a>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 font-weight-bold">
                        <i class="fas fa-play me-1"></i> Mulai Petualangan Inspeksi &rarr;
                    </button>
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
    const penyulangInfo = document.getElementById('penyulang_count_info');

    if (!giSelect || !ulpSelect || !penyulangSelect) return;

    // Cache original penyulang options
    const originalOptions = Array.from(penyulangSelect.options);

    function filterPenyulang() {
        const selectedGi = giSelect.value;
        const selectedUlp = ulpSelect.value;

        let matchCount = 0;

        penyulangSelect.innerHTML = '';
        
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = '-- Pilih Penyulang / Feeder --';
        penyulangSelect.appendChild(defaultOpt);

        originalOptions.forEach(opt => {
            if (!opt.value) return;

            const optGi = opt.getAttribute('data-gi');
            const optUlp = opt.getAttribute('data-ulp');

            let matchGi = !selectedGi || (optGi === selectedGi);
            let matchUlp = !selectedUlp || (optUlp === selectedUlp);

            if (matchGi && matchUlp) {
                penyulangSelect.appendChild(opt.cloneNode(true));
                matchCount++;
            }
        });

        if (penyulangInfo) {
            if (selectedGi || selectedUlp) {
                penyulangInfo.textContent = `Menampilkan ${matchCount} penyulang ter-filter berdasarkan Gardu Induk / ULP terpilih.`;
            } else {
                penyulangInfo.textContent = `Menampilkan seluruh ${matchCount} penyulang aktif.`;
            }
        }
    }

    giSelect.addEventListener('change', filterPenyulang);
    ulpSelect.addEventListener('change', filterPenyulang);
});
</script>
<?= $this->endSection() ?>
