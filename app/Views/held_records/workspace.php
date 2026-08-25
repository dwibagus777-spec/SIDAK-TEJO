<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Held Records Resolution Workspace | SIDAK TEJO') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .workspace-card { border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .badge-high-conf { background-color: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; }
        .badge-ambiguous { background-color: #fffaf0; color: #dd6b20; border: 1px solid #fbd38d; }
        .badge-unresolved { background-color: #fff5f5; color: #e53e3e; border: 1px solid #feb2b2; }
        .badge-duplicate { background-color: #faf5ff; color: #805ad5; border: 1px solid #e9d8fd; }
        .table th { background-color: #f7fafc; font-size: 11px; text-transform: uppercase; color: #4a5568; }
        .table td { vertical-align: middle; font-size: 13px; }
        .governance-banner { background: #ebf8ff; border-left: 4px solid #3182ce; padding: 12px 16px; border-radius: 4px; }
        .dryrun-panel { background: #1a202c; color: #e2e8f0; border-radius: 8px; padding: 20px; }
        .token-box { background: #2d3748; padding: 8px 12px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #68d391; word-break: break-all; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-layer-group text-primary me-2"></i>Held Records Resolution Workspace</h4>
            <div class="text-muted small">CR-02 Phase 2: In-Memory Dry-Run & Governed Human Decision Staging (29 Records)</div>
        </div>
        <div>
            <a href="<?= base_url('executive-intelligence') ?>" class="btn btn-outline-secondary btn-sm me-2"><i class="fa-solid fa-arrow-left me-1"></i> Executive BI</a>
            <button onclick="runDryRun()" class="btn btn-primary btn-sm"><i class="fa-solid fa-play me-1"></i> Simulate Dry-Run Plan</button>
        </div>
    </div>

    <!-- Governance Banner -->
    <div class="governance-banner mb-4">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-shield-halved fa-2x text-primary me-3"></i>
            <div>
                <div class="fw-bold text-primary">🛡️ GOVERNANCE INVARIANT GUARD ACTIVE (PHASE 2 DRY-RUN ONLY)</div>
                <div class="small text-secondary">
                    Baseline gangguan aktif: <strong>832 rekaman</strong> (Terkunci) | Master Penyulang: <strong>134 unit</strong> (Immutable) | Status Database: <strong>ZERO WRITES</strong>.
                    Setiap resolusi pada workspace ini bersifat simulasi staging dan menghasilkan <em>Confirmation Token</em> kriptografis tanpa mengubah database operasional.
                </div>
            </div>
        </div>
    </div>

    <!-- Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="workspace-card p-3 text-center border-primary">
                <div class="text-muted small text-uppercase">Total Held Records</div>
                <div class="fs-3 fw-bold text-primary"><?= (int)($workspaceData['total_held_records'] ?? 29) ?></div>
                <div class="small text-muted">Di luar 832 database aktif</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="workspace-card p-3 text-center">
                <div class="text-muted small text-uppercase">High-Confidence</div>
                <div class="fs-3 fw-bold text-info"><?= (int)($workspaceData['category_breakdown']['HIGH_CONFIDENCE_CANDIDATE'] ?? 9) ?></div>
                <div class="small text-muted">Lexical score &ge; 0.85</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="workspace-card p-3 text-center">
                <div class="text-muted small text-uppercase">Unresolved / Ambiguous</div>
                <div class="fs-3 fw-bold text-danger"><?= (int)(($workspaceData['category_breakdown']['UNRESOLVED_FEEDER_REFERENCE'] ?? 16) + ($workspaceData['category_breakdown']['AMBIGUOUS_CANDIDATE'] ?? 1)) ?></div>
                <div class="small text-muted">Memerlukan mapping manual</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="workspace-card p-3 text-center">
                <div class="text-muted small text-uppercase">Composite Duplicates</div>
                <div class="fs-3 fw-bold text-purple" style="color: #805ad5;"><?= (int)($workspaceData['category_breakdown']['HOLD_COMPOSITE_DUPLICATE'] ?? 3) ?></div>
                <div class="small text-muted">Incident confirmation required</div>
            </div>
        </div>
    </div>

    <!-- Main Workspace Table -->
    <div class="workspace-card mb-4">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-list-check me-2"></i>Staged Held Records Queue (29 Items)</h6>
            <div class="badge bg-secondary">STAGING_ONLY_READ_MODEL</div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 60px;" class="text-center">Row</th>
                        <th>Raw Feeder Name</th>
                        <th>Classification</th>
                        <th>Candidate / Target Feeder</th>
                        <th>Proposed Action</th>
                        <th>Resolution Reason / Note</th>
                    </tr>
                </thead>
                <tbody id="heldTableBody">
                    <?php if (!empty($workspaceData['staged_records'])): ?>
                        <?php foreach ($workspaceData['staged_records'] as $r): ?>
                            <tr id="row-<?= esc($r['staging_id']) ?>">
                                <td class="text-center fw-bold"><?= (int)$r['source_row'] ?></td>
                                <td>
                                    <strong><?= esc($r['raw_feeder_name']) ?></strong>
                                    <div class="small text-muted font-monospace" style="font-size: 10px;">Hash: <?= substr(esc($r['source_record_hash']), 0, 12) ?>...</div>
                                </td>
                                <td>
                                    <?php if ($r['classification'] === 'HIGH_CONFIDENCE_CANDIDATE'): ?>
                                        <span class="badge badge-high-conf"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>High Confidence (<?= round($r['confidence'] * 100) ?>%)</span>
                                    <?php elseif ($r['classification'] === 'AMBIGUOUS_CANDIDATE'): ?>
                                        <span class="badge badge-ambiguous"><i class="fa-solid fa-triangle-exclamation me-1"></i>Ambiguous (<?= round($r['confidence'] * 100) ?>%)</span>
                                    <?php elseif ($r['classification'] === 'HOLD_COMPOSITE_DUPLICATE'): ?>
                                        <span class="badge badge-duplicate"><i class="fa-solid fa-copy me-1"></i>Composite Duplicate</span>
                                    <?php else: ?>
                                        <span class="badge badge-unresolved"><i class="fa-solid fa-circle-question me-1"></i>Unresolved Feeder</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm feeder-select" data-staging-id="<?= esc($r['staging_id']) ?>" style="max-width: 240px;">
                                        <option value="">-- Pilih Penyulang Master (134) --</option>
                                        <?php foreach ($workspaceData['master_feeders'] as $mf): ?>
                                            <option value="<?= (int)$mf['id'] ?>" <?= ((int)$mf['id'] === (int)$r['candidate_feeder_id']) ? 'selected' : '' ?>>
                                                <?= esc($mf['nama_penyulang']) ?> (ULP <?= (int)$mf['ulp_id'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm action-select" data-staging-id="<?= esc($r['staging_id']) ?>" style="max-width: 220px;">
                                        <?php if ($r['classification'] === 'HOLD_COMPOSITE_DUPLICATE'): ?>
                                            <option value="CONFIRM_DISTINCT_INCIDENT" selected>Confirm Distinct Incident</option>
                                            <option value="REJECT_RECORD">Reject / Mark Duplicate</option>
                                        <?php elseif ($r['classification'] === 'HIGH_CONFIDENCE_CANDIDATE'): ?>
                                            <option value="APPROVE_ALIAS_MAPPING" selected>Approve Alias Mapping</option>
                                            <option value="OVERRIDE_MANUAL_MAPPING">Override Feeder</option>
                                            <option value="REJECT_RECORD">Reject Record</option>
                                        <?php else: ?>
                                            <option value="OVERRIDE_MANUAL_MAPPING">Override / Map Feeder</option>
                                            <option value="REJECT_RECORD" selected>Reject / Exclude</option>
                                        <?php endif; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm notes-input" data-staging-id="<?= esc($r['staging_id']) ?>" value="<?= esc($r['resolution_reason']) ?>" placeholder="Catatan resolusi...">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Dry-Run Result Simulation Box -->
    <div class="dryrun-panel mb-4" id="dryRunPanel" style="display: none;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0 text-success"><i class="fa-solid fa-circle-check me-2"></i>Dry-Run Resolution Plan Generated (Simulation Only)</h5>
            <span class="badge bg-success">ZERO_DATABASE_WRITES</span>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="small text-muted">Resolution Plan ID</div>
                <div class="fw-bold text-light" id="planId">-</div>
            </div>
            <div class="col-md-2">
                <div class="small text-muted">Baseline Disturbances</div>
                <div class="fw-bold text-info" id="baselineCount">832</div>
            </div>
            <div class="col-md-2">
                <div class="small text-muted">Candidate Commits</div>
                <div class="fw-bold text-success" id="candidateCommitCount">0</div>
            </div>
            <div class="col-md-2">
                <div class="small text-muted">Rejected / Excluded</div>
                <div class="fw-bold text-warning" id="rejectedCount">0</div>
            </div>
            <div class="col-md-3">
                <div class="small text-muted">Projected Total After Commit</div>
                <div class="fw-bold text-primary fs-5" id="projectedTotal">832</div>
            </div>
        </div>
        <div class="mb-2">
            <div class="small text-muted mb-1">Cryptographic Confirmation Token (SHA-256)</div>
            <div class="token-box" id="confirmToken">-</div>
        </div>
        <div class="small text-secondary mt-2">
            ⚠️ <em>Pemberitahuan Tata Kelola: Token ini mengikat 832 baseline dan keputusan yang dipilih. Pada Phase 2, tidak ada commit database yang diizinkan.</em>
        </div>
    </div>
</div>

<script>
async function runDryRun() {
    const decisions = {};
    document.querySelectorAll('#heldTableBody tr').forEach(tr => {
        const stagingId = tr.id.replace('row-', '');
        const feederSelect = tr.querySelector('.feeder-select');
        const actionSelect = tr.querySelector('.action-select');
        const notesInput = tr.querySelector('.notes-input');

        decisions[stagingId] = {
            action: actionSelect.value,
            target_feeder_id: feederSelect.value ? parseInt(feederSelect.value) : null,
            notes: notesInput.value
        };
    });

    try {
        const resp = await fetch('<?= base_url('api/held-records/dry-run') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ decisions: decisions })
        });
        const result = await resp.json();

        if (result.success) {
            document.getElementById('dryRunPanel').style.display = 'block';
            document.getElementById('planId').textContent = result.resolution_plan_id;
            document.getElementById('baselineCount').textContent = result.baseline_disturbance_count;
            document.getElementById('candidateCommitCount').textContent = result.candidate_commit_count;
            document.getElementById('rejectedCount').textContent = result.rejected_count;
            document.getElementById('projectedTotal').textContent = result.projected_total_after_commit;
            document.getElementById('confirmToken').textContent = result.confirmation_token;
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        } else {
            alert('Dry-run failed: ' + (result.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Error communicating with server: ' + e.message);
    }
}
</script>

</body>
</html>
