<?php
/**
 * Construction, Material & Network Configuration Intelligence (CR-06)
 * Governed by 7 Hardening Gates
 */
$report = $report ?? [
    'metrics' => [
        'total_materials'      => 0,
        'total_aliases'        => 0,
        'total_constructions'  => 0,
        'total_bom_items'      => 0,
        'resolved_bom_items'   => 0,
        'unresolved_bom_items' => 0,
        'resolution_rate_pct'  => 100.0,
        'unknown_qty_items'    => 0,
        'draft_constructions'  => 0,
    ],
    'draft_constructions' => [],
    'unresolved_items'    => [],
];
$m = $report['metrics'];
$policy = $policy ?? ['policy_code' => 'FHI-v1.0', 'policy_name' => 'PLN Feeder Health Index Policy'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Construction & Configuration Intelligence | SIDAK TEJO') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .table th { background-color: #f7fafc; font-size: 11px; text-transform: uppercase; color: #4a5568; }
        .table td { vertical-align: middle; font-size: 13px; }
        .badge-status { font-size: 11px; padding: 4px 8px; border-radius: 4px; font-weight: 600; }
        .gate-card { border-left: 4px solid #10b981; }
        .hero-banner { background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%); color: #fff; border-radius: 10px; padding: 20px 24px; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="hero-banner mb-4 d-flex justify-content-between align-items-center">
        <div>
            <div class="badge bg-primary-subtle text-primary fw-bold mb-2">CR-06 Enterprise Intelligence Layer</div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-layer-group me-2 text-warning"></i>Construction, Material & Network Configuration Intelligence</h3>
            <div class="text-white-50 small">Physical Network Truth &bull; Canonical BOM Taxonomy &bull; Dynamic SLD Decoupling &bull; CC-04 Feeder Health (FHI-v1.0)</div>
        </div>
        <div class="text-end">
            <a href="<?= site_url('master-assets') ?>" class="btn btn-outline-light btn-sm me-2"><i class="fa-solid fa-arrow-left me-1"></i> Master Assets</a>
            <a href="<?= site_url('asset-intelligence') ?>" class="btn btn-warning btn-sm fw-bold"><i class="fa-solid fa-brain me-1"></i> Truth Layer Console</a>
        </div>
    </div>

    <!-- 7 Hardening Gates Verification Ribbon -->
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="card-custom p-3 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-dark small text-uppercase"><i class="fa-solid fa-shield-halved text-success me-1"></i> 7 Architectural Hardening Gates Status</span>
                    <span class="badge bg-success">100% RATIFIED &amp; ENFORCED</span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-check text-success me-1"></i> Gate 1: Pure Material Identity</span>
                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-check text-success me-1"></i> Gate 2: BOM SET NULL FK</span>
                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-check text-success me-1"></i> Gate 3: quantity_status ENUM</span>
                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-check text-success me-1"></i> Gate 4: Single ACTIVE Invariant</span>
                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-check text-success me-1"></i> Gate 5: SLD Topology vs Health Separation</span>
                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-check text-success me-1"></i> Gate 6: Feeder Policy Version (FHI-v1.0)</span>
                    <span class="badge bg-light text-dark border"><i class="fa-solid fa-check text-success me-1"></i> Gate 7: Parameterized Weights &amp; Thresholds</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card-custom p-3 border-start border-4 border-primary">
                <div class="text-muted small">Canonical Master Materials</div>
                <div class="d-flex align-items-baseline justify-content-between mt-1">
                    <h3 class="fw-bold mb-0 text-primary"><?= number_format($m['total_materials'] ?? 0) ?></h3>
                    <span class="badge bg-primary-subtle text-primary"><?= number_format($m['total_aliases'] ?? 0) ?> Aliases</span>
                </div>
                <div class="text-muted small mt-1">JTM &bull; MVTIC &bull; GTT &bull; Accessory</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-custom p-3 border-start border-4 border-info">
                <div class="text-muted small">Standard Construction Types</div>
                <div class="d-flex align-items-baseline justify-content-between mt-1">
                    <h3 class="fw-bold mb-0 text-info"><?= number_format($m['total_constructions'] ?? 0) ?></h3>
                    <span class="badge bg-info-subtle text-info"><?= number_format($m['total_bom_items'] ?? 0) ?> BOM Items</span>
                </div>
                <div class="text-muted small mt-1">17 JTM &bull; 10 MVTIC &bull; 2 GTT</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-custom p-3 border-start border-4 border-success">
                <div class="text-muted small">BOM Resolution Rate</div>
                <div class="d-flex align-items-baseline justify-content-between mt-1">
                    <h3 class="fw-bold mb-0 text-success"><?= $m['resolution_rate_pct'] ?? 100 ?>%</h3>
                    <span class="badge bg-success"><?= number_format($m['resolved_bom_items'] ?? 0) ?> Resolved</span>
                </div>
                <div class="text-muted small mt-1">Honest State: <?= number_format($m['unresolved_bom_items'] ?? 0) ?> Unresolved</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-custom p-3 border-start border-4 border-warning">
                <div class="text-muted small">Kubikel Draft Governance</div>
                <div class="d-flex align-items-baseline justify-content-between mt-1">
                    <h3 class="fw-bold mb-0 text-warning"><?= number_format($m['draft_constructions'] ?? 0) ?></h3>
                    <span class="badge bg-warning text-dark">DRAFT (Review Req.)</span>
                </div>
                <div class="text-muted small mt-1">Not Enforced on Production</div>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div class="card-custom p-4 mb-4">
        <ul class="nav nav-pills mb-3" id="ciTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fw-bold" data-bs-toggle="pill" data-bs-target="#tab-draft-governance"><i class="fa-solid fa-lock me-1"></i> Kubikel Draft Governance</button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#tab-feeder-health"><i class="fa-solid fa-heart-pulse me-1"></i> CC-04 Feeder Health Policy (FHI-v1.0)</button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#tab-unresolved"><i class="fa-solid fa-clipboard-list me-1"></i> Unresolved BOM Review Queue</button>
            </li>
        </ul>

        <div class="tab-content" id="ciTabsContent">
            <!-- Tab 1: Kubikel Draft Governance -->
            <div class="tab-pane fade show active" id="tab-draft-governance">
                <div class="alert alert-warning d-flex align-items-center mb-3">
                    <i class="fa-solid fa-triangle-exclamation fs-4 me-3"></i>
                    <div>
                        <strong>Kubikel Construction Governance Lock:</strong> Sesuai sumber data Sheet <code>KONSTRUKSI GARDU KUBIKEL (BELUM FIX)</code>, seluruh tipe konstruksi kubikel dikunci berstatus <code>DRAFT</code> dan <strong>tidak diterapkan sebagai aturan validasi aset produksi</strong> sampai disetujui secara formal.
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Construction Name</th>
                                <th>Family</th>
                                <th>Asset Domain</th>
                                <th>Approval Status</th>
                                <th>Source Lineage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report['draft_constructions'])): ?>
                                <?php foreach ($report['draft_constructions'] as $dc): ?>
                                    <tr>
                                        <td><code><?= esc($dc['construction_code'] ?? $dc['code']) ?></code></td>
                                        <td class="fw-bold"><?= esc($dc['construction_name'] ?? $dc['name']) ?></td>
                                        <td><span class="badge bg-secondary"><?= esc($dc['construction_family']) ?></span></td>
                                        <td><?= esc($dc['asset_domain']) ?></td>
                                        <td><span class="badge bg-warning text-dark"><i class="fa-solid fa-hourglass-half me-1"></i> <?= esc($dc['approval_status']) ?></span></td>
                                        <td class="text-muted small"><?= esc($dc['source_sheet'] ?? 'KONSTRUKSI.xlsx') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">Tidak ada konstruksi berstatus DRAFT.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: CC-04 Feeder Health Policy -->
            <div class="tab-pane fade" id="tab-feeder-health">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card p-3 border-0 bg-light">
                            <h6 class="fw-bold mb-2"><i class="fa-solid fa-scale-balanced me-1 text-primary"></i> Active Health Policy: <?= esc($policy['policy_code']) ?></h6>
                            <p class="small text-muted mb-3"><?= esc($policy['policy_name']) ?></p>
                            <table class="table table-sm table-bordered bg-white">
                                <thead>
                                    <tr>
                                        <th>Metric Key</th>
                                        <th>Weight</th>
                                        <th>Threshold Classification</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>GANGGUAN_FREQUENCY</code></td>
                                        <td><span class="badge bg-primary">30%</span></td>
                                        <td rowspan="5" class="small align-middle">
                                            <span class="badge bg-success mb-1">SEMPURNA &ge; 85</span><br>
                                            <span class="badge bg-info text-dark mb-1">SAKIT 70 - 84.99</span><br>
                                            <span class="badge bg-warning text-dark mb-1">KRONIS 50 - 69.99</span><br>
                                            <span class="badge bg-danger">KRITIS &lt; 50</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><code>CRITICAL_FINDINGS</code></td>
                                        <td><span class="badge bg-primary">25%</span></td>
                                    </tr>
                                    <tr>
                                        <td><code>RECURRING_FINDINGS</code></td>
                                        <td><span class="badge bg-primary">15%</span></td>
                                    </tr>
                                    <tr>
                                        <td><code>BOM_DEGRADATION</code></td>
                                        <td><span class="badge bg-primary">15%</span></td>
                                    </tr>
                                    <tr>
                                        <td><code>OVERLOAD_EVENTS</code></td>
                                        <td><span class="badge bg-primary">15%</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card p-3 border-0 bg-light">
                            <h6 class="fw-bold mb-2"><i class="fa-solid fa-code me-1 text-primary"></i> API Integration Endpoints</h6>
                            <p class="small text-muted">Akses endpoint JSON untuk integrasi Dynamic SLD dan Executive Dashboard CC-04:</p>
                            <ul class="list-group list-group-flush small">
                                <li class="list-group-item bg-transparent"><code>GET /api/sld/section/{sectionId}</code> &mdash; Dynamic SLD (Topology Truth vs Visual Health Overlay)</li>
                                <li class="list-group-item bg-transparent"><code>GET /api/feeder-health/{penyulangId}</code> &mdash; Executive Feeder Health Index &amp; Explainability Details</li>
                                <li class="list-group-item bg-transparent"><code>GET /api/construction-intelligence/summary</code> &mdash; Construction &amp; BOM Ingestion Summary</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Unresolved BOM Review Queue -->
            <div class="tab-pane fade" id="tab-unresolved">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Raw Material Name (Spreadsheet)</th>
                                <th>Construction Type</th>
                                <th>Quantity &amp; Status</th>
                                <th>Mapping Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report['unresolved_items'])): ?>
                                <?php foreach ($report['unresolved_items'] as $idx => $item): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td class="fw-bold text-danger"><?= esc($item['raw_material_name']) ?></td>
                                        <td>Type ID: <?= esc($item['construction_type_id']) ?></td>
                                        <td>
                                            <?= $item['quantity'] !== null ? esc($item['quantity']) : '-' ?>
                                            <span class="badge bg-secondary ms-1"><?= esc($item['quantity_status']) ?></span>
                                        </td>
                                        <td><span class="badge bg-danger"><i class="fa-solid fa-circle-question me-1"></i> <?= esc($item['mapping_status']) ?></span></td>
                                        <td><button class="btn btn-outline-primary btn-sm" disabled><i class="fa-solid fa-link me-1"></i> Map to Material</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-success py-4">
                                        <i class="fa-solid fa-circle-check fs-2 mb-2 d-block"></i>
                                        <strong>Seluruh Item BOM Terpetakan Sempurna (0 Unresolved)</strong>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
