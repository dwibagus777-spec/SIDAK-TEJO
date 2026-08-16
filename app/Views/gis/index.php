<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>GIS Network Intelligence<?= $this->endSection() ?>
<?= $this->section('page_title') ?>GIS NETWORK — PETA JARINGAN DISTRIBUSI 20KV<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Leaflet, MarkerCluster CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

<style>
    .gis-container {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    #gis-master-wrapper {
        position: relative;
        width: 100%;
        height: calc(100vh - 130px);
        min-height: 600px;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
        background-color: #e2e8f0;
    }

    #gisMap {
        width: 100%;
        height: 100%;
        z-index: 1;
    }

    /* Floating Filter Card Desktop */
    .gis-filter-panel {
        position: absolute;
        top: 16px;
        left: 16px;
        z-index: 1000;
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(12px);
        border-radius: 16px;
        padding: 18px;
        width: 350px;
        max-height: calc(100% - 32px);
        overflow-y: auto;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(226, 232, 240, 0.8);
        transition: all 0.3s ease;
    }

    /* Floating Top Action Bar Right */
    .gis-top-actions {
        position: absolute;
        top: 16px;
        right: 16px;
        z-index: 1000;
        display: flex;
        gap: 8px;
    }

    /* Loading Overlay */
    .gis-loading-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 2000;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        border-radius: 18px;
        transition: opacity 0.3s ease;
    }

    /* Floating Bottom Summary Pill */
    .gis-summary-bar {
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1000;
        background: rgba(15, 23, 42, 0.92);
        backdrop-filter: blur(10px);
        color: #ffffff;
        border-radius: 30px;
        padding: 8px 24px;
        font-size: 13px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.35);
        display: none;
    }

    /* Custom Div Markers */
    .custom-gis-div-icon {
        background: transparent;
        border: none;
    }
    .gis-marker-badge {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 11px;
        border-radius: 50%;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        transition: transform 0.2s ease;
    }
    .gis-marker-badge:hover {
        transform: scale(1.3);
        z-index: 9999 !important;
    }

    /* Mobile Sheet Drawer */
    @media (max-width: 767.98px) {
        .gis-filter-panel {
            top: auto;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            border-radius: 24px 24px 0 0;
            max-height: 80vh;
        }
        #gis-master-wrapper {
            height: calc(100vh - 90px);
        }
    }
</style>

