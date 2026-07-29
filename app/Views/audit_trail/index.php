<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Digital Evidence & Audit Trail<?= $this->endSection() ?>
<?= $this->section('page_title') ?>SIDAK TEJO Enterprise Digital Evidence & Audit Trail<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Phase 39 Digital Evidence & Audit Trail Design System */
    .audit-container {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .audit-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: 20px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
    }
</style>

<div class="audit-container container-fluid py-3">

    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap: 8px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center">
                <i class="fas fa-shield-halved text-warning me-2 fs-3"></i> AUDIT TRAIL & DIGITAL EVIDENCE
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">ENTERPRISE V25</span>
            </h3>
            <p class="text-muted small mb-0">Immutable User Activity Logs, Time Machine Version History, & Digital Evidence Stamping</p>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="audit-card p-3 mb-4">
        <form method="get" action="<?= site_url('audit-log') ?>" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="username" class="form-control form-control-sm" placeholder="Cari Username / Nama User..." value="<?= esc($filters['username'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <select name="aktivitas" class="form-select form-select-sm">
                    <option value="">-- Semua Aktivitas --</option>
                    <option value="CREATE_TEMUAN" <?= ($filters['aktivitas'] ?? '') === 'CREATE_TEMUAN' ? 'selected' : '' ?>>CREATE TEMUAN</option>
                    <option value="UPDATE_TEMUAN" <?= ($filters['aktivitas'] ?? '') === 'UPDATE_TEMUAN' ? 'selected' : '' ?>>UPDATE TEMUAN</option>
                    <option value="DELETE_TEMUAN" <?= ($filters['aktivitas'] ?? '') === 'DELETE_TEMUAN' ? 'selected' : '' ?>>DELETE TEMUAN</option>
                    <option value="LOGIN" <?= ($filters['aktivitas'] ?? '') === 'LOGIN' ? 'selected' : '' ?>>USER LOGIN</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm fw-bold px-3 w-100">Filter Log</button>
                <a href="<?= site_url('audit-log') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>
    </div>

    <!-- Audit Logs Table -->
    <div class="audit-card p-4">
        <h5 class="fw-bold text-dark mb-3"><i class="fas fa-list-ul text-primary me-2"></i> Audit Trail History Log</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                <thead class="table-light">
                    <tr>
                        <th>Waktu (WIB)</th>
                        <th>User & Role</th>
                        <th>Aktivitas</th>
                        <th>Detail Aktivitas</th>
                        <th>App & IP</th>
                        <th class="text-center">Evidence / Time Machine</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada data audit log tercatat.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><span class="fw-bold font-monospace text-dark"><?= esc($log['created_at']) ?></span></td>
                                <td>
                                    <span class="fw-bold text-primary d-block"><?= esc($log['username'] ?: 'Guest') ?></span>
                                    <span class="badge bg-light text-dark border" style="font-size: 9px;"><?= esc(strtoupper($log['role'] ?: 'GUEST')) ?></span>
                                </td>
                                <td><span class="badge bg-purple text-white" style="background:#7e22ce;"><?= esc($log['aktivitas']) ?></span></td>
                                <td><small class="text-muted"><?= esc($log['detail']) ?></small></td>
                                <td><small class="text-secondary font-monospace"><?= esc($log['app_type'] ?: 'WEB') ?> &middot; <?= esc($log['ip_address']) ?></small></td>
                                <td class="text-center">
                                    <?php if (!empty($log['temuan_id'])): ?>
                                        <a href="<?= site_url('digital-evidence/' . $log['temuan_id']) ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2">
                                            <i class="fas fa-file-contract me-1"></i> Evidence & Time Machine
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<?= $this->endSection() ?>
