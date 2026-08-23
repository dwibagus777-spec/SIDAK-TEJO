<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\OperationalWorkAuthorizationService;
use App\Services\OperationalSchedulingService;

/**
 * Operational Work Authorization Controller (Wave 2 Phase OP-05)
 *
 * Responsibilities:
 * - Execution Readiness Gate & Work Authorization Governance UI & API.
 * - Invariant: AUTHORIZED_EXECUTION_INTENT != AUTOMATIC_FIELD_EXECUTION.
 */
class OperationalWorkAuthorizationController extends ResourceController
{
    protected $format = 'json';
    protected OperationalWorkAuthorizationService $authService;
    protected OperationalSchedulingService $schedulingService;

    public function __construct()
    {
        $this->authService = new OperationalWorkAuthorizationService();
        $this->schedulingService = new OperationalSchedulingService();
    }

    /**
     * GET /operational-planning/authorizations
     */
    public function index()
    {
        $authorizations = $this->authService->getAuthorizations();
        $readySlots = $this->authService->getApprovedSlotsReadyForAuthorization();

        $data = [
            'title'          => 'Execution Readiness Gate & Work Authorization',
            'authorizations' => $authorizations,
            'readySlots'     => $readySlots,
        ];

        return view('operational_planning/authorization_list', $data);
    }

    /**
     * GET /operational-planning/authorizations/generate/(:num)
     */
    public function generate($slotId = null)
    {
        $sId = (int)($slotId ?? 1);
        $result = $this->authService->generatePackageForSlot($sId);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Paket Otorisasi {$result['authorization_code']} berhasil dibuat. Silakan lakukan verifikasi kesiapan 4 dimensi.");
            return redirect()->to(base_url('operational-planning/authorizations/detail/' . $result['authorization_id']));
        }

        session()->setFlashdata('error', "Gagal membuat paket otorisasi: " . ($result['message'] ?? 'Data tidak valid.'));
        return redirect()->to(base_url('operational-planning/authorizations'));
    }

    /**
     * GET /operational-planning/authorizations/detail/(:num)
     */
    public function detail($authId = null)
    {
        $aId = (int)($authId ?? 1);
        $data = $this->authService->getAuthorizationDetail($aId);

        if (empty($data['auth'])) {
            session()->setFlashdata('error', "Paket Otorisasi #{$aId} tidak ditemukan.");
            return redirect()->to(base_url('operational-planning/authorizations'));
        }

        $data['title'] = "Work Authorization Package — {$data['auth']['authorization_code']}";

        // If sealed, verify hash
        if (!empty($data['auth']['authorization_sha256'])) {
            $data['seal_verification'] = $this->authService->verifyPackageSeal($aId);
        }

        return view('operational_planning/authorization_detail', $data);
    }

    /**
     * POST /operational-planning/authorizations/verify-readiness/(:num)
     */
    public function verifyReadiness($authId = null)
    {
        $aId = (int)($authId ?? 1);
        $rationale = (string)($this->request->getPost('decision_rationale') ?? 'Pembaruan checklist kesiapan operasional');

        $safety = [];
        $safetyItems = $this->request->getPost('safety_items') ?? [];
        $safetyPassed = $this->request->getPost('safety_passed') ?? [];
        $safetyNotes = $this->request->getPost('safety_notes') ?? [];
        foreach ($safetyItems as $idx => $item) {
            $safety[] = [
                'item'   => $item,
                'passed' => !empty($safetyPassed[$idx]),
                'notes'  => $safetyNotes[$idx] ?? '',
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

        $permit = [];
        $permitItems = $this->request->getPost('permit_items') ?? [];
        $permitPassed = $this->request->getPost('permit_passed') ?? [];
        $permitNotes = $this->request->getPost('permit_notes') ?? [];
        foreach ($permitItems as $idx => $item) {
            $permit[] = [
                'item'   => $item,
                'passed' => !empty($permitPassed[$idx]),
                'notes'  => $permitNotes[$idx] ?? '',
            ];
        }

        $team = [];
        $teamItems = $this->request->getPost('team_items') ?? [];
        $teamPassed = $this->request->getPost('team_passed') ?? [];
        $teamNotes = $this->request->getPost('team_notes') ?? [];
        foreach ($teamItems as $idx => $item) {
            $team[] = [
                'item'   => $item,
                'passed' => !empty($teamPassed[$idx]),
                'notes'  => $teamNotes[$idx] ?? '',
            ];
        }

        $checklistData = [
            'safety_readiness'   => $safety,
            'material_readiness' => $material,
            'permit_readiness'   => $permit,
            'team_readiness'     => $team,
        ];

        $result = $this->authService->updateReadinessChecklist($aId, $checklistData, $rationale);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Checklist kesiapan berhasil diperbarui. Skor Kesiapan: {$result['readiness_score']}%");
        } else {
            session()->setFlashdata('error', "Gagal memperbarui checklist: " . ($result['message'] ?? 'Data tidak valid.'));
        }

        return redirect()->to(base_url('operational-planning/authorizations/detail/' . $aId));
    }

    /**
     * POST /operational-planning/authorizations/transition/(:num)
     */
    public function transition($authId = null)
    {
        $aId       = (int)($authId ?? 1);
        $toStatus  = (string)($this->request->getPost('to_status') ?? '');
        $rationale = (string)($this->request->getPost('decision_rationale') ?? '');

        $result = $this->authService->transitionAuthorizationStatus($aId, $toStatus, $rationale);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Status otorisasi berhasil diubah menjadi: {$toStatus}" . (!empty($result['authorization_sha256']) ? " (Segel SHA-256 Terverifikasi)" : ""));
        } else {
            session()->setFlashdata('error', "Gagal mengubah status: " . ($result['message'] ?? 'Kelengkapan kesiapan belum terpenuhi.'));
        }

        return redirect()->to(base_url('operational-planning/authorizations/detail/' . $aId));
    }
}
