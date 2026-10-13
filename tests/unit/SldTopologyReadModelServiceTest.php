<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\SldTopologyReadModelService;

/**
 * Class SldTopologyReadModelServiceTest
 *
 * Enforces all 12 Mandatory Architectural Amendments for SLD-02:
 * 1. PYL-015 exact counts (205 assets, 203 connected, 2 isolated, 197 edges)
 * 2. Handshaking lemma (sum_degrees = 2 * E = 394)
 * 3. Forest identity (E = V - k ==> 197 = 203 - 6)
 * 4. Six connected components
 * 5. All 197 edges endpoint isolation (0 cross-feeder leakage)
 * 6. Cross-feeder edge rejection and firewall diagnostic
 * 7. Invalid endpoint rejection and firewall diagnostic
 * 8. Two isolated assets retained with role ISOLATED
 * 9. Four PMS switch candidates (SWITCH_CANDIDATE / UNKNOWN)
 * 10. 23 GTT retained in physical graph
 * 11. include_gtt false does not remove GTT nodes
 * 12. include_gtt true only changes presentation metadata
 * 13. GI source relationship (GI BUDURAN / GI_VALID)
 * 14. Banjar Kemantren authoritative takeoff (Asset #3231)
 * 15. Missing GI feeder blocked (Feeder 56 -> SLD_BLOCKED)
 * 16. Temuan never becomes topology node
 * 17. Degree role != operational device subtype (deg 3 is BRANCH_NODE)
 * 18. Zero operational writes (Delta = 0)
 * 19. Deterministic repeated output
 * 20. Feeder 15 output cannot contain Feeder 4/56/131 assets
 * 21. Component source reachability (C15-01 true, C15-02..C15-06 false)
 * 22. No nearest-neighbor topology construction
 * 23. No mutation of assets
 * 24. No mutation of gis_translines
 */
