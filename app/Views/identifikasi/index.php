<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Identifikasi Gangguan Penyulang<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Identifikasi Gangguan Penyulang<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Identifikasi Gangguan</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <!-- Form Filter Analisis -->
    <div class="col-12">
        <div class="card card-modern" style="border-top: 3px solid #ffc107 !important;">
            <div class="card-header border-bottom bg-transparent py-3">
                <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-bolt-lightning text-warning mr-1"></i> Analisis Potensi Penyebab Gangguan Penyulang</h3>
            </div>
            <form action="<?= site_url('identifikasi/analisis') ?>" method="post">
                <?= csrf_field() ?>
                <div class="card-body">
                    <div class="row">
                        <!-- ULP Selection -->
                        <div class="col-md-4 form-group mb-3">
                            <label for="ulp_id" class="text-dark font-weight-bold small">UNIT ULP <span class="text-danger">*</span></label>
                            <?php if ($isRestricted): ?>
                                <select id="ulp_id" class="form-control select2" disabled>
                                    <option value="<?= $ulps[0]['id'] ?>"><?= esc($ulps[0]['nama_ulp']) ?></option>
                                </select>
                                <input type="hidden" name="ulp_id" id="hidden_ulp_id" value="<?= $ulps[0]['id'] ?>">
                            <?php else: ?>
                                <select name="ulp_id" id="ulp_id" class="form-control select2" required>
                                    <option value="">-- Pilih ULP --</option>
                                    <?php foreach ($ulps as $u): ?>
                                        <option value="<?= $u['id'] ?>" <?= (isset($selectedUlp) && $selectedUlp == $u['id']) ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <!-- Penyulang -->
                        <div class="col-md-4 form-group mb-3">
                            <label for="penyulang_id" class="text-dark font-weight-bold small">PILIH PENYULANG <span class="text-danger">*</span></label>
                            <select name="penyulang_id" id="penyulang_id" class="form-control select2" required <?= (!$isRestricted && !isset($selectedPenyulang)) ? 'disabled' : '' ?>>
                                <option value="">-- Pilih ULP Dahulu --</option>
                                <?php foreach ($penyulangs as $penyulang): ?>
                                    <option value="<?= $penyulang['id'] ?>" <?= isset($selectedPenyulang) && $selectedPenyulang == $penyulang['id'] ? 'selected' : '' ?>>
                                        <?= esc($penyulang['nama_penyulang']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Jenis Gangguan -->
                        <div class="col-md-4 form-group mb-3">
                            <label for="potensi_gangguan" class="text-dark font-weight-bold small">PILIH POTENSI GANGGUAN <span class="text-danger">*</span></label>
                            <select name="potensi_gangguan" id="potensi_gangguan" class="form-control select2" required>
                                <option value="">-- Pilih Gangguan --</option>
                                <option value="DGR" <?= isset($selectedGangguan) && $selectedGangguan === 'DGR' ? 'selected' : '' ?>>DGR (Directional Ground Relays)</option>
                                <option value="OCR" <?= isset($selectedGangguan) && $selectedGangguan === 'OCR' ? 'selected' : '' ?>>OCR (Over Current Relays)</option>
                                <option value="OCRDGR" <?= isset($selectedGangguan) && $selectedGangguan === 'OCRDGR' ? 'selected' : '' ?>>OCRDGR</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top">
                    <button type="submit" class="btn btn-warning text-dark font-weight-bold"><i class="fas fa-brain mr-1"></i> Mulai Analisis Gangguan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (isset($isAnalyzed) && $isAnalyzed === true): ?>
    <!-- Tombol Ekspor Hasil Analisis -->
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="text-dark font-weight-bold mb-0" style="font-family: 'Outfit', sans-serif;"><i class="fas fa-chart-line text-warning mr-1"></i> Hasil Analisis Gangguan</h4>
            <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                <!-- PDF -->
                <form action="<?= site_url('identifikasi/export-pdf') ?>" method="post" target="_blank" class="m-0 p-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="penyulang_id" value="<?= esc($selectedPenyulang) ?>">
                    <input type="hidden" name="potensi_gangguan" value="<?= esc($selectedGangguan) ?>">
                    <button type="submit" class="btn btn-sm btn-danger font-weight-bold">
                        <i class="fas fa-file-pdf mr-1"></i> Cetak PDF
                    </button>
                </form>
                <!-- Excel -->
                <form action="<?= site_url('identifikasi/export-excel') ?>" method="post" class="m-0 p-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="penyulang_id" value="<?= esc($selectedPenyulang) ?>">
                    <input type="hidden" name="potensi_gangguan" value="<?= esc($selectedGangguan) ?>">
                    <button type="submit" class="btn btn-sm btn-warning font-weight-bold text-dark">
                        <i class="fas fa-file-excel mr-1"></i> Ekspor Excel
                    </button>
                </form>
                <!-- CSV -->
                <form action="<?= site_url('identifikasi/export-csv') ?>" method="post" class="m-0 p-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="penyulang_id" value="<?= esc($selectedPenyulang) ?>">
                    <input type="hidden" name="potensi_gangguan" value="<?= esc($selectedGangguan) ?>">
                    <button type="submit" class="btn btn-sm btn-info font-weight-bold text-white">
                        <i class="fas fa-file-csv mr-1"></i> Ekspor CSV
                    </button>
                </form>

                <form action="<?= site_url('identifikasi/export-ppt') ?>" method="post" class="m-0 p-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="penyulang_id" value="<?= esc($selectedPenyulang) ?>">
                    <input type="hidden" name="potensi_gangguan" value="<?= esc($selectedGangguan) ?>">
                    <button type="submit" class="btn btn-sm btn-primary font-weight-bold">
                        <i class="fas fa-file-powerpoint mr-1"></i> Ekspor PPT
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Ringkasan & Peringkat Penyebab Gangguan -->
    <div class="row">
        <!-- Box Ringkasan Kiri -->
        <div class="col-lg-6 col-12">
            <div class="card card-modern bg-white">
                <div class="card-header border-bottom bg-transparent py-3">
                    <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-lightbulb text-warning mr-1"></i> Kemungkinan Penyebab Gangguan</h3>
                </div>
                <div class="card-body">
                    <h5 class="mb-4 text-dark font-weight-bold" style="line-height: 1.5;">
                        Terdapat <span class="badge bg-danger animate__animated animate__flash animate__infinite font-monospace" style="font-size: 1.1rem; padding: 5px 12px;"><?= count($temuanList) ?></span> temuan yang berpotensi menyebabkan gangguan <span class="text-primary font-weight-bold"><?= esc($selectedGangguan) ?></span> pada <span class="text-info"><?= esc($penyulangName) ?></span>.
                    </h5>

                    <h6 class="font-weight-bold mb-3 text-secondary" style="font-size: 0.85rem; letter-spacing: 0.5px; text-transform: uppercase;"><i class="fas fa-list-ol mr-1"></i> Peringkat Section Pemicu (Tertinggi ke Terendah):</h6>
                    
                    <?php if (empty($sectionRanking)): ?>
                        <p class="text-muted small"><i class="fas fa-circle-info"></i> Tidak ada data section pemicu.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php $no = 1; foreach ($sectionRanking as $rank): ?>
                                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center py-2 px-1 border-bottom">
                                    <span>
                                        <span class="badge bg-light text-dark border mr-2" style="width: 25px;"><?= $no++ ?></span> 
                                        <?= esc($rank['nama_section']) ?>
                                    </span>
                                    <span class="badge bg-warning text-dark font-weight-bold font-monospace"><?= esc($rank['total_temuan']) ?> Temuan</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Grafik Kanan -->
        <div class="col-lg-6 col-12">
            <div class="card card-modern" style="height: 100%;">
                <div class="card-header border-bottom bg-transparent py-3">
                    <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-chart-bar text-warning mr-1"></i> Grafik Sebaran Temuan per Section</h3>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div style="width: 100%; height: 300px;">
                        <canvas id="chartSectionGangguan" style="max-height: 280px; width:100%;"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Rincian Temuan Pemicu -->
    <div class="row">
        <div class="col-12">
            <div class="card card-modern">
                <div class="card-header border-bottom bg-transparent py-3">
                    <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-circle-exclamation text-danger mr-1"></i> Rincian Temuan Pemicu Gangguan</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-modern" id="table-rincian-gangguan" style="width:100%;">
                            <thead>
                                <tr>
                                    <th>Nomor Temuan</th>
                                    <th>Tanggal</th>
                                    <th>Section</th>
                                    <th>Jenis</th>
                                    <th>Pelaksana</th>
                                    <th>Prioritas</th>
                                    <th>Konduktor</th>
                                    <th>Material</th>
                                    <th>Detail Kerusakan</th>
                                    <th style="width: 60px;">Foto</th>
                                    <th>Alamat</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($temuanList as $row): ?>
                                    <tr>
                                        <td class="font-monospace font-weight-bold">
                                            <a href="<?= site_url('temuan/detail/' . $row['id']) ?>" target="_blank"><?= esc($row['nomor_temuan']) ?></a>
                                        </td>
                                        <td><?= date('d-m-Y', strtotime($row['tanggal_temuan'])) ?></td>
                                        <td><?= esc($row['nama_section']) ?></td>
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
                                        <td><?= esc($row['konduktor']) ?></td>
                                        <td><?= esc($row['material']) ?></td>
                                        <td><?= esc($row['detail_temuan']) ?></td>
                                        <td>
                                            <?php 
                                            $photos = json_decode($row['foto'], true) ?: [];
                                            if (!empty($photos)):
                                                $firstPhoto = base_url($row['foto_path'] . $photos[0]);
                                            ?>
                                                <img src="<?= $firstPhoto ?>" class="img-preview" style="height: 40px; width: 40px;" onclick="openPhotoModal('<?= $firstPhoto ?>')" alt="Thumbnail">
                                            <?php else: ?>
                                                <span class="text-muted small">No photo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= esc($row['alamat']) ?></td>
                                        <td>
                                            <?php if ($row['status'] === 'SELESAI'): ?>
                                                <span class="badge bg-success">SELESAI</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">BELUM</span>
                                            <?php endif; ?>
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
<?php endif; ?>

<!-- Modal Pratinjau Foto -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 card-modern">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title font-weight-bold text-dark" id="photoModalLabel"><i class="fas fa-image mr-1"></i> Preview Foto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-3">
                <img id="modal-img-preview" src="" style="max-width: 100%; max-height: 65vh; object-fit: contain; border-radius: 8px;">
            </div>
            <div class="modal-footer border-top d-flex justify-content-end">
                <a href="" id="btn-download-photo" download class="btn btn-sm btn-success font-weight-bold"><i class="fas fa-download mr-1"></i> Unduh Foto</a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function() {
        // Cascade ULP -> Penyulang
        $('#ulp_id').change(function() {
            const ulpId = $(this).val();
            const penyulangSelect = $('#penyulang_id');
            
            penyulangSelect.empty().append('<option value="">-- Pilih Penyulang --</option>').trigger('change');
            if (!ulpId) {
                penyulangSelect.prop('disabled', true);
                return;
            }
            
            $.ajax({
                url: '<?= site_url('temuan/ajax-penyulang/') ?>' + ulpId,
                type: 'GET',
                dataType: 'JSON',
                success: function(data) {
                    if (data.length > 0) {
                        $.each(data, function(index, item) {
                            penyulangSelect.append('<option value="' + item.id + '">' + item.nama_penyulang + '</option>');
                        });
                        penyulangSelect.prop('disabled', false);
                    } else {
                        penyulangSelect.empty().append('<option value="">Tidak ada penyulang di ULP ini</option>');
                        penyulangSelect.prop('disabled', true);
                    }
                    penyulangSelect.trigger('change');
                }
            });
        });

        if ($('#table-rincian-gangguan').length) {
            $('#table-rincian-gangguan').DataTable({
                "responsive": true,
                "autoWidth": false,
                "language": {
                    "url": "<?= base_url('plugins/datatables/id.json') ?>"
                }
            });
        }

        // Render Bar Chart (Chart.js)
        <?php if (isset($isAnalyzed) && $isAnalyzed === true): ?>
            const labels = <?= $chartLabels ?>;
            const values = <?= $chartValues ?>;
            
            Chart.defaults.color = '#495057';
            Chart.defaults.borderColor = '#e9ecef';

            new Chart(document.getElementById('chartSectionGangguan'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Jumlah Temuan Pemicu',
                        data: values,
                        backgroundColor: '#ffc107',
                        borderColor: '#e0a800',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    });

    function openPhotoModal(imgUrl) {
        $('#modal-img-preview').attr('src', imgUrl);
        $('#btn-download-photo').attr('href', imgUrl);
        const myModal = new bootstrap.Modal(document.getElementById('photoModal'));
        myModal.show();
    }
</script>
<?= $this->endSection() ?>
