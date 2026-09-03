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
            <div class="card border rounded-3 p-2 mb-2 bg-white shadow-sm">
                <div class="small fw-bold text-secondary mb-2 text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                    <i class="fas fa-network-wired text-primary me-1"></i> Konteks Jaringan
                </div>
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="p-1 bg-light rounded border">
                            <span class="d-block text-muted" style="font-size: 10px;">ULP</span>
                            <span id="ctx-drawer-ulp" class="d-block fw-bold text-dark text-truncate" style="font-size: 11px;">-</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-1 bg-light rounded border">
                            <span class="d-block text-muted" style="font-size: 10px;">Penyulang</span>
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
            </div>

            <!-- Section 3: Standar Konstruksi PLN -->
            <div class="card border rounded-3 p-2 mb-2 bg-white shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="small fw-bold text-secondary text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                        <i class="fas fa-hammer text-warning me-1"></i> Standar Konstruksi
                    </div>
                    <span id="ctx-drawer-const-status" class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0" style="font-size: 10px;">VERIFIED</span>
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
        currentFeederId = feederId;
        currentFeederName = opt.dataset.feederName || opt.text;
        currentUlpName = opt.dataset.ulpName || 'PLN ULP';

        document.getElementById('topbar-feeder-title').textContent = currentFeederName;
        document.getElementById('topbar-ulp-subtitle').textContent = currentUlpName;

        document.getElementById('gis-setup-screen').style.display = 'none';
        document.getElementById('gis-workspace-screen').style.display = 'block';

        initializeMapWorkspace();
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

        translinePolylineLayer = L.featureGroup().addTo(map);
        previewConnectionLayer = L.featureGroup().addTo(map);
        segmentEditLayer = L.featureGroup().addTo(map);
        findingLayer = L.featureGroup().addTo(map);

        map.on('click', function () {
            if (translineEditor.state === TRANSLINE_STATE.IDLE) {
                closeAssetQuickCard();
            }
        });

        isolateGisUiFromLeaflet();
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
            if (e.originalEvent) {
                L.DomEvent.stopPropagation(e.originalEvent);
            }
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
    // 🎯 MAP-02: READ-ONLY ASSET CONTEXT DRAWER DISPATCHER
    // ========================================================
    function openAssetContextDrawer(assetId, fallbackProps, svgPath, coords) {
        if (!assetId || isNaN(assetId) || assetId <= 0) {
            console.warn('[MAP-02] Invalid assetId for context drawer:', assetId);
            return;
        }

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

        // Initial placeholder from map props if provided
        if (fallbackProps) {
            document.getElementById('ctx-drawer-title').textContent = fallbackProps.nama_asset || ('Asset #' + assetId);
            document.getElementById('ctx-drawer-code').textContent = fallbackProps.kode_asset || ('ID: ' + assetId);
            if (svgPath) {
                document.getElementById('ctx-drawer-img').src = svgPath;
            }
        }

        safeShowOffcanvas('offcanvas-asset-context-drawer');

        // Deterministic server-authoritative AJAX call
        $.ajax({
            url: "<?= site_url('ajax/network/asset-context') ?>/" + assetId,
            type: "GET",
            dataType: "json",
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(res) {
                $loading.hide();

                if (!res || res.status === 'INVALID_ASSET' || res.status === 'FORBIDDEN') {
                    $error.show();
                    $('#ctx-drawer-error-msg').text(res.message || 'Aset tidak valid atau akses ditolak.');
                    return;
                }

                $content.show();

                // 1. Asset Identity
                const a = res.asset || {};
                document.getElementById('ctx-drawer-title').textContent = a.nama_asset || ('Asset #' + assetId);
                document.getElementById('ctx-drawer-code').textContent = a.kode_asset || ('ID: ' + assetId);
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

                // 2. Network Context
                const net = res.network || {};
                document.getElementById('ctx-drawer-ulp').textContent = (net.ulp && net.ulp.nama_ulp) ? net.ulp.nama_ulp : '-';
                document.getElementById('ctx-drawer-penyulang').textContent = (net.penyulang && net.penyulang.nama_penyulang) ? net.penyulang.nama_penyulang : '-';
                document.getElementById('ctx-drawer-section').textContent = (net.section && net.section.nama_section) ? net.section.nama_section : '-';

                // 3. Construction
                if (res.status === 'NO_CONSTRUCTION' || !res.construction) {
                    $constBox.hide();
                    $constEmpty.show();
                    $('#ctx-drawer-const-status').attr('class', 'badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0').text('UNMAPPED');
                } else {
                    const c = res.construction;
                    $constEmpty.hide();
                    $constBox.show();
                    document.getElementById('ctx-drawer-const-code').textContent = c.code || '-';
                    document.getElementById('ctx-drawer-const-name').textContent = c.name || '-';
                    document.getElementById('ctx-drawer-const-sub').textContent = `${c.construction_family || 'JTM'} • ${c.voltage_level || '20kV'}`;
                    $('#ctx-drawer-const-status').attr('class', 'badge bg-success-subtle text-success border border-success-subtle px-2 py-0').text('VERIFIED');
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
                const navUrl = (res.navigation && res.navigation.create_temuan_url) ? res.navigation.create_temuan_url : `<?= site_url('temuan/create') ?>?asset_id=${assetId}`;
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
            var rawCoords = tl.coordinates || tl.geometry;
            if (typeof rawCoords === 'string') {
                try { rawCoords = JSON.parse(rawCoords); } catch (err) { rawCoords = []; }
            }

            if (!Array.isArray(rawCoords) || rawCoords.length < 2) return;

            var validPts = rawCoords.filter(pt => Array.isArray(pt) && pt.length >= 2 && isValidLatLng(pt[1], pt[0]));
            if (validPts.length < 2) return;

            var latLngs = validPts.map(pt => [pt[1], pt[0]]);
            var conductorLabel = tl.conductor_label || `${tl.conductor_type || 'AAAC'} ${tl.conductor_size || '150 mm²'}`;
            var lengthMeter = tl.length_meter || tl.distance_meters || 0;

            var visiblePoly = L.polyline(latLngs, {
                color: '#0284c7',
                weight: 3.5,
                opacity: 0.9,
                lineJoin: 'round',
                interactive: false
            });

            visiblePoly.feature = {
                properties: {
                    transline_id: tId,
                    transline_code: tl.transline_code || `TL-${currentFeederId}-${tId}`,
                    source_asset_id: tl.source_asset_id || tl.from_asset_id,
                    target_asset_id: tl.target_asset_id || tl.to_asset_id,
                }
            };

            visiblePoly.bindTooltip(`⚡ <strong>${conductorLabel}</strong> (${lengthMeter}m)`, {
                sticky: true,
                className: 'font-monospace small'
            });
            translinePolylineLayer.addLayer(visiblePoly);

            // Invisible hit-layer for mobile & touch accuracy (24px target)
            var hitPoly = L.polyline(latLngs, {
                color: '#0284c7',
                weight: 24,
                opacity: 0.001,
                lineJoin: 'round',
                interactive: true
            });

            hitPoly.on('click', function (evt) {
                L.DomEvent.stopPropagation(evt);
                if (window.activeSegmentHighlight) {
                    window.activeSegmentHighlight.setStyle({ color: '#0284c7', weight: 3.5, opacity: 0.9 });
                }
                window.activeSegmentHighlight = visiblePoly;
                visiblePoly.setStyle({ color: '#f59e0b', weight: 5.5, opacity: 1 });

                var fromId = tl.source_asset_id || tl.from_asset_id;
                var fromAsset = (currentData.features || []).find(f => (f.properties && f.properties.id === fromId));
                if (fromAsset) {
                    var norm = normalizeAssetFeature(fromAsset);
                    var svg = resolveAssetSvgPath(norm.properties, norm.visual);
                    openAssetQuickCard(norm.properties, svg, norm.geometry.coordinates);
                }
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
        loadGisNetworkOnDemand(true);
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

});
</script>
<?= $this->endSection() ?>
