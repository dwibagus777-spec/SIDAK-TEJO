<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Master Asset PLN - SIDAK TEJO</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #333333;
            margin: 0;
            padding: 15px;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #005eb8;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header-title {
            font-size: 16px;
            font-weight: bold;
            color: #005eb8;
        }
        .header-subtitle {
            font-size: 11px;
            color: #666666;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .data-table th, .data-table td {
            border: 1px solid #cccccc;
            padding: 6px 8px;
            text-align: left;
        }
        .data-table th {
            background-color: #005eb8;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
        }
        .data-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            color: #ffffff;
        }
        .badge-normal { background-color: #28a745; }
        .badge-bermasalah { background-color: #ffc107; color: #000; }
        .badge-critical { background-color: #dc3545; }
        .footer {
            margin-top: 20px;
            font-size: 9px;
            color: #888888;
            text-align: right;
        }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

<div class="no-print" style="margin-bottom: 15px;">
    <button onclick="window.print()" style="padding: 8px 16px; background-color: #005eb8; color: #ffffff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
        🖨️ Cetak / Simpan PDF
    </button>
    <button onclick="window.close()" style="padding: 8px 16px; background-color: #6c757d; color: #ffffff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; margin-left: 8px;">
        ❌ Tutup
    </button>
</div>

<table class="header-table">
    <tr>
        <td>
            <div class="header-title">PT PLN (PERSERO) UP3 SIDOARJO</div>
            <div class="header-subtitle">Laporan Master Asset PLN Jaringan Distribusi 20KV - System SIDAK TEJO</div>
        </td>
        <td style="text-align: right;">
            <div style="font-size: 9px; color: #666;">Tanggal Cetak: <?= esc($printDate) ?></div>
            <div style="font-size: 9px; color: #666;">Total Asset: <?= count($assets) ?> Items</div>
        </td>
    </tr>
</table>

<table class="data-table">
    <thead>
        <tr>
            <th style="width: 25px;">No</th>
            <th>Kode Asset</th>
            <th>Nama Asset</th>
            <th>Jenis</th>
            <th>ULP</th>
            <th>Penyulang</th>
            <th>Section</th>
            <th>Merk / Tipe</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($assets)): ?>
            <tr>
                <td colspan="9" style="text-align: center; color: #999;">Belum ada data asset PLN.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($assets as $idx => $a): 
                $st = strtoupper($a['status'] ?? 'NORMAL');
                $badgeClass = 'badge-normal';
                if ($st === 'BERMASALAH') $badgeClass = 'badge-bermasalah';
                if ($st === 'CRITICAL') $badgeClass = 'badge-critical';
            ?>
                <tr>
                    <td style="text-align: center;"><?= $idx + 1 ?></td>
                    <td style="font-weight: bold; font-family: monospace;"><?= esc($a['kode_asset'] ?? '-') ?></td>
                    <td><?= esc($a['nama_asset'] ?? '-') ?></td>
                    <td><?= esc($a['jenis_asset'] ?? '-') ?></td>
                    <td><?= esc($a['nama_ulp'] ?? '-') ?></td>
                    <td><?= esc($a['nama_penyulang'] ?? '-') ?></td>
                    <td><?= esc($a['nama_section'] ?? '-') ?></td>
                    <td><?= esc(($a['merk'] ?? '-') . ' ' . ($a['type'] ?? '')) ?></td>
                    <td style="text-align: center;">
                        <span class="badge <?= $badgeClass ?>"><?= $st ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div class="footer">
    Dicetak secara otomatis melalui Sistem Database Terintegrasi Temuan Inspeksi (SIDAK TEJO) PLN
</div>

</body>
</html>
