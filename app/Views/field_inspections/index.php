<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Field Inspection Intelligence | SIDAK TEJO') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .table th { background-color: #f7fafc; font-size: 11px; text-transform: uppercase; color: #4a5568; }
        .table td { vertical-align: middle; font-size: 13px; }
        .governance-banner { background: #ebf8ff; border-left: 4px solid #3182ce; padding: 12px 16px; border-radius: 4px; }
        .badge-draft { background-color: #edf2f7; color: #4a5568; border: 1px solid #cbd5e0; }
        .badge-assigned { background-color: #fffaf0; color: #dd6b20; border: 1px solid #fbd38d; }
        .badge-field { background-color: #faf5ff; color: #805ad5; border: 1px solid #e9d8fd; }
        .badge-complete { background-color: #e6fffa; color: #319795; border: 1px solid #b2f5ea; }
        .badge-verified { background-color: #f0fff4; color: #38a169; border: 1px solid #9ae6b4; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-clipboard-check text-primary me-2"></i>Field Inspection & Living Asset Intelligence</h4>
            <div class="text-muted small">CR-06 Phase 2: Field Inspection Lifecycle, Living Asset Health & Material Traceability</div>
        </div>
        <div>
            <a href="<?= base_url('assets') ?>" class="btn btn-outline-secondary btn-sm me-2"><i class="fa-solid fa-network-wired me-1"></i> Asset Truth</a>
            <a href="<?= base_url('operational-dispatch') ?>" class="btn btn-outline-secondary btn-sm me-2"><i class="fa-solid fa-truck-fast me-1"></i> Dispatch</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newSessionModal"><i class="fa-solid fa-plus me-1"></i> Inisiasi Sesi Inspeksi Baru</button>
        </div>
    </div>

    <!-- Governance Banner -->
    <div class="governance-banner mb-4">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-clipboard-user fa-2x text-primary me-3"></i>
            <div>
                <div class="fw-bold text-primary">🛡️ FIELD INSPECTION LIVING TRACEABILITY ACTIVE (CR-06)</div>
                <div class="small text-secondary">
                    Pencatatan sesi inspeksi lapangan, kondisi visual komponen aset, dan pemakaian material disimpan pada <strong>Grup C Governed Registries</strong> dengan atribusi NIP dan bukti foto.
                    Kondisi aktual aset menyuplai dimensi <em>25% Asset Health</em> tanpa mengubah model skoring atau membongkar invarian Grup A.
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Total Sesi Inspeksi</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h3 class="mb-0 fw-bold text-primary" id="kpiSessions"><?= (int)($summary['total_sessions'] ?? 0) ?></h3>
                    <span class="ms-2 badge bg-primary">GROUP_C_REGISTRY</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">Siklus Hidup Teratribusi</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Observasi Lapangan Terdaftar</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h3 class="mb-0 fw-bold text-success" id="kpiObservations"><?= (int)($summary['total_observations'] ?? 0) ?></h3>
                    <span class="ms-2 badge bg-success">LIVING_EVIDENCE</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">Pemutakhiran Kondisi Fisik</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Aset Terdaftar di Database</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h3 class="mb-0 fw-bold text-dark"><?= (int)($summary['assets_in_database'] ?? 12) ?></h3>
                    <span class="ms-2 badge bg-info text-dark">POPULATED_TRUTH</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">Tabel <code>assets</code> Group B</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-custom p-3">
                <div class="text-muted small fw-bold text-uppercase">Temuan Lapangan Terhubung</div>
                <div class="d-flex align-items-baseline mt-2">
                    <h3 class="mb-0 fw-bold text-secondary"><?= (int)($summary['findings_preserved'] ?? 441) ?></h3>
                    <span class="ms-2 badge bg-secondary">PRESERVED</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 11px;">Tabel <code>temuan</code> Group A</div>
            </div>
        </div>
    </div>

    <!-- Main Workspace: Active Sessions & Inspection Logger -->
    <div class="card-custom mb-4">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-list-check me-2"></i>Daftar Sesi Inspeksi Lapangan</h6>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#materialModal">
                <i class="fa-solid fa-boxes-stacked me-1"></i> Catat Pemakaian Material
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID Sesi</th>
                        <th>Judul Inspeksi</th>
                        <th>Penyulang</th>
                        <th>Regu Pelaksana</th>
                        <th>Status Sesi</th>
                        <th>Inisiator (NIP)</th>
                        <th class="text-center">Aksi Operasional</th>
                    </tr>
                </thead>
                <tbody id="sessionsTableBody">
                    <?php if (!empty($summary['sessions'])): ?>
                        <?php foreach ($summary['sessions'] as $s): ?>
                            <tr>
                                <td class="font-monospace fw-bold"><?= esc($s['session_id']) ?></td>
                                <td><?= esc($s['title']) ?></td>
                                <td><span class="badge bg-light text-dark fw-bold"><?= esc($s['penyulang_name']) ?></span></td>
                                <td><small><?= esc($s['assigned_team']) ?></small></td>
                                <td>
                                    <?php
                                    $st = $s['current_state'];
                                    $bClass = 'badge-draft';
                                    if ($st === 'ASSIGNED') $bClass = 'badge-assigned';
                                    elseif ($st === 'IN_FIELD') $bClass = 'badge-field';
                                    elseif ($st === 'COMPLETED') $bClass = 'badge-complete';
                                    elseif ($st === 'VERIFIED') $bClass = 'badge-verified';
                                    ?>
                                    <span class="badge <?= $bClass ?>"><?= esc($st) ?></span>
                                </td>
                                <td>
                                    <small><?= esc($s['created_by']['actor_name'] ?? 'N/A') ?></small>
                                    <div class="text-muted" style="font-size: 10px;">NIP: <?= esc($s['created_by']['actor_nip'] ?? 'N/A') ?></div>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-outline-success btn-sm me-1" onclick="openObservationModal('<?= esc($s['session_id']) ?>', <?= (int)$s['penyulang_id'] ?>)">
                                        <i class="fa-solid fa-camera me-1"></i> Catat Observasi
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm" onclick="openTransitionModal('<?= esc($s['session_id']) ?>', '<?= esc($s['current_state']) ?>')">
                                        <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Transisi
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                                Belum ada sesi inspeksi lapangan. Silakan buat sesi baru untuk memulai observasi aset.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Inisiasi Sesi Baru -->
<div class="modal fade" id="newSessionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus text-primary me-2"></i>Inisiasi Sesi Inspeksi Lapangan Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newSessionForm">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Penyulang Target</label>
                            <select id="sessionFeederId" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Penyulang --</option>
                                <?php if (!empty($feeders)): ?>
                                    <?php foreach ($feeders as $f): ?>
                                        <option value="<?= (int)$f['id'] ?>"><?= esc($f['nama_penyulang']) ?> (ULP <?= (int)$f['ulp_id'] ?>)</option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Regu Pelaksana Lapangan</label>
                            <input type="text" id="sessionTeam" class="form-control form-control-sm" value="REGU HAR DISTRIBUSI TIM 1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Judul Sesi Inspeksi</label>
                        <input type="text" id="sessionTitle" class="form-control form-control-sm" placeholder="Contoh: Inspeksi Visual & Thermovisi Rutin Banjar Kemantren" required>
                    </div>
                    <div class="p-3 bg-light rounded mb-3">
                        <div class="small fw-bold text-secondary mb-2"><i class="fa-solid fa-id-card me-1"></i>Identitas Penanggung Jawab Sesi</div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <input type="text" id="sessionActorName" class="form-control form-control-sm" value="SUPERVISOR INSPEKSI HAR" required>
                            </div>
                            <div class="col-md-4">
                                <input type="text" id="sessionActorNip" class="form-control form-control-sm" value="198607122010011002" required>
                            </div>
                            <div class="col-md-4">
                                <input type="text" id="sessionActorRole" class="form-control form-control-sm" value="INSPECTION_SUPERVISOR" readonly>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="submitCreateSession()"><i class="fa-solid fa-check me-1"></i> Buat Sesi Inspeksi</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Catat Observasi -->
<div class="modal fade" id="observationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-camera text-success me-2"></i>Catat Hasil Observasi Lapangan Aset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">ID Sesi Inspeksi</label>
                    <input type="text" id="obsSessionId" class="form-control form-control-sm font-monospace" readonly>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Aset Fisik Target</label>
                        <select id="obsAssetId" class="form-select form-select-sm" required>
                            <option value="">-- Pilih Aset Terdaftar --</option>
                            <?php if (!empty($assets)): ?>
                                <?php foreach ($assets as $a): ?>
                                    <option value="<?= (int)$a['id'] ?>"><?= esc($a['kode_asset']) ?> - <?= esc($a['nama_asset']) ?> (Health: <?= esc($a['health_score']) ?>)</option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Kondisi Visual Hasil Inspeksi</label>
                        <select id="obsCondition" class="form-select form-select-sm">
                            <option value="NORMAL">NORMAL (Kondisi Baik - Skor Tetap)</option>
                            <option value="DEGRADASI_RINGAN">DEGRADASI_RINGAN (Ada korosi halus - Turun 5 poin)</option>
                            <option value="DEGRADASI_BERAT">DEGRADASI_BERAT (Isolator retak / tiang miring - Turun 15 poin)</option>
                            <option value="KRITIS">KRITIS (Bahaya darurat patah / tembus - Turun 30 poin)</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Catatan & Bukti Lapangan</label>
                    <textarea id="obsNotes" class="form-control form-control-sm" rows="2" placeholder="Catatan visual temuan di lapangan..."></textarea>
                </div>
                <div class="p-3 bg-light rounded mb-3">
                    <div class="small fw-bold text-secondary mb-2"><i class="fa-solid fa-user-shield me-1"></i>Identitas Petugas Inspektur Lapangan</div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <input type="text" id="obsActorName" class="form-control form-control-sm" value="PETUGAS INSPEKTUR TEKNIK" required>
                        </div>
                        <div class="col-md-6">
                            <input type="text" id="obsActorNip" class="form-control form-control-sm" value="199304192019021004" required>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success btn-sm" onclick="submitObservation()"><i class="fa-solid fa-save me-1"></i> Simpan Observasi & Mutakhirkan Health</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pemakaian Material -->
<div class="modal fade" id="materialModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Penelusuran Pemakaian Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Katalog Material</label>
                    <select id="matCode" class="form-select form-select-sm" required>
                        <?php if (!empty($summary['material_catalog'])): ?>
                            <?php foreach ($summary['material_catalog'] as $mc): ?>
                                <option value="<?= esc($mc['code']) ?>"><?= esc($mc['name']) ?> (<?= esc($mc['unit']) ?> - Stock: <?= esc($mc['stock']) ?>)</option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Jumlah Pemakaian</label>
                        <input type="number" id="matQty" class="form-control form-control-sm" value="2" min="1" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Nomor Perintah Kerja</label>
                        <input type="text" id="matWo" class="form-control form-control-sm" value="WO-HAR-2026-08-001" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="submitMaterialUsage()"><i class="fa-solid fa-check me-1"></i> Catat Pemakaian</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openObservationModal(sessionId, feederId) {
    document.getElementById('obsSessionId').value = sessionId;
    new bootstrap.Modal(document.getElementById('observationModal')).show();
}

async function submitCreateSession() {
    const feederId = document.getElementById('sessionFeederId').value;
    const title = document.getElementById('sessionTitle').value;
    if (!feederId || !title) {
        alert('Lengkapi penyulang target dan judul sesi.');
        return;
    }

    const payload = {
        penyulang_id: parseInt(feederId),
        title: title,
        assigned_team: document.getElementById('sessionTeam').value
    };

    const actor = {
        actor_name: document.getElementById('sessionActorName').value,
        actor_nip: document.getElementById('sessionActorNip').value,
        actor_role: document.getElementById('sessionActorRole').value
    };

    try {
        const resp = await fetch('<?= base_url('api/inspections/create-session') ?>', {
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

async function submitObservation() {
    const sessionId = document.getElementById('obsSessionId').value;
    const assetId   = document.getElementById('obsAssetId').value;
    if (!sessionId || !assetId) {
        alert('Pilih aset target.');
        return;
    }

    const obsData = {
        asset_id: parseInt(assetId),
        visual_condition: document.getElementById('obsCondition').value,
        notes: document.getElementById('obsNotes').value
    };

    const actor = {
        actor_name: document.getElementById('obsActorName').value,
        actor_nip: document.getElementById('obsActorNip').value,
        actor_role: 'FIELD_INSPECTOR'
    };

    try {
        const resp = await fetch('<?= base_url('api/inspections/record-observation') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: sessionId, observation: obsData, actor: actor })
        });
        const res = await resp.json();
        if (res.success) {
            alert(`Sukses! Kondisi aset diperbarui. Skor Kesehatan Baru: ${res.new_health_score} (${res.new_category})`);
            location.reload();
        } else {
            alert('Gagal: ' + (res.error || 'Unknown'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function submitMaterialUsage() {
    const usageData = {
        material_code: document.getElementById('matCode').value,
        quantity: parseInt(document.getElementById('matQty').value),
        work_order_ref: document.getElementById('matWo').value
    };

    const actor = {
        actor_name: 'PETUGAS LOGISTIK',
        actor_nip: '199011282015031001',
        actor_role: 'MATERIAL_OFFICER'
    };

    try {
        const resp = await fetch('<?= base_url('api/inspections/record-material') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ usage: usageData, actor: actor })
        });
        const res = await resp.json();
        if (res.success) {
            alert('Pemakaian material berhasil dicatat.');
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
