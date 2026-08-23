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
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-charging-station me-2"></i>Enterprise EV Charging & Demand-Side Intelligence Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-temperature-arrow-up me-1"></i>EV Charging Demand Impact, Transformer Thermal Stress Forecast & Peak Shaving Advisory</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-car-battery me-1"></i>EV RESILIENCE VERIFIED</span>
        </div>
    </div>

    <!-- EV Charging Grid Impact & Demand Flexibility Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-bolt-lightning me-2"></i>EV Charging Demand & Thermal Stress Forecast</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Forecasted Peak Load & Horizon:</small>
                    <div class="fs-6 fw-bold text-warning font-monospace"><?= esc($evGridImpact['forecasted_peak_kw'] ?? 145.2) ?> kW (Horizon: <?= esc($evGridImpact['forecast_horizon_hrs'] ?? 24) ?> Hours)</div>
                    <small class="text-secondary">Transformer Thermal Stress Score:</small>
                    <div class="font-monospace text-danger small"><?= esc($evGridImpact['thermal_stress_score'] ?? 78.4) ?> / 100 (Confidence: <?= esc(($evGridImpact['confidence_level'] ?? 0.88) * 100) ?>%)</div>
                </div>
                <div class="small text-light">Model: <span class="badge bg-info font-monospace"><?= esc($evGridImpact['forecast_model_version'] ?? 'MODEL-LOAD-FORECAST-2026-v1.0') ?></span> | Classification: <span class="text-warning"><?= esc($evGridImpact['forecast_truth_class'] ?? 'ESTIMATE_ONLY') ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-success mb-3"><i class="fa-solid fa-sliders me-2"></i>Demand-Side Flexibility & Peak Shaving Bundle</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Flexibility Bundle ID:</small>
                    <div class="text-success font-monospace fw-bold mb-1"><?= esc($flexibilityAdvisory['bundle_id'] ?? 'DSF-BDL-STJ-01') ?></div>
                    <small class="text-secondary">Recommended Flexibility Action:</small>
                    <div class="text-info font-monospace fw-bold fs-6"><?= esc($flexibilityAdvisory['recommended_flexibility_action'] ?? 'SPKLU_PEAK_SHAVING_SCHEDULE_ADVISORY') ?> (-<?= esc($flexibilityAdvisory['estimated_peak_reduction_kw'] ?? 28.5) ?> kW Peak Shaving)</div>
                </div>
                <div class="small text-light">Auto Load Shedding: <span class="badge bg-danger font-monospace">DENIED_REQUIRES_DISPATCHER_APPROVAL</span> | Tap Changer Mutation: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