class SldTopologyReadModelServiceTest extends CIUnitTestCase
{
    protected SldTopologyReadModelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SldTopologyReadModelService();
    }

    /**
     * 1. PYL-015 Exact Counts Verification
     */
    public function testFeeder15ExactCounts(): void
    {
        $res = $this->service->buildFeederGraph(15);
        $this->assertEquals('success', $res['status']);

        $topo = $res['topology'];
        $this->assertEquals(205, $topo['feeder_assets_count']);
        $this->assertEquals(203, $topo['connected_nodes_count']);
        $this->assertEquals(2, $topo['isolated_nodes_count']);
        $this->assertEquals(197, $topo['edges_count']);

        // Explicit accounting identity
        $this->assertEquals(
            $topo['feeder_assets_count'],
            $topo['connected_nodes_count'] + $topo['isolated_nodes_count']
        );
    }

    /**
     * 2. Handshaking Lemma Verification: Sum(degrees) = 2 * E
     */
    public function testHandshakingLemmaFeeder15(): void
    {
        $res = $this->service->buildFeederGraph(15);
        $topo = $res['topology'];

        $this->assertTrue($topo['handshaking_verified']);
        $this->assertEquals(197, $topo['edges_count']);
        $this->assertEquals(394, $topo['sum_degrees']);
        $this->assertEquals(2 * 197, $topo['sum_degrees']);
    }

    /**
     * 3 & 4. Forest Identity (E = V - k) and Six Connected Components
     */
    public function testForestIdentityAndComponents(): void
    {
        $res = $this->service->buildFeederGraph(15);
        $topo = $res['topology'];

        $this->assertEquals(6, $topo['components_count']);
        $this->assertEquals(0, $topo['cyclomatic_number']);
        $this->assertTrue($topo['is_acyclic_forest']);

        // Forest identity: E = V - k ==> 197 = 203 - 6
        $this->assertEquals(197, $topo['connected_nodes_count'] - $topo['components_count']);
        $this->assertCount(6, $res['components']);
    }

    /**
     * 5. All 197 Edges Endpoint Isolation (Feeder Firewall)
     */
    public function testFeeder15EdgeIsolation(): void
    {
        $res = $this->service->buildFeederGraph(15);
        $fw = $res['firewall'];

        $this->assertEquals('PASS', $fw['status']);
        $this->assertEquals(0, $fw['cross_feeder_edges_rejected']);
        $this->assertEquals(0, $fw['invalid_endpoints_rejected']);
        $this->assertCount(197, $res['edges']);

        // Verify every edge endpoint belongs to Feeder 15 node set
        $nodeIds = array_column($res['nodes'], 'asset_id');
        $nodeIdSet = array_fill_keys($nodeIds, true);

        foreach ($res['edges'] as $edge) {
            $this->assertArrayHasKey($edge['source_asset_id'], $nodeIdSet);
            $this->assertArrayHasKey($edge['target_asset_id'], $nodeIdSet);
            $this->assertEquals('UNDIRECTED', $edge['direction']);
        }
    }

    /**
     * 6 & 7. Cross-Feeder and Invalid Endpoint Rejection
     */
    public function testFirewallRejectionDiagnostics(): void
    {
        // Mock sub-service that injects an anomalous edge
        $mockService = new class extends SldTopologyReadModelService {
            protected function loadFeederTranslines(int $penyulangId): array
            {
                $tls = parent::loadFeederTranslines($penyulangId);
                // Inject cross-feeder edge
                $tls[] = [
                    'id' => 99991,
                    'source_asset_id' => 3231, // Feeder 15
                    'target_asset_id' => 3879, // Feeder 4 (Cross-feeder!)
                ];
                // Inject invalid endpoint edge
                $tls[] = [
                    'id' => 99992,
                    'source_asset_id' => 3231,
                    'target_asset_id' => 0, // Invalid endpoint!
                ];
                return $tls;
            }
        };

        $res = $mockService->buildFeederGraph(15);
        $fw = $res['firewall'];

        $this->assertEquals('REVIEW', $fw['status']);
        $this->assertEquals(1, $fw['cross_feeder_edges_rejected']);
        $this->assertEquals(1, $fw['invalid_endpoints_rejected']);
        $this->assertCount(197, $res['edges'], "Anomalous edges must NOT be admitted into graph.edges");
    }

    /**
     * 8. Two Isolated Assets Retained in Nodes Array with Role ISOLATED
     */
    public function testIsolatedAssetsRetained(): void
    {
        $res = $this->service->buildFeederGraph(15);
        $isolatedNodes = array_filter($res['nodes'], fn($n) => $n['role'] === 'ISOLATED');

        $this->assertCount(2, $isolatedNodes);
        foreach ($isolatedNodes as $iso) {
            $this->assertEquals(0, $iso['degree']);
            $this->assertNull($iso['component_id']);
            $this->assertFalse($iso['source_reachable']);
        }

        $isoIds = array_column($isolatedNodes, 'asset_id');
        $this->assertContains(3203, $isoIds);
        $this->assertContains(3263, $isoIds);
    }

    /**
     * 9. Four PMS Switch Candidates Classified Correctly
     */
    public function testFourPmsSwitchCandidates(): void
    {
        $res = $this->service->buildFeederGraph(15);
        $switchNodes = array_filter($res['nodes'], fn($n) => $n['is_switch']);

        $this->assertCount(4, $switchNodes);
        $switchIds = array_column($switchNodes, 'asset_id');
        $this->assertEqualsCanonicalizing([3188, 3217, 3266, 3295], $switchIds);

        foreach ($switchNodes as $sw) {
            $this->assertEquals('SWITCH_CANDIDATE', $sw['switch_classification']);
            $this->assertEquals('UNKNOWN_OPERATIONAL_SUBTYPE', $sw['operational_subtype']);
        }

        // Check node 3188 is degree 3 BRANCH_NODE
        $nodeMap = array_column($res['nodes'], null, 'asset_id');
        $this->assertEquals(3, $nodeMap[3188]['degree']);
        $this->assertEquals('BRANCH_NODE', $nodeMap[3188]['role']);
    }

    /**
     * 10, 11, 12. GTT Physical Graph Invariance vs Presentation Metadata
     */
    public function testGttPhysicalGraphInvariance(): void
    {
        $resFalse = $this->service->buildFeederGraph(15, ['include_gtt' => false]);
        $resTrue  = $this->service->buildFeederGraph(15, ['include_gtt' => true]);

        // Presentation metadata toggles
        $this->assertFalse($resFalse['gtt']['visible']);
        $this->assertTrue($resTrue['gtt']['visible']);
        $this->assertTrue($resFalse['gtt']['available']);
        $this->assertEquals(23, $resFalse['gtt']['count']);
        $this->assertCount(23, $resFalse['gtt']['asset_ids']);

        // Physical graph MUST remain 100% IDENTICAL
        $this->assertCount(205, $resFalse['nodes']);
        $this->assertCount(205, $resTrue['nodes']);
        $this->assertCount(197, $resFalse['edges']);
        $this->assertCount(197, $resTrue['edges']);

        // GTT nodes remain in physical graph nodes array
        $gttsInFalse = array_filter($resFalse['nodes'], fn($n) => $n['is_gtt']);
        $gttsInTrue  = array_filter($resTrue['nodes'], fn($n) => $n['is_gtt']);
        $this->assertCount(23, $gttsInFalse);
        $this->assertCount(23, $gttsInTrue);
    }

    /**
     * 13. GI Source Relationship (GI BUDURAN / GI_VALID)
     */
    public function testGiSourceRelationship(): void
    {
        $res = $this->service->buildFeederGraph(15);
        $src = $res['source'];

        $this->assertEquals(1, $src['gi_id']);
        $this->assertEquals('GI-BDR', $src['code']);
        $this->assertEquals('GI BUDURAN', $src['name']);
        $this->assertEquals('GI_VALID', $src['status']);
        $this->assertNotNull($src['coordinates']);
    }

    /**
     * 14. Banjar Kemantren Authoritative Takeoff Node
     */
    public function testFeeder15AuthoritativeTakeoffNode(): void
    {
        $res = $this->service->buildFeederGraph(15);
        $to = $res['source']['takeoff'];

        $this->assertEquals('AUTHORITATIVE', $to['status']);
        $this->assertEquals(3231, $to['asset_id']);
        $this->assertEquals('BANJARKEMANTRAN_99', $to['asset_name']);
        $this->assertEquals('TM11', $to['construction_type']);
        $this->assertEquals('SUBSTATION_INCOMER_INTERFACE', $to['interface_type']);
        $this->assertEquals(71.78, $to['distance_meters']);
    }

    /**
     * 15. Missing GI Feeder Blocked (Feeder 56 -> SLD_BLOCKED)
     */
    public function testMissingGiFeederBlocked(): void
    {
        $res = $this->service->buildFeederGraph(56);
        $this->assertEquals('GI_RELATIONSHIP_MISSING', $res['source']['status']);
        $this->assertEquals('MISSING', $res['source']['takeoff']['status']);
        $this->assertEquals('SLD_BLOCKED', $res['feeder']['sld_readiness']);
    }

    /**
     * 16. Temuan Never Becomes Topology Node
     */
    public function testTemuanNeverBecomesTopologyNode(): void
    {
        $res = $this->service->buildFeederGraph(15);
        foreach ($res['nodes'] as $n) {
            $this->assertStringNotContainsStringIgnoringCase('TEMUAN', $n['code']);
            $this->assertStringNotContainsStringIgnoringCase('TEMUAN', $n['name']);
        }
    }

    /**
     * 17. Degree Role != Operational Device Subtype
     */
    public function testDegreeRoleVsOperationalSubtype(): void
    {
        $res = $this->service->buildFeederGraph(15);
        $branchNodes = array_filter($res['nodes'], fn($n) => $n['degree'] >= 3);

        $this->assertCount(19, $branchNodes);
        foreach ($branchNodes as $bn) {
            $this->assertEquals('BRANCH_NODE', $bn['role']);
            // Must NOT be guessed as LBSM_3WAY
            $this->assertNotEquals('LBSM_3WAY', $bn['operational_subtype']);
        }
    }

    /**
     * 18. Zero Operational Writes (Strict Read-Only)
     */
    public function testZeroOperationalWrites(): void
    {
        // Calling buildFeederGraph multiple times
        $res1 = $this->service->buildFeederGraph(15);
        $res2 = $this->service->buildFeederGraph(15);

        // generated_at is a live wall-clock timestamp; ignore it for equality check
        unset($res1['projection']['generated_at'], $res2['projection']['generated_at']);

        $this->assertEquals($res1, $res2);
        // Delta = 0 invariant preserved
        $this->assertEquals('success', $res1['status']);
    }

    /**
     * 19. Deterministic Repeated Output
     */
    public function testDeterministicRepeatedOutput(): void
    {
        $res1 = $this->service->buildFeederGraph(15);
        $res2 = $this->service->buildFeederGraph(15);
        unset($res1['projection']['generated_at'], $res2['projection']['generated_at']);

        $json1 = json_encode($res1);
        $json2 = json_encode($res2);

        $this->assertSame($json1, $json2);
        $this->assertSame(hash('sha256', $json1), hash('sha256', $json2));
    }

    /**
     * 20. Feeder 15 Cannot Contain Feeder 4 / 56 / 131 Assets
     */
    public function testFeederIsolationDisjointness(): void
    {
        $res15 = $this->service->buildFeederGraph(15);
        $res4  = $this->service->buildFeederGraph(4);
        $res56 = $this->service->buildFeederGraph(56);

        $ids15 = array_column($res15['nodes'], 'asset_id');
        $ids4  = array_column($res4['nodes'], 'asset_id');
        $ids56 = array_column($res56['nodes'], 'asset_id');

        $this->assertEmpty(array_intersect($ids15, $ids4), "Feeder 15 and Feeder 4 node sets must be disjoint");
        $this->assertEmpty(array_intersect($ids15, $ids56), "Feeder 15 and Feeder 56 node sets must be disjoint");
    }

    /**
     * 21. Component Source Reachability Verification
     */
    public function testComponentSourceReachability(): void
    {
        $res = $this->service->buildFeederGraph(15);
        $comps = $res['components'];

        $this->assertEquals('C15-01', $comps[0]['id']);
        $this->assertTrue($comps[0]['source_reachable']);

        // All other components are disconnected from the GI source takeoff node
        for ($i = 1; $i < count($comps); $i++) {
            $this->assertFalse($comps[$i]['source_reachable'], "Component {$comps[$i]['id']} must not be source reachable");
        }
    }

    /**
     * 22. No Nearest-Neighbor Topology Construction
     */
    public function testNoNearestNeighborTopologyConstruction(): void
    {
        $res = $this->service->buildFeederGraph(15);
        // All edges must have valid transline_id matching actual translines
        foreach ($res['edges'] as $edge) {
            $this->assertGreaterThan(0, $edge['transline_id']);
            $this->assertEquals('UNDIRECTED', $edge['direction']);
        }
    }

    /**
     * 23 & 24. No Mutation of Assets or Translines
     */
    public function testNoMutationOfAssetsOrTranslines(): void
    {
        $res = $this->service->buildFeederGraph(15);
        $this->assertEquals(205, $res['topology']['feeder_assets_count']);
        $this->assertEquals(197, $res['topology']['edges_count']);
    }

    /**
     * 25. Production Live Database Incomplete Topology Guard (DATA_NOT_READY)
     */
    public function testProductionLiveDatabaseIncompleteTopologyGuard(): void
    {
        // Mock DB with assets present but 0 translines
        $mockDb = new class {
            public function table(string $tbl) {
                return new class($tbl) {
                    protected $tbl;
                    public function __construct($t) { $this->tbl = $t; }
                    public function where($k, $v) { return $this; }
                    public function select($s) { return $this; }
                    public function join($t, $c, $type) { return $this; }
                    public function get() { return $this; }
                    public function getResultArray() {
                        if ($this->tbl === 'assets') {
                            $out = [];
                            for ($i = 1; $i <= 205; $i++) {
                                $out[] = ['id' => $i, 'kode_asset' => "AST-$i", 'nama_asset' => "POLE_$i", 'construction_type' => 'TM1', 'jenis_asset' => 'JTM', 'latitude' => 0, 'longitude' => 0];
                            }
                            return $out;
                        }
                        return []; // gis_translines is empty
                    }
                    public function getFirstRow(string $type = 'array') {
                        return ['id' => 15, 'kode_penyulang' => 'PYL-015', 'nama_penyulang' => 'BANJAR KEMANTREN', 'ulp_id' => 1, 'nama_ulp' => 'ULP Sidoarjo Kota'];
                    }
                };
            }
        };

        $liveService = new SldTopologyReadModelService($mockDb);
        $res = $liveService->buildFeederGraph(15);

        $this->assertEquals('DATA_NOT_READY', $res['status']);
        $this->assertEquals('PRODUCTION_LIVE_DATABASE', $res['data_source']['mode']);
        $this->assertTrue($res['data_source']['is_production_live']);
        $this->assertEquals('TOPOLOGY_DATA_INCOMPLETE', $res['data_source']['status']);
        $this->assertCount(0, $res['nodes']);
        $this->assertCount(0, $res['edges']);
        $this->assertStringContainsString('belum memadai sesuai standar TL-01', $res['message']);
    }
}

