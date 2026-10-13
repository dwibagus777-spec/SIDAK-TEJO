<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>GIS Decision Cockpit — SIDAK TEJO<?= $this->endSection() ?>
<?= $this->section('page_title') ?>GIS Decision Cockpit (6-Layer Spatial Operational Intelligence)<?= $this->endSection() ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    .cockpit-card {
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: 16px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
    }
    #gis-map {
        height: 650px;
        width: 100%;
        border-radius: 12px;
        z-index: 1;
    }
    .badge-cat-EXCELLENT { background-color: #10b981; color: #fff; }
    .badge-cat-GOOD { background-color: #3b82f6; color: #fff; }
    .badge-cat-FAIR { background-color: #f59e0b; color: #000; }
    .badge-cat-POOR { background-color: #f97316; color: #fff; }
    .badge-cat-CRITICAL { background-color: #ef4444; color: #fff; animation: pulse-red 2s infinite; }

    @keyframes pulse-red {
        0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
        100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }

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
            <span class="badge bg-primary px-3 py-2 rounded-pill"><i class="fas fa-layer-group me-1"></i> B8.4 COCKPIT</span>
            <div>
                <h5 class="mb-0 fw-bold">GIS Spatial Decision Cockpit</h5>
                <small class="text-muted">Snapshot ID: <code><?= esc($snapshot_id) ?></code> | Master Topology Invariant: <span class="badge bg-success">Δ topology = 0</span></small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
            <button class="btn btn-sm btn-outline-secondary" onclick="reloadSpatialData()"><i class="fas fa-sync-alt me-1"></i> Refresh Layers</button>
            <a href="<?= base_url('sld/cockpit') ?>" class="btn btn-sm btn-dark"><i class="fas fa-project-diagram me-1"></i> Switch to SLD Cockpit</a>
        </div>
    </div>

    <!-- 6-Layer Toggle Bar -->
    <div class="cockpit-card p-3 mb-3">
        <div class="row align-items-center">
            <div class="col-md-8">
                <span class="fw-bold me-2 text-secondary">Toggle Overlay Layers:</span>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="layer-base" checked onchange="toggleLayers()">
                    <label class="form-check-label fw-semibold" for="layer-base"><i class="fas fa-draw-polygon text-secondary me-1"></i> L1: Base Topology</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="layer-ahi" checked onchange="toggleLayers()">
                    <label class="form-check-label fw-semibold" for="layer-ahi"><i class="fas fa-heartbeat text-danger me-1"></i> L2: AHI Heatmap</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="layer-fli" checked onchange="toggleLayers()">
                    <label class="form-check-label fw-semibold" for="layer-fli"><i class="fas fa-bolt text-warning me-1"></i> L3: FLI Projections</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="layer-findings" checked onchange="toggleLayers()">
                    <label class="form-check-label fw-semibold" for="layer-findings"><i class="fas fa-exclamation-triangle text-info me-1"></i> L4: Field Defects</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="layer-remediation" checked onchange="toggleLayers()">
                    <label class="form-check-label fw-semibold" for="layer-remediation"><i class="fas fa-tools text-primary me-1"></i> L5: Remediation WP</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="layer-material" checked onchange="toggleLayers()">
                    <label class="form-check-label fw-semibold" for="layer-material"><i class="fas fa-boxes text-success me-1"></i> L6: Material Requisition</label>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <span class="badge bg-light text-dark border p-2"><i class="fas fa-fingerprint me-1 text-muted"></i> Fingerprint: <span id="spatial-fingerprint-lbl">Computing...</span></span>
            </div>
        </div>
    </div>

    <!-- Main Spatial Map Container -->
    <div class="cockpit-card p-2">
        <div id="gis-map"></div>
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
    let map, assetMarkersGroup, translineGroup;
    let rawGeojsonData = null;

    document.addEventListener("DOMContentLoaded", function () {
        initMap();
        reloadSpatialData();
    });

    function initMap() {
        // Initialize Leaflet map centered at East Java (Surabaya / Sidoarjo)
        map = L.map('gis-map').setView([-7.4500, 112.7200], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap | SIDAK TEJO Decision Cockpit'
        }).addTo(map);

        translineGroup = L.layerGroup().addTo(map);
        assetMarkersGroup = L.layerGroup().addTo(map);
    }

    function reloadSpatialData() {
        fetch('<?= base_url("gis/cockpit/spatial-geojson") ?>')
            .then(res => res.json())
            .then(data => {
                rawGeojsonData = data;
                document.getElementById('spatial-fingerprint-lbl').innerText = (data.spatial_fingerprint || '').substring(0, 12) + '...';
                renderLayers();
            })
            .catch(err => {
                console.error("Failed to load GIS spatial GeoJSON:", err);
            });
    }

    function renderLayers() {
        if (!rawGeojsonData || !rawGeojsonData.features) return;

        assetMarkersGroup.clearLayers();
        translineGroup.clearLayers();

        const showBase = document.getElementById('layer-base').checked;
        const showAhi = document.getElementById('layer-ahi').checked;
        const showFli = document.getElementById('layer-fli').checked;
        const showFindings = document.getElementById('layer-findings').checked;
        const showRemediation = document.getElementById('layer-remediation').checked;
        const showMaterial = document.getElementById('layer-material').checked;

        const bounds = [];

        rawGeojsonData.features.forEach(feat => {
            const props = feat.properties;

            if (feat.geometry.type === 'Point' && showBase) {
                const coords = feat.geometry.coordinates; // [lng, lat]
                const latLng = [coords[1], coords[0]];
                bounds.push(latLng);

                // Determine color badge by AHI category
                let cat = props.health_category || 'GOOD';
                let color = '#3b82f6';
                if (cat === 'EXCELLENT') color = '#10b981';
                if (cat === 'FAIR') color = '#f59e0b';
                if (cat === 'POOR') color = '#f97316';
                if (cat === 'CRITICAL') color = '#ef4444';

                const circleMarker = L.circleMarker(latLng, {
                    radius: 8,
                    fillColor: showAhi ? color : '#64748b',
                    color: '#ffffff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.85
                });

                let popupText = `<strong>${props.nama_asset}</strong><br>` +
                    `<small>Code: ${props.kode_asset}</small><br>` +
                    `<small>AHI Score: <strong>${props.ahi_score}</strong> (${cat})</small><br>`;

                if (showFindings && props.finding_count > 0) {
                    popupText += `<span class="badge bg-warning text-dark mt-1"><i class="fas fa-exclamation-circle me-1"></i> ${props.finding_count} Findings</span><br>`;
                }
                if (showRemediation && props.remediation_package) {
                    popupText += `<span class="badge bg-primary text-white mt-1"><i class="fas fa-tools me-1"></i> WP #${props.remediation_package.id} (${props.remediation_package.status})</span><br>`;
                }
                if (showMaterial && props.material_requisition) {
                    popupText += `<span class="badge bg-success text-white mt-1"><i class="fas fa-boxes me-1"></i> REQ #${props.material_requisition.id} (${props.material_requisition.status})</span><br>`;
                }

                popupText += `<button class="btn btn-xs btn-outline-primary mt-2 w-100" onclick="open360Drawer(${props.asset_id})">Inspect 360° Drawer</button>`;

                circleMarker.bindPopup(popupText);
                assetMarkersGroup.addLayer(circleMarker);

            } else if (feat.geometry.type === 'LineString' && showBase) {
                const coords = feat.geometry.coordinates.map(c => [c[1], c[0]]);
                coords.forEach(c => bounds.push(c));

                const polyline = L.polyline(coords, {
                    color: '#0284c7',
                    weight: 3,
                    opacity: 0.8
                });
                polyline.bindPopup(`<strong>Transline Edge: ${props.kode_transline}</strong><br><small>Feeder: ${props.nama_penyulang || 'N/A'}</small><br><small>Length: ${props.panjang_m}m</small>`);
                translineGroup.addLayer(polyline);
            }
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [30, 30] });
        }
    }

    function toggleLayers() {
        renderLayers();
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
