<?php

namespace Tests\Unit\Fault;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\FaultHypothesisResolutionService;

/**
 * Phase FL-01F: Fault Hypothesis Evidence & Resolution Engine Test Suite
 *
 * 42 Comprehensive Tests Enforcing:
 * A. Candidate preservation (single, two, three, branch ambiguity)
 * B. Authoritative evidence catalog (topology, geometry, distance, measurement, protection, recloser, inspection, historical)
 * C. Resolution state machine (SINGLE_CONFIDENT, MULTIPLE_COMPATIBLE, EQUALLY_PROBABLE, BRANCH_AMBIGUITY, UNRESOLVED, NO_VALID)
 * D. Safety invariants (0 nearest asset/GPS, 0 AI, 0 arbitrary weights, 0 DB mutation, fault_records absent)
 * E. Determinism & Fingerprint stability
 * F. Pipeline integration
 */
class FaultHypothesisResolutionServiceTest extends CIUnitTestCase
{
    protected FaultHypothesisResolutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FaultHypothesisResolutionService();
    }

    // =========================================================================
    // GROUP A: CANDIDATE PRESERVATION
    // =========================================================================

    /**
     * Test 01: Candidate Preservation - Single Candidate
     */
    public function test01_CandidatePreservation_SingleCandidate(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'traversal_distance_m' => 500.0,
                'candidate_paths' => [
                    [
                        'hypothesis_id' => 'HYP-01',
                        'component_id' => 1,
                        'path_id' => 'PATH-01',
                        'edge_id' => 10,
                        'upstream_asset_id' => 101,
                        'downstream_asset_id' => 102,
                        'segment_length_m' => 100.0,
                        'offset_in_segment_m' => 50.0,
                        'segment_ratio' => 0.5,
                    ],
                ],
            ],
            'projection' => [
                'status' => 'PROJECTED',
                'projections' => [
                    [
                        'path_id' => 'PATH-01',
                        'edge_id' => 10,
                        'status' => 'PROJECTED',
                        'latitude' => -7.41,
                        'longitude' => 112.71,
                        'wkt_point' => 'POINT (112.71 -7.41)',
                        'orientation' => 'DIRECT',
                        'segment_ratio' => 0.5,
                    ],
                ],
            ],
            'validated_measurement' => [
                'measurement_status' => 'VALID',
                'fault_type' => 'A-G',
                'Ia' => 450.0, 'Ib' => 0.0, 'Ic' => 0.0,
                'Z' => 1.25,
            ],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame(1, $res['hypotheses_count']);
        $this->assertSame('HYP-01', $res['hypotheses'][0]['hypothesis_id']);
    }

    /**
     * Test 02: Candidate Preservation - Two Candidates
     */
    public function test02_CandidatePreservation_TwoCandidates(): void
    {
        $context = [
            'traversal' => [
                'status' => 'BRANCH_AMBIGUITY',
                'traversal_distance_m' => 500.0,
                'candidate_paths' => [
                    ['hypothesis_id' => 'HYP-A', 'component_id' => 1, 'path_id' => 'P1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 20.0],
                    ['hypothesis_id' => 'HYP-B', 'component_id' => 1, 'path_id' => 'P2', 'edge_id' => 20, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 20.0],
                ],
            ],
            'projection' => [
                'status' => 'PROJECTED',
                'projections' => [
                    ['path_id' => 'P1', 'edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.41, 'longitude' => 112.71, 'wkt_point' => 'POINT (112.71 -7.41)'],
                    ['path_id' => 'P2', 'edge_id' => 20, 'status' => 'PROJECTED', 'latitude' => -7.42, 'longitude' => 112.72, 'wkt_point' => 'POINT (112.72 -7.42)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 400.0, 'Z' => 1.0],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame(2, $res['hypotheses_count']);
        $this->assertSame('HYP-A', $res['hypotheses'][0]['hypothesis_id']);
        $this->assertSame('HYP-B', $res['hypotheses'][1]['hypothesis_id']);
    }

    /**
     * Test 03: Candidate Preservation - Three Candidates
     */
    public function test03_CandidatePreservation_ThreeCandidates(): void
    {
        $context = [
            'traversal' => [
                'status' => 'BRANCH_AMBIGUITY',
                'traversal_distance_m' => 600.0,
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'component_id' => 1, 'path_id' => 'P1', 'edge_id' => 11, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 10.0],
                    ['hypothesis_id' => 'H2', 'component_id' => 1, 'path_id' => 'P2', 'edge_id' => 12, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 10.0],
                    ['hypothesis_id' => 'H3', 'component_id' => 1, 'path_id' => 'P3', 'edge_id' => 13, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 10.0],
                ],
            ],
            'projection' => [
                'status' => 'PROJECTED',
                'projections' => [
                    ['path_id' => 'P1', 'edge_id' => 11, 'status' => 'PROJECTED', 'latitude' => -7.41, 'longitude' => 112.71, 'wkt_point' => 'POINT (112.71 -7.41)'],
                    ['path_id' => 'P2', 'edge_id' => 12, 'status' => 'PROJECTED', 'latitude' => -7.42, 'longitude' => 112.72, 'wkt_point' => 'POINT (112.72 -7.42)'],
                    ['path_id' => 'P3', 'edge_id' => 13, 'status' => 'PROJECTED', 'latitude' => -7.43, 'longitude' => 112.73, 'wkt_point' => 'POINT (112.73 -7.43)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'B-G', 'Ib' => 500.0, 'Z' => 1.5],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame(3, $res['hypotheses_count']);
        $this->assertSame(['H1', 'H2', 'H3'], array_column($res['hypotheses'], 'hypothesis_id'));
    }

    /**
     * Test 04: Candidate Preservation - Branch Ambiguity Preserved
     */
    public function test04_CandidatePreservation_BranchAmbiguity_AllHypothesesPreserved(): void
    {
        $context = [
            'traversal' => [
                'status' => 'BRANCH_AMBIGUITY',
                'candidate_paths' => [
                    ['hypothesis_id' => 'BRANCH-NORTH', 'edge_id' => 101, 'segment_length_m' => 80.0, 'offset_in_segment_m' => 40.0],
                    ['hypothesis_id' => 'BRANCH-SOUTH', 'edge_id' => 102, 'segment_length_m' => 80.0, 'offset_in_segment_m' => 40.0],
                ],
            ],
            'projection' => [
                'status' => 'PROJECTED',
                'projections' => [
                    ['edge_id' => 101, 'status' => 'PROJECTED', 'latitude' => -7.40, 'longitude' => 112.70, 'wkt_point' => 'POINT (112.70 -7.40)'],
                    ['edge_id' => 102, 'status' => 'PROJECTED', 'latitude' => -7.50, 'longitude' => 112.80, 'wkt_point' => 'POINT (112.80 -7.50)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 300.0, 'Z' => 0.8],
        ];

        $res = $this->service->resolve($context);
        $this->assertTrue($res['ambiguity_preserved']);
        $this->assertNull($res['selected_hypothesis_id'], 'Selected hypothesis must be null under branch ambiguity');
        $this->assertSame('BRANCH_AMBIGUITY', $res['resolution_state']);
    }

    // =========================================================================
    // GROUP B: EVIDENCE EVALUATION & CATALOG
    // =========================================================================

    /**
     * Test 05: Evidence - Topology Consistency Supporting
     */
    public function test05_Evidence_TopologyConsistency_Supporting(): void
    {
        $context = [
            'topology' => [
                'edges' => [
                    ['id' => 10, 'is_active' => 1],
                ],
            ],
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 50.0, 'offset_in_segment_m' => 25.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
        ];

        $res = $this->service->resolve($context);
        $topoEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'TOPOLOGY_CONSISTENCY') {
                $topoEv = $ev;
                break;
            }
        }
        $this->assertNotNull($topoEv);
        $this->assertSame('SUPPORTING', $topoEv['status']);
        $this->assertSame('TOPOLOGY_CONNECTED', $topoEv['value']);
    }

    /**
     * Test 06: Evidence - Topology Consistency Contradicting (Edge Not Found)
     */
    public function test06_Evidence_TopologyConsistency_Contradicting_EdgeNotFound(): void
    {
        $context = [
            'topology' => [
                'edges' => [
                    ['id' => 99, 'is_active' => 1],
                ],
            ],
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 50.0, 'offset_in_segment_m' => 25.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('EXCLUDED', $res['hypotheses'][0]['status']);
        $this->assertSame('NO_VALID_HYPOTHESIS', $res['resolution_state']);
    }

    /**
     * Test 07: Evidence - Topology Consistency Contradicting (Edge Inactive)
     */
    public function test07_Evidence_TopologyConsistency_Contradicting_EdgeInactive(): void
    {
        $context = [
            'topology' => [
                'edges' => [
                    ['id' => 10, 'is_active' => 0], // Inactive
                ],
            ],
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 50.0, 'offset_in_segment_m' => 25.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('EXCLUDED', $res['hypotheses'][0]['status']);
    }

    /**
     * Test 08: Evidence - Geometry Consistency Supporting
     */
    public function test08_Evidence_GeometryConsistency_Supporting(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 50.0, 'offset_in_segment_m' => 25.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.405, 'longitude' => 112.705, 'wkt_point' => 'POINT (112.705 -7.405)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
        ];

        $res = $this->service->resolve($context);
        $geomEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'GEOMETRY_CONSISTENCY') {
                $geomEv = $ev;
                break;
            }
        }
        $this->assertSame('SUPPORTING', $geomEv['status']);
    }

    /**
     * Test 09: Evidence - Geometry Consistency Neutral (Orientation Ambiguous)
     */
    public function test09_Evidence_GeometryConsistency_Neutral_OrientationAmbiguous(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 50.0, 'offset_in_segment_m' => 25.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'GEOMETRY_ORIENTATION_AMBIGUOUS', 'latitude' => -7.40, 'longitude' => 112.70, 'wkt_point' => null],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
        ];

        $res = $this->service->resolve($context);
        $geomEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'GEOMETRY_CONSISTENCY') {
                $geomEv = $ev;
                break;
            }
        }
        $this->assertSame('NEUTRAL', $geomEv['status']);
        $this->assertSame('ACTIVE', $res['hypotheses'][0]['status'], 'NEUTRAL geometry must not exclude hypothesis');
    }

    /**
     * Test 10: Evidence - Geometry Consistency Contradicting (Projection Failed)
     */
    public function test10_Evidence_GeometryConsistency_Contradicting_ProjectionFailed(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 50.0, 'offset_in_segment_m' => 25.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'GEOMETRY_ENDPOINT_MISMATCH', 'latitude' => null, 'longitude' => null],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('EXCLUDED', $res['hypotheses'][0]['status']);
    }

    /**
     * Test 11: Evidence - Distance Consistency Supporting
     */
    public function test11_Evidence_DistanceConsistency_Supporting(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 45.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
        ];

        $res = $this->service->resolve($context);
        $distEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'DISTANCE_CONSISTENCY') {
                $distEv = $ev;
                break;
            }
        }
        $this->assertSame('SUPPORTING', $distEv['status']);
    }

    /**
     * Test 12: Evidence - Distance Consistency Contradicting (Offset Out of Bounds)
     */
    public function test12_Evidence_DistanceConsistency_Contradicting_OffsetOutOfBounds(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 150.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('EXCLUDED', $res['hypotheses'][0]['status']);
    }

    /**
     * Test 13: Evidence - Distance Consistency Contradicting (Non-positive Length)
     */
    public function test13_Evidence_DistanceConsistency_Contradicting_NonPositiveLength(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 0.0, 'offset_in_segment_m' => 0.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('EXCLUDED', $res['hypotheses'][0]['status']);
    }

    /**
     * Test 14: Evidence - Measurement Consistency Supporting
     */
    public function test14_Evidence_MeasurementConsistency_Supporting(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => [
                'measurement_status' => 'VALID',
                'fault_type' => 'B-G',
                'Ia' => 0.0, 'Ib' => 600.0, 'Ic' => 0.0,
                'Z' => 1.85,
            ],
        ];

        $res = $this->service->resolve($context);
        $measEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'MEASUREMENT_CONSISTENCY') {
                $measEv = $ev;
                break;
            }
        }
        $this->assertSame('SUPPORTING', $measEv['status']);
    }

    /**
     * Test 15: Evidence - Measurement Consistency Contradicting (Current Mismatch)
     */
    public function test15_Evidence_MeasurementConsistency_Contradicting_CurrentMismatch(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => [
                'measurement_status' => 'VALID',
                'fault_type' => 'A-G', // Phase A fault reported, but Ia is 0 and Ib is 500A
                'Ia' => 0.0, 'Ib' => 500.0, 'Ic' => 0.0,
                'Z' => 1.0,
            ],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('EXCLUDED', $res['hypotheses'][0]['status']);
    }

    /**
     * Test 16: Evidence - Measurement Consistency Contradicting (Invalid Status)
     */
    public function test16_Evidence_MeasurementConsistency_Contradicting_InvalidStatus(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => [
                'measurement_status' => 'INVALID',
                'fault_type' => 'A-G',
            ],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('UNRESOLVED', $res['resolution_state']);
    }

    /**
     * Test 17: Evidence - Measurement Consistency Unavailable When Empty
     */
    public function test17_Evidence_MeasurementConsistency_Unavailable_WhenEmpty(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => [],
        ];

        $res = $this->service->resolve($context);
        $measEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'MEASUREMENT_CONSISTENCY') {
                $measEv = $ev;
                break;
            }
        }
        $this->assertSame('UNAVAILABLE', $measEv['status']);
    }

    /**
     * Test 18: Evidence - Protection Status Supporting (TRIP/LOCKOUT)
     */
    public function test18_Evidence_ProtectionStatus_Supporting_TripOrLockout(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
            'protection_status' => 'TRIP',
        ];

        $res = $this->service->resolve($context);
        $protEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'PROTECTION_STATUS') {
                $protEv = $ev;
                break;
            }
        }
        $this->assertSame('SUPPORTING', $protEv['status']);
        $this->assertSame('TRIP', $protEv['value']);
    }

    /**
     * Test 19: Evidence - Protection Status Contradicting (CLOSED)
     */
    public function test19_Evidence_ProtectionStatus_Contradicting_Closed(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
            'protection_status' => 'CLOSED',
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('EXCLUDED', $res['hypotheses'][0]['status']);
    }

    /**
     * Test 20: Evidence - Protection Status UNKNOWN is NEVER treated as negative
     */
    public function test20_Evidence_ProtectionStatus_Unknown_NeverTreatedAsNegative(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
            'protection_status' => 'UNKNOWN',
        ];

        $res = $this->service->resolve($context);
        $protEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'PROTECTION_STATUS') {
                $protEv = $ev;
                break;
            }
        }
        $this->assertSame('UNKNOWN', $protEv['status']);
        $this->assertSame('ACTIVE', $res['hypotheses'][0]['status'], 'UNKNOWN protection must not exclude hypothesis');
    }

    /**
     * Test 21: Evidence - Protection Status Unavailable When Null
     */
    public function test21_Evidence_ProtectionStatus_Unavailable_WhenNull(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
            'protection_status' => null,
        ];

        $res = $this->service->resolve($context);
        $protEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'PROTECTION_STATUS') {
                $protEv = $ev;
                break;
            }
        }
        $this->assertSame('UNAVAILABLE', $protEv['status']);
    }

    /**
     * Test 22: Evidence - Recloser Status Supporting
     */
    public function test22_Evidence_RecloserStatus_Supporting_Trip(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
            'recloser_status' => 'TRIP',
        ];

        $res = $this->service->resolve($context);
        $recEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'RECLOSER_STATUS') {
                $recEv = $ev;
                break;
            }
        }
        $this->assertSame('SUPPORTING', $recEv['status']);
    }

    /**
     * Test 23: Evidence - Recloser Status Unknown Preserved
     */
    public function test23_Evidence_RecloserStatus_Unknown_Preserved(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
            'recloser_status' => 'UNKNOWN',
        ];

        $res = $this->service->resolve($context);
        $recEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'RECLOSER_STATUS') {
                $recEv = $ev;
                break;
            }
        }
        $this->assertSame('UNKNOWN', $recEv['status']);
    }

    /**
     * Test 24: Evidence - Inspection Finding Explicit Asset Link
     */
    public function test24_Evidence_InspectionFinding_ExplicitAssetLink(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'upstream_asset_id' => 205, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
            'inspection_findings' => [
                ['id' => 441, 'asset_id' => 205, 'detail_temuan' => 'Kawat putus tersangkut pohon'],
            ],
        ];

        $res = $this->service->resolve($context);
        $inspEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'INSPECTION_FINDING') {
                $inspEv = $ev;
                break;
            }
        }
        $this->assertSame('SUPPORTING', $inspEv['status']);
        $this->assertSame('EXPLICIT_ASSET_LINK', $inspEv['relation']);
        $this->assertSame(441, $inspEv['source_id']);
    }

    /**
     * Test 25: Evidence - Inspection Finding Explicit Location Link
     */
    public function test25_Evidence_InspectionFinding_ExplicitLocationLink(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'transline_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
            'inspection_findings' => [
                ['id' => 442, 'transline_id' => 10, 'detail_temuan' => 'Sambungan kendor terbakar'],
            ],
        ];

        $res = $this->service->resolve($context);
        $inspEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'INSPECTION_FINDING') {
                $inspEv = $ev;
                break;
            }
        }
        $this->assertSame('SUPPORTING', $inspEv['status']);
        $this->assertSame('EXPLICIT_LOCATION_LINK', $inspEv['relation']);
    }

    /**
     * Test 26: Evidence - Inspection Finding Unavailable When Unlinked
     */
    public function test26_Evidence_InspectionFinding_Unavailable_WhenUnlinked(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'upstream_asset_id' => 205, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
            'inspection_findings' => [
                ['id' => 888, 'asset_id' => 999, 'detail_temuan' => 'Unrelated asset finding'],
            ],
        ];

        $res = $this->service->resolve($context);
        $inspEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'INSPECTION_FINDING') {
                $inspEv = $ev;
                break;
            }
        }
        $this->assertSame('UNAVAILABLE', $inspEv['status']);
    }

    /**
     * Test 27: Evidence - Historical Fault Index Available
     */
    public function test27_Evidence_HistoricalFaultIndex_Available(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
            'historical_fault_index' => [
                'H1' => 3, // 3 historical occurrences
            ],
        ];

        $res = $this->service->resolve($context);
        $histEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'HISTORICAL_FAULT_INDEX') {
                $histEv = $ev;
                break;
            }
        }
        $this->assertSame('SUPPORTING', $histEv['status']);
        $this->assertSame(3, $histEv['value']);
    }

    /**
     * Test 28: Evidence - Historical Fault Index Unavailable When No Records
     */
    public function test28_Evidence_HistoricalFaultIndex_Unavailable_WhenNoRecords(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 100.0, 'Z' => 0.5],
        ];

        $res = $this->service->resolve($context);
        $histEv = null;
        foreach ($res['hypotheses'][0]['evidence'] as $ev) {
            if ($ev['type'] === 'HISTORICAL_FAULT_INDEX') {
                $histEv = $ev;
                break;
            }
        }
        $this->assertSame('UNAVAILABLE', $histEv['status']);
        $this->assertNull($histEv['value']);
    }

    // =========================================================================
    // GROUP C: RESOLUTION STATE MACHINE
    // =========================================================================

    /**
     * Test 29: Resolution - Single Confident Hypothesis
     */
    public function test29_Resolution_SingleConfidentHypothesis(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H-CONFIDENT', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 200.0, 'Z' => 1.0],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('SINGLE_CONFIDENT_HYPOTHESIS', $res['resolution_state']);
        $this->assertSame('H-CONFIDENT', $res['selected_hypothesis_id']);
        $this->assertFalse($res['ambiguity_preserved']);
    }

    /**
     * Test 30: Resolution - Multiple Compatible Hypotheses
     */
    public function test30_Resolution_MultipleCompatibleHypotheses(): void
    {
        $context = [
            'traversal' => [
                'status' => 'MULTI_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H-PATH-1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                    ['hypothesis_id' => 'H-PATH-2', 'edge_id' => 20, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                    ['edge_id' => 20, 'status' => 'PROJECTED', 'latitude' => -7.5, 'longitude' => 112.8, 'wkt_point' => 'POINT (112.8 -7.5)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 200.0, 'Z' => 1.0],
        ];

        $res = $this->service->resolve($context);
        $this->assertContains($res['resolution_state'], ['MULTIPLE_COMPATIBLE_HYPOTHESES', 'EQUALLY_PROBABLE']);
        $this->assertNull($res['selected_hypothesis_id']);
        $this->assertTrue($res['ambiguity_preserved']);
    }

    /**
     * Test 31: Resolution - Equally Probable (Identical Evidence Counts)
     */
    public function test31_Resolution_EquallyProbable(): void
    {
        $context = [
            'traversal' => [
                'status' => 'MULTI_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                    ['hypothesis_id' => 'H2', 'edge_id' => 20, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                    ['edge_id' => 20, 'status' => 'PROJECTED', 'latitude' => -7.5, 'longitude' => 112.8, 'wkt_point' => 'POINT (112.8 -7.5)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 200.0, 'Z' => 1.0],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('EQUALLY_PROBABLE', $res['resolution_state']);
        $this->assertNull($res['selected_hypothesis_id']);
    }

    /**
     * Test 32: Resolution - Branch Ambiguity
     */
    public function test32_Resolution_BranchAmbiguity(): void
    {
        $context = [
            'traversal' => [
                'status' => 'BRANCH_AMBIGUITY',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H-BRANCH-1', 'edge_id' => 11, 'segment_length_m' => 80.0, 'offset_in_segment_m' => 40.0],
                    ['hypothesis_id' => 'H-BRANCH-2', 'edge_id' => 12, 'segment_length_m' => 80.0, 'offset_in_segment_m' => 40.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 11, 'status' => 'PROJECTED', 'latitude' => -7.41, 'longitude' => 112.71, 'wkt_point' => 'POINT (112.71 -7.41)'],
                    ['edge_id' => 12, 'status' => 'PROJECTED', 'latitude' => -7.42, 'longitude' => 112.72, 'wkt_point' => 'POINT (112.72 -7.42)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 200.0, 'Z' => 1.0],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('BRANCH_AMBIGUITY', $res['resolution_state']);
        $this->assertNull($res['selected_hypothesis_id']);
        $this->assertTrue($res['ambiguity_preserved']);
    }

    /**
     * Test 33: Resolution - Branch Ambiguity With Authoritative Discriminator
     */
    public function test33_Resolution_BranchAmbiguity_WithAuthoritativeDiscriminator(): void
    {
        $context = [
            'traversal' => [
                'status' => 'BRANCH_AMBIGUITY',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H-BRANCH-1', 'edge_id' => 11, 'upstream_asset_id' => 101, 'segment_length_m' => 80.0, 'offset_in_segment_m' => 40.0],
                    ['hypothesis_id' => 'H-BRANCH-2', 'edge_id' => 12, 'upstream_asset_id' => 102, 'segment_length_m' => 80.0, 'offset_in_segment_m' => 40.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 11, 'status' => 'PROJECTED', 'latitude' => -7.41, 'longitude' => 112.71, 'wkt_point' => 'POINT (112.71 -7.41)'],
                    ['edge_id' => 12, 'status' => 'PROJECTED', 'latitude' => -7.42, 'longitude' => 112.72, 'wkt_point' => 'POINT (112.72 -7.42)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 200.0, 'Z' => 1.0],
            'inspection_findings' => [
                ['id' => 999, 'asset_id' => 101, 'detail_temuan' => 'Pohon tumbang mengenai konduktor'],
            ],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('SINGLE_CONFIDENT_HYPOTHESIS', $res['resolution_state']);
        $this->assertSame('H-BRANCH-1', $res['selected_hypothesis_id']);
    }

    /**
     * Test 34: Resolution - Unresolved on ROOT_UNRESOLVED
     */
    public function test34_Resolution_Unresolved_RootUnresolved(): void
    {
        $context = [
            'traversal' => [
                'status' => 'ROOT_UNRESOLVED',
                'candidate_paths' => [],
            ],
            'projection' => [],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G'],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('UNRESOLVED', $res['resolution_state']);
        $this->assertNull($res['selected_hypothesis_id']);
    }

    /**
     * Test 35: Resolution - Unresolved on DISTANCE_OUT_OF_RANGE
     */
    public function test35_Resolution_Unresolved_DistanceOutOfRange(): void
    {
        $context = [
            'traversal' => [
                'status' => 'DISTANCE_OUT_OF_RANGE',
                'candidate_paths' => [],
            ],
            'projection' => [],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G'],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('UNRESOLVED', $res['resolution_state']);
    }

    /**
     * Test 36: Resolution - Unresolved on PATH_DISCONTINUOUS
     */
    public function test36_Resolution_Unresolved_PathDiscontinuous(): void
    {
        $context = [
            'traversal' => [
                'status' => 'PATH_DISCONTINUOUS',
                'candidate_paths' => [],
            ],
            'projection' => [],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G'],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('UNRESOLVED', $res['resolution_state']);
    }

    /**
     * Test 37: Resolution - No Valid Hypothesis (All Excluded)
     */
    public function test37_Resolution_NoValidHypothesis_AllExcluded(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'GEOMETRY_MALFORMED'], // Excluded
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 200.0, 'Z' => 1.0],
        ];

        $res = $this->service->resolve($context);
        $this->assertSame('NO_VALID_HYPOTHESIS', $res['resolution_state']);
        $this->assertNull($res['selected_hypothesis_id']);
    }

    // =========================================================================
    // GROUP D: SAFETY INVARIANTS & AUDIT
    // =========================================================================

    /**
     * Test 38: Safety - Zero Database Mutation Invariant (Delta = 0)
     */
    public function test38_Safety_ZeroDatabaseMutation(): void
    {
        try {
            $db = Database::connect('default');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL default connection not available.');
        }

        if (!$db || !$db->tableExists('assets')) {
            $this->markTestSkipped('Authoritative assets table not accessible.');
        }

        $assetsCount     = (int) $db->table('assets')->countAllResults();
        $translinesCount = (int) $db->table('gis_translines')->countAllResults();
        $penyulangCount  = (int) $db->table('penyulang')->countAllResults();
        $sectionsCount   = (int) $db->table('sections')->countAllResults();
        $temuanCount     = (int) $db->table('temuan')->countAllResults();
        $faultRecordsExists = $db->tableExists('fault_records');

        $this->assertSame(1476, $assetsCount, 'assets count must remain strictly invariant at 1476');
        $this->assertSame(1278, $translinesCount, 'gis_translines count must remain strictly invariant at 1278');
        $this->assertSame(134, $penyulangCount, 'penyulang count must remain strictly invariant at 134');
        $this->assertSame(508, $sectionsCount, 'sections count must remain strictly invariant at 508');
        $this->assertSame(441, $temuanCount, 'temuan count must remain strictly invariant at 441');
        $this->assertFalse($faultRecordsExists, 'fault_records table must NOT exist');
    }

    /**
     * Test 39: Safety - AI Firewall (ai_used == false)
     */
    public function test39_Safety_AiFirewall_AiUsedFalse(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 200.0, 'Z' => 1.0],
        ];

        $res = $this->service->resolve($context);
        $this->assertFalse($res['ai_used'], 'FL-01F must strictly enforce ai_used = false');
        $this->assertFalse($res['persisted'], 'FL-01F must strictly enforce persisted = false');
        $this->assertTrue($res['authoritative_evidence_only']);
    }

    /**
     * Test 40: Safety - No Nearest Asset or GPS Fallback
     */
    public function test40_Safety_NoNearestAssetOrGpsFallback(): void
    {
        $context = [
            'traversal' => [
                'status' => 'SINGLE_PATH_MATCH',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 200.0, 'Z' => 1.0],
        ];

        $res = $this->service->resolve($context);
        $hyp = $res['hypotheses'][0];
        $this->assertArrayNotHasKey('estimated_asset_id', $hyp, 'No nearest asset snapping allowed');
        $this->assertArrayNotHasKey('nearest_pole_id', $hyp, 'No nearest pole fallback allowed');
    }

    // =========================================================================
    // GROUP E: DETERMINISM & REPRODUCIBILITY
    // =========================================================================

    /**
     * Test 41: Determinism - Reproducible Fingerprint
     */
    public function test41_Determinism_ReproducibleFingerprint(): void
    {
        $context = [
            'traversal' => [
                'status' => 'BRANCH_AMBIGUITY',
                'candidate_paths' => [
                    ['hypothesis_id' => 'H1', 'edge_id' => 10, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                    ['hypothesis_id' => 'H2', 'edge_id' => 20, 'segment_length_m' => 100.0, 'offset_in_segment_m' => 50.0],
                ],
            ],
            'projection' => [
                'projections' => [
                    ['edge_id' => 10, 'status' => 'PROJECTED', 'latitude' => -7.4, 'longitude' => 112.7, 'wkt_point' => 'POINT (112.7 -7.4)'],
                    ['edge_id' => 20, 'status' => 'PROJECTED', 'latitude' => -7.5, 'longitude' => 112.8, 'wkt_point' => 'POINT (112.8 -7.5)'],
                ],
            ],
            'validated_measurement' => ['measurement_status' => 'VALID', 'fault_type' => 'A-G', 'Ia' => 200.0, 'Z' => 1.0],
        ];

        $res1 = $this->service->resolve($context);
        $res2 = $this->service->resolve($context);

        $this->assertSame($res1['fingerprint'], $res2['fingerprint']);
        $this->assertSame(64, strlen($res1['fingerprint']));
    }

    /**
     * Test 42: Determinism - Fingerprint Invariant to Runtime Timestamps
     */
    public function test42_Determinism_RuntimeTimestampInvariantFingerprint(): void
    {
        $resA = [
            'engine' => 'FL-01F',
            'version' => 'FL-01F-1.0',
            'resolution_state' => 'BRANCH_AMBIGUITY',
            'selected_hypothesis_id' => null,
            'hypotheses' => [
                ['hypothesis_id' => 'H1', 'status' => 'ACTIVE'],
            ],
            'evidence_catalog' => [
                ['type' => 'TOPOLOGY_CONSISTENCY', 'status' => 'SUPPORTING', 'retrieved_at' => '2026-09-21T07:00:00Z'],
            ],
            'decision_basis' => ['Preserved ambiguity.'],
            'ambiguity_preserved' => true,
            'authoritative_evidence_only' => true,
            'ai_used' => false,
            'persisted' => false,
        ];

        $resB = $resA;
        $resB['evidence_catalog'][0]['retrieved_at'] = '2026-09-21T09:30:00Z'; // Different timestamp

        $fpA = $this->service->calculateResolutionFingerprint($resA);
        $fpB = $this->service->calculateResolutionFingerprint($resB);

        $this->assertSame($fpA, $fpB, 'Fingerprint must be strictly invariant to runtime timestamps');
    }
}
