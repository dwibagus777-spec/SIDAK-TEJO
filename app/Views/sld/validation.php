<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>SLD Feeder Validation & Topology Health<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Single Line Diagram (SLD) Validation &amp; Workbench<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$totalFeeders    = count($validationRows);
$readyFeeders    = 0;
$totalAssets     = 0;
$totalEdges      = 0;
$totalConnected  = 0;
$totalIsolated   = 0;

foreach ($validationRows as $r) {
    if (($r['readiness'] ?? '') === 'SLD_READY') {
        $readyFeeders++;
    }
    $totalAssets    += (int)($r['assets_count'] ?? 0);
    $totalEdges     += (int)($r['edges_count'] ?? 0);
    $totalConnected += (int)($r['connected_nodes'] ?? 0);
    $totalIsolated  += (int)($r['isolated_nodes'] ?? 0);
}
$incompleteFeeders = $totalFeeders - $readyFeeders;
?>

<div class="container-xl">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb" style="font-size: 12px;">
            <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>" class="text-decoration-none"><i class="fas fa-home me-1"></i> Dashboard</a></li>
            <li class="breadcrumb-item text-muted">Single Line Diagram</li>
            <li class="breadcrumb-item active" aria-current="page">SLD Validation</li>
        </ol>
    </nav>

    <!-- Header Banner -->
    <div class="card shadow-sm border-0 mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 16px; color: #ffffff;">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-warning text-dark px-2 py-1 font-weight-bold" style="font-size: 11px;">CR-NAV-01</span>
                        <span class="badge bg-primary px-2 py-1" style="font-size: 11px;">SLD-05T COMPLIANT</span>
                        <span class="badge bg-dark border border-secondary text-info px-2 py-1 font-monospace" style="font-size: 11px;">
                            <i class="fas fa-shield-halved me-1"></i>STRICT READ-ONLY (&Delta; = 0)
                        </span>
                    </div>
                    <h3 class="fw-bold mb-1 text-white">SLD Feeder Validation &amp; Topology Workbench</h3>
                    <p class="text-white-50 mb-0 small">
                        Matriks kesiapan topologi operasional, konektivitas bentang transline, dan akses visual unifilar seluruh penyulang PLN UP3 Sidoarjo.
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= site_url('sld/view/15') ?>" class="btn btn-primary btn-sm px-3 py-2 fw-bold shadow-sm" style="border-radius: 8px;">
                        <i class="fas fa-diagram-project me-1"></i> Buka SLD Default (PYL-015)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs h-100" style="border-radius: 12px; background: #ffffff;">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 10px;">Total Penyulang</div>
                    <div class="fs-4 fw-bold text-dark mt-1"><?= number_format($totalFeeders) ?></div>
                    <div class="text-muted" style="font-size: 10px;">Authoritative DB</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs h-100" style="border-radius: 12px; background: #ffffff;">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 10px;">SLD Ready</div>
                    <div class="fs-4 fw-bold text-success mt-1"><?= number_format($readyFeeders) ?></div>
                    <div class="text-success small" style="font-size: 10px;"><i class="fas fa-check-circle me-1"></i>Topologi Lengkap</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs h-100" style="border-radius: 12px; background: #ffffff;">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 10px;">Data Incomplete</div>
                    <div class="fs-4 fw-bold text-warning mt-1"><?= number_format($incompleteFeeders) ?></div>
                    <div class="text-muted" style="font-size: 10px;">TL-01 Quarantine</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs h-100" style="border-radius: 12px; background: #ffffff;">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 10px;">Total Aset JTM</div>
                    <div class="fs-4 fw-bold text-primary mt-1"><?= number_format($totalAssets) ?></div>
                    <div class="text-muted" style="font-size: 10px;">100% Accounted</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs h-100" style="border-radius: 12px; background: #ffffff;">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 10px;">Bentang Transline</div>
                    <div class="fs-4 fw-bold text-info mt-1"><?= number_format($totalEdges) ?></div>
                    <div class="text-muted" style="font-size: 10px;">Verified Spans</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs h-100" style="border-radius: 12px; background: #ffffff;">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 10px;">Aset Terisolasi</div>
                    <div class="fs-4 fw-bold text-secondary mt-1"><?= number_format($totalIsolated) ?></div>
                    <div class="text-muted" style="font-size: 10px;">No Synthetic Edges</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feeder Table Card -->
    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-list-check me-2 text-primary"></i>Daftar Status Topologi Penyulang</h5>
                <small class="text-muted">Status bersumber langsung dari Authoritative SLD Read Model Service tanpa fabrikasi logika sekunder.</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 260px;">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="feeder-search-input" class="form-control border-start-0" placeholder="Cari nama atau kode feeder..." onkeyup="filterFeederTable(this.value)">
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-hover table-striped mb-0 align-middle" id="table-sld-feeders" style="font-size: 13px;">
                <thead class="table-light text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                    <tr>
                        <th class="w-1 text-center">ID</th>
                        <th>Kode</th>
                        <th>Nama Penyulang</th>
                        <th class="text-center">Aset Fisik</th>
                        <th class="text-center">Bentang (Edges)</th>
                        <th class="text-center">Terhubung</th>
                        <th class="text-center">Terisolasi</th>
                        <th class="text-center">Status Topologi</th>
                        <th class="text-center w-1">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($validationRows as $row): ?>
                    <?php
                    $isReady = (($row['readiness'] ?? '') === 'SLD_READY');
                    ?>
                    <tr class="feeder-row" data-search="<?= esc(strtolower(($row['kode_penyulang'] ?? '') . ' ' . ($row['nama_penyulang'] ?? ''))) ?>">
                        <td class="text-center text-muted font-monospace"><?= esc($row['id']) ?></td>
                        <td>
                            <span class="badge bg-light text-dark border font-monospace"><?= esc($row['kode_penyulang'] ?? '-') ?></span>
                        </td>
                        <td>
                            <span class="fw-bold text-dark"><?= esc($row['nama_penyulang']) ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary-subtle text-secondary font-monospace"><?= number_format($row['assets_count']) ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info-subtle text-info font-monospace"><?= number_format($row['edges_count']) ?></span>
                        </td>
                        <td class="text-center text-success fw-semibold">
                            <?= number_format($row['connected_nodes']) ?>
                        </td>
                        <td class="text-center <?= $row['isolated_nodes'] > 0 ? 'text-warning' : 'text-muted' ?>">
                            <?= number_format($row['isolated_nodes']) ?>
                        </td>
                        <td class="text-center">
                            <?php if ($isReady): ?>
                                <span class="badge bg-success" style="font-size: 10px;"><i class="fas fa-check-circle me-1"></i>SLD READY</span>
                            <?php else: ?>
                                <span class="badge bg-secondary text-white-50" style="font-size: 10px;" title="Bentang transline pada DB produksi belum lengkap"><i class="fas fa-circle-pause me-1"></i>DATA NOT READY</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center text-nowrap">
                            <a href="<?= site_url('sld/view/' . $row['id']) ?>" class="btn btn-sm <?= $isReady ? 'btn-primary' : 'btn-outline-secondary' ?> py-1 px-2 fw-semibold" style="font-size: 11px;" title="Buka SLD Penyulang">
                                <i class="fas fa-diagram-project me-1"></i> Buka SLD
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white py-3 px-4 d-flex justify-content-between align-items-center text-muted small">
            <span id="feeder-count-info">Menampilkan <?= number_format($totalFeeders) ?> penyulang</span>
            <span><i class="fas fa-database me-1 text-primary"></i>Authoritative Read Model &bull; Zero DB Writes</span>
        </div>
    </div>
</div>

<script>
function filterFeederTable(query) {
    var q = query.toLowerCase().trim();
    var rows = document.querySelectorAll('#table-sld-feeders tbody tr.feeder-row');
    var visible = 0;
    rows.forEach(function(r) {
        var str = r.getAttribute('data-search') || '';
        if (!q || str.indexOf(q) !== -1) {
            r.style.display = '';
            visible++;
        } else {
            r.style.display = 'none';
        }
    });
    var info = document.getElementById('feeder-count-info');
    if (info) {
        info.textContent = 'Menampilkan ' + visible + ' penyulang' + (q ? ' (difilter dari ' + rows.length + ')' : '');
    }
}
</script>
<?= $this->endSection() ?>
