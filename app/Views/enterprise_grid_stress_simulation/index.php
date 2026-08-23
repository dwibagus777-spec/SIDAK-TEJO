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
    </style>
</head>
<body class="py-4">
<div class="container-fluid px-4">
    <!-- Header Banner -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
        <div>
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-vial-virus me-2"></i>Enterprise Virtual Grid Stress Testing Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-cubes-stacked me-1"></i>Virtual Grid Stress Simulation, Frozen Snapshot Provenance & Preventive Mitigation Advisory</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-microchip me-1"></i>SANDBOX SIMULATION VERIFIED</span>
        </div>
    </div>

    <!-- Grid Stress Simulation & Mitigation Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-cloud-bolt me-2"></i>Virtual Grid Stress & Cascading Outage Simulation</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Simulation Run ID & Snapshot:</small>
                    <div class="fs-6 fw-bold text-warning font-monospace"><?= esc($gridStressSimulation['simulation_run_id'] ?? 'SIM-RUN-STJ-01') ?> (Snapshot: <?= esc($gridStressSimulation['input_snapshot_id'] ?? 'SNAP-GRID-20260822-01') ?>)</div>
                    <small class="text-secondary">Grid Vulnerability Score & Risk Level:</small>
                    <div class="font-monospace text-danger fw-bold"><?= esc($gridStressSimulation['vulnerability_score'] ?? 68.5) ?> / 100 (Risk: <?= esc($gridStressSimulation['cascading_risk_level'] ?? 'HIGH_SURGE_RISK') ?>)</div>
                </div>
                <div class="small text-light">Truth Class: <span class="badge bg-info font-monospace"><?= esc($gridStressSimulation['truth_class'] ?? 'SIMULATED_SCENARIO_ESTIMATE_ONLY') ?></span> | SCADA Write: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-success mb-3"><i class="fa-solid fa-shield-halved me-2"></i>Preventive Grid Mitigation Advisory Bundle</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Mitigation Bundle ID:</small>
                    <div class="text-success font-monospace fw-bold mb-1"><?= esc($mitigationAdvisory['bundle_id'] ?? 'MITIGATION-BDL-STJ-01') ?></div>
                    <small class="text-secondary">Recommended Preventive Action:</small>
                    <div class="text-info font-monospace fw-bold fs-6"><?= esc($mitigationAdvisory['recommended_mitigation'] ?? 'PREVENTIVE_FEEDER_LOAD_BALANCING_ADVISORY') ?></div>
                </div>
                <div class="small text-light">Human Operational Review: <span class="badge bg-warning font-monospace">REQUIRED</span> | Autonomous Execution: <span class="badge bg-danger font-monospace">DENIED_REQUIRES_DISPATCHER_APPROVAL</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
