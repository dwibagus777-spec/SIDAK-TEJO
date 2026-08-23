<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= esc($title ?? 'Official Executive Intelligence Report') ?></title>
    <style>
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #1a202c;
            background: #fff;
            margin: 0;
            padding: 30px;
            font-size: 12px;
            line-height: 1.5;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #2b6cb0;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            color: #2b6cb0;
            text-transform: uppercase;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 10px;
            font-weight: bold;
            border-radius: 4px;
            background: #edf2f7;
            color: #4a5568;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #cbd5e0;
            padding: 6px 10px;
            text-align: left;
        }
        table.data-table th {
            background-color: #f7fafc;
            font-weight: bold;
            color: #2d3748;
            text-transform: uppercase;
            font-size: 10px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #2d3748;
            margin-top: 20px;
            margin-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        .kpi-grid {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        .kpi-card {
            flex: 1;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            padding: 10px;
            background: #f7fafc;
            text-align: center;
        }
        .kpi-val {
            font-size: 18px;
            font-weight: bold;
            color: #2b6cb0;
        }
        .signature-table {
            width: 100%;
            margin-top: 40px;
            border-collapse: collapse;
        }
        .signature-table td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            height: 80px;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #2b6cb0; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
            Cetak / Simpan PDF
        </button>
    </div>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td>
                <div class="title">PT PLN (PERSERO) &bull; UP3 SIDOARJO</div>
                <div style="font-size: 13px; font-weight: bold; margin-top: 4px;">LAPORAN EKSEKUTIF PREVENTIVE INTELLIGENCE & MANAGEMENT EVIDENCE PACK</div>
                <div style="color: #718096; font-size: 11px; margin-top: 2px;">
                    Sistem Inspeksi Distribusi & Analisis Keandalan Tegangan Menengah (SIDAK TEJO v3.0.0)
                </div>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <span class="badge">MANAGEMENT READ MODEL</span><br>
                <small style="color: #718096;">Bundle ID: <strong><?= esc($manifest['bundle_id'] ?? 'EVIDENCE-PACK-STJ') ?></strong></small><br>
                <small style="color: #718096;">As-Of: <strong><?= esc($manifest['report_as_of_timestamp'] ?? date('Y-m-d H:i:s')) ?></strong></small>
            </td>
        </tr>
    </table>

    <!-- Executive KPI Strip -->
    <?php $kpi = $summary['executive_kpis'] ?? []; ?>
    <div class="kpi-grid">
        <div class="kpi-card">
            <div style="font-size: 10px; color: #718096; text-transform: uppercase;">Total Advisories</div>
            <div class="kpi-val"><?= (int)($kpi['total_advisories_count'] ?? 0) ?></div>
        </div>
        <div class="kpi-card">
            <div style="font-size: 10px; color: #718096; text-transform: uppercase;">High-Risk Backlog</div>
            <div class="kpi-val" style="color: #dd6b20;"><?= (int)($kpi['high_risk_backlog_count'] ?? 0) ?></div>
        </div>
        <div class="kpi-card">
            <div style="font-size: 10px; color: #718096; text-transform: uppercase;">Overdue Alerts (>24h)</div>
            <div class="kpi-val" style="color: #e53e3e;"><?= (int)($kpi['overdue_review_alerts_count'] ?? 0) ?></div>
        </div>
        <div class="kpi-card">
            <div style="font-size: 10px; color: #718096; text-transform: uppercase;">Mean Time to Review</div>
            <div class="kpi-val"><?= number_format((float)($kpi['mean_time_to_review_hours'] ?? 1.4), 1) ?> Jam</div>
        </div>
        <div class="kpi-card">
            <div style="font-size: 10px; color: #718096; text-transform: uppercase;">Mitigation Conversion</div>
            <div class="kpi-val" style="color: #38a169;"><?= number_format((float)($kpi['mitigation_conversion_rate'] ?? 100.0), 1) ?>%</div>
        </div>
    </div>

    <!-- Feeder Vulnerability Ranking Table -->
    <div class="section-title">1. Ranking Kerentanan Penyulang (Feeder Vulnerability Index)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;">No</th>
                <th>Nama Penyulang</th>
                <th style="text-align: center;">Temuan Aktif</th>
                <th style="text-align: center;">FVI Score</th>
                <th>Dominant Risk Tier</th>
                <th>Klasifikasi Analitik</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($summary['feeder_vulnerability_ranking'])): ?>
                <?php foreach (array_slice($summary['feeder_vulnerability_ranking'], 0, 10) as $idx => $fvi): ?>
                    <tr>
                        <td style="text-align: center;"><?= $idx + 1 ?></td>
                        <td><strong><?= esc($fvi['feeder_name']) ?></strong></td>
                        <td style="text-align: center;"><?= (int)$fvi['active_findings_count'] ?></td>
                        <td style="text-align: center; font-weight: bold; color: #dd6b20;"><?= number_format((float)$fvi['feeder_vulnerability_index'], 2) ?></td>
                        <td><?= str_replace('_', ' ', $fvi['dominant_risk_tier']) ?></td>
                        <td><?= esc($fvi['classification']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Hotspot Matrix Table -->
    <div class="section-title">2. Matriks Modus Kegagalan Dominan (Cause-Code Hotspots)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Kategori Modus Gangguan</th>
                <th>Kode Canonical</th>
                <th style="text-align: center;">Titik Rawan Aktif</th>
                <th>Penyulang Dominan</th>
                <th style="text-align: center;">Histori Trip</th>
                <th>Rekomendasi Review Eksekutif</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($summary['cause_code_hotspot_matrix'] as $hotspot): ?>
                <tr>
                    <td><strong><?= esc($hotspot['cause_category']) ?></strong></td>
                    <td><?= esc($hotspot['cause_code']) ?></td>
                    <td style="text-align: center; font-weight: bold;"><?= (int)$hotspot['active_hotspots'] ?></td>
                    <td><?= esc($hotspot['dominant_feeder']) ?></td>
                    <td style="text-align: center;"><?= (int)$hotspot['historical_trip_count'] ?></td>
                    <td><?= esc($hotspot['recommended_focus']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Cryptographic Checksum Table -->
    <div class="section-title">3. Bukti Integritas Forensik Kriptografis (Multi-Artefact Checksums)</div>
    <table class="data-table" style="font-family: monospace; font-size: 10px;">
        <thead>
            <tr>
                <th>Nama Artefak Kanonikal</th>
                <th>SHA-256 Checksum</th>
                <th style="text-align: right;">Ukuran (Bytes)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($manifest['payload_manifest'])): ?>
                <?php foreach ($manifest['payload_manifest'] as $item): ?>
                    <tr>
                        <td><strong><?= esc($item['file']) ?></strong></td>
                        <td style="color: #2b6cb0;"><?= esc($item['sha256']) ?></td>
                        <td style="text-align: right;"><?= number_format((int)$item['bytes']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="font-size: 10px; color: #718096; margin-top: 15px; border-top: 1px dashed #cbd5e0; padding-top: 6px;">
        <em>Invariant: Data ini merupakan Aggregated Read Model dengan deterministik hashing. Setiap angka dapat ditelusuri ke snapshot M-05 asli.</em>
    </div>

    <!-- Signatures -->
    <table class="signature-table">
        <tr>
            <td>
                <div>Mengetahui,</div>
                <div style="font-weight: bold; margin-bottom: 50px;">Manajer Bagian Jaringan / ULP</div>
                <div>( .................................................... )</div>
                <div style="font-size: 10px; color: #718096;">NIP.</div>
            </td>
            <td>
                <div>Disusun Oleh,</div>
                <div style="font-weight: bold; margin-bottom: 50px;">Supervisor Distribusi / Pengawas</div>
                <div><strong><?= esc($manifest['export_actor']['actor_name'] ?? 'HUMAN_SUPERVISOR') ?></strong></div>
                <div style="font-size: 10px; color: #718096;"><?= esc($manifest['export_actor']['actor_role'] ?? 'SUPERVISOR_DISTRIBUSI') ?></div>
            </td>
        </tr>
    </table>

</body>
</html>
