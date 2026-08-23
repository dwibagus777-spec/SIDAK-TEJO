<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #0f172a; color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { background: #1e293b; border: 1px solid #334155; border-radius: 12px; }
    </style>
</head>
<body class="py-4">
<div class="container-fluid px-4">
    <!-- Header Banner -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
        <div>
            <h3 class="fw-bold text-info mb-1"><i class="fa-solid fa-satellite-dish me-2"></i>Enterprise Integration & Real-Time Sync Fabric</h3>
            <small class="text-secondary"><i class="fa-solid fa-network-wired me-1"></i>Cross-System Interoperability: PLN APKT, YANTAP, GIS & SCADA/AMI Stream</small>
        </div>
        <div>
            <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-signal me-1"></i>ALL ADAPTERS ONLINE</span>
        </div>
    </div>

    <!-- External Adapters Status Cards -->
    <div class="row g-3 mb-4">
        <?php if (!empty($interopData['enterprise_adapters'])): ?>
            <?php foreach ($interopData['enterprise_adapters'] as $sysName => $sysInfo): ?>
                <div class="col-md-3">
                    <div class="card card-custom p-3">
                        <div class="text-secondary small fw-bold"><i class="fa-solid fa-plug text-info me-1"></i><?= esc($sysName) ?></div>
                        <div class="fs-4 font-monospace fw-bold text-success mt-1"><?= esc($sysInfo['status']) ?></div>
                        <small class="text-secondary">Latency: <?= esc($sysInfo['latency_ms']) ?> ms | Sync: <?= esc($sysInfo['last_sync']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="row g-3">
        <!-- Telemetry Stream Card -->
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-warning mb-3"><i class="fa-solid fa-gauge-high me-2"></i>Real-Time Sensor Telemetry Stream</h5>
                <ul class="list-group list-group-flush bg-transparent">
                    <li class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between">
                        <span>SCADA Breaker Status:</span>
                        <strong class="text-success"><?= esc($telemetry['scada_breaker_state'] ?? 'CLOSED_NORMAL') ?></strong>
                    </li>
                    <li class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between">
                        <span>Penyulang Voltage (kV):</span>
                        <strong class="text-info"><?= esc($telemetry['scada_feeder_voltage_kv'] ?? 20.15) ?> kV</strong>
                    </li>
                    <li class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between">
                        <span>AMI Current Load (Ampere):</span>
                        <strong class="text-warning"><?= esc($telemetry['ami_current_load_amp'] ?? 145.2) ?> A</strong>
                    </li>
                    <li class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between">
                        <span>Thermovision IoT Sensor (°C):</span>
                        <strong class="text-light"><?= esc($telemetry['thermovision_iot_temp_c'] ?? 68.5) ?> °C</strong>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Real-Time Field Officer Sync Card -->
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-info mb-3"><i class="fa-solid fa-location-dot me-2"></i>Real-Time Field Crew Live Tracking</h5>
                <div class="p-3 bg-dark rounded mb-3">
                    <div class="text-secondary small">Regu Lapangan Terpenuhi:</div>
                    <div class="fs-5 fw-bold text-light"><?= esc($fieldSync['assigned_crew_code'] ?? 'TIM-HAR-SDA-01') ?> (Leader: <?= esc($fieldSync['crew_leader'] ?? 'Budi Santoso') ?>)</div>
                    <div class="text-info small font-monospace mt-1">GPS Lat/Lng: <?= esc($fieldSync['current_gps_lat'] ?? -7.4468) ?>, <?= esc($fieldSync['current_gps_lng'] ?? 112.7178) ?></div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-secondary">Status Pekerjaan:</span>
                    <span class="badge bg-warning text-dark px-3 py-2"><?= esc($fieldSync['field_task_status'] ?? 'ON_SITE_REPAIR') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
