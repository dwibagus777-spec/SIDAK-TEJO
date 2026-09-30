<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\RemediationWorkPackageService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * RemediationWorkPackageController
 *
 * Web/UI controller for B8.1 Remediation — Phase 2A (Read-Only).
 *
 * ARCHITECTURAL CONTRACT:
 * - Session-based authentication handled by route filter 'auth' (NOT token-based).
 * - Phase 2A: Only GET methods active. POST mutations are held for Phase 2B.
 * - This controller is SEPARATE from RemediationController (API/forensic).
 * - Zero writes to the database in Phase 2A.
 * - Topology invariant: Δ = 0 always.
 */
class RemediationWorkPackageController extends BaseController
{
    protected RemediationWorkPackageService $wpService;

    /**
     * Allowed roles to access Remediation UI.
     * Conservative set — supervisor_ulp / supervisor_up3 may view but not mutate.
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
    ];

    public function __construct()
    {
        // Session auth is guaranteed by the route filter 'auth'.
        // We only need to instantiate the service here.
        $this->wpService = new RemediationWorkPackageService();
    }

    // ─────────────────────────────────────────────────────────────────
    // AUTHORIZATION HELPER
    // ─────────────────────────────────────────────────────────────────

    /**
     * Check that the current session role is in the allowed set.
     * Returns redirect to 403 page if unauthorized.
     *
     * @return RedirectResponse|null  null = OK, RedirectResponse = unauthorized
     */
    protected function checkRole(): ?RedirectResponse
    {
        $role = strtolower((string) session()->get('user_role'));
        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            return redirect()->to(site_url('errors/403'))->with('error', 'Anda tidak memiliki akses ke modul Remediasi.');
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // ROUTES (Phase 2A — GET only)
    // ─────────────────────────────────────────────────────────────────

    /**
     * GET /remediation
     * Main index — list all Work Packages with status summary.
     */
    public function index(): string|RedirectResponse
    {
        if ($guard = $this->checkRole()) {
            return $guard;
        }

        try {
            $workPackages = $this->wpService->getAllWorkPackages();
            $statusCounts = $this->wpService->getStatusCounts();
        } catch (\Throwable $e) {
            log_message('error', '[RemediationWorkPackageController::index] ' . $e->getMessage());
            $workPackages = [];
            $statusCounts = [];
        }

        $data = [
            'page_title'    => 'Work Package Remediasi',
            'work_packages' => $workPackages,
            'status_counts' => $statusCounts,
            'all_statuses'  => RemediationWorkPackageService::ALL_STATUSES,
        ];

        return view('remediation/index', $data);
    }

    /**
     * GET /remediation/work-packages
     * Alias for index (canonical listing).
     */
    public function workPackages(): string|RedirectResponse
    {
        return $this->index();
    }

    /**
     * GET /remediation/work-packages/{id}
     * Detail view of a single Work Package.
     */
    public function show(int $id): string|RedirectResponse
    {
        if ($guard = $this->checkRole()) {
            return $guard;
        }

        try {
            $wp = $this->wpService->getWorkPackage($id);
        } catch (\Throwable $e) {
            log_message('error', '[RemediationWorkPackageController::show] ' . $e->getMessage());
            $wp = null;
        }

        if ($wp === null) {
            return redirect()->to(site_url('remediation'))
                ->with('error', "Work Package #{$id} tidak ditemukan.");
        }

        try {
            $findings = $this->wpService->getWorkPackageFindings($id);
            $history  = $this->wpService->getWorkPackageHistory($id);
        } catch (\Throwable $e) {
            log_message('error', '[RemediationWorkPackageController::show] findings/history ' . $e->getMessage());
            $findings = [];
            $history  = [];
        }

        $data = [
            'page_title'   => 'Detail Work Package: ' . esc($wp['package_code'] ?? '#' . $id),
            'wp'           => $wp,
            'findings'     => $findings,
            'history'      => $history,
        ];

        return view('remediation/show', $data);
    }

    /**
     * GET /remediation/create
     * Display the "Create Work Package" form.
     * Phase 2A: display only — form POST is NOT wired yet.
     */
    public function create(): string|RedirectResponse
    {
        if ($guard = $this->checkRole()) {
            return $guard;
        }

        $data = [
            'page_title' => 'Buat Work Package Remediasi',
            'phase2a_notice' => true,   // Triggers UI notice that submission is inactive.
        ];

        return view('remediation/create', $data);
    }
}
