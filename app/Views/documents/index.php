<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <!-- Top Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-file-contract text-warning me-2 fs-3"></i> DIGITAL DOCUMENT INTELLIGENCE
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">ENTERPRISE V23</span>
            </h3>
            <p class="text-muted small mb-0">Manajemen Dokumen Resmi (Berita Acara, WO, Laporan) dengan Tanda Tangan Digital & Verifikasi QR SHA256</p>
        </div>
        <div>
            <a href="<?= site_url('documents/create') ?>" class="btn btn-primary btn-sm rounded-pill font-weight-bold shadow-sm">
                <i class="fas fa-plus-circle me-1"></i> Terbitkan Dokumen Baru
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= site_url('documents') ?>" class="row g-2 align-items-center">
                <div class="col-md-4 col-12">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari No. Dokumen / Judul / Penerbit..." value="<?= esc($filters['search'] ?? '') ?>">
                </div>
                <div class="col-md-3 col-6">
                    <select name="jenis_dokumen" class="form-select form-select-sm">
                        <option value="">-- Semua Jenis Dokumen --</option>
                        <option value="Berita Acara" <?= ($filters['jenis_dokumen'] ?? '') === 'Berita Acara' ? 'selected' : '' ?>>Berita Acara</option>
                        <option value="Work Order" <?= ($filters['jenis_dokumen'] ?? '') === 'Work Order' ? 'selected' : '' ?>>Work Order</option>
                        <option value="Surat Tugas" <?= ($filters['jenis_dokumen'] ?? '') === 'Surat Tugas' ? 'selected' : '' ?>>Surat Tugas</option>
                        <option value="Laporan Bulanan" <?= ($filters['jenis_dokumen'] ?? '') === 'Laporan Bulanan' ? 'selected' : '' ?>>Laporan Bulanan</option>
                    </select>
                </div>
                <div class="col-md-3 col-6">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- Status Approval --</option>
                        <option value="DRAFT" <?= ($filters['status'] ?? '') === 'DRAFT' ? 'selected' : '' ?>>DRAFT</option>
                        <option value="REVIEW" <?= ($filters['status'] ?? '') === 'REVIEW' ? 'selected' : '' ?>>REVIEW</option>
                        <option value="APPROVED" <?= ($filters['status'] ?? '') === 'APPROVED' ? 'selected' : '' ?>>APPROVED (DISETUJUI)</option>
                        <option value="REJECTED" <?= ($filters['status'] ?? '') === 'REJECTED' ? 'selected' : '' ?>>REJECTED</option>
                    </select>
                </div>
                <div class="col-md-2 col-12 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold"><i class="fas fa-filter me-1"></i> Filter</button>
                    <a href="<?= site_url('documents') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-rotate-left"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Nomor Dokumen</th>
                            <th>Judul Dokumen</th>
                            <th>Jenis Dokumen</th>
                            <th>Penerbit</th>
                            <th>SHA256 Checksum</th>
                            <th>Status Approval</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada dokumen resmi terdaftar.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($documents as $d): ?>
                                <tr>
                                    <td class="ps-3">
                                        <a href="<?= site_url('documents/detail/' . $d['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                            <?= esc($d['nomor_dokumen']) ?>
                                        </a>
                                        <small class="text-muted d-block"><?= indo_date($d['created_at']) ?></small>
                                    </td>
                                    <td><span class="fw-bold text-dark d-block"><?= esc($d['judul_dokumen']) ?></span></td>
                                    <td><span class="badge bg-secondary"><?= esc($d['jenis_dokumen']) ?></span></td>
                                    <td><span class="fw-bold text-secondary"><?= esc($d['created_by']) ?></span></td>
                                    <td><small class="font-monospace text-muted text-truncate d-inline-block" style="max-width: 120px;" title="<?= esc($d['checksum']) ?>"><?= esc($d['checksum']) ?></small></td>
                                    <td>
                                        <?php if ($d['status'] === 'APPROVED'): ?>
                                            <span class="badge bg-success"><i class="fas fa-circle-check me-1"></i> APPROVED</span>
                                        <?php elseif ($d['status'] === 'REVIEW'): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half me-1"></i> REVIEW</span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-white"><?= esc($d['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center pe-3">
                                        <a href="<?= site_url('documents/detail/' . $d['id']) ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2">
                                            <i class="fas fa-eye me-1"></i> Kelola & TTD
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
<?= $this->endSection() ?>
