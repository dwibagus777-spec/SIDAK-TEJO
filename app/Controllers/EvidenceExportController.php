<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\ManagementEvidencePackService;
use App\Services\ExecutiveDecisionAnalyticsService;

/**
 * Evidence Export Controller (Phase CC-05)
 *
 * Responsibilities:
 * - Serves Evidence Pack ZIP download, Printable Executive Report, and Hash Verification.
 * - Invariant: MANAGEMENT_EVIDENCE_READ_MODEL_ONLY.
 */
class EvidenceExportController extends ResourceController
{
    protected $format = 'json';
    protected ManagementEvidencePackService $evidencePackService;
    protected ExecutiveDecisionAnalyticsService $analyticsService;

    public function __construct()
    {
        $this->evidencePackService = new ManagementEvidencePackService();
        $this->analyticsService = new ExecutiveDecisionAnalyticsService();
    }

    /**
     * GET /executive-intelligence/export/evidence-pack
     * Downloads the official ZIP Evidence Pack.
     */
    public function downloadPack()
    {
        $result = $this->evidencePackService->generateZipPackage();
        $zipPath = $result['zip_path'];

        if (file_exists($zipPath)) {
            return $this->response->download($zipPath, null)->setFileName($result['zip_filename']);
        }

        return $this->failNotFound('Evidence pack could not be generated.');
    }

    /**
     * GET /executive-intelligence/export/print-report
     * Printable formal executive report.
     */
    public function printReport()
    {
        $summary = $this->analyticsService->generateExecutiveSummary();
        $bundle = $this->evidencePackService->buildEvidencePayloadBundle();

        $data = [
            'title'    => 'Official Executive Intelligence & Management Report',
            'summary'  => $summary,
            'manifest' => $bundle['manifest_array'],
        ];

        return view('preventive_intelligence/print_report', $data);
    }

    /**
     * GET /executive-intelligence/export/verify-hash
     * Independent structural & cryptographic forensic verification endpoint.
     */
    public function verifyHash()
    {
        $bundle = $this->evidencePackService->buildEvidencePayloadBundle();
        $verification = $this->evidencePackService->verifyEvidencePack($bundle);

        return $this->respond([
            'status'             => 'success',
            'bundle_id'          => $bundle['bundle_id'],
            'forensic_audit'     => $verification,
            'governance_verdict' => 'AUDIT_PROOF_CRYPTOGRAPHICALLY_VERIFIED',
        ]);
    }
}