<div class="gis-container container-fluid py-2">

    <!-- Compact Header Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap" style="gap: 8px;">
        <div class="d-flex align-items-center gap-2">
            <h4 class="fw-bold mb-0 text-primary d-flex align-items-center">
                <i class="fas fa-network-wired text-warning me-2 fs-4"></i> GIS NETWORK
            </h4>
            <span class="badge bg-primary rounded-pill font-weight-normal px-2 py-1" style="font-size: 11px;">PETA OPERASIONAL PLN</span>
        </div>
        
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="input-group input-group-sm" style="width: 260px;">
                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="gis-search-input" class="form-control border-start-0 ps-0" placeholder="Cari Kode Asset / Penyulang...">
            </div>
            <button type="button" id="btn-toggle-panel" class="btn btn-outline-primary btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-sliders me-1"></i> Filter Jaringan
            </button>
            <button type="button" id="btn-locate-me" class="btn btn-primary btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-crosshairs me-1"></i> Posisi Saya
            </button>
        </div>
    </div>

    <!-- GIS Master Map Wrapper -->
    <div id="gis-master-wrapper">

        <!-- Loading Spinner Overlay -->
        <div id="gis-loading-overlay" class="gis-loading-overlay" style="display: none;">
            <div class="spinner-border text-warning mb-2" role="status" style="width: 2.5rem; height: 2.5rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <span class="fw-bold font-monospace" style="font-size: 14px;">Memuat Data Jaringan GIS...</span>
        </div>

        <!-- Filter & Layer Panel (Desktop Floating Left / Mobile Bottom Sheet) -->
        <div id="gis-filter-panel" class="gis-filter-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-filter text-primary me-2"></i> Filter Jaringan PLN</h6>
                <button type="button" id="btn-close-panel" class="btn-close d-md-none" aria-label="Close"></button>
            </div>

            <!-- Step 1: Select ULP -->
            <div class="mb-3">
                <label class="small text-muted font-weight-bold d-block mb-1">1. Pilih Unit Layanan Pelanggan (ULP):</label>
                <select id="ulp-select" class="form-select form-select-sm fw-bold text-dark border-secondary">
                    <option value="">-- Semua ULP --</option>
                    <?php if (!empty($ulps)): ?>
                        <?php foreach ($ulps as $u): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= esc($u['nama_ulp']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Step 2: Select Penyulang -->
            <div class="mb-3">
                <label class="small text-muted font-weight-bold d-block mb-1">2. Pilih Penyulang / Feeder:</label>
                <select id="feeder-select" class="form-select form-select-sm fw-bold text-primary border-primary">
                    <option value="">-- Pilih Penyulang --</option>
                    <?php if (!empty($penyulangs)): ?>
                        <?php foreach ($penyulangs as $p): ?>
                            <option value="<?= $p['id'] ?>" data-ulp-id="<?= $p['ulp_id'] ?? '' ?>" <?= (isset($selectedPenyulangId) && (int)$selectedPenyulangId === (int)$p['id']) ? 'selected' : '' ?>>
                                <?= esc($p['nama_penyulang']) ?> (<?= esc($p['nama_ulp'] ?: 'ULP') ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Step 3: Layer Selection Checkboxes -->
            <div class="mb-3">
                <label class="small text-muted font-weight-bold d-block mb-1">3. Pilih Layer Peta Aset:</label>
                <div class="d-flex flex-column gap-2 bg-light p-2 rounded-3 border">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input layer-toggle" type="checkbox" id="layer-jtm" value="JTM" checked>
                        <label class="form-check-label small fw-bold text-dark" for="layer-jtm">
                            <i class="fas fa-square-poll-vertical text-info me-1"></i> JTM & Tiang
                        </label>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input layer-toggle" type="checkbox" id="layer-gardu" value="GARDU" checked>
                        <label class="form-check-label small fw-bold text-dark" for="layer-gardu">
                            <i class="fas fa-building-columns text-success me-1"></i> Gardu Distribusi
                        </label>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input layer-toggle" type="checkbox" id="layer-trafo" value="TRAFO" checked>
                        <label class="form-check-label small fw-bold text-dark" for="layer-trafo">
                            <i class="fas fa-bolt text-warning me-1"></i> Trafo Distribusi
                        </label>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input layer-toggle" type="checkbox" id="layer-switch" value="SWITCH" checked>
                        <label class="form-check-label small fw-bold text-dark" for="layer-switch">
                            <i class="fas fa-toggle-on text-danger me-1"></i> Peralatan (PMS/PMT/LBS/ACR)
                        </label>
                    </div>
                </div>
            </div>

            <!-- Step 4: Execute Action Button -->
            <button type="button" id="btn-apply-gis" class="btn btn-primary w-100 fw-bold rounded-pill shadow-sm py-2">
                <i class="fas fa-play me-2"></i> TAMPILKAN PETA JARINGAN
            </button>
        </div>

        <!-- Summary Floating Bottom Bar -->
        <div id="gis-summary-bar" class="gis-summary-bar">
            <span id="summary-text" class="fw-bold font-monospace"><i class="fas fa-network-wired text-warning me-2"></i> Memuat...</span>
        </div>

        <!-- Leaflet Map Element -->
        <div id="gisMap"></div>

    </div>

</div>

<!-- Leaflet & JS Plugins -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    // Center Coordinate (Sidoarjo Kota Default)
    var defaultLat = -7.4523;
    var defaultLng = 112.7161;

    // Initialize Leaflet Map
    var map = L.map('gisMap', {
        center: [defaultLat, defaultLng],
        zoom: 13,
        zoomControl: false
    });

    // Tile Layer: OpenStreetMap Standard
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; PLN SIDAK TEJO GIS'
    }).addTo(map);

    L.control.zoom({ position: 'topright' }).addTo(map);

    // Layer Groups & Cluster
    var markerCluster = L.markerClusterGroup({
        chunkedLoading: true,
        maxClusterRadius: 40,
        disableClusteringAtZoom: 17
    });
    map.addLayer(markerCluster);

    var translinePolylineLayer = L.featureGroup().addTo(map);
    var userLocationMarker = null;

    var currentFeederId = <?= (int)($selectedPenyulangId ?? 0) ?>;
    var rawFeatures = [];

    // Helper: Show/Hide Spinner Loading Overlay
    function toggleLoading(show) {
        document.getElementById('gis-loading-overlay').style.display = show ? 'flex' : 'none';
    }

    // Helper: Marker Spec Resolver
    function getMarkerStyle(jenis, type) {
        var j = (jenis || '').toUpperCase();
        var t = (type || '').toUpperCase();

        if (t.includes('PMS') || t.includes('PEMISAH')) {
            return { color: '#dc2626', icon: 'fa-toggle-off', label: 'PMS' };
        }
        if (t.includes('PMT') || t.includes('PEMUTUS')) {
            return { color: '#ea580c', icon: 'fa-toggle-on', label: 'PMT' };
        }
        if (t.includes('GTT') || t.includes('GARDU TRAFO')) {
            return { color: '#059669', icon: 'fa-charging-station', label: 'GTT' };
        }
        if (t.includes('TMTP') || t.includes('PORTAL')) {
            return { color: '#7c3aed', icon: 'fa-archway', label: 'TMTP' };
        }
        if (j === 'TRAFO') {
            return { color: '#d97706', icon: 'fa-bolt', label: 'TRAFO' };
        }
        if (j === 'GARDU') {
            return { color: '#0284c7', icon: 'fa-building-columns', label: 'GARDU' };
        }
        if (j === 'KUBIKEL') {
            return { color: '#475569', icon: 'fa-box-archive', label: 'KUBIKEL' };
        }
        if (j === 'LBS' || j === 'LBSM' || j === 'RECLOSER') {
            return { color: '#dc2626', icon: 'fa-toggle-on', label: j };
        }

        return { color: '#2563eb', icon: 'fa-square-poll-vertical', label: 'JTM' };
    }

    // 1. Cascading ULP -> Penyulang (AJAX Options)
    document.getElementById('ulp-select').addEventListener('change', function () {
        var selectedUlpId = this.value;
        var feederSelect = document.getElementById('feeder-select');
        
        if (!selectedUlpId) {
            // Show all options
            Array.from(feederSelect.options).forEach(opt => opt.style.display = 'block');
            feederSelect.value = '';
            return;
        }

        // Fetch Penyulangs under selected ULP
        fetch(`<?= site_url('gis/api-penyulangs') ?>?ulp_id=${selectedUlpId}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' && data.penyulangs) {
                    feederSelect.innerHTML = '<option value="">-- Pilih Penyulang --</option>';
                    data.penyulangs.forEach(p => {
                        var opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = `${p.nama_penyulang} (${p.nama_ulp || 'ULP'})`;
                        feederSelect.appendChild(opt);
                    });
                }
            });
    });

    // 2. On Demand Network Fetch (Triggered ONLY when TAMPILKAN PETA is clicked!)
    function loadGisNetworkOnDemand() {
        var feederId = document.getElementById('feeder-select').value;
        if (!feederId) {
            alert('Silakan pilih Penyulang terlebih dahulu!');
            return;
        }

        currentFeederId = feederId;
        toggleLoading(true);

        // Fetch Network GeoJSON
        fetch(`<?= site_url('gis/api-data') ?>?penyulang_id=${feederId}`)
            .then(res => res.json())
            .then(data => {
                toggleLoading(false);
                if (data.status === 'success' && data.features) {
                    rawFeatures = data.features;
                    renderFilteredLayers();
                    
                    // Show Summary Bar
                    var total = rawFeatures.length;
                    var summaryBar = document.getElementById('gis-summary-bar');
                    summaryBar.style.display = 'block';
                    document.getElementById('summary-text').innerHTML = 
                        `<i class="fas fa-network-wired text-warning me-2"></i> FEEDER READY • Total Asset: <strong>${total}</strong> Unit`;
                }
            })
            .catch(err => {
                toggleLoading(false);
                console.error(err);
                alert('Gagal memuat data GIS. Pastikan koneksi internet stabil.');
            });
    }

    // Render Markers & Network Lines Based on Selected Layer Toggles & Zoom LOD
    function renderFilteredLayers() {
        markerCluster.clearLayers();
        translinePolylineLayer.clearLayers();

        if (!rawFeatures || rawFeatures.length === 0) return;

        // Get Active Layer Checkboxes
        var activeLayers = [];
        if (document.getElementById('layer-jtm').checked) activeLayers.push('JTM');
        if (document.getElementById('layer-gardu').checked) activeLayers.push('GARDU');
        if (document.getElementById('layer-trafo').checked) activeLayers.push('TRAFO');
        if (document.getElementById('layer-switch').checked) {
            activeLayers.push('SWITCH', 'LBS', 'LBSM', 'RECLOSER', 'PMS', 'PMT');
        }

        var bounds = [];
        var polylineCoords = [];

        rawFeatures.forEach(function (f) {
            var props = f.properties || {};
            var geom = f.geometry || {};
            var jenis = (props.jenis_asset || '').toUpperCase();
            var constr = (props.construction_type || '').toUpperCase();

            // Check layer filter
            var matched = activeLayers.some(l => jenis.includes(l) || constr.includes(l));
            if (!matched && activeLayers.length > 0) return;

            if (geom.type === 'Point' && geom.coordinates) {
                var lat = geom.coordinates[1];
                var lng = geom.coordinates[0];
                bounds.push([lat, lng]);
                polylineCoords.push([lat, lng]);

                var style = getMarkerStyle(jenis, constr);

                var iconHtml = `<div class="gis-marker-badge" style="background-color: ${style.color}; width: 28px; height: 28px;">
                                    <i class="fas ${style.icon}"></i>
                                </div>`;

                var customIcon = L.divIcon({
                    html: iconHtml,
                    className: 'custom-gis-div-icon',
                    iconSize: [28, 28],
                    iconAnchor: [14, 14]
                });

                var popupHtml = `
                    <div class="p-1 font-sans">
                        <strong class="text-primary font-monospace d-block">${props.kode_asset || '-'}</strong>
                        <h6 class="fw-bold mb-1" style="font-size: 13px;">${props.nama_asset || '-'}</h6>
                        <span class="badge bg-secondary mb-1">${jenis} • ${props.construction_type || 'TM'}</span>
                        <div class="small text-muted mb-2">${props.lokasi || 'Lokasi PLN'}</div>
                        <a href="<?= site_url('master-assets/detail') ?>/${props.id}" class="btn btn-xs btn-primary w-100 fw-bold rounded-pill">Detail Digital Twin &rarr;</a>
                    </div>
                `;

                var marker = L.marker([lat, lng], { icon: customIcon }).bindPopup(popupHtml);
                markerCluster.addLayer(marker);
            }
        });

        // Render Polyline Transline Connection
        if (polylineCoords.length > 1) {
            var line = L.polyline(polylineCoords, {
                color: '#0284c7',
                weight: 4,
                opacity: 0.8,
                lineJoin: 'round'
            });
            translinePolylineLayer.addLayer(line);
        }

        // Fit Map Bounds
        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [40, 40] });
        }
    }

    // Trigger Action on Click TAMPILKAN PETA
    document.getElementById('btn-apply-gis').addEventListener('click', function () {
        loadGisNetworkOnDemand();
    });

    // Re-render markers on layer toggle change
    document.querySelectorAll('.layer-toggle').forEach(el => {
        el.addEventListener('change', function () {
            renderFilteredLayers();
        });
    });

    // Panel Toggle Buttons
    document.getElementById('btn-toggle-panel').addEventListener('click', function () {
        var panel = document.getElementById('gis-filter-panel');
        panel.style.display = (panel.style.display === 'none') ? 'block' : 'none';
    });

    document.getElementById('btn-close-panel').addEventListener('click', function () {
        document.getElementById('gis-filter-panel').style.display = 'none';
    });

    // Location Tracking
    document.getElementById('btn-locate-me').addEventListener('click', function () {
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function (pos) {
                var userLat = pos.coords.latitude;
                var userLng = pos.coords.longitude;
                if (userLocationMarker) map.removeLayer(userLocationMarker);
                userLocationMarker = L.circleMarker([userLat, userLng], {
                    radius: 10, fillColor: '#3b82f6', color: '#ffffff', weight: 3, fillOpacity: 1
                }).addTo(map);

                map.setView([userLat, userLng], 16);
            });
        }
    });

    // Auto-select Feeder if parameter passed
    if (currentFeederId > 0) {
        loadGisNetworkOnDemand();
    }
});
</script>
<?= $this->endSection() ?>
