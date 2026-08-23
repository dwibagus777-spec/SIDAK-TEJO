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
            <h3 class="fw-bold text-info mb-1"><i class="fa-solid fa-server me-2"></i>Disaster Recovery & Business Continuity</h3>
            <small class="text-secondary"><i class="fa-solid fa-clock-rotate-left me-1"></i>RPO Target: 15 mins (Actual: <?= esc($drReadiness['backup_freshness_minutes'] ?? 8) ?>m) | RTO Target: 60 mins (Estimate: <?= esc($drReadiness['rto_estimated_minutes'] ?? 32) ?>m)</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-heart-pulse me-1"></i>DR STATUS: <?= esc($drReadiness['dr_status'] ?? 'DR_READY') ?></span>
        </div>
    </div>

    <!-- DR Readiness & Operational Mode Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-database me-2"></i>Disaster Recovery Readiness Assessment</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary">Readiness Score:</span>
                        <span class="fw-bold text-success fs-5"><?= esc($drReadiness['readiness_score'] ?? 96.5) ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: <?= esc($drReadiness['readiness_score'] ?? 96.5) ?>%;"></div>
                    </div>
                </div>
                <div class="d-flex justify-content-between small text-light mb-1">
                    <span>RPO Compliance:</span> <span class="badge bg-success"><?= esc($drReadiness['rpo_compliance'] ?? 'COMPLIANT') ?></span>
                </div>
                <div class="d-flex justify-content-between small text-light">
                    <span>RTO Compliance:</span> <span class="badge bg-success"><?= esc($drReadiness['rto_compliance'] ?? 'COMPLIANT') ?></span>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-shield-virus me-2"></i>Operational Continuity Mode</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Current Operating Mode:</small>
                    <div class="fs-5 fw-bold text-success font-monospace"><?= esc($continuity['active_mode'] ?? 'NORMAL') ?></div>
                </div>
                <div class="small text-light">SCADA Subsystem: <span class="badge bg-success"><?= esc($continuity['scada_subsystem'] ?? 'HEALTHY_ONLINE') ?></span></div>
                <div class="small text-light mt-1">Field Execution: <span class="badge bg-success"><?= esc($continuity['field_execution_subsystem'] ?? 'CONTINUOUS_ONLINE') ?></span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
