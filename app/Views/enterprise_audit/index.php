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
            <h3 class="fw-bold text-info mb-1"><i class="fa-solid fa-file-invoice me-2"></i>Enterprise Regulatory Audit & Statutory Export</h3>
            <small class="text-secondary"><i class="fa-solid fa-scale-balanced me-1"></i>Regulatory Body: <?= esc($report['regulatory_body'] ?? 'KEMENTERIAN_ESDM_DAN_BPK') ?></small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-file-shield me-1"></i>STATUS: <?= esc($report['report_status'] ?? 'GENERATED') ?></span>
        </div>
    </div>

    <!-- Statutory Report & Auditor Export Bundle Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-clipboard-check me-2"></i>Statutory Compliance Report</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Report Code & Type:</small>
                    <div class="fs-5 fw-bold text-success font-monospace"><?= esc($report['report_code'] ?? 'RPT-STJ-20260822-001') ?></div>
                    <small class="text-info font-monospace"><?= esc($report['report_type'] ?? 'ESDM_STATUTORY_COMPLIANCE') ?></small>
                </div>
                <div class="small text-light">Health Index Audit: <span class="badge bg-success"><?= esc($report['health_index_compliance'] ?? 'PASSED') ?></span></div>
                <div class="small text-light mt-1">SLA Breach Audit: <span class="badge bg-success"><?= esc($report['sla_breach_compliance'] ?? 'PASSED') ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-file-zipper me-2"></i>Auditor Export Package Bundle</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Bundle Code:</small>
                    <div class="text-warning font-monospace fw-bold mb-1"><?= esc($bundle['bundle_code'] ?? 'AUDIT-BUNDLE-STJ-20260822-001') ?></div>
                    <small class="text-secondary">SHA-256 Checksum:</small>
                    <div class="text-info font-monospace text-truncate small"><?= esc($bundle['manifest_checksum'] ?? '-') ?></div>
                </div>
                <div class="small text-light">Evidence Reference: <span class="font-monospace text-info"><?= esc($bundle['evidence_chain_ref'] ?? '-') ?></span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
