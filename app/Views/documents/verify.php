<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Dokumen - SIDAK TEJO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; font-family: 'Outfit', sans-serif; }
        .verify-card { max-width: 560px; margin: 40px auto; }
        .stamp-valid { border: 4px solid #10b981; border-radius: 12px; padding: 6px 18px; color: #10b981; font-weight: 800; letter-spacing: 3px; text-transform: uppercase; transform: rotate(-6deg); display: inline-block; font-size: 22px; }
        .stamp-invalid { border: 4px solid #ef4444; border-radius: 12px; padding: 6px 18px; color: #ef4444; font-weight: 800; letter-spacing: 3px; text-transform: uppercase; transform: rotate(-6deg); display: inline-block; font-size: 22px; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="verify-card">
        <!-- Header Logo -->
        <div class="text-center mb-4">
            <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="SIDAK TEJO" style="max-height: 50px;" class="mb-2">
            <h4 class="fw-bold text-white">SIDAK TEJO - Verifikasi Dokumen Digital</h4>
            <p class="text-secondary small">Sistem Verifikasi Keabsahan Dokumen Resmi PLN UP3 Sidoarjo</p>
        </div>

        <?php if ($doc): ?>
            <!-- VALID DOCUMENT -->
            <div class="card border-0 shadow-lg rounded-4">
                <div class="card-body p-4 text-center">
                    <div class="mb-3">
                        <span class="stamp-valid"><i class="fas fa-shield-halved me-2"></i> DOKUMEN SAH</span>
                    </div>
                    <p class="text-success fw-bold small mb-4"><i class="fas fa-circle-check me-1"></i> Dokumen ini terdaftar dan terverifikasi secara resmi oleh sistem SIDAK TEJO.</p>

                    <table class="table table-borderless text-start mb-0" style="font-size: 13px;">
                        <tr><td class="text-secondary fw-bold" style="width: 150px;">Nomor Dokumen</td><td class="fw-bold font-monospace text-primary"><?= esc($doc['nomor_dokumen']) ?></td></tr>
                        <tr><td class="text-secondary fw-bold">Judul</td><td class="fw-bold text-dark"><?= esc($doc['judul_dokumen']) ?></td></tr>
                        <tr><td class="text-secondary fw-bold">Jenis Dokumen</td><td><?= esc($doc['jenis_dokumen']) ?></td></tr>
                        <tr>
                            <td class="text-secondary fw-bold">Status</td>
                            <td>
                                <?php if ($doc['status'] === 'APPROVED'): ?>
                                    <span class="badge bg-success"><i class="fas fa-circle-check me-1"></i> APPROVED</span>
                                <?php else: ?>
                                    <span class="badge bg-info"><?= esc($doc['status']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr><td class="text-secondary fw-bold">Tanggal Terbit</td><td><?= esc($doc['created_at']) ?></td></tr>
                        <tr><td class="text-secondary fw-bold">Diterbitkan Oleh</td><td><?= esc($doc['created_by']) ?></td></tr>
                        <tr><td class="text-secondary fw-bold">SHA256 Checksum</td><td><code class="text-muted" style="font-size: 10px; word-break: break-all;"><?= esc($doc['checksum']) ?></code></td></tr>
                    </table>

                    <!-- Signers List -->
                    <?php if (!empty($doc['signatures'])): ?>
                        <hr>
                        <h6 class="fw-bold text-dark mb-2"><i class="fas fa-signature text-success me-1"></i> Tanda Tangan Digital</h6>
                        <?php foreach ($doc['signatures'] as $sig): ?>
                            <div class="d-flex align-items-center gap-2 p-2 bg-light rounded-3 mb-1" style="font-size: 12px;">
                                <i class="fas fa-circle-check text-success"></i>
                                <span class="fw-bold text-dark"><?= esc($sig['signer_name']) ?></span>
                                <span class="text-muted">(<?= esc($sig['signer_role']) ?>)</span>
                                <small class="text-muted ms-auto"><?= esc($sig['signature_date']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- INVALID DOCUMENT -->
            <div class="card border-0 shadow-lg rounded-4">
                <div class="card-body p-4 text-center">
                    <div class="mb-3">
                        <span class="stamp-invalid"><i class="fas fa-triangle-exclamation me-2"></i> TIDAK VALID</span>
                    </div>
                    <p class="text-danger fw-bold small mb-2"><i class="fas fa-circle-xmark me-1"></i> Dokumen dengan checksum ini TIDAK TERDAFTAR dalam sistem SIDAK TEJO.</p>
                    <p class="text-muted small">Kemungkinan dokumen ini palsu, sudah kedaluwarsa, atau checksum telah berubah.</p>
                    <code class="text-muted d-block" style="font-size: 10px; word-break: break-all;"><?= esc($checksum) ?></code>
                </div>
            </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <small class="text-secondary">&copy; <?= date('Y') ?> SIDAK TEJO - PT PLN (Persero) UP3 Sidoarjo</small>
        </div>
    </div>
</div>
</body>
</html>
