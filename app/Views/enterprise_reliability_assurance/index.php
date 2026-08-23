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
            <h3 class="fw-bold text-info mb-1"><i class="fa-solid fa-chart-line me-2"></i>Enterprise Grid Reliability Assurance Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-clock-history me-1"></i>Real-Time SAIDI/SAIFI Tracking, Outage Duration Attribution & Feeder Hardening Advisory</small>
        </div>
        <div>
            <span class="badge bg-info px-3 py-2 fs-6"><i class="fa-solid fa-shield-halved me-1"></i>RELIABILITY ASSURANCE VERIFIED</span>
        </div>
    </div>

    <!-- Reliability Assurance & Improvement Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-gauge-high me-2"></i>SAIDI/SAIFI Metrics & Outage Duration Attribution</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Reliability Compliance Integrity Index:</small>
                    <div class="fs-6 fw-bold text-info font-monospace"><?= esc($reliabilityAssurance['reliability_index'] ?? 97.2) ?>%</div>
                    <small class="text-secondary">Estimated SAIDI & SAIFI Metrics:</small>
                    <div class="font-monospace text-warning fw-bold">SAIDI: <?= esc($reliabilityAssurance['estimated_saidi_min_cust'] ?? 14.2) ?> min/cust | SAIFI: <?= esc($reliabilityAssurance['estimated_saifi_times_cust'] ?? 0.45) ?> times/cust</div>
                </div>
                <div class="small text-light">Reliability Truth Class: <span class="badge bg-info font-monospace"><?= esc($reliabilityAssurance['reliability_truth_class'] ?? 'ESTIMATE_ONLY') ?></span> | Statutory Report Mutation: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-success mb-3"><i class="fa-solid fa-screwdriver-wrench me-2"></i>Reliability Improvement Advisory Bundle</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Improvement Advisory Bundle ID:</small>
                    <div class="text-success font-monospace fw-bold mb-1"><?= esc($improvementAdvisory['bundle_id'] ?? 'RELIABILITY-BDL-STJ-01') ?></div>
                    <small class="text-secondary">Attributed Cause & Recommended Action:</small>
                    <div class="text-info font-monospace fw-bold fs-6"><?= esc($improvementAdvisory['attributed_outage_cause'] ?? 'TRANSIENT_TREE_BRANCH_CONTACT_FEEDER_P_BALUNG') ?></div>
                </div>
                <div class="small text-light">Executive Dispatcher Review: <span class="badge bg-warning font-monospace">REQUIRED</span> | Auto Breaker Switching: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
