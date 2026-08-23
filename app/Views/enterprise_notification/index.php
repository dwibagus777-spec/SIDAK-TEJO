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
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-paper-plane me-2"></i>Multi-Channel Field Notification Dispatch</h3>
            <small class="text-secondary"><i class="fa-solid fa-satellite-dish me-1"></i>Dispatch Mode: Non-Blocking Async Queue (Safe Fallback Enabled)</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-check-double me-1"></i>STATUS: DISPATCH_ACTIVE</span>
        </div>
    </div>

    <!-- Dispatch & Adapter Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-bell me-2"></i>Recent Dispatch Intent</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Dispatch Code & Channel:</small>
                    <div class="fs-5 fw-bold text-success font-monospace"><?= esc($dispatch['dispatch_code'] ?? 'NOTIF-20260822-001') ?> [<span class="text-warning"><?= esc($dispatch['channel'] ?? 'WHATSAPP') ?></span>]</div>
                    <small class="text-info font-monospace">Recipient: <?= esc($dispatch['recipient'] ?? 'PETUGAS_LAPANGAN') ?></small>
                </div>
                <div class="small text-light">Execution Mode: <span class="badge bg-info"><?= esc($dispatch['dispatch_mode'] ?? 'ASYNC_NON_BLOCKING') ?></span></div>
                <div class="small text-light mt-1">Correlation Ref: <span class="font-monospace text-warning"><?= esc($dispatch['correlation_ref'] ?? '-') ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-network-wired me-2"></i>Adapter Registry Status</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Active Adapter Execution:</small>
                    <div class="text-success font-monospace fw-bold mb-1"><?= esc($execRes['adapter_used'] ?? 'PLN_WA_GATEWAY_ADAPTER') ?> (<?= esc($execRes['http_latency_ms'] ?? 24) ?> ms)</div>
                    <small class="text-secondary">Delivery Result:</small>
                    <div class="text-info font-monospace text-truncate small"><?= esc($execRes['delivery_result'] ?? 'SUCCESSFUL') ?></div>
                </div>
                <div class="small text-light">Adapters Registered: <span class="badge bg-success">WHATSAPP, TELEGRAM, EMAIL, SMS, MOCK</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
