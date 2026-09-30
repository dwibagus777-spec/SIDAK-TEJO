<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Work Package Remediasi<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="container-xl">
    <!-- Page Header -->
    <div class="page-header d-print-none mb-3">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-muted">B8.1 Remediation Core</div>
                <h2 class="page-title">
                    <i class="fas fa-tools text-warning me-2"></i>Work Package Remediasi
                </h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <a href="<?= site_url('remediation/create') ?>" class="btn btn-warning">
                    <i class="fas fa-plus-circle me-1"></i> Buat Work Package
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

    <!-- Status Summary Badges -->
    <?php
    use App\Services\RemediationWorkPackageService as WPS;
    $allStatuses = $all_statuses ?? WPS::ALL_STATUSES;
    $counts = $status_counts ?? [];
    $total = array_sum($counts);
    ?>
    <div class="row g-2 mb-3">
        <div class="col-12">
            <div class="card card-sm">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="text-muted small fw-semibold me-1">Status:</span>
                        <?php foreach ($allStatuses as $s): ?>
                            <?php $cnt = $counts[$s] ?? 0; ?>
                            <span class="badge <?= WPS::statusBadgeClass($s) ?> rounded-pill fs-6 px-2">
                                <?= esc(WPS::statusLabel($s)) ?> <span class="ms-1 opacity-75">(<?= $cnt ?>)</span>
                            </span>
                        <?php endforeach; ?>
                        <span class="ms-auto text-muted small">Total: <strong><?= $total ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Work Package Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list me-1"></i> Daftar Work Package</h3>
        </div>
        <div class="card-body p-0">
            <?php if (empty($work_packages)): ?>
                <!-- ── EMPTY STATE (B8.1-UI-05) ── -->
                <div class="d-flex flex-column align-items-center justify-content-center py-5 text-muted">
                    <i class="fas fa-folder-open fa-3x mb-3 opacity-50"></i>
                    <p class="mb-1 fw-semibold">Belum ada Work Package Remediasi</p>
                    <p class="small mb-3">Belum terdapat paket remediasi yang terdaftar dalam sistem.</p>
                    <a href="<?= site_url('remediation/create') ?>" class="btn btn-sm btn-outline-warning">
                        <i class="fas fa-plus-circle me-1"></i> Buat Work Package Pertama
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-vcenter table-hover card-table">
                        <thead>
                            <tr>
                                <th class="w-1">#</th>
                                <th>Kode WP</th>
                                <th>Judul</th>
                                <th>Prioritas</th>
                                <th>Status FSM</th>
                                <th>Tanggal Dibuat</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($work_packages as $idx => $wp): ?>
                                <tr>
                                    <td class="text-muted"><?= $idx + 1 ?></td>
                                    <td>
                                        <span class="font-monospace small fw-semibold text-warning">
                                            <?= esc($wp['package_code'] ?? '-') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= site_url('remediation/work-packages/' . (int)$wp['id']) ?>" class="text-body fw-semibold">
                                            <?= esc($wp['title'] ?? '-') ?>
                                        </a>
                                        <?php if (!empty($wp['description'])): ?>
                                            <div class="text-muted small text-truncate" style="max-width: 280px;">
                                                <?= esc($wp['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $priority = $wp['priority'] ?? 'MEDIUM';
                                        $pBadge = match($priority) {
                                            'HIGH', 'EMERGENCY' => 'bg-danger',
                                            'MEDIUM'            => 'bg-warning text-dark',
                                            default             => 'bg-secondary',
                                        };
                                        ?>
                                        <span class="badge <?= $pBadge ?>"><?= esc($priority) ?></span>
                                    </td>
                                    <td>
                                        <?php $status = $wp['status'] ?? 'DRAFT'; ?>
                                        <span class="badge <?= WPS::statusBadgeClass($status) ?>">
                                            <?= esc(WPS::statusLabel($status)) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <?= !empty($wp['created_at']) ? date('d/m/Y H:i', strtotime($wp['created_at'])) : '-' ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= site_url('remediation/work-packages/' . (int)$wp['id']) ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
