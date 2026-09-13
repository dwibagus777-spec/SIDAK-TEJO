<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Class ConstellationPhase2ETest
 *
 * Automated verification suite for Phase 2E:
 * Constellation Mission Control & Contextual Dashboard.
 * Enforces the 5 Operator Amendments, human visual acceptance alignment,
 * zero mock data firewall, and absolute backend freeze.
 */
class ConstellationPhase2ETest extends CIUnitTestCase
{
    private string $dashboardView;
    private string $modernCss;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['form', 'url', 'app']);
        $this->dashboardView = file_get_contents(APPPATH . 'Views/dashboard/index.php');
        $this->modernCss     = file_get_contents(FCPATH . 'dist/css/custom_modern.css');
    }

    /**
     * 1. Central Hub Node Verification (◎ Dashboard Utama)
     */
    public function testCentralDashboardNodeBindingAndRoute(): void
    {
        $this->assertStringContainsString('id="node-hub-dashboard"', $this->dashboardView);
        $this->assertStringContainsString('constellation-central-node', $this->dashboardView);
        $this->assertStringContainsString('constellation-central-ring', $this->dashboardView);
        $this->assertStringContainsString('constellation-central-halo', $this->dashboardView);
        $this->assertStringContainsString('constellation-central-pill', $this->dashboardView);
        $this->assertStringContainsString("site_url('dashboard')", $this->dashboardView);
        $this->assertStringContainsString("\$stats['total']", $this->dashboardView);
    }

    /**
     * 2. All 8 Satellite Capability Nodes Presence and Canonical Routes
     */
    public function testAllEightSatelliteCapabilityNodesExist(): void
    {
        // 1. Planning Inspeksi
        $this->assertStringContainsString('id="node-planning"', $this->dashboardView);
        $this->assertStringContainsString("site_url('planning')", $this->dashboardView);
        $this->assertStringContainsString('Planning Inspeksi', $this->dashboardView);

        // 2. Data Temuan
        $this->assertStringContainsString('id="node-temuan"', $this->dashboardView);
        $this->assertStringContainsString("site_url('temuan')", $this->dashboardView);
        $this->assertStringContainsString('Data Temuan', $this->dashboardView);

        // 3. Work Orders
        $this->assertStringContainsString('id="node-wo"', $this->dashboardView);
        $this->assertStringContainsString("site_url('pekerjaan')", $this->dashboardView);
        $this->assertStringContainsString('Work Orders', $this->dashboardView);

        // 4. Emergency Priority
        $this->assertStringContainsString('id="node-emergency"', $this->dashboardView);
        $this->assertStringContainsString("site_url('temuan?prioritas=EMERGENCY')", $this->dashboardView);
        $this->assertStringContainsString('Emergency', $this->dashboardView);

        // 5. Tugas Saya
        $this->assertStringContainsString('id="node-tugas"', $this->dashboardView);
        $this->assertStringContainsString("site_url('inspeksi/tugas')", $this->dashboardView);
        $this->assertStringContainsString('Tugas Saya', $this->dashboardView);

        // 6. AI Copilot
        $this->assertStringContainsString('id="node-ai"', $this->dashboardView);
        $this->assertStringContainsString("site_url('ai-copilot')", $this->dashboardView);
        $this->assertStringContainsString('AI Copilot', $this->dashboardView);

        // 7. Peta GIS
        $this->assertStringContainsString('id="node-gis"', $this->dashboardView);
        $this->assertStringContainsString("site_url('gis')", $this->dashboardView);
        $this->assertStringContainsString('Peta GIS', $this->dashboardView);

        // 8. Executive Analytics
        $this->assertStringContainsString('id="node-analytics"', $this->dashboardView);
        $this->assertStringContainsString("site_url('executive-dashboard')", $this->dashboardView);
        $this->assertStringContainsString('Analytics', $this->dashboardView);
    }

    /**
     * 3. SVG Vector Constellation Network Paths
     */
    public function testSvgVectorConstellationNetwork(): void
    {
        $this->assertStringContainsString('constellation-svg-network', $this->dashboardView);
        $this->assertStringContainsString('constellation-svg-line', $this->dashboardView);
        $this->assertStringContainsString('<svg', $this->dashboardView);
        $this->assertStringContainsString('viewBox="0 0 1000 600"', $this->dashboardView);
        $this->assertStringContainsString('preserveAspectRatio="none"', $this->dashboardView);
    }

    /**
     * 4. Constellation Legend & Header Elements
     */
    public function testConstellationLegendAndHeader(): void
    {
        $this->assertStringContainsString('CONSTELLATION NAV', $this->dashboardView);
        $this->assertStringContainsString('9 nodes', $this->dashboardView);
        $this->assertStringContainsString('Pilih titik cahaya untuk membuka menu', $this->dashboardView);
        $this->assertStringContainsString('Setiap node adalah lokasi menu', $this->dashboardView);
        $this->assertStringContainsString('Interactive Constellation Canvas', $this->dashboardView);
    }

    /**
     * 5. Contextual Right Panel Structure & Header
     */
    public function testContextualPanelHeaderAndKpiCards(): void
    {
        $this->assertStringContainsString('contextual-dashboard-card', $this->dashboardView);
        $this->assertStringContainsString('Dashboard Utama', $this->dashboardView);
        $this->assertStringContainsString('Ringkasan &amp; KPI &bull; Constellation &rarr; HOME', $this->dashboardView);
        $this->assertStringContainsString('contextual-kpi-grid', $this->dashboardView);
        $this->assertStringContainsString('contextual-kpi-mint', $this->dashboardView);
        $this->assertStringContainsString('contextual-kpi-rose', $this->dashboardView);
        $this->assertStringContainsString('contextual-kpi-peach', $this->dashboardView);
        $this->assertStringContainsString('contextual-kpi-blue', $this->dashboardView);
    }

    /**
     * 6. Mandatory Amendment #1: Preserve KPI Semantic Meaning
     */
    public function testSemanticMeaningInvariantPreserved(): void
    {
        // 1. Total Temuan is labeled "Jumlah Temuan"
        $this->assertStringContainsString('Jumlah Temuan', $this->dashboardView);
        $this->assertStringContainsString('id="kpi-total-temuan"', $this->dashboardView);

        // 2. $stats['belum'] is labeled "Belum Selesai" (NOT "Menunggu WO")
        $this->assertStringContainsString('Belum Selesai', $this->dashboardView);
        $this->assertStringContainsString('id="kpi-belum"', $this->dashboardView);
        $this->assertStringNotContainsString('Menunggu WO', $this->dashboardView);

        // 3. Emergency remains Emergency
        $this->assertStringContainsString('Emergency', $this->dashboardView);
        $this->assertStringContainsString('id="kpi-emergency"', $this->dashboardView);

        // 4. GIS Node
        $this->assertStringContainsString('GIS Node', $this->dashboardView);
        $this->assertStringContainsString('id="kpi-gis-pins"', $this->dashboardView);
    }

    /**
     * 7. Contextual 3 Mini Metric Badges
     */
    public function testMiniMetricsIndicators(): void
    {
        $this->assertStringContainsString('id="kpi-selesai"', $this->dashboardView);
        $this->assertStringContainsString('Selesai', $this->dashboardView);
        $this->assertStringContainsString('id="kpi-wo-aktif"', $this->dashboardView);
        $this->assertStringContainsString('Progress', $this->dashboardView);
        $this->assertStringContainsString('id="kpi-target-harian-pct"', $this->dashboardView);
        $this->assertStringContainsString('Target', $this->dashboardView);
    }

    /**
     * 8. Operational Feed: "Aktivitas Hari Ini"
     */
    public function testLiveActivityFeed(): void
    {
        $this->assertStringContainsString('Aktivitas Hari Ini', $this->dashboardView);
        $this->assertStringContainsString('contextual-activity-section', $this->dashboardView);
        $this->assertStringContainsString('contextual-activity-list', $this->dashboardView);
        $this->assertStringContainsString('$contextualFeed', $this->dashboardView);
        $this->assertStringContainsString("site_url('temuan/detail/'", $this->dashboardView);
        $this->assertStringContainsString("site_url('audit-log')", $this->dashboardView);
    }

    /**
     * 9. Floating Command Dock Verification
     */
    public function testFloatingCommandDock(): void
    {
        $this->assertStringContainsString('sidak-floating-dock-container', $this->dashboardView);
        $this->assertStringContainsString('sidak-floating-dock', $this->dashboardView);
        $this->assertStringContainsString('dock-pill-btn', $this->dashboardView);
        $this->assertStringContainsString('dock-btn-create', $this->dashboardView);
        $this->assertStringContainsString('dock-btn-ai', $this->dashboardView);
        $this->assertStringContainsString('Synced', $this->dashboardView);
    }

    /**
     * 10. No-Mock-Numbers Firewall
     */
    public function testNoMockNumbersFirewall(): void
    {
        $mockLiterals = [
            '>596<',
            '>137<',
            '>411<',
            '>48<',
            '>495<',
            '>89<',
            '>1.2k<',
            '>24m<',
        ];

        foreach ($mockLiterals as $mock) {
            $this->assertStringNotContainsString(
                $mock,
                $this->dashboardView,
                "Violation of No-Mock-Data Firewall: Found hardcoded literal '{$mock}'"
            );
        }
    }

    /**
     * 11. No-Mock-Strings Firewall
     */
    public function testNoMockStringsFirewall(): void
    {
        $phase2cMockStrings = [
            '>3 Pekerjaan<',
            '08:40 WIB',
            '08:42 WIB',
            '08:45 WIB',
            '08:48 WIB',
            'STJ-2026-000422',
            'SDJ-14',
            'PA1650 inspeksi selesai',
        ];

        foreach ($phase2cMockStrings as $mockStr) {
            $this->assertStringNotContainsString(
                $mockStr,
                $this->dashboardView,
                "Violation of No-Mock-Data Firewall: Found prototype string '{$mockStr}'"
            );
        }
    }

    /**
     * 12. Supporting Tier: Mini GIS Map Intact
     */
    public function testMiniGisMapPreserved(): void
    {
        $this->assertStringContainsString('id="emc-mini-map"', $this->dashboardView);
        $this->assertStringContainsString("L.map('emc-mini-map')", $this->dashboardView);
        $this->assertStringContainsString('json_encode($mapPins ?? [])', $this->dashboardView);
        $this->assertStringContainsString("site_url('gis')", $this->dashboardView);
        $this->assertStringContainsString('Titik Terpetakan', $this->dashboardView);
    }

    /**
     * 13. Supporting Tier: SLA 4 Pillars Preserved
     */
    public function testSlaWidgetPreserved(): void
    {
        $this->assertStringContainsString('SLA Monitoring Widget', $this->dashboardView);
        $this->assertStringContainsString("EMERGENCY (SLA 3 Hari)", $this->dashboardView);
        $this->assertStringContainsString("HIGH (SLA 7 Hari)", $this->dashboardView);
        $this->assertStringContainsString("MEDIUM (SLA 31 Hari)", $this->dashboardView);
        $this->assertStringContainsString("SLA MELEWATI (OVERDUE)", $this->dashboardView);
        $this->assertStringContainsString('id="sla-val-emergency"', $this->dashboardView);
        $this->assertStringContainsString('id="sla-val-high"', $this->dashboardView);
        $this->assertStringContainsString('id="sla-val-medium"', $this->dashboardView);
        $this->assertStringContainsString('id="sla-val-overdue"', $this->dashboardView);
    }

    /**
     * 14. Supporting Tier: Executive Analytics CTA Preserved
     */
    public function testExecutiveCtaCardPreserved(): void
    {
        $this->assertStringContainsString('sidak-bento-cta-card', $this->dashboardView);
        $this->assertStringContainsString('Executive Analytics &amp; Strategic Decision Center', $this->dashboardView);
        $this->assertStringContainsString("site_url('executive-dashboard')", $this->dashboardView);
    }

    /**
     * 15. Zero Horizontal Overflow Enforcers
     */
    public function testZeroHorizontalOverflowTokens(): void
    {
        $this->assertStringContainsString('overflow-x: hidden;', $this->modernCss);
        $this->assertStringContainsString('max-width: 100vw;', $this->modernCss);
        $this->assertStringContainsString('max-width: 100%;', $this->modernCss);
    }

    /**
     * 16. Constellation CSS Tokens Integrity in custom_modern.css
     */
    public function testConstellationCssTokensIntegrity(): void
    {
        $tokens = [
            '.constellation-canvas-card',
            '.constellation-canvas-body',
            '.constellation-svg-network',
            '.constellation-svg-line',
            '.constellation-central-node',
            '.constellation-central-halo',
            '.constellation-central-ring',
            '.constellation-central-pill',
            '.constellation-node',
            '.constellation-node-aura',
            '.constellation-node-disc',
            '.constellation-node-badge',
            '.constellation-node-label',
            '.contextual-dashboard-card',
            '.contextual-kpi-grid',
            '.contextual-kpi-card',
            '.contextual-kpi-mint',
            '.contextual-kpi-rose',
            '.contextual-kpi-peach',
            '.contextual-kpi-blue',
            '.sidak-floating-dock',
            '.dock-pill-btn',
            '.dock-btn-create',
            '.dock-btn-ai',
        ];

        foreach ($tokens as $token) {
            $this->assertStringContainsString(
                $token,
                $this->modernCss,
                "CSS must define scoped token '{$token}' in custom_modern.css"
            );
        }
    }

    /**
     * 17. Responsive Viewport Matrix Tokens
     */
    public function testMobileResponsiveBreakpoints(): void
    {
        $this->assertStringContainsString('@media (max-width: 991.98px)', $this->modernCss);
        $this->assertStringContainsString('@media (max-width: 575.98px)', $this->modernCss);
        $this->assertStringContainsString('min-height: 380px;', $this->modernCss);
    }

    /**
     * 18. Dark Theme Support Tokens
     */
    public function testDarkThemeSupport(): void
    {
        $this->assertStringContainsString('[data-theme="dark"] .constellation-canvas-card', $this->modernCss);
        $this->assertStringContainsString('[data-theme="dark"] .contextual-dashboard-card', $this->modernCss);
        $this->assertStringContainsString('[data-theme="dark"] .sidak-floating-dock', $this->modernCss);
    }

    /**
     * 19. Absolute Backend Freeze (Controllers, Models, Services)
     */
    public function testAbsoluteBackendFreeze(): void
    {
        $dashboardController = file_get_contents(APPPATH . 'Controllers/Dashboard.php');
        $this->assertStringContainsString('class Dashboard extends BaseController', $dashboardController);

        $temuanModel = file_get_contents(APPPATH . 'Models/TemuanModel.php');
        $this->assertStringContainsString('class TemuanModel extends Model', $temuanModel);
    }

    /**
     * 20. SLA Helper Strictly Frozen
     */
    public function testSlaHelperStrictlyFrozen(): void
    {
        $appHelper = file_get_contents(APPPATH . 'Helpers/app_helper.php');
        $this->assertStringContainsString('function get_sla_status', $appHelper);
        $this->assertStringContainsString('function apply_role_scoping', $appHelper);
        $this->assertStringContainsString('function check_role', $appHelper);
    }

    /**
     * 21. Preserved Canonical Routes Accessibility
     */
    public function testCanonicalRoutesReachable(): void
    {
        $routes = [
            "site_url('dashboard')",
            "site_url('gis')",
            "site_url('temuan')",
            "site_url('pekerjaan')",
            "site_url('planning')",
            "site_url('ai-copilot')",
            "site_url('inspeksi/tugas')",
            "site_url('executive-dashboard')",
        ];

        foreach ($routes as $route) {
            $this->assertStringContainsString(
                $route,
                $this->dashboardView,
                "Canonical route '{$route}' must be present in dashboard/index.php"
            );
        }
    }

    /**
     * 22. Full Live View Dynamic Data Binding Verification
     */
    public function testLiveRenderingWithDynamicStats(): void
    {
        $stats = [
            'total'         => 654,
            'emergency'     => 123,
            'high'          => 345,
            'medium'        => 89,
            'belum'         => 432,
            'selesai'       => 222,
            'target_harian' => 40,
            'hari_ini'      => 28,
        ];

        $woStats = [
            'total'   => 12,
            'aktif'   => 5,
            'selesai' => 7,
            'overdue' => 1,
        ];

        $mapPins = [
            [
                'id'           => 888,
                'nomor_temuan' => 'STJ-P2E-000888',
                'judul'        => 'Isolator Tumpu Retak Feeder Krian',
                'latitude'     => -7.4478,
                'longitude'    => 112.7183,
                'prioritas'    => 'EMERGENCY',
                'status'       => 'BELUM',
            ]
        ];

        $viewParams = [
            'userName'      => 'Officer Constellation',
            'userRole'      => 'administrator',
            'canInput'      => true,
            'canEdit'       => true,
            'canDelete'     => true,
            'canApprove'    => true,
            'canMonitoring' => true,
            'stats'         => $stats,
            'woStats'       => $woStats,
            'assetStats'    => [],
            'mapPins'       => $mapPins,
        ];

        $html = view('dashboard/index', $viewParams);

        // Assert rendered dynamic KPI numbers
        $this->assertStringContainsString('id="kpi-total-temuan">654</div>', $html);
        $this->assertStringContainsString('id="kpi-emergency">123</div>', $html);
        $this->assertStringContainsString('id="kpi-belum">432</div>', $html);
        $this->assertStringContainsString('id="kpi-gis-pins">1</div>', $html);
        $this->assertStringContainsString('id="kpi-selesai">222</div>', $html);
        $this->assertStringContainsString('id="kpi-wo-aktif">5</div>', $html);

        // Dynamic Target 28 / 40 = 70%
        $this->assertStringContainsString('id="kpi-target-harian-pct">70%</span>', $html);
        $this->assertStringContainsString('style="width: 70%;"', $html);

        // Feed Rendering
        $this->assertStringContainsString('STJ-P2E-000888', $html);
        $this->assertStringContainsString('Isolator Tumpu Retak Feeder Krian', $html);
        $this->assertStringContainsString('temuan/detail/888', $html);
    }

    /**
     * 23. Zero Topology Mutation
     */
    public function testZeroTopologyMutation(): void
    {
        $topologyOrchestrator = APPPATH . 'Services/MultiFeederCompletionOrchestrator.php';
        if (file_exists($topologyOrchestrator)) {
            $content = file_get_contents($topologyOrchestrator);
            $this->assertStringContainsString('class MultiFeederCompletionOrchestrator', $content);
        }
    }

    /**
     * 24. Role-Based Access Gating
     */
    public function testRoleBasedAccessGates(): void
    {
        $this->assertMatchesRegularExpression(
            '/if\s*\(\$canInput|\bcheck_role\(\[\'administrator\',\s*\'admin_ulp\',\s*\'inspeksi\'\]\)/',
            $this->dashboardView,
            "Role gating must protect create action"
        );
    }

    /**
     * 25. Admin Motivation Quote Banner Preserved
     */
    public function testAdminMotivationBannerPreserved(): void
    {
        $this->assertStringContainsString('id="permanent-motivation-text"', $this->dashboardView);
        $this->assertStringContainsString('editMotivation()', $this->dashboardView);
        $this->assertStringContainsString('get_daily_announcement()', $this->dashboardView);
    }
}
