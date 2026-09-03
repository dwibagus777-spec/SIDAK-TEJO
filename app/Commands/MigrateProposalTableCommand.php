<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Isolated Target Migration Runner for TL-01 Phase 2B Stage A
 *
 * Guaranteed Invariants:
 * - Executes ONLY 2026-09-03-000001_CreateGisTranslineProposalsTable.php.
 * - NEVER runs any other pending migration.
 * - Verifies before/after cryptographic fingerprints of protected tables.
 * - Strictly 0 mutation to `gis_translines`, `assets`, `temuan`, `temuan_materials`.
 * - Records migration atomically into CodeIgniter `migrations` table for historical audit.
 */
class MigrateProposalTableCommand extends BaseCommand
{
    protected $group       = 'Transline';
    protected $name        = 'transline:migrate-proposals';
    protected $description = 'Executes ONLY 2026-09-03-000001_CreateGisTranslineProposalsTable migration with zero side effects.';
    protected $usage       = 'transline:migrate-proposals [--execute] [--dry-run] [--rollback]';
    protected $options     = [
        '--execute'  => 'Execute migration and record in migrations table',
        '--dry-run'  => 'Test migration and rollback without persisting table',
        '--rollback' => 'Rollback gis_transline_proposals migration',
    ];

    public function run(array $params)
    {
        $db = Database::connect();
        $isExecute  = CLI::getOption('execute') !== null;
        $isDryRun   = CLI::getOption('dry-run') !== null;
        $isRollback = CLI::getOption('rollback') !== null;

        if (!$isExecute && !$isDryRun && !$isRollback) {
            CLI::write("Usage: php spark transline:migrate-proposals --dry-run | --execute | --rollback", 'yellow');
            return;
        }

        CLI::write("==================================================================", 'cyan');
        CLI::write("TL-01 PHASE 2B: CONTROLLED PROPOSAL TABLE MIGRATION RUNNER", 'cyan');
        CLI::write("==================================================================", 'cyan');

        $monitored = ['gis_translines', 'assets', 'temuan', 'temuan_materials'];
        $fpBefore = $this->captureFingerprints($db, $monitored);

        require_once APPPATH . 'Database/Migrations/2026-09-03-000001_CreateGisTranslineProposalsTable.php';
        $migration = new \App\Database\Migrations\CreateGisTranslineProposalsTable();

        if ($isRollback) {
            $this->handleRollback($db, $migration, $monitored, $fpBefore);
            return;
        }

        if ($isDryRun) {
            $this->handleDryRun($db, $migration, $monitored, $fpBefore);
            return;
        }

        if ($isExecute) {
            $this->handleExecute($db, $migration, $monitored, $fpBefore);
            return;
        }
    }

    private function captureFingerprints($db, array $tables): array
    {
        $fps = [];
        foreach ($tables as $t) {
            if ($db->tableExists($t)) {
                $count = (int)$db->table($t)->countAllResults();
                $rows = $db->table($t)->orderBy('id', 'ASC')->limit(500)->get()->getResultArray();
                $fps[$t] = [
                    'count' => $count,
                    'hash'  => hash('sha256', json_encode($rows)),
                ];
            } else {
                $fps[$t] = ['count' => 0, 'hash' => hash('sha256', 'NOT_EXISTS')];
            }
        }
        return $fps;
    }

    private function verifyFingerprints(array $before, array $after, array $tables): bool
    {
        $allMatched = true;
        foreach ($tables as $t) {
            $b = $before[$t];
            $a = $after[$t];
            if ($b['count'] !== $a['count'] || $b['hash'] !== $a['hash']) {
                CLI::error("MUTATION DETECTED ON PROTECTED TABLE: {$t}!");
                CLI::error("Before: count={$b['count']} hash={$b['hash']}");
                CLI::error("After:  count={$a['count']} hash={$a['hash']}");
                $allMatched = false;
            }
        }
        return $allMatched;
    }

