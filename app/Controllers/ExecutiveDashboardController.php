<?php

namespace App\Controllers;

use App\Repositories\DashboardRepository;
use App\Models\UidModel;
use App\Models\Up3Model;
use App\Models\UlpModel;
use App\Models\TemuanModel;
use CodeIgniter\HTTP\ResponseInterface;

class ExecutiveDashboardController extends BaseController
{
    private DashboardRepository $dashboardRepository;
    private UidModel $uidModel;
    private Up3Model $up3Model;
    private UlpModel $ulpModel;
    private TemuanModel $temuanModel;

    public function __construct()
    {
        $this->dashboardRepository = new DashboardRepository();
        $this->uidModel           = new UidModel();
        $this->up3Model           = new Up3Model();
        $this->ulpModel           = new UlpModel();
        $this->temuanModel        = new TemuanModel();
    }

    /**
     * Resolve contextual unit scope from the database based on session role & IDs.
     * Enforces strict isolation: ULP -> UP3 -> UID -> GLOBAL.
     * Prevents fallbacks to guessing or hardcoded units.
     */
    public function resolveUserUnitScope(): array
    {
        $session   = session();
        $role      = strtolower((string)$session->get('user_role'));
        $userUlpId = $session->get('user_ulp_id') ?: $session->get('ulp_id');
        $userUp3Id = $session->get('user_up3_id') ?: $session->get('up3_id');
        $userUidId = $session->get('user_uid_id') ?: $session->get('uid_id');

        // 1. Operational / ULP Level Roles
        $ulpRoles = ['supervisor_ulp', 'admin_ulp', 'inspeksi', 'yantek', 'har_gardu', 'har_row', 'pdkb', 'har_crane'];
        if (in_array($role, $ulpRoles, true)) {
            if (empty($userUlpId)) {
                return [
                    'valid'         => false,
                    'error_message' => 'Akun Anda (' . strtoupper($role) . ') belum memiliki penugasan unit ULP yang sah di sistem master data.',
                    'role'          => $role,
                    'scope_level'   => 'ULP',
                ];
            }

            $ulp = $this->ulpModel->where('status', 'AKTIF')->where('id', (int)$userUlpId)->first();
            if (!$ulp) {
                return [
                    'valid'         => false,
                    'error_message' => 'Unit ULP penugasan Anda (ID: ' . (int)$userUlpId . ') tidak ditemukan atau berstatus non-aktif.',
                    'role'          => $role,
                    'scope_level'   => 'ULP',
                ];
            }

            $up3 = !empty($ulp['up3_id']) ? $this->up3Model->where('id', (int)$ulp['up3_id'])->first() : null;
            $ulpName = trim((string)$ulp['nama_ulp']);

            return [
                'valid'                 => true,
                'role'                  => $role,
                'scope_level'           => 'ULP',
                'assigned_unit_name'    => $ulpName,
                'parent_unit_name'      => $up3['nama_up3'] ?? null,
                'default_title'         => 'EXECUTIVE ANALYTICS DASHBOARD - ' . strtoupper($ulpName),
                'default_browser_title' => 'Executive Analytics Dashboard - ' . $ulpName . ' - SIDAK TEJO',
                'allowed_ulps'          => [$ulp],
                'allowed_ulp_ids'       => [(int)$ulp['id']],
                'is_locked_ulp'         => true,
                'locked_ulp_id'         => (int)$ulp['id'],
                'all_option_label'      => $ulpName,
            ];
        }

        // 2. UP3 / UP Level Roles (supervisor_up3, admin_up3, up3)
        if (in_array($role, ['supervisor_up3', 'admin_up3', 'up3'], true)) {
            if (empty($userUp3Id)) {
                return [
                    'valid'         => false,
                    'error_message' => 'Akun Anda (' . strtoupper($role) . ') belum memiliki penugasan unit UP3/UP yang sah di sistem master data.',
                    'role'          => $role,
                    'scope_level'   => 'UP3',
                ];
            }

            $up3 = $this->up3Model->where('status', 'AKTIF')->where('id', (int)$userUp3Id)->first();
            if (!$up3) {
                return [
                    'valid'         => false,
                    'error_message' => 'Unit UP3 penugasan Anda (ID: ' . (int)$userUp3Id . ') tidak ditemukan atau berstatus non-aktif.',
                    'role'          => $role,
                    'scope_level'   => 'UP3',
                ];
            }

            $ulps = $this->ulpModel->where('status', 'AKTIF')->where('up3_id', (int)$up3['id'])->orderBy('nama_ulp', 'ASC')->findAll();
            $allowedUlpIds = array_map('intval', array_column($ulps, 'id'));
            $up3Name = trim((string)$up3['nama_up3']);

            return [
                'valid'                 => true,
                'role'                  => $role,
                'scope_level'           => 'UP3',
                'assigned_unit_name'    => $up3Name,
                'parent_unit_name'      => null,
                'default_title'         => 'EXECUTIVE ANALYTICS DASHBOARD - ' . strtoupper($up3Name),
                'default_browser_title' => 'Executive Analytics Dashboard - ' . $up3Name . ' - SIDAK TEJO',
                'allowed_ulps'          => $ulps,
                'allowed_ulp_ids'       => $allowedUlpIds,
                'is_locked_ulp'         => false,
                'locked_ulp_id'         => null,
                'all_option_label'      => '-- Semua ULP (' . $up3Name . ') --',
            ];
        }

        // 3. UID / Pusat Level Roles (admin_pusat, manajemen_uid)
        if (in_array($role, ['admin_pusat', 'manajemen_uid'], true)) {
            $uid = null;
            if (!empty($userUidId)) {
                $uid = $this->uidModel->where('status', 'AKTIF')->where('id', (int)$userUidId)->first();
            }

            if ($uid) {
                $uidName = trim((string)$uid['nama_uid']);
                $up3s = $this->up3Model->where('status', 'AKTIF')->where('uid_id', (int)$uid['id'])->findAll();
                $up3Ids = array_map('intval', array_column($up3s, 'id'));
                $ulps = empty($up3Ids) ? [] : $this->ulpModel->where('status', 'AKTIF')->whereIn('up3_id', $up3Ids)->orderBy('nama_ulp', 'ASC')->findAll();
                $allowedUlpIds = array_map('intval', array_column($ulps, 'id'));

                return [
                    'valid'                 => true,
                    'role'                  => $role,
                    'scope_level'           => 'UID',
                    'assigned_unit_name'    => $uidName,
                    'parent_unit_name'      => null,
                    'default_title'         => 'EXECUTIVE ANALYTICS DASHBOARD - ' . strtoupper($uidName),
                    'default_browser_title' => 'Executive Analytics Dashboard - ' . $uidName . ' - SIDAK TEJO',
                    'allowed_ulps'          => $ulps,
                    'allowed_ulp_ids'       => $allowedUlpIds,
                    'is_locked_ulp'         => false,
                    'locked_ulp_id'         => null,
                    'all_option_label'      => '-- Semua Unit (' . $uidName . ') --',
                ];
            }
        }

        // 4. Global Administrator / Superadmin
        $allUlps = $this->ulpModel->where('status', 'AKTIF')->orderBy('nama_ulp', 'ASC')->findAll();
        $allowedUlpIds = array_map('intval', array_column($allUlps, 'id'));

        return [
            'valid'                 => true,
            'role'                  => $role,
            'scope_level'           => 'GLOBAL',
            'assigned_unit_name'    => 'Semua Unit',
            'parent_unit_name'      => null,
            'default_title'         => 'EXECUTIVE ANALYTICS DASHBOARD - SEMUA UNIT',
            'default_browser_title' => 'Executive Analytics Dashboard - Semua Unit - SIDAK TEJO',
            'allowed_ulps'          => $allUlps,
            'allowed_ulp_ids'       => $allowedUlpIds,
            'is_locked_ulp'         => false,
            'locked_ulp_id'         => null,
            'all_option_label'      => '-- Semua Unit (Lintas Wilayah) --',
        ];
    }

