<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<style>
:root {
    --cc-bg: #0b111e;
    --cc-card-bg: rgba(18, 26, 43, 0.85);
    --cc-border: rgba(45, 62, 92, 0.6);
    --cc-accent-cyan: #00e5ff;
    --cc-accent-amber: #ffb300;
    --cc-accent-rose: #ff3366;
    --cc-accent-emerald: #00e676;
}

.queue-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.queue-card {
    background: var(--cc-card-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--cc-border);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
}
</style>

<div class="content-wrapper">
    <div class="queue-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-tasks text-warning mr-2"></i>Supervisor Review Queue & Advisory Inbox
                </h2>
                <small class="text-muted">
                    Daftar rekomendasi intelijen pencegahan (M-05) yang memerlukan peninjauan dan otorisasi Pengawas
                </small>
            </div>
            <div>
                <a href="<?= base_url('preventive-intelligence') ?>" class="btn btn-outline-info btn-sm">
                    <i class="fas fa-radar mr-1"></i> Command Center Radar
                </a>
            </div>
        </div>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success bg-success text-white py-2 mb-3">
                <i class="fas fa-check-circle mr-1"></i> <?= session()->getFlashdata('success') ?>
            </div>
        <?php endif; ?>

        <!-- Filter & Table Card -->
        <div class="queue-card">
            <div class="table-responsive">
                <table class="table table-dark table-hover table-bordered small mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase">
                            <th>Snapshot Code</th>
                            <th>Penyulang & Seksi</th>
                            <th class="text-center">Risk Tier</th>
                            <th class="text-center">Attention Score</th>
                            <th class="text-center">Status Tata Kelola</th>
                            <th>Waktu Evaluasi</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($snapshots)): ?>
                            <?php foreach ($snapshots as $snp): ?>
                                <tr>
                                    <td class="font-weight-bold text-info">
                                        <code><?= esc($snp['snapshot_code']) ?></code>
                                    </td>
                                    <td>
                                        <strong class="text-white"><?= esc($snp['feeder_name'] ?? 'BALUNG') ?></strong>
                                        <div class="text-muted font-size-xs"><?= esc($snp['section_name'] ?? 'BALUNG-03') ?> &bull; <?= esc($snp['asset_code'] ?? 'TIANG') ?></div>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                            $tier = $snp['preventive_risk_tier'] ?? 'HIGH_RISK_RECURRENCE';
                                            $tBadge = match($tier) {
                                                'CRITICAL_PREVENTIVE_ATTENTION' => 'badge-danger',
                                                'HIGH_RISK_RECURRENCE'          => 'badge-warning',
                                                'MODERATE_DEGRADATION'          => 'badge-info',
                                                default                         => 'badge-success',
                                            };
                                        ?>
                                        <span class="badge <?= $tBadge ?>"><?= str_replace('_', ' ', $tier) ?></span>
                                    </td>
                                    <td class="text-center font-weight-bold text-warning">
                                        <?= number_format((float)($snp['correlation_confidence_score'] ?? 0.61), 2) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                            $status = $snp['governance_status'] ?? 'ADVISORY_PROPOSED';
                                            $sBadge = match($status) {
                                                'SUPERVISOR_REVIEWED' => 'badge-info',
                                                'MITIGATION_PLANNED'  => 'badge-success',
                                                'ARCHIVED'            => 'badge-secondary',
                                                default               => 'badge-warning',
                                            };
                                        ?>
                                        <span class="badge <?= $sBadge ?> text-uppercase"><?= str_replace('_', ' ', $status) ?></span>
                                    </td>
                                    <td class="text-muted">
                                        <?= date('d M Y H:i', strtotime($snp['evaluation_timestamp'] ?? $snp['created_at'])) ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= base_url('preventive-intelligence/workspace/' . $snp['id']) ?>" class="btn btn-sm btn-warning font-weight-bold">
                                            <i class="fas fa-search mr-1"></i> Review Workspace
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-check-circle text-success mb-2 d-block" style="font-size: 2rem;"></i>
                                    Tidak ada antrean advisory yang memerlukan review saat ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?= $this->endSection() ?>
