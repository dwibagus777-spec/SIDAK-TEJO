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
            <h3 class="fw-bold text-info mb-1"><i class="fa-solid fa-mobile-screen-button me-2"></i>Advanced Field Mobility & Offline Sync Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-wifi me-1"></i>Offline Buffer & Deterministic Conflict Resolution Engine (Version Vectors & Idempotency Keys)</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-signal me-1"></i>MESH NODE ONLINE</span>
        </div>
    </div>

    <!-- Mobility & Offline Sync Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-rotate me-2"></i>Offline Sync Resolution</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Idempotency Key:</small>
                    <div class="fs-6 fw-bold text-info font-monospace"><?= esc($syncStatus['idempotency_key'] ?? 'SYNC-ENV-001') ?></div>
                    <small class="text-secondary">Sync Status:</small>
                    <div><span class="badge bg-success font-monospace"><?= esc($syncStatus['sync_status'] ?? 'SYNCED') ?></span></div>
                </div>
                <div class="small text-light">Entity Version: <span class="badge bg-secondary font-monospace">v<?= esc($syncStatus['entity_version'] ?? 1) ?></span> | Replay Replayed: <span class="text-success"><?= ($syncStatus['replay_detected'] ?? false) ? 'YES' : 'NO' ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><div class="d-inline"><i class="fa-solid fa-network-wired me-2"></i>Mesh Telemetry Queue Buffer</div></h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Node ID:</small>
                    <div class="text-warning font-monospace fw-bold mb-1"><?= esc($meshStatus['mesh_node_id'] ?? 'MESH-NODE-01') ?></div>
                    <small class="text-secondary">Buffered Telemetry Packets:</small>
                    <div class="text-success font-monospace fw-bold fs-5"><?= esc($meshStatus['buffered_packets_cnt'] ?? 18) ?> Packets</div>
                </div>
                <div class="small text-light">Signal Quality: <span class="badge bg-info font-monospace"><?= esc($meshStatus['signal_quality_dbm'] ?? -68) ?> dBm</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
