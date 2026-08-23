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
            <h3 class="fw-bold text-info mb-1"><i class="fa-solid fa-user-gear me-2"></i><?= esc($roleData['role_profile']['role_name'] ?? 'Petugas Lapangan') ?></h3>
            <small class="text-secondary"><i class="fa-solid fa-crosshairs me-1"></i>Fokus Utang: <?= esc($roleData['role_profile']['primary_focus'] ?? 'Eksekusi Lapangan') ?></small>
        </div>
        <div>
            <span class="badge bg-primary px-3 py-2 fs-6"><i class="fa-solid fa-mobile-screen me-1"></i>ADAPTIVE WORKSPACE ACTIVE</span>
        </div>
    </div>

    <!-- Next Best Action Card -->
    <div class="card card-custom p-4 mb-4 border-info">
        <h5 class="text-info mb-2"><i class="fa-solid fa-arrow-right-to-city me-2"></i>Contextual Next Best Action:</h5>
        <div class="fs-4 text-warning fw-bold mb-2"><?= esc($roleData['role_profile']['next_best_action'] ?? 'Lakukan Tindakan Operasional') ?></div>
        <div class="d-flex gap-2">
            <?php if (!empty($roleData['role_profile']['allowed_actions'])): ?>
                <?php foreach ($roleData['role_profile']['allowed_actions'] as $action): ?>
                    <span class="badge bg-secondary px-3 py-2 font-monospace"><?= esc($action) ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Asset Details Card -->
    <div class="card card-custom p-4">
        <h5 class="text-light mb-3"><i class="fa-solid fa-cubes me-2"></i>Konteks Operasional Aset Tersambung</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="p-3 bg-dark rounded">
                    <small class="text-secondary">Nama Aset:</small>
                    <div class="fw-bold text-info"><?= esc($roleData['asset_action_workspace']['nama_asset'] ?? 'Gardu SDJ-045') ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-dark rounded">
                    <small class="text-secondary">Current Health Index:</small>
                    <div class="fw-bold text-success"><?= esc($roleData['asset_action_workspace']['current_health_score'] ?? 74) ?> / 100</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-dark rounded">
                    <small class="text-secondary">Rekomendasi Preskriptif:</small>
                    <div class="fw-bold text-warning"><?= esc($roleData['asset_action_workspace']['recommended_intervention'] ?? 'WHAT_IF_REPLACE_NOW') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
