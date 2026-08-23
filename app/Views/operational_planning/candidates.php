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

.plan-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.plan-card {
    background: var(--cc-card-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--cc-border);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    margin-bottom: 24px;
}
</style>

<div class="content-wrapper">
    <div class="plan-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-clipboard-list text-warning mr-2"></i>Governed Operational Planning Candidate Bridge
                </h2>
                <small class="text-muted">
                    Wave 2 Phase OP-01 &bull; Jembatan promosi manusia dari intelijen pencegahan (CC-03) ke perencanaan operasional
                </small>
            </div>
            <div>
                <a href="<?= base_url('preventive-intelligence/queue') ?>" class="btn btn-outline-secondary btn-sm mr-2">
                    <i class="fas fa-tasks mr-1"></i> Supervisor Queue
                </a>
                <a href="<?= base_url('executive-intelligence') ?>" class="btn btn-outline-info btn-sm">
                    <i class="fas fa-chart-line mr-1"></i> Executive Analytics
                </a>
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

        <!-- ─────────────────────────────────────────────────────────────────
             1. ELIGIBLE ADVISORIES READY FOR PROMOTION (MITIGATION_PLANNED)
             ───────────────────────────────────────────────────────────────── -->
        <div class="plan-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="text-white font-weight-bold mb-0">
                        <i class="fas fa-arrow-alt-circle-up text-success mr-2"></i>Advisory Siap Promosi Perencanaan (CC-03 MITIGATION_PLANNED)
                    </h5>
                    <small class="text-muted">Advisory yang telah disetujui Pengawas untuk masuk agenda mitigasi</small>
                </div>
                <span class="badge badge-success px-3 py-2 text-uppercase">
                    <?= count($eligibleAdvisories) ?> Advisory Siap Promosi
                </span>
            </div>

            <?php if (!empty($eligibleAdvisories)): ?>
                <div class="table-responsive">
                    <table class="table table-dark table-sm table-bordered small mb-0">
                        <thead>
                            <tr class="text-muted text-uppercase">
                                <th>Snapshot Code</th>
                                <th>Penyulang & Seksi</th>
                                <th>Penyebab Dominan</th>
                                <th>Rekomendasi Review Supervisor</th>
                                <th class="text-center">Aksi Promosi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eligibleAdvisories as $adv): ?>
                                <tr>
                                    <td class="font-weight-bold text-info">
                                        <code><?= esc($adv['snapshot_code']) ?></code>
                                    </td>
                                    <td>
                                        <strong class="text-white"><?= esc($adv['feeder_name']) ?></strong>
                                        <div class="text-muted font-size-xs"><?= esc($adv['section_name']) ?> &bull; <?= esc($adv['asset_code'] ?? 'ASET') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-warning"><?= esc($adv['dominant_historical_cause'] ?? 'ROW') ?></span>
                                    </td>
                                    <td class="text-white">
                                        <?= esc($adv['recommended_review_focus']) ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-success font-weight-bold" onclick="openPromotionModal(<?= (int)$adv['id'] ?>, '<?= esc($adv['snapshot_code']) ?>', '<?= esc($adv['feeder_name']) ?>', '<?= esc($adv['section_name']) ?>', '<?= esc(addslashes($adv['recommended_review_focus'])) ?>')">
                                            <i class="fas fa-plus-circle mr-1"></i> Promosikan ke Planning
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="small text-muted mb-0">
                    <i class="fas fa-info-circle mr-1"></i> Belum ada advisory baru berstatus <code>MITIGATION_PLANNED</code> yang siap dipromosikan.
                </p>
            <?php endif; ?>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             2. ACTIVE OPERATIONAL PLANNING CANDIDATES TABLE
             ───────────────────────────────────────────────────────────────── -->
        <div class="plan-card">
            <h5 class="text-white font-weight-bold mb-3">
                <i class="fas fa-tasks text-info mr-2"></i>Daftar Kandidat Perencanaan Aktif (Wave 2)
            </h5>

            <div class="table-responsive">
                <table class="table table-dark table-hover table-bordered small mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase">
                            <th>Candidate Code</th>
                            <th>Judul Usulan Pekerjaan</th>
                            <th>Penyulang & Seksi</th>
                            <th class="text-center">Status Kandidat</th>
                            <th>Target Waktu</th>
                            <th>Planner Inisiator</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($candidates)): ?>
                            <?php foreach ($candidates as $cnd): ?>
                                <tr>
                                    <td class="font-weight-bold text-warning">
                                        <code><?= esc($cnd['candidate_code']) ?></code>
                                    </td>
                                    <td>
                                        <strong class="text-white"><?= esc($cnd['proposed_work_title']) ?></strong>
                                        <div class="text-muted font-size-xs">Lineage: <code><?= esc($cnd['snapshot_code']) ?></code></div>
                                    </td>
                                    <td>
                                        <?= esc($cnd['feeder_name']) ?> &bull; <?= esc($cnd['section_name']) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                            $st = $cnd['candidate_status'];
                                            $badge = match($st) {
                                                'ACCEPTED_AS_PLANNING_INTENT' => 'badge-success',
                                                'UNDER_PLANNING_REVIEW'       => 'badge-info',
                                                'DISCARDED'                   => 'badge-secondary',
                                                default                       => 'badge-warning',
                                            };
                                        ?>
                                        <span class="badge <?= $badge ?> text-uppercase"><?= str_replace('_', ' ', $st) ?></span>
                                    </td>
                                    <td><?= (int)$cnd['target_completion_days'] ?> Hari</td>
                                    <td class="text-muted">
                                        <?= esc($cnd['planner_actor_name']) ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= base_url('operational-planning/candidates/' . $cnd['id']) ?>" class="btn btn-sm btn-outline-info font-weight-bold">
                                            <i class="fas fa-search-plus mr-1"></i> Detail & Lineage
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    Belum ada kandidat perencanaan yang aktif saat ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Promotion Modal -->
<div class="modal fade" id="promotionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title font-weight-bold text-success">
                    <i class="fas fa-plus-circle mr-2"></i>Promosi Advisory ke Kandidat Perencanaan (Human-Initiated)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="<?= base_url('operational-planning/candidates/promote') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="snapshot_id" id="modalSnapshotId">

                <div class="modal-body">
                    <div class="alert alert-info py-2 small bg-dark border-info text-info mb-3">
                        <i class="fas fa-info-circle mr-1"></i> Promosi ini mengikat lineage snapshot M-05 ke dalam kandidat perencanaan tanpa membuat Work Order atau instruksi eksekusi.
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold text-muted">Snapshot Lineage Source:</label>
                        <input type="text" id="modalSnapshotCode" class="form-control bg-secondary text-white border-dark" readonly>
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold text-white">Judul Usulan Pekerjaan:</label>
                        <input type="text" name="proposed_work_title" id="modalWorkTitle" class="form-control bg-dark text-white border-secondary" required>
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold text-white">Ruang Lingkup Usulan (Scope of Work):</label>
                        <textarea name="proposed_work_scope" id="modalWorkScope" class="form-control bg-dark text-white border-secondary" rows="3" required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold text-white">Estimasi Target Waktu (Hari):</label>
                        <input type="number" name="target_completion_days" class="form-control bg-dark text-white border-secondary" value="7" min="1" max="90" required>
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold text-warning">Alasan Promosi Perencanaan (Mandatory Rationale):</label>
                        <input type="text" name="promotion_rationale" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Temuan P2 pada seksi rawan, butuh perabasan segera" required>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success font-weight-bold">
                        <i class="fas fa-check-circle mr-1"></i> Buat Kandidat Perencanaan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPromotionModal(id, code, feeder, section, scope) {
    document.getElementById('modalSnapshotId').value = id;
    document.getElementById('modalSnapshotCode').value = code + ' (' + feeder + ' - ' + section + ')';
    document.getElementById('modalWorkTitle').value = 'Mitigasi Keandalan Seksi ' + section + ' (' + feeder + ')';
    document.getElementById('modalWorkScope').value = scope;
    $('#promotionModal').modal('show');
}
</script>

<?= $this->endSection() ?>
