<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>L4 Recommendations Queue — Asset Health Intelligence<?= $this->endSection() ?>
<?= $this->section('page_title') ?>SIDAK TEJO AI Autonomy Ceiling — Level 4 Recommendations Queue<?= $this->endSection() ?>

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
</style>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap" style="gap: 12px;">
        <div>
            <a href="<?= base_url('asset-health') ?>" class="btn btn-outline-secondary btn-sm rounded-pill me-2">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Overview
            </a>
            <h3 class="fw-bold mb-1 text-dark d-inline-block align-middle">
                <i class="fas fa-robot text-primary me-2"></i> LEVEL 4 RECOMMENDATIONS QUEUE
            </h3>
            <p class="text-muted small mb-0">AI Governance Ceiling (B83-G08): AI outputs L4 recommendations. Supervisor approval is required for L5 APPROVED before Work Package creation (B83-G09).</p>
        </div>
    </div>

    <!-- Governance Alert -->
    <div class="alert alert-info border-0 rounded-4 shadow-sm mb-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-shield-halved fs-3 text-info me-3"></i>
            <div>
                <strong class="d-block">Human Governance Gate (B83-G08 & B83-G09)</strong>
                <span class="small text-dark">
                    Automatic Work Package dispatch is disabled for AI. Scoring a <strong>CRITICAL</strong> AHI score creates an entry in this queue for human supervisor review.
                </span>
            </div>
        </div>
    </div>

    <!-- Recommendations Table -->
    <div class="ah-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0"><i class="fas fa-inbox text-warning me-2"></i> Queue Recommendations</h5>
            <span class="badge bg-dark font-monospace"><?= count($recommendations) ?> Items</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                <thead class="table-light">
                    <tr>
                        <th>Asset Code / Name</th>
                        <th>AHI Score</th>
                        <th>Recommended Action</th>
                        <th>Urgency</th>
                        <th>Recommendation Notes</th>
                        <th>Provenance</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recommendations)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No pending recommendations in queue.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recommendations as $r): ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('asset-health/' . $r['asset_id']) ?>" class="fw-bold text-primary text-decoration-none">
                                        <?= esc($r['kode_asset']) ?>
                                    </a>
                                    <span class="d-block text-muted small"><?= esc($r['nama_asset']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-danger font-monospace fs-6">
                                        <?= number_format((float)($r['ahi_score'] ?? 0), 2) ?>
                                    </span>
                                </td>
                                <td><span class="badge bg-warning text-dark fw-bold"><?= esc($r['recommended_action']) ?></span></td>
                                <td>
                                    <?php $urg = $r['urgency_level']; ?>
                                    <span class="badge <?= match($urg) { 'EMERGENCY'=>'bg-danger', 'HIGH'=>'bg-warning text-dark', default=>'bg-secondary' } ?>">
                                        <?= $urg ?>
                                    </span>
                                </td>
                                <td style="max-width: 300px;">
                                    <small class="text-dark d-block text-truncate" title="<?= esc($r['recommendation_notes']) ?>">
                                        <?= esc($r['recommendation_notes']) ?>
                                    </small>
                                </td>
                                <td>
                                    <?php $prov = $r['provenance_level']; ?>
                                    <span class="badge <?= $prov === 'APPROVED' ? 'bg-success' : 'bg-primary' ?>">
                                        <?= $prov ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($prov === 'RECOMMENDED'): ?>
                                        <form action="<?= base_url('asset-health/recommendations/' . $r['id'] . '/approve') ?>" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">
                                                <i class="fas fa-check me-1"></i> Setujui (L5)
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-success small fw-bold"><i class="fas fa-check-double me-1"></i> Approved</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
