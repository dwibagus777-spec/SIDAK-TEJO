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
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-leaf me-2"></i>Enterprise ESG & Carbon Footprint Control Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-smog me-1"></i>Grid CO₂ Emissions Audit, SF6 Gas Leakage Tracking & Decarbonization Advisory</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-seedling me-1"></i>ESG POSTURE VERIFIED</span>
        </div>
    </div>

    <!-- ESG Carbon Footprint Audit & Decarbonization Advisory Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-success mb-3"><i class="fa-solid fa-cloud-arrow-down me-2"></i>Grid Carbon Footprint & SF6 Audit</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">CO₂ Equivalent Emissions:</small>
                    <div class="fs-6 fw-bold text-success font-monospace"><?= esc($esgAudit['co2_emissions_tons_eq'] ?? 142.8) ?> Tons CO₂eq</div>
                    <small class="text-secondary">SF6 Gas Leakage Rate:</small>
                    <div class="font-monospace text-warning small"><?= esc($esgAudit['sf6_leakage_rate_kg'] ?? 0.45) ?> kg/yr (Audit Status: <?= esc($esgAudit['audit_status'] ?? 'REVIEW_REQUIRED') ?>)</div>
                </div>
                <div class="small text-light">Methodology: <span class="badge bg-info font-monospace"><?= esc($esgAudit['methodology_version'] ?? 'METHODOLOGY-ESG-2026-v1.0') ?></span> | Classification: <span class="text-warning"><?= esc($esgAudit['esg_truth_class'] ?? 'ESTIMATE_ONLY') ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-tree me-2"></i>Decarbonization Advisory Bundle</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Readiness Bundle ID:</small>
                    <div class="text-info font-monospace fw-bold mb-1"><?= esc($decarbonizationAdvisory['bundle_id'] ?? 'ESG-BDL-STJ-01') ?></div>
                    <small class="text-secondary">Recommended Green Intervention:</small>
                    <div class="text-success font-monospace fw-bold fs-6"><?= esc($decarbonizationAdvisory['recommended_intervention'] ?? 'ECO_FRIENDLY_GIS_SF6_RECOVERY') ?> (-<?= esc($decarbonizationAdvisory['estimated_co2_reduction_tons'] ?? 38.5) ?> Tons CO₂eq)</div>
                </div>
                <div class="small text-light">Carbon Credit Trading: <span class="badge bg-danger font-monospace">DENIED_REQUIRES_CSO_BOARD_APPROVAL</span> | Auto Asset Shutdown: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
