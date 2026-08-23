<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-shield-alt text-primary mr-2"></i>Operational Command Center
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">Command Center</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- SLA Escalation Warning Banner (If Active Breaches Exist) -->
            <?php if (($metrics['by_sla_status']['SLA_BREACH'] ?? 0) > 0): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <h5><i class="icon fas fa-exclamation-triangle mr-2"></i>PERINGATAN ESKALASI OPERASIONAL!</h5>
                    Terdapat <strong><?= $metrics['by_sla_status']['SLA_BREACH'] ?> kasus risiko aktif</strong> yang telah melewati tenggat waktu SLA (SLA BREACH). Notifikasi eskalasi otomatis diteruskan ke Manajer ULP & Dalops.
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Real-Time Risk Priority Summary Cards -->
            <div class="row">
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-danger shadow-sm">
                        <div class="inner">
                            <h3><?= $metrics['by_priority']['P1'] ?? 0 ?></h3>
                            <p class="font-weight-bold">P1 - EMERGENCY</p>
                            <small class="d-block">SLA: &le; 3 Hari (72 Jam)</small>
                        </div>
                        <div class="icon">
                            <i class="fas fa-bolt"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-2 col-6">
                    <div class="small-box bg-warning shadow-sm">
                        <div class="inner text-white">
                            <h3 class="text-white"><?= $metrics['by_priority']['P2'] ?? 0 ?></h3>
                            <p class="font-weight-bold text-white">P2 - CRITICAL</p>
                            <small class="d-block text-white">SLA: &le; 3 Hari (72 Jam)</small>
                        </div>
                        <div class="icon">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-2 col-6">
                    <div class="small-box bg-info shadow-sm">
                        <div class="inner">
                            <h3><?= $metrics['by_priority']['P3'] ?? 0 ?></h3>
                            <p class="font-weight-bold">P3 - HIGH</p>
                            <small class="d-block">SLA: &le; 7 Hari (168 Jam)</small>
                        </div>
                        <div class="icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary shadow-sm">
                        <div class="inner">
                            <h3><?= $metrics['by_priority']['P4'] ?? 0 ?></h3>
                            <p class="font-weight-bold">P4 - MEDIUM</p>
                            <small class="d-block">SLA: &le; 30 Hari (720 Jam)</small>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success shadow-sm">
                        <div class="inner">
                            <h3><?= $metrics['by_priority']['P5'] ?? 0 ?></h3>
                            <p class="font-weight-bold">P5 - ROUTINE</p>
                            <small class="d-block">Monitoring Rutin 720 Jam</small>
                        </div>
                        <div class="icon">
                            <i class="fas fa-eye"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Phase 2K: Operational Geospatial Command Map -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card card-outline card-danger shadow-sm">
                        <div class="card-header border-0">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-map-marked-alt mr-2 text-danger"></i>Geo Command Map Intelligence
                            </h3>
                            <div class="card-tools">
                                <span class="badge badge-danger p-1"><i class="fas fa-circle text-danger mr-1"></i>P1 Emergency</span>
                                <span class="badge badge-warning p-1 text-white"><i class="fas fa-circle text-warning mr-1"></i>P2 Critical</span>
                                <span class="badge badge-info p-1"><i class="fas fa-circle text-info mr-1"></i>P3 High</span>
                                <span class="badge badge-success p-1"><i class="fas fa-circle text-success mr-1"></i>P5 Routine</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="geoCommandMap" style="height: 420px; width: 100%; border-radius: 0 0 4px 4px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Operational Action Cases Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card card-outline card-primary shadow-sm">
                        <div class="card-header border-0">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-tasks mr-2 text-primary"></i>Daftar Action Case Operasional Aktif
                            </h3>
                            <div class="card-tools">
                                <span class="badge badge-light p-2 border">Total: <?= count($activeCases) ?> Kasus</span>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover table-striped text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Case ID</th>
                                        <th>Nama Aset</th>
                                        <th>Observasi Evidence</th>
                                        <th>Severity</th>
                                        <th>Priority</th>
                                        <th>Status Operasional</th>
                                        <th>Tenggat SLA</th>
                                        <th>Status SLA</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($activeCases)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="fas fa-check-circle fa-2x mb-2 text-success d-block"></i>
                                                Tidak ada kasus risiko aktif. Seluruh jaringan dalam kondisi terverifikasi aman.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($activeCases as $case): ?>
                                            <?php
                                                $pCode = 'P' . ($case['priority'] ?? 5);
                                                $pBadge = match($pCode) {
                                                    'P1' => 'badge-danger',
                                                    'P2' => 'badge-warning text-white',
                                                    'P3' => 'badge-info',
                                                    'P4' => 'badge-primary',
                                                    default => 'badge-success',
                                                };

                                                $slaStatus = $case['sla_info']['sla_status'] ?? 'ON_TRACK';
                                                $slaBadge  = match($slaStatus) {
                                                    'SLA_BREACH'  => 'badge-danger',
                                                    'SLA_WARNING' => 'badge-warning text-white',
                                                    default       => 'badge-success',
                                                };
                                            ?>
                                            <tr>
                                                <td><strong>#<?= $case['id'] ?></strong></td>
                                                <td>
                                                    <strong><?= esc($case['nama_asset']) ?></strong>
                                                    <br><small class="text-muted"><?= esc($case['kode_asset']) ?></small>
                                                </td>
                                                <td><span class="badge badge-light border"><?= esc($case['source_observation_type']) ?> #<?= $case['source_observation_id'] ?></span></td>
                                                <td><span class="badge badge-secondary"><?= esc($case['severity_at_open']) ?></span></td>
                                                <td><span class="badge <?= $pBadge ?> p-2"><?= $pCode ?></span></td>
                                                <td><span class="badge badge-outline-dark border p-1"><?= esc($case['status']) ?></span></td>
                                                <td><small><?= esc($case['sla_info']['sla_deadline'] ?? '-') ?></small></td>
                                                <td><span class="badge <?= $slaBadge ?> p-2"><?= $slaStatus ?></span></td>
                                                <td>
                                                    <a href="<?= base_url('assets/detail/' . $case['asset_id']) ?>" class="btn btn-xs btn-outline-primary">
                                                        <i class="fas fa-search mr-1"></i>Detail 360°
                                                    </a>
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
    </section>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var map = L.map('geoCommandMap').setView([-7.4478, 112.7183], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap SIDAK TEJO v3.0.0'
    }).addTo(map);

    fetch('<?= base_url('command-center/geo-data') ?>')
        .then(response => response.json())
        .then(data => {
            if (data && data.features) {
                data.features.forEach(function(feat) {
                    var props = feat.properties;
                    var coords = feat.geometry.coordinates;

                    var color = '#28a745';
                    if (props.priority_code === 'P1') color = '#dc3545';
                    else if (props.priority_code === 'P2') color = '#ffc107';
                    else if (props.priority_code === 'P3') color = '#17a2b8';

                    var marker = L.circleMarker([coords[1], coords[0]], {
                        radius: props.priority_code === 'P1' ? 10 : 7,
                        fillColor: color,
                        color: '#ffffff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.85
                    }).addTo(map);

                    var popupHtml = `
                        <div style="min-width: 180px;">
                            <strong>${props.nama_asset}</strong><br>
                            <small class="text-muted">${props.kode_asset} - ${props.lokasi}</small><br>
                            <hr class="my-1">
                            Health Score: <strong>${props.health_score} / 100</strong> (${props.health_category})<br>
                            Priority: <span class="badge badge-dark">${props.priority_code}</span> | SLA: <span class="badge badge-info">${props.sla_status}</span><br>
                            <div class="mt-2 text-right">
                                <a href="<?= base_url('assets/detail/') ?>/${props.asset_id}" class="btn btn-xs btn-primary">Detail 360°</a>
                            </div>
                        </div>
                    `;
                    marker.bindPopup(popupHtml);
                });
            }
        })
        .catch(err => console.error("Geo Data Fetch Error:", err));
});
</script>
<?= $this->endSection() ?>
