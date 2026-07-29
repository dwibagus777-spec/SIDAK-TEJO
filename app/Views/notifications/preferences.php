<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0 font-weight-bold" style="font-family: 'Outfit', sans-serif;">
            <i class="fas fa-sliders text-success me-2"></i> Pengaturan DND & Preferensi Notifikasi
        </h3>
        <a href="<?= site_url('notifications') ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h5 class="fw-bold text-dark mb-3">Tentukan Channel Notifikasi Aktif</h5>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="pref-wa" <?= !empty($prefs['wa_enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold text-dark" for="pref-wa"><i class="fab fa-whatsapp text-success me-2"></i> WhatsApp Notification</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="pref-telegram" <?= !empty($prefs['telegram_enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold text-dark" for="pref-telegram"><i class="fab fa-telegram text-info me-2"></i> Telegram Notification</label>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="pref-push" <?= !empty($prefs['push_enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold text-dark" for="pref-push"><i class="fas fa-mobile-screen-button text-primary me-2"></i> Push Notification (FCM)</label>
        </div>

        <hr class="my-4">

        <h5 class="fw-bold text-dark mb-2"><i class="fas fa-moon text-indigo me-2"></i> Mode Jangan Ganggu (Do Not Disturb - DND)</h5>
        <p class="text-muted small">Saat mode DND aktif, notifikasi non-emergency ditahan. Notifikasi EMERGENCY tetap dikirimkan.</p>

        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="pref-dnd" <?= !empty($prefs['dnd_enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold text-dark" for="pref-dnd">Aktifkan Do Not Disturb (DND)</label>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
