<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Detail Temuan - <?= esc($temuan['nomor_temuan']) ?><?= $this->endSection() ?>
<?= $this->section('page_title') ?>Detail Temuan Inspeksi<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('temuan') ?>">Data Temuan</a></li>
<li class="breadcrumb-item active"><?= esc($temuan['nomor_temuan']) ?></li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$waMsg = "🚨 *TEMUAN INSPEKSI - SIDAK TEJO* 🚨\n\n" .
         "📌 *Nomor Temuan*: " . $temuan['nomor_temuan'] . "\n" .
         "📍 *ULP*: " . $temuan['nama_ulp'] . "\n" .
         "⚡ *Penyulang*: " . $temuan['nama_penyulang'] . "\n" .
         "📍 *Section*: " . $temuan['nama_section'] . "\n" .
         "🔴 *Jenis Temuan*: " . $temuan['jenis_temuan'] . "\n" .
         "⚠️ *Prioritas*: " . $temuan['prioritas'] . "\n" .
         "🔧 *Pelaksana*: " . $temuan['pelaksana'] . "\n" .
         "📝 *Detail*: " . $temuan['detail_temuan'] . "\n" .
         "📍 *Alamat*: " . $temuan['alamat'] . "\n\n" .
         "🔗 *Lihat Detail*: " . site_url('temuan/detail/' . $temuan['id']);
