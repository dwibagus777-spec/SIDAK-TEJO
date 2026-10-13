<?php

namespace Tests\Unit\Fault;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\FtuMeasurementValidatorService;
use App\Services\FtuFaultParserService;

/**
 * Class FtuMeasurementValidatorServiceTest
 *
 * CR-FL-01 Phase FL-01C: FTU-R200 Measurement & Physical Range Validator Test Suite
 * 30-Test Acceptance Suite enforcing:
 * 1. Valid single-line-ground, line-line, and 3-phase events
 * 2. Partial classifications (missing secondary parameters)
 * 3. Invalid physical violations (negative/excessive V, I, Z, D)
 * 4. Fault type / current coherence and strong contradictions
 * 5. Diagnostic-only telemetry separation (battery, heartbeat, normal load)
 * 6. Unresolved malformed / corrupted payloads
 * 7. Boundary tests: 24kV, 30kV, 25kA, 250Ω, 100km
 * 8. High-but-not-invalid diagnostic bands
 * 9. Missing protection setting handled as PROTECTION_PICKUP_UNKNOWN (no false invalid)
 * 10. Authoritative Z preservation (never overwritten by V/I)
 * 11. Deterministic SHA-256 fingerprint reproducibility
 * 12. Zero database mutation firewall
 */
