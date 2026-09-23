<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ConstructionService;

/**
 * Class EquipmentStandardClassificationTest
 *
 * Verifies CR-EQUIPMENT-STANDARD-01 invariants:
 * 1. Equipment master standards (PMCB, LBS, LBSM, ASS, AVS, RECLOSER) are registered in construction_types.
 * 2. asset_domain is strictly 'EQUIPMENT' for all equipment standards.
 * 3. Operational asset names (nama_asset) are preserved verbatim and separated from standard types.
 * 4. Pole constructions (TM-1 s/d TM-12) retain 'POLE' domain.
 */
class EquipmentStandardClassificationTest extends CIUnitTestCase
{
    protected array $canonicalEquipmentCodes = [
        'PMCB',
        'LBS',
        'LBSM',
        'ASS',
        'AVS',
        'RECLOSER',
    ];

    /**
     * 1. Assert domain resolution returns EQUIPMENT for standard equipment codes and operational labels.
     */
    public function testEquipmentDomainResolution(): void
    {
        foreach ($this->canonicalEquipmentCodes as $code) {
            $domain = ConstructionService::resolveAssetDomain($code);
            $this->assertSame(
                'EQUIPMENT',
                $domain,
                "Code '{$code}' must resolve to 'EQUIPMENT' domain"
            );

            // Operational instance test (e.g. 'PMCB KEBONAGUNG 01')
            $instanceDomain = ConstructionService::resolveAssetDomain("{$code} FEEDER CANDI 12");
            $this->assertSame(
                'EQUIPMENT',
                $instanceDomain,
                "Instance '{$code} FEEDER CANDI 12' must resolve to 'EQUIPMENT' domain"
            );
        }
    }

    /**
     * 2. Assert pole constructions and normal assets resolve to POLE domain.
     */
    public function testPoleDomainResolution(): void
    {
        $poleCodes = ['TM1', 'TM2', 'TM4', 'TM5', 'TR1', 'TIANG 12/200', 'TIANG BETON 11M'];
        foreach ($poleCodes as $code) {
            $domain = ConstructionService::resolveAssetDomain($code);
            $this->assertSame(
                'POLE',
                $domain,
                "Pole code '{$code}' must resolve to 'POLE' domain"
            );
        }
    }

    /**
     * 3. Assert migration registers all 6 canonical equipment types with asset_domain = EQUIPMENT.
     */
    public function testMigrationRegistersEquipmentStandards(): void
    {
        $migrationFile = APPPATH . 'Database/Migrations/2026-09-22-000002_RegisterEquipmentAndConstructionStandards.php';
        $this->assertFileExists($migrationFile, 'Equipment registration migration must exist');

        $content = file_get_contents($migrationFile);

        foreach ($this->canonicalEquipmentCodes as $code) {
            $this->assertStringContainsString(
                "'code'                 => '{$code}'",
                $content,
                "Migration must register code '{$code}'"
            );
        }

        $this->assertStringContainsString("'asset_domain'         => 'EQUIPMENT'", $content);
        $this->assertStringContainsString("'approval_status'      => 'ACTIVE'", $content);
    }

    /**
     * 4. Assert ConstructionService catalogs include the 6 equipment types with asset_domain = EQUIPMENT.
     */
    public function testConstructionServiceSeedsEquipmentStandards(): void
    {
        $serviceFile = APPPATH . 'Services/ConstructionService.php';
        $content = file_get_contents($serviceFile);

        foreach ($this->canonicalEquipmentCodes as $code) {
            $this->assertStringContainsString("'code' => '{$code}'", $content);
        }

        $this->assertStringContainsString("'asset_domain' => 'EQUIPMENT'", $content);
    }

    /**
     * 5. Assert Non-Destructive Asset Name Preservation Policy.
     */
    public function testNonDestructiveAssetNamePreservationPolicy(): void
    {
        $migrationFile = APPPATH . 'Database/Migrations/2026-09-22-000002_RegisterEquipmentAndConstructionStandards.php';
        $content = file_get_contents($migrationFile);

        // Ensure update statements only set construction_type_id and do NOT update nama_asset or kode_asset
        $this->assertStringContainsString("SET `construction_type_id` =", $content);
        $this->assertStringNotContainsString("SET `nama_asset`", $content);
        $this->assertStringNotContainsString("SET `kode_asset`", $content);
    }
}
