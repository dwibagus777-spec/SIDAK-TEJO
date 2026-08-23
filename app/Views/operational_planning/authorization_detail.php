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
                <a href="<?= base_url('operational-planning/authorizations') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Paket Otorisasi
                </a>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-file-signature text-warning mr-2"></i>Paket Otorisasi: <code><?= esc($auth['authorization_code']) ?></code>
                </h2>
                <div class="text-muted small">
                    Rencana: <strong><?= esc($auth['plan_code']) ?></strong> &bull; Skenario: <code><?= esc($auth['scenario_code']) ?></code> &bull; Lokasi: <strong><?= esc($auth['feeder_name']) ?> (<?= esc($auth['section_name']) ?>)</strong>
                </div>
            </div>

            <div>
                <?php
                    $st = $auth['authorization_status'];
                    $badge = match($st) {
                        'EXECUTION_AUTHORIZED'     => 'badge-success',
                        'READINESS_VERIFIED'      => 'badge-info',
                        'REVISION_REQUIRED'       => 'badge-danger',
                        'AUTHORIZATION_REVOKED'   => 'badge-dark',
                        'AUTHORIZATION_SUPERSEDED'=> 'badge-secondary',
                        default                   => 'badge-warning',
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

        <!-- SHA-256 Cryptographic Seal Strip (if sealed) -->
        <?php if (!empty($auth['authorization_sha256'])): ?>
            <div class="alert alert-dark border border-success bg-dark mb-4 py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-success font-weight-bold">
                            <i class="fas fa-shield-check mr-1"></i> RESMI BERSEGEL KRIPTOGRAFI SHA-256
                        </span>
                        <div class="font-size-xs text-muted mt-1">
                            SHA-256 Hash: <code class="text-warning"><?= esc($auth['authorization_sha256']) ?></code>
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

        <!-- ─────────────────────────────────────────────────────────────────
             1. 4-DIMENSIONAL READINESS VERIFICATION CHECKLIST
             ───────────────────────────────────────────────────────────────── -->
        <form method="POST" action="<?= base_url('operational-planning/authorizations/verify-readiness/' . $auth['id']) ?>">
            <?= csrf_field() ?>

            <div class="ad-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="text-white font-weight-bold mb-0">
                            <i class="fas fa-tasks text-info mr-2"></i>Inspeksi 4 Dimensi Kesiapan Eksekusi Operasional
                        </h5>
                        <small class="text-muted">Setiap parameter wajib diverifikasi lulus (100%) sebelum otorisasi dapat disahkan</small>
                    </div>
                    <div>
                        <span class="h4 font-weight-bold <?= (float)$auth['readiness_score'] >= 100 ? 'text-success' : 'text-warning' ?> mb-0">
                            Skor Kesiapan: <?= number_format((float)$auth['readiness_score'], 1) ?>%
                        </span>
                    </div>
                </div>

                <div class="row">
                    
                    <!-- Dimensi 1: K3 & Safety -->
                    <div class="col-md-6 mb-3">
                        <div class="p-3 bg-dark rounded border border-secondary h-100">
                            <h6 class="text-warning font-weight-bold mb-2">
                                <i class="fas fa-hard-hat mr-1"></i> 1. Kesiapan K3 & Keselamatan Kerja
                            </h6>
                            <?php foreach ($safety as $idx => $s): ?>
                                <div class="custom-control custom-checkbox mb-2 small">
                                    <input type="checkbox" class="custom-control-input" id="safety_<?= $idx ?>" name="safety_passed[<?= $idx ?>]" value="1" <?= !empty($s['passed']) ? 'checked' : '' ?> <?= $st === 'EXECUTION_AUTHORIZED' ? 'disabled' : '' ?>>
                                    <label class="custom-control-label text-white" for="safety_<?= $idx ?>"><?= esc($s['item']) ?></label>
                                    <input type="hidden" name="safety_items[<?= $idx ?>]" value="<?= esc($s['item']) ?>">
                                    <input type="hidden" name="safety_notes[<?= $idx ?>]" value="<?= esc($s['notes'] ?? '') ?>">
                                    <small class="text-muted d-block">&bull; <?= esc($s['notes'] ?? '') ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Dimensi 2: Material Availability -->
                    <div class="col-md-6 mb-3">
                        <div class="p-3 bg-dark rounded border border-secondary h-100">
                            <h6 class="text-success font-weight-bold mb-2">
                                <i class="fas fa-boxes mr-1"></i> 2. Kesiapan Material & Alat Kerja
                            </h6>
                            <?php foreach ($material as $idx => $m): ?>
                                <div class="custom-control custom-checkbox mb-2 small">
                                    <input type="checkbox" class="custom-control-input" id="mat_<?= $idx ?>" name="material_passed[<?= $idx ?>]" value="1" <?= !empty($m['passed']) ? 'checked' : '' ?> <?= $st === 'EXECUTION_AUTHORIZED' ? 'disabled' : '' ?>>
                                    <label class="custom-control-label text-white" for="mat_<?= $idx ?>"><?= esc($m['item']) ?></label>
                                    <input type="hidden" name="material_items[<?= $idx ?>]" value="<?= esc($m['item']) ?>">
                                    <input type="hidden" name="material_notes[<?= $idx ?>]" value="<?= esc($m['notes'] ?? '') ?>">
                                    <small class="text-muted d-block">&bull; <?= esc($m['notes'] ?? '') ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Dimensi 3: Permit & Customer Notice -->
                    <div class="col-md-6 mb-3">
                        <div class="p-3 bg-dark rounded border border-secondary h-100">
                            <h6 class="text-info font-weight-bold mb-2">
                                <i class="fas fa-scroll mr-1"></i> 3. Kesiapan Izin & Notifikasi Pelanggan
                            </h6>
                            <?php foreach ($permit as $idx => $p): ?>
                                <div class="custom-control custom-checkbox mb-2 small">
                                    <input type="checkbox" class="custom-control-input" id="permit_<?= $idx ?>" name="permit_passed[<?= $idx ?>]" value="1" <?= !empty($p['passed']) ? 'checked' : '' ?> <?= $st === 'EXECUTION_AUTHORIZED' ? 'disabled' : '' ?>>
                                    <label class="custom-control-label text-white" for="permit_<?= $idx ?>"><?= esc($p['item']) ?></label>
                                    <input type="hidden" name="permit_items[<?= $idx ?>]" value="<?= esc($p['item']) ?>">
                                    <input type="hidden" name="permit_notes[<?= $idx ?>]" value="<?= esc($p['notes'] ?? '') ?>">
                                    <small class="text-muted d-block">&bull; <?= esc($p['notes'] ?? '') ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Dimensi 4: Team & Competency -->
                    <div class="col-md-6 mb-3">
                        <div class="p-3 bg-dark rounded border border-secondary h-100">
                            <h6 class="text-primary font-weight-bold mb-2">
                                <i class="fas fa-users-cog mr-1"></i> 4. Kesiapan Regu & Sertifikasi Kompetensi
                            </h6>
                            <?php foreach ($team as $idx => $t): ?>
                                <div class="custom-control custom-checkbox mb-2 small">
                                    <input type="checkbox" class="custom-control-input" id="team_<?= $idx ?>" name="team_passed[<?= $idx ?>]" value="1" <?= !empty($t['passed']) ? 'checked' : '' ?> <?= $st === 'EXECUTION_AUTHORIZED' ? 'disabled' : '' ?>>
                                    <label class="custom-control-label text-white" for="team_<?= $idx ?>"><?= esc($t['item']) ?></label>
                                    <input type="hidden" name="team_items[<?= $idx ?>]" value="<?= esc($t['item']) ?>">
                                    <input type="hidden" name="team_notes[<?= $idx ?>]" value="<?= esc($t['notes'] ?? '') ?>">
                                    <small class="text-muted d-block">&bull; <?= esc($t['notes'] ?? '') ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

                <?php if ($st !== 'EXECUTION_AUTHORIZED'): ?>
                    <div class="form-group mt-2">
                        <label class="small font-weight-bold text-muted">Alasan Perubahan Checklist Kesiapan (Mandatory):</label>
                        <input type="text" name="decision_rationale" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Seluruh APD 20kV dan briefing K3 telah selesai diinspeksi" required>
                    </div>

                    <button type="submit" class="btn btn-info font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Simpan Pembaruan Kesiapan
                    </button>
                <?php endif; ?>
            </div>
        </form>

        <!-- ─────────────────────────────────────────────────────────────────
             2. APPEND-ONLY EVENT AUDIT LOG
             ───────────────────────────────────────────────────────────────── -->
        <?php if (!empty($events)): ?>
            <div class="ad-card">
                <h5 class="text-white font-weight-bold mb-3">
                    <i class="fas fa-history text-muted mr-2"></i>Jejak Forensik Otorisasi Kerja (Append-Only Audit Trail)
                </h5>

                <div class="table-responsive">
                    <table class="table table-dark table-sm table-bordered small mb-0">
                        <thead>
                            <tr class="text-muted text-uppercase">
                                <th>Timestamp</th>
                                <th>Jenis Event</th>
                                <th>Transisi Status</th>
                                <th>Alasan Pertimbangan Manusia</th>
                                <th>Pejabat / Penilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $ev): ?>
                                <tr>
                                    <td class="text-muted"><?= esc($ev['decided_at']) ?></td>
                                    <td><span class="badge badge-info"><?= esc($ev['event_type']) ?></span></td>
                                    <td><code><?= esc($ev['previous_status']) ?></code> &rarr; <strong class="text-warning"><?= esc($ev['new_status']) ?></strong></td>
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
             3. GOVERNED AUTHORIZATION TRANSITION PANEL
             ───────────────────────────────────────────────────────────────── -->
        <div class="ad-card">
            <h5 class="text-white font-weight-bold mb-3">
                <i class="fas fa-stamp text-success mr-2"></i>Pengesahan Otorisasi & Tata Kelola Status
            </h5>

            <?php
                $allowedAuthTransitions = match($st) {
                    'READINESS_CHECK_PENDING' => [
                        'READINESS_VERIFIED' => 'READINESS_VERIFIED (Verifikasi Kesiapan 100% Selesai)',
                        'REVISION_REQUIRED'  => 'REVISION_REQUIRED (Minta Pemenuhan Ulang Kesiapan)'
                    ],
                    'READINESS_VERIFIED' => [
                        'EXECUTION_AUTHORIZED' => 'EXECUTION_AUTHORIZED (Sahkan Otorisasi Kerja Bersegel SHA-256)',
                        'REVISION_REQUIRED'   => 'REVISION_REQUIRED (Batalkan Verifikasi & Minta Revisi)'
                    ],
                    'REVISION_REQUIRED' => [
                        'READINESS_CHECK_PENDING' => 'READINESS_CHECK_PENDING (Buka Kembali Pemeriksaan Kesiapan)'
                    ],
                    'EXECUTION_AUTHORIZED' => [
                        'AUTHORIZATION_REVOKED' => 'AUTHORIZATION_REVOKED (Cabut Izin Pelaksanaan Kerja Karena Anomali Lapangan)'
                    ],
                    default => [],
                };
            ?>

            <?php if (!empty($allowedAuthTransitions)): ?>
                <form method="POST" action="<?= base_url('operational-planning/authorizations/transition/' . $auth['id']) ?>">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="small font-weight-bold text-muted">Aksi Transisi Status Otorisasi:</label>
                                <select name="to_status" class="form-control bg-dark text-white border-secondary" required>
                                    <?php foreach ($allowedAuthTransitions as $val => $lbl): ?>
                                        <option value="<?= $val ?>"><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="form-group">
                                <label class="small font-weight-bold text-warning">Alasan Keputusan Otorisasi (Mandatory Rationale):</label>
                                <input type="text" name="decision_rationale" class="form-control bg-dark text-white border-secondary" placeholder="Masukkan alasan pengesahan..." required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success font-weight-bold">
                        <i class="fas fa-check-circle mr-1"></i> Simpan Transisi Otorisasi Kerja
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-secondary bg-dark border-secondary small text-muted mb-0">
                    <i class="fas fa-lock mr-1"></i> Paket otorisasi ini telah berstatus terminal (<code><?= esc($st) ?></code>).
                    <?php if (!empty($auth['authorizing_official_name'])): ?>
                        <div class="text-white mt-1">Pejabat Pengesah: <strong><?= esc($auth['authorizing_official_name']) ?></strong> (<?= esc($auth['authorized_at']) ?>)</div>
                        <div class="text-info mt-1">Alasan: <?= esc($auth['authorization_rationale']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?= $this->endSection() ?>
