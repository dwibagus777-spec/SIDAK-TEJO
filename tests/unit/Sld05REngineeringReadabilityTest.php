<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\SldTopologyReadModelService;
use App\Services\SldSemanticClassificationService;
use App\Services\SldLayoutCoordinateEngineService;

/**
 * Class Sld05REngineeringReadabilityTest
 *
 * Automated regression suite for SLD-05R: Engineering Drawing Readability Remediation.
 * Enforces the 5 Mandatory Human Amendments and strict read-only governance.
 */
class Sld05REngineeringReadabilityTest extends CIUnitTestCase
{
    protected SldTopologyReadModelService $topologyService;
    protected SldSemanticClassificationService $semanticService;
    protected SldLayoutCoordinateEngineService $layoutService;
    protected array $feeder15Layout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->topologyService = new SldTopologyReadModelService();
        $this->semanticService = new SldSemanticClassificationService($this->topologyService);
        $this->layoutService = new SldLayoutCoordinateEngineService($this->semanticService);
        $this->feeder15Layout = $this->layoutService->buildFeederLayout(15);
    }

    /**
     * Mandatory Amendment 2: Reconciled 197 Active Translines vs 206 Raw Rows.
     * All 9 inactive/soft-deleted rows must be completely excluded from the edge model.
     */
    public function testAuthoritativeActiveTranslineEdgeReconciliation(): void
    {
        $layout = $this->feeder15Layout;
        $this->assertSame('success', $layout['status']);
        $this->assertArrayHasKey('edges', $layout);
        
        // Assert exactly 197 active edges
        $this->assertCount(197, $layout['edges']);
        $this->assertSame(197, $layout['diagnostics']['total_edges']);

        // Verify the 9 known inactive transline IDs are excluded
        $inactiveTranslineIds = [68, 71, 74, 113, 117, 159, 169, 177, 183];
        $loadedTranslineIds = array_column($layout['edges'], 'transline_id');
        
        foreach ($inactiveTranslineIds as $inactiveId) {
            $this->assertNotContains(
                $inactiveId, 
                $loadedTranslineIds, 
                "Inactive/soft-deleted transline #{$inactiveId} must be excluded by active filter."
            );
        }
    }

    /**
     * Complete Physical Graph Topology & Zone Hierarchy.
     * Must contain exactly 205 nodes, 6 components, and 60 line sections.
     */
    public function testFeeder15CompleteGraphIntegrity(): void
    {
        $layout = $this->feeder15Layout;

        // Total 205 nodes
        $this->assertCount(205, $layout['nodes']);
        $this->assertSame(205, $layout['diagnostics']['total_nodes']);

        // 6 distinct electrical components
        $this->assertCount(6, $layout['components']);

        // Component C15-01 (Main Feeder Network connected to GI Buduran)
        $comp1 = null;
        foreach ($layout['components'] as $c) {
            if ($c['component_id'] === 'C15-01') {
                $comp1 = $c;
                break;
            }
        }
        $this->assertNotNull($comp1, 'Component C15-01 must exist.');
        $this->assertSame(183, $comp1['nodes_count']);
        $this->assertSame(182, $comp1['edges_count']);
        $this->assertTrue($comp1['rooted']);
        $this->assertTrue($comp1['source_reachable']);
        $this->assertSame(3231, $comp1['root_asset_id']);

        // 60 line sections
        $this->assertCount(60, $layout['line_sections']);

        // Zero collisions and zero planar edge crossings on rooted tree
        $this->assertSame(0, $layout['diagnostics']['node_collisions']);
        $this->assertSame(0, $layout['diagnostics']['rooted_tree_edge_crossings']);
    }

    /**
     * Mandatory Amendment 4: GI Buduran Substation Origin Demarcation.
     */
    public function testSourceOriginSubstationDemarcation(): void
    {
        $layout = $this->feeder15Layout;

        $this->assertArrayHasKey('source', $layout);
        $this->assertSame('GI BUDURAN', $layout['source']['name']);
        $this->assertSame(3231, $layout['source']['asset_id']);
        $this->assertSame('SUBSTATION_INCOMER_INTERFACE', $layout['source']['interface_type']);

        // Locate node #3231 in nodes array
        $node3231 = null;
        foreach ($layout['nodes'] as $n) {
            if ((int)$n['asset_id'] === 3231) {
                $node3231 = $n;
                break;
            }
        }
        $this->assertNotNull($node3231, 'Node #3231 must be present.');
        $this->assertSame('C15-01', $node3231['component_id']);
        $this->assertSame('MAIN_NETWORK', $node3231['zone']);
    }

    /**
     * Mandatory Amendment 5: Transformer (GTT) Inventory & Subtypes.
     * Reconciled count = 23 (4 Cantol, 15 Portal, 4 Unknown).
     */
    public function testTransformerGttInventoryAndSubtypeClassification(): void
    {
        $layout = $this->feeder15Layout;

        $this->assertArrayHasKey('gtt', $layout);
        $this->assertTrue($layout['gtt']['available']);
        $this->assertSame(23, $layout['gtt']['count']);
        $this->assertCount(23, $layout['gtt']['asset_ids']);

        // Classify GTT nodes from nodes list
        $gttNodes = array_filter($layout['nodes'], fn($n) => $n['device_role'] === 'TRANSFORMER_NODE');
        $this->assertCount(23, $gttNodes);

        $cantolCount = 0;
        $portalCount = 0;
        $unknownCount = 0;

        foreach ($gttNodes as $gtt) {
            $eq = $gtt['equipment_type'] ?? '';
            if ($eq === 'GTT1_CANTOL') {
                $cantolCount++;
            } elseif ($eq === 'GTT2_PORTAL') {
                $portalCount++;
            } else {
                $unknownCount++;
            }
        }

        $this->assertSame(4, $cantolCount, 'GTT Cantol count must be 4.');
        $this->assertSame(15, $portalCount, 'GTT Portal count must be 15.');
        $this->assertSame(4, $unknownCount, 'Unknown GTT count must be 4.');
    }

    /**
     * Strict Read-Only Governance Invariant: Delta = 0.
     */
    public function testStrictReadOnlyInvariantDeltaZero(): void
    {
        $layout = $this->feeder15Layout;
        $this->assertTrue($layout['diagnostics']['deterministic_verified']);
        $this->assertTrue($layout['diagnostics']['zero_writes_verified']);
    }

    /**
     * Mandatory Amendment 1 & 3: Frontend Renderer Engine Compliance.
     * Verifies JavaScript renderer follows all SLD-05R architectural amendments.
     */
    public function testFrontendRendererEngineCompliance(): void
    {
        $jsPath = FCPATH . 'assets/js/sld/sld-renderer-engine.js';
        $this->assertFileExists($jsPath);
        $jsContent = file_get_contents($jsPath);

        // Header and identifier
        $this->assertStringContainsString('SLD-05R', $jsContent);

        // Amendment 1: Does NOT guess trunk by grid_y === 0
        $this->assertStringNotContainsString("grid_y === 0", $jsContent);
        $this->assertStringNotContainsString("grid_y == 0", $jsContent);
        $this->assertStringContainsString("edge.component_id === 'C15-01'", $jsContent);

        // Amendment 3: Light engineering canvas with pure white background & grid
        $this->assertStringContainsString('fill="#ffffff"', $jsContent);
        $this->assertStringContainsString('pattern id="sld-grid-pattern"', $jsContent);
        $this->assertStringContainsString('stroke="#0f172a"', $jsContent);

        // Amendment 4: Substation GI Buduran origin anchor with red takeoff cable
        $this->assertStringContainsString('GI BUDURAN', $jsContent);
        $this->assertStringContainsString('KABEL OUTGOING 20kV', $jsContent);
        $this->assertStringContainsString('stroke="#dc2626"', $jsContent);

        // Amendment 5: Project-approved PLN/IEC-aligned glyphs
        $this->assertStringContainsString('GTT CANTOL', $jsContent);
        $this->assertStringContainsString('GTT PORTAL', $jsContent);
        $this->assertStringContainsString('PMS (UNKNOWN)', $jsContent);
        $this->assertStringContainsString('LBS', $jsContent);
        $this->assertStringContainsString('RECLOSER', $jsContent);

        // Progressive zoom density & collision prevention
        $this->assertStringContainsString('class LabelOccupancyIndex', $jsContent);
        $this->assertStringContainsString('updateZoomClass', $jsContent);
    }

    /**
     * Frontend Index View Compliance.
     * Verifies HTML template styles canvas as light engineering blueprint with updated legend.
     */
    public function testFrontendIndexViewCompliance(): void
    {
        $viewPath = APPPATH . 'Views/sld/index.php';
        $this->assertFileExists($viewPath);
        $viewContent = file_get_contents($viewPath);

        $this->assertStringContainsString('SLD-05R', $viewContent);
        $this->assertStringContainsString('background: #ffffff;', $viewContent);
        $this->assertStringContainsString('GI Incomer', $viewContent);
        $this->assertStringContainsString('Rute Utama', $viewContent);
        $this->assertStringContainsString('LBS / LBSM', $viewContent);
        $this->assertStringContainsString('Recloser', $viewContent);
        $this->assertStringContainsString('GTT Cantol', $viewContent);
        $this->assertStringContainsString('GTT Portal', $viewContent);
    }
}
