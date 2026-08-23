<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\ProductionRuntimeHardeningService;

/**
 * Production Hardening Audit CLI Command (Wave 3 Phase PH-01)
 *
 * Responsibilities:
 * - Read-only CLI runner for production runtime hardening posture.
 * - ZERO side effects on database, config, or sessions.
 * - Clear PASS / WARN / FAIL reporting.
 */
class ProductionHardeningAuditCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'audit:runtime';
    protected $description = 'Audits runtime environment, security posture, writable storage, and secret exposure protection.';

    public function run(array $params)
    {
        CLI::write("====================================================", "yellow");
        CLI::write("SIDAK TEJO ENTERPRISE v3.0.0 — PRODUCTION RUNTIME AUDIT", "yellow");
        CLI::write("====================================================", "yellow");
        CLI::newLine();

        $service = new ProductionRuntimeHardeningService();
        $audit   = $service->runFullAudit();

        $dom = $audit['domains'];

        // 1. Runtime Environment
        $this->printDomainLine("Runtime Environment      ", $dom['environment']['status'], $dom['environment']['summary']);
        
        // 2. Error Exposure
        $this->printDomainLine("Error Exposure           ", $dom['error_exposure']['status'], $dom['error_exposure']['summary']);

        // 3. HTTPS Posture
        $this->printDomainLine("HTTPS & Base URL Posture ", $dom['https_posture']['status'], $dom['https_posture']['summary']);

        // 4. Security & CSRF Posture
        $this->printDomainLine("Security / CSRF Posture  ", $dom['security_csrf']['status'], $dom['security_csrf']['summary']);

        // 5. Session & Cookie Posture
        $this->printDomainLine("Session / Cookie Posture ", $dom['session_cookie']['status'], $dom['session_cookie']['summary']);

        // 6. Writable Storage
        $this->printDomainLine("Writable Storage Health  ", $dom['storage']['status'], $dom['storage']['summary']);

        // 7. Database Posture
        $this->printDomainLine("Database Debug Posture   ", $dom['database']['status'], $dom['database']['summary']);

        // 8. Secret Exposure Guard
        $this->printDomainLine("Secret Exposure Guard    ", $dom['secret_guard']['status'], $dom['secret_guard']['summary']);

        CLI::newLine();
        CLI::write("----------------------------------------------------", "yellow");
        
        $overall = $audit['overall_status'];
        if ($overall === 'PASS') {
            CLI::write("OVERALL STATUS           : [ PASS ] — RUNTIME POSTURE VERIFIED", "green");
        } elseif ($overall === 'WARN') {
            CLI::write("OVERALL STATUS           : [ WARN ] — DEVELOPMENT POSTURE DETECTED", "yellow");
        } else {
            CLI::write("OVERALL STATUS           : [ FAIL ] — RUNTIME HARDENING ACTION REQUIRED", "red");
        }
        CLI::write("----------------------------------------------------", "yellow");
        CLI::write("Audit Timestamp          : " . $audit['audit_timestamp'], "cyan");
        CLI::write("Failures: {$audit['failures_count']} | Warnings: {$audit['warnings_count']}", "cyan");
        CLI::newLine();

        return 0;
    }

    private function printDomainLine(string $label, string $status, string $detail): void
    {
        $color = match($status) {
            'PASS' => 'green',
            'WARN' => 'yellow',
            'FAIL' => 'red',
            default => 'white',
        };

        CLI::write(" - {$label} : [" . str_pad($status, 4, ' ', STR_PAD_BOTH) . "] &bull; {$detail}", $color);
    }
}
