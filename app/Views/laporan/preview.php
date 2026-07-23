<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Preview Laporan<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Preview Hasil Penyaringan Laporan<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('laporan') ?>">Pusat Laporan</a></li>
<li class="breadcrumb-item active">Preview</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card card-outline card-info">
            <div class="card-header d-flex justify-content-between align-items-center" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <h3 class="card-title mb-0"><i class="fas fa-list-check text-info mr-1"></i> Data Temuan Terfilter (<?= count($data) ?> Baris)</h3>
                <div class="d-flex align-items-center" style="gap: 8px;">
                    <!-- Tombol Kembali -->
                    <a href="<?= site_url('laporan') ?>" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                    </a>
                    
                    <!-- Form Cetak Laporan (POST filters) -->
                    <form action="<?= site_url('laporan/print') ?>" method="post" target="_blank" class="m-0 p-0" style="display: inline-block;">
                        <?= csrf_field() ?>
                        <?php if (isset($filters) && is_array($filters)): ?>
                            <?php foreach ($filters as $key => $val): ?>
                                <input type="hidden" name="<?= esc($key) ?>" value="<?= esc($val) ?>">
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-sm btn-success mr-1">
                            <i class="fas fa-print mr-1"></i> Cetak PDF
                        </button>
                    </form>

                    <!-- Form Ekspor Excel -->
                    <form action="<?= site_url('laporan/excel') ?>" method="post" class="m-0 p-0" style="display: inline-block;">
                        <?= csrf_field() ?>
                        <?php if (isset($filters) && is_array($filters)): ?>
                            <?php foreach ($filters as $key => $val): ?>
                                <input type="hidden" name="<?= esc($key) ?>" value="<?= esc($val) ?>">
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-sm btn-warning mr-1 text-dark" style="font-weight: 600;">
                            <i class="fas fa-file-excel mr-1"></i> Ekspor Excel
                        </button>
                    </form>

                    <!-- Form Ekspor CSV -->
                    <form action="<?= site_url('laporan/csv') ?>" method="post" class="m-0 p-0" style="display: inline-block;">
                        <?= csrf_field() ?>
                        <?php if (isset($filters) && is_array($filters)): ?>
                            <?php foreach ($filters as $key => $val): ?>
                                <input type="hidden" name="<?= esc($key) ?>" value="<?= esc($val) ?>">
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-sm btn-info text-white" style="font-weight: 600;">
                            <i class="fas fa-file-csv mr-1"></i> Ekspor CSV
                        </button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="table-preview-report" style="font-size: 12px; width: 100%;">
                        <thead>
                            <tr>
                                 <th>No</th>
                                 <th>Nomor Temuan</th>
                                 <th>ULP</th>
                                 <th>Penyulang</th>
                                 <th>Section</th>
                                 <th>Detail &amp; Material</th>
                                 <th>Foto Temuan</th>
                                 <th>Jenis</th>
                                 <th>Pelaksana</th>
                                 <th>Prioritas</th>
                                 <th>Potensi</th>
                                 <th>Tanggal</th>
                                 <th>Status/SLA</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php $no = 1; foreach ($data as $row): ?>
                                 <tr>
                                     <td><?= $no++ ?></td>
                                     <td>
                                         <a href="<?= site_url('temuan/detail/' . $row['id']) ?>" target="_blank" class="font-weight-bold font-monospace"><?= esc($row['nomor_temuan']) ?></a>
                                     </td>
                                     <td><?= esc($row['nama_ulp']) ?></td>
                                     <td><?= esc($row['nama_penyulang']) ?></td>
                                     <td><?= esc($row['nama_section']) ?></td>
                                     <!-- Detail Kerusakan & Material -->
                                     <td>
                                         <div style="max-width: 260px; min-width: 180px; white-space: normal; font-size: 11px;">
                                             <div class="mb-1">
                                                 <strong class="text-primary"><i class="fas fa-circle-info mr-1"></i> Detail Kerusakan:</strong>
                                                 <div style="padding-left: 12px; color: #555;"><?= nl2br(esc($row['detail_temuan'])) ?></div>
                                             </div>
                                             <?php if (!empty($row['material'])): ?>
                                                 <div>
                                                     <strong class="text-warning-dark" style="color: #d97706;"><i class="fas fa-toolbox mr-1"></i> Material:</strong>
                                                     <div style="padding-left: 12px; color: #6b7280; font-family: monospace; font-size: 10px;"><?= nl2br(esc($row['material'])) ?></div>
                                                 </div>
                                             <?php endif; ?>
                                         </div>
                                     </td>
                                     <!-- Foto Temuan -->
                                     <td>
                                         <div class="d-flex flex-wrap align-items-center" style="gap: 4px; min-width: 120px;">
                                             <?php 
                                             $photos = json_decode($row['foto'], true) ?: [];
                                             $uploadPath = $row['foto_path'];
                                             if (empty($photos)): 
                                             ?>
                                                 <span class="text-muted small">Tidak ada foto</span>
                                             <?php else: ?>
                                                 <?php foreach ($photos as $photo): 
                                                     $filePath = base_url($uploadPath . $photo);
                                                 ?>
                                                     <img src="<?= $filePath ?>" 
                                                          style="width: 46px; height: 46px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; cursor: pointer; transition: transform 0.2s;"
                                                          onclick="openPhotoModal('<?= $filePath ?>')"
                                                          onmouseover="this.style.transform='scale(1.15)';"
                                                          onmouseout="this.style.transform='scale(1)';"
                                                          alt="Foto">
                                                 <?php endforeach; ?>
                                             <?php endif; ?>
                                         </div>
                                     </td>
                                     <td><?= esc($row['jenis_temuan']) ?></td>
                                     <td><?= esc($row['pelaksana']) ?></td>
                                     <td>
                                         <?php 
                                         $prio = strtoupper($row['prioritas']);
                                         if ($prio === 'EMERGENCY') {
                                             echo '<span class="badge bg-danger">' . $prio . '</span>';
                                         } elseif ($prio === 'HIGH') {
                                             echo '<span class="badge bg-warning text-dark">' . $prio . '</span>';
                                         } else {
                                             echo '<span class="badge bg-primary">' . $prio . '</span>';
                                         }
                                         ?>
                                     </td>
                                     <td><?= esc($row['potensi_gangguan']) ?></td>
                                     <td><?= date('d-m-Y', strtotime($row['tanggal_temuan'])) ?></td>
                                     <td>
                                         <?php 
                                         $sla = get_sla_status($row['prioritas'], $row['tanggal_temuan'], $row['status'], $row['tanggal_selesai']);
                                         echo $sla['badge_html'];
                                         ?>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                </div>
            </div>
        </div>
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
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('photo-lightbox').addEventListener('click', function(e) {
            if (e.target === this || e.target === document.getElementById('lb-img-container')) {
                closeLightbox();
            }
        });

        // Tombol ESC untuk tutup
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeLightbox();
        });

        $('#table-preview-report').DataTable({
            "responsive": true,
            "autoWidth": false,
            "language": {
                "url": "<?= base_url('plugins/datatables/id.json') ?>"
            }
        });
    });
</script>
<?= $this->endSection() ?>
