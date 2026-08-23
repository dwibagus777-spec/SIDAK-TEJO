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
            <h3 class="fw-bold text-primary mb-1">
                <i class="fa-solid fa-calendar-check me-2"></i>Enterprise Inspection Planning & Scheduling Intelligence Center
            </h3>
            <small class="text-secondary">
                <i class="fa-solid fa-magnifying-glass me-1"></i>
                Risk-Based Inspection Interval Advisory, Cycle Intelligence & Priority Matrix
            </small>
        </div>
        <div>
            <span class="badge bg-primary px-3 py-2 fs-6">
                <i class="fa-solid fa-shield-check me-1"></i>INSPECTION SCHEDULE ADVISORY VERIFIED
            </span>
        </div>
    </div>

    <!-- Governance Authority Notice -->
    <div class="alert alert-warning border border-warning mb-4">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        <strong>ADVISORY ONLY:</strong>
        Seluruh output merupakan <strong>INSPECTION_SCHEDULE_ADVISORY</strong> semata.
        Penjadwalan inspeksi resmi (<em>Official Inspection Scheduling</em>) sepenuhnya berada pada otoritas
        <strong>HUMAN SUPERVISOR</strong> — bukan sistem.
        <span class="badge bg-danger ms-2">AUTOMATIC_INSPECTION_ORDER = FORBIDDEN</span>
        <span class="badge bg-danger ms-2">AUTOMATIC_INSPECTOR_ASSIGNMENT = FORBIDDEN</span>
        <span class="badge bg-danger ms-2">REGULATORY_INTERVAL_OVERRIDE = FORBIDDEN</span>
    </div>

    <!-- Schedule Audit & Priority Advisory Cards -->
    <div class="row g-3 mb-4">

        <!-- Inspection Schedule Intelligence -->
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-primary mb-3">
                    <i class="fa-solid fa-rotate me-2"></i>Risk-Based Inspection Interval Advisory
                </h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Recommended Inspection Window & Type:</small>
                    <div class="fs-5 fw-bold text-primary font-monospace">
                        <?= esc($inspectionScheduleAudit['recommended_inspection_window'] ?? 'WITHIN_30_DAYS') ?>
                    </div>
                    <div class="text-info font-monospace small">
                        Type: <?= esc($inspectionScheduleAudit['recommended_inspection_type'] ?? 'DETAILED_VISUAL_INSPECTION') ?>
                    </div>
                    <div class="mt-2 small">
                        <span class="text-secondary">Health Signal: </span>
                        <span class="text-warning fw-bold"><?= esc($inspectionScheduleAudit['health_index_signal'] ?? 74.0) ?></span>
                        &nbsp;|&nbsp;
                        <span class="text-secondary">Risk Priority: </span>
                        <span class="text-warning fw-bold"><?= esc($inspectionScheduleAudit['risk_priority_signal'] ?? 'P3') ?></span>
                        &nbsp;|&nbsp;
                        <span class="text-secondary">Last Gap: </span>
                        <span class="text-warning fw-bold"><?= esc($inspectionScheduleAudit['last_inspection_gap_days'] ?? 62) ?> days</span>
                    </div>
                    <div class="mt-1 small">
                        <span class="text-secondary">Scheduling Confidence: </span>
                        <span class="badge bg-info font-monospace"><?= esc($inspectionScheduleAudit['scheduling_confidence'] ?? 'ADVISORY') ?></span>
                        &nbsp;|&nbsp;
                        <span class="text-secondary">Intelligence Class: </span>
                        <span class="badge bg-secondary font-monospace small"><?= esc($inspectionScheduleAudit['inspection_scheduling_intelligence_class'] ?? 'ADVISORY_ONLY') ?></span>
                    </div>
                </div>
                <div class="row g-1 small">
                    <div class="col-12">
                        Interval Class: <span class="badge bg-secondary font-monospace small"><?= esc($inspectionScheduleAudit['risk_based_inspection_interval'] ?? 'RECOMMENDED_INTERVAL_NOT_MANDATORY_INTERVAL') ?></span>
                    </div>
                    <div class="col-6 mt-1">
                        Feeder Outage Planning: <span class="badge bg-danger font-monospace small">FORBIDDEN</span>
                    </div>
                    <div class="col-6 mt-1">
                        Regulatory Override: <span class="badge bg-danger font-monospace small">FORBIDDEN</span>
                    </div>
                    <div class="col-12 mt-1">
                        Human Supervisor Review: <span class="badge bg-warning text-dark font-monospace"><?= esc($inspectionScheduleAudit['human_supervisor_review_required'] ?? 'TRUE') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inspection Priority Advisory Bundle -->
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-success mb-3">
                    <i class="fa-solid fa-list-ol me-2"></i>Inspection Priority Advisory Bundle
                </h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Advisory Bundle ID:</small>
                    <div class="text-success font-monospace fw-bold small mb-2">
                        <?= esc($inspectionPriorityAdvisory['bundle_id'] ?? 'INSPECTION-BDL-STJ-01') ?>
                    </div>
                    <small class="text-secondary">Priority Rank & Recommended Type:</small>
                    <div class="fs-5 fw-bold font-monospace">
                        <span class="text-danger"><?= esc($inspectionPriorityAdvisory['priority_rank'] ?? 'HIGH') ?></span>
                        <small class="fs-6 text-info ms-2"><?= esc($inspectionPriorityAdvisory['recommended_inspection_type'] ?? 'DETAILED_VISUAL_AND_THERMOVISION') ?></small>
                    </div>
                    <div class="mt-2 small">
                        <span class="badge bg-secondary font-monospace small me-1">
                            Priority Class: <?= esc($inspectionPriorityAdvisory['risk_priority_rank_class'] ?? 'NOT_OFFICIAL_OPERATIONAL_PRIORITY') ?>
                        </span>
                    </div>
                </div>
                <div class="small text-light">
                    Predictive Risk Class: <span class="badge bg-secondary font-monospace small"><?= esc($inspectionPriorityAdvisory['predictive_risk_class'] ?? 'NOT_CERTAIN_DUE_DATE') ?></span>
                    <br class="mt-1">
                    Advisory Class: <span class="badge bg-secondary font-monospace small"><?= esc($inspectionPriorityAdvisory['inspection_advisory_class'] ?? 'NOT_OFFICIAL_WORK_ORDER') ?></span>
                    <br class="mt-1">
                    Human Supervisor Review: <span class="badge bg-warning text-dark font-monospace"><?= esc($inspectionPriorityAdvisory['human_supervisor_review'] ?? 'REQUIRED') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Governed Lifecycle Authority Cut-Line -->
    <div class="card card-custom p-4 mb-4">
        <h6 class="text-secondary mb-3">
            <i class="fa-solid fa-diagram-project me-2"></i>Phase 7Y — Governed Lifecycle Authority Cut-Line
        </h6>
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-center small font-monospace">
            <div class="bg-dark rounded p-2 text-primary text-center" style="min-width:120px">RISK SNAPSHOT<br>CAPTURED</div>
            <div class="text-secondary">↓</div>
            <div class="bg-dark rounded p-2 text-primary text-center" style="min-width:120px">INTERVAL<br>RECOMMENDED</div>
            <div class="text-secondary">↓</div>
            <div class="bg-dark rounded p-2 text-primary text-center" style="min-width:120px">PRIORITY MATRIX<br>COMPOSED</div>
            <div class="text-secondary">↓</div>
            <div class="bg-dark rounded p-2 text-primary text-center" style="min-width:140px">INSPECTION ADVISORY<br>COMPOSED</div>
            <div class="text-danger fw-bold fs-5">⊣</div>
            <div class="bg-warning text-dark rounded p-2 fw-bold text-center" style="min-width:150px">HUMAN SUPERVISOR<br>REVIEW REQUIRED</div>
            <div class="text-secondary">↓</div>
            <div class="bg-secondary rounded p-2 text-center" style="min-width:140px">CONFIRMED /<br>DEFERRED / REJECTED</div>
        </div>
        <div class="mt-2 text-center small text-secondary">
            <i class="fa-solid fa-ban text-danger me-1"></i>
            SIDAK Authority ends at advisory — Official scheduling, inspector assignment & resource allocation remain with Human Supervisor
        </div>
    </div>

</div>
</body>
</html>
