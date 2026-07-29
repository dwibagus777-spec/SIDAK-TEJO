<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>GIS Network Intelligence Center<?= $this->endSection() ?>
<?= $this->section('page_title') ?>SIDAK TEJO GIS Network Intelligence & Power Grid Topology<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Leaflet, MarkerCluster, Draw & Heatmap CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />

<style>
    .gis-ni-container {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    #gis-master-wrapper {
        position: relative;
        width: 100%;
        height: calc(100vh - 120px);
        min-height: 560px;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
    }

    #gisMap {
        width: 100%;
        height: 100%;
        z-index: 1;
    }

    /* Floating Panel Left */
    .gis-panel-left {
        position: absolute;
        top: 16px;
        left: 16px;
        z-index: 1000;
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(12px);
        border-radius: 16px;
        padding: 16px;
        width: 360px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(226, 232, 240, 0.8);
    }

    /* Floating Controls Right */
    .gis-panel-right {
        position: absolute;
        top: 16px;
        right: 16px;
        z-index: 1000;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        border-radius: 14px;
        padding: 10px 14px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }

    /* Floating Bottom Stats & Outage Impact Bar */
    .gis-panel-bottom {
        position: absolute;
        bottom: 20px;
        left: 16px;
        z-index: 1000;
        background: rgba(15, 23, 42, 0.92);
        backdrop-filter: blur(10px);
        color: #ffffff;
        border-radius: 14px;
        padding: 10px 18px;
        font-size: 12px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    }

    /* Animated Power Flow Polylines */
    .power-flow-line {
        stroke-dasharray: 10, 10;
        animation: dash-flow 1.5s linear infinite;
    }
    @keyframes dash-flow {
        from { stroke-dashoffset: 20; }
        to { stroke-dashoffset: 0; }
    }

    /* Blinking Emergency Marker */
    .blink-emg-marker {
        animation: pulse-red-gis 1.2s infinite;
    }
    @keyframes pulse-red-gis {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }

    /* Mobile Bottom Sheet */
    .mobile-bottom-sheet {
        position: absolute;
        bottom: 0; left: 0; right: 0;
        z-index: 1050;
        background: #ffffff;
        border-top-left-radius: 24px;
        border-top-right-radius: 24px;
        padding: 20px;
        box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.25);
        display: none;
    }
</style>

<div class="gis-ni-container container-fluid py-3">

    <!-- Top Header Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap: 8px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center">
                <i class="fas fa-network-wired text-warning me-2 fs-3"></i> GIS NETWORK INTELLIGENCE CENTER
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">ENTERPRISE V21</span>
            </h3>
            <p class="text-muted small mb-0">Topology Grid 20KV, Flow Direction, Outage Impact Simulation, Live GPS Tracking & Spatial Heatmap</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" id="btn-mission-mode" class="btn btn-outline-danger btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-triangle-exclamation me-1"></i> Mission Mode (Emergency Only)
            </button>
            <button type="button" id="btn-locate-me" class="btn btn-primary btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-location-crosshairs me-1"></i> Live GPS Posisi Saya
            </button>
        </div>
    </div>

    <!-- GIS Master Wrapper -->
    <div id="gis-master-wrapper">

        <!-- Floating Left Search & Multi-Layer Panel -->
        <div class="gis-panel-left d-none d-md-block">
            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-magnifying-glass text-primary me-1"></i> Network Search & Multi-Layer</h6>
            
            <div class="mb-2">
                <input type="text" id="gis-search-input" class="form-control form-control-sm" placeholder="Cari No Tiang, Trafo, Penyulang, Section, QR, NOGA...">
            </div>

            <!-- Multi-Layer Switches -->
            <div class="p-2 bg-light rounded-3 mb-2 border" style="font-size: 11px;">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" id="layer-grid-topology" checked>
                    <label class="form-check-label fw-bold text-dark" for="layer-grid-topology"><i class="fas fa-diagram-project text-success me-1"></i> Power Grid Topology & Flow</label>
                </div>
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" id="layer-temuan" checked>
                    <label class="form-check-label fw-bold text-dark" for="layer-temuan"><i class="fas fa-list-check text-primary me-1"></i> Pin Temuan Inspeksi</label>
                </div>
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" id="layer-assets" checked>
                    <label class="form-check-label fw-bold text-dark" for="layer-assets"><i class="fas fa-boxes-stacked text-warning me-1"></i> Master Assets & Digital Twin</label>
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="layer-live-officers" checked>
                    <label class="form-check-label fw-bold text-dark" for="layer-live-officers"><i class="fas fa-user-shield text-info me-1"></i> Live Officers GPS</label>
                </div>
            </div>

            <!-- Time Filter Slider -->
            <small class="fw-bold text-secondary d-block mb-1">Time Filter:</small>
            <div class="btn-group btn-group-sm w-100 mb-2">
                <button type="button" class="btn btn-outline-primary active">Hari Ini</button>
                <button type="button" class="btn btn-outline-primary">7 Hari</button>
                <button type="button" class="btn btn-outline-primary">30 Hari</button>
            </div>
        </div>

        <!-- Floating Right Controls (Heatmap, Drone Mode, 3D Ready) -->
        <div class="gis-panel-right d-flex gap-2 align-items-center">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="toggle-heatmap" style="cursor: pointer;">
                <label class="form-check-label fw-bold text-dark small" for="toggle-heatmap"><i class="fas fa-fire text-danger me-1"></i> Risk Heatmap</label>
            </div>
            <div class="vr"></div>
            <button type="button" id="btn-drone-mode" class="btn btn-xs btn-outline-info rounded-pill"><i class="fas fa-plane-up me-1"></i> Drone Mode</button>
        </div>

        <!-- Floating Bottom Outage Impact Bar -->
        <div class="gis-panel-bottom d-flex align-items-center gap-3">
            <div><i class="fas fa-tower-cell text-warning me-1"></i> Total Pin: <strong id="stat-total-pins" class="text-white">0</strong></div>
            <div class="vr bg-secondary"></div>
            <div><i class="fas fa-triangle-exclamation text-danger me-1"></i> Outage Impact: <strong class="text-warning">Penyulang Klurak (1,250 Pelanggan)</strong></div>
        </div>

        <!-- Leaflet Map Container -->
        <div id="gisMap"></div>

        <!-- Mobile Bottom Sheet Details -->
        <div id="gis-bottom-sheet" class="mobile-bottom-sheet">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-dark mb-0" id="bs-title">Detail Node GIS</h6>
                <button type="button" class="btn-close" id="btn-close-bs"></button>
            </div>
            <div id="bs-content" style="font-size: 12px;"></div>
            <div class="mt-3 text-end">
                <a href="#" id="bs-route-btn" target="_blank" class="btn btn-primary btn-sm rounded-pill font-weight-bold px-3">
                    <i class="fas fa-location-arrow me-1"></i> Rute Google Maps
                </a>
            </div>
        </div>

    </div>

