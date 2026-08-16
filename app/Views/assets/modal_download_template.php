<!-- Modal Download Template Master Asset -->
<div class="modal fade" id="modalDownloadTemplate" tabindex="-1" aria-labelledby="modalDownloadTemplateLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-primary text-white rounded-top-4 py-3">
                <h5 class="modal-title fw-bold font-outfit" id="modalDownloadTemplateLabel">
                    <i class="fas fa-file-excel me-2"></i> Download Template Import Asset
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= site_url('master-assets/template') ?>" method="GET" target="_blank" id="formDownloadTemplate">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">
                        Pilih kriteria asset yang akan diimport. Template akan secara otomatis menyesuaikan konteks UP3, ULP, Penyulang, dan Jenis Asset yang Anda pilih.
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
                            <i class="fas fa-network-wired text-primary me-1"></i> ULP (Unit Layanan Pelanggan) <span class="text-danger">*</span>
                        </label>
                        <select class="form-select fw-bold" id="template_ulp_id" name="ulp_id" required>
                            <option value="">-- Pilih ULP --</option>
                            <?php if (!empty($ulps) && is_array($ulps)): ?>
                                <?php foreach ($ulps as $ulp): ?>
                                    <option value="<?= $ulp['id'] ?>"><?= esc($ulp['nama_ulp']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Filter Penyulang (Cascading) -->
                    <div class="mb-3">
                        <label for="template_penyulang_id" class="form-label fw-bold small text-secondary">
                            <i class="fas fa-bolt text-primary me-1"></i> Penyulang / Feeder <span class="text-danger">*</span>
                        </label>
                        <select class="form-select fw-bold" id="template_penyulang_id" name="penyulang_id" required disabled>
                            <option value="">-- Pilih ULP Terlebih Dahulu --</option>
                        </select>
                    </div>

                    <!-- Filter Jenis Asset -->
                    <div class="mb-3">
                        <label for="template_jenis_asset" class="form-label fw-bold small text-secondary">
                            <i class="fas fa-cubes text-primary me-1"></i> Jenis Asset PLN <span class="text-danger">*</span>
                        </label>
                        <?php 
                        $jenisList = ['JTM', 'Gardu', 'Trafo', 'Kubikel', 'LBS', 'LBSM', 'Recloser', 'Sectionalizer', 'Section', 'Penyulang', 'JTR', 'PHB', 'APP', 'Meter', 'Grounding'];
                        ?>
                        <select class="form-select fw-bold text-primary" id="template_jenis_asset" name="jenis_asset" required>
                            <?php foreach ($jenisList as $j): ?>
                                <option value="<?= $j ?>" <?= $j === 'Gardu' ? 'selected' : '' ?>><?= $j ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Format Template Selector -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">
                            <i class="fas fa-file-export text-primary me-1"></i> Format Template <span class="text-danger">*</span>
                        </label>
                        <div class="d-flex gap-3">
                            <div class="form-check form-check-inline border rounded-3 p-2 px-3 flex-fill bg-light">
                                <input class="form-check-input" type="radio" name="format" id="format_xlsx" value="xlsx" checked>
                                <label class="form-check-input-label fw-bold text-dark cursor-pointer ms-1" for="format_xlsx">
                                    <i class="fas fa-file-excel text-success me-1"></i> Excel (.xlsx)
                                </label>
                            </div>
                            <div class="form-check form-check-inline border rounded-3 p-2 px-3 flex-fill bg-light">
                                <input class="form-check-input" type="radio" name="format" id="format_csv" value="csv">
                                <label class="form-check-input-label fw-bold text-dark cursor-pointer ms-1" for="format_csv">
                                    <i class="fas fa-file-csv text-info me-1"></i> CSV (.csv)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light rounded-bottom-4 py-3">
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-success btn-sm font-weight-bold rounded-pill px-4 shadow-sm" id="btnDownloadTemplateSubmit">
                        <i class="fas fa-download me-1"></i> Download Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ulpSelect = document.getElementById('template_ulp_id');
    const penyulangSelect = document.getElementById('template_penyulang_id');
    const formDownload = document.getElementById('formDownloadTemplate');

    if (ulpSelect && penyulangSelect) {
        ulpSelect.addEventListener('change', function() {
            const ulpId = this.value;
            penyulangSelect.innerHTML = '<option value="">-- Memuat Penyulang... --</option>';
            penyulangSelect.disabled = true;

            if (!ulpId) {
                penyulangSelect.innerHTML = '<option value="">-- Pilih ULP Terlebih Dahulu --</option>';
                return;
            }

            fetch('<?= site_url('master-assets/penyulang-by-ulp/') ?>' + ulpId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                let options = '<option value="">-- Pilih Penyulang --</option>';
                if (Array.isArray(data) && data.length > 0) {
                    data.forEach(p => {
                        options += `<option value="${p.id}">${p.nama_penyulang}</option>`;
                    });
                    penyulangSelect.disabled = false;
                } else {
                    options = '<option value="">-- Tidak ada penyulang --</option>';
                    penyulangSelect.disabled = true;
                }
                penyulangSelect.innerHTML = options;
            })
            .catch(err => {
                console.error('Error fetching penyulang:', err);
                penyulangSelect.innerHTML = '<option value="">-- Gagal memuat penyulang --</option>';
                penyulangSelect.disabled = true;
            });
        });
    }

    if (formDownload) {
        formDownload.addEventListener('submit', function(e) {
            const ulpId = ulpSelect ? ulpSelect.value : '';
            const penyulangId = penyulangSelect ? penyulangSelect.value : '';

            if (ulpSelect && !ulpId) {
                e.preventDefault();
                alert('Silakan pilih ULP terlebih dahulu!');
                return false;
            }
            if (penyulangSelect && !penyulangId) {
                e.preventDefault();
                alert('Silakan pilih Penyulang terlebih dahulu!');
                return false;
            }
        });
    }
});
</script>
