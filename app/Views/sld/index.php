<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Single Line Diagram | SIDAK TEJO') ?></title>
    <!-- Bootstrap 5 & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Leaflet CSS for Mode B (GIS Map) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --sld-bg-dark: #090e1d;
            --sld-card-bg: #0f172a;
            --sld-border: #1e293b;
            --sld-accent: #38bdf8;
        }
        body {
            background-color: #0b1120;
            color: #f1f5f9;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }
        .sld-header-card {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 16px 20px;
        }
        .sld-toolbar {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 8px 12px;
        }
        .sld-canvas-box {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            height: calc(100vh - 270px);
            min-height: 520px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1);
        }
        .sld-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            color: #94a3b8;
            margin-right: 14px;
        }
        .sld-legend-item strong {
            color: #f8fafc;
        }
        .legend-indicator {
            width: 14px;
            height: 14px;
            display: inline-block;
            border-radius: 3px;
        }
        .sld-node:focus {
            outline: 2px solid #38bdf8 !important;
            outline-offset: 4px;
        }
        .sld-node:hover {
            filter: drop-shadow(0 0 6px rgba(56, 189, 248, 0.8));
        }
        .sld-node-selected {
            filter: drop-shadow(0 0 8px #f59e0b) !important;
        }

        /* SLD-05T Refinement 5: Browser Print-Ready Styles */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .sld-header-card, .sld-toolbar, .sld-legend-bar, #sld-drawer, .btn, .badge, #sld-sheet-controls {
                display: none !important;
            }
            .sld-canvas-box {
                height: 100vh !important;
                min-height: 100vh !important;
                border: none !important;
                box-shadow: none !important;
                background: #ffffff !important;
            }
            #sld-svg-canvas {
                background: #ffffff !important;
            }
            .sld-mode-indicator {
                display: none !important;
            }
        }
    </style>
</head>
<body class="p-3">

