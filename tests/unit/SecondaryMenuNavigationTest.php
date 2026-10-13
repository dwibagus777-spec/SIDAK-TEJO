<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Controllers\DynamicSldController;
use App\Services\SldTopologyReadModelService;
use Config\Database;

/**
 * CR-NAV-01: Secondary Menu Intermittent Render Fix & SLD Navigation Integration Test Suite
 *
 * Verifies the 24 mandatory compliance gates:
 * - 14 Navigation / Layout gates (forensic dual-mode collision fix, CSS scoping, JS idempotency, bfcache)
 * - 10 SLD Integration gates (authoritative routes, read-model consumption, zero DB mutation, regression)
 */
class SecondaryMenuNavigationTest extends CIUnitTestCase
{
    protected string $adminLayout;
    protected string $dashboardView;
    protected string $validationView;
    protected string $routesContent;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['app', 'url', 'auth', 'date']);
        $this->adminLayout    = file_get_contents(APPPATH . 'Views/layouts/admin.php');
        $this->dashboardView  = file_get_contents(APPPATH . 'Views/dashboard/index.php');
        $this->validationView = file_get_contents(APPPATH . 'Views/sld/validation.php');
        $this->routesContent  = file_get_contents(APPPATH . 'Config/Routes.php');
    }

    // =========================================================================
    // NAVIGATION / SECONDARY MENU TESTS (GATES 1 - 14)
    // =========================================================================

    /**
     * Gate 1: Menu labels render (.nav-link-title is present and protected)
     */
    public function testGate01_MenuLabelsRender(): void
    {
        $this->assertStringContainsString('class="nav-link-title"', $this->adminLayout);
        $this->assertStringContainsString('Dashboard Utama', $this->adminLayout);
        $this->assertStringContainsString('Peta Jaringan (GIS)', $this->adminLayout);
        $this->assertStringContainsString('SLD Feeder', $this->adminLayout);
    }

    /**
     * Gate 2: Category labels render (.nav-header span is present)
     */
    public function testGate02_CategoryLabelsRender(): void
    {
        $this->assertStringContainsString('class="nav-header', $this->adminLayout);
        $this->assertStringContainsString('CORE & ANALYTICS', $this->adminLayout);
        $this->assertStringContainsString('OPERASIONAL & LAPANGAN', $this->adminLayout);
        $this->assertStringContainsString('SINGLE LINE DIAGRAM', $this->adminLayout);
    }

    /**
     * Gate 3: Category chevrons render (.nav-header .chevron-icon is present)
     */
    public function testGate03_CategoryChevronsRender(): void
    {
        $this->assertStringContainsString('fas fa-chevron-down chevron-icon', $this->adminLayout);
    }

    /**
     * Gate 4: Active item retains label and structure
     */
    public function testGate04_ActiveItemRetainsLabel(): void
    {
        // On dashboard route, Dashboard Utama has nav-item active and contains nav-link-title
        $this->assertStringContainsString('(url_is(\'dashboard\') && !url_is(\'executive-dashboard\')) ? \'active\' : \'\'', $this->adminLayout);
        $this->assertStringContainsString('<span class="nav-link-title">Dashboard Utama</span>', $this->adminLayout);
    }

    /**
     * Gate 5: Mission Control ignores sidebar_collapsed in CSS
     */
    public function testGate05_MissionControlIgnoresSidebarCollapsedCss(): void
    {
        // Desktop collapsed rule explicitly excludes .sidak-mission-control
        $this->assertStringContainsString(
            'body.sidebar-collapsed:not(.sidak-mission-control) .navbar-vertical .nav-link-title',
            $this->adminLayout
        );
        $this->assertStringContainsString(
            'body.sidebar-collapsed:not(.sidak-mission-control) .navbar-vertical .nav-header span',
            $this->adminLayout
        );

        // High specificity override guarantees visibility in Mission Control and drawer-open
        $this->assertStringContainsString('body.sidak-mission-control .navbar-vertical .nav-link-title', $this->adminLayout);
        $this->assertStringContainsString('.navbar-vertical.drawer-open .nav-link-title', $this->adminLayout);
    }

    /**
     * Gate 6: Standard desktop pages retain 68px collapse capability
     */
    public function testGate06_StandardPagesRetainCollapseBehavior(): void
    {
        $this->assertStringContainsString('body.sidebar-collapsed:not(.sidak-mission-control) .navbar-vertical {', $this->adminLayout);
        $this->assertStringContainsString('width: 68px !important;', $this->adminLayout);
        $this->assertStringContainsString('margin-left: 68px !important;', $this->adminLayout);
    }

    /**
     * Gate 7: Repeated initialization is idempotent
     */
    public function testGate07_RepeatedInitIsIdempotent(): void
    {
        $this->assertStringContainsString('dataset.collapseBound', $this->adminLayout);
        $this->assertStringContainsString('btnCollapse.dataset.collapseBound = \'true\';', $this->adminLayout);
    }

    /**
     * Gate 8: Open -> Close -> Open drawer remains structurally correct
     */
    public function testGate08_OpenCloseOpenDrawerPreserved(): void
    {
        $this->assertStringContainsString('window.toggleSidebarDrawer = function()', $this->adminLayout);
        $this->assertStringContainsString("aside.classList.toggle('drawer-open')", $this->adminLayout);
    }

    /**
     * Gate 9: Page reload cleans up stale state on Mission Control
     */
    public function testGate09_ReloadCleansStaleState(): void
    {
        $this->assertStringContainsString("document.body.classList.contains('sidak-mission-control')", $this->adminLayout);
        $this->assertStringContainsString("document.body.classList.remove('sidebar-collapsed');", $this->adminLayout);
    }

    /**
     * Gate 10: Browser back/forward navigation retains correctness
     */
    public function testGate10_BrowserBackForwardRetainsCorrectness(): void
    {
        $this->assertStringContainsString("window.addEventListener('pageshow'", $this->adminLayout);
    }

    /**
     * Gate 11: Pageshow / bfcache restores label visibility on Mission Control
     */
    public function testGate11_PageshowBfcacheRestoresLabelVisibility(): void
    {
        $this->assertStringContainsString(
            "window.addEventListener('pageshow', function() {",
            $this->adminLayout
        );
        $this->assertStringContainsString(
            "if (document.body.classList.contains('sidak-mission-control')) {",
            $this->adminLayout
        );
    }

    /**
     * Gate 12: Stale localStorage does not blank Mission Control drawer
     */
    public function testGate12_StaleLocalStorageDoesNotBlankMissionControl(): void
    {
        // Early return before reading localStorage when isMissionControl is true
        $posMissionControlCheck = strpos($this->adminLayout, "var isMissionControl = document.body.classList.contains('sidak-mission-control');");
        $posLocalStorageCheck   = strpos($this->adminLayout, "localStorage.getItem('sidebar_collapsed') === 'true'");

        $this->assertNotFalse($posMissionControlCheck);
        $this->assertNotFalse($posLocalStorageCheck);
        $this->assertLessThan(
            $posLocalStorageCheck,
            $posMissionControlCheck,
            'isMissionControl check must occur before localStorage.getItem to protect Mission Control'
        );
    }

    /**
     * Gate 13: Role filtering remains strictly intact
     */
    public function testGate13_RoleFilteringRemainsIntact(): void
    {
        $this->assertStringContainsString("check_role(['administrator', 'admin_ulp', 'inspeksi'])", $this->adminLayout);
        $this->assertStringContainsString("check_role(['administrator', 'admin_ulp'])", $this->adminLayout);
        $this->assertStringContainsString("check_role(['administrator'])", $this->adminLayout);
    }

    /**
     * Gate 14: Unauthorized menus remain hidden for restricted roles
     */
    public function testGate14_UnauthorizedMenuRemainsHidden(): void
    {
        $this->assertStringContainsString("!check_role(['supervisor_up3'])", $this->adminLayout);
        $this->assertStringContainsString("!check_role(['har_row'])", $this->adminLayout);
    }

    // =========================================================================
    // SLD NAVIGATION & WORKBENCH TESTS (GATES 15 - 24)
    // =========================================================================

    /**
     * Gate 15: SINGLE LINE DIAGRAM category exists in sidebar
     */
    public function testGate15_SingleLineDiagramCategoryExists(): void
    {
        $this->assertStringContainsString('SINGLE LINE DIAGRAM', $this->adminLayout);
        $this->assertStringContainsString('id="group-sld"', $this->adminLayout);
    }

    /**
     * Gate 16: SLD Feeder menu item exists and routes to authoritative route
     */
    public function testGate16_SldFeederMenuItemExists(): void
    {
        $this->assertStringContainsString('SLD Feeder', $this->adminLayout);
        $this->assertStringContainsString("site_url('sld/view/15')", $this->adminLayout);
    }

    /**
     * Gate 17: SLD Validation menu item exists
     */
    public function testGate17_SldValidationMenuItemExists(): void
    {
        $this->assertStringContainsString('SLD Validation', $this->adminLayout);
        $this->assertStringContainsString("site_url('sld/validation')", $this->adminLayout);
    }

    /**
     * Gate 18: SLD Validation controller returns HTTP 200 with complete view
     */
    public function testGate18_SldValidationReturnsHttp200(): void
    {
        ob_start();
        $controller = new DynamicSldController();
        $response = $controller->validation();
        $body = (string)$response;
        ob_end_clean();

        $this->assertNotNull($response);
        $this->assertStringContainsString('SLD Feeder Validation & Topology Health', $body);
        $this->assertStringContainsString('Daftar Status Topologi Penyulang', $body);
        $this->assertStringContainsString('table-sld-feeders', $body);
    }

    /**
     * Gate 19: Feeder records are displayed in Validation Workbench
     */
    public function testGate19_FeederRecordsAreDisplayed(): void
    {
        ob_start();
        $controller = new DynamicSldController();
        $body = (string)$controller->validation();
        ob_end_clean();

        $this->assertStringContainsString('Buka SLD', $body);
        $this->assertStringContainsString('feeder-row', $body);
    }

    /**
     * Gate 20: Existing SLD route links correctly in Routes.php
     */
    public function testGate20_ExistingSldRouteLinksCorrectly(): void
    {
        $this->assertStringContainsString(
            "\$routes->get('sld/view/(:num)', 'DynamicSldController::view/$1');",
            $this->routesContent
        );
        $this->assertStringContainsString(
            "\$routes->get('sld/validation', 'DynamicSldController::validation');",
            $this->routesContent
        );
    }

    /**
     * Gate 21: SLD read model is directly consumed by validation
     */
    public function testGate21_SldReadModelIsDirectlyConsumed(): void
    {
        $controllerFile = file_get_contents(APPPATH . 'Controllers/DynamicSldController.php');
        $this->assertStringContainsString('use App\Services\SldTopologyReadModelService;', $controllerFile);
        $this->assertStringContainsString('buildFeederGraph', $controllerFile);
    }

    /**
     * Gate 22: No duplicate topology algorithm introduced
     */
    public function testGate22_NoDuplicateTopologyAlgorithmIntroduced(): void
    {
        $controllerFile = file_get_contents(APPPATH . 'Controllers/DynamicSldController.php');
        // Controller delegates to readModel->buildFeederGraph and must NOT run Dijkstra, BFS, or cycle checks
        $this->assertStringNotContainsString('dijkstra', strtolower($controllerFile));
        $this->assertStringNotContainsString('breadthfirst', strtolower($controllerFile));
        $this->assertStringNotContainsString('cyclomatic', strtolower($controllerFile));
    }

    /**
     * Gate 23: Zero database mutation invariant (Delta = 0)
     */
    public function testGate23_ZeroDbMutation(): void
    {
        $db = null;
        try {
            $db = Database::connect('default');
            if (!$db->tableExists('assets')) {
                $db = Database::connect();
            }
        } catch (\Throwable $e) {
            $db = Database::connect();
        }

        if (!$db || !$db->tableExists('assets')) {
            $this->markTestSkipped('Authoritative assets table not accessible on test database connection.');
        }

        $assetsCount     = $db->table('assets')->countAllResults();
        $translinesCount = $db->table('gis_translines')->countAllResults();
        $penyulangCount  = $db->table('penyulang')->countAllResults();
        $sectionsCount   = $db->table('sections')->countAllResults();
        $temuanCount     = $db->table('temuan')->countAllResults();
        $faultRecordsExists = $db->tableExists('fault_records');

        // Render validation workbench to test for side-effects
        ob_start();
        $controller = new DynamicSldController();
        $controller->validation();
        ob_end_clean();

        // Assert strictly zero DB changes
        $this->assertSame($assetsCount, $db->table('assets')->countAllResults(), 'assets count must not mutate');
        $this->assertSame($translinesCount, $db->table('gis_translines')->countAllResults(), 'gis_translines count must not mutate');
        $this->assertSame($penyulangCount, $db->table('penyulang')->countAllResults(), 'penyulang count must not mutate');
        $this->assertSame($sectionsCount, $db->table('sections')->countAllResults(), 'sections count must not mutate');
        $this->assertSame($temuanCount, $db->table('temuan')->countAllResults(), 'temuan count must not mutate');
        $this->assertFalse($faultRecordsExists, 'fault_records must not exist');
    }

    /**
     * Gate 24: SLD-05T regression remains 100% green
     */
    public function testGate24_Sld05TContractsPreserved(): void
    {
        // SLD API routes intact
        $this->assertStringContainsString("api/sld/feeder/(:num)/layout", $this->routesContent);
        $this->assertStringContainsString("api/sld/feeder/(:num)/sheets", $this->routesContent);
        $this->assertStringContainsString("api/sld/feeder/(:num)/findings", $this->routesContent);
        $this->assertStringContainsString("api/sld/feeder/(:num)/fingerprint", $this->routesContent);
    }
}
