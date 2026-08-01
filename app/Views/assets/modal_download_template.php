<!-- Modal Download Template Master Asset -->
<div class="modal fade" id="modalDownloadTemplate" tabindex="-1" aria-labelledby="modalDownloadTemplateLabel" aria-hidden="true" style="z-index: 1060 !important;">
    <div class="modal-dialog modal-dialog-centered" style="z-index: 1061 !important;">
        <div class="modal-content border-0 shadow rounded-4" style="z-index: 1062 !important;">
            <div class="modal-header bg-primary text-white rounded-top-4 py-3">
                <h5 class="modal-title fw-bold font-outfit" id="modalDownloadTemplateLabel">
                    <i class="fas fa-file-excel me-2"></i> Download Template Import Asset
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= site_url('master-assets/template') ?>" method="GET" target="_blank">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">
                        Pilih kriteria asset yang akan diimport. Template Excel akan secara otomatis menyesuaikan kolom header dan petunjuk sesuai Jenis Asset yang Anda pilih.
                    </p>

                    <!-- Filter UP3 -->
                    <div class="mb-3">
                        <label for="template_up3" class="form-label fw-bold small text-secondary">
                            <i class="fas fa-building text-primary me-1"></i> UP3 (Unit Pelaksana Pelayanan Pelanggan)
                        </label>
                        <input type="text" class="form-control bg-light fw-bold text-dark" id="template_up3" name="up3" value="UP3 Sidoarjo" readonly>
                    </div>

                    <!-- Filter ULP -->
                    <div class="mb-3">
                        <label for="template_ulp_id" class="form-label fw-bold small text-secondary">
                            <i class="fas fa-network-wired text-primary me-1"></i> ULP (Unit Layanan Pelanggan)
                        </label>
                        <select class="form-select" id="template_ulp_id" name="ulp_id" style="position: relative; z-index: 1063 !important; pointer-events: auto !important;">
                            <option value="">-- Semua ULP / General --</option>
                            <?php if (!empty($ulps) && is_array($ulps)): ?>
                                <?php foreach ($ulps as $ulp): ?>
                                    <option value="<?= $ulp['id'] ?>"><?= esc($ulp['nama_ulp']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Filter Jenis Asset -->
                    <div class="mb-3">
                        <label for="template_jenis_asset" class="form-label fw-bold small text-secondary">
                            <i class="fas fa-cubes text-primary me-1"></i> Jenis Asset PLN <span class="text-danger">*</span>
                        </label>
                        <?php 
                        $jenisList = ['Gardu', 'Trafo', 'Kubikel', 'LBS', 'Recloser', 'Section', 'Penyulang', 'Tiang', 'JTM', 'JTR', 'PHB', 'APP', 'Meter', 'Grounding'];
                        ?>
                        <select class="form-select fw-bold text-primary" id="template_jenis_asset" name="jenis_asset" required style="position: relative; z-index: 1063 !important; pointer-events: auto !important;">
                            <?php foreach ($jenisList as $j): ?>
                                <option value="<?= $j ?>" <?= $j === 'Gardu' ? 'selected' : '' ?>><?= $j ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer bg-light rounded-bottom-4 py-3">
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-success btn-sm font-weight-bold rounded-pill px-4 shadow-sm">
                        <i class="fas fa-download me-1"></i> Download Template (.xlsx)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
