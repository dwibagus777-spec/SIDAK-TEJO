<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Digital Twin: <?= esc($asset['nama_asset']) ?><?= $this->endSection() ?>
<?= $this->section('page_title') ?>Digital Twin & Asset Health Hub<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Phase 31.3 Digital Twin Design System */
    .digital-twin-container {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .dt-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: 20px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
        transition: all 0.28s ease;
        overflow: hidden;
    }
    .dt-card:hover {
        box-shadow: 0 18px 30px -8px rgba(15, 23, 42, 0.1);
    }

    /* Health Gauge Circle */
    .health-circle {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        margin: 0 auto;
    }
    .health-val {
        font-size: 32px;
        font-weight: 800;
        line-height: 1;
    }
    .health-lbl {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    /* Social-Media Style Timeline */
    .timeline-social {
        position: relative;
        padding-left: 28px;
    }
    .timeline-social-item {
        position: relative;
        padding-bottom: 20px;
        border-left: 2px dashed #cbd5e1;
        padding-left: 20px;
    }
    .timeline-social-item:last-child {
        border-left: 2px solid transparent;
    }
    .timeline-social-item::before {
        content: '';
        position: absolute;
        left: -8px;
        top: 2px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #0284c7;
        border: 3px solid #ffffff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    /* Connected Asset Topology Node */
    .topo-node {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 11px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }

    /* Photo Gallery Zoomable */
    .gallery-img {
        width: 100%;
        height: 110px;
        object-fit: cover;
        border-radius: 12px;
        cursor: pointer;
        transition: transform 0.2s ease;
    }
    .gallery-img:hover {
        transform: scale(1.04);
    }
</style>

<div class="digital-twin-container container-fluid py-3">

    <!-- 1. DIGITAL TWIN HEADER -->
    <div class="dt-card p-4 mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff;">
        <div class="row align-items-center g-4">
            <!-- Asset Photo & QR -->
            <div class="col-md-3 text-center">
                <?php if (!empty($asset['foto'])): ?>
                    <img src="<?= base_url($asset['foto']) ?>" alt="Foto Asset" class="img-fluid rounded-4 shadow border border-2 border-warning" style="max-height: 140px; width: 100%; object-fit: cover;">
                <?php else: ?>
                    <div class="bg-dark text-white rounded-4 p-4 border border-secondary text-center" style="max-height: 140px;">
                        <i class="fas fa-boxes-stacked text-warning display-4 mb-2"></i>
                        <span class="d-block small text-white-50">Photo Digital Twin</span>
                    </div>
                <?php endif; ?>
                <div class="mt-2">
                    <span class="badge bg-dark border border-secondary text-warning font-monospace" style="font-size: 11px;">
                        <i class="fas fa-qrcode me-1"></i> <?= esc($asset['kode_asset']) ?>
                    </span>
                </div>
            </div>

            <!-- Asset Info -->
            <div class="col-md-6">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill mb-1">
                    <i class="fas fa-microchip me-1"></i> Digital Twin Node
                </span>
                <h2 class="fw-bold mb-1 text-white"><?= esc($asset['nama_asset']) ?></h2>
                <p class="text-white-50 small mb-2">
                    <i class="fas fa-building-user me-1 text-info"></i> ULP: <strong><?= esc($asset['nama_ulp'] ?? 'UP3 Sidoarjo') ?></strong>
                    &middot; Penyulang: <strong><?= esc($asset['nama_penyulang'] ?: '-') ?></strong>
                    &middot; Section: <strong><?= esc($asset['nama_section'] ?: '-') ?></strong>
                </p>

                <div class="d-flex flex-wrap gap-2 text-white-50 small">
                    <span class="badge bg-dark border border-secondary"><i class="fas fa-tag me-1"></i> Jenis: <?= esc($asset['jenis_asset']) ?></span>
                    <span class="badge bg-dark border border-secondary"><i class="fas fa-industry me-1"></i> Merk: <?= esc($asset['merk'] ?: '-') ?></span>
                    <span class="badge bg-dark border border-secondary"><i class="fas fa-barcode me-1"></i> SN: <?= esc($asset['nomor_seri'] ?: '-') ?></span>
                    <span class="badge bg-dark border border-secondary"><i class="fas fa-calendar-check me-1 text-info"></i> Awal Pemasangan: <?= !empty($asset['installation_date']) ? date('d-m-Y', strtotime($asset['installation_date'])) : 'Belum diisi' ?></span>
                    <span class="badge bg-dark border border-secondary"><i class="fas fa-hourglass-half me-1 text-warning"></i> Umur: <?= $asset['age_years'] ?? 0 ?> Tahun</span>
                </div>
            </div>

            <!-- Health Score Meter -->
            <div class="col-md-3 text-center">
                <div class="health-circle" style="background: <?= $asset['health_color'] ?>;">
                    <span class="health-val"><?= $asset['health_score'] ?></span>
                    <span class="health-lbl">HEALTH SCORE</span>
                </div>
                <div class="mt-2">
                    <span class="badge <?= $asset['health_bg'] ?> px-3 py-1 font-weight-bold" style="font-size: 11px;">
                        STATUS: <?= $asset['health_category'] ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. AI RISK ENGINE & PREDICTIVE MAINTENANCE FORECAST -->
    <div class="dt-card p-3 mb-4 border-start border-4 border-info" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);">
        <div class="d-flex align-items-start gap-3">
            <div class="badge bg-info text-white rounded-circle p-2 fs-4"><i class="fas fa-brain"></i></div>
            <div class="flex-fill">
                <h6 class="fw-bold text-dark mb-1"><i class="fas fa-sparkles text-warning me-1"></i> AI Risk & Predictive Maintenance Engine</h6>
                <p class="text-secondary small mb-1">
                    AI Risk Level: <span class="badge bg-<?= $asset['risk_score'] === 'CRITICAL' ? 'danger' : ($asset['risk_score'] === 'HIGH' ? 'warning text-dark' : 'success') ?>"><?= $asset['risk_score'] ?></span>
                    &middot; Predictive Maintenance Forecast: <strong>Aset diperkirakan butuh maintenance dalam <?= $asset['est_maint_days'] ?> hari lagi.</strong>
                </p>
                <small class="text-muted"><i class="fas fa-circle-check text-success me-1"></i> Rekomendasi Tindakan: Thermovision, Pengujian Isolasi, & Pemangkasan Pohon ROW.</small>
            </div>
            <a href="<?= site_url('work-orders/create?asset_id=' . $asset['id']) ?>" class="btn btn-primary btn-sm rounded-pill font-weight-bold px-3 ms-auto">
                <i class="fas fa-screwdriver-wrench me-1"></i> Terbitkan WO
            </a>
        </div>
    </div>

    <!-- 3. SUB-COMPONENT HEALTH BREAKDOWN & CONNECTED TOPOLOGY -->
    <div class="row g-4 mb-4">
        <!-- Sub-Components Breakdown -->
        <div class="col-lg-6 col-12">
            <div class="dt-card p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-cubes text-primary me-2"></i> Sub-Component Health Status</h5>
                <?php foreach ($asset['components'] as $comp): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small fw-bold mb-1">
                            <span><?= esc($comp['name']) ?></span>
                            <span class="text-primary"><?= $comp['health'] ?>%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-<?= $comp['health'] >= 80 ? 'success' : ($comp['health'] >= 60 ? 'warning' : 'danger') ?>" style="width: <?= $comp['health'] ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Connected Assets Topology Tree -->
        <div class="col-lg-6 col-12">
            <div class="dt-card p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-diagram-project text-success me-2"></i> Connected Asset Topology</h5>
                <p class="text-muted small mb-3">Hirarki keterhubungan jaringan listrik:</p>
                
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="topo-node text-primary"><i class="fas fa-bolt"></i> Trafo SDJ-14</span>
                    <i class="fas fa-arrow-right text-muted"></i>
                    <span class="topo-node text-dark"><i class="fas fa-tower-cell"></i> Tiang T-102</span>
                    <i class="fas fa-arrow-right text-muted"></i>
                    <span class="topo-node text-warning"><i class="fas fa-toggle-on"></i> LBS Klurak</span>
                    <i class="fas fa-arrow-right text-muted"></i>
                    <span class="topo-node text-info"><i class="fas fa-rotate"></i> Recloser</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. LOCATION MINI GIS MAP & NEARBY ASSETS -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8 col-12">
            <div class="dt-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-map-location-dot text-danger me-2"></i> Presisi Lokasi GIS & Koordinat</h5>
                    <?php if (!empty($asset['latitude']) && !empty($asset['longitude'])): ?>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?= $asset['latitude'] ?>,<?= $asset['longitude'] ?>" target="_blank" class="btn btn-outline-danger btn-sm rounded-pill font-weight-bold">
                            <i class="fas fa-map-marked-alt me-1"></i> Google Maps
                        </a>
                    <?php endif; ?>
                </div>
                <div id="dt-asset-map" style="height: 280px; border-radius: 14px;" class="border shadow-sm"></div>
            </div>
        </div>

        <!-- Nearby Assets Radius Filter -->
        <div class="col-lg-4 col-12">
            <div class="dt-card p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-compass text-warning me-2"></i> Nearby Assets (Radius 250m)</h5>
                <?php if (empty($asset['nearby_assets'])): ?>
                    <p class="text-muted small">Tidak ada aset lain dalam radius 250m.</p>
                <?php else: ?>
                    <?php foreach ($asset['nearby_assets'] as $nb): ?>
                        <div class="p-2 border rounded-3 mb-2 bg-light d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="d-block text-dark small"><?= esc($nb['nama_asset']) ?></strong>
                                <small class="text-muted" style="font-size: 10px;"><?= esc($nb['kode_asset']) ?></small>
                            </div>
                            <a href="<?= site_url('assets/detail/' . $nb['id']) ?>" class="btn btn-xs btn-outline-primary rounded-pill">Detail &rarr;</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 4.5. NETWORK TOPOLOGY TREE (RELEASE v2.1.0) -->
    <div class="dt-card p-4 mb-4">
        <h5 class="fw-bold text-dark mb-3"><i class="fas fa-sitemap text-info me-2"></i> Network Topology Tree & Feeder Relation</h5>
        <?php if (!empty($topologyTree) && !empty($topologyTree['downstream'])): ?>
            <div class="p-3 bg-light rounded-3 border border-secondary-subtle font-monospace text-dark" style="font-size: 13px;">
                <div class="fw-bold text-primary mb-2"><i class="fas fa-layer-group me-1"></i> [ROOT] <?= esc($topologyTree['kode_asset']) ?> &mdash; <?= esc($topologyTree['nama_asset']) ?> (<?= esc($topologyTree['jenis_asset']) ?>)</div>
                <div class="ps-3 border-start border-2 border-primary ms-2">
                    <?php foreach ($topologyTree['downstream'] as $child): ?>
                        <div class="mb-1 text-dark">
                            &rdsh; <a href="<?= site_url('assets/detail/' . $child['id']) ?>" class="fw-bold text-decoration-none text-dark">[<?= esc($child['kode_asset']) ?>] <?= esc($child['nama_asset']) ?></a>
                            <span class="badge bg-secondary ms-1" style="font-size: 10px;"><?= esc($child['jenis_asset']) ?></span>
                            <span class="badge bg-success ms-1" style="font-size: 10px;"><?= esc($child['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i> Aset ini berdiri sendiri atau belum terhubung dengan downstream node topologi lain.</p>
        <?php endif; ?>
    </div>

    <!-- 5. SOCIAL MEDIA STYLE TIMELINE HISTORY -->
    <div class="dt-card p-4 mb-4">
        <h5 class="fw-bold text-dark mb-4"><i class="fas fa-clock-rotate-left text-primary me-2"></i> Digital Twin Activity Timeline</h5>
        
        <div class="timeline-social">
            <!-- Inspection History -->
            <?php foreach (($asset['temuan_history'] ?? []) as $t): ?>
                <div class="timeline-social-item mb-3">
                    <span class="badge bg-primary font-monospace me-2" style="font-size: 10px;"><?= esc($t['nomor_temuan']) ?></span>
                    <span class="text-muted small"><?= indo_datetime($t['tanggal_temuan']) ?></span>
                    <h6 class="fw-bold text-dark mb-1 mt-1"><?= esc($t['detail_temuan']) ?></h6>
                    <p class="text-muted small mb-0">Pelaksana: <?= esc($t['pelaksana']) ?> &middot; Status: <strong><?= esc($t['status']) ?></strong></p>
                </div>
            <?php endforeach; ?>

            <!-- Work Order History -->
            <?php foreach (($asset['wo_history'] ?? []) as $wo): ?>
                <div class="timeline-social-item mb-3">
                    <span class="badge bg-success font-monospace me-2" style="font-size: 10px;"><?= esc($wo['nomor_wo']) ?></span>
                    <span class="text-muted small"><?= indo_datetime($wo['created_at']) ?></span>
                    <h6 class="fw-bold text-dark mb-1 mt-1">Work Order: <?= esc($wo['judul_wo'] ?? $wo['judul_pekerjaan'] ?? '-') ?></h6>
                    <p class="text-muted small mb-0">Status: <strong><?= esc($wo['status']) ?></strong> &middot; Target: <?= esc($wo['target_selesai'] ?: '-') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var lat = <?= (float)($asset['latitude'] ?: -7.4478) ?>;
    var lng = <?= (float)($asset['longitude'] ?: 112.7183) ?>;

    var map = L.map('dt-asset-map').setView([lat, lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; SIDAK TEJO Digital Twin'
    }).addTo(map);

    L.marker([lat, lng]).addTo(map)
        .bindPopup('<b><?= esc($asset['nama_asset']) ?></b><br><small>Health: <?= $asset['health_score'] ?>% (<?= $asset['health_category'] ?>)</small>')
        .openPopup();
});
</script>
<?= $this->endSection() ?>
