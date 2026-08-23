<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<style>
:root {
    --cc-bg: #0b111e;
    --cc-card-bg: rgba(18, 26, 43, 0.85);
    --cc-border: rgba(45, 62, 92, 0.6);
}

.sc-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.sc-card {
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
    <div class="sc-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-calendar-alt text-success mr-2"></i>Governed Scheduling & Resource Capacity Planning
                </h2>
                <small class="text-muted">
                    Wave 2 Phase OP-04 &bull; Simulasi skenario penjadwalan, penyeimbangan kapasitas regu kerja, dan jendela pemadaman
                </small>
            </div>
            <div>
                <a href="<?= base_url('operational-planning/portfolios') ?>" class="btn btn-outline-warning btn-sm mr-2">
                    <i class="fas fa-layer-group mr-1"></i> Portfolios Hub
                </a>
                <a href="<?= base_url('operational-planning/workspace') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Workspace
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
             1. RATIFIED PORTFOLIOS READY FOR SCHEDULING SCENARIO
             ───────────────────────────────────────────────────────────────── -->
        <div class="sc-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="text-white font-weight-bold mb-0">
                        <i class="fas fa-stamp text-success mr-2"></i>Portofolio Teratifikasi (Siap Dijadwalkan)
                    </h5>
                    <small class="text-muted">Portofolio berstatus <code>PORTFOLIO_RATIFIED</code> yang siap dibuatkan skenario penjadwalan eksekusi</small>
                </div>
                <span class="badge badge-success px-3 py-2 text-uppercase">
                    <?= count($readyPortfolios) ?> Portofolio Siap
                </span>
            </div>

            <?php if (!empty($readyPortfolios)): ?>
                <div class="table-responsive">
                    <table class="table table-dark table-sm table-bordered small mb-0">
                        <thead>
                            <tr class="text-muted text-uppercase">
                                <th>Portfolio Code</th>
                                <th>Judul Portofolio</th>
                                <th class="text-center">Periode</th>
                                <th class="text-center">Total Rencana</th>
                                <th class="text-center">Pemadaman</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($readyPortfolios as $rp): ?>
                                <tr>
                                    <td class="font-weight-bold text-warning">
                                        <code><?= esc($rp['portfolio_code']) ?></code>
                                    </td>
                                    <td>
                                        <strong class="text-white"><?= esc($rp['portfolio_title']) ?></strong>
                                    </td>
                                    <td class="text-center"><?= (int)$rp['period_year'] ?> - W<?= (int)$rp['period_week'] ?></td>
                                    <td class="text-center font-weight-bold text-info"><?= (int)$rp['total_plans_count'] ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-danger"><?= (int)$rp['total_outage_plans_count'] ?> PADAM</span>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= base_url('operational-planning/scheduling/create/' . $rp['id']) ?>" class="btn btn-sm btn-success font-weight-bold">
                                            <i class="fas fa-calendar-plus mr-1"></i> Rancang Skenario Jadwal
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="small text-muted mb-0">
                    <i class="fas fa-info-circle mr-1"></i> Tidak ada portofolio teratifikasi baru yang belum memiliki skenario aktif.
                </p>
            <?php endif; ?>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             2. SCHEDULING SCENARIOS LIST
             ───────────────────────────────────────────────────────────────── -->
        <div class="sc-card">
            <h5 class="text-white font-weight-bold mb-3">
                <i class="fas fa-calendar-check text-info mr-2"></i>Daftar Skenario Penjadwalan Aktif (OP-04)
            </h5>

            <div class="table-responsive">
                <table class="table table-dark table-hover table-bordered small mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase">
                            <th>Scenario Code</th>
                            <th>Judul Skenario</th>
                            <th>Portofolio Sumber</th>
                            <th>Strategi Jadwal</th>
                            <th class="text-center">Total Rencana</th>
                            <th class="text-center">Status Skenario</th>
                            <th>Pengesah</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($scenarios)): ?>
                            <?php foreach ($scenarios as $sc): ?>
                                <tr>
                                    <td class="font-weight-bold text-info">
                                        <code><?= esc($sc['scenario_code']) ?></code>
                                    </td>
                                    <td>
                                        <strong class="text-white"><?= esc($sc['scenario_title']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="text-warning"><?= esc($sc['portfolio_code']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-dark border border-secondary"><?= esc($sc['scenario_strategy']) ?></span>
                                    </td>
                                    <td class="text-center font-weight-bold"><?= (int)$sc['total_scheduled_plans_count'] ?></td>
                                    <td class="text-center">
                                        <?php
                                            $st = $sc['scenario_status'];
                                            $badge = match($st) {
                                                'SCENARIO_APPROVED'     => 'badge-success',
                                                'UNDER_CAPACITY_REVIEW' => 'badge-info',
                                                'REVISION_REQUIRED'     => 'badge-danger',
                                                'SCENARIO_SUPERSEDED'   => 'badge-secondary',
                                                default                 => 'badge-warning',
                                            };
                                        ?>
                                        <span class="badge <?= $badge ?> text-uppercase"><?= str_replace('_', ' ', $st) ?></span>
                                    </td>
                                    <td class="text-muted"><?= esc($sc['approver_actor_name'] ?? 'Belum Disahkan') ?></td>
                                    <td class="text-center">
                                        <a href="<?= base_url('operational-planning/scheduling/detail/' . $sc['id']) ?>" class="btn btn-sm btn-outline-info font-weight-bold">
                                            <i class="fas fa-calendar mr-1"></i> Buka Kalender & Slot
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    Belum ada skenario penjadwalan yang dirancang saat ini.
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
