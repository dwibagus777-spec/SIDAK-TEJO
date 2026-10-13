<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Edit Inspeksi Kubikel<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Edit Inspeksi Kubikel<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$errors = session()->getFlashdata('errors') ?? [];
$error = session()->getFlashdata('error');
$success = session()->getFlashdata('success');
?>
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-clipboard-check me-2"></i>Edit Inspeksi Kubikel</h1>
            <div class="text-muted"><?= esc($inspeksi['nomor_inspeksi'] ?? '') ?></div>
        </div>
        <a href="<?= site_url('kubikel-inspeksi/detail/' . (int) $inspeksi['id']) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Detail
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= esc($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= esc($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $validationError): ?>
                    <li><?= esc($validationError) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Status Inspeksi</div>
                    <div class="fw-semibold"><?= esc($inspeksi['status'] ?? '-') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Status Temuan</div>
                    <div class="fw-semibold"><?= esc($inspeksi['status_temuan'] ?? '-') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Petugas Inspeksi</div>
                    <div class="fw-semibold"><?= esc($inspeksi['inspector_user_id'] ?? '-') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Informasi Inspeksi</h2>
        </div>
        <div class="card-body">
            <?php if ($canEdit): ?>
                <form action="<?= site_url('kubikel-inspeksi/update/' . (int) $inspeksi['id']) ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="ulp_id" class="form-label fw-medium">ULP <span class="text-danger">*</span></label>
                            <select name="ulp_id" id="ulp_id" class="form-select" required>
                                <option value="">Pilih ULP</option>
                                <?php foreach ($ulps as $ulp): ?>
                                    <?php $optionValue = (string) $ulp['id']; ?>
                                    <option value="<?= esc($optionValue) ?>" <?= (string) old('ulp_id', $inspeksi['ulp_id'] ?? '') === $optionValue ? 'selected' : '' ?>>
                                        <?= esc($ulp['nama_ulp'] ?? $ulp['nama'] ?? 'ULP ' . $ulp['id']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="penyulang_id" class="form-label fw-medium">Penyulang <span class="text-danger">*</span></label>
                            <select name="penyulang_id" id="penyulang_id" class="form-select" required>
                                <option value="">Pilih Penyulang</option>
                                <?php foreach ($penyulangs as $penyulang): ?>
                                    <?php $optionValue = (string) $penyulang['id']; ?>
                                    <option value="<?= esc($optionValue) ?>" data-ulp="<?= esc($penyulang['ulp_id'] ?? 0) ?>" <?= (string) old('penyulang_id', $inspeksi['penyulang_id'] ?? '') === $optionValue ? 'selected' : '' ?>>
                                        <?= esc($penyulang['nama_penyulang'] ?? $penyulang['nama'] ?? 'Penyulang ' . $penyulang['id']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="section_id" class="form-label fw-medium">Section</label>
                            <select name="section_id" id="section_id" class="form-select">
                                <option value="">Pilih Section (Opsional)</option>
                                <?php foreach ($sections as $section): ?>
                                    <?php $optionValue = (string) $section['id']; ?>
                                    <option value="<?= esc($optionValue) ?>" data-penyulang="<?= esc($section['penyulang_id'] ?? 0) ?>" <?= (string) old('section_id', $inspeksi['section_id'] ?? '') === $optionValue ? 'selected' : '' ?>>
                                        <?= esc($section['nama_section'] ?? $section['nama'] ?? 'Section ' . $section['id']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="gardu_induk_id" class="form-label fw-medium">Gardu Induk</label>
                            <select name="gardu_induk_id" id="gardu_induk_id" class="form-select">
                                <option value="">Pilih Gardu Induk (Opsional)</option>
                                <?php foreach ($garduInduk as $gi): ?>
                                    <?php $optionValue = (string) $gi['id']; ?>
                                    <option value="<?= esc($optionValue) ?>" <?= (string) old('gardu_induk_id', $inspeksi['gardu_induk_id'] ?? '') === $optionValue ? 'selected' : '' ?>>
                                        <?= esc($gi['nama_gi'] ?? $gi['nama'] ?? 'GI ' . $gi['id']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="asset_id" class="form-label fw-medium">Aset (Opsional)</label>
                            <input type="number" name="asset_id" id="asset_id" class="form-control" min="1" value="<?= esc(old('asset_id', $inspeksi['asset_id'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="id_pel" class="form-label fw-medium">ID Pelanggan (Opsional)</label>
                            <input type="text" name="id_pel" id="id_pel" class="form-control" maxlength="100" value="<?= esc(old('id_pel', $inspeksi['id_pel'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="nama_pelanggan" class="form-label fw-medium">Nama Pelanggan (Opsional)</label>
                            <input type="text" name="nama_pelanggan" id="nama_pelanggan" class="form-control" maxlength="255" value="<?= esc(old('nama_pelanggan', $inspeksi['nama_pelanggan'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="tgl_inspeksi" class="form-label fw-medium">Tanggal Inspeksi <span class="text-danger">*</span></label>
                            <input type="date" name="tgl_inspeksi" id="tgl_inspeksi" class="form-control" required value="<?= esc(old('tgl_inspeksi', $inspeksi['tgl_inspeksi'] ?? '')) ?>">
                        </div>
                        <div class="col-12">
                            <label for="catatan_umum" class="form-label fw-medium">Detail Temuan</label>
                            <textarea name="catatan_umum" id="catatan_umum" class="form-control" rows="3"><?= esc(old('catatan_umum', $inspeksi['catatan_umum'] ?? '')) ?></textarea>
                        </div>
                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= site_url('kubikel-inspeksi/detail/' . (int) $inspeksi['id']) ?>" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning mb-0" role="alert">Anda tidak memiliki hak untuk mengedit inspeksi ini.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Checklist Pemeriksaan</h2></div>
        <div class="card-body">
            <?php if ($checklist): ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead><tr><th>Komponen</th><th>Fasa</th><th>Posisi</th><th>Item Pemeriksaan</th><th>Kondisi</th><th>Temuan</th><th>Urgensi</th><th>Rekomendasi</th><th>Tindak Lanjut</th><th>Status Pekerjaan</th></tr></thead>
                        <tbody>
                            <?php foreach ($checklist as $item): ?>
                                <tr>
                                    <td><?= esc($item['komponen'] ?? '-') ?></td>
                                    <td><?= esc($item['fasa'] ?? '-') ?></td>
                                    <td><?= esc($item['posisi'] ?? '-') ?></td>
                                    <td><?= esc($item['item_pemeriksaan'] ?? '-') ?></td>
                                    <td><?= esc($item['kondisi'] ?? '-') ?></td>
                                    <td><?= esc($item['temuan'] ?? '-') ?></td>
                                    <td><?= esc($item['urgensi'] ?? '-') ?></td>
                                    <td><?= esc($item['rekomendasi'] ?? '-') ?></td>
                                    <td><?= esc($item['tindak_lanjut'] ?? '-') ?></td>
                                    <td><?= esc($item['status_pekerjaan'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">Belum ada data checklist.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Temuan</h2></div>
        <div class="card-body">
            <?php if ($temuan): ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead><tr><th>Tanggal</th><th>Komponen</th><th>Fasa</th><th>Posisi</th><th>Deskripsi</th><th>Urgensi</th><th>Penerima Rekomendasi</th><th>Status Temuan</th></tr></thead>
                        <tbody>
                            <?php foreach ($temuan as $item): ?>
                                <tr>
                                    <td><span class="badge text-bg-light border font-monospace"><?= esc(!empty($item['tanggal_temuan']) ? date('d/m/Y', strtotime($item['tanggal_temuan'])) : (!empty($inspeksi['tgl_inspeksi']) ? date('d/m/Y', strtotime($inspeksi['tgl_inspeksi'])) : '-')) ?></span></td>
                                    <td><?= esc($item['komponen'] ?? '-') ?></td>
                                    <td><?= esc($item['fasa'] ?? '-') ?></td>
                                    <td><?= esc($item['posisi'] ?? '-') ?></td>
                                    <td><?= esc($item['deskripsi_temuan'] ?? '-') ?></td>
                                    <td><?= esc($item['urgensi'] ?? '-') ?></td>
                                    <td><?= esc($item['penerima_rekomendasi'] ?? '-') ?></td>
                                    <td><?= esc($item['status_temuan'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">Belum ada temuan.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Foto Inspeksi</h2></div>
        <div class="card-body">
            <?php if ($foto): ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead><tr><th>Nama File</th><th>Kategori</th><th>Deskripsi</th><th>Tipe</th><th>Ukuran</th><th>Diunggah</th></tr></thead>
                        <tbody>
                            <?php foreach ($foto as $item): ?>
                                <tr>
                                    <td><?= esc($item['nama_file'] ?? '-') ?></td>
                                    <td><?= esc($item['kategori'] ?? '-') ?></td>
                                    <td><?= esc($item['deskripsi'] ?? '-') ?></td>
                                    <td><?= esc($item['mime_type'] ?? '-') ?></td>
                                    <td><?= number_format((float) ($item['ukuran'] ?? 0)) ?> byte</td>
                                    <td><?= esc($item['created_at'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="form-text mt-2 mb-0">File foto tersimpan melalui storage SIDAK TEJO di luar folder public.</p>
            <?php else: ?>
                <p class="text-muted mb-0">Belum ada foto inspeksi.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Material</h2></div>
        <div class="card-body">
            <?php if ($material): ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead><tr><th>Kode</th><th>Nama Material</th><th>Satuan</th><th>Jumlah Rencana</th><th>Jumlah Digunakan</th><th>Sisa</th><th>Status</th><th>Catatan</th></tr></thead>
                        <tbody>
                            <?php foreach ($material as $item): ?>
                                <tr>
                                    <td><?= esc($item['kode_material'] ?? '-') ?></td>
                                    <td><?= esc($item['nama_material'] ?? '-') ?></td>
                                    <td><?= esc($item['satuan'] ?? '-') ?></td>
                                    <td><?= esc($item['jumlah_rencana'] ?? '-') ?></td>
                                    <td><?= esc($item['jumlah_digunakan'] ?? '-') ?></td>
                                    <td><?= esc($item['sisa'] ?? '-') ?></td>
                                    <td><?= esc($item['status'] ?? '-') ?></td>
                                    <td><?= esc($item['catatan'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">Belum ada data material.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Histori Perubahan</h2></div>
        <div class="card-body">
            <?php if ($histori): ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead><tr><th>Waktu</th><th>Jenis Perubahan</th><th>Nilai Lama</th><th>Nilai Baru</th><th>User ID</th><th>Alasan</th></tr></thead>
                        <tbody>
                            <?php foreach ($histori as $item): ?>
                                <tr>
                                    <td><?= esc($item['created_at'] ?? '-') ?></td>
                                    <td><?= esc($item['jenis_perubahan'] ?? '-') ?></td>
                                    <td><?= esc($item['nilai_lama'] ?? '-') ?></td>
                                    <td><?= esc($item['nilai_baru'] ?? '-') ?></td>
                                    <td><?= esc($item['user_id'] ?? '-') ?></td>
                                    <td><?= esc($item['alasan'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">Belum ada histori perubahan.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
