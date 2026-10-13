<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Beyond KWH — Executive Monitoring & Performance Dashboard<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Beyond KWH — Executive Monitoring<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Beyond KWH Enterprise Executive Theme (Styled after OpsPilot Pro & SIDAK Bento) */
    .beyond-container {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    /* Executive Glass / Dark Accent Banner */
    .beyond-hero-card {
        background: linear-gradient(135deg, #0b1329 0%, #112240 60%, #1e3a8a 100%);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 20px;
        color: #ffffff;
        box-shadow: 0 12px 32px rgba(11, 19, 41, 0.18);
        position: relative;
        overflow: hidden;
    }
    .beyond-hero-card::after {
        content: "";
        position: absolute;
        top: -40px;
        right: -40px;
        width: 180px;
        height: 180px;
        background: radial-gradient(circle, rgba(0, 181, 184, 0.25) 0%, transparent 70%);
        pointer-events: none;
    }

    /* Enterprise Surface Cards */
    .beyond-card {
        background: #ffffff;
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    /* KPI Cards */
    .beyond-kpi-card {
        background: #ffffff;
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        padding: 20px;
        cursor: pointer;
        text-decoration: none !important;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 125px;
        transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        position: relative;
        overflow: hidden;
    }
    .beyond-kpi-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        border-color: #cbd5e1;
    }
    .beyond-kpi-card.active-filter {
        border-color: #00B5B8 !important;
        box-shadow: 0 0 0 3px rgba(0, 181, 184, 0.25) !important;
    }
    .beyond-kpi-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #64748b;
        margin-bottom: 6px;
    }
    .beyond-kpi-val {
        font-size: 32px;
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -1px;
    }
    .beyond-kpi-sub {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 6px;
    }
    .beyond-kpi-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    /* Filter Toolbar Bar */
    .beyond-filter-bar {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        padding: 16px 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    }

    /* Urgency Badges */
    .badge-urgensi-kritis {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    .badge-urgensi-tinggi {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }
    .badge-urgensi-sedang {
        background: #e0f2fe;
        color: #075985;
        border: 1px solid #bae6fd;
    }
    .badge-urgensi-rendah {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    /* Status Badges */
    .badge-status-terkirim { background: #e0f2fe; color: #0284c7; }
    .badge-status-dibaca { background: #fef9c3; color: #a16207; }
    .badge-status-diproses { background: #ffedd5; color: #c2410c; }
    .badge-status-selesai { background: #dcfce7; color: #15803d; }
    .badge-status-ditolak { background: #fee2e2; color: #b91c1c; }

    /* Progress Ring / Bar */
    .beyond-progress-bar {
        height: 8px;
        border-radius: 9999px;
        background: #e2e8f0;
        overflow: hidden;
    }
    .beyond-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #00B5B8 0%, #10b981 100%);
        border-radius: 9999px;
        transition: width 0.4s ease;
    }
</style>

<div class="container-fluid beyond-container px-3 px-md-4 py-3">

    <!-- A. HEADER EKSEKUTIF -->
    <div class="beyond-hero-card p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="beyond-kpi-icon bg-white bg-opacity-10 text-warning" style="width: 52px; height: 52px; font-size: 26px; border: 1px solid rgba(255,255,255,0.2);">
                    <i class="fas fa-bolt"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                        <span class="badge bg-info text-dark fw-bold px-2 py-1" style="font-size: 10px; letter-spacing: 0.8px;">ENTERPRISE MONITORING</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0" style="font-size: 10px;">
                            <span class="spinner-grow spinner-grow-sm me-1" style="width: 6px; height: 6px;" role="status"></span> Live Sync
                        </span>
                        <?php if (!empty($isAdmin)): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0" style="font-size: 10px;">Akses Penuh Administrator</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-0" style="font-size: 10px;">Petugas Beyond KWH</span>
                        <?php endif; ?>
                    </div>
                    <h2 class="fw-bold text-white mb-1" style="letter-spacing: -0.5px;">BEYOND KWH — EXECUTIVE MONITORING</h2>
                    <p class="text-white-50 small mb-0">
                        <?= !empty($isAdmin) 
                            ? 'Pantau portofolio rekomendasi, prioritas temuan kritis, dan progres tindak lanjut regu Beyond KWH se-Sidoarjo.' 
                            : 'Dashboard khusus penugasan Beyond KWH untuk akun ' . esc(session()->get('user_name') ?: 'Petugas') . '.' ?>
                    </p>
                </div>
            </div>

            <!-- Header Quick Actions & Timestamp -->
            <div class="d-flex flex-column align-items-md-end gap-2">
                <div class="text-white-50 small font-monospace">
                    <i class="far fa-clock me-1 text-warning"></i> Pembaruan: <span id="header-last-updated" class="text-white fw-semibold"><?= date('H:i:s') ?> WIB</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3" onclick="refreshBeyondDashboard()" title="Muat Ulang Data">
                        <i class="fas fa-sync-alt me-1" id="btn-refresh-icon"></i> Refresh
                    </button>
                    <?php if (!empty($isAdmin)): ?>
                        <a href="<?= site_url('kubikel-inspeksi') ?>" class="btn btn-sm btn-info text-white rounded-pill px-3">
                            <i class="fas fa-clipboard-check me-1"></i> Inspeksi Kubikel
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Completion Rate Progress Line -->
        <div class="row align-items-center mt-3 pt-3 border-top border-white border-opacity-10 g-2">
            <div class="col-md-3 col-12">
                <div class="d-flex justify-content-between small text-white-50 mb-1">
                    <span>Performance Resolution Rate</span>
                    <strong class="text-warning" id="hero-rate-label"><?= $kpi['completion_rate'] ?>%</strong>
                </div>
                <div class="beyond-progress-bar">
                    <div class="beyond-progress-fill" id="hero-rate-fill" style="width: <?= min(100, (float)$kpi['completion_rate']) ?>%;"></div>
                </div>
            </div>
            <div class="col-md-9 col-12 text-md-end text-white-50 small">
                Total <strong><?= number_format($kpi['total']) ?></strong> penugasan &bull; 
                <span class="text-success fw-bold"><?= number_format($kpi['selesai']) ?> selesai</span> &bull; 
                <span class="text-warning fw-bold"><?= number_format($kpi['proses']) ?> dalam proses</span> &bull; 
                <span class="text-danger fw-bold"><?= number_format($kpi['kritis_terbuka']) ?> prioritas kritis/tinggi terbuka</span>
            </div>
        </div>
    </div>

    <?php if (!$tableReady): ?>
        <div class="alert alert-warning border-warning shadow-sm rounded-16 mb-4 d-flex align-items-center gap-3">
            <i class="fas fa-database fs-3 text-warning"></i>
            <div>
                <strong>Tabel Database Belum Diinisialisasi</strong>
                <p class="small mb-0 text-muted">Struktur tabel inspeksi kubikel dan rekomendasi Beyond KWH sedang menunggu migrasi database. Data di bawah saat ini berstatus kosong (0).</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- B. KPI CARDS (INTERACTIVE & DRILL-DOWN READY) -->
    <div class="row g-3 mb-4">
        <!-- 1. TOTAL REKOMENDASI -->
        <div class="col-6 col-lg">
            <div class="beyond-kpi-card <?= empty($filters['status']) && empty($filters['urgensi']) ? 'active-filter' : '' ?>" onclick="applyKpiDrilldown('', '')">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="beyond-kpi-label">Total Pekerjaan</span>
                    <div class="beyond-kpi-icon bg-primary-subtle text-primary">
                        <i class="fas fa-layer-group"></i>
                    </div>
                </div>
                <div class="beyond-kpi-val text-dark" id="kpi-total-val"><?= number_format($kpi['total']) ?></div>
                <div class="beyond-kpi-sub">Seluruh rekomendasi</div>
            </div>
        </div>

        <!-- 2. BELUM DITINDAKLANJUTI -->
        <div class="col-6 col-lg">
            <div class="beyond-kpi-card <?= ($filters['status'] ?? '') === 'BELUM' ? 'active-filter' : '' ?>" onclick="applyKpiDrilldown('BELUM', '')">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="beyond-kpi-label">Belum Ditindaklanjuti</span>
                    <div class="beyond-kpi-icon bg-info-subtle text-info">
                        <i class="fas fa-inbox"></i>
                    </div>
                </div>
                <div class="beyond-kpi-val text-info" id="kpi-belum-val"><?= number_format($kpi['belum']) ?></div>
                <div class="beyond-kpi-sub">Terkirim / Belum dibaca</div>
            </div>
        </div>

        <!-- 3. DALAM PROSES -->
        <div class="col-6 col-lg">
            <div class="beyond-kpi-card <?= ($filters['status'] ?? '') === 'DIPROSES' ? 'active-filter' : '' ?>" onclick="applyKpiDrilldown('DIPROSES', '')">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="beyond-kpi-label">Dalam Proses</span>
                    <div class="beyond-kpi-icon bg-warning-subtle text-warning">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                </div>
                <div class="beyond-kpi-val text-warning" id="kpi-proses-val"><?= number_format($kpi['proses']) ?></div>
                <div class="beyond-kpi-sub">Sedang dieksekusi</div>
            </div>
        </div>

        <!-- 4. SELESAI -->
        <div class="col-6 col-lg">
            <div class="beyond-kpi-card <?= ($filters['status'] ?? '') === 'SELESAI' ? 'active-filter' : '' ?>" onclick="applyKpiDrilldown('SELESAI', '')">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="beyond-kpi-label">Selesai Ditangani</span>
                    <div class="beyond-kpi-icon bg-success-subtle text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="beyond-kpi-val text-success" id="kpi-selesai-val"><?= number_format($kpi['selesai']) ?></div>
                <div class="beyond-kpi-sub">Tuntas terverifikasi</div>
            </div>
        </div>

        <!-- 5. PRIORITAS KRITIS / TINGGI -->
        <div class="col-12 col-lg">
            <div class="beyond-kpi-card <?= ($filters['urgensi'] ?? '') === 'KRITIS' ? 'active-filter' : '' ?>" onclick="applyKpiDrilldown('', 'KRITIS')">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="beyond-kpi-label text-danger">Prioritas Kritis</span>
                    <div class="beyond-kpi-icon bg-danger-subtle text-danger">
                        <i class="fas fa-triangle-exclamation"></i>
                    </div>
                </div>
                <div class="beyond-kpi-val text-danger" id="kpi-kritis-val"><?= number_format($kpi['kritis_terbuka']) ?></div>
                <div class="beyond-kpi-sub text-danger">Mendesak diselesaikan</div>
            </div>
        </div>
    </div>

    <!-- C. GRAFIK EKSEKUTIF -->
    <div class="row g-4 mb-4">
        <!-- Trend Rekomendasi vs Selesai -->
        <div class="col-lg-7 col-12">
            <div class="beyond-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-chart-line text-primary me-2"></i>Tren Pekerjaan Masuk vs Penyelesaian</h5>
                        <small class="text-muted">Performa bulanan tindak lanjut Beyond KWH (6 bulan terakhir)</small>
                    </div>
                    <span class="badge bg-light text-secondary border">Bulan</span>
                </div>
                <div style="height: 260px; position: relative;">
                    <canvas id="beyondTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Distribusi Status & Prioritas -->
        <div class="col-lg-5 col-12">
            <div class="beyond-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-chart-pie text-warning me-2"></i>Komposisi Status &amp; Urgensi</h5>
                        <small class="text-muted">Proporsi kondisi pekerjaan saat ini</small>
                    </div>
                    <ul class="nav nav-pills nav-pills-sm" id="distChartTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active py-1 px-2 small" id="tab-status-btn" data-bs-toggle="pill" data-bs-target="#tab-status-pane" type="button">Status</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link py-1 px-2 small" id="tab-priority-btn" data-bs-toggle="pill" data-bs-target="#tab-priority-pane" type="button">Urgensi</button>
                        </li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-status-pane">
                        <div style="height: 230px; position: relative;">
                            <canvas id="beyondStatusChart"></canvas>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-priority-pane">
                        <div style="height: 230px; position: relative;">
                            <canvas id="beyondPriorityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- D. FILTER INTERAKTIF TOOLBAR -->
    <div class="beyond-filter-bar mb-4">
        <form id="beyondFilterForm" class="row g-2 align-items-end" onsubmit="handleFilterSubmit(event)">
            <!-- Status Filter -->
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small fw-bold text-muted mb-1">Status</label>
                <select name="status" id="flt-status" class="form-select form-select-sm" onchange="submitFilterAjax()">
                    <option value="">Semua Status</option>
                    <option value="BELUM" <?= ($filters['status'] ?? '') === 'BELUM' ? 'selected' : '' ?>>Belum (Terkirim/Dibaca)</option>
                    <option value="TERKIRIM" <?= ($filters['status'] ?? '') === 'TERKIRIM' ? 'selected' : '' ?>>TERKIRIM</option>
                    <option value="DIBACA" <?= ($filters['status'] ?? '') === 'DIBACA' ? 'selected' : '' ?>>DIBACA</option>
                    <option value="DIPROSES" <?= ($filters['status'] ?? '') === 'DIPROSES' ? 'selected' : '' ?>>DIPROSES</option>
                    <option value="SELESAI" <?= ($filters['status'] ?? '') === 'SELESAI' ? 'selected' : '' ?>>SELESAI</option>
                    <option value="DITOLAK" <?= ($filters['status'] ?? '') === 'DITOLAK' ? 'selected' : '' ?>>DITOLAK</option>
                </select>
            </div>

            <!-- Urgensi Filter -->
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small fw-bold text-muted mb-1">Urgensi</label>
                <select name="urgensi" id="flt-urgensi" class="form-select form-select-sm" onchange="submitFilterAjax()">
                    <option value="">Semua Urgensi</option>
                    <option value="KRITIS" <?= ($filters['urgensi'] ?? '') === 'KRITIS' ? 'selected' : '' ?>>KRITIS</option>
                    <option value="TINGGI" <?= ($filters['urgensi'] ?? '') === 'TINGGI' ? 'selected' : '' ?>>TINGGI</option>
                    <option value="SEDANG" <?= ($filters['urgensi'] ?? '') === 'SEDANG' ? 'selected' : '' ?>>SEDANG</option>
                    <option value="RENDAH" <?= ($filters['urgensi'] ?? '') === 'RENDAH' ? 'selected' : '' ?>>RENDAH</option>
                </select>
            </div>

            <!-- ULP Filter -->
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small fw-bold text-muted mb-1">Unit ULP</label>
                <select name="ulp_id" id="flt-ulp" class="form-select form-select-sm" onchange="handleUlpChange(this.value)">
                    <option value="">Semua ULP</option>
                    <?php foreach ($ulps as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ((string)($filters['ulp_id'] ?? '') === (string)$u['id']) ? 'selected' : '' ?>>
                            <?= esc($u['nama_ulp']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Penyulang Filter -->
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small fw-bold text-muted mb-1">Penyulang</label>
                <select name="penyulang_id" id="flt-penyulang" class="form-select form-select-sm" onchange="submitFilterAjax()">
                    <option value="">Semua Penyulang</option>
                    <?php foreach ($penyulangs as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ((string)($filters['penyulang_id'] ?? '') === (string)$p['id']) ? 'selected' : '' ?>>
                            <?= esc($p['nama_penyulang']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Petugas Filter (Admin Only) -->
            <?php if (!empty($isAdmin)): ?>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small fw-bold text-muted mb-1">Petugas Beyond</label>
                <select name="petugas_id" id="flt-petugas" class="form-select form-select-sm" onchange="submitFilterAjax()">
                    <option value="">Semua Petugas</option>
                    <?php foreach ($officers as $off): ?>
                        <option value="<?= $off['id'] ?>" <?= ((string)($filters['petugas_id'] ?? '') === (string)$off['id']) ? 'selected' : '' ?>>
                            <?= esc($off['nama_pegawai'] ?: $off['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="col-6 col-md-3 col-lg-2 d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary w-100 rounded-pill" onclick="resetFilters()">
                    <i class="fas fa-undo me-1"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- E. MONITORING PEKERJAAN & DAFTAR REKOMENDASI -->
    <div class="beyond-card">
        <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-list-check text-primary me-2"></i>Daftar Pekerjaan &amp; Rekomendasi Terjadwal</h5>
                <small class="text-muted" id="table-summary-count">Menampilkan <?= count($rekomendasi) ?> item rekomendasi</small>
            </div>
            <div id="filter-active-pills" class="d-flex gap-1 flex-wrap"></div>
        </div>

        <div class="p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="beyondTable">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th style="min-width: 140px;">No. Rekomendasi / Waktu</th>
                            <th>Inspeksi &amp; Aset</th>
                            <th>Temuan &amp; Komponen</th>
                            <th style="min-width: 110px;">Prioritas</th>
                            <th style="min-width: 110px;">Status</th>
                            <?php if (!empty($isAdmin)): ?>
                                <th>Petugas Ditugaskan</th>
                            <?php endif; ?>
                            <th class="text-end" style="min-width: 90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="beyondTableBody">
                        <?php if (!empty($rekomendasi)): ?>
                            <?php foreach ($rekomendasi as $item): ?>
                                <?php
                                    $urgClass = 'badge-urgensi-' . strtolower($item['temuan_urgensi'] ?? 'sedang');
                                    $stClass = 'badge-status-' . strtolower($item['status'] ?? 'terkirim');
                                    $waktu = !empty($item['waktu_kirim']) ? date('d M Y H:i', strtotime($item['waktu_kirim'])) : '-';
                                    $tglInspeksi = !empty($item['tgl_inspeksi']) ? date('d M Y', strtotime($item['tgl_inspeksi'])) : '-';
                                    
                                    // Hitung umur pekerjaan (days)
                                    $umurHari = !empty($item['waktu_kirim']) ? max(0, (int)floor((time() - strtotime($item['waktu_kirim'])) / 86400)) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary font-monospace">#REC-<?= (int)$item['id'] ?></strong>
                                        <div class="text-muted small mt-1"><i class="far fa-clock me-1"></i><?= $waktu ?></div>
                                        <?php if ($item['status'] !== 'SELESAI' && $umurHari > 0): ?>
                                            <span class="badge bg-light text-muted border" style="font-size: 9px;">Umur: <?= $umurHari ?> hari</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark"><?= esc($item['nomor_inspeksi'] ?? '-') ?></span>
                                        <div class="text-muted small">
                                            <?= esc($item['nama_ulp'] ?? 'ULP -') ?> &bull; <?= esc($item['nama_penyulang'] ?? 'Feeder -') ?>
                                        </div>
                                        <?php if (!empty($item['id_pel'])): ?>
                                            <span class="badge bg-light text-dark border" style="font-size: 9px;">ID Pel: <?= esc($item['id_pel']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= esc($item['temuan_komponen'] ?? 'Kubikel') ?> (Fasa <?= esc($item['temuan_fasa'] ?? '-') ?>)</div>
                                        <small class="text-muted text-truncate d-block" style="max-width: 280px;" title="<?= esc($item['deskripsi_temuan'] ?? '') ?>">
                                            <?= esc($item['deskripsi_temuan'] ?? '-') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $urgClass ?> px-2 py-1"><?= esc($item['temuan_urgensi'] ?? 'SEDANG') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $stClass ?> px-2 py-1"><?= esc($item['status'] ?? 'TERKIRIM') ?></span>
                                    </td>
                                    <?php if (!empty($isAdmin)): ?>
                                        <td>
                                            <?php if (!empty($item['penerima_nama'])): ?>
                                                <span class="badge bg-light text-dark border"><i class="fas fa-user me-1 text-primary"></i><?= esc($item['penerima_nama']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small fst-italic">Belum ditugaskan</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="text-end">
                                        <a href="<?= site_url('kubikel-inspeksi/beyond-kwh/detail/' . (int)$item['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                            Buka <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="empty-state-row">
                                <td colspan="<?= !empty($isAdmin) ? '7' : '6' ?>" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-inbox display-4 d-block mb-3 opacity-25"></i>
                                        <h6 class="fw-bold mb-1">Tidak Ada Rekomendasi Ditemukan</h6>
                                        <p class="small text-muted mb-2">
                                            <?= !empty($isAdmin) 
                                                ? 'Belum ada rekomendasi Beyond KWH yang cocok dengan filter aktif, atau belum ada rekomendasi yang dibuat dari temuan inspeksi kubikel.' 
                                                : 'Belum ada rekomendasi yang ditugaskan kepada akun Anda saat ini.' ?>
                                        </p>
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="resetFilters()">
                                            <i class="fas fa-undo me-1"></i> Bersihkan Filter
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Data injection for JavaScript charts and interactions -->
<script>
    window.BEYOND_DATA = {
        monthlyTrend: <?= json_encode($monthlyTrend ?? []) ?>,
        statusDist:   <?= json_encode($statusDist ?? []) ?>,
        priorityDist: <?= json_encode($priorityDist ?? []) ?>,
        isAdmin:      <?= !empty($isAdmin) ? 'true' : 'false' ?>,
        detailUrl:    '<?= site_url('kubikel-inspeksi/beyond-kwh/detail/') ?>'
    };
</script>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
let trendChartInstance = null;
let statusChartInstance = null;
let priorityChartInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    initCharts();
});

function initCharts() {
    const data = window.BEYOND_DATA || {};

    // 1. Line/Bar Chart: Monthly Trend
    const ctxTrend = document.getElementById('beyondTrendChart');
    if (ctxTrend && typeof Chart !== 'undefined') {
        const tData = data.monthlyTrend || { labels: [], masuk: [], selesai: [] };
        trendChartInstance = new Chart(ctxTrend, {
            type: 'bar',
            data: {
                labels: tData.labels.length ? tData.labels : ['6 bln lalu', '5 bln lalu', '4 bln lalu', '3 bln lalu', 'Bln lalu', 'Bln ini'],
                datasets: [
                    {
                        label: 'Rekomendasi Masuk',
                        data: tData.masuk.length ? tData.masuk : [0,0,0,0,0,0],
                        backgroundColor: 'rgba(0, 181, 184, 0.65)',
                        borderColor: '#00B5B8',
                        borderWidth: 1,
                        borderRadius: 6,
                    },
                    {
                        label: 'Selesai Ditangani',
                        data: tData.selesai.length ? tData.selesai : [0,0,0,0,0,0],
                        backgroundColor: 'rgba(34, 197, 94, 0.65)',
                        borderColor: '#22c55e',
                        borderWidth: 1,
                        borderRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, font: { family: 'Outfit', size: 11 } } },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        padding: 10,
                        titleFont: { family: 'Outfit', size: 12 },
                        bodyFont: { family: 'Outfit', size: 12 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { family: 'Outfit', size: 10 } },
                        grid: { color: 'rgba(226, 232, 240, 0.6)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Outfit', size: 10 } }
                    }
                }
            }
        });
    }

    // 2. Doughnut Chart: Status
    const ctxStatus = document.getElementById('beyondStatusChart');
    if (ctxStatus && typeof Chart !== 'undefined') {
        const sDist = data.statusDist || {};
        const labels = Object.keys(sDist);
        const values = Object.values(sDist);
        const colors = ['#0284c7', '#a16207', '#c2410c', '#15803d', '#b91c1c'];

        statusChartInstance = new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: labels.length ? labels : ['TERKIRIM', 'DIBACA', 'DIPROSES', 'SELESAI', 'DITOLAK'],
                datasets: [{
                    data: values.length ? values : [0, 0, 0, 0, 0],
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 10, font: { family: 'Outfit', size: 11 } } }
                },
                cutout: '68%'
            }
        });
    }

    // 3. Doughnut Chart: Priority
    const ctxPriority = document.getElementById('beyondPriorityChart');
    if (ctxPriority && typeof Chart !== 'undefined') {
        const pDist = data.priorityDist || {};
        const labels = Object.keys(pDist);
        const values = Object.values(pDist);
        const colors = ['#ef4444', '#f59e0b', '#0284c7', '#94a3b8'];

        priorityChartInstance = new Chart(ctxPriority, {
            type: 'doughnut',
            data: {
                labels: labels.length ? labels : ['KRITIS', 'TINGGI', 'SEDANG', 'RENDAH'],
                datasets: [{
                    data: values.length ? values : [0, 0, 0, 0],
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 10, font: { family: 'Outfit', size: 11 } } }
                },
                cutout: '68%'
            }
        });
    }
}

// KPI Drill-Down Click Handler
function applyKpiDrilldown(status, urgensi) {
    document.getElementById('flt-status').value = status;
    document.getElementById('flt-urgensi').value = urgensi;

    // Highlight active KPI card
    document.querySelectorAll('.beyond-kpi-card').forEach(el => el.classList.remove('active-filter'));
    event.currentTarget.classList.add('active-filter');

    submitFilterAjax();
}

function handleFilterSubmit(e) {
    e.preventDefault();
    submitFilterAjax();
}

function handleUlpChange(ulpId) {
    submitFilterAjax();
}

function resetFilters() {
    document.getElementById('beyondFilterForm').reset();
    document.querySelectorAll('.beyond-kpi-card').forEach(el => el.classList.remove('active-filter'));
    document.querySelector('.beyond-kpi-card').classList.add('active-filter');
    submitFilterAjax();
}

// Seamless AJAX filtering
function submitFilterAjax() {
    const form = document.getElementById('beyondFilterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    params.set('format', 'json');

    const icon = document.getElementById('btn-refresh-icon');
    if (icon) icon.classList.add('fa-spin');

    fetch('<?= site_url('kubikel-inspeksi/beyond-kwh') ?>?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
        if (icon) icon.classList.remove('fa-spin');
        if (!res.success) return;

        // Update KPIs
        const k = res.kpi || {};
        document.getElementById('kpi-total-val').innerText = (k.total || 0).toLocaleString();
        document.getElementById('kpi-belum-val').innerText = (k.belum || 0).toLocaleString();
        document.getElementById('kpi-proses-val').innerText = (k.proses || 0).toLocaleString();
        document.getElementById('kpi-selesai-val').innerText = (k.selesai || 0).toLocaleString();
        document.getElementById('kpi-kritis-val').innerText = (k.kritis_terbuka || 0).toLocaleString();
        document.getElementById('hero-rate-label').innerText = (k.completion_rate || 0) + '%';
        document.getElementById('hero-rate-fill').style.width = Math.min(100, (k.completion_rate || 0)) + '%';

        // Update Charts
        if (trendChartInstance && res.monthlyTrend) {
            trendChartInstance.data.labels = res.monthlyTrend.labels;
            trendChartInstance.data.datasets[0].data = res.monthlyTrend.masuk;
            trendChartInstance.data.datasets[1].data = res.monthlyTrend.selesai;
            trendChartInstance.update();
        }

        if (statusChartInstance && res.statusDist) {
            statusChartInstance.data.labels = Object.keys(res.statusDist);
            statusChartInstance.data.datasets[0].data = Object.values(res.statusDist);
            statusChartInstance.update();
        }

        if (priorityChartInstance && res.priorityDist) {
            priorityChartInstance.data.labels = Object.keys(res.priorityDist);
            priorityChartInstance.data.datasets[0].data = Object.values(res.priorityDist);
            priorityChartInstance.update();
        }

        // Render Table Rows
        renderTableRows(res.rekomendasi || []);

        // Timestamp
        const now = new Date();
        const timeStr = String(now.getHours()).padStart(2, '0') + ':' +
                        String(now.getMinutes()).padStart(2, '0') + ':' +
                        String(now.getSeconds()).padStart(2, '0') + ' WIB';
        document.getElementById('header-last-updated').innerText = timeStr;
    })
    .catch(err => {
        if (icon) icon.classList.remove('fa-spin');
        console.error('Failed to update beyond dashboard:', err);
    });
}

function renderTableRows(items) {
    const tbody = document.getElementById('beyondTableBody');
    const summary = document.getElementById('table-summary-count');
    summary.innerText = 'Menampilkan ' + items.length + ' item rekomendasi';

    if (!items.length) {
        const colSpan = window.BEYOND_DATA.isAdmin ? 7 : 6;
        tbody.innerHTML = `
            <tr>
                <td colspan="${colSpan}" class="text-center py-5">
                    <div class="text-muted">
                        <i class="fas fa-inbox display-4 d-block mb-3 opacity-25"></i>
                        <h6 class="fw-bold mb-1">Tidak Ada Rekomendasi Ditemukan</h6>
                        <p class="small text-muted mb-2">Tidak ada data yang cocok dengan kriteria filter saat ini.</p>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="resetFilters()">
                            <i class="fas fa-undo me-1"></i> Bersihkan Filter
                        </button>
                    </div>
                </td>
            </tr>`;
        return;
    }

    let html = '';
    items.forEach(item => {
        const urgClass = 'badge-urgensi-' + (item.temuan_urgensi || 'sedang').toLowerCase();
        const stClass = 'badge-status-' + (item.status || 'terkirim').toLowerCase();
        const detailUrl = window.BEYOND_DATA.detailUrl + item.id;
        
        let waktu = item.waktu_kirim || item.created_at || '-';
        let petugasCol = '';
        if (window.BEYOND_DATA.isAdmin) {
            const petName = item.penerima_nama || 'Belum ditugaskan';
            petugasCol = `<td><span class="badge bg-light text-dark border"><i class="fas fa-user me-1 text-primary"></i>${escapeHtml(petName)}</span></td>`;
        }

        html += `
            <tr>
                <td>
                    <strong class="text-primary font-monospace">#REC-${item.id}</strong>
                    <div class="text-muted small mt-1"><i class="far fa-clock me-1"></i>${escapeHtml(waktu)}</div>
                </td>
                <td>
                    <span class="fw-bold text-dark">${escapeHtml(item.nomor_inspeksi || '-')}</span>
                    <div class="text-muted small">
                        ${escapeHtml(item.nama_ulp || 'ULP -')} &bull; ${escapeHtml(item.nama_penyulang || 'Feeder -')}
                    </div>
                </td>
                <td>
                    <div class="fw-semibold text-dark">${escapeHtml(item.temuan_komponen || 'Kubikel')} (Fasa ${escapeHtml(item.temuan_fasa || '-')})</div>
                    <small class="text-muted text-truncate d-block" style="max-width: 280px;" title="${escapeHtml(item.deskripsi_temuan || '')}">
                        ${escapeHtml(item.deskripsi_temuan || '-')}
                    </small>
                </td>
                <td>
                    <span class="badge ${urgClass} px-2 py-1">${escapeHtml(item.temuan_urgensi || 'SEDANG')}</span>
                </td>
                <td>
                    <span class="badge ${stClass} px-2 py-1">${escapeHtml(item.status || 'TERKIRIM')}</span>
                </td>
                ${petugasCol}
                <td class="text-end">
                    <a href="${detailUrl}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                        Buka <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </td>
            </tr>`;
    });
    tbody.innerHTML = html;
}

function refreshBeyondDashboard() {
    submitFilterAjax();
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>
<?= $this->endSection() ?>
