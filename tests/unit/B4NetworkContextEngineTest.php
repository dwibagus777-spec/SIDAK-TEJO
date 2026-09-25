<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\NetworkIntelligenceService;
use App\Services\NetworkContextEngine;

/**
 * B4NetworkContextEngineTest
 *
 * Unit Test Suite for Phase B.4.1 Network Context Engine:
 * 1. Asset Context Determinism
 * 2. Feeder 118 Anchor Context & Feeder Head Distinction
 * 3. Disconnected Asset Returns Null Distance (Guard 1: Strictly NOT 0.0)
 * 4. Deterministic Upstream Path Lineage
 * 5. Deterministic Downstream Subtree Telemetry
 * 6. Nearest Protective Switch Detection
 * 7. Candidate Asset Search by Distance with Dynamic Tolerance
 * 8. Conductor Impedance Availability Flag Verification (Guard 5)
 */
class B4NetworkContextEngineTest extends TestCase
{
    protected NetworkContextEngine $contextEngine;
    protected array $fixtureAssets;
    protected array $fixtureTranslines;

    protected function setUp(): void
    {
        parent::setUp();
        $intelligenceService = new NetworkIntelligenceService();
        $this->contextEngine = new NetworkContextEngine($intelligenceService);

        // Canonical Fixtures
        $this->fixtureAssets = [
            // Feeder 118: Tiang 33 (5245) <-> Tiang 34 (5246)
            [
                'id'                   => 5245,
                'kode_asset'           => 'AST-KRN-BHGSTL1-JTM-033',
                'nama_asset'           => 'Tiang 33',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 50,
                'penyulang_id'         => 118,
                'ulp_id'               => 1,
                'sequence_no'          => 33,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 5246,
                'kode_asset'           => 'AST-KRN-BHGSTL1-JTM-034',
                'nama_asset'           => 'Tiang 34',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 50,
                'penyulang_id'         => 118,
                'ulp_id'               => 1,
                'sequence_no'          => 34,
                'status'               => 'NORMAL',
            ],
            // Feeder 118: Disconnected / Blocked assets (Section 46)
            [
                'id'                   => 5232,
                'kode_asset'           => 'AST-KRN-BHGSTL1-JTM-020',
                'nama_asset'           => 'Tiang 20',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 46,
                'penyulang_id'         => 118,
                'ulp_id'               => 1,
                'sequence_no'          => 20,
                'status'               => 'NORMAL',
            ],
            // Feeder 15: Assets with LBS switch
            [
                'id'                   => 101,
                'kode_asset'           => 'BANJARKEMANTRAN_28',
                'nama_asset'           => 'BANJARKEMANTRAN_28 LBS',
                'jenis_asset'          => 'LBS',
                'section_id'           => 46,
                'penyulang_id'         => 15,
                'ulp_id'               => 1,
                'sequence_no'          => 28,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 102,
                'kode_asset'           => 'BANJARKEMANTRAN_29',
                'nama_asset'           => 'BANJARKEMANTRAN_29',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 47,
                'penyulang_id'         => 15,
                'ulp_id'               => 1,
                'sequence_no'          => 29,
                'status'               => 'NORMAL',
            ],
            [
                'id'                   => 103,
                'kode_asset'           => 'BANJARKEMANTRAN_30',
                'nama_asset'           => 'BANJARKEMANTRAN_30',
                'jenis_asset'          => 'TIANG_BETON',
                'section_id'           => 47,
                'penyulang_id'         => 15,
                'ulp_id'               => 1,
                'sequence_no'          => 30,
                'status'               => 'NORMAL',
            ],
        ];

        $this->fixtureTranslines = [
            // Feeder 118: Strictly 1 edge
            [
                'id'                 => 335,
                'transline_code'     => 'TL-118-5245-5246',
                'penyulang_id'       => 118,
                'source_asset_id'    => 5245,
                'target_asset_id'    => 5246,
                'distance_meters'    => 27.82,
                'conductor_type'     => 'XLPE',
                'conductor_size'     => '150 mm²',
                'status'             => 'ACTIVE',
                'is_active'          => 1,
            ],
            // Feeder 15: Edges
            [
                'id'                 => 6,
                'transline_code'     => 'TL-15-6',
                'penyulang_id'       => 15,
                'source_asset_id'    => 101,
                'target_asset_id'    => 102,
                'distance_meters'    => 45.0,
                'conductor_type'     => 'AAAC',
                'conductor_size'     => '150 mm²',
                'status'             => 'ACTIVE',
                'is_active'          => 1,
            ],
            [
                'id'                 => 7,
                'transline_code'     => 'TL-15-7',
                'penyulang_id'       => 15,
                'source_asset_id'    => 102,
                'target_asset_id'    => 103,
                'distance_meters'    => 50.0,
                'conductor_type'     => 'AAAC',
                'conductor_size'     => '150 mm²',
                'status'             => 'ACTIVE',
                'is_active'          => 1,
            ],
        ];
    }

    /**
     * TEST 01: Asset Context Determinism
     */
    public function testAssetContextDeterministic(): void
    {
        $context = $this->contextEngine->getAssetContext(5246, $this->fixtureAssets, $this->fixtureTranslines);

        $this->assertEquals('SIDAK TEJO', $context['system']);
        $this->assertEquals('TOPOLOGY-20260925-243-ad2c9fcb', $context['snapshot_id']);

        $payload = $context['payload'];
        $this->assertEquals(5246, $payload['asset']['id']);
        $this->assertEquals('AST-KRN-BHGSTL1-JTM-034', $payload['asset']['kode_asset']);
        $this->assertEquals(1, $payload['topology_metrics']['degree']);
        $this->assertEquals('TERMINAL', $payload['topology_metrics']['topological_role']);
        $this->assertCount(1, $payload['topology_metrics']['connected_translines']);
        $this->assertEquals(335, $payload['topology_metrics']['connected_translines'][0]['transline_id']);
    }

