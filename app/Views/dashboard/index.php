<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Dashboard<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Dashboard Analitik & GIS<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Dashboard</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Premium Watermark Logo Background */
    .dashboard-watermark-bg {
        position: relative;
        min-height: calc(100vh - 120px);
    }
    .dashboard-watermark-bg::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('<?= base_url("assets/img/logo_sidak.png") ?>');
        background-repeat: no-repeat;
        background-position: center 30%;
        background-size: 550px;
        opacity: 0.045; /* Ultra faint opacity for a premium, non-distracting watermark */
        pointer-events: none; /* Let clicks pass through to map/buttons */
    }
    .dashboard-watermark-bg > * {
        position: relative;
        z-index: 1; /* Keep content above the background watermark */
    }

    /* Eye-Catching Stat Cards */
    div.eyecatching-card,
    .card.eyecatching-card {
        border-radius: 16px !important;
        overflow: hidden;
        border: none !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.06);
    }
    div.eyecatching-card:hover,
    .card.eyecatching-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.22);
    }
    .eyecatching-card .card-body {
        padding: 24px;
        position: relative;
        z-index: 2;
    }
    .eyecatching-card .icon-watermark {
        position: absolute;
        right: -10px;
        bottom: -15px;
        font-size: 120px;
        opacity: 0.15;
        z-index: 1;
        pointer-events: none;
        transition: all 0.3s ease;
    }
    .eyecatching-card:hover .icon-watermark {
        transform: scale(1.15) rotate(-6deg);
        opacity: 0.25;
    }
    div.card-theme-belum,
    .card.card-theme-belum {
        background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%) !important;
        background-color: #dc2626 !important;
        color: #ffffff !important;
    }
    div.card-theme-selesai,
    .card.card-theme-selesai {
        background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;
        background-color: #059669 !important;
        color: #ffffff !important;
    }
