<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Peta Jaringan Distribusi (GIS) - SIDAK TEJO Enterprise<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Peta Jaringan Distribusi (GIS) & Field Network Workspace<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Strict Order Dependency Injection: Leaflet Core CSS followed by Leaflet MarkerCluster CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

<style>
    :root {
        --gis-mob-bottom-nav: 62px;
    }

    /* Container Master */
    .gis-master-container {
        position: relative;
        width: 100%;
        min-height: calc(100vh - 150px);
    }

    /* ==========================================================================
       STAGE 1: Lightweight Mobile Setup Screen (No Leaflet Map rendered)
       ========================================================================== */
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

    /* ==========================================================================
       STAGE 2: Fullscreen GIS Map Workspace
       ========================================================================== */
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
        background: rgba(255, 255, 255, 0.96);
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

    /* Asset-Anchored Transline Editor Mode Banner */
    .gis-mode-banner {
        position: absolute;
        top: 68px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1005;
        background: rgba(15, 23, 42, 0.96);
        backdrop-filter: blur(12px);
        color: #ffffff;
        border-radius: 30px;
        padding: 8px 18px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.4);
        border: 2px solid #10b981;
        display: none;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        max-width: 92vw;
        font-size: 12px;
        font-weight: 600;
        animation: slideDownBanner 0.25s ease;
    }

    @keyframes slideDownBanner {
        from { transform: translateX(-50%) translateY(-15px); opacity: 0; }
        to { transform: translateX(-50%) translateY(0); opacity: 1; }
    }

    /* Segment Geometry Toolbar */
    .gis-segment-toolbar {
        position: absolute;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1005;
        background: rgba(15, 23, 42, 0.96);
        backdrop-filter: blur(12px);
        color: #ffffff;
        border-radius: 40px;
        padding: 8px 18px;
        display: none;
        align-items: center;
        gap: 8px;
        box-shadow: 0 10px 35px rgba(0,0,0,0.5);
        border: 2px solid #10b981;
        max-width: 95vw;
    }

    /* Floating Summary Pill */
    .gis-summary-bar {
        position: absolute;
        bottom: 14px;
        left: 14px;
        z-index: 999;
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
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #0284c7;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
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

    /* ==========================================================================
       Compact Enterprise Asset Quick Card (< 210px Height)
       ========================================================================== */
    .gis-asset-quick-card {
        position: absolute;
        z-index: 1045 !important;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(14px);
        border-radius: 16px;
        padding: 12px 14px;
        box-shadow: 0 12px 35px rgba(15, 23, 42, 0.25);
        border: 1px solid rgba(226, 232, 240, 0.9);
        display: none;
        height: auto !important;
        min-height: 0 !important;
        max-height: 220px !important;
        overflow: hidden;
        animation: slideUpQuickCard 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes slideUpQuickCard {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    @media (max-width: 768px) {
        .gis-asset-quick-card {
            position: fixed !important;
            left: 10px !important;
            right: 10px !important;
            bottom: calc(var(--gis-mob-bottom-nav) + env(safe-area-inset-bottom, 0px) + 8px) !important;
            width: auto !important;
            max-width: none !important;
        }
        .gis-mode-banner {
            top: 60px !important;
            padding: 6px 12px !important;
            font-size: 11px !important;
        }
        .gis-segment-toolbar {
            bottom: calc(var(--gis-mob-bottom-nav) + env(safe-area-inset-bottom, 0px) + 8px) !important;
        }
        .gis-summary-bar {
            bottom: calc(var(--gis-mob-bottom-nav) + env(safe-area-inset-bottom, 0px) + 8px) !important;
        }
        .gis-fab-container {
            bottom: calc(var(--gis-mob-bottom-nav) + env(safe-area-inset-bottom, 0px) + 8px) !important;
        }
    }

    @media (min-width: 769px) {
        .gis-asset-quick-card {
            position: absolute !important;
            bottom: 24px !important;
            right: 24px !important;
            left: auto !important;
            width: 380px !important;
            max-width: 420px !important;
        }
    }

    .quick-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 6px;
    }
    .quick-card-badges {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 10px;
    }
    .quick-card-actions {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* Voice Mic Collision Elimination */
    body.gis-quickcard-active #btn-global-mic,
    body.gis-drawer-active #btn-global-mic {
        opacity: 0 !important;
        pointer-events: none !important;
        visibility: hidden !important;
    }

    /* Offcanvas Sheets */
    .offcanvas-compact-sheet {
        height: auto !important;
        max-height: 75vh !important;
        border-radius: 20px 20px 0 0 !important;
        border-top: 1px solid rgba(226, 232, 240, 0.8) !important;
        box-shadow: 0 -10px 35px rgba(15, 23, 42, 0.25) !important;
        z-index: 1055 !important;
    }

    @media (min-width: 769px) {
        .offcanvas-compact-sheet {
            max-width: 460px !important;
            margin: 0 auto !important;
            left: 50% !important;
            transform: translateX(-50%) translateY(100%) !important;
        }
        .offcanvas-compact-sheet.show {
            transform: translateX(-50%) translateY(0) !important;
        }
    }

    .sheet-drag-handle {
        width: 38px;
        height: 4px;
        background: #cbd5e1;
        border-radius: 3px;
        margin: 0 auto 10px;
    }
    .sheet-action-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border-radius: 12px;
        border: 1px solid #f1f5f9;
        background: #ffffff;
        font-weight: 600;
        font-size: 13px;
        color: #1e293b;
        cursor: pointer;
        transition: background 0.15s ease;
        text-decoration: none !important;
    }
    .sheet-action-item:hover {
        background: #f8fafc;
    }
    .sheet-action-item.destructive {
        color: #dc2626;
        border-color: #fee2e2;
        background: #fef2f2;
    }
    .sheet-action-item.destructive:hover {
        background: #fee2e2;
    }

    .sheet-sticky-footer {
        position: sticky;
        bottom: 0;
        background: #ffffff;
        padding-top: 10px;
        padding-bottom: calc(var(--gis-mob-bottom-nav) + env(safe-area-inset-bottom, 0px) + 10px);
        border-top: 1px solid #f1f5f9;
        margin-top: 12px;
        display: flex;
        gap: 8px;
    }

    /* Flat SVG Marker & Halo Ring System */
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
    .asset-ring-good { border: 2px solid #10b981; background: rgba(16, 185, 129, 0.12); }
    .asset-ring-fair { border: 2px solid #0ea5e9; background: rgba(14, 165, 233, 0.12); }
    .asset-ring-poor { border: 2px solid #f59e0b; background: rgba(245, 158, 11, 0.15); }
    .asset-ring-critical { border: 2.5px solid #ef4444; background: rgba(239, 68, 68, 0.2); animation: pulse-critical-flat 2s infinite; }
    .asset-ring-emergency { border: 3px solid #dc2626; background: rgba(220, 38, 38, 0.25); animation: pulse-emergency-flat 1.4s infinite; }
    .asset-ring-inactive { border: 2px solid #64748b; opacity: 0.6; }
    .asset-ring-proposed { border: 3px dashed #10b981; background: rgba(16, 185, 129, 0.25); animation: pulse-proposed-flat 1.5s infinite; }

    @keyframes pulse-critical-flat {
        0%, 100% { transform: scale(1); opacity: 0.8; }
        50% { transform: scale(1.25); opacity: 0.3; }
    }
    @keyframes pulse-emergency-flat {
        0%, 100% { transform: scale(1); opacity: 0.9; }
        50% { transform: scale(1.4); opacity: 0.2; }
    }
    @keyframes pulse-proposed-flat {
        0%, 100% { transform: scale(1); opacity: 0.9; }
        50% { transform: scale(1.35); opacity: 0.4; }
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

    /* Floating Legend Card */
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
                    <i class="fas fa-bolt me-1"></i> <?= esc($userRole) ?>
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
                    <div class="col-12">
                        <div class="form-check p-2 bg-light rounded-3 border d-flex align-items-center">
                            <input class="form-check-input setup-layer-toggle ms-1 mt-0" type="checkbox" id="setup-layer-temuan" value="TEMUAN">
                            <label class="form-check-label small fw-bold text-danger ms-2 mb-0" for="setup-layer-temuan">
                                <i class="fas fa-triangle-exclamation text-danger me-1"></i> Layer Temuan Inspeksi (Defect / Anomali)
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

        <!-- Honest Empty State Overlay for Unregistered Master Feeder -->
        <div id="gis-empty-feeder-banner" class="gis-empty-feeder-banner" style="display: none; position: absolute; top: 68px; left: 50%; transform: translateX(-50%); z-index: 1040; width: 92%; max-width: 440px;">
            <div class="card border-0 shadow-lg rounded-4 p-3 p-md-4 text-center" style="background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(16px); border: 1px solid rgba(226, 232, 240, 0.9);">
                <div class="mb-2">
                    <div class="d-inline-flex p-2 rounded-circle bg-primary bg-opacity-10 text-primary mb-2">
                        <i class="fas fa-satellite-dish fs-3"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">NETWORK BELUM TERDAFTAR</h6>
                    <span class="small text-muted d-block" style="font-size: 11px;">Belum terdapat Master Asset terdaftar untuk:</span>
                    <span id="empty-feeder-name" class="badge bg-primary fs-6 px-3 py-1 mt-1 font-monospace">-</span>
                </div>

                <div class="bg-light p-2 rounded-3 border mb-3 text-start" style="font-size: 12px;">
                    <div class="d-flex justify-content-between small fw-bold text-secondary mb-1">
                        <span><i class="fas fa-cubes me-1"></i> Master Asset:</span>
                        <span class="text-dark">0 Unit</span>
                    </div>
                    <div class="d-flex justify-content-between small fw-bold text-secondary mb-1">
                        <span><i class="fas fa-route me-1"></i> Transline:</span>
                        <span class="text-dark">0 Segmen</span>
                    </div>
                    <div class="small text-muted mt-2 border-top pt-2" style="font-size: 10px; line-height: 1.3;">
                        <i class="fas fa-shield-halved text-success me-1"></i> <em>Integritas Terjaga: Data temuan inspeksi lapangan tidak disamarkan sebagai Master Asset.</em>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <a href="<?= base_url('master-assets/template') ?>" class="btn btn-primary btn-sm rounded-pill fw-bold flex-grow-1 py-2 shadow-sm" style="font-size: 11px;">
                        <i class="fas fa-file-import me-1"></i> Impor Master Asset PLN
                    </a>
                    <button type="button" id="btn-empty-back-setup" class="btn btn-outline-secondary btn-sm rounded-pill fw-bold px-3 py-2" style="font-size: 11px;">
                        <i class="fas fa-arrow-left me-1"></i> Setup
                    </button>
                </div>
            </div>
        </div>

        <!-- Mode Guidance Banner -->
        <div id="gis-mode-banner" class="gis-mode-banner">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-info-circle text-warning fs-6"></i>
                <span id="gis-mode-banner-text">PILIH TIANG TUJUAN PADA PETA</span>
            </div>
            <button type="button" id="btn-cancel-active-mode" class="btn btn-sm btn-outline-light rounded-pill px-3 py-0" style="font-size: 11px;">
                Batal
            </button>
        </div>

        <!-- Segment Geometry Toolbar (Only for single connected segment) -->
        <div id="gis-segment-toolbar" class="gis-segment-toolbar">
            <span class="badge bg-success font-monospace px-2 py-1"><i class="fas fa-draw-polygon me-1"></i> EDIT BENTUK SEGMEN</span>
            <button type="button" id="btn-undo-segment" class="btn btn-sm btn-outline-light rounded-pill px-2 py-1" title="Undo">
                <i class="fas fa-undo" style="font-size: 11px;"></i>
            </button>
            <button type="button" id="btn-save-segment-geometry" class="btn btn-sm btn-success rounded-pill fw-bold px-3 py-1 shadow" style="font-size: 11px;">
                <i class="fas fa-save me-1"></i> <?= !empty($isAdmin) ? 'Terapkan Bentuk' : 'Simpan Bentuk' ?>
            </button>
            <button type="button" id="btn-cancel-segment" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-1" style="font-size: 11px;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Loading Overlay -->
        <div id="gis-loading-overlay" class="gis-loading-overlay" style="display: none;">
            <div class="spinner-border text-warning mb-2" role="status" style="width: 2.5rem; height: 2.5rem;"></div>
            <span class="fw-bold font-monospace" style="font-size: 13px;">Memuat Data Jaringan GIS...</span>
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
                    <span>Kelola Jalur Aset</span>
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

        <!-- ========================================================
             1️⃣ COMPACT ENTERPRISE ASSET QUICK CARD (< 210px Height)
             ======================================================== -->
        <div id="asset-quick-card" class="gis-asset-quick-card">
            <div class="quick-card-header">
                <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                    <img id="quick-card-img" src="" alt="Icon" class="flex-shrink-0" style="width: 28px; height: 28px; object-fit: contain;">
                    <div style="min-width: 0; line-height: 1.15;">
                        <h6 id="quick-card-name" class="fw-bold mb-0 text-dark text-truncate" style="font-size: 13px;">-</h6>
                        <span id="quick-card-code" class="text-primary font-monospace small d-block text-truncate" style="font-size: 11px; color: #0284c7 !important;">-</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-sm flex-shrink-0 ms-2" style="font-size: 10px;" onclick="closeAssetQuickCard()"></button>
            </div>

            <div class="quick-card-badges">
                <span id="quick-card-badge" class="badge bg-success" style="font-size: 10px; font-weight: 700;">● GOOD</span>
                <span id="quick-card-type" class="badge bg-light text-dark border font-monospace" style="font-size: 10px;">TM-1</span>
                <span id="quick-card-jenis" class="badge bg-light text-secondary border" style="font-size: 10px;">JTM</span>
            </div>

            <div class="quick-card-actions">
                <button type="button" id="btn-quick-detail" class="btn btn-sm btn-primary flex-fill fw-bold rounded-pill shadow-sm py-1" style="font-size: 11px;">
                    <i class="fas fa-eye me-1"></i> Detail
                </button>
                <button type="button" id="btn-quick-edit-sheet" class="btn btn-sm btn-outline-primary flex-fill fw-bold rounded-pill py-1" style="font-size: 11px;">
                    <i class="fas fa-edit me-1"></i> Edit
                </button>
                <button type="button" id="btn-quick-transline-menu" class="btn btn-sm btn-outline-info flex-fill fw-bold rounded-pill py-1" style="font-size: 11px; color: #0284c7; border-color: #0284c7;">
                    <i class="fas fa-route me-1"></i> Jalur
                </button>
            </div>
        </div>

        <!-- Leaflet Map Container -->
        <div id="gisMap"></div>

    </div>

</div>

<!-- ========================================================
     ASSET-ANCHORED TRANSLINE ACTION SHEET (Kelola Jalur Aset)
     ======================================================== -->
<div class="offcanvas offcanvas-bottom offcanvas-compact-sheet" tabindex="-1" id="offcanvas-asset-transline-menu">
    <div class="offcanvas-body p-3">
        <div class="sheet-drag-handle"></div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-route text-info me-2"></i> Kelola Jalur Aset</h6>
                <span id="transline-sheet-subtitle" class="small text-muted font-monospace">-</span>
            </div>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="d-flex flex-column gap-2">
            <div class="sheet-action-item" id="act-change-connection">
                <i class="fas fa-arrows-split-up-and-left text-primary fs-5"></i>
                <div class="flex-grow-1">
                    <span class="d-block fw-bold">🔗 Ubah Sambungan Aset</span>
                    <span class="small text-muted">Pindahkan jalur sambungan tiang ini ke tiang lain</span>
                </div>
                <i class="fas fa-chevron-right text-muted small"></i>
            </div>
            <div class="sheet-action-item" id="act-edit-segment-shape">
                <i class="fas fa-bezier-curve text-success fs-5"></i>
                <div class="flex-grow-1">
                    <span class="d-block fw-bold">✏️ Edit Bentuk Jalur</span>
                    <span class="small text-muted">Koreksi lekukan polyline segmen sekitar tiang</span>
                </div>
                <i class="fas fa-chevron-right text-muted small"></i>
            </div>
            <div class="sheet-action-item" id="act-add-connection">
                <i class="fas fa-plus-circle text-info fs-5"></i>
                <div class="flex-grow-1">
                    <span class="d-block fw-bold">➕ Tambah Sambungan</span>
                    <span class="small text-muted">Hubungkan tiang ini ke cabang/tiang baru</span>
                </div>
                <i class="fas fa-chevron-right text-muted small"></i>
            </div>
            <div class="sheet-action-item" id="act-edit-conductor-spec">
                <i class="fas fa-bolt text-warning fs-5"></i>
                <div class="flex-grow-1">
                    <span class="d-block fw-bold">⚡ Spesifikasi Konduktor</span>
                    <span class="small text-muted">Ubah jenis kabel (AAAC, A3CS, XLPE) & ukuran mm²</span>
                </div>
                <i class="fas fa-chevron-right text-muted small"></i>
            </div>
            <div class="sheet-action-item destructive" id="act-delete-connection">
                <i class="fas fa-trash-alt text-danger fs-5"></i>
                <div class="flex-grow-1">
                    <span class="d-block fw-bold text-danger">🗑 Hapus Sambungan / Jalur</span>
                    <span class="small text-danger opacity-75">Putus sambungan jalur salah dari tiang ini</span>
                </div>
                <i class="fas fa-chevron-right text-danger small"></i>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     CONFIRM CONNECTION SHEET (Ubah / Tambah Sambungan & Konduktor)
     ======================================================== -->
<div class="offcanvas offcanvas-bottom offcanvas-compact-sheet" tabindex="-1" id="offcanvas-confirm-connection-sheet">
    <div class="offcanvas-body p-3">
        <div class="sheet-drag-handle"></div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-check-circle text-success me-2"></i> Konfirmasi Sambungan & Konduktor</h6>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="offcanvas"></button>
        </div>

        <div class="card bg-light border-0 rounded-3 p-3 mb-3">
            <div class="d-flex justify-content-between mb-1">
                <span class="small text-muted">Aset Sumber:</span>
                <span id="conn-source-name" class="small fw-bold text-dark text-truncate" style="max-width: 200px;">-</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="small text-muted">Aset Tujuan:</span>
                <span id="conn-target-name" class="small fw-bold text-primary text-truncate" style="max-width: 200px;">-</span>
            </div>
            <div class="d-flex justify-content-between border-top pt-1 mt-1 mb-2">
                <span class="small text-muted">Estimasi Jarak:</span>
                <span id="conn-distance-meters" class="small fw-bold font-monospace text-success">0 meter</span>
            </div>

            <!-- Conductor Specs Pickers -->
            <div class="row g-2 pt-2 border-top">
                <div class="col-6">
                    <label class="small fw-bold text-secondary mb-1">Jenis Konduktor</label>
                    <select id="conn-conductor-type" class="form-select form-select-sm fw-bold border-primary text-primary">
                        <option value="AAAC" selected>AAAC (All Alloy)</option>
                        <option value="A3CS">A3CS (Shielded)</option>
                        <option value="A3C">A3C</option>
                        <option value="ACSR">ACSR (Steel Core)</option>
                        <option value="MV-TIC">MV-TIC (Twisted)</option>
                        <option value="XLPE">XLPE (Kabel Tanah)</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="small fw-bold text-secondary mb-1">Ukuran Penampang</label>
                    <select id="conn-conductor-size" class="form-select form-select-sm fw-bold border-primary text-primary">
                        <option value="35 mm²">35 mm²</option>
                        <option value="50 mm²">50 mm²</option>
                        <option value="70 mm²">70 mm²</option>
                        <option value="95 mm²">95 mm²</option>
                        <option value="120 mm²">120 mm²</option>
                        <option value="150 mm²" selected>150 mm²</option>
                        <option value="185 mm²">185 mm²</option>
                        <option value="240 mm²">240 mm²</option>
                        <option value="300 mm²">300 mm²</option>
                    </select>
                </div>
            </div>
        </div>

        <?php if (!empty($isAdmin)): ?>
            <div class="alert alert-success small mb-3 border-0 bg-success bg-opacity-10 text-success py-2">
                <i class="fas fa-shield-check me-1"></i> <strong>ADMIN DIRECT COMMIT:</strong> Topologi & spesifikasi kabel langsung aktif di database master tanpa persetujuan SPV.
            </div>
        <?php endif; ?>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary w-50 rounded-pill" data-bs-dismiss="offcanvas">
                Batal
            </button>
            <button type="button" id="btn-submit-connection" class="btn btn-success w-50 fw-bold rounded-pill shadow-sm">
                <i class="fas fa-check me-1"></i> <?= !empty($isAdmin) ? 'Terapkan Langsung' : 'Kirim Usulan' ?>
            </button>
        </div>
    </div>
</div>

<!-- ========================================================
     EDIT CONDUCTOR SPECIFICATION SHEET (Spesifikasi Jalur Existing)
     ======================================================== -->
<div class="offcanvas offcanvas-bottom offcanvas-compact-sheet" tabindex="-1" id="offcanvas-conductor-spec-sheet">
    <div class="offcanvas-body p-3">
        <div class="sheet-drag-handle"></div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-bolt text-warning me-2"></i> Spesifikasi Konduktor Segmen</h6>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="offcanvas"></button>
        </div>

        <div class="card bg-light border-0 rounded-3 p-3 mb-3">
            <div class="mb-2">
                <label class="small fw-bold text-muted d-block mb-1">Pilih Segmen Sambungan:</label>
                <select id="spec-segment-select" class="form-select form-select-sm fw-bold border-secondary">
                    <!-- Populated dynamically -->
                </select>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="small fw-bold text-secondary mb-1">Jenis Konduktor</label>
                    <select id="spec-conductor-type" class="form-select form-select-sm fw-bold border-warning text-dark">
                        <option value="AAAC" selected>AAAC</option>
                        <option value="A3CS">A3CS (Shielded)</option>
                        <option value="A3C">A3C</option>
                        <option value="ACSR">ACSR</option>
                        <option value="MV-TIC">MV-TIC</option>
                        <option value="XLPE">XLPE</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="small fw-bold text-secondary mb-1">Ukuran Penampang</label>
                    <select id="spec-conductor-size" class="form-select form-select-sm fw-bold border-warning text-dark">
                        <option value="35 mm²">35 mm²</option>
                        <option value="50 mm²">50 mm²</option>
                        <option value="70 mm²">70 mm²</option>
                        <option value="95 mm²">95 mm²</option>
                        <option value="120 mm²">120 mm²</option>
                        <option value="150 mm²" selected>150 mm²</option>
                        <option value="185 mm²">185 mm²</option>
                        <option value="240 mm²">240 mm²</option>
                        <option value="300 mm²">300 mm²</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary w-50 rounded-pill" data-bs-dismiss="offcanvas">
                Batal
            </button>
            <button type="button" id="btn-submit-conductor-spec" class="btn btn-warning w-50 fw-bold rounded-pill shadow-sm text-dark">
                <i class="fas fa-save me-1"></i> <?= !empty($isAdmin) ? 'Simpan Langsung' : 'Kirim Usulan' ?>
            </button>
        </div>
    </div>
</div>

<!-- ========================================================
     DELETE CONNECTION SHEET (Hapus Sambungan Salah)
     ======================================================== -->
<div class="offcanvas offcanvas-bottom offcanvas-compact-sheet" tabindex="-1" id="offcanvas-delete-connection-sheet">
    <div class="offcanvas-body p-3">
        <div class="sheet-drag-handle"></div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0 text-danger"><i class="fas fa-trash-alt text-danger me-2"></i> Hapus Sambungan Jalur</h6>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="offcanvas"></button>
        </div>
        <p class="small text-muted mb-3">Pilih sambungan jalur yang ingin diputus dari aset <strong id="delete-conn-source-name">-</strong>:</p>
        
        <div id="delete-connection-list" class="d-flex flex-column gap-2 mb-3">
            <!-- Dynamically populated connected edges -->
        </div>

        <button type="button" class="btn btn-outline-secondary w-100 rounded-pill" data-bs-dismiss="offcanvas">
            Batal
        </button>
    </div>
</div>

<!-- ========================================================
     ACTION SHEET: EDIT ASET PARAMETER
     ======================================================== -->
<div class="offcanvas offcanvas-bottom offcanvas-compact-sheet" tabindex="-1" id="offcanvas-asset-edit-menu">
    <div class="offcanvas-body p-3">
        <div class="sheet-drag-handle"></div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-edit text-primary me-2"></i> Pilih Aksi Edit Aset</h6>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="d-flex flex-column gap-2">
            <div class="sheet-action-item" id="act-edit-params">
                <i class="fas fa-tools text-primary fs-5"></i>
                <div class="flex-grow-1">
                    <span class="d-block fw-bold">Koreksi Konstruksi & Kondisi Fisik</span>
                    <span class="small text-muted">Ubah tipe tiang (TM-1 s.d. TM-11, LBS) & status</span>
                </div>
                <i class="fas fa-chevron-right text-muted small"></i>
            </div>
            <div class="sheet-action-item" id="act-edit-coords">
                <i class="fas fa-map-marker-alt text-danger fs-5"></i>
                <div class="flex-grow-1">
                    <span class="d-block fw-bold">Koreksi Posisi Koordinat GPS</span>
                    <span class="small text-muted">Perbarui titik latitude & longitude tiang</span>
                </div>
                <i class="fas fa-chevron-right text-muted small"></i>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     FULL ASSET DETAIL BOTTOM SHEET (Scrollable with Sticky Footer)
     ======================================================== -->
<div class="offcanvas offcanvas-bottom offcanvas-compact-sheet" tabindex="-1" id="offcanvas-asset-detail">
    <div class="offcanvas-body p-3 pb-0" style="overflow-y: auto;">
        <div class="sheet-drag-handle"></div>
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <div class="d-flex align-items-center gap-2">
                <img id="detail-sheet-img" src="" alt="Icon" style="width: 32px; height: 32px; object-fit: contain;">
                <div>
                    <h6 class="fw-bold text-dark mb-0" id="detail-sheet-title" style="font-size: 14px;">-</h6>
                    <span id="detail-sheet-subtitle" class="small text-primary font-monospace" style="font-size: 11px;">-</span>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="offcanvas"></button>
        </div>

        <div class="d-flex align-items-center gap-2 mb-3">
            <span id="detail-sheet-badge" class="badge bg-success px-3 py-1">● GOOD</span>
            <span id="detail-sheet-construction" class="badge bg-light text-dark border font-monospace px-3 py-1">TM-1</span>
        </div>

        <div class="card bg-light border-0 rounded-3 p-3 mb-2">
            <div class="d-flex justify-content-between mb-1">
                <span class="small text-muted">Lokasi Aset</span>
                <span id="detail-sheet-loc" class="small fw-bold text-dark text-truncate" style="max-width: 220px;">-</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="small text-muted">Koordinat GPS</span>
                <span id="detail-sheet-coords" class="small fw-bold font-monospace text-primary">-</span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="small text-muted">Jenis Aset</span>
                <span id="detail-sheet-jenis" class="small fw-bold text-dark">-</span>
            </div>
        </div>

        <!-- Sticky Action Footer -->
        <div class="sheet-sticky-footer">
            <a id="detail-sheet-dt-link" href="#" class="btn btn-primary flex-fill fw-bold rounded-pill text-white py-2 shadow-sm" style="font-size: 12px;">
                <i class="fas fa-cube me-1"></i> Digital Twin &rarr;
            </a>
            <button type="button" id="btn-sheet-open-edit" class="btn btn-outline-primary flex-fill fw-bold rounded-pill py-2" style="font-size: 12px;">
                <i class="fas fa-edit me-1"></i> Edit Aset
            </button>
        </div>
    </div>
</div>

<!-- ========================================================
     DRAWER: UBAH FILTER (Offcanvas Sheet)
     ======================================================== -->
<div class="offcanvas offcanvas-bottom offcanvas-compact-sheet" tabindex="-1" id="offcanvas-filter-sheet">
    <div class="offcanvas-body p-3">
        <div class="sheet-drag-handle"></div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold text-dark mb-0"><i class="fas fa-filter text-primary me-2"></i> Ubah Filter Jaringan</h6>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="offcanvas"></button>
        </div>
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
                <div class="col-12">
                    <div class="form-check p-2 bg-light rounded-3 border">
                        <input class="form-check-input drawer-layer-toggle ms-1" type="checkbox" id="drawer-layer-temuan" value="TEMUAN">
                        <label class="form-check-label small fw-bold text-danger ms-2" for="drawer-layer-temuan">
                            <i class="fas fa-triangle-exclamation text-danger me-1"></i> Layer Temuan Inspeksi
                        </label>
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
     MODAL: KOREKSI PARAMETER ASET FISIK
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
     MODAL: TAMBAH ASET BARU DI LAPANGAN
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
     MODAL: ANTREAN USULAN KOREKSI (APPROVAL LAYER)
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

    var map = null;
    var markerCluster = null;
    var translinePolylineLayer = null;
    var previewConnectionLayer = null;
    var segmentEditLayer = null;
    var userLocationMarker = null;

    var currentFeederId = 0;
    var currentFeederName = '';
    var currentUlpName = '';
    var currentData = null;
    var currentLOD = null;
    var currentRequestId = 0;
    var currentUlpRequestId = 0;
    var activeAssetProps = null;
    var masterConductorsList = [];

    // ========================================================
    // 🎯 ASSET-ANCHORED TRANSLINE EDITOR STATE MACHINE
    // ========================================================
    var TRANSLINE_STATE = {
        IDLE: 'IDLE',
        SELECT_SOURCE: 'SELECT_SOURCE',
        CHANGE_CONNECTION: 'CHANGE_CONNECTION',
        ADD_CONNECTION: 'ADD_CONNECTION',
        EDIT_SEGMENT_SHAPE: 'EDIT_SEGMENT_SHAPE',
        DELETE_CONNECTION: 'DELETE_CONNECTION'
    };

    var translineEditor = {
        state: TRANSLINE_STATE.IDLE,
        sourceAsset: null,
        targetAsset: null,
        activeSegment: null,
        editedVertices: [],
        undoStack: []
    };

    function setEditorState(newState, bannerText) {
        translineEditor.state = newState;
        var banner = document.getElementById('gis-mode-banner');
        var bannerLabel = document.getElementById('gis-mode-banner-text');

        if (newState === TRANSLINE_STATE.IDLE) {
            banner.style.display = 'none';
            document.getElementById('gis-segment-toolbar').style.display = 'none';
            if (previewConnectionLayer) previewConnectionLayer.clearLayers();
            if (segmentEditLayer) segmentEditLayer.clearLayers();
            renderFilteredLayers(false);
        } else {
            banner.style.display = 'flex';
            if (bannerLabel && bannerText) bannerLabel.textContent = bannerText;
        }
    }

    document.getElementById('btn-cancel-active-mode').addEventListener('click', function () {
        setEditorState(TRANSLINE_STATE.IDLE);
    });

    // ========================================================
    // 🛡️ ZERO-ERROR RUNTIME UTILITIES & API CONTRACT HELPER
    // ========================================================
    function isValidLatLng(lat, lng) {
        var latitude = Number(lat);
        var longitude = Number(lng);
        return (
            Number.isFinite(latitude) &&
            Number.isFinite(longitude) &&
            latitude >= -90 &&
            latitude <= 90 &&
            longitude >= -180 &&
            longitude <= 180 &&
            latitude !== 0 &&
            longitude !== 0
        );
    }

    function normalizeAssetFeature(feature) {
        var props  = feature?.properties || {};
        var coords = feature?.geometry?.coordinates || [];

        var lng = Number(props.longitude ?? props.lng ?? coords[0]);
        var lat = Number(props.latitude ?? props.lat ?? coords[1]);

        return Object.assign({}, feature, {
            geometry: {
                type: 'Point',
                coordinates: [lng, lat]
            },
            properties: Object.assign({}, props, {
                latitude: lat,
                longitude: lng,
                jenis_asset: props.jenis_asset ?? props.asset_type ?? props.type ?? 'JTM',
                construction_type: props.construction_type ?? props.type ?? 'TM-1'
            })
        });
    }

    function calculateHaversineDistance(lat1, lon1, lat2, lon2) {
        var R = 6371000;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLon = (lon2 - lon1) * Math.PI / 180;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return Math.round(R * c);
    }

    async function fetchJson(url, options) {
        if (!options) options = {};
        var defaultHeaders = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '<?= csrf_hash() ?>'
        };
        if (options.body && typeof options.body === 'string') {
            defaultHeaders['Content-Type'] = 'application/json';
        }
        var mergedOptions = Object.assign({}, options, {
            headers: Object.assign({}, defaultHeaders, options.headers || {})
        });

        var response = await fetch(url, mergedOptions);
        var contentType = response.headers.get('content-type') || '';
        var rawBody = await response.text();
        var payload = null;

        try {
            payload = rawBody ? JSON.parse(rawBody) : null;
        } catch (err) {
            console.error('[GIS API NON-JSON RESPONSE]', {
                url: url,
                status: response.status,
                contentType: contentType,
                bodyPreview: rawBody.slice(0, 300)
            });
            throw new Error(`API mengembalikan response non-JSON (Status ${response.status}).`);
        }

        if (!response.ok) {
            console.error('[GIS API ERROR]', {
                url: url,
                status: response.status,
                payload: payload
            });
            throw new Error(payload && payload.message ? payload.message : `Request gagal (${response.status})`);
        }

        return payload;
    }

    // Load Master Conductors Library
    fetchJson('<?= site_url('gis/api-conductors') ?>')
        .then(res => {
            if (res.status === 'success' && res.data) {
                masterConductorsList = res.data;
            }
        })
        .catch(err => console.error('Failed to load master conductors', err));

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

        fetchJson(`<?= site_url('gis/api-penyulangs') ?>?ulp_id=${ulpId}`)
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
            })
            .catch(err => {
                if (thisUlpRequestId === currentUlpRequestId) setupFeederLoading.style.display = 'none';
                console.error(err);
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

        document.getElementById('gis-setup-screen').style.display = 'none';
        document.getElementById('gis-workspace-screen').style.display = 'block';

        initializeMapWorkspace();
        loadGisNetworkOnDemand(true);
    });

    document.getElementById('btn-back-to-setup').addEventListener('click', function () {
        closeAssetQuickCard();
        setEditorState(TRANSLINE_STATE.IDLE);
        document.getElementById('gis-workspace-screen').style.display = 'none';
        document.getElementById('gis-setup-screen').style.display = 'block';
    });

    document.getElementById('btn-empty-back-setup').addEventListener('click', function () {
        document.getElementById('btn-back-to-setup').click();
    });

    function getSelectedSetupLayers() {
        var layers = [];
        if (document.getElementById('setup-layer-jtm') && document.getElementById('setup-layer-jtm').checked) layers.push('JTM');
        if (document.getElementById('setup-layer-gardu') && document.getElementById('setup-layer-gardu').checked) layers.push('GARDU');
        if (document.getElementById('setup-layer-trafo') && document.getElementById('setup-layer-trafo').checked) layers.push('TRAFO');
        if (document.getElementById('setup-layer-switch') && document.getElementById('setup-layer-switch').checked) layers.push('SWITCH');
        if (document.getElementById('setup-layer-temuan') && document.getElementById('setup-layer-temuan').checked) layers.push('TEMUAN');
        return layers;
    }

    function toggleLoading(show) {
        document.getElementById('gis-loading-overlay').style.display = show ? 'flex' : 'none';
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
        previewConnectionLayer = L.featureGroup().addTo(map);
        segmentEditLayer = L.featureGroup().addTo(map);
        findingLayer = L.featureGroup().addTo(map);

        map.on('click', function () {
            if (translineEditor.state === TRANSLINE_STATE.IDLE) {
                closeAssetQuickCard();
            }
        });
    }

    /**
     * Flat 2D SVG Marker Creation with Condition Ring
     */
    function createAssetVisualMarker(feature) {
        var normalized = normalizeAssetFeature(feature);
        var props   = normalized.properties || {};
        var geom    = normalized.geometry || {};
        var visual  = props.visual || {};
        var overlay = props.condition_overlay || {};

        if (!geom.coordinates || !isValidLatLng(geom.coordinates[1], geom.coordinates[0])) {
            return null;
        }

        var lat = geom.coordinates[1];
        var lng = geom.coordinates[0];

        var svgPath = visual.svg_path ? `<?= base_url() ?>${visual.svg_path}` : '<?= base_url('/assets/icons/network/generic-network-asset.svg') ?>';
        var ringClass = overlay.ring_class || 'asset-ring-good';
        var symbolKey = visual.symbol_key || props.jenis_asset || 'ASET';

        var iconHtml = `
            <div class="asset-network-marker-wrap" id="marker-asset-${props.id}" title="${props.nama_asset || ''} (${symbolKey})">
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

        var marker = L.marker([lat, lng], { icon: customIcon });

        marker.on('click', function (e) {
            L.DomEvent.stopPropagation(e);
            handleMarkerTap(props, svgPath, [lng, lat]);
        });

        return marker;
    }

    // ========================================================
    // 🎯 ASSET-ANCHORED INTERACTION DISPATCHER
    // ========================================================
    function handleMarkerTap(props, svgPath, coords) {
        var clickedAsset = Object.assign({}, props, {
            latitude: (props.latitude !== undefined && props.latitude !== null && isValidLatLng(props.latitude, 0)) ? Number(props.latitude) : Number(coords[1]),
            longitude: (props.longitude !== undefined && props.longitude !== null && isValidLatLng(0, props.longitude)) ? Number(props.longitude) : Number(coords[0]),
            _svgPath: svgPath,
            _coords: coords
        });

        // Case A: State is CHANGE_CONNECTION or ADD_CONNECTION -> Target Selected!
        if (translineEditor.state === TRANSLINE_STATE.CHANGE_CONNECTION || translineEditor.state === TRANSLINE_STATE.ADD_CONNECTION) {
            if (translineEditor.sourceAsset && translineEditor.sourceAsset.id === clickedAsset.id) {
                alert('Silakan pilih tiang TUJUAN yang berbeda dengan tiang sumber!');
                return;
            }

            translineEditor.targetAsset = clickedAsset;
            previewNewConnectionLine(translineEditor.sourceAsset, translineEditor.targetAsset);
            return;
        }

        // Case B: State is SELECT_SOURCE -> User picked an asset from global FAB
        if (translineEditor.state === TRANSLINE_STATE.SELECT_SOURCE) {
            setEditorState(TRANSLINE_STATE.IDLE);
            openAssetQuickCard(clickedAsset, svgPath, coords);
            openTranslineActionSheet(clickedAsset);
            return;
        }

        // Default Case: Open Quick Card
        openAssetQuickCard(clickedAsset, svgPath, coords);
    }

    // ========================================================
    // 1️⃣ COMPACT ASSET QUICK CARD LOGIC
    // ========================================================
    function openAssetQuickCard(props, svgPath, coords) {
        activeAssetProps = Object.assign({}, props);
        activeAssetProps._svgPath = svgPath;
        activeAssetProps._coords = coords;
        activeAssetProps.latitude = (props.latitude !== undefined && props.latitude !== null && isValidLatLng(props.latitude, 0)) ? Number(props.latitude) : (coords ? Number(coords[1]) : null);
        activeAssetProps.longitude = (props.longitude !== undefined && props.longitude !== null && isValidLatLng(0, props.longitude)) ? Number(props.longitude) : (coords ? Number(coords[0]) : null);

        document.getElementById('quick-card-img').src = svgPath;
        document.getElementById('quick-card-code').textContent = props.kode_asset || '-';
        document.getElementById('quick-card-name').textContent = props.nama_asset || '-';
        
        var badge = document.getElementById('quick-card-badge');
        badge.textContent = `● ${props.status || 'GOOD'}`;
        badge.className = `badge ${(props.condition_overlay && props.condition_overlay.badge_class) || 'bg-success'}`;
        
        document.getElementById('quick-card-type').textContent = props.construction_type || props.type || 'TM';
        document.getElementById('quick-card-jenis').textContent = props.jenis_asset || 'JTM';

        document.getElementById('asset-quick-card').style.display = 'block';
        document.body.classList.add('gis-quickcard-active');
    }

    window.closeAssetQuickCard = function () {
        document.getElementById('asset-quick-card').style.display = 'none';
        document.body.classList.remove('gis-quickcard-active');
    };

    // Quick Action 1: Open Full Detail Sheet
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

    // Quick Action 2: Open Edit Parameter Sheet
    document.getElementById('btn-quick-edit-sheet').addEventListener('click', function () {
        if (!activeAssetProps) return;
        closeAssetQuickCard();
        var editMenu = new bootstrap.Offcanvas(document.getElementById('offcanvas-asset-edit-menu'));
        editMenu.show();
    });

    // Quick Action 3: Open Asset-Anchored Transline Action Sheet (Jalur)
    document.getElementById('btn-quick-transline-menu').addEventListener('click', function () {
        if (!activeAssetProps) return;
        closeAssetQuickCard();
        openTranslineActionSheet(activeAssetProps);
    });

    function openTranslineActionSheet(assetProps) {
        translineEditor.sourceAsset = assetProps;
        document.getElementById('transline-sheet-subtitle').textContent = `${assetProps.kode_asset || ''} - ${assetProps.nama_asset || ''}`;
        var sheet = new bootstrap.Offcanvas(document.getElementById('offcanvas-asset-transline-menu'));
        sheet.show();
    }

    // ========================================================
    // 🔀 WORKFLOW 1: UBAH KONEKSI ASET
    // ========================================================
    document.getElementById('act-change-connection').addEventListener('click', function () {
        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvas-asset-transline-menu')).hide();
        setEditorState(TRANSLINE_STATE.CHANGE_CONNECTION, `SENTUH TIANG TUJUAN KONEKSI (Sumber: ${translineEditor.sourceAsset.nama_asset})`);
    });

    // ========================================================
    // 🔀 WORKFLOW 2: TAMBAH SAMBUNGAN
    // ========================================================
    document.getElementById('act-add-connection').addEventListener('click', function () {
        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvas-asset-transline-menu')).hide();
        setEditorState(TRANSLINE_STATE.ADD_CONNECTION, `SENTUH TIANG TUJUAN SAMBUNGAN BARU (Sumber: ${translineEditor.sourceAsset.nama_asset})`);
    });

    function previewNewConnectionLine(sourceAsset, targetAsset) {
        if (!previewConnectionLayer) return;
        previewConnectionLayer.clearLayers();

        var lat1 = sourceAsset.latitude;
        var lon1 = sourceAsset.longitude;
        var lat2 = targetAsset.latitude;
        var lon2 = targetAsset.longitude;

        if (!isValidLatLng(lat1, lon1) || !isValidLatLng(lat2, lon2)) {
            alert('Koordinat tiang tidak valid.');
            return;
        }

        var distance = calculateHaversineDistance(lat1, lon1, lat2, lon2);

        // Draw preview line
        var poly = L.polyline([[lat1, lon1], [lat2, lon2]], {
            color: '#10b981',
            weight: 4.5,
            dashArray: '8, 8',
            opacity: 0.95
        }).addTo(previewConnectionLayer);

        map.fitBounds(poly.getBounds(), { padding: [60, 60] });

        // Open Confirmation Sheet with Conductor Attributes
        document.getElementById('conn-source-name').textContent = `${sourceAsset.nama_asset} (${sourceAsset.kode_asset || ''})`;
        document.getElementById('conn-target-name').textContent = `${targetAsset.nama_asset} (${targetAsset.kode_asset || ''})`;
        document.getElementById('conn-distance-meters').textContent = `${distance} meter`;

        var confirmSheet = new bootstrap.Offcanvas(document.getElementById('offcanvas-confirm-connection-sheet'));
        confirmSheet.show();
    }

    document.getElementById('btn-submit-connection').addEventListener('click', function () {
        if (!translineEditor.sourceAsset || !translineEditor.targetAsset) return;

        var mode = (translineEditor.state === TRANSLINE_STATE.CHANGE_CONNECTION) ? 'REPLACE' : 'ADD';
        var cType = document.getElementById('conn-conductor-type').value;
        var cSize = document.getElementById('conn-conductor-size').value;

        var payload = {
            source_asset_id: translineEditor.sourceAsset.id,
            target_asset_id: translineEditor.targetAsset.id,
            connection_mode: mode,
            conductor_type: cType,
            conductor_size: cSize,
            conductor_material: (cType === 'XLPE') ? 'COPPER_XLPE' : ((cType === 'ACSR') ? 'ALUMINUM_STEEL' : 'ALUMINUM_ALLOY'),
            installation_type: (cType === 'XLPE') ? 'UNDERGROUND' : ((cType === 'A3CS') ? 'OVERHEAD_INSULATED' : 'OVERHEAD')
        };

        var submitBtn = document.getElementById('btn-submit-connection');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

        fetchJson('<?= site_url('gis/api-connect-topology') ?>', {
            method: 'POST',
            body: JSON.stringify(payload)
        })
        .then(res => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check me-1"></i> <?= !empty($isAdmin) ? 'Terapkan Langsung' : 'Kirim Usulan' ?>';

            bootstrap.Offcanvas.getInstance(document.getElementById('offcanvas-confirm-connection-sheet')).hide();
            alert(res.message);

            setEditorState(TRANSLINE_STATE.IDLE);
            loadGisNetworkOnDemand(false);
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check me-1"></i> <?= !empty($isAdmin) ? 'Terapkan Langsung' : 'Kirim Usulan' ?>';
            alert('Gagal memperbarui koneksi: ' + err.message);
        });
    });

    // ========================================================
    // ⚡ WORKFLOW 3: SPESIFIKASI KONDUKTOR SEGMEN EXISTING
    // ========================================================
    document.getElementById('act-edit-conductor-spec').addEventListener('click', function () {
        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvas-asset-transline-menu')).hide();
        openConductorSpecSheet(translineEditor.sourceAsset);
    });

    function openConductorSpecSheet(sourceAsset) {
        var select = document.getElementById('spec-segment-select');
        select.innerHTML = '';

        var neighbors = findConnectedNeighbors(sourceAsset);
        if (neighbors.length > 0) {
            neighbors.forEach(n => {
                var opt = document.createElement('option');
                opt.value = n.id;
                opt.textContent = `Ke: ${n.nama} (~${n.distance}m, ${n.conductor_type || 'AAAC'} ${n.conductor_size || '150 mm²'})`;
                opt.dataset.targetId = n.id;
                opt.dataset.cType = n.conductor_type || 'AAAC';
                opt.dataset.cSize = n.conductor_size || '150 mm²';
                select.appendChild(opt);
            });

            document.getElementById('spec-conductor-type').value = neighbors[0].conductor_type || 'AAAC';
            document.getElementById('spec-conductor-size').value = neighbors[0].conductor_size || '150 mm²';
        } else {
            select.innerHTML = '<option value="">Tidak ada tiang terhubung</option>';
        }

        select.onchange = function () {
            var selectedOpt = select.options[select.selectedIndex];
            if (selectedOpt && selectedOpt.dataset.cType) {
                document.getElementById('spec-conductor-type').value = selectedOpt.dataset.cType;
                document.getElementById('spec-conductor-size').value = selectedOpt.dataset.cSize;
            }
        };

        var sheet = new bootstrap.Offcanvas(document.getElementById('offcanvas-conductor-spec-sheet'));
        sheet.show();
    }

    document.getElementById('btn-submit-conductor-spec').addEventListener('click', function () {
        var select = document.getElementById('spec-segment-select');
        var targetId = select.value;
        if (!targetId || !translineEditor.sourceAsset) {
            alert('Pilih segmen sambungan terlebih dahulu.');
            return;
        }

        var cType = document.getElementById('spec-conductor-type').value;
        var cSize = document.getElementById('spec-conductor-size').value;

        fetchJson('<?= site_url('gis/api-update-conductor') ?>', {
            method: 'POST',
            body: JSON.stringify({
                source_asset_id: translineEditor.sourceAsset.id,
                target_asset_id: targetId,
                conductor_type: cType,
                conductor_size: cSize,
                conductor_material: (cType === 'XLPE') ? 'COPPER_XLPE' : ((cType === 'ACSR') ? 'ALUMINUM_STEEL' : 'ALUMINUM_ALLOY')
            })
        })
        .then(res => {
            bootstrap.Offcanvas.getInstance(document.getElementById('offcanvas-conductor-spec-sheet')).hide();
            alert(res.message);
            loadGisNetworkOnDemand(false);
        })
        .catch(err => alert('Gagal: ' + err.message));
    });

    // ========================================================
    // 🗑 WORKFLOW 4: HAPUS JALUR SALAH
    // ========================================================
    document.getElementById('act-delete-connection').addEventListener('click', function () {
        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvas-asset-transline-menu')).hide();
        openDeleteConnectionSheet(translineEditor.sourceAsset);
    });

    function findConnectedNeighbors(sourceAsset) {
        var neighbors = [];
        if (currentData && currentData.features) {
            currentData.features.forEach(f => {
                var norm = normalizeAssetFeature(f);
                var p = norm.properties || {};
                var g = norm.geometry || {};
                if (p.id !== sourceAsset.id && g.coordinates && isValidLatLng(g.coordinates[1], g.coordinates[0])) {
                    var d = calculateHaversineDistance(sourceAsset.latitude, sourceAsset.longitude, g.coordinates[1], g.coordinates[0]);
                    if (d <= 500) {
                        neighbors.push({
                            id: p.id,
                            nama: p.nama_asset,
                            kode: p.kode_asset,
                            distance: d,
                            conductor_type: 'AAAC',
                            conductor_size: '150 mm²'
                        });
                    }
                }
            });
        }
        return neighbors.sort((a, b) => a.distance - b.distance);
    }

    function openDeleteConnectionSheet(sourceAsset) {
        document.getElementById('delete-conn-source-name').textContent = sourceAsset.nama_asset;
        var listContainer = document.getElementById('delete-connection-list');
        listContainer.innerHTML = '';

        var neighbors = findConnectedNeighbors(sourceAsset);

        if (neighbors.length > 0) {
            neighbors.forEach(n => {
                var row = document.createElement('div');
                row.className = 'd-flex justify-content-between align-items-center p-2 rounded-3 border bg-light';
                row.innerHTML = `
                    <div style="min-width: 0; line-height: 1.2;">
                        <span class="d-block fw-bold text-dark text-truncate" style="font-size: 12px;">${n.nama}</span>
                        <span class="small text-muted font-monospace">${n.kode || ''} (~${n.distance}m)</span>
                    </div>
                    <button class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 fw-bold" onclick="executeDisconnectTopology(${sourceAsset.id}, ${n.id}, '${n.nama.replace(/'/g, "\\'")}')">
                        <i class="fas fa-trash-alt me-1"></i> Putus
                    </button>
                `;
                listContainer.appendChild(row);
            });
        } else {
            listContainer.innerHTML = `<div class="text-center text-muted small py-3">Tidak ditemukan sambungan langsung di sekitar tiang ini.</div>`;
        }

        var sheet = new bootstrap.Offcanvas(document.getElementById('offcanvas-delete-connection-sheet'));
        sheet.show();
    }

    window.executeDisconnectTopology = function (sourceId, targetId, targetName) {
        if (!confirm(`Putus sambungan jalur ke tiang ${targetName}?`)) return;

        fetchJson('<?= site_url('gis/api-disconnect-topology') ?>', {
            method: 'POST',
            body: JSON.stringify({ source_asset_id: sourceId, target_asset_id: targetId })
        })
        .then(res => {
            bootstrap.Offcanvas.getInstance(document.getElementById('offcanvas-delete-connection-sheet')).hide();
            alert(res.message);
            loadGisNetworkOnDemand(false);
        })
        .catch(err => {
            alert('Gagal memutus sambungan: ' + err.message);
        });
    };

    // ========================================================
    // ✏ WORKFLOW 5: EDIT BENTUK JALUR SEGMEN (Single Segment ONLY)
    // ========================================================
    document.getElementById('act-edit-segment-shape').addEventListener('click', function () {
        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvas-asset-transline-menu')).hide();
        startEditSingleSegment(translineEditor.sourceAsset);
    });

    function startEditSingleSegment(sourceAsset) {
        if (!segmentEditLayer) return;
        segmentEditLayer.clearLayers();

        var neighbors = findConnectedNeighbors(sourceAsset);
        if (neighbors.length === 0) {
            alert('Tidak ditemukan tiang tetangga terdekat yang terhubung untuk diedit bentuk segmennya.');
            return;
        }

        var nearest = neighbors[0];
        var neighborFeature = currentData.features.find(f => (f.properties && f.properties.id === nearest.id));
        var nCoords = neighborFeature.geometry.coordinates;

        translineEditor.targetAsset = {
            id: nearest.id,
            nama: nearest.nama,
            lat: nCoords[1],
            lng: nCoords[0]
        };

        translineEditor.editedVertices = [
            [sourceAsset.latitude, sourceAsset.longitude],
            [nCoords[1], nCoords[0]]
        ];
        translineEditor.undoStack = [JSON.parse(JSON.stringify(translineEditor.editedVertices))];

        setEditorState(TRANSLINE_STATE.EDIT_SEGMENT_SHAPE, `EDIT BENTUK SEGMEN (${sourceAsset.nama_asset} → ${nearest.nama})`);
        document.getElementById('gis-segment-toolbar').style.display = 'flex';

        renderSingleSegmentEditor();
    }

    function renderSingleSegmentEditor() {
        segmentEditLayer.clearLayers();
        var vertices = translineEditor.editedVertices;

        var poly = L.polyline(vertices, {
            color: '#10b981',
            weight: 5,
            opacity: 0.95
        }).addTo(segmentEditLayer);

        poly.on('click', function (e) {
            if (isValidLatLng(e.latlng.lat, e.latlng.lng)) {
                translineEditor.undoStack.push(JSON.parse(JSON.stringify(vertices)));
                vertices.splice(1, 0, [e.latlng.lat, e.latlng.lng]);
                renderSingleSegmentEditor();
            }
        });

        vertices.forEach((pt, idx) => {
            var handle = L.circleMarker(pt, {
                radius: 9,
                fillColor: '#10b981',
                color: '#ffffff',
                weight: 3,
                fillOpacity: 1
            });

            var isDragging = false;
            handle.on('mousedown touchstart', function () {
                isDragging = true;
                map.dragging.disable();
            });

            map.on('mousemove touchmove', function (e) {
                if (isDragging && isValidLatLng(e.latlng.lat, e.latlng.lng)) {
                    handle.setLatLng(e.latlng);
                    vertices[idx] = [e.latlng.lat, e.latlng.lng];
                    poly.setLatLngs(vertices);
                }
            });

            map.on('mouseup touchend', function () {
                if (isDragging) {
                    isDragging = false;
                    map.dragging.enable();
                    translineEditor.undoStack.push(JSON.parse(JSON.stringify(vertices)));
                }
            });

            if (idx > 0 && idx < vertices.length - 1) {
                handle.on('contextmenu', function (e) {
                    L.DomEvent.stopPropagation(e);
                    if (confirm('Hapus titik lekukan ini?')) {
                        translineEditor.undoStack.push(JSON.parse(JSON.stringify(vertices)));
                        vertices.splice(idx, 1);
                        renderSingleSegmentEditor();
                    }
                });
            }

            segmentEditLayer.addLayer(handle);
        });

        map.fitBounds(poly.getBounds(), { padding: [80, 80] });
    }

    document.getElementById('btn-undo-segment').addEventListener('click', function () {
        if (translineEditor.undoStack.length > 1) {
            translineEditor.undoStack.pop();
            translineEditor.editedVertices = JSON.parse(JSON.stringify(translineEditor.undoStack[translineEditor.undoStack.length - 1]));
            renderSingleSegmentEditor();
        } else {
            alert('Tidak ada riwayat undo.');
        }
    });

    document.getElementById('btn-cancel-segment').addEventListener('click', function () {
        setEditorState(TRANSLINE_STATE.IDLE);
    });

    document.getElementById('btn-save-segment-geometry').addEventListener('click', function () {
        var validVertices = translineEditor.editedVertices.filter(pt => isValidLatLng(pt[0], pt[1]));
        if (validVertices.length < 2) {
            alert('Minimal 2 titik diperlukan.');
            return;
        }

        var geoJsonGeometry = {
            type: 'LineString',
            coordinates: validVertices.map(pt => [pt[1], pt[0]])
        };

        fetchJson('<?= site_url('gis/api-update-segment') ?>', {
            method: 'POST',
            body: JSON.stringify({
                penyulang_id: currentFeederId,
                source_asset_id: translineEditor.sourceAsset.id,
                target_asset_id: translineEditor.targetAsset ? translineEditor.targetAsset.id : 0,
                geometry: geoJsonGeometry
            })
        })
        .then(res => {
            alert(res.message);
            setEditorState(TRANSLINE_STATE.IDLE);
            loadGisNetworkOnDemand(false);
        })
        .catch(err => {
            alert('Gagal menyimpan bentuk segmen: ' + err.message);
        });
    });

    // ========================================================
    // GLOBAL FAB: EDIT TRANSLINE (Activates Source Selection)
    // ========================================================
    document.getElementById('fab-edit-transline').addEventListener('click', function () {
        collapseFab();
        setEditorState(TRANSLINE_STATE.SELECT_SOURCE, 'SENTUH TIANG PADA PETA UNTUK MEMILIH JALUR');
    });

    // Parameter Edit Sub-actions
    document.getElementById('act-edit-params').addEventListener('click', function () {
        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvas-asset-edit-menu')).hide();
        openCorrectionModal(encodeURIComponent(JSON.stringify(activeAssetProps)));
    });

    document.getElementById('act-edit-coords').addEventListener('click', function () {
        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvas-asset-edit-menu')).hide();
        openCorrectionModal(encodeURIComponent(JSON.stringify(activeAssetProps)));
    });

    document.getElementById('btn-sheet-open-edit').addEventListener('click', function () {
        bootstrap.Offcanvas.getInstance(document.getElementById('offcanvas-asset-detail')).hide();
        var editMenu = new bootstrap.Offcanvas(document.getElementById('offcanvas-asset-edit-menu'));
        editMenu.show();
    });

    function createTemuanVisualMarker(norm) {
        var props = norm.properties || {};
        var geom = norm.geometry || {};
        if (!geom.coordinates || !isValidLatLng(geom.coordinates[1], geom.coordinates[0])) {
            return null;
        }

        var lat = geom.coordinates[1];
        var lng = geom.coordinates[0];
        var sevColor = (props.prioritas === 'EMERGENCY') ? '#dc2626' : ((props.prioritas === 'HIGH') ? '#ea580c' : '#eab308');

        var iconHtml = `
            <div class="temuan-marker-wrap" style="position: relative; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; background: ${sevColor}; color: #ffffff; border-radius: 50%; border: 2px solid #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.35); cursor: pointer;" title="TEMUAN: ${props.nomor_temuan || ''} (${props.jenis_temuan || ''})">
                <i class="fas fa-triangle-exclamation" style="font-size: 13px;"></i>
            </div>
        `;

        var customIcon = L.divIcon({
            html: iconHtml,
            className: 'custom-temuan-div-icon',
            iconSize: [30, 30],
            iconAnchor: [15, 15],
            popupAnchor: [0, -15]
        });

        var marker = L.marker([lat, lng], { icon: customIcon });
        marker.bindPopup(`
            <div class="p-2" style="max-width: 260px; font-family: system-ui, sans-serif;">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="badge bg-danger font-monospace" style="font-size: 10px;">TEMUAN INSPEKSI</span>
                    <span class="badge bg-dark font-monospace" style="font-size: 10px;">${props.prioritas || 'NORMAL'}</span>
                </div>
                <strong class="d-block text-dark font-monospace small mb-1">${props.nomor_temuan || '-'}</strong>
                <p class="small text-secondary mb-1" style="font-size: 11px;">${props.detail_temuan || props.jenis_temuan || '-'}</p>
                <div class="d-flex justify-content-between small text-muted border-top pt-1" style="font-size: 10px;">
                    <span>Status:</span>
                    <strong class="${props.status === 'SELESAI' ? 'text-success' : 'text-danger'}">${props.status || 'BELUM'}</strong>
                </div>
            </div>
        `);

        return marker;
    }

    // Render Markers & Network Lines with Conductor Popup Tooltips
    function renderFilteredLayers(autoFitBounds) {
        if (typeof autoFitBounds === 'undefined') autoFitBounds = false;

        if (markerCluster && typeof markerCluster.clearLayers === 'function') {
            markerCluster.clearLayers();
        }
        if (findingLayer && typeof findingLayer.clearLayers === 'function') {
            findingLayer.clearLayers();
        }
        if (translinePolylineLayer) translinePolylineLayer.clearLayers();

        if (!currentData) return;

        // Render Feeder LineString / MultiLineString Segments with high clarity
        var hasTopology = false;
        if (currentData.transline && currentData.transline.geometry) {
            var geom = currentData.transline.geometry;
            var edges = (currentData.transline.properties && currentData.transline.properties.edges) || [];

            if (edges.length > 0) {
                hasTopology = true;
                edges.forEach(function (e) {
                    var c = e.coordinates;
                    if (c && c.length === 2 && isValidLatLng(c[0][1], c[0][0]) && isValidLatLng(c[1][1], c[1][0])) {
                        var poly = L.polyline([[c[0][1], c[0][0]], [c[1][1], c[1][0]]], {
                            color: '#0284c7',
                            weight: 3.5,
                            opacity: 0.9,
                            lineJoin: 'round'
                        });
                        poly.bindTooltip(`⚡ <strong>${e.conductor_label || 'AAAC 150 mm²'}</strong> (${e.length_meter}m)`, {
                            sticky: true,
                            className: 'font-monospace small'
                        });
                        translinePolylineLayer.addLayer(poly);
                    }
                });
            } else if (geom.type === 'MultiLineString' && geom.coordinates && geom.coordinates.length > 0) {
                geom.coordinates.forEach(function (segment) {
                    var validSeg = segment.filter(pt => isValidLatLng(pt[1], pt[0]));
                    if (validSeg.length > 1) {
                        hasTopology = true;
                        var poly = L.polyline(validSeg.map(pt => [pt[1], pt[0]]), {
                            color: '#0284c7',
                            weight: 3.5,
                            opacity: 0.9,
                            lineJoin: 'round'
                        });
                        translinePolylineLayer.addLayer(poly);
                    }
                });
            } else if (geom.type === 'LineString' && geom.coordinates && geom.coordinates.length > 0) {
                var validSeg = geom.coordinates.filter(pt => isValidLatLng(pt[1], pt[0]));
                if (validSeg.length > 1) {
                    hasTopology = true;
                    var poly = L.polyline(validSeg.map(pt => [pt[1], pt[0]]), {
                        color: '#0284c7',
                        weight: 3.5,
                        opacity: 0.9,
                        lineJoin: 'round'
                    });
                    translinePolylineLayer.addLayer(poly);
                }
            }
        }

        // Render Markers strictly separated by entity_type
        var rawFeatures = currentData.features || [];
        var activeLayers = getSelectedSetupLayers();
        var renderedAssetCount = 0;
        var renderedTemuanCount = 0;
        var renderedJtm = 0;
        var renderedGardu = 0;
        var renderedTrafo = 0;
        var renderedSwitch = 0;

        rawFeatures.forEach(function (f) {
            var norm = normalizeAssetFeature(f);
            var props = norm.properties || {};
            var geom  = norm.geometry || {};
            var entityType = props.entity_type || 'ASSET';

            // 1. STRICT FINDING LAYER
            if (entityType === 'TEMUAN') {
                if (activeLayers.includes('TEMUAN') && geom.coordinates && isValidLatLng(geom.coordinates[1], geom.coordinates[0])) {
                    var tMarker = createTemuanVisualMarker(norm);
                    if (tMarker) {
                        findingLayer.addLayer(tMarker);
                        renderedTemuanCount++;
                    }
                }
                return;
            }

            // 2. STRICT MASTER ASSET LAYER
            var jenis = (props.jenis_asset || '').toUpperCase();
            var constr = (props.construction_type || '').toUpperCase();

            var isSwitchType = ['SWITCH', 'LBS', 'LBSM', 'RECLOSER', 'SECTIONALIZER', 'PROTECTION'].includes(jenis) || strContainsAny(constr, ['PMS', 'PMT', 'LBS', 'REC']);
            var isGarduType  = ['GARDU', 'SUBSTATION'].includes(jenis) || strContainsAny(constr, ['TM-8', 'TM-9', 'GTT', 'GARDU']);
            var isTrafoType  = ['TRAFO', 'TRANSFORMER'].includes(jenis) || strContainsAny(constr, ['DISTRIBUSI', 'TRAFO']);
            var isJtmType    = !isSwitchType && !isGarduType && !isTrafoType;

            var shouldRender = false;
            if (isSwitchType && activeLayers.includes('SWITCH')) shouldRender = true;
            else if (isGarduType && activeLayers.includes('GARDU')) shouldRender = true;
            else if (isTrafoType && activeLayers.includes('TRAFO')) shouldRender = true;
            else if (isJtmType && activeLayers.includes('JTM')) shouldRender = true;

            if (shouldRender && geom.coordinates && isValidLatLng(geom.coordinates[1], geom.coordinates[0])) {
                var marker = createAssetVisualMarker(norm);
                if (marker) {
                    markerCluster.addLayer(marker);
                    renderedAssetCount++;
                    if (isGarduType) renderedGardu++;
                    else if (isTrafoType) renderedTrafo++;
                    else if (isSwitchType) renderedSwitch++;
                    else renderedJtm++;
                }
            }
        });

        // 📡 Honest Empty State Check
        var emptyFeederBanner = document.getElementById('gis-empty-feeder-banner');
        if (emptyFeederBanner) {
            if (renderedAssetCount === 0 && !hasTopology) {
                emptyFeederBanner.style.display = 'block';
                document.getElementById('empty-feeder-name').textContent = currentFeederName || 'Penyulang Ini';
            } else {
                emptyFeederBanner.style.display = 'none';
            }
        }

        // 🔒 Console Data Contract Debug Group
        console.group('[GIS DATA CONTRACT]');
        console.log('Selected Feeder:', currentFeederId + ' (' + (currentFeederName || '-') + ')');
        console.log('Asset Features:', renderedAssetCount);
        console.log('Temuan Features:', renderedTemuanCount);
        console.log('Topology Edges:', (currentData.transline && currentData.transline.properties && currentData.transline.properties.edges ? currentData.transline.properties.edges.length : 0));
        console.log('Cross Feeder Rejected:', (currentData.summary && currentData.summary.rejected_cross_feeder ? currentData.summary.rejected_cross_feeder : 0));
        console.groupEnd();

        // Update live summary bar accurately
        var summaryBar = document.getElementById('gis-summary-bar');
        if (summaryBar) {
            summaryBar.style.display = 'block';
            var summaryHtml = `<i class="fas fa-network-wired text-warning me-1"></i> Aset: <strong>${renderedAssetCount}</strong> (TM: ${renderedJtm}, GTT: ${renderedGardu}, Trafo: ${renderedTrafo}, Sw: ${renderedSwitch})`;
            if (renderedTemuanCount > 0) {
                summaryHtml += ` | <i class="fas fa-triangle-exclamation text-danger ms-2 me-1"></i> Temuan: <strong>${renderedTemuanCount}</strong>`;
            }
            document.getElementById('summary-text').innerHTML = summaryHtml;
        }

        if (autoFitBounds && map) {
            if (markerCluster && markerCluster.getLayers().length > 0) {
                map.fitBounds(markerCluster.getBounds(), { padding: [40, 40] });
            } else if (translinePolylineLayer && translinePolylineLayer.getLayers().length > 0) {
                map.fitBounds(translinePolylineLayer.getBounds(), { padding: [40, 40] });
            } else if (findingLayer && findingLayer.getLayers().length > 0) {
                map.fitBounds(findingLayer.getBounds(), { padding: [40, 40] });
            }
        }
    }

    function strContainsAny(str, needles) {
        if (!str) return false;
        return needles.some(n => str.includes(n));
    }

    // Fetch Network Data On-Demand
    function loadGisNetworkOnDemand(autoFitBounds, callback) {
        if (typeof autoFitBounds === 'undefined') autoFitBounds = true;
        if (!currentFeederId) return;

        var thisRequestId = ++currentRequestId;
        currentLOD = getLODCategory(map ? map.getZoom() : 14);

        var layersParam = getSelectedSetupLayers().join(',');
        toggleLoading(true);

        fetchJson(`<?= site_url('gis/api-network') ?>?penyulang_id=${currentFeederId}&zoom=${map ? map.getZoom() : 14}&layers=${layersParam}`)
            .then(res => {
                if (thisRequestId !== currentRequestId) return;
                toggleLoading(false);
                if (res.status === 'success' && res.data) {
                    currentData = res.data;
                    renderFilteredLayers(autoFitBounds);
                    fetchPendingBadgeCount();
                    if (typeof callback === 'function') callback();
                }
            })
            .catch(err => {
                if (thisRequestId === currentRequestId) toggleLoading(false);
                console.error(err);
            });
    }

    function getLODCategory(zoom) {
        if (zoom < 13) return 'overview';
        if (zoom < 17) return 'equipment';
        return 'detail';
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

    document.getElementById('fab-open-filter').addEventListener('click', function () {
        collapseFab();
        openFilterDrawer();
    });

    document.getElementById('btn-open-filter-drawer').addEventListener('click', openFilterDrawer);

    function openFilterDrawer() {
        document.getElementById('drawer-feeder-select').value = currentFeederId;
        if (document.getElementById('drawer-layer-jtm') && document.getElementById('setup-layer-jtm')) {
            document.getElementById('drawer-layer-jtm').checked = document.getElementById('setup-layer-jtm').checked;
        }
        if (document.getElementById('drawer-layer-gardu') && document.getElementById('setup-layer-gardu')) {
            document.getElementById('drawer-layer-gardu').checked = document.getElementById('setup-layer-gardu').checked;
        }
        if (document.getElementById('drawer-layer-trafo') && document.getElementById('setup-layer-trafo')) {
            document.getElementById('drawer-layer-trafo').checked = document.getElementById('setup-layer-trafo').checked;
        }
        if (document.getElementById('drawer-layer-switch') && document.getElementById('setup-layer-switch')) {
            document.getElementById('setup-layer-switch').checked = document.getElementById('drawer-layer-switch').checked;
        }
        if (document.getElementById('drawer-layer-temuan') && document.getElementById('setup-layer-temuan')) {
            document.getElementById('drawer-layer-temuan').checked = document.getElementById('setup-layer-temuan').checked;
        }
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

        if (document.getElementById('drawer-layer-jtm') && document.getElementById('setup-layer-jtm')) {
            document.getElementById('setup-layer-jtm').checked = document.getElementById('drawer-layer-jtm').checked;
        }
        if (document.getElementById('drawer-layer-gardu') && document.getElementById('setup-layer-gardu')) {
            document.getElementById('setup-layer-gardu').checked = document.getElementById('drawer-layer-gardu').checked;
        }
        if (document.getElementById('drawer-layer-trafo') && document.getElementById('setup-layer-trafo')) {
            document.getElementById('setup-layer-trafo').checked = document.getElementById('drawer-layer-trafo').checked;
        }
        if (document.getElementById('drawer-layer-switch') && document.getElementById('setup-layer-switch')) {
            document.getElementById('setup-layer-switch').checked = document.getElementById('drawer-layer-switch').checked;
        }
        if (document.getElementById('drawer-layer-temuan') && document.getElementById('setup-layer-temuan')) {
            document.getElementById('setup-layer-temuan').checked = document.getElementById('drawer-layer-temuan').checked;
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
                if (!isValidLatLng(uLat, uLng)) return;

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
    // FIELD ASSET CORRECTION MODAL HANDLERS
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

        fetchJson('<?= site_url('gis/api-propose-correction') ?>', {
            method: 'POST',
            body: JSON.stringify(payload)
        })
        .then(res => {
            if (res.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('modal-koreksi-asset')).hide();
                alert(res.message);
                fetchPendingBadgeCount();
            } else {
                alert('Gagal: ' + (res.message || 'Terjadi kesalahan'));
            }
        })
        .catch(err => {
            alert('Gagal mengirim usulan koreksi: ' + err.message);
        });
    });

    // ========================================================
    // ADD NEW ASSET WORKFLOW
    // ========================================================
    function openAddAssetModal() {
        if (!currentFeederId) {
            alert('Silakan pilih penyulang terlebih dahulu!');
            return;
        }

        var center = (map && map.getCenter && isValidLatLng(map.getCenter().lat, map.getCenter().lng)) ? map.getCenter() : { lat: -7.4523, lng: 112.7161 };
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

        fetchJson(`<?= site_url('gis/api-next-code') ?>?penyulang_id=${currentFeederId}&jenis_asset=${jenis}`)
            .then(res => {
                if (res.status === 'success') {
                    document.getElementById('new-code').value = res.kode_asset;
                }
            })
            .catch(err => console.error(err));
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

        fetchJson('<?= site_url('gis/api-propose-new-asset') ?>', {
            method: 'POST',
            body: JSON.stringify(payload)
        })
        .then(res => {
            if (res.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('modal-tambah-asset')).hide();
                alert(res.message);
                fetchPendingBadgeCount();
            } else {
                alert('Gagal: ' + (res.message || 'Terjadi kesalahan'));
            }
        })
        .catch(err => {
            alert('Gagal menambah aset: ' + err.message);
        });
    });

    // ========================================================
    // PENDING CORRECTIONS & APPROVAL LAYER
    // ========================================================
    function fetchPendingBadgeCount() {
        if (!currentFeederId) return;
        fetchJson(`<?= site_url('gis/api-pending-corrections') ?>?penyulang_id=${currentFeederId}`)
            .then(res => {
                if (res.status === 'success') {
                    var badge = document.getElementById('pending-badge-count');
                    badge.textContent = res.count;
                    badge.style.display = res.count > 0 ? 'inline-block' : 'none';
                }
            })
            .catch(err => console.error(err));
    }

    document.getElementById('btn-view-corrections').addEventListener('click', function () {
        var container = document.getElementById('corrections-list-container');
        var loading = document.getElementById('corrections-loading');

        container.innerHTML = '';
        loading.style.display = 'block';

        var modal = new bootstrap.Modal(document.getElementById('modal-pending-corrections'));
        modal.show();

        fetchJson(`<?= site_url('gis/api-pending-corrections') ?>?penyulang_id=${currentFeederId}`)
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
                            <h6 class="fw-bold mb-1 text-dark">${c.nama_asset || afterData.nama_asset || 'Koreksi Topologi Jaringan'}</h6>
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
            })
            .catch(err => {
                loading.style.display = 'none';
                container.innerHTML = `<div class="text-center text-danger py-4">${err.message}</div>`;
            });
    });

    window.applyCorrectionAction = function (corrId) {
        if (!confirm('Setujui dan terapkan usulan ini ke data master jaringan?')) return;
        fetchJson('<?= site_url('gis/api-apply-correction') ?>', {
            method: 'POST',
            body: JSON.stringify({ correction_id: corrId })
        })
        .then(res => {
            alert(res.message);
            bootstrap.Modal.getInstance(document.getElementById('modal-pending-corrections')).hide();
            loadGisNetworkOnDemand(false);
        })
        .catch(err => {
            alert('Gagal menyetujui koreksi: ' + err.message);
        });
    };

    window.rejectCorrectionAction = function (corrId) {
        var reason = prompt('Masukkan alasan penolakan usulan:');
        if (!reason) return;
        fetchJson('<?= site_url('gis/api-reject-correction') ?>', {
            method: 'POST',
            body: JSON.stringify({ correction_id: corrId, rejection_reason: reason })
        })
        .then(res => {
            alert(res.message);
            bootstrap.Modal.getInstance(document.getElementById('modal-pending-corrections')).hide();
            loadGisNetworkOnDemand(false);
        })
        .catch(err => {
            alert('Gagal menolak koreksi: ' + err.message);
        });
    };

});
</script>
<?= $this->endSection() ?>
