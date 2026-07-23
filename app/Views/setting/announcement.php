<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-xl">
    <div class="row row-cards justify-content-center">
        <div class="col-lg-8">
            
            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-3" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?= session()->getFlashdata('success') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-3" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?= session()->getFlashdata('error') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Live Preview Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #004D4F 0%, #007275 100%); text-shadow: 0 1px 2px rgba(0,0,0,0.2);">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-warning text-dark font-weight-bold px-2 py-1 me-2" style="border-radius: 6px; font-size: 11px;">
                            <i class="fas fa-eye me-1"></i> PREVIEW HASIL TEKS BERJALAN
                        </span>
                        <small class="text-white-50" style="font-size: 11px;">Tampilan running text di bagian atas header aplikasi</small>
                    </div>
                    <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.15); border: 1px dashed rgba(255,255,255,0.3); overflow: hidden; white-space: nowrap;">
                        <div class="d-inline-block font-weight-bold text-white running-announcement-text-target" style="animation: tickerAnimation 20s linear infinite; font-size: 13px; letter-spacing: 0.3px;">
                            <?= esc($announcement) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Card -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-0">
                    <h3 class="card-title text-dark font-weight-bold m-0" style="font-size: 1.25rem;">
                        <i class="fas fa-bullhorn text-warning me-2"></i> Edit Kata-Kata Motivasi Harian
                    </h3>
                </div>
                <div class="card-body p-4">
                    <form action="<?= site_url('setting/update-announcement') ?>" method="POST">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label font-weight-bold text-dark">
                                Pesan Motivasi Harian <span class="text-danger">*</span>
                            </label>
                            <textarea name="message" class="form-control form-control-lg" rows="4" placeholder="Ketik kata-kata motivasi atau pengumuman harian di sini..." required style="border-radius: 8px; font-size: 14px; line-height: 1.6; border: 2px solid #e2e8f0;"><?= esc($announcement) ?></textarea>
                            <div class="form-text text-muted mt-2" style="font-size: 12px;">
                                <i class="fas fa-info-circle me-1 text-primary"></i> Kata-kata ini akan tampil secara bergerak (running text) pada header atas untuk semua pengguna yang sedang membuka SIDAK TEJO.
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                            <a href="<?= site_url('dashboard') ?>" class="btn btn-outline-secondary px-4" style="border-radius: 8px;">
                                <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary px-4 font-weight-bold" style="background-color: #004D4F; border-color: #004D4F; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,77,79,0.25);">
                                <i class="fas fa-paper-plane me-1"></i> Simpan & Tampilkan Sekarang
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
<?= $this->endSection() ?>
