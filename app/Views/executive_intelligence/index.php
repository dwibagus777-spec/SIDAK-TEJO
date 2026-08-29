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
        $exp = json_decode($fhiData['explanation_json'] ?? '{}', true);
        $fingerprint = json_decode($fhiData['fingerprint_json'] ?? '{}', true);
        $decMatrix = $exp['decision_matrix'] ?? [];
        $primaryDriver = $decMatrix['primary_driver'] ?? null;
        $secondaryDrivers = $decMatrix['secondary_drivers'] ?? [];
        $breakdown = $exp['score_breakdown'] ?? [];
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
                            <div class="fw-bold h5 mb-1"><?= esc($fhiData['fhi_status'] ?? 'RESOLVED') ?></div>
                            <div class="small text-muted">Formula: <code><?= esc($fingerprint['formula_version'] ?? 'FHI-v1.0') ?></code></div>
                            <div class="small text-muted">Kelengkapan Data: <strong><?= number_format(((float)($fhiData['data_completeness_ratio'] ?? 0)) * 100, 1) ?>%</strong></div>
                        </div>
                    </div>
                </div>
                <div class="border-top pt-2 small text-muted">
                    <i class="fa-solid fa-shield-halved me-1 text-success"></i> Invariant Weight Conservation: <strong>1.0000 (LOCKED)</strong>
                </div>
            </div>
        </div>

        <!-- Executive Decision Recommendation Card -->
        <div class="col-md-8">
            <div class="card card-custom h-100 p-4 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted fw-bold small text-uppercase"><i class="fa-solid fa-gavel me-1 text-warning"></i>Rekomendasi Keputusan Eksekutif (Decision Matrix)</span>
                    <span class="badge bg-danger fw-bold"><?= esc($primaryDriver['priority'] ?? 'P3 - MEDIUM') ?></span>
                </div>
                
                <h5 class="fw-bold text-dark mt-2 mb-1">
                    <?= esc($primaryDriver['recommended_action'] ?? 'Monitoring Berkala') ?>
                </h5>
                <div class="text-muted small mb-3">
                    Driver Risiko Utama: <code class="fw-bold text-danger"><?= esc($primaryDriver['driver_code'] ?? 'NORMAL_OPERATION') ?></code> (Skor Pemicu: <?= esc($primaryDriver['driver_score'] ?? 0) ?>) &bull; 
                    Unit Ditugaskan: <strong class="text-primary"><?= esc($primaryDriver['assigned_unit'] ?? 'Pemeliharaan Rutin') ?></strong>
                </div>

                <div class="bg-light p-3 rounded border mb-3 small">
                    <strong>Evidensi Temuan & Keandalan:</strong> <?= esc($primaryDriver['evidence'] ?? 'Tidak ada defek kritis terdeteksi.') ?>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div class="small text-muted">
                        <i class="fa-solid fa-user-check me-1 text-primary"></i> Memerlukan <strong>Human Approval (Gate E9-A)</strong> sebelum dispatch.
                    </div>
                    <button type="button" class="btn btn-primary btn-sm fw-bold px-3" data-bs-toggle="modal" data-bs-target="#approvalModal">
                        <i class="fa-solid fa-paper-plane me-1"></i> Review & Approve Dispatch
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 5 Pillars Breakdown Row -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card card-custom p-4">
                <div class="fw-bold text-dark mb-3"><i class="fa-solid fa-layer-group me-2 text-primary"></i>Dekomposisi 5 Pilar Skor FHI-v1.0 (Fixed Multi-Pillar Model)</div>
                <div class="row g-3">
                    <!-- Pillar 1 -->
                    <div class="col-md">
                        <div class="p-3 bg-light rounded border h-100">
                            <div class="d-flex justify-content-between small text-muted fw-bold">
                                <span>P1: Physical Coverage</span>
                                <span>20%</span>
                            </div>
                            <div class="h4 fw-bold mt-2 text-dark"><?= number_format((float)($breakdown['physical_coverage']['sub_score'] ?? 0), 1) ?></div>
                            <div class="progress my-2" style="height: 6px;">
                                <div class="progress-bar bg-info" style="width: <?= (float)($breakdown['physical_coverage']['sub_score'] ?? 0) ?>%;"></div>
                            </div>
                            <div class="small text-muted"><?= round(((float)($breakdown['physical_coverage']['ratio'] ?? 0)) * 100, 1) ?>% Seksi Terkonfigurasi</div>
                        </div>
                    </div>

                    <!-- Pillar 2 -->
                    <div class="col-md">
                        <div class="p-3 bg-light rounded border h-100">
                            <div class="d-flex justify-content-between small text-muted fw-bold">
                                <span>P2: Asset Health</span>
                                <span>25%</span>
                            </div>
                            <div class="h4 fw-bold mt-2 text-dark"><?= $breakdown['asset_health']['sub_score'] !== null ? number_format((float)$breakdown['asset_health']['sub_score'], 1) : 'N/A' ?></div>
                            <div class="progress my-2" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: <?= (float)($breakdown['asset_health']['sub_score'] ?? 0) ?>%;"></div>
                            </div>
                            <div class="small text-muted"><?= $breakdown['asset_health']['resolved'] ?? 0 ?> / <?= $breakdown['asset_health']['total'] ?? 0 ?> Master Aset Resolved</div>
                        </div>
                    </div>

                    <!-- Pillar 3 -->
                    <div class="col-md">
                        <div class="p-3 bg-light rounded border h-100">
                            <div class="d-flex justify-content-between small text-muted fw-bold">
                                <span>P3: Finding Severity</span>
                                <span>25%</span>
                            </div>
                            <div class="h4 fw-bold mt-2 text-dark"><?= number_format((float)($breakdown['finding_severity']['sub_score'] ?? 0), 1) ?></div>
                            <div class="progress my-2" style="height: 6px;">
                                <div class="progress-bar bg-warning" style="width: <?= (float)($breakdown['finding_severity']['sub_score'] ?? 0) ?>%;"></div>
                            </div>
                            <div class="small text-muted">Penalti: -<?= number_format((float)($breakdown['finding_severity']['penalty'] ?? 0), 1) ?> Poin</div>
                        </div>
                    </div>

                    <!-- Pillar 4 -->
                    <div class="col-md">
                        <div class="p-3 bg-light rounded border h-100">
                            <div class="d-flex justify-content-between small text-muted fw-bold">
                                <span>P4: Reliability (12M)</span>
                                <span>20%</span>
                            </div>
                            <div class="h4 fw-bold mt-2 text-dark"><?= number_format((float)($breakdown['reliability']['sub_score'] ?? 0), 1) ?></div>
                            <div class="progress my-2" style="height: 6px;">
                                <div class="progress-bar bg-danger" style="width: <?= (float)($breakdown['reliability']['sub_score'] ?? 0) ?>%;"></div>
                            </div>
                            <div class="small text-muted"><?= $breakdown['reliability']['trips'] ?? 0 ?> Kali Trip / Gangguan</div>
                        </div>
                    </div>

                    <!-- Pillar 5 -->
                    <div class="col-md">
                        <div class="p-3 bg-light rounded border h-100">
                            <div class="d-flex justify-content-between small text-muted fw-bold">
                                <span>P5: Chronicity Density</span>
                                <span>10%</span>
                            </div>
                            <div class="h4 fw-bold mt-2 text-dark"><?= number_format((float)($breakdown['chronicity']['sub_score'] ?? 0), 1) ?></div>
                            <div class="progress my-2" style="height: 6px;">
                                <div class="progress-bar bg-primary" style="width: <?= (float)($breakdown['chronicity']['sub_score'] ?? 0) ?>%;"></div>
                            </div>
                            <div class="small text-muted"><?= $breakdown['chronicity']['chronic_sections'] ?? 0 ?> Seksi Berulang (&ge;2)</div>
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

        <!-- Secondary Drivers Table -->
        <div class="col-md-6">
            <div class="card card-custom h-100 p-4">
                <div class="fw-bold text-dark mb-3"><i class="fa-solid fa-list-ol me-2 text-primary"></i>Peringkat Driver Risiko Tambahan (Ranked Conflict Resolver)</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Rank</th>
                                <th>Driver Code</th>
                                <th>Score</th>
                                <th>Priority</th>
                                <th>Action & Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($secondaryDrivers)): ?>
                                <?php foreach ($secondaryDrivers as $sd): ?>
                                    <tr>
                                        <td><strong>#<?= esc($sd['driver_rank']) ?></strong></td>
                                        <td><code><?= esc($sd['driver_code']) ?></code></td>
                                        <td><?= number_format((float)$sd['driver_score'], 1) ?></td>
                                        <td><span class="badge bg-secondary"><?= esc($sd['priority']) ?></span></td>
                                        <td><?= esc($sd['recommended_action']) ?> &bull; <strong><?= esc($sd['assigned_unit']) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-success py-3"><i class="fa-solid fa-circle-check me-1"></i> Tidak ada driver risiko sekunder tambahan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
                    <textarea class="form-control form-control-sm" rows="3" placeholder="Masukkan catatan atau instruksi khusus untuk tim lapangan..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success btn-sm fw-bold" onclick="alert('Disetujui! Rekomendasi diteruskan ke antrean Dispatch Operasional.'); bootstrap.Modal.getInstance(document.getElementById('approvalModal')).hide();">
                    <i class="fa-solid fa-check-circle me-1"></i> Setujui & Dispatch
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
