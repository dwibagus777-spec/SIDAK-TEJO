<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>AI Command Center & Live Operation Center<?= $this->endSection() ?>
<?= $this->section('page_title') ?>SIDAK TEJO AI Command Center (Live Operation Center)<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Phase 31.2: AI Command Center & Live Operation System */
    .command-center-reborn {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .oc-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(226, 232, 240, 0.85);
        border-radius: 20px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
        transition: all 0.28s ease;
        overflow: hidden;
    }
    .oc-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 20px 30px -10px rgba(15, 23, 42, 0.1);
    }

    /* Live Mission Control Status Pill Counters */
    .status-counter-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }
    .status-pill {
        padding: 8px 14px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 6px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.03);
    }

    /* Blinking Emergency Indicator */
    .blink-emergency {
        animation: pulse-red 1.2s infinite;
    }
    @keyframes pulse-red {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }

    /* Smart Filter Buttons */
    .filter-pill {
        border-radius: 30px;
        font-size: 11px;
        font-weight: 700;
        padding: 6px 14px;
        transition: all 0.2s ease;
    }

    /* Live Officer Avatar List */
    .officer-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid #f1f5f9;
        margin-bottom: 8px;
        background: #ffffff;
    }
    .officer-row:hover {
        background: #f8fafc;
    }

    /* Voice Command Button */
    .btn-voice-command {
        background: linear-gradient(135deg, #7e22ce 0%, #6b21a8 100%);
        color: #ffffff;
        border-radius: 30px;
        padding: 8px 18px;
        font-weight: 700;
        font-size: 13px;
        border: none;
        box-shadow: 0 4px 12px rgba(126, 34, 206, 0.3);
        transition: all 0.25s ease;
    }
    .btn-voice-command:hover {
        transform: scale(1.04);
        color: #ffffff;
        box-shadow: 0 8px 20px rgba(126, 34, 206, 0.4);
    }
</style>

<div class="command-center-reborn container-fluid py-3">

    <!-- 1. GLOBAL SMART SEARCH & VOICE COMMAND BAR -->
    <div class="oc-card p-3 mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff;">
        <div class="row g-2 align-items-center">
            <div class="col-md-7 col-12">
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-warning"><i class="fas fa-magnifying-glass"></i></span>
                    <input type="text" id="global-smart-search" class="form-control bg-dark text-white border-secondary" placeholder="Smart Search: Cari No Tiang, NOGA, Asset, Penyulang, Petugas, WO, Temuan..." style="font-size: 13px;">
                    <button class="btn btn-primary btn-sm px-3" type="button"><i class="fas fa-arrow-right me-1"></i> Cari</button>
                </div>
            </div>
            <div class="col-md-5 col-12 d-flex justify-content-md-end gap-2">
                <button type="button" class="btn-voice-command" id="btn-voice-trigger">
                    <i class="fas fa-microphone me-1"></i> Voice Command
                </button>
                <a href="<?= site_url('ecc') ?>" class="btn btn-outline-danger btn-sm rounded-pill font-weight-bold px-3">
                    <i class="fas fa-tv me-1"></i> Video Wall
                </a>
            </div>
        </div>
    </div>

    <!-- 2. LIVE MISSION CONTROL COUNTERS BAR (AJAX 30-Sec Polling) -->
    <div class="oc-card p-3 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <h6 class="fw-bold text-dark mb-0"><i class="fas fa-radar text-primary me-2"></i> Mission Control Status Petugas Lapangan (Realtime AJAX)</h6>
            <small class="text-muted">Auto refresh: <span class="badge bg-dark font-monospace" id="live-timer">30s</span></small>
        </div>

        <div class="status-counter-bar">
            <div class="status-pill text-success">
                <span class="status-pulse-live"></span> 🟢 Online: <strong id="live-cnt-online">14</strong>
            </div>
            <div class="status-pill text-warning">
                <i class="fas fa-person-digging me-1"></i> 🟡 Sedang Bekerja: <strong id="live-cnt-working">8</strong>
            </div>
            <div class="status-pill text-primary">
                <i class="fas fa-pen-to-square me-1"></i> 🔵 Sedang Input: <strong id="live-cnt-input">3</strong>
            </div>
            <div class="status-pill text-info">
                <i class="fas fa-arrows-rotate me-1"></i> 🟠 Sedang Update: <strong id="live-cnt-update">4</strong>
            </div>
            <div class="status-pill text-danger bg-danger-subtle border-danger">
                <i class="fas fa-triangle-exclamation me-1 blink-emergency"></i> 🔴 Emergency Aktif: <strong id="live-cnt-emergency"><?= number_format($stats['emergency'] ?? 0) ?></strong>
            </div>
            <div class="status-pill text-secondary">
                <i class="fas fa-power-off me-1"></i> ⚫ Offline: <strong id="live-cnt-offline">2</strong>
            </div>
        </div>
    </div>

    <!-- 3. SMART ALERT BANNER & WEATHER ALERT -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8 col-12">
            <div class="oc-card p-3 border-start border-4 border-warning bg-warning-subtle text-dark">
                <div class="d-flex align-items-start gap-2">
                    <i class="fas fa-triangle-exclamation text-warning fs-3 mt-1"></i>
                    <div>
                        <h6 class="fw-bold mb-1"><i class="fas fa-bell text-danger me-1"></i> Smart AI Alert System</h6>
                        <p class="mb-0 small">
                            <strong>⚠️ Penyulang KRIAN 04</strong> terdapat 6 hotspot belum ditangani. Prioritas <strong>HIGH</strong>.
                            Disarankan pengerahan tim HAR Gardu segera untuk mencegah tripping jaringan.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-12">
            <div class="oc-card p-3 border-start border-4 border-info bg-info-subtle text-dark">
                <div class="d-flex align-items-center gap-3">
                    <i class="fas fa-cloud-sun text-info fs-2"></i>
                    <div>
                        <h6 class="fw-bold mb-0">Cuaca Sidoarjo: 29°C</h6>
                        <small class="text-muted">Angin: 12 km/h &middot; Hujan: 10%</small>
                        <span class="badge bg-success-subtle text-success border border-success-subtle d-block mt-1" style="font-size: 10px;">
                            <i class="fas fa-shield-check me-1"></i> Aman untuk Inspeksi & PDKB
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. LIVE EMERGENCY BOARD & OFFICERS STATUS -->
    <div class="row g-4 mb-4">
        <!-- Live Emergency Board -->
        <div class="col-lg-6 col-12">
            <div class="oc-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-danger mb-0"><i class="fas fa-fire-flame-curved me-2"></i> Live Emergency Board</h5>
                    <span class="badge bg-danger rounded-pill px-3 py-1 blink-emergency">Paling Kritis</span>
                </div>

                <div class="row g-2 text-center mb-3">
                    <div class="col-3">
                        <div class="p-2 bg-danger-subtle rounded-3 border border-danger-subtle">
                            <small class="text-danger fw-bold d-block" style="font-size: 10px;">BARU</small>
                            <h5 class="fw-bold text-danger mb-0"><?= number_format($stats['emergency'] ?? 0) ?></h5>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-warning-subtle rounded-3 border border-warning-subtle">
                            <small class="text-warning fw-bold d-block" style="font-size: 10px;">PROSES</small>
                            <h5 class="fw-bold text-warning mb-0"><?= number_format($stats['high'] ?? 0) ?></h5>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-success-subtle rounded-3 border border-success-subtle">
                            <small class="text-success fw-bold d-block" style="font-size: 10px;">SELESAI</small>
                            <h5 class="fw-bold text-success mb-0"><?= number_format($stats['selesai'] ?? 0) ?></h5>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-dark-subtle rounded-3 border border-secondary-subtle">
                            <small class="text-secondary fw-bold d-block" style="font-size: 10px;">TERLAMBAT</small>
                            <h5 class="fw-bold text-dark mb-0"><?= number_format($stats['medium'] ?? 0) ?></h5>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 border" style="max-height: 200px; overflow-y: auto;">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-danger">STJ-EMG-0419</span>
                        <small class="text-muted">Penyulang Klurak &middot; Hotspot Connector</small>
                        <span class="badge bg-outline-danger">EMERGENCY</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-danger">STJ-EMG-0418</span>
                        <small class="text-muted">Penyulang Krian 04 &middot; Pohon Dekat Jaringan</small>
                        <span class="badge bg-outline-danger">EMERGENCY</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Officers Status Panel -->
        <div class="col-lg-6 col-12">
            <div class="oc-card p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-users-gear text-primary me-2"></i> Live Officer Status & Tracking</h5>

                <div class="officer-row">
                    <div class="d-flex align-items-center gap-2">
                        <span class="status-pulse-live"></span>
                        <div>
                            <span class="fw-bold text-dark d-block small">Dwi Bagus Arianto</span>
                            <small class="text-muted" style="font-size: 10px;">Administrator &middot; ULP Sidoarjo Kota</small>
                        </div>
                    </div>
                    <span class="badge bg-success-subtle text-success">Sedang Bekerja (92%)</span>
                </div>

                <div class="officer-row">
                    <div class="d-flex align-items-center gap-2">
                        <span class="status-pulse-live"></span>
                        <div>
                            <span class="fw-bold text-dark d-block small">Tim PDKB UP3</span>
                            <small class="text-muted" style="font-size: 10px;">PDKB Line Specialist &middot; Penyulang Klurak</small>
                        </div>
                    </div>
                    <span class="badge bg-warning-subtle text-warning">Inspeksi Hotline (80%)</span>
                </div>

                <div class="officer-row">
                    <div class="d-flex align-items-center gap-2">
                        <span class="status-pulse-live"></span>
                        <div>
                            <span class="fw-bold text-dark d-block small">Tim HAR Gardu</span>
                            <small class="text-muted" style="font-size: 10px;">Teknisi Gardu &middot; Gardu SDJ-14</small>
                        </div>
                    </div>
                    <span class="badge bg-info-subtle text-info">Update Eviden (75%)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. TARGET MONITOR & SLA MONITOR -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6 col-12">
            <div class="oc-card p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-bullseye text-warning me-2"></i> Target Monitor (Harian, Mingguan, Bulanan)</h5>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-bold mb-1">
                        <span>Target Harian (15 / 20 Jobs)</span>
                        <span class="text-success">75%</span>
                    </div>
                    <div class="progress" style="height: 8px;"><div class="progress-bar bg-success" style="width: 75%;"></div></div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-bold mb-1">
                        <span>Target Mingguan (110 / 140 Jobs)</span>
                        <span class="text-primary">78%</span>
                    </div>
                    <div class="progress" style="height: 8px;"><div class="progress-bar bg-primary" style="width: 78%;"></div></div>
                </div>

                <div>
                    <div class="d-flex justify-content-between small fw-bold mb-1">
                        <span>Target Bulanan (450 / 500 Jobs)</span>
                        <span class="text-info">90%</span>
                    </div>
                    <div class="progress" style="height: 8px;"><div class="progress-bar bg-info" style="width: 90%;"></div></div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 col-12">
            <div class="oc-card p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-shield-heart text-success me-2"></i> SLA Monitor Compliance</h5>

                <div class="d-flex flex-wrap gap-2 text-center mb-3">
                    <div class="flex-fill p-3 bg-success-subtle rounded-3 border border-success-subtle">
                        <small class="text-success fw-bold d-block" style="font-size: 10px;">SESUAI SLA (HIJAU)</small>
                        <h4 class="fw-bold text-success mb-0">88.5%</h4>
                    </div>
                    <div class="flex-fill p-3 bg-warning-subtle rounded-3 border border-warning-subtle">
                        <small class="text-warning fw-bold d-block" style="font-size: 10px;">MENDEKATI (KUNING)</small>
                        <h4 class="fw-bold text-warning mb-0">7.2%</h4>
                    </div>
                    <div class="flex-fill p-3 bg-danger-subtle rounded-3 border border-danger-subtle">
                        <small class="text-danger fw-bold d-block" style="font-size: 10px;">MELEWATI (MERAH)</small>
                        <h4 class="fw-bold text-danger mb-0">4.3%</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. LIVE GIS MINI MAP & ACTIVITY STREAM -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8 col-12">
            <div class="oc-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-map-location-dot text-success me-2"></i> Live Map Status</h5>
                    <a href="<?= site_url('gis') ?>" class="btn btn-outline-primary btn-sm rounded-pill font-weight-bold"><i class="fas fa-expand me-1"></i> Open Full GIS</a>
                </div>
                <div id="live-command-map" style="height: 320px; border-radius: 14px;" class="border shadow-sm"></div>
            </div>
        </div>

        <div class="col-lg-4 col-12">
            <div class="oc-card p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-stream text-primary me-2"></i> Live Activity Stream</h5>
                <div class="activity-feed">
                    <div class="live-feed-line">
                        <span class="fw-bold text-dark d-block small">08:00 &middot; Input Temuan</span>
                        <small class="text-muted">Petugas &middot; ULP Sidoarjo Kota</small>
                    </div>
                    <div class="live-feed-line">
                        <span class="fw-bold text-dark d-block small">08:10 &middot; Update Pekerjaan</span>
                        <small class="text-muted">Tim PDKB &middot; Penyulang Klurak</small>
                    </div>
                    <div class="live-feed-line">
                        <span class="fw-bold text-dark d-block small">08:20 &middot; Work Order Approved</span>
                        <small class="text-muted">Supervisor ULP &middot; WO-00088</small>
                    </div>
                    <div class="live-feed-line">
                        <span class="fw-bold text-dark d-block small">08:30 &middot; Upload Foto Eviden</span>
                        <small class="text-muted">HAR Gardu &middot; Gardu SDJ-14</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 30-Second AJAX Live Metrics Polling
    var timerVal = 30;
    setInterval(function() {
        timerVal--;
        if (timerVal <= 0) {
            timerVal = 30;
            fetch("<?= site_url('status/live-metrics') ?>")
                .then(res => res.json())
                .then(data => {
                    var elOn = document.getElementById('live-cnt-online');
                    if (elOn && data.online_petugas) elOn.innerText = data.online_petugas;
                    var elEmg = document.getElementById('live-cnt-emergency');
                    if (elEmg && data.emergency_aktif !== undefined) elEmg.innerText = data.emergency_aktif;
                })
                .catch(err => console.log('Polling err:', err));
        }
        var timerEl = document.getElementById('live-timer');
        if (timerEl) timerEl.innerText = timerVal + 's';
    }, 1000);

    // Leaflet Live Map
    var map = L.map('live-command-map').setView([-7.4478, 112.7183], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    var pins = <?= json_encode($mapPins ?? []) ?>;
    pins.forEach(function(p) {
        if (p.latitude && p.longitude) {
            var color = '#10b981';
            if (p.prioritas === 'EMERGENCY') color = '#ef4444';
            else if (p.prioritas === 'HIGH') color = '#f59e0b';

            L.circleMarker([p.latitude, p.longitude], {
                radius: 7, fillColor: color, color: '#fff', weight: 2, fillOpacity: 0.9
            }).addTo(map).bindPopup('<b>' + (p.nomor_temuan || 'Temuan') + '</b><br>' + (p.detail_temuan || ''));
        }
    });

    // Voice Command Listener Trigger
    var voiceBtn = document.getElementById('btn-voice-trigger');
    if (voiceBtn) {
        voiceBtn.addEventListener('click', function() {
            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                var recognition = new SpeechRecognition();
                recognition.lang = 'id-ID';
                recognition.start();

                alert('Listening Voice Command... Silakan bicara (e.g. "Buka GIS", "Tampilkan Emergency")');

                recognition.onresult = function(event) {
                    var cmd = event.results[0][0].transcript.toLowerCase();
                    alert('Command terdeteksi: "' + cmd + '"');
                    if (cmd.includes('gis') || cmd.includes('peta')) {
                        window.location.href = "<?= site_url('gis') ?>";
                    } else if (cmd.includes('emergency') || cmd.includes('darurat')) {
                        window.location.href = "<?= site_url('temuan?prioritas=EMERGENCY') ?>";
                    } else if (cmd.includes('input')) {
                        window.location.href = "<?= site_url('temuan/create') ?>";
                    }
                };
            } else {
                alert('Browser Anda tidak mendukung Voice Command Web Speech API.');
            }
        });
    }
});
</script>
<?= $this->endSection() ?>
