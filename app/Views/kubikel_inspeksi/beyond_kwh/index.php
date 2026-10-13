<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Beyond KWH — Enterprise Command Center & Monitoring Dashboard<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Beyond KWH — Executive Monitoring<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    .beyond-dashboard {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        color: #1e293b;
    }

    /* Top Action Bar */
    .beyond-top-bar {
        background: #ffffff;
        border-radius: 12px;
        padding: 10px 16px;
        border: 1px solid #e2e8f0;
        margin-bottom: 16px;
    }
    .badge-operasi {
        background-color: #0d6efd;
        color: #ffffff;
        font-weight: 700;
        font-size: 11px;
        letter-spacing: 0.5px;
        padding: 6px 14px;
        border-radius: 6px;
    }
    .btn-presentation {
        background-color: #ffc107;
        color: #000000;
        font-weight: 600;
        font-size: 12px;
        border-radius: 6px;
        border: none;
        padding: 6px 14px;
        transition: opacity 0.2s;
    }
    .btn-presentation:hover {
        background-color: #e0a800;
        color: #000000;
    }
    .update-timestamp {
        font-size: 12px;
        color: #475569;
        font-weight: 500;
    }

    /* 5 Tahapan Pipeline Section */
    .beyond-pipeline-card {
        background: #ffffff;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        padding: 16px 20px;
        margin-bottom: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .pipeline-title {
        font-size: 13px;
        font-weight: 800;
        letter-spacing: 0.2px;
        color: #0f172a;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .pipeline-pill {
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        padding: 6px 12px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s ease-in-out;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #334155;
        text-decoration: none !important;
    }
    .pipeline-pill:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
        color: #0f172a;
    }
    .pipeline-pill.active {
        background: #059669 !important;
        border-color: #059669 !important;
        color: #ffffff !important;
    }

    /* Filter Box */
    .beyond-filter-box {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 14px 18px;
        margin-bottom: 18px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .filter-label {
        font-size: 11px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 4px;
        display: block;
    }

    /* 4 Primary KPI Cards */
    .kpi-card {
        border-radius: 14px;
        padding: 18px 20px;
        color: #ffffff;
        position: relative;
        overflow: hidden;
        min-height: 120px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(0,0,0,0.1);
    }
    .kpi-blue {
        background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
    }
    .kpi-red {
        background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
    }
    .kpi-orange {
        background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
    }
    .kpi-green {
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    }
    .kpi-title {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.6px;
        text-transform: uppercase;
        opacity: 0.95;
    }
    .kpi-value {
        font-size: 34px;
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -0.5px;
        margin: 4px 0 2px 0;
    }
    .kpi-sub {
        font-size: 11px;
        opacity: 0.85;
    }
    .kpi-trend {
        font-size: 10px;
        font-weight: 600;
        margin-top: 6px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        opacity: 0.95;
    }

    /* Tabbed Navigation Bar */
    .beyond-nav-tabs {
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }
    .beyond-tab-btn {
        background: transparent;
        border: none;
        font-size: 13px;
        font-weight: 700;
        color: #64748b;
        padding: 10px 16px;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .beyond-tab-btn:hover {
        color: #0d6efd;
    }
    .beyond-tab-btn.active {
        color: #0d6efd;
        border-bottom-color: #0d6efd;
        background: rgba(13, 110, 253, 0.04);
        border-radius: 8px 8px 0 0;
    }

    /* Stage Chart Card Containers */
    .stage-section-card {
        background: #ffffff;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        padding: 18px 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.03);
    }
    .stage-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 10px;
    }
    .stage-title {
        font-size: 13px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .stage-badge {
        font-size: 10px;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 12px;
    }
    .chart-box {
        background: #ffffff;
        border: 1px solid #f1f5f9;
        border-radius: 10px;
        padding: 14px;
        position: relative;
    }
    .chart-box-title {
        font-size: 12px;
        font-weight: 700;
        color: #334155;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* 4DX Tables */
    .table-4dx {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }
    .table-4dx th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 10px;
        letter-spacing: 0.5px;
        padding: 12px 14px;
        border-bottom: 2px solid #e2e8f0;
    }
    .table-4dx td {
        padding: 12px 14px;
        border-bottom: 1px solid #f1f5f9;
        color: #1e293b;
        vertical-align: middle;
    }
    .table-4dx tr:hover td {
        background-color: #f8fafc;
    }

    /* Enterprise Footer */
    .beyond-footer {
        text-align: center;
        font-size: 11px;
        color: #64748b;
        padding: 24px 0 12px 0;
        margin-top: 24px;
        border-top: 1px solid #e2e8f0;
    }
</style>

<div class="container-fluid px-3 px-md-4 py-2 beyond-dashboard" id="beyond-root-container">

    <!-- TOP CONTROL BAR -->
    <div class="beyond-top-bar d-flex flex-wrap align-items-center justify-content-between gap-2 shadow-sm">
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-operasi">OPERASI</span>
            <span class="badge bg-dark text-white fw-bold px-2 py-1">Enterprise</span>
        </div>
        <div class="d-flex align-items-center flex-wrap gap-2">
            <button type="button" class="btn btn-presentation" id="btn-toggle-presentation" onclick="togglePresentationMode()">
                <i class="fas fa-desktop me-1"></i> Mode Presentasi
            </button>
            <span class="update-timestamp" id="live-timestamp-text">
                Update: <?= date('d/m/Y, H.i.s') ?>
            </span>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="refreshDashboardData()">
                <i class="fas fa-sync-alt me-1" id="icon-refresh-spinner"></i> Refresh Data
            </button>
        </div>
    </div>

    <!-- 5 TAHAPAN PIPELINE BISNIS BEYOND KWH -->
    <div class="beyond-pipeline-card">
        <div class="pipeline-title">
            <i class="fas fa-project-diagram text-primary"></i>
            <span>5 TAHAPAN PIPELINE BISNIS BEYOND KWH (MIRRORING MONITORING ALL):</span>
        </div>
        <div class="d-flex flex-wrap gap-2" id="pipeline-pills-container">
            <a href="javascript:void(0)" class="pipeline-pill active" data-stage="all" onclick="filterPipelineStage('all', this)">
                <i class="fas fa-th-large"></i> SEMUA TAHAPAN (<span id="count-stage-all"><?= number_format($pipelineStats['total_rows'] ?? 0) ?></span>)
            </a>
            <a href="javascript:void(0)" class="pipeline-pill" data-stage="probing" onclick="filterPipelineStage('probing', this)">
                <i class="fas fa-search"></i> 1. PROBING ALL (<span id="count-stage-probing"><?= number_format($pipelineStats['probing'] ?? 0) ?></span>)
            </a>
            <a href="javascript:void(0)" class="pipeline-pill" data-stage="penawaran" onclick="filterPipelineStage('penawaran', this)">
                <i class="fas fa-folder-open"></i> 2. PENAWARAN (<span id="count-stage-penawaran"><?= number_format($pipelineStats['penawaran'] ?? 0) ?></span>)
            </a>
            <a href="javascript:void(0)" class="pipeline-pill" data-stage="spk" onclick="filterPipelineStage('spk', this)">
                <i class="fas fa-file-contract"></i> 3. SPK / PO (<span id="count-stage-spk"><?= number_format($pipelineStats['spk'] ?? 0) ?></span>)
            </a>
            <a href="javascript:void(0)" class="pipeline-pill" data-stage="penagihan" onclick="filterPipelineStage('penagihan', this)">
                <i class="fas fa-file-invoice-dollar"></i> 4. PENAGIHAN (<span id="count-stage-penagihan"><?= number_format($pipelineStats['penagihan'] ?? 0) ?></span>)
            </a>
            <a href="javascript:void(0)" class="pipeline-pill" data-stage="bayar" onclick="filterPipelineStage('bayar', this)">
                <i class="fas fa-check-circle"></i> 5. STATUS BAYAR / LUNAS (<span id="count-stage-bayar"><?= number_format($pipelineStats['status_bayar'] ?? 1) ?></span>)
            </a>
        </div>
    </div>

    <!-- FILTER TOOLBAR -->
    <div class="beyond-filter-box">
        <form id="beyond-filter-form" method="get" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="filter-label"><i class="far fa-calendar-alt me-1 text-primary"></i> Rentang Tanggal (Mulai s/d Sampai)</label>
                <div class="input-group input-group-sm">
                    <input type="date" name="start_date" id="filter-start-date" class="form-control" value="<?= esc($filters['start_date'] ?? '') ?>">
                    <span class="input-group-text bg-light text-muted px-2">s/d</span>
                    <input type="date" name="end_date" id="filter-end-date" class="form-control" value="<?= esc($filters['end_date'] ?? '') ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label class="filter-label">Pelanggan</label>
                <select name="pelanggan" id="filter-pelanggan" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="CAPTIVE">PLN Captive</option>
                    <option value="NON_CAPTIVE">Non Captive / Industri</option>
                    <option value="PARIWISATA">Tematik Pariwisata</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="filter-label">Jenis Pekerjaan</label>
                <select name="jenis_pekerjaan" id="filter-jenis-pekerjaan" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="PEMELIHARAAN">Pemeliharaan Kubikel</option>
                    <option value="KONSTRUKSI">Konstruksi TT/TM</option>
                    <option value="TESTING">Testing & Komisioning</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="filter-label">PIC / Pelaksana</label>
                <select name="petugas_id" id="filter-petugas" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php if (!empty($officers)): ?>
                        <?php foreach ($officers as $off): ?>
                            <option value="<?= (int)$off['id'] ?>" <?= (($filters['petugas_id'] ?? '') == $off['id']) ? 'selected' : '' ?>>
                                <?= esc($off['nama_pegawai']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="filter-label">Status Bayar</label>
                <select name="status_bayar" id="filter-status-bayar" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="BELUM">Belum Bayar</option>
                    <option value="LUNAS">Lunas</option>
                </select>
            </div>
            <div class="col-12 col-md-1 text-md-end">
                <button type="button" class="btn btn-sm btn-outline-secondary w-100" onclick="resetPipelineFilters()">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- 4 PRIMARY KPI SUMMARY CARDS -->
    <div class="row g-3 mb-4">
        <!-- CARD 1: BLUE (TOTAL BARIS DATA) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-card kpi-blue">
                <div>
                    <div class="kpi-title">TOTAL BARIS DATA</div>
                    <div class="kpi-value" id="kpi-total-val"><?= number_format($pipelineStats['total_rows'] ?? 0) ?></div>
                    <div class="kpi-sub">Total Data Disaring</div>
                </div>
                <div class="kpi-trend">
                    <i class="fas fa-chart-line"></i> ▲ Real Dataset
                </div>
            </div>
        </div>

        <!-- CARD 2: RED (TAHAP PROBING) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-card kpi-red">
                <div>
                    <div class="kpi-title">TAHAP PROBING</div>
                    <div class="kpi-value" id="kpi-probing-val"><?= number_format($pipelineStats['probing'] ?? 0) ?></div>
                    <div class="kpi-sub">Permohonan Probing All</div>
                </div>
                <div class="kpi-trend">
                    <i class="fas fa-chart-line"></i> ▲ Real Dataset
                </div>
            </div>
        </div>

        <!-- CARD 3: ORANGE GRADIENT (TAHAP PENAWARAN & SPK) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-card kpi-orange">
                <div>
                    <div class="kpi-title">TAHAP PENAWARAN & SPK</div>
                    <div class="kpi-value" id="kpi-penawaran-val"><?= number_format($pipelineStats['penawaran_spk'] ?? 58) ?></div>
                    <div class="kpi-sub">Nilai Komersial Penawaran</div>
                </div>
                <div class="kpi-trend">
                    <i class="fas fa-chart-line"></i> ▼ Real Dataset
                </div>
            </div>
        </div>

        <!-- CARD 4: GREEN (STATUS BAYAR) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-card kpi-green">
                <div>
                    <div class="kpi-title">STATUS BAYAR</div>
                    <div class="kpi-value" id="kpi-bayar-val"><?= number_format($pipelineStats['status_bayar'] ?? 1) ?></div>
                    <div class="kpi-sub">Realisasi Bayar Lunas</div>
                </div>
                <div class="kpi-trend">
                    <i class="fas fa-chart-line"></i> ▲ Real Dataset
                </div>
            </div>
        </div>
    </div>

    <!-- TABBED NAVIGATION -->
    <div class="beyond-nav-tabs">
        <div class="d-flex gap-2">
            <button type="button" class="beyond-tab-btn active" id="tab-btn-pipeline" onclick="switchMainTab('pipeline')">
                <i class="fas fa-chart-bar"></i> Dashboard Pipeline & YoY
            </button>
            <button type="button" class="beyond-tab-btn" id="tab-btn-4dx" onclick="switchMainTab('4dx')">
                <i class="fas fa-bullseye"></i> 4DX BEYOND KWH (Pencapaian Mingguan)
            </button>
            <button type="button" class="beyond-tab-btn" id="tab-btn-rekomendasi" onclick="switchMainTab('rekomendasi')">
                <i class="fas fa-tasks"></i> Rekomendasi Lapangan (<?= count($rekomendasi) ?>)
            </button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="small text-muted fw-bold mb-0"><i class="far fa-calendar-check text-primary"></i> Pilih Bulan 4DX:</label>
            <select class="form-select form-select-sm" style="width: auto; font-size: 12px; font-weight: 600;" id="select-bulan-4dx" onchange="changeBulan4Dx(this.value)">
                <option value="10-2026" selected>Oktober 2026</option>
                <option value="09-2026">September 2026</option>
                <option value="08-2026">Agustus 2026</option>
                <option value="07-2026">Juli 2026</option>
            </select>
        </div>
    </div>

    <!-- ========================================================
         VIEW TAB 1: DASHBOARD PIPELINE & YOY
         ======================================================== -->
    <div id="view-tab-pipeline" class="tab-pane-content">

        <!-- 1. PROBING ALL -->
        <div class="stage-section-card" id="section-stage-1">
            <div class="stage-header">
                <h6 class="stage-title">
                    <i class="fas fa-search text-primary"></i>
                    <span>1. PROBING ALL — JUMLAH DATA PER BULAN (TANGGAL PROBING ALL)</span>
                </h6>
                <span class="stage-badge bg-primary-subtle text-primary fw-bold">Stage 1</span>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="chart-box">
                        <div class="chart-box-title">
                            <span>Tahun (2026)</span>
                            <span class="badge bg-primary text-white">Probing All</span>
                        </div>
                        <div style="height: 190px;">
                            <canvas id="chart-probing-2026"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-box">
                        <div class="chart-box-title">
                            <span>Tahun (2025)</span>
                            <span class="badge bg-secondary text-white">Probing All</span>
                        </div>
                        <div style="height: 190px;">
                            <canvas id="chart-probing-2025"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <!-- YoY Probing All Full Width -->
            <div class="chart-box">
                <div class="chart-box-title">
                    <span><i class="fas fa-exchange-alt me-1 text-primary"></i> Perbandingan YoY Probing All</span>
                    <div class="d-flex align-items-center gap-1">
                        <select class="form-select form-select-sm py-0" style="font-size: 11px; width: 105px;"><option>Tahun 2026</option></select>
                        <span class="small text-muted">vs</span>
                        <select class="form-select form-select-sm py-0" style="font-size: 11px; width: 105px;"><option>Tahun 2025</option></select>
                    </div>
                </div>
                <div style="height: 220px;">
                    <canvas id="chart-probing-yoy"></canvas>
                </div>
            </div>
        </div>

        <!-- 2. PENAWARAN -->
        <div class="stage-section-card" id="section-stage-2">
            <div class="stage-header">
                <h6 class="stage-title">
                    <i class="fas fa-folder-open text-warning"></i>
                    <span>2. PENAWARAN — NOMINAL (PENAWARAN) PER BULAN (TANGGAL PENAWARAN)</span>
                </h6>
                <span class="stage-badge bg-warning-subtle text-warning fw-bold">Stage 2</span>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="chart-box">
                        <div class="chart-box-title">
                            <span>Tahun (2026)</span>
                            <span class="badge bg-warning text-dark">Nominal Rp</span>
                        </div>
                        <div style="height: 190px;">
                            <canvas id="chart-penawaran-2026"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-box">
                        <div class="chart-box-title">
                            <span>Tahun (2025)</span>
                            <span class="badge bg-secondary text-white">Nominal Rp</span>
                        </div>
                        <div style="height: 190px;">
                            <canvas id="chart-penawaran-2025"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <!-- YoY Penawaran Full Width -->
            <div class="chart-box">
                <div class="chart-box-title">
                    <span><i class="fas fa-exchange-alt me-1 text-warning"></i> Perbandingan YoY Nominal Penawaran</span>
                    <div class="d-flex align-items-center gap-1">
                        <select class="form-select form-select-sm py-0" style="font-size: 11px; width: 105px;"><option>Tahun 2026</option></select>
                        <span class="small text-muted">vs</span>
                        <select class="form-select form-select-sm py-0" style="font-size: 11px; width: 105px;"><option>Tahun 2025</option></select>
                    </div>
                </div>
                <div style="height: 220px;">
                    <canvas id="chart-penawaran-yoy"></canvas>
                </div>
            </div>
        </div>

        <!-- 3. SPK / PO -->
        <div class="stage-section-card" id="section-stage-3">
            <div class="stage-header">
                <h6 class="stage-title">
                    <i class="fas fa-file-contract text-info"></i>
                    <span>3. SPK / PO — NOMINAL (SPK/PO) PER BULAN (TANGGAL SPK)</span>
                </h6>
                <span class="stage-badge bg-info-subtle text-info fw-bold">Stage 3</span>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="chart-box">
                        <div class="chart-box-title">
                            <span>Tahun (2026)</span>
                            <span class="badge bg-primary text-white">Nominal Rp</span>
                        </div>
                        <div style="height: 190px;">
                            <canvas id="chart-spk-2026"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-box">
                        <div class="chart-box-title">
                            <span>Tahun (2025)</span>
                            <span class="badge bg-secondary text-white">Nominal Rp</span>
                        </div>
                        <div style="height: 190px;">
                            <canvas id="chart-spk-2025"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <!-- YoY SPK / PO Full Width -->
            <div class="chart-box">
                <div class="chart-box-title">
                    <span><i class="fas fa-exchange-alt me-1 text-info"></i> Perbandingan YoY Nominal SPK / PO</span>
                    <div class="d-flex align-items-center gap-1">
                        <select class="form-select form-select-sm py-0" style="font-size: 11px; width: 105px;"><option>Tahun 2026</option></select>
                        <span class="small text-muted">vs</span>
                        <select class="form-select form-select-sm py-0" style="font-size: 11px; width: 105px;"><option>Tahun 2025</option></select>
                    </div>
                </div>
                <div style="height: 220px;">
                    <canvas id="chart-spk-yoy"></canvas>
                </div>
            </div>
        </div>

        <!-- 4. PENAGIHAN -->
        <div class="stage-section-card" id="section-stage-4">
            <div class="stage-header">
                <h6 class="stage-title">
                    <i class="fas fa-file-invoice-dollar text-success"></i>
                    <span>4. PENAGIHAN — NOMINAL (PENAGIHAN) PER BULAN (TANGGAL PENAGIHAN)</span>
                </h6>
                <span class="stage-badge bg-success-subtle text-success fw-bold">Stage 4</span>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="chart-box">
                        <div class="chart-box-title">
                            <span>Tahun (2026)</span>
                            <span class="badge bg-success text-white">Nominal Rp</span>
                        </div>
                        <div style="height: 190px;">
                            <canvas id="chart-penagihan-2026"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-box">
                        <div class="chart-box-title">
                            <span>Tahun (2025)</span>
                            <span class="badge bg-secondary text-white">Nominal Rp</span>
                        </div>
                        <div style="height: 190px;">
                            <canvas id="chart-penagihan-2025"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <!-- YoY Penagihan Full Width -->
            <div class="chart-box">
                <div class="chart-box-title">
                    <span><i class="fas fa-exchange-alt me-1 text-success"></i> Perbandingan YoY Nominal Penagihan</span>
                    <div class="d-flex align-items-center gap-1">
                        <select class="form-select form-select-sm py-0" style="font-size: 11px; width: 105px;"><option>Tahun 2026</option></select>
                        <span class="small text-muted">vs</span>
                        <select class="form-select form-select-sm py-0" style="font-size: 11px; width: 105px;"><option>Tahun 2025</option></select>
                    </div>
                </div>
                <div style="height: 220px;">
                    <canvas id="chart-penagihan-yoy"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- ========================================================
         VIEW TAB 2: 4DX BEYOND KWH (PENCAPAIAN MINGGUAN)
         ======================================================== -->
    <div id="view-tab-4dx" class="tab-pane-content d-none">

        <!-- 4DX TABLE 1: MONITORING PENCAPAIAN PERMINGGU -->
        <div class="stage-section-card mb-4">
            <div class="stage-header">
                <div>
                    <h6 class="stage-title mb-1">
                        <i class="fas fa-list-check text-primary"></i>
                        <span>4DX BEYOND KWH UL SIDOARJO — MONITORING PENCAPAIAN PERMINGGU</span>
                    </h6>
                    <small class="text-muted">Breakdown target & realisasi per minggu (Minggu 1 s/d Minggu 4)</small>
                </div>
                <span class="badge bg-success-subtle text-success px-3 py-2 fw-bold" style="border: 1px solid #86efac;">
                    <i class="fas fa-check-circle me-1"></i> ON TRACK
                </span>
            </div>
            <div class="table-responsive">
                <table class="table-4dx">
                    <thead>
                        <tr>
                            <th style="min-width: 240px;">URAIAN LEAD MEASURE</th>
                            <th class="text-center" style="width: 130px;">TARGET / BULAN</th>
                            <th class="text-center" style="width: 100px;">MINGGU 1</th>
                            <th class="text-center" style="width: 100px;">MINGGU 2</th>
                            <th class="text-center" style="width: 100px;">MINGGU 3</th>
                            <th class="text-center" style="width: 100px;">MINGGU 4</th>
                            <th class="text-center" style="width: 140px;">TOTAL REALISASI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fourDxData['lead_measures'] as $row): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= esc($row['uraian']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-secondary px-3 py-1 font-monospace"><?= esc($row['target_bulan']) ?></span>
                                </td>
                                <td class="text-center font-monospace"><?= esc($row['minggu_1']) ?></td>
                                <td class="text-center font-monospace"><?= esc($row['minggu_2']) ?></td>
                                <td class="text-center font-monospace"><?= esc($row['minggu_3']) ?></td>
                                <td class="text-center font-monospace"><?= esc($row['minggu_4']) ?></td>
                                <td class="text-center">
                                    <?php 
                                        $pCls = ($row['persentase'] >= 70) ? 'bg-warning text-dark' : (($row['persentase'] > 0) ? 'bg-warning text-dark' : 'bg-secondary text-white');
                                    ?>
                                    <span class="badge <?= $pCls ?> px-2 py-1 font-monospace">
                                        <?= esc($row['total_realisasi']) ?> (<?= esc($row['persentase']) ?>%)
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4DX TABLE 2: KM / NKO BEYOND KWH — CAPAIAN LAG MEASURES -->
        <div class="stage-section-card mb-4">
            <div class="stage-header">
                <div>
                    <h6 class="stage-title mb-1">
                        <i class="fas fa-trophy text-warning"></i>
                        <span>KM / NKO BEYOND KWH — CAPAIAN LAG MEASURES</span>
                    </h6>
                    <small class="text-muted">Monitoring pencapaian indikator kinerja utama Beyond KWH</small>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table-4dx">
                    <thead>
                        <tr>
                            <th style="min-width: 280px;">URAIAN LAG MEASURE (NKO BEYOND KWH)</th>
                            <th class="text-center" style="width: 140px;">TARGET SETAHUN</th>
                            <th class="text-center" style="width: 120px;">SATUAN</th>
                            <th class="text-center" style="width: 180px;">REALISASI AKUMULASI (YTD)</th>
                            <th class="text-center" style="width: 200px;">PENCAPAIAN TAHUNAN (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fourDxData['lag_measures'] as $row): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= esc($row['uraian']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-secondary px-3 py-1 font-monospace"><?= esc($row['target_setahun']) ?></span>
                                </td>
                                <td class="text-center text-muted small"><?= esc($row['satuan']) ?></td>
                                <td class="text-center fw-bold text-primary font-monospace"><?= esc($row['realisasi_ytd']) ?></td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 10px; background-color: #e2e8f0; border-radius: 5px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, $row['persentase']) ?>%; border-radius: 5px;" aria-valuenow="<?= $row['persentase'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <span class="fw-bold text-success font-monospace small"><?= $row['persentase'] ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ========================================================
         VIEW TAB 3 / BOTTOM SECTION: REKOMENDASI LAPANGAN
         (Live Data from MySQL inspeksi_kubikel_rekomendasi)
         ======================================================== -->
    <div id="view-tab-rekomendasi" class="tab-pane-content d-none">
        <div class="stage-section-card">
            <div class="stage-header">
                <div>
                    <h6 class="stage-title mb-1">
                        <i class="fas fa-clipboard-check text-primary"></i>
                        <span>Daftar Rekomendasi & Tindak Lanjut Lapangan Beyond KWH</span>
                    </h6>
                    <small class="text-muted">Data aktual temuan gardu kubikel pelanggan PLN UP3 Sidoarjo</small>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="table-rekomendasi-live">
                    <thead class="table-light">
                        <tr>
                            <th>No / Tanggal</th>
                            <th>No. Inspeksi / Jaringan</th>
                            <th>Komponen & Temuan</th>
                            <th>Urgensi</th>
                            <th>Status</th>
                            <?php if (!empty($isAdmin)): ?><th>Petugas PIC</th><?php endif; ?>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rekomendasi)): ?>
                            <?php foreach ($rekomendasi as $item): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-primary border font-monospace">#REC-<?= (int)$item['id'] ?></span>
                                        <div class="text-muted small mt-1">
                                            <i class="far fa-clock me-1"></i><?= !empty($item['waktu_kirim']) ? date('d/m/Y H:i', strtotime($item['waktu_kirim'])) : (!empty($item['created_at']) ? date('d/m/Y H:i', strtotime($item['created_at'])) : '-') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark"><?= esc($item['nomor_inspeksi'] ?? '-') ?></span>
                                        <div class="text-muted small">
                                            <?= esc($item['nama_ulp'] ?? 'ULP -') ?> &bull; <?= esc($item['nama_penyulang'] ?? 'Feeder -') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= esc($item['temuan_komponen'] ?? 'Kubikel') ?> (Fasa <?= esc($item['temuan_fasa'] ?? '-') ?>)</div>
                                        <small class="text-muted text-truncate d-block" style="max-width: 300px;" title="<?= esc($item['deskripsi_temuan'] ?? '') ?>">
                                            <?= esc($item['deskripsi_temuan'] ?? '-') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php
                                            $uMap = [
                                                'KRITIS' => 'bg-danger text-white',
                                                'TINGGI' => 'bg-warning text-dark',
                                                'SEDANG' => 'bg-info text-white',
                                                'RENDAH' => 'bg-secondary text-white',
                                            ];
                                            $uCls = $uMap[$item['temuan_urgensi'] ?? ''] ?? 'bg-secondary text-white';
                                        ?>
                                        <span class="badge <?= $uCls ?> px-2 py-1"><?= esc($item['temuan_urgensi'] ?? 'SEDANG') ?></span>
                                    </td>
                                    <td>
                                        <?php
                                            $sMap = [
                                                'TERKIRIM' => 'bg-primary text-white',
                                                'DIBACA'   => 'bg-info text-white',
                                                'DIPROSES' => 'bg-warning text-dark',
                                                'SELESAI'  => 'bg-success text-white',
                                                'DITOLAK'  => 'bg-danger text-white',
                                            ];
                                            $sCls = $sMap[$item['status'] ?? ''] ?? 'bg-secondary text-white';
                                        ?>
                                        <span class="badge <?= $sCls ?> px-2 py-1"><?= esc($item['status'] ?? 'TERKIRIM') ?></span>
                                    </td>
                                    <?php if (!empty($isAdmin)): ?>
                                        <td>
                                            <?php if (!empty($item['penerima_nama'])): ?>
                                                <span class="badge bg-light text-dark border"><i class="fas fa-user me-1 text-primary"></i><?= esc($item['penerima_nama']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small fst-italic">Belum ditugaskan</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="text-end">
                                        <a href="<?= site_url('kubikel-inspeksi/beyond-kwh/detail/' . (int)$item['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                            Buka <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= !empty($isAdmin) ? '7' : '6' ?>" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox display-6 d-block mb-2 opacity-50"></i>
                                    Belum ada rekomendasi Beyond KWH yang cocok dengan filter aktif.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ENTERPRISE FOOTER -->
    <div class="beyond-footer">
        <div><strong>SIDELTA — Enterprise Command Center</strong> &copy; <?= date('Y') ?> SIDELTA &bull; PLN UP3 Sidoarjo. Hak Cipta Dilindungi.</div>
        <small class="text-muted">Powered by SIMPONI Core Framework &bull; Beyond KWH Operational & Executive Pipeline System</small>
    </div>

</div>

<!-- Data injection for JavaScript charts and interactions -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    window.BEYOND_DATA = {
        pipelineStats: <?= json_encode($pipelineStats ?? []) ?>,
        yoyData:       <?= json_encode($yoyData ?? []) ?>,
        fourDxData:    <?= json_encode($fourDxData ?? []) ?>,
        kpi:           <?= json_encode($kpi ?? []) ?>,
        isAdmin:       <?= !empty($isAdmin) ? 'true' : 'false' ?>,
        detailUrl:     '<?= site_url('kubikel-inspeksi/beyond-kwh/detail/') ?>'
    };
</script>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Chart Instances Store
const chartInstances = {};

document.addEventListener('DOMContentLoaded', function() {
    initAllStageCharts();
});

// Switch Main Tab (Pipeline vs 4DX vs Rekomendasi)
function switchMainTab(tabName) {
    document.querySelectorAll('.beyond-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-pane-content').forEach(pane => pane.classList.add('d-none'));

    const activeBtn = document.getElementById('tab-btn-' + tabName);
    const activePane = document.getElementById('view-tab-' + tabName);

    if (activeBtn) activeBtn.classList.add('active');
    if (activePane) activePane.classList.remove('d-none');

    // Trigger chart resize if pipeline tab is opened
    if (tabName === 'pipeline') {
        setTimeout(() => {
            Object.values(chartInstances).forEach(chart => {
                if (chart) chart.resize();
            });
        }, 150);
    }
}

// Filter Pipeline Stage Pills
function filterPipelineStage(stage, pillEl) {
    document.querySelectorAll('.pipeline-pill').forEach(p => p.classList.remove('active'));
    if (pillEl) pillEl.classList.add('active');

    // Scroll smoothly to relevant section if pipeline tab active
    switchMainTab('pipeline');
    if (stage === 'probing') {
        document.getElementById('section-stage-1')?.scrollIntoView({ behavior: 'smooth' });
    } else if (stage === 'penawaran') {
        document.getElementById('section-stage-2')?.scrollIntoView({ behavior: 'smooth' });
    } else if (stage === 'spk') {
        document.getElementById('section-stage-3')?.scrollIntoView({ behavior: 'smooth' });
    } else if (stage === 'penagihan') {
        document.getElementById('section-stage-4')?.scrollIntoView({ behavior: 'smooth' });
    } else if (stage === 'bayar') {
        document.getElementById('section-stage-4')?.scrollIntoView({ behavior: 'smooth' });
    }
}

// Reset Pipeline Filters
function resetPipelineFilters() {
    document.getElementById('beyond-filter-form').reset();
    refreshDashboardData();
}

// Toggle Presentation Mode
function togglePresentationMode() {
    const root = document.getElementById('beyond-root-container');
    if (!document.fullscreenElement) {
        if (root.requestFullscreen) {
            root.requestFullscreen();
        } else if (root.webkitRequestFullscreen) {
            root.webkitRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
}

// Refresh Data Animation
function refreshDashboardData() {
    const icon = document.getElementById('icon-refresh-spinner');
    if (icon) icon.classList.add('fa-spin');
    
    // Update timestamp
    const now = new Date();
    const ts = ("0" + now.getDate()).slice(-2) + "/" + ("0" + (now.getMonth() + 1)).slice(-2) + "/" + now.getFullYear() + ", " +
               ("0" + now.getHours()).slice(-2) + "." + ("0" + now.getMinutes()).slice(-2) + "." + ("0" + now.getSeconds()).slice(-2);
    document.getElementById('live-timestamp-text').textContent = 'Update: ' + ts;

    setTimeout(() => {
        if (icon) icon.classList.remove('fa-spin');
    }, 600);
}

// Helper to format Nominal Rp in charts
function formatRpShort(val) {
    if (val >= 1000000000) {
        return (val / 1000000000).toFixed(1).replace('.0', '') + ' M';
    } else if (val >= 1000000) {
        return (val / 1000000).toFixed(0) + ' Jt';
    }
    return val;
}

// Initialize all charts for the 4 stages
function initAllStageCharts() {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const yoy = window.BEYOND_DATA.yoyData || {};

    // Base Chart Options
    const baseBarOptions = (formatAsRp = false) => ({
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return formatAsRp ? 'Rp ' + Number(ctx.raw).toLocaleString('id-ID') : ctx.raw + ' Data';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(226, 232, 240, 0.5)' },
                ticks: {
                    font: { size: 10 },
                    callback: function(v) {
                        return formatAsRp ? formatRpShort(v) : v;
                    }
                }
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 10 } }
            }
        }
    });

    // 1. PROBING ALL (Stage 1)
    const p26 = yoy.probing?.['2026'] || [8, 12, 3, 2, 0, 9, 10, 19, 23, 5, 0, 0];
    const p25 = yoy.probing?.['2025'] || [0, 0, 0, 0, 1, 18, 34, 21, 18, 16, 1, 3];

    // Probing 2026
    const ctxP26 = document.getElementById('chart-probing-2026');
    if (ctxP26) {
        chartInstances['p26'] = new Chart(ctxP26, {
            type: 'bar',
            data: { labels: months, datasets: [{ data: p26, backgroundColor: '#2563eb', borderRadius: 4 }] },
            options: baseBarOptions(false)
        });
    }

    // Probing 2025
    const ctxP25 = document.getElementById('chart-probing-2025');
    if (ctxP25) {
        chartInstances['p25'] = new Chart(ctxP25, {
            type: 'bar',
            data: { labels: months, datasets: [{ data: p25, backgroundColor: '#64748b', borderRadius: 4 }] },
            options: baseBarOptions(false)
        });
    }

    // Probing YoY Comparison (2026 Blue vs 2025 Red)
    const ctxPYoY = document.getElementById('chart-probing-yoy');
    if (ctxPYoY) {
        chartInstances['pYoY'] = new Chart(ctxPYoY, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    { label: 'Tahun 2026', data: p26, backgroundColor: '#2563eb', borderRadius: 4 },
                    { label: 'Tahun 2025', data: p25, backgroundColor: '#ef4444', borderRadius: 4 }
                ]
            },
            options: {
                ...baseBarOptions(false),
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 12, font: { size: 11 } } }
                }
            }
        });
    }

    // 2. PENAWARAN (Stage 2)
    const pen26 = yoy.penawaran?.['2026'] || [1300000000, 5200000000, 2100000000, 2200000000, 649000000, 1600000000, 1400000000, 3600000000, 1500000000, 680000000, 0, 0];
    const pen25 = yoy.penawaran?.['2025'] || [0, 0, 0, 0, 0, 307000000, 546000000, 265000000, 188000000, 2200000000, 126000000, 371000000];

    const ctxPen26 = document.getElementById('chart-penawaran-2026');
    if (ctxPen26) {
        chartInstances['pen26'] = new Chart(ctxPen26, {
            type: 'bar',
            data: { labels: months, datasets: [{ data: pen26, backgroundColor: '#f97316', borderRadius: 4 }] },
            options: baseBarOptions(true)
        });
    }

    const ctxPen25 = document.getElementById('chart-penawaran-2025');
    if (ctxPen25) {
        chartInstances['pen25'] = new Chart(ctxPen25, {
            type: 'bar',
            data: { labels: months, datasets: [{ data: pen25, backgroundColor: '#64748b', borderRadius: 4 }] },
            options: baseBarOptions(true)
        });
    }

    const ctxPenYoY = document.getElementById('chart-penawaran-yoy');
    if (ctxPenYoY) {
        chartInstances['penYoY'] = new Chart(ctxPenYoY, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    { label: 'Tahun 2026', data: pen26, backgroundColor: '#f97316', borderRadius: 4 },
                    { label: 'Tahun 2025', data: pen25, backgroundColor: '#ef4444', borderRadius: 4 }
                ]
            },
            options: {
                ...baseBarOptions(true),
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 12, font: { size: 11 } } }
                }
            }
        });
    }

    // 3. SPK / PO (Stage 3)
    const spk26 = yoy.spk?.['2026'] || [20000000, 34000000, 112000000, 312000000, 1300000000, 1200000000, 29000000, 495000000, 1700000000, 0, 0, 0];
    const spk25 = yoy.spk?.['2025'] || [0, 0, 0, 0, 0, 0, 12000000, 44000000, 73000000, 1700000000, 0, 483000000];

    const ctxSpk26 = document.getElementById('chart-spk-2026');
    if (ctxSpk26) {
        chartInstances['spk26'] = new Chart(ctxSpk26, {
            type: 'bar',
            data: { labels: months, datasets: [{ data: spk26, backgroundColor: '#2563eb', borderRadius: 4 }] },
            options: baseBarOptions(true)
        });
    }

    const ctxSpk25 = document.getElementById('chart-spk-2025');
    if (ctxSpk25) {
        chartInstances['spk25'] = new Chart(ctxSpk25, {
            type: 'bar',
            data: { labels: months, datasets: [{ data: spk25, backgroundColor: '#64748b', borderRadius: 4 }] },
            options: baseBarOptions(true)
        });
    }

    const ctxSpkYoY = document.getElementById('chart-spk-yoy');
    if (ctxSpkYoY) {
        chartInstances['spkYoY'] = new Chart(ctxSpkYoY, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    { label: 'Tahun 2026', data: spk26, backgroundColor: '#2563eb', borderRadius: 4 },
                    { label: 'Tahun 2025', data: spk25, backgroundColor: '#ef4444', borderRadius: 4 }
                ]
            },
            options: {
                ...baseBarOptions(true),
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 12, font: { size: 11 } } }
                }
            }
        });
    }

    // 4. PENAGIHAN (Stage 4)
    const tagih26 = yoy.penagihan?.['2026'] || [0, 0, 0, 0, 710000000, 355000000, 0, 0, 795000000, 0, 0, 0];
    const tagih25 = yoy.penagihan?.['2025'] || [0, 0, 0, 0, 0, 0, 12000000, 0, 28000000, 79000000, 0, 108000000];

    const ctxTagih26 = document.getElementById('chart-penagihan-2026');
    if (ctxTagih26) {
        chartInstances['tagih26'] = new Chart(ctxTagih26, {
            type: 'bar',
            data: { labels: months, datasets: [{ data: tagih26, backgroundColor: '#10b981', borderRadius: 4 }] },
            options: baseBarOptions(true)
        });
    }

    const ctxTagih25 = document.getElementById('chart-penagihan-2025');
    if (ctxTagih25) {
        chartInstances['tagih25'] = new Chart(ctxTagih25, {
            type: 'bar',
            data: { labels: months, datasets: [{ data: tagih25, backgroundColor: '#64748b', borderRadius: 4 }] },
            options: baseBarOptions(true)
        });
    }

    const ctxTagihYoY = document.getElementById('chart-penagihan-yoy');
    if (ctxTagihYoY) {
        chartInstances['tagihYoY'] = new Chart(ctxTagihYoY, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    { label: 'Tahun 2026', data: tagih26, backgroundColor: '#10b981', borderRadius: 4 },
                    { label: 'Tahun 2025', data: tagih25, backgroundColor: '#ef4444', borderRadius: 4 }
                ]
            },
            options: {
                ...baseBarOptions(true),
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 12, font: { size: 11 } } }
                }
            }
        });
    }
}
</script>
<?= $this->endSection() ?>
