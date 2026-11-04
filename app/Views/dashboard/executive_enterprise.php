<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">

<?php if (!empty($emptyUnitState)): ?>
    <!-- EMPTY STATE: UNCONFIGURED OR INVALID UNIT -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-5 text-center my-4 border-start border-4 border-warning">
        <div class="mb-3 text-warning">
            <i class="fas fa-building-circle-exclamation" style="font-size: 3.5rem;"></i>
        </div>
        <h4 class="fw-bold text-dark mb-2">Unit Penugasan Tidak Valid atau Belum Dikonfigurasi</h4>
        <p class="text-muted mb-4 mx-auto" style="max-width: 620px; font-size: 0.95rem; line-height: 1.6;">
            <?= esc($emptyUnitMessage) ?>
            <br><br>
            Sistem SIDAK TEJO tidak menggunakan unit default otomatis demi menjaga integritas data dan isolasi wewenang operasional. Silakan hubungi Administrator atau periksa penugasan akun Anda di menu manajemen pengguna.
        </p>
        <div class="d-flex justify-content-center gap-2">
            <a href="<?= site_url('dashboard') ?>" class="btn btn-outline-secondary btn-sm px-3">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard Utama
            </a>
            <a href="<?= site_url('profile') ?>" class="btn btn-primary btn-sm px-3">
                <i class="fas fa-user-gear me-1"></i> Lihat Profil Akun
            </a>
        </div>
    </div>