    /**
     * Resolve effective ULP filter, active unit label, and contextual title.
     * Throws an array with error status if cross-scope request is attempted.
     */
    private function resolveScopeFilter(array $scope, ?string $requestedUlp): array
    {
        if ($scope['is_locked_ulp']) {
            $lockedId = $scope['locked_ulp_id'];
            if ($requestedUlp !== null && $requestedUlp !== '' && (int)$requestedUlp !== $lockedId) {
                return [
                    'authorized' => false,
                    'message'    => 'Akses ditolak: Anda hanya berwenang mengakses data ' . $scope['assigned_unit_name'] . '.',
                ];
            }
            return [
                'authorized'       => true,
                'ulp_filter'       => $lockedId,
                'ulp_sel'          => $lockedId,
                'unit_name'        => $scope['assigned_unit_name'],
                'scope_title'      => $scope['default_title'],
                'browser_title'    => $scope['default_browser_title'],
                'unit_scope_label' => $scope['assigned_unit_name'],
            ];
        }

        // Role with multiple allowed ULPs (UP3, UID, or GLOBAL)
        if ($requestedUlp !== null && $requestedUlp !== '') {
            $reqId = (int)$requestedUlp;
            if (!in_array($reqId, $scope['allowed_ulp_ids'], true)) {
                return [
                    'authorized' => false,
                    'message'    => 'Akses ditolak: Unit ULP yang diminta (ID: ' . $reqId . ') berada di luar cakupan wewenang Anda.',
                ];
            }

            // Find specific ULP name from allowed list
            $ulpRow = null;
            foreach ($scope['allowed_ulps'] as $u) {
                if ((int)$u['id'] === $reqId) {
                    $ulpRow = $u;
                    break;
                }
            }
            $ulpName = $ulpRow ? trim((string)$ulpRow['nama_ulp']) : ('ULP #' . $reqId);
            $parentInfo = $scope['scope_level'] !== 'GLOBAL' ? ' (' . $scope['assigned_unit_name'] . ')' : '';

            return [
                'authorized'       => true,
                'ulp_filter'       => $reqId,
                'ulp_sel'          => $reqId,
                'unit_name'        => $ulpName,
                'scope_title'      => 'EXECUTIVE ANALYTICS DASHBOARD - ' . strtoupper($ulpName),
                'browser_title'    => 'Executive Analytics Dashboard - ' . $ulpName . ' - SIDAK TEJO',
                'unit_scope_label' => $ulpName . $parentInfo,
            ];
        }

        // Full scope aggregation (All allowed ULPs in this UP3 / UID / Global)
        return [
            'authorized'       => true,
            'ulp_filter'       => $scope['scope_level'] === 'GLOBAL' ? null : $scope['allowed_ulp_ids'],
            'ulp_sel'          => null,
            'unit_name'        => $scope['assigned_unit_name'],
            'scope_title'      => $scope['default_title'],
            'browser_title'    => $scope['default_browser_title'],
            'unit_scope_label' => $scope['assigned_unit_name'],
        ];
    }

