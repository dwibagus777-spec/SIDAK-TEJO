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
            <h3 class="fw-bold text-info mb-1"><i class="fa-solid fa-gauge-high me-2"></i>Enterprise Capacity, Performance & Scaling Control</h3>
            <small class="text-secondary"><i class="fa-solid fa-microchip me-1"></i>Runtime Memory Peak: <?= esc(round(($capacity['php_memory_peak_bytes'] ?? 0) / 1024 / 1024, 2)) ?> MB | Limit: <?= esc($capacity['php_memory_limit'] ?? '256M') ?></small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-shield-halved me-1"></i>GUARDRAIL: <?= esc($guardrail['critical_path_status'] ?? 'ENFORCED') ?></span>
        </div>
    </div>

    <!-- Capacity Snapshot & Read-Model Snapshot Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-database me-2"></i>Real Capacity Snapshot</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Capacity Metric Status:</small>
                    <div class="fs-5 fw-bold text-success font-monospace"><?= esc($capacity['capacity_status'] ?? 'CAPACITY_SNAPSHOT_AVAILABLE') ?></div>
                </div>
                <div class="small text-light">CPU Metric: <span class="badge bg-secondary"><?= esc($capacity['cpu_utilization_metric'] ?? 'METRIC_UNAVAILABLE') ?></span></div>
                <div class="small text-light mt-1">DB Connection: <span class="badge bg-success"><?= esc($capacity['database_connection'] ?? 'HEALTHY') ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-bolt me-2"></i>Lightweight Read-Model Snapshot</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Read Model Type & Latency:</small>
                    <div class="text-warning font-monospace fw-bold mb-1"><?= esc($readModel['read_model_type'] ?? 'PRE_AGGREGATED_CACHE') ?></div>
                    <small class="text-info font-monospace">Fetch Latency: <?= esc($readModel['fetch_latency_ms'] ?? 1.25) ?> ms</small>
                </div>
                <div class="small text-light">Inline Recalculation Trigger: <span class="badge bg-success"><?= esc(($readModel['inline_recalc_trigger'] ?? false) ? 'ACTIVE' : 'FALSE_LIGHTWEIGHT') ?></span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
