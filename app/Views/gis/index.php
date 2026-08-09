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
            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-network-wired text-primary me-1"></i> Pilih Feeder / Penyulang</h6>
            
            <div class="mb-2">
                <select id="feeder-select" class="form-select form-select-sm fw-bold text-primary border-primary">
                    <option value="">-- Pilih Penyulang (GI -> Ujung) --</option>
                    <?php if (!empty($penyulangs)): ?>
                        <?php foreach ($penyulangs as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= esc($p['nama_penyulang']) ?> (<?= esc($p['nama_ulp'] ?: 'ULP') ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="mb-2">
                <input type="text" id="gis-search-input" class="form-control form-control-sm" placeholder="Cari Kode Asset, Penyulang, Section...">
            </div>

            <!-- Multi-Layer Switches -->
            <div class="p-2 bg-light rounded-3 mb-2 border" style="font-size: 11px;">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" id="layer-transline-poly" checked>
                    <label class="form-check-label fw-bold text-dark" for="layer-transline-poly"><i class="fas fa-route text-primary me-1"></i> Feeder Transline Polyline</label>
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="layer-viewport-assets" checked>
                    <label class="form-check-label fw-bold text-dark" for="layer-viewport-assets"><i class="fas fa-location-dot text-warning me-1"></i> Viewport Asset Markers</label>
                </div>
            </div>

            <!-- Nearest Asset Card Panel -->
            <div id="nearest-asset-card" class="p-3 bg-white rounded-3 border border-primary shadow-sm" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge bg-primary rounded-pill font-monospace" id="na-seq">#001</span>
                    <span class="badge bg-success" id="na-status">AKTIF</span>
                </div>
                <h6 class="fw-bold text-dark mb-0" id="na-kode">GDG-001</h6>
                <small class="text-muted d-block mb-2" id="na-jenis">Tiang Sutet</small>
                <div class="d-flex align-items-center text-info small fw-bold mb-2">
                    <i class="fas fa-location-arrow me-1"></i> <span id="na-distance">18 m dari posisi Anda</span>
                </div>
                <a href="#" id="na-inspect-btn" class="btn btn-sm btn-primary w-100 rounded-pill font-weight-bold">
                    <i class="fas fa-clipboard-check me-1"></i> MULAI INSPEKSI
                </a>
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
            <div><i class="fas fa-tower-cell text-warning me-1"></i> Asset Visible: <strong id="stat-total-pins" class="text-white">0</strong></div>
            <div class="vr bg-secondary"></div>
            <div><i class="fas fa-route text-success me-1"></i> Feeder: <strong class="text-success" id="stat-feeder-name">Semua</strong></div>
        </div>

        <!-- Leaflet Map Container -->
        <div id="gisMap"></div>

        <!-- Mobile Bottom Sheet for Tap Asset -->
        <div class="mobile-bottom-sheet" id="gis-bottom-sheet">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <span class="badge bg-primary font-monospace" id="bs-seq">#001</span>
                    <h6 class="fw-bold mb-0 d-inline ms-1" id="bs-kode">GDG-001</h6>
                </div>
                <button type="button" class="btn-close" id="bs-close-btn"></button>
            </div>
            <p class="text-muted small mb-2" id="bs-nama">Tiang 20kV Gedangan</p>
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-info small fw-bold"><i class="fas fa-location-arrow me-1"></i><span id="bs-distance">12 m</span></span>
                <a href="#" id="bs-inspect-action" class="btn btn-primary btn-sm rounded-pill font-weight-bold px-3">
                    <i class="fas fa-play me-1"></i> MULAI INSPEKSI
                </a>
            </div>
        </div>

    </div>

</div>

<!-- Leaflet, MarkerCluster JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/leaflet.markercluster.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var streetTile = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; SIDAK TEJO GIS' });
    var satelliteTile = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: '&copy; Esri Satellite' });
    var darkTile = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { attribution: '&copy; CartoDB Dark' });

    var map = L.map('gisMap', {
        center: [-7.4478, 112.7183],
        zoom: 12,
        layers: [streetTile]
    });

    document.getElementById('tile-switcher').addEventListener('change', function(e) {
        var val = e.target.value;
        map.removeLayer(streetTile);
        map.removeLayer(satelliteTile);
        map.removeLayer(darkTile);

        if (val === 'satellite') satelliteTile.addTo(map);
        else if (val === 'dark') darkTile.addTo(map);
        else streetTile.addTo(map);
    });

    // Defensive MarkerCluster Capability Detection
    var markerCluster = (typeof L.markerClusterGroup === 'function')
        ? L.markerClusterGroup({ disableClusteringAtZoom: 16 })
        : L.layerGroup();
    var translinePolylineLayer = L.layerGroup().addTo(map);
    var userLocationMarker = null;
    var currentFeederId = null;
    var userCoords = null;
    var currentVisibleMarkers = [];

    // Haversine Distance Formula in meters
    function getHaversineDistance(lat1, lon1, lat2, lon2) {
        var R = 6371000;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLon = (lon2 - lon1) * Math.PI / 180;
        var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon/2) * Math.sin(dLon/2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return Math.round(R * c);
    }

    // Load Feeder Network Transline & Bounds
    function loadFeederNetwork(feederId) {
        if (!feederId) return;
        currentFeederId = feederId;
        translinePolylineLayer.clearLayers();

        fetch("<?= site_url('master-assets/feeder-network') ?>?penyulang_id=" + feederId)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.penyulang) {
                        document.getElementById('stat-feeder-name').innerText = data.penyulang.nama_penyulang;
                    }

                    // Render 1 Single LineString Transline Polyline Layer
                    if (data.transline && data.transline.geometry && data.transline.geometry.coordinates.length > 0) {
                        var latLngs = data.transline.geometry.coordinates.map(c => [c[1], c[0]]);
                        var polyline = L.polyline(latLngs, {
                            color: '#0284c7',
                            weight: 5,
                            opacity: 0.85,
                            lineJoin: 'round'
                        });
                        translinePolylineLayer.addLayer(polyline);
                    }

                    // Fit Map Bounds
                    if (data.bbox) {
                        map.fitBounds([
                            [data.bbox.min_lat, data.bbox.min_lng],
                            [data.bbox.max_lat, data.bbox.max_lng]
                        ], { padding: [40, 40] });
                    }

                    // Trigger Viewport Markers Load
                    loadViewportAssets();
                }
            });
    }

    // Viewport Bounding Box Asset Loader
    function loadViewportAssets() {
        if (!currentFeederId) return;
        var bounds = map.getBounds();
        var url = `<?= site_url('master-assets/feeder-assets') ?>?penyulang_id=${currentFeederId}&min_lat=${bounds.getSouth()}&max_lat=${bounds.getNorth()}&min_lng=${bounds.getWest()}&max_lng=${bounds.getEast()}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' && data.markers) {
                    markerCluster.clearLayers();
                    currentVisibleMarkers = data.markers;
                    document.getElementById('stat-total-pins').innerText = data.count;

                    data.markers.forEach(function(m) {
                        var color = m.color || '#10b981';
                        var marker = L.circleMarker([m.lat, m.lng], {
                            radius: 7,
                            fillColor: color,
                            color: '#ffffff',
                            weight: 2,
                            fillOpacity: 0.9
                        });

                        marker.on('click', function() {
                            openAssetBottomSheet(m);
                        });

                        markerCluster.addLayer(marker);
                    });

                    map.addLayer(markerCluster);
                    updateNearestAsset();
                }
            });
    }

    // Nearest Asset Calculator
    function updateNearestAsset() {
        if (!userCoords || currentVisibleMarkers.length === 0) return;

        var minDistance = Infinity;
        var nearest = null;

        currentVisibleMarkers.forEach(function(m) {
            var dist = getHaversineDistance(userCoords.lat, userCoords.lng, m.lat, m.lng);
            if (dist < minDistance) {
                minDistance = dist;
                nearest = m;
                nearest.calculated_distance = dist;
            }
        });

        if (nearest) {
            var card = document.getElementById('nearest-asset-card');
            card.style.display = 'block';
            document.getElementById('na-seq').innerText = '#' + String(nearest.sequence_no).padStart(3, '0');
            document.getElementById('na-kode').innerText = nearest.kode_asset;
            document.getElementById('na-jenis').innerText = nearest.jenis + ' • ' + nearest.nama_asset;
            document.getElementById('na-status').innerText = nearest.status;
            document.getElementById('na-distance').innerText = nearest.calculated_distance + ' m dari posisi Anda';
            document.getElementById('na-inspect-btn').href = `<?= site_url('inspections/start') ?>?asset_id=${nearest.id}`;
        }
    }

    function openAssetBottomSheet(m) {
        var distText = userCoords ? getHaversineDistance(userCoords.lat, userCoords.lng, m.lat, m.lng) + ' m' : '-';
        document.getElementById('bs-seq').innerText = '#' + String(m.sequence_no).padStart(3, '0');
        document.getElementById('bs-kode').innerText = m.kode_asset;
        document.getElementById('bs-nama').innerText = m.jenis + ' • ' + m.nama_asset;
        document.getElementById('bs-distance').innerText = distText;
        document.getElementById('bs-inspect-action').href = `<?= site_url('inspections/start') ?>?asset_id=${m.id}`;
        document.getElementById('gis-bottom-sheet').style.display = 'block';
    }

    document.getElementById('bs-close-btn').addEventListener('click', function() {
        document.getElementById('gis-bottom-sheet').style.display = 'none';
    });

    document.getElementById('feeder-select').addEventListener('change', function(e) {
        var feederId = e.target.value;
        if (feederId) {
            loadFeederNetwork(feederId);
        }
    });

    map.on('moveend zoomend', function() {
        if (currentFeederId) {
            loadViewportAssets();
        }
    });

    // Live GPS Location Tracking
    document.getElementById('btn-locate-me').addEventListener('click', function() {
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                userCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                if (userLocationMarker) map.removeLayer(userLocationMarker);
                userLocationMarker = L.circleMarker([userCoords.lat, userCoords.lng], {
                    radius: 10, fillColor: '#3b82f6', color: '#ffffff', weight: 3, fillOpacity: 1
                }).addTo(map);

                map.setView([userCoords.lat, userCoords.lng], 16);
                updateNearestAsset();
            });
        }
    });
});
</script>
<?= $this->endSection() ?>
