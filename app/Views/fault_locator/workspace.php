<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?><?= esc($title ?? 'Fault Locator Command Center') ?><?= $this->endSection() ?>
<?= $this->section('page_title') ?>Fault Locator Command Center (20 kV)<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('identifikasi') ?>" class="text-decoration-none">Identifikasi Gangguan</a></li>
<li class="breadcrumb-item active">Fault Locator</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    .kpi-stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px;
        text-align: center;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .kpi-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }
    .kpi-stat-value {
        font-size: 1.4rem;
        font-weight: 700;
        line-height: 1.2;
        font-family: 'Outfit', sans-serif;
    }
    .kpi-stat-label {
        font-size: 0.72rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 6px;
    }
    #miniMap {
        height: 450px;
        width: 100%;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
    }
    .row-fault-zone {
        background-color: #fee2e2 !important;
        border-left: 4px solid #ef4444;
    }
    .row-fault-zone td {
        background-color: #fee2e2 !important;
        font-weight: 600;
        color: #991b1b !important;
    }
    .badge-resolved {
        background-color: #10b981;
        color: #ffffff;
        font-weight: 700;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.8rem;
    }
    .badge-ambiguous {
        background-color: #f59e0b;
        color: #000000;
        font-weight: 700;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.8rem;
    }
    .badge-data-tidak-cukup {
        background-color: #f97316;
        color: #ffffff;
        font-weight: 700;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.8rem;
    }
    .badge-unresolved {
        background-color: #ef4444;
        color: #ffffff;
        font-weight: 700;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.8rem;
    }
</style>

<!-- Top Operational KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-stat-card">
            <div class="kpi-stat-value text-primary"><?= number_format($summary['active_translines']) ?> Segmen</div>
            <div class="kpi-stat-label">Transline Tiang JTM (<?= number_format($summary['network_span_meters']) ?>m)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-stat-card">
            <div class="kpi-stat-value text-success"><?= number_format($summary['active_assets']) ?></div>
            <div class="kpi-stat-label">Aset Aktif Terverifikasi</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-stat-card">
            <div class="kpi-stat-value text-warning">KAIDAH IMPEDANSI</div>
            <div class="kpi-stat-label">20 kV LL / 11.55 kV LN | NGR 40Ω</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-stat-card">
            <div class="kpi-stat-value text-info">FLI-2.0.0</div>
            <div class="kpi-stat-label">Engine Berbasis Graf JTM</div>
        </div>
    </div>
</div>

