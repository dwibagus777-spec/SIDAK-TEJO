<!-- ROUTE_TRACE_2026: <?= esc($trace['ROUTE_TRACE_2026'] ?? 'N/A') ?> -->
<!-- CONTROLLER_TRACE_2026: <?= esc($trace['CONTROLLER_TRACE_2026'] ?? 'N/A') ?> -->
<!-- VIEW_TRACE_2026: <?= esc($trace['VIEW_TRACE_2026'] ?? 'N/A') ?> -->
<!-- BUILD_TRACE_2026: <?= esc($trace['BUILD_TRACE_2026'] ?? 'N/A') ?> -->
<!-- HTML_TRACE_2026: <?= esc($trace['HTML_TRACE_2026'] ?? 'N/A') ?> -->
<!-- DOC_ROOT_TRACE: <?= esc($trace['DOC_ROOT_TRACE'] ?? 'N/A') ?> -->
<!-- FCPATH_TRACE: <?= esc($trace['FCPATH_TRACE'] ?? 'N/A') ?> -->
<!-- APPPATH_TRACE: <?= esc($trace['APPPATH_TRACE'] ?? 'N/A') ?> -->
<?= $this->extend('layouts/admin') ?>


<?= $this->section('title') ?>Detail Temuan - <?= esc($temuan['nomor_temuan']) ?><?= $this->endSection() ?>
<?= $this->section('page_title') ?>Detail Temuan Inspeksi Enterprise<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('temuan') ?>">Data Temuan</a></li>
<li class="breadcrumb-item active"><?= esc($temuan['nomor_temuan']) ?></li>
<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<style>
    /* ==========================================================================
       ENTERPRISE PLN MOBILE DESIGN SYSTEM & CSS VARIABLES
       ========================================================================== */
    :root {
        --pln-blue-primary: #005eb8;
        --pln-blue-dark: #003b73;
        --pln-blue-light: #e6f0fa;
        --pln-cyan: #00a3e0;
        --pln-yellow: #ffc72c;
        --bg-glass: rgba(255, 255, 255, 0.85);
        --bg-glass-dark: rgba(15, 23, 42, 0.85);
        --border-glass: rgba(226, 232, 240, 0.8);
        --shadow-subtle: 0 4px 20px -2px rgba(0, 94, 184, 0.08);
        --shadow-hover: 0 12px 28px -4px rgba(0, 94, 184, 0.15);
        --radius-lg: 16px;
        --radius-md: 12px;
        --radius-sm: 8px;
        --transition-fast: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        --transition-bounce: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    /* Badges & Status */
    @keyframes pulse-emergency {
        0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7); }
        50% { transform: scale(1.04); box-shadow: 0 0 0 8px rgba(220, 38, 38, 0); }
    }
    .badge-prio-emergency {
        background: linear-gradient(135deg, #dc2626, #991b1b) !important;
        color: #ffffff !important;
        animation: pulse-emergency 1.8s infinite;
        font-weight: 800;
        letter-spacing: 0.5px;
    }
    .badge-prio-high {
        background: linear-gradient(135deg, #ea580c, #c2410c) !important;
        color: #ffffff !important;
        font-weight: 700;
    }
    .badge-prio-medium {
        background: linear-gradient(135deg, #0284c7, #0369a1) !important;
        color: #ffffff !important;
        font-weight: 700;
    }
    .badge-status-belum { background-color: #dc2626 !important; color: #ffffff !important; }
    .badge-status-proses { background-color: #f59e0b !important; color: #ffffff !important; }
    .badge-status-padam { background-color: #7c3aed !important; color: #ffffff !important; }
    .badge-status-selesai { background-color: #059669 !important; color: #ffffff !important; }

    /* Modern Card Base */
    .card-enterprise {
        background: #ffffff;
        border: 1px solid var(--border-glass);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-subtle);
        transition: var(--transition-fast);
        overflow: hidden;
    }
    .card-enterprise:hover {
        box-shadow: var(--shadow-hover);
    }
    .card-enterprise .card-header {
        background: transparent;
        border-bottom: 1px solid rgba(226, 232, 240, 0.7);
        padding: 1.25rem 1.5rem;
    }

    /* Glassmorphism & AI Copilot Card */
    .ai-card-copilot {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border: 1px solid rgba(255, 199, 44, 0.3);
        border-radius: var(--radius-lg);
        color: #f8fafc;
        position: relative;
        overflow: hidden;
    }
    .ai-card-copilot::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(0, 163, 224, 0.1) 0%, transparent 60%);
        pointer-events: none;
    }

    /* Pinterest-Style Masonry Photo Grid */
    .photo-masonry-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 12px;
    }
    @media (min-width: 768px) {
        .photo-masonry-grid {
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
        }
    }
    .photo-masonry-item {
        position: relative;
        border-radius: var(--radius-md);
        overflow: hidden;
        background-color: #f1f5f9;
        aspect-ratio: 4 / 3;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        cursor: pointer;
        transition: var(--transition-fast);
    }
    .photo-masonry-item:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.12);
    }
    .photo-masonry-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: opacity 0.3s ease;
    }
    .photo-masonry-item img.lazy-loading {
        opacity: 0;
    }
    .photo-masonry-item img.lazy-loaded {
        opacity: 1;
    }
    .photo-masonry-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.6) 0%, transparent 50%);
        opacity: 0;
        transition: var(--transition-fast);
        display: flex;
        align-items: flex-end;
        padding: 10px;
        color: #fff;
    }
    .photo-masonry-item:hover .photo-masonry-overlay {
        opacity: 1;
    }

    /* Skeleton Placeholder */
    .skeleton-loader {
        background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
        background-size: 200% 100%;
        animation: skeleton-loading 1.5s infinite;
    }
    @keyframes skeleton-loading {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* GitHub-Style Timeline */
    .timeline-modern {
        position: relative;
        padding-left: 32px;
    }
    .timeline-modern::before {
        content: '';
        position: absolute;
        left: 14px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background: #e2e8f0;
    }
    .timeline-node {
        position: relative;
        margin-bottom: 24px;
    }
    .timeline-icon-badge {
        position: absolute;
        left: -32px;
        top: 0;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        color: #fff;
        box-shadow: 0 0 0 4px #fff;
    }
    .timeline-card-content {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: var(--radius-md);
        padding: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        transition: var(--transition-fast);
    }
    .timeline-card-content:hover {
        box-shadow: 0 6px 16px rgba(0,0,0,0.08);
    }

    /* Mobile Sticky Elements & Bottom Sheet - Cross-Browser GPU & Safe-Area Optimized */
    @media (max-width: 991.98px) {
        .mobile-sticky-header {
            position: sticky;
            top: 0;
            z-index: 1020;
            background: rgba(255, 255, 255, 0.95);
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #e2e8f0;
            padding-top: calc(10px + env(safe-area-inset-top, 0px));
            padding-bottom: 10px;
            padding-left: calc(16px + env(safe-area-inset-left, 0px));
            padding-right: calc(16px + env(safe-area-inset-right, 0px));
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            transition: var(--transition-fast);
            will-change: transform, padding;
        }
        .mobile-sticky-header.shrunken {
            padding-top: calc(6px + env(safe-area-inset-top, 0px));
            padding-bottom: 6px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
        .mobile-sticky-bottom-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1040;
            background: rgba(255, 255, 255, 0.96);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
            padding-bottom: calc(8px + env(safe-area-inset-bottom, 0px));
            padding-left: calc(12px + env(safe-area-inset-left, 0px));
            padding-right: calc(12px + env(safe-area-inset-right, 0px));
            display: flex;
            gap: 8px;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
            will-change: transform;
        }
        .mobile-fab-container {
            position: fixed;
            bottom: calc(68px + env(safe-area-inset-bottom, 0px));
            right: calc(16px + env(safe-area-inset-right, 0px));
            z-index: 1050;
        }
        .btn-fab-trigger {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--pln-blue-primary), var(--pln-cyan));
            color: #ffffff;
            border: none;
            box-shadow: 0 6px 20px rgba(0, 94, 184, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .btn-fab-trigger.active {
            transform: rotate(135deg);
            background: linear-gradient(135deg, #dc2626, #991b1b);
        }
        .bottom-sheet-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1060;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .bottom-sheet-overlay.show {
            display: block;
            opacity: 1;
        }
        .bottom-sheet-content {
            position: fixed;
            left: 0; right: 0; bottom: 0;
            background: #ffffff;
            border-top-left-radius: 24px;
            border-top-right-radius: 24px;
            padding: 16px 16px 28px 16px;
            z-index: 1070;
            transform: translateY(100%);
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            max-height: 85vh;
            overflow-y: auto;
        }
        .bottom-sheet-overlay.show .bottom-sheet-content {
            transform: translateY(0);
        }
        .bottom-sheet-handle {
            width: 44px;
            height: 5px;
            background: #cbd5e1;
            border-radius: 3px;
            margin: 0 auto 16px auto;
        }
    }

    /* Enterprise Interactive Lightbox */
    #enterprise-lightbox {
        position: fixed;
        inset: 0;
        background: rgba(10, 15, 29, 0.96);
        backdrop-filter: blur(12px);
        z-index: 99999;
        display: none;
        flex-direction: column;
        user-select: none;
    }
    #enterprise-lightbox.active {
        display: flex;
    }
    .lightbox-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 20px;
        background: rgba(255, 255, 255, 0.05);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        color: #fff;
    }
    .lightbox-stage {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
    }
    .lightbox-image-wrapper {
        transition: transform 0.2s ease-out;
        display: flex;
        align-items: center;
        justify-content: center;
        max-width: 100%;
        max-height: 100%;
    }
    .lightbox-image-wrapper img {
        max-width: 90vw;
        max-height: 75vh;
        object-fit: contain;
        border-radius: var(--radius-md);
        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
    }
    .lightbox-controls {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        padding: 16px;
        background: rgba(0, 0, 0, 0.4);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        flex-wrap: wrap;
    }
    .lightbox-btn {
        background: rgba(255, 255, 255, 0.15);
        border: none;
        color: #ffffff;
        padding: 8px 14px;
        border-radius: var(--radius-sm);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: var(--transition-fast);
    }
    .lightbox-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-1px);
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div id="VIEW_DEBUG" class="d-none">ENTERPRISE BUILD 2026</div>

