<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\SldFindingAnnotationLayoutEngineService;
use App\Services\SldFindingOverlayService;
use App\Services\SldSheetComposerService;
use App\Services\SldLayoutCoordinateEngineService;

/**
 * Class Sld05TFindingAnnotationLayoutEngineTest
 *
 * Unit test suite for SLD-05T Technical Finding Annotation Layout Engine:
 * Verifies all 5 mandatory refinements from the user:
 * 1. Location-linked (ROW) findings never bind to nearest asset and never create topology nodes.
 * 2. Printable CAD callout format with explicit text priority.
 * 3. Strict determinism in collision avoidance and leader line routing.
 * 4. Strict sheet boundary firewall and containment.
 * 5. Topology invariance: Delta_nodes = 0, Delta_edges = 0, Delta_db = 0.
 */
class Sld05TFindingAnnotationLayoutEngineTest extends CIUnitTestCase
{
    protected SldFindingAnnotationLayoutEngineService $engine;
    protected array $mockNodes;
    protected array $mockSheets;
    protected array $canvasMeta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new SldFindingAnnotationLayoutEngineService();

        $this->canvasMeta = [
            'scale_x'  => 75.0,
            'scale_y'  => 85.0,
            'offset_x' => 160.0,
            'offset_y' => 180.0,
        ];

        // Mock 4 nodes across 2 sheets
        $this->mockNodes = [
            [
                'asset_id'   => 421,
                'asset_code' => 'PA421',
                'render_x'   => 310.0,
                'render_y'   => 265.0,
                'schematic'  => ['grid_x' => 2, 'grid_y' => 1],
                'geo'        => ['latitude' => -7.4210, 'longitude' => 112.7110],
            ],
            [
                'asset_id'   => 422,
                'asset_code' => 'PA422',
                'render_x'   => 385.0,
                'render_y'   => 265.0,
                'schematic'  => ['grid_x' => 3, 'grid_y' => 1],
                'geo'        => ['latitude' => -7.4220, 'longitude' => 112.7130],
            ],
            [
                'asset_id'   => 423,
                'asset_code' => 'PA423',
                'render_x'   => 1200.0,
                'render_y'   => 265.0,
                'schematic'  => ['grid_x' => 14, 'grid_y' => 1],
                'geo'        => ['latitude' => -7.4320, 'longitude' => 112.7300],
            ],
        ];

