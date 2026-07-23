<!DOCTYPE html>
<html>
<head>
    <title>Laporan Eviden Pemeliharaan - <?= esc($jenis) ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 20px; }
        .page-break { page-break-after: always; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #0057A0; padding-bottom: 10px; }
        .header h2 { color: #0057A0; margin: 0 0 5px 0; }
        .header h4 { margin: 0; color: #555; }
        .row { display: flex; flex-wrap: wrap; margin-right: -10px; margin-left: -10px; }
        .col-3 { flex: 0 0 25%; max-width: 25%; padding: 10px; box-sizing: border-box; }
        .img-container { border: 1px solid #ddd; border-radius: 6px; padding: 4px; background-color: #f9f9f9; text-align: center; height: 210px; display: flex; flex-direction: column; justify-content: space-between; align-items: center; }
        img { max-width: 100%; max-height: 160px; object-fit: contain; border-radius: 4px; }
        .label { font-weight: bold; display: block; margin-top: 5px; font-size: 11px; color: #333; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #fff; }
        th, td { border: 1px solid #aaa; padding: 8px; text-align: left; font-size: 13px; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body onload="window.print()">

    <?php foreach ($dataList as $index => $data): ?>
    <div class="item-container">
        <div class="header">
            <h2>LAPORAN EVIDEN PEMELIHARAAN <?= esc($jenis) ?></h2>
            <h4>PT PLN (PERSERO) SIDOARJO KOTA</h4>
        </div>
        
        <table>
            <tr>
                <th width="20%">Penyulang</th>
                <td width="30%"><b><?= esc($data['nama_penyulang'] ?: '-') ?></b></td>
                <th width="20%">Section</th>
                <td width="30%"><?= esc($data['nama_section'] ?: '-') ?></td>
            </tr>
            <tr>
                <th>Nama Gardu</th>
                <td><span style="background-color: #eee; padding: 2px 6px; border-radius: 3px;"><b><?= esc($data['nama_gardu'] ?: '-') ?></b></span></td>
                <th>Tanggal Input</th>
                <td><?= date('d-m-Y', strtotime($data['tgl_input'])) ?></td>
            </tr>
            <?php if ($jenis === 'KUBIKEL'): ?>
            <tr>
                <th>ID Pelanggan</th>
                <td><?= esc($data['id_pel'] ?: '-') ?></td>
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

        <!-- Photos Grid -->
        <h5 style="margin: 10px 0; border-bottom: 1px solid #ddd; padding-bottom: 5px;">FOTO EVIDEN LAPANGAN</h5>
        <div class="row">
            <?php if (empty($data['fotos'])): ?>
                <div style="padding: 20px; width: 100%; text-align: center; font-style: italic; color: #888;">Tidak ada foto eviden yang terunggah.</div>
            <?php else: ?>
                <?php foreach ($data['fotos'] as $foto): ?>
                    <div class="col-3">
                        <div class="img-container">
                            <img src="<?= base_url('foto/' . $foto['nama_file']) ?>" alt="Foto Eviden">
                            <span class="label"><?= esc($foto['jenis_foto']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($index < count($dataList) - 1): ?>
        <div class="page-break"></div>
    <?php endif; ?>
    <?php endforeach; ?>

</body>
</html>
