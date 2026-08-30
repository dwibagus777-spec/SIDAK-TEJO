<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\CanonicalFeederAssetResolutionService;

/**
 * Phase AR-01 Phase 3: Multi-Source Evidence Mining & Dry-Run Resolution Command (Strictly Read-Only)
 * Usage: php spark audit:ar01-evidence [feederId] [--min-score=85]
 */
class AuditAr01EvidenceCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'audit:ar01-evidence';
    protected $description = 'Phase AR-01 Phase 3: Multi-Signal Evidence Mining and Dry-Run Resolution Impact Simulation (Strictly Read-Only)';

    protected $arguments = [
        'feeder' => 'The Feeder ID to mine evidence for (default: 1)',
    ];
    protected $options = [
        '--min-score' => 'Minimum confidence score threshold for high-confidence queue (default: 85)',
    ];

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $resolver = new CanonicalFeederAssetResolutionService($db);

        $feederId = 1;
        $minScore = 85.0;

        foreach ($params as $p) {
            if (str_starts_with($p, '--min-score=')) {
                $minScore = (float)substr($p, 12);
            } elseif (is_numeric($p)) {
                $feederId = (int)$p;
            }
        }
        if ($optScore = CLI::getOption('min-score')) {
            $minScore = (float)$optScore;
        }

        CLI::write("\n==================================================================", 'yellow');
        CLI::write("    AR-01 PHASE 3: MULTI-SIGNAL EVIDENCE MINING & DRY-RUN AUDIT   ", 'yellow');
        CLI::write("    PILOT FEEDER: PYL-001 (STRICTLY READ-ONLY / ZERO MUTATION)   ", 'yellow');
        CLI::write("==================================================================\n", 'yellow');

        $result = $resolver->mineCandidateEvidence($feederId, ['min_score' => $minScore]);

        if (!$result['success']) {
            CLI::error("ERROR: " . ($result['error'] ?? 'Gagal melakukan penambangan bukti multi-sinyal.'));
            return 1;
        }

        $tf = $result['target_feeder'];
        $ts = $result['tier_summary'];
        $rq = $result['review_queue'];
        $all = $result['all_scored_assets'];

        $softDeletedCount = 0;
        if ($db->fieldExists('deleted_at', 'assets')) {
            $softDeletedCount = $db->table('assets')->where('deleted_at IS NOT NULL')->countAllResults();
        }

        // 1. TARGET FEEDER
        CLI::write("1. TARGET FEEDER & CR-06F ACTIVE TOPOLOGY CONTEXT", 'cyan');
        CLI::write("------------------------------------------------------------------");
        CLI::write("  Feeder ID                         : {$tf['id']}");
        CLI::write("  Feeder Code                       : {$tf['kode_penyulang']}");
        CLI::write("  Feeder Name                       : {$tf['nama_penyulang']}");
        CLI::write("  Active Configured Sections        : {$result['active_sections']}");
        CLI::write("  Active Grid Scope Scanned         : {$result['total_assets_scanned']} assets");
        if ($softDeletedCount > 0) {
            CLI::write("  Quarantined Historical Records    : {$softDeletedCount} records (Excluded from active resolution)");
        }

        // 2. MULTI-SIGNAL EVIDENCE SUMMARY
        CLI::write("\n2. MULTI-SIGNAL EVIDENCE MINING & CONFIDENCE TIERS", 'cyan');
        CLI::write("------------------------------------------------------------------");
        CLI::write("  • CANONICAL (100% Direct Unbroken Chain) : " . CLI::color("{$ts['CANONICAL']} assets", 'green'));
        CLI::write("  • STRONG (Score >= 85%, Corroborated)    : " . CLI::color("{$ts['STRONG']} assets", 'green'));
        CLI::write("  • SUPPORTING (60% <= Score < 85%)        : " . CLI::color("{$ts['SUPPORTING']} assets", 'yellow'));
        CLI::write("  • INSUFFICIENT (Score < 60%, Unassigned) : " . CLI::color("{$ts['INSUFFICIENT']} assets", 'red'));
        CLI::write("  • CONFLICT (Alien Feeder Contradiction)  : " . CLI::color("{$ts['CONFLICT']} assets", 'light_cyan'));

        // 3. HIGH-CONFIDENCE CANDIDATE REVIEW QUEUE
        CLI::write("\n3. HIGH-CONFIDENCE CANDIDATE REVIEW QUEUE (CANONICAL & STRONG)", 'cyan');
        CLI::write("------------------------------------------------------------------");
        if (empty($rq)) {
            CLI::write("  No high-confidence candidates found with current evidence threshold (Score >= {$minScore}%).", 'yellow');
            if ($ts['INSUFFICIENT'] > 0) {
                CLI::write("  All {$ts['INSUFFICIENT']} active unassigned asset(s) remain strictly UNRESOLVED per Invariant AR-01-A.", 'white');
            } else {
                CLI::write("  0 unassigned assets found in active scope (Quarantined records excluded per AR-01 Phase 4A).", 'white');
            }
        } else {
            CLI::write("  Found " . count($rq) . " high-confidence candidate asset(s):\n");
            foreach ($rq as $c) {
                $scoreCol = $c['confidence_score'] >= 85 ? 'green' : 'yellow';
                CLI::write("  • [ID: {$c['asset_id']}] Code: '{$c['kode_asset']}' | Name: '{$c['nama_asset']}'");
                CLI::write("    ├─ Confidence Score   : " . CLI::color("{$c['confidence_score']}% [{$c['confidence_tier']}]", $scoreCol));
                CLI::write("    ├─ Proposed Section   : " . ($c['proposed_section'] ?? 'Unassigned Section'));
                CLI::write("    ├─ Signal Breakdown   : Code/Name: {$c['evidence_signals']['signal_1_code_name']} | Findings: {$c['evidence_signals']['signal_2_findings']} | Geo: {$c['evidence_signals']['signal_3_geo_corridor']} | BOM: {$c['evidence_signals']['signal_4_construction']} | Lineage: {$c['evidence_signals']['signal_5_lineage']}");
                if (!empty($c['evidence_notes'])) {
                    CLI::write("    └─ Corroborations     : " . implode('; ', $c['evidence_notes']));
                }
                CLI::write("");
            }
        }

        // 4. SUPPORTING EVIDENCE SAMPLES
        $supporting = array_values(array_filter($all, fn($a) => $a['confidence_tier'] === CanonicalFeederAssetResolutionService::CONFIDENCE_SUPPORTING));
        if (!empty($supporting)) {
            CLI::write("\n4. SUPPORTING EVIDENCE QUEUE (Hold for Field Survey / Secondary Corroboration)", 'cyan');
            CLI::write("------------------------------------------------------------------");
            CLI::write("  Total Supporting Assets : " . count($supporting) . " assets (Samples below):");
            foreach (array_slice($supporting, 0, 5) as $s) {
                CLI::write("  • [ID: {$s['asset_id']}] '{$s['kode_asset']}' ('{$s['nama_asset']}') - Score: {$s['confidence_score']}% | Notes: " . implode('; ', $s['evidence_notes']));
            }
        }

        // 5. ALIEN & INSUFFICIENT ISOLATION
        $conflictAssets = array_filter($all, fn($a) => $a['confidence_tier'] === CanonicalFeederAssetResolutionService::CONFIDENCE_CONFLICT);
        $alienByFeeder = [];
        foreach ($conflictAssets as $ca) {
            $fId = $ca['existing_feeder_id'] ?? 'Unknown';
            $alienByFeeder[$fId] = ($alienByFeeder[$fId] ?? 0) + 1;
        }

        CLI::write("\n5. NON-CANDIDATE & ALIEN FEEDER ISOLATION", 'cyan');
        CLI::write("------------------------------------------------------------------");
        if (empty($alienByFeeder)) {
            CLI::write("  • Alien Feeder Assets                   : 0 assets (No cross-feeder foreign keys)");
        } else {
            foreach ($alienByFeeder as $fId => $cnt) {
                CLI::write("  • Feeder #{$fId} Registered Assets             : {$cnt} assets -> Status: CONFLICT / ISOLATED");
            }
        }
        CLI::write("  • Generic / Unassigned Active (Score < 60%) : {$ts['INSUFFICIENT']} assets -> Status: UNRESOLVED");
        if ($softDeletedCount > 0) {
            CLI::write("  • Quarantined Historical Dataset        : {$softDeletedCount} records -> Status: QUARANTINED (Safe Soft-Delete)");
        }

        // 6. DRY-RUN RESOLUTION IMPACT SIMULATION
        $approvedCandidateIds = array_column($rq, 'asset_id');
        $sim = $resolver->simulateResolutionImpact($feederId, $approvedCandidateIds);

        CLI::write("\n6. DRY-RUN RESOLUTION IMPACT SIMULATION (Non-Destructive)", 'cyan');
        CLI::write("------------------------------------------------------------------");
        CLI::write("  Simulated Approved Candidates     : {$sim['simulated_approved_candidates']}");
        CLI::write("  Simulated Resolved Assets         : {$sim['simulated_resolved_assets']}");
        CLI::write("  Total Active Grid Scope           : {$sim['total_active_grid_assets']}");
        CLI::write("  Simulated Resolution Ratio        : {$sim['simulated_resolution_ratio']}%");
        CLI::write("  Simulated Average AHS             : {$sim['simulated_average_ahs']}");
        CLI::write("  Projected FHI Pillar 2 State      : " . CLI::color("{$sim['simulated_pillar_2_health']}", 'yellow'));
        CLI::write("  Projected FHI Classification      : {$sim['projected_fhi_state']}");
        CLI::write("  Non-Destructive Guarantee         : " . CLI::color("{$sim['non_destructive_guarantee']}", 'green'));

        CLI::write("\n==================================================================", 'green');
        CLI::write("🟢 EVIDENCE MINING & DRY-RUN AUDIT COMPLETE: ZERO WRITES PERFORMED", 'green');
        CLI::write("   No database mutation applied. Invariant AR-01-A & AR-01-H intact.", 'green');
        CLI::write("==================================================================\n", 'green');

        return 0;
    }
}
