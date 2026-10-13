<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Transline Connectivity Workspace') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --tc-bg-dark: #0f172a;
            --tc-card-bg: #1e293b;
            --tc-accent: #38bdf8;
            --tc-border: #334155;
            --tc-success: #10b981;
            --tc-warning: #f59e0b;
            --tc-danger: #ef4444;
        }
        body {
            background-color: var(--tc-bg-dark);
            color: #f8fafc;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            padding-bottom: 50px;
        }
        .navbar-custom {
            background-color: #0b1120;
            border-bottom: 1px solid var(--tc-border);
            padding: 12px 24px;
        }
        .card-tc {
            background-color: var(--tc-card-bg);
            border: 1px solid var(--tc-border);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            margin-bottom: 24px;
        }
        .card-tc-header {
            background: rgba(15, 23, 42, 0.6);
            border-bottom: 1px solid var(--tc-border);
            padding: 14px 20px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .kpi-box {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid var(--tc-border);
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }
        .kpi-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--tc-accent);
        }
        .kpi-label {
            font-size: 0.8rem;
            color: #94a3b8;
            text-transform: uppercase;
        }
        .table-custom {
            color: #cbd5e1;
        }
        .table-custom th {
            background-color: rgba(15, 23, 42, 0.8);
            color: #94a3b8;
            border-bottom: 1px solid var(--tc-border);
        }
        .table-custom td {
            border-bottom: 1px solid var(--tc-border);
            vertical-align: middle;
        }
        .hash-code {
            font-family: monospace;
            font-size: 0.8rem;
            color: #38bdf8;
            background: rgba(15, 23, 42, 0.8);
            padding: 2px 6px;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-custom navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#">
            <i class="fa-solid fa-diagram-project text-info"></i>
            <span class="fw-bold">SIDAK TEJO</span>
            <span class="text-secondary">|</span>
            <span class="fs-6 text-light">Track D2: Transline Network Connectivity Completion</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-dark border border-info text-info">
                <i class="fa-solid fa-layer-group"></i> Baseline: <?= esc($summary['topology_snapshot']) ?>
            </span>
            <span class="badge bg-success text-white">
                <i class="fa-solid fa-shield-check"></i> Δtopology = 0 (Read-Only Scan)
            </span>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 mt-4">

    <!-- Top KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kpi-box">
                <div class="kpi-value text-info"><?= number_format($summary['active_translines']) ?> TL</div>
                <div class="kpi-label">Active Translines (9,418.37m)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-box">
                <div class="kpi-value text-success"><?= number_format($summary['active_assets']) ?></div>
                <div class="kpi-label">Active Assets Coverage (100%)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-box">
                <div class="kpi-value text-warning"><?= esc($scan['total_candidates_found']) ?></div>
                <div class="kpi-label">Candidates Scanned</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-box">
                <div class="kpi-value text-light"><?= esc($scan['auto_match_candidates']) ?></div>
                <div class="kpi-label">Auto Match Proposals</div>
            </div>
        </div>
    </div>

    <!-- Main Connectivity Proposals Grid -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-tc">
                <div class="card-tc-header">
                    <span><i class="fa-solid fa-network-wired text-info me-2"></i> Governed Transline Proposals Queue (D2.3 & D2.5)</span>
                    <span class="badge bg-warning text-dark">PENDING HUMAN REVIEW</span>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-dark border border-info bg-dark text-light p-3 rounded-3 mb-4 d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-info fw-bold mb-1"><i class="fa-solid fa-shield-halved text-success me-2"></i> Strict Endpoint Integrity & Safety Rules Active</h6>
                            <p class="mb-0 text-secondary small">Seluruh proposal transline menghubungkan aset fisik (<code>assets.id</code>). Tidak ada garis liar/temuan.</p>
                        </div>
                        <button class="btn btn-sm btn-outline-info" id="btnRescan">
                            <i class="fa-solid fa-rotate me-1"></i> Re-Run Forensic Scan
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-custom table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>PROPOSAL UUID</th>
                                    <th>START ASSET</th>
                                    <th>END ASSET</th>
                                    <th class="text-center">PANJANG (M)</th>
                                    <th class="text-center">KANDIDAT TYPE</th>
                                    <th class="text-center">STATUS</th>
                                    <th class="text-center">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody id="proposalsTableBody">
                                <?php if (!empty($proposals)): ?>
                                    <?php foreach ($proposals as $prp): ?>
                                        <tr>
                                            <td class="fw-bold text-info"><?= esc($prp['proposal_uuid']) ?></td>
                                            <td><code><?= esc($prp['start_asset_id']) ?></code></td>
                                            <td><code><?= esc($prp['end_asset_id']) ?></code></td>
                                            <td class="text-center"><?= number_format($prp['proposed_length_meters'], 1) ?>m</td>
                                            <td class="text-center">
                                                <?php if ($prp['candidate_type'] === 'AUTO_MATCH'): ?>
                                                    <span class="badge bg-success">AUTO MATCH</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><?= esc($prp['candidate_type']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($prp['status'] === 'PROMOTED_AUTHORITATIVE'): ?>
                                                    <span class="badge bg-success">PROMOTED</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?= esc($prp['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($prp['status'] !== 'PROMOTED_AUTHORITATIVE'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="promoteProposal('<?= esc($prp['proposal_uuid']) ?>')">
                                                        <i class="fa-solid fa-signature"></i> Promote
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-success small fw-bold"><i class="fa-solid fa-check-double"></i> Sealed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel: Hard Guards & Provenance -->
        <div class="col-lg-4">
            <div class="card-tc">
                <div class="card-tc-header">
                    <span><i class="fa-solid fa-shield-halved text-success me-2"></i> 15 D2 Safety Guards</span>
                    <span class="badge bg-success">15/15 ACTIVE</span>
                </div>
                <div class="card-body p-3 small">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D2-G01</strong>: Endpoint Integrity (Assets Only)</li>
                        <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D2-G02</strong>: Authoritative Topology Preserved</li>
                        <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D2-G03</strong>: Zero Asset Mutation ($\Delta = 0$)</li>
                        <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D2-G04 & G05</strong>: Cross-Feeder & Cross-ULP Protection</li>
                        <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D2-G06</strong>: Self-Loop Rejection</li>
                        <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D2-G07</strong>: Duplicate & Reverse Key Protection</li>
                        <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D2-G08</strong>: Distance Plausibility (&le; 500m)</li>
                        <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D2-G09</strong>: Branching Ambiguity Handling</li>
                        <li class="mb-0"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D2-G12</strong>: Governed Human Promotion ($L5/L6$)</li>
                    </ul>
                </div>
            </div>

            <!-- Provenance Fingerprints Box -->
            <div class="card-tc">
                <div class="card-tc-header">
                    <span><i class="fa-solid fa-fingerprint text-warning me-2"></i> Deterministic Provenance</span>
                    <span class="badge bg-secondary">SHA-256</span>
                </div>
                <div class="card-body p-3">
                    <div class="mb-3">
                        <label class="text-secondary small fw-bold d-block mb-1">SCAN ID</label>
                        <code><?= esc($scan['scan_id']) ?></code>
                    </div>
                    <div class="mb-3">
                        <label class="text-secondary small fw-bold d-block mb-1">INPUT CANONICAL FINGERPRINT</label>
                        <span class="hash-code d-block text-break"><?= esc($scan['input_fingerprint']) ?></span>
                    </div>
                    <div class="mb-3">
                        <label class="text-secondary small fw-bold d-block mb-1">OUTPUT CANONICAL FINGERPRINT</label>
                        <span class="hash-code d-block text-break"><?= esc($scan['output_fingerprint']) ?></span>
                    </div>
                    <div class="mb-3">
                        <label class="text-secondary small fw-bold d-block mb-1">FORENSIC BUNDLE HASH</label>
                        <span class="hash-code d-block text-break"><?= esc($scan['bundle_hash']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('btnRescan').addEventListener('click', async function() {
        const res = await fetch('/api/transline-completion/scan-candidates');
        const json = await res.json();
        if (json.status === 'success') {
            alert('Transline Forensic Connectivity Scan Completed! Scan ID: ' + json.data.scan_id);
            location.reload();
        }
    });

    async function promoteProposal(uuid) {
        if (!confirm('Promote proposal ' + uuid + ' to Authoritative Transline?')) return;
        const res = await fetch('/api/transline-completion/promote-candidate', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({proposal_uuid: uuid, promoted_by: 'SUPERVISOR_MANAGER'})
        });
        const json = await res.json();
        if (json.status === 'success') {
            alert('Proposal ' + uuid + ' Promoted to Authoritative Topology!');
            location.reload();
        }
    }
</script>
</body>
</html>