<?php
$lat = $temuan['latitude'];
$lng = $temuan['longitude'];
if (!empty($lat) && !empty($lng)) {
    $sharelokUrl = "https://maps.google.com/?q={$lat},{$lng}";
} else {
    $sharelokUrl = "https://www.google.com/maps/search/?api=1&query=" . urlencode($temuan['alamat'] . ", Sidoarjo");
}

$tglJamFormatted = date('d-m-Y H:i', strtotime(!empty($temuan['created_at']) ? $temuan['created_at'] : $temuan['tanggal_temuan'])) . ' WIB';

$waMsg = "🚨 *TEMUAN INSPEKSI - SIDAK TEJO* 🚨\n\n" .
         "📌 *Nomor Temuan*: " . $temuan['nomor_temuan'] . "\n" .
         "📅 *Tanggal & Jam*: " . $tglJamFormatted . "\n" .
         "📍 *ULP*: " . $temuan['nama_ulp'] . "\n" .
         "⚡ *Penyulang*: " . $temuan['nama_penyulang'] . "\n" .
         "📍 *Section*: " . $temuan['nama_section'] . "\n" .
         "🔴 *Jenis Temuan*: " . $temuan['jenis_temuan'] . "\n" .
         "⚠️ *Prioritas*: " . $temuan['prioritas'] . "\n" .
         "🔧 *Pelaksana*: " . $temuan['pelaksana'] . "\n" .
         "📝 *Detail*: " . $temuan['detail_temuan'] . "\n" .
         "📍 *Alamat*: " . $temuan['alamat'] . "\n" .
         "🗺️ *Sharelok*: " . $sharelokUrl . "\n\n" .
         "🔗 *Detail Link*: " . site_url('temuan/detail/' . $temuan['id']);
