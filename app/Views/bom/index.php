<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'JTM Construction & BOM Intelligence | SIDAK TEJO') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .table th { background-color: #f7fafc; font-size: 11px; text-transform: uppercase; color: #4a5568; }
        .table td { vertical-align: middle; font-size: 13px; }
        .governance-banner { background: #e6fffa; border-left: 4px solid #319795; padding: 12px 16px; border-radius: 4px; }
        .nav-tabs .nav-link { font-size: 13px; font-weight: 600; color: #4a5568; border: none; border-bottom: 2px solid transparent; }
        .nav-tabs .nav-link.active { color: #0d6efd; border-bottom: 2px solid #0d6efd; background: transparent; }
        .badge-cat-insulator { background-color: #e0e7ff; color: #3730a3; }
        .badge-cat-pole { background-color: #fef3c7; color: #92400e; }
        .badge-cat-conductor { background-color: #dcfce7; color: #166534; }
        .badge-cat-cable { background-color: #fae8ff; color: #86198f; }
        .badge-cat-hardware { background-color: #f1f5f9; color: #334155; }
        .badge-req-mandatory { background-color: #fee2e2; color: #991b1b; }
        .badge-req-alt { background-color: #e0f2fe; color: #075985; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-layer-group text-primary me-2"></i>JTM Construction Taxonomy & BOM Intelligence</h4>
            <div class="text-muted small">CR-07 Phase 2: Standard Construction Types, Canonical Material Master & Work Order BOM Estimator</div>
        </div>
        <div>
            <a href="<?= base_url('inspections') ?>" class="btn btn-outline-secondary btn-sm me-2"><i class="fa-solid fa-clipboard-check me-1"></i> Inspeksi</a>
            <a href="<?= base_url('assets') ?>" class="btn btn-outline-secondary btn-sm me-2"><i class="fa-solid fa-network-wired me-1"></i> Asset Truth</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#estimateModal"><i class="fa-solid fa-calculator me-1"></i> Hitung Estimasi BOM Pekerjaan</button>
        </div>
    </div>

    <!-- Governance Banner -->
    <div class="governance-banner mb-4">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-boxes-stacked fa-2x text-teal me-3" style="color: #319795;"></i>
            <div>
                <div class="fw-bold" style="color: #234e52;">🛡️ GROUP D BOM TECHNICAL FABRIC ACTIVE (CR-07)</div>
                <div class="small text-secondary">
                    Seluruh hubungan tipe konstruksi JTM, kode material kanonikal (SPLN Standard), dan alias nama lapangan dikelola melalui <strong>Group D Technical Registries</strong>.
                    Estimator material hanya menyajikan <em>rekomendasi berbasis bukti teknis</em> tanpa menjalankan pengadaan atau dispatch otomatis.
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Tipe Konstruksi JTM</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h3 class="mb-0 fw-bold text-primary"><?= (int)($summary['total_constructions'] ?? 0) ?></h3>
                    <span class="ms-2 badge bg-primary">PLN_STANDARD</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">TM-1 s.d. TM-REC Substation</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Katalog Material Kanonikal</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h3 class="mb-0 fw-bold text-success"><?= (int)($summary['total_materials'] ?? 0) ?></h3>
                    <span class="ms-2 badge bg-success">CANONICAL_CODE</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">Insulator, Tiang, Konduktor, Traves</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Kamus Alias Lapangan</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h3 class="mb-0 fw-bold text-warning text-dark"><?= (int)($summary['total_aliases'] ?? 0) ?></h3>
                    <span class="ms-2 badge bg-warning text-dark">FUZZY_RESOLVER</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">Normalisasi Istilah Teknisi</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Aset Fisik Terlindungi</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h3 class="mb-0 fw-bold text-dark">30</h3>
                    <span class="ms-2 badge bg-secondary">PRESERVED_BASE</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">Invarian Group B Terkunci</div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-3" id="bomTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="const-tab" data-bs-toggle="tab" data-bs-target="#tab-const"><i class="fa-solid fa-tower-broadcast me-1"></i> Tipe Konstruksi JTM & BOM</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="mat-tab" data-bs-toggle="tab" data-bs-target="#tab-mat"><i class="fa-solid fa-cubes me-1"></i> Master Material Kanonikal</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="alias-tab" data-bs-toggle="tab" data-bs-target="#tab-alias"><i class="fa-solid fa-spell-check me-1"></i> Uji Alias Lapangan (Fuzzy Matcher)</button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content">
        <!-- Tab 1: Konstruksi & BOM -->
        <div class="tab-pane fade show active" id="tab-const">
            <div class="row g-3">
                <?php if (!empty($summary['constructions'])): ?>
                    <?php foreach ($summary['constructions'] as $cCode => $c): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="card-custom p-3 h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-primary fs-6"><?= esc($cCode) ?></span>
                                        <span class="badge bg-light text-secondary border"><?= esc($c['angle_range']) ?></span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1"><?= esc($c['name']) ?></h6>
                                    <p class="text-muted small mb-2"><?= esc($c['description']) ?></p>
                                    <div class="small mb-1"><strong>Tiang Standar:</strong> <code class="text-dark"><?= esc($c['standard_pole']) ?></code></div>
                                </div>
                                <div class="mt-3 pt-2 border-top">
                                    <button class="btn btn-outline-primary btn-sm w-100" onclick="showBomDetail('<?= esc($cCode) ?>')">
                                        <i class="fa-solid fa-list-ol me-1"></i> Rincian Standar BOM
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab 2: Material Master -->
        <div class="tab-pane fade" id="tab-mat">
            <div class="card-custom">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Kode Material Kanonikal</th>
                                <th>Nama Material Resmi</th>
                                <th>Kategori</th>
                                <th>Spesifikasi Teknis</th>
                                <th>Satuan</th>
                                <th>Alias Lapangan Terdaftar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($summary['materials'])): ?>
                                <?php foreach ($summary['materials'] as $m): ?>
                                    <tr>
                                        <td class="font-monospace fw-bold text-primary"><?= esc($m['canonical_material_code']) ?></td>
                                        <td class="fw-bold"><?= esc($m['official_name']) ?></td>
                                        <td>
                                            <?php
                                            $cat = $m['category'];
                                            $bClass = 'badge-cat-hardware';
                                            if ($cat === 'INSULATOR') $bClass = 'badge-cat-insulator';
                                            elseif ($cat === 'STRUCTURE') $bClass = 'badge-cat-pole';
                                            elseif ($cat === 'CONDUCTOR') $bClass = 'badge-cat-conductor';
                                            elseif ($cat === 'CABLE') $bClass = 'badge-cat-cable';
                                            ?>
                                            <span class="badge <?= $bClass ?>"><?= esc($cat) ?></span>
                                        </td>
                                        <td><small class="text-muted"><?= esc($m['technical_spec']) ?></small></td>
                                        <td><span class="badge bg-light text-dark border"><?= esc($m['unit']) ?></span></td>
                                        <td>
                                            <?php foreach ($m['field_aliases'] as $al): ?>
                                                <span class="badge bg-light text-secondary border me-1 mb-1" style="font-size: 10px;"><?= esc($al) ?></span>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 3: Alias Tester -->
        <div class="tab-pane fade" id="tab-alias">
            <div class="card-custom p-4">
                <h6 class="fw-bold mb-2"><i class="fa-solid fa-spell-check text-primary me-2"></i>Penguji Resolusi Alias Material Lapangan</h6>
                <p class="text-muted small mb-3">Ketik nama slang atau istilah lapangan teknisi (misal: <em>"pin post keramik"</em>, <em>"cut out"</em>, <em>"traves 2m"</em>) untuk melihat bagaimana sistem meresolusi ke Kode Material Kanonikal.</p>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <input type="text" id="testAliasInput" class="form-control" placeholder="Contoh: PIN POST KERAMIK, KABEL BUNGKUS 150, SEKRING JTM">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100" onclick="testResolveAlias()"><i class="fa-solid fa-magnifying-glass me-1"></i> Resolusi Kode Kanonikal</button>
                    </div>
                </div>
                <div id="aliasResultBox" class="p-3 bg-light rounded border d-none">
                    <div class="fw-bold text-success mb-1" id="aliasResultTitle"></div>
                    <div class="font-monospace text-primary fs-5 mb-2" id="aliasResultCode"></div>
                    <div class="small text-muted" id="aliasResultDesc"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal BOM Detail -->
<div class="modal fade" id="bomDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="bomModalTitle">Rincian Bill of Materials (BOM)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Kode Material</th>
                                <th>Tipe Kebutuhan</th>
                                <th>Kuantitas Standar</th>
                                <th>Satuan</th>
                                <th>Keterangan Teknis</th>
                            </tr>
                        </thead>
                        <tbody id="bomModalBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Estimasi BOM -->
<div class="modal fade" id="estimateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-calculator text-primary me-2"></i>Kalkulator Estimasi Material Pekerjaan (BOM Estimator)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="estimateForm">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tipe Konstruksi JTM</label>
                            <select id="estConstCode" class="form-select form-select-sm" required>
                                <option value="TM-1">TM-1 (Tiang Tumpu Lurus)</option>
                                <option value="TM-5">TM-5 (Tiang Tarik Ganda)</option>
                                <option value="TM-8">TM-8 (Gardu Tiang Portal)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Jumlah Titik / Gawang Tiang</label>
                            <input type="number" id="estQtyPoles" class="form-control form-control-sm" value="3" min="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nomor Referensi Perintah Kerja / Temuan</label>
                        <input type="text" id="estWoRef" class="form-control form-control-sm" value="WO-HAR-JTM-2026-08-005" required>
                    </div>
                </form>
                <div id="estResultSection" class="mt-3 d-none">
                    <h6 class="fw-bold text-success mb-2"><i class="fa-solid fa-check-circle me-1"></i>Hasil Rekomendasi Estimasi Material (Non-Autonomous Evidence)</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="bg-light">
                                <tr>
                                    <th>Kode Material</th>
                                    <th>Nama Material Resmi</th>
                                    <th>Kebutuhan Total</th>
                                    <th>Satuan</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="estResultBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="submitEstimate()"><i class="fa-solid fa-cogs me-1"></i> Hitung Estimasi BOM</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
async function showBomDetail(code) {
    try {
        const resp = await fetch(`<?= base_url('api/bom/detail/') ?>/${code}`);
        const res = await resp.json();
        if (res.status === 'success') {
            document.getElementById('bomModalTitle').innerText = `Rincian BOM Konstruksi ${code} (${res.bom.bom_version})`;
            let html = '';
            res.bom.materials.forEach(m => {
                const bReq = m.requirement_type === 'MANDATORY' ? 'badge-req-mandatory' : 'badge-req-alt';
                html += `<tr>
                    <td class="font-monospace fw-bold text-primary">${m.canonical_material_code}</td>
                    <td><span class="badge ${bReq}">${m.requirement_type}</span></td>
                    <td class="fw-bold">${m.standard_quantity}</td>
                    <td><span class="badge bg-light text-dark border">${m.unit}</span></td>
                    <td><small class="text-muted">${m.notes || '-'}</small></td>
                </tr>`;
            });
            document.getElementById('bomModalBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('bomDetailModal')).show();
        } else {
            alert('BOM belum terdefinisi untuk tipe konstruksi ini.');
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function testResolveAlias() {
    const term = document.getElementById('testAliasInput').value;
    if (!term) return;

    try {
        const resp = await fetch('<?= base_url('api/bom/resolve-alias') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ term: term })
        });
        const res = await resp.json();
        const box = document.getElementById('aliasResultBox');
        if (res.success) {
            box.classList.remove('d-none');
            document.getElementById('aliasResultTitle').innerText = `🟢 RESOLVED (${res.match_type})`;
            document.getElementById('aliasResultCode').innerText = res.canonical_material_code;
            document.getElementById('aliasResultDesc').innerText = `${res.material.official_name} (${res.material.category} - Satuan: ${res.material.unit})`;
        } else {
            box.classList.remove('d-none');
            document.getElementById('aliasResultTitle').innerText = '🔴 UNRESOLVED';
            document.getElementById('aliasResultCode').innerText = 'N/A';
            document.getElementById('aliasResultDesc').innerText = res.error || 'Istilah tidak ditemukan.';
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function submitEstimate() {
    const payload = {
        construction_code: document.getElementById('estConstCode').value,
        quantity_poles: parseInt(document.getElementById('estQtyPoles').value),
        work_order_ref: document.getElementById('estWoRef').value
    };

    try {
        const resp = await fetch('<?= base_url('api/bom/estimate') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ payload: payload })
        });
        const res = await resp.json();
        if (res.success) {
            let html = '';
            res.estimated_items.forEach(item => {
                const bReq = item.requirement_type === 'MANDATORY' ? 'badge-req-mandatory' : 'badge-req-alt';
                html += `<tr>
                    <td class="font-monospace text-primary fw-bold">${item.canonical_material_code}</td>
                    <td>${item.official_name}</td>
                    <td class="fw-bold text-success">${item.total_estimated_qty}</td>
                    <td>${item.unit}</td>
                    <td><span class="badge ${bReq}">${item.requirement_type}</span></td>
                </tr>`;
            });
            document.getElementById('estResultBody').innerHTML = html;
            document.getElementById('estResultSection').classList.remove('d-none');
        } else {
            alert('Gagal: ' + (res.error || 'Unknown'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}
</script>

</body>
</html>
