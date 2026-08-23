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
            <h3 class="fw-bold text-info mb-1"><i class="fa-solid fa-lock me-2"></i>Enterprise Security & Zero-Trust Access Fabric</h3>
            <small class="text-secondary"><i class="fa-solid fa-user-shield me-1"></i>Identity Context: <?= esc($identity['user_email'] ?? '-') ?> (Device Fingerprint: <?= esc($identity['session_device_fp'] ?? '-') ?>)</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-shield-check me-1"></i>SESSION TRUST: <?= esc($identity['session_risk_score'] ?? 98.0) ?>%</span>
        </div>
    </div>

    <!-- Zero-Trust Evaluation Matrix Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-user-check me-2"></i>Zero-Trust Access Evaluation Result</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Aksi Yang Diminta:</small>
                    <div class="fs-5 fw-bold text-light font-monospace"><?= esc($access['requested_action'] ?? 'APPROVE_RECOMMENDATION') ?></div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-secondary">Keputusan Akses:</span>
                    <span class="badge bg-success fs-6 px-3 py-2"><?= esc($access['access_decision'] ?? 'ALLOW') ?></span>
                </div>
                <div class="mt-2 text-light small">Reason: <?= esc($access['decision_reason'] ?? 'Granted under trusted role') ?></div>
            </div>
        </div>

        <!-- Hash-Chained Security Audit Trail Card -->
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><link rel="stylesheet" href=""><i class="fa-solid fa-link me-2"></i>Hash-Chained Security Audit Trail</h5>
                <div class="p-3 bg-dark rounded">
                    <small class="text-secondary">Previous Hash:</small>
                    <div class="text-secondary font-monospace text-truncate small"><?= esc($audit['previous_hash'] ?? 'GENESIS') ?></div>
                    <small class="text-secondary mt-2 d-block">Current Hash (SHA-256):</small>
                    <div class="text-warning font-monospace text-truncate small fw-bold"><?= esc($audit['current_hash'] ?? '-') ?></div>
                    <div class="mt-2 text-end">
                        <span class="badge bg-success"><?= esc($audit['audit_integrity'] ?? 'VALIDATED') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
