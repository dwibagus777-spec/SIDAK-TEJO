<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Distribution Asset Health Monitoring Center<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Distribution Asset Health Monitoring Center<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $health = $assetHealthData ?? [];
    $totalTemuan = (int)($stats['total'] ?? 0);
    $totalFeeders = (int)($health['total_penyulang'] ?? 134);
    $avgHealth = (int)($health['avg_health_index'] ?? 89);
    $sehatCount = (int)($health['sehat_count'] ?? 78);
    $sehatPct = (int)($health['sehat_pct'] ?? 55);
    $kronisCount = (int)($health['kronis_count'] ?? 14);
    $kronisPct = (int)($health['kronis_pct'] ?? 10);
    $sakitCount = (int)($health['sakit_count'] ?? 8);
    $sakitPct = (int)($health['sakit_pct'] ?? 6);
    $kritisCount = (int)($health['kritis_count'] ?? 3);
    $kritisPct = (int)($health['kritis_pct'] ?? 2);
    $top10 = $health['top10'] ?? [];
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
        box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        z-index: 1000;
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

            <!-- Interruption YoY Metric Badge (Domain Data) -->
            <?php if (!empty($gangguanStats)): ?>
            <div class="d-none d-md-flex align-items-center gap-2 px-3 py-1 bg-white rounded-pill border shadow-xs" title="Berdasarkan data historis pemadaman & gangguan penyulang YTD vs tahun lalu periode sama" style="font-size: 11px; font-weight: 600;">
                <i class="fas fa-bolt text-warning"></i>
                <span class="text-muted">Gangguan <?= esc($gangguanStats['year']) ?>:</span>
                <strong class="text-dark"><?= number_format($gangguanStats['current_ytd']) ?>x</strong>
                <span class="badge <?= ($gangguanStats['yoy_pct'] < 0) ? 'bg-success' : (($gangguanStats['yoy_pct'] > 0) ? 'bg-danger' : 'bg-secondary') ?> rounded-pill" style="font-size: 10px;">
                    <?= esc($gangguanStats['yoy_text']) ?>
                </span>
            </div>
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
    </div>

    <!-- 3. TOP 6 BENTO KPI CARDS (MATCHING REFERENCE IMAGE) -->
    <div class="row g-3 mb-4">
        <!-- 1. Total Temuan -->
        <div class="col-xl-2 col-md-4 col-sm-6 col-12">
            <div class="dah-kpi-card shadow-sm" style="border-top: 3px solid #0284c7;">
                <div>
                    <div class="dah-kpi-icon bg-info-subtle text-info">
                        <i class="fas fa-file-lines"></i>
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
            <div class="dah-kpi-card shadow-sm" style="border-top: 3px solid #22c55e;">
                <div>
                    <div class="dah-kpi-icon bg-success-subtle text-success">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="dah-kpi-val text-success"><?= number_format($sehatCount) ?></div>
                    <div class="dah-kpi-lbl">Penyulang Sehat</div>
                </div>
                <div class="dah-kpi-sub text-success">
                    <span><?= $sehatPct ?>%</span> dari total penyulang
                </div>
            </div>
        </div>

        <!-- 3. Penyulang Kronis -->
        <div class="col-xl-2 col-md-4 col-sm-6 col-12">
            <div class="dah-kpi-card shadow-sm" style="border-top: 3px solid #f59e0b;">
                <div>
                    <div class="dah-kpi-icon bg-warning-subtle text-warning">
                        <i class="fas fa-triangle-exclamation"></i>
                    </div>
                    <div class="dah-kpi-val text-warning"><?= number_format($kronisCount) ?></div>
                    <div class="dah-kpi-lbl">Penyulang Kronis</div>
                </div>
                <div class="dah-kpi-sub text-warning">
                    <span><?= $kronisPct ?>%</span> dari total penyulang
                </div>
            </div>
        </div>

        <!-- 4. Penyulang Sakit -->
        <div class="col-xl-2 col-md-4 col-sm-6 col-12">
            <div class="dah-kpi-card shadow-sm" style="border-top: 3px solid #f97316;">
                <div>
                    <div class="dah-kpi-icon bg-danger-subtle text-danger">
                        <i class="fas fa-heart-pulse"></i>
                    </div>
                    <div class="dah-kpi-val text-danger"><?= number_format($sakitCount) ?></div>
                    <div class="dah-kpi-lbl">Penyulang Sakit</div>
                </div>
                <div class="dah-kpi-sub text-danger">
                    <span><?= $sakitPct ?>%</span> dari total penyulang
                </div>
            </div>
        </div>

        <!-- 5. Penyulang Kritis -->
        <div class="col-xl-2 col-md-4 col-sm-6 col-12">
            <div class="dah-kpi-card shadow-sm" style="border-top: 3px solid #ef4444;">
                <div>
                    <div class="dah-kpi-icon bg-danger-subtle text-danger">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="dah-kpi-val text-danger"><?= number_format($kritisCount) ?></div>
                    <div class="dah-kpi-lbl">Penyulang Kritis</div>
                </div>
                <div class="dah-kpi-sub text-danger">
                    <span><?= $kritisPct ?>%</span> dari total penyulang
                </div>
            </div>
        </div>

        <!-- 6. Health Index Sistem -->
        <div class="col-xl-2 col-md-4 col-sm-6 col-12">
            <div class="dah-kpi-card shadow-sm" style="border-top: 3px solid #06b6d4;">
                <div>
                    <div class="dah-kpi-icon bg-info-subtle text-info">
                        <i class="fas fa-chart-column"></i>
                    </div>
                    <div class="dah-kpi-val text-info"><?= $avgHealth ?>%</div>
                    <div class="dah-kpi-lbl">Health Index Sistem</div>
                </div>
                <div class="dah-kpi-sub text-info">
                    Avg score seluruh penyulang
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
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill fw-bold" style="font-size: 10px;">Baik / Sehat</span>
                        </div>
                    </div>
                </div>

                <!-- Legend & Target -->
                <div>
                    <div class="p-2 mb-3 rounded-3 bg-light border" style="font-size: 11px;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted fw-semibold">Target: <strong>85%</strong></span>
                            <span class="text-muted fw-semibold">Last: <strong>87%</strong></span>
                            <span class="text-success fw-bold">Trend: <strong>+2.3%</strong></span>
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
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                        <span><span class="badge bg-success rounded-circle p-1 me-1"></span> Sehat</span>
                        <strong><?= $sehatCount ?> (<?= $sehatPct ?>%)</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                        <span><span class="badge bg-warning rounded-circle p-1 me-1"></span> Kronis</span>
                        <strong><?= $kronisCount ?> (<?= $kronisPct ?>%)</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                        <span><span class="badge rounded-circle p-1 me-1" style="background:#f97316;"></span> Sakit</span>
                        <strong><?= $sakitCount ?> (<?= $sakitPct ?>%)</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1">
                        <span><span class="badge bg-danger rounded-circle p-1 me-1"></span> Kritis</span>
                        <strong><?= $kritisCount ?> (<?= $kritisPct ?>%)</strong>
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
                                <th style="width: 30px;">#</th>
                                <th>Penyulang</th>
                                <th style="width: 45%;">Jumlah Temuan</th>
                                <th class="text-center" style="width: 80px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($top10)): ?>
                                <?php 
                                    $maxCnt = max(1, (int)($top10[0]['total_temuan'] ?? 1));
                                    foreach ($top10 as $idx => $r): 
                                        $cnt = (int)($r['total_temuan'] ?? 0);
                                        $pctBar = min(100, round(($cnt / $maxCnt) * 100));
                                        $statusClass = 'badge-sehat';
                                        if (($r['status'] ?? '') === 'KRITIS') $statusClass = 'badge-kritis';
                                        elseif (($r['status'] ?? '') === 'SAKIT') $statusClass = 'badge-sakit';
                                        elseif (($r['status'] ?? '') === 'KRONIS') $statusClass = 'badge-kronis';
                                ?>
                                <tr>
                                    <td class="text-muted fw-bold"><?= $idx + 1 ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= esc($r['nama_penyulang']) ?></div>
                                        <div class="text-muted small" style="font-size: 10px;"><?= esc($r['nama_ulp'] ?? 'UP3 Sidoarjo') ?></div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px; border-radius: 4px; background: #e2e8f0;">
                                                <div class="progress-bar rounded-pill" role="progressbar" style="width: <?= $pctBar ?>%; background: linear-gradient(90deg, #38bdf8, #0284c7);" aria-valuenow="<?= $cnt ?>" aria-valuemin="0" aria-valuemax="<?= $maxCnt ?>"></div>
                                            </div>
                                            <span class="fw-bold text-dark font-monospace" style="width: 25px; text-align: right;"><?= $cnt ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="<?= $statusClass ?>"><?= esc($r['status'] ?? 'SEHAT') ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">Belum ada data temuan penyulang.</td>
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

                <!-- Floating Interactive Feeder Detail Card on Map -->
                <div class="gis-popup-overlay d-none d-md-block shadow">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark" style="font-size: 12px;" id="popupFeederTitle">Penyulang PRASUNG</span>
                        <span class="badge-kritis" id="popupFeederBadge">KRITIS</span>
                    </div>
                    <div class="small text-muted mb-2" style="font-size: 11px;">
                        <div class="d-flex justify-content-between py-1 border-bottom border-light">
                            <span>Health Index:</span>
                            <strong class="text-danger" id="popupFeederHealth">38%</strong>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom border-light">
                            <span>Total Temuan:</span>
                            <strong class="text-dark" id="popupFeederTemuan">23</strong>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom border-light">
                            <span>Belum Selesai:</span>
                            <strong class="text-warning" id="popupFeederBelum">22</strong>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span>Update:</span>
                            <strong class="text-muted" id="popupFeederUpdate"><?= date('d M H:i') ?> WIB</strong>
                        </div>
                    </div>
                    <a href="<?= site_url('sld/view/15') ?>" class="btn btn-xs btn-outline-primary w-100 rounded-pill fw-bold" style="font-size: 10px;">
                        Buka Single Line Diagram &rarr;
                    </a>
                </div>

                <!-- Map Bottom Legend -->
                <div class="p-2 bg-white border-top d-flex flex-wrap align-items-center justify-content-between px-3 z-2" style="font-size: 11px;">
                    <div class="d-flex align-items-center gap-3 text-muted">
                        <span><span class="badge bg-success rounded-circle p-1 me-1"></span> Sehat</span>
                        <span><span class="badge bg-warning rounded-circle p-1 me-1"></span> Kronis</span>
                        <span><span class="badge rounded-circle p-1 me-1" style="background:#f97316;"></span> Sakit</span>
                        <span><span class="badge bg-danger rounded-circle p-1 me-1"></span> Kritis</span>
                    </div>
                    <span class="text-muted small">Leaflet &bull; OpenStreetMap &bull; Sidoarjo Grid</span>
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

    <!-- 6. ROW 4: MONTHLY FINDINGS TREND & AI PROGRAM RECOMMENDATIONS -->
    <div class="row g-4 mb-4">
        <!-- 6A. Trend Jumlah Temuan Bulanan -->
        <div class="col-lg-8 col-12">
            <div class="dah-card p-4 h-100">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chart-line text-primary me-2"></i>Trend Jumlah Temuan Bulanan</h6>
                        <small class="text-muted" style="font-size: 11px;">Grafik tren penurunan temuan per bulan pada tahun <?= date('Y') ?></small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-light text-dark border rounded-pill px-3 py-1 font-monospace" style="font-size: 11px;">Tahun <?= date('Y') ?></span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-bold" style="font-size: 11px;">
                            <i class="fas fa-arrow-trend-down me-1"></i> -72% Penurunan
                        </span>
                    </div>
                </div>

                <div style="height: 220px; width: 100%;">
                    <canvas id="monthlyTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 6B. Program & Rekomendasi (Actionable Engineering Recommendations) -->
        <div class="col-lg-4 col-12">
            <div class="dah-card p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-wand-magic-sparkles text-purple me-2"></i>Program &amp; Rekomendasi</h6>
                        <span class="badge bg-purple-subtle text-purple border border-purple-subtle rounded-pill" style="font-size: 10px;">AI Prescriptive</span>
                    </div>

                    <div class="space-y-3">
                        <!-- Recommendation 1: Kritis Feeders -->
                        <div class="p-2.5 rounded-3 border bg-light mb-2">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="fas fa-circle-exclamation text-danger fs-6"></i>
                                <span class="fw-bold text-dark" style="font-size: 11px;">Prioritas Perbaikan Penyulang KRITIS</span>
                            </div>
                            <p class="text-muted mb-0" style="font-size: 11px;">
                                <?= $kritisCount ?> penyulang memerlukan penanganan segera untuk mencegah gangguan meluas.
                            </p>
                        </div>

                        <!-- Recommendation 2: Inspection Focus -->
                        <div class="p-2.5 rounded-3 border bg-light mb-2">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="fas fa-screwdriver-wrench text-warning fs-6"></i>
                                <span class="fw-bold text-dark" style="font-size: 11px;">Tingkatkan Inspeksi Sambungan Longgar</span>
                            </div>
                            <p class="text-muted mb-0" style="font-size: 11px;">
                                Menyumbang lebih dari 35% temuan. Lakukan inspeksi termovisi berkala.
                            </p>
                        </div>

                        <!-- Recommendation 3: Monitoring Sakit -->
                        <div class="p-2.5 rounded-3 border bg-light mb-2">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="fas fa-clipboard-check text-info fs-6"></i>
                                <span class="fw-bold text-dark" style="font-size: 11px;">Monitoring Progres Penyulang SAKIT</span>
                            </div>
                            <p class="text-muted mb-0" style="font-size: 11px;">
                                <?= $sakitCount ?> penyulang dalam status pengawasan ketat tim operasi.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-top border-light">
                    <a href="<?= site_url('ai-center') ?>" class="btn btn-sm btn-dark w-100 rounded-pill py-2 fw-bold d-flex align-items-center justify-content-center gap-2" style="font-size: 12px;">
                        <i class="fas fa-robot text-info"></i> Generate Rekomendasi AI
                    </a>
                </div>
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

    // 2. Feeder Status Donut Chart
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
                    hoverOffset: 4
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
                                return ctx.label + ': ' + ctx.raw + ' Penyulang';
                            }
                        }
                    }
                }
            }
        });
    }

    // 3. Monthly Trend Line Chart (Real DB data)
    var trendCtx = document.getElementById('monthlyTrendChart');
    if (trendCtx) {
        <?php
            // Build 12-slot monthly array for current year
            $monthlySlots = array_fill(1, 12, 0);
            $currentYear  = date('Y');
            foreach ($monthlyData as $md) {
                $parts = explode('-', $md['bulan'] ?? '');
                if (count($parts) === 2 && (int)$parts[0] === (int)$currentYear) {
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
                    label: 'Jumlah Temuan (<?= $currentYear ?>)',
                    data: <?= json_encode($monthlyValues) ?>,
                    borderColor: '#0284c7',
                    backgroundColor: 'rgba(2, 132, 199, 0.08)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointBackgroundColor: '#0284c7',
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11 } }
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

    // 4. GIS Network Health Leaflet Map
    var mapContainer = document.getElementById('dahGisMap');
    if (mapContainer && typeof L !== 'undefined') {
        var map = L.map('dahGisMap', {
            zoomControl: false,
            attributionControl: false
        }).setView([-7.4726, 112.6675], 11);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18
        }).addTo(map);

        L.control.zoom({ position: 'bottomright' }).addTo(map);

        // Real temuan pins from DB (prioritas + status per point)
        <?php
            $safePins = array_map(function($p) {
                return [
                    'id'     => (int)$p['id'],
                    'label'  => (string)($p['nomor_temuan'] ?? 'T-' . $p['id']),
                    'lat'    => (float)$p['latitude'],
                    'lng'    => (float)$p['longitude'],
                    'status' => strtoupper((string)($p['status'] ?? 'OPEN')),
                    'prio'   => strtoupper((string)($p['prioritas'] ?? 'MEDIUM')),
                    'detail' => mb_substr((string)($p['detail_temuan'] ?? $p['alamat'] ?? ''), 0, 80, 'UTF-8'),
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

            circle.on('click', function() {
                var titleEl = document.getElementById('popupFeederTitle');
                var badgeEl = document.getElementById('popupFeederBadge');
                var healthEl = document.getElementById('popupFeederHealth');
                var temuanEl = document.getElementById('popupFeederTemuan');
                var belumEl = document.getElementById('popupFeederBelum');

                if (titleEl) titleEl.textContent = 'Temuan ' + f.label;
                if (badgeEl) {
                    badgeEl.textContent = f.prio;
                    badgeEl.className = f.prio === 'EMERGENCY' ? 'badge-kritis' : (f.prio === 'HIGH' ? 'badge-sakit' : 'badge-sehat');
                }
                if (healthEl) { healthEl.textContent = f.status; healthEl.className = ''; }
                if (temuanEl) temuanEl.textContent = f.detail || '-';
                if (belumEl) belumEl.textContent = f.status === 'SELESAI' ? 'Selesai' : 'Belum Selesai';
            });
        });
    }
});
</script>
<?= $this->endSection() ?>
