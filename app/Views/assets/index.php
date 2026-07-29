<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <!-- Top Header & Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-boxes-stacked text-warning me-2"></i> MASTER ASSET MANAGEMENT
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">ENTERPRISE V17</span>
            </h3>
            <p class="text-muted small mb-0">Manajemen Aset PLN Jaringan Distribusi 20KV Terintegrasi QR Code, Barcode & GPS</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if (in_array(session()->get('user_role'), ['administrator', 'admin_ulp', 'inspeksi'])): ?>
                <a href="<?= site_url('assets/create') ?>" class="btn btn-primary btn-sm font-weight-bold rounded-pill shadow-sm">
                    <i class="fas fa-plus-circle me-1"></i> Tambah Asset Baru
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stat KPI Cards Grid -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small font-weight-bold d-block">TOTAL ASSET</span>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format($stats['total']) ?></h3>
                    </div>
                    <div class="p-3 rounded-circle bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-cubes fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small font-weight-bold d-block">STATUS NORMAL</span>
                        <h3 class="fw-bold mb-0 text-success"><?= number_format($stats['normal']) ?></h3>
                    </div>
                    <div class="p-3 rounded-circle bg-success bg-opacity-10 text-success">
                        <i class="fas fa-circle-check fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small font-weight-bold d-block">BERMASALAH</span>
                        <h3 class="fw-bold mb-0 text-warning"><?= number_format($stats['bermasalah']) ?></h3>
                    </div>
                    <div class="p-3 rounded-circle bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-triangle-exclamation fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small font-weight-bold d-block">CRITICAL</span>
                        <h3 class="fw-bold mb-0 text-danger"><?= number_format($stats['critical']) ?></h3>
                    </div>
                    <div class="p-3 rounded-circle bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-radiation fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= site_url('assets') ?>" class="row g-2 align-items-center">
                <div class="col-md-3 col-12">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari Kode / Nama Asset / Merk / No. Seri..." value="<?= esc($filters['search'] ?? '') ?>">
                </div>
                <div class="col-md-2 col-6">
                    <select name="jenis_asset" class="form-select form-select-sm">
                        <option value="">-- Semua Jenis --</option>
                        <?php foreach (['Gardu', 'Trafo', 'Kubikel', 'LBS', 'Recloser', 'Section', 'Penyulang', 'Tiang', 'JTM', 'JTR', 'PHB', 'APP', 'Meter', 'Grounding'] as $j): ?>
                            <option value="<?= $j ?>" <?= ($filters['jenis_asset'] ?? '') === $j ? 'selected' : '' ?>><?= $j ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- Semua Status --</option>
                        <option value="NORMAL" <?= ($filters['status'] ?? '') === 'NORMAL' ? 'selected' : '' ?>>NORMAL</option>
                        <option value="BERMASALAH" <?= ($filters['status'] ?? '') === 'BERMASALAH' ? 'selected' : '' ?>>BERMASALAH</option>
                        <option value="CRITICAL" <?= ($filters['status'] ?? '') === 'CRITICAL' ? 'selected' : '' ?>>CRITICAL</option>
                    </select>
                </div>
                <div class="col-md-3 col-6">
                    <select name="ulp_id" class="form-select form-select-sm">
                        <option value="">-- Semua ULP --</option>
                        <?php foreach ($ulps as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($filters['ulp_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-6 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold"><i class="fas fa-filter me-1"></i> Filter</button>
                    <a href="<?= site_url('assets') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-rotate-left"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Kode Asset</th>
                            <th>Nama Asset</th>
                            <th>Jenis</th>
                            <th>ULP & Lokasi</th>
                            <th>Kapasitas / Merk</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assets)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada data asset PLN.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assets as $a): ?>
                                <tr>
                                    <td class="ps-3">
                                        <a href="<?= site_url('assets/detail/' . $a['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                            <?= esc($a['kode_asset']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark d-block"><?= esc($a['nama_asset']) ?></span>
                                        <small class="text-muted">SN: <?= esc($a['nomor_seri'] ?: '-') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= esc($a['jenis_asset']) ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-secondary d-block"><?= esc($a['nama_ulp'] ?: '-') ?></span>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 200px;" title="<?= esc($a['lokasi']) ?>"><?= esc($a['lokasi']) ?></small>
                                    </td>
                                    <td>
                                        <small class="d-block text-dark fw-bold"><?= esc($a['merk'] ?: '-') ?></small>
                                        <small class="text-muted"><?= esc($a['kapasitas'] ?: '-') ?></small>
                                    </td>
                                    <td>
                                        <?php if (strtoupper($a['status']) === 'CRITICAL'): ?>
                                            <span class="badge bg-danger animate__animated animate__pulse animate__infinite"><i class="fas fa-radiation me-1"></i> CRITICAL</span>
                                        <?php elseif (strtoupper($a['status']) === 'BERMASALAH'): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-triangle-exclamation me-1"></i> BERMASALAH</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> NORMAL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center pe-3">
                                        <a href="<?= site_url('assets/detail/' . $a['id']) ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2">
                                            <i class="fas fa-eye me-1"></i> Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
