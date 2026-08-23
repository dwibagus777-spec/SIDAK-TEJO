<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<style>
:root {
    --cc-bg: #0b111e;
    --cc-card-bg: rgba(18, 26, 43, 0.85);
    --cc-border: rgba(45, 62, 92, 0.6);
}

.sf-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.sf-card {
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
    <div class="sf-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <a href="<?= base_url('operational-planning/scheduling') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Skenario
                </a>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-calendar-plus text-success mr-2"></i>Rancang Skenario Penjadwalan & Kapasitas (OP-04)
                </h2>
                <small class="text-muted">
                    Portofolio Sumber: <code><?= esc($portfolio['portfolio_code']) ?></code> &bull; Periode: Tahun <?= (int)$portfolio['period_year'] ?> - Minggu <?= (int)$portfolio['period_week'] ?>
                </small>
            </div>
            <div>
                <span class="badge badge-warning px-3 py-2 text-uppercase font-size-sm">
                    Status Awal: SCENARIO_DRAFT
                </span>
            </div>
        </div>

        <form method="POST" action="<?= base_url('operational-planning/scheduling/store') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="portfolio_id" value="<?= (int)$portfolio['id'] ?>">

            <div class="row">
                
                <!-- Left: Scenario Configuration -->
                <div class="col-lg-6">
                    <div class="sf-card">
                        <h5 class="text-white font-weight-bold mb-3">
                            <i class="fas fa-sliders-h text-info mr-2"></i>Parameter & Strategi Penjadwalan
                        </h5>

                        <div class="form-group">
                            <label class="small font-weight-bold text-white">Judul Skenario Penjadwalan:</label>
                            <input type="text" name="scenario_title" class="form-control bg-dark text-white border-secondary" value="Skenario Jadwal Pemeliharaan Mingguan <?= esc($portfolio['portfolio_code']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="small font-weight-bold text-warning">Pilih Strategi Alokasi Waktu & Regu Kerja:</label>
                            <select name="scenario_strategy" class="form-control bg-dark text-white border-secondary" required>
                                <option value="BALANCED_PDKB_PREFERRED">BALANCED_PDKB_PREFERRED (Maksimalkan PDKB, Minimalkan Pemadaman Pelanggan)</option>
                                <option value="CONSERVATIVE_CAPACITY">CONSERVATIVE_CAPACITY (Beban Ringan, Maksimal 1-2 Pekerjaan per Hari)</option>
                                <option value="AGGRESSIVE_OUTAGE_WINDOW">AGGRESSIVE_OUTAGE_WINDOW (Konsolidasi Pemadaman SUTM Terpusat)</option>
                            </select>
                        </div>

                        <div class="p-3 bg-dark rounded border border-secondary small text-muted mb-3">
                            <code>SCHEDULE_SCENARIO ≠ CREW_DISPATCH</code><br>
                            <code>CAPACITY_ALLOCATION ≠ PERSONNEL_ASSIGNMENT</code><br>
                            <code>OUTAGE_WINDOW ≠ SWITCHING_AUTHORIZATION</code>
                        </div>

                        <button type="submit" class="btn btn-success font-weight-bold btn-block py-2">
                            <i class="fas fa-calendar-check mr-1"></i> Buat Skenario & Alokasikan Slot Jadwal
                        </button>
                    </div>
                </div>

                <!-- Right: Portfolio Items Summary -->
                <div class="col-lg-6">
                    <div class="sf-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-white font-weight-bold mb-0">
                                <i class="fas fa-list-ul text-warning mr-2"></i>Daftar Rencana Kerja Portofolio
                            </h5>
                            <span class="badge badge-warning"><?= count($items) ?> Rencana</span>
                        </div>

                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-dark table-sm table-bordered small mb-0">
                                <thead>
                                    <tr class="text-muted text-uppercase">
                                        <th>Plan Code</th>
                                        <th>Penyulang & Seksi</th>
                                        <th class="text-center">Tier</th>
                                        <th class="text-center">Metode</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $it): ?>
                                        <tr>
                                            <td class="font-weight-bold text-info">
                                                <code><?= esc($it['plan_code']) ?></code>
                                            </td>
                                            <td>
                                                <strong class="text-white"><?= esc($it['feeder_name']) ?></strong> &bull; <?= esc($it['section_name']) ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-secondary"><?= esc($it['priority_tier']) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($it['outage_required'])): ?>
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
                    </div>
                </div>

            </div>
        </form>

    </div>
</div>

<?= $this->endSection() ?>
