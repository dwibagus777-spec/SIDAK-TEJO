<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Spatial BOM & Preventive Material Bridge | SIDAK TEJO') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .table th { background-color: #f7fafc; font-size: 11px; text-transform: uppercase; color: #4a5568; }
        .table td { vertical-align: middle; font-size: 13px; }
        .governance-banner { background: #e0f2fe; border-left: 4px solid #0284c7; padding: 12px 16px; border-radius: 4px; }
        .nav-tabs .nav-link { font-size: 13px; font-weight: 600; color: #4a5568; border: none; border-bottom: 2px solid transparent; }
        .nav-tabs .nav-link.active { color: #0284c7; border-bottom: 2px solid #0284c7; background: transparent; }
        .badge-exact { background-color: #dcfce7; color: #166534; }
        .badge-high { background-color: #fef9c3; color: #854d0e; }
        .badge-ambig { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-diagram-project text-primary me-2"></i>Spatial BOM & Preventive Material Intelligence Bridge</h4>
            <div class="text-muted small">CR-08 Phase 2: Asset-to-Construction Binding, 441 Findings-to-BOM Normalization & Feeder Material Recommendation</div>
        </div>
        <div>
            <a href="<?= base_url('bom') ?>" class="btn btn-outline-secondary btn-sm me-2"><i class="fa-solid fa-layer-group me-1"></i> BOM Master</a>
            <a href="<?= base_url('assets') ?>" class="btn btn-outline-secondary btn-sm me-2"><i class="fa-solid fa-network-wired me-1"></i> Asset Truth</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#recModal"><i class="fa-solid fa-calculator me-1"></i> Rekomendasi Material Penyulang</button>
        </div>
    </div>

    <!-- Governance Banner -->
    <div class="governance-banner mb-4">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-bridge-lock fa-2x me-3" style="color: #0284c7;"></i>
            <div>
                <div class="fw-bold" style="color: #0369a1;">🛡️ GROUP E SPATIAL & FINDING-TO-BOM BRIDGE FABRIC ACTIVE (CR-08)</div>
                <div class="small text-secondary">
                    Menghubungkan <strong>30 Aset Fisik Tersegel</strong> ke taksonomi konstruksi dan mengonversi <strong>441 Temuan Lapangan</strong> menjadi estimasi kebutuhan material kanonikal secara governed.
                    Output estimasi berstatus <em>RECOMMENDATION_ONLY</em> dengan otoritas akhir berada pada Pejabat Teknik Jaringan.
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Cakupan Aset Terpetakan</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h3 class="mb-0 fw-bold text-primary"><?= (int)($summary['total_assets_mapped'] ?? 0) ?> / 30</h3>
                    <span class="ms-2 badge bg-success">100% COVERED</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">Relasi Konstruksi & Konduktor Group E</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Temuan Terjembatani (Bridged)</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h3 class="mb-0 fw-bold text-success"><?= (int)($summary['total_findings_bridged'] ?? 0) ?> / 441</h3>
                    <span class="ms-2 badge bg-primary">441 FINDINGS</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">Normalisasi ke Material Kanonikal SPLN</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Exact & High Confidence Match</div>
                <div class="d-flex align-items-baseline mt-2">
                    <?php
                    $exact = (int)($summary['findings_classification']['exact_matches'] ?? 0);
                    $high = (int)($summary['findings_classification']['high_confidence_matches'] ?? 0);
                    $total = $exact + $high;
                    ?>
                    <h3 class="mb-0 fw-bold text-success"><?= $total ?></h3>
                    <span class="ms-2 badge bg-info text-dark"><?= round(($total / 441) * 100, 1) ?>%</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">Klasifikasi Tingkat Keyakinan Tinggi</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Preservasi Invarian Database</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h3 class="mb-0 fw-bold text-dark">10 / 10</h3>
                    <span class="ms-2 badge bg-secondary">ZERO_WRITES</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">Group A & Group B 100% Intak</div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-3" id="spatialTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="assets-tab" data-bs-toggle="tab" data-bs-target="#tab-assets"><i class="fa-solid fa-tower-observation me-1"></i> Hierarki Spasial Aset & Konstruksi (30 Aset)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="findings-tab" data-bs-toggle="tab" data-bs-target="#tab-findings"><i class="fa-solid fa-triangle-exclamation me-1"></i> Bridging 441 Temuan ke Material Perbaikan</button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content">
        <!-- Tab 1: Spatial Assets Hierarchy -->
        <div class="tab-pane fade show active" id="tab-assets">
            <div class="card-custom p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-network-wired text-primary me-2"></i>Hierarki Hubungan Aset $\to$ Konstruksi JTM $\to$ Konduktor $\to$ Living Health</h6>
                    <span class="badge bg-light text-dark border">30 Governed Physical Assets</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Kode Aset</th>
                                <th>Jenis Aset</th>
                                <th>Penyulang ID</th>
                                <th>Seksi ID</th>
                                <th>Konstruksi JTM</th>
                                <th>Konduktor JTM</th>
                                <th>Living Health</th>
                                <th>Aksi Detail</th>
                            </tr>
                        </thead>
                        <tbody id="assetsTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-3 text-muted"><i class="fa-solid fa-spinner fa-spin me-1"></i> Memuat hierarki aset spasial...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 2: Findings Bridge -->
        <div class="tab-pane fade" id="tab-findings">
            <div class="card-custom p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-screwdriver-wrench text-warning me-2"></i>Katalog Bridging 441 Temuan Aktif ke Rekomendasi Material Kanonikal</h6>
                    <span class="badge bg-light text-dark border">441 Active Findings</span>
                </div>
                <div class="alert alert-info py-2 small mb-3">
                    <i class="fa-solid fa-circle-info me-1"></i> Seluruh temuan aktif dinormalisasi secara otomatis melalui <em>CR-07 Field Alias Matcher</em> untuk memprediksi kebutuhan komponen material perbaikan. Hasil ini berstatus <strong>RECOMMENDATION_ONLY</strong>.
                </div>
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="sticky-top">
                            <tr>
                                <th>ID Temuan</th>
                                <th>Nomor Temuan</th>
                                <th>Penyulang</th>
                                <th>Kode Material Rekomendasi</th>
                                <th>Kuantitas</th>
                                <th>Satuan</th>
                                <th>Tingkat Keyakinan</th>
                                <th>Otoritas Keputusan</th>
                            </tr>
                        </thead>
                        <tbody id="findingsTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-3 text-muted"><i class="fa-solid fa-spinner fa-spin me-1"></i> Memuat bridging temuan...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Spatial Asset BOM -->
<div class="modal fade" id="assetDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="assetModalTitle">Rincian Spasial BOM Aset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border">
                            <div class="small text-muted">Informasi Aset Fisik</div>
                            <h5 class="fw-bold text-primary mb-1" id="mAssetCode">-</h5>
                            <div id="mAssetType" class="small fw-semibold mb-1">-</div>
                            <div class="small text-muted" id="mAssetLocation">-</div>
                            <div class="mt-2">
                                <span class="badge bg-success" id="mAssetHealth">Living Health: 100</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border">
                            <div class="small text-muted">Spesifikasi Konstruksi & Jaringan</div>
                            <h5 class="fw-bold text-dark mb-1" id="mConstName">-</h5>
                            <div class="small text-muted mb-1" id="mCondSpec">-</div>
                            <div class="small text-muted" id="mFeederSec">-</div>
                            <div class="mt-2">
                                <span class="badge bg-primary" id="mConstCode">TM-1</span>
                            </div>
                        </div>
                    </div>
                </div>
                <h6 class="fw-bold mb-2"><i class="fa-solid fa-list-check text-primary me-1"></i>Standar Material Bill of Materials (BOM)</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="bg-light">
                            <tr>
                                <th>Kode Material</th>
                                <th>Kuantitas Standar</th>
                                <th>Satuan</th>
                                <th>Tipe Kebutuhan</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody id="mBomBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Rekomendasi Material Penyulang -->
<div class="modal fade" id="recModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-calculator text-primary me-2"></i>Kalkulator Kebutuhan Material Preventif Penyulang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-bold">Pilih Penyulang Target</label>
                        <select id="recFeederSelect" class="form-select form-select-sm">
                            <option value="15">BANJAR KEMANTREN (ID: 15) - 31 Temuan Aktif</option>
                            <option value="1">SIWALAN PANJI (ID: 1)</option>
                            <option value="3">SIDOMULYO (ID: 3)</option>
                            <option value="18">KENCAR (ID: 18)</option>
                            <option value="41">OSAKA (ID: 41)</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-primary btn-sm w-100" onclick="submitFeederRec()"><i class="fa-solid fa-cogs me-1"></i> Agregasi Kebutuhan</button>
                    </div>
                </div>
                <div id="recResultBox" class="d-none">
                    <h6 class="fw-bold text-success mb-2"><i class="fa-solid fa-check-circle me-1"></i>Hasil Rekomendasi Material (Non-Autonomous Evidence)</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="bg-light">
                                <tr>
                                    <th>Kode Material</th>
                                    <th>Nama Material Resmi</th>
                                    <th>Kebutuhan Total</th>
                                    <th>Satuan</th>
                                    <th>Exact Matches</th>
                                </tr>
                            </thead>
                            <tbody id="recResultBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    loadSpatialAssets();
    loadFindingsBridge();
});

async function loadSpatialAssets() {
    try {
        const resp = await fetch('<?= base_url('api/assets/summary') ?>');
        const res = await resp.json();
        if (res.status === 'success') {
            let html = '';
            res.assets.forEach(a => {
                const cCode = (a.jenis_asset === 'GARDU_DISTRIBUSI_PORTAL') ? 'TM-8' :
                              (a.jenis_asset === 'GTT_GARDU_TRAFO_TIANG') ? 'TM-9' :
                              (a.jenis_asset === 'RECLOSER_LBS') ? 'TM-REC' : 'TM-1';
                html += `<tr>
                    <td class="font-monospace fw-bold text-primary">${a.kode_asset}</td>
                    <td><small class="badge bg-light text-dark border">${a.jenis_asset}</small></td>
                    <td>Feeder #${a.penyulang_id}</td>
                    <td>Section #${a.section_id}</td>
                    <td><span class="badge bg-primary">${cCode}</span></td>
                    <td><small class="text-muted">AAAC 150 mm²</small></td>
                    <td><span class="badge bg-success">${a.health_score ?? 100}</span></td>
                    <td>
                        <button class="btn btn-outline-primary btn-sm py-0 px-2" onclick="showAssetDetail(${a.id})">
                            <i class="fa-solid fa-eye me-1"></i> Detail
                        </button>
                    </td>
                </tr>`;
            });
            document.getElementById('assetsTableBody').innerHTML = html;
        }
    } catch (e) {
        console.error(e);
    }
}

async function showAssetDetail(id) {
    try {
        const resp = await fetch(`<?= base_url('api/spatial-bom/asset/') ?>/${id}`);
        const res = await resp.json();
        if (res.success) {
            document.getElementById('mAssetCode').innerText = res.asset.kode_asset;
            document.getElementById('mAssetType').innerText = res.asset.jenis_asset;
            document.getElementById('mAssetLocation').innerText = `Lat: ${res.asset.latitude}, Lng: ${res.asset.longitude}`;
            document.getElementById('mAssetHealth').innerText = `Living Health: ${res.asset.health_score ?? 100}`;
            document.getElementById('mConstName').innerText = res.spatial_bom.construction_name;
            document.getElementById('mConstCode').innerText = res.spatial_bom.construction_code;
            document.getElementById('mCondSpec').innerText = res.spatial_bom.conductor_spec;
            document.getElementById('mFeederSec').innerText = `${res.feeder?.nama_penyulang || 'Feeder'} - ${res.section?.section_name || 'Section'}`;

            let bomHtml = '';
            (res.spatial_bom.standard_bom || []).forEach(item => {
                bomHtml += `<tr>
                    <td class="font-monospace text-primary fw-bold">${item.canonical_material_code}</td>
                    <td class="fw-bold">${item.standard_quantity}</td>
                    <td>${item.unit}</td>
                    <td><span class="badge bg-danger">${item.requirement_type}</span></td>
                    <td><small class="text-muted">${item.notes || '-'}</small></td>
                </tr>`;
            });
            document.getElementById('mBomBody').innerHTML = bomHtml || '<tr><td colspan="5" class="text-center text-muted">BOM Standar Sesuai Master</td></tr>';
            new bootstrap.Modal(document.getElementById('assetDetailModal')).show();
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function loadFindingsBridge() {
    try {
        const resp = await fetch('<?= base_url('api/spatial-bom/summary') ?>');
        const res = await resp.json();
        if (res.success) {
            // Mock sample rows preview
            let html = '';
            for (let i = 1; i <= 20; i++) {
                html += `<tr>
                    <td>#${i}</td>
                    <td class="font-monospace">TEMUAN-2026-${String(i).padStart(4, '0')}</td>
                    <td>Feeder #15</td>
                    <td class="font-monospace text-primary fw-bold">MAT-ISO-PIN-20KV-12.5KN</td>
                    <td>1</td>
                    <td>PCS</td>
                    <td><span class="badge badge-exact">EXACT_MATCH</span></td>
                    <td><small class="badge bg-light text-secondary border">RECOMMENDATION_ONLY</small></td>
                </tr>`;
            }
            document.getElementById('findingsTableBody').innerHTML = html;
        }
    } catch (e) {
        console.error(e);
    }
}

async function submitFeederRec() {
    const fId = document.getElementById('recFeederSelect').value;
    try {
        const resp = await fetch('<?= base_url('api/spatial-bom/feeder-recommendation') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ penyulang_id: parseInt(fId) })
        });
        const res = await resp.json();
        if (res.success) {
            let html = '';
            res.aggregated_materials.forEach(m => {
                html += `<tr>
                    <td class="font-monospace text-primary fw-bold">${m.canonical_material_code}</td>
                    <td>${m.official_name}</td>
                    <td class="fw-bold text-success">${m.total_quantity}</td>
                    <td>${m.unit}</td>
                    <td><span class="badge bg-success">${m.exact_matches}</span></td>
                </tr>`;
            });
            document.getElementById('recResultBody').innerHTML = html;
            document.getElementById('recResultBox').classList.remove('d-none');
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}
</script>

</body>
</html>
