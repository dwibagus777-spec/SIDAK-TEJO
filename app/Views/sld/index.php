<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Single Line Diagram | SIDAK TEJO') ?></title>
    <!-- Bootstrap 5 & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    </style>
</head>
<body class="p-3">

<div class="container-fluid px-2">
    <!-- Header Banner -->
    <div class="sld-header-card mb-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary px-2 py-1">SLD-05R</span>
                <h4 class="fw-bold m-0 text-white">Single Line Diagram (SLD) Engine</h4>
            </div>
            <div class="text-white-50 small">
                Engineering Drawing Readability &bull; PLN/IEC Distribution Symbology &bull; Strict Read-Only (&Delta; = 0)
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

    <!-- Toolbar & Legend Bar -->
    <div class="sld-toolbar mb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <!-- Controls -->
        <div class="d-flex align-items-center gap-2">
            <!-- Mode Switcher -->
            <div class="btn-group btn-group-sm" role="group" aria-label="Mode Tampilan">
                <button type="button" class="btn btn-primary active" id="btn-mode-engineering" onclick="setRendererMode('ENGINEERING')">
                    <i class="fa-solid fa-code-fork me-1"></i> Mode Engineering
                </button>
                <button type="button" class="btn btn-outline-primary" id="btn-mode-simplified" onclick="setRendererMode('SIMPLIFIED')">
                    <i class="fa-solid fa-diagram-project me-1"></i> Mode Simplified
                </button>
            </div>

            <!-- GTT Toggle -->
            <div class="form-check form-switch ms-2 d-flex align-items-center gap-1 mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="toggle-gtt" checked onchange="toggleGttVisibility(this.checked)">
                <label class="form-check-label small text-nowrap text-white-50" for="toggle-gtt">Tampilkan Trafo GTT</label>
            </div>
        </div>

        <!-- Zoom & Viewport Controls (Engineering Viewport System) -->
        <div class="d-flex align-items-center gap-1">
            <button type="button" class="btn btn-dark btn-sm border-secondary text-white" onclick="sldEngine.zoomIn()" title="Perbesar (Zoom In)">
                <i class="fa-solid fa-magnifying-glass-plus me-1"></i> Zoom In
            </button>
            <button type="button" class="btn btn-dark btn-sm border-secondary text-white" onclick="sldEngine.zoomOut()" title="Perkecil (Zoom Out)">
                <i class="fa-solid fa-magnifying-glass-minus me-1"></i> Zoom Out
            </button>
            <button type="button" class="btn btn-dark btn-sm border-secondary text-info" onclick="sldEngine.fitMainNetwork()" title="Fokuskan ke Penyulang Utama (Fit Main Network)">
                <i class="fa-solid fa-bullseye me-1"></i> Fit Main Network
            </button>
            <button type="button" class="btn btn-dark btn-sm border-secondary text-warning" onclick="sldEngine.fitAll()" title="Lihat Seluruh Zona Jaringan (Fit All)">
                <i class="fa-solid fa-expand me-1"></i> Fit All
            </button>
            <button type="button" class="btn btn-dark btn-sm border-secondary text-white-50" onclick="sldEngine.resetView()" title="Kembali ke Tampilan Awal (Reset View)">
                <i class="fa-solid fa-rotate-left me-1"></i> Reset
            </button>
        </div>
    </div>

    <!-- Symbology Legend Bar -->
    <div class="d-flex flex-wrap align-items-center px-2 py-1 mb-2 bg-dark rounded border border-secondary border-opacity-25">
        <span class="text-white-50 small me-2 fw-bold text-uppercase" style="font-size: 0.72rem;">Legenda Simbol:</span>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #ffffff; border: 2px solid #0f172a; position: relative;">
                <span style="position: absolute; top: 1px; left: 2px; width: 0; height: 0; border-left: 4px solid transparent; border-right: 4px solid transparent; border-bottom: 7px solid #0f172a;"></span>
            </span>
            <span><strong>GI Incomer</strong> (Substation Origin)</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #0f172a; height: 3.5px; width: 16px; border-radius: 1px;"></span>
            <span><strong>Rute Utama</strong> (C15-01 3.2px)</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #fef3c7; border: 1.5px solid #d97706; transform: rotate(45deg);"></span>
            <span><strong>PMS (UNKNOWN)</strong> (?)</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: conic-gradient(#0f172a 0deg 90deg, #ffffff 90deg 180deg, #0f172a 180deg 270deg, #ffffff 270deg 360deg); border: 1px solid #0f172a; border-radius: 50%;"></span>
            <span><strong>LBS / LBSM</strong> (PLN/IEC)</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #ffffff; border: 1.5px solid #0f172a; display: inline-flex; align-items: center; justify-content: center; font-size: 7px; font-weight: bold; color: #0f172a;">&#9986;</span>
            <span><strong>Recloser</strong> (Bowtie)</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #f0fdf4; border: 1.5px solid #059669; border-radius: 3px; display: inline-flex; align-items: center; justify-content: center; font-size: 8px; font-weight: bold; color: #059669;">1T</span>
            <span><strong>GTT Cantol</strong> (1 Tiang)</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #f0f9ff; border: 1.5px solid #0284c7; border-radius: 3px; display: inline-flex; align-items: center; justify-content: center; font-size: 8px; font-weight: bold; color: #0284c7;">2T</span>
            <span><strong>GTT Portal</strong> (2 Tiang)</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #dc2626; border-radius: 50%; width: 10px; height: 10px;"></span>
            <span><strong>Branch</strong> (Junction)</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="background: #dc2626; width: 3px; height: 14px;"></span>
            <span><strong>Terminal</strong> (Dead-End)</span>
        </div>
        <div class="sld-legend-item">
            <span class="legend-indicator" style="border: 1px dashed #94a3b8; border-radius: 50%;"></span>
            <span><strong>Fragmen / Terisolasi</strong></span>
        </div>
    </div>

    <!-- Main Interactive SLD Canvas -->
    <div id="sld-canvas-container" class="sld-canvas-box shadow-sm">
        <!-- SVG Canvas rendered dynamically by sld-renderer-engine.js -->
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/sld/sld-renderer-engine.js?v=sld05r_' . (file_exists(FCPATH . 'assets/js/sld/sld-renderer-engine.js') ? filemtime(FCPATH . 'assets/js/sld/sld-renderer-engine.js') : '20260916')) ?>"></script>
<script>
    let sldEngine = null;

    document.addEventListener('DOMContentLoaded', function() {
        const layoutApiUrl = '<?= esc($layoutApiUrl) ?>';
        sldEngine = new SldRendererEngine('sld-canvas-container', {
            apiUrl: layoutApiUrl,
            defaultMode: 'ENGINEERING',
            showGtt: true
        });

        // Load authoritative layout
        sldEngine.load();
    });

    function setRendererMode(mode) {
        if (!sldEngine) return;
        sldEngine.setMode(mode);

        const btnEng = document.getElementById('btn-mode-engineering');
        const btnSimp = document.getElementById('btn-mode-simplified');

        if (mode === 'ENGINEERING') {
            btnEng.className = 'btn btn-primary active';
            btnSimp.className = 'btn btn-outline-primary';
        } else {
            btnEng.className = 'btn btn-outline-primary';
            btnSimp.className = 'btn btn-primary active';
        }
    }

    function toggleGttVisibility(show) {
        if (!sldEngine) return;
        sldEngine.toggleGtt(show);
    }
</script>

</body>
</html>
