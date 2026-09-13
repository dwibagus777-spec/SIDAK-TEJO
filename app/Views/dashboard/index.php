<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Enterprise Monitoring Center PLN<?= $this->endSection() ?>
<?= $this->section('page_title') ?>SIDAK TEJO Enterprise Monitoring Center PLN<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Local dashboard layout helpers (leveraging .sidak-bento-* tokens in custom_modern.css) */
    .emc-container, .sidak-bento-container {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .activity-stream-item {
        position: relative;
        padding-left: 24px;
        padding-bottom: 14px;
        border-left: 2px solid #e2e8f0;
    }
    .activity-stream-item:last-child {
        padding-bottom: 0;
        border-left: 2px solid transparent;
    }
    .activity-stream-item::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 2px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #0284c7;
        border: 2px solid #ffffff;
    }
</style>

<div class="sidak-bento-container emc-container container-fluid py-3">

    <!-- 1. SECTION A: EXECUTIVE WELCOME BANNER -->
    <div class="sidak-bento-card sidak-bento-welcome emc-card p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="Logo PLN SIDAK TEJO" style="max-height: 52px;" class="bg-white p-1 rounded-3 shadow-sm">
                <div>
                    <?php
                        $hour = (int)date('H');
                        if ($hour >= 0 && $hour < 11) $greeting = 'Selamat Pagi';
                        elseif ($hour >= 11 && $hour < 15) $greeting = 'Selamat Siang';
                        elseif ($hour >= 15 && $hour < 18) $greeting = 'Selamat Sore';
                        else $greeting = 'Selamat Malam';
                    ?>
                    <h4 class="fw-bold mb-1 text-white">
                        <?= $greeting ?>, <span class="text-warning"><?= esc(session()->get('user_name') ?: 'Mas Dwi') ?></span> 👋
                    </h4>
                    <p class="text-white-50 small mb-0">
                        Hari ini ada <strong>18 pekerjaan aktif</strong> &middot; ULP: <strong><?= esc(session()->get('user_ulp_nama') ?: 'UP3 Sidoarjo') ?></strong> &middot; Role: <strong><?= esc(get_role_label((string)(session()->get('user_role') ?: 'administrator'))) ?></strong>
                    </p>
                </div>
            </div>

            <!-- Server Time & Date -->
            <div class="text-end d-none d-md-block">
                <h3 class="fw-bold font-monospace mb-0 text-warning" id="emc-clock"><?= date('H:i:s') ?> WIB</h3>
                <small class="text-white-50"><i class="fas fa-calendar-day me-1"></i> <?= date('l, d F Y') ?></small>
            </div>
        </div>

        <!-- Permanent Admin Motivation Quote Banner -->
        <hr class="border-secondary opacity-25 my-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2" style="font-size: 12px;">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-quote-left text-warning fs-5"></i>
                <span id="permanent-motivation-text" class="fst-italic text-white">"<?= esc(get_daily_announcement()) ?>"</span>
                <button type="button" class="btn btn-xs btn-outline-light rounded-circle ms-2" onclick="editMotivation()" title="Edit Motivasi Admin"><i class="fas fa-pencil"></i></button>
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">
                <span class="status-pulse-live me-1"></span> Live Monitoring Center PLN
            </span>
        </div>
    </div>

    <!-- 2. SECTION B: TOUCH-FRIENDLY QUICK ACTIONS -->
    <div class="sidak-bento-action-bar quick-action-bar-emc">
        <?php if ($canInput ?? check_role(['administrator', 'admin_ulp', 'inspeksi'])): ?>
        <a href="<?= site_url('temuan/create') ?>" class="sidak-bento-action-pill quick-emc-btn">
            <i class="fas fa-plus-circle text-success fs-5"></i>
            <span>Input Temuan</span>
        </a>
        <?php endif; ?>
        <a href="<?= site_url('temuan') ?>" class="sidak-bento-action-pill quick-emc-btn">
            <i class="fas fa-list-check text-primary fs-5"></i>
            <span>Data Temuan</span>
        </a>
        <?php if ($canEdit ?? !check_role(['supervisor_up3'])): ?>
        <a href="<?= site_url('temuan/update-pekerjaan') ?>" class="sidak-bento-action-pill quick-emc-btn">
            <i class="fas fa-pen-to-square text-warning fs-5"></i>
            <span>Update Pekerjaan</span>
        </a>
        <?php endif; ?>
        <a href="javascript:void(0)" onclick="triggerQrScanModal()" class="sidak-bento-action-pill quick-emc-btn">
            <i class="fas fa-qrcode text-purple fs-5"></i>
            <span>QR Scanner</span>
        </a>
        <a href="<?= site_url('ai-copilot') ?>" class="sidak-bento-action-pill quick-emc-btn">
            <i class="fas fa-robot text-info fs-5"></i>
            <span>Voice AI</span>
        </a>
        <a href="<?= site_url('temuan/terdekat') ?>" class="sidak-bento-action-pill quick-emc-btn">
            <i class="fas fa-location-crosshairs text-danger fs-5"></i>
            <span>Lokasi Terdekat</span>
        </a>
    </div>

    <!-- 3. SECTION C: 8-CARD BENTO KPI GRID -->
    <?php
        $dailyTarget = max(1, (int)($stats['target_harian'] ?? 25));
        $dailyDone = (int)($stats['hari_ini'] ?? $stats['selesai_hari_ini'] ?? ($stats['selesai'] ?? 0));
        $dailyPct = min(100, (int)round(($dailyDone / $dailyTarget) * 100));
    ?>
    <div class="sidak-bento-kpi-grid mb-4">
        <!-- 1. Jumlah Temuan -->
        <a href="<?= site_url('temuan') ?>" class="sidak-bento-kpi-card sidak-bento-kpi-primary kpi-emc-card kpi-drilldown-link">
            <div class="d-flex justify-content-between align-items-center">
                <span class="sidak-bento-lbl kpi-emc-lbl">Jumlah Temuan</span>
                <i class="fas fa-arrow-up-right-from-square opacity-50"></i>
            </div>
            <div class="sidak-bento-val kpi-emc-val mt-2" id="kpi-total-temuan"><?= number_format($stats['total'] ?? 0) ?></div>
            <div class="sidak-bento-sub">
                <span>Total Inspeksi Fisik</span>
                <i class="fas fa-chevron-right opacity-75"></i>
            </div>
        </a>

        <!-- 2. Emergency -->
        <a href="<?= site_url('temuan?prioritas=EMERGENCY') ?>" class="sidak-bento-kpi-card sidak-bento-kpi-danger kpi-emc-card kpi-drilldown-link">
            <div class="d-flex justify-content-between align-items-center">
                <span class="sidak-bento-lbl kpi-emc-lbl">Emergency</span>
                <i class="fas fa-triangle-exclamation opacity-50"></i>
            </div>
            <div class="sidak-bento-val kpi-emc-val mt-2" id="kpi-emergency"><?= number_format($stats['emergency'] ?? 0) ?></div>
            <div class="sidak-bento-sub">
                <span>Tindak Lanjut Darurat</span>
                <i class="fas fa-chevron-right opacity-75"></i>
            </div>
        </a>

        <!-- 3. High Priority -->
        <a href="<?= site_url('temuan?prioritas=HIGH') ?>" class="sidak-bento-kpi-card sidak-bento-kpi-warning kpi-emc-card kpi-drilldown-link">
            <div class="d-flex justify-content-between align-items-center">
                <span class="sidak-bento-lbl kpi-emc-lbl">High Priority</span>
                <i class="fas fa-clock opacity-50"></i>
            </div>
            <div class="sidak-bento-val kpi-emc-val mt-2" id="kpi-high"><?= number_format($stats['high'] ?? 0) ?></div>
            <div class="sidak-bento-sub">
                <span>SLA 7 Hari</span>
                <i class="fas fa-chevron-right opacity-75"></i>
            </div>
        </a>

        <!-- 4. Medium Priority -->
        <a href="<?= site_url('temuan?prioritas=MEDIUM') ?>" class="sidak-bento-kpi-card sidak-bento-kpi-info kpi-emc-card kpi-drilldown-link">
            <div class="d-flex justify-content-between align-items-center">
                <span class="sidak-bento-lbl kpi-emc-lbl">Medium Priority</span>
                <i class="fas fa-calendar-check opacity-50"></i>
            </div>
            <div class="sidak-bento-val kpi-emc-val mt-2" id="kpi-medium"><?= number_format($stats['medium'] ?? 0) ?></div>
            <div class="sidak-bento-sub">
                <span>SLA 31 Hari</span>
                <i class="fas fa-chevron-right opacity-75"></i>
            </div>
        </a>

        <!-- 5. Belum Selesai -->
        <a href="<?= site_url('temuan?status=BELUM') ?>" class="sidak-bento-kpi-card sidak-bento-kpi-dark kpi-emc-card kpi-drilldown-link">
            <div class="d-flex justify-content-between align-items-center">
                <span class="sidak-bento-lbl kpi-emc-lbl">Belum Selesai</span>
                <i class="fas fa-hourglass-half opacity-50"></i>
            </div>
            <div class="sidak-bento-val kpi-emc-val mt-2" id="kpi-belum"><?= number_format($stats['belum'] ?? 0) ?></div>
            <div class="sidak-bento-sub">
                <span>Outstanding</span>
                <i class="fas fa-chevron-right opacity-75"></i>
            </div>
        </a>

        <!-- 6. WO Aktif -->
        <a href="<?= site_url('work-orders?status=AKTIF') ?>" class="sidak-bento-kpi-card sidak-bento-kpi-cyan kpi-emc-card kpi-drilldown-link">
            <div class="d-flex justify-content-between align-items-center">
                <span class="sidak-bento-lbl kpi-emc-lbl">WO Aktif</span>
                <i class="fas fa-bolt opacity-50"></i>
            </div>
            <div class="sidak-bento-val kpi-emc-val mt-2" id="kpi-wo-aktif"><?= number_format($woStats['aktif'] ?? 0) ?></div>
            <div class="sidak-bento-sub">
                <span>SPK Work Order Aktif</span>
                <i class="fas fa-chevron-right opacity-75"></i>
            </div>
        </a>

        <!-- 7. Sudah Selesai -->
        <a href="<?= site_url('temuan?status=SELESAI') ?>" class="sidak-bento-kpi-card sidak-bento-kpi-success kpi-emc-card kpi-drilldown-link">
            <div class="d-flex justify-content-between align-items-center">
                <span class="sidak-bento-lbl kpi-emc-lbl">Sudah Selesai</span>
                <i class="fas fa-circle-check opacity-50"></i>
            </div>
            <div class="sidak-bento-val kpi-emc-val mt-2" id="kpi-selesai"><?= number_format($stats['selesai'] ?? 0) ?></div>
            <div class="sidak-bento-sub">
                <span>Tuntas 100%</span>
                <i class="fas fa-chevron-right opacity-75"></i>
            </div>
        </a>

        <!-- 8. Target Harian -->
        <div class="sidak-bento-kpi-card sidak-bento-kpi-target p-3 d-flex flex-column justify-content-between">
            <div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="sidak-bento-lbl text-muted">TARGET HARIAN</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill" id="kpi-target-harian-pct"><?= $dailyPct ?>%</span>
                </div>
                <div class="d-flex justify-content-between align-items-baseline mt-2">
                    <div class="sidak-bento-val text-primary" id="kpi-target-harian-text"><?= number_format($dailyDone) ?> <span class="fs-5 text-muted fw-semibold">/ <?= number_format($dailyTarget) ?></span></div>
                </div>
                <div class="progress mt-2" style="height: 8px; border-radius: 6px; background-color: #e2e8f0;">
                    <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: <?= $dailyPct ?>%;" id="kpi-target-harian-bar" aria-valuenow="<?= $dailyPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
            <small class="text-muted d-block mt-2" style="font-size: 11px;">
                <i class="fas fa-bullseye text-primary me-1"></i> Realisasi Inspeksi &amp; Pemulihan
            </small>
        </div>
    </div>

    <!-- 4. SECTION D: MINI GIS MAP & OPERATIONAL FINDINGS FEED -->
    <div class="row g-4 mb-4">
        <!-- Mini GIS Map -->
        <div class="col-lg-8 col-12">
            <div class="sidak-bento-card emc-card p-4 h-100">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="sidak-bento-icon-box bg-success-subtle text-success">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">Mini GIS - Sebaran Temuan Lapangan</h5>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small">
                                <i class="fas fa-map-pin me-1"></i><?= number_format(count($mapPins ?? [])) ?> Titik Terpetakan
                            </span>
                        </div>
                    </div>
                    <a href="<?= site_url('gis') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold" id="gis-full-btn">
                        <i class="fas fa-expand me-1"></i> Full Map Mode
                    </a>
                </div>
                <div id="emc-mini-map" style="height: 290px; border-radius: 14px;" class="border shadow-sm"></div>
            </div>
        </div>

        <!-- Realtime Operational Feed Panel -->
        <div class="col-lg-4 col-12">
            <div class="sidak-bento-card emc-card p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="sidak-bento-icon-box bg-primary-subtle text-primary">
                                <i class="fas fa-satellite-dish"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0">Aktivitas Lapangan Terkini</h5>
                                <small class="text-muted" style="font-size: 11px;">Data Temuan Operasional Riil</small>
                            </div>
                        </div>
                        <span class="badge bg-primary-subtle text-primary rounded-pill small">Live Data</span>
                    </div>
                    
                    <div class="sidak-bento-feed-list">
                        <?php 
                        $recentPins = !empty($mapPins) ? array_slice($mapPins, 0, 4) : [];
                        if (!empty($recentPins)): 
                            foreach ($recentPins as $item): 
                                $prio = strtoupper((string)($item['prioritas'] ?? 'MEDIUM'));
                                $badgeClass = $prio === 'EMERGENCY' ? 'bg-danger text-white' : ($prio === 'HIGH' ? 'bg-warning text-dark' : 'bg-info-subtle text-primary');
                                $status = strtoupper((string)($item['status'] ?? 'BELUM'));
                                $statusBadge = $status === 'SELESAI' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle';
                                $nomor = !empty($item['nomor_temuan']) ? $item['nomor_temuan'] : 'STJ-' . ($item['id'] ?? '0');
                                $title = !empty($item['judul']) ? $item['judul'] : (!empty($item['penyulang_nama']) ? 'Penyulang ' . $item['penyulang_nama'] : 'Temuan Inspeksi');
                        ?>
                            <div class="sidak-bento-feed-item mb-2 p-2 rounded-3 border d-flex justify-content-between align-items-center">
                                <div class="me-2 overflow-hidden">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold text-dark small text-truncate" style="max-width: 140px;"><?= esc($nomor) ?></span>
                                        <span class="badge <?= $badgeClass ?> px-1 py-0" style="font-size: 9px;"><?= esc($prio) ?></span>
                                    </div>
                                    <small class="text-muted d-block text-truncate" style="font-size: 11px;">
                                        <?= esc($title) ?>
                                    </small>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <span class="badge <?= $statusBadge ?> px-2 py-0 mb-1 d-inline-block" style="font-size: 9px;"><?= esc($status) ?></span>
                                    <a href="<?= site_url('temuan/detail/' . ($item['id'] ?? 0)) ?>" class="d-block small text-primary fw-bold text-decoration-none" style="font-size: 11px;">
                                        Lihat <i class="fas fa-chevron-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2 opacity-50"></i>
                                <p class="small mb-0">Belum ada temuan terpetakan terkini.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pt-2">
                    <a href="<?= site_url('audit-log') ?>" class="btn btn-sm btn-outline-secondary w-100 rounded-pill fw-semibold" style="font-size: 11px;">
                        <i class="fas fa-history me-1"></i> Buka Log Aktivitas Lengkap
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. SECTION E: SLA MONITOR WIDGET -->
    <div class="sidak-bento-card emc-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <div class="sidak-bento-icon-box bg-warning-subtle text-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-dark mb-0">SLA Monitoring Widget</h5>
                    <small class="text-muted" style="font-size: 11px;">Kepatuhan Batas Waktu Tindak Lanjut Temuan Lapangan</small>
                </div>
            </div>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill small">SLA Korporat</span>
        </div>
        <div class="row g-3 text-center">
            <div class="col-lg-3 col-md-6 col-12">
                <a href="<?= site_url('temuan?prioritas=EMERGENCY') ?>" class="sidak-bento-sla-card bg-danger-subtle border border-danger-subtle">
                    <small class="text-danger fw-bold d-block text-uppercase" style="font-size: 10px; letter-spacing: 0.5px;">EMERGENCY (SLA 3 Hari)</small>
                    <h3 class="fw-bold text-danger my-1" id="sla-val-emergency"><?= number_format($stats['emergency'] ?? 0) ?></h3>
                    <span class="small text-danger opacity-75" style="font-size: 11px;">Penanganan Kritis <i class="fas fa-arrow-right ms-1"></i></span>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <a href="<?= site_url('temuan?prioritas=HIGH') ?>" class="sidak-bento-sla-card bg-warning-subtle border border-warning-subtle">
                    <small class="text-warning fw-bold d-block text-uppercase" style="font-size: 10px; letter-spacing: 0.5px;">HIGH (SLA 7 Hari)</small>
                    <h3 class="fw-bold text-warning my-1" id="sla-val-high"><?= number_format($stats['high'] ?? 0) ?></h3>
                    <span class="small text-warning opacity-75" style="font-size: 11px;">Prioritas Tinggi <i class="fas fa-arrow-right ms-1"></i></span>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <a href="<?= site_url('temuan?prioritas=MEDIUM') ?>" class="sidak-bento-sla-card bg-primary-subtle border border-primary-subtle">
                    <small class="text-primary fw-bold d-block text-uppercase" style="font-size: 10px; letter-spacing: 0.5px;">MEDIUM (SLA 31 Hari)</small>
                    <h3 class="fw-bold text-primary my-1" id="sla-val-medium"><?= number_format($stats['medium'] ?? 0) ?></h3>
                    <span class="small text-primary opacity-75" style="font-size: 11px;">Jadwal Terencana <i class="fas fa-arrow-right ms-1"></i></span>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <a href="<?= site_url('pekerjaan') ?>" class="sidak-bento-sla-card bg-danger-subtle border border-danger">
                    <small class="text-danger fw-bold d-block text-uppercase" style="font-size: 10px; letter-spacing: 0.5px;">SLA MELEWATI (OVERDUE)</small>
                    <h3 class="fw-bold text-danger my-1" id="sla-val-overdue"><?= number_format($woStats['overdue'] ?? 0) ?></h3>
                    <span class="small text-danger fw-bold" style="font-size: 11px;">Perlu Eskalasi Segera <i class="fas fa-exclamation-triangle ms-1"></i></span>
                </a>
            </div>
        </div>
    </div>

    <!-- 6. SECTION F: EXECUTIVE ANALYTICS CTA -->
    <div class="sidak-bento-cta-card mb-4 shadow-sm">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-white bg-opacity-10 rounded-3 border border-white border-opacity-20 text-warning fs-2">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-white mb-1">Executive Analytics &amp; Strategic Decision Center</h5>
                    <p class="text-white-50 small mb-0">
                        Pantau perbandingan performa lintas 3 ULP, rasio penyelesaian penyulang prioritas, tren gangguan periodik, serta kepatuhan SLA korporat secara komprehensif.
                    </p>
                </div>
            </div>
            <div>
                <a href="<?= site_url('executive-dashboard') ?>" class="sidak-bento-cta-btn">
                    <span>Buka Executive Dashboard</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Realtime Clock
    setInterval(function() {
        var now = new Date();
        var h = String(now.getHours()).padStart(2, '0');
        var m = String(now.getMinutes()).padStart(2, '0');
        var s = String(now.getSeconds()).padStart(2, '0');
        var clockEl = document.getElementById('emc-clock');
        if (clockEl) clockEl.innerText = h + ':' + m + ':' + s + ' WIB';
    }, 1000);

    window.editMotivation = function() {
        var currentText = ($('#permanent-motivation-text').text() || '').replace(/^"|"$/g, '').trim();
        var input = prompt("Masukkan Kata-Kata Motivasi Admin Baru (Disimpan Permanen ke Database):", currentText);
        if (input !== null && input.trim() !== '') {
            const newText = input.trim();
            $.ajax({
                url: '<?= site_url('setting/update-announcement') ?>',
                type: 'POST',
                data: {
                    '<?= csrf_token() ?>': '<?= csrf_hash() ?>',
                    'daily_motivation': newText
                },
                dataType: 'JSON',
                success: function(res) {
                    if (res && res.success) {
                        var motivEl = document.getElementById('permanent-motivation-text');
                        if (motivEl) motivEl.innerText = '"' + newText + '"';
                        var mMotivEl = document.getElementById('m-permanent-motivation');
                        if (mMotivEl) mMotivEl.innerText = '"' + newText + '"';
                        var tickerEl = document.getElementById('running-announcement-text');
                        if (tickerEl) tickerEl.innerText = newText;
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Motivasi Admin Saved!',
                                text: 'Kata-kata motivasi harian berhasil tersimpan permanen di database server.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    } else {
                        alert(res ? res.message : 'Gagal menyimpan motivasi.');
                    }
                },
                error: function(xhr) {
                    alert('Gagal terhubung ke server untuk menyimpan motivasi.');
                }
            });
        }
    };

    // Mini GIS Map
    var miniMapEl = document.getElementById('emc-mini-map');
    if (miniMapEl && typeof L !== 'undefined') {
        var map = L.map('emc-mini-map').setView([-7.4478, 112.7183], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var pins = <?= json_encode($mapPins ?? []) ?>;
        pins.forEach(function(p) {
            if (p.latitude && p.longitude) {
                var color = '#10b981'; // Hijau / Normal
                if (p.prioritas === 'EMERGENCY') color = '#ef4444'; // Merah
                else if (p.prioritas === 'HIGH') color = '#f59e0b'; // Kuning

                var circle = L.circleMarker([p.latitude, p.longitude], {
                    radius: 7, fillColor: color, color: '#fff', weight: 2, fillOpacity: 0.9
                }).addTo(map);

                var tipText = '<strong>' + (p.nomor_temuan || ('Temuan #' + p.id)) + '</strong><br><span style="font-size:11px;">Prioritas: ' + (p.prioritas || '-') + '</span>';
                circle.bindTooltip(tipText, { direction: 'top', offset: [0, -4] });

                circle.on('click', function() {
                    window.location.href = "<?= site_url('temuan/detail/') ?>" + p.id;
                });
            }
        });
    }
});
</script>
<?= $this->endSection() ?>
