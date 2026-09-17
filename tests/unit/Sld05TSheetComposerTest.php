<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\SldSheetComposerService;
use App\Services\SldLayoutCoordinateEngineService;

/**
 * Class Sld05TSheetComposerTest
 *
 * Verifies Lock 4 of SLD-05T:
 * 1. Sheet Composer 100% Coverage Invariant: sum(unique asset IDs across sheets) == 205.
 * 2. Zero lost assets and zero orphaned nodes.
 * 3. Topological continuity maintained across partition boundaries.
 * 4. Match lines point to exact boundary asset nodes.
 * 5. Optimal per-sheet viewBox calculations.
 * 6. CAD Title Block metadata integrity.
 */
class Sld05TSheetComposerTest extends CIUnitTestCase
{
    protected SldSheetComposerService $composerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->composerService = new SldSheetComposerService();
    }

    /**
     * Test 100% Asset Coverage Invariant across all partitioned sheets.
     */
    public function testFeeder15SheetComposerFullCoverageInvariant(): void
    {
        $res = $this->composerService->composeFeederSheets(15);

        $this->assertEquals('success', $res['status']);
        $this->assertEquals(205, $res['total_assets']);
        $this->assertEquals(205, $res['covered_assets']);
        $this->assertTrue($res['coverage_complete']);
        $this->assertGreaterThanOrEqual(3, $res['total_sheets']);

        // Collect all asset IDs from all sheets
        $allIds = [];
        foreach ($res['sheets'] as $sheet) {
            $this->assertNotEmpty($sheet['nodes']);
            $this->assertNotEmpty($sheet['node_ids']);
            foreach ($sheet['node_ids'] as $id) {
                $allIds[] = (int)$id;
            }
        }

        // Verify zero duplicates across sheets
        $this->assertCount(205, $allIds);
        $uniqueIds = array_unique($allIds);
        $this->assertCount(205, $uniqueIds);
    }

    /**
     * Test Match Lines integrity between adjacent sheets.
     */
    public function testMatchLinesContinuity(): void
    {
        $res = $this->composerService->composeFeederSheets(15);
        $sheets = $res['sheets'];
        $total = count($sheets);

        for ($i = 0; $i < $total; $i++) {
            $sheet = $sheets[$i];
            $sheetNum = $i + 1;

            if ($sheetNum === 1) {
                // First sheet has no backward match line, but has forward
                $this->assertArrayNotHasKey('backward', $sheet['match_lines']);
                $this->assertArrayHasKey('forward', $sheet['match_lines']);
                $this->assertEquals(2, $sheet['match_lines']['forward']['adjacent_sheet']);
                $this->assertNotEmpty($sheet['match_lines']['forward']['boundary_node_id']);
                $this->assertStringContainsString('LEMBAR 02', $sheet['match_lines']['forward']['label']);
            } elseif ($sheetNum === $total) {
                // Last sheet has backward match line, but no forward
                $this->assertArrayHasKey('backward', $sheet['match_lines']);
                $this->assertArrayNotHasKey('forward', $sheet['match_lines']);
                $this->assertEquals($total - 1, $sheet['match_lines']['backward']['adjacent_sheet']);
                $this->assertNotEmpty($sheet['match_lines']['backward']['boundary_node_id']);
                $this->assertStringContainsString(sprintf("LEMBAR %02d", $total - 1), $sheet['match_lines']['backward']['label']);
            } else {
                // Intermediate sheets have both backward and forward
                $this->assertArrayHasKey('backward', $sheet['match_lines']);
                $this->assertArrayHasKey('forward', $sheet['match_lines']);
                $this->assertEquals($sheetNum - 1, $sheet['match_lines']['backward']['adjacent_sheet']);
                $this->assertEquals($sheetNum + 1, $sheet['match_lines']['forward']['adjacent_sheet']);
            }
        }
    }

    /**
     * Test CAD Title Block metadata and optimal ViewBox parameters.
     */
    public function testCadTitleBlockAndViewBox(): void
    {
        $res = $this->composerService->composeFeederSheets(15);

        foreach ($res['sheets'] as $sheet) {
            $tb = $sheet['title_block'];
            $this->assertEquals('PT PLN (PERSERO)', $tb['company']);
            $this->assertEquals('20 kV', $tb['voltage_level']);
            $this->assertEquals('SINGLE LINE DIAGRAM 20 kV', $tb['drawing_title']);
            $this->assertNotEmpty($tb['substation']);
            $this->assertNotEmpty($tb['sheet_label']);
            $this->assertNotEmpty($tb['statistics']);

            $vb = $sheet['view_box'];
            $this->assertGreaterThan(0, $vb['w']);
            $this->assertGreaterThan(0, $vb['h']);
            // Aspect ratio must be approximately landscape (~1.414)
            $ratio = $vb['w'] / $vb['h'];
            $this->assertEqualsWithDelta(1.414, $ratio, 0.05);
        }
    }
}
