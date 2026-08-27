<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Finding Type Canonicalization Audit Command (HF-CR06F-01)
 * Audit Only - Non-destructive inspection of finding categories vs legacy free-text values.
 */
class AuditFindingCanonicalCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'audit:finding-canonical';
    protected $description = 'Audits temuan.jenis_temuan against canonical domain types vs legacy free-text values.';

    public function run(array $params)
    {
        CLI::write("==================================================================", "yellow");
        CLI::write("       FINDING TYPE CANONICALIZATION & LEGACY AUDIT               ", "yellow");
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

        // 1. TOTAL INVENTORY STATS
        $totalTemuan = (int)$db->table('temuan')->countAllResults();
        $activeTemuan = (int)$db->table('temuan')->where('deleted_at IS NULL')->countAllResults();
        $deletedTemuan = $totalTemuan - $activeTemuan;

        CLI::write("1️⃣  OVERALL TEMUAN INVENTORY", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        CLI::write(sprintf("  Total Temuan Records (All)    : %d", $totalTemuan), "white");
        CLI::write(sprintf("  Active Temuan Records         : %d", $activeTemuan), "green");
        CLI::write(sprintf("  Soft-Deleted Records          : %d", $deletedTemuan), "yellow");
        CLI::newLine();

        // 2. DISTINCT JENIS_TEMUAN BREAKDOWN
        $distinctRows = $db->table('temuan')
            ->select('jenis_temuan, COUNT(*) as cnt')
            ->groupBy('jenis_temuan')
            ->orderBy('cnt', 'DESC')
            ->get()
            ->getResultArray();

        $canonicalCount = 0;
        $legacyCount    = 0;
        $legacyValues   = [];

        foreach ($distinctRows as $row) {
            $val = (string)($row['jenis_temuan'] ?? '');
            $cnt = (int)$row['cnt'];
            $upperVal = strtoupper(trim($val));

            if (in_array($upperVal, $canonicalCategories, true)) {
                $canonicalCount += $cnt;
            } else {
                $legacyCount += $cnt;
                $legacyValues[] = [
                    'value' => $val !== '' ? $val : '(NULL / EMPTY)',
                    'count' => $cnt,
                    'suggested_mapping' => $this->suggestCanonicalMapping($val),
                ];
            }
        }

        $canonicalPct = $totalTemuan > 0 ? ($canonicalCount / $totalTemuan) * 100 : 0;
        $legacyPct    = $totalTemuan > 0 ? ($legacyCount / $totalTemuan) * 100 : 0;

        CLI::write("2️⃣  CANONICAL VS LEGACY FREE-TEXT METRICS", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        CLI::write(sprintf("  Canonical Domain Types       : %d (%.2f%%)", $canonicalCount, $canonicalPct), $canonicalPct > 80 ? "green" : "yellow");
        CLI::write(sprintf("  Legacy Free-Text Types       : %d (%.2f%%)", $legacyCount, $legacyPct), $legacyCount > 0 ? "yellow" : "green");
        CLI::write(sprintf("  Distinct Finding Categories  : %d", count($distinctRows)), "white");
        CLI::write(sprintf("  Canonical Master Categories  : [%s]", implode(', ', $canonicalCategories)), "cyan");
        CLI::newLine();

        // 3. TARGET VALUE AUDIT: 'Isolator Retak Fasa R'
        CLI::write("3️⃣  TARGET VALUE AUDIT: 'Isolator Retak Fasa R'", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        $isolatorMatches = $db->table('temuan')
            ->select('COUNT(*) as cnt')
            ->like('jenis_temuan', 'Isolator Retak', 'both')
            ->get()
            ->getRowArray();
        $isolatorCount = (int)($isolatorMatches['cnt'] ?? 0);

        if ($isolatorCount > 0) {
            CLI::write(sprintf("  ⚠️  Status: LEGACY FREE-TEXT DETECTED (Count: %d records)", $isolatorCount), "yellow");
            CLI::write("  Classification       : Free-Text Defect Description stored in jenis_temuan", "white");
            CLI::write("  Canonical Mapping    : Domain 'KONSTRUKSI' (Defect: Isolator Retak)", "green");
        } else {
            CLI::write("  ℹ️  'Isolator Retak' string not found directly in jenis_temuan column.", "white");
        }
        CLI::newLine();

        // 4. DISTINCT VALUE TABLE
        CLI::write("4️⃣  DISTINCT FINDING VALUES AUDIT TABLE", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        CLI::write(sprintf("  %-4s | %-32s | %-8s | %-12s | %-16s", "NO", "JENIS TEMUAN VALUE", "COUNT", "STATUS", "SUGGESTED MAPPING"), "yellow");
        CLI::write("------------------------------------------------------------------", "white");

        $i = 1;
        foreach ($distinctRows as $row) {
            $val = (string)($row['jenis_temuan'] ?? '');
            $cnt = (int)$row['cnt'];
            $upperVal = strtoupper(trim($val));
            $isCanonical = in_array($upperVal, $canonicalCategories, true);
            $statusStr = $isCanonical ? 'CANONICAL' : 'LEGACY';
            $statusColor = $isCanonical ? 'green' : 'yellow';
            $displayVal = $val !== '' ? $val : '(NULL / EMPTY)';
            $mapping = $isCanonical ? $upperVal : $this->suggestCanonicalMapping($val);

            CLI::write(sprintf("  %-4d | %-32s | %-8d | %-12s | %-16s", 
                $i++, 
                mb_strimwidth($displayVal, 0, 32, '...'), 
                $cnt, 
                $statusStr, 
                $mapping
            ), $statusColor);
        }
        CLI::newLine();

        // 5. SUMMARY RECOMMENDATION
        CLI::write("5️⃣  ARCHITECTURAL AUDIT RECOMMENDATION", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        if ($legacyCount > 0) {
            CLI::write("  ⚠️  Action Plan Recommendation:", "yellow");
            CLI::write("  1. Search UI & Repository DataTables Hotfix: DEPLOYED (HF-CR06F-01)", "green");
            CLI::write("  2. Database Schema / Value Canonicalization Migration:", "white");
            CLI::write("     Design Migration `HF-CR06F-01B` to map legacy free-text values into:", "white");
            CLI::write("     - jenis_temuan = 'KONSTRUKSI' / 'HOTSPOT' / 'ROW' (Canonical Domain)", "cyan");
            CLI::write("     - Preserve original detailed description in detail_temuan", "cyan");
            CLI::write("  3. DO NOT blind migrate before User Architecture Sign-off.", "yellow");
        } else {
            CLI::write("  ✅ All existing finding records are already canonical.", "green");
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
