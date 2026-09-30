<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Buat Work Package<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="container-xl">
    <div class="page-header d-print-none mb-3">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle text-muted">
                    <a href="<?= site_url('remediation') ?>" class="text-muted">Remediasi</a>
                    <span class="mx-1">/</span> Buat Work Package
                </div>
                <h2 class="page-title">
                    <i class="fas fa-plus-circle text-success me-2"></i>Buat Work Package Remediasi
                </h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <a href="<?= site_url('remediation') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    <!-- Phase 2A Notice Banner -->
    <?php if (!empty($phase2a_notice)): ?>
    <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
        <i class="fas fa-lock fa-lg me-3 flex-shrink-0"></i>
        <div>
            <strong>Phase 2A — Review Mode:</strong>
            Form ini ditampilkan untuk keperluan review UI/UX.
            Tombol submit <strong>belum aktif</strong> — akan diaktifkan pada Phase 2B setelah review disetujui.
        </div>
    </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-plus me-1"></i> Form Work Package Baru</h3>
                </div>
                <div class="card-body">
                    <!-- Form display only — POST action not wired in Phase 2A -->
                    <form id="form-create-wp" autocomplete="off">

                        <div class="mb-3">
                            <label for="wp_title" class="form-label required">Judul Work Package</label>
                            <input type="text" id="wp_title" class="form-control" name="title"
                                   placeholder="Contoh: Remediasi Gangguan Penyulang Mawar 2026-09"
                                   disabled>
                            <div class="form-hint">Judul singkat dan deskriptif untuk paket remediasi ini.</div>
                        </div>

                        <div class="mb-3">
                            <label for="wp_description" class="form-label">Deskripsi</label>
                            <textarea id="wp_description" class="form-control" name="description" rows="3"
                                      placeholder="Detail latar belakang, scope, dan tujuan remediasi..."
                                      disabled></textarea>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="wp_priority" class="form-label required">Prioritas</label>
                                <select id="wp_priority" class="form-select" name="priority" disabled>
                                    <option value="LOW">LOW — Rendah</option>
                                    <option value="MEDIUM" selected>MEDIUM — Sedang</option>
                                    <option value="HIGH">HIGH — Tinggi</option>
                                    <option value="EMERGENCY">EMERGENCY — Darurat</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="wp_feeder_code" class="form-label">Kode Feeder / Penyulang</label>
                                <input type="text" id="wp_feeder_code" class="form-control" name="feeder_code"
                                       placeholder="Contoh: PNY-MAW-001" disabled>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="wp_target_completion_date" class="form-label">Target Tanggal Selesai</label>
                            <input type="date" id="wp_target_completion_date" class="form-control" name="target_completion_date" disabled>
                        </div>

                        <div class="mb-3">
                            <label for="wp_client_operation_uuid" class="form-label">Client Operation UUID</label>
                            <input type="text" id="wp_client_operation_uuid" class="form-control font-monospace" name="client_operation_uuid"
                                   placeholder="UUID unik untuk idempotency (auto-generated jika dikosongkan)"
                                   disabled>
                            <div class="form-hint text-muted">
                                B81-G03: Digunakan untuk mencegah duplikat pada kondisi retry/replay.
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- Disabled submit button — Phase 2A -->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-warning" disabled title="Tidak aktif di Phase 2A">
                                <i class="fas fa-lock me-1"></i> Buat Work Package
                            </button>
                            <a href="<?= site_url('remediation') ?>" class="btn btn-outline-secondary">
                                Batal
                            </a>
                        </div>

                        <p class="text-muted small mt-2 mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Tombol ini akan aktif pada Phase 2B setelah review UI/RBAC disetujui.
                        </p>
                    </form>
                </div>
            </div>

            <!-- FSM Reference Card -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-diagram-project me-1"></i> Alur FSM Work Package (9 State)</h3>
                </div>
                <div class="card-body py-2">
                    <?php
                    use App\Services\RemediationWorkPackageService as WPS;
                    $fsm = WPS::ALL_STATUSES;
                    foreach ($fsm as $s):
                    ?>
                    <span class="badge <?= WPS::statusBadgeClass($s) ?> me-1 mb-1"><?= esc(WPS::statusLabel($s)) ?></span>
                    <?php endforeach; ?>
                    <p class="text-muted small mt-2 mb-0">
                        Setiap transisi melewati validasi FSM + RBAC + finding revalidation di sisi server.
                    </p>
                </div>
            </div>
        </div>
    </div>

</div>

<?= $this->endSection() ?>
