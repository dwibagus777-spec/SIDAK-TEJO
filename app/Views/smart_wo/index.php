<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Smart Work Order Center<?= $this->endSection() ?>
<?= $this->section('page_title') ?>SIDAK TEJO Smart Work Order & Resource Optimization Center<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Phase 35 Smart Work Order Design System */
    .smart-wo-container {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .wo-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: 20px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
        transition: all 0.28s ease;
        overflow: hidden;
    }
    .wo-card:hover {
        box-shadow: 0 18px 30px -8px rgba(15, 23, 42, 0.1);
    }

    /* Hourly Timeline Bar */
    .timeline-hour-slot {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 10px 14px;
        border-left: 3px solid #0284c7;
        background: #f8fafc;
        border-radius: 10px;
        margin-bottom: 8px;
    }
</style>

<div class="smart-wo-container container-fluid py-3">

    <!-- Top Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center">
                <i class="fas fa-screwdriver-wrench text-warning me-2 fs-3"></i> SMART WORK ORDER CENTER
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">ENTERPRISE V23</span>
            </h3>
            <p class="text-muted small mb-0">Auto Work Order Generation, Auto Assignment, Route & Travel Time Optimization, & Digital Checklist</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= site_url('work-orders/create') ?>" class="btn btn-primary btn-sm rounded-pill font-weight-bold px-3 shadow-sm">
                <i class="fas fa-plus-circle me-1"></i> Buat Work Order Manual
            </a>
        </div>
    </div>

    <!-- KPI Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="wo-card p-3 border-start border-4 border-primary">
                <small class="text-muted fw-bold d-block" style="font-size: 10px;">TOTAL WORK ORDERS</small>
                <h3 class="fw-bold mb-0 text-dark mt-1"><?= number_format($analytics['total_wo'] ?? 0) ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="wo-card p-3 border-start border-4 border-danger">
                <small class="text-danger fw-bold d-block" style="font-size: 10px;">WO CRITICAL (EMERGENCY)</small>
                <h3 class="fw-bold mb-0 text-danger mt-1"><?= number_format($analytics['critical_wo'] ?? 0) ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="wo-card p-3 border-start border-4 border-success">
                <small class="text-success fw-bold d-block" style="font-size: 10px;">SLA COMPLIANCE</small>
                <h3 class="fw-bold mb-0 text-success mt-1"><?= $analytics['sla_compliance'] ?? 100 ?>%</h3>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="wo-card p-3 border-start border-4 border-info">
                <small class="text-info fw-bold d-block" style="font-size: 10px;">OPTIMIZED ROUTE TIME</small>
                <h3 class="fw-bold mb-0 text-info mt-1"><?= $analytics['est_travel_minutes'] ?? 25 ?> <small style="font-size: 12px; color:#64748b;">Menit</small></h3>
            </div>
        </div>
    </div>

    <!-- AI OPTIMIZED WORK ORDER SCHEDULE & ROUTE SEQUENCE TABLE -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8 col-12">
            <div class="wo-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-route text-success me-2"></i> AI Optimized Route & Work Sequence</h5>
                    <span class="badge bg-success rounded-pill px-3">Travel Time Minimized</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                        <thead class="table-light">
                            <tr>
                                <th># Rute</th>
                                <th>Nomor WO & Pekerjaan</th>
                                <th>Prioritas AI</th>
                                <th>Petugas Assigned</th>
                                <th>Est. Durasi</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($analytics['optimized_wos'])): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada Work Order aktif.</td></tr>
                            <?php else: ?>
                                <?php foreach ($analytics['optimized_wos'] as $idx => $wo): ?>
                                    <tr>
                                        <td><span class="badge bg-dark rounded-circle font-monospace"><?= $idx + 1 ?></span></td>
                                        <td>
                                            <span class="fw-bold font-monospace text-primary d-block"><?= esc($wo['nomor_wo']) ?></span>
                                            <span class="fw-bold text-dark"><?= esc($wo['judul_wo'] ?? $wo['judul_pekerjaan'] ?? '-') ?></span>
                                        </td>
                                        <td><span class="badge <?= $wo['badge_class'] ?>"><?= $wo['auto_priority'] ?></span></td>
                                        <td><small class="fw-bold text-secondary"><?= esc($wo['petugas_assigned'] ?: 'Tim PDKB UP3') ?></small></td>
                                        <td><small class="text-muted"><i class="fas fa-clock me-1 text-info"></i> <?= $analytics['est_job_duration'] ?></small></td>
                                        <td><span class="badge bg-light text-dark border"><?= esc($wo['status']) ?></span></td>
                                        <td class="text-center">
                                            <a href="<?= site_url('work-orders/edit/' . $wo['id']) ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2">Update &rarr;</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Hourly Work Timeline -->
        <div class="col-lg-4 col-12">
            <div class="wo-card p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-calendar-day text-primary me-2"></i> Timeline Jam Kerja Harian</h5>
                <div class="timeline-hour-slot">
                    <span class="fw-bold text-primary font-monospace">08:00</span>
                    <div><span class="fw-bold text-dark d-block small">Safety Briefing & Equipment Check</span><small class="text-muted" style="font-size: 10px;">Posko UP3 Sidoarjo</small></div>
                </div>
                <div class="timeline-hour-slot" style="border-left-color: #f59e0b;">
                    <span class="fw-bold text-warning font-monospace">09:00</span>
                    <div><span class="fw-bold text-dark d-block small">Inspeksi Hotline & Hotspot</span><small class="text-muted" style="font-size: 10px;">Penyulang Klurak &middot; LBS Klurak</small></div>
                </div>
                <div class="timeline-hour-slot" style="border-left-color: #10b981;">
                    <span class="fw-bold text-success font-monospace">11:30</span>
                    <div><span class="fw-bold text-dark d-block small">Pemberkasan Berita Acara & Foto</span><small class="text-muted" style="font-size: 10px;">Digital Signature & Submission</small></div>
                </div>
            </div>
        </div>
    </div>

    <!-- DIGITAL CHECKLIST & REQUIRED MATERIALS LIST -->
    <div class="row g-4">
        <div class="col-lg-6 col-12">
            <div class="wo-card p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-list-check text-success me-2"></i> Digital Safety & Inspection Checklist</h5>
                <?php foreach ($analytics['checklist'] as $chk): ?>
                    <div class="p-2 border rounded-3 mb-2 bg-light d-flex align-items-center justify-content-between">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" <?= $chk['done'] ? 'checked' : '' ?> style="cursor: pointer;">
                            <label class="form-check-label fw-bold text-dark small" style="cursor: pointer;"><?= esc($chk['task']) ?></label>
                        </div>
                        <?php if ($chk['done']): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-check me-1"></i> Tuntas</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="fas fa-clock me-1"></i> Pending</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-6 col-12">
            <div class="wo-card p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-boxes-packing text-amber me-2"></i> Requirement Material & Tools</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                        <thead class="table-light">
                            <tr><th>Nama Material</th><th>Qty</th><th>Status Stok</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics['materials'] as $mat): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= esc($mat['name']) ?></td>
                                    <td><span class="badge bg-dark font-monospace"><?= $mat['qty'] ?> <?= $mat['unit'] ?? 'pcs' ?></span></td>
                                    <td><span class="badge bg-<?= $mat['status'] === 'READY' ? 'success' : 'warning' ?>"><?= $mat['status'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
<?= $this->endSection() ?>
