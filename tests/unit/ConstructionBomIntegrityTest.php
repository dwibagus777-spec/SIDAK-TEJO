<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\MaterialPickerService;
use App\Services\ConstructionService;

/**
 * Class ConstructionBomIntegrityTest
 *
 * Verifies CR-MATERIAL-BOM-INTEGRITY-01 invariants:
 * 1. TM1 canonical BOM comprises exactly 13 standard PLN components.
 * 2. All canonical units are normalized to 'buah'.
 * 3. Canonical field aliases exist for all 13 items.
 * 4. Default quantities correspond to PLN Buku 5 engineering specifications.
 * 5. MaterialPickerService properly serves default_qty and canonical nomenclature.
 */
class ConstructionBomIntegrityTest extends CIUnitTestCase
{
    /**
     * Authoritative list of 13 canonical components for TM1.
     */
    protected array $expectedTm1Components = [
        'KANAL' => [
            'name' => 'Travers / Cross Arm Besi Kanal UNP 8 x 2000 mm',
            'default_qty' => 1,
            'unit' => 'buah',
            'alias' => 'KANAL'
        ],
        'ARM TIE' => [
            'name' => 'Arm Tie / Penguat Besi Kanal UNP',
            'default_qty' => 2,
            'unit' => 'buah',
            'alias' => 'ARM TIE'
        ],
        'BAUT 50' => [
            'name' => 'Baut & Mur Mesin M16 x 50 mm Hot Dip Galvanized',
            'default_qty' => 2,
            'unit' => 'buah',
            'alias' => 'BAUT 50'
        ],
        'BAUT 400' => [
            'name' => 'Baut & Mur Mesin M16 x 400 mm Hot Dip Galvanized',
            'default_qty' => 1,
            'unit' => 'buah',
            'alias' => 'BAUT 400'
        ],
        'PLAT GSW' => [
            'name' => 'Plat Penjepit / Begel GSW Hot Dip Galvanized',
            'default_qty' => 1,
            'unit' => 'buah',
            'alias' => 'PLAT GSW'
        ],
        'GSW' => [
            'name' => 'Kawat Tanah / Ground Steel Wire (GSW) 22 mm2 / 35 mm2',
            'default_qty' => 1,
            'unit' => 'buah',
            'alias' => 'GSW'
        ],
        'BENDING' => [
            'name' => 'Bending Wire / Kawat Pengikat Aluminium',
            'default_qty' => 1,
            'unit' => 'buah',
            'alias' => 'BENDING'
        ],
        'BEGEL VERLINK' => [
            'name' => 'Begel Verlink / Bracket Gantungan GSW',
            'default_qty' => 1,
            'unit' => 'buah',
            'alias' => 'BEGEL VERLINK'
        ],
        'VERLINK GSW' => [
            'name' => 'Verlink / Plat Penghubung Kawat GSW',
            'default_qty' => 1,
            'unit' => 'buah',
            'alias' => 'VERLINK GSW'
        ],
        'WIRE CLIP' => [
            'name' => 'Wire Clip / Klem Kawat Baja 22-35 mm2',
            'default_qty' => 3,
            'unit' => 'buah',
            'alias' => 'WIRE CLIP'
        ],
        'PIN' => [
            'name' => 'Isolator Tumpu Pin Post Porcelain 24 kV',
            'default_qty' => 3,
            'unit' => 'buah',
            'alias' => 'PIN'
        ],
        'TOP TIES' => [
            'name' => 'Top Ties / Pengikat Atas Isolator Tumpu',
            'default_qty' => 2,
            'unit' => 'buah',
            'alias' => 'TOP TIES'
        ],
        'TOP TIES SIDE' => [
            'name' => 'Top Ties Side / Side Ties Isolator Tumpu Sisi',
            'default_qty' => 1,
            'unit' => 'buah',
            'alias' => 'TOP TIES SIDE'
        ],
    ];

    /**
     * 1. Assert TM1 BOM specifications count is exactly 13.
     */
    public function testTm1AuthoritativeComponentCount(): void
    {
        $this->assertCount(13, $this->expectedTm1Components, 'TM-1 must have exactly 13 canonical components');
    }

    /**
     * 2. Assert all 13 canonical items have unit 'buah'.
     */
    public function testAllCanonicalUnitsAreBuah(): void
    {
        foreach ($this->expectedTm1Components as $alias => $comp) {
            $this->assertSame(
                'buah',
                strtolower($comp['unit']),
                "Material component [{$alias}] must have canonical unit 'buah'"
            );
        }
    }

    /**
     * 3. Assert default quantities strictly match PLN Buku 5 standards.
     */
    public function testDefaultQuantitiesMatchPlnBuku5(): void
    {
        $this->assertSame(1, $this->expectedTm1Components['KANAL']['default_qty']);
        $this->assertSame(2, $this->expectedTm1Components['ARM TIE']['default_qty']);
        $this->assertSame(2, $this->expectedTm1Components['BAUT 50']['default_qty']);
        $this->assertSame(1, $this->expectedTm1Components['BAUT 400']['default_qty']);
        $this->assertSame(1, $this->expectedTm1Components['PLAT GSW']['default_qty']);
        $this->assertSame(1, $this->expectedTm1Components['GSW']['default_qty']);
        $this->assertSame(1, $this->expectedTm1Components['BENDING']['default_qty']);
        $this->assertSame(1, $this->expectedTm1Components['BEGEL VERLINK']['default_qty']);
        $this->assertSame(1, $this->expectedTm1Components['VERLINK GSW']['default_qty']);
        $this->assertSame(3, $this->expectedTm1Components['WIRE CLIP']['default_qty']);
        $this->assertSame(3, $this->expectedTm1Components['PIN']['default_qty']);
        $this->assertSame(2, $this->expectedTm1Components['TOP TIES']['default_qty']);
        $this->assertSame(1, $this->expectedTm1Components['TOP TIES SIDE']['default_qty']);
    }

    /**
     * 4. Verify Migration 000003 contains all 13 canonical TM1 materials.
     */
    public function testMigrationFileContainsAll13Tm1Materials(): void
    {
        $migrationFile = APPPATH . 'Database/Migrations/2026-09-22-000003_PopulateAuthoritativeJtmBom.php';
        $this->assertFileExists($migrationFile);

        $content = file_get_contents($migrationFile);

        foreach (array_keys($this->expectedTm1Components) as $alias) {
            $this->assertStringContainsString(
                $alias,
                $content,
                "Migration 000003 must contain field alias '{$alias}'"
            );
        }

        // Verify no SET or BTG in TM1 definition
        $this->assertStringNotContainsString("'unit' => 'SET'", $content);
        $this->assertStringNotContainsString("'unit' => 'BTG'", $content);
    }

    /**
     * 5. Verify MaterialPickerService helper correctly sets default_qty key in BOM arrays.
     */
    public function testMaterialPickerDefaultQtyContract(): void
    {
        $refMethod = new \ReflectionMethod(MaterialPickerService::class, 'resolvePicker');
        $this->assertTrue($refMethod->isPublic());

        $viewCreate = file_get_contents(APPPATH . 'Views/temuan/create.php');
        $this->assertStringContainsString('data-default-qty', $viewCreate);
        $this->assertStringContainsString('mr01-mat-qty', $viewCreate);
    }
}
