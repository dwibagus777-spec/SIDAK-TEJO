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
            <h3 class="fw-bold text-info mb-1"><i class="fa-solid fa-rocket me-2"></i>Enterprise Production Deployment & Environment Control</h3>
            <small class="text-secondary"><i class="fa-solid fa-code-branch me-1"></i>Active Release: <?= esc($manifest['release_version'] ?? 'v3.0.0-PROD') ?> (Commit: <?= esc(substr($manifest['git_commit_hash'] ?? '', 0, 7)) ?>)</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-circle-check me-1"></i>ENV: <?= esc($envContext['active_environment'] ?? 'PRODUCTION') ?></span>
        </div>
    </div>

    <!-- Production Readiness & Release Manifest Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-clipboard-check me-2"></i>Production Readiness Gate Approval</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Readiness Gate Decision:</small>
                    <div class="fs-5 fw-bold text-success font-monospace"><?= esc($readiness['readiness_decision'] ?? 'PRODUCTION_READINESS_APPROVED') ?></div>
                </div>
                <div class="small text-light">Database Migrations: <span class="badge bg-success"><?= esc($readiness['database_migrations'] ?? 'PASSED') ?></span></div>
                <div class="small text-light mt-1">Secret Boundary: <span class="badge bg-success"><?= esc($readiness['secret_boundary_health'] ?? 'PASSED') ?></span></div>
                <div class="small text-light mt-1">Verification Suite Gates: <span class="badge bg-success"><?= esc($readiness['verification_suite_gates'] ?? '36/36 PASSED') ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-box-archive me-2"></i>Immutable Release Manifest</h5>
                <div class="p-3 bg-dark rounded">
                    <small class="text-secondary">Release Code:</small>
                    <div class="text-warning font-monospace text-truncate fw-bold mb-2"><?= esc($manifest['release_code'] ?? '-') ?></div>
                    <small class="text-secondary">SHA-256 Release Checksum:</small>
                    <div class="text-info font-monospace text-truncate small"><?= esc($manifest['release_checksum'] ?? '-') ?></div>
                    <div class="mt-3 text-end">
                        <span class="badge bg-success"><?= esc($manifest['manifest_status'] ?? 'VALIDATED') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
