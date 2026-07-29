<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-1 text-dark" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-network-wired text-primary me-2"></i> Integration Center (EIP)
            </h3>
            <p class="text-muted small mb-0">Enterprise Integration Platform & REST API Management SIDAK TEJO</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= site_url('api/docs/ui') ?>" target="_blank" class="btn btn-primary btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-book me-1"></i> Swagger OpenAPI Docs
            </a>
            <a href="<?= site_url('api/health') ?>" target="_blank" class="btn btn-outline-success btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-heartbeat me-1"></i> Health Check
            </a>
        </div>
    </div>

    <!-- System Health Alert Banner -->
    <div class="alert alert-<?= ($health['status'] === 'HEALTHY') ? 'success' : 'warning' ?> border-0 shadow-sm rounded-4 d-flex align-items-center justify-content-between mb-4">
        <div>
            <span class="fw-bold"><i class="fas fa-shield-heart me-2"></i> System Health Status: <?= esc($health['status']) ?></span>
            <small class="d-block text-muted">Disk Free: <?= esc($health['checks']['disk']['free_space'] ?? '-') ?> | Memory Peak: <?= esc($health['checks']['memory']['peak'] ?? '-') ?></small>
        </div>
        <span class="badge bg-dark font-monospace"><?= esc($health['timestamp']) ?></span>
    </div>

    <!-- API Dashboard KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 text-white" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                <div class="card-body p-3 text-center">
                    <small class="text-white-50 text-uppercase fw-bold" style="font-size: 11px;">Total API Request</small>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format($stats['total_requests']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 text-white" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%);">
                <div class="card-body p-3 text-center">
                    <small class="text-white-50 text-uppercase fw-bold" style="font-size: 11px;">Success (2xx)</small>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format($stats['success']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 text-white" style="background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);">
                <div class="card-body p-3 text-center">
                    <small class="text-white-50 text-uppercase fw-bold" style="font-size: 11px;">Failed / Error</small>
                    <h3 class="fw-bold mb-0 mt-1"><?= number_format($stats['failed']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 text-white" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);">
                <div class="card-body p-3 text-center">
                    <small class="text-white-50 text-uppercase fw-bold" style="font-size: 11px;">Rata-rata Latency</small>
                    <h3 class="fw-bold mb-0 mt-1"><?= esc($stats['avg_latency_ms']) ?> <small style="font-size: 14px;">ms</small></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Drivers & Export Engine -->
        <div class="col-lg-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="fas fa-plug text-primary me-2"></i> Integration Drivers</h5>
                    <p class="text-muted small mb-3">Driver pattern yang didukung untuk komunikasi multi-protokol:</p>
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <?php foreach ($drivers as $drv): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill font-monospace" style="font-size: 12px;">
                                <i class="fas fa-check-circle me-1"></i> <?= esc($drv) ?> Driver
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <hr>

                    <h6 class="fw-bold text-dark mb-2"><i class="fas fa-file-export text-success me-2"></i> Export Data Multi-Format</h6>
                    <div class="d-flex gap-2">
                        <a href="<?= site_url('integration/export?format=json') ?>" class="btn btn-outline-primary btn-sm rounded-pill font-weight-bold"><i class="fas fa-file-code me-1"></i> Export JSON</a>
                        <a href="<?= site_url('integration/export?format=xml') ?>" class="btn btn-outline-info btn-sm rounded-pill font-weight-bold"><i class="fas fa-file-excel me-1"></i> Export XML</a>
                        <a href="<?= site_url('integration/export?format=csv') ?>" class="btn btn-outline-success btn-sm rounded-pill font-weight-bold"><i class="fas fa-file-csv me-1"></i> Export CSV</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- API Keys Management -->
        <div class="col-lg-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-key text-warning me-2"></i> API Key Management</h5>
                        <form method="POST" action="<?= site_url('integration/generate-key') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-primary rounded-pill font-weight-bold"><i class="fas fa-plus me-1"></i> Generate Key</button>
                        </form>
                    </div>

                    <div class="table-responsive" style="max-height: 220px;">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                            <thead class="table-light">
                                <tr>
                                    <th>API Key</th>
                                    <th>Rate Limit</th>
                                    <th>Status</th>
                                    <th>Terakhir Digunakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($api_keys)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">Belum ada API Key</td></tr>
                                <?php else: ?>
                                    <?php foreach ($api_keys as $key): ?>
                                        <tr>
                                            <td><code class="text-primary font-monospace"><?= esc(substr($key['api_key'], 0, 16)) ?>...</code></td>
                                            <td><?= esc($key['rate_limit']) ?> req/hr</td>
                                            <td><span class="badge bg-<?= $key['is_active'] ? 'success' : 'danger' ?>"><?= $key['is_active'] ? 'Active' : 'Revoked' ?></span></td>
                                            <td><?= esc($key['last_used_at'] ?? 'Belum pernah') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Webhook Subscriptions -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-satellite-dish text-info me-2"></i> Webhook Event Subscriptions</h5>
            </div>

            <form method="POST" action="<?= site_url('integration/register-webhook') ?>" class="row g-2 mb-4">
                <?= csrf_field() ?>
                <div class="col-md-6 col-12">
                    <input type="url" name="url" class="form-control form-control-sm" placeholder="URL Target Webhook (e.g. https://api.external.com/webhook)" required>
                </div>
                <div class="col-md-4 col-12">
                    <select name="event" class="form-select form-select-sm">
                        <option value="Temuan Baru">Temuan Baru</option>
                        <option value="WO Baru">WO Baru</option>
                        <option value="WO Selesai">WO Selesai</option>
                        <option value="Update Progress">Update Progress</option>
                        <option value="Emergency Alert">Emergency Alert</option>
                        <option value="Dokumen Baru">Dokumen Baru</option>
                    </select>
                </div>
                <div class="col-md-2 col-12">
                    <button type="submit" class="btn btn-info btn-sm text-white w-100 rounded-pill font-weight-bold"><i class="fas fa-plus me-1"></i> Register Webhook</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                    <thead class="table-light">
                        <tr>
                            <th>Target URL</th>
                            <th>Event</th>
                            <th>Status</th>
                            <th>Terakhir Ditembak</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($webhooks)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada webhook terdaftar</td></tr>
                        <?php else: ?>
                            <?php foreach ($webhooks as $wh): ?>
                                <tr>
                                    <td><code class="text-dark"><?= esc($wh['url']) ?></code></td>
                                    <td><span class="badge bg-secondary"><?= esc($wh['event']) ?></span></td>
                                    <td><span class="badge bg-<?= $wh['is_active'] ? 'success' : 'danger' ?>"><?= $wh['is_active'] ? 'Active' : 'Disabled' ?></span></td>
                                    <td><?= esc($wh['last_triggered_at'] ?? 'Belum pernah') ?></td>
                                    <td>
                                        <a href="<?= site_url('integration/test-webhook/' . $wh['id']) ?>" class="btn btn-outline-warning btn-xs rounded-pill">
                                            <i class="fas fa-bolt me-1"></i> Test Event
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

    <!-- Real-time API Activity Logs -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-clock-rotate-left text-primary me-2"></i> Real-time API Activity Audit Log</h5>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu</th>
                            <th>Method</th>
                            <th>Endpoint</th>
                            <th>Status</th>
                            <th>Latency</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">Belum ada aktivitas API dicatat</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td><small class="text-muted"><?= esc($l['created_at']) ?></small></td>
                                    <td><span class="badge bg-outline-primary font-monospace"><?= esc($l['method']) ?></span></td>
                                    <td><code class="text-dark"><?= esc($l['endpoint']) ?></code></td>
                                    <td>
                                        <?php if ($l['status_code'] < 300): ?>
                                            <span class="badge bg-success"><?= esc($l['status_code']) ?> OK</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><?= esc($l['status_code']) ?> ERROR</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="font-monospace"><?= esc($l['duration_ms']) ?> ms</span></td>
                                    <td><small class="text-muted"><?= esc($l['ip_address']) ?></small></td>
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
