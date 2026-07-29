<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<!-- Leaflet & GIS Extra Styles -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />

<style>
    #gis-master-container {
        position: relative;
        width: 100%;
        height: calc(100vh - 120px);
        min-height: 550px;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    #gisMap {
        width: 100%;
        height: 100%;
        z-index: 1;
    }
    .gis-floating-panel {
        position: absolute;
        top: 16px;
        left: 16px;
        z-index: 1000;
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(10px);
        border-radius: 14px;
        padding: 14px 18px;
        max-width: 380px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }
    .gis-floating-stats {
        position: absolute;
        bottom: 20px;
        left: 16px;
        z-index: 1000;
        background: rgba(15, 23, 42, 0.9);
        backdrop-filter: blur(8px);
        color: #ffffff;
        border-radius: 12px;
        padding: 10px 16px;
        font-size: 12px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.3);
    }
    .gis-floating-tools {
        position: absolute;
        top: 16px;
        right: 16px;
        z-index: 1000;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        padding: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }
    /* Pulse GPS Officer Marker */
    .gps-pulse-marker {
        width: 18px;
        height: 18px;
        background-color: #2563eb;
        border: 3px solid #ffffff;
        border-radius: 50%;
        box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.7);
        animation: pulse-gps 1.6s infinite;
    }
    @keyframes pulse-gps {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.7); }
        70% { transform: scale(1.1); box-shadow: 0 0 0 12px rgba(37, 99, 235, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
    }
</style>

<div class="container-fluid py-3">
    <!-- Top Header Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap: 8px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-map-marked-alt text-warning me-2 fs-3"></i> SMART GIS & NETWORK MAPPING ENTERPRISE
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">ENTERPRISE V18</span>
            </h3>
            <p class="text-muted small mb-0">Visualisasi Peta Jaringan 20KV, Clustering Marker, Drawing Tools, Routing & Geofence Verification</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" id="btn-locate-me" class="btn btn-outline-primary btn-sm rounded-pill font-weight-bold shadow-xs">
                <i class="fas fa-location-crosshairs me-1 text-danger"></i> Posisi Saya (Live GPS)
            </button>
            <button type="button" id="btn-refresh-gis" class="btn btn-primary btn-sm rounded-pill font-weight-bold shadow-xs">
                <i class="fas fa-sync-alt me-1"></i> Sync Data GIS
            </button>
        </div>
    </div>

    <!-- GIS Map Container -->
    <div id="gis-master-container">
        <!-- Floating Filter & Search Panel -->
        <div class="gis-floating-panel d-none d-md-block">
            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-filter text-primary me-1"></i> Filter & Search GIS</h6>
            <div class="mb-2">
                <input type="text" id="gis-search-input" class="form-control form-control-sm" placeholder="Cari No. Temuan / Asset / Penyulang / Alamat...">
            </div>
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <select id="gis-filter-ulp" class="form-select form-select-sm">
                        <option value="">-- Semua ULP --</option>
                        <?php foreach ($ulps as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= esc($u['nama_ulp']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <select id="gis-filter-prio" class="form-select form-select-sm">
                        <option value="">-- Prioritas --</option>
                        <option value="EMERGENCY">EMERGENCY</option>
                        <option value="HIGH">HIGH</option>
                        <option value="MEDIUM">MEDIUM</option>
                    </select>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" id="btn-apply-filter" class="btn btn-primary btn-sm w-100 fw-bold">Terapkan Filter</button>
                <button type="button" id="btn-reset-filter" class="btn btn-outline-secondary btn-sm"><i class="fas fa-rotate-left"></i></button>
            </div>
        </div>

        <!-- Floating Quick Tools (Heatmap & Layers Toggle) -->
        <div class="gis-floating-tools d-flex gap-2 align-items-center">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="toggle-heatmap" style="cursor: pointer;">
                <label class="form-check-label fw-bold text-dark small" for="toggle-heatmap"><i class="fas fa-fire text-danger me-1"></i> Heatmap</label>
            </div>
            <div class="vr"></div>
            <div class="form-check form-switch mb-0 me-2">
                <input class="form-check-input" type="checkbox" id="toggle-cluster" checked style="cursor: pointer;">
                <label class="form-check-label fw-bold text-dark small" for="toggle-cluster"><i class="fas fa-circle-nodes text-primary me-1"></i> Cluster</label>
            </div>
        </div>

        <!-- Floating Summary Stats Panel -->
        <div class="gis-floating-stats d-flex align-items-center gap-3">
            <div>
                <i class="fas fa-map-pin text-warning me-1"></i>
                <span>Total Pin: <strong id="stat-total-pins" class="text-white">0</strong></span>
            </div>
            <div class="vr bg-secondary"></div>
            <div>
                <i class="fas fa-list-check text-info me-1"></i>
                <span>Temuan: <strong id="stat-total-temuan" class="text-white">0</strong></span>
            </div>
            <div class="vr bg-secondary"></div>
            <div>
                <i class="fas fa-cubes text-success me-1"></i>
                <span>Master Asset: <strong id="stat-total-asset" class="text-white">0</strong></span>
            </div>
        </div>

        <!-- Leaflet Map Container Target -->
        <div id="gisMap"></div>
    </div>
</div>

<!-- Modal Geofence Check-in Verification (Target 8, 9) -->
<div class="modal fade" id="geofenceCheckinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-shield-halved text-success me-2"></i> Verifikasi Geofence Check-in</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <span class="text-muted small d-block font-weight-bold">TARGET TEMUAN</span>
                    <h5 id="geofence-target-nomor" class="fw-bold text-primary font-monospace mb-1">-</h5>
                    <p id="geofence-target-detail" class="small text-dark mb-0">-</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Pilih Batas Radius Toleransi GPS</label>
                    <select id="geofence-radius-select" class="form-select text-center font-weight-bold">
                        <option value="50">50 Meter (Presisi Tinggi)</option>
                        <option value="100" selected>100 Meter (Standard Lapangan)</option>
                        <option value="250">250 Meter (Area Luas)</option>
                        <option value="500">500 Meter (Toleransi Maksimal)</option>
                    </select>
                </div>
                <div id="geofence-result-alert" class="alert d-none rounded-3 small"></div>
                <button type="button" id="btn-execute-checkin" class="btn btn-success w-100 rounded-pill font-weight-bold shadow-sm">
                    <i class="fas fa-location-dot me-1"></i> Verifikasi Check-in GPS Lapangan
                </button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<!-- Leaflet & Plugins Scripts -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet-heat@0.2.0/dist/leaflet-heat.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

<script>
let gisMap, clusterGroup, normalGroup, heatmapLayer, drawnItems;
let userLiveMarker = null;
let currentTemuanPins = [];
let currentAssetPins = [];
let activeCheckinTargetId = null;

$(document).ready(function() {
    initGisMap();
    loadGisData();

    // Event Handlers
    $('#btn-refresh-gis, #btn-apply-filter').on('click', function() {
        loadGisData();
    });

    $('#btn-reset-filter').on('click', function() {
        $('#gis-search-input').val('');
        $('#gis-filter-ulp').val('');
        $('#gis-filter-prio').val('');
        loadGisData();
    });

    $('#toggle-cluster, #toggle-heatmap').on('change', function() {
        renderMapLayers();
    });

    $('#btn-locate-me').on('click', function() {
        locateUserPosition();
    });

    $('#btn-execute-checkin').on('click', function() {
        executeGeofenceCheckin();
    });
});

function initGisMap() {
    // Sidoarjo Coordinates default [-7.4478, 112.7183]
    gisMap = L.map('gisMap', {
        center: [-7.4478, 112.7183],
        zoom: 12,
        zoomControl: true
    });

    // Base Tile Layers (OpenStreetMap with cache attribution)
    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© PLN UP3 Sidoarjo - SIDAK TEJO GIS Enterprise'
    }).addTo(gisMap);

    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 18,
        attribution: 'Esri World Imagery'
    });

    // Marker Clusters & Normal Groups
    clusterGroup = L.markerClusterGroup({ maxClusterRadius: 40 });
    normalGroup = L.layerGroup();
    
    // Leaflet Draw Items Group
    drawnItems = new L.FeatureGroup().addTo(gisMap);
    const drawControl = new L.Control.Draw({
        edit: { featureGroup: drawnItems },
        draw: {
            polyline: true,
            polygon: true,
            circle: true,
            rectangle: true,
            marker: true
        }
    });
    gisMap.addControl(drawControl);

    gisMap.on(L.Draw.Event.CREATED, function (e) {
        drawnItems.addLayer(e.layer);
    });

    // Layer Control Widget
    const baseMaps = {
        "Peta Vektor OSM": osmLayer,
        "Satelit Esri": satelliteLayer
    };

    const overlayMaps = {
        "Cluster Markers": clusterGroup,
        "Hasil Gambar": drawnItems
    };

    L.control.layers(baseMaps, overlayMaps, { position: 'topright' }).addTo(gisMap);
}

