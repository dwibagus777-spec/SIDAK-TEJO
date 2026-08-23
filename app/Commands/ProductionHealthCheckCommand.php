<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\ProductionHealthCheckService;

/**
 * Production Health Check CLI Command (Wave 3 Phase PH-02)
 *
 * Command: php spark health:check
 *
 * Responsibilities:
 * - Unified 100% READ-ONLY observability runner for production operational health.
 * - Inspects Runtime, Security, Database, Migrations, 67 Constitutional Gates,
 *   Writable Storage, Log Health, and Git Release Baseline.
 * - Returns exit code 0 (Healthy/Degraded) or 1 (Unhealthy).
 */
class ProductionHealthCheckCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'health:check';
    protected $description = 'Performs a comprehensive read-only operational health and governance audit of the application.';

    public function run(array $params)
    {
        CLI::write("====================================================", "yellow");
        CLI::write("SIDAK TEJO ENTERPRISE v3.0.0 — PRODUCTION HEALTH CHECK", "yellow");
        CLI::write("====================================================", "yellow");
        CLI::newLine();

        $service = new ProductionHealthCheckService();
        $report  = $service->runFullHealthCheck();

        $dom = $report['domains'];

        // [1/8] Runtime Environment
        $this->printStepLine("1/8", "Runtime Environment      ", $dom['runtime']['status'], $dom['runtime']['detail'], $dom['runtime']['duration_ms'] ?? null);

        // [2/8] Runtime Security
        $this->printStepLine("2/8", "Runtime Security         ", $dom['security']['status'], $dom['security']['detail'], $dom['security']['duration_ms'] ?? null);

        // [3/8] Database Connectivity
        $this->printStepLine("3/8", "Database Connectivity    ", $dom['database']['status'], $dom['database']['detail'], $dom['database']['duration_ms'] ?? null);

        // [4/8] Migration Integrity
        $this->printStepLine("4/8", "Migration Integrity      ", $dom['migration']['status'], $dom['migration']['detail'], $dom['migration']['duration_ms'] ?? null);

        // [5/8] Constitutional Gates
        $this->printStepLine("5/8", "Constitutional Gates     ", $dom['constitutional_gates']['status'], $dom['constitutional_gates']['detail'], $dom['constitutional_gates']['duration_ms'] ?? null);

        // [6/8] Writable Storage
        $this->printStepLine("6/8", "Writable Storage Health  ", $dom['storage']['status'], $dom['storage']['detail'], $dom['storage']['duration_ms'] ?? null);

        // [7/8] Log Health
        $this->printStepLine("7/8", "Log Health               ", $dom['logs']['status'], $dom['logs']['detail'], $dom['logs']['duration_ms'] ?? null);

        // [8/8] Release Baseline
        $this->printStepLine("8/8", "Release Baseline         ", $dom['release_baseline']['status'], $dom['release_baseline']['detail'], $dom['release_baseline']['duration_ms'] ?? null);

        CLI::newLine();
        CLI::write("----------------------------------------------------", "yellow");

        $overall = $report['overall_status'];
        if ($overall === 'HEALTHY') {
            CLI::write("OVERALL STATUS           : [ PASS ] — HEALTHY", "green");
        } elseif ($overall === 'DEGRADED') {
            CLI::write("OVERALL STATUS           : [ WARN ] — DEGRADED / ATTENTION REQUIRED", "yellow");
        } else {
            CLI::write("OVERALL STATUS           : [ FAIL ] — UNHEALTHY / ACTION REQUIRED", "red");
        }

        CLI::write("----------------------------------------------------", "yellow");
        $counts = $report['summary_counts'];
        CLI::write("Domains Summary          : PASS: {$counts['pass']} | WARN: {$counts['warn']} | FAIL: {$counts['fail']} | N/A: {$counts['not_available']}", "cyan");
        CLI::write("Health Check Duration    : {$report['total_duration_ms']} ms", "cyan");
        CLI::write("Timestamp                : {$report['timestamp']}", "cyan");
        CLI::newLine();

        return ($overall === 'UNHEALTHY') ? 1 : 0;
    }

    private function printStepLine(string $step, string $label, string $status, string $detail, ?float $durationMs): void
    {
        $color = match($status) {
            'PASS' => 'green',
            'WARN' => 'yellow',
            'FAIL' => 'red',
            default => 'white',
        };

        $durationStr = ($durationMs !== null) ? " ({$durationMs}ms)" : "";
        CLI::write("[{$step}] {$label}: [" . str_pad($status, 4, ' ', STR_PAD_BOTH) . "] &bull; {$detail}{$durationStr}", $color);
    }
}
