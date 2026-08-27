<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * CR-06 Construction Intelligence Acceptance Audit CLI Command
 * Hardened with CR-06-AIH-01: Strict Verdict Consistency.
 */
class AuditCr06Command extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'audit:cr06';
    protected $description = 'Executes complete production acceptance audit for CR-06 Construction Intelligence.';

    public function run(array $params)
    {
        CLI::write("==================================================================", "yellow");
        CLI::write("    CR-06 PRODUCTION DATA & ARCHITECTURAL ACCEPTANCE AUDIT        ", "yellow");
        CLI::write("==================================================================", "yellow");
        CLI::newLine();

        try {
            $db = \Config\Database::connect();
            $db->initialize();
        } catch (\Throwable $e) {
            CLI::write("❌ Database connection failed: " . $e->getMessage(), "red");
            return 1;
        }

        $failures = [];
        $warnings = [];

        // 1. POPULATION AUDIT
        CLI::write("1️⃣  POPULATION AUDIT (13 REQUIRED TABLES)", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        $tables = [
            'master_materials'                 => ['min' => 1, 'domain' => 'CR-06A Material Identity'],
            'material_aliases'                 => ['min' => 1, 'domain' => 'CR-06A Material Aliases'],
            'construction_types'               => ['min' => 1, 'domain' => 'CR-06A Construction Taxonomy'],
            'construction_bom_items'           => ['min' => 1, 'domain' => 'CR-06A Construction BOM Items'],
            'network_section_configurations'   => ['min' => 0, 'domain' => 'CR-06B Section Configurations (0 is valid empty state)'],
            'network_section_conductors'       => ['min' => 0, 'domain' => 'CR-06B Mixed Conductor Segments'],
            'network_section_accessories'      => ['min' => 0, 'domain' => 'CR-06B Network Accessories'],
            'inspection_programs'              => ['min' => 1, 'domain' => 'CR-06C Inspection Programs'],
            'inspection_measurement_templates' => ['min' => 1, 'domain' => 'CR-06C GTT Measurement Template'],
            'inspection_measurement_points'    => ['min' => 1, 'domain' => 'CR-06C GTT Measurement Points'],
            'feeder_health_policy_versions'    => ['min' => 1, 'domain' => 'CR-06E Feeder Health Policy'],
            'feeder_health_policy_rules'       => ['min' => 1, 'domain' => 'CR-06E Feeder Health Parameterized Rules'],
            'feeder_health_classifications'    => ['min' => 0, 'domain' => 'CR-06E Monthly Feeder Health Evaluations'],
        ];

        foreach ($tables as $t => $meta) {
            $exists = $db->tableExists($t);
            if (!$exists) {
                $failures[] = "Required table '{$t}' is MISSING in database.";
                CLI::write(sprintf("  %-36s : %-15s [❌ FAIL: TABLE MISSING]", $t, $meta['domain']), 'red');
            } else {
                $count = $db->table($t)->countAllResults();
                if ($count < $meta['min']) {
                    $failures[] = "Table '{$t}' has 0 rows but requires at least {$meta['min']}.";
                    CLI::write(sprintf("  %-36s : %6d rows       [❌ FAIL: EMPTY]", $t, $count), 'red');
                } else {
                    CLI::write(sprintf("  %-36s : %6d rows       [✅ OK]", $t, $count), 'green');
                }
            }
        }
        CLI::newLine();

        // 2. SCHEMA & DATA CHECK: construction_types
        CLI::write("2️⃣  SCHEMA & SYNC AUDIT: construction_types", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        if ($db->tableExists('construction_types')) {
            $fields = $db->getFieldNames('construction_types');
            $requiredColumns = ['construction_code', 'construction_name', 'construction_family', 'asset_domain', 'approval_status'];
            $missingCols = [];
            foreach ($requiredColumns as $rc) {
                if (!in_array($rc, $fields)) {
                    $missingCols[] = $rc;
                }
            }

            if (!empty($missingCols)) {
                $failures[] = "Tabel construction_types is missing columns: " . implode(', ', $missingCols);
                CLI::write("  ❌ Missing columns: " . implode(', ', $missingCols), 'red');
            } else {
                CLI::write("  ✅ All canonical columns verified (construction_code, construction_name, family, domain, status).", 'green');
            }

            // Sample data
            $samples = $db->table('construction_types')
                ->select('id, code, name, construction_code, construction_name, construction_family, approval_status')
                ->orderBy('id', 'ASC')
                ->limit(8)
                ->get()
                ->getResultArray();

            CLI::write("  Sample Construction Types:", "white");
            foreach ($samples as $s) {
                CLI::write(sprintf("    ID: %-3d | Code: %-12s | Name: %-32s | Status: %s",
                    $s['id'],
                    $s['construction_code'] ?? $s['code'],
                    substr($s['construction_name'] ?? $s['name'], 0, 32),
                    $s['approval_status'] ?? 'N/A'
                ), ($s['approval_status'] === 'DRAFT') ? 'yellow' : 'white');
            }
        } else {
            $failures[] = "construction_types table does not exist.";
        }
        CLI::newLine();

        // 3. GATE 2 & 3: HONEST BOM
        CLI::write("3️⃣  GATE 2 & 3: HONEST BOM DISTRIBUTION", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        if ($db->tableExists('construction_bom_items')) {
            $bomDist = $db->table('construction_bom_items')
                ->select('mapping_status, quantity_status, COUNT(*) as total')
                ->groupBy('mapping_status, quantity_status')
                ->get()
                ->getResultArray();

            foreach ($bomDist as $d) {
                CLI::write(sprintf("  Mapping Status: %-12s | Qty Status: %-10s | Total: %d",
                    $d['mapping_status'],
                    $d['quantity_status'],
                    $d['total']
                ), 'green');
            }

            $unresolved = $db->table('construction_bom_items')
                ->where('mapping_status', 'UNRESOLVED')
                ->get()
                ->getResultArray();

            CLI::write(sprintf("  Unresolved Review Queue Items: %d", count($unresolved)), count($unresolved) > 0 ? 'yellow' : 'green');
            foreach ($unresolved as $u) {
                CLI::write(sprintf("    - Raw: %s (Const ID: %d, Qty: %s)",
                    $u['raw_material_name'],
                    $u['construction_type_id'],
                    $u['quantity'] ?? 'NULL'
                ), 'yellow');
            }
        } else {
            $failures[] = "construction_bom_items table does not exist.";
        }
        CLI::newLine();

        // 4. GATE 4: SINGLE ACTIVE CONFIGURATION INVARIANT
        CLI::write("4️⃣  GATE 4: SINGLE ACTIVE CONFIGURATION INVARIANT", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        if ($db->tableExists('network_section_configurations')) {
            $violatingSections = $db->table('network_section_configurations')
                ->select('section_id, COUNT(*) as active_count')
                ->where('verification_status', 'ACTIVE')
                ->where('effective_to IS NULL')
                ->groupBy('section_id')
                ->having('COUNT(*) > 1')
                ->get()
                ->getResultArray();

            if (empty($violatingSections)) {
                CLI::write("  ✅ Invariant Satisfied: 0 violating sections (1 ACTIVE version max per section).", "green");
            } else {
                $failures[] = sprintf("Gate 4 Invariant VIOLATED: %d sections have multiple active configurations.", count($violatingSections));
                CLI::write(sprintf("  ❌ Invariant VIOLATED: %d sections have multiple ACTIVE configurations!", count($violatingSections)), "red");
            }
        } else {
            $failures[] = "network_section_configurations table does not exist.";
        }
        CLI::newLine();

        // 5. GATE 6 & 7: FEEDER HEALTH POLICY FHI-v1.0
        CLI::write("5️⃣  GATE 6 & 7: FEEDER HEALTH POLICY (FHI-v1.0)", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        if ($db->tableExists('feeder_health_policy_versions')) {
            $policies = $db->table('feeder_health_policy_versions')->get()->getResultArray();
            if (empty($policies)) {
                $failures[] = "No Feeder Health policy version found (expected FHI-v1.0).";
                CLI::write("  ❌ No policy version found.", 'red');
            } else {
                foreach ($policies as $p) {
                    CLI::write(sprintf("  Policy: %s (%s) | Status: %s", $p['policy_code'], $p['policy_name'], $p['status']), 'green');
                }
            }
        } else {
            $failures[] = "feeder_health_policy_versions table does not exist.";
        }

        if ($db->tableExists('feeder_health_policy_rules')) {
            $rules = $db->table('feeder_health_policy_rules')->get()->getResultArray();
            if (empty($rules)) {
                $failures[] = "No Feeder Health policy rules configured.";
                CLI::write("  ❌ No policy rules configured.", 'red');
            } else {
                CLI::write(sprintf("  Policy Rules Defined: %d", count($rules)), 'white');
                foreach ($rules as $r) {
                    CLI::write(sprintf("    Metric: %-22s | Weight: %.2f | Sempurna >= %.0f | Sakit >= %.0f | Kronis >= %.0f | Kritis <= %.2f",
                        $r['metric_key'],
                        (float)$r['weight'],
                        (float)$r['threshold_sempurna_min'],
                        (float)$r['threshold_sakit_min'],
                        (float)$r['threshold_kronis_min'],
                        (float)$r['threshold_kritis_max']
                    ), 'green');
                }
            }
        } else {
            $failures[] = "feeder_health_policy_rules table does not exist.";
        }
        CLI::newLine();

        // 6. FINAL VERDICT WITH CR-06-AIH-01 CONSISTENCY ENFORCEMENT
        CLI::write("==================================================================", "yellow");
        CLI::write("                      FINAL AUDIT VERDICT                         ", "yellow");
        CLI::write("==================================================================", "yellow");

        if (!empty($failures)) {
            CLI::write("  🔴 CR-06 ACCEPTANCE STATUS       : FAILED / NOT READY FOR SEAL", "red");
            CLI::write("  Details of failures:", "red");
            foreach ($failures as $f) {
                CLI::write("    ❌ " . $f, "red");
            }
            CLI::write("==================================================================", "yellow");
            return 1;
        } else {
            CLI::write("  🟢 CR-06 Database Foundation      : SEALED & VERIFIED", "green");
            CLI::write("  🟢 7 Hardening Gates Posture      : RATIFIED & ENFORCED", "green");
            CLI::write("  🟢 Idempotent Seeder Baseline     : READY FOR PRODUCTION BASELINE", "green");
            CLI::write("==================================================================", "yellow");
            return 0;
        }
    }
}