$waUrl = "https://api.whatsapp.com/send?text=" . urlencode($waMsg);
?>
<div class="row">
    <!-- Kolom Utama: Data Temuan -->
    <div class="col-lg-8 col-12">
        <div class="card card-outline card-primary">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                <h3 class="card-title mb-0">
                    <i class="fas fa-circle-info text-primary me-1"></i> 
                    Nomor Temuan: <span class="font-weight-bold text-primary font-monospace"><?= esc($temuan['nomor_temuan']) ?></span>
                </h3>
                <div class="d-flex align-items-center ms-auto" style="gap: 8px;">
                    <a href="<?= $waUrl ?>" target="_blank" class="btn btn-success btn-sm font-weight-bold shadow-sm" style="background-color: #25D366; border-color: #25D366; color: #ffffff; border-radius: 6px;">
                        <i class="fab fa-whatsapp me-1" style="font-size: 15px;"></i> Share ke WA
                    </a>
                    <span><?= $sla['badge_html'] ?></span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th style="width: 140px;">Unit ULP</th>
                                <td>: <?= esc($temuan['nama_ulp']) ?></td>
                            </tr>
                            <tr>
                                <th>Penyulang</th>
                                <td>: <?= esc($temuan['nama_penyulang']) ?></td>
                            </tr>
                            <tr>
                                <th>Section / Gardu</th>
                                <td>: <?= esc($temuan['nama_section']) ?></td>
                            </tr>
                            <tr>
                                <th>Jenis Temuan</th>
                                <td>: <?= esc($temuan['jenis_temuan']) ?></td>
                            </tr>
                            <tr>
                                <th>Pelaksana</th>
                                <td>: <span class="badge bg-primary text-white font-weight-bold px-2 py-1" style="font-size: 13px; font-weight: 700; color: #ffffff !important; letter-spacing: 0.5px;"><?= esc($temuan['pelaksana']) ?></span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th style="width: 140px;">Prioritas SLA</th>
                                <td>: <span class="font-weight-bold"><?= esc($temuan['prioritas']) ?></span></td>
                            </tr>
                            <tr>
                                <th>Potensi Gangguan</th>
                                <td>: <span class="badge bg-info text-dark font-weight-bold px-2 py-1" style="font-size: 13px; font-weight: 700; color: #000000 !important;"><?= esc($temuan['potensi_gangguan']) ?></span></td>
                            </tr>
                            <tr>
                                <th>Konduktor</th>
                                <td>: <?= esc($temuan['konduktor']) ?></td>
                            </tr>
                            <tr>
                                <th>Nomor Gardu (NOGA)</th>
                                <td>: <?= $temuan['noga'] ? esc($temuan['noga']) : '<span class="text-muted small">Tidak ada</span>' ?></td>
                            </tr>
                            <tr>
                                <th>Tanggal Temuan</th>
                                <td>: <?= date('d-m-Y', strtotime($temuan['tanggal_temuan'])) ?></td>
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

                <div class="mb-3">
                    <h6 class="font-weight-bold"><i class="fas fa-images text-secondary mr-1"></i> Foto Temuan Lapangan:</h6>
                    <div class="row px-2">
                        <?php 
                        $photos = json_decode($temuan['foto'], true) ?: [];
                        $uploadPath = $temuan['foto_path'];
                        foreach ($photos as $photo):
                            $filePath = base_url($uploadPath . $photo);
                        ?>
                            <div class="col-md-3 col-6 mb-3 px-1 animate__animated animate__fadeIn">
                                <div class="img-thumbnail bg-dark" style="border-color: #3d3d3d; border-radius: 8px; overflow: hidden; height: 120px; display: flex; align-items: center; justify-content: center; cursor: pointer;" onclick="openPhotoModal('<?= $filePath ?>')">
                                    <img src="<?= $filePath ?>" style="max-height: 100%; max-width: 100%; object-fit: cover;">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <a href="<?= site_url('temuan') ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                <?php if (in_array(session()->get('user_role'), ['administrator', 'admin_ulp'])): ?>
                <a href="<?= site_url('temuan/edit/' . $temuan['id']) ?>" class="btn btn-warning text-dark">
                    <i class="fas fa-edit mr-1"></i> Edit Temuan
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Timeline Histori Tindak Lanjut -->
        <div class="card card-outline card-success mt-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-timeline text-success mr-1"></i> Riwayat Tindak Lanjut Pekerjaan</h3>
            </div>
            <div class="card-body">
                <?php if (empty($history)): ?>
                    <p class="text-muted text-center py-3"><i class="fas fa-info-circle"></i> Belum ada riwayat progress tindak lanjut untuk temuan ini.</p>
                <?php else: ?>
                    <div class="timeline timeline-inverse">
                        <?php foreach ($history as $h):
                            $statusProg = $h['status_progress'] ?? 'PROSES';
                            $statusBadgeClass = 'bg-info';
                            if ($statusProg === 'SELESAI') $statusBadgeClass = 'bg-success';
                            elseif ($statusProg === 'BUTUH PADAM') $statusBadgeClass = 'bg-danger';
                        ?>
                            <!-- timeline item -->
                            <div class="animate__animated animate__fadeIn">
                                <i class="fas <?= $statusProg === 'SELESAI' ? 'fa-check bg-success' : 'fa-wrench bg-info' ?>"></i>
                                <div class="timeline-item card shadow-none bg-dark border-secondary">
                                    <span class="time text-muted"><i class="far fa-clock"></i> <?= date('d-m-Y H:i', strtotime($h['tanggal'])) ?></span>
                                    <h3 class="timeline-header font-weight-bold" style="font-size: 13px;">
                                        Oleh: <?= esc($h['pelaksana']) ?> 
                                        <span class="badge ml-2 <?= $statusBadgeClass ?>">
                                            <?= esc($statusProg) ?>
                                        </span>
                                    </h3>
                                    <div class="timeline-body" style="font-size: 13px;">

                                        <p class="mb-2"><?= esc($h['komentar']) ?></p>
                                        
                                        <!-- Progress Photos -->
                                        <div class="row">
                                            <?php if ($h['foto_sebelum']): ?>
                                                <div class="col-md-4 col-6 mb-2">
                                                    <span class="text-xs text-muted d-block">Sebelum</span>
                                                    <div class="img-thumbnail bg-dark" style="border-color: #3d3d3d; border-radius: 4px; overflow: hidden; height: 80px; display: flex; align-items: center; justify-content: center; cursor: pointer;" onclick="openPhotoModal('<?= base_url($h['foto_sebelum']) ?>')">
                                                        <img src="<?= base_url($h['foto_sebelum']) ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($h['foto_proses']): ?>
                                                <div class="col-md-4 col-6 mb-2">
                                                    <span class="text-xs text-muted d-block">Proses</span>
                                                    <div class="img-thumbnail bg-dark" style="border-color: #3d3d3d; border-radius: 4px; overflow: hidden; height: 80px; display: flex; align-items: center; justify-content: center; cursor: pointer;" onclick="openPhotoModal('<?= base_url($h['foto_proses']) ?>')">
                                                        <img src="<?= base_url($h['foto_proses']) ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($h['foto_sesudah']): ?>
                                                <div class="col-md-4 col-6 mb-2">
                                                    <span class="text-xs text-muted d-block">Sesudah</span>
                                                    <div class="img-thumbnail bg-dark" style="border-color: #3d3d3d; border-radius: 4px; overflow: hidden; height: 80px; display: flex; align-items: center; justify-content: center; cursor: pointer;" onclick="openPhotoModal('<?= base_url($h['foto_sesudah']) ?>')">
                                                        <img src="<?= base_url($h['foto_sesudah']) ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;">
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
        <!-- Peta Lokasi Leaflet -->
        <?php if ($temuan['latitude'] !== null && $temuan['longitude'] !== null): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-map-location-dot text-danger mr-1"></i> Peta Lokasi Temuan</h3>
                </div>
                <div class="card-body p-0">
                    <div id="detail-map" style="height: 260px; width: 100%;"></div>
                </div>
                <div class="card-footer text-center">
                    <a href="https://www.google.com/maps/search/?api=1&query=<?= $temuan['latitude'] ?>,<?= $temuan['longitude'] ?>" target="_blank" class="btn btn-success btn-sm btn-block"><i class="fas fa-map-marker-alt mr-1"></i> Buka Google Maps</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- QR Code Card -->
        <div class="card text-center py-4">
            <div class="card-header border-0 bg-transparent">
                <h3 class="card-title text-center float-none mb-0"><i class="fas fa-qrcode text-primary mr-1"></i> QR Code Temuan</h3>
            </div>
            <div class="card-body d-flex flex-column align-items-center">
                <div class="bg-white p-2 rounded mb-3 animate__animated animate__zoomIn">
                    <canvas id="qr-code-canvas"></canvas>
                </div>
                <button class="btn btn-outline-primary btn-sm" id="btn-download-qr"><i class="fas fa-download mr-1"></i> Unduh QR Code</button>
            </div>
        </div>

        <!-- Form Update Progress Tindak Lanjut -->
        <?php if ($temuan['status'] !== 'SELESAI' && check_role(['administrator', 'admin_ulp', 'pdkb', 'har_gardu', 'har_row', 'har_crane', 'yantek'])): ?>
            <div class="card card-outline card-info mt-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-pen-to-square text-info mr-1"></i> Tambah Progress Kerja</h3>
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
                        <button type="submit" class="btn btn-info text-white btn-block"><i class="fas fa-paper-plane mr-1"></i> Kirim Progress</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== CUSTOM LIGHTBOX (Tanpa Bootstrap Modal - 100% Reliable) ===== -->
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
        <button onclick="closeLightbox()" style="background:rgba(255,255,255,0.15); border:none; color:#fff; width:36px; height:36px; border-radius:50%; font-size:1.1rem; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,0,0,0.5)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
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
<!-- QR Code Library client-side (Lokal) -->
<script src="<?= base_url('plugins/qrious.min.js') ?>"></script>
<script>
    // ============================================================
    // CUSTOM LIGHTBOX - No Bootstrap Modal, No z-index conflict
    // ============================================================
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

    // Klik di luar gambar untuk tutup
    document.getElementById('photo-lightbox').addEventListener('click', function(e) {
        if (e.target === this || e.target === document.getElementById('lb-img-container')) {
            closeLightbox();
        }
    });

    // Tombol ESC untuk tutup
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeLightbox();
    });

    // ============================================================
    // DOM READY
    // ============================================================
    $(function() {

        // --- 1. QR CODE: link ke Google Maps jika ada koordinat, jika tidak ke detail temuan ---
        const nomorTemuan = "<?= esc($temuan['nomor_temuan']) ?>";
        const latVal = <?= $temuan['latitude']  !== null ? $temuan['latitude']  : 'null' ?>;
        const lngVal = <?= $temuan['longitude'] !== null ? $temuan['longitude'] : 'null' ?>;
        
        let qrValue = "<?= site_url('temuan/detail/' . $temuan['id']) ?>";
        if (latVal && lngVal) {
            qrValue = 'https://www.google.com/maps?q=' + latVal + ',' + lngVal;
        }

        new QRious({
            element: document.getElementById('qr-code-canvas'),
            value: qrValue,
            size: 160,
            background: '#ffffff',
            foreground: '#121212',
            level: 'H'
        });

        $('#btn-download-qr').click(function() {
            const canvas = document.getElementById('qr-code-canvas');
            const a = document.createElement('a');
            a.href = canvas.toDataURL('image/png');
            a.download = 'QR_' + nomorTemuan + '.png';
            a.click();
        });

        // --- 2. LEAFLET MAP ---
        const lat = <?= $temuan['latitude']  !== null ? $temuan['latitude']  : 'null' ?>;
        const lng = <?= $temuan['longitude'] !== null ? $temuan['longitude'] : 'null' ?>;

        if (lat && lng) {
            const map = L.map('detail-map').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 20
            }).addTo(map);

            const customIcon = L.icon({
                iconUrl: '<?= base_url('assets/img/logo_sidak.png') ?>',
                iconSize: [40, 40],
                iconAnchor: [20, 40],
                popupAnchor: [0, -42]
            });

            L.marker([lat, lng], { icon: customIcon }).addTo(map)
                .bindPopup('<b>' + nomorTemuan + '</b><br><small><?= esc($temuan['alamat']) ?></small>')
                .openPopup();
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
