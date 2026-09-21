<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Temuan Jaringan - <?= esc($temuan['nomor_temuan']) ?> - SIDAK TEJO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --pln-blue: #005eb8;
            --pln-blue-dark: #003b73;
            --pln-yellow: #ffc72c;
        }
        body {
            background: linear-gradient(135deg, #0b132b 0%, #1c2541 100%);
            min-height: 100vh;
            font-family: 'Outfit', sans-serif;
            color: #334155;
        }
        .share-container {
            max-width: 860px;
            margin: 24px auto 48px auto;
            padding: 0 16px;
        }
        .card-main {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
            overflow: hidden;
            border: none;
        }
        .header-brand {
            background: linear-gradient(135deg, #005eb8 0%, #003b73 100%);
            padding: 24px 28px;
            color: #ffffff;
        }
        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
        .badge-emergency {
            background: #dc2626 !important;
            color: #ffffff;
            font-weight: 700;
        }
        .badge-high {
            background: #ea580c !important;
            color: #ffffff;
            font-weight: 700;
        }
        .badge-medium {
            background: #0284c7 !important;
            color: #ffffff;
            font-weight: 700;
        }
        .photo-thumbnail {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        .photo-thumbnail:hover {
            transform: scale(1.02);
        }
    </style>
</head>
<body>

<div class="share-container">
    <!-- Main Card -->
    <div class="card-main">
        <!-- Brand Header -->
        <div class="header-brand">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="Logo PLN" style="max-height: 42px;" onerror="this.style.display='none'">
                    <div>
                        <h5 class="mb-0 fw-bold text-white tracking-wide">SIDAK TEJO</h5>
                        <small class="text-white-50" style="font-size: 11px;">PT PLN (Persero) UP3 Sidoarjo — Deteksi & Keandalan Jaringan</small>
                    </div>
                </div>
                <span class="badge bg-warning text-dark px-3 py-2 fw-bold" style="border-radius: 8px; font-size: 12px;">
                    <i class="fas fa-shield-halved me-1"></i> REKAP TEMUAN RESMI
                </span>
            </div>
        </div>

        <div class="p-4">
            <!-- Finding Title Bar -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pb-3 mb-3 border-bottom">
                <div>
                    <span class="text-muted small d-block">Nomor Temuan Inspeksi</span>
                    <h4 class="fw-bold text-primary mb-0 font-mono"><?= esc($temuan['nomor_temuan']) ?></h4>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <?php
                    $prio = strtoupper($temuan['prioritas'] ?? 'MEDIUM');
                    $prioBadgeClass = match($prio) {
                        'EMERGENCY' => 'badge-emergency',
                        'HIGH'      => 'badge-high',
                        default     => 'badge-medium'
                    };
                    $status = strtoupper($temuan['status'] ?? 'BELUM');
                    $statusBadgeClass = match($status) {
                        'SELESAI' => 'bg-success',
                        'PROSES'  => 'bg-warning text-dark',
                        default   => 'bg-danger'
                    };
                    ?>
                    <span class="badge <?= $prioBadgeClass ?> px-3 py-2 fs-7">Prioritas: <?= $prio ?></span>
                    <span class="badge <?= $statusBadgeClass ?> px-3 py-2 fs-7">Status: <?= $status ?></span>
                </div>
            </div>

            <!-- ASSET JARINGAN CONTEXT CARD (CR-HOTFIX-03) -->
            <div class="card border-primary border-opacity-50 rounded-4 mb-4 shadow-sm" style="background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%);">
                <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 px-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary p-2 fs-6 rounded-circle"><i class="fas fa-network-wired"></i></span>
                        <div>
                            <h6 class="mb-0 fw-bold text-primary">ASSET JARINGAN DISTRIBUSI</h6>
                            <small class="text-muted" style="font-size: 11px;">Konteks Otoritatif Jaringan PLN Sidoarjo</small>
                        </div>
                    </div>
                    <?php if (!empty($linkedAsset)): ?>
                        <div class="d-flex gap-2">
                            <a href="<?= site_url('gis?asset_id=' . $linkedAsset['id']) ?>" target="_blank" class="btn btn-success btn-sm fw-bold shadow-sm" style="border-radius: 8px;">
                                <i class="fas fa-map-location-dot me-1"></i> Buka di GIS
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body p-3">
                    <?php if (!empty($linkedAsset)): ?>
                    <div class="row g-3">
                        <div class="col-md-6 col-12">
                            <div class="p-3 rounded-3 bg-white border h-100">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-muted small"><i class="fas fa-barcode me-1"></i> Asset ID:</span>
                                    <span class="badge bg-dark font-mono"><?= esc($linkedAsset['kode_asset'] ?? 'AST-' . $linkedAsset['id']) ?></span>
                                </div>
                                <div class="fw-bold text-dark fs-5"><?= esc($linkedAsset['nama_asset'] ?? 'Aset #' . $linkedAsset['id']) ?></div>
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <span class="badge bg-info text-dark fw-bold"><?= esc($linkedAsset['jenis_asset'] ?? 'TIANG') ?></span>
                                    <?php if (!empty($linkedAsset['construction_code'])): ?>
                                        <span class="badge bg-secondary"><?= esc($linkedAsset['construction_code']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="p-3 rounded-3 bg-white border h-100">
                                <div class="text-muted small mb-1"><i class="fas fa-sitemap text-primary me-1"></i> Hierarki Jaringan:</div>
                                <div class="small text-dark">
                                    ULP: <strong><?= esc($linkedAsset['nama_ulp'] ?? $temuan['nama_ulp']) ?></strong><br>
                                    Penyulang: <strong><?= esc($linkedAsset['nama_penyulang'] ?? $temuan['nama_penyulang']) ?></strong><br>
                                    Section: <strong><?= esc($linkedAsset['nama_section'] ?? $temuan['nama_section']) ?></strong>
                                </div>
                                <div class="mt-2 pt-2 border-top small text-muted d-flex justify-content-between align-items-center flex-wrap gap-1">
                                    <div>
                                        <i class="fas fa-lock text-success me-1"></i> Koordinat:
                                        <code class="fw-bold text-dark font-mono"><?= esc($temuan['latitude']) ?>, <?= esc($temuan['longitude']) ?></code>
                                    </div>
                                    <?php
                                    $mapUrl = (!empty($temuan['latitude']) && !empty($temuan['longitude']))
                                        ? "https://maps.google.com/?q={$temuan['latitude']},{$temuan['longitude']}"
                                        : "https://www.google.com/maps/search/?api=1&query=" . urlencode($temuan['alamat']);
                                    ?>
                                    <a href="<?= $mapUrl ?>" target="_blank" class="btn btn-outline-primary btn-xs py-0 px-2 fw-bold" style="font-size: 11px;">
                                        <i class="fas fa-arrow-up-right-from-square me-1"></i> Maps
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="p-2 text-center text-muted small">
                        <i class="fas fa-circle-info me-1"></i> Temuan ini tidak terikat secara spesifik ke aset tiang/gardu tertentu.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Detail Kerusakan & Info Lapangan -->
            <div class="row g-3 mb-4">
                <div class="col-md-6 col-12">
                    <div class="p-3 rounded-3 border bg-light h-100">
                        <h6 class="fw-bold text-danger mb-2" style="font-size: 13px;">
                            <i class="fas fa-triangle-exclamation me-1"></i> Detail Kerusakan:
                        </h6>
                        <p class="mb-0 text-dark fw-semibold small" style="white-space: pre-wrap; line-height: 1.6;">
                            <?= esc($temuan['detail_temuan']) ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-12">
                    <div class="p-3 rounded-3 border bg-light h-100">
                        <h6 class="fw-bold text-success mb-2" style="font-size: 13px;">
                            <i class="fas fa-map-location-dot me-1"></i> Alamat & Lokasi:
                        </h6>
                        <p class="mb-1 text-dark small fw-semibold" style="line-height: 1.6;">
                            <?= esc($temuan['alamat']) ?>
                        </p>
                        <div class="small text-muted mt-2">
                            Pelaksana: <strong><?= esc($temuan['pelaksana']) ?></strong> | Jenis: <strong><?= esc($temuan['jenis_temuan']) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Material Standar PLN Dibutuhkan (MR-01) -->
            <?php if (!empty($materials)): ?>
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-primary mb-0" style="font-size: 14px;">
                        <i class="fas fa-boxes-stacked text-success me-1"></i> Material Standar Dibutuhkan:
                    </h6>
                    <span class="badge bg-success"><?= count($materials) ?> Item Tersimpan</span>
                </div>
                <div class="table-responsive rounded-3 border bg-white shadow-sm">
                    <table class="table table-sm table-hover mb-0" style="font-size: 12px;">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Material</th>
                                <th class="text-center">Volume</th>
                                <th class="text-center">Satuan</th>
                                <th>Kategori</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materials as $m): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= esc($m['material_name']) ?></td>
                                <td class="text-center fw-bold text-primary font-mono"><?= number_format((float)$m['quantity'], 2) ?></td>
                                <td class="text-center"><span class="badge bg-secondary"><?= esc($m['unit']) ?></span></td>
                                <td><span class="badge bg-light text-secondary border"><?= esc($m['material_category'] ?? 'REGULER') ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Aksesoris JTM Terpasang (CR-HOTFIX-02) -->
            <?php if (!empty($accessories)): ?>
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-primary mb-0" style="font-size: 14px;">
                        <i class="fas fa-shield-halved text-warning me-1"></i> Aksesoris JTM / Konduktor Terpasang:
                    </h6>
                    <span class="badge bg-primary"><?= count($accessories) ?> Item Tercatat</span>
                </div>
                <div class="table-responsive rounded-3 border bg-white shadow-sm">
                    <table class="table table-sm table-hover mb-0" style="font-size: 12px;">
                        <thead class="table-light">
                            <tr>
                                <th>Aksesoris</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Kondisi</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accessories as $acc): ?>
                            <tr>
                                <td class="fw-bold text-dark">
                                    <i class="fas fa-check-circle text-info me-1"></i>
                                    <?= esc($acc['accessory_name_snapshot'] ?? $acc['nama_aksesoris'] ?? $acc['kode_aksesoris'] ?? '-') ?>
                                </td>
                                <td class="text-center">
                                    <?php if (($acc['status'] ?? '') === 'ADA'): ?>
                                        <span class="badge bg-success px-2 py-1"><i class="fas fa-check me-1"></i> ADA</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary px-2 py-1"><i class="fas fa-times me-1"></i> TIDAK ADA</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $kondisi = strtoupper($acc['condition'] ?? 'BAIK');
                                    $badgeKondisi = match($kondisi) {
                                        'RUSAK'             => 'badge bg-danger',
                                        'PERLU_PENGGANTIAN' => 'badge bg-warning text-dark',
                                        default             => 'badge bg-success'
                                    };
                                    ?>
                                    <span class="<?= $badgeKondisi ?> px-2 py-1"><?= esc($kondisi) ?></span>
                                </td>
                                <td class="text-muted"><?= esc($acc['note'] ?: '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Galeri Foto Temuan Lapangan -->
            <div class="mb-3">
                <h6 class="fw-bold text-dark mb-2" style="font-size: 14px;">
                    <i class="fas fa-camera text-primary me-1"></i> Foto Temuan Lapangan:
                </h6>
                <div class="row g-2">
                    <?php
                    $photos = json_decode((string)($temuan['foto'] ?? ''), true) ?: [];
                    if (is_string($temuan['foto'] ?? null) && empty($photos) && !empty($temuan['foto'])) {
                        $photos = [$temuan['foto']];
                    }

                    if (empty($photos)): ?>
                        <div class="col-12">
                            <div class="p-3 text-center border rounded-3 bg-light text-muted small">
                                <i class="fas fa-image-slash me-1"></i> Tidak ada foto temuan yang diunggah.
                            </div>
                        </div>
                    <?php else:
                        foreach ($photos as $idx => $photo):
                            if (empty($photo)) continue;
                            $photoUrl = get_photo_url($photo, $temuan['foto_path'] ?? 'foto/', 'full');
                    ?>
                        <div class="col-md-4 col-6">
                            <a href="<?= $photoUrl ?>" target="_blank" rel="noopener noreferrer">
                                <img src="<?= $photoUrl ?>" class="photo-thumbnail border" alt="Foto Temuan <?= $idx + 1 ?>">
                            </a>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-light p-3 border-top text-center" style="font-size: 11px; color: #64748b;">
            <div>
                <i class="fas fa-lock me-1 text-success"></i> Tautan Publik Resmi SIDAK TEJO • PT PLN (Persero) UP3 Sidoarjo
            </div>
            <div class="mt-1">
                Kedaluwarsa: <?= !empty($shareLink['expires_at']) ? date('d-m-Y H:i', strtotime($shareLink['expires_at'])) . ' WIB' : '30 Hari' ?> • 
                Bebas Kredensial & Autentikasi Rahasia
            </div>
        </div>
    </div>
</div>

</body>
</html>
