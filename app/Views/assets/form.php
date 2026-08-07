<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php $isEdit = !empty($isEdit); ?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0 font-weight-bold" style="font-family: 'Outfit', sans-serif;">
            <i class="fas <?= $isEdit ? 'fa-pen-to-square text-warning' : 'fa-plus-circle text-primary' ?> me-2"></i> 
            <?= $isEdit ? 'Edit Master Asset: ' . esc($asset['kode_asset']) : 'Tambah Master Asset Baru' ?>
        </h3>
        <a href="<?= site_url('assets') ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-1"></i> Batal</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form action="<?= $isEdit ? site_url('assets/update/' . $asset['id']) : site_url('assets/store') ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Jenis Asset <span class="text-danger">*</span></label>
                        <select name="jenis_asset" class="form-select" required>
                            <?php foreach ($jenisList as $j): ?>
                                <option value="<?= $j ?>" <?= ($isEdit && strcasecmp($asset['jenis_asset'] ?? '', $j) === 0) ? 'selected' : '' ?>><?= $j ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Kode Asset <?= $isEdit ? '<span class="badge bg-secondary ms-1">Read-Only</span>' : '(Otomatis)' ?></label>
                        <input type="text" name="kode_asset" class="form-control font-monospace fw-bold bg-light" value="<?= esc($isEdit ? $asset['kode_asset'] : $generatedKode) ?>" <?= $isEdit ? 'readonly' : 'required' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Nama Asset <span class="text-danger">*</span></label>
                        <input type="text" name="nama_asset" class="form-control" placeholder="Contoh: Trafo 250kVA SDJ-045" value="<?= esc($asset['nama_asset'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">ULP <span class="text-danger">*</span></label>
                        <select name="ulp_id" class="form-select" required>
                            <option value="">-- Pilih ULP --</option>
                            <?php foreach ($ulps as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($isEdit && ($asset['ulp_id'] ?? null) == $u['id']) ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Penyulang</label>
                        <select name="penyulang_id" class="form-select">
                            <option value="">-- Pilih Penyulang --</option>
                            <?php foreach ($penyulangs as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ($isEdit && ($asset['penyulang_id'] ?? null) == $p['id']) ? 'selected' : '' ?>><?= esc($p['nama_penyulang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Section</label>
                        <select name="section_id" class="form-select">
                            <option value="">-- Pilih Section --</option>
                            <?php foreach ($sections as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($isEdit && ($asset['section_id'] ?? null) == $s['id']) ? 'selected' : '' ?>><?= esc($s['nama_section']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Merk / Pabrikan</label>
                        <input type="text" name="merk" class="form-control" placeholder="Contoh: Schneider / ABB / Trafoindo" value="<?= esc($asset['merk'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tipe / Spesifikasi</label>
                        <input type="text" name="type" class="form-control" placeholder="Contoh: Portal 20KV / 630A" value="<?= esc($asset['type'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Nomor Seri</label>
                        <input type="text" name="nomor_seri" class="form-control font-monospace" placeholder="SN-2026-XXXX" value="<?= esc($asset['nomor_seri'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Kapasitas</label>
                        <input type="text" name="kapasitas" class="form-control" placeholder="Contoh: 250 kVA / 630 A" value="<?= esc($asset['kapasitas'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Tahun Instalasi</label>
                        <input type="number" name="tahun_instalasi" class="form-control" value="<?= esc($asset['tahun_instalasi'] ?? date('Y')) ?>">
                    </div>

                    <!-- NEW FIELD: Awal Pemasangan (installation_date) -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Awal Pemasangan <small class="text-muted">(Tanggal Mulai Beroperasi)</small></label>
                        <input type="date" name="installation_date" class="form-control" value="<?= esc($asset['installation_date'] ?? '') ?>" placeholder="YYYY-MM-DD">
                        <small class="text-muted" style="font-size: 11px;">Digunakan untuk presisi umur aset pada Health Score & Digital Twin</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Latitude (GPS)</label>
                        <input type="text" name="latitude" class="form-control font-monospace" placeholder="-7.4478" value="<?= esc($asset['latitude'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Longitude (GPS)</label>
                        <input type="text" name="longitude" class="form-control font-monospace" placeholder="112.7183" value="<?= esc($asset['longitude'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Alamat & Lokasi Fisik</label>
                        <textarea name="lokasi" class="form-control" rows="2" placeholder="Alamat lengkap lokasi fisik aset..."><?= esc($asset['lokasi'] ?? '') ?></textarea>
                    </div>

                    <?php if (!$isEdit): ?>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Status Aset Awal</label>
                        <select name="status" class="form-select">
                            <option value="NORMAL" selected>NORMAL</option>
                            <option value="BERMASALAH">BERMASALAH</option>
                            <option value="CRITICAL">CRITICAL</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="reset" class="btn btn-light px-4 rounded-3">Reset</button>
                    <button type="submit" class="btn <?= $isEdit ? 'btn-warning text-dark' : 'btn-primary' ?> px-4 rounded-3 font-weight-bold">
                        <i class="fas <?= $isEdit ? 'fa-save' : 'fa-plus-circle' ?> me-1"></i> <?= $isEdit ? 'Update Master Asset' : 'Simpan Master Asset' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('select[name="ulp_id"]').change(function() {
        var ulpId = $(this).val();
        if (ulpId) {
            $.get('<?= site_url("api/penyulang-by-ulp/") ?>' + ulpId, function(res) {
                var opt = '<option value="">-- Pilih Penyulang --</option>';
                if (res.data) {
                    res.data.forEach(function(item) {
                        opt += '<option value="' + item.id + '">' + item.nama_penyulang + '</option>';
                    });
                }
                $('select[name="penyulang_id"]').html(opt);
            });
        }
    });
});
</script>
<?= $this->endSection() ?>
