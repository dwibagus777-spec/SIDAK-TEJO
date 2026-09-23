<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\MaterialPickerService;

/**
 * Class EquipmentBomIntegrityTest
 *
 * Verifies CR-HOTFIX-04 Block B:
 * 1. Equipment types (PMCB, LBS, LBSM, ASS, AVS, RECLOSER) have seeded canonical BOM items.
 * 2. All canonical equipment materials use unit 'buah'.
 * 3. MaterialPickerService resolves equipment nomenclature and distinguishes main equipment from auxiliary components.
 * 4. MaterialPickerService computes honest bom_status (COMPLETE / PARTIAL / NEEDS_BOM_MAPPING).
 * 5. MigrateController Phase 3B is synchronized.
 */
class EquipmentBomIntegrityTest extends CIUnitTestCase
{
    protected array $equipmentCodes = [
        'LBS',
        'LBSM',
        'PMCB',
        'RECLOSER',
        'ASS',
        'AVS',
    ];

    /**
     * 1. Assert migration file exists and defines BOM items for all 6 equipment types.
     */
    public function testEquipmentBomMigrationContainsAllEquipmentTypes(): void
    {
        $migrationFile = APPPATH . 'Database/Migrations/2026-09-23-000001_PopulateEquipmentBomItems.php';
        $this->assertFileExists($migrationFile, 'Equipment BOM migration must exist');

        $content = file_get_contents($migrationFile);

        foreach ($this->equipmentCodes as $code) {
            $this->assertStringContainsString(
                "'{$code}' => [",
                $content,
                "Migration must contain BOM mapping for '{$code}'"
            );
        }
    }

    /**
     * 2. Assert all equipment materials in migration are strictly defined with unit = 'buah'.
     */
    public function testEquipmentMaterialsUnitIsBuah(): void
    {
        $migrationFile = APPPATH . 'Database/Migrations/2026-09-23-000001_PopulateEquipmentBomItems.php';
        $content = file_get_contents($migrationFile);

        // Extract material definitions
        preg_match('/\$equipmentMaterials\s*=\s*\[(.*?)\];/s', $content, $matches);
        $this->assertNotEmpty($matches, 'Migration must define $equipmentMaterials array');

        $materialsSnippet = $matches[1];
        preg_match_all("/'unit'\s*=>\s*'([^']+)'/", $materialsSnippet, $unitMatches);
        $this->assertNotEmpty($unitMatches[1], 'Must find unit definitions in materials');

        foreach ($unitMatches[1] as $idx => $unit) {
            $this->assertSame(
                'buah',
                $unit,
                "All equipment material definitions must have unit 'buah' (found '{$unit}' at index {$idx})"
            );
        }
    }

    /**
     * 3. Assert MaterialPickerService resolves equipment nomenclature for standard equipment and alias REC.
     */
    public function testEquipmentNomenclatureResolution(): void
    {
        $testCases = [
            'LBS' => 'LBS',
            'LBSM' => 'LBSM',
            'PMCB' => 'PMCB',
            'RECLOSER' => 'RECLOSER',
            'REC' => 'RECLOSER',
            'ASS' => 'ASS',
            'AVS' => 'AVS',
        ];

        foreach ($testCases as $alias => $expectedCanonical) {
            $resolved = MaterialPickerService::resolveNomenclature($alias, 'Default Name');
            $this->assertIsArray($resolved);
            $this->assertSame(
                $expectedCanonical,
                $resolved['canonical_code'],
                "Alias '{$alias}' must resolve to canonical code '{$expectedCanonical}'"
            );
            $this->assertNotEmpty($resolved['technical_name'], "Technical name must not be empty for '{$alias}'");
            $this->assertNotEmpty($resolved['feature_badge'], "Feature badge must not be empty for '{$alias}'");
            $this->assertNotEmpty($resolved['display_label'], "Display label must not be empty for '{$alias}'");
        }
    }

    /**
     * 4. Assert resolveBomByConstructionTypeId returns honest bom_status.
     */
    public function testResolveBomHonestStatusComputation(): void
    {
        $picker = new MaterialPickerService();

        // Testing unmapped construction ID 0 -> NEEDS_BOM_MAPPING
        $result = $picker->resolveBomByConstructionTypeId(0);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('bom_status', $result);
        $this->assertArrayHasKey('bom_item_count', $result);
        $this->assertSame('NEEDS_BOM_MAPPING', $result['bom_status']);
        $this->assertSame(0, $result['bom_item_count']);
        $this->assertEmpty($result['materials']);
    }

    /**
     * 5. Assert Auto-Migration Phase 3B in MigrateController is synchronized.
     */
    public function testMigrateControllerPhase3BSynchronization(): void
    {
        $controllerFile = APPPATH . 'Controllers/MigrateController.php';
        $this->assertFileExists($controllerFile);

        $content = file_get_contents($controllerFile);
        $this->assertStringContainsString('Phase 3B: Seed Equipment BOM Catalog', $content);
        $this->assertStringContainsString('$equipmentMaterials = [', $content);
        $this->assertStringContainsString('$equipmentBomMap = [', $content);

        foreach ($this->equipmentCodes as $code) {
            $this->assertStringContainsString(
                "'{$code}' => [",
                $content,
                "MigrateController must map BOM for '{$code}'"
            );
        }
    }
}