$waUrl = "https://api.whatsapp.com/send?text=" . urlencode($waMsg);

$prio = strtoupper($temuan['prioritas'] ?? 'MEDIUM');
$prioClass = match($prio) {
    'EMERGENCY' => 'badge-prio-emergency',
    'HIGH'      => 'badge-prio-high',
    default     => 'badge-prio-medium'
};

$statusStr = strtoupper($temuan['status'] ?? 'BELUM');
$statusClass = match($statusStr) {
    'SELESAI'     => 'badge-status-selesai',
    'PROSES'      => 'badge-status-proses',
    'BUTUH PADAM' => 'badge-status-padam',
    default       => 'badge-status-belum'
};

// AI Decision Support Service Initialization
$aiService = new \App\Services\PredictiveMaintenanceService();
$aiRecommendation = $aiService->getExplainableRecommendation($temuan);
?>

<!-- Mobile Sticky Header (< 992px) -->
<div class="mobile-sticky-header d-lg-none" id="mobileHeader" role="banner">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="font-weight-bold text-primary" style="font-size: 14px;">
            <i class="fas fa-file-invoice me-1"></i> <?= esc($temuan['nomor_temuan']) ?>
        </span>
        <div class="d-flex gap-1">
            <span class="badge px-2 py-1 <?= $statusClass ?>"><?= $statusStr ?></span>
            <span class="badge px-2 py-1 <?= $prioClass ?>"><?= $prio ?></span>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center" style="font-size: 11px;">
        <span class="text-muted"><i class="fas fa-bolt text-warning me-1"></i> <?= esc($temuan['nama_penyulang']) ?></span>
        <span><?= $sla['badge_html'] ?></span>
    </div>
</div>

