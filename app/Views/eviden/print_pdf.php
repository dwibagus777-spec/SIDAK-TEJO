<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Eviden Lapangan - <?= esc($kategori) ?></title>
    <style>
        @page {
            size: A4;
            margin: 15mm 15mm 20mm 15mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 0;
            background-color: #fff;
            line-height: 1.5;
            font-size: 13px;
        }
        .page {
            page-break-after: always;
            position: relative;
        }
        .page:last-child {
            page-break-after: avoid;
        }
        /* Header PLN Style */
        .header-container {
            display: table;
            width: 100%;
            border-bottom: 3px double #005eb8;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header-logo {
            display: table-cell;
            vertical-align: middle;
            width: 55px;
        }
        .header-logo img {
            width: 48px;
            height: auto;
        }
        .header-title-block {
            display: table-cell;
            vertical-align: middle;
            padding-left: 15px;
        }
        .header-title-block h1 {
            font-size: 18px;
            margin: 0;
            font-weight: 800;
            color: #005eb8;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .header-title-block p {
            font-size: 11px;
            margin: 3px 0 0 0;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
        }
        .header-meta {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 10px;
            color: #94a3b8;
        }
        
        /* Gardu Details Table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-table th, .info-table td {
            padding: 10px 12px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        .info-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            width: 20%;
        }
        .info-table td {
            color: #0f172a;
            width: 30%;
        }
        .badge-gardu {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            color: #0f172a;
            display: inline-block;
        }
        
        /* Section Title */
        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 6px;
            margin: 25px 0 12px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Photos Grid Layout */
        .photos-grid {
            width: 100%;
            margin-top: 10px;
        }
        
        /* Simple table-based grid for ultra-safe PDF print rendering alignment */
        .photos-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px;
            margin-left: -12px;
            margin-right: -12px;
        }
        .photo-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background-color: #f8fafc;
            padding: 8px;
            text-align: center;
            vertical-align: top;
            width: 50%;
            box-sizing: border-box;
        }
        .photo-wrapper {
            background-color: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            height: 220px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .photo-wrapper img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .photo-label {
            margin-top: 8px;
            font-size: 11px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .no-photo {
            padding: 30px;
            text-align: center;
            font-style: italic;
            color: #94a3b8;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            background-color: #f8fafc;
        }
    </style>
</head>
<body onload="window.print()">

    <?php foreach ($dataList as $index => $data): ?>
    <div class="page">
        <!-- Header -->
        <div class="header-container">
            <div class="header-logo">
                <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="PLN Logo">
            </div>
            <div class="header-title-block">
                <h1>Laporan Eviden Lapangan - <?= esc($kategori) ?></h1>
                <p>PT PLN (Persero) UID Jawa Timur • UP3 Sidoarjo • ULP Sidoarjo Kota</p>
            </div>
            <div class="header-meta">
                Dokumen Digital SIDAK TEJO<br>
                Tanggal Cetak: <?= date('d-m-Y') ?>
            </div>
        </div>
        
        <!-- Info Table -->
        <table class="info-table">
            <tr>
                <th>Penyulang</th>
                <td><b><?= esc($data['nama_penyulang'] ?: '-') ?></b></td>
                <th>Section</th>
                <td><?= esc($data['nama_section'] ?: '-') ?></td>
            </tr>
            <tr>
                <th>Nama Gardu</th>
                <td><span class="badge-gardu"><?= esc($data['nama_gardu'] ?: '-') ?></span></td>
                <th>Tanggal Input</th>
                <td><?= date('d-m-Y', strtotime($data['tgl_input'])) ?></td>
            </tr>
            <?php if ($kategori === 'KUBIKEL'): ?>
            <tr>
                <th>ID Pelanggan</th>
                <td><b><?= esc($data['id_pel'] ?: '-') ?></b></td>
                <th>Keterangan</th>
                <td><?= nl2br(esc($data['keterangan'] ?: '-')) ?></td>
            </tr>
            <?php else: ?>
            <tr>
                <th>Keterangan</th>
                <td colspan="3"><?= nl2br(esc($data['keterangan'] ?: '-')) ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <!-- Foto Eviden Section -->
        <div class="section-title">Dokumentasi Foto Lapangan</div>
        
        <div class="photos-grid">
            <?php if (empty($data['fotos'])): ?>
                <div class="no-photo">
                    <img src="<?= base_url('assets/img/logo_sidak.png') ?>" style="width: 40px; opacity: 0.3; filter: grayscale(1); margin-bottom: 8px;"><br>
                    Tidak ada berkas dokumentasi foto lapangan untuk gardu ini.
                </div>
            <?php else: ?>
                <table class="photos-table">
                    <?php 
                    // Render photos in rows of 2 for optimal print layouts
                    $fotosChunked = array_chunk($data['fotos'], 2);
                    foreach ($fotosChunked as $row):
                    ?>
                        <tr>
                            <?php foreach ($row as $foto): ?>
                                <td class="photo-card">
                                    <div class="photo-wrapper">
                                        <img src="<?= base_url('foto/' . $foto['nama_file']) ?>" alt="Foto Eviden">
                                    </div>
                                    <div class="photo-label"><?= esc($foto['jenis_foto']) ?></div>
                                </td>
                            <?php endforeach; ?>
                            
                            <?php 
                            // Fill empty cell if there is an odd number of photos in the row
                            if (count($row) < 2): 
                            ?>
                                <td style="width: 50%;"></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

</body>
</html>
