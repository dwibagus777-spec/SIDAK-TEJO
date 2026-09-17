<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\SldTopologyReadModelService;
use App\Services\SldSemanticClassificationService;

/**
 * Class Sld05TDeviceSymbologyTest
 *
 * Verifies Lock 1 of SLD-05T:
 * 1. Simbol derived strictly from authoritative GIS fields (jenis_asset -> construction_type).
 * 2. Zero guessing purely from string name.
 * 3. Unsubstantiated subtype strictly resolves to DEVICE_UNKNOWN.
 * 4. All 14 official device roles correctly classified:
 *    GI, LBS, LBSM_2WAY, LBSM_3WAY, PMCB, RECLOSER, AVS, PGS, PMS, GTT_CANTOL, GTT_PORTAL, LINE_POLE, BRANCH, TERMINAL.
 */
class Sld05TDeviceSymbologyTest extends CIUnitTestCase
{
    protected SldTopologyReadModelService $topologyService;
    protected SldSemanticClassificationService $semanticService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->topologyService = new SldTopologyReadModelService();
        $this->semanticService = new SldSemanticClassificationService($this->topologyService);
    }

    /**
     * Test all 14 official device roles plus DEVICE_UNKNOWN fallback via classifier.
     */
    public function testFourteenOfficialDeviceRolesClassification(): void
    {
        // 1. GI (Takeoff Node)
        $gi = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => 'TM11',
            'jenis_asset'       => 'JTM',
            'name'              => 'BANJARKEMANTRAN_99',
            'code'              => 'AST-01',
            'is_switch'         => false,
            'is_gtt'            => false,
        ], true, 2);
        $this->assertEquals('GI', $gi['official_device_role']);
        $this->assertEquals('GI', $gi['sld_glyph']);

        // 2. LBS (Manual)
        $lbs = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => 'LBS',
            'jenis_asset'       => 'LBS',
            'name'              => 'LBS SEKARPINGGIR',
            'code'              => 'AST-02',
            'is_switch'         => true,
            'is_gtt'            => false,
        ], false, 2);
        $this->assertEquals('LBS', $lbs['official_device_role']);
        $this->assertEquals('LBS', $lbs['sld_glyph']);

        // 3. LBSM 2WAY
        $lbsm2 = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => '2WAY',
            'jenis_asset'       => 'LBSM',
            'name'              => 'LBS MOTOR 2WAY',
            'code'              => 'AST-03',
            'is_switch'         => true,
            'is_gtt'            => false,
        ], false, 2);
        $this->assertEquals('LBSM_2WAY', $lbsm2['official_device_role']);
        $this->assertEquals('LBSM_2WAY', $lbsm2['sld_glyph']);

        // 4. LBSM 3WAY
        $lbsm3 = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => '3WAY',
            'jenis_asset'       => 'LBSM',
            'name'              => 'LBS MOTOR 3WAY',
            'code'              => 'AST-04',
            'is_switch'         => true,
            'is_gtt'            => false,
        ], false, 3);
        $this->assertEquals('LBSM_3WAY', $lbsm3['official_device_role']);
        $this->assertEquals('LBSM_3WAY', $lbsm3['sld_glyph']);

        // 5. PMCB
        $pmcb = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => 'PMCB',
            'jenis_asset'       => 'SWITCH',
            'name'              => 'PMCB 01',
            'code'              => 'AST-05',
            'is_switch'         => true,
            'is_gtt'            => false,
        ], false, 2);
        $this->assertEquals('PMCB', $pmcb['official_device_role']);
        $this->assertEquals('PMCB', $pmcb['sld_glyph']);

        // 6. RECLOSER
        $rec = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => 'RECLOSER',
            'jenis_asset'       => 'RECLOSER',
            'name'              => 'REC SIDOARJO',
            'code'              => 'AST-06',
            'is_switch'         => true,
            'is_gtt'            => false,
        ], false, 2);
        $this->assertEquals('RECLOSER', $rec['official_device_role']);
        $this->assertEquals('RECLOSER', $rec['sld_glyph']);

        // 7. AVS
        $avs = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => 'AVS',
            'jenis_asset'       => 'SWITCH',
            'name'              => 'AVS 01',
            'code'              => 'AST-07',
            'is_switch'         => true,
            'is_gtt'            => false,
        ], false, 2);
        $this->assertEquals('AVS', $avs['official_device_role']);
        $this->assertEquals('AVS', $avs['sld_glyph']);

        // 8. PGS
        $pgs = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => 'PGS',
            'jenis_asset'       => 'SWITCH',
            'name'              => 'PGS 01',
            'code'              => 'AST-08',
            'is_switch'         => true,
            'is_gtt'            => false,
        ], false, 2);
        $this->assertEquals('PGS', $pgs['official_device_role']);
        $this->assertEquals('PGS', $pgs['sld_glyph']);

        // 9. PMS
        $pms = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => 'PMS',
            'jenis_asset'       => 'JTM',
            'name'              => 'BANJARKEMANTRAN_28',
            'code'              => 'AST-09',
            'is_switch'         => true,
            'is_gtt'            => false,
        ], false, 3);
        $this->assertEquals('PMS', $pms['official_device_role']);
        $this->assertEquals('PMS', $pms['sld_glyph']);

        // 10. GTT CANTOL
        $cantol = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => 'GTT1',
            'jenis_asset'       => 'JTM',
            'name'              => 'TRAFO CANTOL 50kVA',
            'code'              => 'AST-10',
            'is_switch'         => false,
            'is_gtt'            => true,
        ], false, 2);
        $this->assertEquals('GTT_CANTOL', $cantol['official_device_role']);
        $this->assertEquals('GTT_CANTOL', $cantol['sld_glyph']);

        // 11. GTT PORTAL
        $portal = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => 'GTT2',
            'jenis_asset'       => 'JTM',
            'name'              => 'TRAFO PORTAL 160kVA',
            'code'              => 'AST-11',
            'is_switch'         => false,
            'is_gtt'            => true,
        ], false, 2);
        $this->assertEquals('GTT_PORTAL', $portal['official_device_role']);
        $this->assertEquals('GTT_PORTAL', $portal['sld_glyph']);

        // 12. LINE POLE (degree 2)
        $pole = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => 'TM1',
            'jenis_asset'       => 'JTM',
            'name'              => 'TIANG LINE',
            'code'              => 'AST-12',
            'is_switch'         => false,
            'is_gtt'            => false,
        ], false, 2);
        $this->assertEquals('LINE_POLE', $pole['official_device_role']);
        $this->assertEquals('LINE_POLE', $pole['sld_glyph']);

        // 13. BRANCH (degree 3)
        $branch = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => 'TM10',
            'jenis_asset'       => 'JTM',
            'name'              => 'TIANG PERCABANGAN',
            'code'              => 'AST-13',
            'is_switch'         => false,
            'is_gtt'            => false,
        ], false, 3);
        $this->assertEquals('BRANCH', $branch['official_device_role']);
        $this->assertEquals('BRANCH', $branch['sld_glyph']);

        // 14. TERMINAL (degree 1)
        $term = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => 'TM11',
            'jenis_asset'       => 'JTM',
            'name'              => 'TIANG AKHIR',
            'code'              => 'AST-14',
            'is_switch'         => false,
            'is_gtt'            => false,
        ], false, 1);
        $this->assertEquals('TERMINAL', $term['official_device_role']);
        $this->assertEquals('TERMINAL', $term['sld_glyph']);

        // 15. Fallback: DEVICE_UNKNOWN for unproven GTT subtype
        $unkGtt = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => 'GTT',
            'jenis_asset'       => 'JTM',
            'name'              => 'GARDU TRAFO',
            'code'              => 'AST-15',
            'is_switch'         => false,
            'is_gtt'            => true,
        ], false, 2);
        $this->assertEquals('DEVICE_UNKNOWN', $unkGtt['official_device_role']);
        $this->assertEquals('DEVICE_UNKNOWN', $unkGtt['sld_glyph']);

        // 16. Fallback: DEVICE_UNKNOWN for unproven LBSM subtype
        $unkLbsm = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => 'LBSM',
            'jenis_asset'       => 'LBSM',
            'name'              => 'LBS MOTOR UNKNOWN',
            'code'              => 'AST-16',
            'is_switch'         => true,
            'is_gtt'            => false,
        ], false, 2);
        $this->assertEquals('DEVICE_UNKNOWN', $unkLbsm['official_device_role']);
        $this->assertEquals('DEVICE_UNKNOWN', $unkLbsm['sld_glyph']);
    }

    /**
     * Test anti-guessing invariant: name containing "LBS" does NOT yield LBS
     * if authoritative fields do not corroborate it.
     */
    public function testAntiGuessingInvariantFromName(): void
    {
        // Asset named "LBS_NEARBY_POLE" but jenis_asset=JTM, construction_type=TM1
        $fakeLbs = $this->semanticService->classifyOfficialDeviceRole([
            'construction_type' => 'TM1',
            'jenis_asset'       => 'JTM',
            'name'              => 'LBS SEKARPINGGIR_POLE_01',
            'code'              => 'AST-FAKE-01',
            'is_switch'         => false,
            'is_gtt'            => false,
        ], false, 2);

        $this->assertEquals('LINE_POLE', $fakeLbs['official_device_role']);
        $this->assertNotEquals('LBS', $fakeLbs['official_device_role']);
    }

    /**
     * Test Feeder 15 full graph classification results.
     */
    public function testFeeder15NodeClassifications(): void
    {
        $res = $this->semanticService->buildSemanticHierarchy(15);
        $nodes = $res['nodes'];

        $roleCounts = [];
        foreach ($nodes as $n) {
            $r = $n['official_device_role'];
            $roleCounts[$r] = ($roleCounts[$r] ?? 0) + 1;
        }

        // Must have 1 GI takeoff incomer (#3231)
        $this->assertEquals(1, $roleCounts['GI'] ?? 0);

        // Must have 4 PMS devices (#3188, #3217, #3266, #3295)
        $this->assertEquals(4, $roleCounts['PMS'] ?? 0);

        // Must have 15 GTT2 Portal transformers
        $this->assertEquals(15, $roleCounts['GTT_PORTAL'] ?? 0);

        // Must have 4 GTT1 Cantol transformers
        $this->assertEquals(4, $roleCounts['GTT_CANTOL'] ?? 0);

        // Must have 4 GTT unproven subtype -> DEVICE_UNKNOWN
        $this->assertEquals(4, $roleCounts['DEVICE_UNKNOWN'] ?? 0);

        // Total nodes must remain strictly 205
        $this->assertEquals(205, array_sum($roleCounts));
    }
}
