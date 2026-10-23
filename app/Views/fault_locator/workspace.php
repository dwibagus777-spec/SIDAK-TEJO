<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?><?= esc($title ?? 'Fault Locator Command Center') ?><?= $this->endSection() ?>
<?= $this->section('page_title') ?>Fault Locator Command Center (20 kV)<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('identifikasi') ?>" class="text-decoration-none">Identifikasi Gangguan</a></li>
<li class="breadcrumb-item active">Fault Locator</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Quick Navigation Bar -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary-subtle text-primary border"><i class="fas fa-microchip me-1"></i> Engine: <?= esc($summary['engine_version'] ?? 'FLI-2.1.0') ?></span>
        <span class="badge bg-warning-subtle text-dark border"><i class="fas fa-bolt me-1"></i> 20 kV / NGR 40Ω</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= site_url('identifikasi') ?>" class="btn btn-sm btn-warning text-dark font-weight-bold">
            <i class="fas fa-bolt-lightning me-1"></i> Identifikasi Gangguan Penyulang
        </a>
        <a href="<?= site_url('gis') ?>" class="btn btn-sm btn-outline-primary font-weight-bold">
            <i class="fas fa-map me-1"></i> Peta Spasial GIS
        </a>
    </div>
</div>

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
    .fl-map-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        align-items: center;
        padding: 8px 12px;
        background: #0f172a;
        color: #f8fafc;
        border-radius: 8px;
        font-size: 0.74rem;
        margin-bottom: 10px;
    }
    .fl-map-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-weight: 600;
    }
    .fl-legend-line {
        width: 22px;
        height: 4px;
        border-radius: 2px;
        display: inline-block;
    }
    .fl-legend-dot {
        width: 11px;
        height: 11px;
        border-radius: 50%;
        display: inline-block;
        border: 2px solid #ffffff;
    }
    .fl-source-badge-icon {
        background: #7c3aed;
        color: #ffffff;
        font-weight: 700;
        font-size: 0.72rem;
        padding: 4px 10px;
        border-radius: 6px;
        border: 2px solid #ddd6fe;
        box-shadow: 0 3px 10px rgba(0,0,0,0.35);
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .fl-source-badge-gi {
        background: #0f172a;
        border-color: #38bdf8;
    }
    .fl-fault-callout-icon {
        background: rgba(127, 29, 29, 0.94);
        color: #fecaca;
        font-weight: 700;
        font-size: 0.72rem;
        padding: 4px 10px;
        border-radius: 6px;
        border: 1.5px solid #ef4444;
        box-shadow: 0 3px 10px rgba(0,0,0,0.35);
        white-space: nowrap;
    }
    .fl-fault-cross-marker {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: #ef4444;
        color: #ffffff;
        border: 3px solid #ffffff;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.35), 0 3px 8px rgba(0,0,0,0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 13px;
        line-height: 1;
    }
    @keyframes flTargetPulse {
        0% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.75), 0 0 0 0 rgba(239, 68, 68, 0.4);
            transform: scale(1);
        }
        50% {
            box-shadow: 0 0 0 9px rgba(239, 68, 68, 0.28), 0 0 0 18px rgba(239, 68, 68, 0.1);
            transform: scale(1.06);
        }
        100% {
            box-shadow: 0 0 0 16px rgba(239, 68, 68, 0), 0 0 0 26px rgba(239, 68, 68, 0);
            transform: scale(1);
        }
    }
    .fl-target-pulse-marker {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: radial-gradient(circle, #ef4444 40%, #b91c1c 100%);
        color: #ffffff;
        border: 3px solid #ffffff;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.4), 0 4px 12px rgba(0,0,0,0.45);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 14px;
        line-height: 1;
        animation: flTargetPulse 2s infinite ease-in-out;
        cursor: pointer;
    }
    @keyframes flCorridorDashFlow {
        to {
            stroke-dashoffset: -32;
        }
    }
    .fl-candidate-corridor-flow path {
        animation: flCorridorDashFlow 1.6s linear infinite;
    }
    @keyframes flPoleHighlightPulse {
        0% {
            box-shadow: 0 0 0 2px #38bdf8, 0 0 0 5px rgba(56, 189, 248, 0.6);
            transform: scale(1);
        }
        50% {
            box-shadow: 0 0 0 4px #0284c7, 0 0 0 14px rgba(56, 189, 248, 0.25);
            transform: scale(1.18);
        }
        100% {
            box-shadow: 0 0 0 2px #38bdf8, 0 0 0 5px rgba(56, 189, 248, 0.6);
            transform: scale(1);
        }
    }
    .fl-pole-highlight-marker {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: rgba(14, 165, 233, 0.35);
        border: 3px solid #0284c7;
        animation: flPoleHighlightPulse 1.8s infinite ease-in-out;
        pointer-events: none;
    }
    @media (prefers-reduced-motion: reduce) {
        .fl-target-pulse-marker,
        .fl-candidate-corridor-flow path,
        .fl-pole-highlight-marker {
            animation: none !important;
            transition: none !important;
        }
    }
    .fl-seq-strip {
        display: flex;
        align-items: center;
        gap: 6px;
        overflow-x: auto;
        padding: 10px 12px;
        background: #0f172a;
        border-radius: 8px;
        margin-top: 10px;
        border: 1px solid #1e293b;
    }
    .fl-seq-node {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        padding: 5px 9px;
        border-radius: 6px;
        background: #1e293b;
        color: #e2e8f0;
        border: 1px solid #334155;
        min-width: 92px;
        flex-shrink: 0;
        font-size: 0.68rem;
    }
    .fl-seq-node.is-source {
        background: rgba(124, 58, 237, 0.25);
        border-color: #a78bfa;
        color: #ede9fe;
    }
    .fl-seq-node.is-fault-zone {
        background: rgba(245, 158, 11, 0.22);
        border-color: #f59e0b;
        color: #fef3c7;
    }
    .fl-seq-arrow {
        color: #38bdf8;
        font-weight: 700;
        font-size: 0.78rem;
        flex-shrink: 0;
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

                    <!-- Masukan Arus (A) & Tegangan Fasa (kV) R, S, T, serta Arus Netral (A) -->
                    <div class="p-3 rounded-3 bg-light border mb-3" id="telemetryInputsCard">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mb-2">
                            <label class="form-label text-dark small fw-bold mb-0">
                                <i class="fas fa-wave-square text-warning me-1"></i> TELEMETRI ARUS (A) &amp; TEGANGAN FASA (kV)
                            </label>
                            <span class="badge bg-info-subtle text-primary border" style="font-size:0.68rem;">R/S/T: A &amp; kV | N: A</span>
                        </div>

                        <!-- Fasa R: Arus (A) & Tegangan (kV) -->
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1" for="currentR">Arus Fasa R (A)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control text-danger fw-bold" id="currentR" value="1450" min="0" max="50000" step="0.1" inputmode="decimal">
                                    <span class="input-group-text bg-white text-muted fw-semibold">A</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1" for="voltageR">Tegangan Fasa R (kV)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control text-danger fw-bold" id="voltageR" placeholder="Opsional (cth: 21.26)" min="0" max="35" step="0.01" inputmode="decimal">
                                    <span class="input-group-text bg-white text-muted fw-semibold">kV</span>
                                </div>
                            </div>
                        </div>

                        <!-- Fasa S: Arus (A) & Tegangan (kV) -->
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1" for="currentS">Arus Fasa S (A)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control text-warning fw-bold" id="currentS" value="1420" min="0" max="50000" step="0.1" inputmode="decimal">
                                    <span class="input-group-text bg-white text-muted fw-semibold">A</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1" for="voltageS">Tegangan Fasa S (kV)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control text-warning fw-bold" id="voltageS" placeholder="Opsional (cth: 0.00)" min="0" max="35" step="0.01" inputmode="decimal">
                                    <span class="input-group-text bg-white text-muted fw-semibold">kV</span>
                                </div>
                            </div>
                        </div>

                        <!-- Fasa T: Arus (A) & Tegangan (kV) -->
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1" for="currentT">Arus Fasa T (A)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control text-primary fw-bold" id="currentT" value="1440" min="0" max="50000" step="0.1" inputmode="decimal">
                                    <span class="input-group-text bg-white text-muted fw-semibold">A</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1" for="voltageT">Tegangan Fasa T (kV)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control text-primary fw-bold" id="voltageT" placeholder="Opsional (cth: 21.00)" min="0" max="35" step="0.01" inputmode="decimal">
                                    <span class="input-group-text bg-white text-muted fw-semibold">kV</span>
                                </div>
                            </div>
                        </div>

                        <!-- Netral / GFR: Tetap Arus (A) Tanpa Tegangan N -->
                        <div class="row g-2 align-items-center pt-1 border-top mt-2">
                            <div class="col-6">
                                <label class="form-label text-muted small mb-1" for="currentN">Netral / GFR (A)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control text-dark fw-bold" id="currentN" value="15" min="0" max="50000" step="0.1" inputmode="decimal">
                                    <span class="input-group-text bg-white text-muted fw-semibold">A</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted mt-3" style="font-size:0.7rem; line-height:1.25;">
                                    <i class="fas fa-circle-info text-info me-1"></i>Netral/GFR khusus arus residu (A); tidak meminta tegangan fasa N. Nilai <code>0.00 kV</code> pada fasa R/S/T dibedakan dari kolom kosong.
                                </div>
                            </div>
                        </div>

                        <div id="telemetryValidationAlert" class="alert alert-danger py-2 px-3 mt-2 mb-0 small" style="display:none; font-size:0.74rem;"></div>

                        <div class="form-text text-muted mt-2" style="font-size:0.73rem;">
                            Klasifikasi indikasi gangguan memadukan arus R/S/T/N dan tegangan fasa R/S/T (contoh: S = 0.00 kV dengan R &amp; T ~21 kV serta arus N terukur &rarr; Indikasi Gangguan Tanah Fasa S-N / DGR).
                        </div>
                    </div>

                    <!-- Konduktor Otomatis dari Jalur Jaringan Hasil Traversal -->
                    <div class="mb-3 p-3 rounded-3 bg-light border" id="autoConductorBox">
                        <input type="hidden" id="conductorType" value="AUTO">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label text-dark small fw-bold mb-0">
                                <i class="fas fa-bolt-lightning text-primary me-1"></i> KONDUKTOR JARINGAN (OTOMATIS DARI JALUR)
                            </label>
                            <span class="badge bg-primary-subtle text-primary border" id="autoConductorStatusBadge" style="font-size:0.68rem;">
                                <?= esc($result['traversed_conductor']['status'] ?? 'OTOMATIS') ?>
                            </span>
                        </div>
                        <div class="fw-bold text-dark small" id="autoConductorDisplay">
                            <?= esc($result['traversed_conductor']['display_label'] ?? ($result['electrical_parameters']['conductor_type'] ?? 'Belum tersedia')) ?>
                        </div>
                        <div class="text-muted mt-1" id="autoConductorSubtext" style="font-size:0.72rem;">
                            <?= esc($result['traversed_conductor']['status_message'] ?? 'Diambil otomatis dari gis_translines aktif pada jalur hilir terpilih.') ?>
                        </div>
                        <div id="autoConductorRangesList" class="mt-2 pt-2 border-top small" style="font-size:0.7rem; <?= empty($result['traversed_conductor']['segment_ranges']) ? 'display:none;' : '' ?>">
                            <?php if (!empty($result['traversed_conductor']['segment_ranges'])): ?>
                                <?php foreach (array_slice($result['traversed_conductor']['segment_ranges'], 0, 5) as $sr): ?>
                                    <div class="text-muted"><i class="fas fa-route text-primary me-1"></i><?= esc($sr['range_text']) ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Panjang Jalur Topologi (Otomatis) vs Jarak Gangguan Terukur Relay (Opsional) -->
                    <div class="mb-3 p-3 rounded-3 bg-light border" id="topologyPathLengthBox">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-dark small fw-bold" id="topologyPathLengthLabel">
                                <i class="fas fa-ruler-horizontal text-success me-1"></i> <?= esc($result['topology_path_length_label'] ?? 'Panjang jalur dari GI ke ujung feeder (estimasi topologi)') ?>
                            </span>
                            <span class="badge bg-success-subtle text-success border" style="font-size:0.66rem;">Estimasi Topologi</span>
                        </div>
                        <div class="fs-6 fw-bold text-success" id="topologyPathLengthValue">
                            <?php if (isset($result['topology_path_length_m']) && $result['topology_path_length_m'] !== null): ?>
                                <?= number_format((float)$result['topology_path_length_m'], 1) ?> m (<?= number_format(((float)$result['topology_path_length_m']) / 1000.0, 2) ?> km)
                            <?php else: ?>
                                Belum tersedia
                            <?php endif; ?>
                        </div>
                        <div class="text-muted mt-1" id="topologyConstraintNotice" style="font-size:0.72rem;">
                            Dihitung otomatis dari titik sumber acuan (0.0 m) menelusuri transline ke arah hilir hingga ujung penyulang. Bukan hasil pengukuran relay.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-dark small fw-bold" for="relayDistanceM">
                            JARAK GANGGUAN TERUKUR RELAY (OPSIONAL / METER)
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control text-primary fw-bold" id="relayDistanceM" placeholder="Kosongkan bila tidak ada pengukuran relay numerik" min="0" max="250000" step="1">
                            <span class="input-group-text bg-light text-muted">Meter</span>
                        </div>
                        <div class="form-text text-muted small">Hanya diisi bila relay proteksi / fault locator numerik benar-benar menampilkan jarak gangguan terukur.</div>
                    </div>

                    <button type="button" class="btn btn-danger w-100 py-2 fw-bold" id="btnLocateFault">
                        <i class="fas fa-crosshairs me-2"></i> HITUNG &amp; TAMPILKAN ESTIMASI
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
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <div class="bg-light p-3 rounded-3 border h-100">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small">INDIKASI KLASIFIKASI GANGGUAN</span>
                                <span class="badge bg-warning-subtle text-dark border" id="resIndicationConfidenceBadge" style="font-size:0.68rem;">
                                    Keyakinan Indikasi: <span id="resIndicationConfidence"><?= esc($result['fault_classification']['indication_confidence_percent'] ?? 68) ?></span>%
                                </span>
                            </div>
                            <span id="resFaultTypeLabel" class="text-warning fw-bold fs-6 d-block">
                                <?= esc($result['fault_classification']['fault_type_label'] ?? 'Gangguan 3 Fasa') ?>
                            </span>
                            <div class="text-dark small mt-1">
                                Arus Aktif: <strong id="resActiveCurrent" class="text-danger"><?= esc($result['fault_classification']['active_current_a'] ?? 1450) ?> A</strong>
                                <span id="resVoltageSummaryInline" class="text-muted ms-1" style="font-size:0.72rem;"></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light p-3 rounded-3 border h-100">
                            <span class="text-muted small d-block mb-1" id="resTopologyPathCardLabel">
                                <?= esc($result['topology_path_length_label'] ?? 'Panjang jalur dari GI ke ujung feeder (estimasi topologi)') ?>
                            </span>
                            <span id="resTopologyPathLength" class="text-success fw-bold fs-6 d-block">
                                <?php if (isset($result['topology_path_length_m']) && $result['topology_path_length_m'] !== null): ?>
                                    <?= number_format((float)$result['topology_path_length_m'], 1) ?> Meter (<?= number_format(((float)$result['topology_path_length_m']) / 1000.0, 2) ?> km)
                                <?php else: ?>
                                    Belum tersedia
                                <?php endif; ?>
                            </span>
                            <div class="text-dark small mt-1 border-top pt-1">
                                Jarak Terukur Relay:
                                <strong id="resRelayMeasuredDistance" class="text-primary">
                                    <?= (isset($result['relay_measured_distance_m']) && $result['relay_measured_distance_m'] !== null) ? (number_format((float)$result['relay_measured_distance_m'], 1) . ' Meter') : 'Belum diisi' ?>
                                </strong>
                            </div>
                            <div class="text-muted small mt-1" style="font-size:0.72rem;">
                                Estimasi Titik Sistem: <strong id="resDistance" class="text-dark"><?= number_format($result['calculated_distance_m'] ?? 0, 1) ?> Meter</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-light p-3 rounded-3 border h-100">
                            <span class="text-muted small d-block mb-1">KEYAKINAN LOKASI</span>
                            <span id="resConfidence" class="text-success fw-bold fs-5"><?= esc($result['confidence_percent'] ?? 88) ?>%</span>
                            <div class="text-muted small mt-1">Case: <code id="resCaseUuid" class="text-primary"><?= esc($result['case_uuid']) ?></code></div>
                        </div>
                    </div>
                </div>

                <!-- Supporting Evidence for Fault Indication -->
                <div id="supportingEvidenceBox" class="p-3 rounded-3 bg-light border mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mb-1">
                        <strong class="text-dark small"><i class="fas fa-magnifying-glass-chart text-primary me-1"></i> Data Pendukung Indikasi Gangguan (Arus &amp; Tegangan Fasa)</strong>
                        <span class="badge bg-light text-muted border" id="resIndicationBasisBadge" style="font-size:0.68rem;">
                            <?= (!empty($result['fault_classification']['has_voltage_telemetry'])) ? 'Arus + Tegangan Fasa (kV)' : 'Arus Skalar Saja (Tanpa Tegangan Fasa)' ?>
                        </span>
                    </div>
                    <ul class="small mb-0 ps-3 text-dark" id="resSupportingEvidenceList">
                        <?php if (!empty($result['fault_classification']['supporting_evidence'])): ?>
                            <?php foreach ($result['fault_classification']['supporting_evidence'] as $ev): ?>
                                <li><?= esc($ev) ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
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
                                    <strong class="text-dark" id="resFeederName"><?= esc($result['feeder_name']) ?></strong> (Total Span Hilir: <span id="resTotalSpan"><?= number_format($result['feeder_span_meters'] ?? 0) ?></span> m)<br>
                                    <span class="text-primary small" id="resAnchorDesc"><?= esc($result['anchor_description'] ?? '') ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-light text-dark">Konduktor Jalur Traversal</th>
                                <td>
                                    <strong class="text-dark" id="resConductorSummary"><?= esc($result['traversed_conductor']['display_label'] ?? ($result['electrical_parameters']['conductor_type'] ?? 'Belum tersedia')) ?></strong>
                                    <div class="text-muted small" id="resConductorMessage"><?= esc($result['traversed_conductor']['status_message'] ?? '') ?></div>
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-light text-dark">Dasar Perhitungan</th>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border" id="resDasarBadge"><?= esc($result['dasar_perhitungan'] ?? '') ?></span>
                                    <span class="text-muted small ms-2" id="resDasarLabel"><?= esc($result['dasar_perhitungan_label'] ?? '') ?></span>
                                    <div class="text-muted small mt-1 font-monospace bg-light p-2 rounded" id="resFormulaDerivation" style="font-size:0.78rem; white-space: pre-wrap; <?= empty($result['formula_derivation']) ? 'display:none;' : '' ?>"><?= esc($result['formula_derivation'] ?? '') ?></div>
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-light text-dark" style="width:28%">Kandidat Tiang Terduga</th>
                                <td id="resCandidateAsset" class="text-dark">
                                    <?php if (!empty($result['candidate_assets'])): ?>
                                        <?php 
                                            $cands = $result['candidate_assets'];
                                            $totalCands = count($cands);
                                            $isSinglePt = !empty($result['candidate_zone']['is_single_point']);
                                            $cz = $result['candidate_zone'] ?? [];
                                        ?>
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2 pb-2 border-bottom">
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <?php if (!$isSinglePt): ?>
                                                    <span class="badge bg-warning text-dark border fw-bold"><i class="fas fa-layer-group me-1"></i>ZONA KANDIDAT — BELUM PRESISI</span>
                                                    <span class="badge bg-light text-dark border font-monospace">Total: <?= $totalCands ?> Tiang</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger text-white fw-bold"><i class="fas fa-crosshairs me-1"></i>TITIK ESTIMASI PRESISI</span>
                                                    <span class="badge bg-light text-dark border font-monospace"><?= $totalCands ?> Tiang Terdekat</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted small">
                                                <?php if (!$isSinglePt): ?>
                                                    <i class="fas fa-ruler-horizontal text-primary me-1"></i>Rentang: <b><?= number_format(((float)($cz['range_start_m'] ?? 0)) / 1000.0, 2) ?> – <?= number_format(((float)($cz['range_end_m'] ?? 0)) / 1000.0, 2) ?> km</b>
                                                <?php else: ?>
                                                    <i class="fas fa-bullseye text-danger me-1"></i>Estimasi toleransi ±100 m
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="table-responsive mb-2">
                                            <table class="table table-sm table-bordered table-hover align-middle mb-0" style="font-size:0.78rem;">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="text-center" style="width: 5%">#</th>
                                                        <th style="width: 25%">Kode Tiang</th>
                                                        <th style="width: 35%">Nama / Status Aset</th>
                                                        <th style="width: 22%" class="text-end">Kumulatif dari Acuan</th>
                                                        <th style="width: 13%" class="text-center">Aksi Peta</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (array_slice($cands, 0, 5) as $idx => $c): ?>
                                                        <?php 
                                                            $rawName = trim($c['asset_name'] ?? '');
                                                            $rawKode = trim($c['kode_asset'] ?? '');
                                                            $isUnverified = empty($rawName) || str_starts_with(strtoupper($rawName), 'NEW_INGESTED_ASSET_') || $rawName === $rawKode;
                                                            $cumM = isset($c['cumulative_meters']) && $c['cumulative_meters'] !== null ? (float)$c['cumulative_meters'] : null;
                                                        ?>
                                                        <tr>
                                                            <td class="text-center font-monospace text-muted"><?= $idx + 1 ?></td>
                                                            <td><strong class="font-monospace text-dark"><?= esc($rawKode) ?></strong></td>
                                                            <td>
                                                                <?php if ($isUnverified): ?>
                                                                    <span class="badge bg-secondary-subtle text-secondary border fw-normal" style="font-size:0.7rem;"><i class="fas fa-circle-question me-1"></i>Nama aset belum terverifikasi</span>
                                                                <?php else: ?>
                                                                    <span class="text-dark small"><?= esc($rawName) ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-end font-monospace">
                                                                <?= $cumM !== null ? number_format($cumM, 1) . ' m (' . number_format($cumM / 1000.0, 2) . ' km)' : '-' ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <button type="button" class="btn btn-outline-primary btn-xs py-0 px-2 fw-bold"
                                                                    onclick="window.flFocusPole(<?= (float)$c['latitude'] ?>, <?= (float)$c['longitude'] ?>, '<?= esc($rawKode) ?>', <?= $cumM !== null ? $cumM : 'null' ?>, '<?= esc($rawName) ?>')"
                                                                    title="Fokuskan peta ke tiang ini">
                                                                    <i class="fas fa-crosshairs me-1"></i>Fokus
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <?php if ($totalCands > 5): ?>
                                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-1">
                                                <span class="text-muted small">Menampilkan 5 prioritas teratas dari <b><?= $totalCands ?></b> tiang terduga.</span>
                                                <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalAllCandidates" onclick="window.flPopulateCandidateModal()">
                                                    <i class="fas fa-list-ol text-warning me-1"></i> Lihat Semua Kandidat (<?= $totalCands ?>)
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-danger fw-bold"><i class="fas fa-circle-exclamation me-1"></i>Tidak ada kandidat valid pada penyulang ini</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-light text-dark">Segmen Zona Gangguan</th>
                                <td class="text-primary">
                                    <span id="resSegmentName" class="fw-bold"><?= esc($result['transline_segment'] ?? '-') ?></span>
                                    <span id="resSegmentPoles" class="text-muted small ms-2">
                                        <?php if (!empty($result['fault_zone_segment'])): ?>
                                            (<?= esc($result['fault_zone_segment']['from_pole_kode']) ?> &rarr; <?= esc($result['fault_zone_segment']['to_pole_kode']) ?>)
                                        <?php endif; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-light text-dark">Estimasi Koordinat (Sidoarjo)</th>
                                <td id="resCoords" class="font-monospace text-primary">
                                    <?= (!empty($result['fault_point']) && !empty($result['candidate_assets'])) ? esc($result['fault_point']['lat'] . ', ' . $result['fault_point']['lng']) : 'Tidak ada kandidat valid pada penyulang ini' ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Save Case Feedback Banner -->
                <div id="saveCaseStatusBanner" class="alert alert-success border py-2 px-3 mb-3 small" style="display:none;"></div>

                <!-- Dispatch & Deep-Link Action Banner -->
                <div class="alert alert-light border border-danger p-3 rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                    <div>
                        <span class="badge bg-danger text-white me-2"><i class="fas fa-truck-fast"></i> FIELD DISPATCH</span>
                        <strong class="text-dark">Teruskan Hasil Gangguan ke Tim Lapangan &amp; GIS</strong>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-warning btn-sm fw-bold text-dark" id="btnRecordOutcomeCurrentCase" title="Rekam hasil temuan pemeriksaan patroli di lapangan">
                            <i class="fas fa-clipboard-check me-1"></i> Rekam Hasil Lapangan
                        </button>
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
                                    <th style="width: 22%">Dari Tiang</th>
                                    <th style="width: 22%">Ke Tiang</th>
                                    <th style="width: 18%">Konduktor</th>
                                    <th style="width: 11%" class="text-end">Bentang (m)</th>
                                    <th style="width: 11%" class="text-end">Kumulatif (m)</th>
                                    <th style="width: 11%">Status</th>
                                </tr>
                            </thead>
                            <tbody id="poleSequenceBody">
                                <?php if (!empty($result['pole_sequence'])): ?>
                                    <?php foreach ($result['pole_sequence'] as $p): ?>
                                        <tr class="<?= (!empty($p['is_fault_zone'])) ? 'row-fault-zone' : '' ?>">
                                            <td class="text-center font-monospace"><?= esc($p['seq_no']) ?></td>
                                            <td><?= esc($p['from_pole_kode']) ?></td>
                                            <td><?= esc($p['to_pole_kode']) ?></td>
                                            <td><span class="badge bg-light text-dark border" style="font-size:0.68rem;"><?= esc(trim(($p['conductor_type'] ?? 'Belum tersedia') . (($p['conductor_size'] ?? '-') !== '-' ? ' ' . $p['conductor_size'] : ''))) ?></span></td>
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
                                        <td colspan="7" class="text-center text-muted py-3">Belum ada segmen transline tiang JTM terpetakan untuk titik acuan ini.</td>
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
            <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title font-weight-bold text-dark mb-0">
                    <i class="fas fa-map-location-dot text-danger me-2"></i> Pratinjau Spasial Titik &amp; Zona Gangguan Transline
                </h4>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold" id="btnFocusFaultLocation" title="Fokuskan peta ke titik atau zona estimasi gangguan">
                        <i class="fas fa-crosshairs me-1"></i> Fokus Titik/Zona
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary fw-bold" id="btnResetMapFocus" title="Kembalikan tampilan peta ke seluruh jalur hilir feeder">
                        <i class="fas fa-compress me-1"></i> Kembali ke Seluruh Jalur
                    </button>
                    <span class="badge bg-danger text-white" id="mapStatusBadge">FAULT POINT LOCATED</span>
                </div>
            </div>
            <div class="card-body p-3">
                <!-- Spatial Candidate Zone Alert / Callout Box -->
                <div id="candidateZoneNoticeBox" class="alert alert-warning border text-dark p-3 rounded-3 mb-2 small" style="<?= (!empty($result['candidate_zone']) && empty($result['candidate_zone']['is_single_point'])) ? 'display:block;' : 'display:none;' ?>">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mb-1">
                        <strong class="text-dark"><i class="fas fa-layer-group text-warning me-1"></i> <span id="candidateZoneNoticeTitle"><?= esc($result['candidate_zone']['title'] ?? 'ZONA KANDIDAT PENELUSURAN GANGGUAN TANAH (DGR)') ?></span></strong>
                        <span class="badge bg-warning text-dark border" id="candidateZoneRangeBadge">
                            Rentang: <span id="candidateZoneRangeText"><?= number_format($result['candidate_zone']['range_start_m'] ?? 0, 0) ?> m – <?= number_format($result['candidate_zone']['range_end_m'] ?? 0, 0) ?> m</span>
                        </span>
                    </div>
                    <div id="candidateZoneExplanation" class="text-muted mb-1" style="font-size:0.75rem;">
                        <?= esc($result['candidate_zone']['notes'] ?? 'Tanpa nilai impedansi/reaktansi loop dari relay numerik, sistem menampilkan zona kandidat bentang hilir yang diamankan.') ?>
                    </div>
                    <div id="candidateZonePolesWrap" class="mt-1" style="font-size:0.73rem;">
                        <span class="fw-bold text-dark">Tiang Terduga Dalam Zona:</span>
                        <span id="candidateZonePolesList">
                            <?php if (!empty($result['candidate_zone']['candidate_poles'])): ?>
                                <?php foreach (array_slice($result['candidate_zone']['candidate_poles'], 0, 8) as $cp): ?>
                                    <span class="badge bg-white text-dark border me-1 font-monospace"><i class="fas fa-bolt text-warning me-1"></i><?= esc($cp['kode_asset'] ?? $cp['kode_tiang'] ?? '-') ?></span>
                                <?php endforeach; ?>
                                <?php if (count($result['candidate_zone']['candidate_poles']) > 8): ?>
                                    <span class="text-muted">+<?= count($result['candidate_zone']['candidate_poles']) - 8 ?> tiang lainnya</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Semua tiang sepanjang zona kandidat</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="fl-map-legend" id="miniMapLegend">
                    <span class="fl-map-legend-item">
                        <span class="fl-legend-line" style="background:#0284c7;"></span> Transline Hilir
                    </span>
                    <span class="fl-map-legend-item">
                        <span class="fl-legend-line" style="background:repeating-linear-gradient(90deg,#ef4444,#ef4444 5px,transparent 5px,transparent 9px);"></span> Jalur Terukur ke Gangguan
                    </span>
                    <span class="fl-map-legend-item">
                        <span class="fl-legend-line" style="background:#f59e0b; box-shadow:0 0 5px #f59e0b;"></span> Zona Kandidat Estimasi
                    </span>
                    <span class="fl-map-legend-item">
                        <span class="fl-legend-dot" style="background:#8b5cf6;"></span> Titik Acuan (0.0 km)
                    </span>
                    <span class="fl-map-legend-item">
                        <span class="fl-legend-dot" style="background:#10b981;"></span> Tiang JTM (Normal)
                    </span>
                    <span class="fl-map-legend-item">
                        <span class="fl-legend-dot" style="background:#ef4444;"></span> Estimasi Titik Gangguan (✕)
                    </span>
                </div>
                <div id="miniMap"></div>
                <div id="faultPathSequenceStrip" class="fl-seq-strip" style="display:none;"></div>
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

                <!-- Controlled Calibration Breakdown by Category -->
                <div class="p-3 rounded-3 bg-light border">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
                        <strong class="text-dark small"><i class="fas fa-chart-pie text-info me-1"></i> Performa Model Kalibrasi per Kategori Jaringan &amp; Gangguan</strong>
                        <span class="text-muted" style="font-size:0.68rem;">Target Kalibrasi: Min. 15 Sampel Terkonfirmasi per Kategori</span>
                    </div>
                    <div class="row g-2">
                        <!-- Breakdown by Fault Type -->
                        <div class="col-md-4">
                            <div class="border rounded p-2 bg-white h-100">
                                <div class="fw-bold text-dark small mb-1 border-bottom pb-1" style="font-size:0.72rem;">Berdasarkan Jenis Gangguan</div>
                                <?php if (!empty($calibrationSummary['metrics_by_fault_type'])): ?>
                                    <table class="table table-sm table-borderless mb-0" style="font-size:0.68rem;">
                                        <thead>
                                            <tr class="text-muted border-bottom">
                                                <th>Tipe</th>
                                                <th class="text-center">Sampel</th>
                                                <th class="text-end">MAE</th>
                                                <th class="text-end">Akurasi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($calibrationSummary['metrics_by_fault_type'] as $k => $m): ?>
                                                <tr>
                                                    <td class="fw-bold"><?= esc($k) ?></td>
                                                    <td class="text-center"><?= esc($m['confirmed_count']) ?>/<?= esc($m['total_samples']) ?></td>
                                                    <td class="text-end"><?= esc($m['mae_label']) ?></td>
                                                    <td class="text-end fw-bold <?= ($m['accuracy_percent'] >= 80) ? 'text-success' : 'text-warning' ?>"><?= esc($m['accuracy_percent']) ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div class="text-muted p-2 text-center" style="font-size:0.7rem;">Belum ada sampel terkonfirmasi.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Breakdown by Source Type -->
                        <div class="col-md-4">
                            <div class="border rounded p-2 bg-white h-100">
                                <div class="fw-bold text-dark small mb-1 border-bottom pb-1" style="font-size:0.72rem;">Berdasarkan Titik Acuan</div>
                                <?php if (!empty($calibrationSummary['metrics_by_source_type'])): ?>
                                    <table class="table table-sm table-borderless mb-0" style="font-size:0.68rem;">
                                        <thead>
                                            <tr class="text-muted border-bottom">
                                                <th>Sumber</th>
                                                <th class="text-center">Sampel</th>
                                                <th class="text-end">MAE</th>
                                                <th class="text-end">Akurasi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($calibrationSummary['metrics_by_source_type'] as $k => $m): ?>
                                                <tr>
                                                    <td class="fw-bold"><?= esc($k) ?></td>
                                                    <td class="text-center"><?= esc($m['confirmed_count']) ?>/<?= esc($m['total_samples']) ?></td>
                                                    <td class="text-end"><?= esc($m['mae_label']) ?></td>
                                                    <td class="text-end fw-bold <?= ($m['accuracy_percent'] >= 80) ? 'text-success' : 'text-warning' ?>"><?= esc($m['accuracy_percent']) ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div class="text-muted p-2 text-center" style="font-size:0.7rem;">Belum ada sampel terkonfirmasi.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Breakdown by Conductor Type -->
                        <div class="col-md-4">
                            <div class="border rounded p-2 bg-white h-100">
                                <div class="fw-bold text-dark small mb-1 border-bottom pb-1" style="font-size:0.72rem;">Berdasarkan Konduktor</div>
                                <?php if (!empty($calibrationSummary['metrics_by_conductor_type'])): ?>
                                    <table class="table table-sm table-borderless mb-0" style="font-size:0.68rem;">
                                        <thead>
                                            <tr class="text-muted border-bottom">
                                                <th>Konduktor</th>
                                                <th class="text-center">Sampel</th>
                                                <th class="text-end">MAE</th>
                                                <th class="text-end">Akurasi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($calibrationSummary['metrics_by_conductor_type'] as $k => $m): ?>
                                                <tr>
                                                    <td class="fw-bold"><?= esc($k) ?></td>
                                                    <td class="text-center"><?= esc($m['confirmed_count']) ?>/<?= esc($m['total_samples']) ?></td>
                                                    <td class="text-end"><?= esc($m['mae_label']) ?></td>
                                                    <td class="text-end fw-bold <?= ($m['accuracy_percent'] >= 80) ? 'text-success' : 'text-warning' ?>"><?= esc($m['accuracy_percent']) ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <div class="text-muted p-2 text-center" style="font-size:0.7rem;">Belum ada sampel terkonfirmasi.</div>
                                <?php endif; ?>
                            </div>
                        </div>
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
                                <th style="width: 13%">No. Kasus</th>
                                <th style="width: 16%">Penyulang &amp; Sumber</th>
                                <th style="width: 18%">Arus (A) &amp; Tegangan (kV)</th>
                                <th style="width: 13%">Jarak (Topologi / Relay)</th>
                                <th style="width: 13%">Status Patroli</th>
                                <th style="width: 14%">Hasil Temuan Lapangan</th>
                                <th style="width: 13%">Aksi</th>
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
                                                $vR = $c['voltage_r_kv'] ?? null;
                                                $vS = $c['voltage_s_kv'] ?? null;
                                                $vT = $c['voltage_t_kv'] ?? null;
                                                $hasVoltage = ($vR !== null || $vS !== null || $vT !== null);
                                            ?>
                                            <div class="text-dark fw-bold"><?= esc($fdrName) ?></div>
                                            <div class="text-muted" style="font-size:0.72rem;"><?= esc($c['source_type'] ?: 'GI') ?> &bull; <?= esc($c['fault_type_label'] ?? $c['fault_type'] ?: '1-PHASE') ?></div>
                                        </td>
                                        <td>
                                            <?php if ($hasCurrent): ?>
                                                <span class="text-danger small fw-bold"><?= number_format($rCurr, 1) ?> / <?= number_format($sCurr, 1) ?> / <?= number_format($tCurr, 1) ?> A</span>
                                                <div class="text-muted" style="font-size:0.72rem;">N: <?= number_format($nCurr, 1) ?> A</div>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-muted border small">Tanpa Telemetri Arus</span>
                                            <?php endif; ?>
                                            <?php if ($hasVoltage): ?>
                                                <div class="text-primary fw-semibold mt-1" style="font-size:0.71rem;">
                                                    V (R/S/T):
                                                    <?= $vR !== null ? number_format((float)$vR, 2, '.', '') : '-' ?> /
                                                    <?= $vS !== null ? number_format((float)$vS, 2, '.', '') : '-' ?> /
                                                    <?= $vT !== null ? number_format((float)$vT, 2, '.', '') : '-' ?> kV
                                                </div>
                                            <?php else: ?>
                                                <div class="text-muted" style="font-size:0.68rem;">Tegangan: Belum diisi</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small">
                                            <?php if (!empty($c['topology_path_length_m'])): ?>
                                                <div class="text-success fw-semibold">Jalur: <?= number_format((float)$c['topology_path_length_m'], 1) ?> m</div>
                                            <?php endif; ?>
                                            <?php if (!empty($c['relay_measured_distance_m'])): ?>
                                                <div class="text-primary fw-bold">Relay: <?= number_format((float)$c['relay_measured_distance_m'], 1) ?> m</div>
                                            <?php elseif (($c['target_distance_meters'] ?? 0) > 0): ?>
                                                <div class="text-dark fw-bold">Est: <?= number_format((float)$c['target_distance_meters'], 1) ?> m</div>
                                            <?php else: ?>
                                                <div class="text-muted">Relay: -</div>
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
                                            <div class="d-flex flex-wrap gap-1">
                                                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 btn-review-case"
                                                    data-case-id="<?= esc($c['id']) ?>"
                                                    data-case-number="<?= esc($c['case_number']) ?>"
                                                    data-ulp-id="<?= esc($c['ulp_id'] ?? '') ?>"
                                                    data-penyulang-id="<?= esc($c['penyulang_id'] ?? '') ?>"
                                                    data-source-type="<?= esc($c['source_type'] ?? 'GI') ?>"
                                                    data-protection-id="<?= esc($c['protection_device_id'] ?? '') ?>"
                                                    data-current-r="<?= esc($rCurr) ?>"
                                                    data-current-s="<?= esc($sCurr) ?>"
                                                    data-current-t="<?= esc($tCurr) ?>"
                                                    data-current-n="<?= esc($nCurr) ?>"
                                                    data-voltage-r="<?= $vR !== null ? esc(number_format((float)$vR, 2, '.', '')) : '' ?>"
                                                    data-voltage-s="<?= $vS !== null ? esc(number_format((float)$vS, 2, '.', '')) : '' ?>"
                                                    data-voltage-t="<?= $vT !== null ? esc(number_format((float)$vT, 2, '.', '')) : '' ?>"
                                                    data-relay-distance="<?= !empty($c['relay_measured_distance_m']) ? esc(number_format((float)$c['relay_measured_distance_m'], 1, '.', '')) : '' ?>"
                                                    title="Muat kembali nilai arus dan tegangan kasus ini untuk ditinjau">
                                                    <i class="fas fa-eye me-1"></i> Tinjau
                                                </button>
                                                <button type="button" class="btn btn-outline-warning btn-sm py-0 px-2 btn-record-outcome" data-case-id="<?= esc($c['id']) ?>" data-case-number="<?= esc($c['case_number']) ?>" data-feeder-name="<?= esc($c['nama_penyulang'] ?? '') ?>">
                                                    <i class="fas fa-clipboard-check me-1"></i> Catat
                                                </button>
                                            </div>
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
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-dark small fw-bold">KATEGORI PENYEBAB GANGGUAN <span class="text-danger">*</span></label>
                                <select class="form-select" id="modalCauseCategory">
                                    <option value="BINATANG">Binatang (Bunglon / Burung / Kelelawar / Ular)</option>
                                    <option value="POHON">Pohon (Sentuhan Dahan / Pohon Tumbang)</option>
                                    <option value="PETIR">Surja Petir / Sambaran Petir Langsung</option>
                                    <option value="PERALATAN_RUSAK">Peralatan Rusak (Isolator Flashover / Arrester / Kawat Putus)</option>
                                    <option value="KONTAK_BENDA_ASING">Kontak Benda Asing (Layang-layang / Seng / Spanduk)</option>
                                    <option value="TEMPORER_HILANG">Temporer Hilang Sendiri</option>
                                    <option value="LAINNYA">Lain-lain / Penyebab Khusus</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-dark small fw-bold">DESKRIPSI KERUSAKAN KOMPONEN</label>
                                <input type="text" class="form-control" id="modalComponentDamaged" placeholder="Contoh: Bunglon mati di pin isolator fasa R / Flashover kawat">
                            </div>
                        </div>

                        <!-- Candidate Pole Select & ID -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <label class="form-label text-dark small fw-bold">PILIH TIANG JTM TEMUAN (KANDIDAT ATAU DAFTAR TIANG)</label>
                                <select class="form-select" id="modalFoundAssetSelect">
                                    <option value="">-- Pilih dari Kandidat / Tiang Jalur --</option>
                                </select>
                                <div class="form-text text-muted" style="font-size:0.69rem;">Memilih tiang akan otomatis mengisi Asset ID, estimasi jarak, dan koordinat.</div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label text-dark small fw-bold">ID ASET TIANG (MANUAL / OTOMATIS)</label>
                                <input type="number" class="form-control" id="modalFoundAssetId" placeholder="Asset ID (opsional)">
                            </div>
                        </div>

                        <!-- Actual Coordinates & Measured Distance -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label text-dark small fw-bold">JARAK DARI ACUAN (METER)</label>
                                <input type="number" step="0.1" class="form-control fw-bold" id="modalActualDistanceM" placeholder="Jarak kumulatif (m)">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-dark small fw-bold">LATITUDE AKTUAL</label>
                                <input type="number" step="0.00000001" class="form-control font-monospace" id="modalActualLatitude" placeholder="-7.45xxxxxx">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-dark small fw-bold">LONGITUDE AKTUAL</label>
                                <input type="number" step="0.00000001" class="form-control font-monospace" id="modalActualLongitude" placeholder="112.71xxxxxx">
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="modalIsComponentMatch" checked>
                                <label class="form-check-label text-dark small" for="modalIsComponentMatch">
                                    Kerusakan komponen sesuai dengan klasifikasi gangguan sistem (DGR / OCR)
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

<!-- Modal All Candidates Dialog -->
<div class="modal fade" id="modalAllCandidates" tabindex="-1" aria-labelledby="modalAllCandidatesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title font-weight-bold" id="modalAllCandidatesLabel">
                    <i class="fas fa-list-ol text-warning me-2"></i> Seluruh Kandidat Tiang Terduga (<span id="modalCandidateCount"><?= !empty($result['candidate_assets']) ? count($result['candidate_assets']) : 0 ?></span> Tiang)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div class="input-group" style="max-width: 380px;">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" id="inputSearchCandidates" placeholder="Cari kode tiang atau nama aset..." autocomplete="off">
                    </div>
                    <div id="modalCandidateStatusInfo" class="small text-muted">
                        <?php if (empty($result['candidate_zone']['is_single_point'])): ?>
                            <span class="badge bg-warning text-dark border">ZONA KANDIDAT — BELUM PRESISI</span> Urut hilir dari acuan
                        <?php else: ?>
                            <span class="badge bg-danger text-white">TITIK ESTIMASI PRESISI</span> Kandidat terdekat gangguan
                        <?php endif; ?>
                    </div>
                </div>
                <div class="table-responsive" style="max-height: 480px;">
                    <table class="table table-sm table-hover table-bordered align-middle mb-0" id="tableModalCandidates">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 6%" class="text-center">#</th>
                                <th style="width: 25%">Kode Tiang</th>
                                <th style="width: 32%">Nama / Status Aset</th>
                                <th style="width: 22%" class="text-end">Kumulatif dari Acuan</th>
                                <th style="width: 15%" class="text-center">Aksi Peta</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyModalCandidates">
                            <!-- Populated dynamically via JS or initial PHP -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light">
                <span class="text-muted small me-auto"><i class="fas fa-info-circle me-1"></i>Klik "Fokus" untuk memusatkan peta dan menandai tiang dengan highlight ring.</span>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    let currentCaseUuid = '<?= esc($result['case_uuid'] ?? '') ?>';
    let currentGisUrl = '<?= esc($result['gis_url'] ?? site_url('gis')) ?>';
    let currentLat = <?= (!empty($result['fault_point']['lat']) && !empty($result['candidate_assets'])) ? (float)$result['fault_point']['lat'] : -7.4478 ?>;
    let currentLng = <?= (!empty($result['fault_point']['lng']) && !empty($result['candidate_assets'])) ? (float)$result['fault_point']['lng'] : 112.7183 ?>;
    let lastCalculatedResult = <?= json_encode($result) ?>;

    // Initialize Leaflet Map
    const map = L.map('miniMap').setView([currentLat, currentLng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const miniMapLayerGroup = L.layerGroup().addTo(map);
    window.miniMap = map;
    window.miniMapLayerGroup = miniMapLayerGroup;
    window.flActiveHighlightLayer = null;
    window.flCachedCandidates = <?= !empty($result['candidate_assets']) ? json_encode($result['candidate_assets']) : '[]' ?>;
    window.flCachedCandidateZone = <?= !empty($result['candidate_zone']) ? json_encode($result['candidate_zone']) : 'null' ?>;
    window.flCachedIsSinglePoint = <?= !empty($result['candidate_zone']['is_single_point']) ? 'true' : 'false' ?>;

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatAssetDisplayName(kode, name) {
        const rawName = String(name || '').trim();
        const rawKode = String(kode || '').trim();
        const isUnverified = !rawName || rawName.toUpperCase().startsWith('NEW_INGESTED_ASSET_') || rawName === rawKode;
        if (isUnverified) {
            return `<span class="badge bg-secondary-subtle text-secondary border fw-normal" style="font-size:0.7rem;"><i class="fas fa-circle-question me-1"></i>Nama aset belum terverifikasi</span>`;
        }
        return `<span class="text-dark small">${escapeHtml(rawName)}</span>`;
    }

    window.flFocusPole = function(lat, lng, kode, cumMeters, assetName) {
        if (!map || lat === null || lng === null || isNaN(Number(lat)) || isNaN(Number(lng))) return;

        if (window.flActiveHighlightLayer) {
            miniMapLayerGroup.removeLayer(window.flActiveHighlightLayer);
            window.flActiveHighlightLayer = null;
        }

        const targetCoord = [Number(lat), Number(lng)];

        const ringIcon = L.divIcon({
            className: 'fl-pole-highlight-wrapper',
            html: '<div class="fl-pole-highlight-marker"></div>',
            iconSize: [26, 26],
            iconAnchor: [13, 13]
        });

        window.flActiveHighlightLayer = L.marker(targetCoord, { icon: ringIcon, zIndexOffset: 1200 }).addTo(miniMapLayerGroup);
        map.setView(targetCoord, 18, { animate: true });

        const cumStr = (cumMeters !== null && cumMeters !== undefined && !isNaN(Number(cumMeters)))
            ? `Jarak Kumulatif dari Acuan: <b>${Number(cumMeters).toFixed(1)} m (${(Number(cumMeters) / 1000).toFixed(2)} km)</b><br>`
            : '';
        const rawName = String(assetName || '').trim();
        const isUnverified = !rawName || rawName.toUpperCase().startsWith('NEW_INGESTED_ASSET_') || rawName === String(kode || '').trim();
        const nameDisplay = isUnverified
            ? '<span class="badge bg-secondary-subtle text-secondary border mb-1">Nama aset belum terverifikasi</span><br>'
            : `Nama Aset: <b>${escapeHtml(rawName)}</b><br>`;

        L.popup({ offset: [0, -10] })
            .setLatLng(targetCoord)
            .setContent(
                `<b>Tiang JTM Terpilih: ${escapeHtml(kode)}</b><br>` +
                nameDisplay +
                cumStr +
                `Koordinat: <code>${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}</code>`
            )
            .openOn(map);
    };

    window.flFocusFromModal = function(lat, lng, kode, cumM, assetName) {
        const modalEl = document.getElementById('modalAllCandidates');
        if (modalEl && window.bootstrap && bootstrap.Modal) {
            const inst = bootstrap.Modal.getInstance(modalEl);
            if (inst) inst.hide();
        }
        window.flFocusPole(lat, lng, kode, cumM, assetName);
    };

    function renderModalCandidateRows(items) {
        const tbody = document.getElementById('tbodyModalCandidates');
        if (!tbody) return;

        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Tidak ada tiang yang cocok dengan pencarian.</td></tr>';
            return;
        }

        let html = '';
        items.forEach((c, idx) => {
            const cumM = (c.cumulative_meters !== null && c.cumulative_meters !== undefined) ? Number(c.cumulative_meters) : null;
            const cumStr = cumM !== null ? `${cumM.toFixed(1)} m (${(cumM / 1000).toFixed(2)} km)` : '-';
            const nameDisplay = formatAssetDisplayName(c.kode_asset, c.asset_name);

            html += `
                <tr>
                    <td class="text-center font-monospace text-muted">${idx + 1}</td>
                    <td><strong class="font-monospace text-dark">${escapeHtml(c.kode_asset)}</strong></td>
                    <td>${nameDisplay}</td>
                    <td class="text-end font-monospace">${cumStr}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-primary btn-xs py-0 px-2 fw-bold"
                            onclick="window.flFocusFromModal(${Number(c.latitude)}, ${Number(c.longitude)}, '${escapeHtml(c.kode_asset)}', ${cumM !== null ? cumM : 'null'}, '${escapeHtml(c.asset_name || '')}')"
                            title="Fokuskan peta ke tiang ini">
                            <i class="fas fa-crosshairs me-1"></i>Fokus
                        </button>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    window.flPopulateCandidateModal = function() {
        const list = window.flCachedCandidates || [];
        const countEl = document.getElementById('modalCandidateCount');
        const statusEl = document.getElementById('modalCandidateStatusInfo');

        if (countEl) countEl.innerText = list.length;
        if (statusEl) {
            if (!window.flCachedIsSinglePoint) {
                statusEl.innerHTML = `<span class="badge bg-warning text-dark border">ZONA KANDIDAT — BELUM PRESISI</span> Urut hilir dari acuan`;
            } else {
                statusEl.innerHTML = `<span class="badge bg-danger text-white">TITIK ESTIMASI PRESISI</span> Kandidat terdekat gangguan`;
            }
        }

        renderModalCandidateRows(list);

        const searchInput = document.getElementById('inputSearchCandidates');
        if (searchInput && !searchInput.dataset.bound) {
            searchInput.dataset.bound = '1';
            searchInput.addEventListener('input', function() {
                const query = (this.value || '').trim().toLowerCase();
                if (!query) {
                    renderModalCandidateRows(window.flCachedCandidates || []);
                    return;
                }
                const filtered = (window.flCachedCandidates || []).filter(c => {
                    const kode = String(c.kode_asset || '').toLowerCase();
                    const name = String(c.asset_name || '').toLowerCase();
                    return kode.includes(query) || name.includes(query);
                });
                renderModalCandidateRows(filtered);
            });
        }
    };

    function renderCandidateAssetsSummary(candidates, isSinglePoint, candidateZone, anchorDesc) {
        const container = document.getElementById('resCandidateAsset');
        if (!container) return;

        window.flCachedCandidates = Array.isArray(candidates) ? candidates : [];
        window.flCachedCandidateZone = candidateZone || null;
        window.flCachedIsSinglePoint = Boolean(isSinglePoint);

        if (!Array.isArray(candidates) || candidates.length === 0) {
            container.innerHTML = '<span class="text-danger fw-bold"><i class="fas fa-circle-exclamation me-1"></i>Tidak ada kandidat valid pada penyulang ini</span>';
            return;
        }

        const totalCount = candidates.length;
        const isIndeterminate = !isSinglePoint;

        let statusBadgeHtml = '';
        if (isIndeterminate) {
            const startM = candidateZone ? Number(candidateZone.range_start_m || 0).toFixed(0) : '0';
            const endM = candidateZone ? Number(candidateZone.range_end_m || 0).toFixed(0) : '0';
            const startKm = (Number(startM) / 1000).toFixed(2);
            const endKm = (Number(endM) / 1000).toFixed(2);
            statusBadgeHtml = `
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2 pb-2 border-bottom">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-warning text-dark border fw-bold"><i class="fas fa-layer-group me-1"></i>ZONA KANDIDAT — BELUM PRESISI</span>
                        <span class="badge bg-light text-dark border font-monospace">Total: ${totalCount} Tiang</span>
                    </div>
                    <div class="text-muted small">
                        <i class="fas fa-ruler-horizontal text-primary me-1"></i>Rentang: <b>${startKm} – ${endKm} km</b> (${startM} – ${endM} m)
                    </div>
                </div>
            `;
        } else {
            statusBadgeHtml = `
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2 pb-2 border-bottom">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-danger text-white fw-bold"><i class="fas fa-crosshairs me-1"></i>TITIK ESTIMASI PRESISI</span>
                        <span class="badge bg-light text-dark border font-monospace">${totalCount} Tiang Terdekat</span>
                    </div>
                    <div class="text-muted small">
                        <i class="fas fa-bullseye text-danger me-1"></i>Estimasi toleransi ±100 m
                    </div>
                </div>
            `;
        }

        const topCandidates = candidates.slice(0, 5);
        let rowsHtml = '';
        topCandidates.forEach((c, idx) => {
            const cumM = (c.cumulative_meters !== null && c.cumulative_meters !== undefined) ? Number(c.cumulative_meters) : null;
            const cumStr = cumM !== null ? `${cumM.toFixed(1)} m (${(cumM / 1000).toFixed(2)} km)` : '-';
            const nameDisplay = formatAssetDisplayName(c.kode_asset, c.asset_name);

            rowsHtml += `
                <tr>
                    <td class="text-center font-monospace text-muted" style="width: 5%;">${idx + 1}</td>
                    <td style="width: 25%;">
                        <strong class="font-monospace text-dark">${escapeHtml(c.kode_asset)}</strong>
                    </td>
                    <td style="width: 35%;">${nameDisplay}</td>
                    <td class="text-end font-monospace" style="width: 22%;">${cumStr}</td>
                    <td class="text-center" style="width: 13%;">
                        <button type="button" class="btn btn-outline-primary btn-xs py-0 px-2 fw-bold"
                            onclick="window.flFocusPole(${Number(c.latitude)}, ${Number(c.longitude)}, '${escapeHtml(c.kode_asset)}', ${cumM !== null ? cumM : 'null'}, '${escapeHtml(c.asset_name || '')}')"
                            title="Fokuskan peta ke tiang ini">
                            <i class="fas fa-crosshairs me-1"></i>Fokus
                        </button>
                    </td>
                </tr>
            `;
        });

        const previewTableHtml = `
            <div class="table-responsive mb-2">
                <table class="table table-sm table-bordered table-hover align-middle mb-0" style="font-size:0.78rem;">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center">#</th>
                            <th>Kode Tiang</th>
                            <th>Nama / Status Aset</th>
                            <th class="text-end">Kumulatif dari Acuan</th>
                            <th class="text-center">Aksi Peta</th>
                        </tr>
                    </thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
            </div>
        `;

        let footerHtml = '';
        if (totalCount > 5) {
            footerHtml = `
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-1">
                    <span class="text-muted small">Menampilkan 5 prioritas teratas dari <b>${totalCount}</b> tiang terduga.</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalAllCandidates" onclick="window.flPopulateCandidateModal()">
                        <i class="fas fa-list-ol text-warning me-1"></i> Lihat Semua Kandidat (${totalCount})
                    </button>
                </div>
            `;
        }

        container.innerHTML = statusBadgeHtml + previewTableHtml + footerHtml;
    }

    function clearMiniMapLayers(badgeText = 'TIDAK ADA KANDIDAT VALID', badgeClass = 'badge bg-secondary text-white') {
        map.closePopup();
        if (window.flActiveHighlightLayer) {
            miniMapLayerGroup.removeLayer(window.flActiveHighlightLayer);
            window.flActiveHighlightLayer = null;
        }
        miniMapLayerGroup.clearLayers();
        const mapBadge = document.getElementById('mapStatusBadge');
        if (mapBadge) {
            mapBadge.className = badgeClass;
            mapBadge.innerText = badgeText;
        }
        const stripEl = document.getElementById('faultPathSequenceStrip');
        if (stripEl) {
            stripEl.style.display = 'none';
            stripEl.innerHTML = '';
        }
    }

    function renderFaultPathSequenceStrip(resultData) {
        const stripEl = document.getElementById('faultPathSequenceStrip');
        if (!stripEl) return;

        const pathSeq = (Array.isArray(resultData?.fault_path_sequence) && resultData.fault_path_sequence.length > 0)
            ? resultData.fault_path_sequence
            : (Array.isArray(resultData?.pole_sequence) ? resultData.pole_sequence.slice(0, 12) : []);

        if (!pathSeq || pathSeq.length === 0) {
            stripEl.style.display = 'none';
            stripEl.innerHTML = '';
            return;
        }

        const srcCtx = resultData?.source_context || {};
        const isProt = (resultData?.source_type === 'PROTECTION' || srcCtx.source_type === 'PROTECTION');
        const srcTitle = isProt
            ? ('⚡ ' + (srcCtx.protection_device?.nama_asset || srcCtx.source_label || 'PROTEKSI'))
            : ('GI PANGKAL (' + (resultData?.feeder_name || '') + ')');
        const anchorCode = resultData?.anchor_pole?.kode_asset || pathSeq[0]?.from_pole_kode || 'PANGKAL';

        let html = `
            <div class="fl-seq-node is-source" title="${escapeHtml(resultData?.anchor_description || srcTitle)}">
                <span class="fw-bold">${escapeHtml(srcTitle)}</span>
                <span class="font-monospace" style="font-size:0.64rem;">${escapeHtml(anchorCode)}</span>
                <span class="badge bg-dark text-info mt-1" style="font-size:0.62rem;">0.00 km (Acuan)</span>
            </div>
        `;

        // If path is long, show first 3, ellipsis, and last 5 segments up to the fault zone
        const maxDisplay = 8;
        let displayItems = pathSeq;
        let truncatedCount = 0;
        if (pathSeq.length > maxDisplay) {
            truncatedCount = pathSeq.length - maxDisplay;
            displayItems = [
                ...pathSeq.slice(0, 3),
                { _ellipsis: true, skipped: truncatedCount },
                ...pathSeq.slice(pathSeq.length - 5)
            ];
        }

        displayItems.forEach(seg => {
            if (seg._ellipsis) {
                html += `<span class="fl-seq-arrow">&rarr;</span>
                    <div class="fl-seq-node text-center" style="min-width:70px;opacity:0.8;">
                        <span class="fw-bold">... +${seg.skipped} bentang ...</span>
                        <span style="font-size:0.62rem;">Jalur Hilir</span>
                    </div>`;
                return;
            }
            const isZone = Boolean(seg.is_fault_zone);
            const kmStr = (Number(seg.cumulative_meters || 0) / 1000).toFixed(2) + ' km';
            const mStr = Number(seg.cumulative_meters || 0).toFixed(0) + ' m';
            html += `<span class="fl-seq-arrow">&rarr;</span>
                <div class="fl-seq-node ${isZone ? 'is-fault-zone' : ''}">
                    <span class="fw-bold">${escapeHtml(seg.to_pole_kode)}</span>
                    <span style="font-size:0.62rem;">+${Number(seg.span_meters || 0).toFixed(0)}m &bull; ${kmStr} (${mStr})</span>
                    ${isZone ? '<span class="badge bg-danger text-white mt-1" style="font-size:0.6rem;">✕ ZONA GANGGUAN</span>' : '<span class="text-success mt-1" style="font-size:0.6rem;">● Tiang Normal</span>'}
                </div>`;
        });

        stripEl.innerHTML = html;
        stripEl.style.display = 'flex';
    }

    function extractValidSegmentLatLngs(seg) {
        if (Array.isArray(seg.path_coords) && seg.path_coords.length >= 2) {
            const pts = [];
            seg.path_coords.forEach(pt => {
                const lat = Number(pt.lat ?? pt[0] ?? 0);
                const lng = Number(pt.lng ?? pt[1] ?? 0);
                if (lat !== 0 && lng !== 0 && !Number.isNaN(lat) && !Number.isNaN(lng)) {
                    pts.push([lat, lng]);
                }
            });
            if (pts.length >= 2) return pts;
        }
        const fLat = Number(seg.from_coords?.lat ?? 0);
        const fLng = Number(seg.from_coords?.lng ?? 0);
        const tLat = Number(seg.to_coords?.lat ?? 0);
        const tLng = Number(seg.to_coords?.lng ?? 0);
        if (fLat !== 0 && fLng !== 0 && tLat !== 0 && tLng !== 0) {
            return [[fLat, fLng], [tLat, tLng]];
        }
        return [];
    }

    function updateMiniMap(lat, lng, assetLabel, poleSeq, feederName, feederCode, fullResultData = null) {
        const resultObj = fullResultData || lastCalculatedResult || {};
        const srcCtx = resultObj.source_context || {};
        const isProt = (resultObj.source_type === 'PROTECTION' || srcCtx.source_type === 'PROTECTION');
        const srcBadgeSuffix = isProt
            ? (' — HILIR DARI ' + (srcCtx.protection_device?.nama_asset || srcCtx.source_label || 'PROTEKSI'))
            : (' — DARI GI PANGKAL');

        const candidateZone = resultObj.candidate_zone || null;
        const isCandidateZoneIndeterminate = Boolean(candidateZone && !candidateZone.is_single_point);
        const hasFaultPoint = Boolean(lat && lng && !Number.isNaN(Number(lat)) && !Number.isNaN(Number(lng)) && !isCandidateZoneIndeterminate);
        const hasDownstreamSeq = Array.isArray(poleSeq) && poleSeq.length > 0;

        if (!hasFaultPoint && !hasDownstreamSeq && !isCandidateZoneIndeterminate) {
            clearMiniMapLayers(
                'TIDAK ADA KANDIDAT VALID' + (feederCode ? ' (' + feederCode + ')' : ''),
                'badge bg-secondary text-white'
            );
            const czBox = document.getElementById('candidateZoneNoticeBox');
            if (czBox) czBox.style.display = 'none';
            return;
        }

        let mapStatusText = '';
        let mapStatusClass = '';
        if (hasFaultPoint) {
            mapStatusText = 'FAULT POINT LOCATED' + (feederCode ? ' (' + feederCode + ')' : '') + srcBadgeSuffix;
            mapStatusClass = 'badge bg-danger text-white';
        } else if (isCandidateZoneIndeterminate) {
            mapStatusText = 'ZONA KANDIDAT TERPETAKAN' + (feederCode ? ' (' + feederCode + ')' : '') + srcBadgeSuffix;
            mapStatusClass = 'badge bg-warning text-dark';
        } else {
            mapStatusText = 'JALUR HILIR TERPETAKAN' + (feederCode ? ' (' + feederCode + ')' : '') + srcBadgeSuffix;
            mapStatusClass = 'badge bg-info text-dark';
        }

        clearMiniMapLayers(mapStatusText, mapStatusClass);

        // Update Spatial Candidate Zone Notice Box in Map Card
        const czNoticeBox = document.getElementById('candidateZoneNoticeBox');
        if (czNoticeBox) {
            if (isCandidateZoneIndeterminate && candidateZone) {
                czNoticeBox.style.display = 'block';
                const czTitle = document.getElementById('candidateZoneNoticeTitle');
                if (czTitle) czTitle.innerText = candidateZone.title || 'ZONA KANDIDAT PENELUSURAN GANGGUAN TANAH (DGR)';
                const czRange = document.getElementById('candidateZoneRangeText');
                if (czRange) {
                    const startM = Number(candidateZone.range_start_m || 0).toFixed(0);
                    const endM = Number(candidateZone.range_end_m || 0).toFixed(0);
                    const startKm = (Number(startM) / 1000).toFixed(2);
                    const endKm = (Number(endM) / 1000).toFixed(2);
                    czRange.innerText = `${startM} m – ${endM} m (${startKm} – ${endKm} km)`;
                }
                const czNotes = document.getElementById('candidateZoneExplanation');
                if (czNotes) czNotes.innerText = candidateZone.notes || '';
                const czPolesList = document.getElementById('candidateZonePolesList');
                if (czPolesList) {
                    const poles = Array.isArray(candidateZone.candidate_poles) ? candidateZone.candidate_poles : [];
                    if (poles.length > 0) {
                        czPolesList.innerHTML = poles.slice(0, 10).map(p =>
                            `<span class="badge bg-white text-dark border me-1 font-monospace"><i class="fas fa-bolt text-warning me-1"></i>${escapeHtml(p.kode_asset || p.kode_tiang || '-')}</span>`
                        ).join('') + (poles.length > 10 ? `<span class="text-muted">+${poles.length - 10} tiang lainnya</span>` : '');
                    } else {
                        czPolesList.innerHTML = '<span class="text-muted">Semua tiang sepanjang jalur hilir kandidat</span>';
                    }
                }
            } else {
                czNoticeBox.style.display = 'none';
            }
        }

        if (hasFaultPoint) {
            currentLat = Number(lat);
            currentLng = Number(lng);
        }

        const focusBoundsPoints = [];
        const allDownstreamPoints = [];

        // 1. Optional Upstream Reference Segments (GI -> Protection Device, muted dashed gray)
        const upstreamSegs = Array.isArray(resultObj.upstream_segments) ? resultObj.upstream_segments : [];
        upstreamSegs.forEach(uSeg => {
            const pts = extractValidSegmentLatLngs(uSeg);
            if (pts.length >= 2) {
                L.polyline(pts, {
                    color: '#94a3b8',
                    weight: 2.5,
                    opacity: 0.5,
                    dashArray: '4, 6'
                }).bindTooltip(
                    'Jalur Hulu (GI &rarr; Proteksi): ' + escapeHtml(uSeg.from_pole_kode) + ' &rarr; ' + escapeHtml(uSeg.to_pole_kode) + ' (Diblokir dari penelusuran hilir)',
                    { sticky: true }
                ).addTo(miniMapLayerGroup);
            }
        });

        // 2. Render each downstream transline segment as its OWN individual L.polyline
        const poleMap = new Map();
        const faultZonePoleIds = new Set();
        const candidatePoleIds = new Set();
        if (candidateZone && Array.isArray(candidateZone.candidate_poles)) {
            candidateZone.candidate_poles.forEach(cp => {
                if (cp && cp.id) candidatePoleIds.add(Number(cp.id));
            });
        }

        let firstCandidateCoord = null;

        if (hasDownstreamSeq) {
            poleSeq.forEach(seg => {
                const pts = extractValidSegmentLatLngs(seg);
                if (pts.length < 2) return;

                pts.forEach(p => allDownstreamPoints.push(p));

                const isZone = Boolean(seg.is_fault_zone);
                const isCandZone = Boolean(seg.is_candidate_zone || (isCandidateZoneIndeterminate && seg.is_on_fault_path));
                const isOnFaultPath = Boolean(seg.is_on_fault_path);
                const condText = escapeHtml(
                    ((seg.conductor_type || 'Belum tersedia') + ((seg.conductor_size && seg.conductor_size !== '-') ? ' ' + seg.conductor_size : '')).trim()
                );

                if (isZone) {
                    faultZonePoleIds.add(seg.from_pole_id);
                    faultZonePoleIds.add(seg.to_pole_id);
                }
                if (isCandZone) {
                    candidatePoleIds.add(seg.from_pole_id);
                    candidatePoleIds.add(seg.to_pole_id);
                    if (!firstCandidateCoord && pts.length > 0) {
                        firstCandidateCoord = pts[0];
                    }
                }

                if (isZone) {
                    L.polyline(pts, {
                        color: '#f59e0b',
                        weight: 9,
                        opacity: 0.85,
                        lineCap: 'round'
                    }).addTo(miniMapLayerGroup);

                    L.polyline(pts, {
                        color: '#ef4444',
                        weight: 4,
                        opacity: 1.0,
                        dashArray: '6, 6'
                    }).bindPopup(
                        '<b>Zona Gangguan (' + escapeHtml(seg.from_pole_kode) + ' &rarr; ' + escapeHtml(seg.to_pole_kode) + ')</b><br>' +
                        'Bentang: ' + Number(seg.span_meters).toFixed(1) + ' m<br>' +
                        'Jarak Kumulatif dari Acuan: <b>' + Number(seg.cumulative_meters).toFixed(1) + ' m (' + (Number(seg.cumulative_meters) / 1000).toFixed(2) + ' km)</b><br>' +
                        'Konduktor: ' + condText
                    ).addTo(miniMapLayerGroup);

                    pts.forEach(p => focusBoundsPoints.push(p));
                } else if (isCandZone) {
                    L.polyline(pts, {
                        color: '#f59e0b',
                        weight: 8,
                        opacity: 0.65,
                        lineCap: 'round',
                        className: 'fl-candidate-corridor-flow'
                    }).addTo(miniMapLayerGroup);

                    L.polyline(pts, {
                        color: '#d97706',
                        weight: 3.5,
                        opacity: 0.95,
                        dashArray: '8, 8',
                        className: 'fl-candidate-corridor-flow'
                    }).bindTooltip(
                        '<b>Zona kandidat — belum presisi:</b> ' + escapeHtml(seg.from_pole_kode) + ' &rarr; ' + escapeHtml(seg.to_pole_kode) +
                        ' (' + Number(seg.cumulative_meters).toFixed(1) + ' m &bull; ' + condText + ')',
                        { sticky: true }
                    ).addTo(miniMapLayerGroup);

                    pts.forEach(p => focusBoundsPoints.push(p));
                } else if (isOnFaultPath) {
                    L.polyline(pts, {
                        color: '#0284c7',
                        weight: 5,
                        opacity: 0.92,
                        lineCap: 'round'
                    }).addTo(miniMapLayerGroup);

                    L.polyline(pts, {
                        color: '#ef4444',
                        weight: 2.5,
                        opacity: 0.85,
                        dashArray: '6, 8'
                    }).bindTooltip(
                        'Jalur Hilir #' + seg.seq_no + ': ' + escapeHtml(seg.from_pole_kode) + ' &rarr; ' + escapeHtml(seg.to_pole_kode) +
                        ' (' + Number(seg.cumulative_meters).toFixed(1) + ' m &bull; ' + condText + ')',
                        { sticky: true }
                    ).addTo(miniMapLayerGroup);

                    pts.forEach(p => focusBoundsPoints.push(p));
                } else {
                    L.polyline(pts, {
                        color: '#0284c7',
                        weight: 3,
                        opacity: 0.6
                    }).bindTooltip(
                        escapeHtml(seg.from_pole_kode) + ' &rarr; ' + escapeHtml(seg.to_pole_kode) +
                        ' (Kumulatif Hilir: ' + Number(seg.cumulative_meters).toFixed(1) + ' m &bull; ' + condText + ')',
                        { sticky: true }
                    ).addTo(miniMapLayerGroup);
                }

                if (seg.from_coords && seg.from_coords.lat && seg.from_coords.lng) {
                    const existingFrom = poleMap.get(seg.from_pole_id);
                    poleMap.set(seg.from_pole_id, {
                        id: seg.from_pole_id,
                        kode: seg.from_pole_kode,
                        lat: Number(seg.from_coords.lat),
                        lng: Number(seg.from_coords.lng),
                        cum_m: existingFrom ? Math.min(existingFrom.cum_m, Number(seg.cum_start_meters || 0)) : Number(seg.cum_start_meters || 0),
                        on_path: (existingFrom?.on_path || false) || isOnFaultPath || isZone || isCandZone
                    });
                }
                if (seg.to_coords && seg.to_coords.lat && seg.to_coords.lng) {
                    const existingTo = poleMap.get(seg.to_pole_id);
                    poleMap.set(seg.to_pole_id, {
                        id: seg.to_pole_id,
                        kode: seg.to_pole_kode,
                        lat: Number(seg.to_coords.lat),
                        lng: Number(seg.to_coords.lng),
                        cum_m: Number(seg.cumulative_meters || 0),
                        on_path: (existingTo?.on_path || false) || isOnFaultPath || isZone || isCandZone
                    });
                }
            });
        }

        // 3. Render Pole Node Markers (Normal #10b981, Zona #f59e0b, Acuan #8b5cf6)
        const anchorPoleId = Number(resultObj.anchor_pole?.id || 0);
        poleMap.forEach(pole => {
            const isZonePole = faultZonePoleIds.has(pole.id);
            const isCandidatePole = candidatePoleIds.has(pole.id);
            const isAnchor = (pole.id === anchorPoleId);
            if (!pole.lat || !pole.lng) return;

            if (isZonePole) {
                L.circleMarker([pole.lat, pole.lng], {
                    radius: 7,
                    fillColor: '#ef4444',
                    color: '#ffffff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.95
                }).bindTooltip(
                    '<b>' + escapeHtml(pole.kode) + '</b> (Segmen Gangguan Terdekat &bull; ' + (pole.cum_m / 1000).toFixed(2) + ' km)',
                    { direction: 'top', offset: [0, -6] }
                ).addTo(miniMapLayerGroup);
            } else if (pole.on_path || isAnchor || (isCandidatePole && isCandidateZoneIndeterminate)) {
                // Keep normal green (#10b981) for poles along candidate corridor (do NOT mark all 100+ poles orange!)
                L.circleMarker([pole.lat, pole.lng], {
                    radius: isAnchor ? 7 : 5.5,
                    fillColor: isAnchor ? '#8b5cf6' : '#10b981',
                    color: '#ffffff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.95
                }).bindTooltip(
                    '<b>' + escapeHtml(pole.kode) + '</b> &bull; ' + (pole.cum_m / 1000).toFixed(2) + ' km (' + pole.cum_m.toFixed(0) + ' m)' + (isCandidateZoneIndeterminate ? ' <span class="badge bg-warning text-dark border">Zona Kandidat</span>' : ''),
                    { direction: 'top', offset: [0, -5] }
                ).addTo(miniMapLayerGroup);
            } else {
                L.circleMarker([pole.lat, pole.lng], {
                    radius: 3.5,
                    fillColor: '#10b981',
                    color: '#ffffff',
                    weight: 1,
                    opacity: 0.85,
                    fillOpacity: 0.8
                }).bindTooltip(
                    escapeHtml(pole.kode) + ' (' + pole.cum_m.toFixed(0) + ' m)',
                    { direction: 'top' }
                ).addTo(miniMapLayerGroup);
            }
        });

        // 4. Render Source Anchor Callout Badge (0.0 km at Mounting Pole of Recloser/PMCB or GI Pangkal)
        const anchorPole = resultObj.anchor_pole;
        if (anchorPole && anchorPole.latitude && anchorPole.longitude) {
            const aLat = Number(anchorPole.latitude);
            const aLng = Number(anchorPole.longitude);
            focusBoundsPoints.push([aLat, aLng]);
            allDownstreamPoints.push([aLat, aLng]);

            const badgeLabel = isProt
                ? ('⚡ ' + (srcCtx.protection_device?.nama_asset || srcCtx.source_label || 'PROTEKSI') + ' &bull; 0.0 km')
                : ('⚡ GI PANGKAL (' + (anchorPole.kode_asset || feederName || '') + ') &bull; 0.0 km');

            const sourceIcon = L.divIcon({
                className: 'fl-source-div-icon',
                html: `<div class="fl-source-badge-icon ${isProt ? '' : 'fl-source-badge-gi'}">${badgeLabel}</div>`,
                iconSize: null,
                iconAnchor: [16, 34]
            });

            L.marker([aLat, aLng], { icon: sourceIcon, zIndexOffset: 900 })
                .bindPopup(
                    '<b>Titik Awal Penelusuran (0.0 m)</b><br>' +
                    escapeHtml(resultObj.anchor_description || '') + '<br>' +
                    'Tiang Acuan: <code>' + escapeHtml(anchorPole.kode_asset) + '</code><br>' +
                    'Arah Traversal: <b>Hilir (Menuju Ujung Penyulang)</b>'
                )
                .addTo(miniMapLayerGroup);
        }

        // 5. Render Fault Point Marker (Pulse Ripple Target) & Radius Circle ONLY when a single verified fault coordinate exists
        if (hasFaultPoint) {
            const fLatNum = Number(lat);
            const fLngNum = Number(lng);
            focusBoundsPoints.push([fLatNum, fLngNum]);
            allDownstreamPoints.push([fLatNum, fLngNum]);

            L.circle([fLatNum, fLngNum], {
                radius: 85,
                color: '#ef4444',
                weight: 2,
                dashArray: '5, 5',
                fillColor: '#f87171',
                fillOpacity: 0.16
            }).addTo(miniMapLayerGroup);

            const targetPulseIcon = L.divIcon({
                className: 'fl-target-pulse-wrapper',
                html: '<div class="fl-target-pulse-marker" title="Titik Estimasi Gangguan">&#10005;</div>',
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            });

            const distKm = (Number(resultObj.calculated_distance_m || 0) / 1000).toFixed(2);
            const distM = Number(resultObj.calculated_distance_m || 0).toFixed(1);
            const refSourceShort = isProt
                ? (srcCtx.protection_device?.nama_asset || srcCtx.source_label || 'Peralatan Proteksi')
                : ('GI Pangkal ' + (feederName || ''));
            const conductorLabel = resultObj.traversed_conductor?.display_label || resultObj.electrical_parameters?.conductor_type || 'Belum tersedia';
            const segLabel = resultObj.fault_zone_segment ? (resultObj.fault_zone_segment.from_pole_kode + ' &rarr; ' + resultObj.fault_zone_segment.to_pole_kode) : '-';

            const faultMarker = L.marker([fLatNum, fLngNum], { icon: targetPulseIcon, zIndexOffset: 1000 }).addTo(miniMapLayerGroup);
            const fTag = feederName ? ('<span class="text-muted small">Penyulang: ' + escapeHtml(feederName) + (feederCode ? ' (' + escapeHtml(feederCode) + ')' : '') + '</span><br>') : '';
            faultMarker.bindPopup(
                '<b><i class="fas fa-crosshairs text-danger me-1"></i>Estimasi Titik Gangguan: ' + escapeHtml(assetLabel || '') + '</b><br>' +
                fTag +
                '<span class="badge bg-danger-subtle text-danger border mb-1" style="font-size:0.7rem;"><i class="fas fa-calculator me-1"></i>Estimasi Sistem (Bukan Aktual Lapangan)</span><br>' +
                'Jarak Hilir: <b>' + distKm + ' km (' + distM + ' m)</b> dari ' + escapeHtml(refSourceShort) + '<br>' +
                'Segmen Terdekat: <code>' + escapeHtml(segLabel) + '</code><br>' +
                'Jenis Konduktor: <b>' + escapeHtml(conductorLabel) + '</b><br>' +
                'Keyakinan (Confidence): <b>' + escapeHtml(resultObj.confidence_percent || 88) + '%</b><br>' +
                'Koordinat: <code>' + fLatNum.toFixed(6) + ', ' + fLngNum.toFixed(6) + '</code>'
            );

            const calloutIcon = L.divIcon({
                className: 'fl-fault-callout-wrapper',
                html: `<div class="fl-fault-callout-icon">✕ Estimasi: ${distKm} km (${distM} m) dari ${escapeHtml(refSourceShort)} &bull; ${escapeHtml(assetLabel || '')}</div>`,
                iconSize: null,
                iconAnchor: [-16, 14]
            });
            L.marker([fLatNum, fLngNum], { icon: calloutIcon, zIndexOffset: 950, interactive: false }).addTo(miniMapLayerGroup);
        } else if (isCandidateZoneIndeterminate && firstCandidateCoord) {
            // Render Callout Badge for Candidate Zone without placing fake precision cross
            const refSourceShort = isProt
                ? (srcCtx.protection_device?.nama_asset || srcCtx.source_label || 'Proteksi')
                : ('GI Pangkal ' + (feederName || ''));
            const czEndKm = (Number(candidateZone.range_end_m || 0) / 1000).toFixed(2);
            const czPoleCount = candidateZone.candidate_poles ? candidateZone.candidate_poles.length : (candidateZone.candidate_pole_count || 0);

            const czCalloutIcon = L.divIcon({
                className: 'fl-fault-callout-wrapper',
                html: `<div class="fl-fault-callout-icon" style="background:rgba(180,83,9,0.95); border-color:#f59e0b; color:#fffbeb;"><i class="fas fa-layer-group me-1"></i>Zona Kandidat — Belum Presisi (0.00 – ${czEndKm} km dari ${escapeHtml(refSourceShort)}) &bull; ${czPoleCount} Tiang</div>`,
                iconSize: null,
                iconAnchor: [-16, 14]
            });
            L.marker(firstCandidateCoord, { icon: czCalloutIcon, zIndexOffset: 950, interactive: false }).addTo(miniMapLayerGroup);
        }

        // 6. Cache bounds for Focus / Reset buttons
        window.flFaultLocationBounds = (focusBoundsPoints.length > 0) ? L.latLngBounds(focusBoundsPoints) : null;
        window.flAllDownstreamBounds = (allDownstreamPoints.length > 0) ? L.latLngBounds(allDownstreamPoints) : null;
        window.flFaultPointCoord = hasFaultPoint ? [Number(lat), Number(lng)] : null;

        // 7. Fit Map Bounds smoothly around the active path
        if (focusBoundsPoints.length >= 2) {
            const bounds = L.latLngBounds(focusBoundsPoints);
            if (bounds.isValid()) {
                map.fitBounds(bounds, { padding: [42, 42], maxZoom: 17 });
            } else if (hasFaultPoint) {
                map.setView([Number(lat), Number(lng)], 16);
            }
        } else if (focusBoundsPoints.length === 1) {
            map.setView(focusBoundsPoints[0], 16);
        } else if (hasFaultPoint) {
            map.setView([Number(lat), Number(lng)], 16);
        } else if (allDownstreamPoints.length >= 2) {
            map.fitBounds(L.latLngBounds(allDownstreamPoints), { padding: [42, 42], maxZoom: 17 });
        }

        // 8. Render the linear pole sequence strip below #miniMap
        renderFaultPathSequenceStrip(resultObj);
    }

    // Attach listeners for Focus / Reset Map View buttons
    const btnFocusMap = document.getElementById('btnFocusFaultLocation');
    if (btnFocusMap && !btnFocusMap.dataset.bound) {
        btnFocusMap.dataset.bound = '1';
        btnFocusMap.addEventListener('click', function() {
            if (window.flFaultPointCoord) {
                map.setView(window.flFaultPointCoord, 17);
            } else if (window.flFaultLocationBounds && window.flFaultLocationBounds.isValid()) {
                map.fitBounds(window.flFaultLocationBounds, { padding: [40, 40], maxZoom: 17 });
            } else if (window.flAllDownstreamBounds && window.flAllDownstreamBounds.isValid()) {
                map.fitBounds(window.flAllDownstreamBounds, { padding: [40, 40], maxZoom: 17 });
            }
        });
    }

    const btnResetMap = document.getElementById('btnResetMapFocus');
    if (btnResetMap && !btnResetMap.dataset.bound) {
        btnResetMap.dataset.bound = '1';
        btnResetMap.addEventListener('click', function() {
            if (window.flAllDownstreamBounds && window.flAllDownstreamBounds.isValid()) {
                map.fitBounds(window.flAllDownstreamBounds, { padding: [40, 40], maxZoom: 17 });
            }
        });
    }

    // Initial map state
    if (lastCalculatedResult && ((lastCalculatedResult.fault_point && lastCalculatedResult.fault_point.lat) || (Array.isArray(lastCalculatedResult.pole_sequence) && lastCalculatedResult.pole_sequence.length > 0))) {
        const initLat = (lastCalculatedResult.fault_point && lastCalculatedResult.candidate_assets && lastCalculatedResult.candidate_assets.length > 0) ? lastCalculatedResult.fault_point.lat : null;
        const initLng = (lastCalculatedResult.fault_point && lastCalculatedResult.candidate_assets && lastCalculatedResult.candidate_assets.length > 0) ? lastCalculatedResult.fault_point.lng : null;
        const initLabel = (lastCalculatedResult.candidate_assets && lastCalculatedResult.candidate_assets.length > 0) ? lastCalculatedResult.candidate_assets[0].kode_asset : '';
        updateMiniMap(
            initLat,
            initLng,
            initLabel,
            lastCalculatedResult.pole_sequence,
            lastCalculatedResult.feeder_name,
            lastCalculatedResult.feeder_code,
            lastCalculatedResult
        );
    } else {
        clearMiniMapLayers('TIDAK ADA KANDIDAT VALID', 'badge bg-secondary text-white');
    }

    // Helper: Refresh Select2 widget when options dynamically change
    function refreshSelect2(el) {
        if (window.jQuery && $.fn && $.fn.select2) {
            const $el = $(el);
            if ($el.data('select2')) {
                $el.select2('destroy');
            }
            $el.select2({
                theme: 'bootstrap4',
                width: '100%'
            });
        }
    }

    // Race-condition sequence counters & AbortControllers
    let ulpFetchSeq = 0;
    let ulpAbortController = null;
    let contextFetchSeq = 0;
    let contextAbortController = null;
    let locateFetchSeq = 0;
    let locateAbortController = null;
    let currentUlpValue = document.getElementById('ulpId') ? document.getElementById('ulpId').value : '';
    let currentFeederValue = document.getElementById('feederId') ? document.getElementById('feederId').value : '';

    // Helper: Update Automatic Conductor & Topological Path Length boxes in the Form
    function updateAutoConductorAndTopologyUI(condObj, topoObj, srcType = 'GI') {
        const condBadge = document.getElementById('autoConductorStatusBadge');
        const condDisplay = document.getElementById('autoConductorDisplay');
        const condSubtext = document.getElementById('autoConductorSubtext');
        const condRanges = document.getElementById('autoConductorRangesList');
        const condHidden = document.getElementById('conductorType');

        if (condObj) {
            const isAvail = Boolean(condObj.is_available);
            const statusStr = condObj.status || (isAvail ? 'OTOMATIS' : 'BELUM_TERSEDIA');
            if (condBadge) {
                condBadge.innerText = statusStr.replace(/_/g, ' ');
                condBadge.className = isAvail
                    ? (condObj.is_hybrid ? 'badge bg-warning-subtle text-dark border' : 'badge bg-primary-subtle text-primary border')
                    : 'badge bg-secondary-subtle text-muted border';
            }
            if (condDisplay) {
                condDisplay.innerText = condObj.display_label || condObj.official_type || 'Belum tersedia';
                condDisplay.className = isAvail ? 'fw-bold text-dark small' : 'fw-bold text-danger small';
            }
            if (condSubtext) {
                condSubtext.innerText = condObj.status_message || (isAvail ? 'Diambil otomatis dari gis_translines aktif pada jalur hilir terpilih.' : 'Data konduktor pada jalur ini belum tersedia.');
            }
            if (condHidden) {
                condHidden.value = 'AUTO';
            }
            if (condRanges) {
                const ranges = Array.isArray(condObj.segment_ranges) ? condObj.segment_ranges : [];
                if (ranges.length > 0 && isAvail) {
                    condRanges.innerHTML = ranges.slice(0, 6).map(sr =>
                        `<div class="text-muted"><i class="fas fa-route text-primary me-1"></i>${escapeHtml(sr.range_text || (sr.start_m + ' m – ' + sr.end_m + ' m: ' + sr.conductor_label))}</div>`
                    ).join('');
                    condRanges.style.display = 'block';
                } else {
                    condRanges.innerHTML = '';
                    condRanges.style.display = 'none';
                }
            }
        }

        const pathLabelEl = document.getElementById('topologyPathLengthLabel');
        const pathValueEl = document.getElementById('topologyPathLengthValue');
        const pathConstraintEl = document.getElementById('topologyConstraintNotice');

        const defaultLabel = srcType === 'PROTECTION'
            ? 'Panjang jalur dari proteksi ke ujung feeder (estimasi topologi)'
            : 'Panjang jalur dari GI ke ujung feeder (estimasi topologi)';

        const labelText = topoObj?.topology_path_length_label || defaultLabel;
        if (pathLabelEl) {
            pathLabelEl.innerHTML = '<i class="fas fa-ruler-horizontal text-success me-1"></i> ' + escapeHtml(labelText);
        }

        const pathLenM = topoObj?.topology_path_length_m;
        const constraintErr = topoObj?.constraint_error || null;

        if (pathValueEl) {
            if (pathLenM !== null && pathLenM !== undefined && Number(pathLenM) > 0) {
                const mNum = Number(pathLenM);
                const kmNum = (mNum / 1000.0).toFixed(2);
                pathValueEl.innerText = mNum.toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' m (' + kmNum + ' km)';
                pathValueEl.className = 'fs-6 fw-bold text-success';
            } else {
                pathValueEl.innerText = 'Belum tersedia';
                pathValueEl.className = 'fs-6 fw-bold text-secondary';
            }
        }

        if (pathConstraintEl) {
            if (constraintErr) {
                pathConstraintEl.innerHTML = '<span class="text-danger fw-semibold"><i class="fas fa-triangle-exclamation me-1"></i>' + escapeHtml(constraintErr) + '</span>';
            } else if (pathLenM !== null && pathLenM !== undefined && Number(pathLenM) > 0) {
                pathConstraintEl.innerText = 'Dihitung otomatis dari titik sumber acuan (0.0 m) menelusuri transline ke arah hilir hingga ujung penyulang. Bukan jarak terukur relay.';
            } else {
                pathConstraintEl.innerText = 'Jalur transline hilir dari titik sumber belum tersedia atau belum lengkap.';
            }
        }
    }

    // Reset previous analysis results, markers, zones, and layers when feeder/ULP changes
    function resetAnalysisResultsAndMap(feederLabel = '') {
        if (locateAbortController) {
            locateAbortController.abort();
            locateAbortController = null;
        }
        locateFetchSeq++;
        lastCalculatedResult = null;
        currentCaseUuid = '';
        window.flCachedCandidates = [];
        window.flCachedCandidateZone = null;
        window.flCachedIsSinglePoint = false;
        if (window.flActiveHighlightLayer) {
            miniMapLayerGroup.removeLayer(window.flActiveHighlightLayer);
            window.flActiveHighlightLayer = null;
        }

        clearMiniMapLayers('MENUNGGU ANALISIS PENYULANG', 'badge bg-secondary text-white');

        const feederEl = document.getElementById('feederId');
        const selectedOptText = feederLabel || (feederEl && feederEl.selectedIndex >= 0 && feederEl.value ? feederEl.options[feederEl.selectedIndex].text : 'Belum Dipilih');

        const resFeederName = document.getElementById('resFeederName');
        if (resFeederName) resFeederName.innerText = selectedOptText;
        const resTotalSpan = document.getElementById('resTotalSpan');
        if (resTotalSpan) resTotalSpan.innerText = '0';
        const resAnchorDesc = document.getElementById('resAnchorDesc');
        if (resAnchorDesc) resAnchorDesc.innerText = 'Klik "Hitung & Tampilkan Estimasi" untuk menjalankan analisis pada penyulang terpilih.';
        const resCandidateAsset = document.getElementById('resCandidateAsset');
        if (resCandidateAsset) resCandidateAsset.innerHTML = '<span class="text-muted">Menunggu eksekusi analisis pada penyulang terpilih...</span>';
        const resSegmentName = document.getElementById('resSegmentName');
        if (resSegmentName) resSegmentName.innerText = '-';
        const resSegmentPoles = document.getElementById('resSegmentPoles');
        if (resSegmentPoles) resSegmentPoles.innerText = '';
        const resCoords = document.getElementById('resCoords');
        if (resCoords) resCoords.innerText = '-';
        const poleSeqCount = document.getElementById('poleSeqCount');
        if (poleSeqCount) poleSeqCount.innerText = '0 Bentang';
        const poleSequenceBody = document.getElementById('poleSequenceBody');
        if (poleSequenceBody) {
            poleSequenceBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Klik tombol <strong>HITUNG &amp; TAMPILKAN ESTIMASI</strong> untuk menelusuri graf transline penyulang ini.</td></tr>';
        }
        const insufficientAlert = document.getElementById('insufficientDataAlert');
        if (insufficientAlert) insufficientAlert.style.display = 'none';
        const saveBanner = document.getElementById('saveCaseStatusBanner');
        if (saveBanner) saveBanner.style.display = 'none';
        const outcomeBadge = document.getElementById('outcomeBadge');
        if (outcomeBadge) {
            outcomeBadge.className = 'badge-unresolved';
            outcomeBadge.innerText = 'MENUNGGU ANALISIS';
        }
        const gisBtn = document.getElementById('btnOpenGis');
        if (gisBtn) {
            gisBtn.href = '<?= site_url('gis') ?>' + (feederEl && feederEl.value ? ('?penyulang_id=' + encodeURIComponent(feederEl.value)) : '');
        }
    }

    // Helper: Reset downstream Feeder Context & Protection Device
    function clearFeederNetworkContext() {
        if (contextAbortController) {
            contextAbortController.abort();
            contextAbortController = null;
        }
        contextFetchSeq++;

        const pill = document.getElementById('feederReadinessPill');
        const contextDetails = document.getElementById('feederContextDetails');
        const segTbody = document.getElementById('feederSegmentsPreviewBody');
        const protSelect = document.getElementById('protectionDeviceId');
        const srcProtRadio = document.getElementById('srcProtection');
        const protNotice = document.getElementById('noProtectionNotice');
        const protContainer = document.getElementById('protectionDeviceContainer');

        if (pill) {
            pill.className = 'badge bg-secondary-subtle text-dark border';
            pill.innerText = 'Pilih penyulang';
        }
        if (contextDetails) {
            contextDetails.innerHTML = '<span class="text-muted">Pilih penyulang untuk memuat konteks topologi resmi.</span>';
        }
        if (segTbody) {
            segTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-2">Pilih penyulang untuk melihat segmen.</td></tr>';
        }
        if (protSelect) {
            protSelect.innerHTML = '<option value="">-- Pilih Perangkat Proteksi --</option>';
        }
        if (srcProtRadio) {
            srcProtRadio.disabled = true;
        }
        if (protNotice) {
            protNotice.style.display = 'block';
        }
        if (srcProtRadio && srcProtRadio.checked) {
            const srcGI = document.getElementById('srcGI');
            if (srcGI) srcGI.checked = true;
            if (protContainer) protContainer.style.display = 'none';
        }

        updateAutoConductorAndTopologyUI(
            { is_available: false, status: 'BELUM_TERSEDIA', display_label: 'Belum tersedia', status_message: 'Pilih penyulang dan sumber acuan terlebih dahulu.' },
            { topology_path_length_m: null, topology_path_length_label: 'Panjang jalur dari GI ke ujung feeder (estimasi topologi)' },
            'GI'
        );
    }

    async function handleUlpChange(ulpId) {
        if (ulpId === currentUlpValue && document.getElementById('feederId') && document.getElementById('feederId').options.length > 1) {
            return;
        }
        currentUlpValue = ulpId;
        currentFeederValue = '';

        const feederSelect = document.getElementById('feederId');
        if (!feederSelect) return;

        feederSelect.innerHTML = '<option value="">Memuat penyulang...</option>';
        refreshSelect2(feederSelect);

        clearFeederNetworkContext();
        resetAnalysisResultsAndMap('Belum Dipilih');

        if (!ulpId) {
            feederSelect.innerHTML = '<option value="">-- Pilih ULP Dahulu --</option>';
            refreshSelect2(feederSelect);
            return;
        }

        if (ulpAbortController) {
            ulpAbortController.abort();
        }
        ulpAbortController = new AbortController();
        const thisSeq = ++ulpFetchSeq;

        try {
            const res = await fetch('<?= site_url('api/fault-locator/feeders-by-ulp') ?>/' + encodeURIComponent(ulpId), {
                signal: ulpAbortController.signal
            });
            const data = await res.json();

            if (thisSeq !== ulpFetchSeq) return;

            feederSelect.innerHTML = '';

            if (data.status === 'success' && Array.isArray(data.data) && data.data.length > 0) {
                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.innerText = '-- Pilih Penyulang (' + data.data.length + ') --';
                feederSelect.appendChild(defaultOpt);

                data.data.forEach(f => {
                    const opt = document.createElement('option');
                    opt.value = f.id;
                    opt.innerText = f.nama_penyulang + (f.kode_penyulang ? ' (' + f.kode_penyulang + ')' : '');
                    feederSelect.appendChild(opt);
                });
            } else {
                const emptyOpt = document.createElement('option');
                emptyOpt.value = '';
                emptyOpt.innerText = '-- Tidak ada penyulang untuk ULP ini --';
                feederSelect.appendChild(emptyOpt);
            }

            refreshSelect2(feederSelect);
        } catch (e) {
            if (e.name === 'AbortError') return;
            if (thisSeq !== ulpFetchSeq) return;
            console.error('Error fetching feeders:', e);
            feederSelect.innerHTML = '<option value="">-- Gagal memuat penyulang --</option>';
            refreshSelect2(feederSelect);
        }
    }

    function handleFeederChange(feederId) {
        if (feederId === currentFeederValue) return;
        currentFeederValue = feederId;
        resetAnalysisResultsAndMap();
        if (feederId) {
            const srcRadio = document.querySelector('input[name="sourceType"]:checked');
            const srcType = srcRadio ? srcRadio.value : 'GI';
            const protId = document.getElementById('protectionDeviceId') ? document.getElementById('protectionDeviceId').value : '';
            loadFeederNetworkContext(feederId, srcType, protId);
        } else {
            clearFeederNetworkContext();
        }
    }

    const ulpEl = document.getElementById('ulpId');
    const feederEl = document.getElementById('feederId');

    if (ulpEl) {
        ulpEl.addEventListener('change', function() { handleUlpChange(this.value); });
    }
    if (feederEl) {
        feederEl.addEventListener('change', function() { handleFeederChange(this.value); });
    }
    if (window.jQuery) {
        $('#ulpId').on('change', function() { handleUlpChange(this.value); });
        $('#feederId').on('change', function() { handleFeederChange(this.value); });
    }

    // Feeder Network Context Loader (Race-Condition Protected, Source-Aware)
    async function loadFeederNetworkContext(feederId, sourceTypeOverride = null, protectionIdOverride = null) {
        if (!feederId) return;

        if (contextAbortController) {
            contextAbortController.abort();
        }
        contextAbortController = new AbortController();
        const thisContextSeq = ++contextFetchSeq;

        const pill = document.getElementById('feederReadinessPill');
        const contextDetails = document.getElementById('feederContextDetails');
        const srcProtRadio = document.getElementById('srcProtection');
        const protNotice = document.getElementById('noProtectionNotice');
        const protContainer = document.getElementById('protectionDeviceContainer');
        const protSelect = document.getElementById('protectionDeviceId');

        const activeSrcRadio = document.querySelector('input[name="sourceType"]:checked');
        const srcType = sourceTypeOverride || (activeSrcRadio ? activeSrcRadio.value : 'GI');
        const prevProtId = protectionIdOverride !== null ? String(protectionIdOverride) : (protSelect ? String(protSelect.value || '') : '');

        pill.className = 'badge bg-secondary-subtle text-dark border';
        pill.innerText = 'Menganalisis...';

        const qs = new URLSearchParams({
            source_type: srcType,
            protection_device_id: prevProtId || '0'
        });

        try {
            const res = await fetch('<?= site_url('api/fault-locator/feeder-network-context') ?>/' + encodeURIComponent(feederId) + '?' + qs.toString(), {
                signal: contextAbortController.signal
            });
            const json = await res.json();
            if (thisContextSeq !== contextFetchSeq) return;
            if (String(document.getElementById('feederId').value) !== String(feederId)) return;

            if (json.status === 'success' && json.data) {
                const data = json.data;
                const topo = data.topology || {};
                const cond = data.conductor || {};

                // 1. Update Automatic Conductor & Topological Path Length boxes
                updateAutoConductorAndTopologyUI(cond, topo, srcType);

                // 2. Display readiness and topology summary
                const isReady = topo.is_topology_ready;
                pill.className = isReady ? 'badge bg-success-subtle text-success border border-success' : 'badge bg-warning-subtle text-dark border border-warning';
                pill.innerText = isReady ? 'TOPOLOGI SIAP' : 'TOPOLOGI BELUM LENGKAP';

                const pathLenLabel = topo.topology_path_length_label || 'Panjang jalur ke ujung feeder (estimasi topologi)';
                const pathLenText = (topo.topology_path_length_m !== null && topo.topology_path_length_m !== undefined)
                    ? `${Number(topo.topology_path_length_m).toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} m (${topo.topology_path_length_km || 0} km)`
                    : 'Belum tersedia';

                let detailsHtml = `<strong>${escapeHtml(pathLenLabel)}:</strong> <span class="text-success fw-bold">${escapeHtml(pathLenText)}</span><br>`;
                detailsHtml += `<strong>Tiang JTM:</strong> ${topo.valid_jtm_poles_count || 0} tiang &bull; `;
                detailsHtml += `<strong>Segmen Hilir:</strong> ${topo.traversed_segment_count ?? topo.segment_count ?? 0} span<br>`;
                detailsHtml += `<strong>Konduktor Jalur:</strong> <span class="badge bg-light text-primary border">${escapeHtml(cond.display_label || cond.official_type || 'Belum tersedia')}</span> `;
                if (cond.is_hybrid) {
                    detailsHtml += `<span class="badge bg-warning-subtle text-dark border">Multi-Segmen</span> `;
                }
                detailsHtml += `<a class="text-primary text-decoration-none fw-bold ms-1" data-bs-toggle="collapse" href="#feederSegmentsCollapse" role="button" aria-expanded="false" aria-controls="feederSegmentsCollapse">[Lihat Segmen]</a>`;
                contextDetails.innerHTML = detailsHtml;

                // 3. Render Segments Preview Table
                const segTbody = document.getElementById('feederSegmentsPreviewBody');
                if (data.segments_preview && data.segments_preview.length > 0) {
                    segTbody.innerHTML = '';
                    data.segments_preview.forEach((s, idx) => {
                        const tr = document.createElement('tr');
                        const seqNo = s.seq_no !== undefined ? s.seq_no : (idx + 1);
                        const fromPole = s.from_pole || s.source_kode || '-';
                        const toPole = s.to_pole || s.target_kode || '-';
                        const spanM = s.span_meters !== undefined ? s.span_meters : (s.distance_meters || 0);
                        const condText = ((s.conductor_type || 'Belum tersedia') + ((s.conductor_size && s.conductor_size !== '-') ? ' ' + s.conductor_size : '')).trim();
                        tr.innerHTML = `
                            <td>${seqNo}</td>
                            <td>${escapeHtml(fromPole)}</td>
                            <td>${escapeHtml(toPole)}</td>
                            <td class="text-end">${Number(spanM).toFixed(1)}m</td>
                            <td><span class="badge bg-light text-muted border" style="font-size:0.65rem;">${escapeHtml(condText)}</span></td>
                        `;
                        segTbody.appendChild(tr);
                    });
                } else {
                    segTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-2">Tidak ada segmen transline tiang JTM terpetakan untuk titik sumber ini.</td></tr>';
                }

                // 4. Update Protection Devices dropdown while preserving active selection
                const pDevs = data.protection_devices || {};
                protSelect.innerHTML = '<option value="">-- Pilih Perangkat Proteksi --</option>';
                if (pDevs.available && pDevs.devices && pDevs.devices.length > 0) {
                    srcProtRadio.disabled = false;
                    protNotice.style.display = 'none';
                    pDevs.devices.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.id;
                        opt.innerText = d.display_label;
                        if (prevProtId && String(d.id) === String(prevProtId)) {
                            opt.selected = true;
                        }
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
            if (e.name === 'AbortError') return;
            if (thisContextSeq !== contextFetchSeq) return;
            console.error('Error loading feeder network context:', e);
            contextDetails.innerHTML = '<span class="text-danger">Gagal memuat konteks topologi resmi.</span>';
        }
    }

    if (document.getElementById('feederId') && document.getElementById('feederId').value) {
        loadFeederNetworkContext(document.getElementById('feederId').value);
    }

    // Toggle Protection Device container & refresh source-aware topology/conductor context
    document.querySelectorAll('input[name="sourceType"]').forEach(el => {
        el.addEventListener('change', async function() {
            const container = document.getElementById('protectionDeviceContainer');
            const protSelect = document.getElementById('protectionDeviceId');
            const feederVal = document.getElementById('feederId') ? document.getElementById('feederId').value : '';

            resetAnalysisResultsAndMap();

            if (this.value === 'PROTECTION') {
                container.style.display = 'block';
                if (protSelect && !protSelect.value && protSelect.options.length === 2) {
                    protSelect.selectedIndex = 1;
                }
                const chosenProt = protSelect ? protSelect.value : '';
                if (feederVal) {
                    await loadFeederNetworkContext(feederVal, 'PROTECTION', chosenProt);
                }
                if (feederVal && chosenProt) {
                    document.getElementById('btnLocateFault').click();
                } else {
                    const resAnchorDesc = document.getElementById('resAnchorDesc');
                    if (resAnchorDesc) {
                        resAnchorDesc.innerText = 'Pilih perangkat proteksi (Recloser / PMCB) untuk menghitung jarak ke arah hilir penyulang.';
                    }
                }
            } else {
                container.style.display = 'none';
                if (feederVal) {
                    await loadFeederNetworkContext(feederVal, 'GI', 0);
                    document.getElementById('btnLocateFault').click();
                }
            }
        });
    });

    const protDeviceSelectEl = document.getElementById('protectionDeviceId');
    if (protDeviceSelectEl) {
        protDeviceSelectEl.addEventListener('change', async function() {
            resetAnalysisResultsAndMap();
            const srcTypeEl = document.querySelector('input[name="sourceType"]:checked');
            const feederVal = document.getElementById('feederId') ? document.getElementById('feederId').value : '';
            if (feederVal) {
                await loadFeederNetworkContext(feederVal, srcTypeEl ? srcTypeEl.value : 'PROTECTION', this.value);
            }
            if (this.value && feederVal && srcTypeEl && srcTypeEl.value === 'PROTECTION') {
                document.getElementById('btnLocateFault').click();
            }
        });
    }

    // Parse & Validate Current (A), Phase Voltage (kV), and Optional Relay Distance (m)
    // Strictly distinguishes 0.00 kV (0.0) from empty/unentered (null)
    function parseAndValidateFormTelemetry() {
        const errors = [];
        const alertEl = document.getElementById('telemetryValidationAlert');

        function readCurrent(elId, label) {
            const raw = String(document.getElementById(elId)?.value ?? '').trim().replace(',', '.');
            if (raw === '') return 0.0;
            const num = Number(raw);
            if (Number.isNaN(num) || !Number.isFinite(num) || num < 0 || num > 50000) {
                errors.push(`Arus ${label} harus berupa angka 0.00 – 50,000.00 A.`);
                return 0.0;
            }
            return num;
        }

        function readOptionalVoltageKv(elId, label) {
            const raw = String(document.getElementById(elId)?.value ?? '').trim().replace(',', '.');
            if (raw === '') return null; // Unentered -> null (distinct from 0.00 kV)
            const num = Number(raw);
            if (Number.isNaN(num) || !Number.isFinite(num) || num < 0 || num > 35) {
                errors.push(`Tegangan ${label} harus berada dalam rentang 0.00 – 35.00 kV.`);
                return null;
            }
            return Number(num.toFixed(2));
        }

        function readOptionalRelayDistanceM(elId) {
            const raw = String(document.getElementById(elId)?.value ?? '').trim().replace(',', '.');
            if (raw === '') return null;
            const num = Number(raw);
            if (Number.isNaN(num) || !Number.isFinite(num) || num < 0 || num > 250000) {
                errors.push('Jarak gangguan terukur relay harus berada dalam rentang > 0 – 250,000 Meter.');
                return null;
            }
            return num > 0 ? Number(num.toFixed(1)) : null;
        }

        const currentR = readCurrent('currentR', 'Fasa R');
        const currentS = readCurrent('currentS', 'Fasa S');
        const currentT = readCurrent('currentT', 'Fasa T');
        const currentN = readCurrent('currentN', 'Netral / GFR');

        const voltageR = readOptionalVoltageKv('voltageR', 'Fasa R');
        const voltageS = readOptionalVoltageKv('voltageS', 'Fasa S');
        const voltageT = readOptionalVoltageKv('voltageT', 'Fasa T');
        const relayDistanceM = readOptionalRelayDistanceM('relayDistanceM');

        if (alertEl) {
            if (errors.length > 0) {
                alertEl.innerHTML = '<i class="fas fa-circle-exclamation me-1"></i>' + errors.map(escapeHtml).join('<br>');
                alertEl.style.display = 'block';
            } else {
                alertEl.innerHTML = '';
                alertEl.style.display = 'none';
            }
        }

        return {
            valid: errors.length === 0,
            errors,
            values: {
                current_r: currentR,
                current_s: currentS,
                current_t: currentT,
                current_n: currentN,
                voltage_r_kv: voltageR,
                voltage_s_kv: voltageS,
                voltage_t_kv: voltageT,
                relay_distance_m: relayDistanceM,
                relay_measured_distance_m: relayDistanceM
            }
        };
    }

    // Locate Fault Action (Race-Condition Protected)
    document.getElementById('btnLocateFault').addEventListener('click', async function() {
        const feederId = document.getElementById('feederId').value;
        const alertEl = document.getElementById('telemetryValidationAlert');

        if (!feederId) {
            if (alertEl) {
                alertEl.innerHTML = '<i class="fas fa-circle-exclamation me-1"></i>Silakan pilih penyulang terlebih dahulu.';
                alertEl.style.display = 'block';
            }
            return;
        }

        const srcType = document.querySelector('input[name="sourceType"]:checked').value;
        const protId = document.getElementById('protectionDeviceId').value;

        if (srcType === 'PROTECTION' && !protId) {
            if (alertEl) {
                alertEl.innerHTML = '<i class="fas fa-circle-exclamation me-1"></i>Silakan pilih peralatan proteksi (Recloser / PMCB) yang menjadi acuan.';
                alertEl.style.display = 'block';
            }
            return;
        }

        const parsedTel = parseAndValidateFormTelemetry();
        if (!parsedTel.valid) {
            return;
        }

        const payload = {
            ulp_id: document.getElementById('ulpId').value,
            feeder_id: feederId,
            source_type: srcType,
            protection_device_id: protId ? parseInt(protId, 10) : null,
            fault_time: document.getElementById('faultTimestamp').value,
            current_r: parsedTel.values.current_r,
            current_s: parsedTel.values.current_s,
            current_t: parsedTel.values.current_t,
            current_n: parsedTel.values.current_n,
            voltage_r_kv: parsedTel.values.voltage_r_kv,
            voltage_s_kv: parsedTel.values.voltage_s_kv,
            voltage_t_kv: parsedTel.values.voltage_t_kv,
            conductor_type: 'AUTO',
            relay_distance_m: parsedTel.values.relay_distance_m,
            relay_measured_distance_m: parsedTel.values.relay_measured_distance_m
        };

        if (locateAbortController) {
            locateAbortController.abort();
        }
        locateAbortController = new AbortController();
        const thisLocateSeq = ++locateFetchSeq;

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menghitung Solusi Graf JTM...';

        try {
            const res = await fetch('<?= site_url('api/fault-locator/locate') ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload),
                signal: locateAbortController.signal
            });
            const data = await res.json();
            if (thisLocateSeq !== locateFetchSeq) return;
            if (String(document.getElementById('feederId').value) !== String(feederId)) return;

            if (data.status === 'success' && data.data) {
                if (String(data.data.feeder_id) !== String(feederId)) {
                    console.warn('Discarded mismatched feeder response:', data.data.feeder_id, 'expected:', feederId);
                    return;
                }
                lastCalculatedResult = data.data;
                currentCaseUuid = data.data.case_uuid;
                updateResultsUI(data.data);
            } else {
                if (alertEl) {
                    alertEl.innerHTML = '<i class="fas fa-circle-exclamation me-1"></i>' + escapeHtml(data.message || 'Gagal menghitung lokasi gangguan.');
                    alertEl.style.display = 'block';
                }
            }
        } catch(e) {
            if (e.name === 'AbortError') return;
            if (thisLocateSeq !== locateFetchSeq) return;
            console.error('Error locating fault:', e);
            if (alertEl) {
                alertEl.innerHTML = '<i class="fas fa-circle-exclamation me-1"></i>Terjadi kesalahan koneksi server.';
                alertEl.style.display = 'block';
            }
        } finally {
            if (thisLocateSeq === locateFetchSeq) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-crosshairs me-2"></i> HITUNG &amp; TAMPILKAN ESTIMASI';
            }
        }
    });

    // Attach handler to Review/Reload a saved case into the form & results
    function bindReviewCaseButton(btn) {
        if (!btn || btn.dataset.boundReview === '1') return;
        btn.dataset.boundReview = '1';
        btn.addEventListener('click', async function() {
            const ulpId = this.getAttribute('data-ulp-id') || '';
            const penyulangId = this.getAttribute('data-penyulang-id') || '';
            const srcType = (this.getAttribute('data-source-type') || 'GI').toUpperCase();
            const protId = this.getAttribute('data-protection-id') || '';

            const cR = this.getAttribute('data-current-r') ?? '0';
            const cS = this.getAttribute('data-current-s') ?? '0';
            const cT = this.getAttribute('data-current-t') ?? '0';
            const cN = this.getAttribute('data-current-n') ?? '0';

            const vR = this.getAttribute('data-voltage-r') ?? '';
            const vS = this.getAttribute('data-voltage-s') ?? '';
            const vT = this.getAttribute('data-voltage-t') ?? '';
            const rDist = this.getAttribute('data-relay-distance') ?? '';

            document.getElementById('currentR').value = cR;
            document.getElementById('currentS').value = cS;
            document.getElementById('currentT').value = cT;
            document.getElementById('currentN').value = cN;

            document.getElementById('voltageR').value = vR;
            document.getElementById('voltageS').value = vS;
            document.getElementById('voltageT').value = vT;
            document.getElementById('relayDistanceM').value = rDist;

            if (ulpId && document.getElementById('ulpId') && String(document.getElementById('ulpId').value) !== String(ulpId)) {
                document.getElementById('ulpId').value = ulpId;
                refreshSelect2(document.getElementById('ulpId'));
                await handleUlpChange(ulpId);
            }

            if (penyulangId && document.getElementById('feederId')) {
                document.getElementById('feederId').value = penyulangId;
                currentFeederValue = String(penyulangId);
                refreshSelect2(document.getElementById('feederId'));
                await loadFeederNetworkContext(penyulangId, srcType, protId);
            }

            const srcGiRadio = document.getElementById('srcGI');
            const srcProtRadio = document.getElementById('srcProtection');
            const protContainer = document.getElementById('protectionDeviceContainer');
            const protSelect = document.getElementById('protectionDeviceId');

            if (srcType === 'PROTECTION' && srcProtRadio && !srcProtRadio.disabled) {
                srcProtRadio.checked = true;
                if (protContainer) protContainer.style.display = 'block';
                if (protSelect && protId) {
                    protSelect.value = protId;
                }
            } else if (srcGiRadio) {
                srcGiRadio.checked = true;
                if (protContainer) protContainer.style.display = 'none';
            }

            document.getElementById('btnLocateFault').click();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    document.querySelectorAll('.btn-review-case').forEach(bindReviewCaseButton);

    // Save Case Action (Persists Currents & Phase Voltages and updates History Table for immediate review)
    document.getElementById('btnSaveCase').addEventListener('click', async function() {
        const saveBanner = document.getElementById('saveCaseStatusBanner');
        if (!lastCalculatedResult) {
            if (saveBanner) {
                saveBanner.className = 'alert alert-warning border py-2 px-3 mb-3 small';
                saveBanner.innerText = 'Lakukan perhitungan terlebih dahulu sebelum menyimpan kasus.';
                saveBanner.style.display = 'block';
            }
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
                if (saveBanner) {
                    saveBanner.className = 'alert alert-success border py-2 px-3 mb-3 small';
                    saveBanner.innerHTML = `<i class="fas fa-circle-check me-1"></i>Kasus <strong>${escapeHtml(data.case_number)}</strong> berhasil disimpan beserta telemetri arus &amp; tegangan fasa.`;
                    saveBanner.style.display = 'block';
                }

                // Prepend or update row in #caseHistoryBody so it can be reviewed immediately without page reload
                const historyTbody = document.getElementById('caseHistoryBody');
                if (historyTbody) {
                    const fc = lastCalculatedResult.fault_classification || {};
                    const cR = Number(fc.current_r ?? 0);
                    const cS = Number(fc.current_s ?? 0);
                    const cT = Number(fc.current_t ?? 0);
                    const cN = Number(fc.current_n ?? 0);
                    const vR = fc.voltage_r_kv !== undefined ? fc.voltage_r_kv : null;
                    const vS = fc.voltage_s_kv !== undefined ? fc.voltage_s_kv : null;
                    const vT = fc.voltage_t_kv !== undefined ? fc.voltage_t_kv : null;
                    const hasV = (vR !== null || vS !== null || vT !== null);
                    const topoLen = lastCalculatedResult.topology_path_length_m;
                    const relayDist = lastCalculatedResult.relay_measured_distance_m;

                    const vStr = hasV
                        ? `V (R/S/T): ${vR !== null ? Number(vR).toFixed(2) : '-'} / ${vS !== null ? Number(vS).toFixed(2) : '-'} / ${vT !== null ? Number(vT).toFixed(2) : '-'} kV`
                        : 'Tegangan: Belum diisi';

                    const tr = document.createElement('tr');
                    tr.setAttribute('data-saved-case-number', data.case_number);
                    tr.innerHTML = `
                        <td class="font-monospace text-primary small fw-bold">${escapeHtml(data.case_number)}</td>
                        <td>
                            <div class="text-dark fw-bold">${escapeHtml(lastCalculatedResult.feeder_name || '-')}</div>
                            <div class="text-muted" style="font-size:0.72rem;">${escapeHtml(lastCalculatedResult.source_type || 'GI')} &bull; ${escapeHtml(fc.fault_type_label || fc.fault_type || '-')}</div>
                        </td>
                        <td>
                            <span class="text-danger small fw-bold">${cR.toFixed(1)} / ${cS.toFixed(1)} / ${cT.toFixed(1)} A</span>
                            <div class="text-muted" style="font-size:0.72rem;">N: ${cN.toFixed(1)} A</div>
                            <div class="${hasV ? 'text-primary fw-semibold' : 'text-muted'} mt-1" style="font-size:0.71rem;">${escapeHtml(vStr)}</div>
                        </td>
                        <td class="small">
                            ${topoLen !== null && topoLen !== undefined ? `<div class="text-success fw-semibold">Jalur: ${Number(topoLen).toFixed(1)} m</div>` : ''}
                            ${relayDist !== null && relayDist !== undefined ? `<div class="text-primary fw-bold">Relay: ${Number(relayDist).toFixed(1)} m</div>` : `<div class="text-muted">Relay: -</div>`}
                        </td>
                        <td><span class="badge bg-secondary small">MENUNGGU PEMERIKSAAN</span></td>
                        <td class="small"><span class="text-muted">Belum ada inspeksi</span></td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 btn-review-case"
                                    data-case-id="${escapeHtml(data.case_id || '')}"
                                    data-case-number="${escapeHtml(data.case_number)}"
                                    data-ulp-id="${escapeHtml(lastCalculatedResult.ulp_id || '')}"
                                    data-penyulang-id="${escapeHtml(lastCalculatedResult.feeder_id || '')}"
                                    data-source-type="${escapeHtml(lastCalculatedResult.source_type || 'GI')}"
                                    data-protection-id="${escapeHtml(lastCalculatedResult.protection_device_id || '')}"
                                    data-current-r="${cR}"
                                    data-current-s="${cS}"
                                    data-current-t="${cT}"
                                    data-current-n="${cN}"
                                    data-voltage-r="${vR !== null ? Number(vR).toFixed(2) : ''}"
                                    data-voltage-s="${vS !== null ? Number(vS).toFixed(2) : ''}"
                                    data-voltage-t="${vT !== null ? Number(vT).toFixed(2) : ''}"
                                    data-relay-distance="${relayDist !== null && relayDist !== undefined ? Number(relayDist).toFixed(1) : ''}">
                                    <i class="fas fa-eye me-1"></i> Tinjau
                                </button>
                            </div>
                        </td>
                    `;
                    historyTbody.prepend(tr);
                    bindReviewCaseButton(tr.querySelector('.btn-review-case'));
                }
            } else {
                if (saveBanner) {
                    saveBanner.className = 'alert alert-danger border py-2 px-3 mb-3 small';
                    saveBanner.innerText = 'Gagal menyimpan: ' + (data.message || '');
                    saveBanner.style.display = 'block';
                }
            }
        } catch(e) {
            console.error('Error saving case:', e);
            if (saveBanner) {
                saveBanner.className = 'alert alert-danger border py-2 px-3 mb-3 small';
                saveBanner.innerText = 'Gagal menghubungi server.';
                saveBanner.style.display = 'block';
            }
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
            const saveBanner = document.getElementById('saveCaseStatusBanner');
            if (data.status === 'success' && saveBanner) {
                saveBanner.className = 'alert alert-info border py-2 px-3 mb-3 small';
                saveBanner.innerText = 'Surat Tugas Lapangan berhasil diterbitkan: ' + data.dispatch.dispatch_id + ' untuk ' + data.dispatch.assigned_team;
                saveBanner.style.display = 'block';
            }
        } catch(e) {
            console.error('Error dispatching:', e);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Buat Surat Tugas';
        }
    });

    // Populate Candidate Poles Select in the Outcome Modal
    function populateOutcomeModalCandidates(resultData) {
        const selectEl = document.getElementById('modalFoundAssetSelect');
        if (!selectEl) return;

        selectEl.innerHTML = '<option value="">-- Pilih Tiang JTM Temuan / Kandidat --</option>';

        const seenIds = new Set();
        const addPoleOption = (id, kode, cumDist, lat, lng, isCand = false) => {
            if (!id || seenIds.has(Number(id))) return;
            seenIds.add(Number(id));
            const opt = document.createElement('option');
            opt.value = id;
            opt.dataset.assetId = id;
            opt.dataset.kode = kode;
            opt.dataset.cumDist = (cumDist !== null && cumDist !== undefined) ? Number(cumDist).toFixed(1) : '';
            opt.dataset.lat = (lat !== null && lat !== undefined) ? Number(lat).toFixed(6) : '';
            opt.dataset.lng = (lng !== null && lng !== undefined) ? Number(lng).toFixed(6) : '';

            const distStr = opt.dataset.cumDist ? ` (+${opt.dataset.cumDist}m)` : '';
            const tagStr = isCand ? ' [KANDIDAT UTAMA]' : '';
            opt.innerText = `${kode}${distStr}${tagStr}`;
            selectEl.appendChild(opt);
        };

        // 1. Primary candidate assets
        if (Array.isArray(resultData?.candidate_assets)) {
            resultData.candidate_assets.forEach(ca => {
                addPoleOption(ca.id, ca.kode_asset, resultData.calculated_distance_m, ca.latitude, ca.longitude, true);
            });
        }

        // 2. Candidate Zone poles
        if (Array.isArray(resultData?.candidate_zone?.candidate_poles)) {
            resultData.candidate_zone.candidate_poles.forEach(cp => {
                addPoleOption(cp.id, cp.kode_asset || cp.kode_tiang, cp.cumulative_meters, cp.latitude, cp.longitude, true);
            });
        }

        // 3. Downstream sequence poles
        if (Array.isArray(resultData?.pole_sequence)) {
            resultData.pole_sequence.forEach(ps => {
                if (ps.to_pole_id) {
                    addPoleOption(ps.to_pole_id, ps.to_pole_kode, ps.cumulative_meters, ps.to_coords?.lat, ps.to_coords?.lng, Boolean(ps.is_fault_zone || ps.is_candidate_zone));
                }
            });
        }
    }

    // Auto-fill coordinates and distance when a candidate pole is selected in modal
    const foundAssetSelect = document.getElementById('modalFoundAssetSelect');
    if (foundAssetSelect) {
        foundAssetSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (!opt || !opt.value) return;

            const assetIdInput = document.getElementById('modalFoundAssetId');
            if (assetIdInput && opt.dataset.assetId) assetIdInput.value = opt.dataset.assetId;

            const distInput = document.getElementById('modalActualDistanceM');
            if (distInput && opt.dataset.cumDist) distInput.value = opt.dataset.cumDist;

            const latInput = document.getElementById('modalActualLatitude');
            if (latInput && opt.dataset.lat) latInput.value = opt.dataset.lat;

            const lngInput = document.getElementById('modalActualLongitude');
            if (lngInput && opt.dataset.lng) lngInput.value = opt.dataset.lng;
        });
    }

    // Trigger Outcome Modal for Current Active Calculated Case
    const btnRecordCurrentCase = document.getElementById('btnRecordOutcomeCurrentCase');
    if (btnRecordCurrentCase) {
        btnRecordCurrentCase.addEventListener('click', async function() {
            if (!lastCalculatedResult) {
                alert('Silakan lakukan analisis gangguan terlebih dahulu sebelum mencatat hasil patroli.');
                return;
            }

            const btn = this;
            btn.disabled = true;

            try {
                // If case has not been saved yet, auto-save to get official case_id and case_number
                let caseId = lastCalculatedResult.case_id;
                let caseNum = lastCalculatedResult.case_number || lastCalculatedResult.case_uuid;

                if (!caseId) {
                    const saveRes = await fetch('<?= site_url('api/fault-locator/save-case') ?>', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(lastCalculatedResult)
                    });
                    const saveData = await saveRes.json();
                    if (saveData.status === 'success' && saveData.case_id) {
                        caseId = saveData.case_id;
                        caseNum = saveData.case_number;
                        lastCalculatedResult.case_id = caseId;
                        lastCalculatedResult.case_number = caseNum;
                    }
                }

                document.getElementById('modalCaseId').value = caseId || '';
                document.getElementById('modalCaseNumberDisplay').innerText = caseNum || '-';
                document.getElementById('modalFeederDisplay').innerText = lastCalculatedResult.feeder_name || '-';

                populateOutcomeModalCandidates(lastCalculatedResult);

                // If single point fault point exists, pre-fill distance & coordinates
                if (lastCalculatedResult.fault_point && lastCalculatedResult.calculated_distance_m > 0) {
                    const latIn = document.getElementById('modalActualLatitude');
                    const lngIn = document.getElementById('modalActualLongitude');
                    const distIn = document.getElementById('modalActualDistanceM');
                    if (latIn && !latIn.value) latIn.value = Number(lastCalculatedResult.fault_point.lat).toFixed(6);
                    if (lngIn && !lngIn.value) lngIn.value = Number(lastCalculatedResult.fault_point.lng).toFixed(6);
                    if (distIn && !distIn.value) distIn.value = Number(lastCalculatedResult.calculated_distance_m).toFixed(1);
                }

                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRecordOutcome'));
                modal.show();
            } catch (err) {
                console.error('Error preparing outcome recording:', err);
                alert('Gagal menyiapkan form rekam hasil lapangan.');
            } finally {
                btn.disabled = false;
            }
        });
    }

    // Record Outcome Modal Handlers from Case History table
    document.querySelectorAll('.btn-record-outcome').forEach(btn => {
        btn.addEventListener('click', function() {
            const caseId = this.getAttribute('data-case-id');
            const caseNum = this.getAttribute('data-case-number');
            const feederName = this.getAttribute('data-feeder-name');

            document.getElementById('modalCaseId').value = caseId;
            document.getElementById('modalCaseNumberDisplay').innerText = caseNum;
            document.getElementById('modalFeederDisplay').innerText = feederName;

            // Load candidate list from last calculated result if matches, or clear
            if (lastCalculatedResult && String(lastCalculatedResult.case_id) === String(caseId)) {
                populateOutcomeModalCandidates(lastCalculatedResult);
            } else {
                const selectEl = document.getElementById('modalFoundAssetSelect');
                if (selectEl) selectEl.innerHTML = '<option value="">-- Masukkan Asset ID manual di samping --</option>';
            }

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
        if (!caseId) {
            alert('ID kasus gangguan tidak valid.');
            return;
        }

        const outcomeType = document.querySelector('input[name="outcomeType"]:checked').value;
        const causeCategory = document.getElementById('modalCauseCategory')?.value || 'GENERAL_FAULT';
        const actualLat = document.getElementById('modalActualLatitude')?.value;
        const actualLng = document.getElementById('modalActualLongitude')?.value;
        const actualDist = document.getElementById('modalActualDistanceM')?.value;

        const payload = {
            case_id: parseInt(caseId, 10),
            outcome_type: outcomeType,
            cause_category_code: causeCategory,
            found_asset_id: document.getElementById('modalFoundAssetId').value ? parseInt(document.getElementById('modalFoundAssetId').value, 10) : null,
            component_damaged: document.getElementById('modalComponentDamaged').value,
            actual_distance_m: (actualDist !== '' && actualDist !== null) ? parseFloat(actualDist) : null,
            actual_latitude: (actualLat !== '' && actualLat !== null) ? parseFloat(actualLat) : null,
            actual_longitude: (actualLng !== '' && actualLng !== null) ? parseFloat(actualLng) : null,
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
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalRecordOutcome'));
                if (modal) modal.hide();
                alert('Hasil pemeriksaan lapangan berhasil direkam. Dataset kalibrasi & riwayat kasus telah diperbarui.');
                location.reload();
            } else {
                alert('Gagal menyimpan hasil: ' + (data.message || ''));
            }
        } catch(e) {
            console.error('Error recording outcome:', e);
            alert('Terjadi kesalahan koneksi server saat menyimpan hasil.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-floppy-disk me-1"></i> SIMPAN HASIL PEMERIKSAAN';
        }
    });

    // Update Results UI
    function updateResultsUI(data) {
        const activeFeederVal = document.getElementById('feederId') ? document.getElementById('feederId').value : '';
        if (activeFeederVal && data.feeder_id && String(data.feeder_id) !== String(activeFeederVal)) {
            return;
        }

        document.getElementById('resCaseUuid').innerText = data.case_uuid;
        document.getElementById('resConfidence').innerText = data.confidence_percent + '%';
        document.getElementById('resDistance').innerText = Number(data.calculated_distance_m || 0).toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' Meter';
        document.getElementById('resFeederName').innerText = data.feeder_name || 'Feeder';
        document.getElementById('resTotalSpan').innerText = Number(data.feeder_span_meters || 0).toLocaleString();
        document.getElementById('resAnchorDesc').innerText = data.anchor_description || '';
        document.getElementById('resSegmentName').innerText = data.transline_segment || '-';

        // Update Topological Path Length vs Relay Measured Distance in Results Card & Form
        const topoLabel = data.topology_path_length_label || (data.source_type === 'PROTECTION'
            ? 'Panjang jalur dari proteksi ke ujung feeder (estimasi topologi)'
            : 'Panjang jalur dari GI ke ujung feeder (estimasi topologi)');
        const resTopoCardLabel = document.getElementById('resTopologyPathCardLabel');
        if (resTopoCardLabel) resTopoCardLabel.innerText = topoLabel;

        const resTopoLenEl = document.getElementById('resTopologyPathLength');
        if (resTopoLenEl) {
            if (data.topology_path_length_m !== null && data.topology_path_length_m !== undefined && Number(data.topology_path_length_m) > 0) {
                const mVal = Number(data.topology_path_length_m);
                const kmVal = (mVal / 1000.0).toFixed(2);
                resTopoLenEl.innerText = mVal.toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' Meter (' + kmVal + ' km)';
                resTopoLenEl.className = 'text-success fw-bold fs-6 d-block';
            } else {
                resTopoLenEl.innerText = 'Belum tersedia';
                resTopoLenEl.className = 'text-secondary fw-bold fs-6 d-block';
            }
        }

        const resRelayMeasEl = document.getElementById('resRelayMeasuredDistance');
        if (resRelayMeasEl) {
            if (data.relay_measured_distance_m !== null && data.relay_measured_distance_m !== undefined && Number(data.relay_measured_distance_m) > 0) {
                resRelayMeasEl.innerText = Number(data.relay_measured_distance_m).toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' Meter';
                resRelayMeasEl.className = 'text-primary';
            } else {
                resRelayMeasEl.innerText = 'Belum diisi';
                resRelayMeasEl.className = 'text-muted';
            }
        }

        // Sync automatic conductor & topological path length box in left form panel
        if (data.traversed_conductor) {
            updateAutoConductorAndTopologyUI(
                data.traversed_conductor,
                {
                    topology_path_length_m: data.topology_path_length_m,
                    topology_path_length_label: topoLabel,
                    constraint_error: data.source_context?.constraint_error || null
                },
                data.source_type || 'GI'
            );
            const resCondSum = document.getElementById('resConductorSummary');
            const resCondMsg = document.getElementById('resConductorMessage');
            if (resCondSum) resCondSum.innerText = data.traversed_conductor.display_label || data.traversed_conductor.official_type || 'Belum tersedia';
            if (resCondMsg) resCondMsg.innerText = data.traversed_conductor.status_message || '';
        }

        const segPolesEl = document.getElementById('resSegmentPoles');
        if (segPolesEl) {
            if (data.fault_zone_segment && data.fault_zone_segment.from_pole_kode && data.fault_zone_segment.to_pole_kode) {
                segPolesEl.innerHTML = '(' + data.fault_zone_segment.from_pole_kode + ' &rarr; ' + data.fault_zone_segment.to_pole_kode + ')';
            } else {
                segPolesEl.innerText = '';
            }
        }

        document.getElementById('resDasarBadge').innerText = data.dasar_perhitungan || 'ESTIMASI';
        document.getElementById('resDasarLabel').innerText = data.dasar_perhitungan_label || '';

        const formulaBox = document.getElementById('resFormulaDerivation');
        if (formulaBox) {
            formulaBox.innerText = data.formula_derivation || '';
            formulaBox.style.display = data.formula_derivation ? 'block' : 'none';
        }

        // Fault classification & Supporting Evidence
        if (data.fault_classification) {
            const fc = data.fault_classification;
            document.getElementById('resFaultTypeLabel').innerText = fc.fault_type_label || 'Indikasi Gangguan';
            document.getElementById('resActiveCurrent').innerText = fc.active_current_a + ' A';

            const indConfEl = document.getElementById('resIndicationConfidence');
            if (indConfEl && fc.indication_confidence_percent !== undefined) {
                indConfEl.innerText = fc.indication_confidence_percent;
            }

            const basisBadge = document.getElementById('resIndicationBasisBadge');
            if (basisBadge) {
                basisBadge.innerText = fc.has_voltage_telemetry ? 'Arus + Tegangan Fasa (kV)' : 'Arus Skalar Saja (Tanpa Tegangan Fasa)';
                basisBadge.className = fc.has_voltage_telemetry ? 'badge bg-info-subtle text-primary border' : 'badge bg-light text-muted border';
            }

            const vInline = document.getElementById('resVoltageSummaryInline');
            if (vInline) {
                if (fc.has_voltage_telemetry) {
                    const vrStr = fc.voltage_r_kv !== null && fc.voltage_r_kv !== undefined ? Number(fc.voltage_r_kv).toFixed(2) : '-';
                    const vsStr = fc.voltage_s_kv !== null && fc.voltage_s_kv !== undefined ? Number(fc.voltage_s_kv).toFixed(2) : '-';
                    const vtStr = fc.voltage_t_kv !== null && fc.voltage_t_kv !== undefined ? Number(fc.voltage_t_kv).toFixed(2) : '-';
                    vInline.innerText = `| V(R/S/T): ${vrStr} / ${vsStr} / ${vtStr} kV`;
                } else {
                    vInline.innerText = '| Tegangan Fasa: Belum diisi';
                }
            }

            const evListEl = document.getElementById('resSupportingEvidenceList');
            if (evListEl) {
                evListEl.innerHTML = '';
                if (Array.isArray(fc.supporting_evidence) && fc.supporting_evidence.length > 0) {
                    fc.supporting_evidence.forEach(ev => {
                        const li = document.createElement('li');
                        li.innerText = ev;
                        evListEl.appendChild(li);
                    });
                }
            }
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
        if (data.outcome_status === 'RESOLVED' || data.outcome_status === 'CALCULATED_RELAY_AIDED') {
            badge.className = 'badge-resolved';
        } else if (data.outcome_status === 'DATA_TIDAK_CUKUP') {
            badge.className = 'badge-data-tidak-cukup';
        } else if (data.outcome_status === 'DISTANCE_AMBIGUOUS') {
            badge.className = 'badge-ambiguous';
        } else {
            badge.className = 'badge-unresolved';
        }

        // Candidate assets (Strictly verified against selected feeder)
        const validCandidates = Array.isArray(data.candidate_assets)
            ? data.candidate_assets.filter(c => c && (!c.penyulang_id || String(c.penyulang_id) === String(data.feeder_id)))
            : [];
        const isCandidateZoneIndeterminate = Boolean(data.candidate_zone && !data.candidate_zone.is_single_point);

        renderCandidateAssetsSummary(validCandidates, !isCandidateZoneIndeterminate, data.candidate_zone, data.anchor_description);

        // Coordinates & Map
        const hasValidPoint = validCandidates.length > 0 && data.fault_point && data.fault_point.lat && data.fault_point.lng && !isCandidateZoneIndeterminate;
        const hasDownstreamPath = Array.isArray(data.pole_sequence) && data.pole_sequence.length > 0;

        if (hasValidPoint) {
            document.getElementById('resCoords').innerText = data.fault_point.lat.toFixed(6) + ', ' + data.fault_point.lng.toFixed(6);
            updateMiniMap(
                data.fault_point.lat,
                data.fault_point.lng,
                validCandidates[0].kode_asset,
                data.pole_sequence,
                data.feeder_name,
                data.feeder_code,
                data
            );
        } else if (isCandidateZoneIndeterminate && hasDownstreamPath) {
            const czStart = Number(data.candidate_zone.range_start_m || 0).toFixed(0);
            const czEnd = Number(data.candidate_zone.range_end_m || 0).toFixed(0);
            document.getElementById('resCoords').innerText = `Zona kandidat estimasi (${czStart} m – ${czEnd} m dari acuan); butuh jarak relay numerik untuk koordinat presisi tunggal`;
            updateMiniMap(
                null,
                null,
                '',
                data.pole_sequence,
                data.feeder_name,
                data.feeder_code,
                data
            );
        } else if (hasDownstreamPath) {
            document.getElementById('resCoords').innerText = 'Jalur hilir terpetakan (Masukkan jarak terukur relay untuk titik koordinat spesifik)';
            updateMiniMap(
                null,
                null,
                '',
                data.pole_sequence,
                data.feeder_name,
                data.feeder_code,
                data
            );
        } else {
            document.getElementById('resCoords').innerText = 'Tidak ada kandidat valid pada penyulang ini';
            clearMiniMapLayers('TIDAK ADA KANDIDAT VALID (' + (data.feeder_code || data.feeder_name || '-') + ')', 'badge bg-secondary text-white');
        }

        // Pole Sequence Table
        const tbody = document.getElementById('poleSequenceBody');
        tbody.innerHTML = '';
        if (data.pole_sequence && data.pole_sequence.length > 0) {
            document.getElementById('poleSeqCount').innerText = data.pole_sequence.length + ' Bentang';
            data.pole_sequence.forEach(p => {
                const tr = document.createElement('tr');
                if (p.is_fault_zone) tr.className = 'row-fault-zone';
                const condLabel = ((p.conductor_type || 'Belum tersedia') + ((p.conductor_size && p.conductor_size !== '-') ? ' ' + p.conductor_size : '')).trim();

                tr.innerHTML = `
                    <td class="text-center font-monospace">${p.seq_no}</td>
                    <td>${escapeHtml(p.from_pole_kode)}</td>
                    <td>${escapeHtml(p.to_pole_kode)}</td>
                    <td><span class="badge bg-light text-dark border" style="font-size:0.68rem;">${escapeHtml(condLabel)}</span></td>
                    <td class="text-end">${Number(p.span_meters).toFixed(1)}</td>
                    <td class="text-end fw-bold">${Number(p.cumulative_meters).toFixed(1)}</td>
                    <td>${p.is_fault_zone ? '<span class="badge bg-danger text-white"><i class="fas fa-crosshairs me-1"></i> ZONA GANGGUAN</span>' : '<span class="badge bg-light text-muted border">Normal</span>'}</td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            document.getElementById('poleSeqCount').innerText = '0 Bentang';
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Tidak ada kandidat valid pada penyulang ini (belum ada transline tiang JTM terpetakan).</td></tr>';
        }

        const gisBtn = document.getElementById('btnOpenGis');
        if (gisBtn && data.gis_url) {
            gisBtn.href = data.gis_url;
        }
    }
</script>
<?= $this->endSection() ?>