    /**
     * Executive Dashboard View
     */
    public function index()
    {
        $session  = session();
        $userRole = strtolower((string)$session->get('user_role'));
        $userName = $session->get('user_name') ?: 'Executive';

        $scope = $this->resolveUserUnitScope();

        // 1. Handle user without valid unit assignment
        if (!$scope['valid']) {
            return view('dashboard/executive_enterprise', [
                'emptyUnitState'   => true,
                'emptyUnitMessage' => $scope['error_message'],
                'userRole'         => $userRole,
                'userName'         => $userName,
                'title'            => 'Executive Analytics Dashboard - Unit Belum Dikonfigurasi - SIDAK TEJO',
                'scopeTitle'       => 'EXECUTIVE ANALYTICS DASHBOARD - UNIT BELUM DIKONFIGURASI',
                'unitScopeLabel'   => 'Belum Dikonfigurasi',
                'allOptionLabel'   => '-- Tidak Ada Unit --',
                'ulps'             => [],
                'ulpSel'           => null,
                'bulanSel'         => (int)date('n'),
                'tahunSel'         => (int)date('Y'),
                'isLockedUlp'      => false,
                'lockedUlpId'      => null,
                'kpi'              => ['total' => 0, 'selesai' => 0, 'proses' => 0, 'belum' => 0, 'emergency' => 0, 'high' => 0, 'medium' => 0, 'rate' => 0.0],
                'lineData'         => ['labels' => [], 'temuan' => [], 'selesai' => []],
                'ulpBar'           => ['labels' => [], 'values' => []],
                'statusDonut'      => ['total' => 0, 'labels' => ['Belum', 'Proses', 'Selesai'], 'values' => [0, 0, 0], 'percentages' => [0, 0, 0]],
                'jenisPie'         => ['total' => 0, 'labels' => [], 'values' => [], 'percentages' => []],
                'prioPie'          => ['total' => 0, 'labels' => [], 'values' => [], 'percentages' => []],
            ]);
        }

        // 2. Validate requested ULP filter parameter
        $requestedUlp = $this->request->getGet('ulp_id');
        $resolved = $this->resolveScopeFilter($scope, $requestedUlp);

        if (!$resolved['authorized']) {
            // Reject unauthorized cross-scope request with HTTP 403 response
            return $this->response->setStatusCode(403)->setBody(
                view('errors/html/error_403', [
                    'message' => $resolved['message']
                ])
            );
        }

        $rawBulan = $this->request->getGet('bulan');
        $bulanSel = ($rawBulan !== null && $rawBulan !== '') ? (int)$rawBulan : (int)date('n');
        $rawTahun = $this->request->getGet('tahun');
        $tahunSel = ($rawTahun !== null && $rawTahun !== '') ? (int)$rawTahun : (int)date('Y');

        // Query metrics scoped to authorized filter
        $kpi           = $this->dashboardRepository->getDashboardKpi($resolved['ulp_filter'], $bulanSel, $tahunSel);
        $lineData      = $this->dashboardRepository->getLineChartData($resolved['ulp_filter'], $bulanSel, $tahunSel);
        $ulpBar        = $this->dashboardRepository->getUlpBarChartData($bulanSel, $tahunSel, $scope['scope_level'] === 'GLOBAL' ? null : $scope['allowed_ulp_ids']);
        $statusDonut   = $this->dashboardRepository->getStatusDonutData($resolved['ulp_filter'], $bulanSel, $tahunSel);
        $jenisPie      = $this->dashboardRepository->getJenisPieData($resolved['ulp_filter'], $bulanSel, $tahunSel);
        $prioPie       = $this->dashboardRepository->getPrioritasPieData($resolved['ulp_filter'], $bulanSel, $tahunSel);
        $agingData     = $this->dashboardRepository->getSlaAgingBacklog($resolved['ulp_filter'], $bulanSel, $tahunSel);
        $topFeeders    = $this->dashboardRepository->getTopCriticalFeeders($resolved['ulp_filter'], $bulanSel, $tahunSel);
        $pelaksanaData = $this->dashboardRepository->getPelaksanaBreakdown($resolved['ulp_filter'], $bulanSel, $tahunSel);

        return view('dashboard/executive_enterprise', [
            'emptyUnitState'   => false,
            'emptyUnitMessage' => '',
            'title'            => $resolved['browser_title'],
            'scopeTitle'       => $resolved['scope_title'],
            'unitScopeLabel'   => $resolved['unit_scope_label'],
            'allOptionLabel'   => $scope['all_option_label'],
            'userRole'         => $userRole,
            'userName'         => $userName,
            'ulps'             => $scope['allowed_ulps'],
            'bulanSel'         => $bulanSel,
            'tahunSel'         => $tahunSel,
            'ulpSel'           => $resolved['ulp_sel'],
            'isLockedUlp'      => $scope['is_locked_ulp'],
            'lockedUlpId'      => $scope['locked_ulp_id'],
            'kpi'              => $kpi,
            'lineData'         => $lineData,
            'ulpBar'           => $ulpBar,
            'statusDonut'      => $statusDonut,
            'jenisPie'         => $jenisPie,
            'prioPie'          => $prioPie,
            'agingData'        => $agingData,
            'topFeeders'       => $topFeeders,
            'pelaksanaData'    => $pelaksanaData,
        ]);
    }

