<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<style>
:root {
    --cc-bg: #0b111e;
    --cc-card-bg: rgba(18, 26, 43, 0.85);
    --cc-border: rgba(45, 62, 92, 0.6);
}

.ad-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.ad-card {
    background: var(--cc-card-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--cc-border);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    margin-bottom: 24px;
}
</style>

<div class="content-wrapper">
    <div class="ad-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <a href="<?= base_url('operational-planning/acceptances') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Penerimaan
                </a>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-award text-success mr-2"></i>Sertifikat Penerimaan: <code><?= esc($acc['acceptance_code']) ?></code>
                </h2>
                <div class="text-muted small">
                    Eksekusi: <strong class="text-info"><?= esc($acc['execution_code']) ?></strong> &bull; Rencana: <strong><?= esc($acc['plan_code']) ?></strong> &bull; Lokasi: <strong><?= esc($acc['feeder_name']) ?> (<?= esc($acc['section_name']) ?>)</strong>
                </div>
            </div>

            <div>
                <?php
                    $st = $acc['acceptance_status'];
                    $badge = match($st) {
                        'WORK_ACCEPTED'             => 'badge-primary',
                        'WORK_CLOSED'               => 'badge-success',
                        'REWORK_REQUIRED'           => 'badge-warning',
                        'ACCEPTANCE_REJECTED'       => 'badge-danger',
                        default                     => 'badge-secondary',
                    };
                ?>
                <span class="badge <?= $badge ?> px-3 py-2 text-uppercase font-size-sm">
                    Status: <?= str_replace('_', ' ', $st) ?>
                </span>
            </div>
        </div>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success bg-success text-white py-2 mb-3">
                <i class="fas fa-check-circle mr-1"></i> <?= session()->getFlashdata('success') ?>
            </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger bg-danger text-white py-2 mb-3">
                <i class="fas fa-exclamation-triangle mr-1"></i> <?= session()->getFlashdata('error') ?>
            </div>
        <?php endif; ?>

        <!-- SHA-256 Cryptographic Certificate Seal Strip (if accepted or closed) -->
        <?php if (!empty($acc['acceptance_certificate_sha256'])): ?>
            <div class="alert alert-dark border border-success bg-dark mb-4 py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-success font-weight-bold">
                            <i class="fas fa-certificate mr-1"></i> SERTIFIKAT PENERIMAAN RESMI BERSEGEL SHA-256
                        </span>
                        <div class="font-size-xs text-muted mt-1">
                            Certificate Hash: <code class="text-warning"><?= esc($acc['acceptance_certificate_sha256']) ?></code>
                        </div>
                    </div>
                    <div>
                        <span class="badge badge-success px-3 py-2 text-uppercase">
                            <?= esc($seal_verification['integrity_verdict'] ?? 'SHA256_INTEGRITY_VERIFIED') ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Rework Alert (if REWORK_REQUIRED) -->
        <?php if ($st === 'REWORK_REQUIRED'): ?>
            <div class="alert alert-warning bg-dark border-warning text-warning mb-4 py-3">
                <h6 class="font-weight-bold mb-1"><i class="fas fa-tools mr-1"></i> Instruksi Perbaikan Fisik Lapangan (Rework Required):</h6>
                <p class="small mb-2 text-white"><?= esc($acc['rework_instructions']) ?></p>
                <form method="POST" action="<?= base_url('operational-planning/acceptances/transition/' . $acc['id']) ?>" class="mt-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action_type" value="REQUEST_REINSPECTION">
                    <div class="input-group input-group-sm">
                        <input type="text" name="decision_rationale" class="form-control bg-dark text-white border-secondary" placeholder="Catatan perbaikan telah selesai dilaksanakan..." required>
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-warning font-weight-bold">
                                <i class="fas fa-redo mr-1"></i> Ajukan Re-Inspeksi (Re-Inspection)
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- ─────────────────────────────────────────────────────────────────
             1. 4-DIMENSIONAL QUALITY & ACCEPTANCE AUDIT FORM
             ───────────────────────────────────────────────────────────────── -->
        <form method="POST" action="<?= base_url('operational-planning/acceptances/evaluate/' . $acc['id']) ?>">
            <?= csrf_field() ?>

            <div class="ad-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="text-white font-weight-bold mb-0">
                            <i class="fas fa-microscope text-success mr-2"></i>Evaluasi 4 Dimensi Mutu & Kelaikan Pekerjaan
                        </h5>
                        <small class="text-muted">Penerimaan resmi mensyaratkan skor mutu minimal <strong>85.00%</strong></small>
                    </div>
                    <div>
                        <span class="h4 font-weight-bold <?= (float)$acc['quality_score'] >= 85 ? 'text-success' : 'text-danger' ?> mb-0">
                            Skor Mutu: <?= number_format((float)$acc['quality_score'], 1) ?>%
                        </span>
                    </div>
                </div>

                <div class="row">
                    
                    <!-- Dimensi 1: Evidence Verification -->
                    <div class="col-md-6 mb-3">
                        <div class="p-3 bg-dark rounded border border-secondary h-100">
                            <h6 class="text-info font-weight-bold mb-2">
                                <i class="fas fa-camera mr-1"></i> 1. Verifikasi Bukti Foto & Lokasi
                            </h6>
                            <?php foreach ($evidence as $idx => $ev): ?>
                                <div class="custom-control custom-checkbox mb-2 small">
                                    <input type="checkbox" class="custom-control-input" id="ev_<?= $idx ?>" name="evidence_passed[<?= $idx ?>]" value="1" <?= !empty($ev['passed']) ? 'checked' : '' ?> <?= in_array($st, ['WORK_ACCEPTED', 'WORK_CLOSED'], true) ? 'disabled' : '' ?>>
                                    <label class="custom-control-label text-white" for="ev_<?= $idx ?>"><?= esc($ev['item']) ?></label>
                                    <input type="hidden" name="evidence_items[<?= $idx ?>]" value="<?= esc($ev['item']) ?>">
                                    <input type="hidden" name="evidence_notes[<?= $idx ?>]" value="<?= esc($ev['notes'] ?? '') ?>">
                                    <small class="text-muted d-block">&bull; <?= esc($ev['notes'] ?? '') ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Dimensi 2: Technical Quality & Safety -->
                    <div class="col-md-6 mb-3">
                        <div class="p-3 bg-dark rounded border border-secondary h-100">
                            <h6 class="text-warning font-weight-bold mb-2">
                                <i class="fas fa-bolt mr-1"></i> 2. Mutu Teknis & Ruang Bebas (ROW)
                            </h6>
                            <?php foreach ($technical as $idx => $t): ?>
                                <div class="custom-control custom-checkbox mb-2 small">
                                    <input type="checkbox" class="custom-control-input" id="tech_<?= $idx ?>" name="technical_passed[<?= $idx ?>]" value="1" <?= !empty($t['passed']) ? 'checked' : '' ?> <?= in_array($st, ['WORK_ACCEPTED', 'WORK_CLOSED'], true) ? 'disabled' : '' ?>>
                                    <label class="custom-control-label text-white" for="tech_<?= $idx ?>"><?= esc($t['item']) ?></label>
                                    <input type="hidden" name="technical_items[<?= $idx ?>]" value="<?= esc($t['item']) ?>">
                                    <input type="hidden" name="technical_notes[<?= $idx ?>]" value="<?= esc($t['notes'] ?? '') ?>">
                                    <small class="text-muted d-block">&bull; <?= esc($t['notes'] ?? '') ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Dimensi 3: Material Variance Audit -->
                    <div class="col-md-6 mb-3">
                        <div class="p-3 bg-dark rounded border border-secondary h-100">
                            <h6 class="text-success font-weight-bold mb-2">
                                <i class="fas fa-boxes mr-1"></i> 3. Audit Selisih Material & Logistik
                            </h6>
                            <?php foreach ($material as $idx => $m): ?>
                                <div class="custom-control custom-checkbox mb-2 small">
                                    <input type="checkbox" class="custom-control-input" id="mat_<?= $idx ?>" name="material_passed[<?= $idx ?>]" value="1" <?= !empty($m['passed']) ? 'checked' : '' ?> <?= in_array($st, ['WORK_ACCEPTED', 'WORK_CLOSED'], true) ? 'disabled' : '' ?>>
                                    <label class="custom-control-label text-white" for="mat_<?= $idx ?>"><?= esc($m['item']) ?></label>
                                    <input type="hidden" name="material_items[<?= $idx ?>]" value="<?= esc($m['item']) ?>">
                                    <input type="hidden" name="material_notes[<?= $idx ?>]" value="<?= esc($m['notes'] ?? '') ?>">
                                    <small class="text-muted d-block">&bull; <?= esc($m['notes'] ?? '') ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Dimensi 4: As-Built & Documentation -->
                    <div class="col-md-6 mb-3">
                        <div class="p-3 bg-dark rounded border border-secondary h-100">
                            <h6 class="text-primary font-weight-bold mb-2">
                                <i class="fas fa-file-alt mr-1"></i> 4. Kelengkapan Berkas & As-Built
                            </h6>
                            <?php foreach ($asbuilt as $idx => $a): ?>
                                <div class="custom-control custom-checkbox mb-2 small">
                                    <input type="checkbox" class="custom-control-input" id="asb_<?= $idx ?>" name="asbuilt_passed[<?= $idx ?>]" value="1" <?= !empty($a['passed']) ? 'checked' : '' ?> <?= in_array($st, ['WORK_ACCEPTED', 'WORK_CLOSED'], true) ? 'disabled' : '' ?>>
                                    <label class="custom-control-label text-white" for="asb_<?= $idx ?>"><?= esc($a['item']) ?></label>
                                    <input type="hidden" name="asbuilt_items[<?= $idx ?>]" value="<?= esc($a['item']) ?>">
                                    <input type="hidden" name="asbuilt_notes[<?= $idx ?>]" value="<?= esc($a['notes'] ?? '') ?>">
                                    <small class="text-muted d-block">&bull; <?= esc($a['notes'] ?? '') ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

                <?php if (!in_array($st, ['WORK_ACCEPTED', 'WORK_CLOSED'], true)): ?>
                    <div class="form-group mt-2">
                        <label class="small font-weight-bold text-muted">Alasan Evaluasi Mutu (Mandatory):</label>
                        <input type="text" name="decision_rationale" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Pemeriksaan fisik ROW dan pelepasan grounding set telah diverifikasi lengkap" required>
                    </div>

                    <button type="submit" class="btn btn-outline-info font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Simpan Evaluasi Mutu
                    </button>
                <?php endif; ?>
            </div>
        </form>

        <!-- ─────────────────────────────────────────────────────────────────
             2. APPEND-ONLY ACCEPTANCE EVENT AUDIT TRAIL
             ───────────────────────────────────────────────────────────────── -->
        <?php if (!empty($events)): ?>
            <div class="ad-card">
                <h5 class="text-white font-weight-bold mb-3">
                    <i class="fas fa-history text-muted mr-2"></i>Jejak Forensik Penerimaan Mutu (Append-Only Events)
                </h5>

                <div class="table-responsive">
                    <table class="table table-dark table-sm table-bordered small mb-0">
                        <thead>
                            <tr class="text-muted text-uppercase">
                                <th>Timestamp</th>
                                <th>Jenis Event</th>
                                <th>Transisi Status</th>
                                <th class="text-center">Skor Mutu</th>
                                <th>Alasan Pertimbangan</th>
                                <th>Pejabat QA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $ev): ?>
                                <tr>
                                    <td class="text-muted"><?= esc($ev['decided_at']) ?></td>
                                    <td><span class="badge badge-info"><?= esc($ev['event_type']) ?></span></td>
                                    <td><code><?= esc($ev['previous_status']) ?></code> &rarr; <strong class="text-success"><?= esc($ev['new_status']) ?></strong></td>
                                    <td class="text-center font-weight-bold text-warning"><?= (float)$ev['quality_score'] ?>%</td>
                                    <td class="text-white"><?= esc($ev['decision_rationale']) ?></td>
                                    <td class="text-muted"><?= esc($ev['decided_by']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- ─────────────────────────────────────────────────────────────────
             3. GOVERNED ACTION PANEL (ACCEPT / REWORK / CLOSE)
             ───────────────────────────────────────────────────────────────── -->
        <?php if ($st === 'ACCEPTANCE_REVIEW_PENDING'): ?>
            <div class="row">
                
                <!-- Accept Work Form -->
                <div class="col-md-6">
                    <div class="ad-card border border-success">
                        <h5 class="text-success font-weight-bold mb-2">
                            <i class="fas fa-stamp mr-1"></i> Sahkan Penerimaan Mutu (Work Accepted)
                        </h5>
                        <p class="small text-muted mb-3">Menerbitkan Sertifikat Penerimaan Mutu resmi bersegel kriptografi SHA-256.</p>

                        <form method="POST" action="<?= base_url('operational-planning/acceptances/transition/' . $acc['id']) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action_type" value="ACCEPT_WORK">
                            <div class="form-group">
                                <label class="small font-weight-bold text-warning">Pertimbangan Pengesahan Penerimaan (Mandatory):</label>
                                <input type="text" name="decision_rationale" class="form-control bg-dark text-white border-secondary" placeholder="Mutu pekerjaan lulus seluruh kriteria QA dan aman dioperasikan" required>
                            </div>
                            <button type="submit" class="btn btn-success font-weight-bold" <?= (float)$acc['quality_score'] < 85 ? 'disabled' : '' ?>>
                                <i class="fas fa-certificate mr-1"></i> Terbitkan Sertifikat Penerimaan (SHA-256)
                            </button>
                            <?php if ((float)$acc['quality_score'] < 85): ?>
                                <small class="text-danger d-block mt-1">Skor mutu saat ini (<?= (float)$acc['quality_score'] ?>%) di bawah 85%. Mohon perbaiki checklist atau ajukan Rework.</small>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Request Rework Form -->
                <div class="col-md-6">
                    <div class="ad-card border border-warning">
                        <h5 class="text-warning font-weight-bold mb-2">
                            <i class="fas fa-tools mr-1"></i> Minta Perbaikan Lapangan (Request Rework)
                        </h5>
                        <p class="small text-muted mb-3">Kirim kembali pekerjaan ke regu lapangan dengan instruksi perbaikan spesifik.</p>

                        <form method="POST" action="<?= base_url('operational-planning/acceptances/transition/' . $acc['id']) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action_type" value="REQUEST_REWORK">
                            <div class="form-group">
                                <label class="small font-weight-bold text-white">Instruksi / Temuan Defisiensi Fisik (Mandatory):</label>
                                <input type="text" name="rework_instructions" class="form-control bg-dark text-white border-secondary" placeholder="Dahan pohon span 13 masih berjarak 2m dari konduktor, wajib dirabas ulang" required>
                            </div>
                            <button type="submit" class="btn btn-outline-warning btn-sm font-weight-bold">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Tetapkan Status REWORK_REQUIRED
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        <?php elseif ($st === 'WORK_ACCEPTED'): ?>
            <div class="ad-card border border-primary">
                <h5 class="text-primary font-weight-bold mb-2">
                    <i class="fas fa-archive mr-1"></i> Penutupan Resmi Tata Kelola Pekerjaan (Final Work Closure)
                </h5>
                <p class="small text-muted mb-3">
                    Pekerjaan telah diterima secara mutu. Otorisasi penutupan administratif oleh Manajer akan mengunci seluruh silsilah forensik (M-05 s.d. OP-07) secara permanen.
                </p>

                <form method="POST" action="<?= base_url('operational-planning/acceptances/transition/' . $acc['id']) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action_type" value="CLOSE_WORK">
                    <div class="form-group">
                        <label class="small font-weight-bold text-warning">Alasan / Pernyataan Penutupan Pekerjaan oleh Manajer (Mandatory):</label>
                        <input type="text" name="decision_rationale" class="form-control bg-dark text-white border-secondary" placeholder="Seluruh kewajiban fisik, logistik material, dan administrasi telah selesai sempurna" required>
                    </div>
                    <button type="submit" class="btn btn-primary font-weight-bold">
                        <i class="fas fa-lock mr-1"></i> Sahkan Penutupan Resmi (WORK_CLOSED)
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="alert alert-secondary bg-dark border-secondary small text-muted mb-0">
                <i class="fas fa-lock mr-1"></i> Rekaman penerimaan ini telah mencapai batas terminal (<code><?= esc($st) ?></code>).
                <?php if (!empty($acc['closing_manager_name'])): ?>
                    <div class="text-white mt-1">Ditutup oleh Manajer: <strong><?= esc($acc['closing_manager_name']) ?></strong> (<?= esc($acc['closed_at']) ?>)</div>
                    <div class="text-success mt-1">Alasan Penutupan: <?= esc($acc['closure_rationale']) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?= $this->endSection() ?>
