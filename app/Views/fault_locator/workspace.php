<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Fault Locator Command Center') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            padding: 16px;
            text-align: center;
        }
        .kpi-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--fl-accent);
        }
        .kpi-label {
            font-size: 0.8rem;
            color: #94a3b8;
            text-transform: uppercase;
        }
        .badge-resolved {
            background-color: var(--fl-success);
            color: #fff;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .badge-ambiguous {
            background-color: var(--fl-warning);
            color: #000;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .badge-unresolved {
            background-color: var(--fl-danger);
            color: #fff;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .hash-code {
            font-family: monospace;
            font-size: 0.8rem;
            color: #38bdf8;
            background: rgba(15, 23, 42, 0.8);
            padding: 2px 6px;
            border-radius: 4px;
        }
        .sld-viewport {
            background: #090d16;
            border: 1px dashed var(--fl-border);
            border-radius: 8px;
            height: 280px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-custom navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#">
            <i class="fa-solid fa-crosshairs text-danger"></i>
            <span class="fw-bold">SIDAK TEJO</span>
            <span class="text-secondary">|</span>
            <span class="fs-6 text-light">Track D3: Fault Locator Command Center Workspace</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-dark border border-danger text-danger">
                <i class="fa-solid fa-microchip"></i> Engine: <?= esc($summary['engine_version']) ?> (IMMUTABLE)
            </span>
            <span class="badge bg-dark border border-info text-info">
                <i class="fa-solid fa-layer-group"></i> Snapshot: <?= esc($summary['topology_snapshot']) ?>
            </span>
            <span class="badge bg-success text-white">
                <i class="fa-solid fa-shield-check"></i> Δtopology = 0
            </span>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 mt-4">

    <!-- Top KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kpi-box">
                <div class="kpi-value text-info"><?= number_format($summary['active_translines']) ?> TL</div>
                <div class="kpi-label">Authoritative Translines (9,418.37m)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-box">
                <div class="kpi-value text-success"><?= number_format($summary['active_assets']) ?></div>
                <div class="kpi-label">Active Assets Network Truth</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-box">
                <div class="kpi-value text-warning">DISTANCE ALONG NETWORK</div>
                <div class="kpi-label">Traversal Engine Rule</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-box">
                <div class="kpi-value text-light">3 OUTCOMES</div>
                <div class="kpi-label">RESOLVED / AMBIGUOUS / UNRESOLVED</div>
            </div>
        </div>
    </div>

    <!-- Main Workspace Grid -->
    <div class="row g-4">

        <!-- Left Panel: Data Gangguan (Telemetry Form) -->
        <div class="col-lg-4">
            <div class="card-fl">
                <div class="card-fl-header">
                    <span><i class="fa-solid fa-bolt text-warning me-2"></i> DATA GANGGUAN (TELEMETRY INPUT)</span>
                    <span class="badge bg-secondary">FLI-1.0.0 INPUT</span>
                </div>
                <div class="card-body p-4">
                    <form id="faultForm">
                        <div class="mb-3">
                            <label class="form-label text-secondary small">PENYULANG (FEEDER)</label>
                            <select class="form-select bg-dark text-light border-secondary" id="feederId">
                                <option value="F-CANDRAMAS" selected>F-CANDRAMAS (Penyulang Candramas)</option>
                                <option value="F-GIRI">F-GIRI (Penyulang Giri)</option>
                                <option value="F-KOTA">F-KOTA (Penyulang Kota)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">WAKTU GANGGUAN (TIMESTAMP)</label>
                            <input type="datetime-local" class="form-control bg-dark text-light border-secondary" id="faultTimestamp" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label text-secondary small">FASA GANGGUAN</label>
                                <select class="form-select bg-dark text-light border-secondary" id="faultPhase">
                                    <option value="R-N" selected>R - N (Single Phase)</option>
                                    <option value="S-N">S - N</option>
                                    <option value="T-N">T - N</option>
                                    <option value="R-S">R - S (Phase-to-Phase)</option>
                                    <option value="3PH">3PH (Three Phase)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-secondary small">ARUS GANGGUAN (A)</label>
                                <input type="number" class="form-control bg-dark text-light border-secondary" id="faultCurrentA" value="1450" step="10">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">RELAY DISTANCE FROM DEVICE (METERS)</label>
                            <div class="input-group">
                                <input type="number" class="form-control bg-dark text-light border-secondary fw-bold text-info" id="relayDistanceM" value="1250" step="50">
                                <span class="input-group-text bg-secondary text-light border-secondary">Meter</span>
                            </div>
                            <div class="form-text text-secondary small">Distance along network traversal from protective device.</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary small">PROTECTIVE DEVICE (RECLOSER / PMCB)</label>
                            <select class="form-select bg-dark text-light border-secondary" id="deviceAssetId">
                                <option value="AST-REC-005" selected>AST-REC-005 (Recloser GI Candramas)</option>
                                <option value="AST-PMCB-001">AST-PMCB-001 (PMCB Outgoing GI)</option>
                                <option value="AST-LBS-012">AST-LBS-012 (Load Break Switch Motorized)</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-danger w-100 py-2 fw-bold" id="btnLocateFault">
                            <i class="fa-solid fa-crosshairs me-2"></i> LOCATE FAULT (FLI-1.0.0)
                        </button>
                    </form>
                </div>
            </div>

            <!-- Hard Safety Guards Box -->
            <div class="card-fl">
                <div class="card-fl-header">
                    <span><i class="fa-solid fa-shield-halved text-success me-2"></i> D3 Hard Safety Guards</span>
                    <span class="badge bg-success">15/15 ACTIVE</span>
                </div>
                <div class="card-body p-3 small">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D3-G01 & G02</strong>: Route /fault-locator & Controller Active</li>
                        <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D3-G04 & G05</strong>: FLI-1.0.0 Immutable Engine & Snapshot</li>
                        <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D3-G07 & G08</strong>: Branch Ambiguity & UNRESOLVED outcomes</li>
                        <li class="mb-2"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D3-G10</strong>: Zero GIS / Asset Mutation ($\Delta = 0$)</li>
                        <li class="mb-0"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D3-G14</strong>: B6 Field Investigation Dispatch Package</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Middle & Right Panel: FLI Result & Map + SLD -->
        <div class="col-lg-8">
            
            <!-- Result Panel Card -->
            <div class="card-fl">
                <div class="card-fl-header">
                    <span><i class="fa-solid fa-square-poll-vertical text-info me-2"></i> FLI-1.0.0 FAULT LOCATION ANALYSIS RESULT</span>
                    <span id="outcomeBadge" class="badge-resolved">RESOLVED</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="bg-dark p-3 rounded-3 border border-secondary">
                                <span class="text-secondary small d-block mb-1">CASE UUID</span>
                                <code id="resCaseUuid" class="text-info fs-6"><?= esc($result['case_uuid']) ?></code>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-dark p-3 rounded-3 border border-secondary">
                                <span class="text-secondary small d-block mb-1">CONFIDENCE & RESOLUTION</span>
                                <span id="resConfidence" class="text-success fw-bold fs-5"><?= esc($result['confidence_percent']) ?>%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Details Table -->
                    <table class="table table-dark table-bordered align-middle mb-4">
                        <tbody>
                            <tr>
                                <th style="width:30%">Candidate Asset</th>
                                <td id="resCandidateAsset" class="fw-bold text-light">AST-TRF-094 (Trafo Distribusi 250kVA)</td>
                            </tr>
                            <tr>
                                <th>Transline Segment</th>
                                <td id="resSegment" class="text-info">TL-CAND-SEC01-094</td>
                            </tr>
                            <tr>
                                <th>Distance Along Network</th>
                                <td id="resDistance" class="fw-bold text-warning"><?= esc($result['relay_distance_m']) ?> Meter</td>
                            </tr>
                            <tr>
                                <th>Estimated Coordinates</th>
                                <td id="resCoords" class="hash-code">-7.9512, 112.6305</td>
                            </tr>
                            <tr>
                                <th>Analysis Explanation</th>
                                <td id="resExplanation" class="text-secondary small"><?= esc($result['explanation']) ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Dispatch Action Banner -->
                    <div class="alert alert-dark border border-danger bg-dark text-light p-3 rounded-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="badge bg-danger text-white me-2"><i class="fa-solid fa-truck-fast"></i> FIELD DISPATCH</span>
                            <strong class="text-light">Teruskan Hasil Gangguan ke Tim Lapangan (B6 Dispatch)</strong>
                        </div>
                        <button class="btn btn-danger btn-sm fw-bold" id="btnCreateDispatch">
                            <i class="fa-solid fa-paper-plane me-1"></i> Create Field Dispatch
                        </button>
                    </div>
                </div>
            </div>

            <!-- GIS MAP & SLD OVERLAY CARD -->
            <div class="card-fl">
                <div class="card-fl-header">
                    <span><i class="fa-solid fa-map-location-dot text-danger me-2"></i> SLD & Spatial Fault Point Highlight</span>
                    <span class="badge bg-danger">FAULT MARKER ACTIVE</span>
                </div>
                <div class="card-body p-3">
                    <div class="sld-viewport">
                        <svg width="100%" height="100%" viewBox="0 0 800 240">
                            <!-- Grid background -->
                            <defs>
                                <pattern id="flgrid" width="40" height="40" patternUnits="userSpaceOnUse">
                                    <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#1e293b" stroke-width="1"/>
                                </pattern>
                            </defs>
                            <rect width="100%" height="100%" fill="url(#flgrid)" />

                            <!-- Network Circuit Path (Solid Line) -->
                            <path d="M 50 120 L 250 120 L 450 120 L 650 120 L 750 120" stroke="#10b981" stroke-width="4" fill="none" />

                            <!-- Substation PMCB Node -->
                            <rect x="35" y="105" width="30" height="30" fill="#0284c7" stroke="#ffffff" stroke-width="2" rx="4"/>
                            <text x="50" y="155" fill="#94a3b8" font-size="11" text-anchor="middle">GI CANDRAMAS</text>

                            <!-- Intermediate Asset Node -->
                            <circle cx="250" cy="120" r="10" fill="#1e293b" stroke="#38bdf8" stroke-width="3"/>
                            <text x="250" y="155" fill="#cbd5e1" font-size="11" text-anchor="middle">LBS-012</text>

                            <!-- Fault Target Asset Node with Pulsing Target -->
                            <circle cx="450" cy="120" r="14" fill="#ef4444" stroke="#ffffff" stroke-width="3"/>
                            <circle cx="450" cy="120" r="24" fill="none" stroke="#ef4444" stroke-width="2" stroke-dasharray="4,4"/>
                            <text x="450" y="160" fill="#f87171" font-weight="bold" font-size="12" text-anchor="middle">🔥 FAULT POINT (AST-TRF-094)</text>

                            <!-- Recloser Node -->
                            <rect x="635" y="105" width="30" height="30" fill="#1e293b" stroke="#10b981" stroke-width="3" rx="4"/>
                            <text x="650" y="155" fill="#cbd5e1" font-size="11" text-anchor="middle">REC-005</text>

                            <!-- Distance Marker Indicator -->
                            <path d="M 50 80 L 450 80" stroke="#f59e0b" stroke-width="2" stroke-dasharray="4,4" />
                            <rect x="210" y="68" width="120" height="22" fill="#0f172a" stroke="#f59e0b" stroke-width="1" rx="4"/>
                            <text x="270" y="83" fill="#f59e0b" font-size="11" font-weight="bold" text-anchor="middle">1,250 METERS</text>
                        </svg>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentCaseUuid = '<?= esc($result['case_uuid']) ?>';

    document.getElementById('btnLocateFault').addEventListener('click', async function() {
        const payload = {
            feeder_id: document.getElementById('feederId').value,
            timestamp: document.getElementById('faultTimestamp').value,
            phase: document.getElementById('faultPhase').value,
            fault_current_a: parseFloat(document.getElementById('faultCurrentA').value),
            relay_distance_m: parseFloat(document.getElementById('relayDistanceM').value),
            device_asset_id: document.getElementById('deviceAssetId').value
        };

        try {
            const res = await fetch('/api/fault-locator/locate', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (json.status === 'success') {
                const data = json.data;
                currentCaseUuid = data.case_uuid;
                updateResultUI(data);
                alert('Fault Analysis Completed! Outcome: ' + data.outcome_status);
            }
        } catch(e) {
            console.error(e);
            alert('Failed to execute fault location calculation.');
        }
    });

    document.getElementById('btnCreateDispatch').addEventListener('click', async function() {
        if (!currentCaseUuid) {
            alert('Silakan eksekusi penentuan lokasi gangguan terlebih dahulu.');
            return;
        }
        const res = await fetch('/api/fault-locator/dispatch', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({case_uuid: currentCaseUuid})
        });
        const json = await res.json();
        if (json.status === 'success') {
            alert('B6 Field Investigation Dispatch Package Created! Dispatch ID: ' + json.data.dispatch_id);
        }
    });

    function updateResultUI(data) {
        document.getElementById('resCaseUuid').innerText = data.case_uuid;
        document.getElementById('resConfidence').innerText = data.confidence_percent + '%';
        document.getElementById('resDistance').innerText = data.relay_distance_m + ' Meter';
        document.getElementById('resExplanation').innerText = data.explanation;

        const badge = document.getElementById('outcomeBadge');
        badge.innerText = data.outcome_status;
        if (data.outcome_status === 'RESOLVED') {
            badge.className = 'badge-resolved';
        } else if (data.outcome_status === 'DISTANCE_AMBIGUOUS') {
            badge.className = 'badge-ambiguous';
        } else {
            badge.className = 'badge-unresolved';
        }

        if (data.candidate_assets && data.candidate_assets.length > 0) {
            document.getElementById('resCandidateAsset').innerText = data.candidate_assets[0].asset_id + ' (' + data.candidate_assets[0].asset_name + ')';
        }
    }
</script>
</body>
</html>
