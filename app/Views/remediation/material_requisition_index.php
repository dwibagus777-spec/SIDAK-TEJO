<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Material Requisition Core (B8.2)<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="container-xl">
    <!-- Page Header -->
    <div class="page-header d-print-none mb-3">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-muted">B8.2 Material Requisition Core</div>
                <h2 class="page-title">
                    <i class="fas fa-boxes text-info me-2"></i>Material Requisition Packages
                </h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <a href="<?= site_url('remediation/material-requisitions/create') ?>" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-1"></i> Buat Requisition Baru
                </a>
            </div>
        </div>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= esc(session()->getFlashdata('error')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?= esc(session()->getFlashdata('success')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Status Summary Badges (9 FSM States) -->
    <?php
    $counts = $status_counts ?? [];
    $total  = array_sum($counts);
    $badgeColors = [
        'DRAFT'            => 'bg-secondary text-white',
        'REQUESTED'        => 'bg-info text-dark',
        'PENDING_APPROVAL' => 'bg-warning text-dark',
        'APPROVED'         => 'bg-primary text-white',
        'REJECTED'         => 'bg-danger text-white',
        'RESERVED'         => 'bg-purple text-white',
        'ISSUED'           => 'bg-azure text-white',
        'CONSUMED'         => 'bg-cyan text-dark',
        'RECONCILED'       => 'bg-success text-white',
    ];
    ?>
    <div class="row g-2 mb-3">
        <div class="col-12">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="text-muted small fw-bold me-2 mb-1"><i class="fas fa-filter me-1 text-info"></i> 9 FSM Status:</span>
                        <?php foreach ($all_statuses as $st): ?>
                            <?php $cnt = $counts[$st] ?? 0; ?>
                            <?php $colorClass = $badgeColors[$st] ?? 'bg-secondary text-white'; ?>
                            <span class="badge <?= $colorClass ?> px-2 py-1 mb-1 fs-6 fw-normal" style="font-size: 0.82rem !important; white-space: nowrap;">
                                <?= esc($st) ?> <span class="ms-1 fw-bold opacity-75">(<?= $cnt ?>)</span>
                            </span>
                        <?php endforeach; ?>
                        <span class="ms-auto text-muted small mb-1">Total: <strong><?= $total ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main List Card -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">
                <i class="fas fa-list me-1 text-info"></i> Daftar Material Requisition
            </h3>
            <span class="badge bg-info-lt"><?= count($packages) ?> Requisitions</span>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table table-hover">
                <thead>
                    <tr>
                        <th class="w-1">#</th>
                        <th>Kode Requisition</th>
                        <th>Parent Work Package</th>
                        <th>Status FSM (9 States)</th>
                        <th>Warehouse</th>
                        <th>Total Items</th>
                        <th>Test Mode</th>
                        <th>Tanggal Dibuat</th>
                        <th class="w-1 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($packages)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <div class="mb-2"><i class="fas fa-box-open fa-3x opacity-50"></i></div>
                                <h5>Belum ada Material Requisition Package</h5>
                                <p class="small mb-3 text-muted">Belum terdapat paket permintaan material yang terdaftar dalam sistem.</p>
                                <a href="<?= site_url('remediation/material-requisitions/create') ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus-circle me-1"></i> Buat Requisition Pertama
                                </a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($packages as $idx => $pkg): ?>
                            <tr>
                                <td><span class="text-muted"><?= $idx + 1 ?></span></td>
                                <td>
                                    <strong class="font-monospace text-primary"><?= esc($pkg['requisition_code']) ?></strong>
                                    <div class="small text-muted font-monospace" style="font-size: 0.75rem;">UUID: <?= esc(substr($pkg['client_operation_uuid'], 0, 16)) ?>...</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">WP #<?= (int)$pkg['work_package_id'] ?></span>
                                </td>
                                <td>
                                    <?php $colorClass = $badgeColors[$pkg['status']] ?? 'bg-secondary text-white'; ?>
                                    <span class="badge <?= $colorClass ?>">
                                        <?= esc($pkg['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($pkg['warehouse_id'])): ?>
                                        <span class="text-dark"><i class="fas fa-warehouse text-muted me-1"></i> WH #<?= (int)$pkg['warehouse_id'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted italic">- Unassigned -</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold"><?= (int)($pkg['items_count'] ?? 0) ?></span> items
                                </td>
                                <td>
                                    <?php if (!empty($pkg['test_mode'])): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-vial me-1"></i> TEST</span>
                                    <?php else: ?>
                                        <span class="badge bg-success text-white">PROD</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="small text-muted"><?= date('d M Y H:i', strtotime($pkg['created_at'])) ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= site_url('remediation/material-requisitions/' . $pkg['id']) ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i> Detail
                                    </a>
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
