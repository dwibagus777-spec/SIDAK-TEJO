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
    <?php if ($message = session()->getFlashdata('success')): ?>
        <div class="alert alert-success" role="alert"><?= esc($message) ?></div>
    <?php endif; ?>
    <div id="action-message" class="alert d-none" role="alert"></div>

    <div class="row g-3 mb-4">
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

    <!-- PIPELINE 5 TAHAPAN TRANSAKSI BEYOND KWH -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center py-3">
                    <div>
                        <h2 class="h5 mb-0 fw-bold text-dark"><i class="bi bi-diagram-3 me-2 text-primary"></i>Alur Transaksi Beyond KWH (5 Tahapan)</h2>
                        <span class="text-muted small">Catat dan kelola transaksi pipeline Beyond KWH untuk temuan ini</span>
                    </div>
                    <div class="btn-group mt-2 mt-md-0" role="group">
                        <a href="<?= site_url('kubikel-inspeksi/beyond-kwh/' . (int)($rekomendasi['id'] ?? $rekomendasi['rekomendasi_id']) . '/probing') ?>" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-search me-1"></i>1. Probing
                        </a>
                        <a href="<?= site_url('kubikel-inspeksi/beyond-kwh/' . (int)($rekomendasi['id'] ?? $rekomendasi['rekomendasi_id']) . '/penawaran') ?>" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-folder me-1"></i>2. Penawaran
                        </a>
                        <a href="<?= site_url('kubikel-inspeksi/beyond-kwh/' . (int)($rekomendasi['id'] ?? $rekomendasi['rekomendasi_id']) . '/spkpo') ?>" class="btn btn-sm btn-outline-info">
                            <i class="bi bi-file-earmark-text me-1"></i>3. SPK/PO
                        </a>
                        <a href="<?= site_url('kubikel-inspeksi/beyond-kwh/' . (int)($rekomendasi['id'] ?? $rekomendasi['rekomendasi_id']) . '/penagihan') ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-receipt me-1"></i>4. Penagihan
                        </a>
                        <a href="<?= site_url('kubikel-inspeksi/beyond-kwh/' . (int)($rekomendasi['id'] ?? $rekomendasi['rekomendasi_id']) . '/pembayaran') ?>" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-check-circle me-1"></i>5. Pembayaran
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Nav tabs -->
                    <ul class="nav nav-pills mb-3" id="pipelineTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active btn-sm" id="tab-probing" data-bs-toggle="tab" data-bs-target="#content-probing" type="button" role="tab">
                                1. Probing (<?= count($probingList ?? []) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link btn-sm" id="tab-penawaran" data-bs-toggle="tab" data-bs-target="#content-penawaran" type="button" role="tab">
                                2. Penawaran (<?= count($penawaranList ?? []) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link btn-sm" id="tab-spkpo" data-bs-toggle="tab" data-bs-target="#content-spkpo" type="button" role="tab">
                                3. SPK/PO (<?= count($spkpoList ?? []) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link btn-sm" id="tab-penagihan" data-bs-toggle="tab" data-bs-target="#content-penagihan" type="button" role="tab">
                                4. Penagihan (<?= count($penagihanList ?? []) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link btn-sm" id="tab-pembayaran" data-bs-toggle="tab" data-bs-target="#content-pembayaran" type="button" role="tab">
                                5. Pembayaran (<?= count($pembayaranList ?? []) ?>)
                            </button>
                        </li>
                    </ul>

                    <!-- Tab content -->
                    <div class="tab-content border rounded p-3 bg-light">
                        <!-- Tab 1: Probing -->
                        <div class="tab-pane fade show active" id="content-probing" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0 text-danger"><i class="bi bi-search me-1"></i>Riwayat Probing</h6>
                                <a href="<?= site_url('kubikel-inspeksi/beyond-kwh/' . (int)($rekomendasi['id'] ?? $rekomendasi['rekomendasi_id']) . '/probing') ?>" class="btn btn-sm btn-danger">+ Input Probing</a>
                            </div>
                            <?php if (empty($probingList)): ?>
                                <p class="text-muted small mb-0">Belum ada riwayat probing untuk rekomendasi ini.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 bg-white">
                                        <thead><tr><th>Tanggal</th><th>Uraian</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($probingList as $row): ?>
                                                <tr>
                                                    <td><?= esc($row['tanggal_probing'] ?? '-') ?></td>
                                                    <td><?= nl2br(esc($row['uraian'] ?? '-')) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Tab 2: Penawaran -->
                        <div class="tab-pane fade" id="content-penawaran" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0 text-warning"><i class="bi bi-folder me-1"></i>Riwayat Penawaran</h6>
                                <a href="<?= site_url('kubikel-inspeksi/beyond-kwh/' . (int)($rekomendasi['id'] ?? $rekomendasi['rekomendasi_id']) . '/penawaran') ?>" class="btn btn-sm btn-warning">+ Input Penawaran</a>
                            </div>
                            <?php if (empty($penawaranList)): ?>
                                <p class="text-muted small mb-0">Belum ada penawaran tercatat.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 bg-white">
                                        <thead><tr><th>Tanggal</th><th>Nomor Penawaran</th><th>Nominal (Rp)</th><th>Uraian</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($penawaranList as $row): ?>
                                                <tr>
                                                    <td><?= esc($row['tanggal_penawaran'] ?? '-') ?></td>
                                                    <td><span class="badge bg-secondary"><?= esc($row['nomor_penawaran'] ?? '-') ?></span></td>
                                                    <td class="fw-bold text-success">Rp <?= number_format((float)($row['nominal'] ?? 0), 0, ',', '.') ?></td>
                                                    <td><?= nl2br(esc($row['uraian'] ?? '-')) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Tab 3: SPK/PO -->
                        <div class="tab-pane fade" id="content-spkpo" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0 text-info"><i class="bi bi-file-earmark-text me-1"></i>Riwayat SPK / PO</h6>
                                <a href="<?= site_url('kubikel-inspeksi/beyond-kwh/' . (int)($rekomendasi['id'] ?? $rekomendasi['rekomendasi_id']) . '/spkpo') ?>" class="btn btn-sm btn-info text-white">+ Input SPK/PO</a>
                            </div>
                            <?php if (empty($spkpoList)): ?>
                                <p class="text-muted small mb-0">Belum ada SPK / PO tercatat.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 bg-white">
                                        <thead><tr><th>Tanggal</th><th>Nomor SPK/PO</th><th>Berlaku s/d</th><th>Nominal (Rp)</th><th>Uraian Perbaikan</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($spkpoList as $row): ?>
                                                <tr>
                                                    <td><?= esc($row['tanggal_spkpo'] ?? '-') ?></td>
                                                    <td><span class="badge bg-info text-white"><?= esc($row['nomor_spkpo'] ?? '-') ?></span></td>
                                                    <td><?= esc($row['tanggal_berlaku'] ?? '-') ?></td>
                                                    <td class="fw-bold text-primary">Rp <?= number_format((float)($row['nominal'] ?? 0), 0, ',', '.') ?></td>
                                                    <td><?= nl2br(esc($row['uraian_jenis_perbaikan'] ?? '-')) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Tab 4: Penagihan -->
                        <div class="tab-pane fade" id="content-penagihan" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-receipt me-1"></i>Riwayat Penagihan</h6>
                                <a href="<?= site_url('kubikel-inspeksi/beyond-kwh/' . (int)($rekomendasi['id'] ?? $rekomendasi['rekomendasi_id']) . '/penagihan') ?>" class="btn btn-sm btn-primary">+ Input Penagihan</a>
                            </div>
                            <?php if (empty($penagihanList)): ?>
                                <p class="text-muted small mb-0">Belum ada penagihan tercatat.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 bg-white">
                                        <thead><tr><th>Tanggal</th><th>Nomor BAPP</th><th>Nomor BAST</th><th>Nominal (Rp)</th><th>Keterangan</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($penagihanList as $row): ?>
                                                <tr>
                                                    <td><?= esc($row['tanggal_penagihan'] ?? '-') ?></td>
                                                    <td><?= esc($row['nomor_bapp'] ?? '-') ?></td>
                                                    <td><?= esc($row['nomor_bast'] ?? '-') ?></td>
                                                    <td class="fw-bold text-primary">Rp <?= number_format((float)($row['nominal'] ?? 0), 0, ',', '.') ?></td>
                                                    <td><?= nl2br(esc($row['keterangan'] ?? '-')) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Tab 5: Pembayaran -->
                        <div class="tab-pane fade" id="content-pembayaran" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0 text-success"><i class="bi bi-check-circle me-1"></i>Riwayat Pembayaran / Status Lunas</h6>
                                <a href="<?= site_url('kubikel-inspeksi/beyond-kwh/' . (int)($rekomendasi['id'] ?? $rekomendasi['rekomendasi_id']) . '/pembayaran') ?>" class="btn btn-sm btn-success">+ Input Pembayaran</a>
                            </div>
                            <?php if (empty($pembayaranList)): ?>
                                <p class="text-muted small mb-0">Belum ada pembayaran tercatat.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 bg-white">
                                        <thead><tr><th>Tanggal</th><th>Nomor Invoice</th><th>Nominal (Rp)</th><th>Keterangan</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($pembayaranList as $row): ?>
                                                <tr>
                                                    <td><?= esc($row['tanggal_pembayaran'] ?? '-') ?></td>
                                                    <td><span class="badge bg-success"><?= esc($row['nomor_invoice'] ?? '-') ?></span></td>
                                                    <td class="fw-bold text-success">Rp <?= number_format((float)($row['nominal'] ?? 0), 0, ',', '.') ?></td>
                                                    <td><?= nl2br(esc($row['keterangan'] ?? '-')) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
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