<div class="row g-4">
    <!-- Main Detail Column -->
    <div class="col-lg-8 col-12">
        <div class="card card-enterprise shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h3 class="card-title mb-0 fs-5 font-weight-bold">
                    <i class="fas fa-circle-info text-primary me-2"></i> 
                    Nomor Temuan: <span class="font-monospace text-primary"><?= esc($temuan['nomor_temuan']) ?></span>
                </h3>
                <div class="d-flex align-items-center ms-auto gap-2">
                    <a href="<?= $waUrl ?>" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-sm font-weight-bold shadow-sm d-none d-lg-inline-flex align-items-center" style="background-color: #25D366; border: none; border-radius: 8px;">
                        <i class="fab fa-whatsapp me-1 fs-6"></i> Share WhatsApp
                    </a>
                    <span><?= $sla['badge_html'] ?></span>
                </div>
            </div>

            <div class="card-body p-4">
                <!-- AI Copilot Recommendation Card -->
                <div class="ai-card-copilot p-3 p-md-4 mb-4 shadow">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-brain text-warning fs-4"></i>
                            <h6 class="fw-bold mb-0 text-warning letter-spacing-1" style="font-size: 14px;">
                                SIDAK AI DECISION SUPPORT ENGINE
                            </h6>
                        </div>
                        <span class="badge <?= esc($aiRecommendation['badge_class']) ?> px-3 py-2 fs-7">
                            RISK SCORE: <?= number_format($aiRecommendation['score'], 1) ?> (<?= esc($aiRecommendation['category']) ?>)
                        </span>
                    </div>

                    <div class="p-3 bg-dark bg-opacity-50 rounded-3 border border-secondary border-opacity-25 mb-3">
                        <p class="mb-0 fw-bold text-white small" style="font-size: 13px; line-height: 1.6;">
                            <i class="fas fa-lightbulb text-info me-2"></i> <?= esc($aiRecommendation['recommendation_text']) ?>
                        </p>
                    </div>

                    <div class="bg-secondary bg-opacity-20 rounded-3 p-3" style="font-size: 12px;">
                        <strong class="text-warning d-block mb-2">
                            <i class="fas fa-magnifying-glass-chart me-1"></i> Analisis Rasional Explainable AI:
                        </strong>
                        <ul class="mb-0 ps-3 text-light">
                            <?php foreach ($aiRecommendation['reasons'] as $reason): ?>
                                <li class="mb-1"><?= esc($reason) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Info Grid -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6 col-12">
                        <div class="p-3 rounded-3 border bg-light h-100">
                            <div class="text-muted small font-weight-bold mb-1"><i class="fas fa-building-user text-primary me-1"></i> ULP & Wilayah Kerja</div>
                            <div class="font-weight-bold text-dark fs-6"><?= esc($temuan['nama_ulp']) ?></div>
                            <div class="small text-secondary mt-1">
                                ⚡ Penyulang: <strong><?= esc($temuan['nama_penyulang']) ?></strong> | Section: <strong><?= esc($temuan['nama_section']) ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-12">
                        <div class="p-3 rounded-3 border bg-light h-100">
                            <div class="text-muted small font-weight-bold mb-1"><i class="fas fa-tags text-info me-1"></i> Klasifikasi & Status</div>
                            <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                                <span class="badge bg-primary px-2 py-1"><?= esc($temuan['jenis_temuan']) ?></span>
                                <span class="badge px-2 py-1 <?= $prioClass ?>">Prioritas: <?= $prio ?></span>
                                <span class="badge px-2 py-1 <?= $statusClass ?>">Status: <?= $statusStr ?></span>
                            </div>
                            <div class="small text-muted mt-2">
                                Pelaksana: <strong><?= esc($temuan['pelaksana']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Technical Specification Table -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <th style="width: 140px;" class="text-muted">Potensi Gangguan</th>
                                <td>: <span class="badge bg-info text-dark font-weight-bold px-2 py-1" style="font-size: 12px;"><?= esc($temuan['potensi_gangguan']) ?></span></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Konduktor</th>
                                <td>: <span class="fw-semibold text-dark"><?= esc($temuan['konduktor']) ?></span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <th style="width: 140px;" class="text-muted">Gardu (NOGA)</th>
                                <td>: <?= $temuan['noga'] ? esc($temuan['noga']) : '<span class="text-muted small">Tidak ada</span>' ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Waktu Mulai</th>
                                <td>: <span class="font-weight-bold text-primary"><?= indo_datetime($temuan['created_at'] ?: $temuan['tanggal_temuan']) ?></span></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Update Terakhir</th>
                                <td>: <span class="font-weight-bold text-secondary"><?= indo_datetime($temuan['updated_at']) ?></span></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Lama Pengerjaan</th>
                                <td>: <span class="badge bg-success font-weight-bold px-2 py-1"><i class="fas fa-stopwatch me-1"></i> <?= duration($temuan['created_at'] ?: $temuan['tanggal_temuan'], $temuan['updated_at']) ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <hr class="my-4 text-secondary opacity-25">

                <!-- Material & Damage Cards -->
                <div class="mb-4">
                    <h6 class="font-weight-bold text-primary mb-2" style="font-size: 14px;">
                        <i class="fas fa-screwdriver-wrench me-2"></i> Material Dibutuhkan:
                    </h6>
                    <div class="p-3 rounded-3" style="background-color: #f1f5f9; border-left: 4px solid #005eb8; font-size: 13px; font-weight: 600; color: #0f172a; white-space: pre-wrap; line-height: 1.6;">
                        <?= esc($temuan['material']) ?: '<span class="text-muted font-italic">Tidak ada spesifikasi material</span>' ?>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="font-weight-bold text-danger mb-2" style="font-size: 14px;">
                        <i class="fas fa-triangle-exclamation me-2"></i> Detail Kerusakan:
                    </h6>
                    <div class="p-3 rounded-3" style="background-color: #fff7ed; border-left: 4px solid #ea580c; font-size: 13px; font-weight: 600; color: #1c1917; white-space: pre-wrap; line-height: 1.6;">
                        <?= esc($temuan['detail_temuan']) ?>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="font-weight-bold text-success mb-2" style="font-size: 14px;">
                        <i class="fas fa-map-location-dot me-2"></i> Alamat Lokasi Temuan:
                    </h6>
                    <div class="p-3 rounded-3" style="background-color: #f0fdf4; border-left: 4px solid #16a34a; font-size: 13px; font-weight: 600; color: #052e16; white-space: pre-wrap; line-height: 1.6;">
                        <?= esc($temuan['alamat']) ?>
                    </div>
                </div>

                <!-- Pinterest-Style Photo Gallery -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="font-weight-bold text-dark mb-0 fs-6">
                            <i class="fas fa-images text-primary me-2"></i> Galeri Foto Temuan Lapangan:
                        </h6>
                        <span class="text-muted small"><i class="fas fa-hand-pointer me-1"></i> Klik untuk Zoom / Fullscreen</span>
                    </div>

                    <div class="photo-masonry-grid" id="photoGalleryContainer">
                        <?php 
                        $photos = json_decode($temuan['foto'], true) ?: [];
                        if (is_string($temuan['foto']) && empty($photos) && !empty($temuan['foto'])) {
                            $photos = [$temuan['foto']];
                        }

                        if (empty($photos)): ?>
                            <div class="col-12">
                                <div class="p-4 text-center border rounded-3 bg-light text-muted">
                                    <i class="fas fa-image-slash fs-3 mb-2 d-block text-secondary"></i>
                                    Tidak ada foto temuan yang diunggah.
                                </div>
                            </div>
                        <?php else:
                            foreach ($photos as $idx => $photo):
                                if (empty($photo)) continue;
                                $filePath = get_photo_url($photo, $temuan['foto_path'] ?? 'foto/', 'full');
                        ?>
                            <div class="photo-masonry-item skeleton-loader" onclick="SidakGallery.open('<?= $filePath ?>', <?= $idx ?>)" role="button" aria-label="Lihat Foto Temuan <?= $idx + 1 ?>">
                                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 3'%3E%3C/svg%3E" data-src="<?= $filePath ?>" loading="lazy" class="lazy-image lazy-loading" alt="Foto Temuan <?= $idx + 1 ?>" onload="this.classList.remove('lazy-loading'); this.parentElement.classList.remove('skeleton-loader');" onerror="this.onerror=null; this.parentElement.innerHTML='<span class=\'text-muted small p-2 text-center d-flex flex-column align-items-center justify-content-center h-100\'><i class=\'fas fa-image-slash fs-5 mb-1\'></i> Gagal Muat</span>';">
                                <div class="photo-masonry-overlay">
                                    <span class="small font-weight-bold"><i class="fas fa-expand me-1"></i> Perbesar</span>
                                </div>
                            </div>
                        <?php 
                            endforeach;
                        endif; 
                        ?>
                    </div>
                </div>
            </div>

            <div class="card-footer p-3 bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                <a href="javascript:smartBack('<?= site_url('temuan') ?>');" class="btn btn-outline-secondary font-weight-bold px-3">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
                <div class="d-flex gap-2">
                    <?php if (!empty($temuan['latitude']) && !empty($temuan['longitude'])): ?>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?= $temuan['latitude'] ?>,<?= $temuan['longitude'] ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-danger font-weight-bold">
                        <i class="fas fa-location-dot me-1"></i> Buka Google Maps
                    </a>
                    <?php endif; ?>
                    <?php if (in_array(session()->get('user_role'), ['administrator', 'admin_ulp', 'inspeksi'])): ?>
                    <a href="<?= site_url('temuan/edit/' . $temuan['id']) ?>" class="btn btn-warning text-dark font-weight-bold px-3">
                        <i class="fas fa-edit me-1"></i> Edit Temuan
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- GitHub-Style Timeline Work Progress -->
        <div class="card card-enterprise shadow-sm mb-4">
            <div class="card-header border-bottom">
                <h3 class="card-title font-weight-bold fs-6 mb-0">
                    <i class="fas fa-timeline text-success me-2"></i> Riwayat Progress & Timeline Pekerjaan
                </h3>
            </div>
            <div class="card-body p-4">
                <?php if (empty($history)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-clock-rotate-left fs-3 mb-2 d-block"></i>
                        Belum ada riwayat progress tindak lanjut untuk temuan ini.
                    </div>
                <?php else: ?>
                    <div class="timeline-modern">
                        <?php foreach ($history as $h):
                            $statusProg = $h['status_progress'] ?? 'PROSES';
                            $statusBadgeClass = match($statusProg) {
                                'SELESAI'     => 'bg-success',
                                'BUTUH PADAM' => 'bg-purple',
                                'TERKENDALA'  => 'bg-warning text-dark',
                                default       => 'bg-info'
                            };
                            $iconClass = match($statusProg) {
                                'SELESAI'     => 'fa-check bg-success',
                                'BUTUH PADAM' => 'fa-bolt bg-purple',
                                'TERKENDALA'  => 'fa-exclamation bg-warning',
                                default       => 'fa-wrench bg-info'
                            };
                        ?>
                            <div class="timeline-node">
                                <div class="timeline-icon-badge <?= $statusBadgeClass ?>">
                                    <i class="fas <?= $iconClass ?>"></i>
                                </div>
                                <div class="timeline-card-content">
                                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
                                        <h6 class="fw-bold mb-0 text-dark" style="font-size: 13px;">
                                            Oleh: <?= esc($h['pelaksana']) ?>
                                            <span class="badge ms-2 <?= $statusBadgeClass ?>"><?= esc($statusProg) ?></span>
                                        </h6>
                                        <span class="small text-muted"><i class="far fa-clock me-1"></i> <?= date('d-m-Y H:i', strtotime($h['tanggal'])) ?></span>
                                    </div>
                                    <p class="mb-3 text-secondary small" style="line-height: 1.5; font-size: 13px;">
                                        <?= esc($h['komentar']) ?>
                                    </p>

                                    <!-- Progress Photos Grid -->
                                    <div class="row g-2">
                                        <?php if ($h['foto_sebelum']): $urlSeb = get_photo_url($h['foto_sebelum']); ?>
                                            <div class="col-4">
                                                <span class="text-xs text-muted d-block mb-1">Sebelum</span>
                                                <div class="photo-masonry-item" style="aspect-ratio: 1/1;" onclick="SidakGallery.open('<?= $urlSeb ?>')">
                                                    <img src="<?= $urlSeb ?>" loading="lazy" alt="Sebelum">
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($h['foto_proses']): $urlPro = get_photo_url($h['foto_proses']); ?>
                                            <div class="col-4">
                                                <span class="text-xs text-muted d-block mb-1">Proses</span>
                                                <div class="photo-masonry-item" style="aspect-ratio: 1/1;" onclick="SidakGallery.open('<?= $urlPro ?>')">
                                                    <img src="<?= $urlPro ?>" loading="lazy" alt="Proses">
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($h['foto_sesudah']): $urlSes = get_photo_url($h['foto_sesudah']); ?>
                                            <div class="col-4">
                                                <span class="text-xs text-muted d-block mb-1">Sesudah</span>
                                                <div class="photo-masonry-item" style="aspect-ratio: 1/1;" onclick="SidakGallery.open('<?= $urlSes ?>')">
                                                    <img src="<?= $urlSes ?>" loading="lazy" alt="Sesudah">
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar Column: GIS Map, QR Code, Progress Form -->
    <div class="col-lg-4 col-12">
        <!-- Interactive Leaflet Map -->
        <div class="card card-enterprise shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold fs-6 mb-0">
                    <i class="fas fa-map-marked-alt text-danger me-2"></i> Peta Presisi GIS
                </h3>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnToggleLayer" title="Ubah Layer Maps">
                    <i class="fas fa-layer-group me-1"></i> Satelit
                </button>
            </div>
            <div class="card-body p-0 position-relative">
                <div id="detail-gis-map" style="height: 260px; width: 100%; border-radius: 0;"></div>
            </div>
            <div class="card-footer p-2 bg-light">
                <div class="d-grid gap-2">
                    <div class="btn-group w-100">
                        <a href="https://maps.google.com/?q=<?= $temuan['latitude'] ?>,<?= $temuan['longitude'] ?>" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-sm font-weight-bold" style="background-color: #059669; border: none;">
                            <i class="fas fa-diamond-turn-right me-1"></i> Google Maps
                        </a>
                        <a href="https://waze.com/ul?ll=<?= $temuan['latitude'] ?>,<?= $temuan['longitude'] ?>&navigate=yes" target="_blank" rel="noopener noreferrer" class="btn btn-info text-white btn-sm font-weight-bold">
                            <i class="fab fa-waze me-1"></i> Waze
                        </a>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCopyCoords">
                        <i class="fas fa-copy me-1"></i> Salin Koordinat (<?= esc($temuan['latitude']) ?>, <?= esc($temuan['longitude']) ?>)
                    </button>
                </div>
            </div>
        </div>

        <!-- High Resolution QR Code Card -->
        <div class="card card-enterprise text-center p-3 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pb-0">
                <h3 class="card-title font-weight-bold fs-6 mb-0">
                    <i class="fas fa-qrcode text-primary me-2"></i> Enterprise QR Code
                </h3>
            </div>
            <div class="card-body d-flex flex-column align-items-center p-3">
                <div class="bg-white p-3 rounded-3 border shadow-sm mb-3">
                    <canvas id="qr-code-canvas"></canvas>
                </div>
                <div class="d-flex gap-2 w-100">
                    <button class="btn btn-outline-primary btn-sm font-weight-bold flex-fill" id="btn-download-qr">
                        <i class="fas fa-download me-1"></i> Unduh QR
                    </button>
                    <button class="btn btn-outline-secondary btn-sm font-weight-bold flex-fill" id="btn-share-qr">
                        <i class="fas fa-share-nodes me-1"></i> Bagikan
                    </button>
                </div>
            </div>
        </div>

        <!-- Progress Form Card -->
        <?php if ($temuan['status'] !== 'SELESAI' && check_role(['administrator', 'admin_ulp', 'pdkb', 'har_gardu', 'har_row', 'har_crane', 'yantek'])): ?>
            <div class="card card-enterprise shadow-sm mb-4" id="progressFormCard">
                <div class="card-header border-bottom bg-light">
                    <h3 class="card-title font-weight-bold fs-6 mb-0 text-info">
                        <i class="fas fa-pen-to-square me-2"></i> Tambah Progress Pekerjaan
                    </h3>
                </div>
                <form action="<?= site_url('temuan/tindak-lanjut/' . $temuan['id']) ?>" method="post" enctype="multipart/form-data" id="formProgress">
                    <?= csrf_field() ?>
                    <div class="card-body p-3">
                        <div class="mb-3">
                            <label for="status_progress" class="form-label small font-weight-bold text-dark">Status Progress Kerja</label>
                            <select name="status_progress" id="status_progress" class="form-select form-select-sm" required>
                                <option value="PROSES">PROSES PEKERJAAN</option>
                                <option value="TERKENDALA">TERKENDALA (Bahan / Akses / Cuaca)</option>
                                <option value="BUTUH PADAM">BUTUH PADAM (Pemadaman Listrik)</option>
                                <option value="SELESAI">SELESAI PEKERJAAN</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="komentar" class="form-label small font-weight-bold text-dark">Komentar / Keterangan Lapangan</label>
                            <textarea name="komentar" id="komentar" class="form-control form-control-sm" rows="3" placeholder="Contoh: Sedang dilakukan perapian isolator tumpu..." required></textarea>
                        </div>

                        <!-- Photo File Inputs -->
                        <?php foreach (['sebelum' => 'Sebelum', 'proses' => 'Proses', 'sesudah' => 'Sesudah'] as $key => $label): ?>
                            <div class="mb-3">
                                <label class="form-label small font-weight-bold text-dark mb-1">Foto <?= $label ?> Pekerjaan</label>
                                <div class="btn-group w-100 mb-1" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-dual-gallery" data-target="#foto_<?= $key ?>">
                                        <i class="fas fa-folder-open me-1"></i> Galeri
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success btn-dual-camera" data-target="#foto_<?= $key ?>_cam">
                                        <i class="fas fa-camera me-1"></i> Kamera
                                    </button>
                                </div>
                                <input type="file" name="foto_<?= $key ?>" id="foto_<?= $key ?>" class="d-none" accept="image/*">
                                <input type="file" id="foto_<?= $key ?>_cam" class="d-none" accept="image/*" capture="environment">
                                <div class="small text-muted text-truncate font-monospace" id="preview_name_foto_<?= $key ?>" style="font-size: 11px;">Belum ada foto terpilih</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-footer bg-light p-3">
                        <button type="submit" class="btn btn-info text-white w-100 font-weight-bold shadow-sm" id="btnSubmitProgress">
                            <i class="fas fa-paper-plane me-1"></i> Kirim Progress Lapangan
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mobile Sticky Bottom Bar (< 992px) -->
<div class="mobile-sticky-bottom-bar d-lg-none">
    <?php if (in_array(session()->get('user_role'), ['administrator', 'admin_ulp'])): ?>
        <a href="<?= site_url('temuan/edit/' . $temuan['id']) ?>" class="btn btn-warning text-dark flex-fill btn-sm font-weight-bold">
            <i class="fas fa-edit me-1"></i> Edit
        </a>
    <?php endif; ?>
    <button type="button" class="btn btn-info text-white flex-fill btn-sm font-weight-bold" id="btnStickyProgress">
        <i class="fas fa-wrench me-1"></i> Progress
    </button>
    <a href="<?= $waUrl ?>" target="_blank" rel="noopener noreferrer" class="btn btn-success text-white flex-fill btn-sm font-weight-bold" style="background-color: #25D366; border: none;">
        <i class="fab fa-whatsapp me-1"></i> Share
    </a>
    <a href="<?= $sharelokUrl ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary text-white flex-fill btn-sm font-weight-bold">
        <i class="fas fa-map-marker-alt me-1"></i> Maps
    </a>
</div>

<!-- Floating Action Button (FAB) -->
<div class="mobile-fab-container d-lg-none">
    <button type="button" class="btn-fab-trigger" id="fabBtnTrigger" aria-label="Menu Opsi">
        <i class="fas fa-ellipsis-v" id="fabIcon"></i>
    </button>
</div>

<!-- Bottom Sheet Speed Dial Modal -->
<div class="bottom-sheet-overlay" id="bottomSheetOverlay">
    <div class="bottom-sheet-content">
        <div class="bottom-sheet-handle"></div>
        <h6 class="font-weight-bold text-dark mb-3 text-center border-bottom pb-2">
            <i class="fas fa-sliders text-primary me-1"></i> Menu Opsi Enterprise PLN
        </h6>
        <div class="list-group list-group-flush">
            <a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex align-items-center py-2" onclick="location.reload()">
                <i class="fas fa-rotate text-primary me-3 fs-5" style="width: 24px;"></i> Refresh Halaman
            </a>
            <?php if (in_array(session()->get('user_role'), ['administrator', 'admin_ulp'])): ?>
                <a href="<?= site_url('temuan/edit/' . $temuan['id']) ?>" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                    <i class="fas fa-pencil-alt text-warning me-3 fs-5" style="width: 24px;"></i> Edit Data Temuan
                </a>
            <?php endif; ?>
            <a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex align-items-center py-2" id="bsActionProgress">
                <i class="fas fa-wrench text-info me-3 fs-5" style="width: 24px;"></i> Form Progress Lapangan
            </a>
            <a href="<?= $waUrl ?>" target="_blank" rel="noopener noreferrer" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                <i class="fab fa-whatsapp text-success me-3 fs-5" style="width: 24px;"></i> Bagikan ke WhatsApp
            </a>
            <a href="<?= $sharelokUrl ?>" target="_blank" rel="noopener noreferrer" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                <i class="fas fa-map-marked-alt text-danger me-3 fs-5" style="width: 24px;"></i> Petunjuk Arah (Maps)
            </a>
            <a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex align-items-center py-2" id="bsActionCopy">
                <i class="fas fa-copy text-secondary me-3 fs-5" style="width: 24px;"></i> Salin Link Detail
            </a>
            <a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex align-items-center py-2 text-danger font-weight-bold" id="bsActionClose">
                <i class="fas fa-times me-3 fs-5" style="width: 24px;"></i> Tutup Menu
            </a>
        </div>
    </div>
</div>

<!-- Enterprise Lightbox Modal -->
<div id="enterprise-lightbox" role="dialog" aria-modal="true" aria-label="Viewer Foto Lightbox">
    <div class="lightbox-toolbar">
        <span class="font-weight-bold small"><i class="fas fa-image text-info me-2"></i> Viewer Foto Inspeksi</span>
        <button type="button" class="btn-close btn-close-white" onclick="SidakGallery.close()" aria-label="Close"></button>
    </div>
    <div class="lightbox-stage" id="lightboxStage">
        <div class="lightbox-image-wrapper" id="lightboxWrapper">
            <img id="lightboxImg" src="" alt="Preview Foto Lapangan">
        </div>
    </div>
    <div class="lightbox-controls">
        <button type="button" class="lightbox-btn" onclick="SidakGallery.zoom(0.2)"><i class="fas fa-plus"></i> Zoom In</button>
        <button type="button" class="lightbox-btn" onclick="SidakGallery.zoom(-0.2)"><i class="fas fa-minus"></i> Zoom Out</button>
        <button type="button" class="lightbox-btn" onclick="SidakGallery.rotate()"><i class="fas fa-rotate"></i> Putar</button>
        <button type="button" class="lightbox-btn" onclick="SidakGallery.reset()"><i class="fas fa-arrows-rotate"></i> Reset</button>
        <a id="lightboxDownload" href="" download class="lightbox-btn" style="text-decoration:none;"><i class="fas fa-download"></i> Unduh</a>
        <button type="button" class="lightbox-btn bg-danger" onclick="SidakGallery.close()"><i class="fas fa-times"></i> Tutup</button>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="<?= base_url('plugins/qrious.min.js') ?>"></script>
<script>
    /**
     * SIDAK TEJO - ENTERPRISE DETAIL ENGINE
     * Clean, Modular, Reusable Namespace Architecture
     */
    const SidakDetail = (function() {
        let mapInstance = null;
        let streetLayer = null;
        let satLayer = null;
        let currentLayerType = 'street';

        // Lazy Image Loader with IntersectionObserver
        function initLazyImages() {
            const lazyImages = document.querySelectorAll('.lazy-image');
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries, obs) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.removeAttribute('data-src');
                            }
                            obs.unobserve(img);
                        }
                    });
                }, { rootMargin: '50px 0px' });
                lazyImages.forEach(img => observer.observe(img));
            } else {
                lazyImages.forEach(img => {
                    if (img.dataset.src) img.src = img.dataset.src;
                });
            }
        }

        // GIS Map Initialization
        function initMap() {
            const lat = <?= $temuan['latitude'] !== null ? (float)$temuan['latitude'] : 'null' ?>;
            const lng = <?= $temuan['longitude'] !== null ? (float)$temuan['longitude'] : 'null' ?>;
            const defaultLat = lat !== null ? lat : -7.4478;
            const defaultLng = lng !== null ? lng : 112.7183;
            const container = document.getElementById('detail-gis-map');

            if (!container || typeof L === 'undefined') return;

            mapInstance = L.map('detail-gis-map', {
                center: [defaultLat, defaultLng],
                zoom: lat !== null ? 16 : 12,
                zoomControl: true
            });

            streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap SIDAK GIS'
            });

            satLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: '&copy; Esri Satellite'
            });

            streetLayer.addTo(mapInstance);

            const customIcon = L.divIcon({
                className: 'custom-map-marker',
                html: '<div style="background:#dc2626; width:20px; height:20px; border-radius:50%; border:3px solid #fff; box-shadow:0 0 10px rgba(220,38,38,0.8);"></div>',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });

            if (lat !== null && lng !== null) {
                L.marker([lat, lng], { icon: customIcon }).addTo(mapInstance)
                    .bindPopup('<b><?= esc($temuan['nomor_temuan']) ?></b><br><small><?= esc($temuan['alamat']) ?></small>')
                    .openPopup();
            }

            // Layer Switcher Event
            const btnToggle = document.getElementById('btnToggleLayer');
            if (btnToggle) {
                btnToggle.addEventListener('click', function() {
                    if (currentLayerType === 'street') {
                        mapInstance.removeLayer(streetLayer);
                        satLayer.addTo(mapInstance);
                        currentLayerType = 'sat';
                        this.innerHTML = '<i class="fas fa-map me-1"></i> Peta';
                    } else {
                        mapInstance.removeLayer(satLayer);
                        streetLayer.addTo(mapInstance);
                        currentLayerType = 'street';
                        this.innerHTML = '<i class="fas fa-layer-group me-1"></i> Satelit';
                    }
                });
            }

            // Copy Coordinates Event
            const btnCopy = document.getElementById('btnCopyCoords');
            if (btnCopy) {
                btnCopy.addEventListener('click', function() {
                    if (lat && lng) {
                        navigator.clipboard.writeText(lat + ', ' + lng).then(() => {
                            Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Koordinat disalin ke clipboard!', timer: 1500, showConfirmButton: false });
                        });
                    }
                });
            }

            setTimeout(() => mapInstance.invalidateSize(), 400);
        }

        // QR Code Generator
        function initQR() {
            const qrCanvas = document.getElementById('qr-code-canvas');
            const nomorTemuan = "<?= esc($temuan['nomor_temuan']) ?>";
            const detailUrl = "<?= site_url('temuan/detail/' . $temuan['id']) ?>";

            if (qrCanvas && typeof QRious !== 'undefined') {
                try {
                    const qr = new QRious({
                        element: qrCanvas,
                        value: detailUrl,
                        size: 160,
                        background: '#ffffff',
                        foreground: '#0f172a',
                        level: 'H'
                    });

                    document.getElementById('btn-download-qr')?.addEventListener('click', function() {
                        const a = document.createElement('a');
                        a.href = qrCanvas.toDataURL('image/png');
                        a.download = 'QR_' + nomorTemuan + '.png';
                        a.click();
                    });

                    document.getElementById('btn-share-qr')?.addEventListener('click', function() {
                        if (navigator.share) {
                            navigator.share({
                                title: 'QR Temuan ' + nomorTemuan,
                                text: 'Scan QR untuk detail temuan ' + nomorTemuan,
                                url: detailUrl
                            }).catch(() => {});
                        } else {
                            navigator.clipboard.writeText(detailUrl).then(() => {
                                Swal.fire({ icon: 'info', title: 'Link Disalin', text: detailUrl, timer: 2000 });
                            });
                        }
                    });
                } catch(e) { console.error('QR Engine Error:', e); }
            }
        }

        // Dual File Input Trigger Setup
        function initFileInputs() {
            document.querySelectorAll('.btn-dual-gallery').forEach(btn => {
                btn.addEventListener('click', function() {
                    const target = document.querySelector(this.dataset.target);
                    target?.click();
                });
            });

            document.querySelectorAll('.btn-dual-camera').forEach(btn => {
                btn.addEventListener('click', function() {
                    const target = document.querySelector(this.dataset.target);
                    target?.click();
                });
            });

            const inputs = ['sebelum', 'proses', 'sesudah'];
            inputs.forEach(key => {
                const mainInput = document.getElementById('foto_' + key);
                const camInput = document.getElementById('foto_' + key + '_cam');
                const previewEl = document.getElementById('preview_name_foto_' + key);

                const handleFileChange = (file) => {
                    if (file && previewEl) {
                        previewEl.innerHTML = '<span class="text-success font-weight-bold"><i class="fas fa-check-circle me-1"></i> ' + file.name + '</span>';
                    }
                };

                camInput?.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        const dt = new DataTransfer();
                        dt.items.add(this.files[0]);
                        if (mainInput) mainInput.files = dt.files;
                        handleFileChange(this.files[0]);
                    }
                });

                mainInput?.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        handleFileChange(this.files[0]);
                    }
                });
            });
        }

        // Mobile Bottom Sheet & UI Sticky Events
        function initMobileEvents() {
            const fabBtn = document.getElementById('fabBtnTrigger');
            const overlay = document.getElementById('bottomSheetOverlay');
            const closeBtn = document.getElementById('bsActionClose');
            const header = document.getElementById('mobileHeader');

            function toggleSheet() {
                overlay?.classList.toggle('show');
                fabBtn?.classList.toggle('active');
            }

            fabBtn?.addEventListener('click', toggleSheet);
            closeBtn?.addEventListener('click', () => {
                overlay?.classList.remove('show');
                fabBtn?.classList.remove('active');
            });
            overlay?.addEventListener('click', function(e) {
                if (e.target === this) {
                    overlay.classList.remove('show');
                    fabBtn?.classList.remove('active');
                }
            });

            document.querySelectorAll('#btnStickyProgress, #bsActionProgress').forEach(el => {
                el.addEventListener('click', function() {
                    overlay?.classList.remove('show');
                    fabBtn?.classList.remove('active');
                    const targetCard = document.getElementById('progressFormCard');
                    if (targetCard) {
                        targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            });

            document.getElementById('bsActionCopy')?.addEventListener('click', function() {
                navigator.clipboard.writeText(window.location.href).then(() => {
                    Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Link disalin ke clipboard!', timer: 1500, showConfirmButton: false });
                    overlay?.classList.remove('show');
                    fabBtn?.classList.remove('active');
                });
            });

            window.addEventListener('scroll', function() {
                if (window.scrollY > 40) {
                    header?.classList.add('shrunken');
                } else {
                    header?.classList.remove('shrunken');
                }
            }, { passive: true });
        }

        return {
            init: function() {
                initLazyImages();
                initMap();
                initQR();
                initFileInputs();
                initMobileEvents();
            }
        };
    })();

    /**
     * SIDAK GALLERY - LIGHTBOX ENGINE
     */
    const SidakGallery = (function() {
        let scale = 1;
        let rotation = 0;
        let currentImgUrl = '';

        const modal = document.getElementById('enterprise-lightbox');
        const img = document.getElementById('lightboxImg');
        const wrapper = document.getElementById('lightboxWrapper');
        const downloadBtn = document.getElementById('lightboxDownload');

        function updateTransform() {
            if (wrapper) {
                wrapper.style.transform = `scale(${scale}) rotate(${rotation}deg)`;
            }
        }

        return {
            open: function(url) {
                currentImgUrl = url;
                scale = 1;
                rotation = 0;
                if (img) img.src = url;
                if (downloadBtn) downloadBtn.href = url;
                updateTransform();
                modal?.classList.add('active');
                document.body.style.overflow = 'hidden';
            },
            close: function() {
                modal?.classList.remove('active');
                document.body.style.overflow = '';
                if (img) img.src = '';
            },
            zoom: function(delta) {
                scale = Math.max(0.4, Math.min(scale + delta, 5));
                updateTransform();
            },
            rotate: function() {
                rotation = (rotation + 90) % 360;
                updateTransform();
            },
            reset: function() {
                scale = 1;
                rotation = 0;
                updateTransform();
            }
        };
    })();

    document.addEventListener('DOMContentLoaded', function() {
        SidakDetail.init();
    });
</script>
<?= $this->endSection() ?>
