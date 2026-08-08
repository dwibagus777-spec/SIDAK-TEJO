<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Kelola Master Gardu Induk (GI)<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Master Network: Gardu Induk (GI)<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item active">Gardu Induk</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center rounded-top-4 border-bottom">
                <div>
                    <h5 class="card-title mb-0 font-weight-bold text-dark"><i class="fas fa-charging-station text-primary me-2"></i> Master Data Gardu Induk (GI)</h5>
                    <small class="text-muted">Kelola pasokan utama dan node sumber tegangan 150 kV / 20 kV PLN</small>
                </div>
                <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalAddGi">
                    <i class="fas fa-plus me-1"></i> Tambah Gardu Induk
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (session()->getFlashdata('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show m-3 rounded-3" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?= session()->getFlashdata('success') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show m-3 rounded-3" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?= session()->getFlashdata('error') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted uppercase small">
                            <tr>
                                <th class="ps-4">No</th>
                                <th>Kode GI</th>
                                <th>Nama Gardu Induk</th>
                                <th>Lokasi / Alamat</th>
                                <th>Koordinat GIS</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($garduInduk)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-charging-station fa-3x mb-3 d-block text-secondary opacity-50"></i>
                                        Belum ada data Gardu Induk. Klik <strong>Tambah Gardu Induk</strong> untuk menambahkan baru.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($garduInduk as $idx => $gi): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-muted"><?= $idx + 1 ?></td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill font-monospace fw-bold">
                                                <?= esc($gi['kode_gi']) ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-dark"><?= esc($gi['nama_gi']) ?></td>
                                        <td><?= esc($gi['lokasi'] ?: '-') ?></td>
                                        <td class="small text-muted">
                                            <?php if (!empty($gi['latitude']) && !empty($gi['longitude'])): ?>
                                                <a href="https://maps.google.com/?q=<?= esc($gi['latitude']) ?>,<?= esc($gi['longitude']) ?>" target="_blank" class="text-decoration-none">
                                                    <i class="fas fa-map-marker-alt text-danger me-1"></i> <?= esc($gi['latitude']) ?>, <?= esc($gi['longitude']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (in_array(strtoupper($gi['status']), ['ACTIVE', 'AKTIF'])): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1 rounded-pill">Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-circle me-1" 
                                                    data-bs-toggle="modal" data-bs-target="#modalEditGi<?= $gi['id'] ?>" title="Edit GI">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form action="<?= site_url('master-gi/delete/' . $gi['id']) ?>" method="post" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Gardu Induk ini?')">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="Hapus GI">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- Modal Edit GI -->
                                    <div class="modal fade" id="modalEditGi<?= $gi['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow">
                                                <div class="modal-header bg-primary text-white rounded-top-4">
                                                    <h5 class="modal-title font-weight-bold"><i class="fas fa-edit me-2"></i> Edit Gardu Induk</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="<?= site_url('master-gi/update/' . $gi['id']) ?>" method="post">
                                                    <?= csrf_field() ?>
                                                    <div class="modal-body p-4">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Kode GI</label>
                                                            <input type="text" class="form-control" value="<?= esc($gi['kode_gi']) ?>" disabled>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Nama Gardu Induk <span class="text-danger">*</span></label>
                                                            <input type="text" name="nama_gi" class="form-control" value="<?= esc($gi['nama_gi']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Lokasi / Alamat</label>
                                                            <input type="text" name="lokasi" class="form-control" value="<?= esc($gi['lokasi']) ?>">
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-6 mb-3">
                                                                <label class="form-label fw-bold">Latitude</label>
                                                                <input type="text" name="latitude" class="form-control" value="<?= esc($gi['latitude']) ?>">
                                                            </div>
                                                            <div class="col-6 mb-3">
                                                                <label class="form-label fw-bold">Longitude</label>
                                                                <input type="text" name="longitude" class="form-control" value="<?= esc($gi['longitude']) ?>">
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Status Operational</label>
                                                            <select name="status" class="form-select">
                                                                <option value="ACTIVE" <?= in_array(strtoupper($gi['status']), ['ACTIVE', 'AKTIF']) ? 'selected' : '' ?>>ACTIVE (Aktif)</option>
                                                                <option value="INACTIVE" <?= !in_array(strtoupper($gi['status']), ['ACTIVE', 'AKTIF']) ? 'selected' : '' ?>>INACTIVE (Nonaktif)</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light rounded-bottom-4">
                                                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Perubahan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add GI -->
<div class="modal fade" id="modalAddGi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-plus-circle me-2"></i> Tambah Gardu Induk Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= site_url('master-gi/store') ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-body p-4">
                    <div class="alert alert-info rounded-3 small mb-3 border-0">
                        <i class="fas fa-magic me-1"></i> Kode GI (misal: <code>GI-BDR-001</code>) akan secara otomatis digenerasi oleh sistem berdasarkan Nama Gardu Induk yang diinputkan.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Gardu Induk <span class="text-danger">*</span></label>
                        <input type="text" name="nama_gi" class="form-control" placeholder="Misal: GI BUDURAN" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Lokasi / Alamat</label>
                        <input type="text" name="lokasi" class="form-control" placeholder="Misal: Buduran, Sidoarjo">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold">Latitude</label>
                            <input type="text" name="latitude" class="form-control" placeholder="-7.43981">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold">Longitude</label>
                            <input type="text" name="longitude" class="form-control" placeholder="112.72145">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Status Operational</label>
                        <select name="status" class="form-select">
                            <option value="ACTIVE">ACTIVE (Aktif)</option>
                            <option value="INACTIVE">INACTIVE (Nonaktif)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan GI Baru</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