class FtuMeasurementValidatorServiceTest extends CIUnitTestCase
{
    protected FtuMeasurementValidatorService $validator;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FtuMeasurementValidatorService();
        $this->db = \Config\Database::connect('default');
    }

    /**
     * Test 1: Valid Single-Line-to-Ground Fault
     */
    public function testValidSingleLineGround(): void
    {
        $raw = "FAULT: L2-G\nIa: 0.15kA Ib: 2.35kA Ic: 0.12kA\nV: 11.5kV\nDist: 1.2km\nZ: 0.48Ω\nTime: 15:32 WIB";
        $result = $this->validator->validate($raw);

        $this->assertEquals('VALID', $result['status']);
        $this->assertTrue($result['flags']['is_fault_event']);
        $this->assertTrue($result['flags']['is_locatable']);
        $this->assertTrue($result['flags']['is_engineering_plausible']);
        $this->assertTrue($result['flags']['is_complete']);
        $this->assertEmpty($result['rejection_codes']);
        $this->assertEquals('L2-G', $result['normalized_metrics']['fault_type']);
        $this->assertEquals(2.35, $result['normalized_metrics']['ib_ka']);
        $this->assertEquals(0.48, $result['normalized_metrics']['impedance_ohms']);
    }

    /**
     * Test 2: Valid Line-to-Line Fault
     */
    public function testValidLineLine(): void
    {
        $raw = "FAULT: A-B\nIa: 3.2kA Ib: 3.1kA Ic: 0.2kA\nV: 18.2kV\nDist: 4.5km\nZ: 1.8Ω\nTime: 10:15 WIB";
        $result = $this->validator->validate($raw);

        $this->assertEquals('VALID', $result['status']);
        $this->assertTrue($result['flags']['is_fault_event']);
        $this->assertTrue($result['flags']['is_locatable']);
        $this->assertTrue($result['flags']['is_engineering_plausible']);
        $this->assertTrue($result['flags']['is_complete']);
    }

    /**
     * Test 3: Valid Three-Phase Fault
     */
    public function testValidThreePhase(): void
    {
        $raw = "FAULT: 3P\nIa: 5.5kA Ib: 5.4kA Ic: 5.6kA\nV: 4.2kV\nDist: 2.1km\nZ: 0.85Ω\nTime: 08:44 WIB";
        $result = $this->validator->validate($raw);

        $this->assertEquals('VALID', $result['status']);
        $this->assertTrue($result['flags']['is_fault_event']);
        $this->assertTrue($result['flags']['is_locatable']);
        $this->assertTrue($result['flags']['is_engineering_plausible']);
        $this->assertTrue($result['flags']['is_complete']);
    }

    /**
     * Test 4: Partial Classification - Missing Voltage
     */
    public function testPartialMissingVoltage(): void
    {
        $raw = "FAULT: L1-G\nIa: 2.1kA Ib: 0.1kA Ic: 0.1kA\nDist: 1.5km\nZ: 0.6Ω\nTime: 12:00 WIB";
        $result = $this->validator->validate($raw);

        $this->assertEquals('PARTIAL', $result['status']);
        $this->assertTrue($result['flags']['is_fault_event']);
        $this->assertFalse($result['flags']['is_locatable']); // MUST NOT be true for PARTIAL
        $this->assertTrue($result['flags']['is_engineering_plausible']);
        $this->assertFalse($result['flags']['is_complete']);
        $this->assertNull($result['normalized_metrics']['voltage_kv']);
    }

    /**
     * Test 5: Partial Classification - Missing Impedance and Distance
     */
    public function testPartialMissingImpedanceAndDistance(): void
    {
        $raw = "FAULT: L3-G\nIa: 0.1kA Ib: 0.1kA Ic: 2.5kA\nV: 10.5kV\nTime: 14:20 WIB";
        $result = $this->validator->validate($raw);

        $this->assertEquals('PARTIAL', $result['status']);
        $this->assertTrue($result['flags']['is_fault_event']);
        $this->assertFalse($result['flags']['is_locatable']);
        $this->assertTrue($result['flags']['is_engineering_plausible']);
        $this->assertFalse($result['flags']['is_complete']);
        $this->assertNull($result['normalized_metrics']['distance_km']);
        $this->assertNull($result['normalized_metrics']['impedance_ohms']);
    }

    /**
     * Test 6: Invalid - Negative Current
     */
    public function testNegativeCurrent(): void
    {
        $payload = [
            'fault_type' => 'A-G',
            'ia'         => -1.5,
            'ib'         => 0.1,
            'ic'         => 0.1,
            'voltage'    => 11.5,
            'distance'   => 2.0,
            'impedance'  => 0.8,
        ];

        $result = $this->validator->validate($payload);

        $this->assertEquals('INVALID', $result['status']);
        $this->assertFalse($result['flags']['is_engineering_plausible']);
        $this->assertFalse($result['flags']['is_locatable']);
        $this->assertContains('CURRENT_NEGATIVE', $result['rejection_codes']);
    }

    /**
     * Test 7: Invalid - Current Exceeds Physical Rating (> 25 kA)
     */
    public function testCurrentExceedsMaxRating(): void
    {
        $payload = [
            'fault_type' => 'A-G',
            'ia'         => 26.5, // 26.5 kA > 25 kA
            'ib'         => 0.1,
            'ic'         => 0.1,
            'voltage'    => 11.5,
            'distance'   => 1.0,
            'impedance'  => 0.4,
        ];

        $result = $this->validator->validate($payload);

        $this->assertEquals('INVALID', $result['status']);
        $this->assertFalse($result['flags']['is_engineering_plausible']);
        $this->assertContains('CURRENT_EXCEEDS_RATING', $result['rejection_codes']);
    }

    /**
     * Test 8: Invalid - Negative Voltage
     */
    public function testNegativeVoltage(): void
    {
        $payload = [
            'fault_type' => 'A-G',
            'ia'         => 2.0,
            'ib'         => 0.1,
            'ic'         => 0.1,
            'voltage'    => -5.0,
            'distance'   => 1.0,
            'impedance'  => 0.4,
        ];

        $result = $this->validator->validate($payload);

        $this->assertEquals('INVALID', $result['status']);
        $this->assertFalse($result['flags']['is_engineering_plausible']);
        $this->assertContains('VOLTAGE_NEGATIVE', $result['rejection_codes']);
    }

    /**
     * Test 9: Invalid - Voltage Exceeds Maximum MV Withstand (> 30 kV)
     */
    public function testVoltageExceedsMaxMv(): void
    {
        $payload = [
            'fault_type' => 'A-G',
            'ia'         => 2.0,
            'ib'         => 0.1,
            'ic'         => 0.1,
            'voltage'    => 32.0, // 32 kV > 30 kV
            'distance'   => 1.0,
            'impedance'  => 0.4,
        ];

        $result = $this->validator->validate($payload);

        $this->assertEquals('INVALID', $result['status']);
        $this->assertFalse($result['flags']['is_engineering_plausible']);
        $this->assertContains('VOLTAGE_EXCEEDS_MAX_MV', $result['rejection_codes']);
    }

    /**
     * Test 10: Invalid - Non-positive Impedance (Z <= 0)
     */
    public function testNonPositiveImpedance(): void
    {
        $payload = [
            'fault_type' => 'A-G',
            'ia'         => 2.0,
            'ib'         => 0.1,
            'ic'         => 0.1,
            'voltage'    => 11.5,
            'distance'   => 1.0,
            'impedance'  => 0.0,
        ];

        $result = $this->validator->validate($payload);

        $this->assertEquals('INVALID', $result['status']);
        $this->assertFalse($result['flags']['is_engineering_plausible']);
        $this->assertContains('IMPEDANCE_NON_POSITIVE', $result['rejection_codes']);
    }

    /**
     * Test 11: Invalid - Impedance Exceeds Physical Bound (> 250 Ohm)
     */
    public function testImpedanceExceedsMaxBound(): void
    {
        $payload = [
            'fault_type' => 'A-G',
            'ia'         => 2.0,
            'ib'         => 0.1,
            'ic'         => 0.1,
            'voltage'    => 11.5,
            'distance'   => 1.0,
            'impedance'  => 280.0, // 280 Ohm > 250 Ohm
        ];

        $result = $this->validator->validate($payload);

        $this->assertEquals('INVALID', $result['status']);
        $this->assertFalse($result['flags']['is_engineering_plausible']);
        $this->assertContains('IMPEDANCE_OUT_OF_BOUNDS', $result['rejection_codes']);
    }

    /**
     * Test 12: Invalid - Non-positive Distance (D <= 0)
     */
    public function testNonPositiveDistance(): void
    {
        $payload = [
            'fault_type' => 'A-G',
            'ia'         => 2.0,
            'ib'         => 0.1,
            'ic'         => 0.1,
            'voltage'    => 11.5,
            'distance'   => -0.5,
            'impedance'  => 0.5,
        ];

        $result = $this->validator->validate($payload);

        $this->assertEquals('INVALID', $result['status']);
        $this->assertFalse($result['flags']['is_engineering_plausible']);
        $this->assertContains('DISTANCE_NON_POSITIVE', $result['rejection_codes']);
    }

    /**
     * Test 13: Invalid - Distance Exceeds Feeder Scale (> 100 km)
     */
    public function testDistanceExceedsScale(): void
    {
        $payload = [
            'fault_type' => 'A-G',
            'ia'         => 2.0,
            'ib'         => 0.1,
            'ic'         => 0.1,
            'voltage'    => 11.5,
            'distance'   => 125.0, // 125 km > 100 km
            'impedance'  => 0.5,
        ];

        $result = $this->validator->validate($payload);

        $this->assertEquals('INVALID', $result['status']);
        $this->assertFalse($result['flags']['is_engineering_plausible']);
        $this->assertContains('DISTANCE_EXCEEDS_SCALE', $result['rejection_codes']);
    }

    /**
     * Test 14: Invalid - Strong Fault-Type / Current Contradiction
     */
    public function testStrongFaultCurrentContradiction(): void
    {
        // Reported A-G, but Ia is 0 while Ib is 5.0 kA
        $payload = [
            'fault_type' => 'A-G',
            'ia'         => 0.0,
            'ib'         => 5.0,
            'ic'         => 0.1,
            'voltage'    => 11.5,
            'distance'   => 2.0,
            'impedance'  => 0.8,
        ];

        $result = $this->validator->validate($payload);

        $this->assertEquals('INVALID', $result['status']);
        $this->assertFalse($result['flags']['is_engineering_plausible']);
        $this->assertContains('FAULT_TYPE_CURRENT_INCOHERENT', $result['rejection_codes']);
    }

    /**
     * Test 15: Diagnostic-Only - Battery Test
     */
    public function testDiagnosticBattery(): void
    {
        $raw = "FAULT: BATTERY-TEST\nIa: 0.0kA Ib: 0.0kA Ic: 0.0kA\nV: 20.1kV\nTime: 02:00 WIB";
        $result = $this->validator->validate($raw);

        $this->assertEquals('DIAGNOSTIC_ONLY', $result['status']);
        $this->assertFalse($result['flags']['is_fault_event']);
        $this->assertFalse($result['flags']['is_locatable']);
        $this->assertTrue($result['flags']['is_engineering_plausible']);
        $this->assertFalse($result['flags']['is_complete']);
    }

    /**
     * Test 16: Diagnostic-Only - Normal Non-Fault Telemetry
     */
    public function testDiagnosticNormalTelemetry(): void
    {
        $payload = [
            'fault_type' => 'NORMAL',
            'ia'         => 0.12, // 120 A load
            'ib'         => 0.13, // 130 A load
            'ic'         => 0.11, // 110 A load
            'voltage'    => 20.0,
        ];

        $result = $this->validator->validate($payload);

        $this->assertEquals('DIAGNOSTIC_ONLY', $result['status']);
        $this->assertFalse($result['flags']['is_fault_event']);
        $this->assertFalse($result['flags']['is_locatable']);
    }

    /**
     * Test 17: Unresolved - Empty Payload
     */
    public function testUnresolvedEmpty(): void
    {
        $result = $this->validator->validate("   \n\t  ");

        $this->assertEquals('UNRESOLVED', $result['status']);
        $this->assertFalse($result['flags']['is_fault_event']);
        $this->assertFalse($result['flags']['is_locatable']);
        $this->assertContains('UNRESOLVED_PAYLOAD', $result['rejection_codes']);
    }

    /**
     * Test 18: Unresolved - Malformed Parser Result / Conflicting Fields
     */
    public function testUnresolvedMalformedParserResult(): void
    {
        // Conflicting duplicate fields in raw string
        $raw = "FAULT: A-G\nFAULT: B-C\nIa: 2.0kA Ib: 0.1kA Ic: 0.1kA\nV: 11.5kV";
        $result = $this->validator->validate($raw);

        $this->assertEquals('UNRESOLVED', $result['status']);
        $this->assertFalse($result['flags']['is_fault_event']);
        $this->assertFalse($result['flags']['is_locatable']);
    }

    /**
     * Test 19: Deterministic SHA-256 Fingerprint Reproducibility
     */
    public function testDeterministicFingerprint(): void
    {
        $raw = "FAULT: L2-G\nIa: 0.15kA Ib: 2.35kA Ic: 0.12kA\nV: 11.5kV\nDist: 1.2km\nZ: 0.48Ω\nTime: 15:32 WIB";
        $run1 = $this->validator->validate($raw);
        $run2 = $this->validator->validate($raw);

        $this->assertNotEmpty($run1['validation_fingerprint']);
        $this->assertEquals($run1['validation_fingerprint'], $run2['validation_fingerprint']);
    }

    /**
     * Test 20: Zero Database Mutation Firewall
     */
    public function testZeroDatabaseMutation(): void
    {
        $assetsBefore = $this->db->table('assets')->countAllResults();
        $translinesBefore = $this->db->table('gis_translines')->countAllResults();
        $penyulangBefore = $this->db->table('penyulang')->countAllResults();
        $sectionsBefore = $this->db->table('sections')->countAllResults();
        $temuanBefore = $this->db->table('temuan')->countAllResults();

        // Run validation on multiple records
        $this->validator->validate("FAULT: A-G\nIa: 2.1kA Ib: 0.1kA Ic: 0.1kA\nV: 11.5kV\nDist: 1.5km\nZ: 0.6Ω");
        $this->validator->validate("FAULT: BATTERY-TEST\nIa: 0kA\nV: 20kV");

        $assetsAfter = $this->db->table('assets')->countAllResults();
        $translinesAfter = $this->db->table('gis_translines')->countAllResults();
        $penyulangAfter = $this->db->table('penyulang')->countAllResults();
        $sectionsAfter = $this->db->table('sections')->countAllResults();
        $temuanAfter = $this->db->table('temuan')->countAllResults();

        $this->assertEquals($assetsBefore, $assetsAfter);
        $this->assertEquals($translinesBefore, $translinesAfter);
        $this->assertEquals($penyulangBefore, $penyulangAfter);
        $this->assertEquals($sectionsBefore, $sectionsAfter);
        $this->assertEquals($temuanBefore, $temuanAfter);

        $this->assertFalse($this->db->tableExists('fault_records'));
    }

    /**
     * Test 21: 24kV Boundary Testing
     */
    public function test24kVBoundary(): void
    {
        // Exactly 24.0 kV: PLAUSIBLE / TYPICAL BAND
        $resAt = $this->validator->validate([
            'fault_type' => 'A-G', 'ia' => 2.0, 'ib' => 0.1, 'ic' => 0.1, 'voltage' => 24.0, 'distance' => 1.0, 'impedance' => 0.4
        ]);
        $this->assertEquals('PLAUSIBLE / TYPICAL BAND', $resAt['diagnostic_bands']['voltage_band']);
        $this->assertEquals('VALID', $resAt['status']);

        // Slightly above 24.0 kV (24.1 kV): OUTSIDE_TYPICAL / DIAGNOSTIC (NOT invalid!)
        $resAbove = $this->validator->validate([
            'fault_type' => 'A-G', 'ia' => 2.0, 'ib' => 0.1, 'ic' => 0.1, 'voltage' => 24.1, 'distance' => 1.0, 'impedance' => 0.4
        ]);
        $this->assertEquals('OUTSIDE_TYPICAL / DIAGNOSTIC', $resAbove['diagnostic_bands']['voltage_band']);
        $this->assertEquals('VALID', $resAbove['status']); // Still valid!
    }

    /**
     * Test 22: 30kV Boundary Testing
     */
    public function test30kVBoundary(): void
    {
        // Exactly 30.0 kV: OUTSIDE_TYPICAL / DIAGNOSTIC
        $resAt = $this->validator->validate([
            'fault_type' => 'A-G', 'ia' => 2.0, 'ib' => 0.1, 'ic' => 0.1, 'voltage' => 30.0, 'distance' => 1.0, 'impedance' => 0.4
        ]);
        $this->assertEquals('OUTSIDE_TYPICAL / DIAGNOSTIC', $resAt['diagnostic_bands']['voltage_band']);
        $this->assertEquals('VALID', $resAt['status']);

        // Slightly above 30.0 kV (30.1 kV): INVALID_PHYSICAL_BOUND
        $resAbove = $this->validator->validate([
            'fault_type' => 'A-G', 'ia' => 2.0, 'ib' => 0.1, 'ic' => 0.1, 'voltage' => 30.1, 'distance' => 1.0, 'impedance' => 0.4
        ]);
        $this->assertEquals('INVALID_PHYSICAL_BOUND', $resAbove['diagnostic_bands']['voltage_band']);
        $this->assertEquals('INVALID', $resAbove['status']);
    }

    /**
     * Test 23: 25kA Boundary Testing
     */
    public function test25kABoundary(): void
    {
        // Exactly 25.0 kA: HIGH / OUTSIDE_TYPICAL (NOT invalid!)
        $resAt = $this->validator->validate([
            'fault_type' => 'A-G', 'ia' => 25.0, 'ib' => 0.1, 'ic' => 0.1, 'voltage' => 11.5, 'distance' => 1.0, 'impedance' => 0.4
        ]);
        $this->assertEquals('HIGH / OUTSIDE_TYPICAL', $resAt['diagnostic_bands']['current_bands']['ia']);
        $this->assertEquals('VALID', $resAt['status']);

        // Above 25.0 kA (25.1 kA): INVALID_PHYSICAL_BOUND
        $resAbove = $this->validator->validate([
            'fault_type' => 'A-G', 'ia' => 25.1, 'ib' => 0.1, 'ic' => 0.1, 'voltage' => 11.5, 'distance' => 1.0, 'impedance' => 0.4
        ]);
        $this->assertEquals('INVALID_PHYSICAL_BOUND', $resAbove['diagnostic_bands']['current_bands']['ia']);
        $this->assertEquals('INVALID', $resAbove['status']);
    }

    /**
     * Test 24: 250 Ohm Boundary Testing
     */
    public function test250OhmBoundary(): void
    {
        // Exactly 250.0 Ohm: HIGH / OUTSIDE_TYPICAL
        $resAt = $this->validator->validate([
            'fault_type' => 'A-G', 'ia' => 2.0, 'ib' => 0.1, 'ic' => 0.1, 'voltage' => 11.5, 'distance' => 1.0, 'impedance' => 250.0
        ]);
        $this->assertEquals('HIGH / OUTSIDE_TYPICAL', $resAt['diagnostic_bands']['impedance_band']);
        $this->assertEquals('VALID', $resAt['status']);

        // Above 250.0 Ohm (250.1 Ohm): INVALID_PHYSICAL_BOUND
        $resAbove = $this->validator->validate([
            'fault_type' => 'A-G', 'ia' => 2.0, 'ib' => 0.1, 'ic' => 0.1, 'voltage' => 11.5, 'distance' => 1.0, 'impedance' => 250.1
        ]);
        $this->assertEquals('INVALID_PHYSICAL_BOUND', $resAbove['diagnostic_bands']['impedance_band']);
        $this->assertEquals('INVALID', $resAbove['status']);
    }

    /**
     * Test 25: 100km Boundary Testing
     */
    public function test100kmBoundary(): void
    {
        // Exactly 100.0 km: OUTSIDE_TYPICAL / DIAGNOSTIC
        $resAt = $this->validator->validate([
            'fault_type' => 'A-G', 'ia' => 2.0, 'ib' => 0.1, 'ic' => 0.1, 'voltage' => 11.5, 'distance' => 100.0, 'impedance' => 0.4
        ]);
        $this->assertEquals('OUTSIDE_TYPICAL / DIAGNOSTIC', $resAt['diagnostic_bands']['distance_band']);
        $this->assertEquals('VALID', $resAt['status']);

        // Above 100.0 km (100.1 km): INVALID_PHYSICAL_BOUND
        $resAbove = $this->validator->validate([
            'fault_type' => 'A-G', 'ia' => 2.0, 'ib' => 0.1, 'ic' => 0.1, 'voltage' => 11.5, 'distance' => 100.1, 'impedance' => 0.4
        ]);
        $this->assertEquals('INVALID_PHYSICAL_BOUND', $resAbove['diagnostic_bands']['distance_band']);
        $this->assertEquals('INVALID', $resAbove['status']);
    }

    /**
     * Test 26: High-But-Not-Invalid Diagnostic Bands
     */
    public function testHighButNotInvalidBands(): void
    {
        // Values in the elevated diagnostic bands: V=27kV, I=18kA, Z=180Ω, D=65km
        $payload = [
            'fault_type' => 'A-G',
            'ia'         => 18.0,
            'ib'         => 0.1,
            'ic'         => 0.1,
            'voltage'    => 27.0,
            'distance'   => 65.0,
            'impedance'  => 180.0,
        ];

        $result = $this->validator->validate($payload);

        $this->assertEquals('VALID', $result['status']);
        $this->assertTrue($result['flags']['is_engineering_plausible']);
        $this->assertEquals('OUTSIDE_TYPICAL / DIAGNOSTIC', $result['diagnostic_bands']['voltage_band']);
        $this->assertEquals('HIGH / OUTSIDE_TYPICAL', $result['diagnostic_bands']['current_bands']['ia']);
        $this->assertEquals('HIGH / OUTSIDE_TYPICAL', $result['diagnostic_bands']['impedance_band']);
        $this->assertEquals('OUTSIDE_TYPICAL / DIAGNOSTIC', $result['diagnostic_bands']['distance_band']);
    }

    /**
     * Test 27: Missing Protection Pickup Setting Does Not Create False Invalid
     */
    public function testMissingOcrGfrSettingDoesNotCreateFalseInvalid(): void
    {
        // Event with low current (e.g. 150 A = 0.15 kA) without configured protection setting
        $payload = [
            'fault_type' => 'A-G',
            'ia'         => 0.15,
            'ib'         => 0.05,
            'ic'         => 0.05,
            'voltage'    => 11.5,
            'distance'   => 1.0,
            'impedance'  => 0.4,
        ];

        $result = $this->validator->validate($payload);

        $this->assertNotEquals('INVALID', $result['status']);
        $this->assertEquals('PROTECTION_PICKUP_UNKNOWN', $result['protection_pickup_status']);
    }

    /**
     * Test 28: Authoritative Z Preserved Exactly
     */
    public function testAuthoritativeZPreserved(): void
    {
        $payload = [
            'fault_type' => 'L2-G',
            'ia'         => 0.1,
            'ib'         => 2.5,
            'ic'         => 0.1,
            'voltage'    => 11.5,
            'distance'   => 1.2,
            'impedance'  => 0.3845,
        ];

        $result = $this->validator->validate($payload);

        $this->assertEquals(0.3845, $result['normalized_metrics']['impedance_ohms']);
        $this->assertTrue($result['diagnostics']['authoritative_z_preserved']);
    }

    /**
     * Test 29: Validator Never Computes Replacement Z
     */
    public function testValidatorNeverComputesReplacementZ(): void
    {
        $payload = [
            'fault_type' => 'A-G',
            'ia'         => 2.0,
            'ib'         => 0.1,
            'ic'         => 0.1,
            'voltage'    => 10.0,
            'distance'   => 1.0,
            'impedance'  => 0.5,
        ];

        $result = $this->validator->validate($payload);

        // FTU Z is 0.5, but V/I would be 10.0 / 2.0 = 5.0
        $this->assertEquals(0.5, $result['normalized_metrics']['impedance_ohms']);
        $this->assertNotEquals(5.0, $result['normalized_metrics']['impedance_ohms']);

        // Diagnostic V/I is labeled DIAGNOSTIC_ONLY
        $diag = $result['diagnostics']['diagnostic_v_over_i'] ?? null;
        $this->assertNotNull($diag);
        $this->assertEquals('DIAGNOSTIC_ONLY', $diag['label']);
        $this->assertEquals(5.0, $diag['calculated_ratio_ohms']);
    }

    /**
     * Test 30: No Topology Access
     */
    public function testNoTopologyAccess(): void
    {
        // Service should execute cleanly even if DB tables were completely unreferenced
        $validatorWithoutDb = new FtuMeasurementValidatorService();
        $result = $validatorWithoutDb->validate("FAULT: A-G\nIa: 2.1kA Ib: 0.1kA Ic: 0.1kA\nV: 11.5kV\nDist: 1.5km\nZ: 0.6Ω");

        $this->assertEquals('VALID', $result['status']);
        $this->assertArrayNotHasKey('topology', $result);
        $this->assertArrayNotHasKey('nearest_asset', $result);
        $this->assertArrayNotHasKey('fault_coordinates', $result);
    }
}