<div class="container-fluid px-2">
    <!-- Header Banner -->
    <div class="sld-header-card mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-success px-2 py-1">SLD-05S</span>
                <span class="badge bg-secondary px-2 py-1" style="font-size: 0.75rem;">SLD-05R COMPLIANT</span>
                <h4 class="fw-bold m-0 text-white">Dynamic Single Line Diagram & GIS Context Engine</h4>
                <span id="sld-fingerprint-badge" class="badge bg-dark border border-secondary text-info px-2 py-1 ms-2" style="font-family: monospace; font-size: 0.75rem;">
                    <i class="fa-solid fa-fingerprint me-1"></i>FINGERPRINT: INITIALIZING...
                </span>
            </div>
            <div class="text-white-50 small">
                Dynamic Read Model Discovery &bull; Tri-Mode (Engineering, GIS Map, Hybrid) &bull; Strict Read-Only (&Delta; = 0)
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <!-- Feeder Selector -->
            <div class="d-flex align-items-center gap-2">
                <label for="select-feeder" class="text-white-50 small text-nowrap"><i class="fa-solid fa-plug-circle-bolt me-1 text-warning"></i>Penyulang:</label>
                <select id="select-feeder" class="form-select form-select-sm bg-dark text-white border-secondary fw-bold" style="min-width: 220px;"
                        onchange="location.href='<?= site_url('sld/view') ?>/' + this.value;">
                    <?php foreach ($feeders as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= ($selectedFeederId == $f['id']) ? 'selected' : '' ?>>
                            [<?= esc($f['kode_penyulang'] ?? 'PYL') ?>] <?= esc($f['nama_penyulang']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <a href="<?= site_url('asset-intelligence') ?>" class="btn btn-outline-secondary btn-sm text-nowrap">
                <i class="fa-solid fa-database me-1"></i> Asset Truth
            </a>
        </div>
    </div>

    <!-- Dynamic Auto-Refresh Alert Banner (Amendment 8) -->
    <div id="sld-refresh-alert" class="alert alert-info py-2 px-3 d-none align-items-center justify-content-between mb-2 shadow-sm" style="font-size: 0.85rem; border-left: 4px solid #0284c7;">
        <span><i class="fa-solid fa-arrows-rotate fa-spin me-2 text-primary"></i><strong>Data Jaringan Diperbarui:</strong> Terdeteksi perubahan authoritative database. Memperbarui diagram SLD secara otomatis...</span>
        <button type="button" class="btn-close btn-sm" onclick="document.getElementById('sld-refresh-alert').classList.add('d-none')"></button>
    </div>

    <!-- Toolbar & Legend Bar -->
    <div class="sld-toolbar mb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <!-- Controls -->
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Tri-Mode Switcher -->
            <div class="btn-group btn-group-sm" role="group" aria-label="Mode Tampilan">
                <button type="button" class="btn btn-primary active" id="btn-mode-hybrid" onclick="setRendererMode('HYBRID')">
                    <i class="fa-solid fa-road me-1"></i> Mode Hybrid (CAD + Jalan)
                </button>
                <button type="button" class="btn btn-outline-primary" id="btn-mode-engineering" onclick="setRendererMode('ENGINEERING')">
                    <i class="fa-solid fa-bolt me-1"></i> Mode Engineering
                </button>
                <button type="button" class="btn btn-outline-primary" id="btn-mode-gis" onclick="setRendererMode('GIS')">
                    <i class="fa-solid fa-map-location-dot me-1"></i> Mode GIS Map
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btn-mode-simplified" onclick="setRendererMode('SIMPLIFIED')">
                    <i class="fa-solid fa-diagram-project me-1"></i> Mode Simplified
                </button>
            </div>

            <!-- Layer Switches (SLD-05T Layer Controls) -->
            <div class="d-flex align-items-center gap-2 ms-1 flex-wrap">
                <!-- Finding Layer Toggle -->
                <div class="form-check form-switch d-flex align-items-center gap-1 mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="toggle-findings" onchange="toggleFindingsVisibility(this.checked)">
                    <label class="form-check-label small text-nowrap text-warning fw-bold" for="toggle-findings">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>Temuan
                    </label>
                </div>

                <!-- Pole Labels Toggle -->
                <div class="form-check form-switch d-flex align-items-center gap-1 mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="toggle-labels" checked onchange="toggleAssetLabelsVisibility(this.checked)">
                    <label class="form-check-label small text-nowrap text-white-50" for="toggle-labels">Label Aset</label>
                </div>

                <!-- Span Lengths Toggle -->
                <div class="form-check form-switch d-flex align-items-center gap-1 mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="toggle-spans" checked onchange="toggleSpanLengthsVisibility(this.checked)">
                    <label class="form-check-label small text-nowrap text-white-50" for="toggle-spans">Panjang Bentang</label>
                </div>

                <!-- Road Corridors Toggle -->
                <div class="form-check form-switch d-flex align-items-center gap-1 mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="toggle-roads" checked onchange="toggleRoadNamesVisibility(this.checked)">
                    <label class="form-check-label small text-nowrap text-white-50" for="toggle-roads">Nama Jalan</label>
                </div>

                <!-- GTT Toggle -->
                <div class="form-check form-switch d-flex align-items-center gap-1 mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="toggle-gtt" checked onchange="toggleGttVisibility(this.checked)">
                    <label class="form-check-label small text-nowrap text-white-50" for="toggle-gtt">GTT</label>
                </div>
            </div>
        </div>

        <!-- Sheet Selector Controls (SLD-05T Sheet Composer) -->
        <div class="d-flex align-items-center gap-1 flex-wrap">
            <span class="text-white-50 small me-1 fw-bold text-nowrap"><i class="fa-solid fa-sheet-plastic me-1 text-info"></i>Lembar CAD:</span>
            <div id="sld-sheet-controls" class="btn-group btn-group-sm" role="group" aria-label="Kontrol Lembar CAD">
                <button type="button" class="btn btn-sm btn-info text-dark fw-bold" onclick="sldEngine.setSheet(null)">
                    <i class="fa-solid fa-map me-1"></i> Penuh (Fit Main)
                </button>
                <button type="button" class="btn btn-sm btn-dark border-secondary text-white" onclick="sldEngine.setSheet(1)">
                    <i class="fa-solid fa-file-lines me-1"></i> Lembar 01
                </button>
                <button type="button" class="btn btn-sm btn-dark border-secondary text-white" onclick="sldEngine.setSheet(2)">
                    <i class="fa-solid fa-file-lines me-1"></i> Lembar 02
                </button>
                <button type="button" class="btn btn-sm btn-dark border-secondary text-white" onclick="sldEngine.setSheet(3)">
                    <i class="fa-solid fa-file-lines me-1"></i> Lembar 03
                </button>
                <button type="button" class="btn btn-sm btn-dark border-secondary text-white" onclick="sldEngine.setSheet(4)">
                    <i class="fa-solid fa-file-lines me-1"></i> Lembar 04
                </button>
            </div>
        </div>

        <!-- Zoom & Viewport Controls (Engineering Viewport System) -->
        <div class="d-flex align-items-center gap-1">
            <button type="button" class="btn btn-dark btn-sm border-secondary text-info fw-bold" onclick="sldEngine.fitMainNetwork()" title="Fokuskan ke Penyulang Utama (Fit Main Network)">
                <i class="fa-solid fa-bullseye me-1"></i> Fit Main
            </button>
            <button type="button" class="btn btn-dark btn-sm border-secondary text-warning" onclick="sldEngine.fitAll()" title="Lihat Seluruh Zona Jaringan (Fit All)">
                <i class="fa-solid fa-expand me-1"></i> Fit All
            </button>
            <button type="button" class="btn btn-dark btn-sm border-secondary text-white" onclick="sldEngine.zoomIn()" title="Perbesar (Zoom In)">
                <i class="fa-solid fa-magnifying-glass-plus me-1"></i> +
            </button>
            <button type="button" class="btn btn-dark btn-sm border-secondary text-white" onclick="sldEngine.zoomOut()" title="Perkecil (Zoom Out)">
                <i class="fa-solid fa-magnifying-glass-minus me-1"></i> -
            </button>
            <button type="button" class="btn btn-dark btn-sm border-secondary text-white-50" onclick="sldEngine.resetView()" title="Kembali ke Tampilan Awal (Reset View)">
                <i class="fa-solid fa-rotate-left me-1"></i> Reset
            </button>
            <button type="button" class="btn btn-dark btn-sm border-secondary text-info" onclick="window.print()" title="Cetak Single Line Diagram (Print CAD)">
                <i class="fa-solid fa-print me-1"></i> Cetak
            </button>
        </div>
    </div>

    <!-- Symbology Legend Bar -->
    <div class="sld-legend-bar d-flex flex-wrap align-items-center px-2 py-1 mb-2 bg-dark rounded border border-secondary border-opacity-25">
        <span class="text-white-50 small me-2 fw-bold text-uppercase" style="font-size: 0.72rem;">Legenda Simbol (14 Peran GIS):</span>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #ffffff; border: 2px solid #dc2626; position: relative;">
                <span style="position: absolute; top: 1px; left: 2px; width: 0; height: 0; border-left: 4px solid transparent; border-right: 4px solid transparent; border-bottom: 7px solid #dc2626;"></span>
            </span>
            <span><strong>GI Incomer</strong> (Substation)</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #0f172a; height: 3.5px; width: 16px; border-radius: 1px;"></span>
            <span><strong>Rute Utama</strong> (C15-01)</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: conic-gradient(#0f172a 0deg 90deg, #ffffff 90deg 180deg, #0f172a 180deg 270deg, #ffffff 270deg 360deg); border: 1px solid #0f172a; border-radius: 50%;"></span>
            <span><strong>LBS / LBSM</strong> (PLN/IEC)</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #ffffff; border: 1.5px solid #0f172a; display: inline-flex; align-items: center; justify-content: center; font-size: 7px; font-weight: bold; color: #dc2626;">/</span>
            <span><strong>PMCB</strong> CB</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #f5f3ff; border: 1.5px solid #7c3aed; display: inline-flex; align-items: center; justify-content: center; font-size: 7px; font-weight: bold; color: #7c3aed;">&#9986;</span>
            <span><strong>Recloser</strong></span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #f0f9ff; border: 1.5px solid #0284c7; display: inline-flex; align-items: center; justify-content: center; font-size: 7px; font-weight: bold; color: #0284c7;">&Delta;</span>
            <span><strong>AVS</strong></span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #f0fdf4; border: 1.5px solid #059669; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 7px; font-weight: bold; color: #059669;">G</span>
            <span><strong>PGS</strong> Gas</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #fef3c7; border: 1.5px solid #d97706; transform: rotate(45deg);"></span>
            <span><strong>PMS</strong> (UNKNOWN ?)</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #f0fdf4; border: 1.5px solid #059669; border-radius: 3px; display: inline-flex; align-items: center; justify-content: center; font-size: 8px; font-weight: bold; color: #059669;">1T</span>
            <span><strong>GTT Cantol</strong></span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #f0f9ff; border: 1.5px solid #0284c7; border-radius: 3px; display: inline-flex; align-items: center; justify-content: center; font-size: 8px; font-weight: bold; color: #0284c7;">2T</span>
            <span><strong>GTT Portal</strong></span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #0f172a; border-radius: 50%; width: 7px; height: 7px; display: inline-block;"></span>
            <span><strong>Line Pole</strong> (#ID)</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #dc2626; border-radius: 50%; width: 9px; height: 9px;"></span>
            <span><strong>Branch</strong></span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #dc2626; width: 3px; height: 14px;"></span>
            <span><strong>Terminal</strong></span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #ef4444; border-radius: 50%; width: 10px; height: 10px; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; font-size: 8px; font-weight: bold;">!</span>
            <span><strong>Temuan</strong> (Overlay)</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #22c55e; border-radius: 50%; width: 9px; height: 9px; display: inline-block;"></span>
            <span><strong>● CONNECTED</strong></span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #fef2f2; border: 1.5px dashed #ef4444; border-radius: 50%; width: 10px; height: 10px; display: inline-block;"></span>
            <span><strong>○ ISOLATED</strong></span>
        </div>
    </div>

    <!-- Main Interactive SLD Canvas -->
    <div id="sld-canvas-container" class="sld-canvas-box shadow-sm">
        <div id="sld-svg-container" style="width: 100%; height: 100%; position: absolute; top: 0; left: 0;"></div>
        <div id="sld-gis-map-container" style="width: 100%; height: 100%; position: absolute; top: 0; left: 0; display: none; z-index: 5;"></div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Leaflet JS for Mode B (GIS Map) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= base_url('assets/js/sld/sld-renderer-engine.js?v=sld05t_' . (file_exists(FCPATH . 'assets/js/sld/sld-renderer-engine.js') ? filemtime(FCPATH . 'assets/js/sld/sld-renderer-engine.js') : '20260917')) ?>"></script>
<script>
    let sldEngine = null;

    document.addEventListener('DOMContentLoaded', function() {
        const layoutApiUrl = '<?= esc($layoutApiUrl) ?>';
        const fingerprintApiUrl = '<?= site_url('api/sld/feeder/' . $selectedFeederId . '/fingerprint') ?>';
        const sheetsApiUrl = '<?= esc($sheetsApiUrl ?? '') ?>';
        const findingsApiUrl = '<?= esc($findingsApiUrl ?? '') ?>';

        sldEngine = new SldRendererEngine('sld-canvas-container', {
            apiUrl: layoutApiUrl,
            fingerprintApiUrl: fingerprintApiUrl,
            sheetsApiUrl: sheetsApiUrl,
            findingsApiUrl: findingsApiUrl,
            feederId: <?= (int)$selectedFeederId ?>,
            defaultMode: 'HYBRID',
            showGtt: true,
            showFindings: false
        });

        // Load authoritative layout, sheets, and findings
        sldEngine.load();

        // SLD-05S-VH: Automatic Resize Observer to preserve target occupancy upon container resize
        const canvasContainer = document.getElementById('sld-canvas-container');
        if (canvasContainer && window.ResizeObserver) {
            let resizeTimer = null;
            const ro = new ResizeObserver(() => {
                if (resizeTimer) clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    if (sldEngine && typeof sldEngine.handleResize === 'function') {
                        sldEngine.handleResize();
                    }
                }, 100);
            });
            ro.observe(canvasContainer);
        }
    });

    function setRendererMode(mode) {
        if (!sldEngine) return;
        sldEngine.setMode(mode);

        const btnEng = document.getElementById('btn-mode-engineering');
        const btnHyb = document.getElementById('btn-mode-hybrid');
        const btnGis = document.getElementById('btn-mode-gis');
        const btnSimp = document.getElementById('btn-mode-simplified');

        [btnEng, btnHyb, btnGis, btnSimp].forEach(b => {
            if (b) {
                b.classList.remove('active', 'btn-primary');
                b.classList.add('btn-outline-primary');
            }
        });

        if (mode === 'ENGINEERING' && btnEng) {
            btnEng.classList.add('active', 'btn-primary');
            btnEng.classList.remove('btn-outline-primary');
        } else if (mode === 'HYBRID' && btnHyb) {
            btnHyb.classList.add('active', 'btn-primary');
            btnHyb.classList.remove('btn-outline-primary');
        } else if (mode === 'GIS' && btnGis) {
            btnGis.classList.add('active', 'btn-primary');
            btnGis.classList.remove('btn-outline-primary');
        } else if (mode === 'SIMPLIFIED' && btnSimp) {
            btnSimp.classList.add('active', 'btn-primary');
            btnSimp.classList.remove('btn-outline-secondary', 'btn-outline-primary');
        }
    }

    function toggleGttVisibility(show) {
        if (!sldEngine) return;
        sldEngine.toggleGtt(show);
    }

    function toggleFindingsVisibility(show) {
        if (!sldEngine) return;
        sldEngine.toggleFindings(show);
    }

    function toggleAssetLabelsVisibility(show) {
        if (!sldEngine) return;
        sldEngine.toggleAssetLabels(show);
    }

    function toggleSpanLengthsVisibility(show) {
        if (!sldEngine) return;
        sldEngine.toggleSpanLengths(show);
    }

    function toggleRoadNamesVisibility(show) {
        if (!sldEngine) return;
        sldEngine.toggleRoadNames(show);
    }
</script>

</body>
</html>
