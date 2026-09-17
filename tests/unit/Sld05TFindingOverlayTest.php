<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\SldFindingOverlayService;
use App\Services\SldTopologyReadModelService;

/**
 * Class Sld05TFindingOverlayTest
 *
 * Verifies Lock 5, 6, and 7 of SLD-05T:
 * 1. Two finding models: ASSET_LINKED and LOCATION_LINKED.
 * 2. LOCATION_LINKED findings strictly classified as "LOCATION FINDING / NOT TOPOLOGY NODE".
 * 3. Zero Topology Mutation Invariant: delta_nodes = 0, delta_edges = 0, topology_mutation = false.
 * 4. Production guard: DB failure returns DATA_NOT_READY, no silent fixture fallback.
 */
class Sld05TFindingOverlayTest extends CIUnitTestCase
{
    protected SldFindingOverlayService $findingService;
    protected SldTopologyReadModelService $topologyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->findingService = new SldFindingOverlayService();
        $this->topologyService = new SldTopologyReadModelService();
    }

    /**
     * Test the two finding models and classification attributes.
     */
    public function testTwoFindingModelsSeparation(): void
    {
        // Feeder 15 active asset IDs
        $graph = $this->topologyService->buildFeederGraph(15);
        $validAssetIds = array_column($graph['nodes'], 'asset_id');

        $res = $this->findingService->getFeederFindingOverlay(15, $validAssetIds);

        $this->assertEquals('success', $res['status']);
        $this->assertGreaterThan(0, $res['total_findings']);
        $this->assertGreaterThan(0, $res['asset_linked_count']);
        $this->assertGreaterThan(0, $res['location_linked_count']);

        // Test ASSET_LINKED findings
        foreach ($res['asset_linked'] as $f) {
            $this->assertEquals('ASSET_LINKED', $f['model_type']);
            $this->assertNotNull($f['asset_id']);
            $this->assertContains($f['asset_id'], $validAssetIds);
            $this->assertFalse($f['is_topology_node']);
            $this->assertArrayHasKey($f['asset_id'], $res['findings_by_asset']);
        }

        // Test LOCATION_LINKED findings
        foreach ($res['location_linked'] as $f) {
            $this->assertEquals('LOCATION_LINKED', $f['model_type']);
            $this->assertEquals('LOCATION FINDING / NOT TOPOLOGY NODE', $f['classification']);
            $this->assertFalse($f['is_topology_node']);
            $this->assertNotEquals(0.0, $f['latitude']);
            $this->assertNotEquals(0.0, $f['longitude']);
        }
    }

    /**
     * Test Zero Topology Mutation Invariant (Lock 5 & 6).
     */
    public function testZeroTopologyMutationInvariant(): void
    {
        $graph = $this->topologyService->buildFeederGraph(15);
        $validAssetIds = array_column($graph['nodes'], 'asset_id');

        $preNodeCount = count($graph['nodes']);
        $preEdgeCount = count($graph['edges']);

        $res = $this->findingService->getFeederFindingOverlay(15, $validAssetIds);

        // Finding service must explicitly guarantee zero graph mutations
        $inv = $res['invariant'];
        $this->assertFalse($inv['topology_mutation']);
        $this->assertEquals(0, $inv['delta_nodes']);
        $this->assertEquals(0, $inv['delta_edges']);

        // Post check: graph remains identical
        $postGraph = $this->topologyService->buildFeederGraph(15);
        $this->assertCount($preNodeCount, $postGraph['nodes']);
        $this->assertCount($preEdgeCount, $postGraph['edges']);
        $this->assertEquals(205, count($postGraph['nodes']));
        $this->assertEquals(197, count($postGraph['edges']));
    }

    /**
     * Test Production Database Guard (Lock 7):
     * If $db is passed but encounters error, strictly returns DATA_NOT_READY.
     */
    public function testProductionLiveDatabaseGuard(): void
    {
        // Mock a broken DB object that throws exception
        $mockDb = new class {
            public function table(string $table) {
                throw new \RuntimeException("Production MySQL connection failed on port 3306");
            }
        };

        $prodService = new SldFindingOverlayService($mockDb);
        $res = $prodService->getFeederFindingOverlay(15, [3188, 3231]);

        $this->assertEquals('DATA_NOT_READY', $res['status']);
        $this->assertEquals(503, $res['code']);
        $this->assertEquals('PRODUCTION_LIVE_DATABASE', $res['data_source']['mode']);
        $this->assertTrue($res['data_source']['is_production_live']);
        $this->assertStringContainsString('tidak dapat diakses', $res['message']);
        $this->assertEquals(0, $res['total_findings']);
    }
}
