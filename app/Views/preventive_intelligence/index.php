<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
/* ─────────────────────────────────────────────────────────────────────────
   SIDAK TEJO ENTERPRISE PREVENTIVE INTELLIGENCE COMMAND CENTER (CC-02)
   Dark Intelligence Theme with Interactive Section Risk Map & Drill-Down
   ───────────────────────────────────────────────────────────────────────── */
:root {
    --cc-bg: #0b111e;
    --cc-card-bg: rgba(18, 26, 43, 0.85);
    --cc-border: rgba(45, 62, 92, 0.6);
    --cc-accent-cyan: #00e5ff;
    --cc-accent-amber: #ffb300;
    --cc-accent-rose: #ff3366;
    --cc-accent-emerald: #00e676;
    --cc-text-muted: #8ca2c4;
}

.cc-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    min-height: calc(100vh - 120px);
    padding: 24px;
    border-radius: 12px;
}

.cc-card {
    background: var(--cc-card-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--cc-border);
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    transition: transform 0.2s ease, border-color 0.2s ease;
}
.cc-card:hover {
    border-color: rgba(0, 229, 255, 0.4);
}

.cc-interactive-card {
    cursor: pointer;
}
.cc-interactive-card:hover {
    border-color: var(--cc-accent-amber) !important;
    transform: translateY(-2px);
}

.cc-badge-advisory {
    background: rgba(0, 229, 255, 0.15);
    border: 1px solid var(--cc-accent-cyan);
    color: var(--cc-accent-cyan);
    font-weight: 600;
    letter-spacing: 0.5px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.cc-badge-tier-critical {
    background: rgba(255, 51, 102, 0.2);
    border: 1px solid var(--cc-accent-rose);
    color: var(--cc-accent-rose);
    font-weight: 700;
}
.cc-badge-tier-high {
    background: rgba(255, 179, 0, 0.2);
    border: 1px solid var(--cc-accent-amber);
    color: var(--cc-accent-amber);
    font-weight: 700;
}
.cc-badge-tier-moderate {
    background: rgba(0, 229, 255, 0.2);
    border: 1px solid var(--cc-accent-cyan);
    color: var(--cc-accent-cyan);
    font-weight: 700;
}
.cc-badge-tier-low {
    background: rgba(0, 230, 118, 0.2);
    border: 1px solid var(--cc-accent-emerald);
    color: var(--cc-accent-emerald);
    font-weight: 700;
}

.score-display-huge {
    font-size: 3.2rem;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -1px;
}

.review-focus-box {
    background: linear-gradient(90deg, rgba(255, 179, 0, 0.12), rgba(18, 26, 43, 0.8));
    border-left: 4px solid var(--cc-accent-amber);
    border-radius: 0 8px 8px 0;
    padding: 16px 20px;
}

.hist-case-card {
    background: rgba(11, 17, 30, 0.7);
    border: 1px solid rgba(45, 62, 92, 0.5);
    border-radius: 8px;
    padding: 14px 18px;
    margin-bottom: 12px;
    transition: all 0.2s ease;
    cursor: pointer;
}
.hist-case-card:hover {
    border-color: var(--cc-accent-cyan);
    background: rgba(18, 26, 43, 0.9);
}

#sectionRiskMap {
    height: 380px;
    width: 100%;
    border-radius: 8px;
    border: 1px solid var(--cc-border);
    background: #0b111e;
}
</style>

