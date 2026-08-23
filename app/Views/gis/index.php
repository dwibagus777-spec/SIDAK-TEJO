<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Peta Jaringan Distribusi (GIS) - SIDAK TEJO Enterprise<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Peta Jaringan Distribusi (GIS) & Field Network Workspace<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Strict Order Dependency Injection: Leaflet Core CSS followed by Leaflet MarkerCluster CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

<style>
    /* ==========================================================================
       PH-MOB-GIS-UX-01: Two-Stage Workflow Architecture (Setup vs Full Map Workspace)
       ========================================================================== */
    .gis-master-container {
        position: relative;
        width: 100%;
        min-height: calc(100vh - 150px);
    }

    /* STAGE 1: Lightweight Mobile Setup Screen (No Leaflet Map rendered) */
    .gis-setup-screen {
        width: 100%;
        padding: 12px 4px 40px;
        transition: opacity 0.25s ease;
    }

    .feeder-chip-btn {
        font-size: 11px;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 20px;
        transition: all 0.2s ease;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #334155;
        cursor: pointer;
    }
    .feeder-chip-btn.active {
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
        box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
    }

    /* STAGE 2: Fullscreen GIS Map Workspace */
    .gis-workspace-screen {
        position: relative;
        width: 100%;
        height: calc(100vh - 140px);
        min-height: 560px;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: #f8fafc;
    }

    #gisMap {
        width: 100%;
        height: 100%;
        z-index: 1;
        background: #f1f5f9;
    }

    /* Compact Mobile Map Top Navigation Bar */
    .gis-compact-topbar {
        position: absolute;
        top: 14px;
        left: 14px;
        right: 14px;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        pointer-events: none;
    }
    .gis-topbar-pill {
        pointer-events: auto;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        border-radius: 30px;
        padding: 6px 14px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        border: 1px solid rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Loading Spinner Overlay */
    .gis-loading-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 2000;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(4px);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        border-radius: 18px;
    }

    /* Floating Transline Geometry Editor Toolbar */
    .gis-transline-toolbar {
        position: absolute;
        top: 12px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1005;
        background: rgba(15, 23, 42, 0.96);
        backdrop-filter: blur(14px);
        color: #ffffff;
        border-radius: 40px;
        padding: 8px 18px;
        display: none;
        align-items: center;
        gap: 8px;
        box-shadow: 0 10px 35px rgba(0,0,0,0.5);
        border: 2px solid #10b981;
        max-width: 95vw;
        flex-wrap: wrap;
    }

    .gis-editor-guide-banner {
        position: absolute;
        top: 70px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1004;
        background: rgba(16, 185, 129, 0.95);
        color: #ffffff;
        border-radius: 25px;
        padding: 6px 16px;
        font-size: 11px;
        font-weight: 600;
        box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        display: none;
        align-items: center;
        gap: 8px;
        max-width: 90vw;
    }

    /* Floating Summary Pill */
    .gis-summary-bar {
        position: absolute;
        bottom: 14px;
        left: 14px;
        z-index: 1000;
        background: rgba(15, 23, 42, 0.92);
        backdrop-filter: blur(10px);
        color: #ffffff;
        border-radius: 20px;
        padding: 6px 14px;
        font-size: 11px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        display: none;
        max-width: calc(100vw - 120px);
    }

    /* Floating Action Button (FAB) Menu */
    .gis-fab-container {
        position: absolute;
        bottom: 18px;
        right: 18px;
        z-index: 1002;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 10px;
    }
    .gis-fab-main {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #0284c7;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        box-shadow: 0 8px 25px rgba(2, 132, 199, 0.5);
        border: 2px solid #ffffff;
        cursor: pointer;
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .gis-fab-main:hover, .gis-fab-main.active {
        transform: rotate(45deg);
        background: #0369a1;
    }
    .gis-fab-menu {
        display: none;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
    }
    .gis-fab-item {
        background: rgba(255, 255, 255, 0.96);
        color: #1e293b;
        border-radius: 25px;
        padding: 6px 14px;
        font-size: 12px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.2);
        border: 1px solid rgba(226, 232, 240, 0.8);
        cursor: pointer;
        transition: transform 0.15s ease;
    }
    .gis-fab-item:hover {
        transform: scale(1.05);
    }

    /* 1️⃣ Compact Asset Quick Card (Bottom Card <= 30-35% Height) */
    .gis-asset-quick-card {
        position: absolute;
        bottom: 16px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1003;
        width: calc(100% - 32px);
        max-width: 440px;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(14px);
        border-radius: 18px;
        padding: 14px;
        box-shadow: 0 12px 35px rgba(15, 23, 42, 0.25);
        border: 1px solid rgba(226, 232, 240, 0.9);
        display: none;
        animation: slideUpQuickCard 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes slideUpQuickCard {
        from { transform: translate(-50%, 30px); opacity: 0; }
        to { transform: translate(-50%, 0); opacity: 1; }
    }

    /* ==========================================================================
       PH-VIS-02: Pure Flat SVG Marker & Condition Halo Architecture (Zero Cards)
       ========================================================================== */
    .custom-gis-div-icon {
        background: transparent !important;
        border: none !important;
    }
    .asset-network-marker-wrap {
        position: relative;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        background: transparent !important;
        border: none !important;
    }
    .asset-condition-halo {
        position: absolute;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        pointer-events: none;
        transition: all 0.2s ease;
        z-index: 1;
    }
    .asset-ring-good {
        border: 2px solid #10b981;
        background: rgba(16, 185, 129, 0.12);
    }
    .asset-ring-fair {
        border: 2px solid #0ea5e9;
        background: rgba(14, 165, 233, 0.12);
    }
    .asset-ring-poor {
        border: 2px solid #f59e0b;
        background: rgba(245, 158, 11, 0.15);
    }
    .asset-ring-critical {
        border: 2.5px solid #ef4444;
        background: rgba(239, 68, 68, 0.2);
        animation: pulse-critical-flat 2s infinite;
    }
    .asset-ring-emergency {
        border: 3px solid #dc2626;
        background: rgba(220, 38, 38, 0.25);
        animation: pulse-emergency-flat 1.4s infinite;
    }
    .asset-ring-inactive {
        border: 2px solid #64748b;
        opacity: 0.6;
    }
    .asset-ring-proposed {
        border: 2.5px dashed #8b5cf6;
        background: rgba(139, 92, 246, 0.15);
        animation: pulse-proposed-flat 2s infinite;
    }

    @keyframes pulse-critical-flat {
        0%, 100% { transform: scale(1); opacity: 0.8; }
        50% { transform: scale(1.25); opacity: 0.3; }
    }
    @keyframes pulse-emergency-flat {
        0%, 100% { transform: scale(1); opacity: 0.9; }
        50% { transform: scale(1.4); opacity: 0.2; }
    }
    @keyframes pulse-proposed-flat {
        0%, 100% { transform: scale(1); opacity: 0.8; }
        50% { transform: scale(1.2); opacity: 0.4; }
    }

    .asset-flat-svg {
        position: relative;
        width: 28px;
        height: 28px;
        display: block;
        object-fit: contain;
        filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.35));
        transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        z-index: 2;
    }
    .asset-network-marker-wrap:hover .asset-flat-svg {
        transform: scale(1.35);
        z-index: 1000 !important;
    }

    /* Floating Legend Panel */
    .gis-legend-card {
        position: absolute;
        bottom: 80px;
        right: 18px;
        z-index: 1001;
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(12px);
        border-radius: 14px;
        padding: 12px 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.8);
        max-height: 360px;
        overflow-y: auto;
        width: 240px;
        display: none;
    }
    .legend-item-row {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 11px;
        padding: 4px 0;
    }
    .legend-icon-preview {
        width: 22px;
        height: 22px;
        object-fit: contain;
        flex-shrink: 0;
        filter: drop-shadow(0 1px 1px rgba(0,0,0,0.25));
    }
