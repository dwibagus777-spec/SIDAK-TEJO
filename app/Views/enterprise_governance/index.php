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
            <h3 class="fw-bold text-warning mb-1"><i class="fa-solid fa-scale-balanced me-2"></i>Enterprise Data Governance & Reconciliation Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-database me-1"></i>Master Data Stewardship & Read-Compare-First Cross-System Reconciliation Engine</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-check-double me-1"></i>DATA CERTIFIED HIGH TRUST</span>
        </div>
    </div>

    <!-- Governance & Stewardship Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-user-shield me-2"></i>Master Data Stewardship Audit</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Stewardship Status:</small>
                    <div class="fs-6 fw-bold text-warning font-monospace"><?= esc($stewardshipAudit['stewardship_status'] ?? 'STEWARDSHIP_PENDING') ?></div>
                    <small class="text-secondary">Authoritative Truth Source:</small>
                    <div class="font-monospace text-success small"><?= esc($stewardshipAudit['authoritative_truth'] ?? 'SIDAK_TEJO_PERSISTED') ?></div>
                </div>
                <div class="small text-light">Duplicate Asset Code: <span class="badge bg-success"><?= ($stewardshipAudit['duplicate_code_detected'] ?? false) ? 'DETECTED' : 'NONE' ?></span> | Unauthorized Promotion: <span class="text-danger font-monospace"><?= esc($stewardshipAudit['unauthorized_promotion'] ?? 'DENIED') ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-success mb-3"><i class="fa-solid fa-code-compare me-2"></i>Cross-System Data Reconciliation</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Systems Reconciled:</small>
                    <div class="text-info font-monospace fw-bold mb-1"><?= implode(' ⇄ ', $reconResult['systems_compared'] ?? ['APKT', 'SAP_ERP', 'SCADA', 'AMR', 'SIDAK_TEJO']) ?></div>
                    <small class="text-secondary">Matched vs Conflicted Records:</small>
                    <div class="text-success font-monospace fw-bold fs-6">Matched: <?= esc($reconResult['matched_records_cnt'] ?? 120) ?> | Conflicted: <span class="text-warning"><?= esc($reconResult['conflicted_records_cnt'] ?? 4) ?></span></div>
                </div>
                <div class="small text-light">Schema Drift: <span class="badge bg-warning text-dark font-monospace"><?= esc($reconResult['drift_finding_type'] ?? 'TYPE_MISMATCH_NON_DESTRUCTIVE') ?></span> | Destructive Write: <span class="badge bg-danger font-monospace">DENIED</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
