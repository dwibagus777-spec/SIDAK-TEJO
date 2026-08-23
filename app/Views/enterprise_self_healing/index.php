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
            <h3 class="fw-bold text-info mb-1"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Enterprise AI Predictive Anomaly & Governed Self-Healing Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-microchip me-1"></i>Telemetry Fault Signature Correlation & Simulation-Only Auto-Recovery Advisory</small>
        </div>
        <div>
            <span class="badge bg-info text-dark px-3 py-2 fs-6"><i class="fa-solid fa-robot me-1"></i>AI ADVISORY ACTIVE</span>
        </div>
    </div>

    <!-- Anomaly Audit & Recovery Proposal Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-wave-square me-2"></i>Telemetry Anomaly Correlation</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Anomaly Type Detected:</small>
                    <div class="fs-6 fw-bold text-warning font-monospace"><?= esc($anomalyAudit['anomaly_type'] ?? 'INSULATOR_DEGRADATION') ?></div>
                    <small class="text-secondary">Correlation Metadata:</small>
                    <div class="font-monospace text-info small"><?= esc($anomalyAudit['correlation_metadata'] ?? 'CORRELATED_WITH_SCADA_AMR_STREAM') ?></div>
                </div>
                <div class="small text-light">Telemetry Stream: <span class="badge bg-success"><?= esc($anomalyAudit['telemetry_status'] ?? 'TELEMETRY_RECEIVED') ?></span> | Provenance: <span class="text-success"><?= ($anomalyAudit['provenance_verified'] ?? true) ? 'VERIFIED' : 'UNVERIFIED' ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-success mb-3"><i class="fa-solid fa-shield-cat me-2"></i>Governed Auto-Recovery Proposal</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Proposal ID:</small>
                    <div class="text-info font-monospace fw-bold mb-1"><?= esc($recoveryProposal['proposal_id'] ?? 'REC-PROP-STJ-01') ?></div>
                    <small class="text-secondary">AI Authority Classification:</small>
                    <div class="text-warning font-monospace fw-bold fs-6"><?= esc($recoveryProposal['ai_authority_class'] ?? 'ADVISORY_PROPOSAL_ONLY') ?></div>
                </div>
                <div class="small text-light">Direct Execution: <span class="badge bg-danger font-monospace">DENIED_HUMAN_GOVERNANCE</span> | Step-Up Auth: <span class="badge bg-success font-monospace">ENFORCED</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
