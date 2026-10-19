<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Analitik Gangguan Penyulang - SIDAK TEJO<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Analitik Gangguan Penyulang<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid px-3 py-3">

<?php
// ── PHP helpers ──────────────────────────────────────────────────────────────
$monthLabels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$pctChange   = $pctChange ?? null;
$baseUrl     = site_url('gangguan');
$ulpParam    = $ulpIdFilter ? '&ulp_id=' . $ulpIdFilter : '';
?>

<!-- ═══════════════════════════════════════════════════════ Flash messages ══ -->
<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm d-flex align-items-center mb-3" role="alert">
    <i class="fas fa-check-circle me-2 fs-5"></i>
    <div><?= session()->getFlashdata('success') ?></div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm d-flex align-items-center mb-3" role="alert">
    <i class="fas fa-triangle-exclamation me-2 fs-5"></i>
    <div><?= session()->getFlashdata('error') ?></div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('errors')): ?>
<div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-3" role="alert">
    <div class="fw-bold mb-1"><i class="fas fa-triangle-exclamation me-1"></i> Terjadi kesalahan validasi input:</div>
    <ul class="mb-0 ps-3">
        <?php foreach (session()->getFlashdata('errors') as $err): ?>
            <li><?= esc($err) ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════ Year & Feeder Selector Bar ══ -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body py-2 px-3">
        <form method="GET" action="<?= $baseUrl ?>" id="analyticsForm" class="row g-2 align-items-center">
            <div class="col-auto">
                <span class="fw-semibold text-secondary small"><i class="fas fa-chart-bar me-1 text-primary"></i>Analitik YoY:</span>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-0 me-1 text-muted">Tahun A</label>
                <select name="year_a" id="selYearA" class="form-select form-select-sm rounded-pill d-inline-block" style="width: auto;" onchange="document.getElementById('analyticsForm').submit()">
                    <?php foreach ($availableYears as $yr): ?>
                        <option value="<?= $yr ?>" <?= ($yr === $yearA) ? 'selected' : '' ?>><?= $yr ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto text-muted"><i class="fas fa-arrows-left-right"></i></div>
            <div class="col-auto">
                <label class="form-label small mb-0 me-1 text-muted">Tahun B</label>
                <select name="year_b" id="selYearB" class="form-select form-select-sm rounded-pill d-inline-block" style="width: auto;" onchange="document.getElementById('analyticsForm').submit()">
                    <?php foreach ($availableYears as $yr): ?>
                        <option value="<?= $yr ?>" <?= ($yr === $yearB) ? 'selected' : '' ?>><?= $yr ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (in_array(strtolower((string)session()->get('user_role')), ['administrator', 'admin', 'admin_pusat', 'supervisor_up3'])): ?>
            <div class="col-auto">
                <label class="form-label small mb-0 me-1 text-muted">ULP</label>
                <select name="ulp_id" id="selUlpAnalytics" class="form-select form-select-sm rounded-pill d-inline-block" style="width: auto;" onchange="document.getElementById('analyticsForm').submit()">
                    <option value="">Semua ULP</option>
                    <?php foreach ($ulps as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ((int)$ulpIdFilter === (int)$u['id']) ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <?php if ($ulpIdFilter): ?><input type="hidden" name="ulp_id" value="<?= $ulpIdFilter ?>"><?php endif; ?>
            <?php endif; ?>
            <div class="col-auto">
                <label class="form-label small mb-0 me-1 text-muted">Penyulang</label>
                <select name="penyulang_id" id="selPenyulangAnalytics" class="form-select form-select-sm rounded-pill d-inline-block" style="width: auto; max-width: 190px;" onchange="document.getElementById('analyticsForm').submit()">
                    <option value="">Semua Penyulang</option>
                    <?php foreach ($penyulangs as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ((int)$penyulangId === (int)$p['id']) ? 'selected' : '' ?>><?= esc($p['nama_penyulang']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($penyulangId || $ulpIdFilter): ?>
            <div class="col-auto">
                <a href="<?= $baseUrl ?>?year_a=<?= $yearA ?>&year_b=<?= $yearB ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Reset filter penyulang/ULP">
                    <i class="fas fa-times me-1"></i>Reset Filter
                </a>
            </div>
            <?php endif; ?>
            <div class="col-auto ms-auto d-flex gap-2">
                <?php if ($canManage): ?>
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalImportExcel">
                    <i class="fas fa-file-excel me-1"></i> Impor XLSX
                </button>
                <button type="button" class="btn btn-sm btn-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalAddGangguan">
                    <i class="fas fa-plus me-1"></i> Tambah Gangguan
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($penyulangId): ?>
<?php
    $activePenyulangName = '';
    foreach ($penyulangs as $p) {
        if ((int)$p['id'] === (int)$penyulangId) {
            $activePenyulangName = $p['nama_penyulang'];
            break;
        }
    }
?>
<div class="alert alert-info border-0 shadow-sm rounded-4 d-flex justify-content-between align-items-center py-2 px-3 mb-4">
    <div class="small">
        <i class="fas fa-filter text-primary me-2"></i>Menampilkan analitik khusus Penyulang: <strong><?= esc($activePenyulangName ?: ('ID #' . $penyulangId)) ?></strong>
    </div>
    <a href="<?= $baseUrl ?>?year_a=<?= $yearA ?>&year_b=<?= $yearB ?><?= $ulpParam ?>" class="btn btn-xs btn-outline-primary rounded-pill px-3 py-1 small">
        Tampilkan Semua Penyulang
    </a>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════ YoY Summary ══ -->
<?php
$selisihLabel = $selisih > 0 ? '+' . number_format($selisih) : number_format($selisih);
$selisihColor = $selisih > 0 ? 'danger' : ($selisih < 0 ? 'success' : 'secondary');
$pctLabel     = $pctChange !== null ? ($pctChange > 0 ? '+' . $pctChange . '%' : $pctChange . '%') : 'N/A';
$pctColor     = ($pctChange !== null && $pctChange > 0) ? 'danger' : (($pctChange !== null && $pctChange < 0) ? 'success' : 'secondary');
$compDesc     = 'Perbandingan periode setara: ' . ($periodInfo['comparable_period'] ?? '') . ' (bulan yang tersedia di kedua tahun)';
?>
<div class="row g-3 mb-4">
    <div class="col-lg-3 col-sm-6 col-12">
        <div class="card border-0 shadow-sm rounded-4 p-3 h-100 border-start border-4 border-primary">
            <div class="text-muted small fw-semibold mb-1">Total Gangguan <?= $yearA ?></div>
            <h3 class="fw-bold mb-0 text-dark"><?= number_format($totalA) ?> <span class="fs-6 fw-normal text-muted">kali</span></h3>
            <div class="text-muted" style="font-size:11px;"><?= esc($periodInfo['yearA_period'] ?? '') ?></div>
        </div>
    </div>
    <div class="col-lg-3 col-sm-6 col-12">
        <div class="card border-0 shadow-sm rounded-4 p-3 h-100 border-start border-4 border-secondary">
            <div class="text-muted small fw-semibold mb-1">Total Gangguan <?= $yearB ?></div>
            <h3 class="fw-bold mb-0 text-dark"><?= number_format($totalB) ?> <span class="fs-6 fw-normal text-muted">kali</span></h3>
            <div class="text-muted" style="font-size:11px;"><?= esc($periodInfo['yearB_period'] ?? '') ?></div>
        </div>
    </div>
    <div class="col-lg-3 col-sm-6 col-12">
        <div class="card border-0 shadow-sm rounded-4 p-3 h-100 border-start border-4 border-<?= $selisihColor ?>">
            <div class="text-muted small fw-semibold mb-1">Selisih (A − B)</div>
            <h3 class="fw-bold mb-0 text-<?= $selisihColor ?>"><?= $selisihLabel ?></h3>
            <div class="text-muted" style="font-size:11px;"><?= $compDesc ?></div>
        </div>
    </div>
    <div class="col-lg-3 col-sm-6 col-12">
        <div class="card border-0 shadow-sm rounded-4 p-3 h-100 border-start border-4 border-<?= $pctColor ?>">
            <div class="text-muted small fw-semibold mb-1">Perubahan YoY</div>
            <h3 class="fw-bold mb-0 text-<?= $pctColor ?>"><?= $pctLabel ?></h3>
            <div class="text-muted" style="font-size:11px;">
                <?= $pctChange !== null
                    ? ($pctChange > 0 ? 'Meningkat dibanding periode yang sama' : ($pctChange < 0 ? 'Menurun dibanding periode yang sama' : 'Tidak ada perubahan'))
                    : 'Data tahun B tidak tersedia' ?>
            </div>
        </div>
    </div>
</div>

<?php
$sumA = array_sum($monthlyA ?? []);
$sumB = array_sum($monthlyB ?? []);
$hasChartData = ($sumA > 0 || $sumB > 0);
?>
<!-- ═══════════════════════════════════════════════ Monthly Chart ══ -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-transparent border-0 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fas fa-chart-bar text-primary me-2"></i>Distribusi Gangguan per Bulan
            <?php if ($penyulangId && !empty($activePenyulangName)): ?>
                <span class="badge bg-primary-subtle text-primary border ms-2 font-monospace"><?= esc($activePenyulangName) ?></span>
            <?php endif; ?>
        </h6>
        <div class="d-flex gap-2 align-items-center" style="font-size:12px;">
            <span class="d-inline-block rounded-2" style="width:14px;height:14px;background:#0d6efd;"></span> <?= $yearA ?>
            <span class="d-inline-block rounded-2 ms-2" style="width:14px;height:14px;background:#adb5bd;"></span> <?= $yearB ?>
        </div>
    </div>
    <div class="card-body pt-0" id="chartGangguanContainer" style="min-height: 260px;">
        <?php if ($hasChartData): ?>
            <div style="height: 280px; position: relative;">
                <canvas id="chartGangguan"></canvas>
            </div>
        <?php else: ?>
            <div class="d-flex flex-column align-items-center justify-content-center py-5 text-center text-muted">
                <i class="fas fa-chart-line fs-1 text-secondary opacity-50 mb-3"></i>
                <h6 class="fw-semibold text-secondary mb-1">Belum Ada Data Kejadian Gangguan</h6>
                <p class="small text-muted mb-0">
                    Tidak ditemukan catatan kejadian gangguan untuk tahun <?= esc($yearA) ?> maupun <?= esc($yearB) ?>
                    <?= ($penyulangId && !empty($activePenyulangName)) ? ' pada penyulang <strong>' . esc($activePenyulangName) . '</strong>' : '' ?>.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ════════════════════════════════════════════ Top 10 Penyulang ══ -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-transparent border-0 py-3">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fas fa-ranking-star text-warning me-2"></i>10 Penyulang Gangguan Terbanyak — Tahun <?= $yearA ?>
        </h6>
        <div class="text-muted" style="font-size:11px;">Diurutkan berdasarkan jumlah gangguan <?= $yearA ?>. Kolom <?= $yearB ?> menampilkan nilai periode yang tersedia.</div>
    </div>
    <div class="table-responsive">
        <?php if (empty($top10Merged)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-inbox fs-2 mb-2 d-block text-secondary opacity-50"></i>
            Tidak ada data penyulang untuk tahun <?= $yearA ?>.
        </div>
        <?php else: ?>
        <table class="table table-hover align-middle mb-0" style="font-size:13px;">
            <thead class="table-light text-muted fw-semibold">
                <tr>
                    <th class="ps-3" style="width:40px;">#</th>
                    <th>Penyulang</th>
                    <th>ULP</th>
                    <th class="text-center" style="width:110px;">Gangguan <?= $yearA ?></th>
                    <th class="text-center" style="width:110px;">Gangguan <?= $yearB ?></th>
                    <th class="text-center" style="width:100px;">Selisih</th>
                    <th class="text-center" style="width:140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $cntAValues = array_column($top10Merged, 'cnt_a');
                $maxCntA    = !empty($cntAValues) ? max($cntAValues) : 1;
                if ($maxCntA < 1) $maxCntA = 1;
                foreach ($top10Merged as $i => $row):
                $diff      = $row['selisih'];
                $diffLabel = $diff > 0 ? '+' . $diff : $diff;
                $diffCls   = $diff > 0 ? 'text-danger' : ($diff < 0 ? 'text-success' : 'text-muted');
                $barW      = round($row['cnt_a'] / $maxCntA * 100);
                ?>
                <tr class="<?= ((int)$penyulangId === (int)$row['penyulang_id'] && !empty($row['penyulang_id'])) ? 'table-primary' : '' ?>">
                    <td class="ps-3 text-muted fw-semibold"><?= $i + 1 ?></td>
                    <td>
                        <div class="fw-semibold text-dark"><?= esc($row['nama_penyulang'] ?: '—') ?></div>
                        <div style="height:4px;background:#e9ecef;border-radius:3px;margin-top:3px;max-width:120px;">
                            <div style="height:4px;width:<?= $barW ?>%;background:#0d6efd;border-radius:3px;"></div>
                        </div>
                    </td>
                    <td class="text-muted"><?= esc($row['nama_ulp'] ?: '—') ?></td>
                    <td class="text-center fw-bold text-primary"><?= $row['cnt_a'] ?: '0' ?></td>
                    <td class="text-center text-muted"><?= $row['cnt_b'] ?: '0' ?></td>
                    <td class="text-center fw-semibold <?= $diffCls ?>"><?= $diffLabel ?></td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <?php if (!empty($row['penyulang_id'])): ?>
                            <a href="<?= $baseUrl ?>?year_a=<?= $yearA ?>&year_b=<?= $yearB ?><?= $ulpParam ?>&penyulang_id=<?= $row['penyulang_id'] ?>"
                               class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-0 me-1"
                               title="Filter grafik dan ringkasan ke penyulang ini" style="font-size:11px;">
                                <i class="fas fa-chart-line me-1"></i>Fokus
                            </a>
                            <a href="<?= site_url('temuan?penyulang_id=' . $row['penyulang_id']) ?>"
                               class="btn btn-xs btn-outline-primary rounded-pill px-2 py-0"
                               title="Daftar Temuan penyulang ini" style="font-size:11px;">
                                <i class="fas fa-list-check me-1"></i>Temuan
                            </a>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ══════════════════════════════════════════ Histori Gangguan (tabel) ══ -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-transparent border-0 py-2 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fas fa-table-list text-primary me-2"></i>Histori Kejadian Gangguan
            <span class="badge bg-light text-muted border font-monospace ms-2"><?= number_format($totalCount) ?> data</span>
        </h6>
        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" type="button"
                data-bs-toggle="collapse" data-bs-target="#collapseHistori"
                aria-expanded="false" aria-controls="collapseHistori">
            <i class="fas fa-chevron-down me-1" id="iconCollapseHistori"></i>Tampilkan / Sembunyikan
        </button>
    </div>
    <div class="collapse" id="collapseHistori">
        <!-- Filter Bar -->
        <div class="border-top px-3 py-2 bg-light-subtle">
            <form method="GET" action="<?= $baseUrl ?>" id="filterForm" class="row g-2 align-items-center">
                <input type="hidden" name="year_a" value="<?= $yearA ?>">
                <input type="hidden" name="year_b" value="<?= $yearB ?>">
                <?php if ($ulpIdFilter && !in_array(strtolower((string)session()->get('user_role')), ['administrator', 'admin', 'admin_pusat', 'supervisor_up3'])): ?>
                <input type="hidden" name="ulp_id" value="<?= $ulpIdFilter ?>">
                <?php endif; ?>

                <div class="col-lg-2 col-md-3 col-sm-6">
                    <select name="ulp_id" id="filter_ulp" class="form-select form-select-sm rounded-3">
                        <option value="">Semua ULP</option>
                        <?php foreach ($ulps as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ((int)$ulpIdFilter === (int)$u['id']) ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-sm-6">
                    <select name="penyulang_id" id="filter_penyulang" class="form-select form-select-sm rounded-3">
                        <option value="">Semua Penyulang</option>
                        <?php foreach ($penyulangs as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ((int)$penyulangId === (int)$p['id']) ? 'selected' : '' ?>><?= esc($p['nama_penyulang']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-2 col-sm-4">
                    <select name="year" class="form-select form-select-sm rounded-3">
                        <option value="">Semua Tahun</option>
                        <?php foreach ($availableYears as $yr): ?>
                            <option value="<?= $yr ?>" <?= ((int)$yearFilter === (int)$yr) ? 'selected' : '' ?>>Tahun <?= $yr ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-2 col-sm-4">
                    <select name="category" class="form-select form-select-sm rounded-3">
                        <option value="">Semua Kategori</option>
                        <option value="TEMPORARY" <?= ($categoryFilter === 'TEMPORARY') ? 'selected' : '' ?>>Temporary</option>
                        <option value="PERMANENT" <?= ($categoryFilter === 'PERMANENT') ? 'selected' : '' ?>>Permanent</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-2 col-sm-4">
                    <input type="text" name="search" class="form-control form-control-sm rounded-3" placeholder="Cari penyulang / narasi..." value="<?= esc($search) ?>">
                </div>
                <div class="col-lg-2 col-auto d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="<?= $baseUrl ?>?year_a=<?= $yearA ?>&year_b=<?= $yearB ?><?= $ulpParam ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Reset Filter">
                        <i class="fas fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
        <!-- KPI mini strip -->
        <div class="d-flex gap-3 flex-wrap px-3 py-2 border-bottom" style="font-size:12px;">
            <span class="text-muted">Filter aktif: <strong class="text-dark"><?= number_format($totalCount) ?> kejadian</strong></span>
            <span class="vr"></span>
            <span class="text-warning"><i class="fas fa-arrow-down me-1"></i>DGR <strong><?= number_format($dgrCount) ?></strong></span>
            <span class="text-danger"><i class="fas fa-gauge-high me-1"></i>OCR <strong><?= number_format($ocrCount) ?></strong></span>
            <span class="text-danger ms-2"><i class="fas fa-lock me-1"></i>Permanen <strong><?= number_format($permCount) ?></strong></span>
            <span class="text-secondary"><i class="fas fa-rotate me-1"></i>Temporer <strong><?= number_format($tempCount) ?></strong></span>
            <span class="vr"></span>
            <span class="text-info"><i class="fas fa-plug-circle-bolt me-1"></i>ENS <strong><?= number_format($totalKwh, 1) ?> kWh</strong></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                <thead class="table-light text-muted fw-semibold">
                    <tr>
                        <th class="ps-3" style="width: 50px;">No</th>
                        <th>Waktu Trip</th>
                        <th>Penyulang &amp; ULP</th>
                        <th>Alat Hubung / Recloser</th>
                        <th class="text-center">Relay &amp; Arus</th>
                        <th class="text-center">Kategori</th>
                        <th>Narasi Lapangan</th>
                        <th class="text-end">Durasi</th>
                        <th class="text-end">ENS (kWh)</th>
                        <?php if ($canManage): ?>
                        <th class="text-center pe-3" style="width: 80px;">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="<?= $canManage ? 10 : 9 ?>" class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fs-2 mb-2 d-block text-secondary opacity-50"></i>
                                Tidak ada data gangguan yang sesuai dengan filter.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $idx => $row): ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $offset + $idx + 1 ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= esc($row['event_date']) ?></div>
                                    <small class="text-muted font-monospace" style="font-size: 11px;">
                                        <?= esc(substr((string)$row['interruption_started_at'], 11, 5)) ?>
                                        <?= !empty($row['interruption_ended_at']) ? ' – ' . esc(substr((string)$row['interruption_ended_at'], 11, 5)) : '' ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= esc($row['feeder_name'] ?: ($row['master_penyulang'] ?? '—')) ?></div>
                                    <small class="text-muted"><?= esc($row['master_ulp'] ?: ('ULP ' . $row['ulp_name'])) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= esc($row['switching_device_type'] ?: 'PMCB') ?></span>
                                    <span class="ms-1 small"><?= esc($row['device_name'] ?: '—') ?></span>
                                </td>
                                <td class="text-center">
                                    <?php
                                        $relay = strtoupper((string)($row['relay_trip_type'] ?? '—'));
                                        $badgeCls = str_contains($relay, 'DGR') ? 'bg-warning-subtle text-warning border-warning' : (str_contains($relay, 'OCR') ? 'bg-danger-subtle text-danger border-danger' : 'bg-secondary-subtle text-secondary');
                                    ?>
                                    <span class="badge <?= $badgeCls ?> border px-2 py-1"><?= esc($relay) ?></span>
                                    <?php if (!empty($row['fault_current_amperes'])): ?>
                                        <div class="font-monospace text-muted" style="font-size: 11px;"><?= number_format($row['fault_current_amperes']) ?> A</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (strtoupper((string)$row['interruption_category']) === 'PERMANENT'): ?>
                                        <span class="badge bg-danger rounded-pill px-2">PERMANEN</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-dark border rounded-pill px-2">TEMPORER</span>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width: 250px;">
                                    <div class="text-truncate" title="<?= esc($row['field_narrative_raw']) ?>">
                                        <?= esc($row['field_narrative_raw'] ?: '—') ?>
                                    </div>
                                    <?php if (!empty($row['restoration_action_raw'])): ?>
                                        <small class="text-muted d-block text-truncate" title="<?= esc($row['restoration_action_raw']) ?>">
                                            <i class="fas fa-wrench me-1"></i><?= esc($row['restoration_action_raw']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end font-monospace">
                                    <?= !empty($row['outage_duration_minutes']) ? number_format($row['outage_duration_minutes'], 1) . ' m' : '—' ?>
                                </td>
                                <td class="text-end font-monospace fw-semibold text-info">
                                    <?= !empty($row['energy_not_supplied_kwh']) ? number_format($row['energy_not_supplied_kwh'], 1) : '—' ?>
                                </td>
                                <?php if ($canManage): ?>
                                <td class="text-center pe-3">
                                    <form method="POST" action="<?= site_url('gangguan/delete/' . $row['id']) ?>" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data gangguan ini?');" style="display: inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-xs btn-outline-danger rounded-circle p-1" title="Hapus Data" style="width: 28px; height: 28px;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-0 py-3 d-flex justify-content-between align-items-center">
            <small class="text-muted">Menampilkan <?= min($totalCount, $offset + 1) ?> – <?= min($totalCount, $offset + $perPage) ?> dari <?= number_format($totalCount) ?> kejadian</small>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link rounded-start-pill" href="<?= current_url() . '?' . http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Sebelumnya</a>
                </li>
                <?php
                    $startP = max(1, $page - 2);
                    $endP   = min($totalPages, $page + 2);
                    for ($p = $startP; $p <= $endP; $p++):
                ?>
                    <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= current_url() . '?' . http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link rounded-end-pill" href="<?= current_url() . '?' . http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Selanjutnya</a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

</div><!-- /.container-fluid -->

<!-- ═══════════════════════════════════════════════════ Modal Input (prototype) ══ -->
<?php if ($canManage): ?>
<div class="modal fade" id="modalAddGangguan" tabindex="-1" aria-labelledby="modalAddGangguanLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalAddGangguanLabel">
                    <i class="fas fa-bolt text-warning me-2"></i>Form Input Kejadian Gangguan Penyulang
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= site_url('gangguan/store') ?>" id="formInputGangguan">
                <?= csrf_field() ?>
                <div class="modal-body pt-3">
                    <!-- Prototype notice -->
                    <div class="alert alert-warning rounded-3 d-flex align-items-start gap-2 py-2 mb-3" style="font-size:12px;">
                        <i class="fas fa-triangle-exclamation text-warning fs-6 mt-1 flex-shrink-0"></i>
                        <div>
                            <strong>Form ini merupakan rancangan antarmuka.</strong>
                            Kolom, format, dan validasi masih menunggu konfirmasi format data final.
                            Tombol simpan sudah aktif untuk pengujian teknis — pastikan data sudah benar sebelum menyimpan.
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Tanggal Kejadian <span class="text-danger">*</span></label>
                            <input type="date" name="event_date" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-dark">Waktu Trip Mulai <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="interruption_started_at" class="form-control rounded-3" value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-dark">Waktu Penormalan</label>
                            <input type="datetime-local" name="interruption_ended_at" class="form-control rounded-3">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">ULP <span class="text-danger">*</span></label>
                            <select name="ulp_id" id="modal_ulp_id" class="form-select rounded-3" required>
                                <option value="">— Pilih ULP —</option>
                                <?php foreach ($ulps as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= esc($u['nama_ulp']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Penyulang <span class="text-danger">*</span></label>
                            <select name="penyulang_id" id="modal_penyulang_id" class="form-select rounded-3" required>
                                <option value="">— Pilih ULP Terlebih Dahulu —</option>
                                <?php foreach ($penyulangs as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-ulp="<?= $p['ulp_id'] ?? '' ?>"><?= esc($p['nama_penyulang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">Jenis Alat Hubung</label>
                            <select name="switching_device_type" class="form-select rounded-3">
                                <option value="RECL-PMCB">RECL-PMCB (Recloser)</option>
                                <option value="PMT">PMT GI</option>
                                <option value="LBS">LBS Motorized</option>
                                <option value="FCO">Fuse Cut Out (FCO)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">Nama Peralatan / Lokasi</label>
                            <input type="text" name="device_name" class="form-control rounded-3" placeholder="Contoh: RECL GRIYA CANDI ASRI">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">Tipe Relay Trip</label>
                            <select name="relay_trip_type" class="form-select rounded-3">
                                <option value="DGR">DGR (Directional Ground Relay)</option>
                                <option value="OCR">OCR (Over Current Relay)</option>
                                <option value="OCR/DGR">OCR/DGR (Kombinasi)</option>
                                <option value="UFR">UFR (Under Frequency)</option>
                                <option value="LAINNYA">Lainnya / Belum Diketahui</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">Kategori Gangguan</label>
                            <select name="interruption_category" class="form-select rounded-3">
                                <option value="TEMPORARY">TEMPORARY (Trip-Reclose Berhasil)</option>
                                <option value="PERMANENT">PERMANENT (Lockout / Butuh Penelusuran)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">Arus Gangguan (Ampere)</label>
                            <input type="number" step="0.01" name="fault_current_amperes" class="form-control rounded-3" placeholder="Contoh: 153.0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">Energi Padam (kWh)</label>
                            <input type="number" step="0.001" name="energy_not_supplied_kwh" class="form-control rounded-3" placeholder="Contoh: 300.34">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-dark">Penyebab Gangguan (Sesuai Kode Gangguan)</label>
                            <input type="text" name="cause_raw" class="form-control rounded-3" placeholder="Contoh: POHON, BINA-BURUNG, PETIR, JUMPER PUTUS, dsb.">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-dark">Narasi / Indikasi Penyebab di Lapangan</label>
                            <textarea name="field_narrative_raw" class="form-control rounded-3" rows="2" placeholder="Jelaskan temuan di lapangan saat penelusuran..."></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Tindakan Penormalan</label>
                            <input type="text" name="restoration_action_raw" class="form-control rounded-3" placeholder="Contoh: Penelusuran ulang dan penggantian fuse...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Zona / Section</label>
                            <input type="text" name="extracted_zone_section" class="form-control rounded-3" placeholder="Contoh: ZONA 2 SECTION 2">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 d-flex justify-content-between align-items-center">
                    <span class="text-muted small">
                        <i class="fas fa-shield-halved text-success me-1"></i>Tervalidasi Skema Database
                    </span>
                    <div>
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4">
                            <i class="fas fa-save me-1"></i> Simpan Gangguan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════ Modal Impor XLSX ══ -->
<div class="modal fade" id="modalImportExcel" tabindex="-1" aria-labelledby="modalImportExcelLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-success-subtle text-success rounded-3">
                        <i class="fas fa-file-excel fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalImportExcelLabel">
                            Impor Data Gangguan Penyulang (XLSX)
                        </h5>
                        <small class="text-muted">Parser Native PHP • 22 Kolom Bisnis • Validasi & Isolasi Keamanan</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Source Selection -->
                <div class="row g-3 mb-4">
                    <div class="col-md-7">
                        <div class="card h-100 border rounded-3 p-3 bg-light">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="import_source_type" id="src_upload" value="upload" checked>
                                <label class="form-check-label fw-bold text-dark" for="src_upload">
                                    <i class="fas fa-upload me-1 text-primary"></i> Unggah Berkas XLSX dari Komputer
                                </label>
                            </div>
                            <input type="file" id="excel_file_input" class="form-control rounded-3" accept=".xlsx">
                            <small class="text-muted mt-1 d-block" style="font-size: 11px;">Format berkas harus Microsoft Excel (.xlsx), ukuran maks. 15MB.</small>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="card h-100 border rounded-3 p-3 bg-light">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="import_source_type" id="src_server" value="server">
                                <label class="form-check-label fw-bold text-dark" for="src_server">
                                    <i class="fas fa-server me-1 text-secondary"></i> Gunakan Berkas Kanonik Server
                                </label>
                            </div>
                            <div class="p-2 border rounded bg-white text-muted small font-monospace">
                                E:\GANGGUAN PENYULANG.xlsx
                            </div>
                            <small class="text-muted mt-1 d-block" style="font-size: 11px;">Berkas workbook gangguan resmi yang tersedia di root server lokal.</small>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="text-muted small">
                        <i class="fas fa-info-circle me-1 text-info"></i>
                        Pratinjau akan memverifikasi 22 header kolom bisnis, mendeteksi duplikat hash, dan memvalidasi baris sebelum masuk database.
                    </div>
                    <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" id="btnRunPreview" onclick="runExcelPreview()">
                        <i class="fas fa-magnifying-glass me-1"></i> Periksa Berkas (Dry Run)
                    </button>
                </div>

                <!-- Preview Results Container (Hidden until preview loaded) -->
                <div id="previewResultsContainer" style="display: none;">
                    <hr class="my-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fas fa-clipboard-check text-success me-2"></i>Hasil Pratinjau & Diagnosa Berkas
                        </h6>
                        <span id="previewHeaderBadge" class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">
                            Header Valid (22/22)
                        </span>
                    </div>

                    <!-- Metrics Cards -->
                    <div class="row g-2 mb-4">
                        <div class="col-6 col-md-2">
                            <div class="p-2 border rounded-3 text-center bg-light">
                                <small class="text-muted d-block" style="font-size: 11px;">Baris Fisik</small>
                                <span class="fw-bold fs-5 text-dark font-monospace" id="statTotalRows">0</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="p-2 border rounded-3 text-center bg-success-subtle border-success-subtle">
                                <small class="text-success d-block fw-semibold" style="font-size: 11px;">Siap Impor</small>
                                <span class="fw-bold fs-5 text-success font-monospace" id="statReadyImport">0</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="p-2 border rounded-3 text-center bg-info-subtle border-info-subtle">
                                <small class="text-info d-block fw-semibold" style="font-size: 11px;">Valid Bersih</small>
                                <span class="fw-bold fs-5 text-info font-monospace" id="statValidCount">0</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="p-2 border rounded-3 text-center bg-warning-subtle border-warning-subtle">
                                <small class="text-warning-emphasis d-block fw-semibold" style="font-size: 11px;">Peringatan / #REF!</small>
                                <span class="fw-bold fs-5 text-warning-emphasis font-monospace" id="statWarningCount">0</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="p-2 border rounded-3 text-center bg-secondary-subtle">
                                <small class="text-secondary d-block fw-semibold" style="font-size: 11px;">Duplikat Hash</small>
                                <span class="fw-bold fs-5 text-secondary font-monospace" id="statDuplicateCount">0</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="p-2 border rounded-3 text-center bg-danger-subtle border-danger-subtle">
                                <small class="text-danger d-block fw-semibold" style="font-size: 11px;">Ditolak (Kosong)</small>
                                <span class="fw-bold fs-5 text-danger font-monospace" id="statRejectedCount">0</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs for Preview Details -->
                    <ul class="nav nav-tabs nav-tabs-bordered mb-3" id="previewTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-2 px-3 fw-semibold small" id="tab-samples-btn" data-bs-toggle="tab" data-bs-target="#tab-samples" type="button" role="tab">
                                <i class="fas fa-table-list me-1"></i> Sampel Data Normalisasi
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2 px-3 fw-semibold small" id="tab-columns-btn" data-bs-toggle="tab" data-bs-target="#tab-columns" type="button" role="tab">
                                <i class="fas fa-columns me-1"></i> Pemetaan 22 Kolom Standar
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2 px-3 fw-semibold small" id="tab-issues-btn" data-bs-toggle="tab" data-bs-target="#tab-issues" type="button" role="tab">
                                <i class="fas fa-triangle-exclamation me-1 text-warning"></i> Catatan & Isu Baris (<span id="badgeIssueCount">0</span>)
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="previewTabsContent">
                        <!-- Tab 1: Samples -->
                        <div class="tab-pane fade show active" id="tab-samples" role="tabpanel">
                            <div class="table-responsive border rounded-3" style="max-height: 250px;">
                                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 11px;">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Baris</th>
                                            <th>Tanggal</th>
                                            <th>ULP</th>
                                            <th>Penyulang</th>
                                            <th>Relai</th>
                                            <th>Kategori</th>
                                            <th>Lama Padam</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="previewSamplesTableBody"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tab 2: Column Mapping -->
                        <div class="tab-pane fade" id="tab-columns" role="tabpanel">
                            <div class="table-responsive border rounded-3" style="max-height: 250px;">
                                <table class="table table-sm table-striped align-middle mb-0" style="font-size: 11px;">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th style="width: 50px;">Idx</th>
                                            <th>Kolom Standar (Kanonik)</th>
                                            <th>Nama di Berkas</th>
                                            <th class="text-center" style="width: 100px;">Kecocokan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="previewColumnsTableBody"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tab 3: Issues -->
                        <div class="tab-pane fade" id="tab-issues" role="tabpanel">
                            <div class="table-responsive border rounded-3" style="max-height: 250px;">
                                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 11px;">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th style="width: 60px;">Baris</th>
                                            <th style="width: 90px;">Tipe</th>
                                            <th>Alasan / Penjelasan Normalisasi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="previewIssuesTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-3 bg-light d-flex justify-content-between">
                <div>
                    <span id="importExecutionStatus" class="small text-muted"></span>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-success rounded-pill px-4" id="btnExecuteImport" disabled onclick="executeExcelImport()">
                        <i class="fas fa-cloud-arrow-up me-1"></i> Eksekusi Impor ke Database
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<!-- ═══════════════════════════════════════════════════════════ End Content ══ -->
<?= $this->endSection() ?>

<!-- ═══════════════════════════════════════════════════════════ Scripts ══ -->
<?= $this->section('scripts') ?>
<script>
(function() {
    'use strict';

    /**
     * Ensure Chart.js is loaded and ready before calling callback.
     * Checks window.Chart -> local plugin asset -> CDN fallback -> error message.
     */
    function ensureChartLoaded(callback) {
        if (typeof window.Chart !== 'undefined') {
            callback();
            return;
        }

        // Try local asset first
        var localScript = document.createElement('script');
        localScript.src = '<?= base_url('plugins/chart.js') ?>';
        localScript.onload = function() {
            if (typeof window.Chart !== 'undefined') {
                callback();
            } else {
                loadCdn();
            }
        };
        localScript.onerror = loadCdn;

        function loadCdn() {
            var cdnScript = document.createElement('script');
            cdnScript.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js';
            cdnScript.onload = function() {
                if (typeof window.Chart !== 'undefined') {
                    callback();
                } else {
                    showError();
                }
            };
            cdnScript.onerror = showError;
            document.head.appendChild(cdnScript);
        }

        function showError() {
            var container = document.getElementById('chartGangguanContainer');
            if (container) {
                container.innerHTML = '<div class="alert alert-warning py-3 text-center my-3"><i class="fas fa-exclamation-triangle me-2"></i>Pustaka grafik Chart.js tidak dapat dimuat. Silakan periksa koneksi internet atau muat ulang halaman.</div>';
            }
        }

        document.head.appendChild(localScript);
    }

    document.addEventListener('DOMContentLoaded', function() {
        // ── Chart.js – Monthly bar chart ──────────────────────────────────
        var ctx = document.getElementById('chartGangguan');
        if (ctx) {
            ensureChartLoaded(function() {
                var monthLabels = <?= json_encode($monthLabels) ?>;
                var dataA       = <?= json_encode(array_values($monthlyA)) ?>;
                var dataB       = <?= json_encode(array_values($monthlyB)) ?>;
                var yearA       = <?= (int)$yearA ?>;
                var yearB       = <?= (int)$yearB ?>;

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: monthLabels,
                        datasets: [
                            {
                                label: 'Tahun ' + yearA,
                                data: dataA,
                                backgroundColor: 'rgba(13,110,253,0.75)',
                                borderColor: '#0d6efd',
                                borderWidth: 1,
                                borderRadius: 4,
                            },
                            {
                                label: 'Tahun ' + yearB,
                                data: dataB,
                                backgroundColor: 'rgba(173,181,189,0.6)',
                                borderColor: '#adb5bd',
                                borderWidth: 1,
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        return ' ' + ctx.dataset.label + ': ' + ctx.parsed.y + ' kejadian';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 11 } }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: { font: { size: 11 }, stepSize: 1 },
                                grid: { color: 'rgba(0,0,0,0.05)' }
                            }
                        },
                        onClick: function(e, elements) {
                            if (elements.length > 0) {
                                var yr = elements[0].datasetIndex === 0 ? yearA : yearB;
                                var url = '<?= site_url('gangguan') ?>?year_a=<?= $yearA ?>&year_b=<?= $yearB ?><?= $ulpParam ?>&year=' + yr + '&page=1';
                                var collapseEl = document.getElementById('collapseHistori');
                                if (collapseEl) {
                                    var bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
                                    bsCollapse.show();
                                }
                                window.location.href = url;
                            }
                        }
                    }
                });
            });
        }

        // ── Collapse icon toggle ──────────────────────────────────────────
        var colEl = document.getElementById('collapseHistori');
        if (colEl) {
            colEl.addEventListener('show.bs.collapse', function() {
                var icon = document.getElementById('iconCollapseHistori');
                if (icon) { icon.classList.remove('fa-chevron-down'); icon.classList.add('fa-chevron-up'); }
            });
            colEl.addEventListener('hide.bs.collapse', function() {
                var icon = document.getElementById('iconCollapseHistori');
                if (icon) { icon.classList.remove('fa-chevron-up'); icon.classList.add('fa-chevron-down'); }
            });
            // Auto-expand if any list filter is active
            <?php if ($yearFilter || $penyulangId || $categoryFilter || $search !== ''): ?>
            bootstrap.Collapse.getOrCreateInstance(colEl, { toggle: false }).show();
            <?php endif; ?>
        }

        // ── Modal: cascade ULP → Penyulang ───────────────────────────────
        var modalUlp      = document.getElementById('modal_ulp_id');
        var modalPenyulang = document.getElementById('modal_penyulang_id');
        if (modalUlp && modalPenyulang) {
            var allOpts = Array.from(modalPenyulang.querySelectorAll('option[data-ulp]'));
            modalUlp.addEventListener('change', function() {
                var selUlp = this.value;
                modalPenyulang.value = '';
                allOpts.forEach(function(opt) {
                    opt.style.display = (!selUlp || opt.getAttribute('data-ulp') === selUlp) ? '' : 'none';
                });
            });
        }

        // ── Filter cascade ULP → Penyulang via AJAX ──────────────────────
        var filterUlp      = document.getElementById('filter_ulp');
        var filterPenyulang = document.getElementById('filter_penyulang');
        if (filterUlp && filterPenyulang) {
            filterUlp.addEventListener('change', function() {
                var selUlp = this.value;
                if (!selUlp) { return; }
                fetch('<?= site_url('gangguan/api-feeders') ?>/' + selUlp)
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.status === 'success' && res.data) {
                            filterPenyulang.innerHTML = '<option value="">Semua Penyulang</option>';
                            res.data.forEach(function(p) {
                                var opt = document.createElement('option');
                                opt.value = p.id;
                                opt.textContent = p.nama_penyulang;
                                filterPenyulang.appendChild(opt);
                            });
                        }
                    })
                    .catch(function(err) { console.error('Gagal memuat penyulang:', err); });
            });
        }
    });

    // ── Global Excel Preview & Import Handlers ────────────────────────
    var currentImportToken = null;

    window.runExcelPreview = function() {
        var isServer = document.getElementById('src_server').checked;
        var fileInput = document.getElementById('excel_file_input');
        var btnPreview = document.getElementById('btnRunPreview');
        var btnExecute = document.getElementById('btnExecuteImport');
        var container = document.getElementById('previewResultsContainer');
        var statusEl = document.getElementById('importExecutionStatus');

        if (!isServer && (!fileInput.files || fileInput.files.length === 0)) {
            alert('Silakan pilih berkas .xlsx terlebih dahulu.');
            return;
        }

        var formData = new FormData();
        formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        if (isServer) {
            formData.append('use_server_file', '1');
        } else {
            formData.append('excel_file', fileInput.files[0]);
        }

        btnPreview.disabled = true;
        btnPreview.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Membaca Berkas...';
        btnExecute.disabled = true;
        statusEl.innerHTML = '<span class="text-primary"><i class="fas fa-spinner fa-spin me-1"></i>Menganalisis lembar kerja dan 22 kolom...</span>';

        fetch('<?= site_url('gangguan/preview-excel') ?>', {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btnPreview.disabled = false;
            btnPreview.innerHTML = '<i class="fas fa-magnifying-glass me-1"></i> Periksa Berkas (Dry Run)';

            if (res.status !== 'success') {
                statusEl.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>' + (res.message || 'Gagal memeriksa berkas.') + '</span>';
                alert(res.message || 'Gagal memeriksa berkas.');
                return;
            }

            var d = res.data;
            currentImportToken = res.import_token;
            container.style.display = 'block';

            // Metrics
            document.getElementById('statTotalRows').textContent = d.total_physical_rows || 0;
            document.getElementById('statReadyImport').textContent = d.ready_to_import || 0;
            document.getElementById('statValidCount').textContent = d.valid_count || 0;
            document.getElementById('statWarningCount').textContent = d.warning_count || 0;
            document.getElementById('statDuplicateCount').textContent = d.duplicate_count || 0;
            document.getElementById('statRejectedCount').textContent = d.rejected_count || 0;
            document.getElementById('badgeIssueCount').textContent = d.total_issues_count || 0;

            var badge = document.getElementById('previewHeaderBadge');
            if (d.header_valid) {
                badge.className = 'badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill';
                badge.innerHTML = '<i class="fas fa-check me-1"></i>Header Standar Valid (22/22)';
            } else {
                badge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill';
                badge.innerHTML = '<i class="fas fa-triangle-exclamation me-1"></i>Header Kolom Tidak Sesuai';
            }

            // Samples Table
            var sampleBody = document.getElementById('previewSamplesTableBody');
            sampleBody.innerHTML = '';
            if (d.samples && d.samples.length > 0) {
                d.samples.forEach(function(s) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td>#' + s.row + '</td>' +
                        '<td>' + (s.event_date || '-') + '</td>' +
                        '<td>' + (s.ulp || '-') + '</td>' +
                        '<td><span class="fw-semibold">' + (s.penyulang || '-') + '</span></td>' +
                        '<td>' + (s.rele || '-') + '</td>' +
                        '<td><span class="badge ' + (s.kategori === 'PERMANENT' ? 'bg-danger' : 'bg-primary') + '">' + (s.kategori || '-') + '</span></td>' +
                        '<td>' + (s.durasi_menit ? s.durasi_menit + ' m' : (s.lama_padam || '-')) + '</td>' +
                        '<td class="text-muted small">' + (s.keterangan || '-') + '</td>';
                    sampleBody.appendChild(tr);
                });
            } else {
                sampleBody.innerHTML = '<tr><td colspan="8" class="text-center py-3 text-muted">Tidak ada data sampel untuk ditampilkan.</td></tr>';
            }

            // Columns Mapping Table
            var colBody = document.getElementById('previewColumnsTableBody');
            colBody.innerHTML = '';
            if (d.column_mapping) {
                d.column_mapping.forEach(function(c) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td class="font-monospace text-muted">#' + c.index + '</td>' +
                        '<td class="fw-semibold">' + c.expected + '</td>' +
                        '<td class="font-monospace">' + (c.actual || '<span class="text-danger">(kosong)</span>') + '</td>' +
                        '<td class="text-center">' + (c.matched ? '<span class="badge bg-success-subtle text-success">Cocok</span>' : '<span class="badge bg-danger-subtle text-danger">Tidak Cocok</span>') + '</td>';
                    colBody.appendChild(tr);
                });
            }

            // Issues Table
            var issueBody = document.getElementById('previewIssuesTableBody');
            issueBody.innerHTML = '';
            if (d.issues && d.issues.length > 0) {
                d.issues.forEach(function(iss) {
                    var tr = document.createElement('tr');
                    var badgeCls = iss.type === 'REJECTED' ? 'bg-danger' : 'bg-warning text-dark';
                    tr.innerHTML = '<td class="font-monospace">#' + iss.row + '</td>' +
                        '<td><span class="badge ' + badgeCls + '">' + iss.type + '</span></td>' +
                        '<td>' + iss.reason + '</td>';
                    issueBody.appendChild(tr);
                });
            } else {
                issueBody.innerHTML = '<tr><td colspan="3" class="text-center py-3 text-success"><i class="fas fa-check-circle me-1"></i>Tidak ada anomali atau isu baris terdeteksi.</td></tr>';
            }

            // Enable execute import button if ready
            if (d.header_valid && d.ready_to_import > 0) {
                btnExecute.disabled = false;
                statusEl.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>' + d.ready_to_import + ' data siap diimpor ke database.</span>';
            } else if (!d.header_valid) {
                btnExecute.disabled = true;
                statusEl.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Impor ditolak karena struktur kolom berkas tidak sesuai standar.</span>';
            } else {
                btnExecute.disabled = true;
                statusEl.innerHTML = '<span class="text-warning-emphasis"><i class="fas fa-info-circle me-1"></i>Semua data telah diimpor sebelumnya (duplikat).</span>';
            }
        })
        .catch(function(err) {
            btnPreview.disabled = false;
            btnPreview.innerHTML = '<i class="fas fa-magnifying-glass me-1"></i> Periksa Berkas (Dry Run)';
            statusEl.innerHTML = '<span class="text-danger">Koneksi gagal atau respons server tidak valid.</span>';
            console.error('Preview error:', err);
        });
    };

    window.executeExcelImport = function() {
        if (!currentImportToken) {
            alert('Silakan lakukan pratinjau berkas terlebih dahulu.');
            return;
        }

        if (!confirm('Apakah Anda yakin ingin mengeksekusi impor data gangguan ke database?')) {
            return;
        }

        var btnExecute = document.getElementById('btnExecuteImport');
        var btnPreview = document.getElementById('btnRunPreview');
        var statusEl = document.getElementById('importExecutionStatus');

        btnExecute.disabled = true;
        btnExecute.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan ke Database...';
        btnPreview.disabled = true;

        var formData = new FormData();
        formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        formData.append('import_token', currentImportToken);

        fetch('<?= site_url('gangguan/import-excel') ?>', {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btnExecute.innerHTML = '<i class="fas fa-cloud-arrow-up me-1"></i> Eksekusi Impor ke Database';
            btnPreview.disabled = false;

            if (res.status === 'success') {
                statusEl.innerHTML = '<span class="text-success fw-bold"><i class="fas fa-check-double me-1"></i>' + res.message + '</span>';
                alert(res.message);
                window.location.reload();
            } else {
                btnExecute.disabled = false;
                statusEl.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>' + (res.message || 'Gagal mengeksekusi impor.') + '</span>';
                alert(res.message || 'Gagal mengeksekusi impor.');
            }
        })
        .catch(function(err) {
            btnExecute.disabled = false;
            btnExecute.innerHTML = '<i class="fas fa-cloud-arrow-up me-1"></i> Eksekusi Impor ke Database';
            btnPreview.disabled = false;
            statusEl.innerHTML = '<span class="text-danger">Gagal menghubungi server saat eksekusi impor.</span>';
            console.error('Import error:', err);
        });
    };
})();
</script>
<?= $this->endSection() ?>
