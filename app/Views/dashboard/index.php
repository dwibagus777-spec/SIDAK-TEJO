<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Distribution Asset Health Monitoring Center<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Distribution Asset Health Monitoring Center<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $health = $assetHealthData ?? [];
    $totalTemuan = (int)($stats['total'] ?? 0);
    $totalFeeders = (int)($health['total_penyulang'] ?? 0);
    $avgHealth = (int)($health['avg_health_index'] ?? 0);
    $sehatCount = (int)($health['sehat_count'] ?? 0);
    $sehatPct = (int)($health['sehat_pct'] ?? 0);
    $kronisCount = (int)($health['kronis_count'] ?? 0);
    $kronisPct = (int)($health['kronis_pct'] ?? 0);
    $sakitCount = (int)($health['sakit_count'] ?? 0);
    $sakitPct = (int)($health['sakit_pct'] ?? 0);
    $kritisCount = (int)($health['kritis_count'] ?? 0);
    $kritisPct = (int)($health['kritis_pct'] ?? 0);
    $top10 = $health['top10'] ?? [];
    $allFeeders = $health['all_feeders'] ?? [];
    $statusRule = $health['status_rule'] ?? 'Berdasarkan temuan Emergency aktif (belum selesai): Sehat=0, Kronis=1, Sakit=2, Kritis=≥3';
    $jenisTemuan = $health['jenis_temuan'] ?? [];
    $recentActs = $health['recent'] ?? [];
    $currentUlp = 'UP3 Sidoarjo';
    if (!empty($selectedUlpId) && !empty($ulpsList)) {
        foreach ($ulpsList as $u) {
            if ((int)$u['id'] === (int)$selectedUlpId) {
                $currentUlp = $u['nama_ulp'];
                break;
            }
        }
    }

    // Calculate month-over-month comparison for Total Temuan
    $curMonthKey = date('Y-m');
    $prevMonthKey = date('Y-m', strtotime('first day of last month'));
    $monthlyByMonth = [];
    if (!empty($monthlyData) && is_array($monthlyData)) {
        foreach ($monthlyData as $md) {
            if (isset($md['bulan'])) {
                $monthlyByMonth[$md['bulan']] = (int)($md['total'] ?? 0);
            }
        }
    }

    $curMonthTemuan = $monthlyByMonth[$curMonthKey] ?? null;
    $prevMonthTemuan = $monthlyByMonth[$prevMonthKey] ?? null;

    if ($curMonthTemuan !== null && $prevMonthTemuan !== null && $prevMonthTemuan > 0) {
        $diff = $curMonthTemuan - $prevMonthTemuan;
        $pctChange = round(($diff / $prevMonthTemuan) * 100);
        if ($pctChange > 0) {
            $temuanChangeText = '<i class="fas fa-arrow-up"></i> ' . $pctChange . '% dari bulan lalu';
            $temuanChangeClass = 'text-primary';
        } elseif ($pctChange < 0) {
            $temuanChangeText = '<i class="fas fa-arrow-down"></i> ' . abs($pctChange) . '% dari bulan lalu';
            $temuanChangeClass = 'text-success';
        } else {
            $temuanChangeText = '<i class="fas fa-minus"></i> 0% dari bulan lalu';
            $temuanChangeClass = 'text-muted';
        }
    } else {
        $temuanChangeText = 'Data baru periode ini';
        $temuanChangeClass = 'text-muted';
    }
?>

