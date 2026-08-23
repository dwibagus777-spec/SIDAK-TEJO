<?= $this->extend('layouts/app') ?>

<?= $this->section('title') ?>
Peta Jaringan Distribusi (GIS) - SIDAK TEJO Enterprise
<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<!-- Strict Order Dependency Injection: Leaflet Core CSS followed by Leaflet MarkerCluster CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

<style>
    /* Full Responsive Height Container */
    #gis-master-wrapper {
        position: relative;
        width: 100%;
        height: calc(100vh - 120px);
        min-height: 520px;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
        border: 1px solid rgba(226, 232, 240, 0.8);
    }

    #gisMap {
        width: 100%;
        height: 100%;
        z-index: 1;
        background: #e2e8f0;
    }

    /* Floating Left Filter Panel */
    .gis-filter-panel {
        position: absolute;
        top: 20px;
        left: 20px;
        z-index: 1000;
        width: 330px;
        max-width: calc(100vw - 40px);
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        border-radius: 16px;
        padding: 16px;
        box-shadow: 0 12px 35px rgba(0,0,0,0.18);
        border: 1px solid rgba(255, 255, 255, 0.8);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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

    /* Floating Transline Editor Toolbar */
    .gis-transline-toolbar {
        position: absolute;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1001;
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(12px);
        color: #ffffff;
        border-radius: 30px;
        padding: 8px 20px;
        display: none;
        align-items: center;
        gap: 12px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.4);
        border: 1px solid rgba(16, 185, 129, 0.6);
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
    .asset-ring-proposed {
        box-shadow: 0 0 0 3px #8b5cf6, 0 0 10px rgba(139, 92, 246, 0.6);
        animation: pulse-proposed 2s infinite;
    }

    @keyframes pulse-critical {
        0%, 100% { box-shadow: 0 0 0 2.5px #ef4444, 0 0 0 0 rgba(239, 68, 68, 0.6); }
        50% { box-shadow: 0 0 0 2.5px #ef4444, 0 0 0 8px rgba(239, 68, 68, 0); }
    }
    @keyframes pulse-emergency {
        0%, 100% { box-shadow: 0 0 0 3px #dc2626, 0 0 12px #dc2626; }
        50% { box-shadow: 0 0 0 5px #dc2626, 0 0 18px #dc2626; }
    }
    @keyframes pulse-proposed {
        0%, 100% { box-shadow: 0 0 0 2.5px #8b5cf6, 0 0 0 0 rgba(139, 92, 246, 0.6); }
        50% { box-shadow: 0 0 0 2.5px #8b5cf6, 0 0 0 8px rgba(139, 92, 246, 0); }
    }

    /* Floating Collapsible Legend Panel */
    .gis-legend-container {
        position: absolute;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
    }
    .gis-legend-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        border-radius: 14px;
        padding: 12px 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.8);
        max-height: 380px;
        overflow-y: auto;
        width: 250px;
        display: none;
    }
    .legend-item-row {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        padding: 3px 0;
    }
    .legend-icon-preview {
        width: 22px;
        height: 22px;
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
        min-width: 220px;
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
            <span class="badge bg-primary rounded-pill font-weight-normal px-2 py-1" style="font-size: 11px;">FIELD COLLABORATIVE TWIN</span>
        </div>
        
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" id="btn-add-asset-map" class="btn btn-success btn-sm rounded-pill font-weight-bold shadow-sm">
                <i class="fas fa-plus-circle me-1"></i> Tambah Aset
            </button>
            <button type="button" id="btn-toggle-edit-transline" class="btn btn-outline-info btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-route me-1"></i> Edit Transline
            </button>
            <button type="button" id="btn-view-corrections" class="btn btn-outline-warning btn-sm rounded-pill font-weight-bold position-relative">
                <i class="fas fa-clipboard-check me-1"></i> Usulan Koreksi
                <span id="pending-badge-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none; font-size:9px;">0</span>
            </button>
            <button type="button" id="btn-toggle-legend-top" class="btn btn-outline-secondary btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-layer-group me-1 text-primary"></i> Legenda
            </button>
            <button type="button" id="btn-toggle-panel" class="btn btn-outline-primary btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-sliders me-1"></i> Filter
            </button>
            <button type="button" id="btn-locate-me" class="btn btn-primary btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-crosshairs me-1"></i> GPS Saya
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

        <!-- Floating Transline Geometry Editor Toolbar -->
        <div id="gis-transline-toolbar" class="gis-transline-toolbar">
            <span class="badge bg-success font-monospace px-2 py-1"><i class="fas fa-draw-polygon me-1"></i> MODE EDIT JALUR</span>
            <span id="transline-points-info" class="small text-light">0 Titik Vertex</span>
            <button type="button" id="btn-save-transline" class="btn btn-sm btn-success rounded-pill fw-bold px-3 py-1">
                <i class="fas fa-save me-1"></i> Simpan Usulan Jalur
            </button>
            <button type="button" id="btn-cancel-transline" class="btn btn-sm btn-outline-light rounded-pill px-2 py-1">
                Batal
            </button>
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
                
                <!-- Visual Symbols from Registry -->
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
                        <span class="badge bg-purple text-white" style="background:#8b5cf6; font-size: 9px;"><i class="fas fa-clock me-1"></i> PROPOSED</span>
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

<!-- ========================================================
     MODAL 1: KOREKSI PARAMETER ASET (CONSTRUCTION / LOCATION)
     ======================================================== -->
<div class="modal fade" id="modal-koreksi-asset" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title fw-bold mb-0"><i class="fas fa-edit me-2"></i> Usulan Koreksi Aset Lapangan</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="form-koreksi-asset">
                    <input type="hidden" id="corr-asset-id" name="asset_id">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Kode & Nama Aset</label>
                        <input type="text" id="corr-asset-code" class="form-control form-control-sm bg-light fw-bold" readonly>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Konstruksi Lapangan</label>
                            <select id="corr-construction" class="form-select form-select-sm fw-bold border-primary">
                                <option value="TM-1">TM-1 (Tiang Tumpu)</option>
                                <option value="TM-5">TM-5 (Tiang Sudut)</option>
                                <option value="TM-8">TM-8 (Gardu Tiang Portal)</option>
                                <option value="TM-10">TM-10 (Tiang Akhir)</option>
                                <option value="TM-11">TM-11 (Tiang Percabangan)</option>
                                <option value="LBS">LBS (Load Break Switch)</option>
                                <option value="LBSM">LBSM / PMS Manual</option>
                                <option value="PMCB_REC">PMCB / Recloser</option>
                                <option value="GI">Gardu Induk</option>
                                <option value="GH">Gardu Hubung</option>
                                <option value="DISTRIBUSI">Trafo Distribusi</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Kondisi Fisik</label>
                            <select id="corr-condition" class="form-select form-select-sm">
                                <option value="NORMAL">GOOD (Normal)</option>
                                <option value="FAIR">FAIR (Waspada)</option>
                                <option value="POOR">POOR (Perlu Perbaikan)</option>
                                <option value="CRITICAL">CRITICAL (Kritis)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Latitude</label>
                            <input type="number" step="any" id="corr-lat" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Longitude</label>
                            <input type="number" step="any" id="corr-lng" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Penjelasan / Alasan Koreksi <span class="text-danger">*</span></label>
                        <textarea id="corr-rationale" class="form-control form-control-sm" rows="2" placeholder="Contoh: Konstruksi aktual di lapangan adalah TM-5 karena tiang sudut 30 derajat." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold rounded-pill shadow-sm">
                        <i class="fas fa-paper-plane me-1"></i> Kirim Usulan Koreksi
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL 2: TAMBAH ASET BARU DI LAPANGAN
     ======================================================== -->
<div class="modal fade" id="modal-tambah-asset" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-success text-white py-3">
                <h6 class="modal-title fw-bold mb-0"><i class="fas fa-plus-circle me-2"></i> Tambah Aset Baru Lapangan</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="form-tambah-asset">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Jenis Aset</label>
                            <select id="new-jenis" class="form-select form-select-sm fw-bold">
                                <option value="JTM">JTM / Tiang</option>
                                <option value="GARDU">Gardu Distribusi</option>
                                <option value="TRAFO">Trafo Distribusi</option>
                                <option value="SWITCH">Peralatan Hubung</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Konstruksi</label>
                            <select id="new-construction" class="form-select form-select-sm fw-bold border-success">
                                <option value="TM-1">TM-1 (Tiang Tumpu)</option>
                                <option value="TM-5">TM-5 (Tiang Sudut)</option>
                                <option value="TM-8">TM-8 (Gardu Tiang Portal)</option>
                                <option value="TM-10">TM-10 (Tiang Akhir)</option>
                                <option value="TM-11">TM-11 (Tiang Percabangan)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kode Aset (Auto-Generated)</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="new-code" class="form-control font-monospace bg-light fw-bold" readonly placeholder="Klik Refresh Code">
                            <button class="btn btn-outline-secondary" type="button" id="btn-refresh-code"><i class="fas fa-sync-alt"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama / Identitas Aset</label>
                        <input type="text" id="new-name" class="form-control form-control-sm" placeholder="Contoh: Tiang JTM Banjar Kemantren #159">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Latitude</label>
                            <input type="number" step="any" id="new-lat" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Longitude</label>
                            <input type="number" step="any" id="new-lng" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Alasan Penambahan <span class="text-danger">*</span></label>
                        <textarea id="new-rationale" class="form-control form-control-sm" rows="2" placeholder="Pemasangan tiang baru hasil sisipan penyulang." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100 fw-bold rounded-pill shadow-sm">
                        <i class="fas fa-save me-1"></i> Simpan Usulan Aset Baru
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL 3: LAPORAN ASET HILANG / DECOMMISSIONED
     ======================================================== -->
<div class="modal fade" id="modal-laporkan-missing" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-danger text-white py-3">
                <h6 class="modal-title fw-bold mb-0"><i class="fas fa-trash-alt me-2"></i> Laporkan Aset Sudah Tidak Ada</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="form-laporkan-missing">
                    <input type="hidden" id="missing-asset-id">
                    <div class="alert alert-warning small mb-3">
                        <i class="fas fa-shield-alt me-1"></i> <strong>Enterprise Soft-State:</strong> Aset tidak dihapus permanen dari database agar histori pemeliharaan dan audit trail tetap utuh.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kode Aset</label>
                        <input type="text" id="missing-asset-code" class="form-control form-control-sm bg-light" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Alasan / Bukti Pembongkaran <span class="text-danger">*</span></label>
                        <textarea id="missing-reason" class="form-control form-control-sm" rows="3" placeholder="Contoh: Tiang sudah dicabut karena relokasi pelebaran jalan tol." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 fw-bold rounded-pill shadow-sm">
                        <i class="fas fa-exclamation-triangle me-1"></i> Laporkan Aset Tidak Ada
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL 4: TELAAH USULAN KOREKSI (APPROVAL LAYER)
     ======================================================== -->
<div class="modal fade" id="modal-pending-corrections" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-dark text-white py-3">
                <h6 class="modal-title fw-bold mb-0"><i class="fas fa-clipboard-check text-warning me-2"></i> Antrean Usulan Koreksi Lapangan</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div id="corrections-loading" class="text-center py-4" style="display:none;">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <div id="corrections-list-container" class="d-flex flex-column gap-2" style="max-height: 450px; overflow-y: auto;">
                    <!-- Dynamically populated rows -->
                </div>
            </div>
        </div>
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
    var proposedTranslineLayer = L.featureGroup().addTo(map);
    var translineEditMarkersGroup = L.featureGroup().addTo(map);
    var userLocationMarker = null;

    var currentFeederId = 0;
    var currentData = null;
    var currentLOD = null;
    var currentRequestId = 0;
    var currentUlpRequestId = 0;

    // Transline Editing Mode States
    var isEditingTransline = false;
    var editedVertices = [];

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

        currentFeederId = 0;
        currentData = null;
        currentLOD = null;
        currentRequestId++;
        var thisUlpRequestId = ++currentUlpRequestId;

        toggleLoading(false);
        if (markerCluster && typeof markerCluster.clearLayers === 'function') {
            markerCluster.clearLayers();
        }
        translinePolylineLayer.clearLayers();
        proposedTranslineLayer.clearLayers();
        document.getElementById('gis-summary-bar').style.display = 'none';

        feederSelect.innerHTML = '<option value="">-- Pilih Penyulang --</option>';
        if (!selectedUlpId) return;

        fetch(`<?= site_url('gis/api-penyulangs') ?>?ulp_id=${selectedUlpId}`)
            .then(res => res.json())
            .then(res => {
                if (thisUlpRequestId !== currentUlpRequestId) return;
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
     * PH-VIS-01: Create Reusable Asset Visual Marker with SVG Silhouette, Condition Overlay, and Action Hooks
     */
    function createAssetVisualMarker(feature) {
        var props   = feature.properties || {};
        var geom    = feature.geometry || {};
        var visual  = props.visual || {};
        var overlay = props.condition_overlay || {};

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

        var safePropJson = encodeURIComponent(JSON.stringify(props));

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
                
                <div class="d-flex flex-column gap-1 mt-2">
                    <a href="<?= site_url('master-assets/detail') ?>/${props.id}" class="btn btn-sm btn-primary w-100 fw-bold rounded-pill text-white py-1 shadow-sm" style="font-size: 11px;">
                        <i class="fas fa-cube me-1"></i> Detail Digital Twin &rarr;
                    </a>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary w-50 fw-bold rounded-pill py-1" style="font-size: 10px;" onclick="openCorrectionModal('${safePropJson}')">
                            <i class="fas fa-edit me-1"></i> Koreksi
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger w-50 fw-bold rounded-pill py-1" style="font-size: 10px;" onclick="openMissingModal('${safePropJson}')">
                            <i class="fas fa-trash-alt me-1"></i> Hilang
                        </button>
                    </div>
                </div>
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
        proposedTranslineLayer.clearLayers();

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
                if (thisRequestId !== currentRequestId) return;
                toggleLoading(false);
                if (res.status === 'success' && res.data) {
                    currentData = res.data;
                    renderFilteredLayers(autoFitBounds);

                    var summaryBar = document.getElementById('gis-summary-bar');
                    summaryBar.style.display = 'block';

                    var sum = currentData.summary || {};
                    document.getElementById('summary-text').innerHTML = 
                        `<i class="fas fa-network-wired text-warning me-2"></i> FEEDER READY • Total Aset: <strong>${sum.total_assets || 0}</strong> (JTM: ${sum.jtm_count || 0}, Gardu: ${sum.gardu_count || 0}, Trafo: ${sum.trafo_count || 0}, Switch: ${sum.switch_count || 0})`;
                    
                    fetchPendingBadgeCount();
                }
            })
            .catch(err => {
                if (thisRequestId === currentRequestId) toggleLoading(false);
                console.error(err);
            });
    }

    // Action Triggers
    document.getElementById('btn-apply-gis').addEventListener('click', function () {
        loadGisNetworkOnDemand(true);
    });

    document.querySelectorAll('.layer-toggle').forEach(el => {
        el.addEventListener('change', function () {
            if (currentData) renderFilteredLayers(false);
        });
    });

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

    // Legend Toggle
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

    // ========================================================
    // PH-AI-GIS-01A: FIELD ASSET CORRECTION MODAL HANDLERS
    // ========================================================
    window.openCorrectionModal = function (encodedProps) {
        var props = JSON.parse(decodeURIComponent(encodedProps));
        document.getElementById('corr-asset-id').value = props.id || '';
        document.getElementById('corr-asset-code').value = `${props.kode_asset || ''} - ${props.nama_asset || ''}`;
        document.getElementById('corr-construction').value = props.construction_type || 'TM-1';
        document.getElementById('corr-lat').value = props.latitude || '';
        document.getElementById('corr-lng').value = props.longitude || '';
        document.getElementById('corr-condition').value = props.status || 'NORMAL';
        document.getElementById('corr-rationale').value = '';

        var modal = new bootstrap.Modal(document.getElementById('modal-koreksi-asset'));
        modal.show();
    };

    document.getElementById('form-koreksi-asset').addEventListener('submit', function (e) {
        e.preventDefault();
        var payload = {
            asset_id: document.getElementById('corr-asset-id').value,
            correction_type: 'ASSET_CONSTRUCTION',
            proposed_construction: document.getElementById('corr-construction').value,
            proposed_condition: document.getElementById('corr-condition').value,
            latitude: document.getElementById('corr-lat').value,
            longitude: document.getElementById('corr-lng').value,
            rationale: document.getElementById('corr-rationale').value,
        };

        fetch('<?= site_url('gis/api-propose-correction') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('modal-koreksi-asset')).hide();
                alert(res.message);
                fetchPendingBadgeCount();
            } else {
                alert('Gagal: ' + (res.message || 'Terjadi kesalahan'));
            }
        });
    });

    // ========================================================
    // PH-AI-GIS-01C: ADD NEW ASSET WORKFLOW
    // ========================================================
    document.getElementById('btn-add-asset-map').addEventListener('click', function () {
        var feederId = document.getElementById('feeder-select').value;
        if (!feederId) {
            alert('Silakan pilih penyulang terlebih dahulu!');
            return;
        }

        var center = map.getCenter();
        document.getElementById('new-lat').value = center.lat.toFixed(7);
        document.getElementById('new-lng').value = center.lng.toFixed(7);
        document.getElementById('new-name').value = '';
        document.getElementById('new-rationale').value = '';

        fetchNextAssetCode();

        var modal = new bootstrap.Modal(document.getElementById('modal-tambah-asset'));
        modal.show();
    });

    function fetchNextAssetCode() {
        var feederId = document.getElementById('feeder-select').value;
        var jenis = document.getElementById('new-jenis').value;
        if (!feederId) return;

        fetch(`<?= site_url('gis/api-next-code') ?>?penyulang_id=${feederId}&jenis_asset=${jenis}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    document.getElementById('new-code').value = res.kode_asset;
                }
            });
    }

    document.getElementById('new-jenis').addEventListener('change', fetchNextAssetCode);
    document.getElementById('btn-refresh-code').addEventListener('click', fetchNextAssetCode);

    document.getElementById('form-tambah-asset').addEventListener('submit', function (e) {
        e.preventDefault();
        var feederId = document.getElementById('feeder-select').value;
        var payload = {
            penyulang_id: feederId,
            jenis_asset: document.getElementById('new-jenis').value,
            construction_type: document.getElementById('new-construction').value,
            nama_asset: document.getElementById('new-name').value,
            latitude: document.getElementById('new-lat').value,
            longitude: document.getElementById('new-lng').value,
            rationale: document.getElementById('new-rationale').value,
        };

        fetch('<?= site_url('gis/api-propose-new-asset') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('modal-tambah-asset')).hide();
                alert(res.message);
                fetchPendingBadgeCount();
            } else {
                alert('Gagal: ' + (res.message || 'Terjadi kesalahan'));
            }
        });
    });

    // ========================================================
    // PH-AI-GIS-01C: REPORT MISSING ASSET
    // ========================================================
    window.openMissingModal = function (encodedProps) {
        var props = JSON.parse(decodeURIComponent(encodedProps));
        document.getElementById('missing-asset-id').value = props.id || '';
        document.getElementById('missing-asset-code').value = `${props.kode_asset || ''} - ${props.nama_asset || ''}`;
        document.getElementById('missing-reason').value = '';

        var modal = new bootstrap.Modal(document.getElementById('modal-laporkan-missing'));
        modal.show();
    };

    document.getElementById('form-laporkan-missing').addEventListener('submit', function (e) {
        e.preventDefault();
        var payload = {
            asset_id: document.getElementById('missing-asset-id').value,
            reason: document.getElementById('missing-reason').value,
        };

        fetch('<?= site_url('gis/api-report-missing') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('modal-laporkan-missing')).hide();
                alert(res.message);
                loadGisNetworkOnDemand(false);
            } else {
                alert('Gagal: ' + (res.message || 'Terjadi kesalahan'));
            }
        });
    });

    // ========================================================
    // PH-AI-GIS-01B: TRANSLINE GEOMETRY EDITOR (DUAL LAYER)
    // ========================================================
    document.getElementById('btn-toggle-edit-transline').addEventListener('click', function () {
        if (!currentData || !currentData.transline) {
            alert('Silakan tampilkan jaringan penyulang terlebih dahulu!');
            return;
        }

        isEditingTransline = !isEditingTransline;
        var toolbar = document.getElementById('gis-transline-toolbar');
        toolbar.style.display = isEditingTransline ? 'flex' : 'none';

        if (isEditingTransline) {
            // Extract existing polyline points
            editedVertices = [];
            var geom = currentData.transline.geometry;
            if (geom && geom.coordinates) {
                if (geom.type === 'LineString') {
                    editedVertices = geom.coordinates.map(pt => [pt[1], pt[0]]);
                } else if (geom.type === 'MultiLineString' && geom.coordinates.length > 0) {
                    editedVertices = geom.coordinates[0].map(pt => [pt[1], pt[0]]);
                }
            }
            renderTranslineEditor();
        } else {
            proposedTranslineLayer.clearLayers();
            translineEditMarkersGroup.clearLayers();
        }
    });

    document.getElementById('btn-cancel-transline').addEventListener('click', function () {
        isEditingTransline = false;
        document.getElementById('gis-transline-toolbar').style.display = 'none';
        proposedTranslineLayer.clearLayers();
        translineEditMarkersGroup.clearLayers();
    });

    function renderTranslineEditor() {
        proposedTranslineLayer.clearLayers();
        translineEditMarkersGroup.clearLayers();

        if (editedVertices.length > 1) {
            var proposedPoly = L.polyline(editedVertices, {
                color: '#10b981', // Green Proposed Network Layer
                weight: 5,
                dashArray: '8, 8',
                opacity: 0.95
            });
            proposedTranslineLayer.addLayer(proposedPoly);
        }

        document.getElementById('transline-points-info').textContent = `${editedVertices.length} Titik Vertex`;

        // Render draggable handles
        editedVertices.forEach(function (pt, idx) {
            var handle = L.circleMarker(pt, {
                radius: 7,
                fillColor: '#10b981',
                color: '#ffffff',
                weight: 2,
                fillOpacity: 1
            });
            translineEditMarkersGroup.addLayer(handle);
        });
    }

    // Click on map to add vertex point when in Transline Edit Mode
    map.on('click', function (e) {
        if (isEditingTransline) {
            editedVertices.push([e.latlng.lat, e.latlng.lng]);
            renderTranslineEditor();
        }
    });

    document.getElementById('btn-save-transline').addEventListener('click', function () {
        if (editedVertices.length < 2) {
            alert('Minimal 2 titik diperlukan untuk membentuk jalur transline.');
            return;
        }

        var feederId = document.getElementById('feeder-select').value;
        var geoJsonGeometry = {
            type: 'LineString',
            coordinates: editedVertices.map(pt => [pt[1], pt[0]])
        };

        fetch('<?= site_url('gis/api-propose-transline') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                penyulang_id: feederId,
                geometry: geoJsonGeometry,
                rationale: 'Koreksi sambungan rute transline lapangan'
            })
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                alert(res.message);
                isEditingTransline = false;
                document.getElementById('gis-transline-toolbar').style.display = 'none';
                proposedTranslineLayer.clearLayers();
                translineEditMarkersGroup.clearLayers();
                fetchPendingBadgeCount();
            } else {
                alert('Gagal: ' + (res.message || 'Terjadi kesalahan'));
            }
        });
    });

    // ========================================================
    // PH-AI-GIS-01C: PENDING CORRECTIONS & APPROVAL LAYER
    // ========================================================
    function fetchPendingBadgeCount() {
        var feederId = document.getElementById('feeder-select').value || '';
        fetch(`<?= site_url('gis/api-pending-corrections') ?>?penyulang_id=${feederId}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    var badge = document.getElementById('pending-badge-count');
                    badge.textContent = res.count;
                    badge.style.display = res.count > 0 ? 'inline-block' : 'none';
                }
            });
    }

    document.getElementById('btn-view-corrections').addEventListener('click', function () {
        var feederId = document.getElementById('feeder-select').value || '';
        var container = document.getElementById('corrections-list-container');
        var loading = document.getElementById('corrections-loading');

        container.innerHTML = '';
        loading.style.display = 'block';

        var modal = new bootstrap.Modal(document.getElementById('modal-pending-corrections'));
        modal.show();

        fetch(`<?= site_url('gis/api-pending-corrections') ?>?penyulang_id=${feederId}`)
            .then(res => res.json())
            .then(res => {
                loading.style.display = 'none';
                if (res.status === 'success' && res.data && res.data.length > 0) {
                    res.data.forEach(function (c) {
                        var card = document.createElement('div');
                        card.className = 'card border rounded-3 p-3 mb-2 shadow-sm';
                        
                        var afterData = JSON.parse(c.after_payload || '{}');
                        var typeLabel = c.correction_type.replace('_', ' ');

                        card.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-primary">${typeLabel}</span>
                                <span class="font-monospace text-muted small">${c.correction_code}</span>
                            </div>
                            <h6 class="fw-bold mb-1 text-dark">${c.nama_asset || afterData.nama_asset || 'Usulan Baru'}</h6>
                            <p class="small text-secondary mb-2"><strong>Alasan:</strong> ${c.rationale || '-'}</p>
                            <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-1">
                                <span class="small text-muted"><i class="fas fa-user me-1"></i> ${c.reporter_name} (${c.reporter_role})</span>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-bold" onclick="applyCorrectionAction(${c.id})">
                                        <i class="fas fa-check me-1"></i> Setujui
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger rounded-pill px-2 py-1" onclick="rejectCorrectionAction(${c.id})">
                                        Tolak
                                    </button>
                                </div>
                            </div>
                        `;
                        container.appendChild(card);
                    });
                } else {
                    container.innerHTML = `<div class="text-center text-muted py-4"><i class="fas fa-check-circle text-success fs-3 mb-2 d-block"></i>Tidak ada antrean usulan koreksi pending.</div>`;
                }
            });
    });

    window.applyCorrectionAction = function (corrId) {
        if (!confirm('Setujui dan terapkan usulan ini ke data master jaringan?')) return;
        fetch('<?= site_url('gis/api-apply-correction') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ correction_id: corrId })
        })
        .then(res => res.json())
        .then(res => {
            alert(res.message);
            bootstrap.Modal.getInstance(document.getElementById('modal-pending-corrections')).hide();
            loadGisNetworkOnDemand(false);
        });
    };

    window.rejectCorrectionAction = function (corrId) {
        var reason = prompt('Masukkan alasan penolakan usulan:');
        if (!reason) return;
        fetch('<?= site_url('gis/api-reject-correction') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ correction_id: corrId, rejection_reason: reason })
        })
        .then(res => res.json())
        .then(res => {
            alert(res.message);
            bootstrap.Modal.getInstance(document.getElementById('modal-pending-corrections')).hide();
            loadGisNetworkOnDemand(false);
        });
    };

});
</script>
<?= $this->endSection() ?>
