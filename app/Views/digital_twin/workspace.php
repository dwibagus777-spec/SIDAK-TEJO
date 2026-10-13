<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Digital Twin Command Center') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --dt-bg-dark: #0f172a;
            --dt-card-bg: #1e293b;
            --dt-accent: #38bdf8;
            --dt-accent-glow: rgba(56, 189, 248, 0.25);
            --dt-border: #334155;
            --dt-success: #10b981;
            --dt-warning: #f59e0b;
            --dt-danger: #ef4444;
        }

        body {
            background-color: var(--dt-bg-dark);
            color: #f8fafc;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            padding-bottom: 50px;
        }

        .navbar-custom {
            background-color: #0b1120;
            border-bottom: 1px solid var(--dt-border);
            padding: 12px 24px;
        }

        .card-dt {
            background-color: var(--dt-card-bg);
            border: 1px solid var(--dt-border);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            margin-bottom: 24px;
        }

        .card-dt-header {
            background: rgba(15, 23, 42, 0.6);
            border-bottom: 1px solid var(--dt-border);
            padding: 14px 20px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .kpi-box {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid var(--dt-border);
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }

        .kpi-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--dt-accent);
        }

        .kpi-label {
            font-size: 0.8rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-l4 {
            background-color: #7c3aed;
            color: #fff;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
        }

        .badge-l5 {
            background-color: var(--dt-success);
            color: #fff;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
        }

        .table-custom {
            color: #cbd5e1;
        }

        .table-custom th {
            background-color: rgba(15, 23, 42, 0.8);
            color: #94a3b8;
            border-bottom: 1px solid var(--dt-border);
        }

        .table-custom td {
            border-bottom: 1px solid var(--dt-border);
            vertical-align: middle;
        }

        .hash-code {
            font-family: monospace;
            font-size: 0.78rem;
            color: #38bdf8;
            background: rgba(15, 23, 42, 0.8);
            padding: 2px 6px;
            border-radius: 4px;
        }

        .sld-viewport {
            background: #090d16;
            border: 1px dashed var(--dt-border);
            border-radius: 8px;
            height: 320px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .legend-tag {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-dt-primary {
            background-color: #0284c7;
            color: #ffffff;
            border: none;
            font-weight: 600;
        }
        .btn-dt-primary:hover {
            background-color: #0369a1;
            color: #ffffff;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-custom navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#">
            <i class="fa-solid fa-cube text-info"></i>
            <span class="fw-bold">SIDAK TEJO</span>
            <span class="text-secondary">|</span>
            <span class="fs-6 text-light">Enterprise Digital Twin Command Center (D1)</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-dark border border-info text-info">
                <i class="fa-solid fa-layer-group"></i> Baseline: <?= esc($summary['topology_snapshot']) ?>
            </span>
            <span class="badge badge-l4">
                <i class="fa-solid fa-robot"></i> AI Ceiling: L4 RECOMMENDED
            </span>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 mt-4">

    <!-- Top KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="kpi-box">
                <div class="kpi-value text-info"><?= number_format($summary['active_translines']) ?> TL</div>
                <div class="kpi-label">Active Translines</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="kpi-box">
                <div class="kpi-value text-primary"><?= number_format($summary['active_assets']) ?></div>
                <div class="kpi-label">Active Assets</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="kpi-box">
                <div class="kpi-value text-warning"><?= esc($summary['active_scenarios_count']) ?></div>
                <div class="kpi-label">Active Scenarios</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="kpi-box">
                <div class="kpi-value text-success"><?= esc($summary['projected_reliability']) ?>%</div>
                <div class="kpi-label">Projected Reliability</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="kpi-box">
                <div class="kpi-value text-danger"><?= esc($summary['projected_risk']) ?></div>
                <div class="kpi-label">Projected Risk</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="kpi-box">
                <div class="kpi-value text-light">Rp <?= number_format($summary['projected_cost_idr'] / 1000000, 1) ?>M</div>
                <div class="kpi-label">Projected Investment</div>
            </div>
        </div>
    </div>

    <!-- Main Workspace Grid -->
    <div class="row g-4">

        <!-- Left Panel: Scenario Builder & Controls -->
        <div class="col-lg-4">
            <div class="card-dt">
                <div class="card-dt-header">
                    <span><i class="fa-solid fa-sliders text-info me-2"></i> Scenario Builder</span>
                    <span class="badge bg-secondary">D1.1 ENGINE</span>
                </div>
                <div class="card-body p-4">
                    <form id="scenarioForm">
                        <div class="mb-3">
                            <label class="form-label text-secondary small">PENYULANG (FEEDER)</label>
                            <select class="form-select bg-dark text-light border-secondary" id="feederId">
                                <option value="F-CANDRAMAS" selected>F-CANDRAMAS (Penyulang Candramas)</option>
                                <option value="F-GIRI">F-GIRI (Penyulang Giri)</option>
                                <option value="F-KOTA">F-KOTA (Penyulang Kota)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">SECTION</label>
                            <select class="form-select bg-dark text-light border-secondary" id="sectionId">
                                <option value="SEC-CAND-01" selected>SEC-CAND-01 (Kanal Utama)</option>
                                <option value="SEC-CAND-02">SEC-CAND-02 (Percabangan Utara)</option>
                                <option value="SEC-CAND-03">SEC-CAND-03 (Percabangan Selatan)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">TARGET ASET (ASSET ID)</label>
                            <select class="form-select bg-dark text-light border-secondary" id="assetId">
                                <option value="AST-TRF-094" selected>AST-TRF-094 (Trafo Distribusi 250kVA)</option>
                                <option value="AST-LBS-012">AST-LBS-012 (Load Break Switch Motorized)</option>
                                <option value="AST-REC-005">AST-REC-005 (Recloser Telecontrol)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">JENIS SKENARIO (SCENARIO TYPE)</label>
                            <select class="form-select bg-dark text-light border-secondary" id="scenarioType">
                                <option value="MAINTENANCE_SCENARIO" selected>PREVENTIVE MAINTENANCE SCENARIO</option>
                                <option value="REPLACEMENT_SCENARIO">ASSET REPLACEMENT VS MAINTENANCE</option>
                                <option value="OUTAGE_SCENARIO">PLANNED OUTAGE OPTIMIZATION</option>
                                <option value="CAPEX_SCENARIO">CAPEX CAPITAL ALLOCATION</option>
                                <option value="RISK_MITIGATION_SCENARIO">RISK MITIGATION & WEATHER SURGE</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary small">NAMA SKENARIO</label>
                            <input type="text" class="form-control bg-dark text-light border-secondary" id="scenarioName" value="What-If Optimization Skenario 2026-Q4">
                        </div>
                        <button type="button" class="btn btn-dt-primary w-100 py-2" id="btnRunSimulation">
                            <i class="fa-solid fa-play me-2"></i> RUN WHAT-IF SIMULATION
                        </button>
                    </form>
                </div>
            </div>

            <!-- Hard Safety Guards Box -->
            <div class="card-dt">
                <div class="card-dt-header">
                    <span><i class="fa-solid fa-shield-halved text-success me-2"></i> D1 Hard Safety Guards</span>
                    <span class="badge bg-success">10/10 ACTIVE</span>
                </div>
                <div class="card-body p-3">
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2 text-light"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D1-G01</strong>: Zero production GIS mutation ($\Delta = 0$)</li>
                        <li class="mb-2 text-light"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D1-G02</strong>: Zero production asset mutation ($\Delta = 0$)</li>
                        <li class="mb-2 text-light"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D1-G03</strong>: Isolated simulation state</li>
                        <li class="mb-2 text-light"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D1-G04</strong>: Bound to snapshot <code>TOPOLOGY-20260928</code></li>
                        <li class="mb-2 text-light"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D1-G05 & G06</strong>: Deterministic SHA-256 fingerprints</li>
                        <li class="mb-0 text-light"><i class="fa-solid fa-circle-check text-success me-2"></i> <strong>D1-G07 & G08</strong>: Mandatory Human Approval ($L5/L6$)</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Middle & Right Panel: Comparison & Overlay -->
        <div class="col-lg-8">
            
            <!-- Side-by-Side Comparison Card -->
            <div class="card-dt">
                <div class="card-dt-header">
                    <span><i class="fa-solid fa-scale-balanced text-warning me-2"></i> Side-by-Side Comparison (CURRENT vs SIMULATED SCENARIO)</span>
                    <span class="badge badge-l4" id="recommendationBadge">L4 RECOMMENDED</span>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-custom table-bordered align-middle mb-4">
                            <thead>
                                <tr>
                                    <th>METRIK EVALUASI</th>
                                    <th class="text-center text-danger">CURRENT BASELINE</th>
                                    <th class="text-center text-success">SIMULATED SCENARIO</th>
                                    <th class="text-center text-info">DELTA / IMPACT</th>
                                </tr>
                            </thead>
                            <tbody id="comparisonTableBody">
                                <tr>
                                    <td><strong>AHI (Asset Health Index)</strong></td>
                                    <td class="text-center text-danger fw-bold">62.4</td>
                                    <td class="text-center text-success fw-bold">81.7</td>
                                    <td class="text-center text-info">+19.3 (SIGNIFICANT GAIN)</td>
                                </tr>
                                <tr>
                                    <td><strong>Risk Exposure</strong></td>
                                    <td class="text-center text-danger">HIGH</td>
                                    <td class="text-center text-success">LOW</td>
                                    <td class="text-center text-info">HIGH → LOW</td>
                                </tr>
                                <tr>
                                    <td><strong>Recurring Defects</strong></td>
                                    <td class="text-center">4 Temuan</td>
                                    <td class="text-center text-success">1 Temuan</td>
                                    <td class="text-center text-info">-3 Temuan (-75%)</td>
                                </tr>
                                <tr>
                                    <td><strong>Projected Outage Duration</strong></td>
                                    <td class="text-center text-danger">8.5 Jam</td>
                                    <td class="text-center text-success">3.2 Jam</td>
                                    <td class="text-center text-info">-5.3 Jam (-62%)</td>
                                </tr>
                                <tr>
                                    <td><strong>Estimasi Material (B8.2)</strong></td>
                                    <td class="text-center">-</td>
                                    <td class="text-center text-light">Rp 65.000.000</td>
                                    <td class="text-center text-secondary">Material Requirement</td>
                                </tr>
                                <tr>
                                    <td><strong>Estimasi OPEX / CAPEX</strong></td>
                                    <td class="text-center">-</td>
                                    <td class="text-center text-light">OPEX: Rp 25.000.000</td>
                                    <td class="text-center text-secondary">Operational Cost</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Recommended Option Banner -->
                    <div class="alert alert-dark border border-info bg-dark text-light p-3 d-flex align-items-center justify-content-between rounded-3">
                        <div>
                            <span class="badge bg-info text-dark me-2"><i class="fa-solid fa-lightbulb"></i> AI RECOMENDED OPTION</span>
                            <strong id="optName" class="text-info">OPTION A — PREVENTIVE MAINTENANCE & REFURBISHMENT</strong>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-info" id="btnSubmitRec">
                                <i class="fa-solid fa-paper-plane me-1"></i> Submit L4 Rec
                            </button>
                            <button class="btn btn-sm btn-success" id="btnHumanApprove">
                                <i class="fa-solid fa-signature me-1"></i> Sign-Off L5 Approval
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GIS / SLD Simulation Overlay Card -->
            <div class="card-dt">
                <div class="card-dt-header">
                    <span><i class="fa-solid fa-map-location-dot text-info me-2"></i> SLD & Spatial Simulation Overlay</span>
                    <div class="d-flex gap-2">
                        <span class="legend-tag bg-success text-white"><i class="fa-solid fa-minus"></i> Real Network (Authoritative)</span>
                        <span class="legend-tag bg-info text-dark"><i class="fa-solid fa-ellipsis"></i> Simulation Network Overlay</span>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="sld-viewport">
                        <svg width="100%" height="100%" viewBox="0 0 800 280">
                            <!-- Grid lines -->
                            <defs>
                                <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                                    <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#1e293b" stroke-width="1"/>
                                </pattern>
                            </defs>
                            <rect width="100%" height="100%" fill="url(#grid)" />

                            <!-- Real Network Line (Solid Green) -->
                            <path d="M 50 140 L 250 140 L 450 140 L 650 140 L 750 140" stroke="#10b981" stroke-width="4" fill="none" />

                            <!-- Simulation Overlay Path (Dashed Cyan) -->
                            <path d="M 250 140 Q 350 60 450 140 Q 550 220 650 140" stroke="#38bdf8" stroke-width="3" stroke-dasharray="6,6" fill="none" />

                            <!-- Feeder Node GI -->
                            <circle cx="50" cy="140" r="14" fill="#0284c7" stroke="#ffffff" stroke-width="2"/>
                            <text x="50" y="175" fill="#94a3b8" font-size="11" text-anchor="middle">GI CANDRAMAS</text>

                            <!-- Asset Nodes -->
                            <rect x="235" y="125" width="30" height="30" fill="#1e293b" stroke="#f59e0b" stroke-width="3" rx="4"/>
                            <text x="250" y="175" fill="#cbd5e1" font-size="11" text-anchor="middle">LBS-012</text>

                            <circle cx="450" cy="140" r="12" fill="#ef4444" stroke="#ffffff" stroke-width="2"/>
                            <text x="450" y="175" fill="#f87171" font-size="11" text-anchor="middle">AST-TRF-094 (Target)</text>

                            <rect x="635" y="125" width="30" height="30" fill="#1e293b" stroke="#10b981" stroke-width="3" rx="4"/>
                            <text x="650" y="175" fill="#cbd5e1" font-size="11" text-anchor="middle">REC-005</text>

                            <!-- Overlay Indicators -->
                            <rect x="330" y="70" width="100" height="24" fill="#0f172a" stroke="#38bdf8" stroke-width="1" rx="4"/>
                            <text x="380" y="86" fill="#38bdf8" font-size="10" text-anchor="middle">BYPASS SIMULATION</text>
                        </svg>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- Bottom Row: Historical Scenario & Forensic Register -->
    <div class="row mt-2">
        <div class="col-12">
            <div class="card-dt">
                <div class="card-dt-header">
                    <span><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Digital Twin Scenario & Forensic Lineage Register</span>
                    <span class="badge bg-secondary">D1.6 PROVENANCE BUNDLE</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>SCENARIO UUID</th>
                                    <th>SKENARIO & TYPE</th>
                                    <th>TOPOLOGY SNAPSHOT</th>
                                    <th>INPUT FINGERPRINT</th>
                                    <th>OUTPUT FINGERPRINT</th>
                                    <th>FORENSIC BUNDLE HASH</th>
                                    <th>STATUS</th>
                                    <th>ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody id="scenarioHistoryBody">
                                <?php if (!empty($scenarios)): ?>
                                    <?php foreach ($scenarios as $scn): ?>
                                        <tr>
                                            <td class="fw-bold text-info"><?= esc($scn['scenario_uuid']) ?></td>
                                            <td>
                                                <div><strong><?= esc($scn['scenario_name']) ?></strong></div>
                                                <span class="badge bg-dark border border-secondary text-secondary small"><?= esc($scn['scenario_type']) ?></span>
                                            </td>
                                            <td><code><?= esc($scn['base_topology_snapshot']) ?></code></td>
                                            <td><span class="hash-code"><?= esc(substr($scn['input_fingerprint'], 0, 16)) ?>...</span></td>
                                            <td><span class="hash-code"><?= esc(substr($scn['output_fingerprint'], 0, 16)) ?>...</span></td>
                                            <td><span class="hash-code"><?= esc(substr($scn['forensic_bundle_hash'] ?? '', 0, 16)) ?>...</span></td>
                                            <td>
                                                <?php if ($scn['status'] === 'HUMAN_APPROVED'): ?>
                                                    <span class="badge badge-l5">HUMAN APPROVED</span>
                                                <?php else: ?>
                                                    <span class="badge badge-l4"><?= esc($scn['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info" onclick="viewDetails('<?= esc($scn['scenario_uuid']) ?>')">
                                                    <i class="fa-solid fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-secondary">Belum ada history skenario simulasi. Silakan jalankan simulasi baru.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentScenarioUuid = null;

    document.getElementById('btnRunSimulation').addEventListener('click', async function() {
        const payload = {
            feeder_id: document.getElementById('feederId').value,
            section_id: document.getElementById('sectionId').value,
            asset_id: document.getElementById('assetId').value,
            scenario_type: document.getElementById('scenarioType').value,
            scenario_name: document.getElementById('scenarioName').value,
            parameters: { timestamp: new Date().toISOString() }
        };

        try {
            const res = await fetch('/api/digital-twin/run-simulation', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (json.status === 'success') {
                const data = json.data;
                currentScenarioUuid = data.scenario_uuid;
                updateComparisonUI(data);
                alert('Simulation Completed! Scenario UUID: ' + data.scenario_uuid);
                location.reload();
            }
        } catch(e) {
            console.error(e);
            alert('Simulation failed to execute.');
        }
    });

    document.getElementById('btnSubmitRec').addEventListener('click', async function() {
        if (!currentScenarioUuid) {
            alert('Silakan jalankan simulasi terlebih dahulu.');
            return;
        }
        const res = await fetch('/api/digital-twin/submit-recommendation', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({scenario_uuid: currentScenarioUuid})
        });
        const json = await res.json();
        if (json.status === 'success') {
            alert('Status updated to L4 RECOMMENDED!');
            location.reload();
        }
    });

    document.getElementById('btnHumanApprove').addEventListener('click', async function() {
        if (!currentScenarioUuid) {
            alert('Silakan jalankan simulasi terlebih dahulu.');
            return;
        }
        const res = await fetch('/api/digital-twin/human-approve', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({scenario_uuid: currentScenarioUuid, approved_by: 'EXECUTIVE_SUPERVISOR'})
        });
        const json = await res.json();
        if (json.status === 'success') {
            alert('Status updated to L5 HUMAN APPROVED!');
            location.reload();
        }
    });

    function updateComparisonUI(data) {
        const sim = data.simulation_results.simulated_scenario;
        const cur = data.simulation_results.baseline_current;
        document.getElementById('optName').innerText = data.simulation_results.recommended_option;
    }

    function viewDetails(uuid) {
        currentScenarioUuid = uuid;
        fetch('/api/digital-twin/compare/' + uuid)
            .then(res => res.json())
            .then(json => {
                if (json.status === 'success') {
                    alert('Scenario Details Loaded for: ' + uuid + '\nOutput Fingerprint: ' + json.data.output_fingerprint);
                }
            });
    }
</script>
</body>
</html>
