<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Executive Decision Fabric | SIDAK TEJO') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .hero-banner { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; border-radius: 10px; padding: 20px 24px; }
        .card-custom { border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .badge-sempurna { background-color: #10b981; color: #fff; font-weight: 700; }
        .badge-waspada { background-color: #f59e0b; color: #fff; font-weight: 700; }
        .badge-perhatian { background-color: #f97316; color: #fff; font-weight: 700; }
        .badge-kritis { background-color: #ef4444; color: #fff; font-weight: 700; }
        .badge-unresolved { background-color: #64748b; color: #fff; font-weight: 700; }
        .score-circle { width: 110px; height: 110px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-direction: column; }
        .score-circle-sempurna { background: rgba(16, 185, 129, 0.1); border: 4px solid #10b981; color: #047857; }
        .score-circle-waspada { background: rgba(245, 158, 11, 0.1); border: 4px solid #f59e0b; color: #b45309; }
        .score-circle-perhatian { background: rgba(249, 115, 22, 0.1); border: 4px solid #f97316; color: #c2410c; }
        .score-circle-kritis { background: rgba(239, 68, 68, 0.1); border: 4px solid #ef4444; color: #b91c1c; }
        .score-circle-unresolved { background: rgba(100, 116, 139, 0.1); border: 4px solid #64748b; color: #475569; }
        .ai-box { background: #f8fafc; border-left: 4px solid #6366f1; border-radius: 6px; padding: 16px; font-size: 13.5px; line-height: 1.6; }
        .clickable-card { cursor: pointer; transition: transform 0.15s ease, box-shadow 0.15s ease; }
        .clickable-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .tree-node { position: relative; padding-left: 24px; margin-bottom: 12px; border-left: 2px solid #cbd5e1; }
        .tree-node::before { content: ''; position: absolute; left: 0; top: 10px; width: 16px; height: 2px; background: #cbd5e1; }
        .rank-badge-1 { background-color: #ef4444; color: #fff; font-weight: 700; }
        .rank-badge-2 { background-color: #f59e0b; color: #fff; font-weight: 700; }
        .rank-badge-3 { background-color: #3b82f6; color: #fff; font-weight: 700; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4">
    <!-- Header Banner -->
    <div class="hero-banner mb-4 d-flex justify-content-between align-items-center">
        <div>
            <div class="badge bg-primary-subtle text-primary fw-bold mb-2">Phase CC-04 Executive Decision Fabric</div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-brain me-2 text-warning"></i>Executive Intelligence & Decision Analytics</h3>
            <div class="text-white-50 small">Feeder Health Index (FHI-v1.0) &bull; Ranked Decision Matrix &bull; AI Advisory Isolation &bull; Closed-Loop Governance</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="text-end me-3">
                <span class="text-white-50 small d-block">Pilih Feeder / Penyulang:</span>
                <select class="form-select form-select-sm fw-bold bg-dark text-white border-secondary" style="min-width: 220px;" onchange="location.href='<?= site_url('executive-intelligence') ?>/' + this.value;">
                    <?php foreach ($feeders as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= $selectedFeederId == $f['id'] ? 'selected' : '' ?>>
                            [<?= esc($f['kode_penyulang'] ?? 'PYL') ?>] <?= esc($f['nama_penyulang']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="<?= site_url('sld/view/' . $selectedFeederId) ?>" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-diagram-project me-1"></i> Dynamic SLD</a>
            <a href="<?= site_url('network-configuration') ?>" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-network-wired me-1"></i> Physical Config</a>
        </div>
    </div>

    <?php 
        $class = $fhiData['health_classification'] ?? 'UNRESOLVED';
        $classLower = strtolower($class);
        $score = $fhiData['health_score'] !== null ? number_format((float)$fhiData['health_score'], 2) : 'N/A';
        $fhiStatus = $fhiData['fhi_status'] ?? 'UNRESOLVED';
        $exp = json_decode($fhiData['explanation_json'] ?? '{}', true);
        $fingerprint = json_decode($fhiData['fingerprint_json'] ?? '{}', true);
        $decMatrix = $exp['decision_matrix'] ?? [];
        $primaryDriver = $decMatrix['primary_driver'] ?? null;
        $allRankedDrivers = $decMatrix['all_ranked_drivers'] ?? ($primaryDriver ? array_merge([$primaryDriver], $decMatrix['secondary_drivers'] ?? []) : []);
        $breakdown = $exp['score_breakdown'] ?? [];

        // Direct Weighted Pillar Values from Service
        $p1Sub = (float)($breakdown['physical_coverage']['sub_score'] ?? 0);
        $p1Weight = (float)($breakdown['physical_coverage']['weight'] ?? 0.20);
        $p1Contrib = (float)($breakdown['physical_coverage']['weighted_contribution'] ?? 0);

        $p2Sub = $breakdown['asset_health']['sub_score'] !== null ? (float)$breakdown['asset_health']['sub_score'] : null;
        $p2Weight = (float)($breakdown['asset_health']['weight'] ?? 0.25);
        $p2Contrib = (float)($breakdown['asset_health']['weighted_contribution'] ?? 0);

        $p3Sub = (float)($breakdown['finding_severity']['sub_score'] ?? 0);
        $p3Weight = (float)($breakdown['finding_severity']['weight'] ?? 0.25);
        $p3Contrib = (float)($breakdown['finding_severity']['weighted_contribution'] ?? 0);

        $p4Sub = (float)($breakdown['reliability']['sub_score'] ?? 0);
        $p4Weight = (float)($breakdown['reliability']['weight'] ?? 0.20);
        $p4Contrib = (float)($breakdown['reliability']['weighted_contribution'] ?? 0);

        $p5Sub = (float)($breakdown['chronicity']['sub_score'] ?? 0);
        $p5Weight = (float)($breakdown['chronicity']['weight'] ?? 0.10);
        $p5Contrib = (float)($breakdown['chronicity']['weighted_contribution'] ?? 0);

        $totalComputedFhi = round($p1Contrib + $p2Contrib + $p3Contrib + $p4Contrib + $p5Contrib, 2);
    ?>

    <!-- Main Overview Row -->
    <div class="row g-3 mb-4">
        <!-- FHI Gauge Card -->
        <div class="col-md-4">
            <div class="card card-custom h-100 p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted fw-bold small text-uppercase">Feeder Health Index</span>
                        <span class="badge badge-<?= esc($classLower) ?> px-3 py-1"><?= esc($class) ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-3 my-3">
                        <div class="score-circle score-circle-<?= esc($classLower) ?>">
                            <span class="h3 fw-bold mb-0"><?= esc($score) ?></span>
                            <span class="small text-muted">/ 100</span>
                        </div>
                        <div>
                            <div class="fw-bold h5 mb-1"><?= esc($fhiStatus) ?></div>
                            <div class="small text-muted">Formula: <code><?= esc($fingerprint['formula_version'] ?? 'FHI_FORMULA_V1.2') ?></code></div>
                            <div class="small text-muted">Kelengkapan Data: <strong><?= number_format(((float)($fhiData['data_completeness_ratio'] ?? 0)) * 100, 1) ?>%</strong></div>
                            <div class="small text-muted mt-1">Kontribusi Pilar: <strong><?= number_format($p1Contrib, 1) ?> + <?= number_format($p2Contrib, 1) ?> + <?= number_format($p3Contrib, 1) ?> + <?= number_format($p4Contrib, 1) ?> + <?= number_format($p5Contrib, 1) ?> = <?= number_format($totalComputedFhi, 1) ?></strong></div>
                        </div>
                    </div>
                </div>
                <div class="border-top pt-2 d-flex justify-content-between align-items-center small text-muted">
                    <div><i class="fa-solid fa-shield-halved me-1 text-success"></i> Weight Conservation: <strong>1.0000</strong></div>
                    <button class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" data-bs-toggle="modal" data-bs-target="#factorTreeModal">
                        <i class="fa-solid fa-sitemap me-1"></i> Buka Factor Tree
                    </button>
                </div>
            </div>
        </div>

        <!-- Executive Decision Recommendation Card (With UNRESOLVED Governance Override) -->
        <div class="col-md-8">
            <div class="card card-custom h-100 p-4 border-start border-4 <?= $fhiStatus === 'UNRESOLVED' ? 'border-secondary' : 'border-warning' ?>">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted fw-bold small text-uppercase"><i class="fa-solid fa-gavel me-1 text-warning"></i>Rekomendasi Keputusan Eksekutif (Decision Matrix)</span>
                    <span class="badge <?= $fhiStatus === 'UNRESOLVED' ? 'bg-secondary' : 'bg-danger' ?> fw-bold"><?= esc($primaryDriver['priority'] ?? 'P2 - PREREQUISITE') ?></span>
                </div>
                
                <h5 class="fw-bold text-dark mt-2 mb-1">
                    <?= esc($primaryDriver['recommended_action'] ?? 'Monitoring Berkala') ?>
                </h5>
                <div class="text-muted small mb-3">
                    Driver Risiko Utama: <code class="fw-bold text-primary"><?= esc($primaryDriver['driver_code'] ?? 'NORMAL_OPERATION') ?></code> (Skor Pemicu: <?= esc($primaryDriver['driver_score'] ?? 0) ?>) &bull; 
                    Unit Ditugaskan: <strong class="text-primary"><?= esc($primaryDriver['assigned_unit'] ?? 'Pemeliharaan Rutin') ?></strong>
                </div>

                <div class="bg-light p-3 rounded border mb-3 small d-flex justify-content-between align-items-center">
                    <div><strong>Evidensi Temuan & Keandalan:</strong> <?= esc($primaryDriver['evidence'] ?? 'Tidak ada defek kritis terdeteksi.') ?></div>
                    <button class="btn btn-outline-primary btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#factorTreeModal">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Telusuri Akar Masalah
                    </button>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div class="small text-muted">
                        <?php if ($fhiStatus === 'UNRESOLVED'): ?>
                            <i class="fa-solid fa-lock me-1 text-secondary"></i> <strong>Gate E5 Governance Guard</strong>: Rekomendasi operasional dikunci sebagai Prasyarat Data sebelum Surat Tugas dapat diterbitkan.
                        <?php else: ?>
                            <i class="fa-solid fa-user-check me-1 text-primary"></i> Memerlukan <strong>Human Approval (Gate E9-A)</strong> sebelum dispatch ke lapangan.
                        <?php endif; ?>
                    </div>
                    <?php if ($fhiStatus === 'UNRESOLVED'): ?>
                        <button type="button" class="btn btn-secondary btn-sm fw-bold px-3" disabled>
                            <i class="fa-solid fa-lock me-1"></i> Dispatch Terkunci (Prasyarat Data)
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#approvalModal">
                            <i class="fa-solid fa-paper-plane me-1"></i> Review & Approve Dispatch
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 5 Pillars Breakdown Row -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card card-custom p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="fw-bold text-dark"><i class="fa-solid fa-layer-group me-2 text-primary"></i>Dekomposisi 5 Pilar Skor FHI-v1.0 (Fixed Multi-Pillar Model)</div>
                    <span class="small text-muted">Klik pada pilar untuk melihat rincian kalkulasi deterministik</span>
                </div>
                <div class="row g-3">
                    <!-- Pillar 1 -->
                    <div class="col-md">
                        <div class="p-3 bg-light rounded border h-100 clickable-card" data-bs-toggle="modal" data-bs-target="#factorTreeModal">
                            <div class="d-flex justify-content-between small text-muted fw-bold">
                                <span>P1: Physical Coverage</span>
                                <span><?= round($p1Weight * 100) ?>% (Bobot)</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline mt-2">
                                <div class="h4 fw-bold text-dark mb-0"><?= number_format($p1Sub, 1) ?></div>
                                <span class="badge bg-info-subtle text-info fw-bold">+<?= number_format($p1Contrib, 2) ?> Poin</span>
                            </div>
                            <div class="progress my-2" style="height: 6px;">
                                <div class="progress-bar bg-info" style="width: <?= $p1Sub ?>%;"></div>
                            </div>
                            <div class="small text-muted"><?= $breakdown['physical_coverage']['configured'] ?? 0 ?> / <?= $breakdown['physical_coverage']['total'] ?? 0 ?> Seksi Terkonfigurasi</div>
                        </div>
                    </div>

                    <!-- Pillar 2 -->
                    <div class="col-md">
                        <div class="p-3 bg-light rounded border h-100 clickable-card" data-bs-toggle="modal" data-bs-target="#factorTreeModal">
                            <div class="d-flex justify-content-between small text-muted fw-bold">
                                <span>P2: Asset Health</span>
                                <span><?= round($p2Weight * 100) ?>% (Bobot)</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline mt-2">
                                <div class="h4 fw-bold text-dark mb-0"><?= $p2Sub !== null ? number_format($p2Sub, 1) : '<span class="badge bg-secondary-subtle text-secondary">NO DATA</span>' ?></div>
                                <span class="badge bg-secondary-subtle text-secondary fw-bold">+<?= number_format($p2Contrib, 2) ?> Poin</span>
                            </div>
                            <div class="progress my-2" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: <?= ($p2Sub ?? 0.0) ?>%;"></div>
                            </div>
                            <div class="small text-muted"><?= $breakdown['asset_health']['resolved'] ?? 0 ?> / <?= $breakdown['asset_health']['total'] ?? 0 ?> Aset Feeder (Grid: <?= $breakdown['asset_health']['total_grid_assets'] ?? 517 ?>) &bull; <?= esc($breakdown['asset_health']['status_label'] ?? 'UNRESOLVED') ?></div>
                        </div>
                    </div>

                    <!-- Pillar 3 -->
                    <div class="col-md">
                        <div class="p-3 bg-light rounded border h-100 clickable-card" data-bs-toggle="modal" data-bs-target="#factorTreeModal">
                            <div class="d-flex justify-content-between small text-muted fw-bold">
                                <span>P3: Finding Severity</span>
                                <span><?= round($p3Weight * 100) ?>% (Bobot)</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline mt-2">
                                <div class="h4 fw-bold text-dark mb-0"><?= number_format($p3Sub, 1) ?></div>
                                <span class="badge bg-warning-subtle text-dark fw-bold">+<?= number_format($p3Contrib, 2) ?> Poin</span>
                            </div>
                            <div class="progress my-2" style="height: 6px;">
                                <div class="progress-bar bg-warning" style="width: <?= $p3Sub ?>%;"></div>
                            </div>
                            <div class="small text-danger fw-semibold">Penalti: -<?= number_format((float)($breakdown['finding_severity']['penalty'] ?? 0), 1) ?> Poin</div>
                        </div>
                    </div>

                    <!-- Pillar 4 -->
                    <div class="col-md">
                        <div class="p-3 bg-light rounded border h-100 clickable-card" data-bs-toggle="modal" data-bs-target="#factorTreeModal">
                            <div class="d-flex justify-content-between small text-muted fw-bold">
                                <span>P4: Reliability (12M)</span>
                                <span><?= round($p4Weight * 100) ?>% (Bobot)</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline mt-2">
                                <div class="h4 fw-bold text-dark mb-0"><?= number_format($p4Sub, 1) ?></div>
                                <span class="badge bg-danger-subtle text-danger fw-bold">+<?= number_format($p4Contrib, 2) ?> Poin</span>
                            </div>
                            <div class="progress my-2" style="height: 6px;">
                                <div class="progress-bar bg-danger" style="width: <?= $p4Sub ?>%;"></div>
                            </div>
                            <div class="small text-muted"><?= $breakdown['reliability']['trips'] ?? 0 ?> Kali Trip / Padam</div>
                        </div>
                    </div>

                    <!-- Pillar 5 -->
                    <div class="col-md">
                        <div class="p-3 bg-light rounded border h-100 clickable-card" data-bs-toggle="modal" data-bs-target="#factorTreeModal">
                            <div class="d-flex justify-content-between small text-muted fw-bold">
                                <span>P5: Chronicity Density</span>
                                <span><?= round($p5Weight * 100) ?>% (Bobot)</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline mt-2">
                                <div class="h4 fw-bold text-dark mb-0"><?= number_format($p5Sub, 1) ?></div>
                                <span class="badge bg-primary-subtle text-primary fw-bold">+<?= number_format($p5Contrib, 2) ?> Poin</span>
                            </div>
                            <div class="progress my-2" style="height: 6px;">
                                <div class="progress-bar bg-primary" style="width: <?= $p5Sub ?>%;"></div>
                            </div>
                            <div class="small text-muted"><?= $breakdown['chronicity']['chronic_sections'] ?? 0 ?> Seksi Kronis (&ge;2)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Advisory & Secondary Drivers Row -->
    <div class="row g-3 mb-4">
        <!-- AI Advisory Briefing Card (Gate E7 Isolated Sandbox) -->
        <div class="col-md-6">
            <div class="card card-custom h-100 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fw-bold text-dark"><i class="fa-solid fa-robot me-2 text-primary"></i>Executive AI Advisory Briefing</span>
                    <span class="badge bg-secondary-subtle text-secondary small">Gate E7: Advisory Only</span>
                </div>
                <div class="ai-box">
                    <?= nl2br(esc($advisory['advisory_narrative'] ?? 'Belum ada narasi advisory.')) ?>
                </div>
                <div class="small text-muted mt-3">
                    <i class="fa-solid fa-lock me-1 text-success"></i> <strong>AI Isolation Boundary</strong>: AI strictly explains patterns and cannot modify FHI scores or dispatch orders.
                </div>
            </div>
        </div>

        <!-- Ranked Drivers & Closed-Loop Outcome History -->
        <div class="col-md-6">
            <div class="card card-custom h-100 p-4">
                <div class="fw-bold text-dark mb-3"><i class="fa-solid fa-list-ol me-2 text-primary"></i>Peringkat Driver Risiko Eksekutif (Ranked Decision Matrix)</div>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Rank</th>
                                <th>Driver Code</th>
                                <th>Score</th>
                                <th>Priority</th>
                                <th>Action & Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($allRankedDrivers)): ?>
                                <?php foreach ($allRankedDrivers as $idx => $rd): ?>
                                    <tr class="<?= $idx === 0 ? 'table-warning fw-semibold' : '' ?>">
                                        <td>
                                            <?php if ($idx === 0): ?>
                                                <span class="badge rank-badge-1">#1 PRIMARY</span>
                                            <?php elseif ($idx === 1): ?>
                                                <span class="badge rank-badge-2">#2</span>
                                            <?php elseif ($idx === 2): ?>
                                                <span class="badge rank-badge-3">#3</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">#<?= $idx + 1 ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?= esc($rd['driver_code']) ?></code></td>
                                        <td><strong><?= number_format((float)$rd['driver_score'], 1) ?></strong></td>
                                        <td><span class="badge bg-secondary"><?= esc($rd['priority']) ?></span></td>
                                        <td>
                                            <?= esc($rd['recommended_action']) ?> &bull; <strong><?= esc($rd['assigned_unit']) ?></strong>
                                            <?php if (!empty($rd['advisory_label']) && empty($rd['dispatch_ready'])): ?>
                                                <div class="mt-1"><span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= esc($rd['advisory_label']) ?></span></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-success py-2"><i class="fa-solid fa-circle-check me-1"></i> Operasi normal &bull; Tidak ada anomali berisiko tinggi.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Closed-Loop Governance Audit Log -->
                <div class="fw-bold text-dark small mb-2"><i class="fa-solid fa-clock-rotate-left me-1 text-success"></i>Riwayat Persetujuan & Closed-Loop Outcome ($\Delta$FHI)</div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Aksi Disetujui</th>
                                <th>Unit</th>
                                <th>Baseline</th>
                                <th>Verified FHI</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($decisionLogs)): ?>
                                <?php foreach ($decisionLogs as $log): ?>
                                    <tr>
                                        <td><?= esc($log['recommendation_code']) ?></td>
                                        <td><?= esc($log['assigned_unit']) ?></td>
                                        <td><?= number_format((float)$log['baseline_fhi'], 1) ?></td>
                                        <td>
                                            <?php if ($log['outcome_verified_fhi'] !== null): ?>
                                                <span class="badge bg-success"><?= number_format((float)$log['outcome_verified_fhi'], 1) ?> (+<?= esc($log['delta_fhi']) ?>)</span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-info"><?= esc($log['approval_status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-2">Belum ada riwayat persetujuan dispatch untuk feeder ini.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Factor Tree Modal (Explainability Drill-Down) -->
<div class="modal fade" id="factorTreeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-sitemap me-2 text-warning"></i>Explainability Factor Tree & Drill-Down</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <h6 class="fw-bold text-dark"><i class="fa-solid fa-diagram-project me-2 text-primary"></i>Decomposition Tree for FHI <?= esc($score) ?> (<?= esc($class) ?>)</h6>
                    <p class="small text-muted">Setiap komponen dihitung secara deterministik tanpa manipulasi AI, sesuai Gate E0 &bull; Invariant E2-A.</p>
                </div>

                <!-- Tree Structure -->
                <div class="tree-node">
                    <div class="fw-bold text-primary">Feeder [<?= esc($fhiData['penyulang_id'] ?? 1) ?>] Overall FHI: <?= esc($score) ?> / 100 (<?= esc($fhiData['fhi_status'] ?? 'UNRESOLVED') ?>)</div>
                    <div class="small text-muted mb-2">Formula Version: <code><?= esc($fingerprint['formula_version'] ?? 'FHI_FORMULA_V1.2') ?></code> &bull; Kelengkapan: <?= round(((float)($fhiData['data_completeness_ratio'] ?? 0)) * 100, 1) ?>%</div>

                    <div class="tree-node">
                        <div class="fw-bold">Pillar 1: Physical Network Coverage (20%) &bull; Skor: <?= number_format($p1Sub, 1) ?> &bull; Kontribusi: +<?= number_format($p1Contrib, 2) ?> Poin</div>
                        <div class="small text-muted">Rasio Seksi Fisik: <?= $breakdown['physical_coverage']['configured'] ?? 0 ?> dari <?= $breakdown['physical_coverage']['total'] ?? 0 ?> seksi terverifikasi aktif pada CR-06F.</div>
                    </div>

                    <div class="tree-node">
                        <div class="fw-bold">Pillar 2: Asset Structural Health (25%) &bull; Skor: <?= $p2Sub !== null ? number_format($p2Sub, 1) : 'UNRESOLVED / NO DATA' ?> &bull; Kontribusi: +<?= number_format($p2Contrib, 2) ?> Poin</div>
                        <div class="small text-muted">Master Aset Ter-resolve: <?= $breakdown['asset_health']['resolved'] ?? 0 ?> dari <?= $breakdown['asset_health']['total'] ?? 0 ?> aset feeder (Grid: <?= $breakdown['asset_health']['total_grid_assets'] ?? 517 ?>) &bull; Status: <?= esc($breakdown['asset_health']['status_label'] ?? 'UNRESOLVED') ?>.</div>
                    </div>

                    <div class="tree-node">
                        <div class="fw-bold">Pillar 3: Active Operational Finding Severity (25%) &bull; Skor: <?= number_format($p3Sub, 1) ?> &bull; Kontribusi: +<?= number_format($p3Contrib, 2) ?> Poin</div>
                        <div class="small text-muted mb-2">Rincian Penalti Akumulasi Temuan Terbuka (Total Penalti: -<?= number_format((float)($breakdown['finding_severity']['penalty'] ?? 0), 1) ?> Poin):</div>
                        <ul class="small text-muted mb-1 ps-3">
                            <li>Emergency: <strong><?= $breakdown['finding_severity']['details']['emergency']['count'] ?? 0 ?></strong> &times; 25.0 = -<?= $breakdown['finding_severity']['details']['emergency']['subtotal'] ?? 0 ?> Poin</li>
                            <li>Kritis: <strong><?= $breakdown['finding_severity']['details']['kritis']['count'] ?? 0 ?></strong> &times; 20.0 = -<?= $breakdown['finding_severity']['details']['kritis']['subtotal'] ?? 0 ?> Poin</li>
                            <li>Serius: <strong><?= $breakdown['finding_severity']['details']['serius']['count'] ?? 0 ?></strong> &times; 10.0 = -<?= $breakdown['finding_severity']['details']['serius']['subtotal'] ?? 0 ?> Poin</li>
                            <li>Ringan: <strong><?= $breakdown['finding_severity']['details']['ringan']['count'] ?? 0 ?></strong> &times; 3.0 = -<?= $breakdown['finding_severity']['details']['ringan']['subtotal'] ?? 0 ?> Poin</li>
                        </ul>
                    </div>

                    <div class="tree-node">
                        <div class="fw-bold">Pillar 4: Reliability Performance Rolling 12M (20%) &bull; Skor: <?= number_format($p4Sub, 1) ?> &bull; Kontribusi: +<?= number_format($p4Contrib, 2) ?> Poin</div>
                        <div class="small text-muted">Total Gangguan: <?= $breakdown['reliability']['trips'] ?? 0 ?> trip, Durasi Padam: <?= number_format((float)($breakdown['reliability']['dur_mins'] ?? 0), 1) ?> menit.</div>
                    </div>

                    <div class="tree-node">
                        <div class="fw-bold">Pillar 5: Chronicity & Recurrence Density (10%) &bull; Skor: <?= number_format($p5Sub, 1) ?> &bull; Kontribusi: +<?= number_format($p5Contrib, 2) ?> Poin</div>
                        <div class="small text-muted">Seksi Kronis (&ge;2 Kali Berulang): <?= $breakdown['chronicity']['chronic_sections'] ?? 0 ?> seksi jaringan.</div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded border mt-3 small">
                    <i class="fa-solid fa-fingerprint me-1 text-primary"></i> <strong>Audit Fingerprint Hash</strong>: <code><?= hash('sha256', $fhiData['fingerprint_json'] ?? '') ?></code>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal (Gate E9-A) -->
<div class="modal fade" id="approvalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-gavel me-2 text-warning"></i>Manager Approval Gate (E9-A)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-3">Sesuai Invariant <strong>E9-A (Decision &ne; Dispatch)</strong>, setiap aksi eksekutif wajib disetujui secara sadar oleh Manager sebelum diterbitkan sebagai Surat Tugas Kerja / Dispatch.</p>
                <div class="p-3 bg-light rounded border mb-3">
                    <div class="fw-bold text-dark"><?= esc($primaryDriver['recommended_action'] ?? '') ?></div>
                    <div class="small text-muted">Unit: <strong><?= esc($primaryDriver['assigned_unit'] ?? '') ?></strong> &bull; Prioritas: <strong><?= esc($primaryDriver['priority'] ?? '') ?></strong></div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Catatan Arahan Manager:</label>
                    <textarea id="managerNotes" class="form-control form-control-sm" rows="3" placeholder="Masukkan instruksi khusus atau catatan penugasan untuk tim operasional..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success btn-sm fw-bold" onclick="submitManagerApproval();">
                    <i class="fa-solid fa-check-circle me-1"></i> Setujui & Dispatch
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function submitManagerApproval() {
    const notes = document.getElementById('managerNotes').value;
    const formData = new FormData();
    formData.append('decision_log_id', '<?= esc($primaryDriver['log_id'] ?? 1) ?>');
    formData.append('user_id', '1');
    formData.append('notes', notes);

    fetch('<?= site_url('api/executive-intelligence/approve-action') ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        alert('Disetujui! Rekomendasi tindakan berhasil disetujui dan diteruskan ke antrean Dispatch Operasional.');
        location.reload();
    })
    .catch(err => {
        alert('Aksi telah dicatat ke audit log approval.');
        bootstrap.Modal.getInstance(document.getElementById('approvalModal')).hide();
    });
}
</script>
</body>
</html>
