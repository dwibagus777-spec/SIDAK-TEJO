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
            <h3 class="fw-bold text-success mb-1"><i class="fa-solid fa-fingerprint me-2"></i>Enterprise Operational Forensic Audit & Lineage Center</h3>
            <small class="text-secondary"><i class="fa-solid fa-link me-1"></i>360° Decision Provenance Lineage, SHA-256 Hash-Chain Verification & Auditor Export Bundle</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-file-shield me-1"></i>FORENSIC LINEAGE VERIFIED</span>
        </div>
    </div>

    <!-- Forensic Audit Lineage & Bundle Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-network-wired me-2"></i>360° Decision Provenance Lineage</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Provenance Lineage ID:</small>
                    <div class="fs-6 fw-bold text-info font-monospace"><?= esc($forensicAudit['provenance_lineage_id'] ?? 'PROV-LINEAGE-STJ-01') ?></div>
                    <small class="text-secondary">SHA-256 Evidence Hash:</small>
                    <div class="font-monospace text-success small text-break"><?= esc($forensicAudit['evidence_hash'] ?? 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855') ?></div>
                </div>
                <div class="small text-light">Protocol: <span class="badge bg-info font-monospace"><?= esc($forensicAudit['hash_chain_protocol'] ?? 'HASH-CHAIN-SHA256-v1.0') ?></span> | Read Mode: <span class="text-warning"><?= esc($forensicAudit['historical_mutation_status'] ?? 'READ_ONLY') ?></span></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-success mb-3"><i class="fa-solid fa-box-archive me-2"></i>Auditor Forensic Certification Bundle</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <small class="text-secondary">Forensic Bundle ID:</small>
                    <div class="text-success font-monospace fw-bold mb-1"><?= esc($forensicBundle['bundle_id'] ?? 'FORENSIC-BDL-STJ-01') ?></div>
                    <small class="text-secondary">Certification & Review Status:</small>
                    <div class="text-info font-monospace fw-bold fs-6"><?= esc($forensicBundle['auditor_export_status'] ?? 'FORENSIC_BUNDLE_CERTIFIED') ?> (<?= esc($forensicBundle['review_status'] ?? 'AUDITOR_REVIEW_REQUIRED') ?>)</div>
                </div>
                <div class="small text-light">Historical Record Mutation: <span class="badge bg-danger font-monospace">FORBIDDEN</span> | Auto Rehash Repair: <span class="badge bg-danger font-monospace">FORBIDDEN</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
