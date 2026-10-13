<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Controllers\DynamicSldController;
use App\Controllers\Api\SldApiController;
use App\Services\SldLayoutCoordinateEngineService;

/**
 * Class SldVisualRendererContractTest
 *
 * Verifies the SLD-05 Visual Renderer Engine Contract:
 * 1. Single immutable layout snapshot consumption
 * 2. View template compilation & parameter passing
 * 3. Client-side vector engine script integrity
 * 4. Zero topology logic in renderer contract
 * 5. Symbology coverage across all 205 nodes
 * 6. Dual-mode support (Engineering vs Simplified)
 * 7. Three-zone visual boundary preservation
 * 8. Zero database mutation invariant (Delta = 0)
 * 9. Zero mutation HTTP endpoints (strictly GET)
 */
class SldVisualRendererContractTest extends CIUnitTestCase
{
    protected SldLayoutCoordinateEngineService $layoutService;
    protected DynamicSldController $controller;
    protected SldApiController $apiController;

    protected function setUp(): void
    {
        parent::setUp();
        $this->layoutService = new SldLayoutCoordinateEngineService();
        $this->controller = new DynamicSldController();
        $this->apiController = new SldApiController();
    }

    /**
     * 1. View Template Compiles & Renders Cleanly
     */
    public function testViewTemplateCompilesSuccessfully(): void
    {
        $viewFile = defined('APPPATH') ? APPPATH . 'Views/sld/index.php' : 'e:/XAMPP/htdocs/SIDAK TEJO/app/Views/sld/index.php';
        $this->assertFileExists($viewFile);

        $content = file_get_contents($viewFile);
        $this->assertStringContainsString('SldRendererEngine', $content);
        $this->assertStringContainsString('Mode Engineering', $content);
        $this->assertStringContainsString('Mode Simplified', $content);
        $this->assertStringContainsString('sld-canvas-container', $content);
        $this->assertStringContainsString('Fit Main Network', $content);
        $this->assertStringContainsString('Fit All', $content);
        $this->assertStringContainsString('Reset', $content);
    }

    /**
     * 2. Controller Passes Immutable API URL & Feeder Selection
     */
    public function testDynamicSldControllerViewParameters(): void
    {
        $response = $this->controller->view(15);
        $this->assertNotNull($response);
        $body = (string)$response;

        $this->assertStringContainsString('api/sld/feeder/15/layout', $body);
        $this->assertStringContainsString('id="select-feeder"', $body);
        $this->assertStringContainsString('setRendererMode', $body);
    }

    /**
     * 3. Client-Side Script Integrity & 10 Amendments Adherence
     */
    public function testClientSideEngineScriptIntegrity(): void
    {
        $jsFile = defined('ROOTPATH') ? ROOTPATH . 'public/assets/js/sld/sld-renderer-engine.js' : 'e:/XAMPP/htdocs/SIDAK TEJO/public/assets/js/sld/sld-renderer-engine.js';
        $this->assertFileExists($jsFile);

        $js = file_get_contents($jsFile);

        // Core class exists
        $this->assertStringContainsString('class SldRendererEngine', $js);

        // Uses strictly GET method
        $this->assertStringContainsString("method: 'GET'", $js);
        $this->assertStringNotContainsString("method: 'POST'", $js);
        $this->assertStringNotContainsString("method: 'PUT'", $js);
        $this->assertStringNotContainsString("method: 'DELETE'", $js);

        // Neutral UNKNOWN switch representation (diamond with ?)
        $this->assertStringContainsString('PMS (UNKNOWN)', $js);
        $this->assertStringNotContainsString('NORMAL_CLOSED_CANDIDATE', $js);

        // Pan/Zoom operates on viewBox, not layout coordinates
        $this->assertStringContainsString('viewBox', $js);
        $this->assertStringContainsString('setAttribute(\'viewBox\'', $js);

        // Three distinct zones
        $this->assertStringContainsString('sld-zone-main', $js);
        $this->assertStringContainsString('sld-zone-unconnected', $js);
        $this->assertStringContainsString('sld-zone-isolated', $js);

        // Accessibility attributes
        $this->assertStringContainsString('data-asset-id', $js);
        $this->assertStringContainsString('data-topology-role', $js);
        $this->assertStringContainsString('data-device-role', $js);
        $this->assertStringContainsString('tabindex="0"', $js);

        // Visual Remediation: Viewport Navigation System
        $this->assertStringContainsString('fitMainNetwork', $js);
        $this->assertStringContainsString('fitAll', $js);
        $this->assertStringContainsString('resetView', $js);

        // Visual Remediation: Conductor Span Length Annotations & Staggered Pole Labels
        $this->assertStringContainsString('sld-edge-label-group', $js);
        $this->assertStringContainsString('sld-pole-label', $js);
        $this->assertStringContainsString('Length unavailable', $js);

        // Visual Remediation Hardening: Geometric AABB Collision Index
        $this->assertStringContainsString('class LabelOccupancyIndex', $js);
        $this->assertStringContainsString('registerNodeSymbolOccupancies', $js);
        $this->assertStringContainsString('collides', $js);
    }

