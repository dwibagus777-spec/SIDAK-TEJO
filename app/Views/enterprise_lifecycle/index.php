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
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-hourglass-half me-2"></i>Asset Lifecycle & CAPEX Decision Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-coins me-1"></i>CAPEX Investment Model: Refurbishment vs Replacement Decision Matrix</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-sack-dollar me-1"></i>TOTAL ESTIMATED CAPEX: IDR <?= number_format($capex['total_estimated_capex_idr'] ?? 450000000, 0, ',', '.') ?></span>
        </div>
    </div>

    <!-- Lifecycle & CAPEX Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-screwdriver-wrench me-2"></i>Asset Lifecycle Evaluation</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Decision Recommendation:</small>
                    <div class="fs-5 fw-bold text-success font-monospace"><?= esc($lifecycle['decision_recommendation'] ?? 'RECOMMENDATION_REFURBISH') ?></div>
                    <small class="text-info font-monospace">EOL Forecast: Year <?= esc($lifecycle['end_of_life_forecast_yr'] ?? 2038) ?> (Remaining: <?= esc($lifecycle['remaining_useful_life'] ?? 12.5) ?> yrs)</small>
                </div>
                <div class="small text-light">Refurbish Cost: <span class="badge bg-info"><?= esc($lifecycle['refurbish_cost_pct'] ?? 18.5) ?>% vs Replacement (100%)</span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-layer-group me-2"></i>CAPEX Portfolio Prioritization</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Portfolio Total Assets:</small>
                    <div class="text-warning font-monospace fw-bold mb-1"><?= esc($capex['portfolio_total_assets'] ?? 124) ?> Assets Analyzed</div>
                    <small class="text-secondary">High Priority Replacement / Refurbish:</small>
                    <div class="text-danger font-monospace text-truncate small">Replacement Needed: <?= esc($capex['high_priority_replacement_cnt'] ?? 2) ?> | Refurbish: <?= esc($capex['medium_priority_refurbish_cnt'] ?? 14) ?></div>
                </div>
                <div class="small text-light">Routine Maintenance: <span class="badge bg-success"><?= esc($capex['routine_maintenance_cnt'] ?? 108) ?> Assets</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
