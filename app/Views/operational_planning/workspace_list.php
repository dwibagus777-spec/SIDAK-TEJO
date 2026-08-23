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

.wk-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.wk-card {
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
    <div class="wk-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-drafting-compass text-info mr-2"></i>Human Operational Planning Workspace
                </h2>
                <small class="text-muted">
                    Wave 2 Phase OP-02 &bull; Ruang kerja terstruktur penyusunan ruang lingkup, material indikatif, dan telaah rencana kerja
                </small>
            </div>
            <div>
                <a href="<?= base_url('operational-planning/candidates') ?>" class="btn btn-outline-warning btn-sm mr-2">
                    <i class="fas fa-clipboard-list mr-1"></i> Candidates Queue
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
             1. CANDIDATES READY FOR PLANNING DRAFT CREATION
             ───────────────────────────────────────────────────────────────── -->
        <div class="wk-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="text-white font-weight-bold mb-0">
                        <i class="fas fa-check-double text-success mr-2"></i>Kandidat Diterima (Siap Disusun Draft Rencana)
                    </h5>
                    <small class="text-muted">Kandidat berstatus <code>ACCEPTED_AS_PLANNING_INTENT</code> yang belum memiliki draft rencana aktif</small>
                </div>
                <span class="badge badge-success px-3 py-2 text-uppercase">
                    <?= count($readyCandidates) ?> Kandidat Siap Susun
                </span>
            </div>

            <?php if (!empty($readyCandidates)): ?>
                <div class="table-responsive">
                    <table class="table table-dark table-sm table-bordered small mb-0">
                        <thead>
                            <tr class="text-muted text-uppercase">
                                <th>Candidate Code</th>
                                <th>Judul Usulan Pekerjaan</th>
                                <th>Penyulang & Seksi</th>
                                <th>Target Durasi</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($readyCandidates as $rc): ?>
                                <tr>
                                    <td class="font-weight-bold text-warning">
                                        <code><?= esc($rc['candidate_code']) ?></code>
                                    </td>
                                    <td>
                                        <strong class="text-white"><?= esc($rc['proposed_work_title']) ?></strong>
                                    </td>
                                    <td>
                                        <?= esc($rc['feeder_name']) ?> &bull; <?= esc($rc['section_name']) ?>
                                    </td>
                                    <td><?= (int)$rc['target_completion_days'] ?> Hari</td>
                                    <td class="text-center">
                                        <a href="<?= base_url('operational-planning/workspace/create/' . $rc['id']) ?>" class="btn btn-sm btn-info font-weight-bold">
                                            <i class="fas fa-pen mr-1"></i> Susun Draft Rencana
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="small text-muted mb-0">
                    <i class="fas fa-info-circle mr-1"></i> Semua kandidat yang diterima telah memiliki draft rencana kerja.
                </p>
            <?php endif; ?>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             2. OPERATIONAL PLANS LIST TABLE
             ───────────────────────────────────────────────────────────────── -->
        <div class="wk-card">
            <h5 class="text-white font-weight-bold mb-3">
                <i class="fas fa-folder-open text-warning mr-2"></i>Daftar Dokumen Rencana Kerja Operasional (OP-02)
            </h5>

            <div class="table-responsive">
                <table class="table table-dark table-hover table-bordered small mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase">
                            <th>Plan Code</th>
                            <th>Kategori Pekerjaan</th>
                            <th>Penyulang & Seksi</th>
                            <th class="text-center">Status Rencana</th>
                            <th class="text-center">Pemadaman</th>
                            <th>Perencana</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($plans)): ?>
                            <?php foreach ($plans as $p): ?>
                                <tr>
                                    <td class="font-weight-bold text-info">
                                        <code><?= esc($p['plan_code']) ?></code>
                                    </td>
                                    <td>
                                        <span class="badge badge-dark border border-secondary"><?= esc($p['work_category']) ?></span>
                                    </td>
                                    <td>
                                        <?= esc($p['feeder_name']) ?> &bull; <?= esc($p['section_name']) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                            $st = $p['plan_status'];
                                            $badge = match($st) {
                                                'APPROVED_FOR_PORTFOLIO' => 'badge-success',
                                                'UNDER_PLANNING_REVIEW'  => 'badge-info',
                                                'REVISION_REQUIRED'      => 'badge-danger',
                                                default                  => 'badge-warning',
                                            };
                                        ?>
                                        <span class="badge <?= $badge ?> text-uppercase"><?= str_replace('_', ' ', $st) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty($p['outage_required'])): ?>
                                            <span class="badge badge-danger">PADAM</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">PDKB / BERTEGANGAN</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted"><?= esc($p['planner_actor_name']) ?></td>
                                    <td class="text-center">
                                        <a href="<?= base_url('operational-planning/workspace/detail/' . $p['id']) ?>" class="btn btn-sm btn-outline-info font-weight-bold">
                                            <i class="fas fa-search mr-1"></i> Rincian & Telaah
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    Belum ada dokumen rencana kerja yang disusun.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?= $this->endSection() ?>
