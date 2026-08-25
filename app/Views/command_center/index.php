<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Operational Risk Radar & Preventive Intelligence<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- ========================================================================= -->
<!-- ENTERPRISE DARK COMMAND CENTER WORKSPACE (STEP 6)                         -->
<!-- Protocol: Signal > Decoration • Priority > Volume • Context > Card Count  -->
<!-- ========================================================================= -->

<style>
/* Enterprise Dark Control Room Palette */
:root {
    --cc-bg: #0b0f19;
    --cc-surface: #111827;
    --cc-surface-elevated: #1f2937;
    --cc-surface-card: #151d30;
    --cc-border: #283548;
    --cc-border-focus: #3b82f6;
    --cc-text-main: #f3f4f6;
    --cc-text-muted: #94a3b8;
    --cc-text-dim: #64748b;
    
    --cc-critical: #ef4444;
    --cc-critical-bg: rgba(239, 68, 68, 0.12);
    --cc-critical-border: rgba(239, 68, 68, 0.4);
    
    --cc-high: #f59e0b;
    --cc-high-bg: rgba(245, 158, 11, 0.12);
    --cc-high-border: rgba(245, 158, 11, 0.4);
    
    --cc-moderate: #3b82f6;
    --cc-moderate-bg: rgba(59, 130, 246, 0.12);
    --cc-moderate-border: rgba(59, 130, 246, 0.4);
    
    --cc-low: #10b981;
    --cc-low-bg: rgba(16, 185, 129, 0.12);
    --cc-low-border: rgba(16, 185, 129, 0.4);
}

.cc-workspace {
    background-color: var(--cc-bg);
    color: var(--cc-text-main);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    padding: 16px;
    border-radius: 10px;
    box-shadow: inset 0 0 20px rgba(0,0,0,0.5);
}

/* Banner */
.cc-banner {
    background: linear-gradient(135deg, #111827 0%, #172033 100%);
    border: 1px solid var(--cc-border);
    border-radius: 8px;
    padding: 16px 20px;
    margin-bottom: 16px;
}
.cc-banner-title {
    font-size: 1.25rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: #ffffff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.cc-live-pulse {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: ccPulse 2s infinite;
}
@keyframes ccPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* Macro Stats Grid */
.cc-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-top: 14px;
}
.cc-stat-card {
    background-color: var(--cc-surface-card);
    border: 1px solid var(--cc-border);
    border-radius: 6px;
    padding: 12px 14px;
    position: relative;
    overflow: hidden;
}
.cc-stat-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    background-color: var(--cc-border);
}
.cc-stat-card.critical::before { background-color: var(--cc-critical); }
.cc-stat-card.high::before { background-color: var(--cc-high); }
.cc-stat-card.moderate::before { background-color: var(--cc-moderate); }
.cc-stat-card.low::before { background-color: var(--cc-low); }

.cc-stat-label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--cc-text-muted);
    font-weight: 600;
}
.cc-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #ffffff;
    font-family: "SF Mono", Monaco, Consolas, monospace;
    line-height: 1.2;
    margin: 4px 0 2px 0;
}
.cc-stat-sub {
    font-size: 0.72rem;
    color: var(--cc-text-dim);
}

/* Command Filter Bar */
.cc-filter-bar {
    background-color: var(--cc-surface);
    border: 1px solid var(--cc-border);
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}
.cc-filter-item {
    flex: 1 1 180px;
    min-width: 140px;
}
.cc-filter-label {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--cc-text-muted);
    margin-bottom: 3px;
    display: block;
}
.cc-select {
    width: 100%;
    background-color: #0f172a;
    border: 1px solid var(--cc-border);
    color: var(--cc-text-main);
    border-radius: 5px;
    padding: 6px 10px;
    font-size: 0.82rem;
    outline: none;
    transition: border-color 0.15s ease;
}
.cc-select:focus {
    border-color: var(--cc-border-focus);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}