</style>
<div class="dashboard-watermark-bg">
<!-- Grid Stats Card Modern -->
<div class="row">
    <!-- Jumlah Temuan -->
    <div class="col-lg-3 col-6">
        <div class="stats-card bg-gradient-blue">
            <div class="inner">
                <h3><?= $stats['total'] ?></h3>
                <p>Jumlah Temuan</p>
            </div>
            <div class="icon">
                <i class="fas fa-search"></i>
            </div>
            <div style="font-size: 11px;">
                <a href="<?= site_url('temuan') ?>" class="text-white" style="text-decoration: underline;">Detail Data &rarr;</a>
            </div>
        </div>
    </div>
    <!-- EMERGENCY -->
    <div class="col-lg-3 col-6">
        <div class="stats-card bg-gradient-red animate__animated animate__pulse animate__infinite">
            <div class="inner">
                <h3><?= $stats['emergency'] ?></h3>
                <p>Temuan Emergency</p>
            </div>
            <div class="icon">
                <i class="fas fa-fire-extinguisher"></i>
            </div>
            <div style="font-size: 11px;">
                <a href="<?= site_url('temuan?prioritas=EMERGENCY') ?>" class="text-white" style="text-decoration: underline;">Detail Data &rarr;</a>
            </div>
        </div>
    </div>
    <!-- HIGH -->
    <div class="col-lg-3 col-6">
        <div class="stats-card bg-gradient-orange" style="background: linear-gradient(135deg, #f857a6 0%, #ff5858 100%) !important;">
            <div class="inner">
                <h3><?= $stats['high'] ?></h3>
                <p>Temuan High</p>
            </div>
            <div class="icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div style="font-size: 11px;">
                <a href="<?= site_url('temuan?prioritas=HIGH') ?>" class="text-white" style="text-decoration: underline;">Detail Data &rarr;</a>
            </div>
        </div>
    </div>
    <!-- MEDIUM -->
    <div class="col-lg-3 col-6">
        <div class="stats-card bg-gradient-info-modern">
            <div class="inner">
                <h3><?= $stats['medium'] ?></h3>
                <p>Temuan Medium</p>
            </div>
            <div class="icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <div style="font-size: 11px;">
                <a href="<?= site_url('temuan?prioritas=MEDIUM') ?>" class="text-white" style="text-decoration: underline;">Detail Data &rarr;</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- PDKB -->
    <div class="col-5ths col-6">
        <div class="stats-card bg-gradient-purple">
            <div class="inner">
                <h3><?= $stats['pdkb'] ?></h3>
                <p>PDKB</p>
            </div>
            <div class="icon">
                <i class="fas fa-bolt"></i>
            </div>
            <div style="font-size: 11px;">
                <a href="<?= site_url('temuan?pelaksana=PDKB') ?>" class="text-white" style="text-decoration: underline;">Detail Data &rarr;</a>
            </div>
        </div>
    </div>
    <!-- HAR GARDU -->
    <div class="col-5ths col-6">
        <div class="stats-card bg-gradient-teal-modern">
            <div class="inner">
                <h3><?= $stats['har_gardu'] ?></h3>
                <p>HAR GARDU</p>
            </div>
            <div class="icon">
                <i class="fas fa-hammer"></i>
            </div>
            <div style="font-size: 11px;">
                <a href="<?= site_url('temuan?pelaksana=HAR GARDU') ?>" class="text-white" style="text-decoration: underline;">Detail Data &rarr;</a>
            </div>
        </div>
    </div>
    <!-- HAR KONSTRUKSI -->
    <div class="col-5ths col-6">
        <div class="stats-card bg-gradient-info-modern" style="background: linear-gradient(135deg, #da22ff 0%, #9733ee 100%) !important;">
            <div class="inner">
                <h3><?= $stats['har_konstruksi'] ?></h3>
                <p>HAR KONSTRUKSI</p>
            </div>
            <div class="icon">
                <i class="fas fa-screwdriver-wrench"></i>
            </div>
            <div style="font-size: 11px;">
                <a href="<?= site_url('temuan?pelaksana=HAR KONSTRUKSI') ?>" class="text-white" style="text-decoration: underline;">Detail Data &rarr;</a>
            </div>
        </div>
    </div>
    <!-- HAR ROW -->
    <div class="col-5ths col-6">
        <div class="stats-card bg-gradient-orange">
            <div class="inner">
                <h3><?= $stats['har_row'] ?></h3>
                <p>HAR ROW</p>
            </div>
            <div class="icon">
                <i class="fas fa-tree"></i>
            </div>
            <div style="font-size: 11px;">
                <a href="<?= site_url('temuan?pelaksana=HAR ROW') ?>" class="text-white" style="text-decoration: underline;">Detail Data &rarr;</a>
            </div>
        </div>
    </div>
    <!-- HAR CRANE -->
    <div class="col-5ths col-6">
        <div class="stats-card bg-gradient-blue" style="background: linear-gradient(135deg, #7b4397 0%, #dc2430 100%) !important;">
            <div class="inner">
                <h3><?= $stats['har_crane'] ?></h3>
                <p>HAR CRANE</p>
            </div>
            <div class="icon">
                <i class="fas fa-truck-monster"></i>
            </div>
            <div style="font-size: 11px;">
                <a href="<?= site_url('temuan?pelaksana=HAR CRANE') ?>" class="text-white" style="text-decoration: underline;">Detail Data &rarr;</a>
            </div>
        </div>
    </div>
</div>

