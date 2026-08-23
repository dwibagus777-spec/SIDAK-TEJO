<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { background: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .badge-emergency { background-color: #ef4444; color: #fff; }
        .badge-warning { background-color: #f59e0b; color: #fff; }
        .badge-success { background-color: #10b981; color: #fff; }
        .badge-info { background-color: #3b82f6; color: #fff; }
        .header-title { font-weight: 700; letter-spacing: 0.5px; }
    </style>
</head>
<body class="py-4">
<div class="container-fluid px-4">
    <!-- Header Banner -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
        <div>
            <h3 class="header-title text-info mb-1"><i class="fa-solid fa-shield-halved me-2"></i>SIDAK TEJO v3.0.0 — Enterprise Command Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-building me-1"></i><?= esc($workspaceData['enterprise_unit_hierarchy']['unit_layanan'] ?? 'PLN ULP SIDOARJO KOTA') ?> | Digital Operational Experience & Control Plane</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-circle-check me-1"></i>SYSTEM HEALTHY & AUDITED</span>
        </div>
    </div>

    <!-- Top Key Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card card-custom p-3">
                <div class="text-secondary small fw-bold"><i class="fa-solid fa-triangle-exclamation text-danger me-1"></i>ACTION REQUIRED NOW</div>
                <div class="fs-2 font-monospace fw-bold text-danger"><?= esc($workspaceData['decision_inbox_counts']['action_required_now'] ?? 0) ?></div>
                <small class="text-secondary">Kasus darurat / SLA Warning</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-custom p-3">
                <div class="text-secondary small fw-bold"><i class="fa-solid fa-clipboard-check text-warning me-1"></i>DECISION REQUIRED</div>
                <div class="fs-2 font-monospace fw-bold text-warning"><?= esc($workspaceData['decision_inbox_counts']['decision_required'] ?? 0) ?></div>
                <small class="text-secondary">Menunggu persetujuan manusia</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-custom p-3">
                <div class="text-secondary small fw-bold"><i class="fa-solid fa-cubes text-info me-1"></i>DIGITAL TWIN HEALTH</div>
                <div class="fs-2 font-monospace fw-bold text-info"><?= esc($workspaceData['digital_twin_model']['digital_twin_health_score'] ?? 0) ?> / 100</div>
                <small class="text-secondary"><?= esc($workspaceData['digital_twin_model']['nama_asset'] ?? 'Gardu SDJ-045') ?></small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-custom p-3">
                <div class="text-secondary small fw-bold"><i class="fa-solid fa-certificate text-success me-1"></i>DATA TRUST QUALITY</div>
                <div class="fs-2 font-monospace fw-bold text-success"><?= esc($workspaceData['data_trust_metrics']['quality_index'] ?? 0) ?>%</div>
                <small class="text-secondary"><?= esc($workspaceData['data_trust_metrics']['certification'] ?? 'CERTIFIED') ?></small>
            </div>
        </div>
    </div>

    <!-- Digital Twin Studio & Scenario Comparison -->
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card card-custom p-4 h-100">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-vial-circle-check me-2"></i>Digital Twin Studio — What-If Scenario Comparison</h5>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                            <tr class="table-active">
                                <th>Skenario</th>
                                <th>Predictive HI Recovery</th>
                                <th>Recurrence Risk</th>
                                <th>Est. Biaya</th>
                                <th>Durasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-primary me-2">Opsi A</span> Major Overhaul / Replace</td>
                                <td><strong class="text-success">85.0% (GOOD)</strong></td>
                                <td><span class="badge bg-success">LOW</span></td>
                                <td>Rp 15.000.000</td>
                                <td>6.0 Jam</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning text-dark me-2">Opsi B</span> Temporary Repair</td>
                                <td><strong class="text-warning">62.0% (FAIR)</strong></td>
                                <td><span class="badge bg-danger">HIGH</span></td>
                                <td>Rp 3.500.000</td>
                                <td>2.0 Jam</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-danger me-2">Opsi C</span> Defer 30 Days</td>
                                <td><strong class="text-danger">35.0% (POOR)</strong></td>
                                <td><span class="badge bg-danger">CRITICAL</span></td>
                                <td>Rp 0</td>
                                <td>0.0 Jam</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 p-3 bg-dark border border-secondary rounded">
                    <span class="text-info font-semibold"><i class="fa-solid fa-lightbulb me-1"></i>Rekomendasi Preskriptif Sistem:</span>
                    <span class="text-light"><?= esc($workspaceData['recommended_intervention'] ?? 'WHAT_IF_REPLACE_NOW') ?> — Perolehan recovery Health Index tertinggi (85%) dengan keandalan jangka panjang.</span>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-custom p-4 h-100">
                <h5 class="text-info mb-3"><i class="fa-solid fa-microchip me-2"></i>Engine Governance Status</h5>
                <ul class="list-group list-group-flush bg-transparent">
                    <li class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-shield me-2 text-success"></i>Hardening SHA-256</span>
                        <span class="badge bg-success">VERIFIED</span>
                    </li>
                    <li class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-rotate me-2 text-success"></i>Circuit Breaker</span>
                        <span class="badge bg-success">HEALTHY</span>
                    </li>
                    <li class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-chart-line me-2 text-info"></i>SRE Telemetry Trace</span>
                        <span class="badge bg-info">ACTIVE</span>
                    </li>
                    <li class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-network-wired me-2 text-primary"></i>Event Fabric</span>
                        <span class="badge bg-primary">v1.0 ACTIVE</span>
                    </li>
                    <li class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-user-gear me-2 text-warning"></i>Authority & Escalation</span>
                        <span class="badge bg-warning text-dark">IN-POLICY</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
</body>
</html>
