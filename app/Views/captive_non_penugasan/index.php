<?= $this->extend('layouts/admin') ?>
<?= $this->section('title') ?>Captive Non Penugasan<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Captive Non Penugasan<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <?php if (session()->getFlashdata('success')): ?><div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
    <?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
    <div class="row g-3">
        <div class="col-xl-5"><div class="card shadow-sm"><div class="card-header fw-bold d-flex justify-content-between align-items-center"><span>Catat Potensi Coating / Temuan Manual</span><?php if (!empty($returnTo) && $returnTo === 'gis' && !empty($returnPenyulangId)): ?><a href="<?= site_url('gis?penyulang_id=' . (int)$returnPenyulangId . (!empty($returnLat) && !empty($returnLng) ? '&lat=' . urlencode($returnLat) . '&lng=' . urlencode($returnLng) : '') . (!empty($returnZoom) ? '&zoom=' . urlencode($returnZoom) : '') . (!empty($returnLayers) ? '&layers=' . urlencode($returnLayers) : '')) ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-arrow-left me-1"></i> Kembali ke GIS</a><?php endif ?></div><div class="card-body">
            <form method="post" action="<?= site_url('captive-non-penugasan/store') ?>"><?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= esc($returnTo ?? '') ?>">
                <input type="hidden" name="return_penyulang_id" value="<?= esc($returnPenyulangId ?? '') ?>">
                <input type="hidden" name="return_lat" value="<?= esc($returnLat ?? '') ?>">
                <input type="hidden" name="return_lng" value="<?= esc($returnLng ?? '') ?>">
                <input type="hidden" name="return_zoom" value="<?= esc($returnZoom ?? '') ?>">
                <input type="hidden" name="return_layers" value="<?= esc($returnLayers ?? '') ?>">

                <?php if (!empty($selectedAsset)): ?>
                <!-- Authoritative Gardu Asset Card -->
                <div class="card border-primary bg-light mb-3 shadow-sm" style="border-radius: 10px;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary text-white px-2 py-1"><i class="fas fa-lock me-1"></i> ASET GARDU (AUTHORITATIVE)</span>
                                <span class="badge bg-dark text-white font-monospace"><?= esc($selectedAsset['kode_asset'] ?? '-') ?></span>
                                <span class="badge bg-info text-dark"><?= esc($selectedAsset['jenis_asset'] ?? 'GARDU') ?></span>
                            </div>
                            <span class="badge bg-success text-white"><i class="fas fa-link me-1"></i> Tertaut ke Captive</span>
                        </div>
                        <div class="row g-2 small text-dark mb-1">
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Nama Gardu:</span>
                                <strong><?= esc($selectedAsset['nama_asset'] ?? '-') ?></strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Jaringan / ULP:</span>
                                <strong><?= esc($selectedAsset['nama_ulp'] ?? '-') ?></strong> &bull; <?= esc($selectedAsset['nama_penyulang'] ?? '-') ?>
                                <?php if (!empty($selectedAsset['nama_section'])): ?>
                                    &bull; <?= esc($selectedAsset['nama_section']) ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($selectedAsset['latitude']) && !empty($selectedAsset['longitude'])): ?>
                            <div class="col-12">
                                <span class="text-muted d-block">Koordinat Titik Gardu:</span>
                                <strong class="font-monospace text-primary"><?= esc($selectedAsset['latitude']) ?>, <?= esc($selectedAsset['longitude']) ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="source_asset_id" value="<?= (int)$selectedAsset['id'] ?>">
                        <input type="hidden" name="source_label" value="<?= esc(($selectedAsset['kode_asset'] ?? '') . ' - ' . ($selectedAsset['nama_asset'] ?? '')) ?>">
                    </div>
                </div>
                <?php endif; ?>

                <label class="form-label">Jenis catatan</label>
                <select class="form-select mb-3" name="jenis" id="captive-kind">
                    <option value="COATING" <?= ($translineId && empty($selectedAsset)) ? 'selected' : '' ?>>Potensi coating dari transline GIS</option>
                    <option value="MANUAL" <?= (!$translineId || !empty($selectedAsset)) ? 'selected' : '' ?>>Temuan manual<?= !empty($selectedAsset) ? ' (Aset Gardu)' : '' ?></option>
                </select>

                <div id="captive-line-fields" <?= (!$translineId || !empty($selectedAsset)) ? 'hidden' : '' ?>>
                    <label class="form-label">Transline terpilih</label>
                    <?php if (!empty($selectedLine)): ?>
                        <input type="hidden" name="transline_id" value="<?= (int)$selectedLine['id'] ?>"><input type="hidden" name="penyulang_id" value="<?= (int)$selectedLine['penyulang_id'] ?>"><input type="hidden" name="ulp_id" value="<?= (int)$selectedLine['ulp_id'] ?>">
                        <div class="alert alert-info small">#<?= (int)$selectedLine['id'] ?> · <?= esc($selectedLine['source_asset']['kode_asset'] ?? 'Titik A') ?> → <?= esc($selectedLine['target_asset']['kode_asset'] ?? 'Titik B') ?><br><?= esc($selectedLine['conductor_type'] ?? '-') ?> <?= esc($selectedLine['conductor_size'] ?? '-') ?> · <?= number_format((float)($selectedLine['distance_meters'] ?? 0), 2) ?> m</div>
                    <?php else: ?><div class="alert alert-warning">Pilih transline dari GIS untuk membuat catatan coating.</div><?php endif ?>
                    <div class="row"><div class="col-6"><label class="form-label">Konduktor hasil coating</label><input type="hidden" name="target_conductor_type" value="AAACS"><input class="form-control mb-2" value="AAACS" readonly></div><div class="col-6"><label class="form-label">Ukuran</label><?php $currentSizeDigits = preg_replace('/\D/', '', (string)($selectedLine['conductor_size'] ?? '')); ?><select name="target_conductor_size" class="form-select mb-2"><?php foreach ($sizes as $s): ?><option value="<?= esc($s) ?>" <?= $currentSizeDigits === preg_replace('/\D/', '', $s) ? 'selected' : '' ?>><?= esc($s) ?></option><?php endforeach ?></select></div></div>
                </div>

                <div id="captive-manual-fields" <?= ($translineId && empty($selectedAsset)) ? 'hidden' : '' ?>>
                    <label class="form-label">ULP <span class="text-danger">*</span></label>
                    <select class="form-select mb-2" name="ulp_id" id="manual-ulp" required>
                        <option value="">Pilih ULP</option>
                        <?php foreach ($ulps as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= (int)$selectedUlpId === (int)$u['id'] ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="form-label">Penyulang (opsional)</label>
                    <select class="form-select mb-2" name="penyulang_id" id="manual-penyulang">
                        <?php if (empty($selectedUlpId)): ?>
                            <option value="">-- Pilih ULP terlebih dahulu --</option>
                        <?php else: ?>
                            <option value="">Pilih penyulang</option>
                            <?php foreach ($feeders as $f): ?>
                                <option value="<?= (int)$f['id'] ?>" <?= (int)$selectedFeederId === (int)$f['id'] ? 'selected' : '' ?>><?= esc($f['nama_penyulang']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                    <label class="form-label">Section (opsional)</label>
                    <select class="form-select mb-2" name="section_id" id="manual-section">
                        <?php if (empty($selectedFeederId)): ?>
                            <option value="">-- Pilih penyulang terlebih dahulu --</option>
                        <?php else: ?>
                            <option value="">Pilih section</option>
                            <?php foreach ($sections as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= (int)$selectedSectionId === (int)$s['id'] ? 'selected' : '' ?>><?= esc($s['nama_section']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                    <label class="form-label">No. gangguan</label>
                    <input class="form-control mb-2" name="nomor_gangguan" maxlength="100" placeholder="Nomor referensi / gangguan (opsional)">

                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label mb-1">Lokasi temuan — klik peta untuk mengisi koordinat</label>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="locate-captive"><i class="fas fa-crosshairs me-1"></i> Lokasi saya</button>
                    </div>
                    <div id="captive-map" style="height:220px" class="rounded border mb-2"></div>
                    <div class="row">
                        <div class="col"><input id="captive-lat" class="form-control mb-2" name="latitude" placeholder="Latitude" value="<?= esc($selectedAsset['latitude'] ?? '') ?>" required></div>
                        <div class="col"><input id="captive-lng" class="form-control mb-2" name="longitude" placeholder="Longitude" value="<?= esc($selectedAsset['longitude'] ?? '') ?>" required></div>
                    </div>
                </div>

                <label class="form-label">Detail temuan / rencana pekerjaan <span class="text-danger">*</span></label>
                <textarea class="form-control mb-3" name="detail_temuan" rows="3" required placeholder="Deskripsikan temuan atau rencana pekerjaan captive..."></textarea>
                <button class="btn btn-primary w-100" type="submit"><i class="fas fa-save me-1"></i> Simpan Catatan Captive</button>
            </form>
            <div class="small text-muted mt-3">Perubahan spesifikasi transline hanya dilakukan setelah status pekerjaan coating ditandai selesai.</div>
        </div></div></div>
        <div class="col-xl-7"><div class="card shadow-sm"><div class="card-header fw-bold">Daftar Captive Non Penugasan</div><div class="table-responsive"><table class="table table-sm table-striped align-middle mb-0"><thead><tr><th>No</th><th>Lokasi / feeder</th><th>Jenis & konduktor</th><th>Detail</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        <?php foreach ($items as $item): ?><tr><td><?= esc($item['nomor']) ?><div class="small text-muted"><?= esc($item['created_at'] ?? '') ?></div></td><td><?= esc($item['nama_ulp'] ?? '-') ?><br><?= esc($item['nama_penyulang'] ?? '-') ?><br><span class="small"><?= esc($item['source_label'] ?? 'Manual') ?> <?= !empty($item['target_label']) ? '→ ' . esc($item['target_label']) : '' ?></span></td><td><?= esc($item['jenis']) ?><br><?= esc($item['conductor_type'] ?? '-') ?> <?= esc($item['conductor_size'] ?? '') ?><?php if ($item['jenis'] === 'COATING'): ?> → <?= esc($item['target_conductor_type'] ?? '') ?> <?= esc($item['target_conductor_size'] ?? '') ?><?php endif ?></td><td><?= esc($item['detail_temuan']) ?><br><span class="small text-muted"><?= esc($item['nomor_gangguan'] ?? '') ?></span></td><td><span class="badge <?= $item['status'] === 'SELESAI' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= esc($item['status']) ?></span></td><td><?php if ($item['status'] !== 'SELESAI'): ?><form method="post" action="<?= site_url('captive-non-penugasan/complete/' . (int)$item['id']) ?>" onsubmit="return confirm('Tandai pekerjaan selesai dan perbarui transline terkait?')"><?= csrf_field() ?><button class="btn btn-sm btn-success">Selesai</button></form><?php endif ?><?php if (!empty($item['transline_id'])): ?><a class="btn btn-sm btn-outline-primary mt-1" href="<?= site_url('gis?penyulang_id=' . (int)$item['penyulang_id'] . '&captive_only=1') ?>">Lihat GIS</a><?php endif ?></td></tr><?php endforeach ?>
        <?php if (!$items): ?><tr><td colspan="6" class="text-center text-muted p-4">Belum ada catatan.</td></tr><?php endif ?>
        </tbody></table></div></div></div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const kind = document.getElementById('captive-kind');
    const lineFields = document.getElementById('captive-line-fields');
    const manualFields = document.getElementById('captive-manual-fields');
    const setKind = () => {
        lineFields.hidden = kind.value !== 'COATING'; manualFields.hidden = kind.value !== 'MANUAL';
        lineFields.querySelectorAll('input,select,textarea,button').forEach(el => el.disabled = kind.value !== 'COATING');
        manualFields.querySelectorAll('input,select,textarea,button').forEach(el => el.disabled = kind.value !== 'MANUAL');
    };
    kind.addEventListener('change', setKind);
    setKind();

    // Cascading ULP -> Feeder -> Section with sequence counter protection
    const ulpSelect = document.getElementById('manual-ulp');
    const feederSelect = document.getElementById('manual-penyulang');
    const sectionSelect = document.getElementById('manual-section');

    let feederReqSeq = 0;
    let sectionReqSeq = 0;

    if (ulpSelect && feederSelect) {
        ulpSelect.addEventListener('change', function () {
            const ulpId = this.value;
            feederSelect.innerHTML = '<option value="">-- Pilih penyulang --</option>';
            sectionSelect.innerHTML = '<option value="">-- Pilih penyulang terlebih dahulu --</option>';

            if (!ulpId) {
                feederSelect.innerHTML = '<option value="">-- Pilih ULP terlebih dahulu --</option>';
                return;
            }

            const currentSeq = ++feederReqSeq;
            feederSelect.disabled = true;
            feederSelect.innerHTML = '<option value="">Memuat penyulang...</option>';

            fetch('<?= site_url('ajax/network/penyulang') ?>?ulp_id=' + encodeURIComponent(ulpId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (currentSeq !== feederReqSeq) return; // Discard stale response
                feederSelect.disabled = false;
                if (!Array.isArray(data) || data.length === 0) {
                    feederSelect.innerHTML = '<option value="">-- Tidak ada penyulang untuk ULP ini --</option>';
                } else {
                    let opts = '<option value="">Pilih penyulang</option>';
                    data.forEach(f => {
                        opts += `<option value="${f.id}">${f.nama_penyulang || ('Penyulang #' + f.id)}</option>`;
                    });
                    feederSelect.innerHTML = opts;
                }
            })
            .catch(() => {
                if (currentSeq !== feederReqSeq) return;
                feederSelect.disabled = false;
                feederSelect.innerHTML = '<option value="">Gagal memuat penyulang</option>';
            });
        });
    }

    if (feederSelect && sectionSelect) {
        feederSelect.addEventListener('change', function () {
            const feederId = this.value;
            sectionSelect.innerHTML = '<option value="">-- Pilih section --</option>';

            if (!feederId) {
                sectionSelect.innerHTML = '<option value="">-- Pilih penyulang terlebih dahulu --</option>';
                return;
            }

            const currentSeq = ++sectionReqSeq;
            sectionSelect.disabled = true;
            sectionSelect.innerHTML = '<option value="">Memuat section...</option>';

            fetch('<?= site_url('ajax/network/section') ?>?penyulang_id=' + encodeURIComponent(feederId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (currentSeq !== sectionReqSeq) return; // Discard stale response
                sectionSelect.disabled = false;
                if (!Array.isArray(data) || data.length === 0) {
                    sectionSelect.innerHTML = '<option value="">-- Tidak ada section untuk penyulang ini --</option>';
                } else {
                    let opts = '<option value="">Pilih section</option>';
                    data.forEach(s => {
                        opts += `<option value="${s.id}">${s.nama_section || ('Section #' + s.id)}</option>`;
                    });
                    sectionSelect.innerHTML = opts;
                }
            })
            .catch(() => {
                if (currentSeq !== sectionReqSeq) return;
                sectionSelect.disabled = false;
                sectionSelect.innerHTML = '<option value="">Gagal memuat section</option>';
            });
        });
    }

    if (!window.L || !document.getElementById('captive-map')) return;

    // Check for prefilled coordinates (e.g. from selected asset)
    const initLat = parseFloat(document.getElementById('captive-lat').value) || -7.45;
    const initLng = parseFloat(document.getElementById('captive-lng').value) || 112.56;
    const hasInitialCoord = Boolean(document.getElementById('captive-lat').value && document.getElementById('captive-lng').value);

    const map = L.map('captive-map').setView([initLat, initLng], hasInitialCoord ? 16 : 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    let marker;

    const put = (lat, lng) => {
        document.getElementById('captive-lat').value = lat.toFixed(8);
        document.getElementById('captive-lng').value = lng.toFixed(8);
        if (marker) marker.setLatLng([lat,lng]);
        else marker = L.marker([lat,lng]).addTo(map);
    };

    if (hasInitialCoord) {
        put(initLat, initLng);
    }

    map.on('click', e => put(e.latlng.lat, e.latlng.lng));
    kind.addEventListener('change', () => setTimeout(() => map.invalidateSize(), 100));
    document.getElementById('locate-captive').addEventListener('click', () => navigator.geolocation?.getCurrentPosition(p => { put(p.coords.latitude,p.coords.longitude); map.setView([p.coords.latitude,p.coords.longitude],17); }, () => alert('Lokasi perangkat tidak tersedia. Klik titik pada peta.')));
    setTimeout(() => map.invalidateSize(), 300);
});
</script>
<?= $this->endSection() ?>
