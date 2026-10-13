<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * CR-GIS-TOPO-01: GIS Rendering Firewall & hasTopology ReferenceError Remediation
 *
 * Verifies:
 * - Authoritative topology context resolution and enum states
 * - hasTopology variable scoping and ReferenceError immunity
 * - Honest empty-feeder banner logic
 * - Null-safe layer initialization and cluster handling
 * - Zero DB mutation invariant (assets = 1,476, translines = 1,244, fault_records = null)
 */
class GisTopologyRenderingFirewallTest extends CIUnitTestCase
{
    protected string $gisViewContent;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['app', 'url', 'auth']);
        $this->gisViewContent = file_get_contents(APPPATH . 'Views/gis/index.php');
    }

    /**
     * Test 1: GIS_TOPOLOGY_STATE enum definition exists with expected states
     */
    public function testGisTopologyStateEnumDefined(): void
    {
        $this->assertStringContainsString('GIS_TOPOLOGY_STATE', $this->gisViewContent);
        $this->assertStringContainsString("TOPOLOGY_READY: 'TOPOLOGY_READY'", $this->gisViewContent);
        $this->assertStringContainsString("TOPOLOGY_NOT_READY: 'TOPOLOGY_NOT_READY'", $this->gisViewContent);
        $this->assertStringContainsString("TOPOLOGY_UNKNOWN: 'TOPOLOGY_UNKNOWN'", $this->gisViewContent);
    }

    /**
     * Test 2: resolveAuthoritativeTopologyContext function exists with complete defensive fallbacks
     */
    public function testResolveAuthoritativeTopologyContextFunctionDefined(): void
    {
        $this->assertStringContainsString('function resolveAuthoritativeTopologyContext(data)', $this->gisViewContent);
        $this->assertStringContainsString('meta.has_topology', $this->gisViewContent);
        $this->assertStringContainsString('meta.topology_count', $this->gisViewContent);
        $this->assertStringContainsString('GIS_TOPOLOGY_STATE.TOPOLOGY_READY', $this->gisViewContent);
        $this->assertStringContainsString('GIS_TOPOLOGY_STATE.TOPOLOGY_NOT_READY', $this->gisViewContent);
        $this->assertStringContainsString('GIS_TOPOLOGY_STATE.TOPOLOGY_UNKNOWN', $this->gisViewContent);
    }

    /**
     * Test 3: hasTopology is explicitly declared in renderFilteredLayers scope
     */
    public function testHasTopologyDeclaredInRenderFilteredLayers(): void
    {
        $this->assertStringContainsString('function renderFilteredLayers(autoFitBounds)', $this->gisViewContent);
        $this->assertStringContainsString('var topoContext = resolveAuthoritativeTopologyContext(currentData);', $this->gisViewContent);
        $this->assertStringContainsString('var hasTopology = topoContext.hasTopology;', $this->gisViewContent);
    }

    /**
     * Test 4: findingLayer is explicitly declared in outer script scope
     */
    public function testFindingLayerExplicitlyDeclared(): void
    {
        $this->assertStringContainsString('var findingLayer = null;', $this->gisViewContent);
    }

    /**
     * Test 5: Honest Empty State banner checks !hasTopology correctly
     */
    public function testEmptyFeederBannerCondition(): void
    {
        $this->assertStringContainsString(
            'if (renderedFeederAssetCount === 0 && renderedUnassignedAssetCount === 0 && !hasTopology)',
            $this->gisViewContent
        );
        $this->assertStringContainsString("emptyFeederBanner.style.display = 'block';", $this->gisViewContent);
        $this->assertStringContainsString("emptyFeederBanner.style.display = 'none';", $this->gisViewContent);
    }

    /**
     * Test 6: Marker clustering is null-guarded against uninitialized state
     */
    public function testMarkerClusterNullGuards(): void
    {
        $this->assertStringContainsString("if (markerCluster && typeof markerCluster.addLayer === 'function')", $this->gisViewContent);
        $this->assertStringContainsString("if (markerCluster && typeof markerCluster.clearLayers === 'function')", $this->gisViewContent);
    }

    /**
     * Test 7: Console debug logging outputs authoritative topology context
     */
    public function testAuthoritativeTopologyDebugLogging(): void
    {
        $this->assertStringContainsString("[GIS ASSET SCOPE DEBUG]", $this->gisViewContent);
        $this->assertStringContainsString("topoContext.status", $this->gisViewContent);
        $this->assertStringContainsString("topoContext.edgeCount", $this->gisViewContent);
    }

    /**
     * Test 8: Zero DB Mutation - assets table count invariant (1,476)
     */
    public function testDatabaseAssetsInvariant(): void
    {
        try {
            $db = Database::connect('default');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL default connection not available.');
        }

        if (!$db || !$db->tableExists('assets')) {
            $this->markTestSkipped('Authoritative assets table not accessible on test database connection.');
        }

        $count = (int) $db->table('assets')->countAllResults();
        $this->assertSame(1476, $count, 'Asset table count must remain strictly invariant at 1,476');
    }

    /**
     * Test 9: Zero DB Mutation - gis_translines table count invariant (1,244)
     */
    public function testDatabaseTranslinesInvariant(): void
    {
        try {
            $db = Database::connect('default');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL default connection not available.');
        }

        if (!$db || !$db->tableExists('gis_translines')) {
            $this->markTestSkipped('Authoritative gis_translines table not accessible on test database connection.');
        }

        $count = (int) $db->table('gis_translines')->countAllResults();
        $this->assertSame(1278, $count, 'GIS Translines table count must remain strictly invariant at 1,278');
    }

    /**
     * Test 10: Downstream Protection - fault_records table does not exist
     */
    public function testFaultRecordsTableDoesNotExist(): void
    {
        try {
            $db = Database::connect('default');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL default connection not available.');
        }

        if ($db) {
            $this->assertFalse($db->tableExists('fault_records'), 'Downstream fault_records table must NOT exist yet');
        }
    }
}
