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
            <h3 class="fw-bold text-info mb-1"><i class="fa-solid fa-file-signature me-2"></i>Release Governance & Production Change Control</h3>
            <small class="text-secondary"><i class="fa-solid fa-hashtag me-1"></i>Active Change Request: <?= esc($change['change_code'] ?? 'CR-STJ-20260822-001') ?> (Type: <?= esc($change['change_type'] ?? 'NORMAL_CHANGE') ?>)</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-user-check me-1"></i>AUTH: <?= esc($approval['approval_pipeline'] ?? 'AUTHORIZED') ?></span>
        </div>
    </div>

    <!-- Impact & Approval Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-burst me-2"></i>Change Impact & Blast Radius</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Risk Score & Classification:</small>
                    <div class="fs-5 fw-bold text-success font-monospace"><?= esc($impact['change_risk_score'] ?? 18) ?>/100 (<?= esc($impact['risk_classification'] ?? 'LOW_RISK') ?>)</div>
                </div>
                <div class="small text-light">Blast Radius: <span class="badge bg-success"><?= esc($impact['blast_radius'] ?? 'LIMITED') ?></span></div>
                <div class="small text-light mt-1">Database Impact: <span class="badge bg-success"><?= esc($impact['database_schema_impact'] ?? 'NON_DESTRUCTIVE') ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-stamp me-2"></i>Production Change Authority Approval</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Approver Role & Pipeline:</small>
                    <div class="text-warning font-monospace fw-bold mb-2"><?= esc($approval['approver_role'] ?? 'MANAJER_ULP') ?></div>
                    <span class="badge bg-success"><?= esc($approval['approval_pipeline'] ?? 'DEPLOYMENT_AUTHORIZED') ?></span>
                </div>
                <div class="small text-light">Step-Up Auth Reference: <span class="font-monospace text-info"><?= esc($approval['step_up_ref'] ?? '-') ?></span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
