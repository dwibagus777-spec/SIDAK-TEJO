<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\AssetVisualRegistryService;

class GisLayersAndDashboardTest extends CIUnitTestCase
{
    private string $dashboardView;
    private string $mobileDashboardView;
    private string $gisView;
    private string $temuanView;
    private AssetVisualRegistryService $visualRegistry;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['url']);
        $this->dashboardView       = file_get_contents(APPPATH . 'Views/dashboard/index.php');
        $this->mobileDashboardView = file_get_contents(APPPATH . 'Views/dashboard/mobile.php');
        $this->gisView             = file_get_contents(APPPATH . 'Views/gis/index.php');
        $this->temuanView          = file_get_contents(APPPATH . 'Views/temuan/index.php');
        $this->visualRegistry      = new AssetVisualRegistryService();
    }

    public function testDashboardTemuanSelesaiCardIsAdjacentToTotalTemuan(): void
    {
        $this->assertStringContainsString('id="card-dashboard-temuan-selesai"', $this->dashboardView);
        $this->assertStringContainsString('Temuan Selesai', $this->dashboardView);
        $this->assertStringContainsString('.dah-kpi-grid', $this->dashboardView);
        $this->assertStringContainsString('grid-template-columns: repeat(7, 1fr)', $this->dashboardView);

        // Position check: Total Temuan appears before Temuan Selesai
        $posTotalTemuan = strpos($this->dashboardView, 'Total Temuan');
        $posTemuanSelesai = strpos($this->dashboardView, 'id="card-dashboard-temuan-selesai"');
        $posPenyulangSehat = strpos($this->dashboardView, 'Penyulang Sehat');

        $this->assertNotFalse($posTotalTemuan);
        $this->assertNotFalse($posTemuanSelesai);
        $this->assertNotFalse($posPenyulangSehat);
        $this->assertLessThan($posTemuanSelesai, $posTotalTemuan);
        $this->assertLessThan($posPenyulangSehat, $posTemuanSelesai);

        // Link with status=SELESAI
        $this->assertStringContainsString("temuan?status=SELESAI", $this->dashboardView);
    }

    public function testMobileDashboardHasSelesaiCardBesideTotalTemuan(): void
    {
        $posTotal = strpos($this->mobileDashboardView, 'Total Temuan');
        $posSelesai = strpos($this->mobileDashboardView, 'Sudah Selesai');
        $posEmergency = strpos($this->mobileDashboardView, 'Emergency');

        $this->assertNotFalse($posTotal);
        $this->assertNotFalse($posSelesai);
        $this->assertNotFalse($posEmergency);
        $this->assertLessThan($posSelesai, $posTotal);
        $this->assertLessThan($posEmergency, $posSelesai);
    }

    public function testGisSetupAndDrawerHaveTranslineAndRowLayerCheckboxes(): void
    {
        // Setup screen checkboxes
        $this->assertStringContainsString('id="setup-layer-transline"', $this->gisView);
        $this->assertStringContainsString('id="setup-layer-row"', $this->gisView);
        $this->assertStringContainsString('Aset Transline', $this->gisView);
        $this->assertStringContainsString('Aset ROW', $this->gisView);

        // Drawer filter checkboxes
        $this->assertStringContainsString('id="drawer-layer-transline"', $this->gisView);
        $this->assertStringContainsString('id="drawer-layer-row"', $this->gisView);

        // URL param initialization includes transline and row
        $this->assertStringContainsString("'transline', 'row'", $this->gisView);
    }

    public function testAssetVisualRegistryResolvesRowSymbols(): void
    {
        $resolved = $this->visualRegistry->resolveVisual('POHON_ROW', 'ROW');

        $this->assertSame('POHON_ROW', $resolved['symbol_key']);
        $this->assertSame('vegetation', $resolved['category']);
        $this->assertSame('#16a34a', $resolved['color']);
        $this->assertSame('tree', $resolved['shape']);
    }

    public function testTemuanViewPreservesSelesaiStatusSelection(): void
    {
        $this->assertStringContainsString('value="SELESAI"', $this->temuanView);
        $this->assertStringContainsString('$selectedStatus', $this->temuanView);
    }
}
