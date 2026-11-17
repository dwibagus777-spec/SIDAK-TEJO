<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Analitik Gangguan Penyulang - SIDAK TEJO<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Analitik Gangguan Penyulang<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    .gg-card {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
    }
    .gg-card-header {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .gg-card-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: 0.01em;
        margin: 0;
    }
    .gg-num-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 800;
        margin-right: 6px;
    }
    .gg-pivot-wrap {
        max-height: 410px;
        overflow: auto;
        border-top: 1px solid #e2e8f0;
    }
    .gg-pivot-table {
        font-size: 11.5px;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
        width: 100%;
    }
    .gg-pivot-table th,
    .gg-pivot-table td {
        white-space: nowrap;
        padding: 5px 8px;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        border-right: 1px solid rgba(15, 23, 42, 0.06);
    }
    .gg-pivot-table thead th {
        position: sticky;
        top: 0;
        z-index: 3;
        font-weight: 700;
    }
    .gg-pivot-table thead tr:nth-child(2) th {
        top: 28px;
        z-index: 3;
    }
    .gg-sticky-col-1 { position: sticky; left: 0; z-index: 2; min-width: 145px; }
    .gg-sticky-col-2 { position: sticky; left: 145px; z-index: 2; min-width: 115px; }
    .gg-sticky-col-3 { position: sticky; left: 260px; z-index: 2; min-width: 155px; }
    .gg-pivot-table thead th.gg-sticky-col-1,
    .gg-pivot-table thead th.gg-sticky-col-2,
    .gg-pivot-table thead th.gg-sticky-col-3 { z-index: 5; }

    /* Looker-inspired Pivot Color Themes (legible accessible contrast) */
    .gg-pivot-amber thead th { background: #f59e0b; color: #1e293b; }
    .gg-pivot-amber tbody td.gg-sticky-col-1,
    .gg-pivot-amber tbody td.gg-sticky-col-2,
    .gg-pivot-amber tbody td.gg-sticky-col-3 { background: #fffbeb; }
    .gg-pivot-amber tbody tr:hover td { background: #fef3c7 !important; }
    .gg-pivot-amber tfoot td { background: #fde68a; font-weight: 700; position: sticky; bottom: 0; z-index: 2; }

    .gg-pivot-blue thead th { background: #2563eb; color: #ffffff; }
    .gg-pivot-blue tbody td.gg-sticky-col-1,
    .gg-pivot-blue tbody td.gg-sticky-col-2,
    .gg-pivot-blue tbody td.gg-sticky-col-3 { background: #eff6ff; }
    .gg-pivot-blue tbody tr:hover td { background: #dbeafe !important; }
    .gg-pivot-blue tfoot td { background: #bfdbfe; font-weight: 700; position: sticky; bottom: 0; z-index: 2; }

    .gg-pivot-green thead th { background: #15803d; color: #ffffff; }
    .gg-pivot-green tbody td.gg-sticky-col-1,
    .gg-pivot-green tbody td.gg-sticky-col-2,
    .gg-pivot-green tbody td.gg-sticky-col-3 { background: #f0fdf4; }
    .gg-pivot-green tbody tr:hover td { background: #dcfce7 !important; }
    .gg-pivot-green tfoot td { background: #bbf7d0; font-weight: 700; position: sticky; bottom: 0; z-index: 2; }

    .gg-zero { color: #94a3b8; }
    .gg-chart-scroll-x {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .gg-chart-wide-inner {
        width: 100%;
        position: relative;
    }
    @media (max-width: 767.98px) {
        .gg-chart-wide-inner {
            min-width: 680px;
        }
    }
    .gg-log-table-wrap {
        max-height: 540px;
        overflow: auto;
        -webkit-overflow-scrolling: touch;
        position: relative;
        border-top: 1px solid #e2e8f0;
        scrollbar-gutter: stable;
    }
    @media (max-width: 767.98px) {
        .gg-log-table-wrap {
            max-height: 62vh;
        }
    }
    .gg-log-table {
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
        width: 100%;
    }
    .gg-log-table thead th {
        position: sticky;
        top: 0;
        z-index: 4;
        background-color: #f8fafc !important;
        color: #475569;
        border-bottom: 2px solid #cbd5e1;
        box-shadow: 0 1px 0 rgba(15, 23, 42, 0.08);
        white-space: nowrap;
    }
</style>

<div class="container-fluid px-3 py-3">

<?php
$monthLabels  = \App\Services\GangguanAnalyticsService::MONTH_LABELS;
$baseUrl      = site_url('gangguan');
$ulpParam     = $ulpIdFilter ? '&ulp_id=' . (int)$ulpIdFilter : '';
$feederParam  = $penyulangId ? '&penyulang_id=' . (int)$penyulangId : '';
$scopeParam   = '&scope=' . urlencode($scope ?? 'year_a');
$queryForSync = 'year_a=' . (int)$yearA . '&year_b=' . (int)$yearB . $scopeParam . $ulpParam . $feederParam;

$syncState    = strtoupper((string)($syncStatus['status'] ?? 'STANDBY'));
$syncError    = $syncStatus['last_error'] ?? null;
$lastSyncedAt = $syncStatus['last_synced_at'] ?? null;
$syncRowCount = (int)($syncStatus['row_count'] ?? 0);

// Unpack 12 analytics items
$item1  = $analytics12['permanen_vs_temporer'] ?? [];
$item2  = $analytics12['gangguan_per_ulp'] ?? [];
$item3  = $analytics12['total_gangguan'] ?? [];
$item4  = $analytics12['permanen_vs_temporer_per_ulp'] ?? [];
$item5  = $analytics12['relai_bekerja'] ?? [];
$item6  = $analytics12['rasio_pmt_vs_recl_pmcb'] ?? [];
$item7  = $analytics12['pareto_penyebab'] ?? [];
$item8  = $analytics12['top_10_penyulang'] ?? [];
$item9  = $analytics12['sebaran_pmt_recloser'] ?? [];
$item10 = $analytics12['detail_penyulang_bulanan'] ?? [];
$item11 = $analytics12['penyulang_vs_penyebab'] ?? [];
$item12 = $analytics12['detail_bulanan_peralatan'] ?? [];

$focusLabel   = $analytics12['focus_label'] ?? ("Tahun {$yearA}");
$focusCat     = $item1['focus'] ?? ['TEMPORARY' => 0, 'PERMANENT' => 0, 'UNKNOWN' => 0, 'total' => 0, 'temp_pct' => 0, 'perm_pct' => 0];
$yoyInfo      = $item3['yoy'] ?? [];
$focusTotal   = (int)($item3['focus_total'] ?? 0);
$combinedTot  = (int)($item3['combined_total'] ?? 0);
$fullYearATot = (int)($item3['year_a_total'] ?? 0);
$fullYearBTot = (int)($item3['year_b_total'] ?? 0);
?>

<!-- ═══════════════════════════════════════════════════════ Flash messages ══ -->
<?php if (session()->getFlashdata('success')): ?>
<div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm d-flex align-items-center mb-3" role="alert">
    <i class="fas fa-check-circle me-2 fs-5"></i>
    <div><?= esc(session()->getFlashdata('success')) ?></div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm d-flex align-items-center mb-3" role="alert">
    <i class="fas fa-triangle-exclamation me-2 fs-5"></i>
    <div><?= esc(session()->getFlashdata('error')) ?></div>
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

<?php if ($syncState === 'ERROR' && !empty($syncError)): ?>
<div class="alert alert-warning border-warning rounded-4 shadow-sm d-flex align-items-start gap-2 mb-3" role="alert">
    <i class="fas fa-triangle-exclamation text-warning fs-5 mt-1 flex-shrink-0"></i>
    <div class="small">
        <strong>Pembaruan data dari Google Sheets gagal:</strong> <?= esc($syncError) ?>.
        Menampilkan data tersimpan terakhir<?= $lastSyncedAt ? ' (terakhir sinkron: ' . esc($lastSyncedAt) . ')' : '' ?>.
    </div>
</div>
<?php endif; ?>

<!-- ═════════════════════════════════════════════ Sync & Filter Control Bar ══ -->
<div class="gg-card mb-4">
    <div class="card-body py-2.5 px-3">
        <!-- Top source provenance & actions -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-2 mb-2 border-bottom">
            <div class="d-flex flex-wrap align-items-center gap-2" style="min-width: 0; max-width: 100%;">
                <span class="badge <?= $syncState === 'SYNCED' ? 'bg-success-subtle text-success border border-success-subtle' : ($syncState === 'ERROR' ? 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' : 'bg-secondary-subtle text-secondary border') ?> rounded-pill px-2.5 py-1" style="font-size: 11px; white-space: normal; text-align: start; max-width: 100%; line-height: 1.35;">
                    <i class="fas fa-table me-1"></i><?= esc($syncStatus['source_label'] ?? 'Google Sheets (DATA_GANGGUAN)') ?>
                    &bull; <?= $syncState === 'SYNCED' ? 'Tersinkron' : ($syncState === 'ERROR' ? 'Mode Cache Offline' : $syncState) ?>
                </span>
                <?php if ($lastSyncedAt): ?>
                    <span class="text-muted small" style="font-size: 11.5px;">
                        <i class="far fa-clock me-1"></i>Diperbarui: <strong class="text-dark"><?= esc($lastSyncedAt) ?></strong>
                        (<?= number_format($syncRowCount) ?> baris)
                    </span>
                <?php endif; ?>
                <button class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-0.5" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMappingDoc" style="font-size: 11px;">
                    <i class="fas fa-circle-info me-1"></i>Pemetaan Kolom &amp; Definisi
                </button>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= site_url('dashboard?year_a=' . (int)$yearA . '&year_b=' . (int)$yearB . $ulpParam) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                    <i class="fas fa-gauge-high me-1"></i>Dashboard Utama
                </a>
                <?php if ($canManage): ?>
                <form method="POST" action="<?= site_url('gangguan/sync-sheets') ?>" class="d-inline m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect_to" value="gangguan">
                    <input type="hidden" name="redirect_query" value="<?= esc($queryForSync) ?>">
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3" title="Ambil data terbaru langsung dari Google Sheets DATA_GANGGUAN">
                        <i class="fas fa-rotate me-1"></i>Sinkronkan Google Sheets
                    </button>
                </form>
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalImportExcel">
                    <i class="fas fa-file-excel me-1"></i>Impor XLSX
                </button>
                <button type="button" class="btn btn-sm btn-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalAddGangguan">
                    <i class="fas fa-plus me-1"></i>Tambah Gangguan
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Collapsible Column Mapping Documentation -->
        <div class="collapse mb-2" id="collapseMappingDoc">
            <div class="bg-light rounded-3 p-3 border small" style="font-size: 11.5px;">
                <div class="fw-bold text-dark mb-1"><i class="fas fa-book-open text-primary me-1"></i>Dokumentasi Pemetaan Kolom Sumber (Google Sheets &rarr; 12 Item Analitik)</div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <ul class="mb-0 ps-3">
                            <li><strong>Item 1 &amp; 4 (Permanen vs Temporer):</strong> Kolom <code>KATEGORI</code> (<code>Temporer</code> &rarr; Temporer, <code>Permanen</code> &rarr; Permanen).</li>
                            <li><strong>Item 2 (Gangguan per ULP):</strong> Kolom <code>ULP</code> (<code>KRIAN</code>, <code>SIDOARJO KOTA</code>, <code>PORONG</code>).</li>
                            <li><strong>Item 3 (Total Gangguan):</strong> Jumlah baris kejadian (kolom <code>Nilai</code> = 1 per kejadian) sesuai filter tahun <code>year_a</code> / <code>year_b</code>.</li>
                            <li><strong>Item 5 (Relai Kerja):</strong> Kolom <code>RELE KERJA</code> (<code>DGR</code>, <code>OCR-INST</code>, <code>OCR</code>, <code>OCR-DGR</code>, <code>DGR-OCR-INST</code>, <code>EF</code>; kosong ditampilkan sebagai <code>TIDAK TERCATAT</code>).</li>
                            <li><strong>Item 6 (Rasio PMT vs REC/PMCB):</strong> Kolom <code>PMT-RECL/PMCB</code> (<code>PMT</code> vs <code>RECL-PMCB</code>).</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="mb-0 ps-3">
                            <li><strong>Item 7 &amp; 11 (Pareto &amp; Matrix Penyebab):</strong> Kolom <code>PENYEBAB SESUAI KODE GANGGUAN</code> (diurutkan menurun dengan garis kontribusi kumulatif Pareto 0–100%).</li>
                            <li><strong>Item 8 &amp; 10 (Top 10 &amp; Detail Bulanan Penyulang):</strong> Kolom <code>PENYULANG</code>, <code>ULP</code>, <code>RECLOSER</code>, dan tanggal kejadian (<code>tanggal</code> / <code>bln ke-</code> / <code>thn</code>).</li>
                            <li><strong>Item 9 &amp; 12 (Sebaran &amp; Detail Bulanan Peralatan):</strong> Kolom <code>RECLOSER</code> &amp; <code>PMT-RECL/PMCB</code> diklasifikasikan ke famili: <code>PMT</code> (awalan PMT), <code>Recloser</code> (awalan RECL), <code>PMCB</code> (awalan PMCB), dan <code>Key Point</code> (awalan GH/KP/KEY/LBS/ABS/ASS/SEC, mis. <code>GH SAFLOCK</code>).</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Year, Scope, ULP, Penyulang Filters -->
        <form method="GET" action="<?= $baseUrl ?>" id="analyticsForm" class="row g-2 align-items-center">
            <div class="col-auto">
                <label for="selYearA" class="form-label small mb-0 me-1 text-muted fw-semibold">Tahun A</label>
                <select name="year_a" id="selYearA" class="form-select form-select-sm rounded-pill d-inline-block font-monospace fw-bold" style="width: auto;" onchange="this.form.submit()">
                    <?php foreach ($availableYears as $yr): ?>
                        <option value="<?= (int)$yr ?>" <?= ((int)$yr === (int)$yearA) ? 'selected' : '' ?>><?= (int)$yr ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto text-muted"><i class="fas fa-arrows-left-right"></i></div>
            <div class="col-auto">
                <label for="selYearB" class="form-label small mb-0 me-1 text-muted fw-semibold">Tahun B</label>
                <select name="year_b" id="selYearB" class="form-select form-select-sm rounded-pill d-inline-block font-monospace fw-bold" style="width: auto;" onchange="this.form.submit()">
                    <?php foreach ($availableYears as $yr): ?>
                        <option value="<?= (int)$yr ?>" <?= ((int)$yr === (int)$yearB) ? 'selected' : '' ?>><?= (int)$yr ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label for="selScope" class="form-label small mb-0 me-1 text-muted fw-semibold">Fokus Grafik</label>
                <select name="scope" id="selScope" class="form-select form-select-sm rounded-pill d-inline-block" style="width: auto;" onchange="this.form.submit()">
                    <option value="year_a" <?= ($scope === 'year_a') ? 'selected' : '' ?>>Tahun A (<?= (int)$yearA ?>)</option>
                    <option value="year_b" <?= ($scope === 'year_b') ? 'selected' : '' ?>>Tahun B (<?= (int)$yearB ?>)</option>
                    <option value="combined" <?= ($scope === 'combined') ? 'selected' : '' ?>>Gabungan (<?= $yearA === $yearB ? (int)$yearA : (int)$yearA . ' + ' . (int)$yearB ?>)</option>
                </select>
            </div>
            <?php if (in_array(strtolower((string)session()->get('user_role')), ['administrator', 'admin', 'admin_pusat', 'supervisor_up3'])): ?>
            <div class="col-auto">
                <label for="selUlpAnalytics" class="form-label small mb-0 me-1 text-muted fw-semibold">ULP</label>
                <select name="ulp_id" id="selUlpAnalytics" class="form-select form-select-sm rounded-pill d-inline-block" style="width: auto;" onchange="this.form.submit()">
                    <option value="">Semua ULP</option>
                    <?php foreach ($ulps as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= ((int)$ulpIdFilter === (int)$u['id']) ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <?php if ($ulpIdFilter): ?><input type="hidden" name="ulp_id" value="<?= (int)$ulpIdFilter ?>"><?php endif; ?>
            <?php endif; ?>
            <div class="col-auto">
                <label for="selPenyulangAnalytics" class="form-label small mb-0 me-1 text-muted fw-semibold">Penyulang</label>
                <select name="penyulang_id" id="selPenyulangAnalytics" class="form-select form-select-sm rounded-pill d-inline-block" style="width: auto; max-width: 195px;" onchange="this.form.submit()">
                    <option value="">Semua Penyulang</option>
                    <?php foreach ($penyulangs as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= ((int)$penyulangId === (int)$p['id']) ? 'selected' : '' ?>><?= esc($p['nama_penyulang']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($penyulangId || $ulpIdFilter || $scope !== 'year_a'): ?>
            <div class="col-auto">
                <a href="<?= $baseUrl ?>?year_a=<?= (int)$yearA ?>&year_b=<?= (int)$yearB ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5" title="Reset filter">
                    <i class="fas fa-times me-1"></i>Reset
                </a>
            </div>
            <?php endif; ?>
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
        (Fokus: <strong><?= esc($focusLabel) ?></strong>)
    </div>
    <a href="<?= $baseUrl ?>?year_a=<?= (int)$yearA ?>&year_b=<?= (int)$yearB ?><?= $scopeParam . $ulpParam ?>" class="btn btn-xs btn-outline-primary rounded-pill px-3 py-1 small">
        Tampilkan Semua Penyulang
    </a>
</div>
<?php endif; ?>

<!-- ═════════════════════════════════════════════ ROW 1: ITEMS 1, 2, 3 ══ -->
<div class="row g-3 mb-3 align-items-stretch">
    <!-- ITEM 1: Gangguan Permanen vs Temporer -->
    <div class="col-lg-4 col-md-6 col-12">
        <div class="gg-card h-100 d-flex flex-column">
            <div class="gg-card-header">
                <h6 class="gg-card-title">
                    <span class="gg-num-badge bg-danger-subtle text-danger">1</span>Permanen vs Temporer
                </h6>
                <span class="badge bg-light text-secondary border rounded-pill" style="font-size: 10.5px;"><?= esc($focusLabel) ?></span>
            </div>
            <div class="card-body d-flex flex-column justify-content-between">
                <?php if ($focusCat['total'] > 0): ?>
                    <div style="height: 215px; position: relative;">
                        <canvas id="chartPermTemp" role="img" aria-label="Grafik donat gangguan permanen vs temporer"></canvas>
                    </div>
                    <div class="row g-2 mt-2 text-center" style="font-size: 12px;">
                        <div class="col-6">
                            <div class="p-2 rounded-3 bg-light border gg-cat-drill-card" data-cat-key="TEMPORARY" title="Klik untuk melihat rincian kejadian Temporer">
                                <span class="text-muted d-block" style="font-size: 10.5px;">Temporer</span>
                                <strong class="fs-6 text-dark font-monospace"><?= number_format((int)$focusCat['TEMPORARY'], 0, ',', '.') ?></strong>
                                <span class="text-muted small font-monospace">(<?= number_format((float)$focusCat['temp_pct'], 1, ',', '.') ?>%)</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded-3 bg-danger-subtle border border-danger-subtle gg-cat-drill-card" data-cat-key="PERMANENT" title="Klik untuk melihat rincian kejadian Permanen">
                                <span class="text-danger d-block" style="font-size: 10.5px;">Permanen</span>
                                <strong class="fs-6 text-danger font-monospace"><?= number_format((int)$focusCat['PERMANENT'], 0, ',', '.') ?></strong>
                                <span class="text-danger small font-monospace">(<?= number_format((float)$focusCat['perm_pct'], 1, ',', '.') ?>%)</span>
                            </div>
                        </div>
                        <?php if (!empty($focusCat['UNKNOWN'])): ?>
                        <div class="col-12">
                            <div class="p-1.5 rounded-3 bg-secondary-subtle border gg-cat-drill-card" data-cat-key="UNKNOWN" title="Klik untuk melihat rincian kejadian Tidak Tercatat">
                                <span class="text-muted small">Tidak Tercatat: </span>
                                <strong class="text-dark font-monospace"><?= number_format((int)$focusCat['UNKNOWN'], 0, ',', '.') ?></strong>
                                <span class="text-muted small font-monospace">(<?= number_format((float)($focusCat['unk_pct'] ?? 0), 1, ',', '.') ?>%)</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5 small"><i class="fas fa-circle-info me-1"></i>Belum ada data kategori pada filter ini.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ITEM 2: Gangguan per ULP -->
    <div class="col-lg-4 col-md-6 col-12">
        <div class="gg-card h-100 d-flex flex-column">
            <div class="gg-card-header">
                <h6 class="gg-card-title">
                    <span class="gg-num-badge bg-warning-subtle text-warning-emphasis">2</span>Gangguan per ULP
                </h6>
                <span class="badge bg-light text-secondary border rounded-pill" style="font-size: 10.5px;"><?= esc($focusLabel) ?> vs <?= (int)$yearB ?></span>
            </div>
            <div class="card-body d-flex flex-column justify-content-between">
                <?php if (!empty($item2['rows'])): ?>
                    <div style="height: 205px; position: relative;">
                        <canvas id="chartUlp"></canvas>
                    </div>
                    <div class="d-flex flex-wrap gap-2 justify-content-center mt-2" style="font-size: 11px;">
                        <?php foreach ($item2['rows'] as $ur): ?>
                            <span class="badge bg-light text-dark border rounded-pill px-2.5 py-1">
                                <?= esc($ur['ulp']) ?>: <strong class="text-danger"><?= number_format($ur['focus']) ?></strong>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5 small"><i class="fas fa-circle-info me-1"></i>Belum ada data ULP pada filter ini.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ITEM 3: Total Gangguan (Scorecard + YoY Parity) -->
    <?php
        $selisihLabel = $selisih > 0 ? '+' . number_format($selisih) : number_format($selisih);
        $selisihColor = $selisih > 0 ? 'danger' : ($selisih < 0 ? 'success' : 'secondary');
        $pctLabel     = $pctChange !== null ? ($pctChange > 0 ? '+' . $pctChange . '%' : $pctChange . '%') : 'N/A';
        $pctColor     = ($pctChange !== null && $pctChange > 0) ? 'danger' : (($pctChange !== null && $pctChange < 0) ? 'success' : 'secondary');
    ?>
    <div class="col-lg-4 col-12">
        <div class="gg-card h-100 d-flex flex-column">
            <div class="gg-card-header">
                <h6 class="gg-card-title">
                    <span class="gg-num-badge bg-primary-subtle text-primary">3</span>Total Gangguan
                </h6>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" style="font-size: 10.5px;"><?= esc($focusLabel) ?></span>
            </div>
            <div class="card-body d-flex flex-column justify-content-between gap-2">
                <div class="p-3 rounded-3 bg-light border text-center">
                    <div class="text-muted small fw-semibold mb-1">Total Gangguan (<?= esc($focusLabel) ?>)</div>
                    <div class="display-5 fw-bold text-dark font-monospace mb-0"><?= number_format($focusTotal) ?></div>
                    <div class="text-muted" style="font-size: 11px;">
                        Gabungan <?= (int)$yearA ?> &amp; <?= (int)$yearB ?>: <strong><?= number_format($combinedTot) ?></strong> kejadian
                    </div>
                </div>
                <div class="row g-2" style="font-size: 12px;">
                    <div class="col-6">
                        <div class="p-2 rounded-3 border border-start border-4 border-primary h-100">
                            <div class="text-muted" style="font-size: 10.5px;">Tahun <?= (int)$yearA ?></div>
                            <div class="fw-bold fs-5 text-dark font-monospace"><?= number_format($fullYearATot) ?></div>
                            <div class="text-muted" style="font-size: 10px;"><?= esc($periodInfo['yearA_period'] ?? '') ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded-3 border border-start border-4 border-secondary h-100">
                            <div class="text-muted" style="font-size: 10.5px;">Tahun <?= (int)$yearB ?></div>
                            <div class="fw-bold fs-5 text-dark font-monospace"><?= number_format($fullYearBTot) ?></div>
                            <div class="text-muted" style="font-size: 10px;"><?= esc($periodInfo['yearB_period'] ?? '') ?></div>
                        </div>
                    </div>
                </div>
                <div class="p-2 rounded-3 border bg-<?= $pctColor ?>-subtle border-<?= $pctColor ?>-subtle d-flex justify-content-between align-items-center" style="font-size: 11.5px;">
                    <span class="text-muted">YoY Setara (<?= esc($periodInfo['comparable_period'] ?? 'YTD') ?>): <strong><?= number_format($totalA) ?> vs <?= number_format($totalB) ?></strong></span>
                    <span class="fw-bold text-<?= $pctColor ?> font-monospace"><?= $selisihLabel ?> (<?= $pctLabel ?>)</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═════════════════════════════════════════════ ROW 2: ITEMS 4, 5, 6 ══ -->
<div class="row g-3 mb-3 align-items-stretch">
    <!-- ITEM 4: Gangguan Permanen vs Temporer per ULP -->
    <div class="col-lg-4 col-md-6 col-12">
        <div class="gg-card h-100 d-flex flex-column">
            <div class="gg-card-header">
                <h6 class="gg-card-title">
                    <span class="gg-num-badge bg-danger-subtle text-danger">4</span>Permanen vs Temporer per ULP
                </h6>
                <span class="badge bg-light text-secondary border rounded-pill" style="font-size: 10.5px;"><?= esc($focusLabel) ?></span>
            </div>
            <div class="card-body">
                <?php if (!empty($item4['labels'])): ?>
                    <div style="height: 225px; position: relative;">
                        <canvas id="chartPermTempUlp"></canvas>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5 small">Belum ada data ULP vs kategori.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ITEM 5: Relai yang Bekerja -->
    <div class="col-lg-4 col-md-6 col-12">
        <div class="gg-card h-100 d-flex flex-column">
            <div class="gg-card-header">
                <div>
                    <h6 class="gg-card-title">
                        <span class="gg-num-badge bg-success-subtle text-success">5</span>Relai yang Bekerja
                    </h6>
                    <small class="text-muted" style="font-size: 10.5px;">Klik batang untuk rincian relai</small>
                </div>
                <span class="badge bg-light text-secondary border rounded-pill" style="font-size: 10.5px;"><?= esc($focusLabel) ?></span>
            </div>
            <div class="card-body">
                <?php if (!empty($item5['labels'])): ?>
                    <div style="height: 235px; position: relative;">
                        <canvas id="chartRelai" role="img" aria-label="Grafik relai proteksi yang bekerja"></canvas>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5 small">Belum ada data relai kerja.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ITEM 6: Rasio Gangguan PMT vs REC/PMCB -->
    <div class="col-lg-4 col-12">
        <div class="gg-card h-100 d-flex flex-column">
            <div class="gg-card-header">
                <h6 class="gg-card-title">
                    <span class="gg-num-badge bg-info-subtle text-info-emphasis">6</span>Rasio PMT vs REC/PMCB
                </h6>
                <span class="badge bg-light text-secondary border rounded-pill" style="font-size: 10.5px;"><?= esc($focusLabel) ?></span>
            </div>
            <div class="card-body d-flex flex-column justify-content-between">
                <?php if (($item6['focus_total'] ?? 0) > 0): ?>
                    <div style="height: 210px; position: relative;">
                        <canvas id="chartPmtRecl" role="img" aria-label="Grafik donat rasio gangguan PMT vs REC/PMCB"></canvas>
                    </div>
                    <div class="d-flex flex-column gap-1.5 mt-2" style="font-size: 12px;">
                        <?php foreach ($item6['rows'] as $dr): ?>
                        <div class="d-flex justify-content-between align-items-center px-2.5 py-1.5 rounded-2 bg-light border gg-sw-drill-row" data-sw-key="<?= esc($dr['type']) ?>" title="Klik untuk melihat rincian peralatan <?= esc($dr['label']) ?>">
                            <span class="fw-semibold text-dark"><?= esc($dr['label']) ?></span>
                            <span class="font-monospace">
                                <strong><?= number_format((int)$dr['focus'], 0, ',', '.') ?></strong>
                                <span class="text-muted">(<?= number_format((float)$dr['focus_pct'], 1, ',', '.') ?>%)</span>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5 small">Belum ada data rasio PMT vs REC/PMCB.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═════════════════════════════════════════════ Monthly Trend Context ══ -->
<?php
$sumA = array_sum($monthlyA ?? []);
$sumB = array_sum($monthlyB ?? []);
$hasChartData = ($sumA > 0 || $sumB > 0);
?>
<div class="gg-card mb-3">
    <div class="gg-card-header">
        <h6 class="gg-card-title">
            <i class="fas fa-chart-column text-primary me-2"></i>Distribusi Gangguan per Bulan (<?= (int)$yearA ?> vs <?= (int)$yearB ?>)
            <?php if ($penyulangId && !empty($activePenyulangName)): ?>
                <span class="badge bg-primary-subtle text-primary border ms-2 font-monospace"><?= esc($activePenyulangName) ?></span>
            <?php endif; ?>
        </h6>
        <div class="d-flex gap-3 align-items-center" style="font-size:12px;">
            <span><span class="d-inline-block rounded-1 me-1" style="width:12px;height:12px;background:#0d6efd;"></span><?= (int)$yearA ?> (<?= number_format($fullYearATot) ?>)</span>
            <span><span class="d-inline-block rounded-1 me-1" style="width:12px;height:12px;background:#94a3b8;"></span><?= (int)$yearB ?> (<?= number_format($fullYearBTot) ?>)</span>
        </div>
    </div>
    <div class="card-body" id="chartGangguanContainer">
        <?php if ($hasChartData): ?>
            <div style="height: 240px; position: relative;">
                <canvas id="chartGangguan"></canvas>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted small">Belum ada data gangguan bulanan pada tahun <?= (int)$yearA ?> maupun <?= (int)$yearB ?>.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ═════════════════════════════════════════════ ENS (kWh) Reliability Analysis ══ -->
<?php
$ensData     = $analytics12['ens_analysis'] ?? [];
$ensMoA      = $ensData['monthly_a'] ?? array_fill(0, 12, null);
$ensMoB      = $ensData['monthly_b'] ?? array_fill(0, 12, null);
$ensTotA     = (float)($ensData['total_a'] ?? 0);
$ensTotB     = (float)($ensData['total_b'] ?? 0);
$ensTotFocus = (float)($ensData['total_focus'] ?? 0);
$ensDiff     = (float)($ensData['full_diff'] ?? 0);
$ensPct      = $ensData['full_pct'] ?? null;
$ensCompPct  = $ensData['comparable_pct'] ?? null;
$ensMeta     = $ensData['meta'] ?? [];
$hasEnsData  = ($ensTotA > 0 || $ensTotB > 0);
$ensDiffCls  = $ensDiff > 0 ? 'danger' : ($ensDiff < 0 ? 'success' : 'secondary');
$ensDiffSign = $ensDiff > 0 ? '+' : '';
$ensPctStr   = $ensPct !== null ? ($ensPct > 0 ? '+' . $ensPct . '%' : $ensPct . '%') : 'N/A';
?>
<div class="gg-card mb-3" id="cardEnsGangguan">
    <div class="gg-card-header">
        <div>
            <h6 class="gg-card-title">
                <i class="fas fa-plug-circle-bolt text-warning me-2"></i>Analisis ENS (kWh) per Bulan &amp; Perbandingan Total Tahunan (<?= (int)$yearA ?> vs <?= (int)$yearB ?>)
                <?php if ($penyulangId && !empty($activePenyulangName)): ?>
                    <span class="badge bg-primary-subtle text-primary border ms-2 font-monospace"><?= esc($activePenyulangName) ?></span>
                <?php endif; ?>
            </h6>
            <small class="text-muted" style="font-size: 11px;">
                Sumber kolom <code><?= esc($ensMeta['source_column'] ?? '( Kwh )') ?></code> &bull;
                Rumus: <span class="font-monospace"><?= esc($ensMeta['formula_text'] ?? '√3 × 20 kV × I × 0,85 × jam') ?></span> &bull;
                Fokus aktif: <strong><?= esc($focusLabel) ?></strong> (<?= number_format($ensTotFocus, 2, ',', '.') ?> kWh) &bull;
                Klik batang untuk rincian trip pembentuk ENS
            </small>
        </div>
        <div class="d-flex flex-wrap gap-1.5 align-items-center" style="font-size: 11px;">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 font-monospace">
                <?= (int)$yearA ?>: <?= number_format($ensTotA, 2, ',', '.') ?> kWh
            </span>
            <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-2.5 py-1 font-monospace">
                <?= (int)$yearB ?>: <?= number_format($ensTotB, 2, ',', '.') ?> kWh
            </span>
            <span class="badge bg-<?= $ensDiffCls ?>-subtle text-<?= $ensDiffCls ?> border border-<?= $ensDiffCls ?>-subtle rounded-pill px-2.5 py-1 font-monospace">
                <?= $ensDiffSign . number_format($ensDiff, 2, ',', '.') ?> kWh (<?= $ensPctStr ?>)
            </span>
        </div>
    </div>
    <div class="card-body">
        <?php if ($hasEnsData): ?>
        <div class="row g-3 align-items-stretch">
            <div class="col-lg-8 col-12">
                <div class="border rounded-3 p-2.5 bg-light bg-opacity-50 h-100 d-flex flex-column">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-1 mb-2">
                        <span class="fw-bold text-dark small"><i class="fas fa-chart-bar text-primary me-1"></i>ENS (kWh) per Bulan (<?= (int)$yearA ?> vs <?= (int)$yearB ?>)</span>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="d-lg-none badge bg-white text-secondary border rounded-pill px-2 py-0.5 text-nowrap" style="font-size: 10px;"><i class="fas fa-arrows-left-right me-1" aria-hidden="true"></i>Geser grafik</span>
                            <span class="text-muted font-monospace" style="font-size: 10.5px;">Total bulanan = total tahunan (<?= esc($ensMeta['unit'] ?? 'kWh') ?>)</span>
                        </div>
                    </div>
                    <div class="ens-chart-scroll-wrap">
                        <div class="ens-monthly-canvas-box">
                            <canvas id="chartEnsBulanan" role="img" aria-label="Grafik ENS (kWh) per bulan membandingkan tahun A dan tahun B"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-12">
                <div class="border rounded-3 p-2.5 bg-light bg-opacity-50 h-100 d-flex flex-column justify-content-between gap-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark small"><i class="fas fa-scale-balanced text-warning-emphasis me-1"></i>Total ENS per Tahun</span>
                        <span class="badge bg-light text-secondary border rounded-pill" style="font-size: 10px;"><?= esc($focusLabel) ?></span>
                    </div>
                    <div class="ens-annual-canvas-box">
                        <canvas id="chartEnsTahunan" role="img" aria-label="Grafik perbandingan total ENS per tahun"></canvas>
                    </div>
                    <div class="d-flex justify-content-between align-items-center px-2 py-1.5 rounded-2 bg-white border small font-monospace" style="font-size: 10.5px;">
                        <span class="text-muted">Setara <?= esc($ensData['comparable_period'] ?? 'YTD') ?>:</span>
                        <span class="fw-bold text-dark">
                            <?= number_format((float)($ensData['comparable_a'] ?? 0), 2, ',', '.') ?> vs <?= number_format((float)($ensData['comparable_b'] ?? 0), 2, ',', '.') ?>
                            <?php if ($ensCompPct !== null): ?>
                                (<?= ($ensCompPct > 0 ? '+' : '') . $ensCompPct ?>%)
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif ($syncState === 'ERROR' && !empty($syncError)): ?>
            <div class="alert alert-warning mb-0 py-3 px-3 rounded-3 small d-flex align-items-center justify-content-center gap-2 text-center">
                <i class="fas fa-triangle-exclamation flex-shrink-0"></i>
                <div>Sinkronisasi Google Sheets gagal (<?= esc($syncError) ?>) dan belum ada data ENS (kWh) tersimpan pada tahun <?= (int)$yearA ?> maupun <?= (int)$yearB ?> sesuai filter ini.</div>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted small"><i class="fas fa-circle-info me-1" aria-hidden="true"></i>Belum ada data ENS (kWh) pada tahun <?= (int)$yearA ?> maupun <?= (int)$yearB ?> sesuai filter ini.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ═════════════════════════════════════════════ ITEM 7: PARETO PENYEBAB ══ -->
<div class="gg-card mb-3">
    <div class="gg-card-header">
        <div>
            <h6 class="gg-card-title">
                <span class="gg-num-badge bg-warning-subtle text-warning-emphasis">7</span>Pareto Penyebab Gangguan (<code>PENYEBAB SESUAI KODE GANGGUAN</code>)
            </h6>
            <small class="text-muted" style="font-size: 11px;">Diurutkan menurun beserta kurva &amp; batas Pareto 80% &bull; Klik batang untuk rincian penyebab, klik titik garis untuk urutan kumulatif &bull; Fokus: <?= esc($focusLabel) ?></small>
        </div>
        <div class="d-flex flex-wrap gap-1.5 align-items-center">
            <?php if (!empty($item7['vital_80_count'])): ?>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 font-monospace" style="font-size: 11px;">
                Vital &le;80%: <?= (int)$item7['vital_80_count'] ?> Kode (<?= number_format((float)($item7['vital_80_pct'] ?? 0), 1) ?>%)
            </span>
            <?php endif; ?>
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2.5 py-1 font-monospace" style="font-size: 11px;">
                <?= number_format((int)($item7['total'] ?? 0)) ?> Kejadian &bull; <?= count($item7['items'] ?? []) ?> Kode Penyebab
            </span>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($item7['items'])): ?>
            <div class="gg-chart-scroll-x mb-3">
                <div class="gg-chart-wide-inner" style="height: 320px;">
                    <canvas id="chartParetoPenyebab" role="img" aria-label="Grafik Pareto penyebab gangguan"></canvas>
                </div>
            </div>
            <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0" id="tableParetoPenyebab" style="font-size: 12px;">
                    <thead class="table-light sticky-top" style="font-size: 11px;">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Kode Penyebab (Spreadsheet)</th>
                            <th>Klasifikasi Kanonik SIDAK TEJO</th>
                            <th class="text-end">Jumlah</th>
                            <th class="text-end">Kontribusi</th>
                            <th class="text-end">Kumulatif</th>
                            <th style="width: 120px;">% Kumulatif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($item7['items'] as $idx => $pRow): ?>
                        <tr class="gg-pareto-row <?= !empty($pRow['is_vital_80']) ? 'table-warning-subtle' : '' ?>" data-cause="<?= esc($pRow['cause_raw']) ?>" title="Klik untuk melihat rincian kejadian <?= esc($pRow['cause_raw']) ?>">
                            <td class="text-muted fw-bold"><?= $idx + 1 ?></td>
                            <td class="fw-bold text-dark font-monospace">
                                <?= esc($pRow['cause_raw']) ?>
                                <?php if (!empty($pRow['is_vital_80'])): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1" style="font-size: 9.5px;">80% Vital</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= esc($pRow['category_label']) ?></td>
                            <td class="text-end fw-bold font-monospace"><?= number_format($pRow['count']) ?></td>
                            <td class="text-end font-monospace"><?= number_format($pRow['pct'], 1) ?>%</td>
                            <td class="text-end font-monospace text-muted"><?= number_format($pRow['cumulative_count']) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-1.5">
                                    <div class="progress flex-grow-1" style="height: 6px;">
                                        <div class="progress-bar bg-danger" style="width: <?= min(100, (float)$pRow['cumulative_pct']) ?>%;"></div>
                                    </div>
                                    <span class="font-monospace small fw-semibold" style="min-width: 42px;"><?= number_format($pRow['cumulative_pct'], 1) ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold" style="font-size: 11.5px;">
                        <tr>
                            <td colspan="3">Total Rekonsiliasi (<?= (int)($item7['category_count'] ?? count($item7['items'])) ?> Kategori)</td>
                            <td class="text-end font-monospace"><?= number_format((int)($item7['total'] ?? 0)) ?></td>
                            <td class="text-end font-monospace"><?= number_format((float)($item7['sum_pct'] ?? 100), 1) ?>%</td>
                            <td class="text-end font-monospace"><?= number_format((int)($item7['total'] ?? 0)) ?></td>
                            <td class="text-end font-monospace"><?= number_format((float)($item7['final_cumulative_pct'] ?? 100), 1) ?>%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted small">Belum ada data penyebab gangguan untuk filter ini.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ═════════════════════════════════════════════ ITEM 8: TOP 10 PENYULANG ══ -->
<div class="gg-card mb-3">
    <div class="gg-card-header">
        <div>
            <h6 class="gg-card-title">
                <span class="gg-num-badge bg-danger-subtle text-danger">8</span>Top 10 Gangguan Penyulang
            </h6>
            <small class="text-muted" style="font-size: 11px;">10 penyulang frekuensi trip tertinggi (<?= esc($scope === 'year_b' ? "Tahun {$yearB}" : ($scope === 'combined' ? "Gabungan {$yearA} & {$yearB}" : "Tahun {$yearA}")) ?>) beserta pembanding <?= (int)$yearB ?></small>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($top10Merged)): ?>
            <div style="height: 230px; position: relative;" class="mb-3">
                <canvas id="chartTop10Penyulang"></canvas>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-light text-muted fw-semibold">
                        <tr>
                            <th class="ps-3" style="width:40px;">#</th>
                            <th>Penyulang</th>
                            <th>ULP</th>
                            <th class="text-center" style="width:110px;">Gangguan <?= (int)$yearA ?></th>
                            <th class="text-center" style="width:110px;">Gangguan <?= (int)$yearB ?></th>
                            <th class="text-center" style="width:100px;">Selisih</th>
                            <th class="text-center" style="width:140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $cntAValues = array_column($top10Merged, 'cnt_a');
                        $maxCntA    = !empty($cntAValues) ? max(1, max($cntAValues)) : 1;
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
                                    <div style="height:4px;width:<?= $barW ?>%;background:#be185d;border-radius:3px;"></div>
                                </div>
                            </td>
                            <td class="text-muted"><?= esc($row['nama_ulp'] ?: '—') ?></td>
                            <td class="text-center fw-bold text-primary font-monospace"><?= number_format((int)$row['cnt_a']) ?></td>
                            <td class="text-center text-muted font-monospace"><?= number_format((int)$row['cnt_b']) ?></td>
                            <td class="text-center fw-semibold font-monospace <?= $diffCls ?>"><?= $diffLabel ?></td>
                            <td class="text-center">
                                <?php if (!empty($row['penyulang_id'])): ?>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= $baseUrl ?>?year_a=<?= (int)$yearA ?>&year_b=<?= (int)$yearB ?><?= $scopeParam . $ulpParam ?>&penyulang_id=<?= (int)$row['penyulang_id'] ?>"
                                       class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-0 me-1" style="font-size:11px;">
                                        <i class="fas fa-chart-line me-1"></i>Fokus
                                    </a>
                                    <a href="<?= site_url('temuan?penyulang_id=' . (int)$row['penyulang_id']) ?>"
                                       class="btn btn-xs btn-outline-primary rounded-pill px-2 py-0" style="font-size:11px;">
                                        <i class="fas fa-list-check me-1"></i>Temuan
                                    </a>
                                </div>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted small">Tidak ada data top penyulang pada periode terpilih.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ═════════════════════════════════════════════ ITEM 9: SEBARAN PMT & RECLOSER ══ -->
<div class="gg-card mb-3">
    <div class="gg-card-header">
        <div>
            <h6 class="gg-card-title">
                <span class="gg-num-badge bg-success-subtle text-success">9</span>Sebaran Gangguan PMT dan Recloser
            </h6>
            <small class="text-muted" style="font-size: 11px;">Distribusi frekuensi trip berdasarkan peralatan switching (<code>RECLOSER</code>) &bull; Fokus: <?= esc($focusLabel) ?></small>
        </div>
        <div class="btn-group btn-group-sm rounded-pill bg-light p-0.5 border" role="group">
            <button type="button" class="btn btn-xs btn-success rounded-pill px-2.5 py-0.5 fw-semibold" id="btnDevNonPmt" onclick="switchDeviceDistMode('non_pmt', this)">Recloser / PMCB / Key Point</button>
            <button type="button" class="btn btn-xs btn-light text-dark rounded-pill px-2.5 py-0.5 fw-semibold" id="btnDevAll" onclick="switchDeviceDistMode('all', this)">Semua (termasuk PMT)</button>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($item9['all_devices'])): ?>
            <div class="gg-chart-scroll-x">
                <div class="gg-chart-wide-inner" style="height: 280px;">
                    <canvas id="chartSebaranPeralatan" role="img" aria-label="Grafik sebaran gangguan per peralatan switching"></canvas>
                </div>
            </div>
            <div class="text-muted mt-2" style="font-size: 11px;">
                Menampilkan hingga 25 peralatan dengan frekuensi tertinggi. Klik batang peralatan untuk melihat detail kejadian. Gunakan tombol di kanan atas untuk menyertakan atau menyembunyikan PMT GI.
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted small">Belum ada data sebaran peralatan pada filter ini.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ═════════════════════════════════════════════ ITEM 10: DETAIL PENYULANG PER BULAN ══ -->
<?php
$p10Years = $item10['years'] ?? [$yearA, $yearB];
$p10Rows  = $item10['rows'] ?? [];
$dualYr10 = count($p10Years) > 1;
?>
<div class="gg-card mb-3">
    <div class="gg-card-header">
        <div>
            <h6 class="gg-card-title">
                <span class="gg-num-badge bg-warning-subtle text-warning-emphasis">10</span>Detail Gangguan Penyulang per Bulan
            </h6>
            <small class="text-muted" style="font-size: 11px;">Matriks <code>PENYULANG &times; ULP &times; RECLOSER</code> per bulan (<?= implode(' vs ', $p10Years) ?>) &bull; <?= count($p10Rows) ?> kombinasi</small>
        </div>
        <input type="text" class="form-control form-control-sm rounded-pill" style="max-width: 240px;" placeholder="Cari penyulang / ULP / recloser..." oninput="filterPivotRows('tableItem10', this.value)">
    </div>
    <div class="gg-pivot-wrap">
        <?php if (!empty($p10Rows)): ?>
        <table class="gg-pivot-table gg-pivot-amber" id="tableItem10">
            <thead>
                <tr>
                    <th class="gg-sticky-col-1" rowspan="<?= $dualYr10 ? 2 : 1 ?>">Penyulang</th>
                    <th class="gg-sticky-col-2" rowspan="<?= $dualYr10 ? 2 : 1 ?>">ULP</th>
                    <th class="gg-sticky-col-3" rowspan="<?= $dualYr10 ? 2 : 1 ?>">Recloser / Alat</th>
                    <?php foreach ($monthLabels as $mLbl): ?>
                        <th class="text-center" colspan="<?= $dualYr10 ? 2 : 1 ?>"><?= $mLbl ?></th>
                    <?php endforeach; ?>
                    <th class="text-end" colspan="<?= $dualYr10 ? 3 : 1 ?>">Total</th>
                </tr>
                <?php if ($dualYr10): ?>
                <tr>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <th class="text-end"><?= (int)$p10Years[0] ?></th>
                        <th class="text-end"><?= (int)$p10Years[1] ?></th>
                    <?php endfor; ?>
                    <th class="text-end"><?= (int)$p10Years[0] ?></th>
                    <th class="text-end"><?= (int)$p10Years[1] ?></th>
                    <th class="text-end">Total</th>
                </tr>
                <?php endif; ?>
            </thead>
            <tbody>
                <?php foreach ($p10Rows as $r): ?>
                <tr>
                    <td class="gg-sticky-col-1 fw-semibold text-dark"><?= esc($r['feeder_name']) ?></td>
                    <td class="gg-sticky-col-2 text-muted"><?= esc($r['ulp_name']) ?></td>
                    <td class="gg-sticky-col-3 font-monospace"><?= esc($r['device_name']) ?></td>
                    <?php for ($m = 1; $m <= 12; $m++):
                        $va = (int)($r['months_a'][$m] ?? 0);
                        $vb = (int)($r['months_b'][$m] ?? 0);
                    ?>
                        <td class="text-end font-monospace <?= $va === 0 ? 'gg-zero' : 'fw-semibold' ?>"><?= $va ?: '–' ?></td>
                        <?php if ($dualYr10): ?>
                        <td class="text-end font-monospace <?= $vb === 0 ? 'gg-zero' : '' ?>"><?= $vb ?: '–' ?></td>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <td class="text-end font-monospace fw-bold text-primary"><?= number_format((int)$r['total_a']) ?></td>
                    <?php if ($dualYr10): ?>
                    <td class="text-end font-monospace fw-semibold"><?= number_format((int)$r['total_b']) ?></td>
                    <td class="text-end font-monospace fw-bold text-dark"><?= number_format((int)$r['total']) ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td class="gg-sticky-col-1" colspan="3">Total Keseluruhan</td>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <td class="text-end font-monospace"><?= number_format((int)($item10['month_totals_a'][$m] ?? 0)) ?></td>
                        <?php if ($dualYr10): ?>
                        <td class="text-end font-monospace"><?= number_format((int)($item10['month_totals_b'][$m] ?? 0)) ?></td>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <td class="text-end font-monospace"><?= number_format((int)($item10['grand_total_a'] ?? 0)) ?></td>
                    <?php if ($dualYr10): ?>
                    <td class="text-end font-monospace"><?= number_format((int)($item10['grand_total_b'] ?? 0)) ?></td>
                    <td class="text-end font-monospace"><?= number_format((int)($item10['grand_total_combined'] ?? 0)) ?></td>
                    <?php endif; ?>
                </tr>
            </tfoot>
        </table>
        <?php else: ?>
            <div class="text-center py-4 text-muted small">Tidak ada data detail penyulang per bulan.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ═════════════════════════════════════════════ ITEM 11: PENYULANG VS PENYEBAB ══ -->
<?php
$p11Causes = $item11['causes'] ?? [];
$p11Rows   = $item11['rows'] ?? [];
?>
<div class="gg-card mb-3">
    <div class="gg-card-header">
        <div>
            <h6 class="gg-card-title">
                <span class="gg-num-badge bg-primary-subtle text-primary">11</span>Gangguan Penyulang vs Penyebab Gangguan
            </h6>
            <small class="text-muted" style="font-size: 11px;">Tabulasi silang <code>PENYULANG &times; ULP &times; RECLOSER</code> terhadap <code>PENYEBAB SESUAI KODE GANGGUAN</code> &bull; Fokus: <?= esc($focusLabel) ?></small>
        </div>
        <input type="text" class="form-control form-control-sm rounded-pill" style="max-width: 240px;" placeholder="Cari penyulang / ULP / recloser..." oninput="filterPivotRows('tableItem11', this.value)">
    </div>
    <div class="gg-pivot-wrap">
        <?php if (!empty($p11Rows)): ?>
        <table class="gg-pivot-table gg-pivot-blue" id="tableItem11">
            <thead>
                <tr>
                    <th class="gg-sticky-col-1">Penyulang</th>
                    <th class="gg-sticky-col-2">ULP</th>
                    <th class="gg-sticky-col-3">Recloser / Alat</th>
                    <?php foreach ($p11Causes as $cLabel): ?>
                        <th class="text-end"><?= esc($cLabel) ?></th>
                    <?php endforeach; ?>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($p11Rows as $r): ?>
                <tr>
                    <td class="gg-sticky-col-1 fw-semibold text-dark"><?= esc($r['feeder_name']) ?></td>
                    <td class="gg-sticky-col-2 text-muted"><?= esc($r['ulp_name']) ?></td>
                    <td class="gg-sticky-col-3 font-monospace"><?= esc($r['device_name']) ?></td>
                    <?php foreach ($p11Causes as $cLabel):
                        $vc = (int)($r['causes'][$cLabel] ?? 0);
                    ?>
                        <td class="text-end font-monospace <?= $vc === 0 ? 'gg-zero' : 'fw-semibold' ?>"><?= $vc ?: '–' ?></td>
                    <?php endforeach; ?>
                    <td class="text-end font-monospace fw-bold text-primary"><?= number_format((int)$r['total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td class="gg-sticky-col-1" colspan="3">Total Keseluruhan</td>
                    <?php foreach ($p11Causes as $cLabel): ?>
                        <td class="text-end font-monospace"><?= number_format((int)($item11['cause_totals'][$cLabel] ?? 0)) ?></td>
                    <?php endforeach; ?>
                    <td class="text-end font-monospace"><?= number_format((int)($item11['grand_total'] ?? 0)) ?></td>
                </tr>
            </tfoot>
        </table>
        <?php else: ?>
            <div class="text-center py-4 text-muted small">Tidak ada data penyulang vs penyebab pada filter ini.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ═════════════════════════════════════════════ ITEM 12: DETAIL BULANAN PERALATAN ══ -->
<?php
$p12Families = $item12['family_summary'] ?? [];
$p12Rows     = $item12['rows'] ?? [];
?>
<div class="gg-card mb-4">
    <div class="gg-card-header">
        <div>
            <h6 class="gg-card-title">
                <span class="gg-num-badge bg-success-subtle text-success">12</span>Detail Bulanan Gangguan PMT, Recloser, PMCB, dan Key Point
            </h6>
            <small class="text-muted" style="font-size: 11px;">Rekap bulanan berdasarkan famili peralatan (<code>PMT</code>, <code>Recloser</code>, <code>PMCB</code>, <code>Key Point</code>) dan titik proteksi &bull; Fokus: <?= esc($focusLabel) ?></small>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-xs btn-success active" onclick="filterItem12Family('ALL', this)">Semua</button>
                <button type="button" class="btn btn-xs btn-outline-success" onclick="filterItem12Family('PMT', this)">PMT</button>
                <button type="button" class="btn btn-xs btn-outline-success" onclick="filterItem12Family('Recloser', this)">Recloser</button>
                <button type="button" class="btn btn-xs btn-outline-success" onclick="filterItem12Family('PMCB', this)">PMCB</button>
                <button type="button" class="btn btn-xs btn-outline-success" onclick="filterItem12Family('Key Point', this)">Key Point</button>
            </div>
            <input type="text" id="searchItem12" class="form-control form-control-sm rounded-pill" style="max-width: 210px;" placeholder="Cari peralatan / ULP..." oninput="applyItem12Filters()">
        </div>
    </div>

    <?php if (!empty($p12Rows)): ?>
    <!-- Family summary sub-table -->
    <div class="table-responsive border-bottom bg-light-subtle px-2 py-2">
        <table class="table table-sm table-bordered align-middle mb-0 bg-white" style="font-size: 11.5px;">
            <thead class="table-success">
                <tr>
                    <th>Ringkasan Kategori Peralatan</th>
                    <?php foreach ($monthLabels as $mLbl): ?>
                        <th class="text-end"><?= $mLbl ?></th>
                    <?php endforeach; ?>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($p12Families as $fam): ?>
                <tr>
                    <td class="fw-bold text-dark"><?= esc($fam['family']) ?></td>
                    <?php for ($m = 1; $m <= 12; $m++):
                        $vm = (int)($fam['months'][$m] ?? 0);
                    ?>
                        <td class="text-end font-monospace <?= $vm === 0 ? 'gg-zero' : '' ?>"><?= $vm ?: '–' ?></td>
                    <?php endfor; ?>
                    <td class="text-end font-monospace fw-bold text-success"><?= number_format((int)$fam['total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Detailed Device x ULP x Month pivot -->
    <div class="gg-pivot-wrap">
        <table class="gg-pivot-table gg-pivot-green" id="tableItem12">
            <thead>
                <tr>
                    <th class="gg-sticky-col-1">Tipe Alat</th>
                    <th class="gg-sticky-col-2">Recloser / PMCB / Key Point</th>
                    <th class="gg-sticky-col-3">ULP</th>
                    <?php foreach ($monthLabels as $mLbl): ?>
                        <th class="text-end"><?= $mLbl ?></th>
                    <?php endforeach; ?>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($p12Rows as $r): ?>
                <tr data-family="<?= esc($r['equipment_family']) ?>">
                    <td class="gg-sticky-col-1 fw-bold"><span class="badge bg-success-subtle text-success border border-success-subtle"><?= esc($r['equipment_family']) ?></span></td>
                    <td class="gg-sticky-col-2 fw-semibold font-monospace text-dark"><?= esc($r['device_name']) ?></td>
                    <td class="gg-sticky-col-3 text-muted"><?= esc($r['ulp_name']) ?></td>
                    <?php for ($m = 1; $m <= 12; $m++):
                        $vm = (int)($r['months'][$m] ?? 0);
                    ?>
                        <td class="text-end font-monospace <?= $vm === 0 ? 'gg-zero' : 'fw-semibold' ?>"><?= $vm ?: '–' ?></td>
                    <?php endfor; ?>
                    <td class="text-end font-monospace fw-bold text-success"><?= number_format((int)$r['total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td class="gg-sticky-col-1" colspan="3">Total Keseluruhan</td>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <td class="text-end font-monospace"><?= number_format((int)($item12['month_totals'][$m] ?? 0)) ?></td>
                    <?php endfor; ?>
                    <td class="text-end font-monospace"><?= number_format((int)($item12['grand_total'] ?? 0)) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php else: ?>
        <div class="card-body text-center py-4 text-muted small">Tidak ada data bulanan peralatan proteksi untuk filter ini.</div>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════ Histori Gangguan (tabel rinci) ══ -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-transparent border-0 py-2 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fas fa-table-list text-primary me-2"></i>Histori Kejadian Gangguan (Log Rinci)
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
                <input type="hidden" name="year_a" value="<?= (int)$yearA ?>">
                <input type="hidden" name="year_b" value="<?= (int)$yearB ?>">
                <input type="hidden" name="scope" value="<?= esc($scope) ?>">
                <?php if ($ulpIdFilter && !in_array(strtolower((string)session()->get('user_role')), ['administrator', 'admin', 'admin_pusat', 'supervisor_up3'])): ?>
                <input type="hidden" name="ulp_id" value="<?= (int)$ulpIdFilter ?>">
                <?php endif; ?>

                <div class="col-lg-2 col-md-3 col-sm-6">
                    <select name="ulp_id" id="filter_ulp" class="form-select form-select-sm rounded-3">
                        <option value="">Semua ULP</option>
                        <?php foreach ($ulps as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= ((int)$ulpIdFilter === (int)$u['id']) ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-sm-6">
                    <select name="penyulang_id" id="filter_penyulang" class="form-select form-select-sm rounded-3">
                        <option value="">Semua Penyulang</option>
                        <?php foreach ($penyulangs as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= ((int)$penyulangId === (int)$p['id']) ? 'selected' : '' ?>><?= esc($p['nama_penyulang']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-2 col-sm-4">
                    <select name="year" class="form-select form-select-sm rounded-3">
                        <option value="">Semua Tahun</option>
                        <?php foreach ($availableYears as $yr): ?>
                            <option value="<?= (int)$yr ?>" <?= ((int)$yearFilter === (int)$yr) ? 'selected' : '' ?>>Tahun <?= (int)$yr ?></option>
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
                    <a href="<?= $baseUrl ?>?year_a=<?= (int)$yearA ?>&year_b=<?= (int)$yearB ?><?= $scopeParam . $ulpParam ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Reset Filter">
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
        <div class="table-responsive gg-log-table-wrap">
            <table class="table table-hover align-middle mb-0 gg-log-table" id="tableLogRinci" style="font-size: 13px;">
                <thead class="table-light text-muted fw-semibold">
                    <tr>
                        <th class="ps-3" style="width: 50px;">No</th>
                        <th>Waktu Trip</th>
                        <th>Penyulang &amp; ULP</th>
                        <th>Alat Hubung / Recloser</th>
                        <th class="text-center">Relay &amp; Arus</th>
                        <th class="text-center">Kategori</th>
                        <th>Kode Penyebab &amp; Narasi</th>
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
                                        $relay = strtoupper((string)($row['relay_trip_type'] ?: 'TIDAK TERCATAT'));
                                        $badgeCls = str_contains($relay, 'DGR') ? 'bg-warning-subtle text-warning border-warning' : (str_contains($relay, 'OCR') ? 'bg-danger-subtle text-danger border-danger' : 'bg-secondary-subtle text-secondary');
                                    ?>
                                    <span class="badge <?= $badgeCls ?> border px-2 py-1"><?= esc($relay) ?></span>
                                    <?php if (!empty($row['fault_current_amperes'])): ?>
                                        <div class="font-monospace text-muted" style="font-size: 11px;"><?= number_format((float)$row['fault_current_amperes']) ?> A</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (strtoupper((string)$row['interruption_category']) === 'PERMANENT'): ?>
                                        <span class="badge bg-danger rounded-pill px-2">PERMANEN</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-dark border rounded-pill px-2">TEMPORER</span>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width: 260px;">
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle mb-1" style="font-size: 10px;"><?= esc($row['cause_raw'] ?? '-') ?></span>
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
                                    <?= !empty($row['outage_duration_minutes']) ? number_format((float)$row['outage_duration_minutes'], 1) . ' m' : '—' ?>
                                </td>
                                <td class="text-end font-monospace fw-semibold text-info">
                                    <?= !empty($row['energy_not_supplied_kwh']) ? number_format((float)$row['energy_not_supplied_kwh'], 1) : '—' ?>
                                </td>
                                <?php if ($canManage): ?>
                                <td class="text-center pe-3">
                                    <form method="POST" action="<?= site_url('gangguan/delete/' . (int)$row['id']) ?>" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data gangguan ini?');" style="display: inline;">
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

<!-- ═══════════════════════════════════════════════════ Modal Input & Impor XLSX ══ -->
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
                                    <option value="<?= (int)$u['id'] ?>"><?= esc($u['nama_ulp']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Penyulang <span class="text-danger">*</span></label>
                            <select name="penyulang_id" id="modal_penyulang_id" class="form-select rounded-3" required>
                                <option value="">— Pilih ULP Terlebih Dahulu —</option>
                                <?php foreach ($penyulangs as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>" data-ulp="<?= (int)($p['ulp_id'] ?? 0) ?>"><?= esc($p['nama_penyulang']) ?></option>
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
                                <option value="OCR-INST">OCR-INST</option>
                                <option value="OCR-DGR">OCR-DGR (Kombinasi)</option>
                                <option value="EF">EF</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">Kategori Gangguan</label>
                            <select name="interruption_category" class="form-select rounded-3">
                                <option value="TEMPORARY">TEMPORARY (Temporer)</option>
                                <option value="PERMANENT">PERMANENT (Permanen)</option>
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
                            <input type="text" name="cause_raw" class="form-control rounded-3" placeholder="Contoh: BINATANG, ROW, MATERIAL, PETIR, PIHAK KE 3, dsb.">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-dark">Narasi / Indikasi Penyebab di Lapangan</label>
                            <textarea name="field_narrative_raw" class="form-control rounded-3" rows="2"></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Tindakan Penormalan</label>
                            <input type="text" name="restoration_action_raw" class="form-control rounded-3">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Zona / Section</label>
                            <input type="text" name="extracted_zone_section" class="form-control rounded-3">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4"><i class="fas fa-save me-1"></i> Simpan Gangguan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Impor XLSX -->
<div class="modal fade" id="modalImportExcel" tabindex="-1" aria-labelledby="modalImportExcelLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-success-subtle text-success rounded-3"><i class="fas fa-file-excel fs-5"></i></div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalImportExcelLabel">Impor Data Gangguan Penyulang (XLSX)</h5>
                        <small class="text-muted">Parser Native PHP &bull; Validasi &amp; Isolasi Keamanan</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-7">
                        <label class="form-label small fw-bold text-dark">Pilih Sumber Berkas XLSX</label>
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check p-2 border rounded-3 bg-light">
                                <input class="form-check-input ms-1 me-2" type="radio" name="import_source" id="src_upload" value="upload" checked onchange="document.getElementById('excel_file_input').disabled = false;">
                                <label class="form-check-label fw-semibold small" for="src_upload">Unggah Berkas XLSX (.xlsx)</label>
                                <input type="file" id="excel_file_input" class="form-control form-control-sm mt-2" accept=".xlsx">
                            </div>
                            <div class="form-check p-2 border rounded-3 bg-light">
                                <input class="form-check-input ms-1 me-2" type="radio" name="import_source" id="src_server" value="server" onchange="document.getElementById('excel_file_input').disabled = true;">
                                <label class="form-check-label fw-semibold small" for="src_server">Gunakan Berkas Kanonik Server</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5 d-flex align-items-end">
                        <button type="button" class="btn btn-primary rounded-pill px-4 w-100" id="btnRunPreview" onclick="runExcelPreview()">
                            <i class="fas fa-magnifying-glass me-1"></i> Periksa Berkas (Dry Run)
                        </button>
                    </div>
                </div>

                <div id="previewResultsContainer" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">Hasil Pratinjau Impor</h6>
                        <span id="previewHeaderBadge" class="badge"></span>
                    </div>
                    <div class="row g-2 mb-3 text-center">
                        <div class="col"><div class="p-2 border rounded-3"><small class="text-muted d-block">Total Baris</small><strong id="statTotalRows">0</strong></div></div>
                        <div class="col"><div class="p-2 border rounded-3"><small class="text-muted d-block">Siap Impor</small><strong id="statReadyImport" class="text-primary">0</strong></div></div>
                        <div class="col"><div class="p-2 border rounded-3"><small class="text-muted d-block">Valid</small><strong id="statValidCount" class="text-success">0</strong></div></div>
                        <div class="col"><div class="p-2 border rounded-3"><small class="text-muted d-block">Peringatan</small><strong id="statWarningCount" class="text-warning">0</strong></div></div>
                        <div class="col"><div class="p-2 border rounded-3"><small class="text-muted d-block">Duplikat</small><strong id="statDuplicateCount" class="text-secondary">0</strong></div></div>
                        <div class="col"><div class="p-2 border rounded-3"><small class="text-muted d-block">Ditolak</small><strong id="statRejectedCount" class="text-danger">0</strong></div></div>
                    </div>
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabSamples" type="button">Sampel Data</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabColumns" type="button">Pemetaan Kolom</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabIssues" type="button">Isu (<span id="badgeIssueCount">0</span>)</button></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tabSamples">
                            <div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Baris</th><th>Tanggal</th><th>ULP</th><th>Penyulang</th><th>Rele</th><th>Kategori</th><th>Durasi</th><th>Keterangan</th></tr></thead><tbody id="previewSamplesTableBody"></tbody></table></div>
                        </div>
                        <div class="tab-pane fade" id="tabColumns">
                            <div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Idx</th><th>Diharapkan</th><th>Terbaca</th><th>Status</th></tr></thead><tbody id="previewColumnsTableBody"></tbody></table></div>
                        </div>
                        <div class="tab-pane fade" id="tabIssues">
                            <div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Baris</th><th>Tipe</th><th>Alasan</th></tr></thead><tbody id="previewIssuesTableBody"></tbody></table></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-3 bg-light d-flex justify-content-between">
                <div><span id="importExecutionStatus" class="small text-muted"></span></div>
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
<?= $this->include('partials/_gangguan_drilldown_modal') ?>
<?= $this->include('partials/_ens_chart_helper') ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function() {
    'use strict';

    function ensureChartLoaded(callback) {
        if (typeof window.Chart !== 'undefined') {
            callback();
            return;
        }
        var localScript = document.createElement('script');
        localScript.src = '<?= base_url('plugins/chart.js') ?>';
        localScript.onload = function() {
            if (typeof window.Chart !== 'undefined') callback();
        };
        document.head.appendChild(localScript);
    }

    var barValueLabelPlugin = {
        id: 'ggBarValueLabels',
        afterDatasetsDraw: function(chart) {
            var c = chart.ctx;
            var isHoriz = (chart.config.options.indexAxis === 'y');
            c.save();
            c.font = '700 10px Inter, sans-serif';
            chart.data.datasets.forEach(function(ds, dsIdx) {
                if (ds.type === 'line' || ds.skipDataLabel) return;
                var meta = chart.getDatasetMeta(dsIdx);
                if (!meta || meta.hidden) return;
                meta.data.forEach(function(elem, idx) {
                    var val = Number(ds.data[idx] || 0);
                    if (val <= 0) return;
                    c.fillStyle = '#0f172a';
                    if (isHoriz) {
                        c.textAlign = 'left';
                        c.textBaseline = 'middle';
                        c.fillText(val.toLocaleString('id-ID'), elem.x + 5, elem.y);
                    } else {
                        c.textAlign = 'center';
                        c.textBaseline = 'bottom';
                        c.fillText(val.toLocaleString('id-ID'), elem.x, elem.y - 3);
                    }
                });
            });
            c.restore();
        }
    };

    var activeFilterParams = {
        year_a: <?= (int)$yearA ?>,
        year_b: <?= (int)$yearB ?>,
        scope: '<?= esc($scope ?? 'year_a') ?>',
        ulp_id: <?= $ulpIdFilter ? (int)$ulpIdFilter : 'null' ?>,
        penyulang_id: <?= $penyulangId ? (int)$penyulangId : 'null' ?>
    };

    function openGgDetail(dimension, key, extra, triggerEl) {
        var params = Object.assign({}, activeFilterParams, extra || {}, { dimension: dimension, key: key });
        if (window.openGangguanDrilldown) {
            window.openGangguanDrilldown({
                endpoint: '<?= site_url('gangguan/drilldown') ?>',
                params: params,
                triggerEl: triggerEl
            });
        }
    }

    function setPointerOnHover(evt, elements) {
        if (evt && evt.native && evt.native.target) {
            evt.native.target.style.cursor = (elements && elements.length) ? 'pointer' : 'default';
        }
    }

    var pareto80ThresholdPlugin = {
        id: 'paretoThreshold80',
        afterDatasetsDraw: function(chart) {
            var y1 = chart.scales && chart.scales.y1;
            var area = chart.chartArea;
            if (!y1 || !area) return;
            var yPx = y1.getPixelForValue(80);
            if (isNaN(yPx)) return;
            var c = chart.ctx;
            c.save();
            c.beginPath();
            c.setLineDash([6, 4]);
            c.strokeStyle = 'rgba(220, 38, 38, 0.78)';
            c.lineWidth = 1.6;
            c.moveTo(area.left, yPx);
            c.lineTo(area.right, yPx);
            c.stroke();

            var labelText = 'Batas Pareto 80%';
            c.font = '700 10px Inter, sans-serif';
            var txtW = c.measureText(labelText).width;
            var boxW = txtW + 12;
            var boxH = 18;
            var boxX = Math.max(area.left + 4, area.right - boxW - 6);
            var boxY = Math.max(area.top + 2, yPx - boxH - 4);
            c.fillStyle = 'rgba(254, 226, 226, 0.96)';
            c.strokeStyle = 'rgba(220, 38, 38, 0.65)';
            c.setLineDash([]);
            c.lineWidth = 1;
            c.fillRect(boxX, boxY, boxW, boxH);
            c.strokeRect(boxX, boxY, boxW, boxH);
            c.fillStyle = '#991b1b';
            c.textAlign = 'left';
            c.textBaseline = 'middle';
            c.fillText(labelText, boxX + 6, boxY + (boxH / 2));
            c.restore();
        }
    };

    var sebaranAllDevices    = <?= json_encode(array_slice($item9['all_devices'] ?? [], 0, 25), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
    var sebaranNonPmtDevices = <?= json_encode(array_slice($item9['non_pmt_devices'] ?? [], 0, 25), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
    var sebaranTotalFocus    = <?= (int)($focusTotal ?? 0) ?>;
    var sebaranChartInstance = null;

    window.switchDeviceDistMode = function(mode, btn) {
        var btnNon = document.getElementById('btnDevNonPmt');
        var btnAll = document.getElementById('btnDevAll');
        [btnNon, btnAll].forEach(function(b) {
            if (!b) return;
            b.classList.remove('btn-success');
            b.classList.add('btn-light', 'text-dark');
        });
        if (btn) {
            btn.classList.remove('btn-light', 'text-dark');
            btn.classList.add('btn-success');
        }
        renderSebaranChart(mode === 'all' ? sebaranAllDevices : sebaranNonPmtDevices);
    };

    function renderSebaranChart(list) {
        var ctx = document.getElementById('chartSebaranPeralatan');
        if (!ctx || typeof Chart === 'undefined') return;
        if (sebaranChartInstance) {
            sebaranChartInstance.destroy();
            sebaranChartInstance = null;
        }
        var maxCount = list.reduce(function(m, d) { return Math.max(m, Number(d.count || 0)); }, 0);
        sebaranChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: list.map(function(d) { return d.device_name; }),
                datasets: [{
                    label: 'Jumlah Gangguan',
                    data: list.map(function(d) { return d.count; }),
                    backgroundColor: list.map(function(d) { return d.equipment_family === 'PMT' ? '#0ea5e9' : '#22c55e'; }),
                    borderRadius: 4
                }]
            },
            plugins: [barValueLabelPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onHover: setPointerOnHover,
                onClick: function(evt, elements, chart) {
                    var hits = elements && elements.length ? elements : chart.getElementsAtEventForMode(evt, 'nearest', { intersect: false }, true);
                    if (!hits || !hits.length) return;
                    var item = list[hits[0].index];
                    if (item) {
                        openGgDetail('device', item.device_name, { sub_key: item.equipment_family }, ctx);
                    }
                },
                layout: { padding: { top: 22 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(tc) {
                                var item = list[tc.dataIndex];
                                if (!item) return '';
                                var pct = sebaranTotalFocus > 0 ? ((item.count / sebaranTotalFocus) * 100).toFixed(1) : '0.0';
                                return ['Porsi Fokus: ' + pct + '%', 'Famili: ' + item.equipment_family + ' | ULP: ' + item.ulp_name, 'Klik batang untuk detail'];
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { autoSkip: false, font: { size: 10 }, maxRotation: 40, minRotation: 25 } },
                    y: { beginAtZero: true, suggestedMax: maxCount > 0 ? Math.ceil(maxCount * 1.18) : 5, ticks: { precision: 0, font: { size: 10 } } }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        ensureChartLoaded(function() {
            <?php
                $activeUlpText = 'Semua ULP';
                if ($ulpIdFilter && !empty($ulps)) {
                    foreach ($ulps as $uRow) {
                        if ((int)$uRow['id'] === (int)$ulpIdFilter) {
                            $activeUlpText = $uRow['nama_ulp'];
                            break;
                        }
                    }
                }
                $activePenyulangText = !empty($activePenyulangName) ? $activePenyulangName : 'Semua Penyulang';
                $ggFilterDesc = $activeUlpText . ' • ' . $activePenyulangText;
                $catA = $item1['year_a'] ?? ['TEMPORARY' => 0, 'PERMANENT' => 0, 'UNKNOWN' => 0];
                $catB = $item1['year_b'] ?? ['TEMPORARY' => 0, 'PERMANENT' => 0, 'UNKNOWN' => 0];
            ?>
            var ggFilterSummary = <?= json_encode($ggFilterDesc) ?>;

            // Item 1: Permanen vs Temporer Donut (direct count + % labels & full tooltip context)
            var ctx1 = document.getElementById('chartPermTemp');
            if (ctx1 && window.renderSidaktejoDoughnutChart) {
                var catLabels1 = ['Temporer', 'Permanen'];
                var catKeys1   = ['TEMPORARY', 'PERMANENT'];
                var catCounts1 = [<?= (int)$focusCat['TEMPORARY'] ?>, <?= (int)$focusCat['PERMANENT'] ?>];
                var catPcts1   = [<?= json_encode((float)$focusCat['temp_pct']) ?>, <?= json_encode((float)$focusCat['perm_pct']) ?>];
                var catYrA1    = [<?= (int)($catA['TEMPORARY'] ?? 0) ?>, <?= (int)($catA['PERMANENT'] ?? 0) ?>];
                var catYrB1    = [<?= (int)($catB['TEMPORARY'] ?? 0) ?>, <?= (int)($catB['PERMANENT'] ?? 0) ?>];
                var catColors1 = ['#64748b', '#ef4444'];
                var catInks1   = ['#334155', '#b91c1c'];
                <?php if (!empty($focusCat['UNKNOWN'])): ?>
                catLabels1.push('Tidak Tercatat');
                catKeys1.push('UNKNOWN');
                catCounts1.push(<?= (int)$focusCat['UNKNOWN'] ?>);
                catPcts1.push(<?= json_encode((float)($focusCat['unk_pct'] ?? 0)) ?>);
                catYrA1.push(<?= (int)($catA['UNKNOWN'] ?? 0) ?>);
                catYrB1.push(<?= (int)($catB['UNKNOWN'] ?? 0) ?>);
                catColors1.push('#94a3b8');
                catInks1.push('#475569');
                <?php endif; ?>

                window.renderSidaktejoDoughnutChart({
                    canvas: ctx1,
                    labels: catLabels1,
                    keys: catKeys1,
                    counts: catCounts1,
                    pcts: catPcts1,
                    total: <?= (int)$focusCat['total'] ?>,
                    colors: catColors1,
                    labelInks: catInks1,
                    yearA: <?= (int)$yearA ?>,
                    yearB: <?= (int)$yearB ?>,
                    yearACounts: catYrA1,
                    yearBCounts: catYrB1,
                    focusLabel: <?= json_encode($focusLabel) ?>,
                    filterSummary: ggFilterSummary,
                    cutout: '56%',
                    onSliceClick: function(k, idx, triggerEl) {
                        if (k) openGgDetail('category', k, {}, triggerEl || ctx1);
                    }
                });
            }
            document.querySelectorAll('.gg-cat-drill-card').forEach(function(card) {
                var k = card.getAttribute('data-cat-key');
                if (k && window.bindAccessibleAction) {
                    window.bindAccessibleAction(card, function() { openGgDetail('category', k, {}, card); });
                }
            });

            // Item 2: Gangguan per ULP
            var ctx2 = document.getElementById('chartUlp');
            if (ctx2) {
                var ulpLabels = <?= json_encode($item2['labels'] ?? []) ?>;
                var ulpFocusCounts = <?= json_encode($item2['focus_counts'] ?? []) ?>;
                var ulpYearBCounts = <?= json_encode($item2['year_b_counts'] ?? []) ?>;
                var maxUlpVal = Math.max.apply(null, [0].concat(ulpFocusCounts, ulpYearBCounts));
                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: ulpLabels,
                        datasets: [
                            {
                                label: '<?= esc($focusLabel) ?>',
                                data: ulpFocusCounts,
                                backgroundColor: '#f97316',
                                borderRadius: 4
                            },
                            {
                                label: 'Tahun <?= (int)$yearB ?>',
                                data: ulpYearBCounts,
                                backgroundColor: '#cbd5e1',
                                borderRadius: 4
                            }
                        ]
                    },
                    plugins: [barValueLabelPlugin],
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        onHover: setPointerOnHover,
                        onClick: function(evt, elements) {
                            if (!elements || !elements.length) return;
                            var hit = elements[0];
                            var ulpName = ulpLabels[hit.index];
                            var extra = (hit.datasetIndex === 1) ? { target_year: <?= (int)$yearB ?> } : {};
                            if (ulpName) openGgDetail('ulp', ulpName, extra, ctx2);
                        },
                        layout: { padding: { right: 38 } },
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10.5 } } },
                            tooltip: { callbacks: { afterBody: function() { return ['Klik batang untuk rincian ULP']; } } }
                        },
                        scales: {
                            x: { beginAtZero: true, suggestedMax: maxUlpVal > 0 ? Math.ceil(maxUlpVal * 1.18) : 5, ticks: { precision: 0, font: { size: 10 } } },
                            y: { grid: { display: false }, ticks: { autoSkip: false, font: { size: 10.5, weight: '600' } } }
                        }
                    }
                });
            }

            // Item 4: Permanen vs Temporer per ULP
            var ctx4 = document.getElementById('chartPermTempUlp');
            if (ctx4) {
                var ulpLabels4 = <?= json_encode($item4['labels'] ?? []) ?>;
                var tempArr4   = <?= json_encode($item4['temporer'] ?? []) ?>;
                var permArr4   = <?= json_encode($item4['permanen'] ?? []) ?>;
                var max4 = Math.max.apply(null, [0].concat(tempArr4, permArr4));
                new Chart(ctx4, {
                    type: 'bar',
                    data: {
                        labels: ulpLabels4,
                        datasets: [
                            { label: 'Temporer', data: tempArr4, backgroundColor: '#64748b', borderRadius: 4 },
                            { label: 'Permanen', data: permArr4, backgroundColor: '#ef4444', borderRadius: 4 }
                        ]
                    },
                    plugins: [barValueLabelPlugin],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        onHover: setPointerOnHover,
                        onClick: function(evt, elements) {
                            if (!elements || !elements.length) return;
                            var hit = elements[0];
                            var u = ulpLabels4[hit.index];
                            var c = hit.datasetIndex === 0 ? 'TEMPORARY' : 'PERMANENT';
                            if (u) openGgDetail('ulp_category', u + '|' + c, {}, ctx4);
                        },
                        layout: { padding: { top: 20 } },
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } },
                            tooltip: { callbacks: { afterBody: function() { return ['Klik batang untuk rincian ULP & kategori']; } } }
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { autoSkip: false, font: { size: 10 } } },
                            y: { beginAtZero: true, suggestedMax: max4 > 0 ? Math.ceil(max4 * 1.18) : 5, ticks: { precision: 0, font: { size: 10 } } }
                        }
                    }
                });
            }

            // Item 5: Relai yang Bekerja (Horizontal bar so labels never clip at bottom)
            var ctx5 = document.getElementById('chartRelai');
            if (ctx5) {
                var relayLabels5 = <?= json_encode($item5['labels'] ?? []) ?>;
                var relayData5   = <?= json_encode($item5['focus_counts'] ?? []) ?>;
                var maxRelay5    = Math.max.apply(null, [0].concat(relayData5));
                new Chart(ctx5, {
                    type: 'bar',
                    data: {
                        labels: relayLabels5,
                        datasets: [{
                            label: '<?= esc($focusLabel) ?>',
                            data: relayData5,
                            backgroundColor: '#16a34a',
                            borderRadius: 4
                        }]
                    },
                    plugins: [barValueLabelPlugin],
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        onHover: setPointerOnHover,
                        onClick: function(evt, elements, chart) {
                            var hits = elements && elements.length ? elements : chart.getElementsAtEventForMode(evt, 'nearest', { intersect: false }, true);
                            if (!hits || !hits.length) return;
                            var lbl = relayLabels5[hits[0].index];
                            if (lbl) openGgDetail('relay', lbl, {}, ctx5);
                        },
                        layout: { padding: { right: 36, top: 4, bottom: 4 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { afterBody: function() { return ['Klik batang untuk daftar trip relai ini']; } } }
                        },
                        scales: {
                            x: { beginAtZero: true, suggestedMax: maxRelay5 > 0 ? Math.ceil(maxRelay5 * 1.18) : 5, ticks: { precision: 0, font: { size: 10 } } },
                            y: { grid: { display: false }, ticks: { autoSkip: false, font: { size: 10.5, weight: '600' } } }
                        }
                    }
                });
            }

            // Item 6: Rasio PMT vs REC/PMCB (direct count + % labels & full tooltip context)
            var ctx6 = document.getElementById('chartPmtRecl');
            if (ctx6 && window.renderSidaktejoDoughnutChart) {
                var swKeys6   = <?= json_encode($item6['keys'] ?? []) ?>;
                var swCounts6 = <?= json_encode($item6['focus_counts'] ?? []) ?>;
                var swPcts6   = <?= json_encode($item6['focus_pcts'] ?? []) ?>;
                var swYrA6    = <?= json_encode($item6['year_a_counts'] ?? []) ?>;
                var swYrB6    = <?= json_encode($item6['year_b_counts'] ?? []) ?>;
                window.renderSidaktejoDoughnutChart({
                    canvas: ctx6,
                    labels: <?= json_encode($item6['labels'] ?? []) ?>,
                    keys: swKeys6,
                    counts: swCounts6,
                    pcts: swPcts6,
                    total: <?= (int)($item6['focus_total'] ?? 0) ?>,
                    colors: ['#0284c7', '#f59e0b', '#64748b'],
                    labelInks: ['#0369a1', '#b45309', '#334155'],
                    yearA: <?= (int)$yearA ?>,
                    yearB: <?= (int)$yearB ?>,
                    yearACounts: swYrA6,
                    yearBCounts: swYrB6,
                    focusLabel: <?= json_encode($focusLabel) ?>,
                    filterSummary: ggFilterSummary,
                    cutout: '55%',
                    onSliceClick: function(k, idx, triggerEl) {
                        if (k) openGgDetail('switching_type', k, {}, triggerEl || ctx6);
                    }
                });
            }
            document.querySelectorAll('.gg-sw-drill-row').forEach(function(rowEl) {
                var k = rowEl.getAttribute('data-sw-key');
                if (k && window.bindAccessibleAction) {
                    window.bindAccessibleAction(rowEl, function() { openGgDetail('switching_type', k, {}, rowEl); });
                }
            });

            // Monthly Bar Chart (Year A vs Year B)
            var ctxMonthly = document.getElementById('chartGangguan');
            if (ctxMonthly) {
                var moA = <?= json_encode(array_values($monthlyA)) ?>;
                var moB = <?= json_encode(array_values($monthlyB)) ?>;
                var maxMo = Math.max.apply(null, [0].concat(moA, moB));
                new Chart(ctxMonthly, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($monthLabels) ?>,
                        datasets: [
                            { label: 'Tahun <?= (int)$yearA ?>', data: moA, backgroundColor: 'rgba(13,110,253,0.82)', borderRadius: 4 },
                            { label: 'Tahun <?= (int)$yearB ?>', data: moB, backgroundColor: 'rgba(148,163,184,0.65)', borderRadius: 4 }
                        ]
                    },
                    plugins: [barValueLabelPlugin],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        onHover: setPointerOnHover,
                        onClick: function(evt, elements) {
                            if (!elements || !elements.length) return;
                            var hit = elements[0];
                            var yr  = hit.datasetIndex === 0 ? <?= (int)$yearA ?> : <?= (int)$yearB ?>;
                            openGgDetail('month', String(hit.index + 1), { target_year: yr }, ctxMonthly);
                        },
                        layout: { padding: { top: 20 } },
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { afterBody: function() { return ['Klik batang bulan untuk lihat rincian kejadian']; } } }
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                            y: { beginAtZero: true, suggestedMax: maxMo > 0 ? Math.ceil(maxMo * 1.18) : 5, ticks: { precision: 0, font: { size: 11 } } }
                        }
                    }
                });
            }

            // ENS (kWh) Monthly Comparison & Annual Total Comparison (shared non-overlapping renderer)
            var ctxEnsMo = document.getElementById('chartEnsBulanan');
            var ctxEnsYr = document.getElementById('chartEnsTahunan');
            var ensMoA   = <?= json_encode($ensMoA ?? []) ?>;
            var ensMoB   = <?= json_encode($ensMoB ?? []) ?>;
            var ggScope  = <?= json_encode($scope ?? 'year_a') ?>;
            if ((ctxEnsMo || ctxEnsYr) && window.renderSidaktejoEnsCharts) {
                window.renderSidaktejoEnsCharts({
                    monthlyCanvas: ctxEnsMo,
                    annualCanvas: ctxEnsYr,
                    monthLabels: <?= json_encode($monthLabels) ?>,
                    yearA: <?= (int)$yearA ?>,
                    yearB: <?= (int)$yearB ?>,
                    scope: ggScope,
                    monthlyA: ensMoA,
                    monthlyB: ensMoB,
                    monthlyCntA: <?= json_encode($ensData['monthly_cnt_a'] ?? array_fill(0, 12, 0)) ?>,
                    monthlyCntB: <?= json_encode($ensData['monthly_cnt_b'] ?? array_fill(0, 12, 0)) ?>,
                    totalA: <?= json_encode(round($ensTotA, 2)) ?>,
                    totalB: <?= json_encode(round($ensTotB, 2)) ?>,
                    combinedTotal: <?= json_encode(round((float)($ensData['combined_total'] ?? ($ensTotA + $ensTotB)), 2)) ?>,
                    countA: <?= (int)($ensData['count_a'] ?? 0) ?>,
                    countB: <?= (int)($ensData['count_b'] ?? 0) ?>,
                    avgA: <?= json_encode((float)($ensData['avg_a'] ?? 0)) ?>,
                    avgB: <?= json_encode((float)($ensData['avg_b'] ?? 0)) ?>,
                    colorA: ggScope === 'year_b' ? 'rgba(13,110,253,0.45)' : 'rgba(13,110,253,0.86)',
                    colorB: ggScope === 'year_b' ? 'rgba(217,119,6,0.86)' : 'rgba(100,116,139,0.72)',
                    colorCombined: 'rgba(22,163,74,0.86)',
                    inkA: ggScope === 'year_b' ? '#1d4ed8' : '#1e3a8a',
                    inkB: ggScope === 'year_b' ? '#b45309' : '#475569',
                    inkCombined: '#15803d',
                    onBarClick: function(dim, key, targetYear, triggerEl) {
                        openGgDetail(dim, key, targetYear ? { target_year: targetYear } : {}, triggerEl);
                    }
                });
            }

            // Item 7: Pareto Penyebab Gangguan (Dual-Axis Bar + Cumulative % + 80% Reference Threshold)
            var ctx7 = document.getElementById('chartParetoPenyebab');
            if (ctx7) {
                var paretoItems = <?= json_encode($item7['items'] ?? [], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
                var paretoTotal = <?= (int)($item7['total'] ?? 0) ?>;
                var maxParetoCount = paretoItems.reduce(function(m, i) { return Math.max(m, Number(i.count || 0)); }, 0);
                new Chart(ctx7, {
                    data: {
                        labels: paretoItems.map(function(i) { return i.cause_raw; }),
                        datasets: [
                            {
                                type: 'line',
                                label: '% Kumulatif Pareto',
                                data: paretoItems.map(function(i) { return i.cumulative_pct; }),
                                borderColor: '#dc2626',
                                backgroundColor: '#dc2626',
                                borderWidth: 2.4,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointHitRadius: 10,
                                tension: 0.15,
                                yAxisID: 'y1',
                                order: 0
                            },
                            {
                                type: 'bar',
                                label: 'Jumlah Gangguan',
                                data: paretoItems.map(function(i) { return i.count; }),
                                backgroundColor: paretoItems.map(function(i) { return i.is_vital_80 ? '#f59e0b' : '#fcd34d'; }),
                                borderColor: '#d97706',
                                borderWidth: 1,
                                borderRadius: 4,
                                yAxisID: 'y',
                                order: 1
                            }
                        ]
                    },
                    plugins: [barValueLabelPlugin, pareto80ThresholdPlugin],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        onHover: setPointerOnHover,
                        onClick: function(evt, elements, chart) {
                            // First detect exact hit on cumulative point vs bar so both interactions work cleanly
                            var exactHits = chart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                            if (exactHits && exactHits.length) {
                                var lineHit = exactHits.find(function(h) { return h.datasetIndex === 0; });
                                if (lineHit) {
                                    var rowLine = paretoItems[lineHit.index];
                                    if (rowLine) {
                                        openGgDetail('pareto_cumulative', rowLine.cause_raw, { rank: rowLine.rank }, ctx7);
                                    }
                                    return;
                                }
                                var barHit = exactHits.find(function(h) { return h.datasetIndex === 1; });
                                if (barHit) {
                                    var rowBar = paretoItems[barHit.index];
                                    if (rowBar) {
                                        openGgDetail('pareto_cause', rowBar.cause_raw, {}, ctx7);
                                    }
                                    return;
                                }
                            }
                            var colHits = chart.getElementsAtEventForMode(evt, 'index', { intersect: false }, true);
                            if (colHits && colHits.length) {
                                var rowCol = paretoItems[colHits[0].index];
                                if (rowCol) openGgDetail('pareto_cause', rowCol.cause_raw, {}, ctx7);
                            }
                        },
                        layout: { padding: { top: 22 } },
                        plugins: {
                            legend: { position: 'top', align: 'end', labels: { boxWidth: 12, font: { size: 11 } } },
                            tooltip: {
                                callbacks: {
                                    title: function(items) {
                                        var idx = items[0] ? items[0].dataIndex : -1;
                                        var row = paretoItems[idx];
                                        return row ? ('#' + row.rank + ' • ' + row.cause_raw) : '';
                                    },
                                    afterBody: function(items) {
                                        var idx = items[0] ? items[0].dataIndex : -1;
                                        var row = paretoItems[idx];
                                        if (!row) return [];
                                        return [
                                            'Kontribusi: ' + row.pct + '% (' + row.count + ' / ' + paretoTotal + ')',
                                            'Kumulatif: ' + row.cumulative_count + ' / ' + paretoTotal + ' (' + row.cumulative_pct + '%)',
                                            'Status Pareto: ' + (row.is_vital_80 ? '80% Vital Few' : 'Tail > 80%'),
                                            'Klasifikasi: ' + row.category_label,
                                            'Klik batang: rincian penyebab • Klik titik garis: urutan kumulatif'
                                        ];
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { autoSkip: false, font: { size: 10 }, maxRotation: 42, minRotation: 25 } },
                            y: { type: 'linear', position: 'left', beginAtZero: true, suggestedMax: maxParetoCount > 0 ? Math.ceil(maxParetoCount * 1.18) : 10, title: { display: true, text: 'Jumlah Kejadian', font: { size: 10.5 } }, ticks: { precision: 0 } },
                            y1: { type: 'linear', position: 'right', min: 0, max: 100, grid: { drawOnChartArea: false }, title: { display: true, text: 'Kumulatif (%)', font: { size: 10.5 } }, ticks: { stepSize: 20, callback: function(v) { return v + '%'; } } }
                        }
                    }
                });
            }

            // Pareto Table Rows accessible click -> drill-down
            document.querySelectorAll('#tableParetoPenyebab tbody tr.gg-pareto-row').forEach(function(tr) {
                var causeKey = tr.getAttribute('data-cause');
                if (causeKey && window.bindAccessibleAction) {
                    window.bindAccessibleAction(tr, function() {
                        openGgDetail('pareto_cause', causeKey, {}, tr);
                    });
                }
            });

            // Item 8: Top 10 Gangguan Penyulang
            var ctx8 = document.getElementById('chartTop10Penyulang');
            if (ctx8) {
                var top10Rows = <?= json_encode($top10Merged ?? [], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
                var top10Data = top10Rows.map(function(r) { return Number(r.cnt_focus ?? r.cnt_a ?? 0); });
                var maxTop10  = Math.max.apply(null, [0].concat(top10Data));
                new Chart(ctx8, {
                    type: 'bar',
                    data: {
                        labels: top10Rows.map(function(r) { return r.nama_penyulang; }),
                        datasets: [{
                            label: 'Gangguan <?= esc($scope === "year_b" ? $yearB : ($scope === "combined" ? ($yearA . "+" . $yearB) : $yearA)) ?>',
                            data: top10Data,
                            backgroundColor: '#be185d',
                            borderRadius: 4
                        }]
                    },
                    plugins: [barValueLabelPlugin],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        onHover: setPointerOnHover,
                        onClick: function(evt, elements, chart) {
                            var hits = elements && elements.length ? elements : chart.getElementsAtEventForMode(evt, 'nearest', { intersect: false }, true);
                            if (!hits || !hits.length) return;
                            var row = top10Rows[hits[0].index];
                            if (row) openGgDetail('feeder', row.feeder_name || row.nama_penyulang, {}, ctx8);
                        },
                        layout: { padding: { top: 20 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    afterLabel: function(tc) {
                                        var row = top10Rows[tc.dataIndex];
                                        return row ? ['ULP: ' + row.nama_ulp, 'Klik batang untuk detail penyulang'] : '';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { autoSkip: false, font: { size: 10 }, maxRotation: 30 } },
                            y: { beginAtZero: true, suggestedMax: maxTop10 > 0 ? Math.ceil(maxTop10 * 1.18) : 5, ticks: { precision: 0, font: { size: 10 } } }
                        }
                    }
                });
            }

            // Item 9: Sebaran Gangguan PMT & Recloser (default Non-PMT so Recloser/PMCB scale is crisp)
            if (document.getElementById('chartSebaranPeralatan')) {
                renderSebaranChart(sebaranNonPmtDevices.length ? sebaranNonPmtDevices : sebaranAllDevices);
            }
        });

        // Collapse icon toggle
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
            <?php if ($yearFilter || $categoryFilter || $search !== ''): ?>
            bootstrap.Collapse.getOrCreateInstance(colEl, { toggle: false }).show();
            <?php endif; ?>
        }

        // Modal cascade ULP -> Penyulang
        var modalUlp = document.getElementById('modal_ulp_id');
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

        var filterUlp = document.getElementById('filter_ulp');
        var filterPenyulang = document.getElementById('filter_penyulang');
        if (filterUlp && filterPenyulang) {
            filterUlp.addEventListener('change', function() {
                var selUlp = this.value;
                if (!selUlp) return;
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
                    });
            });
        }
    });

    // Pivot table client-side search helpers
    window.filterPivotRows = function(tableId, query) {
        var q = String(query || '').trim().toLowerCase();
        var table = document.getElementById(tableId);
        if (!table) return;
        var rows = table.querySelectorAll('tbody tr');
        rows.forEach(function(tr) {
            var txt = tr.textContent.toLowerCase();
            tr.style.display = (q === '' || txt.indexOf(q) !== -1) ? '' : 'none';
        });
    };

    var item12ActiveFamily = 'ALL';
    window.filterItem12Family = function(family, btn) {
        item12ActiveFamily = family;
        if (btn && btn.parentElement) {
            btn.parentElement.querySelectorAll('button').forEach(function(b) {
                b.classList.remove('btn-success', 'active');
                b.classList.add('btn-outline-success');
            });
            btn.classList.remove('btn-outline-success');
            btn.classList.add('btn-success', 'active');
        }
        window.applyItem12Filters();
    };

    window.applyItem12Filters = function() {
        var q = String((document.getElementById('searchItem12') || {}).value || '').trim().toLowerCase();
        var table = document.getElementById('tableItem12');
        if (!table) return;
        table.querySelectorAll('tbody tr').forEach(function(tr) {
            var fam = tr.getAttribute('data-family') || '';
            var matchFam = (item12ActiveFamily === 'ALL' || fam === item12ActiveFamily);
            var matchQ   = (q === '' || tr.textContent.toLowerCase().indexOf(q) !== -1);
            tr.style.display = (matchFam && matchQ) ? '' : 'none';
        });
    };

    // Global Excel Preview & Import Handlers
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
        if (isServer) formData.append('use_server_file', '1');
        else formData.append('excel_file', fileInput.files[0]);

        btnPreview.disabled = true;
        btnExecute.disabled = true;
        statusEl.textContent = 'Memeriksa berkas...';

        fetch('<?= site_url('gangguan/preview-excel') ?>', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                btnPreview.disabled = false;
                if (res.status !== 'success') {
                    statusEl.textContent = res.message || 'Gagal memeriksa berkas.';
                    return;
                }
                var d = res.data;
                currentImportToken = res.import_token;
                container.style.display = 'block';
                document.getElementById('statTotalRows').textContent = d.total_physical_rows || 0;
                document.getElementById('statReadyImport').textContent = d.ready_to_import || 0;
                document.getElementById('statValidCount').textContent = d.valid_count || 0;
                document.getElementById('statWarningCount').textContent = d.warning_count || 0;
                document.getElementById('statDuplicateCount').textContent = d.duplicate_count || 0;
                document.getElementById('statRejectedCount').textContent = d.rejected_count || 0;
                document.getElementById('badgeIssueCount').textContent = d.total_issues_count || 0;
                var badge = document.getElementById('previewHeaderBadge');
                badge.className = d.header_valid ? 'badge bg-success-subtle text-success' : 'badge bg-danger-subtle text-danger';
                badge.textContent = d.header_valid ? 'Header Valid' : 'Header Tidak Sesuai';
                btnExecute.disabled = !(d.header_valid && d.ready_to_import > 0);
                statusEl.textContent = (d.ready_to_import || 0) + ' baris siap diimpor.';

                var esc = function(s) {
                    return String(s == null ? '' : s)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                };

                var sampBody = document.getElementById('previewSamplesTableBody');
                if (sampBody) {
                    var samples = Array.isArray(d.samples) ? d.samples : [];
                    sampBody.innerHTML = samples.length
                        ? samples.map(function(s) {
                            return '<tr>' +
                                '<td>' + esc(s.row) + '</td>' +
                                '<td>' + esc(s.event_date) + '</td>' +
                                '<td>' + esc(s.ulp) + '</td>' +
                                '<td>' + esc(s.penyulang) + '</td>' +
                                '<td>' + esc(s.rele || '-') + '</td>' +
                                '<td>' + esc(s.kategori) + '</td>' +
                                '<td>' + esc(s.durasi_menit !== null && typeof s.durasi_menit !== 'undefined' ? s.durasi_menit + ' mnt' : (s.lama_padam || '-')) + '</td>' +
                                '<td class="small">' + esc(s.keterangan || '-') + '</td>' +
                            '</tr>';
                        }).join('')
                        : '<tr><td colspan="8" class="text-center text-muted small">Tidak ada sampel baris valid.</td></tr>';
                }

                var colBody = document.getElementById('previewColumnsTableBody');
                if (colBody) {
                    var cols = Array.isArray(d.column_mapping) ? d.column_mapping : [];
                    colBody.innerHTML = cols.map(function(c) {
                        return '<tr>' +
                            '<td>' + esc(c.index) + '</td>' +
                            '<td><code>' + esc(c.expected) + '</code></td>' +
                            '<td><code>' + esc(c.actual) + '</code></td>' +
                            '<td>' + (c.matched ? '<span class="badge bg-success-subtle text-success">Cocok</span>' : '<span class="badge bg-warning-subtle text-warning-emphasis">Beda</span>') + '</td>' +
                        '</tr>';
                    }).join('');
                }

                var issBody = document.getElementById('previewIssuesTableBody');
                if (issBody) {
                    var issues = Array.isArray(d.issues) ? d.issues : [];
                    issBody.innerHTML = issues.length
                        ? issues.map(function(iss) {
                            return '<tr>' +
                                '<td>' + esc(iss.row) + '</td>' +
                                '<td><span class="badge bg-secondary-subtle text-dark">' + esc(iss.type) + '</span></td>' +
                                '<td class="small">' + esc(iss.reason) + '</td>' +
                            '</tr>';
                        }).join('')
                        : '<tr><td colspan="3" class="text-center text-success small">Tidak ada isu validasi pada berkas.</td></tr>';
                }
            })
            .catch(function() {
                btnPreview.disabled = false;
                statusEl.textContent = 'Gagal memeriksa berkas.';
            });
    };

    window.executeExcelImport = function() {
        if (!currentImportToken) return;
        if (!confirm('Apakah Anda yakin ingin mengeksekusi impor data gangguan ke database?')) return;
        var formData = new FormData();
        formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        formData.append('import_token', currentImportToken);
        fetch('<?= site_url('gangguan/import-excel') ?>', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                alert(res.message || 'Selesai.');
                if (res.status === 'success') window.location.reload();
            });
    };
})();
</script>
<?= $this->endSection() ?>
