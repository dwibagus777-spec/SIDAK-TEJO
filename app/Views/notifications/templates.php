<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-dark mb-0 font-weight-bold" style="font-family: 'Outfit', sans-serif;">
            <i class="fas fa-pen-to-square text-primary me-2"></i> Editor Template Notifikasi
        </h3>
        <a href="<?= site_url('notifications') ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Key Template</th>
                            <th>Judul Template</th>
                            <th>Body / Isi Pesan</th>
                            <th>Channel</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $t): ?>
                            <tr>
                                <td class="ps-3 fw-bold font-monospace text-primary"><?= esc($t['template_key']) ?></td>
                                <td class="fw-bold text-dark"><?= esc($t['title']) ?></td>
                                <td class="text-secondary" style="max-width: 300px;"><?= esc($t['body']) ?></td>
                                <td><span class="badge bg-secondary"><?= esc($t['channel']) ?></span></td>
                                <td><span class="badge bg-success">AKTIF</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
