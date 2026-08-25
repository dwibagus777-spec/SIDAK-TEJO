<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Historical Pattern Intelligence | SIDAK TEJO') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-intel { border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .badge-fact { background-color: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; }
        .badge-inference { background-color: #faf5ff; color: #6b46c1; border: 1px solid #e9d8fd; }
        .badge-advisory { background-color: #fffaf0; color: #c05621; border: 1px solid #feebc8; }
        .table th { background-color: #f7fafc; font-size: 11px; text-transform: uppercase; color: #4a5568; }
        .table td { vertical-align: middle; font-size: 13px; }
        .governance-banner { background: #f0fdf4; border-left: 4px solid #38a169; padding: 12px 16px; border-radius: 4px; }
        .section-header { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; display: flex; align-items: center; }
        .bar-container { display: flex; align-items: flex-end; height: 120px; gap: 4px; padding-top: 10px; }
        .bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; }
        .bar-fill { width: 100%; background-color: #3182ce; border-radius: 3px 3px 0 0; }
        .bar-label { font-size: 9px; color: #718096; margin-top: 4px; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-chart-line text-primary me-2"></i>Historical Pattern Intelligence & Recurrence Analytics</h4>
            <div class="text-muted small">CR-03 Phase 2: Empirical Recurrence, Temporal Clustering, and Spatial Hotspot Intelligence (841 Records)</div>
        </div>
        <div>
            <a href="<?= base_url('held-records') ?>" class="btn btn-outline-secondary btn-sm me-2"><i class="fa-solid fa-layer-group me-1"></i> Held Workspace</a>
            <a href="<?= base_url('executive-intelligence') ?>" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-gauge-high me-1"></i> Executive BI</a>
        </div>
    </div>

    <!-- Governance Banner -->
    <div class="governance-banner mb-4">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-shield-halved fa-2x text-success me-3"></i>
            <div>
                <div class="fw-bold text-success">🛡️ READ-MODEL PATTERN INTELLIGENCE ACTIVE (ZERO DATABASE MUTATIONS)</div>
                <div class="small text-secondary">
                    Baseline Analitik: <strong>841 gangguan historis</strong> pada <strong>95 penyulang aktif</strong> | Master Penyulang: <strong>134 unit</strong> (Immutable).
                    Pola analitik ini beroperasi sebagai sinyal pelengkap tanpa mengubah model penilaian risiko <em>M-04 / M-05</em>, bobot <em>40/35/25</em>, maupun kewenangan mutlak manusia.
                </div>
            </div>
        </div>
    </div>

    <!-- Section 1: OBSERVED FACT (Empirical Baseline) -->
    <div class="section-header text-primary">
        <i class="fa-solid fa-database me-2"></i>SECTION 1: OBSERVED FACTS (Empirical Database Records)
        <span class="badge badge-fact ms-2">EMPIRICAL EVIDENCE</span>
    </div>

    <!-- Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card-intel p-3 text-center border-primary">
                <div class="text-muted small text-uppercase">Historical Disturbances</div>
                <div class="fs-3 fw-bold text-primary"><?= (int)($summary['baseline_metrics']['total_disturbances'] ?? 841) ?></div>
                <div class="small text-muted">95 Penyulang Aktif</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-intel p-3 text-center">
                <div class="text-muted small text-uppercase">Peak Afternoon Window</div>
                <div class="fs-3 fw-bold text-danger"><?= (int)($summary['observed_facts']['peak_window_summary']['peak_trips'] ?? 275) ?></div>
                <div class="small text-muted">13:00 - 17:59 (<?= esc($summary['observed_facts']['peak_window_summary']['percentage'] ?? 32.7) ?>%)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-intel p-3 text-center">
                <div class="text-muted small text-uppercase">Dominant Relay Mode</div>
                <div class="fs-3 fw-bold text-purple" style="color: #6b46c1;"><?= (int)($summary['observed_facts']['relay_evidence']['dgr_trips'] ?? 584) ?></div>
                <div class="small text-muted">DGR (Fasa-ke-Tanah)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-intel p-3 text-center">
                <div class="text-muted small text-uppercase">Linked Active Findings</div>
                <div class="fs-3 fw-bold text-info"><?= (int)($summary['baseline_metrics']['total_active_findings'] ?? 441) ?></div>
                <div class="small text-muted">Temuan Lapangan Aktif</div>
            </div>
        </div>
    </div>

    <!-- Charts & Tables Row -->
    <div class="row g-3 mb-4">
        <!-- Top Recurring Feeders -->
        <div class="col-md-7">
            <div class="card-intel p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-bolt me-2 text-warning"></i>Top Recurring Feeders (Frekuensi Trip Tertinggi)</h6>
                    <span class="badge bg-light text-dark">Top 10 Feeders</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Nama Penyulang</th>
                                <th class="text-center">Trip Count</th>
                                <th class="text-center">Total Durasi</th>
                                <th class="text-center">Rata-rata</th>
                                <th class="text-center">Hari Kejadian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($summary['observed_facts']['top_recurring_feeders'])): ?>
                                <?php foreach (array_slice($summary['observed_facts']['top_recurring_feeders'], 0, 8) as $f): ?>
                                    <tr>
                                        <td class="fw-bold"><?= esc($f['feeder_name']) ?></td>
                                        <td class="text-center"><span class="badge bg-danger rounded-pill"><?= (int)$f['trip_count'] ?></span></td>
                                        <td class="text-center"><?= round($f['total_duration'], 1) ?> min</td>
                                        <td class="text-center"><?= round($f['avg_duration'], 1) ?> min</td>
                                        <td class="text-center"><?= (int)$f['incident_days'] ?> hari</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Spatial Micro-Hotspots -->
        <div class="col-md-5">
            <div class="card-intel p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-map-pin me-2 text-danger"></i>Spatial Micro-Hotspots (Zonasi Berulang)</h6>
                    <span class="badge bg-light text-dark">Narrative Evidence</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Penyulang</th>
                                <th>Zona / Seksi</th>
                                <th class="text-center">Klaster Trip</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($summary['observed_facts']['spatial_micro_hotspots'])): ?>
                                <?php foreach (array_slice($summary['observed_facts']['spatial_micro_hotspots'], 0, 7) as $s): ?>
                                    <tr>
                                        <td class="fw-bold"><?= esc($s['feeder_name']) ?></td>
                                        <td><span class="badge bg-light text-dark font-monospace"><?= esc($s['extracted_zone_section']) ?></span></td>
                                        <td class="text-center fw-bold text-danger"><?= (int)$s['cluster_count'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: PATTERN INFERENCE (Statistical Signals) -->
    <div class="section-header text-purple" style="color: #6b46c1;">
        <i class="fa-solid fa-brain me-2"></i>SECTION 2: PATTERN INFERENCES (Derived Statistical Intelligence)
        <span class="badge badge-inference ms-2">STATISTICAL INFERENCE</span>
    </div>

    <div class="row g-3 mb-4">
        <?php if (!empty($summary['pattern_inferences'])): ?>
            <?php foreach ($summary['pattern_inferences'] as $inf): ?>
                <div class="col-md-4">
                    <div class="card-intel p-3 h-100 border-start border-4" style="border-left-color: #6b46c1 !important;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold small text-uppercase font-monospace"><?= esc($inf['inference_type']) ?></span>
                            <span class="badge badge-inference">Conf: <?= round($inf['confidence'] * 100) ?>%</span>
                        </div>
                        <div class="small mb-2 text-dark"><strong>Observasi:</strong> <?= esc($inf['observation']) ?></div>
                        <div class="small text-muted"><strong>Inferensi Pola:</strong> <?= esc($inf['inference']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Section 3: MANAGEMENT RECOMMENDATION (Advisory Only) -->
    <div class="section-header text-warning">
        <i class="fa-solid fa-clipboard-check me-2"></i>SECTION 3: MANAGEMENT RECOMMENDATIONS (Human Decision Support Only)
        <span class="badge badge-advisory ms-2">NO AUTO-DISPATCH</span>
    </div>

    <div class="row g-3 mb-4">
        <?php if (!empty($summary['management_advisory'])): ?>
            <?php foreach ($summary['management_advisory'] as $rec): ?>
                <div class="col-md-6">
                    <div class="card-intel p-3 h-100 border-start border-4 border-warning">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold small text-uppercase font-monospace text-dark"><?= esc($rec['advisory_code']) ?> &bull; <?= esc($rec['target_scope']) ?></span>
                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-user-shield me-1"></i>HUMAN AUTHORITY FINAL</span>
                        </div>
                        <div class="small text-secondary"><?= esc($rec['recommendation']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Feeder Specific Interactive Drilldown -->
    <div class="card-intel p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0"><i class="fa-solid fa-magnifying-glass-chart me-2 text-primary"></i>Feeder-Specific Recurrence Drilldown</h6>
            <div class="d-flex align-items-center gap-2">
                <select id="feederSelect" class="form-select form-select-sm" style="min-width: 250px;">
                    <option value="">-- Pilih Penyulang (134 Master) --</option>
                    <?php if (!empty($feeders)): ?>
                        <?php foreach ($feeders as $f): ?>
                            <option value="<?= (int)$f['id'] ?>"><?= esc($f['nama_penyulang']) ?> (ULP <?= (int)$f['ulp_id'] ?>)</option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <button onclick="loadFeederDetail()" class="btn btn-primary btn-sm"><i class="fa-solid fa-arrows-rotate me-1"></i> Load Drilldown</button>
            </div>
        </div>

        <div id="feederDetailBox" style="display: none;">
            <hr class="my-3">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded text-center">
                        <div class="small text-muted">Total Gangguan Historis</div>
                        <div class="fs-4 fw-bold text-primary" id="fTrips">0</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded text-center">
                        <div class="small text-muted">Total Durasi Padam</div>
                        <div class="fs-4 fw-bold text-danger" id="fDuration">0 min</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded text-center">
                        <div class="small text-muted">Temuan Aktif Terhubung</div>
                        <div class="fs-4 fw-bold text-info" id="fFindings">0</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded text-center">
                        <div class="small text-muted">Status Keparahan Recurrence</div>
                        <div class="fs-5 fw-bold text-purple" id="fSeverity" style="color: #6b46c1;">LOW</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function loadFeederDetail() {
    const fid = document.getElementById('feederSelect').value;
    if (!fid) {
        alert('Silakan pilih penyulang terlebih dahulu.');
        return;
    }

    try {
        const resp = await fetch('<?= base_url('api/pattern-intelligence/feeder') ?>/' + fid);
        const data = await resp.json();

        if (data.success) {
            document.getElementById('feederDetailBox').style.display = 'block';
            document.getElementById('fTrips').textContent = data.observed_facts.total_trips;
            document.getElementById('fDuration').textContent = data.observed_facts.total_duration_mins + ' min';
            document.getElementById('fFindings').textContent = data.observed_facts.active_findings_count;
            document.getElementById('fSeverity').textContent = data.pattern_inference.recurrence_severity;
        } else {
            alert('Gagal memuat data penyulang: ' + (data.error || 'Unknown'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}
</script>

</body>
</html>