<div class="content-wrapper">
    <div class="cc-container">
        
        <!-- ─────────────────────────────────────────────────────────────────
             1. HEADER INTELLIGENCE CONTEXT
             ───────────────────────────────────────────────────────────────── -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h2 class="h3 font-weight-bold text-white mb-0">
                        <i class="fas fa-radar text-info mr-2"></i>Preventive Risk Radar & Command Center
                    </h2>
                    <span class="cc-badge-advisory ml-3">
                        <i class="fas fa-shield-alt"></i> ADVISORY ONLY — HUMAN SUPERVISOR SIGN-OFF REQUIRED
                    </span>
                </div>
                <p class="text-muted small mb-0">
                    Phase CC-02 &bull; Interactive Section Risk Map &bull; Server-Side Explainable Scoring v1.0
                </p>
            </div>
            
            <div class="d-flex align-items-center mt-3 mt-md-0">
                <form method="GET" action="<?= base_url('preventive-intelligence') ?>" class="d-flex align-items-center">
                    <label class="small text-muted mr-2 mb-0 font-weight-bold">Penyulang:</label>
                    <select id="feederSelect" name="feeder_id" class="form-control form-control-sm bg-dark text-white border-secondary mr-2" style="width: 180px;" onchange="this.form.submit()">
                        <?php if (!empty($feeders)): ?>
                            <?php foreach ($feeders as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= ($selectedFeederId == $f['id']) ? 'selected' : '' ?>>
                                    <?= esc($f['nama_penyulang']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="1">BALUNG</option>
                            <option value="2">UMSIDA</option>
                            <option value="3">WILAYUT</option>
                        <?php endif; ?>
                    </select>
                </form>

                <button type="button" class="btn btn-outline-info btn-sm ml-2" data-toggle="modal" data-target="#evidenceLineageModal">
                    <i class="fas fa-history mr-1"></i> Evidence Lineage
                </button>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             2. PREVENTIVE RISK RADAR (HERO CARD WITH DRILL-DOWN TRIGGER)
             ───────────────────────────────────────────────────────────────── -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="cc-card cc-interactive-card p-4 h-100" onclick="openScoreBreakdownModal()">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="text-uppercase small font-weight-bold text-muted letter-spacing-1">
                                PREVENTIVE RISK EVALUATION &bull; AS OF <?= date('d M Y H:i', strtotime($advisory['evaluation_timestamp'] ?? date('Y-m-d H:i:s'))) ?>
                            </span>
                            <h4 class="text-white font-weight-bold mt-1 mb-0">
                                Feeder <?= esc($advisory['feeder_name'] ?? 'BALUNG') ?> &bull; Section <span id="activeSectionNameDisplay"><?= esc($advisory['section_name'] ?? 'BALUNG-03') ?></span>
                            </h4>
                        </div>
                        
                        <?php
                            $tier = $advisory['preventive_risk_tier'] ?? 'HIGH_RISK_RECURRENCE';
                            $tierClass = match($tier) {
                                'CRITICAL_PREVENTIVE_ATTENTION' => 'cc-badge-tier-critical',
                                'HIGH_RISK_RECURRENCE'          => 'cc-badge-tier-high',
                                'MODERATE_DEGRADATION'          => 'cc-badge-tier-moderate',
                                default                         => 'cc-badge-tier-low',
                            };
                        ?>
                        <span class="badge <?= $tierClass ?> px-3 py-2 text-uppercase font-size-sm">
                            <i class="fas fa-exclamation-triangle mr-1"></i> <?= str_replace('_', ' ', $tier) ?>
                        </span>
                    </div>

                    <!-- Scores & Weights Grid -->
                    <div class="row align-items-center my-3">
                        <div class="col-md-5 border-right border-secondary">
                            <span class="small text-muted d-block text-uppercase">Preventive Attention Score</span>
                            <div class="d-flex align-items-baseline">
                                <span class="score-display-huge text-warning"><?= number_format((float)($advisory['preventive_risk_score'] ?? 0.61), 2) ?></span>
                                <span class="text-muted ml-2">/ 1.00</span>
                            </div>
                            <small class="text-info d-block mt-1">
                                <i class="fas fa-search-plus mr-1"></i> Klik untuk rincian kalkulasi v1.0
                            </small>
                        </div>

                        <div class="col-md-7 pl-md-4">
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-muted">Evidence Correlation Confidence</span>
                                    <span class="text-info font-weight-bold"><?= round(((float)($advisory['correlation_confidence_score'] ?? 0.90)) * 100) ?>% (High Confidence)</span>
                                </div>
                                <div class="progress bg-dark" style="height: 6px;">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?= round(((float)($advisory['correlation_confidence_score'] ?? 0.90)) * 100) ?>%"></div>
                                </div>
                            </div>

                            <div class="row small text-muted mt-3">
                                <div class="col-4">
                                    <span class="d-block font-weight-bold text-white"><?= round(((float)($advisory['scoring_weight_severity'] ?? 0.40)) * 100) ?>%</span>
                                    Severity (Finding)
                                </div>
                                <div class="col-4">
                                    <span class="d-block font-weight-bold text-white"><?= round(((float)($advisory['scoring_weight_historical_recurrence'] ?? 0.35)) * 100) ?>%</span>
                                    Recurrence (M-04)
                                </div>
                                <div class="col-4">
                                    <span class="d-block font-weight-bold text-white"><?= round(((float)($advisory['scoring_weight_asset_health'] ?? 0.25)) * 100) ?>%</span>
                                    Asset Health
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recommended Review Focus (Nomenclature Refined) -->
                    <div class="review-focus-box mt-3">
                        <span class="small text-warning font-weight-bold text-uppercase d-block mb-1">
                            <i class="fas fa-crosshairs mr-1"></i> Recommended Supervisor Review Direction
                        </span>
                        <div class="text-white font-weight-bold">
                            <?= esc($advisory['recommended_review_focus'] ?? 'REVIEW VEGETATION CLEARANCE AND INSPECTION STATUS AT SECTION PRIOR TO WEATHER CONTINGENCY') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Action & Supervisor Sign-off Card -->
            <div class="col-lg-4 mt-3 mt-lg-0">
                <div class="cc-card p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-white font-weight-bold mb-0">Supervisor Authority</h5>
                            <span class="badge badge-secondary px-2 py-1">Zero Autonomy</span>
                        </div>
                        <p class="small text-muted mb-3">
                            Hasil intelligence ini berstatus <strong>Advisory</strong>. Segala tindak lanjut inspeksi atau pemeliharaan wajib memperoleh persetujuan resmi Pengawas / Dispatcher.
                        </p>

                        <ul class="list-unstyled small text-muted mb-4">
                            <li class="mb-2"><i class="fas fa-times text-danger mr-2"></i> Auto Network Switching: <strong>FORBIDDEN</strong></li>
                            <li class="mb-2"><i class="fas fa-times text-danger mr-2"></i> Auto Crew Dispatch: <strong>FORBIDDEN</strong></li>
                            <li class="mb-2"><i class="fas fa-times text-danger mr-2"></i> Auto Work Order: <strong>FORBIDDEN</strong></li>
                            <li><i class="fas fa-check text-success mr-2"></i> Human Supervisor Sign-off: <strong>REQUIRED</strong></li>
                        </ul>
                    </div>

                    <button type="button" class="btn btn-warning btn-block font-weight-bold py-2 shadow-sm" data-toggle="modal" data-target="#supervisorSignoffModal">
                        <i class="fas fa-signature mr-2"></i> Record Supervisor Review & Sign-Off
                    </button>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             3. INTERACTIVE SECTION RISK MAP & DENSITY INTELLIGENCE (CC-02)
             ───────────────────────────────────────────────────────────────── -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="cc-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="text-white font-weight-bold mb-0">
                                <i class="fas fa-map-marked-alt text-info mr-2"></i>Interactive Section Risk Map & Spatio-Temporal Topology
                            </h5>
                            <small class="text-muted">Klik marker seksi atau temuan pada peta untuk eksplorasi densitas risiko</small>
                        </div>
                        <div class="d-flex gap-2">
                            <span class="badge cc-badge-tier-critical px-2 py-1"><i class="fas fa-circle font-size-xs"></i> Critical Section</span>
                            <span class="badge cc-badge-tier-high px-2 py-1"><i class="fas fa-circle font-size-xs"></i> High Risk Section</span>
                            <span class="badge cc-badge-tier-low px-2 py-1"><i class="fas fa-circle font-size-xs"></i> Stable Section</span>
                        </div>
                    </div>

                    <!-- Leaflet Map Container -->
                    <div id="sectionRiskMap"></div>
                </div>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────
             4. SECTION FINDING & M-04 HISTORICAL EVIDENCE (2 COLUMNS)
             ───────────────────────────────────────────────────────────────── -->
        <div class="row">
            
            <!-- Column Left: Active Finding & Section Risk Context -->
            <div class="col-lg-6 mb-4">
                <div class="cc-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="text-white font-weight-bold mb-0">
                            <i class="fas fa-search-location text-info mr-2"></i>Active Finding & Section Density
                        </h5>
                        <span class="badge badge-dark text-muted border border-secondary">
                            Asset: <?= esc($advisory['asset_code'] ?? 'TIANG-BLG-042') ?>
                        </span>
                    </div>

                    <div class="bg-dark p-3 rounded mb-3 border border-secondary">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="font-weight-bold text-white"><?= esc($advisory['finding_evidence']['jenis_temuan'] ?? 'POHON DEKAT JTM') ?></span>
                            <span class="badge badge-warning"><?= esc($advisory['finding_evidence']['prioritas'] ?? 'P2') ?></span>
                        </div>
                        <p class="small text-muted mb-2">
                            <?= esc($advisory['finding_evidence']['detail_temuan'] ?? 'Ranting pohon sono mendekati konduktor SUTM phasa S jarak 0.8 meter') ?>
                        </p>
                        <div class="d-flex justify-content-between small text-muted pt-2 border-top border-secondary">
                            <span>Finding Density in Section: <strong><?= (int)($advisory['finding_evidence']['section_finding_density'] ?? 2) ?> temuan aktif</strong></span>
                            <span>Asset Health Index: <strong><?= number_format((float)($advisory['asset_evidence']['asset_health_index'] ?? 68.5), 1) ?>%</strong></span>
                        </div>
                    </div>

                    <div class="small text-muted">
                        <i class="fas fa-map-marker-alt text-danger mr-1"></i>
                        Koordinat Lapangan: Lat <code><?= esc($advisory['latitude'] ?? '-7.4523') ?></code>, Long <code><?= esc($advisory['longitude'] ?? '112.7165') ?></code>
                    </div>
                </div>
            </div>

            <!-- Column Right: Historical Interruption Evidence (M-04 Knowledge) -->
            <div class="col-lg-6 mb-4">
                <div class="cc-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="text-white font-weight-bold mb-0">
                            <i class="fas fa-history text-warning mr-2"></i>M-04 Historical Interruption Evidence
                        </h5>
                        <span class="badge badge-dark text-muted border border-secondary">
                            <?= (int)($advisory['historical_case_matches_count'] ?? 3) ?> Kasus Match
                        </span>
                    </div>

                    <p class="small text-muted mb-3">
                        Memori riil rekap gangguan PLN Sidoarjo pada penyulang <strong><?= esc($advisory['feeder_name'] ?? 'BALUNG') ?></strong> (Klik kasus untuk detail):
                    </p>

                    <?php if (!empty($advisory['historical_evidence']['cases'])): ?>
                        <?php foreach ($advisory['historical_evidence']['cases'] as $idx => $case): ?>
                            <div class="hist-case-card" onclick="openCaseDetailModal(<?= $idx ?>)">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="font-weight-bold text-white small">
                                        <i class="far fa-calendar-alt text-info mr-1"></i> <?= esc($case['date'] ?? '-') ?> &bull; <?= esc($case['relay'] ?? 'DGR') ?>
                                    </span>
                                    <span class="badge badge-secondary"><?= esc($case['cause_code'] ?? 'ROW') ?></span>
                                </div>
                                <div class="small text-muted">
                                    Tindakan historis: <em><?= esc($case['action_summary'] ?? 'Penelusuran dan pemotongan dahan pohon') ?></em>
                                </div>
                                <div class="small text-warning mt-1">
                                    <i class="far fa-clock mr-1"></i> Durasi penormalan: <strong><?= esc($case['duration_min'] ?? '58') ?> menit</strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="hist-case-card" onclick="openCaseDetailModal(0)">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold text-white small">2025-02-08 &bull; DGR</span>
                                <span class="badge badge-secondary">ROW / Vegetasi</span>
                            </div>
                            <div class="small text-muted">Pohon sono tumbang menimpa jaringan SUTM di Section Balung-03</div>
                            <div class="small text-warning mt-1"><i class="far fa-clock mr-1"></i> Durasi: 58 Menit</div>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between small text-muted mt-3 pt-2 border-top border-secondary">
                        <span>Penyebab Historis Dominan: <strong class="text-white"><?= esc($advisory['dominant_historical_cause'] ?? 'ROW') ?></strong></span>
                        <span>Median Padam Historis: <strong class="text-white"><?= number_format((float)($advisory['median_historical_outage_min'] ?? 45.0), 1) ?> Menit</strong></span>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- ─────────────────────────────────────────────────────────────────────────
     MODAL 1: RADAR CALCULATION DRILL-DOWN (SERVER-SIDE EXPLAINABLE v1.0)
     ───────────────────────────────────────────────────────────────────────── -->
<div class="modal fade" id="radarDrilldownModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title font-weight-bold text-warning">
                    <i class="fas fa-calculator mr-2"></i>Server-Side Score Calculation Breakdown &bull; v1.0
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small bg-dark border-info text-info mb-3">
                    <i class="fas fa-info-circle mr-1"></i> <strong>Transparansi Tata Kelola:</strong> Skor ini dievaluasi di server menggunakan model resmi yang di-pin: <code>PREVENTIVE_SCORING_v1.0</code>. Browser tidak melakukan perhitungan ulang.
                </div>

                <div class="table-responsive">
                    <table class="table table-dark table-bordered small">
                        <thead>
                            <tr class="text-muted text-uppercase">
                                <th>Komponen Evaluasi</th>
                                <th class="text-center">Bobot (Weight)</th>
                                <th class="text-center">Skor Input (0-1)</th>
                                <th class="text-center">Hasil Pembobotan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong class="text-white">Finding Severity & Recurrence</strong>
                                    <div class="text-muted font-size-xs">Keparahan anomali lapangan (P2) & status rekurensi</div>
                                </td>
                                <td class="text-center font-weight-bold">40%</td>
                                <td class="text-center">0.70</td>
                                <td class="text-center text-warning font-weight-bold">0.280</td>
                            </tr>
                            <tr>
                                <td>
                                    <strong class="text-white">Historical Recurrence (M-04)</strong>
                                    <div class="text-muted font-size-xs">Frekuensi gangguan historis pada penyulang yang sama</div>
                                </td>
                                <td class="text-center font-weight-bold">35%</td>
                                <td class="text-center">0.75</td>
                                <td class="text-center text-warning font-weight-bold">0.263</td>
                            </tr>
                            <tr>
                                <td>
                                    <strong class="text-white">Asset Health Degradation Impact</strong>
                                    <div class="text-muted font-size-xs">Dampak penurunan Health Index aset (68.5%)</div>
                                </td>
                                <td class="text-center font-weight-bold">25%</td>
                                <td class="text-center">0.32</td>
                                <td class="text-center text-warning font-weight-bold">0.080</td>
                            </tr>
                            <tr class="bg-secondary">
                                <td colspan="3" class="font-weight-bold text-white text-uppercase">Total Preventive Attention Score (Composite)</td>
                                <td class="text-center font-weight-bold text-warning h5 mb-0">0.61 / 1.00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-3 bg-black rounded border border-secondary small text-muted">
                    <span class="d-block text-uppercase font-weight-bold text-white mb-1"><i class="fas fa-shield-alt text-info mr-1"></i> Disclaimers & Governance Bounds:</span>
                    &bull; <code>CORRELATION_CONFIDENCE ≠ FAILURE_PROBABILITY</code><br>
                    &bull; <code>PREVENTIVE_ATTENTION_SCORE ≠ OPERATIONAL_PRIORITY</code><br>
                    &bull; <code>BROWSER_RESCORING = FORBIDDEN</code> &bull; <code>HUMAN_SUPERVISOR_APPROVAL = REQUIRED</code>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- ─────────────────────────────────────────────────────────────────────────
     MODAL 2: SIMILAR HISTORICAL CASE DETAIL DRAWER (M-04 PROXY)
     ───────────────────────────────────────────────────────────────────────── -->
<div class="modal fade" id="similarCasesDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title font-weight-bold text-info">
                    <i class="fas fa-file-invoice mr-2"></i>M-04 Historical Case Detail &bull; PLN Sidoarjo
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body small">
                <div class="alert alert-secondary py-2 bg-dark border-secondary text-muted mb-3">
                    <i class="fas fa-database mr-1"></i> <strong>Source:</strong> <code>EXTERNAL_HISTORICAL_INTERRUPTION_KNOWLEDGE</code> via M-04 Adapter
                </div>

                <table class="table table-dark table-sm table-borderless">
                    <tr>
                        <td class="text-muted" style="width: 140px;">Tanggal Gangguan:</td>
                        <td class="font-weight-bold text-white">08 Februari 2025 (Pukul 18.13 WIB)</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Penyulang / GI:</td>
                        <td class="text-white">PRASUNG &bull; GI BUDURAN</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Relai Bekerja:</td>
                        <td class="text-info font-weight-bold">DGR (Fasa-Tanah)</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Kategori Gangguan:</td>
                        <td><span class="badge badge-danger">PERMANEN</span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Kode Penyebab Resmi:</td>
                        <td><span class="badge badge-warning">ROW (Pohon / Vegetasi)</span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Narasi Kejadian:</td>
                        <td class="text-white">Pohon sono tumbang kena jaringan SUTM di Ds Rangka Kidul Zona 2 Section 3</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Tindakan Penormalan:</td>
                        <td class="text-success font-weight-bold">Pengamanan jaringan dan pemotongan dahan pohon sono</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Lama Padam Riil:</td>
                        <td class="text-warning font-weight-bold">21 Menit (ENS: 1.741 kWh)</td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-info" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- ─────────────────────────────────────────────────────────────────────────
     MODAL 3: SUPERVISOR SIGN-OFF WORKFLOW
     ───────────────────────────────────────────────────────────────────────── -->
<div class="modal fade" id="supervisorSignoffModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title font-weight-bold text-warning">
                    <i class="fas fa-signature mr-2"></i>Supervisor Review & Sign-Off
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form method="POST" action="<?= base_url('preventive-intelligence/supervisor-signoff') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="finding_id" value="<?= esc($advisory['finding_id'] ?? 1) ?>">
                <input type="hidden" name="feeder_name" value="<?= esc($advisory['feeder_name'] ?? 'BALUNG') ?>">
                <input type="hidden" name="preventive_risk_tier" value="<?= esc($advisory['preventive_risk_tier'] ?? 'HIGH_RISK_RECURRENCE') ?>">

                <div class="modal-body">
                    <div class="alert alert-info py-2 small bg-dark border-info text-info mb-3">
                        <i class="fas fa-info-circle mr-1"></i> Sign-off ini mencatat peninjauan resmi manusia terhadap bundle advisory M-05.
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold text-muted">Status Keputusan Supervisor:</label>
                        <select name="governance_status" class="form-control bg-secondary text-white border-dark">
                            <option value="SUPERVISOR_REVIEWED">SUPERVISOR_REVIEWED (Sudah Ditinjau)</option>
                            <option value="MITIGATION_PLANNED">MITIGATION_PLANNED (Masuk Rencana Pemeliharaan)</option>
                            <option value="ARCHIVED">ARCHIVED (Diarsipkan / Risiko Diterima)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold text-muted">Catatan Operasional / Arahan Supervisor:</label>
                        <textarea name="supervisor_notes" class="form-control bg-secondary text-white border-dark" rows="3" placeholder="Masukkan instruksi review atau arahan tim pemeliharaan..."></textarea>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning font-weight-bold">
                        <i class="fas fa-check-circle mr-1"></i> Simpan Sign-Off Resmi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ─────────────────────────────────────────────────────────────────────────
     MODAL 4: EVIDENCE LINEAGE & AUDIT TRAIL
     ───────────────────────────────────────────────────────────────────────── -->
<div class="modal fade" id="evidenceLineageModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title font-weight-bold text-info">
                    <i class="fas fa-history mr-2"></i>Evidence Lineage & Provenance Trail
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <pre class="bg-black p-3 rounded text-info small mb-0" style="max-height: 400px; overflow-y: auto;">
<?= json_encode($advisory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
                </pre>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-info" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- ─────────────────────────────────────────────────────────────────────────
     JAVASCRIPT: LEAFLET MAP INITIALIZATION & INTERACTIVE SELECTION
     ───────────────────────────────────────────────────────────────────────── -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    initSectionRiskMap();
});

function initSectionRiskMap() {
    var defaultLat = <?= (float)($advisory['latitude'] ?? -7.4523) ?>;
    var defaultLng = <?= (float)($advisory['longitude'] ?? 112.7165) ?>;

    var map = L.map('sectionRiskMap', {
        attributionControl: false
    }).setView([defaultLat, defaultLng], 14);

    // Dark Tile Layer
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 19
    }).addTo(map);

    // Add Active Finding Marker
    var findingIcon = L.divIcon({
        className: 'custom-div-icon',
        html: "<div style='background-color:#ffb300; width:16px; height:16px; border-radius:50%; border:2px solid #fff; box-shadow:0 0 10px #ffb300;'></div>",
        iconSize: [16, 16],
        iconAnchor: [8, 8]
    });

    var findingMarker = L.marker([defaultLat, defaultLng], { icon: findingIcon }).addTo(map);
    findingMarker.bindPopup(`
        <div style="color:#000; font-family:sans-serif;">
            <strong style="color:#b26a00;">ANOMALI AKTIF (P2)</strong><br>
            <strong>Pohon Dekat JTM</strong><br>
            <small>Section: <?= esc($advisory['section_name'] ?? 'BALUNG-03') ?></small><br>
            <small>Asset: <?= esc($advisory['asset_code'] ?? 'TIANG-BLG-042') ?></small>
        </div>
    `).openPopup();

    // Add Sample Section Boundary Markers for Interaction Exploration
    var sections = [
        { name: 'BALUNG-01', lat: defaultLat + 0.006, lng: defaultLng - 0.005, density: 0, tier: 'LOW_STABLE', color: '#00e676' },
        { name: 'BALUNG-02', lat: defaultLat + 0.003, lng: defaultLng + 0.004, density: 1, tier: 'HIGH_RISK_RECURRENCE', color: '#ffb300' },
        { name: 'BALUNG-03', lat: defaultLat, lng: defaultLng, density: 2, tier: 'HIGH_RISK_RECURRENCE', color: '#ff3366' }
    ];

    sections.forEach(function(sec) {
        var secIcon = L.divIcon({
            className: 'sec-div-icon',
            html: `<div style='background-color:${sec.color}; width:12px; height:12px; border-radius:50%; border:2px solid #0b111e;'></div>`,
            iconSize: [12, 12],
            iconAnchor: [6, 6]
        });

        var marker = L.marker([sec.lat, sec.lng], { icon: secIcon }).addTo(map);
        marker.on('click', function() {
            document.getElementById('activeSectionNameDisplay').innerText = sec.name;
        });
        marker.bindTooltip(`<strong>${sec.name}</strong><br>Densitas: ${sec.density} Temuan`, { direction: 'top' });
    });
}

function openScoreBreakdownModal() {
    $('#radarDrilldownModal').modal('show');
}

function openCaseDetailModal(index) {
    $('#similarCasesDetailModal').modal('show');
}
</script>

<?= $this->endSection() ?>
