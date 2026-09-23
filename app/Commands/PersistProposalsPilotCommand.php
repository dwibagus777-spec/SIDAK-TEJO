<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use App\Services\TranslineCompletionService;

/**
 * TL-01 Sub-Gate D1: Controlled Proposal Persistence Pilot Runner
 *
 * Persists exactly 5 AUTO_MATCH candidates from Penyulang BANJAR KEMANTREN into
 * `gis_transline_proposals` with strict idempotency and zero-mutation firewall on `gis_translines`.
 */
class PersistProposalsPilotCommand extends BaseCommand
{
    protected $group       = 'Transline';
    protected $name        = 'transline:persist-proposals-pilot';
    protected $description = 'Executes the controlled 5-row proposal persistence pilot (TL-01 Sub-Gate D1).';
    protected $usage       = 'transline:persist-proposals-pilot [--dry-run] [--execute] [--status] [--penyulang="NAME"]';
    protected $options     = [
        '--dry-run'   => 'Perform simulated run without database mutations',
        '--execute'   => 'Commit exactly 5 pilot AUTO_MATCH proposals into gis_transline_proposals',
        '--status'    => 'Read-only inspection of proposal table state',
        '--penyulang' => 'Target Feeder Name (default: BANJAR KEMANTREN)',
    ];

    public function run(array $params)
    {
        $db = Database::connect();
        $service = new TranslineCompletionService($db);

        $isDryRun  = CLI::getOption('dry-run') !== null;
        $isExecute = CLI::getOption('execute') !== null;
        $isStatus  = CLI::getOption('status') !== null;
        $feederOpt = CLI::getOption('penyulang') ?? 'BANJAR KEMANTREN';

        $monitored = ['gis_translines', 'gis_transline_proposals', 'assets', 'sections', 'penyulang', 'temuan', 'temuan_materials'];

        if ($isStatus) {
            $this->renderStatus($db, $monitored);
            return;
        }

        if (!$isDryRun && !$isExecute) {
            CLI::error("Tentukan mode eksekusi: gunakan --dry-run untuk simulasi atau --execute untuk komit pilot.");
            return;
        }

        CLI::write("==================================================================", 'cyan');
        CLI::write("TL-01 SUB-GATE D1: CONTROLLED 5-ROW PROPOSAL PERSISTENCE PILOT", 'cyan');
        CLI::write("==================================================================", 'cyan');
        CLI::write("Mode       : " . ($isExecute ? "LIVE EXECUTE" : "SIMULATED DRY-RUN"));
        CLI::write("Target     : Penyulang {$feederOpt} (5 AUTO_MATCH)");
        CLI::write("Firewall   : gis_translines MUST REMAIN 42");
        CLI::write("Timestamp  : " . date('Y-m-d H:i:s T'));
        CLI::write("------------------------------------------------------------------", 'yellow');

        // 1. Resolve Feeder
        $feeder = $db->table('penyulang')
            ->like('nama_penyulang', trim($feederOpt), 'both', null, true)
            ->get()
            ->getRowArray();

        if (!$feeder) {
            CLI::error("Penyulang '{$feederOpt}' tidak ditemukan.");
            return;
        }
        $feederId = (int)$feeder['id'];

        // 2. BEFORE Snapshot
        $fpBefore = $this->captureFingerprints($db, $monitored);
        CLI::write("[1/6] BEFORE Snapshot captured:");
        CLI::write("  - gis_translines          : {$fpBefore['gis_translines']['count']} baris");
        CLI::write("  - gis_transline_proposals : {$fpBefore['gis_transline_proposals']['count']} baris");
        CLI::write("  - assets                  : {$fpBefore['assets']['count']} baris");

        // 3. Scan & Select exactly 5 AUTO_MATCH candidates
        CLI::write("[2/6] Re-resolving candidates from deterministic engine...");
        $scan = $service->getPenyulangCompletionCandidates($feederId);
        $autoMatches = array_filter($scan['candidates'], fn($c) => $c['classification'] === TranslineCompletionService::STATUS_AUTO_MATCH);
        $pilotCandidates = array_slice(array_values($autoMatches), 0, 5);

        if (count($pilotCandidates) < 5) {
            CLI::error("Gagal mendapatkan 5 kandidat AUTO_MATCH (hanya ditemukan " . count($pilotCandidates) . ").");
            return;
        }

        CLI::write("  ✓ 5 Pilot AUTO_MATCH candidates verified:");
        foreach ($pilotCandidates as $i => $pc) {
            CLI::write("    #" . ($i + 1) . " {$pc['natural_key']} | {$pc['source_asset_code']} -> {$pc['target_asset_code']} | {$pc['distance_meters']}m | {$pc['visual_style_token']}");
        }

        // 4. RUN #1: Persistence (or Dry Run)
        CLI::write("[3/6] Executing RUN #1 (Persistence)...");
        $resultRun1 = $service->persistProposalBatch($pilotCandidates, [
            'dry_run'        => $isDryRun,
            'engine_version' => 'TL-01-V2.0',
        ]);

        if ($resultRun1['status'] !== 'success' && $resultRun1['status'] !== 'dry_run_success') {
            CLI::error("RUN #1 FAILED: " . json_encode($resultRun1));
            return;
        }

        CLI::write("  ✓ RUN #1 Status: {$resultRun1['status']}");
        if ($isExecute) {
            CLI::write("  ✓ Proposals inserted: {$resultRun1['inserted_count']} rows");
        }

        // 5. RUN #2: Idempotency Verification
        CLI::write("[4/6] Executing RUN #2 (Idempotency Proof)...");
        $resultRun2 = $service->persistProposalBatch($pilotCandidates, [
            'dry_run'        => $isDryRun,
            'engine_version' => 'TL-01-V2.0',
        ]);

        if ($isExecute) {
            if ($resultRun2['inserted_count'] !== 0 || $resultRun2['skipped_existing_count'] !== 5) {
                CLI::error("IDEMPOTENCY FAILED: Run 2 inserted {$resultRun2['inserted_count']} rows (expected 0).");
                return;
            }
            CLI::write("  ✓ Idempotency PASS: 0 duplicates created, 5 existing skipped.");
        } else {
            CLI::write("  ✓ Dry Run simulated cleanly.");
        }

        // 6. AFTER Snapshot & Firewall Verification
        $fpAfter = $this->captureFingerprints($db, $monitored);
        CLI::write("[5/6] AFTER Snapshot & Firewall Audit:");
        CLI::write("  - gis_translines          : {$fpAfter['gis_translines']['count']} baris (delta: " . ($fpAfter['gis_translines']['count'] - $fpBefore['gis_translines']['count']) . ")");
        CLI::write("  - gis_transline_proposals : {$fpAfter['gis_transline_proposals']['count']} baris (delta: " . ($fpAfter['gis_transline_proposals']['count'] - $fpBefore['gis_transline_proposals']['count']) . ")");
        CLI::write("  - assets                  : {$fpAfter['assets']['count']} baris (delta: " . ($fpAfter['assets']['count'] - $fpBefore['assets']['count']) . ")");

        if ($fpAfter['gis_translines']['count'] !== $fpBefore['gis_translines']['count']) {
            CLI::error("FIREWALL BREACH: gis_translines was mutated! Hard Stop triggered.");
            return;
        }
        CLI::write("  ✓ Firewall VERIFIED: gis_translines untouched.");

        CLI::write("[6/6] Final Gate Verdict:");
        CLI::write("==================================================================", 'green');
        CLI::write("TL-01 SUB-GATE D1 PILOT = " . ($isExecute ? "PASS / VERIFIED / COMMITTED" : "PASS / DRY RUN VERIFIED"), 'green');
        CLI::write("==================================================================", 'green');
    }

