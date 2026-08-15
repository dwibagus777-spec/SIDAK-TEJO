<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <!-- Header Page -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-file-import text-success me-2"></i> IMPORT MASTER ASSET PLN
            </h3>
            <p class="text-muted small mb-0">Unggah berkas spreadsheet Excel (.xlsx) untuk menambahkan data asset PLN secara masal</p>
        </div>
        <div>
            <a href="<?= site_url('master-assets') ?>" class="btn btn-outline-secondary btn-sm rounded-pill font-weight-bold shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Master Asset
            </a>
        </div>
    </div>

    <!-- Alert Success & Error -->
    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-circle-check fs-4 me-3 text-success"></i>
                <div>
                    <h6 class="fw-bold mb-0">Berhasil!</h6>
                    <p class="mb-0 small"><?= session()->getFlashdata('success') ?></p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-circle-exclamation fs-4 me-3 text-danger"></i>
                <div>
                    <h6 class="fw-bold mb-0">Gagal / Peringatan!</h6>
                    <p class="mb-0 small"><?= session()->getFlashdata('error') ?></p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Import Summary Card (If Flash Data Available) -->
    <?php 
    $summary = session()->getFlashdata('import_summary');
    if (!empty($summary)): 
    ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                    <i class="fas fa-chart-pie text-primary me-2"></i> Ringkasan Hasil Import
                </h6>
                <?php if (!empty($summary['error_excel_path'])): ?>
                    <a href="<?= site_url('master-assets/download-error-report?file=' . urlencode($summary['error_excel_path'])) ?>" class="btn btn-danger btn-sm rounded-pill font-weight-bold shadow-sm">
                        <i class="fas fa-file-excel me-1"></i> Unduh Laporan Baris Error (.xlsx)
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center mb-3">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted small d-block font-weight-bold">TOTAL DATA DIPROSES</span>
                            <h4 class="fw-bold text-dark mb-0"><?= number_format($summary['total']) ?></h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25">
                            <span class="text-success small d-block font-weight-bold">BERHASIL DIIMPORT</span>
                            <h4 class="fw-bold text-success mb-0"><?= number_format($summary['inserted']) ?></h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-danger bg-opacity-10 rounded-3 border border-danger border-opacity-25">
                            <span class="text-danger small d-block font-weight-bold">GAGAL / SKIPPED</span>
                            <h4 class="fw-bold text-danger mb-0"><?= number_format($summary['failed']) ?></h4>
                        </div>
                    </div>
                </div>

                <?php if (!empty($summary['errors'])): ?>
                    <h6 class="fw-bold text-danger mb-2"><i class="fas fa-list-check me-1"></i> Rincian Baris Gagal / Duplikat:</h6>
                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm table-bordered table-striped align-middle mb-0" style="font-size: 12px;">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 80px;" class="text-center">Baris Excel</th>
                                    <th>Kode Asset</th>
                                    <th>Nama Asset</th>
                                    <th>Alasan Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summary['errors'] as $err): ?>
                                    <tr>
                                        <td class="text-center font-monospace fw-bold text-danger">Baris <?= $err['baris'] ?></td>
                                        <td class="font-monospace fw-bold"><?= esc($err['kode_asset']) ?></td>
                                        <td><?= esc($err['nama_asset']) ?></td>
                                        <td class="text-danger font-weight-bold"><?= esc($err['alasan']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Form Upload & Step Instructions Grid -->
    <div class="row g-4">
        <!-- Form Upload -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-primary d-flex align-items-center">
                        <i class="fas fa-cloud-arrow-up text-primary me-2"></i> Form Unggah Berkas Excel
                    </h6>
                </div>
                <div class="card-body p-4">
                    <form action="<?= site_url('master-assets/import-process') ?>" method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>

                        <div class="mb-4 text-center p-4 border border-2 border-dashed rounded-4 bg-light" id="drop-area">
                            <i class="fas fa-file-excel text-success display-4 mb-3 d-block"></i>
                            <h6 class="fw-bold text-dark mb-1">Pilih Berkas Excel / CSV (.xlsx / .xls / .csv)</h6>
                            <p class="text-muted small mb-3">Maksimum ukuran berkas 10 MB</p>

                            <input type="file" name="file_excel" id="file_excel" class="form-control d-none" accept=".xlsx, .xls, .csv" required>
                            
                            <button type="button" class="btn btn-outline-primary rounded-pill px-4 font-weight-bold shadow-sm" onclick="document.getElementById('file_excel').click();">
                                <i class="fas fa-folder-open me-1"></i> Cari Berkas
                            </button>
                            <div id="selected-file-name" class="mt-3 font-monospace small fw-bold text-success"></div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary py-2 rounded-pill font-weight-bold shadow-sm">
                                <i class="fas fa-upload me-1"></i> Mulai Proses Import
                            </button>
                            <button type="button" class="btn btn-outline-success py-2 rounded-pill font-weight-bold" data-bs-toggle="modal" data-bs-target="#modalDownloadTemplate">
                                <i class="fas fa-download me-1"></i> Unduh Format Template Excel (.xlsx)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Instructions & Guidelines -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                        <i class="fas fa-circle-info text-info me-2"></i> Panduan & Aturan Format Batch
                    </h6>
                </div>
                <div class="card-body p-4">
                    <ul class="list-group list-group-flush border-0 small">
                        <li class="list-group-item bg-transparent px-0 py-2 border-bottom">
                            <span class="badge bg-primary me-2">1</span> Gunakan template resmi dengan tombol <strong>"Unduh Format Template Excel"</strong>.
                        </li>
                        <li class="list-group-item bg-transparent px-0 py-2 border-bottom">
                            <span class="badge bg-primary me-2">2</span> <strong>Kode Asset</strong> akan <strong>digenerate otomatis oleh sistem</strong> secara unik untuk setiap asset.
                        </li>
                        <li class="list-group-item bg-transparent px-0 py-2 border-bottom">
                            <span class="badge bg-primary me-2">3</span> NAMA ULP, Penyulang, dan Section disesuaikan dengan nama yang terdaftar di database.
                        </li>
                        <li class="list-group-item bg-transparent px-0 py-2 border-bottom">
                            <span class="badge bg-primary me-2">4</span> Kolom Status diisi salah satu: <code>NORMAL</code>, <code>BERMASALAH</code>, atau <code>CRITICAL</code>.
                        </li>
                        <li class="list-group-item bg-transparent px-0 py-2">
                            <span class="badge bg-success me-2">5</span> Jika terdapat baris berformat salah, sistem <strong>tetap memasukkan baris valid lainnya</strong> tanpa menggagalkan seluruh import.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?= view('assets/modal_download_template', ['ulps' => $ulps ?? []]) ?>

<script>
document.getElementById('file_excel').addEventListener('change', function(e) {
    if (e.target.files && e.target.files[0]) {
        document.getElementById('selected-file-name').innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Berkas Dipilih: ' + e.target.files[0].name;
    }
});
</script>
<?= $this->endSection() ?>
