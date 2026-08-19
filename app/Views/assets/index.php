<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <!-- Top Header & Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-1 text-primary d-flex align-items-center" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-boxes-stacked text-warning me-2"></i> MASTER ASSET MANAGEMENT
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">ENTERPRISE V17</span>
            </h3>
            <p class="text-muted small mb-0">Manajemen Aset PLN Jaringan Distribusi 20KV Terintegrasi QR Code, Barcode & GPS</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <?php 
            $queryString = !empty($_GET) ? '?' . http_build_query($_GET) : '';
            ?>
            <?php if (in_array(session()->get('user_role'), ['administrator', 'admin_ulp', 'inspeksi'])): ?>
                <a href="<?= site_url('assets/create') ?>" class="btn btn-primary btn-sm font-weight-bold rounded-pill shadow-sm">
                    <i class="fas fa-plus-circle me-1"></i> Tambah Asset Baru
                </a>
                <a href="<?= site_url('master-assets/import') ?>" class="btn btn-success btn-sm font-weight-bold rounded-pill shadow-sm">
                    <i class="fas fa-file-import me-1"></i> Import Excel
                </a>
                <button type="button" class="btn btn-outline-success btn-sm font-weight-bold rounded-pill" data-bs-toggle="modal" data-bs-target="#modalDownloadTemplate">
                    <i class="fas fa-download me-1"></i> Download Template
                </button>
            <?php endif; ?>
            <a href="<?= site_url('master-assets/export-excel' . $queryString) ?>" class="btn btn-outline-primary btn-sm font-weight-bold rounded-pill shadow-sm">
                <i class="fas fa-file-excel me-1"></i> Export Excel
            </a>
            <a href="<?= site_url('master-assets/export-csv' . $queryString) ?>" class="btn btn-outline-secondary btn-sm font-weight-bold rounded-pill shadow-sm">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </a>
            <a href="<?= site_url('master-assets/export-pdf' . $queryString) ?>" class="btn btn-outline-danger btn-sm font-weight-bold rounded-pill shadow-sm" target="_blank">
                <i class="fas fa-file-pdf me-1"></i> Export PDF
            </a>
        </div>
    </div>

    <!-- KPI Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card border rounded-4 overflow-hidden position-relative shadow-sm" style="background-color: #ffffff !important; border-color: #e2e8f0 !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="small mb-1 font-weight-bold text-uppercase" style="color: #64748b !important; font-size: 0.75rem; letter-spacing: 0.05em;">Total Asset</p>
                            <h2 class="fw-bold mb-0 font-outfit" style="color: #0f172a !important; font-size: 1.75rem;"><?= number_format($stats['total']) ?></h2>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background-color: #eff6ff !important; color: #2563eb !important; width: 48px; height: 48px;">
                            <i class="fas fa-cubes fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card border rounded-4 overflow-hidden position-relative shadow-sm" style="background-color: #ffffff !important; border-color: #e2e8f0 !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="small mb-1 font-weight-bold text-uppercase" style="color: #166534 !important; font-size: 0.75rem; letter-spacing: 0.05em;">Status Normal</p>
                            <h2 class="fw-bold mb-0 font-outfit" style="color: #0f172a !important; font-size: 1.75rem;"><?= number_format($stats['normal']) ?></h2>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background-color: #f0fdf4 !important; color: #16a34a !important; width: 48px; height: 48px;">
                            <i class="fas fa-check-circle fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card border rounded-4 overflow-hidden position-relative shadow-sm" style="background-color: #ffffff !important; border-color: #e2e8f0 !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="small mb-1 font-weight-bold text-uppercase" style="color: #854d0e !important; font-size: 0.75rem; letter-spacing: 0.05em;">Bermasalah</p>
                            <h2 class="fw-bold mb-0 font-outfit" style="color: #0f172a !important; font-size: 1.75rem;"><?= number_format($stats['bermasalah']) ?></h2>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background-color: #fefce8 !important; color: #ca8a04 !important; width: 48px; height: 48px;">
                            <i class="fas fa-triangle-exclamation fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card border rounded-4 overflow-hidden position-relative shadow-sm" style="background-color: #ffffff !important; border-color: #e2e8f0 !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="small mb-1 font-weight-bold text-uppercase" style="color: #991b1b !important; font-size: 0.75rem; letter-spacing: 0.05em;">Critical</p>
                            <h2 class="fw-bold mb-0 font-outfit" style="color: #0f172a !important; font-size: 1.75rem;"><?= number_format($stats['critical']) ?></h2>
                        </div>
                        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background-color: #fef2f2 !important; color: #dc2626 !important; width: 48px; height: 48px;">
                            <i class="fas fa-radiation fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Selection Confirmation Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border-left: 5px solid #2563eb !important;">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold text-primary mb-1 d-flex align-items-center">
                        <i class="fas fa-sliders text-warning me-2 fs-4"></i> PANEL KONFIRMASI SELEKSI KELUARKAN DATA ASET
                    </h5>
                    <p class="text-muted small mb-0">Pilih kriteria ULP, Penyulang, Jenis Aset, atau Status terlebih dahulu, lalu klik tombol <strong>Konfirmasi & Tampilkan Data</strong> untuk memuat daftar aset secara aman dan ringan.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= site_url('master-assets?show_all=1') ?>" class="btn btn-outline-primary btn-sm rounded-pill font-weight-bold shadow-sm">
                        <i class="fas fa-eye me-1"></i> Tampilkan Semua Aset (<?= number_format($stats['total'] ?? 0) ?>)
                    </a>
                    <a href="<?= site_url('master-assets') ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
                        <i class="fas fa-rotate-left me-1"></i> Reset
                    </a>
                </div>
            </div>

            <form action="<?= site_url('master-assets') ?>" method="GET" class="row g-2">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-muted mb-1">1. Unit (ULP)</label>
                    <select name="ulp_id" class="form-select form-select-sm font-weight-bold" id="filter_ulp_id">
                        <option value="">-- Semua ULP --</option>
                        <?php foreach ($ulps as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($filters['ulp_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-muted mb-1">2. Penyulang (Feeder)</label>
                    <select name="penyulang_id" class="form-select form-select-sm font-weight-bold" id="filter_penyulang_id">
                        <option value="">-- Semua Penyulang --</option>
                        <?php foreach ($penyulangs as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($filters['penyulang_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= esc($p['nama_penyulang']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">3. Jenis Aset</label>
                    <select name="jenis_asset" class="form-select form-select-sm">
                        <option value="">-- Semua Jenis --</option>
                        <?php 
                        $jenisOptions = [
                            'JTM'           => '🔌 JTM & Tiang',
                            'Gardu'         => '🏭 Gardu Distribusi',
                            'Trafo'         => '⚡ Trafo',
                            'Kubikel'       => '📦 Kubikel',
                            'LBS'           => '🔀 LBS / LBSM',
                            'Recloser'      => '🔄 Recloser',
                            'Sectionalizer' => '✂️ Sectionalizer',
                            'JTR'           => '🏠 JTR',
                        ];
                        foreach ($jenisOptions as $val => $label): ?>
                            <option value="<?= $val ?>" <?= strcasecmp(($filters['jenis_asset'] ?? ''), $val) === 0 ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">4. Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- Semua Status --</option>
                        <option value="NORMAL" <?= ($filters['status'] ?? '') === 'NORMAL' ? 'selected' : '' ?>>NORMAL</option>
                        <option value="BERMASALAH" <?= ($filters['status'] ?? '') === 'BERMASALAH' ? 'selected' : '' ?>>BERMASALAH</option>
                        <option value="CRITICAL" <?= ($filters['status'] ?? '') === 'CRITICAL' ? 'selected' : '' ?>>CRITICAL</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">5. Cari Kode / Nama</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari Kode / Nama..." value="<?= esc($filters['search'] ?? '') ?>">
                </div>

                <div class="col-12 mt-3 text-end">
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-4 rounded-pill shadow-sm">
                        <i class="fas fa-check-circle me-1"></i> KONFIRMASI & TAMPILKAN DATA ASET
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead class="bg-light text-secondary">
                        <tr>
                            <th class="ps-3 text-center" style="width: 40px;">
                                <input type="checkbox" id="check-all-assets" class="form-check-input">
                            </th>
                            <th class="py-3">Kode Asset</th>
                            <th>Nama Asset & SN</th>
                            <th>Jenis</th>
                            <th>ULP & Lokasi</th>
                            <th>Kapasitas / Merk</th>
                            <th>Status</th>
                            <th class="text-center pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assets)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <?php if (empty($hasFilter)): ?>
                                        <div class="py-3">
                                            <div class="rounded-circle bg-light d-inline-flex p-3 mb-3 text-primary shadow-sm">
                                                <i class="fas fa-sliders-h fs-2"></i>
                                            </div>
                                            <h5 class="fw-bold text-dark mb-2">Silakan Pilih Filter untuk Menampilkan Aset</h5>
                                            <p class="text-muted small mb-4 style="max-width: 500px; margin: 0 auto;">
                                                Pilih ULP, Penyulang, atau Jenis Aset di atas untuk memuat daftar aset secara cepat & ringan, atau klik pintasan di bawah ini.
                                            </p>
                                            <div class="d-flex justify-content-center flex-wrap gap-2">
                                                <a href="?jenis_asset=JTM" class="btn btn-outline-primary btn-sm rounded-pill font-weight-bold px-3">
                                                    <i class="fas fa-bolt me-1"></i> JTM & Tiang
                                                </a>
                                                <a href="?jenis_asset=GARDU" class="btn btn-outline-success btn-sm rounded-pill font-weight-bold px-3">
                                                    <i class="fas fa-building me-1"></i> Gardu Distribusi
                                                </a>
                                                <a href="?jenis_asset=TRAFO" class="btn btn-outline-warning btn-sm rounded-pill font-weight-bold px-3">
                                                    <i class="fas fa-plug me-1"></i> Trafo
                                                </a>
                                                <a href="?show_all=1" class="btn btn-primary btn-sm rounded-pill font-weight-bold px-3 shadow-sm">
                                                    <i class="fas fa-eye me-1"></i> Tampilkan Semua Aset (<?= number_format($stats['total'] ?? 0) ?>)
                                                </a>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="py-3 text-muted">
                                            <i class="fas fa-inbox fs-3 d-block mb-2 text-secondary"></i>
                                            Tidak ada data aset PLN yang cocok dengan kriteria filter Anda.
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assets as $a): ?>
                                <tr>
                                    <td class="ps-3 text-center">
                                        <input type="checkbox" class="check-asset-item form-check-input" value="<?= $a['id'] ?>">
                                    </td>
                                    <td>
                                        <a href="<?= site_url('assets/detail/' . $a['id']) ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                            <?= esc($a['kode_asset']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark d-block"><?= esc($a['nama_asset']) ?></span>
                                        <small class="text-muted">SN: <?= esc($a['nomor_seri'] ?: '-') ?></small>
                                    </td>
                                     <td>
                                         <span class="badge bg-info-subtle text-info border border-info-subtle font-weight-bold">
                                             <?= esc(!empty($a['jenis_asset']) ? $a['jenis_asset'] : (str_contains($a['kode_asset'], 'GRD') ? 'Gardu' : (str_contains($a['kode_asset'], 'TRF') ? 'Trafo' : (str_contains($a['kode_asset'], 'KUB') ? 'Kubikel' : 'Asset')))) ?>
                                         </span>
                                     </td>
                                    <td>
                                        <span class="fw-bold text-secondary d-block"><?= esc($a['nama_ulp'] ?: '-') ?></span>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 200px;" title="<?= esc($a['lokasi']) ?>"><?= esc($a['lokasi']) ?></small>
                                    </td>
                                    <td>
                                        <small class="d-block text-dark fw-bold"><?= esc($a['merk'] ?: '-') ?></small>
                                        <small class="text-muted"><?= esc($a['kapasitas'] ?: '-') ?></small>
                                    </td>
                                    <td>
                                        <?php if (strtoupper($a['status']) === 'CRITICAL'): ?>
                                            <span class="badge bg-danger animate__animated animate__pulse animate__infinite"><i class="fas fa-radiation me-1"></i> CRITICAL</span>
                                        <?php elseif (strtoupper($a['status']) === 'BERMASALAH'): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-triangle-exclamation me-1"></i> BERMASALAH</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> NORMAL</span>
                                        <?php endif; ?>
                                    </td>
                                     <td class="text-center pe-3">
                                         <div class="btn-group btn-group-sm" role="group">
                                             <a href="<?= site_url('assets/detail/' . $a['id']) ?>" class="btn btn-xs btn-primary font-weight-bold shadow-sm" title="Digital Twin Hub">
                                                 <i class="fas fa-microchip me-1"></i> Digital Twin
                                             </a>
                                             
                                             <?php if (check_role(['administrator', 'admin_ulp', 'inspeksi'])): ?>
                                                 <a href="<?= site_url('assets/edit/' . $a['id']) ?>" class="btn btn-xs btn-warning text-dark font-weight-bold shadow-sm" title="Edit Master Asset">
                                                     <i class="fas fa-edit me-1"></i> Edit
                                                 </a>
                                                 
                                                 <?php if (strtoupper($a['status']) === 'DIHAPUS' || !empty($a['deleted_at'])): ?>
                                                     <form action="<?= site_url('assets/restore/' . $a['id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Restore aset ini kembali?');">
                                                         <?= csrf_field() ?>
                                                         <button type="submit" class="btn btn-xs btn-success text-white font-weight-bold shadow-sm" title="Restore Asset">
                                                             <i class="fas fa-undo me-1"></i> Restore
                                                         </button>
                                                     </form>
                                                 <?php else: ?>
                                                     <button type="button" class="btn btn-xs btn-outline-danger font-weight-bold shadow-sm btn-delete-asset" 
                                                             data-id="<?= $a['id'] ?>" 
                                                             data-kode="<?= esc($a['kode_asset']) ?>" 
                                                             data-nama="<?= esc($a['nama_asset']) ?>" 
                                                             data-sn="<?= esc($a['nomor_seri'] ?: '-') ?>"
                                                             title="Soft Delete Asset">
                                                         <i class="fas fa-trash me-1"></i> Hapus
                                                     </button>
                                                 <?php endif; ?>
                                             <?php endif; ?>
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

     <!-- Pagination & Summary Footer -->
    <?php if (!empty($pagination) && $pagination['last_page'] > 1): ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 bg-light rounded-4 border mt-3 mb-4">
            <div class="small text-muted font-monospace">
                Menampilkan Halaman <strong><?= $pagination['page'] ?></strong> dari <strong><?= $pagination['last_page'] ?></strong> (Total: <strong><?= number_format($pagination['total']) ?></strong> Aset)
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $queryParams = $_GET;
                    $currentPage = $pagination['page'];
                    $lastPage    = $pagination['last_page'];
                    
                    $prevParams = array_merge($queryParams, ['page' => max(1, $currentPage - 1)]);
                    $nextParams = array_merge($queryParams, ['page' => min($lastPage, $currentPage + 1)]);
                    ?>
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link rounded-pill px-3" href="?<?= http_build_query($prevParams) ?>">&laquo; Prev</a>
                    </li>
                    <li class="page-item disabled">
                        <span class="page-link border-0 text-dark fw-bold"><?= $currentPage ?> / <?= $lastPage ?></span>
                    </li>
                    <li class="page-item <?= $currentPage >= $lastPage ? 'disabled' : '' ?>">
                        <a class="page-link rounded-pill px-3" href="?<?= http_build_query($nextParams) ?>">Next &raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>

</div>

<!-- Modal Bulk Delete -->
<div class="modal fade" id="modalBulkDelete" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-danger text-white border-0 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="fas fa-trash-can me-2"></i> Hapus Massal / Mass Soft Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="<?= site_url('master-assets/bulk-delete') ?>" method="POST" id="formBulkDelete">
                    <?= csrf_field() ?>
                    <input type="hidden" name="selected_ids" id="selected_ids">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih Mode Hapus Massal:</label>
                        <select name="delete_type" id="delete_type_select" class="form-select font-weight-bold" onchange="toggleBulkDeleteMode(this.value)">
                            <option value="feeder">1. Hapus Seluruh Aset Satu Penyulang (Feeder Rollback)</option>
                            <option value="selected">2. Hapus Aset Terpilih (Checkbox)</option>
                        </select>
                    </div>

                    <!-- Feeder Mode Section -->
                    <div id="section_feeder_delete" class="p-3 bg-light rounded-3 border mb-3">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih Penyulang:</label>
                            <select name="penyulang_id" class="form-select">
                                <option value="">-- Pilih Penyulang --</option>
                                <?php foreach ($penyulangs as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= ($filters['penyulang_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= esc($p['nama_penyulang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-danger">Ketik kata "HAPUS" untuk mengonfirmasi hapus massal penyulang:</label>
                            <input type="text" name="confirm_text" class="form-control text-uppercase font-monospace fw-bold" placeholder="Ketik HAPUS di sini...">
                        </div>
                    </div>

                    <div class="alert alert-warning small mb-0 rounded-3">
                        <i class="fas fa-shield-alt text-warning me-1"></i> Data aset akan di-soft delete secara aman (dapat di-restore kembali) dan tercatat dalam log audit.
                    </div>

                    <div class="modal-footer bg-light border-0 rounded-bottom-4 mt-3 px-0 pb-0">
                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger font-weight-bold px-4 rounded-pill"><i class="fas fa-trash-can me-1"></i> Jalankan Hapus Massal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Soft Delete Asset -->
<div class="modal fade" id="modalSoftDeleteAsset" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-danger text-white border-0 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="fas fa-exclamation-triangle me-2"></i> Konfirmasi Soft Delete Asset</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formSoftDeleteAsset" action="" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body p-4">
                    <div class="alert alert-light border rounded-3 p-3 mb-3">
                        <div class="row g-2 small">
                            <div class="col-4 text-muted">Kode Asset:</div>
                            <div class="col-8 font-monospace fw-bold text-primary" id="delKodeAsset">-</div>
                            <div class="col-4 text-muted">Nama Asset:</div>
                            <div class="col-8 fw-bold" id="delNamaAsset">-</div>
                            <div class="col-4 text-muted">Nomor Seri:</div>
                            <div class="col-8 font-monospace" id="delSnAsset">-</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-danger">Alasan Penghapusan (Wajib) <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Contoh: Aset di-scrap karena rusak berat / diganti dengan unit baru..." required></textarea>
                    </div>
                    <small class="text-muted d-block"><i class="fas fa-shield-alt text-success me-1"></i> Data aset tidak akan dihapus permanen dari database dan histori audit akan tersimpan.</small>
                </div>
                <div class="modal-footer bg-light border-0 rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-3 px-3" data-bs-dismiss="modal" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger font-weight-bold px-4 rounded-3"><i class="fas fa-trash me-1"></i> Hapus Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Download Template -->
<?= $this->include('assets/modal_download_template') ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function toggleBulkDeleteMode(val) {
    var feederSec = document.getElementById('section_feeder_delete');
    if (feederSec) {
        feederSec.style.display = (val === 'feeder') ? 'block' : 'none';
    }
}

(function() {
    function initDeleteModal() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initDeleteModal, 50);
            return;
        }
        var $ = jQuery;
        $(function() {
            $('#filter_ulp_id').on('change', function() {
                var ulpId = $(this).val();
                var $penyulangSelect = $('#filter_penyulang_id');
                $penyulangSelect.html('<option value="">-- Loading Penyulang... --</option>');

                var apiUrl = '<?= site_url("api/network/penyulang") ?>' + (ulpId ? '?ulp_id=' + ulpId : '');
                $.ajax({
                    url: apiUrl,
                    type: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        $penyulangSelect.html('<option value="">-- Semua Penyulang --</option>');
                        if (res && res.data && res.data.length > 0) {
                            $.each(res.data, function(idx, item) {
                                $penyulangSelect.append('<option value="' + item.id + '">' + item.nama_penyulang + '</option>');
                            });
                        }
                    },
                    error: function() {
                        $penyulangSelect.html('<option value="">-- Semua Penyulang --</option>');
                    }
                });
            });

            $('#check-all-assets').on('change', function() {
                var checked = this.checked;
                $('.check-asset-item').prop('checked', checked);
            });

            $('#formBulkDelete').on('submit', function(e) {
                var deleteType = $('#delete_type_select').val();
                if (deleteType === 'selected') {
                    var selectedIds = [];
                    $('.check-asset-item:checked').each(function() {
                        selectedIds.push($(this).val());
                    });
                    if (selectedIds.length === 0) {
                        e.preventDefault();
                        alert('Silakan pilih minimal 1 aset pada tabel (centang checkbox) terlebih dahulu!');
                        return false;
                    }
                    $('#selected_ids').val(selectedIds.join(','));
                }
            });

            $('.btn-delete-asset').on('click', function() {
                var id = $(this).data('id');
                var kode = $(this).data('kode');
                var nama = $(this).data('nama');
                var sn = $(this).data('sn');

                $('#delKodeAsset').text(kode);
                $('#delNamaAsset').text(nama);
                $('#delSnAsset').text(sn);
                $('#formSoftDeleteAsset').attr('action', '<?= site_url("assets/soft-delete/") ?>' + id);

                var modalEl = document.getElementById('modalSoftDeleteAsset');
                if (modalEl) {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                        modal.show();
                    } else if (typeof $ !== 'undefined' && $.fn.modal) {
                        $(modalEl).modal('show');
                    }
                }
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDeleteModal);
    } else {
        initDeleteModal();
    }
})();
</script>
<?= $this->endSection() ?>
