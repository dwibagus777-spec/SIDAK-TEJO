<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class AuditDashboardAssetsCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'audit:dashboard-assets';
    protected $description = 'Audit assets breakdown and inspect files for dashboard reconciliation';

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        $dbFeeders = $db->table('penyulang')->select('id, kode_penyulang, nama_penyulang, ulp_id')->get()->getResultArray();
        CLI::write("Total Feeders in DB: " . count($dbFeeders), 'green');

        $dbMap = [];
        foreach ($dbFeeders as $df) {
            $norm = $this->normalizeFeederName($df['nama_penyulang']);
            $dbMap[$norm][] = $df;
        }

        $aliases = [
            'MCA'                  => 'MUTIARACITRAASRI',
            'SMC'                  => 'SURYAMULTICEMERLANG',
            'GLAGAHARUM'           => 'GLAGAHARUM',
            'GLAGAHARUM'           => 'GLAGAHARUM',
            'JAVAPACIFIK1'         => 'JAVAPASIFIK1',
            'JAVAPACIFIK2'         => 'JAVAPASIFIK2',
            'JAVAPACIFIK3'         => 'JAVAPASIFIK3',
            'JAVAPACIFIK4'         => 'JAVAPASIFIK4',
            'JAVAPASIFIC1'         => 'JAVAPASIFIK1',
            'JAVAPASIFIC2'         => 'JAVAPASIFIK2',
            'JAVAPASIFIC3'         => 'JAVAPASIFIK3',
            'JAVAPASIFIC4'         => 'JAVAPASIFIK4',
            'SINGOGALIH'           => 'SINGOGALIH',
            'WARUBERON'            => 'WARUBERON',
            'JAYALAND'             => 'JAYALAND',
            'HEAVENLAND'           => 'HEAVENLAND',
            'PERMATAHIJAUBARU'     => 'PERMATAHIJAU',
            'PERMATAHIJAU'         => 'PERMATAHIJAU',
        ];

        // 1. JTM.xlsx feeders
        $xlsxPath = FCPATH . '../JTM.xlsx';
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($xlsxPath)->getActiveSheet();
        $maxRow = $sheet->getHighestRow();
        $jtmFeeders = [];
        for ($r = 2; $r <= $maxRow; $r++) {
            $f = trim((string)$sheet->getCell("C{$r}")->getValue());
            if ($f !== '') $jtmFeeders[$f] = ($jtmFeeders[$f] ?? 0) + 1;
        }

        // 2. Gardu CSV
        $h = fopen(FCPATH . '../asset-statistics-SIDOARJO-jtm_gardu.csv', 'r');
        fgetcsv($h);
        $garduFeeders = [];
        while (($row = fgetcsv($h)) !== false) {
            if (!empty($row[2])) {
                $f = trim($row[2]);
                $garduFeeders[$f] = ($garduFeeders[$f] ?? 0) + 1;
            }
        }
        fclose($h);

        // 3. Trafo CSV
        $h = fopen(FCPATH . '../asset-statistics-SIDOARJO-jtm_trafo.csv', 'r');
        fgetcsv($h);
        $trafoFeeders = [];
        while (($row = fgetcsv($h)) !== false) {
            if (!empty($row[2])) {
                $f = trim($row[2]);
                $trafoFeeders[$f] = ($trafoFeeders[$f] ?? 0) + 1;
            }
        }
        fclose($h);

        $files = [
            'JTM.xlsx'  => $jtmFeeders,
            'GARDU CSV' => $garduFeeders,
            'TRAFO CSV' => $trafoFeeders,
        ];

        foreach ($files as $fLabel => $feedersList) {
            CLI::write("\n=== MATCHING AUDIT FOR: {$fLabel} (" . count($feedersList) . " feeders) ===", 'yellow');
            $exact = 0;
            $ambig = 0;
            $unmatched = 0;
            $unmatchedList = [];

            foreach ($feedersList as $fn => $cnt) {
                $res = $this->matchFeeder($fn, $dbMap, $aliases);
                if ($res['status'] === 'EXACT') {
                    $exact++;
                } elseif ($res['status'] === 'AMBIGUOUS') {
                    $ambig++;
                    CLI::write("  [AMBIGUOUS] '{$fn}' matched multiple: " . json_encode(array_column($res['matched'], 'nama_penyulang')), 'red');
                } else {
                    $unmatched++;
                    $unmatchedList[$fn] = $cnt;
                    CLI::write("  [UNMATCHED] '{$fn}' ({$cnt} rows)", 'red');
                }
            }
            CLI::write("Summary for {$fLabel}: Exact: {$exact}, Ambiguous: {$ambig}, Unmatched: {$unmatched}", 'cyan');
        }

        CLI::write("\n=== DETAILS OF UNMATCHED ROWS ===", 'yellow');
        $gh = fopen(FCPATH . '../asset-statistics-SIDOARJO-jtm_gardu.csv', 'r');
        fgetcsv($gh);
        while (($row = fgetcsv($gh)) !== false) {
            if (in_array(trim($row[2] ?? ''), ['WARINGIN INTI', 'LAPINDO', 'SUGIHWARAS'])) {
                CLI::write("  [GARDU] ULP: {$row[1]} | Feeder: {$row[2]} | Aset: {$row[3]} | Lat: {$row[4]} | Lng: {$row[5]} | Constr: {$row[7]}");
            }
        }
        fclose($gh);

        $th = fopen(FCPATH . '../asset-statistics-SIDOARJO-jtm_trafo.csv', 'r');
        fgetcsv($th);
        while (($row = fgetcsv($th)) !== false) {
            if (in_array(trim($row[2] ?? ''), ['WARINGIN INTI'])) {
                CLI::write("  [TRAFO] ULP: {$row[1]} | Feeder: {$row[2]} | Aset: {$row[3]} | Lat: {$row[4]} | Lng: {$row[5]} | Constr: {$row[7]}");
            }
        }
        fclose($th);
        if ($db->tableExists('asset_relationships')) {
            CLI::write("asset_relationships columns: " . implode(', ', $db->getFieldNames('asset_relationships')));
            $relCount = $db->table('asset_relationships')->countAllResults();
            CLI::write("Total rows in asset_relationships: " . $relCount);
            if ($relCount > 0) {
                $sampleRels = $db->table('asset_relationships')->limit(5)->get()->getResultArray();
                foreach ($sampleRels as $sr) {
                    CLI::write("  " . json_encode($sr));
                }
            }
        }
        $parentCount = $db->table('assets')->where('parent_asset_id IS NOT NULL')->where('deleted_at IS NULL')->countAllResults();
        CLI::write("Assets with parent_asset_id: " . $parentCount);
        CLI::write("Columns: " . implode(', ', $db->getFieldNames('assets')));
        CLI::write("Table asset_relationships exists: " . ($db->tableExists('asset_relationships') ? 'YES' : 'NO'));
        CLI::write("Table translines exists: " . ($db->tableExists('translines') ? 'YES' : 'NO'));

        $assetBreakdown = $db->query("SELECT jenis_asset, COUNT(*) cnt FROM assets WHERE deleted_at IS NULL GROUP BY jenis_asset ORDER BY cnt DESC")->getResultArray();
        CLI::write("Active assets by jenis_asset:");
        foreach ($assetBreakdown as $ab) {
            CLI::write("  - {$ab['jenis_asset']}: {$ab['cnt']}");
        }

        CLI::write("\n=== TEST DASHBOARD AGGREGATION LOGIC ===", 'yellow');
        $assetSummaryRows = $assetBreakdown;
        $assetTotalAll = array_sum(array_column($assetSummaryRows, 'cnt'));
        $jtmCount      = 0;
        $keypointCount = 0;
        $garduCount    = 0;
        $trafoCount    = 0;
        $pohonCount    = 0;

        foreach ($assetSummaryRows as $asr) {
            $rawType = strtoupper(trim((string)($asr['jenis_asset'] ?? '')));
            $c = (int)($asr['cnt'] ?? 0);

            if (preg_match('/(KEYPOINT|RECLOSER|PMCB|GH|LBS|ASS|SWITCH|DISCONNECT|SECTIONALIZER|FCO)/i', $rawType)) {
                $keypointCount += $c;
            } elseif (preg_match('/(TRAFO|TRANSFORMATOR|TRANSFORMER)/i', $rawType)) {
                $trafoCount += $c;
            } elseif (preg_match('/(GARDU|SUBSTATION|PORTAL|CANTOL|KIOS|KUBIKEL|GTT)/i', $rawType)) {
                $garduCount += $c;
            } elseif (preg_match('/(POHON|TREE|VEGETASI|ROW)/i', $rawType)) {
                $pohonCount += $c;
            } elseif (preg_match('/(TIANG|POLE|JTM|SUTM|SKTM|CONDUCTOR)/i', $rawType) || $rawType === '') {
                $jtmCount += $c;
            } else {
                $jtmCount += $c;
            }
        }

        CLI::write("Total Aset:  " . number_format($assetTotalAll));
        CLI::write("Tiang JTM:   " . number_format($jtmCount));
        CLI::write("Keypoint:    " . number_format($keypointCount));
        CLI::write("Gardu:       " . number_format($garduCount));
        CLI::write("Trafo:       " . number_format($trafoCount));
        CLI::write("Sum Check:   " . number_format($jtmCount + $keypointCount + $garduCount + $trafoCount));
    }

    private function normalizeFeederName(string $name): string
    {
        $n = strtoupper(trim($name));
        $n = preg_replace('/^(PENYULANG|FEEDER|P\.)\s+/i', '', $n);
        $n = preg_replace('/[^A-Z0-9]/', '', $n);
        return $n;
    }

    private function matchFeeder(string $fileFeeder, array $dbMap, array $aliases): array
    {
        $clean = trim($fileFeeder);
        $norm = $this->normalizeFeederName($clean);

        if (isset($dbMap[$norm])) {
            return [
                'status'  => count($dbMap[$norm]) === 1 ? 'EXACT' : 'AMBIGUOUS',
                'matched' => $dbMap[$norm],
                'rule'    => 'Direct Normalized'
            ];
        }

        if (isset($aliases[$norm]) && isset($dbMap[$aliases[$norm]])) {
            return [
                'status'  => count($dbMap[$aliases[$norm]]) === 1 ? 'EXACT' : 'AMBIGUOUS',
                'matched' => $dbMap[$aliases[$norm]],
                'rule'    => 'Alias Lookup'
            ];
        }

        return [
            'status'  => 'UNMATCHED',
            'matched' => [],
            'rule'    => 'None'
        ];
    }
}
