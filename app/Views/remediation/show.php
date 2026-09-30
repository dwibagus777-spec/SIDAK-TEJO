<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Detail Work Package<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
use App\Services\RemediationWorkPackageService as WPS;
$status = $wp['status'] ?? 'DRAFT';
?>

<div class="container-xl">
    <!-- Breadcrumb -->
    <div class="page-header d-print-none mb-3">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-muted">
                    <a href="<?= site_url('remediation') ?>" class="text-muted">Remediasi</a>
                    <span class="mx-1">/</span>
                    <?= esc($wp['package_code'] ?? '#' . $wp['id']) ?>
                </div>
                <h2 class="page-title">
                    <i class="fas fa-briefcase text-warning me-2"></i>
                    <?= esc($wp['title'] ?? 'Work Package') ?>
                </h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <a href="<?= site_url('remediation') ?>" class="btn btn-outline-secondary btn-sm me-1">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3">

        <!-- Left: Main Info -->
        <div class="col-lg-8">

            <!-- Work Package Detail Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle me-1"></i> Informasi Work Package</h3>
                    <div class="card-options">
                        <span class="badge <?= WPS::statusBadgeClass($status) ?> fs-6 px-3 py-2">
                            <?= esc(WPS::statusLabel($status)) ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted">Kode WP</dt>
                        <dd class="col-sm-8 font-monospace fw-semibold text-warning">
                            <?= esc($wp['package_code'] ?? '-') ?>
                        </dd>

                        <dt class="col-sm-4 text-muted">Judul</dt>
                        <dd class="col-sm-8"><?= esc($wp['title'] ?? '-') ?></dd>

                        <?php if (!empty($wp['description'])): ?>
                        <dt class="col-sm-4 text-muted">Deskripsi</dt>
                        <dd class="col-sm-8 text-muted"><?= nl2br(esc($wp['description'])) ?></dd>
                        <?php endif; ?>

                        <dt class="col-sm-4 text-muted">Prioritas</dt>
                        <dd class="col-sm-8">
                            <?php
                            $priority = $wp['priority'] ?? 'MEDIUM';
                            $pBadge = match($priority) {
                                'HIGH', 'EMERGENCY' => 'bg-danger',
                                'MEDIUM'            => 'bg-warning text-dark',
                                default             => 'bg-secondary',
                            };
                            ?>
                            <span class="badge <?= $pBadge ?>"><?= esc($priority) ?></span>
                        </dd>

                        <dt class="col-sm-4 text-muted">Status FSM</dt>
                        <dd class="col-sm-8">
                            <span class="badge <?= WPS::statusBadgeClass($status) ?>">
                                <?= esc(WPS::statusLabel($status)) ?>
                            </span>
                        </dd>

                        <?php if (!empty($wp['feeder_code'])): ?>
                        <dt class="col-sm-4 text-muted">Kode Feeder</dt>
                        <dd class="col-sm-8 font-monospace"><?= esc($wp['feeder_code']) ?></dd>
                        <?php endif; ?>

                        <dt class="col-sm-4 text-muted">Dibuat</dt>
                        <dd class="col-sm-8 text-muted">
                            <?= !empty($wp['created_at']) ? date('d/m/Y H:i:s', strtotime($wp['created_at'])) : '-' ?>
                        </dd>

                        <?php if (!empty($wp['submitted_at'])): ?>
                        <dt class="col-sm-4 text-muted">Diajukan</dt>
                        <dd class="col-sm-8 text-muted"><?= date('d/m/Y H:i:s', strtotime($wp['submitted_at'])) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($wp['approved_at'])): ?>
                        <dt class="col-sm-4 text-muted">Disetujui</dt>
                        <dd class="col-sm-8 text-muted"><?= date('d/m/Y H:i:s', strtotime($wp['approved_at'])) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($wp['rejected_at'])): ?>
                        <dt class="col-sm-4 text-muted">Ditolak</dt>
                        <dd class="col-sm-8 text-muted"><?= date('d/m/Y H:i:s', strtotime($wp['rejected_at'])) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($wp['rejection_notes'])): ?>
                        <dt class="col-sm-4 text-muted">Catatan Penolakan</dt>
                        <dd class="col-sm-8 text-danger"><?= nl2br(esc($wp['rejection_notes'])) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($wp['target_completion_date'])): ?>
                        <dt class="col-sm-4 text-muted">Target Selesai</dt>
                        <dd class="col-sm-8"><?= esc($wp['target_completion_date']) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($wp['client_operation_uuid'])): ?>
                        <dt class="col-sm-4 text-muted">Client UUID</dt>
                        <dd class="col-sm-8 font-monospace small text-muted"><?= esc($wp['client_operation_uuid']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Findings Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-search me-1"></i> Temuan yang Dilampirkan
                        <span class="badge bg-blue ms-2"><?= count($findings) ?></span>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($findings)): ?>
                        <div class="d-flex flex-column align-items-center py-4 text-muted">
                            <i class="fas fa-folder-open fa-2x mb-2 opacity-50"></i>
                            <p class="mb-0 small">Belum ada temuan yang dilampirkan ke work package ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-vcenter table-sm card-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>ID Temuan</th>
                                        <th>Status Temuan (saat attach)</th>
                                        <th>Kategori</th>
                                        <th>Status Remediasi Item</th>
                                        <th>Utama</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($findings as $fi => $f): ?>
                                    <tr>
                                        <td class="text-muted"><?= $fi + 1 ?></td>
                                        <td class="font-monospace">#<?= (int)$f['finding_id'] ?></td>
                                        <td>
                                            <span class="badge bg-info text-dark">
                                                <?= esc($f['finding_status_at_attach'] ?? 'CONFIRMED') ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small"><?= esc($f['cause_category'] ?? '-') ?></td>
                                        <td>
                                            <?php $irs = $f['item_remediation_status'] ?? 'PENDING'; ?>
                                            <span class="badge <?= $irs === 'DONE' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= esc($irs) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= !empty($f['is_primary']) ? '<i class="fas fa-star text-warning"></i>' : '' ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /col-lg-8 -->

        <!-- Right: FSM Timeline + Action Buttons -->
        <div class="col-lg-4">

            <!-- FSM Status Map -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-diagram-project me-1"></i> FSM Lifecycle</h3>
                </div>
                <div class="card-body py-2">
                    <?php
                    $allFsm = WPS::ALL_STATUSES;
                    foreach ($allFsm as $fsm):
                        $isActive = ($fsm === $status);
                    ?>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge <?= WPS::statusBadgeClass($fsm) ?> me-2" style="min-width:120px; text-align:center;">
                                <?= esc(WPS::statusLabel($fsm)) ?>
                            </span>
                            <?php if ($isActive): ?>
                                <i class="fas fa-arrow-left text-primary ms-1"></i>
                                <span class="text-primary small ms-1 fw-bold">Status Saat Ini</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Phase 2A Notice — action buttons disabled -->
            <div class="card mb-3 border-warning">
                <div class="card-body py-2">
                    <div class="d-flex align-items-start gap-2">
                        <i class="fas fa-lock text-warning mt-1"></i>
                        <div>
                            <p class="mb-1 small fw-semibold text-warning">Phase 2A — Read-Only Review</p>
                            <p class="mb-0 small text-muted">
                                Tombol aksi (Submit, Approve, Reject, dsb.) akan aktif pada
                                Phase 2B setelah review UI disetujui.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /col-lg-4 -->

        <!-- History Timeline -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-clock-rotate-left me-1"></i> Riwayat Transisi</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($history)): ?>
                        <p class="text-muted small mb-0">Belum ada riwayat transisi.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($history as $h): ?>
                            <div class="timeline-event">
                                <div class="timeline-event-icon bg-warning-lt">
                                    <i class="fas fa-right-left text-warning"></i>
                                </div>
                                <div class="card timeline-event-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-1">
                                            <strong class="small"><?= esc($h['action_name'] ?? '-') ?></strong>
                                            <span class="text-muted small">
                                                <?= !empty($h['created_at']) ? date('d/m/Y H:i', strtotime($h['created_at'])) : '-' ?>
                                            </span>
                                        </div>
                                        <div class="small text-muted mb-1">
                                            <?php if (!empty($h['previous_status'])): ?>
                                                <span class="badge <?= WPS::statusBadgeClass($h['previous_status']) ?> me-1">
                                                    <?= esc(WPS::statusLabel($h['previous_status'])) ?>
                                                </span>
                                                <i class="fas fa-arrow-right text-muted mx-1"></i>
                                            <?php endif; ?>
                                            <span class="badge <?= WPS::statusBadgeClass($h['new_status'] ?? 'DRAFT') ?>">
                                                <?= esc(WPS::statusLabel($h['new_status'] ?? 'DRAFT')) ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($h['actor_role'])): ?>
                                            <div class="text-muted small">Oleh: <?= esc($h['actor_role']) ?> (ID: <?= (int)($h['actor_id'] ?? 0) ?>)</div>
                                        <?php endif; ?>
                                        <?php if (!empty($h['transition_notes'])): ?>
                                            <div class="text-muted small fst-italic mt-1"><?= esc($h['transition_notes']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /row -->
</div>

<?= $this->endSection() ?>
