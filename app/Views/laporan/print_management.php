<!DOCTYPE html>
<html>
<head>
    <title>Laporan Management Trafo</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; color: #333; }
        .header { 
            position: relative; 
            margin-bottom: 20px; 
            border-bottom: 3px double #0057A0; 
            padding-bottom: 10px; 
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .header img { 
            width: 50px; 
            height: auto; 
            position: absolute; 
            left: 0; 
            top: 0;
        }
        .header-text { text-align: center; width: 100%; }
        h2 { margin: 0; font-size: 16px; font-weight: bold; color: #0057A0; }
        h3 { margin: 2px 0; font-size: 14px; font-weight: bold; }
        h4 { margin: 2px 0; font-size: 12px; font-weight: normal; color: #555; }
        
        .content-item { page-break-inside: avoid; margin-bottom: 30px; border: 1px solid #ccc; padding: 15px; border-radius: 6px; background-color: #fff; }
        .data-table { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .data-table td { padding: 6px; vertical-align: top; border-bottom: 1px solid #eee; font-size: 13px; }
        .label { font-weight: bold; width: 120px; }
        
        .photo-grid { display: flex; justify-content: center; gap: 20px; margin-top: 10px; }
        .photo-item { text-align: center; border: 1px solid #ddd; padding: 5px; width: 45%; border-radius: 4px; background: #fafafa; }
        .photo-item img { width: 100%; height: 220px; object-fit: contain; border: 1px solid #ccc; border-radius: 4px; background: #eee; }
        .photo-caption { font-size: 12px; margin-top: 5px; font-weight: bold; padding: 5px; color: white; display: block; border-radius: 3px; }
        .bg-old { background-color: #ffc107; color: black; }
        .bg-new { background-color: #28a745; }

        @media print {
            .no-print { display: none; }
            body { margin: 10mm; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <img src="https://upload.wikimedia.org/wikipedia/commons/9/97/Logo_PLN.png" alt="PLN Logo">
        <div class="header-text">
            <h2>PT PLN (PERSERO) UID JAWA TIMUR</h2>
            <h3>UP3 SIDOARJO - ULP SIDOARJO KOTA</h3>
            <h4>LAPORAN MANAGEMENT NAMEPLATE TRAFO</h4>
        </div>
    </div>

    <?php if (empty($dataList)): ?>
        <h3 style="text-align:center; padding: 50px; color: #888;">Tidak ada data ditemukan untuk filter ini.</h3>
    <?php else: ?>
        <?php foreach ($dataList as $row): ?>
            <div class="content-item">
                <table class="data-table">
                    <tr>
                        <td class="label">Nama Gardu</td><td>: <b><?= esc($row['nama_gardu']) ?></b></td>
                        <td class="label">Section</td><td>: <?= esc($row['nama_section'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Tanggal Input</td><td>: <?= date("d-m-Y", strtotime($row['tgl_input'])) ?></td>
                        <td class="label">Keterangan</td><td>: <?= nl2br(esc($row['keterangan'] ?: '-')) ?></td>
                    </tr>
                </table>

                <div class="photo-grid">
                    <div class="photo-item">
                        <span class="photo-caption bg-old">NAMEPLATE LAMA</span>
                        <?php if(!empty($row['foto_nameplate_lama'])) { ?>
                            <img src="<?= base_url('foto/management/' . $row['foto_nameplate_lama']) ?>">
                        <?php } else { echo "<br><br><span class='text-muted small'>Tidak ada foto</span>"; } ?>
                    </div>
                    <div class="photo-item">
                        <span class="photo-caption bg-new">NAMEPLATE BARU</span>
                        <?php if(!empty($row['foto_nameplate_baru'])) { ?>
                            <img src="<?= base_url('foto/management/' . $row['foto_nameplate_baru']) ?>">
                        <?php } else { echo "<br><br><span class='text-muted small'>Tidak ada foto</span>"; } ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>