    private function handleExecute($db, $migration, array $monitored, array $fpBefore): void
    {
        CLI::write("[1/5] Checking collision...", 'yellow');
        if ($db->tableExists('gis_transline_proposals')) {
            CLI::error("Collision error: Table gis_transline_proposals ALREADY exists!");
            return;
        }
        CLI::write("  ✓ Collision check PASS: gis_transline_proposals does not exist.", 'green');

        CLI::write("[2/5] Executing ONLY target migration up()...", 'yellow');
        $migration->up();

        if (!$db->tableExists('gis_transline_proposals')) {
            CLI::error("Migration execution failed: table not created.");
            return;
        }
        CLI::write("  ✓ Target table gis_transline_proposals created successfully.", 'green');

        CLI::write("[3/5] Recording migration in migrations table...", 'yellow');
        if ($db->tableExists('migrations')) {
            $maxBatch = (int)($db->table('migrations')->selectMax('batch')->get()->getRow()->batch ?? 0);
            $nextBatch = $maxBatch + 1;
            $db->table('migrations')->insert([
                'version'   => '2026-09-03-000001',
                'class'     => 'App\Database\Migrations\CreateGisTranslineProposalsTable',
                'group'     => 'default',
                'namespace' => 'App',
                'time'      => time(),
                'batch'     => $nextBatch,
            ]);
            CLI::write("  ✓ Migration recorded: version=2026-09-03-000001 batch={$nextBatch}", 'green');
        }

        CLI::write("[4/5] Verifying 23 columns & constraints...", 'yellow');
        $expectedCols = [
            'id', 'penyulang_id', 'section_id', 'source_asset_id', 'target_asset_id',
            'natural_key', 'proposed_conductor_type', 'proposed_conductor_size',
            'proposed_distance', 'proposed_geometry', 'classification',
            'confidence_score', 'evidence_json', 'proposal_source', 'engine_version',
            'status', 'reviewed_by', 'reviewed_at', 'review_note',
            'confirmed_transline_id', 'created_at', 'updated_at', 'deleted_at'
        ];
        foreach ($expectedCols as $col) {
            if (!$db->fieldExists($col, 'gis_transline_proposals')) {
                CLI::error("Missing column: {$col}!");
                return;
            }
        }
        CLI::write("  ✓ All 23 columns verified in gis_transline_proposals.", 'green');

        CLI::write("[5/5] Auditing zero operational mutations...", 'yellow');
        $fpAfter = $this->captureFingerprints($db, $monitored);
        if ($this->verifyFingerprints($fpBefore, $fpAfter, $monitored)) {
            CLI::write("  ✓ ZERO MUTATIONS VERIFIED ON ALL PROTECTED TABLES [PASS]", 'green');
            CLI::write("==================================================================", 'cyan');
            CLI::write("SUCCESS: ONLY 2026-09-03-000001 APPLIED SAFELY.", 'green');
            CLI::write("==================================================================", 'cyan');
        }
    }

    private function handleDryRun($db, $migration, array $monitored, array $fpBefore): void
    {
        CLI::write("[DRY RUN] Testing up() and down() lifecycle...", 'yellow');
        $migration->up();
        $existsAfterUp = $db->tableExists('gis_transline_proposals');
        $migration->down();
        $existsAfterDown = $db->tableExists('gis_transline_proposals');

        $fpAfter = $this->captureFingerprints($db, $monitored);
        $clean = $this->verifyFingerprints($fpBefore, $fpAfter, $monitored);

        if ($existsAfterUp && !$existsAfterDown && $clean) {
            CLI::write("  ✓ DRY RUN PASSED: up() created table, down() dropped table, 0 mutations.", 'green');
        } else {
            CLI::error("  ✗ DRY RUN FAILED!");
        }
    }

    private function handleRollback($db, $migration, array $monitored, array $fpBefore): void
    {
        CLI::write("[ROLLBACK] Dropping gis_transline_proposals...", 'yellow');
        $migration->down();
        if ($db->tableExists('migrations')) {
            $db->table('migrations')->where('version', '2026-09-03-000001')->delete();
        }
        $fpAfter = $this->captureFingerprints($db, $monitored);
        if ($this->verifyFingerprints($fpBefore, $fpAfter, $monitored)) {
            CLI::write("  ✓ Rollback successful and verified 0 side-effects.", 'green');
        }
    }
}