<!-- ROW 3: METRIK SUMMARY COMPACT (BELUM & SUDAH SELESAI) -->
<div class="row mb-3">
    <!-- BELUM SELESAI CARD -->
    <div class="col-md-6 col-12 mb-2 mb-md-0">
        <div class="card eyecatching-card card-theme-belum" style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%) !important; background-color: #dc2626 !important; color: #ffffff !important; padding: 0;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="p-2 rounded-circle me-3" style="background: rgba(255,255,255,0.22); backdrop-filter: blur(8px); width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-clock-rotate-left text-white" style="font-size: 1.1rem;"></i>
                        </div>
                        <div>
                            <span class="text-white-50 text-uppercase font-weight-bold" style="font-size: 10px; letter-spacing: 0.8px;">Status Pekerjaan</span>
                            <h5 class="m-0 font-weight-bold text-white" style="font-size: 16px;">Belum Selesai</h5>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-white text-danger font-weight-bold px-2 py-1" style="border-radius: 12px; font-size: 11px; color: #dc2626 !important;">
                            <i class="fas fa-exclamation-circle me-1"></i> <?= $stats['total'] > 0 ? round(($stats['belum'] / $stats['total']) * 100, 1) : 0 ?>% Total
                        </span>
                        <h2 class="font-weight-extrabold text-white m-0 mt-1" style="font-size: 2rem; line-height: 1; color: #ffffff !important;">
                            <?= number_format($stats['belum']) ?>
                        </h2>
                    </div>
                </div>

                <div class="progress mt-2 mb-2" style="height: 6px; background: rgba(255,255,255,0.25); border-radius: 6px;">
                    <div class="progress-bar bg-white" style="width: <?= $stats['total'] > 0 ? ($stats['belum'] / $stats['total']) * 100 : 0 ?>%; border-radius: 6px; background-color: #ffffff !important;"></div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-white-50" style="font-size: 11px;"><i class="fas fa-info-circle me-1"></i> Menunggu tindak lanjut</span>
                    <a href="<?= site_url('temuan?status=BELUM') ?>" class="btn btn-xs btn-light text-danger font-weight-bold px-2 py-0" style="border-radius: 8px; font-size: 11px; color: #dc2626 !important; background-color: #ffffff !important;">
                        Lihat Data &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- SUDAH SELESAI CARD -->
    <div class="col-md-6 col-12">
        <div class="card eyecatching-card card-theme-selesai" style="background: linear-gradient(135deg, #059669 0%, #047857 100%) !important; background-color: #059669 !important; color: #ffffff !important; padding: 0;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="p-2 rounded-circle me-3" style="background: rgba(255,255,255,0.22); backdrop-filter: blur(8px); width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-circle-check text-white" style="font-size: 1.1rem;"></i>
                        </div>
                        <div>
                            <span class="text-white-50 text-uppercase font-weight-bold" style="font-size: 10px; letter-spacing: 0.8px;">Status Pekerjaan</span>
                            <h5 class="m-0 font-weight-bold text-white" style="font-size: 16px;">Sudah Selesai</h5>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-white text-success font-weight-bold px-2 py-1" style="border-radius: 12px; font-size: 11px; color: #059669 !important;">
                            <i class="fas fa-check-circle me-1"></i> <?= $stats['total'] > 0 ? round(($stats['selesai'] / $stats['total']) * 100, 1) : 0 ?>% Success Rate
                        </span>
                        <h2 class="font-weight-extrabold text-white m-0 mt-1" style="font-size: 2rem; line-height: 1; color: #ffffff !important;">
                            <?= number_format($stats['selesai']) ?>
                        </h2>
                    </div>
                </div>

                <div class="progress mt-2 mb-2" style="height: 6px; background: rgba(255,255,255,0.25); border-radius: 6px;">
                    <div class="progress-bar bg-white" style="width: <?= $stats['total'] > 0 ? ($stats['selesai'] / $stats['total']) * 100 : 0 ?>%; border-radius: 6px; background-color: #ffffff !important;"></div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-white-50" style="font-size: 11px;"><i class="fas fa-shield-check me-1"></i> Tuntas sesuai SLA</span>
                    <a href="<?= site_url('temuan?status=SELESAI') ?>" class="btn btn-xs btn-light text-success font-weight-bold px-2 py-0" style="border-radius: 8px; font-size: 11px; color: #059669 !important; background-color: #ffffff !important;">
                        Lihat Data &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TOP 10 LEADERBOARD SECTION -->
