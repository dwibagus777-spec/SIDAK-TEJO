<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Mulai Inspeksi Guided Baru<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Persiapan Inspeksi Lapangan<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('inspections') ?>">Inspeksi Jaringan</a></li>
<li class="breadcrumb-item active">Mulai Baru</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-8 col-12">
        <div class="card shadow border-0 rounded-4">
            <div class="card-header bg-primary text-white py-3 rounded-top-4">
                <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-route me-2"></i> Form Inisialisasi Guided Inspection</h5>
            </div>
            <form action="<?= site_url('inspections/start') ?>" method="post">
                <?= csrf_field() ?>
                <div class="card-body p-4">
                    <p class="text-muted small mb-4">
                        Pilih jenis pekerjaan dan baseline jalur feeder/penyulang. Aplikasi akan secara otomatis menyiapkan urutan pemeriksaan titik aset berdasarkan rute lapangan.
                    </p>

                    <div class="mb-4">
                        <label for="inspection_type_id" class="form-label fw-bold text-dark">Jenis Pekerjaan Inspeksi <span class="text-danger">*</span></label>
                        <select name="inspection_type_id" id="inspection_type_id" class="form-select form-select-lg rounded-3" required>
                            <option value="">-- Pilih Jenis Pekerjaan --</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= $t['id'] ?>">
                                    [<?= esc($t['code']) ?>] <?= esc($t['name']) ?> (Interval: <?= esc($t['default_interval_months']) ?> Bulan)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="baseline_id" class="form-label fw-bold text-dark">Rute Baseline Jaringan / Penyulang <span class="text-danger">*</span></label>
                        <select name="baseline_id" id="baseline_id" class="form-select form-select-lg rounded-3" required>
                            <option value="">-- Pilih Baseline JTM / JTR --</option>
                            <?php foreach ($baselines as $b): ?>
                                <option value="<?= $b['id'] ?>">
                                    <?= esc($b['name']) ?> [<?= esc($b['type']) ?>] &mdash; ULP: <?= esc($b['nama_ulp'] ?? 'UP3') ?>, Penyulang: <?= esc($b['nama_penyulang'] ?? '-') ?> (Total: <?= esc($b['total_assets']) ?> Aset)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="alert alert-info rounded-3 small border-0 mb-0">
                        <i class="fas fa-info-circle me-1"></i> Mode offline-resilient aktif. Seluruh draft pemeriksaan per titik aset akan tersimpan secara otomatis pada memori lokal ponsel Anda.
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
<?= $this->endSection() ?>
