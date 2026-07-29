<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Detail Temuan - <?= esc($temuan['nomor_temuan']) ?><?= $this->endSection() ?>
<?= $this->section('page_title') ?>Detail Temuan Inspeksi<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('temuan') ?>">Data Temuan</a></li>
<li class="breadcrumb-item active"><?= esc($temuan['nomor_temuan']) ?></li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
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
         "🗺️ *Sharelok (Google Maps)*: " . $sharelokUrl . "\n\n" .
         "🔗 *Lihat Detail*: " . site_url('temuan/detail/' . $temuan['id']);
$waUrl = "https://api.whatsapp.com/send?text=" . urlencode($waMsg);

// Determine Status & Priority Badges
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
?>

<style>
    /* CSS Animations & Custom Badges for Enterprise Mobile Detail */
    @keyframes pulse-danger {
        0%, 100% { transform: scale(1); opacity: 1; box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7); }
        50% { transform: scale(1.05); opacity: 0.85; box-shadow: 0 0 0 8px rgba(220, 38, 38, 0); }
    }
    .badge-prio-emergency {
        background-color: #dc2626 !important;
        color: #ffffff !important;
        animation: pulse-danger 1.8s infinite;
        font-weight: 800;
        letter-spacing: 0.5px;
    }
    .badge-prio-high {
        background-color: #ea580c !important;
        color: #ffffff !important;
        font-weight: 700;
    }
    .badge-prio-medium {
        background-color: #0284c7 !important;
        color: #ffffff !important;
        font-weight: 700;
    }
    .badge-status-belum {
        background-color: #dc2626 !important;
        color: #ffffff !important;
    }
    .badge-status-proses {
        background-color: #f59e0b !important;
        color: #ffffff !important;
    }
    .badge-status-padam {
        background-color: #7c3aed !important;
        color: #ffffff !important;
    }
    .badge-status-selesai {
        background-color: #059669 !important;
        color: #ffffff !important;
    }

    /* Mobile Only Styles (< 992px) */
    @media (max-width: 991.98px) {
        /* Collapsible Sticky Top Header */
        .mobile-sticky-header {
            position: sticky;
            top: 0;
            z-index: 1020;
            background: #ffffff;
            border-bottom: 2px solid #e2e8f0;
            padding: 10px 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
        }
        .mobile-sticky-header.shrunken {
            padding: 6px 16px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
        }

        /* 2-Column Photo Grid */
        .photo-grid-mobile {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .photo-grid-item {
            position: relative;
            aspect-ratio: 4/3;
            border-radius: 10px;
            overflow: hidden;
            background-color: #f1f5f9;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            cursor: pointer;
        }
        .photo-grid-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .photo-grid-item:hover img {
            transform: scale(1.06);
        }

        /* Sticky Bottom Action Bar */
        .mobile-sticky-bottom-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1040;
            background: #ffffff;
            border-top: 1px solid #cbd5e1;
            padding: 10px 16px;
            display: flex;
            gap: 8px;
            box-shadow: 0 -4px 15px rgba(0,0,0,0.1);
        }
        .mobile-sticky-bottom-bar .btn-sticky-act {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 8px;
            padding: 8px 4px;
        }

        /* Floating Action Button (Speed Dial) */
        .mobile-fab-container {
            position: fixed;
            bottom: 72px;
            right: 16px;
            z-index: 1050;
        }
        .btn-fab-trigger {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, #005eb8, #0284c7);
            color: #ffffff;
            border: none;
            box-shadow: 0 6px 16px rgba(0, 94, 184, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-fab-trigger.active {
            transform: rotate(135deg);
            background: linear-gradient(135deg, #dc2626, #991b1b);
        }

        /* Bottom Sheet Modal */
        .bottom-sheet-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
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
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
            padding: 20px 16px 28px 16px;
            z-index: 1070;
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            max-height: 85vh;
            overflow-y: auto;
        }
        .bottom-sheet-overlay.show .bottom-sheet-content {
            transform: translateY(0);
        }
        .bottom-sheet-handle {
            width: 40px;
            height: 5px;
            background: #cbd5e1;
            border-radius: 3px;
            margin: 0 auto 16px auto;
        }
    }
</style>

<!-- Mobile Collapsible Sticky Top Header (< 992px) -->
<div class="mobile-sticky-header d-lg-none" id="mobileHeader">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="font-weight-bold text-primary" style="font-size: 14px;">
            <i class="fas fa-file-invoice mr-1"></i> <?= esc($temuan['nomor_temuan']) ?>
        </span>
        <div>
            <span class="badge px-2 py-1 me-1 <?= $statusClass ?>"><?= $statusStr ?></span>
            <span class="badge px-2 py-1 <?= $prioClass ?>"><?= $prio ?></span>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center" style="font-size: 11px;">
        <span class="text-muted"><i class="fas fa-bolt text-warning mr-1"></i> <?= esc($temuan['nama_penyulang']) ?></span>
        <span><?= $sla['badge_html'] ?></span>
    </div>
</div>

<div class="row">
    <!-- Kolom Utama: Data Temuan -->
    <div class="col-lg-8 col-12">
        <div class="card card-outline card-primary shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                <h3 class="card-title mb-0">
                    <i class="fas fa-circle-info text-primary me-1"></i> 
                    Nomor Temuan: <span class="font-weight-bold text-primary font-monospace"><?= esc($temuan['nomor_temuan']) ?></span>
                </h3>
                <div class="d-flex align-items-center ms-auto" style="gap: 8px;">
                    <a href="<?= $waUrl ?>" target="_blank" class="btn btn-success btn-sm font-weight-bold shadow-sm d-none d-lg-inline-flex" style="background-color: #25D366; border-color: #25D366; color: #ffffff; border-radius: 6px;">
                        <i class="fab fa-whatsapp me-1" style="font-size: 15px;"></i> Share ke WA
                    </a>
                    <span><?= $sla['badge_html'] ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php
                // Phase 19: AI Decision Support & Explainable Recommendation
                $aiService = new \App\Services\PredictiveMaintenanceService();
                $aiRecommendation = $aiService->getExplainableRecommendation($temuan);
                ?>

                <!-- Phase 19: AI Explainable Recommendation Box -->
                <div class="card border-0 bg-dark text-white rounded-3 mb-4 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0 text-warning d-flex align-items-center">
                                <i class="fas fa-brain text-warning me-2"></i> AI DECISION SUPPORT & REKOMENDASI OLEH SIDAK AI
                            </h6>
                            <span class="badge <?= $aiRecommendation['badge_class'] ?>">
                                RISK SCORE: <?= number_format($aiRecommendation['score'], 1) ?> (<?= $aiRecommendation['category'] ?>)
                            </span>
                        </div>
                        <p class="mb-2 fw-bold text-white small" style="font-size: 13px;">
                            <i class="fas fa-lightbulb text-info me-1"></i> <?= esc($aiRecommendation['recommendation_text']) ?>
                        </p>
                        <div class="bg-secondary bg-opacity-25 rounded-2 p-2" style="font-size: 12px;">
                            <strong class="text-warning d-block mb-1"><i class="fas fa-magnifying-glass-chart me-1"></i> Analisis Rasional Explainable AI:</strong>
                            <ul class="mb-0 ps-3 text-light">
                                <?php foreach ($aiRecommendation['reasons'] as $reason): ?>
                                    <li><?= esc($reason) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Info Cards Grid Modern -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6 col-12">
                        <div class="p-3 rounded border bg-light h-100">
                            <div class="text-muted small font-weight-bold mb-1"><i class="fas fa-building-user text-primary mr-1"></i> ULP & Wilayah Kerja</div>
                            <div class="font-weight-bold text-dark fs-6"><?= esc($temuan['nama_ulp']) ?></div>
                            <div class="small text-secondary mt-1">
                                ⚡ Penyulang: <strong><?= esc($temuan['nama_penyulang']) ?></strong> | Section: <strong><?= esc($temuan['nama_section']) ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-12">
                        <div class="p-3 rounded border bg-light h-100">
                            <div class="text-muted small font-weight-bold mb-1"><i class="fas fa-tags text-info mr-1"></i> Classifikasi & Status</div>
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

                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th style="width: 140px;">Potensi Gangguan</th>
                                <td>: <span class="badge bg-info text-dark font-weight-bold px-2 py-1" style="font-size: 13px; font-weight: 700; color: #000000 !important;"><?= esc($temuan['potensi_gangguan']) ?></span></td>
                            </tr>
                            <tr>
                                <th>Konduktor</th>
                                <td>: <?= esc($temuan['konduktor']) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th style="width: 140px;">Nomor Gardu (NOGA)</th>
                                <td>: <?= $temuan['noga'] ? esc($temuan['noga']) : '<span class="text-muted small">Tidak ada</span>' ?></td>
                            </tr>
                            <tr>
                                <th>Waktu Mulai</th>
                                <td>: <span class="font-weight-bold text-primary"><?= indo_datetime($temuan['created_at'] ?: $temuan['tanggal_temuan']) ?></span></td>
                            </tr>
                            <tr>
                                <th>Update Terakhir</th>
                                <td>: <span class="font-weight-bold text-secondary"><?= indo_datetime($temuan['updated_at']) ?></span></td>
                            </tr>
                            <tr>
                                <th>Lama Pengerjaan</th>
                                <td>: <span class="badge bg-success font-weight-bold px-2 py-1"><i class="fas fa-stopwatch me-1"></i> <?= duration($temuan['created_at'] ?: $temuan['tanggal_temuan'], $temuan['updated_at']) ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <hr class="border-secondary">

                <div class="mb-4">
                    <h6 class="font-weight-bold text-primary mb-2" style="font-size: 15px;">
                        <i class="fas fa-screwdriver-wrench me-1"></i> Material Dibutuhkan:
                    </h6>
                    <div class="p-3 rounded" style="background-color: #f1f5f9; border-left: 4px solid #005eb8; border: 1px solid #cbd5e1; border-left-width: 4px; font-size: 14px; font-weight: 600; color: #0f172a; white-space: pre-wrap; line-height: 1.6;">
                        <?= esc($temuan['material']) ?: '<span class="text-muted font-italic">Tidak ada spesifikasi material</span>' ?>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="font-weight-bold text-danger mb-2" style="font-size: 15px;">
                        <i class="fas fa-triangle-exclamation me-1"></i> Detail Kerusakan:
                    </h6>
                    <div class="p-3 rounded" style="background-color: #fff7ed; border-left: 4px solid #ea580c; border: 1px solid #fed7aa; border-left-width: 4px; font-size: 14px; font-weight: 600; color: #1c1917; white-space: pre-wrap; line-height: 1.6;">
                        <?= esc($temuan['detail_temuan']) ?>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="font-weight-bold text-success mb-2" style="font-size: 15px;">
                        <i class="fas fa-map-location-dot me-1"></i> Alamat Lokasi:
                    </h6>
                    <div class="p-3 rounded" style="background-color: #f0fdf4; border-left: 4px solid #16a34a; border: 1px solid #bbf7d0; border-left-width: 4px; font-size: 14px; font-weight: 600; color: #052e16; white-space: pre-wrap; line-height: 1.6;">
                        <?= esc($temuan['alamat']) ?>
                    </div>
                </div>

                <!-- 2-Column Photo Gallery Task 3 -->
                <div class="mb-3">
                    <h6 class="font-weight-bold"><i class="fas fa-images text-secondary me-1"></i> Galeri Foto Temuan Lapangan:</h6>
                    <div class="photo-grid-mobile">
                        <?php 
                        $photos = json_decode($temuan['foto'], true) ?: [];
                        if (is_string($temuan['foto']) && empty($photos) && !empty($temuan['foto'])) {
                            $photos = [$temuan['foto']];
                        }

                        if (empty($photos)): ?>
                            <div class="col-12">
                                <p class="text-muted italic"><i class="fas fa-info-circle me-1"></i> Tidak ada foto temuan yang diunggah.</p>
                            </div>
                        <?php else:
                            foreach ($photos as $photo):
                                if (empty($photo)) continue;
                                $filePath = get_photo_url($photo, $temuan['foto_path'] ?? 'foto/');
                        ?>
                            <div class="photo-grid-item" onclick="openPhotoModal('<?= $filePath ?>')">
                                <img src="<?= $filePath ?>" loading="lazy" alt="Foto Temuan" onerror="this.onerror=null; this.parentElement.innerHTML='<span class=\'text-muted small p-2 text-center d-flex flex-column align-items-center justify-content-center h-100\'><i class=\'fas fa-image-slash fs-5 mb-1\'></i> Gagal Muat</span>';">
                            </div>
                        <?php 
                            endforeach;
                        endif; 
                        ?>
                    </div>
                </div>

            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <a href="javascript:smartBack('<?= site_url('temuan') ?>');" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                <?php if (in_array(session()->get('user_role'), ['administrator', 'admin_ulp'])): ?>
                <a href="<?= site_url('temuan/edit/' . $temuan['id']) ?>" class="btn btn-warning text-dark font-weight-bold">
                    <i class="fas fa-edit mr-1"></i> Edit Temuan
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Timeline Histori Tindak Lanjut Task 4 -->
        <div class="card card-outline card-success mt-4 shadow-sm">
            <div class="card-header">
                <h3 class="card-title font-weight-bold"><i class="fas fa-timeline text-success mr-1"></i> Riwayat Progress & Timeline Pekerjaan</h3>
            </div>
            <div class="card-body">
                <?php if (empty($history)): ?>
                    <p class="text-muted text-center py-3"><i class="fas fa-info-circle"></i> Belum ada riwayat progress tindak lanjut untuk temuan ini.</p>
                <?php else: ?>
                    <div class="timeline timeline-inverse">
                        <?php foreach ($history as $h):
                            $statusProg = $h['status_progress'] ?? 'PROSES';
                            $statusBadgeClass = match($statusProg) {
                                'SELESAI'     => 'bg-success',
                                'BUTUH PADAM' => 'bg-purple',
                                'TERKENDALA'  => 'bg-warning text-dark',
                                default       => 'bg-info'
                            };
                        ?>
                            <!-- timeline item -->
                            <div class="animate__animated animate__fadeIn">
                                <i class="fas <?= $statusProg === 'SELESAI' ? 'fa-check bg-success' : 'fa-wrench bg-info' ?>"></i>
                                <div class="timeline-item card shadow-none bg-light border">
                                    <span class="time text-muted"><i class="far fa-clock"></i> <?= date('d-m-Y H:i', strtotime($h['tanggal'])) ?></span>
                                    <h3 class="timeline-header font-weight-bold" style="font-size: 13px;">
                                        Oleh: <?= esc($h['pelaksana']) ?> 
                                        <span class="badge ml-2 <?= $statusBadgeClass ?>">
                                            <?= esc($statusProg) ?>
                                        </span>
                                    </h3>
                                    <div class="timeline-body" style="font-size: 13px;">
                                        <p class="mb-2 font-weight-semibold text-dark"><?= esc($h['komentar']) ?></p>
                                        
                                        <!-- Progress Photos Grid -->
                                        <div class="row g-2">
                                            <?php if ($h['foto_sebelum']): $urlSeb = get_photo_url($h['foto_sebelum']); ?>
                                                <div class="col-4">
                                                    <span class="text-xs text-muted d-block mb-1">Sebelum</span>
                                                    <div class="photo-grid-item" style="aspect-ratio: 1/1;" onclick="openPhotoModal('<?= $urlSeb ?>')">
                                                        <img src="<?= $urlSeb ?>" loading="lazy" alt="Sebelum">
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($h['foto_proses']): $urlPro = get_photo_url($h['foto_proses']); ?>
                                                <div class="col-4">
                                                    <span class="text-xs text-muted d-block mb-1">Proses</span>
                                                    <div class="photo-grid-item" style="aspect-ratio: 1/1;" onclick="openPhotoModal('<?= $urlPro ?>')">
                                                        <img src="<?= $urlPro ?>" loading="lazy" alt="Proses">
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($h['foto_sesudah']): $urlSes = get_photo_url($h['foto_sesudah']); ?>
                                                <div class="col-4">
                                                    <span class="text-xs text-muted d-block mb-1">Sesudah</span>
                                                    <div class="photo-grid-item" style="aspect-ratio: 1/1;" onclick="openPhotoModal('<?= $urlSes ?>')">
                                                        <img src="<?= $urlSes ?>" loading="lazy" alt="Sesudah">
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- END timeline item -->
                        <?php endforeach; ?>
                        <div>
                            <i class="far fa-clock bg-gray"></i>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kolom Samping: Peta GIS Lokasi & QR Code -->
    <div class="col-lg-4 col-12">
        <!-- Peta Lokasi Leaflet Task 7 & 8 -->
        <?php if ($temuan['latitude'] !== null && $temuan['longitude'] !== null): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-map-location-dot text-danger mr-1"></i> Mini Map Preview</h3>
                </div>
                <div class="card-body p-0">
                    <div id="detail-map" style="height: 240px; width: 100%;"></div>
                </div>
                <div class="card-footer p-2 text-center">
                    <a href="https://maps.google.com/?q=<?= $temuan['latitude'] ?>,<?= $temuan['longitude'] ?>" target="_blank" class="btn btn-success btn-sm btn-block font-weight-bold" style="background-color: #059669; border: none;">
                        <i class="fas fa-diamond-turn-right mr-1"></i> Navigasi Google Maps
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- QR Code Card -->
        <div class="card text-center py-3 shadow-sm mb-4">
            <div class="card-header border-0 bg-transparent">
                <h3 class="card-title text-center float-none mb-0"><i class="fas fa-qrcode text-primary mr-1"></i> QR Code Temuan</h3>
            </div>
            <div class="card-body d-flex flex-column align-items-center p-2">
                <div class="bg-white p-2 rounded mb-2 border shadow-sm">
                    <canvas id="qr-code-canvas"></canvas>
                </div>
                <button class="btn btn-outline-primary btn-sm font-weight-bold" id="btn-download-qr"><i class="fas fa-download mr-1"></i> Unduh QR Code</button>
            </div>
        </div>

        <!-- Form Update Progress Tindak Lanjut -->
        <?php if ($temuan['status'] !== 'SELESAI' && check_role(['administrator', 'admin_ulp', 'pdkb', 'har_gardu', 'har_row', 'har_crane', 'yantek'])): ?>
            <div class="card card-outline card-info shadow-sm" id="progressFormCard">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-pen-to-square text-info mr-1"></i> Tambah Progress Kerja</h3>
                </div>
                <form action="<?= site_url('temuan/tindak-lanjut/' . $temuan['id']) ?>" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="card-body">
                        
                        <div class="form-group mb-3">
                            <label for="status_progress">Status Progress Kerja</label>
                            <select name="status_progress" id="status_progress" class="form-control select2" required>
                                <option value="PROSES">PROSES PEKERJAAN</option>
                                <option value="TERKENDALA">TERKENDALA (Bahan / Akses / Cuaca)</option>
                                <option value="BUTUH PADAM">BUTUH PADAM (Butuh Pemadaman Listrik)</option>
                                <option value="SELESAI">SELESAI PEKERJAAN</option>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label for="komentar">Komentar / Keterangan</label>
                            <textarea name="komentar" id="komentar" class="form-control" rows="3" placeholder="Contoh: Sedang dilakukan perapian isolator tumpu..." required></textarea>
                        </div>

                        <!-- Foto Sebelum -->
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold">Foto Sebelum Pekerjaan (Opsional)</label>
                            <div class="btn-group w-100 mb-1" role="group">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-dual-gallery" data-target="#foto_sebelum">📁 Berkas</button>
                                <button type="button" class="btn btn-sm btn-outline-success btn-dual-camera" data-target="#foto_sebelum_cam">📷 Kamera</button>
                            </div>
                            <input type="file" name="foto_sebelum" id="foto_sebelum" class="d-none" accept="image/*">
                            <input type="file" id="foto_sebelum_cam" class="d-none" accept="image/*" capture="environment">
                            <div class="file-name-preview small text-muted text-truncate" id="preview_name_foto_sebelum">Belum ada foto</div>
                        </div>

                        <!-- Foto Proses -->
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold">Foto Proses Pekerjaan (Opsional)</label>
                            <div class="btn-group w-100 mb-1" role="group">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-dual-gallery" data-target="#foto_proses">📁 Berkas</button>
                                <button type="button" class="btn btn-sm btn-outline-success btn-dual-camera" data-target="#foto_proses_cam">📷 Kamera</button>
                            </div>
                            <input type="file" name="foto_proses" id="foto_proses" class="d-none" accept="image/*">
                            <input type="file" id="foto_proses_cam" class="d-none" accept="image/*" capture="environment">
                            <div class="file-name-preview small text-muted text-truncate" id="preview_name_foto_proses">Belum ada foto</div>
                        </div>

                        <!-- Foto Sesudah -->
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold">Foto Sesudah Pekerjaan (Opsional)</label>
                            <div class="btn-group w-100 mb-1" role="group">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-dual-gallery" data-target="#foto_sesudah">📁 Berkas</button>
                                <button type="button" class="btn btn-sm btn-outline-success btn-dual-camera" data-target="#foto_sesudah_cam">📷 Kamera</button>
                            </div>
                            <input type="file" name="foto_sesudah" id="foto_sesudah" class="d-none" accept="image/*">
                            <input type="file" id="foto_sesudah_cam" class="d-none" accept="image/*" capture="environment">
                            <div class="file-name-preview small text-muted text-truncate" id="preview_name_foto_sesudah">Belum ada foto</div>
                        </div>

                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-info text-white btn-block font-weight-bold"><i class="fas fa-paper-plane mr-1"></i> Kirim Progress</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Task 2: Sticky Bottom Action Bar (< 992px) -->
<div class="mobile-sticky-bottom-bar d-lg-none">
    <?php if (in_array(session()->get('user_role'), ['administrator', 'admin_ulp'])): ?>
        <a href="<?= site_url('temuan/edit/' . $temuan['id']) ?>" class="btn btn-warning btn-sticky-act text-dark">
            <i class="fas fa-edit"></i> Edit
        </a>
    <?php endif; ?>
    <button type="button" class="btn btn-info btn-sticky-act text-white" id="btnStickyProgress">
        <i class="fas fa-wrench"></i> Progress
    </button>
    <a href="<?= $waUrl ?>" target="_blank" class="btn btn-success btn-sticky-act text-white" style="background-color: #25D366; border: none;">
        <i class="fab fa-whatsapp"></i> Share
    </a>
    <a href="<?= $sharelokUrl ?>" target="_blank" class="btn btn-primary btn-sticky-act text-white">
        <i class="fas fa-map-marker-alt"></i> Maps
    </a>
</div>

<!-- Task 1: Floating Action Button (Speed Dial) (< 992px) -->
<div class="mobile-fab-container d-lg-none">
    <button type="button" class="btn-fab-trigger" id="fabBtnTrigger" title="Menu Opsi">
        <i class="fas fa-ellipsis-v" id="fabIcon"></i>
    </button>
</div>

<!-- Task 11: Bottom Sheet Speed Dial Modal (< 992px) -->
<div class="bottom-sheet-overlay" id="bottomSheetOverlay">
    <div class="bottom-sheet-content">
        <div class="bottom-sheet-handle"></div>
        <h6 class="font-weight-bold text-dark mb-3 text-center border-bottom pb-2">
            <i class="fas fa-sliders text-primary me-1"></i> Menu Opsi Enterprise
        </h6>
        <div class="list-group list-group-flush">
            <a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex align-items-center py-2" id="bsActionDetail">
                <i class="fas fa-eye text-primary me-3 fs-5" style="width: 24px;"></i> Reload Detail
            </a>
            <?php if (in_array(session()->get('user_role'), ['administrator', 'admin_ulp'])): ?>
                <a href="<?= site_url('temuan/edit/' . $temuan['id']) ?>" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                    <i class="fas fa-pencil-alt text-warning me-3 fs-5" style="width: 24px;"></i> Edit Temuan
                </a>
            <?php endif; ?>
            <a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex align-items-center py-2" id="bsActionProgress">
                <i class="fas fa-wrench text-info me-3 fs-5" style="width: 24px;"></i> Tambah Progress
            </a>
            <a href="<?= $waUrl ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                <i class="fab fa-whatsapp text-success me-3 fs-5" style="width: 24px;"></i> Share WhatsApp
            </a>
            <a href="<?= $sharelokUrl ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center py-2">
                <i class="fas fa-map-marked-alt text-danger me-3 fs-5" style="width: 24px;"></i> Google Maps
            </a>
            <a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex align-items-center py-2" id="bsActionCopy">
                <i class="fas fa-copy text-secondary me-3 fs-5" style="width: 24px;"></i> Copy Link Detail
            </a>
            <a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex align-items-center py-2" onclick="window.print()">
                <i class="fas fa-file-pdf text-danger me-3 fs-5" style="width: 24px;"></i> Download PDF / Cetak
            </a>
            <a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex align-items-center py-2" onclick="window.print()">
                <i class="fas fa-print text-dark me-3 fs-5" style="width: 24px;"></i> Print Detail
            </a>
            <a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex align-items-center py-2" onclick="location.reload()">
                <i class="fas fa-sync-alt text-success me-3 fs-5" style="width: 24px;"></i> Refresh
            </a>
            <a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex align-items-center py-2 text-danger font-weight-bold" id="bsActionClose">
                <i class="fas fa-times me-3 fs-5" style="width: 24px;"></i> Tutup Menu
            </a>
        </div>
    </div>
</div>

<!-- ===== CUSTOM LIGHTBOX ===== -->
<div id="photo-lightbox" style="
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.92);
    z-index: 99999;
    flex-direction: column;
    align-items: center;
    justify-content: center;
">
    <!-- Header -->
    <div style="width:100%; max-width:900px; display:flex; align-items:center; justify-content:space-between; padding:10px 16px; flex-shrink:0;">
        <span style="color:#fff; font-family:'Outfit',sans-serif; font-weight:600; font-size:1rem;">
            <i class="fas fa-image" style="color:#00c6fb; margin-right:6px;"></i> Preview Foto
        </span>
        <button onclick="closeLightbox()" style="background:rgba(255,255,255,0.15); border:none; color:#fff; width:36px; height:36px; border-radius:50%; font-size:1.1rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background 0.2s;">
            &times;
        </button>
    </div>
    <!-- Image Container -->
    <div id="lb-img-container" style="flex:1; width:100%; max-width:900px; overflow:auto; display:flex; align-items:center; justify-content:center; padding:0 16px;">
        <img id="lb-img" src="" alt="Preview" style="max-width:100%; max-height:75vh; object-fit:contain; border-radius:8px; transition:transform 0.2s ease; cursor:grab; user-select:none;">
    </div>
    <!-- Footer Controls -->
    <div style="width:100%; max-width:900px; display:flex; align-items:center; justify-content:space-between; padding:12px 16px; flex-shrink:0; gap:8px;">
        <div style="display:flex; gap:8px;">
            <button onclick="lbZoom(1)"  style="background:linear-gradient(135deg,#0072ff,#00c6fb); border:none; color:#fff; padding:7px 14px; border-radius:8px; font-size:13px; cursor:pointer;">
                <i class="fas fa-plus"></i> Zoom In
            </button>
            <button onclick="lbZoom(-1)" style="background:linear-gradient(135deg,#0072ff,#00c6fb); border:none; color:#fff; padding:7px 14px; border-radius:8px; font-size:13px; cursor:pointer;">
                <i class="fas fa-minus"></i> Zoom Out
            </button>
            <button onclick="lbReset()"  style="background:#555; border:none; color:#fff; padding:7px 14px; border-radius:8px; font-size:13px; cursor:pointer;">
                <i class="fas fa-rotate"></i> Reset
            </button>
        </div>
        <div style="display:flex; gap:8px;">
            <a id="lb-download" href="" download style="background:linear-gradient(135deg,#00c6fb,#005eb8); border:none; color:#fff; padding:7px 14px; border-radius:8px; font-size:13px; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                <i class="fas fa-download"></i> Unduh
            </a>
            <button onclick="closeLightbox()" style="background:linear-gradient(135deg,#ff416c,#ff4b2b); border:none; color:#fff; padding:7px 14px; border-radius:8px; font-size:13px; cursor:pointer;">
                <i class="fas fa-times"></i> Tutup
            </button>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('plugins/qrious.min.js') ?>"></script>
<script>
    var lbScale = 1;

    function openPhotoModal(imgUrl) {
        lbScale = 1;
        document.getElementById('lb-img').src = imgUrl;
        document.getElementById('lb-img').style.transform = 'scale(1)';
        document.getElementById('lb-download').href = imgUrl;
        var lb = document.getElementById('photo-lightbox');
        lb.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        var lb = document.getElementById('photo-lightbox');
        lb.style.display = 'none';
        document.body.style.overflow = '';
        lbScale = 1;
        document.getElementById('lb-img').src = '';
        document.getElementById('lb-img').style.transform = 'scale(1)';
    }

    function lbZoom(direction) {
        if (direction > 0) {
            lbScale = Math.min(lbScale + 0.3, 6);
        } else {
            lbScale = Math.max(lbScale - 0.3, 0.3);
        }
        document.getElementById('lb-img').style.transform = 'scale(' + lbScale + ')';
    }

    function lbReset() {
        lbScale = 1;
        document.getElementById('lb-img').style.transform = 'scale(1)';
    }

    document.addEventListener('click', function(e) {
        var lb = document.getElementById('photo-lightbox');
        if (e.target === lb) closeLightbox();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeLightbox();
    });

    $(function() {
        // --- Sticky Header Scroll Shrink Task 5 ---
        $(window).scroll(function() {
            if ($(this).scrollTop() > 40) {
                $('#mobileHeader').addClass('shrunken');
            } else {
                $('#mobileHeader').removeClass('shrunken');
            }
        });

        // --- Bottom Sheet & Speed Dial Controls Task 1 & 11 ---
        function toggleBottomSheet() {
            $('#bottomSheetOverlay').toggleClass('show');
            $('#fabBtnTrigger').toggleClass('active');
        }

        $('#fabBtnTrigger').click(toggleBottomSheet);
        $('#bsActionClose, #bottomSheetOverlay').click(function(e) {
            if (e.target === this) {
                $('#bottomSheetOverlay').removeClass('show');
                $('#fabBtnTrigger').removeClass('active');
            }
        });

        $('#bsActionDetail').click(function() {
            location.reload();
        });

        $('#btnStickyProgress, #bsActionProgress').click(function() {
            $('#bottomSheetOverlay').removeClass('show');
            $('#fabBtnTrigger').removeClass('active');
            const target = $('#progressFormCard');
            if (target.length) {
                $('html, body').animate({ scrollTop: target.offset().top - 80 }, 500);
            }
        });

        $('#bsActionCopy').click(function() {
            navigator.clipboard.writeText(window.location.href).then(function() {
                alert('Link URL temuan berhasil disalin ke clipboard!');
                $('#bottomSheetOverlay').removeClass('show');
                $('#fabBtnTrigger').removeClass('active');
            });
        });

        // --- QR CODE ---
        const nomorTemuan = "<?= esc($temuan['nomor_temuan']) ?>";
        const latVal = <?= $temuan['latitude']  !== null ? $temuan['latitude']  : 'null' ?>;
        const lngVal = <?= $temuan['longitude'] !== null ? $temuan['longitude'] : 'null' ?>;
        
        let qrValue = "<?= site_url('temuan/detail/' . $temuan['id']) ?>";
        if (latVal && lngVal) {
            qrValue = 'https://maps.google.com/?q=' + latVal + ',' + lngVal;
        }

        if (document.getElementById('qr-code-canvas') && typeof QRious !== 'undefined') {
            try {
                new QRious({
                    element: document.getElementById('qr-code-canvas'),
                    value: qrValue,
                    size: 160,
                    background: '#ffffff',
                    foreground: '#121212',
                    level: 'H'
                });
            } catch(e) { console.error('QR Generator Error:', e); }
        }

        $('#btn-download-qr').click(function() {
            const canvas = document.getElementById('qr-code-canvas');
            if (canvas) {
                const a = document.createElement('a');
                a.href = canvas.toDataURL('image/png');
                a.download = 'QR_' + nomorTemuan + '.png';
                a.click();
            }
        });

        // --- LEAFLET MAP ---
        const lat = <?= $temuan['latitude']  !== null ? $temuan['latitude']  : 'null' ?>;
        const lng = <?= $temuan['longitude'] !== null ? $temuan['longitude'] : 'null' ?>;
        const defaultLat = lat ? lat : -7.4478;
        const defaultLng = lng ? lng : 112.7183;

        if ($('#detail-map').length > 0 && typeof L !== 'undefined') {
            const map = L.map('detail-map').setView([defaultLat, defaultLng], lat ? 15 : 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 20
            }).addTo(map);

            const customIcon = L.icon({
                iconUrl: '<?= base_url('assets/img/logo_sidak.png') ?>',
                iconSize: [36, 36],
                iconAnchor: [18, 36],
                popupAnchor: [0, -38]
            });

            if (lat && lng) {
                L.marker([lat, lng], { icon: customIcon }).addTo(map)
                    .bindPopup('<b>' + nomorTemuan + '</b><br><small><?= esc($temuan['alamat']) ?></small>')
                    .openPopup();
            } else {
                L.marker([defaultLat, defaultLng], { icon: customIcon }).addTo(map)
                    .bindPopup('<b>' + nomorTemuan + ' (Lokasi ULP/Sidoarjo)</b><br><small><?= esc($temuan['alamat']) ?></small>');
            }

            setTimeout(function() { map.invalidateSize(); }, 400);
        }

        // Dual Photo triggers (Berkas / Kamera)
        $(document).on('click', '.btn-dual-gallery', function() {
            const target = $(this).data('target');
            $(target).trigger('click');
        });

        $(document).on('click', '.btn-dual-camera', function() {
            const target = $(this).data('target');
            $(target).trigger('click');
        });

        $(document).on('change', '#foto_sebelum, #foto_sebelum_cam, #foto_proses, #foto_proses_cam, #foto_sesudah, #foto_sesudah_cam', function() {
            if (this.files && this.files.length > 0) {
                const f = this.files[0];
                let fieldId = this.id.replace('_cam', '');
                
                if (this.id.endsWith('_cam')) {
                    const mainInput = document.getElementById(fieldId);
                    if (mainInput) {
                        const dt = new DataTransfer();
                        dt.items.add(f);
                        mainInput.files = dt.files;
                    }
                }
                $('#preview_name_' + fieldId).html('<span class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> ' + f.name + '</span>');
            }
        });
    });
</script>
<?= $this->endSection() ?>

