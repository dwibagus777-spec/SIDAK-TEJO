<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\CanonicalFeederAssetResolutionService;

/**
 * Phase AR-01 Phase 2: Data Lineage & Candidate Reconciliation Command (Strictly Read-Only)
 * Usage: php spark audit:ar01-reconcile [feederId]
 */
class AuditAr01ReconcileCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'audit:ar01-reconcile';
    protected $description = 'Phase AR-01 Phase 2: Reconcile 517 vs 518 scope, Feeder Lineage, and Candidate Evidence (Strictly Read-Only)';

    protected $arguments = [
        'feeder' => 'The Feeder ID to reconcile against (default: 1)',
    ];

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $resolver = new CanonicalFeederAssetResolutionService($db);

        $feederId = !empty($params[0]) && is_numeric($params[0]) ? (int)$params[0] : 1;

        CLI::write("\n==================================================================", 'yellow');
        CLI::write("    AR-01 PHASE 2: DATA LINEAGE & CANDIDATE RECONCILIATION AUDIT  ", 'yellow');
        CLI::write("    PILOT FEEDER: PYL-001 (STRICTLY READ-ONLY / ZERO MUTATION)   ", 'yellow');
        CLI::write("==================================================================\n", 'yellow');

        $result = $resolver->reconcileGlobalAssetLineage($feederId);

        if (!$result['success']) {
            CLI::error("ERROR: " . ($result['error'] ?? 'Gagal melakukan audit rekonsiliasi data lineage.'));
            return 1;
        }

        $tf = $result['target_feeder'];
        $sr = $result['scope_reconciliation'];
        $fl = $result['feeder_lineage'];
        $cl = $result['clusters'];
        $ev = $result['evidence_classification'];

        // 1. TARGET FEEDER
        CLI::write("1. TARGET FEEDER CONTEXT (CR-06F Physical Truth)", 'cyan');
        CLI::write("------------------------------------------------------------------");
        CLI::write("  Feeder ID                         : {$tf['id']}");
        CLI::write("  Feeder Code                       : {$tf['kode_penyulang']}");
        CLI::write("  Feeder Name                       : {$tf['nama_penyulang']}");
        CLI::write("  Parent ULP ID                     : " . ($tf['ulp_id'] ?? 1));

        // 2. ALL REGISTERED FEEDERS IN DATABASE
        CLI::write("\n2. REGISTERED FEEDER REGISTRY IN DATABASE (Global Scope)", 'cyan');
        CLI::write("------------------------------------------------------------------");
        foreach ($result['all_feeders'] as $f) {
            $isTarget = ((int)$f['id'] === (int)$tf['id']) ? " [TARGET PILOT]" : "";
            CLI::write("  • Feeder ID #{$f['id']} : [{$f['kode_penyulang']}] {$f['nama_penyulang']}{$isTarget}");
        }

        // 3. MASTER ASSET SCOPE & DISCREPANCY RECONCILIATION
        CLI::write("\n3. MASTER ASSET SCOPE & DISCREPANCY RECONCILIATION", 'cyan');
        CLI::write("------------------------------------------------------------------");
        CLI::write("  Total Raw Records in Table 'assets': {$sr['raw_master_assets']}");
        CLI::write("  Active Master Assets (Grid Scope)  : " . CLI::color("{$sr['active_grid_scope']} assets", 'green'));
        CLI::write("  Soft-Deleted Records (deleted_at)  : " . CLI::color("{$sr['soft_deleted_count']} records", 'yellow'));
        CLI::write("  Status Discrepancy Reconciliation  : " . CLI::color($sr['discrepancy_explanation'], 'green'));
        if (!empty($sr['soft_deleted_records'])) {
            CLI::write("  Sampel Soft-Deleted Records (5 dari {$sr['soft_deleted_count']}):");
            foreach (array_slice($sr['soft_deleted_records'], 0, 5) as $sd) {
                CLI::write("     └─ [ID: {$sd['id']}] Code: '{$sd['kode_asset']}' | Name: '{$sd['nama_asset']}' | Deleted: {$sd['deleted_at']}");
            }
            if (count($sr['soft_deleted_records']) > 5) {
                CLI::write("     └─ ... dan " . (count($sr['soft_deleted_records']) - 5) . " record soft-deleted lainnya tersimpan aman.");
            }
        }

        // 4. GLOBAL FEEDER FK LINEAGE & DISTRIBUTION
        CLI::write("\n4. GLOBAL ACTIVE FEEDER FK LINEAGE & OWNERSHIP (Active Grid Scope: {$sr['active_grid_scope']})", 'cyan');
        CLI::write("------------------------------------------------------------------");
        if (empty($fl)) {
            CLI::write("  (Tidak ada aset aktif)");
        } else {
            foreach ($fl as $row) {
                $fidStr = $row['penyulang_id'] !== null ? "Feeder #{$row['penyulang_id']}" : "NULL (Unassigned)";
                CLI::write("  • {$fidStr} [{$row['feeder_code']}] {$row['feeder_name']} : {$row['count']} assets");
            }
        }

        $ql = $result['quarantined_lineage'] ?? [];
        if (!empty($ql)) {
            CLI::write("\n4.B. QUARANTINED HISTORICAL FK LINEAGE (Soft-Deleted: {$sr['soft_deleted_count']})", 'cyan');
            CLI::write("------------------------------------------------------------------");
            foreach ($ql as $qrow) {
                $qfidStr = $qrow['penyulang_id'] !== null ? "Feeder #{$qrow['penyulang_id']}" : "NULL (Unassigned CANDRAMAS)";
                CLI::write("  • {$qfidStr} [{$qrow['feeder_code']}] {$qrow['feeder_name']} : {$qrow['count']} records");
            }
        }

        // 5. NAMING & CODE CLUSTER RECONNAISSANCE
        CLI::write("\n5. ASSET NAMING & CODE PATTERN CLUSTERS", 'cyan');
        CLI::write("------------------------------------------------------------------");
        CLI::write("  A. Code Pattern Breakdown:");
        foreach ($cl['code_prefixes'] as $cp => $cnt) {
            CLI::write("     • {$cp} : {$cnt} assets");
        }
        CLI::write("\n  B. Name Prefix Breakdown (Top prefixes):");
        arsort($cl['name_prefixes']);
        foreach (array_slice($cl['name_prefixes'], 0, 8) as $np => $cnt) {
            CLI::write("     • {$np}_* : {$cnt} assets");
        }

        // 6. TARGET FEEDER EVIDENCE EVALUATION
        $st = $ev['stats'];
        $sm = $ev['samples'];

        CLI::write("\n6. PILOT PYL-001 EVIDENCE CLASSIFICATION (Contract AR-01 Invariants)", 'cyan');
        CLI::write("------------------------------------------------------------------");
        CLI::write("  Level A — Strong Evidence (Explicit FK / Direct Canonical Link) : " . CLI::color("{$st['level_a_strong']} assets", 'green'));
        CLI::write("  Level B — Supporting Evidence (Textual / Naming Marker Match)    : " . CLI::color("{$st['level_b_supporting']} assets", 'yellow'));
        CLI::write("  Level C — Insufficient Evidence (Generic / Unlinked / NULL)      : " . CLI::color("{$st['level_c_insufficient']} assets", 'red'));
        CLI::write("  Alien Feeder (Belongs to Other Registered Feeder)               : " . CLI::color("{$st['cross_feeder_alien']} assets", 'light_cyan'));

        if (!empty($sm['level_b'])) {
            CLI::write("\n  Sample Supporting Evidence Assets (Level B):");
            foreach ($sm['level_b'] as $ast) {
                CLI::write("     └─ [ID: {$ast['id']}] Code: '{$ast['kode_asset']}' | Name: '{$ast['nama_asset']}' | FK: " . ($ast['penyulang_id'] ?? 'NULL'));
            }
        }

        if (!empty($sm['level_c'])) {
            CLI::write("\n  Sample Insufficient Evidence Assets (Level C):");
            foreach ($sm['level_c'] as $ast) {
                CLI::write("     └─ [ID: {$ast['id']}] Code: '{$ast['kode_asset']}' | Name: '{$ast['nama_asset']}' | FK: " . ($ast['penyulang_id'] ?? 'NULL'));
            }
        }

        // 7. GOVERNANCE CONCLUSION
        CLI::write("\n==================================================================", 'green');
        CLI::write("🟢 RECONCILIATION AUDIT COMPLETE: ZERO MUTATIONS APPLIED", 'green');
        CLI::write("   All data relationships verified read-only against physical truth.", 'green');
        CLI::write("==================================================================\n", 'green');

        return 0;
    }
}
