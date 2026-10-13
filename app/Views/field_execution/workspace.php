<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <!-- Header Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-white shadow-sm border-0">
                <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <span class="badge bg-success mb-2">B8.7 CORE ENGINE — LOCKED 🔒</span>
                        <h2 class="h3 font-weight-bold mb-1">Field Work Execution & Outage Realization Control Center</h2>
                        <p class="text-muted mb-0 font-monospace" style="font-size: 0.9rem;">
                            Master Baseline: <strong class="text-info"><?= esc($summary['master_topology_snapshot']) ?></strong> | 
                            Active Translines: <strong class="text-white"><?= esc($summary['active_translines']) ?></strong> | 
                            Active Assets: <strong class="text-white"><?= esc($summary['active_assets']) ?></strong> | 
                            &Delta;topology: <strong class="text-success">0 (READ-ONLY)</strong>
                        </p>
                    </div>
                    <div class="mt-3 mt-md-0">
                        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#dispatchModal">
                            <i class="fas fa-paper-plane me-1"></i> Dispatch Field Team
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
                    <h6 class="card-subtitle text-muted mb-2">Total Executions</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-primary" id="totalExecutions"><?= esc($summary['total_executions']) ?></h3>
                    <small class="text-muted">Dispatched field work units</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">In Progress / Safety Hold</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-warning" id="inProgressHold">
                        <?= esc($summary['executions_in_progress']) ?> <span class="fs-6 text-danger">(<?= esc($summary['executions_safety_hold']) ?> Hold)</span>
                    </h3>
                    <small class="text-muted">Active field execution state</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Outage Realization Ratio</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-info" id="outageRatio"><?= esc($summary['outage_efficiency_ratio_pct']) ?>%</h3>
                    <small class="text-muted">Actual vs Planned outage duration</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Verified Closed (L5 &rarr; L6)</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-success" id="verifiedClosed"><?= esc($summary['verified_closed']) ?></h3>
                    <small class="text-muted">Human supervisor signoff complete</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Governance Boundary & Rules Alert -->
    <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-shield-alt fa-2x me-3 text-info"></i>
            <div>
                <strong>B8.7 Technical Governance Rules Enforced:</strong>
                <ul class="mb-0 mt-1" style="font-size: 0.88rem;">
                    <li><strong>B87-G01 & B87-G02:</strong> GIS & Asset master tables strictly <strong>READ-ONLY</strong> (&Delta;topology = 0).</li>
                    <li><strong>B87-G03 & B87-G04:</strong> Field start requires explicit human action + <strong>Mandatory Before Photo</strong> (SHA-256 hashed).</li>
                    <li><strong>B87-G06 & B87-G08:</strong> Real-time tracking of actual outage duration & material consumption variance.</li>
                    <li><strong>B87-G10:</strong> AI Engine ceiling capped at <strong>L4 RECOMMENDED</strong>. Signoff & Closure ($L5 \rightarrow L6$) reserved for Human Supervisors.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Dispatch Modal -->
    <div class="modal fade" id="dispatchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-paper-plane me-2"></i>Dispatch Field Execution Team</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="dispatchForm" onsubmit="submitDispatch(event)">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Feeder / Penyulang</label>
                            <input type="text" class="form-control" name="feeder_name" value="KEDUNGPRING" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Section / Seksi</label>
                            <input type="text" class="form-control" name="section_name" value="KDP-01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Lingkup Pekerjaan</label>
                            <input type="text" class="form-control" name="work_scope" value="Perabasan & Penggantian Isolator Tumpu" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Estimasi Pemadaman (Menit)</label>
                                <input type="number" class="form-control" name="planned_outage_duration_mins" value="240" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Jumlah Regu Kerja</label>
                                <input type="number" class="form-control" name="crew_count" value="4" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i> Dispatch Regu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function loadSummary() {
    fetch('<?= base_url('api/field-execution/summary') ?>')
        .then(r => r.json())
        .then(data => {
            document.getElementById('totalExecutions').innerText = data.total_executions;
            document.getElementById('inProgressHold').innerHTML = data.executions_in_progress + ' <span class="fs-6 text-danger">(' + data.executions_safety_hold + ' Hold)</span>';
            document.getElementById('outageRatio').innerText = data.outage_efficiency_ratio_pct + '%';
            document.getElementById('verifiedClosed').innerText = data.verified_closed;
        })
        .catch(e => console.error('Error refreshing summary:', e));
}

function submitDispatch(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    fetch('<?= base_url('api/field-execution/dispatch') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            alert('Berhasil dispatch regu kerja: ' + res.execution_code + ' (Status: WORK_PENDING_FIELD_START)');
            bootstrap.Modal.getInstance(document.getElementById('dispatchModal')).hide();
            loadSummary();
        } else {
            alert('Gagal dispatch: ' + (res.message || 'Kesalahan sistem'));
        }
    })
    .catch(err => alert('Error: ' + err));
}
</script>
<?= $this->endSection() ?>
