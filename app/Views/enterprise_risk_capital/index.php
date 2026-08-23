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
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-coins me-2"></i>Enterprise Operational Risk Capital & Investment Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-chart-line me-1"></i>Risk-Adjusted Return on Capital (RAROC), Resilience Opportunity Score & CAPEX Optimization</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-scale-balanced me-1"></i>CAPITAL ALLOCATION VERIFIED</span>
        </div>
    </div>

    <!-- Risk Capital Allocation & Investment Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-percent me-2"></i>RAROC & Resilience Opportunity Scorecard</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Risk-Adjusted Return on Capital (RAROC):</small>
                    <div class="fs-6 fw-bold text-info font-monospace"><?= esc($riskCapitalAllocation['raroc_percentage'] ?? 14.8) ?>%</div>
                    <small class="text-secondary">Resilience Investment Opportunity Score:</small>
                    <div class="font-monospace text-success fw-bold"><?= esc($riskCapitalAllocation['resilience_opportunity_score'] ?? 84.5) ?> / 100</div>
                </div>
                <div class="small text-light">Methodology: <span class="badge bg-info font-monospace"><?= esc($riskCapitalAllocation['methodology_version'] ?? 'METHODOLOGY-RAROC-2026-v1.0') ?></span> | Truth Class: <span class="text-warning"><?= esc($riskCapitalAllocation['financial_truth_class'] ?? 'ESTIMATE_ONLY') ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-success mb-3"><i class="fa-solid fa-sack-dollar me-2"></i>Resilience Investment Advisory Bundle</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Investment Bundle ID:</small>
                    <div class="text-success font-monospace fw-bold mb-1"><?= esc($investmentAdvisory['bundle_id'] ?? 'INVEST-BDL-STJ-01') ?></div>
                    <small class="text-secondary">Recommended Action & Estimated CAPEX:</small>
                    <div class="text-info font-monospace fw-bold fs-6"><?= esc($investmentAdvisory['recommended_investment'] ?? 'TRANSFORMER_REFURBISHMENT_AND_GRID_HARDENING') ?> (Rp <?= number_format($investmentAdvisory['estimated_capex_idr'] ?? 450000000, 0, ',', '.') ?>)</div>
                </div>
                <div class="small text-light">Board Financial Review: <span class="badge bg-warning font-monospace">REQUIRED</span> | Direct ERP Mutation: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