    /**
     * Realtime AJAX Endpoint for Global Dashboard Filter Refresh
     */
    public function getChartData(): ResponseInterface
    {
        $scope = $this->resolveUserUnitScope();
        if (!$scope['valid']) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => $scope['error_message'],
            ]);
        }

        $requestedUlp = $this->request->getGet('ulp_id');
        $resolved = $this->resolveScopeFilter($scope, $requestedUlp);

        if (!$resolved['authorized']) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => $resolved['message'],
            ]);
        }

        $rawBulan = $this->request->getGet('bulan');
        $bulan    = ($rawBulan !== null && $rawBulan !== '') ? (int)$rawBulan : null;
        $rawTahun = $this->request->getGet('tahun');
        $tahun    = ($rawTahun !== null && $rawTahun !== '') ? (int)$rawTahun : (int)date('Y');

        $kpi           = $this->dashboardRepository->getDashboardKpi($resolved['ulp_filter'], $bulan, $tahun);
        $lineData      = $this->dashboardRepository->getLineChartData($resolved['ulp_filter'], $bulan, $tahun);
        $ulpBar        = $this->dashboardRepository->getUlpBarChartData($bulan, $tahun, $scope['scope_level'] === 'GLOBAL' ? null : $scope['allowed_ulp_ids']);
        $statusDonut   = $this->dashboardRepository->getStatusDonutData($resolved['ulp_filter'], $bulan, $tahun);
        $jenisPie      = $this->dashboardRepository->getJenisPieData($resolved['ulp_filter'], $bulan, $tahun);
        $prioPie       = $this->dashboardRepository->getPrioritasPieData($resolved['ulp_filter'], $bulan, $tahun);
        $agingData     = $this->dashboardRepository->getSlaAgingBacklog($resolved['ulp_filter'], $bulan, $tahun);
        $topFeeders    = $this->dashboardRepository->getTopCriticalFeeders($resolved['ulp_filter'], $bulan, $tahun);
        $pelaksanaData = $this->dashboardRepository->getPelaksanaBreakdown($resolved['ulp_filter'], $bulan, $tahun);

        return $this->response->setStatusCode(200)->setJSON([
            'success'          => true,
            'timestamp'        => date('Y-m-d H:i:s'),
            'unit_name'        => $resolved['unit_name'],
            'scope_title'      => $resolved['scope_title'],
            'browser_title'    => $resolved['browser_title'],
            'unit_scope_label' => $resolved['unit_scope_label'],
            'filters'          => [
                'ulp_id' => $resolved['ulp_sel'],
                'bulan'  => $bulan,
                'tahun'  => $tahun,
            ],
            'kpi'              => $kpi,
            'line_chart'       => $lineData,
            'ulp_bar'          => $ulpBar,
            'status_donut'     => $statusDonut,
            'jenis_pie'        => $jenisPie,
            'prio_pie'         => $prioPie,
            'aging'            => $agingData,
            'top_feeders'      => $topFeeders,
            'pelaksana'        => $pelaksanaData,
        ]);
    }

    /**
     * Export Executive Dashboard Summary as CSV
     * GET executive-dashboard/export
     */
    public function export(): ResponseInterface
    {
        $scope = $this->resolveUserUnitScope();
        if (!$scope['valid']) {
            return $this->response->setStatusCode(403)->setBody('Akses Ditolak: ' . $scope['error_message']);
        }

        $requestedUlp = $this->request->getGet('ulp_id');
        $resolved = $this->resolveScopeFilter($scope, $requestedUlp);

        if (!$resolved['authorized']) {
            return $this->response->setStatusCode(403)->setBody('Akses Ditolak: ' . $resolved['message']);
        }

        $rawBulan = $this->request->getGet('bulan');
        $bulan    = ($rawBulan !== null && $rawBulan !== '') ? (int)$rawBulan : null;
        $rawTahun = $this->request->getGet('tahun');
        $tahun    = ($rawTahun !== null && $rawTahun !== '') ? (int)$rawTahun : (int)date('Y');

        $kpi         = $this->dashboardRepository->getDashboardKpi($resolved['ulp_filter'], $bulan, $tahun);
        $statusDonut = $this->dashboardRepository->getStatusDonutData($resolved['ulp_filter'], $bulan, $tahun);
        $jenisPie    = $this->dashboardRepository->getJenisPieData($resolved['ulp_filter'], $bulan, $tahun);
        $prioPie     = $this->dashboardRepository->getPrioritasPieData($resolved['ulp_filter'], $bulan, $tahun);
        $ulpBar      = $this->dashboardRepository->getUlpBarChartData($bulan, $tahun, $scope['scope_level'] === 'GLOBAL' ? null : $scope['allowed_ulp_ids']);

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $periodeLabel = $bulan ? ($monthNames[$bulan] ?? $bulan) . ' ' . $tahun : 'Semua Bulan (Tahunan) ' . $tahun;

        // Build CSV output with UTF-8 BOM
        $out = "\xEF\xBB\xBF";
        $out .= "SIDAK TEJO - EXECUTIVE ANALYTICS DASHBOARD EXPORT\n";
        $out .= "Cakupan Unit," . '"' . str_replace('"', '""', $resolved['unit_scope_label']) . '"' . "\n";
        $out .= "Periode," . '"' . $periodeLabel . '"' . "\n";
        $out .= "Tanggal Ekspor," . date('Y-m-d H:i:s') . "\n";
        $out .= "Dicetak Oleh," . '"' . str_replace('"', '""', (session()->get('user_name') ?: 'User')) . ' (' . strtoupper($scope['role']) . ')"' . "\n\n";

        // Section 1: KPI
        $out .= "=== 1. RINGKASAN KPI EKSEKUTIF ===\n";
        $out .= "Metrik,Jumlah\n";
        $out .= "Total Temuan," . $kpi['total'] . "\n";
        $out .= "Temuan Selesai," . $kpi['selesai'] . "\n";
        $out .= "Dalam Proses," . $kpi['proses'] . "\n";
        $out .= "Belum Ditindak," . $kpi['belum'] . "\n";
        $out .= "Prioritas Emergency," . $kpi['emergency'] . "\n";
        $out .= "Prioritas High," . $kpi['high'] . "\n";
        $out .= "Prioritas Medium," . $kpi['medium'] . "\n";
        $out .= "Penyelesaian SLA (%)," . $kpi['rate'] . "%\n\n";

        // Section 2: Status Breakdown
        $out .= "=== 2. BREAKDOWN STATUS ===\n";
        $out .= "Status,Jumlah,Persentase (%)\n";
        foreach ($statusDonut['labels'] as $idx => $lbl) {
            $out .= '"' . $lbl . '",' . ($statusDonut['values'][$idx] ?? 0) . ',' . ($statusDonut['percentages'][$idx] ?? 0) . "%\n";
        }
        $out .= "\n";

        // Section 3: Jenis Temuan
        $out .= "=== 3. BREAKDOWN JENIS TEMUAN ===\n";
        $out .= "Jenis Temuan,Jumlah,Persentase (%)\n";
        foreach ($jenisPie['labels'] as $idx => $lbl) {
            $out .= '"' . $lbl . '",' . ($jenisPie['values'][$idx] ?? 0) . ',' . ($jenisPie['percentages'][$idx] ?? 0) . "%\n";
        }
        $out .= "\n";

        // Section 4: Prioritas
        $out .= "=== 4. BREAKDOWN PRIORITAS ===\n";
        $out .= "Prioritas,Jumlah,Persentase (%)\n";
        foreach ($prioPie['labels'] as $idx => $lbl) {
            $out .= '"' . $lbl . '",' . ($prioPie['values'][$idx] ?? 0) . ',' . ($prioPie['percentages'][$idx] ?? 0) . "%\n";
        }
        $out .= "\n";

        // Section 5: Performa ULP
        $out .= "=== 5. PERFORMA PER UNIT ULP ===\n";
        $out .= "Unit ULP,Total Temuan\n";
        foreach ($ulpBar['labels'] as $idx => $lbl) {
            $out .= '"' . $lbl . '",' . ($ulpBar['values'][$idx] ?? 0) . "\n";
        }

        $sanitizedUnit = preg_replace('/[^A-Za-z0-9_-]/', '_', $resolved['unit_name']);
        $filename = "Executive_Dashboard_{$sanitizedUnit}_{$tahun}" . ($bulan ? "_{$bulan}" : "") . ".csv";

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($out);
    }

    /**
     * Dashboard Summary JSON
     */
    public function getSummary(): ResponseInterface
    {
        return $this->getChartData();
    }

    /**
     * Dashboard KPI JSON
     */
    public function getKpiData(): ResponseInterface
    {
        return $this->getChartData();
    }
}
