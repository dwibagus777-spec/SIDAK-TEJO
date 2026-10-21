<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DiagnoseDashboardDataCommand extends BaseCommand
{
    protected $group       = 'Diagnose';
    protected $name        = 'diagnose:dashboard';
    protected $description = 'Diagnose Dashboard assets, ROW trees, temuan #29, and Kahuripan BOM';

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        CLI::write("=== STORED TEMUAN_MATERIALS FOR KAHURIPAN ===", 'yellow');
        $rows = $db->query('SELECT tm.*, t.detail_temuan, t.penyulang_id, p.nama_penyulang 
            FROM temuan_materials tm 
            JOIN temuan t ON t.id = tm.temuan_id 
            JOIN penyulang p ON p.id = t.penyulang_id 
            WHERE p.nama_penyulang LIKE "%KAHURIPAN%"')->getResultArray();

        CLI::write("Total stored rows in temuan_materials for Kahuripan: " . count($rows));
        $byMat = [];
        foreach ($rows as $r) {
            $code = $r['canonical_code_snapshot'];
            $name = $r['canonical_name_snapshot'];
            $qty = (float)$r['quantity'];
            $tid = $r['temuan_id'];
            if (!isset($byMat[$code])) {
                $byMat[$code] = ['name' => $name, 'qty' => 0, 'tids' => []];
            }
            $byMat[$code]['qty'] += $qty;
            $byMat[$code]['tids'][] = $tid;
            CLI::write("  Temuan #{$tid} | {$code} | {$name} | Qty: {$qty} | " . substr($r['detail_temuan'], 0, 50));
        }

        CLI::write("\n=== TESTING TEMUAN #29 BOM GENERATION ===", 'yellow');
        $ruleService = new \App\Services\PlnStandardBomRuleService();
        $t29 = $db->table('temuan')->where('id', 29)->get()->getRowArray();
        if ($t29) {
            $bom29 = $ruleService->resolveBomFromFinding($t29);
            CLI::write("Finding #29 detail: " . $t29['detail_temuan']);
            CLI::write("Resolved materials count: " . count($bom29['materials']));
            foreach ($bom29['materials'] as $mat) {
                CLI::write("  - " . $mat['canonical_code'] . " | " . $mat['canonical_name'] . " | Qty: " . $mat['quantity'] . " " . $mat['unit']);
            }
            $syncRes = $ruleService->syncTemuanMaterials(29, true);
            CLI::write("Sync temuan #29 status: " . ($syncRes['status'] ?? 'unknown'));
        }
        foreach ($byMat as $c => $m) {
            CLI::write(sprintf("  %-16s | %-45s | Qty: %4.1f | Dari Temuan: %s", $c, substr($m['name'], 0, 45), $m['qty'], implode(', ', array_unique($m['tids']))));
        }

        CLI::write("\n=== MONTHLY FAULT CURRENT & RELAYS ===", 'yellow');
        $currMo = $db->query("SELECT YEAR(event_date) yr, MONTH(event_date) mo, AVG(NULLIF(fault_current_amperes, 0)) avg_curr, COUNT(CASE WHEN fault_current_amperes > 0 THEN 1 END) cnt_curr FROM historical_feeder_interruptions WHERE event_date IS NOT NULL GROUP BY yr, mo ORDER BY yr DESC, mo ASC")->getResultArray();
        foreach ($currMo as $cm) {
            CLI::write("Yr {$cm['yr']} Mo {$cm['mo']}: Cnt: {$cm['cnt_curr']}, Avg Current: " . round((float)$cm['avg_curr'], 1) . " A");
        }
        $activeAssets = $db->table('assets')->where('deleted_at IS NULL')->countAllResults();
        $deletedAssets = $db->table('assets')->where('deleted_at IS NOT NULL')->countAllResults();
        CLI::write("Active assets: {$activeAssets}, Deleted assets: {$deletedAssets}, Total: " . ($activeAssets + $deletedAssets));

        $pohonAsset = $db->query("SELECT COUNT(*) cnt FROM assets WHERE deleted_at IS NULL AND (jenis_asset LIKE '%POHON%' OR jenis_asset LIKE '%ROW%' OR jenis_asset LIKE '%TREE%')")->getRowArray()['cnt'] ?? 0;
        CLI::write("Pohon in assets table: {$pohonAsset}");

        $vegObs = $db->tableExists('vegetation_observations') ? $db->table('vegetation_observations')->countAllResults() : 0;
        CLI::write("Total vegetation_observations: {$vegObs}");

        $rowTemuan = $db->query("SELECT COUNT(*) cnt FROM temuan WHERE deleted_at IS NULL AND (jenis_temuan LIKE '%ROW%' OR jenis_temuan LIKE '%POHON%' OR detail_temuan LIKE '%pohon%' OR detail_temuan LIKE '%row%' OR detail_temuan LIKE '%vegetasi%')")->getRowArray()['cnt'] ?? 0;
        CLI::write("ROW / Pohon in temuan table: {$rowTemuan}");

        CLI::write("\n=== INTERRUPTION METRICS BY YEAR ===", 'yellow');
        $hYears = $db->query("SELECT DISTINCT YEAR(event_date) yr FROM historical_feeder_interruptions WHERE event_date IS NOT NULL ORDER BY yr DESC")->getResultArray();
        foreach ($hYears as $hy) {
            $yr = (int)$hy['yr'];
            $tot = $db->query("SELECT COUNT(*) cnt, 
                COUNT(CASE WHEN relay_trip_type IS NOT NULL AND relay_trip_type != '' THEN 1 END) cnt_relay,
                COUNT(CASE WHEN cause_category IS NOT NULL AND cause_category != '' THEN 1 END) cnt_cause,
                COUNT(CASE WHEN fault_current_amperes > 0 THEN 1 END) cnt_current,
                AVG(CASE WHEN fault_current_amperes > 0 THEN fault_current_amperes END) avg_current,
                MAX(MONTH(event_date)) max_mo
                FROM historical_feeder_interruptions WHERE YEAR(event_date) = {$yr}")->getRowArray();
            CLI::write("Year {$yr}: Max Mo: {$tot['max_mo']}, Total: {$tot['cnt']}, With Relay: {$tot['cnt_relay']}, With Cause: {$tot['cnt_cause']}, With Current: {$tot['cnt_current']}, Avg Current: " . round((float)$tot['avg_current'], 1) . " A");
        }
    }
}
