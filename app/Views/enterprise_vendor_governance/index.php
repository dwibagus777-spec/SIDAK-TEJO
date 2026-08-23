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
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-users-gear me-2"></i>Enterprise Contractor Performance & Vendor SLA Governance Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-clipboard-check me-1"></i>Contractor Execution Audit, K3 Workforce Review & Vendor Rating Advisory</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-award me-1"></i>VENDOR GOVERNANCE VERIFIED</span>
        </div>
    </div>

    <!-- Contractor Performance Audit & Vendor Rating Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-building-user me-2"></i>Contractor Performance Audit</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Vendor Name:</small>
                    <div class="fs-6 fw-bold text-info font-monospace"><?= esc($contractorAudit['vendor_name'] ?? 'PT KARYA LISTRIK UTAMA') ?></div>
                    <small class="text-secondary">Contract Reference & Version:</small>
                    <div class="font-monospace text-success small"><?= esc($contractorAudit['contract_reference'] ?? 'SPK-HAR-2026') ?> (<?= esc($contractorAudit['contract_version'] ?? 'CONTRACT-HAR-2026-v1.0') ?>)</div>
                </div>
                <div class="small text-light">KPI Score: <span class="badge bg-success font-monospace"><?= esc($contractorAudit['kpi_score_calculated'] ?? 92.4) ?> Poin</span> | K3 Status: <span class="text-success"><?= esc($contractorAudit['k3_certification_status'] ?? 'VALIDATED') ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-star-half-stroke me-2"></i>Vendor Rating & SLA Penalty Advisory</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Advisory ID:</small>
                    <div class="text-warning font-monospace fw-bold mb-1"><?= esc($ratingAdvisory['advisory_id'] ?? 'VENDOR-ADV-STJ-01') ?></div>
                    <small class="text-secondary">Calculated Contractual SLA Penalty:</small>
                    <div class="text-danger font-monospace fw-bold fs-6">Rp <?= number_format($ratingAdvisory['calculated_sla_penalty_rp'] ?? 1500000, 2, ',', '.') ?> (Rating: <span class="text-success"><?= esc($ratingAdvisory['vendor_rating_category'] ?? 'GRADE_A') ?></span>)</div>
                </div>
                <div class="small text-light">Auto Blacklisting: <span class="badge bg-danger font-monospace">DENIED_REQUIRES_PROCUREMENT_CLEARANCE</span> | ERP Posting: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
