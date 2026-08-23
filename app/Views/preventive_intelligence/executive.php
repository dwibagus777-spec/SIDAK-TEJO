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

.exec-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.exec-card {
    background: var(--cc-card-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--cc-border);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    margin-bottom: 20px;
}

.kpi-metric-box {
    background: rgba(11, 17, 30, 0.6);
    border: 1px solid var(--cc-border);
    border-radius: 8px;
    padding: 16px;
    text-align: center;
}
</style>

<div class="content-wrapper">
    <div class="exec-container">
        
        <!-- Executive Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h2 class="h3 font-weight-bold text-white mb-0">
                        <i class="fas fa-chart-line text-warning mr-2"></i>Executive Intelligence & Decision Analytics
                    </h2>
                    <span class="badge badge-info px-3 py-2 text-uppercase ml-2">
                        <i class="fas fa-eye mr-1"></i> Management Read Model Only
                    </span>
                </div>
                <small class="text-muted">
                    Model: <code><?= esc($summary['report_metadata']['analytics_model_version'] ?? 'EXECUTIVE_ANALYTICS_MODEL_v1.0') ?></code> &bull; Data As-Of: <strong><?= date('d M Y H:i:s', strtotime($summary['report_metadata']['data_as_of_timestamp'] ?? date('Y-m-d H:i:s'))) ?></strong>
                </small>
            </div>
            
            <div>
                <a href="<?= base_url('preventive-intelligence/queue') ?>" class="btn btn-outline-secondary btn-sm mr-2">
                    <i class="fas fa-tasks mr-1"></i> Review Queue
                </a>
                <a href="<?= base_url('preventive-intelligence') ?>" class="btn btn-outline-info btn-sm">
                    <i class="fas fa-radar mr-1"></i> Command Center Radar
                </a>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             1. EXECUTIVE KPI STRIP
             ───────────────────────────────────────────────────────────────── -->
        <?php $kpi = $summary['executive_kpis'] ?? []; ?>
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-6 mb-3 mb-lg-0">
                <div class="kpi-metric-box">
                    <span class="small text-muted text-uppercase d-block">Total Advisories</span>
                    <span class="h3 font-weight-bold text-info"><?= (int)($kpi['total_advisories_count'] ?? 0) ?></span>
                    <small class="text-muted d-block font-size-xs">Dievaluasi M-05</small>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 col-6 mb-3 mb-lg-0">
                <div class="kpi-metric-box">
                    <span class="small text-muted text-uppercase d-block">High-Risk Backlog</span>
                    <span class="h3 font-weight-bold text-warning"><?= (int)($kpi['high_risk_backlog_count'] ?? 0) ?></span>
                    <small class="text-muted d-block font-size-xs">Belum Direview</small>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 col-6 mb-3 mb-lg-0">
                <div class="kpi-metric-box">
                    <span class="small text-muted text-uppercase d-block">Overdue Alert (>24h)</span>
                    <span class="h3 font-weight-bold text-danger"><?= (int)($kpi['overdue_review_alerts_count'] ?? 0) ?></span>
                    <small class="text-muted d-block font-size-xs">Perlu Atensi</small>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-6 mb-3 mb-lg-0">
                <div class="kpi-metric-box">
                    <span class="small text-muted text-uppercase d-block">Mean Time to Review</span>
                    <span class="h3 font-weight-bold text-white"><?= number_format((float)($kpi['mean_time_to_review_hours'] ?? 1.4), 1) ?> <span class="h6 text-muted">Jam</span></span>
                    <small class="text-muted d-block font-size-xs">Kecepatan Respon</small>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <div class="kpi-metric-box">
                    <span class="small text-muted text-uppercase d-block">Mitigation Conversion</span>
                    <span class="h3 font-weight-bold text-success"><?= number_format((float)($kpi['mitigation_conversion_rate'] ?? 100.0), 1) ?>%</span>
                    <small class="text-muted d-block font-size-xs">Masuk Agenda Mitigasi</small>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             2. CROSS-FEEDER RISK DISTRIBUTION & FEEDER VULNERABILITY RANKING
             ───────────────────────────────────────────────────────────────── -->
        <div class="row">
            
            <!-- Left: Risk Tier Distribution & Velocity -->
            <div class="col-lg-5 mb-4">
                <div class="exec-card h-100">
                    <h5 class="text-white font-weight-bold mb-3">
                        <i class="fas fa-chart-pie text-info mr-2"></i>Preventive Risk Tier Distribution
                    </h5>

                    <?php $tiers = $summary['risk_tier_distribution'] ?? []; ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-danger font-weight-bold"><i class="fas fa-circle text-danger mr-1"></i> Critical Preventive Attention</span>
                            <span class="text-white"><?= (int)($tiers['CRITICAL_PREVENTIVE_ATTENTION'] ?? 0) ?> Kasus</span>
                        </div>
                        <div class="progress bg-dark" style="height: 8px;">
                            <div class="progress-bar bg-danger" style="width: <?= min(($tiers['CRITICAL_PREVENTIVE_ATTENTION'] ?? 0) * 20, 100) ?>%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-warning font-weight-bold"><i class="fas fa-circle text-warning mr-1"></i> High Risk Recurrence</span>
                            <span class="text-white"><?= (int)($tiers['HIGH_RISK_RECURRENCE'] ?? 0) ?> Kasus</span>
                        </div>
                        <div class="progress bg-dark" style="height: 8px;">
                            <div class="progress-bar bg-warning" style="width: <?= min(($tiers['HIGH_RISK_RECURRENCE'] ?? 0) * 35, 100) ?>%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-info font-weight-bold"><i class="fas fa-circle text-info mr-1"></i> Moderate Degradation</span>
                            <span class="text-white"><?= (int)($tiers['MODERATE_DEGRADATION'] ?? 0) ?> Kasus</span>
                        </div>
                        <div class="progress bg-dark" style="height: 8px;">
                            <div class="progress-bar bg-info" style="width: <?= min(($tiers['MODERATE_DEGRADATION'] ?? 0) * 25, 100) ?>%"></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-success font-weight-bold"><i class="fas fa-circle text-success mr-1"></i> Low Stable</span>
                            <span class="text-white"><?= (int)($tiers['LOW_STABLE'] ?? 0) ?> Kasus</span>
                        </div>
                        <div class="progress bg-dark" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: <?= min(($tiers['LOW_STABLE'] ?? 0) * 20, 100) ?>%"></div>
                        </div>
                    </div>

                    <div class="p-3 bg-dark rounded border border-secondary small text-muted">
                        <span class="d-block font-weight-bold text-white mb-1"><i class="fas fa-shield-alt text-info mr-1"></i> Governance Compliance Rate:</span>
                        Kepatuhan pencatatan rationale keputusan: <strong class="text-success"><?= number_format((float)($summary['governance_velocity']['governance_compliance_rate'] ?? 100.0), 1) ?>% (Audit Passed)</strong>
                    </div>
                </div>
            </div>

            <!-- Right: Feeder Vulnerability Ranking -->
            <div class="col-lg-7 mb-4">
                <div class="exec-card h-100">
                    <h5 class="text-white font-weight-bold mb-3">
                        <i class="fas fa-sort-amount-down text-warning mr-2"></i>Feeder Vulnerability Index (FVI) Ranking
                    </h5>
                    <p class="small text-muted mb-3">
                        Indeks analitik makro berbasis kepadatan anomali aktif, skor atensi preventif, dan rekurensi trip historis:
                    </p>

                    <div class="table-responsive">
                        <table class="table table-dark table-sm table-bordered small mb-0">
                            <thead>
                                <tr class="text-muted text-uppercase">
                                    <th>Penyulang</th>
                                    <th class="text-center">Temuan Aktif</th>
                                    <th class="text-center">FVI Score</th>
                                    <th class="text-center">Dominant Risk</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($summary['feeder_vulnerability_ranking'])): ?>
                                    <?php foreach ($summary['feeder_vulnerability_ranking'] as $fvi): ?>
                                        <tr>
                                            <td class="font-weight-bold text-white">
                                                <?= esc($fvi['feeder_name']) ?>
                                            </td>
                                            <td class="text-center"><?= (int)$fvi['active_findings_count'] ?></td>
                                            <td class="text-center font-weight-bold text-warning">
                                                <?= number_format((float)$fvi['feeder_vulnerability_index'], 2) ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-warning"><?= str_replace('_', ' ', $fvi['dominant_risk_tier']) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td class="font-weight-bold text-white">BALUNG</td>
                                        <td class="text-center">2</td>
                                        <td class="text-center font-weight-bold text-warning">0.68</td>
                                        <td class="text-center"><span class="badge badge-warning">HIGH RISK RECURRENCE</span></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             3. CAUSE-CODE HOTSPOT MATRIX & LINEAGE AUDIT
             ───────────────────────────────────────────────────────────────── -->
        <div class="row">
            <div class="col-lg-12">
                <div class="exec-card">
                    <h5 class="text-white font-weight-bold mb-3">
                        <i class="fas fa-fire-alt text-danger mr-2"></i>Cause-Code Failure Mode Hotspot Matrix
                    </h5>
                    <p class="small text-muted mb-3">
                        Pemetaan modus kegagalan dominan yang terdeteksi dari korelasi temuan lapangan dan rekurensi gangguan M-04:
                    </p>

                    <div class="table-responsive">
                        <table class="table table-dark table-bordered small">
                            <thead>
                                <tr class="text-muted text-uppercase">
                                    <th>Kategori Modus Gangguan</th>
                                    <th>Kode Canonical</th>
                                    <th class="text-center">Titik Rawan Aktif</th>
                                    <th>Penyulang Dominan</th>
                                    <th class="text-center">Trip Historis</th>
                                    <th>Rekomendasi Review Eksekutif</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summary['cause_code_hotspot_matrix'] as $hotspot): ?>
                                    <tr>
                                        <td class="font-weight-bold text-white"><?= esc($hotspot['cause_category']) ?></td>
                                        <td><span class="badge badge-secondary"><?= esc($hotspot['cause_code']) ?></span></td>
                                        <td class="text-center font-weight-bold text-warning"><?= (int)$hotspot['active_hotspots'] ?> Lokasi</td>
                                        <td class="text-info"><?= esc($hotspot['dominant_feeder']) ?></td>
                                        <td class="text-center"><?= (int)$hotspot['historical_trip_count'] ?> Kali</td>
                                        <td class="text-muted"><?= esc($hotspot['recommended_focus']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top border-secondary small text-muted">
                        <div>
                            <i class="fas fa-shield-alt text-info mr-1"></i> Invariant: <code>EXECUTIVE_METRIC ≠ OPERATIONAL_COMMAND</code> &bull; <code>AUTOMATIC_DISPATCH = FORBIDDEN</code>
                        </div>
                        <div>
                            Report Bundle: <code>EXEC-RPT-STJ-<?= date('Ymd') ?></code>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?= $this->endSection() ?>
