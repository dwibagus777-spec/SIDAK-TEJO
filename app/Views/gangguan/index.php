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
            <div class="col-auto ms-auto">
                <?php if ($canManage): ?>
                <button type="button" class="btn btn-sm btn-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalAddGangguan">
                    <i class="fas fa-plus me-1"></i> Form Input (Draft)
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
    <div class="card-body pt-0" style="min-height: 280px;">
        <canvas id="chartGangguan" height="100"></canvas>
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
                    <span class="badge bg-warning-subtle text-warning border px-2 py-1 small">
                        <i class="fas fa-lock me-1"></i>Penyimpanan Terkunci (Menunggu Format Data)
                    </span>
                    <div>
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
                        <button type="button" class="btn btn-secondary rounded-pill px-4" disabled title="Penyimpanan dinonaktifkan sementara menunggu contoh data dan format final">
                            <i class="fas fa-clock me-1"></i> Simpan (Menunggu Format Data)
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ Scripts ══ -->
<script>
(function() {
    'use strict';

    // ── Chart.js – Monthly bar chart ──────────────────────────────────────
    var monthLabels = <?= json_encode($monthLabels) ?>;
    var dataA       = <?= json_encode(array_values($monthlyA)) ?>;
    var dataB       = <?= json_encode(array_values($monthlyB)) ?>;
    var yearA       = <?= (int)$yearA ?>;
    var yearB       = <?= (int)$yearB ?>;

    var ctx = document.getElementById('chartGangguan');
    if (ctx) {
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
                maintainAspectRatio: true,
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
                        var monthIdx = elements[0].index + 1; // 1-based
                        var yr = elements[0].datasetIndex === 0 ? yearA : yearB;
                        var url = '<?= site_url('gangguan') ?>?year_a=<?= $yearA ?>&year_b=<?= $yearB ?><?= $ulpParam ?>&year=' + yr + '&page=1';
                        // Expand histori section and apply filter
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
    }

    // ── Collapse icon toggle ──────────────────────────────────────────────
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

    // ── Modal: cascade ULP → Penyulang ───────────────────────────────────
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

    // ── Filter cascade ULP → Penyulang via AJAX ──────────────────────────
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
})();
</script>

<?= $this->endSection() ?>
