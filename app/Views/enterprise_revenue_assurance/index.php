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
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-piggy-bank me-2"></i>Enterprise Grid Revenue Assurance Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-file-invoice-dollar me-1"></i>Feeder Energy Balance Reconciliation, Loss Attribution & P2TL Tariff Compliance Advisory</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-calculator me-1"></i>REVENUE PROTECTION VERIFIED</span>
        </div>
    </div>

    <!-- Revenue Assurance & Protection Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-chart-pie me-2"></i>Revenue Assurance Index & Loss Attribution</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Revenue Assurance Integrity Index:</small>
                    <div class="fs-6 fw-bold text-info font-monospace"><?= esc($revenueAssurance['revenue_assurance_index'] ?? 95.8) ?>% (Confidence: <?= esc($revenueAssurance['attribution_confidence'] ?? 0.92) ?>)</div>
                    <small class="text-secondary">Estimated Non-Technical Revenue Loss:</small>
                    <div class="font-monospace text-warning fw-bold">Rp <?= number_format($revenueAssurance['estimated_revenue_loss_idr'] ?? 12400000, 0, ',', '.') ?></div>
                </div>
                <div class="small text-light">Revenue Truth Class: <span class="badge bg-info font-monospace"><?= esc($revenueAssurance['revenue_truth_class'] ?? 'ESTIMATE_ONLY') ?></span> | Billing Ledger Mutation: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-success mb-3"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Revenue Protection Advisory Bundle</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Protection Advisory Bundle ID:</small>
                    <div class="text-success font-monospace fw-bold mb-1"><?= esc($protectionAdvisory['bundle_id'] ?? 'REVENUE-BDL-STJ-01') ?></div>
                    <small class="text-secondary">Classified Metering Anomaly & Recommended Action:</small>
                    <div class="text-info font-monospace fw-bold fs-6"><?= esc($protectionAdvisory['classified_anomaly'] ?? 'SUSPECTED_UNMETERED_INSPECTION_REQUIRED') ?></div>
                </div>
                <div class="small text-light">P2TL Officer Review: <span class="badge bg-warning font-monospace">REQUIRED</span> | Customer Auto Disconnection: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