.cc-btn {
    border-radius: 5px;
    padding: 6px 14px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    border: 1px solid transparent;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.cc-btn-primary {
    background-color: #2563eb;
    color: #ffffff;
}
.cc-btn-primary:hover {
    background-color: #1d4ed8;
}
.cc-btn-outline {
    background-color: transparent;
    border-color: var(--cc-border);
    color: var(--cc-text-muted);
}
.cc-btn-outline:hover {
    background-color: var(--cc-surface-elevated);
    color: #ffffff;
}

/* Action Queue & Radar Layout */
.cc-main-grid {
    display: grid;
    grid-template-columns: 1.15fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}
@media (max-width: 1024px) {
    .cc-main-grid {
        grid-template-columns: 1fr;
    }
}

/* Panel Containers */
.cc-panel {
    background-color: var(--cc-surface);
    border: 1px solid var(--cc-border);
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.cc-panel-header {
    background-color: #151e2f;
    border-bottom: 1px solid var(--cc-border);
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.cc-panel-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: #ffffff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.cc-panel-body {
    padding: 14px;
    flex: 1;
}

/* Action Queue Cards */
.cc-queue-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 620px;
    overflow-y: auto;
    padding-right: 4px;
}
.cc-queue-card {
    background-color: var(--cc-surface-card);
    border: 1px solid var(--cc-border);
    border-radius: 6px;
    padding: 12px 14px;
    transition: all 0.15s ease;
    position: relative;
}
.cc-queue-card:hover {
    border-color: #475569;
    background-color: #1a233a;
}
.cc-queue-card.rank-1 { border-left: 4px solid var(--cc-critical); }
.cc-queue-card.rank-2 { border-left: 4px solid var(--cc-high); }
.cc-queue-card.rank-3 { border-left: 4px solid var(--cc-moderate); }
.cc-queue-card.rank-default { border-left: 4px solid var(--cc-border); }

.cc-queue-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 6px;
}
.cc-rank-badge {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 4px;
    background-color: #1e293b;
    color: #94a3b8;
    font-family: monospace;
}
.cc-tier-badge {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.cc-tier-badge.critical { background-color: var(--cc-critical-bg); color: var(--cc-critical); border: 1px solid var(--cc-critical-border); }
.cc-tier-badge.high { background-color: var(--cc-high-bg); color: var(--cc-high); border: 1px solid var(--cc-high-border); }
.cc-tier-badge.moderate { background-color: var(--cc-moderate-bg); color: var(--cc-moderate); border: 1px solid var(--cc-moderate-border); }
.cc-tier-badge.low { background-color: var(--cc-low-bg); color: var(--cc-low); border: 1px solid var(--cc-low-border); }

.cc-action-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #f1f5f9;
    margin-bottom: 4px;
}
.cc-action-location {
    font-size: 0.75rem;
    color: var(--cc-text-muted);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.cc-action-recommendation {
    font-size: 0.78rem;
    background-color: rgba(15, 23, 42, 0.6);
    border-left: 2px solid #3b82f6;
    padding: 6px 10px;
    border-radius: 0 4px 4px 0;
    color: #e2e8f0;
    margin-bottom: 8px;
}
.cc-action-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.cc-score-pill {
    font-size: 0.72rem;
    font-weight: 700;
    font-family: monospace;
    color: #cbd5e1;
}

/* Radar & Tables */
.cc-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
}
.cc-table th {
    background-color: #0f172a;
    color: var(--cc-text-muted);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.68rem;
    letter-spacing: 0.04em;
    padding: 8px 10px;
    border-bottom: 1px solid var(--cc-border);
    text-align: left;
}
.cc-table td {
    padding: 8px 10px;
    border-bottom: 1px solid rgba(51, 65, 85, 0.4);
    color: var(--cc-text-main);
}
.cc-table tr:hover td {
    background-color: rgba(30, 41, 59, 0.5);
}

/* Tabs */
.cc-tabs {
    display: flex;
    border-bottom: 1px solid var(--cc-border);
    gap: 4px;
    margin-bottom: 12px;
}
.cc-tab {
    padding: 6px 12px;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--cc-text-muted);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    text-transform: uppercase;
    transition: all 0.15s ease;
}
.cc-tab:hover {
    color: #ffffff;
}
.cc-tab.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
}

