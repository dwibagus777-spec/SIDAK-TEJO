<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Data Gangguan Penyulang - SIDAK TEJO<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Data Gangguan Penyulang<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid px-3 py-3">

    <!-- Flash Messages -->
    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm d-flex align-items-center mb-3" role="alert">
            <i class="fas fa-check-circle me-2 fs-5"></i>
            <div><?= session()->getFlashdata('success') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm d-flex align-items-center mb-3" role="alert">
            <i class="fas fa-triangle-exclamation me-2 fs-5"></i>
            <div><?= session()->getFlashdata('error') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('errors')): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-3" role="alert">
            <div class="fw-bold mb-1"><i class="fas fa-triangle-exclamation me-1"></i> Terjadi kesalahan validasi input:</div>
            <ul class="mb-0 ps-3">
                <?php foreach (session()->getFlashdata('errors') as $err): ?>
                    <li><?= esc($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Summary KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Total Kejadian Gangguan</div>
                        <h3 class="fw-bold mb-0 text-dark"><?= number_format($totalCount) ?> <span class="fs-6 fw-normal text-muted">kali</span></h3>
                    </div>
                    <div class="rounded-3 bg-primary-subtle text-primary p-3">
                        <i class="fas fa-bolt fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Trip DGR (Ground Fault)</div>
                        <h3 class="fw-bold mb-0 text-warning"><?= number_format($dgrCount) ?> <span class="fs-6 fw-normal text-muted">event</span></h3>
                    </div>
                    <div class="rounded-3 bg-warning-subtle text-warning p-3">
                        <i class="fas fa-arrow-down fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Trip OCR (Over Current)</div>
                        <h3 class="fw-bold mb-0 text-danger"><?= number_format($ocrCount) ?> <span class="fs-6 fw-normal text-muted">event</span></h3>
                    </div>
                    <div class="rounded-3 bg-danger-subtle text-danger p-3">
                        <i class="fas fa-gauge-high fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold">Total ENS (Energy Not Supplied)</div>
                        <h3 class="fw-bold mb-0 text-info"><?= number_format($totalKwh, 1) ?> <span class="fs-6 fw-normal text-muted">kWh</span></h3>
                    </div>
                    <div class="rounded-3 bg-info-subtle text-info p-3">
                        <i class="fas fa-plug-circle-bolt fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Action Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= site_url('gangguan') ?>" id="filterForm">
                <div class="row g-2 align-items-center">
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <label class="form-label small text-muted mb-1 fw-semibold">ULP</label>
                        <select name="ulp_id" id="filter_ulp" class="form-select form-select-sm rounded-3">
                            <option value="">Semua ULP</option>
                            <?php foreach ($ulps as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ((int)$ulpIdFilter === (int)$u['id']) ? 'selected' : '' ?>>
                                    <?= esc($u['nama_ulp']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <label class="form-label small text-muted mb-1 fw-semibold">Penyulang</label>
                        <select name="penyulang_id" id="filter_penyulang" class="form-select form-select-sm rounded-3">
                            <option value="">Semua Penyulang</option>
                            <?php foreach ($penyulangs as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ((int)$penyulangId === (int)$p['id']) ? 'selected' : '' ?>>
                                    <?= esc($p['nama_penyulang']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <label class="form-label small text-muted mb-1 fw-semibold">Tahun</label>
                        <select name="year" id="filter_year" class="form-select form-select-sm rounded-3">
                            <option value="">Semua Tahun</option>
                            <?php foreach ($availableYears as $yr): ?>
                                <option value="<?= $yr ?>" <?= ((int)$yearFilter === (int)$yr) ? 'selected' : '' ?>>
                                    Tahun <?= $yr ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <label class="form-label small text-muted mb-1 fw-semibold">Kategori</label>
                        <select name="category" id="filter_category" class="form-select form-select-sm rounded-3">
                            <option value="">Semua Kategori</option>
                            <option value="TEMPORARY" <?= ($categoryFilter === 'TEMPORARY') ? 'selected' : '' ?>>Temporary</option>
                            <option value="PERMANENT" <?= ($categoryFilter === 'PERMANENT') ? 'selected' : '' ?>>Permanent</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <label class="form-label small text-muted mb-1 fw-semibold">Cari</label>
                        <input type="text" name="search" class="form-control form-control-sm rounded-3" placeholder="Penyulang / narasi..." value="<?= esc($search) ?>">
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 d-flex align-items-end gap-1 pt-3">
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 flex-grow-1">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="<?= site_url('gangguan') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Reset Filter">
                            <i class="fas fa-rotate-left"></i>
                        </a>
                        <?php if ($canManage): ?>
                        <button type="button" class="btn btn-sm btn-success rounded-pill px-3 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#modalAddGangguan">
                            <i class="fas fa-plus me-1"></i> Input Gangguan
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fas fa-table-list text-primary me-2"></i>Histori &amp; Rekap Kejadian Gangguan Penyulang
            </h6>
            <span class="badge bg-light text-muted border font-monospace">Halaman <?= $page ?> dari <?= $totalPages ?> (Total <?= number_format($totalCount) ?> data)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                <thead class="table-light text-muted fw-semibold">
                    <tr>
                        <th class="ps-3" style="width: 50px;">No</th>
                        <th>Waktu Trip</th>
                        <th>Penyulang &amp; ULP</th>
                        <th>Alat Hubung / Recloser</th>
                        <th class="text-center">Relay &amp; Arus</th>
                        <th class="text-center">Kategori</th>
                        <th>Narasi Lapangan</th>
                        <th class="text-end">Durasi</th>
                        <th class="text-end">ENS (kWh)</th>
                        <?php if ($canManage): ?>
                        <th class="text-center pe-3" style="width: 80px;">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="<?= $canManage ? 10 : 9 ?>" class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fs-2 mb-2 d-block text-secondary opacity-50"></i>
                                Tidak ada data gangguan penyulang yang sesuai dengan filter.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $idx => $row): ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $offset + $idx + 1 ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= esc($row['event_date']) ?></div>
                                    <small class="text-muted font-monospace" style="font-size: 11px;">
                                        <?= esc(substr((string)$row['interruption_started_at'], 11, 5)) ?>
                                        <?= !empty($row['interruption_ended_at']) ? ' - ' . esc(substr((string)$row['interruption_ended_at'], 11, 5)) : '' ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= esc($row['feeder_name'] ?: ($row['master_penyulang'] ?? '-')) ?></div>
                                    <small class="text-muted"><?= esc($row['master_ulp'] ?: ('ULP ' . $row['ulp_name'])) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= esc($row['switching_device_type'] ?: 'PMCB') ?></span>
                                    <span class="ms-1 small"><?= esc($row['device_name'] ?: '-') ?></span>
                                </td>
                                <td class="text-center">
                                    <?php
                                        $relay = strtoupper((string)($row['relay_trip_type'] ?? '-'));
                                        $badgeCls = str_contains($relay, 'DGR') ? 'bg-warning-subtle text-warning border-warning' : (str_contains($relay, 'OCR') ? 'bg-danger-subtle text-danger border-danger' : 'bg-secondary-subtle text-secondary');
                                    ?>
                                    <span class="badge <?= $badgeCls ?> border px-2 py-1"><?= esc($relay) ?></span>
                                    <?php if (!empty($row['fault_current_amperes'])): ?>
                                        <div class="font-monospace text-muted" style="font-size: 11px;"><?= number_format($row['fault_current_amperes']) ?> A</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (strtoupper((string)$row['interruption_category']) === 'PERMANENT'): ?>
                                        <span class="badge bg-danger rounded-pill px-2">PERMANEN</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-dark border rounded-pill px-2">TEMPORER</span>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width: 250px;">
                                    <div class="text-truncate" title="<?= esc($row['field_narrative_raw']) ?>">
                                        <?= esc($row['field_narrative_raw'] ?: '-') ?>
                                    </div>
                                    <?php if (!empty($row['restoration_action_raw'])): ?>
                                        <small class="text-muted d-block text-truncate" title="<?= esc($row['restoration_action_raw']) ?>">
                                            <i class="fas fa-wrench me-1"></i><?= esc($row['restoration_action_raw']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end font-monospace">
                                    <?= !empty($row['outage_duration_minutes']) ? number_format($row['outage_duration_minutes'], 1) . ' m' : '-' ?>
                                </td>
                                <td class="text-end font-monospace fw-semibold text-info">
                                    <?= !empty($row['energy_not_supplied_kwh']) ? number_format($row['energy_not_supplied_kwh'], 1) : '-' ?>
                                </td>
                                <?php if ($canManage): ?>
                                <td class="text-center pe-3">
                                    <form method="POST" action="<?= site_url('gangguan/delete/' . $row['id']) ?>" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data gangguan ini?');" style="display: inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-xs btn-outline-danger rounded-circle p-1" title="Hapus Data" style="width: 28px; height: 28px;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-0 py-3 d-flex justify-content-between align-items-center">
            <small class="text-muted">Menampilkan <?= min($totalCount, $offset + 1) ?> - <?= min($totalCount, $offset + $perPage) ?> dari <?= number_format($totalCount) ?> kejadian</small>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link rounded-start-pill" href="<?= current_url() . '?' . http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Sebelumnya</a>
                </li>
                <?php
                    $startP = max(1, $page - 2);
                    $endP = min($totalPages, $page + 2);
                    for ($p = $startP; $p <= $endP; $p++):
                ?>
                    <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= current_url() . '?' . http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link rounded-end-pill" href="<?= current_url() . '?' . http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Selanjutnya</a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Modal: Input Gangguan Penyulang Baru -->
<?php if ($canManage): ?>
<div class="modal fade" id="modalAddGangguan" tabindex="-1" aria-labelledby="modalAddGangguanLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalAddGangguanLabel">
                    <i class="fas fa-bolt text-warning me-2"></i>Form Input Kejadian Gangguan Penyulang
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= site_url('gangguan/store') ?>" id="formInputGangguan">
                <?= csrf_field() ?>
                <div class="modal-body pt-3">
                    <p class="text-muted small mb-3">Catat kejadian trip/gangguan jaringan untuk analisis keandalan dan pemetaan relasi temuan.</p>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Tanggal Kejadian <span class="text-danger">*</span></label>
                            <input type="date" name="event_date" class="form-control rounded-3" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-dark">Waktu Trip Mulai <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="interruption_started_at" class="form-control rounded-3" value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-dark">Waktu Penormalan</label>
                            <input type="datetime-local" name="interruption_ended_at" class="form-control rounded-3">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">ULP <span class="text-danger">*</span></label>
                            <select name="ulp_id" id="modal_ulp_id" class="form-select rounded-3" required>
                                <option value="">-- Pilih ULP --</option>
                                <?php foreach ($ulps as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= esc($u['nama_ulp']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Penyulang <span class="text-danger">*</span></label>
                            <select name="penyulang_id" id="modal_penyulang_id" class="form-select rounded-3" required>
                                <option value="">-- Pilih ULP Terlebih Dahulu --</option>
                                <?php foreach ($penyulangs as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-ulp="<?= $p['ulp_id'] ?? '' ?>"><?= esc($p['nama_penyulang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">Jenis Alat Hubung</label>
                            <select name="switching_device_type" class="form-select rounded-3">
                                <option value="RECL-PMCB">RECL-PMCB (Recloser)</option>
                                <option value="PMT">PMT GI</option>
                                <option value="LBS">LBS Motorized</option>
                                <option value="FCO">Fuse Cut Out (FCO)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">Nama Peralatan / Lokasi</label>
                            <input type="text" name="device_name" class="form-control rounded-3" placeholder="Contoh: RECL GRIYA CANDI ASRI">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">Tipe Relay Trip</label>
                            <select name="relay_trip_type" class="form-select rounded-3">
                                <option value="DGR">DGR (Directional Ground Relay)</option>
                                <option value="OCR">OCR (Over Current Relay)</option>
                                <option value="OCR/DGR">OCR/DGR (Kombinasi)</option>
                                <option value="UFR">UFR (Under Frequency)</option>
                                <option value="LAINNYA">Lainnya / Belum Diketahui</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">Kategori Gangguan</label>
                            <select name="interruption_category" class="form-select rounded-3">
                                <option value="TEMPORARY">TEMPORARY (Trip-Reclose Berhasil)</option>
                                <option value="PERMANENT">PERMANENT (Lockout / Butuh Penelusuran)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">Arus Gangguan (Ampere)</label>
                            <input type="number" step="0.01" name="fault_current_amperes" class="form-control rounded-3" placeholder="Contoh: 153.0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-dark">Energi Padam (kWh)</label>
                            <input type="number" step="0.001" name="energy_not_supplied_kwh" class="form-control rounded-3" placeholder="Contoh: 300.34">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-dark">Narasi / Indikasi Penyebab di Lapangan</label>
                            <textarea name="field_narrative_raw" class="form-control rounded-3" rows="2" placeholder="Jelaskan temuan di lapangan saat penelusuran (misal: kabel SR nempel jamperan CO Ds. Gelam zona 2 section 2)..."></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Tindakan Penormalan</label>
                            <input type="text" name="restoration_action_raw" class="form-control rounded-3" placeholder="Contoh: Penelusuran ulang dan penggantian fuse...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Zona / Section</label>
                            <input type="text" name="extracted_zone_section" class="form-control rounded-3" placeholder="Contoh: ZONA 2 SECTION 2">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4">
                        <i class="fas fa-save me-1"></i> Simpan Data Gangguan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modalUlp = document.getElementById('modal_ulp_id');
    var modalPenyulang = document.getElementById('modal_penyulang_id');
    if (modalUlp && modalPenyulang) {
        var allOptions = Array.from(modalPenyulang.querySelectorAll('option[data-ulp]'));
        modalUlp.addEventListener('change', function() {
            var selectedUlp = this.value;
            modalPenyulang.value = '';
            allOptions.forEach(function(opt) {
                if (!selectedUlp || opt.getAttribute('data-ulp') === selectedUlp) {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                }
            });
        });
    }

    // Filter cascade on main page
    var filterUlp = document.getElementById('filter_ulp');
    var filterPenyulang = document.getElementById('filter_penyulang');
    if (filterUlp && filterPenyulang) {
        filterUlp.addEventListener('change', function() {
            var selectedUlp = this.value;
            if (!selectedUlp) {
                // reload or show all
                return;
            }
            fetch('<?= site_url('gangguan/api-feeders') ?>/' + selectedUlp)
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success' && res.data) {
                        filterPenyulang.innerHTML = '<option value="">Semua Penyulang</option>';
                        res.data.forEach(function(p) {
                            var opt = document.createElement('option');
                            opt.value = p.id;
                            opt.textContent = p.nama_penyulang;
                            filterPenyulang.appendChild(opt);
                        });
                    }
                })
                .catch(err => console.error(err));
        });
    }
});
</script>
<?php endif; ?>

<?= $this->endSection() ?>