<style>
    .dah-container {
        font-family: 'Outfit', 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #1e293b;
        overflow-x: hidden;
        max-width: 100%;
    }
    .dah-card {
        background: #ffffff;
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .dah-card:hover {
        box-shadow: 0 8px 25px rgba(15, 23, 42, 0.07);
    }
    .dah-header {
        background: #ffffff;
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        padding: 16px 24px;
        margin-bottom: 20px;
    }
    .dah-tagline {
        font-family: 'Brush Script MT', 'Dancing Script', cursive, sans-serif;
        font-size: 20px;
        color: #0284c7;
        letter-spacing: 0.5px;
    }
    .dah-kpi-card {
        padding: 16px 18px;
        border-radius: 16px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        position: relative;
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: all 0.2s ease;
    }
    .dah-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
    }
    .dah-kpi-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        margin-bottom: 12px;
    }
    .dah-kpi-val {
        font-size: 32px;
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -0.5px;
        margin-bottom: 4px;
    }
    .dah-kpi-lbl {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: capitalize;
    }
    .dah-kpi-sub {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .status-dot-live {
        width: 8px;
        height: 8px;
        background-color: #22c55e;
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 0 rgba(34, 197, 94, 0.4);
        animation: dahPulse 2s infinite;
    }
    @keyframes dahPulse {
        0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
        70% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
        100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }
    .badge-kritis {
        background-color: #fee2e2;
        color: #ef4444;
        border: 1px solid #fecaca;
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 20px;
        text-transform: uppercase;
    }
    .badge-sakit {
        background-color: #ffedd5;
        color: #f97316;
        border: 1px solid #fed7aa;
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 20px;
        text-transform: uppercase;
    }
    .badge-kronis {
        background-color: #fef3c7;
        color: #d97706;
        border: 1px solid #fde68a;
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 20px;
        text-transform: uppercase;
    }
    .badge-sehat {
        background-color: #dcfce7;
        color: #16a34a;
        border: 1px solid #bbf7d0;
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 20px;
        text-transform: uppercase;
    }
    .dah-quick-pill {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 8px 14px;
        font-size: 12px;
        font-weight: 600;
        color: #334155 !important;
        text-decoration: none !important;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        transition: all 0.2s ease;
    }
    .dah-quick-pill:hover {
        border-color: #0284c7;
        color: #0284c7 !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(2, 132, 199, 0.12);
    }
    .gis-popup-overlay {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 250px;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(8px);
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        padding: 14px;
    }
    .dah-chip-gangguan {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 9999px;
        padding: 5px 12px;
        transition: all 0.2s ease;
        text-decoration: none !important;
        font-size: 12px;
        font-weight: 600;
    }
    .dah-chip-gangguan:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.08) !important;
        border-color: #0284c7 !important;
    }
    body.dark-mode .dah-chip-gangguan {
        background: #1e293b !important;
        border-color: #334155 !important;
    }
    body.dark-mode .dah-chip-gangguan .text-dark {
        color: #f1f5f9 !important;
    }
    .dah-cum-card {
        min-height: 340px !important;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .dah-cum-chart-box {
        position: relative !important;
        width: 100% !important;
        height: 300px !important;
        max-height: 320px !important;
        flex: 1 1 300px !important;
        overflow: hidden;
    }
    .dah-cum-chart-box canvas {
        display: block;
        width: 100% !important;
        height: 100% !important;
        max-height: 320px !important;
    }
    @media (max-width: 767.98px) {
        .dah-cum-card {
            min-height: 290px !important;
        }
        .dah-cum-chart-box {
            height: 240px !important;
            max-height: 250px !important;
            flex: 1 1 240px !important;
        }
        .dah-cum-chart-box canvas {
            max-height: 250px !important;
        }
    }
</style>

<div class="dah-container container-fluid py-3">

    <!-- 1. HEADER SECTION (MATCHING REFERENCE IMAGE) -->
    <div class="dah-header d-flex flex-wrap align-items-center justify-content-between gap-3 shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="SIDAK TEJO" style="max-height: 48px;" class="bg-light p-1 rounded-3">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h3 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.5px;">SIDAK TEJO</h3>
                    <span class="dah-tagline d-none d-md-inline ms-2">Jaringan Andal Layanan Optimal</span>
                </div>
                <div class="text-muted small fw-medium" style="font-size: 12px;">Distribution Asset Health Monitoring Center &bull; UP3 Sidoarjo</div>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <!-- Filter ULP -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle bg-white rounded-pill px-3 fw-semibold text-dark" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 12px;">
                    <i class="fas fa-location-dot text-primary me-1"></i> <?= esc($currentUlp) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="font-size: 12px; border-radius: 12px;">
                    <li><a class="dropdown-item <?= empty($selectedUlpId) ? 'active' : '' ?>" href="<?= site_url('dashboard') ?>">Semua ULP (UP3 Sidoarjo)</a></li>
                    <?php if (!empty($ulpsList)): ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php foreach ($ulpsList as $u): ?>
                        <li><a class="dropdown-item <?= ((int)$selectedUlpId === (int)$u['id']) ? 'active' : '' ?>" href="<?= site_url('dashboard?ulp_id=' . $u['id']) ?>"><?= esc($u['nama_ulp']) ?></a></li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Date & Clock -->
            <div class="d-none d-sm-flex align-items-center gap-2 px-3 py-1 bg-light rounded-pill border" style="font-size: 12px; font-weight: 600;">
                <i class="far fa-calendar text-muted"></i>
                <span id="dah-date"><?= date('D, d M Y') ?></span>
                <span class="text-muted">&bull;</span>
                <i class="far fa-clock text-muted"></i>
                <span class="font-monospace text-primary" id="dah-live-clock"><?= date('H:i:s') ?> WIB</span>
            </div>

            <!-- Live Status Indicator -->
            <div class="d-flex align-items-center gap-2 px-3 py-1 bg-success-subtle text-success border border-success-subtle rounded-pill" style="font-size: 11px; font-weight: 700;">
                <span class="status-dot-live"></span> Live Data
            </div>

            <!-- Interruption Metric Chip (Domain Data) -->
            <?php if (!empty($gangguanStats)): ?>
            <?php
                $ggYearA   = (int)($gangguanStats['year'] ?? date('Y'));
                $ggYearB   = (int)($gangguanStats['last_year'] ?? ($ggYearA - 1));
                $ggUlpQ    = !empty($ulpIdFilter) ? '&ulp_id=' . (int)$ulpIdFilter : '';
                $ggUrl     = site_url('gangguan') . '?year_a=' . $ggYearA . '&year_b=' . $ggYearB . $ggUlpQ;
                $curCount  = (int)($gangguanStats['current_ytd'] ?? 0);
            ?>
            <a href="<?= $ggUrl ?>" class="dah-chip-gangguan d-inline-flex align-items-center gap-2 shadow-xs" title="Klik untuk membuka analitik gangguan penyulang (<?= $ggYearA ?> vs <?= $ggYearB ?>)">
                <i class="fas fa-bolt text-warning fs-6"></i>
                <span class="text-dark fw-bold" style="font-size: 12px; white-space: nowrap;">
                    Gangguan <?= esc($ggYearA) ?> &bull; <?= number_format($curCount) ?> kali
                </span>
                <?php if (!empty($gangguanStats['yoy_text']) && !empty($gangguanStats['yoy_pct'])): ?>
                    <span class="badge <?= ($gangguanStats['yoy_pct'] < 0) ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger' ?> rounded-pill px-2" style="font-size: 10px; font-weight: 600;">
                        <?= esc($gangguanStats['yoy_text']) ?>
                    </span>
                <?php endif; ?>
            </a>
            <?php endif; ?>


            <!-- Theme Toggle Button (Light / Dark Mode) -->
            <button type="button" class="btn btn-sm btn-outline-secondary border rounded-circle" title="Ganti Tema (Terang / Gelap)" onclick="toggleDarkMode()" style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center;">
                <i id="dah-theme-icon" class="fas fa-moon text-dark theme-toggle-icon"></i>
            </button>

            <!-- Quick Utilities -->
            <a href="<?= site_url('backup-database') ?>" class="btn btn-sm btn-outline-secondary text-muted border rounded-circle" title="Backup Database" style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="fas fa-database"></i>
            </a>
            <a href="<?= site_url('change-password') ?>" class="btn btn-sm btn-outline-secondary text-muted border rounded-circle" title="Ganti Password" style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="fas fa-key"></i>
            </a>
        </div>
    </div>

    <!-- 2. TOUCH-FRIENDLY QUICK ACTION BAR -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <?php if ($canInput ?? check_role(['administrator', 'admin_ulp', 'inspeksi'])): ?>
        <a href="<?= site_url('temuan/create') ?>" class="dah-quick-pill">
            <i class="fas fa-plus-circle text-success fs-6"></i>
            <span>Input Temuan Baru</span>
        </a>
        <?php endif; ?>
        <a href="<?= site_url('temuan') ?>" class="dah-quick-pill">
            <i class="fas fa-list-check text-primary fs-6"></i>
            <span>Data Semua Temuan</span>
        </a>
        <?php if ($canEdit ?? !check_role(['supervisor_up3'])): ?>
        <a href="<?= site_url('temuan/update-pekerjaan') ?>" class="dah-quick-pill">
            <i class="fas fa-wrench text-warning fs-6"></i>
            <span>Update Pekerjaan (HAR)</span>
        </a>
        <?php endif; ?>
        <a href="<?= site_url('temuan/terdekat') ?>" class="dah-quick-pill">
            <i class="fas fa-location-crosshairs text-danger fs-6"></i>
            <span>Temuan Terdekat</span>
        </a>
        <a href="<?= site_url('sld/view/15') ?>" class="dah-quick-pill">
            <i class="fas fa-diagram-project text-info fs-6"></i>
            <span>SLD Feeder</span>
        </a>
        <a href="<?= site_url('ai-copilot') ?>" class="dah-quick-pill">
            <i class="fas fa-robot text-purple fs-6"></i>
            <span>AI Copilot</span>
        </a>
        <a href="<?= site_url('captive-non-penugasan') ?>" class="dah-quick-pill"><i class="fas fa-plug text-warning fs-6"></i><span>Captive Non Penugasan</span></a>
    </div>


<!-- ASSET SUMMARY PANEL (STRUCTURED MODULE) -->
<?php
    $topSelectedUlpLabel = 'Semua ULP (UP3 Sidoarjo)';
    if (!empty($selectedUlpId) && !empty($ulpsList)) {
        foreach ($ulpsList as $u) {
            if ((int)$u['id'] === (int)$selectedUlpId) {
                $topSelectedUlpLabel = (string)$u['nama_ulp'];
                break;
            }
        }
    }
    $topUlpCount = !empty($selectedUlpId) ? 1 : count($ulpsList ?? []);
    $topPenyulangCount = (int)($totalFeeders ?? count($allFeeders ?? []));
?>
<div class="card dah-card shadow-sm border mb-4">
  <div class="card-header bg-white py-2.5 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom">
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-primary-subtle text-primary p-2 rounded-2"><i class="fas fa-layer-group"></i></span>
      <div>
        <h6 class="fw-bold mb-0 text-dark" style="font-size: 13.5px;">Ringkasan Aset &amp; Infrastruktur UP3 Sidoarjo</h6>
        <small class="text-muted" style="font-size: 11px;">Cakupan aktif: <strong class="text-primary"><?= esc($topSelectedUlpLabel) ?></strong> &bull; <?= (int)$topUlpCount ?> ULP &bull; <?= number_format($topPenyulangCount) ?> Penyulang</small>
      </div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <select aria-label="Filter ULP Ringkasan Aset" class="form-select form-select-sm rounded-pill fw-semibold text-dark" style="font-size: 11.5px; width: auto; min-width: 170px;" onchange="window.location.href = this.value ? '<?= site_url('dashboard') ?>?ulp_id=' + this.value : '<?= site_url('dashboard') ?>'">
        <option value="">Semua ULP (<?= count($ulpsList ?? []) ?> Unit)</option>
        <?php foreach (($ulpsList ?? []) as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= ((int)($selectedUlpId ?? 0) === (int)$u['id']) ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
        <?php endforeach; ?>
      </select>
      <a href="<?= site_url('assets' . (!empty($selectedUlpId) ? '?ulp_id=' . (int)$selectedUlpId : '')) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="font-size: 11.5px;">
        <i class="fas fa-boxes-stacked me-1"></i>Lihat Semua Aset
      </a>
    </div>
  </div>
  <div class="card-body py-3 px-3 bg-light-subtle">
    <div class="row g-2">
      <div class="col-xl-2 col-md-4 col-6">
        <div class="p-2.5 rounded-3 bg-white border shadow-2xs h-100 d-flex align-items-center gap-2.5">
          <div class="rounded-2 bg-dark-subtle text-dark d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;"><i class="fas fa-cubes"></i></div>
          <div>
            <div class="fw-bold fs-5 text-dark lh-1 font-monospace"><?= number_format($assetSummary['total'] ?? 0) ?></div>
            <div class="text-muted fw-semibold mt-1" style="font-size: 11px;">Total Aset</div>
          </div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-6">
        <div class="p-2.5 rounded-3 bg-white border shadow-2xs h-100 d-flex align-items-center gap-2.5">
          <div class="rounded-2 bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;"><i class="fas fa-tower-observation"></i></div>
          <div>
            <div class="fw-bold fs-5 text-primary lh-1 font-monospace"><?= number_format($assetSummary['jtm'] ?? 0) ?></div>
            <div class="text-muted fw-semibold mt-1" style="font-size: 11px;">Tiang JTM</div>
          </div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-6">
        <div class="p-2.5 rounded-3 bg-white border shadow-2xs h-100 d-flex align-items-center gap-2.5">
          <div class="rounded-2 bg-info-subtle text-info d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;"><i class="fas fa-toggle-on"></i></div>
          <div>
            <div class="fw-bold fs-5 text-info lh-1 font-monospace"><?= number_format($assetSummary['keypoint'] ?? 0) ?></div>
            <div class="text-muted fw-semibold mt-1" style="font-size: 11px;">Keypoint (LBS/Rec)</div>
          </div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-6">
        <div class="p-2.5 rounded-3 bg-white border shadow-2xs h-100 d-flex align-items-center gap-2.5">
          <div class="rounded-2 bg-warning-subtle text-warning d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;"><i class="fas fa-building-shield"></i></div>
          <div>
            <div class="fw-bold fs-5 text-warning lh-1 font-monospace"><?= number_format($assetSummary['gardu'] ?? 0) ?></div>
            <div class="text-muted fw-semibold mt-1" style="font-size: 11px;">Gardu</div>
          </div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-6">
        <div class="p-2.5 rounded-3 bg-white border shadow-2xs h-100 d-flex align-items-center gap-2.5">
          <div class="rounded-2 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;"><i class="fas fa-bolt-lightning"></i></div>
          <div>
            <div class="fw-bold fs-5 text-success lh-1 font-monospace"><?= number_format($assetSummary['trafo'] ?? 0) ?></div>
            <div class="text-muted fw-semibold mt-1" style="font-size: 11px;">Trafo</div>
          </div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4 col-6">
        <div class="p-2.5 rounded-3 bg-white border shadow-2xs h-100 d-flex align-items-center gap-2.5">
          <div class="rounded-2 bg-secondary-subtle text-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;"><i class="fas fa-tree"></i></div>
          <div>
            <div class="fw-bold fs-5 text-secondary lh-1 font-monospace"><?= number_format($assetSummary['pohon'] ?? 0) ?></div>
            <div class="text-muted fw-semibold mt-1" style="font-size: 11px;">Pohon (ROW)</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- 3. TOP 6 BENTO KPI CARDS (MATCHING REFERENCE IMAGE) -->
    <div class="row g-3 mb-4">
        <!-- 1. Total Temuan -->
        <div class="col-xl-2 col-md-4 col-sm-6 col-12">
            <div class="dah-kpi-card shadow-sm" style="border-top: 3px solid #0284c7; cursor: pointer;" onclick="window.location.href='<?= site_url('temuan') ?>'" title="Klik untuk membuka seluruh data temuan">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="dah-kpi-icon bg-info-subtle text-info mb-0">
                            <i class="fas fa-file-lines"></i>
                        </div>
                        <i class="fas fa-arrow-up-right-from-square text-muted opacity-50" style="font-size: 11px;"></i>
                    </div>
                    <div class="dah-kpi-val text-dark"><?= number_format($totalTemuan) ?></div>
                    <div class="dah-kpi-lbl">Total Temuan</div>
                </div>
                <div class="dah-kpi-sub <?= $temuanChangeClass ?>">
                    <?= $temuanChangeText ?>
                </div>
            </div>
        </div>

        <!-- 2. Penyulang Sehat -->
        <div class="col-xl-2 col-md-4 col-sm-6 col-12">
            <div class="dah-kpi-card shadow-sm" style="border-top: 3px solid #22c55e; cursor: pointer;" onclick="showFeederListByStatus('SEHAT')" title="Klik untuk membuka rincian penyulang sehat (0 Emergency)">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="dah-kpi-icon bg-success-subtle text-success mb-0">
                            <i class="fas fa-circle-check"></i>
                        </div>
                        <i class="fas fa-arrow-up-right-from-square text-muted opacity-50" style="font-size: 11px;"></i>
                    </div>
                    <div class="dah-kpi-val text-success"><?= number_format($sehatCount) ?></div>
                    <div class="dah-kpi-lbl">Penyulang Sehat</div>
                </div>
                <div class="dah-kpi-sub text-success">
                    <span><?= $sehatPct ?>%</span> (0 Emergency aktif)
                </div>
            </div>
        </div>

        <!-- 3. Penyulang Kronis -->
        <div class="col-xl-2 col-md-4 col-sm-6 col-12">
            <div class="dah-kpi-card shadow-sm" style="border-top: 3px solid #f59e0b; cursor: pointer;" onclick="showFeederListByStatus('KRONIS')" title="Klik untuk membuka rincian penyulang kronis (1 Emergency aktif)">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="dah-kpi-icon bg-warning-subtle text-warning mb-0">
                            <i class="fas fa-triangle-exclamation"></i>
                        </div>
                        <i class="fas fa-arrow-up-right-from-square text-muted opacity-50" style="font-size: 11px;"></i>
                    </div>
                    <div class="dah-kpi-val text-warning"><?= number_format($kronisCount) ?></div>
                    <div class="dah-kpi-lbl">Penyulang Kronis</div>
                </div>
                <div class="dah-kpi-sub text-warning">
                    <span><?= $kronisPct ?>%</span> (1 Emergency aktif)
                </div>
            </div>
        </div>

        <!-- 4. Penyulang Sakit -->
        <div class="col-xl-2 col-md-4 col-sm-6 col-12">
            <div class="dah-kpi-card shadow-sm" style="border-top: 3px solid #f97316; cursor: pointer;" onclick="showFeederListByStatus('SAKIT')" title="Klik untuk membuka rincian penyulang sakit (2 Emergency aktif)">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="dah-kpi-icon bg-danger-subtle text-danger mb-0">
                            <i class="fas fa-heart-pulse"></i>
                        </div>
                        <i class="fas fa-arrow-up-right-from-square text-muted opacity-50" style="font-size: 11px;"></i>
                    </div>
                    <div class="dah-kpi-val text-danger"><?= number_format($sakitCount) ?></div>
                    <div class="dah-kpi-lbl">Penyulang Sakit</div>
                </div>
                <div class="dah-kpi-sub text-danger">
                    <span><?= $sakitPct ?>%</span> (2 Emergency aktif)
                </div>
            </div>
        </div>

        <!-- 5. Penyulang Kritis -->
        <div class="col-xl-2 col-md-4 col-sm-6 col-12">
            <div class="dah-kpi-card shadow-sm" style="border-top: 3px solid #ef4444; cursor: pointer;" onclick="showFeederListByStatus('KRITIS')" title="Klik untuk membuka rincian penyulang kritis (≥ 3 Emergency aktif)">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="dah-kpi-icon bg-danger-subtle text-danger mb-0">
                            <i class="fas fa-bell"></i>
                        </div>
                        <i class="fas fa-arrow-up-right-from-square text-muted opacity-50" style="font-size: 11px;"></i>
                    </div>
                    <div class="dah-kpi-val text-danger"><?= number_format($kritisCount) ?></div>
                    <div class="dah-kpi-lbl">Penyulang Kritis</div>
                </div>
                <div class="dah-kpi-sub text-danger">
                    <span><?= $kritisPct ?>%</span> (≥ 3 Emergency aktif)
                </div>
            </div>
        </div>

        <!-- 6. Health Index Sistem -->
        <div class="col-xl-2 col-md-4 col-sm-6 col-12">
            <div class="dah-kpi-card shadow-sm" style="border-top: 3px solid #06b6d4; cursor: pointer;" onclick="showFeederListByStatus('ALL')" title="Klik untuk melihat skor & rincian seluruh penyulang">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="dah-kpi-icon bg-info-subtle text-info mb-0">
                            <i class="fas fa-chart-column"></i>
                        </div>
                        <i class="fas fa-arrow-up-right-from-square text-muted opacity-50" style="font-size: 11px;"></i>
                    </div>
                    <div class="dah-kpi-val text-info"><?= $avgHealth ?>%</div>
                    <div class="dah-kpi-lbl">Health Index Sistem</div>
                </div>
                <div class="dah-kpi-sub text-info">
                    Avg score <?= number_format($totalFeeders) ?> penyulang
                </div>
            </div>
        </div>
    </div>


    <!-- 4. ROW 2: HEALTH GAUGE, STATUS DONUT, & TOP 10 FEEDERS -->
    <div class="row g-4 mb-4">
        <!-- 4A. Health Index Sistem Gauge -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="dah-card p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-heart-pulse text-danger me-2"></i>Health Index Sistem</h6>
                        <span class="badge bg-light text-muted border rounded-pill">PLN UP3</span>
                    </div>

                    <!-- Speedometer Semi-Circle Gauge -->
                    <div class="position-relative text-center my-3" style="height: 120px;">
                        <svg viewBox="0 0 100 55" class="w-100 h-100">
                            <!-- Background Arc -->
                            <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="#e2e8f0" stroke-width="8" stroke-linecap="round"/>
                            <!-- Colored Arc based on Health Index -->
                            <?php 
                                $dashOffset = 125.6 * (1 - ($avgHealth / 100));
                                $arcColor = $avgHealth >= 80 ? '#22c55e' : ($avgHealth >= 60 ? '#f59e0b' : ($avgHealth >= 40 ? '#f97316' : '#ef4444'));
                            ?>
                            <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="<?= $arcColor ?>" stroke-width="8" stroke-linecap="round" stroke-dasharray="125.6" stroke-dashoffset="<?= $dashOffset ?>"/>
                        </svg>
                        <div class="position-absolute bottom-0 start-50 translate-middle-x mb-1 text-center">
                            <h2 class="fw-black mb-0 text-dark" style="font-size: 28px;"><?= $avgHealth ?>%</h2>
                            <?php 
                                $healthBadgeClass = $avgHealth >= 80 ? 'bg-success-subtle text-success border-success-subtle' : ($avgHealth >= 60 ? 'bg-warning-subtle text-warning border-warning-subtle' : ($avgHealth >= 40 ? 'bg-orange-subtle text-orange border-orange-subtle' : 'bg-danger-subtle text-danger border-danger-subtle'));
                                $healthStatusLabel = $avgHealth >= 80 ? 'Kondisi Baik' : ($avgHealth >= 60 ? 'Waspada' : ($avgHealth >= 40 ? 'Perlu Perhatian' : 'Kritis'));
                            ?>
                            <span class="badge <?= $healthBadgeClass ?> border rounded-pill fw-bold" style="font-size: 10px;"><?= $healthStatusLabel ?></span>
                        </div>
                    </div>
                </div>

                <!-- Legend & Real Status Summary -->
                <div>
                    <div class="p-2 mb-3 rounded-3 bg-light border" style="font-size: 11px;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Total Penyulang: <strong class="text-dark"><?= $totalFeeders ?></strong></span>
                            <span class="text-muted">Sehat: <strong class="text-success"><?= $sehatCount ?> (<?= $sehatPct ?>%)</strong></span>
                        </div>
                    </div>

                    <div style="font-size: 11px;" class="space-y-1 text-muted">
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                            <span><span class="badge bg-success rounded-circle p-1 me-1"></span> Sehat</span>
                            <strong>&gt; 80</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                            <span><span class="badge bg-warning rounded-circle p-1 me-1"></span> Kronis</span>
                            <strong>60 - 80</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                            <span><span class="badge bg-orange rounded-circle p-1 me-1" style="background:#f97316;"></span> Sakit</span>
                            <strong>40 - 60</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1">
                            <span><span class="badge bg-danger rounded-circle p-1 me-1"></span> Kritis</span>
                            <strong>&lt; 40</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4B. Distribusi Status Penyulang Donut -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="dah-card p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-pie text-primary me-2"></i>Distribusi Status Penyulang</h6>
                        <span class="badge bg-light text-muted border rounded-pill">Total <?= $totalFeeders ?></span>
                    </div>

                    <div class="position-relative my-2" style="height: 160px;">
                        <canvas id="feederStatusDonutChart"></canvas>
                        <div class="position-absolute top-50 start-50 translate-middle text-center" style="pointer-events: none;">
                            <span class="text-muted small" style="font-size: 10px; text-transform: uppercase;">Total</span>
                            <h4 class="fw-bold mb-0 text-dark"><?= $totalFeeders ?></h4>
                            <span class="text-muted" style="font-size: 10px;">Penyulang</span>
                        </div>
                    </div>
                </div>

                <div style="font-size: 11px;" class="mt-3">
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light" style="cursor: pointer;" onclick="showFeederListByStatus('SEHAT')" title="Klik untuk lihat daftar penyulang Sehat (0 Emergency)">
                        <span><span class="badge bg-success rounded-circle p-1 me-1"></span> Sehat (0 Emg)</span>
                        <strong><?= $sehatCount ?> (<?= $sehatPct ?>%) <i class="fas fa-chevron-right text-muted ms-1" style="font-size: 9px;"></i></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light" style="cursor: pointer;" onclick="showFeederListByStatus('KRONIS')" title="Klik untuk lihat daftar penyulang Kronis (1 Emergency)">
                        <span><span class="badge bg-warning rounded-circle p-1 me-1"></span> Kronis (1 Emg)</span>
                        <strong><?= $kronisCount ?> (<?= $kronisPct ?>%) <i class="fas fa-chevron-right text-muted ms-1" style="font-size: 9px;"></i></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light" style="cursor: pointer;" onclick="showFeederListByStatus('SAKIT')" title="Klik untuk lihat daftar penyulang Sakit (2 Emergency)">
                        <span><span class="badge rounded-circle p-1 me-1" style="background:#f97316;"></span> Sakit (2 Emg)</span>
                        <strong><?= $sakitCount ?> (<?= $sakitPct ?>%) <i class="fas fa-chevron-right text-muted ms-1" style="font-size: 9px;"></i></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light" style="cursor: pointer;" onclick="showFeederListByStatus('KRITIS')" title="Klik untuk lihat daftar penyulang Kritis (≥3 Emergency)">
                        <span><span class="badge bg-danger rounded-circle p-1 me-1"></span> Kritis (&ge;3 Emg)</span>
                        <strong><?= $kritisCount ?> (<?= $kritisPct ?>%) <i class="fas fa-chevron-right text-muted ms-1" style="font-size: 9px;"></i></strong>
                    </div>
                    <div class="text-center pt-2">
                        <small class="text-primary fw-semibold" style="font-size: 10px; cursor: pointer;" onclick="showFeederListByStatus('ALL')">
                            <i class="fas fa-list-check me-1"></i>Lihat Semua Daftar Status Penyulang
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4C. Top 10 Penyulang dengan Temuan Terbanyak -->
        <div class="col-lg-6 col-12">
            <div class="dah-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-trophy text-warning me-2"></i>Top 10 Penyulang dengan Temuan Terbanyak</h6>
                        <small class="text-muted" style="font-size: 11px;">Berdasarkan akumulasi temuan fisik di jaringan distribusi</small>
                    </div>
                    <a href="<?= site_url('penyulang') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1" style="font-size: 11px;">Lihat Semua</a>
                </div>

                <div class="table-responsive" style="max-height: 290px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                        <thead class="table-light sticky-top" style="font-size: 10px; text-transform: uppercase;">
                            <tr>
                                <th style="width: 25px;">#</th>
                                <th>Penyulang</th>
                                <th style="width: 32%;">Belum Selesai (Rincian)</th>
                                <th class="text-center" style="width: 15%;">Total Aktif</th>
                                <th class="text-center" style="width: 75px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($top10)): ?>
                                <?php 
                                    $maxCnt = max(1, (int)($top10[0]['total_temuan'] ?? 1));
                                    foreach ($top10 as $idx => $r): 
                                        $cnt = (int)($r['total_temuan'] ?? 0);
                                        $belum = (int)($r['belum_selesai'] ?? 0);
                                        $pctBar = min(100, round(($cnt / $maxCnt) * 100));
                                        $statusClass = 'badge-sehat';
                                        if (($r['status'] ?? '') === 'KRITIS') $statusClass = 'badge-kritis';
                                        elseif (($r['status'] ?? '') === 'SAKIT') $statusClass = 'badge-sakit';
                                        elseif (($r['status'] ?? '') === 'KRONIS') $statusClass = 'badge-kronis';
                                        $feederJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr style="cursor: pointer;" onclick="openFeederDetailModal(<?= $feederJson ?>)" title="Klik untuk rincian <?= esc($r['nama_penyulang']) ?>">
                                    <td class="text-muted fw-bold"><?= $idx + 1 ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= esc($r['nama_penyulang']) ?></div>
                                        <div class="text-muted small" style="font-size: 10px;"><?= esc($r['nama_ulp'] ?? 'UP3 Sidoarjo') ?></div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1 flex-wrap">
                                            <?php if ((int)$r['emg'] > 0): ?>
                                                <span class="badge bg-danger rounded-pill px-1" style="font-size: 9px;" title="Emergency Belum Selesai">EMG: <?= $r['emg'] ?></span>
                                            <?php endif; ?>
                                            <?php if ((int)$r['high'] > 0): ?>
                                                <span class="badge bg-warning text-dark rounded-pill px-1" style="font-size: 9px;" title="High Belum Selesai">HIGH: <?= $r['high'] ?></span>
                                            <?php endif; ?>
                                            <?php if ((int)$r['med'] > 0): ?>
                                                <span class="badge bg-info-subtle text-info border rounded-pill px-1" style="font-size: 9px;" title="Medium Belum Selesai">MED: <?= $r['med'] ?></span>
                                            <?php endif; ?>
                                            <span class="fw-bold text-dark font-monospace small ms-auto"><?= $belum ?></span>
                                        </div>
                                        <?php if (!empty($r['pelaksana_breakdown'])): ?>
                                        <div class="d-flex align-items-center gap-1 flex-wrap mt-1">
                                            <?php foreach (array_slice($r['pelaksana_breakdown'], 0, 3) as $pb): ?>
                                                <?php if ($pb['belum_selesai'] > 0): ?>
                                                    <span class="badge bg-light text-secondary border rounded-pill px-1" style="font-size: 8.5px;" title="<?= esc($pb['pelaksana']) ?>: <?= $pb['belum_selesai'] ?> belum selesai (EMG: <?= $pb['emg'] ?>, HIGH: <?= $pb['high'] ?>, MED: <?= $pb['med'] ?>)">
                                                        <?= esc($pb['pelaksana']) ?>: <?= $pb['belum_selesai'] ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <?php if (count($r['pelaksana_breakdown']) > 3): ?>
                                                <span class="text-muted" style="font-size: 8.5px;">+<?= count($r['pelaksana_breakdown']) - 3 ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                            <div class="progress d-none d-sm-block flex-grow-1" style="height: 5px; border-radius: 4px; background: #e2e8f0; max-width: 50px;">
                                                <div class="progress-bar rounded-pill" role="progressbar" style="width: <?= $pctBar ?>%; background: linear-gradient(90deg, #38bdf8, #0284c7);"></div>
                                            </div>
                                            <span class="fw-bold text-dark font-monospace"><?= $cnt ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="<?= $statusClass ?>"><?= esc($r['status'] ?? 'SEHAT') ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Belum ada data temuan penyulang.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. ROW 3: JENIS TEMUAN, GIS NETWORK HEALTH MAP, & AKTIVITAS LAPANGAN -->
    <div class="row g-4 mb-4">
        <!-- 5A. Jenis Temuan Terbanyak -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="dah-card p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-layer-group text-info me-2"></i>Jenis Temuan</h6>
                        <span class="badge bg-light text-muted border rounded-pill">Top Kategori</span>
                    </div>

                    <div class="space-y-3">
                        <?php if (!empty($jenisTemuan)): ?>
                            <?php 
                                $totalJ = array_sum(array_column($jenisTemuan, 'cnt'));
                                $colors = ['#ef4444', '#f97316', '#f59e0b', '#3b82f6', '#8b5cf6', '#64748b'];
                                foreach ($jenisTemuan as $jIdx => $j): 
                                    $jCnt = (int)$j['cnt'];
                                    $jPct = $totalJ > 0 ? round(($jCnt / $totalJ) * 100) : 0;
                                    $barCol = $colors[$jIdx % count($colors)];
                            ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 11px;">
                                    <span class="text-dark fw-semibold text-truncate me-2" style="max-width: 140px;"><?= esc($j['jenis_temuan'] ?: 'Lainnya') ?></span>
                                    <span class="text-muted font-monospace"><?= $jPct ?>% (<?= $jCnt ?>)</span>
                                </div>
                                <div class="progress" style="height: 6px; border-radius: 4px; background: #e2e8f0;">
                                    <div class="progress-bar rounded-pill" role="progressbar" style="width: <?= $jPct ?>%; background-color: <?= $barCol ?>;" aria-valuenow="<?= $jPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-muted small text-center py-4">Data kategori temuan belum tersedia.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pt-3 border-top border-light text-center">
                    <a href="<?= site_url('laporan') ?>" class="text-primary text-decoration-none fw-semibold small" style="font-size: 11px;">
                        Lihat Analisis Temuan <i class="fas fa-chevron-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- 5B. GIS Network Health Map Panel -->
        <div class="col-lg-6 col-12">
            <div class="dah-card p-0 overflow-hidden h-100 position-relative d-flex flex-column" style="min-height: 380px;">
                <!-- Map Top Overlay Header -->
                <div class="p-3 bg-white border-bottom d-flex align-items-center justify-content-between z-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-primary-subtle text-primary rounded-3">
                            <i class="fas fa-map-location-dot"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark">GIS Network Health Map</h6>
                            <small class="text-muted" style="font-size: 11px;">Pemetaan Sebaran &amp; Status Kesehatan Jaringan PLN</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill" style="font-size: 10px;">
                            <span class="status-dot-live me-1"></span> Hybrid Map
                        </span>
                        <a href="<?= site_url('gis') ?>" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold" style="font-size: 11px;">
                            <i class="fas fa-expand me-1"></i> Full Map Mode
                        </a>
                    </div>
                </div>

                <!-- Leaflet Map Container -->
                <div id="dahGisMap" class="flex-grow-1" style="min-height: 280px; width: 100%; z-index: 1;"></div>

                <!-- Floating Interactive Pin & Feeder Detail Card on Map -->
                <div class="gis-popup-overlay d-none d-md-block shadow">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark" style="font-size: 12px;" id="popupFeederTitle">Detail Titik Temuan GIS</span>
                        <span class="badge bg-light text-muted border" id="popupFeederBadge">-</span>
                    </div>
                    <div class="small text-muted mb-2" style="font-size: 11px;">
                        <div class="d-flex justify-content-between py-1 border-bottom border-light">
                            <span>Penyulang:</span>
                            <strong class="text-primary text-truncate ms-2" style="max-width: 130px;" id="popupFeederName">-</strong>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom border-light">
                            <span>Prioritas:</span>
                            <strong id="popupFeederPrio">-</strong>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom border-light">
                            <span>Status:</span>
                            <strong id="popupFeederStatus">-</strong>
                        </div>
                        <div class="py-1">
                            <span class="d-block text-muted" style="font-size: 10px;">Catatan:</span>
                            <div class="text-dark small text-truncate" id="popupFeederDetail" style="font-size: 11px;">Pilih pin pada peta atau klik penyulang di Top 10</div>
                        </div>
                    </div>
                    <a href="<?= site_url('temuan') ?>" id="popupFeederLink" class="btn btn-xs btn-outline-primary w-100 rounded-pill fw-bold" style="font-size: 10px;">
                        Buka Halaman Temuan &rarr;
                    </a>
                </div>

                <!-- Map Bottom Legend & Disclaimer -->
                <div class="p-2 bg-white border-top d-flex flex-wrap align-items-center justify-content-between px-3 z-2" style="font-size: 11px;">
                    <div class="d-flex align-items-center gap-3 text-muted">
                        <span><span class="badge bg-danger rounded-circle p-1 me-1"></span> Emergency</span>
                        <span><span class="badge bg-warning rounded-circle p-1 me-1"></span> High</span>
                        <span><span class="badge bg-info rounded-circle p-1 me-1"></span> Medium</span>
                        <span><span class="badge bg-success rounded-circle p-1 me-1"></span> Low</span>
                    </div>
                    <span class="text-muted small"><i class="fas fa-info-circle me-1"></i>Pin peta mewakili titik temuan fisik (gardu/tiang), bukan rute utuh kawat jaringan.</span>
                </div>
            </div>
        </div>

        <!-- 5C. Aktivitas Lapangan Terkini -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="dah-card p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-satellite-dish text-success me-2"></i>Aktivitas Lapangan</h6>
                        <span class="status-dot-live"></span>
                    </div>

                    <div class="space-y-3">
                        <?php if (!empty($recentActs)): ?>
                            <?php foreach ($recentActs as $act): 
                                $prio = strtoupper((string)($act['prioritas'] ?? 'MEDIUM'));
                                $badgeClass = $prio === 'EMERGENCY' ? 'badge bg-danger text-white' : ($prio === 'HIGH' ? 'badge bg-warning text-dark' : 'badge bg-info text-white');
                            ?>
                            <div class="p-2 rounded-3 bg-light border mb-2" style="font-size: 11px;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-dark text-truncate me-1" style="max-width: 130px;"><?= esc($act['nama_penyulang'] ?: 'Penyulang') ?></span>
                                    <span class="<?= $badgeClass ?>" style="font-size: 9px;"><?= esc($act['status'] ?: $prio) ?></span>
                                </div>
                                <div class="text-muted text-truncate mb-1" style="font-size: 11px;"><?= esc($act['detail_temuan'] ?: ($act['jenis_temuan'] ?? 'Inspeksi fisik')) ?></div>
                                <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 10px;">
                                    <span><i class="fas fa-user-circle me-1"></i> <?= esc($act['pelaksana'] ?: 'Team HAR') ?></span>
                                    <span><?= date('H:i', strtotime($act['created_at'] ?? 'now')) ?> WIB</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-muted small text-center py-4">Belum ada aktivitas tercatat hari ini.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pt-3 border-top border-light text-center">
                    <a href="<?= site_url('my-history') ?>" class="text-primary text-decoration-none fw-semibold small" style="font-size: 11px;">
                        Lihat Riwayat Lengkap <i class="fas fa-chevron-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>


        <!-- 6. ANALISIS TEMUAN INSPEKSI, KEBUTUHAN MATERIAL BOM, & EVALUASI GANGGUAN -->
    <!-- 6A. Penyulang Temuan Inspeksi Terbanyak -->
    <div class="card dah-card shadow-sm border mb-3">
        <div class="card-header bg-white py-2.5 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-info-subtle text-info p-2 rounded-2"><i class="fas fa-clipboard-list"></i></span>
                <div>
                    <h6 class="fw-bold mb-0 text-dark" style="font-size: 13.5px;">Penyulang Temuan Inspeksi Terbanyak</h6>
                    <small class="text-muted" style="font-size: 11px;">Rincian pelaksana temuan aktif (PDKB, HAR GTT, HAR ROW) &bull; Klik baris untuk sorot peta GIS</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-muted border rounded-pill px-2.5 py-1 small">
                    <?= count($topInspeksiPenyulang ?? []) ?> Penyulang
                </span>
                <a href="<?= site_url('temuan') ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-1 fw-semibold" style="font-size: 11px;">
                    Semua Temuan <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                    <thead class="table-light sticky-top" style="font-size: 11px; text-transform: uppercase;">
                        <tr>
                            <th style="width: 40px;" class="text-center">#</th>
                            <th>Penyulang</th>
                            <th class="text-end" style="width: 110px;" title="Temuan PDKB">PDKB</th>
                            <th class="text-end" style="width: 110px;" title="Temuan HAR GTT">HAR GTT</th>
                            <th class="text-end" style="width: 110px;" title="Temuan HAR ROW">HAR ROW</th>
                            <th class="text-end" style="width: 110px;" title="Total Temuan Aktif">Total Temuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($topInspeksiPenyulang ?? []) as $rank => $row): ?>
                        <tr style="cursor: pointer;" onclick="highlightFeederOnMap(<?= (int)$row['penyulang_id'] ?>, '<?= esc(addslashes($row['nama_penyulang'] ?? '')) ?>')" title="Klik untuk fokus ke titik penyulang di GIS Map">
                            <td class="text-center text-muted fw-bold"><?= $rank + 1 ?></td>
                            <td>
                                <span class="fw-semibold text-dark"><?= esc($row['nama_penyulang'] ?? '-') ?></span>
                                <span class="text-muted ms-2" style="font-size: 10px;"><i class="fas fa-location-dot text-primary me-1"></i>Lihat di GIS</span>
                            </td>
                            <td class="text-end"><span class="badge bg-light text-dark border font-monospace"><?= (int)($row['pdkb'] ?? 0) ?></span></td>
                            <td class="text-end"><span class="badge bg-light text-dark border font-monospace"><?= (int)($row['har_gardu'] ?? 0) ?></span></td>
                            <td class="text-end"><span class="badge bg-light text-dark border font-monospace"><?= (int)($row['har_row'] ?? 0) ?></span></td>
                            <td class="text-end fw-bold text-info font-monospace fs-6"><?= (int)($row['total'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topInspeksiPenyulang)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4 small">Tidak ada data temuan inspeksi aktif.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 6B. TABEL TEMUAN DAN KEBUTUHAN MATERIAL (TEPAT DI BAWAH PENYULANG TEMUAN INSPEKSI TERBANYAK) -->
    <?php
        $bomMatrixRows      = $temuanMaterialMatrix['rows'] ?? [];
        $bomSummaryByMat    = $temuanMaterialMatrix['summary_by_mat'] ?? [];
        $bomConstructions   = $temuanMaterialMatrix['bom_constructions'] ?? [];
        $bomGisList         = $temuanMaterialMatrix['gis_list'] ?? [];
        $bomCondTypes       = $temuanMaterialMatrix['conductor_types'] ?? ['AAAC', 'AAACS', 'MVTIC', 'XLPE'];
        $bomCondSizes       = $temuanMaterialMatrix['conductor_sizes'] ?? ['70', '110', '150', '240'];

        $totMatTemuan   = 0;
        $totMatArmtie   = 0.0;
        $totMatCrossarm = 0.0;
        $totMatPinIso   = 0.0;
        $totMatHangIso  = 0.0;
        $totMatFco      = 0.0;
        $totMatArrester = 0.0;
        $totMatSkun     = 0.0;
        $totMatGsw      = 0.0;
        $totMatCondM    = 0.0;
        foreach ($bomMatrixRows as $bmRow) {
            $totMatTemuan   += (int)($bmRow['total_temuan'] ?? $bmRow['temuan_count'] ?? 0);
            $totMatArmtie   += (float)($bmRow['qty_armtie'] ?? 0);
            $totMatCrossarm += (float)($bmRow['qty_crossarm'] ?? 0);
            $totMatPinIso   += (float)($bmRow['qty_pin_isolator'] ?? 0);
            $totMatHangIso  += (float)($bmRow['qty_hang_isolator'] ?? 0);
            $totMatFco      += (float)($bmRow['qty_fco'] ?? 0);
            $totMatArrester += (float)($bmRow['qty_arrester'] ?? 0);
            $totMatSkun     += (float)($bmRow['qty_skun_konektor'] ?? 0);
            $totMatGsw      += (float)($bmRow['qty_gsw_ground'] ?? 0);
            $totMatCondM    += (float)($bmRow['qty_konduktor_m'] ?? 0);
        }
    ?>
    <div class="card dah-card shadow-sm border mb-4" id="temuanMaterialBomCard">
        <div class="card-header bg-white py-3 px-3 border-bottom">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary p-2 rounded-2"><i class="fas fa-boxes-stacked fs-5"></i></span>
                    <div>
                        <h5 class="fw-bold text-dark mb-0" style="font-size: 14px;">Tabel Temuan dan Kebutuhan Material (Standar BOM Konstruksi PLN)</h5>
                        <div class="small text-muted" style="font-size: 11px;">
                            Rekapitulasi temuan per GI, ULP, Penyulang, Section, Spesifikasi Konduktor (<code>AAAC</code>, <code>AAACS</code>, <code>MVTIC</code>, <code>XLPE</code> &bull; <code>70</code>, <code>110</code>, <code>150</code>, <code>240 mm²</code>), serta rincian kebutuhan material BOM aktual
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <ul class="nav nav-pills nav-fill gap-1 bg-light p-1 rounded-pill border" id="bomModuleTabs" role="tablist" style="font-size: 11px;">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-pill py-1 px-2.5 fw-semibold" id="tab-bom-matrix-btn" data-bs-toggle="pill" data-bs-target="#pane-bom-matrix" type="button" role="tab">
                                <i class="fas fa-table me-1"></i>Matriks per Penyulang (<?= count($bomMatrixRows) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill py-1 px-2.5 fw-semibold" id="tab-bom-recap-btn" data-bs-toggle="pill" data-bs-target="#pane-bom-recap" type="button" role="tab">
                                <i class="fas fa-list-ol me-1"></i>Rekap Kode Material BOM (<?= count($bomSummaryByMat) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill py-1 px-2.5 fw-semibold" id="tab-bom-std-btn" data-bs-toggle="pill" data-bs-target="#pane-bom-std" type="button" role="tab">
                                <i class="fas fa-book-open me-1"></i>Standar Konstruksi JTM (TM-1 s/d TM-11)
                            </button>
                        </li>
                    </ul>
                    <a href="<?= site_url('temuan/create') ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold" style="font-size: 11px;">
                        <i class="fas fa-plus me-1"></i> Input Temuan &amp; BOM
                    </a>
                </div>
            </div>

            <!-- Interactive Filter Bar: GI, ULP, Penyulang, Jenis Konduktor, Ukuran Konduktor -->
            <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top">
                <div class="d-flex align-items-center gap-1">
                    <label for="bomFilterGi" class="small text-muted fw-semibold mb-0" style="font-size: 11px;">GI:</label>
                    <select id="bomFilterGi" class="form-select form-select-sm rounded-pill" style="font-size: 11px; width: auto; min-width: 135px;" onchange="applyBomMatrixFilter()">
                        <option value="">Semua GI (<?= count($bomGisList) ?>)</option>
                        <?php foreach ($bomGisList as $giOpt): ?>
                            <option value="<?= esc($giOpt) ?>"><?= esc($giOpt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <label for="bomFilterUlp" class="small text-muted fw-semibold mb-0" style="font-size: 11px;">ULP:</label>
                    <select id="bomFilterUlp" class="form-select form-select-sm rounded-pill" style="font-size: 11px; width: auto; min-width: 145px;" onchange="applyBomMatrixFilter()">
                        <option value="">Semua ULP</option>
                        <?php foreach (($ulpsList ?? []) as $uOpt): ?>
                            <option value="<?= (int)$uOpt['id'] ?>"><?= esc($uOpt['nama_ulp']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <label for="bomFilterCondType" class="small text-muted fw-semibold mb-0" style="font-size: 11px;">Konduktor:</label>
                    <select id="bomFilterCondType" class="form-select form-select-sm rounded-pill fw-semibold text-primary" style="font-size: 11px; width: auto; min-width: 135px;" onchange="onBomCondTypeFilterChange()">
                        <option value="">Semua Jenis (AAAC/AAACS/MVTIC/XLPE)</option>
                        <?php foreach ($bomCondTypes as $cType): ?>
                            <option value="<?= esc($cType) ?>"><?= esc($cType) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <label for="bomFilterCondSize" class="small text-muted fw-semibold mb-0" style="font-size: 11px;">Ukuran:</label>
                    <select id="bomFilterCondSize" class="form-select form-select-sm rounded-pill font-monospace" style="font-size: 11px; width: auto; min-width: 125px;" onchange="applyBomMatrixFilter()">
                        <option value="">Semua Ukuran (70–240 mm²)</option>
                        <?php foreach ($bomCondSizes as $cSz): ?>
                            <option value="<?= esc($cSz) ?>"><?= esc($cSz) ?> mm² <?= ((int)$cSz < 150) ? '(Non-Standar)' : '(Standar)' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex align-items-center gap-1 flex-grow-1" style="max-width: 240px;">
                    <input type="text" id="bomFilterSearch" class="form-control form-control-sm rounded-pill" style="font-size: 11px;" placeholder="Cari penyulang / section / kode BOM..." oninput="applyBomMatrixFilter()">
                </div>
                <button type="button" class="btn btn-sm btn-light border rounded-pill px-2.5 py-1 text-muted" style="font-size: 11px;" onclick="resetBomMatrixFilter()">
                    <i class="fas fa-rotate-left"></i> Reset
                </button>
                <span class="ms-auto badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 font-monospace" style="font-size: 11px;" id="bomMatrixActiveCountBadge">
                    <?= number_format($totMatTemuan) ?> Temuan &bull; <?= count($bomMatrixRows) ?> Segmen
                </span>
            </div>
        </div>

        <div class="tab-content" id="bomModuleTabContent">
            <!-- TAB 1: Matriks per GI, ULP, Penyulang, Section, Konduktor & Komponen BOM -->
            <div class="tab-pane fade show active" id="pane-bom-matrix" role="tabpanel">
                <div class="table-responsive" style="max-height: 390px; overflow-y: auto;">
                    <table class="table table-sm table-hover table-bordered align-middle mb-0" style="font-size: 11.5px;" id="bomMatrixTable">
                        <thead class="table-light sticky-top text-center" style="font-size: 10.5px; text-transform: uppercase;">
                            <tr>
                                <th rowspan="2" style="width: 34px;" class="align-middle">#</th>
                                <th rowspan="2" class="align-middle text-start">Gardu Induk (GI)</th>
                                <th rowspan="2" class="align-middle text-start">ULP</th>
                                <th rowspan="2" class="align-middle text-start">Penyulang</th>
                                <th rowspan="2" class="align-middle text-start">Section / Segmen</th>
                                <th rowspan="2" class="align-middle">Konduktor &amp; Ukuran</th>
                                <th rowspan="2" class="align-middle text-end" title="Jumlah Temuan pada Segmen">Temuan</th>
                                <th colspan="9" class="bg-primary-subtle text-primary fw-bold py-1">Kebutuhan Material Standar BOM PLN</th>
                                <th rowspan="2" class="align-middle" style="width: 195px;">Rincian Kode Material BOM</th>
                            </tr>
                            <tr>
                                <th class="text-end" title="BES00080015 Besi Arm Tie 750 mm (Bh)">Armtie<br><small class="text-muted fw-normal">(Bh)</small></th>
                                <th class="text-end" title="BES00020031 / BES00020003 Cross Arm UNP 10 (Bh)">Crossarm<br><small class="text-muted fw-normal">(Bh)</small></th>
                                <th class="text-end" title="ISO00020004 Isolator Tumpu / Pin Post 20 kV (Bh)">Pin Iso<br><small class="text-muted fw-normal">(Bh)</small></th>
                                <th class="text-end" title="ISO00010004 Isolator Tarik / Hang Long Rod 20 kV (Set)">Hang Iso<br><small class="text-muted fw-normal">(Set)</small></th>
                                <th class="text-end" title="CUT00010002 Fuse Cut Out (FCO) 20 kV (Bh)">FCO<br><small class="text-muted fw-normal">(Bh)</small></th>
                                <th class="text-end" title="ARR00010006 Lightning Arrester (LA) 20 kV (Bh)">Arrester<br><small class="text-muted fw-normal">(Bh)</small></th>
                                <th class="text-end" title="CCO00020004 / KON00040001 Joint Sleeve / Skun / Konektor (Bh)">Skun/Konek<br><small class="text-muted fw-normal">(Bh)</small></th>
                                <th class="text-end" title="MAT-GSW-ACC /PEN00050002 GSW &amp; Grounding (Set)">GSW/Grd<br><small class="text-muted fw-normal">(Set)</small></th>
                                <th class="text-end text-primary" title="MAT-COND-* Kebutuhan Konduktor (Meter)">Konduktor<br><small class="text-muted fw-normal">(m)</small></th>
                            </tr>
                        </thead>
                        <tbody id="bomMatrixTableBody">
                            <?php foreach ($bomMatrixRows as $bIdx => $bRow):
                                $cType = (string)($bRow['conductor_type'] ?? 'AAACS');
                                $cSize = (string)($bRow['conductor_size'] ?? '150');
                                $bGi   = (string)($bRow['nama_gi'] ?? $bRow['gardu_induk'] ?? '-');
                                $bTem  = (int)($bRow['total_temuan'] ?? $bRow['temuan_count'] ?? 0);
                                $fId   = (int)($bRow['penyulang_id'] ?? 0);
                                $sections = $bRow['sections'] ?? [];
                                $hasSections = count($sections) > 1 || (count($sections) === 1 && !empty($sections[0]['nama_section']) && $sections[0]['nama_section'] !== '-');
                                $isNonStdSize = ((int)$cSize > 0 && (int)$cSize < 150);
                                $cBadgeCls = $cType === 'AAAC'
                                    ? 'bg-warning-subtle text-warning-emphasis border-warning-subtle'
                                    : ($cType === 'MVTIC' || $cType === 'XLPE'
                                        ? 'bg-secondary-subtle text-dark border-secondary-subtle'
                                        : 'bg-info-subtle text-info-emphasis border-info-subtle');
                                $matSummaryStr = (string)($bRow['rincian_material'] ?? '');
                                $matFullStr    = $matSummaryStr;
                                if (mb_strlen($matSummaryStr) > 55) {
                                    $matSummaryStr = mb_substr($matSummaryStr, 0, 52) . '...';
                                }
                            ?>
                            <tr class="bom-matrix-row bom-feeder-row"
                                id="bom-feeder-<?= $fId ?>"
                                data-gi="<?= esc($bGi) ?>"
                                data-ulp-id="<?= (int)($bRow['ulp_id'] ?? 0) ?>"
                                data-feeder-id="<?= $fId ?>"
                                data-cond-type="<?= esc($cType) ?>"
                                data-cond-size="<?= esc($cSize) ?>"
                                data-search="<?= esc(strtolower($bGi . ' ' . ($bRow['nama_ulp'] ?? '') . ' ' . ($bRow['nama_penyulang'] ?? '') . ' ' . $cType . ' ' . $cSize . ' ' . $matFullStr)) ?>"
                                data-temuan="<?= $bTem ?>"
                                data-armtie="<?= (float)($bRow['qty_armtie'] ?? 0) ?>"
                                data-crossarm="<?= (float)($bRow['qty_crossarm'] ?? 0) ?>"
                                data-pin="<?= (float)($bRow['qty_pin_isolator'] ?? 0) ?>"
                                data-hang="<?= (float)($bRow['qty_hang_isolator'] ?? 0) ?>"
                                data-fco="<?= (float)($bRow['qty_fco'] ?? 0) ?>"
                                data-arrester="<?= (float)($bRow['qty_arrester'] ?? 0) ?>"
                                data-skun="<?= (float)($bRow['qty_skun_konektor'] ?? 0) ?>"
                                data-gsw="<?= (float)($bRow['qty_gsw_ground'] ?? 0) ?>"
                                data-condm="<?= (float)($bRow['qty_konduktor_m'] ?? 0) ?>">
                                <td class="text-center text-muted fw-bold"><?= $bIdx + 1 ?></td>
                                <td><span class="badge bg-light text-dark border"><?= esc($bGi) ?></span></td>
                                <td class="text-muted small"><?= esc($bRow['nama_ulp'] ?? '-') ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <?php if ($hasSections): ?>
                                            <button type="button" class="btn btn-xs btn-outline-secondary p-0 px-1 border-0 toggle-feeder-sections" data-feeder="<?= $fId ?>" title="Lihat Rincian Section">
                                                <i class="fas fa-chevron-right" style="font-size: 9px;"></i>
                                            </button>
                                        <?php endif; ?>
                                        <a href="<?= site_url('temuan?penyulang_id=' . $fId) ?>" class="fw-bold text-dark text-decoration-none">
                                            <?= esc($bRow['nama_penyulang'] ?? '-') ?>
                                        </a>
                                    </div>
                                </td>
                                <td class="text-muted small font-monospace">
                                    <?php if ($hasSections): ?>
                                        <span class="badge bg-light text-secondary border font-monospace" style="font-size: 10px; cursor: pointer;" onclick="toggleFeederSections(<?= $fId ?>)">
                                            <i class="fas fa-layer-group me-1"></i><?= count($sections) ?> Section
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge border <?= $cBadgeCls ?> font-monospace" style="font-size: 10px;">
                                        <?= esc($cType) ?> <?= esc($cSize) ?> mm²
                                    </span>
                                    <?php if ($isNonStdSize): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-0.5" style="font-size: 9px;" title="Konduktor Tidak Standar (< 150 mm²)">&lt;150</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold text-dark font-monospace"><?= number_format($bTem) ?></td>
                                <td class="text-end font-monospace <?= ($bRow['qty_armtie'] ?? 0) > 0 ? 'fw-bold text-dark' : 'text-muted' ?>"><?= ($bRow['qty_armtie'] ?? 0) > 0 ? number_format((float)$bRow['qty_armtie'], 0) : '-' ?></td>
                                <td class="text-end font-monospace <?= ($bRow['qty_crossarm'] ?? 0) > 0 ? 'fw-bold text-dark' : 'text-muted' ?>"><?= ($bRow['qty_crossarm'] ?? 0) > 0 ? number_format((float)$bRow['qty_crossarm'], 0) : '-' ?></td>
                                <td class="text-end font-monospace <?= ($bRow['qty_pin_isolator'] ?? 0) > 0 ? 'fw-bold text-dark' : 'text-muted' ?>"><?= ($bRow['qty_pin_isolator'] ?? 0) > 0 ? number_format((float)$bRow['qty_pin_isolator'], 0) : '-' ?></td>
                                <td class="text-end font-monospace <?= ($bRow['qty_hang_isolator'] ?? 0) > 0 ? 'fw-bold text-dark' : 'text-muted' ?>"><?= ($bRow['qty_hang_isolator'] ?? 0) > 0 ? number_format((float)$bRow['qty_hang_isolator'], 0) : '-' ?></td>
                                <td class="text-end font-monospace <?= ($bRow['qty_fco'] ?? 0) > 0 ? 'fw-bold text-danger' : 'text-muted' ?>"><?= ($bRow['qty_fco'] ?? 0) > 0 ? number_format((float)$bRow['qty_fco'], 0) : '-' ?></td>
                                <td class="text-end font-monospace <?= ($bRow['qty_arrester'] ?? 0) > 0 ? 'fw-bold text-warning-emphasis' : 'text-muted' ?>"><?= ($bRow['qty_arrester'] ?? 0) > 0 ? number_format((float)$bRow['qty_arrester'], 0) : '-' ?></td>
                                <td class="text-end font-monospace <?= ($bRow['qty_skun_konektor'] ?? 0) > 0 ? 'fw-bold text-dark' : 'text-muted' ?>"><?= ($bRow['qty_skun_konektor'] ?? 0) > 0 ? number_format((float)$bRow['qty_skun_konektor'], 0) : '-' ?></td>
                                <td class="text-end font-monospace <?= ($bRow['qty_gsw_ground'] ?? 0) > 0 ? 'fw-bold text-dark' : 'text-muted' ?>"><?= ($bRow['qty_gsw_ground'] ?? 0) > 0 ? number_format((float)$bRow['qty_gsw_ground'], 0) : '-' ?></td>
                                <td class="text-end font-monospace <?= ($bRow['qty_konduktor_m'] ?? 0) > 0 ? 'fw-bold text-primary' : 'text-muted' ?>"><?= ($bRow['qty_konduktor_m'] ?? 0) > 0 ? number_format((float)$bRow['qty_konduktor_m'], 0) : '-' ?></td>
                                <td class="small text-muted font-monospace" style="font-size: 10px;" title="<?= esc($matFullStr) ?>">
                                    <?php if (!empty($matSummaryStr)): ?>
                                        <?= esc($matSummaryStr) ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Belum Ada Rincian BOM</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($hasSections): ?>
                                <?php foreach ($sections as $sIdx => $sec): 
                                    $secName = (string)($sec['nama_section'] ?? '-');
                                    $sMatStr = (string)($sec['rincian_material'] ?? '');
                                    $sMatFull = $sMatStr;
                                    if (mb_strlen($sMatStr) > 45) $sMatStr = mb_substr($sMatStr, 0, 42) . '...';
                                ?>
                                <tr class="bom-section-child-row feeder-subrow-<?= $fId ?> d-none bg-light-subtle" style="font-size: 11px;">
                                    <td class="text-center text-muted">-</td>
                                    <td class="text-muted small"><?= esc($bGi) ?></td>
                                    <td class="text-muted small"><?= esc($bRow['nama_ulp'] ?? '-') ?></td>
                                    <td class="ps-4 text-muted small">
                                        <i class="fas fa-turn-up fa-rotate-90 text-muted me-1"></i>
                                        <span class="fw-semibold text-secondary">Section: <?= esc($secName) ?></span>
                                    </td>
                                    <td class="text-muted small font-monospace"><?= esc($secName) ?></td>
                                    <td class="text-center text-muted font-monospace" style="font-size: 9.5px;"><?= esc($sec['conductor_type'] ?? $cType) ?> <?= esc($sec['conductor_size'] ?? $cSize) ?></td>
                                    <td class="text-end text-dark font-monospace"><?= number_format((int)($sec['total_temuan'] ?? 0)) ?></td>
                                    <td class="text-end font-monospace text-muted"><?= ($sec['qty_armtie'] ?? 0) > 0 ? number_format((float)$sec['qty_armtie'], 0) : '-' ?></td>
                                    <td class="text-end font-monospace text-muted"><?= ($sec['qty_crossarm'] ?? 0) > 0 ? number_format((float)$sec['qty_crossarm'], 0) : '-' ?></td>
                                    <td class="text-end font-monospace text-muted"><?= ($sec['qty_pin_isolator'] ?? 0) > 0 ? number_format((float)$sec['qty_pin_isolator'], 0) : '-' ?></td>
                                    <td class="text-end font-monospace text-muted"><?= ($sec['qty_hang_isolator'] ?? 0) > 0 ? number_format((float)$sec['qty_hang_isolator'], 0) : '-' ?></td>
                                    <td class="text-end font-monospace text-muted"><?= ($sec['qty_fco'] ?? 0) > 0 ? number_format((float)$sec['qty_fco'], 0) : '-' ?></td>
                                    <td class="text-end font-monospace text-muted"><?= ($sec['qty_arrester'] ?? 0) > 0 ? number_format((float)$sec['qty_arrester'], 0) : '-' ?></td>
                                    <td class="text-end font-monospace text-muted"><?= ($sec['qty_skun_konektor'] ?? 0) > 0 ? number_format((float)$sec['qty_skun_konektor'], 0) : '-' ?></td>
                                    <td class="text-end font-monospace text-muted"><?= ($sec['qty_gsw_ground'] ?? 0) > 0 ? number_format((float)$sec['qty_gsw_ground'], 0) : '-' ?></td>
                                    <td class="text-end font-monospace text-muted"><?= ($sec['qty_konduktor_m'] ?? 0) > 0 ? number_format((float)$sec['qty_konduktor_m'], 0) : '-' ?></td>
                                    <td class="small text-muted font-monospace" style="font-size: 9.5px;" title="<?= esc($sMatFull) ?>"><?= esc($sMatStr ?: '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if (empty($bomMatrixRows)): ?>
                            <tr>
                                <td colspan="17" class="text-center text-muted py-4">Belum ada data temuan dan kebutuhan material pada cakupan ini.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold font-monospace" style="font-size: 11.5px;">
                            <tr>
                                <td colspan="6" class="text-end text-dark">TOTAL KEBUTUHAN MATERIAL (SESUAI FILTER):</td>
                                <td class="text-end text-dark" id="bomTotTemuan"><?= number_format($totMatTemuan) ?></td>
                                <td class="text-end text-dark" id="bomTotArmtie"><?= number_format($totMatArmtie, 0) ?></td>
                                <td class="text-end text-dark" id="bomTotCrossarm"><?= number_format($totMatCrossarm, 0) ?></td>
                                <td class="text-end text-dark" id="bomTotPin"><?= number_format($totMatPinIso, 0) ?></td>
                                <td class="text-end text-dark" id="bomTotHang"><?= number_format($totMatHangIso, 0) ?></td>
                                <td class="text-end text-danger" id="bomTotFco"><?= number_format($totMatFco, 0) ?></td>
                                <td class="text-end text-warning-emphasis" id="bomTotArrester"><?= number_format($totMatArrester, 0) ?></td>
                                <td class="text-end text-dark" id="bomTotSkun"><?= number_format($totMatSkun, 0) ?></td>
                                <td class="text-end text-dark" id="bomTotGsw"><?= number_format($totMatGsw, 0) ?></td>
                                <td class="text-end text-primary" id="bomTotCondM"><?= number_format($totMatCondM, 0) ?></td>
                                <td class="text-muted small fw-normal">Rekap terstruktur</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- TAB 2: Rekapitulasi per Kode Material BOM Aktual (master_materials) -->
            <div class="tab-pane fade" id="pane-bom-recap" role="tabpanel">
                <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                        <thead class="table-light sticky-top" style="font-size: 10.5px; text-transform: uppercase;">
                            <tr>
                                <th style="width: 40px;" class="text-center">#</th>
                                <th>Kode Material BOM</th>
                                <th>Uraian Nama Material Standar PLN</th>
                                <th>Kategori</th>
                                <th class="text-center">Spesifikasi Konduktor</th>
                                <th class="text-end">Frekuensi</th>
                                <th class="text-end text-primary" title="Total Kebutuhan Fisik Material Standar PLN">Total Kebutuhan</th>
                                <th class="text-end text-success" title="Material yang Telah Nyata Digunakan pada Realisasi Pekerjaan">Sudah Digunakan</th>
                                <th class="text-end text-danger" title="Sisa Kebutuhan Material (Hanya berkurang bila pemakaian nyata dicatat)">Sisa Kebutuhan</th>
                                <th class="text-center">Satuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grandTotReq = 0.0;
                            $grandTotUsed = 0.0;
                            $grandTotRem = 0.0;
                            foreach ($bomSummaryByMat as $mIdx => $mSum): 
                                $tQty = (float)($mSum['total_qty'] ?? 0);
                                $uQty = (float)($mSum['total_used_qty'] ?? 0);
                                $rQty = (float)($mSum['sisa_qty'] ?? max(0.0, $tQty - $uQty));
                                $grandTotReq += $tQty;
                                $grandTotUsed += $uQty;
                                $grandTotRem += $rQty;
                            ?>
                            <tr>
                                <td class="text-center text-muted fw-bold"><?= $mIdx + 1 ?></td>
                                <td>
                                    <?php if (($mSum['material_code'] ?? '') === 'PERLU_VERIFIKASI'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning font-monospace">
                                            <i class="fas fa-triangle-exclamation me-1"></i>PERLU_VERIFIKASI
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-dark-subtle text-dark font-monospace"><?= esc($mSum['material_code'] ?? '-') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold text-dark"><?= esc($mSum['nama_material'] ?? $mSum['material_name'] ?? '-') ?></td>
                                <td>
                                    <?php if (($mSum['material_code'] ?? '') === 'PERLU_VERIFIKASI'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning">UNMAPPED</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border"><?= esc($mSum['material_category'] ?? $mSum['category'] ?? '-') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center font-monospace small">
                                    <?php if (!empty($mSum['conductor_type'])): ?>
                                        <span class="badge bg-info-subtle text-info-emphasis border"><?= esc($mSum['conductor_type']) ?> <?= esc($mSum['conductor_size'] ?? '') ?> mm²</span>
                                    <?php else: ?>
                                        <span class="text-muted"><?= esc($mSum['nama_lapangan'] ?? 'Standar PLN') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end font-monospace"><?= number_format((int)($mSum['temuan_count'] ?? 0)) ?></td>
                                <td class="text-end fw-bold text-primary font-monospace"><?= number_format($tQty, 0) ?></td>
                                <td class="text-end font-monospace <?= $uQty > 0 ? 'fw-bold text-success' : 'text-muted' ?>">
                                    <?= $uQty > 0 ? number_format($uQty, 0) : '0' ?>
                                </td>
                                <td class="text-end font-monospace <?= $rQty > 0 ? 'fw-bold text-danger' : 'text-success' ?>">
                                    <?= number_format($rQty, 0) ?>
                                </td>
                                <td class="text-center text-muted fw-semibold"><?= esc($mSum['satuan'] ?? $mSum['unit'] ?? 'Bh') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($bomSummaryByMat)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-3">Belum ada rincian transaksi material BOM.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($bomSummaryByMat)): ?>
                        <tfoot class="table-light fw-bold font-monospace" style="font-size: 11.5px;">
                            <tr>
                                <td colspan="6" class="text-end text-dark">TOTAL AKUMULASI MATERIAL:</td>
                                <td class="text-end text-primary"><?= number_format($grandTotReq, 0) ?></td>
                                <td class="text-end text-success"><?= number_format($grandTotUsed, 0) ?></td>
                                <td class="text-end text-danger"><?= number_format($grandTotRem, 0) ?></td>
                                <td class="text-center text-muted">-</td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- TAB 3: Standar BOM Konstruksi JTM (TM-1 s/d TM-11 dari Database) -->
            <div class="tab-pane fade" id="pane-bom-std" role="tabpanel">
                <div class="p-3 bg-light-subtle" style="max-height: 360px; overflow-y: auto;">
                    <div class="row g-3">
                        <?php foreach ($bomConstructions as $ctIdx => $ctData):
                            $ctCode = $ctData['code'] ?? (is_string($ctIdx) ? $ctIdx : 'TM');
                            $ctDesc = $ctData['name'] ?? $ctData['description'] ?? '';
                        ?>
                        <div class="col-xl-6 col-12">
                            <div class="card border shadow-xs h-100">
                                <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center border-bottom">
                                    <div>
                                        <span class="badge bg-primary text-white font-monospace me-1"><?= esc($ctCode) ?></span>
                                        <strong class="text-dark" style="font-size: 12px;"><?= esc($ctDesc) ?></strong>
                                    </div>
                                    <span class="badge bg-light text-muted border" style="font-size: 10px;"><?= count($ctData['items'] ?? []) ?> Item BOM</span>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped mb-0 align-middle" style="font-size: 11px; min-width: 460px;">
                                            <thead class="table-light text-muted text-uppercase" style="font-size: 10px;">
                                                <tr>
                                                    <th style="width: 135px;" class="text-dark fw-bold">Kode Material</th>
                                                    <th class="text-dark fw-bold">Nama Material</th>
                                                    <th style="width: 100px;">Kategori</th>
                                                    <th style="width: 90px;" class="text-end text-dark fw-bold">Qty Standar</th>
                                                    <th style="width: 75px;" class="text-center text-dark fw-bold">Satuan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (($ctData['items'] ?? []) as $bItem): ?>
                                                <tr>
                                                    <td class="font-monospace text-dark fw-bold"><?= esc($bItem['material_code'] ?? $bItem['code'] ?? '-') ?></td>
                                                    <td class="fw-semibold text-dark"><?= esc($bItem['name'] ?? '-') ?></td>
                                                    <td><span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle" style="font-size: 9.5px;"><?= esc($bItem['category'] ?? '-') ?></span></td>
                                                    <td class="text-end fw-bold font-monospace text-primary" style="font-size: 12px;"><?= rtrim(rtrim(number_format((float)($bItem['quantity'] ?? $bItem['qty'] ?? 0), 2), '0'), '.') ?></td>
                                                    <td class="text-center text-dark fw-semibold"><?= esc($bItem['unit'] ?? '-') ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6C. ANALISIS KEANDALAN & GANGGUAN PENYULANG (SUMBER SAMA DENGAN /gangguan) -->
    <!-- Grafik Gangguan Kumulatif (Perbandingan 2 Tahun & Multi-Metrik) -->
    <div class="card dah-card dah-cum-card shadow-sm border mb-3">
        <div class="card-header bg-white py-2 px-3 border-bottom">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-danger-subtle text-danger p-2 rounded-2"><i class="fas fa-chart-line"></i></span>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 13.5px;">Gangguan Kumulatif &amp; Analisis Metrik Keandalan</h6>
                        <small class="text-muted" style="font-size: 11px;">
                            Perbandingan tepat 2 tahun pilihan pengguna &bull; Sumber data dan filter terintegrasi langsung dengan <a href="<?= site_url('gangguan') ?>" class="text-decoration-none fw-semibold">/gangguan</a>
                        </small>
                    </div>
                </div>

                <!-- Year Pair Comparison Selector -->
                <div class="d-flex align-items-center gap-1.5 bg-light p-1 rounded-pill border">
                    <span class="text-muted small fw-bold ps-2" style="font-size: 11px;"><i class="fas fa-calendar-alt me-1"></i>Bandingkan:</span>
                    <select id="yoyYearA" class="form-select form-select-sm py-0 px-2 rounded-pill font-monospace fw-bold text-danger border-danger-subtle" style="font-size: 11px; width: auto; height: 26px;" onchange="updateYoYComparisonChart()">
                        <?php foreach (($availableTrendYears ?? [date('Y')]) as $yrOpt): ?>
                            <option value="<?= $yrOpt ?>" <?= (int)$yrOpt === (int)($trendYear ?? date('Y')) ? 'selected' : '' ?>><?= $yrOpt ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-muted small fw-bold px-0.5">vs</span>
                    <select id="yoyYearB" class="form-select form-select-sm py-0 px-2 rounded-pill font-monospace fw-bold text-primary border-primary-subtle" style="font-size: 11px; width: auto; height: 26px;" onchange="updateYoYComparisonChart()">
                        <?php 
                            $prevYearDef = (int)($gangguanStats['last_year'] ?? (($trendYear ?? date('Y')) - 1));
                            foreach (($availableTrendYears ?? [date('Y') - 1]) as $yrOpt): 
                        ?>
                            <option value="<?= $yrOpt ?>" <?= (int)$yrOpt === $prevYearDef ? 'selected' : '' ?>><?= $yrOpt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Zero-Reload Metric Pills & Dynamic Summary Badges -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
                <div class="btn-group btn-group-sm rounded-pill p-0.5 bg-light border" role="group" id="yoyMetricToggleGroup" style="font-size: 11px;">
                    <button type="button" class="btn btn-xs rounded-pill px-2.5 py-0.5 fw-semibold btn-primary" id="btnMetricTrips" onclick="switchYoYMetric('trips', this)">
                        <i class="fas fa-chart-line me-1"></i>Total Trip Kumulatif
                    </button>
                    <button type="button" class="btn btn-xs rounded-pill px-2.5 py-0.5 fw-semibold btn-light text-dark" id="btnMetricCause" onclick="switchYoYMetric('cause', this)">
                        <i class="fas fa-tags me-1"></i>Kategori Penyebab
                    </button>
                    <button type="button" class="btn btn-xs rounded-pill px-2.5 py-0.5 fw-semibold btn-light text-dark" id="btnMetricRelay" onclick="switchYoYMetric('relay', this)">
                        <i class="fas fa-shield-halved me-1"></i>Proteksi/Rele
                    </button>
                    <button type="button" class="btn btn-xs rounded-pill px-2.5 py-0.5 fw-semibold btn-light text-dark" id="btnMetricCurrent" onclick="switchYoYMetric('current', this)">
                        <i class="fas fa-bolt me-1"></i>Arus Gangguan (A)
                    </button>
                </div>

                <div class="d-flex align-items-center gap-1.5">
                    <span class="badge border rounded-pill px-2.5 py-1 fw-bold font-monospace bg-danger-subtle text-danger border-danger-subtle" id="badgeYearA" style="font-size: 11px;">
                        Tahun A: -
                    </span>
                    <span class="badge border rounded-pill px-2.5 py-1 fw-bold font-monospace bg-primary-subtle text-primary border-primary-subtle" id="badgeYearB" style="font-size: 11px;">
                        Tahun B: -
                    </span>
                    <span class="badge border rounded-pill px-2 py-1 fw-bold font-monospace bg-light text-dark" id="badgeDiffYoY" style="font-size: 11px;">
                        Diff: -
                    </span>
                    <a id="btnDetailYoy" href="<?= site_url('gangguan?year_a=' . (int)($gangguanStats['year'] ?? ($trendYear ?? date('Y'))) . '&year_b=' . (int)($gangguanStats['last_year'] ?? (($trendYear ?? date('Y')) - 1)) . (!empty($selectedUlpId) ? '&ulp_id=' . (int)$selectedUlpId : '')) ?>" class="btn btn-xs btn-outline-danger rounded-pill px-2.5 py-1 fw-semibold ms-1" style="font-size: 11px;" title="Lihat Rekapitulasi Rinci di Halaman Gangguan">
                        <i class="fas fa-arrow-up-right-from-square me-1"></i> Buka /gangguan
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-2.5 d-flex align-items-center justify-content-center">
            <div class="dah-cum-chart-box w-100 position-relative" id="cumulativeChartContainer" style="min-height: 250px;">
                <canvas id="cumulativeGangguanChart"></canvas>
                <div id="chartNoDataOverlay" class="d-none position-absolute top-50 start-50 translate-middle text-center p-3 bg-white bg-opacity-75 rounded border shadow-sm">
                    <i class="fas fa-circle-info text-muted fs-4 mb-1"></i>
                    <div class="small fw-semibold text-dark" id="chartNoDataMsg">Data metrik belum tersedia pada periode ini.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Baris Bawah Gangguan: Penyulang Gangguan Terbanyak (Kiri) & Penyebab Gangguan (Kanan) -->
    <div class="row g-3 mb-4 align-items-stretch">
        <!-- 6D. Penyulang Gangguan Terbanyak -->
        <div class="col-lg-6 col-12">
            <div class="card dah-card shadow-sm border h-100">
                <div class="card-header bg-white py-2.5 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger-subtle text-danger p-2 rounded-2"><i class="fas fa-bolt"></i></span>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 13px;">Penyulang Gangguan Terbanyak</h6>
                            <small class="text-muted" style="font-size: 11px;">Peringkat frekuensi trip tahun <?= (int)($trendYear ?? date('Y')) ?> (Sumber: Rekap Gangguan)</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-1.5">
                        <label for="trendYearSelectGangguan" class="small text-muted fw-semibold mb-0" style="font-size: 11px;">Tahun:</label>
                        <select id="trendYearSelectGangguan" class="form-select form-select-sm rounded-pill font-monospace" style="font-size: 11px; width: auto; padding: 2px 24px 2px 10px;" onchange="changeTrendYear(this.value)">
                            <?php foreach ($availableTrendYears ?? [date('Y')] as $yr): ?>
                                <option value="<?= $yr ?>" <?= ((int)($trendYear ?? date('Y')) === (int)$yr) ? 'selected' : '' ?>>
                                    <?= $yr ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 290px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                            <thead class="table-light sticky-top" style="font-size: 11px; text-transform: uppercase;">
                                <tr>
                                    <th style="width: 35px;" class="text-center">#</th>
                                    <th>Penyulang</th>
                                    <th>ULP</th>
                                    <th class="text-end" style="width: 110px;">Total Gangguan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($topFeedersGangguan ?? []) as $rank => $row): ?>
                                <tr>
                                    <td class="text-center text-muted fw-bold"><?= $rank + 1 ?></td>
                                    <td class="fw-semibold text-dark"><?= esc($row['nama_penyulang'] ?? $row['feeder_name'] ?? '-') ?></td>
                                    <td class="text-muted small"><?= esc($row['nama_ulp'] ?? '-') ?></td>
                                    <td class="text-end fw-bold text-danger font-monospace"><?= (int)($row['cnt'] ?? 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($topFeedersGangguan)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3 small">Tidak ada data gangguan untuk tahun <?= (int)($trendYear ?? date('Y')) ?>.</td>
                                                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6E. Penyebab Gangguan -->
        <?php
            $causeYearsList = !empty($causeBreakdownByYear) ? array_keys($causeBreakdownByYear) : ($availableTrendYears ?? [(int)date('Y')]);
            $initialCauseYear = in_array((int)($trendYear ?? date('Y')), array_map('intval', $causeYearsList), true) ? (int)($trendYear ?? date('Y')) : (int)($causeYearsList[0] ?? date('Y'));
            $rawInitialCause = $causeBreakdownByYear[$initialCauseYear] ?? [];
            if (is_array($rawInitialCause) && array_key_exists('items', $rawInitialCause)) {
                $initialCauseItems = is_array($rawInitialCause['items']) ? $rawInitialCause['items'] : [];
                $initialCauseTotal = isset($rawInitialCause['total_events'])
                    ? (int)$rawInitialCause['total_events']
                    : (int)array_sum(array_map(static fn($r) => (int)($r['count'] ?? $r['total'] ?? 0), $initialCauseItems));
                $initialCauseNote  = $rawInitialCause['source_note'] ?? null;
            } elseif (is_array($rawInitialCause)) {
                $initialCauseItems = array_values($rawInitialCause);
                $initialCauseTotal = (int)array_sum(array_map(static fn($r) => (int)($r['count'] ?? $r['total'] ?? 0), $initialCauseItems));
                $initialCauseNote  = null;
            } else {
                $initialCauseItems = [];
                $initialCauseTotal = 0;
                $initialCauseNote  = null;
            }
        ?>
        <div class="col-lg-6 col-12">
            <div class="card dah-card shadow-sm border h-100">
                <div class="card-header bg-white py-2.5 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-warning-subtle text-warning-emphasis p-2 rounded-2"><i class="fas fa-tags"></i></span>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 13px;">Penyebab Gangguan <span id="causeYearTitleLabel" class="text-primary font-monospace"><?= (int)$initialCauseYear ?></span></h6>
                            <small class="text-muted" style="font-size: 11px;">Klasifikasi sesuai kamus kode gangguan &amp; histori trip</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="causeYearSelect" class="small text-muted fw-semibold mb-0" style="font-size: 11px;">Tahun:</label>
                        <select id="causeYearSelect" aria-label="Pilih Tahun Penyebab Gangguan" class="form-select form-select-sm rounded-pill font-monospace" style="font-size: 11px; width: auto; padding: 2px 24px 2px 10px;" onchange="renderCauseBreakdownByYear(this.value)">
                            <?php foreach ($causeYearsList as $cYr):
                                $cYrEntry = $causeBreakdownByYear[$cYr] ?? [];
                                $cYrHasSource = is_array($cYrEntry) && array_key_exists('has_source', $cYrEntry) ? (bool)$cYrEntry['has_source'] : true;
                            ?>
                                <option value="<?= (int)$cYr ?>" <?= ((int)$cYr === (int)$initialCauseYear) ? 'selected' : '' ?>>
                                    <?= (int)$cYr ?><?= !$cYrHasSource ? ' (Belum Ada Log Detail Penyebab)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span id="causeTotalBadge" class="badge bg-dark-subtle text-dark rounded-pill px-2.5 py-1 fw-bold font-monospace" style="font-size: 11px;"><?= number_format($initialCauseTotal) ?> Kejadian</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 290px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                            <thead class="table-light sticky-top" style="font-size: 10.5px; text-transform: uppercase;">
                                <tr>
                                    <th style="width: 32px;" class="text-center">#</th>
                                    <th>Kode &amp; Klasifikasi Penyebab</th>
                                    <th class="text-center" style="width: 110px;">Status Kamus</th>
                                    <th class="text-end" style="width: 95px;">Jumlah</th>
                                    <th style="width: 110px;">Proporsi</th>
                                </tr>
                            </thead>
                            <tbody id="causeBreakdownTableBody">
                                <?php if (!empty($initialCauseItems)): ?>
                                    <?php foreach ($initialCauseItems as $cIdx => $cRow):
                                        $st = strtoupper((string)($cRow['mapping_status'] ?? 'RESOLVED'));
                                        $stBadge = $st === 'RESOLVED'
                                            ? '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5" style="font-size:10px;">TERPETAKAN</span>'
                                            : ($st === 'PARTIALLY_RESOLVED'
                                                ? '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-0.5" style="font-size:10px;">PARSIAL</span>'
                                                : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-0.5" style="font-size:10px;">REKAP BULANAN</span>');
                                        $rowCount = (int)($cRow['count'] ?? $cRow['total'] ?? 0);
                                        $rowPct   = (float)($cRow['pct'] ?? ($initialCauseTotal > 0 ? round(($rowCount / $initialCauseTotal) * 100, 1) : 0));
                                    ?>
                                    <tr>
                                        <td class="text-center text-muted fw-bold"><?= $cIdx + 1 ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= esc($cRow['label'] ?? '-') ?></div>
                                            <div class="text-muted font-monospace" style="font-size: 10px;">
                                                <?= esc($cRow['canonical_code'] ?? '-') ?>
                                                <?php if (!empty($cRow['raw_samples'])): ?>
                                                    &bull; <span class="text-secondary">Contoh: <?= esc(mb_strlen((string)$cRow['raw_samples']) > 75 ? mb_substr((string)$cRow['raw_samples'], 0, 72) . '...' : (string)$cRow['raw_samples']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center"><?= $stBadge ?></td>
                                        <td class="text-end fw-bold text-dark font-monospace"><?= number_format($rowCount) ?> <span class="text-muted fw-normal" style="font-size:10px;">(<?= number_format($rowPct, 1) ?>%)</span></td>
                                        <td>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar <?= $st === 'UNMAPPED' ? 'bg-secondary' : 'bg-danger' ?>" role="progressbar" style="width: <?= min(100, max(2, $rowPct)) ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3 small">
                                            <?= esc($initialCauseNote ?: ('Belum ada data histori gangguan untuk tahun ' . (int)$initialCauseYear . '.')) ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 7. ROW 5: RINGKASAN JARINGAN & ASET DISTRIBUSI UP3 SIDOARJO (INTERACTIVE MODULE) -->
    <?php
        $networkRows = $networkData['feeders'] ?? [];
        $networkTotals = ['ulp_count' => 0, 'penyulang' => 0, 'aset' => 0, 'aaac_kms' => 0, 'aaacs_kms' => 0, 'mvtic_kms' => 0, 'xlpe_kms' => 0, 'gis' => []];
        $seenNetworkUlps = [];
        foreach (($networkData['ulp_summary'] ?? []) as $networkSummary) {
            if (!empty($selectedUlpId) && (int)($networkSummary['ulp_id'] ?? 0) !== (int)$selectedUlpId) continue;
            if (!empty($networkSummary['ulp_id'])) {
                $seenNetworkUlps[(int)$networkSummary['ulp_id']] = true;
            }
            foreach (['penyulang', 'aset', 'aaac_kms', 'aaacs_kms', 'mvtic_kms', 'xlpe_kms'] as $field) {
                if (isset($networkSummary[$field]) && is_numeric($networkSummary[$field])) {
                    $networkTotals[$field] += $networkSummary[$field];
                }
            }
            $networkTotals['gis'] = array_values(array_unique(array_merge($networkTotals['gis'], $networkSummary['gis'] ?? [])));
        }
        $networkTotals['ulp_count'] = count($seenNetworkUlps) ?: (!empty($selectedUlpId) ? 1 : count($ulpsList ?? []));
        $totalKonduktorKm = (float)$networkTotals['aaac_kms'] + (float)$networkTotals['aaacs_kms'] + (float)$networkTotals['mvtic_kms'] + (float)$networkTotals['xlpe_kms'];
        $unassignedAssets = (int)($networkData['unassigned_assets'] ?? 0);
    ?>
    <div class="card dah-card shadow-sm border mb-4" id="networkSummaryModuleCard">
        <div class="card-header bg-white py-3 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary p-2 rounded-2"><i class="fas fa-network-wired fs-5"></i></span>
                <div>
                    <h5 class="fw-bold text-dark mb-0" style="font-size: 14px;">Ringkasan Aset &amp; Jaringan Distribusi UP3 Sidoarjo</h5>
                    <div class="small text-muted" style="font-size: 11px;">Rekapitulasi Gardu Induk (GI), ULP, Penyulang, Jumlah Aset, dan Panjang Transline Aktif (AAAC, AAACS, MVTIC, XLPE)</div>
                </div>
            </div>
            <!-- Interactive Filter ULP & Penyulang -->
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="d-flex align-items-center gap-1">
                    <label for="netFilterUlpSelect" class="small text-muted fw-semibold mb-0" style="font-size: 11px;">ULP:</label>
                    <select id="netFilterUlpSelect" class="form-select form-select-sm rounded-pill fw-semibold" style="font-size: 11.5px; width: auto; min-width: 155px;" onchange="applyNetworkModuleFilter()">
                        <option value="">Semua ULP (<?= (int)$networkTotals['ulp_count'] ?>)</option>
                        <?php foreach (($ulpsList ?? []) as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= ((int)($selectedUlpId ?? 0) === (int)$u['id']) ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <label for="netFilterFeederSelect" class="small text-muted fw-semibold mb-0" style="font-size: 11px;">Penyulang:</label>
                    <select id="netFilterFeederSelect" class="form-select form-select-sm rounded-pill fw-semibold" style="font-size: 11.5px; width: auto; min-width: 175px;" onchange="applyNetworkModuleFilter()">
                        <option value="">Semua Penyulang (<?= count($networkRows) ?>)</option>
                        <?php foreach ($networkRows as $netOpt): ?>
                            <option value="<?= (int)$netOpt['id'] ?>" data-ulp-id="<?= (int)($netOpt['ulp_id'] ?? 0) ?>"><?= esc($netOpt['nama']) ?> (<?= esc($netOpt['nama_ulp'] ?? '-') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="btn btn-sm btn-light border rounded-pill px-2.5 py-1 text-muted" style="font-size: 11px;" onclick="resetNetworkModuleFilter()" title="Reset Filter Jaringan">
                    <i class="fas fa-rotate-left"></i> Reset
                </button>
            </div>
        </div>
        
        <!-- Summary Stats Ribbon (Dynamically Updated by Filter ULP & Penyulang) -->
        <div class="card-body bg-light-subtle py-3 px-3 border-bottom">
            <div class="row g-2 align-items-stretch">
                <!-- GI & ULP Card -->
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="p-2.5 rounded-3 bg-white border h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                            <span class="text-muted small fw-semibold" style="font-size: 11px;"><i class="fas fa-industry text-primary me-1"></i>Gardu Induk (GI) &amp; ULP</span>
                            <span class="badge bg-dark-subtle text-dark rounded-pill font-monospace" style="font-size: 10.5px;" id="netStatUlpCount"><?= (int)$networkTotals['ulp_count'] ?> ULP</span>
                        </div>
                        <div class="d-flex flex-wrap gap-1" id="netStatGiBadges">
                            <?php if (!empty($networkTotals['gis'])): ?>
                                <?php foreach ($networkTotals['gis'] as $giName): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1" style="font-size: 10.5px;"><?= esc($giName) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted small">–</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Penyulang & Total Aset Card -->
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="p-2.5 rounded-3 bg-white border h-100 d-flex align-items-center justify-content-around text-center">
                        <div>
                            <div class="text-muted small fw-semibold" style="font-size: 11px;">Penyulang</div>
                            <div class="fs-4 fw-bold text-dark font-monospace lh-1 mt-1" id="netStatFeederCount"><?= number_format((int)$networkTotals['penyulang']) ?></div>
                        </div>
                        <div class="vr opacity-25"></div>
                        <div>
                            <div class="text-muted small fw-semibold" style="font-size: 11px;">Aset Mapped</div>
                            <div class="fs-4 fw-bold text-primary font-monospace lh-1 mt-1" id="netStatAssetCount" title="Aset tiang yang sudah terhubung ke penyulang spesifik"><?= number_format((int)$networkTotals['aset']) ?></div>
                        </div>
                        <?php if ($unassignedAssets > 0): ?>
                        <div class="vr opacity-25"></div>
                        <div>
                            <div class="text-warning-emphasis small fw-semibold" style="font-size: 11px;">Pool ULP</div>
                            <div class="fs-4 fw-bold text-warning font-monospace lh-1 mt-1" id="netStatUnassignedCount" title="Aset tiang ULP yang belum terhubung foreign key penyulang (penyulang_id IS NULL)"><?= number_format($unassignedAssets) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Active Conductor Length Breakdown (AAAC, AAACS, MVTIC, XLPE) -->
                <div class="col-xl-6 col-12">
                    <div class="p-2.5 rounded-3 bg-white border h-100">
                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                            <span class="text-muted small fw-semibold" style="font-size: 11px;"><i class="fas fa-bolt text-warning me-1"></i>Panjang Transline Aktif per Jenis Konduktor (km):</span>
                            <span class="badge bg-light text-dark border font-monospace" style="font-size: 10.5px;">Total: <strong id="netStatTotalKm"><?= number_format($totalKonduktorKm, 2) ?></strong> km</span>
                        </div>
                        <div class="row g-1.5 text-center">
                            <div class="col-3">
                                <div class="p-1.5 rounded-2 bg-warning-subtle border border-warning-subtle">
                                    <div class="text-warning-emphasis fw-bold font-monospace" style="font-size: 13px;" id="netStatAaacKm"><?= number_format((float)$networkTotals['aaac_kms'], 2) ?> <small class="fw-normal" style="font-size:10px;">km</small></div>
                                    <div class="text-dark fw-semibold" style="font-size: 10px;">AAAC <span class="badge bg-warning text-dark ms-0.5 px-1 py-0" style="font-size:8.5px;">Bisa Coating</span></div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-1.5 rounded-2 bg-info-subtle border border-info-subtle">
                                    <div class="text-info-emphasis fw-bold font-monospace" style="font-size: 13px;" id="netStatAaacsKm"><?= number_format((float)$networkTotals['aaacs_kms'], 2) ?> <small class="fw-normal" style="font-size:10px;">km</small></div>
                                    <div class="text-muted fw-semibold" style="font-size: 10px;">AAACS</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-1.5 rounded-2 bg-light border">
                                    <div class="text-dark fw-bold font-monospace" style="font-size: 13px;" id="netStatMvticKm"><?= number_format((float)$networkTotals['mvtic_kms'], 2) ?> <small class="fw-normal" style="font-size:10px;">km</small></div>
                                    <div class="text-muted fw-semibold" style="font-size: 10px;">MVTIC</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-1.5 rounded-2 bg-secondary-subtle border border-secondary-subtle">
                                    <div class="text-dark fw-bold font-monospace" style="font-size: 13px;" id="netStatXlpeKm"><?= number_format((float)$networkTotals['xlpe_kms'], 2) ?> <small class="fw-normal" style="font-size:10px;">km</small></div>
                                    <div class="text-muted fw-semibold" style="font-size: 10px;">XLPE</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($unassignedAssets > 0): ?>
        <div class="px-3 py-2 bg-light-subtle border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small text-muted" style="font-size: 11px;">
                <i class="fas fa-circle-info text-primary me-1"></i>
                Total Master Aset UP3: <strong class="text-dark font-monospace"><?= number_format((int)$networkTotals['aset'] + $unassignedAssets) ?></strong> unit
                (<strong class="text-primary font-monospace"><?= number_format((int)$networkTotals['aset']) ?></strong> unit terpetakan ke penyulang, 
                <strong class="text-warning font-monospace"><?= number_format($unassignedAssets) ?></strong> unit pada pool ULP menanti relasi foreign key penyulang).
            </div>
            <a href="<?= site_url('api/audit-asset-integrity') ?>" target="_blank" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 10.5px;" title="Lihat hasil audit forensik aset">
                <i class="fas fa-microscope me-1"></i>Audit Forensik Aset (Read-Only)
            </a>
        </div>
        <?php endif; ?>

        <!-- Feeder Details Table -->
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;" id="networkFeedersTable">
                    <thead class="table-light sticky-top" style="font-size: 11px; text-transform: uppercase;">
                        <tr>
                            <th>Gardu Induk</th>
                            <th>ULP</th>
                            <th>Penyulang</th>
                            <th class="text-end">Jumlah Aset</th>
                            <th class="text-end text-warning-emphasis">AAAC (km)</th>
                            <th class="text-end">AAACS (km)</th>
                            <th class="text-end">MVTIC (km)</th>
                            <th class="text-end">XLPE (km)</th>
                            <th class="text-center" style="width: 145px;">Aksi Peta</th>
                        </tr>
                    </thead>
                    <tbody id="networkFeedersTableBody">
                        <?php foreach ($networkRows as $net): ?>
                        <tr class="net-feeder-row"
                            data-feeder-id="<?= (int)$net['id'] ?>"
                            data-ulp-id="<?= (int)($net['ulp_id'] ?? 0) ?>"
                            data-ulp-name="<?= esc($net['nama_ulp'] ?? '-') ?>"
                            data-gi="<?= esc($net['gardu_induk'] ?? '-') ?>"
                            data-aset="<?= (int)($net['aset'] ?? 0) ?>"
                            data-aaac="<?= (float)($net['aaac_kms'] ?? 0) ?>"
                            data-aaacs="<?= (float)($net['aaacs_kms'] ?? 0) ?>"
                            data-mvtic="<?= (float)($net['mvtic_kms'] ?? 0) ?>"
                            data-xlpe="<?= (float)($net['xlpe_kms'] ?? 0) ?>">
                            <td class="fw-semibold text-dark"><span class="badge bg-light text-dark border"><?= esc($net['gardu_induk'] ?? '-') ?></span></td>
                            <td class="text-muted small"><?= esc($net['nama_ulp'] ?? '-') ?></td>
                            <td>
                                <span class="fw-bold text-dark"><?= esc($net['nama'] ?? '-') ?></span>
                                <?php if (!empty($net['kode'])): ?>
                                    <span class="text-muted small font-monospace ms-1" style="font-size: 10px;">(<?= esc($net['kode']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold font-monospace"><?= number_format((int)$net['aset']) ?></td>
                            <td class="text-end fw-bold text-warning-emphasis font-monospace"><?= number_format((float)$net['aaac_kms'], 2) ?></td>
                            <td class="text-end text-muted font-monospace"><?= number_format((float)$net['aaacs_kms'], 2) ?></td>
                            <td class="text-end text-muted font-monospace"><?= number_format((float)$net['mvtic_kms'], 2) ?></td>
                            <td class="text-end text-muted font-monospace"><?= number_format((float)$net['xlpe_kms'], 2) ?></td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-0.5" href="<?= site_url('gis?penyulang_id=' . (int)$net['id']) ?>" title="Buka Topologi Penyulang di GIS">
                                        <i class="fas fa-map-location-dot me-0.5"></i> GIS
                                    </a>
                                    <?php if (!empty($net['captive_candidates'])): ?>
                                        <a class="btn btn-xs btn-warning rounded-pill px-2 py-0.5 text-dark fw-bold" href="<?= site_url('gis?penyulang_id=' . (int)$net['id'] . '&captive_only=1') ?>" title="Tinjau Titik Potensi Coating AAAC di GIS">
                                            <i class="fas fa-highlighter me-0.5"></i> <?= (int)$net['captive_candidates'] ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$networkRows): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Data penyulang tidak tersedia dalam cakupan ini.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 8. CAPTIVE NON PENUGASAN — POTENSI COATING (AAAC) & KONDUKTOR TIDAK STANDAR (< 150 MM2) -->
    <?php
        $coatingByFeeder   = $coatingSummary['by_feeder'] ?? [];
        $coatingTotalTitik = (int)($coatingSummary['total_titik'] ?? 0);
        $coatingTotalKm    = round(((float)($coatingSummary['total_meter'] ?? 0)) / 1000, 2);
        $coatingFeederCnt  = (int)($coatingSummary['total_penyulang'] ?? count($coatingByFeeder));
        $aaacPoolSpans     = (int)($coatingSummary['aaac_pool_spans'] ?? 0);
        $aaacPoolKms       = (float)($coatingSummary['aaac_pool_kms'] ?? 0.0);
        $aaacTopFeeders    = $coatingSummary['aaac_top_feeders'] ?? [];
        $nonStdTitik       = (int)($coatingSummary['non_std_titik'] ?? 0);
        $nonStdKm          = round(((float)($coatingSummary['non_std_meter'] ?? 0)) / 1000, 2);
        $nonStdRows        = $coatingSummary['non_std_rows'] ?? [];
        $sizeRecap         = $coatingSummary['size_recap'] ?? [];
    ?>
    <div class="card dah-card shadow-sm border mb-4" id="captiveNonPenugasanCard">
        <div class="card-header bg-white py-3 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-warning-subtle text-warning-emphasis p-2 rounded-2"><i class="fas fa-highlighter fs-5"></i></span>
                <div>
                    <h5 class="fw-bold text-dark mb-0" style="font-size: 14px;">Captive Non Penugasan — Potensi Coating AAAC &amp; Konduktor Tidak Standar (&lt; 150 mm²)</h5>
                    <div class="small text-muted" style="font-size: 11px;">
                        Evaluasi segmen transline <strong>AAAC</strong> kandidat coating serta rekapitulasi konduktor ukuran tidak standar (<code>70 mm²</code> &amp; <code>110 mm²</code> vs standar <code>150 mm²</code> &amp; <code>240 mm²</code>)
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <ul class="nav nav-pills gap-1 bg-light p-1 rounded-pill border" role="tablist" style="font-size: 11px;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill py-1 px-2.5 fw-semibold" data-bs-toggle="pill" data-bs-target="#pane-captive-coating" type="button" role="tab">
                            <i class="fas fa-brush me-1"></i>Potensi Coating AAAC
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill py-1 px-2.5 fw-semibold" data-bs-toggle="pill" data-bs-target="#pane-captive-nonstd" type="button" role="tab">
                            <i class="fas fa-triangle-exclamation text-danger me-1"></i>Konduktor Tidak Standar &lt; 150 mm² (<?= number_format($nonStdTitik) ?> Segmen)
                        </button>
                    </li>
                </ul>
                <a class="btn btn-sm btn-warning text-dark fw-semibold rounded-pill px-3" href="<?= site_url('gis?captive_only=1' . (!empty($selectedUlpId) ? '&ulp_id=' . (int)$selectedUlpId : '')) ?>">
                    <i class="fas fa-map-location-dot me-1"></i> Tinjau di GIS
                </a>
                <a class="btn btn-sm btn-outline-primary rounded-pill px-3" href="<?= site_url('captive-non-penugasan') ?>">
                    <i class="fas fa-list-check me-1"></i> Kelola Captive &amp; Coating
                </a>
            </div>
        </div>

        <div class="card-body bg-light-subtle py-3 px-3 border-bottom">
            <div class="row g-2">
                <div class="col-xl-3 col-sm-6 col-12">
                    <div class="p-2.5 rounded-3 bg-white border h-100 d-flex align-items-center gap-2.5">
                        <div class="rounded-2 bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                            <i class="fas fa-brush"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold" style="font-size: 11px;">Titik AAAC Ditandai Coating</div>
                            <div class="fs-5 fw-bold text-dark font-monospace lh-1 mt-0.5"><?= number_format($coatingTotalTitik) ?> <small class="text-muted fw-normal" style="font-size:11px;">Segmen (<?= number_format($coatingTotalKm, 2) ?> km)</small></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 col-12">
                    <div class="p-2.5 rounded-3 bg-white border h-100 d-flex align-items-center gap-2.5">
                        <div class="rounded-2 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                            <i class="fas fa-route"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold" style="font-size: 11px;">Populasi Transline AAAC Aktif</div>
                            <div class="fs-5 fw-bold text-success font-monospace lh-1 mt-0.5"><?= number_format($aaacPoolKms, 2) ?> <small class="text-muted fw-normal" style="font-size:11px;">km (<?= number_format($aaacPoolSpans) ?> segmen)</small></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 col-12">
                    <div class="p-2.5 rounded-3 bg-white border h-100 d-flex align-items-center gap-2.5">
                        <div class="rounded-2 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                            <i class="fas fa-ruler-combined"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold" style="font-size: 11px;">Konduktor Tidak Standar (&lt;150 mm²)</div>
                            <div class="fs-5 fw-bold text-danger font-monospace lh-1 mt-0.5"><?= number_format($nonStdKm, 2) ?> <small class="text-muted fw-normal" style="font-size:11px;">km (<?= number_format($nonStdTitik) ?> segmen)</small></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 col-12">
                    <div class="p-2 rounded-3 bg-white border h-100 d-flex flex-column justify-content-center">
                        <div class="text-muted small fw-semibold mb-1" style="font-size: 10.5px;">Distribusi Ukuran Penampang (70 / 110 / 150 / 240 mm²):</div>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach (['70', '110', '150', '240'] as $szKey):
                                $szData = $sizeRecap[$szKey] ?? ['kms' => 0, 'temuan_cnt' => 0, 'is_standard' => ((int)$szKey >= 150)];
                                $isStd  = (bool)($szData['is_standard'] ?? $szData['is_standar'] ?? ((int)$szKey >= 150));
                                $szCls  = $isStd ? 'bg-success-subtle text-success border-success-subtle' : 'bg-danger-subtle text-danger border-danger-subtle';
                            ?>
                                <span class="badge border <?= $szCls ?> font-monospace" style="font-size: 9.5px;" title="Temuan pada ukuran <?= $szKey ?> mm²: <?= (int)$szData['temuan_cnt'] ?>">
                                    <?= $szKey ?>mm²: <?= number_format((float)$szData['kms'], 1) ?>km (<?= (int)$szData['temuan_cnt'] ?>T)
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-content">
            <!-- Pane 1: Potensi Coating AAAC -->
            <div class="tab-pane fade show active" id="pane-captive-coating" role="tabpanel">
                <div class="card-body p-0">
                    <?php if (!empty($coatingByFeeder)): ?>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                                <thead class="table-light sticky-top" style="font-size: 11px; text-transform: uppercase;">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">#</th>
                                        <th>ULP</th>
                                        <th>Penyulang</th>
                                        <th class="text-center">Konduktor</th>
                                        <th class="text-end">Titik Bisa Coating</th>
                                        <th class="text-end">Total Jarak Coating</th>
                                        <th class="text-center" style="width: 170px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($coatingByFeeder as $idx => $cf):
                                        $cfMeter = (float)($cf['total_meter'] ?? 0);
                                        $cfKm    = $cfMeter / 1000.0;
                                    ?>
                                    <tr>
                                        <td class="text-center text-muted fw-bold"><?= $idx + 1 ?></td>
                                        <td class="text-muted small"><?= esc($cf['nama_ulp'] ?? '-') ?></td>
                                        <td class="fw-bold text-dark"><?= esc($cf['nama_penyulang'] ?? '-') ?></td>
                                        <td class="text-center"><span class="badge bg-warning-subtle text-dark border border-warning-subtle font-monospace">AAAC</span></td>
                                        <td class="text-end fw-bold text-warning-emphasis font-monospace"><?= number_format((int)($cf['total_titik'] ?? 0)) ?> titik</td>
                                        <td class="text-end fw-bold text-info font-monospace"><?= number_format($cfKm, 2) ?> km <span class="text-muted fw-normal small">(<?= number_format($cfMeter, 0) ?> m)</span></td>
                                        <td class="text-center">
                                            <a class="btn btn-xs btn-warning text-dark fw-semibold rounded-pill px-2.5 py-1" href="<?= site_url('gis?penyulang_id=' . (int)$cf['penyulang_id'] . '&captive_only=1') ?>">
                                                <i class="fas fa-map-location-dot me-1"></i> Tinjau di GIS
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-3">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-2 mb-2 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2.5 py-1" style="font-size: 11px;">
                                        <i class="fas fa-circle-info me-1"></i> 0 Titik Ditandai Potensi Coating
                                    </span>
                                    <span class="text-muted small" style="font-size: 11.5px;">
                                        Belum ada segmen AAAC yang ditandai <code>POTENSI COATING</code>. Berikut daftar penyulang dengan populasi transline <strong>AAAC aktif</strong> siap ditinjau &amp; ditandai di GIS:
                                    </span>
                                </div>
                            </div>
                            <?php if (!empty($aaacTopFeeders)): ?>
                                <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                                        <thead class="table-light sticky-top" style="font-size: 10.5px; text-transform: uppercase;">
                                            <tr>
                                                <th style="width: 36px;" class="text-center">#</th>
                                                <th>ULP</th>
                                                <th>Penyulang</th>
                                                <th class="text-center">Status Coating</th>
                                                <th class="text-end">Segmen AAAC Aktif</th>
                                                <th class="text-end">Panjang AAAC (km)</th>
                                                <th class="text-center" style="width: 185px;">Tindakan di GIS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($aaacTopFeeders as $aIdx => $af): ?>
                                            <tr>
                                                <td class="text-center text-muted fw-bold"><?= $aIdx + 1 ?></td>
                                                <td class="text-muted small"><?= esc($af['nama_ulp'] ?? '-') ?></td>
                                                <td>
                                                    <span class="fw-bold text-dark"><?= esc($af['nama'] ?? '-') ?></span>
                                                    <?php if (!empty($af['gardu_induk'])): ?>
                                                        <span class="badge bg-light text-muted border ms-1" style="font-size: 9.5px;"><?= esc($af['gardu_induk']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5" style="font-size: 10px;">Default: Tidak Bisa Coating</span>
                                                </td>
                                                <td class="text-end fw-semibold font-monospace"><?= number_format((int)($af['aaac_spans'] ?? 0)) ?> segmen</td>
                                                <td class="text-end fw-bold text-warning-emphasis font-monospace"><?= number_format((float)($af['aaac_kms'] ?? 0), 2) ?> km</td>
                                                <td class="text-center">
                                                    <a class="btn btn-xs btn-outline-warning text-dark fw-semibold rounded-pill px-2.5 py-0.5" href="<?= site_url('gis?penyulang_id=' . (int)$af['id'] . '&captive_only=1') ?>" title="Buka Penyulang di GIS untuk Menandai Segmen AAAC">
                                                        <i class="fas fa-highlighter text-warning me-1"></i> Buka &amp; Tandai di GIS
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-muted small py-2">Tidak ada transline AAAC aktif pada cakupan ULP ini.</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pane 2: Konduktor Tidak Standar (< 150 mm²: 70 mm² & 110 mm²) -->
            <div class="tab-pane fade" id="pane-captive-nonstd" role="tabpanel">
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                            <thead class="table-light sticky-top" style="font-size: 10.5px; text-transform: uppercase;">
                                <tr>
                                    <th style="width: 36px;" class="text-center">#</th>
                                    <th>Gardu Induk (GI)</th>
                                    <th>ULP</th>
                                    <th>Penyulang</th>
                                    <th class="text-center">Jenis &amp; Ukuran Konduktor</th>
                                    <th class="text-center">Status Standar</th>
                                    <th class="text-end">Jumlah Segmen</th>
                                    <th class="text-end">Panjang Jaringan</th>
                                    <th class="text-center" style="width: 150px;">Aksi Peta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($nonStdRows as $nsIdx => $nsRow):
                                    $nsKm = ((float)($nsRow['total_meter'] ?? 0)) / 1000.0;
                                ?>
                                <tr>
                                    <td class="text-center text-muted fw-bold"><?= $nsIdx + 1 ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= esc($nsRow['gardu_induk'] ?? '-') ?></span></td>
                                    <td class="text-muted small"><?= esc($nsRow['nama_ulp'] ?? '-') ?></td>
                                    <td class="fw-bold text-dark"><?= esc($nsRow['nama_penyulang'] ?? '-') ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace">
                                            <?= esc($nsRow['conductor_type'] ?? 'AAAC') ?> <?= esc($nsRow['conductor_size'] ?? '70') ?> mm²
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-0.5" style="font-size: 10px;">
                                            Non-Standar (&lt; 150 mm²)
                                        </span>
                                    </td>
                                    <td class="text-end fw-semibold font-monospace"><?= number_format((int)($nsRow['total_titik'] ?? 0)) ?> segmen</td>
                                    <td class="text-end fw-bold text-danger font-monospace"><?= number_format($nsKm, 2) ?> km <span class="text-muted fw-normal small">(<?= number_format((float)($nsRow['total_meter'] ?? 0), 0) ?> m)</span></td>
                                    <td class="text-center">
                                        <a class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-0.5" href="<?= site_url('gis?penyulang_id=' . (int)($nsRow['penyulang_id'] ?? 0)) ?>">
                                            <i class="fas fa-map-location-dot me-1"></i> Lihat di GIS
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($nonStdRows)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4 small">Tidak ditemukan segmen konduktor di bawah 150 mm² pada cakupan ULP ini.</td>
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
<!-- Modal: Daftar Penyulang Berdasarkan Status -->
<div class="modal fade" id="feederStatusModal" tabindex="-1" aria-labelledby="feederStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div>
                    <h5 class="modal-title fw-bold text-dark mb-1" id="feederStatusModalLabel">
                        <i class="fas fa-list-check text-primary me-2"></i>Daftar Penyulang &bull; <span id="modalStatusBadge"></span>
                    </h5>
                    <small class="text-muted" id="modalStatusRuleText"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                        <thead class="table-light text-muted sticky-top" style="font-size: 11px; text-transform: uppercase;">
                            <tr>
                                <th style="width: 35px;">#</th>
                                <th>Penyulang</th>
                                <th>ULP</th>
                                <th class="text-center" title="Emergency Belum Selesai">EMG</th>
                                <th class="text-center" title="High Belum Selesai">HIGH</th>
                                <th class="text-center" title="Medium Belum Selesai">MED</th>
                                <th class="text-center" title="Total Belum Selesai">Belum</th>
                                <th class="text-center" title="Total Akumulasi Temuan">Total</th>
                                <th class="text-center">Status</th>
                                <th class="text-center" style="width: 90px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="modalFeederListBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 border-top bg-light">
                <span class="text-muted small me-auto" id="modalFeederCountText">0 penyulang</span>
                <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Detail Lengkap Penyulang (Drill-down dari Top 10) -->
<div class="modal fade" id="feederDetailModal" tabindex="-1" aria-labelledby="feederDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h5 class="modal-title fw-bold text-dark mb-0" id="detailModalFeederName">Penyulang</h5>
                        <span id="detailModalFeederBadge"></span>
                    </div>
                    <small class="text-muted" id="detailModalFeederUlp">UP3 Sidoarjo</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Health Score Summary -->
                <div class="p-3 rounded-3 bg-light border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small">Asset Health Score</span>
                        <strong class="text-dark" id="detailModalHealthScore">0%</strong>
                    </div>
                    <div class="progress" style="height: 6px; border-radius: 3px;">
                        <div class="progress-bar rounded-pill" id="detailModalHealthBar" role="progressbar" style="width: 0%;"></div>
                    </div>
                    <small class="text-muted d-block mt-2" style="font-size: 10px;" id="detailModalStatusRuleDesc"></small>
                </div>

                <!-- Breakdown Temuan Belum Selesai -->
                <h6 class="fw-bold text-dark mb-2" style="font-size: 13px;">Rincian Temuan Belum Selesai</h6>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="p-2 border rounded text-center bg-danger-subtle border-danger-subtle">
                            <span class="text-danger fw-black d-block fs-5" id="detailModalEmg">0</span>
                            <small class="text-danger fw-semibold" style="font-size: 10px;">Emergency</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded text-center bg-warning-subtle border-warning-subtle">
                            <span class="text-warning fw-black d-block fs-5" id="detailModalHigh">0</span>
                            <small class="text-warning fw-semibold" style="font-size: 10px;">High</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded text-center bg-info-subtle border-info-subtle">
                            <span class="text-info fw-black d-block fs-5" id="detailModalMed">0</span>
                            <small class="text-info fw-semibold" style="font-size: 10px;">Medium</small>
                        </div>
                    </div>
                </div>

                <div class="p-3 border rounded bg-white" style="font-size: 12px;">
                    <div class="d-flex justify-content-between py-1 border-bottom border-light">
                        <span class="text-muted">Total Temuan Belum Selesai:</span>
                        <strong class="text-danger font-monospace" id="detailModalTotalBelum">0</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom border-light">
                        <span class="text-muted">Total Temuan Sudah Selesai:</span>
                        <strong class="text-success font-monospace" id="detailModalTotalSelesai">0</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Akumulasi Seluruh Temuan:</span>
                        <strong class="text-dark font-monospace" id="detailModalTotalAll">0</strong>
                    </div>
                </div>

                <!-- Rincian Pelaksana & Prioritas (Kanonik Temuan) -->
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0" style="font-size: 13px;">
                            <i class="fas fa-users-gear text-primary me-1"></i>Rincian Menurut Pelaksana & Prioritas
                        </h6>
                        <small class="text-muted" style="font-size: 10px;">Data Inspeksi Fisik</small>
                    </div>
                    <div class="table-responsive border rounded-3" style="max-height: 200px;">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 11px;">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Pelaksana</th>
                                    <th class="text-center" style="width: 45px;">EMG</th>
                                    <th class="text-center" style="width: 45px;">HIGH</th>
                                    <th class="text-center" style="width: 45px;">MED</th>
                                    <th class="text-center" style="width: 45px;">LOW</th>
                                    <th class="text-center" style="width: 55px;">Belum</th>
                                    <th class="text-center" style="width: 50px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="detailModalPelaksanaTableBody">
                                <!-- Populated dynamically by openFeederDetailModal -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 border-top bg-light d-flex gap-2">
                <a href="#" id="detailModalBtnTemuan" class="btn btn-sm btn-primary rounded-pill flex-grow-1">
                    <i class="fas fa-list-check me-1"></i>Daftar Temuan
                </a>
                <button type="button" id="detailModalBtnGis" class="btn btn-sm btn-outline-info rounded-pill">
                    <i class="fas fa-map-marked-alt me-1"></i>Fokus di GIS
                </button>
                <button type="button" class="btn btn-sm btn-secondary rounded-pill" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- FOOTER RUNTIME SCRIPTS FOR CHARTS & GIS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Live Clock
    setInterval(function() {
        var now = new Date();
        var timeStr = String(now.getHours()).padStart(2, '0') + ':' +
                      String(now.getMinutes()).padStart(2, '0') + ':' +
                      String(now.getSeconds()).padStart(2, '0') + ' WIB';
        var clockEl = document.getElementById('dah-live-clock');
        if (clockEl) clockEl.textContent = timeStr;
    }, 1000);

    // 2. Feeder Status Donut Chart with onClick Drill-Down
    var donutCtx = document.getElementById('feederStatusDonutChart');
    if (donutCtx) {
        new Chart(donutCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Sehat', 'Kronis', 'Sakit', 'Kritis'],
                datasets: [{
                    data: [<?= $sehatCount ?>, <?= $kronisCount ?>, <?= $sakitCount ?>, <?= $kritisCount ?>],
                    backgroundColor: ['#22c55e', '#f59e0b', '#f97316', '#ef4444'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ctx.label + ': ' + ctx.raw + ' Penyulang (Klik untuk daftar)';
                            }
                        }
                    }
                },
                onClick: function(evt, elements) {
                    if (!elements || !elements.length) return;
                    var statusMap = ['SEHAT', 'KRONIS', 'SAKIT', 'KRITIS'];
                    var idx = elements[0].index;
                    if (statusMap[idx]) {
                        showFeederListByStatus(statusMap[idx]);
                    }
                }
            }
        });
    }

    // 3. Monthly Trend Line Chart (Real DB data)
    var trendCtx = document.getElementById('monthlyTrendChart');
    if (trendCtx) {
        <?php
            // Build 12-slot monthly array for selected trend year
            $selectedTrendYear = (int)($trendYear ?? date('Y'));
            $monthlySlots = array_fill(1, 12, 0);
            foreach (($monthlyData ?? []) as $md) {
                $parts = explode('-', $md['bulan'] ?? '');
                if (count($parts) === 2 && (int)$parts[0] === $selectedTrendYear) {
                    $monthlySlots[(int)$parts[1]] = (int)($md['total'] ?? 0);
                }
            }
            $monthlyValues = array_values($monthlySlots);
        ?>
        new Chart(trendCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                datasets: [{
                    label: 'Jumlah Temuan (<?= $selectedTrendYear ?>)',
                    data: <?= json_encode($monthlyValues) ?>,
                    borderColor: '#0284c7',
                    backgroundColor: 'rgba(2, 132, 199, 0.08)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 5,
                    pointBackgroundColor: '#0284c7',
                    pointHoverRadius: 8,
                    pointHoverBackgroundColor: '#0369a1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onClick: function(evt, elements) {
                    if (elements && elements.length > 0) {
                        var clickedMonth = elements[0].index + 1;
                        var targetUrl = '<?= site_url('temuan') ?>?year=<?= $selectedTrendYear ?>&month=' + clickedMonth + '<?= !empty($selectedUlpId) ? ('&ulp_id=' . $selectedUlpId) : '' ?>';
                        window.location.href = targetUrl;
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function() {
                                return 'Klik untuk buka temuan bulan ini';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(226, 232, 240, 0.6)' },
                        ticks: { font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
    }

    window.changeTrendYear = function(newYear) {
        var url = new URL(window.location.href);
        url.searchParams.set('trend_year', newYear);
        window.location.href = url.toString();
    };


    // 4. GIS Network Health Leaflet Map
    var mapContainer = document.getElementById('dahGisMap');
    var map = null;
    var markerLayers = [];

    if (mapContainer && typeof L !== 'undefined') {
        map = L.map('dahGisMap', {
            zoomControl: false,
            attributionControl: false
        }).setView([-7.4726, 112.6675], 11);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18
        }).addTo(map);

        L.control.zoom({ position: 'bottomright' }).addTo(map);

        // Real temuan pins from DB (prioritas, status, and linked feeder)
        <?php
            $safePins = array_map(function($p) {
                return [
                    'id'           => (int)$p['id'],
                    'label'        => (string)($p['nomor_temuan'] ?? 'T-' . $p['id']),
                    'lat'          => (float)$p['latitude'],
                    'lng'          => (float)$p['longitude'],
                    'status'       => strtoupper((string)($p['status'] ?? 'BELUM')),
                    'prio'         => strtoupper((string)($p['prioritas'] ?? 'MEDIUM')),
                    'penyulang_id' => (int)($p['penyulang_id'] ?? 0),
                    'penyulang'    => (string)($p['nama_penyulang'] ?? '-'),
                    'detail'       => mb_substr((string)($p['detail_temuan'] ?? $p['alamat'] ?? ''), 0, 100, 'UTF-8'),
                ];
            }, $mapPins ?? []);
            $pinsJson = json_encode($safePins, JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_UNESCAPED_SLASHES);
            if ($pinsJson === false || empty($pinsJson)) {
                $pinsJson = '[]';
            }
        ?>
        var mapPins = <?= $pinsJson ?>;

        mapPins.forEach(function(f) {
            var color = '#0ea5e9'; // default = MEDIUM
            if (f.prio === 'EMERGENCY') color = '#ef4444';
            else if (f.prio === 'HIGH') color = '#f97316';
            else if (f.prio === 'LOW') color = '#22c55e';
            var radius = f.prio === 'EMERGENCY' ? 9 : (f.prio === 'HIGH' ? 7 : 5);

            var circle = L.circleMarker([f.lat, f.lng], {
                radius: radius,
                fillColor: color,
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.85
            }).addTo(map);

            circle.feederId = f.penyulang_id;
            circle.pinData = f;
            markerLayers.push(circle);

            circle.on('click', function() {
                updateGisPopupCard(f);
            });
        });
    }

    function updateGisPopupCard(f) {
        var titleEl  = document.getElementById('popupFeederTitle');
        var badgeEl  = document.getElementById('popupFeederBadge');
        var nameEl   = document.getElementById('popupFeederName');
        var prioEl   = document.getElementById('popupFeederPrio');
        var statusEl = document.getElementById('popupFeederStatus');
        var detailEl = document.getElementById('popupFeederDetail');
        var linkEl   = document.getElementById('popupFeederLink');

        if (titleEl) titleEl.textContent = 'Temuan ' + f.label;
        if (badgeEl) {
            badgeEl.textContent = f.prio;
            badgeEl.className = f.prio === 'EMERGENCY' ? 'badge bg-danger' : (f.prio === 'HIGH' ? 'badge bg-warning text-dark' : 'badge bg-info');
        }
        if (nameEl) nameEl.textContent = f.penyulang || 'Tanpa Penyulang';
        if (prioEl) {
            prioEl.textContent = f.prio;
            prioEl.className = f.prio === 'EMERGENCY' ? 'text-danger' : (f.prio === 'HIGH' ? 'text-warning' : 'text-primary');
        }
        if (statusEl) {
            statusEl.textContent = f.status === 'SELESAI' ? 'Selesai' : 'Belum Selesai';
            statusEl.className = f.status === 'SELESAI' ? 'text-success' : 'text-danger';
        }
        if (detailEl) detailEl.textContent = f.detail || '-';
        if (linkEl) {
            linkEl.href = '<?= site_url('temuan') ?>?id=' + f.id;
            linkEl.textContent = 'Buka Temuan ' + f.label + ' →';
        }
    }

    function isSidoarjoCoord(lat, lng) {
        return (typeof lat === 'number' && typeof lng === 'number' && !isNaN(lat) && !isNaN(lng) && lat >= -8.2 && lat <= -7.0 && lng >= 112.2 && lng <= 113.3);
    }

    // 5. Global Helpers: Highlight Feeder on Map
    window.highlightFeederOnMap = function(feederId, feederName) {
        if (!map || !markerLayers || !markerLayers.length) return;
        var validMatched = [];
        var outlierCount = 0;
        var totalMatched = 0;

        markerLayers.forEach(function(m) {
            if (m.feederId === parseInt(feederId)) {
                totalMatched++;
                var ll = m.getLatLng();
                if (isSidoarjoCoord(ll.lat, ll.lng)) {
                    validMatched.push(m);
                    m.setStyle({ weight: 4, color: '#f59e0b', opacity: 1, fillOpacity: 0.95 });
                    m.bringToFront();
                } else {
                    outlierCount++;
                    m.setStyle({ weight: 1, color: '#94a3b8', opacity: 0.3, fillOpacity: 0.2 });
                }
            } else {
                m.setStyle({ weight: 1, color: '#ffffff', opacity: 0.35, fillOpacity: 0.25 });
            }
        });

        var gisCard = document.getElementById('dahGisMap');
        if (gisCard) gisCard.scrollIntoView({ behavior: 'smooth', block: 'center' });

        if (validMatched.length > 0) {
            map.invalidateSize();
            setTimeout(function() {
                var group = L.featureGroup(validMatched);
                map.fitBounds(group.getBounds().pad(0.3), { maxZoom: 16 });
                setTimeout(function() { map.invalidateSize(); }, 300);
            }, 100);

            var titleEl  = document.getElementById('popupFeederTitle');
            var badgeEl  = document.getElementById('popupFeederBadge');
            var nameEl   = document.getElementById('popupFeederName');
            var detailEl = document.getElementById('popupFeederDetail');
            var linkEl   = document.getElementById('popupFeederLink');

            if (titleEl) titleEl.textContent = 'Fokus Penyulang';
            if (badgeEl) { badgeEl.textContent = 'FEEDER'; badgeEl.className = 'badge bg-primary'; }
            if (nameEl) nameEl.textContent = feederName;
            
            var detailText = 'Menampilkan ' + validMatched.length + ' titik temuan aktif untuk ' + feederName;
            if (outlierCount > 0) {
                detailText += ' (' + outlierCount + ' titik dengan koordinat outlier di luar wilayah tidak disertakan dalam fokus)';
            }
            if (detailEl) detailEl.textContent = detailText;
            if (linkEl) {
                linkEl.href = '<?= site_url('temuan') ?>?penyulang_id=' + feederId;
                linkEl.textContent = 'Buka Semua Temuan ' + feederName + ' →';
            }
        } else {
            if (outlierCount > 0) {
                alert('Penyulang ' + feederName + ' memiliki ' + outlierCount + ' temuan, tetapi koordinatnya berada di luar batas wilayah valid (outlier/kesalahan input GPS). Silakan perbaiki koordinat pada menu Temuan.');
            } else {
                alert('Penyulang ' + feederName + ' belum memiliki koordinat titik temuan di peta.');
            }
        }
    };


    // 6. Global Helpers: Show Feeder List by Status Modal
    window.allFeedersData = <?= json_encode($allFeeders, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;

    window.showFeederListByStatus = function(status) {
        var modalEl = document.getElementById('feederStatusModal');
        if (!modalEl) return;
        var filtered = [];
        if (status === 'ALL') {
            filtered = window.allFeedersData;
        } else {
            filtered = window.allFeedersData.filter(function(f) {
                return (f.status || '').toUpperCase() === status.toUpperCase();
            });
        }

        var badgeMap = {
            'SEHAT': { text: 'SEHAT', cls: 'bg-success', desc: 'Penyulang tanpa temuan Emergency aktif (0 Emergency)' },
            'KRONIS': { text: 'KRONIS', cls: 'bg-warning text-dark', desc: 'Penyulang dengan 1 temuan Emergency belum selesai' },
            'SAKIT': { text: 'SAKIT', cls: 'bg-orange text-white', desc: 'Penyulang dengan 2 temuan Emergency belum selesai' },
            'KRITIS': { text: 'KRITIS', cls: 'bg-danger text-white', desc: 'Penyulang dengan ≥ 3 temuan Emergency belum selesai' },
            'ALL': { text: 'SEMUA STATUS', cls: 'bg-primary text-white', desc: 'Daftar seluruh penyulang distribusi' }
        };

        var info = badgeMap[status] || { text: status, cls: 'bg-secondary', desc: '' };
        document.getElementById('modalStatusBadge').innerHTML = '<span class="badge ' + info.cls + '">' + info.text + '</span>';
        document.getElementById('modalStatusRuleText').textContent = info.desc;
        document.getElementById('modalFeederCountText').textContent = filtered.length + ' penyulang ditemukan';

        var tbody = document.getElementById('modalFeederListBody');
        tbody.innerHTML = '';

        if (!filtered.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">Tidak ada penyulang dengan status ' + status + '.</td></tr>';
        } else {
            filtered.forEach(function(r, idx) {
                var tr = document.createElement('tr');
                var statusCls = r.status === 'KRITIS' ? 'badge-kritis' : (r.status === 'SAKIT' ? 'badge-sakit' : (r.status === 'KRONIS' ? 'badge-kronis' : 'badge-sehat'));
                var temuanUrl = '<?= site_url('temuan') ?>?penyulang_id=' + r.id;
                tr.innerHTML = '<td class="text-muted">' + (idx + 1) + '</td>' +
                    '<td class="fw-semibold text-dark">' + escHtml(r.nama_penyulang) + '</td>' +
                    '<td class="text-muted">' + escHtml(r.nama_ulp || 'UP3 Sidoarjo') + '</td>' +
                    '<td class="text-center"><span class="badge ' + (r.emg > 0 ? 'bg-danger' : 'bg-light text-muted border') + '">' + r.emg + '</span></td>' +
                    '<td class="text-center"><span class="badge ' + (r.high > 0 ? 'bg-warning text-dark' : 'bg-light text-muted border') + '">' + r.high + '</span></td>' +
                    '<td class="text-center"><span class="badge ' + (r.med > 0 ? 'bg-info-subtle text-info border' : 'bg-light text-muted border') + '">' + r.med + '</span></td>' +
                    '<td class="text-center fw-bold text-dark font-monospace">' + r.belum_selesai + '</td>' +
                    '<td class="text-center fw-bold text-muted font-monospace">' + r.total_temuan + '</td>' +
                    '<td class="text-center"><span class="' + statusCls + '">' + r.status + '</span></td>' +
                    '<td class="text-center">' +
                    '  <a href="' + temuanUrl + '" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-0" title="Buka Temuan"><i class="fas fa-arrow-up-right-from-square"></i></a>' +
                    '</td>';
                tbody.appendChild(tr);
            });
        }

        var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    };

    // 7. Global Helpers: Open Feeder Detail Modal from Top 10
    window.openFeederDetailModal = function(f) {
        var modalEl = document.getElementById('feederDetailModal');
        if (!modalEl || !f) return;

        document.getElementById('detailModalFeederName').textContent = f.nama_penyulang;
        document.getElementById('detailModalFeederUlp').textContent = f.nama_ulp || 'UP3 Sidoarjo';

        var statusCls = f.status === 'KRITIS' ? 'badge-kritis' : (f.status === 'SAKIT' ? 'badge-sakit' : (f.status === 'KRONIS' ? 'badge-kronis' : 'badge-sehat'));
        document.getElementById('detailModalFeederBadge').innerHTML = '<span class="' + statusCls + '">' + f.status + '</span>';

        var score = f.health_score || 0;
        document.getElementById('detailModalHealthScore').textContent = score + '%';
        var bar = document.getElementById('detailModalHealthBar');
        bar.style.width = score + '%';
        bar.className = 'progress-bar rounded-pill ' + (score >= 80 ? 'bg-success' : (score >= 60 ? 'bg-warning' : (score >= 40 ? 'bg-orange' : 'bg-danger')));

        var ruleDesc = 'Status ' + f.status + ' ditentukan dari temuan Emergency belum selesai: ' + (f.emg || 0) + ' temuan.';
        document.getElementById('detailModalStatusRuleDesc').textContent = ruleDesc;

        document.getElementById('detailModalEmg').textContent = f.emg || 0;
        document.getElementById('detailModalHigh').textContent = f.high || 0;
        document.getElementById('detailModalMed').textContent = f.med || 0;
        document.getElementById('detailModalTotalBelum').textContent = f.belum_selesai || 0;
        document.getElementById('detailModalTotalSelesai').textContent = f.selesai || Math.max(0, (f.total_temuan - f.belum_selesai));
        document.getElementById('detailModalTotalAll').textContent = f.total_temuan || 0;

        var pelBody = document.getElementById('detailModalPelaksanaTableBody');
        if (pelBody) {
            pelBody.innerHTML = '';
            var pList = f.pelaksana_breakdown || [];
            if (pList.length > 0) {
                pList.forEach(function(p) {
                    var tr = document.createElement('tr');
                    var emgBadge = p.emg > 0 ? '<span class="badge bg-danger rounded-pill px-1">' + p.emg + '</span>' : '<span class="text-muted">-</span>';
                    var highBadge = p.high > 0 ? '<span class="badge bg-warning text-dark rounded-pill px-1">' + p.high + '</span>' : '<span class="text-muted">-</span>';
                    var medBadge = p.med > 0 ? '<span class="badge bg-info-subtle text-info border rounded-pill px-1">' + p.med + '</span>' : '<span class="text-muted">-</span>';
                    var lowBadge = p.low > 0 ? '<span class="badge bg-secondary-subtle text-secondary rounded-pill px-1">' + p.low + '</span>' : '<span class="text-muted">-</span>';
                    var drillUrl = '<?= site_url('temuan') ?>?penyulang_id=' + f.id + '&pelaksana=' + encodeURIComponent(p.pelaksana) + '&status=BELUM_SELESAI';
                    tr.innerHTML = '<td><span class="fw-semibold text-dark">' + escHtml(p.pelaksana) + '</span></td>' +
                        '<td class="text-center">' + emgBadge + '</td>' +
                        '<td class="text-center">' + highBadge + '</td>' +
                        '<td class="text-center">' + medBadge + '</td>' +
                        '<td class="text-center">' + lowBadge + '</td>' +
                        '<td class="text-center"><strong class="font-monospace ' + (p.belum_selesai > 0 ? 'text-danger' : 'text-success') + '">' + p.belum_selesai + '</strong></td>' +
                        '<td class="text-center"><a href="' + drillUrl + '" class="btn btn-xs btn-outline-primary py-0 px-1 rounded" style="font-size: 10px;" title="Lihat Temuan ' + escHtml(p.pelaksana) + '"><i class="fas fa-arrow-right"></i></a></td>';
                    pelBody.appendChild(tr);
                });
            } else {
                pelBody.innerHTML = '<tr><td colspan="7" class="text-center py-2 text-muted">Tidak ada rincian pelaksana untuk penyulang ini.</td></tr>';
            }
        }

        var btnTemuan = document.getElementById('detailModalBtnTemuan');
        if (btnTemuan) btnTemuan.href = '<?= site_url('temuan') ?>?penyulang_id=' + f.id;

        var btnGis = document.getElementById('detailModalBtnGis');
        if (btnGis) {
            btnGis.onclick = function() {
                var bsModal = bootstrap.Modal.getInstance(modalEl);
                if (bsModal) bsModal.hide();
                highlightFeederOnMap(f.id, f.nama_penyulang);
            };
        }

        var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    };

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // 6. Cumulative Gangguan 2-Year YoY Comparison & Zero-Reload Multi-Metric Line Chart
    window.yoyPayload = <?= json_encode($interruptionMetricsPayload ?? [], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
    window.currentYoYMetric = 'trips';
    window.yoyChartInstance = null;

    window.switchYoYMetric = function(metric, btn) {
        window.currentYoYMetric = metric;
        var group = document.getElementById('yoyMetricToggleGroup');
        if (group) {
            group.querySelectorAll('button').forEach(function(b) {
                b.classList.remove('btn-primary', 'active');
                b.classList.add('btn-light', 'text-dark');
            });
        }
        if (btn) {
            btn.classList.remove('btn-light', 'text-dark');
            btn.classList.add('btn-primary', 'active');
        }
        window.updateYoYComparisonChart();
    };

    window.updateYoYComparisonChart = function() {
        var selA = document.getElementById('yoyYearA');
        var selB = document.getElementById('yoyYearB');
        var yrA = selA ? parseInt(selA.value, 10) : new Date().getFullYear();
        var yrB = selB ? parseInt(selB.value, 10) : (new Date().getFullYear() - 1);

        // Update detail link href to maintain seamless synchronization with /gangguan page
        var btnDetail = document.getElementById('btnDetailYoy');
        if (btnDetail) {
            btnDetail.href = '<?= site_url("gangguan") ?>?year_a=' + yrA + '&year_b=' + yrB + '<?= !empty($selectedUlpId) ? "&ulp_id=" . (int)$selectedUlpId : "" ?>';
        }

        var labels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        var ctx = document.getElementById('cumulativeGangguanChart');
        var overlay = document.getElementById('chartNoDataOverlay');
        var overlayMsg = document.getElementById('chartNoDataMsg');
        if (!ctx || typeof Chart === 'undefined') return;

        if (window.yoyChartInstance) {
            window.yoyChartInstance.destroy();
            window.yoyChartInstance = null;
        }

        var metric = window.currentYoYMetric || 'trips';
        var datasets = [];
        var yTitle = 'Total Trip Kumulatif';
        var maxVal = 0;
        var hasValidData = false;

        var yoyData = (window.yoyPayload && window.yoyPayload.yoy) ? window.yoyPayload.yoy : {};
        var entryA = yoyData[yrA] || null;
        var entryB = yoyData[yrB] || null;

        if (metric === 'trips') {
            yTitle = 'Gangguan Kumulatif (Trip)';
            var dataA = (entryA && Array.isArray(entryA.cumulative)) ? entryA.cumulative : array_fill_null(12);
            var dataB = (entryB && Array.isArray(entryB.cumulative)) ? entryB.cumulative : array_fill_null(12);

            var totalA = (entryA && typeof entryA.total === 'number') ? entryA.total : getArrayMax(dataA);
            var totalB = (entryB && typeof entryB.total === 'number') ? entryB.total : getArrayMax(dataB);

            if (totalA > 0 || totalB > 0) hasValidData = true;
            maxVal = Math.max(totalA, totalB);

            // Update badge cards
            var badgeA = document.getElementById('badgeYearA');
            var badgeB = document.getElementById('badgeYearB');
            var badgeDiff = document.getElementById('badgeDiffYoY');
            if (badgeA) badgeA.textContent = yrA + ': ' + totalA.toLocaleString('id-ID') + ' trip';
            if (badgeB) badgeB.textContent = yrB + ': ' + totalB.toLocaleString('id-ID') + ' trip';
            if (badgeDiff) {
                var diff = totalA - totalB;
                var pct = totalB > 0 ? ((diff / totalB) * 100).toFixed(1) : (totalA > 0 ? '+100' : '0');
                var sign = diff > 0 ? '+' : '';
                badgeDiff.textContent = 'YoY: ' + sign + diff.toLocaleString('id-ID') + ' (' + sign + pct + '%)';
                badgeDiff.className = 'badge border rounded-pill px-2 py-1 fw-bold font-monospace ' +
                    (diff <= 0 ? 'bg-success-subtle text-success border-success-subtle' : 'bg-danger-subtle text-danger border-danger-subtle');
            }

            datasets.push({
                label: 'Tahun ' + yrA + ' (Total: ' + totalA.toLocaleString('id-ID') + ')',
                data: dataA,
                borderColor: '#dc2626',
                backgroundColor: 'rgba(220, 38, 38, 0.08)',
                borderWidth: 2.8,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#dc2626',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                fill: true,
                tension: 0.25,
                spanGaps: false
            });

            datasets.push({
                label: 'Tahun ' + yrB + ' (Total: ' + totalB.toLocaleString('id-ID') + ')',
                data: dataB,
                borderColor: '#0284c7',
                backgroundColor: 'rgba(2, 132, 199, 0.05)',
                borderWidth: 2.4,
                pointRadius: 3.5,
                pointHoverRadius: 5.5,
                pointBackgroundColor: '#0284c7',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                fill: false,
                borderDash: [5, 4],
                tension: 0.25,
                spanGaps: false
            });

        } else if (metric === 'current') {
            yTitle = 'Rata-rata Arus Gangguan (Ampere)';
            var currMap = (window.yoyPayload && window.yoyPayload.fault_current) ? window.yoyPayload.fault_current : {};
            var dataA = (currMap && currMap[yrA]) ? currMap[yrA] : array_fill_null(12);
            var dataB = (currMap && currMap[yrB]) ? currMap[yrB] : array_fill_null(12);

            var validA = dataA.filter(function(v) { return v !== null && !isNaN(v) && v > 0; });
            var validB = dataB.filter(function(v) { return v !== null && !isNaN(v) && v > 0; });
            if (validA.length || validB.length) hasValidData = true;

            var avgA = validA.length ? (validA.reduce(function(a, b) { return a + b; }, 0) / validA.length).toFixed(1) : null;
            var avgB = validB.length ? (validB.reduce(function(a, b) { return a + b; }, 0) / validB.length).toFixed(1) : null;

            var badgeA = document.getElementById('badgeYearA');
            var badgeB = document.getElementById('badgeYearB');
            var badgeDiff = document.getElementById('badgeDiffYoY');
            if (badgeA) badgeA.textContent = yrA + ': ' + (avgA !== null ? avgA + ' A' : 'N/A');
            if (badgeB) badgeB.textContent = yrB + ': ' + (avgB !== null ? avgB + ' A' : 'N/A');
            if (badgeDiff) {
                badgeDiff.textContent = 'Metrik: Arus Gangguan Riil';
                badgeDiff.className = 'badge border rounded-pill px-2 py-1 fw-bold font-monospace bg-light text-dark';
            }

            maxVal = Math.max(getArrayMax(dataA), getArrayMax(dataB));

            datasets.push({
                label: 'Arus Gangguan ' + yrA + (avgA ? ' (Rata-rata: ' + avgA + ' A)' : ' (N/A)'),
                data: dataA,
                borderColor: '#e11d48',
                backgroundColor: 'rgba(225, 29, 72, 0.08)',
                borderWidth: 2.5,
                pointRadius: 4,
                spanGaps: false
            });
            datasets.push({
                label: 'Arus Gangguan ' + yrB + (avgB ? ' (Rata-rata: ' + avgB + ' A)' : ' (N/A)'),
                data: dataB,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.05)',
                borderWidth: 2.2,
                pointRadius: 3.5,
                borderDash: [4, 4],
                spanGaps: false
            });

        } else if (metric === 'relay') {
            yTitle = 'Frekuensi Kerja Proteksi / Rele';
            var relayMap = (window.yoyPayload && window.yoyPayload.relay) ? window.yoyPayload.relay : {};
            var relaysA = relayMap[yrA] || {};
            var relaysB = relayMap[yrB] || {};
            var allRelayTypes = Object.keys(Object.assign({}, relaysA, relaysB));

            var colors = ['#dc2626', '#0284c7', '#d97706', '#16a34a', '#8b5cf6', '#ec4899'];
            var cIdx = 0;
            allRelayTypes.forEach(function(rType) {
                if (rType === 'TIDAK_TERCATAT') return;
                var arrA = relaysA[rType] || array_fill_null(12);
                var arrB = relaysB[rType] || array_fill_null(12);
                var totA = arrA.reduce(function(a, b) { return a + (Number(b) || 0); }, 0);
                var totB = arrB.reduce(function(a, b) { return a + (Number(b) || 0); }, 0);
                if (totA > 0 || totB > 0) hasValidData = true;
                maxVal = Math.max(maxVal, getArrayMax(arrA), getArrayMax(arrB));

                var col = colors[cIdx % colors.length];
                cIdx++;
                datasets.push({
                    label: rType + ' ' + yrA + ' (' + totA + ')',
                    data: arrA,
                    borderColor: col,
                    borderWidth: 2.4,
                    pointRadius: 3.5,
                    spanGaps: false
                });
                datasets.push({
                    label: rType + ' ' + yrB + ' (' + totB + ')',
                    data: arrB,
                    borderColor: col,
                    borderDash: [5, 4],
                    borderWidth: 1.8,
                    pointRadius: 3,
                    spanGaps: false
                });
            });

        } else if (metric === 'cause') {
            yTitle = 'Trip Berdasarkan Kategori Penyebab';
            var causeMap = (window.yoyPayload && window.yoyPayload.cause) ? window.yoyPayload.cause : {};
            var causesA = causeMap[yrA] || {};
            var causesB = causeMap[yrB] || {};
            var allCats = Object.keys(Object.assign({}, causesA, causesB));

            var colors = ['#16a34a', '#d97706', '#dc2626', '#0284c7', '#7c3aed', '#059669'];
            var cIdx = 0;
            allCats.forEach(function(cat) {
                var arrA = causesA[cat] || array_fill_null(12);
                var arrB = causesB[cat] || array_fill_null(12);
                var totA = arrA.reduce(function(a, b) { return a + (Number(b) || 0); }, 0);
                var totB = arrB.reduce(function(a, b) { return a + (Number(b) || 0); }, 0);
                if (totA > 0 || totB > 0) hasValidData = true;
                maxVal = Math.max(maxVal, getArrayMax(arrA), getArrayMax(arrB));

                var col = colors[cIdx % colors.length];
                cIdx++;
                datasets.push({
                    label: cat + ' ' + yrA + ' (' + totA + ')',
                    data: arrA,
                    borderColor: col,
                    borderWidth: 2.4,
                    pointRadius: 3.5,
                    spanGaps: false
                });
                datasets.push({
                    label: cat + ' ' + yrB + ' (' + totB + ')',
                    data: arrB,
                    borderColor: col,
                    borderDash: [5, 4],
                    borderWidth: 1.8,
                    pointRadius: 3,
                    spanGaps: false
                });
            });
        }

        if (!hasValidData) {
            if (overlay) {
                overlay.classList.remove('d-none');
                if (overlayMsg) overlayMsg.textContent = 'Data metrik "' + yTitle + '" belum tersedia pada periode ' + yrA + ' vs ' + yrB + '.';
            }
        } else {
            if (overlay) overlay.classList.add('d-none');
        }

        var ySuggestedMax = maxVal > 0 ? Math.ceil(maxVal * 1.25) : 10;

        // Custom plugin: draws point labels on vertices with collision avoidance (higher point above, lower point below)
        var directPointLabelsPlugin = {
            id: 'directPointLabels',
            afterDatasetsDraw: function(chart) {
                var c = chart.ctx;
                c.save();
                c.font = '700 9.5px "JetBrains Mono", Consolas, monospace';
                c.textAlign = 'center';

                var ds0Meta = chart.getDatasetMeta(0);
                var ds1Meta = chart.data.datasets.length > 1 ? chart.getDatasetMeta(1) : null;

                chart.data.datasets.forEach(function(ds, dsIdx) {
                    var meta = chart.getDatasetMeta(dsIdx);
                    if (!meta || meta.hidden) return;

                    meta.data.forEach(function(pt, ptIdx) {
                        var rawVal = ds.data[ptIdx];
                        if (rawVal === null || typeof rawVal === 'undefined' || isNaN(rawVal)) return;
                        var numVal = Number(rawVal);
                        var txt = (metric === 'current' && numVal > 0) ? numVal.toFixed(1) + 'A' : Math.round(numVal).toLocaleString('id-ID');

                        var isAbove = true;
                        if (chart.data.datasets.length > 1 && ds0Meta && ds1Meta && ds0Meta.data[ptIdx] && ds1Meta.data[ptIdx]) {
                            var y0 = ds0Meta.data[ptIdx].y;
                            var y1 = ds1Meta.data[ptIdx].y;
                            if (Math.abs(y0 - y1) < 18) {
                                isAbove = (dsIdx === 0 ? (y0 <= y1) : (y1 < y0));
                            } else {
                                isAbove = (dsIdx === 0);
                            }
                        } else {
                            isAbove = (dsIdx === 0);
                        }

                        c.textBaseline = isAbove ? 'bottom' : 'top';
                        var yOffset = isAbove ? -6 : 6;
                        var drawY = pt.y + yOffset;

                        if (drawY < 12) {
                            drawY = 12;
                            c.textBaseline = 'top';
                        } else if (drawY > chart.height - 14) {
                            drawY = chart.height - 14;
                            c.textBaseline = 'bottom';
                        }

                        c.lineWidth = 3.5;
                        c.strokeStyle = 'rgba(255, 255, 255, 0.95)';
                        c.strokeText(txt, pt.x, drawY);
                        c.fillStyle = ds.borderColor || '#0f172a';
                        c.fillText(txt, pt.x, drawY);
                    });
                });
                c.restore();
            }
        };

        window.yoyChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets
            },
            plugins: [directPointLabelsPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                layout: {
                    padding: { top: 18, right: 16, bottom: 6, left: 8 }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 10,
                            font: { size: 10.5, weight: '600' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(c) {
                                var val = c.parsed.y;
                                if (val === null || typeof val === 'undefined') return c.dataset.label + ': Tidak Ada Data';
                                var u = metric === 'current' ? ' A' : ' kejadian';
                                return c.dataset.label.split(' (')[0] + ': ' + Number(val).toLocaleString('id-ID') + u;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        min: 0,
                        suggestedMax: ySuggestedMax,
                        title: {
                            display: true,
                            text: yTitle,
                            font: { size: 10, weight: 'bold' }
                        },
                        ticks: {
                            precision: metric === 'current' ? 1 : 0,
                            maxTicksLimit: 6,
                            font: { size: 10.5 }
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.16)'
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 10.5, weight: '600' }
                        }
                    }
                }
            }
        });
    };

    function array_fill_null(len) {
        var a = [];
        for (var i = 0; i < len; i++) a.push(null);
        return a;
    }

    function getArrayMax(arr) {
        if (!Array.isArray(arr) || !arr.length) return 0;
        var valid = arr.filter(function(v) { return v !== null && !isNaN(v); });
        return valid.length ? Math.max.apply(null, valid) : 0;
    }

    // Initialize Cumulative 2-Year YoY Comparison Chart immediately on load
    $(function() {
        window.updateYoYComparisonChart();
    });

    // Feeder sections toggle helper
    window.toggleFeederSections = function(feederId) {
        var subrows = document.querySelectorAll('.feeder-subrow-' + feederId);
        var btn = document.querySelector('.toggle-feeder-sections[data-feeder="' + feederId + '"]');
        var isHidden = false;
        subrows.forEach(function(row) {
            if (row.classList.contains('d-none')) {
                row.classList.remove('d-none');
                isHidden = false;
            } else {
                row.classList.add('d-none');
                isHidden = true;
            }
        });
        if (btn) {
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = isHidden ? 'fas fa-chevron-right' : 'fas fa-chevron-down';
            }
        }
    };

    $(document).on('click', '.toggle-feeder-sections', function(e) {
        e.preventDefault();
        var fId = $(this).data('feeder');
        if (fId) window.toggleFeederSections(fId);
    });

    // 6B. Interactive Filter for Tabel Temuan dan Kebutuhan Material (BOM)
    window.onBomCondTypeFilterChange = function() {
        var typeSel = document.getElementById('bomFilterCondType');
        var sizeSel = document.getElementById('bomFilterCondSize');
        if (typeSel && sizeSel && typeSel.value) {
            sizeSel.classList.add('border-primary');
        } else if (sizeSel) {
            sizeSel.classList.remove('border-primary');
        }
        window.applyBomMatrixFilter();
    };

    window.applyBomMatrixFilter = function() {
        var giVal   = String((document.getElementById('bomFilterGi') || {}).value || '');
        var ulpVal  = String((document.getElementById('bomFilterUlp') || {}).value || '');
        var typeVal = String((document.getElementById('bomFilterCondType') || {}).value || '');
        var sizeVal = String((document.getElementById('bomFilterCondSize') || {}).value || '');
        var qVal    = String((document.getElementById('bomFilterSearch') || {}).value || '').trim().toLowerCase();

        var rows = document.querySelectorAll('#bomMatrixTableBody .bom-matrix-row');
        var segCount = 0, sumTemuan = 0, sumArm = 0, sumCross = 0, sumPin = 0, sumHang = 0, sumFco = 0, sumArr = 0, sumSkun = 0, sumGsw = 0, sumCondM = 0;

        rows.forEach(function(r) {
            var rGi   = String(r.getAttribute('data-gi') || '');
            var rUlp  = String(r.getAttribute('data-ulp-id') || '');
            var rType = String(r.getAttribute('data-cond-type') || '');
            var rSize = String(r.getAttribute('data-cond-size') || '');
            var rSrch = String(r.getAttribute('data-search') || '');

            var ok = (!giVal || rGi === giVal)
                  && (!ulpVal || rUlp === ulpVal)
                  && (!typeVal || rType === typeVal)
                  && (!sizeVal || rSize === sizeVal)
                  && (!qVal || rSrch.indexOf(qVal) !== -1);

            r.style.display = ok ? '' : 'none';
            var fId = r.getAttribute('data-feeder-id');
            if (!ok && fId) {
                document.querySelectorAll('.feeder-subrow-' + fId).forEach(function(sr) {
                    sr.classList.add('d-none');
                });
            }
            if (ok) {
                segCount++;
                sumTemuan += Number(r.getAttribute('data-temuan') || 0);
                sumArm    += Number(r.getAttribute('data-armtie') || 0);
                sumCross  += Number(r.getAttribute('data-crossarm') || 0);
                sumPin    += Number(r.getAttribute('data-pin') || 0);
                sumHang   += Number(r.getAttribute('data-hang') || 0);
                sumFco    += Number(r.getAttribute('data-fco') || 0);
                sumArr    += Number(r.getAttribute('data-arrester') || 0);
                sumSkun   += Number(r.getAttribute('data-skun') || 0);
                sumGsw    += Number(r.getAttribute('data-gsw') || 0);
                sumCondM  += Number(r.getAttribute('data-condm') || 0);
            }
        });

        var setTxt = function(id, v) {
            var el = document.getElementById(id);
            if (el) el.textContent = Math.round(v).toLocaleString('id-ID');
        };
        setTxt('bomTotTemuan', sumTemuan);
        setTxt('bomTotArmtie', sumArm);
        setTxt('bomTotCrossarm', sumCross);
        setTxt('bomTotPin', sumPin);
        setTxt('bomTotHang', sumHang);
        setTxt('bomTotFco', sumFco);
        setTxt('bomTotArrester', sumArr);
        setTxt('bomTotSkun', sumSkun);
        setTxt('bomTotGsw', sumGsw);
        setTxt('bomTotCondM', sumCondM);

        var badge = document.getElementById('bomMatrixActiveCountBadge');
        if (badge) {
            badge.textContent = sumTemuan.toLocaleString('id-ID') + ' Temuan • ' + segCount.toLocaleString('id-ID') + ' Penyulang';
        }
    };

    window.resetBomMatrixFilter = function() {
        ['bomFilterGi', 'bomFilterUlp', 'bomFilterCondType', 'bomFilterCondSize', 'bomFilterSearch'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        window.onBomCondTypeFilterChange();
    };

    // 6C. Client-side Year Switcher for Penyebab Gangguan Module (supports both {total_events, items} and flat array payloads)
    window.causeBreakdownDataByYear = <?= json_encode($causeBreakdownByYear ?? [], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
    window.renderCauseBreakdownByYear = function(year) {
        var yr = String(year || '');
        var raw = window.causeBreakdownDataByYear ? window.causeBreakdownDataByYear[yr] : null;
        var items = [];
        var totalEvents = 0;
        var sourceNote = null;

        if (Array.isArray(raw)) {
            items = raw;
            totalEvents = items.reduce(function(acc, r) { return acc + (Number(r.count ?? r.total ?? 0) || 0); }, 0);
        } else if (raw && typeof raw === 'object') {
            items = Array.isArray(raw.items) ? raw.items : [];
            totalEvents = (typeof raw.total_events === 'number')
                ? raw.total_events
                : items.reduce(function(acc, r) { return acc + (Number(r.count ?? r.total ?? 0) || 0); }, 0);
            sourceNote = raw.source_note || null;
        }

        var titleEl = document.getElementById('causeYearTitleLabel');
        var badgeEl = document.getElementById('causeTotalBadge');
        var tbody   = document.getElementById('causeBreakdownTableBody');
        if (titleEl) titleEl.textContent = yr;
        if (badgeEl) badgeEl.textContent = Number(totalEvents || 0).toLocaleString('id-ID') + ' Kejadian';
        if (!tbody) return;

        if (!items.length) {
            var emptyMsg = sourceNote || ('Belum ada data histori gangguan untuk tahun ' + yr + '.');
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3 small">' + escHtml(emptyMsg) + '</td></tr>';
            return;
        }

        var html = '';
        items.forEach(function(cRow, idx) {
            var st = String(cRow.mapping_status || 'RESOLVED').toUpperCase();
            var stBadge = st === 'RESOLVED'
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5" style="font-size:10px;">TERPETAKAN</span>'
                : (st === 'PARTIALLY_RESOLVED'
                    ? '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-0.5" style="font-size:10px;">PARSIAL</span>'
                    : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-0.5" style="font-size:10px;">BELUM TERPETAKAN</span>');
            var rowCount = Number(cRow.count ?? cRow.total ?? 0) || 0;
            var pct = (typeof cRow.pct === 'number') ? cRow.pct : (totalEvents > 0 ? ((rowCount / totalEvents) * 100) : 0);
            var barW = Math.min(100, Math.max(2, pct));
            var barCls = st === 'UNMAPPED' ? 'bg-secondary' : 'bg-danger';
            var rawStr = String(cRow.raw_samples || '');
            if (rawStr.length > 75) rawStr = rawStr.slice(0, 72) + '...';
            var rawText = rawStr ? ' &bull; <span class="text-secondary">Contoh: ' + escHtml(rawStr) + '</span>' : '';

            html += '<tr>' +
                '<td class="text-center text-muted fw-bold">' + (idx + 1) + '</td>' +
                '<td>' +
                    '<div class="fw-semibold text-dark">' + escHtml(cRow.label || '-') + '</div>' +
                    '<div class="text-muted font-monospace" style="font-size: 10px;">' + escHtml(cRow.canonical_code || '-') + rawText + '</div>' +
                '</td>' +
                '<td class="text-center">' + stBadge + '</td>' +
                '<td class="text-end fw-bold text-dark font-monospace">' + rowCount.toLocaleString('id-ID') + ' <span class="text-muted fw-normal" style="font-size:10px;">(' + pct.toFixed(1) + '%)</span></td>' +
                '<td><div class="progress" style="height: 6px;"><div class="progress-bar ' + barCls + '" role="progressbar" style="width: ' + barW + '%"></div></div></td>' +
            '</tr>';
        });
        tbody.innerHTML = html;
    };

    // 7. Interactive Filter ULP & Penyulang for Ringkasan Jaringan & Aset Module
    window.applyNetworkModuleFilter = function() {
        var ulpSelect = document.getElementById('netFilterUlpSelect');
        var feederSelect = document.getElementById('netFilterFeederSelect');
        if (!ulpSelect || !feederSelect) return;

        var selectedUlp = String(ulpSelect.value || '');
        var selectedFeeder = String(feederSelect.value || '');

        // Filter feeder dropdown options to match selected ULP
        Array.from(feederSelect.options).forEach(function(opt) {
            if (!opt.value) return;
            var optUlp = String(opt.getAttribute('data-ulp-id') || '');
            var matchUlp = !selectedUlp || optUlp === selectedUlp;
            opt.hidden = !matchUlp;
            if (!matchUlp && opt.value === selectedFeeder) {
                selectedFeeder = '';
                feederSelect.value = '';
            }
        });

        var rows = document.querySelectorAll('#networkFeedersTableBody .net-feeder-row');
        var ulpSet = new Set();
        var giSet = new Set();
        var feederCount = 0;
        var assetSum = 0;
        var aaacSum = 0, aaacsSum = 0, mvticSum = 0, xlpeSum = 0;

        rows.forEach(function(row) {
            var rUlp = String(row.getAttribute('data-ulp-id') || '');
            var rFeeder = String(row.getAttribute('data-feeder-id') || '');
            var show = (!selectedUlp || rUlp === selectedUlp) && (!selectedFeeder || rFeeder === selectedFeeder);
            row.style.display = show ? '' : 'none';
            if (show) {
                feederCount++;
                if (rUlp && rUlp !== '0') ulpSet.add(rUlp);
                var gi = (row.getAttribute('data-gi') || '').trim();
                if (gi && gi !== '-') giSet.add(gi);
                assetSum += Number(row.getAttribute('data-aset') || 0);
                aaacSum  += Number(row.getAttribute('data-aaac') || 0);
                aaacsSum += Number(row.getAttribute('data-aaacs') || 0);
                mvticSum += Number(row.getAttribute('data-mvtic') || 0);
                xlpeSum  += Number(row.getAttribute('data-xlpe') || 0);
            }
        });

        var ulpCountEl = document.getElementById('netStatUlpCount');
        var giBadgesEl = document.getElementById('netStatGiBadges');
        var fCountEl   = document.getElementById('netStatFeederCount');
        var aCountEl   = document.getElementById('netStatAssetCount');
        var aaacEl     = document.getElementById('netStatAaacKm');
        var aaacsEl    = document.getElementById('netStatAaacsKm');
        var mvticEl    = document.getElementById('netStatMvticKm');
        var xlpeEl     = document.getElementById('netStatXlpeKm');
        var totalKmEl  = document.getElementById('netStatTotalKm');

        if (ulpCountEl) ulpCountEl.textContent = (ulpSet.size || (selectedUlp ? 1 : 0)) + ' ULP';
        if (fCountEl)   fCountEl.textContent   = feederCount.toLocaleString('id-ID');
        if (aCountEl)   aCountEl.textContent   = assetSum.toLocaleString('id-ID');
        if (aaacEl)     aaacEl.innerHTML       = aaacSum.toFixed(2) + ' <small class="fw-normal" style="font-size:10px;">km</small>';
        if (aaacsEl)    aaacsEl.innerHTML      = aaacsSum.toFixed(2) + ' <small class="fw-normal" style="font-size:10px;">km</small>';
        if (mvticEl)    mvticEl.innerHTML      = mvticSum.toFixed(2) + ' <small class="fw-normal" style="font-size:10px;">km</small>';
        if (xlpeEl)     xlpeEl.innerHTML       = xlpeSum.toFixed(2) + ' <small class="fw-normal" style="font-size:10px;">km</small>';
        if (totalKmEl)  totalKmEl.textContent  = (aaacSum + aaacsSum + mvticSum + xlpeSum).toFixed(2);

        if (giBadgesEl) {
            if (giSet.size > 0) {
                var giHtml = '';
                giSet.forEach(function(g) {
                    giHtml += '<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1" style="font-size: 10.5px;">' + escHtml(g) + '</span>';
                });
                giBadgesEl.innerHTML = giHtml;
            } else {
                giBadgesEl.innerHTML = '<span class="text-muted small">–</span>';
            }
        }
    };

    window.resetNetworkModuleFilter = function() {
        var ulpSelect = document.getElementById('netFilterUlpSelect');
        var feederSelect = document.getElementById('netFilterFeederSelect');
        if (ulpSelect) ulpSelect.value = '<?= (int)($selectedUlpId ?? 0) ?: '' ?>';
        if (feederSelect) feederSelect.value = '';
        window.applyNetworkModuleFilter();
    };
});
</script>
<?= $this->endSection() ?>