<?php else: ?>

    <!-- Top Header & Title -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom" style="gap: 12px;">
        <div>
            <span class="badge bg-warning text-dark font-weight-bold mb-1" style="font-size: 11px;">Selamat Datang, <?= esc($userName) ?> (<?= esc(strtoupper($userRole)) ?>)!</span>
            <h4 class="fw-bold mb-1 text-primary d-flex align-items-center flex-wrap" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-chart-pie text-warning me-2 fs-3"></i>
                <span id="executive-dashboard-title-text"><?= esc($scopeTitle) ?></span>
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">ENTERPRISE ANALYTICS</span>
            </h4>
            <p class="text-muted small mb-0">
                Cakupan Wilayah: <strong id="scope-unit-subtitle" class="text-dark"><?= esc($unitScopeLabel) ?></strong> &bull; Multi-Dimensional Network Monitoring &amp; Temuan SLA Compliance
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span id="live-clock" class="badge bg-white text-dark border p-2 font-monospace fw-bold shadow-xs">
                <i class="fas fa-clock text-primary me-1"></i> <?= date('d M Y H:i:s') ?> WIB
            </span>
        </div>
    </div>

    <!-- GLOBAL FILTER TOOLBAR (ULP, BULAN, TAHUN) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white border-start border-4 border-primary">
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0 text-primary d-flex align-items-center" style="font-size: 13px;">
                <i class="fas fa-filter text-warning me-2"></i> FILTER GLOBAL EXECUTIVE DASHBOARD
            </h6>
            <small class="text-muted" id="filter-status-text"><i class="fas fa-sync fa-spin me-1 text-primary"></i> Data Realtime Terhubung</small>
        </div>
        <div class="card-body p-3">
            <form id="global-dashboard-filter-form" class="row g-2 align-items-end">
                <!-- 1. Filter ULP -->
                <div class="col-md-4 col-sm-6">
                    <label for="filter_ulp" class="form-label small fw-bold text-dark mb-1">Unit ULP PLN</label>
                    <?php if (!empty($isLockedUlp)): ?>
                        <select id="filter_ulp" class="form-select form-select-sm border-secondary-subtle font-weight-bold bg-light" disabled>
                            <option value="<?= esc($lockedUlpId) ?>" selected><?= esc($unitScopeLabel) ?></option>
                        </select>
                        <small class="text-muted d-block mt-1" style="font-size: 11px;"><i class="fas fa-lock text-warning me-1"></i> Terkunci pada ULP penugasan Anda</small>
                    <?php else: ?>
                        <select id="filter_ulp" class="form-select form-select-sm border-secondary-subtle font-weight-bold">
                            <option value=""><?= esc($allOptionLabel) ?></option>
                            <?php foreach ($ulps as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= (string)$ulpSel === (string)$u['id'] ? 'selected' : '' ?>>
                                    <?= esc($u['nama_ulp']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <!-- 2. Filter Bulan -->
                <div class="col-md-3 col-sm-6">
                    <label for="filter_bulan" class="form-label small fw-bold text-dark mb-1">Bulan Inspeksi</label>
                    <select id="filter_bulan" class="form-select form-select-sm border-secondary-subtle font-weight-bold">
                        <option value="" <?= empty($bulanSel) ? 'selected' : '' ?>>-- Semua Bulan (Tahunan) --</option>
                        <?php
                        $months = [
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                        ];
                        foreach ($months as $num => $name):
                        ?>
                            <option value="<?= $num ?>" <?= (int)$bulanSel === $num ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. Filter Tahun -->
                <div class="col-md-3 col-sm-6">
                    <label for="filter_tahun" class="form-label small fw-bold text-dark mb-1">Tahun</label>
                    <select id="filter_tahun" class="form-select form-select-sm border-secondary-subtle font-weight-bold">
                        <?php
                        $curYr = (int)date('Y');
                        for ($y = $curYr + 1; $y >= $curYr - 3; $y--):
                        ?>
                            <option value="<?= $y ?>" <?= (int)$tahunSel === $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Buttons: Reset & Export -->
                <div class="col-md-2 col-sm-6 d-flex gap-2">
                    <button type="button" id="btn-reset-global-filter" class="btn btn-sm btn-outline-secondary font-weight-bold rounded-3 flex-fill">
                        <i class="fas fa-undo me-1"></i> Reset
                    </button>
                    <button type="button" id="btn-export-dashboard" class="btn btn-sm btn-outline-primary font-weight-bold rounded-3 flex-fill" title="Unduh Rincian Data Eksekutif (CSV)">
                        <i class="fas fa-file-csv me-1"></i> Ekspor
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- KPI CARDS GRID -->
    <div class="row g-3 mb-4">
        <!-- Total Temuan -->
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 border-start border-4 border-primary h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold d-block">TOTAL TEMUAN</span>
                        <h2 class="fw-bold mb-0 text-dark" id="kpi-total"><?= number_format($kpi['total']) ?></h2>
                    </div>
                    <div class="p-3 rounded-circle bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-clipboard-list fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Temuan Selesai -->
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 border-start border-4 border-success h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold d-block">TEMUAN SELESAI</span>
                        <h2 class="fw-bold mb-0 text-success" id="kpi-selesai"><?= number_format($kpi['selesai']) ?></h2>
                        <small class="text-muted" id="kpi-rate-text">Rate: <?= $kpi['rate'] ?>%</small>
                    </div>
                    <div class="p-3 rounded-circle bg-success bg-opacity-10 text-success">
                        <i class="fas fa-circle-check fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dalam Proses -->
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 border-start border-4 border-warning h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold d-block">DALAM PROSES</span>
                        <h2 class="fw-bold mb-0 text-warning" id="kpi-proses"><?= number_format($kpi['proses']) ?></h2>
                    </div>
                    <div class="p-3 rounded-circle bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-spinner fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Belum Ditindaklanjuti -->
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 border-start border-4 border-danger h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold d-block">BELUM DITINDAK</span>
                        <h2 class="fw-bold mb-0 text-danger" id="kpi-belum"><?= number_format($kpi['belum']) ?></h2>
                    </div>
                    <div class="p-3 rounded-circle bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-triangle-exclamation fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW 1: LINE CHART TEMUAN VS SELESAI & DONUT CHART STATUS -->
    <div class="row g-3 mb-4">
        <!-- Line Chart: Trend Temuan vs Penyelesaian -->
        <div class="col-lg-8 col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                <div class="card-header bg-white py-3 px-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0 text-primary d-flex align-items-center">
                        <i class="fas fa-chart-line text-primary me-2"></i> Tren Temuan vs Realisasi Penyelesaian
                    </h6>
                    <span class="badge bg-light text-primary border" id="line-chart-period-badge"><?= !empty($bulanSel) ? 'Bulanan' : 'Tahunan' ?></span>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 320px;">
                        <canvas id="lineChartTemuan"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Donut Chart: Status & SLA Breakdown with Total in Center -->
        <div class="col-lg-4 col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                <div class="card-header bg-white py-3 px-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                        <i class="fas fa-chart-pie text-success me-2"></i> Status Completion Breakdown
                    </h6>
                </div>
                <div class="card-body p-3 text-center position-relative">
                    <div style="position: relative; height: 260px; display: flex; align-items: center; justify-content: center;">
                        <canvas id="donutChartStatus"></canvas>
                    </div>
                    <div id="donut-legend-container" class="mt-2 text-center small fw-bold"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW 2: BAR CHART PERFORMA ULP & PIE CHARTS (JENIS & PRIORITAS) -->
    <div class="row g-3 mb-4">
        <!-- Bar Chart: Performa per ULP -->
        <div class="col-lg-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                <div class="card-header bg-white py-3 px-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-primary d-flex align-items-center">
                        <i class="fas fa-chart-bar text-warning me-2"></i> Performa Temuan per ULP Unit <span id="bar-chart-unit-label" class="text-muted small fw-normal ms-1">(<?= esc($unitScopeLabel) ?>)</span>
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 280px;">
                        <canvas id="barChartUlp"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pie Chart: Breakdown Jenis Temuan -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                <div class="card-header bg-white py-3 px-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                        <i class="fas fa-bolt text-danger me-2"></i> Jenis Temuan
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 220px;">
                        <canvas id="pieChartJenis"></canvas>
                    </div>
                    <div id="jenis-legend-container" class="mt-2 small"></div>
                </div>
            </div>
        </div>

        <!-- Pie Chart: Breakdown Prioritas SLA -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                <div class="card-header bg-white py-3 px-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                        <i class="fas fa-shield-halved text-warning me-2"></i> Prioritas SLA
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 220px;">
                        <canvas id="pieChartPrioritas"></canvas>
                    </div>
                    <div id="prioritas-legend-container" class="mt-2 small"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 3: SLA AGING BACKLOG MONITORING -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white">
                <div class="card-header bg-white py-3 px-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                        <i class="fas fa-hourglass-half text-danger me-2"></i> SLA Aging &amp; Backlog Temuan Belum Selesai
                    </h6>
                    <span class="badge bg-danger-subtle text-danger border" id="aging-total-badge"><?= number_format($agingData['total_open'] ?? 0) ?> Total Backlog</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <!-- > 30 Hari (Overdue) -->
                        <div class="col-md-3 col-6">
                            <div class="p-3 rounded-3 bg-danger bg-opacity-10 border border-danger border-opacity-25 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-danger fw-bold small">&gt; 30 HARI (KRITIS)</span>
                                    <i class="fas fa-circle-exclamation text-danger fs-5"></i>
                                </div>
                                <h3 class="fw-bold text-danger mb-0" id="aging-over-30"><?= number_format($agingData['over_30'] ?? 0) ?></h3>
                                <small class="text-muted">Lewat batas SLA perbaikan</small>
                            </div>
                        </div>
                        <!-- 15 - 30 Hari -->
                        <div class="col-md-3 col-6">
                            <div class="p-3 rounded-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-warning-emphasis fw-bold small">15 - 30 HARI</span>
                                    <i class="fas fa-triangle-exclamation text-warning fs-5"></i>
                                </div>
                                <h3 class="fw-bold text-dark mb-0" id="aging-15-30"><?= number_format($agingData['days_15_30'] ?? 0) ?></h3>
                                <small class="text-muted">Perlu percepatan WO</small>
                            </div>
                        </div>
                        <!-- 8 - 14 Hari -->
                        <div class="col-md-3 col-6">
                            <div class="p-3 rounded-3 bg-info bg-opacity-10 border border-info border-opacity-25 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-info-emphasis fw-bold small">8 - 14 HARI</span>
                                    <i class="fas fa-clock text-info fs-5"></i>
                                </div>
                                <h3 class="fw-bold text-dark mb-0" id="aging-8-14"><?= number_format($agingData['days_8_14'] ?? 0) ?></h3>
                                <small class="text-muted">Dalam jadwal patroli/eksekusi</small>
                            </div>
                        </div>
                        <!-- <= 7 Hari -->
                        <div class="col-md-3 col-6">
                            <div class="p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-success fw-bold small">&le; 7 HARI (BARU)</span>
                                    <i class="fas fa-check-circle text-success fs-5"></i>
                                </div>
                                <h3 class="fw-bold text-success mb-0" id="aging-under-7"><?= number_format($agingData['under_7'] ?? 0) ?></h3>
                                <small class="text-muted">Temuan inspeksi terbaru</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 4: TOP CRITICAL FEEDERS & PELAKSANA SUMMARY -->
    <div class="row g-3 mb-4">
        <!-- Top Critical Feeders -->
        <div class="col-lg-7 col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                <div class="card-header bg-white py-3 px-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0 text-primary d-flex align-items-center">
                        <i class="fas fa-ranking-star text-warning me-2"></i> Top Penyulang Kritis (Backlog Tertinggi)
                    </h6>
                    <small class="text-muted">5 Penyulang Prioritas Penanganan</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 50px;">No</th>
                                    <th>Penyulang</th>
                                    <th class="text-center" style="width: 90px;">Total</th>
                                    <th class="text-center" style="width: 100px;">Backlog</th>
                                    <th class="text-center" style="width: 100px;">Emergency</th>
                                    <th class="text-end pe-3" style="width: 80px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="topFeedersTableBody">
                                <?php if (!empty($topFeeders)): ?>
                                    <?php $rank = 1; foreach ($topFeeders as $tf): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-muted"><?= $rank++ ?></td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= esc($tf['nama_penyulang']) ?></div>
                                                <small class="text-muted">ID: #<?= esc($tf['penyulang_id']) ?></small>
                                            </td>
                                            <td class="text-center fw-semibold"><?= number_format($tf['total_temuan']) ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-danger-subtle text-danger border px-2 py-1 fw-bold"><?= number_format($tf['total_backlog']) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ((int)$tf['total_emergency'] > 0): ?>
                                                    <span class="badge bg-danger text-white px-2 py-1 fw-bold"><?= number_format($tf['total_emergency']) ?> Emg</span>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <a href="<?= site_url('temuan?penyulang_id=' . $tf['penyulang_id'] . (!empty($tf['ulp_id']) ? '&ulp_id=' . $tf['ulp_id'] : '') . '&status=BELUM_DITINDAK') ?>" class="btn btn-outline-primary btn-xs py-0 px-2 fw-semibold" style="font-size: 11px;">
                                                    <i class="fas fa-arrow-up-right-from-square me-1"></i>Buka
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted small">
                                            <i class="fas fa-circle-check text-success me-1"></i> Tidak ada data backlog temuan pada cakupan unit ini.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pelaksana Summary -->
        <div class="col-lg-5 col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                <div class="card-header bg-white py-3 px-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                        <i class="fas fa-users-gear text-info me-2"></i> Beban Kerja per Pelaksana
                    </h6>
                    <small class="text-muted">Realisasi per Regu</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Pelaksana</th>
                                    <th class="text-center" style="width: 75px;">Total</th>
                                    <th class="text-center" style="width: 75px;">Selesai</th>
                                    <th class="text-center" style="width: 75px;">Open</th>
                                </tr>
                            </thead>
                            <tbody id="pelaksanaTableBody">
                                <?php if (!empty($pelaksanaData)): ?>
                                    <?php foreach ($pelaksanaData as $pel): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <span class="badge bg-secondary-subtle text-dark border px-2 py-1 fw-bold"><?= esc($pel['pelaksana']) ?></span>
                                            </td>
                                            <td class="text-center fw-semibold"><?= number_format($pel['total']) ?></td>
                                            <td class="text-center text-success fw-bold"><?= number_format($pel['selesai']) ?></td>
                                            <td class="text-center text-danger fw-bold"><?= number_format($pel['open']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted small">
                                            Tidak ada data temuan per pelaksana.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if (empty($emptyUnitState)): ?>
<script src="<?= base_url('plugins/chart.js') ?>"></script>
<script>
$(document).ready(function () {
    // Initial Datasets from Controller
    const initialLine   = <?= json_encode($lineData) ?>;
    const initialStatus = <?= json_encode($statusDonut) ?>;
    const initialBar    = <?= json_encode($ulpBar) ?>;
    const initialJenis  = <?= json_encode($jenisPie) ?>;
    const initialPrio   = <?= json_encode($prioPie) ?>;

    const isLockedUlp   = <?= !empty($isLockedUlp) ? 'true' : 'false' ?>;
    const lockedUlpId   = '<?= !empty($lockedUlpId) ? esc($lockedUlpId) : '' ?>';

    let chartTemuanLine  = null;
    let chartStatusDonut = null;
    let chartUlpBar      = null;
    let chartJenisPie    = null;
    let chartPrioPie     = null;

    // 1. Initialize Line Chart (Tren Temuan vs Selesai)
    function initLineChart(data) {
        const ctx = document.getElementById('lineChartTemuan').getContext('2d');
        if (chartTemuanLine) chartTemuanLine.destroy();

        chartTemuanLine = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Temuan Baru',
                        data: data.temuan,
                        borderColor: '#005eb8',
                        backgroundColor: 'rgba(0, 94, 184, 0.1)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                    },
                    {
                        label: 'Realisasi Selesai',
                        data: data.selesai,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', align: 'end' }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    // 2. Initialize Donut Chart (Status)
    function initDonutChart(data) {
        const ctx = document.getElementById('donutChartStatus').getContext('2d');
        if (chartStatusDonut) chartStatusDonut.destroy();

        chartStatusDonut = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: ['#dc3545', '#ffc107', '#28a745'],
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const val = context.raw;
                                const pct = data.percentages[idx] || 0;
                                return data.labels[idx] + ': ' + val + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Render Legend HTML
        let legendHtml = '<div class="d-flex justify-content-center gap-2 flex-wrap">';
        data.labels.forEach((lbl, idx) => {
            const colors = ['#dc3545', '#ffc107', '#28a745'];
            legendHtml += `<span class="badge" style="background-color:${colors[idx]}; font-size:11px;">${lbl}: ${data.values[idx]} (${data.percentages[idx]}%)</span>`;
        });
        legendHtml += `</div><div class="mt-2 fw-bold text-dark">TOTAL TEMUAN: ${data.total}</div>`;
        $('#donut-legend-container').html(legendHtml);
    }

    // 3. Initialize Bar Chart (Performa ULP)
    function initBarChart(data) {
        const ctx = document.getElementById('barChartUlp').getContext('2d');
        if (chartUlpBar) chartUlpBar.destroy();

        chartUlpBar = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Jumlah Temuan',
                    data: data.values,
                    backgroundColor: '#005eb8',
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw + ' Temuan';
                            }
                        }
                    }
                },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    // 4. Initialize Pie Chart (Jenis Temuan)
    function initJenisChart(data) {
        const ctx = document.getElementById('pieChartJenis').getContext('2d');
        if (chartJenisPie) chartJenisPie.destroy();

        chartJenisPie = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: ['#e63946', '#f4a261', '#2a9d8f', '#457b9d', '#9b59b6', '#34495e'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const val = context.raw;
                                const pct = data.percentages[idx] || 0;
                                return data.labels[idx] + ': ' + val + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // 5. Initialize Pie Chart (Prioritas)
    function initPrioChart(data) {
        const ctx = document.getElementById('pieChartPrioritas').getContext('2d');
        if (chartPrioPie) chartPrioPie.destroy();

        chartPrioPie = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#6c757d'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const val = context.raw;
                                const pct = data.percentages[idx] || 0;
                                return data.labels[idx] + ': ' + val + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // Initial render
    initLineChart(initialLine);
    initDonutChart(initialStatus);
    initBarChart(initialBar);
    initJenisChart(initialJenis);
    initPrioChart(initialPrio);

    // 6. REALTIME AJAX REFRESH ON GLOBAL FILTER CHANGE
    function fetchDashboardData() {
        const ulpId = isLockedUlp ? lockedUlpId : ($('#filter_ulp').val() || '');
        const bulan = $('#filter_bulan').val() || '';
        const tahun = $('#filter_tahun').val() || '';

        $('#filter-status-text').html('<i class="fas fa-spinner fa-spin text-primary me-1"></i> Mengambil data...');

        $.ajax({
            url: '<?= site_url('dashboard/chart-data') ?>',
            type: 'GET',
            data: { ulp_id: ulpId, bulan: bulan, tahun: tahun },
            dataType: 'JSON',
            success: function (res) {
                if (res.success) {
                    // Update Header & Document Titles in real-time
                    if (res.scope_title) {
                        $('#executive-dashboard-title-text').text(res.scope_title);
                    }
                    if (res.browser_title) {
                        document.title = res.browser_title;
                    }
                    if (res.unit_scope_label) {
                        $('#scope-unit-subtitle').text(res.unit_scope_label);
                        $('#bar-chart-unit-label').text('(' + res.unit_scope_label + ')');
                    }

                    // Update Badge Period
                    $('#line-chart-period-badge').text(bulan ? 'Bulanan' : 'Tahunan');

                    // Update KPI Cards
                    $('#kpi-total').text(Number(res.kpi.total).toLocaleString());
                    $('#kpi-selesai').text(Number(res.kpi.selesai).toLocaleString());
                    $('#kpi-proses').text(Number(res.kpi.proses).toLocaleString());
                    $('#kpi-belum').text(Number(res.kpi.belum).toLocaleString());
                    $('#kpi-rate-text').text('Rate: ' + res.kpi.rate + '%');

                    // Refresh Charts
                    initLineChart(res.line_chart);
                    initDonutChart(res.status_donut);
                    initBarChart(res.ulp_bar);
                    initJenisChart(res.jenis_pie);
                    initPrioChart(res.prio_pie);

                    // Update SLA Aging Cards
                    if (res.aging) {
                        $('#aging-total-badge').text(Number(res.aging.total_open || 0).toLocaleString() + ' Total Backlog');
                        $('#aging-over-30').text(Number(res.aging.over_30 || 0).toLocaleString());
                        $('#aging-15-30').text(Number(res.aging.days_15_30 || 0).toLocaleString());
                        $('#aging-8-14').text(Number(res.aging.days_8_14 || 0).toLocaleString());
                        $('#aging-under-7').text(Number(res.aging.under_7 || 0).toLocaleString());
                    }

                    // Update Top Feeders Table
                    if (Array.isArray(res.top_feeders)) {
                        let fHtml = '';
                        if (res.top_feeders.length > 0) {
                            res.top_feeders.forEach(function(tf, idx) {
                                const emgBadge = (parseInt(tf.total_emergency || 0, 10) > 0)
                                    ? '<span class="badge bg-danger text-white px-2 py-1 fw-bold">' + tf.total_emergency + ' Emg</span>'
                                    : '<span class="text-muted small">-</span>';
                                const ulpParam = tf.ulp_id ? ('&ulp_id=' + tf.ulp_id) : '';
                                fHtml += '<tr>' +
                                    '<td class="ps-3 fw-bold text-muted">' + (idx + 1) + '</td>' +
                                    '<td><div class="fw-bold text-dark">' + $('<div>').text(tf.nama_penyulang).html() + '</div><small class="text-muted">ID: #' + tf.penyulang_id + '</small></td>' +
                                    '<td class="text-center fw-semibold">' + Number(tf.total_temuan).toLocaleString() + '</td>' +
                                    '<td class="text-center"><span class="badge bg-danger-subtle text-danger border px-2 py-1 fw-bold">' + Number(tf.total_backlog).toLocaleString() + '</span></td>' +
                                    '<td class="text-center">' + emgBadge + '</td>' +
                                    '<td class="text-end pe-3"><a href="<?= site_url('temuan?status=BELUM_DITINDAK&penyulang_id=') ?>' + tf.penyulang_id + ulpParam + '" class="btn btn-outline-primary btn-xs py-0 px-2 fw-semibold" style="font-size: 11px;"><i class="fas fa-arrow-up-right-from-square me-1"></i>Buka</a></td>' +
                                '</tr>';
                            });
                        } else {
                            fHtml = '<tr><td colspan="6" class="text-center py-4 text-muted small"><i class="fas fa-circle-check text-success me-1"></i> Tidak ada data backlog temuan pada cakupan unit ini.</td></tr>';
                        }
                        $('#topFeedersTableBody').html(fHtml);
                    }

                    // Update Pelaksana Table
                    if (Array.isArray(res.pelaksana)) {
                        let pHtml = '';
                        if (res.pelaksana.length > 0) {
                            res.pelaksana.forEach(function(pel) {
                                pHtml += '<tr>' +
                                    '<td class="ps-3"><span class="badge bg-secondary-subtle text-dark border px-2 py-1 fw-bold">' + $('<div>').text(pel.pelaksana).html() + '</span></td>' +
                                    '<td class="text-center fw-semibold">' + Number(pel.total).toLocaleString() + '</td>' +
                                    '<td class="text-center text-success fw-bold">' + Number(pel.selesai).toLocaleString() + '</td>' +
                                    '<td class="text-center text-danger fw-bold">' + Number(pel.open).toLocaleString() + '</td>' +
                                '</tr>';
                            });
                        } else {
                            pHtml = '<tr><td colspan="4" class="text-center py-4 text-muted small">Tidak ada data temuan per pelaksana.</td></tr>';
                        }
                        $('#pelaksanaTableBody').html(pHtml);
                    }

                    $('#filter-status-text').html('<i class="fas fa-circle-check text-success me-1"></i> Data Diperbarui (' + res.timestamp + ')');
                }
            },
            error: function (xhr) {
                let msg = 'Gagal memperbarui data.';
                try {
                    const json = JSON.parse(xhr.responseText);
                    if (json && json.message) msg = json.message;
                } catch(e) {}
                $('#filter-status-text').html('<i class="fas fa-exclamation-triangle text-danger me-1"></i> ' + msg);
                if (xhr.status === 403 && typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Akses Ditolak',
                        text: msg
                    });
                }
            }
        });
    }

    $('#filter_ulp, #filter_bulan, #filter_tahun').change(function () {
        fetchDashboardData();
    });

    $('#btn-reset-global-filter').click(function () {
        if (!isLockedUlp) {
            $('#filter_ulp').val('');
        }
        $('#filter_bulan').val('<?= date('n') ?>');
        $('#filter_tahun').val('<?= date('Y') ?>');
        fetchDashboardData();
    });

    $('#btn-export-dashboard').click(function () {
        const ulpId = isLockedUlp ? lockedUlpId : ($('#filter_ulp').val() || '');
        const bulan = $('#filter_bulan').val() || '';
        const tahun = $('#filter_tahun').val() || '';
        window.location.href = '<?= site_url('executive-dashboard/export') ?>?ulp_id=' + encodeURIComponent(ulpId) + '&bulan=' + encodeURIComponent(bulan) + '&tahun=' + encodeURIComponent(tahun);
    });
});
</script>
<?php endif; ?>

<?= $this->endSection() ?>
