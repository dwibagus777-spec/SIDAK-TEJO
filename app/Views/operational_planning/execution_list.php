<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<style>
:root {
    --cc-bg: #0b111e;
    --cc-card-bg: rgba(18, 26, 43, 0.85);
    --cc-border: rgba(45, 62, 92, 0.6);
}

.ex-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.ex-card {
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
    <div class="ex-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-hard-hat text-success mr-2"></i>Controlled Field Execution & Progress Governance
                </h2>
                <small class="text-muted">
                    Wave 2 Phase OP-06 &bull; Pencatatan pelaksanaan fisik di lapangan, bukti foto before/after, progres riil, dan rekonsiliasi material
                </small>
            </div>
            <div>
                <a href="<?= base_url('operational-planning/authorizations') ?>" class="btn btn-outline-warning btn-sm mr-2">
                    <i class="fas fa-file-signature mr-1"></i> Authorizations Hub
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
             1. AUTHORIZED PACKAGES READY FOR FIELD WORK INITIATION
             ───────────────────────────────────────────────────────────────── -->
        <div class="ex-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="text-white font-weight-bold mb-0">
                        <i class="fas fa-certificate text-success mr-2"></i>Paket Otorisasi Bersegel SHA-256 (Siap Mulai Kerja)
                    </h5>
                    <small class="text-muted">Paket berstatus <code>EXECUTION_AUTHORIZED</code> yang siap diinisiasi pekerjaan lapangannya oleh manusia</small>
                </div>
                <span class="badge badge-success px-3 py-2 text-uppercase">
                    <?= count($readyPackages) ?> Paket Siap
                </span>
            </div>

            <?php if (!empty($readyPackages)): ?>
                <div class="table-responsive">
                    <table class="table table-dark table-sm table-bordered small mb-0">
                        <thead>
                            <tr class="text-muted text-uppercase">
                                <th>Auth Code</th>
                                <th>Plan Code</th>
                                <th>Penyulang & Seksi</th>
                                <th class="text-center">Jadwal Pelaksanaan</th>
                                <th>Pejabat Pengesah</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($readyPackages as $pkg): ?>
                                <tr>
                                    <td class="font-weight-bold text-warning">
                                        <code><?= esc($pkg['authorization_code']) ?></code>
                                    </td>
                                    <td class="text-info font-weight-bold"><?= esc($pkg['plan_code']) ?></td>
                                    <td>
                                        <strong class="text-white"><?= esc($pkg['feeder_name']) ?></strong> &bull; <?= esc($pkg['section_name']) ?>
                                    </td>
                                    <td class="text-center"><?= esc($pkg['scheduled_date']) ?> (<?= esc($pkg['scheduled_window']) ?>)</td>
                                    <td class="text-muted"><?= esc($pkg['authorizing_official_name']) ?></td>
                                    <td class="text-center">
                                        <a href="<?= base_url('operational-planning/executions/initiate/' . $pkg['id']) ?>" class="btn btn-sm btn-success font-weight-bold">
                                            <i class="fas fa-play mr-1"></i> Inisiasi Rekaman Lapangan
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="small text-muted mb-0">
                    <i class="fas fa-info-circle mr-1"></i> Tidak ada paket otorisasi bersegel baru yang belum memiliki rekaman eksekusi aktif.
                </p>
            <?php endif; ?>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             2. FIELD EXECUTION RECORDS LIST
             ───────────────────────────────────────────────────────────────── -->
        <div class="ex-card">
            <h5 class="text-white font-weight-bold mb-3">
                <i class="fas fa-clipboard-list text-info mr-2"></i>Daftar Rekaman Eksekusi Lapangan (OP-06)
            </h5>

            <div class="table-responsive">
                <table class="table table-dark table-hover table-bordered small mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase">
                            <th>Execution Code</th>
                            <th>Plan Code</th>
                            <th>Penyulang & Lokasi</th>
                            <th class="text-center">Progres Fisik</th>
                            <th class="text-center">Status Eksekusi</th>
                            <th>Pengawas Lapangan</th>
                            <th class="text-center">Waktu Mulai</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($executions)): ?>
                            <?php foreach ($executions as $ex): ?>
                                <tr>
                                    <td class="font-weight-bold text-info">
                                        <code><?= esc($ex['execution_code']) ?></code>
                                    </td>
                                    <td class="text-warning font-weight-bold"><?= esc($ex['plan_code']) ?></td>
                                    <td>
                                        <strong class="text-white"><?= esc($ex['feeder_name']) ?></strong> &bull; <?= esc($ex['section_name']) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="progress" style="height: 16px; background-color: #1e293b;">
                                            <div class="progress-bar <?= (float)$ex['progress_percentage'] >= 100 ? 'bg-success' : 'bg-info' ?> progress-bar-striped font-size-xs" role="progressbar" style="width: <?= (float)$ex['progress_percentage'] ?>%;">
                                                <?= (float)$ex['progress_percentage'] ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                            $st = $ex['execution_status'];
                                            $badge = match($st) {
                                                'WORK_IN_PROGRESS'                  => 'badge-primary',
                                                'WORK_COMPLETED_PENDING_ACCEPTANCE' => 'badge-success',
                                                'WORK_PAUSED_SAFETY_HOLD'           => 'badge-danger',
                                                'WORK_ABORTED_FIELD_CONSTRAINTS'    => 'badge-dark',
                                                default                             => 'badge-warning',
                                            };
                                        ?>
                                        <span class="badge <?= $badge ?> text-uppercase"><?= str_replace('_', ' ', $st) ?></span>
                                    </td>
                                    <td class="text-muted"><?= esc($ex['field_supervisor_name'] ?? '-') ?></td>
                                    <td class="text-center text-muted"><?= esc($ex['work_started_at'] ?? 'Belum Dimulai') ?></td>
                                    <td class="text-center">
                                        <a href="<?= base_url('operational-planning/executions/detail/' . $ex['id']) ?>" class="btn btn-sm btn-outline-info font-weight-bold">
                                            <i class="fas fa-tachometer-alt mr-1"></i> Buka Lapangan
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    Belum ada rekaman eksekusi lapangan saat ini.
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
