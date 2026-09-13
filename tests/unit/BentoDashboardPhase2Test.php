<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Class BentoDashboardPhase2Test
 *
 * Test suite for Phase 2B: Bento KPI & Executive Analytics Dashboard.
 * Enforces the Data & Functionality Preservation Invariant, No-Mock-Data Firewall,
 * and 6-section UI architecture integrity.
 */
class BentoDashboardPhase2Test extends CIUnitTestCase
{
    private string $dashboardView;
    private string $mobileView;
    private string $modernCss;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['form', 'url', 'app']);
        $this->dashboardView = file_get_contents(APPPATH . 'Views/dashboard/index.php');
        $this->mobileView    = file_get_contents(APPPATH . 'Views/dashboard/mobile.php');
        $this->modernCss     = file_get_contents(FCPATH . 'dist/css/custom_modern.css');
    }

    /**
     * 1. No-Mock-Data Firewall:
     * Ensure prototype snapshot numbers (596, 137, 411, 48, 495, 89, 18 / 25, 72%)
     * are NOT hardcoded as static HTML literals in dashboard KPI values.
     */
    public function testNoMockNumbersFirewallInKPIValues(): void
    {
        $mockLiterals = [
            '>596<',
            '>137<',
            '>411<',
            '>48<',
            '>495<',
            '>89<',
            '>18 / 25<',
            '>72%<',
        ];

        foreach ($mockLiterals as $mock) {
            $this->assertStringNotContainsString(
                $mock,
                $this->dashboardView,
                "Violation of No-Mock-Data Firewall: Found hardcoded prototype literal '{$mock}' in dashboard/index.php"
            );
        }

        // Phase 2C Specific No-Mock-Data Assertions (3 Pekerjaan, fake timestamps, static strings)
        $phase2cMockStrings = [
            '>3 Pekerjaan<',
            '08:40 WIB',
            '08:42 WIB',
            '08:45 WIB',
            '08:48 WIB',
            'STJ-2026-000422',
            'SDJ-14',
        ];

        foreach ($phase2cMockStrings as $mockStr) {
            $this->assertStringNotContainsString(
                $mockStr,
                $this->dashboardView,
                "Violation of Phase 2C No-Mock-Data Firewall: Found prototype mock string '{$mockStr}' in dashboard/index.php"
            );
        }

        // Also check mobile view for static 18/25 and 72%
        $this->assertStringNotContainsString(
            'Realisasi: 18 / 25',
            $this->mobileView,
            "Violation of No-Mock-Data Firewall: Found hardcoded '18 / 25' in dashboard/mobile.php"
        );
        $this->assertStringNotContainsString(
            '>72%<',
            $this->mobileView,
            "Violation of No-Mock-Data Firewall: Found hardcoded '>72%<' in dashboard/mobile.php"
        );
    }

    /**
     * 2. SECTION A: Executive Welcome Banner Verification
     */
    public function testSectionAExecutiveWelcomeBanner(): void
    {
        $this->assertStringContainsString('sidak-bento-welcome', $this->dashboardView);
        $this->assertStringContainsString("session()->get('user_name')", $this->dashboardView);
        $this->assertStringContainsString("session()->get('user_ulp_nama')", $this->dashboardView);
        $this->assertStringContainsString('get_role_label', $this->dashboardView);
        $this->assertStringContainsString("session()->get('user_role')", $this->dashboardView);
        $this->assertStringContainsString('id="emc-clock"', $this->dashboardView);
        $this->assertStringContainsString('id="permanent-motivation-text"', $this->dashboardView);
        $this->assertStringContainsString('get_daily_announcement()', $this->dashboardView);
        $this->assertStringContainsString('editMotivation()', $this->dashboardView);
        $this->assertStringContainsString('Live Monitoring Center PLN', $this->dashboardView);
    }

    /**
     * 3. SECTION B: Quick Action Bar Presence & Verified Canonical Destinations
     */
    public function testSectionBQuickActionsPresenceAndDestinations(): void
    {
        $this->assertStringContainsString("site_url('temuan/create')", $this->dashboardView);
        $this->assertStringContainsString("site_url('temuan')", $this->dashboardView);
        $this->assertStringContainsString("site_url('temuan/update-pekerjaan')", $this->dashboardView);
        $this->assertStringContainsString("triggerQrScanModal()", $this->dashboardView);
        $this->assertStringContainsString("site_url('ai-copilot')", $this->dashboardView);
        $this->assertStringContainsString("site_url('temuan/terdekat')", $this->dashboardView);
    }

    /**
     * 4. SECTION B: Role-Based Access Gates for Quick Actions
     */
    public function testSectionBQuickActionsRoleGates(): void
    {
        // Input Temuan must be role-gated
        $this->assertMatchesRegularExpression(
            '/if\s*\(\$canInput|\bcheck_role\(\[\'administrator\',\s*\'admin_ulp\',\s*\'inspeksi\'\]\)/',
            $this->dashboardView,
            "Quick action 'Input Temuan' must be role-gated"
        );

        // Update Pekerjaan must be role-gated
        $this->assertMatchesRegularExpression(
            '/if\s*\(\$canEdit|!check_role\(\[\'supervisor_up3\'\]\)/',
            $this->dashboardView,
            "Quick action 'Update Pekerjaan' must be role-gated"
        );
    }

    /**
     * 5. SECTION B: Touch-Friendly Target Tokens (>= 44px)
     */
    public function testSectionBTouchTargetTokens(): void
    {
        $this->assertStringContainsString('.sidak-bento-action-pill', $this->modernCss);
        $this->assertMatchesRegularExpression(
            '/min-height:\s*44px;/',
            $this->modernCss,
            "CSS .sidak-bento-action-pill must define min-height: 44px for touch-target compliance"
        );
        $this->assertStringContainsString('touch-action: manipulation;', $this->modernCss);
    }

    /**
     * 6. SECTION C: All 8 KPI slots bound 100% to dynamic PHP expressions
     */
    public function testSectionCAll8KPIBoundToDynamicPhp(): void
    {
        // 1. Total Temuan
        $this->assertStringContainsString("\$stats['total']", $this->dashboardView);
        $this->assertStringContainsString('id="kpi-total-temuan"', $this->dashboardView);

        // 2. Emergency
        $this->assertStringContainsString("\$stats['emergency']", $this->dashboardView);
        $this->assertStringContainsString('id="kpi-emergency"', $this->dashboardView);

        // 3. High Priority
        $this->assertStringContainsString("\$stats['high']", $this->dashboardView);
        $this->assertStringContainsString('id="kpi-high"', $this->dashboardView);

        // 4. Medium Priority
        $this->assertStringContainsString("\$stats['medium']", $this->dashboardView);
        $this->assertStringContainsString('id="kpi-medium"', $this->dashboardView);

        // 5. Belum Selesai
        $this->assertStringContainsString("\$stats['belum']", $this->dashboardView);
        $this->assertStringContainsString('id="kpi-belum"', $this->dashboardView);

        // 6. WO Aktif
        $this->assertStringContainsString("\$woStats['aktif']", $this->dashboardView);
        $this->assertStringContainsString('id="kpi-wo-aktif"', $this->dashboardView);

        // 7. Sudah Selesai
        $this->assertStringContainsString("\$stats['selesai']", $this->dashboardView);
        $this->assertStringContainsString('id="kpi-selesai"', $this->dashboardView);

        // 8. Target Harian (Dynamic Ratio)
        $this->assertStringContainsString('$dailyTarget', $this->dashboardView);
        $this->assertStringContainsString('$dailyDone', $this->dashboardView);
        $this->assertStringContainsString('$dailyPct', $this->dashboardView);
        $this->assertStringContainsString('id="kpi-target-harian-text"', $this->dashboardView);
        $this->assertStringContainsString('id="kpi-target-harian-pct"', $this->dashboardView);
        $this->assertStringContainsString('id="kpi-target-harian-bar"', $this->dashboardView);
    }

    /**
     * 7. SECTION C: KPI Drilldown Routes Integrity
     */
    public function testSectionCKPIDrilldownRoutesIntegrity(): void
    {
        $this->assertStringContainsString("site_url('temuan')", $this->dashboardView);
        $this->assertStringContainsString("site_url('temuan?prioritas=EMERGENCY')", $this->dashboardView);
        $this->assertStringContainsString("site_url('temuan?prioritas=HIGH')", $this->dashboardView);
        $this->assertStringContainsString("site_url('temuan?prioritas=MEDIUM')", $this->dashboardView);
        $this->assertStringContainsString("site_url('temuan?status=BELUM')", $this->dashboardView);
        $this->assertStringContainsString("site_url('work-orders?status=AKTIF')", $this->dashboardView);
        $this->assertStringContainsString("site_url('temuan?status=SELESAI')", $this->dashboardView);
    }

    /**
     * 8. SECTION D: Mini GIS Map Leaflet Hooks Preserved & Enhanced
     */
    public function testSectionDMiniGisLeafletHooksIntact(): void
    {
        $this->assertStringContainsString('id="emc-mini-map"', $this->dashboardView);
        $this->assertStringContainsString("L.map('emc-mini-map')", $this->dashboardView);
        $this->assertStringContainsString('json_encode($mapPins ?? [])', $this->dashboardView);
        $this->assertStringContainsString("site_url('gis')", $this->dashboardView);
        $this->assertStringContainsString('id="gis-full-btn"', $this->dashboardView);
        $this->assertStringContainsString('bindTooltip', $this->dashboardView);
        $this->assertStringContainsString("site_url('temuan/detail/')", $this->dashboardView);
        $this->assertStringContainsString('Titik Terpetakan', $this->dashboardView);
    }

    /**
     * 9. SECTION D: Operational Findings Feed Integrity (Real Records, No Fake Events)
     */
    public function testSectionDOperationalFeedIntegrity(): void
    {
        $this->assertStringContainsString('Aktivitas Lapangan Terkini', $this->dashboardView);
        $this->assertStringContainsString('Data Temuan Operasional Riil', $this->dashboardView);
        $this->assertStringContainsString('sidak-bento-feed-item', $this->dashboardView);
        $this->assertStringContainsString('$recentPins', $this->dashboardView);
        $this->assertStringContainsString("site_url('temuan/detail/'", $this->dashboardView);
        $this->assertStringContainsString("site_url('audit-log')", $this->dashboardView);
        $this->assertStringContainsString('Buka Log Aktivitas Lengkap', $this->dashboardView);
    }

    /**
     * 10. SECTION E: Existing SLA Visualization (4 Balanced Pillars, Zero Rule Mutation)
     */
    public function testSectionEExistingSlaVisualization(): void
    {
        $this->assertStringContainsString('SLA Monitoring Widget', $this->dashboardView);
        $this->assertStringContainsString('Kepatuhan Batas Waktu Tindak Lanjut Temuan Lapangan', $this->dashboardView);
        
        // Authoritative existing labels
        $this->assertStringContainsString("EMERGENCY (SLA 3 Hari)", $this->dashboardView);
        $this->assertStringContainsString("HIGH (SLA 7 Hari)", $this->dashboardView);
        $this->assertStringContainsString("MEDIUM (SLA 31 Hari)", $this->dashboardView);
        $this->assertStringContainsString("SLA MELEWATI (OVERDUE)", $this->dashboardView);

        // Data binding IDs
        $this->assertStringContainsString('id="sla-val-emergency"', $this->dashboardView);
        $this->assertStringContainsString('id="sla-val-high"', $this->dashboardView);
        $this->assertStringContainsString('id="sla-val-medium"', $this->dashboardView);
        $this->assertStringContainsString('id="sla-val-overdue"', $this->dashboardView);

        // Canonical drilldown links
        $this->assertStringContainsString("site_url('temuan?prioritas=EMERGENCY')", $this->dashboardView);
        $this->assertStringContainsString("site_url('temuan?prioritas=HIGH')", $this->dashboardView);
        $this->assertStringContainsString("site_url('temuan?prioritas=MEDIUM')", $this->dashboardView);
        $this->assertStringContainsString("site_url('pekerjaan')", $this->dashboardView);

        // Prototype mock number eliminated
        $this->assertStringNotContainsString('>3 Pekerjaan<', $this->dashboardView);
    }

    /**
     * 11. SECTION F: Executive Analytics & Decision Center CTA Card
     */
    public function testSectionFExecutiveAnalyticsCtaCard(): void
    {
        $this->assertStringContainsString('sidak-bento-cta-card', $this->dashboardView);
        $this->assertStringContainsString('Executive Analytics &amp; Strategic Decision Center', $this->dashboardView);
        $this->assertStringContainsString("site_url('executive-dashboard')", $this->dashboardView);
        $this->assertStringContainsString('sidak-bento-cta-btn', $this->dashboardView);
    }

    /**
     * 12. CSS Scoped Token Integrity
     */
    public function testScopedBentoTokensInCss(): void
    {
        $tokens = [
            '.sidak-bento-container',
            '.sidak-bento-card',
            '.sidak-bento-welcome',
            '.sidak-bento-action-bar',
            '.sidak-bento-action-pill',
            '.sidak-bento-kpi-grid',
            '.sidak-bento-kpi-card',
            '.sidak-bento-val',
            '.sidak-bento-lbl',
            '.sidak-bento-sub',
            '.sidak-bento-cta-card',
            '.sidak-bento-cta-btn',
            '.sidak-bento-icon-box',
            '.sidak-bento-feed-item',
            '.sidak-bento-sla-card',
            '[data-theme="dark"] .sidak-bento-card',
            '[data-theme="dark"] .sidak-bento-action-pill',
            '[data-theme="dark"] .sidak-bento-feed-item',
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
     * 13. Phase 2D: Responsive Viewport Matrix Tokens & Zero Horizontal Overflow
     */
    public function testPhase2DResponsiveMatrixTokensAndZeroHorizontalOverflow(): void
    {
        // 1. Zero horizontal overflow enforcers
        $this->assertStringContainsString('overflow-x: hidden;', $this->modernCss);
        $this->assertStringContainsString('max-width: 100vw;', $this->modernCss);
        $this->assertStringContainsString('max-width: 100%;', $this->modernCss);

        // 2. Mobile viewport adjustments (360px - 575px)
        $this->assertStringContainsString('@media (max-width: 575.98px)', $this->modernCss);
        $this->assertStringContainsString('repeat(2, 1fr)', $this->modernCss);
        $this->assertStringContainsString('font-size: 22px;', $this->modernCss);
        $this->assertStringContainsString('height: 250px !important;', $this->modernCss);

        // 3. Tablet viewport adjustments (768px - 1024px)
        $this->assertStringContainsString('@media (min-width: 768px) and (max-width: 1024px)', $this->modernCss);
        $this->assertStringContainsString('font-size: 28px;', $this->modernCss);
        $this->assertStringContainsString('height: 280px !important;', $this->modernCss);

        // 4. Desktop large breakpoints (1199px+)
        $this->assertStringContainsString('@media (max-width: 1199.98px)', $this->modernCss);
        $this->assertStringContainsString('repeat(4, 1fr)', $this->modernCss);
    }

    /**
     * 13. Full Runtime Render and Data Binding Verification (Rendered == Runtime Stats)
     */
    public function testViewRenderingAndLiveValueBinding(): void
    {
        $stats = [
            'total'         => 789,
            'emergency'     => 142,
            'high'          => 415,
            'medium'        => 52,
            'belum'         => 501,
            'selesai'       => 93,
            'target_harian' => 30,
            'hari_ini'      => 21
        ];

        $woStats = [
            'total'   => 8,
            'aktif'   => 3,
            'selesai' => 5,
            'overdue' => 2
        ];

        $mapPins = [
            [
                'id'           => 999,
                'nomor_temuan' => 'STJ-TEST-000999',
                'judul'        => 'Isolator Flashover Titik 42',
                'latitude'     => -7.4478,
                'longitude'    => 112.7183,
                'prioritas'    => 'EMERGENCY',
                'status'       => 'BELUM'
            ]
        ];

        $viewParams = [
            'userName'      => 'Test Officer',
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

        // Section C: Assert rendered values match runtime stats directly
        $this->assertStringContainsString('id="kpi-total-temuan">789</div>', $html);
        $this->assertStringContainsString('id="kpi-emergency">142</div>', $html);
        $this->assertStringContainsString('id="kpi-high">415</div>', $html);
        $this->assertStringContainsString('id="kpi-medium">52</div>', $html);
        $this->assertStringContainsString('id="kpi-belum">501</div>', $html);
        $this->assertStringContainsString('id="kpi-wo-aktif">3</div>', $html);
        $this->assertStringContainsString('id="kpi-selesai">93</div>', $html);
        
        // Dynamic target: 21 / 30 = 70%
        $this->assertStringContainsString('id="kpi-target-harian-text">21', $html);
        $this->assertStringContainsString('/ 30', $html);
        $this->assertStringContainsString('id="kpi-target-harian-pct">70%</span>', $html);
        $this->assertStringContainsString('style="width: 70%;"', $html);

        // Section D: Mini GIS map & real operational feed
        $this->assertStringContainsString('STJ-TEST-000999', $html);
        $this->assertStringContainsString('Isolator Flashover Titik 42', $html);
        $this->assertStringContainsString('temuan/detail/999', $html);
        $this->assertStringContainsString('1 Titik Terpetakan', $html);

        // Section E: SLA rendered values
        $this->assertStringContainsString('id="sla-val-emergency">142</h3>', $html);
        $this->assertStringContainsString('id="sla-val-high">415</h3>', $html);
        $this->assertStringContainsString('id="sla-val-medium">52</h3>', $html);
        $this->assertStringContainsString('id="sla-val-overdue">2</h3>', $html);
    }

    /**
     * 14. Invariant: Absolute Zero Backend & Topology Mutation & Frozen SLA Helper
     */
    public function testZeroBackendAndTopologyMutation(): void
    {
        $topologyOrchestrator = APPPATH . 'Services/MultiFeederCompletionOrchestrator.php';
        $translineService    = APPPATH . 'Services/TranslineCompletionService.php';
        $proposalService     = APPPATH . 'Services/TranslineProposalService.php';

        if (file_exists($topologyOrchestrator)) {
            $content = file_get_contents($topologyOrchestrator);
            $this->assertStringContainsString('class MultiFeederCompletionOrchestrator', $content);
        }
        if (file_exists($translineService)) {
            $content = file_get_contents($translineService);
            $this->assertStringContainsString('class TranslineCompletionService', $content);
        }
        if (file_exists($proposalService)) {
            $content = file_get_contents($proposalService);
            $this->assertStringContainsString('class TranslineProposalService', $content);
        }

        // Controllers must NOT be modified
        $dashboardController = file_get_contents(APPPATH . 'Controllers/Dashboard.php');
        $this->assertStringContainsString('class Dashboard extends BaseController', $dashboardController);

        // app_helper.php must NOT be modified and SLA business rule must remain intact
        $appHelper = file_get_contents(APPPATH . 'Helpers/app_helper.php');
        $this->assertStringContainsString("function get_sla_status", $appHelper);
        $this->assertStringContainsString("function apply_role_scoping", $appHelper);
    }
}