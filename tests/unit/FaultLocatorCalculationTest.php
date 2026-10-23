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
        $this->assertSame('OCR_INSTAN', $class['fault_type']);
        $this->assertSame('3_PHASE', $class['technical_fault_type']);
        $this->assertFalse($class['is_ground_fault']);
        $this->assertEqualsWithDelta(1500.0, $class['active_current_a'], 5.0);
    }

    public function testFaultClassificationPhaseToPhase(): void
    {
        $class = $this->service->classifyFaultCurrents(1200.0, 1195.0, 5.0, 8.0);
        $this->assertSame('OCR_INSTAN', $class['fault_type']);
        $this->assertSame('2_PHASE', $class['technical_fault_type']);
        $this->assertFalse($class['is_ground_fault']);
        $this->assertEqualsWithDelta(1197.5, $class['active_current_a'], 2.0);
    }

    public function testFaultClassificationSingleLineToGround(): void
    {
        $class = $this->service->classifyFaultCurrents(280.0, 5.0, 4.0, 278.0);
        $this->assertSame('DGR', $class['fault_type']);
        $this->assertSame('1_PHASE_GROUND', $class['technical_fault_type']);
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

    public function testProtectionDeviceStartsFromMountingPoleAndTraversesDownstreamOnly(): void
    {
        $db = \Config\Database::connect('default');
        $db->transBegin();

        try {
            // Build a 4-pole linear feeder topology on feeder 30 (or synthetic poles if needed):
            // GI_POLE_1 (seq 1) --(400m)--> REC_POLE_2 (seq 2, Recloser mounted here) --(450m)--> DOWN_POLE_3 (seq 3) --(450m)--> END_POLE_4 (seq 4)
            $db->table('gis_translines')->where('penyulang_id', 30)->delete();
            $db->table('assets')->where('penyulang_id', 30)->delete();

            $db->table('assets')->insert([
                'kode_asset'   => 'AST-TULANGAN-GI-001',
                'nama_asset'   => 'Tiang Pangkal GI 001',
                'jenis_asset'  => 'JTM_TIANG',
                'ulp_id'       => 1,
                'penyulang_id' => 30,
                'sequence_no'  => 1,
                'latitude'     => -7.440000,
                'longitude'    => 112.720000,
            ]);
            $giPole1 = (int)$db->insertID();

            $db->table('assets')->insert([
                'kode_asset'   => 'AST-TULANGAN-REC-002',
                'nama_asset'   => 'Tiang Tumpu Recloser Megah Asri 002',
                'jenis_asset'  => 'JTM_TIANG',
                'ulp_id'       => 1,
                'penyulang_id' => 30,
                'sequence_no'  => 2,
                'latitude'     => -7.443600,
                'longitude'    => 112.720000,
            ]);
            $recPole2 = (int)$db->insertID();

            $db->table('assets')->insert([
                'kode_asset'   => 'AST-TULANGAN-DOWN-003',
                'nama_asset'   => 'Tiang Hilir 003',
                'jenis_asset'  => 'JTM_TIANG',
                'ulp_id'       => 1,
                'penyulang_id' => 30,
                'sequence_no'  => 3,
                'latitude'     => -7.447600,
                'longitude'    => 112.720000,
            ]);
            $downPole3 = (int)$db->insertID();

            $db->table('assets')->insert([
                'kode_asset'   => 'AST-TULANGAN-END-004',
                'nama_asset'   => 'Tiang Ujung 004',
                'jenis_asset'  => 'JTM_TIANG',
                'ulp_id'       => 1,
                'penyulang_id' => 30,
                'sequence_no'  => 4,
                'latitude'     => -7.451600,
                'longitude'    => 112.720000,
            ]);
            $endPole4 = (int)$db->insertID();

            // Mount Recloser on $recPole2 via parent_asset_id
            $db->table('assets')->insert([
                'kode_asset'      => 'AST-PROT-REC-MEGAHASRI',
                'nama_asset'      => 'RECLOSER MEGAH ASRI',
                'jenis_asset'     => 'RECLOSER',
                'ulp_id'          => 1,
                'penyulang_id'    => 30,
                'parent_asset_id' => $recPole2,
                'latitude'        => -7.443610,
                'longitude'       => 112.720010,
            ]);
            $recloserId = (int)$db->insertID();

            // Translines: GI_POLE_1 -> REC_POLE_2 (400m), REC_POLE_2 -> DOWN_POLE_3 (450m), DOWN_POLE_3 -> END_POLE_4 (450m)
            $db->table('gis_translines')->insert([
                'transline_code'  => 'TL-30-1-2',
                'penyulang_id'    => 30,
                'source_asset_id' => $giPole1,
                'target_asset_id' => $recPole2,
                'conductor_type'  => 'AAAC',
                'conductor_size'  => '150 mm²',
                'distance_meters' => 400.0,
                'is_active'       => 1,
            ]);
            $db->table('gis_translines')->insert([
                'transline_code'  => 'TL-30-2-3',
                'penyulang_id'    => 30,
                'source_asset_id' => $recPole2,
                'target_asset_id' => $downPole3,
                'conductor_type'  => 'AAAC',
                'conductor_size'  => '150 mm²',
                'distance_meters' => 450.0,
                'is_active'       => 1,
            ]);
            $db->table('gis_translines')->insert([
                'transline_code'  => 'TL-30-3-4',
                'penyulang_id'    => 30,
                'source_asset_id' => $downPole3,
                'target_asset_id' => $endPole4,
                'conductor_type'  => 'AAAC',
                'conductor_size'  => '150 mm²',
                'distance_meters' => 450.0,
                'is_active'       => 1,
            ]);

            // 1. Locate fault from PROTECTION (Recloser Megah Asri) at relay_distance_m = 600m
            $resProt = $this->service->locateFault([
                'ulp_id'               => 1,
                'feeder_id'            => 30,
                'source_type'          => 'PROTECTION',
                'protection_device_id' => $recloserId,
                'relay_distance_m'     => 600.0,
                'current_r'            => 950.0,
                'current_s'            => 940.0,
                'current_t'            => 945.0,
                'current_n'            => 10.0,
                'conductor_type'       => 'AAAC 150 mm²',
            ]);

            $this->assertSame('PROTECTION', $resProt['source_type']);
            $this->assertSame($recPole2, (int)$resProt['anchor_pole']['id'], 'Anchor pole must be the JTM pole where Recloser is mounted, NOT GI pole');
            $this->assertStringContainsString('RECLOSER MEGAH ASRI', $resProt['anchor_description']);
            $this->assertStringNotContainsString('Gardu Induk (Pangkal Penyulang)', $resProt['anchor_description']);

            // Verify upstream GI pole ($giPole1) is completely excluded from downstream pole_sequence
            $this->assertCount(2, $resProt['pole_sequence'], 'Downstream sequence from Recloser must have exactly 2 spans (REC->DOWN3, DOWN3->END4)');
            $this->assertSame($recPole2, (int)$resProt['pole_sequence'][0]['from_pole_id']);
            $this->assertSame($downPole3, (int)$resProt['pole_sequence'][0]['to_pole_id']);
            $this->assertSame(450.0, (float)$resProt['pole_sequence'][0]['cumulative_meters']);

            $this->assertSame($downPole3, (int)$resProt['pole_sequence'][1]['from_pole_id']);
            $this->assertSame($endPole4, (int)$resProt['pole_sequence'][1]['to_pole_id']);
            $this->assertSame(900.0, (float)$resProt['pole_sequence'][1]['cumulative_meters']);
            $this->assertTrue($resProt['pole_sequence'][1]['is_fault_zone']);

            foreach ($resProt['pole_sequence'] as $seg) {
                $this->assertNotSame($giPole1, (int)$seg['from_pole_id'], 'Upstream GI pole must never appear in downstream traversal');
                $this->assertNotSame($giPole1, (int)$seg['to_pole_id'], 'Upstream GI pole must never appear in downstream traversal');
                $this->assertNotEmpty($seg['path_coords']);
                $this->assertTrue($seg['is_on_fault_path']);
            }

            // Candidate poles at 600m from Recloser must be DOWN_POLE_3 and END_POLE_4
            $candCodes = array_column($resProt['candidate_assets'], 'kode_asset');
            $this->assertContains('AST-TULANGAN-DOWN-003', $candCodes);
            $this->assertContains('AST-TULANGAN-END-004', $candCodes);
            $this->assertNotContains('AST-TULANGAN-GI-001', $candCodes);

            // 2. Locate fault from GI on the same feeder at relay_distance_m = 600m
            $resGi = $this->service->locateFault([
                'ulp_id'           => 1,
                'feeder_id'        => 30,
                'source_type'      => 'GI',
                'relay_distance_m' => 600.0,
                'current_r'        => 950.0,
                'current_s'        => 940.0,
                'current_t'        => 945.0,
                'current_n'        => 10.0,
                'conductor_type'   => 'AAAC 150 mm²',
            ]);

            $this->assertSame('GI', $resGi['source_type']);
            $this->assertSame($giPole1, (int)$resGi['anchor_pole']['id'], 'GI source must start at GI root pole');
            $this->assertCount(3, $resGi['pole_sequence']);
            // At 600m from GI, fault falls on span 2 (REC_POLE_2 -> DOWN_POLE_3: 400m..850m), different from Recloser's 600m!
            $this->assertSame($recPole2, (int)$resGi['fault_zone_segment']['from_pole_id']);
            $this->assertSame($downPole3, (int)$resGi['fault_zone_segment']['to_pole_id']);
        } finally {
            $db->transRollback();
        }
    }

    public function testDgrFaultIndicationFromPhaseCurrentsAndCollapsedPhaseVoltage(): void
    {
        // Real case from field: R = 244 A / 21.26 kV, S = 264 A / 0.00 kV, T = 238 A / 21.00 kV, N = 67 A
        $class = $this->service->classifyFaultCurrents(244.0, 264.0, 238.0, 67.0, 21.26, 0.00, 21.00);

        $this->assertSame('DGR', $class['fault_type']);
        $this->assertSame('1_PHASE_GROUND', $class['technical_fault_type']);
        $this->assertSame('Indikasi Gangguan Tanah Fasa S-N / DGR', $class['fault_type_label']);
        $this->assertSame('INDIKASI_DGR_S_N', $class['classification_rule']);
        $this->assertSame(['S', 'N'], $class['fault_phases']);
        $this->assertTrue($class['is_ground_fault']);
        $this->assertTrue($class['has_voltage_telemetry']);
        $this->assertSame('COMBINED_CURRENT_AND_PHASE_VOLTAGE', $class['indication_basis']);
        $this->assertGreaterThanOrEqual(90.0, $class['indication_confidence_percent']);
        $this->assertNotEmpty($class['supporting_evidence']);

        $evidenceJoined = implode(' | ', $class['supporting_evidence']);
        $this->assertStringContainsString('0.00 kV', $evidenceJoined);
        $this->assertStringContainsString('67.0 A', $evidenceJoined);

        // Compare with missing voltage telemetry on the same currents (244/264/238 A, N=67 A):
        // Without phase voltages, phase currents are balanced (load+fault), so confidence must be lower and not claim certainty
        $classNoVoltage = $this->service->classifyFaultCurrents(244.0, 264.0, 238.0, 67.0, null, null, null);
        $this->assertFalse($classNoVoltage['has_voltage_telemetry']);
        $this->assertSame('CURRENT_ONLY_SCALAR', $classNoVoltage['indication_basis']);
        $this->assertLessThan($class['indication_confidence_percent'], $classNoVoltage['indication_confidence_percent']);
    }

    public function testVoltageValidationDistinguishesZeroFromNullAndRejectsOutOfRange(): void
    {
        // 1. 0.00 kV must be preserved as float 0.0, while empty string '' is preserved as null
        $res = $this->service->locateFault([
            'feeder_id'    => 1,
            'source_type'  => 'GI',
            'current_r'    => '244',
            'current_s'    => '264',
            'current_t'    => '238',
            'current_n'    => '67',
            'voltage_r_kv' => '21.26',
            'voltage_s_kv' => '0.00',
            'voltage_t_kv' => '',
        ]);

        $this->assertFalse($res['validation_error'] ?? false);
        $this->assertSame(21.26, $res['telemetry_input']['voltage_r_kv']);
        $this->assertSame(0.0, $res['telemetry_input']['voltage_s_kv']);
        $this->assertNull($res['telemetry_input']['voltage_t_kv']);
        $this->assertSame('Indikasi Gangguan Tanah Fasa S-N / DGR', $res['fault_classification']['fault_type_label']);

        // 2. Out-of-range voltage (> 35 kV or < 0 kV) must return validation_error = true
        $invalidHigh = $this->service->locateFault([
            'feeder_id'    => 1,
            'source_type'  => 'GI',
            'current_r'    => 244,
            'current_s'    => 264,
            'current_t'    => 238,
            'current_n'    => 67,
            'voltage_r_kv' => 45.0,
        ]);
        $this->assertTrue($invalidHigh['validation_error'] ?? false);
        $this->assertSame('VALIDATION_ERROR', $invalidHigh['calculation_status']);

        $invalidNegative = $this->service->locateFault([
            'feeder_id'    => 1,
            'source_type'  => 'GI',
            'current_r'    => 244,
            'current_s'    => 264,
            'current_t'    => 238,
            'current_n'    => 67,
            'voltage_s_kv' => -0.5,
        ]);
        $this->assertTrue($invalidNegative['validation_error'] ?? false);
    }

    public function testAutomaticConductorFromTraversedPathAndDistanceSeparationAndPersistence(): void
    {
        $db = \Config\Database::connect('default');
        $db->transBegin();

        try {
            $db->table('gis_translines')->where('penyulang_id', 30)->delete();
            $db->table('assets')->where('penyulang_id', 30)->delete();

            $db->table('assets')->insert([
                'kode_asset'   => 'AST-COND-GI-001',
                'nama_asset'   => 'Tiang Pangkal GI 001',
                'jenis_asset'  => 'JTM_TIANG',
                'ulp_id'       => 1,
                'penyulang_id' => 30,
                'sequence_no'  => 1,
                'latitude'     => -7.440000,
                'longitude'    => 112.720000,
            ]);
            $giPole1 = (int)$db->insertID();

            $db->table('assets')->insert([
                'kode_asset'   => 'AST-COND-REC-002',
                'nama_asset'   => 'Tiang Tumpu Recloser Megah Asri 002',
                'jenis_asset'  => 'JTM_TIANG',
                'ulp_id'       => 1,
                'penyulang_id' => 30,
                'sequence_no'  => 2,
                'latitude'     => -7.443600,
                'longitude'    => 112.720000,
            ]);
            $recPole2 = (int)$db->insertID();

            $db->table('assets')->insert([
                'kode_asset'   => 'AST-COND-DOWN-003',
                'nama_asset'   => 'Tiang Hilir 003',
                'jenis_asset'  => 'JTM_TIANG',
                'ulp_id'       => 1,
                'penyulang_id' => 30,
                'sequence_no'  => 3,
                'latitude'     => -7.447600,
                'longitude'    => 112.720000,
            ]);
            $downPole3 = (int)$db->insertID();

            $db->table('assets')->insert([
                'kode_asset'   => 'AST-COND-END-004',
                'nama_asset'   => 'Tiang Ujung 004',
                'jenis_asset'  => 'JTM_TIANG',
                'ulp_id'       => 1,
                'penyulang_id' => 30,
                'sequence_no'  => 4,
                'latitude'     => -7.451600,
                'longitude'    => 112.720000,
            ]);
            $endPole4 = (int)$db->insertID();

            $db->table('assets')->insert([
                'kode_asset'      => 'AST-PROT-REC-MEGAHASRI-2',
                'nama_asset'      => 'RECLOSER MEGAH ASRI',
                'jenis_asset'     => 'RECLOSER',
                'ulp_id'          => 1,
                'penyulang_id'    => 30,
                'parent_asset_id' => $recPole2,
                'latitude'        => -7.443610,
                'longitude'       => 112.720010,
            ]);
            $recloserId = (int)$db->insertID();

            // Upstream GI -> REC uses AAAC 150 mm² (400m)
            $db->table('gis_translines')->insert([
                'transline_code'  => 'TL-30-UP-1-2',
                'penyulang_id'    => 30,
                'source_asset_id' => $giPole1,
                'target_asset_id' => $recPole2,
                'conductor_type'  => 'AAAC',
                'conductor_size'  => '150 mm²',
                'distance_meters' => 400.0,
                'is_active'       => 1,
            ]);
            // Downstream REC -> DOWN3 uses AAACS 150 mm² (450m)
            $db->table('gis_translines')->insert([
                'transline_code'  => 'TL-30-DN-2-3',
                'penyulang_id'    => 30,
                'source_asset_id' => $recPole2,
                'target_asset_id' => $downPole3,
                'conductor_type'  => 'AAACS',
                'conductor_size'  => '150 mm²',
                'distance_meters' => 450.0,
                'is_active'       => 1,
            ]);
            // Downstream DOWN3 -> END4 uses MVTIC 150 mm² (550m)
            $db->table('gis_translines')->insert([
                'transline_code'  => 'TL-30-DN-3-4',
                'penyulang_id'    => 30,
                'source_asset_id' => $downPole3,
                'target_asset_id' => $endPole4,
                'conductor_type'  => 'MVTIC',
                'conductor_size'  => '150 mm²',
                'distance_meters' => 550.0,
                'is_active'       => 1,
            ]);

            // Run locateFault from Recloser WITHOUT manual conductor_type and WITHOUT relay_distance_m
            $res = $this->service->locateFault([
                'ulp_id'               => 1,
                'feeder_id'            => 30,
                'source_type'          => 'PROTECTION',
                'protection_device_id' => $recloserId,
                'current_r'            => 244.0,
                'current_s'            => 264.0,
                'current_t'            => 238.0,
                'current_n'            => 67.0,
                'voltage_r_kv'         => 21.26,
                'voltage_s_kv'         => 0.00,
                'voltage_t_kv'         => 21.00,
                'relay_distance_m'     => null,
            ]);

            // 1. Verify topological path length vs relay measured distance separation
            $this->assertEquals(1000.0, $res['topology_path_length_m'], 'Downstream path length from Recloser to feeder end must be 450 + 550 = 1000.0 m');
            $this->assertSame('Panjang jalur dari proteksi ke ujung feeder (estimasi topologi)', $res['topology_path_length_label']);
            $this->assertNull($res['relay_measured_distance_m'], 'Relay measured distance must be null when not entered by user');
            $this->assertNull($res['relay_distance_m'], 'relay_distance_m must never be populated with topological path length or fake fallback');
            $this->assertEquals(0.0, $res['calculated_distance_m'], 'DGR ground fault without numeric relay distance must not invent a 1200m fault distance');

            // 2. Verify automatic conductor from traversed downstream path (excluding upstream GI AAAC span)
            $condSummary = $res['traversed_conductor'];
            $this->assertTrue($condSummary['is_available']);
            $this->assertSame('MULTI_SEGMENT_HYBRID', $condSummary['status']);
            $this->assertCount(2, $condSummary['segment_ranges']);
            $this->assertSame('AAACS 150 mm²', $condSummary['segment_ranges'][0]['conductor_label']);
            $this->assertEquals(0.0, $condSummary['segment_ranges'][0]['start_m']);
            $this->assertEquals(450.0, $condSummary['segment_ranges'][0]['end_m']);
            $this->assertSame('MVTIC 150 mm²', $condSummary['segment_ranges'][1]['conductor_label']);
            $this->assertEquals(450.0, $condSummary['segment_ranges'][1]['start_m']);
            $this->assertEquals(1000.0, $condSummary['segment_ranges'][1]['end_m']);

            // 3. Verify persistence of phase voltages (including 0.00 kV vs null) in fault_cases
            $resWithNullT = $res;
            $resWithNullT['fault_classification']['voltage_t_kv'] = null;
            $resWithNullT['telemetry_input']['voltage_t_kv'] = null;
            $saved = $this->service->saveFaultCase($resWithNullT, null);
            $this->assertSame('success', $saved['status']);

            $reloaded = $this->service->getCaseDetails((int)$saved['case_id']);
            $this->assertNotNull($reloaded);
            $this->assertEquals(21.26, $reloaded['voltage_r_kv']);
            $this->assertSame(0.0, $reloaded['voltage_s_kv'], 'Reloaded 0.00 kV must remain float 0.0, not null');
            $this->assertNull($reloaded['voltage_t_kv'], 'Unentered voltage must remain null');
            $this->assertEquals(1000.0, $reloaded['topology_path_length_m']);
            $this->assertNull($reloaded['relay_measured_distance_m']);

            // 4. Verify empty conductor metadata reports "Belum tersedia" without guessing
            $db->table('gis_translines')->where('penyulang_id', 30)->update([
                'conductor_type' => '',
                'conductor_size' => null,
            ]);
            $resEmptyCond = $this->service->locateFault([
                'ulp_id'               => 1,
                'feeder_id'            => 30,
                'source_type'          => 'PROTECTION',
                'protection_device_id' => $recloserId,
                'current_r'            => 244.0,
                'current_s'            => 264.0,
                'current_t'            => 238.0,
                'current_n'            => 67.0,
            ]);
            $this->assertFalse($resEmptyCond['traversed_conductor']['is_available']);
            $this->assertSame('Belum tersedia', $resEmptyCond['traversed_conductor']['display_label']);
            $this->assertSame('Belum tersedia', $resEmptyCond['electrical_parameters']['conductor_type']);
        } finally {
            $db->transRollback();
        }
    }

    public function testOperationalClassificationBoundary300A(): void
    {
        // Boundary: Exactly 300 A does NOT meet "di atas 300 A" -> PERLU_VERIFIKASI
        $class300 = $this->service->classifyFaultCurrents(300.0, 300.0, 5.0, 0.0);
        $this->assertSame('PERLU_VERIFIKASI', $class300['fault_type']);
        $this->assertSame('PERLU_VERIFIKASI', $class300['operational_category']);
        $this->assertStringContainsString('300 A', $class300['fault_type_label']);
        $this->assertFalse($class300['is_ground_fault']);

        // Boundary: 301 A meets "di atas 300 A" -> OCR
        $class301 = $this->service->classifyFaultCurrents(301.0, 301.0, 5.0, 0.0);
        $this->assertSame('OCR', $class301['fault_type']);
        $this->assertSame('OCR', $class301['operational_category']);
        $this->assertStringContainsString('OCR', $class301['fault_type_label']);
        $this->assertFalse($class301['is_ground_fault']);
    }

    public function testOperationalClassificationBoundary1000A(): void
    {
        // Boundary: Exactly 1000 A enters OCR
        $class1000 = $this->service->classifyFaultCurrents(1000.0, 1000.0, 5.0, 0.0);
        $this->assertSame('OCR', $class1000['fault_type']);
        $this->assertSame('OCR', $class1000['operational_category']);
        $this->assertStringContainsString('OCR', $class1000['fault_type_label']);
        $this->assertFalse($class1000['is_ground_fault']);

        // Boundary: Above 1000 A (1001 A) enters OCR Instan
        $class1001 = $this->service->classifyFaultCurrents(1001.0, 1001.0, 5.0, 0.0);
        $this->assertSame('OCR_INSTAN', $class1001['fault_type']);
        $this->assertSame('OCR_INSTAN', $class1001['operational_category']);
        $this->assertStringContainsString('OCR Instan', $class1001['fault_type_label']);
        $this->assertFalse($class1001['is_ground_fault']);
    }

    public function testOperationalClassificationNeutralNoiseVersusDetected(): void
    {
        // Neutral <= 15 A considered zero/noise (e.g. 5 A)
        $classZero = $this->service->classifyFaultCurrents(600.0, 600.0, 5.0, 5.0);
        $this->assertSame('OCR', $classZero['fault_type']);
        $this->assertFalse($classZero['is_ground_fault']);

        // Neutral >= 20 A is detected ground fault -> OCR-DGR
        $classDetected = $this->service->classifyFaultCurrents(600.0, 600.0, 5.0, 45.0);
        $this->assertSame('OCR_DGR', $classDetected['fault_type']);
        $this->assertSame('OCR', $classDetected['sub_classification']);
        $this->assertTrue($classDetected['is_ground_fault']);
    }

    public function testOperationalClassificationOcrDgrAndOcrInstanDgr(): void
    {
        // OCR-DGR: Phase between 300 A - 1000 A + neutral detected
        $classOcrDgr = $this->service->classifyFaultCurrents(700.0, 700.0, 5.0, 50.0);
        $this->assertSame('OCR_DGR', $classOcrDgr['fault_type']);
        $this->assertSame('OCR', $classOcrDgr['sub_classification']);
        $this->assertStringContainsString('OCR-DGR', $classOcrDgr['fault_type_label']);
        $this->assertTrue($classOcrDgr['is_ground_fault']);

        // OCR Instan-DGR: Phase > 1000 A + neutral detected
        $classInstanDgr = $this->service->classifyFaultCurrents(1500.0, 1500.0, 1500.0, 60.0);
        $this->assertSame('OCR_INSTAN_DGR', $classInstanDgr['fault_type']);
        $this->assertSame('OCR_INSTAN', $classInstanDgr['sub_classification']);
        $this->assertStringContainsString('OCR Instan-DGR', $classInstanDgr['fault_type_label']);
        $this->assertTrue($classInstanDgr['is_ground_fault']);
    }

    public function testJenggoloDgrCaseYieldsZeroCandidatePolesAndHonestMissingParams(): void
    {
        // Jenggolo case from user screenshot:
        // R=244A / 21.26kV, S=264A / 0kV, T=238A / 21kV, N=67A
        $telemetry = [
            'feeder_id'        => 1,
            'source_type'      => 'GI',
            'current_r'        => 244.0,
            'current_s'        => 264.0,
            'current_t'        => 238.0,
            'current_n'        => 67.0,
            'voltage_r_kv'     => 21.26,
            'voltage_s_kv'     => 0.0,
            'voltage_t_kv'     => 21.0,
            'relay_distance_m' => null,
            'conductor_type'   => 'AAAC 150 mm²',
        ];

        $res = $this->service->locateFault($telemetry);

        $this->assertSame('DATA_TIDAK_CUKUP', $res['calculation_status']);
        $this->assertSame('DATA_TIDAK_CUKUP', $res['outcome_status']);
        $this->assertSame('DGR', $res['fault_classification']['fault_type']);
        $this->assertEmpty($res['candidate_assets'], 'Indeterminate DGR without loop reactance/relay distance MUST return 0 fake candidate poles');
        $this->assertFalse($res['has_physics_prediction']);
        $this->assertNotEmpty($res['missing_parameters']);
        $this->assertStringContainsString('Jarak gangguan terukur relay', implode(' ', $res['missing_parameters']));
        $this->assertFalse($res['candidate_zone']['is_single_point']);
        $this->assertSame(0, $res['candidate_zone']['candidate_pole_count']);
    }

    public function testRelayAidedCandidatesRankedByDeltaToFaultPointNotDistanceFromSource(): void
    {
        $telemetry = [
            'feeder_id'        => 1,
            'source_type'      => 'GI',
            'current_r'        => 280.0,
            'current_s'        => 0.0,
            'current_t'        => 0.0,
            'current_n'        => 275.0,
            'relay_distance_m' => 850.0, // target distance 850m
            'conductor_type'   => 'AAAC 150 mm²',
        ];

        $res = $this->service->locateFault($telemetry);

        $this->assertSame('CALCULATED_RELAY_AIDED', $res['calculation_status']);
        $this->assertEqualsWithDelta(850.0, (float)$res['calculated_distance_m'], 1.0);
        $this->assertTrue($res['has_physics_prediction']);
        $this->assertTrue($res['candidate_zone']['is_single_point']);

        if (!empty($res['candidate_assets'])) {
            $this->assertLessThanOrEqual(4, count($res['candidate_assets']), 'Bounding candidate poles must be narrowed to segment bounding poles, not all feeder poles');
            
            // Verify candidate ranking is by proximity delta to calculated fault point
            $prevDelta = -1.0;
            foreach ($res['candidate_assets'] as $cand) {
                $this->assertArrayHasKey('delta_to_target_m', $cand);
                $this->assertSame('PROXIMITY_TO_CALCULATED_FAULT_POINT', $cand['ranking_basis']);
                $delta = (float)$cand['delta_to_target_m'];
                $this->assertGreaterThanOrEqual($prevDelta, $delta, 'Candidates must be sorted strictly by delta_to_target_m ascending');
                $prevDelta = $delta;
            }
        }
    }
}
