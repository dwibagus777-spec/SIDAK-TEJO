<?php

namespace App\Services;

use App\AI\WorkOrderOptimizer;
use App\Repositories\WorkOrderRepository;
use App\Repositories\TemuanRepository;
use App\Repositories\AssetRepository;

class SmartWorkOrderService
{
    private WorkOrderOptimizer $optimizer;
    private WorkOrderRepository $woRepo;
    private TemuanRepository $temuanRepo;
    private AssetRepository $assetRepo;

    public function __construct()
    {
        $this->optimizer  = new WorkOrderOptimizer();
        $this->woRepo     = new WorkOrderRepository();
        $this->temuanRepo = new TemuanRepository();
        $this->assetRepo  = new AssetRepository();
    }

    /**
     * Get Smart WO Analytics & Optimized Work Order Schedule
     */
    public function getSmartWoAnalytics(?int $userUlpId = null): array
    {
        $woList = $this->woRepo->getFilteredWorkOrders([], $userUlpId);
        $optimizedWos = $this->optimizer->optimizeRouteSequence($woList);

        // Calculate Workload & SLA Compliance
        $totalWo = count($woList);
        $criticalWo = 0; $highWo = 0; $completedWo = 0;

        foreach ($optimizedWos as &$wo) {
            $prioRes = $this->optimizer->calculateAutoPriority($wo);
            $wo['auto_priority'] = $prioRes['priority'];
            $wo['badge_class']   = $prioRes['badge'];
            $wo['sla_hours']     = $prioRes['sla_hours'];

            if ($prioRes['priority'] === 'CRITICAL') $criticalWo++;
            if ($prioRes['priority'] === 'HIGH') $highWo++;
            if (($wo['status'] ?? '') === 'COMPLETED') $completedWo++;
        }
        unset($wo);

        // Required Materials & Checklist Template
        $requiredMaterials = [
            ['name' => 'Isolator Tumpu 20KV', 'qty' => 12, 'status' => 'READY'],
            ['name' => 'Connector Parallel Groove (PG)', 'qty' => 24, 'status' => 'READY'],
            ['name' => 'Kabel AAAC 150mm²', 'qty' => 50, 'unit' => 'meter', 'status' => 'READY'],
            ['name' => 'Minyak Trafo Shell Diala', 'qty' => 20, 'unit' => 'liter', 'status' => 'LIMITED'],
        ];

        $digitalChecklist = [
            ['task' => 'Pemeriksaan Safety Briefing & APD Lengkap', 'done' => true],
            ['task' => 'Pengukuran Tegangan & Beban Awal', 'done' => true],
            ['task' => 'Pengisolasian Grounding & Penanganan Hotspot', 'done' => false],
            ['task' => 'Pengujian Thermovision Pasca Maintenance', 'done' => false],
            ['task' => 'Berita Acara Digital & Tanda Tangan Mandor', 'done' => false],
        ];

        return [
            'optimized_wos'      => array_slice($optimizedWos, 0, 15),
            'total_wo'           => $totalWo,
            'critical_wo'        => $criticalWo,
            'high_wo'            => $highWo,
            'completed_wo'       => $completedWo,
            'sla_compliance'     => $totalWo > 0 ? round(($completedWo / $totalWo) * 100, 1) : 100,
            'materials'          => $requiredMaterials,
            'checklist'          => $digitalChecklist,
            'est_travel_minutes' => 25,
            'est_job_duration'   => '2 Jam 30 Menit',
        ];
    }
}