</style>

<div class="gis-master-container">

    <!-- ========================================================
         STAGE 1: GIS NETWORK SETUP (Default Mobile View)
         ======================================================== -->
    <div id="gis-setup-screen" class="gis-setup-screen">
        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mx-auto" style="max-width: 580px; background: #ffffff;">
            
            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-network-wired fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">GIS Network Setup</h5>
                        <span class="small text-muted">Pilih Area & Jaringan Inspeksi</span>
                    </div>
                </div>
                <span class="badge bg-success rounded-pill px-3 py-1 font-monospace" style="font-size: 10px;">
                    <i class="fas fa-bolt me-1"></i> READY
                </span>
            </div>

            <!-- Step 1: Jenis Pekerjaan -->
            <div class="mb-3">
                <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                    <i class="fas fa-tasks text-primary me-1"></i> 1. Pekerjaan Lapangan
                </label>
                <select id="setup-job-type" class="form-select form-select-sm fw-bold border-secondary rounded-3 py-2">
                    <option value="INSPEKSI_VISUAL" selected>Inspeksi Visual JTM (Rutin & Validasi)</option>
                    <option value="INSPEKSI_TERMO">Inspeksi Termovisi (Kamera Panas)</option>
                    <option value="INSPEKSI_ROW">Inspeksi ROW / Tebang Pohon</option>
                    <option value="SURVEI_TWIN">Survei Digital Twin & Validasi Topologi</option>
                </select>
            </div>

            <!-- Step 2: Pilih ULP -->
            <div class="mb-3">
                <label class="form-label small fw-bold text-secondary text-uppercase mb-1">
                    <i class="fas fa-building text-primary me-1"></i> 2. Unit Layanan Pelanggan (ULP)
                </label>
                <select id="setup-ulp-select" class="form-select form-select-sm fw-bold border-secondary rounded-3 py-2">
                    <option value="">-- Pilih ULP --</option>
                    <?php if (!empty($ulps)): ?>
                        <?php foreach ($ulps as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($selectedPenyulangId === 0 && $u['id'] == 1) ? 'selected' : '' ?>>
                                <?= esc($u['nama_ulp']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Step 3: Pilih Penyulang -->
            <div class="mb-3">
                <label class="form-label small fw-bold text-secondary text-uppercase mb-1 d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-bolt text-warning me-1"></i> 3. Penyulang / Feeder</span>
                    <span id="setup-feeder-loading" class="spinner-border spinner-border-sm text-primary" style="display:none;"></span>
                </label>
                <select id="setup-feeder-select" class="form-select form-select-sm fw-bold border-primary text-primary rounded-3 py-2">
                    <option value="">-- Pilih ULP Terlebih Dahulu --</option>
                </select>
                
                <!-- Quick Selection Chips Container -->
                <div id="feeder-quick-chips" class="d-flex flex-wrap gap-1 mt-2">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- Step 4: Layer Aset yang Dimuat -->
            <div class="mb-4">
                <label class="form-label small fw-bold text-secondary text-uppercase mb-2">
                    <i class="fas fa-layer-group text-primary me-1"></i> 4. Layer Aset yang Dimuat
                </label>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="form-check p-2 bg-light rounded-3 border d-flex align-items-center">
                            <input class="form-check-input setup-layer-toggle ms-1 mt-0" type="checkbox" id="setup-layer-jtm" value="JTM" checked>
                            <label class="form-check-label small fw-bold text-dark ms-2 mb-0" for="setup-layer-jtm">
                                Tiang JTM (TM)
                            </label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check p-2 bg-light rounded-3 border d-flex align-items-center">
                            <input class="form-check-input setup-layer-toggle ms-1 mt-0" type="checkbox" id="setup-layer-gardu" value="GARDU" checked>
                            <label class="form-check-label small fw-bold text-dark ms-2 mb-0" for="setup-layer-gardu">
                                Gardu Distribusi
                            </label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check p-2 bg-light rounded-3 border d-flex align-items-center">
                            <input class="form-check-input setup-layer-toggle ms-1 mt-0" type="checkbox" id="setup-layer-trafo" value="TRAFO" checked>
                            <label class="form-check-label small fw-bold text-dark ms-2 mb-0" for="setup-layer-trafo">
                                Trafo Distribusi
                            </label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check p-2 bg-light rounded-3 border d-flex align-items-center">
                            <input class="form-check-input setup-layer-toggle ms-1 mt-0" type="checkbox" id="setup-layer-switch" value="SWITCH" checked>
                            <label class="form-check-label small fw-bold text-dark ms-2 mb-0" for="setup-layer-switch">
                                Peralatan (LBS/REC)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Primary Action CTA -->
            <button type="button" id="btn-setup-open-map" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-lg d-flex align-items-center justify-content-center gap-2" style="font-size: 15px;">
                <i class="fas fa-map-marked-alt fs-5"></i> BUKA PETA JARINGAN
            </button>
        </div>
    </div>

    <!-- ========================================================
         STAGE 2: GIS MAP WORKSPACE (Fullscreen Focused View)
         ======================================================== -->
    <div id="gis-workspace-screen" class="gis-workspace-screen" style="display: none;">

        <!-- Compact Top Navigation Bar -->
        <div class="gis-compact-topbar">
            <div class="gis-topbar-pill">
                <button type="button" id="btn-back-to-setup" class="btn btn-sm btn-light rounded-circle p-1" style="width: 28px; height: 28px;" title="Kembali ke Setup">
                    <i class="fas fa-arrow-left text-dark" style="font-size: 11px;"></i>
                </button>
                <div style="line-height: 1.1;">
                    <strong id="topbar-feeder-title" class="text-primary font-monospace d-block" style="font-size: 12px; color: #0284c7 !important;">-</strong>
                    <span id="topbar-ulp-subtitle" class="text-muted" style="font-size: 10px;">ULP PLN</span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-1">
                <button type="button" id="btn-open-filter-drawer" class="btn btn-light btn-sm rounded-pill font-weight-bold shadow-sm pointer-events-auto" style="font-size: 11px;">
                    <i class="fas fa-sliders text-primary me-1"></i> Filter
                </button>
                <button type="button" id="btn-view-corrections" class="btn btn-dark btn-sm rounded-pill font-weight-bold position-relative pointer-events-auto" style="font-size: 11px; background: rgba(15, 23, 42, 0.9);">
                    <i class="fas fa-clipboard-check text-warning me-1"></i> Usulan
                    <span id="pending-badge-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none; font-size:9px;">0</span>
                </button>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="gis-loading-overlay" class="gis-loading-overlay" style="display: none;">
            <div class="spinner-border text-warning mb-2" role="status" style="width: 2.5rem; height: 2.5rem;"></div>
            <span class="fw-bold font-monospace" style="font-size: 13px;">Memuat Data Jaringan GIS...</span>
        </div>

        <!-- Floating Transline Geometry Editor Toolbar -->
        <div id="gis-transline-toolbar" class="gis-transline-toolbar">
            <span class="badge bg-success font-monospace px-2 py-1"><i class="fas fa-draw-polygon me-1"></i> EDIT JALUR</span>
            <span id="transline-points-info" class="small text-light font-monospace" style="font-size: 11px;">0 Titik</span>
            <button type="button" id="btn-undo-transline" class="btn btn-sm btn-outline-light rounded-pill px-2 py-1" title="Undo">
                <i class="fas fa-undo" style="font-size: 11px;"></i>
            </button>
            <button type="button" id="btn-open-save-transline-modal" class="btn btn-sm btn-success rounded-pill fw-bold px-3 py-1 shadow" style="font-size: 11px;">
                <i class="fas fa-save me-1"></i> Simpan
            </button>
            <button type="button" id="btn-cancel-transline" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-1" style="font-size: 11px;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Guidance Banner for Transline Editing -->
        <div id="gis-editor-guide-banner" class="gis-editor-guide-banner">
            <i class="fas fa-info-circle text-warning"></i>
            <span>Tarik titik bulat hijau untuk menggeser jalur, klik di garis untuk tambah titik.</span>
        </div>

        <!-- Floating Bottom Summary Bar -->
        <div id="gis-summary-bar" class="gis-summary-bar">
            <span id="summary-text" class="fw-bold font-monospace">Memuat Data Jaringan...</span>
        </div>

        <!-- Floating Action Menu (FAB) -->
        <div class="gis-fab-container">
            <div id="gis-fab-menu" class="gis-fab-menu">
                <div class="gis-fab-item" id="fab-add-asset">
                    <i class="fas fa-plus-circle text-success fs-6"></i>
                    <span>Tambah Aset</span>
                </div>
                <div class="gis-fab-item" id="fab-edit-transline">
                    <i class="fas fa-route text-info fs-6"></i>
                    <span>Edit Transline</span>
                </div>
                <div class="gis-fab-item" id="fab-open-filter">
                    <i class="fas fa-filter text-primary fs-6"></i>
                    <span>Ubah Filter</span>
                </div>
                <div class="gis-fab-item" id="fab-locate-me">
                    <i class="fas fa-crosshairs text-danger fs-6"></i>
                    <span>GPS Saya</span>
                </div>
                <div class="gis-fab-item" id="fab-toggle-legend">
                    <i class="fas fa-layer-group text-warning fs-6"></i>
                    <span>Legenda</span>
                </div>
            </div>
            
            <button type="button" id="btn-fab-toggle" class="gis-fab-main" title="Menu Aksi GIS">
                <i class="fas fa-plus"></i>
            </button>
        </div>

        <!-- Floating Legend Card -->
        <div id="gis-legend-panel" class="gis-legend-card">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                <span class="fw-bold text-dark" style="font-size: 11px;">
                    <i class="fas fa-shapes text-warning me-1"></i> LEGENDA ASET
                </span>
                <button type="button" id="btn-close-legend" class="btn-close btn-close-sm" style="font-size: 9px;"></button>
            </div>
            <div class="d-flex flex-column gap-1 mb-2">
                <?php if (!empty($legendItems)): ?>
                    <?php foreach ($legendItems as $item): ?>
                        <?php if ($item['symbol_key'] === 'DEFAULT') continue; ?>
                        <div class="legend-item-row">
                            <img src="<?= base_url($item['svg_path']) ?>" alt="<?= esc($item['label']) ?>" class="legend-icon-preview">
                            <div class="d-flex flex-column" style="line-height: 1.1;">
                                <strong class="text-dark" style="font-size: 10px;"><?= esc($item['symbol_key']) ?></strong>
                                <span class="text-muted" style="font-size: 9px;"><?= esc($item['label']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 1️⃣ Compact Asset Quick Card (Tap Marker) -->
        <div id="asset-quick-card" class="gis-asset-quick-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="d-flex align-items-center gap-2">
                    <img id="quick-card-img" src="" alt="Icon" style="width: 32px; height: 32px; object-fit: contain;">
                    <div>
                        <strong id="quick-card-code" class="text-primary font-monospace d-block" style="font-size: 12px; color: #0284c7 !important;">-</strong>
                        <h6 id="quick-card-name" class="fw-bold mb-0 text-dark" style="font-size: 13px;">-</h6>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-sm" style="font-size: 10px;" onclick="closeAssetQuickCard()"></button>
            </div>
            <div class="d-flex align-items-center gap-1 mb-3">
                <span id="quick-card-badge" class="badge bg-success" style="font-size: 10px;">GOOD</span>
                <span id="quick-card-type" class="badge bg-light text-dark border font-monospace" style="font-size: 10px;">TM-1</span>
                <span id="quick-card-loc" class="small text-muted text-truncate" style="font-size: 10px; max-width: 180px;">-</span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" id="btn-quick-detail" class="btn btn-sm btn-primary w-50 fw-bold rounded-pill shadow-sm" style="font-size: 11px;">
                    <i class="fas fa-eye me-1"></i> Lihat Detail
                </button>
                <button type="button" id="btn-quick-edit" class="btn btn-sm btn-outline-primary w-50 fw-bold rounded-pill" style="font-size: 11px;">
                    <i class="fas fa-edit me-1"></i> Edit Aset
                </button>
            </div>
        </div>

        <!-- Leaflet Map Container -->
        <div id="gisMap"></div>

    </div>

</div>

<!-- ========================================================
     DRAWER: UBAH FILTER (Offcanvas Sheet)
     ======================================================== -->
<div class="offcanvas offcanvas-bottom rounded-top-4" tabindex="-1" id="offcanvas-filter-sheet" style="height: auto; max-height: 80vh;">
    <div class="offcanvas-header border-bottom py-3">
        <h6 class="offcanvas-title fw-bold text-dark mb-0"><i class="fas fa-filter text-primary me-2"></i> Ubah Filter Jaringan</h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-4">
        <div class="mb-3">
            <label class="small text-muted font-weight-bold d-block mb-1">Pilih Penyulang:</label>
            <select id="drawer-feeder-select" class="form-select form-select-sm fw-bold border-primary text-primary">
                <!-- Dynamically cloned -->
            </select>
        </div>
        <div class="mb-3">
            <label class="small text-muted font-weight-bold d-block mb-1">Layer Aset:</label>
            <div class="row g-2">
                <div class="col-6">
                    <div class="form-check p-2 bg-light rounded-3 border">
                        <input class="form-check-input drawer-layer-toggle ms-1" type="checkbox" id="drawer-layer-jtm" value="JTM" checked>
                        <label class="form-check-label small fw-bold text-dark ms-2" for="drawer-layer-jtm">Tiang JTM</label>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-check p-2 bg-light rounded-3 border">
                        <input class="form-check-input drawer-layer-toggle ms-1" type="checkbox" id="drawer-layer-gardu" value="GARDU" checked>
                        <label class="form-check-label small fw-bold text-dark ms-2" for="drawer-layer-gardu">Gardu</label>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-check p-2 bg-light rounded-3 border">
                        <input class="form-check-input drawer-layer-toggle ms-1" type="checkbox" id="drawer-layer-trafo" value="TRAFO" checked>
                        <label class="form-check-label small fw-bold text-dark ms-2" for="drawer-layer-trafo">Trafo</label>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-check p-2 bg-light rounded-3 border">
                        <input class="form-check-input drawer-layer-toggle ms-1" type="checkbox" id="drawer-layer-switch" value="SWITCH" checked>
                        <label class="form-check-label small fw-bold text-dark ms-2" for="drawer-layer-switch">Peralatan</label>
                    </div>
                </div>
            </div>
        </div>
        <button type="button" id="btn-apply-drawer-filter" class="btn btn-primary w-100 fw-bold rounded-pill py-2 shadow-sm">
            <i class="fas fa-check-circle me-1"></i> Terapkan Filter & Buka Peta
        </button>
    </div>
</div>

<!-- ========================================================
     2️⃣ FULL ASSET DETAIL BOTTOM SHEET
     ======================================================== -->
<div class="offcanvas offcanvas-bottom rounded-top-4" tabindex="-1" id="offcanvas-asset-detail" style="height: auto; max-height: 85vh;">
    <div class="offcanvas-header border-bottom py-3">
        <div class="d-flex align-items-center gap-2">
            <img id="detail-sheet-img" src="" alt="Icon" style="width: 32px; height: 32px; object-fit: contain;">
            <div>
                <h6 class="offcanvas-title fw-bold text-dark mb-0" id="detail-sheet-title">-</h6>
                <span id="detail-sheet-subtitle" class="small text-muted font-monospace">-</span>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span id="detail-sheet-badge" class="badge bg-success px-3 py-1">● GOOD</span>
            <span id="detail-sheet-construction" class="badge bg-light text-dark border font-monospace px-3 py-1">TM-1</span>
        </div>

        <div class="card bg-light border-0 rounded-3 p-3 mb-3">
            <div class="d-flex justify-content-between mb-1">
                <span class="small text-muted">Lokasi</span>
                <span id="detail-sheet-loc" class="small fw-bold text-dark">-</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="small text-muted">Koordinat</span>
                <span id="detail-sheet-coords" class="small fw-bold font-monospace text-primary">-</span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="small text-muted">Jenis Aset</span>
                <span id="detail-sheet-jenis" class="small fw-bold text-dark">-</span>
            </div>
        </div>

        <div class="d-flex flex-column gap-2">
            <a id="detail-sheet-dt-link" href="#" class="btn btn-primary w-100 fw-bold rounded-pill text-white py-2 shadow-sm">
                <i class="fas fa-cube me-1"></i> Buka Full Digital Twin &rarr;
            </a>
            <div class="d-flex gap-2">
                <button type="button" id="btn-sheet-koreksi" class="btn btn-outline-primary w-50 fw-bold rounded-pill py-2">
                    <i class="fas fa-edit me-1"></i> Koreksi Aset
                </button>
                <button type="button" id="btn-sheet-hilang" class="btn btn-outline-danger w-50 fw-bold rounded-pill py-2">
                    <i class="fas fa-trash-alt me-1"></i> Aset Hilang
                </button>
            </div>
            <button type="button" id="btn-sheet-transline" class="btn btn-outline-info w-100 fw-bold rounded-pill py-2 text-dark" style="background: rgba(6, 182, 212, 0.1); border-color: #06b6d4;">
                <i class="fas fa-route me-1 text-info"></i> 🔀 Koreksi Jalur di Sekitar Aset
            </button>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL 1: KOREKSI PARAMETER ASET FISIK
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
                        <i class="fas fa-paper-plane me-1"></i> Kirim Usulan Koreksi Aset
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
     MODAL 3: LAPORAN ASET HILANG
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

<!-- ========================================================
     MODAL 5: KHUSUS USULAN KOREKSI JALUR TRANSLINE
     ======================================================== -->
<div class="modal fade" id="modal-koreksi-transline" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-info text-white py-3" style="background-color: #0284c7 !important;">
                <h6 class="modal-title fw-bold mb-0 text-white"><i class="fas fa-route me-2"></i> Usulan Koreksi Jalur Transline</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="form-koreksi-transline">
                    <div class="alert alert-info small mb-3 border-0" style="background: rgba(2, 132, 199, 0.1); color: #0369a1;">
                        <i class="fas fa-info-circle me-1"></i> <strong>Dual-Layer Topology:</strong> Usulan jalur Anda akan dibuat sebagai versi usulan baru (garis hijau) tanpa menimpa data master secara langsung sampai disetujui Supervisor.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Penyulang Terpilih</label>
                        <input type="text" id="modal-transline-feeder-name" class="form-control form-control-sm bg-light fw-bold font-monospace" readonly>
                    </div>

                    <div class="card bg-light border-0 rounded-3 p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">Titik Jalur Eksisting (Biru)</span>
                            <span id="modal-transline-orig-points" class="fw-bold font-monospace small">0 Titik</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">Titik Jalur Usulan (Hijau)</span>
                            <span id="modal-transline-prop-points" class="fw-bold text-success font-monospace small">0 Titik</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-top pt-1 mt-1">
                            <span class="small fw-bold text-dark">Perubahan Geometri (Delta)</span>
                            <span id="modal-transline-delta" class="fw-bold text-primary font-monospace small">±0 Titik</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Penjelasan & Alasan Koreksi Jalur <span class="text-danger">*</span></label>
                        <textarea id="modal-transline-rationale" class="form-control form-control-sm" rows="3" placeholder="Contoh: Rute aktual konduktor SUTM mengikuti jalan baru di sisi timur untuk menghindari proyek saluran irigasi." required></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary w-50 rounded-pill" data-bs-dismiss="modal">
                            Kembali Edit
                        </button>
                        <button type="submit" class="btn btn-success w-50 fw-bold rounded-pill shadow-sm">
                            <i class="fas fa-paper-plane me-1"></i> Kirim Usulan Jalur
                        </button>
                    </div>
                </form>
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

    var map = null;
    var markerCluster = null;
    var translinePolylineLayer = null;
    var proposedTranslineLayer = null;
    var translineEditMarkersGroup = null;
    var userLocationMarker = null;

    var currentFeederId = 0;
    var currentFeederName = '';
    var currentUlpName = '';
    var currentData = null;
    var currentLOD = null;
    var currentRequestId = 0;
    var currentUlpRequestId = 0;
    var activeAssetProps = null;

    // Transline Editing Mode States & History Stack
    var isEditingTransline = false;
    var originalVertices = [];
    var editedVertices = [];
    var undoStack = [];

    // ========================================================
    // STAGE 1: SETUP LOGIC (Cascading Options & Quick Chips)
    // ========================================================
    var setupUlpSelect = document.getElementById('setup-ulp-select');
    var setupFeederSelect = document.getElementById('setup-feeder-select');
    var feederQuickChips = document.getElementById('feeder-quick-chips');
    var setupFeederLoading = document.getElementById('setup-feeder-loading');

    function loadPenyulangsForUlp(ulpId, autoSelectFirst) {
        setupFeederSelect.innerHTML = '<option value="">-- Memuat Penyulang... --</option>';
        feederQuickChips.innerHTML = '';
        setupFeederLoading.style.display = 'inline-block';
        var thisUlpRequestId = ++currentUlpRequestId;

        fetch(`<?= site_url('gis/api-penyulangs') ?>?ulp_id=${ulpId}`)
            .then(res => res.json())
            .then(res => {
                if (thisUlpRequestId !== currentUlpRequestId) return;
                setupFeederLoading.style.display = 'none';
                setupFeederSelect.innerHTML = '<option value="">-- Pilih Penyulang --</option>';
                
                var drawerSelect = document.getElementById('drawer-feeder-select');
                drawerSelect.innerHTML = '<option value="">-- Pilih Penyulang --</option>';

                if (res.status === 'success' && res.penyulangs && res.penyulangs.length > 0) {
                    res.penyulangs.forEach((p, idx) => {
                        var opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = `${p.nama_penyulang} (${p.nama_ulp || 'ULP'})`;
                        opt.dataset.feederName = p.nama_penyulang;
                        opt.dataset.ulpName = p.nama_ulp || 'ULP';
                        setupFeederSelect.appendChild(opt);

                        var optDrawer = opt.cloneNode(true);
                        drawerSelect.appendChild(optDrawer);

                        // Render quick chip
                        var chip = document.createElement('button');
                        chip.type = 'button';
                        chip.className = `feeder-chip-btn ${idx === 0 && autoSelectFirst ? 'active' : ''}`;
                        chip.textContent = p.nama_penyulang;
                        chip.onclick = function () {
                            document.querySelectorAll('.feeder-chip-btn').forEach(b => b.classList.remove('active'));
                            chip.classList.add('active');
                            setupFeederSelect.value = p.id;
                        };
                        feederQuickChips.appendChild(chip);
                    });

                    if (autoSelectFirst) {
                        setupFeederSelect.value = res.penyulangs[0].id;
                    }
                }
            });
    }

    setupUlpSelect.addEventListener('change', function () {
        var ulpId = this.value;
        if (!ulpId) {
            setupFeederSelect.innerHTML = '<option value="">-- Pilih ULP Terlebih Dahulu --</option>';
            feederQuickChips.innerHTML = '';
            return;
        }
        loadPenyulangsForUlp(ulpId, true);
    });

    // Auto load first ULP if available
    if (setupUlpSelect.value) {
        loadPenyulangsForUlp(setupUlpSelect.value, true);
    }

    // ========================================================
    // STAGE 2: TRANSITION FROM SETUP TO FULL MAP WORKSPACE
    // ========================================================
    document.getElementById('btn-setup-open-map').addEventListener('click', function () {
        var feederId = setupFeederSelect.value;
        if (!feederId) {
            alert('Silakan pilih Penyulang terlebih dahulu!');
            return;
        }

        var opt = setupFeederSelect.options[setupFeederSelect.selectedIndex];
        currentFeederId = feederId;
        currentFeederName = opt.dataset.feederName || opt.text;
        currentUlpName = opt.dataset.ulpName || 'PLN ULP';

        document.getElementById('topbar-feeder-title').textContent = currentFeederName;
        document.getElementById('topbar-ulp-subtitle').textContent = currentUlpName;

        // Switch Screen Views
        document.getElementById('gis-setup-screen').style.display = 'none';
        document.getElementById('gis-workspace-screen').style.display = 'block';

        // Lazy initialize Leaflet map
        initializeMapWorkspace();
        loadGisNetworkOnDemand(true);
    });

    document.getElementById('btn-back-to-setup').addEventListener('click', function () {
        document.getElementById('gis-workspace-screen').style.display = 'none';
        document.getElementById('gis-setup-screen').style.display = 'block';
    });

    function getSelectedSetupLayers() {
        var layers = [];
        if (document.getElementById('setup-layer-jtm').checked) layers.push('JTM');
        if (document.getElementById('setup-layer-gardu').checked) layers.push('GARDU');
        if (document.getElementById('setup-layer-trafo').checked) layers.push('TRAFO');
        if (document.getElementById('setup-layer-switch').checked) layers.push('SWITCH');
        return layers;
    }

    function toggleLoading(show) {
        document.getElementById('gis-loading-overlay').style.display = show ? 'flex' : 'none';
    }

    function getLODCategory(zoom) {
        if (zoom < 13) return 'overview';
        if (zoom < 17) return 'equipment';
        return 'detail';
    }

    function initializeMapWorkspace() {
        if (map !== null) {
            map.invalidateSize();
            return;
        }

        var defaultLat = -7.4523;
        var defaultLng = 112.7161;

        map = L.map('gisMap', {
            center: [defaultLat, defaultLng],
            zoom: 14,
            zoomControl: false
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; PLN SIDAK TEJO GIS'
        }).addTo(map);

        L.control.zoom({ position: 'topright' }).addTo(map);

        if (typeof L !== 'undefined' && typeof L.markerClusterGroup === 'function') {
            markerCluster = L.markerClusterGroup({
                chunkedLoading: true,
                maxClusterRadius: 35,
                disableClusteringAtZoom: 16
            });
        } else {
            markerCluster = L.featureGroup();
        }
        map.addLayer(markerCluster);

        translinePolylineLayer = L.featureGroup().addTo(map);
        proposedTranslineLayer = L.featureGroup().addTo(map);
        translineEditMarkersGroup = L.featureGroup().addTo(map);

        map.on('zoomend', function () {
            if (currentFeederId > 0 && !isEditingTransline) {
                var newLOD = getLODCategory(map.getZoom());
                if (newLOD !== currentLOD) {
                    loadGisNetworkOnDemand(false);
                }
            }
        });

        map.on('click', function (e) {
            if (isEditingTransline) {
                saveUndoState();
                editedVertices.push([e.latlng.lat, e.latlng.lng]);
                renderTranslineEditor();
            } else {
                closeAssetQuickCard();
            }
        });
    }

    /**
     * PH-VIS-02: Pure Flat SVG Marker with Condition Halo Ring (Zero Card Container)
     */
    function createAssetVisualMarker(feature) {
        var props   = feature.properties || {};
        var geom    = feature.geometry || {};
        var visual  = props.visual || {};
        var overlay = props.condition_overlay || {};

        var svgPath = visual.svg_path ? `<?= base_url() ?>${visual.svg_path}` : '<?= base_url('/assets/icons/network/generic-network-asset.svg') ?>';
        var ringClass = overlay.ring_class || 'asset-ring-good';
        var symbolKey = visual.symbol_key || props.jenis_asset || 'ASET';

        var iconHtml = `
            <div class="asset-network-marker-wrap" title="${props.nama_asset || ''} (${symbolKey})">
                <span class="asset-condition-halo ${ringClass}"></span>
                <img src="${svgPath}" alt="${symbolKey}" class="asset-flat-svg" />
            </div>
        `;

        var customIcon = L.divIcon({
            html: iconHtml,
            className: 'custom-gis-div-icon',
            iconSize: [44, 44],
            iconAnchor: [22, 22],
            popupAnchor: [0, -22]
        });

        var marker = L.marker([geom.coordinates[1], geom.coordinates[0]], { icon: customIcon });

        // 1️⃣ Marker Tap Interaction: Open Compact Quick Card (Not giant Leaflet popup)
        marker.on('click', function (e) {
            L.DomEvent.stopPropagation(e);
            openAssetQuickCard(props, svgPath, geom.coordinates);
        });

        return marker;
    }

    // ========================================================
    // 1️⃣ COMPACT ASSET QUICK CARD & 2️⃣ DETAIL SHEET HANDLERS
    // ========================================================
    function openAssetQuickCard(props, svgPath, coords) {
        activeAssetProps = props;
        activeAssetProps._svgPath = svgPath;
        activeAssetProps._coords = coords;

        document.getElementById('quick-card-img').src = svgPath;
        document.getElementById('quick-card-code').textContent = props.kode_asset || '-';
        document.getElementById('quick-card-name').textContent = props.nama_asset || '-';
        
        var badge = document.getElementById('quick-card-badge');
        badge.textContent = props.status || 'GOOD';
        badge.className = `badge ${(props.condition_overlay && props.condition_overlay.badge_class) || 'bg-success'}`;
        
        document.getElementById('quick-card-type').textContent = props.construction_type || props.type || 'TM';
        document.getElementById('quick-card-loc').textContent = props.lokasi || 'Jaringan SUTM PLN';

        document.getElementById('asset-quick-card').style.display = 'block';
    }

    window.closeAssetQuickCard = function () {
        document.getElementById('asset-quick-card').style.display = 'none';
    };

    document.getElementById('btn-quick-detail').addEventListener('click', function () {
        if (!activeAssetProps) return;
        closeAssetQuickCard();

        document.getElementById('detail-sheet-img').src = activeAssetProps._svgPath;
        document.getElementById('detail-sheet-title').textContent = activeAssetProps.nama_asset || '-';
        document.getElementById('detail-sheet-subtitle').textContent = activeAssetProps.kode_asset || '-';

        var badge = document.getElementById('detail-sheet-badge');
        badge.textContent = `● ${activeAssetProps.status || 'NORMAL'}`;
        badge.className = `badge ${(activeAssetProps.condition_overlay && activeAssetProps.condition_overlay.badge_class) || 'bg-success'} px-3 py-1`;

        document.getElementById('detail-sheet-construction').textContent = activeAssetProps.construction_type || activeAssetProps.type || 'TM';
        document.getElementById('detail-sheet-loc').textContent = activeAssetProps.lokasi || 'Jaringan SUTM PLN';
        document.getElementById('detail-sheet-coords').textContent = `${activeAssetProps.latitude || '-'}, ${activeAssetProps.longitude || '-'}`;
        document.getElementById('detail-sheet-jenis').textContent = activeAssetProps.jenis_asset || 'JTM';
        document.getElementById('detail-sheet-dt-link').href = `<?= site_url('master-assets/detail') ?>/${activeAssetProps.id}`;

        var detailSheet = new bootstrap.Offcanvas(document.getElementById('offcanvas-asset-detail'));
        detailSheet.show();
    });

    document.getElementById('btn-quick-edit').addEventListener('click', function () {
        if (!activeAssetProps) return;
        closeAssetQuickCard();
        openCorrectionModal(encodeURIComponent(JSON.stringify(activeAssetProps)));
    });

    document.getElementById('btn-sheet-koreksi').addEventListener('click', function () {
        if (!activeAssetProps) return;
        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvas-asset-detail')).hide();
        openCorrectionModal(encodeURIComponent(JSON.stringify(activeAssetProps)));
    });

    document.getElementById('btn-sheet-hilang').addEventListener('click', function () {
        if (!activeAssetProps) return;
        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvas-asset-detail')).hide();
        openMissingModal(encodeURIComponent(JSON.stringify(activeAssetProps)));
    });

    document.getElementById('btn-sheet-transline').addEventListener('click', function () {
        if (!activeAssetProps) return;
        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvas-asset-detail')).hide();
        startTranslineEditAroundAsset(activeAssetProps.latitude, activeAssetProps.longitude);
    });

    // Render Markers & Network Lines
    function renderFilteredLayers(autoFitBounds) {
        if (typeof autoFitBounds === 'undefined') autoFitBounds = false;

        if (markerCluster && typeof markerCluster.clearLayers === 'function') {
            markerCluster.clearLayers();
        }
        if (translinePolylineLayer) translinePolylineLayer.clearLayers();
        if (proposedTranslineLayer) proposedTranslineLayer.clearLayers();

        if (!currentData) return;

        // Render Feeder LineString / MultiLineString Segments with high clarity
        if (currentData.transline && currentData.transline.geometry) {
            var geom = currentData.transline.geometry;
            originalVertices = [];

            if (geom.type === 'MultiLineString' && geom.coordinates) {
                geom.coordinates.forEach(function (segment) {
                    if (segment.length > 1) {
                        var poly = L.polyline(segment.map(pt => [pt[1], pt[0]]), {
                            color: '#0284c7',
                            weight: 3.5,
                            opacity: 0.9,
                            lineJoin: 'round'
                        });
                        translinePolylineLayer.addLayer(poly);
                        segment.forEach(pt => originalVertices.push([pt[1], pt[0]]));
                    }
                });
            } else if (geom.type === 'LineString' && geom.coordinates && geom.coordinates.length > 1) {
                var poly = L.polyline(geom.coordinates.map(pt => [pt[1], pt[0]]), {
                    color: '#0284c7',
                    weight: 3.5,
                    opacity: 0.9,
                    lineJoin: 'round'
                });
                translinePolylineLayer.addLayer(poly);
                geom.coordinates.forEach(pt => originalVertices.push([pt[1], pt[0]]));
            }
        }

        // Render Markers
        var features = currentData.features || [];
        var activeLayers = getSelectedSetupLayers();

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

        if (autoFitBounds && currentData.bbox && map) {
            var b = currentData.bbox;
            map.fitBounds([[b.min_lat, b.min_lng], [b.max_lat, b.max_lng]], { padding: [40, 40] });
        }
    }

    // Fetch Network Data On-Demand
    function loadGisNetworkOnDemand(autoFitBounds, callback) {
        if (typeof autoFitBounds === 'undefined') autoFitBounds = true;
        if (!currentFeederId) return;

        var thisRequestId = ++currentRequestId;
        currentLOD = getLODCategory(map ? map.getZoom() : 14);

        var layersParam = getSelectedSetupLayers().join(',');
        toggleLoading(true);

        fetch(`<?= site_url('gis/api-network') ?>?penyulang_id=${currentFeederId}&zoom=${map ? map.getZoom() : 14}&layers=${layersParam}`)
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
                        `<i class="fas fa-network-wired text-warning me-1"></i> Aset: <strong>${sum.total_assets || 0}</strong> (JTM: ${sum.jtm_count || 0}, GTT: ${sum.gardu_count || 0}, Sw: ${sum.switch_count || 0})`;
                    
                    fetchPendingBadgeCount();
                    if (typeof callback === 'function') callback();
                }
            })
            .catch(err => {
                if (thisRequestId === currentRequestId) toggleLoading(false);
                console.error(err);
            });
    }

    // ========================================================
    // FLOATING ACTION BUTTON (FAB) LOGIC
    // ========================================================
    var fabToggle = document.getElementById('btn-fab-toggle');
    var fabMenu = document.getElementById('gis-fab-menu');

    fabToggle.addEventListener('click', function () {
        var isExpanded = fabMenu.style.display === 'flex';
        fabMenu.style.display = isExpanded ? 'none' : 'flex';
        fabToggle.classList.toggle('active', !isExpanded);
    });

    function collapseFab() {
        fabMenu.style.display = 'none';
        fabToggle.classList.remove('active');
    }

    document.getElementById('fab-add-asset').addEventListener('click', function () {
        collapseFab();
        openAddAssetModal();
    });

    document.getElementById('fab-edit-transline').addEventListener('click', function () {
        collapseFab();
        activateTranslineEditor();
    });

    document.getElementById('fab-open-filter').addEventListener('click', function () {
        collapseFab();
        openFilterDrawer();
    });

    document.getElementById('btn-open-filter-drawer').addEventListener('click', openFilterDrawer);

    function openFilterDrawer() {
        document.getElementById('drawer-feeder-select').value = currentFeederId;
        var drawer = new bootstrap.Offcanvas(document.getElementById('offcanvas-filter-sheet'));
        drawer.show();
    }

    document.getElementById('btn-apply-drawer-filter').addEventListener('click', function () {
        var newFeederId = document.getElementById('drawer-feeder-select').value;
        if (newFeederId) {
            currentFeederId = newFeederId;
            var opt = document.getElementById('drawer-feeder-select').options[document.getElementById('drawer-feeder-select').selectedIndex];
            currentFeederName = opt.dataset.feederName || opt.text;
            document.getElementById('topbar-feeder-title').textContent = currentFeederName;
            setupFeederSelect.value = newFeederId;
        }

        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvas-filter-sheet')).hide();
        loadGisNetworkOnDemand(true);
    });

    document.getElementById('fab-locate-me').addEventListener('click', function () {
        collapseFab();
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

    document.getElementById('fab-toggle-legend').addEventListener('click', function () {
        collapseFab();
        var lp = document.getElementById('gis-legend-panel');
        lp.style.display = lp.style.display === 'block' ? 'none' : 'block';
    });
    document.getElementById('btn-close-legend').addEventListener('click', function () {
        document.getElementById('gis-legend-panel').style.display = 'none';
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
    function openAddAssetModal() {
        if (!currentFeederId) {
            alert('Silakan pilih penyulang terlebih dahulu!');
            return;
        }

        var center = map ? map.getCenter() : { lat: -7.4523, lng: 112.7161 };
        document.getElementById('new-lat').value = center.lat.toFixed(7);
        document.getElementById('new-lng').value = center.lng.toFixed(7);
        document.getElementById('new-name').value = '';
        document.getElementById('new-rationale').value = '';

        fetchNextAssetCode();

        var modal = new bootstrap.Modal(document.getElementById('modal-tambah-asset'));
        modal.show();
    }

    function fetchNextAssetCode() {
        var jenis = document.getElementById('new-jenis').value;
        if (!currentFeederId) return;

        fetch(`<?= site_url('gis/api-next-code') ?>?penyulang_id=${currentFeederId}&jenis_asset=${jenis}`)
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
        var payload = {
            penyulang_id: currentFeederId,
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
    // PH-AI-GIS-01B: DEDICATED TRANSLINE GEOMETRY EDITOR
    // ========================================================
    window.startTranslineEditAroundAsset = function (lat, lng) {
        closeAssetQuickCard();
        if (!isEditingTransline) {
            activateTranslineEditor();
        }
        if (map) map.setView([lat, lng], 17);
    };

    function activateTranslineEditor() {
        if (!currentData || !currentData.transline) {
            alert('Data transline penyulang belum dimuat!');
            return;
        }

        isEditingTransline = true;
        undoStack = [];

        var toolbar = document.getElementById('gis-transline-toolbar');
        var banner = document.getElementById('gis-editor-guide-banner');
        toolbar.style.display = 'flex';
        banner.style.display = 'flex';

        editedVertices = [];
        var geom = currentData.transline.geometry;
        if (geom && geom.coordinates) {
            if (geom.type === 'LineString') {
                editedVertices = geom.coordinates.map(pt => [pt[1], pt[0]]);
            } else if (geom.type === 'MultiLineString' && geom.coordinates.length > 0) {
                editedVertices = geom.coordinates[0].map(pt => [pt[1], pt[0]]);
            }
        }

        saveUndoState();
        renderTranslineEditor();
    }

    function saveUndoState() {
        undoStack.push(JSON.parse(JSON.stringify(editedVertices)));
        if (undoStack.length > 20) undoStack.shift();
    }

    document.getElementById('btn-undo-transline').addEventListener('click', function () {
        if (undoStack.length > 1) {
            undoStack.pop();
            editedVertices = JSON.parse(JSON.stringify(undoStack[undoStack.length - 1]));
            renderTranslineEditor();
        } else {
            alert('Tidak ada aksi undo.');
        }
    });

    document.getElementById('btn-cancel-transline').addEventListener('click', function () {
        if (confirm('Batalkan perubahan pada jalur transline?')) {
            isEditingTransline = false;
            document.getElementById('gis-transline-toolbar').style.display = 'none';
            document.getElementById('gis-editor-guide-banner').style.display = 'none';
            proposedTranslineLayer.clearLayers();
            translineEditMarkersGroup.clearLayers();
            renderFilteredLayers(false);
        }
    });

    function renderTranslineEditor() {
        proposedTranslineLayer.clearLayers();
        translineEditMarkersGroup.clearLayers();

        if (editedVertices.length > 1) {
            var proposedPoly = L.polyline(editedVertices, {
                color: '#10b981',
                weight: 5,
                dashArray: '8, 8',
                opacity: 0.95,
                lineJoin: 'round'
            }).addTo(proposedTranslineLayer);

            proposedPoly.on('click', function (e) {
                var clickedPt = [e.latlng.lat, e.latlng.lng];
                var insertIndex = findClosestSegmentIndex(clickedPt, editedVertices);
                saveUndoState();
                editedVertices.splice(insertIndex + 1, 0, clickedPt);
                renderTranslineEditor();
            });
        }

        var delta = editedVertices.length - originalVertices.length;
        var deltaSign = delta >= 0 ? `+${delta}` : `${delta}`;
        document.getElementById('transline-points-info').textContent = `${editedVertices.length} Titik (${deltaSign})`;

        editedVertices.forEach(function (pt, idx) {
            var handle = L.circleMarker(pt, {
                radius: 7,
                fillColor: '#10b981',
                color: '#ffffff',
                weight: 2.5,
                fillOpacity: 1
            });

            var isDragging = false;
            handle.on('mousedown', function () {
                isDragging = true;
                map.dragging.disable();
            });

            map.on('mousemove', function (e) {
                if (isDragging) {
                    handle.setLatLng(e.latlng);
                    editedVertices[idx] = [e.latlng.lat, e.latlng.lng];
                    if (proposedPoly) proposedPoly.setLatLngs(editedVertices);
                }
            });

            map.on('mouseup', function () {
                if (isDragging) {
                    isDragging = false;
                    map.dragging.enable();
                    saveUndoState();
                    renderTranslineEditor();
                }
            });

            handle.on('contextmenu', function (e) {
                L.DomEvent.stopPropagation(e);
                if (editedVertices.length <= 2) {
                    alert('Minimal 2 titik diperlukan untuk jalur.');
                    return;
                }
                if (confirm(`Hapus titik vertex #${idx + 1}?`)) {
                    saveUndoState();
                    editedVertices.splice(idx, 1);
                    renderTranslineEditor();
                }
            });

            translineEditMarkersGroup.addLayer(handle);
        });
    }

    function findClosestSegmentIndex(point, vertices) {
        var minDistance = Infinity;
        var bestIndex = 0;
        for (var i = 0; i < vertices.length - 1; i++) {
            var dist = distanceToSegment(point, vertices[i], vertices[i + 1]);
            if (dist < minDistance) {
                minDistance = dist;
                bestIndex = i;
            }
        }
        return bestIndex;
    }

    function distanceToSegment(p, v, w) {
        var l2 = (v[0] - w[0]) * (v[0] - w[0]) + (v[1] - w[1]) * (v[1] - w[1]);
        if (l2 === 0) return Math.hypot(p[0] - v[0], p[1] - v[1]);
        var t = Math.max(0, Math.min(1, ((p[0] - v[0]) * (w[0] - v[0]) + (p[1] - v[1]) * (w[1] - v[1])) / l2));
        var proj = [v[0] + t * (w[0] - v[0]), v[1] + t * (w[1] - v[1])];
        return Math.hypot(p[0] - proj[0], p[1] - proj[1]);
    }

    document.getElementById('btn-open-save-transline-modal').addEventListener('click', function () {
        if (editedVertices.length < 2) {
            alert('Minimal 2 titik diperlukan untuk membentuk jalur transline.');
            return;
        }

        document.getElementById('modal-transline-feeder-name').value = `${currentFeederName} (${currentUlpName})`;
        document.getElementById('modal-transline-orig-points').textContent = `${originalVertices.length} Titik`;
        document.getElementById('modal-transline-prop-points').textContent = `${editedVertices.length} Titik`;

        var delta = editedVertices.length - originalVertices.length;
        document.getElementById('modal-transline-delta').textContent = delta >= 0 ? `+${delta} Titik` : `${delta} Titik`;
        document.getElementById('modal-transline-rationale').value = '';

        var modal = new bootstrap.Modal(document.getElementById('modal-koreksi-transline'));
        modal.show();
    });

    document.getElementById('form-koreksi-transline').addEventListener('submit', function (e) {
        e.preventDefault();
        var rationale = document.getElementById('modal-transline-rationale').value;

        var geoJsonGeometry = {
            type: 'LineString',
            coordinates: editedVertices.map(pt => [pt[1], pt[0]])
        };

        fetch('<?= site_url('gis/api-propose-transline') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                penyulang_id: currentFeederId,
                geometry: geoJsonGeometry,
                rationale: rationale
            })
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('modal-koreksi-transline')).hide();
                alert(res.message);
                isEditingTransline = false;
                document.getElementById('gis-transline-toolbar').style.display = 'none';
                document.getElementById('gis-editor-guide-banner').style.display = 'none';
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
        if (!currentFeederId) return;
        fetch(`<?= site_url('gis/api-pending-corrections') ?>?penyulang_id=${currentFeederId}`)
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
        var container = document.getElementById('corrections-list-container');
        var loading = document.getElementById('corrections-loading');

        container.innerHTML = '';
        loading.style.display = 'block';

        var modal = new bootstrap.Modal(document.getElementById('modal-pending-corrections'));
        modal.show();

        fetch(`<?= site_url('gis/api-pending-corrections') ?>?penyulang_id=${currentFeederId}`)
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
                            <h6 class="fw-bold mb-1 text-dark">${c.nama_asset || afterData.nama_asset || 'Usulan Jalur Transline'}</h6>
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
