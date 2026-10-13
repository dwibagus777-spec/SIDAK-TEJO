<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Tindak Lanjut Beyond KWH<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Tindak Lanjut Beyond KWH<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid px-4">
    <div class="mb-4">
        <h1 class="h3 mb-1"><i class="bi bi-lightning-charge me-2"></i>Rekomendasi Ditugaskan</h1>
        <p class="text-muted mb-0">Daftar ini hanya memuat rekomendasi Beyond KWH yang ditugaskan ke akun Anda.</p>
    </div>

    <?php if ($message = session()->getFlashdata('error')): ?>
        <div class="alert alert-danger" role="alert"><?= esc($message) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <?php if ($rekomendasi): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Dikirim</th><th>Inspeksi</th><th>Temuan</th><th>Urgensi</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($rekomendasi as $item): ?>
                                <tr>
                                    <td><?= esc($item['waktu_kirim'] ?? $item['created_at'] ?? '-') ?></td>
                                    <td><strong><?= esc($item['nomor_inspeksi'] ?? '-') ?></strong><br><span class="text-muted"><?= esc($item['tgl_inspeksi'] ?? '-') ?></span></td>
                                    <td><?= esc($item['temuan_komponen'] ?? '-') ?><br><?= esc($item['deskripsi_temuan'] ?? '-') ?></td>
                                    <td><?= esc($item['temuan_urgensi'] ?? '-') ?></td>
                                    <td><span class="badge text-bg-info"><?= esc($item['status'] ?? '-') ?></span></td>
                                    <td><a class="btn btn-sm btn-primary" href="<?= site_url('kubikel-inspeksi/beyond-kwh/detail/' . (int)$item['id']) ?>">Buka</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox display-5 d-block mb-2"></i>
                    Belum ada rekomendasi yang ditugaskan kepada Anda.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
