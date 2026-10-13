<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Buat Material Requisition Baru<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="container-xl">
    <div class="page-header d-print-none mb-3">
        <div class="row align-items-center">
            <div class="col">
                <a href="<?= site_url('remediation/material-requisitions') ?>" class="text-muted small text-decoration-none mb-1 d-inline-block">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar Requisition
                </a>
                <h2 class="page-title">
                    <i class="fas fa-plus-circle text-info me-2"></i>Buat Material Requisition Package
                </h2>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-edit me-2 text-info"></i>Form Requisition Package (Read-Only Preview)</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Phase 2A Mode Read-Only Preview:</strong> Form UI ini disediakan untuk peninjauan struktur UI. Mutasi data melalui UI diaktifkan pada Phase 2B setelah otorisasi.
            </div>

            <form action="#" method="post" onsubmit="return false;">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="work_package_id" class="form-label required">Parent Work Package</label>
                        <select class="form-select" id="work_package_id" name="work_package_id" disabled>
                            <option value="">-- Pilih Parent Work Package --</option>
                            <?php foreach ($work_packages as $wp): ?>
                                <option value="<?= $wp['id'] ?>">
                                    WP #<?= $wp['id'] ?> - <?= esc($wp['package_code']) ?> (<?= esc($wp['title']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="warehouse_id" class="form-label">Warehouse (Gudang ULP)</label>
                        <select class="form-select" id="warehouse_id" name="warehouse_id" disabled>
                            <option value="">-- Pilih Warehouse Authority --</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>">
                                    <?= esc($wh['warehouse_code']) ?> - <?= esc($wh['warehouse_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="card bg-light mb-3">
                    <div class="card-header py-2">
                        <h4 class="card-title mb-0 small text-muted"><i class="fas fa-list me-1"></i>Requested Items Preview</h4>
                    </div>
                    <div class="card-body py-2">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered bg-white mb-0">
                                <thead>
                                    <tr>
                                        <th>Material Master Authority</th>
                                        <th style="width: 150px;">Requested Qty</th>
                                        <th style="width: 120px;">Satuan</th>
                                        <th style="width: 200px;">Provenance (Max L4 AI)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <select class="form-select form-select-sm" id="mat_master_preview" name="mat_master_preview" disabled>
                                                <?php foreach (array_slice($master_materials, 0, 5) as $mm): ?>
                                                    <option value="<?= $mm['id'] ?>"><?= esc($mm['material_code'] ?? ('MAT-' . $mm['id'])) ?> - <?= esc($mm['nama_material']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" id="item_qty_preview" value="10" disabled />
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" id="item_unit_preview" value="PCS" disabled />
                                        </td>
                                        <td>
                                            <span class="badge bg-blue-lt"><i class="fas fa-robot me-1"></i> RECOMMENDED (L4)</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= site_url('remediation/material-requisitions') ?>" class="btn btn-secondary">Batal</a>
                    <button type="button" class="btn btn-primary" disabled>
                        <i class="fas fa-lock me-1"></i> Simpan (Terkunci Phase 2A)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
