<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Detail Rekomendasi Beyond KWH<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Detail Rekomendasi Beyond KWH<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= esc($rekomendasi['nomor_inspeksi'] ?? 'Rekomendasi Temuan') ?></h1>
            <div class="text-muted">Tanggal inspeksi: <?= esc($rekomendasi['tgl_inspeksi'] ?? '-') ?></div>
        </div>
        <a href="<?= site_url('kubikel-inspeksi/beyond-kwh') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Daftar Rekomendasi</a>
    </div>

    <?php if ($message = session()->getFlashdata('error')): ?>
        <div class="alert alert-danger" role="alert"><?= esc($message) ?></div>
    <?php endif; ?>
    <div id="action-message" class="alert d-none" role="alert"></div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Temuan yang Ditugaskan</h2></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Komponen</dt><dd class="col-sm-8"><?= esc($rekomendasi['komponen'] ?? '-') ?></dd>
                        <dt class="col-sm-4">Fasa / Posisi</dt><dd class="col-sm-8"><?= esc($rekomendasi['fasa'] ?? '-') ?> / <?= esc($rekomendasi['posisi'] ?? '-') ?></dd>
                        <dt class="col-sm-4">Urgensi</dt><dd class="col-sm-8"><?= esc($rekomendasi['urgensi'] ?? '-') ?></dd>
                        <dt class="col-sm-4">Deskripsi Temuan</dt><dd class="col-sm-8"><?= nl2br(esc($rekomendasi['deskripsi_temuan'] ?? '-')) ?></dd>
                        <dt class="col-sm-4">Tindak Lanjut</dt><dd class="col-sm-8"><?= nl2br(esc($rekomendasi['deskripsi_rekomendasi'] ?? '-')) ?></dd>
                        <dt class="col-sm-4">Status Temuan</dt><dd class="col-sm-8"><?= esc($rekomendasi['status_temuan'] ?? '-') ?></dd>
                    </dl>
                </div>
            </div>

            <?php if ($foto): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white"><h2 class="h5 mb-0">Foto Terkait Checklist Temuan</h2></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($foto as $item): ?>
                                <div class="col-sm-6 col-xl-4">
                                    <a href="<?= esc(get_photo_url($item['path_file'] ?? null)) ?>" target="_blank" rel="noopener">
                                        <img src="<?= esc(get_photo_url($item['path_file'] ?? null)) ?>" alt="<?= esc($item['deskripsi'] ?? 'Foto temuan') ?>" class="img-fluid img-thumbnail">
                                    </a>
                                    <div class="small mt-1"><?= esc($item['kategori'] ?? '-') ?> · <?= esc($item['deskripsi'] ?? '') ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Status Rekomendasi</h2></div>
                <div class="card-body">
                    <p class="mb-1"><strong>Status:</strong> <?= esc($rekomendasi['status_rekomendasi'] ?? '-') ?></p>
                    <p class="small text-muted">Dikirim: <?= esc($rekomendasi['waktu_kirim'] ?? '-') ?><br>Dibaca: <?= esc($rekomendasi['waktu_baca'] ?? '-') ?><br>Selesai: <?= esc($rekomendasi['waktu_selesai'] ?? '-') ?></p>
                    <?php if (!empty($rekomendasi['catatan_penerima'])): ?>
                        <p><strong>Catatan:</strong><br><?= nl2br(esc($rekomendasi['catatan_penerima'])) ?></p>
                    <?php endif; ?>
                    <?php if (!in_array($rekomendasi['status_rekomendasi'] ?? '', ['SELESAI', 'DITOLAK'], true)): ?>
                        <form action="<?= site_url('kubikel-inspeksi/beyond-kwh/update-status/' . (int)$rekomendasi['rekomendasi_id']) ?>" method="post" data-ajax-form>
                            <?= csrf_field() ?>
                            <label for="status" class="form-label">Perbarui Status</label>
                            <select name="status" id="status" class="form-select mb-2" required>
                                <?php if (($rekomendasi['status_rekomendasi'] ?? '') === 'TERKIRIM'): ?><option value="DIBACA">Dibaca</option><?php endif; ?>
                                <?php if (in_array($rekomendasi['status_rekomendasi'] ?? '', ['TERKIRIM', 'DIBACA'], true)): ?><option value="DIPROSES">Diproses</option><option value="DITOLAK">Ditolak</option><?php endif; ?>
                                <?php if (($rekomendasi['status_rekomendasi'] ?? '') === 'DIPROSES'): ?><option value="SELESAI">Selesai</option><option value="DITOLAK">Ditolak</option><?php endif; ?>
                            </select>
                            <label for="catatan_penerima" class="form-label">Catatan Tindak Lanjut</label>
                            <textarea name="catatan_penerima" id="catatan_penerima" class="form-control mb-3" rows="4" maxlength="5000"><?= esc($rekomendasi['catatan_penerima'] ?? '') ?></textarea>
                            <button type="submit" class="btn btn-primary w-100">Simpan Status</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-ajax-form]');
    const message = document.getElementById('action-message');
    if (!form) return;
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const result = await response.json();
            if (!response.ok || result.success !== true) throw new Error(result.message || 'Permintaan gagal.');
            window.location.reload();
        } catch (error) {
            message.className = 'alert alert-danger';
            message.textContent = error.message || 'Permintaan gagal diproses.';
        } finally {
            button.disabled = false;
        }
    });
});
</script>
<?= $this->endSection() ?>
