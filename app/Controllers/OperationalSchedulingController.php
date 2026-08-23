<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\OperationalSchedulingService;
use App\Services\OperationalPlanningPortfolioService;

/**
 * Operational Scheduling Controller (Wave 2 Phase OP-04)
 *
 * Responsibilities:
 * - Governed Scheduling & Resource Capacity Planning UI & API.
 * - Invariant: SCHEDULE_SCENARIO != CREW_DISPATCH, ZERO_AUTO_EXECUTION.
 */
class OperationalSchedulingController extends ResourceController
{
    protected $format = 'json';
    protected OperationalSchedulingService $schedulingService;
    protected OperationalPlanningPortfolioService $portfolioService;

    public function __construct()
    {
        $this->schedulingService = new OperationalSchedulingService();
        $this->portfolioService = new OperationalPlanningPortfolioService();
    }

    /**
     * GET /operational-planning/scheduling
     */
    public function index()
    {
        $scenarios = $this->schedulingService->getScenarios();
        $readyPortfolios = $this->schedulingService->getRatifiedPortfoliosReadyForScheduling();

        $data = [
            'title'           => 'Governed Scheduling & Capacity Planning',
            'scenarios'       => $scenarios,
            'readyPortfolios' => $readyPortfolios,
        ];

        return view('operational_planning/scheduling_list', $data);
    }

    /**
     * GET /operational-planning/scheduling/create/(:num)
     */
    public function create($portfolioId = null)
    {
        $pId = (int)($portfolioId ?? 1);
        $portfolioData = $this->portfolioService->getPortfolioDetail($pId);

        if (empty($portfolioData['portfolio'])) {
            session()->setFlashdata('error', "Portofolio #{$pId} tidak ditemukan.");
            return redirect()->to(base_url('operational-planning/scheduling'));
        }

        $portfolio = $portfolioData['portfolio'];
        if ($portfolio['portfolio_status'] !== 'PORTFOLIO_RATIFIED') {
            session()->setFlashdata('error', "Portofolio berstatus '{$portfolio['portfolio_status']}', hanya portofolio 'PORTFOLIO_RATIFIED' yang dapat dijadwalkan.");
            return redirect()->to(base_url('operational-planning/scheduling'));
        }

        $data = [
            'title'     => "Rancang Skenario Penjadwalan — {$portfolio['portfolio_code']}",
            'portfolio' => $portfolio,
            'items'     => $portfolioData['items'] ?? [],
        ];

        return view('operational_planning/scheduling_form', $data);
    }

    /**
     * POST /operational-planning/scheduling/store
     */
    public function store()
    {
        $portfolioId = (int)($this->request->getPost('portfolio_id') ?? 0);
        $title       = (string)($this->request->getPost('scenario_title') ?? 'Skenario Penjadwalan');
        $strategy    = (string)($this->request->getPost('scenario_strategy') ?? 'BALANCED_PDKB_PREFERRED');

        $result = $this->schedulingService->createScenarioDraft($portfolioId, $title, $strategy);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Skenario Penjadwalan {$result['scenario_code']} berhasil dirancang.");
            return redirect()->to(base_url('operational-planning/scheduling/detail/' . $result['scenario_id']));
        }

        session()->setFlashdata('error', "Gagal merancang skenario: " . ($result['message'] ?? 'Data tidak valid.'));
        return redirect()->to(base_url('operational-planning/scheduling'));
    }

    /**
     * GET /operational-planning/scheduling/detail/(:num)
     */
    public function detail($scenarioId = null)
    {
        $sId = (int)($scenarioId ?? 1);
        $data = $this->schedulingService->getScenarioDetail($sId);

        if (empty($data['scenario'])) {
            session()->setFlashdata('error', "Skenario Penjadwalan #{$sId} tidak ditemukan.");
            return redirect()->to(base_url('operational-planning/scheduling'));
        }

        $data['title'] = "Scheduling Scenario Detail — {$data['scenario']['scenario_code']}";

        return view('operational_planning/scheduling_detail', $data);
    }

    /**
     * POST /operational-planning/scheduling/slot/(:num)
     */
    public function updateSlot($slotId = null)
    {
        $slId        = (int)($slotId ?? 1);
        $scenarioId  = (int)($this->request->getPost('scenario_id') ?? 1);
        $date        = (string)($this->request->getPost('scheduled_date') ?? '');
        $start       = (string)($this->request->getPost('scheduled_start_time') ?? '08:30:00');
        $end         = (string)($this->request->getPost('scheduled_end_time') ?? '12:00:00');
        $duration    = (float)($this->request->getPost('estimated_duration_hours') ?? 4.0);
        $crewType    = (string)($this->request->getPost('estimated_crew_type') ?? 'REGU_PDKB_BERTEGANGAN');
        $outage      = (int)($this->request->getPost('outage_required') ?? 0);
        $isOverride  = (int)($this->request->getPost('capacity_override_applied') ?? 0);
        $notes       = (string)($this->request->getPost('scheduling_notes') ?? '');
        $rationale   = (string)($this->request->getPost('decision_rationale') ?? '');

        $slotData = [
            'scheduled_date'            => $date,
            'scheduled_start_time'      => $start,
            'scheduled_end_time'        => $end,
            'estimated_duration_hours'  => $duration,
            'estimated_crew_type'       => $crewType,
            'outage_required'           => $outage,
            'capacity_override_applied' => $isOverride,
            'scheduling_notes'          => $notes,
        ];

        $result = $this->schedulingService->updateSlot($slId, $slotData, $rationale);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Slot jadwal {$result['plan_code']} berhasil diperbarui.");
        } else {
            session()->setFlashdata('error', "Gagal memperbarui slot: " . ($result['message'] ?? 'Alasan tidak valid.'));
        }

        return redirect()->to(base_url('operational-planning/scheduling/detail/' . $scenarioId));
    }

    /**
     * POST /operational-planning/scheduling/transition/(:num)
     */
    public function transition($scenarioId = null)
    {
        $sId       = (int)($scenarioId ?? 1);
        $toStatus  = (string)($this->request->getPost('to_status') ?? '');
        $rationale = (string)($this->request->getPost('approval_rationale') ?? '');

        $result = $this->schedulingService->transitionScenarioStatus($sId, $toStatus, $rationale);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Status skenario berhasil diubah menjadi: {$toStatus}");
        } else {
            session()->setFlashdata('error', "Gagal mengubah status: " . ($result['message'] ?? 'Kelengkapan belum terpenuhi.'));
        }

        return redirect()->to(base_url('operational-planning/scheduling/detail/' . $sId));
    }

    /**
     * POST /operational-planning/scheduling/supersede/(:num)
     */
    public function supersede($scenarioId = null)
    {
        $sId       = (int)($scenarioId ?? 1);
        $rationale = (string)($this->request->getPost('supersede_rationale') ?? 'Skenario digantikan dengan skenario baru');

        $result = $this->schedulingService->supersedeScenario($sId, $rationale);

        if ($result['status'] === 'success') {
            session()->setFlashdata('success', "Skenario berhasil disupersede. Portofolio kini dapat dirancang skenario baru.");
        } else {
            session()->setFlashdata('error', "Gagal supersede: " . ($result['message'] ?? 'Gagal memproses.'));
        }

        return redirect()->to(base_url('operational-planning/scheduling'));
    }
}
