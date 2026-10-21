<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\RowTreeAssetService;

class ReconcileRowTreesCommand extends BaseCommand
{
    protected $group       = 'Finding';
    protected $name        = 'temuan:reconcile-row-trees';
    protected $description = 'Reconciles ROW findings to tree assets (POHON_ROW), prevents duplicate trees per coordinate, records vegetation observations, and computes growth predictions.';
    protected $usage       = 'temuan:reconcile-row-trees [--execute]';
    protected $options     = [
        '--execute' => 'Execute transactional mutation and linkings (default: dry-run)',
    ];

    public function run(array $params)
    {
        $isExecute = CLI::getOption('execute') !== null;
        $svc = new RowTreeAssetService();

        CLI::write("==================================================================", 'yellow');
        CLI::write("   SIDAK TEJO — REKONSILIASI TEMUAN ROW KE ASET POHON (POHON_ROW)", 'yellow');
        CLI::write("==================================================================", 'yellow');
        CLI::write("Mode: " . ($isExecute ? "LIVE EXECUTION (TRANSACTIONAL UPDATE)" : "DRY-RUN (SIMULASI AUDIT)"), $isExecute ? 'red' : 'green');
        CLI::newLine();

        $db = \Config\Database::connect();
        $pelaksanaCounts = $db->table('temuan')
            ->select('pelaksana, COUNT(*) as cnt')
            ->where('deleted_at IS NULL')
            ->groupBy('pelaksana')
            ->get()
            ->getResultArray();
        CLI::write("Distribusi Pelaksana pada Tabel Temuan:", 'white');
        foreach ($pelaksanaCounts as $pc) {
            CLI::write("  • {$pc['pelaksana']} : {$pc['cnt']} temuan", 'cyan');
        }
        CLI::newLine();

        $jenisCounts = $db->table('temuan')
            ->select('jenis_temuan, COUNT(*) as cnt')
            ->where('deleted_at IS NULL')
            ->groupBy('jenis_temuan')
            ->get()
            ->getResultArray();
        CLI::write("Distribusi Jenis Temuan pada Tabel Temuan:", 'white');
        foreach ($jenisCounts as $jc) {
            CLI::write("  • {$jc['jenis_temuan']} : {$jc['cnt']} temuan", 'cyan');
        }
        CLI::newLine();

        $res = $svc->reconcileRowFindings($isExecute);

        CLI::write("Hasil Audit & Rekonsiliasi Temuan ROW:", 'white');
        CLI::write("  • Total Temuan ROW Terdeteksi     : " . $res['total_row_findings'], 'cyan');
        CLI::write("  • Temuan dengan Koordinat Valid   : " . $res['with_coordinates'], 'green');
        CLI::write("  • Temuan Tanpa Koordinat          : " . $res['without_coordinates'], 'yellow');
        CLI::write("  • Total Entitas Pohon Unik Titik  : " . $res['unique_trees_count'], 'magenta');
        CLI::write("  • Entitas Pohon Baru Dibuat       : " . $res['new_trees_created'], 'green');
        CLI::write("  • Riwayat Observasi Vegetasi      : " . $res['observations_recorded'], 'cyan');
        CLI::newLine();

        CLI::write("Sampel Penautan Temuan -> Aset Pohon:", 'white');
        $tHead = ['Temuan ID', 'Nomor Temuan', 'Target Tree ID', 'Spesies Pohon', 'Clearance', 'Koordinat'];
        $tData = [];
        foreach ($res['findings_linked'] as $fl) {
            $tData[] = [
                $fl['temuan_id'],
                $fl['nomor_temuan'],
                $fl['tree_asset_id'],
                $fl['species'],
                $fl['clearance_m'] !== null ? $fl['clearance_m'] . ' m' : 'Default (1.5m)',
                $fl['coord'],
            ];
        }
        CLI::table($tData, $tHead);
        CLI::newLine();

        CLI::write("Sampel Prediksi Pertumbuhan & Jadwal Tebang/Pangkas:", 'white');
        $pHead = ['Tree Asset ID', 'Status Prediksi', 'Confidence', 'Spesies', 'Laju (cm/hari)', 'Jadwal Pangkas'];
        $pData = [];
        foreach ($res['sample_predictions'] as $tid => $pred) {
            $pData[] = [
                $tid,
                $pred['prediction_status'],
                $pred['confidence'],
                $pred['species'],
                $pred['growth_rate_cm_day'] !== null ? $pred['growth_rate_cm_day'] : '-',
                $pred['recommended_prune_date'] !== null ? $pred['recommended_prune_date'] . " ({$pred['days_until_pruning']} hari)" : 'BELUM_CUKUP_DATA',
            ];
        }
        CLI::table($pData, $pHead);
        CLI::newLine();

        if (!$isExecute) {
            CLI::write("Audit selesai. Jalankan dengan flag --execute untuk memutasi database.", 'yellow');
        } else {
            CLI::write("Pohon ROW dan riwayat observasi berhasil dimutasi secara transaksional ke database.", 'green');
        }
        CLI::write("==================================================================", 'yellow');
    }
}
