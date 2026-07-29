<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0 font-weight-bold" style="font-family: 'Outfit', sans-serif;">
            <i class="fas fa-plus-circle text-primary me-2"></i> Terbitkan Work Order Baru
        </h3>
        <a href="<?= site_url('work-orders') ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-1"></i> Batal</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form action="<?= site_url('work-orders/store') ?>" method="POST">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Nomor WO (Otomatis)</label>
                        <input type="text" name="nomor_wo" class="form-control font-monospace fw-bold" value="<?= esc($generatedNomor) ?>" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Judul Pekerjaan WO <span class="text-danger">*</span></label>
                        <input type="text" name="judul_wo" class="form-control" placeholder="Contoh: Perbaikan Hotspot Kubikel Outgoing Gardu SDJ-045" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Hubungkan Master Asset PLN</label>
                        <select name="asset_id" class="form-select">
                            <option value="">-- Pilih Asset --</option>
                            <?php foreach ($assets as $a): ?>
                                <option value="<?= $a['id'] ?>" <?= (string)$selectedAsset === (string)$a['id'] ? 'selected' : '' ?>>
                                    [<?= esc($a['kode_asset']) ?>] <?= esc($a['nama_asset']) ?> (<?= esc($a['jenis_asset']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Hubungkan Temuan Inspeksi</label>
                        <select name="temuan_id" class="form-select">
                            <option value="">-- Pilih Temuan --</option>
                            <?php foreach ($temuans as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= (string)$selectedTemuan === (string)$t['id'] ? 'selected' : '' ?>>
                                    [<?= esc($t['nomor_temuan']) ?>] <?= esc($t['detail_temuan']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Assigned To (Petugas / Penanggung Jawab)</label>
                        <input type="text" name="assigned_to" class="form-control" placeholder="Nama Petugas Lapangan...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Tim / Regu Pelaksana</label>
                        <select name="pelaksana" class="form-select">
                            <option value="INSPEKSI">INSPEKSI</option>
                            <option value="HAR GARDU">HAR GARDU</option>
                            <option value="HAR KONSTRUKSI">HAR KONSTRUKSI</option>
                            <option value="HAR ROW">HAR ROW</option>
                            <option value="HAR CRANE">HAR CRANE</option>
                            <option value="PDKB">PDKB</option>
                            <option value="YANTEK">YANTEK</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Prioritas WO</label>
                        <select name="prioritas" class="form-select">
                            <option value="MEDIUM" selected>MEDIUM</option>
                            <option value="HIGH">HIGH</option>
                            <option value="EMERGENCY">EMERGENCY</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Target Tanggal Selesai</label>
                        <input type="datetime-local" name="target_selesai" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Status WO Awal</label>
                        <select name="status" class="form-select">
                            <option value="OPEN" selected>OPEN</option>
                            <option value="ASSIGNED">ASSIGNED</option>
                            <option value="PROGRESS">PROGRESS</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Detail Perintah Kerja (WO)</label>
                        <textarea name="detail_wo" class="form-control" rows="3" placeholder="Instruksi kerja lengkap untuk petugas di lapangan..."></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="reset" class="btn btn-light px-4 rounded-3">Reset</button>
                    <button type="submit" class="btn btn-primary px-4 rounded-3 font-weight-bold">
                        <i class="fas fa-paper-plane me-1"></i> Terbitkan Work Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
