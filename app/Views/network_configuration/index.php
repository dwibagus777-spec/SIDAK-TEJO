<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Network Configuration Activation | SIDAK TEJO') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .table th { background-color: #f7fafc; font-size: 11px; text-transform: uppercase; color: #4a5568; }
        .table td { vertical-align: middle; font-size: 13px; }
        .hero-banner { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; border-radius: 10px; padding: 20px 24px; }
        .stat-card { border-radius: 8px; padding: 16px; background: #fff; border: 1px solid #e2e8f0; }
        .badge-verified { background-color: #dcfce7; color: #15803d; font-weight: 600; font-size: 11px; padding: 4px 8px; border-radius: 4px; }
        .badge-unverified { background-color: #fef9c3; color: #854d0e; font-weight: 600; font-size: 11px; padding: 4px 8px; border-radius: 4px; }
        .badge-discontinuous { background-color: #fee2e2; color: #b91c1c; font-weight: 600; font-size: 11px; padding: 4px 8px; border-radius: 4px; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4">
    <!-- Header Banner -->
    <div class="hero-banner mb-4 d-flex justify-content-between align-items-center">
        <div>
            <div class="badge bg-success-subtle text-success fw-bold mb-2">CR-06F Physical Topology Activation</div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-network-wired me-2 text-warning"></i>Network Configuration & Conductor Activation</h3>
            <div class="text-white-50 small">Physical Line Reality &bull; Mixed Conductor Support &bull; Two-Tier Node Connectivity &bull; Batch Provenance (Contract v1.1.1)</div>
        </div>
        <div class="text-end">
            <a href="<?= site_url('construction-intelligence') ?>" class="btn btn-outline-light btn-sm me-2"><i class="fa-solid fa-layer-group me-1"></i> CR-06 Architecture</a>
            <a href="<?= site_url('network-configuration/template') ?>" class="btn btn-warning btn-sm fw-bold me-2"><i class="fa-solid fa-file-excel me-1"></i> Download Template</a>
            <button type="button" class="btn btn-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="fa-solid fa-upload me-1"></i> Upload Konfigurasi</button>
        </div>
    </div>

    <!-- Section Coverage Statistics (Gate F2 Honest Empty State Support) -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small fw-bold text-uppercase">Total Master Sections</div>
                <div class="h3 fw-bold mt-1 text-dark"><?= number_format($coverage['total_sections']) ?></div>
                <div class="small text-muted"><i class="fa-solid fa-bezier-curve me-1"></i> Terdaftar di SIDAK TEJO</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small fw-bold text-uppercase">Configured (ACTIVE)</div>
                <div class="h3 fw-bold mt-1 text-success"><?= number_format($coverage['configured_sections']) ?></div>
                <div class="small text-muted"><i class="fa-solid fa-circle-check text-success me-1"></i> Konfigurasi Fisik Terpasang</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small fw-bold text-uppercase">Honest Empty State</div>
                <div class="h3 fw-bold mt-1 text-warning"><?= number_format($coverage['unconfigured_sections']) ?></div>
                <div class="small text-muted"><i class="fa-solid fa-shield-halved text-warning me-1"></i> Gate F2: Tanpa Mock Sintetis</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="text-muted small fw-bold text-uppercase">Topology Coverage</div>
                <div class="h3 fw-bold mt-1 text-primary"><?= number_format($coverage['coverage_pct'], 1) ?>%</div>
                <div class="small text-muted"><i class="fa-solid fa-chart-pie me-1"></i> Kesiapan Dynamic SLD</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left: Feeder & Section Explorer -->
        <div class="col-lg-4">
            <div class="card-custom p-3 mb-4">
                <h6 class="fw-bold mb-3"><i class="fa-solid fa-filter me-2 text-primary"></i>Pilih Feeder / Penyulang</h6>
                <form method="get" action="<?= site_url('network-configuration') ?>">
                    <div class="mb-3">
                        <select name="penyulang_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">-- Pilih Penyulang --</option>
                            <?php foreach ($feeders as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= (isset($selectedFeeder['id']) && $selectedFeeder['id'] == $f['id']) ? 'selected' : '' ?>>
                                    [<?= esc($f['kode_penyulang']) ?>] <?= esc($f['nama_penyulang']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if ($selectedFeeder): ?>
                    <div class="alert alert-info py-2 px-3 small mb-0">
                        <strong>Penyulang Terpilih:</strong> <?= esc($selectedFeeder['nama_penyulang']) ?> (<?= esc($selectedFeeder['kode_penyulang']) ?>)<br>
                        <strong>Total Section:</strong> <?= count($sectionsWithConfig) ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted small text-center py-3">
                        Silakan pilih penyulang untuk melihat rincian segmen konduktor dan aksesoris per section.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Ingestion Batches (Gate F8 Provenance) -->
            <div class="card-custom p-3">
                <h6 class="fw-bold mb-3"><i class="fa-solid fa-clock-rotate-left me-2 text-secondary"></i>Riwayat Ingestion (Gate F8)</h6>
                <div class="list-group list-group-flush small">
                    <?php if (empty($recentBatches)): ?>
                        <div class="text-muted text-center py-2">Belum ada riwayat import konfigurasi.</div>
                    <?php else: ?>
                        <?php foreach ($recentBatches as $b): ?>
                            <div class="list-group-item px-0 py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-truncate" style="max-width: 180px;"><?= esc($b['source_filename'] ?? $b['batch_uuid']) ?></strong>
                                    <span class="badge <?= $b['import_status'] === 'COMMITTED' ? 'bg-success' : 'bg-danger' ?>"><?= esc($b['import_status']) ?></span>
                                </div>
                                <div class="text-muted" style="font-size: 11px;">
                                    <?= esc($b['committed_sections']) ?> Section &bull; <?= esc($b['created_at']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Active Physical Configuration Details -->
        <div class="col-lg-8">
            <div class="card-custom p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-diagram-project me-2 text-primary"></i>Konfigurasi Topologi Section Fisik</h5>
                    <?php if ($selectedFeeder): ?>
                        <span class="badge bg-secondary"><?= esc($selectedFeeder['nama_penyulang']) ?></span>
                    <?php endif; ?>
                </div>

                <?php if (empty($sectionsWithConfig)): ?>
                    <div class="text-center py-5">
                        <i class="fa-solid fa-folder-open text-muted fa-3x mb-3"></i>
                        <p class="text-muted mb-0">Belum ada section yang dipilih atau konfigurasi fisik aktif.</p>
                        <small class="text-muted">Gunakan tombol <strong>Upload Konfigurasi</strong> di kanan atas untuk mengunggah file Excel <code>NETWORK_CONFIGURATION.xlsx</code>.</small>
                    </div>
                <?php else: ?>
                    <div class="accordion" id="accordionSections">
                        <?php foreach ($sectionsWithConfig as $idx => $item): ?>
                            <?php $cfg = $item['config']; ?>
                            <div class="accordion-item mb-2 border rounded">
                                <h2 class="accordion-header" id="heading-<?= $item['section_id'] ?>">
                                    <button class="accordion-button <?= $idx > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $item['section_id'] ?>">
                                        <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                            <div>
                                                <strong><?= esc($item['nama_section']) ?></strong>
                                                <?php if ($item['has_config']): ?>
                                                    <span class="ms-2 badge bg-primary">v<?= esc($cfg['version_number']) ?></span>
                                                    <span class="ms-1 badge <?= $cfg['topology_connectivity_status'] === 'VERIFIED' ? 'badge-verified' : ($cfg['topology_connectivity_status'] === 'DISCONTINUOUS' ? 'badge-discontinuous' : 'badge-unverified') ?>">
                                                        <?= esc($cfg['topology_connectivity_status']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="ms-2 badge bg-warning text-dark">NO_ACTIVE_CONFIGURATION</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted small">
                                                <?php if ($item['has_config']): ?>
                                                    <?= count($cfg['conductors'] ?? []) ?> Segmen Konduktor &bull; <?= count($cfg['accessories'] ?? []) ?> Aksesoris
                                                <?php else: ?>
                                                    Gate F2 Honest Empty
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse-<?= $item['section_id'] ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" data-bs-parent="#accordionSections">
                                    <div class="accordion-body">
                                        <?php if (!$item['has_config']): ?>
                                            <div class="alert alert-warning py-2 mb-0 small">
                                                Section ini belum memiliki konfigurasi fisik aktif. Data akan ditampilkan setelah import resmi.
                                            </div>
                                        <?php else: ?>
                                            <!-- Conductors Breakdown -->
                                            <h6 class="fw-bold mb-2 text-success small text-uppercase"><i class="fa-solid fa-bolt me-1"></i> Segmen Konduktor (Gate F3 & F3A)</h6>
                                            <div class="table-responsive mb-3">
                                                <table class="table table-sm table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th width="40">Seq</th>
                                                            <th>Label Segmen</th>
                                                            <th>Material Konduktor</th>
                                                            <th>Panjang</th>
                                                            <th>Simpul (Start &rarr; End)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($cfg['conductors'] as $c): ?>
                                                            <tr>
                                                                <td class="text-center font-monospace fw-bold"><?= esc($c['sequence_order']) ?></td>
                                                                <td><?= esc($c['segment_label'] ?? '-') ?></td>
                                                                <td><span class="badge bg-secondary"><?= esc($c['material_code']) ?></span> <?= esc($c['nama_material']) ?></td>
                                                                <td class="fw-bold"><?= number_format($c['length_m'] ?? 0, 1) ?> m</td>
                                                                <td class="text-muted small font-monospace">
                                                                    <?= esc($c['start_node_id'] ?? 'PB-START') ?> &rarr; <?= esc($c['end_node_id'] ?? 'PB-END') ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <!-- Accessories Breakdown -->
                                            <h6 class="fw-bold mb-2 text-warning small text-uppercase"><i class="fa-solid fa-shield me-1"></i> Aksesoris Proteksi & Saluran (Gate F4)</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>Tipe</th>
                                                            <th>Material</th>
                                                            <th width="60">Qty</th>
                                                            <th>Lokasi Referensi</th>
                                                            <th>Kondisi Awal</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (empty($cfg['accessories'])): ?>
                                                            <tr><td colspan="5" class="text-center text-muted small">Tidak ada data aksesoris pada konfigurasi ini.</td></tr>
                                                        <?php else: ?>
                                                            <?php foreach ($cfg['accessories'] as $a): ?>
                                                                <tr>
                                                                    <td><span class="badge bg-dark"><?= esc($a['accessory_type']) ?></span></td>
                                                                    <td><?= esc($a['nama_material']) ?></td>
                                                                    <td class="text-center fw-bold"><?= esc($a['quantity']) ?></td>
                                                                    <td><?= esc($a['location_reference'] ?? '-') ?></td>
                                                                    <td><span class="badge bg-info text-dark"><?= esc($a['initial_observed_condition'] ?? 'GOOD') ?></span></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="uploadModalLabel"><i class="fa-solid fa-file-arrow-up me-2 text-primary"></i>Upload NETWORK_CONFIGURATION.xlsx</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <p class="small text-muted">
                        Pastikan file Excel mengikuti spesifikasi <strong>CR-06F Contract v1.1.1</strong> dengan 3 sheet wajib (<code>SECTION_CONFIGURATIONS</code>, <code>CONDUCTOR_SEGMENTS</code>, <code>NETWORK_ACCESSORIES</code>).
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pilih File Excel (.xlsx)</label>
                        <input type="file" name="file_excel" id="file_excel" class="form-control form-control-sm" accept=".xlsx,.xls" required>
                    </div>
                    <div id="uploadResult" class="d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" id="btnUploadSubmit" class="btn btn-primary btn-sm fw-bold">
                        <i class="fa-solid fa-play me-1"></i> Mulai Validasi & Ingestion
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('uploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnUploadSubmit');
    const resDiv = document.getElementById('uploadResult');
    const formData = new FormData(this);

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Memproses...';
    resDiv.className = 'd-none';

    try {
        const response = await fetch('<?= site_url('network-configuration/upload') ?>', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();

        resDiv.classList.remove('d-none');
        if (res.success) {
            resDiv.className = 'alert alert-success mt-3 small';
            resDiv.innerHTML = `<strong><i class="fa-solid fa-circle-check me-1"></i> Berhasil!</strong> ${res.committed_sections} section configuration berhasil diaktifkan.`;
            setTimeout(() => window.location.reload(), 1500);
        } else {
            resDiv.className = 'alert alert-danger mt-3 small';
            let errMsg = `<strong><i class="fa-solid fa-triangle-exclamation me-1"></i> Gagal Ingestion:</strong><br>`;
            if (res.errors && res.errors.length) {
                errMsg += '<ul class="mb-0 mt-1 ps-3">';
                res.errors.forEach(err => errMsg += `<li>${err}</li>`);
                errMsg += '</ul>';
            } else {
                errMsg += res.message || 'Terjadi kesalahan validasi.';
            }
            resDiv.innerHTML = errMsg;
        }
    } catch (err) {
        resDiv.classList.remove('d-none');
        resDiv.className = 'alert alert-danger mt-3 small';
        resDiv.innerHTML = '<strong>Error koneksi server:</strong> ' + err.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-play me-1"></i> Mulai Validasi & Ingestion';
    }
});
</script>
</body>
</html>
