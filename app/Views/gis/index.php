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
        min-height: 36px;
        display: inline-flex;
        align-items: center;
        touch-action: manipulation;
    }
    .feeder-chip-btn.active {
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
        box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
    }
    #setup-feeder-select, #drawer-feeder-select {
        min-height: 44px;
        font-size: 13px;
        touch-action: manipulation;
    }
    #feeder-quick-chips {
        max-height: 140px;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 2px;
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
    .quick-card-actions button {
        min-height: 40px;
        touch-action: manipulation;
        pointer-events: auto;
    }

    /* Voice Mic Collision Elimination (Higher z-index guard & comprehensive suppression) */
    #global-voice-container {
        z-index: 1000 !important;
    }
    body.gis-quickcard-active #btn-global-mic,
    body.gis-drawer-active #btn-global-mic,
    body.gis-sheet-open #btn-global-mic,
    body.modal-open #btn-global-mic,
    body.offcanvas-open #btn-global-mic,
    body.gis-quickcard-active #global-voice-container,
    body.gis-drawer-active #global-voice-container,
    body.gis-sheet-open #global-voice-container,
    body.modal-open #global-voice-container,
    body.offcanvas-open #global-voice-container {
        opacity: 0 !important;
        pointer-events: none !important;
        visibility: hidden !important;
        display: none !important;
    }

    /* Offcanvas Sheets - Touch-safe, elevated z-index, pan-y, safe-area aware */
    .offcanvas-compact-sheet {
        height: auto !important;
        max-height: 85vh !important;
        border-radius: 20px 20px 0 0 !important;
        border-top: 1px solid rgba(226, 232, 240, 0.8) !important;
        box-shadow: 0 -10px 35px rgba(15, 23, 42, 0.25) !important;
        z-index: 1055 !important;
        touch-action: pan-y !important;
        overflow: hidden !important;
    }

    .offcanvas-compact-sheet .offcanvas-body {
        max-height: 85vh !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch !important;
        padding-bottom: 0 !important;
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
        padding: 14px 16px;
        min-height: 52px;
        border-radius: 12px;
        border: 1px solid #f1f5f9;
        background: #ffffff;
        font-weight: 600;
        font-size: 13px;
        color: #1e293b;
        cursor: pointer;
        touch-action: manipulation;
        user-select: none;
        -webkit-user-select: none;
        -webkit-tap-highlight-color: rgba(2, 132, 199, 0.1);
        pointer-events: auto !important;
        position: relative;
        z-index: 2;
        transition: background 0.15s ease, transform 0.05s ease;
        text-decoration: none !important;
    }
    .sheet-action-item:active {
        transform: scale(0.98);
        background: #f1f5f9;
    }
    .sheet-action-item * {
        pointer-events: none !important;
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
    .sheet-action-item.destructive:active {
        background: #fecaca;
    }

    .sheet-sticky-footer {
        position: sticky;
        bottom: 0;
        background: #ffffff;
        padding-top: 10px;
        padding-bottom: calc(var(--gis-mob-bottom-nav, 62px) + env(safe-area-inset-bottom, 0px) + 12px);
        border-top: 1px solid #f1f5f9;
        margin-top: 12px;
        display: flex;
        gap: 8px;
        z-index: 20;
    }

    @media (min-width: 769px) {
        .sheet-sticky-footer {
            padding-bottom: 12px !important;
        }
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
    .asset-ring-unassigned { border: 2.5px dashed #f59e0b; background: rgba(245, 158, 11, 0.2); animation: pulse-proposed-flat 2s infinite; }

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
                <button type="button" id="btn-open-transline-ai" class="btn btn-sm rounded-pill font-weight-bold shadow-sm pointer-events-auto text-white px-2 px-md-3" style="font-size: 11px; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); border: 1px solid #0284c7;">
                    <i class="fas fa-bolt text-warning me-1"></i> Auto-Complete AI
                </button>
                <button type="button" id="btn-open-proposals-drawer" class="btn btn-warning btn-sm rounded-pill font-weight-bold shadow-sm pointer-events-auto text-dark px-2 px-md-3" style="font-size: 11px; border: 1px solid #f59e0b; background: linear-gradient(135deg, #fef08a 0%, #facc15 100%);">
                    <i class="fas fa-robot text-dark me-1"></i> Proposal AI
                    <span id="proposals-badge-count" class="badge rounded-pill bg-dark text-warning ms-1" style="font-size:10px; padding: 2px 6px;">5</span>
                </button>
                <button type="button" id="btn-open-filter-drawer" class="btn btn-light btn-sm rounded-pill font-weight-bold shadow-sm pointer-events-auto" style="font-size: 11px;">
                    <i class="fas fa-sliders text-primary me-1"></i> Filter
                </button>
                <button type="button" id="btn-view-corrections" class="btn btn-dark btn-sm rounded-pill font-weight-bold position-relative pointer-events-auto" style="font-size: 11px; background: rgba(15, 23, 42, 0.9);">
                    <i class="fas fa-edit text-info me-1"></i> Koreksi Aset
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
            <button type="button" id="btn-add-midpoint-vertex" class="btn btn-sm btn-outline-info rounded-pill px-2 py-1" title="Tambah Titik Belok">
                <i class="fas fa-plus me-1"></i> Titik
            </button>
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
                    <i class="fas fa-shapes text-warning me-1"></i> LEGENDA GIS
                </span>
                <button type="button" id="btn-close-legend" class="btn-close btn-close-sm" style="font-size: 9px;"></button>
            </div>
            <div class="d-flex flex-column gap-1 mb-2">
                <span class="text-muted fw-bold" style="font-size: 10px; text-transform: uppercase;">Simbol Aset Master (PNG)</span>
                <?php if (!empty($legendItems)): ?>
                    <?php foreach ($legendItems as $item): ?>
                        <?php if ($item['symbol_key'] === 'DEFAULT') continue; ?>
                        <div class="legend-item-row">
                            <img src="<?= !empty($item['png_path']) ? base_url($item['png_path']) : base_url($item['svg_path']) ?>" alt="<?= esc($item['label']) ?>" class="legend-icon-preview">
                            <div class="d-flex flex-column" style="line-height: 1.1;">
                                <strong class="text-dark" style="font-size: 10px;"><?= esc($item['symbol_key']) ?></strong>
                                <span class="text-muted" style="font-size: 9px;"><?= esc($item['label']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="border-top pt-2 mt-2">
                <span class="text-muted fw-bold d-block mb-1" style="font-size: 10px; text-transform: uppercase;">Topologi Jaringan</span>
                <div class="d-flex flex-column gap-1">
                    <div class="legend-item-row align-items-center">
                        <div style="width: 22px; height: 4px; background-color: #0284c7; border-radius: 2px; flex-shrink: 0;"></div>
                        <div class="d-flex flex-column" style="line-height: 1.1;">
                            <strong class="text-dark" style="font-size: 10px;">Transline Manual (Otoritatif)</strong>
                            <span class="text-muted" style="font-size: 9px;">Koneksi Baseline Manual</span>
                        </div>
                    </div>
                    <div class="legend-item-row align-items-center">
                        <div style="width: 22px; height: 4px; background-color: #2563eb; border-radius: 2px; flex-shrink: 0;"></div>
                        <div class="d-flex flex-column" style="line-height: 1.1;">
                            <strong class="text-dark" style="font-size: 10px;">Transline AI (TL-02)</strong>
                            <span class="text-muted" style="font-size: 9px;">AI Auto-Complete Otoritatif</span>
                        </div>
                    </div>
                    <div class="legend-item-row align-items-center">
                        <div style="width: 22px; height: 4px; background-color: #06b6d4; border-radius: 2px; flex-shrink: 0;"></div>
                        <div class="d-flex flex-column" style="line-height: 1.1;">
                            <strong class="text-dark" style="font-size: 10px;">Rekonstruksi AI (TL-03)</strong>
                            <span class="text-muted" style="font-size: 9px;">Advanced Network Reconstruction</span>
                        </div>
                    </div>
                    <div class="legend-item-row align-items-center">
                        <div style="width: 22px; height: 4px; background-color: #10b981; border-radius: 2px; flex-shrink: 0;"></div>
                        <div class="d-flex flex-column" style="line-height: 1.1;">
                            <strong class="text-dark" style="font-size: 10px;">Promosi Jaringan (TL-04)</strong>
                            <span class="text-muted" style="font-size: 9px;">Network-Level Promotion (Emerald)</span>
                        </div>
                    </div>
                    <div class="legend-item-row align-items-center">
                        <div style="width: 22px; height: 0px; border-top: 3px dashed #8b5cf6; flex-shrink: 0;"></div>
                        <div class="d-flex flex-column" style="line-height: 1.1;">
                            <strong class="text-dark" style="font-size: 10px;">Proposal AI (Dashed)</strong>
                            <span class="text-muted" style="font-size: 9px;">Kandidat Rekomendasi (Review)</span>
                        </div>
                    </div>
                    <div class="legend-item-row align-items-center">
                        <div style="width: 14px; height: 14px; border-radius: 50%; background-color: #2563eb; flex-shrink: 0; margin-left: 4px;"></div>
                        <div class="d-flex flex-column" style="line-height: 1.1;">
                            <strong class="text-dark" style="font-size: 10px;">Master Asset (Node JTM)</strong>
                            <span class="text-muted" style="font-size: 9px;">Gardu, Tiang, Trafo Otoritatif</span>
                        </div>
                    </div>
                    <div class="legend-item-row align-items-center">
                        <div style="width: 18px; height: 18px; color: #eab308; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-left: 2px;">
                            <i class="fas fa-triangle-exclamation" style="font-size: 12px; color: #eab308;"></i>
                        </div>
                        <div class="d-flex flex-column" style="line-height: 1.1;">
                            <strong class="text-dark" style="font-size: 10px;">Temuan (Konteks Inspeksi)</strong>
                            <span class="text-muted" style="font-size: 9px;">Bukan Node/Endpoint Jaringan</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="border-top pt-2 mt-2">
                <span class="text-muted fw-bold d-block mb-1" style="font-size: 10px; text-transform: uppercase;">Standar Konduktor SUTM (PLN)</span>
                <div class="d-flex flex-column gap-1">
                    <div class="legend-item-row align-items-center">
                        <img src="<?= base_url('assets/gis/icons/a3c-150.png') ?>" alt="AAAC 150" class="legend-icon-preview" style="height: 14px; width: 44px; object-fit: contain;">
                        <span class="text-dark font-monospace" style="font-size: 9px;">AAAC 150 mm²</span>
                    </div>
                    <div class="legend-item-row align-items-center">
                        <img src="<?= base_url('assets/gis/icons/a3c-240.png') ?>" alt="AAAC 240" class="legend-icon-preview" style="height: 14px; width: 44px; object-fit: contain;">
                        <span class="text-dark font-monospace" style="font-size: 9px;">AAAC 240 mm²</span>
                    </div>
                    <div class="legend-item-row align-items-center">
                        <img src="<?= base_url('assets/gis/icons/a3cs-150.png') ?>" alt="AAAC-S 150" class="legend-icon-preview" style="height: 14px; width: 44px; object-fit: contain;">
                        <span class="text-dark font-monospace" style="font-size: 9px;">AAAC-S 150 mm²</span>
                    </div>
                    <div class="legend-item-row align-items-center">
                        <img src="<?= base_url('assets/gis/icons/mvtic-150.png') ?>" alt="MVTIC" class="legend-icon-preview" style="height: 14px; width: 44px; object-fit: contain;">
                        <span class="text-dark font-monospace" style="font-size: 9px;">MVTIC 150 mm²</span>
                    </div>
                    <div class="legend-item-row align-items-center">
                        <img src="<?= base_url('assets/gis/icons/xlpe.png') ?>" alt="XLPE" class="legend-icon-preview" style="height: 14px; width: 44px; object-fit: contain;">
                        <span class="text-dark font-monospace" style="font-size: 9px;">XLPE 150 mm²</span>
                    </div>
                </div>
                <div class="mt-2 text-center">
                    <span class="badge bg-secondary text-wrap" style="font-size: 9px; padding: 4px 8px;">
                        🔒 MODE BACA OTORITATIF (D4B Terkunci)
                    </span>
                </div>
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

        <!-- Sticky Action Footer (Mobile Safe-Area Aware) -->
        <div class="sheet-sticky-footer">
            <button type="button" class="btn btn-outline-secondary w-50 rounded-pill py-2" data-bs-dismiss="offcanvas">
                Batal
            </button>
            <button type="button" id="btn-submit-connection" class="btn btn-success w-50 fw-bold rounded-pill shadow-sm py-2">
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
            <div class="d-flex justify-content-between mb-1">
                <span class="small text-muted">Titik 1 (Sumber):</span>
                <span id="spec-source-name" class="small fw-bold text-dark text-truncate" style="max-width: 200px;">-</span>
            </div>
            <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                <span class="small text-muted">Titik 2 (Tujuan):</span>
                <span id="spec-target-name" class="small fw-bold text-primary text-truncate" style="max-width: 200px;">-</span>
            </div>
            <input type="hidden" id="spec-transline-id" value="" />
            <input type="hidden" id="spec-source-id" value="" />
            <input type="hidden" id="spec-target-id" value="" />

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

        <!-- Sticky Action Footer (Mobile Safe-Area Aware) -->
        <div class="sheet-sticky-footer">
            <button type="button" class="btn btn-outline-secondary w-50 rounded-pill py-2" data-bs-dismiss="offcanvas">
                Batal
            </button>
            <button type="button" id="btn-submit-conductor-spec" class="btn btn-warning w-50 fw-bold rounded-pill shadow-sm text-dark py-2">
                <i class="fas fa-save me-1"></i> <?= !empty($isAdmin) ? 'Simpan Langsung' : 'Kirim Usulan' ?>
            </button>
        </div>
    </div>
</div>

<!-- ========================================================
     CONFIRM DELETE PAIR SHEET (Hapus Sambungan Pair A-B)
     ======================================================== -->
<div class="offcanvas offcanvas-bottom offcanvas-compact-sheet" tabindex="-1" id="offcanvas-delete-connection-sheet">
    <div class="offcanvas-body p-3">
        <div class="sheet-drag-handle"></div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0 text-danger"><i class="fas fa-trash-alt text-danger me-2"></i> Konfirmasi Hapus Jalur</h6>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="offcanvas"></button>
        </div>
        
        <div class="card bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded-3 p-3 mb-3">
            <div class="d-flex justify-content-between mb-1">
                <span class="small text-muted">Titik 1 (Sumber):</span>
                <span id="del-source-name" class="small fw-bold text-dark text-truncate" style="max-width: 200px;">-</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="small text-muted">Titik 2 (Tujuan):</span>
                <span id="del-target-name" class="small fw-bold text-danger text-truncate" style="max-width: 200px;">-</span>
            </div>
            <div class="d-flex justify-content-between border-top border-danger border-opacity-25 pt-1 mt-1 mb-1">
                <span class="small text-muted">Kode Segmen:</span>
                <span id="del-segment-code" class="small fw-bold font-monospace text-dark">-</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="small text-muted">Jarak Segmen:</span>
                <span id="del-distance-meters" class="small fw-bold font-monospace text-dark">0 m</span>
            </div>
            <div class="d-flex justify-content-between border-top border-danger border-opacity-25 pt-1 mt-1">
                <span class="small text-muted">Spesifikasi Kabel:</span>
                <span id="del-conductor-spec" class="small fw-bold text-primary">AAAC 150 mm²</span>
            </div>
        </div>

        <input type="hidden" id="del-transline-id" value="" />
        <input type="hidden" id="del-source-id" value="" />
        <input type="hidden" id="del-target-id" value="" />

        <!-- Sticky Action Footer (Mobile Safe-Area Aware) -->
        <div class="sheet-sticky-footer">
            <button type="button" class="btn btn-outline-secondary w-50 rounded-pill py-2" data-bs-dismiss="offcanvas">
                Batal
            </button>
            <button type="button" id="btn-confirm-delete-pair" class="btn btn-danger w-50 fw-bold rounded-pill shadow-sm py-2">
                <i class="fas fa-trash-alt me-1"></i> Hapus Jalur
            </button>
        </div>
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
     MAP-02: READ-ONLY ASSET CONTEXT DRAWER
     Deterministic Read-Only Engine with Governed BOM Preview
     ======================================================== -->
<div class="offcanvas offcanvas-bottom offcanvas-compact-sheet d-flex flex-column" tabindex="-1" id="offcanvas-asset-context-drawer" style="max-height: 85vh; border-top-left-radius: 18px; border-top-right-radius: 18px;">
    <div class="offcanvas-body p-3 pb-0 flex-grow-1" style="overflow-y: auto;">
        <div class="sheet-drag-handle"></div>

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-2 border-bottom pb-2">
            <div class="d-flex align-items-center gap-2">
                <img id="ctx-drawer-img" src="<?= base_url('/assets/icons/network/generic-network-asset.svg') ?>" alt="Icon" style="width: 34px; height: 34px; object-fit: contain;">
                <div>
                    <h6 class="fw-bold text-dark mb-0" id="ctx-drawer-title" style="font-size: 15px;">-</h6>
                    <span id="ctx-drawer-code" class="small text-primary font-monospace fw-bold" style="font-size: 12px;">-</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span id="ctx-drawer-badge" class="badge bg-success px-2 py-1">● GOOD</span>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
        </div>

        <!-- Loading State -->
        <div id="ctx-drawer-loading" class="text-center py-4 text-primary">
            <i class="fas fa-circle-notch fa-spin fa-2x mb-2"></i>
            <p class="small fw-bold mb-0">Memuat konteks aset & standar konstruksi...</p>
        </div>

        <!-- Error State -->
        <div id="ctx-drawer-error" class="alert alert-danger py-2 px-3 small my-2" style="display: none;">
            <i class="fas fa-exclamation-triangle me-1"></i> <span id="ctx-drawer-error-msg">-</span>
        </div>

        <!-- Content Container -->
        <div id="ctx-drawer-content" style="display: none;">
            <!-- Section 1: Identitas & Koordinat Aset -->
            <div class="card bg-light border-0 rounded-3 p-2 mb-2">
                <div class="d-flex justify-content-between mb-1">
                    <span class="small text-muted"><i class="fas fa-tag me-1"></i>Jenis Aset</span>
                    <span id="ctx-drawer-jenis" class="small fw-bold text-dark">-</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="small text-muted"><i class="fas fa-map-marker-alt me-1"></i>Lokasi</span>
                    <span id="ctx-drawer-loc" class="small fw-bold text-dark text-truncate" style="max-width: 210px;">-</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="small text-muted"><i class="fas fa-location-crosshairs me-1"></i>Koordinat GPS</span>
                    <span id="ctx-drawer-coords" class="small fw-bold font-monospace text-primary">-</span>
                </div>
            </div>

            <!-- Section 2: Konteks Jaringan (ULP -> Feeder -> Section) -->
            <!-- Section 2: Konteks Jaringan (ULP -> Feeder -> Section) -->
            <div class="card border rounded-3 p-2 mb-2 bg-white shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="small fw-bold text-secondary text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                        <i class="fas fa-network-wired text-primary me-1"></i> Konteks Jaringan
                    </div>
                    <span id="ctx-drawer-section-badge" class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0" style="font-size: 10px;">✓ TERVERIFIKASI SISTEM</span>
                </div>
                <div class="row g-2 text-center mb-2">
                    <div class="col-4">
                        <div class="p-1 bg-light rounded border position-relative">
                            <span class="d-block text-muted" style="font-size: 10px;">ULP <i class="fas fa-lock text-secondary ms-1" title="Server-Authoritative / Locked"></i></span>
                            <span id="ctx-drawer-ulp" class="d-block fw-bold text-dark text-truncate" style="font-size: 11px;">-</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-1 bg-light rounded border position-relative">
                            <span class="d-block text-muted" style="font-size: 10px;">Penyulang <i class="fas fa-lock text-secondary ms-1" title="Server-Authoritative / Locked"></i></span>
                            <span id="ctx-drawer-penyulang" class="d-block fw-bold text-dark text-truncate" style="font-size: 11px;">-</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-1 bg-light rounded border">
                            <span class="d-block text-muted" style="font-size: 10px;">Section</span>
                            <span id="ctx-drawer-section" class="d-block fw-bold text-dark text-truncate" style="font-size: 11px;">-</span>
                        </div>
                    </div>
                </div>

                <!-- Section Working Context Action & Inline Selector -->
                <div class="d-flex align-items-center justify-content-between pt-1 border-top">
                    <span class="small text-muted" style="font-size: 11px;"><i class="fas fa-layer-group text-primary me-1"></i>Konteks Section:</span>
                    <div class="d-flex gap-1">
                        <button type="button" id="btn-correct-section" class="btn btn-sm btn-outline-primary py-1 px-2 d-flex align-items-center gap-1" style="min-height: 38px; font-size: 12px;">
                            <i class="fas fa-pen"></i> Perbaiki
                        </button>
                        <button type="button" id="btn-cancel-section" class="btn btn-sm btn-outline-secondary py-1 px-2 d-flex align-items-center gap-1" style="min-height: 38px; font-size: 12px; display: none;">
                            <i class="fas fa-times"></i> Batal
                        </button>
                    </div>
                </div>

                <!-- Section Selector Container -->
                <div id="ctx-section-selector-container" class="mt-2 p-2 bg-light rounded border" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold text-dark mb-0" style="font-size: 11px;">Pilih Section Kerja (Feeder Terkait):</label>
                        <span class="badge bg-secondary-subtle text-secondary" style="font-size: 9px;">Working Context</span>
                    </div>
                    <div class="input-group input-group-sm mb-1">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="input-search-section" class="form-control" placeholder="Cari nama section..." style="font-size: 12px;">
                    </div>
                    <div id="section-options-list" class="list-group list-group-flush border rounded bg-white" style="max-height: 150px; overflow-y: auto;">
                        <!-- Dynamically populated -->
                    </div>
                </div>
            </div>

            <!-- Section 3: Standar Konstruksi PLN -->
            <div class="card border rounded-3 p-2 mb-2 bg-white shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="small fw-bold text-secondary text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                        <i class="fas fa-hammer text-warning me-1"></i> Standar Konstruksi
                    </div>
                    <span id="ctx-drawer-const-status" class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0" style="font-size: 10px;">✓ TERVERIFIKASI SISTEM</span>
                </div>
                <div id="ctx-drawer-const-box">
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <span id="ctx-drawer-const-code" class="badge bg-primary px-2 py-1 font-monospace fw-bold" style="font-size: 12px;">-</span>
                        <div class="flex-grow-1">
                            <div id="ctx-drawer-const-name" class="small fw-bold text-dark">-</div>
                            <div class="text-muted" style="font-size: 11px;" id="ctx-drawer-const-sub">-</div>
                        </div>
                    </div>
                </div>
                <div id="ctx-drawer-const-empty" class="alert alert-warning py-1 px-2 small mb-0 mt-1" style="display: none; font-size: 11px;">
                    <i class="fas fa-info-circle me-1"></i> Konstruksi belum terpetakan pada aset ini.
                </div>

                <!-- Construction Working Context Action & Inline Selector -->
                <div class="d-flex align-items-center justify-content-between mt-2 pt-1 border-top">
                    <span class="small text-muted" style="font-size: 11px;"><i class="fas fa-tools text-warning me-1"></i>Konteks Konstruksi:</span>
                    <div class="d-flex gap-1">
                        <button type="button" id="btn-correct-const" class="btn btn-sm btn-outline-warning text-dark py-1 px-2 d-flex align-items-center gap-1" style="min-height: 38px; font-size: 12px;">
                            <i class="fas fa-pen"></i> Perbaiki
                        </button>
                        <button type="button" id="btn-cancel-const" class="btn btn-sm btn-outline-secondary py-1 px-2 d-flex align-items-center gap-1" style="min-height: 38px; font-size: 12px; display: none;">
                            <i class="fas fa-times"></i> Batal
                        </button>
                    </div>
                </div>

                <!-- Construction Selector Container -->
                <div id="ctx-const-selector-container" class="mt-2 p-2 bg-light rounded border" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold text-dark mb-0" style="font-size: 11px;">Pilih Standar Konstruksi PLN:</label>
                        <span class="badge bg-secondary-subtle text-secondary" style="font-size: 9px;">Working Context</span>
                    </div>
                    <div class="input-group input-group-sm mb-1">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="input-search-const" class="form-control" placeholder="Cari TM-1, TM-2, dsb..." style="font-size: 12px;">
                    </div>
                    <div id="const-options-list" class="list-group list-group-flush border rounded bg-white" style="max-height: 150px; overflow-y: auto;">
                        <!-- Dynamically populated -->
                    </div>
                </div>
            </div>

            <!-- Section 4: Governed Material / BOM Catalog Preview (Strict Read-Only) -->
            <div class="card border rounded-3 p-2 mb-3 bg-white shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="small fw-bold text-secondary text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                        <i class="fas fa-boxes-stacked text-info me-1"></i> Katalog Material Standar (BOM Preview)
                    </div>
                    <span class="badge bg-light text-muted border px-2 py-0" style="font-size: 10px;">Read-Only</span>
                </div>
                <p class="text-muted mb-2" style="font-size: 11px;">Daftar material resmi sesuai tipe konstruksi aset. Pratinjau katalog (bukan pemilihan transaksi).</p>

                <div id="ctx-drawer-bom-container">
                    <div class="table-responsive border rounded bg-white">
                        <table class="table table-sm table-hover mb-0" style="font-size: 11px;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 32%;">Kode PLN</th>
                                    <th>Nama Material</th>
                                    <th style="width: 15%; text-align: center;">Satuan</th>
                                </tr>
                            </thead>
                            <tbody id="ctx-drawer-bom-tbody">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="ctx-drawer-bom-empty" class="alert alert-secondary py-1 px-2 small mb-0 mt-1 text-center text-muted" style="display: none; font-size: 11px;">
                    <i class="fas fa-box-open me-1"></i> BOM konstruksi belum tersedia pada katalog resmi.
                </div>
            </div>
        </div>
    </div>

    <!-- Pinned Action Footer (Navigation Only, Always Visible) -->
    <div class="sheet-sticky-footer px-3 pt-2 pb-3 bg-white border-top flex-shrink-0" style="z-index: 1060;">
        <a id="btn-context-drawer-create-temuan" href="#" class="btn btn-primary w-100 fw-bold rounded-pill text-white py-2 shadow-sm d-flex justify-content-center align-items-center gap-2" style="font-size: 13px;">
            <i class="fas fa-plus-circle"></i> Buat Temuan dari Aset Ini
        </a>
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
     TL-01 SUB-GATE D4A: TRANSLINE EXCEPTION & PROPOSAL REVIEW QUEUE DRAWER
     ======================================================== -->
<div class="offcanvas offcanvas-end offcanvas-compact-sheet shadow-lg" tabindex="-1" id="offcanvas-proposals-drawer" style="width: 100%; max-width: 460px; border-left: 1px solid rgba(226, 232, 240, 0.9); z-index: 1060;">
    <div class="offcanvas-header bg-dark text-white py-3 border-bottom border-secondary">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle p-2 bg-warning bg-opacity-20 text-warning">
                <i class="fas fa-microscope fs-5"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-0 text-white" style="font-size: 14px;">TRANSLINE EXCEPTION & PROPOSAL REVIEW</h6>
                <span id="proposal-drawer-feeder" class="small text-warning font-monospace" style="font-size: 11px;">BANJAR KEMANTREN</span>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>

    <!-- Summary Metrics & Operational State Filter Pills -->
    <div class="p-3 bg-light border-bottom">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small fw-bold text-uppercase text-secondary" style="font-size: 10px;">Filter Status Operasional:</span>
            <span class="badge bg-primary font-monospace" style="font-size: 10px;">TL-01 Sub-Gate D4A</span>
        </div>
        <div class="row g-1 text-center" style="font-size: 11px;">
            <div class="col-4">
                <div class="p-2 bg-white rounded-2 border shadow-sm cursor-pointer filter-pill active" data-filter="ALL" id="pill-state-all">
                    <span class="d-block text-primary fw-bold fs-6" id="cnt-state-all">0</span>
                    <span class="text-muted font-monospace" style="font-size: 9px;">SEMUA</span>
                </div>
            </div>
            <div class="col-4">
                <div class="p-2 bg-white rounded-2 border shadow-sm cursor-pointer filter-pill" data-filter="GOVERNANCE_ANOMALY" id="pill-state-gov-anomaly">
                    <span class="d-block text-danger fw-bold fs-6" id="cnt-state-gov-anomaly">0</span>
                    <span class="text-muted font-monospace" style="font-size: 9px;">ANOMALY</span>
                </div>
            </div>
            <div class="col-4">
                <div class="p-2 bg-white rounded-2 border shadow-sm cursor-pointer filter-pill" data-filter="BLOCKED" id="pill-state-blocked">
                    <span class="d-block text-danger fw-bold fs-6" id="cnt-state-blocked">0</span>
                    <span class="text-muted font-monospace" style="font-size: 9px;">BLOCKED</span>
                </div>
            </div>
            <div class="col-4 mt-1">
                <div class="p-2 bg-white rounded-2 border shadow-sm cursor-pointer filter-pill" data-filter="HUMAN_REVIEW" id="pill-state-human-review">
                    <span class="d-block text-warning fw-bold fs-6" id="cnt-state-human-review">0</span>
                    <span class="text-muted font-monospace" style="font-size: 9px;">REVIEW</span>
                </div>
            </div>
            <div class="col-4 mt-1">
                <div class="p-2 bg-white rounded-2 border shadow-sm cursor-pointer filter-pill" data-filter="READY" id="pill-state-ready">
                    <span class="d-block text-success fw-bold fs-6" id="cnt-state-ready">0</span>
                    <span class="text-muted font-monospace" style="font-size: 9px;">READY</span>
                </div>
            </div>
            <div class="col-4 mt-1">
                <div class="p-2 bg-white rounded-2 border shadow-sm cursor-pointer filter-pill" data-filter="ACTIVE" id="pill-state-active">
                    <span class="d-block text-info fw-bold fs-6" id="cnt-state-active">0</span>
                    <span class="text-muted font-monospace" style="font-size: 9px;">ACTIVE</span>
                </div>
            </div>
        </div>
    </div>

    <!-- TL-01 Sub-Gate D4A: Read-Only Policy Lock Banner -->
    <div class="px-3 py-2 bg-dark text-white border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2" id="d4a-drawer-policy-banner" style="font-size: 11px;">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary font-monospace"><i class="fas fa-lock me-1"></i> WRITE GATE: LOCKED</span>
            <span class="text-white-50" style="font-size: 10px;">Sub-Gate D4A · Pure Read-Only</span>
        </div>
        <div>
            <span class="badge bg-dark border border-secondary text-warning font-monospace" style="font-size: 10px;">MUTATIONS = 0</span>
        </div>
    </div>

    <!-- Proposal Cards List Container -->
    <div class="offcanvas-body p-3" style="overflow-y: auto; max-height: calc(100vh - 230px);">
        <div id="proposals-loading" class="text-center py-5" style="display: none;">
            <div class="spinner-border text-primary" role="status"></div>
            <span class="d-block small text-muted mt-2">Memuat antrean proposal...</span>
        </div>
        <div id="proposals-empty" class="text-center py-5" style="display: none;">
            <i class="fas fa-inbox text-muted fs-1 mb-2"></i>
            <h6 class="fw-bold text-dark mb-1">No proposal sesuai filter.</h6>
            <p class="small text-muted mb-0">Tidak ada usulan dalam antrean untuk kriteria yang dipilih.</p>
        </div>
        <div id="proposals-list" class="d-flex flex-column gap-3">
            <!-- Dynamically populated cards -->
        </div>
    </div>

    <!-- Sticky Drawer Footer -->
    <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center" style="font-size: 11px;">
        <span class="text-muted"><i class="fas fa-shield-halved text-success me-1"></i> D4A Read-Only Queue (0 DB Writes)</span>
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="offcanvas">Tutup</button>
    </div>
</div>

<!-- ========================================================
     TL-01 SUB-GATE D4A: EXCEPTION REVIEW WORKBENCH MODAL
     ======================================================== -->
<div class="modal fade" id="modal-proposal-workbench" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <!-- Modal Header -->
            <div class="modal-header bg-dark text-white py-3 border-bottom border-secondary">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="rounded-circle p-2 bg-primary bg-opacity-25 text-primary">
                        <i class="fas fa-microscope fs-5"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h6 class="modal-title fw-bold mb-0 text-white" style="font-size: 15px;">TL-01 — EXCEPTION REVIEW WORKBENCH</h6>
                            <span id="wb-proposal-id-badge" class="badge bg-secondary font-monospace" style="font-size: 11px;">#0</span>
                            <span id="wb-classification-badge" class="badge bg-light text-dark font-monospace" style="font-size: 11px;">AUTO_MATCH</span>
                            <span id="wb-lifecycle-badge" class="badge bg-light text-dark font-monospace" style="font-size: 11px;">PENDING_REVIEW</span>
                            <span id="wb-canonical-state-badge" class="badge bg-primary font-monospace" style="font-size: 11px;">READY</span>
                            <span id="wb-integrity-badge" class="badge bg-success font-monospace" style="font-size: 11px;">HEALTHY</span>
                        </div>
                        <span id="wb-natural-key" class="small text-warning font-monospace" style="font-size: 11px;">-</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-4 bg-light">
                <!-- Loading State -->
                <div id="wb-loading" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status"></div>
                    <span class="d-block small text-muted mt-2">Memuat data diagnostik proposal...</span>
                </div>

                <!-- Error State -->
                <div id="wb-error" class="alert alert-danger py-3 px-3 small my-2" style="display: none;">
                    <i class="fas fa-exclamation-triangle me-1"></i> <span id="wb-error-msg">-</span>
                </div>

                <!-- Main Content -->
                <div id="wb-content" style="display: none;">
                    <!-- Network Hierarchy Breadcrumb -->
                    <div class="card border-0 shadow-sm rounded-3 mb-3 bg-white p-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap" style="font-size: 11px;">
                            <span class="text-muted fw-bold"><i class="fas fa-sitemap text-primary me-1"></i> Hierarki:</span>
                            <span class="badge bg-light text-dark border font-monospace" id="wb-crumb-ulp">ULP SIDOARJO KOTA</span>
                            <i class="fas fa-chevron-right text-muted small" style="font-size: 9px;"></i>
                            <span class="badge bg-light text-dark border font-monospace" id="wb-crumb-feeder">BANJAR KEMANTREN</span>
                            <i class="fas fa-chevron-right text-muted small" style="font-size: 9px;"></i>
                            <span class="badge bg-light text-dark border font-monospace" id="wb-crumb-section">RECLOSER SEDATI</span>
                            <i class="fas fa-chevron-right text-muted small" style="font-size: 9px;"></i>
                            <span class="badge bg-light text-primary border font-monospace" id="wb-crumb-span">AST#1 ➔ AST#2</span>
                        </div>
                    </div>

                    <!-- Dual Asset Cards -->
                    <div class="row g-3 mb-3">
                        <!-- Source Asset Card -->
                        <div class="col-12 col-md-6">
                            <div class="card h-100 border rounded-3 shadow-sm bg-white">
                                <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
                                    <span class="small fw-bold text-uppercase text-secondary" style="font-size: 10px;">
                                        <i class="fas fa-map-pin text-success me-1"></i> [ASET ASAL] SOURCE POLE
                                    </span>
                                    <span id="wb-source-status" class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0" style="font-size: 10px;">NORMAL</span>
                                </div>
                                <div class="card-body p-3 small" style="font-size: 11px;">
                                    <div class="mb-2">
                                        <span class="text-muted d-block" style="font-size: 10px;">KODE & NAMA ASET:</span>
                                        <strong id="wb-source-code" class="text-dark font-monospace fs-6">-</strong>
                                        <span id="wb-source-name" class="d-block text-secondary">-</span>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <span class="text-muted d-block" style="font-size: 10px;">JENIS ASET:</span>
                                            <span id="wb-source-jenis" class="fw-bold text-dark">-</span>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted d-block" style="font-size: 10px;">SEKSI:</span>
                                            <span id="wb-source-section" class="fw-bold text-dark">-</span>
                                        </div>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <span class="text-muted d-block" style="font-size: 10px;">KONSTRUKSI:</span>
                                            <span id="wb-source-const" class="fw-bold text-primary font-monospace">-</span>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted d-block" style="font-size: 10px;">KOORDINAT (LAT, LNG):</span>
                                            <span id="wb-source-coords" class="fw-bold font-monospace text-dark">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Target Asset Card -->
                        <div class="col-12 col-md-6">
                            <div class="card h-100 border rounded-3 shadow-sm bg-white">
                                <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
                                    <span class="small fw-bold text-uppercase text-secondary" style="font-size: 10px;">
                                        <i class="fas fa-map-pin text-danger me-1"></i> [ASET TUJUAN] TARGET POLE
                                    </span>
                                    <span id="wb-target-status" class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0" style="font-size: 10px;">NORMAL</span>
                                </div>
                                <div class="card-body p-3 small" style="font-size: 11px;">
                                    <div class="mb-2">
                                        <span class="text-muted d-block" style="font-size: 10px;">KODE & NAMA ASET:</span>
                                        <strong id="wb-target-code" class="text-dark font-monospace fs-6">-</strong>
                                        <span id="wb-target-name" class="d-block text-secondary">-</span>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <span class="text-muted d-block" style="font-size: 10px;">JENIS ASET:</span>
                                            <span id="wb-target-jenis" class="fw-bold text-dark">-</span>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted d-block" style="font-size: 10px;">SEKSI:</span>
                                            <span id="wb-target-section" class="fw-bold text-dark">-</span>
                                        </div>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <span class="text-muted d-block" style="font-size: 10px;">KONSTRUKSI:</span>
                                            <span id="wb-target-const" class="fw-bold text-primary font-monospace">-</span>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted d-block" style="font-size: 10px;">KOORDINAT (LAT, LNG):</span>
                                            <span id="wb-target-coords" class="fw-bold font-monospace text-dark">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Proposal Specification & Evidence Inspector -->
                    <div class="row g-3 mb-3">
                        <!-- Specifications -->
                        <div class="col-12 col-md-6">
                            <div class="card h-100 border rounded-3 shadow-sm bg-white">
                                <div class="card-header bg-white py-2 border-bottom">
                                    <span class="small fw-bold text-uppercase text-secondary" style="font-size: 10px;">
                                        <i class="fas fa-bolt text-warning me-1"></i> SPESIFIKASI USULAN TRANSLINE
                                    </span>
                                </div>
                                <div class="card-body p-3 small" style="font-size: 11px;">
                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                        <span class="text-muted">• Konduktor:</span>
                                        <strong id="wb-prop-conductor" class="text-primary font-monospace">-</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                        <span class="text-muted">• Jarak Geodesic:</span>
                                        <strong id="wb-prop-distance" class="text-dark font-monospace">-</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                        <span class="text-muted">• Skor Keyakinan:</span>
                                        <strong id="wb-prop-confidence" class="text-success font-monospace">-</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                        <span class="text-muted">• Natural Key:</span>
                                        <span id="wb-prop-natural-key" class="font-monospace text-dark fw-bold">-</span>
                                    </div>
                                    <div class="d-flex justify-content-between py-1">
                                        <span class="text-muted">• Engine & Version:</span>
                                        <span id="wb-prop-engine" class="text-secondary font-monospace">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- AI Evidence Inspector -->
                        <div class="col-12 col-md-6">
                            <div class="card h-100 border rounded-3 shadow-sm bg-white">
                                <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
                                    <span class="small fw-bold text-uppercase text-secondary" style="font-size: 10px;">
                                        <i class="fas fa-shield-halved text-info me-1"></i> BUKTI DETERMINISTIK (AI SIGNAL / EVIDENCE ONLY)
                                    </span>
                                    <span class="badge bg-info-subtle text-info font-monospace" style="font-size: 9px;">ADVISORY ONLY</span>
                                </div>
                                <div class="card-body p-3 small" style="font-size: 11px;">
                                    <div class="alert alert-info py-1 px-2 mb-2 border-0 bg-info-subtle text-info-emphasis" style="font-size: 10px;">
                                        <i class="fas fa-info-circle me-1"></i> Sinyal AI adalah instrumen diagnostik pendukung, bukan persetujuan otomatis. Keputusan operasional mutlak wewenang operator.
                                    </div>
                                    <div class="row g-1 mb-2 font-monospace" style="font-size: 10px;">
                                        <div class="col-6 text-success"><i class="fas fa-check-circle me-1"></i> [✓] Satu Feeder Resmi</div>
                                        <div class="col-6 text-success"><i class="fas fa-check-circle me-1"></i> [✓] Sekuensial Valid</div>
                                        <div class="col-6 text-success"><i class="fas fa-check-circle me-1"></i> [✓] Geodesic Terukur</div>
                                        <div class="col-6 text-success"><i class="fas fa-check-circle me-1"></i> [✓] Aset Terdaftar</div>
                                    </div>
                                    <details class="mb-2">
                                        <summary class="btn btn-outline-secondary btn-sm py-0 px-2 font-monospace cursor-pointer" style="font-size: 10px;">
                                            <i class="fas fa-code me-1"></i> [ Raw Evidence JSON Toggle ]
                                        </summary>
                                        <pre class="bg-light p-2 rounded-2 border font-monospace mt-2 mb-0" style="max-height: 120px; overflow-y: auto; font-size: 9px;" id="wb-evidence-json">-</pre>
                                    </details>
                                    <div class="text-muted font-monospace" style="font-size: 10px;">
                                        <i class="fas fa-fingerprint text-secondary me-1"></i> Audit Receipt SHA-256:
                                        <span id="wb-audit-fingerprint" class="d-block font-monospace text-truncate text-primary fw-bold">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Authoritative Translines Comparison Card -->
                    <div class="card border rounded-3 shadow-sm bg-white mb-3">
                        <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <span class="small fw-bold text-uppercase text-secondary" style="font-size: 10px;">
                                    <i class="fas fa-network-wired text-primary me-1"></i> PERBANDINGAN TRANSLINE OTORITATIF (gis_translines)
                                </span>
                            </div>
                            <span id="wb-authoritative-count-badge" class="badge bg-light text-dark border" style="font-size: 10px;">0 Ditemukan</span>
                        </div>
                        <div class="card-body p-3 small" style="font-size: 11px;">
                            <div id="wb-authoritative-empty" class="text-center py-3 text-muted" style="display: none;">
                                <i class="fas fa-info-circle me-1"></i> Tidak ditemukan transline aktif yang saling terhubung pada seksi/aset ini. Usulan ini merupakan bentang baru murni.
                            </div>
                            <div id="wb-authoritative-table-container" class="table-responsive" style="max-height: 180px; overflow-y: auto;">
                                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 10px;">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>#ID</th>
                                            <th>Kode TL</th>
                                            <th>Scope</th>
                                            <th>Pjg</th>
                                            <th>Delta</th>
                                            <th>Konduktor</th>
                                            <th>Ujung Aset</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="wb-authoritative-tbody">
                                        <!-- Dynamically populated rows -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Guidance / Resolution Operator Callout -->
                    <div id="wb-guidance-container" class="card border-0 shadow-sm rounded-3 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-start gap-2">
                                <i id="wb-guidance-icon" class="fas fa-info-circle text-primary fs-5 mt-1"></i>
                                <div class="flex-fill">
                                    <h6 class="fw-bold mb-1" id="wb-guidance-title" style="font-size: 13px;">PETUNJUK RESOLUSI OPERATOR</h6>
                                    <p class="small mb-2 text-secondary" id="wb-guidance-text" style="font-size: 11px;">-</p>
                                    <div id="wb-anomalies-list" class="d-flex flex-column gap-1" style="display: none;">
                                        <!-- Dynamically populated anomalies -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Server Policy Locks (Read-Only Banner) -->
                    <div class="card border-dark bg-dark text-white rounded-3 shadow-sm mb-2">
                        <div class="card-header bg-dark py-2 border-secondary d-flex justify-content-between align-items-center">
                            <span class="small fw-bold text-uppercase text-warning font-monospace" style="font-size: 11px;">
                                <i class="fas fa-lock me-1"></i> SERVER POLICY LOCKS (READ-ONLY) — TL-01 SUB-GATE D4A
                            </span>
                            <span class="badge bg-danger font-monospace" style="font-size: 10px;">PROD WRITE: LOCKED</span>
                        </div>
                        <div class="card-body p-3 font-monospace small" style="font-size: 11px;">
                            <div class="d-flex justify-content-around text-center flex-wrap gap-2 py-2 mb-2 bg-secondary bg-opacity-25 rounded-2 border border-secondary">
                                <div><span class="text-white-50">Confirm</span> <strong class="text-danger fs-6 ms-1">[✕]</strong></div>
                                <div><span class="text-white-50">Rollback</span> <strong class="text-danger fs-6 ms-1">[✕]</strong></div>
                                <div><span class="text-white-50">Reject</span> <strong class="text-danger fs-6 ms-1">[✕]</strong></div>
                                <div><span class="text-white-50">Asset Mutation</span> <strong class="text-danger fs-6 ms-1">[✕]</strong></div>
                            </div>
                            <div class="text-white-50" style="font-size: 10px;">
                                <i class="fas fa-shield-halved text-success me-1"></i>
                                Policy: <code>POLICY_GATE_LOCKED_D4A</code> · Zero Mutations Enforced: <code>INSERT=0, UPDATE=0, DELETE=0, DDL=0</code>.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer bg-light py-2 px-3 d-flex justify-content-between align-items-center" style="font-size: 11px;">
                <span class="text-muted"><i class="fas fa-shield-halved text-success me-1"></i> TL-01 D4A Read-Only Workbench (0 DB Writes)</span>
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Tutup Workbench</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     TL-02: AI-ASSISTED JTM TRANSLINE AUTO-COMPLETION MODAL
     ======================================================== -->
<!-- ========================================================
     TL-03 & TL-02: AI-ASSISTED JTM TOPOLOGY RECONSTRUCTION MODAL
     ======================================================== -->
<div class="modal fade" id="modal-transline-ai-completion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <!-- Modal Header -->
            <div class="modal-header bg-dark text-white py-3 border-bottom border-secondary">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="rounded-circle p-2 bg-info bg-opacity-25 text-info">
                        <i class="fas fa-network-wired fs-5"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h6 class="modal-title fw-bold mb-0 text-white" style="font-size: 15px;">TL-03 &amp; TL-02 — AI JTM TOPOLOGY RECONSTRUCTION</h6>
                            <span id="ai-modal-feeder-badge" class="badge bg-primary font-monospace" style="font-size: 11px;">-</span>
                            <span class="badge bg-info text-dark font-monospace" style="font-size: 11px;">24 SAFETY GATES ACTIVE</span>
                            <span class="badge bg-success font-monospace" style="font-size: 11px;">ZERO-WRITE PROTECTED</span>
                        </div>
                        <span class="small text-muted font-monospace" style="font-size: 11px;">Rekonstruksi Topologi Jaringan &amp; Resolusi Aset Terisolasi Berbasis Spasial &amp; Multi-Evidence Reasoning</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-4 bg-light">
                <!-- Nav Tabs for TL-04, TL-03 & TL-02 -->
                <ul class="nav nav-pills mb-3 border-bottom pb-2 gap-2" id="ai-engine-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold py-2 px-3 shadow-sm rounded-pill text-white" id="tab-btn-tl04" data-bs-toggle="pill" data-bs-target="#tab-tl04" type="button" role="tab" style="font-size: 12px; background: #10b981;">
                            <i class="fas fa-project-diagram text-white me-1"></i> TL-04 Promosi Jaringan (Accelerated)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold py-2 px-3 rounded-pill text-info border border-info-subtle" id="tab-btn-tl03" data-bs-toggle="pill" data-bs-target="#tab-tl03" type="button" role="tab" style="font-size: 12px;">
                            <i class="fas fa-network-wired text-info me-1"></i> TL-03 Rekonstruksi (Strict ≥ 90)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold py-2 px-3 rounded-pill text-warning border border-warning-subtle" id="tab-btn-tl02" data-bs-toggle="pill" data-bs-target="#tab-tl02" type="button" role="tab" style="font-size: 12px;">
                            <i class="fas fa-bolt text-warning me-1"></i> TL-02 Progressive Pilot
                        </button>
                    </li>
                </ul>

                <!-- Loading State -->
                <div id="ai-loading" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status"></div>
                    <span class="d-block small text-muted mt-2">Menganalisis topologi, mendeteksi rantai spasial, &amp; mengevaluasi 24 Safety Gates...</span>
                </div>

                <!-- Alert Result / Status -->
                <div id="ai-alert-box" class="alert py-2 px-3 small my-2" style="display: none;"></div>

                <!-- Main Content Tabs -->
                <div class="tab-content" id="ai-tab-content">
                    <!-- ==============================================
                         TAB 0: TL-04 NETWORK PROMOTION ACCELERATION
                         ============================================== -->
                    <div class="tab-pane fade show active" id="tab-tl04" role="tabpanel">
                        <!-- TL-04 Metrics Summary Cards -->
                        <div class="row g-2 mb-3">
                            <div class="col-6 col-md-2">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">TOTAL ASET JTM</span>
                                    <strong id="tl04-stat-total-assets" class="fs-5 text-dark font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Penyulang Terpilih</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">TRANSLINE OTORITATIF</span>
                                    <strong id="tl04-stat-active-translines" class="fs-5 text-primary font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Baseline Aktif</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">TERKONEKSI (d ≥ 1)</span>
                                    <strong id="tl04-stat-connected" class="fs-5 text-success font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Node Jaringan Aktif</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">TERISOLASI (d = 0)</span>
                                    <strong id="tl04-stat-isolated" class="fs-5 text-danger font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Target TL-04</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">DEFENSIBEL TL-04</span>
                                    <strong id="tl04-stat-defensible" class="fs-5 text-success font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Ready to Materialize</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">PROMOSI JARINGAN</span>
                                    <strong id="tl04-stat-promoted" class="fs-5 text-success font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Score 80-89 + Coherence</span>
                                </div>
                            </div>
                        </div>

                        <!-- TL-04 Governance Notice -->
                        <div class="card border-0 shadow-sm rounded-3 mb-3 bg-white p-3 border-start border-success border-4">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fas fa-project-diagram text-success fs-5 mt-1"></i>
                                <div class="flex-fill small" style="font-size: 11px;">
                                    <strong class="text-dark d-block mb-1">TL-04 ACCELERATED RECONSTRUCTION &amp; NETWORK PROMOTION INVARIANTS:</strong>
                                    <div class="row g-1 text-muted">
                                        <div class="col-md-6"><i class="fas fa-check text-success me-1"></i> <strong>Dual-Scoring:</strong> Skor individual ≥90 ATAU 80-89 dengan bukti koherensi jaringan ≥20pt.</div>
                                        <div class="col-md-6"><i class="fas fa-check text-success me-1"></i> <strong>24 Safety Gates:</strong> Semua 24 hard gates wajib lulus 100% tanpa kompromi.</div>
                                        <div class="col-md-6"><i class="fas fa-check text-success me-1"></i> <strong>Batch Hingga 10:</strong> Materialisasi hingga 10 edge defensibel per batch dengan rekalkulasi graf dinamis.</div>
                                        <div class="col-md-6"><i class="fas fa-check text-success me-1"></i> <strong>Transaksi Independen:</strong> Setiap batch berjalan dalam transaksi atomik terisolasi (bukan monolitik).</div>
                                        <div class="col-md-6"><i class="fas fa-check text-success me-1"></i> <strong>Zero-Write Firewall:</strong> Tabel <code>assets</code> dan <code>temuan</code> 0 mutasi (Strict Read-Only).</div>
                                        <div class="col-md-6"><i class="fas fa-check text-success me-1"></i> <strong>Stabilisasi Jujur:</strong> Tidak memaksakan 205/205 jika tanpa bukti jaringan sah. Node unproven tetap terisolasi.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Candidate Batch Table -->
                        <div class="card border rounded-3 shadow-sm bg-white mb-3">
                            <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="small fw-bold text-uppercase text-secondary" style="font-size: 10px;">
                                        <i class="fas fa-list-check text-success me-1"></i> KANDIDAT BATCH TL-04 SIAP DIEKSEKUSI (MAKSIMAL 10 SEGMEN)
                                    </span>
                                </div>
                                <span id="tl04-candidate-count" class="badge bg-success font-monospace text-white" style="font-size: 10px;">0 Segmen Siap</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 11px;">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>#</th>
                                                <th>Source Asset (Titik A)</th>
                                                <th>Target Asset (Titik B)</th>
                                                <th>Jarak</th>
                                                <th>Skor Bukti</th>
                                                <th>Promosi Jaringan</th>
                                                <th>Tipe Rekonstruksi</th>
                                                <th>24 Safety Gates</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tl04-candidate-tbody">
                                            <!-- Dynamically loaded -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Diagnostics Table -->
                        <div class="card border rounded-3 shadow-sm bg-white mb-2">
                            <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="small fw-bold text-uppercase text-secondary" style="font-size: 10px;">
                                        <i class="fas fa-stethoscope text-secondary me-1"></i> DIAGNOSTIK DETERMINISTIK ASET TERISOLASI TL-04 (d = 0)
                                    </span>
                                </div>
                                <div class="d-flex gap-1" style="font-size: 9px;">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0" id="tl04-diag-auto-badge">Auto: 0</span>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-0" id="tl04-diag-high-badge">High Conf: 0</span>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0" id="tl04-diag-review-badge">Review: 0</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-0" id="tl04-diag-blocked-badge">Isolated/Blocked: 0</span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 180px; overflow-y: auto;">
                                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 10px;">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>ID Aset</th>
                                                <th>Klasifikasi Diagnostik</th>
                                                <th>Keterangan / Alasan Deterministic</th>
                                                <th>Kandidat Ditemukan</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tl04-diagnostics-tbody">
                                            <!-- Dynamically loaded -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ==============================================
                         TAB 1: TL-03 ADVANCED RECONSTRUCTION
                         ============================================== -->
                    <div class="tab-pane fade" id="tab-tl03" role="tabpanel">
                        <!-- TL-03 Metrics Summary Cards -->
                        <div class="row g-2 mb-3">
                            <div class="col-6 col-md-2">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">TOTAL ASET JTM</span>
                                    <strong id="tl03-stat-total-assets" class="fs-5 text-dark font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Penyulang Terpilih</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">TRANSLINE OTORITATIF</span>
                                    <strong id="tl03-stat-active-translines" class="fs-5 text-primary font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Baseline Aktif</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">TERKONEKSI (d ≥ 1)</span>
                                    <strong id="tl03-stat-connected" class="fs-5 text-success font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Node Jaringan Aktif</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">TERISOLASI (d = 0)</span>
                                    <strong id="tl03-stat-isolated" class="fs-5 text-danger font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Target Analisis TL-03</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">ELIGIBLE TL-03</span>
                                    <strong id="tl03-stat-auto-eligible" class="fs-5 text-info font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Skor ≥ 90 &amp; 24 Gates</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">RANTAI TERDETEKSI</span>
                                    <strong id="tl03-stat-chains" class="fs-5 text-warning font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Klaster Terisolasi</span>
                                </div>
                            </div>
                        </div>

                        <!-- TL-03 Governance Notice -->
                        <div class="card border-0 shadow-sm rounded-3 mb-3 bg-white p-3 border-start border-info border-4">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fas fa-shield-halved text-info fs-5 mt-1"></i>
                                <div class="flex-fill small" style="font-size: 11px;">
                                    <strong class="text-dark d-block mb-1">TL-03 TOPOLOGICAL RECONSTRUCTION &amp; SAFETY INVARIANTS:</strong>
                                    <div class="row g-1 text-muted">
                                        <div class="col-md-6"><i class="fas fa-check text-success me-1"></i> <strong>Batas Seksi:</strong> Seksi sama = 15pt; Batas sah berdekatan (≤55m) = 10pt; Lintas feeder diblokir.</div>
                                        <div class="col-md-6"><i class="fas fa-check text-success me-1"></i> <strong>Dekomposisi Rantai:</strong> Rantai terisolasi dipecah jadi pasangan diskrit (A-B, B-C).</div>
                                        <div class="col-md-6"><i class="fas fa-check text-success me-1"></i> <strong>Kapasitas T-Off:</strong> Percabangan d=2→3 diizinkan; node saturasi d=4 diblokir mutlak.</div>
                                        <div class="col-md-6"><i class="fas fa-check text-success me-1"></i> <strong>Kurva Jarak:</strong> 2m-15m = 25pt, 15m-55m optimal = 30pt; &gt;85m diblokir (0pt).</div>
                                        <div class="col-md-6"><i class="fas fa-check text-success me-1"></i> <strong>Zero-Write Firewall:</strong> Tabel <code>assets</code> dan <code>temuan</code> 0 mutasi (Strict Read-Only).</div>
                                        <div class="col-md-6"><i class="fas fa-check text-success me-1"></i> <strong>Diagnostik Jujur:</strong> Node tanpa relasi valid tetap terisolasi tanpa koneksi paksa.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Candidate Batch Table -->
                        <div class="card border rounded-3 shadow-sm bg-white mb-3">
                            <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="small fw-bold text-uppercase text-secondary" style="font-size: 10px;">
                                        <i class="fas fa-list-check text-info me-1"></i> KANDIDAT REKONSTRUKSI TOPOLOGI TL-03 (BATCH MAKSIMAL 10 SEGMEN)
                                    </span>
                                </div>
                                <span id="tl03-candidate-count" class="badge bg-info font-monospace text-dark" style="font-size: 10px;">0 Segmen Siap</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 11px;">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>#</th>
                                                <th>Source Asset (Titik A)</th>
                                                <th>Target Asset (Titik B)</th>
                                                <th>Jarak Spasial</th>
                                                <th>Skor Bukti</th>
                                                <th>Tipe Rekonstruksi</th>
                                                <th>24 Safety Gates</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tl03-candidate-tbody">
                                            <!-- Dynamically loaded -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Diagnostics of 101 Isolated Assets -->
                        <div class="card border rounded-3 shadow-sm bg-white mb-2">
                            <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="small fw-bold text-uppercase text-secondary" style="font-size: 10px;">
                                        <i class="fas fa-stethoscope text-secondary me-1"></i> DIAGNOSTIK DETERMINISTIK ASET TERISOLASI (d = 0)
                                    </span>
                                </div>
                                <div class="d-flex gap-1" style="font-size: 9px;">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0" id="tl03-diag-auto-badge">Auto: 0</span>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-0" id="tl03-diag-high-badge">High Conf: 0</span>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0" id="tl03-diag-review-badge">Review: 0</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-0" id="tl03-diag-blocked-badge">Isolated/Blocked: 0</span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 180px; overflow-y: auto;">
                                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 10px;">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>ID Aset</th>
                                                <th>Klasifikasi Diagnostik</th>
                                                <th>Keterangan / Alasan Deterministic</th>
                                                <th>Kandidat Ditemukan</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tl03-diagnostics-tbody">
                                            <!-- Dynamically loaded -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ==============================================
                         TAB 2: TL-02 PROGRESSIVE PILOT
                         ============================================== -->
                    <div class="tab-pane fade" id="tab-tl02" role="tabpanel">
                        <!-- TL-02 Metrics Summary Cards -->
                        <div class="row g-2 mb-3">
                            <div class="col-6 col-md-3">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">TOTAL ASET JTM</span>
                                    <strong id="ai-stat-total-assets" class="fs-5 text-dark font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Penyulang Terpilih</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">TRANSLINE OTORITATIF</span>
                                    <strong id="ai-stat-active-translines" class="fs-5 text-primary font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Aktif di Database</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">ELIGIBLE AUTO-COMPLETE</span>
                                    <strong id="ai-stat-auto-eligible" class="fs-5 text-success font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Pass 24 Gates (Conf ≥ 0.95)</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card border rounded-3 p-2 bg-white shadow-sm text-center">
                                    <span class="text-muted small" style="font-size: 10px;">BUTUH REVIEW MANUAL</span>
                                    <strong id="ai-stat-review-required" class="fs-5 text-warning font-monospace">-</strong>
                                    <span class="text-muted" style="font-size: 9px;">Exceptions / Multi-branch</span>
                                </div>
                            </div>
                        </div>

                        <!-- Pilot Batch Candidate Table -->
                        <div class="card border rounded-3 shadow-sm bg-white mb-3">
                            <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="small fw-bold text-uppercase text-secondary" style="font-size: 10px;">
                                        <i class="fas fa-list-check text-success me-1"></i> KANDIDAT PILOT AUTO-COMPLETE TL-02 (TOP 10 BATCH)
                                    </span>
                                </div>
                                <span id="ai-pilot-badge-count" class="badge bg-success font-monospace" style="font-size: 10px;">0 Segmen Siap</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 11px;">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>#</th>
                                                <th>Source Asset (Titik A)</th>
                                                <th>Target Asset (Titik B)</th>
                                                <th>Jarak Spasial</th>
                                                <th>Confidence</th>
                                                <th>Tipe Bentang</th>
                                                <th>Status 24 Gates</th>
                                            </tr>
                                        </thead>
                                        <tbody id="ai-candidate-tbody">
                                            <!-- Dynamically loaded -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer bg-light py-2 px-3 d-flex justify-content-between align-items-center" style="font-size: 11px;">
                <div id="ai-engine-footer-meta" class="text-muted font-monospace" style="font-size: 10px;">
                    <i class="fas fa-project-diagram text-success me-1"></i> Engine: TL-04 Network Promotion v1.0.0 · Dynamic Graph Batch
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" id="btn-execute-transline-tl04" class="btn text-white btn-sm rounded-pill px-4 fw-bold shadow-sm" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;">
                        <i class="fas fa-play text-warning me-1"></i> Eksekusi 1 Batch TL-04 (Maks 10 Segmen)
                    </button>
                    <button type="button" id="btn-execute-transline-tl04-loop" class="btn text-white btn-sm rounded-pill px-3 fw-bold shadow-sm" style="background: linear-gradient(135deg, #047857 0%, #065f46 100%); border: none;">
                        <i class="fas fa-sync text-warning me-1"></i> Loop Hingga Stabil
                    </button>
                    <button type="button" id="btn-execute-transline-tl03" class="btn text-white btn-sm rounded-pill px-4 fw-bold shadow-sm" style="display: none; background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%); border: none;">
                        <i class="fas fa-network-wired text-warning me-1"></i> Eksekusi Rekonstruksi TL-03 (10 Segmen)
                    </button>
                    <button type="button" id="btn-execute-transline-ai" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm" style="display: none; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); border: none;">
                        <i class="fas fa-bolt text-warning me-1"></i> Eksekusi Progressive TL-02 (10 Segmen)
                    </button>
                </div>
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
    var proposalsPreviewLayer = null;
    var proposalsData = [];
    var currentProposalFilter = 'ALL';
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
        EDIT_CONDUCTOR_SPEC: 'EDIT_CONDUCTOR_SPEC',
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

    function resolveTranslinePair(assetAId, assetBId) {
        var aId = Number(assetAId);
        var bId = Number(assetBId);
        if (!aId || !bId || aId === bId || !currentData) return null;

        var list = currentData.translines || [];
        if (list.length === 0 && currentData.transline && currentData.transline.properties && Array.isArray(currentData.transline.properties.edges)) {
            list = currentData.transline.properties.edges;
        }

        var matching = list.filter(function (tl) {
            var s = Number(tl.source_asset_id || tl.from_asset_id);
            var t = Number(tl.target_asset_id || tl.to_asset_id);
            var active = (tl.is_active === undefined || Number(tl.is_active) === 1);
            return active && ((s === aId && t === bId) || (s === bId && t === aId));
        });

        if (matching.length === 0) return null;
        return matching[0];
    }

    function setEditorState(newState, bannerText) {
        translineEditor.state = newState;
        var banner = document.getElementById('gis-mode-banner');
        var bannerLabel = document.getElementById('gis-mode-banner-text');

        if (newState === TRANSLINE_STATE.IDLE) {
            banner.style.display = 'none';
            document.getElementById('gis-segment-toolbar').style.display = 'none';
            if (previewConnectionLayer) previewConnectionLayer.clearLayers();
            if (segmentEditLayer) segmentEditLayer.clearLayers();
            if (window.activeSegmentHighlight) {
                window.activeSegmentHighlight.setStyle({ color: '#0284c7', weight: 3.5, opacity: 0.9 });
                window.activeSegmentHighlight = null;
            }
            translineEditor.sourceAsset = null;
            translineEditor.targetAsset = null;
            translineEditor.activeSegment = null;
            renderFilteredLayers(false);
        } else {
            banner.style.display = 'flex';
            if (bannerLabel && bannerText) bannerLabel.textContent = bannerText;
        }
    }

    // ========================================================
    // 📱 MOBILE TOUCH RELIABILITY & FAST POINTER TAP HANDLERS
    // ========================================================
    function logMobileTouch(data) {
        console.log(
            '[GIS MOBILE TOUCH]',
            'event=' + (data.event || '-'),
            'target=' + (data.target || '-'),
            'mode=' + ((typeof translineEditor !== 'undefined' && translineEditor.state) ? translineEditor.state : 'IDLE'),
            'action=' + (data.action || '-'),
            'assetId=' + (data.assetId || '-')
        );
    }

    function safeHideOffcanvas(elementOrId) {
        var el = (typeof elementOrId === 'string') ? document.getElementById(elementOrId) : elementOrId;
        if (!el) return;
        try {
            var inst = bootstrap.Offcanvas.getOrCreateInstance(el);
            if (inst) inst.hide();
        } catch (e) {
            console.warn('[GIS OFFCANVAS HIDE ERROR]', e);
        }
    }

    function safeShowOffcanvas(elementOrId) {
        var el = (typeof elementOrId === 'string') ? document.getElementById(elementOrId) : elementOrId;
        if (!el) return;
        try {
            var inst = bootstrap.Offcanvas.getOrCreateInstance(el);
            if (inst) inst.show();
        } catch (e) {
            console.warn('[GIS OFFCANVAS SHOW ERROR]', e);
        }
    }

    function bindPointerSafeTap(elementOrId, handler, actionName) {
        var el = (typeof elementOrId === 'string') ? document.getElementById(elementOrId) : elementOrId;
        if (!el) return;

        var lastTriggerTime = 0;

        function onTrigger(evt) {
            var now = Date.now();
            if (now - lastTriggerTime < 350) {
                if (evt) {
                    if (evt.preventDefault) evt.preventDefault();
                    if (evt.stopPropagation) evt.stopPropagation();
                }
                return;
            }
            lastTriggerTime = now;

            if (evt) {
                if (evt.stopPropagation) evt.stopPropagation();
                if (evt.cancelable && evt.type === 'touchend') evt.preventDefault();
            }

            logMobileTouch({
                event: evt ? evt.type : 'manual',
                target: el.id || (el.classList ? el.classList[0] : 'element'),
                action: actionName || '-'
            });

            handler(evt);
        }

        el.addEventListener('touchend', onTrigger, { passive: false });
        el.addEventListener('click', onTrigger);
    }

    // Isolate all GIS overlay elements from Leaflet map gesture capture
    function isolateGisUiFromLeaflet() {
        var overlayIds = [
            'asset-quick-card',
            'offcanvas-asset-transline-menu',
            'offcanvas-confirm-connection-sheet',
            'offcanvas-conductor-spec-sheet',
            'offcanvas-delete-connection-sheet',
            'offcanvas-asset-detail',
            'offcanvas-asset-edit-menu',
            'offcanvas-filter-sheet',
            'gis-mode-banner',
            'gis-segment-toolbar'
        ];

        overlayIds.forEach(function (id) {
            var el = document.getElementById(id);
            if (el && typeof L !== 'undefined' && L.DomEvent) {
                L.DomEvent.disableClickPropagation(el);
                L.DomEvent.disableScrollPropagation(el);
            }
        });
    }

    // Track active offcanvas state on body & strictly suppress Voice Assistant
    document.addEventListener('show.bs.offcanvas', function () {
        document.body.classList.add('gis-sheet-open');
        var voiceContainer = document.getElementById('global-voice-container');
        if (voiceContainer) {
            voiceContainer.style.setProperty('display', 'none', 'important');
            voiceContainer.style.setProperty('pointer-events', 'none', 'important');
        }
    });

    document.addEventListener('hidden.bs.offcanvas', function () {
        var openSheets = document.querySelectorAll('.offcanvas.show');
        if (openSheets.length === 0) {
            document.body.classList.remove('gis-sheet-open');
            var voiceContainer = document.getElementById('global-voice-container');
            if (voiceContainer && !document.body.classList.contains('gis-quickcard-active')) {
                voiceContainer.style.removeProperty('display');
                voiceContainer.style.removeProperty('pointer-events');
            }
        }
    });

    bindPointerSafeTap('btn-cancel-active-mode', function () {
        setEditorState(TRANSLINE_STATE.IDLE);
    }, 'CANCEL_MODE');

    // ========================================================
    // ⚡ GIS-01: HIGH-PERFORMANCE MULTI-FEEDER CACHE & INDEXING
    // ========================================================
    window.SIDAK_GIS_NETWORK_CACHE_BY_FEEDER = new Map();
    window.SIDAK_GIS_NETWORK_CACHE = null;
    var GIS_ICON_CACHE = new Map();
    var markerByAssetId = new Map();
    var activeNetworkRequestPromise = null;
    var activeNetworkAbortController = null;
    var currentRequestGeneration = 0;
    var gisCanvasRenderer = null;

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
    // STAGE 1: SETUP LOGIC (Unified Single Feeder Selector)
    // ========================================================
    var setupUlpSelect = document.getElementById('setup-ulp-select');
    var setupFeederSelect = document.getElementById('setup-feeder-select');
    var setupFeederLoading = document.getElementById('setup-feeder-loading');

    function loadPenyulangsForUlp(ulpId, autoSelectFirst) {
        setupFeederSelect.innerHTML = '<option value="">-- Memuat Penyulang... --</option>';
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
                        opt.textContent = p.nama_penyulang;
                        opt.dataset.feederName = p.nama_penyulang;
                        opt.dataset.ulpName = p.nama_ulp || '';
                        setupFeederSelect.appendChild(opt);

                        var optDrawer = opt.cloneNode(true);
                        drawerSelect.appendChild(optDrawer);
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
    bindPointerSafeTap('btn-setup-open-map', function () {
        var feederId = setupFeederSelect.value;
        if (!feederId) {
            alert('Silakan pilih Penyulang terlebih dahulu!');
            return;
        }

        var opt = setupFeederSelect.options[setupFeederSelect.selectedIndex];
        var isDifferentFeeder = (currentFeederId && String(currentFeederId) !== String(feederId));
        currentFeederId = feederId;
        currentFeederName = opt.dataset.feederName || opt.text;
        currentUlpName = opt.dataset.ulpName || 'PLN ULP';

        document.getElementById('topbar-feeder-title').textContent = currentFeederName;
        document.getElementById('topbar-ulp-subtitle').textContent = currentUlpName;

        document.getElementById('gis-setup-screen').style.display = 'none';
        document.getElementById('gis-workspace-screen').style.display = 'block';

        initializeMapWorkspace();

        if (isDifferentFeeder) {
            markerByAssetId.clear();
            if (markerCluster && typeof markerCluster.clearLayers === 'function') markerCluster.clearLayers();
            if (translinePolylineLayer && typeof translinePolylineLayer.clearLayers === 'function') translinePolylineLayer.clearLayers();
            if (findingLayer && typeof findingLayer.clearLayers === 'function') findingLayer.clearLayers();
            if (proposalsPreviewLayer && typeof proposalsPreviewLayer.clearLayers === 'function') proposalsPreviewLayer.clearLayers();
        }

        loadGisNetworkOnDemand(true);
    }, 'SETUP_OPEN_MAP');

    bindPointerSafeTap('btn-back-to-setup', function () {
        closeAssetQuickCard();
        setEditorState(TRANSLINE_STATE.IDLE);
        document.getElementById('gis-workspace-screen').style.display = 'none';
        document.getElementById('gis-setup-screen').style.display = 'block';
    }, 'BACK_TO_SETUP');

    bindPointerSafeTap('btn-empty-back-setup', function () {
        var backBtn = document.getElementById('btn-back-to-setup');
        if (backBtn) backBtn.click();
    }, 'EMPTY_BACK_SETUP');

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

        if (typeof L !== 'undefined' && typeof L.canvas === 'function') {
            gisCanvasRenderer = L.canvas({ padding: 0.5, tolerance: 10 });
            window.gisCanvasRenderer = gisCanvasRenderer;
        }

        translinePolylineLayer = L.featureGroup().addTo(map);
        previewConnectionLayer = L.featureGroup().addTo(map);
        segmentEditLayer = L.featureGroup().addTo(map);
        proposalsPreviewLayer = L.featureGroup().addTo(map);
        findingLayer = L.featureGroup().addTo(map);

        map.on('click', function () {
            if (translineEditor.state === TRANSLINE_STATE.IDLE) {
                closeAssetQuickCard();
            }
        });

        isolateGisUiFromLeaflet();
    }

    /**
     * TL-03: GIS Icon Resolution & Leaflet Icon Cache
     */
    var gisLeafletIconCache = {};

    function resolveAssetIconUrl(props, visual) {
        if (visual && visual.png_path) {
            return `<?= base_url() ?>${visual.png_path.replace(/^\//, '')}`;
        }
        var type   = (props.jenis_asset || props.asset_type || props.type || '').toUpperCase();
        var name   = (props.nama_asset || props.name || '').toUpperCase();
        var code   = (props.kode_asset || props.code || '').toUpperCase();
        var constr = (props.construction_type || props.konstruksi || props.construction_code || '').toUpperCase();
        var basePath = '<?= base_url('assets/gis/icons/') ?>';

        if (type === 'GARDU') {
            if (name.includes('GI') || code.includes('GI-') || name.includes('INDUK')) return basePath + 'gi.png';
            if (name.includes('PORTAL') || name.includes('GTT2') || constr.includes('2-TIANG')) {
                return basePath + (name.includes('I2') ? 'gtt2-i2.png' : 'gtt2-dist.png');
            }
            if (name.includes('I2')) return basePath + 'gtt1-i2.png';
            return basePath + 'gtt1-dist.png';
        }

        if (type === 'SWITCH' || name.includes('LBS') || name.includes('REC') || name.includes('PMCB')) {
            if (name.includes('LBSM') || name.includes('MOTOR')) return basePath + 'lbsm.png';
            if (name.includes('LBS')) return basePath + 'lbs.png';
            if (name.includes('REC') || name.includes('PMCB') || name.includes('RECLOSER')) return basePath + 'pmcb-rec.png';
            if (name.includes('FCO') || name.includes('CUTOUT') || name.includes('BRANCH')) return basePath + 'co-branch.png';
            return basePath + 'lbs.png';
        }

        // JTM Poles
        if (constr.includes('TM-11') || name.includes('TM11') || code.includes('TM11')) {
            return basePath + (constr.includes('I3') || name.includes('I3') ? 'tm11-i3.png' : 'tm11.png');
        }
        if (constr.includes('TM-10') || name.includes('TM10') || code.includes('TM10')) return basePath + 'tm10.png';
        if (constr.includes('TM-8') || name.includes('TM8') || code.includes('TM8')) return basePath + 'tm8.png';
        if (constr.includes('TM-5') || name.includes('TM5') || code.includes('TM5')) return basePath + 'tm5.png';
        if (constr.includes('TM-4') || name.includes('TM4') || code.includes('TM4')) return basePath + 'tm4.png';
        if (constr.includes('TM-2') || name.includes('TM2') || code.includes('TM2')) return basePath + 'tm2.png';
        if (constr.includes('TM-1') || name.includes('TM1') || code.includes('TM1')) return basePath + 'tm1.png';

        return (visual && visual.svg_path) ? `<?= base_url() ?>${visual.svg_path.replace(/^\//, '')}` : (basePath + 'tm1.png');
    }

    function resolveConductorPng(type, size) {
        var str = ((type || '') + ' ' + (size || '')).toUpperCase();
        var basePath = '<?= base_url('assets/gis/icons/') ?>';
        if (str.includes('240')) return (str.includes('-S') || str.includes('A3CS')) ? basePath + 'a3cs-240.png' : basePath + 'a3c-240.png';
        if (str.includes('70')) return basePath + 'a3c-70.png';
        if (str.includes('MVTIC')) return basePath + 'mvtic-150.png';
        if (str.includes('XLPE')) return basePath + 'xlpe.png';
        if (str.includes('-S') || str.includes('A3CS')) return basePath + 'a3cs-150.png';
        return basePath + 'a3c-150.png';
    }

    /**
     * Flat PNG / SVG Marker Creation with Condition Ring & Leaflet Icon Cache
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
        var assetId = props.id ? String(props.id) : null;

        var iconUrl = resolveAssetIconUrl(props, visual);
        var ringClass = overlay.ring_class || 'asset-ring-good';
        var symbolKey = visual.symbol_key || props.jenis_asset || 'ASET';

        // 1. Icon Caching: Reuse cached L.divIcon instance per icon+ring+symbol
        var iconKey = `${iconUrl}|${ringClass}|${symbolKey}`;
        var customIcon = GIS_ICON_CACHE.get(iconKey);
        if (!customIcon) {
            var iconHtml = `
                <div class="asset-network-marker-wrap" id="marker-asset-${props.id}" title="${props.nama_asset || ''} (${symbolKey})">
                    <span class="asset-condition-halo ${ringClass}"></span>
                    <img src="${iconUrl}" alt="${symbolKey}" class="asset-flat-svg" />
                </div>
            `;

            customIcon = L.divIcon({
                html: iconHtml,
                className: 'custom-gis-div-icon',
                iconSize: [44, 44],
                iconAnchor: [22, 22],
                popupAnchor: [0, -22]
            });
            GIS_ICON_CACHE.set(iconKey, customIcon);
        }

        // 2. Marker Instance Reuse: If marker for this asset already exists, reuse it!
        if (assetId && markerByAssetId.has(assetId)) {
            var existingMarker = markerByAssetId.get(assetId);
            existingMarker.setLatLng([lat, lng]);
            existingMarker.setIcon(customIcon);
            return existingMarker;
        }

        var marker = L.marker([lat, lng], { icon: customIcon });

        marker.on('click', function (e) {
            L.DomEvent.stopPropagation(e);
            if (e.originalEvent) {
                L.DomEvent.stopPropagation(e.originalEvent);
            }
            handleMarkerTap(props, iconUrl, [lng, lat]);
        });

        if (assetId) {
            markerByAssetId.set(assetId, marker);
        }

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

        logMobileTouch({
            event: 'marker_tap',
            target: 'asset_marker',
            action: translineEditor.state,
            assetId: clickedAsset.id
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

        // Case B: State is DELETE_CONNECTION -> Target Selected for Pair Deletion!
        if (translineEditor.state === TRANSLINE_STATE.DELETE_CONNECTION) {
            if (!translineEditor.sourceAsset) {
                translineEditor.sourceAsset = clickedAsset;
                setEditorState(TRANSLINE_STATE.DELETE_CONNECTION, `HAPUS JALUR: Titik 1 [${clickedAsset.nama_asset}] dipilih ➔ Sentuh tiang tujuan`);
                return;
            }

            if (translineEditor.sourceAsset.id === clickedAsset.id) {
                alert('Silakan pilih tiang kedua yang berbeda untuk menentukan jalur yang akan dihapus!');
                return;
            }

            var tl = resolveTranslinePair(translineEditor.sourceAsset.id, clickedAsset.id);
            if (!tl) {
                alert(`Tidak ditemukan sambungan jalur aktif antara ${translineEditor.sourceAsset.nama_asset} dan ${clickedAsset.nama_asset}.`);
                return;
            }

            translineEditor.targetAsset = clickedAsset;
            translineEditor.activeSegment = tl;
            openPairDeleteConfirmSheet(translineEditor.sourceAsset, translineEditor.targetAsset, tl);
            return;
        }

        // Case C: State is EDIT_CONDUCTOR_SPEC -> Target Selected for Conductor Spec Edit!
        if (translineEditor.state === TRANSLINE_STATE.EDIT_CONDUCTOR_SPEC) {
            if (!translineEditor.sourceAsset) {
                translineEditor.sourceAsset = clickedAsset;
                setEditorState(TRANSLINE_STATE.EDIT_CONDUCTOR_SPEC, `EDIT KONDUKTOR: Titik 1 [${clickedAsset.nama_asset}] dipilih ➔ Sentuh tiang kedua`);
                return;
            }

            if (translineEditor.sourceAsset.id === clickedAsset.id) {
                alert('Silakan pilih tiang kedua yang berbeda untuk menentukan jalur!');
                return;
            }

            var tl = resolveTranslinePair(translineEditor.sourceAsset.id, clickedAsset.id);
            if (!tl) {
                alert(`Tidak ditemukan sambungan jalur aktif antara ${translineEditor.sourceAsset.nama_asset} dan ${clickedAsset.nama_asset}.`);
                return;
            }

            translineEditor.targetAsset = clickedAsset;
            translineEditor.activeSegment = tl;
            openPairConductorSpecSheet(translineEditor.sourceAsset, translineEditor.targetAsset, tl);
            return;
        }

        // Case D: State is EDIT_SEGMENT_SHAPE -> Target Selected for Geometry Edit!
        if (translineEditor.state === TRANSLINE_STATE.EDIT_SEGMENT_SHAPE) {
            if (!translineEditor.sourceAsset) {
                translineEditor.sourceAsset = clickedAsset;
                setEditorState(TRANSLINE_STATE.EDIT_SEGMENT_SHAPE, `EDIT BENTUK: Titik 1 [${clickedAsset.nama_asset}] dipilih ➔ Sentuh tiang kedua`);
                return;
            }

            if (translineEditor.sourceAsset.id === clickedAsset.id) {
                alert('Silakan pilih tiang kedua yang berbeda untuk menentukan jalur!');
                return;
            }

            var tl = resolveTranslinePair(translineEditor.sourceAsset.id, clickedAsset.id);
            if (!tl) {
                alert(`Tidak ditemukan sambungan jalur aktif antara ${translineEditor.sourceAsset.nama_asset} dan ${clickedAsset.nama_asset}.`);
                return;
            }

            translineEditor.targetAsset = clickedAsset;
            translineEditor.activeSegment = tl;
            startEditPairSegmentGeometry(translineEditor.sourceAsset, translineEditor.targetAsset, tl);
            return;
        }

        // Case E: State is SELECT_SOURCE -> User picked an asset from global FAB
        if (translineEditor.state === TRANSLINE_STATE.SELECT_SOURCE) {
            setEditorState(TRANSLINE_STATE.IDLE);
            openAssetQuickCard(clickedAsset, svgPath, coords);
            openTranslineActionSheet(clickedAsset);
            return;
        }

        // MAP-02: Open Read-Only Asset Context Drawer on asset marker tap
        openAssetContextDrawer(clickedAsset.id, clickedAsset, svgPath, coords);
    }

    // ========================================================
    // 🎯 MAP-02 & MAP-02C: READ-ONLY ASSET CONTEXT DRAWER DISPATCHER
    // Deterministic Working-Context Engine with Governed BOM Preview
    // Guaranteed ZERO Master Mutation (Zero DB Writes)
    // ========================================================
    let currentAssetContextId = null;
    let currentAssetAuthoritativePenyulangId = null;
    let currentWorkingSectionId = null;
    let currentWorkingConstructionId = null;
    let cachedSectionsByFeeder = {};
    let cachedConstructionsList = null;

    function openAssetContextDrawer(assetId, fallbackProps, svgPath, coords) {
        if (!assetId || isNaN(assetId) || assetId <= 0) {
            console.warn('[MAP-02C] Invalid assetId for context drawer:', assetId);
            return;
        }

        currentAssetContextId = assetId;
        currentWorkingSectionId = null;
        currentWorkingConstructionId = null;
        currentAssetAuthoritativePenyulangId = null;

        // Reset UI selector containers
        $('#ctx-section-selector-container').hide();
        $('#ctx-const-selector-container').hide();
        $('#btn-cancel-section').hide();
        $('#btn-cancel-const').hide();

        // Initial placeholder from map props if provided
        if (fallbackProps) {
            document.getElementById('ctx-drawer-title').textContent = fallbackProps.nama_asset || ('Asset #' + assetId);
            document.getElementById('ctx-drawer-code').textContent = fallbackProps.kode_asset || ('ID: ' + assetId);
            if (svgPath) {
                document.getElementById('ctx-drawer-img').src = svgPath;
            }
        }

        safeShowOffcanvas('offcanvas-asset-context-drawer');
        fetchAssetContextWithWorking();
    }

    function fetchAssetContextWithWorking() {
        if (!currentAssetContextId) return;

        const $loading = $('#ctx-drawer-loading');
        const $error = $('#ctx-drawer-error');
        const $content = $('#ctx-drawer-content');
        const $bomTbody = $('#ctx-drawer-bom-tbody');
        const $bomContainer = $('#ctx-drawer-bom-container');
        const $bomEmpty = $('#ctx-drawer-bom-empty');
        const $constBox = $('#ctx-drawer-const-box');
        const $constEmpty = $('#ctx-drawer-const-empty');
        const $btnCreate = $('#btn-context-drawer-create-temuan');

        $loading.show();
        $error.hide();
        $content.hide();
        $bomTbody.empty();

        let reqData = {};
        if (currentWorkingSectionId) {
            reqData.working_section_id = currentWorkingSectionId;
        }
        if (currentWorkingConstructionId) {
            reqData.working_construction_id = currentWorkingConstructionId;
        }

        $.ajax({
            url: "<?= site_url('ajax/network/asset-context') ?>/" + currentAssetContextId,
            type: "GET",
            data: reqData,
            dataType: "json",
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(res) {
                $loading.hide();

                if (!res || res.status === 'INVALID_ASSET' || res.status === 'FORBIDDEN' || res.status === 'INVALID_WORKING_SECTION' || res.status === 'INVALID_WORKING_CONSTRUCTION') {
                    $error.show();
                    $('#ctx-drawer-error-msg').text(res.message || 'Konteks aset tidak valid atau akses ditolak.');
                    return;
                }

                $content.show();

                // 1. Asset Identity
                const a = res.asset || {};
                document.getElementById('ctx-drawer-title').textContent = a.nama_asset || ('Asset #' + currentAssetContextId);
                document.getElementById('ctx-drawer-code').textContent = a.kode_asset || ('ID: ' + currentAssetContextId);
                document.getElementById('ctx-drawer-jenis').textContent = a.jenis_asset || '-';
                document.getElementById('ctx-drawer-loc').textContent = a.lokasi || 'Jaringan SUTM PLN';

                const latVal = (a.latitude !== null && a.latitude !== undefined) ? Number(a.latitude).toFixed(7) : '-';
                const lngVal = (a.longitude !== null && a.longitude !== undefined) ? Number(a.longitude).toFixed(7) : '-';
                document.getElementById('ctx-drawer-coords').textContent = `${latVal}, ${lngVal}`;

                const badgeStatus = a.status || 'NORMAL';
                const $badge = $('#ctx-drawer-badge');
                $badge.text(`● ${badgeStatus}`);
                if (badgeStatus === 'NORMAL' || badgeStatus === 'GOOD') {
                    $badge.attr('class', 'badge bg-success px-2 py-1');
                } else if (badgeStatus === 'WARNING' || badgeStatus === 'ALERT') {
                    $badge.attr('class', 'badge bg-warning text-dark px-2 py-1');
                } else {
                    $badge.attr('class', 'badge bg-danger px-2 py-1');
                }

                // 2. Network Context (ULP & Feeder locked, Section correctable)
                const net = res.network || {};
                document.getElementById('ctx-drawer-ulp').textContent = (net.ulp && net.ulp.nama_ulp) ? net.ulp.nama_ulp : '-';
                document.getElementById('ctx-drawer-penyulang').textContent = (net.penyulang && net.penyulang.nama_penyulang) ? net.penyulang.nama_penyulang : '-';
                document.getElementById('ctx-drawer-section').textContent = (net.section && net.section.nama_section) ? net.section.nama_section : 'Belum Terpetakan';

                if (net.penyulang && net.penyulang.id) {
                    currentAssetAuthoritativePenyulangId = net.penyulang.id;
                }

                // Section Badge Mapping
                const secBadge = (res.status_badges && res.status_badges.section) ? res.status_badges.section : 'BELUM_TERPETAKAN';
                const $secBadgeEl = $('#ctx-drawer-section-badge');
                if (secBadge === 'DIKOREKSI_OPERATOR') {
                    $secBadgeEl.attr('class', 'badge bg-info-subtle text-info border border-info-subtle px-2 py-0').text('✎ DIKOREKSI OPERATOR');
                    $('#btn-cancel-section').show();
                } else if (secBadge === 'TERVERIFIKASI_SISTEM') {
                    $secBadgeEl.attr('class', 'badge bg-success-subtle text-success border border-success-subtle px-2 py-0').text('✓ TERVERIFIKASI SISTEM');
                    $('#btn-cancel-section').hide();
                } else {
                    $secBadgeEl.attr('class', 'badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0').text('⚠ BELUM TERPETAKAN');
                    $('#btn-cancel-section').hide();
                }

                // 3. Construction Context
                const constBadge = (res.status_badges && res.status_badges.construction) ? res.status_badges.construction : 'BELUM_TERPETAKAN';
                const $constBadgeEl = $('#ctx-drawer-const-status');

                if (constBadge === 'DIKOREKSI_OPERATOR') {
                    $constBadgeEl.attr('class', 'badge bg-info-subtle text-info border border-info-subtle px-2 py-0').text('✎ DIKOREKSI OPERATOR');
                    $('#btn-cancel-const').show();
                } else if (constBadge === 'TERVERIFIKASI_SISTEM') {
                    $constBadgeEl.attr('class', 'badge bg-success-subtle text-success border border-success-subtle px-2 py-0').text('✓ TERVERIFIKASI SISTEM');
                    $('#btn-cancel-const').hide();
                } else {
                    $constBadgeEl.attr('class', 'badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0').text('⚠ BELUM TERPETAKAN');
                    $('#btn-cancel-const').hide();
                }

                if (res.status === 'NO_CONSTRUCTION' || !res.construction) {
                    $constBox.hide();
                    $constEmpty.show();
                } else {
                    const c = res.construction;
                    $constEmpty.hide();
                    $constBox.show();
                    document.getElementById('ctx-drawer-const-code').textContent = c.code || '-';
                    document.getElementById('ctx-drawer-const-name').textContent = c.name || '-';
                    document.getElementById('ctx-drawer-const-sub').textContent = `${c.construction_family || 'JTM'} • ${c.voltage_level || '20kV'}`;
                }

                // 4. Governed BOM Catalog Preview (Strict Read-Only)
                if (res.status === 'NO_BOM' || !Array.isArray(res.bom) || res.bom.length === 0) {
                    $bomContainer.hide();
                    $bomEmpty.show();
                } else {
                    $bomEmpty.hide();
                    $bomContainer.show();
                    let bomHtml = '';
                    res.bom.forEach(function(m) {
                        const code = m.material_code || m.raw_material_code || '-';
                        const name = m.nama_material || m.nama_lapangan || m.raw_material_name || '-';
                        const unit = m.satuan || m.unit || 'SET';
                        bomHtml += `<tr>
                            <td class="font-monospace text-primary fw-bold">${code}</td>
                            <td class="text-dark">${name}</td>
                            <td class="text-center font-monospace text-muted">${unit}</td>
                        </tr>`;
                    });
                    $bomTbody.html(bomHtml);
                }

                // 5. Navigation Link (Context Handoff Only)
                const navUrl = (res.navigation && res.navigation.create_temuan_url) ? res.navigation.create_temuan_url : `<?= site_url('temuan/create') ?>?asset_id=${currentAssetContextId}`;
                $btnCreate.attr('href', navUrl);
            },
            error: function(xhr) {
                $loading.hide();
                $error.show();
                let errMsg = 'Gagal memuat konteks aset dari server.';
                try {
                    const errData = JSON.parse(xhr.responseText);
                    if (errData && errData.message) errMsg = errData.message;
                } catch (e) {}
                $('#ctx-drawer-error-msg').text(errMsg);
            }
        });
    }

    // MAP-02C Section Correction Event Handlers
    $(document).on('click', '#btn-correct-section', function() {
        const $container = $('#ctx-section-selector-container');
        if ($container.is(':visible')) {
            $container.slideUp(150);
            return;
        }

        if (!currentAssetAuthoritativePenyulangId) {
            alert('Penyulang aset belum teridentifikasi.');
            return;
        }

        $container.slideDown(150);
        loadFeederSectionsList(currentAssetAuthoritativePenyulangId);
    });

    $(document).on('click', '#btn-cancel-section', function() {
        currentWorkingSectionId = null;
        $('#ctx-section-selector-container').slideUp(150);
        $('#btn-cancel-section').hide();
        fetchAssetContextWithWorking();
    });

    function loadFeederSectionsList(penyulangId) {
        const $list = $('#section-options-list');
        $list.html('<div class="p-3 text-center text-muted small"><i class="fas fa-spinner fa-spin me-1"></i>Memuat daftar section...</div>');

        if (cachedSectionsByFeeder[penyulangId]) {
            renderSectionOptions(cachedSectionsByFeeder[penyulangId]);
            return;
        }

        $.ajax({
            url: "<?= site_url('ajax/network/section') ?>/" + penyulangId,
            type: "GET",
            dataType: "json",
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(sections) {
                cachedSectionsByFeeder[penyulangId] = sections || [];
                renderSectionOptions(cachedSectionsByFeeder[penyulangId]);
            },
            error: function() {
                $list.html('<div class="p-2 text-danger small">Gagal memuat daftar section.</div>');
            }
        });
    }

    function renderSectionOptions(sections) {
        const $list = $('#section-options-list');
        if (!sections || sections.length === 0) {
            $list.html('<div class="p-3 text-center text-muted small">Tidak ada section pada penyulang ini.</div>');
            return;
        }

        let html = '';
        sections.forEach(function(sec) {
            const secName = sec.nama_section || sec.name || ('Section #' + sec.id);
            const isSelected = (currentWorkingSectionId && currentWorkingSectionId == sec.id);
            html += `<button type="button" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center btn-select-section-item" data-id="${sec.id}" data-name="${secName}" style="min-height: 44px; font-size: 12px;">
                <span class="fw-bold ${isSelected ? 'text-primary' : 'text-dark'}">${secName}</span>
                <span class="badge ${isSelected ? 'bg-primary' : 'bg-light text-muted border'} px-2 py-1">Pilih</span>
            </button>`;
        });
        $list.html(html);
    }

    $(document).on('click', '.btn-select-section-item', function() {
        const secId = $(this).data('id');
        const secName = $(this).data('name') || ('Section #' + secId);

        // Render State B: Confirmation Box inside container
        $('#section-confirm-box').remove();
        const confirmHtml = `
            <div id="section-confirm-box" class="p-2 mt-2 bg-white border border-primary rounded-3 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-bold text-primary"><i class="fas fa-save me-1"></i> Konfirmasi Koreksi Section</span>
                    <span class="badge bg-primary-subtle text-primary" style="font-size: 9px;">Menunggu Persetujuan</span>
                </div>
                <p class="small text-dark mb-2" style="font-size: 11px;">Simpan section baru ke database: <strong>${secName}</strong>?</p>
                <div id="section-save-error" class="alert alert-danger py-1 px-2 small mb-2" style="display: none; font-size: 11px;"></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary w-50 py-1" id="btn-cancel-section-confirm" style="font-size: 11px;">Batal</button>
                    <button type="button" class="btn btn-sm btn-primary w-50 fw-bold py-1" id="btn-commit-section-correction" data-id="${secId}" style="font-size: 11px;">
                        <i class="fas fa-check me-1"></i> Simpan ke DB
                    </button>
                </div>
            </div>`;
        $('#section-options-list').after(confirmHtml);
    });

    $(document).on('click', '#btn-cancel-section-confirm', function() {
        $('#section-confirm-box').remove();
    });

    $(document).on('click', '#btn-commit-section-correction', function() {
        const secId = $(this).data('id');
        const $btn = $(this);
        const $cancelBtn = $('#btn-cancel-section-confirm');
        const $errBox = $('#section-save-error');

        // State C: Saving
        $btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin me-1"></i>Menyimpan...');
        $cancelBtn.prop('disabled', true);
        $errBox.hide();

        $.ajax({
            url: "<?= site_url('ajax/network/correct-section') ?>",
            type: "POST",
            data: JSON.stringify({
                asset_id: currentAssetContextId,
                section_id: parseInt(secId, 10),
                reason: 'Koreksi Section Operator via GIS Context Drawer'
            }),
            contentType: "application/json",
            dataType: "json",
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(res) {
                // State D: Saved / Persisted
                currentWorkingSectionId = null;
                $('#ctx-section-selector-container').slideUp(150);
                $('#section-confirm-box').remove();
                fetchAssetContextWithWorking();
            },
            error: function(xhr) {
                // State E: Failed
                $btn.prop('disabled', false).html('<i class="fas fa-check me-1"></i> Coba Simpan Lagi');
                $cancelBtn.prop('disabled', false);
                let errMsg = 'Gagal menyimpan koreksi section ke database.';
                try {
                    const err = JSON.parse(xhr.responseText);
                    if (err && err.message) errMsg = err.message;
                } catch(e) {}
                $errBox.text(errMsg).show();
            }
        });
    });

    $(document).on('keyup', '#input-search-section', function() {
        const q = $(this).val().toLowerCase().trim();
        $('#section-options-list .btn-select-section-item').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(q) > -1);
        });
    });

    // MAP-02C Construction Correction Event Handlers
    $(document).on('click', '#btn-correct-const', function() {
        const $container = $('#ctx-const-selector-container');
        if ($container.is(':visible')) {
            $container.slideUp(150);
            return;
        }

        $container.slideDown(150);
        loadConstructionsList();
    });

    $(document).on('click', '#btn-cancel-const', function() {
        currentWorkingConstructionId = null;
        $('#ctx-const-selector-container').slideUp(150);
        $('#btn-cancel-const').hide();
        fetchAssetContextWithWorking();
    });

    function loadConstructionsList() {
        const $list = $('#const-options-list');
        $list.html('<div class="p-3 text-center text-muted small"><i class="fas fa-spinner fa-spin me-1"></i>Memuat standar konstruksi...</div>');

        if (cachedConstructionsList) {
            renderConstOptions(cachedConstructionsList);
            return;
        }

        $.ajax({
            url: "<?= site_url('ajax/network/constructions') ?>",
            type: "GET",
            dataType: "json",
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(constructions) {
                cachedConstructionsList = constructions || [];
                renderConstOptions(cachedConstructionsList);
            },
            error: function() {
                $list.html('<div class="p-2 text-danger small">Gagal memuat standar konstruksi.</div>');
            }
        });
    }

    function renderConstOptions(constructions) {
        const $list = $('#const-options-list');
        if (!constructions || constructions.length === 0) {
            $list.html('<div class="p-3 text-center text-muted small">Tidak ada standar konstruksi tersedia.</div>');
            return;
        }

        let html = '';
        constructions.forEach(function(c) {
            const isSelected = (currentWorkingConstructionId && currentWorkingConstructionId == c.id);
            html += `<button type="button" class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center btn-select-const-item" data-id="${c.id}" data-code="${c.code || ''}" data-name="${c.name || ''}" style="min-height: 44px; font-size: 12px;">
                <div>
                    <span class="badge bg-primary font-monospace me-1">${c.code}</span>
                    <span class="fw-bold text-dark">${c.name}</span>
                    <span class="text-muted d-block" style="font-size: 10px;">${c.construction_family || 'JTM'} • ${c.voltage_level || '20kV'}</span>
                </div>
                <span class="badge ${isSelected ? 'bg-primary' : 'bg-light text-muted border'} px-2 py-1">Pilih</span>
            </button>`;
        });
        $list.html(html);
    }

    $(document).on('click', '.btn-select-const-item', function() {
        const constId = $(this).data('id');
        const constCode = $(this).data('code') || '';
        const constName = $(this).data('name') || '';

        $('#const-confirm-box').remove();
        const confirmHtml = `
            <div id="const-confirm-box" class="p-2 mt-2 bg-white border border-warning rounded-3 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-bold text-dark"><i class="fas fa-save text-warning me-1"></i> Konfirmasi Koreksi Konstruksi</span>
                    <span class="badge bg-warning-subtle text-dark" style="font-size: 9px;">Menunggu Persetujuan</span>
                </div>
                <p class="small text-dark mb-2" style="font-size: 11px;">Simpan standar konstruksi baru ke database: <strong>${constCode} — ${constName}</strong>?</p>
                <div id="const-save-error" class="alert alert-danger py-1 px-2 small mb-2" style="display: none; font-size: 11px;"></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary w-50 py-1" id="btn-cancel-const-confirm" style="font-size: 11px;">Batal</button>
                    <button type="button" class="btn btn-sm btn-warning text-dark w-50 fw-bold py-1" id="btn-commit-const-correction" data-id="${constId}" style="font-size: 11px;">
                        <i class="fas fa-check me-1"></i> Simpan ke DB
                    </button>
                </div>
            </div>`;
        $('#const-options-list').after(confirmHtml);
    });

    $(document).on('click', '#btn-cancel-const-confirm', function() {
        $('#const-confirm-box').remove();
    });

    $(document).on('click', '#btn-commit-const-correction', function() {
        const constId = $(this).data('id');
        const $btn = $(this);
        const $cancelBtn = $('#btn-cancel-const-confirm');
        const $errBox = $('#const-save-error');

        // State C: Saving
        $btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin me-1"></i>Menyimpan...');
        $cancelBtn.prop('disabled', true);
        $errBox.hide();

        $.ajax({
            url: "<?= site_url('ajax/network/correct-construction') ?>",
            type: "POST",
            data: JSON.stringify({
                asset_id: currentAssetContextId,
                construction_type_id: parseInt(constId, 10),
                reason: 'Koreksi Konstruksi Operator via GIS Context Drawer'
            }),
            contentType: "application/json",
            dataType: "json",
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(res) {
                // State D: Saved / Persisted
                currentWorkingConstructionId = null;
                $('#ctx-const-selector-container').slideUp(150);
                $('#const-confirm-box').remove();
                fetchAssetContextWithWorking();
            },
            error: function(xhr) {
                // State E: Failed
                $btn.prop('disabled', false).html('<i class="fas fa-check me-1"></i> Coba Simpan Lagi');
                $cancelBtn.prop('disabled', false);
                let errMsg = 'Gagal menyimpan koreksi konstruksi ke database.';
                try {
                    const err = JSON.parse(xhr.responseText);
                    if (err && err.message) errMsg = err.message;
                } catch(e) {}
                $errBox.text(errMsg).show();
            }
        });
    });

    $(document).on('keyup', '#input-search-const', function() {
        const q = $(this).val().toLowerCase().trim();
        $('#const-options-list .btn-select-const-item').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(q) > -1);
        });
    });

    // ========================================================
    // 1️⃣ COMPACT ASSET QUICK CARD LOGIC & SVG RESOLVER
    // ========================================================
    function resolveAssetSvgPath(props, visual) {
        if (visual && visual.svg_path) {
            return `<?= base_url() ?>${visual.svg_path}`;
        }
        if (props && props._svgPath) {
            return props._svgPath;
        }
        return '<?= base_url('/assets/icons/network/generic-network-asset.svg') ?>';
    }

    function openAssetQuickCard(props, svgPath, coords) {
        if (!svgPath) {
            svgPath = resolveAssetSvgPath(props, null);
        }
        activeAssetProps = Object.assign({}, props);
        activeAssetProps._svgPath = svgPath;
        activeAssetProps._coords = coords;
        activeAssetProps.latitude = (props.latitude !== undefined && props.latitude !== null && isValidLatLng(props.latitude, 0)) ? Number(props.latitude) : (coords ? Number(coords[1]) : null);
        activeAssetProps.longitude = (props.longitude !== undefined && props.longitude !== null && isValidLatLng(0, props.longitude)) ? Number(props.longitude) : (coords ? Number(coords[0]) : null);

        document.getElementById('quick-card-img').src = svgPath;
        document.getElementById('quick-card-code').textContent = props.kode_asset || '-';
        document.getElementById('quick-card-name').textContent = props.nama_asset || '-';
        
        var isUnassigned = (props.asset_scope === 'ULP_UNASSIGNED');
        var badge = document.getElementById('quick-card-badge');
        if (isUnassigned) {
            badge.textContent = '● BELUM TERHUBUNG KE PENYULANG';
            badge.className = 'badge bg-warning text-dark font-monospace';
        } else {
            badge.textContent = `● ${props.status || 'GOOD'}`;
            badge.className = `badge ${(props.condition_overlay && props.condition_overlay.badge_class) || 'bg-success'}`;
        }
        
        document.getElementById('quick-card-type').textContent = (props.construction_type || props.type || 'TM') + (isUnassigned ? ' (Master ULP)' : '');
        document.getElementById('quick-card-jenis').textContent = props.jenis_asset || 'JTM';

        document.getElementById('asset-quick-card').style.display = 'block';
        document.body.classList.add('gis-quickcard-active');
    }

    window.closeAssetQuickCard = function () {
        document.getElementById('asset-quick-card').style.display = 'none';
        document.body.classList.remove('gis-quickcard-active');
    };

    // Quick Action 1: Open Full Context Drawer
    bindPointerSafeTap('btn-quick-detail', function () {
        if (!activeAssetProps) return;
        closeAssetQuickCard();
        openAssetContextDrawer(activeAssetProps.id, activeAssetProps, activeAssetProps._svgPath, activeAssetProps._coords);
    }, 'QUICK_DETAIL');

    // Quick Action 2: Open Edit Parameter Sheet
    bindPointerSafeTap('btn-quick-edit-sheet', function () {
        if (!activeAssetProps) return;
        closeAssetQuickCard();
        safeShowOffcanvas('offcanvas-asset-edit-menu');
    }, 'QUICK_EDIT');

    // Quick Action 3: Open Asset-Anchored Transline Action Sheet (Jalur)
    bindPointerSafeTap('btn-quick-transline-menu', function () {
        if (!activeAssetProps) return;
        closeAssetQuickCard();
        openTranslineActionSheet(activeAssetProps);
    }, 'QUICK_TRANSLINE_MENU');

    function openTranslineActionSheet(assetProps) {
        translineEditor.sourceAsset = assetProps;
        document.getElementById('transline-sheet-subtitle').textContent = `${assetProps.kode_asset || ''} - ${assetProps.nama_asset || ''}`;
        safeShowOffcanvas('offcanvas-asset-transline-menu');
    }

    // ========================================================
    // 🔀 WORKFLOW 1: UBAH KONEKSI ASET
    // ========================================================
    bindPointerSafeTap('act-change-connection', function () {
        safeHideOffcanvas('offcanvas-asset-transline-menu');
        setEditorState(TRANSLINE_STATE.CHANGE_CONNECTION, `SENTUH TIANG TUJUAN KONEKSI (Sumber: ${translineEditor.sourceAsset ? translineEditor.sourceAsset.nama_asset : ''})`);
    }, 'ACTION_CHANGE_CONNECTION');

    // ========================================================
    // 🔀 WORKFLOW 2: TAMBAH SAMBUNGAN
    // ========================================================
    bindPointerSafeTap('act-add-connection', function () {
        safeHideOffcanvas('offcanvas-asset-transline-menu');
        setEditorState(TRANSLINE_STATE.ADD_CONNECTION, `SENTUH TIANG TUJUAN SAMBUNGAN BARU (Sumber: ${translineEditor.sourceAsset ? translineEditor.sourceAsset.nama_asset : ''})`);
    }, 'ACTION_ADD_CONNECTION');

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

        safeShowOffcanvas('offcanvas-confirm-connection-sheet');
    }

    bindPointerSafeTap('btn-submit-connection', function () {
        if (!translineEditor.sourceAsset || !translineEditor.targetAsset) return;

        var mode = (translineEditor.state === TRANSLINE_STATE.CHANGE_CONNECTION) ? 'REPLACE' : 'ADD';
        var cType = document.getElementById('conn-conductor-type').value;
        var cSize = document.getElementById('conn-conductor-size').value;

        var payload = {
            penyulang_id: currentFeederId,
            source_asset_id: translineEditor.sourceAsset.id,
            target_asset_id: translineEditor.targetAsset.id,
            connection_mode: mode,
            conductor_type: cType,
            conductor_size: cSize,
            conductor_material: (cType === 'XLPE') ? 'COPPER_XLPE' : ((cType === 'ACSR') ? 'ALUMINUM_STEEL' : 'ALUMINUM_ALLOY'),
            installation_type: (cType === 'XLPE') ? 'UNDERGROUND' : ((cType === 'A3CS') ? 'OVERHEAD_INSULATED' : 'OVERHEAD')
        };

        var submitBtn = document.getElementById('btn-submit-connection');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';
        }

        fetchJson('<?= site_url('gis/api-connect-topology') ?>', {
            method: 'POST',
            body: JSON.stringify(payload)
        })
        .then(res => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check me-1"></i> <?= !empty($isAdmin) ? 'Terapkan Langsung' : 'Kirim Usulan' ?>';
            }

            safeHideOffcanvas('offcanvas-confirm-connection-sheet');
            setEditorState(TRANSLINE_STATE.IDLE);

            // Reconcile and redraw GIS layer immediately
            if (currentData) {
                if (res.translines) currentData.translines = res.translines;
                if (res.topology) {
                    currentData.transline = {
                        type: 'Feature',
                        geometry: {
                            type: res.topology.type || 'MultiLineString',
                            coordinates: res.topology.coordinates || []
                        },
                        properties: {
                            edges: res.topology.edges || [],
                            nodes: res.topology.nodes || []
                        }
                    };
                }
                renderFilteredLayers(false);
                alert(res.message);
            } else {
                loadGisNetworkOnDemand(false, function() {
                    alert(res.message);
                });
            }
        })
        .catch(err => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check me-1"></i> <?= !empty($isAdmin) ? 'Terapkan Langsung' : 'Kirim Usulan' ?>';
            }
            alert('Gagal memperbarui koneksi: ' + err.message);
        });
    }, 'SUBMIT_CONNECTION');

    // ========================================================
    // ⚡ WORKFLOW 3: SPESIFIKASI KONDUKTOR SEGMEN (Pair A -> B)
    // ========================================================
    bindPointerSafeTap('act-edit-conductor-spec', function () {
        safeHideOffcanvas('offcanvas-asset-transline-menu');
        setEditorState(TRANSLINE_STATE.EDIT_CONDUCTOR_SPEC, `EDIT KONDUKTOR: Titik 1 [${translineEditor.sourceAsset ? translineEditor.sourceAsset.nama_asset : ''}] dipilih ➔ Sentuh tiang kedua`);
    }, 'ACTION_EDIT_CONDUCTOR_SPEC');

    function openPairConductorSpecSheet(sourceAsset, targetAsset, tl) {
        document.getElementById('spec-source-name').textContent = `${sourceAsset.nama_asset} (${sourceAsset.kode_asset || ''})`;
        document.getElementById('spec-target-name').textContent = `${targetAsset.nama_asset} (${targetAsset.kode_asset || ''})`;
        document.getElementById('spec-transline-id').value = tl.id || tl.transline_id || '';
        document.getElementById('spec-source-id').value = sourceAsset.id;
        document.getElementById('spec-target-id').value = targetAsset.id;

        document.getElementById('spec-conductor-type').value = tl.conductor_type || 'AAAC';
        document.getElementById('spec-conductor-size').value = tl.conductor_size || '150 mm²';

        // Highlight segment in yellow on map
        var tId = tl.id || tl.transline_id;
        if (window.translineLayers && window.translineLayers.has(tId)) {
            var poly = window.translineLayers.get(tId);
            if (window.activeSegmentHighlight) {
                window.activeSegmentHighlight.setStyle({ color: '#0284c7', weight: 3.5, opacity: 0.9 });
            }
            window.activeSegmentHighlight = poly;
            poly.setStyle({ color: '#f59e0b', weight: 6.0, opacity: 1 });
        }

        safeShowOffcanvas('offcanvas-conductor-spec-sheet');
    }

    bindPointerSafeTap('btn-submit-conductor-spec', function () {
        var tId = Number(document.getElementById('spec-transline-id').value);
        var sourceId = Number(document.getElementById('spec-source-id').value);
        var targetId = Number(document.getElementById('spec-target-id').value);
        var cType = document.getElementById('spec-conductor-type').value;
        var cSize = document.getElementById('spec-conductor-size').value;

        if (!sourceId || !targetId) {
            alert('Titik sambungan tidak valid.');
            return;
        }

        var submitBtn = document.getElementById('btn-submit-conductor-spec');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';
        }

        fetchJson('<?= site_url('gis/api-update-conductor') ?>', {
            method: 'POST',
            body: JSON.stringify({
                transline_id: tId,
                penyulang_id: currentFeederId,
                source_asset_id: sourceId,
                target_asset_id: targetId,
                conductor_type: cType,
                conductor_size: cSize,
                conductor_material: (cType === 'XLPE') ? 'COPPER_XLPE' : ((cType === 'ACSR') ? 'ALUMINUM_STEEL' : 'ALUMINUM_ALLOY')
            })
        })
        .then(res => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check me-1"></i> Simpan Langsung';
            }
            safeHideOffcanvas('offcanvas-conductor-spec-sheet');
            setEditorState(TRANSLINE_STATE.IDLE);
            if (currentData) {
                if (res.translines) currentData.translines = res.translines;
                if (res.topology) {
                    currentData.transline = {
                        type: 'Feature',
                        geometry: {
                            type: res.topology.type || 'MultiLineString',
                            coordinates: res.topology.coordinates || []
                        },
                        properties: {
                            edges: res.topology.edges || [],
                            nodes: res.topology.nodes || []
                        }
                    };
                }
                renderFilteredLayers(false);
                alert(res.message);
            } else {
                loadGisNetworkOnDemand(false, function() {
                    alert(res.message);
                });
            }
        })
        .catch(err => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check me-1"></i> Simpan Langsung';
            }
            alert('Gagal: ' + err.message);
        });
    }, 'SUBMIT_CONDUCTOR_SPEC');

    // ========================================================
    // 🗑 WORKFLOW 4: HAPUS JALUR SEGMEN (Pair A -> B)
    // ========================================================
    bindPointerSafeTap('act-delete-connection', function () {
        safeHideOffcanvas('offcanvas-asset-transline-menu');
        setEditorState(TRANSLINE_STATE.DELETE_CONNECTION, `HAPUS JALUR: Titik 1 [${translineEditor.sourceAsset ? translineEditor.sourceAsset.nama_asset : ''}] dipilih ➔ Sentuh tiang tujuan`);
    }, 'ACTION_DELETE_CONNECTION');

    function openPairDeleteConfirmSheet(sourceAsset, targetAsset, tl) {
        document.getElementById('del-source-name').textContent = `${sourceAsset.nama_asset} (${sourceAsset.kode_asset || ''})`;
        document.getElementById('del-target-name').textContent = `${targetAsset.nama_asset} (${targetAsset.kode_asset || ''})`;
        document.getElementById('del-segment-code').textContent = tl.transline_code || `TL-${currentFeederId}-${tl.id}`;
        document.getElementById('del-distance-meters').textContent = `${tl.distance_meters || tl.length_meter || 0} m`;
        document.getElementById('del-conductor-spec').textContent = `${tl.conductor_type || 'AAAC'} ${tl.conductor_size || '150 mm²'}`;

        document.getElementById('del-transline-id').value = tl.id || tl.transline_id || '';
        document.getElementById('del-source-id').value = sourceAsset.id;
        document.getElementById('del-target-id').value = targetAsset.id;

        // Highlight segment in red on map
        var tId = tl.id || tl.transline_id;
        if (window.translineLayers && window.translineLayers.has(tId)) {
            var poly = window.translineLayers.get(tId);
            if (window.activeSegmentHighlight) {
                window.activeSegmentHighlight.setStyle({ color: '#0284c7', weight: 3.5, opacity: 0.9 });
            }
            window.activeSegmentHighlight = poly;
            poly.setStyle({ color: '#ef4444', weight: 6.0, opacity: 1 });
        }

        safeShowOffcanvas('offcanvas-delete-connection-sheet');
    }

    bindPointerSafeTap('btn-confirm-delete-pair', function () {
        var tId = Number(document.getElementById('del-transline-id').value);
        var sourceId = Number(document.getElementById('del-source-id').value);
        var targetId = Number(document.getElementById('del-target-id').value);

        var confirmBtn = document.getElementById('btn-confirm-delete-pair');
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menghapus...';
        }

        fetchJson('<?= site_url('gis/api-disconnect-topology') ?>', {
            method: 'POST',
            body: JSON.stringify({
                transline_id: tId,
                penyulang_id: currentFeederId,
                source_asset_id: sourceId,
                target_asset_id: targetId
            })
        })
        .then(res => {
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fas fa-trash-alt me-1"></i> Hapus Jalur';
            }
            safeHideOffcanvas('offcanvas-delete-connection-sheet');
            setEditorState(TRANSLINE_STATE.IDLE);

            // Directly remove the specific segment from Leaflet map without full wipe
            if (tId && window.translineLayers && window.translineLayers.has(tId)) {
                var layer = window.translineLayers.get(tId);
                if (translinePolylineLayer) translinePolylineLayer.removeLayer(layer);
                window.translineLayers.delete(tId);
            }

            if (currentData) {
                if (res.translines) {
                    currentData.translines = res.translines;
                } else if (tId && Array.isArray(currentData.translines)) {
                    currentData.translines = currentData.translines.filter(t => (t.id !== tId && t.transline_id !== tId));
                }
                if (res.topology) {
                    currentData.transline = {
                        type: 'Feature',
                        geometry: {
                            type: res.topology.type || 'MultiLineString',
                            coordinates: res.topology.coordinates || []
                        },
                        properties: {
                            edges: res.topology.edges || [],
                            nodes: res.topology.nodes || []
                        }
                    };
                }
                renderFilteredLayers(false);
            }

            alert(res.message || 'Jalur berhasil diputus.');
        })
        .catch(err => {
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="fas fa-trash-alt me-1"></i> Hapus Jalur';
            }
            alert('Gagal menghapus jalur: ' + err.message);
        });
    }, 'CONFIRM_DELETE_PAIR');

    // ========================================================
    // ✏ WORKFLOW 5: EDIT BENTUK JALUR SEGMEN (Pair A -> B)
    // ========================================================
    bindPointerSafeTap('act-edit-segment-shape', function () {
        safeHideOffcanvas('offcanvas-asset-transline-menu');
        setEditorState(TRANSLINE_STATE.EDIT_SEGMENT_SHAPE, `EDIT BENTUK: Titik 1 [${translineEditor.sourceAsset ? translineEditor.sourceAsset.nama_asset : ''}] dipilih ➔ Sentuh tiang kedua`);
    }, 'ACTION_EDIT_SEGMENT_SHAPE');

    function startEditPairSegmentGeometry(sourceAsset, targetAsset, tl) {
        if (!segmentEditLayer) return;
        segmentEditLayer.clearLayers();

        var rawCoords = tl.coordinates || tl.geometry;
        if (typeof rawCoords === 'string') {
            try { rawCoords = JSON.parse(rawCoords); } catch (e) { rawCoords = []; }
        }

        var vertices = [];
        if (Array.isArray(rawCoords) && rawCoords.length >= 2) {
            vertices = rawCoords.map(pt => [pt[1], pt[0]]);
        } else {
            vertices = [
                [sourceAsset.latitude, sourceAsset.longitude],
                [targetAsset.latitude, targetAsset.longitude]
            ];
        }

        translineEditor.activeSegment = tl;
        translineEditor.targetAsset = targetAsset;
        translineEditor.editedVertices = vertices;
        translineEditor.undoStack = [JSON.parse(JSON.stringify(vertices))];

        setEditorState(TRANSLINE_STATE.EDIT_SEGMENT_SHAPE, `EDIT BENTUK SEGMEN (${sourceAsset.nama_asset} ➔ ${targetAsset.nama_asset})`);
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
            var isEndpoint = (idx === 0 || idx === vertices.length - 1);
            var handle = L.circleMarker(pt, {
                radius: isEndpoint ? 11 : 9,
                fillColor: isEndpoint ? '#2563eb' : '#10b981',
                color: '#ffffff',
                weight: 3,
                fillOpacity: 1
            });

            var isDragging = false;
            
            function resolvePointerLatLng(e) {
                if (e.latlng) return e.latlng;
                if (e.originalEvent && e.originalEvent.touches && e.originalEvent.touches[0]) {
                    return map.mouseEventToLatLng(e.originalEvent.touches[0]);
                }
                return null;
            }

            handle.on('mousedown touchstart', function (e) {
                isDragging = true;
                map.dragging.disable();
            });

            map.on('mousemove touchmove', function (e) {
                if (isDragging) {
                    var ll = resolvePointerLatLng(e);
                    if (ll && isValidLatLng(ll.lat, ll.lng)) {
                        handle.setLatLng(ll);
                        vertices[idx] = [ll.lat, ll.lng];
                        poly.setLatLngs(vertices);
                    }
                }
            });

            map.on('mouseup touchend', function () {
                if (isDragging) {
                    isDragging = false;
                    map.dragging.enable();
                    translineEditor.undoStack.push(JSON.parse(JSON.stringify(vertices)));
                }
            });

            if (!isEndpoint) {
                // Mobile-friendly Up/Down/Nudge vertex control popup
                var upDisabled = (idx <= 1) ? 'disabled' : '';
                var downDisabled = (idx >= vertices.length - 2) ? 'disabled' : '';

                handle.bindPopup(`
                    <div class="p-1 text-center" style="font-size: 11px; min-width: 140px;">
                        <strong class="d-block mb-1 text-dark">Titik Lekukan #${idx}</strong>
                        <div class="btn-group btn-group-sm w-100 mb-2">
                            <button class="btn btn-sm btn-outline-primary py-0" ${upDisabled} onclick="moveVertex(${idx}, -1)" title="Pindah Urutan Naik">↑</button>
                            <button class="btn btn-sm btn-outline-primary py-0" ${downDisabled} onclick="moveVertex(${idx}, 1)" title="Pindah Urutan Turun">↓</button>
                            <button class="btn btn-sm btn-outline-danger py-0" onclick="deleteVertex(${idx})" title="Hapus Titik">🗑</button>
                        </div>
                        <div class="d-flex justify-content-center gap-1">
                            <button class="btn btn-light btn-sm border px-2 py-0" onclick="nudgeVertex(${idx}, 0.0001, 0)" title="Geser Utara">⬆</button>
                            <button class="btn btn-light btn-sm border px-2 py-0" onclick="nudgeVertex(${idx}, -0.0001, 0)" title="Geser Selatan">⬇</button>
                            <button class="btn btn-light btn-sm border px-2 py-0" onclick="nudgeVertex(${idx}, 0, -0.0001)" title="Geser Barat">⬅</button>
                            <button class="btn btn-light btn-sm border px-2 py-0" onclick="nudgeVertex(${idx}, 0, 0.0001)" title="Geser Timur">➡</button>
                        </div>
                    </div>
                `);

                handle.on('contextmenu', function (e) {
                    L.DomEvent.stopPropagation(e);
                    if (confirm('Hapus titik lekukan ini?')) {
                        deleteVertex(idx);
                    }
                });
            } else {
                handle.bindTooltip(idx === 0 ? 'Tiang Awal (Tetap)' : 'Tiang Akhir (Tetap)', { direction: 'top' });
            }

            segmentEditLayer.addLayer(handle);
        });

        map.fitBounds(poly.getBounds(), { padding: [80, 80] });
    }

    window.moveVertex = function (fromIdx, dir) {
        var vertices = translineEditor.editedVertices;
        var toIdx = fromIdx + dir;
        if (toIdx < 1 || toIdx > vertices.length - 2) return;
        translineEditor.undoStack.push(JSON.parse(JSON.stringify(vertices)));
        var temp = vertices[fromIdx];
        vertices[fromIdx] = vertices[toIdx];
        vertices[toIdx] = temp;
        renderSingleSegmentEditor();
    };

    window.deleteVertex = function (idx) {
        var vertices = translineEditor.editedVertices;
        if (idx <= 0 || idx >= vertices.length - 1) return;
        translineEditor.undoStack.push(JSON.parse(JSON.stringify(vertices)));
        vertices.splice(idx, 1);
        renderSingleSegmentEditor();
    };

    window.nudgeVertex = function (idx, dLat, dLng) {
        var vertices = translineEditor.editedVertices;
        if (idx < 0 || idx >= vertices.length) return;
        translineEditor.undoStack.push(JSON.parse(JSON.stringify(vertices)));
        vertices[idx] = [vertices[idx][0] + dLat, vertices[idx][1] + dLng];
        renderSingleSegmentEditor();
    };

    bindPointerSafeTap('btn-add-midpoint-vertex', function () {
        var vertices = translineEditor.editedVertices;
        if (vertices.length < 2) return;
        var midLat = (vertices[0][0] + vertices[1][0]) / 2;
        var midLng = (vertices[0][1] + vertices[1][1]) / 2;
        translineEditor.undoStack.push(JSON.parse(JSON.stringify(vertices)));
        vertices.splice(1, 0, [midLat, midLng]);
        renderSingleSegmentEditor();
    }, 'ADD_MIDPOINT_VERTEX');

    bindPointerSafeTap('btn-undo-segment', function () {
        if (translineEditor.undoStack.length > 1) {
            translineEditor.undoStack.pop();
            translineEditor.editedVertices = JSON.parse(JSON.stringify(translineEditor.undoStack[translineEditor.undoStack.length - 1]));
            renderSingleSegmentEditor();
        } else {
            alert('Tidak ada riwayat undo.');
        }
    }, 'UNDO_SEGMENT');

    bindPointerSafeTap('btn-cancel-segment', function () {
        setEditorState(TRANSLINE_STATE.IDLE);
    }, 'CANCEL_SEGMENT');

    bindPointerSafeTap('btn-save-segment-geometry', function () {
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
            setEditorState(TRANSLINE_STATE.IDLE);
            if (currentData) {
                if (res.translines) currentData.translines = res.translines;
                if (res.topology) {
                    currentData.transline = {
                        type: 'Feature',
                        geometry: {
                            type: res.topology.type || 'MultiLineString',
                            coordinates: res.topology.coordinates || []
                        },
                        properties: {
                            edges: res.topology.edges || [],
                            nodes: res.topology.nodes || []
                        }
                    };
                }
                renderFilteredLayers(false);
                alert(res.message);
            } else {
                loadGisNetworkOnDemand(false, function() {
                    alert(res.message);
                });
            }
        })
        .catch(err => {
            alert('Gagal menyimpan bentuk segmen: ' + err.message);
        });
    }, 'SAVE_SEGMENT_GEOMETRY');

    // ========================================================
    // GLOBAL FAB: EDIT TRANSLINE (Activates Source Selection)
    // ========================================================
    bindPointerSafeTap('fab-edit-transline', function () {
        collapseFab();
        setEditorState(TRANSLINE_STATE.SELECT_SOURCE, 'SENTUH TIANG PADA PETA UNTUK MEMILIH JALUR');
    }, 'FAB_EDIT_TRANSLINE');

    // Parameter Edit Sub-actions
    bindPointerSafeTap('act-edit-params', function () {
        safeHideOffcanvas('offcanvas-asset-edit-menu');
        openCorrectionModal(encodeURIComponent(JSON.stringify(activeAssetProps)));
    }, 'ACTION_EDIT_PARAMS');

    bindPointerSafeTap('act-edit-coords', function () {
        safeHideOffcanvas('offcanvas-asset-edit-menu');
        openCorrectionModal(encodeURIComponent(JSON.stringify(activeAssetProps)));
    }, 'ACTION_EDIT_COORDS');

    bindPointerSafeTap('btn-sheet-open-edit', function () {
        safeHideOffcanvas('offcanvas-asset-detail');
        safeShowOffcanvas('offcanvas-asset-edit-menu');
    }, 'SHEET_OPEN_EDIT');

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

    // ========================================================
    // ⚡ CENTRALIZED TRANSLINE MULTI-SEGMENT RENDERER
    // ========================================================
    function renderAllTranslines() {
        if (!translinePolylineLayer) return;
        translinePolylineLayer.clearLayers();
        window.translineLayers = new Map();

        if (!currentData) return;

        // 1. Gather all active segment representations
        var translinesList = [];
        if (Array.isArray(currentData.translines) && currentData.translines.length > 0) {
            translinesList = currentData.translines;
        } else if (currentData.transline && currentData.transline.properties && Array.isArray(currentData.transline.properties.edges) && currentData.transline.properties.edges.length > 0) {
            translinesList = currentData.transline.properties.edges;
        } else if (currentData.transline && currentData.transline.geometry && currentData.transline.geometry.coordinates) {
            var geom = currentData.transline.geometry;
            if (geom.type === 'MultiLineString' && Array.isArray(geom.coordinates)) {
                translinesList = geom.coordinates.map((seg, idx) => ({
                    id: idx + 1,
                    transline_id: idx + 1,
                    coordinates: seg,
                    conductor_label: 'AAAC 150 mm²',
                    length_meter: 0,
                    is_active: 1
                }));
            } else if (geom.type === 'LineString' && Array.isArray(geom.coordinates)) {
                translinesList = [{
                    id: 1,
                    transline_id: 1,
                    coordinates: geom.coordinates,
                    conductor_label: 'AAAC 150 mm²',
                    length_meter: 0,
                    is_active: 1
                }];
            }
        }

        var activeTranslines = translinesList.filter(t => (t.is_active === undefined || Number(t.is_active) === 1));

        console.log(
            '[GIS TRANSLINE]',
            'feeder=', currentFeederId,
            'active=', activeTranslines.length,
            'ids=', activeTranslines.map(t => t.id || t.transline_id || t.edge_id)
        );

        activeTranslines.forEach(function (tl, idx) {
            var tId = tl.id || tl.transline_id || tl.edge_id || (idx + 1);
            var fromId = tl.source_asset_id || tl.from_asset_id;
            var toId = tl.target_asset_id || tl.to_asset_id;

            // Resolve coordinates strictly from authoritative Asset points
            var latLngs = [];
            var rawCoords = tl.coordinates || tl.geometry;
            if (typeof rawCoords === 'string') {
                try { rawCoords = JSON.parse(rawCoords); } catch (err) { rawCoords = []; }
            }

            // Canonical unwrap: if rawCoords is GeoJSON object {"type":"LineString","coordinates":[...]}
            if (rawCoords && !Array.isArray(rawCoords) && Array.isArray(rawCoords.coordinates)) {
                rawCoords = rawCoords.coordinates;
            }

            if (Array.isArray(rawCoords) && rawCoords.length >= 2) {
                var validPts = rawCoords.filter(pt => Array.isArray(pt) && pt.length >= 2 && isValidLatLng(pt[1], pt[0]));
                if (validPts.length >= 2) {
                    latLngs = validPts.map(pt => [pt[1], pt[0]]);
                }
            }

            // Fallback: derive directly from source_asset and target_asset objects if provided
            if (latLngs.length < 2 && tl.source_asset && tl.target_asset) {
                var sLat = Number(tl.source_asset.latitude || 0);
                var sLng = Number(tl.source_asset.longitude || 0);
                var tLat = Number(tl.target_asset.latitude || 0);
                var tLng = Number(tl.target_asset.longitude || 0);
                if (isValidLatLng(sLat, sLng) && isValidLatLng(tLat, tLng)) {
                    latLngs = [[sLat, sLng], [tLat, tLng]];
                }
            }

            // Secondary Fallback: look up in canonical in-memory index or currentData.features
            if (latLngs.length < 2 && fromId && toId) {
                var sNorm = String(fromId);
                var tNorm = String(toId);
                var activeIdx = (window.SIDAK_GIS_NETWORK_CACHE && window.SIDAK_GIS_NETWORK_CACHE.indexes)
                    ? window.SIDAK_GIS_NETWORK_CACHE.indexes
                    : null;

                if (activeIdx && activeIdx.coordinateByAsset.has(sNorm) && activeIdx.coordinateByAsset.has(tNorm)) {
                    latLngs = [activeIdx.coordinateByAsset.get(sNorm), activeIdx.coordinateByAsset.get(tNorm)];
                } else if (Array.isArray(currentData.features)) {
                    var sFeat = currentData.features.find(f => f.properties && f.properties.entity_type === 'ASSET' && String(f.properties.id) === sNorm);
                    var tFeat = currentData.features.find(f => f.properties && f.properties.entity_type === 'ASSET' && String(f.properties.id) === tNorm);
                    if (sFeat && tFeat && sFeat.geometry && tFeat.geometry) {
                        var sCoords = sFeat.geometry.coordinates;
                        var tCoords = tFeat.geometry.coordinates;
                        if (isValidLatLng(sCoords[1], sCoords[0]) && isValidLatLng(tCoords[1], tCoords[0])) {
                            latLngs = [[sCoords[1], sCoords[0]], [tCoords[1], tCoords[0]]];
                        }
                    }
                }
            }

            if (latLngs.length < 2) {
                console.warn('[GIS TRANSLINE] Skipped segment lacking valid asset coordinates:', tId, 'from:', fromId, 'to:', toId);
                return;
            }

            var conductorLabel = tl.conductor_label || `${tl.conductor_type || 'AAAC'} ${tl.conductor_size || '150 mm²'}`;
            var lengthMeter = tl.length_meter || tl.distance_meters || 0;
            var translineCode = tl.transline_code || `TL-${currentFeederId}-${tId}`;

            var createdBy = tl.created_by || '';
            var isTl04 = createdBy.includes('ENGINE=TL04') || createdBy.includes('TL-04');
            var isTl03 = !isTl04 && createdBy.includes('ENGINE=TL03');
            var isTl02 = !isTl04 && !isTl03 && (createdBy.includes('RUN:') || createdBy.includes('AI') || createdBy.includes('TL-02') || createdBy.includes('TL02'));
            var lineColor = isTl04 ? '#10b981' : (isTl03 ? '#06b6d4' : (isTl02 ? '#2563eb' : '#0284c7'));
            var lineWeight = isTl04 ? 4.5 : (isTl03 ? 4.0 : 3.5);

            var visPolyOpts = {
                color: lineColor,
                weight: lineWeight,
                opacity: 0.9,
                lineJoin: 'round',
                interactive: false
            };
            if (gisCanvasRenderer) visPolyOpts.renderer = gisCanvasRenderer;
            var visiblePoly = L.polyline(latLngs, visPolyOpts);

            visiblePoly.feature = {
                properties: {
                    transline_id: tId,
                    transline_code: translineCode,
                    source_asset_id: fromId,
                    target_asset_id: toId,
                    is_tl04: isTl04,
                    is_tl03: isTl03,
                    is_tl02: isTl02,
                }
            };

            var condImgUrl = resolveConductorPng(tl.conductor_type, tl.conductor_size);

            visiblePoly.bindTooltip(`⚡ <strong>${conductorLabel}</strong> (${Number(lengthMeter).toFixed(1)}m)`, {
                sticky: true,
                className: 'font-monospace small'
            });
            translinePolylineLayer.addLayer(visiblePoly);

            // Invisible hit-layer for touch / mouse target (24px width)
            var hitPolyOpts = {
                color: lineColor,
                weight: 24,
                opacity: 0.001,
                lineJoin: 'round',
                interactive: true
            };
            if (gisCanvasRenderer) hitPolyOpts.renderer = gisCanvasRenderer;
            var hitPoly = L.polyline(latLngs, hitPolyOpts);

            hitPoly.on('click', function (evt) {
                L.DomEvent.stopPropagation(evt);
                if (window.activeSegmentHighlight) {
                    var prevProps = window.activeSegmentHighlight.feature ? window.activeSegmentHighlight.feature.properties : {};
                    var prevColor = prevProps.is_tl04 ? '#10b981' : (prevProps.is_tl03 ? '#06b6d4' : (prevProps.is_tl02 ? '#2563eb' : '#0284c7'));
                    window.activeSegmentHighlight.setStyle({ color: prevColor, weight: prevProps.is_tl04 ? 4.5 : (prevProps.is_tl03 ? 4.0 : 3.5), opacity: 0.9 });
                }
                window.activeSegmentHighlight = visiblePoly;
                visiblePoly.setStyle({ color: '#f59e0b', weight: 5.5, opacity: 1 });

                // Lookup source & target names strictly from ASSET features or properties via O(1) index
                var fromAsset = (window.SIDAK_GIS_NETWORK_CACHE && window.SIDAK_GIS_NETWORK_CACHE.indexes)
                    ? window.SIDAK_GIS_NETWORK_CACHE.indexes.assetById.get(String(fromId))
                    : (currentData.features || []).find(f => (f.properties && f.properties.entity_type === 'ASSET' && String(f.properties.id) === String(fromId)));
                var toAsset = (window.SIDAK_GIS_NETWORK_CACHE && window.SIDAK_GIS_NETWORK_CACHE.indexes)
                    ? window.SIDAK_GIS_NETWORK_CACHE.indexes.assetById.get(String(toId))
                    : (currentData.features || []).find(f => (f.properties && f.properties.entity_type === 'ASSET' && String(f.properties.id) === String(toId)));

                var fromName = (fromAsset && fromAsset.properties) 
                    ? `${fromAsset.properties.nama_asset || fromAsset.properties.kode_asset} (#${fromId})` 
                    : (tl.source_asset ? `${tl.source_asset.nama_asset || tl.source_asset.kode_asset} (#${fromId})` : `Asset #${fromId}`);
                var toName = (toAsset && toAsset.properties) 
                    ? `${toAsset.properties.nama_asset || toAsset.properties.kode_asset} (#${toId})` 
                    : (tl.target_asset ? `${tl.target_asset.nama_asset || tl.target_asset.kode_asset} (#${toId})` : `Asset #${toId}`);
                var feederName = currentFeederName || (tl.penyulang_name || `Penyulang #${currentFeederId}`);
                var sectionName = tl.section_name || (fromAsset && fromAsset.properties.section_name) || (tl.section_id ? `Section #${tl.section_id}` : '-');

                var originBadge = isTl04
                    ? `<span class="badge" style="background-color: #10b981; color: #fff; font-size: 9px;"><i class="fas fa-project-diagram me-1"></i>TL-04 NETWORK PROMOTED</span>`
                    : (isTl03
                        ? `<span class="badge" style="background-color: #06b6d4; color: #fff; font-size: 9px;"><i class="fas fa-network-wired me-1"></i>TL-03 ADVANCED RECON</span>`
                        : (isTl02
                            ? `<span class="badge bg-info text-dark" style="font-size: 9px;"><i class="fas fa-bolt text-warning me-1"></i>TL-02 AI AUTO-COMPLETED</span>`
                            : `<span class="badge bg-secondary" style="font-size: 9px;"><i class="fas fa-check-circle me-1"></i>OTORITATIF MANUAL</span>`));

                var popupContent = `
                    <div style="min-width: 280px; font-family: system-ui, -apple-system, sans-serif;">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-1 mb-2">
                            <strong style="color: ${lineColor}; font-size: 13px;">⚡ ${translineCode}</strong>
                            ${originBadge}
                        </div>
                        <div class="small text-muted mb-2" style="font-size: 11px; line-height: 1.4;">
                            <div><strong>Penyulang:</strong> <span class="text-dark">${feederName}</span></div>
                            <div><strong>Section:</strong> <span class="text-dark">${sectionName}</span></div>
                            <div><strong>Titik A (Source):</strong> <span class="text-dark">${fromName}</span></div>
                            <div><strong>Titik B (Target):</strong> <span class="text-dark">${toName}</span></div>
                            <div class="d-flex align-items-center gap-1"><strong>Konduktor:</strong> <span class="text-dark">${conductorLabel}</span> <img src="${condImgUrl}" alt="Conductor" style="height: 12px; max-width: 45px; object-fit: contain;"></div>
                            <div><strong>Panjang:</strong> <span class="text-dark">${Number(lengthMeter).toFixed(1)} m</span></div>
                            <div><strong>Status:</strong> <span class="badge bg-success" style="font-size: 9px;">ACTIVE</span></div>
                            ${createdBy ? `<div class="mt-1 pt-1 border-top" style="font-size: 9px;"><strong>Provenance:</strong> <span class="font-monospace text-secondary">${createdBy}</span></div>` : ''}
                        </div>
                        <div class="p-1 bg-light rounded text-center border">
                            <span class="text-secondary fw-bold" style="font-size: 9px;">
                                🟢 TRANSLINE OTORITATIF JTM
                            </span>
                        </div>
                    </div>
                `;

                L.popup()
                    .setLatLng(evt.latlng)
                    .setContent(popupContent)
                    .openOn(map);
            });

            translinePolylineLayer.addLayer(hitPoly);
            window.translineLayers.set(tId, visiblePoly);
        });

        console.log(
            '[GIS TRANSLINE RENDER]',
            'rendered=', window.translineLayers.size,
            'ids=', Array.from(window.translineLayers.keys())
        );
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

        if (!currentData) return;

        // Render all independent transline segments
        renderAllTranslines();

        // Render Markers strictly separated by entity_type and asset_scope
        var rawFeatures = currentData.features || [];
        var activeLayers = getSelectedSetupLayers();
        var renderedFeederAssetCount = 0;
        var renderedUnassignedAssetCount = 0;
        var renderedTemuanCount = 0;
        var renderedJtm = 0;
        var renderedGardu = 0;
        var renderedTrafo = 0;
        var renderedSwitch = 0;
        var feederAssetIds = [];
        var unassignedAssetIds = [];
        var findingIds = [];

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
                        findingIds.push(props.finding_id || props.id);
                    }
                }
                return;
            }

            // 2. STRICT MASTER ASSET LAYER (Feeder vs ULP Unassigned)
            var jenis = (props.jenis_asset || '').toUpperCase();
            var constr = (props.construction_type || '').toUpperCase();
            var scope = props.asset_scope || 'FEEDER';

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
                    if (scope === 'FEEDER') {
                        renderedFeederAssetCount++;
                        feederAssetIds.push(props.id);
                    } else {
                        renderedUnassignedAssetCount++;
                        unassignedAssetIds.push(props.id);
                    }

                    if (isGarduType) renderedGardu++;
                    else if (isTrafoType) renderedTrafo++;
                    else if (isSwitchType) renderedSwitch++;
                    else renderedJtm++;
                }
            }
        });

        // 📡 Honest Empty State Check (Only show if both Feeder and Unassigned Master Assets are 0)
        var emptyFeederBanner = document.getElementById('gis-empty-feeder-banner');
        if (emptyFeederBanner) {
            if (renderedFeederAssetCount === 0 && renderedUnassignedAssetCount === 0 && !hasTopology) {
                emptyFeederBanner.style.display = 'block';
                document.getElementById('empty-feeder-name').textContent = currentFeederName || 'Penyulang Ini';
            } else {
                emptyFeederBanner.style.display = 'none';
            }
        }

        // 🔒 Console Data Contract Debug Group
        console.group('[GIS ASSET SCOPE DEBUG]');
        console.log('Selected Penyulang:', currentFeederId + ' (' + (currentFeederName || '-') + ')');
        console.log('Selected ULP:', (currentData.meta && currentData.meta.selected_ulp_id) || 1);
        console.log('Feeder Assets:', renderedFeederAssetCount, 'IDs:', feederAssetIds);
        console.log('ULP Unassigned Assets:', renderedUnassignedAssetCount, 'IDs:', unassignedAssetIds);
        console.log('Rejected Cross-Feeder Assets:', (currentData.summary && currentData.summary.rejected_cross_feeder ? currentData.summary.rejected_cross_feeder : 0));
        console.log('Rejected Cross-ULP Assets:', (currentData.summary && currentData.summary.rejected_cross_ulp ? currentData.summary.rejected_cross_ulp : 0));
        console.log('Temuan Loaded:', renderedTemuanCount, 'IDs:', findingIds);
        console.groupEnd();

        // Update live summary bar accurately
        var summaryBar = document.getElementById('gis-summary-bar');
        if (summaryBar) {
            summaryBar.style.display = 'block';
            var summaryHtml = `<i class="fas fa-network-wired text-primary me-1"></i> Asset Penyulang: <strong>${renderedFeederAssetCount}</strong>`;
            if (renderedUnassignedAssetCount > 0) {
                summaryHtml += ` | <i class="fas fa-link-slash text-warning ms-2 me-1"></i> Belum Terhubung: <strong>${renderedUnassignedAssetCount}</strong>`;
            }
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

    // Canonical In-Memory Index Builder
    function buildNetworkIndexes(data) {
        var assetById = new Map();
        var coordinateByAsset = new Map();
        var translineById = new Map();
        var neighborsByAsset = new Map();

        if (!data) return { assetById, coordinateByAsset, translineById, neighborsByAsset };

        var features = Array.isArray(data.features) ? data.features : [];
        for (var i = 0; i < features.length; i++) {
            var f = features[i];
            var props = f.properties || {};
            if (props.entity_type === 'ASSET' && props.id !== undefined && props.id !== null) {
                var idStr = String(props.id);
                assetById.set(idStr, f);
                if (f.geometry && Array.isArray(f.geometry.coordinates)) {
                    var c = f.geometry.coordinates;
                    if (isValidLatLng(c[1], c[0])) {
                        coordinateByAsset.set(idStr, [c[1], c[0]]);
                    }
                }
            }
        }

        var translines = Array.isArray(data.translines) ? data.translines : [];
        for (var j = 0; j < translines.length; j++) {
            var tl = translines[j];
            var tId = String(tl.id || tl.transline_id || tl.edge_id || (j + 1));
            translineById.set(tId, tl);

            var sId = String(tl.source_asset_id || tl.from_asset_id || '');
            var tTargetId = String(tl.target_asset_id || tl.to_asset_id || '');

            if (sId && tTargetId) {
                if (!neighborsByAsset.has(sId)) neighborsByAsset.set(sId, []);
                neighborsByAsset.get(sId).push(tTargetId);

                if (!neighborsByAsset.has(tTargetId)) neighborsByAsset.set(tTargetId, []);
                neighborsByAsset.get(tTargetId).push(sId);
            }
        }

        return { assetById, coordinateByAsset, translineById, neighborsByAsset };
    }

    // Fetch Network Data On-Demand (With In-Memory Caching & Request Deduplication)
    function loadGisNetworkOnDemand(autoFitBounds, callback) {
        if (typeof autoFitBounds === 'undefined') autoFitBounds = true;
        if (!currentFeederId) return;

        var fKey = String(currentFeederId);

        // 1. In-Memory Cache Check: If feeder data already in memory, render immediately with 0 network calls!
        var cachedEntry = window.SIDAK_GIS_NETWORK_CACHE_BY_FEEDER.get(fKey);
        if (cachedEntry && cachedEntry.data) {
            currentData = cachedEntry.data;
            window.SIDAK_GIS_NETWORK_CACHE = cachedEntry;
            renderFilteredLayers(autoFitBounds);
            fetchPendingBadgeCount();
            loadGisProposalsOnDemand();
            if (typeof callback === 'function') callback();
            return;
        }

        // 2. Request Deduplication: If identical feeder fetch is currently in-flight, reuse promise
        if (activeNetworkRequestPromise && activeNetworkRequestPromise.feederId === fKey) {
            activeNetworkRequestPromise.then(() => {
                if (typeof callback === 'function') callback();
            });
            return;
        }

        // 3. Stale Request Cancellation: Abort previous feeder fetch if switching feeders
        if (activeNetworkAbortController) {
            try { activeNetworkAbortController.abort(); } catch (e) {}
        }
        activeNetworkAbortController = (typeof AbortController !== 'undefined') ? new AbortController() : null;

        var thisGeneration = ++currentRequestGeneration;
        var thisRequestId = ++currentRequestId;
        currentLOD = getLODCategory(map ? map.getZoom() : 14);

        var layersParam = getSelectedSetupLayers().join(',');
        toggleLoading(true);

        var fetchUrl = `<?= site_url('gis/api-network') ?>?penyulang_id=${currentFeederId}&zoom=${map ? map.getZoom() : 14}&layers=${layersParam}`;
        var fetchOpts = activeNetworkAbortController ? { signal: activeNetworkAbortController.signal } : {};

        var reqPromise = fetchJson(fetchUrl, fetchOpts)
            .then(res => {
                if (thisGeneration !== currentRequestGeneration) return;
                toggleLoading(false);
                activeNetworkRequestPromise = null;

                if (res && res.status === 'success' && res.data) {
                    currentData = res.data;

                    // Build canonical in-memory index
                    var indexes = buildNetworkIndexes(currentData);
                    var feederCache = {
                        feederId: fKey,
                        feederName: currentFeederName,
                        loadedAt: Date.now(),
                        data: currentData,
                        indexes: indexes
                    };
                    window.SIDAK_GIS_NETWORK_CACHE_BY_FEEDER.set(fKey, feederCache);
                    window.SIDAK_GIS_NETWORK_CACHE = feederCache;

                    renderFilteredLayers(autoFitBounds);
                    fetchPendingBadgeCount();
                    loadGisProposalsOnDemand();
                    if (typeof callback === 'function') callback();
                }
            })
            .catch(err => {
                if (err && err.name === 'AbortError') {
                    // Stale request aborted cleanly
                    return;
                }
                if (thisGeneration === currentRequestGeneration) {
                    toggleLoading(false);
                    activeNetworkRequestPromise = null;
                }
                console.error(err);
            });

        reqPromise.feederId = fKey;
        activeNetworkRequestPromise = reqPromise;
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

    bindPointerSafeTap(fabToggle, function () {
        var isExpanded = fabMenu.style.display === 'flex';
        fabMenu.style.display = isExpanded ? 'none' : 'flex';
        fabToggle.classList.toggle('active', !isExpanded);
    }, 'TOGGLE_FAB');

    function collapseFab() {
        fabMenu.style.display = 'none';
        fabToggle.classList.remove('active');
    }

    bindPointerSafeTap('fab-add-asset', function () {
        collapseFab();
        openAddAssetModal();
    }, 'FAB_ADD_ASSET');

    bindPointerSafeTap('fab-open-filter', function () {
        collapseFab();
        openFilterDrawer();
    }, 'FAB_OPEN_FILTER');

    bindPointerSafeTap('btn-open-filter-drawer', openFilterDrawer, 'OPEN_FILTER_DRAWER');

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
        safeShowOffcanvas('offcanvas-filter-sheet');
    }

    bindPointerSafeTap('btn-apply-drawer-filter', function () {
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

        safeHideOffcanvas('offcanvas-filter-sheet');
        var fKey = String(currentFeederId);
        if (window.SIDAK_GIS_NETWORK_CACHE && window.SIDAK_GIS_NETWORK_CACHE.feederId === fKey) {
            renderFilteredLayers(false);
        } else {
            loadGisNetworkOnDemand(true);
        }
    }, 'APPLY_DRAWER_FILTER');

    bindPointerSafeTap('fab-locate-me', function () {
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
    }, 'FAB_LOCATE_ME');

    bindPointerSafeTap('fab-toggle-legend', function () {
        collapseFab();
        var lp = document.getElementById('gis-legend-panel');
        lp.style.display = lp.style.display === 'block' ? 'none' : 'block';
    }, 'FAB_TOGGLE_LEGEND');

    bindPointerSafeTap('btn-close-legend', function () {
        document.getElementById('gis-legend-panel').style.display = 'none';
    }, 'CLOSE_LEGEND');

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

    // ========================================================
    // 🔮 TL-01 SUB-GATE D4A: TRANSLINE EXCEPTION REVIEW WORKBENCH & QUEUE
    // ========================================================
    var proposalHighlightLayer = null;

    function loadGisProposalsOnDemand() {
        var fId = currentFeederId || '';
        var state = currentProposalFilter || 'ALL';
        var url = `<?= site_url('gis/api-proposal-exception-queue') ?>?penyulang_id=${encodeURIComponent(fId)}&state=${encodeURIComponent(state)}`;

        var loadingNotice = document.getElementById('proposals-loading');
        var emptyNotice   = document.getElementById('proposals-empty');
        if (loadingNotice) loadingNotice.style.display = 'block';
        if (emptyNotice)   emptyNotice.style.display   = 'none';

        fetchJson(url)
            .then(res => {
                if (loadingNotice) loadingNotice.style.display = 'none';
                if (res && res.status === 'success') {
                    proposalsData = res.queue || [];
                    updateProposalsBadgeAndSummary(res.summary || {});
                    renderProposalsPreviewLayer();
                    renderProposalsListInDrawer();
                } else {
                    proposalsData = [];
                    updateProposalsBadgeAndSummary({});
                    renderProposalsListInDrawer();
                }
            })
            .catch(err => {
                if (loadingNotice) loadingNotice.style.display = 'none';
                console.error('[D4A PROPOSALS QUEUE ERROR]', err);
                proposalsData = [];
                renderProposalsListInDrawer();
            });
    }

    function updateProposalsBadgeAndSummary(summary) {
        var total = summary.total !== undefined ? summary.total : proposalsData.length;
        var badge = document.getElementById('proposals-badge-count');
        if (badge) {
            badge.textContent = total;
            badge.style.display = total > 0 ? 'inline-block' : 'none';
        }

        if (document.getElementById('cnt-state-all')) document.getElementById('cnt-state-all').textContent = total;
        if (document.getElementById('cnt-state-gov-anomaly')) document.getElementById('cnt-state-gov-anomaly').textContent = summary.governance_anomaly || 0;
        if (document.getElementById('cnt-state-blocked')) document.getElementById('cnt-state-blocked').textContent = summary.blocked || 0;
        if (document.getElementById('cnt-state-human-review')) document.getElementById('cnt-state-human-review').textContent = summary.human_review || 0;
        if (document.getElementById('cnt-state-ready')) document.getElementById('cnt-state-ready').textContent = summary.ready || 0;
        if (document.getElementById('cnt-state-active')) document.getElementById('cnt-state-active').textContent = summary.active || 0;
        if (document.getElementById('proposal-drawer-feeder')) {
            document.getElementById('proposal-drawer-feeder').textContent = currentFeederName || 'SEMUA PENYULANG';
        }
    }

    function renderProposalsPreviewLayer() {
        if (!proposalsPreviewLayer) return;
        proposalsPreviewLayer.clearLayers();

        if (!Array.isArray(proposalsData) || proposalsData.length === 0) return;

        proposalsData.forEach(function (prop) {
            var geom = prop.proposed_geometry;
            if (!geom || !Array.isArray(geom.coordinates) || geom.coordinates.length < 2) return;

            var latLngs = geom.coordinates.map(pt => [pt[1], pt[0]]);
            var isReady = (prop.canonical_operational_state === 'READY');
            var isAnomaly = (prop.canonical_operational_state === 'GOVERNANCE_ANOMALY' || prop.canonical_operational_state === 'BLOCKED');
            var color = isReady ? '#10b981' : (isAnomaly ? '#ef4444' : '#f59e0b');

            var dashPattern = '8, 8';
            if (prop.visual_pattern === 'DASH_DOT') dashPattern = '12, 4, 2, 4';
            else if (prop.visual_pattern === 'DASHED') dashPattern = '8, 6';
            else if (prop.visual_pattern === 'TWISTED_CHAIN') dashPattern = '10, 3, 3, 3';

            var previewLineOpts = {
                color: color,
                weight: 4.0,
                opacity: 0.85,
                dashArray: dashPattern,
                lineCap: 'round',
                interactive: true
            };
            if (gisCanvasRenderer) previewLineOpts.renderer = gisCanvasRenderer;
            var previewLine = L.polyline(latLngs, previewLineOpts);

            previewLine.bindTooltip(`
                <div class="font-monospace small">
                    <span class="badge ${isReady ? 'bg-success text-white' : (isAnomaly ? 'bg-danger text-white' : 'bg-warning text-dark')} mb-1">[${prop.canonical_operational_state}] Proposal #${prop.id}</span><br>
                    <strong>${prop.proposed_conductor || '-'}</strong> (${prop.proposed_distance}m)<br>
                    <span class="text-muted" style="font-size:9px;">Klik untuk Workbench Proposal #${prop.id}</span>
                </div>
            `, { sticky: true });

            previewLine.on('click', function (e) {
                L.DomEvent.stopPropagation(e);
                openProposalWorkbench(prop.id);
            });

            proposalsPreviewLayer.addLayer(previewLine);
        });
    }

    function renderProposalsListInDrawer() {
        var listContainer = document.getElementById('proposals-list');
        var emptyNotice   = document.getElementById('proposals-empty');
        var loadingNotice = document.getElementById('proposals-loading');

        if (loadingNotice) loadingNotice.style.display = 'none';
        if (!listContainer) return;
        listContainer.innerHTML = '';

        if (!Array.isArray(proposalsData) || proposalsData.length === 0) {
            if (emptyNotice) emptyNotice.style.display = 'block';
            return;
        } else {
            if (emptyNotice) emptyNotice.style.display = 'none';
        }

        proposalsData.forEach(function (prop) {
            var state = prop.canonical_operational_state || 'READY';
            var prioWeight = prop.priority_weight || 4;
            var prioLabel = prop.triage_label || `P${prioWeight} · ${state}`;

            var prioBadgeClass = prioWeight === 1 ? 'bg-danger text-white' :
                                (prioWeight === 2 ? 'bg-danger-subtle text-danger border border-danger-subtle' :
                                (prioWeight === 3 ? 'bg-warning text-dark' :
                                (prioWeight === 4 ? 'bg-success text-white' : 'bg-info text-white')));

            var stateBadgeClass = state === 'READY' ? 'bg-success' :
                                 (state === 'HUMAN_REVIEW' ? 'bg-warning text-dark' :
                                 (state === 'BLOCKED' ? 'bg-danger' :
                                 (state === 'ACTIVE' ? 'bg-info' : 'bg-dark')));

            var integBadgeClass = prop.integrity_status === 'HEALTHY' ?
                                  'bg-success-subtle text-success border border-success-subtle' :
                                  'bg-danger-subtle text-danger border border-danger-subtle';

            var card = document.createElement('div');
            card.className = 'card border rounded-3 shadow-sm proposal-card';
            card.id = `proposal-card-${prop.id}`;

            card.innerHTML = `
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                            <span class="badge ${prioBadgeClass} font-monospace" style="font-size: 10px;">${prioLabel}</span>
                            <span class="badge ${stateBadgeClass} font-monospace" style="font-size: 10px;">${state}</span>
                            <span class="badge ${integBadgeClass} font-monospace" style="font-size: 9px;">${prop.integrity_status || 'HEALTHY'}</span>
                        </div>
                        <span class="text-muted font-monospace fw-bold" style="font-size: 11px;">#${prop.id}</span>
                    </div>

                    <div class="small text-muted mb-2 font-monospace" style="font-size: 10px;">
                        <i class="fas fa-sitemap me-1 text-secondary"></i> ${prop.feeder_name || '-'} · ${prop.section_name || 'Tanpa Seksi'}
                    </div>

                    <div class="d-flex align-items-center justify-content-between bg-light p-2 rounded-2 mb-2 font-monospace" style="font-size: 11px;">
                        <div class="text-truncate" style="max-width: 140px;">
                            <strong class="text-dark d-block text-truncate" title="${prop.source_asset_name || prop.source_asset_code}">${prop.source_asset_code || '-'}</strong>
                            <span class="text-muted small" style="font-size: 9px;">${prop.source_asset_name || '-'}</span>
                        </div>
                        <i class="fas fa-arrow-right text-primary mx-1"></i>
                        <div class="text-truncate text-end" style="max-width: 140px;">
                            <strong class="text-dark d-block text-truncate" title="${prop.target_asset_name || prop.target_asset_code}">${prop.target_asset_code || '-'}</strong>
                            <span class="text-muted small" style="font-size: 9px;">${prop.target_asset_name || '-'}</span>
                        </div>
                    </div>

                    <div class="small mb-2" style="font-size: 11px;">
                        <div class="d-flex justify-content-between text-secondary mb-1">
                            <span><i class="fas fa-ruler me-1"></i> Jarak Geodesic:</span>
                            <strong class="text-dark font-monospace">${prop.proposed_distance || 0} meter</strong>
                        </div>
                        <div class="d-flex justify-content-between text-secondary mb-1">
                            <span><i class="fas fa-bolt me-1"></i> Konduktor:</span>
                            <strong class="text-primary font-monospace">${prop.proposed_conductor || '-'}</strong>
                        </div>
                        <div class="d-flex justify-content-between text-secondary">
                            <span><i class="fas fa-microchip me-1"></i> Klasifikasi:</span>
                            <span class="badge bg-secondary font-monospace" style="font-size: 9px;">${prop.classification || '-'} · ${prop.lifecycle_status || '-'}</span>
                        </div>
                    </div>

                    ${prop.reason_code ? `
                        <div class="badge bg-danger-subtle text-danger border border-danger-subtle mb-2 d-block text-start p-2 font-monospace" style="font-size: 10px; white-space: normal;">
                            <i class="fas fa-triangle-exclamation me-1"></i> <strong>${prop.reason_code}</strong>
                        </div>
                    ` : ''}

                    <div class="d-flex gap-2 mt-2">
                        <button type="button" class="btn btn-outline-primary btn-sm flex-fill rounded-pill fw-bold" onclick="focusProposalOnMap(${prop.id})" style="font-size: 11px;">
                            <i class="fas fa-crosshairs me-1"></i> Focus Map
                        </button>
                        <button type="button" class="btn btn-primary btn-sm flex-fill rounded-pill fw-bold" onclick="openProposalWorkbench(${prop.id})" style="font-size: 11px;">
                            <i class="fas fa-microscope me-1"></i> Workbench
                        </button>
                    </div>

                    ${state === 'ACTIVE' ? `
                        <div class="mt-2 text-center">
                            <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 d-block rounded-pill" style="font-size: 10px;">
                                <i class="fas fa-check-circle me-1"></i> Terkonfirmasi di gis_translines
                            </span>
                        </div>
                    ` : ''}
                </div>
            `;
            listContainer.appendChild(card);
        });
    }

    window.focusProposalOnMap = function (propId) {
        var prop = proposalsData.find(p => p.id === propId);
        if (!prop || !prop.proposed_geometry) return;

        var coords = prop.proposed_geometry.coordinates;
        if (Array.isArray(coords) && coords.length >= 2) {
            safeHideOffcanvas('offcanvas-proposals-drawer');
            var latLngs = coords.map(pt => [pt[1], pt[0]]);
            highlightProposalOnMap(latLngs, prop);
            if (map) {
                var bounds = L.latLngBounds(latLngs);
                map.fitBounds(bounds, { padding: [80, 80], maxZoom: 18 });
            }
        }
    };

    function highlightProposalOnMap(latLngs, prop) {
        if (!map) return;
        if (!proposalHighlightLayer) {
            proposalHighlightLayer = L.featureGroup().addTo(map);
        }
        proposalHighlightLayer.clearLayers();

        var isReady = prop && (prop.canonical_operational_state === 'READY');
        var isAnomaly = prop && (prop.canonical_operational_state === 'GOVERNANCE_ANOMALY' || prop.canonical_operational_state === 'BLOCKED');
        var color = isReady ? '#10b981' : (isAnomaly ? '#ef4444' : '#00d2ff');

        var highlightLine = L.polyline(latLngs, {
            color: color,
            weight: 6.0,
            opacity: 0.95,
            dashArray: '8, 6',
            lineCap: 'round'
        });

        var startMarker = L.circleMarker(latLngs[0], {
            radius: 6,
            color: '#10b981',
            fillColor: '#ffffff',
            fillOpacity: 1,
            weight: 3
        }).bindTooltip(`<strong>ASET ASAL:</strong> ${prop ? (prop.source_asset_code || '-') : '-'}`, { permanent: false });

        var endMarker = L.circleMarker(latLngs[latLngs.length - 1], {
            radius: 6,
            color: '#ef4444',
            fillColor: '#ffffff',
            fillOpacity: 1,
            weight: 3
        }).bindTooltip(`<strong>ASET TUJUAN:</strong> ${prop ? (prop.target_asset_code || '-') : '-'}`, { permanent: false });

        proposalHighlightLayer.addLayer(highlightLine);
        proposalHighlightLayer.addLayer(startMarker);
        proposalHighlightLayer.addLayer(endMarker);
    }

    // Filter pills click handler
    document.querySelectorAll('#offcanvas-proposals-drawer .filter-pill').forEach(function (pill) {
        pill.addEventListener('click', function () {
            document.querySelectorAll('#offcanvas-proposals-drawer .filter-pill').forEach(p => p.classList.remove('active', 'border-primary', 'bg-light'));
            this.classList.add('active', 'border-primary', 'bg-light');
            currentProposalFilter = this.dataset.filter || 'ALL';
            loadGisProposalsOnDemand();
        });
    });

    // TL-01 Sub-Gate D4A: Open Proposal Exception Review Workbench Modal (Pure Read-Only)
    window.openProposalWorkbench = function (propId) {
        var modalEl = document.getElementById('modal-proposal-workbench');
        if (!modalEl) return;

        var loadingEl = document.getElementById('wb-loading');
        var errorEl   = document.getElementById('wb-error');
        var contentEl = document.getElementById('wb-content');

        if (loadingEl) loadingEl.style.display = 'block';
        if (errorEl)   errorEl.style.display   = 'none';
        if (contentEl) contentEl.style.display = 'none';

        var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();

        fetchJson('<?= site_url('gis/api-proposal-workbench') ?>/' + propId)
            .then(res => {
                if (loadingEl) loadingEl.style.display = 'none';

                if (!res || res.status !== 'success') {
                    if (errorEl) {
                        errorEl.style.display = 'block';
                        document.getElementById('wb-error-msg').innerText = (res && (res.message || res.reason)) ? (res.message || res.reason) : 'Gagal memuat detail workbench proposal.';
                    }
                    return;
                }

                if (contentEl) contentEl.style.display = 'block';

                var prop    = res.proposal || {};
                var src     = res.source_asset || {};
                var tgt     = res.target_asset || {};
                var integ   = res.integrity_layer || {};
                var comp    = res.authoritative_comparison || {};
                var evid    = res.evidence_inspector || {};
                var receipt = res.audit_receipt_preview || {};

                // Header badges
                if (document.getElementById('wb-proposal-id-badge')) document.getElementById('wb-proposal-id-badge').innerText = '#' + (prop.id || propId);
                if (document.getElementById('wb-classification-badge')) document.getElementById('wb-classification-badge').innerText = prop.classification || 'AUTO_MATCH';
                if (document.getElementById('wb-lifecycle-badge')) document.getElementById('wb-lifecycle-badge').innerText = prop.lifecycle_status || 'PENDING_REVIEW';
                if (document.getElementById('wb-natural-key')) document.getElementById('wb-natural-key').innerText = prop.natural_key || '-';

                var stateBadge = document.getElementById('wb-canonical-state-badge');
                var state = prop.canonical_operational_state || 'READY';
                if (stateBadge) {
                    stateBadge.innerText = state;
                    stateBadge.className = 'badge font-monospace ' + (
                        state === 'READY' ? 'bg-success' :
                        state === 'HUMAN_REVIEW' ? 'bg-warning text-dark' :
                        state === 'BLOCKED' ? 'bg-danger' :
                        state === 'ACTIVE' ? 'bg-info' : 'bg-dark'
                    );
                }

                var integBadge = document.getElementById('wb-integrity-badge');
                var integStatus = integ.status || 'HEALTHY';
                if (integBadge) {
                    integBadge.innerText = integStatus;
                    integBadge.className = 'badge font-monospace ' + (integStatus === 'HEALTHY' ? 'bg-success' : 'bg-danger');
                }

                // Network Hierarchy Breadcrumb
                if (document.getElementById('wb-crumb-ulp')) document.getElementById('wb-crumb-ulp').innerText = currentUlpName || 'ULP SIDOARJO KOTA';
                if (document.getElementById('wb-crumb-feeder')) document.getElementById('wb-crumb-feeder').innerText = (prop.feeder_name || ('Penyulang #' + (prop.feeder_id || ''))) + (prop.feeder_code ? ' (' + prop.feeder_code + ')' : '');
                if (document.getElementById('wb-crumb-section')) document.getElementById('wb-crumb-section').innerText = prop.section_name || 'Tanpa Seksi';
                if (document.getElementById('wb-crumb-span')) document.getElementById('wb-crumb-span').innerText = (src.kode_asset || 'SRC') + ' ➔ ' + (tgt.kode_asset || 'TGT');

                // Guidance Callout
                var guidanceIcon  = document.getElementById('wb-guidance-icon');
                var guidanceTitle = document.getElementById('wb-guidance-title');
                var guidanceText  = document.getElementById('wb-guidance-text');
                if (guidanceText) guidanceText.innerText = integ.resolution_guidance || '-';

                if (guidanceIcon && guidanceTitle) {
                    if (state === 'BLOCKED' || integStatus === 'ANOMALY') {
                        guidanceIcon.className = 'fas fa-exclamation-circle text-danger fs-5 mt-1';
                        guidanceTitle.innerText = 'PETUNJUK RESOLUSI OPERATOR (BLOCKED / ANOMALY)';
                    } else if (state === 'HUMAN_REVIEW') {
                        guidanceIcon.className = 'fas fa-eye text-warning fs-5 mt-1';
                        guidanceTitle.innerText = 'PETUNJUK RESOLUSI OPERATOR (HUMAN REVIEW)';
                    } else {
                        guidanceIcon.className = 'fas fa-check-circle text-success fs-5 mt-1';
                        guidanceTitle.innerText = 'PETUNJUK RESOLUSI OPERATOR';
                    }
                }

                // Anomalies List
                var anomaliesContainer = document.getElementById('wb-anomalies-list');
                if (anomaliesContainer) {
                    anomaliesContainer.innerHTML = '';
                    if (integ.anomalies && integ.anomalies.length > 0) {
                        anomaliesContainer.style.display = 'flex';
                        integ.anomalies.forEach(function (ano) {
                            var anoItem = document.createElement('div');
                            anoItem.className = 'small text-danger bg-danger bg-opacity-10 border border-danger-subtle p-2 rounded font-monospace';
                            anoItem.style.fontSize = '10px';
                            anoItem.innerHTML = `<i class="fas fa-triangle-exclamation me-1"></i> <strong>[${ano.code}]</strong> ${ano.description}`;
                            anomaliesContainer.appendChild(anoItem);
                        });
                    } else {
                        anomaliesContainer.style.display = 'none';
                    }
                }

                // Source Asset
                if (document.getElementById('wb-source-code')) document.getElementById('wb-source-code').innerText = src.kode_asset || '-';
                if (document.getElementById('wb-source-name')) document.getElementById('wb-source-name').innerText = src.nama_asset || '-';
                if (document.getElementById('wb-source-jenis')) document.getElementById('wb-source-jenis').innerText = src.jenis_asset || '-';
                if (document.getElementById('wb-source-section')) document.getElementById('wb-source-section').innerText = prop.section_name || '-';
                if (document.getElementById('wb-source-const')) document.getElementById('wb-source-const').innerText = src.construction_name || '-';
                if (document.getElementById('wb-source-coords')) {
                    document.getElementById('wb-source-coords').innerText = (src.latitude && src.longitude) ? `${src.latitude.toFixed(6)}, ${src.longitude.toFixed(6)}` : '-';
                }
                if (document.getElementById('wb-source-status')) document.getElementById('wb-source-status').innerText = src.status || 'NORMAL';

                // Target Asset
                if (document.getElementById('wb-target-code')) document.getElementById('wb-target-code').innerText = tgt.kode_asset || '-';
                if (document.getElementById('wb-target-name')) document.getElementById('wb-target-name').innerText = tgt.nama_asset || '-';
                if (document.getElementById('wb-target-jenis')) document.getElementById('wb-target-jenis').innerText = tgt.jenis_asset || '-';
                if (document.getElementById('wb-target-section')) document.getElementById('wb-target-section').innerText = prop.section_name || '-';
                if (document.getElementById('wb-target-const')) document.getElementById('wb-target-const').innerText = tgt.construction_name || '-';
                if (document.getElementById('wb-target-coords')) {
                    document.getElementById('wb-target-coords').innerText = (tgt.latitude && tgt.longitude) ? `${tgt.latitude.toFixed(6)}, ${tgt.longitude.toFixed(6)}` : '-';
                }
                if (document.getElementById('wb-target-status')) document.getElementById('wb-target-status').innerText = tgt.status || 'NORMAL';

                // Specifications
                if (document.getElementById('wb-prop-conductor')) document.getElementById('wb-prop-conductor').innerText = `${prop.proposed_conductor_type || ''} ${prop.proposed_conductor_size || ''}`;
                if (document.getElementById('wb-prop-distance')) document.getElementById('wb-prop-distance').innerText = `${prop.proposed_distance || 0} meter`;
                if (document.getElementById('wb-prop-confidence')) document.getElementById('wb-prop-confidence').innerText = `${((prop.confidence_score || 0) * 100).toFixed(1)}% (${prop.classification || ''})`;
                if (document.getElementById('wb-prop-natural-key')) document.getElementById('wb-prop-natural-key').innerText = prop.natural_key || '-';
                if (document.getElementById('wb-prop-engine')) document.getElementById('wb-prop-engine').innerText = `${prop.proposal_source || 'DETERMINISTIC_ENGINE'} (${prop.engine_version || 'TL-01-V2.0'})`;

                // Evidence JSON & Audit Receipt
                if (document.getElementById('wb-evidence-json')) {
                    document.getElementById('wb-evidence-json').innerText = evid.raw || JSON.stringify(evid.structured || {}, null, 2);
                }
                if (document.getElementById('wb-audit-fingerprint')) {
                    document.getElementById('wb-audit-fingerprint').innerText = receipt.sha256_fingerprint || '(In-memory receipt preview generated)';
                }

                // Authoritative Comparison
                var authCountBadge = document.getElementById('wb-authoritative-count-badge');
                var authEmpty      = document.getElementById('wb-authoritative-empty');
                var authTbody      = document.getElementById('wb-authoritative-tbody');

                var records = comp.comparison_records || [];
                if (authCountBadge) authCountBadge.innerText = `${records.length} Ditemukan`;

                if (records.length === 0) {
                    if (authEmpty) authEmpty.style.display = 'block';
                    if (authTbody) authTbody.innerHTML = '';
                } else {
                    if (authEmpty) authEmpty.style.display = 'none';
                    if (authTbody) {
                        authTbody.innerHTML = '';
                        records.forEach(function (r) {
                            var scopeBadge = (r.scope_category === 'LEGACY_AUTHORITATIVE')
                                ? '<span class="badge bg-secondary font-monospace" style="font-size: 9px;"><i class="fas fa-landmark me-1"></i> LEGACY-AUTH</span>'
                                : '<span class="badge bg-primary font-monospace" style="font-size: 9px;"><i class="fas fa-wand-magic-sparkles me-1"></i> PROPOSAL-GOV</span>';

                            var matchBadge = r.is_exact_endpoints
                                ? '<span class="badge bg-success font-monospace" style="font-size: 9px;">✓ EXACT</span>'
                                : '<span class="badge bg-light text-muted border font-monospace" style="font-size: 9px;">SEKSI SAMA</span>';

                            var tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td class="font-monospace">#${r.transline_id}</td>
                                <td class="font-monospace fw-bold">${r.transline_code || '-'}</td>
                                <td>${scopeBadge}</td>
                                <td class="font-monospace">${r.length_meters}m</td>
                                <td class="font-monospace ${r.distance_delta > 10 ? 'text-danger fw-bold' : 'text-success'}">Δ ${r.distance_delta}m</td>
                                <td class="font-monospace">${r.conductor}</td>
                                <td>${matchBadge}</td>
                                <td><span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0" style="font-size: 9px;">${r.status}</span></td>
                            `;
                            authTbody.appendChild(tr);
                        });
                    }
                }

                // Map visual highlight
                if (prop.proposed_geometry && Array.isArray(prop.proposed_geometry.coordinates) && prop.proposed_geometry.coordinates.length >= 2) {
                    var latLngs = prop.proposed_geometry.coordinates.map(pt => [pt[1], pt[0]]);
                    highlightProposalOnMap(latLngs, prop);
                } else if (src.latitude && src.longitude && tgt.latitude && tgt.longitude) {
                    var latLngs = [
                        [src.latitude, src.longitude],
                        [tgt.latitude, tgt.longitude]
                    ];
                    highlightProposalOnMap(latLngs, prop);
                }
            })
            .catch(err => {
                if (loadingEl) loadingEl.style.display = 'none';
                if (errorEl) {
                    errorEl.style.display = 'block';
                    document.getElementById('wb-error-msg').innerText = 'Kendala komunikasi workbench: ' + err.message;
                }
            });
    };

    // ========================================================
    // ⚡ TL-03 & TL-02: AI-ASSISTED JTM TOPOLOGY RECONSTRUCTION UI
    // ========================================================
    var aiModalInstance = null;

    // Tab switching controls between TL-04, TL-03, and TL-02
    function setupAiEngineTabs() {
        var btnTl04Tab = document.getElementById('tab-btn-tl04');
        var btnTl03Tab = document.getElementById('tab-btn-tl03');
        var btnTl02Tab = document.getElementById('tab-btn-tl02');
        var btnExecTl04 = document.getElementById('btn-execute-transline-tl04');
        var btnExecTl04Loop = document.getElementById('btn-execute-transline-tl04-loop');
        var btnExecTl03 = document.getElementById('btn-execute-transline-tl03');
        var btnExecTl02 = document.getElementById('btn-execute-transline-ai');
        var footerMeta = document.getElementById('ai-engine-footer-meta');

        if (btnTl04Tab) {
            btnTl04Tab.addEventListener('shown.bs.tab', function () {
                if (btnExecTl04) btnExecTl04.style.display = 'inline-block';
                if (btnExecTl04Loop) btnExecTl04Loop.style.display = 'inline-block';
                if (btnExecTl03) btnExecTl03.style.display = 'none';
                if (btnExecTl02) btnExecTl02.style.display = 'none';
                if (footerMeta) footerMeta.innerHTML = '<i class="fas fa-project-diagram text-success me-1"></i> Engine: TL-04 Network Promotion v1.0.0 · Dynamic Graph Batch (Max 10)';
            });
        }
        if (btnTl03Tab) {
            btnTl03Tab.addEventListener('shown.bs.tab', function () {
                if (btnExecTl04) btnExecTl04.style.display = 'none';
                if (btnExecTl04Loop) btnExecTl04Loop.style.display = 'none';
                if (btnExecTl03) btnExecTl03.style.display = 'inline-block';
                if (btnExecTl02) btnExecTl02.style.display = 'none';
                if (footerMeta) footerMeta.innerHTML = '<i class="fas fa-network-wired text-info me-1"></i> Engine: TL-03 Advanced Reconstruction v1.0.0 · Max Batch: 10';
            });
        }
        if (btnTl02Tab) {
            btnTl02Tab.addEventListener('shown.bs.tab', function () {
                if (btnExecTl04) btnExecTl04.style.display = 'none';
                if (btnExecTl04Loop) btnExecTl04Loop.style.display = 'none';
                if (btnExecTl03) btnExecTl03.style.display = 'none';
                if (btnExecTl02) btnExecTl02.style.display = 'inline-block';
                if (footerMeta) footerMeta.innerHTML = '<i class="fas fa-bolt text-warning me-1"></i> Engine: TL-02 Progressive Completion v1.0.0 · Max Batch: 10';
            });
        }
    }

    function openTranslineAiModal() {
        if (!currentFeederId) {
            alert('Pilih penyulang terlebih dahulu sebelum membuka Rekonstruksi JTM AI.');
            return;
        }

        var modalEl = document.getElementById('modal-transline-ai-completion');
        if (!modalEl) return;

        if (!aiModalInstance) {
            aiModalInstance = new bootstrap.Modal(modalEl);
            setupAiEngineTabs();
        }

        var feederTitle = currentFeederName || `Penyulang #${currentFeederId}`;
        var feederBadge = document.getElementById('ai-modal-feeder-badge');
        if (feederBadge) feederBadge.textContent = feederTitle;

        var loadingEl = document.getElementById('ai-loading');
        var contentEl = document.getElementById('ai-tab-content');
        var alertBox  = document.getElementById('ai-alert-box');
        if (alertBox) alertBox.style.display = 'none';

        if (loadingEl) loadingEl.style.display = 'block';
        if (contentEl) contentEl.style.display = 'none';

        aiModalInstance.show();

        Promise.allSettled([
            fetchJson(`<?= site_url('gis/api-transline-tl04-preview') ?>?penyulang_id=${encodeURIComponent(currentFeederId)}`),
            fetchJson(`<?= site_url('gis/api-transline-tl03-preview') ?>?penyulang_id=${encodeURIComponent(currentFeederId)}`),
            fetchJson(`<?= site_url('gis/api-transline-ai-preview') ?>?penyulang_id=${encodeURIComponent(currentFeederId)}`)
        ]).then(results => {
            if (loadingEl) loadingEl.style.display = 'none';
            if (contentEl) contentEl.style.display = 'block';

            var resTl04 = (results[0].status === 'fulfilled') ? results[0].value : null;
            var resTl03 = (results[1].status === 'fulfilled') ? results[1].value : null;
            var resTl02 = (results[2].status === 'fulfilled') ? results[2].value : null;

            if (resTl04 && resTl04.status === 'success') {
                populateTranslineTl04Modal(resTl04);
            } else if (resTl04 && resTl04.message) {
                if (alertBox) {
                    alertBox.className = 'alert alert-danger py-2 px-3 small my-2';
                    alertBox.textContent = 'TL-04 Preview: ' + resTl04.message;
                    alertBox.style.display = 'block';
                }
            }

            if (resTl03 && resTl03.status === 'success') {
                populateTranslineTl03Modal(resTl03);
            }

            if (resTl02 && resTl02.status === 'success') {
                populateTranslineAiModal(resTl02);
            }
        }).catch(err => {
            if (loadingEl) loadingEl.style.display = 'none';
            if (alertBox) {
                alertBox.className = 'alert alert-danger py-2 px-3 small my-2';
                alertBox.textContent = 'Kendala koneksi AI Preview: ' + err.message;
                alertBox.style.display = 'block';
            }
        });
    }

    function populateTranslineTl04Modal(data) {
        var inv = data.inventory || {};
        var sum = data.summary || {};
        var batch = data.defensible_batch_preview || [];
        var diag = data.isolated_asset_diagnostics || {};
        var diagBreakdown = diag.summary_breakdown || {};
        var diagDetails = diag.assets_detail || [];

        // Inventory cards
        if (document.getElementById('tl04-stat-total-assets')) {
            document.getElementById('tl04-stat-total-assets').textContent = inv.total_master_assets || 0;
        }
        if (document.getElementById('tl04-stat-active-translines')) {
            document.getElementById('tl04-stat-active-translines').textContent = inv.authoritative_translines || 0;
        }
        if (document.getElementById('tl04-stat-connected')) {
            document.getElementById('tl04-stat-connected').textContent = inv.connected_assets_count || 0;
        }
        if (document.getElementById('tl04-stat-isolated')) {
            document.getElementById('tl04-stat-isolated').textContent = inv.isolated_assets_count || 0;
        }
        if (document.getElementById('tl04-stat-defensible')) {
            document.getElementById('tl04-stat-defensible').textContent = sum.defensible_candidates_count || batch.length;
        }
        if (document.getElementById('tl04-stat-promoted')) {
            document.getElementById('tl04-stat-promoted').textContent = sum.promoted_candidates_count || 0;
        }
        if (document.getElementById('tl04-candidate-count')) {
            document.getElementById('tl04-candidate-count').textContent = `${batch.length} Segmen Siap`;
        }

        // Diagnostic summary badges
        if (document.getElementById('tl04-diag-auto-badge')) {
            document.getElementById('tl04-diag-auto-badge').textContent = `Auto: ${diagBreakdown.AUTO_COMPLETE || 0}`;
        }
        if (document.getElementById('tl04-diag-high-badge')) {
            document.getElementById('tl04-diag-high-badge').textContent = `High Conf: ${diagBreakdown.HIGH_CONFIDENCE_REVIEW || 0}`;
        }
        if (document.getElementById('tl04-diag-review-badge')) {
            document.getElementById('tl04-diag-review-badge').textContent = `Review: ${diagBreakdown.REVIEW_REQUIRED || 0}`;
        }
        if (document.getElementById('tl04-diag-blocked-badge')) {
            var blockedCount = (diagBreakdown.BLOCKED || 0) + (diagBreakdown.NO_VALID_NETWORK_RELATIONSHIP || 0) + (diagBreakdown.AMBIGUOUS || 0);
            document.getElementById('tl04-diag-blocked-badge').textContent = `Isolated/Blocked: ${blockedCount}`;
        }

        // Populate TL-04 Candidates Table
        var tbody = document.getElementById('tl04-candidate-tbody');
        var btnExec = document.getElementById('btn-execute-transline-tl04');
        var btnExecLoop = document.getElementById('btn-execute-transline-tl04-loop');
        if (tbody) {
            tbody.innerHTML = '';
            if (batch.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-check-circle text-success me-1"></i> Tidak ada kandidat TL-04 defensibel yang tersisa. Topologi telah stabil secara alami. Node terisolasi yang tersisa tetap aman terisolasi.</td></tr>`;
                if (btnExec) btnExec.disabled = true;
                if (btnExecLoop) btnExecLoop.disabled = true;
            } else {
                if (btnExec) btnExec.disabled = false;
                if (btnExecLoop) btnExecLoop.disabled = false;
                batch.forEach(function (c, idx) {
                    var tr = document.createElement('tr');
                    var typeLabel = c.candidate_type || 'MAINLINE';
                    var typeBadgeClass = 'bg-secondary';
                    if (typeLabel.includes('ANCHOR')) typeBadgeClass = 'bg-primary';
                    else if (typeLabel.includes('T_OFF') || typeLabel.includes('TOFF')) typeBadgeClass = 'bg-warning text-dark';
                    else if (typeLabel.includes('CHAIN')) typeBadgeClass = 'bg-info text-dark';

                    var promoBadge = c.promoted
                        ? `<span class="badge" style="background-color: #10b981; color: #fff; font-size: 9px;"><i class="fas fa-check-double me-1"></i>PROMOTED (+${c.network_score} Net)</span>`
                        : (c.total_score >= 90
                            ? `<span class="badge bg-primary font-monospace" style="font-size: 9px;"><i class="fas fa-star me-1"></i>SCORE ≥ 90</span>`
                            : `<span class="badge bg-light text-muted border" style="font-size: 9px;">STANDARD</span>`);

                    tr.innerHTML = `
                        <td class="font-monospace text-muted">${idx + 1}</td>
                        <td>
                            <strong class="font-monospace text-dark">${c.source_asset_code}</strong>
                            <span class="badge bg-light text-secondary border ms-1" style="font-size: 9px;">d=${c.source_degree}</span>
                            <div class="text-muted" style="font-size: 9px;">${c.source_asset_name || '-'}</div>
                        </td>
                        <td>
                            <strong class="font-monospace text-dark">${c.target_asset_code}</strong>
                            <span class="badge bg-light text-secondary border ms-1" style="font-size: 9px;">d=${c.target_degree}</span>
                            <div class="text-muted" style="font-size: 9px;">${c.target_asset_name || '-'}</div>
                        </td>
                        <td class="font-monospace fw-bold text-success">${Number(c.distance_meters).toFixed(1)} m</td>
                        <td>
                            <span class="badge font-monospace" style="background-color: #047857; color: #fff; font-size: 10px;">${c.total_score}/100</span>
                        </td>
                        <td>${promoBadge}</td>
                        <td><span class="badge ${typeBadgeClass} font-monospace" style="font-size: 9px;">${typeLabel}</span></td>
                        <td>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0" style="font-size: 9px;">
                                <i class="fas fa-shield-check me-1"></i> PASS 24 GATES
                            </span>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }
        }

        // Populate Isolated Asset Diagnostics Table
        var diagTbody = document.getElementById('tl04-diagnostics-tbody');
        if (diagTbody) {
            diagTbody.innerHTML = '';
            if (diagDetails.length === 0) {
                diagTbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-2">Semua aset telah terhubung ke jaringan JTM.</td></tr>`;
            } else {
                diagDetails.slice(0, 30).forEach(function (d) {
                    var tr = document.createElement('tr');
                    var classBadge = 'bg-secondary';
                    if (d.classification === 'AUTO_COMPLETE') classBadge = 'bg-success';
                    else if (d.classification === 'HIGH_CONFIDENCE_REVIEW') classBadge = 'bg-info text-dark';
                    else if (d.classification === 'REVIEW_REQUIRED') classBadge = 'bg-warning text-dark';
                    else if (d.classification === 'BLOCKED') classBadge = 'bg-danger';

                    tr.innerHTML = `
                        <td class="font-monospace"><strong>#${d.asset_id}</strong></td>
                        <td><span class="badge ${classBadge}" style="font-size: 9px;">${d.classification}</span></td>
                        <td class="text-muted" style="font-size: 10px;">${d.reason}</td>
                        <td class="text-center font-monospace">${d.candidate_count}</td>
                    `;
                    diagTbody.appendChild(tr);
                });
                if (diagDetails.length > 30) {
                    var trMore = document.createElement('tr');
                    trMore.innerHTML = `<td colspan="4" class="text-center text-muted py-1" style="font-size: 9px;">... dan ${diagDetails.length - 30} aset terisolasi lainnya (diagnosa lengkap di audit server).</td>`;
                    diagTbody.appendChild(trMore);
                }
            }
        }
    }

    function populateTranslineTl03Modal(data) {
        var inv = data.inventory || {};
        var sum = data.summary || {};
        var batch = data.auto_complete_batch_preview || [];
        var diag = data.isolated_asset_diagnostics || {};
        var diagBreakdown = diag.summary_breakdown || {};
        var diagDetails = diag.assets_detail || [];

        // Inventory cards
        if (document.getElementById('tl03-stat-total-assets')) {
            document.getElementById('tl03-stat-total-assets').textContent = inv.total_master_assets || 0;
        }
        if (document.getElementById('tl03-stat-active-translines')) {
            document.getElementById('tl03-stat-active-translines').textContent = inv.authoritative_translines || 0;
        }
        if (document.getElementById('tl03-stat-connected')) {
            document.getElementById('tl03-stat-connected').textContent = inv.connected_assets_count || 0;
        }
        if (document.getElementById('tl03-stat-isolated')) {
            document.getElementById('tl03-stat-isolated').textContent = inv.isolated_assets_count || 0;
        }
        if (document.getElementById('tl03-stat-auto-eligible')) {
            document.getElementById('tl03-stat-auto-eligible').textContent = sum.auto_complete_candidates || 0;
        }
        if (document.getElementById('tl03-stat-chains')) {
            document.getElementById('tl03-stat-chains').textContent = sum.isolated_chains_detected || 0;
        }
        if (document.getElementById('tl03-candidate-count')) {
            document.getElementById('tl03-candidate-count').textContent = `${batch.length} Segmen Siap`;
        }

        // Diagnostic summary badges
        if (document.getElementById('tl03-diag-auto-badge')) {
            document.getElementById('tl03-diag-auto-badge').textContent = `Auto: ${diagBreakdown.AUTO_COMPLETE || 0}`;
        }
        if (document.getElementById('tl03-diag-high-badge')) {
            document.getElementById('tl03-diag-high-badge').textContent = `High Conf: ${diagBreakdown.HIGH_CONFIDENCE_REVIEW || 0}`;
        }
        if (document.getElementById('tl03-diag-review-badge')) {
            document.getElementById('tl03-diag-review-badge').textContent = `Review: ${diagBreakdown.REVIEW_REQUIRED || 0}`;
        }
        if (document.getElementById('tl03-diag-blocked-badge')) {
            var blockedCount = (diagBreakdown.BLOCKED || 0) + (diagBreakdown.NO_VALID_NETWORK_RELATIONSHIP || 0) + (diagBreakdown.AMBIGUOUS || 0);
            document.getElementById('tl03-diag-blocked-badge').textContent = `Isolated/Blocked: ${blockedCount}`;
        }

        // Populate TL-03 Candidates Table
        var tbody = document.getElementById('tl03-candidate-tbody');
        var btnExec = document.getElementById('btn-execute-transline-tl03');
        if (tbody) {
            tbody.innerHTML = '';
            if (batch.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-check-circle text-success me-1"></i> Tidak ada kandidat auto-complete dengan skor ≥ 90 saat ini. Semua node terisolasi lainnya memerlukan review manusia atau tetap aman terisolasi.</td></tr>`;
                if (btnExec) btnExec.disabled = true;
            } else {
                if (btnExec) btnExec.disabled = false;
                batch.forEach(function (c, idx) {
                    var tr = document.createElement('tr');
                    var typeLabel = c.candidate_type || 'RECONSTRUCTION';
                    var typeBadgeClass = 'bg-secondary';
                    if (typeLabel.includes('ANCHOR')) typeBadgeClass = 'bg-primary';
                    else if (typeLabel.includes('TOFF')) typeBadgeClass = 'bg-warning text-dark';
                    else if (typeLabel.includes('CHAIN')) typeBadgeClass = 'bg-info text-dark';

                    tr.innerHTML = `
                        <td class="font-monospace text-muted">${idx + 1}</td>
                        <td>
                            <strong class="font-monospace text-dark">${c.source_asset_code}</strong>
                            <span class="badge bg-light text-secondary border ms-1" style="font-size: 9px;">d=${c.source_degree}</span>
                            <div class="text-muted" style="font-size: 9px;">${c.source_asset_name || '-'}</div>
                        </td>
                        <td>
                            <strong class="font-monospace text-dark">${c.target_asset_code}</strong>
                            <span class="badge bg-light text-secondary border ms-1" style="font-size: 9px;">d=${c.target_degree}</span>
                            <div class="text-muted" style="font-size: 9px;">${c.target_asset_name || '-'}</div>
                        </td>
                        <td class="font-monospace fw-bold text-info">${Number(c.distance_meters).toFixed(1)} m</td>
                        <td>
                            <span class="badge bg-info text-dark font-monospace" style="font-size: 10px;">${c.total_score}/100</span>
                        </td>
                        <td><span class="badge ${typeBadgeClass} font-monospace" style="font-size: 9px;">${typeLabel}</span></td>
                        <td>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0" style="font-size: 9px;">
                                <i class="fas fa-shield-check me-1"></i> PASS 24 GATES
                            </span>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }
        }

        // Populate Isolated Asset Diagnostics Table
        var diagTbody = document.getElementById('tl03-diagnostics-tbody');
        if (diagTbody) {
            diagTbody.innerHTML = '';
            if (diagDetails.length === 0) {
                diagTbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-2">Semua aset telah terhubung ke jaringan JTM.</td></tr>`;
            } else {
                diagDetails.slice(0, 30).forEach(function (d) {
                    var tr = document.createElement('tr');
                    var classBadge = 'bg-secondary';
                    if (d.classification === 'AUTO_COMPLETE') classBadge = 'bg-success';
                    else if (d.classification === 'HIGH_CONFIDENCE_REVIEW') classBadge = 'bg-info text-dark';
                    else if (d.classification === 'REVIEW_REQUIRED') classBadge = 'bg-warning text-dark';
                    else if (d.classification === 'BLOCKED') classBadge = 'bg-danger';

                    tr.innerHTML = `
                        <td class="font-monospace"><strong>#${d.asset_id}</strong></td>
                        <td><span class="badge ${classBadge}" style="font-size: 9px;">${d.classification}</span></td>
                        <td class="text-muted" style="font-size: 10px;">${d.reason}</td>
                        <td class="text-center font-monospace">${d.candidate_count}</td>
                    `;
                    diagTbody.appendChild(tr);
                });
                if (diagDetails.length > 30) {
                    var trMore = document.createElement('tr');
                    trMore.innerHTML = `<td colspan="4" class="text-center text-muted py-1" style="font-size: 9px;">... dan ${diagDetails.length - 30} aset terisolasi lainnya (diagnosa lengkap tersedia di audit server).</td>`;
                    diagTbody.appendChild(trMore);
                }
            }
        }
    }

    function populateTranslineAiModal(data) {
        var summary = data.summary || {};
        var pilotBatch = data.pilot_batch || [];

        if (document.getElementById('ai-stat-total-assets')) {
            document.getElementById('ai-stat-total-assets').textContent = summary.total_master_assets || 0;
        }
        if (document.getElementById('ai-stat-active-translines')) {
            document.getElementById('ai-stat-active-translines').textContent = summary.active_translines || 0;
        }
        if (document.getElementById('ai-stat-auto-eligible')) {
            document.getElementById('ai-stat-auto-eligible').textContent = summary.auto_eligible_count || 0;
        }
        if (document.getElementById('ai-stat-review-required')) {
            document.getElementById('ai-stat-review-required').textContent = summary.review_required_count || 0;
        }
        if (document.getElementById('ai-pilot-badge-count')) {
            document.getElementById('ai-pilot-badge-count').textContent = `${pilotBatch.length} Segmen Siap`;
        }

        var tbody = document.getElementById('ai-candidate-tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        var btnExec = document.getElementById('btn-execute-transline-ai');

        if (pilotBatch.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-check-circle text-success me-1"></i> Semua segmen berkeyakinan tinggi telah diselesaikan atau tidak ada kandidat eligible saat ini.</td></tr>`;
            if (btnExec) btnExec.disabled = true;
            return;
        }

        if (btnExec) btnExec.disabled = false;

        pilotBatch.forEach(function (c, idx) {
            var tr = document.createElement('tr');
            var confPct = ((c.confidence_score || 0) * 100).toFixed(0);
            var isAnchor = (c.candidate_type === 'ANCHOR_CONTINUATION');
            var badgeType = isAnchor 
                ? `<span class="badge bg-primary font-monospace" style="font-size: 9px;"><i class="fas fa-anchor me-1"></i>ANCHOR DEGREE-1</span>`
                : `<span class="badge bg-secondary font-monospace" style="font-size: 9px;">NOMINAL ROAD</span>`;

            tr.innerHTML = `
                <td class="font-monospace text-muted">${idx + 1}</td>
                <td>
                    <strong class="font-monospace text-dark">${c.source_asset_code}</strong>
                    <div class="text-muted" style="font-size: 9px;">${c.source_asset_name || '-'}</div>
                </td>
                <td>
                    <strong class="font-monospace text-dark">${c.target_asset_code}</strong>
                    <div class="text-muted" style="font-size: 9px;">${c.target_asset_name || '-'}</div>
                </td>
                <td class="font-monospace fw-bold text-primary">${Number(c.geodesic_distance_m).toFixed(1)} m</td>
                <td>
                    <span class="badge bg-success font-monospace" style="font-size: 10px;">${confPct}%</span>
                </td>
                <td>${badgeType}</td>
                <td>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0" style="font-size: 9px;">
                        <i class="fas fa-shield-check me-1"></i> PASS 24 GATES
                    </span>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function executeTranslineTl03Batch() {
        if (!currentFeederId) return;
        var btn = document.getElementById('btn-execute-transline-tl03');
        var alertBox = document.getElementById('ai-alert-box');

        if (!confirm('Apakah Anda yakin ingin merekonstruksi hingga 10 segmen transline JTM dengan TL-03 Engine? Tindakan ini atomik dan dilindungi 24 safety gates serta zero-write firewall.')) {
            return;
        }

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Merekonstruksi Topologi...';
        }

        fetchJson('<?= site_url('gis/api-transline-tl03-complete') ?>', {
            method: 'POST',
            body: JSON.stringify({
                penyulang_id: currentFeederId,
                auto_batch: true
            })
        })
        .then(res => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-network-wired text-warning me-1"></i> Eksekusi Rekonstruksi TL-03 (10 Segmen)';
            }

            if (res && res.status === 'success') {
                if (alertBox) {
                    alertBox.className = 'alert alert-success py-2 px-3 small my-2';
                    alertBox.innerHTML = `<strong><i class="fas fa-check-circle me-1"></i> Rekonstruksi Berhasil!</strong> ${res.message || (res.created_count + ' Transline berhasil direkonstruksi.')} Provenance: <code>${res.provenance_run_id || 'RUN:TL03'}</code>`;
                    alertBox.style.display = 'block';
                }

                // Refresh GIS map layers to immediately display new authoritative lines
                loadGisNetworkOnDemand(true);
                loadGisProposalsOnDemand();

                // Re-fetch preview in modal
                setTimeout(function () {
                    openTranslineAiModal();
                }, 1200);
            } else {
                if (alertBox) {
                    alertBox.className = 'alert alert-danger py-2 px-3 small my-2';
                    alertBox.innerHTML = `<strong><i class="fas fa-exclamation-triangle me-1"></i> Rekonstruksi Dibatalkan:</strong> ${res.message || 'Eksekusi dibatalkan oleh Safety Gate.'}`;
                    alertBox.style.display = 'block';
                }
            }
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-network-wired text-warning me-1"></i> Eksekusi Rekonstruksi TL-03 (10 Segmen)';
            }
            if (alertBox) {
                alertBox.className = 'alert alert-danger py-2 px-3 small my-2';
                alertBox.innerHTML = `<strong><i class="fas fa-exclamation-circle me-1"></i> Error:</strong> ${err.message}`;
                alertBox.style.display = 'block';
            }
        });
    }

    function executeTranslineAiBatch() {
        if (!currentFeederId) return;
        var btn = document.getElementById('btn-execute-transline-ai');
        var alertBox = document.getElementById('ai-alert-box');

        if (!confirm('Apakah Anda yakin ingin mengeksekusi 10 segmen transline otomatis ini ke database resmi? Tindakan ini dilindungi 24 safety gates dan atomic transaction.')) {
            return;
        }

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengeksekusi...';
        }

        fetchJson('<?= site_url('gis/api-transline-ai-complete') ?>', {
            method: 'POST',
            body: JSON.stringify({
                penyulang_id: currentFeederId,
                auto_pilot: true
            })
        })
        .then(res => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-bolt text-warning me-1"></i> Eksekusi Auto-Complete (10 Segmen)';
            }

            if (res && res.status === 'success') {
                if (alertBox) {
                    alertBox.className = 'alert alert-success py-2 px-3 small my-2';
                    alertBox.innerHTML = `<strong><i class="fas fa-check-circle me-1"></i> Berhasil!</strong> ${res.message || (res.created_count + ' Transline berhasil dibuat.')} Provenance: <code>${res.provenance_run_id || 'RUN:TL02'}</code>`;
                    alertBox.style.display = 'block';
                }

                // Refresh GIS map layers to immediately display new authoritative lines
                loadGisNetworkOnDemand(true);
                loadGisProposalsOnDemand();

                // Re-fetch preview in modal
                setTimeout(function () {
                    openTranslineAiModal();
                }, 1200);
            } else {
                if (alertBox) {
                    alertBox.className = 'alert alert-danger py-2 px-3 small my-2';
                    alertBox.innerHTML = `<strong><i class="fas fa-exclamation-triangle me-1"></i> Gagal:</strong> ${res.message || 'Eksekusi dibatalkan oleh Safety Gate.'}`;
                    alertBox.style.display = 'block';
                }
            }
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-bolt text-warning me-1"></i> Eksekusi Auto-Complete (10 Segmen)';
            }
            if (alertBox) {
                alertBox.className = 'alert alert-danger py-2 px-3 small my-2';
                alertBox.innerHTML = `<strong><i class="fas fa-exclamation-circle me-1"></i> Error:</strong> ${err.message}`;
                alertBox.style.display = 'block';
            }
        });
    }

    function executeTranslineTl04(mode) {
        if (!currentFeederId) return;
        var btnBatch = document.getElementById('btn-execute-transline-tl04');
        var btnLoop = document.getElementById('btn-execute-transline-tl04-loop');
        var alertBox = document.getElementById('ai-alert-box');

        var isProgressive = (mode === 'progressive');
        var confirmMsg = isProgressive
            ? 'Apakah Anda yakin ingin menjalankan Loop Rekonstruksi TL-04 hingga stabil alami? Setiap batch (maks 10 edge) akan dieksekusi dalam transaksi atomik independen dengan rekalkulasi graf dinamis, 24 safety gates, dan zero-write firewall.'
            : 'Apakah Anda yakin ingin mengeksekusi 1 batch (hingga 10 edge) TL-04? Tindakan ini atomik dan dilindungi 24 safety gates serta zero-write firewall.';

        if (!confirm(confirmMsg)) {
            return;
        }

        if (btnBatch) btnBatch.disabled = true;
        if (btnLoop) btnLoop.disabled = true;

        if (isProgressive && btnLoop) {
            btnLoop.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menjalankan Loop...';
        } else if (btnBatch) {
            btnBatch.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengeksekusi Batch...';
        }

        fetchJson('<?= site_url('gis/api-transline-tl04-complete') ?>', {
            method: 'POST',
            body: JSON.stringify({
                penyulang_id: currentFeederId,
                mode: mode,
                max_batch: 10
            })
        })
        .then(res => {
            if (btnBatch) {
                btnBatch.disabled = false;
                btnBatch.innerHTML = '<i class="fas fa-play text-warning me-1"></i> Eksekusi 1 Batch TL-04 (Maks 10 Segmen)';
            }
            if (btnLoop) {
                btnLoop.disabled = false;
                btnLoop.innerHTML = '<i class="fas fa-sync text-warning me-1"></i> Loop Hingga Stabil';
            }

            if (res && res.status === 'success') {
                if (alertBox) {
                    alertBox.className = 'alert alert-success py-2 px-3 small my-2';
                    var countMsg = isProgressive 
                        ? `${res.total_created_count || 0} Transline berhasil direkonstruksi dalam ${res.iterations_run || 0} batch!`
                        : `${res.created_count || 0} Transline berhasil direkonstruksi!`;
                    alertBox.innerHTML = `<strong><i class="fas fa-check-circle me-1"></i> Rekonstruksi TL-04 Berhasil!</strong> ${countMsg} Zero-Write Invariant: <code>PASS</code>`;
                    alertBox.style.display = 'block';
                }

                // Refresh GIS map layers to immediately display new authoritative lines
                loadGisNetworkOnDemand(true);
                loadGisProposalsOnDemand();

                // Re-fetch preview in modal
                setTimeout(function () {
                    openTranslineAiModal();
                }, 1200);
            } else {
                if (alertBox) {
                    alertBox.className = 'alert alert-danger py-2 px-3 small my-2';
                    alertBox.innerHTML = `<strong><i class="fas fa-exclamation-triangle me-1"></i> Rekonstruksi Dibatalkan:</strong> ${res.message || 'Eksekusi dibatalkan oleh Safety Gate.'}`;
                    alertBox.style.display = 'block';
                }
            }
        })
        .catch(err => {
            if (btnBatch) {
                btnBatch.disabled = false;
                btnBatch.innerHTML = '<i class="fas fa-play text-warning me-1"></i> Eksekusi 1 Batch TL-04 (Maks 10 Segmen)';
            }
            if (btnLoop) {
                btnLoop.disabled = false;
                btnLoop.innerHTML = '<i class="fas fa-sync text-warning me-1"></i> Loop Hingga Stabil';
            }
            if (alertBox) {
                alertBox.className = 'alert alert-danger py-2 px-3 small my-2';
                alertBox.innerHTML = `<strong><i class="fas fa-exclamation-circle me-1"></i> Error:</strong> ${err.message}`;
                alertBox.style.display = 'block';
            }
        });
    }

    bindPointerSafeTap('btn-open-transline-ai', function () {
        openTranslineAiModal();
    }, 'OPEN_TRANSLINE_AI_MODAL');

    bindPointerSafeTap('btn-execute-transline-tl04', function () {
        executeTranslineTl04('batch');
    }, 'EXECUTE_TRANSLINE_TL04_BATCH');

    bindPointerSafeTap('btn-execute-transline-tl04-loop', function () {
        executeTranslineTl04('progressive');
    }, 'EXECUTE_TRANSLINE_TL04_LOOP');

    bindPointerSafeTap('btn-execute-transline-tl03', function () {
        executeTranslineTl03Batch();
    }, 'EXECUTE_TRANSLINE_TL03_BATCH');

    bindPointerSafeTap('btn-execute-transline-ai', function () {
        executeTranslineAiBatch();
    }, 'EXECUTE_TRANSLINE_AI_BATCH');

    bindPointerSafeTap('btn-open-proposals-drawer', function () {
        loadGisProposalsOnDemand();
        safeShowOffcanvas('offcanvas-proposals-drawer');
    }, 'OPEN_PROPOSALS_DRAWER');

    // Auto-fetch proposals on initial page ready to hydrate badge and queue
    setTimeout(function () {
        loadGisProposalsOnDemand();
    }, 400);

});
</script>
<?= $this->endSection() ?>
