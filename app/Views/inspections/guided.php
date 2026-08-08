<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Mobile Guided Inspector<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Guided Inspection Execution<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-lg-8 col-12">
        <!-- HEADER PROGRESS CARD -->
        <div class="card shadow-sm border-0 rounded-4 mb-3 bg-gradient bg-primary text-white">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-white text-primary font-monospace px-3 py-1">
                        <i class="fas fa-barcode me-1"></i> <?= esc($inspection['nomor_inspeksi']) ?>
                    </span>
                    <span class="badge bg-dark text-warning border border-warning" id="network-status-badge">
                        <i class="fas fa-wifi me-1"></i> ONLINE
                    </span>
                </div>
                <h5 class="fw-bold text-white mb-1"><?= esc($inspection['type_name']) ?></h5>
                <p class="small text-white-50 mb-2"><i class="fas fa-route me-1"></i> <?= esc($inspection['baseline_name']) ?></p>

                <div class="d-flex justify-content-between align-items-center small mb-1">
                    <span class="fw-bold" id="progress-text">Titik Aset 1 dari <?= count($points) ?></span>
                    <span class="fw-bold" id="progress-percent">0%</span>
                </div>
                <div class="progress bg-white-50" style="height: 8px;">
                    <div class="progress-bar bg-warning" id="progress-bar-fill" role="progressbar" style="width: 0%"></div>
                </div>
            </div>
        </div>

        <!-- CURRENT ASSET CARD -->
        <div class="card shadow border-0 rounded-4 mb-4">
            <div class="card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center rounded-top-4">
                <div>
                    <span class="badge bg-info me-2 font-monospace" id="point-seq-badge">#001</span>
                    <span class="fw-bold" id="asset-name-header">Memuat Aset...</span>
                </div>
                <span class="badge bg-secondary font-monospace" id="asset-type-badge">TIANG</span>
            </div>

            <div class="card-body p-4">
                <div class="p-3 bg-light rounded-3 border border-secondary-subtle mb-4">
                    <div class="row g-2 text-dark small">
                        <div class="col-6"><strong>Kode Aset:</strong> <span class="font-monospace text-primary" id="asset-kode-val">-</span></div>
                        <div class="col-6"><strong>Jenis Aset:</strong> <span id="asset-jenis-val">-</span></div>
                        <div class="col-12 mt-2"><strong>Lokasi / Alamat:</strong> <span id="asset-lokasi-val">-</span></div>
                    </div>
                </div>

                <!-- CHECKLIST & TELEMETRY TEMPLATE ITEMS -->
                <form id="form-point-execution">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-list-check me-2 text-primary"></i> Parameter Pemeriksaan Lapangan</h6>
                    
                    <div id="template-items-container">
                        <?php if (!empty($templateItems)): ?>
                            <?php foreach ($templateItems as $idx => $item): ?>
                                <div class="card mb-3 border border-secondary-subtle rounded-3 item-execution-row" data-item-id="<?= $item['id'] ?>" data-item-type="<?= $item['item_type'] ?>">
                                    <div class="card-body p-3">
                                        <div class="fw-bold text-dark mb-2">
                                            <?= ($idx + 1) ?>. <?= esc($item['item_name']) ?>
                                            <?php if ($item['is_photo_required']): ?>
                                                <span class="badge bg-danger ms-1" style="font-size: 10px;"><i class="fas fa-camera me-1"></i> Foto Wajib</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($item['item_type'] === 'NUMERIC_MEASUREMENT'): ?>
                                            <div class="input-group">
                                                <input type="number" step="0.1" class="form-control form-control-lg numeric-measurement-input" placeholder="Masukkan nilai angka..." required>
                                                <span class="input-group-text bg-light text-muted fw-bold"><?= esc($item['unit'] ?: '°C') ?></span>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex gap-2">
                                                <input type="radio" class="btn-check btn-status-pass" name="item_status_<?= $item['id'] ?>" id="pass_<?= $item['id'] ?>" value="PASS" checked autocomplete="off">
                                                <label class="btn btn-outline-success flex-fill py-2 font-weight-bold" for="pass_<?= $item['id'] ?>">
                                                    <i class="fas fa-check-circle me-1"></i> PASS (Normal)
                                                </label>

                                                <input type="radio" class="btn-check btn-status-fail" name="item_status_<?= $item['id'] ?>" id="fail_<?= $item['id'] ?>" value="FAIL" autocomplete="off">
                                                <label class="btn btn-outline-danger flex-fill py-2 font-weight-bold" for="fail_<?= $item['id'] ?>">
                                                    <i class="fas fa-triangle-exclamation me-1"></i> FAIL (Abnormal)
                                                </label>
                                            </div>
                                        <?php endif; ?>

                                        <div class="mt-2">
                                            <input type="text" class="form-control form-control-sm item-notes-input" placeholder="Catatan khusus / uraian kerusakan (opsional)...">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- THERMOVISION CALCULATOR PREVIEW (IF THERMOVISION) -->
                    <div id="thermovision-calc-box" class="p-3 bg-dark text-white rounded-3 mb-4 d-none">
                        <div class="fw-bold text-warning mb-2"><i class="fas fa-temperature-high me-1"></i> Thermovision Real-Time Delta T Calculator</div>
                        <div class="row text-center g-2 small">
                            <div class="col-4"><div class="p-2 bg-secondary rounded">Delta R: <strong id="delta-r-val">0 °C</strong></div></div>
                            <div class="col-4"><div class="p-2 bg-secondary rounded">Delta S: <strong id="delta-s-val">0 °C</strong></div></div>
                            <div class="col-4"><div class="p-2 bg-secondary rounded">Delta T: <strong id="delta-t-val">0 °C</strong></div></div>
                        </div>
                    </div>

                    <!-- GENERAL POINT NOTES -->
                    <div class="mb-4">
                        <label for="point_notes" class="form-label fw-bold text-dark">Catatan Umum Titik Aset <small class="text-muted">(Opsional)</small></label>
                        <textarea id="point_notes" class="form-control" rows="2" placeholder="Catatan kondisi lingkungan / akses tiang..."></textarea>
                    </div>
                </form>
            </div>

            <!-- CARD FOOTER NAVIGATION -->
            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center rounded-bottom-4">
                <button type="button" id="btn-prev-point" class="btn btn-outline-secondary rounded-pill px-4" disabled>
                    <i class="fas fa-arrow-left me-1"></i> Sebelum
                </button>
                <button type="button" id="btn-next-point" class="btn btn-primary rounded-pill px-5 font-weight-bold">
                    Simpan & Lanjut <i class="fas fa-arrow-right ms-1"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const pointsData = <?= json_encode($points) ?>;
    let currentIndex = 0;

    function renderPoint(index) {
        if (index < 0 || index >= pointsData.length) return;
        currentIndex = index;
        const p = pointsData[index];

        document.getElementById('point-seq-badge').innerText = '#' + String(p.sequence_no).padStart(3, '0');
        document.getElementById('asset-name-header').innerText = p.nama_asset;
        document.getElementById('asset-type-badge').innerText = p.jenis_asset;
        document.getElementById('asset-kode-val').innerText = p.kode_asset;
        document.getElementById('asset-jenis-val').innerText = p.jenis_asset;
        document.getElementById('asset-lokasi-val').innerText = p.lokasi || '-';

        const percent = Math.round(((index + 1) / pointsData.length) * 100);
        document.getElementById('progress-text').innerText = `Titik Aset ${index + 1} dari ${pointsData.length}`;
        document.getElementById('progress-percent').innerText = `${percent}%`;
        document.getElementById('progress-bar-fill').style.width = `${percent}%`;

        document.getElementById('btn-prev-point').disabled = (index === 0);
        document.getElementById('btn-next-point').innerHTML = (index === pointsData.length - 1)
            ? '<i class="fas fa-check-circle me-1"></i> Selesaikan Inspeksi'
            : 'Simpan & Lanjut <i class="fas fa-arrow-right ms-1"></i>';
    }

    document.getElementById('btn-prev-point').addEventListener('click', function() {
        if (currentIndex > 0) renderPoint(currentIndex - 1);
    });

    document.getElementById('btn-next-point').addEventListener('click', function() {
        const p = pointsData[currentIndex];
        const items = [];

        document.querySelectorAll('.item-execution-row').forEach(function(row) {
            const itemId = row.getAttribute('data-item-id');
            const itemType = row.getAttribute('data-item-type');
            let status = 'PASS';
            let val = null;

            if (itemType === 'NUMERIC_MEASUREMENT') {
                val = row.querySelector('.numeric-measurement-input').value;
            } else {
                const failRadio = row.querySelector('.btn-status-fail');
                if (failRadio && failRadio.checked) status = 'FAIL';
            }

            items.push({
                template_item_id: itemId,
                result_status: status,
                measurement_value: val,
                notes: row.querySelector('.item-notes-input').value
            });
        });

        const payload = {
            items: items,
            notes: document.getElementById('point_notes').value
        };

        const btn = document.getElementById('btn-next-point');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...';

        let csrfHeaderName = '<?= csrf_header() ?>';
        let csrfHashValue  = '<?= csrf_hash() ?>';

        const headers = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        if (csrfHeaderName && csrfHashValue) {
            headers[csrfHeaderName] = csrfHashValue;
        }

        fetch(`<?= site_url('inspections/submit-point/') ?>${p.id}`, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(res => {
            btn.disabled = false;
            if (res.csrf_token && res.csrf_hash) {
                csrfHeaderName = res.csrf_token;
                csrfHashValue  = res.csrf_hash;
            }
            if (res.success) {
                if (currentIndex < pointsData.length - 1) {
                    renderPoint(currentIndex + 1);
                } else {
                    alert('Seluruh titik aset telah berhasil diinspeksi!');
                    window.location.href = '<?= site_url('inspections') ?>';
                }
            } else {
                alert('Gagal menyimpan hasil: ' + (res.message || 'Error tidak diketahui'));
                renderPoint(currentIndex);
            }
        })
        .catch(err => {
            btn.disabled = false;
            alert('Koneksi terputus. Data disimpan di antrean offline LocalStorage.');
            if (currentIndex < pointsData.length - 1) {
                renderPoint(currentIndex + 1);
            }
        });
    });

    if (pointsData.length > 0) renderPoint(0);
})();
</script>
<?= $this->endSection() ?>
