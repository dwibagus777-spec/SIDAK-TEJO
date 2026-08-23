<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\OperationalFieldExecutionService;
use App\Services\OperationalWorkAuthorizationService;

/**
 * Operational Field Execution Controller (Wave 2 Phase OP-06)
 *
 * Responsibilities:
 * - Controlled Field Execution Record & Human Progress Governance UI & API.
 * - Invariant: EXECUTION_AUTHORIZED != WORK_AUTOMATICALLY_STARTED.
 */
class OperationalFieldExecutionController extends ResourceController
{
    protected $format = 'json';
    protected OperationalFieldExecutionService $executionService;
    protected OperationalWorkAuthorizationService $authService;

    public function __construct()
    {
        $this->executionService = new OperationalFieldExecutionService();
        $this->authService = new OperationalWorkAuthorizationService();
    }

    /**
     * GET /operational-planning/executions
     */
    public function index()
    {
        $executions = $this->executionService->getExecutionRecords();
        $readyPackages = $this->executionService->getAuthorizedPackagesReadyForExecution();

        $data = [
            'title'         => 'Controlled Field Execution & Progress Governance',
            'executions'    => $executions,
            'readyPackages' => $readyPackages,
        ];

        return view('operational_planning/execution_list', $data);
    }

    /**
     * GET /operational-planning/executions/initiate/(:num)
     */
    public function initiate($authId = null)
    {
        $aId = (int)($authId ?? 1);
        $result = $this->executionService->initiateExecutionRecord($aId);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Rekaman Eksekusi {$result['execution_code']} berhasil diinisiasi. Status: WORK_PENDING_FIELD_START.");
            return redirect()->to(base_url('operational-planning/executions/detail/' . $result['execution_id']));
        }

