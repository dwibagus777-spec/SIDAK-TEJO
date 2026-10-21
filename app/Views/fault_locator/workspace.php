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
            padding: 14px;
            text-align: center;
        }
        .kpi-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--fl-accent);
        }
        .kpi-label {
            font-size: 0.72rem;
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
            font-size: 0.85rem;
        }
        .badge-ambiguous {
            background-color: var(--fl-warning);
            color: #000;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        .badge-data-tidak-cukup {
            background-color: #f97316;
            color: #fff;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        .badge-unresolved {
            background-color: var(--fl-danger);
            color: #fff;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
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
            height: 380px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid var(--fl-border);
        }
        .table-custom-dark th {
            background-color: #0f172a;
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            border-color: var(--fl-border);
        }
        .table-custom-dark td {
            background-color: rgba(15, 23, 42, 0.4);
            color: #f1f5f9;
            border-color: var(--fl-border);
            font-size: 0.82rem;
        }
        .row-fault-zone {
            background-color: rgba(239, 68, 68, 0.25) !important;
            border-left: 4px solid var(--fl-danger);
        }
        .row-fault-zone td {
            background-color: rgba(239, 68, 68, 0.2) !important;
            font-weight: 600;
            color: #fecaca !important;
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
            <span class="fs-6 text-light">Fault Locator Command Center</span>
        </a>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="badge bg-dark border border-danger text-danger">
                <i class="fa-solid fa-microchip"></i> Engine: <?= esc($summary['engine_version'] ?? 'FLI-2.0.0') ?>
            </span>
            <span class="badge bg-dark border border-warning text-warning">
                <i class="fa-solid fa-bolt"></i> 20 kV / NGR 40Ω
            </span>
            <a href="<?= site_url('identifikasi') ?>" class="btn btn-outline-warning btn-sm">
                <i class="fa-solid fa-bolt-lightning"></i> Identifikasi Gangguan
            </a>
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
                <div class="kpi-value text-info"><?= number_format($summary['active_translines']) ?> Segmen</div>
                <div class="kpi-label">Transline Tiang JTM (<?= number_format($summary['network_span_meters']) ?>m)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-box">
                <div class="kpi-value text-success"><?= number_format($summary['active_assets']) ?></div>
                <div class="kpi-label">Aset Aktif Terverifikasi</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-box">
                <div class="kpi-value text-warning">KAIDAH IMPEDANSI</div>
                <div class="kpi-label">20 kV LL / 11.55 kV LN | NGR 40Ω</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-box">
                <div class="kpi-value text-light">4 STATUS</div>
                <div class="kpi-label">RESOLVED / AMBIGUOUS / DATA_TIDAK_CUKUP / UNRESOLVED</div>
            </div>
        </div>
    </div>

    <!-- Main Workspace Grid -->
    <div class="row g-4">

        <!-- Left Panel: Data Gangguan (Telemetry & Filter Form) -->
        <div class="col-lg-4">
            <div class="card-fl">
                <div class="card-fl-header">
                    <span><i class="fa-solid fa-sliders text-warning me-2"></i> PARAMETER GANGGUAN</span>
                    <span class="badge bg-secondary">INPUT FILTER</span>
                </div>
                <div class="card-body p-4">
                    
                    <form id="faultForm">
                        <!-- Cascading ULP -->
                        <div class="mb-3">
                            <label class="form-label text-warning small fw-bold">UNIT LAYANAN PELANGGAN (ULP) <span class="text-danger">*</span></label>
                            <select class="form-select bg-dark text-light border-secondary" id="ulpId">
                                <option value="">-- Pilih ULP --</option>
                                <?php if (!empty($ulps)): ?>
                                    <?php foreach ($ulps as $u): ?>
                                        <option value="<?= esc($u['id']) ?>" <?= (isset($telemetry['ulp_id']) && $telemetry['ulp_id'] == $u['id']) ? 'selected' : '' ?>>
                                            <?= esc($u['nama_ulp']) ?> (<?= esc($u['kode_ulp']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Cascading Penyulang -->
                        <div class="mb-3">
                            <label class="form-label text-info small fw-bold">PENYULANG (FEEDER) <span class="text-danger">*</span></label>
                            <select class="form-select bg-dark text-light border-secondary" id="feederId">
                                <option value="">-- Pilih Penyulang --</option>
                                <?php if (!empty($feeders)): ?>
                                    <?php foreach ($feeders as $f): ?>
                                        <option value="<?= esc($f['id']) ?>" <?= (isset($result['feeder_id']) && $result['feeder_id'] == $f['id']) ? 'selected' : '' ?>>
                                            <?= esc($f['nama_penyulang']) ?> (<?= esc($f['kode_penyulang']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="form-text text-secondary small">Filter otomatis disesuaikan saat ULP diubah.</div>
                        </div>

                        <!-- Sumber Acuan Gangguan: GI vs Peralatan Proteksi -->
                        <div class="mb-3 p-3 rounded-3 bg-dark border border-secondary">
                            <label class="form-label text-light small fw-bold mb-2">
                                <i class="fa-solid fa-map-pin text-danger me-1"></i> SUMBER ACUAN AWAL GANGGUAN
                            </label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="sourceType" id="srcGI" value="GI" checked>
                                <label class="form-check-label text-light small" for="srcGI">
                                    <strong>Dari Gardu Induk (GI)</strong>
                                    <div class="text-secondary" style="font-size:0.75rem;">Perhitungan berawal dari tiang JTM pertama di pangkal penyulang.</div>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="sourceType" id="srcProtection" value="PROTECTION">
                                <label class="form-check-label text-light small" for="srcProtection">
                                    <strong>Dari Peralatan Proteksi (Recloser / PMCB / LBS)</strong>
                                    <div class="text-secondary" style="font-size:0.75rem;">Berawal dari tiang JTM terverifikasi terdekat dari peralatan proteksi tersebut.</div>
                                </label>
                            </div>

                            <!-- Dropdown Peralatan Proteksi (Visible if PROTECTION chosen) -->
                            <div class="mt-3" id="protectionDeviceContainer" style="display: none;">
                                <label class="form-label text-secondary small">PILIH PERALATAN PROTEKSI</label>
                                <select class="form-select bg-dark text-light border-warning" id="protectionDeviceId">
                                    <option value="">-- Pilih Perangkat Proteksi --</option>
                                    <?php if (!empty($protectionDevices)): ?>
                                        <?php foreach ($protectionDevices as $dev): ?>
                                            <option value="<?= esc($dev['id']) ?>">
                                                <?= esc($dev['kode_asset']) ?> - <?= esc($dev['nama_asset']) ?> (<?= esc($dev['jenis_asset']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Waktu Gangguan -->
                        <div class="mb-3">
                            <label class="form-label text-secondary small">WAKTU GANGGUAN (TIMESTAMP)</label>
                            <input type="datetime-local" class="form-control bg-dark text-light border-secondary" id="faultTimestamp" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>

                        <!-- Masukan Arus Fasa R, S, T, dan Netral -->
                        <div class="p-3 rounded-3 bg-dark border border-secondary mb-3">
                            <label class="form-label text-warning small fw-bold mb-2">
                                <i class="fa-solid fa-wave-square me-1"></i> ARUS GANGGUAN RELAY (AMPERE)
                            </label>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label text-secondary small">Fasa R (A)</label>
                                    <input type="number" class="form-control bg-dark text-danger border-secondary fw-bold" id="currentR" value="1450" step="5">
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-secondary small">Fasa S (A)</label>
                                    <input type="number" class="form-control bg-dark text-warning border-secondary fw-bold" id="currentS" value="1420" step="5">
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label text-secondary small">Fasa T (A)</label>
                                    <input type="number" class="form-control bg-dark text-info border-secondary fw-bold" id="currentT" value="1440" step="5">
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-secondary small">Netral / GFR (A)</label>
                                    <input type="number" class="form-control bg-dark text-light border-secondary fw-bold" id="currentN" value="15" step="5">
                                </div>
                            </div>
                            <div class="form-text text-secondary mt-1" style="font-size:0.75rem;">
                                Klasifikasi otomatis jenis gangguan (3-PH, 2-PH, 1-LG, 2-LG) berdasarkan sebaran arus fasa dan netral.
                            </div>
                        </div>

                        <!-- Tipe Konduktor JTM -->
                        <div class="mb-3">
                            <label class="form-label text-secondary small">TIPE KONDUKTOR JTM</label>
                            <select class="form-select bg-dark text-light border-secondary" id="conductorType">
                                <?php foreach ($conductorCatalog as $k => $c): ?>
                                    <option value="<?= esc($k) ?>" <?= ($k === 'AAAC 150 mm²') ? 'selected' : '' ?>>
                                        <?= esc($k) ?> (Z1 = <?= esc($c['z1_ohm_km']) ?> Ω/km)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Pembacaan Jarak Relay Langsung (Opsional) -->
                        <div class="mb-4">
                            <label class="form-label text-secondary small">PEMBACAAN JARAK RELAY TERSEDIA (OPSIONAL / METER)</label>
                            <div class="input-group">
                                <input type="number" class="form-control bg-dark text-info border-secondary fw-bold" id="relayDistanceM" placeholder="Kosongkan untuk hitung via impedansi arus" step="10">
                                <span class="input-group-text bg-secondary text-light border-secondary">Meter</span>
                            </div>
                            <div class="form-text text-secondary small">Jika relay jarak / DFR telah menampilkan nilai km pasti, masukkan di sini.</div>
                        </div>

                        <button type="button" class="btn btn-danger w-100 py-2 fw-bold" id="btnLocateFault">
                            <i class="fa-solid fa-crosshairs me-2"></i> HITUNG LOKASI GANGGUAN
                        </button>
                    </form>
                </div>
            </div>

            <!-- Transparency & Engineering Principles -->
            <div class="card-fl">
                <div class="card-fl-header">
                    <span><i class="fa-solid fa-book-bookmark text-info me-2"></i> Kaidah Teknik Elektro 20 kV</span>
                </div>
                <div class="card-body p-3 small text-secondary">
                    <p class="mb-2">
                        <strong class="text-light">1. Tegangan & Pentanahan:</strong><br>
                        Tegangan nominal fasa-fasa $V_{LL} = 20\text{ kV}$, fasa-netral $V_{LN} \approx 11,547\text{ V}$. Sistem distribusi UP3 Sidoarjo menggunakan NGR 40 $\Omega$.
                    </p>
                    <p class="mb-2">
                        <strong class="text-warning">2. Batasan Arus Hubung Tanah (1-LG):</strong><br>
                        Arus maksimum 1-LG dibatasi oleh NGR: $I_{f\text{ max}} \approx \frac{V_{LN}}{R_N} \approx 288\text{ A}$. Tanpa nilai reaktansi murni ($X$), besaran arus 1-LG didominasi oleh tahanan kontak ($R_f$) dan pentanahan, bukan panjang bentang semata.
                    </p>
                    <p class="mb-0">
                        <strong class="text-info">3. Integritas Tiang JTM:</strong><br>
                        Transline hanya menghubungkan fisik tiang JTM. Gardu induk dan recloser dipetakan ke tiang JTM terverifikasi terdekat sebagai titik acuan ($0\text{ m}$).
                    </p>
                </div>
            </div>
        </div>

        <!-- Middle & Right Panel: Calculation Results, Technical Details, SLD & Map -->
        <div class="col-lg-8">
            
            <!-- Result Panel Card -->
            <div class="card-fl">
                <div class="card-fl-header">
                    <span><i class="fa-solid fa-square-poll-vertical text-info me-2"></i> HASIL ANALISIS FAULT LOCATOR</span>
                    <span id="outcomeBadge" class="<?= ($result['outcome_status'] === 'RESOLVED') ? 'badge-resolved' : (($result['outcome_status'] === 'DATA_TIDAK_CUKUP') ? 'badge-data-tidak-cukup' : (($result['outcome_status'] === 'DISTANCE_AMBIGUOUS') ? 'badge-ambiguous' : 'badge-unresolved')) ?>">
                        <?= esc($result['outcome_status'] ?? 'RESOLVED') ?>
                    </span>
                </div>
                <div class="card-body p-4">
                    
                    <!-- Top Status Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="bg-dark p-3 rounded-3 border border-secondary">
                                <span class="text-secondary small d-block mb-1">KLASIFIKASI GANGGUAN</span>
                                <span id="resFaultTypeLabel" class="text-warning fw-bold fs-6">
                                    <?= esc($result['fault_classification']['fault_type_label'] ?? 'Gangguan 3 Fasa') ?>
                                </span>
                                <div class="text-light small mt-1">Arus Aktif: <strong id="resActiveCurrent" class="text-danger"><?= esc($result['fault_classification']['active_current_a'] ?? 1450) ?> A</strong></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-dark p-3 rounded-3 border border-secondary">
                                <span class="text-secondary small d-block mb-1">ESTIMASI JARAK DARI ACUAN</span>
                                <span id="resDistance" class="text-info fw-bold fs-5">
                                    <?= number_format($result['calculated_distance_m'] ?? 0, 1) ?> Meter
                                </span>
                                <div class="text-secondary small mt-1">Dari Titik Nol Acuan JTM</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-dark p-3 rounded-3 border border-secondary">
                                <span class="text-secondary small d-block mb-1">TINGKAT KEYAKINAN</span>
                                <span id="resConfidence" class="text-success fw-bold fs-5"><?= esc($result['confidence_percent'] ?? 88) ?>%</span>
                                <div class="text-secondary small mt-1">Case: <code id="resCaseUuid" class="text-info"><?= esc($result['case_uuid']) ?></code></div>
                            </div>
                        </div>
                    </div>

                    <!-- Insufficient Data Alert Box (if applicable) -->
                    <div id="insufficientDataAlert" class="alert alert-warning border border-warning bg-dark text-warning p-3 rounded-3 mb-4" style="display: <?= ($result['calculation_status'] === 'DATA_TIDAK_CUKUP') ? 'block' : 'none' ?>;">
                        <h6 class="fw-bold mb-2"><i class="fa-solid fa-triangle-exclamation me-1"></i> STATUS: DATA TELEMETRI BELUM CUKUP UNTUK JARAK PRESISI</h6>
                        <p class="small mb-2 text-light" id="resExplanationText">
                            <?= esc($result['explanation'] ?? '') ?>
                        </p>
                        <div class="small fw-bold text-warning mb-1">Parameter yang masih diperlukan:</div>
                        <ul class="small mb-2 ps-3 text-secondary" id="missingParametersList">
                            <?php if (!empty($result['missing_parameters'])): ?>
                                <?php foreach ($result['missing_parameters'] as $mp): ?>
                                    <li><?= esc($mp) ?></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                        <div class="small text-info fw-bold">
                            <i class="fa-solid fa-info-circle me-1"></i> Penelusuran topologi di bawah ditampilkan sebagai <u>ESTIMASI TOPOLOGI KANDIDAT</u>.
                        </div>
                    </div>

                    <!-- Technical Details Table -->
                    <table class="table table-dark table-bordered align-middle mb-4">
                        <tbody>
                            <tr>
                                <th style="width:28%">Penyulang & Acuan Awal</th>
                                <td>
                                    <strong class="text-light" id="resFeederName"><?= esc($result['feeder_name']) ?></strong> (Total Span: <span id="resTotalSpan"><?= number_format($result['feeder_span_meters'] ?? 0) ?></span> m)<br>
                                    <span class="text-info small" id="resAnchorDesc"><?= esc($result['anchor_description'] ?? '') ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>Dasar Perhitungan</th>
                                <td>
                                    <span class="badge bg-info text-dark" id="resDasarBadge"><?= esc($result['dasar_perhitungan'] ?? '') ?></span>
                                    <span class="text-secondary small ms-2" id="resDasarLabel"><?= esc($result['dasar_perhitungan_label'] ?? '') ?></span>
                                    <?php if (!empty($result['formula_derivation'])): ?>
                                        <div class="text-light small mt-1 font-monospace" id="resFormulaDerivation" style="font-size:0.78rem;">
                                            <?= esc($result['formula_derivation']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Kandidat Tiang Terduga</th>
                                <td id="resCandidateAsset" class="fw-bold text-light">
                                    <?php if (!empty($result['candidate_assets'])): ?>
                                        <?php foreach ($result['candidate_assets'] as $c): ?>
                                            <span class="badge bg-secondary me-1"><?= esc($c['kode_asset']) ?> (<?= esc($c['asset_name']) ?>)</span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-secondary">Tidak ditemukan kandidat dalam rentang toleransi</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Segmen Zona Gangguan</th>
                                <td class="text-info">
                                    <span id="resSegmentName"><?= esc($result['transline_segment'] ?? '-') ?></span>
                                    <?php if (!empty($result['fault_zone_segment'])): ?>
                                        <span class="text-secondary small ms-2">
                                            (<?= esc($result['fault_zone_segment']['from_pole_kode']) ?> &rarr; <?= esc($result['fault_zone_segment']['to_pole_kode']) ?>)
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Estimasi Koordinat (Sidoarjo)</th>
                                <td id="resCoords" class="hash-code">
                                    <?= ($result['fault_point']) ? esc($result['fault_point']['lat'] . ', ' . $result['fault_point']['lng']) : '-7.4478, 112.7183' ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Dispatch & Deep-Link Action Banner -->
                    <div class="alert alert-dark border border-danger bg-dark text-light p-3 rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                        <div>
                            <span class="badge bg-danger text-white me-2"><i class="fa-solid fa-truck-fast"></i> FIELD DISPATCH</span>
                            <strong class="text-light">Teruskan Hasil Gangguan ke Tim Lapangan & GIS</strong>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success btn-sm fw-bold" id="btnSaveCase">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Kasus
                            </button>
                            <a id="btnOpenGis" href="<?= esc($result['gis_url'] ?? site_url('gis')) ?>" target="_blank" class="btn btn-outline-info btn-sm fw-bold">
                                <i class="fa-solid fa-map-location-dot me-1"></i> Buka di Peta GIS
                            </a>
                            <button class="btn btn-danger btn-sm fw-bold" id="btnCreateDispatch">
                                <i class="fa-solid fa-paper-plane me-1"></i> Buat Surat Tugas
                            </button>
                        </div>
                    </div>

                    <!-- Sequence of JTM Pole Translines Table -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-light mb-0">
                                <i class="fa-solid fa-route text-info me-2"></i> URUTAN TRANSLINE TIANG JTM (HILIR DARI ACUAN)
                            </h6>
                            <span class="badge bg-dark border border-secondary text-secondary small" id="poleSeqCount">
                                <?= count($result['pole_sequence'] ?? []) ?> Bentang
                            </span>
                        </div>
                        <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                            <table class="table table-custom-dark table-sm table-bordered align-middle mb-0" id="tablePoleSequence">
                                <thead>
                                    <tr>
                                        <th style="width: 5%">#</th>
                                        <th style="width: 25%">Dari Tiang</th>
                                        <th style="width: 25%">Ke Tiang</th>
                                        <th style="width: 15%">Bentang (m)</th>
                                        <th style="width: 15%">Kumulatif (m)</th>
                                        <th style="width: 15%">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="poleSequenceBody">
                                    <?php if (!empty($result['pole_sequence'])): ?>
                                        <?php foreach ($result['pole_sequence'] as $p): ?>
                                            <tr class="<?= (!empty($p['is_fault_zone'])) ? 'row-fault-zone' : '' ?>">
                                                <td class="text-center font-monospace"><?= esc($p['seq_no']) ?></td>
                                                <td><?= esc($p['from_pole_kode']) ?></td>
                                                <td><?= esc($p['to_pole_kode']) ?></td>
                                                <td class="text-end"><?= number_format($p['span_meters'], 1) ?></td>
                                                <td class="text-end fw-bold"><?= number_format($p['cumulative_meters'], 1) ?></td>
                                                <td>
                                                    <?php if (!empty($p['is_fault_zone'])): ?>
                                                        <span class="badge bg-danger text-white"><i class="fa-solid fa-crosshairs me-1"></i> ZONA GANGGUAN</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-dark text-secondary">Normal</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-secondary py-3">Belum ada segmen transline tiang JTM terpetakan untuk titik acuan ini.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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

            <!-- Controlled AI Feedback Calibration & Feeder Readiness (Track B & C) -->
            <div class="card-fl">
                <div class="card-fl-header">
                    <span><i class="fa-solid fa-brain text-warning me-2"></i> Kalibrasi AI Umpan Balik Terkontrol & Kesiapan Jaringan Penyulang</span>
                    <span class="badge <?= ($calibrationSummary['is_calibration_unlocked'] ?? false) ? 'bg-success' : 'bg-warning text-dark' ?>">
                        <i class="fa-solid fa-shield-halved me-1"></i> <?= esc($calibrationSummary['calibration_status'] ?? 'DATA_COLLECTION_MODE') ?>
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="kpi-box">
                                <div class="kpi-value text-warning"><?= esc($calibrationSummary['confirmed_samples'] ?? 0) ?> / <?= esc($calibrationSummary['min_required_samples'] ?? 15) ?></div>
                                <div class="kpi-label">Sampel Terkonfirmasi</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="kpi-box">
                                <div class="kpi-value text-info"><?= ($calibrationSummary['mean_absolute_error_meters'] !== null) ? number_format($calibrationSummary['mean_absolute_error_meters'], 1) . ' m' : 'N/A' ?></div>
                                <div class="kpi-label">Mean Absolute Error (MAE)</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="kpi-box">
                                <div class="kpi-value text-success"><?= esc($calibrationSummary['accuracy_rate_percent'] ?? 0) ?>%</div>
                                <div class="kpi-label">Akurasi Toleransi (≤250m)</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="kpi-box">
                                <div class="kpi-value text-light"><?= esc($readinessSummary['summary']['ready_count'] ?? 0) ?> / <?= esc($readinessSummary['summary']['total_feeders'] ?? 134) ?></div>
                                <div class="kpi-label">Penyulang Siap Terverifikasi</div>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 rounded-3 bg-dark border border-secondary mb-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <strong class="text-light small"><i class="fa-solid fa-layer-group text-info me-1"></i> Segregasi Kumpulan Data Lapangan (Audit Trail)</strong>
                            <span class="badge bg-secondary font-monospace" style="font-size:0.75rem;">Model: <?= esc($calibrationSummary['active_model_version'] ?? 'CALIB-V1-PHYSICS-BASELINE') ?></span>
                        </div>
                        <div class="row g-2 text-center small">
                            <div class="col-3">
                                <div class="p-2 border border-success rounded bg-dark">
                                    <div class="text-success fw-bold"><?= esc($calibrationSummary['segregated_datasets']['CONFIRMED'] ?? 0) ?></div>
                                    <div class="text-secondary" style="font-size:0.7rem;">TERKONFIRMASI</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 border border-danger rounded bg-dark">
                                    <div class="text-danger fw-bold"><?= esc($calibrationSummary['segregated_datasets']['NOT_FOUND'] ?? 0) ?></div>
                                    <div class="text-secondary" style="font-size:0.7rem;">TIDAK DITEMUKAN</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 border border-warning rounded bg-dark">
                                    <div class="text-warning fw-bold"><?= esc($calibrationSummary['segregated_datasets']['AMBIGUOUS_VERIFIED'] ?? 0) ?></div>
                                    <div class="text-secondary" style="font-size:0.7rem;">AMBIGU / VERIFIKASI</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 border border-info rounded bg-dark">
                                    <div class="text-info fw-bold"><?= esc($calibrationSummary['case_follow_up_statuses']['MENUNGGU_PEMERIKSAAN'] ?? 0) ?></div>
                                    <div class="text-secondary" style="font-size:0.7rem;">MENUNGGU PATROLI</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 text-secondary" style="font-size:0.75rem;">
                            <i class="fa-solid fa-lock text-warning me-1"></i> <strong>Invarian Kalibrasi:</strong> Formula fisika 20 kV baseline terlindungi secara deterministik dari perubahan liar. Parameter hanya dapat dikalibrasi setelah minimal 15 sampel lapangan terkonfirmasi terkumpul.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historical Fault Cases & Patrol Findings (Track A) -->
            <div class="card-fl">
                <div class="card-fl-header">
                    <span><i class="fa-solid fa-clock-rotate-left text-info me-2"></i> Riwayat Kasus Analisis & Rekam Hasil Patroli Lapangan</span>
                    <span class="badge bg-dark border border-secondary text-light" id="caseHistoryCount">
                        <?= count($caseHistory ?? []) ?> Kasus
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-custom-dark table-sm table-bordered align-middle mb-0" id="tableCaseHistory">
                            <thead>
                                <tr>
                                    <th style="width: 14%">No. Kasus</th>
                                    <th style="width: 16%">Penyulang & Sumber</th>
                                    <th style="width: 14%">Arus Gangguan</th>
                                    <th style="width: 12%">Prediksi Jarak</th>
                                    <th style="width: 16%">Status Patroli</th>
                                    <th style="width: 16%">Hasil Temuan Lapangan</th>
                                    <th style="width: 12%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="caseHistoryBody">
                                <?php if (!empty($caseHistory)): ?>
                                    <?php foreach ($caseHistory as $c): ?>
                                        <tr>
                                            <td class="font-monospace text-info small fw-bold"><?= esc($c['case_number']) ?></td>
                                            <td>
                                                <div class="text-light fw-bold"><?= esc($c['nama_penyulang'] ?? 'Feeder #' . $c['penyulang_id']) ?></div>
                                                <div class="text-secondary" style="font-size:0.72rem;"><?= esc($c['source_type']) ?> &bull; <?= esc($c['fault_type']) ?></div>
                                            </td>
                                            <td>
                                                <span class="text-warning small"><?= number_format($c['current_r'] ?? 0) ?> / <?= number_format($c['current_s'] ?? 0) ?> / <?= number_format($c['current_t'] ?? 0) ?> A</span>
                                                <div class="text-secondary" style="font-size:0.72rem;">N: <?= number_format($c['current_n'] ?? 0) ?> A</div>
                                            </td>
                                            <td class="fw-bold text-light"><?= number_format($c['target_distance_meters'] ?? 0, 1) ?> m</td>
                                            <td>
                                                <?php
                                                    $st = $c['follow_up_status'] ?? 'MENUNGGU_PEMERIKSAAN';
                                                    $badgeClass = 'bg-secondary';
                                                    if ($st === 'GANGGUAN_DITEMUKAN') $badgeClass = 'bg-success';
                                                    elseif ($st === 'TIDAK_DITEMUKAN') $badgeClass = 'bg-danger';
                                                    elseif ($st === 'PERLU_VERIFIKASI') $badgeClass = 'bg-warning text-dark';
                                                ?>
                                                <span class="badge <?= $badgeClass ?> small"><?= esc(str_replace('_', ' ', $st)) ?></span>
                                            </td>
                                            <td class="small">
                                                <?php if (!empty($c['latest_finding'])): ?>
                                                    <?php $lf = $c['latest_finding']; ?>
                                                    <?php if ($lf['outcome_type'] === 'CONFIRMED'): ?>
                                                        <strong class="text-success"><i class="fa-solid fa-check me-1"></i><?= esc($lf['component_damaged'] ?: 'Ditemukan') ?></strong>
                                                        <div class="text-secondary" style="font-size:0.7rem;">&Delta; Jarak: <?= esc($lf['distance_error_meters']) ?>m</div>
                                                    <?php elseif ($lf['outcome_type'] === 'NOT_FOUND'): ?>
                                                        <strong class="text-danger"><i class="fa-solid fa-xmark me-1"></i>Tidak Ditemukan</strong>
                                                        <div class="text-secondary" style="font-size:0.7rem;"><?= esc($lf['not_found_reason'] ?: 'Penyisiran nihil') ?></div>
                                                    <?php else: ?>
                                                        <span class="text-warning"><?= esc($lf['outcome_type']) ?></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-secondary">Belum ada inspeksi</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-outline-warning btn-sm py-0 px-2 btn-record-outcome" data-case-id="<?= esc($c['id']) ?>" data-case-number="<?= esc($c['case_number']) ?>" data-feeder-name="<?= esc($c['nama_penyulang'] ?? '') ?>">
                                                    <i class="fa-solid fa-clipboard-check me-1"></i> Catat
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-secondary py-3">Belum ada riwayat kasus analisis gangguan tersimpan.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal Catat Hasil Patroli / Outcome Lapangan -->
            <div class="modal fade" id="modalRecordOutcome" tabindex="-1" aria-labelledby="modalRecordOutcomeLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content bg-dark text-light border border-secondary">
                        <div class="modal-header border-secondary">
                            <h5 class="modal-title text-warning" id="modalRecordOutcomeLabel">
                                <i class="fa-solid fa-clipboard-check me-2"></i> Rekam Hasil Patroli Lapangan
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <form id="formRecordOutcome">
                                <input type="hidden" id="modalCaseId" name="case_id">

                                <div class="alert alert-dark border border-secondary p-3 mb-3">
                                    <div class="row g-2 small">
                                        <div class="col-md-6">No. Kasus: <strong class="text-info" id="modalCaseNumberDisplay">-</strong></div>
                                        <div class="col-md-6">Penyulang: <strong class="text-light" id="modalFeederDisplay">-</strong></div>
                                    </div>
                                </div>

                                <!-- Jenis Hasil Outcome -->
                                <div class="mb-3">
                                    <label class="form-label text-secondary small fw-bold">HASIL TEMUAN PATROLI LAPANGAN</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="outcomeType" id="outConfirmed" value="CONFIRMED" checked>
                                            <label class="form-check-label text-success fw-bold small" for="outConfirmed">
                                                <i class="fa-solid fa-circle-check me-1"></i> Gangguan Ditemukan
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="outcomeType" id="outNotFound" value="NOT_FOUND">
                                            <label class="form-check-label text-danger fw-bold small" for="outNotFound">
                                                <i class="fa-solid fa-circle-xmark me-1"></i> Tidak Ditemukan (Nihil)
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="outcomeType" id="outAmbiguous" value="AMBIGUOUS">
                                            <label class="form-check-label text-warning fw-bold small" for="outAmbiguous">
                                                <i class="fa-solid fa-triangle-exclamation me-1"></i> Perlu Verifikasi Lanjutan
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bagian Jika Gangguan Ditemukan (CONFIRMED) -->
                                <div id="sectionConfirmed">
                                    <div class="mb-3">
                                        <label class="form-label text-secondary small">DESKRIPSI KERUSAKAN KOMPONEN</label>
                                        <input type="text" class="form-control bg-dark text-light border-secondary" id="modalComponentDamaged" placeholder="Contoh: Bunglon menempel pada pin isolator fasa R / Flashover kawat">
                                    </div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary small">ID ASET TIANG TERDAPAT GANGGUAN (OPSIONAL)</label>
                                            <input type="number" class="form-control bg-dark text-light border-secondary" id="modalFoundAssetId" placeholder="Masukkan Asset ID jika diketahui">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-secondary small">JARAK LAPANGAN AKTUAL (METER)</label>
                                            <input type="number" class="form-control bg-dark text-light border-secondary" id="modalActualDistanceM" placeholder="Jarak speedometer / GPS dari acuan">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="modalIsComponentMatch" checked>
                                            <label class="form-check-label text-light small" for="modalIsComponentMatch">
                                                Kerusakan komponen sesuai dengan klasifikasi gangguan sistem
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bagian Jika Tidak Ditemukan (NOT_FOUND) -->
                                <div id="sectionNotFound" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label text-secondary small">JALUR / BENTANG YANG TELAH DISISIR</label>
                                        <textarea class="form-control bg-dark text-light border-secondary" id="modalInspectedAreaNotes" rows="2" placeholder="Catat tiang awal sampai tiang akhir yang telah dipatroli secara visual"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-secondary small">ALASAN GANGGUAN TIDAK DITEMUKAN</label>
                                        <select class="form-select bg-dark text-light border-secondary" id="modalNotFoundReason">
                                            <option value="SURJA_PETIR_HILANG_SENDIRI">Surja Petir / Gangguan Temporer Hilang Sendiri</option>
                                            <option value="BINATANG_TERLEPAS">Binatang / Sentuhan Dahan Pohon Lepas Sendiri</option>
                                            <option value="ROW_BERSIH_TIDAK_ADA_FLASH">ROW Bersih & Tidak Ada Bekas Flashover</option>
                                            <option value="KENDALA_MEDAN_AKSES">Kendala Akses Medan / Malam Hari</option>
                                            <option value="LAINNYA">Lainnya</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Meta Data Petugas & Waktu -->
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-secondary small">NAMA PETUGAS PATROLI / YANTEK</label>
                                        <input type="text" class="form-control bg-dark text-light border-secondary" id="modalTechnicianName" value="TIM_YANTEK_DISTRIBUSI">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-secondary small">WAKTU PEMERIKSAAN</label>
                                        <input type="datetime-local" class="form-control bg-dark text-light border-secondary" id="modalInspectedAt" value="<?= date('Y-m-d\TH:i') ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-secondary small">CATATAN TEMUAN PATROLI</label>
                                    <textarea class="form-control bg-dark text-light border-secondary" id="modalFindingNotes" rows="2" placeholder="Catatan detail temuan patroli di lapangan..."></textarea>
                                </div>

                                <button type="button" class="btn btn-warning w-100 fw-bold py-2" id="btnSubmitOutcome">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> SIMPAN HASIL PEMERIKSAAN
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let currentCaseUuid = '<?= esc($result['case_uuid'] ?? '') ?>';
    let currentGisUrl = '<?= esc($result['gis_url'] ?? site_url('gis')) ?>';
    let currentLat = <?= ($result['fault_point'] && isset($result['fault_point']['lat'])) ? (float)$result['fault_point']['lat'] : -7.4478 ?>;
    let currentLng = <?= ($result['fault_point'] && isset($result['fault_point']['lng'])) ? (float)$result['fault_point']['lng'] : 112.7183 ?>;
    let lastCalculatedResult = <?= json_encode($result) ?>;

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
        radius: 120,
        color: '#ef4444',
        fillColor: '#f87171',
        fillOpacity: 0.2
    }).addTo(map);

    let pathPolyline = null;

    faultMarker.bindPopup('<b>Titik Dugaan Gangguan</b><br>Lat: ' + currentLat.toFixed(5) + '<br>Lng: ' + currentLng.toFixed(5)).openPopup();

    function updateMiniMap(lat, lng, assetLabel, poleSeq) {
        if (!lat || !lng) return;
        currentLat = lat;
        currentLng = lng;
        map.setView([lat, lng], 16);
        faultMarker.setLatLng([lat, lng]);
        pulseCircle.setLatLng([lat, lng]);
        faultMarker.bindPopup('<b>Titik Gangguan: ' + (assetLabel || '') + '</b><br>Lat: ' + lat.toFixed(5) + '<br>Lng: ' + lng.toFixed(5)).openPopup();

        // Render transline polyline path if sequence coordinates exist
        if (poleSeq && poleSeq.length > 0) {
            const latlngs = [];
            poleSeq.forEach(p => {
                if (p.from_coords && p.from_coords.lat) latlngs.push([p.from_coords.lat, p.from_coords.lng]);
                if (p.to_coords && p.to_coords.lat) latlngs.push([p.to_coords.lat, p.to_coords.lng]);
            });
            if (pathPolyline) map.removeLayer(pathPolyline);
            if (latlngs.length > 1) {
                pathPolyline = L.polyline(latlngs, {color: '#38bdf8', weight: 3, opacity: 0.7}).addTo(map);
            }
        }
    }

    // Cascading: Change ULP -> Update Feeders
    document.getElementById('ulpId').addEventListener('change', async function() {
        const ulpId = this.value;
        const feederSelect = document.getElementById('feederId');
        feederSelect.innerHTML = '<option value="">-- Memuat Penyulang... --</option>';

        // Clear obsolete protection devices
        const devSelect = document.getElementById('protectionDeviceId');
        devSelect.innerHTML = '<option value="">-- Pilih Perangkat Proteksi --</option>';

        if (!ulpId) {
            feederSelect.innerHTML = '<option value="">-- Pilih ULP Dahulu --</option>';
            return;
        }

        try {
            const res = await fetch('<?= site_url('api/fault-locator/feeders-by-ulp') ?>/' + ulpId);
            const json = await res.json();
            if (json.status === 'success' && json.data) {
                feederSelect.innerHTML = '<option value="">-- Pilih Penyulang --</option>';
                json.data.forEach(f => {
                    const opt = document.createElement('option');
                    opt.value = f.id;
                    opt.innerText = f.nama_penyulang + ' (' + (f.kode_penyulang || '') + ')';
                    feederSelect.appendChild(opt);
                });
            }
        } catch(e) {
            console.error('Error fetching feeders:', e);
            feederSelect.innerHTML = '<option value="">Gagal memuat penyulang</option>';
        }
    });

    // Cascading: Change Feeder -> Update Protection Devices
    document.getElementById('feederId').addEventListener('change', async function() {
        const feederId = this.value;
        const devSelect = document.getElementById('protectionDeviceId');
        devSelect.innerHTML = '<option value="">-- Memuat Perangkat... --</option>';

        if (!feederId) {
            devSelect.innerHTML = '<option value="">-- Pilih Penyulang Dahulu --</option>';
            return;
        }

        try {
            const res = await fetch('<?= site_url('api/fault-locator/protection-devices') ?>/' + feederId);
            const json = await res.json();
            if (json.status === 'success' && json.data) {
                devSelect.innerHTML = '<option value="">-- Pilih Perangkat Proteksi --</option>';
                if (json.data.length === 0) {
                    devSelect.innerHTML = '<option value="">Tidak ada perangkat proteksi terdaftar pada penyulang ini</option>';
                } else {
                    json.data.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.id;
                        opt.innerText = d.kode_asset + ' - ' + d.nama_asset + ' (' + d.jenis_asset + ')';
                        devSelect.appendChild(opt);
                    });
                }
            }
        } catch(e) {
            console.error('Error fetching protection devices:', e);
            devSelect.innerHTML = '<option value="">Gagal memuat perangkat proteksi</option>';
        }
    });

    // Toggle Source Type UI
    document.querySelectorAll('input[name="sourceType"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const cont = document.getElementById('protectionDeviceContainer');
            if (this.value === 'PROTECTION') {
                cont.style.display = 'block';
            } else {
                cont.style.display = 'none';
            }
        });
    });

    // Locate Fault Action
    document.getElementById('btnLocateFault').addEventListener('click', async function() {
        const feederId = document.getElementById('feederId').value;
        if (!feederId) {
            alert('Silakan pilih penyulang terlebih dahulu.');
            return;
        }

        const srcType = document.querySelector('input[name="sourceType"]:checked').value;
        const protId = document.getElementById('protectionDeviceId').value;
        if (srcType === 'PROTECTION' && !protId) {
            alert('Silakan pilih perangkat proteksi (Recloser / PMCB / LBS) sebagai titik acuan.');
            return;
        }

        const relayDistVal = document.getElementById('relayDistanceM').value;

        const payload = {
            ulp_id: document.getElementById('ulpId').value,
            feeder_id: feederId,
            source_type: srcType,
            protection_device_id: protId ? parseInt(protId) : null,
            fault_time: document.getElementById('faultTimestamp').value,
            current_r: parseFloat(document.getElementById('currentR').value) || 0,
            current_s: parseFloat(document.getElementById('currentS').value) || 0,
            current_t: parseFloat(document.getElementById('currentT').value) || 0,
            current_n: parseFloat(document.getElementById('currentN').value) || 0,
            conductor_type: document.getElementById('conductorType').value,
            relay_distance_m: relayDistVal ? parseFloat(relayDistVal) : null,
        };

        const btn = document.getElementById('btnLocateFault');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> MENGHITUNG...';

        try {
            const res = await fetch('<?= site_url('api/fault-locator/locate') ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (json.status === 'success') {
                updateResultUI(json.data);
            } else {
                alert('Gagal menghitung lokasi gangguan: ' + (json.message || 'Unknown error'));
            }
        } catch(e) {
            console.error(e);
            alert('Terjadi kesalahan saat menghubungkan ke service Fault Locator.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-crosshairs me-2"></i> HITUNG LOKASI GANGGUAN';
        }
    });

    // Field Dispatch Action
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

    // Save Case Action (Track A)
    document.getElementById('btnSaveCase').addEventListener('click', async function() {
        if (!lastCalculatedResult) {
            alert('Belum ada hasil analisis gangguan yang dapat disimpan.');
            return;
        }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Menyimpan...';

        try {
            const res = await fetch('<?= site_url('api/fault-locator/save-case') ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(lastCalculatedResult)
            });
            const json = await res.json();
            if (json.status === 'success') {
                alert('Kasus Gangguan ' + (json.case_number || '') + ' berhasil disimpan!');
                window.location.reload();
            } else {
                alert('Gagal menyimpan kasus: ' + (json.message || 'Unknown error'));
            }
        } catch(e) {
            console.error(e);
            alert('Terjadi kesalahan saat menyimpan kasus gangguan.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Simpan Kasus';
        }
    });

    // Outcome Type Toggle in Modal
    document.querySelectorAll('input[name="outcomeType"]').forEach(r => {
        r.addEventListener('change', function() {
            const secConf = document.getElementById('sectionConfirmed');
            const secNotFound = document.getElementById('sectionNotFound');
            if (this.value === 'CONFIRMED') {
                secConf.style.display = 'block';
                secNotFound.style.display = 'none';
            } else if (this.value === 'NOT_FOUND') {
                secConf.style.display = 'none';
                secNotFound.style.display = 'block';
            } else {
                secConf.style.display = 'block';
                secNotFound.style.display = 'none';
            }
        });
    });

    // Open Record Outcome Modal
    document.querySelectorAll('.btn-record-outcome').forEach(btn => {
        btn.addEventListener('click', function() {
            const caseId = this.getAttribute('data-case-id');
            const caseNum = this.getAttribute('data-case-number');
            const feederName = this.getAttribute('data-feeder-name');

            document.getElementById('modalCaseId').value = caseId;
            document.getElementById('modalCaseNumberDisplay').innerText = caseNum;
            document.getElementById('modalFeederDisplay').innerText = feederName;

            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRecordOutcome'));
            modal.show();
        });
    });

    // Submit Patrol Outcome (Track A & B)
    document.getElementById('btnSubmitOutcome').addEventListener('click', async function() {
        const caseId = document.getElementById('modalCaseId').value;
        if (!caseId) {
            alert('Kasus tidak valid.');
            return;
        }

        const outcomeType = document.querySelector('input[name="outcomeType"]:checked').value;
        const payload = {
            case_id: parseInt(caseId),
            outcome_type: outcomeType,
            technician_name: document.getElementById('modalTechnicianName').value,
            inspected_at: document.getElementById('modalInspectedAt').value,
            finding_notes: document.getElementById('modalFindingNotes').value,
        };

        if (outcomeType === 'CONFIRMED') {
            const astId = document.getElementById('modalFoundAssetId').value;
            const actDist = document.getElementById('modalActualDistanceM').value;
            payload.found_asset_id = astId ? parseInt(astId) : null;
            payload.actual_distance_m = actDist ? parseFloat(actDist) : null;
            payload.component_damaged = document.getElementById('modalComponentDamaged').value;
            payload.is_component_match = document.getElementById('modalIsComponentMatch').checked ? 1 : 0;
        } else if (outcomeType === 'NOT_FOUND') {
            payload.inspected_area_notes = document.getElementById('modalInspectedAreaNotes').value;
            payload.not_found_reason = document.getElementById('modalNotFoundReason').value;
        }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Menyimpan...';

        try {
            const res = await fetch('<?= site_url('api/fault-locator/record-outcome') ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (json.status === 'success') {
                alert('Hasil patroli lapangan berhasil dicatat!\nStatus: ' + json.follow_up_status);
                window.location.reload();
            } else {
                alert('Gagal mencatat hasil: ' + (json.message || 'Unknown error'));
            }
        } catch(e) {
            console.error(e);
            alert('Terjadi kesalahan saat menyimpan hasil patroli.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> SIMPAN HASIL PEMERIKSAAN';
        }
    });

    function updateResultUI(data) {
        lastCalculatedResult = data;
        currentCaseUuid = data.case_uuid;
        currentGisUrl = data.gis_url;

        document.getElementById('resCaseUuid').innerText = data.case_uuid;
        document.getElementById('resConfidence').innerText = data.confidence_percent + '%';
        document.getElementById('resDistance').innerText = Number(data.calculated_distance_m).toLocaleString() + ' Meter';
        document.getElementById('resFeederName').innerText = data.feeder_name || 'Feeder';
        document.getElementById('resTotalSpan').innerText = Number(data.feeder_span_meters || 0).toLocaleString();
        document.getElementById('resAnchorDesc').innerText = data.anchor_description || '';
        document.getElementById('resSegmentName').innerText = data.transline_segment || '-';
        document.getElementById('resDasarBadge').innerText = data.dasar_perhitungan || 'ESTIMASI';
        document.getElementById('resDasarLabel').innerText = data.dasar_perhitungan_label || '';

        const formulaBox = document.getElementById('resFormulaDerivation');
        if (formulaBox) {
            formulaBox.innerText = data.formula_derivation || '';
        }

        // Fault classification
        if (data.fault_classification) {
            document.getElementById('resFaultTypeLabel').innerText = data.fault_classification.fault_type_label;
            document.getElementById('resActiveCurrent').innerText = data.fault_classification.active_current_a + ' A';
        }

        // Insufficient data alert
        const alertBox = document.getElementById('insufficientDataAlert');
        if (data.calculation_status === 'DATA_TIDAK_CUKUP') {
            alertBox.style.display = 'block';
            document.getElementById('resExplanationText').innerText = data.explanation || '';
            const mpList = document.getElementById('missingParametersList');
            mpList.innerHTML = '';
            if (data.missing_parameters) {
                data.missing_parameters.forEach(mp => {
                    const li = document.createElement('li');
                    li.innerText = mp;
                    mpList.appendChild(li);
                });
            }
        } else {
            alertBox.style.display = 'none';
        }

        // Outcome Badge
        const badge = document.getElementById('outcomeBadge');
        badge.innerText = data.outcome_status;
        if (data.outcome_status === 'RESOLVED') {
            badge.className = 'badge-resolved';
        } else if (data.outcome_status === 'DATA_TIDAK_CUKUP') {
            badge.className = 'badge-data-tidak-cukup';
        } else if (data.outcome_status === 'DISTANCE_AMBIGUOUS') {
            badge.className = 'badge-ambiguous';
        } else {
            badge.className = 'badge-unresolved';
        }

        // Candidate assets
        const candTd = document.getElementById('resCandidateAsset');
        candTd.innerHTML = '';
        if (data.candidate_assets && data.candidate_assets.length > 0) {
            data.candidate_assets.forEach(c => {
                const sp = document.createElement('span');
                sp.className = 'badge bg-secondary me-1';
                sp.innerText = c.kode_asset + ' (' + c.asset_name + ')';
                candTd.appendChild(sp);
            });
        } else {
            candTd.innerHTML = '<span class="text-secondary">Tidak ditemukan kandidat dalam rentang toleransi</span>';
        }

        // Coordinates & Map
        if (data.fault_point && data.fault_point.lat && data.fault_point.lng) {
            document.getElementById('resCoords').innerText = data.fault_point.lat.toFixed(6) + ', ' + data.fault_point.lng.toFixed(6);
            let candName = (data.candidate_assets && data.candidate_assets.length > 0) ? data.candidate_assets[0].kode_asset : '';
            updateMiniMap(data.fault_point.lat, data.fault_point.lng, candName, data.pole_sequence);
        }

        // Pole Sequence Table
        const tbody = document.getElementById('poleSequenceBody');
        tbody.innerHTML = '';
        if (data.pole_sequence && data.pole_sequence.length > 0) {
            document.getElementById('poleSeqCount').innerText = data.pole_sequence.length + ' Bentang';
            data.pole_sequence.forEach(p => {
                const tr = document.createElement('tr');
                if (p.is_fault_zone) tr.className = 'row-fault-zone';

                tr.innerHTML = `
                    <td class="text-center font-monospace">${p.seq_no}</td>
                    <td>${p.from_pole_kode}</td>
                    <td>${p.to_pole_kode}</td>
                    <td class="text-end">${Number(p.span_meters).toFixed(1)}</td>
                    <td class="text-end fw-bold">${Number(p.cumulative_meters).toFixed(1)}</td>
                    <td>${p.is_fault_zone ? '<span class="badge bg-danger text-white"><i class="fa-solid fa-crosshairs me-1"></i> ZONA GANGGUAN</span>' : '<span class="badge bg-dark text-secondary">Normal</span>'}</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            document.getElementById('poleSeqCount').innerText = '0 Bentang';
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-secondary py-3">Tidak ada transline tiang JTM terpetakan untuk titik acuan ini.</td></tr>';
        }

        const gisBtn = document.getElementById('btnOpenGis');
        if (gisBtn && data.gis_url) {
            gisBtn.href = data.gis_url;
        }
    }
</script>
</body>
</html>
