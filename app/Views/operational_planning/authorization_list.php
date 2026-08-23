<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<style>
:root {
    --cc-bg: #0b111e;
    --cc-card-bg: rgba(18, 26, 43, 0.85);
    --cc-border: rgba(45, 62, 92, 0.6);
}

.auth-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.auth-card {
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
    <div class="auth-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-file-signature text-warning mr-2"></i>Execution Readiness Gate & Work Authorization
                </h2>
                <small class="text-muted">
                    Wave 2 Phase OP-05 &bull; Verifikasi 4 dimensi kesiapan eksekusi dan penerbitan paket otorisasi kerja bersegel SHA-256
                </small>
            </div>
            <div>
                <a href="<?= base_url('operational-planning/scheduling') ?>" class="btn btn-outline-success btn-sm mr-2">
                    <i class="fas fa-calendar-alt mr-1"></i> Scheduling Hub
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
             1. APPROVED SLOTS READY FOR WORK AUTHORIZATION
             ───────────────────────────────────────────────────────────────── -->
        <div class="auth-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="text-white font-weight-bold mb-0">
                        <i class="fas fa-calendar-check text-info mr-2"></i>Slot Jadwal Teratifikasi (Siap Paket Otorisasi)
                    </h5>
                    <small class="text-muted">Slot pekerjaan dari skenario yang disahkan (<code>SCENARIO_APPROVED</code>) yang siap diverifikasi kesiapannya</small>
                </div>
                <span class="badge badge-info px-3 py-2 text-uppercase">
                    <?= count($readySlots) ?> Slot Siap
                </span>
            </div>

            <?php if (!empty($readySlots)): ?>
                <div class="table-responsive">
                    <table class="table table-dark table-sm table-bordered small mb-0">
                        <thead>
                            <tr class="text-muted text-uppercase">
                                <th>Plan Code</th>
                                <th>Scenario Code</th>
                                <th>Penyulang & Seksi</th>
                                <th class="text-center">Tanggal & Jam</th>
                                <th class="text-center">Metode</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($readySlots as $rs): ?>
                                <tr>
                                    <td class="font-weight-bold text-warning">
                                        <code><?= esc($rs['plan_code']) ?></code>
                                    </td>
                                    <td class="text-info"><?= esc($rs['scenario_code']) ?></td>
                                    <td>
                                        <strong class="text-white"><?= esc($rs['feeder_name']) ?></strong> &bull; <?= esc($rs['section_name']) ?>
                                    </td>
                                    <td class="text-center">
                                        <?= esc($rs['scheduled_date']) ?> (<?= substr($rs['scheduled_start_time'], 0, 5) ?> - <?= substr($rs['scheduled_end_time'], 0, 5) ?>)
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty($rs['outage_required'])): ?>
                                            <span class="badge badge-danger">PADAM</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">PDKB</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= base_url('operational-planning/authorizations/generate/' . $rs['id']) ?>" class="btn btn-sm btn-warning font-weight-bold">
                                            <i class="fas fa-file-signature mr-1"></i> Buat Paket Otorisasi
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="small text-muted mb-0">
                    <i class="fas fa-info-circle mr-1"></i> Tidak ada slot jadwal baru yang belum memiliki paket otorisasi aktif.
                </p>
            <?php endif; ?>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             2. WORK AUTHORIZATION PACKAGES LIST
             ───────────────────────────────────────────────────────────────── -->
        <div class="auth-card">
            <h5 class="text-white font-weight-bold mb-3">
                <i class="fas fa-stamp text-warning mr-2"></i>Daftar Paket Otorisasi Kerja Resmi (OP-05)
            </h5>

            <div class="table-responsive">
                <table class="table table-dark table-hover table-bordered small mb-0">
                    <thead>
                        <tr class="text-muted text-uppercase">
                            <th>Authorization Code</th>
                            <th>Plan Code</th>
                            <th>Penyulang & Lokasi</th>
                            <th class="text-center">Tanggal Jadwal</th>
                            <th class="text-center">Skor Kesiapan</th>
                            <th class="text-center">Status Otorisasi</th>
                            <th>Pejabat Pemberi Izin</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($authorizations)): ?>
                            <?php foreach ($authorizations as $pkg): ?>
                                <tr>
                                    <td class="font-weight-bold text-warning">
                                        <code><?= esc($pkg['authorization_code']) ?></code>
                                    </td>
                                    <td class="text-info font-weight-bold">
                                        <?= esc($pkg['plan_code']) ?>
                                    </td>
                                    <td>
                                        <strong class="text-white"><?= esc($pkg['feeder_name']) ?></strong> &bull; <?= esc($pkg['section_name']) ?>
                                    </td>
                                    <td class="text-center"><?= esc($pkg['scheduled_date']) ?></td>
                                    <td class="text-center font-weight-bold <?= (float)$pkg['readiness_score'] >= 100 ? 'text-success' : 'text-warning' ?>">
                                        <?= number_format((float)$pkg['readiness_score'], 1) ?>%
                                    </td>
                                    <td class="text-center">
                                        <?php
                                            $st = $pkg['authorization_status'];
                                            $badge = match($st) {
                                                'EXECUTION_AUTHORIZED'     => 'badge-success',
                                                'READINESS_VERIFIED'      => 'badge-info',
                                                'REVISION_REQUIRED'       => 'badge-danger',
                                                'AUTHORIZATION_REVOKED'   => 'badge-dark',
                                                'AUTHORIZATION_SUPERSEDED'=> 'badge-secondary',
                                                default                   => 'badge-warning',
                                            };
                                        ?>
                                        <span class="badge <?= $badge ?> text-uppercase"><?= str_replace('_', ' ', $st) ?></span>
                                    </td>
                                    <td class="text-muted"><?= esc($pkg['authorizing_official_name'] ?? 'Belum Disahkan') ?></td>
                                    <td class="text-center">
                                        <a href="<?= base_url('operational-planning/authorizations/detail/' . $pkg['id']) ?>" class="btn btn-sm btn-outline-warning font-weight-bold">
                                            <i class="fas fa-search-plus mr-1"></i> Buka Paket
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    Belum ada paket otorisasi kerja yang dibuat saat ini.
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