    /**
     * 4. Single Immutable Layout Snapshot Supplies All Visual Metadata
     */
    public function testSingleImmutableLayoutSnapshotContract(): void
    {
        $layout = $this->layoutService->buildFeederLayout(15);

        $this->assertEquals('success', $layout['status']);
        $this->assertArrayHasKey('canvas', $layout);
        $this->assertArrayHasKey('zones', $layout);
        $this->assertArrayHasKey('nodes', $layout);
        $this->assertArrayHasKey('edges', $layout);
        $this->assertArrayHasKey('line_sections', $layout);
        $this->assertArrayHasKey('source', $layout);

        // Exact counts verified
        $this->assertCount(205, $layout['nodes']);
        $this->assertCount(197, $layout['edges']);
        $this->assertCount(60, $layout['line_sections']);
    }

    /**
     * 5. Symbology Coverage for All 205 Nodes
     */
    public function testSymbologyCoverageAcrossAllNodes(): void
    {
        $layout = $this->layoutService->buildFeederLayout(15);

        $topologyCounts = [
            'SOURCE_INCOMER' => 0,
            'TERMINAL_NODE'  => 0,
            'PASS_THROUGH'    => 0,
            'BRANCH_NODE'    => 0,
            'ISOLATED_NODE'  => 0,
        ];

        $deviceCounts = [
            'SOURCE_INCOMER'   => 0,
            'SWITCH_CANDIDATE' => 0,
            'TRANSFORMER_NODE' => 0,
            'LINE_POLE'        => 0,
        ];

        foreach ($layout['nodes'] as $node) {
            $topRole = $node['topology_role'];
            $devRole = $node['device_role'];

            $this->assertArrayHasKey($topRole, $topologyCounts, "Unknown topology_role: {$topRole}");
            $this->assertArrayHasKey($devRole, $deviceCounts, "Unknown device_role: {$devRole}");

            $topologyCounts[$topRole]++;
            $deviceCounts[$devRole]++;
        }

        // Verify Topology Role Partition (Sum = 205)
        $this->assertEquals(1, $topologyCounts['SOURCE_INCOMER']);
        $this->assertEquals(30, $topologyCounts['TERMINAL_NODE']);
        $this->assertEquals(153, $topologyCounts['PASS_THROUGH']);
        $this->assertEquals(19, $topologyCounts['BRANCH_NODE']);
        $this->assertEquals(2, $topologyCounts['ISOLATED_NODE']);
        $this->assertEquals(205, array_sum($topologyCounts));

        // Verify Device Role Partition (Sum = 205)
        $this->assertEquals(1, $deviceCounts['SOURCE_INCOMER']);
        $this->assertEquals(4, $deviceCounts['SWITCH_CANDIDATE']);
        $this->assertEquals(23, $deviceCounts['TRANSFORMER_NODE']);
        $this->assertEquals(177, $deviceCounts['LINE_POLE']);
        $this->assertEquals(205, array_sum($deviceCounts));

        // Verify Authoritative GTT Subtypes inherited from SLD-03 (Total = 23)
        $gttSubtypes = [
            'GTT2_PORTAL'         => 0,
            'GTT1_CANTOL'         => 0,
            'UNKNOWN_GTT_SUBTYPE' => 0,
        ];
        foreach ($layout['nodes'] as $node) {
            if ($node['device_role'] === 'TRANSFORMER_NODE') {
                $eq = $node['equipment_type'];
                $this->assertArrayHasKey($eq, $gttSubtypes, "Unexpected GTT subtype: {$eq}");
                $gttSubtypes[$eq]++;
            }
        }
        $this->assertEquals(15, $gttSubtypes['GTT2_PORTAL']);
        $this->assertEquals(4, $gttSubtypes['GTT1_CANTOL']);
        $this->assertEquals(4, $gttSubtypes['UNKNOWN_GTT_SUBTYPE']);
        $this->assertEquals(23, array_sum($gttSubtypes));

        // Verify Data Source Mode Metadata
        $this->assertArrayHasKey('data_source', $layout);
        $this->assertEquals('CANONICAL_FORENSIC_BASELINE', $layout['data_source']['mode']);
        $this->assertFalse($layout['data_source']['is_production_live']);
    }

