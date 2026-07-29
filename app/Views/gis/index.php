<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>GIS Network Intelligence Center Enterprise<?= $this->endSection() ?>
<?= $this->section('page_title') ?>SIDAK TEJO Enterprise Intelligent GIS & Power Grid Topology<?= $this->endSection() ?>

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
        min-height: 580px;
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
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        border-radius: 16px;
        padding: 16px;
        width: 360px;
        max-height: calc(100% - 32px);
        overflow-y: auto;
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

    /* Floating Bottom Stats Bar */
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

    /* Blinking Emergency Marker */
    .pulse-emg-marker {
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
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">ENTERPRISE V40</span>
            </h3>
            <p class="text-muted small mb-0">Smart Cluster Markers, Heatmap Density, Radius Buffer Search, Live GPS Tracking, & Multi-Tile Maps</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" id="btn-mission-mode" class="btn btn-outline-danger btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-triangle-exclamation me-1"></i> Mission Mode (Emergency Only)
            </button>
            <button type="button" id="btn-locate-me" class="btn btn-primary btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-location-crosshairs me-1"></i> Posisi Saya
            </button>
        </div>
    </div>

    <!-- GIS Master Wrapper -->
    <div id="gis-master-wrapper">

        <!-- Floating Left Search, Filters & Layers Panel -->
        <div class="gis-panel-left d-none d-md-block">
            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-magnifying-glass text-primary me-1"></i> Live Search & Radius Search</h6>
            
            <div class="mb-2">
                <input type="text" id="gis-search-input" class="form-control form-control-sm" placeholder="Cari No Temuan, Penyulang, Section...">
            </div>

            <!-- Draw Radius Buffer Dropdown -->
            <div class="row g-2 mb-2">
                <div class="col-7">
                    <select id="radius-select" class="form-select form-select-sm">
                        <option value="0">-- Filter Radius --</option>
                        <option value="250">Radius 250m</option>
                        <option value="500">Radius 500m</option>
                        <option value="1000">Radius 1 km</option>
                        <option value="3000">Radius 3 km</option>
                        <option value="5000">Radius 5 km</option>
                    </select>
                </div>
                <div class="col-5">
                    <button type="button" id="btn-reset-filter" class="btn btn-outline-secondary btn-sm w-100">Reset</button>
                </div>
            </div>

            <!-- Multi-Layer Switches -->
            <div class="p-2 bg-light rounded-3 mb-2 border" style="font-size: 11px;">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" id="layer-grid-topology" checked>
                    <label class="form-check-label fw-bold text-dark" for="layer-grid-topology"><i class="fas fa-diagram-project text-success me-1"></i> Power Grid Topology</label>
                </div>
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" id="layer-temuan" checked>
                    <label class="form-check-label fw-bold text-dark" for="layer-temuan"><i class="fas fa-list-check text-primary me-1"></i> Pin Temuan Inspeksi</label>
                </div>
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" id="layer-assets" checked>
                    <label class="form-check-label fw-bold text-dark" for="layer-assets"><i class="fas fa-boxes-stacked text-warning me-1"></i> Master Assets</label>
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="layer-live-officers" checked>
                    <label class="form-check-label fw-bold text-dark" for="layer-live-officers"><i class="fas fa-user-shield text-info me-1"></i> Live Officers GPS</label>
                </div>
            </div>
        </div>

        <!-- Floating Right Controls (Tile Layer Switcher & Heatmap) -->
        <div class="gis-panel-right d-flex gap-2 align-items-center">
            <select id="tile-switcher" class="form-select form-select-sm border-0 bg-transparent fw-bold text-primary" style="font-size: 12px; cursor: pointer;">
                <option value="street">🗺️ Street Map</option>
                <option value="satellite">🛰️ Satellite View</option>
                <option value="dark">🌙 Dark Mode Map</option>
            </select>
        </div>

        <!-- Floating Bottom Stats Bar -->
        <div class="gis-panel-bottom d-flex align-items-center gap-3">
            <div><i class="fas fa-tower-cell text-warning me-1"></i> Total Marker: <strong id="stat-total-pins" class="text-white">0</strong></div>
            <div class="vr bg-secondary"></div>
            <div><i class="fas fa-triangle-exclamation text-danger me-1"></i> Emergency: <strong class="text-danger" id="stat-emg-count">0</strong></div>
        </div>

        <!-- Leaflet Map Container -->
        <div id="gisMap"></div>

        <!-- Mobile Bottom Sheet Details -->
        <div id="gis-bottom-sheet" class="mobile-bottom-sheet">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-dark mb-0" id="bs-title">Detail Marker GIS</h6>
                <button type="button" class="btn-close" id="btn-close-bs"></button>
            </div>
            <div id="bs-content" style="font-size: 12px;"></div>
            <div class="mt-3 text-end d-flex gap-2 justify-content-end">
                <a href="#" id="bs-waze-btn" target="_blank" class="btn btn-outline-info btn-sm rounded-pill font-weight-bold">
                    <i class="fas fa-waze me-1"></i> Waze
                </a>
                <a href="#" id="bs-route-btn" target="_blank" class="btn btn-primary btn-sm rounded-pill font-weight-bold px-3">
                    <i class="fas fa-location-arrow me-1"></i> Google Maps
                </a>
            </div>
        </div>

    </div>

</div>

<!-- Leaflet, MarkerCluster, Heatmap & Draw JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Tile Layers
    var streetTile = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; SIDAK TEJO GIS' });
    var satelliteTile = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: '&copy; Esri Satellite' });
    var darkTile = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { attribution: '&copy; CartoDB Dark' });

    var map = L.map('gisMap', {
        center: [-7.4478, 112.7183],
        zoom: 12,
        layers: [streetTile]
    });

    // Tile Switcher Listener
    document.getElementById('tile-switcher').addEventListener('change', function(e) {
        var val = e.target.value;
        map.removeLayer(streetTile);
        map.removeLayer(satelliteTile);
        map.removeLayer(darkTile);

        if (val === 'satellite') satelliteTile.addTo(map);
        else if (val === 'dark') darkTile.addTo(map);
        else streetTile.addTo(map);
    });

    var markerCluster = L.markerClusterGroup();
    var topologyLayerGroup = L.layerGroup().addTo(map);
    var officersLayerGroup = L.layerGroup().addTo(map);
    var userLocationMarker = null;

    // Fetch GIS API Data
    fetch("<?= site_url('gis/api-data') ?>")
        .then(res => res.json())
        .then(data => {
            if (data.stats) {
                document.getElementById('stat-total-pins').innerText = data.stats.total_pins || 0;
            }

            // Render Temuan Pins with Smart Colors & MarkerCluster
            var emgCount = 0;
            if (data.temuanPins) {
                data.temuanPins.forEach(function(pin) {
                    var color = '#10b981'; // Selesai / Green
                    var prio = (pin.prioritas || '').toUpperCase();
                    var status = (pin.status || '').toUpperCase();

                    if (status === 'SELESAI') color = '#10b981';
                    else if (status === 'PROSES') color = '#3b82f6';
                    else if (prio === 'EMERGENCY') { color = '#ef4444'; emgCount++; }
                    else if (prio === 'HIGH') color = '#f97316';
                    else if (prio === 'MEDIUM') color = '#f59e0b';

                    var marker = L.circleMarker([pin.latitude, pin.longitude], {
                        radius: 8, fillColor: color, color: '#ffffff', weight: 2, fillOpacity: 0.9
                    });

                    marker.on('click', function() {
                        showBottomSheet(pin.nomor_temuan, pin.detail_temuan, pin.latitude, pin.longitude);
                    });

                    markerCluster.addLayer(marker);
                });
                map.addLayer(markerCluster);
            }
            document.getElementById('stat-emg-count').innerText = emgCount;

            // Render Topology Lines & Nodes
            if (data.gridTopology && data.gridTopology.lines) {
                data.gridTopology.lines.forEach(function(line) {
                    L.polyline([line.from, line.to], { color: line.color, weight: 4, opacity: 0.85 }).addTo(topologyLayerGroup);
                });
            }
        });

    // Live GPS Location Tracking Button
    document.getElementById('btn-locate-me').addEventListener('click', function() {
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                var lat = pos.coords.latitude;
                var lng = pos.coords.longitude;

                if (userLocationMarker) map.removeLayer(userLocationMarker);
                userLocationMarker = L.circleMarker([lat, lng], {
                    radius: 10, fillColor: '#3b82f6', color: '#ffffff', weight: 3, fillOpacity: 1
                }).addTo(map).bindPopup('<b>Posisi Anda Saat Ini</b>').openPopup();

                map.flyTo([lat, lng], 15);
            }, function(err) {
                alert("GPS tidak tersedia atau belum diizinkan.");
            });
        }
    });

    function showBottomSheet(title, desc, lat, lng) {
        var bs = document.getElementById('gis-bottom-sheet');
        var bsTitle = document.getElementById('bs-title');
        var bsContent = document.getElementById('bs-content');
        var bsRoute = document.getElementById('bs-route-btn');
        var bsWaze = document.getElementById('bs-waze-btn');

        if (bs && bsTitle && bsContent) {
            bsTitle.innerText = title;
            bsContent.innerHTML = desc;
            if (bsRoute) bsRoute.href = "https://www.google.com/maps/dir/?api=1&destination=" + lat + "," + lng;
            if (bsWaze) bsWaze.href = "https://waze.com/ul?ll=" + lat + "," + lng + "&navigate=yes";
            bs.style.display = 'block';
        }
    }

    document.getElementById('btn-close-bs').addEventListener('click', function() {
        document.getElementById('gis-bottom-sheet').style.display = 'none';
    });
});
</script>
<?= $this->endSection() ?>
