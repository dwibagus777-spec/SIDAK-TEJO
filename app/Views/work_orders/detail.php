<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap: 8px;">
        <div>
            <span class="badge bg-secondary font-monospace" style="font-size: 11px;"><?= esc($wo['nomor_wo']) ?></span>
            <h3 class="fw-bold mb-0 text-dark font-weight-bold" style="font-family: 'Outfit', sans-serif;">
                <?= esc($wo['judul_wo']) ?>
            </h3>
            <p class="text-muted small mb-0"><i class="fas fa-cubes me-1 text-primary"></i> Asset: <?= esc($wo['nama_asset'] ?: 'General Asset') ?> | ULP: <?= esc($wo['nama_ulp'] ?: '-') ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= site_url('work-orders') ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
            <button type="button" class="btn btn-primary btn-sm rounded-pill font-weight-bold" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                <i class="fas fa-edit me-1"></i> Update Status & Foto EV
            </button>
        </div>
    </div>

    <!-- Progress Percentage Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold text-dark small"><i class="fas fa-list-check text-primary me-1"></i> Progress Checklist Pekerjaan</span>
                <span class="badge bg-primary fs-6"><?= $wo['checklist_percentage'] ?>% Selesai</span>
            </div>
            <div class="progress" style="height: 10px; border-radius: 5px;">
                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?= $wo['checklist_percentage'] ?>%;"></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Checklist & Material Section -->
        <div class="col-lg-8 col-12">
            <!-- Checklist Items -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-tasks text-success me-2"></i> Standard Operating Checklist</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($wo['checklists'])): ?>
                        <p class="text-muted small mb-0">Belum ada checklist pekerjaan.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($wo['checklists'] as $chk): ?>
                                <div class="list-group-item d-flex align-items-center justify-content-between py-2 px-0 border-bottom">
                                    <div class="form-check">
                                        <input class="form-check-input chk-toggle-item" type="checkbox" data-id="<?= $chk['id'] ?>" id="chk-<?= $chk['id'] ?>" <?= !empty($chk['is_completed']) ? 'checked' : '' ?> style="cursor: pointer; transform: scale(1.2);">
                                        <label class="form-check-label ms-2 <?= !empty($chk['is_completed']) ? 'text-decoration-line-through text-muted' : 'fw-bold text-dark' ?>" for="chk-<?= $chk['id'] ?>" style="cursor: pointer;">
                                            <?= esc($chk['item_text']) ?>
                                        </label>
                                    </div>
                                    <?php if (!empty($chk['is_completed'])): ?>
                                        <small class="text-success fw-bold" style="font-size: 11px;">
                                            <i class="fas fa-check-circle me-1"></i> <?= esc($chk['completed_by']) ?> (<?= date('H:i', strtotime($chk['completed_at'])) ?>)
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Material Usage Section -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-boxes-packing text-warning me-2"></i> Penggunaan Material Pekerjaan</h5>
                    <button type="button" class="btn btn-xs btn-outline-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                        <i class="fas fa-plus me-1"></i> Tambah Material
                    </button>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($wo['materials'])): ?>
                        <div class="p-4 text-center text-muted small">Belum ada rincian material terdaftar.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 12px;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Nama Material</th>
                                        <th>Jumlah</th>
                                        <th>Satuan</th>
                                        <th>Status Penggunaan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($wo['materials'] as $m): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-dark"><?= esc($m['nama_material']) ?></td>
                                            <td class="fw-bold text-primary"><?= number_format($m['jumlah'], 2) ?></td>
                                            <td><?= esc($m['satuan']) ?></td>
                                            <td><span class="badge bg-success"><?= esc($m['status_penggunaan']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Timeline History Log -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-history text-secondary me-2"></i> Timeline & EV Execution Photos</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($wo['histories'])): ?>
                        <p class="text-muted small mb-0">Belum ada riwayat aktivitas.</p>
                    <?php else: ?>
                        <div class="timeline" style="border-left: 2px solid #cbd5e1; padding-left: 16px;">
                            <?php foreach ($wo['histories'] as $h): ?>
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="badge bg-secondary rounded-pill"><?= esc($h['user_name']) ?></span>
                                        <small class="text-muted fw-bold"><?= indo_datetime($h['created_at']) ?></small>
                                    </div>
                                    <div class="fw-bold text-dark mt-1" style="font-size: 13px;"><?= esc($h['aktivitas']) ?></div>
                                    <?php if (!empty($h['catatan'])): ?>
                                        <p class="small text-muted mb-2"><?= esc($h['catatan']) ?></p>
                                    <?php endif; ?>

                                    <!-- Photos EV Grid -->
                                    <div class="d-flex gap-2 flex-wrap mt-2">
                                        <?php if (!empty($h['foto_sebelum'])): ?>
                                            <div class="text-center">
                                                <a href="<?= get_photo_url($h['foto_sebelum']) ?>" target="_blank">
                                                    <img src="<?= get_photo_url($h['foto_sebelum']) ?>" alt="Sebelum" class="img-thumbnail rounded-3" style="width: 90px; height: 90px; object-fit: cover;">
                                                </a>
                                                <span class="badge bg-danger d-block mt-1" style="font-size: 9px;">Foto Sebelum</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($h['foto_proses'])): ?>
                                            <div class="text-center">
                                                <a href="<?= get_photo_url($h['foto_proses']) ?>" target="_blank">
                                                    <img src="<?= get_photo_url($h['foto_proses']) ?>" alt="Proses" class="img-thumbnail rounded-3" style="width: 90px; height: 90px; object-fit: cover;">
                                                </a>
                                                <span class="badge bg-warning text-dark d-block mt-1" style="font-size: 9px;">Foto Proses</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($h['foto_sesudah'])): ?>
                                            <div class="text-center">
                                                <a href="<?= get_photo_url($h['foto_sesudah']) ?>" target="_blank">
                                                    <img src="<?= get_photo_url($h['foto_sesudah']) ?>" alt="Sesudah" class="img-thumbnail rounded-3" style="width: 90px; height: 90px; object-fit: cover;">
                                                </a>
                                                <span class="badge bg-success d-block mt-1" style="font-size: 9px;">Foto Sesudah</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Side WO Information -->
        <div class="col-lg-4 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-info-circle text-primary me-2"></i> Informasi WO</h5>
                <table class="table table-sm table-borderless" style="font-size: 13px;">
                    <tr><th style="width: 120px;">Assigned To</th><td>: <strong class="text-dark"><?= esc($wo['assigned_to'] ?: 'Unassigned') ?></strong></td></tr>
                    <tr><th>Tim / Pelaksana</th><td>: <?= esc($wo['assigned_team'] ?: esc($wo['pelaksana'])) ?></td></tr>
                    <tr><th>Prioritas</th><td>: 
                        <?php if (strtoupper($wo['prioritas']) === 'EMERGENCY'): ?>
                            <span class="badge bg-danger animate__animated animate__pulse animate__infinite">EMERGENCY</span>
                        <?php else: ?>
                            <span class="badge bg-info text-white"><?= esc($wo['prioritas']) ?></span>
                        <?php endif; ?>
                    </td></tr>
                    <tr><th>Status WO</th><td>: 
                        <span class="badge bg-success"><?= esc($wo['status']) ?></span>
                    </td></tr>
                    <tr><th>Target Selesai</th><td>: <?= indo_datetime($wo['target_selesai']) ?></td></tr>
                    <tr><th>Diterbitkan Oleh</th><td>: <?= esc($wo['created_by']) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Update Status & Upload Foto EV -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <form action="<?= site_url('work-orders/update-status/' . $wo['id']) ?>" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit text-primary me-2"></i> Update Status WO</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Status Pekerjaan Baru</label>
                        <select name="status" class="form-select" required>
                            <option value="OPEN" <?= $wo['status'] === 'OPEN' ? 'selected' : '' ?>>OPEN</option>
                            <option value="ASSIGNED" <?= $wo['status'] === 'ASSIGNED' ? 'selected' : '' ?>>ASSIGNED</option>
                            <option value="PROGRESS" <?= $wo['status'] === 'PROGRESS' ? 'selected' : '' ?>>PROGRESS</option>
                            <option value="WAITING_MATERIAL" <?= $wo['status'] === 'WAITING_MATERIAL' ? 'selected' : '' ?>>WAITING MATERIAL</option>
                            <option value="WAITING_PADAM" <?= $wo['status'] === 'WAITING_PADAM' ? 'selected' : '' ?>>WAITING PADAM</option>
                            <option value="COMPLETED" <?= $wo['status'] === 'COMPLETED' ? 'selected' : '' ?>>COMPLETED (SELESAI)</option>
                            <option value="CANCELLED" <?= $wo['status'] === 'CANCELLED' ? 'selected' : '' ?>>CANCELLED</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Catatan Kemajuan / Tindak Lanjut</label>
                        <textarea name="catatan" class="form-control" rows="3" placeholder="Detail progres atau catatan pengerjaan..."></textarea>
                    </div>

                    <!-- Upload Foto EV -->
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-danger">Foto Sebelum Pengerjaan</label>
                        <input type="file" name="foto_sebelum" class="form-control form-control-sm" accept="image/*">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-warning">Foto Proses Pengerjaan</label>
                        <input type="file" name="foto_proses" class="form-control form-control-sm" accept="image/*">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-success">Foto Sesudah Pengerjaan (Eviden)</label>
                        <input type="file" name="foto_sesudah" class="form-control form-control-sm" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 font-weight-bold">Simpan Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah Material -->
<div class="modal fade" id="addMaterialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <form action="<?= site_url('work-orders/add-material/' . $wo['id']) ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-dark"><i class="fas fa-boxes-packing text-warning me-2"></i> Tambah Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Material <span class="text-danger">*</span></label>
                        <input type="text" name="nama_material" class="form-control" placeholder="Contoh: Fuse Cut Out 20kV / NH Fuse 160A" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Jumlah</label>
                            <input type="number" step="0.1" name="jumlah" class="form-control" value="1.0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Satuan</label>
                            <input type="text" name="satuan" class="form-control" value="Pcs" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 font-weight-bold">Tambah Material</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    $('.chk-toggle-item').on('change', function() {
        var chkId = $(this).data('id');
        $.post("<?= site_url('work-orders/toggle-checklist/') ?>" + chkId, {
            <?= csrf_token() ?>: "<?= csrf_hash() ?>"
        }, function(res) {
            location.reload();
        });
    });
});
</script>
<?= $this->endSection() ?>
