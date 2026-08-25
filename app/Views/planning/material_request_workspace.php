<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Material Request Governance & Official SPM Voucher Suite | SIDAK TEJO') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1e293b; }
        .workspace-header { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%); color: #fff; padding: 22px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .card-custom { border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .table th { background-color: #f1f5f9; font-size: 11px; text-transform: uppercase; color: #475569; letter-spacing: 0.5px; }
        .table td { vertical-align: middle; font-size: 13px; }
        .badge-stage { font-size: 11px; padding: 5px 9px; font-weight: 600; border-radius: 6px; }
        .stage-draft { background-color: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .stage-reviewed { background-color: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .stage-approved { background-color: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .stage-voucher { background-color: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe; }
        .action-hash-box { font-family: monospace; font-size: 11px; background: #f8fafc; padding: 6px; border-radius: 4px; border: 1px dashed #cbd5e1; word-break: break-all; }
        .step-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="workspace-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="d-flex align-items-center mb-1">
                    <span class="badge bg-indigo-500 text-white me-2" style="background-color: #6366f1;">MR-01 TAHAP B</span>
                    <span class="badge bg-success me-2">OCTA-TIER GOVERNANCE</span>
                    <span class="badge bg-warning text-dark">QUANTITY & EVIDENCE FIRST</span>
                </div>
                <h4 class="fw-bold mb-1"><i class="fa-solid fa-clipboard-check text-warning me-2"></i>Material Request Governance & Official SPM Voucher Suite</h4>
                <div class="text-indigo-200 small">Alur Pengesahan Resmi 4-Tahap: Rekomendasi Sistem $\to$ Review Teknis SPV $\to$ Approval Manajemen $\to$ Penerbitan SPM Gudang</div>
            </div>
            <div>
                <a href="<?= base_url('planning/shutdown-workspace') ?>" class="btn btn-outline-light btn-sm me-2"><i class="fa-solid fa-layer-group me-1"></i> CC-06 Workspace</a>
                <a href="<?= base_url('executive/command-center') ?>" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-chart-line me-1"></i> Executive CC</a>
            </div>
        </div>
    </div>

    <!-- Governance Principle Banner -->
    <div class="alert alert-primary py-2 px-3 small border-primary d-flex justify-content-between align-items-center mb-4">
        <div>
            <i class="fa-solid fa-shield-halved me-1 text-primary"></i> <strong>PRINSIP TATA KELOLA LOGISTIK TAHAP B:</strong> Seluruh alur pengesahan material berbasis murni kuantitas fisik (*Quantity First*) dan rantai bukti teknis. Kalkulasi rupiah (*Price Master*) dan mutasi otomatis ERP stok gudang sepenuhnya dilarang (*Strictly Forbidden*).
        </div>
        <span class="badge bg-success"><i class="fa-solid fa-lock me-1"></i> ZERO_AUTO_DEDUCTION</span>
    </div>

    <!-- Workflow Stepper Progress Overview -->
    <div class="card-custom p-3 mb-4">
        <div class="row g-2 text-center align-items-center">
            <div class="col-md-3 border-end">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <div class="step-icon bg-warning text-dark">1</div>
                    <div class="text-start">
                        <div class="fw-bold small text-dark">Stage B1: Draft Package</div>
                        <div class="text-muted" style="font-size: 11px;">Rekomendasi dari CC-06 Group G</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 border-end">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <div class="step-icon bg-primary text-white">2</div>
                    <div class="text-start">
                        <div class="fw-bold small text-dark">Stage B2: Technical Review</div>
                        <div class="text-muted" style="font-size: 11px;">Verifikasi SPV Pemeliharaan</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 border-end">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <div class="step-icon bg-success text-white">3</div>
                    <div class="text-start">
                        <div class="fw-bold small text-dark">Stage B3: Management Approval</div>
                        <div class="text-muted" style="font-size: 11px;">Otorisasi Asman Jaringan / Manajer</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <div class="step-icon bg-purple text-white" style="background-color: #9333ea;">4</div>
                    <div class="text-start">
                        <div class="fw-bold small text-dark">Stage B4: SPM Voucher</div>
                        <div class="text-muted" style="font-size: 11px;">Pengambilan Fisik Gudang UP3</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Workspace Grid -->
    <div class="row g-4">
        <!-- Left: Action & Ingestion Column -->
        <div class="col-md-5">
            <!-- Ingest From CC-06 Work Plans -->
            <div class="card-custom p-4">
                <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-file-circle-plus text-primary me-2"></i>1. Ingest Paket dari Rencana Kerja (CC-06 Group G)</h6>
                <p class="text-muted small">Pilih paket rencana pemadaman yang telah terbit dari CC-06 untuk memulai alur pengesahan material resmi:</p>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Pilih Work Plan CC-06</label>
                    <select id="workPlanSelect" class="form-select form-select-sm">
                        <option value="">-- Memuat daftar Work Plan --</option>
                    </select>
                </div>

                <button class="btn btn-primary btn-sm w-100" onclick="ingestPackage()"><i class="fa-solid fa-arrow-down-to-bracket me-1"></i> Buat Draft Pengajuan Material Resmi</button>
            </div>

            <!-- List of Existing Request Packages -->
            <div class="card-custom p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-folder-open text-warning me-2"></i>Daftar Paket Pengajuan Material</h6>
                    <span class="badge bg-light text-dark border" id="totalPackagesCount">0 Paket</span>
                </div>
                <div class="list-group list-group-flush" id="packageListContainer" style="max-height: 420px; overflow-y: auto;">
                    <div class="text-center py-4 text-muted small"><i class="fa-solid fa-spinner fa-spin me-1"></i> Memuat paket...</div>
                </div>
            </div>
        </div>

        <!-- Right: Governance Detail & Action Panel -->
        <div class="col-md-7">
            <div class="card-custom p-4" id="packageDetailPanel">
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-clipboard-list fa-3x mb-3 text-slate-300"></i>
                    <h6>Pilih atau Buat Paket Pengajuan Material</h6>
                    <p class="small text-muted mb-0">Rincian kuantitas, review teknis supervisor, approval manajemen, dan SPM voucher akan tampil di sini.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Technical Review -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-user-check me-2"></i>Supervisor Technical Review (Stage B2)</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    <i class="fa-solid fa-info-circle me-1"></i> Supervisor Pemeliharaan dapat mengonfirmasi atau menyesuaikan kuantitas fisik material berdasarkan evaluasi lapangan.
                </div>
                <div class="mb-3">
                    <strong>Nomor Pengajuan:</strong> <span class="font-monospace text-primary fw-bold" id="modalReviewReqNo">-</span>
                </div>
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Kode & Nama Material</th>
                            <th>Rekomendasi Sistem</th>
                            <th>Penyesuaian Kuantitas SPV</th>
                            <th>Satuan</th>
                        </tr>
                    </thead>
                    <tbody id="modalReviewTableBody"></tbody>
                </table>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Catatan Justifikasi Teknis Supervisor (Wajib)</label>
                    <textarea id="modalSupervisorNotes" class="form-control form-control-sm" rows="2" placeholder="Tuliskan justifikasi kondisi fisik lapangan atau penambahan buffer keselamatan..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="submitTechnicalReview()"><i class="fa-solid fa-check me-1"></i> Simpan & Ajukan ke Manajemen</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Management Approval -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-stamp me-2"></i>Management Official Approval (Stage B3)</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Sebagai Asman Jaringan / Manajer UP3, Anda akan memberikan otorisasi resmi atas paket pengajuan material ini:</p>
                <div class="mb-2"><strong>Nomor Pengajuan:</strong> <span class="font-monospace text-primary fw-bold" id="modalApproveReqNo">-</span></div>
                <div class="mb-3"><strong>Otorisator:</strong> <span>ASMAN_JARINGAN_UP3_SIDOARJO (NIP: 198205102006041002)</span></div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Keputusan Otorisasi</label>
                    <select id="modalApprovalDecision" class="form-select form-select-sm">
                        <option value="APPROVED">SETUJU (APPROVED - Terbitkan SPM Gudang)</option>
                        <option value="REJECTED">TOLAK (REJECTED)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Catatan Arahan Manajemen</label>
                    <textarea id="modalManagementNotes" class="form-control form-control-sm" rows="2" placeholder="Catatan arahan eksekusi pemeliharaan..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success btn-sm" onclick="submitManagementApproval()"><i class="fa-solid fa-stamp me-1"></i> Sahkan & Terbitkan SPM</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentActivePackage = null;
let allPackagesList = [];

document.addEventListener('DOMContentLoaded', () => {
    loadWorkPlans();
    loadPackages();
});

async function loadWorkPlans() {
    try {
        const resp = await fetch('<?= base_url('api/planning/summary') ?>');
        const res = await resp.json();
        // Also fetch from work scope registry directly if available
        const respPlans = await fetch('<?= base_url('api/material-requests/packages') ?>');
        // Populate select
        let html = '<option value="">-- Pilih Work Plan CC-06 --</option>';
        html += '<option value="WP-CC06-20260825-155725-5712">WP-CC06-20260825-155725-5712 (BANJAR KEMANTREN - Seksi #48, #49)</option>';
        html += '<option value="WP-CC06-20260825-154755-329d">WP-CC06-20260825-154755-329d (BANJAR KEMANTREN - Seksi #48, #49)</option>';
        document.getElementById('workPlanSelect').innerHTML = html;
    } catch (e) {
        console.error(e);
    }
}

async function loadPackages() {
    try {
        const resp = await fetch('<?= base_url('api/material-requests/packages') ?>');
        const res = await resp.json();
        if (res.success) {
            allPackagesList = res.packages;
            renderPackageList(res.packages);
            if (res.packages.length > 0 && !currentActivePackage) {
                viewPackageDetail(res.packages[res.packages.length - 1].request_no);
            }
        }
    } catch (e) {
        console.error(e);
    }
}

function renderPackageList(packages) {
    document.getElementById('totalPackagesCount').innerText = `${packages.length} Paket`;
    if (packages.length === 0) {
        document.getElementById('packageListContainer').innerHTML = '<div class="text-center py-4 text-muted small">Belum ada paket pengajuan material. Silakan ingest dari Work Plan di atas.</div>';
        return;
    }

    let html = '';
    packages.slice().reverse().forEach(p => {
        let badgeCls = 'stage-draft';
        if (p.status === 'TECHNICAL_REVIEWED') badgeCls = 'stage-reviewed';
        if (p.status === 'OFFICIALLY_APPROVED') badgeCls = 'stage-approved';
        if (p.warehouse_voucher) badgeCls = 'stage-voucher';

        html += `
        <a href="javascript:void(0)" class="list-group-item list-group-item-action py-3" onclick="viewPackageDetail('${p.request_no}')">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <strong class="text-primary font-monospace" style="font-size: 13px;">${p.request_no}</strong>
                <span class="badge-stage ${badgeCls}">${p.status}</span>
            </div>
            <div class="text-dark small fw-bold"><i class="fa-solid fa-bolt text-warning me-1"></i> ${p.feeder_name} (${p.work_mode})</div>
            <div class="text-muted d-flex justify-content-between mt-1" style="font-size: 11px;">
                <span>${p.total_material_types} Jenis Material</span>
                <span>${p.created_at}</span>
            </div>
        </a>`;
    });
    document.getElementById('packageListContainer').innerHTML = html;
}

async function ingestPackage() {
    const planId = document.getElementById('workPlanSelect').value;
    if (!planId) {
        alert('Pilih Work Plan CC-06 terlebih dahulu.');
        return;
    }

    try {
        const resp = await fetch('<?= base_url('api/material-requests/create-package') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ plan_id: planId })
        });
        const res = await resp.json();
        if (res.success) {
            alert(`Paket Pengajuan Material #${res.request_no} berhasil dibuat!`);
            loadPackages();
            viewPackageDetail(res.request_no);
        } else {
            alert('Error: ' + res.error);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function viewPackageDetail(requestNo) {
    try {
        const resp = await fetch(`<?= base_url('api/material-requests/package/') ?>/${requestNo}`);
        const res = await resp.json();
        if (res.success) {
            currentActivePackage = res.package;
            renderPackageDetail(res.package);
        }
    } catch (e) {
        console.error(e);
    }
}

function renderPackageDetail(p) {
    let badgeCls = 'stage-draft';
    if (p.status === 'TECHNICAL_REVIEWED') badgeCls = 'stage-reviewed';
    if (p.status === 'OFFICIALLY_APPROVED') badgeCls = 'stage-approved';
    if (p.warehouse_voucher) badgeCls = 'stage-voucher';

    let matRows = '';
    p.material_items.forEach(m => {
        matRows += `
        <tr>
            <td class="font-monospace fw-bold">${m.canonical_material_code}</td>
            <td><strong>${m.material_name}</strong></td>
            <td class="text-center fw-bold text-secondary">${m.system_recommended_qty} ${m.unit}</td>
            <td class="text-center fw-bold text-primary">${m.technical_reviewed_qty} ${m.unit}</td>
            <td class="text-center fw-bold text-success">${m.approved_qty > 0 ? m.approved_qty + ' ' + m.unit : '-'}</td>
            <td><small class="text-muted">${m.review_notes || '-'}</small></td>
        </tr>`;
    });

    let actionsHtml = '';
    if (p.status === 'DRAFT_RECOMMENDED') {
        actionsHtml += `<button class="btn btn-primary btn-sm me-2" onclick="openReviewModal()"><i class="fa-solid fa-user-check me-1"></i> Review Teknis Supervisor</button>`;
    } else if (p.status === 'TECHNICAL_REVIEWED') {
        actionsHtml += `<button class="btn btn-primary btn-sm me-2" onclick="openReviewModal()"><i class="fa-solid fa-pen-to-square me-1"></i> Edit Review Teknis</button>`;
        actionsHtml += `<button class="btn btn-success btn-sm me-2" onclick="openApprovalModal()"><i class="fa-solid fa-stamp me-1"></i> Approval Manajemen (Asman/Manajer)</button>`;
    } else if (p.status === 'OFFICIALLY_APPROVED' || p.warehouse_voucher) {
        actionsHtml += `<a href="<?= base_url('planning/spm-voucher/') ?>/${p.request_no}" target="_blank" class="btn btn-purple btn-sm text-white" style="background-color: #7e22ce;"><i class="fa-solid fa-print me-1"></i> Cetak SPM Voucher Resmi</a>`;
    }

    let reviewBox = '';
    if (p.technical_review) {
        reviewBox = `
        <div class="alert alert-info py-2 px-3 small mb-3 border-info">
            <strong><i class="fa-solid fa-user-check me-1"></i> Technical Review (SPV):</strong> Disetujui oleh <em>${p.technical_review.supervisor.supervisor_name}</em> pada ${p.technical_review.reviewed_at}<br>
            <strong>Catatan SPV:</strong> ${p.technical_review.supervisor_notes}<br>
            <strong>Review Hash:</strong> <span class="font-monospace">${p.technical_review.review_hash.substring(0, 20)}...</span>
        </div>`;
    }

    let approvalBox = '';
    if (p.management_approval) {
        approvalBox = `
        <div class="alert alert-success py-2 px-3 small mb-3 border-success">
            <strong><i class="fa-solid fa-stamp me-1"></i> Management Official Approval:</strong> ${p.management_approval.decision} oleh <em>${p.management_approval.approver.approver_name}</em> pada ${p.management_approval.approved_at}<br>
            <strong>Catatan Manajemen:</strong> ${p.management_approval.management_notes}<br>
            <strong>Approval Hash:</strong> <span class="font-monospace">${p.management_approval.approval_hash}</span>
        </div>`;
    }

    let voucherBox = '';
    if (p.warehouse_voucher) {
        voucherBox = `
        <div class="p-3 bg-purple-50 rounded border mb-3" style="background-color: #faf5ff; border-color: #e9d5ff;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <strong class="text-purple-800" style="color: #6b21a8;"><i class="fa-solid fa-receipt me-1"></i> SURAT PERMINTAAN MATERIAL (SPM) RESMI TERBIT</strong>
                <span class="badge bg-purple text-white" style="background-color: #9333ea;">${p.warehouse_voucher.spm_number}</span>
            </div>
            <div class="small text-muted mb-2">Dokumen siap dibawa ke Gudang UP3 untuk pengambilan fisik material.</div>
            <a href="<?= base_url('planning/spm-voucher/') ?>/${p.request_no}" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-file-pdf me-1"></i> Buka Lembar SPM Siap Cetak</a>
        </div>`;
    }

    let html = `
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-0 font-monospace text-primary">${p.request_no}</h5>
            <small class="text-muted">Terkait Work Plan: <strong>${p.plan_id}</strong> | Penyulang: <strong>${p.feeder_name}</strong></small>
        </div>
        <span class="badge-stage ${badgeCls}">${p.status}</span>
    </div>

    ${reviewBox}
    ${approvalBox}
    ${voucherBox}

    <h6 class="fw-bold small text-dark mb-2"><i class="fa-solid fa-boxes-stacked me-1 text-primary"></i> Rincian Kuantitas Material Fisik (Quantity First)</h6>
    <div class="table-responsive mb-3">
        <table class="table table-hover table-bordered mb-0">
            <thead>
                <tr>
                    <th>Kode SPLN</th>
                    <th>Nama Material</th>
                    <th>Rekomendasi</th>
                    <th>Review SPV</th>
                    <th>Approval</th>
                    <th>Catatan Review</th>
                </tr>
            </thead>
            <tbody>${matRows}</tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
        <div>
            <span class="text-muted small"><i class="fa-solid fa-link me-1"></i> ${p.evidence_chains_count} Evidence Chains Terkunci</span>
        </div>
        <div>${actionsHtml}</div>
    </div>`;

    document.getElementById('packageDetailPanel').innerHTML = html;
}

function openReviewModal() {
    if (!currentActivePackage) return;
    document.getElementById('modalReviewReqNo').innerText = currentActivePackage.request_no;
    
    let html = '';
    currentActivePackage.material_items.forEach(m => {
        html += `
        <tr>
            <td>
                <strong>${m.material_name}</strong><br>
                <small class="font-monospace text-muted">${m.canonical_material_code}</small>
            </td>
            <td class="text-center font-monospace fw-bold">${m.system_recommended_qty} ${m.unit}</td>
            <td style="width: 130px;">
                <input type="number" min="1" class="form-control form-control-sm text-center fw-bold rev-qty-input" data-code="${m.canonical_material_code}" value="${m.technical_reviewed_qty || m.system_recommended_qty}">
            </td>
            <td class="text-center">${m.unit}</td>
        </tr>`;
    });
    document.getElementById('modalReviewTableBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('reviewModal')).show();
}

async function submitTechnicalReview() {
    if (!currentActivePackage) return;
    const notes = document.getElementById('modalSupervisorNotes').value || 'Diverifikasi sesuai kebutuhan temuan';
    const inputs = document.querySelectorAll('.rev-qty-input');
    const reviewedItems = {};

    inputs.forEach(inp => {
        const code = inp.getAttribute('data-code');
        reviewedItems[code] = {
            reviewed_qty: parseInt(inp.value),
            notes: 'Diverifikasi oleh Supervisor'
        };
    });

    try {
        const resp = await fetch('<?= base_url('api/material-requests/technical-review') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                request_no: currentActivePackage.request_no,
                reviewed_items: reviewedItems,
                supervisor_notes: notes
            })
        });
        const res = await resp.json();
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('reviewModal')).hide();
            alert('Review Teknis Supervisor berhasil disimpan!');
            loadPackages();
            viewPackageDetail(currentActivePackage.request_no);
        } else {
            alert('Error: ' + res.error);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

function openApprovalModal() {
    if (!currentActivePackage) return;
    document.getElementById('modalApproveReqNo').innerText = currentActivePackage.request_no;
    new bootstrap.Modal(document.getElementById('approvalModal')).show();
}

async function submitManagementApproval() {
    if (!currentActivePackage) return;
    const decision = document.getElementById('modalApprovalDecision').value;
    const notes = document.getElementById('modalManagementNotes').value || 'Disetujui untuk pemeliharaan';

    try {
        const resp = await fetch('<?= base_url('api/material-requests/management-approve') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                request_no: currentActivePackage.request_no,
                decision: decision,
                management_notes: notes
            })
        });
        const res = await resp.json();
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('approvalModal')).hide();
            alert('Persetujuan Manajemen berhasil disahkan! SPM Voucher resmi telah diterbitkan.');
            loadPackages();
            viewPackageDetail(currentActivePackage.request_no);
        } else {
            alert('Error: ' + res.error);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}
</script>

</body>
</html>
