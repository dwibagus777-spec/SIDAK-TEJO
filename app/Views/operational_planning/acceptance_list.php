<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<style>
:root {
    --cc-bg: #0b111e;
    --cc-card-bg: rgba(18, 26, 43, 0.85);
    --cc-border: rgba(45, 62, 92, 0.6);
}

.acc-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.acc-card {
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
    <div class="acc-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-award text-success mr-2"></i>Work Acceptance & Quality Assurance Governance
                </h2>
                <small class="text-muted">
                    Wave 2 Phase OP-07 &bull; Inspeksi mutu independen 4 dimensi, penerbitan sertifikat bersegel SHA-256, dan penutupan resmi pekerjaan
                </small>
            </div>
            <div>
                <a href="<?= base_url('operational-planning/executions') ?>" class="btn btn-outline-info btn-sm mr-2">
                    <i class="fas fa-hard-hat mr-1"></i> Field Executions Hub
                </a>
                <a href="<?= base_url('operational-planning/workspace') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Workspace
                </a>
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

        <!-- ─────────────────────────────────────────────────────────────────
             1. COMPLETED FIELD EXECUTIONS READY FOR ACCEPTANCE REVIEW
             ───────────────────────────────────────────────────────────────── -->
        <div class="acc-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="text-white font-weight-bold mb-0">
                        <i class="fas fa-clipboard-check text-info mr-2"></i>Pekerjaan Lapangan Selesai (Siap Audit Mutu)
                    </h5>
                    <small class="text-muted">Rekaman eksekusi berstatus <code>WORK_COMPLETED_PENDING_ACCEPTANCE</code> yang menunggu audit mutu independen</small>
                </div>
                <span class="badge badge-info px-3 py-2 text-uppercase">
                    <?= count($readyExecutions) ?> Pekerjaan Selesai
                </span>
            </div>

            <?php if (!empty($readyExecutions)): ?>
                <div class="table-responsive">
                    <table class="table table-dark table-sm table-bordered small mb-0">
                        <thead>
                            <tr class="text-muted text-uppercase">
                                <th>Execution Code</th>
                                <th>Plan Code</th>
                                <th>Penyulang & Seksi</th>
                                <th>Pengawas Deklarasi</th>
                                <th class="text-center">Waktu Selesai</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($readyExecutions as $rex): ?>
                                <tr>
                                    <td class="font-weight-bold text-info">
                                        <code><?= esc($rex['execution_code']) ?></code>
                                    </td>
                                    <td class="text-warning font-weight-bold"><?= esc($rex['plan_code']) ?></td>
                                    <td>
                                        <strong class="text-white"><?= esc($rex['feeder_name']) ?></strong> &bull; <?= esc($rex['section_name']) ?>
                                    </td>
                                    <td class="text-muted"><?= esc($rex['field_completion_declared_by'] ?? 'Pengawas Lapangan') ?></td>
                                    <td class="text-center text-muted"><?= esc($rex['work_completed_at']) ?></td>
                                    <td class="text-center">
                                        <a href="<?= base_url('operational-planning/acceptances/initiate/' . $rex['id']) ?>" class="btn btn-sm btn-info font-weight-bold">
                                            <i class="fas fa-search mr-1"></i> Mulai Audit Mutu
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="small text-muted mb-0">
                    <i class="fas fa-info-circle mr-1"></i> Tidak ada pekerjaan selesai baru yang belum diaudit mutunya.
                </p>
            <?php endif; ?>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             2. WORK ACCEPTANCES LIST
             ───────────────────────────────────────────────────────────────── -->
        <div class="acc-card">
            <h5 class="text-white font-weight-bold mb-3">
                <i class="fas fa-stamp text-success mr-2"></i>Daftar Sertifikat Penerimaan & Penutupan Kerja (OP-07)
            </h5>

            <div class="table-responsive">
                <table class="table table-dark table-hover table-bordered small mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase">
                            <th>Acceptance Code</th>
                            <th>Execution Code</th>
                            <th>Penyulang & Lokasi</th>
                            <th class="text-center">Skor Mutu (QA)</th>
                            <th class="text-center">Status Penerimaan</th>
                            <th>Inspektur Mutu</th>
                            <th>Manajer Penutup</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($acceptances)): ?>
                            <?php foreach ($acceptances as $acc): ?>
                                <tr>
                                    <td class="font-weight-bold text-success">
                                        <code><?= esc($acc['acceptance_code']) ?></code>
                                    </td>
                                    <td class="text-info"><?= esc($acc['execution_code']) ?></td>
                                    <td>
                                        <strong class="text-white"><?= esc($acc['feeder_name']) ?></strong> &bull; <?= esc($acc['section_name']) ?>
                                    </td>
                                    <td class="text-center font-weight-bold <?= (float)$acc['quality_score'] >= 85 ? 'text-success' : 'text-danger' ?>">
                                        <?= number_format((float)$acc['quality_score'], 1) ?>%
                                    </td>
                                    <td class="text-center">
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
                                        <span class="badge <?= $badge ?> text-uppercase"><?= str_replace('_', ' ', $st) ?></span>
                                    </td>
                                    <td class="text-muted"><?= esc($acc['accepting_inspector_name'] ?? 'Belum Diterima') ?></td>
                                    <td class="text-muted"><?= esc($acc['closing_manager_name'] ?? 'Belum Ditutup') ?></td>
                                    <td class="text-center">
                                        <a href="<?= base_url('operational-planning/acceptances/detail/' . $acc['id']) ?>" class="btn btn-sm btn-outline-success font-weight-bold">
                                            <i class="fas fa-certificate mr-1"></i> Buka Sertifikat
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    Belum ada rekaman penerimaan pekerjaan saat ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?= $this->endSection() ?>
