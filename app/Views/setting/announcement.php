<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <!-- Header Page -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-bullhorn text-warning me-2"></i> Pengaturan Sistem & Motivasi Harian
            </h3>
            <p class="text-muted small mb-0">Kelola teks motivasi, running ticker, pesan dashboard, dan judul aplikasi secara permanen di database.</p>
        </div>
        <div>
            <a href="<?= site_url('dashboard') ?>" class="btn btn-outline-secondary btn-sm rounded-pill shadow-xs">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <!-- Alert Success / Error -->
    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-xs mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= session()->getFlashdata('success') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- REALTIME LIVE PREVIEW BANNER (Target 7) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #003f8a 0%, #005eb8 100%);">
        <div class="card-header py-2 px-3 bg-transparent border-0 d-flex align-items-center justify-content-between">
            <span class="badge bg-warning text-dark font-weight-bold" style="font-size: 11px;">
                <i class="fas fa-desktop me-1"></i> LIVE REALTIME PREVIEW
            </span>
            <span class="badge bg-light text-dark opacity-90" style="font-size: 10px;">WIB: <?= date('H:i:s') ?></span>
        </div>
        <div class="card-body p-3 text-white">
            <div class="row align-items-center g-3">
                <div class="col-md-8 col-12">
                    <div class="p-2 px-3 rounded-3 d-flex align-items-center mb-2" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); overflow: hidden; height: 32px;">
                        <i class="fas fa-bullhorn text-warning me-2" style="font-size: 13px; flex-shrink: 0;"></i>
                        <div style="overflow: hidden; flex-grow: 1; display: flex; align-items: center;">
                            <div id="preview-ticker-text" class="fw-bold" style="font-size: 12px; white-space: nowrap;">
                                <?= esc($daily_motivation) ?>
                            </div>
                        </div>
                    </div>
                    <div class="small opacity-90">
                        <strong id="preview-dashboard-title"><?= esc($dashboard_title) ?></strong> - <span id="preview-dashboard-subtitle"><?= esc($dashboard_subtitle) ?></span>
                    </div>
                </div>
                <div class="col-md-4 col-12 text-md-end">
                    <span class="badge bg-success rounded-pill px-3 py-2" style="font-size: 11px;">
                        <i class="fas fa-database me-1"></i> Permanent DB Synced
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Form Pengaturan -->
        <div class="col-lg-7 col-12">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-sliders text-primary me-2"></i> Form Update Pengaturan</h5>
                </div>
                <div class="card-body p-4">
                    <form action="<?= site_url('setting/update-announcement') ?>" method="POST" id="form-system-setting">
                        <?= csrf_field() ?>

                        <!-- Motivasi Harian -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark" for="input_daily_motivation">
                                <i class="fas fa-quote-left text-warning me-1"></i> Kata-Kata Motivasi Harian (Running Ticker)
                            </label>
                            <textarea name="daily_motivation" id="input_daily_motivation" class="form-control form-control-lg" rows="3" placeholder="Ketik kata-kata motivasi atau pengumuman..." required style="font-size: 14px; border-radius: 10px; border: 2px solid #cbd5e1;"><?= esc($daily_motivation) ?></textarea>
                            <div class="form-text small text-muted">Perubahan langsung muncul di running text header seluruh pengguna.</div>
                        </div>

                        <!-- Running Text Tambahan -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark" for="input_running_text">
                                <i class="fas fa-scroll text-info me-1"></i> Running Text Informasi Tambahan
                            </label>
                            <input type="text" name="running_text" id="input_running_text" class="form-control" value="<?= esc($running_text) ?>" style="border-radius: 8px;">
                        </div>

                        <!-- Pesan Dashboard -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark" for="input_dashboard_message">
                                <i class="fas fa-comment-dots text-success me-1"></i> Pesan Sambutan Dashboard
                            </label>
                            <textarea name="dashboard_message" id="input_dashboard_message" class="form-control" rows="2" style="border-radius: 8px;"><?= esc($dashboard_message) ?></textarea>
                        </div>

                        <!-- Judul & Sub Judul -->
                        <div class="row g-2 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark" for="input_dashboard_title">Judul Sistem</label>
                                <input type="text" name="dashboard_title" id="input_dashboard_title" class="form-control" value="<?= esc($dashboard_title) ?>" style="border-radius: 8px;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark" for="input_dashboard_subtitle">Sub Judul Sistem</label>
                                <input type="text" name="dashboard_subtitle" id="input_dashboard_subtitle" class="form-control" value="<?= esc($dashboard_subtitle) ?>" style="border-radius: 8px;">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="reset" class="btn btn-light px-4 rounded-3 fw-bold">Reset</button>
                            <button type="submit" class="btn btn-primary px-4 rounded-3 font-weight-bold shadow-sm">
                                <i class="fas fa-save me-1"></i> Simpan Permanen ke Database
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Riwayat Perubahan & Status Audit (Target 8) -->
        <div class="col-lg-5 col-12">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-history text-secondary me-2"></i> Riwayat & Audit Perubahan</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($auditHistory)): ?>
                        <div class="p-4 text-center text-muted small">Belum ada riwayat perubahan.</div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Pengaturan</th>
                                        <th>Pengubah</th>
                                        <th>Waktu (WIB)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($auditHistory as $row): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold text-dark d-block"><?= esc($row['setting_key']) ?></span>
                                                <small class="text-muted text-truncate d-inline-block" style="max-width: 180px;" title="<?= esc($row['setting_value']) ?>">
                                                    <?= esc($row['setting_value']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary rounded-pill"><?= esc($row['updated_by'] ?: 'Admin') ?></span>
                                            </td>
                                            <td>
                                                <small class="text-muted fw-bold"><?= indo_datetime($row['updated_at']) ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    // Live Realtime Preview (Target 7)
    $('#input_daily_motivation').on('keyup input', function() {
        var text = $(this).val();
        $('#preview-ticker-text').text(text || '...');
    });

    $('#input_dashboard_title').on('keyup input', function() {
        var text = $(this).val();
        $('#preview-dashboard-title').text(text || 'SIDAK TEJO');
    });

    $('#input_dashboard_subtitle').on('keyup input', function() {
        var text = $(this).val();
        $('#preview-dashboard-subtitle').text(text || 'Sistem Data Temuan');
    });
});
</script>
<?= $this->endSection() ?>
