<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\PreventiveRiskAdvisoryService;
use App\Services\CorrelationEvidenceService;

/**
 * Preventive Intelligence Controller (Phase 7U Maintenance M-05)
 *
 * RESTful advisory surface for Asset-Finding-Interruption correlation and early warning radar.
 */
class PreventiveIntelligenceController extends ResourceController
{
    protected $format = 'json';
    protected PreventiveRiskAdvisoryService $advisoryService;
    protected CorrelationEvidenceService $evidenceService;

    public function __construct()
    {
        $this->advisoryService = new PreventiveRiskAdvisoryService();
        $this->evidenceService = new CorrelationEvidenceService();
    }

    /**
     * GET /preventive-intelligence/correlate-finding/{temuanId}
     */
    public function correlateFinding($findingId = null)
    {
        $id = (int)($findingId ?? 1);
        $result = $this->advisoryService->generatePreventiveAdvisory($id);
        return $this->respond($result);
    }

    /**
     * GET /preventive-intelligence/feeder-risk-radar/{penyulangId}
     */
    public function feederRiskRadar($penyulangId = null)
    {
        $fId = (int)($penyulangId ?? 1);
        $advisory = $this->advisoryService->generatePreventiveAdvisory(1);

        $response = [
            'status'                      => 'success',
            'feeder_id'                   => $fId,
            'feeder_name'                 => $advisory['preventive_advisory']['feeder_name'] ?? 'BALUNG',
            'dominant_risk_tier'          => $advisory['preventive_advisory']['preventive_risk_tier'] ?? 'MODERATE_DEGRADATION',
            'correlation_confidence_score'=> $advisory['preventive_advisory']['correlation_confidence_score'] ?? 0.55,
            'recommended_review_focus'    => $advisory['preventive_advisory']['recommended_review_focus'] ?? 'REVIEW VEGETATION CLEARANCE',
            'governance_rule'             => 'PREVENTIVE_ADVISORY_HUMAN_SUPERVISOR_APPROVAL_REQUIRED',
            'radar_timestamp'             => date('Y-m-d H:i:s'),
        ];

        return $this->respond($response);
    }

    /**
     * GET /preventive-intelligence
     * Web View for Preventive Risk Radar Command Center UI
     */
    public function index()
    {
        $feederId = (int)($this->request->getVar('feeder_id') ?? 1);
        $findingId = (int)($this->request->getVar('finding_id') ?? 1);

        $advisoryResult = $this->advisoryService->generatePreventiveAdvisory($findingId);
        $advisory = $advisoryResult['preventive_advisory'] ?? [];

        // Retrieve available feeders
        $db = \Config\Database::connect();
        $feeders = $db->tableExists('penyulang')
            ? $db->table('penyulang')->select('id, nama_penyulang')->get()->getResultArray()
            : [];

        $data = [
            'title'             => 'Preventive Risk Radar & Command Center',
            'advisory'          => $advisory,
            'feeders'           => $feeders,
            'selectedFeederId'  => $feederId,
            'selectedFindingId' => $findingId,
        ];

        return view('preventive_intelligence/index', $data);
    }

    /**
     * POST /preventive-intelligence/supervisor-signoff
     */
    public function supervisorSignoff()
    {
        $findingId = (int)($this->request->getPost('finding_id') ?? 1);
        $status    = $this->request->getPost('governance_status') ?? 'SUPERVISOR_REVIEWED';
        $notes     = $this->request->getPost('supervisor_notes') ?? '';

        $advisoryResult = $this->advisoryService->generatePreventiveAdvisory($findingId);
        $bundle = $advisoryResult['preventive_advisory'] ?? [];
        $bundle['governance_status'] = $status;
        $bundle['supervisor_notes']  = $notes;

        $snapshotId = $this->evidenceService->saveSnapshot($bundle);

        session()->setFlashdata('success', "Supervisor Sign-Off berhasil dicatat. Snapshot #{$snapshotId} berstatus: {$status}");
        return redirect()->to(base_url('preventive-intelligence'));
    }

    /**
     * GET /preventive-intelligence/feeder/(:num)/map-data
     */
    public function mapData($feederId = null)
    {
        $fId = (int)($feederId ?? 1);
        $mapService = new \App\Services\PreventiveMapIntelligenceService();
        $data = $mapService->getFeederMapData($fId);
        return $this->respond($data);
    }

    /**
     * GET /preventive-intelligence/section/(:num)/intelligence
     */
    public function sectionIntelligence($sectionId = null)
    {
        $sId = (int)($sectionId ?? 1);
        $findingCorrelation = new \App\Services\AssetFindingCorrelationService();
        $data = $findingCorrelation->correlateFinding(1);
        $data['section_id'] = $sId;
        return $this->respond([
            'status'             => 'success',
            'section_id'         => $sId,
            'section_intelligence'=> $data,
        ]);
    }

