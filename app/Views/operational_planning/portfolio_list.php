<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<style>
:root {
    --cc-bg: #0b111e;
    --cc-card-bg: rgba(18, 26, 43, 0.85);
    --cc-border: rgba(45, 62, 92, 0.6);
}

.port-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.port-card {
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
    <div class="port-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-layer-group text-warning mr-2"></i>Portfolio Governance & Human Planning Prioritization
                </h2>
                <small class="text-muted">
                    Wave 2 Phase OP-03 &bull; Agregasi makro rencana kerja, evaluasi kapasitas material, dan penetapan prioritas terkelola
                </small>
            </div>
            <div>
                <a href="<?= base_url('operational-planning/portfolios/create') ?>" class="btn btn-warning font-weight-bold btn-sm mr-2">
                    <i class="fas fa-plus mr-1"></i> Rakit Portofolio Baru
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

        <!-- Active Portfolios List -->
        <div class="port-card">
            <h5 class="text-white font-weight-bold mb-3">
                <i class="fas fa-briefcase text-info mr-2"></i>Daftar Portofolio Perencanaan Operasional (OP-03)
            </h5>

            <div class="table-responsive">
                <table class="table table-dark table-hover table-bordered small mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase">
                            <th>Portfolio Code</th>
                            <th>Judul Portofolio</th>
                            <th class="text-center">Periode (Tahun-W)</th>
                            <th class="text-center">Total Rencana</th>
                            <th class="text-center">Pemadaman</th>
                            <th class="text-center">Status Portofolio</th>
                            <th>Pengesah (Manajer)</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($portfolios)): ?>
                            <?php foreach ($portfolios as $port): ?>
                                <tr>
                                    <td class="font-weight-bold text-warning">
                                        <code><?= esc($port['portfolio_code']) ?></code>
                                    </td>
                                    <td>
                                        <strong class="text-white"><?= esc($port['portfolio_title']) ?></strong>
                                    </td>
                                    <td class="text-center"><?= (int)$port['period_year'] ?> - W<?= str_pad((string)$port['period_week'], 2, '0', STR_PAD_LEFT) ?></td>
                                    <td class="text-center font-weight-bold text-info"><?= (int)$port['total_plans_count'] ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-danger"><?= (int)$port['total_outage_plans_count'] ?> PADAM</span>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                            $st = $port['portfolio_status'];
                                            $badge = match($st) {
                                                'PORTFOLIO_RATIFIED'     => 'badge-success',
                                                'UNDER_PORTFOLIO_REVIEW' => 'badge-info',
                                                'PORTFOLIO_ARCHIVED'     => 'badge-secondary',
                                                default                  => 'badge-warning',
                                            };
                                        ?>
                                        <span class="badge <?= $badge ?> text-uppercase"><?= str_replace('_', ' ', $st) ?></span>
                                    </td>
                                    <td class="text-muted">
                                        <?= esc($port['governing_manager_name'] ?? 'Belum Diratifikasi') ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= base_url('operational-planning/portfolios/detail/' . $port['id']) ?>" class="btn btn-sm btn-outline-info font-weight-bold">
                                            <i class="fas fa-search-plus mr-1"></i> Buka Portofolio
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    Belum ada portofolio perencanaan yang dirakit saat ini.
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