    /**
     * TEST 02: Feeder 118 Context & Feeder Head Distinction
     */
    public function testFeeder118ContextDistinction(): void
    {
        $context = $this->contextEngine->getAssetContext(5246, $this->fixtureAssets, $this->fixtureTranslines);
        $upstream = $context['payload']['upstream_lineage'];

        $this->assertTrue($upstream['reachable']);
        $this->assertEquals(5245, $upstream['feeder_head']['asset_id']);
        $this->assertEqualsWithDelta(27.82, $upstream['distance_to_feeder_head_m'], 0.05);
        $this->assertEquals(1, $upstream['hops_to_feeder_head']);
    }

    /**
     * TEST 03: Disconnected Asset Returns Null Distance (Guard 1 Invariant)
     */
    public function testDisconnectedAssetReturnsNullDistance(): void
    {
        // Tiang 20 (5232) is isolated on Feeder 118
        $context = $this->contextEngine->getAssetContext(5232, $this->fixtureAssets, $this->fixtureTranslines);
        $upstream = $context['payload']['upstream_lineage'];

        $this->assertFalse($upstream['reachable']);
        $this->assertEquals('NO_AUTHORITATIVE_PATH_TO_FEEDER_HEAD', $upstream['unreachable_reason']);
        $this->assertNull($upstream['distance_to_feeder_head_m'], 'Guard 1 breach: distance must be null when disconnected.');
        $this->assertNull($upstream['hops_to_feeder_head'], 'Guard 1 breach: hops must be null when disconnected.');
    }

    /**
     * TEST 04: Deterministic Upstream Path Lineage
     */
    public function testUpstreamPathDeterministic(): void
    {
        $context = $this->contextEngine->getAssetContext(5246, $this->fixtureAssets, $this->fixtureTranslines);
        $upstream = $context['payload']['upstream_lineage'];

        $this->assertEquals([5245, 5246], $upstream['upstream_path_node_ids']);
    }

    /**
     * TEST 05: Deterministic Downstream Subtree Telemetry
     */
    public function testDownstreamSubtreeDeterministic(): void
    {
        // Root Tiang 33 (5245) has Tiang 34 (5246) downstream
        $context = $this->contextEngine->getAssetContext(5245, $this->fixtureAssets, $this->fixtureTranslines);
        $downstream = $context['payload']['downstream_lineage'];

        $this->assertEquals(1, $downstream['downstream_asset_count']);
        $this->assertEqualsWithDelta(27.82, $downstream['total_downstream_distance_m'], 0.05);
    }

    /**
     * TEST 06: Nearest Protective Switch Detection
     */
    public function testNearestProtectiveSwitchResolution(): void
    {
        // In Feeder 15, asset 103 (Banjarkemantran 30) has switch at asset 101 (Banjarkemantran 28 LBS)
        $context = $this->contextEngine->getAssetContext(103, $this->fixtureAssets, $this->fixtureTranslines);
        $upstream = $context['payload']['upstream_lineage'];

        $this->assertNotNull($upstream['nearest_upstream_switch']);
        $this->assertEquals(101, $upstream['nearest_upstream_switch']['asset_id']);
        $this->assertEquals('LBS', $upstream['nearest_upstream_switch']['switch_type']);
        $this->assertEqualsWithDelta(95.0, $upstream['nearest_upstream_switch']['distance_m'], 0.1);
    }

    /**
     * TEST 07: Candidate Asset Search by Distance with Dynamic Tolerance (Guard 7)
     */
    public function testDistanceCandidateSearchWithDynamicTolerance(): void
    {
        // Target: 25.0 meters on Feeder 118, tolerance: 10 meters
        $res = $this->contextEngine->findCandidateAssetsByDistance(118, 25.0, 10.0, $this->fixtureAssets, $this->fixtureTranslines);
        $payload = $res['payload'];

        $this->assertEquals(1, $payload['candidates_count']);
        $candidate = $payload['candidates'][0];
        $this->assertEquals(5246, $candidate['asset_id']);
        $this->assertEquals(1, $candidate['preliminary_rank']);
        $this->assertEqualsWithDelta(27.82, $candidate['electrical_distance_m'], 0.05);
        $this->assertEqualsWithDelta(2.82, $candidate['distance_deviation_m'], 0.05);
        $this->assertGreaterThan(0.7, $candidate['distance_fitness']);
    }

    /**
     * TEST 08: Conductor Impedance Availability Flag Verification (Guard 5)
     */
    public function testImpedanceAvailabilityFlagIsStrictlyFalse(): void
    {
        $context = $this->contextEngine->getAssetContext(5246, $this->fixtureAssets, $this->fixtureTranslines);
        $topo = $context['payload']['topology_metrics'];

        $this->assertFalse($topo['impedance_supported'], 'Guard 5 breach: impedance_supported must be strictly false until canonical registry.');
        $this->assertEquals('NO_CANONICAL_IMPEDANCE_PROFILE', $topo['impedance_status_reason']);
    }
}
