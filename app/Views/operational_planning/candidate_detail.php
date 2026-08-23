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

.cnd-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.cnd-card {
    background: var(--cc-card-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--cc-border);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    margin-bottom: 20px;
}
</style>

<div class="content-wrapper">
    <div class="cnd-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <a href="<?= base_url('operational-planning/candidates') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Kandidat
                </a>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-file-signature text-warning mr-2"></i>Kandidat Perencanaan: <code><?= esc($candidate['candidate_code']) ?></code>
                </h2>
                <small class="text-muted">
                    Promoted From: <code><?= esc($candidate['promoted_from_lifecycle_state']) ?></code> &bull; Snapshot: <code><?= esc($candidate['snapshot_code']) ?></code>
                </small>
            </div>

            <div>
                <?php
                    $st = $candidate['candidate_status'];
                    $badge = match($st) {
                        'ACCEPTED_AS_PLANNING_INTENT' => 'badge-success',
                        'UNDER_PLANNING_REVIEW'       => 'badge-info',
                        'DISCARDED'                   => 'badge-secondary',
                        default                       => 'badge-warning',
                    };
                ?>
                <span class="badge <?= $badge ?> px-3 py-2 text-uppercase font-size-sm">
                    Status: <?= str_replace('_', ' ', $st) ?>
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

        <!-- Split Pane -->
        <div class="row">
            
            <!-- Left: Candidate Details & State Transition -->
            <div class="col-lg-7">
                <div class="cnd-card">
                    <h5 class="text-white font-weight-bold mb-3">
                        <i class="fas fa-clipboard-check text-info mr-2"></i>Rincian Usulan Perencanaan
                    </h5>

                    <div class="form-group">
                        <label class="small text-muted text-uppercase font-weight-bold">Judul Usulan Pekerjaan:</label>
                        <div class="h5 text-white font-weight-bold"><?= esc($candidate['proposed_work_title']) ?></div>
                    </div>

                    <div class="form-group">
                        <label class="small text-muted text-uppercase font-weight-bold">Ruang Lingkup Usulan (Scope of Work):</label>
                        <div class="p-3 bg-dark rounded border border-secondary text-white small">
                            <?= nl2br(esc($candidate['proposed_work_scope'])) ?>
                        </div>
                    </div>

                    <div class="row small text-muted mb-3">
                        <div class="col-6">
                            <span>Target Durasi:</span>
                            <strong class="text-white d-block"><?= (int)$candidate['target_completion_days'] ?> Hari</strong>
                        </div>
                        <div class="col-6">
                            <span>Planner Inisiator:</span>
                            <strong class="text-white d-block"><?= esc($candidate['planner_actor_name']) ?> (<?= esc($candidate['planner_actor_role']) ?>)</strong>
                        </div>
                    </div>

                    <div class="p-3 bg-black rounded border border-warning small mb-3">
                        <span class="text-warning font-weight-bold text-uppercase d-block mb-1">Alasan Promosi Inisiasi (Promotion Rationale):</span>
                        <div class="text-white"><?= esc($candidate['promotion_rationale']) ?></div>
                    </div>

                    <?php if (!empty($candidate['decision_rationale'])): ?>
                        <div class="p-3 bg-dark rounded border border-info small">
                            <span class="text-info font-weight-bold text-uppercase d-block mb-1">Alasan Keputusan Status (Decision Rationale):</span>
                            <div class="text-white"><?= esc($candidate['decision_rationale']) ?></div>
                            <?php if (!empty($candidate['decision_notes'])): ?>
                                <small class="text-muted d-block mt-1">Catatan: <?= esc($candidate['decision_notes']) ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- State Transition Card -->
                <div class="cnd-card">
                    <h5 class="text-white font-weight-bold mb-3">
                        <i class="fas fa-exchange-alt text-warning mr-2"></i>Governed Candidate Lifecycle Transition
                    </h5>

                    <?php
                        $allowed = match($st) {
                            'CANDIDATE_CREATED'     => ['UNDER_PLANNING_REVIEW' => 'UNDER_PLANNING_REVIEW (Mulai Penelaahan Detail)'],
                            'UNDER_PLANNING_REVIEW' => [
                                'ACCEPTED_AS_PLANNING_INTENT' => 'ACCEPTED_AS_PLANNING_INTENT (Diterima Masuk Rencana Kerja)',
                                'DISCARDED'                   => 'DISCARDED (Batalkan / Tidak Dilanjutkan)'
                            ],
                            default                 => [],
                        };
                    ?>

                    <?php if (!empty($allowed)): ?>
                        <form method="POST" action="<?= base_url('operational-planning/candidates/' . $candidate['id'] . '/transition') ?>">
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <label class="small font-weight-bold text-muted">Transisi Status Perencanaan:</label>
                                <select name="to_status" class="form-control bg-dark text-white border-secondary" required>
                                    <?php foreach ($allowed as $val => $lbl): ?>
                                        <option value="<?= $val ?>"><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="small font-weight-bold text-warning">Decision Rationale (Mandatory):</label>
                                <input type="text" name="decision_rationale" class="form-control bg-dark text-white border-secondary" placeholder="Masukkan alasan keputusan..." required>
                            </div>

                            <div class="form-group">
                                <label class="small font-weight-bold text-muted">Catatan Tambahan (Opsional):</label>
                                <textarea name="decision_notes" class="form-control bg-dark text-white border-secondary" rows="2"></textarea>
                            </div>

                            <button type="submit" class="btn btn-warning font-weight-bold btn-block">
                                <i class="fas fa-stamp mr-1"></i> Simpan Transisi Status Perencanaan
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-secondary bg-dark border-secondary small text-muted mb-0">
                            <i class="fas fa-lock mr-1"></i> Kandidat ini telah berada pada status terminal (<code><?= esc($st) ?></code>).
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Wave 1 Lineage Explorer -->
            <div class="col-lg-5">
                <div class="cnd-card">
                    <h5 class="text-white font-weight-bold mb-3">
                        <i class="fas fa-link text-success mr-2"></i>Wave 1 Source Intelligence Lineage
                    </h5>

                    <table class="table table-dark table-sm table-borderless small mb-3">
                        <tr>
                            <td class="text-muted" style="width: 140px;">Penyulang / Seksi:</td>
                            <td class="font-weight-bold text-white"><?= esc($candidate['feeder_name']) ?> &bull; <?= esc($candidate['section_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Aset Terkait:</td>
                            <td class="text-white"><code><?= esc($candidate['asset_code'] ?? 'ASET-JTM') ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Snapshot ID:</td>
                            <td class="text-info font-weight-bold">#<?= (int)$candidate['snapshot_id'] ?> (<?= esc($candidate['snapshot_code']) ?>)</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Model Scoring:</td>
                            <td class="text-white"><code><?= esc($lineage_provenance['scoring_model_version'] ?? 'PREVENTIVE_SCORING_v1.0') ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Penyebab Dominan:</td>
                            <td><span class="badge badge-warning"><?= esc($lineage_provenance['dominant_cause'] ?? 'ROW') ?></span></td>
                        </tr>
                    </table>

                    <div class="p-3 bg-black rounded border border-secondary small text-muted">
                        <span class="d-block text-white font-weight-bold mb-1"><i class="fas fa-shield-alt text-info mr-1"></i> Constitutional Invariant:</span>
                        <code>PLANNING_CANDIDATE ≠ WORK_ORDER</code><br>
                        <code>SOURCE_LINEAGE = IMMUTABLY_BOUND</code><br>
                        <code>ZERO_AUTONOMOUS_EXECUTION = ENFORCED</code>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<?= $this->endSection() ?>
