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
        $this->assertSame('NGR_GROUND_FAULT_INDETERMINATE', $res['dasar_perhitungan']);
        $this->assertNotEmpty($res['missing_parameters']);
        $this->assertStringContainsString('NGR', $res['formula_derivation']);
    }

    public function testSymmetrical3PhaseWithoutVoltageReturnsDataTidakCukupWithIdealComplexSimulation(): void
    {
        // 3-PH with 1500A on AAAC 150 (r1 = 0.233, x1 = 0.335 Ohm/km)
        // With only currents and no voltage phasors, physical engineering requires DATA_TIDAK_CUKUP
        // and low confidence with an explicit theoretical quadratic derivation.
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

        $this->assertSame('DATA_TIDAK_CUKUP', $res['calculation_status']);
        $this->assertSame('SIMULASI_IDEAL_BOLTED_3PH', $res['dasar_perhitungan']);
        $this->assertLessThanOrEqual(40.0, $res['confidence_percent']);
        $this->assertNotEmpty($res['missing_parameters']);
        $this->assertGreaterThan(5000.0, $res['calculated_distance_m']);
        $this->assertStringContainsString('Persamaan Kuadrat Impedansi Kompleks', $res['formula_derivation']);
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
            'relay_distance_m' => 1850.0, // Measured by field numeric distance relay
            'conductor_type'   => 'AAAC 150 mm²',
        ];

        $res = $this->service->locateFault($telemetry);

        $this->assertSame('CALCULATED_RELAY_AIDED', $res['calculation_status']);
        $this->assertSame('RELAY_DISTANCE_MEASUREMENT', $res['dasar_perhitungan']);
        $this->assertEquals(1850.0, $res['calculated_distance_m']);
        $this->assertGreaterThanOrEqual(90.0, $res['confidence_percent']);
    }

    public function testFeederWithoutPolesOrTranslinesReturnsDataTidakCukupZeroDistance(): void
    {
        // Feeder without physical spans/poles (such as Citra Fajar or unmapped feeder)
        // Ensure distance is strictly 0.0 m, calculation_status is DATA_TIDAK_CUKUP,
        // and dasar_perhitungan is TOPOLOGI_BELUM_TERSEDIA (no phantom 18,713.6 m).
        $telemetry = [
            'feeder_id'        => 999999, // unmapped feeder
            'source_type'      => 'GI',
            'current_r'        => 1500.0,
            'current_s'        => 1500.0,
            'current_t'        => 1500.0,
            'current_n'        => 5.0,
            'relay_distance_m' => null,
            'conductor_type'   => 'AAAC 150 mm²',
        ];

        $res = $this->service->locateFault($telemetry);

        $this->assertSame('DATA_TIDAK_CUKUP', $res['calculation_status']);
        $this->assertSame('TOPOLOGI_BELUM_TERSEDIA', $res['dasar_perhitungan']);
        $this->assertEquals(0.0, $res['calculated_distance_m']);
        $this->assertEquals(0.0, $res['confidence_percent']);
        $this->assertEmpty($res['pole_sequence']);
        $this->assertEmpty($res['candidate_assets']);
        $this->assertNull($res['fault_point']);
    }

    public function testJenggoloFeederNeverReturnsAdiprimaAssetsAndResolvesJtmTiangPoles(): void
    {
        $db = \Config\Database::connect('default');

        // 1. Direct check on existing DB state for JENGGOLO (id=30 / PYL-030)
        $resById = $this->service->locateFault([
            'ulp_id'         => 1,
            'feeder_id'      => 30,
            'source_type'    => 'GI',
            'current_r'      => 1450.0,
            'current_s'      => 1420.0,
            'current_t'      => 1440.0,
            'current_n'      => 15.0,
            'conductor_type' => 'AAAC 150 mm²',
        ]);

        $this->assertSame(30, $resById['feeder_id']);
        $this->assertSame('PYL-030', $resById['feeder_code']);
        $this->assertSame('JENGGOLO', $resById['feeder_name']);

        foreach ($resById['candidate_assets'] as $cand) {
            $this->assertSame(30, (int)$cand['penyulang_id']);
            $this->assertStringNotContainsStringIgnoringCase('ADIPRIMA', $cand['kode_asset']);
            $this->assertStringNotContainsStringIgnoringCase('ADIPRIMA', $cand['asset_name']);
        }
        foreach ($resById['pole_sequence'] as $seg) {
            $this->assertStringNotContainsStringIgnoringCase('ADIPRIMA', $seg['from_pole_kode']);
            $this->assertStringNotContainsStringIgnoringCase('ADIPRIMA', $seg['to_pole_kode']);
        }

        // 2. Also verify when JENGGOLO has jtm_tiang poles and translines (production topology structure)
        $db->transBegin();
        try {
            $hasJenggoloTl = $db->table('gis_translines')
                ->where('penyulang_id', 30)
                ->where('is_active', 1)
                ->where('deleted_at IS NULL')
                ->countAllResults() > 0;

            if (!$hasJenggoloTl) {
                $db->table('assets')->insert([
                    'kode_asset'   => 'AST-JENGGOLO-TEST-001',
                    'nama_asset'   => 'Tiang JTM Jenggolo 001',
                    'jenis_asset'  => 'jtm_tiang',
                    'ulp_id'       => 1,
                    'penyulang_id' => 30,
                    'sequence_no'  => 1,
                    'latitude'     => -7.441406,
                    'longitude'    => 112.720442,
                ]);
                $pole1Id = (int)$db->insertID();

                $db->table('assets')->insert([
                    'kode_asset'   => 'AST-JENGGOLO-TEST-002',
                    'nama_asset'   => 'Tiang JTM Jenggolo 002',
                    'jenis_asset'  => 'jtm_tiang',
                    'ulp_id'       => 1,
                    'penyulang_id' => 30,
                    'sequence_no'  => 2,
                    'latitude'     => -7.441086,
                    'longitude'    => 112.720348,
                ]);
                $pole2Id = (int)$db->insertID();

                // Insert a valid Jenggolo transline AND a corrupted cross-feeder transline pointing to ADIPRIMA1_123 (id=42813)
                $db->table('gis_translines')->insert([
                    'transline_code'   => 'TL-PYL-030-TEST-1',
                    'penyulang_id'     => 30,
                    'source_asset_id'  => $pole1Id,
                    'target_asset_id'  => $pole2Id,
                    'conductor_type'   => 'AAAC',
                    'conductor_size'   => '150 mm²',
                    'distance_meters'  => 45.0,
                    'is_active'        => 1,
                ]);
                $db->table('gis_translines')->insert([
                    'transline_code'   => 'TL-PYL-030-CROSS-CORRUPT',
                    'penyulang_id'     => 30,
                    'source_asset_id'  => $pole2Id,
                    'target_asset_id'  => 42813, // AST-ADIPRIMA1-ADIPRIMA1_123 on feeder 46
                    'conductor_type'   => 'AAAC',
                    'conductor_size'   => '150 mm²',
                    'distance_meters'  => 50.0,
                    'is_active'        => 1,
                ]);
            }

            $resJenggolo = $this->service->locateFault([
                'ulp_id'         => 1,
                'feeder_id'      => 30,
                'source_type'    => 'GI',
                'current_r'      => 1450.0,
                'current_s'      => 1420.0,
                'current_t'      => 1440.0,
                'current_n'      => 15.0,
                'conductor_type' => 'AAAC 150 mm²',
            ]);

            $this->assertSame(30, $resJenggolo['feeder_id']);
            $this->assertNotEmpty($resJenggolo['anchor_pole'], 'Anchor pole for Jenggolo with jtm_tiang must be resolved');
            $this->assertNotEmpty($resJenggolo['candidate_assets'], 'Jenggolo with jtm_tiang must resolve candidate poles');
            $this->assertNotNull($resJenggolo['fault_point'], 'Jenggolo with jtm_tiang must resolve fault_point coordinates');

            foreach ($resJenggolo['candidate_assets'] as $cand) {
                $this->assertSame(30, (int)$cand['penyulang_id']);
                $this->assertStringNotContainsStringIgnoringCase('ADIPRIMA', $cand['kode_asset']);
                $this->assertNotSame(42813, (int)$cand['asset_id']);
            }
            foreach ($resJenggolo['pole_sequence'] as $seg) {
                $this->assertStringNotContainsStringIgnoringCase('ADIPRIMA', $seg['from_pole_kode']);
                $this->assertStringNotContainsStringIgnoringCase('ADIPRIMA', $seg['to_pole_kode']);
            }

            $ctxJenggolo = $this->service->getFeederNetworkContext(30);
            $this->assertSame('success', $ctxJenggolo['status']);
            $this->assertGreaterThan(0, $ctxJenggolo['data']['topology']['valid_jtm_poles_count']);
        } finally {
            $db->transRollback();
        }
    }

    public function testOtherFeedersMaintainStrictBoundaryRegression(): void
    {
        // Test SIWALAN PANJI (id=1) and GEMURUNG (id=4) which have active translines
        foreach ([1, 4] as $feederId) {
            $res = $this->service->locateFault([
                'feeder_id'      => $feederId,
                'source_type'    => 'GI',
                'current_r'      => 1450.0,
                'current_s'      => 1420.0,
                'current_t'      => 1440.0,
                'current_n'      => 15.0,
                'conductor_type' => 'AAAC 150 mm²',
            ]);

            $this->assertSame($feederId, $res['feeder_id']);
            $this->assertNotEmpty($res['candidate_assets']);
            $this->assertNotNull($res['fault_point']);

            foreach ($res['candidate_assets'] as $cand) {
                $this->assertSame($feederId, (int)$cand['penyulang_id']);
                $this->assertStringNotContainsStringIgnoringCase('ADIPRIMA', $cand['kode_asset']);
            }
        }
    }
}
