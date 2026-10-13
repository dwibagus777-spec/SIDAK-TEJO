<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <!-- Header Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-white shadow-sm border-0">
                <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <span class="badge bg-success mb-2">B8.8 CORE ENGINE — LOCKED 🔒</span>
                        <h2 class="h3 font-weight-bold mb-1">Work Acceptance, QA & Asset Health Recalibration Center</h2>
                        <p class="text-muted mb-0 font-monospace" style="font-size: 0.9rem;">
                            Master Baseline: <strong class="text-info"><?= esc($summary['master_topology_snapshot']) ?></strong> | 
                            Active Translines: <strong class="text-white"><?= esc($summary['active_translines']) ?></strong> | 
                            Active Assets: <strong class="text-white"><?= esc($summary['active_assets']) ?></strong> | 
                            &Delta;topology: <strong class="text-success">0 (READ-ONLY)</strong>
                        </p>
                    </div>
                    <div class="mt-3 mt-md-0">
                        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#evaluateModal">
                            <i class="fas fa-clipboard-check me-1"></i> QA 4D Evaluation
                        </button>
                        <button class="btn btn-outline-light" onclick="loadSummary()">
                            <i class="fas fa-sync-alt me-1"></i> Refresh Realtime
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Metrics Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Total Acceptances</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-primary" id="totalAcceptances"><?= esc($summary['total_acceptances']) ?></h3>
                    <small class="text-muted">Registered QA review cases</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Average QA Quality Score</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-info" id="avgScore"><?= esc($summary['average_quality_score']) ?>%</h3>
                    <small class="text-muted">Target threshold &ge; 85.00%</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">AHI Recalibrated Assets</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-warning" id="recalibratedCount"><?= esc($summary['recalibrated_assets_count']) ?></h3>
                    <small class="text-muted">Post-maintenance AHI score boost</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Forensically Closed (L5 &rarr; L6)</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-success" id="workClosed"><?= esc($summary['work_closed']) ?></h3>
                    <small class="text-muted">Executive Manager SHA-256 seal</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Technical Governance Alert -->
    <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-shield-alt fa-2x me-3 text-info"></i>
            <div>
                <strong>B8.8 Technical Governance Rules Enforced:</strong>
                <ul class="mb-0 mt-1" style="font-size: 0.88rem;">
                    <li><strong>B88-G01 & B88-G02:</strong> GIS & Asset master tables strictly <strong>READ-ONLY</strong> (&Delta;topology = 0).</li>
                    <li><strong>B88-G03 & B88-G04:</strong> 4D Quality Assurance Evaluation + <strong>Separation of Duties</strong> (Field Executor &ne; QA Inspector).</li>
                    <li><strong>B88-G07:</strong> Automatic post-maintenance <strong>Asset Health Index (AHI) Recalibration Loop</strong> (+15 to +25 points restored in B8.3).</li>
                    <li><strong>B88-G10:</strong> AI Engine ceiling capped at <strong>L4 RECOMMENDED</strong>. Executive Closure ($L5 \rightarrow L6$) strictly reserved for Human Manager.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- QA Evaluation Modal -->
    <div class="modal fade" id="evaluateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-clipboard-check me-2"></i>Evaluasi Mutu QA 4D</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="evaluateForm" onsubmit="submitEvaluation(event)">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">ID Accept Record</label>
                            <input type="number" class="form-control" name="acceptance_id" value="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">1. Evidence Verification (25%)</label>
                            <input type="number" step="0.1" class="form-control" name="evidence_score" value="100.0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">2. Technical Quality (30%)</label>
                            <input type="number" step="0.1" class="form-control" name="technical_score" value="95.0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">3. Material Audit (25%)</label>
                            <input type="number" step="0.1" class="form-control" name="material_score" value="100.0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">4. As-Built Verification (20%)</label>
                            <input type="number" step="0.1" class="form-control" name="asbuilt_score" value="95.0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i> Submit Evaluasi QA</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function loadSummary() {
    fetch('<?= base_url('api/work-acceptance/summary') ?>')
        .then(r => r.json())
        .then(data => {
            document.getElementById('totalAcceptances').innerText = data.total_acceptances;
            document.getElementById('avgScore').innerText = data.average_quality_score + '%';
            document.getElementById('recalibratedCount').innerText = data.recalibrated_assets_count;
            document.getElementById('workClosed').innerText = data.work_closed;
        })
        .catch(e => console.error('Error refreshing summary:', e));
}

function submitEvaluation(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    fetch('<?= base_url('api/work-acceptance/evaluate') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            alert('Evaluasi QA 4D berhasil! Skor Mutu: ' + res.quality_score + '% (Status: ' + res.acceptance_status + ')');
            bootstrap.Modal.getInstance(document.getElementById('evaluateModal')).hide();
            loadSummary();
        } else {
            alert('Gagal evaluasi: ' + (res.message || 'Kesalahan sistem'));
        }
    })
    .catch(err => alert('Error: ' + err));
}
</script>
<?= $this->endSection() ?>
