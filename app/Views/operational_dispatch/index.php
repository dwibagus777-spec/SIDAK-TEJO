<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Operational Dispatch Workflow | SIDAK TEJO') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .badge-draft { background-color: #edf2f7; color: #4a5568; border: 1px solid #cbd5e0; }
        .badge-pending { background-color: #fffaf0; color: #dd6b20; border: 1px solid #fbd38d; }
        .badge-auth { background-color: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; }
        .badge-exec { background-color: #faf5ff; color: #805ad5; border: 1px solid #e9d8fd; }
        .badge-complete { background-color: #e6fffa; color: #319795; border: 1px solid #b2f5ea; }
        .badge-verified { background-color: #f0fff4; color: #38a169; border: 1px solid #9ae6b4; }
        .badge-cancel { background-color: #fff5f5; color: #e53e3e; border: 1px solid #feb2b2; }
        .table th { background-color: #f7fafc; font-size: 11px; text-transform: uppercase; color: #4a5568; }
        .table td { vertical-align: middle; font-size: 13px; }
        .governance-banner { background: #ebf8ff; border-left: 4px solid #3182ce; padding: 12px 16px; border-radius: 4px; }
        .stepper-box { display: flex; justify-content: space-between; position: relative; margin-bottom: 20px; }
        .step-item { text-align: center; flex: 1; position: relative; z-index: 1; }
        .step-circle { width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; color: #4a5568; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; margin-bottom: 4px; }
        .step-active .step-circle { background: #3182ce; color: #fff; }
        .step-label { font-size: 11px; font-weight: 600; color: #4a5568; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-truck-fast text-primary me-2"></i>Operational Dispatch & Action Governance</h4>
            <div class="text-muted small">CR-04 Phase 2: Governed State Machine, Evidence Lineage & Human Authority Final</div>
        </div>
        <div>
            <a href="<?= base_url('pattern-intelligence') ?>" class="btn btn-outline-secondary btn-sm me-2"><i class="fa-solid fa-chart-line me-1"></i> Pattern Intel</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal"><i class="fa-solid fa-plus me-1"></i> Inisiasi Draf Dispatch Baru</button>
        </div>
    </div>

    <!-- Governance Banner -->
    <div class="governance-banner mb-4">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-user-shield fa-2x text-primary me-3"></i>
            <div>
                <div class="fw-bold text-primary">🛡️ HUMAN MANAGEMENT AUTHORITY FINAL & ZERO AUTO-DISPATCH ACTIVE</div>
                <div class="small text-secondary">
                    Setiap pembuatan dan transisi surat perintah kerja wajib diinisiasi oleh manusia teridentifikasi (NIP, Nama, Peran).
                    Pola kegagalan historis (CR-03), gangguan (CR-01/02), dan radar preventif (M-04/M-05) hanya berfungsi sebagai <em>Decision-Support Evidence</em>.
                </div>
            </div>
        </div>
    </div>

    <!-- Lifecycle Stepper Visualizer -->
    <div class="card-custom p-3 mb-4">
        <div class="small text-muted text-uppercase fw-bold mb-3"><i class="fa-solid fa-diagram-project me-2"></i>Governed Dispatch State Machine</div>
        <div class="stepper-box">
            <div class="step-item step-active">
                <div class="step-circle">1</div>
                <div class="step-label">Draft Plan</div>
            </div>
            <div class="step-item">
                <div class="step-circle">2</div>
                <div class="step-label">Pending Approval</div>
            </div>
            <div class="step-item">
                <div class="step-circle">3</div>
                <div class="step-label">Authorized</div>
            </div>
            <div class="step-item">
                <div class="step-circle">4</div>
                <div class="step-label">In Execution</div>
            </div>
            <div class="step-item">
                <div class="step-circle">5</div>
                <div class="step-label">Completed</div>
            </div>
            <div class="step-item">
                <div class="step-circle">6</div>
                <div class="step-label">Verified</div>
            </div>
        </div>
    </div>

    <!-- Main Dispatch Queue Table -->
    <div class="card-custom mb-4">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-list-check me-2"></i>Governed Dispatch Queue & Registry</h6>
            <span class="badge bg-secondary">ZERO_AUTO_DISPATCH</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID Dispatch</th>
                        <th>Judul Perintah Kerja</th>
                        <th>Penyulang Target</th>
                        <th>Lingkup Pekerjaan</th>
                        <th>Status Siklus Hidup</th>
                        <th>Inisiator (NIP)</th>
                        <th>Confirmation Token</th>
                        <th class="text-center">Aksi Transisi</th>
                    </tr>
                </thead>
                <tbody id="dispatchTableBody">
                    <?php if (!empty($queueData['dispatches'])): ?>
                        <?php foreach ($queueData['dispatches'] as $d): ?>
                            <tr>
                                <td class="fw-bold font-monospace"><?= esc($d['dispatch_id']) ?></td>
                                <td><?= esc($d['title']) ?></td>
                                <td><span class="badge bg-light text-dark fw-bold"><?= esc($d['penyulang_name']) ?></span></td>
                                <td><small><?= esc($d['work_scope']) ?></small></td>
                                <td>
                                    <?php
                                    $st = $d['current_state'];
                                    $badgeClass = 'badge-draft';
                                    if ($st === 'PENDING_SUPERVISOR_APPROVAL') $badgeClass = 'badge-pending';
                                    elseif ($st === 'DISPATCH_AUTHORIZED') $badgeClass = 'badge-auth';
                                    elseif ($st === 'IN_FIELD_EXECUTION') $badgeClass = 'badge-exec';
                                    elseif ($st === 'FIELD_COMPLETED') $badgeClass = 'badge-complete';
                                    elseif ($st === 'SUPERVISOR_VERIFIED') $badgeClass = 'badge-verified';
                                    elseif ($st === 'CANCELLED') $badgeClass = 'badge-cancel';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= esc($st) ?></span>
                                </td>
                                <td>
                                    <small><?= esc($d['created_by']['actor_name'] ?? 'N/A') ?></small>
                                    <div class="text-muted" style="font-size: 10px;">NIP: <?= esc($d['created_by']['actor_nip'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($d['confirmation_token'])): ?>
                                        <span class="badge bg-success font-monospace" style="font-size: 10px;"><?= substr(esc($d['confirmation_token']), 0, 12) ?>...</span>
                                    <?php else: ?>
                                        <span class="text-muted small"><em>Belum Terbit</em></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-outline-primary btn-sm" onclick="openTransitionModal('<?= esc($d['dispatch_id']) ?>', '<?= esc($d['current_state']) ?>')">
                                        <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Transisi
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                                Belum ada berkas dispatch aktif. Silakan inisiasi draf perintah kerja baru.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Create Draft -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus text-primary me-2"></i>Inisiasi Draf Dispatch Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createDraftForm">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Penyulang Target (134 Master)</label>
                            <select id="draftFeederId" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Penyulang --</option>
                                <?php if (!empty($queueData['master_feeders'])): ?>
                                    <?php foreach ($queueData['master_feeders'] as $mf): ?>
                                        <option value="<?= (int)$mf['id'] ?>"><?= esc($mf['nama_penyulang']) ?> (ULP <?= (int)$mf['ulp_id'] ?>)</option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Lingkup Pekerjaan</label>
                            <select id="draftWorkScope" class="form-select form-select-sm">
                                <option value="INSPEKSI_DAN_PEMANGKASAN_ROW">Inspeksi & Pemangkasan ROW (Pohon)</option>
                                <option value="PENGGANTIAN_ISOLATOR_DEGRADASI">Penggantian Isolator Degradasi</option>
                                <option value="PEMASANGAN_GROUNDING_ARRESTER">Pemasangan / Audit Grounding & Arrester</option>
                                <option value="AUDIT_TITIK_SAMBUNG_THERMO">Audit Titik Sambung Thermovisi</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Judul Perintah Kerja</label>
                        <input type="text" id="draftTitle" class="form-control form-control-sm" placeholder="Contoh: Pemeliharaan ROW Terarah Penyulang Samaleak" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Justifikasi Berbasis Evidence</label>
                        <textarea id="draftJustification" class="form-control form-control-sm" rows="2" placeholder="Jelaskan alasan pemeliharaan berdasarkan data recurrence / temuan..."></textarea>
                    </div>
                    <div class="p-3 bg-light rounded mb-3">
                        <div class="small fw-bold text-secondary mb-2"><i class="fa-solid fa-id-card me-1"></i>Identitas Inisiator Manusia (Wajib)</div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <input type="text" id="actorName" class="form-control form-control-sm" value="BUDI SANTOSO" placeholder="Nama Inisiator" required>
                            </div>
                            <div class="col-md-4">
                                <input type="text" id="actorNip" class="form-control form-control-sm" value="198905142014021001" placeholder="NIP Inisiator" required>
                            </div>
                            <div class="col-md-4">
                                <input type="text" id="actorRole" class="form-control form-control-sm" value="PLANNER" readonly>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="submitCreateDraft()"><i class="fa-solid fa-check me-1"></i> Buat Draf Dispatch</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Transition -->
<div class="modal fade" id="transitionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-arrow-right-arrow-left text-primary me-2"></i>Eksekusi Transisi Siklus Hidup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">ID Dispatch</label>
                    <input type="text" id="transDispatchId" class="form-control form-control-sm font-monospace" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Status Saat Ini</label>
                    <input type="text" id="transCurrentState" class="form-control form-control-sm" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Target Status Transisi</label>
                    <select id="transTargetState" class="form-select form-select-sm" required>
                        <!-- Populated by JS -->
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Catatan Transisi / Alasan</label>
                    <input type="text" id="transNotes" class="form-control form-control-sm" placeholder="Catatan resolusi atau otorisasi...">
                </div>
                <div class="p-3 bg-light rounded mb-3">
                    <div class="small fw-bold text-secondary mb-2"><i class="fa-solid fa-user-shield me-1"></i>Otoritas Aktor Manusia</div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <input type="text" id="transActorName" class="form-control form-control-sm" value="AHMAD SUPERVISOR" required>
                        </div>
                        <div class="col-md-6">
                            <input type="text" id="transActorNip" class="form-control form-control-sm" value="198503122009011002" required>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="submitTransition()"><i class="fa-solid fa-paper-plane me-1"></i> Jalankan Transisi</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const allowedTransitions = {
    'DRAFT_DISPATCH_PLAN': ['PENDING_SUPERVISOR_APPROVAL', 'CANCELLED'],
    'PENDING_SUPERVISOR_APPROVAL': ['DISPATCH_AUTHORIZED', 'DRAFT_DISPATCH_PLAN', 'CANCELLED'],
    'DISPATCH_AUTHORIZED': ['IN_FIELD_EXECUTION', 'CANCELLED'],
    'IN_FIELD_EXECUTION': ['FIELD_COMPLETED'],
    'FIELD_COMPLETED': ['SUPERVISOR_VERIFIED', 'IN_FIELD_EXECUTION'],
    'SUPERVISOR_VERIFIED': [],
    'CANCELLED': []
};

function openTransitionModal(dispId, currState) {
    document.getElementById('transDispatchId').value = dispId;
    document.getElementById('transCurrentState').value = currState;
    const targetSelect = document.getElementById('transTargetState');
    targetSelect.innerHTML = '';

    const nextStates = allowedTransitions[currState] || [];
    if (nextStates.length === 0) {
        targetSelect.innerHTML = '<option value="">-- Tidak ada transisi lanjutan (Terminal State) --</option>';
    } else {
        nextStates.forEach(st => {
            const opt = document.createElement('option');
            opt.value = st;
            opt.textContent = st;
            targetSelect.appendChild(opt);
        });
    }

    new bootstrap.Modal(document.getElementById('transitionModal')).show();
}

async function submitCreateDraft() {
    const feederId = document.getElementById('draftFeederId').value;
    const title = document.getElementById('draftTitle').value;
    if (!feederId || !title) {
        alert('Lengkapi penyulang target dan judul perintah kerja.');
        return;
    }

    const payload = {
        penyulang_id: parseInt(feederId),
        title: title,
        work_scope: document.getElementById('draftWorkScope').value,
        justification: document.getElementById('draftJustification').value
    };

    const actor = {
        actor_name: document.getElementById('actorName').value,
        actor_nip: document.getElementById('actorNip').value,
        actor_role: document.getElementById('actorRole').value
    };

    try {
        const resp = await fetch('<?= base_url('api/operational-dispatch/create-draft') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ payload: payload, actor: actor })
        });
        const res = await resp.json();
        if (res.success) {
            location.reload();
        } else {
            alert('Gagal: ' + (res.error || 'Unknown'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function submitTransition() {
    const dispId = document.getElementById('transDispatchId').value;
    const targetState = document.getElementById('transTargetState').value;
    if (!targetState) {
        alert('Pilih target status transisi.');
        return;
    }

    const actor = {
        actor_name: document.getElementById('transActorName').value,
        actor_nip: document.getElementById('transActorNip').value,
        actor_role: 'SUPERVISOR'
    };

    try {
        const resp = await fetch('<?= base_url('api/operational-dispatch/transition') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                dispatch_id: dispId,
                target_state: targetState,
                notes: document.getElementById('transNotes').value,
                actor: actor
            })
        });
        const res = await resp.json();
        if (res.success) {
            location.reload();
        } else {
            alert('Gagal: ' + (res.error || 'Unknown'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}
</script>

</body>
</html>
