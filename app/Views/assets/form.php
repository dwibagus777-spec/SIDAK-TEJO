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
                        <label for="form_jenis_asset" class="form-label fw-bold">Jenis Asset <span class="text-danger">*</span></label>
                        <select id="form_jenis_asset" name="jenis_asset" class="form-select" required>
                            <?php foreach ($jenisList as $j): ?>
                                <option value="<?= $j ?>" <?= ($isEdit && strcasecmp($asset['jenis_asset'] ?? '', $j) === 0) ? 'selected' : '' ?>><?= $j ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="form_kode_asset" class="form-label fw-bold">Kode Asset <?= $isEdit ? '<span class="badge bg-secondary ms-1">Read-Only</span>' : '(Otomatis)' ?></label>
                        <input id="form_kode_asset" type="text" name="kode_asset" class="form-control font-monospace fw-bold bg-light" value="<?= esc($isEdit ? $asset['kode_asset'] : $generatedKode) ?>" <?= $isEdit ? 'readonly' : 'required' ?>>
                    </div>
                    <div class="col-md-4">
                        <label for="form_nama_asset" class="form-label fw-bold">Nama Asset <span class="text-danger">*</span></label>
                        <input id="form_nama_asset" type="text" name="nama_asset" class="form-control" placeholder="Contoh: Trafo 250kVA SDJ-045" value="<?= esc($asset['nama_asset'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label for="form_ulp_id" class="form-label fw-bold">ULP <span class="text-danger">*</span></label>
                        <select id="form_ulp_id" name="ulp_id" class="form-select" required>
                            <option value="">-- Pilih ULP --</option>
                            <?php foreach ($ulps as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($isEdit && ($asset['ulp_id'] ?? null) == $u['id']) ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="form_penyulang_id" class="form-label fw-bold">Penyulang</label>
                        <select id="form_penyulang_id" name="penyulang_id" class="form-select">
                            <option value="">-- Pilih Penyulang --</option>
                            <?php foreach ($penyulangs as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ($isEdit && ($asset['penyulang_id'] ?? null) == $p['id']) ? 'selected' : '' ?>><?= esc($p['nama_penyulang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="form_section_id" class="form-label fw-bold">Section</label>
                        <select id="form_section_id" name="section_id" class="form-select">
                            <option value="">-- Pilih Section --</option>
                            <?php foreach ($sections as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($isEdit && ($asset['section_id'] ?? null) == $s['id']) ? 'selected' : '' ?>><?= esc($s['nama_section']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- RELEASE v2.1.0: Network Topology & Construction Specs -->
                    <div class="col-md-4">
                        <label for="form_parent_asset_id" class="form-label fw-bold">Parent Asset / Induk Topologi <small class="text-muted">(Opsional)</small></label>
                        <select id="form_parent_asset_id" name="parent_asset_id" class="form-select">
                            <option value="">-- Tanpa Induk (Standalone/Top Root) --</option>
                            <?php if (!empty($parentAssets)): ?>
                                <?php foreach ($parentAssets as $pa): ?>
                                    <?php if ($isEdit && $pa['id'] == $asset['id']) continue; ?>
                                    <option value="<?= $pa['id'] ?>" <?= ($isEdit && ($asset['parent_asset_id'] ?? null) == $pa['id']) ? 'selected' : '' ?>>
                                        [<?= esc($pa['kode_asset']) ?>] <?= esc($pa['nama_asset']) ?> (<?= esc($pa['jenis_asset']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="form_construction_type_id" class="form-label fw-bold">Standar Konstruksi PLN <small class="text-muted">(Opsional)</small></label>
                        <select id="form_construction_type_id" name="construction_type_id" class="form-select">
                            <option value="">-- Pilih Standar Konstruksi PLN --</option>
                            <?php if (!empty($constructionTypes)): ?>
                                <?php foreach ($constructionTypes as $ct): ?>
                                    <option value="<?= $ct['id'] ?>" <?= ($isEdit && ($asset['construction_type_id'] ?? null) == $ct['id']) ? 'selected' : '' ?>>
                                        <?= esc($ct['code']) ?> - <?= esc($ct['name']) ?> (<?= esc($ct['network_type']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="form_merk" class="form-label fw-bold">Merk / Pabrikan</label>
                        <input id="form_merk" type="text" name="merk" class="form-control" placeholder="Contoh: Schneider / ABB / Trafoindo" value="<?= esc($asset['merk'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="form_type" class="form-label fw-bold">Tipe / Spesifikasi</label>
                        <input id="form_type" type="text" name="type" class="form-control" placeholder="Contoh: Portal 20KV / 630A" value="<?= esc($asset['type'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="form_nomor_seri" class="form-label fw-bold">Nomor Seri</label>
                        <input id="form_nomor_seri" type="text" name="nomor_seri" class="form-control font-monospace" placeholder="SN-2026-XXXX" value="<?= esc($asset['nomor_seri'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="form_kapasitas" class="form-label fw-bold">Kapasitas</label>
                        <input id="form_kapasitas" type="text" name="kapasitas" class="form-control" placeholder="Contoh: 250 kVA / 630 A" value="<?= esc($asset['kapasitas'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="form_tahun_instalasi" class="form-label fw-bold">Tahun Instalasi</label>
                        <input id="form_tahun_instalasi" type="number" name="tahun_instalasi" class="form-control" value="<?= esc($asset['tahun_instalasi'] ?? date('Y')) ?>">
                    </div>

                    <!-- NEW FIELD: Awal Pemasangan (installation_date) -->
                    <div class="col-md-4">
                        <label for="form_installation_date" class="form-label fw-bold">Awal Pemasangan <small class="text-muted">(Tanggal Mulai Beroperasi)</small></label>
                        <input id="form_installation_date" type="date" name="installation_date" class="form-control" value="<?= esc($asset['installation_date'] ?? '') ?>" placeholder="YYYY-MM-DD">
                        <small class="text-muted" style="font-size: 11px;">Digunakan untuk presisi umur aset pada Health Score & Digital Twin</small>
                    </div>

                    <div class="col-md-4">
                        <label for="form_latitude" class="form-label fw-bold">Latitude (GPS)</label>
                        <input id="form_latitude" type="text" name="latitude" class="form-control font-monospace" placeholder="-7.4478" value="<?= esc($asset['latitude'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="form_longitude" class="form-label fw-bold">Longitude (GPS)</label>
                        <input id="form_longitude" type="text" name="longitude" class="form-control font-monospace" placeholder="112.7183" value="<?= esc($asset['longitude'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <label for="form_lokasi" class="form-label fw-bold">Alamat & Lokasi Fisik</label>
                        <textarea id="form_lokasi" name="lokasi" class="form-control" rows="2" placeholder="Alamat lengkap lokasi fisik aset..."><?= esc($asset['lokasi'] ?? '') ?></textarea>
                    </div>

                    <?php if (!$isEdit): ?>
                    <div class="col-md-4">
                        <label for="form_status" class="form-label fw-bold">Status Aset Awal</label>
                        <select id="form_status" name="status" class="form-select">
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
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function() {
    function initAssetForm() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initAssetForm, 50);
            return;
        }
        var $ = jQuery;
        $(function() {
            $('#form_ulp_id').on('change', function() {
                var ulpId = $(this).val();
                var $penyulang = $('#form_penyulang_id');
                var $section = $('#form_section_id');
                $section.html('<option value="">-- Pilih Section --</option>');
                if (ulpId) {
                    $penyulang.html('<option value="">Sedang memuat...</option>');
                    $.get('<?= site_url("api/penyulang-by-ulp/") ?>' + ulpId, function(res) {
                        var opt = '<option value="">-- Pilih Penyulang --</option>';
                        var list = (res && res.data) ? res.data : (Array.isArray(res) ? res : []);
                        list.forEach(function(item) {
                            opt += '<option value="' + item.id + '">' + item.nama_penyulang + '</option>';
                        });
                        $penyulang.html(opt);
                    }).fail(function() {
                        $penyulang.html('<option value="">Gagal memuat penyulang</option>');
                    });
                } else {
                    $penyulang.html('<option value="">-- Pilih Penyulang --</option>');
                }
            });

            $('#form_penyulang_id').on('change', function() {
                var penyulangId = $(this).val();
                var $section = $('#form_section_id');
                if (penyulangId) {
                    $section.html('<option value="">Sedang memuat...</option>');
                    $.get('<?= site_url("api/section-by-penyulang/") ?>' + penyulangId, function(res) {
                        var opt = '<option value="">-- Pilih Section --</option>';
                        var list = (res && res.data) ? res.data : (Array.isArray(res) ? res : []);
                        list.forEach(function(item) {
                            opt += '<option value="' + item.id + '">' + item.nama_section + '</option>';
                        });
                        $section.html(opt);
                    }).fail(function() {
                        $section.html('<option value="">Gagal memuat section</option>');
                    });
                } else {
                    $section.html('<option value="">-- Pilih Section --</option>');
                }
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAssetForm);
    } else {
        initAssetForm();
    }
})();
</script>
<?= $this->endSection() ?>
