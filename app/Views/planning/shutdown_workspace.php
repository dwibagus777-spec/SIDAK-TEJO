<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Evidence-Based Shutdown Work Planning & Material Traceability Suite | SIDAK TEJO') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1e293b; }
        .workspace-header { background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%); color: #fff; padding: 22px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .card-custom { border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .workspace-title { font-size: 15px; font-weight: 700; color: #1e293b; display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .sld-box { background-color: #090d16; color: #f8fafc; border-radius: 10px; padding: 22px; min-height: 220px; font-family: 'Fira Code', monospace; }
        .sld-node-item { padding: 10px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; text-align: center; }
        .sld-normal { background-color: #1e293b; border: 1px solid #475569; color: #94a3b8; }
        .sld-shutdown { background-color: #7f1d1d; border: 2px solid #ef4444; color: #fecaca; box-shadow: 0 0 14px rgba(239,68,68,0.5); }
        .sld-cb { background-color: #0369a1; border: 2px solid #38bdf8; color: #e0f2fe; }
        .table th { background-color: #f8fafc; font-size: 11px; text-transform: uppercase; color: #475569; letter-spacing: 0.5px; }
        .table td { vertical-align: middle; font-size: 13px; }
        .evidence-tag { font-family: monospace; font-size: 11px; background-color: #f1f5f9; padding: 3px 6px; border-radius: 4px; border: 1px solid #cbd5e1; }
        .tree-node { border-left: 2px solid #cbd5e1; padding-left: 16px; margin-left: 12px; position: relative; margin-bottom: 8px; }
        .tree-node::before { content: ""; position: absolute; top: 12px; left: 0; width: 12px; height: 2px; background: #cbd5e1; }
        .nav-pills .nav-link { color: #64748b; font-size: 13px; font-weight: 600; }
        .nav-pills .nav-link.active { background-color: #4338ca; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="workspace-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="d-flex align-items-center mb-1">
                    <span class="badge bg-indigo-500 text-white me-2" style="background-color: #6366f1;">CC-06 SUITE</span>
                    <span class="badge bg-success me-2">v3.1.0 HEPTA-TIER</span>
                    <span class="badge bg-warning text-dark">EVIDENCE-BASED WORKSPACE</span>
                </div>
                <h4 class="fw-bold mb-1"><i class="fa-solid fa-layer-group text-warning me-2"></i>Evidence-Based Shutdown Work Planning & Material Traceability Suite</h4>
                <div class="text-indigo-200 small">6 Integrated Workspaces: Executive Reliability $\to$ Work Scope Planner $\to$ Dynamic SLD $\to$ Work Finding Console $\to$ Material Requirements $\to$ Material Allocation Evidence</div>
            </div>
            <div>
                <a href="<?= base_url('executive/command-center') ?>" class="btn btn-outline-light btn-sm me-2"><i class="fa-solid fa-chart-line me-1"></i> Executive CC</a>
                <a href="<?= base_url('spatial-bom') ?>" class="btn btn-outline-light btn-sm me-2"><i class="fa-solid fa-diagram-project me-1"></i> Spatial BOM</a>
            </div>
        </div>
    </div>

    <!-- WORKSPACE 1: Executive Reliability & Health Quick Bar -->
    <div class="card-custom p-3 bg-white mb-3">
        <div class="row g-3 text-center">
            <div class="col-md-3 border-end">
                <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Grid Reliability (GIRI)</small>
                <div class="h5 fw-bold text-success mb-0 mt-1"><i class="fa-solid fa-shield-halved me-1"></i> 83.4% <span class="badge bg-success-subtle text-success border border-success" style="font-size: 10px;">HIGH RELIABILITY</span></div>
            </div>
            <div class="col-md-3 border-end">
                <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Living Asset Radar</small>
                <div class="h5 fw-bold text-primary mb-0 mt-1"><i class="fa-solid fa-satellite-dish me-1"></i> 30 Monitored <span class="badge bg-primary-subtle text-primary border border-primary" style="font-size: 10px;">22 Normal / 8 Degraded</span></div>
            </div>
            <div class="col-md-3 border-end">
                <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Material Supply Readiness</small>
                <div class="h5 fw-bold text-info mb-0 mt-1"><i class="fa-solid fa-boxes-stacked me-1"></i> 88.5% <span class="badge bg-info-subtle text-info border border-info" style="font-size: 10px;">BUFFER SUFFICIENT</span></div>
            </div>
            <div class="col-md-3">
                <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Predictive OPEX/CAPEX</small>
                <div class="h5 fw-bold text-dark mb-0 mt-1"><i class="fa-solid fa-coins text-warning me-1"></i> Rp 453 Juta <span class="badge bg-warning-subtle text-dark border border-warning" style="font-size: 10px;">ESTIMATED</span></div>
            </div>
        </div>
    </div>

    <!-- WORKSPACE 2: Work Scope & Inspection Taxonomy Planner -->
    <div class="card-custom p-4">
        <div class="workspace-title">
            <span><i class="fa-solid fa-sliders text-indigo me-2" style="color: #4f46e5;"></i>WORKSPACE 2: Work Scope & Inspection Taxonomy Planner</span>
            <span class="badge bg-indigo-100 text-indigo border" style="background-color: #e0e7ff; color: #4338ca;">Target Scope Configurator</span>
        </div>
        
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Pilih Penyulang</label>
                <select id="feederSelect" class="form-select form-select-sm" onchange="loadFeederSections()">
                    <option value="15">BANJAR KEMANTREN (ID: 15 - SIDOARJO)</option>
                    <option value="1">SIWALAN PANJI (ID: 1 - SIDOARJO)</option>
                    <option value="3">SIDOMULYO (ID: 3 - SIDOARJO)</option>
                    <option value="18">KENCAR (ID: 18 - KRIAN)</option>
                    <option value="41">OSAKA (ID: 41 - PORONG)</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">Pilih Seksi Terdampak (Multi-Select)</label>
                <div id="sectionCheckboxes" class="d-flex flex-wrap gap-2 p-2 bg-light rounded border" style="max-height: 100px; overflow-y: auto;">
                    <span class="text-muted small py-1"><i class="fa-solid fa-spinner fa-spin me-1"></i> Memuat seksi jaringan...</span>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Mode Pekerjaan</label>
                <select id="workModeSelect" class="form-select form-select-sm">
                    <option value="OUTAGE_ISOLATED">OUTAGE ISOLATED (Padam)</option>
                    <option value="PDKB_HOTLINE">PDKB (Bertegangan)</option>
                    <option value="NON_OUTAGE_ONLINE">ONLINE INSPECTION</option>
                    <option value="NON_OUTAGE">NON-OUTAGE GROUND</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Scope Pekerjaan</label>
                <div class="p-2 bg-light rounded border small d-flex gap-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="scopeTemuan" value="TO_TEMUAN" checked>
                        <label class="form-check-label" for="scopeTemuan">Temuan</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="scopeGardu" value="TO_GTT_GARDU" checked>
                        <label class="form-check-label" for="scopeGardu">GTT/Gardu</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="scopePdkb" value="TO_PDKB">
                        <label class="form-check-label" for="scopePdkb">PDKB</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- 17 Canonical Inspection Tasks Accordion -->
        <label class="form-label small fw-bold mb-2">Pilih Tugas Inspeksi Standar (17 Canonical Catalog Items across 4 Domains)</label>
        <div class="row g-2 mb-3">
            <?php 
            $tasks = $catalog['inspection_tasks'] ?? [];
            $byDomain = [];
            foreach ($tasks as $t) {
                $byDomain[$t['domain']][] = $t;
            }
            foreach ($byDomain as $dom => $domTasks):
            ?>
            <div class="col-md-3">
                <div class="p-2 bg-light rounded border h-100 small">
                    <div class="fw-bold text-dark mb-1 pb-1 border-bottom d-flex justify-content-between">
                        <span><i class="fa-solid fa-bolt text-warning me-1"></i> <?= esc($dom) ?></span>
                        <span class="badge bg-secondary" style="font-size: 10px;"><?= count($domTasks) ?></span>
                    </div>
                    <?php foreach ($domTasks as $idx => $dt): ?>
                    <div class="form-check mb-1">
                        <input class="form-check-input insp-cb" type="checkbox" value="<?= esc($dt['code']) ?>" id="insp_<?= esc($dt['code']) ?>" <?= ($dom === 'JTM' && $idx === 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="insp_<?= esc($dt['code']) ?>" style="font-size: 11px;">
                            <?= esc($dt['name']) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-end">
            <button class="btn btn-primary btn-sm px-4" onclick="composePlan()"><i class="fa-solid fa-gears me-1"></i> Bangun Workspace & SLD Scope</button>
        </div>
    </div>

    <!-- WORKSPACES 3, 4, 5, 6: (Hidden until composed) -->
    <div id="workPlanResultArea" class="d-none">
        
        <!-- WORKSPACE 3: Dynamic SLD Scope Viewer -->
        <div class="card-custom p-4">
            <div class="workspace-title">
                <span><i class="fa-solid fa-network-wired text-info me-2"></i>WORKSPACE 3: Dynamic Single Line Diagram (SLD) Scope Viewer</span>
                <span class="badge bg-danger" id="sldShutdownCountBadge">2 Seksi Padam Terisolasi</span>
            </div>
            <div class="sld-box">
                <div class="text-slate-400 small mb-2"><i class="fa-solid fa-info-circle me-1"></i> Visualisasi Topologi Jalur Radial & Daerah Padam Terisolasi (Non-Switching Planning Aid):</div>
                <div class="d-flex align-items-center gap-3 overflow-auto pb-2" id="sldNodesContainer">
                    <!-- Dynamic SLD Nodes -->
                </div>
            </div>
        </div>

        <!-- WORKSPACE 4: Work Finding Console -->
        <div class="card-custom p-4">
            <div class="workspace-title">
                <span><i class="fa-solid fa-table-list text-warning me-2"></i>WORKSPACE 4: Work Finding Console (Aset & Temuan Teridentifikasi)</span>
                <div class="d-flex gap-2">
                    <select id="filterScopeCategory" class="form-select form-select-sm" style="width: 140px;" onchange="filterWorkItems()">
                        <option value="ALL">Semua Kategori</option>
                        <option value="TO_TEMUAN">TO Temuan</option>
                        <option value="TO_GTT">TO GTT</option>
                        <option value="TO_GARDU">TO Gardu</option>
                        <option value="TO_PDKB">TO PDKB</option>
                    </select>
                    <span class="badge bg-light text-dark border p-2" id="workItemsCountBadge">0 Pekerjaan</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Seksi</th>
                            <th>SLD Node</th>
                            <th>Kode Aset</th>
                            <th>Kategori / Konstruksi</th>
                            <th>Tugas Inspeksi</th>
                            <th>Nomor Temuan</th>
                            <th>Uraian Pekerjaan</th>
                            <th>Prioritas</th>
                            <th>Material Kanonikal</th>
                            <th>Qty</th>
                        </tr>
                    </thead>
                    <tbody id="workItemsTableBody"></tbody>
                </table>
            </div>
        </div>

        <!-- WORKSPACE 5: Preventive Material Requirement Console -->
        <div class="card-custom p-4">
            <div class="workspace-title">
                <span><i class="fa-solid fa-boxes-stacked text-success me-2"></i>WORKSPACE 5: Preventive Material Requirement Console</span>
                <span class="badge bg-success" id="materialTypesCountBadge">0 Jenis Material</span>
            </div>
            
            <!-- Level 1: Aggregate Needs -->
            <h6 class="fw-bold text-dark small mb-2"><i class="fa-solid fa-list-ol me-1"></i> Level 1: Total Rekapitulasi Kebutuhan Material Pekerjaan</h6>
            <div class="row g-3 mb-4" id="aggregateMaterialCards">
                <!-- Aggregate Material Cards -->
            </div>

            <!-- Level 2: Evidence Drill-Down -->
            <h6 class="fw-bold text-dark small mb-2"><i class="fa-solid fa-magnifying-glass-chart me-1"></i> Level 2: Evidence Drill-Down (Material $\to$ Work Plan $\to$ Seksi $\to$ SLD Node $\to$ Aset $\to$ Temuan)</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Kode Material SPLN</th>
                            <th>Nama Material</th>
                            <th>Total Kebutuhan</th>
                            <th>Jumlah Titik Aset</th>
                            <th>Rincian Alokasi Titik Fisik & Bukti Temuan</th>
                        </tr>
                    </thead>
                    <tbody id="drillDownTableBody"></tbody>
                </table>
            </div>
        </div>

        <!-- WORKSPACE 6: Material Allocation Evidence Console -->
        <div class="card-custom p-4">
            <div class="workspace-title">
                <span><i class="fa-solid fa-tree text-primary me-2"></i>WORKSPACE 6: Material Allocation Evidence Console (Rantai Bukti Pengajuan)</span>
                <span class="badge bg-primary" id="evidenceChainsCountBadge">0 Evidence Chains</span>
            </div>

            <div class="alert alert-indigo p-3 small mb-3 border" style="background-color: #eef2ff; border-color: #c7d2fe;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong><i class="fa-solid fa-file-signature text-primary me-1"></i> Rantai Bukti Pengajuan: "Material X $\to$ Temuan Y $\to$ Aset Z"</strong><br>
                        Setiap unit material yang diajukan memiliki bukti teknis dan justifikasi riil hingga level titik fisik aset dan node SLD.
                    </div>
                    <div>
                        <span class="badge bg-success"><i class="fa-solid fa-lock me-1"></i> RECOMMENDATION_ONLY</span>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-7">
                    <h6 class="fw-bold small text-dark mb-2"><i class="fa-solid fa-folder-tree me-1"></i> Hierarchical Material Request Tree</h6>
                    <div class="p-3 bg-light rounded border" id="hierarchicalTreeContainer" style="max-height: 380px; overflow-y: auto;">
                        <!-- Hierarchical Tree Rendered Here -->
                    </div>
                </div>
                <div class="col-md-5">
                    <h6 class="fw-bold small text-dark mb-2"><i class="fa-solid fa-fingerprint me-1"></i> Governance & Cryptographic Seal</h6>
                    <div class="p-3 bg-white rounded border small h-100">
                        <div class="mb-2"><strong>Nomor Pengajuan:</strong> <span class="font-monospace text-primary fw-bold" id="govRequestNo">-</span></div>
                        <div class="mb-2"><strong>Work Plan ID:</strong> <span class="font-monospace text-dark" id="govPlanId">-</span></div>
                        <div class="mb-2"><strong>Penyulang:</strong> <span id="govFeeder">-</span></div>
                        <div class="mb-2"><strong>Authorizer:</strong> <span>Supervisor Pemeliharaan & Manajer UP3</span></div>
                        <div class="mb-2"><strong>Action Hash:</strong> <div class="evidence-tag text-break" id="govActionHash">-</div></div>
                        <div class="mb-3"><strong>Status Keputusan:</strong> <span class="badge bg-warning text-dark">PENDING HUMAN REVIEW</span></div>
                        <div class="alert alert-secondary py-2 small mb-0">
                            <i class="fa-solid fa-ban text-danger me-1"></i> <strong>Batas Otoritas:</strong> Sistem tidak melakukan auto-switching, auto-outage, atau auto-procurement material.
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentPlanData = null;

document.addEventListener('DOMContentLoaded', () => {
    loadFeederSections();
});

async function loadFeederSections() {
    const fId = document.getElementById('feederSelect').value;
    try {
        const resp = await fetch(`<?= base_url('api/planning/feeder-sections/') ?>/${fId}`);
        const res = await resp.json();
        if (res.success) {
            let html = '';
            res.sections.forEach((s, idx) => {
                const isChecked = (idx === 0 || idx === 1) ? 'checked' : '';
                html += `
                <div class="form-check">
                    <input class="form-check-input sec-cb" type="checkbox" value="${s.section_id}" id="sec_${s.section_id}" ${isChecked}>
                    <label class="form-check-label small" for="sec_${s.section_id}">
                        <strong>${s.section_name}</strong> (${s.total_findings} temuan, ${s.total_assets} aset)
                    </label>
                </div>`;
            });
            document.getElementById('sectionCheckboxes').innerHTML = html || '<span class="text-muted small">Tidak ada seksi.</span>';
        }
    } catch (e) {
        console.error(e);
    }
}

async function composePlan() {
    const fId = parseInt(document.getElementById('feederSelect').value);
    const checkedSecs = Array.from(document.querySelectorAll('.sec-cb:checked')).map(cb => parseInt(cb.value));
    const checkedInsps = Array.from(document.querySelectorAll('.insp-cb:checked')).map(cb => cb.value);
    const workMode = document.getElementById('workModeSelect').value;

    if (checkedSecs.length === 0) {
        alert('Pilih minimal satu seksi untuk rencana pekerjaan pemadaman.');
        return;
    }

    const scopes = [];
    if (document.getElementById('scopeTemuan').checked) scopes.push('TO_TEMUAN');
    if (document.getElementById('scopeGardu').checked) scopes.push('TO_GTT_GARDU');
    if (document.getElementById('scopePdkb').checked) scopes.push('TO_PDKB');

    try {
        const resp = await fetch('<?= base_url('api/planning/compose-scope') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                penyulang_id: fId,
                section_ids: checkedSecs,
                scopes: scopes,
                inspection_work_codes: checkedInsps,
                work_mode: workMode
            })
        });
        const res = await resp.json();
        if (res.success) {
            currentPlanData = res;
            renderAllWorkspaces(res);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

function renderAllWorkspaces(data) {
    // 1. WORKSPACE 3: Render SLD
    let sldHtml = '';
    data.sld_topology.sld_nodes.forEach((n, idx) => {
        const cls = (n.type === 'CIRCUIT_BREAKER') ? 'sld-cb' : (n.is_shutdown ? 'sld-shutdown' : 'sld-normal');
        const icon = (n.type === 'CIRCUIT_BREAKER') ? 'fa-bolt' : (n.is_shutdown ? 'fa-triangle-exclamation' : 'fa-circle-check');
        const badge = n.is_shutdown ? `<span class="badge bg-warning text-dark mt-1">PADAM (${n.work_items_cnt} TO)</span>` : '<span class="badge bg-secondary mt-1">NORMAL</span>';

        sldHtml += `
        <div class="d-flex align-items-center">
            <div class="sld-node-item ${cls}" style="min-width: 150px;">
                <div><i class="fa-solid ${icon} me-1"></i> ${n.label}</div>
                ${badge}
            </div>
            ${(idx < data.sld_topology.sld_nodes.length - 1) ? '<div class="px-2 text-slate-500 fw-bold"><i class="fa-solid fa-arrow-right"></i></div>' : ''}
        </div>`;
    });
    document.getElementById('sldNodesContainer').innerHTML = sldHtml;
    document.getElementById('sldShutdownCountBadge').innerText = `${data.affected_sections} Seksi Padam Terisolasi`;

    // 2. WORKSPACE 4: Render Work Finding Console
    renderWorkItemsTable(data.work_items);

    // 3. WORKSPACE 5: Render Preventive Material Requirement Console
    renderMaterialRequirements(data.material_requirements);

    // 4. WORKSPACE 6: Render Material Allocation Evidence Console
    renderHierarchicalEvidenceTree(data);

    document.getElementById('workPlanResultArea').classList.remove('d-none');
}

function renderWorkItemsTable(items) {
    let html = '';
    items.forEach(w => {
        html += `
        <tr>
            <td class="text-center">${w.item_no}</td>
            <td>Seksi #${w.section_id}</td>
            <td class="font-monospace fw-bold text-dark" style="font-size: 11px;">${w.sld_node}</td>
            <td class="font-monospace fw-bold text-primary">${w.asset_code}</td>
            <td><small class="badge bg-light text-dark border">${w.scope_category} (${w.construction})</small></td>
            <td><span class="badge bg-indigo-100 text-primary border" style="background-color: #e0e7ff;">${w.inspection_code}</span></td>
            <td class="font-monospace">${w.nomor_temuan}</td>
            <td>${w.deskripsi}</td>
            <td><span class="badge ${w.priority === 'KRITIS' ? 'bg-danger' : 'bg-warning text-dark'}">${w.priority}</span></td>
            <td class="font-monospace fw-bold">${w.canonical_mat}</td>
            <td class="text-center fw-bold">${w.quantity} ${w.unit}</td>
        </tr>`;
    });
    document.getElementById('workItemsTableBody').innerHTML = html || '<tr><td colspan="11" class="text-center text-muted">Tidak ada temuan dalam seksi terpilih.</td></tr>';
    document.getElementById('workItemsCountBadge').innerText = `${items.length} Pekerjaan Teridentifikasi`;
}

function filterWorkItems() {
    if (!currentPlanData) return;
    const cat = document.getElementById('filterScopeCategory').value;
    if (cat === 'ALL') {
        renderWorkItemsTable(currentPlanData.work_items);
    } else {
        const filtered = currentPlanData.work_items.filter(w => w.scope_category === cat);
        renderWorkItemsTable(filtered);
    }
}

function renderMaterialRequirements(mats) {
    // Level 1: Aggregate Cards
    let cardHtml = '';
    mats.forEach(m => {
        cardHtml += `
        <div class="col-md-4">
            <div class="p-3 bg-light rounded border h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <strong class="text-dark small">${m.official_name}</strong>
                    <span class="badge bg-success fs-6">${m.total_quantity} ${m.unit}</span>
                </div>
                <div class="text-muted font-monospace mt-1" style="font-size: 11px;">${m.canonical_material_code}</div>
                <div class="mt-2 text-primary small"><i class="fa-solid fa-location-dot me-1"></i> ${m.allocated_assets_count} Titik Aset Teralokasi</div>
            </div>
        </div>`;
    });
    document.getElementById('aggregateMaterialCards').innerHTML = cardHtml;
    document.getElementById('materialTypesCountBadge').innerText = `${mats.length} Jenis Material Kanonikal`;

    // Level 2: Drill Down
    let drillHtml = '';
    mats.forEach(m => {
        let allocList = '<ul class="mb-0 ps-3 small">';
        m.drill_down_evidence.forEach(e => {
            allocList += `<li><strong>${e.allocated_quantity} ${e.unit}</strong> $\\to$ Aset <strong>${e.target_asset_code}</strong> (${e.sld_position}) karena <em>${e.source_finding_number}</em></li>`;
        });
        allocList += '</ul>';

        drillHtml += `
        <tr>
            <td class="font-monospace fw-bold">${m.canonical_material_code}</td>
            <td><strong>${m.official_name}</strong></td>
            <td class="text-center fw-bold text-success">${m.total_quantity} ${m.unit}</td>
            <td class="text-center">${m.allocated_assets_count} Titik</td>
            <td>${allocList}</td>
        </tr>`;
    });
    document.getElementById('drillDownTableBody').innerHTML = drillHtml;
}

function renderHierarchicalEvidenceTree(data) {
    let treeHtml = `
    <div class="fw-bold text-primary mb-2">
        <i class="fa-solid fa-folder-open me-1"></i> ${data.request_no} (${data.plan_id})
    </div>
    <div class="tree-node">
        <div class="fw-bold text-dark"><i class="fa-solid fa-bolt me-1"></i> Penyulang: ${data.feeder_name} (Mode: ${data.work_mode})</div>`;

    data.hierarchical_request_tree.forEach(mat => {
        treeHtml += `
        <div class="tree-node">
            <div class="fw-bold text-success"><i class="fa-solid fa-box me-1"></i> ${mat.material_name} (${mat.total_quantity} ${mat.unit})</div>`;
        mat.allocations.forEach(alloc => {
            treeHtml += `
            <div class="tree-node">
                <div class="small">
                    <strong>${alloc.qty} ${alloc.unit}</strong> $\\to$ Seksi #${alloc.section_id} $\\to$ Aset <span class="font-monospace fw-bold text-primary">${alloc.asset_code}</span> (${alloc.sld_node})
                    <br><span class="text-muted"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> Dasar Temuan: ${alloc.finding_no} | Hash: ${alloc.evidence_hash.substring(0, 16)}...</span>
                </div>
            </div>`;
        });
        treeHtml += `</div>`;
    });

    treeHtml += `</div>`;
    document.getElementById('hierarchicalTreeContainer').innerHTML = treeHtml;
    document.getElementById('evidenceChainsCountBadge').innerText = `${data.evidence_chains_count} Evidence Chains Terverifikasi`;

    // Governance info
    document.getElementById('govRequestNo').innerText = data.request_no;
    document.getElementById('govPlanId').innerText = data.plan_id;
    document.getElementById('govFeeder').innerText = data.feeder_name;
    document.getElementById('govActionHash').innerText = data.action_hash;
}
</script>

</body>
</html>
