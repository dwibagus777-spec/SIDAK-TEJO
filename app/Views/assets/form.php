<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0 font-weight-bold" style="font-family: 'Outfit', sans-serif;">
            <i class="fas fa-plus-circle text-primary me-2"></i> Tambah Master Asset Baru
        </h3>
        <a href="<?= site_url('assets') ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-1"></i> Batal</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form action="<?= site_url('assets/store') ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Jenis Asset <span class="text-danger">*</span></label>
                        <select name="jenis_asset" class="form-select" required>
                            <?php foreach ($jenisList as $j): ?>
                                <option value="<?= $j ?>"><?= $j ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Kode Asset (Otomatis)</label>
                        <input type="text" name="kode_asset" class="form-control font-monospace fw-bold" value="<?= esc($generatedKode) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Nama Asset <span class="text-danger">*</span></label>
                        <input type="text" name="nama_asset" class="form-control" placeholder="Contoh: Trafo 250kVA SDJ-045" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">ULP <span class="text-danger">*</span></label>
                        <select name="ulp_id" class="form-select" required>
                            <option value="">-- Pilih ULP --</option>
                            <?php foreach ($ulps as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= esc($u['nama_ulp']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Penyulang</label>
                        <select name="penyulang_id" class="form-select">
                            <option value="">-- Pilih Penyulang --</option>
                            <?php foreach ($penyulangs as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= esc($p['nama_penyulang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Section</label>
                        <select name="section_id" class="form-select">
                            <option value="">-- Pilih Section --</option>
                            <?php foreach ($sections as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= esc($s['nama_section']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Merk / Pabrikan</label>
                        <input type="text" name="merk" class="form-control" placeholder="Contoh: Schneider / ABB / Trafoindo">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tipe / Spesifikasi</label>
                        <input type="text" name="type" class="form-control" placeholder="Contoh: Portal 20KV / 630A">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Nomor Seri</label>
                        <input type="text" name="nomor_seri" class="form-control font-monospace" placeholder="SN-2026-XXXX">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Kapasitas</label>
                        <input type="text" name="kapasitas" class="form-control" placeholder="Contoh: 250 kVA / 630 A">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Tahun Instalasi</label>
                        <input type="number" name="tahun_instalasi" class="form-control" value="<?= date('Y') ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Alamat & Lokasi Fisik</label>
                        <textarea name="lokasi" class="form-control" rows="2" placeholder="Alamat lengkap lokasi fisik aset..."></textarea>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Latitude (GPS)</label>
                        <input type="text" name="latitude" class="form-control font-monospace" placeholder="-7.4478">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Longitude (GPS)</label>
                        <input type="text" name="longitude" class="form-control font-monospace" placeholder="112.7183">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Status Aset Awal</label>
                        <select name="status" class="form-select">
                            <option value="NORMAL" selected>NORMAL</option>
                            <option value="BERMASALAH">BERMASALAH</option>
                            <option value="CRITICAL">CRITICAL</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="reset" class="btn btn-light px-4 rounded-3">Reset</button>
                    <button type="submit" class="btn btn-primary px-4 rounded-3 font-weight-bold">
                        <i class="fas fa-save me-1"></i> Simpan Master Asset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
