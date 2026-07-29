<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-1 text-dark" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-database text-warning me-2"></i> Database Backup & Restore (Hostinger)
            </h3>
            <p class="text-muted small mb-0">Modul Manajemen Backup, Restore & Audit Database SIDAK TEJO (`https://sidaktejo.site`)</p>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="<?= site_url('backup-database/create') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-warning text-dark btn-sm rounded-pill font-weight-bold px-3 shadow-sm">
                    <i class="fas fa-file-export me-1"></i> Buat Backup SQL Baru
                </button>
            </form>
            <a href="<?= site_url('backup-database/clean-old') ?>" class="btn btn-outline-danger btn-sm rounded-pill font-weight-bold px-3" onclick="return confirm('Hapus seluruh backup yang berusia lebih dari 30 hari?')">
                <i class="fas fa-broom me-1"></i> Bersihkan >30 Hari
            </a>
        </div>
    </div>

    <!-- Metadata Info Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
                <div class="card-body p-3 text-center">
                    <small class="text-white-50 text-uppercase fw-bold" style="font-size: 11px;">Database Host</small>
                    <h6 class="fw-bold mb-0 mt-1 font-monospace text-warning"><?= esc($db_host) ?></h6>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 text-white" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                <div class="card-body p-3 text-center">
                    <small class="text-white-50 text-uppercase fw-bold" style="font-size: 11px;">Nama Database</small>
                    <h6 class="fw-bold mb-0 mt-1 font-monospace text-white"><?= esc($db_name) ?></h6>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 text-white" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%);">
                <div class="card-body p-3 text-center">
                    <small class="text-white-50 text-uppercase fw-bold" style="font-size: 11px;">Ukuran Database</small>
                    <h4 class="fw-bold mb-0 mt-1"><?= esc($db_size) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 text-white" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);">
                <div class="card-body p-3 text-center">
                    <small class="text-white-50 text-uppercase fw-bold" style="font-size: 11px;">Jumlah Tabel</small>
                    <h4 class="fw-bold mb-0 mt-1"><?= esc($table_count) ?> <small style="font-size: 13px;">tabel</small></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- List of Backup Files -->
        <div class="col-lg-8 col-12">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="fas fa-folder-open text-primary me-2"></i> Daftar File Backup SQL (`/writable/backups/database/`)</h5>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama File SQL</th>
                                    <th>Ukuran</th>
                                    <th>Tanggal & Jam Backup</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($files)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada file backup tersimpan</td></tr>
                                <?php else: ?>
                                    <?php foreach ($files as $f): ?>
                                        <tr>
                                            <td>
                                                <code class="text-dark fw-bold"><?= esc($f['filename']) ?></code>
                                                <?php if ($f['is_old']): ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1" style="font-size: 10px;">>30 Hari</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-secondary-subtle text-dark"><?= esc($f['size_formatted']) ?></span></td>
                                            <td><small class="text-muted"><?= esc($f['created_at']) ?></small></td>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end gap-1">
                                                    <a href="<?= site_url('backup-database/download/' . $f['filename']) ?>" class="btn btn-outline-success btn-xs rounded-pill" title="Unduh File SQL">
                                                        <i class="fas fa-download me-1"></i> Unduh
                                                    </a>
                                                    <a href="<?= site_url('backup-database/delete/' . $f['filename']) ?>" class="btn btn-outline-danger btn-xs rounded-pill" onclick="return confirm('Hapus file backup ini?')" title="Hapus Backup">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
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

        <!-- Restore Database (Super Admin Only) -->
        <div class="col-lg-4 col-12">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-2"><i class="fas fa-clock-rotate-left text-danger me-2"></i> Restore Database</h5>
                    <p class="text-muted small mb-3">Pulihkan seluruh struktur & data database Hostinger dari file backup SQL.</p>

                    <div class="alert alert-warning border-0 small py-2 mb-3">
                        <i class="fas fa-exclamation-triangle me-1"></i> <strong>PERHATIAN:</strong> Proses restore akan menimpa seluruh tabel yang ada pada database Hostinger.
                    </div>

                    <form method="POST" action="<?= site_url('backup-database/restore') ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Pilih File Backup Tersimpan</label>
                            <select name="selected_filename" class="form-select form-select-sm">
                                <option value="">-- Upload File Baru di Bawah --</option>
                                <?php foreach ($files as $f): ?>
                                    <option value="<?= esc($f['filename']) ?>"><?= esc($f['filename']) ?> (<?= esc($f['size_formatted']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Atau Upload File SQL Baru</label>
                            <input type="file" name="backup_file" class="form-control form-control-sm" accept=".sql">
                        </div>

                        <button type="submit" class="btn btn-danger btn-sm w-100 rounded-pill font-weight-bold" onclick="return confirm('APAKAH ANDA YAKIN INGIN RESTORE DATABASE? Tindakan ini akan menimpa data saat ini!')">
                            <i class="fas fa-database me-1"></i> Jalankan Restore Database
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Log Backup Activity -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-shield-halved text-info me-2"></i> Log Audit Aktivitas Backup & Restore</h5>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu Audit</th>
                            <th>User Pemproses</th>
                            <th>Aksi</th>
                            <th>Detail Catatan Audit</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($audit_logs)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada log audit backup</td></tr>
                        <?php else: ?>
                            <?php foreach ($audit_logs as $log): ?>
                                <tr>
                                    <td><small class="text-muted"><?= esc($log['created_at']) ?></small></td>
                                    <td><span class="fw-bold text-dark"><?= esc($log['user_name'] ?? 'System Admin') ?></span></td>
                                    <td><span class="badge bg-secondary"><?= esc($log['action']) ?></span></td>
                                    <td><code class="text-dark"><?= esc($log['description']) ?></code></td>
                                    <td><small class="text-muted"><?= esc($log['ip_address'] ?? '127.0.0.1') ?></small></td>
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
