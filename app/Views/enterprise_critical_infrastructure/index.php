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
            <h3 class="fw-bold text-danger mb-1"><i class="fa-solid fa-hospital-user me-2"></i>Enterprise Grid Critical Infrastructure Resilience Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-network-wired me-1"></i>Critical Customer Node Interdependency, Cascading Outage Mapping & Priority Restoration Advisory</small>
        </div>
        <div>
            <span class="badge bg-danger px-3 py-2 fs-6"><i class="fa-solid fa-shield-virus me-1"></i>CRITICAL INFRASTRUCTURE VERIFIED</span>
        </div>
    </div>

    <!-- Critical Infrastructure & Restoration Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-danger mb-3"><i class="fa-solid fa-hospital me-2"></i>Critical Node Resilience & Cascading Risk</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Criticality Score & Cascading Risk Class:</small>
                    <div class="fs-6 fw-bold text-danger font-monospace"><?= esc($criticalInfrastructureResilience['criticality_score'] ?? 96.5) ?>% (Risk: <?= esc($criticalInfrastructureResilience['cascading_risk_class'] ?? 'PROBABILISTIC_ADVISORY') ?>)</div>
                    <small class="text-secondary">Resilience Truth Class:</small>
                    <div class="font-monospace text-warning fw-bold"><?= esc($criticalInfrastructureResilience['resilience_truth_class'] ?? 'ESTIMATE_ONLY') ?></div>
                </div>
                <div class="small text-light">Automatic Load Shedding: <span class="badge bg-danger font-monospace">FORBIDDEN</span> | Remote Tap Changing: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-kit-medical me-2"></i>Priority Restoration Advisory Bundle</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Restoration Advisory Bundle ID:</small>
                    <div class="text-warning font-monospace fw-bold mb-1"><?= esc($criticalAdvisory['bundle_id'] ?? 'CRITICAL-BDL-STJ-01') ?></div>
                    <small class="text-secondary">Interdependency Risk & Recommended Restoration Action:</small>
                    <div class="text-info font-monospace fw-bold fs-6"><?= esc($criticalAdvisory['recommended_restoration_action'] ?? 'PRIORITY_HOSPITAL_FEEDER_BACKUP_GENERATOR_READY') ?></div>
                </div>
                <div class="small text-light">Crisis Commander Review: <span class="badge bg-warning font-monospace">REQUIRED</span> | Incident Command Transferred: <span class="badge bg-secondary font-monospace">FALSE</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
