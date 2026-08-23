<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<style>
:root {
    --cc-bg: #0b111e;
    --cc-card-bg: rgba(18, 26, 43, 0.85);
    --cc-border: rgba(45, 62, 92, 0.6);
}

.pd-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.pd-card {
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
    <div class="pd-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <a href="<?= base_url('operational-planning/portfolios') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Portofolio
                </a>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-layer-group text-warning mr-2"></i>Portofolio: <code><?= esc($portfolio['portfolio_code']) ?></code>
                </h2>
                <div class="text-muted small">
                    <?= esc($portfolio['portfolio_title']) ?> &bull; Periode: Tahun <?= (int)$portfolio['period_year'] ?> - Minggu <?= (int)$portfolio['period_week'] ?>
                </div>
            </div>

            <div>
                <?php
                    $st = $portfolio['portfolio_status'];
                    $badge = match($st) {
                        'PORTFOLIO_RATIFIED'     => 'badge-success',
                        'UNDER_PORTFOLIO_REVIEW' => 'badge-info',
                        'PORTFOLIO_ARCHIVED'     => 'badge-secondary',
                        default                  => 'badge-warning',
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

        <!-- Macro Strip Summary -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="pd-card text-center py-3 mb-0">
                    <small class="text-muted text-uppercase">Total Rencana Kerja</small>
                    <div class="h3 font-weight-bold text-white mb-0"><?= (int)$portfolio['total_plans_count'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="pd-card text-center py-3 mb-0">
                    <small class="text-muted text-uppercase">Tier 1 (Prioritas Utama)</small>
                    <div class="h3 font-weight-bold text-danger mb-0"><?= (int)$portfolio['tier_1_plans_count'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="pd-card text-center py-3 mb-0">
                    <small class="text-muted text-uppercase">Tier 2 (Jadwal Normal)</small>
                    <div class="h3 font-weight-bold text-warning mb-0"><?= (int)$portfolio['tier_2_plans_count'] ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="pd-card text-center py-3 mb-0">
                    <small class="text-muted text-uppercase">Tier 3 (Deferred Backlog)</small>
                    <div class="h3 font-weight-bold text-info mb-0"><?= (int)$portfolio['tier_3_plans_count'] ?></div>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             1. PORTFOLIO ITEMS & HUMAN PRIORITIZATION MATRIX
             ───────────────────────────────────────────────────────────────── -->
        <div class="pd-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="text-white font-weight-bold mb-0">
                        <i class="fas fa-list-ol text-info mr-2"></i>Matriks Prioritisasi Rencana Kerja (Human Governance)
                    </h5>
                    <small class="text-muted">Setiap penetapan atau perubahan tier wajib menyertakan alasan pertimbangan manusia</small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-dark table-hover table-bordered small mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase">
                            <th>Plan Code</th>
                            <th>Penyulang & Seksi</th>
                            <th>Kategori</th>
                            <th class="text-center">Metode</th>
                            <th class="text-center">Priority Tier</th>
                            <th>Alasan Prioritas</th>
                            <th class="text-center">Aksi Prioritas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td class="font-weight-bold text-info">
                                    <code><?= esc($it['plan_code']) ?></code>
                                </td>
                                <td>
                                    <strong class="text-white"><?= esc($it['feeder_name']) ?></strong> &bull; <?= esc($it['section_name']) ?>
                                </td>
                                <td><?= esc($it['work_category']) ?></td>
                                <td class="text-center">
                                    <?php if (!empty($it['outage_required'])): ?>
                                        <span class="badge badge-danger">PADAM</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">PDKB</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                        $pt = $it['priority_tier'];
                                        $tBadge = match($pt) {
                                            'TIER_1_IMMEDIATE_SCHEDULING' => 'badge-danger',
                                            'TIER_2_PLANNED_WINDOW'       => 'badge-warning',
                                            'TIER_3_DEFERRED_MAINTENANCE' => 'badge-info',
                                            default                       => 'badge-secondary',
                                        };
                                    ?>
                                    <span class="badge <?= $tBadge ?> text-uppercase"><?= str_replace('_', ' ', $pt) ?></span>
                                </td>
                                <td class="text-muted">
                                    <?= esc($it['priority_rationale'] ?? '-') ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!in_array($st, ['PORTFOLIO_RATIFIED', 'PORTFOLIO_ARCHIVED'], true)): ?>
                                        <button type="button" class="btn btn-sm btn-outline-warning font-weight-bold" onclick="openTierModal(<?= (int)$it['id'] ?>, '<?= esc($it['plan_code']) ?>', '<?= esc($it['priority_tier']) ?>', '<?= esc(addslashes($it['priority_rationale'] ?? '')) ?>')">
                                            <i class="fas fa-edit mr-1"></i> Tetapkan Tier
                                        </button>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><i class="fas fa-lock mr-1"></i> Locked</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             2. AGGREGATED INDICATIVE MATERIAL DEMANDS
             ───────────────────────────────────────────────────────────────── -->
        <div class="pd-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-white font-weight-bold mb-0">
                    <i class="fas fa-boxes text-success mr-2"></i>Rekapitulasi Agregat Kebutuhan Material (Estimasi Portofolio)
                </h5>
                <span class="badge badge-secondary"><?= esc($portfolio['material_aggregation_status']) ?></span>
            </div>

            <table class="table table-dark table-sm table-bordered small mb-0">
                <thead>
                    <tr class="text-muted text-uppercase">
                        <th>Nama Material</th>
                        <th class="text-center">Total Estimasi Jumlah</th>
                        <th>Satuan</th>
                        <th>Status Tata Kelola</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($materials)): ?>
                        <?php foreach ($materials as $mat): ?>
                            <tr>
                                <td><strong><?= esc($mat['material_name']) ?></strong></td>
                                <td class="text-center font-weight-bold text-warning"><?= (float)($mat['total_quantity'] ?? 0) ?></td>
                                <td><?= esc($mat['unit'] ?? 'buah') ?></td>
                                <td><span class="badge badge-dark border border-secondary"><?= esc($portfolio['material_aggregation_status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">Tidak ada material khusus yang direkap.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             3. APPEND-ONLY TIER DECISION AUDIT LOG
             ───────────────────────────────────────────────────────────────── -->
        <?php if (!empty($tier_events)): ?>
            <div class="pd-card">
                <h5 class="text-white font-weight-bold mb-3">
                    <i class="fas fa-history text-muted mr-2"></i>Jejak Forensik Keputusan Prioritas Portofolio (Audit Trail)
                </h5>

                <div class="table-responsive">
                    <table class="table table-dark table-sm table-bordered small mb-0">
                        <thead>
                            <tr class="text-muted text-uppercase">
                                <th>Timestamp</th>
                                <th>Plan Code</th>
                                <th>Transisi Tier</th>
                                <th>Alasan Pertimbangan Manusia</th>
                                <th>Pengambil Keputusan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tier_events as $ev): ?>
                                <tr>
                                    <td class="text-muted"><?= esc($ev['decided_at']) ?></td>
                                    <td class="text-info font-weight-bold"><?= esc($ev['plan_code']) ?></td>
                                    <td>
                                        <code><?= esc($ev['previous_tier']) ?></code> &rarr; <strong class="text-warning"><?= esc($ev['new_tier']) ?></strong>
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
             4. RATIFICATION & STATE TRANSITION
             ───────────────────────────────────────────────────────────────── -->
        <div class="pd-card">
            <h5 class="text-white font-weight-bold mb-3">
                <i class="fas fa-stamp text-warning mr-2"></i>Pengesahan & Tata Kelola Status Portofolio
            </h5>

            <?php
                $allowedPortTransitions = match($st) {
                    'PORTFOLIO_DRAFT'        => ['UNDER_PORTFOLIO_REVIEW' => 'UNDER_PORTFOLIO_REVIEW (Kirim untuk Telaah Portofolio)'],
                    'UNDER_PORTFOLIO_REVIEW' => ['PORTFOLIO_RATIFIED' => 'PORTFOLIO_RATIFIED (Ratifikasi Resmi Manajer Bagian Jaringan)'],
                    'PORTFOLIO_RATIFIED'     => ['PORTFOLIO_ARCHIVED' => 'PORTFOLIO_ARCHIVED (Arsipkan Portofolio)'],
                    default                  => [],
                };
            ?>

            <?php if (!empty($allowedPortTransitions)): ?>
                <form method="POST" action="<?= base_url('operational-planning/portfolios/transition/' . $portfolio['id']) ?>">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="small font-weight-bold text-muted">Aksi Transisi Status Portofolio:</label>
                                <select name="to_status" class="form-control bg-dark text-white border-secondary" required>
                                    <?php foreach ($allowedPortTransitions as $val => $lbl): ?>
                                        <option value="<?= $val ?>"><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="form-group">
                                <label class="small font-weight-bold text-warning">Alasan Pengesahan / Telaah Portofolio (Mandatory):</label>
                                <input type="text" name="ratification_rationale" class="form-control bg-dark text-white border-secondary" placeholder="Masukkan alasan pengesahan..." required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success font-weight-bold">
                        <i class="fas fa-check-circle mr-1"></i> Simpan Transisi Portofolio
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-secondary bg-dark border-secondary small text-muted mb-0">
                    <i class="fas fa-lock mr-1"></i> Portofolio ini telah diratifikasi dan terkunci (<code><?= esc($st) ?></code>).
                    <?php if (!empty($portfolio['governing_manager_name'])): ?>
                        <div class="text-white mt-1">Diratifikasi oleh: <strong><?= esc($portfolio['governing_manager_name']) ?></strong> (<?= esc($portfolio['ratified_at']) ?>)</div>
                        <div class="text-info mt-1">Alasan: <?= esc($portfolio['ratification_rationale']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Modal Set Priority Tier -->
<div class="modal fade" id="tierModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title font-weight-bold text-warning">
                    <i class="fas fa-edit mr-2"></i>Tetapkan Priority Tier Rencana Kerja
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="tierForm">
                <?= csrf_field() ?>
                <input type="hidden" name="portfolio_id" value="<?= (int)$portfolio['id'] ?>">

                <div class="modal-body">
                    <div class="form-group">
                        <label class="small font-weight-bold text-muted">Plan Code:</label>
                        <input type="text" id="modalPlanCode" class="form-control bg-secondary text-white border-dark" readonly>
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold text-white">Priority Tier:</label>
                        <select name="priority_tier" id="modalPriorityTier" class="form-control bg-dark text-white border-secondary" required>
                            <option value="TIER_1_IMMEDIATE_SCHEDULING">TIER_1_IMMEDIATE_SCHEDULING (Prioritas Atensi Utama)</option>
                            <option value="TIER_2_PLANNED_WINDOW">TIER_2_PLANNED_WINDOW (Prioritas Terjadwal Normal)</option>
                            <option value="TIER_3_DEFERRED_MAINTENANCE">TIER_3_DEFERRED_MAINTENANCE (Backlog Terkendali / Ditunda)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold text-warning">Alasan Pertimbangan Manusia (Mandatory Rationale):</label>
                        <input type="text" name="priority_rationale" id="modalPriorityRationale" class="form-control bg-dark text-white border-secondary" placeholder="Contoh: Titik kritis penyulang utama RSUD, jadwalkan tier 1" required>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Simpan Prioritas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openTierModal(itemId, planCode, currentTier, currentRationale) {
    document.getElementById('tierForm').action = '<?= base_url('operational-planning/portfolios/set-item-tier/') ?>/' + itemId;
    document.getElementById('modalPlanCode').value = planCode;
    if (currentTier && currentTier !== 'UNASSIGNED') {
        document.getElementById('modalPriorityTier').value = currentTier;
    }
    document.getElementById('modalPriorityRationale').value = currentRationale;
    $('#tierModal').modal('show');
}
</script>

<?= $this->endSection() ?>
