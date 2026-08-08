<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Manajemen Planning Inspeksi Jaringan<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Inspection Planning & Assignment Center<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item active">Planning Inspeksi</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center rounded-top-4 border-bottom">
                <div>
                    <h5 class="card-title mb-0 font-weight-bold text-dark"><i class="fas fa-clipboard-list text-primary me-2"></i> Daftar Planning Inspeksi Jaringan</h5>
                    <small class="text-muted">Kelola perencanaan inspeksi (WHAT + WHO), alokasi petugas, dan snapshot aset</small>
                </div>
                <a href="<?= site_url('planning/create') ?>" class="btn btn-primary rounded-pill px-4">
                    <i class="fas fa-plus me-1"></i> Buat Planning Baru
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (session()->getFlashdata('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show m-3 rounded-3" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?= session()->getFlashdata('success') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show m-3 rounded-3" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?= session()->getFlashdata('error') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted uppercase small">
                            <tr>
                                <th class="ps-4">No</th>
                                <th>Nomor Planning</th>
                                <th>Judul & Jenis Inspeksi</th>
                                <th>Scope Jaringan (GI / ULP / Feeder)</th>
                                <th>Target Asset</th>
                                <th>Petugas Inspeksi</th>
                                <th>Jadwal</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($plannings)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="fas fa-clipboard-check fa-3x mb-3 d-block text-secondary opacity-50"></i>
                                        Belum ada Planning Inspeksi yang dibuat. Klik <strong>Buat Planning Baru</strong> untuk memulai penugasan.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($plannings as $idx => $p): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-muted"><?= $idx + 1 ?></td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill font-monospace fw-bold">
                                                <?= esc($p['nomor_planning']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-dark d-block"><?= esc($p['title']) ?></strong>
                                            <small class="text-muted"><i class="fas fa-tasks me-1"></i> <?= esc($p['type_name'] ?? 'Inspeksi Visual') ?></small>
                                        </td>
                                        <td class="small">
                                            <div class="fw-bold text-dark"><?= esc($p['nama_penyulang'] ?: 'Semua Feeder') ?></div>
                                            <span class="text-muted"><?= esc($p['nama_gi'] ?: 'GI') ?> &bull; <?= esc($p['nama_ulp'] ?: 'ULP') ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3 py-1 fw-bold">
                                                <?= esc($p['total_assets']) ?> Asset (<?= esc($p['jenis_asset']) ?>)
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($p['inspector_name'])): ?>
                                                <span class="fw-bold text-dark"><i class="fas fa-user-check text-success me-1"></i> <?= esc($p['inspector_name']) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1">Belum Diassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <i class="far fa-calendar-alt me-1"></i> <?= date('d M Y', strtotime($p['scheduled_date'] ?: $p['created_at'])) ?>
                                        </td>
                                        <td>
                                            <?php
                                            $st = strtoupper($p['status']);
                                            $badgeClass = 'bg-secondary-subtle text-secondary';
                                            if ($st === 'PUBLISHED' || $st === 'ASSIGNED') $badgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
                                            else if ($st === 'IN_PROGRESS') $badgeClass = 'bg-warning-subtle text-warning border border-warning-subtle';
                                            else if ($st === 'COMPLETED') $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                                            ?>
                                            <span class="badge <?= $badgeClass ?> px-3 py-1 rounded-pill"><?= esc($st) ?></span>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <a href="<?= site_url('planning/detail/' . $p['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill me-1" title="Lihat Detail Asset">
                                                <i class="fas fa-eye me-1"></i> Detail
                                            </a>
                                            <?php if ($st === 'DRAFT'): ?>
                                                <form action="<?= site_url('planning/publish/' . $p['id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Publikasikan planning ini agar dapat dikerjakan petugas?')">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">
                                                        <i class="fas fa-paper-plane me-1"></i> Publish
                                                    </button>
                                                </form>
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
    </div>
</div>
<?= $this->endSection() ?>
