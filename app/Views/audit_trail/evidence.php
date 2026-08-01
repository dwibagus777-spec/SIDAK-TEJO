<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Digital Evidence Hub<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Digital Evidence & Time Machine History #<?= esc($temuan['nomor_temuan']) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Phase 39 Digital Evidence Hub System */
    .evidence-container {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .evidence-card {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        padding: 24px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
    }

    /* Vertical Mobile & Web Timeline */
    .timeline-ev-item {
        position: relative;
        padding-left: 30px;
        padding-bottom: 20px;
        border-left: 2px solid #0284c7;
    }
    .timeline-ev-item:last-child {
        border-left: 2px solid transparent;
        padding-bottom: 0;
    }
    .timeline-ev-item::before {
        content: '';
        position: absolute;
        left: -7px;
        top: 2px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #0284c7;
        border: 2px solid #ffffff;
    }
</style>

<div class="evidence-container container-fluid py-3">

    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center">
                <i class="fas fa-file-contract text-warning me-2 fs-3"></i> DIGITAL EVIDENCE HUB
                <span class="badge bg-success ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">VERIFIED EVIDENCE</span>
            </h3>
            <p class="text-muted small mb-0">Nomor Temuan: <strong><?= esc($temuan['nomor_temuan']) ?></strong> &middot; ULP: <strong><?= esc($temuan['nama_ulp'] ?? 'Sidoarjo') ?></strong></p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="https://www.google.com/maps/search/?api=1&query=<?= $temuan['latitude'] ?>,<?= $temuan['longitude'] ?>" target="_blank" class="btn btn-outline-danger btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-location-dot me-1"></i> Buka Google Maps
            </a>
            <a href="<?= site_url('audit-log') ?>" class="btn btn-secondary btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Log
            </a>
        </div>
    </div>

    <!-- Evidence Photos Box -->
    <div class="row g-4 mb-4">
        <div class="col-lg-7 col-12">
            <div class="evidence-card h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-camera text-primary me-2"></i> Foto Temuan Stempel Watermark</h5>
                <?php
                    $photos = [];
                    if (!empty($temuan['foto'])) {
                        $decoded = json_decode((string)($temuan['foto'] ?? ''), true);
                        $photos = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', (string)$temuan['foto'])));
                    }
                ?>
                <div class="row g-2">
                    <?php foreach ($photos as $photo):
                        $url = get_photo_url($photo, $temuan['foto_path'] ?? 'foto/');
                    ?>
                        <div class="col-6">
                            <div class="border rounded overflow-hidden shadow-sm bg-dark text-center" style="height: 180px;">
                                <img src="<?= $url ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;" alt="Foto Evidence">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Time Machine History Timeline -->
        <div class="col-lg-5 col-12">
            <div class="evidence-card h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-clock-rotate-left text-warning me-2"></i> Time Machine Version History</h5>
                <?php if (empty($versions)): ?>
                    <p class="text-muted small">Belum ada versi riwayat tercatat.</p>
                <?php else: ?>
                    <?php foreach ($versions as $v): ?>
                        <div class="timeline-ev-item">
                            <span class="badge bg-purple text-white mb-1" style="background:#7e22ce;">Versi #<?= $v['version_number'] ?> &middot; <?= esc($v['aktivitas']) ?></span>
                            <span class="fw-bold text-dark d-block small"><?= esc($v['username']) ?> (<?= esc(strtoupper($v['role'])) ?>)</span>
                            <small class="text-muted d-block"><?= esc($v['created_at']) ?></small>
                            <?php if (!empty($v['diff_json'])): ?>
                                <div class="mt-2 p-2 bg-light rounded border font-monospace" style="font-size: 10px;">
                                    <strong>Perubahan Data:</strong>
                                    <pre class="mb-0 mt-1"><?= esc($v['diff_json']) ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
<?= $this->endSection() ?>
