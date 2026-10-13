<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MaterialRequisitionService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * MaterialRequisitionController
 *
 * Web/UI controller for B8.2 Material Requisition — Phase 2A (Read-Only).
 *
 * ARCHITECTURAL CONTRACT:
 * - Session-based authentication handled by route filter 'auth' (NOT token-based).
 * - Phase 2A: Only GET methods active for user viewing.
 * - Zero writes to the database in Phase 2A.
 * - Topology invariant: Δ = 0 always.
 */
class MaterialRequisitionController extends BaseController
{
    protected MaterialRequisitionService $mrService;

    /**
     * Allowed roles to access Material Requisition UI.
     */
    protected const ALLOWED_ROLES = [
        'administrator',
        'admin',
        'admin_pusat',
        'admin_ulp',
        'inspeksi',
        'supervisor_ulp',
        'supervisor_up3',
        'pdkb',
        'warehouse_officer',
    ];

    public function __construct()
    {
        $this->mrService = new MaterialRequisitionService();
    }

    /**
     * Check that the current session role is in the allowed set.
     */
    protected function checkRole(): ?RedirectResponse
    {
        $role = strtolower((string) session()->get('user_role'));
        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            return redirect()->to(site_url('errors/403'))->with('error', 'Anda tidak memiliki akses ke modul Material Requisition.');
        }
        return null;
    }

    /**
     * GET /remediation/material-requisitions
     * Main index — list all Material Requisition Packages.
     */
    public function index(): string|RedirectResponse
    {
        if ($guard = $this->checkRole()) {
            return $guard;
        }

        try {
            $packages = $this->mrService->listRequisitionPackages();
        } catch (\Throwable $e) {
            log_message('error', '[MaterialRequisitionController::index] ' . $e->getMessage());
            $packages = [];
        }

        $statusCounts = [];
        foreach (MaterialRequisitionService::ALL_STATUSES as $st) {
            $statusCounts[$st] = 0;
        }
        foreach ($packages as $pkg) {
            if (isset($statusCounts[$pkg['status']])) {
                $statusCounts[$pkg['status']]++;
            }
        }

        $data = [
            'page_title'    => 'Material Requisition Core (B8.2)',
            'packages'      => $packages,
            'status_counts' => $statusCounts,
            'all_statuses'  => MaterialRequisitionService::ALL_STATUSES,
        ];

        return view('remediation/material_requisition_index', $data);
    }

    /**
     * GET /remediation/material-requisitions/(:num)
     * Detail view for a Material Requisition Package.
     */
    public function show(int $id): string|RedirectResponse
    {
        if ($guard = $this->checkRole()) {
            return $guard;
        }

        $package = $this->mrService->getRequisitionPackage($id);
        if ($package === null) {
            return redirect()->to(site_url('remediation/material-requisitions'))->with('error', "Material Requisition Package #{$id} tidak ditemukan.");
        }

        $data = [
            'page_title' => 'Detail Material Requisition ' . $package['requisition_code'],
            'package'    => $package,
        ];

        return view('remediation/material_requisition_detail', $data);
    }

    /**
     * GET /remediation/material-requisitions/create
     * Form view to create a new Material Requisition Package.
     */
    public function create(): string|RedirectResponse
    {
        if ($guard = $this->checkRole()) {
            return $guard;
        }

        $db = $this->mrService->getDb();
        $workPackages = $db->table('remediation_work_packages')
            ->select('id, package_code, title, status')
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();

        $warehouses = $db->table('material_warehouses')
            ->where('status', 'ACTIVE')
            ->orderBy('warehouse_name', 'ASC')
            ->get()
            ->getResultArray();

        $masterMaterials = $db->table('master_materials')
            ->select('id, material_code, nama_material, satuan')
            ->orderBy('nama_material', 'ASC')
            ->limit(100)
            ->get()
            ->getResultArray();

        $data = [
            'page_title'       => 'Buat Material Requisition Baru',
            'work_packages'    => $workPackages,
            'warehouses'       => $warehouses,
            'master_materials' => $masterMaterials,
        ];

        return view('remediation/material_requisition_create', $data);
    }
}