function loadGisData() {
    const filters = {
        search: $('#gis-search-input').val(),
        ulp_id: $('#gis-filter-ulp').val(),
        prioritas: $('#gis-filter-prio').val()
    };

    $.getJSON("<?= site_url('gis/api-data') ?>", filters, function(res) {
        if (!res || !res.success) return;

        currentTemuanPins = res.temuanPins || [];
        currentAssetPins = res.assetPins || [];

        // Update stats panel
        $('#stat-total-pins').text(res.stats.total_pins || 0);
        $('#stat-total-temuan').text(res.stats.total_temuan || 0);
        $('#stat-total-asset').text(res.stats.total_assets || 0);

        // Prepare Heatmap Layer
        if (heatmapLayer) {
            gisMap.removeLayer(heatmapLayer);
        }
        if (res.heatmapData && res.heatmapData.length) {
            heatmapLayer = L.heatLayer(res.heatmapData, { radius: 25, blur: 15, maxZoom: 17 });
        }

        renderMapLayers();
    });
}

function renderMapLayers() {
    clusterGroup.clearLayers();
    normalGroup.clearLayers();

    const isCluster = $('#toggle-cluster').is(':checked');
    const isHeatmap = $('#toggle-heatmap').is(':checked');

    if (isHeatmap && heatmapLayer) {
        heatmapLayer.addTo(gisMap);
    } else if (heatmapLayer) {
        gisMap.removeLayer(heatmapLayer);
    }

    const targetGroup = isCluster ? clusterGroup : normalGroup;
    if (!isCluster) {
        normalGroup.addTo(gisMap);
    } else {
        gisMap.addLayer(clusterGroup);
    }

    // 1. Render Temuan Pins
    currentTemuanPins.forEach(function(p) {
        if (!p.latitude || !p.longitude) return;

        let color = '#3b82f6';
        if (p.prioritas === 'EMERGENCY') color = '#dc2626';
        else if (p.prioritas === 'HIGH') color = '#ea580c';
        else if (p.prioritas === 'MEDIUM') color = '#eab308';
        if (p.status === 'SELESAI') color = '#10b981';

        const marker = L.circleMarker([parseFloat(p.latitude), parseFloat(p.longitude)], {
            radius: 8,
            fillColor: color,
            color: '#ffffff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.9
        });

        const popupContent = `
            <div style="font-size:12px; min-width: 200px;">
                ${p.foto_url ? `<img src="${p.foto_url}" class="img-fluid rounded mb-2" style="max-height: 100px; width: 100%; object-fit: cover;">` : ''}
                <strong class="text-primary font-monospace d-block">${p.nomor_temuan}</strong>
                <div class="fw-bold text-dark my-1">${p.detail_temuan || '-'}</div>
                <div class="small text-muted mb-2">
                    ULP: <strong>${p.nama_ulp || '-'}</strong> | Penyulang: <strong>${p.nama_penyulang || '-'}</strong><br>
                    Waktu: ${p.created_at_formatted}
                </div>
                <div class="d-flex gap-1">
                    <a href="<?= site_url('temuan/detail/') ?>${p.id}" class="btn btn-xs btn-primary text-white w-100">Detail</a>
                    <a href="https://www.google.com/maps/search/?api=1&query=${p.latitude},${p.longitude}" target="_blank" class="btn btn-xs btn-danger text-white" title="Google Maps"><i class="fas fa-map-marked-alt"></i></a>
                    <button type="button" onclick="openGeofenceCheckinModal(${p.id}, '${p.nomor_temuan}', '${p.detail_temuan}')" class="btn btn-xs btn-success text-white" title="Check-in GPS"><i class="fas fa-location-dot"></i></button>
                </div>
            </div>
        `;

        marker.bindPopup(popupContent);
        targetGroup.addLayer(marker);
    });

    // 2. Render Asset Pins
    currentAssetPins.forEach(function(a) {
        if (!a.latitude || !a.longitude) return;

        const assetIcon = L.divIcon({
            className: 'custom-asset-icon',
            html: `<div style="background:#8b5cf6; color:#ffffff; width:22px; height:22px; border-radius:50%; text-align:center; line-height:22px; border:2px solid #ffffff; font-size:10px;"><i class="fas fa-cube"></i></div>`,
            iconSize: [22, 22]
        });

        const marker = L.marker([parseFloat(a.latitude), parseFloat(a.longitude)], { icon: assetIcon });

        const popupContent = `
            <div style="font-size:12px; min-width: 180px;">
                <strong class="text-purple font-monospace d-block">${a.kode_asset}</strong>
                <div class="fw-bold text-dark my-1">${a.nama_asset}</div>
                <div class="small text-muted mb-2">Jenis: ${a.jenis_asset} | ULP: ${a.nama_ulp || '-'}</div>
                <a href="<?= site_url('assets/detail/') ?>${a.id}" class="btn btn-xs btn-primary text-white w-100">Profile Aset</a>
            </div>
        `;

        marker.bindPopup(popupContent);
        targetGroup.addLayer(marker);
    });
}

