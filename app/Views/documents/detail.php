<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4" style="gap: 12px;">
        <div>
            <h3 class="fw-bold mb-1 text-dark" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-file-signature text-primary me-2"></i> Detail Dokumen Resmi
            </h3>
            <p class="text-muted small mb-0">Nomor: <strong class="font-monospace text-primary"><?= esc($doc['nomor_dokumen']) ?></strong></p>
        </div>
        <a href="<?= site_url('documents') ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
    </div>

    <div class="row g-4">
        <!-- Document Profile -->
        <div class="col-lg-8 col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1"><?= esc($doc['judul_dokumen']) ?></h5>
                            <span class="badge bg-secondary me-1"><?= esc($doc['jenis_dokumen']) ?></span>
                            <?php if ($doc['status'] === 'APPROVED'): ?>
                                <span class="badge bg-success"><i class="fas fa-circle-check me-1"></i> APPROVED</span>
                            <?php elseif ($doc['status'] === 'REVIEW'): ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half me-1"></i> REVIEW</span>
                            <?php elseif ($doc['status'] === 'REJECTED'): ?>
                                <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> REJECTED</span>
                            <?php else: ?>
                                <span class="badge bg-info"><?= esc($doc['status']) ?></span>
                            <?php endif; ?>
                        </div>
                        <!-- QR Code Verification Link -->
                        <div class="text-center">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?= urlencode(site_url('documents/verify/' . $doc['checksum'])) ?>" alt="QR Verification" class="rounded shadow-sm">
                            <small class="d-block text-muted mt-1" style="font-size: 10px;">QR Verifikasi</small>
                        </div>
                    </div>

                    <hr>

                    <table class="table table-borderless mb-0" style="font-size: 13px;">
                        <tr><td class="text-secondary fw-bold" style="width: 160px;">Nomor Dokumen</td><td class="fw-bold font-monospace text-primary"><?= esc($doc['nomor_dokumen']) ?></td></tr>
                        <tr><td class="text-secondary fw-bold">Jenis Dokumen</td><td><?= esc($doc['jenis_dokumen']) ?></td></tr>
                        <tr><td class="text-secondary fw-bold">Diterbitkan Oleh</td><td><?= esc($doc['created_by']) ?></td></tr>
                        <tr><td class="text-secondary fw-bold">Tanggal Terbit</td><td><?= indo_date($doc['created_at']) ?></td></tr>
                        <tr><td class="text-secondary fw-bold">SHA256 Checksum</td><td><code class="text-muted" style="font-size: 11px; word-break: break-all;"><?= esc($doc['checksum']) ?></code></td></tr>
                        <tr><td class="text-secondary fw-bold">URL Verifikasi Publik</td><td><a href="<?= site_url('documents/verify/' . $doc['checksum']) ?>" target="_blank" class="text-primary text-decoration-none small"><?= site_url('documents/verify/' . substr($doc['checksum'], 0, 16) . '...') ?></a></td></tr>
                    </table>

                    <!-- Document Content Preview -->
                    <?php if (!empty($doc['content_html'])): ?>
                        <hr>
                        <h6 class="fw-bold text-dark mb-2"><i class="fas fa-file-lines me-1 text-info"></i> Isi Dokumen</h6>
                        <div class="p-3 bg-light rounded-3 border" style="font-size: 13px;">
                            <?= $doc['content_html'] ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Digital Signatures List -->
            <div class="card border-0 shadow-sm rounded-4 mt-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="fas fa-signature text-success me-2"></i> Tanda Tangan Digital</h5>
                    <?php if (empty($doc['signatures'])): ?>
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-pen-nib fs-3 mb-2 d-block opacity-50"></i>
                            <p class="small mb-0">Belum ada tanda tangan digital pada dokumen ini.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($doc['signatures'] as $sig): ?>
                            <div class="d-flex align-items-start gap-3 p-3 bg-light rounded-3 border mb-2">
                                <div class="text-success"><i class="fas fa-circle-check fs-4"></i></div>
                                <div class="flex-fill">
                                    <span class="fw-bold text-dark d-block"><?= esc($sig['signer_name']) ?></span>
                                    <small class="text-muted"><?= esc(get_role_label($sig['signer_role'])) ?> &middot; <?= esc($sig['signature_date']) ?></small>
                                    <?php if (!empty($sig['notes'])): ?>
                                        <p class="small text-secondary mt-1 mb-0"><i class="fas fa-comment me-1"></i> <?= esc($sig['notes']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Revision History -->
            <?php if (!empty($doc['revisions'])): ?>
                <div class="card border-0 shadow-sm rounded-4 mt-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-dark mb-3"><i class="fas fa-clock-rotate-left text-warning me-2"></i> Riwayat Revisi</h5>
                        <?php foreach ($doc['revisions'] as $rev): ?>
                            <div class="d-flex align-items-start gap-2 mb-2" style="font-size: 13px;">
                                <span class="badge bg-warning text-dark">Rev <?= $rev['revision_number'] ?></span>
                                <div>
                                    <span class="fw-bold text-dark"><?= esc($rev['revised_by']) ?></span>
                                    <small class="text-muted d-block"><?= esc($rev['created_at']) ?></small>
                                    <?php if (!empty($rev['revision_notes'])): ?>
                                        <p class="small text-secondary mb-0"><?= esc($rev['revision_notes']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar: Approval & Signature Form -->
        <div class="col-lg-4 col-12">
            <!-- Approval Workflow Timeline -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-diagram-project text-primary me-2"></i> Approval Workflow</h6>
                    <div class="d-flex flex-column gap-2">
                        <?php
                            $steps = ['DRAFT', 'REVIEW', 'APPROVED'];
                            $currentIdx = array_search($doc['status'], $steps);
                            if ($currentIdx === false) $currentIdx = -1;
                        ?>
                        <?php foreach ($steps as $i => $step): ?>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($i <= $currentIdx): ?>
                                    <span class="badge bg-success rounded-circle p-1" style="width: 22px; height: 22px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-check" style="font-size: 10px;"></i></span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted rounded-circle p-1 border" style="width: 22px; height: 22px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-circle" style="font-size: 6px;"></i></span>
                                <?php endif; ?>
                                <span class="small <?= $i <= $currentIdx ? 'fw-bold text-dark' : 'text-muted' ?>"><?= $step ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Digital Signature Form -->
            <?php if ($doc['status'] !== 'APPROVED' && $doc['status'] !== 'ARCHIVED'): ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="fas fa-pen-to-square text-warning me-2"></i> Tanda Tangan & Setujui</h6>
                        <form method="POST" action="<?= site_url('documents/approve/' . $doc['id']) ?>">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">Catatan Approval</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Tuliskan catatan persetujuan..."></textarea>
                            </div>
                            <input type="hidden" name="signature_canvas_data" value="DIGITAL_SIGNATURE_CANVAS_DATA">
                            <button type="submit" class="btn btn-success btn-sm w-100 fw-bold rounded-pill">
                                <i class="fas fa-signature me-1"></i> Tanda Tangani & Setujui Dokumen
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
