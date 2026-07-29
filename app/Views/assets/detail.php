<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap: 8px;">
        <div>
            <span class="badge bg-secondary font-monospace" style="font-size: 11px;"><?= esc($asset['kode_asset']) ?></span>
            <h3 class="fw-bold mb-0 text-dark font-weight-bold" style="font-family: 'Outfit', sans-serif;">
                <?= esc($asset['nama_asset']) ?>
            </h3>
            <p class="text-muted small mb-0"><i class="fas fa-building-user me-1 text-primary"></i> ULP: <?= esc($asset['nama_ulp']) ?> | Penyulang: <?= esc($asset['nama_penyulang'] ?: '-') ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= site_url('assets') ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
            <a href="<?= site_url('work-orders/create?asset_id=' . $asset['id']) ?>" class="btn btn-primary btn-sm rounded-pill font-weight-bold"><i class="fas fa-screwdriver-wrench me-1"></i> Terbitkan WO</a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Asset Card Info -->
        <div class="col-lg-8 col-12">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless" style="font-size: 13px;">
                                <tr><th style="width: 130px;">Jenis Asset</th><td>: <span class="badge bg-primary"><?= esc($asset['jenis_asset']) ?></span></td></tr>
                                <tr><th>Merk</th><td>: <?= esc($asset['merk'] ?: '-') ?></td></tr>
                                <tr><th>Tipe</th><td>: <?= esc($asset['type'] ?: '-') ?></td></tr>
                                <tr><th>Nomor Seri</th><td>: <span class="font-monospace fw-bold"><?= esc($asset['nomor_seri'] ?: '-') ?></span></td></tr>
                                <tr><th>Kapasitas</th><td>: <?= esc($asset['kapasitas'] ?: '-') ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless" style="font-size: 13px;">
                                <tr><th style="width: 130px;">Tahun Instalasi</th><td>: <?= esc($asset['tahun_instalasi'] ?: '-') ?></td></tr>
                                <tr><th>Status Asset</th><td>: 
                                    <?php if (strtoupper($asset['status']) === 'CRITICAL'): ?>
                                        <span class="badge bg-danger animate__animated animate__pulse animate__infinite"><i class="fas fa-radiation me-1"></i> CRITICAL</span>
                                    <?php elseif (strtoupper($asset['status']) === 'BERMASALAH'): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-triangle-exclamation me-1"></i> BERMASALAH</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> NORMAL</span>
                                    <?php endif; ?>
                                </td></tr>
                                <tr><th>Lokasi</th><td>: <?= esc($asset['lokasi'] ?: '-') ?></td></tr>
                                <tr><th>Koordinat GPS</th><td>: <span class="font-monospace text-primary"><?= esc($asset['latitude']) ?>, <?= esc($asset['longitude']) ?></span></td></tr>
                            </table>
                        </div>
                    </div>

                    <!-- GPS Live Distance Indicator -->
                    <div class="p-3 rounded-3 bg-light border d-flex align-items-center justify-content-between mt-3" style="font-size: 12px;">
                        <div>
                            <i class="fas fa-location-crosshairs text-danger me-2 fs-5"></i>
                            <span class="fw-bold text-dark">Jarak dari Posisi Anda:</span>
                            <span id="gps-distance-val" class="fw-bold text-primary ms-1">Menghitung koordinat...</span>
                        </div>
                        <?php if ($asset['latitude'] && $asset['longitude']): ?>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?= $asset['latitude'] ?>,<?= $asset['longitude'] ?>" target="_blank" class="btn btn-xs btn-outline-danger rounded-pill">
                                <i class="fas fa-map-marked-alt me-1"></i> Buka Google Maps
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tabs Timeline History (Temuan & Work Orders) -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <ul class="nav nav-pills card-header-pills" id="assetHistoryTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active font-weight-bold" id="temuan-tab" data-bs-toggle="tab" data-bs-target="#temuan-pane" type="button">
                                <i class="fas fa-list-check me-1"></i> Histori Temuan (<?= count($asset['temuan_history']) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link font-weight-bold" id="wo-tab" data-bs-toggle="tab" data-bs-target="#wo-pane" type="button">
                                <i class="fas fa-screwdriver-wrench me-1"></i> Histori Work Orders (<?= count($asset['wo_history']) ?>)
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-4 tab-content" id="assetHistoryTabContent">
                    <!-- Temuan Pane -->
                    <div class="tab-pane fade show active" id="temuan-pane" role="tabpanel">
                        <?php if (empty($asset['temuan_history'])): ?>
                            <p class="text-muted small mb-0">Belum ada temuan inspeksi pada aset ini.</p>
                        <?php else: ?>
                            <div class="timeline" style="border-left: 2px solid #e2e8f0; padding-left: 16px;">
                                <?php foreach ($asset['temuan_history'] as $t): ?>
                                    <div class="mb-3">
                                        <span class="badge bg-primary font-monospace" style="font-size: 10px;"><?= esc($t['nomor_temuan']) ?></span>
                                        <span class="text-muted small ms-2"><?= indo_datetime($t['tanggal_temuan']) ?></span>
                                        <div class="fw-bold text-dark mt-1" style="font-size: 13px;"><?= esc($t['detail_temuan']) ?></div>
                                        <small class="text-muted d-block">Pelaksana: <?= esc($t['pelaksana']) ?> | Status: <strong><?= esc($t['status']) ?></strong></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Work Order Pane -->
                    <div class="tab-pane fade" id="wo-pane" role="tabpanel">
                        <?php if (empty($asset['wo_history'])): ?>
                            <p class="text-muted small mb-0">Belum ada Work Order diterbitkan untuk aset ini.</p>
                        <?php else: ?>
                            <div class="timeline" style="border-left: 2px solid #e2e8f0; padding-left: 16px;">
                                <?php foreach ($asset['wo_history'] as $w): ?>
                                    <div class="mb-3">
                                        <a href="<?= site_url('work-orders/detail/' . $w['id']) ?>" class="fw-bold font-monospace text-decoration-none" style="font-size: 12px;">
                                            <?= esc($w['nomor_wo']) ?>
                                        </a>
                                        <span class="badge bg-info ms-2" style="font-size: 10px;"><?= esc($w['status']) ?></span>
                                        <div class="fw-bold text-dark mt-1" style="font-size: 13px;"><?= esc($w['judul_wo']) ?></div>
                                        <small class="text-muted d-block">Assigned To: <?= esc($w['assigned_to'] ?: '-') ?> | Diterbitkan: <?= indo_datetime($w['created_at']) ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: QR Code & Barcode Generator -->
        <div class="col-lg-4 col-12">
            <div class="card border-0 shadow-sm rounded-4 text-center p-4 mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-qrcode text-primary me-2"></i> QR Code & Barcode Aset</h5>
                <div class="p-3 bg-white border rounded-3 d-inline-block mx-auto mb-3 shadow-xs">
                    <!-- Dynamic SVG QR Code generator API -->
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode(site_url('assets/detail/' . $asset['id'])) ?>" alt="QR Code" class="img-fluid">
                </div>
                <div class="fw-bold font-monospace text-primary fs-6 mb-2"><?= esc($asset['kode_asset']) ?></div>
                <p class="text-muted small">Scan QR Code dengan Smartphone untuk membuka profile dan histori aset ini secara otomatis di lapangan.</p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function() {
    var assetLat = parseFloat("<?= $asset['latitude'] ?>");
    var assetLng = parseFloat("<?= $asset['longitude'] ?>");

    if (navigator.geolocation && assetLat && assetLng) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            var userLat = pos.coords.latitude;
            var userLng = pos.coords.longitude;
            
            // Calculate Haversine distance client side
            var R = 6371; // km
            var dLat = (assetLat - userLat) * Math.PI / 180;
            var dLon = (assetLng - userLng) * Math.PI / 180;
            var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                    Math.cos(userLat * Math.PI / 180) * Math.cos(assetLat * Math.PI / 180) *
                    Math.sin(dLon/2) * Math.sin(dLon/2);
            var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            var km = R * c;

            if (km < 1) {
                $('#gps-distance-val').text(Math.round(km * 1000) + ' meter dari lokasi Anda');
            } else {
                $('#gps-distance-val').text(km.toFixed(1) + ' km dari lokasi Anda');
            }
        }, function(err) {
            $('#gps-distance-val').text('Izin GPS tidak aktif / Koordinat tidak tersedia.');
        });
    } else {
        $('#gps-distance-val').text('Koordinat lokasi aset tidak terdaftar.');
    }
});
</script>
<?= $this->endSection() ?>
