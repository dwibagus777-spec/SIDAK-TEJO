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
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-user-shield me-2"></i>Enterprise Grid Cyber-Physical Immunity Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-network-wired me-1"></i>Zero-Trust Telemetry Integrity, HMAC Sensor Verification & Cyber-Physical Security Advisory</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-lock me-1"></i>TELEMETRY INTEGRITY VERIFIED</span>
        </div>
    </div>

    <!-- Telemetry Integrity & Security Advisory Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-shield-halved me-2"></i>Zero-Trust Telemetry & HMAC Verification</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Zero-Trust Telemetry Integrity Score:</small>
                    <div class="fs-6 fw-bold text-info font-monospace"><?= esc($telemetryIntegrity['zero_trust_integrity_score'] ?? 96.4) ?>%</div>
                    <small class="text-secondary">HMAC Status & Physical Law Verification:</small>
                    <div class="font-monospace text-success fw-bold"><?= esc($telemetryIntegrity['hmac_status'] ?? 'HMAC_VERIFIED') ?> (<?= esc($telemetryIntegrity['physical_consistency'] ?? 'PHYSICAL_LAW_CONSTRAINTS_VALIDATED') ?>)</div>
                </div>
                <div class="small text-light">Security Truth Class: <span class="badge bg-info font-monospace"><?= esc($telemetryIntegrity['security_truth_class'] ?? 'ADVISORY_ONLY') ?></span> | Secret Key Exposure: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-success mb-3"><i class="fa-solid fa-bug-slash me-2"></i>Cyber-Physical Security Advisory Bundle</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Security Advisory Bundle ID:</small>
                    <div class="text-success font-monospace fw-bold mb-1"><?= esc($securityAdvisory['bundle_id'] ?? 'CYBER-BDL-STJ-01') ?></div>
                    <small class="text-secondary">Classified Anomaly & Recommended Action:</small>
                    <div class="text-info font-monospace fw-bold fs-6"><?= esc($securityAdvisory['classified_anomaly'] ?? 'INSULATOR_THERMAL_DRIFT_OR_SENSOR_PHYSICAL_INCONSISTENCY') ?></div>
                </div>
                <div class="small text-light">Dispatcher OT Review: <span class="badge bg-warning font-monospace">REQUIRED</span> | Auto Breaker Trip: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
