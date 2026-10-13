<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Buat Inspeksi Kubikel Baru<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Buat Inspeksi Kubikel Baru<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="bi bi-clipboard-plus me-2"></i>Buat Inspeksi Kubikel Baru
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
            <form action="<?= site_url('kubikel-inspeksi/store') ?>" method="post">
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
                        <input type="text" name="id_pel" id="id_pel" class="form-control" placeholder="ID Pelanggan / IDPEL" maxlength="100">
                        <div class="form-text">Untuk inspeksi aset pelanggan / Beyond KWH</div>
                    </div>

                    <div class="col-md-3">
                        <label for="tgl_inspeksi" class="form-label fw-medium">Tanggal Inspeksi <span class="text-danger">*</span></label>
                        <input type="date" name="tgl_inspeksi" id="tgl_inspeksi" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="waktu_mulai" class="form-label fw-medium">Waktu Mulai</label>
                        <input type="time" name="waktu_mulai" id="waktu_mulai" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label for="waktu_selesai" class="form-label fw-medium">Waktu Selesai</label>
                        <input type="time" name="waktu_selesai" id="waktu_selesai" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label for="inspector_id" class="form-label fw-medium">Petugas</label>
                        <input type="text" id="inspector_id" class="form-control" value="<?= esc($inspectorId ?? '') ?>" readonly>
                        <input type="hidden" name="inspector_user_id" value="<?= esc($inspectorId ?? '') ?>">
                        <div class="form-text">Otomatis diisi dengan user yang login</div>
                    </div>

                    <div class="col-12">
                        <label for="catatan_umum" class="form-label fw-medium">Catatan Umum</label>
                        <textarea name="catatan_umum" id="catatan_umum" class="form-control" rows="3" placeholder="Catatan tambahan untuk inspeksi ini..."></textarea>
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
    });
</script>
<?= $this->endSection() ?>