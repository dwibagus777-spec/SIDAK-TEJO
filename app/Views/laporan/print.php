<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cetak Laporan - SIDAK TEJO</title>
    <!-- Google Font: Inter -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <!-- Bootstrap 5 minimal CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <style>
        @page {
            size: landscape;
            margin: 12mm 10mm;
        }
        body {
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            background-color: #ffffff;
            font-size: 9.5px;
            line-height: 1.4;
        }
        
        /* Top Branding Header */
        .report-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 6px;
            margin-bottom: 18px;
        }
        .header-left {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-right {
            font-size: 10px;
            font-weight: 800;
            color: #0284c7;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }
        
        /* Main Title Header */
        .report-header-title {
            text-align: center;
            margin-bottom: 20px;
        }
        .report-header-title h2 {
            color: #0284c7;
            font-weight: 800;
            font-size: 20px;
            letter-spacing: 0.5px;
            margin: 0;
            text-transform: uppercase;
        }
        
        /* Table Styles */
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 25px;
            border: 1px solid #cbd5e1;
        }
        .table th {
            background-color: #0284c7 !important;
            color: #ffffff !important;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
            text-align: center;
            padding: 8px 6px !important;
            border: 1px solid #0284c7 !important;
            vertical-align: middle;
            letter-spacing: 0.3px;
        }
        .table td {
            padding: 7px 6px !important;
            border: 1px solid #e2e8f0 !important;
            vertical-align: middle;
            color: #334155;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f8fafc;
        }
        
        .no-print {
            background: #f1f5f9;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
        }
        
        @media print {
            body {
                padding: 0;
                color: #000;
            }
            .no-print {
                display: none !important;
            }
            .table th {
                background-color: #0284c7 !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <!-- Controls Menu (Hidden during Print) -->
    <div class="no-print mb-4 d-flex justify-content-between align-items-center">
        <span class="small text-muted"><i class="fas fa-info-circle"></i> Gunakan opsi printer Anda untuk mengatur margins atau menyimpan sebagai PDF.</span>
        <div>
            <button onclick="window.print()" class="btn btn-primary btn-sm px-3 fw-bold"><i class="fas fa-print"></i> Cetak Sekarang</button>
            <button onclick="window.close()" class="btn btn-secondary btn-sm ms-2">Tutup Halaman</button>
        </div>
    </div>

    <?php
    $titleUlp = "SEMUA ULP";
    if (!empty($data)) {
        if (!empty($filters['ulp_id'])) {
            $titleUlp = strtoupper($data[0]['nama_ulp']);
        }
    }
    ?>

    <!-- Branding Header -->
    <div class="report-header-top">
        <div class="header-left">PT PLN (Persero)</div>
        <div class="header-right">SISTEM MONITORING TEMUAN INSPEKSI</div>
    </div>

    <!-- Main Title -->
    <div class="report-header-title">
        <h2>INSPEKSI <?= $titleUlp ?></h2>
        <div style="font-size: 9.5px; color: #475569; font-weight: 500; margin-top: 4px;">
            Periode: <?= $filters['tanggal_awal'] ? date('d-m-Y', strtotime($filters['tanggal_awal'])) : 'Awal' ?> s.d 
            <?= $filters['tanggal_akhir'] ? date('d-m-Y', strtotime($filters['tanggal_akhir'])) : 'Hari Ini' ?>
            <?php if (!empty($filters['pelaksana'])): ?> | Pelaksana: <?= esc($filters['pelaksana']) ?><?php endif; ?>
            <?php if (!empty($filters['prioritas'])): ?> | Prioritas: <?= esc($filters['prioritas']) ?><?php endif; ?>
            <?php if (!empty($filters['status'])): ?> | Status: <?= esc($filters['status']) ?><?php endif; ?>
        </div>
        <div class="text-muted small" style="font-size: 8px; margin-top: 2px;">Dicetak pada: <?= date('d-m-Y H:i:s') ?></div>
    </div>

    <!-- Printable Report Table -->
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th style="width: 35px;">No</th>
                <th>Penyulang</th>
                <th>Section</th>
                <th style="width: 70px;">NOGA</th>
                <th style="width: 110px;">Koordinat</th>
                <th style="width: 80px;">Jenis Temuan</th>
                <th style="width: 80px;">Tanggal</th>
                <th>Keterangan</th>
                <th style="width: 120px;">Foto</th>
                <th style="width: 75px;">Status</th>
                <th style="width: 80px;">Tgl Selesai</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data)): ?>
                <tr>
                    <td colspan="11" class="text-center py-3 text-muted">Tidak ada data temuan inspeksi yang cocok dengan filter yang dipilih.</td>
                </tr>
            <?php else: ?>
                <?php $no = 1; foreach ($data as $row): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <!-- ULP & Penyulang Combined -->
                        <td>
                            <div style="font-size: 7.5px; color: #64748b; font-weight: 500;"><?= esc($row['nama_ulp']) ?></div>
                            <div class="fw-bold" style="color: #0f172a;"><?= esc($row['nama_penyulang']) ?></div>
                        </td>
                        <td><?= esc($row['nama_section']) ?></td>
                        <td class="text-center fw-semibold"><?= $row['noga'] ? esc($row['noga']) : '-' ?></td>
                        <!-- Coordinates -->
                        <td class="font-monospace" style="font-size: 8px;">
                            <?= $row['latitude'] !== null ? $row['latitude'] . ',<br>' . $row['longitude'] : '-' ?>
                        </td>
                        <td class="text-center"><?= esc($row['jenis_temuan']) ?></td>
                        <td class="text-center"><?= date('d-m-Y', strtotime($row['tanggal_temuan'])) ?></td>
                        <!-- Keterangan (Detail + Material) -->
                        <td>
                            <div style="line-height: 1.35; max-width: 250px; white-space: normal;">
                                <div class="fw-semibold text-dark"><?= esc($row['detail_temuan']) ?></div>
                                <?php if (!empty($row['material'])): ?>
                                    <div class="mt-1" style="font-size: 8px; color: #475569; background: #f1f5f9; padding: 2px 4px; border-radius: 3px; font-family: monospace; border: 1.5px dashed #cbd5e1; display: inline-block;">
                                        Material: <?= esc($row['material']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <!-- Image Thumbnails -->
                        <td>
                            <div style="display: flex; flex-wrap: wrap; gap: 3px; justify-content: center; align-items: center;">
                                <?php 
                                $photos = json_decode($row['foto'], true) ?: [];
                                $uploadPath = $row['foto_path'];
                                if (empty($photos)): 
                                ?>
                                    <span class="text-muted" style="font-size: 8px;">Tidak ada foto</span>
                                <?php else: ?>
                                    <?php foreach ($photos as $photo): 
                                        $filePath = base_url($uploadPath . $photo);
                                    ?>
                                        <img src="<?= $filePath ?>" style="width: 48px; height: 48px; object-fit: cover; border-radius: 3px; border: 1px solid #94a3b8;">
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-center fw-bold">
                            <?php 
                            $status = strtoupper($row['status']);
                            if ($status === 'SELESAI') {
                                echo '<span class="text-success">' . $status . '</span>';
                            } elseif ($status === 'BUTUH PADAM') {
                                echo '<span class="text-danger" style="color: #d946ef !important;">' . $status . '</span>';
                            } else {
                                echo '<span class="text-warning" style="color: #ea580c !important;">' . $status . '</span>';
                            }
                            ?>
                        </td>
                        <td class="text-center"><?= $row['tanggal_selesai'] ? date('d-m-Y', strtotime($row['tanggal_selesai'])) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Signature / Approval (Hidden or formatted cleanly at bottom) -->
    <div style="display: flex; justify-content: flex-end; margin-top: 30px;">
        <div style="text-align: center; width: 220px; font-size: 9.5px;">
            <div>Sidoarjo, <?= date('d F Y') ?></div>
            <div class="fw-bold mt-1" style="color: #0f172a;">Manager ULP / Pejabat Berwenang</div>
            <div style="height: 60px;"></div>
            <div style="border-bottom: 1.5px solid #000; display: inline-block; width: 100%;"></div>
            <div style="font-size: 8.5px; color: #475569; margin-top: 3px;">NIP. ..........................................</div>
        </div>
    </div>

    <!-- Auto Print Trigger -->
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>

