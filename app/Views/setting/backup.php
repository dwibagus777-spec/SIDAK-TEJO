<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Manajemen Backup & Restore<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-header d-print-none mb-3">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title text-primary font-weight-bold mb-1">
                    <i class="fas fa-database me-2"></i> Manajemen Backup & Restore Sistem
                </h2>
                <div class="text-muted small">Kelola backup otomatis Database MySQL, Folder Foto, dan Konfigurasi Sistem untuk Hostinger Shared Hosting.</div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <a href="<?= site_url('backup/create') ?>" class="btn btn-primary shadow-sm" onclick="return confirm('Buat Full Backup ZIP sekarang?');">
                    <i class="fas fa-archive me-1"></i> Buat Backup Sekarang
                </a>
                <button type="button" class="btn btn-warning text-dark shadow-sm" data-bs-toggle="modal" data-bs-target="#modalRestore">
                    <i class="fas fa-upload me-1"></i> Restore Sistem
                </button>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">

        <!-- Informational Card Cron Job Hostinger -->
        <div class="card card-modern mb-4 border-start border-primary border-4">
            <div class="card-body">
                <h4 class="card-title text-dark font-weight-bold mb-2">
                    <i class="fas fa-clock text-primary me-2"></i> Jadwal Backup Otomatis Harian (Hostinger Cron Job)
                </h4>
                <p class="text-muted small mb-2">
                    Untuk menjalankan backup otomatis setiap hari pada <strong>Hostinger Shared Hosting</strong>, tambahkan perintah Cron Job berikut pada panel Hostinger:
                </p>
                <div class="bg-light p-2 rounded font-monospace small border text-dark mb-2 select-all">
                    wget -q -O - "<?= esc($cronUrl) ?>" >/dev/null 2>&1
                </div>
                <div class="text-secondary small">
                    <i class="fas fa-info-circle text-info me-1"></i> Waktu eksekusi yang disarankan: <strong>Setiap Jam 02:00 Pagi (0 2 * * *)</strong>.
                </div>
            </div>
        </div>

        <!-- Tabel Daftar Backup -->
        <div class="card card-modern">
            <div class="card-header border-bottom bg-transparent py-3 d-flex justify-content-between align-items-center">
                <h3 class="card-title text-dark font-weight-bold m-0">
                    <i class="fas fa-file-archive text-success me-2"></i> Berkas Full Backup ZIP Terlampir
                </h3>
                <span class="badge bg-primary rounded-pill"><?= count($backups) ?> Berkas</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-vcenter card-table m-0">
                        <thead>
                            <tr>
                                <th>Nama Berkas Backup</th>
                                <th>Ukuran File</th>
                                <th>Tanggal Dibuat</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($backups)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="fas fa-folder-open fa-2x mb-2 d-block opacity-50"></i>
                                        Belum ada berkas backup yang dibuat. Klik tombol "Buat Backup Sekarang" di atas.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($backups as $b): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-file-archive text-warning me-2"></i>
                                            <strong class="text-dark"><?= esc($b['filename']) ?></strong>
                                        </td>
                                        <td><span class="badge bg-secondary"><?= esc($b['size_fmt']) ?></span></td>
                                        <td><span class="text-muted small"><?= date('d-m-Y H:i:s', strtotime($b['created_at'])) ?> WIB</span></td>
                                        <td class="text-end">
                                            <a href="<?= site_url('backup/download/' . $b['filename']) ?>" class="btn btn-sm btn-outline-primary me-1" title="Unduh Berkas ZIP">
                                                <i class="fas fa-download me-1"></i> Unduh
                                            </a>
                                            <a href="<?= site_url('backup/delete/' . $b['filename']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus berkas backup <?= esc($b['filename']) ?>?');" title="Hapus Berkas">
                                                <i class="fas fa-trash me-1"></i> Hapus
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
</div>

<!-- Modal Restore Sistem -->
<div class="modal fade" id="modalRestore" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= site_url('backup/restore') ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-upload me-2"></i> Restore Sistem dari ZIP Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small mb-3">
                        <i class="fas fa-exclamation-triangle me-1"></i> <strong>PERHATIAN:</strong> Proses restore akan memperbarui struktur database dan memulihkan berkas foto temuan. Pastikan berkas ZIP yang diunggah adalah hasil dari ekspor backup SIDAK TEJO.
                    </div>
                    <div class="mb-3">
                        <label for="backup_zip" class="form-label font-weight-bold">Pilih Berkas Backup (.zip)</label>
                        <input type="file" class="form-control" id="backup_zip" name="backup_zip" accept=".zip" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning text-dark font-weight-bold" onclick="return confirm('Proses ini akan memperbarui data sistem. Lanjutkan restore?');">
                        <i class="fas fa-undo me-1"></i> Mulai Restore Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
