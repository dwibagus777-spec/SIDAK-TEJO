<?php

namespace App\Controllers;

use App\Services\FaultLocationIntelligenceService;
use App\Services\NetworkContextEngine;
use App\Services\NetworkIntelligenceService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * SIDAK TEJO — Phase B.4: Fault Location Intelligence Controller
 *
 * Exposes endpoints for:
 * 1. 360° Asset Context Telemetry (Guard 1 & Guard 5)
 * 2. Multi-Factor Candidate Fault Locator (Guards 4, 6, 7, 14)
 * 3. Read-Only Fault Simulation (Zero DB write)
 * 4. Fault Case Lifecycle & Input Hash Fingerprinting (Guards 2, 3, 11, 13)
 * 5. Append-Only Field Findings Recording (Guards 8, 9, 12)
 * 6. Canonical Cause Taxonomy Catalog (Guard 10)
 * 7. Phase B.4 Live System Audit Scorecard
 */
class FaultIntelligenceController extends BaseController
{
    protected NetworkIntelligenceService $networkIntelligence;
    protected NetworkContextEngine $contextEngine;
    protected FaultLocationIntelligenceService $faultService;

    public function __construct()
    {
        $this->networkIntelligence = new NetworkIntelligenceService();
        $this->contextEngine = new NetworkContextEngine($this->networkIntelligence);
        $this->faultService = new FaultLocationIntelligenceService($this->networkIntelligence, $this->contextEngine);
    }

    /**
     * Authenticate via session or audit secret key
     */
    protected function authenticate(): bool
    {
        if (session()->get('logged_in')) {
            return true;
        }

        $auditKey = 'sidak_transline_audit_2026';
        $providedKey = $this->request->getGet('key') ?? $this->request->getHeaderLine('X-Audit-Token');

        return (!empty($providedKey) && hash_equals($auditKey, (string)$providedKey));
    }

    protected function unauthorizedResponse(): ResponseInterface
    {
        return $this->response->setStatusCode(401)->setJSON([
            'status'  => 'error',
            'code'    => 401,
            'reason'  => 'UNAUTHORIZED',
            'message' => 'Unauthorized: Endpoint ini memerlukan autentikasi login atau deployment audit credential.',
        ]);
    }

    /**
     * B.4.1 — 360° Asset Context Telemetry (Guard 1 & Guard 5)
     */
    public function context($identifier): ResponseInterface
    {
        if (!$this->authenticate()) {
            return $this->unauthorizedResponse();
        }

        $result = $this->contextEngine->getAssetContext($identifier);
        return $this->response->setJSON($result);
    }

