<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use App\Services\AssetMassIngestionService;
use App\Services\AssetTopologyReconciliationService;
use App\Services\MassAssetCommitService;

/**
 * CR-ASSET-01 Spark Command: Mass Asset Ingestion & Topology Reconciliation Engine
 */
class ReconcileMassAssetsCommand extends BaseCommand
{
    protected $group       = 'Asset';
    protected $name        = 'asset:reconcile-mass';
    protected $description = 'Executes bulk asset ingestion, identity reconciliation, and candidate topology construction.';
    protected $usage       = 'asset:reconcile-mass [--dry-run] [--plan=<path> --execute] [--file=<path>] [--status]';
    protected $options     = [
        '--dry-run'  => 'Simulate ingestion and generate cryptographic plan without mutating DB',
        '--execute'  => 'Execute previously generated preflight plan atomically',
        '--plan'     => 'Path to plan JSON file (required with --execute)',
        '--file'     => 'Path to specific CSV file (defaults to all Template_Import_*.csv in writable/)',
        '--status'   => 'Display current asset and transline inventory across all feeders',
    ];

    public function run(array $params)
    {
        $db = Database::connect();
        $isStatus  = CLI::getOption('status') !== null;
        $isDryRun  = CLI::getOption('dry-run') !== null;
        $isExecute = CLI::getOption('execute') !== null;
        $planFile  = CLI::getOption('plan') ?? ($params['plan'] ?? null);
        $csvFile   = CLI::getOption('file') ?? ($params['file'] ?? null);

        // Also check if any positional param is a json or csv file
        foreach ($params as $p) {
            if (is_string($p)) {
                if (str_ends_with(strtolower($p), '.json') && !$planFile) {
                    $planFile = $p;
                } elseif (str_ends_with(strtolower($p), '.csv') && !$csvFile) {
                    $csvFile = $p;
                }
            }
        }

        // If execute is requested but planFile is not specified, auto-detect the latest plan file
        if ($isExecute && !$planFile) {
            $plans = glob(WRITEPATH . 'audits/plans/CR-ASSET01-PLAN-*.json');
            if (!empty($plans)) {
                usort($plans, fn($a, $b) => filemtime($b) - filemtime($a));
                $planFile = $plans[0];
                CLI::write("Auto-detected latest preflight plan: " . basename($planFile), 'light_cyan');
            }
        }

        CLI::write("==================================================================", 'cyan');
        CLI::write("CR-ASSET-01: MASS ASSET DATA INGESTION & RECONCILIATION ENGINE", 'cyan');
        CLI::write("==================================================================", 'cyan');

        if ($isStatus) {
            $this->showStatus($db);
            return;
        }

        if ($isExecute) {
            if (!$planFile) {
                CLI::error("Error: --execute requires --plan=<path-to-plan.json>");
                return;
            }
            $this->executePlan($db, $planFile);
            return;
        }

        if ($isDryRun || (!$isExecute && !$isStatus)) {
            $this->runPreflight($db, $csvFile);
            return;
        }
    }

    protected function showStatus($db): void
    {
        CLI::write("\n--- CURRENT DATABASE NETWORK INVENTORY ---", 'yellow');

        $tablePenyulang = $db->tableExists('db_penyulang') ? 'db_penyulang' : 'penyulang';
        $feeders = $db->table($tablePenyulang)->get()->getResultArray();

        $rows = [];
        $totalAssets = 0;
        $totalTranslines = 0;

        foreach ($feeders as $f) {
            $fId = (int)$f['id'];
            $assetCnt = $db->table('assets')
                ->where('penyulang_id', $fId)
                ->where('deleted_at IS NULL', null, false)
                ->countAllResults();

            $tlCnt = $db->tableExists('gis_translines')
                ? $db->table('gis_translines')
                    ->where('penyulang_id', $fId)
                    ->where('deleted_at IS NULL', null, false)
                    ->countAllResults()
                : 0;

            $rows[] = [
                $fId,
                $f['kode_penyulang'] ?? '-',
                $f['nama_penyulang'] ?? '-',
                $assetCnt,
                $tlCnt,
            ];

            $totalAssets += $assetCnt;
            $totalTranslines += $tlCnt;
        }

        CLI::table($rows, ['ID', 'KODE', 'NAMA PENYULANG', 'TOTAL ASSETS', 'ACTIVE TRANSLINES']);
        CLI::write("\nTOTAL ASSETS IN DATABASE    : {$totalAssets}", 'green');
        CLI::write("TOTAL TRANSLINES IN DATABASE: {$totalTranslines}\n", 'green');
    }

