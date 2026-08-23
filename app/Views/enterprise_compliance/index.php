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
            <h3 class="fw-bold text-info mb-1"><i class="fa-solid fa-scale-balanced me-2"></i>Enterprise Data Retention & Compliance Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-file-contract me-1"></i>Policy Coverage: 100% | Legal Hold Protection: ACTIVE</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-shield-check me-1"></i>RETENTION: COMPLIANT</span>
        </div>
    </div>

    <!-- Retention Policy Coverage Table & Legal Hold Control -->
    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-folder-tree me-2"></i>Data Domain Retention Policy Coverage</h5>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                            <tr class="table-active">
                                <th>Domain Data</th>
                                <th>Retensi (Tahun)</th>
                                <th>Status Lifecycle</th>
                                <th>Otorisasi Pembuangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($policyStatus)): ?>
                                <?php foreach ($policyStatus as $domain => $policy): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary font-monospace"><?= esc($domain) ?></span></td>
                                        <td><?= esc($policy['retention_period_years']) ?> thn</td>
                                        <td><span class="badge bg-info"><?= esc($policy['lifecycle_state']) ?></span></td>
                                        <td><span class="badge bg-warning text-dark"><?= esc($policy['disposal_auth']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-gavel me-2"></i>Legal Hold & Disposal Enforcement</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Target Domain:</small>
                    <div class="fw-bold text-light font-monospace"><?= esc($legalHold['domain'] ?? 'FINDINGS_MASTER') ?></div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="text-secondary">Legal Hold Status:</span>
                        <span class="badge bg-danger"><?= esc($legalHold['legal_hold_status'] ?? 'LEGAL_HOLD_ACTIVE') ?></span>
                    </div>
                </div>
                <div class="alert alert-warning py-2 mb-0 small">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>Disposal Protection: <strong><?= esc($legalHold['disposal_protection'] ?? 'BLOCKED') ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
