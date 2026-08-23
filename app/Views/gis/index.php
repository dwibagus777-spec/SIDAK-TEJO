<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>GIS Network Intelligence<?= $this->endSection() ?>
<?= $this->section('page_title') ?>GIS NETWORK — PETA JARINGAN DISTRIBUSI 20KV<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Leaflet & MarkerCluster CSS -->
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

    /* ========================================================
       PH-VIS-01: Asset Visual Identity & Network Symbol Styles
       ======================================================== */
    .custom-gis-div-icon {
        background: transparent;
        border: none;
    }

    /* Touch target container (minimum 44x44px for Android WebView & Mobile) */
    .asset-network-marker-wrap {
        position: relative;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    /* Inner symbol container */
    .asset-symbol-box {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #ffffff;
        padding: 3px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 3px 8px rgba(15, 23, 42, 0.25);
        transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease;
    }

    .asset-symbol-img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
    }

    .asset-network-marker-wrap:hover .asset-symbol-box {
        transform: scale(1.3);
        z-index: 9999 !important;
    }

    /* Condition & Severity Rings */
    .asset-ring-good {
        box-shadow: 0 0 0 2px #10b981, 0 3px 8px rgba(15, 23, 42, 0.25);
    }
    .asset-ring-fair {
        box-shadow: 0 0 0 2px #0ea5e9, 0 3px 8px rgba(15, 23, 42, 0.25);
    }
    .asset-ring-poor {
        box-shadow: 0 0 0 2px #f59e0b, 0 3px 8px rgba(15, 23, 42, 0.25);
    }
    .asset-ring-critical {
        box-shadow: 0 0 0 2.5px #ef4444, 0 3px 8px rgba(239, 68, 68, 0.4);
        animation: pulse-critical 2s infinite;
    }
    .asset-ring-emergency {
        box-shadow: 0 0 0 3px #dc2626, 0 0 12px #dc2626;
        animation: pulse-emergency 1.4s infinite;
    }
    .asset-ring-inactive {
        opacity: 0.65;
        box-shadow: 0 0 0 2px #64748b;
    }

    @keyframes pulse-critical {
        0%   { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7), 0 3px 8px rgba(15, 23, 42, 0.25); }
        70%  { box-shadow: 0 0 0 9px rgba(239, 68, 68, 0), 0 3px 8px rgba(15, 23, 42, 0.25); }
        100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0), 0 3px 8px rgba(15, 23, 42, 0.25); }
    }
    @keyframes pulse-emergency {
        0%   { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.85), 0 0 12px #dc2626; }
        70%  { box-shadow: 0 0 0 12px rgba(220, 38, 38, 0), 0 0 12px #dc2626; }
        100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0), 0 0 12px #dc2626; }
    }

    /* Floating Legend Panel (Bottom-Right) */
    .gis-legend-container {
        position: absolute;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
    }

    .gis-legend-card {
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(12px);
        border-radius: 16px;
        padding: 14px 16px;
        width: 320px;
        max-height: 420px;
        overflow-y: auto;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(226, 232, 240, 0.8);
        display: none;
        margin-bottom: 8px;
    }

    .legend-item-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 4px 6px;
        border-radius: 8px;
        transition: background 0.15s ease;
    }
    .legend-item-row:hover {
        background: #f1f5f9;
    }
    .legend-icon-preview {
        width: 24px;
        height: 24px;
        object-fit: contain;
        flex-shrink: 0;
    }

    /* Custom Popup Card Styling */
    .leaflet-popup-content-wrapper {
        border-radius: 16px !important;
        padding: 4px !important;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.25) !important;
        border: 1px solid rgba(226, 232, 240, 0.8);
    }
    .gis-popup-container {
        padding: 8px;
        font-family: 'Outfit', sans-serif;
    }
    .gis-popup-badge {
        display: inline-block !important;
        font-weight: 700 !important;
        font-size: 11px !important;
        padding: 3px 8px !important;
        border-radius: 8px !important;
        line-height: 1.2 !important;
    }
</style>

