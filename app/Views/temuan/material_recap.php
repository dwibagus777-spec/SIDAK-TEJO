<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Rekap Kebutuhan Material (MR-01)<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Rekap Kebutuhan Material Lapangan<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('temuan') ?>">Data Temuan</a></li>
<li class="breadcrumb-item active">Rekap Material</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid px-0">

    <!-- Header & Governance Notice -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="fas fa-boxes-stacked text-primary me-2"></i> Rekapitulasi Kebutuhan Material Standar (MR-01)
            </h4>
            <p class="text-muted small mb-0">
                Laporan agregasi deterministik kebutuhan fisik material berdasarkan transaksi sah <code>temuan_materials</code>.
            </p>
        </div>
        <div>
            <span class="badge bg-info text-dark p-2 border">
                <i class="fas fa-shield-halved me-1"></i> Read-Only Proof Gate
            </span>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form id="filter-recap-form" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">ULP</label>
                    <select id="filter_ulp_id" name="ulp_id" class="form-select form-select-sm select2">
                        <?php if (empty($isRestricted)): ?>
                            <option value="">-- Semua ULP --</option>
                        <?php endif; ?>
                        <?php foreach ($ulps as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= esc($u['nama_ulp']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Penyulang</label>
                    <select id="filter_penyulang_id" name="penyulang_id" class="form-select form-select-sm select2">
                        <option value="">-- Semua Penyulang --</option>
                        <?php foreach ($penyulangs as $p): ?>
                            <option value="<?= $p['id'] ?>" data-ulp="<?= $p['ulp_id'] ?>"><?= esc($p['nama_penyulang']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Dari Tanggal</label>
                    <input type="date" id="filter_start_date" name="start_date" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Sampai Tanggal</label>
                    <input type="date" id="filter_end_date" name="end_date" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="button" id="btn-apply-filter" class="btn btn-primary btn-sm w-100 fw-bold">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <button type="button" id="btn-reset-filter" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                        <i class="fas fa-rotate-left"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- KPI Metric Summary Cards -->
    <div class="row g-3 mb-4" id="kpi-container">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <small class="text-muted fw-bold text-uppercase">Total Baris Transaksi</small>
                <h3 class="fw-bold text-primary mb-0 mt-1" id="kpi-total-lines">-</h3>
                <small class="text-secondary mt-1">Item material teregister</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <small class="text-muted fw-bold text-uppercase">Jenis Material Kanonikal</small>
                <h3 class="fw-bold text-info mb-0 mt-1" id="kpi-total-types">-</h3>
                <small class="text-secondary mt-1">Varian material terdaftar</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <small class="text-muted fw-bold text-uppercase">Temuan & Aset Terkait</small>
                <div class="d-flex align-items-baseline gap-2 mt-1">
                    <h3 class="fw-bold text-success mb-0" id="kpi-total-findings">-</h3>
                    <span class="text-muted small">temuan /</span>
                    <h5 class="fw-bold text-secondary mb-0" id="kpi-total-assets">-</h5>
                    <span class="text-muted small">aset</span>
                </div>
                <small class="text-secondary mt-1">Cakupan fisik lapangan</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <small class="text-muted fw-bold text-uppercase">Total Kebutuhan per Satuan</small>
                <div id="kpi-totals-by-unit" class="d-flex flex-wrap gap-1 mt-2">
                    <span class="text-muted small">Memuat data...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Multi-Level Recap Tabs -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom p-3">
            <ul class="nav nav-pills card-header-pills" id="recap-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-bold small" id="tab-global-btn" data-bs-toggle="pill" data-bs-target="#tab-global" type="button">
                        <i class="fas fa-globe me-1"></i> Rekap Global Material
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold small" id="tab-ulp-btn" data-bs-toggle="pill" data-bs-target="#tab-ulp" type="button">
                        <i class="fas fa-building me-1"></i> Rekap per ULP
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold small" id="tab-penyulang-btn" data-bs-toggle="pill" data-bs-target="#tab-penyulang" type="button">
                        <i class="fas fa-bolt me-1"></i> Rekap per Penyulang
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold small" id="tab-section-btn" data-bs-toggle="pill" data-bs-target="#tab-section" type="button">
                        <i class="fas fa-network-wired me-1"></i> Rekap per Section
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold small" id="tab-detail-btn" data-bs-toggle="pill" data-bs-target="#tab-detail" type="button">
                        <i class="fas fa-list me-1"></i> Detail Transaksi Aset
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-3">
            <div class="tab-content" id="recap-tab-content">
                <!-- Tab 1: Global Recap -->
                <div class="tab-pane fade show active" id="tab-global" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode Standar</th>
                                    <th>Nama Material (Kanonikal Snapshot)</th>
                                    <th class="text-center">Satuan</th>
                                    <th class="text-center">Total Kebutuhan</th>
                                    <th class="text-center">Jumlah Temuan</th>
                                    <th class="text-center">Jumlah Aset</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-global">
                                <tr><td colspan="6" class="text-center text-muted py-4">Memuat data rekap global...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab 2: ULP Recap -->
                <div class="tab-pane fade" id="tab-ulp" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th>ULP</th>
                                    <th>Kode Standar</th>
                                    <th>Nama Material</th>
                                    <th class="text-center">Satuan</th>
                                    <th class="text-center">Kebutuhan ULP</th>
                                    <th class="text-center">Jumlah Temuan</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-ulp">
                                <tr><td colspan="6" class="text-center text-muted py-4">Memuat data rekap ULP...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab 3: Penyulang Recap -->
                <div class="tab-pane fade" id="tab-penyulang" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th>ULP</th>
                                    <th>Penyulang</th>
                                    <th>Kode Standar</th>
                                    <th>Nama Material</th>
                                    <th class="text-center">Satuan</th>
                                    <th class="text-center">Kebutuhan Penyulang</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-penyulang">
                                <tr><td colspan="6" class="text-center text-muted py-4">Memuat data rekap penyulang...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab 4: Section Recap -->
                <div class="tab-pane fade" id="tab-section" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th>Penyulang</th>
                                    <th>Section</th>
                                    <th>Kode Standar</th>
                                    <th>Nama Material</th>
                                    <th class="text-center">Satuan</th>
                                    <th class="text-center">Kebutuhan Ruas</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-section">
                                <tr><td colspan="6" class="text-center text-muted py-4">Memuat data rekap section...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab 5: Detail Rows -->
                <div class="tab-pane fade" id="tab-detail" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0" style="font-size: 12px;">
                            <thead class="table-light">
                                <tr>
                                    <th>Tgl Temuan</th>
                                    <th>No. Temuan</th>
                                    <th>Aset & Konstruksi</th>
                                    <th>Ruas / Penyulang</th>
                                    <th>Nama Material</th>
                                    <th class="text-center">Kuantitas</th>
                                    <th>Catatan Teknis</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-detail">
                                <tr><td colspan="7" class="text-center text-muted py-4">Memuat data detail transaksi...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mathematical Reconciliation Invariant Card -->
    <div class="card border-0 shadow-sm rounded-4 bg-light p-3" id="reconciliation-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold text-dark mb-0">
                <i class="fas fa-scale-balanced text-primary me-2"></i> Audit Rekonsiliasi Invariant Multi-Level
            </h6>
            <span class="badge bg-success" id="reconciliation-badge">STATUS: BALANCED</span>
        </div>
        <p class="text-muted small mb-2">
            Membuktikan bahwa kuantitas pada tingkat Detail &equiv; Section &equiv; Penyulang &equiv; ULP &equiv; Global tanpa ada kebocoran atau pelipatgandaan join.
        </p>
        <div id="reconciliation-details" class="d-flex flex-wrap gap-2">
            <!-- Dynamically populated -->
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadRecapData();

    $('#btn-apply-filter').on('click', function() {
        loadRecapData();
    });

    $('#btn-reset-filter').on('click', function() {
        $('#filter-recap-form')[0].reset();
        loadRecapData();
    });

    function loadRecapData() {
        const params = {
            ulp_id: $('#filter_ulp_id').val(),
            penyulang_id: $('#filter_penyulang_id').val(),
            start_date: $('#filter_start_date').val(),
            end_date: $('#filter_end_date').val()
        };

        $.ajax({
            url: "<?= site_url('temuan/ajax-material-recap') ?>",
            type: "GET",
            data: params,
            dataType: "json",
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(res) {
                if (res.status === 'SUCCESS') {
                    renderKPI(res.kpi);
                    renderGlobalRecap(res.global_recap);
                    renderUlpRecap(res.ulp_recap);
                    renderPenyulangRecap(res.penyulang_recap);
                    renderSectionRecap(res.section_recap);
                    renderDetailRows(res.detail_rows);
                    renderReconciliation(res.reconciliation);
                } else {
                    alert('Gagal memuat rekapitulasi: ' + res.message);
                }
            },
            error: function(xhr) {
                alert('Terjadi kesalahan jaringan (Status: ' + xhr.status + ')');
            }
        });
    }

    function renderKPI(kpi) {
        $('#kpi-total-lines').text(kpi.total_material_lines || 0);
        $('#kpi-total-types').text(kpi.total_material_types || 0);
        $('#kpi-total-findings').text(kpi.total_findings || 0);
        $('#kpi-total-assets').text(kpi.total_assets || 0);

        const $unitContainer = $('#kpi-totals-by-unit').empty();
        if (kpi.totals_by_unit && Object.keys(kpi.totals_by_unit).length > 0) {
            for (const [unit, qty] of Object.entries(kpi.totals_by_unit)) {
                $unitContainer.append(`
                    <span class="badge bg-secondary p-2 d-inline-flex align-items-center gap-1" style="font-size: 12px;">
                        <strong>${qty}</strong> <span>${unit}</span>
                    </span>
                `);
            }
        } else {
            $unitContainer.html('<span class="text-muted small">0 kebutuhan</span>');
        }
    }

    function renderGlobalRecap(rows) {
        const $tbody = $('#tbody-global').empty();
        if (!rows || rows.length === 0) {
            $tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">Belum ada transaksi material pada filter terpilih.</td></tr>');
            return;
        }

        rows.forEach(function(r) {
            const varianceBadge = r.has_unit_variance ? '<span class="badge bg-warning text-dark ms-1" title="Terdapat variasi satuan pada transaksi historis"><i class="fas fa-triangle-exclamation"></i> UNIT_VARIANCE</span>' : '';
            $tbody.append(`
                <tr>
                    <td><code>${r.canonical_code_snapshot}</code></td>
                    <td class="fw-bold text-dark">${r.canonical_name_snapshot} ${varianceBadge}</td>
                    <td class="text-center"><span class="badge bg-secondary">${r.unit_snapshot}</span></td>
                    <td class="text-center fw-bold text-primary" style="font-size: 14px;">${r.total_quantity}</td>
                    <td class="text-center">${r.finding_count}</td>
                    <td class="text-center">${r.asset_count}</td>
                </tr>
            `);
        });
    }

    function renderUlpRecap(rows) {
        const $tbody = $('#tbody-ulp').empty();
        if (!rows || rows.length === 0) {
            $tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">Belum ada data ULP.</td></tr>');
            return;
        }

        rows.forEach(function(r) {
            $tbody.append(`
                <tr>
                    <td class="fw-bold">${r.nama_ulp}</td>
                    <td><code>${r.canonical_code_snapshot}</code></td>
                    <td>${r.canonical_name_snapshot}</td>
                    <td class="text-center"><span class="badge bg-secondary">${r.unit_snapshot}</span></td>
                    <td class="text-center fw-bold text-primary">${r.total_quantity}</td>
                    <td class="text-center">${r.finding_count}</td>
                </tr>
            `);
        });
    }

    function renderPenyulangRecap(rows) {
        const $tbody = $('#tbody-penyulang').empty();
        if (!rows || rows.length === 0) {
            $tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">Belum ada data penyulang.</td></tr>');
            return;
        }

        rows.forEach(function(r) {
            $tbody.append(`
                <tr>
                    <td>${r.nama_ulp}</td>
                    <td class="fw-bold text-primary">${r.nama_penyulang}</td>
                    <td><code>${r.canonical_code_snapshot}</code></td>
                    <td>${r.canonical_name_snapshot}</td>
                    <td class="text-center"><span class="badge bg-secondary">${r.unit_snapshot}</span></td>
                    <td class="text-center fw-bold text-dark">${r.total_quantity}</td>
                </tr>
            `);
        });
    }

    function renderSectionRecap(rows) {
        const $tbody = $('#tbody-section').empty();
        if (!rows || rows.length === 0) {
            $tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">Belum ada data section.</td></tr>');
            return;
        }

        rows.forEach(function(r) {
            $tbody.append(`
                <tr>
                    <td>${r.nama_penyulang}</td>
                    <td class="fw-bold">${r.nama_section}</td>
                    <td><code>${r.canonical_code_snapshot}</code></td>
                    <td>${r.canonical_name_snapshot}</td>
                    <td class="text-center"><span class="badge bg-secondary">${r.unit_snapshot}</span></td>
                    <td class="text-center fw-bold text-dark">${r.total_quantity}</td>
                </tr>
            `);
        });
    }

    function renderDetailRows(rows) {
        const $tbody = $('#tbody-detail').empty();
        if (!rows || rows.length === 0) {
            $tbody.html('<tr><td colspan="7" class="text-center text-muted py-4">Belum ada transaksi material detail.</td></tr>');
            return;
        }

        rows.forEach(function(r) {
            $tbody.append(`
                <tr>
                    <td><code>${r.tanggal_temuan}</code></td>
                    <td class="fw-bold"><a href="<?= site_url('temuan/detail/') ?>${r.temuan_id}">${r.nomor_temuan || '#' + r.temuan_id}</a></td>
                    <td>
                        <div class="fw-bold">${r.nama_asset || 'Aset #' + r.asset_id}</div>
                        <small class="badge bg-light text-muted border">${r.construction_code || '-'}</small>
                    </td>
                    <td>
                        <div>${r.nama_section || '-'}</div>
                        <small class="text-muted">${r.nama_penyulang || '-'}</small>
                    </td>
                    <td>
                        <div class="fw-bold">${r.canonical_name_snapshot}</div>
                        <small class="text-muted"><code>${r.canonical_code_snapshot}</code></small>
                    </td>
                    <td class="text-center fw-bold text-primary">${r.quantity} <small class="text-muted">${r.unit_snapshot}</small></td>
                    <td class="text-muted">${r.justification_note || '-'}</td>
                </tr>
            `);
        });
    }

    function renderReconciliation(recon) {
        const $badge = $('#reconciliation-badge');
        const $container = $('#reconciliation-details').empty();

        if (recon.status === 'BALANCED') {
            $badge.attr('class', 'badge bg-success').text('STATUS: BALANCED (100% RECONCILED)');
        } else {
            $badge.attr('class', 'badge bg-danger').text('STATUS: DISCREPANCY DETECTED');
        }

        if (recon.checks) {
            for (const [unit, check] of Object.entries(recon.checks)) {
                $container.append(`
                    <span class="badge bg-white text-dark border p-2" style="font-size: 11px;">
                        <i class="fas fa-check text-success me-1"></i> Satuan <strong>${unit}</strong>:
                        Total = ${check.expected} | Detail = ${check.sum_detail} | Ruas = ${check.sum_section} | Penyulang = ${check.sum_penyulang} | ULP = ${check.sum_ulp} | Global = ${check.sum_global}
                    </span>
                `);
            }
        }
    }
});
</script>
<?= $this->endSection() ?>
