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
            <h3 class="fw-bold text-danger mb-1"><i class="fa-solid fa-triangle-exclamation me-2"></i>Enterprise Incident Command & Crisis Control Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-bullhorn me-1"></i>Major Incident Orchestration, Situation Board & Multi-Unit Resource Coordination</small>
        </div>
        <div>
            <span class="badge bg-danger px-3 py-2 fs-6"><i class="fa-solid fa-shield-virus me-1"></i>MAJOR INCIDENT ACTIVE</span>
        </div>
    </div>

    <!-- Incident Command & Crisis Coordination Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-flag me-2"></i>Major Incident Declaration</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Incident Code:</small>
                    <div class="fs-6 fw-bold text-danger font-monospace"><?= esc($incidentDeclaration['incident_code'] ?? 'INC-STJ-01') ?></div>
                    <small class="text-secondary">Commander Role:</small>
                    <div class="font-monospace text-info small"><?= esc($incidentDeclaration['incident_commander_role'] ?? 'MANAJER_ULP_DAN_DALOPS') ?></div>
                </div>
                <div class="small text-light">Severity: <span class="badge bg-danger"><?= esc($incidentDeclaration['severity'] ?? 'MAJOR_EVENT_CRITICAL') ?></span> | Affected Feeders: <span class="badge bg-warning text-dark"><?= esc($incidentDeclaration['affected_feeders_cnt'] ?? 3) ?> Feeders</span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-people-group me-2"></i>Crisis Resource Coordination</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Participating Units:</small>
                    <div class="text-warning font-monospace fw-bold mb-1"><?= esc($crisisCoordination['participating_units_cnt'] ?? 3) ?> ULP Units Coordinated</div>
                    <small class="text-secondary">Deployed Field Crews:</small>
                    <div class="text-success font-monospace fw-bold fs-6"><?= esc($crisisCoordination['deployed_field_crews_cnt'] ?? 8) ?> Emergency Field Crews Active</div>
                </div>
                <div class="small text-light">Executive Briefing: <span class="badge bg-success font-monospace"><?= esc($crisisCoordination['executive_briefing_status'] ?? 'BRIEFING_DISPATCHED') ?></span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
