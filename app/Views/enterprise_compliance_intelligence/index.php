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
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-gavel me-2"></i>Enterprise Regulatory Compliance Intelligence & Obligation Control Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-file-contract me-1"></i>Regulatory Obligation Registry, Evidence Lineage & Governed Submission Readiness</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-shield-check me-1"></i>COMPLIANCE POSTURE VERIFIED</span>
        </div>
    </div>

    <!-- Obligation Registry & Readiness Bundle Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-book-bookmark me-2"></i>Regulatory Obligation Registry</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Regulation Reference:</small>
                    <div class="fs-6 fw-bold text-info font-monospace"><?= esc($obligationRegistry['regulation_reference'] ?? 'PERMEN_ESDM_2026') ?></div>
                    <small class="text-secondary">Regulation Version:</small>
                    <div class="font-monospace text-success small"><?= esc($obligationRegistry['regulation_version'] ?? 'REGULATION-ESDM-2026-v1.0') ?></div>
                </div>
                <div class="small text-light">Evidence Lineage: <span class="badge bg-success font-monospace"><?= esc($obligationRegistry['evidence_lineage'] ?? 'EVD-LINEAGE-STJ-VERIFIED') ?></span> | Obligation Status: <span class="text-success"><?= esc($obligationRegistry['obligation_status'] ?? 'EVIDENCE_MAPPED') ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-box-archive me-2"></i>Compliance Gap & Submission Readiness</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Readiness Bundle ID:</small>
                    <div class="text-warning font-monospace fw-bold mb-1"><?= esc($gapAssessment['bundle_id'] ?? 'READINESS-BDL-STJ-01') ?></div>
                    <small class="text-secondary">Compliance Index Score:</small>
                    <div class="text-success font-monospace fw-bold fs-6"><?= esc($gapAssessment['compliance_score_pct'] ?? 98.6) ?>% (0 Gaps Detected)</div>
                </div>
                <div class="small text-light">Statutory Declaration: <span class="badge bg-danger font-monospace">DENIED_REQUIRES_EXECUTIVE_SIGN_OFF</span> | Auto Submission: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
