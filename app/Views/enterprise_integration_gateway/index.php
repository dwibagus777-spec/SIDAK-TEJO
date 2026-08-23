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
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-diagram-project me-2"></i>Federated Integration Gateway & Adapter Control Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-shield-halved me-1"></i>Provider Isolation, Circuit Breaker Registry (APKT, SAP ERP, AMR, SCADA) & Bounded Rate-Limiting</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-server me-1"></i>GATEWAY ONLINE</span>
        </div>
    </div>

    <!-- Gateway & Adapter Status Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-route me-2"></i>Federated Gateway Status</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Adapter Selected:</small>
                    <div class="fs-6 fw-bold text-info font-monospace"><?= esc($gatewayStatus['adapter_id'] ?? 'APKT_OUTAGE_ADAPTER') ?></div>
                    <small class="text-secondary">Correlation ID:</small>
                    <div class="font-monospace text-success small"><?= esc($gatewayStatus['correlation_id'] ?? 'CORR-STJ-99') ?></div>
                </div>
                <div class="small text-light">Rate Limiting: <span class="badge bg-success font-monospace"><?= esc($gatewayStatus['rate_limit_evaluated'] ?? 'ALLOWED_UNDER_QUOTA') ?></span> | Schema Validated: <span class="text-success"><?= ($gatewayStatus['schema_validated'] ?? true) ? 'PASSED' : 'FAILED' ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-plug-circle-check me-2"></i>External Adapter Circuit Breaker Status</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Registered Enterprise Adapters:</small>
                    <div class="text-warning font-monospace fw-bold mb-2"><?= esc($adapterHealth['registered_adapters_cnt'] ?? 5) ?> Adapters Active</div>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Adapter</th>
                                    <th>Status</th>
                                    <th>Circuit Breaker</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($adapterHealth['adapters'] ?? []) as $name => $info): ?>
                                    <tr>
                                        <td class="font-monospace text-info"><?= esc($name) ?></td>
                                        <td><span class="badge bg-<?= $info['status'] === 'HEALTHY' ? 'success' : 'warning' ?>"><?= esc($info['status']) ?></span></td>
                                        <td><span class="badge bg-<?= $info['circuit'] === 'CLOSED' ? 'success' : 'danger' ?>"><?= esc($info['circuit']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
