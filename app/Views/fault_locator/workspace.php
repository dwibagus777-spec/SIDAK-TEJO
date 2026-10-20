<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Fault Locator Command Center') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --fl-bg-dark: #0f172a;
            --fl-card-bg: #1e293b;
            --fl-accent: #38bdf8;
            --fl-border: #334155;
            --fl-success: #10b981;
            --fl-warning: #f59e0b;
            --fl-danger: #ef4444;
            --fl-purple: #8b5cf6;
        }
        body {
            background-color: var(--fl-bg-dark);
            color: #f8fafc;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            padding-bottom: 50px;
        }
        .navbar-custom {
            background-color: #0b1120;
            border-bottom: 1px solid var(--fl-border);
            padding: 12px 24px;
        }
        .card-fl {
            background-color: var(--fl-card-bg);
            border: 1px solid var(--fl-border);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            margin-bottom: 24px;
        }
        .card-fl-header {
            background: rgba(15, 23, 42, 0.6);
            border-bottom: 1px solid var(--fl-border);
            padding: 14px 20px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .kpi-box {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid var(--fl-border);
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }
        .kpi-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--fl-accent);
        }
        .kpi-label {
            font-size: 0.75rem;
            color: #94a3b8;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .badge-resolved {
            background-color: var(--fl-success);
            color: #fff;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .badge-ambiguous {
            background-color: var(--fl-warning);
            color: #000;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .badge-unresolved {
            background-color: var(--fl-danger);
            color: #fff;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .hash-code {
            font-family: monospace;
            font-size: 0.8rem;
            color: #38bdf8;
            background: rgba(15, 23, 42, 0.8);
            padding: 2px 6px;
            border-radius: 4px;
        }
        #miniMap {
            height: 320px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid var(--fl-border);
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-custom navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= site_url('dashboard') ?>">
            <i class="fa-solid fa-crosshairs text-danger"></i>
            <span class="fw-bold">SIDAK TEJO</span>
            <span class="text-secondary">|</span>
            <span class="fs-6 text-light">Fault Locator Command Center Workspace</span>
        </a>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-dark border border-danger text-danger">
                <i class="fa-solid fa-microchip"></i> Engine: <?= esc($summary['engine_version']) ?>
            </span>
            <span class="badge bg-dark border border-info text-info">
                <i class="fa-solid fa-layer-group"></i> <?= esc($summary['topology_snapshot']) ?>
            </span>
            <a href="<?= site_url('gis') ?>" class="btn btn-outline-info btn-sm">
                <i class="fa-solid fa-map"></i> Peta GIS
            </a>
            <a href="<?= site_url('dashboard') ?>" class="btn btn-outline-light btn-sm">
                <i class="fa-solid fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 mt-4">

    <!-- Top KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kpi-box">
                <div class="kpi-value text-info"><?= number_format($summary['active_translines']) ?> TL</div>
                <div class="kpi-label">Authoritative Translines (<?= number_format($summary['network_span_meters']) ?>m)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-box">
                <div class="kpi-value text-success"><?= number_format($summary['active_assets']) ?></div>
                <div class="kpi-label">Active Network Assets</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-box">
                <div class="kpi-value text-warning">ESTIMASI TOPOLOGI</div>
                <div class="kpi-label">Dasar Perhitungan (Traversal GIS)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-box">
                <div class="kpi-value text-light">3 OUTCOMES</div>
                <div class="kpi-label">RESOLVED / AMBIGUOUS / UNRESOLVED</div>
            </div>
        </div>
    </div>

    <!-- Main Workspace Grid -->
    <div class="row g-4">

        <!-- Left Panel: Data Gangguan (Telemetry Form) -->
        <div class="col-lg-4">
            <div class="card-fl">
                <div class="card-fl-header">
                    <span><i class="fa-solid fa-bolt text-warning me-2"></i> INPUT DATA GANGGUAN</span>
                    <span class="badge bg-secondary">TELEMETRY</span>
                </div>
                <div class="card-body p-4">
                    
                    <!-- Quick-fill from Historical Interruptions -->
                    <?php if (!empty($interruptions)): ?>
                    <div class="mb-3">
                        <label class="form-label text-warning small fw-bold">
                            <i class="fa-solid fa-clock-rotate-left me-1"></i> PILIH DARI RIWAYAT GANGGUAN NYATA
                        </label>
                        <select class="form-select bg-dark text-info border-warning" id="quickHistorySelect">
                            <option value="">-- Muat Otomatis Dari Riwayat SCADA --</option>
                            <?php foreach ($interruptions as $hist): ?>
                                <option value="<?= esc(json_encode($hist)) ?>">
                                    <?= esc($hist['event_date']) ?> | <?= esc($hist['feeder_name']) ?> (<?= esc($hist['relay_trip_type'] ?? 'GGN') ?> - <?= esc($hist['fault_current_amperes'] ?? 0) ?>A)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-secondary small">Memuat parameter gangguan riil yang tersimpan di sistem.</div>
                    </div>
                    <hr class="border-secondary my-3">
                    <?php endif; ?>

                    <form id="faultForm">
                        <div class="mb-3">
                            <label class="form-label text-secondary small">PENYULANG (FEEDER)</label>
                            <select class="form-select bg-dark text-light border-secondary" id="feederId">
                                <?php if (!empty($feeders)): ?>
                                    <?php foreach ($feeders as $f): ?>
                                        <option value="<?= esc($f['id']) ?>" <?= (isset($result['feeder_id']) && $result['feeder_id'] == $f['id']) ? 'selected' : '' ?>>
                                            <?= esc($f['nama_penyulang']) ?> (<?= esc($f['kode_penyulang']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="1">PYL-001 (SIWALAN PANJI)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">WAKTU GANGGUAN (TIMESTAMP)</label>
                            <input type="datetime-local" class="form-control bg-dark text-light border-secondary" id="faultTimestamp" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label text-secondary small">FASA GANGGUAN</label>
                                <select class="form-select bg-dark text-light border-secondary" id="faultPhase">
                                    <option value="R-N" selected>R - N (Single Phase)</option>
                                    <option value="S-N">S - N</option>
                                    <option value="T-N">T - N</option>
                                    <option value="R-S">R - S (Phase-to-Phase)</option>
                                    <option value="3PH">3PH (Three Phase)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-secondary small">ARUS GANGGUAN (A)</label>
                                <input type="number" class="form-control bg-dark text-light border-secondary" id="faultCurrentA" value="1450" step="10">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">ESTIMASI JARAK RELAY (METER)</label>
                            <div class="input-group">
                                <input type="number" class="form-control bg-dark text-light border-secondary fw-bold text-info" id="relayDistanceM" value="<?= esc($telemetry['relay_distance_m'] ?? 1250) ?>" step="50">
                                <span class="input-group-text bg-secondary text-light border-secondary">Meter</span>
                            </div>
                            <div class="form-text text-secondary small">Jarak traversal sepanjang konduktor dari titik proteksi/GI.</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary small">TITIK ACUAN PROTEKSI (PERANGKAT)</label>
                            <select class="form-select bg-dark text-light border-secondary" id="deviceAssetId">
                                <option value="GI_OUTGOING" selected>GI Outgoing Breaker (PMCB)</option>
                                <option value="RECLOSER_UTAMA">Recloser Section Utama</option>
                                <option value="LBS_MOTORIZED">LBS Motorized</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-danger w-100 py-2 fw-bold" id="btnLocateFault">
                            <i class="fa-solid fa-crosshairs me-2"></i> HITUNG LOKASI GANGGUAN
                        </button>
                    </form>
                </div>
            </div>

            <!-- Transparency & Telemetry Notes -->
            <div class="card-fl">
                <div class="card-fl-header">
                    <span><i class="fa-solid fa-circle-info text-info me-2"></i> Transparansi & Batasan Telemetri</span>
                </div>
                <div class="card-body p-3 small text-secondary">
                    <p class="mb-2">
                        <strong class="text-light">Dasar Perhitungan Saat Ini:</strong><br>
                        Menggunakan akumulasi panjang segmen konduktor dan posisi tiang GIS dari outgoing GI (<code class="text-info">ESTIMASI_TOPOLOGI</code>).
                    </p>
                    <p class="mb-0">
                        <strong class="text-warning">Kebutuhan Akurasi Metrik Presisi Tinggi:</strong><br>
                        1. Profil impedansi konduktor spesifik ($R + jX$ ohm/km) per jenis kawat.<br>
                        2. Rekaman COMTRADE fasa-ke-tanah dari relay numerik.<br>
                        3. Parameter tahanan pentanahan gardu induk.
                    </p>
                </div>
            </div>
        </div>

        <!-- Middle & Right Panel: FLI Result & Map + SLD -->
        <div class="col-lg-8">
            
            <!-- Result Panel Card -->
            <div class="card-fl">
                <div class="card-fl-header">
                    <span><i class="fa-solid fa-square-poll-vertical text-info me-2"></i> HASIL ANALISIS LOKASI GANGGUAN</span>
                    <span id="outcomeBadge" class="<?= ($result['outcome_status'] === 'RESOLVED') ? 'badge-resolved' : (($result['outcome_status'] === 'DISTANCE_AMBIGUOUS') ? 'badge-ambiguous' : 'badge-unresolved') ?>">
                        <?= esc($result['outcome_status']) ?>
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="bg-dark p-3 rounded-3 border border-secondary">
                                <span class="text-secondary small d-block mb-1">CASE UUID</span>
                                <code id="resCaseUuid" class="text-info fs-6"><?= esc($result['case_uuid']) ?></code>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-dark p-3 rounded-3 border border-secondary">
                                <span class="text-secondary small d-block mb-1">TINGKAT KEYAKINAN (CONFIDENCE)</span>
                                <span id="resConfidence" class="text-success fw-bold fs-5"><?= esc($result['confidence_percent']) ?>%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Details Table -->
                    <table class="table table-dark table-bordered align-middle mb-4">
                        <tbody>
                            <tr>
                                <th style="width:30%">Penyulang Terkait</th>
                                <td id="resFeeder" class="fw-bold text-light"><?= esc($result['feeder_name'] ?? 'SIWALAN PANJI') ?> (Span: <?= number_format($result['feeder_span_meters'] ?? 0) ?> m)</td>
                            </tr>
                            <tr>
                                <th>Kandidat Aset Terduga</th>
                                <td id="resCandidateAsset" class="fw-bold text-light">
                                    <?php if (!empty($result['candidate_assets'])): ?>
                                        <?= esc($result['candidate_assets'][0]['kode_asset'] ?? '') ?> - <?= esc($result['candidate_assets'][0]['asset_name'] ?? '') ?>
                                    <?php else: ?>
                                        Tidak ditemukan aset dalam radius toleransi
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Segmen Transline / Jaringan</th>
                                <td id="resSegment" class="text-info"><?= esc($result['transline_segment'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <th>Jarak Traversal Relay</th>
                                <td id="resDistance" class="fw-bold text-warning"><?= esc($result['relay_distance_m']) ?> Meter</td>
                            </tr>
                            <tr>
                                <th>Estimasi Koordinat (Sidoarjo)</th>
                                <td id="resCoords" class="hash-code">
                                    <?= ($result['fault_point']) ? esc($result['fault_point']['lat'] . ', ' . $result['fault_point']['lng']) : '-7.4478, 112.7183' ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Dasar Perhitungan</th>
                                <td>
                                    <span class="badge bg-info text-dark" id="resDasarPerhitungan"><?= esc($result['dasar_perhitungan'] ?? 'ESTIMASI_TOPOLOGI') ?></span>
                                    <span class="text-secondary small ms-2" id="resDasarLabel"><?= esc($result['dasar_perhitungan_label'] ?? '') ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>Penjelasan & Rekomendasi</th>
                                <td id="resExplanation" class="text-secondary small"><?= esc($result['explanation']) ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Dispatch & Deep-Link Action Banner -->
                    <div class="alert alert-dark border border-danger bg-dark text-light p-3 rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <span class="badge bg-danger text-white me-2"><i class="fa-solid fa-truck-fast"></i> FIELD DISPATCH</span>
                            <strong class="text-light">Teruskan Hasil Gangguan ke Tim Lapangan & GIS</strong>
                        </div>
                        <div class="d-flex gap-2">
                            <a id="btnOpenGis" href="<?= esc($result['gis_url'] ?? site_url('gis')) ?>" target="_blank" class="btn btn-outline-info btn-sm fw-bold">
                                <i class="fa-solid fa-map-location-dot me-1"></i> Buka di Peta GIS
                            </a>
                            <button class="btn btn-danger btn-sm fw-bold" id="btnCreateDispatch">
                                <i class="fa-solid fa-paper-plane me-1"></i> Buat Surat Tugas
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Spatial Fault Point Leaflet Map Preview -->
            <div class="card-fl">
                <div class="card-fl-header">
                    <span><i class="fa-solid fa-map-location-dot text-danger me-2"></i> Pratinjau Spasial Titik Gangguan (Sidoarjo)</span>
                    <span class="badge bg-danger" id="mapStatusBadge">FAULT POINT LOCATED</span>
                </div>
                <div class="card-body p-3">
                    <div id="miniMap"></div>
                </div>
            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let currentCaseUuid = '<?= esc($result['case_uuid']) ?>';
    let currentGisUrl = '<?= esc($result['gis_url'] ?? site_url('gis')) ?>';
    let currentLat = <?= ($result['fault_point'] && isset($result['fault_point']['lat'])) ? (float)$result['fault_point']['lat'] : -7.4478 ?>;
    let currentLng = <?= ($result['fault_point'] && isset($result['fault_point']['lng'])) ? (float)$result['fault_point']['lng'] : 112.7183 ?>;

    // Initialize Leaflet Map
    const map = L.map('miniMap').setView([currentLat, currentLng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    let faultMarker = L.circleMarker([currentLat, currentLng], {
        radius: 12,
        fillColor: '#ef4444',
        color: '#ffffff',
        weight: 3,
        opacity: 1,
        fillOpacity: 0.9
    }).addTo(map);

    let pulseCircle = L.circle([currentLat, currentLng], {
        radius: 150,
        color: '#ef4444',
        fillColor: '#f87171',
        fillOpacity: 0.2
    }).addTo(map);

    faultMarker.bindPopup('<b>Titik Dugaan Gangguan</b><br>Lat: ' + currentLat.toFixed(5) + '<br>Lng: ' + currentLng.toFixed(5)).openPopup();

    function updateMiniMap(lat, lng, assetLabel) {
        if (!lat || !lng) return;
        currentLat = lat;
        currentLng = lng;
        map.setView([lat, lng], 16);
        faultMarker.setLatLng([lat, lng]);
        pulseCircle.setLatLng([lat, lng]);
        faultMarker.bindPopup('<b>Titik Gangguan: ' + (assetLabel || '') + '</b><br>Lat: ' + lat.toFixed(5) + '<br>Lng: ' + lng.toFixed(5)).openPopup();
    }

    // Quick-fill from Historical Interruptions
    const quickHistorySelect = document.getElementById('quickHistorySelect');
    if (quickHistorySelect) {
        quickHistorySelect.addEventListener('change', function() {
            if (!this.value) return;
            try {
                const hist = JSON.parse(this.value);
                if (hist.penyulang_id) {
                    const feederSelect = document.getElementById('feederId');
                    for (let i = 0; i < feederSelect.options.length; i++) {
                        if (feederSelect.options[i].value == hist.penyulang_id) {
                            feederSelect.selectedIndex = i;
                            break;
                        }
                    }
                }
                if (hist.fault_current_amperes) {
                    document.getElementById('faultCurrentA').value = hist.fault_current_amperes;
                }
                if (hist.faulted_phase) {
                    const phaseSelect = document.getElementById('faultPhase');
                    for (let i = 0; i < phaseSelect.options.length; i++) {
                        if (phaseSelect.options[i].value.includes(hist.faulted_phase)) {
                            phaseSelect.selectedIndex = i;
                            break;
                        }
                    }
                }
                if (hist.interruption_started_at) {
                    document.getElementById('faultTimestamp').value = hist.interruption_started_at.replace(' ', 'T').substring(0, 16);
                }
            } catch(e) {
                console.error('Failed to parse history:', e);
            }
        });
    }

    // Locate Fault Button Action
    document.getElementById('btnLocateFault').addEventListener('click', async function() {
        const payload = {
            feeder_id: document.getElementById('feederId').value,
            timestamp: document.getElementById('faultTimestamp').value,
            phase: document.getElementById('faultPhase').value,
            fault_current_a: parseFloat(document.getElementById('faultCurrentA').value),
            relay_distance_m: parseFloat(document.getElementById('relayDistanceM').value),
            device_asset_id: document.getElementById('deviceAssetId').value
        };

        try {
            const res = await fetch('<?= site_url('api/fault-locator/locate') ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (json.status === 'success') {
                const data = json.data;
                currentCaseUuid = data.case_uuid;
                currentGisUrl = data.gis_url;
                updateResultUI(data);
            } else {
                alert('Gagal menghitung lokasi gangguan: ' + (json.message || 'Unknown error'));
            }
        } catch(e) {
            console.error(e);
            alert('Terjadi kesalahan saat menghubungkan ke service Fault Locator.');
        }
    });

    document.getElementById('btnCreateDispatch').addEventListener('click', async function() {
        if (!currentCaseUuid) {
            alert('Silakan eksekusi penentuan lokasi gangguan terlebih dahulu.');
            return;
        }
        const res = await fetch('<?= site_url('api/fault-locator/dispatch') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({case_uuid: currentCaseUuid})
        });
        const json = await res.json();
        if (json.status === 'success') {
            alert('Surat Tugas Tim Yantek Berhasil Dibuat!\nDispatch ID: ' + json.data.dispatch_id + '\nTim: ' + json.data.assigned_team);
        }
    });

    function updateResultUI(data) {
        document.getElementById('resCaseUuid').innerText = data.case_uuid;
        document.getElementById('resConfidence').innerText = data.confidence_percent + '%';
        document.getElementById('resDistance').innerText = data.relay_distance_m + ' Meter';
        document.getElementById('resExplanation').innerText = data.explanation;
        document.getElementById('resFeeder').innerText = (data.feeder_name || 'Feeder') + ' (Span: ' + (data.feeder_span_meters || 0) + ' m)';
        document.getElementById('resSegment').innerText = data.transline_segment || '-';

        if (data.fault_point && data.fault_point.lat && data.fault_point.lng) {
            document.getElementById('resCoords').innerText = data.fault_point.lat.toFixed(6) + ', ' + data.fault_point.lng.toFixed(6);
            let candName = '';
            if (data.candidate_assets && data.candidate_assets.length > 0) {
                candName = data.candidate_assets[0].kode_asset + ' (' + data.candidate_assets[0].asset_name + ')';
            }
            updateMiniMap(data.fault_point.lat, data.fault_point.lng, candName);
        }

        const badge = document.getElementById('outcomeBadge');
        badge.innerText = data.outcome_status;
        if (data.outcome_status === 'RESOLVED') {
            badge.className = 'badge-resolved';
        } else if (data.outcome_status === 'DISTANCE_AMBIGUOUS') {
            badge.className = 'badge-ambiguous';
        } else {
            badge.className = 'badge-unresolved';
        }

        if (data.candidate_assets && data.candidate_assets.length > 0) {
            const top = data.candidate_assets[0];
            document.getElementById('resCandidateAsset').innerText = top.kode_asset + ' - ' + top.asset_name + ' (Deviasi: ' + top.delta_dist_m + 'm)';
        } else {
            document.getElementById('resCandidateAsset').innerText = 'Tidak ditemukan kandidat dalam rentang toleransi';
        }

        const gisBtn = document.getElementById('btnOpenGis');
        if (gisBtn && data.gis_url) {
            gisBtn.href = data.gis_url;
        }
    }
</script>
</body>
</html>
