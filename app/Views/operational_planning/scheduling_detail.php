<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<style>
:root {
    --cc-bg: #0b111e;
    --cc-card-bg: rgba(18, 26, 43, 0.85);
    --cc-border: rgba(45, 62, 92, 0.6);
}

.sd-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.sd-card {
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
    <div class="sd-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <a href="<?= base_url('operational-planning/scheduling') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Skenario
                </a>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-calendar-check text-info mr-2"></i>Skenario Penjadwalan: <code><?= esc($scenario['scenario_code']) ?></code>
                </h2>
                <div class="text-muted small">
                    <?= esc($scenario['scenario_title']) ?> &bull; Portofolio: <code><?= esc($scenario['portfolio_code']) ?></code> &bull; Strategi: <strong><?= esc($scenario['scenario_strategy']) ?></strong>
                </div>
            </div>

            <div>
                <?php
                    $st = $scenario['scenario_status'];
                    $badge = match($st) {
                        'SCENARIO_APPROVED'     => 'badge-success',
                        'UNDER_CAPACITY_REVIEW' => 'badge-info',
                        'REVISION_REQUIRED'     => 'badge-danger',
                        'SCENARIO_SUPERSEDED'   => 'badge-secondary',
                        default                 => 'badge-warning',
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

        <!-- Macro Capacity Strip -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="sd-card text-center py-3 mb-0">
                    <small class="text-muted text-uppercase">Total Rencana Terjadwal</small>
                    <div class="h3 font-weight-bold text-white mb-0"><?= (int)$scenario['total_scheduled_plans_count'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="sd-card text-center py-3 mb-0">
                    <small class="text-muted text-uppercase">Estimasi Man-Days</small>
                    <div class="h3 font-weight-bold text-warning mb-0"><?= number_format((float)$scenario['total_estimated_man_days'], 1) ?> Hari</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="sd-card text-center py-3 mb-0">
                    <small class="text-muted text-uppercase">Puncak Pemadaman Harian</small>
                    <div class="h3 font-weight-bold text-danger mb-0"><?= (int)$scenario['peak_daily_outage_count'] ?> / Hari</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="sd-card text-center py-3 mb-0">
                    <small class="text-muted text-uppercase">Status Beban Kapasitas</small>
                    <div class="h5 font-weight-bold text-success mb-0 mt-2"><?= esc($capacity['capacity_status'] ?? 'BALANCED') ?></div>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             1. SCHEDULED SLOTS CALENDAR / TABLE
             ───────────────────────────────────────────────────────────────── -->
        <div class="sd-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="text-white font-weight-bold mb-0">
                        <i class="fas fa-calendar-day text-info mr-2"></i>Matriks Alokasi Slot Jadwal & Regu Kerja
                    </h5>
                    <small class="text-muted">Perubahan tanggal atau jam eksekusi wajib menyertakan alasan telaah kapasitas</small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-dark table-hover table-bordered small mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase">
                            <th>Tanggal Pelaksanaan</th>
                            <th>Jam Kerja</th>
                            <th>Plan Code</th>
                            <th>Penyulang & Seksi</th>
                            <th>Estimasi Regu Kerja</th>
                            <th class="text-center">Metode</th>
                            <th>Catatan Jadwal</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slots as $sl): ?>
                            <tr>
                                <td class="font-weight-bold text-warning">
                                    <i class="far fa-calendar-alt mr-1"></i> <?= esc($sl['scheduled_date']) ?>
                                </td>
                                <td>
                                    <?= substr($sl['scheduled_start_time'], 0, 5) ?> - <?= substr($sl['scheduled_end_time'], 0, 5) ?>
                                    <small class="text-muted d-block">(<?= (float)$sl['estimated_duration_hours'] ?> Jam)</small>
                                </td>
                                <td class="font-weight-bold text-info">
                                    <code><?= esc($sl['plan_code']) ?></code>
                                </td>
                                <td>
                                    <strong class="text-white"><?= esc($sl['feeder_name']) ?></strong> &bull; <?= esc($sl['section_name']) ?>
                                </td>
                                <td>
                                    <span class="badge badge-dark border border-secondary"><?= esc($sl['estimated_crew_type']) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($sl['outage_required'])): ?>
                                        <span class="badge badge-danger">PADAM</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">PDKB</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted">
                                    <?= esc($sl['scheduling_notes'] ?? '-') ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!in_array($st, ['SCENARIO_APPROVED', 'SCENARIO_SUPERSEDED'], true)): ?>
                                        <button type="button" class="btn btn-sm btn-outline-warning font-weight-bold" onclick="openSlotModal(<?= (int)$sl['id'] ?>, '<?= esc($sl['plan_code']) ?>', '<?= esc($sl['scheduled_date']) ?>', '<?= esc($sl['scheduled_start_time']) ?>', '<?= esc($sl['scheduled_end_time']) ?>', <?= (float)$sl['estimated_duration_hours'] ?>, '<?= esc($sl['estimated_crew_type']) ?>', <?= (int)$sl['outage_required'] ?>, '<?= esc(addslashes($sl['scheduling_notes'] ?? '')) ?>')">
                                            <i class="fas fa-edit mr-1"></i> Ubah Slot
                                        </button>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><i class="fas fa-lock mr-1"></i> Frozen</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             2. APPEND-ONLY SLOT DECISION AUDIT LOG
             ───────────────────────────────────────────────────────────────── -->
        <?php if (!empty($events)): ?>
            <div class="sd-card">
                <h5 class="text-white font-weight-bold mb-3">
                    <i class="fas fa-history text-muted mr-2"></i>Jejak Forensik Penjadwalan & Override Kapasitas (Slot Audit Trail)
                </h5>

                <div class="table-responsive">
                    <table class="table table-dark table-sm table-bordered small mb-0">
                        <thead>
                            <tr class="text-muted text-uppercase">
                                <th>Timestamp</th>
                                <th>Plan Code</th>
                                <th>Jenis Event</th>
                                <th>Alasan Pertimbangan Manusia</th>
                                <th>Pengambil Keputusan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $ev): ?>
                                <tr>
                                    <td class="text-muted"><?= esc($ev['decided_at']) ?></td>
                                    <td class="text-info font-weight-bold"><?= esc($ev['plan_code']) ?></td>
                                    <td>
                                        <span class="badge badge-info"><?= esc($ev['event_type']) ?></span>
                                    </td>
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
             3. APPROVAL & STATE MACHINE TRANSITION
             ───────────────────────────────────────────────────────────────── -->
        <div class="sd-card">
            <h5 class="text-white font-weight-bold mb-3">
                <i class="fas fa-stamp text-success mr-2"></i>Pengesahan & Tata Kelola Status Skenario
            </h5>

            <?php
                $allowedScnTransitions = match($st) {
                    'SCENARIO_DRAFT'        => ['UNDER_CAPACITY_REVIEW' => 'UNDER_CAPACITY_REVIEW (Kirim untuk Telaah Kapasitas)'],
                    'UNDER_CAPACITY_REVIEW' => [
                        'SCENARIO_APPROVED' => 'SCENARIO_APPROVED (Sahkan Skenario Jadwal Resmi)',
                        'REVISION_REQUIRED' => 'REVISION_REQUIRED (Minta Revisi Skenario)'
                    ],
                    'REVISION_REQUIRED'     => ['SCENARIO_DRAFT' => 'SCENARIO_DRAFT (Buka Kembali Draft untuk Revisi)'],
                    default                 => [],
                };
            ?>

            <?php if (!empty($allowedScnTransitions)): ?>
                <form method="POST" action="<?= base_url('operational-planning/scheduling/transition/' . $scenario['id']) ?>">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="small font-weight-bold text-muted">Aksi Transisi Status Skenario:</label>
                                <select name="to_status" class="form-control bg-dark text-white border-secondary" required>
                                    <?php foreach ($allowedScnTransitions as $val => $lbl): ?>
                                        <option value="<?= $val ?>"><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="form-group">
                                <label class="small font-weight-bold text-warning">Alasan Pengesahan / Catatan Revisi (Mandatory):</label>
                                <input type="text" name="approval_rationale" class="form-control bg-dark text-white border-secondary" placeholder="Masukkan alasan keputusan..." required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success font-weight-bold">
                        <i class="fas fa-check-circle mr-1"></i> Simpan Transisi Skenario Jadwal
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-secondary bg-dark border-secondary small text-muted mb-0">
                    <i class="fas fa-lock mr-1"></i> Skenario ini telah disahkan dan dibekukan (<code><?= esc($st) ?></code>).
                    <?php if (!empty($scenario['approver_actor_name'])): ?>
                        <div class="text-white mt-1">Disahkan oleh: <strong><?= esc($scenario['approver_actor_name']) ?></strong> (<?= esc($scenario['approved_at']) ?>)</div>
                        <div class="text-info mt-1">Alasan: <?= esc($scenario['approval_rationale']) ?></div>
                    <?php endif; ?>
                </div>

                <?php if ($st === 'SCENARIO_APPROVED'): ?>
                    <form method="POST" action="<?= base_url('operational-planning/scheduling/supersede/' . $scenario['id']) ?>" class="mt-3" onsubmit="return confirm('Supersede skenario ini agar portofolio dapat dirancang ulang?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="supersede_rationale" value="Skenario jadwal disupersede untuk membuat rencana jadwal baru">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-archive mr-1"></i> Supersede Skenario Ini (Buka Kunci Portofolio)
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Modal Update Slot -->
<div class="modal fade" id="slotModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title font-weight-bold text-warning">
                    <i class="fas fa-calendar-edit mr-2"></i>Ubah Alokasi Slot Jadwal
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="slotForm">
                <?= csrf_field() ?>
                <input type="hidden" name="scenario_id" value="<?= (int)$scenario['id'] ?>">

                <div class="modal-body">
                    <div class="form-group">
                        <label class="small font-weight-bold text-muted">Plan Code:</label>
                        <input type="text" id="modalSlotPlanCode" class="form-control bg-secondary text-white border-dark" readonly>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="small font-weight-bold text-white">Tanggal Pelaksanaan:</label>
                                <input type="date" name="scheduled_date" id="modalScheduledDate" class="form-control bg-dark text-white border-secondary" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="small font-weight-bold text-white">Jam Mulai:</label>
                                <input type="time" name="scheduled_start_time" id="modalStartTime" class="form-control bg-dark text-white border-secondary" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="small font-weight-bold text-white">Jam Selesai:</label>
                                <input type="time" name="scheduled_end_time" id="modalEndTime" class="form-control bg-dark text-white border-secondary" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="small font-weight-bold text-white">Estimasi Regu Kerja:</label>
                                <select name="estimated_crew_type" id="modalCrewType" class="form-control bg-dark text-white border-secondary" required>
                                    <option value="REGU_PDKB_BERTEGANGAN">REGU_PDKB_BERTEGANGAN (Pekerjaan Bertegangan)</option>
                                    <option value="REGU_PEMELIHARAAN_SUTM_PADAM">REGU_PEMELIHARAAN_SUTM_PADAM (Pemeliharaan SUTM Padam)</option>
                                    <option value="REGU_RABAS_POHON_ROW">REGU_RABAS_POHON_ROW (Tim Rabas Pohon)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="small font-weight-bold text-white">Durasi (Jam):</label>
                                <input type="number" name="estimated_duration_hours" id="modalDuration" class="form-control bg-dark text-white border-secondary" step="0.5" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="small font-weight-bold text-white">Pemadaman:</label>
                                <select name="outage_required" id="modalOutage" class="form-control bg-dark text-white border-secondary" required>
                                    <option value="0">PDKB (Bertegangan)</option>
                                    <option value="1">PADAM</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold text-warning">Alasan Pertimbangan Manusia / Override Kapasitas (Mandatory Rationale):</label>
                        <input type="text" name="decision_rationale" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Menggeser jadwal karena koordinasi padam dengan ULP tetangga" required>
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold text-muted">Catatan Tambahan:</label>
                        <input type="text" name="scheduling_notes" id="modalNotes" class="form-control bg-dark text-white border-secondary">
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Simpan Pembaruan Slot
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openSlotModal(slotId, planCode, date, start, end, duration, crew, outage, notes) {
    document.getElementById('slotForm').action = '<?= base_url('operational-planning/scheduling/slot/') ?>/' + slotId;
    document.getElementById('modalSlotPlanCode').value = planCode;
    document.getElementById('modalScheduledDate').value = date;
    document.getElementById('modalStartTime').value = start.substring(0, 5);
    document.getElementById('modalEndTime').value = end.substring(0, 5);
    document.getElementById('modalDuration').value = duration;
    document.getElementById('modalCrewType').value = crew;
    document.getElementById('modalOutage').value = outage;
    document.getElementById('modalNotes').value = notes;
    $('#slotModal').modal('show');
}
</script>

<?= $this->endSection() ?>
