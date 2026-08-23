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
        .timeline-step { border-left: 2px solid #3b82f6; padding-left: 15px; position: relative; margin-bottom: 12px; }
        .timeline-step::before { content: ''; position: absolute; left: -6px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: #3b82f6; }
    </style>
</head>
<body class="py-4">
<div class="container-fluid px-4">
    <!-- Banner Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
        <div>
            <h3 class="fw-bold text-info mb-1"><i class="fa-solid fa-microchip me-2"></i><?= esc($workspaceData['nama_asset'] ?? 'Gardu SDJ-045') ?> — Operational Action Workspace</h3>
            <small class="text-secondary"><i class="fa-solid fa-bolt me-1"></i>Connected Load: <?= esc($workspaceData['connected_load_kva'] ?? 120) ?> kVA | <?= esc($workspaceData['customer_count_impact'] ?? 340) ?> Pelanggan Tersambung</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-certificate me-1"></i>DATA TRUST: <?= esc($workspaceData['data_trust_index'] ?? 98.5) ?>% (HIGH)</span>
        </div>
    </div>

    <div class="row g-3">
        <!-- Left Panel: Decision Explainability & Action Panel -->
        <div class="col-md-7">
            <div class="card card-custom p-4 mb-3">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-circle-question me-2"></i>Decision Explainability Panel — Mengapa Tindakan Ini Direkomendasikan?</h5>
                <div class="alert alert-info bg-dark border-info text-light mb-3">
                    <h6 class="text-info fw-bold mb-1"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Rekomendasi Preskriptif Utama:</h6>
                    <span class="fs-5 text-warning fw-bold"><?= esc($explainability['recommendation_label'] ?? 'Major Overhaul / Ganti Aset Baru') ?></span>
                </div>

                <h6 class="text-secondary fw-bold mb-2">Bukti & Evaluasi Rules Engine:</h6>
                <ul class="list-group list-group-flush bg-transparent mb-3">
                    <?php if (!empty($explainability['why_reasons'])): ?>
                        <?php foreach ($explainability['why_reasons'] as $reason): ?>
                            <li class="list-group-item bg-transparent text-light border-secondary">
                                <i class="fa-solid fa-check text-success me-2"></i><?= esc($reason) ?>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top border-secondary">
                    <div>
                        <small class="text-secondary">Otoritas Wewenang Keputusan:</small>
                        <div class="fw-bold text-warning"><?= esc($explainability['human_authority_required'] ?? 'SUPERVISOR_ULP') ?></div>
                    </div>
                    <div>
                        <button class="btn btn-success me-2"><i class="fa-solid fa-thumbs-up me-1"></i>Setujui Tindakan</button>
                        <button class="btn btn-outline-warning"><i class="fa-solid fa-pen-to-square me-1"></i>Override / Ubah</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel: 10-Stage Operational Intelligence Lifecycle Timeline -->
        <div class="col-md-5">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-timeline me-2"></i>End-to-End Operational Lifecycle Timeline</h5>
                <div class="ps-2">
                    <?php if (!empty($timeline)): ?>
                        <?php foreach ($timeline as $stage): ?>
                            <div class="timeline-step">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-light"><?= esc($stage['stage_num']) ?>. <?= esc($stage['stage_label']) ?></strong>
                                    <span class="badge bg-success small"><?= esc($stage['status']) ?></span>
                                </div>
                                <small class="text-secondary font-monospace"><?= esc($stage['timestamp']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
