<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Executive Command Center | SIDAK TEJO') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1e293b; }
        .executive-header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .card-custom { border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .kpi-card { border-radius: 10px; padding: 20px; background: #fff; border: 1px solid #e2e8f0; position: relative; overflow: hidden; }
        .kpi-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; }
        .kpi-blue::before { background: #0284c7; }
        .kpi-red::before { background: #ef4444; }
        .kpi-green::before { background: #10b981; }
        .kpi-amber::before { background: #f59e0b; }
        .table th { background-color: #f8fafc; font-size: 11px; text-transform: uppercase; color: #475569; letter-spacing: 0.5px; }
        .table td { vertical-align: middle; font-size: 13px; }
        .nav-pills .nav-link { color: #64748b; font-weight: 600; font-size: 13px; border-radius: 8px; padding: 8px 16px; }
        .nav-pills .nav-link.active { background-color: #0f172a; color: #fff; }
        .badge-critical { background-color: #fee2e2; color: #991b1b; }
        .badge-high { background-color: #fef3c7; color: #92400e; }
        .badge-moderate { background-color: #e0f2fe; color: #075985; }
        .badge-low { background-color: #dcfce7; color: #166534; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4">
    <!-- Executive Header -->
    <div class="executive-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="d-flex align-items-center mb-1">
                    <span class="badge bg-primary me-2">CC-06 EXECUTIVE SUITE</span>
                    <span class="badge bg-success">v3.1.0 ENTERPRISE</span>
                </div>
                <h3 class="fw-bold mb-1"><i class="fa-solid fa-chart-line text-info me-2"></i>Executive Command Center: Grid Infrastructure Reliability & Material Readiness</h3>
                <div class="text-slate-300 small">Konsumsi Analitik Terpadu: Indeks Keandalan JTM (GIRI), Radar Kesehatan Aset Hidup, Gap Pasokan Material, dan Estimasi OPEX/CAPEX</div>
            </div>
            <div class="text-end">
                <a href="<?= base_url('spatial-bom') ?>" class="btn btn-outline-light btn-sm me-2"><i class="fa-solid fa-diagram-project me-1"></i> Spatial BOM</a>
                <a href="<?= base_url('bom') ?>" class="btn btn-outline-light btn-sm me-2"><i class="fa-solid fa-layer-group me-1"></i> BOM Master</a>
                <a href="<?= base_url('gis') ?>" class="btn btn-info btn-sm text-white"><i class="fa-solid fa-map-location-dot me-1"></i> GIS Map</a>
            </div>
        </div>
    </div>

    <!-- Executive KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kpi-card kpi-blue">
                <div class="text-muted small fw-bold text-uppercase">Rata-Rata GIRI Index (134 Penyulang)</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h2 class="mb-0 fw-bold text-primary">83.4%</h2>
                    <span class="ms-2 badge bg-success">+1.2% MoM</span>
                </div>
                <div class="text-muted mt-1 small">Grid Infrastructure Reliability Index</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-red">
                <div class="text-muted small fw-bold text-uppercase">Penyulang Prioritas Kritis</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h2 class="mb-0 fw-bold text-danger">5</h2>
                    <span class="ms-2 badge badge-critical">GIRI < 60%</span>
                </div>
                <div class="text-muted mt-1 small">Butuh Intervensi Preventif Cepat</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-amber">
                <div class="text-muted small fw-bold text-uppercase">Radar Degradasi Aset Fisik</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h2 class="mb-0 fw-bold text-dark">8 / 30</h2>
                    <span class="ms-2 badge badge-high">DEGRADED</span>
                </div>
                <div class="text-muted mt-1 small">22 Sehat Normal (Health ≥ 80)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-green">
                <div class="text-muted small fw-bold text-uppercase">Kesiapan Material Preventif</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h2 class="mb-0 fw-bold text-success">88.5%</h2>
                    <span class="ms-2 badge bg-primary">441 FINDINGS</span>
                </div>
                <div class="text-muted mt-1 small">Cakupan Stok vs Kebutuhan Bridging</div>
            </div>
        </div>
    </div>

    <!-- Main Navigation Pills -->
    <ul class="nav nav-pills mb-4" id="execTabs" role="tablist">
        <li class="nav-item me-2">
            <button class="nav-link active" id="giri-tab" data-bs-toggle="pill" data-bs-target="#tab-giri"><i class="fa-solid fa-tower-broadcast me-1"></i> GIRI Index Penyulang (134 Penyulang)</button>
        </li>
        <li class="nav-item me-2">
            <button class="nav-link" id="radar-tab" data-bs-toggle="pill" data-bs-target="#tab-radar"><i class="fa-solid fa-radar me-1"></i> Radar Degradasi Aset Hidup (30 Aset)</button>
        </li>
        <li class="nav-item me-2">
            <button class="nav-link" id="gap-tab" data-bs-toggle="pill" data-bs-target="#tab-gap"><i class="fa-solid fa-boxes-stacked me-1"></i> Analisis Kesiapan & Gap Material</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="budget-tab" data-bs-toggle="pill" data-bs-target="#tab-budget"><i class="fa-solid fa-coins me-1"></i> Proyeksi Anggaran OPEX / CAPEX</button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Tab 1: GIRI Feeders -->
        <div class="tab-pane fade show active" id="tab-giri">
            <div class="card-custom p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-ranking-star text-primary me-2"></i>Peringkat Keandalan Jaringan JTM (GIRI Score)</h6>
                        <div class="text-muted small">Dihitung otomatis dari bobot M-04/M-05 (40/35/25), frekuensi gangguan historis (841), dan 441 temuan riil.</div>
                    </div>
                    <span class="badge bg-light text-dark border">134 Master Feeders</span>
                </div>
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-hover mb-0">
                        <thead class="sticky-top">
                            <tr>
                                <th>ID</th>
                                <th>Nama Penyulang</th>
                                <th>Kode</th>
                                <th>ULP</th>
                                <th>GIRI Index</th>
                                <th>Kategori Risiko</th>
                                <th>Gangguan (CR-01/02)</th>
                                <th>Temuan Aktif</th>
                                <th>Aset Governed</th>
                            </tr>
                        </thead>
                        <tbody id="giriTableBody">
                            <tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Memuat data GIRI 134 penyulang...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 2: Living Asset Health Radar -->
        <div class="tab-pane fade" id="tab-radar">
            <div class="card-custom p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-heart-pulse text-danger me-2"></i>Radar Kesehatan & Degradasi 30 Aset Fisik Governed</h6>
                        <div class="text-muted small">Evaluasi skor kesehatan aset hasil pemantauan inspeksi lapangan (CR-06) dan penentuan prioritas pemeliharaan.</div>
                    </div>
                    <span class="badge bg-light text-dark border">30 Sealed Physical Assets</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Kode Aset</th>
                                <th>Jenis Aset</th>
                                <th>Penyulang</th>
                                <th>Living Health Score</th>
                                <th>Status Degradasi</th>
                                <th>Rekomendasi Tindakan</th>
                            </tr>
                        </thead>
                        <tbody id="radarTableBody">
                            <tr><td colspan="6" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Memuat radar aset...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 3: Material Readiness Gap -->
        <div class="tab-pane fade" id="tab-gap">
            <div class="card-custom p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-warehouse text-warning me-2"></i>Analisis Kesiapan & Gap Pasokan Material Kanonikal (SPLN)</h6>
                        <div class="text-muted small">Kebutuhan agregat dihitung dari bridging 441 temuan lapangan (CR-08) terhadap estimasi buffer stok logistik UP3.</div>
                    </div>
                    <span class="badge bg-light text-dark border">CR-07 Canonical Master</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Kode Material Kanonikal</th>
                                <th>Total Kebutuhan (441 Temuan)</th>
                                <th>Satuan</th>
                                <th>Estimasi Buffer Stok</th>
                                <th>Defisit / Gap</th>
                                <th>Tingkat Pemenuhan</th>
                            </tr>
                        </thead>
                        <tbody id="gapTableBody">
                            <tr><td colspan="6" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Memuat analisis gap material...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 4: Predictive Budget Estimator -->
        <div class="tab-pane fade" id="tab-budget">
            <div class="card-custom p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-invoice-dollar text-secondary me-2"></i>Proyeksi Anggaran Pemeliharaan Preventif (Baseline Preservation)</h6>
                        <div class="text-muted small">Data historis proyeksi biaya dipreservasi sebagai baseline audit. Modul keuangan aktif dikunci hingga Price Governance Layer diratifikasi.</div>
                    </div>
                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-lock me-1"></i> FROZEN / NON-OPERATIONAL</span>
                </div>
                <div class="alert alert-warning py-2 small mb-3 border-warning">
                    <i class="fa-solid fa-shield-halved me-1"></i> <strong>PANDUAN TATA KELOLA (QUANTITY & EVIDENCE FIRST):</strong> Sistem saat ini memprioritaskan validitas kebutuhan material fisik, jumlah (kuantitas), serta rantai bukti teknis (Aset $\to$ Temuan $\to$ SLD). Pengambilan keputusan anggaran keuangan ditunda hingga Master Tata Kelola Harga (Price Master Layer) selesai diratifikasi.
                </div>
                <div class="row g-4 mt-1">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border">
                            <h6 class="fw-bold text-primary mb-3">Alokasi Estimasi Anggaran per ULP (Preserved Baseline)</h6>
                            <table class="table table-sm table-bordered">
                                <thead class="bg-white">
                                    <tr>
                                        <th>Unit Pelayanan Pelanggan</th>
                                        <th>OPEX Preventif</th>
                                        <th>CAPEX Struktur</th>
                                        <th>Prioritas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="fw-bold">ULP Sidoarjo Kota</td>
                                        <td>Rp 45.000.000</td>
                                        <td>Rp 120.000.000</td>
                                        <td><span class="badge bg-danger">HIGH</span></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">ULP Krian</td>
                                        <td>Rp 38.000.000</td>
                                        <td>Rp 95.000.000</td>
                                        <td><span class="badge bg-danger">HIGH</span></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">ULP Porong</td>
                                        <td>Rp 28.000.000</td>
                                        <td>Rp 60.000.000</td>
                                        <td><span class="badge bg-warning text-dark">MEDIUM</span></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">ULP Sedati</td>
                                        <td>Rp 22.000.000</td>
                                        <td>Rp 45.000.000</td>
                                        <td><span class="badge bg-success">LOW</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border">
                            <h6 class="fw-bold text-dark mb-3">Kalkulator Rekomendasi Eksekutif (Berjejak Action Hash)</h6>
                            <div class="mb-3 small text-muted">
                                Menghasilkan berkas rekomendasi anggaran eksekutif berjejak kriptografis. Otoritas persetujuan akhir berada pada <strong>Senior Manager UP3</strong>.
                            </div>
                            <button class="btn btn-primary btn-sm mb-3" onclick="generateBudgetRec()"><i class="fa-solid fa-signature me-1"></i> Generate Budget Recommendation Hash</button>
                            <div id="budgetHashResult" class="d-none alert alert-success p-2 small font-monospace"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    loadGiri();
    loadRadar();
    loadGap();
});

async function loadGiri() {
    try {
        const resp = await fetch('<?= base_url('api/executive/giri-feeders') ?>');
        const res = await resp.json();
        if (res.success) {
            let html = '';
            res.feeders.forEach(f => {
                const badgeClass = (f.risk_tier === 'CRITICAL_PREVENTIVE_ATTENTION') ? 'badge-critical' :
                                   (f.risk_tier === 'HIGH_RISK_RECURRENCE') ? 'badge-high' :
                                   (f.risk_tier === 'MODERATE_DEGRADATION') ? 'badge-moderate' : 'badge-low';
                html += `<tr>
                    <td>#${f.penyulang_id}</td>
                    <td class="fw-bold text-dark">${f.nama_penyulang}</td>
                    <td class="font-monospace">${f.kode_penyulang}</td>
                    <td>ULP #${f.ulp_id}</td>
                    <td class="fw-bold ${f.giri_score < 70 ? 'text-danger' : 'text-primary'}">${f.giri_score}%</td>
                    <td><span class="badge ${badgeClass}">${f.risk_tier}</span></td>
                    <td>${f.total_disturbances} kali</td>
                    <td>${f.total_findings} temuan</td>
                    <td>${f.governed_assets} aset</td>
                </tr>`;
            });
            document.getElementById('giriTableBody').innerHTML = html;
        }
    } catch (e) {
        console.error(e);
    }
}

async function loadRadar() {
    try {
        const resp = await fetch('<?= base_url('api/executive/asset-radar') ?>');
        const res = await resp.json();
        if (res.success) {
            let html = '';
            res.assets.forEach(a => {
                const badgeClass = (a.health_score < 60) ? 'bg-danger' :
                                   (a.health_score < 75) ? 'bg-warning text-dark' :
                                   (a.health_score < 85) ? 'bg-info text-dark' : 'bg-success';
                html += `<tr>
                    <td class="font-monospace fw-bold text-primary">${a.kode_asset}</td>
                    <td><small class="badge bg-light text-dark border">${a.jenis_asset}</small></td>
                    <td>Feeder #${a.penyulang_id}</td>
                    <td><span class="badge ${badgeClass}">Score: ${a.health_score}</span></td>
                    <td><span class="badge bg-light text-secondary border">${a.degradation_tier}</span></td>
                    <td><small class="fw-semibold ${a.action_required.includes('PRIORITY') ? 'text-danger' : 'text-success'}">${a.action_required}</small></td>
                </tr>`;
            });
            document.getElementById('radarTableBody').innerHTML = html;
        }
    } catch (e) {
        console.error(e);
    }
}

async function loadGap() {
    try {
        const resp = await fetch('<?= base_url('api/executive/material-gap') ?>');
        const res = await resp.json();
        if (res.success) {
            let html = '';
            res.materials.forEach(m => {
                html += `<tr>
                    <td class="font-monospace fw-bold text-primary">${m.canonical_material_code}</td>
                    <td class="fw-bold">${m.total_required}</td>
                    <td>${m.unit}</td>
                    <td>${m.estimated_stock}</td>
                    <td class="${m.gap > 0 ? 'text-danger fw-bold' : 'text-success'}">${m.gap > 0 ? '- ' + m.gap : 'TERPENUHI'}</td>
                    <td>
                        <div class="progress" style="height: 16px;">
                            <div class="progress-bar ${m.fulfillment_rate < 80 ? 'bg-warning text-dark' : 'bg-success'}" style="width: ${m.fulfillment_rate}%">${m.fulfillment_rate}%</div>
                        </div>
                    </td>
                </tr>`;
            });
            document.getElementById('gapTableBody').innerHTML = html;
        }
    } catch (e) {
        console.error(e);
    }
}

async function generateBudgetRec() {
    try {
        const resp = await fetch('<?= base_url('api/executive/budget-estimation') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        });
        const res = await resp.json();
        if (res.success) {
            const r = res.recommendation;
            const el = document.getElementById('budgetHashResult');
            el.innerHTML = `<strong>ID:</strong> ${r.recommendation_id}<br><strong>Grand Total:</strong> Rp 453.000.000<br><strong>Action Hash:</strong> ${r.action_hash}<br><strong>Decision:</strong> ${r.decision_boundary}`;
            el.classList.remove('d-none');
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}
</script>

</body>
</html>
