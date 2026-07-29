<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <!-- Top Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-bell text-warning me-2 fs-3"></i> SMART NOTIFICATION CENTER
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">ENTERPRISE V21</span>
            </h3>
            <p class="text-muted small mb-0">Multi-Channel Dispatcher (Push FCM, WhatsApp, Telegram Bot, Email, In-App & Voice Alert)</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= site_url('notifications/read-all') ?>" class="btn btn-outline-primary btn-sm rounded-pill font-weight-bold">
                <i class="fas fa-check-double me-1"></i> Tandai Semua Dibaca
            </a>
            <?php if (in_array(session()->get('user_role'), ['administrator'])): ?>
                <a href="<?= site_url('notifications/templates') ?>" class="btn btn-primary btn-sm rounded-pill font-weight-bold">
                    <i class="fas fa-pen-to-square me-1"></i> Template & Rules
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- Notification Feed list -->
        <div class="col-lg-8 col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-list text-primary me-2"></i> In-App Notification Feed</h5>
                    <span class="badge bg-danger rounded-pill"><?= $unreadCount ?> Belum Dibaca</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <div class="p-5 text-center text-muted">
                            <i class="fas fa-bell-slash fs-1 text-secondary mb-2 d-block"></i>
                            Belum ada notifikasi masuk.
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $n): ?>
                                <div class="list-group-item p-3 border-bottom <?= empty($n['read_at']) ? 'bg-light border-start border-4 border-primary' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-dark" style="font-size: 14px;">
                                            <i class="fas fa-circle-info text-primary me-1"></i> <?= esc($n['title']) ?>
                                        </span>
                                        <small class="text-muted font-monospace"><?= indo_datetime($n['created_at']) ?></small>
                                    </div>
                                    <p class="text-secondary small mb-2"><?= esc($n['message']) ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-secondary font-monospace" style="font-size: 10px;"><?= esc($n['channel']) ?></span>
                                        <?php if (empty($n['read_at'])): ?>
                                            <span class="badge bg-warning text-dark font-weight-normal" style="font-size: 10px;">Belum dibaca</span>
                                        <?php else: ?>
                                            <span class="badge bg-success font-weight-normal" style="font-size: 10px;"><i class="fas fa-check me-1"></i> Dibaca</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Side Quick Channels & DND Settings -->
        <div class="col-lg-4 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-sliders text-success me-2"></i> Status Channel Notifikasi</h5>
                <ul class="list-group list-group-flush mb-3" style="font-size: 13px;">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span><i class="fab fa-whatsapp text-success me-2"></i> WhatsApp Gateway</span>
                        <span class="badge bg-success">AKTIF (Fonnte)</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span><i class="fab fa-telegram text-info me-2"></i> Telegram Bot</span>
                        <span class="badge bg-success">AKTIF</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span><i class="fas fa-mobile-screen-button text-primary me-2"></i> Push Notification (FCM)</span>
                        <span class="badge bg-success">AKTIF</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span><i class="fas fa-envelope text-warning me-2"></i> Email Queue</span>
                        <span class="badge bg-success">AKTIF</span>
                    </li>
                </ul>
                <a href="<?= site_url('notifications/preferences') ?>" class="btn btn-outline-primary btn-sm w-100 rounded-pill font-weight-bold">
                    <i class="fas fa-gear me-1"></i> Pengaturan DND & Preferensi
                </a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