<!-- Main Workspace Grid -->
<div class="row g-4">

    <!-- Left Panel: Parameter Gangguan (Telemetry & Filter Form) -->
    <div class="col-lg-4">
        <div class="card card-modern shadow-sm border-0 mb-4" style="border-top: 3px solid #0284c7 !important;">
            <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold text-dark mb-0">
                    <i class="fas fa-sliders text-primary me-2"></i> Parameter Gangguan
                </h3>
                <span class="badge bg-primary-subtle text-primary border">Input Telemetri</span>
            </div>
            <div class="card-body p-3">
                <form id="faultForm">
                    <!-- Cascading ULP -->
                    <div class="mb-3">
                        <label class="form-label text-dark small fw-bold">UNIT LAYANAN PELANGGAN (ULP) <span class="text-danger">*</span></label>
                        <select class="form-select select2" id="ulpId">
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
                        <label class="form-label text-dark small fw-bold">PENYULANG (FEEDER) <span class="text-danger">*</span></label>
                        <select class="form-select select2" id="feederId">
                            <option value="">-- Pilih Penyulang --</option>
                            <?php if (!empty($feeders)): ?>
                                <?php foreach ($feeders as $f): ?>
                                    <option value="<?= esc($f['id']) ?>" <?= (isset($result['feeder_id']) && $result['feeder_id'] == $f['id']) ? 'selected' : '' ?>>
                                        <?= esc($f['nama_penyulang']) ?> (<?= esc($f['kode_penyulang']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="form-text text-muted small">Penyulang aktif tersinkronisasi otomatis dengan ULP yang dipilih.</div>
                        
                        <!-- Feeder Network Context Banner -->
                        <div id="feederContextBanner" class="p-2.5 rounded-3 bg-light border mt-2" style="font-size:0.75rem;">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-primary fw-bold"><i class="fas fa-network-wired me-1"></i> Topologi &amp; Konduktor Resmi</span>
                                <span id="feederReadinessPill" class="badge bg-secondary-subtle text-dark border">Memuat...</span>
                            </div>
                            <div id="feederContextDetails" class="text-muted small">
                                Pilih penyulang untuk memuat topologi transline resmi dan tipe konduktor otomatis.
                            </div>
                            <div id="feederSegmentsCollapse" class="collapse mt-2 pt-2 border-top">
                                <div class="fw-semibold text-dark mb-1" style="font-size:0.72rem;">
                                    <i class="fas fa-layer-group text-primary me-1"></i> Urutan Tiang &amp; Spesifikasi Konduktor Per Segmen:
                                </div>
                                <div class="table-responsive" style="max-height: 180px; overflow-y: auto;">
                                    <table class="table table-sm table-bordered align-middle mb-0" style="font-size:0.68rem;">
                                        <thead class="table-light text-dark sticky-top">
                                            <tr>
                                                <th style="width: 8%;">#</th>
                                                <th style="width: 25%;">Dari Tiang</th>
                                                <th style="width: 25%;">Ke Tiang</th>
                                                <th class="text-end" style="width: 14%;">Bentang</th>
                                                <th style="width: 28%;">Konduktor</th>
                                            </tr>
                                        </thead>
                                        <tbody id="feederSegmentsPreviewBody">
                                            <tr><td colspan="5" class="text-center text-muted py-2">Belum ada segmen terpilih</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sumber Acuan Gangguan: GI vs Peralatan Proteksi -->
                    <div class="mb-3 p-3 rounded-3 bg-light border">
                        <label class="form-label text-dark small fw-bold mb-2">
                            <i class="fas fa-map-pin text-danger me-1"></i> SUMBER ACUAN AWAL GANGGUAN
                        </label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="sourceType" id="srcGI" value="GI" checked>
                            <label class="form-check-label text-dark small" for="srcGI">
                                <strong>Dari Gardu Induk (GI)</strong>
                                <div class="text-muted" style="font-size:0.75rem;">Perhitungan berawal dari tiang JTM pertama di pangkal penyulang.</div>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sourceType" id="srcProtection" value="PROTECTION">
                            <label class="form-check-label text-dark small" for="srcProtection" id="srcProtectionLabel">
                                <strong>Dari Peralatan Proteksi (Recloser / PMCB / LBS)</strong>
                                <div class="text-muted" style="font-size:0.75rem;">Berawal dari tiang JTM tempat peralatan proteksi terpasang (perangkat bukan node transline).</div>
                            </label>
                        </div>

                        <!-- Notice jika perangkat proteksi belum ada -->
                        <div id="noProtectionNotice" class="alert alert-warning border text-dark small py-2 px-3 mt-2 mb-0" style="display: none; font-size: 0.75rem;">
                            <i class="fas fa-triangle-exclamation me-1"></i> Data aset proteksi (Recloser / PMCB / LBS) belum tersedia untuk penyulang ini. Perhitungan didukung penuh menggunakan titik acuan <strong>Gardu Induk (GI) Pangkal</strong>.
                        </div>

                        <!-- Dropdown Peralatan Proteksi (Visible if PROTECTION chosen) -->
                        <div class="mt-3" id="protectionDeviceContainer" style="display: none;">
                            <label class="form-label text-muted small">PILIH PERALATAN PROTEKSI</label>
                            <select class="form-select border-warning" id="protectionDeviceId">
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
                        <label class="form-label text-dark small fw-bold">WAKTU GANGGUAN (TIMESTAMP)</label>
                        <input type="datetime-local" class="form-control" id="faultTimestamp" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>

                    <!-- Masukan Arus Fasa R, S, T, dan Netral -->
                    <div class="p-3 rounded-3 bg-light border mb-3">
                        <label class="form-label text-dark small fw-bold mb-2">
                            <i class="fas fa-wave-square text-warning me-1"></i> ARUS GANGGUAN RELAY (AMPERE)
                        </label>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1">Fasa R (A)</label>
                                <input type="number" class="form-control text-danger fw-bold" id="currentR" value="1450" step="5">
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1">Fasa S (A)</label>
                                <input type="number" class="form-control text-warning fw-bold" id="currentS" value="1420" step="5">
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1">Fasa T (A)</label>
                                <input type="number" class="form-control text-primary fw-bold" id="currentT" value="1440" step="5">
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1">Netral / GFR (A)</label>
                                <input type="number" class="form-control text-dark fw-bold" id="currentN" value="15" step="5">
                            </div>
                        </div>
                        <div class="form-text text-muted mt-2" style="font-size:0.75rem;">
                            Klasifikasi otomatis jenis gangguan (3-PH, 2-PH, 1-LG, 2-LG) berdasarkan sebaran arus fasa dan netral.
                        </div>
                    </div>

                    <!-- Tipe Konduktor JTM -->
                    <div class="mb-3">
                        <label class="form-label text-dark small fw-bold">TIPE KONDUKTOR JTM</label>
                        <select class="form-select" id="conductorType">
                            <?php foreach ($conductorCatalog as $k => $c): ?>
                                <option value="<?= esc($k) ?>" <?= ($k === 'AAAC 150 mm²') ? 'selected' : '' ?>>
                                    <?= esc($k) ?> (Z1 = <?= esc($c['z1_ohm_km']) ?> Ω/km)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Pembacaan Jarak Relay Langsung (Opsional) -->
                    <div class="mb-4">
                        <label class="form-label text-dark small fw-bold">JARAK RELAY NUMERIK (OPSIONAL / METER)</label>
                        <div class="input-group">
                            <input type="number" class="form-control text-primary fw-bold" id="relayDistanceM" placeholder="Kosongkan jika hitung via impedansi arus" step="10">
                            <span class="input-group-text bg-light text-muted">Meter</span>
                        </div>
                        <div class="form-text text-muted small">Jika relay jarak / DFR telah menampilkan nilai km pasti, masukkan di sini.</div>
                    </div>

                    <button type="button" class="btn btn-danger w-100 py-2 fw-bold" id="btnLocateFault">
                        <i class="fas fa-crosshairs me-2"></i> HITUNG LOKASI GANGGUAN
                    </button>
                </form>
            </div>
        </div>

        <!-- Transparency & Engineering Principles -->
        <div class="card card-modern shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent border-bottom py-3">
                <h4 class="card-title font-weight-bold text-dark mb-0">
                    <i class="fas fa-book-bookmark text-info me-2"></i> Kaidah Teknik Elektro 20 kV
                </h4>
            </div>
            <div class="card-body p-3 small text-muted">
                <p class="mb-2">
                    <strong class="text-dark">1. Tegangan &amp; Pentanahan:</strong><br>
                    Tegangan nominal fasa-fasa $V_{LL} = 20\text{ kV}$, fasa-netral $V_{LN} \approx 11,547\text{ V}$. Sistem distribusi UP3 Sidoarjo beroperasi dengan NGR 40 $\Omega$.
                </p>
                <p class="mb-2">
                    <strong class="text-warning">2. Batasan Arus Hubung Tanah (1-LG):</strong><br>
                    Arus maksimum 1-LG dibatasi oleh NGR: $I_{f\text{ max}} \approx \frac{V_{LN}}{R_N} \approx 288\text{ A}$. Tanpa nilai reaktansi murni ($X$), besaran arus 1-LG didominasi oleh tahanan kontak ($R_f$) dan pentanahan, bukan panjang bentang semata.
                </p>
                <p class="mb-0">
                    <strong class="text-primary">3. Integritas Tiang JTM:</strong><br>
                    Transline hanya menghubungkan fisik tiang JTM. Gardu induk dan recloser dipetakan ke tiang JTM terverifikasi terdekat sebagai titik acuan ($0\text{ m}$).
                </p>
            </div>
        </div>
    </div>

    <!-- Middle & Right Panel: Calculation Results, Technical Details, SLD & Map -->
    <div class="col-lg-8">
        
        <!-- Result Panel Card -->
        <div class="card card-modern shadow-sm border-0 mb-4" style="border-top: 3px solid #10b981 !important;">
            <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold text-dark mb-0">
                    <i class="fas fa-square-poll-vertical text-info me-2"></i> Hasil Analisis Fault Locator
                </h3>
                <span id="outcomeBadge" class="<?= ($result['outcome_status'] === 'RESOLVED') ? 'badge-resolved' : (($result['outcome_status'] === 'DATA_TIDAK_CUKUP') ? 'badge-data-tidak-cukup' : (($result['outcome_status'] === 'DISTANCE_AMBIGUOUS') ? 'badge-ambiguous' : 'badge-unresolved')) ?>">
                    <?= esc($result['outcome_status'] ?? 'RESOLVED') ?>
                </span>
            </div>
            <div class="card-body p-4">
                
                <!-- Top Status Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded-3 border">
                            <span class="text-muted small d-block mb-1">KLASIFIKASI GANGGUAN</span>
                            <span id="resFaultTypeLabel" class="text-warning fw-bold fs-6">
                                <?= esc($result['fault_classification']['fault_type_label'] ?? 'Gangguan 3 Fasa') ?>
                            </span>
                            <div class="text-dark small mt-1">Arus Aktif: <strong id="resActiveCurrent" class="text-danger"><?= esc($result['fault_classification']['active_current_a'] ?? 1450) ?> A</strong></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded-3 border">
                            <span class="text-muted small d-block mb-1">ESTIMASI JARAK DARI ACUAN</span>
                            <span id="resDistance" class="text-primary fw-bold fs-5">
                                <?= number_format($result['calculated_distance_m'] ?? 0, 1) ?> Meter
                            </span>
                            <div class="text-muted small mt-1">Dari Titik Nol Acuan JTM</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded-3 border">
                            <span class="text-muted small d-block mb-1">TINGKAT KEYAKINAN</span>
                            <span id="resConfidence" class="text-success fw-bold fs-5"><?= esc($result['confidence_percent'] ?? 88) ?>%</span>
                            <div class="text-muted small mt-1">Case: <code id="resCaseUuid" class="text-primary"><?= esc($result['case_uuid']) ?></code></div>
                        </div>
                    </div>
                </div>

                <!-- Insufficient Data Alert Box (if applicable) -->
                <div id="insufficientDataAlert" class="alert alert-warning border text-dark p-3 rounded-3 mb-4" style="display: <?= ($result['calculation_status'] === 'DATA_TIDAK_CUKUP') ? 'block' : 'none' ?>;">
                    <h6 class="fw-bold mb-2 text-dark"><i class="fas fa-triangle-exclamation text-warning me-1"></i> STATUS: DATA TELEMETRI BELUM CUKUP UNTUK JARAK PRESISI</h6>
                    <p class="small mb-2 text-dark" id="resExplanationText">
                        <?= esc($result['explanation'] ?? '') ?>
                    </p>
                    <div class="small fw-bold text-dark mb-1">Parameter yang masih diperlukan:</div>
                    <ul class="small mb-2 ps-3 text-muted" id="missingParametersList">
                        <?php if (!empty($result['missing_parameters'])): ?>
                            <?php foreach ($result['missing_parameters'] as $mp): ?>
                                <li><?= esc($mp) ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                    <div class="small text-primary fw-bold">
                        <i class="fas fa-info-circle me-1"></i> Penelusuran topologi di bawah ditampilkan sebagai <u>ESTIMASI TOPOLOGI KANDIDAT</u>.
                    </div>
                </div>

                <!-- Technical Details Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <tbody>
                            <tr>
                                <th style="width:28%" class="bg-light text-dark">Penyulang &amp; Acuan Awal</th>
                                <td>
                                    <strong class="text-dark" id="resFeederName"><?= esc($result['feeder_name']) ?></strong> (Total Span: <span id="resTotalSpan"><?= number_format($result['feeder_span_meters'] ?? 0) ?></span> m)<br>
                                    <span class="text-primary small" id="resAnchorDesc"><?= esc($result['anchor_description'] ?? '') ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-light text-dark">Dasar Perhitungan</th>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border" id="resDasarBadge"><?= esc($result['dasar_perhitungan'] ?? '') ?></span>
                                    <span class="text-muted small ms-2" id="resDasarLabel"><?= esc($result['dasar_perhitungan_label'] ?? '') ?></span>
                                    <?php if (!empty($result['formula_derivation'])): ?>
                                        <div class="text-muted small mt-1 font-monospace bg-light p-2 rounded" id="resFormulaDerivation" style="font-size:0.78rem; white-space: pre-wrap;">
                                            <?= esc($result['formula_derivation']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-light text-dark">Kandidat Tiang Terduga</th>
                                <td id="resCandidateAsset" class="fw-bold text-dark">
                                    <?php if (!empty($result['candidate_assets'])): ?>
                                        <?php foreach ($result['candidate_assets'] as $c): ?>
                                            <span class="badge bg-light text-dark border me-1"><i class="fas fa-bolt text-danger me-1"></i><?= esc($c['kode_asset']) ?> (<?= esc($c['asset_name']) ?>)</span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Tidak ditemukan kandidat dalam rentang toleransi</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-light text-dark">Segmen Zona Gangguan</th>
                                <td class="text-primary">
                                    <span id="resSegmentName" class="fw-bold"><?= esc($result['transline_segment'] ?? '-') ?></span>
                                    <?php if (!empty($result['fault_zone_segment'])): ?>
                                        <span class="text-muted small ms-2">
                                            (<?= esc($result['fault_zone_segment']['from_pole_kode']) ?> &rarr; <?= esc($result['fault_zone_segment']['to_pole_kode']) ?>)
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-light text-dark">Estimasi Koordinat (Sidoarjo)</th>
                                <td id="resCoords" class="font-monospace text-primary">
                                    <?= ($result['fault_point']) ? esc($result['fault_point']['lat'] . ', ' . $result['fault_point']['lng']) : '-7.4478, 112.7183' ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Dispatch & Deep-Link Action Banner -->
                <div class="alert alert-light border border-danger p-3 rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                    <div>
                        <span class="badge bg-danger text-white me-2"><i class="fas fa-truck-fast"></i> FIELD DISPATCH</span>
                        <strong class="text-dark">Teruskan Hasil Gangguan ke Tim Lapangan &amp; GIS</strong>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success btn-sm fw-bold" id="btnSaveCase">
                            <i class="fas fa-floppy-disk me-1"></i> Simpan Kasus
                        </button>
                        <a id="btnOpenGis" href="<?= esc($result['gis_url'] ?? site_url('gis')) ?>" target="_blank" class="btn btn-outline-primary btn-sm fw-bold">
                            <i class="fas fa-map-location-dot me-1"></i> Buka di Peta GIS
                        </a>
                        <button class="btn btn-danger btn-sm fw-bold" id="btnCreateDispatch">
                            <i class="fas fa-paper-plane me-1"></i> Buat Surat Tugas
                        </button>
                    </div>
                </div>

                <!-- Sequence of JTM Pole Translines Table -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fas fa-route text-primary me-2"></i> URUTAN TRANSLINE TIANG JTM (HILIR DARI ACUAN)
                        </h6>
                        <span class="badge bg-light text-dark border small" id="poleSeqCount">
                            <?= count($result['pole_sequence'] ?? []) ?> Bentang
                        </span>
                    </div>
                    <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-sm table-bordered align-middle mb-0" id="tablePoleSequence">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 5%">#</th>
                                    <th style="width: 25%">Dari Tiang</th>
                                    <th style="width: 25%">Ke Tiang</th>
                                    <th style="width: 15%" class="text-end">Bentang (m)</th>
                                    <th style="width: 15%" class="text-end">Kumulatif (m)</th>
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
                                                    <span class="badge bg-danger text-white"><i class="fas fa-crosshairs me-1"></i> ZONA GANGGUAN</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border">Normal</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-3">Belum ada segmen transline tiang JTM terpetakan untuk titik acuan ini.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Spatial Fault Point Leaflet Map Preview -->
        <div class="card card-modern shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                <h4 class="card-title font-weight-bold text-dark mb-0">
                    <i class="fas fa-map-location-dot text-danger me-2"></i> Pratinjau Spasial Titik Gangguan (Sidoarjo)
                </h4>
                <span class="badge bg-danger text-white" id="mapStatusBadge">FAULT POINT LOCATED</span>
            </div>
            <div class="card-body p-3">
                <div id="miniMap"></div>
            </div>
        </div>

        <!-- Controlled AI Feedback Calibration & Feeder Readiness (Track B & C) -->
        <div class="card card-modern shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                <h4 class="card-title font-weight-bold text-dark mb-0">
                    <i class="fas fa-brain text-warning me-2"></i> Kalibrasi AI Umpan Balik Terkontrol &amp; Kesiapan Jaringan Penyulang
                </h4>
                <span class="badge <?= ($calibrationSummary['is_calibration_unlocked'] ?? false) ? 'bg-success' : 'bg-warning text-dark' ?>">
                    <i class="fas fa-shield-halved me-1"></i> <?= esc($calibrationSummary['calibration_status'] ?? 'DATA_COLLECTION_MODE') ?>
                </span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3">
                        <div class="kpi-stat-card">
                            <div class="kpi-stat-value text-warning"><?= esc($calibrationSummary['confirmed_samples'] ?? 0) ?> / <?= esc($calibrationSummary['min_required_samples'] ?? 15) ?></div>
                            <div class="kpi-stat-label">Sampel Terkonfirmasi</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="kpi-stat-card">
                            <div class="kpi-stat-value text-primary"><?= ($calibrationSummary['mean_absolute_error_meters'] !== null) ? number_format($calibrationSummary['mean_absolute_error_meters'], 1) . ' m' : 'N/A' ?></div>
                            <div class="kpi-stat-label">Mean Absolute Error (MAE)</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="kpi-stat-card">
                            <div class="kpi-stat-value text-success"><?= esc($calibrationSummary['accuracy_rate_percent'] ?? 0) ?>%</div>
                            <div class="kpi-stat-label">Akurasi Toleransi (≤250m)</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="kpi-stat-card">
                            <div class="kpi-stat-value text-dark"><?= esc($readinessSummary['summary']['ready_count'] ?? 0) ?> / <?= esc($readinessSummary['summary']['total_feeders'] ?? 134) ?></div>
                            <div class="kpi-stat-label">Penyulang Siap Terverifikasi</div>
                        </div>
                    </div>
                </div>

                <div class="p-3 rounded-3 bg-light border mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <strong class="text-dark small"><i class="fas fa-layer-group text-primary me-1"></i> Segregasi Kumpulan Data Lapangan (Audit Trail)</strong>
                        <span class="badge bg-light text-muted border font-monospace" style="font-size:0.75rem;">Model: <?= esc($calibrationSummary['active_model_version'] ?? 'CALIB-V1-PHYSICS-BASELINE') ?></span>
                    </div>
                    <div class="row g-2 text-center small">
                        <div class="col-3">
                            <div class="p-2 border border-success rounded bg-white">
                                <div class="text-success fw-bold"><?= esc($calibrationSummary['segregated_datasets']['CONFIRMED'] ?? 0) ?></div>
                                <div class="text-muted" style="font-size:0.7rem;">TERKONFIRMASI</div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 border border-danger rounded bg-white">
                                <div class="text-danger fw-bold"><?= esc($calibrationSummary['segregated_datasets']['NOT_FOUND'] ?? 0) ?></div>
                                <div class="text-muted" style="font-size:0.7rem;">TIDAK DITEMUKAN</div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 border border-warning rounded bg-white">
                                <div class="text-warning fw-bold"><?= esc($calibrationSummary['segregated_datasets']['AMBIGUOUS_VERIFIED'] ?? 0) ?></div>
                                <div class="text-muted" style="font-size:0.7rem;">AMBIGU / VERIFIKASI</div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 border border-info rounded bg-white">
                                <div class="text-info fw-bold"><?= esc($calibrationSummary['case_follow_up_statuses']['MENUNGGU_PEMERIKSAAN'] ?? 0) ?></div>
                                <div class="text-muted" style="font-size:0.7rem;">MENUNGGU PATROLI</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 text-muted" style="font-size:0.75rem;">
                        <i class="fas fa-lock text-warning me-1"></i> <strong>Invarian Kalibrasi:</strong> Formula fisika 20 kV baseline terlindungi secara deterministik dari perubahan liar. Parameter hanya dapat dikalibrasi setelah minimal 15 sampel lapangan terkonfirmasi terkumpul.
                    </div>
                </div>
            </div>
        </div>

        <!-- Historical Fault Cases & Patrol Findings (Track A) -->
        <div class="card card-modern shadow-sm border-0 mb-4">
            <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                <h4 class="card-title font-weight-bold text-dark mb-0">
                    <i class="fas fa-clock-rotate-left text-primary me-2"></i> Riwayat Kasus Analisis &amp; Rekam Hasil Patroli Lapangan
                </h4>
                <span class="badge bg-light text-dark border" id="caseHistoryCount">
                    <?= count($caseHistory ?? []) ?> Kasus
                </span>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0" id="tableCaseHistory">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 14%">No. Kasus</th>
                                <th style="width: 16%">Penyulang &amp; Sumber</th>
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
                                        <td class="font-monospace text-primary small fw-bold"><?= esc($c['case_number']) ?></td>
                                        <td>
                                            <?php
                                                $fdrName = !empty($c['nama_penyulang']) 
                                                    ? $c['nama_penyulang'] 
                                                    : (!empty($c['penyulang_id']) ? 'Penyulang ID #' . $c['penyulang_id'] : 'Penyulang Belum Terdefinisi (Data Uji/Arsip)');
                                                $rCurr = (float)($c['current_r'] ?? 0);
                                                $sCurr = (float)($c['current_s'] ?? 0);
                                                $tCurr = (float)($c['current_t'] ?? 0);
                                                $nCurr = (float)($c['current_n'] ?? 0);
                                                $hasCurrent = ($rCurr > 0 || $sCurr > 0 || $tCurr > 0 || $nCurr > 0);
                                            ?>
                                            <div class="text-dark fw-bold"><?= esc($fdrName) ?></div>
                                            <div class="text-muted" style="font-size:0.72rem;"><?= esc($c['source_type'] ?: 'GI') ?> &bull; <?= esc($c['fault_type'] ?: '1-PHASE') ?></div>
                                        </td>
                                        <td>
                                            <?php if ($hasCurrent): ?>
                                                <span class="text-danger small fw-bold"><?= number_format($rCurr, 1) ?> / <?= number_format($sCurr, 1) ?> / <?= number_format($tCurr, 1) ?> A</span>
                                                <div class="text-muted" style="font-size:0.72rem;">N: <?= number_format($nCurr, 1) ?> A</div>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-muted border small">Tanpa Telemetri Arus</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold text-dark">
                                            <?php if (($c['target_distance_meters'] ?? 0) > 0): ?>
                                                <div><?= number_format($c['target_distance_meters'], 1) ?> m</div>
                                                <div class="text-muted fw-normal" style="font-size:0.68rem;">&plusmn; <?= number_format($c['distance_tolerance_meters'] ?? 250, 0) ?> m</div>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
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
                                                    <strong class="text-success"><i class="fas fa-check me-1"></i><?= esc($lf['component_damaged'] ?: 'Ditemukan') ?></strong>
                                                    <div class="text-muted" style="font-size:0.7rem;">&Delta; Jarak: <?= esc($lf['distance_error_meters']) ?>m</div>
                                                <?php elseif ($lf['outcome_type'] === 'NOT_FOUND'): ?>
                                                    <strong class="text-danger"><i class="fas fa-xmark me-1"></i>Tidak Ditemukan</strong>
                                                    <div class="text-muted" style="font-size:0.7rem;"><?= esc($lf['not_found_reason'] ?: 'Penyisiran nihil') ?></div>
                                                <?php else: ?>
                                                    <span class="text-warning"><?= esc($lf['outcome_type']) ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Belum ada inspeksi</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-outline-warning btn-sm py-0 px-2 btn-record-outcome" data-case-id="<?= esc($c['id']) ?>" data-case-number="<?= esc($c['case_number']) ?>" data-feeder-name="<?= esc($c['nama_penyulang'] ?? '') ?>">
                                                <i class="fas fa-clipboard-check me-1"></i> Catat
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">Belum ada riwayat kasus analisis gangguan tersimpan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Modal Catat Hasil Patroli / Outcome Lapangan -->
<div class="modal fade" id="modalRecordOutcome" tabindex="-1" aria-labelledby="modalRecordOutcomeLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content card-modern shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title font-weight-bold text-dark" id="modalRecordOutcomeLabel">
                    <i class="fas fa-clipboard-check text-warning me-2"></i> Rekam Hasil Patroli Lapangan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formRecordOutcome">
                    <input type="hidden" id="modalCaseId" name="case_id">

                    <div class="alert alert-light border p-3 mb-3">
                        <div class="row g-2 small">
                            <div class="col-md-6">No. Kasus: <strong class="text-primary" id="modalCaseNumberDisplay">-</strong></div>
                            <div class="col-md-6">Penyulang: <strong class="text-dark" id="modalFeederDisplay">-</strong></div>
                        </div>
                    </div>

                    <!-- Jenis Hasil Outcome -->
                    <div class="mb-3">
                        <label class="form-label text-dark small fw-bold">HASIL TEMUAN PATROLI LAPANGAN</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="outcomeType" id="outConfirmed" value="CONFIRMED" checked>
                                <label class="form-check-label text-success fw-bold small" for="outConfirmed">
                                    <i class="fas fa-circle-check me-1"></i> Gangguan Ditemukan
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="outcomeType" id="outNotFound" value="NOT_FOUND">
                                <label class="form-check-label text-danger fw-bold small" for="outNotFound">
                                    <i class="fas fa-circle-xmark me-1"></i> Tidak Ditemukan (Nihil)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="outcomeType" id="outAmbiguous" value="AMBIGUOUS">
                                <label class="form-check-label text-warning fw-bold small" for="outAmbiguous">
                                    <i class="fas fa-triangle-exclamation me-1"></i> Perlu Verifikasi Lanjutan
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Bagian Jika Gangguan Ditemukan (CONFIRMED) -->
                    <div id="sectionConfirmed">
                        <div class="mb-3">
                            <label class="form-label text-dark small fw-bold">DESKRIPSI KERUSAKAN KOMPONEN</label>
                            <input type="text" class="form-control" id="modalComponentDamaged" placeholder="Contoh: Bunglon menempel pada pin isolator fasa R / Flashover kawat">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-dark small fw-bold">ID ASET TIANG TERDAPAT GANGGUAN (OPSIONAL)</label>
                                <input type="number" class="form-control" id="modalFoundAssetId" placeholder="Masukkan Asset ID jika diketahui">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-dark small fw-bold">JARAK LAPANGAN AKTUAL (METER)</label>
                                <input type="number" class="form-control" id="modalActualDistanceM" placeholder="Jarak speedometer / GPS dari acuan">
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="modalIsComponentMatch" checked>
                                <label class="form-check-label text-dark small" for="modalIsComponentMatch">
                                    Kerusakan komponen sesuai dengan klasifikasi gangguan sistem
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Bagian Jika Tidak Ditemukan (NOT_FOUND) -->
                    <div id="sectionNotFound" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label text-dark small fw-bold">JALUR / BENTANG YANG TELAH DISISIR</label>
                            <textarea class="form-control" id="modalInspectedAreaNotes" rows="2" placeholder="Catat tiang awal sampai tiang akhir yang telah dipatroli secara visual"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-dark small fw-bold">ALASAN GANGGUAN TIDAK DITEMUKAN</label>
                            <select class="form-select" id="modalNotFoundReason">
                                <option value="SURJA_PETIR_HILANG_SENDIRI">Surja Petir / Gangguan Temporer Hilang Sendiri</option>
                                <option value="BINATANG_TERLEPAS">Binatang / Sentuhan Dahan Pohon Lepas Sendiri</option>
                                <option value="ROW_BERSIH_TIDAK_ADA_FLASH">ROW Bersih &amp; Tidak Ada Bekas Flashover</option>
                                <option value="KENDALA_MEDAN_AKSES">Kendala Akses Medan / Malam Hari</option>
                                <option value="LAINNYA">Lainnya</option>
                            </select>
                        </div>
                    </div>

                    <!-- Meta Data Petugas & Waktu -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-dark small fw-bold">NAMA PETUGAS PATROLI / YANTEK</label>
                            <input type="text" class="form-control" id="modalTechnicianName" value="TIM_YANTEK_DISTRIBUSI">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-dark small fw-bold">WAKTU PEMERIKSAAN</label>
                            <input type="datetime-local" class="form-control" id="modalInspectedAt" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-dark small fw-bold">CATATAN TEMUAN PATROLI</label>
                        <textarea class="form-control" id="modalFindingNotes" rows="2" placeholder="Catatan detail temuan patroli di lapangan..."></textarea>
                    </div>

                    <button type="button" class="btn btn-warning w-100 fw-bold py-2 text-dark" id="btnSubmitOutcome">
                        <i class="fas fa-floppy-disk me-1"></i> SIMPAN HASIL PEMERIKSAAN
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
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
                pathPolyline = L.polyline(latlngs, {color: '#0284c7', weight: 4, opacity: 0.8}).addTo(map);
            }
        }
    }

    // Initial polyline if initial result has sequence
    if (lastCalculatedResult && lastCalculatedResult.pole_sequence) {
        updateMiniMap(currentLat, currentLng, (lastCalculatedResult.candidate_assets && lastCalculatedResult.candidate_assets[0]) ? lastCalculatedResult.candidate_assets[0].kode_asset : '', lastCalculatedResult.pole_sequence);
    }

    // Cascading ULP -> Feeder
    document.getElementById('ulpId').addEventListener('change', async function() {
        const ulpId = this.value;
        const feederSelect = document.getElementById('feederId');
        feederSelect.innerHTML = '<option value="">Memuat penyulang...</option>';

        if (!ulpId) {
            feederSelect.innerHTML = '<option value="">-- Pilih ULP Dahulu --</option>';
            return;
        }

        try {
            const res = await fetch('<?= site_url('api/fault-locator/feeders-by-ulp') ?>/' + ulpId);
            const data = await res.json();
            feederSelect.innerHTML = '<option value="">-- Pilih Penyulang --</option>';
            if (data.status === 'success' && data.data) {
                data.data.forEach(f => {
                    const opt = document.createElement('option');
                    opt.value = f.id;
                    opt.innerText = f.nama_penyulang + ' (' + f.kode_penyulang + ')';
                    feederSelect.appendChild(opt);
                });
            }
        } catch (e) {
            console.error('Error fetching feeders:', e);
            feederSelect.innerHTML = '<option value="">Gagal memuat penyulang</option>';
        }
    });

    // Feeder Network Context Loader
    async function loadFeederNetworkContext(feederId) {
        if (!feederId) return;

        const banner = document.getElementById('feederContextBanner');
        const pill = document.getElementById('feederReadinessPill');
        const contextDetails = document.getElementById('feederContextDetails');
        const segCollapse = document.getElementById('feederSegmentsCollapse');
        const condSelect = document.getElementById('conductorType');
        const srcProtRadio = document.getElementById('srcProtection');
        const protNotice = document.getElementById('noProtectionNotice');
        const protContainer = document.getElementById('protectionDeviceContainer');
        const protSelect = document.getElementById('protectionDeviceId');

        pill.className = 'badge bg-secondary-subtle text-dark border';
        pill.innerText = 'Menganalisis...';

        try {
            const res = await fetch('<?= site_url('api/fault-locator/feeder-network-context') ?>/' + feederId);
            const json = await res.json();
            if (json.status === 'success' && json.data) {
                const data = json.data;

                // 1. Auto-select official dominant conductor
                if (data.conductor && data.conductor.official_type) {
                    for (let i = 0; i < condSelect.options.length; i++) {
                        if (condSelect.options[i].value === data.conductor.official_type) {
                            condSelect.selectedIndex = i;
                            break;
                        }
                    }
                }

                // 2. Display readiness and topology summary
                const topo = data.topology || {};
                const isReady = topo.is_topology_ready;
                pill.className = isReady ? 'badge bg-success-subtle text-success border border-success' : 'badge bg-warning-subtle text-dark border border-warning';
                pill.innerText = isReady ? 'TOPOLOGI SIAP' : 'TOPOLOGI BELUM LENGKAP';

                let detailsHtml = `<strong>Bentang Resmi:</strong> ${Number(topo.total_span_meters || 0).toLocaleString()} m (${topo.total_span_km || 0} km) &bull; `;
                detailsHtml += `<strong>Tiang JTM:</strong> ${topo.valid_jtm_poles_count || 0} tiang &bull; `;
                detailsHtml += `<strong>Segmen:</strong> ${topo.segment_count || 0} span<br>`;
                detailsHtml += `<strong>Konduktor Dominan:</strong> <span class="badge bg-light text-primary border">${data.conductor.official_type}</span> `;
                if (data.conductor.is_hybrid) {
                    detailsHtml += `<span class="badge bg-warning-subtle text-dark border">Campuran</span> `;
                }
                detailsHtml += `<a class="text-primary text-decoration-none fw-bold ms-1" data-bs-toggle="collapse" href="#feederSegmentsCollapse" role="button" aria-expanded="false" aria-controls="feederSegmentsCollapse">[Lihat Segmen]</a>`;
                contextDetails.innerHTML = detailsHtml;

                // 3. Render Segments Preview Table
                const segTbody = document.getElementById('feederSegmentsPreviewBody');
                if (data.segments_preview && data.segments_preview.length > 0) {
                    segTbody.innerHTML = '';
                    data.segments_preview.forEach(s => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${s.seq_no}</td>
                            <td>${s.from_pole}</td>
                            <td>${s.to_pole}</td>
                            <td class="text-end">${Number(s.span_meters).toFixed(1)}m</td>
                            <td><span class="badge bg-light text-muted border" style="font-size:0.65rem;">${s.conductor_type}</span></td>
                        `;
                        segTbody.appendChild(tr);
                    });
                } else {
                    segTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-2">Tidak ada segmen transline tiang JTM terpetakan untuk penyulang ini.</td></tr>';
                }

                // 4. Update Protection Devices dropdown
                const pDevs = data.protection_devices || {};
                protSelect.innerHTML = '<option value="">-- Pilih Perangkat Proteksi --</option>';
                if (pDevs.available && pDevs.devices && pDevs.devices.length > 0) {
                    srcProtRadio.disabled = false;
                    protNotice.style.display = 'none';
                    pDevs.devices.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.id;
                        opt.innerText = d.display_label;
                        protSelect.appendChild(opt);
                    });
                } else {
                    srcProtRadio.disabled = true;
                    if (srcProtRadio.checked) {
                        document.getElementById('srcGI').checked = true;
                        protContainer.style.display = 'none';
                    }
                    protNotice.style.display = 'block';
                }
            }
        } catch(e) {
            console.error('Error loading feeder network context:', e);
            contextDetails.innerHTML = '<span class="text-danger">Gagal memuat konteks topologi resmi.</span>';
        }
    }

    document.getElementById('feederId').addEventListener('change', function() {
        loadFeederNetworkContext(this.value);
    });

    if (document.getElementById('feederId').value) {
        loadFeederNetworkContext(document.getElementById('feederId').value);
    }

    // Toggle Protection Device container
    document.querySelectorAll('input[name="sourceType"]').forEach(el => {
        el.addEventListener('change', function() {
            const container = document.getElementById('protectionDeviceContainer');
            if (this.value === 'PROTECTION') {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
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
            alert('Silakan pilih peralatan proteksi yang menjadi acuan.');
            return;
        }

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
            relay_distance_m: document.getElementById('relayDistanceM').value ? parseFloat(document.getElementById('relayDistanceM').value) : null
        };

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menghitung Solusi Graf JTM...';

        try {
            const res = await fetch('<?= site_url('api/fault-locator/locate') ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.status === 'success' && data.data) {
                lastCalculatedResult = data.data;
                currentCaseUuid = data.data.case_uuid;
                updateResultsUI(data.data);
            } else {
                alert(data.message || 'Gagal menghitung lokasi gangguan.');
            }
        } catch(e) {
            console.error('Error locating fault:', e);
            alert('Terjadi kesalahan koneksi server.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-crosshairs me-2"></i> HITUNG LOKASI GANGGUAN';
        }
    });

    // Save Case Action
    document.getElementById('btnSaveCase').addEventListener('click', async function() {
        if (!lastCalculatedResult) {
            alert('Lakukan perhitungan terlebih dahulu sebelum menyimpan kasus.');
            return;
        }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';

        try {
            const res = await fetch('<?= site_url('api/fault-locator/save-case') ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(lastCalculatedResult)
            });
            const data = await res.json();
            if (data.status === 'success') {
                alert('Kasus ' + data.case_number + ' berhasil disimpan ke database SIDAK TEJO.');
                location.reload();
            } else {
                alert('Gagal menyimpan: ' + (data.message || ''));
            }
        } catch(e) {
            console.error('Error saving case:', e);
            alert('Gagal menghubungi server.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-floppy-disk me-1"></i> Simpan Kasus';
        }
    });

    // Create Dispatch Action
    document.getElementById('btnCreateDispatch').addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menerbitkan...';

        try {
            const res = await fetch('<?= site_url('api/fault-locator/dispatch') ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({case_uuid: currentCaseUuid})
            });
            const data = await res.json();
            if (data.status === 'success') {
                alert('Surat Tugas Lapangan berhasil diterbitkan: ' + data.dispatch.dispatch_id + ' untuk ' + data.dispatch.assigned_team);
            } else {
                alert('Gagal menerbitkan tugas.');
            }
        } catch(e) {
            console.error('Error dispatching:', e);
            alert('Gagal menerbitkan surat tugas.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Buat Surat Tugas';
        }
    });

    // Record Outcome Modal Handlers
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

    document.querySelectorAll('input[name="outcomeType"]').forEach(el => {
        el.addEventListener('change', function() {
            const secConf = document.getElementById('sectionConfirmed');
            const secNot = document.getElementById('sectionNotFound');
            if (this.value === 'CONFIRMED') {
                secConf.style.display = 'block';
                secNot.style.display = 'none';
            } else if (this.value === 'NOT_FOUND') {
                secConf.style.display = 'none';
                secNot.style.display = 'block';
            } else {
                secConf.style.display = 'block';
                secNot.style.display = 'none';
            }
        });
    });

    document.getElementById('btnSubmitOutcome').addEventListener('click', async function() {
        const caseId = document.getElementById('modalCaseId').value;
        if (!caseId) return;

        const outcomeType = document.querySelector('input[name="outcomeType"]:checked').value;
        const payload = {
            case_id: parseInt(caseId),
            outcome_type: outcomeType,
            found_asset_id: document.getElementById('modalFoundAssetId').value ? parseInt(document.getElementById('modalFoundAssetId').value) : null,
            component_damaged: document.getElementById('modalComponentDamaged').value,
            actual_distance_m: document.getElementById('modalActualDistanceM').value ? parseFloat(document.getElementById('modalActualDistanceM').value) : null,
            is_component_match: document.getElementById('modalIsComponentMatch').checked ? 1 : 0,
            inspected_area_notes: document.getElementById('modalInspectedAreaNotes').value,
            not_found_reason: document.getElementById('modalNotFoundReason').value,
            technician_name: document.getElementById('modalTechnicianName').value,
            inspected_at: document.getElementById('modalInspectedAt').value,
            finding_notes: document.getElementById('modalFindingNotes').value
        };

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';

        try {
            const res = await fetch('<?= site_url('api/fault-locator/record-outcome') ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.status === 'success') {
                alert('Hasil temuan patroli berhasil disimpan. Status: ' + data.follow_up_status);
                location.reload();
            } else {
                alert(data.message || 'Gagal menyimpan temuan patroli.');
            }
        } catch(e) {
            console.error('Error recording outcome:', e);
            alert('Gagal menghubungi server.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-floppy-disk me-1"></i> SIMPAN HASIL PEMERIKSAAN';
        }
    });

    // Update Results UI
    function updateResultsUI(data) {
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
                sp.className = 'badge bg-light text-dark border me-1';
                sp.innerHTML = '<i class="fas fa-bolt text-danger me-1"></i>' + c.kode_asset + ' (' + c.asset_name + ')';
                candTd.appendChild(sp);
            });
        } else {
            candTd.innerHTML = '<span class="text-muted">Tidak ditemukan kandidat dalam rentang toleransi</span>';
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
                    <td>${p.is_fault_zone ? '<span class="badge bg-danger text-white"><i class="fas fa-crosshairs me-1"></i> ZONA GANGGUAN</span>' : '<span class="badge bg-light text-muted border">Normal</span>'}</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            document.getElementById('poleSeqCount').innerText = '0 Bentang';
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Tidak ada transline tiang JTM terpetakan untuk titik acuan ini.</td></tr>';
        }

        const gisBtn = document.getElementById('btnOpenGis');
        if (gisBtn && data.gis_url) {
            gisBtn.href = data.gis_url;
        }
    }
</script>
<?= $this->endSection() ?>