<div class="row my-4">
    <div class="col-12 mb-3 d-flex align-items-center justify-content-between flex-wrap">
        <div>
            <h3 class="font-weight-extrabold text-dark m-0 d-flex align-items-center" style="font-size: 1.35rem;">
                <i class="fas fa-trophy text-warning me-2 animate__animated animate__bounceIn"></i> 
                Rekap Kinerja & Top 10 Petugas
            </h3>
            <p class="text-muted small m-0 mt-1">Peringkat petugas dengan kontribusi input & penyelesaian temuan tertinggi</p>
        </div>
        <!-- Month / Year Filter -->
        <form method="GET" action="<?= site_url('dashboard') ?>" class="d-flex align-items-center gap-2 mt-2 mt-md-0">
            <select name="month" class="form-select form-select-sm" style="border-radius: 8px; width: 140px;" onchange="this.form.submit()">
                <?php
                $bulanNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
                $mFilter = $monthFilter ?? date('n');
                $yFilter = $yearFilter ?? date('Y');
                foreach ($bulanNames as $mNum => $mName):
                ?>
                    <option value="<?= $mNum ?>" <?= ($mFilter == $mNum) ? 'selected' : '' ?>><?= $mName ?></option>
                <?php endforeach; ?>
            </select>
            <select name="year" class="form-select form-select-sm" style="border-radius: 8px; width: 100px;" onchange="this.form.submit()">
                <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                    <option value="<?= $y ?>" <?= ($yFilter == $y) ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <!-- Top 10 Input Temuan -->
    <div class="col-lg-6 col-12 mb-3">
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden h-100">
            <div class="card-header py-3" style="background: linear-gradient(135deg, #004D4F 0%, #007275 100%); color: #ffffff;">
                <div class="d-flex align-items-center justify-content-between">
                    <h4 class="card-title text-white font-weight-bold m-0" style="font-size: 15px;">
                        <i class="fas fa-file-signature text-warning me-2"></i> Top 10 Petugas Input Temuan
                    </h4>
                    <span class="badge bg-warning text-dark font-weight-bold" style="border-radius: 12px; font-size: 11px;">
                        <?= $bulanNames[$mFilter] ?> <?= $yFilter ?>
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topInputOfficers)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                        Belum ada data input temuan pada periode ini.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 50px;" class="text-center">#</th>
                                    <th>Nama Pegawai / NIP</th>
                                    <th class="text-center" style="width: 110px;">Total Input</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topInputOfficers as $idx => $officer): ?>
                                    <tr>
                                        <td class="text-center font-weight-bold">
                                            <?php if ($idx == 0): ?>
                                                🥇
                                            <?php elseif ($idx == 1): ?>
                                                🥈
                                            <?php elseif ($idx == 2): ?>
                                                🥉
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark border rounded-circle" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px;"><?= $idx + 1 ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="font-weight-bold text-dark"><?= esc($officer['created_by_name']) ?></div>
                                            <small class="text-muted" style="font-size: 11px;">NIP: <?= esc($officer['created_by_nip'] ?: '-') ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary px-3 py-1 font-weight-bold" style="border-radius: 12px; font-size: 12px;">
                                                <?= number_format($officer['total_input']) ?> Temuan
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top 10 Update / Penyelesaian Temuan -->
    <div class="col-lg-6 col-12 mb-3">
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden h-100">
            <div class="card-header py-3" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #ffffff;">
                <div class="d-flex align-items-center justify-content-between">
                    <h4 class="card-title text-white font-weight-bold m-0" style="font-size: 15px;">
                        <i class="fas fa-check-circle text-white me-2"></i> Top 10 Petugas Update & Eksekusi
                    </h4>
                    <span class="badge bg-white text-success font-weight-bold" style="border-radius: 12px; font-size: 11px; color: #059669 !important;">
                        <?= $bulanNames[$mFilter] ?> <?= $yFilter ?>
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topUpdateOfficers)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                        Belum ada data update penyelesaian pada periode ini.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 50px;" class="text-center">#</th>
                                    <th>Nama Pegawai / NIP</th>
                                    <th class="text-center" style="width: 110px;">Total Tuntas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topUpdateOfficers as $idx => $officer): ?>
                                    <tr>
                                        <td class="text-center font-weight-bold">
                                            <?php if ($idx == 0): ?>
                                                🥇
                                            <?php elseif ($idx == 1): ?>
                                                🥈
                                            <?php elseif ($idx == 2): ?>
                                                🥉
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark border rounded-circle" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px;"><?= $idx + 1 ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="font-weight-bold text-dark"><?= esc($officer['updated_by_name']) ?></div>
                                            <small class="text-muted" style="font-size: 11px;">NIP: <?= esc($officer['updated_by_nip'] ?: '-') ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success px-3 py-1 font-weight-bold" style="border-radius: 12px; font-size: 12px; background-color: #059669 !important;">
                                                <?= number_format($officer['total_update']) ?> Tuntas
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Grafik Analitik -->
<div class="row">
    <!-- Temuan per Bulan -->
    <div class="col-md-6">
        <div class="card card-modern">
            <div class="card-header border-bottom bg-transparent">
                <h3 class="card-title text-dark"><i class="fas fa-chart-line text-info mr-1"></i> Tren Temuan per Bulan</h3>
            </div>
            <div class="card-body">
                <canvas id="chartBulanan" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
            </div>
        </div>
    </div>
    <!-- Temuan per ULP -->
    <div class="col-md-6">
        <div class="card card-modern">
            <div class="card-header border-bottom bg-transparent">
                <h3 class="card-title text-dark"><i class="fas fa-chart-pie text-success mr-1"></i> Temuan per ULP</h3>
            </div>
            <div class="card-body">
                <canvas id="chartUlp" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Temuan per Penyulang -->
    <div class="col-md-6">
        <div class="card card-modern">
            <div class="card-header border-bottom bg-transparent">
                <h3 class="card-title text-dark"><i class="fas fa-chart-bar text-warning mr-1"></i> Top 10 Penyulang (Temuan Belum Dieksekusi)</h3>
            </div>
            <div class="card-body">
                <canvas id="chartPenyulang" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
            </div>
        </div>
    </div>
    <!-- Temuan per Pelaksana -->
    <div class="col-md-6">
        <div class="card card-modern">
            <div class="card-header border-bottom bg-transparent">
                <h3 class="card-title text-dark"><i class="fas fa-users-cog text-teal mr-1"></i> Temuan per Pelaksana</h3>
            </div>
            <div class="card-body">
                <canvas id="chartPelaksana" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Temuan berdasarkan Prioritas -->
    <div class="col-md-6">
        <div class="card card-modern">
            <div class="card-header border-bottom bg-transparent">
                <h3 class="card-title text-dark"><i class="fas fa-star text-danger mr-1"></i> Temuan Berdasarkan Prioritas</h3>
            </div>
            <div class="card-body">
                <canvas id="chartPrioritas" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
            </div>
        </div>
    </div>
    <!-- Temuan berdasarkan Potensi Gangguan -->
    <div class="col-md-6">
        <div class="card card-modern">
            <div class="card-header border-bottom bg-transparent">
                <h3 class="card-title text-dark"><i class="fas fa-triangle-exclamation text-orange mr-1"></i> Temuan Berdasarkan Potensi Gangguan</h3>
            </div>
            <div class="card-body">
                <canvas id="chartPotensiGangguan" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
            </div>
        </div>
    </div>
