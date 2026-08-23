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
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-diagram-project me-2"></i>Enterprise SLD Adaptive Topology & Distribution Disaster Immunity Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-shield-cat me-1"></i>Real-Time SLD Graph Reconstruction, Observed vs Simulated State & Disaster Immunity</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-bolt me-1"></i>GRID TOPOLOGY ONLINE</span>
        </div>
    </div>

    <!-- SLD Dynamic Topology & Disaster Immunity Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-cyan mb-3"><i class="fa-solid fa-network-wired me-2"></i>SLD Dynamic Topology Graph</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Feeder Code & Substation Node:</small>
                    <div class="fs-6 fw-bold text-cyan font-monospace"><?= esc($sldTopology['feeder_code'] ?? 'P-BALUNG-20KV') ?> (<?= esc($sldTopology['active_substation_node'] ?? 'GI_SIDOARJO') ?>)</div>
                    <small class="text-secondary">Observed vs Simulated State:</small>
                    <div class="font-monospace text-success small"><?= esc($sldTopology['observed_topology_state'] ?? 'OBSERVED_CONFIRMED') ?> | <span class="text-info"><?= esc($sldTopology['simulated_topology_state'] ?? 'SIMULATED_READY') ?></span></div>
                </div>
                <div class="small text-light">Freshness: <span class="badge bg-info font-monospace"><?= esc($sldTopology['topology_freshness_sec'] ?? 12) ?>s</span> | Topology Engine: <span class="text-success"><?= esc($sldTopology['sld_topology_status'] ?? 'COMPLETED') ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-cloud-bolt me-2"></i>Grid Disaster Immunity & Emergency Load Transfer</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Immunity Assessment ID:</small>
                    <div class="text-warning font-monospace fw-bold mb-1"><?= esc($immunityAdvisory['immunity_assessment_id'] ?? 'DISASTER-IMM-STJ-01') ?></div>
                    <small class="text-secondary">Grid Disaster Immunity Score:</small>
                    <div class="text-success font-monospace fw-bold fs-6"><?= esc($immunityAdvisory['disaster_immunity_index'] ?? 88.5) ?> / 100 (Model: <?= esc($immunityAdvisory['hazard_model_version'] ?? 'HAZARD-DISASTER-2026-v1.0') ?>)</div>
                </div>
                <div class="small text-light">Remote Switching: <span class="badge bg-danger font-monospace">DENIED_REQUIRES_DISPATCHER_CLEARANCE</span> | SCADA Control Plane: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
