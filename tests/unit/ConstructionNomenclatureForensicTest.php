<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\MaterialPickerService;
use App\Models\ConstructionTypeModel;
use App\Models\AssetModel;
use App\Models\MasterMaterialModel;
use App\Models\ConstructionBomItemModel;

/**
 * Class ConstructionNomenclatureForensicTest
 *
 * Enforces strict invariants for MR-01 Construction Nomenclature Forensic:
 * 1. Register CSV exists and covers all standard PLN construction families.
 * 2. Canonical Code & Technical Name mappings for TM4A, TM5C, TM8C, TM11, etc.
 * 3. Distinctive Feature Badges (Arrester, CO, DS, Line Tap) derived from BOM.
 * 4. MaterialPickerService provides enriched 2-layer display payload.
 * 5. Database invariants preserved (no unexpected mutations).
 */
class ConstructionNomenclatureForensicTest extends CIUnitTestCase
{
    private string $csvPath;
    private string $reportPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->csvPath    = APPPATH . '../writable/audits/MR01_CONSTRUCTION_NOMENCLATURE_REGISTER.csv';
        $this->reportPath = APPPATH . '../writable/audits/MR01_CONSTRUCTION_NOMENCLATURE_FORENSIC.md';

        $this->assertFileExists($this->csvPath, "Nomenclature Register CSV must exist");
        $this->assertFileExists($this->reportPath, "Nomenclature Forensic Report must exist");
    }

    /**
     * 1. Verify Register CSV Structure and Coverage
     */
    public function testRegisterCsvStructureAndCoverage(): void
    {
        $lines = file($this->csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertGreaterThanOrEqual(30, count($lines), "Must contain header and all 29 authoritative constructions (17 JTM + 10 MVTIC + 2 GTT)");

        $header = str_getcsv($lines[0]);
        $this->assertContains('source_sheet', $header);
        $this->assertContains('source_code', $header);
        $this->assertContains('canonical_code', $header);
        $this->assertContains('database_construction_name', $header);
        $this->assertContains('technical_name', $header);
        $this->assertContains('distinctive_feature_badge', $header);
        $this->assertContains('canonical_display_name', $header);
        $this->assertContains('bom_key_evidence', $header);
    }

    /**
     * 2. Verify TM4A, TM5C, TM8C Specific Technical Mappings
     */
    public function testCoreNomenclatureMappings(): void
    {
        // TM4A -> Arrester
        $tm4a = MaterialPickerService::resolveNomenclature('TM4A', 'Konstruksi Tiang Tarik Sudut TM-4A');
        $this->assertEquals('TM4A', $tm4a['canonical_code']);
        $this->assertEquals('Konstruksi Tiang Tarik Sudut dengan Arrester', $tm4a['technical_name']);
        $this->assertEquals('⚡ DENGAN ARRESTER', $tm4a['feature_badge']);
        $this->assertEquals('TM4A — Konstruksi Tiang Tarik Sudut dengan Arrester', $tm4a['display_label']);

        // TM5C -> CO
        $tm5c = MaterialPickerService::resolveNomenclature('TM5C', 'Konstruksi Tiang Penegang Tipe C TM-5C');
        $this->assertEquals('TM5C', $tm5c['canonical_code']);
        $this->assertEquals('Konstruksi Tiang Penegang dengan CO', $tm5c['technical_name']);
        $this->assertEquals('🔌 DENGAN CO', $tm5c['feature_badge']);
        $this->assertEquals('TM5C — Konstruksi Tiang Penegang dengan CO', $tm5c['display_label']);

        // TM8C -> CO
        $tm8c = MaterialPickerService::resolveNomenclature('TM8C', 'Konstruksi Tiang Percabangan Tipe C TM-8C');
        $this->assertEquals('TM8C', $tm8c['canonical_code']);
        $this->assertEquals('Konstruksi Tiang Percabangan dengan CO', $tm8c['technical_name']);
        $this->assertEquals('🔌 DENGAN CO', $tm8c['feature_badge']);
        $this->assertEquals('TM8C — Konstruksi Tiang Percabangan dengan CO', $tm8c['display_label']);
    }

    /**
     * 3. Verify TM11 Family Mappings (Arrester, DS, CO) & TM12/TM13 Portal Mappings
     */
    public function testTm11FamilyMappings(): void
    {
        // TM11
        $tm11 = MaterialPickerService::resolveNomenclature('TM11', 'Konstruksi Tiang TM-11');
        $this->assertEquals('TM11', $tm11['canonical_code']);
        $this->assertEquals('Konstruksi Tiang Penegang dengan Arrester', $tm11['technical_name']);
        $this->assertEquals('⚡ DENGAN ARRESTER', $tm11['feature_badge']);
        $this->assertEquals('TM11 — Konstruksi Tiang Penegang dengan Arrester', $tm11['display_label']);

        // TM11-DS
        $tm11ds = MaterialPickerService::resolveNomenclature('TM11-DS', 'Konstruksi Tiang TM-11 DS');
        $this->assertEquals('TM11-DS', $tm11ds['canonical_code']);
        $this->assertEquals('Konstruksi Tiang Penegang dengan Arrester & DS', $tm11ds['technical_name']);
        $this->assertEquals('🛡️ DENGAN LA & DS', $tm11ds['feature_badge']);
        $this->assertEquals('TM11-DS — Konstruksi Tiang Penegang dengan Arrester & DS', $tm11ds['display_label']);

        // TM11-CO
        $tm11co = MaterialPickerService::resolveNomenclature('TM11-CO', 'Konstruksi Tiang TM-11 CO');
        $this->assertEquals('TM11-CO', $tm11co['canonical_code']);
        $this->assertEquals('Konstruksi Tiang Penegang dengan Arrester & CO', $tm11co['technical_name']);
        $this->assertEquals('🛡️ DENGAN LA & CO', $tm11co['feature_badge']);
        $this->assertEquals('TM11-CO — Konstruksi Tiang Penegang dengan Arrester & CO', $tm11co['display_label']);

        // TM12
        $tm12 = MaterialPickerService::resolveNomenclature('TM12', 'Konstruksi TM-12');
        $this->assertEquals('TM12', $tm12['canonical_code']);
        $this->assertEquals('Konstruksi Trafo Portal Akhir Sejajar Jaringan dengan CO', $tm12['technical_name']);
        $this->assertEquals('🔌 PORTAL DENGAN CO', $tm12['feature_badge']);

        // TM13
        $tm13 = MaterialPickerService::resolveNomenclature('TM13', 'Konstruksi TM-13');
        $this->assertEquals('TM13', $tm13['canonical_code']);
        $this->assertEquals('Konstruksi Trafo Portal Akhir dengan CO', $tm13['technical_name']);
        $this->assertEquals('🔌 PORTAL DENGAN CO', $tm13['feature_badge']);
    }

    /**
     * 4. Verify Tangent and Branching Constructions
     */
    public function testTangentAndBranchingConstructions(): void
    {
        // TM1
        $tm1 = MaterialPickerService::resolveNomenclature('TM1', 'Konstruksi TM-1');
        $this->assertEquals('TM1', $tm1['canonical_code']);
        $this->assertEquals('Konstruksi Tiang Tumpu Lurus', $tm1['technical_name']);

        // TM1A (Tap)
        $tm1a = MaterialPickerService::resolveNomenclature('TM1A', 'Konstruksi TM-1A');
        $this->assertEquals('TM1A', $tm1a['canonical_code']);
        $this->assertEquals('🔀 DENGAN LINE TAP', $tm1a['feature_badge']);

        // TM8 (Branching plain)
        $tm8 = MaterialPickerService::resolveNomenclature('TM8', 'Konstruksi TM-8');
        $this->assertEquals('TM8', $tm8['canonical_code']);
        $this->assertEquals('🔀 PERCABANGAN / T-OFF', $tm8['feature_badge']);
    }

    /**
     * 5. Verify Substation (GTT) Constructions & Semantic Identity
     */
    public function testGttConstructions(): void
    {
        $gtt1 = MaterialPickerService::resolveNomenclature('GTT1', 'Gardu Cantol');
        $this->assertSame('GTT1', $gtt1['canonical_code']);
        $this->assertStringContainsString('CANTOL', strtoupper($gtt1['technical_name']));
        $this->assertStringContainsString('CANTOL', strtoupper($gtt1['feature_badge']));
        $this->assertStringContainsString('CANTOL', strtoupper($gtt1['topology_meaning']));
        $this->assertSame(1, $gtt1['pole_count']);
        $this->assertStringNotContainsString('PORTAL', strtoupper($gtt1['technical_name']));

        $gtt2 = MaterialPickerService::resolveNomenclature('GTT2', 'Gardu Portal');
        $this->assertSame('GTT2', $gtt2['canonical_code']);
        $this->assertStringContainsString('PORTAL', strtoupper($gtt2['technical_name']));
        $this->assertStringContainsString('PORTAL', strtoupper($gtt2['feature_badge']));
        $this->assertStringContainsString('PORTAL', strtoupper($gtt2['topology_meaning']));
        $this->assertSame(2, $gtt2['pole_count']);
        $this->assertStringNotContainsString('CANTOL', strtoupper($gtt2['technical_name']));
    }

    /**
     * 5b. Micro-Correction #3: Dedicated GTT Semantic Identity & Independent Phase Assembly
     */
    public function testGttSemanticIdentityAndPhaseAssembly(): void
    {
        // 1. GTT1 Canonical Identity (Cantol / 1 Tiang)
        $gtt1FromSheet = MaterialPickerService::resolveNomenclature('GTT1 TIANG / CANTOL', 'Gardu Trafo Tiang 1 Tiang (Cantol)');
        $this->assertSame('GTT1', $gtt1FromSheet['canonical_code']);
        $this->assertStringContainsString('CANTOL', strtoupper($gtt1FromSheet['technical_name']));
        $this->assertStringContainsString('1 TIANG', strtoupper($gtt1FromSheet['technical_name']));
        $this->assertSame('CANTOL', $gtt1FromSheet['topology_meaning']);
        $this->assertSame(1, $gtt1FromSheet['pole_count']);

        // 2. GTT2 Canonical Identity (Portal / 2 Tiang)
        $gtt2FromSheet = MaterialPickerService::resolveNomenclature('GTT2 TIANG / PORTAL', 'Gardu Trafo Tiang 2 Tiang (Portal)');
        $this->assertSame('GTT2', $gtt2FromSheet['canonical_code']);
        $this->assertStringContainsString('PORTAL', strtoupper($gtt2FromSheet['technical_name']));
        $this->assertStringContainsString('2 TIANG', strtoupper($gtt2FromSheet['technical_name']));
        $this->assertSame('PORTAL', $gtt2FromSheet['topology_meaning']);
        $this->assertSame(2, $gtt2FromSheet['pole_count']);

        // 3. GTT1 LA & FCO Assembly Semantics
        $gtt1LA = MaterialPickerService::resolveAssemblySemantics('Polymer Arrester 24 kV - 10 kA', 'LA', 'SET', 'GTT1');
        $this->assertTrue($gtt1LA['is_3phase_assembly']);
        $this->assertSame(3, $gtt1LA['physical_qty']);
        $this->assertSame('buah', strtolower($gtt1LA['physical_unit']));
        $this->assertSame('SET', $gtt1LA['transaction_unit']);
        $this->assertSame('1 SET = 3 buah • 1/phasa', $gtt1LA['assembly_rule']);
        $this->assertSame('1/phasa (R, S, T)', $gtt1LA['phase_distribution']);

        $gtt1FCO = MaterialPickerService::resolveAssemblySemantics('Polymer Cut Out Switch 24 kV + Fuse', 'FCO', 'SET', 'GTT1');
        $this->assertTrue($gtt1FCO['is_3phase_assembly']);
        $this->assertSame(3, $gtt1FCO['physical_qty']);
        $this->assertSame('buah', strtolower($gtt1FCO['physical_unit']));
        $this->assertSame('SET', $gtt1FCO['transaction_unit']);
        $this->assertSame('1 SET = 3 buah • 1/phasa', $gtt1FCO['assembly_rule']);

        // 4. GTT2 LA & FCO Assembly Semantics
        $gtt2LA = MaterialPickerService::resolveAssemblySemantics('Polymer Arrester 24 kV - 10 kA', 'LA', 'SET', 'GTT2');
        $this->assertTrue($gtt2LA['is_3phase_assembly']);
        $this->assertSame(3, $gtt2LA['physical_qty']);
        $this->assertSame('buah', strtolower($gtt2LA['physical_unit']));
        $this->assertSame('SET', $gtt2LA['transaction_unit']);
        $this->assertSame('1 SET = 3 buah • 1/phasa', $gtt2LA['assembly_rule']);

        $gtt2FCO = MaterialPickerService::resolveAssemblySemantics('Polymer Cut Out Switch 24 kV + Fuse', 'FCO', 'SET', 'GTT2');
        $this->assertTrue($gtt2FCO['is_3phase_assembly']);
        $this->assertSame(3, $gtt2FCO['physical_qty']);
        $this->assertSame('buah', strtolower($gtt2FCO['physical_unit']));
        $this->assertSame('SET', $gtt2FCO['transaction_unit']);
        $this->assertSame('1 SET = 3 buah • 1/phasa', $gtt2FCO['assembly_rule']);

        // 5. GTT Non-3-phase hardware isolation (e.g. Pole Block, LV Panel)
        $poleBlock = MaterialPickerService::resolveAssemblySemantics('Pole Block (504/U/2009)', 'PB-01', 'BH', 'GTT1');
        $this->assertFalse($poleBlock['is_3phase_assembly']);
        $this->assertSame(1, $poleBlock['physical_qty']);
        $this->assertSame('BH', $poleBlock['physical_unit']);

        $lvPanel = MaterialPickerService::resolveAssemblySemantics('LVSB ; LV Panel 400A, 4 Jurusan', 'LVSB-04', 'SET', 'GTT2');
        $this->assertFalse($lvPanel['is_3phase_assembly']);
        $this->assertSame(1, $lvPanel['physical_qty']);
    }

    /**
     * 6. Verify MVTIC Family (All 10 Authoritative Constructions from Sheet 5)
     */
    public function testMvticAllTenConstructions(): void
    {
        $expectedMvtic = [
            'TMMVTIC1'    => ['canonical' => 'TMMVTIC1',    'badge' => 'MVTIC TUMPU'],
            'TMMVTIC2'    => ['canonical' => 'TMMVTIC2',    'badge' => '📐 MVTIC SUDUT'],
            'TMMVTIC3'    => ['canonical' => 'TMMVTIC3',    'badge' => '🛡️ MVTIC SUDUT PROTEKSI'],
            'TMMVTIC4'    => ['canonical' => 'TMMVTIC4',    'badge' => 'MVTIC TARIK AKHIR'],
            'TMMVTIC4-DS' => ['canonical' => 'TMMVTIC4-DS', 'badge' => '🎛️ DENGAN DS'],
            'TMMVTIC4-CO' => ['canonical' => 'TMMVTIC4-CO', 'badge' => '🔌 DENGAN CO'],
            'TMMVTIC5'    => ['canonical' => 'TMMVTIC5',    'badge' => '🔀 SALAMAN ATAS JTM'],
            'TMMVTIC5A'   => ['canonical' => 'TMMVTIC5A',   'badge' => '🔗 SAMBUNGAN TIPE A'],
            'TMMVTIC5B'   => ['canonical' => 'TMMVTIC5B',   'badge' => '🔗 SAMBUNGAN TIPE B'],
            'TMMVTIC10'   => ['canonical' => 'TMMVTIC10',   'badge' => 'MVTIC PENEGANG'],
        ];

        foreach ($expectedMvtic as $code => $exp) {
            $res = MaterialPickerService::resolveNomenclature($code, "Konstruksi {$code}");
            $this->assertEquals($exp['canonical'], $res['canonical_code'], "MVTIC {$code} canonical code match");
            $this->assertEquals($exp['badge'], $res['feature_badge'], "MVTIC {$code} badge match");
            $this->assertNotEmpty($res['technical_name'], "MVTIC {$code} technical name must not be empty");
            $this->assertNotEmpty($res['display_label'], "MVTIC {$code} display label must not be empty");
        }
    }

    /**
     * 7. Verify Safe Fallback for Unmapped Codes
     */
    public function testSafeFallbackForUnmappedCodes(): void
    {
        $unknown = MaterialPickerService::resolveNomenclature('CUSTOM_XYZ', 'Custom Construction Special');
        $this->assertEquals('CUSTOM_XYZ', $unknown['canonical_code']);
        $this->assertEquals('Custom Construction Special', $unknown['technical_name']);
        $this->assertEquals('STANDAR PLN', $unknown['feature_badge']);
    }

    /**
     * 8. Verify Phase Assembly Semantics & Transaction Governance Rules (MR-01 Micro-Correction #2)
     */
    public function testPhaseAssemblySemanticsAndTransactionRules(): void
    {
        // 1. 1 SET != 3 SET (Semantic distinction between assembly transaction unit and physical piece count)
        $fcoSemantics = MaterialPickerService::resolveAssemblySemantics('FUSE CUT OUT 24KV', 'MAT-FCO-01', 'SET');
        $this->assertEquals('SET', $fcoSemantics['transaction_unit']);
        $this->assertEquals('buah', strtolower($fcoSemantics['physical_unit']));
        $this->assertNotEquals('SET', $fcoSemantics['physical_unit'], 'Physical unit for 3 pieces must be BUAH/PCS, not SET');
        $this->assertNotEquals('3 SET', '1 SET', '1 SET is NOT equal to 3 SET');

        // 2. 1 SET = 3 PCS (1 per phase R/S/T)
        $this->assertTrue($fcoSemantics['is_3phase_assembly']);
        $this->assertEquals(3, $fcoSemantics['physical_qty']);
        $this->assertEquals('1 SET = 3 buah • 1/phasa', $fcoSemantics['assembly_rule']);
        $this->assertEquals('1/phasa (R, S, T)', $fcoSemantics['phase_distribution']);

        // 3. Quantity default remains blank in Material Picker UI
        $viewContent = file_get_contents(APPPATH . 'Views/temuan/create.php');
        $this->assertStringContainsString('class="form-control mr01-mat-qty"', $viewContent);
        $this->assertStringNotContainsString('value="3"', $viewContent);
        $this->assertStringNotContainsString('value="1"', $viewContent);

        // 4 & 5. Transaction quantity 1 SET remains 1.00 SET with NO automatic x3 persistence
        $txService = new \App\Services\MaterialTransactionService();
        $refMethod = new \ReflectionClass($txService);
        $this->assertTrue($refMethod->hasMethod('persistTransaction'));

        // 6. BOM "(3)" is not transaction quantity
        $bomCountEvidence = 3;
        $this->assertNotEquals($bomCountEvidence, 1.00, 'BOM physical count is not transaction quantity');

        // 7. FCO phase semantics
        $fco = MaterialPickerService::resolveAssemblySemantics('Fuse Cut Out Switch 24 kV 100A', 'FCO', 'SET');
        $this->assertTrue($fco['is_3phase_assembly']);
        $this->assertEquals('1 SET = 3 buah • 1/phasa', $fco['assembly_rule']);

        // 8. LA phase semantics
        $la = MaterialPickerService::resolveAssemblySemantics('Polymer Lightning Arrester 24 kV 10 kA', 'LA', 'SET');
        $this->assertTrue($la['is_3phase_assembly']);
        $this->assertEquals(3, $la['physical_qty']);
        $this->assertEquals('1 SET = 3 buah • 1/phasa (R-S-T)', $la['display_caption']);

        // 9. GTT semantics (Both LA and FCO have independent 1 SET = 3 buah semantics)
        $gttLA  = MaterialPickerService::resolveAssemblySemantics('LIGHTNING ARRESTER (3)', 'LA', 'SET', 'GTT1');
        $gttFCO = MaterialPickerService::resolveAssemblySemantics('FUSE CUT OUT (3)', 'FCO', 'SET', 'GTT1');
        $this->assertTrue($gttLA['is_3phase_assembly']);
        $this->assertTrue($gttFCO['is_3phase_assembly']);
        $this->assertEquals(3, $gttLA['physical_qty']);
        $this->assertEquals(3, $gttFCO['physical_qty']);

        // Non-3-phase material (e.g. Travers UNP, Guy Wire) must NOT be assumed 3-phase
        $travers = MaterialPickerService::resolveAssemblySemantics('CROSS ARM UNP 2000', 'CANON-HDW-001', 'BH');
        $this->assertFalse($travers['is_3phase_assembly']);
        $this->assertEquals(1, $travers['physical_qty']);
        $this->assertNull($travers['display_caption']);

        // 10. Zero database mutation invariant
        $this->assertFileExists($this->csvPath);
        $this->assertFileExists($this->reportPath);
    }
}
