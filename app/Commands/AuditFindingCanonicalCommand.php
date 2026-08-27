<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Finding Type Canonicalization Audit Command (HF-CR06F-01C)
 * Differentiates Active Operational Truth from Historical/Soft-Deleted Audit Trails.
 */
class AuditFindingCanonicalCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'audit:finding-canonical';
    protected $description = 'Audits temuan.jenis_temuan against canonical domain types across Active and Historical scopes.';

    public function run(array $params)
    {
        CLI::write("==================================================================", "yellow");
        CLI::write("       FINDING TYPE CANONICALIZATION & LEGACY AUDIT (v1.2)        ", "yellow");
        CLI::write("==================================================================", "yellow");
        CLI::newLine();

        try {
            $db = \Config\Database::connect();
            $db->initialize();
        } catch (\Throwable $e) {
            CLI::write("❌ Database connection failed: " . $e->getMessage(), "red");
            return 1;
        }

        if (!$db->tableExists('temuan')) {
            CLI::write("❌ Table 'temuan' does not exist in database.", "red");
            return 1;
        }

        // Canonical Baseline Categories
        $canonicalCategories = ['KONSTRUKSI', 'HOTSPOT', 'ROW'];

        // 1. OVERALL INVENTORY STATS
        $totalTemuan   = (int)$db->table('temuan')->countAllResults();
        $activeTemuan  = (int)$db->table('temuan')->where('deleted_at IS NULL')->countAllResults();
        $deletedTemuan = (int)$db->table('temuan')->where('deleted_at IS NOT NULL')->countAllResults();

        CLI::write("1️⃣  OVERALL INVENTORY SUMMARY", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        CLI::write(sprintf("  Total Records in Database     : %d", $totalTemuan), "white");
        CLI::write(sprintf("  Active Operational Records    : %d", $activeTemuan), "green");
        CLI::write(sprintf("  Soft-Deleted / Archived       : %d", $deletedTemuan), "yellow");
        CLI::newLine();

        // 2. ACTIVE OPERATIONAL DATASET AUDIT (deleted_at IS NULL)
        CLI::write("2️⃣  ACTIVE DATASET AUDIT (Operational Truth - deleted_at IS NULL)", "cyan");
        CLI::write("------------------------------------------------------------------", "white");

        $activeDistinctRows = $db->table('temuan')
            ->select('jenis_temuan, COUNT(*) as cnt')
            ->where('deleted_at IS NULL')
            ->groupBy('jenis_temuan')
            ->orderBy('cnt', 'DESC')
            ->get()
            ->getResultArray();

        $activeCanonicalCount = 0;
        $activeLegacyCount    = 0;
        $activeLegacyValues   = [];

        foreach ($activeDistinctRows as $row) {
            $val = (string)($row['jenis_temuan'] ?? '');
            $cnt = (int)$row['cnt'];
            $upperVal = strtoupper(trim($val));

            if (in_array($upperVal, $canonicalCategories, true)) {
                $activeCanonicalCount += $cnt;
            } else {
                $activeLegacyCount += $cnt;
                $activeLegacyValues[] = [
                    'value' => $val !== '' ? $val : '(NULL / EMPTY)',
                    'count' => $cnt,
                    'suggested_mapping' => $this->suggestCanonicalMapping($val),
                ];
            }
        }

        $activeCanonicalPct = $activeTemuan > 0 ? ($activeCanonicalCount / $activeTemuan) * 100 : 100;
        $activeLegacyPct    = $activeTemuan > 0 ? ($activeLegacyCount / $activeTemuan) * 100 : 0;

        CLI::write(sprintf("  Active Canonical Types        : %d (%.2f%%)", $activeCanonicalCount, $activeCanonicalPct), $activeCanonicalPct >= 100 ? "green" : "yellow");
        CLI::write(sprintf("  Active Legacy Free-Text Types : %d (%.2f%%)", $activeLegacyCount, $activeLegacyPct), $activeLegacyCount === 0 ? "green" : "red");
        CLI::write(sprintf("  Active Distinct Categories    : %d", count($activeDistinctRows)), "white");
        CLI::write(sprintf("  Canonical Master Categories   : [%s]", implode(', ', $canonicalCategories)), "cyan");

        // Breakdown table for active records
        CLI::newLine();
        CLI::write("  Active Finding Breakdown Table:", "white");
        CLI::write(sprintf("  %-4s | %-28s | %-8s | %-12s", "NO", "ACTIVE CATEGORY", "COUNT", "STATUS"), "yellow");
        CLI::write("  ----------------------------------------------------------------", "white");

        $i = 1;
        foreach ($activeDistinctRows as $row) {
            $val = (string)($row['jenis_temuan'] ?? '');
            $cnt = (int)$row['cnt'];
            $upperVal = strtoupper(trim($val));
            $isCanonical = in_array($upperVal, $canonicalCategories, true);
            $statusStr = $isCanonical ? 'CANONICAL' : 'LEGACY';
            $statusColor = $isCanonical ? 'green' : 'red';
            $displayVal = $val !== '' ? $val : '(NULL / EMPTY)';

            CLI::write(sprintf("  %-4d | %-28s | %-8d | %-12s", 
                $i++, 
                mb_strimwidth($displayVal, 0, 28, '...'), 
                $cnt, 
                $statusStr
            ), $statusColor);
        }
        CLI::newLine();

        // 3. HISTORICAL / SOFT-DELETED AUDIT (deleted_at IS NOT NULL)
        CLI::write("3️⃣  HISTORICAL / ARCHIVAL INVENTORY (Audit Trail - deleted_at IS NOT NULL)", "cyan");
        CLI::write("------------------------------------------------------------------", "white");

        $deletedDistinctRows = $db->table('temuan')
            ->select('jenis_temuan, COUNT(*) as cnt')
            ->where('deleted_at IS NOT NULL')
            ->groupBy('jenis_temuan')
            ->orderBy('cnt', 'DESC')
            ->get()
            ->getResultArray();

        $deletedCanonicalCount = 0;
        $deletedLegacyCount    = 0;

        foreach ($deletedDistinctRows as $row) {
            $val = (string)($row['jenis_temuan'] ?? '');
            $cnt = (int)$row['cnt'];
            $upperVal = strtoupper(trim($val));

            if (in_array($upperVal, $canonicalCategories, true)) {
                $deletedCanonicalCount += $cnt;
            } else {
                $deletedLegacyCount += $cnt;
            }
        }

        CLI::write(sprintf("  Soft-Deleted Records Total    : %d", $deletedTemuan), "white");
        CLI::write(sprintf("  Historical Canonical Records  : %d", $deletedCanonicalCount), "white");
        CLI::write(sprintf("  Historical Legacy Records     : %d", $deletedLegacyCount), $deletedLegacyCount > 0 ? "yellow" : "green");

        if ($deletedLegacyCount > 0) {
            CLI::write("  ℹ️  Note: Historical legacy records are safely preserved in soft-delete state.", "yellow");
            CLI::write("      They maintain audit trail history and do NOT impact active operations.", "yellow");
        }
        CLI::newLine();

        // 4. TARGET VALUE STATUS: 'Isolator Retak Fasa R'
        CLI::write("4️⃣  TARGET RECORD VERIFICATION: 'Isolator Retak Fasa R'", "cyan");
        CLI::write("------------------------------------------------------------------", "white");

        $activeIsolator = (int)$db->table('temuan')
            ->where('deleted_at IS NULL')
            ->like('jenis_temuan', 'Isolator Retak', 'both')
            ->countAllResults();

        $deletedIsolator = (int)$db->table('temuan')
            ->where('deleted_at IS NOT NULL')
            ->like('jenis_temuan', 'Isolator Retak', 'both')
            ->countAllResults();

        CLI::write(sprintf("  Active State Count            : %d records", $activeIsolator), $activeIsolator === 0 ? "green" : "red");
        CLI::write(sprintf("  Historical (Soft-Deleted)     : %d records", $deletedIsolator), "cyan");

        if ($activeIsolator === 0 && $deletedIsolator > 0) {
            CLI::write("  ✅ Status: PROPERLY ARCHIVED (Soft-deleted, zero active pollution)", "green");
        } elseif ($activeIsolator === 0 && $deletedIsolator === 0) {
            CLI::write("  ✅ Status: CLEAN (Record does not exist)", "green");
        } else {
            CLI::write("  ⚠️  Status: ACTIVE VIOLATION (Record is still active)", "red");
        }
        CLI::newLine();

        // 5. FINAL VERDICT & COMPLIANCE
        CLI::write("5️⃣  FINAL ENTERPRISE COMPLIANCE VERDICT", "cyan");
        CLI::write("------------------------------------------------------------------", "white");

        if ($activeLegacyCount === 0) {
            CLI::write("  🟢 Active Finding Dataset       : CANONICAL & CLEAN", "green");
            CLI::write("  🟢 Active Legacy Free-Text      : 0 (0.00%)", "green");
            CLI::write(sprintf("  ℹ️  Historical Soft-Deleted Data : %d legacy records retained for audit trail", $deletedLegacyCount), "cyan");
            CLI::write("  🟢 Canonicalization Compliance  : 100.00% (ACTIVE OPERATIONAL DATASET)", "green");
            CLI::newLine();
            CLI::write("  ✅ ENTERPRISE AUDIT PASSED: Active operational truth is 100% canonical.", "green");
            CLI::write("     No destructive hard-delete or emergency migration is required.", "green");
        } else {
            CLI::write("  ⚠️  Active Finding Dataset       : CONTAINS LEGACY VALUES", "yellow");
            CLI::write(sprintf("  ⚠️  Active Legacy Free-Text      : %d records", $activeLegacyCount), "yellow");
            CLI::write(sprintf("  ⚠️  Canonicalization Compliance  : %.2f%%", $activeCanonicalPct), "yellow");
            CLI::newLine();
            CLI::write("  Recommendation: Schedule migration HF-CR06F-01B to map active legacy records.", "yellow");
        }

        CLI::newLine();
        CLI::write("==================================================================", "yellow");
        CLI::write("                   AUDIT COMPLETED SUCCESSFULLY                   ", "green");
        CLI::write("==================================================================", "yellow");

        return 0;
    }

    private function suggestCanonicalMapping(string $value): string
    {
        $val = strtolower($value);
        if (str_contains($val, 'isolator') || str_contains($val, 'trafo') || str_contains($val, 'tiang') || str_contains($val, 'crossarm') || str_contains($val, 'konstruksi') || str_contains($val, 'jumper') || str_contains($val, 'fco') || str_contains($val, 'arrester') || str_contains($val, 'guy') || str_contains($val, 'grounding') || str_contains($val, 'andongan')) {
            return 'KONSTRUKSI';
        }
        if (str_contains($val, 'hotspot') || str_contains($val, 'thermo') || str_contains($val, 'klem') || str_contains($val, 'panas') || str_contains($val, 'temperatur')) {
            return 'HOTSPOT';
        }
        if (str_contains($val, 'row') || str_contains($val, 'pohon') || str_contains($val, 'ranting') || str_contains($val, 'tumbuh') || str_contains($val, 'bambu')) {
            return 'ROW';
        }
        return 'KONSTRUKSI (Default)';
    }
}
