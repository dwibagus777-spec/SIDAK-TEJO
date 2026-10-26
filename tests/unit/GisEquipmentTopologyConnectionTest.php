<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\GisTranslineService;
use App\Services\AssetTopologyService;

/**
 * Unit Test for GIS Equipment-to-Pole Connection Resolution, Duplicate Prevention, and Cycle Guard
 */
class GisEquipmentTopologyConnectionTest extends CIUnitTestCase
{
    protected string $controllerContent;
    protected string $viewContent;
    protected string $translineServiceContent;
    protected string $topologyServiceContent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controllerContent = file_get_contents(APPPATH . 'Controllers/GisController.php');
        $this->viewContent = file_get_contents(APPPATH . 'Views/gis/index.php');
        $this->translineServiceContent = file_get_contents(APPPATH . 'Services/GisTranslineService.php');
        $this->topologyServiceContent = file_get_contents(APPPATH . 'Services/AssetTopologyService.php');
    }

    /**
     * Test 01: PMCB and other protection/equipment are classified as non-pole devices
     */
    public function testEquipmentClassifiedAsNonPole(): void
    {
        $pmcb = [
            'id' => 47077,
            'nama_asset' => 'PMCB GUDANG BULOG',
            'kode_asset' => 'AST-PMCB-F15-808683',
            'jenis_asset' => 'PMCB',
        ];
        $check = GisTranslineService::isJtmPoleAsset($pmcb);
        $this->assertFalse($check['valid']);
        $this->assertStringContainsString('PMCB', $check['reason']);

        $tiang = [
            'id' => 3269,
            'nama_asset' => 'BANJARKEMANTRAN_56',
            'kode_asset' => 'AST-KOTA-BNJRKMNTRN-JTM-108',
            'jenis_asset' => 'JTM_TIANG',
        ];
        $checkTiang = GisTranslineService::isJtmPoleAsset($tiang);
        $this->assertTrue($checkTiang['valid']);
    }

    /**
     * Test 02: Duplicate transline check happens BEFORE cycle detection
     */
    public function testDuplicateTranslineCheckedBeforeCycleCheck(): void
    {
        // In GisController::apiConnectTopology, resolveTranslinePair must precede wouldCreateCycle
        $posDuplicate = strpos($this->controllerContent, 'resolveTranslinePair');
        $posCycle = strpos($this->controllerContent, 'wouldCreateCycle');

        $this->assertNotFalse($posDuplicate, 'resolveTranslinePair must be called in apiConnectTopology');
        $this->assertNotFalse($posCycle, 'wouldCreateCycle must be called in apiConnectTopology');
        $this->assertLessThan($posCycle, $posDuplicate, 'Duplicate check must occur before cycle check to prevent false circular topology errors');

        // Verify duplicate returns existing transline data and informative message
        $this->assertStringContainsString("'duplicate'            => true", $this->controllerContent);
        $this->assertStringContainsString('sudah aktif dengan kode', $this->controllerContent);
    }

    /**
     * Test 03: Ambiguous or unverified mounting pole is rejected without silent snapping
     */
    public function testUnresolvedMountingPoleRejectedExplicitly(): void
    {
        $this->assertStringContainsString('UNRESOLVED_MOUNTING_POLE', $this->controllerContent);
        $this->assertStringContainsString('Silakan pilih tiang JTM tempat peralatan ini terpasang secara eksplisit', $this->controllerContent);

        // In GisTranslineService, spatial fallbacks (NEAREST_CONNECTED_POLE / NEAREST_POLE) have verified => false
        $this->assertStringContainsString("'mounting_relation'       => 'NEAREST_CONNECTED_POLE'", $this->translineServiceContent);
        $this->assertMatchesRegularExpression("/'mounting_relation'\s*=>\s*'NEAREST_CONNECTED_POLE'[\s\S]*?'verified'\s*=>\s*false/", $this->translineServiceContent);
    }

    /**
     * Test 04: Cycle detection returns specific cycle path and identifies resolved poles
     */
    public function testCycleDetectionReturnsDetailedPath(): void
    {
        $this->assertStringContainsString('findCyclePath', $this->topologyServiceContent);
        $this->assertStringContainsString('CIRCULAR_TOPOLOGY', $this->controllerContent);
        $this->assertStringContainsString('Jalur loop:', $this->controllerContent);
        $this->assertStringContainsString("'cycle_path'", $this->controllerContent);
    }

    /**
     * Test 05: Equipment mounted on source pole does not create transline
     */
    public function testEquipmentMountedOnSourcePoleHandledCleanly(): void
    {
        $this->assertStringContainsString("'is_equipment_mounted' => true", $this->controllerContent);
        $this->assertStringContainsString('telah terpasang pada tiang tumpu', $this->controllerContent);
        $this->assertStringContainsString('(tidak memerlukan transline tambahan)', $this->controllerContent);
    }

    /**
     * Test 06: Frontend View resolves equipment to mounting pole and shows informative alerts
     */
    public function testFrontendResolvesEquipmentAndShowsAlerts(): void
    {
        $this->assertStringContainsString('function isNonPoleEquipment', $this->viewContent);
        $this->assertStringContainsString('conn-equipment-alert', $this->viewContent);
        $this->assertStringContainsString('conn-existing-alert', $this->viewContent);
        $this->assertStringContainsString('Lihat Segmen Aktif', $this->viewContent);
        $this->assertStringContainsString('VIEW_EXISTING', $this->viewContent);
    }
}
