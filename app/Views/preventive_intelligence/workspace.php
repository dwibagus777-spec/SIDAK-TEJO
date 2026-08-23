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

.workspace-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.ws-card {
    background: var(--cc-card-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--cc-border);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    margin-bottom: 20px;
}

.timeline-item {
    border-left: 2px solid var(--cc-accent-cyan);
    padding-left: 16px;
    margin-bottom: 16px;
    position: relative;
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: -7px;
    top: 4px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--cc-accent-cyan);
}
</style>

<div class="content-wrapper">
    <div class="workspace-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <a href="<?= base_url('preventive-intelligence/queue') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Review Queue
                </a>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-user-shield text-warning mr-2"></i>Supervisor Review Workspace & Lifecycle
                </h2>
                <small class="text-muted">
                    Snapshot ID #<?= esc($snapshot['id'] ?? 1) ?> &bull; Code: <code><?= esc($snapshot['snapshot_code'] ?? 'SNP-01') ?></code> &bull; Model: <code><?= esc($snapshot['scoring_model_version'] ?? 'PREVENTIVE_SCORING_v1.0') ?></code>
                </small>
            </div>

            <div>
                <?php
                    $status = $snapshot['governance_status'] ?? 'ADVISORY_PROPOSED';
                    $badgeClass = match($status) {
                        'SUPERVISOR_REVIEWED' => 'badge-info',
                        'MITIGATION_PLANNED'  => 'badge-success',
                        'ARCHIVED'            => 'badge-secondary',
                        default               => 'badge-warning',
                    };
                ?>
                <span class="badge <?= $badgeClass ?> px-3 py-2 text-uppercase font-size-sm">
                    Status: <?= str_replace('_', ' ', $status) ?>
                </span>
            </div>
        </div>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success bg-success text-white py-2 mb-3">
                <i class="fas fa-check-circle mr-1"></i> <?= session()->getFlashdata('success') ?>
            </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger bg-danger text-white py-2 mb-3">
                <i class="fas fa-exclamation-triangle mr-1"></i> <?= session()->getFlashdata('error') ?>
            </div>
        <?php endif; ?>

        <!-- Split Pane: Left = Evidence Context, Right = Decision & Timeline -->
        <div class="row">
            
            <!-- Left Pane: Evidence Bundle Lineage -->
            <div class="col-lg-7">
                <div class="ws-card">
                    <h5 class="text-white font-weight-bold mb-3">
                        <i class="fas fa-layer-group text-info mr-2"></i>Evidence Lineage & Context
                    </h5>

                    <!-- Pinned Formula Scores -->
                    <div class="p-3 bg-dark rounded border border-secondary mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small text-uppercase font-weight-bold">Preventive Attention Score</span>
                            <span class="badge badge-warning"><?= esc($snapshot['preventive_risk_tier'] ?? 'HIGH_RISK_RECURRENCE') ?></span>
                        </div>
                        <div class="d-flex align-items-baseline">
                            <span class="h2 text-warning font-weight-bold mb-0"><?= number_format((float)($snapshot['correlation_confidence_score'] ?? 0.61), 2) ?></span>
                            <span class="text-muted ml-2">/ 1.00</span>
                        </div>
                        <div class="small text-muted mt-2">
                            Pinned Weights: Severity <strong><?= round(((float)($snapshot['scoring_weight_severity'] ?? 0.40)) * 100) ?>%</strong> &bull;
                            Recurrence <strong><?= round(((float)($snapshot['scoring_weight_historical_recurrence'] ?? 0.35)) * 100) ?>%</strong> &bull;
                            Asset Health <strong><?= round(((float)($snapshot['scoring_weight_asset_health'] ?? 0.25)) * 100) ?>%</strong>
                        </div>
                    </div>

                    <!-- Recommended Review Direction -->
                    <div class="p-3 rounded border border-warning bg-black mb-3">
                        <small class="text-warning font-weight-bold text-uppercase d-block mb-1">Recommended Supervisor Review Direction:</small>
                        <strong class="text-white"><?= esc($snapshot['recommended_review_focus'] ?? 'REVIEW VEGETATION CLEARANCE PRIOR TO CONTINGENCY') ?></strong>
                    </div>

                    <!-- Historical & Asset Metadata -->
                    <div class="row small text-muted">
                        <div class="col-6 mb-2">
                            <span>Dominant Historical Cause:</span>
                            <strong class="text-white d-block"><?= esc($snapshot['dominant_historical_cause'] ?? 'ROW') ?></strong>
                        </div>
                        <div class="col-6 mb-2">
                            <span>Historical Cases Matched:</span>
                            <strong class="text-white d-block"><?= (int)($snapshot['historical_case_matches_count'] ?? 3) ?> Kasus (M-04 Adapter)</strong>
                        </div>
                        <div class="col-6">
                            <span>Median Outage Duration:</span>
                            <strong class="text-white d-block"><?= number_format((float)($snapshot['median_historical_outage_min'] ?? 45.0), 1) ?> Menit</strong>
                        </div>
                        <div class="col-6">
                            <span>Evaluation Timestamp:</span>
                            <strong class="text-white d-block"><?= esc($snapshot['evaluation_timestamp'] ?? '-') ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Pane: Supervisor Governance Decision & Timeline -->
            <div class="col-lg-5">
                
                <!-- Decision Form -->
                <div class="ws-card">
                    <h5 class="text-white font-weight-bold mb-3">
                        <i class="fas fa-gavel text-warning mr-2"></i>Governed Supervisor Decision
                    </h5>

                    <?php
                        $allowedTransitions = match($status) {
                            'ADVISORY_PROPOSED'   => ['SUPERVISOR_REVIEWED' => 'SUPERVISOR_REVIEWED (Konfirmasi Telaah Bukti)'],
                            'SUPERVISOR_REVIEWED' => [
                                'MITIGATION_PLANNED' => 'MITIGATION_PLANNED (Masuk Agenda Pemeliharaan)',
                                'ARCHIVED'           => 'ARCHIVED (Diarsipkan / Risiko Diterima)'
                            ],
                            'MITIGATION_PLANNED'  => ['ARCHIVED' => 'ARCHIVED (Selesai Tindak Lanjut / Diarsipkan)'],
                            default               => [],
                        };
                    ?>

                    <?php if (!empty($allowedTransitions)): ?>
                        <form method="POST" action="<?= base_url('preventive-intelligence/lifecycle/transition') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="snapshot_id" value="<?= esc($snapshot['id']) ?>">

                            <div class="form-group">
                                <label class="small font-weight-bold text-muted">Transisi Status Resmi:</label>
                                <select name="to_status" class="form-control bg-dark text-white border-secondary" required>
                                    <?php foreach ($allowedTransitions as $val => $lbl): ?>
                                        <option value="<?= $val ?>"><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="small font-weight-bold text-warning">Decision Rationale (Wajib):</label>
                                <input type="text" name="decision_rationale" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Bukti vegetasi terverifikasi, jadwalkan perabasan" required>
                            </div>

                            <div class="form-group">
                                <label class="small font-weight-bold text-muted">Catatan Tambahan Supervisor (Opsional):</label>
                                <textarea name="decision_notes" class="form-control bg-dark text-white border-secondary" rows="2" placeholder="Catatan instruksi untuk tim lapangan..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-warning btn-block font-weight-bold">
                                <i class="fas fa-stamp mr-1"></i> Rekam Keputusan Supervisor
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-secondary bg-dark text-muted border-secondary small mb-0">
                            <i class="fas fa-lock mr-1"></i> Snapshot ini berada pada status terminal (<code><?= esc($status) ?></code>). Tidak ada transisi status lanjutan.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Decision Audit Timeline -->
                <div class="ws-card">
                    <h6 class="text-white font-weight-bold mb-3">
                        <i class="fas fa-history text-info mr-2"></i>Decision Audit Timeline (Append-Only)
                    </h6>

                    <?php if (!empty($timeline)): ?>
                        <?php foreach ($timeline as $evt): ?>
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-info small"><?= esc($evt['to_status']) ?></strong>
                                    <small class="text-muted"><?= date('d M Y H:i', strtotime($evt['event_timestamp'])) ?></small>
                                </div>
                                <div class="small text-white mt-1"><?= esc($evt['decision_rationale']) ?></div>
                                <small class="text-muted d-block">
                                    Oleh: <strong><?= esc($evt['actor_name_snapshot']) ?></strong> (<?= esc($evt['actor_role_snapshot']) ?>)
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="small text-muted mb-0">Belum ada catatan transisi status untuk snapshot ini.</p>
                    <?php endif; ?>
                </div>

            </div>

        </div>

    </div>
</div>

<?= $this->endSection() ?>