    protected function runPreflight($db, ?string $csvFile): void
    {
        CLI::write("\n[PHASE 1] Preflight Simulation & Plan Generation (Dry-Run)", 'yellow');

        $filesToProcess = [];
        if ($csvFile) {
            if (!file_exists($csvFile)) {
                CLI::error("Specified file does not exist: {$csvFile}");
                return;
            }
            $filesToProcess[] = $csvFile;
        } else {
            // Auto-discover in writable/
            $pattern = WRITEPATH . 'Template_Import_*.csv';
            $filesToProcess = glob($pattern) ?: [];
        }

        if (empty($filesToProcess)) {
            CLI::error("No CSV source files found in " . WRITEPATH);
            return;
        }

        CLI::write("Source Files to Process (" . count($filesToProcess) . "):");
        foreach ($filesToProcess as $f) {
            CLI::write("  - " . basename($f));
        }

        $ingestionService = new AssetMassIngestionService($db);
        $topologyService  = new AssetTopologyReconciliationService($db);
        $commitService    = new MassAssetCommitService($db);

        $batches = [];
        $combinedAutoAccept = [];

        foreach ($filesToProcess as $f) {
            CLI::write("\nScanning " . basename($f) . "...");
            $batchResult = $ingestionService->ingestCsv($f);
            $batches[] = $batchResult;

            CLI::write("  Scanned Rows : {$batchResult['total_rows_scanned']}");
            CLI::write("  AUTO_ACCEPT  : {$batchResult['summary']['auto_accept_count']}", 'green');
            CLI::write("    - New      : {$batchResult['breakdown']['new']}");
            CLI::write("    - Enrich   : {$batchResult['breakdown']['enrich']}");
            CLI::write("    - Skip     : {$batchResult['breakdown']['skip']}");
            CLI::write("  AUTO_REVIEW  : {$batchResult['summary']['auto_review_count']}", 'yellow');
            CLI::write("    - Conflict : {$batchResult['breakdown']['conflict']}");
            CLI::write("    - Collision: {$batchResult['breakdown']['collision']}");
            CLI::write("  QUARANTINE   : {$batchResult['summary']['quarantine_count']}", 'red');
            CLI::write("    - Invalid  : {$batchResult['breakdown']['invalid_gps']}");
            CLI::write("    - Duplicate: {$batchResult['breakdown']['duplicate']}");

            foreach ($batchResult['auto_accept'] as $acc) {
                $combinedAutoAccept[] = $acc;
            }
        }

        CLI::write("\n[PHASE 2] Decoupled Topology Candidate Reconciliation...", 'cyan');
        $topologyResult = $topologyService->reconcileTopology($combinedAutoAccept);

        CLI::write("Topology Summary:");
        CLI::write("  Total Assets Evaluated   : {$topologyResult['summary']['total_assets']}");
        CLI::write("  Candidate Translines (L2): {$topologyResult['summary']['candidate_translines_cnt']}", 'green');
        CLI::write("  Isolated Nodes (0 edges) : {$topologyResult['summary']['isolated_assets']}", 'yellow');
        CLI::write("  Review Candidates (Held) : {$topologyResult['summary']['review_candidates_cnt']}", 'yellow');

        CLI::write("\n[PHASE 3] Cryptographic Plan Generation...", 'cyan');
        $planMeta = $commitService->generatePlan($batches, $topologyResult);

        CLI::write("\n✓ PREFLIGHT PLAN SUCCESSFULLY GENERATED", 'green');
        CLI::write("  Plan ID     : {$planMeta['plan_id']}");
        CLI::write("  Fingerprint : {$planMeta['fingerprint']}");
        CLI::write("  File        : {$planMeta['plan_file']}");
        CLI::write("\nTo atomically execute this plan, run:\n", 'yellow');
        CLI::write("  php spark asset:reconcile-mass --plan=\"{$planMeta['plan_file']}\" --execute\n", 'light_cyan');
    }

    protected function executePlan($db, string $planFile): void
    {
        CLI::write("\n[EXECUTE] Committing Plan to Database...", 'yellow');
        CLI::write("Plan File: {$planFile}");

        $commitService = new MassAssetCommitService($db);

        try {
            $receipt = $commitService->executePlan($planFile);

            CLI::write("\n✓ PLAN EXECUTION COMMITTED SUCCESSFULLY", 'green');
            CLI::write("  Receipt ID           : {$receipt['receipt_id']}");
            CLI::write("  Executed At          : {$receipt['executed_at']}");
            CLI::write("  Assets Inserted      : {$receipt['mutations']['assets_inserted']}", 'green');
            CLI::write("  Assets Enriched      : {$receipt['mutations']['assets_enriched']}", 'cyan');
            CLI::write("  Assets Skipped       : {$receipt['mutations']['assets_skipped']}");
            CLI::write("  Translines Inserted  : {$receipt['mutations']['translines_inserted']}", 'green');
            CLI::write("  Isolated Nodes       : {$receipt['mutations']['isolated_nodes_count']}", 'yellow');
            CLI::write("  Quarantine Rows      : {$receipt['mutations']['quarantine_count']}", 'red');
            CLI::write("  Review Queue Held    : {$receipt['mutations']['auto_review_count']}", 'yellow');
            CLI::write("  Receipt Saved To     : {$receipt['receipt_file']}\n");

        } catch (\Throwable $e) {
            CLI::error("\n✗ EXECUTION ABORTED AND ROLLED BACK:");
            CLI::error("  " . $e->getMessage() . "\n");
        }
    }
}