</div>

<!-- Leaflet, MarkerCluster, Heatmap & Draw JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Leaflet Map Initializer
    var map = L.map('gisMap').setView([-7.4478, 112.7183], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; SIDAK TEJO Network Intelligence'
    }).addTo(map);

    var markerCluster = L.markerClusterGroup();
    var topologyLayerGroup = L.layerGroup().addTo(map);
    var officersLayerGroup = L.layerGroup().addTo(map);

    // Fetch GIS API Data
    fetch("<?= site_url('gis/api-data') ?>")
        .then(res => res.json())
        .then(data => {
            if (data.stats) {
                var el = document.getElementById('stat-total-pins');
                if (el) el.innerText = data.stats.total_pins;
            }

            // Render Power Grid Topology Lines & Flow Animation
            if (data.gridTopology && data.gridTopology.lines) {
                data.gridTopology.lines.forEach(function(line) {
                    L.polyline([line.from, line.to], {
                        color: line.color,
                        weight: 4,
                        opacity: 0.85,
                        className: 'power-flow-line'
                    }).addTo(topologyLayerGroup).bindPopup('<b>' + line.label + '</b><br>Status: ' + line.status);
                });

                // Topology Nodes (GI, Penyulang, Section, LBS, Trafo, Tiang)
                data.gridTopology.nodes.forEach(function(node) {
                    L.circleMarker([node.lat, node.lng], {
                        radius: 8,
                        fillColor: node.color,
                        color: '#ffffff',
                        weight: 2,
                        fillOpacity: 1
                    }).addTo(topologyLayerGroup).bindPopup('<b>' + node.name + ' (' + node.type + ')</b><br>Status: ' + node.status);
                });
            }

            // Render Live Officers GPS Pins
            if (data.liveOfficers) {
                data.liveOfficers.forEach(function(off) {
                    L.marker([off.lat, off.lng]).addTo(officersLayerGroup)
                        .bindPopup('<b>' + off.nama + '</b><br><small>' + off.role + ' &middot; ' + off.status + '</small>');
                });
            }

            // Render Temuan Pins
            if (data.temuanPins) {
                data.temuanPins.forEach(function(pin) {
                    var color = '#10b981';
                    if (pin.prioritas === 'EMERGENCY') color = '#ef4444';
                    else if (pin.prioritas === 'HIGH') color = '#f59e0b';

                    var marker = L.circleMarker([pin.latitude, pin.longitude], {
                        radius: 7, fillColor: color, color: '#fff', weight: 2, fillOpacity: 0.9
                    });

                    marker.on('click', function() {
                        showBottomSheet(pin.nomor_temuan, pin.detail_temuan, pin.latitude, pin.longitude);
                    });

                    markerCluster.addLayer(marker);
                });
                map.addLayer(markerCluster);
            }
        });

    function showBottomSheet(title, desc, lat, lng) {
        var bs = document.getElementById('gis-bottom-sheet');
        var bsTitle = document.getElementById('bs-title');
        var bsContent = document.getElementById('bs-content');
        var bsRoute = document.getElementById('bs-route-btn');

        if (bs && bsTitle && bsContent) {
            bsTitle.innerText = title;
            bsContent.innerHTML = desc;
            if (bsRoute) bsRoute.href = "https://www.google.com/maps/search/?api=1&query=" + lat + "," + lng;
            bs.style.display = 'block';
        }
    }

    var btnCloseBs = document.getElementById('btn-close-bs');
    if (btnCloseBs) {
        btnCloseBs.addEventListener('click', function() {
            var bs = document.getElementById('gis-bottom-sheet');
            if (bs) bs.style.display = 'none';
        });
    }
});
</script>
<?= $this->endSection() ?>
