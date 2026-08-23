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
            <h3 class="fw-bold text-success mb-1">
                <i class="fa-solid fa-clipboard-check me-2"></i>Enterprise Work Completion Assurance Center
            </h3>
            <small class="text-secondary">
                <i class="fa-solid fa-magnifying-glass me-1"></i>
                Work Execution Evidence Reconciliation, Completion Integrity Score & Quality Advisory
            </small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6">
                <i class="fa-solid fa-shield-check me-1"></i>WORK COMPLETION ASSURANCE VERIFIED
            </span>
        </div>
    </div>

    <!-- Governance Authority Notice -->
    <div class="alert alert-warning border border-warning mb-4">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        <strong>ADVISORY ONLY:</strong>
        Seluruh output sistem merupakan <strong>COMPLETION_ASSURANCE_ADVISORY</strong> semata.
        Penerimaan resmi pekerjaan (<em>Official Work Acceptance</em>) sepenuhnya berada pada otoritas
        <strong>HUMAN OPERATIONAL REVIEW</strong> — bukan sistem.
        <span class="badge bg-danger ms-2">AUTOMATIC_WORK_REJECTION = FORBIDDEN</span>
        <span class="badge bg-danger ms-2">AUTOMATIC_PAYMENT_CERTIFICATION = FORBIDDEN</span>
    </div>

    <!-- Work Completion Audit & Quality Advisory Cards -->
    <div class="row g-3 mb-4">

        <!-- Completion Integrity Score -->
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-success mb-3">
                    <i class="fa-solid fa-chart-line me-2"></i>Completion Evidence Integrity Audit
                </h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Completion Integrity Score & Assessment Class:</small>
                    <div class="fs-4 fw-bold text-success font-monospace">
                        <?= esc($workCompletionAudit['completion_integrity_score'] ?? 94.2) ?>%
                        <small class="fs-6 text-warning">(<?= esc($workCompletionAudit['completion_assessment_class'] ?? 'ADVISORY_ONLY') ?>)</small>
                    </div>
                    <small class="text-secondary mt-2 d-block">Work Completion Truth Class:</small>
                    <div class="font-monospace text-warning fw-bold small">
                        <?= esc($workCompletionAudit['work_completion_truth_class'] ?? 'WORK_COMPLETION_ASSESSMENT_ADVISORY_ONLY') ?>
                    </div>
                </div>
                <div class="row g-2 small">
                    <div class="col-6">
                        Automatic Work Rejection:
                        <span class="badge bg-danger font-monospace">
                            <?= esc($workCompletionAudit['automatic_work_rejection'] ?? 'FORBIDDEN') ?>
                        </span>
                    </div>
                    <div class="col-6">
                        Automatic Work Order Closure:
                        <span class="badge bg-danger font-monospace">
                            <?= esc($workCompletionAudit['automatic_work_order_closure'] ?? 'FORBIDDEN') ?>
                        </span>
                    </div>
                    <div class="col-6">
                        Asset Condition Mutation:
                        <span class="badge bg-danger font-monospace">
                            <?= esc($workCompletionAudit['automatic_asset_condition_mutation'] ?? 'FORBIDDEN') ?>
                        </span>
                    </div>
                    <div class="col-6">
                        Contractor Penalty:
                        <span class="badge bg-danger font-monospace">
                            <?= esc($workCompletionAudit['automatic_contractor_penalty'] ?? 'FORBIDDEN') ?>
                        </span>
                    </div>
                    <div class="col-12 mt-1">
                        Payment Certification:
                        <span class="badge bg-danger font-monospace">
                            <?= esc($workCompletionAudit['automatic_payment_certification'] ?? 'FORBIDDEN') ?>
                        </span>
                        &nbsp;Official Work Acceptance:
                        <span class="badge bg-warning text-dark font-monospace">
                            <?= esc($workCompletionAudit['official_work_acceptance'] ?? 'HUMAN_AUTHORITY_REQUIRED') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Work Quality Advisory Bundle -->
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3">
                    <i class="fa-solid fa-file-circle-check me-2"></i>Work Quality Advisory Bundle
                </h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Advisory Bundle ID:</small>
                    <div class="text-info font-monospace fw-bold small mb-2">
                        <?= esc($workQualityAdvisory['bundle_id'] ?? 'WORK-QUALITY-BDL-STJ-01') ?>
                    </div>
                    <small class="text-secondary">Quality Score & Advisory Status:</small>
                    <div class="fs-5 fw-bold text-success font-monospace">
                        <?= esc($workQualityAdvisory['completion_quality_score'] ?? 94.2) ?>%
                        <small class="fs-6 text-warning">
                            (<?= esc($workQualityAdvisory['advisory_status'] ?? 'COMPLETION_ASSURANCE_ADVISORY_PROPOSED') ?>)
                        </small>
                    </div>
                    <div class="mt-2">
                        <span class="badge bg-secondary me-1 font-monospace small">
                            Missing Evidence: <?= ($workQualityAdvisory['missing_evidence_detected'] ?? false) ? 'DETECTED' : 'NONE' ?>
                        </span>
                        <span class="badge bg-secondary me-1 font-monospace small">
                            Inconsistency: <?= ($workQualityAdvisory['inconsistency_detected'] ?? false) ? 'DETECTED' : 'NONE' ?>
                        </span>
                        <span class="badge bg-secondary font-monospace small">
                            Rework: <?= ($workQualityAdvisory['rework_recommended'] ?? false) ? 'RECOMMENDED' : 'NOT REQUIRED' ?>
                        </span>
                    </div>
                </div>
                <div class="small text-light">
                    Human Operational Review:
                    <span class="badge bg-warning text-dark font-monospace">
                        <?= esc($workQualityAdvisory['human_operational_review'] ?? 'REQUIRED') ?>
                    </span>
                    &nbsp;Quality Score Class:
                    <span class="badge bg-secondary font-monospace small">
                        NOT_LEGAL_VERDICT
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Operational Loop Closure Diagram -->
    <div class="card card-custom p-4 mb-4">
        <h6 class="text-secondary mb-3">
            <i class="fa-solid fa-diagram-project me-2"></i>Phase 7X — Governed Lifecycle Authority Cut-Line
        </h6>
        <div class="row text-center small">
            <div class="col">
                <div class="bg-dark rounded p-2 font-monospace text-success">WORK_EXECUTION_SNAPSHOT_CAPTURED</div>
            </div>
            <div class="col-auto d-flex align-items-center text-secondary">↓</div>
            <div class="col">
                <div class="bg-dark rounded p-2 font-monospace text-success">COMPLETION_EVIDENCE_RECONCILED</div>
            </div>
            <div class="col-auto d-flex align-items-center text-secondary">↓</div>
            <div class="col">
                <div class="bg-dark rounded p-2 font-monospace text-success">COMPLETION_ASSURANCE_ADVISORY_COMPOSED</div>
            </div>
            <div class="col-auto d-flex align-items-center text-danger fw-bold">⊣</div>
            <div class="col">
                <div class="bg-warning text-dark rounded p-2 font-monospace fw-bold">HUMAN_OPERATIONAL_REVIEW_REQUIRED</div>
            </div>
            <div class="col-auto d-flex align-items-center text-secondary">↓</div>
            <div class="col">
                <div class="bg-secondary rounded p-2 font-monospace">CONFIRMED / REJECTED / REWORK</div>
            </div>
        </div>
        <div class="mt-2 text-center small text-secondary">
            <i class="fa-solid fa-ban text-danger me-1"></i>
            SIDAK Authority ends at advisory composition — Human authority governs all acceptance decisions
        </div>
    </div>

</div>
</body>
</html>
