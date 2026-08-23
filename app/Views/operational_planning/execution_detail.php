<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<style>
:root {
    --cc-bg: #0b111e;
    --cc-card-bg: rgba(18, 26, 43, 0.85);
    --cc-border: rgba(45, 62, 92, 0.6);
}

.ed-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.ed-card {
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
    <div class="ed-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <a href="<?= base_url('operational-planning/executions') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Rekaman Eksekusi
                </a>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-hard-hat text-success mr-2"></i>Rekaman Eksekusi: <code><?= esc($exec['execution_code']) ?></code>
                </h2>
                <div class="text-muted small">
                    Paket Otorisasi: <strong class="text-warning"><?= esc($exec['authorization_code']) ?></strong> &bull; Rencana: <strong><?= esc($exec['plan_code']) ?></strong> &bull; Lokasi: <strong><?= esc($exec['feeder_name']) ?> (<?= esc($exec['section_name']) ?>)</strong>
                </div>
            </div>

            <div>
                <?php
                    $st = $exec['execution_status'];
                    $badge = match($st) {
                        'WORK_IN_PROGRESS'                  => 'badge-primary',
                        'WORK_COMPLETED_PENDING_ACCEPTANCE' => 'badge-success',
                        'WORK_PAUSED_SAFETY_HOLD'           => 'badge-danger',
                        'WORK_ABORTED_FIELD_CONSTRAINTS'    => 'badge-dark',
                        default                             => 'badge-warning',
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

        <!-- Macro Progress Strip -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="ed-card text-center py-3 mb-0">
                    <small class="text-muted text-uppercase">Progres Fisik</small>
                    <div class="h3 font-weight-bold text-success mb-0"><?= (float)$exec['progress_percentage'] ?>%</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="ed-card text-center py-3 mb-0">
                    <small class="text-muted text-uppercase">Pengawas Lapangan</small>
                    <div class="h6 font-weight-bold text-white mb-0 mt-2"><?= esc($exec['field_supervisor_name'] ?? 'Belum Ditunjuk') ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="ed-card text-center py-3 mb-0">
                    <small class="text-muted text-uppercase">Jumlah Regu Kerja</small>
                    <div class="h3 font-weight-bold text-warning mb-0"><?= (int)$exec['field_crew_count'] ?> Personil</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="ed-card text-center py-3 mb-0">
                    <small class="text-muted text-uppercase">Waktu Mulai Kerja</small>
                    <div class="h6 font-weight-bold text-info mb-0 mt-2"><?= esc($exec['work_started_at'] ?? 'Menunggu Start') ?></div>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             1. EXPLICIT HUMAN FIELD WORK START PANEL (WORK_PENDING_FIELD_START)
             ───────────────────────────────────────────────────────────────── -->
        <?php if ($st === 'WORK_PENDING_FIELD_START'): ?>
            <div class="ed-card border border-warning">
                <h5 class="text-warning font-weight-bold mb-3">
                    <i class="fas fa-play mr-2"></i>Inisiasi Mulai Kerja Fisik di Lapangan (Human Start Action)
                </h5>
                <p class="small text-muted mb-3">
                    Status <code>EXECUTION_AUTHORIZED</code> tidak pernah memulai pekerjaan secara otomatis. Pengawas lapangan wajib mengunggah bukti foto kondisi awal (<em>Before Photo</em>) dan menyatakan kesiapan regu di lokasi.
                </p>

                <form method="POST" action="<?= base_url('operational-planning/executions/start/' . $exec['id']) ?>">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="small font-weight-bold text-white">URI / File Foto Sebelum Pekerjaan (Before Photo):</label>
                                <input type="text" name="before_photo_uri" class="form-control bg-dark text-white border-secondary" placeholder="uploads/evidence/before_balung_span12.jpg" value="uploads/evidence/before_balung_span12.jpg" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="small font-weight-bold text-white">Catatan Kondisi Awal Lapangan:</label>
                                <input type="text" name="before_photo_notes" class="form-control bg-dark text-white border-secondary" placeholder="Pohon sono berjarak 0.3m dari SUTM span 12-13" value="Pohon sono berjarak 0.3m dari SUTM span 12-13">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold text-warning">Alasan / Pernyataan Mulai Kerja (Mandatory Rationale):</label>
                        <input type="text" name="start_rationale" class="form-control bg-dark text-white border-secondary" placeholder="Toolbox meeting K3 selesai, grounding terpasang, regu siap naik" value="Toolbox meeting K3 selesai, grounding terpasang, regu siap naik" required>
                    </div>

                    <button type="submit" class="btn btn-warning font-weight-bold">
                        <i class="fas fa-play-circle mr-1"></i> Nyatakan Mulai Bekerja (WORK_IN_PROGRESS)
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- ─────────────────────────────────────────────────────────────────
             2. BEFORE & AFTER PHOTO EVIDENCE CARDS
             ───────────────────────────────────────────────────────────────── -->
        <div class="row">
            <div class="col-md-6">
                <div class="ed-card">
                    <h5 class="text-white font-weight-bold mb-3">
                        <i class="fas fa-camera text-info mr-2"></i>Bukti Kondisi Awal (Before Photo)
                    </h5>
                    <?php if (!empty($before_evidence)): ?>
                        <div class="p-3 bg-dark rounded border border-secondary small">
                            <div class="text-warning font-weight-bold mb-1"><i class="fas fa-image mr-1"></i> <?= esc($before_evidence['photo_uri']) ?></div>
                            <div class="text-muted">SHA-256: <code><?= esc(substr($before_evidence['photo_sha256'] ?? '', 0, 24)) ?>...</code></div>
                            <div class="text-white mt-1">&bull; Catatan: <?= esc($before_evidence['notes']) ?></div>
                            <div class="text-muted mt-1">Diunggah oleh: <strong><?= esc($before_evidence['captured_by']) ?></strong> (<?= esc($before_evidence['captured_at']) ?>)</div>
                        </div>
                    <?php else: ?>
                        <p class="small text-muted mb-0">Belum ada foto kondisi awal (menunggu inisiasi mulai kerja).</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-6">
                <div class="ed-card">
                    <h5 class="text-white font-weight-bold mb-3">
                        <i class="fas fa-camera text-success mr-2"></i>Bukti Hasil Akhir (After Photo)
                    </h5>
                    <?php if (!empty($after_evidence)): ?>
                        <div class="p-3 bg-dark rounded border border-secondary small">
                            <div class="text-success font-weight-bold mb-1"><i class="fas fa-image mr-1"></i> <?= esc($after_evidence['photo_uri']) ?></div>
                            <div class="text-muted">SHA-256: <code><?= esc(substr($after_evidence['photo_sha256'] ?? '', 0, 24)) ?>...</code></div>
                            <div class="text-white mt-1">&bull; Catatan: <?= esc($after_evidence['notes']) ?></div>
                            <div class="text-muted mt-1">Diunggah oleh: <strong><?= esc($after_evidence['captured_by']) ?></strong> (<?= esc($after_evidence['captured_at']) ?>)</div>
                        </div>
                    <?php else: ?>
                        <p class="small text-muted mb-0">Belum ada foto hasil akhir (diunggah saat deklarasi penyelesaian).</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             3. ACTUAL MATERIAL RECONCILIATION TABLE (PRESERVING OP-02)
             ───────────────────────────────────────────────────────────────── -->
        <div class="ed-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="text-white font-weight-bold mb-0">
                        <i class="fas fa-boxes text-warning mr-2"></i>Rekonsiliasi Pemakaian Material Aktual (Hardening #6)
                    </h5>
                    <small class="text-muted">Merekam realisasi material aktual tanpa memutasi estimasi perencanaan OP-02</small>
                </div>
            </div>

            <form method="POST" action="<?= base_url('operational-planning/executions/materials/' . $exec['id']) ?>">
                <?= csrf_field() ?>
                <div class="table-responsive">
                    <table class="table table-dark table-sm table-bordered small mb-0">
                        <thead>
                            <tr class="text-muted text-uppercase">
                                <th>Nama Material</th>
                                <th>Satuan</th>
                                <th class="text-center">Estimasi OP-02</th>
                                <th class="text-center" style="width: 140px;">Pemakaian Aktual</th>
                                <th class="text-center">Selisih (Variance)</th>
                                <th>Alasan Selisih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($materials)): ?>
                                <?php foreach ($materials as $idx => $m): ?>
                                    <tr>
                                        <td><strong><?= esc($m['material_name']) ?></strong></td>
                                        <td><?= esc($m['unit']) ?></td>
                                        <td class="text-center font-weight-bold text-info"><?= (float)$m['estimated_quantity'] ?></td>
                                        <td class="text-center">
                                            <input type="number" step="0.5" name="actual_quantity[<?= $idx ?>]" value="<?= (float)$m['actual_quantity'] ?>" class="form-control form-control-sm bg-dark text-white border-secondary text-center" <?= in_array($st, ['WORK_COMPLETED_PENDING_ACCEPTANCE', 'WORK_ABORTED_FIELD_CONSTRAINTS'], true) ? 'disabled' : '' ?>>
                                        </td>
                                        <td class="text-center font-weight-bold <?= (float)$m['variance_quantity'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= (float)$m['variance_quantity'] ?> (<?= (float)$m['variance_percentage'] ?>%)
                                        </td>
                                        <td>
                                            <input type="text" name="variance_rationale[<?= $idx ?>]" value="<?= esc($m['variance_rationale'] ?? '') ?>" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Alasan jika pemakaian berbeda..." <?= in_array($st, ['WORK_COMPLETED_PENDING_ACCEPTANCE', 'WORK_ABORTED_FIELD_CONSTRAINTS'], true) ? 'disabled' : '' ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Tidak ada material khusus yang direkonsiliasi.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!in_array($st, ['WORK_COMPLETED_PENDING_ACCEPTANCE', 'WORK_ABORTED_FIELD_CONSTRAINTS'], true)): ?>
                    <div class="mt-3 text-right">
                        <button type="submit" class="btn btn-sm btn-outline-warning font-weight-bold">
                            <i class="fas fa-save mr-1"></i> Simpan Rekonsiliasi Material
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             4. REAL-TIME PROGRESS UPDATE & APPEND-ONLY LEDGER
             ───────────────────────────────────────────────────────────────── -->
        <?php if ($st === 'WORK_IN_PROGRESS'): ?>
            <div class="ed-card">
                <h5 class="text-white font-weight-bold mb-3">
                    <i class="fas fa-tasks text-info mr-2"></i>Catat Pembaruan Progres Lapangan (Append-Only Ledger)
                </h5>

                <form method="POST" action="<?= base_url('operational-planning/executions/progress/' . $exec['id']) ?>">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="small font-weight-bold text-white">Progres Fisik (%):</label>
                                <input type="number" step="5" name="progress_percentage" value="<?= min(90.0, (float)$exec['progress_percentage'] + 25.0) ?>" class="form-control bg-dark text-white border-secondary" min="10" max="95" required>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="form-group">
                                <label class="small font-weight-bold text-white">Deskripsi Capaian Pekerjaan:</label>
                                <input type="text" name="progress_description" class="form-control bg-dark text-white border-secondary" placeholder="Pemotongan dahan pohon sono span 12 telah selesai 50%" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-info font-weight-bold">
                        <i class="fas fa-plus-circle mr-1"></i> Tambahkan Catatan Progres
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- ─────────────────────────────────────────────────────────────────
             5. APPEND-ONLY PROGRESS EVENT AUDIT TRAIL
             ───────────────────────────────────────────────────────────────── -->
        <?php if (!empty($events)): ?>
            <div class="ed-card">
                <h5 class="text-white font-weight-bold mb-3">
                    <i class="fas fa-history text-muted mr-2"></i>Jejak Forensik Progres & Aksi Lapangan (Append-Only Events)
                </h5>

                <div class="table-responsive">
                    <table class="table table-dark table-sm table-bordered small mb-0">
                        <thead>
                            <tr class="text-muted text-uppercase">
                                <th>Timestamp</th>
                                <th>Jenis Event</th>
                                <th class="text-center">Progres</th>
                                <th>Deskripsi Event</th>
                                <th>Alasan Keputusan</th>
                                <th>Pencatat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $ev): ?>
                                <tr>
                                    <td class="text-muted"><?= esc($ev['recorded_at']) ?></td>
                                    <td><span class="badge badge-info"><?= esc($ev['event_type']) ?></span></td>
                                    <td class="text-center font-weight-bold text-warning"><?= (float)$ev['progress_percentage'] ?>%</td>
                                    <td class="text-white"><?= esc($ev['event_description']) ?></td>
                                    <td class="text-muted"><?= esc($ev['decision_rationale']) ?></td>
                                    <td class="text-muted"><?= esc($ev['recorded_by']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- ─────────────────────────────────────────────────────────────────
             6. SAFETY HOLD & RESUME / COMPLETION DECLARATION
             ───────────────────────────────────────────────────────────────── -->
        <?php if ($st === 'WORK_IN_PROGRESS'): ?>
            <div class="row">
                
                <!-- Safety Hold Form -->
                <div class="col-md-6">
                    <div class="ed-card border border-danger">
                        <h5 class="text-danger font-weight-bold mb-2">
                            <i class="fas fa-pause-circle mr-1"></i> Tetapkan Safety Hold (Jeda Bahaya)
                        </h5>
                        <p class="small text-muted mb-3">Hentikan pekerjaan sementara jika cuaca memburuk atau terjadi anomali keselamatan.</p>

                        <form method="POST" action="<?= base_url('operational-planning/executions/hold/' . $exec['id']) ?>">
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <label class="small font-weight-bold text-white">Alasan Safety Hold:</label>
                                <input type="text" name="safety_hold_reason" class="form-control bg-dark text-white border-secondary" placeholder="Hujan deras dan petir di area Siwalan Panji" required>
                            </div>
                            <div class="form-group">
                                <label class="small font-weight-bold text-white">Deskripsi Risiko:</label>
                                <input type="text" name="risk_description" class="form-control bg-dark text-white border-secondary" placeholder="Risiko induksi tegangan dan licin pada tiang">
                            </div>
                            <button type="submit" class="btn btn-outline-danger btn-sm font-weight-bold">
                                <i class="fas fa-hand-paper mr-1"></i> Jeda Pekerjaan (SAFETY_HOLD)
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Completion Declaration Form -->
                <div class="col-md-6">
                    <div class="ed-card border border-success">
                        <h5 class="text-success font-weight-bold mb-2">
                            <i class="fas fa-check-double mr-1"></i> Deklarasi Penyelesaian Lapangan
                        </h5>
                        <p class="small text-muted mb-3">Unggah bukti foto hasil akhir (<em>After Photo</em>) dan nyatakan pekerjaan fisik selesai.</p>

                        <form method="POST" action="<?= base_url('operational-planning/executions/complete/' . $exec['id']) ?>">
                            <?= csrf_field() ?>
                            <div class="form-group">
                                <label class="small font-weight-bold text-white">URI Foto Setelah Selesai (After Photo):</label>
                                <input type="text" name="after_photo_uri" class="form-control bg-dark text-white border-secondary" placeholder="uploads/evidence/after_balung_span12.jpg" value="uploads/evidence/after_balung_span12.jpg" required>
                            </div>
                            <div class="form-group">
                                <label class="small font-weight-bold text-white">Catatan Hasil Pekerjaan:</label>
                                <input type="text" name="after_photo_notes" class="form-control bg-dark text-white border-secondary" value="Pohon telah dirabas bersih radius 3m dari SUTM, grounding dilepas aman">
                            </div>
                            <div class="form-group">
                                <label class="small font-weight-bold text-warning">Pernyataan Penyelesaian (Mandatory):</label>
                                <input type="text" name="completion_declaration_rationale" class="form-control bg-dark text-white border-secondary" value="Seluruh item pekerjaan fisik telah selesai 100% dan regu telah turun dengan selamat" required>
                            </div>
                            <button type="submit" class="btn btn-success font-weight-bold">
                                <i class="fas fa-check-circle mr-1"></i> Deklarasikan Selesai (Pending Acceptance)
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        <?php elseif ($st === 'WORK_PAUSED_SAFETY_HOLD'): ?>
            <div class="ed-card border border-warning">
                <h5 class="text-warning font-weight-bold mb-2">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Pekerjaan Sedang Dijeda (SAFETY HOLD)
                </h5>
                <p class="text-danger small mb-2">
                    Alasan Jeda: <strong><?= esc($exec['safety_hold_reason']) ?></strong> (Ditetapkan oleh <?= esc($exec['safety_hold_declared_by']) ?> pada <?= esc($exec['safety_hold_declared_at']) ?>)
                </p>

                <form method="POST" action="<?= base_url('operational-planning/executions/resume/' . $exec['id']) ?>" class="mt-3">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label class="small font-weight-bold text-warning">Alasan Re-Evaluasi K3 untuk Melanjutkan Pekerjaan (Mandatory):</label>
                        <input type="text" name="resume_rationale" class="form-control bg-dark text-white border-secondary" placeholder="Cuaca telah cerah kembali, pemeriksaan tiang aman, pekerjaan dapat dilanjutkan" required>
                    </div>
                    <button type="submit" class="btn btn-warning font-weight-bold">
                        <i class="fas fa-play mr-1"></i> Lanjutkan Kembali Pekerjaan (WORK_IN_PROGRESS)
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="alert alert-secondary bg-dark border-secondary small text-muted mb-0">
                <i class="fas fa-lock mr-1"></i> Rekaman eksekusi ini telah mencapai batas terminal (<code><?= esc($st) ?></code>).
                <?php if (!empty($exec['field_completion_declared_by'])): ?>
                    <div class="text-white mt-1">Dideklarasikan Selesai oleh: <strong><?= esc($exec['field_completion_declared_by']) ?></strong> (<?= esc($exec['field_completion_declared_at']) ?>)</div>
                    <div class="text-success mt-1">Alasan: <?= esc($exec['completion_declaration_rationale']) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?= $this->endSection() ?>
