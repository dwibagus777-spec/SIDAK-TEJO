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
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-ranking-star me-2"></i>Enterprise Operational Twin Federation & Cross-Unit Benchmarking Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-code-compare me-1"></i>Cross-ULP Scorecard Normalization, Peer Benchmarking & Best Practice Knowledge Transfer</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-handshake me-1"></i>FEDERATION CONTRACT VALIDATED</span>
        </div>
    </div>

    <!-- Federated Cross-Unit Benchmarking & Knowledge Transfer Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-success mb-3"><i class="fa-solid fa-trophy me-2"></i>Federated Cross-Unit Benchmark Scorecard</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Federation Scope & Rank Position:</small>
                    <div class="fs-6 fw-bold text-success font-monospace"><?= esc($federatedBenchmark['unit_scope'] ?? 'PLN_UP3_SIDOARJO') ?> (Rank #<?= esc($federatedBenchmark['benchmark_rank_position'] ?? 1) ?> of <?= esc($federatedBenchmark['total_peer_units'] ?? 4) ?> Peer ULP Units)</div>
                    <small class="text-secondary">Comparability Contract Status:</small>
                    <div class="font-monospace text-info small"><?= esc($federatedBenchmark['comparability_status'] ?? 'COMPARABILITY_VALIDATED') ?> (Metric: <?= esc($federatedBenchmark['metric_version'] ?? 'METRIC-RESILIENCE-2026-v1.0') ?>)</div>
                </div>
                <div class="small text-light">Normalization: <span class="badge bg-info font-monospace"><?= esc($federatedBenchmark['normalization_version'] ?? 'NORMALIZATION-v1.0') ?></span> | Truth Class: <span class="text-warning"><?= esc($federatedBenchmark['federated_truth_class'] ?? 'PROJECTION_ONLY') ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-lightbulb me-2"></i>Operational Knowledge Transfer Bundle</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Knowledge Transfer Bundle ID:</small>
                    <div class="text-info font-monospace fw-bold mb-1"><?= esc($knowledgeAdvisory['bundle_id'] ?? 'KT-BDL-STJ-01') ?></div>
                    <small class="text-secondary">Recommended Best Practice Intervention:</small>
                    <div class="text-success font-monospace fw-bold fs-6"><?= esc($knowledgeAdvisory['recommended_best_practice'] ?? 'PREVENTIVE_THERMOVISION_CALIBRATION_STANDARD') ?></div>
                </div>
                <div class="small text-light">Auto Policy Propagation: <span class="badge bg-danger font-monospace">DENIED_REQUIRES_LOCAL_UNIT_ACCEPTANCE</span> | Local Authority Override: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
