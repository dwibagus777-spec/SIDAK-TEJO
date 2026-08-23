<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<style>
:root {
    --cc-bg: #0b111e;
    --cc-card-bg: rgba(18, 26, 43, 0.85);
    --cc-border: rgba(45, 62, 92, 0.6);
}

.pln-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.pln-card {
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
    <div class="pln-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <a href="<?= base_url('operational-planning/workspace') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Workspace
                </a>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-file-invoice text-info mr-2"></i>Rencana Kerja: <code><?= esc($plan['plan_code']) ?></code>
                </h2>
                <small class="text-muted">
                    Candidate: <code><?= esc($plan['candidate_code']) ?></code> &bull; Snapshot: <code><?= esc($plan['snapshot_code']) ?></code> &bull; Seksi: <?= esc($plan['feeder_name']) ?> - <?= esc($plan['section_name']) ?>
                </small>
            </div>

            <div>
                <?php
                    $st = $plan['plan_status'];
                    $badge = match($st) {
                        'APPROVED_FOR_PORTFOLIO' => 'badge-success',
                        'UNDER_PLANNING_REVIEW'  => 'badge-info',
                        'REVISION_REQUIRED'      => 'badge-danger',
                        default                  => 'badge-warning',
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
            
            <!-- Left: Scope, Safety, Materials, Schedule -->
            <div class="col-lg-7">
                <div class="pln-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="text-white font-weight-bold mb-0">
                            <i class="fas fa-clipboard-list text-warning mr-2"></i>Ruang Lingkup Pekerjaan
                        </h5>
                        <span class="badge badge-dark border border-secondary text-uppercase"><?= esc($plan['work_category']) ?></span>
                    </div>

                    <div class="p-3 bg-dark rounded border border-secondary text-white small mb-3">
                        <?= nl2br(esc($plan['work_scope_narrative'])) ?>
                    </div>

                    <h6 class="text-warning font-weight-bold mb-2">
                        <i class="fas fa-shield-alt mr-1"></i> Langkah Keselamatan K3:
                    </h6>
                    <div class="p-3 bg-black rounded border border-warning text-white small mb-3">
                        <?= nl2br(esc($plan['safety_precautions'])) ?>
                    </div>

                    <div class="row small text-muted mb-3">
                        <div class="col-6">
                            <span>Metode Pekerjaan:</span>
                            <?php if (!empty($plan['outage_required'])): ?>
                                <strong class="text-danger d-block">PADAM (Pemadaman SUTM)</strong>
                            <?php else: ?>
                                <strong class="text-success d-block">PDKB (Bertegangan)</strong>
                            <?php endif; ?>
                        </div>
                        <div class="col-6">
                            <span>Perencana:</span>
                            <strong class="text-white d-block"><?= esc($plan['planner_actor_name']) ?> (<?= esc($plan['planner_actor_role']) ?>)</strong>
                        </div>
                    </div>

                    <!-- Indicative Materials Table -->
                    <h6 class="text-info font-weight-bold mb-2">
                        <i class="fas fa-boxes mr-1"></i> Kebutuhan Material Indikatif (Estimasi Perencanaan):
                    </h6>
                    <table class="table table-dark table-sm table-bordered small mb-0">
                        <thead>
                            <tr class="text-muted text-uppercase">
                                <th>Nama Material</th>
                                <th class="text-center">Jumlah</th>
                                <th>Satuan</th>
                                <th>Status Governance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($materials)): ?>
                                <?php foreach ($materials as $m): ?>
                                    <tr>
                                        <td><strong><?= esc($m['material_name']) ?></strong></td>
                                        <td class="text-center font-weight-bold text-warning"><?= (float)($m['quantity'] ?? 1) ?></td>
                                        <td><?= esc($m['unit'] ?? 'buah') ?></td>
                                        <td><span class="badge badge-secondary"><?= esc($plan['material_status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Tidak ada material khusus yang dicatat.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right: State Transitions & Lineage -->
            <div class="col-lg-5">
                
                <!-- Review / Transition Card -->
                <div class="pln-card">
                    <h5 class="text-white font-weight-bold mb-3">
                        <i class="fas fa-stamp text-success mr-2"></i>Governed Plan Peer Review
                    </h5>

                    <?php
                        $allowedTransitions = match($st) {
                            'PLAN_DRAFT'            => ['UNDER_PLANNING_REVIEW' => 'UNDER_PLANNING_REVIEW (Ajukan untuk Ditelaah)'],
                            'UNDER_PLANNING_REVIEW' => [
                                'APPROVED_FOR_PORTFOLIO' => 'APPROVED_FOR_PORTFOLIO (Setujui Masuk Portofolio)',
                                'REVISION_REQUIRED'      => 'REVISION_REQUIRED (Kembalikan untuk Revisi)'
                            ],
                            'REVISION_REQUIRED'     => ['PLAN_DRAFT' => 'PLAN_DRAFT (Buka Kembali Draft untuk Revisi)'],
                            default                 => [],
                        };
                    ?>

                    <?php if (!empty($allowedTransitions)): ?>
                        <form method="POST" action="<?= base_url('operational-planning/workspace/transition/' . $plan['id']) ?>">
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <label class="small font-weight-bold text-muted">Aksi Transisi Status:</label>
                                <select name="to_status" class="form-control bg-dark text-white border-secondary" required>
                                    <?php foreach ($allowedTransitions as $val => $lbl): ?>
                                        <option value="<?= $val ?>"><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="small font-weight-bold text-warning">Review Rationale / Catatan Revisi (Mandatory):</label>
                                <input type="text" name="review_rationale" class="form-control bg-dark text-white border-secondary" placeholder="Masukkan alasan telaah..." required>
                            </div>

                            <button type="submit" class="btn btn-success font-weight-bold btn-block">
                                <i class="fas fa-check-circle mr-1"></i> Simpan Keputusan Telaah
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-secondary bg-dark border-secondary small text-muted mb-0">
                            <i class="fas fa-lock mr-1"></i> Rencana ini telah berada pada status <code><?= esc($st) ?></code> dan siap diagregasikan pada OP-03 / OP-04.
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($plan['review_rationale'])): ?>
                        <div class="p-3 bg-dark rounded border border-success small mt-3">
                            <span class="text-success font-weight-bold text-uppercase d-block mb-1">Persetujuan Reviewer:</span>
                            <div class="text-white"><?= esc($plan['review_rationale']) ?></div>
                            <small class="text-muted d-block mt-1">Oleh: <?= esc($plan['reviewer_actor_name']) ?></small>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($plan['revision_reason'])): ?>
                        <div class="p-3 bg-dark rounded border border-danger small mt-3">
                            <span class="text-danger font-weight-bold text-uppercase d-block mb-1">Catatan Revisi:</span>
                            <div class="text-white"><?= esc($plan['revision_reason']) ?></div>
                            <small class="text-muted d-block mt-1">Diminta oleh: <?= esc($plan['revision_requested_by']) ?> (<?= esc($plan['revision_requested_at']) ?>)</small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Lineage Card -->
                <div class="pln-card">
                    <h5 class="text-white font-weight-bold mb-3">
                        <i class="fas fa-link text-info mr-2"></i>Lineage Provenance
                    </h5>

                    <table class="table table-dark table-sm table-borderless small mb-3">
                        <tr>
                            <td class="text-muted" style="width: 140px;">Candidate Code:</td>
                            <td class="font-weight-bold text-warning"><?= esc($plan['candidate_code']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Snapshot Code:</td>
                            <td class="text-info"><?= esc($plan['snapshot_code']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Schedule Status:</td>
                            <td><span class="badge badge-secondary"><?= esc($plan['schedule_status']) ?></span></td>
                        </tr>
                    </table>

                    <div class="p-3 bg-black rounded border border-secondary small text-muted">
                        <code>PLANNING_INTENT ≠ WORK_ORDER</code><br>
                        <code>MATERIAL_STATUS = INDICATIVE_ESTIMATE_ONLY</code><br>
                        <code>ZERO_AUTO_DISPATCH = ENFORCED</code>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

<?= $this->endSection() ?>
