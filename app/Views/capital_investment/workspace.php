<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <!-- Header Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-dark text-white shadow-sm border-0">
                <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <span class="badge bg-success mb-2">B8.10 CORE ENGINE — LOCKED 🔒</span>
                        <h2 class="h3 font-weight-bold mb-1">Executive Reliability Capital Allocation & Investment Optimization</h2>
                        <p class="text-muted mb-0 font-monospace" style="font-size: 0.9rem;">
                            Master Baseline: <strong class="text-info"><?= esc($summary['master_topology_snapshot']) ?></strong> | 
                            Active Translines: <strong class="text-white"><?= esc($summary['active_translines']) ?></strong> | 
                            Active Assets: <strong class="text-white"><?= esc($summary['active_assets']) ?></strong> | 
                            &Delta;topology: <strong class="text-success">0 (READ-ONLY)</strong>
                        </p>
                    </div>
                    <div class="mt-3 mt-md-0">
                        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#assembleModal">
                            <i class="fas fa-coins me-1"></i> Assemble Capital Portfolio
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
                    <h6 class="card-subtitle text-muted mb-2">Total Capital Portfolios</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-primary" id="totalPortfolios"><?= esc($summary['total_capital_portfolios']) ?></h3>
                    <small class="text-muted">Proposed & Sealed investment packages</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Total Budget (CapEx + OpEx)</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-info" id="totalBudget">
                        Rp <?= number_format($summary['total_capex_budget'] + $summary['total_opex_budget'], 0, ',', '.') ?>
                    </h3>
                    <small class="text-muted">Allocated reliability investment</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Simulated SAIDI Reduction</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-success" id="simSaidi"><?= esc($summary['simulated_saidi_reduction_mins']) ?> Mins/Yr</h3>
                    <small class="text-muted">Expected outage duration saved</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Simulated SAIFI Reduction</h6>
                    <h3 class="card-title font-weight-bold mb-0 text-warning" id="simSaifi"><?= esc($summary['simulated_saifi_reduction_freq']) ?> Freq/Yr</h3>
                    <small class="text-muted">Interruption frequency reduction</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Technical Governance Alert -->
    <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-shield-alt fa-2x me-3 text-info"></i>
            <div>
                <strong>B8.10 Technical Governance Rules Enforced:</strong>
                <ul class="mb-0 mt-1" style="font-size: 0.88rem;">
                    <li><strong>B810-G01 & B810-G02:</strong> GIS & Asset master tables strictly <strong>READ-ONLY</strong> (&Delta;topology = 0).</li>
                    <li><strong>B810-G03 & B810-G05:</strong> SAIDI / SAIFI Interruption Reduction Simulation + <strong>Risk-Weighted RoI Scoring Matrix</strong>.</li>
                    <li><strong>B810-G06 & B810-G07:</strong> Executive Senior Manager Ratification Gate ($L5 \rightarrow L6$) + <strong>SHA-256 Investment Certificate Seal</strong>.</li>
                    <li><strong>B810-G10:</strong> AI Engine ceiling capped at <strong>L4 RECOMMENDED</strong>. Zero direct AI state mutations.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Assemble Modal -->
    <div class="modal fade" id="assembleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-coins me-2"></i>Rakit Draf Portofolio Investasi Keandalan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="assembleForm" onsubmit="submitAssemble(event)">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Nama Portofolio Investasi</label>
                            <input type="text" class="form-control" name="portfolio_name" value="Portofolio Mitigasi Keandalan ULP Kedungpring 2026" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Tahun Anggaran</label>
                                <input type="number" class="form-control" name="fiscal_year" value="2026" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Target Jumlah Penyulang</label>
                                <input type="number" class="form-control" name="target_feeder_count" value="3" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Anggaran CapEx (Rp)</label>
                            <input type="number" class="form-control" name="total_capex_budget" value="450000000" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Anggaran OpEx (Rp)</label>
                            <input type="number" class="form-control" name="total_opex_budget" value="150000000" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i> Rakit & Simulasi SAIDI</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function loadSummary() {
    fetch('<?= base_url('api/capital-investment/summary') ?>')
        .then(r => r.json())
        .then(data => {
            document.getElementById('totalPortfolios').innerText = data.total_capital_portfolios;
            document.getElementById('simSaidi').innerText = data.simulated_saidi_reduction_mins + ' Mins/Yr';
            document.getElementById('simSaifi').innerText = data.simulated_saifi_reduction_freq + ' Freq/Yr';
        })
        .catch(e => console.error('Error refreshing summary:', e));
}

function submitAssemble(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    fetch('<?= base_url('api/capital-investment/assemble') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            alert('Portofolio berhasil dirakit! Simulasi Reduksi SAIDI: ' + res.simulated_saidi_reduction_mins + ' Menit/Tahun (Kode: ' + res.portfolio_code + ')');
            bootstrap.Modal.getInstance(document.getElementById('assembleModal')).hide();
            loadSummary();
        } else {
            alert('Gagal merakit portofolio: ' + (res.message || 'Kesalahan sistem'));
        }
    })
    .catch(err => alert('Error: ' + err));
}
</script>
<?= $this->endSection() ?>