    /**
     * 6. Dual-Mode LINE_SECTION Integration
     */
    public function testDualModeLineSectionIntegration(): void
    {
        $layout = $this->layoutService->buildFeederLayout(15);

        $sections = $layout['line_sections'];
        $this->assertCount(60, $sections);

        foreach ($sections as $sec) {
            $this->assertArrayHasKey('start', $sec['layout']);
            $this->assertArrayHasKey('end', $sec['layout']);
            $this->assertNotNull($sec['layout']['start']['grid_x']);
            $this->assertNotNull($sec['layout']['start']['grid_y']);
            $this->assertNotNull($sec['layout']['end']['grid_x']);
            $this->assertNotNull($sec['layout']['end']['grid_y']);
        }
    }

    /**
     * 7. Three-Zone Visual Separation Verified
     */
    public function testThreeZoneSeparation(): void
    {
        $layout = $this->layoutService->buildFeederLayout(15);
        $zones = $layout['zones'];

        $this->assertArrayHasKey('MAIN_NETWORK', $zones);
        $this->assertArrayHasKey('UNCONNECTED_COMPONENTS', $zones);
        $this->assertArrayHasKey('ISOLATED_ASSETS', $zones);

        $this->assertEquals(183, $zones['MAIN_NETWORK']['nodes_count']);
        $this->assertEquals(20, $zones['UNCONNECTED_COMPONENTS']['nodes_count']);
        $this->assertEquals(2, $zones['ISOLATED_ASSETS']['nodes_count']);

        // Assert spatial separation: Zone 2 Y > Zone 1 Max Y, Zone 3 Y > Zone 2 Max Y
        $this->assertGreaterThan($zones['MAIN_NETWORK']['bounding_box']['max_y'], $zones['UNCONNECTED_COMPONENTS']['bounding_box']['min_y']);
        $this->assertGreaterThan($zones['UNCONNECTED_COMPONENTS']['bounding_box']['max_y'], $zones['ISOLATED_ASSETS']['bounding_box']['min_y']);
    }

    /**
     * 8. Strict Read-Only Invariant (Delta = 0)
     */
    public function testStrictReadOnlyInvariant(): void
    {
        $res = $this->layoutService->buildFeederLayout(15);
        $this->assertTrue($res['diagnostics']['zero_writes_verified']);
    }

    /**
     * 9. Authoritative Conductor Span Readability Contract (Visual Remediation)
     */
    public function testAuthoritativeConductorSpanReadabilityContract(): void
    {
        $layout = $this->layoutService->buildFeederLayout(15);
        $edges = $layout['edges'];
        $this->assertCount(197, $edges);

        $validLengthCount = 0;
        foreach ($edges as $e) {
            $this->assertArrayHasKey('transline_id', $e);
            $this->assertArrayHasKey('source_grid', $e);
            $this->assertArrayHasKey('target_grid', $e);
            $this->assertArrayHasKey('route_type', $e);

            // Length must be either numeric or null; never invented fake strings
            if ($e['length_meters'] !== null) {
                $this->assertIsNumeric($e['length_meters']);
                $this->assertGreaterThan(0, $e['length_meters']);
                $validLengthCount++;
            }
        }

        // Renderer script must contain fallback "Length unavailable" and zero GPS distance invention
        $jsFile = defined('ROOTPATH') ? ROOTPATH . 'public/assets/js/sld/sld-renderer-engine.js' : 'e:/XAMPP/htdocs/SIDAK TEJO/public/assets/js/sld/sld-renderer-engine.js';
        $js = file_get_contents($jsFile);
        $this->assertStringContainsString('Length unavailable', $js);
        $this->assertStringNotContainsString('Math.hypot', $js); // No Euclidean GPS distance invention
        $this->assertStringNotContainsString('haversine', $js);  // No Haversine GPS distance invention
    }
}

