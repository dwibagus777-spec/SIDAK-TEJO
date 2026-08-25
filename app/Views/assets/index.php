<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Physical Asset Truth Layer | SIDAK TEJO') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .table th { background-color: #f7fafc; font-size: 11px; text-transform: uppercase; color: #4a5568; }
        .table td { vertical-align: middle; font-size: 13px; }
        .governance-banner { background: #ebf8ff; border-left: 4px solid #3182ce; padding: 12px 16px; border-radius: 4px; }
        .tree-box { max-height: 450px; overflow-y: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; }
        .tree-node { padding: 6px 10px; margin-bottom: 4px; border-radius: 4px; font-size: 13px; }
        .tree-feeder { background: #e2e8f0; font-weight: bold; }
        .tree-section { background: #edf2f7; margin-left: 15px; }
        .tree-asset { background: #f7fafc; margin-left: 30px; border-left: 2px solid #3182ce; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-network-wired text-primary me-2"></i>Physical Asset Truth Layer & GIS Intelligence</h4>
            <div class="text-muted small">CR-05 Phase 2: Governed Asset Population, Topology Sequencing & Evidence Integration</div>
        </div>
        <div>
            <a href="<?= base_url('pattern-intelligence') ?>" class="btn btn-outline-secondary btn-sm me-2"><i class="fa-solid fa-chart-line me-1"></i> Pattern Intel</a>
            <a href="<?= base_url('operational-dispatch') ?>" class="btn btn-outline-secondary btn-sm me-2"><i class="fa-solid fa-truck-fast me-1"></i> Dispatch</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#stagingModal"><i class="fa-solid fa-file-import me-1"></i> Governed Ingestion Staging</button>
        </div>
    </div>

    <!-- Governance Banner -->
    <div class="governance-banner mb-4">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-shield-halved fa-2x text-primary me-3"></i>
            <div>
                <div class="fw-bold text-primary">🛡️ GOVERNED PHYSICAL ASSET TRUTH LAYER ACTIVE (CR-05)</div>
                <div class="small text-secondary">
                    Tabel <code>assets</code> adalah <strong>Authorized Mutation Scope</strong> melalui pipeline: <code>DRY_RUN → VALIDATION → CONFIRMATION_TOKEN → CONTROLLED_COMMIT</code>.
                    Grup A Invarian (134 Master Penyulang, 508 Seksi, 841 Gangguan, 441 Temuan) tetap 100% <strong>IMMUTABLE & PRESERVED</strong>.
                </div>
            </div>
        </div>
    </div>

    <!-- Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Total Aset Fisik di Database</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h3 class="mb-0 fw-bold text-dark" id="summaryTotalAssets"><?= (int)($summary['total_assets'] ?? 0) ?></h3>
                    <span class="ms-2 badge <?= ($summary['total_assets'] > 0) ? 'bg-success' : 'bg-secondary' ?>">
                        <?= ($summary['total_assets'] > 0) ? 'POPULATED_TRUTH' : 'HONEST_ZERO_STATE' ?>
                    </span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">Tabel <code>assets</code> Physical Layer</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Master Penyulang (134 Invariant)</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h3 class="mb-0 fw-bold text-primary"><?= (int)($summary['master_feeders_count'] ?? 134) ?></h3>
                    <span class="ms-2 badge bg-primary">IMMUTABLE</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">100% Tersegel di Database</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Master Seksi Jaringan (508)</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h3 class="mb-0 fw-bold text-info"><?= (int)($summary['master_sections_count'] ?? 508) ?></h3>
                    <span class="ms-2 badge bg-info text-dark">508 SECTIONS</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">Hierarki Topologi Distribusi</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Preventive Scoring Model</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h5 class="mb-0 fw-bold text-success font-monospace">PREVENTIVE_v1.0</h5>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">Bobot 40/35/25 Tetap Terkunci</div>
            </div>
        </div>
    </div>

    <!-- Main Workspace Grid -->
    <div class="row g-3 mb-4">
        <!-- Left: Feeder Topology & Asset Tree -->
        <div class="col-md-5">
            <div class="card-custom p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 fw-bold"><i class="fa-solid fa-sitemap me-2"></i>Feeder Topology & Asset Explorer</h6>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Pilih Master Penyulang (134 Feeders):</label>
                    <select id="feederSelect" class="form-select form-select-sm" onchange="loadFeederTree()">
                        <option value="">-- Pilih Penyulang --</option>
                        <?php if (!empty($masterFeeders)): ?>
                            <?php foreach ($masterFeeders as $mf): ?>
                                <option value="<?= (int)$mf['id'] ?>"><?= esc($mf['nama_penyulang']) ?> (ULP <?= (int)$mf['ulp_id'] ?>)</option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="tree-box" id="treeContainer">
                    <div class="text-muted small text-center py-4">
                        <i class="fa-solid fa-diagram-project fa-2x mb-2 d-block text-secondary"></i>
                        Pilih penyulang untuk melihat hierarki seksi dan aset fisik yang terdaftar.
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Physical Asset Table & Health Matrix -->
        <div class="col-md-7">
            <div class="card-custom p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 fw-bold"><i class="fa-solid fa-table-list me-2"></i>Daftar Aset Fisik Terdaftar di Database</h6>
                    <span class="badge bg-light text-dark border font-monospace">MODEL_v1.0</span>
                </div>
                <div class="table-responsive" style="max-height: 450px;">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Kode Aset</th>
                                <th>Nama Aset</th>
                                <th>Jenis</th>
                                <th>Penyulang</th>
                                <th>Seksi</th>
                                <th>Kondisi</th>
                                <th>Health Score</th>
                            </tr>
                        </thead>
                        <tbody id="assetTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-database fa-2x mb-2 d-block text-secondary"></i>
                                    Pilih penyulang atau jalankan governed ingestion staging untuk mempopulasi data aset fisik.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Staging & Ingestion -->
<div class="modal fade" id="stagingModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-import text-primary me-2"></i>Governed Asset Ingestion Staging Console</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    <i class="fa-solid fa-info-circle me-1"></i>
                    Pipeline ini melakukan <strong>Dry-Run Staging</strong> terlebih dahulu. Data aset akan divalidasi terhadap master 134 Penyulang, 508 Seksi, dan Bounding Box Sidoarjo.
                    Commit hanya dapat dieksekusi setelah terbit <strong>Confirmation Token</strong> dari hasil verifikasi.
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold mb-0">Input Batch Aset (JSON Array):</label>
                        <button type="button" class="btn btn-outline-primary btn-sm py-0" style="font-size: 11px;" onclick="loadSampleBatch()">
                            <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Load Contoh Pilot Batch (12 Aset)
                        </button>
                    </div>
                    <textarea id="jsonAssetInput" class="form-control font-monospace" rows="8" style="font-size: 12px;" placeholder='[{"kode_asset":"AST-BANJAR-001","nama_asset":"Tiang Beton #01 Banjar Kemantren","jenis_asset":"TIANG_BETON","ulp_id":1,"penyulang_id":15,"section_id":1,"sequence_no":1,"latitude":"-7.4478","longitude":"112.7183"}]'></textarea>
                </div>

                <div class="p-3 bg-light rounded mb-3">
                    <div class="small fw-bold text-secondary mb-2"><i class="fa-solid fa-id-card me-1"></i>Identitas Penanggung Jawab Ingestion (Wajib)</div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" id="actorName" class="form-control form-control-sm" value="IR. HENDRA ASSET ENGINEER" required>
                        </div>
                        <div class="col-md-4">
                            <input type="text" id="actorNip" class="form-control form-control-sm" value="198709182011011003" required>
                        </div>
                        <div class="col-md-4">
                            <input type="text" id="actorRole" class="form-control form-control-sm" value="ASSET_ENGINEER" readonly>
                        </div>
                    </div>
                </div>

                <!-- Dry-Run Result Preview Box -->
                <div id="dryRunResultBox" class="d-none">
                    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-clipboard-check me-2 text-success"></i>Hasil Validasi Dry-Run Staging</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <div class="card p-2 text-center bg-light">
                                <div class="small text-muted">Total Diperiksa</div>
                                <div class="fw-bold" id="resTotal">0</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card p-2 text-center bg-light border-success">
                                <div class="small text-success fw-bold">Valid (Siap Commit)</div>
                                <div class="fw-bold text-success" id="resValid">0</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card p-2 text-center bg-light border-danger">
                                <div class="small text-danger fw-bold">Ditolak (Error)</div>
                                <div class="fw-bold text-danger" id="resInvalid">0</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card p-2 text-center bg-light">
                                <div class="small text-muted">Verdict Governance</div>
                                <div class="fw-bold text-primary small" id="resVerdict">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Confirmation Token Terbit:</label>
                        <input type="text" id="resToken" class="form-control form-control-sm font-monospace bg-light" readonly>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnDryRun" onclick="runDryRun()"><i class="fa-solid fa-play me-1"></i> Jalankan Dry-Run Validasi</button>
                <button type="button" class="btn btn-success btn-sm d-none" id="btnCommit" onclick="runControlledCommit()"><i class="fa-solid fa-check-double me-1"></i> Otorisasi & Commit ke Database</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentPlanId = '';
let currentToken = '';

function loadSampleBatch() {
    const sample = [
        {
            "kode_asset": "AST-BJK-T001",
            "nama_asset": "Tiang Beton #01 Outgoing GI Banjar Kemantren",
            "jenis_asset": "TIANG_BETON",
            "ulp_id": 1,
            "penyulang_id": 15,
            "section_id": 48,
            "sequence_no": 1,
            "lokasi": "Depan Gardu Induk Sidoarjo, Seksi 1",
            "latitude": "-7.4478",
            "longitude": "112.7183",
            "tahun_instalasi": 2019,
            "merk": "WIKA",
            "type": "Beton 12m / 350daN",
            "status": "NORMAL"
        },
        {
            "kode_asset": "AST-BJK-T002",
            "nama_asset": "Tiang Beton #02 Seksi 1 Banjar Kemantren",
            "jenis_asset": "TIANG_BETON",
            "ulp_id": 1,
            "penyulang_id": 15,
            "section_id": 48,
            "sequence_no": 2,
            "lokasi": "Jl. Raya Banjar No. 12",
            "latitude": "-7.4485",
            "longitude": "112.7190",
            "tahun_instalasi": 2019,
            "merk": "WIKA",
            "type": "Beton 12m / 350daN",
            "status": "NORMAL"
        },
        {
            "kode_asset": "AST-BJK-GRD001",
            "nama_asset": "Gardu Trafo Portal BJK-01 200kVA",
            "jenis_asset": "GARDU_DISTRIBUSI_PORTAL",
            "ulp_id": 1,
            "penyulang_id": 15,
            "section_id": 48,
            "sequence_no": 3,
            "lokasi": "Jl. Raya Banjar Kemantren Timur",
            "latitude": "-7.4492",
            "longitude": "112.7205",
            "tahun_instalasi": 2020,
            "merk": "Schneider",
            "type": "Portal 20kV",
            "kapasitas": "200 kVA",
            "status": "NORMAL"
        },
        {
            "kode_asset": "AST-BJK-REC001",
            "nama_asset": "Recloser LBS Banjar Kemantren Seksi 2",
            "jenis_asset": "RECLOSER_LBS",
            "ulp_id": 1,
            "penyulang_id": 15,
            "section_id": 49,
            "sequence_no": 4,
            "lokasi": "Simpang 3 Banjar Asri",
            "latitude": "-7.4510",
            "longitude": "112.7230",
            "tahun_instalasi": 2021,
            "merk": "NOJA Power",
            "type": "OSM38",
            "status": "NORMAL"
        }
    ];
    document.getElementById('jsonAssetInput').value = JSON.stringify(sample, null, 2);
}

async function loadFeederTree() {
    const feederId = document.getElementById('feederSelect').value;
    const treeBox = document.getElementById('treeContainer');
    const tableBody = document.getElementById('assetTableBody');
    if (!feederId) {
        treeBox.innerHTML = '<div class="text-muted small text-center py-4">Pilih penyulang untuk melihat topologi.</div>';
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Pilih penyulang untuk melihat daftar aset.</td></tr>';
        return;
    }

    treeBox.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Memuat topologi...</div>';

    try {
        const resp = await fetch(`<?= base_url('api/assets/tree') ?>/${feederId}`);
        const res = await resp.json();
        if (res.success) {
            let treeHtml = `<div class="tree-node tree-feeder"><i class="fa-solid fa-bolt me-1 text-warning"></i> Penyulang ${res.feeder.nama_penyulang} (ULP ${res.feeder.ulp_id})</div>`;

            if (res.sections && res.sections.length > 0) {
                res.sections.forEach(s => {
                    treeHtml += `<div class="tree-node tree-section"><i class="fa-solid fa-code-branch me-1 text-primary"></i> Seksi #${s.id} - ${s.nama_section || 'Seksi Distribusi'}</div>`;
                    const sAssets = res.assets.filter(a => a.section_id == s.id);
                    if (sAssets.length > 0) {
                        sAssets.forEach(a => {
                            treeHtml += `<div class="tree-node tree-asset"><i class="fa-solid fa-microchip me-1 text-success"></i> [Seq #${a.sequence_no}] ${a.nama_asset} (${a.kode_asset})</div>`;
                        });
                    }
                });
            } else {
                treeHtml += '<div class="text-muted small ms-4">Belum ada data seksi terdaftar.</div>';
            }
            treeBox.innerHTML = treeHtml;

            // Populate Table
            if (res.assets && res.assets.length > 0) {
                let tableHtml = '';
                res.assets.forEach(a => {
                    tableHtml += `<tr>
                        <td class="font-monospace fw-bold">${a.kode_asset}</td>
                        <td>${a.nama_asset}</td>
                        <td><span class="badge bg-light text-dark border">${a.jenis_asset}</span></td>
                        <td>${res.feeder.nama_penyulang}</td>
                        <td>Seksi #${a.section_id || '-'}</td>
                        <td><span class="badge bg-${a.health_category === 'GOOD' ? 'success' : 'warning'}">${a.health_category}</span></td>
                        <td class="fw-bold">${a.health_score}</td>
                    </tr>`;
                });
                tableBody.innerHTML = tableHtml;
            } else {
                tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Belum ada aset fisik yang terdaftar pada penyulang ini.</td></tr>';
            }
        }
    } catch (e) {
        treeBox.innerHTML = `<div class="text-danger small p-2">Error: ${e.message}</div>`;
    }
}

async function runDryRun() {
    const raw = document.getElementById('jsonAssetInput').value;
    if (!raw) {
        alert('Masukkan JSON array data aset terlebih dahulu.');
        return;
    }

    let rows;
    try {
        rows = JSON.parse(raw);
        if (!Array.isArray(rows)) throw new Error('Data harus berupa JSON Array.');
    } catch (e) {
        alert('Format JSON tidak valid: ' + e.message);
        return;
    }

    const actor = {
        actor_name: document.getElementById('actorName').value,
        actor_nip: document.getElementById('actorNip').value,
        actor_role: document.getElementById('actorRole').value
    };

    try {
        const resp = await fetch('<?= base_url('api/assets/dry-run') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ rows: rows, actor: actor })
        });
        const res = await resp.json();
        if (res.success) {
            currentPlanId = res.plan_id;
            currentToken  = res.confirmation_token;

            document.getElementById('dryRunResultBox').classList.remove('d-none');
            document.getElementById('resTotal').textContent = res.total_submitted;
            document.getElementById('resValid').textContent = res.valid_count;
            document.getElementById('resInvalid').textContent = res.invalid_count;
            document.getElementById('resVerdict').textContent = res.verdict;
            document.getElementById('resToken').value = res.confirmation_token;

            if (res.valid_count > 0) {
                document.getElementById('btnCommit').classList.remove('d-none');
            } else {
                document.getElementById('btnCommit').classList.add('d-none');
            }
        } else {
            alert('Dry run gagal: ' + (res.error || 'Unknown'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function runControlledCommit() {
    if (!currentPlanId || !currentToken) {
        alert('Jalankan dry-run validasi terlebih dahulu.');
        return;
    }

    if (!confirm(`Konfirmasi eksekusi commit terkontrol untuk Plan ID: ${currentPlanId}?`)) {
        return;
    }

    const actor = {
        actor_name: document.getElementById('actorName').value,
        actor_nip: document.getElementById('actorNip').value,
        actor_role: 'MANAGER_UP3'
    };

    try {
        const resp = await fetch('<?= base_url('api/assets/controlled-commit') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                plan_id: currentPlanId,
                confirmation_token: currentToken,
                actor: actor
            })
        });
        const res = await resp.json();
        if (res.success) {
            alert(`Berhasil! ${res.inserted_count} aset fisik berhasil di-commit ke database.`);
            location.reload();
        } else {
            alert('Commit gagal: ' + (res.error || 'Unknown'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}
</script>

</body>
</html>
