<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Surat Permintaan Material (SPM) | SIDAK TEJO') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Times New Roman', Times, serif; color: #000; }
        .document-page { max-width: 850px; margin: 20px auto; background: #fff; padding: 40px; border: 1px solid #cbd5e1; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .doc-header { border-bottom: 3px double #000; padding-bottom: 12px; margin-bottom: 20px; }
        .doc-title { font-family: 'Arial', sans-serif; font-size: 18px; font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 4px; }
        .doc-subtitle { font-family: 'Arial', sans-serif; font-size: 12px; text-align: center; color: #475569; margin-bottom: 15px; }
        .table-doc { width: 100%; border-collapse: collapse; font-family: 'Arial', sans-serif; font-size: 12px; margin-bottom: 20px; }
        .table-doc th, .table-doc td { border: 1px solid #000; padding: 6px 10px; }
        .table-doc th { background-color: #f1f5f9; text-align: center; font-weight: bold; }
        .signature-box { font-family: 'Arial', sans-serif; font-size: 12px; margin-top: 30px; }
        .hash-tag { font-family: 'Fira Code', monospace; font-size: 10px; background: #f8fafc; padding: 4px; border: 1px dashed #94a3b8; word-break: break-all; }
        @media print {
            body { background: #fff; padding: 0; }
            .document-page { border: none; box-shadow: none; margin: 0; padding: 20px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="py-3">

<div class="container text-center no-print mb-3">
    <button class="btn btn-primary btn-sm px-4 me-2" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Cetak Dokumen SPM</button>
    <a href="<?= base_url('planning/material-requests') ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Workspace</a>
</div>

<div class="document-page">
    <!-- Kop Surat -->
    <div class="doc-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0" style="font-family: Arial, sans-serif;">PT PLN (PERSERO) UID JAWA TIMUR</h5>
            <div style="font-size: 12px; font-family: Arial, sans-serif;">UP3 SIDOARJO — BAGIAN JARINGAN & PEMELIHARAAN</div>
            <div style="font-size: 11px; color: #475569; font-family: Arial, sans-serif;">Sistem Informasi & Keandalan Jaringan Distribusi (SIDAK TEJO v3.1.0)</div>
        </div>
        <div class="text-end">
            <span class="badge bg-dark text-white p-2" style="font-family: monospace; font-size: 12px;">FORMULIR RESMI: SPM-LOG-01</span>
        </div>
    </div>

    <!-- Title -->
    <div class="doc-title">SURAT PERMINTAAN MATERIAL (SPM) PEKERJAAN PREVENTIF</div>
    <div class="doc-subtitle">Nomor Voucher: <strong><?= esc($package['warehouse_voucher']['spm_number'] ?? 'SPM-JTM-PENDING') ?></strong> | Paket: <strong><?= esc($package['request_no']) ?></strong></div>

    <!-- Metadata Table -->
    <table class="table table-sm table-borderless mb-3" style="font-family: Arial, sans-serif; font-size: 12px;">
        <tr>
            <td style="width: 20%;"><strong>Penyulang / ULP</strong></td>
            <td style="width: 30%;">: <?= esc($package['feeder_name']) ?> (ID: <?= esc($package['feeder_id']) ?>)</td>
            <td style="width: 20%;"><strong>Tanggal Terbit</strong></td>
            <td style="width: 30%;">: <?= esc($package['warehouse_voucher']['issued_at'] ?? date('Y-m-d H:i:s')) ?></td>
        </tr>
        <tr>
            <td><strong>Work Plan ID</strong></td>
            <td>: <?= esc($package['plan_id']) ?></td>
            <td><strong>Gudang Tujuan</strong></td>
            <td>: GUDANG UP3 SIDOARJO</td>
        </tr>
        <tr>
            <td><strong>Mode Pekerjaan</strong></td>
            <td>: <span class="badge bg-secondary"><?= esc($package['work_mode']) ?></span></td>
            <td><strong>Status Dokumen</strong></td>
            <td>: <span class="badge bg-success">TERVALIDASI & DISETUJUI</span></td>
        </tr>
    </table>

    <!-- Material Line Items Table -->
    <div class="fw-bold mb-1" style="font-family: Arial, sans-serif; font-size: 12px;">I. DAFTAR MATERIAL YANG DISETUJUI (QUANTITY FIRST)</div>
    <table class="table-doc">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 25%;">Kode Material SPLN</th>
                <th style="width: 40%;">Nama / Deskripsi Material Standar</th>
                <th style="width: 15%;">Jumlah Disetujui</th>
                <th style="width: 15%;">Satuan</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach ($package['material_items'] as $m): 
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="font-monospace fw-bold"><?= esc($m['canonical_material_code']) ?></td>
                <td><strong><?= esc($m['material_name']) ?></strong></td>
                <td class="text-center fw-bold fs-6"><?= esc($m['approved_qty'] > 0 ? $m['approved_qty'] : $m['technical_reviewed_qty']) ?></td>
                <td class="text-center"><?= esc($m['unit']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Traceable Evidence Justification Table -->
    <div class="fw-bold mb-1" style="font-family: Arial, sans-serif; font-size: 12px;">II. RANTAI BUKTI ALOKASI ASET & TEMUAN (TRACEABLE EVIDENCE)</div>
    <table class="table-doc" style="font-size: 11px;">
        <thead>
            <tr>
                <th>No</th>
                <th>Kode Aset Fisik</th>
                <th>Seksi / Node SLD</th>
                <th>Dasar Temuan Lapangan</th>
                <th>Material Dialokasikan</th>
                <th>Evidence Hash</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $evNo = 1;
            foreach (array_slice($package['evidence_chains'] ?? [], 0, 10) as $ev): 
            ?>
            <tr>
                <td class="text-center"><?= $evNo++ ?></td>
                <td class="font-monospace fw-bold text-primary"><?= esc($ev['target_asset_code']) ?></td>
                <td>Seksi #<?= esc($ev['section_id']) ?> (<?= esc($ev['sld_position']) ?>)</td>
                <td><strong><?= esc($ev['source_finding_number']) ?></strong>: <?= esc(substr($ev['reason_justification'] ?? '', 0, 45)) ?>...</td>
                <td class="text-center fw-bold"><?= esc($ev['allocated_quantity']) ?> <?= esc($ev['unit']) ?> (<?= esc($ev['canonical_material_code']) ?>)</td>
                <td class="font-monospace" style="font-size: 9px;"><?= esc(substr($ev['evidence_hash'] ?? '', 0, 14)) ?>...</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (count($package['evidence_chains'] ?? []) > 10): ?>
    <div class="text-muted small text-end mb-3" style="font-family: Arial, sans-serif; font-size: 10px;">
        <em>* Menampilkan 10 dari total <?= count($package['evidence_chains']) ?> alokasi titik aset granular. Seluruh bukti lengkap terdaftar di registry digital SIDAK TEJO.</em>
    </div>
    <?php endif; ?>

    <!-- Cryptographic Proof Box -->
    <div class="p-2 bg-light rounded border mb-3" style="font-family: Arial, sans-serif; font-size: 11px;">
        <div><strong>Cryptographic Approval Hash:</strong></div>
        <div class="hash-tag"><?= esc($package['management_approval']['approval_hash'] ?? 'APPROVAL_HASH_RECORDED') ?></div>
        <div class="mt-1 text-muted" style="font-size: 10px;">
            <i class="fa-solid fa-lock me-1"></i> Tersegel secara digital dan terikat pada Work Plan ID <strong><?= esc($package['plan_id']) ?></strong>.
        </div>
    </div>

    <!-- Signature Matrix -->
    <div class="signature-box">
        <table class="table table-borderless text-center mb-0">
            <tr>
                <td style="width: 33%;">
                    <div>Diajukan oleh:</div>
                    <div class="small text-muted">Supervisor Pemeliharaan Jaringan</div>
                    <div style="height: 55px;"></div>
                    <div class="fw-bold text-decoration-underline"><?= esc($package['technical_review']['supervisor']['supervisor_name'] ?? 'SUPERVISOR PEMELIHARAAN') ?></div>
                    <div class="small">NIP: <?= esc($package['technical_review']['supervisor']['supervisor_nip'] ?? '198403152008121003') ?></div>
                </td>
                <td style="width: 33%;">
                    <div>Disetujui oleh:</div>
                    <div class="small text-muted">Asman Jaringan UP3 Sidoarjo</div>
                    <div style="height: 55px;"></div>
                    <div class="fw-bold text-decoration-underline"><?= esc($package['management_approval']['approver']['approver_name'] ?? 'ASMAN JARINGAN') ?></div>
                    <div class="small">NIP: <?= esc($package['management_approval']['approver']['approver_nip'] ?? '198205102006041002') ?></div>
                </td>
                <td style="width: 33%;">
                    <div>Diterima oleh Gudang:</div>
                    <div class="small text-muted">Petugas Logistik & Gudang UP3</div>
                    <div style="height: 55px;"></div>
                    <div class="fw-bold text-decoration-underline">( ............................................ )</div>
                    <div class="small">Tanggal: ............................</div>
                </td>
            </tr>
        </table>
    </div>

</div>

</body>
</html>