function locateUserPosition() {
    if (!navigator.geolocation) {
        alert('Browser Anda tidak mendukung Geolocation GPS.');
        return;
    }

    navigator.geolocation.getCurrentPosition(function(pos) {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;

        if (userLiveMarker) {
            gisMap.removeLayer(userLiveMarker);
        }

        const pulseIcon = L.divIcon({
            className: 'gps-pulse-marker',
            iconSize: [18, 18],
            iconAnchor: [9, 9]
        });

        userLiveMarker = L.marker([lat, lng], { icon: pulseIcon }).addTo(gisMap);
        userLiveMarker.bindPopup('<strong class="text-primary"><i class="fas fa-user-circle me-1"></i> Posisi Live Petugas</strong><br>Koordinat: ' + lat.toFixed(5) + ', ' + lng.toFixed(5)).openPopup();

        gisMap.setView([lat, lng], 15);
    }, function(err) {
        alert('Gagal mengambil posisi GPS live Anda: ' + err.message);
    });
}

function openGeofenceCheckinModal(id, nomor, detail) {
    activeCheckinTargetId = id;
    $('#geofence-target-nomor').text(nomor);
    $('#geofence-target-detail').text(detail);
    $('#geofence-result-alert').addClass('d-none');
    $('#geofenceCheckinModal').modal('show');
}

function executeGeofenceCheckin() {
    if (!navigator.geolocation) {
        alert('Geolocation GPS tidak aktif.');
        return;
    }

    navigator.geolocation.getCurrentPosition(function(pos) {
        const userLat = pos.coords.latitude;
        const userLng = pos.coords.longitude;
        const radius = $('#geofence-radius-select').val();

        $.post("<?= site_url('gis/checkin') ?>", {
            latitude: userLat,
            longitude: userLng,
            target_id: activeCheckinTargetId,
            radius: radius,
            <?= csrf_token() ?>: "<?= csrf_hash() ?>"
        }, function(res) {
            const $alert = $('#geofence-result-alert');
            $alert.removeClass('d-none alert-success alert-danger');

            if (res.success) {
                $alert.addClass('alert-success').html('<i class="fas fa-check-circle me-1"></i> ' + res.message);
            } else {
                $alert.addClass('alert-danger').html('<i class="fas fa-triangle-exclamation me-1"></i> ' + res.message);
            }
        });
    });
}
</script>
<?= $this->endSection() ?>
