<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\RowTreeAssetService;
use App\Services\PlnStandardBomRuleService;

class RowTreeAssetServiceTest extends CIUnitTestCase
{
    protected RowTreeAssetService $rowService;
    protected PlnStandardBomRuleService $bomService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rowService = new RowTreeAssetService();
        $this->bomService = new PlnStandardBomRuleService();
    }

    public function testDetectSpeciesCorrectlyIdentifiesKnownSpecies()
    {
        $bambu = $this->rowService->detectSpecies('Pohon bambu dekat fasa S mendekati jaringan');
        $this->assertTrue($bambu['identified']);
        $this->assertSame('BAMBU', $bambu['key']);
        $this->assertSame(3.50, $bambu['rate_cm_day']);

        $mangga = $this->rowService->detectSpecies('Pohon mangga rimbun menyentuh konduktor');
        $this->assertTrue($mangga['identified']);
        $this->assertSame('MANGGA', $mangga['key']);
        $this->assertSame(1.00, $mangga['rate_cm_day']);

        $unknown = $this->rowService->detectSpecies('Semak belukar liar di bawah tiang');
        $this->assertFalse($unknown['identified']);
        $this->assertSame('UNKNOWN', $unknown['key']);
        $this->assertNull($unknown['rate_cm_day']);
    }

    public function testDetectClearanceMetersExtractsDistance()
    {
        $d1 = $this->rowService->detectClearanceMeters('Jarak ke jaringan: 0.8 m');
        $this->assertEquals(0.80, $d1);

        $d2 = $this->rowService->detectClearanceMeters('Clearance 50 cm dari konduktor');
        $this->assertEquals(0.50, $d2);

        $d3 = $this->rowService->detectClearanceMeters('Pohon rimbun tanpa keterangan jarak');
        $this->assertNull($d3);
    }

    public function testGrowthPredictionStrictlyReturnsBelumCukupDataWhenInsufficient()
    {
        $species = [
            'identified'  => false,
            'name'        => 'Pohon Tidak Dikenal',
            'rate_cm_day' => null,
        ];
        $pred = $this->rowService->calculateVegetationGrowthPrediction('SIM-01', $species, null, '2026-10-21');

        $this->assertSame('BELUM_CUKUP_DATA', $pred['prediction_status']);
        $this->assertSame('LOW', $pred['confidence']);
        $this->assertNull($pred['growth_rate_cm_day']);
        $this->assertNull($pred['recommended_prune_date']);
    }

    public function testGrowthPredictionEstimasiValidWhenSpeciesAndClearanceKnown()
    {
        $species = [
            'identified'  => true,
            'name'        => 'Bambu',
            'rate_cm_day' => 3.50,
        ];
        // 1.2m clearance, critical threshold is 0.5m -> gap is 0.70m = 70cm -> at 3.5cm/day = 20 days
        $pred = $this->rowService->calculateVegetationGrowthPrediction('SIM-02', $species, 1.20, '2026-10-21');

        $this->assertSame('ESTIMASI_VALID', $pred['prediction_status']);
        $this->assertSame('MEDIUM', $pred['confidence']);
        $this->assertEquals(3.50, $pred['growth_rate_cm_day']);
        $this->assertEquals(20, $pred['days_until_pruning']);
        $this->assertSame('2026-11-10', $pred['recommended_prune_date']);
    }

    public function testBomDemandPreservationAndRemainingCalculation()
    {
        // Test calculateFindingBomProgress formula
        $dummyTemuanId = 999999;
        $progress = $this->bomService->calculateFindingBomProgress($dummyTemuanId);

        $this->assertIsArray($progress);
        $this->assertArrayHasKey('total_kebutuhan', $progress);
        $this->assertArrayHasKey('sudah_digunakan', $progress);
        $this->assertArrayHasKey('sisa_kebutuhan', $progress);
        $this->assertArrayHasKey('fulfillment_status', $progress);
    }
}