        session()->setFlashdata('error', "Gagal menginisiasi rekaman eksekusi: " . ($result['message'] ?? 'Data tidak valid.'));
        return redirect()->to(base_url('operational-planning/executions'));
    }

    /**
     * GET /operational-planning/executions/detail/(:num)
     */
    public function detail($execId = null)
    {
        $eId = (int)($execId ?? 1);
        $data = $this->executionService->getExecutionDetail($eId);

        if (empty($data['exec'])) {
            session()->setFlashdata('error', "Rekaman Eksekusi #{$eId} tidak ditemukan.");
            return redirect()->to(base_url('operational-planning/executions'));
        }

        $data['title'] = "Field Execution Record — {$data['exec']['execution_code']}";

        return view('operational_planning/execution_detail', $data);
    }

    /**
     * POST /operational-planning/executions/start/(:num)
     */
    public function startWork($execId = null)
    {
        $eId       = (int)($execId ?? 1);
        $photoUri  = (string)($this->request->getPost('before_photo_uri') ?? '');
        $notes     = (string)($this->request->getPost('before_photo_notes') ?? '');
        $rationale = (string)($this->request->getPost('start_rationale') ?? 'Pemeriksaan lapangan dan briefing K3 selesai');

        $beforeEvidence = [
            'photo_uri' => $photoUri,
            'notes'     => $notes,
        ];

        $result = $this->executionService->startFieldWork($eId, $beforeEvidence, $rationale);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Pekerjaan fisik resmi DIMULAI di lapangan oleh {$result['initiated_by']}. Status: WORK_IN_PROGRESS.");
        } else {
            session()->setFlashdata('error', "Gagal memulai pekerjaan: " . ($result['message'] ?? 'Bukti Before Photo wajib diisi.'));
        }

        return redirect()->to(base_url('operational-planning/executions/detail/' . $eId));
    }

    /**
     * POST /operational-planning/executions/progress/(:num)
     */
    public function logProgress($execId = null)
    {
        $eId         = (int)($execId ?? 1);
        $progressPct = (float)($this->request->getPost('progress_percentage') ?? 50.0);
        $desc        = (string)($this->request->getPost('progress_description') ?? '');
        $rationale   = (string)($this->request->getPost('decision_rationale') ?? '');

        $result = $this->executionService->logProgressUpdate($eId, $progressPct, $desc, null, $rationale);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Progres berhasil dicatat: {$result['progress']}%");
        } else {
            session()->setFlashdata('error', "Gagal mencatat progres: " . ($result['message'] ?? 'Data tidak valid.'));
        }

        return redirect()->to(base_url('operational-planning/executions/detail/' . $eId));
    }

    /**
     * POST /operational-planning/executions/materials/(:num)
     */
    public function reconcileMaterials($execId = null)
    {
        $eId       = (int)($execId ?? 1);
        $actualQty = $this->request->getPost('actual_quantity') ?? [];
        $varNotes  = $this->request->getPost('variance_rationale') ?? [];
        $rationale = (string)($this->request->getPost('reconciliation_rationale') ?? 'Rekonsiliasi pemakaian material harian');

        $actuals = [];
        foreach ($actualQty as $idx => $qty) {
            $actuals[$idx] = [
                'actual_quantity'    => (float)$qty,
                'variance_rationale' => $varNotes[$idx] ?? '',
            ];
        }

        $result = $this->executionService->reconcileActualMaterials($eId, $actuals, $rationale);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Rekonsiliasi material aktual berhasil disimpan tanpa mengubah estimasi perencanaan OP-02.");
        } else {
            session()->setFlashdata('error', "Gagal merekonsiliasi material: " . ($result['message'] ?? 'Data tidak valid.'));
        }

        return redirect()->to(base_url('operational-planning/executions/detail/' . $eId));
    }

    /**
     * POST /operational-planning/executions/hold/(:num)
     */
    public function declareHold($execId = null)
    {
        $eId        = (int)($execId ?? 1);
        $holdReason = (string)($this->request->getPost('safety_hold_reason') ?? '');
        $riskDesc   = (string)($this->request->getPost('risk_description') ?? '');

        $result = $this->executionService->declareSafetyHold($eId, $holdReason, $riskDesc);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Safety Hold berhasil ditetapkan. Pekerjaan DIJEDA demi keselamatan regu kerja.");
        } else {
            session()->setFlashdata('error', "Gagal menetapkan safety hold: " . ($result['message'] ?? 'Alasan tidak valid.'));
        }

        return redirect()->to(base_url('operational-planning/executions/detail/' . $eId));
    }

    /**
     * POST /operational-planning/executions/resume/(:num)
     */
    public function resumeHold($execId = null)
    {
        $eId             = (int)($execId ?? 1);
        $resumeRationale = (string)($this->request->getPost('resume_rationale') ?? '');

        $result = $this->executionService->resumeFromSafetyHold($eId, $resumeRationale);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Pekerjaan resmi DILANJUTKAN kembali setelah re-evaluasi K3.");
        } else {
            session()->setFlashdata('error', "Gagal melanjutkan pekerjaan: " . ($result['message'] ?? 'Alasan re-evaluasi wajib diisi.'));
        }

        return redirect()->to(base_url('operational-planning/executions/detail/' . $eId));
    }

    /**
     * POST /operational-planning/executions/complete/(:num)
     */
    public function declareCompletion($execId = null)
    {
        $eId       = (int)($execId ?? 1);
        $photoUri  = (string)($this->request->getPost('after_photo_uri') ?? '');
        $notes     = (string)($this->request->getPost('after_photo_notes') ?? '');
        $rationale = (string)($this->request->getPost('completion_declaration_rationale') ?? '');

        $afterEvidence = [
            'photo_uri' => $photoUri,
            'notes'     => $notes,
        ];

        $result = $this->executionService->declareWorkCompleted($eId, $afterEvidence, $rationale);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Deklarasi penyelesaian kerja berhasil dicatat oleh {$result['declared_by']}. Status: WORK_COMPLETED_PENDING_ACCEPTANCE.");
        } else {
            session()->setFlashdata('error', "Gagal mendeklarasikan penyelesaian: " . ($result['message'] ?? 'Bukti After Photo wajib diisi.'));
        }

        return redirect()->to(base_url('operational-planning/executions/detail/' . $eId));
    }

    /**
     * POST /operational-planning/executions/abort/(:num)
     */
    public function abort($execId = null)
    {
        $eId         = (int)($execId ?? 1);
        $abortReason = (string)($this->request->getPost('abort_reason') ?? 'Dibatalkan karena kendala lapangan');

        $result = $this->executionService->abortWork($eId, $abortReason);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Pekerjaan lapangan telah dibatalkan (WORK_ABORTED_FIELD_CONSTRAINTS).");
        } else {
            session()->setFlashdata('error', "Gagal membatalkan pekerjaan: " . ($result['message'] ?? 'Data tidak valid.'));
        }

        return redirect()->to(base_url('operational-planning/executions/detail/' . $eId));
    }
}