/* Trend Badges */
.cc-trend {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 3px;
    display: inline-block;
}
.cc-trend.increasing { background: rgba(239, 68, 68, 0.2); color: #f87171; }
.cc-trend.stable { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
.cc-trend.decreasing { background: rgba(16, 185, 129, 0.2); color: #34d399; }
.cc-trend.insufficient { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }

/* Explainability Drawer / Modal */
.cc-modal-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(3px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.cc-modal-backdrop.open {
    display: flex;
}
.cc-modal {
    background-color: var(--cc-surface);
    border: 1px solid var(--cc-border);
    border-radius: 10px;
    width: 100%;
    max-width: 780px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.7);
    animation: ccSlideUp 0.2s ease-out;
}
@keyframes ccSlideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.cc-modal-header {
    background-color: #172033;
    padding: 14px 20px;
    border-bottom: 1px solid var(--cc-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 10;
}
.cc-modal-body {
    padding: 20px;
}
.cc-pillar-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin: 14px 0;
}
@media (max-width: 640px) {
    .cc-pillar-grid { grid-template-columns: 1fr; }
}
.cc-pillar-box {
    background-color: #0f172a;
    border: 1px solid var(--cc-border);
    border-radius: 6px;
    padding: 10px 12px;
}
.cc-pillar-weight {
    font-size: 0.65rem;
    font-weight: 700;
    color: #38bdf8;
    text-transform: uppercase;
}
.cc-pillar-val {
    font-size: 1.1rem;
    font-weight: 700;
    color: #ffffff;
    font-family: monospace;
    margin: 2px 0;
}

/* Mobile Action First */
@media (max-width: 767px) {
    .cc-workspace { padding: 10px; }
    .cc-banner-title { font-size: 1rem; }
    .cc-stat-grid { grid-template-columns: 1fr 1fr; }
    .cc-main-grid { display: flex; flex-direction: column; }
    .cc-queue-list { max-height: 480px; }
}
</style>

<div class="content-wrapper p-2" style="background-color: #0b0f19;">
    <div class="container-fluid cc-workspace">

        <!-- 1. TOP INTELLIGENCE BANNER & LIVE STATUS -->
        <div class="cc-banner">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h2 class="cc-banner-title">
                        <span class="cc-live-pulse"></span>
                        SIDAK TEJO COMMAND CENTER
                        <span class="badge bg-primary text-white" style="font-size: 0.68rem; font-weight: normal;">PREVENTIVE RISK RADAR</span>
                    </h2>
                    <div style="font-size: 0.75rem; color: var(--cc-text-muted); margin-top: 3px;">
                        Live Grid Intelligence • Pinned Baseline M-04/M-05 (40/35/25) • Human Decision-Support Plane
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <span id="ccLastSync" style="font-size: 0.72rem; color: var(--cc-text-dim); font-family: monospace;">
                        Sync: Menghubungkan...
                    </span>
                    <button class="cc-btn cc-btn-outline" id="ccBtnRefresh" onclick="window.ccManager.refreshAll()">
                        <i class="fas fa-sync-alt" id="ccRefreshIcon"></i> Refresh Telemetry
                    </button>
                </div>
            </div>

            <!-- Macro Stats Banner -->
            <div class="cc-stat-grid" id="ccMacroStats">
                <div class="cc-stat-card">
                    <div class="cc-stat-label">System Risk Index</div>
                    <div class="cc-stat-value text-info" id="statRiskIndex">--</div>
                    <div class="cc-stat-sub">Rata-rata tertimbang M-05</div>
                </div>
                <div class="cc-stat-card critical">
                    <div class="cc-stat-label">Critical Attention</div>
                    <div class="cc-stat-value text-danger" id="statCritical">--</div>
                    <div class="cc-stat-sub">Prioritas tindakan darurat</div>
                </div>
                <div class="cc-stat-card high">
                    <div class="cc-stat-label">High Recurrence</div>
                    <div class="cc-stat-value text-warning" id="statHigh">--</div>
                    <div class="cc-stat-sub">Anomali rawan trip</div>
                </div>
                <div class="cc-stat-card moderate">
                    <div class="cc-stat-label">Active Queued Actions</div>
                    <div class="cc-stat-value text-primary" id="statQueued">--</div>
                    <div class="cc-stat-sub">Temuan terbuka siap mitigasi</div>
                </div>
                <div class="cc-stat-card low">
                    <div class="cc-stat-label">Disturbance Provider</div>
                    <div class="cc-stat-value text-success" id="statProvider" style="font-size: 0.95rem; line-height: 1.6;">STANDBY</div>
                    <div class="cc-stat-sub" id="statProviderDesc">Safe Null Fallback</div>
                </div>
            </div>
        </div>

        <!-- 2. FILTER COMMAND BAR -->
        <div class="cc-filter-bar">
            <div class="cc-filter-item">
                <label class="cc-filter-label"><i class="fas fa-map-marker-alt mr-1"></i> Unit (ULP)</label>
                <select class="cc-select" id="filterUlp" onchange="window.ccManager.onFilterChange()">
                    <option value="">Semua ULP (4 Unit)</option>
                    <option value="1">ULP SIDOARJO KOTA</option>
                    <option value="2">ULP KRIAN</option>
                    <option value="3">ULP PORONG</option>
                    <option value="4">ULP SEDATI</option>
                </select>
            </div>

            <div class="cc-filter-item">
                <label class="cc-filter-label"><i class="fas fa-bolt mr-1"></i> Penyulang</label>
                <select class="cc-select" id="filterPenyulang" onchange="window.ccManager.onFilterChange()">
                    <option value="">Semua Penyulang (134 Master)</option>
                </select>
            </div>

            <div class="cc-filter-item">
                <label class="cc-filter-label"><i class="fas fa-shield-alt mr-1"></i> Risk Tier</label>
                <select class="cc-select" id="filterTier" onchange="window.ccManager.onFilterChange()">
                    <option value="">Semua Tier Risiko</option>
                    <option value="CRITICAL_PREVENTIVE_ATTENTION">CRITICAL (Darurat)</option>
                    <option value="HIGH_RISK_RECURRENCE">HIGH (Rekurensi Tinggi)</option>
                    <option value="MODERATE_DEGRADATION">MODERATE (Degradasi)</option>
                    <option value="LOW_STABLE">LOW (Stabil)</option>
                </select>
            </div>

            <div class="cc-filter-item">
                <label class="cc-filter-label"><i class="fas fa-tags mr-1"></i> Kategori Gangguan</label>
                <select class="cc-select" id="filterCategory" onchange="window.ccManager.onFilterChange()">
                    <option value="">Semua Kategori</option>
                    <option value="VEGETATION_ROW">Vegetasi / Pohon (ROW)</option>
                    <option value="EQUIPMENT_FAILURE">Kegagalan Peralatan</option>
                    <option value="LIGHTNING_SURGE">Surja Petir / Arrester</option>
                    <option value="EXTERNAL_FOREIGN_OBJECT">Benda Asing / Hewan</option>
                    <option value="GENERAL_ANOMALY">Anomali Umum</option>
                </select>
            </div>

            <div class="d-flex align-items-end gap-2" style="margin-top: 18px;">
                <button class="cc-btn cc-btn-primary" onclick="window.ccManager.applyFilters()">
                    <i class="fas fa-filter"></i> Terapkan
                </button>
                <button class="cc-btn cc-btn-outline" onclick="window.ccManager.resetFilters()">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </div>

        <!-- 3. MAIN DUAL-COLUMN INTELLIGENCE GRID -->
        <div class="cc-main-grid">

            <!-- COLUMN A: PRIORITY PREVENTIVE ACTION QUEUE -->
            <div class="cc-panel">
                <div class="cc-panel-header">
                    <h3 class="cc-panel-title">
                        <i class="fas fa-clipboard-check text-warning"></i>
                        Priority Preventive Action Queue
                    </h3>
                    <span id="queueBadgeCount" class="badge bg-primary text-white" style="font-size: 0.7rem;">0 Antrean</span>
                </div>
                <div class="cc-panel-body">
                    <div id="queueLoadingState" class="text-center py-4" style="color: var(--cc-text-muted);">
                        <i class="fas fa-circle-notch fa-spin fa-2x mb-2"></i>
                        <p class="small mb-0">Menyusun antrean tindakan preventif terkalibrasi...</p>
                    </div>
                    <div id="queueEmptyState" class="text-center py-4 d-none" style="color: var(--cc-text-dim);">
                        <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                        <p class="small mb-0">Tidak ada tindakan mendesak yang cocok dengan filter saat ini.</p>
                    </div>
                    <div id="queueCardList" class="cc-queue-list d-none">
                        <!-- Populated asynchronously -->
                    </div>
                </div>
            </div>

            <!-- COLUMN B: PREVENTIVE RISK RADAR & HOTSPOTS -->
            <div class="cc-panel">
                <div class="cc-panel-header">
                    <h3 class="cc-panel-title">
                        <i class="fas fa-satellite-dish text-info"></i>
                        Preventive Risk Radar & Hotspots
                    </h3>
                    <span class="badge bg-dark border border-secondary text-muted" style="font-size: 0.65rem;">M-05 CALIBRATED</span>
                </div>
                <div class="cc-panel-body">
                    
                    <!-- Tier Distribution Progress -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1" style="font-size: 0.72rem; color: var(--cc-text-muted);">
                            <span>Distribusi Tier Risiko Jaringan</span>
                            <span id="radarFindingsCount">429 Temuan Aktif</span>
                        </div>
                        <div class="progress" style="height: 10px; background-color: #0f172a; border: 1px solid var(--cc-border); border-radius: 4px;">
                            <div id="progCritical" class="progress-bar bg-danger" style="width: 0%" title="Critical"></div>
                            <div id="progHigh" class="progress-bar bg-warning" style="width: 0%" title="High"></div>
                            <div id="progModerate" class="progress-bar bg-primary" style="width: 0%" title="Moderate"></div>
                            <div id="progLow" class="progress-bar bg-success" style="width: 0%" title="Low"></div>
                        </div>
                    </div>

                    <!-- Top Vulnerable Feeders Table -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: #cbd5e1;">Top Vulnerable Feeders</span>
                            <small class="text-muted" style="font-size: 0.68rem;">Perhitungan agregat</small>
                        </div>
                        <div class="table-responsive" style="max-height: 230px; overflow-y: auto;">
                            <table class="cc-table">
                                <thead>
                                    <tr>
                                        <th>Penyulang</th>
                                        <th>ULP</th>
                                        <th class="text-center">Temuan</th>
                                        <th class="text-center">Rekur.</th>
                                        <th class="text-right">Risk Score</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyVulnerableFeeders">
                                    <tr><td colspan="5" class="text-center text-muted">Memuat data penyulang...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top Section Hotspots -->
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: #cbd5e1;">Seksi Jaringan Rawan Hotspot</span>
                            <small class="text-muted" style="font-size: 0.68rem;">Densitas Seksi</small>
                        </div>
                        <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                            <table class="cc-table">
                                <thead>
                                    <tr>
                                        <th>Nama Seksi</th>
                                        <th>Penyulang</th>
                                        <th class="text-center">Temuan</th>
                                        <th class="text-right">Tier</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodySectionHotspots">
                                    <tr><td colspan="4" class="text-center text-muted">Memuat hotspot seksi...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <!-- 4. RECURRING FINDING & DISTURBANCE INTELLIGENCE -->
        <div class="cc-panel">
            <div class="cc-panel-header">
                <h3 class="cc-panel-title">
                    <i class="fas fa-history text-success"></i>
                    Recurring Finding & Disturbance Intelligence
                </h3>
                <span class="badge bg-dark border border-secondary text-muted" style="font-size: 0.65rem;">5 PERSPEKTIF POLA</span>
            </div>
            <div class="cc-panel-body">
                <div class="cc-tabs">
                    <button class="cc-tab active" onclick="window.ccManager.switchRecurringTab('findings', this)">Temuan Berulang</button>
                    <button class="cc-tab" onclick="window.ccManager.switchRecurringTab('components', this)">Kluster Komponen</button>
                    <button class="cc-tab" onclick="window.ccManager.switchRecurringTab('sections', this)">Seksi Rawan</button>
                    <button class="cc-tab" onclick="window.ccManager.switchRecurringTab('feeders', this)">Penyulang Rekuren</button>
                </div>

                <div id="recurringTabContent" class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                    <table class="cc-table" id="tableRecurring">
                        <thead id="theadRecurring">
                            <!-- Populated dynamically -->
                        </thead>
                        <tbody id="tbodyRecurring">
                            <tr><td colspan="5" class="text-center text-muted">Memuat intelijen rekurensi...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ========================================================================= -->
<!-- 5. "WHY PRIORITIZED?" EXPLAINABILITY DRAWER / MODAL                       -->
<!-- ========================================================================= -->
<div class="cc-modal-backdrop" id="explainModal" onclick="if(event.target === this) window.ccManager.closeExplainModal()">
    <div class="cc-modal">
        <div class="cc-modal-header">
            <div>
                <h4 class="m-0 text-white font-weight-bold" style="font-size: 1rem;">
                    <i class="fas fa-microscope text-primary mr-2"></i>
                    Evidence & Risk Explainability Lineage
                </h4>
                <div id="explainSubtitle" style="font-size: 0.72rem; color: var(--cc-text-muted);">
                    Evaluating Finding Lineage...
                </div>
            </div>
            <button class="cc-btn cc-btn-outline" onclick="window.ccManager.closeExplainModal()" style="padding: 4px 10px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="cc-modal-body" id="explainModalBody">
            <div class="text-center py-4">
                <i class="fas fa-circle-notch fa-spin fa-2x mb-2 text-primary"></i>
                <p class="small text-muted">Menyusun pohon bukti dan dekomposisi pilar risiko M-05...</p>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 6. FRONTEND CONTROL PLANE SCRIPT (VANILLA JS, ZERO N+1, RACE-SAFE)        -->
<!-- ========================================================================= -->
<script>
(function() {
    class CommandCenterManager {
        constructor() {
            this.abortController = null;
            this.requestSequence = 0;
            this.activeRecurringTab = 'findings';
            this.recurringDataCache = null;
            this.cachedFeeders = [];
        }

        init() {
            this.loadInitialData();
        }

        async loadInitialData() {
            // Abort in-flight requests if user rapidly changes filters
            if (this.abortController) {
                this.abortController.abort();
            }
            this.abortController = new AbortController();
            const signal = this.abortController.signal;
            const currentSeq = ++this.requestSequence;

            // Load components concurrently with isolated failure boundaries
            await Promise.allSettled([
                this.fetchSummary(signal, currentSeq),
                this.fetchRiskRadar(signal, currentSeq),
                this.fetchPriorityActions(signal, currentSeq),
                this.fetchRecurringIntelligence(signal, currentSeq)
            ]);
        }

        getFilterParams() {
            const params = new URLSearchParams();
            const ulp = document.getElementById('filterUlp').value;
            const penyulang = document.getElementById('filterPenyulang').value;
            const tier = document.getElementById('filterTier').value;
            const cat = document.getElementById('filterCategory').value;

            if (ulp) params.append('ulp[]', ulp);
            if (penyulang) params.append('penyulang[]', penyulang);
            if (tier) params.append('risk_tier[]', tier);
            if (cat) params.append('category[]', cat);

            return params;
        }

        async refreshAll() {
            const icon = document.getElementById('ccRefreshIcon');
            if (icon) icon.classList.add('fa-spin');
            await this.loadInitialData();
            if (icon) icon.classList.remove('fa-spin');
        }

        onFilterChange() {
            // Smooth immediate feedback
        }

        applyFilters() {
            this.loadInitialData();
        }

        resetFilters() {
            document.getElementById('filterUlp').value = '';
            document.getElementById('filterPenyulang').value = '';
            document.getElementById('filterTier').value = '';
            document.getElementById('filterCategory').value = '';
            this.loadInitialData();
        }

        async fetchSummary(signal, seq) {
            try {
                const params = this.getFilterParams();
                const res = await fetch(`<?= base_url('command-center/api/summary') ?>?${params.toString()}`, { signal });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                if (seq !== this.requestSequence) return; // Stale request guard
                if (json.status === 'success') {
                    this.renderSummary(json.data);
                }
            } catch (err) {
                if (err.name === 'AbortError') return;
                console.warn('[CommandCenter] Summary panel isolated fetch issue:', err.message);
                if (seq === this.requestSequence) {
                    document.getElementById('statRiskIndex').innerText = 'N/A';
                }
            }
        }

        renderSummary(data) {
            const radar = data.radar_summary || {};
            const queue = data.queue_summary || {};
            const source = data.interruption_source || {};

            document.getElementById('statRiskIndex').innerText = (radar.average_display_score ?? '--') + '/100';
            document.getElementById('statCritical').innerText = (radar.tier_distribution?.CRITICAL_PREVENTIVE_ATTENTION ?? 0);
            document.getElementById('statHigh').innerText = (radar.tier_distribution?.HIGH_RISK_RECURRENCE ?? 0);
            document.getElementById('statQueued').innerText = (queue.total_actions_queued ?? 0);

            const statProvider = document.getElementById('statProvider');
            const statProviderDesc = document.getElementById('statProviderDesc');
            if (source.is_available) {
                statProvider.innerText = 'ONLINE';
                statProvider.className = 'cc-stat-value text-success';
                statProviderDesc.innerText = source.status_description || 'Active Disturbance Source';
            } else {
                statProvider.innerText = 'STANDBY';
                statProvider.className = 'cc-stat-value text-muted';
                statProviderDesc.innerText = 'Safe Null Fallback (0 External Rows)';
            }

            document.getElementById('ccLastSync').innerText = 'Sync: ' + new Date().toLocaleTimeString();
        }

        async fetchRiskRadar(signal, seq) {
            try {
                const params = this.getFilterParams();
                const res = await fetch(`<?= base_url('command-center/api/risk-radar') ?>?${params.toString()}`, { signal });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                if (seq !== this.requestSequence) return; // Stale request guard
                if (json.status === 'success') {
                    this.renderRiskRadar(json.data);
                }
            } catch (err) {
                if (err.name === 'AbortError') return;
                console.warn('[CommandCenter] Risk Radar isolated fetch issue:', err.message);
            }
        }

        renderRiskRadar(data) {
            const summary = data.summary || {};
            const tierDist = summary.tier_distribution || {};
            const total = summary.total_findings_processed || 1;

            const pCrit = Math.round(((tierDist.CRITICAL_PREVENTIVE_ATTENTION || 0) / total) * 100);
            const pHigh = Math.round(((tierDist.HIGH_RISK_RECURRENCE || 0) / total) * 100);
            const pMod  = Math.round(((tierDist.MODERATE_DEGRADATION || 0) / total) * 100);
            const pLow  = Math.round(((tierDist.LOW_STABLE || 0) / total) * 100);

            document.getElementById('progCritical').style.width = pCrit + '%';
            document.getElementById('progHigh').style.width = pHigh + '%';
            document.getElementById('progModerate').style.width = pMod + '%';
            document.getElementById('progLow').style.width = pLow + '%';
            document.getElementById('radarFindingsCount').innerText = `${total} Temuan Aktif`;

            // Render Vulnerable Feeders Table
            const tbodyFeeders = document.getElementById('tbodyVulnerableFeeders');
            const feeders = data.top_vulnerable_feeders || [];
            if (feeders.length === 0) {
                tbodyFeeders.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Tidak ada penyulang rawan</td></tr>';
            } else {
                tbodyFeeders.innerHTML = feeders.map(f => `
                    <tr>
                        <td><strong>${f.feeder_name}</strong></td>
                        <td class="text-muted" style="font-size: 0.7rem;">${f.ulp_name}</td>
                        <td class="text-center">${f.findings_count}</td>
                        <td class="text-center ${f.recurring_count > 0 ? 'text-warning font-weight-bold' : 'text-muted'}">${f.recurring_count}</td>
                        <td class="text-right font-weight-bold ${f.max_display_score >= 70 ? 'text-danger' : (f.max_display_score >= 50 ? 'text-warning' : 'text-info')}">
                            ${f.max_display_score} <small>/100</small>
                        </td>
                    </tr>
                `).join('');
            }

            // Populate Feeder Select if not populated
            const selectFeeder = document.getElementById('filterPenyulang');
            if (selectFeeder.options.length <= 1 && feeders.length > 0) {
                feeders.forEach(f => {
                    const opt = document.createElement('option');
                    opt.value = f.penyulang_id;
                    opt.textContent = `${f.feeder_name} (${f.findings_count} Temuan)`;
                    selectFeeder.appendChild(opt);
                });
            }

            // Render Section Hotspots Table
            const tbodySections = document.getElementById('tbodySectionHotspots');
            const sections = data.top_vulnerable_sections || [];
            if (sections.length === 0) {
                tbodySections.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Tidak ada hotspot seksi</td></tr>';
            } else {
                tbodySections.innerHTML = sections.map(s => `
                    <tr>
                        <td><strong>${s.section_name}</strong></td>
                        <td class="text-muted" style="font-size: 0.7rem;">${s.feeder_name}</td>
                        <td class="text-center">${s.findings_count}</td>
                        <td class="text-right">
                            <span class="cc-tier-badge ${s.risk_tier === 'CRITICAL_PREVENTIVE_ATTENTION' ? 'critical' : (s.risk_tier === 'HIGH_RISK_RECURRENCE' ? 'high' : 'moderate')}">
                                ${s.risk_tier.replace('_', ' ').substring(0, 8)}
                            </span>
                        </td>
                    </tr>
                `).join('');
            }
        }

        async fetchPriorityActions(signal, seq) {
            const loadingState = document.getElementById('queueLoadingState');
            const emptyState = document.getElementById('queueEmptyState');
            const cardList = document.getElementById('queueCardList');

            loadingState.classList.remove('d-none');
            emptyState.classList.add('d-none');
            cardList.classList.add('d-none');

            try {
                const params = this.getFilterParams();
                params.append('limit', '25');
                const res = await fetch(`<?= base_url('command-center/api/priority-actions') ?>?${params.toString()}`, { signal });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                
                if (seq !== this.requestSequence) return; // Stale request guard
                loadingState.classList.add('d-none');

                if (json.status === 'success' && json.data?.queue?.length > 0) {
                    this.renderPriorityActions(json.data);
                    cardList.classList.remove('d-none');
                } else {
                    emptyState.classList.remove('d-none');
                }
            } catch (err) {
                if (err.name === 'AbortError') return;
                console.warn('[CommandCenter] Priority Queue isolated fetch issue:', err.message);
                if (seq === this.requestSequence) {
                    loadingState.classList.add('d-none');
                    emptyState.classList.remove('d-none');
                    emptyState.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle mr-1"></i> Gagal memuat antrean tindakan</span>';
                }
            }
        }

        renderPriorityActions(data) {
            const queue = data.queue || [];
            document.getElementById('queueBadgeCount').innerText = `${data.summary?.total_actions_queued || queue.length} Antrean`;

            const cardList = document.getElementById('queueCardList');
            cardList.innerHTML = queue.map(item => {
                const tierClass = item.preventive_risk_tier === 'CRITICAL_PREVENTIVE_ATTENTION' ? 'critical' : (item.preventive_risk_tier === 'HIGH_RISK_RECURRENCE' ? 'high' : (item.preventive_risk_tier === 'MODERATE_DEGRADATION' ? 'moderate' : 'low'));
                const rankClass = item.queue_rank === 1 ? 'rank-1' : (item.queue_rank === 2 ? 'rank-2' : (item.queue_rank === 3 ? 'rank-3' : 'rank-default'));

                return `
                    <div class="cc-queue-card ${rankClass}">
                        <div class="cc-queue-header">
                            <div class="d-flex align-items-center gap-2">
                                <span class="cc-rank-badge">#${item.queue_rank}</span>
                                <span class="cc-tier-badge ${tierClass}">${item.preventive_risk_tier.replace(/_/g, ' ')}</span>
                                <span class="text-muted" style="font-size: 0.72rem; font-family: monospace;">${item.nomor_temuan}</span>
                            </div>
                            <span class="cc-score-pill ${tierClass === 'critical' ? 'text-danger' : (tierClass === 'high' ? 'text-warning' : 'text-info')}">
                                SCORE: ${item.display_score}<small>/100</small>
                            </span>
                        </div>
                        <div class="cc-action-title">
                            ${item.primary_signal}
                        </div>
                        <div class="cc-action-location">
                            <span><i class="fas fa-bolt text-warning mr-1"></i>${item.feeder_name}</span>
                            <span><i class="fas fa-network-wired text-info mr-1"></i>${item.section_name}</span>
                            ${item.recurrence_count > 0 ? `<span class="badge bg-warning text-dark" style="font-size: 0.65rem;">Rekur: ${item.recurrence_count}x</span>` : ''}
                        </div>
                        <div class="cc-action-recommendation">
                            <i class="fas fa-tools mr-1 text-primary"></i> ${item.recommended_action}
                        </div>
                        <div class="cc-action-footer">
                            <span class="text-muted" style="font-size: 0.7rem;">Prioritas Lapangan: <strong>${item.prioritas}</strong></span>
                            <button class="cc-btn cc-btn-outline" style="padding: 3px 8px; font-size: 0.72rem;" onclick="window.ccManager.openExplainModal(${item.finding_id})">
                                <i class="fas fa-eye text-primary"></i> WHY PRIORITIZED?
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        async fetchRecurringIntelligence(signal, seq) {
            try {
                const params = this.getFilterParams();
                const res = await fetch(`<?= base_url('command-center/api/recurring-intelligence') ?>?${params.toString()}`, { signal });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                if (seq !== this.requestSequence) return; // Stale request guard
                if (json.status === 'success') {
                    this.recurringDataCache = json.data;
                    this.renderRecurringTab();
                }
            } catch (err) {
                if (err.name === 'AbortError') return;
                console.warn('[CommandCenter] Recurring Intelligence isolated fetch issue:', err.message);
            }
        }

        switchRecurringTab(tabKey, btnElement) {
            this.activeRecurringTab = tabKey;
            document.querySelectorAll('.cc-tab').forEach(t => t.classList.remove('active'));
            if (btnElement) btnElement.classList.add('active');
            this.renderRecurringTab();
        }

        renderRecurringTab() {
            if (!this.recurringDataCache) return;
            const thead = document.getElementById('theadRecurring');
            const tbody = document.getElementById('tbodyRecurring');

            if (this.activeRecurringTab === 'findings') {
                thead.innerHTML = `
                    <tr>
                        <th>Temuan</th>
                        <th>Penyulang</th>
                        <th>Seksi</th>
                        <th class="text-center">Rekurensi</th>
                        <th class="text-center">Tren</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                `;
                const items = this.recurringDataCache.top_recurring_findings || [];
                if (items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Belum ada temuan berulang &ge; 2x yang terdeteksi.</td></tr>';
                } else {
                    tbody.innerHTML = items.map(i => `
                        <tr>
                            <td><strong>${i.nomor_temuan}</strong><br><small class="text-muted">${i.jenis_temuan}</small></td>
                            <td>${i.feeder_name}</td>
                            <td>${i.section_name}</td>
                            <td class="text-center font-weight-bold text-warning">${i.recurrence_count}x</td>
                            <td class="text-center"><span class="cc-trend ${i.trend.toLowerCase()}">${i.trend}</span></td>
                            <td class="text-right">
                                <button class="cc-btn cc-btn-outline" style="padding: 2px 6px; font-size: 0.7rem;" onclick="window.ccManager.openExplainModal(${i.entity_id})">
                                    Detail
                                </button>
                            </td>
                        </tr>
                    `).join('');
                }
            } else if (this.activeRecurringTab === 'components') {
                thead.innerHTML = `
                    <tr>
                        <th>Kode Komponen</th>
                        <th class="text-center">Total Temuan</th>
                        <th class="text-center">Temuan Berulang</th>
                        <th>Penyulang Terdampak</th>
                    </tr>
                `;
                const comps = this.recurringDataCache.top_component_clusters || [];
                tbody.innerHTML = comps.map(c => `
                    <tr>
                        <td><strong>${c.component_code}</strong></td>
                        <td class="text-center">${c.total_findings}</td>
                        <td class="text-center font-weight-bold ${c.recurring_findings > 0 ? 'text-warning' : 'text-muted'}">${c.recurring_findings}</td>
                        <td class="text-muted small">${c.affected_feeders?.join(', ') || '-'}</td>
                    </tr>
                `).join('');
            } else if (this.activeRecurringTab === 'sections') {
                thead.innerHTML = `
                    <tr>
                        <th>Nama Seksi</th>
                        <th>Penyulang</th>
                        <th class="text-center">Total Temuan</th>
                        <th class="text-center">Temuan Berulang</th>
                    </tr>
                `;
                const secs = this.recurringDataCache.top_recurring_sections || [];
                tbody.innerHTML = secs.map(s => `
                    <tr>
                        <td><strong>${s.section_name}</strong></td>
                        <td>${s.feeder_name}</td>
                        <td class="text-center">${s.total_findings}</td>
                        <td class="text-center font-weight-bold ${s.recurring_findings > 0 ? 'text-warning' : 'text-muted'}">${s.recurring_findings}</td>
                    </tr>
                `).join('');
            } else if (this.activeRecurringTab === 'feeders') {
                thead.innerHTML = `
                    <tr>
                        <th>Penyulang</th>
                        <th class="text-center">Total Temuan</th>
                        <th class="text-center">Temuan Berulang</th>
                        <th class="text-right">Total Rekurensi</th>
                    </tr>
                `;
                const fds = this.recurringDataCache.top_recurring_feeders || [];
                tbody.innerHTML = fds.map(f => `
                    <tr>
                        <td><strong>${f.feeder_name}</strong></td>
                        <td class="text-center">${f.total_findings}</td>
                        <td class="text-center font-weight-bold ${f.recurring_findings > 0 ? 'text-warning' : 'text-muted'}">${f.recurring_findings}</td>
                        <td class="text-right">${f.total_recurrence}</td>
                    </tr>
                `).join('');
            }
        }

        async openExplainModal(findingId) {
            const modal = document.getElementById('explainModal');
            const modalBody = document.getElementById('explainModalBody');
            const subtitle = document.getElementById('explainSubtitle');

            modal.classList.add('open');
            modalBody.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-circle-notch fa-spin fa-2x mb-2 text-primary"></i>
                    <p class="small text-muted">Mengambil data silsilah bukti M-04 & M-05 untuk Finding #${findingId}...</p>
                </div>
            `;

            try {
                const res = await fetch(`<?= base_url('command-center/api/explainability') ?>/${findingId}`);
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                if (json.status === 'success') {
                    const data = json.data;
                    subtitle.innerText = `${data.nomor_temuan} • ${data.feeder_name} (${data.section_name}) • Scoring: ${data.scoring_version}`;
                    this.renderExplainability(data, modalBody);
                } else {
                    modalBody.innerHTML = '<div class="alert alert-danger">Gagal memuat detail explainability.</div>';
                }
            } catch (err) {
                modalBody.innerHTML = `<div class="alert alert-danger">Error: ${err.message}</div>`;
            }
        }

        renderExplainability(data, container) {
            const tierClass = data.preventive_risk_tier === 'CRITICAL_PREVENTIVE_ATTENTION' ? 'critical' : (data.preventive_risk_tier === 'HIGH_RISK_RECURRENCE' ? 'high' : 'moderate');

            container.innerHTML = `
                <!-- Top Summary Header -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 pb-2 border-bottom border-secondary">
                    <div>
                        <span class="cc-tier-badge ${tierClass}" style="font-size: 0.8rem;">${data.preventive_risk_tier.replace(/_/g, ' ')}</span>
                        <h4 class="text-white font-weight-bold mt-2 mb-0" style="font-size: 1.1rem;">
                            Score: ${data.display_score}<small class="text-muted">/100</small>
                            <span class="badge bg-dark border border-secondary text-info ml-2" style="font-size: 0.7rem;">Raw: ${data.preventive_risk_score}</span>
                        </h4>
                    </div>
                    <div class="text-right">
                        <div style="font-size: 0.72rem; color: var(--cc-text-muted);">Evidence Completeness</div>
                        <div class="font-weight-bold text-success" style="font-size: 1rem; font-family: monospace;">${data.evidence_completeness_percent}%</div>
                        <div style="font-size: 0.68rem; color: var(--cc-text-dim);">Correlation Confidence: ${(data.correlation_confidence_score * 100).toFixed(0)}%</div>
                    </div>
                </div>

                <!-- Primary Driver Box -->
                <div class="p-3 mb-3" style="background-color: rgba(59, 130, 246, 0.08); border-left: 3px solid #3b82f6; border-radius: 4px;">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="fas fa-crosshairs text-primary"></i>
                        <strong class="text-white" style="font-size: 0.85rem;">PRIMARY DRIVER: ${data.primary_driver.replace(/_/g, ' ')}</strong>
                    </div>
                    <p class="small text-muted mb-0">${data.primary_driver_description}</p>
                </div>

                <!-- 3 Pillars Breakdown -->
                <h5 class="text-white font-weight-bold mb-2" style="font-size: 0.85rem; text-transform: uppercase;">
                    <i class="fas fa-layer-group text-info mr-1"></i> M-05 Pinned 3-Pillar Weight Decomposition
                </h5>
                <div class="cc-pillar-grid">
                    <div class="cc-pillar-box">
                        <div class="cc-pillar-weight">Pillar 1 • Severity (40%)</div>
                        <div class="cc-pillar-val text-danger">${(data.finding_evidence?.severity_score ?? 0.50).toFixed(2)}</div>
                        <small class="text-muted" style="font-size: 0.68rem;">Prioritas: ${data.finding_evidence?.prioritas ?? 'P3'}</small>
                    </div>
                    <div class="cc-pillar-box">
                        <div class="cc-pillar-weight">Pillar 2 • Recurrence (35%)</div>
                        <div class="cc-pillar-val text-warning">${data.finding_evidence?.is_recurring ? 'RECURRING' : 'STANDARD'}</div>
                        <small class="text-muted" style="font-size: 0.68rem;">Recur: ${data.finding_evidence?.recurrence_count ?? 0}x</small>
                    </div>
                    <div class="cc-pillar-box">
                        <div class="cc-pillar-weight">Pillar 3 • Asset Health (25%)</div>
                        <div class="cc-pillar-val text-info">${data.asset_evidence?.asset_health ?? 75.0}</div>
                        <small class="text-muted" style="font-size: 0.68rem;">${data.asset_evidence?.status?.includes('FALLBACK') ? 'Baseline Fallback' : 'Master Asset'}</small>
                    </div>
                </div>

                <!-- Disturbance Context Box -->
                <div class="p-3 mb-3" style="background-color: #0f172a; border: 1px solid var(--cc-border); border-radius: 6px;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="text-white" style="font-size: 0.8rem;"><i class="fas fa-bolt text-warning mr-1"></i> M-04 Historical Disturbance Intelligence</strong>
                        <span class="badge ${data.historical_interruption_context?.provider_available ? 'bg-success' : 'bg-dark text-muted'}" style="font-size: 0.65rem;">
                            ${data.historical_interruption_context?.status}
                        </span>
                    </div>
                    <p class="small text-muted mb-0" style="font-size: 0.74rem;">
                        ${data.historical_interruption_context?.note}
                    </p>
                </div>

                <!-- Recommended Mitigation Action -->
                <div class="p-3" style="background-color: #1e293b; border-radius: 6px; border: 1px solid #334155;">
                    <strong class="text-white d-block mb-1" style="font-size: 0.82rem;">
                        <i class="fas fa-shield-alt text-success mr-1"></i> Recommended Preventive Action:
                    </strong>
                    <div class="text-light" style="font-size: 0.82rem; font-weight: 500;">
                        ${data.recommended_action}
                    </div>
                </div>
            `;
        }

        closeExplainModal() {
            const modal = document.getElementById('explainModal');
            if (modal) modal.classList.remove('open');
        }
    }

    // Bootstrap single global manager
    document.addEventListener('DOMContentLoaded', () => {
        window.ccManager = new CommandCenterManager();
        window.ccManager.init();
    });
})();
</script>

<?= $this->endSection() ?>
