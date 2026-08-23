<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\OperationalWorkAcceptanceService;
use App\Services\OperationalFieldExecutionService;

/**
 * Operational Work Acceptance Controller (Wave 2 Phase OP-07)
 *
 * Responsibilities:
 * - Work Acceptance, Quality Assurance & Closure Governance UI & API.
 * - Invariant: COMPLETION_DECLARATION != WORK_ACCEPTANCE != WORK_CLOSED.
 */
class OperationalWorkAcceptanceController extends ResourceController
{
    protected $format = 'json';
    protected OperationalWorkAcceptanceService $acceptanceService;
    protected OperationalFieldExecutionService $executionService;

    public function __construct()
    {
        $this->acceptanceService = new OperationalWorkAcceptanceService();
        $this->executionService = new OperationalFieldExecutionService();
    }

    /**
     * GET /operational-planning/acceptances
     */
    public function index()
    {
        $acceptances = $this->acceptanceService->getAcceptanceRecords();
        $readyExecutions = $this->acceptanceService->getCompletedExecutionsReadyForAcceptance();

        $data = [
            'title'           => 'Work Acceptance & Quality Assurance Governance',
            'acceptances'     => $acceptances,
            'readyExecutions' => $readyExecutions,
        ];

        return view('operational_planning/acceptance_list', $data);
    }

    /**
     * GET /operational-planning/acceptances/initiate/(:num)
     */
    public function initiate($execId = null)
    {
        $eId = (int)($execId ?? 1);
        $result = $this->acceptanceService->initiateAcceptanceReview($eId);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Proses Review Penerimaan {$result['acceptance_code']} berhasil dibuat. Silakan lakukan audit 4 dimensi mutu.");
            return redirect()->to(base_url('operational-planning/acceptances/detail/' . $result['acceptance_id']));
        }

        session()->setFlashdata('error', "Gagal menginisiasi review penerimaan: " . ($result['message'] ?? 'Data tidak valid.'));
        return redirect()->to(base_url('operational-planning/acceptances'));
    }

    /**
     * GET /operational-planning/acceptances/detail/(:num)
     */
    public function detail($accId = null)
    {
        $aId = (int)($accId ?? 1);
        $data = $this->acceptanceService->getAcceptanceDetail($aId);

        if (empty($data['acc'])) {
            session()->setFlashdata('error', "Rekaman Penerimaan #{$aId} tidak ditemukan.");
            return redirect()->to(base_url('operational-planning/acceptances'));
        }

        $data['title'] = "Work Acceptance Certificate — {$data['acc']['acceptance_code']}";

        if (!empty($data['acc']['acceptance_certificate_sha256'])) {
            $data['seal_verification'] = $this->acceptanceService->verifyCertificateSeal($aId);
        }

        return view('operational_planning/acceptance_detail', $data);
    }

    /**
     * POST /operational-planning/acceptances/evaluate/(:num)
     */
    public function evaluate($accId = null)
    {
        $aId       = (int)($accId ?? 1);
        $rationale = (string)($this->request->getPost('decision_rationale') ?? 'Pembaruan evaluasi mutu 4 dimensi');

        $evidence = [];
        $evidenceItems = $this->request->getPost('evidence_items') ?? [];
        $evidencePassed = $this->request->getPost('evidence_passed') ?? [];
        $evidenceNotes = $this->request->getPost('evidence_notes') ?? [];
        foreach ($evidenceItems as $idx => $item) {
            $evidence[] = [
                'item'   => $item,
                'passed' => !empty($evidencePassed[$idx]),
                'notes'  => $evidenceNotes[$idx] ?? '',
            ];
        }

        $technical = [];
        $technicalItems = $this->request->getPost('technical_items') ?? [];
        $technicalPassed = $this->request->getPost('technical_passed') ?? [];
        $technicalNotes = $this->request->getPost('technical_notes') ?? [];
        foreach ($technicalItems as $idx => $item) {
            $technical[] = [
                'item'   => $item,
                'passed' => !empty($technicalPassed[$idx]),
                'notes'  => $technicalNotes[$idx] ?? '',
            ];
        }

        $material = [];
        $materialItems = $this->request->getPost('material_items') ?? [];
        $materialPassed = $this->request->getPost('material_passed') ?? [];
        $materialNotes = $this->request->getPost('material_notes') ?? [];
        foreach ($materialItems as $idx => $item) {
            $material[] = [
                'item'   => $item,
                'passed' => !empty($materialPassed[$idx]),
                'notes'  => $materialNotes[$idx] ?? '',
            ];
        }

        $asbuilt = [];
        $asbuiltItems = $this->request->getPost('asbuilt_items') ?? [];
        $asbuiltPassed = $this->request->getPost('asbuilt_passed') ?? [];
        $asbuiltNotes = $this->request->getPost('asbuilt_notes') ?? [];
        foreach ($asbuiltItems as $idx => $item) {
            $asbuilt[] = [
                'item'   => $item,
                'passed' => !empty($asbuiltPassed[$idx]),
                'notes'  => $asbuiltNotes[$idx] ?? '',
            ];
        }

        $evalData = [
            'evidence_verification' => $evidence,
            'technical_quality'     => $technical,
            'material_audit'        => $material,
            'asbuilt_verification'  => $asbuilt,
        ];

        $result = $this->acceptanceService->evaluateQualityDimensions($aId, $evalData, $rationale);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Evaluasi mutu berhasil disimpan. Skor Mutu Saat Ini: {$result['quality_score']}%");
        } else {
            session()->setFlashdata('error', "Gagal memperbarui evaluasi mutu: " . ($result['message'] ?? 'Data tidak valid.'));
        }

        return redirect()->to(base_url('operational-planning/acceptances/detail/' . $aId));
    }

    /**
     * POST /operational-planning/acceptances/transition/(:num)
     */
    public function transition($accId = null)
    {
        $aId       = (int)($accId ?? 1);
        $action    = (string)($this->request->getPost('action_type') ?? '');
        $rationale = (string)($this->request->getPost('decision_rationale') ?? '');

        if ($action === 'ACCEPT_WORK') {
            $result = $this->acceptanceService->acceptWork($aId, $rationale);
        } elseif ($action === 'REQUEST_REWORK') {
            $instructions = (string)($this->request->getPost('rework_instructions') ?? $rationale);
            $result = $this->acceptanceService->requestRework($aId, $instructions);
        } elseif ($action === 'REQUEST_REINSPECTION') {
            $result = $this->acceptanceService->requestReinspection($aId, $rationale);
        } elseif ($action === 'CLOSE_WORK') {
            $result = $this->acceptanceService->closeWork($aId, $rationale);
        } else {
            $result = [
                'status'  => 'error',
                'message' => "Aksi transisi '{$action}' tidak dikenal.",
            ];
        }

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Aksi berhasil diproses: " . ($result['governance_verdict'] ?? 'Status terverifikasi.'));
        } else {
            session()->setFlashdata('error', "Gagal memproses aksi: " . ($result['message'] ?? 'Persyaratan mutu belum terpenuhi.'));
        }

        return redirect()->to(base_url('operational-planning/acceptances/detail/' . $aId));
    }
}
