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
        .dim-available  { border-left: 3px solid #22c55e; }
        .dim-advisory   { border-left: 3px solid #f59e0b; }
        .dim-unavailable{ border-left: 3px solid #ef4444; }
    </style>
</head>
<body class="py-4">
<div class="container-fluid px-4">

    <!-- Header Banner -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
        <div>
            <h3 class="fw-bold text-info mb-1">
                <i class="fa-solid fa-gauge-high me-2"></i>Enterprise Operational Performance Scorecard Center
            </h3>
            <small class="text-secondary">
                <i class="fa-solid fa-layer-group me-1"></i>
                Capstone Phase 7Z — Federated Multi-Dimensional KPI Intelligence & Continuous Improvement Advisory
            </small>
        </div>
        <div>
            <span class="badge bg-info text-dark px-3 py-2 fs-6">
                <i class="fa-solid fa-shield-check me-1"></i>PERFORMANCE SCORECARD ADVISORY VERIFIED
            </span>
        </div>
    </div>

    <!-- Governance Authority Notice -->
    <div class="alert alert-warning border border-warning mb-4">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        <strong>ADVISORY ONLY — FEDERATED READ MODEL:</strong>
        Scorecard ini merupakan <strong>ADVISORY_ESTIMATE_ONLY</strong>.
        <span class="badge bg-danger ms-1">UNIFIED_SCORE ≠ OFFICIAL_ENTERPRISE_PERFORMANCE_TRUTH</span>
        <span class="badge bg-danger ms-1">MISSING_DIMENSION ≠ ZERO_PERFORMANCE</span>
        <span class="badge bg-danger ms-1">STALE_DIMENSION ≠ CURRENT_OPERATIONAL_TRUTH</span>
        <br class="mt-1">
        Penetapan KPI resmi sepenuhnya berada pada otoritas <strong>EXTERNAL MANAGEMENT AUTHORITY</strong>.
        <span class="badge bg-danger ms-1">AUTOMATIC_KPI_MANDATE = FORBIDDEN</span>
        <span class="badge bg-danger ms-1">AUTOMATIC_PERFORMANCE_PENALTY = FORBIDDEN</span>
    </div>

    <div class="row g-3 mb-4">

        <!-- Scorecard Header -->
        <div class="col-md-5">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3">
                    <i class="fa-solid fa-star-half-stroke me-2"></i>Scorecard Snapshot
                </h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Snapshot ID:</small>
                    <div class="font-monospace text-info fw-bold small mb-2">
                        <?= esc($performanceScorecard['snapshot_id'] ?? 'SCORECARD-SNP-STJ-01') ?>
                    </div>
                    <small class="text-secondary">Overall Assessment:</small>
                    <div class="fs-5 fw-bold font-monospace text-success">
                        <?= esc($performanceScorecard['overall_assessment'] ?? '-') ?>
                    </div>
                    <div class="mt-2 small">
                        <span class="badge bg-secondary me-1">Advisory Class: <?= esc($performanceScorecard['advisory_class'] ?? 'ADVISORY_ONLY') ?></span>
                        <span class="badge bg-secondary me-1">Dimensions: <?= esc($performanceScorecard['dimension_count_available'] ?? 0) ?></span>
                        <span class="badge bg-secondary me-1">Numeric: <?= esc($performanceScorecard['dimension_count_numeric'] ?? 0) ?></span>
                        <span class="badge bg-secondary">Non-Numeric: <?= esc($performanceScorecard['dimension_count_non_numeric'] ?? 0) ?></span>
                    </div>
                </div>
                <div class="small">
                    <div class="mb-1">Unified Score Class: <span class="badge bg-secondary font-monospace" style="font-size:0.65rem"><?= esc($performanceScorecard['unified_score_class'] ?? '-') ?></span></div>
                    <div class="mb-1">Missing Dim Class: <span class="badge bg-secondary font-monospace" style="font-size:0.65rem"><?= esc($performanceScorecard['missing_dimension_class'] ?? '-') ?></span></div>
                    <div class="mb-1">Stale Dim Class: <span class="badge bg-secondary font-monospace" style="font-size:0.65rem"><?= esc($performanceScorecard['stale_dimension_class'] ?? '-') ?></span></div>
                    <div class="mb-1">Weight Class: <span class="badge bg-secondary font-monospace" style="font-size:0.65rem"><?= esc($performanceScorecard['dimension_weight_class'] ?? '-') ?></span></div>
                    <div class="mb-1">Comparability: <span class="badge bg-warning text-dark font-monospace" style="font-size:0.65rem"><?= esc($performanceScorecard['cross_phase_comparability'] ?? '-') ?></span></div>
                    <div class="mb-1">KPI Authority: <span class="badge bg-warning text-dark font-monospace" style="font-size:0.65rem"><?= esc($performanceScorecard['official_kpi_target_authority'] ?? '-') ?></span></div>
                </div>
            </div>
        </div>

        <!-- Dimension Intelligence Cards -->
        <div class="col-md-7">
            <div class="card card-custom p-4">
                <h5 class="text-success mb-3">
                    <i class="fa-solid fa-chart-bar me-2"></i>Performance Dimensions (Federated Read-Model)
                </h5>
                <?php if (!empty($performanceScorecard['dimensions'])): ?>
                    <?php foreach ($performanceScorecard['dimensions'] as $dim): ?>
                        <?php
                            $dimClass = 'dim-available';
                            if (($dim['availability_status'] ?? '') === 'AVAILABLE_NON_NUMERIC') $dimClass = 'dim-advisory';
                            if (($dim['availability_status'] ?? '') === 'UNAVAILABLE') $dimClass = 'dim-unavailable';
                        ?>
                        <div class="bg-dark rounded p-2 mb-2 small <?= $dimClass ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="text-secondary font-monospace" style="font-size:0.7rem"><?= esc($dim['phase_source'] ?? '-') ?></span>
                                    <div class="fw-bold text-light"><?= esc($dim['dimension_name'] ?? '-') ?></div>
                                    <div class="font-monospace text-info">
                                        <?= $dim['signal_value'] !== null ? esc($dim['signal_value']) . '%' : esc($dim['signal_class'] ?? '-') ?>
                                    </div>
                                </div>
                                <div class="text-end" style="font-size:0.65rem">
                                    <span class="badge bg-secondary me-1"><?= esc($dim['confidence'] ?? '-') ?></span>
                                    <span class="badge <?= ($dim['freshness'] ?? '') === 'CURRENT' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= esc($dim['freshness'] ?? '-') ?></span>
                                    <div class="text-secondary mt-1"><?= esc($dim['provenance'] ?? '-') ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Continuous Improvement Advisory -->
    <div class="card card-custom p-4 mb-4">
        <h5 class="text-warning mb-3">
            <i class="fa-solid fa-arrow-trend-up me-2"></i>Continuous Improvement Advisory Bundle
            <small class="fs-6 text-secondary ms-2">Bundle: <?= esc($continuousImprovementAdvisory['bundle_id'] ?? '-') ?></small>
        </h5>
        <?php if (!empty($continuousImprovementAdvisory['improvement_priorities'])): ?>
            <?php foreach ($continuousImprovementAdvisory['improvement_priorities'] as $imp): ?>
                <div class="bg-dark rounded p-3 mb-2 d-flex justify-content-between align-items-start">
                    <div>
                        <span class="badge bg-warning text-dark me-2">#<?= esc($imp['priority_rank']) ?></span>
                        <strong class="text-light"><?= esc($imp['dimension']) ?></strong>
                        <span class="badge bg-secondary ms-2 font-monospace" style="font-size:0.65rem"><?= esc($imp['phase_source']) ?></span>
                        <div class="text-secondary small mt-1">Current Signal: <span class="text-info"><?= esc($imp['current_signal']) ?></span></div>
                        <div class="text-light small">Opportunity: <span class="text-warning"><?= esc($imp['improvement_opportunity']) ?></span></div>
                        <div class="text-light small">Recommended Action: <span class="text-success"><?= esc($imp['recommended_action']) ?></span></div>
                    </div>
                    <div>
                        <span class="badge bg-secondary font-monospace small"><?= esc($imp['advisory_confidence']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="mt-2 small text-secondary">
            Human Management Review: <span class="badge bg-warning text-dark font-monospace"><?= esc($continuousImprovementAdvisory['human_management_review'] ?? 'REQUIRED') ?></span>
            &nbsp;|&nbsp; Automatic Performance Penalty: <span class="badge bg-danger font-monospace">FORBIDDEN</span>
            &nbsp;|&nbsp; Automatic Budget Reallocation: <span class="badge bg-danger font-monospace">FORBIDDEN</span>
        </div>
    </div>

    <!-- Governed Lifecycle Authority Cut-Line -->
    <div class="card card-custom p-4">
        <h6 class="text-secondary mb-3">
            <i class="fa-solid fa-diagram-project me-2"></i>Phase 7Z Capstone — Governed Lifecycle Authority Cut-Line
        </h6>
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-center small font-monospace">
            <div class="bg-dark rounded p-2 text-info text-center" style="min-width:110px">PHASE 7A–7Y<br>SNAPSHOTS</div>
            <div class="text-secondary">↓</div>
            <div class="bg-dark rounded p-2 text-info text-center" style="min-width:120px">PROVENANCE &<br>FRESHNESS</div>
            <div class="text-secondary">↓</div>
            <div class="bg-dark rounded p-2 text-info text-center" style="min-width:130px">DIMENSION<br>AGGREGATION</div>
            <div class="text-secondary">↓</div>
            <div class="bg-dark rounded p-2 text-info text-center" style="min-width:130px">SCORECARD &<br>IMPROVEMENT</div>
            <div class="text-danger fw-bold fs-5">⊣</div>
            <div class="bg-warning text-dark rounded p-2 fw-bold text-center" style="min-width:150px">HUMAN MANAGEMENT<br>REVIEW REQUIRED</div>
            <div class="text-secondary">↓</div>
            <div class="bg-secondary rounded p-2 text-center" style="min-width:130px">CONFIRMED /<br>DEFERRED</div>
        </div>
        <div class="mt-2 text-center small text-secondary">
            <i class="fa-solid fa-ban text-danger me-1"></i>
            SIDAK Authority ends at advisory scorecard — Official KPI, penalty, budget & restructuring remain with External Management Authority
        </div>
    </div>

</div>
</body>
</html>
