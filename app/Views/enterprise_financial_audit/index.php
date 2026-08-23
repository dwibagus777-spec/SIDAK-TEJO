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
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-coins me-2"></i>Enterprise Operational Financial Audit & Cost Recovery Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-file-invoice-dollar me-1"></i>Outage Loss Valuation, Versioned SLA Compensation & Evidence-Based Cost Recovery</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-calculator me-1"></i>FINANCIAL AUDIT ONLINE</span>
        </div>
    </div>

    <!-- Financial Audit & Recovery Proposal Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-chart-line-down me-2"></i>Outage Loss Valuation & SLA Penalty</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Estimated Outage Loss (kWh):</small>
                    <div class="fs-6 fw-bold text-danger font-monospace">Rp <?= number_format($financialAudit['estimated_outage_loss_rp'] ?? 18500000, 2, ',', '.') ?></div>
                    <small class="text-secondary">SLA Penalty Compensation:</small>
                    <div class="font-monospace text-warning small">Rp <?= number_format($financialAudit['sla_penalty_compensation'] ?? 3200000, 2, ',', '.') ?></div>
                </div>
                <div class="small text-light">Formula Version: <span class="badge bg-info font-monospace"><?= esc($financialAudit['formula_version'] ?? 'FORMULA-SLA-2026-v1.0') ?></span> | Classification: <span class="text-warning"><?= esc($financialAudit['accounting_truth_class'] ?? 'ESTIMATE_ONLY') ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-receipt me-2"></i>Outage Cost Recovery Package</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Recovery Package ID:</small>
                    <div class="text-info font-monospace fw-bold mb-1"><?= esc($recoveryProposal['recovery_package_id'] ?? 'REC-PKG-STJ-01') ?></div>
                    <small class="text-secondary">Technical vs Non-Technical Loss Attribution:</small>
                    <div class="text-success font-monospace fw-bold fs-6">Technical: <?= esc($recoveryProposal['technical_loss_share'] ?? 82.5) ?>% | Non-Tech: <span class="text-warning"><?= esc($recoveryProposal['non_technical_loss_share'] ?? 17.5) ?>%</span></div>
                </div>
                <div class="small text-light">Direct ERP Posting: <span class="badge bg-danger font-monospace">DENIED_REQUIRES_ERP_CLEARANCE</span> | Ledger Write-Back: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
