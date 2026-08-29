<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Dynamic SLD | SIDAK TEJO') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .hero-banner { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; border-radius: 10px; padding: 20px 24px; }
        .stat-card { border-radius: 8px; padding: 16px; background: #fff; border: 1px solid #e2e8f0; }
        .sld-container { background: #1e293b; border-radius: 10px; padding: 30px 20px; min-height: 480px; overflow-x: auto; position: relative; }
        .sld-node { cursor: pointer; transition: all 0.2s ease; }
        .sld-node:hover { transform: scale(1.05); }
        .sld-edge { transition: stroke-width 0.2s ease; cursor: pointer; }
        .sld-edge:hover { stroke-width: 6; stroke: #38bdf8 !important; }
        .badge-good { background-color: #10b981; color: #fff; font-weight: 600; }
        .badge-warning { background-color: #f59e0b; color: #fff; font-weight: 600; }
        .badge-poor { background-color: #f97316; color: #fff; font-weight: 600; }
        .badge-critical { background-color: #ef4444; color: #fff; font-weight: 600; }
        .badge-unresolved { background-color: #64748b; color: #fff; font-weight: 600; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4">
    <!-- Header Banner -->
    <div class="hero-banner mb-4 d-flex justify-content-between align-items-center">
        <div>
            <div class="badge bg-info-subtle text-info fw-bold mb-2">CR-06H Dynamic Single Line Diagram</div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-diagram-project me-2 text-warning"></i>Dynamic Single Line Diagram (SLD) Engine</h3>
            <div class="text-white-50 small">Physical Topology Truth (CR-06F) &bull; Visual Health Overlay (CR-06G) &bull; Read-Only Renderer (Contract v1.0)</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="text-end me-3">
                <span class="text-white-50 small d-block">Pilih Feeder / Penyulang:</span>
                <select class="form-select form-select-sm fw-bold bg-dark text-white border-secondary" style="min-width: 220px;" onchange="location.href='<?= site_url('sld/view') ?>/' + this.value;">
                    <?php foreach ($feeders as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= $selectedFeederId == $f['id'] ? 'selected' : '' ?>>
                            [<?= esc($f['kode_penyulang'] ?? 'PYL') ?>] <?= esc($f['nama_penyulang']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="<?= site_url('network-configuration') ?>" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-network-wired me-1"></i> CR-06F Config</a>
            <a href="<?= site_url('construction-intelligence') ?>" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-layer-group me-1"></i> CR-06 Architecture</a>
        </div>
    </div>

    <?php if (!empty($sldData['success'])): ?>
    <!-- Feeder KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small fw-bold text-uppercase">Feeder Info</div>
                <div class="h5 fw-bold mt-1 text-dark">[<?= esc($sldData['kode_penyulang']) ?>] <?= esc($sldData['nama_penyulang']) ?></div>
                <div class="small text-muted"><i class="fa-solid fa-building me-1"></i> <?= esc($sldData['nama_ulp']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small fw-bold text-uppercase">Panjang Konduktor Aktif</div>
                <div class="h4 fw-bold mt-1 text-primary"><?= number_format($sldData['topology_summary']['total_conductor_length_km'], 2) ?> km</div>
                <div class="small text-muted"><?= $sldData['topology_summary']['configured_sections'] ?> / <?= $sldData['topology_summary']['total_sections'] ?> Sections Configured</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small fw-bold text-uppercase">Aksesoris Proteksi</div>
                <div class="h4 fw-bold mt-1 text-success"><?= $sldData['topology_summary']['total_accessories'] ?> Unit</div>
                <div class="small text-muted">GSW, LA, CLD, Animal Guard</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small fw-bold text-uppercase">Temuan Aktif Jaringan</div>
                <div class="h4 fw-bold mt-1 <?= $sldData['intelligence_overlay']['active_findings_count'] > 0 ? 'text-danger' : 'text-success' ?>">
                    <?= $sldData['intelligence_overlay']['active_findings_count'] ?> Temuan
                </div>
                <div class="small text-muted">Defect Overlay Layer (CR-06G)</div>
            </div>
        </div>
    </div>

    <!-- Interactive SLD Canvas -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div class="fw-bold"><i class="fa-solid fa-project-diagram me-2 text-primary"></i>Diagram Garis Tunggal (SLD) — Topologi Fisik & Defect Overlay</div>
            <div class="small text-muted">
                <span class="badge badge-good me-1">Good (&ge;85)</span>
                <span class="badge badge-warning me-1">Warning (70-84)</span>
                <span class="badge badge-poor me-1">Attention (50-69)</span>
                <span class="badge badge-critical me-1">Critical (&lt;50)</span>
                <span class="badge badge-unresolved">Unresolved / No Data</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="sld-container d-flex flex-column align-items-center justify-content-center">
                <!-- Dynamic SVG Renderer -->
                <svg width="100%" height="320" viewBox="0 0 1000 320" xmlns="http://www.w3.org/2000/svg">
                    <!-- Definitions -->
                    <defs>
                        <linearGradient id="trunkGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#38bdf8" />
                            <stop offset="100%" stop-color="#818cf8" />
                        </linearGradient>
                        <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
                            <feGaussianBlur stdDeviation="3" result="glow" />
                            <feComposite in="SourceGraphic" in2="glow" operator="over" />
                        </filter>
                    </defs>

                    <?php
                        $graphNodes = $sldData['graph']['nodes'] ?? [];
                        $graphEdges = $sldData['graph']['edges'] ?? [];
                        $nodeCount  = count($graphNodes);
                        $stepX      = $nodeCount > 1 ? (800 / ($nodeCount - 1)) : 400;
                        $nodeCoords = [];

                        // Assign coordinates
                        foreach ($graphNodes as $idx => $n) {
                            $x = 100 + ($idx * $stepX);
                            $y = 160;
                            $nodeCoords[$n['node_id']] = ['x' => $x, 'y' => $y, 'node' => $n];
                        }
                    ?>

                    <!-- Render Edges (Conductors) -->
                    <?php foreach ($graphEdges as $edge): 
                        $from = $nodeCoords[$edge['from_node']] ?? ['x' => 100, 'y' => 160];
                        $to   = $nodeCoords[$edge['to_node']] ?? ['x' => 900, 'y' => 160];
                        $edgeColor = ($edge['health_category'] === 'GOOD') ? '#10b981' : (($edge['health_category'] === 'WARNING') ? '#f59e0b' : (($edge['health_category'] === 'CRITICAL') ? '#ef4444' : '#38bdf8'));
                    ?>
                        <line x1="<?= $from['x'] ?>" y1="<?= $from['y'] ?>" x2="<?= $to['x'] ?>" y2="<?= $to['y'] ?>" 
                              stroke="<?= $edgeColor ?>" stroke-width="4" class="sld-edge"
                              onclick="openDrilldown(<?= (int)$edge['section_id'] ?>)" />

                        <!-- Segment Label & Length -->
                        <text x="<?= ($from['x'] + $to['x']) / 2 ?>" y="<?= (($from['y'] + $to['y']) / 2) - 14 ?>" 
                              fill="#94a3b8" font-size="11" font-weight="bold" text-anchor="middle">
                            <?= esc($edge['conductor_material']) ?> (<?= esc($edge['length_m']) ?>m)
                        </text>

                        <!-- Defect Badge if findings exist -->
                        <?php if (!empty($edge['active_defects']) && $edge['active_defects'] > 0): ?>
                            <circle cx="<?= ($from['x'] + $to['x']) / 2 ?>" cy="<?= ($from['y'] + $to['y']) / 2 ?>" r="10" fill="#ef4444" filter="url(#glow)" />
                            <text x="<?= ($from['x'] + $to['x']) / 2 ?>" y="<?= (($from['y'] + $to['y']) / 2) + 4 ?>" fill="#fff" font-size="10" font-weight="bold" text-anchor="middle">
                                <?= $edge['active_defects'] ?>
                            </text>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <!-- Render Nodes -->
                    <?php foreach ($nodeCoords as $nId => $coord): 
                        $node = $coord['node'];
                        $isGi = $node['node_type'] === 'SUBSTATION';
                        $isSw = in_array($node['node_type'], ['SWITCH', 'RECLOSER']);
                    ?>
                        <g class="sld-node" onclick="alert('Node: <?= esc($node['label']) ?>\nTipe: <?= esc($node['node_type']) ?>')">
                            <?php if ($isGi): ?>
                                <rect x="<?= $coord['x'] - 24 ?>" y="<?= $coord['y'] - 24 ?>" width="48" height="48" rx="8" fill="#0284c7" stroke="#38bdf8" stroke-width="2" filter="url(#glow)" />
                                <text x="<?= $coord['x'] ?>" y="<?= $coord['y'] + 5 ?>" fill="#fff" font-size="12" font-weight="bold" text-anchor="middle">GI</text>
                            <?php elseif ($isSw): ?>
                                <polygon points="<?= $coord['x'] ?>,<?= $coord['y'] - 18 ?> <?= $coord['x'] + 18 ?>,<?= $coord['y'] + 18 ?> <?= $coord['x'] - 18 ?>,<?= $coord['y'] + 18 ?>" fill="#d97706" stroke="#fde047" stroke-width="2" />
                                <text x="<?= $coord['x'] ?>" y="<?= $coord['y'] + 10 ?>" fill="#fff" font-size="10" font-weight="bold" text-anchor="middle">SW</text>
                            <?php else: ?>
                                <circle cx="<?= $coord['x'] ?>" cy="<?= $coord['y'] ?>" r="14" fill="#334155" stroke="#94a3b8" stroke-width="2" />
                                <circle cx="<?= $coord['x'] ?>" cy="<?= $coord['y'] ?>" r="4" fill="#38bdf8" />
                            <?php endif; ?>

                            <!-- Node Title -->
                            <text x="<?= $coord['x'] ?>" y="<?= $coord['y'] + 38 ?>" fill="#f8fafc" font-size="11" font-weight="600" text-anchor="middle">
                                <?= esc($node['label']) ?>
                            </text>
                        </g>
                    <?php endforeach; ?>
                </svg>
            </div>
        </div>
        <div class="card-footer bg-white text-muted small py-2 d-flex justify-content-between">
            <div><i class="fa-solid fa-info-circle me-1 text-primary"></i> Klik pada segmen garis untuk membuka <strong>Interactive Section Drill-Down</strong>.</div>
            <div>Rendered at: <?= esc($sldData['rendered_at']) ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Section Drilldown Modal -->
<div class="modal fade" id="drilldownModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="drilldownTitle"><i class="fa-solid fa-network-wired me-2 text-warning"></i>Section Drill-Down</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="drilldownContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 text-muted">Memuat data konfigurasi fisik & intelijen aset...</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openDrilldown(sectionId) {
    const modal = new bootstrap.Modal(document.getElementById('drilldownModal'));
    modal.show();

    fetch('<?= site_url('sld/section-detail') ?>/' + sectionId)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('drilldownContent').innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }

            document.getElementById('drilldownTitle').innerHTML = `<i class="fa-solid fa-network-wired me-2 text-warning"></i>${data.section.nama_section} (${data.section.nama_penyulang})`;
            
            let html = `
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border">
                            <div class="text-muted small fw-bold text-uppercase">Konfigurasi Fisik (CR-06F)</div>
                            <div class="h6 fw-bold mt-1 text-dark">Status: <span class="badge bg-success">${data.physical_configuration ? data.physical_configuration.status : 'UNCONFIGURED'}</span></div>
                            <div class="small text-muted">Total Panjang: <strong>${data.physical_configuration ? data.physical_configuration.total_length_m : 0} m</strong> &bull; Aksesoris: <strong>${data.physical_configuration ? data.physical_configuration.total_accessories : 0}</strong></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded border">
                            <div class="text-muted small fw-bold text-uppercase">Kesehatan Aset (CR-06G)</div>
                            <div class="h6 fw-bold mt-1 text-dark">Rata-rata AHS: <span class="badge ${data.intelligence_summary.average_health_score >= 85 ? 'bg-success' : 'bg-warning'}">${data.intelligence_summary.average_health_score !== null ? data.intelligence_summary.average_health_score : 'UNRESOLVED'}</span></div>
                            <div class="small text-muted">Structural Risk (ADI): <strong>${data.intelligence_summary.section_structural_risk}</strong> &bull; Total Aset: <strong>${data.intelligence_summary.total_assets}</strong></div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mb-2"><i class="fa-solid fa-bolt me-2 text-warning"></i>Segmen Konduktor & Aksesoris</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Seq</th>
                                <th>Material Konduktor</th>
                                <th>Panjang</th>
                                <th>Start Node</th>
                                <th>End Node</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.physical_configuration && data.physical_configuration.conductors.length > 0 ? 
                                data.physical_configuration.conductors.map(c => `
                                    <tr>
                                        <td><strong>#${c.sequence_order}</strong></td>
                                        <td>${c.material_code || 'AAAC'}</td>
                                        <td>${c.length_m} m</td>
                                        <td><code>${c.start_node_id || '-'}</code></td>
                                        <td><code>${c.end_node_id || '-'}</code></td>
                                    </tr>
                                `).join('') : '<tr><td colspan="5" class="text-center text-muted py-2">Belum ada segmen konduktor aktif.</td></tr>'
                            }
                        </tbody>
                    </table>
                </div>

                <h6 class="fw-bold mb-2"><i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>Temuan Operasional Aktif (${data.active_findings.length})</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No Temuan</th>
                                <th>Komponen</th>
                                <th>Prioritas</th>
                                <th>Detail Temuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.active_findings.length > 0 ? 
                                data.active_findings.map(f => `
                                    <tr>
                                        <td><strong>${f.nomor_temuan}</strong></td>
                                        <td><span class="badge bg-secondary">${f.component}</span></td>
                                        <td><span class="badge ${f.prioritas === 'KRITIS' || f.prioritas === 'EMERGENCY' ? 'bg-danger' : 'bg-warning'}">${f.prioritas}</span></td>
                                        <td>${f.detail_temuan || f.jenis_temuan}</td>
                                    </tr>
                                `).join('') : '<tr><td colspan="4" class="text-center text-success py-2"><i class="fa-solid fa-circle-check me-1"></i> Tidak ada temuan open di section ini.</td></tr>'
                            }
                        </tbody>
                    </table>
                </div>
            `;

            document.getElementById('drilldownContent').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('drilldownContent').innerHTML = `<div class="alert alert-danger">Gagal memuat detail drill-down: ${err.message}</div>`;
        });
}
</script>
</body>
</html>
