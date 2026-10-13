<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>SLD Decision Cockpit — SIDAK TEJO<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Single Line Diagram Decision Cockpit (Interactive Vector Topology)<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    .cockpit-card {
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: 16px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
    }
    #sld-canvas-container {
        width: 100%;
        min-height: 600px;
        background: #0f172a;
        border-radius: 12px;
        position: relative;
        overflow: auto;
        padding: 24px;
    }
    .node-box {
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .node-box:hover {
        transform: scale(1.05);
        filter: brightness(1.2);
    }
    .badge-cat-EXCELLENT { background-color: #10b981; color: #fff; }
    .badge-cat-GOOD { background-color: #3b82f6; color: #fff; }
    .badge-cat-FAIR { background-color: #f59e0b; color: #000; }
    .badge-cat-POOR { background-color: #f97316; color: #fff; }
    .badge-cat-CRITICAL { background-color: #ef4444; color: #fff; }

    /* 360 Context Drawer */
    .drawer-overlay {
        position: fixed;
        top: 0; right: -450px; width: 450px; height: 100vh;
        background: #ffffff;
        box-shadow: -5px 0 25px rgba(0,0,0,0.15);
        z-index: 9999;
        transition: right 0.3s ease-in-out;
        overflow-y: auto;
        padding: 24px;
    }
    .drawer-overlay.active {
        right: 0;
    }
</style>

<div class="container-fluid py-3">

    <!-- Header Control Bar -->
    <div class="cockpit-card p-3 mb-3 d-flex flex-wrap align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-dark px-3 py-2 rounded-pill"><i class="fas fa-project-diagram me-1"></i> B8.4 SLD COCKPIT</span>
            <div>
                <h5 class="mb-0 fw-bold">Single Line Diagram Decision Cockpit</h5>
                <small class="text-muted">Snapshot ID: <code><?= esc($snapshot_id) ?></code> | Master Topology Invariant: <span class="badge bg-success">Δ topology = 0</span></small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
            <button class="btn btn-sm btn-outline-secondary" onclick="reloadSldData()"><i class="fas fa-sync-alt me-1"></i> Refresh Topology</button>
            <a href="<?= base_url('gis/cockpit') ?>" class="btn btn-sm btn-primary"><i class="fas fa-map-marked-alt me-1"></i> Switch to GIS Cockpit</a>
        </div>
    </div>

    <!-- Summary & Layer Bar -->
    <div class="cockpit-card p-3 mb-3">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="fw-bold me-3 text-secondary">SLD Topology Stats:</span>
                <span class="badge bg-primary px-2 py-1 me-2" id="stat-nodes">Nodes: 0</span>
                <span class="badge bg-info px-2 py-1 me-2" id="stat-edges">Edges: 0</span>
            </div>
            <div class="col-md-5 text-end">
                <span class="badge bg-light text-dark border p-2"><i class="fas fa-fingerprint me-1 text-muted"></i> Fingerprint: <span id="sld-fingerprint-lbl">Computing...</span></span>
            </div>
        </div>
    </div>

    <!-- Main SLD Vector Topology Container -->
    <div class="cockpit-card p-2">
        <div id="sld-canvas-container">
            <svg id="sld-svg" width="100%" height="600" style="min-width: 900px;"></svg>
        </div>
    </div>

</div>

<!-- 360-Degree Asset Context Drawer -->
<div id="asset-drawer" class="drawer-overlay">
    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h5 class="fw-bold mb-0 text-primary"><i class="fas fa-microscope me-2"></i> 360° Asset Context</h5>
        <button type="button" class="btn-close" onclick="closeDrawer()"></button>
    </div>
    <div id="drawer-content">
        <div class="text-center py-5 text-muted">
            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
            <p>Loading Asset 360° Intelligence Context...</p>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        reloadSldData();
    });

    function reloadSldData() {
        fetch('<?= base_url("sld/cockpit/topology-overlay") ?>')
            .then(res => res.json())
            .then(data => {
                document.getElementById('stat-nodes').innerText = `Nodes: ${data.summary.total_nodes}`;
                document.getElementById('stat-edges').innerText = `Edges: ${data.summary.total_edges}`;
                document.getElementById('sld-fingerprint-lbl').innerText = (data.topology_fingerprint || '').substring(0, 12) + '...';
                renderSldGraph(data.nodes, data.edges);
            })
            .catch(err => {
                console.error("Failed to load SLD topology overlay:", err);
            });
    }

    function renderSldGraph(nodes, edges) {
        const svg = document.getElementById('sld-svg');
        svg.innerHTML = ''; // Clear SVG

        if (!nodes || nodes.length === 0) {
            svg.innerHTML = `<text x="50%" y="50%" fill="#64748b" text-anchor="middle" font-size="16">No topology nodes found.</text>`;
            return;
        }

        // Draw main Feeder Backbone Bus Line
        const busLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        busLine.setAttribute('x1', '50');
        busLine.setAttribute('y1', '100');
        busLine.setAttribute('x2', '850');
        busLine.setAttribute('y2', '100');
        busLine.setAttribute('stroke', '#38bdf8');
        busLine.setAttribute('stroke-width', '6');
        svg.appendChild(busLine);

        // Render nodes along bus line in grid format
        const cols = 5;
        const spacingX = 160;
        const spacingY = 120;
        const startX = 80;
        const startY = 180;

        nodes.forEach((node, index) => {
            const col = index % cols;
            const row = Math.floor(index / cols);

            const x = startX + col * spacingX;
            const y = startY + row * spacingY;

            // Drop line from bus bar
            const dropLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            dropLine.setAttribute('x1', x);
            dropLine.setAttribute('y1', '100');
            dropLine.setAttribute('x2', x);
            dropLine.setAttribute('y2', y);
            dropLine.setAttribute('stroke', '#475569');
            dropLine.setAttribute('stroke-width', '2');
            dropLine.setAttribute('stroke-dasharray', '4');
            svg.appendChild(dropLine);

            // Determine color by AHI Category
            let cat = node.health_category || 'GOOD';
            let color = '#3b82f6';
            if (cat === 'EXCELLENT') color = '#10b981';
            if (cat === 'FAIR') color = '#f59e0b';
            if (cat === 'POOR') color = '#f97316';
            if (cat === 'CRITICAL') color = '#ef4444';

            // Group Node Container
            const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            g.setAttribute('class', 'node-box');
            g.setAttribute('onclick', `open360Drawer(${node.asset_id})`);

            // Node Circle Symbol
            const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            circle.setAttribute('cx', x);
            circle.setAttribute('cy', y);
            circle.setAttribute('r', '22');
            circle.setAttribute('fill', color);
            circle.setAttribute('stroke', '#ffffff');
            circle.setAttribute('stroke-width', '3');
            g.appendChild(circle);

            // Node AHI Score Text
            const textScore = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            textScore.setAttribute('x', x);
            textScore.setAttribute('y', y + 4);
            textScore.setAttribute('fill', '#ffffff');
            textScore.setAttribute('font-size', '11');
            textScore.setAttribute('font-weight', 'bold');
            textScore.setAttribute('text-anchor', 'middle');
            textScore.textContent = Math.round(node.ahi_score);
            g.appendChild(textScore);

            // Node Label Below
            const textLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            textLabel.setAttribute('x', x);
            textLabel.setAttribute('y', y + 40);
            textLabel.setAttribute('fill', '#cbd5e1');
            textLabel.setAttribute('font-size', '11');
            textLabel.setAttribute('text-anchor', 'middle');
            textLabel.textContent = (node.label || node.code || '').substring(0, 15);
            g.appendChild(textLabel);

            svg.appendChild(g);
        });

        // Set SVG container height dynamically
        const totalRows = Math.ceil(nodes.length / cols);
        const calcHeight = Math.max(600, startY + totalRows * spacingY + 50);
        svg.setAttribute('height', calcHeight);
    }

    function open360Drawer(assetId) {
        const drawer = document.getElementById('asset-drawer');
        const content = document.getElementById('drawer-content');
        drawer.classList.add('active');

        content.innerHTML = `<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-primary mb-2"></div><p>Loading Asset 360° Context...</p></div>`;

        fetch(`<?= base_url('gis/cockpit/asset-profile/') ?>/${assetId}`)
            .then(res => res.json())
            .then(res => {
                if (!res.success) {
                    content.innerHTML = `<div class="alert alert-danger">${res.message}</div>`;
                    return;
                }

                const a = res.asset;
                const ahi = res.ahi_intelligence;
                const fli = res.fli_fault_projection;

                let html = `
                    <div class="mb-3">
                        <span class="badge bg-secondary mb-1">${a.jenis_asset}</span>
                        <h4 class="fw-bold text-dark mb-0">${a.nama_asset}</h4>
                        <small class="text-muted">Code: <code>${a.kode_asset}</code> | ULP: ${a.unit_up}</small>
                    </div>

                    <div class="card bg-light border-0 rounded-3 mb-3 p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted d-block">AHI Health Score</small>
                                <span class="display-6 fw-bold text-primary">${ahi.health_score}</span>
                            </div>
                            <span class="badge badge-cat-${ahi.classification || 'GOOD'} fs-6 px-3 py-2">${ahi.classification || 'GOOD'}</span>
                        </div>
                    </div>

                    <h6 class="fw-bold text-secondary mb-2"><i class="fas fa-shield-alt me-1"></i> Provenance & Context</h6>
                    <ul class="list-group list-group-flush mb-3 small">
                        <li class="list-group-item d-flex justify-content-between"><span>Snapshot ID</span> <code>${res.snapshot_id}</code></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Fingerprint</span> <code>${(res.context_fingerprint || '').substring(0, 10)}...</code></li>
                        <li class="list-group-item d-flex justify-content-between"><span>FLI Fault Hypothesis</span> <span>${fli ? 'Candidate Point (' + fli.distance_m + 'm)' : 'None'}</span></li>
                    </ul>

                    <h6 class="fw-bold text-secondary mb-2"><i class="fas fa-exclamation-circle me-1"></i> Active Findings (${(res.findings || []).length})</h6>
                    <div class="mb-3">
                `;

                if ((res.findings || []).length === 0) {
                    html += `<small class="text-muted">No active unresolved findings.</small>`;
                } else {
                    res.findings.forEach(f => {
                        html += `<div class="p-2 border rounded mb-1 bg-white small"><span class="badge bg-danger me-1">${f.prioritas || 'NORMAL'}</span> ${f.deskripsi_finding || f.deskripsi}</div>`;
                    });
                }

                html += `</div>
                    <h6 class="fw-bold text-secondary mb-2"><i class="fas fa-tools me-1"></i> Remediation Work Packages (${(res.remediation_packages || []).length})</h6>
                    <div class="mb-3">`;

                if ((res.remediation_packages || []).length === 0) {
                    html += `<small class="text-muted">No work package assigned.</small>`;
                } else {
                    res.remediation_packages.forEach(wp => {
                        html += `<div class="p-2 border rounded mb-1 bg-white small d-flex justify-content-between"><span><strong>${wp.package_number || 'WP-#' + wp.id}</strong> — ${wp.title || 'Remediation'}</span><span class="badge bg-info">${wp.status}</span></div>`;
                    });
                }

                html += `</div>
                    <h6 class="fw-bold text-secondary mb-2"><i class="fas fa-boxes me-1"></i> Material Requisitions (${(res.material_requisitions || []).length})</h6>
                    <div class="mb-3">`;

                if ((res.material_requisitions || []).length === 0) {
                    html += `<small class="text-muted">No material requisitions.</small>`;
                } else {
                    res.material_requisitions.forEach(mr => {
                        html += `<div class="p-2 border rounded mb-1 bg-white small d-flex justify-content-between"><span><strong>${mr.requisition_number || 'REQ-#' + mr.id}</strong></span><span class="badge bg-success">${mr.status}</span></div>`;
                    });
                }

                html += `</div>`;
                content.innerHTML = html;
            })
            .catch(err => {
                content.innerHTML = `<div class="alert alert-danger">Error loading asset 360° context drawer.</div>`;
            });
    }

    function closeDrawer() {
        document.getElementById('asset-drawer').classList.remove('active');
    }
</script>
<?= $this->endSection() ?>
