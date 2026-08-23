<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<style>
:root {
    --cc-bg: #0b111e;
    --cc-card-bg: rgba(18, 26, 43, 0.85);
    --cc-border: rgba(45, 62, 92, 0.6);
}

.pf-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.pf-card {
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
    <div class="pf-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <a href="<?= base_url('operational-planning/portfolios') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Portofolio
                </a>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-tools text-warning mr-2"></i>Rakit Portofolio Perencanaan Baru (OP-03)
                </h2>
                <small class="text-muted">
                    Gabungkan rencana kerja yang disetujui (<code>APPROVED_FOR_PORTFOLIO</code>) ke dalam portofolio mingguan terpadu
                </small>
            </div>
            <div>
                <span class="badge badge-warning px-3 py-2 text-uppercase font-size-sm">
                    Status Awal: PORTFOLIO_DRAFT
                </span>
            </div>
        </div>

        <form method="POST" action="<?= base_url('operational-planning/portfolios/store') ?>">
            <?= csrf_field() ?>

            <div class="row">
                
                <!-- Left: Portfolio Metadata -->
                <div class="col-lg-5">
                    <div class="pf-card">
                        <h5 class="text-white font-weight-bold mb-3">
                            <i class="fas fa-info-circle text-info mr-2"></i>Metadata Portofolio
                        </h5>

                        <div class="form-group">
                            <label class="small font-weight-bold text-white">Judul Portofolio Perencanaan:</label>
                            <input type="text" name="portfolio_title" class="form-control bg-dark text-white border-secondary" value="Portofolio Mitigasi Keandalan UP3 Sidoarjo Minggu <?= $currentWeek ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="small font-weight-bold text-muted">Tahun Periode:</label>
                                    <input type="number" name="period_year" class="form-control bg-dark text-white border-secondary" value="<?= $currentYear ?>" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="small font-weight-bold text-muted">Minggu ke (Week):</label>
                                    <input type="number" name="period_week" class="form-control bg-dark text-white border-secondary" value="<?= $currentWeek ?>" min="1" max="53" required>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 bg-dark rounded border border-secondary small text-muted mb-3">
                            <code>PORTFOLIO_PRIORITY ≠ EXECUTION_ORDER</code><br>
                            <code>MATERIAL_STATUS = INDICATIVE_PORTFOLIO_ESTIMATE_ONLY</code><br>
                            <code>DUPLICATE_MEMBERSHIP = REJECTED</code>
                        </div>

                        <button type="submit" class="btn btn-warning font-weight-bold btn-block py-2">
                            <i class="fas fa-check-circle mr-1"></i> Rakit Portofolio Terpilih
                        </button>
                    </div>
                </div>

                <!-- Right: Select Approved Plans -->
                <div class="col-lg-7">
                    <div class="pf-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-white font-weight-bold mb-0">
                                <i class="fas fa-list-check text-success mr-2"></i>Pilih Rencana Kerja yang Disetujui
                            </h5>
                            <span class="badge badge-success"><?= count($unassignedPlans) ?> Rencana Siap</span>
                        </div>

                        <?php if (!empty($unassignedPlans)): ?>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-dark table-sm table-bordered small mb-0">
                                    <thead>
                                        <tr class="text-muted text-uppercase">
                                            <th style="width: 40px;" class="text-center">Pilih</th>
                                            <th>Plan Code & Kategori</th>
                                            <th>Penyulang & Seksi</th>
                                            <th class="text-center">Metode</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($unassignedPlans as $p): ?>
                                            <tr>
                                                <td class="text-center align-middle">
                                                    <input type="checkbox" name="plan_ids[]" value="<?= (int)$p['id'] ?>" checked>
                                                </td>
                                                <td>
                                                    <strong class="text-info"><?= esc($p['plan_code']) ?></strong>
                                                    <div class="text-muted font-size-xs"><?= esc($p['work_category']) ?></div>
                                                </td>
                                                <td>
                                                    <span class="text-white"><?= esc($p['feeder_name']) ?></span> &bull; <?= esc($p['section_name']) ?>
                                                </td>
                                                <td class="text-center align-middle">
                                                    <?php if (!empty($p['outage_required'])): ?>
                                                        <span class="badge badge-danger">PADAM</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">PDKB</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="small text-muted mb-0">
                                <i class="fas fa-info-circle mr-1"></i> Tidak ada rencana kerja berstatus <code>APPROVED_FOR_PORTFOLIO</code> yang belum masuk portofolio aktif.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </form>

    </div>
</div>

<?= $this->endSection() ?>
