<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Asset Health Detail — <?= esc($asset['kode_asset'] ?? '') ?><?= $this->endSection() ?>
<?= $this->section('page_title') ?>SIDAK TEJO Asset Health Intelligence — 10-Factor Breakdown<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    .ah-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: 20px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
    }
    .badge-cat-EXCELLENT { background-color: #10b981; color: #fff; }
    .badge-cat-GOOD { background-color: #3b82f6; color: #fff; }
    .badge-cat-FAIR { background-color: #f59e0b; color: #000; }
    .badge-cat-POOR { background-color: #f97316; color: #fff; }
    .badge-cat-CRITICAL { background-color: #ef4444; color: #fff; }
</style>

<div class="container-fluid py-3">
    <!-- Back Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="<?= base_url('asset-health') ?>" class="btn btn-outline-secondary btn-sm rounded-pill me-2">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Overview
            </a>
            <h3 class="fw-bold mb-0 text-dark d-inline-block align-middle">
                <?= esc($asset['nama_asset'] ?? 'Asset Detail') ?> 
                <span class="text-muted fs-5">(<?= esc($asset['kode_asset'] ?? '') ?>)</span>
            </h3>
        </div>
        <div>
            <span class="badge bg-dark font-monospace p-2">Snapshot: <?= esc($index['topology_snapshot_id'] ?? 'TOPOLOGY-20260928-245-81c43a7f') ?></span>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <!-- Asset Metadata -->
        <div class="col-lg-4 col-md-6 col-12">
            <div class="ah-card p-4 h-100">
                <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 11px;">Identity & Location Context</h6>
                <table class="table table-sm table-borderless mb-0" style="font-size: 13px;">
                    <tr><td class="text-muted">Jenis Asset</td><td class="fw-semibold"><?= esc($asset['jenis_asset'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">ULP</td><td class="fw-semibold"><?= esc($asset['nama_ulp'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Penyulang</td><td class="fw-semibold text-primary"><?= esc($asset['nama_penyulang'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Section</td><td class="fw-semibold"><?= esc($asset['nama_section'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Tahun Instalasi</td><td class="fw-semibold"><?= esc($asset['tahun_instalasi'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Kapasitas</td><td class="fw-semibold"><?= esc($asset['kapasitas'] ?? '-') ?></td></tr>
                </table>
            </div>
        </div>

        <!-- AHI Score & Category -->
        <div class="col-lg-4 col-md-6 col-12">
            <div class="ah-card p-4 h-100 text-center d-flex flex-column justify-content-center align-items-center">
                <h6 class="fw-bold text-muted text-uppercase mb-2" style="font-size: 11px;">Asset Health Index Score</h6>
                <?php $cat = $index['health_category'] ?? 'FAIR'; ?>
                <div class="display-3 fw-bold mb-2 font-monospace" style="color: <?= match($cat) { 'EXCELLENT'=>'#10b981', 'GOOD'=>'#3b82f6', 'FAIR'=>'#f59e0b', 'POOR'=>'#f97316', default=>'#ef4444' } ?>;">
                    <?= number_format((float)($index['ahi_score'] ?? 0), 2) ?>
                </div>
                <div>
                    <span class="badge badge-cat-<?= $cat ?> px-3 py-2 fs-6 rounded-pill me-1"><?= $cat ?></span>
                    <span class="badge bg-secondary px-3 py-2 fs-6 rounded-pill">Risk: <?= esc($index['failure_risk_level'] ?? 'LOW') ?></span>
                </div>
                <small class="text-muted mt-3">Calculated: <?= esc($index['calculated_at'] ?? '-') ?></small>
            </div>
        </div>

        <!-- SHA-256 Audit Trail Fingerprints -->
        <div class="col-lg-4 col-12">
            <div class="ah-card p-4 h-100">
                <h6 class="fw-bold text-muted text-uppercase mb-3" style="font-size: 11px;">SHA-256 Audit Fingerprints</h6>
                <div class="mb-3">
                    <small class="text-muted d-block font-monospace">input_fingerprint</small>
                    <code class="text-break bg-light p-1 rounded font-monospace" style="font-size: 11px;"><?= esc($index['input_fingerprint'] ?? 'N/A') ?></code>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block font-monospace">factor_fingerprint</small>
                    <code class="text-break bg-light p-1 rounded font-monospace" style="font-size: 11px;"><?= esc($index['factor_fingerprint'] ?? 'N/A') ?></code>
                </div>
                <div class="d-flex justify-content-between text-muted" style="font-size: 11px;">
                    <span>Profile: <strong><?= esc($index['weight_profile_id'] ?? 'WP-1.0') ?></strong></span>
                    <span>Algo: <strong><?= esc($index['algorithm_version'] ?? 'AHI-1.0.0') ?></strong></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 10-FACTOR EVIDENCE BREAKDOWN TABLE -->
    <div class="ah-card p-4 mb-4">
        <h5 class="fw-bold text-dark mb-3"><i class="fas fa-list-check text-primary me-2"></i> 10-Factor Evidence & Weighted Deduction Breakdown</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Factor Name</th>
                        <th>Evidence Status</th>
                        <th class="text-end">Base Wt</th>
                        <th class="text-end">Effective Wt</th>
                        <th class="text-end">Raw Score</th>
                        <th class="text-end">Penalty</th>
                        <th class="text-end">Contrib Score</th>
                        <th>Evidence Source ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($factors)): ?>
                        <tr><td colspan="9" class="text-center py-3 text-muted">No factor breakdown available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($factors as $f): ?>
                            <tr>
                                <td><code class="fw-bold"><?= esc($f['factor_code']) ?></code></td>
                                <td class="fw-semibold text-dark"><?= esc($f['factor_name']) ?></td>
                                <td>
                                    <?php $st = $f['evidence_status']; ?>
                                    <span class="badge <?= $st === 'AVAILABLE' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= $st ?>
                                    </span>
                                </td>
                                <td class="text-end font-monospace"><?= number_format((float)($f['weight_pct'] ?? 0), 1) ?>%</td>
                                <td class="text-end font-monospace text-primary fw-bold"><?= number_format((float)$f['weight_pct'], 2) ?>%</td>
                                <td class="text-end font-monospace"><?= number_format((float)$f['raw_score'], 2) ?></td>
                                <td class="text-end font-monospace text-danger">-<?= number_format((float)$f['penalty_score'], 2) ?></td>
                                <td class="text-end font-monospace fw-bold text-success">+<?= number_format((float)$f['weighted_contribution'], 2) ?></td>
                                <td>
                                    <small class="text-muted font-monospace"><?= esc($f['evidence_source'] ?? '-') ?> (<?= esc($f['evidence_id'] ?? '-') ?>)</small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- AUDIT TRAIL HISTORY -->
    <div class="ah-card p-4">
        <h5 class="fw-bold text-dark mb-3"><i class="fas fa-history text-secondary me-2"></i> Append-Only Score Transition History (B83-G12)</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                <thead class="table-light">
                    <tr>
                        <th>Timestamp</th>
                        <th>Trigger Event</th>
                        <th>Previous Score</th>
                        <th>New Score</th>
                        <th>Previous Category</th>
                        <th>New Category</th>
                        <th>Algorithm Version</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="7" class="text-center py-3 text-muted">No history logged yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td class="font-monospace"><?= esc($h['calculated_at']) ?></td>
                                <td><span class="badge bg-secondary"><?= esc($h['trigger_event']) ?></span></td>
                                <td class="font-monospace text-muted"><?= $h['previous_score'] !== null ? number_format((float)$h['previous_score'], 2) : '-' ?></td>
                                <td class="font-monospace fw-bold text-primary"><?= number_format((float)$h['new_score'], 2) ?></td>
                                <td><?= esc($h['previous_category'] ?? '-') ?></td>
                                <td><span class="badge badge-cat-<?= esc($h['new_category']) ?>"><?= esc($h['new_category']) ?></span></td>
                                <td class="font-monospace text-muted"><?= esc($h['algorithm_version']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