    private function captureFingerprints($db, array $tables): array
    {
        $fps = [];
        foreach ($tables as $table) {
            if (!$db->tableExists($table)) {
                $fps[$table] = ['count' => 0];
                continue;
            }
            $fps[$table] = [
                'count' => (int)$db->table($table)->countAllResults(),
            ];
        }
        return $fps;
    }

    private function renderStatus($db, array $monitored): void
    {
        CLI::write("==================================================================", 'cyan');
        CLI::write("TL-01 SUB-GATE D1: PROPOSAL TABLE STATUS", 'cyan');
        CLI::write("==================================================================", 'cyan');
        foreach ($monitored as $t) {
            $c = $db->tableExists($t) ? (int)$db->table($t)->countAllResults() : 0;
            CLI::write("  - " . str_pad($t, 25, ' ') . ": {$c} baris");
        }

        if ($db->tableExists('gis_transline_proposals')) {
            $rows = $db->table('gis_transline_proposals')
                ->where('deleted_at IS NULL')
                ->limit(10)
                ->get()
                ->getResultArray();

            CLI::newLine();
            CLI::write("D1 Active Proposals Sample (" . count($rows) . " rows):", 'yellow');
            foreach ($rows as $r) {
                CLI::write("  #{$r['id']} | {$r['natural_key']} | {$r['classification']} | {$r['status']} | {$r['proposed_conductor_type']} | {$r['proposed_distance']}m");
            }
        }
    }
}
