<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Audit & Perapian Relasi Hierarki Jaringan<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Audit Relasi Hierarki: Data Unlinked / Unassigned<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item"><a href="<?= site_url('master-uid') ?>">Master UID</a></li>
<li class="breadcrumb-item active">Audit Data Unlinked</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <!-- Stat Summary Cards -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 rounded-4 bg-white">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="rounded-circle bg-danger-subtle text-danger p-3 me-3">
                    <i class="fas fa-bolt fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1 text-uppercase small fw-bold">Aset Tanpa Penyulang</h6>
                    <h3 class="mb-0 fw-bold <?= $totalUnlinkedAssets > 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($totalUnlinkedAssets) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 rounded-4 bg-white">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="rounded-circle bg-warning-subtle text-warning p-3 me-3">
                    <i class="fas fa-code-branch fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1 text-uppercase small fw-bold">Penyulang Tanpa GI / ULP</h6>
                    <h3 class="mb-0 fw-bold"><?= count($unlinkedFeedersGi) + count($unlinkedFeedersUlp) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 rounded-4 bg-white">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="rounded-circle bg-info-subtle text-info p-3 me-3">
                    <i class="fas fa-sitemap fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1 text-uppercase small fw-bold">ULP / GI Tanpa UP</h6>
                    <h3 class="mb-0 fw-bold"><?= count($unlinkedUlps) + count($unlinkedGi) ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- 1. Assets Without Feeder -->
    <div class="col-12 mb-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center rounded-top-4 border-bottom">
                <div>
                    <h5 class="card-title mb-0 font-weight-bold text-dark">
                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                        Aset Tanpa Penyulang (<?= number_format($totalUnlinkedAssets) ?> record)
                    </h5>
                    <small class="text-muted">Aset-aset ini belum terikat ke penyulang manapun. Pilih penyulang yang sesuai untuk menautkan.</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted uppercase small sticky-top">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Asset ID / Tag</th>
                                <th>Nama Aset</th>
                                <th>Jenis Aset</th>
                                <th>Koordinat GIS</th>
                                <th class="pe-4" style="width: 320px;">Tautkan ke Penyulang</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($unlinkedAssets)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-success fw-bold">
                                        <i class="fas fa-check-circle me-1"></i> Semua aset telah terhubung ke penyulang dengan benar.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($unlinkedAssets as $asset): ?>
                                    <tr id="row-asset-<?= $asset['id'] ?>">
                                        <td class="ps-4 font-monospace small"><?= $asset['id'] ?></td>
                                        <td class="font-monospace fw-bold text-primary"><?= esc($asset['asset_id']) ?></td>
                                        <td><?= esc($asset['nama_asset']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= esc($asset['jenis_asset']) ?></span></td>
                                        <td class="small font-monospace text-muted"><?= esc($asset['latitude']) ?>, <?= esc($asset['longitude']) ?></td>
                                        <td class="pe-4">
                                            <div class="input-group input-group-sm">
                                                <select class="form-select form-select-sm" id="select-feeder-<?= $asset['id'] ?>">
                                                    <option value="">-- Pilih Penyulang --</option>
                                                    <?php foreach ($penyulangs as $p): ?>
                                                        <option value="<?= $p['id'] ?>"><?= esc($p['nama_penyulang']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn btn-primary btn-sm btn-link-asset" data-asset-id="<?= $asset['id'] ?>" type="button">
                                                    <i class="fas fa-link"></i> Tautkan
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Unlinked Feeders -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 font-weight-bold text-dark">
                    <i class="fas fa-charging-station text-warning me-2"></i>
                    Penyulang Belum Terhubung GI (<?= count($unlinkedFeedersGi) ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small">
                            <tr>
                                <th class="ps-3">Kode</th>
                                <th>Nama Penyulang</th>
                                <th class="pe-3">Tautkan GI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($unlinkedFeedersGi)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-success small">
                                        <i class="fas fa-check-circle me-1"></i> Semua penyulang terhubung GI.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($unlinkedFeedersGi as $f): ?>
                                    <tr id="row-feeder-<?= $f['id'] ?>">
                                        <td class="ps-3 font-monospace small"><?= esc($f['kode_penyulang']) ?></td>
                                        <td class="fw-bold small"><?= esc($f['nama_penyulang']) ?></td>
                                        <td class="pe-3">
                                            <div class="input-group input-group-sm">
                                                <select class="form-select form-select-sm" id="select-gi-<?= $f['id'] ?>">
                                                    <option value="">-- Pilih GI --</option>
                                                    <?php foreach ($garduInduk as $g): ?>
                                                        <option value="<?= $g['id'] ?>"><?= esc($g['nama_gi']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn btn-primary btn-sm btn-link-gi" data-feeder-id="<?= $f['id'] ?>" type="button">
                                                    <i class="fas fa-link"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Unlinked ULPs & GIs to UP -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 font-weight-bold text-dark">
                    <i class="fas fa-sitemap text-info me-2"></i>
                    ULP Belum Terhubung UP (<?= count($unlinkedUlps) ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small">
                            <tr>
                                <th class="ps-3">Kode</th>
                                <th>Nama ULP</th>
                                <th class="pe-3">Tautkan ke UP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($unlinkedUlps)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-success small">
                                        <i class="fas fa-check-circle me-1"></i> Semua ULP terhubung ke Unit Pelaksana.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($unlinkedUlps as $u): ?>
                                    <tr id="row-ulp-<?= $u['id'] ?>">
                                        <td class="ps-3 font-monospace small"><?= esc($u['kode_ulp']) ?></td>
                                        <td class="fw-bold small"><?= esc($u['nama_ulp']) ?></td>
                                        <td class="pe-3">
                                            <div class="input-group input-group-sm">
                                                <select class="form-select form-select-sm" id="select-up3-<?= $u['id'] ?>">
                                                    <option value="">-- Pilih UP --</option>
                                                    <?php foreach ($up3s as $up): ?>
                                                        <option value="<?= $up['id'] ?>"><?= esc($up['nama_up3']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn btn-primary btn-sm btn-link-ulp" data-ulp-id="<?= $u['id'] ?>" type="button">
                                                    <i class="fas fa-link"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Tautkan Aset ke Penyulang
    document.querySelectorAll('.btn-link-asset').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var assetId = this.getAttribute('data-asset-id');
            var select = document.getElementById('select-feeder-' + assetId);
            var feederId = select.value;

            if (!feederId) {
                alert('Silakan pilih penyulang terlebih dahulu.');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            var formData = new FormData();
            formData.append('asset_id', assetId);
            formData.append('penyulang_id', feederId);
            formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= site_url('master-hierarchy/assign-asset-feeder') ?>', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    var row = document.getElementById('row-asset-' + assetId);
                    if (row) {
                        row.classList.add('table-success');
                        setTimeout(function() { row.remove(); }, 800);
                    }
                } else {
                    alert(data.message || 'Gagal menautkan aset.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-link"></i> Tautkan';
                }
            })
            .catch(err => {
                alert('Terjadi kesalahan koneksi.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-link"></i> Tautkan';
            });
        });
    });

    // 2. Tautkan Penyulang ke GI
    document.querySelectorAll('.btn-link-gi').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var feederId = this.getAttribute('data-feeder-id');
            var giId = document.getElementById('select-gi-' + feederId).value;
            if (!giId) {
                alert('Pilih GI terlebih dahulu.');
                return;
            }

            btn.disabled = true;
            var formData = new FormData();
            formData.append('penyulang_id', feederId);
            formData.append('gi_id', giId);
            formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= site_url('master-hierarchy/assign-feeder-gi') ?>', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    var row = document.getElementById('row-feeder-' + feederId);
                    if (row) row.remove();
                } else {
                    alert(data.message || 'Gagal menautkan.');
                    btn.disabled = false;
                }
            });
        });
    });

    // 3. Tautkan ULP ke UP3
    document.querySelectorAll('.btn-link-ulp').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var ulpId = this.getAttribute('data-ulp-id');
            var up3Id = document.getElementById('select-up3-' + ulpId).value;
            if (!up3Id) {
                alert('Pilih UP terlebih dahulu.');
                return;
            }

            btn.disabled = true;
            var formData = new FormData();
            formData.append('ulp_id', ulpId);
            formData.append('up3_id', up3Id);
            formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

            fetch('<?= site_url('master-hierarchy/assign-ulp-up3') ?>', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    var row = document.getElementById('row-ulp-' + ulpId);
                    if (row) row.remove();
                } else {
                    alert(data.message || 'Gagal menautkan.');
                    btn.disabled = false;
                }
            });
        });
    });
});
</script>
<?= $this->endSection() ?>