        // Sheet 01 (contains 421, 422), Sheet 02 (contains 423)
        $this->mockSheets = [
            [
                'sheet_index' => 1,
                'sheet_code'  => 'SHEET-01',
                'node_ids'    => [421, 422],
                'view_box'    => ['x' => 0, 'y' => 0, 'w' => 800, 'h' => 600],
            ],
            [
                'sheet_index' => 2,
                'sheet_code'  => 'SHEET-02',
                'node_ids'    => [423],
                'view_box'    => ['x' => 900, 'y' => 0, 'w' => 800, 'h' => 600],
            ],
        ];
    }

    /**
     * 1. Test anchor coordinate resolution for ASSET_LINKED.
     */
    public function testResolveAnchorCoordinatesAssetLinked(): void
    {
        $finding = [
            'id'       => 101,
            'asset_id' => 421,
        ];
        $nodeMap = [421 => $this->mockNodes[0]];

        $anchor = $this->engine->resolveAnchor($finding, $nodeMap, $this->canvasMeta);

        $this->assertSame('ASSET_LINKED', $anchor['model_type']);
        $this->assertEquals(310.0, $anchor['anchor_x']);
        $this->assertEquals(265.0, $anchor['anchor_y']);
        $this->assertTrue($anchor['is_bound_asset']);
        $this->assertNotNull($anchor['target_node']);
        $this->assertSame('PA421', $anchor['target_node']['asset_code']);
    }

    /**
     * 2. Test anchor coordinate resolution for LOCATION_LINKED (ROW).
     */
    public function testResolveAnchorCoordinatesLocationLinkedRow(): void
    {
        $finding = [
            'id'        => 104,
            'asset_id'  => null,
            'latitude'  => -7.4260,
            'longitude' => 112.7200,
        ];
        $nodeMap = [
            421 => $this->mockNodes[0],
            422 => $this->mockNodes[1],
            423 => $this->mockNodes[2],
        ];

        $anchor = $this->engine->resolveAnchor($finding, $nodeMap, $this->canvasMeta);

        $this->assertSame('LOCATION_LINKED', $anchor['model_type']);
        $this->assertFalse($anchor['is_bound_asset']);
        $this->assertNull($anchor['target_node'], 'LOCATION_LINKED must never bind a target node');
        $this->assertGreaterThan(0, $anchor['anchor_x']);
        $this->assertGreaterThan(0, $anchor['anchor_y']);
    }

    /**
     * 3. Test sheet resolution assigns findings to correct sheet.
     */
    public function testSheetResolutionForFindings(): void
    {
        $findingSheet1 = ['id' => 1, 'asset_id' => 421];
        $findingSheet2 = ['id' => 2, 'asset_id' => 423];

        $nodeMap = [];
        foreach ($this->mockNodes as $n) $nodeMap[$n['asset_id']] = $n;

        $sheetMap = [];
        foreach ($this->mockSheets as $s) $sheetMap[$s['sheet_index']] = $s;

        $anchor1 = $this->engine->resolveAnchor($findingSheet1, $nodeMap, $this->canvasMeta);
        $sheetId1 = $this->engine->resolveSheetId($findingSheet1, $anchor1, $sheetMap, $nodeMap);
        $this->assertSame(1, $sheetId1, 'Asset 421 must belong to Sheet 1');

        $anchor2 = $this->engine->resolveAnchor($findingSheet2, $nodeMap, $this->canvasMeta);
        $sheetId2 = $this->engine->resolveSheetId($findingSheet2, $anchor2, $sheetMap, $nodeMap);
        $this->assertSame(2, $sheetId2, 'Asset 423 must belong to Sheet 2');
    }

    /**
     * 4. Test collision avoidance between adjacent findings.
     */
    public function testCollisionAvoidanceBetweenAdjacentFindings(): void
    {
        // Two findings on adjacent poles (421 and 422) in Sheet 01
        $findings = [
            [
                'id'           => 175,
                'nomor_temuan' => 'STJ-2026-000175',
                'asset_id'     => 421,
                'prioritas'    => 'HIGH',
                'jenis_temuan' => 'COVER TIDAK LENGKAP',
            ],
            [
                'id'           => 176,
                'nomor_temuan' => 'STJ-2026-000176',
                'asset_id'     => 422,
                'prioritas'    => 'HIGH',
                'jenis_temuan' => 'FCO TUA 3BH',
            ],
        ];

        $annotated = $this->engine->buildAnnotationModel(15, $findings, $this->mockNodes, $this->mockSheets, $this->canvasMeta);
        $this->assertCount(2, $annotated);

        $box1 = $annotated[0]['annotation'];
        $box2 = $annotated[1]['annotation'];

        // Verify AABB does not overlap
        $overlapX = ($box1['box_x'] < $box2['box_x'] + $box2['box_width']) && ($box1['box_x'] + $box1['box_width'] > $box2['box_x']);
        $overlapY = ($box1['box_y'] < $box2['box_y'] + $box2['box_height']) && ($box1['box_y'] + $box1['box_height'] > $box2['box_y']);

        $this->assertFalse($overlapX && $overlapY, 'Adjacent callout boxes must NOT collide or overlap');
    }

    /**
     * 5. Test leader line routing coordinates are valid.
     */
    public function testLeaderLineRoutingCoordinates(): void
    {
        $findings = [
            [
                'id'           => 175,
                'asset_id'     => 421,
                'prioritas'    => 'HIGH',
                'jenis_temuan' => 'COVER TIDAK LENGKAP',
            ],
        ];

        $annotated = $this->engine->buildAnnotationModel(15, $findings, $this->mockNodes, $this->mockSheets, $this->canvasMeta);
        $ann = $annotated[0]['annotation'];

        $this->assertNotEmpty($ann['leader_points']);
        $this->assertGreaterThanOrEqual(2, count($ann['leader_points']));

        // First point must be the physical anchor
        $firstPt = $ann['leader_points'][0];
        $this->assertEquals($ann['anchor_x'], $firstPt['x']);
        $this->assertEquals($ann['anchor_y'], $firstPt['y']);

        // Last point must touch the callout box boundary
        $lastPt = end($ann['leader_points']);
        $this->assertGreaterThanOrEqual($ann['box_x'] - 1.0, $lastPt['x']);
        $this->assertLessThanOrEqual($ann['box_x'] + $ann['box_width'] + 1.0, $lastPt['x']);
    }

    /**
     * 6. Test explicit text priority for black & white print compliance.
     */
    public function testPriorityTextAlwaysExplicit(): void
    {
        $high = $this->engine->formatFindingText(['id' => 1, 'prioritas' => 'DARURAT'], null);
        $this->assertSame('HIGH', $high['priority']);

        $med = $this->engine->formatFindingText(['id' => 2, 'prioritas' => 'SEDANG'], null);
        $this->assertSame('MEDIUM', $med['priority']);

        $low = $this->engine->formatFindingText(['id' => 3, 'prioritas' => 'RINGAN'], null);
        $this->assertSame('LOW', $low['priority']);
    }

    /**
     * 7. Test strict zero topology mutation invariant: Delta_nodes = 0, Delta_edges = 0.
     */
    public function testStrictZeroTopologyMutationInvariant(): void
    {
        $service = new SldFindingOverlayService(null, $this->engine);
        $res = $service->getFeederFindingOverlay(15, [421, 422, 423], $this->mockNodes, $this->mockSheets, $this->canvasMeta);

        $this->assertSame('success', $res['status']);
        $this->assertFalse($res['invariant']['topology_mutation']);
        $this->assertSame(0, $res['invariant']['delta_nodes']);
        $this->assertSame(0, $res['invariant']['delta_edges']);
    }

    /**
     * 8. Test annotation layout is strictly deterministic.
     */
    public function testAnnotationLayoutIsDeterministic(): void
    {
        $findings = [
            ['id' => 101, 'asset_id' => 421, 'prioritas' => 'HIGH', 'jenis_temuan' => 'ANOMALI PMS'],
            ['id' => 102, 'asset_id' => 422, 'prioritas' => 'MEDIUM', 'jenis_temuan' => 'REMBES OLI'],
            ['id' => 103, 'asset_id' => 423, 'prioritas' => 'LOW', 'jenis_temuan' => 'PONDASI AMBLAS'],
        ];

        $run1 = $this->engine->buildAnnotationModel(15, $findings, $this->mockNodes, $this->mockSheets, $this->canvasMeta);
        $run2 = $this->engine->buildAnnotationModel(15, $findings, $this->mockNodes, $this->mockSheets, $this->canvasMeta);

        $this->assertEquals($run1, $run2, 'Multiple layout executions must produce bitwise identical output');
    }

    /**
     * 9. Refinement 1: Test ROW finding does NOT bind to nearest asset.
     */
    public function testRowFindingDoesNotBindNearestAsset(): void
    {
        $rowFinding = [
            'id'           => 181,
            'asset_id'     => null,
            'latitude'     => -7.4211, // Extremely close to PA421 (-7.4210)
            'longitude'    => 112.7111,
            'jenis_temuan' => 'POHON DEKAT JTM',
        ];

        $nodeMap = [421 => $this->mockNodes[0]];
        $anchor = $this->engine->resolveAnchor($rowFinding, $nodeMap, $this->canvasMeta);

        $this->assertNull($anchor['target_node'], 'ROW finding must never bind nearest asset');
        $this->assertFalse($anchor['is_bound_asset']);
        $this->assertSame('LOCATION_LINKED', $anchor['model_type']);

        $textInfo = $this->engine->formatFindingText($rowFinding, $anchor['target_node']);
        $this->assertSame('LOCATION_ROW', $textInfo['callout_type']);
        $this->assertSame('NOT TOPOLOGY NODE', $textInfo['disclaimer']);
    }

    /**
     * 10. Refinement 4: Test sheet boundary containment.
     */
    public function testSheetBoundaryContainment(): void
    {
        $findings = [
            ['id' => 101, 'asset_id' => 421, 'prioritas' => 'HIGH'],
            ['id' => 102, 'asset_id' => 423, 'prioritas' => 'HIGH'],
        ];

        $annotated = $this->engine->buildAnnotationModel(15, $findings, $this->mockNodes, $this->mockSheets, $this->canvasMeta);

        foreach ($annotated as $item) {
            $ann = $item['annotation'];
            $sheetId = $ann['sheet_id'];
            $sheet = $this->mockSheets[$sheetId - 1];
            $vb = $sheet['view_box'];

            $this->assertGreaterThanOrEqual($vb['x'], $ann['box_x']);
            $this->assertLessThanOrEqual($vb['x'] + $vb['w'], $ann['box_x'] + $ann['box_width']);
            $this->assertGreaterThanOrEqual($vb['y'], $ann['box_y']);
            $this->assertLessThanOrEqual($vb['y'] + $vb['h'], $ann['box_y'] + $ann['box_height']);
        }
    }

    /**
     * 11. Test multiple findings on the same anchor asset stagger without colliding.
     */
    public function testMultipleFindingsSameAnchor(): void
    {
        $findings = [
            ['id' => 201, 'asset_id' => 421, 'prioritas' => 'HIGH', 'jenis_temuan' => 'TEMUAN 1'],
            ['id' => 202, 'asset_id' => 421, 'prioritas' => 'MEDIUM', 'jenis_temuan' => 'TEMUAN 2'],
            ['id' => 203, 'asset_id' => 421, 'prioritas' => 'LOW', 'jenis_temuan' => 'TEMUAN 3'],
        ];

        $annotated = $this->engine->buildAnnotationModel(15, $findings, $this->mockNodes, $this->mockSheets, $this->canvasMeta);
        $this->assertCount(3, $annotated);

        $box1 = $annotated[0]['annotation'];
        $box2 = $annotated[1]['annotation'];
        $box3 = $annotated[2]['annotation'];

        // None of the 3 boxes must collide
        $c12 = ($box1['box_x'] < $box2['box_x'] + $box2['box_width']) && ($box1['box_x'] + $box1['box_width'] > $box2['box_x']) &&
               ($box1['box_y'] < $box2['box_y'] + $box2['box_height']) && ($box1['box_y'] + $box1['box_height'] > $box2['box_y']);
        $c13 = ($box1['box_x'] < $box3['box_x'] + $box3['box_width']) && ($box1['box_x'] + $box1['box_width'] > $box3['box_x']) &&
               ($box1['box_y'] < $box3['box_y'] + $box3['box_height']) && ($box1['box_y'] + $box1['box_height'] > $box3['box_y']);
        $c23 = ($box2['box_x'] < $box3['box_x'] + $box3['box_width']) && ($box2['box_x'] + $box2['box_width'] > $box3['box_x']) &&
               ($box2['box_y'] < $box3['box_y'] + $box3['box_height']) && ($box2['box_y'] + $box2['box_height'] > $box3['box_y']);

        $this->assertFalse($c12, 'Box 1 and Box 2 must not collide');
        $this->assertFalse($c13, 'Box 1 and Box 3 must not collide');
        $this->assertFalse($c23, 'Box 2 and Box 3 must not collide');
    }

    /**
     * 12. Test print projection does not mutate topology.
     */
    public function testPrintProjectionDoesNotMutateTopology(): void
    {
        $initialNodes = count($this->mockNodes);
        $findings = [
            ['id' => 301, 'asset_id' => 421, 'prioritas' => 'HIGH'],
            ['id' => 302, 'asset_id' => null, 'latitude' => -7.425, 'longitude' => 112.72, 'prioritas' => 'HIGH'],
        ];

        $annotated = $this->engine->buildAnnotationModel(15, $findings, $this->mockNodes, $this->mockSheets, $this->canvasMeta);

        $finalNodes = count($this->mockNodes);
        $this->assertSame($initialNodes, $finalNodes, 'Annotating must never add or remove nodes');
        $this->assertCount(2, $annotated);
        $this->assertFalse($annotated[0]['is_topology_node']);
        $this->assertFalse($annotated[1]['is_topology_node']);
    }
}
