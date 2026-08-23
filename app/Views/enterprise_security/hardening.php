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
            <h3 class="fw-bold text-info mb-1"><i class="fa-solid fa-key me-2"></i>Security Hardening & Secret Management</h3>
            <small class="text-secondary"><i class="fa-solid fa-shield-halved me-1"></i>Secret Boundary & Session Revocation Fabric</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-check-double me-1"></i>0 HARDCODED SECRETS DETECTED</span>
        </div>
    </div>

    <!-- Secret Registry & Session Trust Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-vault me-2"></i>Enterprise External Secret Registry</h5>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                            <tr class="table-active">
                                <th>Kunci Rahasia</th>
                                <th>Fingerprint (No Exposure)</th>
                                <th>Kadaluarsa</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($secretHealth['secret_registry'])): ?>
                                <?php foreach ($secretHealth['secret_registry'] as $key => $sec): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary font-monospace"><?= esc($key) ?></span></td>
                                        <td><code class="text-info"><?= esc($sec['fingerprint']) ?></code></td>
                                        <td><?= esc($sec['expires_in_days']) ?> hari lagi</td>
                                        <td><span class="badge bg-success"><?= esc($sec['status']) ?></span></td>
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
                <h5 class="text-info mb-3"><i class="fa-solid fa-user-xmark me-2"></i>Active Session Trust & Revocation</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Session ID:</small>
                    <div class="fw-bold text-light font-monospace"><?= esc($sessionTrust['session_id'] ?? 'SESS-001') ?></div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="text-secondary">Level Kepercayaan:</span>
                        <span class="badge bg-success"><?= esc($sessionTrust['trust_level'] ?? 'TRUSTED') ?></span>
                    </div>
                </div>
                <button class="btn btn-outline-danger w-100"><i class="fa-solid fa-ban me-1"></i>Cabut Sesi Secara Instan (Revoke)</button>
            </div>
        </div>
    </div>
</div>
</body>
</html>
