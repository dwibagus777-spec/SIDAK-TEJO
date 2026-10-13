<?php

namespace Tests\Unit\Fault;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\FtuFaultParserService;

/**
 * Class FtuFaultParserServiceTest
 *
 * CR-FL-01 Phase FL-01B: FTU-R200 Fault Parser Service Acceptance Test Suite.
 * Enforces strict read-only, deterministic parsing, unit normalization,
 * fault type preservation, and comprehensive malformed input handling.
 */
class FtuFaultParserServiceTest extends CIUnitTestCase
{
    protected FtuFaultParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FtuFaultParserService();
    }

    /**
     * Test 1: Valid Canonical Payload
     */
    public function testCanonicalPayload(): void
    {
        $raw = "FAULT: L2-G\nIa: 0.15kA Ib: 2.35kA Ic: 0.12kA\nV: 0.42kV\nDist: 1.2km\nZ: 0.38Ω\nTime: 15:32 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('SUCCESS', $result['parse_status']);
        $this->assertEquals('FL-01B-1.0', $result['parser_version']);
        $this->assertEquals('L2-G', $result['fault_type']);
        $this->assertEquals(0.15, $result['ia']);
        $this->assertEquals(2.35, $result['ib']);
        $this->assertEquals(0.12, $result['ic']);
        $this->assertEquals(0.42, $result['voltage']);
        $this->assertEquals(1.2, $result['distance']);
        $this->assertEquals('KM', $result['distance_unit']);
        $this->assertEquals(0.38, $result['impedance']);
        $this->assertEquals('OHM', $result['impedance_unit']);
        $this->assertEquals('15:32', $result['fault_time']);
        $this->assertEquals('WIB', $result['timezone']);
        $this->assertNull($result['date']);
        $this->assertEmpty($result['parse_errors']);
        $this->assertCount(8, $result['parsed_fields']);
    }

    /**
     * Test 2: Whitespace Variants (Tabs, Extra Lines, Single-line Inline)
     */
    public function testWhitespaceVariants(): void
    {
        // Single-line inline with tabs and variable spaces
        $rawInline = "  FAULT:   L2-G \t  Ia: 0.15kA   Ib: 2.35kA   Ic: 0.12kA \t V: 0.42kV   Dist: 1.2km   Z: 0.38Ω   Time: 15:32 WIB  ";

        $result = $this->parser->parse($rawInline);

        $this->assertEquals('SUCCESS', $result['parse_status']);
        $this->assertEquals('L2-G', $result['fault_type']);
        $this->assertEquals(0.15, $result['ia']);
        $this->assertEquals(1.2, $result['distance']);
        $this->assertEquals('KM', $result['distance_unit']);
        $this->assertEquals('15:32', $result['fault_time']);
        $this->assertEquals('WIB', $result['timezone']);
    }

    /**
     * Test 3: Line Ordering Variants (Time First, Dist Middle, Fault Last)
     */
    public function testLineOrderingVariants(): void
    {
        $raw = "Time: 15:32 WIB\nDist: 1.2km\nZ: 0.38Ω\nV: 0.42kV\nIc: 0.12kA\nIb: 2.35kA\nIa: 0.15kA\nFAULT: L1-L2";

        $result = $this->parser->parse($raw);

        $this->assertEquals('SUCCESS', $result['parse_status']);
        $this->assertEquals('L1-L2', $result['fault_type']);
        $this->assertEquals(0.15, $result['ia']);
        $this->assertEquals(1.2, $result['distance']);
        $this->assertEquals('15:32', $result['fault_time']);
    }

    /**
     * Test 4: Unit Delimiter Variants (=, :, Space, Spaces around Units)
     */
    public function testUnitDelimiterVariants(): void
    {
        $raw = "FAULT=L3-G\nIa=0.15 kA\nIb=2.35 kA\nIc=0.12 kA\nV=0.42 kV\nDist=1.2 km\nZ=0.38 ohm\nTime=15:32 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('SUCCESS', $result['parse_status']);
        $this->assertEquals('L3-G', $result['fault_type']);
        $this->assertEquals(0.15, $result['ia']);
        $this->assertEquals(1.2, $result['distance']);
        $this->assertEquals('KM', $result['distance_unit']);
        $this->assertEquals(0.38, $result['impedance']);
        $this->assertEquals('OHM', $result['impedance_unit']);
    }

    /**
     * Test 5: Missing Fault Type
     */
    public function testMissingFaultType(): void
    {
        $raw = "Ia: 0.15kA Ib: 2.35kA Ic: 0.12kA\nV: 0.42kV\nDist: 1.2km\nZ: 0.38Ω\nTime: 15:32 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('PARTIAL', $result['parse_status']);
        $this->assertNull($result['fault_type']);
        $this->assertContains('MISSING_FIELD: fault_type is absent', $result['parse_errors']);
    }

    /**
     * Test 6: Missing Ia Current
     */
    public function testMissingCurrentIa(): void
    {
        $raw = "FAULT: L2-G\nIb: 2.35kA Ic: 0.12kA\nV: 0.42kV\nDist: 1.2km\nZ: 0.38Ω\nTime: 15:32 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('PARTIAL', $result['parse_status']);
        $this->assertNull($result['ia']);
        $this->assertContains('MISSING_FIELD: ia is absent', $result['parse_errors']);
    }

    /**
     * Test 7: Missing Ib Current
     */
    public function testMissingCurrentIb(): void
    {
        $raw = "FAULT: L2-G\nIa: 0.15kA Ic: 0.12kA\nV: 0.42kV\nDist: 1.2km\nZ: 0.38Ω\nTime: 15:32 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('PARTIAL', $result['parse_status']);
        $this->assertNull($result['ib']);
        $this->assertContains('MISSING_FIELD: ib is absent', $result['parse_errors']);
    }

    /**
     * Test 8: Missing Ic Current
     */
    public function testMissingCurrentIc(): void
    {
        $raw = "FAULT: L2-G\nIa: 0.15kA Ib: 2.35kA\nV: 0.42kV\nDist: 1.2km\nZ: 0.38Ω\nTime: 15:32 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('PARTIAL', $result['parse_status']);
        $this->assertNull($result['ic']);
        $this->assertContains('MISSING_FIELD: ic is absent', $result['parse_errors']);
    }

    /**
     * Test 9: Missing Voltage
     */
    public function testMissingVoltage(): void
    {
        $raw = "FAULT: L2-G\nIa: 0.15kA Ib: 2.35kA Ic: 0.12kA\nDist: 1.2km\nZ: 0.38Ω\nTime: 15:32 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('PARTIAL', $result['parse_status']);
        $this->assertNull($result['voltage']);
        $this->assertContains('MISSING_FIELD: voltage is absent', $result['parse_errors']);
    }

    /**
     * Test 10: Missing Distance
     */
    public function testMissingDistance(): void
    {
        $raw = "FAULT: L2-G\nIa: 0.15kA Ib: 2.35kA Ic: 0.12kA\nV: 0.42kV\nZ: 0.38Ω\nTime: 15:32 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('PARTIAL', $result['parse_status']);
        $this->assertNull($result['distance']);
        $this->assertNull($result['distance_unit']);
        $this->assertContains('MISSING_FIELD: distance is absent', $result['parse_errors']);
    }

    /**
     * Test 11: Missing Impedance
     */
    public function testMissingImpedance(): void
    {
        $raw = "FAULT: L2-G\nIa: 0.15kA Ib: 2.35kA Ic: 0.12kA\nV: 0.42kV\nDist: 1.2km\nTime: 15:32 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('PARTIAL', $result['parse_status']);
        $this->assertNull($result['impedance']);
        $this->assertNull($result['impedance_unit']);
        $this->assertContains('MISSING_FIELD: impedance is absent', $result['parse_errors']);
    }

    /**
     * Test 12: Malformed Numeric Value
     */
    public function testMalformedNumericValue(): void
    {
        $raw = "FAULT: L2-G\nIa: abc\nIb: 2.35kA\nIc: 0.12kA\nV: 0.42kV\nDist: 1..2km\nZ: 0.38Ω\nTime: 15:32 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('MALFORMED_SYNTAX', $result['parse_status']);
        $errorStr = implode(' ', $result['parse_errors']);
        $this->assertStringContainsString('MALFORMED_NUMERIC_VALUE', $errorStr);
    }

    /**
     * Test 13: Unsupported Unit (miles, Henry)
     */
    public function testUnsupportedUnit(): void
    {
        $raw = "FAULT: L2-G\nIa: 0.15kA\nIb: 2.35kA\nIc: 0.12kA\nV: 0.42kV\nDist: 1.2 miles\nZ: 0.38 Henry\nTime: 15:32 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('MALFORMED_SYNTAX', $result['parse_status']);
        $errorStr = implode(' ', $result['parse_errors']);
        $this->assertStringContainsString('UNSUPPORTED_UNIT', $errorStr);
        $this->assertStringContainsString('miles', $errorStr);
        $this->assertStringContainsString('Henry', $errorStr);
    }

    /**
     * Test 14: Missing Required Unit
     */
    public function testMissingRequiredUnit(): void
    {
        $raw = "FAULT: L2-G\nIa: 0.15\nIb: 2.35kA\nIc: 0.12kA\nV: 0.42kV\nDist: 1.2\nZ: 0.38\nTime: 15:32 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('MALFORMED_SYNTAX', $result['parse_status']);
        $errorStr = implode(' ', $result['parse_errors']);
        $this->assertStringContainsString('MISSING_UNIT', $errorStr);
    }

    /**
     * Test 15: Malformed Time
     */
    public function testMalformedTime(): void
    {
        $raw = "FAULT: L2-G\nIa: 0.15kA\nIb: 2.35kA\nIc: 0.12kA\nV: 0.42kV\nDist: 1.2km\nZ: 0.38Ω\nTime: 25:70 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('MALFORMED_SYNTAX', $result['parse_status']);
        $errorStr = implode(' ', $result['parse_errors']);
        $this->assertStringContainsString('MALFORMED_TIME', $errorStr);
    }

    /**
     * Test 16: Payload with Date and Seconds
     */
    public function testPayloadWithDateAndSeconds(): void
    {
        $raw = "FAULT: L2-G\nIa: 0.15kA Ib: 2.35kA Ic: 0.12kA\nV: 0.42kV\nDist: 1.2km\nZ: 0.38Ω\nTime: 2026-09-18 15:32:45 WITA";

        $result = $this->parser->parse($raw);

        $this->assertEquals('SUCCESS', $result['parse_status']);
        $this->assertEquals('15:32:45', $result['fault_time']);
        $this->assertEquals('2026-09-18', $result['date']);
        $this->assertEquals('WITA', $result['timezone']);
    }

    /**
     * Test 17: Unknown Field Preserves Valid Fields
     */
    public function testUnknownFieldPreservesValidFields(): void
    {
        $raw = "FAULT: L2-G\nIa: 0.15kA Ib: 2.35kA Ic: 0.12kA\nV: 0.42kV\nDist: 1.2km\nZ: 0.38Ω\nTime: 15:32 WIB\nLocation: Surabaya Substation";

        $result = $this->parser->parse($raw);

        $this->assertEquals('SUCCESS', $result['parse_status']);
        $this->assertEquals('L2-G', $result['fault_type']);
        $this->assertEquals(1.2, $result['distance']);
        $this->assertEquals('15:32', $result['fault_time']);
    }

    /**
     * Test 18: Empty Payload
     */
    public function testEmptyPayload(): void
    {
        $result = $this->parser->parse("   \n\t  ");

        $this->assertEquals('EMPTY_PAYLOAD', $result['parse_status']);
        $this->assertEmpty($result['parsed_fields']);
        $this->assertNotEmpty($result['parse_errors']);
    }

    /**
     * Test 19: Completely Unrelated Text
     */
    public function testCompletelyUnrelatedText(): void
    {
        $result = $this->parser->parse("Halo bro, selamat pagi ini bukan laporan telemetry FTU sama sekali.");

        $this->assertEquals('UNRECOGNIZED_FORMAT', $result['parse_status']);
        $this->assertEmpty($result['parsed_fields']);
        $this->assertNotEmpty($result['parse_errors']);
    }

    /**
     * Test 20: Duplicate Field with Identical Value
     */
    public function testIdenticalDuplicateField(): void
    {
        $raw = "FAULT: L2-G\nIa: 0.15kA\nIb: 2.35kA\nIc: 0.12kA\nV: 0.42kV\nDist: 1.2km\nDist: 1.2km\nZ: 0.38Ω\nTime: 15:32 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('SUCCESS', $result['parse_status']);
        $this->assertEquals(1.2, $result['distance']);
    }

    /**
     * Test 21: Conflicting Duplicate Field
     */
    public function testConflictingDuplicateField(): void
    {
        $raw = "FAULT: L2-G\nIa: 0.15kA\nIb: 2.35kA\nIc: 0.12kA\nV: 0.42kV\nDist: 1.2km\nDist: 3.5km\nZ: 0.38Ω\nTime: 15:32 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('MALFORMED_SYNTAX', $result['parse_status']);
        $errorStr = implode(' ', $result['parse_errors']);
        $this->assertStringContainsString('CONFLICTING_FIELD', $errorStr);
    }

    /**
     * Test 22: Decimal Formatting Variant (Comma Decimal)
     */
    public function testDecimalFormattingVariant(): void
    {
        $raw = "FAULT: L2-G\nIa: 0,15kA Ib: 2,35kA Ic: 0,12kA\nV: 0,42kV\nDist: 1,2km\nZ: 0,38Ω\nTime: 15:32 WIB";

        $result = $this->parser->parse($raw);

        $this->assertEquals('SUCCESS', $result['parse_status']);
        $this->assertEquals(0.15, $result['ia']);
        $this->assertEquals(2.35, $result['ib']);
        $this->assertEquals(0.12, $result['ic']);
        $this->assertEquals(0.42, $result['voltage']);
        $this->assertEquals(1.2, $result['distance']);
        $this->assertEquals(0.38, $result['impedance']);
    }

    /**
     * Test 23: Deterministic Output Across Multiple Runs
     */
    public function testDeterministicOutputAcrossRuns(): void
    {
        $raw = "FAULT: L1-G\nIa: 1.55kA Ib: 0.10kA Ic: 0.08kA\nV: 0.40kV\nDist: 2.45km\nZ: 0.62Ω\nTime: 10:15 WIB";

        $run1 = $this->parser->parse($raw);
        $run2 = $this->parser->parse($raw);

        $this->assertEquals(json_encode($run1), json_encode($run2), 'Parser output must be 100% deterministic and bit-for-bit identical across runs');
    }

    /**
     * Test 24: Database Zero-Mutation Firewall
     */
    public function testDatabaseMutationFirewallZeroWrite(): void
    {
        $db = \Config\Database::connect('default');

        $assetsBefore     = $db->table('assets')->countAllResults();
        $translinesBefore = $db->table('gis_translines')->countAllResults();
        $temuanBefore     = $db->table('temuan')->countAllResults();

        // Run parser multiple times with different payloads
        $this->parser->parse("FAULT: L2-G\nIa: 0.15kA Ib: 2.35kA Ic: 0.12kA\nV: 0.42kV\nDist: 1.2km\nZ: 0.38Ω\nTime: 15:32 WIB");
        $this->parser->parse("MALFORMED TEXT TEST");
        $this->parser->parse("");

        $assetsAfter     = $db->table('assets')->countAllResults();
        $translinesAfter = $db->table('gis_translines')->countAllResults();
        $temuanAfter     = $db->table('temuan')->countAllResults();

        $this->assertEquals($assetsBefore, $assetsAfter, 'Delta assets must be 0');
        $this->assertEquals($translinesBefore, $translinesAfter, 'Delta translines must be 0');
        $this->assertEquals($temuanBefore, $temuanAfter, 'Delta temuan must be 0');
        $this->assertFalse($db->tableExists('fault_records'), 'Table fault_records must NOT exist during FL-01B');
    }
}
