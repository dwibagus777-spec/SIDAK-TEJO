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
        height: 310px !important;
        max-height: 320px !important;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .dah-cum-chart-box {
        position: relative !important;
        width: 100% !important;
        height: 225px !important;
        max-height: 225px !important;
        flex: 0 0 225px !important;
        overflow: hidden;
    }
    .dah-cum-chart-box canvas {
        display: block;
        width: 100% !important;
        height: 100% !important;
        max-height: 225px !important;
    }
    @media (max-width: 767.98px) {
        .dah-cum-card {
            height: 260px !important;
            max-height: 270px !important;
        }
        .dah-cum-chart-box {
            height: 180px !important;
            max-height: 180px !important;
            flex: 0 0 180px !important;
        }
        .dah-cum-chart-box canvas {
            max-height: 180px !important;
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
            <div class="text-muted fw-semibold mt-1" style="font-size: 11px;">Gardu Distribusi</div>
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


        <!-- 6. ANALISIS KEANDALAN GANGGUAN & TEMUAN INSPEKSI -->
    <div class="row g-3 mb-4 align-items-start">
        <!-- Kolom Kiri: Modul Gangguan (Penyulang Terbanyak + Tepat di bawahnya Gangguan Kumulatif) -->
        <div class="col-lg-6 col-12 d-flex flex-column gap-3">
            <!-- 6A. Penyulang Gangguan Terbanyak -->
            <div class="card dah-card shadow-sm border">
                <div class="card-header bg-white py-2.5 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger-subtle text-danger p-2 rounded-2"><i class="fas fa-bolt"></i></span>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 13px;">Penyulang Gangguan Terbanyak</h6>
                            <small class="text-muted" style="font-size: 11px;">Peringkat frekuensi trip tahun <?= (int)$trendYear ?></small>
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
                    <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                            <thead class="table-light sticky-top" style="font-size: 11px; text-transform: uppercase;">
                                <tr>
                                    <th style="width: 35px;" class="text-center">#</th>
                                    <th>Penyulang</th>
                                    <th>ULP</th>
                                    <th class="text-end" style="width: 90px;">Total Gangguan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($topFeedersGangguan ?? []) as $rank => $row): ?>
                                <tr>
                                    <td class="text-center text-muted fw-bold"><?= $rank + 1 ?></td>
                                    <td class="fw-semibold text-dark"><?= esc($row['feeder_name'] ?? '-') ?></td>
                                    <td class="text-muted small"><?= esc($row['nama_ulp'] ?? '-') ?></td>
                                    <td class="text-end fw-bold text-danger"><?= (int)($row['cnt'] ?? 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($topFeedersGangguan)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3 small">Tidak ada data gangguan untuk tahun <?= (int)$trendYear ?>.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 6B. Gangguan Kumulatif Year-over-Year (Tepat di Bawah Penyulang Gangguan Terbanyak) -->
            <div class="card dah-card dah-cum-card shadow-sm border">
                <div class="card-header bg-white py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger-subtle text-danger p-2 rounded-2"><i class="fas fa-chart-line"></i></span>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 13px;">Gangguan Kumulatif (Perbandingan YoY)</h6>
                            <small class="text-muted" style="font-size: 11px;">Akumulasi bulanan Jan–Des lintas tahun (<?= implode(', ', array_keys($cumulativeGangguanYoY ?? [$trendYear => []])) ?>)</small>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-1.5">
                        <?php
                            $yoyBadgeColors = [
                                0 => 'bg-danger-subtle text-danger border-danger-subtle',
                                1 => 'bg-info-subtle text-info border-info-subtle',
                                2 => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                3 => 'bg-success-subtle text-success border-success-subtle',
                            ];
                            $yoyIdx = 0;
                            foreach (($cumulativeGangguanYoY ?? []) as $yKey => $yMonths):
                                $yMax = !empty($yMonths) ? max($yMonths) : 0;
                                $bCls = $yoyBadgeColors[$yoyIdx % count($yoyBadgeColors)];
                                $yoyIdx++;
                        ?>
                            <span class="badge border rounded-pill px-2 py-1 fw-bold font-monospace <?= $bCls ?>" style="font-size: 10.5px;">
                                <?= (int)$yKey ?>: <?= number_format((int)$yMax) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card-body p-2.5 d-flex align-items-center justify-content-center">
                    <div class="dah-cum-chart-box">
                        <canvas id="cumulativeGangguanChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- 6C. Penyebab Gangguan (Tepat di Bawah Grafik Gangguan Kumulatif) -->
            <?php
                $causeYearsList = !empty($causeBreakdownByYear) ? array_keys($causeBreakdownByYear) : ($availableTrendYears ?? [(int)date('Y')]);
                $initialCauseYear = in_array((int)$trendYear, array_map('intval', $causeYearsList), true) ? (int)$trendYear : (int)($causeYearsList[0] ?? date('Y'));
                $initialCauseData = $causeBreakdownByYear[$initialCauseYear] ?? ['total_events' => 0, 'items' => []];
            ?>
            <div class="card dah-card shadow-sm border">
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
                            <?php foreach ($causeYearsList as $cYr): ?>
                                <option value="<?= (int)$cYr ?>" <?= ((int)$cYr === (int)$initialCauseYear) ? 'selected' : '' ?>><?= (int)$cYr ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span id="causeTotalBadge" class="badge bg-dark-subtle text-dark rounded-pill px-2.5 py-1 fw-bold font-monospace" style="font-size: 11px;">
                            <?= number_format((int)($initialCauseData['total_events'] ?? 0)) ?> Kejadian
                        </span>
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
                                <?php if (!empty($initialCauseData['items'])): ?>
                                    <?php foreach ($initialCauseData['items'] as $cIdx => $cRow):
                                        $st = strtoupper((string)($cRow['mapping_status'] ?? 'RESOLVED'));
                                        $stBadge = $st === 'RESOLVED'
                                            ? '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5" style="font-size:10px;">TERPETAKAN</span>'
                                            : ($st === 'PARTIALLY_RESOLVED'
                                                ? '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-0.5" style="font-size:10px;">PARSIAL</span>'
                                                : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-0.5" style="font-size:10px;">BELUM TERPETAKAN</span>');
                                    ?>
                                    <tr>
                                        <td class="text-center text-muted fw-bold"><?= $cIdx + 1 ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= esc($cRow['label'] ?? '-') ?></div>
                                            <div class="text-muted font-monospace" style="font-size: 10px;">
                                                <?= esc($cRow['canonical_code'] ?? '-') ?>
                                                <?php if (!empty($cRow['raw_samples'])): ?>
                                                    &bull; <span class="text-secondary">Contoh: <?= esc($cRow['raw_samples']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-center"><?= $stBadge ?></td>
                                        <td class="text-end fw-bold text-dark font-monospace"><?= number_format((int)($cRow['total'] ?? 0)) ?> <span class="text-muted fw-normal" style="font-size:10px;">(<?= number_format((float)($cRow['pct'] ?? 0), 1) ?>%)</span></td>
                                        <td>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar <?= $st === 'UNMAPPED' ? 'bg-secondary' : 'bg-danger' ?>" role="progressbar" style="width: <?= min(100, max(2, (float)($cRow['pct'] ?? 0))) ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3 small">Belum ada data histori gangguan untuk tahun <?= (int)$initialCauseYear ?>.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Penyulang Temuan Inspeksi Terbanyak -->
        <div class="col-lg-6 col-12">
            <div class="card dah-card shadow-sm border h-100">
                <div class="card-header bg-white py-2.5 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-info-subtle text-info p-2 rounded-2"><i class="fas fa-clipboard-list"></i></span>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 13px;">Penyulang Temuan Inspeksi Terbanyak</h6>
                            <small class="text-muted" style="font-size: 11px;">Rincian pelaksana temuan aktif &bull; Klik baris untuk sorot peta</small>
                        </div>
                    </div>
                    <span class="badge bg-light text-muted border rounded-pill px-2.5 py-1 small">
                        <?= count($topInspeksiPenyulang ?? []) ?> Penyulang
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 760px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                            <thead class="table-light sticky-top" style="font-size: 11px; text-transform: uppercase;">
                                <tr>
                                    <th style="width: 35px;" class="text-center">#</th>
                                    <th>Penyulang</th>
                                    <th class="text-end" style="width: 60px;" title="Temuan PDKB">PDKB</th>
                                    <th class="text-end" style="width: 75px;" title="Temuan HAR Gardu">HAR Gardu</th>
                                    <th class="text-end" style="width: 75px;" title="Temuan HAR ROW">HAR ROW</th>
                                    <th class="text-end" style="width: 60px;" title="Total Temuan Aktif">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($topInspeksiPenyulang ?? []) as $rank => $row): ?>
                                <tr style="cursor: pointer;" onclick="highlightFeederOnMap(<?= (int)$row['penyulang_id'] ?>, '<?= esc(addslashes($row['nama_penyulang'] ?? '')) ?>')" title="Klik untuk fokus ke titik penyulang di GIS Map">
                                    <td class="text-center text-muted fw-bold"><?= $rank + 1 ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= esc($row['nama_penyulang'] ?? '-') ?></div>
                                        <div class="text-muted" style="font-size: 10px;"><i class="fas fa-location-dot text-primary me-1"></i>Lihat di GIS</div>
                                    </td>
                                    <td class="text-end"><span class="badge bg-light text-dark border"><?= (int)($row['pdkb'] ?? 0) ?></span></td>
                                    <td class="text-end"><span class="badge bg-light text-dark border"><?= (int)($row['har_gardu'] ?? 0) ?></span></td>
                                    <td class="text-end"><span class="badge bg-light text-dark border"><?= (int)($row['har_row'] ?? 0) ?></span></td>
                                    <td class="text-end fw-bold text-info fs-6"><?= (int)($row['total'] ?? 0) ?></td>
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
                <div class="card-footer bg-light-subtle py-2 px-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small" style="font-size: 11px;"><i class="fas fa-hand-pointer text-primary me-1"></i>Pilih penyulang untuk menandai lokasinya pada peta di atas.</span>
                    <a href="<?= site_url('temuan') ?>" class="text-primary text-decoration-none fw-semibold small" style="font-size: 11px;">Semua Temuan <i class="fas fa-arrow-right ms-1"></i></a>
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
                            <div class="text-muted small fw-semibold" style="font-size: 11px;">Jumlah Penyulang</div>
                            <div class="fs-4 fw-bold text-dark font-monospace lh-1 mt-1" id="netStatFeederCount"><?= number_format((int)$networkTotals['penyulang']) ?></div>
                        </div>
                        <div class="vr opacity-25"></div>
                        <div>
                            <div class="text-muted small fw-semibold" style="font-size: 11px;">Jumlah Aset Jaringan</div>
                            <div class="fs-4 fw-bold text-primary font-monospace lh-1 mt-1" id="netStatAssetCount"><?= number_format((int)$networkTotals['aset']) ?></div>
                        </div>
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

    <!-- 8. CAPTIVE NON PENUGASAN — POTENSI COATING (AAAC OPERATIONAL MODULE) -->
    <?php
        $coatingByFeeder   = $coatingSummary['by_feeder'] ?? [];
        $coatingTotalTitik = (int)($coatingSummary['total_titik'] ?? 0);
        $coatingTotalKm    = round(((float)($coatingSummary['total_meter'] ?? 0)) / 1000, 2);
        $coatingFeederCnt  = (int)($coatingSummary['total_penyulang'] ?? count($coatingByFeeder));
        $aaacPoolSpans     = (int)($coatingSummary['aaac_pool_spans'] ?? 0);
        $aaacPoolKms       = (float)($coatingSummary['aaac_pool_kms'] ?? 0.0);
        $aaacTopFeeders    = $coatingSummary['aaac_top_feeders'] ?? [];
    ?>
    <div class="card dah-card shadow-sm border mb-4">
        <div class="card-header bg-white py-3 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-warning-subtle text-warning-emphasis p-2 rounded-2"><i class="fas fa-highlighter fs-5"></i></span>
                <div>
                    <h5 class="fw-bold text-dark mb-0" style="font-size: 14px;">Captive Non Penugasan — Potensi Coating Konduktor (Khusus AAAC)</h5>
                    <div class="small text-muted" style="font-size: 11px;">
                        Rekapitulasi per penyulang untuk segmen transline <strong>AAAC</strong> aktif yang ditandai bisa coating &bull;
                        <span class="text-secondary">Default: Tidak Bisa Coating (AAACS, MVTIC, XLPE dikecualikan)</span>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
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
                            <div class="fs-5 fw-bold text-dark font-monospace lh-1 mt-0.5"><?= number_format($coatingTotalTitik) ?> <small class="text-muted fw-normal" style="font-size:11px;">Segmen</small></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 col-12">
                    <div class="p-2.5 rounded-3 bg-white border h-100 d-flex align-items-center gap-2.5">
                        <div class="rounded-2 bg-info-subtle text-info d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                            <i class="fas fa-ruler-horizontal"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold" style="font-size: 11px;">Total Jarak Potensi Coating</div>
                            <div class="fs-5 fw-bold text-info font-monospace lh-1 mt-0.5"><?= number_format($coatingTotalKm, 2) ?> <small class="text-muted fw-normal" style="font-size:11px;">km (<?= number_format((float)($coatingSummary['total_meter'] ?? 0), 0) ?> m)</small></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 col-12">
                    <div class="p-2.5 rounded-3 bg-white border h-100 d-flex align-items-center gap-2.5">
                        <div class="rounded-2 bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold" style="font-size: 11px;">Penyulang Memiliki Kandidat</div>
                            <div class="fs-5 fw-bold text-primary font-monospace lh-1 mt-0.5"><?= number_format($coatingFeederCnt) ?> <small class="text-muted fw-normal" style="font-size:11px;">Penyulang</small></div>
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
                            <div class="fs-5 fw-bold text-success font-monospace lh-1 mt-0.5"><?= number_format($aaacPoolKms, 2) ?> <small class="text-muted fw-normal" style="font-size:11px;">km (<?= number_format($aaacPoolSpans) ?> titik)</small></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (!empty($coatingByFeeder)): ?>
                <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
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
            foreach ($monthlyData as $md) {
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

    // 6. Cumulative Gangguan Multi-Year YoY Line Chart (12 Months Running Total per Year)
    (function initCumulativeChart() {
        var yoyRaw = <?= json_encode($cumulativeGangguanYoY ?? [], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
        var fallbackSingle = <?= json_encode(array_values($cumulativeGangguan ?? array_fill(0, 12, 0))) ?>;
        var selectedTrendYear = <?= (int)($trendYear ?? date('Y')) ?>;
        var labels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        var ctx = document.getElementById('cumulativeGangguanChart');
        if (!ctx || typeof Chart === 'undefined') return;
        if (typeof Chart.getChart === 'function') {
            var prev = Chart.getChart(ctx);
            if (prev) prev.destroy();
        }

        var palette = [
            { border: '#dc2626', bg: 'rgba(220, 38, 38, 0.10)', dash: [] },
            { border: '#0284c7', bg: 'rgba(2, 132, 199, 0.06)', dash: [] },
            { border: '#d97706', bg: 'rgba(217, 119, 6, 0.05)', dash: [5, 4] },
            { border: '#16a34a', bg: 'rgba(22, 163, 74, 0.05)', dash: [3, 3] }
        ];

        var years = Object.keys(yoyRaw || {}).sort(function(a, b) { return Number(b) - Number(a); });
        if (!years.length) {
            yoyRaw = {};
            yoyRaw[String(selectedTrendYear)] = fallbackSingle;
            years = [String(selectedTrendYear)];
        }

        var maxVal = 0;
        var datasets = years.map(function(yr, idx) {
            var arr = (yoyRaw[yr] || []).map(function(v) { return Number(v) || 0; });
            while (arr.length < 12) arr.push(arr.length ? arr[arr.length - 1] : 0);
            var localMax = arr.length ? Math.max.apply(null, arr) : 0;
            if (localMax > maxVal) maxVal = localMax;
            var style = palette[idx % palette.length];
            var isPrimaryYear = (Number(yr) === Number(selectedTrendYear)) || idx === 0;
            return {
                label: 'Tahun ' + yr + ' (Total: ' + localMax + ')',
                data: arr,
                borderColor: style.border,
                backgroundColor: style.bg,
                borderDash: style.dash,
                borderWidth: isPrimaryYear ? 2.8 : 2.2,
                pointRadius: isPrimaryYear ? 3.5 : 3,
                pointHoverRadius: 5.5,
                pointBackgroundColor: style.border,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 1.5,
                fill: idx === 0,
                tension: 0.28
            };
        });

        var ySuggestedMax = maxVal > 0 ? Math.ceil(maxVal * 1.12) : 10;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                layout: {
                    padding: { top: 4, right: 8, bottom: 2, left: 4 }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: {
                            boxWidth: 12,
                            boxHeight: 12,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 10,
                            font: { size: 11, weight: '600' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(c) {
                                return c.dataset.label.split(' (')[0] + ': ' + c.parsed.y + ' gangguan kumulatif';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        min: 0,
                        suggestedMax: ySuggestedMax,
                        ticks: {
                            precision: 0,
                            maxTicksLimit: 6,
                            font: { size: 11 }
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.16)'
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 11 }
                        }
                    }
                }
            }
        });
    })();

    // 6C. Client-side Year Switcher for Penyebab Gangguan Module
    window.causeBreakdownDataByYear = <?= json_encode($causeBreakdownByYear ?? [], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
    window.renderCauseBreakdownByYear = function(year) {
        var yr = String(year || '');
        var data = (window.causeBreakdownDataByYear && window.causeBreakdownDataByYear[yr]) || { total_events: 0, items: [] };
        var titleEl = document.getElementById('causeYearTitleLabel');
        var badgeEl = document.getElementById('causeTotalBadge');
        var tbody   = document.getElementById('causeBreakdownTableBody');
        if (titleEl) titleEl.textContent = yr;
        if (badgeEl) badgeEl.textContent = Number(data.total_events || 0).toLocaleString('id-ID') + ' Kejadian';
        if (!tbody) return;

        var items = data.items || [];
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3 small">Belum ada data histori gangguan untuk tahun ' + escHtml(yr) + '.</td></tr>';
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
            var pct = Number(cRow.pct || 0);
            var barW = Math.min(100, Math.max(2, pct));
            var barCls = st === 'UNMAPPED' ? 'bg-secondary' : 'bg-danger';
            var rawText = cRow.raw_samples ? ' &bull; <span class="text-secondary">Contoh: ' + escHtml(cRow.raw_samples) + '</span>' : '';

            html += '<tr>' +
                '<td class="text-center text-muted fw-bold">' + (idx + 1) + '</td>' +
                '<td>' +
                    '<div class="fw-semibold text-dark">' + escHtml(cRow.label || '-') + '</div>' +
                    '<div class="text-muted font-monospace" style="font-size: 10px;">' + escHtml(cRow.canonical_code || '-') + rawText + '</div>' +
                '</td>' +
                '<td class="text-center">' + stBadge + '</td>' +
                '<td class="text-end fw-bold text-dark font-monospace">' + Number(cRow.total || 0).toLocaleString('id-ID') + ' <span class="text-muted fw-normal" style="font-size:10px;">(' + pct.toFixed(1) + '%)</span></td>' +
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
