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

    /* Construction-Specific SVG/HTML Marker Icons */
    .custom-gis-div-icon {
        background: transparent;
        border: none;
    }
    .gis-construction-marker {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 11px;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.25);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .gis-construction-marker:hover {
        transform: scale(1.25);
        z-index: 9999 !important;
    }

    /* Shapes based on construction type */
    .shape-pole {
        width: 28px; height: 28px;
        border-radius: 50%; /* Circle for Tiang */
    }
    .shape-gtt {
        width: 28px; height: 28px;
        border-radius: 6px; /* Square/Shield for GTT */
    }
    .shape-gardu {
        width: 32px; height: 26px;
        border-radius: 4px; /* Rectangular Building for Gardu */
    }
    .shape-kubikel {
        width: 26px; height: 30px;
        border-radius: 5px; /* Cubicle Panel */
    }
    .shape-trafo {
        width: 28px; height: 28px;
        transform: rotate(45deg); /* Diamond for Trafo */
    }
    .shape-trafo i {
        transform: rotate(-45deg);
    }
    .shape-generic {
        width: 28px; height: 28px;
        border-radius: 50%;
    }

    /* Secondary Inspection State Accent Borders & Badges */
    .state-pending {
        background-color: #0284c7;
        border: 2px solid #ffffff;
    }
    .state-pass {
        background-color: #10b981;
        border: 2px solid #ffffff;
    }
    .state-fail {
        background-color: #ef4444;
        border: 2px solid #ffffff;
    }
    .state-current-target {
        background-color: #f59e0b;
        border: 3px solid #ffffff;
        animation: target-pulse 1.5s infinite;
    }

    @keyframes target-pulse {
        0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
        100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
    }

    .marker-seq-badge {
        position: absolute;
        top: -8px; right: -8px;
        background: #0f172a;
        color: #f59e0b;
        font-size: 9px;
        font-weight: bold;
        padding: 1px 4px;
        border-radius: 8px;
        border: 1px solid #f59e0b;
        line-height: 1;
        z-index: 10;
    }
    .marker-status-badge {
        position: absolute;
        bottom: -4px; right: -4px;
        width: 13px; height: 13px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        border: 1px solid #fff;
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
                            <option value="<?= $p['id'] ?>" <?= (isset($selectedPenyulangId) && (int)$selectedPenyulangId === (int)$p['id']) ? 'selected' : '' ?>>
                                <?= esc($p['nama_penyulang']) ?> (<?= esc($p['nama_ulp'] ?: 'ULP') ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Feeder Inspection Journey Card Panel -->
            <div id="feeder-inspection-card" class="p-3 bg-white rounded-3 border border-info shadow-sm mb-2" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="fw-bold text-primary font-monospace" id="fic-planning-no">PLN-20260809-001</small>
                    <span class="badge bg-warning text-dark font-weight-bold" id="fic-progress-badge">0 / 0 Selesai</span>
                </div>
                <h6 class="fw-bold text-dark mb-1" style="font-size: 13px;" id="fic-title">Planning Inspections</h6>
                <div class="progress mb-2" style="height: 6px;">
                    <div class="progress-bar bg-success" id="fic-progress-bar" role="progressbar" style="width: 0%;"></div>
                </div>
                <div class="p-2 bg-light rounded border" style="font-size: 11px;">
                    <span class="text-muted d-block" style="font-size: 10px;">Target Inspeksi Saat Ini:</span>
                    <strong class="text-dark font-monospace d-block" id="fic-current-target">#001 GDG-001</strong>
                </div>
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
    var currentFeederId = <?= json_encode((int)($selectedPenyulangId ?? 0)) ?> || null;
    var currentPlanningId = <?= json_encode((int)($selectedPlanningId ?? 0)) ?> || null;
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

    // Release D: Construction-Specific HTML DivIcon Marker Builder
    function createConstructionIcon(m) {
        var shapeClass = 'shape-' + (m.shape || 'generic');
        var statusClass = 'state-' + String(m.inspection_status || 'PENDING').toLowerCase().replace('_', '-');
        var iconClass = m.icon_class || 'fas fa-location-dot';

        var badgeHtml = '';
        if (m.inspection_status === 'CURRENT_TARGET') {
            badgeHtml = `<span class="marker-seq-badge">#${String(m.sequence_no).padStart(3, '0')}</span>`;
        } else if (m.inspection_status === 'PASS') {
            badgeHtml = `<span class="marker-status-badge bg-success"><i class="fas fa-check" style="font-size:7px;"></i></span>`;
        } else if (m.inspection_status === 'FAIL') {
            badgeHtml = `<span class="marker-status-badge bg-danger"><i class="fas fa-exclamation" style="font-size:7px;"></i></span>`;
        }

        var html = `
            <div class="gis-construction-marker ${shapeClass} ${statusClass}" title="${m.shape_label || ''}: ${m.kode_asset}">
                <i class="${iconClass}"></i>
                ${badgeHtml}
            </div>
        `;

        var iconSize = (m.shape === 'gardu') ? [32, 26] : (m.shape === 'kubikel') ? [26, 30] : [28, 28];
        return L.divIcon({
            className: 'custom-gis-div-icon',
            html: html,
            iconSize: iconSize,
            iconAnchor: [iconSize[0]/2, iconSize[1]/2]
        });
    }

    // Load Feeder Network Transline & Bounds
    function loadFeederNetwork(feederId, planningId) {
        if (!feederId) return;
        currentFeederId = feederId;
        if (planningId) currentPlanningId = planningId;
        translinePolylineLayer.clearLayers();

        var url = "<?= site_url('master-assets/feeder-network') ?>?penyulang_id=" + feederId;
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.penyulang) {
                        document.getElementById('stat-feeder-name').innerText = data.penyulang.nama_penyulang;
                    }
                    if (data.planning) {
                        currentPlanningId = data.planning.id;
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

    // Viewport Bounding Box Asset Loader with Full Feeder Planning Context
    function loadViewportAssets() {
        if (!currentFeederId) return;
        var bounds = map.getBounds();
        var url = `<?= site_url('master-assets/feeder-assets') ?>?penyulang_id=${currentFeederId}&min_lat=${bounds.getSouth()}&max_lat=${bounds.getNorth()}&min_lng=${bounds.getWest()}&max_lng=${bounds.getEast()}`;
        if (currentPlanningId) {
            url += `&planning_id=${currentPlanningId}`;
        }

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' && data.markers) {
                    markerCluster.clearLayers();
                    currentVisibleMarkers = data.markers;
                    document.getElementById('stat-total-pins').innerText = data.total_planned || data.count;

                    if (data.planning) {
                        var fic = document.getElementById('feeder-inspection-card');
                        if (fic) {
                            fic.style.display = 'block';
                            document.getElementById('fic-planning-no').innerText = data.planning.nomor_planning;
                            document.getElementById('fic-title').innerText = data.planning.title;
                            var inspected = data.inspected_count || 0;
                            var total = data.total_planned || data.count;
                            document.getElementById('fic-progress-badge').innerText = inspected + ' / ' + total + ' Selesai';
                            var pct = total > 0 ? Math.round((inspected / total) * 100) : 0;
                            document.getElementById('fic-progress-bar').style.width = pct + '%';
                        }
                    }

                    var currentTargetObj = null;

                    data.markers.forEach(function(m) {
                        var icon = createConstructionIcon(m);
                        var marker = L.marker([m.lat, m.lng], { icon: icon });

                        if (m.inspection_status === 'CURRENT_TARGET') {
                            currentTargetObj = m;
                        }

                        marker.on('click', function() {
                            openAssetBottomSheet(m);
                        });

                        markerCluster.addLayer(marker);
                    });

                    if (currentTargetObj) {
                        document.getElementById('fic-current-target').innerText = '#' + String(currentTargetObj.sequence_no).padStart(3, '0') + ' ' + currentTargetObj.kode_asset + ' (' + currentTargetObj.jenis + ')';
                    } else if (data.inspected_count === data.total_planned && data.total_planned > 0) {
                        document.getElementById('fic-current-target').innerText = '🎉 Seluruh penyulang selesai diinspeksi!';
                    }

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
            document.getElementById('na-jenis').innerText = (nearest.shape_label || nearest.jenis) + ' • ' + nearest.nama_asset;
            document.getElementById('na-status').innerText = nearest.inspection_status || nearest.status;
            document.getElementById('na-distance').innerText = nearest.calculated_distance + ' m dari posisi Anda';
            
            var naUrl = `<?= site_url('inspections/start-by-asset') ?>?asset_id=${nearest.id}`;
            if (nearest.planning_id) {
                naUrl += `&planning_id=${nearest.planning_id}`;
            }
            document.getElementById('na-inspect-btn').href = naUrl;
        }
    }

    function openAssetBottomSheet(m) {
        var distText = userCoords ? getHaversineDistance(userCoords.lat, userCoords.lng, m.lat, m.lng) + ' m' : '-';
        document.getElementById('bs-seq').innerText = '#' + String(m.sequence_no).padStart(3, '0');
        document.getElementById('bs-kode').innerText = m.kode_asset;
        document.getElementById('bs-nama').innerText = (m.shape_label || m.jenis) + ' • ' + m.nama_asset;
        document.getElementById('bs-distance').innerText = distText;
        
        var inspectUrl = `<?= site_url('inspections/start-by-asset') ?>?asset_id=${m.id}`;
        if (m.planning_id) {
            inspectUrl += `&planning_id=${m.planning_id}`;
        }
        document.getElementById('bs-inspect-action').href = inspectUrl;
        document.getElementById('gis-bottom-sheet').style.display = 'block';
    }

    document.getElementById('bs-close-btn').addEventListener('click', function() {
        document.getElementById('gis-bottom-sheet').style.display = 'none';
    });

    document.getElementById('feeder-select').addEventListener('change', function(e) {
        var feederId = e.target.value;
        if (feederId) {
            loadFeederNetwork(feederId, null);
        }
    });

    map.on('moveend zoomend', function() {
        if (currentFeederId) {
            loadViewportAssets();
        }
    });

    // Auto-select Feeder and Planning on page load if parameters passed
    if (currentFeederId) {
        loadFeederNetwork(currentFeederId, currentPlanningId);
    }

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
