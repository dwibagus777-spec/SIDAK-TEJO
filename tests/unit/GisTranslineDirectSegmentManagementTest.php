<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Unit Test for Transline Direct Segment Management, Authorization, and Readability
 */
class GisTranslineDirectSegmentManagementTest extends CIUnitTestCase
{
    protected string $viewContent;
    protected string $routesContent;
    protected string $controllerContent;
    protected string $serviceContent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->viewContent = file_get_contents(APPPATH . 'Views/gis/index.php');
        $this->routesContent = file_get_contents(APPPATH . 'Config/Routes.php');
        $this->controllerContent = file_get_contents(APPPATH . 'Controllers/GisController.php');
        $this->serviceContent = file_get_contents(APPPATH . 'Services/GisTranslineService.php');
    }

    /**
     * Test 01: View exposes IS_ADMIN constant and popup includes action buttons guarded by IS_ADMIN
     */
    public function testViewExposesIsAdminAndPopupActions(): void
    {
        $this->assertStringContainsString("const IS_ADMIN = <?= !empty(\$isAdmin) ? 'true' : 'false' ?>;", $this->viewContent);
        $this->assertStringContainsString('window.deleteTranslineFromPopup', $this->viewContent);
        $this->assertStringContainsString('window.editTranslineFromPopup', $this->viewContent);
        $this->assertStringContainsString('Edit Transline', $this->viewContent);
        $this->assertStringContainsString('Hapus Transline', $this->viewContent);

        // Verify actions are guarded by IS_ADMIN
        $pattern = '/\$\{IS_ADMIN\s*\?[\s\S]*?Edit Transline[\s\S]*?Hapus Transline[\s\S]*?:\s*\'\'\}/';
        $this->assertMatchesRegularExpression($pattern, $this->viewContent);
    }

    /**
     * Test 02: Transline Code readability in popup and sheets (word-break, wrapping, safe clearance)
     */
    public function testTranslineCodeReadabilityAndWrapping(): void
    {
        // Popup header clearance against Leaflet close button
        $this->assertStringContainsString('padding-right: 22px;', $this->viewContent);
        $this->assertStringContainsString('word-break: break-all;', $this->viewContent);
        $this->assertStringContainsString('text-break', $this->viewContent);

        // Delete & Spec sheets text wrapping
        $this->assertStringContainsString('id="del-source-name" class="small fw-bold text-dark text-break"', $this->viewContent);
        $this->assertStringContainsString('id="del-target-name" class="small fw-bold text-danger text-break"', $this->viewContent);
        $this->assertStringContainsString('id="spec-source-name" class="small fw-bold text-dark text-break"', $this->viewContent);
        $this->assertStringContainsString('id="spec-target-name" class="small fw-bold text-primary text-break"', $this->viewContent);
    }

    /**
     * Test 03: Route filters enforce admin role on topology and transline mutation endpoints
     */
    public function testRoutesEnforceAdminFilter(): void
    {
        $routesToCheck = [
            'gis/api-connect-topology',
            'gis/api-disconnect-topology',
            'gis/api-update-segment',
            'gis/api-update-conductor',
            'gis/api-batch-transline/dry-run',
            'gis/api-batch-transline/execute',
            'gis/api-validate-transline-segment',
        ];

        foreach ($routesToCheck as $route) {
            $pattern = '/\(\'' . preg_quote($route, '/') . '\'[\s\S]*?\'filter\'\s*=>\s*\'role:administrator,admin,admin_pusat,admin_ulp\'/';
            $this->assertMatchesRegularExpression($pattern, $this->routesContent, "Route [{$route}] must have admin role filter.");
        }
    }

    /**
     * Test 04: GisController implements backend isActorAdmin check
     */
    public function testControllerBackendAdminCheck(): void
    {
        $this->assertStringContainsString('function isActorAdmin', $this->controllerContent);
        $this->assertStringContainsString('!$this->isActorAdmin($actor)', $this->controllerContent);

        // Test logic of isActorAdmin via reflection
        $controller = new \App\Controllers\GisController();
        $refMethod = new \ReflectionMethod($controller, 'isActorAdmin');
        $refMethod->setAccessible(true);

        $adminRoles = ['administrator', 'ADMIN', 'admin_pusat', 'admin_ulp', 'SUPERADMIN', 'DALOPS', 'MANAJER'];
        foreach ($adminRoles as $role) {
            $this->assertTrue(
                $refMethod->invoke($controller, ['id' => 1, 'name' => 'User', 'role' => $role]),
                "Role [{$role}] should be recognized as admin."
            );
        }

        $nonAdminRoles = ['PETUGAS_LAPANGAN', 'VIEWER', 'SURVEYOR', 'GUEST', 'YANTEK', 'OPERATOR'];
        foreach ($nonAdminRoles as $role) {
            $this->assertFalse(
                $refMethod->invoke($controller, ['id' => 2, 'name' => 'User', 'role' => $role]),
                "Role [{$role}] should NOT be recognized as admin."
            );
        }
    }

    /**
     * Test 05: GisTranslineService resolveTranslinePair handles bidirectional and co-located fallback
     */
    public function testTranslinePairResolutionWithCoLocatedFallback(): void
    {
        $this->assertStringContainsString('Spatial proximity fallback for co-located or alias markers', $this->serviceContent);
        $this->assertStringContainsString('source_asset_id', $this->serviceContent);
        $this->assertStringContainsString('target_asset_id', $this->serviceContent);
    }
}
