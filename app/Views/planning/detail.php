<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Detail Planning Inspeksi<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Detail Snapshot Asset Planning<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('planning') ?>">Planning Inspeksi</a></li>
<li class="breadcrumb-item active">Detail Snapshot</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-4 col-12 mb-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-primary text-white py-3 rounded-top-4">
                <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-info-circle me-2"></i> Informasi Planning</h5>
            </div>
            <div class="card-body p-4">
                <h5 class="fw-bold text-dark mb-1"><?= esc($planning['title']) ?></h5>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill font-monospace mb-3">
                    <?= esc($planning['nomor_planning']) ?>
                </span>

                <div class="bg-light p-3 rounded-3 mb-3 small">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Target Asset:</span>
                        <strong class="text-dark"><?= esc($planning['total_assets']) ?> Asset (<?= esc($planning['jenis_asset']) ?>)</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Status Planning:</span>
                        <strong class="text-primary"><?= esc(strtoupper($planning['status'])) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Jadwal:</span>
                        <strong class="text-dark"><?= date('d M Y', strtotime($planning['scheduled_date'] ?: $planning['created_at'])) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Dipublikasikan:</span>
                        <strong class="text-dark"><?= $planning['published_at'] ? date('d M Y H:i', strtotime($planning['published_at'])) : '-' ?></strong>
                    </div>
                </div>

                <a href="<?= site_url('planning') ?>" class="btn btn-outline-secondary w-100 rounded-pill">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar Planning
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-8 col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 border-bottom rounded-top-4">
                <h5 class="card-title mb-0 font-weight-bold text-dark"><i class="fas fa-boxes-stacked text-primary me-2"></i> Snapshot Asset Terencana</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted uppercase small">
                            <tr>
                                <th class="ps-4">Urutan</th>
                                <th>Kode Asset</th>
                                <th>Nama Asset</th>
                                <th>Jenis</th>
                                <th>Lokasi</th>
                                <th class="pe-4 text-end">Status Asset</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assets)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Belum ada asset snapshot pada planning ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assets as $ast): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-muted">#<?= esc($ast['sequence_no']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-2 py-1 font-monospace fw-bold">
                                                <?= esc($ast['kode_asset']) ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-dark"><?= esc($ast['nama_asset']) ?></td>
                                        <td><span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 rounded-pill"><?= esc($ast['jenis_asset']) ?></span></td>
                                        <td class="small text-muted"><?= esc($ast['lokasi'] ?: 'Jalur Feeder') ?></td>
                                        <td class="pe-4 text-end">
                                            <span class="badge bg-success-subtle text-success px-2 py-1 rounded-pill"><?= esc($ast['asset_status'] ?: 'AKTIF') ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
