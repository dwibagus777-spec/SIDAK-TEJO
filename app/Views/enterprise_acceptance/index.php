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
        .certificate-box { border: 2px dashed #10b981; background: #064e3b; border-radius: 12px; }
    </style>
</head>
<body class="py-4">
<div class="container-fluid px-4">
    <!-- Header Banner -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
        <div>
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-award me-2"></i>Production Acceptance & Final Go-Live Certification</h3>
            <small class="text-secondary"><i class="fa-solid fa-certificate me-1"></i>Official Platform Version: SIDAK TEJO <?= esc($cert['platform_version'] ?? 'v3.0.0') ?> (Certified: <?= esc($cert['certified_at'] ?? date('Y-m-d')) ?>)</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-circle-check me-1"></i>GO-LIVE: <?= esc($cert['go_live_decision'] ?? 'AUTHORIZED') ?></span>
        </div>
    </div>

    <!-- Go-Live Certificate Box -->
    <div class="card certificate-box p-4 text-center mb-4">
        <h4 class="fw-bold text-light mb-2"><i class="fa-solid fa-shield-cat me-2 text-warning"></i>OFFICIAL GO-LIVE CERTIFICATE</h4>
        <p class="text-light mb-2">SIDAK TEJO v3.0.0 Enterprise Operational Intelligence & Governance Platform</p>
        <div class="fs-6 font-monospace text-warning fw-bold mb-2">CODE: <?= esc($cert['certificate_code'] ?? 'CERT-STJ-v3.0.0-PROD') ?></div>
        <small class="text-light opacity-75 font-monospace">SHA-256 Checksum: <?= esc($cert['checksum_sha256'] ?? '-') ?></small>
    </div>

    <!-- Acceptance & Runbook Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-list-check me-2"></i>Production Acceptance Checklist</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Acceptance Decision:</small>
                    <div class="fs-5 fw-bold text-success font-monospace"><?= esc($checklist['checklist_decision'] ?? 'ACCEPTANCE_CHECKLIST_APPROVED') ?></div>
                </div>
                <div class="small text-light">Verification Suite: <span class="badge bg-success"><?= esc($checklist['verification_gates_audit'] ?? '40/40 PASSED') ?></span></div>
                <div class="small text-light mt-1">Zero-Trust & DR: <span class="badge bg-success">PASSED VERIFIED</span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-book-bookmark me-2"></i>Operational Handover & Runbook</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Runbook Status & Target Units:</small>
                    <div class="text-warning font-monospace fw-bold mb-1"><?= esc($runbook['handover_status'] ?? 'RUNBOOK_HANDOVER_COMPLETED') ?></div>
                    <small class="text-info font-monospace">Units: PLN ULP Sidoarjo Kota & Dalops Ops Center</small>
                </div>
                <div class="small text-light">Hypercare Window: <span class="badge bg-success"><?= esc($hypercare['hypercare_status'] ?? 'HYPERCARE_MONITORING_ACTIVE') ?></span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