    /**
     * B.4.4 — Locate Fault Candidates (Guards 4, 6, 7, 14)
     */
    public function candidates(): ResponseInterface
    {
        if (!$this->authenticate()) {
            return $this->unauthorizedResponse();
        }

        $penyulangId = (int)$this->request->getGet('penyulang_id');
        $deviceId = (int)$this->request->getGet('device_id');
        $targetDistance = (float)$this->request->getGet('target_distance');
        $tolerance = $this->request->getGet('tolerance') !== null ? (float)$this->request->getGet('tolerance') : null;
        $faultType = $this->request->getGet('fault_type') ?? 'UNKNOWN';

        if ($penyulangId <= 0 || $deviceId <= 0 || $targetDistance < 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'code'    => 400,
                'message' => 'Parameter penyulang_id, device_id, dan target_distance wajib disertakan.',
            ]);
        }

        $result = $this->faultService->locateCandidates(
            $penyulangId,
            $deviceId,
            $targetDistance,
            $tolerance,
            ['fault_type' => $faultType]
        );

        return $this->response->setJSON($result);
    }

    /**
     * B.4.7 — Read-Only Fault Simulation (Zero DB write)
     */
    public function simulate(): ResponseInterface
    {
        if (!$this->authenticate()) {
            return $this->unauthorizedResponse();
        }

        $penyulangId = (int)($this->request->getVar('penyulang_id') ?? 118);
        $deviceId = (int)($this->request->getVar('device_id') ?? 5245);
        $targetDistance = (float)($this->request->getVar('target_distance') ?? 25.0);
        $tolerance = $this->request->getVar('tolerance') !== null ? (float)$this->request->getVar('tolerance') : null;

        $result = $this->faultService->locateCandidates(
            $penyulangId,
            $deviceId,
            $targetDistance,
            $tolerance,
            ['fault_type' => 'SIMULATED_TEST']
        );

        return $this->response->setJSON([
            'simulation' => true,
            'mutation'   => false,
            'mode'       => 'READ_ONLY_SIMULATION',
            'result'     => $result,
        ]);
    }

    /**
     * B.4.4.2 — Record Actual Field Finding (Guards 8, 9, 12)
     */
    public function recordFinding(int $caseId): ResponseInterface
    {
        if (!$this->authenticate()) {
            return $this->unauthorizedResponse();
        }

        $foundAssetId = (int)$this->request->getPost('found_asset_id');
        $causeCategoryCode = (string)$this->request->getPost('cause_category_code');
        $observedDistanceM = $this->request->getPost('actual_observed_distance_m') !== null ? (float)$this->request->getPost('actual_observed_distance_m') : null;
        $graphDistanceM = $this->request->getPost('actual_graph_distance_from_device_m') !== null ? (float)$this->request->getPost('actual_graph_distance_from_device_m') : null;
        $technicianName = (string)($this->request->getPost('technician_name') ?? 'PETUGAS_LAPANGAN');
        $notes = $this->request->getPost('finding_notes');
        $photoUrl = $this->request->getPost('photo_evidence_url');

        if ($caseId <= 0 || $foundAssetId <= 0 || empty($causeCategoryCode)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'code'    => 400,
                'message' => 'Parameter case_id, found_asset_id, dan cause_category_code wajib disertakan.',
            ]);
        }

        $res = $this->faultService->recordActualFinding(
            $caseId,
            $foundAssetId,
            $causeCategoryCode,
            $observedDistanceM,
            $graphDistanceM,
            $technicianName,
            $notes,
            $photoUrl
        );

        return $this->response->setJSON($res);
    }

    /**
     * B.4.3 — Run Fault Intelligence Table Migration (Idempotent DDL & Seed)
     */
    public function migrate(): ResponseInterface
    {
        if (!$this->authenticate()) {
            return $this->unauthorizedResponse();
        }

        try {
            $migration = new \App\Database\Migrations\CreateFaultIntelligenceTables();
            $migration->up();

            $db = \Config\Database::connect();
            $tables = [
                'fault_cause_categories' => $db->tableExists('fault_cause_categories'),
                'fault_events'           => $db->tableExists('fault_events'),
                'fault_cases'            => $db->tableExists('fault_cases'),
                'fault_candidate_assets' => $db->tableExists('fault_candidate_assets'),
                'fault_actual_findings'  => $db->tableExists('fault_actual_findings'),
            ];

            $causesCount = $tables['fault_cause_categories']
                ? $db->table('fault_cause_categories')->countAllResults()
                : 0;

            return $this->response->setJSON([
                'success'           => true,
                'message'           => 'Fault Intelligence tables migrated and seeded successfully.',
                'tables'            => $tables,
                'causes_seeded'     => $causesCount,
                'timestamp'         => date('Y-m-d H:i:s T'),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * B.4.3 — Canonical Cause Categories Taxonomy Catalog (Guard 10)
     */
    public function causes(): ResponseInterface
    {
        if (!$this->authenticate()) {
            return $this->unauthorizedResponse();
        }

        $db = \Config\Database::connect();
        if ($db->tableExists('fault_cause_categories')) {
            $categories = $db->table('fault_cause_categories')
                ->where('is_active', 1)
                ->orderBy('sort_order', 'ASC')
                ->get()
                ->getResultArray();
        } else {
            $categories = [];
        }

        return $this->response->setJSON([
            'status'     => 'success',
            'count'      => count($categories),
            'categories' => $categories,
        ]);
    }

    /**
     * Phase B.4 Live System Audit Scorecard
     */
    public function b4Audit(): ResponseInterface
    {
        if (!$this->authenticate()) {
            return $this->unauthorizedResponse();
        }

        $db = \Config\Database::connect();
        $startTime = microtime(true);

        // Pre-audit table counts
        $tlCountBefore = $db->tableExists('gis_translines') ? $db->table('gis_translines')->countAllResults() : 0;
        $assetCountBefore = $db->tableExists('assets') ? $db->table('assets')->countAllResults() : 0;

        // 1. Run Engine Guard Audit
        $engineAudit = $this->faultService->runIntegrityAudit();

        // 2. Database Schema Presence Audit (Guard 10, 2, 3, 4, 8)
        $requiredTables = [
            'fault_cause_categories',
            'fault_events',
            'fault_cases',
            'fault_candidate_assets',
            'fault_actual_findings',
        ];
        $schemaChecks = [];
        foreach ($requiredTables as $tbl) {
            $schemaChecks[$tbl] = $db->tableExists($tbl);
        }
        $schemaPass = !in_array(false, $schemaChecks, true);

        // 3. Circular Key Guard Audit (Guard 12)
        // Ensure fault_cases table does NOT contain actual_finding_id
        $noCircularFk = true;
        if ($db->tableExists('fault_cases')) {
            $fields = $db->getFieldNames('fault_cases');
            $noCircularFk = !in_array('actual_finding_id', $fields, true);
        }

        // 4. Feeder 118 Candidate Simulation Test
        $simResult = $this->faultService->locateCandidates(118, 5245, 25.0, 20.0);
        $candidates = $simResult['payload']['candidates'] ?? [];
        $topCand = !empty($candidates) ? $candidates[0] : null;
        $feeder118Pass = ($topCand !== null && (int)$topCand['asset_id'] === 5246 && $topCand['candidate_status'] === 'CANDIDATE');

        // Post-audit table counts (Verify 0 DB mutation)
        $tlCountAfter = $db->tableExists('gis_translines') ? $db->table('gis_translines')->countAllResults() : 0;
        $assetCountAfter = $db->tableExists('assets') ? $db->table('assets')->countAllResults() : 0;

        $zeroMutationPass = ($tlCountBefore === $tlCountAfter && $assetCountBefore === $assetCountAfter);

        $executionMs = round((microtime(true) - $startTime) * 1000, 2);

        $scorecard = [
            'phase'                  => 'B.4',
            'engine_version'         => FaultLocationIntelligenceService::ANALYSIS_VERSION,
            'topology_snapshot_id'   => FaultLocationIntelligenceService::TOPOLOGY_SNAPSHOT_ID,
            'timestamp'              => date('Y-m-d H:i:s T'),
            'execution_time_ms'      => $executionMs,
            'all_guards_passed'      => $engineAudit['all_passed'] && $noCircularFk && $zeroMutationPass && $feeder118Pass,
            'status'                 => ($engineAudit['all_passed'] && $noCircularFk && $zeroMutationPass && $feeder118Pass)
                ? 'PHASE_B4_FAULT_INTELLIGENCE_VERIFIED'
                : 'PHASE_B4_FAULT_INTELLIGENCE_FAILED',
            'checks'                 => [
                'engine_guards'        => $engineAudit,
                'tables_installed'     => [
                    'passed' => $schemaPass,
                    'tables' => $schemaChecks,
                ],
                'guard_12_no_circular' => [
                    'name'   => 'Guard 12: No circular FK in fault_cases',
                    'passed' => $noCircularFk,
                ],
                'zero_mutation'        => [
                    'name'         => 'Authoritative Physical Zero Mutation',
                    'passed'       => $zeroMutationPass,
                    'translines'   => ['before' => $tlCountBefore, 'after' => $tlCountAfter],
                    'assets'       => ['before' => $assetCountBefore, 'after' => $assetCountAfter],
                ],
                'feeder_118_locator'   => [
                    'name'             => 'Feeder 118 Ground Truth Candidate Match',
                    'passed'           => $feeder118Pass,
                    'top_candidate_id' => $topCand['asset_id'] ?? null,
                    'candidate_status' => $topCand['candidate_status'] ?? null,
                ],
            ],
        ];

        return $this->response->setJSON($scorecard);
    }
}
