<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">
            <i class="fas fa-plus-circle text-primary me-2"></i> Terbitkan Dokumen Resmi Baru
        </h3>
        <a href="<?= site_url('documents') ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form method="POST" action="<?= site_url('documents/store') ?>">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-6 col-12">
                        <label class="form-label fw-bold text-secondary small">Jenis Dokumen <span class="text-danger">*</span></label>
                        <select name="jenis_dokumen" class="form-select form-select-sm" required>
                            <option value="">-- Pilih Jenis Dokumen --</option>
                            <option value="Berita Acara">Berita Acara</option>
                            <option value="Laporan Inspeksi">Laporan Inspeksi</option>
                            <option value="Laporan Harian">Laporan Harian</option>
                            <option value="Laporan Mingguan">Laporan Mingguan</option>
                            <option value="Laporan Bulanan">Laporan Bulanan</option>
                            <option value="Laporan Tahunan">Laporan Tahunan</option>
                            <option value="Surat Tugas">Surat Tugas</option>
                            <option value="Work Order">Work Order</option>
                            <option value="Rekap Temuan">Rekap Temuan</option>
                            <option value="Rekap Penyelesaian">Rekap Penyelesaian</option>
                        </select>
                    </div>
                    <div class="col-md-6 col-12">
                        <label class="form-label fw-bold text-secondary small">Status Awal</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="DRAFT">DRAFT</option>
                            <option value="REVIEW">REVIEW (Kirim ke Supervisor)</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary small">Judul Dokumen <span class="text-danger">*</span></label>
                        <input type="text" name="judul_dokumen" class="form-control form-control-sm" placeholder="Contoh: Berita Acara Inspeksi Penyulang SDJ01 - Juli 2026" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold text-secondary small">Isi Dokumen (HTML)</label>
                        <textarea name="content_html" class="form-control form-control-sm" rows="10" placeholder="Tulis konten dokumen resmi di sini..."></textarea>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill font-weight-bold px-4">
                        <i class="fas fa-file-circle-plus me-1"></i> Terbitkan Dokumen & Generate QR + SHA256
                    </button>
                    <a href="<?= site_url('documents') ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