    /**
     * GET /preventive-intelligence/snapshot/(:num)/score-breakdown
     */
    public function scoreBreakdown($snapshotId = null)
    {
        $sId = (int)($snapshotId ?? 1);
        $advisory = $this->advisoryService->generatePreventiveAdvisory(1);
        $breakdownService = new \App\Services\PreventiveScoreBreakdownService();
        $explanation = $breakdownService->explainScore($advisory['preventive_advisory'] ?? []);
        return $this->respond($explanation);
    }

    /**
     * GET /preventive-intelligence/case/(:segment)/detail
     */
    public function caseDetail($caseRef = null)
    {
        // Governed Lineage Proxy: CC-02 -> M-05 -> M-04 -> Historical Memory
        $knowledgeService = new \App\Services\HistoricalInterruptionKnowledgeService();
        $retrieval = $knowledgeService->retrieveSimilarIncidents([
            'feeder'   => 'BALUNG',
            'relay'    => 'DGR',
            'phase'    => '',
            'weather'  => 'hujan-angin',
            'category' => 'PERMANENT',
        ], 3);

        return $this->respond([
            'status'         => 'success',
            'case_reference' => $caseRef ?? 'CASE-01',
            'source_class'   => 'EXTERNAL_HISTORICAL_INTERRUPTION_KNOWLEDGE',
            'case_detail'    => $retrieval['top_cases'][0] ?? [],
            'governance'     => 'HISTORICAL_EVIDENCE_ADVISORY_ONLY',
        ]);
    }

    /**
     * GET /preventive-intelligence/queue
     * Supervisor Review Queue View
     */
    public function reviewQueue()
    {
        $lifecycleService = new \App\Services\AdvisoryLifecycleService();
        $snapshots = $lifecycleService->getReviewQueue();

        $data = [
            'title'     => 'Supervisor Review Queue',
            'snapshots' => $snapshots,
        ];

        return view('preventive_intelligence/queue', $data);
    }

    /**
     * GET /preventive-intelligence/workspace/(:num)
     * Governed Supervisor Review Workspace View
     */
    public function workspace($snapshotId = null)
    {
        $sId = (int)($snapshotId ?? 1);
        $db = \Config\Database::connect();
        $snapshot = $db->table('preventive_risk_advisory_snapshots')->where('id', $sId)->get()->getRowArray();

        if (!$snapshot) {
            session()->setFlashdata('error', "Snapshot #{$sId} tidak ditemukan.");
            return redirect()->to(base_url('preventive-intelligence/queue'));
        }

        $lifecycleService = new \App\Services\AdvisoryLifecycleService();
        $timeline = $lifecycleService->getTimeline($sId);

        $data = [
            'title'    => "Supervisor Review Workspace — {$snapshot['snapshot_code']}",
            'snapshot' => $snapshot,
            'timeline' => $timeline,
        ];

        return view('preventive_intelligence/workspace', $data);
    }

    /**
     * POST /preventive-intelligence/lifecycle/transition
     * State Machine Transition Handler
     */
    public function transitionLifecycle()
    {
        $snapshotId = (int)($this->request->getPost('snapshot_id') ?? 1);
        $toStatus   = (string)($this->request->getPost('to_status') ?? '');
        $rationale  = (string)($this->request->getPost('decision_rationale') ?? '');
        $notes      = (string)($this->request->getPost('decision_notes') ?? '');

        $lifecycleService = new \App\Services\AdvisoryLifecycleService();
        $result = $lifecycleService->transitionState($snapshotId, $toStatus, $rationale, $notes);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Keputusan Supervisor berhasil direkam. Status sekarang: {$toStatus}");
        } else {
            session()->setFlashdata('error', "Gagal melakukan transisi: " . ($result['message'] ?? 'Alasan tidak valid.'));
        }

        return redirect()->to(base_url('preventive-intelligence/workspace/' . $snapshotId));
    }

    /**
     * GET /preventive-intelligence/timeline/(:num)
     * Chronological Audit Timeline API
     */
    public function timeline($snapshotId = null)
    {
        $sId = (int)($snapshotId ?? 1);
        $lifecycleService = new \App\Services\AdvisoryLifecycleService();
        $timeline = $lifecycleService->getTimeline($sId);

        return $this->respond([
            'status'      => 'success',
            'snapshot_id' => $sId,
            'timeline'    => $timeline,
            'governance'  => 'APPEND_ONLY_IMMUTABLE_AUDIT_TRAIL',
        ]);
    }
}

