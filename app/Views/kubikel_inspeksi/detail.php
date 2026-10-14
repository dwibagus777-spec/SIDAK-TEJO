<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Detail Inspeksi Gardu<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Detail Inspeksi Gardu<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-clipboard-data me-2"></i><?= esc($inspeksi['nomor_inspeksi'] ?? 'Inspeksi Kubikel') ?></h1>
            <div class="text-muted"><?= esc($inspeksi['tgl_inspeksi'] ?? '-') ?> · <?= esc($inspeksi['status'] ?? '-') ?></div>
        </div>
        <div class="d-flex gap-2">
            <?php if ($canEdit && ($inspeksi['status'] ?? '') !== 'DITUTUP'): ?>
                <a href="<?= site_url('kubikel-inspeksi/edit/' . (int)$inspeksi['id']) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit Informasi</a>
            <?php endif; ?>
            <a href="<?= site_url('kubikel-inspeksi') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Daftar</a>
        </div>
    </div>

    <?php foreach (['success' => 'success', 'error' => 'danger'] as $flashKey => $alertClass): ?>
        <?php if ($message = session()->getFlashdata($flashKey)): ?>
            <div class="alert alert-<?= $alertClass ?> alert-dismissible fade show" role="alert">
                <?= esc($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
    <div id="action-message" class="alert d-none" role="alert"></div>

    <div class="row g-3 mb-4">
        <?php foreach ([
            'ULP' => $inspeksi['ulp_id'] ?? '-',
            'Penyulang' => $inspeksi['penyulang_id'] ?? '-',
            'Section' => $inspeksi['section_id'] ?? '-',
            'Gardu Induk' => $inspeksi['gardu_induk_id'] ?? '-',
            'Aset' => $inspeksi['asset_id'] ?? '-',
            'ID Pelanggan' => $inspeksi['id_pel'] ?? '-',
            'Nama Pelanggan' => $inspeksi['nama_pelanggan'] ?? '-',
            'Kepemilikan Gardu' => $inspeksi['kepemilikan_gardu'] ?? '-',
            'Jenis Gardu' => $inspeksi['jenis_gardu'] ?? '-',
            'Petugas' => $inspeksi['inspector_user_id'] ?? '-',
            'Tanggal Inspeksi' => $inspeksi['tgl_inspeksi'] ?? '-',
            'Status Temuan' => $inspeksi['status_temuan'] ?? '-',
        ] as $label => $value): ?>
            <div class="col-sm-6 col-lg-4">
                <div class="card shadow-sm h-100"><div class="card-body">
                    <div class="small text-muted"><?= esc($label) ?></div>
                    <div class="fw-semibold"><?= esc($value) ?></div>
                </div></div>
            </div>
        <?php endforeach; ?>
        <div class="col-12">
            <div class="card shadow-sm"><div class="card-body">
                <div class="small text-muted">Detail Temuan</div>
                <div><?= nl2br(esc($inspeksi['catatan_umum'] ?? '-')) ?></div>
            </div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Status Inspeksi</h2>
            <?php if (in_array($role, ['administrator', 'admin', 'admin_pusat', 'supervisor'], true) && ($inspeksi['status'] ?? '') === 'DITUTUP'): ?>
                <form action="<?= site_url('kubikel-inspeksi/reopen/' . (int)$inspeksi['id']) ?>" method="post" data-ajax-form class="d-flex gap-2">
                    <?= csrf_field() ?>
                    <input type="text" name="alasan" class="form-control form-control-sm" placeholder="Alasan buka kembali" required>
                    <button class="btn btn-sm btn-warning" type="submit">Buka Kembali</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($canEdit && ($inspeksi['status'] ?? '') !== 'DITUTUP'): ?>
                <form action="<?= site_url('kubikel-inspeksi/status/update/' . (int)$inspeksi['id']) ?>" method="post" data-ajax-form class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-sm-4">
                        <label for="status" class="form-label">Perubahan Status</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="">Pilih status</option>
                            <?php foreach (['DRAFT', 'IN_PROGRESS', 'SELESAI', 'DITUTUP', 'DIBATALKAN'] as $status): ?>
                                <?php if ($status !== ($inspeksi['status'] ?? '')): ?>
                                    <option value="<?= esc($status) ?>"><?= esc($status) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label for="alasan_penutupan" class="form-label">Alasan Penutupan (wajib saat menutup)</label>
                        <input type="text" name="alasan_penutupan" id="alasan_penutupan" class="form-control" maxlength="1000">
                    </div>
                    <div class="col-sm-2"><button class="btn btn-primary w-100" type="submit">Simpan</button></div>
                </form>
            <?php else: ?>
                <span class="badge text-bg-primary"><?= esc($inspeksi['status'] ?? '-') ?></span>
                <?php if (!empty($inspeksi['alasan_penutupan'])): ?><p class="mt-2 mb-0"><strong>Alasan penutupan:</strong> <?= esc($inspeksi['alasan_penutupan']) ?></p><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Checklist Pemeriksaan</h2></div>
        <?php if ($checklist): ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead><tr><th>Komponen / Item</th><th>Fasa / Posisi</th><th>Kondisi</th><th>Temuan / Rekomendasi</th><th>Urgensi / Pekerjaan</th><?php if ($canEdit && $inspeksi['status'] !== 'DITUTUP'): ?><th>Aksi</th><?php endif; ?></tr></thead>
                    <tbody>
                        <?php foreach ($checklist as $item): ?>
                            <tr>
                                <td><strong><?= esc($item['komponen'] ?? '-') ?></strong><br><?= esc($item['item_pemeriksaan'] ?? '-') ?></td>
                                <td><?= esc($item['fasa'] ?? '-') ?><br><span class="text-muted"><?= esc($item['posisi'] ?? '-') ?></span></td>
                                <td><?= esc($item['kondisi'] ?? '-') ?></td>
                                <td><?= nl2br(esc($item['temuan'] ?? '-')) ?><br><span class="text-muted"><?= nl2br(esc($item['rekomendasi'] ?? '')) ?></span></td>
                                <td><?= esc($item['urgensi'] ?? '-') ?><br><?= esc($item['status_pekerjaan'] ?? '-') ?></td>
                                <?php if ($canEdit && $inspeksi['status'] !== 'DITUTUP'): ?>
                                    <td>
                                        <details><summary class="btn btn-sm btn-outline-secondary">Ubah</summary>
                                            <form action="<?= site_url('kubikel-inspeksi/checklist/update/' . (int)$inspeksi['id'] . '/' . (int)$item['id']) ?>" method="post" data-ajax-form class="mt-2" style="min-width:280px">
                                                <?= csrf_field() ?>
                                                <input class="form-control form-control-sm mb-1" name="komponen" value="<?= esc($item['komponen'] ?? '') ?>" maxlength="100" required>
                                                <select class="form-select form-select-sm mb-1" name="fasa"><?php foreach (['R','S','T','N','TIDAK_BERFASA'] as $fasa): ?><option value="<?= $fasa ?>" <?= ($item['fasa'] ?? '') === $fasa ? 'selected' : '' ?>><?= $fasa ?></option><?php endforeach; ?></select>
                                                <input class="form-control form-control-sm mb-1" name="posisi" value="<?= esc($item['posisi'] ?? '') ?>" maxlength="100" placeholder="Posisi">
                                                <input class="form-control form-control-sm mb-1" name="item_pemeriksaan" value="<?= esc($item['item_pemeriksaan'] ?? '') ?>" maxlength="255" required>
                                                <select class="form-select form-select-sm mb-1" name="kondisi"><?php foreach (['BAIK','RUSAK_RINGAN','RUSAK_SEDANG','RUSAK_BERAT','TIDAK_DITEMUKAN','TIDAK_APLIKABEL'] as $value): ?><option value="<?= $value ?>" <?= ($item['kondisi'] ?? '') === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select>
                                                <textarea class="form-control form-control-sm mb-1" name="temuan" placeholder="Temuan"><?= esc($item['temuan'] ?? '') ?></textarea>
                                                <select class="form-select form-select-sm mb-1" name="urgensi"><?php foreach (['RENDAH','SEDANG','TINGGI','KRITIS'] as $value): ?><option value="<?= $value ?>" <?= ($item['urgensi'] ?? '') === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select>
                                                <textarea class="form-control form-control-sm mb-1" name="rekomendasi" placeholder="Rekomendasi"><?= esc($item['rekomendasi'] ?? '') ?></textarea>
                                                <textarea class="form-control form-control-sm mb-1" name="tindak_lanjut" placeholder="Tindak lanjut"><?= esc($item['tindak_lanjut'] ?? '') ?></textarea>
                                                <input class="form-control form-control-sm mb-1" name="material_digunakan" value="<?= esc($item['material_digunakan'] ?? '') ?>" placeholder="Material">
                                                <select class="form-select form-select-sm mb-1" name="status_pekerjaan"><?php foreach (['BELUM','PROSES','SELESAI','DITANGGUHKAN'] as $value): ?><option value="<?= $value ?>" <?= ($item['status_pekerjaan'] ?? '') === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select>
                                                <button class="btn btn-sm btn-primary" type="submit">Simpan Checklist</button>
                                            </form>
                                        </details>
                                        <form action="<?= site_url('kubikel-inspeksi/checklist/delete/' . (int)$inspeksi['id'] . '/' . (int)$item['id']) ?>" method="post" data-ajax-form data-confirm="Hapus checklist ini?" class="mt-1">
                                            <?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?><div class="card-body text-muted">Belum ada checklist pemeriksaan.</div><?php endif; ?>
        <?php if ($canEdit && $inspeksi['status'] !== 'DITUTUP'): ?>
            <div class="card-body border-top">
                <h3 class="h6">Tambah Checklist</h3>
                <form action="<?= site_url('kubikel-inspeksi/checklist/store/' . (int)$inspeksi['id']) ?>" method="post" data-ajax-form class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-md-3"><input name="komponen" class="form-control" placeholder="Komponen" maxlength="100" required></div>
                    <div class="col-md-2"><select name="fasa" class="form-select"><?php foreach (['R','S','T','N','TIDAK_BERFASA'] as $value): ?><option value="<?= $value ?>"><?= $value ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2"><input name="posisi" class="form-control" placeholder="Posisi" maxlength="100"></div>
                    <div class="col-md-5"><input name="item_pemeriksaan" class="form-control" placeholder="Item pemeriksaan" maxlength="255" required></div>
                    <div class="col-md-3"><select name="kondisi" class="form-select"><?php foreach (['BAIK','RUSAK_RINGAN','RUSAK_SEDANG','RUSAK_BERAT','TIDAK_DITEMUKAN','TIDAK_APLIKABEL'] as $value): ?><option value="<?= $value ?>"><?= $value ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><select name="urgensi" class="form-select"><?php foreach (['RENDAH','SEDANG','TINGGI','KRITIS'] as $value): ?><option value="<?= $value ?>"><?= $value ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><select name="status_pekerjaan" class="form-select"><?php foreach (['BELUM','PROSES','SELESAI','DITANGGUHKAN'] as $value): ?><option value="<?= $value ?>"><?= $value ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Tambah Checklist</button></div>
                    <div class="col-md-6"><textarea name="temuan" class="form-control" placeholder="Temuan (opsional)"></textarea></div>
                    <div class="col-md-6"><textarea name="rekomendasi" class="form-control" placeholder="Rekomendasi (opsional)"></textarea></div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Temuan dan Rekomendasi</h2></div>
        <?php if ($temuan): ?>
            <div class="table-responsive"><table class="table table-striped align-middle mb-0">
                <thead><tr><th>Tanggal</th><th>Komponen</th><th>Deskripsi / Rekomendasi</th><th>Potensi</th><th>Urgensi</th><th>Penerima</th><th>Status</th><?php if ($canEdit && $inspeksi['status'] !== 'DITUTUP'): ?><th>Aksi</th><?php endif; ?></tr></thead>
                <tbody><?php foreach ($temuan as $item): ?><tr>
                    <td><span class="badge text-bg-light border font-monospace"><?= esc(!empty($item['tanggal_temuan']) ? date('d/m/Y', strtotime($item['tanggal_temuan'])) : (!empty($inspeksi['tgl_inspeksi']) ? date('d/m/Y', strtotime($inspeksi['tgl_inspeksi'])) : '-')) ?></span></td>
                    <td><?= esc($item['komponen'] ?? '-') ?><br><span class="text-muted"><?= esc($item['fasa'] ?? '-') ?> · <?= esc($item['posisi'] ?? '-') ?></span></td>
                    <td><?= nl2br(esc($item['deskripsi_temuan'] ?? '-')) ?><br><span class="text-muted"><?= nl2br(esc($item['rekomendasi'] ?? '')) ?></span></td>
                    <td>
                        <?php if (!empty($item['jenis_potensi'])): ?>
                            <span class="badge text-bg-warning">
                                <?= $item['jenis_potensi'] === 'TREATMENT_TRAFO' ? 'Treatment Trafo' : ($item['jenis_potensi'] === 'CLEANING_KUBIKEL' ? 'Cleaning Kubikel' : esc($item['jenis_potensi'])) ?>
                            </span>
                            <div class="small text-success fw-bold">Rp <?= number_format($item['nilai_potensi'] ?? 0, 0, ',', '.') ?></div>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($item['urgensi'] ?? '-') ?></td><td><?= esc($item['penerima_rekomendasi'] ?? '-') ?></td><td><?= esc($item['status_temuan'] ?? '-') ?></td>
                    <?php if ($canEdit && $inspeksi['status'] !== 'DITUTUP'): ?>
                        <td><details><summary class="btn btn-sm btn-outline-secondary">Ubah</summary>
                            <form action="<?= site_url('kubikel-inspeksi/temuan/update/' . (int)$inspeksi['id'] . '/' . (int)$item['id']) ?>" method="post" data-ajax-form class="mt-2" style="min-width:260px">
                                <?= csrf_field() ?>
                                <label class="form-label small text-muted mb-0">Tanggal Temuan</label>
                                <input type="date" name="tanggal_temuan" class="form-control form-control-sm mb-1" value="<?= esc($item['tanggal_temuan'] ?? $inspeksi['tgl_inspeksi'] ?? date('Y-m-d')) ?>">
                                <input name="komponen" class="form-control form-control-sm mb-1" value="<?= esc($item['komponen'] ?? '') ?>" maxlength="100" required>
                                <select name="fasa" class="form-select form-select-sm mb-1"><?php foreach (['R','S','T','N','TIDAK_BERFASA'] as $value): ?><option value="<?= $value ?>" <?= ($item['fasa'] ?? '') === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select>
                                <input name="posisi" class="form-control form-control-sm mb-1" value="<?= esc($item['posisi'] ?? '') ?>" maxlength="100">
                                <textarea name="deskripsi_temuan" class="form-control form-control-sm mb-1" required><?= esc($item['deskripsi_temuan'] ?? '') ?></textarea>
                                <label class="form-label small text-muted mb-0">Potensi Pekerjaan</label>
                                <select name="jenis_potensi" class="form-select form-select-sm mb-1 edit-potensi-select" onchange="syncPotensiEdit(this)">
                                    <option value="" data-price="0">-- Tanpa Potensi --</option>
                                    <option value="TREATMENT_TRAFO" data-price="5000000" <?= ($item['jenis_potensi'] ?? '') === 'TREATMENT_TRAFO' ? 'selected' : '' ?>>Treatment Trafo (Rp 5.000.000)</option>
                                    <option value="CLEANING_KUBIKEL" data-price="4999000" <?= ($item['jenis_potensi'] ?? '') === 'CLEANING_KUBIKEL' ? 'selected' : '' ?>>Cleaning Kubikel (Rp 4.999.000)</option>
                                </select>
                                <input type="hidden" name="nilai_potensi" class="edit-nilai-potensi-val" value="<?= esc($item['nilai_potensi'] ?? '0') ?>">
                                <input type="text" class="form-control form-control-sm mb-1 bg-light fw-bold text-success edit-nilai-potensi-prev" readonly value="Rp <?= number_format($item['nilai_potensi'] ?? 0, 0, ',', '.') ?>">
                                <select name="urgensi" class="form-select form-select-sm mb-1"><?php foreach (['RENDAH','SEDANG','TINGGI','KRITIS'] as $value): ?><option value="<?= $value ?>" <?= ($item['urgensi'] ?? '') === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select>
                                <textarea name="rekomendasi" class="form-control form-control-sm mb-1" placeholder="Rekomendasi"><?= esc($item['rekomendasi'] ?? '') ?></textarea>
                                <button class="btn btn-sm btn-primary" type="submit">Simpan Temuan</button>
                            </form>
                        </details></td>
                    <?php endif; ?>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php else: ?><div class="card-body text-muted">Belum ada temuan.</div><?php endif; ?>
        <?php if ($canEdit && $inspeksi['status'] !== 'DITUTUP'): ?>
            <div class="card-body border-top">
                <h3 class="h6">Catat Temuan Baru</h3>
                <form action="<?= site_url('kubikel-inspeksi/temuan/store/' . (int)$inspeksi['id']) ?>" method="post" data-ajax-form class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-md-2"><label class="form-label small text-muted mb-0">Tanggal Temuan</label><input type="date" name="tanggal_temuan" class="form-control" value="<?= esc($inspeksi['tgl_inspeksi'] ?? date('Y-m-d')) ?>" required></div>
                    <div class="col-md-3"><label class="form-label small text-muted mb-0">Komponen</label><input name="komponen" class="form-control" placeholder="Komponen" maxlength="100" required></div>
                    <div class="col-md-2"><label class="form-label small text-muted mb-0">Fasa</label><select name="fasa" class="form-select"><?php foreach (['R','S','T','N','TIDAK_BERFASA'] as $value): ?><option value="<?= $value ?>"><?= $value ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2"><label class="form-label small text-muted mb-0">Posisi</label><input name="posisi" class="form-control" placeholder="Posisi" maxlength="100"></div>
                    <div class="col-md-3"><label class="form-label small text-muted mb-0">Item Checklist</label><select name="checklist_id" class="form-select"><option value="">Tanpa item checklist</option><?php foreach ($checklist as $check): ?><option value="<?= (int)$check['id'] ?>"><?= esc($check['komponen'] . ' — ' . $check['item_pemeriksaan']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><textarea name="deskripsi_temuan" class="form-control" placeholder="Deskripsi temuan" required></textarea></div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-0">Potensi Pekerjaan</label>
                        <select name="jenis_potensi" id="create_jenis_potensi" class="form-select" onchange="syncPotensiPrice(this, '#create_nilai_potensi', '#create_harga_potensi_preview')">
                            <option value="" data-price="0">-- Tanpa Potensi --</option>
                            <option value="TREATMENT_TRAFO" data-price="5000000">Treatment Trafo (Rp 5.000.000)</option>
                            <option value="CLEANING_KUBIKEL" data-price="4999000">Cleaning Kubikel (Rp 4.999.000)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-0">Harga Potensi</label>
                        <input type="hidden" name="nilai_potensi" id="create_nilai_potensi" value="0">
                        <input type="text" id="create_harga_potensi_preview" class="form-control bg-light fw-bold text-success" readonly value="Rp 0">
                    </div>
                    <div class="col-md-3"><select name="urgensi" class="form-select"><?php foreach (['RENDAH','SEDANG','TINGGI','KRITIS'] as $value): ?><option value="<?= $value ?>"><?= $value ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><select name="penerima_rekomendasi" class="form-select" required><option value="ULP">ULP</option><option value="HAR_GTT">HAR GTT</option><option value="BEYOND_KWH">Beyond KWH</option><option value="ADMINISTRATOR">Administrator</option><option value="LAINNYA">Lainnya</option></select></div>
                    <div class="col-md-6"><textarea name="rekomendasi" class="form-control" placeholder="Rekomendasi tindak lanjut"></textarea></div>
                    <div class="col-md-4"><select name="assigned_to_user_id" class="form-select"><option value="">Pilih petugas Beyond KWH (jika dipilih sebagai penerima)</option><?php foreach ($beyondUsers as $user): ?><option value="<?= (int)$user['id'] ?>"><?= esc($user['nama_pegawai'] ?? 'User ' . $user['id']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Simpan Temuan</button></div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Foto Inspeksi</h2></div>
        <?php if ($foto): ?>
            <div class="table-responsive"><table class="table table-striped align-middle mb-0">
                <thead><tr><th>Foto</th><th>Kategori</th><th>Deskripsi</th><th>Diunggah</th><?php if ($canEdit && $inspeksi['status'] !== 'DITUTUP'): ?><th></th><?php endif; ?></tr></thead>
                <tbody><?php foreach ($foto as $item): ?><tr>
                    <td><a href="<?= esc(get_photo_url($item['path_file'] ?? null)) ?>" target="_blank" rel="noopener"><img src="<?= esc(get_photo_url($item['path_file'] ?? null)) ?>" alt="<?= esc($item['deskripsi'] ?? 'Foto inspeksi') ?>" class="img-thumbnail" style="max-width:110px;max-height:90px"></a><br><?= esc($item['nama_file'] ?? '-') ?></td>
                    <td><?= esc($item['kategori'] ?? '-') ?></td><td><?= esc($item['deskripsi'] ?? '-') ?></td><td><?= esc($item['created_at'] ?? '-') ?></td>
                    <?php if ($canEdit && $inspeksi['status'] !== 'DITUTUP'): ?><td><form action="<?= site_url('kubikel-inspeksi/foto/delete/' . (int)$inspeksi['id'] . '/' . (int)$item['id']) ?>" method="post" data-ajax-form data-confirm="Hapus foto ini?"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form></td><?php endif; ?>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php else: ?><div class="card-body text-muted">Belum ada foto inspeksi.</div><?php endif; ?>
        <?php if ($canEdit && $inspeksi['status'] !== 'DITUTUP'): ?>
            <div class="card-body border-top">
                <form action="<?= site_url('kubikel-inspeksi/foto/upload/' . (int)$inspeksi['id']) ?>" method="post" enctype="multipart/form-data" data-ajax-form class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-md-3"><label class="form-label">File (JPEG/PNG/WebP, maks. 10 MB)</label><input type="file" name="foto" class="form-control" accept="image/jpeg,image/png,image/webp" required></div>
                    <div class="col-md-2"><label class="form-label">Kategori</label><select name="kategori" class="form-select"><?php foreach (['SEBELUM','SESUDAH','TEMUAN','BUKTI','LAINNYA'] as $value): ?><option value="<?= $value ?>"><?= $value ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label">Terkait Checklist</label><select name="checklist_id" class="form-select"><option value="">Tidak terkait checklist</option><?php foreach ($checklist as $check): ?><option value="<?= (int)$check['id'] ?>"><?= esc($check['komponen'] . ' — ' . $check['item_pemeriksaan']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label">Deskripsi</label><input name="deskripsi" class="form-control" maxlength="1000"></div>
                    <div class="col-md-1"><button class="btn btn-primary w-100" type="submit">Unggah</button></div>
                </form>
                <div class="form-text mt-2">File disimpan di SIDAK_STORAGE_PATH, di luar public, dan hanya disajikan melalui endpoint foto berotorisasi.</div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Material</h2></div>
        <?php if ($material): ?><div class="table-responsive"><table class="table table-striped mb-0">
            <thead><tr><th>Kode</th><th>Nama</th><th>Satuan</th><th>Rencana</th><th>Digunakan</th><th>Sisa</th><th>Status</th><th>Catatan</th></tr></thead>
            <tbody><?php foreach ($material as $item): ?><tr><td><?= esc($item['kode_material'] ?? '-') ?></td><td><?= esc($item['nama_material'] ?? '-') ?></td><td><?= esc($item['satuan'] ?? '-') ?></td><td><?= esc($item['jumlah_rencana'] ?? '-') ?></td><td><?= esc($item['jumlah_digunakan'] ?? '-') ?></td><td><?= esc($item['sisa'] ?? '-') ?></td><td><?= esc($item['status'] ?? '-') ?></td><td><?= esc($item['catatan'] ?? '-') ?></td></tr><?php endforeach; ?></tbody>
        </table></div><?php else: ?><div class="card-body text-muted">Belum ada material yang tercatat.</div><?php endif; ?>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Histori Perubahan</h2></div>
        <?php if ($histori): ?><div class="table-responsive"><table class="table table-striped mb-0">
            <thead><tr><th>Waktu</th><th>Jenis</th><th>Nilai Lama</th><th>Nilai Baru</th><th>User</th><th>Alasan</th></tr></thead>
            <tbody><?php foreach ($histori as $item): ?><tr><td><?= esc($item['created_at'] ?? '-') ?></td><td><?= esc($item['jenis_perubahan'] ?? '-') ?></td><td><?= esc($item['nilai_lama'] ?? '-') ?></td><td><?= esc($item['nilai_baru'] ?? '-') ?></td><td><?= esc($item['user_id'] ?? '-') ?></td><td><?= esc($item['alasan'] ?? '-') ?></td></tr><?php endforeach; ?></tbody>
        </table></div><?php else: ?><div class="card-body text-muted">Belum ada histori.</div><?php endif; ?>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const message = document.getElementById('action-message');
    document.querySelectorAll('[data-ajax-form]').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;
            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                });
                const result = await response.json();
                if (!response.ok || result.success !== true) {
                    const details = result.errors ? Object.values(result.errors).join(' ') : '';
                    throw new Error([result.message || 'Permintaan gagal.', details].filter(Boolean).join(' '));
                }
                message.className = 'alert alert-success';
                message.textContent = result.message || 'Perubahan tersimpan.';
                window.location.reload();
            } catch (error) {
                message.className = 'alert alert-danger';
                message.textContent = error.message || 'Permintaan gagal diproses.';
                message.scrollIntoView({behavior: 'smooth', block: 'center'});
            } finally {
                if (button) button.disabled = false;
            }
        });
    });
});

function syncPotensiPrice(selectEl, hiddenSelector, previewSelector) {
    const opt = selectEl.options[selectEl.selectedIndex];
    const price = opt ? opt.getAttribute('data-price') : null;
    const hidden = document.querySelector(hiddenSelector);
    const preview = document.querySelector(previewSelector);
    if (price && Number(price) > 0) {
        if (hidden) hidden.value = price;
        if (preview) preview.value = 'Rp ' + Number(price).toLocaleString('id-ID');
    } else {
        if (hidden) hidden.value = '0';
        if (preview) preview.value = 'Rp 0';
    }
}
function syncPotensiEdit(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    const price = opt ? opt.getAttribute('data-price') : 0;
    const form = selectEl.closest('form');
    if (form) {
        const hidden = form.querySelector('.edit-nilai-potensi-val');
        const prev = form.querySelector('.edit-nilai-potensi-prev');
        if (hidden) hidden.value = price || '0';
        if (prev) prev.value = 'Rp ' + Number(price || 0).toLocaleString('id-ID');
    }
}
</script>
<?= $this->endSection() ?>
