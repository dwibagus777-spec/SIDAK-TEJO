<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <!-- Header Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-white shadow-sm border-0">
                <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <span class="badge bg-success mb-2">B8.9 CORE ENGINE — LOCKED 🔒</span>
                        <h2 class="h3 font-weight-bold mb-1">Continuous Reliability Intelligence & Closed-Loop Learning</h2>
                        <p class="text-muted mb-0 font-monospace" style="font-size: 0.9rem;">
                            Master Baseline: <strong class="text-info"><?= esc($summary['master_topology_snapshot']) ?></strong> | 
                            Active Translines: <strong class="text-white"><?= esc($summary['active_translines']) ?></strong> | 
                            Active Assets: <strong class="text-white"><?= esc($summary['active_assets']) ?></strong> | 
                            &Delta;topology: <strong class="text-success">0 (READ-ONLY)</strong>
                        </p>
                    </div>
                    <div class="mt-3 mt-md-0">
                        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#lineageModal">
                            <i class="fas fa-history me-1"></i> Record 7-Stage AHI Lineage
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
                    <h6 class="card-subtitle text-muted mb-2">7-Stage AHI Lineage Logs</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-primary" id="totalLineage"><?= esc($summary['total_ahi_lineage_records']) ?></h3>
                    <small class="text-muted">Auditable AHI restoration records</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Avg Job Effectiveness Index (JEI)</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-info" id="avgJei"><?= esc($summary['average_job_effectiveness']) ?>%</h3>
                    <small class="text-muted">Outage efficiency + QA score</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Learning Loop Events</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-warning" id="totalEvents"><?= esc($summary['total_learning_events']) ?></h3>
                    <small class="text-muted">SHA-256 fingerprinted loops</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Recurrence Risk Distribution</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-success" id="riskDist">
                        <?= esc($summary['recurrence_low_risk']) ?> <span class="fs-6 text-muted">Low</span>
                    </h3>
                    <small class="text-muted">60-day repeat finding radar</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Auditable 7-Stage AHI Lineage Architecture Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white font-weight-bold py-3">
            <i class="fas fa-sitemap me-2 text-primary"></i> 7-Stage Auditable AHI Recalibration Lineage Flow
        </div>
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center text-center flex-wrap gap-2">
                <div class="p-2 border rounded bg-light flex-fill">
                    <span class="badge bg-secondary mb-1">STAGE 1</span>
                    <h6 class="mb-0 font-weight-bold">BEFORE AHI</h6>
                    <small class="text-muted">Baseline Health Score</small>
                </div>
                <i class="fas fa-arrow-right text-muted d-none d-md-inline"></i>
                <div class="p-2 border rounded bg-light flex-fill">
                    <span class="badge bg-info mb-1">STAGE 2</span>
                    <h6 class="mb-0 font-weight-bold">Evidence QA</h6>
                    <small class="text-muted">SHA-256 Hashed Photos</small>
                </div>
                <i class="fas fa-arrow-right text-muted d-none d-md-inline"></i>
                <div class="p-2 border rounded bg-light flex-fill">
                    <span class="badge bg-primary mb-1">STAGE 3</span>
                    <h6 class="mb-0 font-weight-bold">Tech Acceptance</h6>
                    <small class="text-muted">QA Score &ge; 85%</small>
                </div>
                <i class="fas fa-arrow-right text-muted d-none d-md-inline"></i>
                <div class="p-2 border rounded bg-light flex-fill">
                    <span class="badge bg-warning text-dark mb-1">STAGE 4</span>
                    <h6 class="mb-0 font-weight-bold">Post Evidence</h6>
                    <small class="text-muted">ROW & Safety Clean</small>
                </div>
                <i class="fas fa-arrow-right text-muted d-none d-md-inline"></i>
                <div class="p-2 border rounded bg-light flex-fill">
                    <span class="badge bg-dark mb-1">STAGE 5</span>
                    <h6 class="mb-0 font-weight-bold">Recalculation</h6>
                    <small class="text-muted">Provenance Boost</small>
                </div>
                <i class="fas fa-arrow-right text-muted d-none d-md-inline"></i>
                <div class="p-2 border rounded bg-light flex-fill">
                    <span class="badge bg-success mb-1">STAGE 6</span>
                    <h6 class="mb-0 font-weight-bold">AFTER AHI</h6>
                    <small class="text-muted">Restored AHI Score</small>
                </div>
                <i class="fas fa-arrow-right text-muted d-none d-md-inline"></i>
                <div class="p-2 border rounded bg-success text-white flex-fill">
                    <span class="badge bg-light text-dark mb-1">STAGE 7</span>
                    <h6 class="mb-0 font-weight-bold">Fingerprint</h6>
                    <small>B8.5 Forensic Seal</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Reliability Advisories Section -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white font-weight-bold py-3 d-flex justify-content-between align-items-center">
            <span><i class="fas fa-lightbulb text-warning me-2"></i> Reliability Improvement Advisory (L4 RECOMMENDED Cap)</span>
            <span class="badge bg-info">L4 RECOMMENDED</span>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                <?php foreach ($advisories as $adv): ?>
                <div class="list-group-item p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="font-weight-bold mb-1 text-primary"><?= esc($adv['advisory_title']) ?></h6>
                            <p class="mb-1 text-muted" style="font-size: 0.88rem;"><?= esc($adv['provenance_rationale']) ?></p>
                            <small class="text-monospace">Penyulang: <strong><?= esc($adv['feeder_name']) ?></strong> | Seksi: <strong><?= esc($adv['section_name']) ?></strong></small>
                        </div>
                        <span class="badge bg-success font-monospace"><?= esc($adv['ai_ceiling_level']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Lineage Modal -->
    <div class="modal fade" id="lineageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-history me-2"></i>Record 7-Stage AHI Recalibration Lineage</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="lineageForm" onsubmit="submitLineage(event)">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Feeder / Penyulang</label>
                            <input type="text" class="form-control" name="feeder_name" value="KEDUNGPRING" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Section / Seksi</label>
                            <input type="text" class="form-control" name="section_name" value="KDP-01" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">BEFORE AHI Score</label>
                                <input type="number" step="0.1" class="form-control" name="before_ahi_score" value="65.0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">QA Quality Score (%)</label>
                                <input type="number" step="0.1" class="form-control" name="qa_quality_score" value="95.0" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Technical Acceptance By</label>
                            <input type="text" class="form-control" name="technical_acceptance_by" value="INSPEKTUR_MUTU_JARINGAN" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i> Seal 7-Stage Lineage</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function loadSummary() {
    fetch('<?= base_url('api/reliability-learning/summary') ?>')
        .then(r => r.json())
        .then(data => {
            document.getElementById('totalLineage').innerText = data.total_ahi_lineage_records;
            document.getElementById('avgJei').innerText = data.average_job_effectiveness + '%';
            document.getElementById('totalEvents').innerText = data.total_learning_events;
        })
        .catch(e => console.error('Error refreshing summary:', e));
}

function submitLineage(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    fetch('<?= base_url('api/reliability-learning/recalibrate-lineage') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            alert('Lineage 7-Stage berhasil tersegel! AFTER AHI: ' + res.after_ahi_score + ' (Fingerprint: ' + res.lineage_fingerprint.substring(0, 16) + '...)');
            bootstrap.Modal.getInstance(document.getElementById('lineageModal')).hide();
            loadSummary();
        } else {
            alert('Gagal mencatat lineage: ' + (res.message || 'Kesalahan sistem'));
        }
    })
    .catch(err => alert('Error: ' + err));
}
</script>
<?= $this->endSection() ?>
