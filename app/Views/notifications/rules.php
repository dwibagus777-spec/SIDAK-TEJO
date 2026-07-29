<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0 font-weight-bold" style="font-family: 'Outfit', sans-serif;">
            <i class="fas fa-robot text-warning me-2"></i> Automation Rule Engine Notifikasi
        </h3>
        <a href="<?= site_url('notifications') ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h5 class="fw-bold text-dark mb-3"><i class="fas fa-code-branch me-2 text-primary"></i> Rule Otomatisasi Terdaftar</h5>
        <div class="p-3 bg-light rounded-3 border mb-3">
            <div class="fw-bold text-dark small mb-1"><i class="fas fa-bolt text-danger me-1"></i> Rule 1: Emergency Multi-Channel Dispatch</div>
            <p class="small text-muted mb-0"><strong>IF</strong> Prioritas = <code>EMERGENCY</code> <strong>THEN</strong> Kirim WhatsApp & Push FCM ke Supervisor & Manager ULP.</p>
        </div>
        <div class="p-3 bg-light rounded-3 border">
            <div class="fw-bold text-dark small mb-1"><i class="fas fa-hourglass-end text-warning me-1"></i> Rule 2: SLA Overdue Escalation</div>
            <p class="small text-muted mb-0"><strong>IF</strong> SLA Terlewati (> 24 Jam) <strong>THEN</strong> Kirim Peringatan Eskalasi ke Admin Pusat & Telegram Group.</p>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
