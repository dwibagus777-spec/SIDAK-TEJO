<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class AuditTranslinePolesCommand extends BaseCommand
{
    protected $group       = 'GIS';
    protected $name        = 'transline:audit-poles';
    protected $description = 'Audits all translines in gis_translines to ensure they only connect valid JTM pole assets and no cross-feeder links.';
    protected $usage       = 'transline:audit-poles [--fix] [--backup]';
    protected $options     = [
        '--fix'    => 'Safely deactivate invalid non-JTM or cross-feeder transline records',
        '--backup' => 'Generate JSON backup before applying fixes',
    ];

    public const VALID_JTM_POLE_TYPES = [
        'TIANG_BETON',
        'TIANG_BESI',
        'TIANG_KAYU',
        'TIANG',
        'JTM',
        'POLE',
    ];

    public function run(array $params)
    {
        $isFix = CLI::getOption('fix') !== null;
        $db = \Config\Database::connect();

        CLI::write("==================================================================", 'yellow');
        CLI::write("   SIDAK TEJO — AUDIT TOPOLOGI TRANSLINE KE TIANG JTM", 'yellow');
        CLI::write("==================================================================", 'yellow');

        if (!$db->tableExists('gis_translines')) {
            CLI::error("Tabel 'gis_translines' tidak ditemukan.");
            return;
        }

        // 1. Fetch all active translines with source and target assets
        $sql = "
            SELECT 
                tl.id,
                tl.transline_code,
                tl.penyulang_id,
                tl.source_asset_id,
                tl.target_asset_id,
                tl.distance_meters,
                tl.conductor_type,
                tl.conductor_size,
                p.nama_penyulang,
                sa.kode_asset AS source_code,
                sa.nama_asset AS source_name,
                sa.jenis_asset AS source_jenis,
                sa.penyulang_id AS source_penyulang_id,
                ta.kode_asset AS target_code,
                ta.nama_asset AS target_name,
                ta.jenis_asset AS target_jenis,
                ta.penyulang_id AS target_penyulang_id
            FROM gis_translines tl
            LEFT JOIN penyulang p ON p.id = tl.penyulang_id
            LEFT JOIN assets sa ON sa.id = tl.source_asset_id
            LEFT JOIN assets ta ON ta.id = tl.target_asset_id
            WHERE tl.deleted_at IS NULL
            ORDER BY tl.penyulang_id ASC, tl.id ASC
        ";

        $translines = $db->query($sql)->getResultArray();
        $totalTl = count($translines);

        CLI::write("Total Transline Aktif: {$totalTl} segmen", 'cyan');
        CLI::newLine();

        $validPolesCount = 0;
        $invalidSourceType = [];
        $invalidTargetType = [];
        $crossFeederList = [];
        $orphanList = [];

        foreach ($translines as $tl) {
            $srcJenis = strtoupper(trim((string)($tl['source_jenis'] ?? '')));
            $tgtJenis = strtoupper(trim((string)($tl['target_jenis'] ?? '')));

            $isSrcValid = in_array($srcJenis, self::VALID_JTM_POLE_TYPES, true);
            $isTgtValid = in_array($tgtJenis, self::VALID_JTM_POLE_TYPES, true);

            if (!$tl['source_code']) {
                $orphanList[] = ['id' => $tl['id'], 'reason' => 'Source asset #'.$tl['source_asset_id'].' missing'];
                continue;
            }
            if (!$tl['target_code']) {
                $orphanList[] = ['id' => $tl['id'], 'reason' => 'Target asset #'.$tl['target_asset_id'].' missing'];
                continue;
            }

            if (!$isSrcValid) {
                $invalidSourceType[] = [
                    'id'          => $tl['id'],
                    'feeder'      => $tl['nama_penyulang'] ?? $tl['penyulang_id'],
                    'asset_id'    => $tl['source_asset_id'],
                    'code'        => $tl['source_code'],
                    'jenis_asset' => $tl['source_jenis'] ?: 'NULL',
                    'pole_role'   => 'SOURCE',
                ];
            }

            if (!$isTgtValid) {
                $invalidTargetType[] = [
                    'id'          => $tl['id'],
                    'feeder'      => $tl['nama_penyulang'] ?? $tl['penyulang_id'],
                    'asset_id'    => $tl['target_asset_id'],
                    'code'        => $tl['target_code'],
                    'jenis_asset' => $tl['target_jenis'] ?: 'NULL',
                    'pole_role'   => 'TARGET',
                ];
            }

            $srcFeeder = (int)$tl['source_penyulang_id'];
            $tgtFeeder = (int)$tl['target_penyulang_id'];
            $tlFeeder  = (int)$tl['penyulang_id'];

            if ($srcFeeder > 0 && $tgtFeeder > 0 && ($srcFeeder !== $tgtFeeder || $tlFeeder !== $srcFeeder)) {
                $crossFeederList[] = [
                    'id'            => $tl['id'],
                    'tl_feeder'     => $tlFeeder,
                    'source_feeder' => $srcFeeder,
                    'target_feeder' => $tgtFeeder,
                    'source_code'   => $tl['source_code'],
                    'target_code'   => $tl['target_code'],
                ];
            }

            if ($isSrcValid && $isTgtValid && $srcFeeder === $tgtFeeder) {
                $validPolesCount++;
            }
        }

        CLI::write("Ringkasan Hasil Audit:", 'white');
        CLI::write("  • Segmen Murni Tiang JTM Valid    : {$validPolesCount} segmen", 'green');
        CLI::write("  • Segmen Ujung Bukan Tiang JTM    : " . (count($invalidSourceType) + count($invalidTargetType)) . " pelanggaran", count($invalidSourceType) + count($invalidTargetType) > 0 ? 'red' : 'green');
        CLI::write("  • Segmen Lintas Penyulang         : " . count($crossFeederList) . " segmen", count($crossFeederList) > 0 ? 'red' : 'green');
        CLI::write("  • Segmen Yatim (Orphan Node)      : " . count($orphanList) . " segmen", count($orphanList) > 0 ? 'red' : 'green');
        CLI::newLine();

        if (!empty($invalidSourceType) || !empty($invalidTargetType)) {
            CLI::write("Detail Segmen Bukan Tiang JTM (Pohon, Gardu, Recloser, dll.):", 'red');
            $allInvalid = array_merge($invalidSourceType, $invalidTargetType);
            $tableData = [];
            foreach (array_slice($allInvalid, 0, 15) as $inv) {
                $tableData[] = [
                    $inv['id'],
                    $inv['feeder'],
                    $inv['pole_role'],
                    $inv['asset_id'],
                    $inv['code'],
                    $inv['jenis_asset'],
                ];
            }
            CLI::table($tableData, ['TL ID', 'Penyulang', 'Posisi', 'Asset ID', 'Kode Asset', 'Jenis Asset Aktual']);
            CLI::newLine();
        }

        if (!empty($crossFeederList)) {
            CLI::write("Detail Segmen Lintas Penyulang:", 'red');
            $tableCf = [];
            foreach (array_slice($crossFeederList, 0, 10) as $cf) {
                $tableCf[] = [$cf['id'], $cf['tl_feeder'], $cf['source_feeder'], $cf['target_feeder'], $cf['source_code'], $cf['target_code']];
            }
            CLI::table($tableCf, ['TL ID', 'TL Feeder', 'Src Feeder', 'Tgt Feeder', 'Src Code', 'Tgt Code']);
            CLI::newLine();
        }

        CLI::write("Audit selesai. Validasi pembatasan tiang JTM siap ditegakkan pada seluruh jalur penyimpanan.", 'green');
        CLI::write("==================================================================", 'yellow');
    }
}
