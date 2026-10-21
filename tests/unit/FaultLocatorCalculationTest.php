<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\FaultLocatorWorkspaceService;

class FaultLocatorCalculationTest extends CIUnitTestCase
{
    protected FaultLocatorWorkspaceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FaultLocatorWorkspaceService();
    }

    public function testFaultClassificationSymmetrical3Phase(): void
    {
        $class = $this->service->classifyFaultCurrents(1500.0, 1490.0, 1510.0, 10.0);
        $this->assertSame('3_PHASE', $class['fault_type']);
        $this->assertFalse($class['is_ground_fault']);
        $this->assertEqualsWithDelta(1500.0, $class['active_current_a'], 5.0);
    }

    public function testFaultClassificationPhaseToPhase(): void
    {
        $class = $this->service->classifyFaultCurrents(1200.0, 1195.0, 5.0, 8.0);
        $this->assertSame('2_PHASE', $class['fault_type']);
        $this->assertFalse($class['is_ground_fault']);
        $this->assertEqualsWithDelta(1197.5, $class['active_current_a'], 2.0);
    }

    public function testFaultClassificationSingleLineToGround(): void
    {
        $class = $this->service->classifyFaultCurrents(280.0, 5.0, 4.0, 278.0);
        $this->assertSame('1_PHASE_GROUND', $class['fault_type']);
        $this->assertTrue($class['is_ground_fault']);
        $this->assertEqualsWithDelta(280.0, $class['active_current_a'], 5.0);
    }

    public function testGroundFaultWithoutReactanceReturnsDataTidakCukup(): void
    {
        // Ground fault 1-LG with NGR 40 Ohm
        $telemetry = [
            'feeder_id'        => 1,
            'source_type'      => 'GI',
            'current_r'        => 280.0,
            'current_s'        => 0.0,
            'current_t'        => 0.0,
            'current_n'        => 275.0,
            'relay_distance_m' => null,
            'conductor_type'   => 'AAAC 150 mm²',
        ];

        $res = $this->service->locateFault($telemetry);

        $this->assertSame('DATA_TIDAK_CUKUP', $res['calculation_status']);
        $this->assertSame('DATA_TIDAK_CUKUP', $res['outcome_status']);
        $this->assertSame('NGR_40_OHM_PHYSICAL_LIMITATION', $res['dasar_perhitungan']);
        $this->assertNotEmpty($res['missing_parameters']);
        $this->assertStringContainsString('NGR 40 Ohm', $res['explanation']);
    }

    public function testSymmetrical3PhaseCalculatesImpedanceDistance(): void
    {
        // 3-PH with 1500A on AAAC 150 (Z1 = 0.408 Ohm/km)
        // V_LN = 11,547.0 V
        // Z_loop = 11547 / 1500 ≈ 7.698 Ohm
        // Z_line = 7.698 - 0.5 = 7.198 Ohm
        // distKm = 7.198 / 0.408 ≈ 17.64 km
        $telemetry = [
            'feeder_id'        => 1,
            'source_type'      => 'GI',
            'current_r'        => 1500.0,
            'current_s'        => 1500.0,
            'current_t'        => 1500.0,
            'current_n'        => 5.0,
            'relay_distance_m' => null,
            'conductor_type'   => 'AAAC 150 mm²',
        ];

        $res = $this->service->locateFault($telemetry);

        $this->assertSame('CALCULATED_ELECTRICAL_IMPEDANCE', $res['calculation_status']);
        $this->assertSame('IMPEDANCE_3PH_SYMMETRICAL', $res['dasar_perhitungan']);
        $this->assertGreaterThan(5000.0, $res['calculated_distance_m']);
        $this->assertStringContainsString('11,547', $res['formula_derivation']);
    }

    public function testRelayAidedDirectDistanceTakesPrecedence(): void
    {
        $telemetry = [
            'feeder_id'        => 1,
            'source_type'      => 'GI',
            'current_r'        => 280.0,
            'current_s'        => 0.0,
            'current_t'        => 0.0,
            'current_n'        => 275.0,
            'relay_distance_m' => 1850.0, // Explicitly measured by numeric relay
            'conductor_type'   => 'AAAC 150 mm²',
        ];

        $res = $this->service->locateFault($telemetry);

        $this->assertSame('CALCULATED_RELAY_AIDED', $res['calculation_status']);
        $this->assertSame('RELAY_DISTANCE_MEASUREMENT', $res['dasar_perhitungan']);
        $this->assertEquals(1850.0, $res['calculated_distance_m']);
    }
}