<div class="gis-container container-fluid py-2">

    <!-- Compact Header Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap" style="gap: 8px;">
        <div class="d-flex align-items-center gap-2">
            <h4 class="fw-bold mb-0 text-primary d-flex align-items-center">
                <i class="fas fa-network-wired text-warning me-2 fs-4"></i> GIS NETWORK
            </h4>
            <span class="badge bg-primary rounded-pill font-weight-normal px-2 py-1" style="font-size: 11px;">PETA OPERASIONAL PLN 20KV</span>
        </div>
        
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" id="btn-toggle-legend-top" class="btn btn-outline-secondary btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-layer-group me-1 text-primary"></i> Legenda Simbol
            </button>
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
            <div class="spinner-border text-warning mb-2" role="status" style="width: 2.5rem; height: 2.5rem;"></div>
            <span class="fw-bold font-monospace" style="font-size: 14px;">Memuat Data Jaringan GIS...</span>
        </div>

        <!-- Filter & Layer Panel (Desktop Floating Left / Mobile Bottom Sheet) -->
        <div id="gis-filter-panel" class="gis-filter-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-filter text-primary me-2"></i> Filter Jaringan PLN</h6>
                <button type="button" id="btn-close-panel" class="btn-close d-md-none"></button>
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
                </select>
            </div>

            <!-- Step 3: Layer Selection Checkboxes -->
            <div class="mb-3">
                <label class="small text-muted font-weight-bold d-block mb-1">3. Pilih Layer Peta Aset:</label>
                <div class="d-flex flex-column gap-2 bg-light p-2 rounded-3 border">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input layer-toggle" type="checkbox" id="layer-jtm" value="JTM" checked>
                        <label class="form-check-label small fw-bold text-dark" for="layer-jtm">
                            <i class="fas fa-square-poll-vertical text-primary me-1"></i> JTM & Tiang
                        </label>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input layer-toggle" type="checkbox" id="layer-gardu" value="GARDU" checked>
                        <label class="form-check-label small fw-bold text-dark" for="layer-gardu">
                            <i class="fas fa-building-columns text-info me-1"></i> Gardu Distribusi
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

        <!-- Floating Collapsible Legend Panel (Bottom-Right) -->
        <div class="gis-legend-container">
            <div id="gis-legend-panel" class="gis-legend-card">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                    <span class="fw-bold text-dark" style="font-size: 12px;">
                        <i class="fas fa-shapes text-warning me-1"></i> SIMBOL ASET JARINGAN (PLN)
                    </span>
                    <button type="button" id="btn-close-legend" class="btn-close btn-close-sm" style="font-size: 10px;"></button>
                </div>
                
                <!-- 10 Primary Asset Symbols from Visual Registry -->
                <div class="d-flex flex-column gap-1 mb-2">
                    <?php if (!empty($legendItems)): ?>
                        <?php foreach ($legendItems as $item): ?>
                            <?php if ($item['symbol_key'] === 'DEFAULT') continue; ?>
                            <div class="legend-item-row">
                                <img src="<?= base_url($item['svg_path']) ?>" alt="<?= esc($item['label']) ?>" class="legend-icon-preview">
                                <div class="d-flex flex-column" style="line-height: 1.1;">
                                    <strong class="text-dark" style="font-size: 11px;"><?= esc($item['symbol_key']) ?></strong>
                                    <span class="text-muted" style="font-size: 10px;"><?= esc($item['label']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Condition Overlay Legend -->
                <div class="border-top pt-2 mt-1">
                    <span class="fw-bold text-secondary d-block mb-1" style="font-size: 10px;">STATUS & KONDISI ASET</span>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge bg-success text-white" style="font-size: 9px;"><i class="fas fa-check-circle me-1"></i> GOOD</span>
                        <span class="badge bg-info text-dark" style="font-size: 9px;"><i class="fas fa-info-circle me-1"></i> FAIR</span>
                        <span class="badge bg-warning text-dark" style="font-size: 9px;"><i class="fas fa-exclamation-circle me-1"></i> POOR</span>
                        <span class="badge bg-danger text-white" style="font-size: 9px;"><i class="fas fa-triangle-exclamation me-1"></i> CRITICAL</span>
                    </div>
                </div>
            </div>

            <!-- Floating Toggle Button for Legend -->
            <button type="button" id="btn-toggle-legend" class="btn btn-dark btn-sm rounded-pill shadow px-3 py-1 font-weight-bold" style="background: rgba(15, 23, 42, 0.92); border: none;">
                <i class="fas fa-layer-group text-warning me-1"></i> Legenda Peta
            </button>
        </div>

        <!-- Summary Floating Bottom Bar -->
        <div id="gis-summary-bar" class="gis-summary-bar">
            <span id="summary-text" class="fw-bold font-monospace">Memuat Data Jaringan...</span>
        </div>

        <!-- Leaflet Map Element -->
        <div id="gisMap"></div>

    </div>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<!-- Strict Order Dependency Injection: Leaflet Core JS followed by Leaflet MarkerCluster Plugin -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    var defaultLat = -7.4523;
    var defaultLng = 112.7161;

    var map = L.map('gisMap', {
        center: [defaultLat, defaultLng],
        zoom: 14,
        zoomControl: false
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; PLN SIDAK TEJO GIS'
    }).addTo(map);

    L.control.zoom({ position: 'topright' }).addTo(map);

    // Runtime Guard & Fallback for Leaflet.MarkerCluster Plugin
    var markerCluster;
    if (typeof L !== 'undefined' && typeof L.markerClusterGroup === 'function') {
        markerCluster = L.markerClusterGroup({
            chunkedLoading: true,
            maxClusterRadius: 40,
            disableClusteringAtZoom: 17
        });
    } else {
        console.warn('[GIS Engine] Leaflet MarkerCluster plugin tidak berhasil dimuat. Fallback ke L.featureGroup()');
        markerCluster = L.featureGroup();
    }
    map.addLayer(markerCluster);

    var translinePolylineLayer = L.featureGroup().addTo(map);
    var userLocationMarker = null;

    var currentFeederId = 0;
    var currentData = null;
    var currentLOD = null;
    var currentRequestId = 0;
    var currentUlpRequestId = 0;

    function toggleLoading(show) {
        document.getElementById('gis-loading-overlay').style.display = show ? 'flex' : 'none';
    }

    function getSelectedLayers() {
        var layers = [];
        if (document.getElementById('layer-jtm').checked) layers.push('JTM');
        if (document.getElementById('layer-gardu').checked) layers.push('GARDU');
        if (document.getElementById('layer-trafo').checked) layers.push('TRAFO');
        if (document.getElementById('layer-switch').checked) layers.push('SWITCH');
        return layers;
    }

    function getLODCategory(zoom) {
        if (zoom < 13) return 'overview';
        if (zoom < 17) return 'equipment';
        return 'detail';
    }

    // 1. Cascading ULP -> Penyulang (With ULP Race Guard & Complete State Reset!)
    document.getElementById('ulp-select').addEventListener('change', function () {
        var selectedUlpId = this.value;
        var feederSelect = document.getElementById('feeder-select');

        // State Reset on ULP Change
        currentFeederId = 0;
        currentData = null;
        currentLOD = null;
        currentRequestId++; // Invalidate stale network requests!
        var thisUlpRequestId = ++currentUlpRequestId; // ULP Race Guard!

        toggleLoading(false);
        if (markerCluster && typeof markerCluster.clearLayers === 'function') {
            markerCluster.clearLayers();
        }
        translinePolylineLayer.clearLayers();
        document.getElementById('gis-summary-bar').style.display = 'none';

        feederSelect.innerHTML = '<option value="">-- Pilih Penyulang --</option>';
        if (!selectedUlpId) return;

        fetch(`<?= site_url('gis/api-penyulangs') ?>?ulp_id=${selectedUlpId}`)
            .then(res => res.json())
            .then(res => {
                if (thisUlpRequestId !== currentUlpRequestId) return; // Ignore stale ULP response!
                if (res.status === 'success' && res.penyulangs) {
                    res.penyulangs.forEach(p => {
                        var opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = `${p.nama_penyulang} (${p.nama_ulp || 'ULP'})`;
                        feederSelect.appendChild(opt);
                    });
                }
            });
    });

    /**
     * PH-VIS-01: Create Reusable Asset Visual Marker with SVG Silhouette and Condition Overlay
     */
    function createAssetVisualMarker(feature) {
        var props   = feature.properties || {};
        var geom    = feature.geometry || {};
        var visual  = props.visual || {};
        var overlay = props.condition_overlay || {};
        var spec    = props.marker_spec || {};

        var svgPath = visual.svg_path ? `<?= base_url() ?>${visual.svg_path}` : '<?= base_url('/assets/icons/network/generic-network-asset.svg') ?>';
        var ringClass = overlay.ring_class || 'asset-ring-good';
        var symbolKey = visual.symbol_key || props.jenis_asset || 'ASET';
        var conditionLabel = overlay.label || props.status || 'NORMAL';
        var badgeClass = overlay.badge_class || 'bg-success text-white';

        var iconHtml = `
            <div class="asset-network-marker-wrap" title="${props.nama_asset || ''} (${symbolKey})">
                <div class="asset-symbol-box ${ringClass}">
                    <img src="${svgPath}" alt="${symbolKey}" class="asset-symbol-img" />
                </div>
            </div>
        `;

        var customIcon = L.divIcon({
            html: iconHtml,
            className: 'custom-gis-div-icon',
            iconSize: [44, 44],
            iconAnchor: [22, 22],
            popupAnchor: [0, -22]
        });

        var popupHtml = `
            <div class="gis-popup-container">
                <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
                    <img src="${svgPath}" alt="${symbolKey}" style="width: 32px; height: 32px; object-fit: contain;" />
                    <div style="line-height: 1.1;">
                        <strong class="text-primary font-monospace d-block" style="font-size: 11px; color: #0284c7 !important;">${props.kode_asset || '-'}</strong>
                        <span class="text-muted" style="font-size: 10px;">${visual.label || props.jenis_asset || 'Aset Jaringan'}</span>
                    </div>
                </div>
                <h6 class="fw-bold mb-1 text-dark" style="font-size: 13px; color: #1e293b !important;">${props.nama_asset || '-'}</h6>
                <div class="d-flex align-items-center gap-1 mb-2">
                    <span class="gis-popup-badge ${badgeClass}" style="font-size: 10px;">● ${conditionLabel}</span>
                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 10px;">${props.construction_type || 'TM'}</span>
                </div>
                <div class="small text-secondary mb-2" style="font-size: 11px; color: #64748b !important;">
                    <i class="fas fa-location-dot text-danger me-1"></i> ${props.lokasi || 'Lokasi Jaringan PLN'}
                </div>
                <a href="<?= site_url('master-assets/detail') ?>/${props.id}" class="btn btn-sm btn-primary w-100 fw-bold rounded-pill text-white py-1 shadow-sm" style="background-color: #00B5B8 !important; color: #ffffff !important; border: none; font-size: 11px;">
                    Detail Digital Twin &rarr;
                </a>
            </div>
        `;

        return L.marker([geom.coordinates[1], geom.coordinates[0]], { icon: customIcon }).bindPopup(popupHtml);
    }

    // Render Markers & Network Lines
    function renderFilteredLayers(autoFitBounds) {
        if (typeof autoFitBounds === 'undefined') autoFitBounds = false;

        if (markerCluster && typeof markerCluster.clearLayers === 'function') {
            markerCluster.clearLayers();
        }
        translinePolylineLayer.clearLayers();

        if (!currentData) return;

        // Render Feeder LineString / MultiLineString Segments
        if (currentData.transline && currentData.transline.geometry) {
            var geom = currentData.transline.geometry;
            if (geom.type === 'MultiLineString' && geom.coordinates) {
                geom.coordinates.forEach(function (segment) {
                    if (segment.length > 1) {
                        var poly = L.polyline(segment.map(pt => [pt[1], pt[0]]), {
                            color: '#0284c7',
                            weight: 4,
                            opacity: 0.85,
                            lineJoin: 'round'
                        });
                        translinePolylineLayer.addLayer(poly);
                    }
                });
            } else if (geom.type === 'LineString' && geom.coordinates && geom.coordinates.length > 1) {
                var poly = L.polyline(geom.coordinates.map(pt => [pt[1], pt[0]]), {
                    color: '#0284c7',
                    weight: 4,
                    opacity: 0.85,
                    lineJoin: 'round'
                });
                translinePolylineLayer.addLayer(poly);
            }
        }

        // Render Markers
        var features = currentData.features || [];
        var activeLayers = getSelectedLayers();

        features.forEach(function (f) {
            var props = f.properties || {};
            var geom  = f.geometry || {};
            var jenis = (props.jenis_asset || '').toUpperCase();
            var constr = (props.construction_type || '').toUpperCase();

            var isSwitchType = ['LBS', 'LBSM', 'RECLOSER', 'SECTIONALIZER'].includes(jenis);
            var isMatched = activeLayers.includes(jenis) || (activeLayers.includes('SWITCH') && (isSwitchType || constr.includes('PMS') || constr.includes('PMT')));
            if (!isMatched && activeLayers.length > 0) return;

            if (geom.type === 'Point' && geom.coordinates) {
                var marker = createAssetVisualMarker(f);
                markerCluster.addLayer(marker);
            }
        });

        // Fit Bounds ONLY on explicit initial user selection (prevents fitBounds zoomend infinite loop!)
        if (autoFitBounds && currentData.bbox) {
            var b = currentData.bbox;
            map.fitBounds([[b.min_lat, b.min_lng], [b.max_lat, b.max_lng]], { padding: [40, 40] });
        }
    }

    // 2. Fetch Network Data On-Demand
    function loadGisNetworkOnDemand(autoFitBounds) {
        if (typeof autoFitBounds === 'undefined') autoFitBounds = true;

        var feederId = document.getElementById('feeder-select').value;
        if (!feederId) {
            alert('Silakan pilih Penyulang terlebih dahulu!');
            return;
        }

        var thisRequestId = ++currentRequestId;
        currentFeederId = feederId;
        currentLOD = getLODCategory(map.getZoom());

        var layersParam = getSelectedLayers().join(',');
        toggleLoading(true);

        fetch(`<?= site_url('gis/api-network') ?>?penyulang_id=${feederId}&zoom=${map.getZoom()}&layers=${layersParam}`)
            .then(res => res.json())
            .then(res => {
                if (thisRequestId !== currentRequestId) {
                    return; // Ignore stale out-of-order response!
                }
                toggleLoading(false);
                if (res.status === 'success' && res.data) {
                    currentData = res.data;
                    renderFilteredLayers(autoFitBounds);

                    var summaryBar = document.getElementById('gis-summary-bar');
                    summaryBar.style.display = 'block';

                    var sum = currentData.summary || {};
                    document.getElementById('summary-text').innerHTML = 
                        `<i class="fas fa-network-wired text-warning me-2"></i> FEEDER READY • Total Aset: <strong>${sum.total_assets || 0}</strong> (JTM: ${sum.jtm_count || 0}, Gardu: ${sum.gardu_count || 0}, Trafo: ${sum.trafo_count || 0}, Switch: ${sum.switch_count || 0})`;
                }
            })
            .catch(err => {
                if (thisRequestId === currentRequestId) {
                    toggleLoading(false);
                }
                console.error(err);
            });
    }

    // Action Triggers
    document.getElementById('btn-apply-gis').addEventListener('click', function () {
        loadGisNetworkOnDemand(true);
    });

    // Instant client-side Layer Toggle Event Handler
    document.querySelectorAll('.layer-toggle').forEach(el => {
        el.addEventListener('change', function () {
            if (currentData) {
                renderFilteredLayers(false);
            }
        });
    });

    // Smart LOD Debounce Zoom Listener (ONLY triggers API request when crossing LOD boundary, autoFitBounds = false!)
    map.on('zoomend', function () {
        if (currentFeederId > 0) {
            var newLOD = getLODCategory(map.getZoom());
            if (newLOD !== currentLOD) {
                loadGisNetworkOnDemand(false);
            }
        }
    });

    // Panel Toggle
    document.getElementById('btn-toggle-panel').addEventListener('click', function () {
        var panel = document.getElementById('gis-filter-panel');
        panel.style.display = (panel.style.display === 'none') ? 'block' : 'none';
    });

    document.getElementById('btn-close-panel').addEventListener('click', function () {
        document.getElementById('gis-filter-panel').style.display = 'none';
    });

    // Legend Toggle Handlers
    function toggleLegend() {
        var legendPanel = document.getElementById('gis-legend-panel');
        legendPanel.style.display = (legendPanel.style.display === 'block') ? 'none' : 'block';
    }

    document.getElementById('btn-toggle-legend').addEventListener('click', toggleLegend);
    var topLegendBtn = document.getElementById('btn-toggle-legend-top');
    if (topLegendBtn) topLegendBtn.addEventListener('click', toggleLegend);
    document.getElementById('btn-close-legend').addEventListener('click', function () {
        document.getElementById('gis-legend-panel').style.display = 'none';
    });

    // Location Tracking
    document.getElementById('btn-locate-me').addEventListener('click', function () {
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(function (pos) {
                var uLat = pos.coords.latitude;
                var uLng = pos.coords.longitude;
                if (userLocationMarker) map.removeLayer(userLocationMarker);
                userLocationMarker = L.circleMarker([uLat, uLng], {
                    radius: 10, fillColor: '#3b82f6', color: '#ffffff', weight: 3, fillOpacity: 1
                }).addTo(map);

                map.setView([uLat, uLng], 16);
            });
        }
    });
});
</script>
<?= $this->endSection() ?>