</div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function() {

        // --- 2. CHART.JS CONFIGURATIONS - Light Mode Settings ---
        Chart.defaults.color = '#495057';
        Chart.defaults.borderColor = '#e9ecef';

        // Grafik 1: Tren Bulanan
        const monthlyData = <?= json_encode($monthlyData) ?>;
        new Chart(document.getElementById('chartBulanan'), {
            type: 'line',
            data: {
                labels: monthlyData.map(d => d.bulan),
                datasets: [{
                    label: 'Jumlah Temuan',
                    data: monthlyData.map(d => d.total),
                    backgroundColor: 'rgba(0, 123, 255, 0.15)',
                    borderColor: '#007bff',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Grafik 2: Temuan per ULP
        const ulpData = <?= json_encode($ulpData) ?>;
        new Chart(document.getElementById('chartUlp'), {
            type: 'pie',
            data: {
                labels: ulpData.map(d => d.nama_ulp),
                datasets: [{
                    data: ulpData.map(d => d.total),
                    backgroundColor: ['#007bff', '#17a2b8', '#ffc107', '#dc3545', '#6f42c1', '#e83e8c']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Grafik 3: Temuan per Penyulang
        const penyulangData = <?= json_encode($penyulangData) ?>;
        new Chart(document.getElementById('chartPenyulang'), {
            type: 'bar',
            data: {
                labels: penyulangData.map(d => d.nama_penyulang),
                datasets: [{
                    label: 'Temuan',
                    data: penyulangData.map(d => d.total),
                    backgroundColor: '#ffc107'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y'
            }
        });

        // Grafik 4: Temuan per Pelaksana
        const pelaksanaData = <?= json_encode($pelaksanaData) ?>;
        new Chart(document.getElementById('chartPelaksana'), {
            type: 'bar',
            data: {
                labels: pelaksanaData.map(d => d.pelaksana),
                datasets: [{
                    label: 'Temuan',
                    data: pelaksanaData.map(d => d.total),
                    backgroundColor: '#6f42c1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Grafik 5: Prioritas
        const prioritasData = <?= json_encode($prioritasData) ?>;
        new Chart(document.getElementById('chartPrioritas'), {
            type: 'doughnut',
            data: {
                labels: prioritasData.map(d => d.prioritas),
                datasets: [{
                    data: prioritasData.map(d => d.total),
                    backgroundColor: ['#dc3545', '#fd7e14', '#007bff'] // Red, Orange, Blue
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Grafik 6: Potensi Gangguan
        const potensiGangguanData = <?= json_encode($potensiGangguanData) ?>;
        new Chart(document.getElementById('chartPotensiGangguan'), {
            type: 'polarArea',
            data: {
                labels: potensiGangguanData.map(d => d.potensi_gangguan),
                datasets: [{
                    data: potensiGangguanData.map(d => d.total),
                    backgroundColor: ['rgba(220, 53, 69, 0.7)', 'rgba(0, 123, 255, 0.7)', 'rgba(40, 167, 69, 0.7)']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    });
</script>
<?= $this->endSection() ?>
