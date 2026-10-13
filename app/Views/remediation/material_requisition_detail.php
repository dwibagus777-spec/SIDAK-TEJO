<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Detail Material Requisition <?= esc($package['requisition_code']) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="container-xl">
    <!-- Page Header -->
    <div class="page-header d-print-none mb-3">
        <div class="row align-items-center">
            <div class="col">
                <a href="<?= site_url('remediation/material-requisitions') ?>" class="text-muted small text-decoration-none mb-1 d-inline-block">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar Requisition
                </a>
                <h2 class="page-title">
                    <i class="fas fa-box text-info me-2"></i><?= esc($package['requisition_code']) ?>
                </h2>
            </div>
            <div class="col-auto ms-auto">
                <span class="badge bg-info p-2 fs-6">
                    <i class="fas fa-sync me-1"></i> Status FSM: <?= esc($package['status']) ?>
                </span>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Main Details Left Column -->
        <div class="col-lg-8">
            <!-- Items Table -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-cubes me-2 text-info"></i>Daftar Material Requested</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Material Code</th>
                                <th>Master Material ID</th>
                                <th>Qty Requested</th>
                                <th>Qty Approved</th>
                                <th>Qty Issued</th>
                                <th>Unit</th>
                                <th>Provenance Level (6-Level)</th>
                                <th>Item Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($package['items'])): ?>
                                <tr><td colspan="9" class="text-center py-4 text-muted">Belum ada item material dalam package ini.</td></tr>
                            <?php else: ?>
                                <?php foreach ($package['items'] as $idx => $it): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td><strong class="font-monospace"><?= esc($it['item_code']) ?></strong></td>
                                        <td><span class="badge bg-light text-dark border">#<?= (int)$it['material_master_id'] ?></span></td>
                                        <td><span class="fw-bold"><?= number_format((float)$it['requested_quantity'], 2) ?></span></td>
                                        <td><?= $it['approved_quantity'] !== null ? number_format((float)$it['approved_quantity'], 2) : '-' ?></td>
                                        <td><?= $it['issued_quantity'] !== null ? number_format((float)$it['issued_quantity'], 2) : '-' ?></td>
                                        <td><span class="badge bg-secondary-lt"><?= esc($it['unit_of_measure']) ?></span></td>
                                        <td>
                                            <span class="badge bg-blue-lt">
                                                <i class="fas fa-robot me-1"></i><?= esc($it['provenance_level']) ?>
                                            </span>
                                        </td>
                                        <td><span class="badge bg-info-lt"><?= esc($it['item_status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Reconciliation Card if present -->
            <?php if (!empty($package['reconciliation'])): ?>
                <?php $rec = $package['reconciliation']; ?>
                <div class="card card-active-success mb-3 border-success">
                    <div class="card-header bg-success-lt">
                        <h3 class="card-title text-success mb-0"><i class="fas fa-check-double me-2"></i>Consumption Reconciliation Record (B82-G06)</h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <span class="text-muted small d-block">Issued Items</span>
                                <strong><?= (int)$rec['total_items_issued'] ?></strong>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted small d-block">Consumed Items</span>
                                <strong><?= (int)$rec['total_items_consumed'] ?></strong>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted small d-block">Returned Items</span>
                                <strong><?= (int)$rec['total_items_returned'] ?></strong>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted small d-block">Variance Pct</span>
                                <strong class="text-primary"><?= number_format((float)$rec['overall_variance_pct'], 2) ?>%</strong>
                            </div>
                            <div class="col-12">
                                <span class="text-muted small d-block">Evidence SHA-256 Hash</span>
                                <code class="small text-break bg-light p-1 rounded font-monospace"><?= esc($rec['evidence_sha256']) ?></code>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Audit Trail / History Timeline -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-history me-2 text-info"></i>FSM State Audit Trail (B82-G05 Non-Cascade)</h3>
                </div>
                <div class="card-body">
                    <ul class="timeline timeline-simple">
                        <?php foreach ($package['history'] as $h): ?>
                            <li class="timeline-event">
                                <div class="timeline-event-icon bg-info">
                                    <i class="fas fa-angle-right text-white"></i>
                                </div>
                                <div class="timeline-event-card card">
                                    <div class="card-body py-2 px-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong>Action: <?= esc($h['action_name']) ?></strong>
                                            <span class="text-muted small"><?= date('d M Y H:i:s', strtotime($h['created_at'])) ?></span>
                                        </div>
                                        <p class="small mb-1 text-muted">
                                            Status: <span class="badge bg-secondary"><?= esc($h['previous_status'] ?? 'NONE') ?></span>
                                            <i class="fas fa-long-arrow-alt-right mx-1"></i>
                                            <span class="badge bg-info"><?= esc($h['new_status']) ?></span>
                                        </p>
                                        <div class="small text-dark">
                                            <i class="fas fa-user-shield me-1 text-muted"></i>Actor ID: <?= (int)$h['actor_id'] ?> (<?= esc($h['actor_role']) ?>)
                                        </div>
                                        <?php if (!empty($h['transition_notes'])): ?>
                                            <div class="small text-muted italic mt-1 bg-light p-1 rounded">
                                                Note: "<?= esc($h['transition_notes']) ?>"
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Sidebar Right Column -->
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-info-circle me-2 text-info"></i>Package Metadata</h3>
                </div>
                <div class="card-body py-2">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="text-muted">Requisition Code</td>
                            <td><strong class="font-monospace text-primary"><?= esc($package['requisition_code']) ?></strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Parent WP ID</td>
                            <td><span class="badge bg-light text-dark border">#<?= (int)$package['work_package_id'] ?></span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Warehouse ID</td>
                            <td><?= !empty($package['warehouse_id']) ? '#' . (int)$package['warehouse_id'] : '<span class="text-muted">-</span>' ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Client UUID</td>
                            <td><code class="small text-break font-monospace"><?= esc($package['client_operation_uuid']) ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Test Mode</td>
                            <td>
                                <?php if (!empty($package['test_mode'])): ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-vial me-1"></i> Synthetic Test (test_mode=1)</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Production</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
