<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Executive Dashboard & Analytics - SIDAK TEJO<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Enterprise Executive Dashboard Engine (Phase 14) -->
<div class="container-fluid px-3 py-3">
    
    <!-- Top Executive Header & Toolbar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom" style="gap: 12px;">
        <div>
            <h4 class="fw-bold mb-1 text-primary d-flex align-items-center" style="font-family: 'Outfit', sans-serif; letter-spacing: -0.5px;">
                <i class="fas fa-chart-line text-warning me-2 fs-3"></i>
                EXECUTIVE DASHBOARD & ANALYTICS
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px; letter-spacing: 0.5px;">ENTERPRISE V14</span>
            </h4>
            <p class="text-muted small mb-0">Realtime KPI, Monitoring Field Operations, Workload, SLA Tracking & GIS Heatmap</p>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2">
            <!-- Auto Refresh Indicator -->
            <div class="bg-white p-2 rounded-3 border shadow-xs d-flex align-items-center me-1" style="font-size: 11px;">
                <div class="form-check form-switch mb-0 me-2" title="Toggle Auto Refresh 30 Detik">
                    <input class="form-check-input" type="checkbox" id="auto-refresh-toggle" checked style="cursor: pointer;">
                    <label class="form-check-label fw-bold text-dark" for="auto-refresh-toggle">Auto 30s</label>
                </div>
                <span id="refresh-timer-badge" class="badge bg-light text-primary border me-2" style="font-size: 10px;">30s</span>
                <button type="button" id="btn-manual-sync" class="btn btn-xs btn-outline-primary rounded-circle" title="Refresh Sekarang">
                    <i class="fas fa-sync-alt" id="sync-icon-spin"></i>
                </button>
            </div>

            <!-- Export Toolbar -->
            <div class="btn-group shadow-sm">
                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle font-weight-bold" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 8px;">
                    <i class="fas fa-download me-1 text-primary"></i> Ekspor Report
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="font-size: 13px;">
                    <li><a class="dropdown-item py-2" href="#" onclick="exportExecutiveDashboard('pdf')"><i class="fas fa-file-pdf text-danger me-2"></i> Ekspor PDF Report</a></li>
                    <li><a class="dropdown-item py-2" href="#" onclick="exportExecutiveDashboard('excel')"><i class="fas fa-file-excel text-success me-2"></i> Ekspor Data Excel (.xlsx)</a></li>
                    <li><a class="dropdown-item py-2" href="#" onclick="exportExecutiveDashboard('csv')"><i class="fas fa-file-csv text-info me-2"></i> Ekspor Data CSV</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2" href="#" onclick="window.print()"><i class="fas fa-print text-dark me-2"></i> Print Dashboard View</a></li>
                    <li><a class="dropdown-item py-2" href="#" onclick="captureDashboardScreenshot()"><i class="fas fa-camera text-primary me-2"></i> Screenshot Dashboard (PNG)</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Navigation Module Tabs (Module 1) -->
    <ul class="nav nav-pills custom-executive-tabs mb-3 p-1 rounded-3 bg-white border shadow-xs" id="execDashboardTabs" role="tablist" style="gap: 4px;">
        <li class="nav-item" role="presentation">
            <button class="nav-link active font-weight-bold px-3 py-2" id="tab-exec-btn" data-bs-toggle="pill" data-bs-target="#tab-executive" type="button" role="tab">
                <i class="fas fa-building-user me-1 text-primary"></i> Dashboard Executive
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link font-weight-bold px-3 py-2" id="tab-ops-btn" data-bs-toggle="pill" data-bs-target="#tab-operasional" type="button" role="tab">
                <i class="fas fa-screwdriver-wrench me-1 text-warning"></i> Dashboard Operasional
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link font-weight-bold px-3 py-2" id="tab-mon-btn" data-bs-toggle="pill" data-bs-target="#tab-monitoring" type="button" role="tab">
                <i class="fas fa-map-location-dot me-1 text-success"></i> Monitoring & GIS Heatmap
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link font-weight-bold px-3 py-2" id="tab-ai-btn" data-bs-toggle="pill" data-bs-target="#tab-ai" type="button" role="tab">
                <i class="fas fa-brain me-1 text-info"></i> Dashboard AI & Predictive
            </button>
        </li>
    </ul>

    <!-- Realtime Multi-Filter Toolbar (Module 9) -->
    <div class="card border-0 shadow-sm rounded-18 mb-4 overflow-hidden" style="background: #ffffff;">
        <div class="card-header py-2 px-3 bg-light border-bottom d-flex align-items-center justify-content-between">
            <span class="font-weight-bold text-secondary" style="font-size: 12px; letter-spacing: 0.5px;">
                <i class="fas fa-filter text-primary me-1"></i> FILTER REALTIME ANALYTICS
            </span>
            <small class="text-muted" id="filter-applied-count">0 Filter Aktif</small>
        </div>
        <div class="card-body p-3">
            <form id="execFilterForm" class="row g-2">
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label text-muted small fw-bold mb-1">ULP Unit</label>
                    <select class="form-select form-select-sm" id="flt_ulp_id" name="ulp_id">
                        <option value="">-- Semua ULP --</option>
                        <?php foreach ($ulps as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= esc($u['nama_ulp']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label text-muted small fw-bold mb-1">Penyulang</label>
                    <select class="form-select form-select-sm" id="flt_penyulang_id" name="penyulang_id">
                        <option value="">-- Semua Penyulang --</option>
                        <?php foreach ($penyulangs as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= esc($p['nama_penyulang']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label text-muted small fw-bold mb-1">Jenis Temuan</label>
                    <select class="form-select form-select-sm" id="flt_jenis_temuan" name="jenis_temuan">
                        <option value="">-- Semua Jenis --</option>
                        <option value="KONSTRUKSI">KONSTRUKSI</option>
                        <option value="HOTSPOT">HOTSPOT</option>
                        <option value="ROW">ROW</option>
                        <option value="GARDU">GARDU</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label text-muted small fw-bold mb-1">Pelaksana</label>
                    <select class="form-select form-select-sm" id="flt_pelaksana" name="pelaksana">
                        <option value="">-- Semua Pelaksana --</option>
                        <option value="YANTEK">YANTEK</option>
                        <option value="PDKB">PDKB</option>
                        <option value="HAR GARDU">HAR GARDU</option>
                        <option value="HAR KONSTRUKSI">HAR KONSTRUKSI</option>
                        <option value="HAR ROW">HAR ROW</option>
                        <option value="HAR CRANE">HAR CRANE</option>
                        <option value="INSPEKSI">INSPEKSI</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label text-muted small fw-bold mb-1">Prioritas</label>
                    <select class="form-select form-select-sm" id="flt_prioritas" name="prioritas">
                        <option value="">-- Semua Prioritas --</option>
                        <option value="EMERGENCY">EMERGENCY</option>
                        <option value="HIGH">HIGH</option>
                        <option value="MEDIUM">MEDIUM</option>
                        <option value="LOW">LOW</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label text-muted small fw-bold mb-1">Status</label>
                    <select class="form-select form-select-sm" id="flt_status" name="status">
                        <option value="">-- Semua Status --</option>
                        <option value="BELUM">BELUM</option>
                        <option value="PROSES">PROSES</option>
                        <option value="SELESAI">SELESAI</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label text-muted small fw-bold mb-1">Tgl Mulai</label>
                    <input type="date" class="form-control form-control-sm" id="flt_tanggal_mulai" name="tanggal_mulai">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label text-muted small fw-bold mb-1">Tgl Selesai</label>
                    <input type="date" class="form-control form-control-sm" id="flt_tanggal_selesai" name="tanggal_selesai">
                </div>
                <div class="col-12 col-md-6 col-lg-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1 font-weight-bold" style="border-radius: 8px;">
                        <i class="fas fa-magnifying-glass me-1"></i> Terapkan Filter
                    </button>
                    <button type="button" id="btn-reset-filters" class="btn btn-sm btn-outline-secondary font-weight-bold" style="border-radius: 8px;">
                        <i class="fas fa-undo me-1"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab Contents Container -->
    <div class="tab-content" id="execDashboardTabContent">
        
        <!-- ============================================================ -->
        <!-- TAB 1: EXECUTIVE DASHBOARD                                   -->
        <!-- ============================================================ -->
        <div class="tab-pane fade show active" id="tab-executive" role="tabpanel">
            
            <!-- Realtime KPI Cards Grid (Module 2) -->
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card border-0 shadow-xs rounded-18 p-3 bg-gradient-blue text-white h-100">
                        <small class="text-uppercase fw-bold opacity-75" style="font-size: 10px;">Total Temuan</small>
                        <h3 class="fw-bold my-1" id="kpi-total"><?= number_format($initialData['kpi']['total_temuan'] ?? 0) ?></h3>
                        <div class="d-flex align-items-center justify-content-between text-white-50" style="font-size: 10px;">
                            <span>Tahun: <strong class="text-white"><?= number_format($initialData['kpi']['temuan_tahun_ini'] ?? 0) ?></strong></span>
                            <i class="fas fa-list-check"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card border-0 shadow-xs rounded-18 p-3 text-white h-100" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <small class="text-uppercase fw-bold opacity-75" style="font-size: 10px;">Temuan Selesai</small>
                        <h3 class="fw-bold my-1" id="kpi-selesai"><?= number_format($initialData['kpi']['selesai'] ?? 0) ?></h3>
                        <div class="d-flex align-items-center justify-content-between" style="font-size: 10px;">
                            <span>Rate: <strong class="text-white" id="kpi-rate"><?= $initialData['kpi']['persentase_selesai'] ?? 0 ?>%</strong></span>
                            <i class="fas fa-circle-check"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card border-0 shadow-xs rounded-18 p-3 text-white h-100" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <small class="text-uppercase fw-bold opacity-75" style="font-size: 10px;">Dalam Proses</small>
                        <h3 class="fw-bold my-1" id="kpi-proses"><?= number_format($initialData['kpi']['proses'] ?? 0) ?></h3>
                        <div class="d-flex align-items-center justify-content-between" style="font-size: 10px;">
                            <span>Belum: <strong class="text-white" id="kpi-belum"><?= number_format($initialData['kpi']['belum_dikerjakan'] ?? 0) ?></strong></span>
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card border-0 shadow-xs rounded-18 p-3 text-white h-100" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                        <small class="text-uppercase fw-bold opacity-75" style="font-size: 10px;">Overdue SLA</small>
                        <h3 class="fw-bold my-1" id="kpi-overdue"><?= number_format($initialData['kpi']['overdue'] ?? 0) ?></h3>
                        <div class="d-flex align-items-center justify-content-between" style="font-size: 10px;">
                            <span>SLA Met: <strong class="text-white" id="kpi-sla-rate"><?= $initialData['kpi']['persentase_sla_met'] ?? 0 ?>%</strong></span>
                            <i class="fas fa-triangle-exclamation"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card border-0 shadow-xs rounded-18 p-3 text-white h-100" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                        <small class="text-uppercase fw-bold opacity-75" style="font-size: 10px;">Target Bulanan</small>
                        <h3 class="fw-bold my-1" id="kpi-ach-bulanan"><?= $initialData['kpi']['ach_bulanan'] ?? 0 ?>%</h3>
                        <div class="d-flex align-items-center justify-content-between" style="font-size: 10px;">
                            <span>Input: <strong class="text-white" id="kpi-bulan-ini"><?= number_format($initialData['kpi']['temuan_bulan_ini'] ?? 0) ?></strong>/350</span>
                            <i class="fas fa-bullseye"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card border-0 shadow-xs rounded-18 p-3 text-white h-100" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);">
                        <small class="text-uppercase fw-bold opacity-75" style="font-size: 10px;">Temuan Hari Ini</small>
                        <h3 class="fw-bold my-1" id="kpi-hari-ini"><?= number_format($initialData['kpi']['temuan_hari_ini'] ?? 0) ?></h3>
                        <div class="d-flex align-items-center justify-content-between" style="font-size: 10px;">
                            <span>Minggu: <strong class="text-white" id="kpi-minggu-ini"><?= number_format($initialData['kpi']['temuan_minggu_ini'] ?? 0) ?></strong></span>
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart Analytics Row (Module 3) -->
            <div class="row g-3 mb-4">
                <!-- 1. Tren Harian Temuan & Realisasi -->
                <div class="col-12 col-xl-8">
                    <div class="card border-0 shadow-sm rounded-18 h-100">
                        <div class="card-header bg-white py-3 px-3 border-bottom d-flex align-items-center justify-content-between">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-area text-primary me-2"></i> Tren Temuan & Realisasi (14 Hari Terakhir)</h6>
                            <span class="badge bg-light text-dark border">Realtime</span>
                        </div>
                        <div class="card-body p-3" style="min-height: 280px;">
                            <canvas id="chartHarianCanvas" height="240"></canvas>
                        </div>
                    </div>
                </div>

                <!-- 2. Status Breakdown & SLA Compliance -->
                <div class="col-12 col-xl-4">
                    <div class="card border-0 shadow-sm rounded-18 h-100">
                        <div class="card-header bg-white py-3 px-3 border-bottom d-flex align-items-center justify-content-between">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-pie text-success me-2"></i> Progress & SLA Compliance</h6>
                            <span class="badge bg-light text-dark border">Kepatuhan</span>
                        </div>
                        <div class="card-body p-3 text-center d-flex flex-column align-items-center justify-content-center">
                            <div style="width: 220px; height: 220px;">
                                <canvas id="chartStatusCanvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Second Chart Row -->
            <div class="row g-3 mb-4">
                <!-- Temuan per ULP -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm rounded-18 h-100">
                        <div class="card-header bg-white py-3 px-3 border-bottom">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-building text-info me-2"></i> Performa Temuan per ULP Unit</h6>
                        </div>
                        <div class="card-body p-3">
                            <canvas id="chartUlpCanvas" height="220"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Temuan per Jenis & Prioritas -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm rounded-18 h-100">
                        <div class="card-header bg-white py-3 px-3 border-bottom">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-tags text-warning me-2"></i> Breakdown Jenis & Severity Prioritas</h6>
                        </div>
                        <div class="card-body p-3 row">
                            <div class="col-6">
                                <canvas id="chartJenisCanvas" height="200"></canvas>
                            </div>
                            <div class="col-6">
                                <canvas id="chartPrioritasCanvas" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rankings Section (Module 4 & 5) -->
            <div class="row g-3 mb-4">
                <!-- Ranking ULP -->
                <div class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm rounded-18">
                        <div class="card-header py-3 px-3 bg-gradient-blue text-white d-flex align-items-center justify-content-between rounded-top-18">
                            <h6 class="fw-bold mb-0"><i class="fas fa-trophy text-warning me-2"></i> Ranking Performa Seluruh ULP Unit</h6>
                            <span class="badge bg-light text-dark font-weight-normal" style="font-size: 10px;">Module 5</span>
                        </div>
                        <div class="card-body p-0 table-responsive" style="max-height: 320px;">
                            <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Nama ULP</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-center">Selesai</th>
                                        <th class="text-center">Proses</th>
                                        <th class="text-center">Belum</th>
                                        <th class="text-center">% Selesai</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-ulp-ranking">
                                    <?php foreach (($initialData['ulp_ranking'] ?? []) as $idx => $u): 
                                        $pct = $u['total'] > 0 ? round(($u['selesai'] / $u['total']) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td class="fw-bold"><?= $idx + 1 ?></td>
                                            <td class="fw-bold text-dark"><?= esc($u['nama_ulp']) ?></td>
                                            <td class="text-center fw-bold"><?= number_format($u['total']) ?></td>
                                            <td class="text-center text-success fw-bold"><?= number_format($u['selesai']) ?></td>
                                            <td class="text-center text-warning fw-bold"><?= number_format($u['proses']) ?></td>
                                            <td class="text-center text-danger fw-bold"><?= number_format($u['belum']) ?></td>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center justify-content-center gap-1">
                                                    <div class="progress flex-grow-1" style="height: 6px; min-width: 50px;">
                                                        <div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div>
                                                    </div>
                                                    <span class="fw-bold" style="font-size: 10px;"><?= $pct ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Top 10 Officer Leaderboard -->
                <div class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm rounded-18">
                        <div class="card-header py-3 px-3 bg-dark text-white d-flex align-items-center justify-content-between rounded-top-18">
                            <h6 class="fw-bold mb-0"><i class="fas fa-medal text-warning me-2"></i> Top 10 Executive Officer Ranking</h6>
                            <span class="badge bg-secondary font-weight-normal" style="font-size: 10px;">Module 4</span>
                        </div>
                        <div class="card-body p-0 table-responsive" style="max-height: 320px;">
                            <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Rank</th>
                                        <th>Nama Officer</th>
                                        <th>NIP</th>
                                        <th class="text-center">Input Temuan</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-top-officers">
                                    <?php foreach (($initialData['top_input_officers'] ?? []) as $idx => $off): ?>
                                        <tr>
                                            <td class="fw-bold">
                                                <span class="badge rounded-circle <?= $idx === 0 ? 'bg-warning text-dark' : ($idx === 1 ? 'bg-secondary text-white' : ($idx === 2 ? 'bg-danger text-white' : 'bg-light text-dark border')) ?>" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;">
                                                    <?= $idx + 1 ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold text-dark"><?= esc($off['created_by_name']) ?></td>
                                            <td class="text-muted" style="font-size: 10px;"><?= esc($off['created_by_nip'] ?: '-') ?></td>
                                            <td class="text-center fw-bold text-primary"><?= number_format($off['total_input']) ?></td>
                                            <td class="text-center"><span class="badge bg-success" style="font-size: 9px;">AKTIF</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ============================================================ -->
        <!-- TAB 2: DASHBOARD OPERASIONAL (Workload & Field)              -->
        <!-- ============================================================ -->
        <div class="tab-pane fade" id="tab-operasional" role="tabpanel">
            <div class="row g-3 mb-4">
                <!-- Workload Table per Pelaksana (Module 6) -->
                <div class="col-12 col-lg-7">
                    <div class="card border-0 shadow-sm rounded-18 h-100">
                        <div class="card-header bg-white py-3 px-3 border-bottom">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-users-gear text-primary me-2"></i> Distribusi Beban Kerja (Workload) Per Pelaksana</h6>
                        </div>
                        <div class="card-body p-3">
                            <canvas id="chartPelaksanaCanvas" height="240"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top 10 Penyulang Workload -->
                <div class="col-12 col-lg-5">
                    <div class="card border-0 shadow-sm rounded-18 h-100">
                        <div class="card-header bg-white py-3 px-3 border-bottom">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-network-wired text-warning me-2"></i> Top 10 Penyulang Beban Tertinggi</h6>
                        </div>
                        <div class="card-body p-3">
                            <canvas id="chartPenyulangCanvas" height="240"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 3: DASHBOARD MONITORING & GIS HEATMAP (Module 8 & SLA 7) -->
        <!-- ============================================================ -->
        <div class="tab-pane fade" id="tab-monitoring" role="tabpanel">
            <div class="row g-3 mb-4">
                <!-- GIS Heatmap Container -->
                <div class="col-12 col-xl-8">
                    <div class="card border-0 shadow-sm rounded-18 overflow-hidden">
                        <div class="card-header bg-dark text-white py-3 px-3 d-flex align-items-center justify-content-between">
                            <h6 class="fw-bold mb-0"><i class="fas fa-fire-flame-curved text-danger me-2"></i> GIS Heatmap & Marker Cluster Inspection Points</h6>
                            <span class="badge bg-danger" id="heatmap-pin-count">0 Pins</span>
                        </div>
                        <div class="card-body p-0">
                            <div id="gisHeatmapContainer" style="height: 480px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <!-- SLA Countdown & Risk Matrix (Module 7) -->
                <div class="col-12 col-xl-4">
                    <div class="card border-0 shadow-sm rounded-18 h-100">
                        <div class="card-header bg-white py-3 px-3 border-bottom">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-clock-rotate-left text-danger me-2"></i> SLA Risk Matrix & Overdue Monitor</h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-3 p-3 rounded-3" style="background: #fff5f5; border-left: 4px solid #ef4444;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-danger"><i class="fas fa-bolt me-1"></i> EMERGENCY (SLA 24 Jam)</span>
                                    <span class="badge bg-danger" id="sla-emergency-overdue">0 Overdue</span>
                                </div>
                                <small class="text-muted d-block mt-1">Status Kepatuhan: <strong id="sla-emergency-met">0</strong> / <span id="sla-emergency-total">0</span> Terpenuhi</small>
                            </div>

                            <div class="mb-3 p-3 rounded-3" style="background: #fff7ed; border-left: 4px solid #f97316;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-warning"><i class="fas fa-triangle-exclamation me-1"></i> HIGH (SLA 3 Hari)</span>
                                    <span class="badge bg-warning text-dark" id="sla-high-overdue">0 Overdue</span>
                                </div>
                                <small class="text-muted d-block mt-1">Status Kepatuhan: <strong id="sla-high-met">0</strong> / <span id="sla-high-total">0</span> Terpenuhi</small>
                            </div>

                            <div class="mb-3 p-3 rounded-3" style="background: #f0f9ff; border-left: 4px solid #0284c7;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-info"><i class="fas fa-circle-info me-1"></i> MEDIUM (SLA 7 Hari)</span>
                                    <span class="badge bg-info text-white" id="sla-medium-overdue">0 Overdue</span>
                                </div>
                                <small class="text-muted d-block mt-1">Status Kepatuhan: <strong id="sla-medium-met">0</strong> / <span id="sla-medium-total">0</span> Terpenuhi</small>
                            </div>

                            <div class="p-3 rounded-3" style="background: #f8fafc; border-left: 4px solid #64748b;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-secondary"><i class="fas fa-flag me-1"></i> LOW (SLA 14 Hari)</span>
                                    <span class="badge bg-secondary" id="sla-low-overdue">0 Overdue</span>
                                </div>
                                <small class="text-muted d-block mt-1">Status Kepatuhan: <strong id="sla-low-met">0</strong> / <span id="sla-low-total">0</span> Terpenuhi</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 4: DASHBOARD AI & PREDICTIVE ANALYTICS                  -->
        <!-- ============================================================ -->
        <div class="tab-pane fade" id="tab-ai" role="tabpanel">
            <div class="row g-3 mb-4">
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm rounded-18 p-4 text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border: 1px solid rgba(56, 189, 248, 0.3);">
                        <h5 class="fw-bold text-info"><i class="fas fa-brain me-2"></i> Predictive Risk Pattern Machine Learning</h5>
                        <p class="small text-light opacity-75 mb-3">Model kecerdasan buatan SIDAK TEJO memprediksi titik rawan gangguan jaringan berdasarkan histori inspeksi & kondisi fisik peralatan.</p>
                        
                        <div class="p-3 rounded-3 mb-2" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                            <div class="d-flex justify-content-between text-warning font-weight-bold small">
                                <span><i class="fas fa-shield-virus me-1"></i> Penyulang Rawan Hotspot #1</span>
                                <span>Skor Risiko: 88.4%</span>
                            </div>
                            <small class="text-muted">Rekomendasi: Pemeliharaan Termovisi ulang dalam 48 jam.</small>
                        </div>
                        <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                            <div class="d-flex justify-content-between text-info font-weight-bold small">
                                <span><i class="fas fa-tree me-1"></i> Penyulang Rawan ROW Jarak Dekat #2</span>
                                <span>Skor Risiko: 74.2%</span>
                            </div>
                            <small class="text-muted">Rekomendasi: Pemangkasan dahan pohon pelaksana HAR ROW.</small>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm rounded-18 h-100">
                        <div class="card-header bg-white py-3 px-3 border-bottom">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-robot text-primary me-2"></i> Rekomendasi Mitigasi Otomatis AI</h6>
                        </div>
                        <div class="card-body p-3">
                            <ul class="list-group list-group-flush small">
                                <li class="list-group-item d-flex align-items-center justify-content-between py-2">
                                    <span><i class="fas fa-check-double text-success me-2"></i> Alokasikan tim PDKB untuk penanganan hotspot urgent ULP Krian.</span>
                                    <span class="badge bg-success">Rekomendasi A1</span>
                                </li>
                                <li class="list-group-item d-flex align-items-center justify-content-between py-2">
                                    <span><i class="fas fa-check-double text-success me-2"></i> Percepat eksekusi 5 temuan konstruksi status BELUM > 5 hari.</span>
                                    <span class="badge bg-warning text-dark">Prioritas Tinggi</span>
                                </li>
                                <li class="list-group-item d-flex align-items-center justify-content-between py-2">
                                    <span><i class="fas fa-check-double text-success me-2"></i> Evaluasi beban kerja YANTEK pada penyulang berbeban maksimal.</span>
                                    <span class="badge bg-info">Optimalisasi</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Styles Specific for Executive Dashboard -->
<style>
    .custom-executive-tabs .nav-link {
        color: #475569;
        border-radius: 8px;
        transition: all 0.2s;
    }
    .custom-executive-tabs .nav-link.active {
        background-color: #005eb8 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(0, 94, 184, 0.3);
    }
    .rounded-18 {
        border-radius: 18px !important;
    }
    .rounded-top-18 {
        border-top-left-radius: 18px !important;
        border-top-right-radius: 18px !important;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<!-- Chart.js, Leaflet JS, & Screenshot Exporters -->
<script src="<?= base_url('plugins/chart.js') ?>"></script>
<script src="<?= base_url('plugins/leaflet.js') ?>"></script>
<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>

<script>
    let execCharts = {};
    let gisMap = null;
    let mapMarkersGroup = null;
    let refreshSeconds = 30;
    let refreshTimerInterval = null;

    $(function() {
        initExecutiveCharts();
        initGisHeatmap();
        startAutoRefreshTimer();

        // Filter submit handler
        $('#execFilterForm').on('submit', function(e) {
            e.preventDefault();
            fetchExecutiveData();
        });

        // Reset filter
        $('#btn-reset-filters').on('click', function() {
            $('#execFilterForm')[0].reset();
            fetchExecutiveData();
        });

        // Manual sync button
        $('#btn-manual-sync').on('click', function() {
            $('#sync-icon-spin').addClass('fa-spin');
            fetchExecutiveData(() => $('#sync-icon-spin').removeClass('fa-spin'));
        });

        // Initialize with initial PHP data
        applyDataToDashboard(<?= json_encode($initialData) ?>);
    });

    function startAutoRefreshTimer() {
        if (refreshTimerInterval) clearInterval(refreshTimerInterval);
        refreshSeconds = 30;

        refreshTimerInterval = setInterval(function() {
            if ($('#auto-refresh-toggle').is(':checked')) {
                refreshSeconds--;
                $('#refresh-timer-badge').text(refreshSeconds + 's');
                if (refreshSeconds <= 0) {
                    refreshSeconds = 30;
                    fetchExecutiveData();
                }
            }
        }, 1000);
    }

    function fetchExecutiveData(callback) {
        const formData = $('#execFilterForm').serialize();
        $.ajax({
            url: "<?= site_url('dashboard/executive-api') ?>?" + formData,
            type: "GET",
            dataType: "json",
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(res) {
                if (res.success && res.data) {
                    applyDataToDashboard(res.data);
                }
                if (callback) callback();
            },
            error: function() {
                if (callback) callback();
            }
        });
    }

    function applyDataToDashboard(data) {
        if (!data || !data.kpi) return;
        const k = data.kpi;

        // 1. Update KPI Cards
        $('#kpi-total').text(Number(k.total_temuan || 0).toLocaleString());
        $('#kpi-selesai').text(Number(k.selesai || 0).toLocaleString());
        $('#kpi-proses').text(Number(k.proses || 0).toLocaleString());
        $('#kpi-belum').text(Number(k.belum_dikerjakan || 0).toLocaleString());
        $('#kpi-overdue').text(Number(k.overdue || 0).toLocaleString());
        $('#kpi-hari-ini').text(Number(k.temuan_hari_ini || 0).toLocaleString());
        $('#kpi-minggu-ini').text(Number(k.temuan_minggu_ini || 0).toLocaleString());
        $('#kpi-bulan-ini').text(Number(k.temuan_bulan_ini || 0).toLocaleString());
        $('#kpi-rate').text((k.persentase_selesai || 0) + '%');
        $('#kpi-sla-rate').text((k.persentase_sla_met || 0) + '%');
        $('#kpi-ach-bulanan').text((k.ach_bulanan || 0) + '%');

        // 2. Update Charts
        if (data.charts) {
            updateHarianChart(data.charts.harian);
            updateUlpChart(data.charts.ulp);
            updateJenisChart(data.charts.jenis);
            updatePrioritasChart(data.charts.prioritas);
            updateStatusChart(data.charts.status);
            updatePelaksanaChart(data.charts.pelaksana);
            updatePenyulangChart(data.charts.penyulang);
        }

        // 3. Update SLA Matrix
        if (data.sla && data.sla.details) {
            const d = data.sla.details;
            if (d.EMERGENCY) {
                $('#sla-emergency-overdue').text(d.EMERGENCY.overdue + ' Overdue');
                $('#sla-emergency-met').text(d.EMERGENCY.met);
                $('#sla-emergency-total').text(d.EMERGENCY.total);
            }
            if (d.HIGH) {
                $('#sla-high-overdue').text(d.HIGH.overdue + ' Overdue');
                $('#sla-high-met').text(d.HIGH.met);
                $('#sla-high-total').text(d.HIGH.total);
            }
            if (d.MEDIUM) {
                $('#sla-medium-overdue').text(d.MEDIUM.overdue + ' Overdue');
                $('#sla-medium-met').text(d.MEDIUM.met);
                $('#sla-medium-total').text(d.MEDIUM.total);
            }
            if (d.LOW) {
                $('#sla-low-overdue').text(d.LOW.overdue + ' Overdue');
                $('#sla-low-met').text(d.LOW.met);
                $('#sla-low-total').text(d.LOW.total);
            }
        }

        // 4. Update GIS Map Pins
        if (data.map_pins && gisMap) {
            updateGisMapPins(data.map_pins);
        }
    }

    function initExecutiveCharts() {
        // Harian Chart (Line)
        const ctxHarian = document.getElementById('chartHarianCanvas').getContext('2d');
        execCharts.harian = new Chart(ctxHarian, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    { label: 'Temuan Baru', data: [], borderColor: '#005eb8', backgroundColor: 'rgba(0,94,184,0.1)', fill: true, tension: 0.3 },
                    { label: 'Realisasi Selesai', data: [], borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', fill: true, tension: 0.3 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Status Doughnut Chart
        const ctxStatus = document.getElementById('chartStatusCanvas').getContext('2d');
        execCharts.status = new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: ['Belum', 'Proses', 'Selesai'],
                datasets: [{ data: [0, 0, 0], backgroundColor: ['#ef4444', '#f59e0b', '#10b981'] }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // ULP Bar Chart
        const ctxUlp = document.getElementById('chartUlpCanvas').getContext('2d');
        execCharts.ulp = new Chart(ctxUlp, {
            type: 'bar',
            data: { labels: [], datasets: [{ label: 'Total Temuan', data: [], backgroundColor: '#0284c7' }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false }
        });

        // Jenis Pie Chart
        const ctxJenis = document.getElementById('chartJenisCanvas').getContext('2d');
        execCharts.jenis = new Chart(ctxJenis, {
            type: 'pie',
            data: { labels: [], datasets: [{ data: [], backgroundColor: ['#38bdf8', '#fb923c', '#4ade80', '#a78bfa'] }] },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Prioritas Doughnut Chart
        const ctxPrioritas = document.getElementById('chartPrioritasCanvas').getContext('2d');
        execCharts.prioritas = new Chart(ctxPrioritas, {
            type: 'doughnut',
            data: { labels: [], datasets: [{ data: [], backgroundColor: ['#dc2626', '#ea580c', '#0284c7', '#64748b'] }] },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Pelaksana Bar Chart
        const ctxPelaksana = document.getElementById('chartPelaksanaCanvas').getContext('2d');
        execCharts.pelaksana = new Chart(ctxPelaksana, {
            type: 'bar',
            data: { labels: [], datasets: [{ label: 'Total Workload', data: [], backgroundColor: '#6366f1' }] },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Penyulang Bar Chart
        const ctxPenyulang = document.getElementById('chartPenyulangCanvas').getContext('2d');
        execCharts.penyulang = new Chart(ctxPenyulang, {
            type: 'bar',
            data: { labels: [], datasets: [{ label: 'Temuan', data: [], backgroundColor: '#f59e0b' }] },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    function updateHarianChart(harian) {
        if (!harian || !execCharts.harian) return;
        execCharts.harian.data.labels = harian.labels || [];
        execCharts.harian.data.datasets[0].data = harian.temuan || [];
        execCharts.harian.data.datasets[1].data = harian.selesai || [];
        execCharts.harian.update();
    }

    function updateStatusChart(status) {
        if (!status || !execCharts.status) return;
        execCharts.status.data.datasets[0].data = [status.BELUM || 0, status.PROSES || 0, status.SELESAI || 0];
        execCharts.status.update();
    }

    function updateUlpChart(ulpRaw) {
        if (!ulpRaw || !execCharts.ulp) return;
        execCharts.ulp.data.labels = ulpRaw.map(u => u.nama_ulp);
        execCharts.ulp.data.datasets[0].data = ulpRaw.map(u => u.total);
        execCharts.ulp.update();
    }

    function updateJenisChart(jenisRaw) {
        if (!jenisRaw || !execCharts.jenis) return;
        execCharts.jenis.data.labels = jenisRaw.map(j => j.jenis_temuan);
        execCharts.jenis.data.datasets[0].data = jenisRaw.map(j => j.total);
        execCharts.jenis.update();
    }

    function updatePrioritasChart(prioRaw) {
        if (!prioRaw || !execCharts.prioritas) return;
        execCharts.prioritas.data.labels = prioRaw.map(p => p.prioritas);
        execCharts.prioritas.data.datasets[0].data = prioRaw.map(p => p.total);
        execCharts.prioritas.update();
    }

    function updatePelaksanaChart(pelaksanaRaw) {
        if (!pelaksanaRaw || !execCharts.pelaksana) return;
        execCharts.pelaksana.data.labels = pelaksanaRaw.map(p => p.pelaksana);
        execCharts.pelaksana.data.datasets[0].data = pelaksanaRaw.map(p => p.total);
        execCharts.pelaksana.update();
    }

    function updatePenyulangChart(penyulangRaw) {
        if (!penyulangRaw || !execCharts.penyulang) return;
        execCharts.penyulang.data.labels = penyulangRaw.map(p => p.nama_penyulang);
        execCharts.penyulang.data.datasets[0].data = penyulangRaw.map(p => p.total);
        execCharts.penyulang.update();
    }

    function initGisHeatmap() {
        const container = document.getElementById('gisHeatmapContainer');
        if (!container) return;

        gisMap = L.map('gisHeatmapContainer').setView([-7.4478, 112.7183], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '© PLN UP3 SIDOARJO - SIDAK TEJO GIS'
        }).addTo(gisMap);

        mapMarkersGroup = L.layerGroup().addTo(gisMap);
    }

    function updateGisMapPins(pins) {
        if (!gisMap || !mapMarkersGroup) return;
        mapMarkersGroup.clearLayers();
        $('#heatmap-pin-count').text((pins.length || 0) + ' Pins');

        pins.forEach(p => {
            if (p.latitude && p.longitude) {
                let markerColor = '#005eb8';
                if (p.prioritas === 'EMERGENCY') markerColor = '#dc2626';
                if (p.prioritas === 'HIGH') markerColor = '#ea580c';
                if (p.status === 'SELESAI') markerColor = '#10b981';

                const circle = L.circleMarker([parseFloat(p.latitude), parseFloat(p.longitude)], {
                    radius: 8,
                    fillColor: markerColor,
                    color: '#ffffff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8
                });

                circle.bindPopup(`
                    <div style="font-size:12px;">
                        <strong class="text-primary">${p.nomor_temuan}</strong><br>
                        <span>ULP: ${p.nama_ulp || '-'}</span><br>
                        <span>Penyulang: ${p.nama_penyulang || '-'}</span><br>
                        <span class="badge bg-secondary">${p.jenis_temuan}</span>
                        <span class="badge bg-danger">${p.prioritas}</span>
                    </div>
                `);

                mapMarkersGroup.addLayer(circle);
            }
        });
    }

    function exportExecutiveDashboard(type) {
        const formData = $('#execFilterForm').serialize();
        if (type === 'excel') {
            window.location.href = "<?= site_url('laporan/excel') ?>?" + formData;
        } else if (type === 'csv') {
            window.location.href = "<?= site_url('laporan/csv') ?>?" + formData;
        } else {
            window.print();
        }
    }

    function captureDashboardScreenshot() {
        const target = document.getElementById('execDashboardTabContent');
        if (!target) return;
        html2canvas(target).then(canvas => {
            const link = document.createElement('a');
            link.download = 'SIDAK_TEJO_Executive_Dashboard_' + Date.now() + '.png';
            link.href = canvas.toDataURL();
            link.click();
        });
    }
</script>
<?= $this->endSection() ?>
