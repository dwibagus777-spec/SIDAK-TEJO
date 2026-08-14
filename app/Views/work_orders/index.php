<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <!-- Top Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-screwdriver-wrench text-warning me-2"></i> WORK ORDER MANAGEMENT
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">ENTERPRISE V17</span>
            </h3>
            <p class="text-muted small mb-0">Sistem Penugasan, Checklist Pekerjaan, Penggunaan Material & Eksekusi Jaringan</p>
        </div>
        <div>
            <?php if (in_array(session()->get('user_role'), ['administrator', 'admin_ulp', 'supervisor_ulp', 'supervisor_up3', 'inspeksi'])): ?>
                <a href="<?= site_url('work-orders/create') ?>" class="btn btn-primary btn-sm font-weight-bold rounded-pill shadow-sm">
                    <i class="fas fa-plus-circle me-1"></i> Terbitkan Work Order
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stat KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small font-weight-bold d-block">TOTAL WO</span>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format($stats['total']) ?></h3>
                    </div>
                    <div class="p-3 rounded-circle bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-clipboard-list fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small font-weight-bold d-block">WO AKTIF</span>
                        <h3 class="fw-bold mb-0 text-warning"><?= number_format($stats['aktif']) ?></h3>
                    </div>
                    <div class="p-3 rounded-circle bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-spinner fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small font-weight-bold d-block">WO SELESAI</span>
                        <h3 class="fw-bold mb-0 text-success"><?= number_format($stats['selesai']) ?></h3>
                    </div>
                    <div class="p-3 rounded-circle bg-success bg-opacity-10 text-success">
                        <i class="fas fa-check-double fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small font-weight-bold d-block">WO OVERDUE</span>
                        <h3 class="fw-bold mb-0 text-danger"><?= number_format($stats['overdue']) ?></h3>
                    </div>
                    <div class="p-3 rounded-circle bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-hourglass-end fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= site_url('work-orders') ?>" class="row g-2 align-items-center">
                <div class="col-md-3 col-12">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari No WO / Judul / Petugas / Asset..." value="<?= esc($filters['search'] ?? '') ?>">
                </div>
                <div class="col-md-2 col-6">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- Semua Status --</option>
                        <option value="AKTIF" <?= in_array(strtoupper($filters['status'] ?? ''), ['AKTIF', 'PROSES']) ? 'selected' : '' ?>>WO AKTIF (BELUM SELESAI)</option>
                        <option value="OPEN" <?= ($filters['status'] ?? '') === 'OPEN' ? 'selected' : '' ?>>OPEN</option>
                        <option value="ASSIGNED" <?= ($filters['status'] ?? '') === 'ASSIGNED' ? 'selected' : '' ?>>ASSIGNED</option>
                        <option value="PROGRESS" <?= ($filters['status'] ?? '') === 'PROGRESS' ? 'selected' : '' ?>>PROGRESS</option>
                        <option value="WAITING_MATERIAL" <?= ($filters['status'] ?? '') === 'WAITING_MATERIAL' ? 'selected' : '' ?>>WAITING MATERIAL</option>
                        <option value="WAITING_PADAM" <?= ($filters['status'] ?? '') === 'WAITING_PADAM' ? 'selected' : '' ?>>WAITING PADAM</option>
                        <option value="COMPLETED" <?= ($filters['status'] ?? '') === 'COMPLETED' ? 'selected' : '' ?>>COMPLETED</option>
                        <option value="CANCELLED" <?= ($filters['status'] ?? '') === 'CANCELLED' ? 'selected' : '' ?>>CANCELLED</option>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <select name="prioritas" class="form-select form-select-sm">
                        <option value="">-- Prioritas --</option>
                        <option value="EMERGENCY" <?= ($filters['prioritas'] ?? '') === 'EMERGENCY' ? 'selected' : '' ?>>EMERGENCY</option>
                        <option value="HIGH" <?= ($filters['prioritas'] ?? '') === 'HIGH' ? 'selected' : '' ?>>HIGH</option>
                        <option value="MEDIUM" <?= ($filters['prioritas'] ?? '') === 'MEDIUM' ? 'selected' : '' ?>>MEDIUM</option>
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
                    <a href="<?= site_url('work-orders') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-rotate-left"></i></a>
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
                            <th class="ps-3">Nomor WO</th>
                            <th>Judul Pekerjaan</th>
                            <th>Asset PLN</th>
                            <th>Assigned To</th>
                            <th>Prioritas</th>
                            <th>Status WO</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($workOrders)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada data Work Order.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($workOrders as $wo): ?>
                                <tr>
                                    <td class="ps-3">
                                        <a href="<?= site_url('work-orders/detail/' . $wo['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                            <?= esc($wo['nomor_wo']) ?>
                                        </a>
                                        <small class="text-muted d-block"><?= indo_date($wo['created_at']) ?></small>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark d-block"><?= esc($wo['judul_wo']) ?></span>
                                        <?php if (!empty($wo['nomor_temuan'])): ?>
                                            <small class="text-secondary"><i class="fas fa-file-invoice me-1"></i> Temuan: <?= esc($wo['nomor_temuan']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($wo['nama_asset'])): ?>
                                            <span class="fw-bold text-secondary d-block"><?= esc($wo['nama_asset']) ?></span>
                                            <small class="font-monospace text-muted"><?= esc($wo['kode_asset']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark d-block"><?= esc($wo['assigned_to'] ?: 'Unassigned') ?></span>
                                        <small class="text-muted"><?= esc($wo['pelaksana'] ?: 'INSPEKSI') ?></small>
                                    </td>
                                    <td>
                                        <?php if (strtoupper($wo['prioritas']) === 'EMERGENCY'): ?>
                                            <span class="badge bg-danger animate__animated animate__pulse animate__infinite">EMERGENCY</span>
                                        <?php elseif (strtoupper($wo['prioritas']) === 'HIGH'): ?>
                                            <span class="badge bg-warning text-dark">HIGH</span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-white">MEDIUM</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $st = strtoupper($wo['status']);
                                        $stClass = match($st) {
                                            'COMPLETED'        => 'bg-success',
                                            'PROGRESS'         => 'bg-warning text-dark',
                                            'WAITING_MATERIAL' => 'bg-purple text-white',
                                            'WAITING_PADAM'    => 'bg-indigo text-white',
                                            'CANCELLED'        => 'bg-secondary',
                                            default            => 'bg-primary'
                                        };
                                        ?>
                                        <span class="badge <?= $stClass ?>"><?= $st ?></span>
                                    </td>
                                    <td class="text-center pe-3">
                                        <a href="<?= site_url('work-orders/detail/' . $wo['id']) ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2">
                                            <i class="fas fa-eye me-1"></i> Kelola
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
