<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\AssetVisualRegistryService;
use Config\GisIconConfig;

/**
 * GIS-02: Authentic PLN GIS Icon Visual Modernization Test Suite
 *
 * 25 Comprehensive Unit Tests covering:
 * 1. TM-1 tangent pole resolution to tm1.png
 * 2. TM-2 tension pole resolution to tm2.png
 * 3. TM-4 double tension pole resolution to tm4.png
 * 4. TM-5 angle pole resolution to tm5.png
 * 5. TM-8 portal double pole resolution to tm8.png
 * 6. TM-10 dead-end pole resolution to tm10.png
 * 7. TM-11 branch pole (T-Off) resolution to tm11.png
 * 8. GTT2 portal transformer resolution to gtt2-dist.png
 * 9. GTT1 / GTT cantilever transformer resolution to gtt1-dist.png
 * 10. GI substation resolution to gi.png
 * 11. PMS / LBSM manual switch resolution to lbsm.png
 * 12. LBS motorized switch resolution to lbs.png
 * 13. Recloser / PMCB resolution to pmcb-rec.png
 * 14. Cut-Out FCO resolution to co-branch.png
 * 15. Safe controlled fallback to TM-1 with diagnostic metadata
 * 16. Physical existence of all 24 authentic PNG files
 * 17. Valid PNG file header magic bytes
 * 18. GisIconConfig master definitions completeness
 * 19. GisIconConfig resolveIconKey resolution logic
 * 20. Frontend index.php resolveAssetIcon implementation & exposure
 * 21. Frontend marker CSS refinement (halo ring transparency & PNG 34px)
 * 22. Frontend MarkerCluster threshold (zoom 16 unclustering)
 * 23. Frontend GIS_ICON_CACHE & marker pooling preservation
 * 24. Finding isolation: temuan domain strictly separated from asset icons
 * 25. Read-only zero-write code contract: resolver has zero SQL mutations
 */
class GisIconModernizationTest extends CIUnitTestCase
{
    protected AssetVisualRegistryService $visualRegistry;
    protected GisIconConfig $iconConfig;
    protected string $publicIconDir;
    protected string $viewGisContent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->visualRegistry = new AssetVisualRegistryService();
        $this->iconConfig = new GisIconConfig();
        $this->publicIconDir = realpath(FCPATH . 'assets/gis/icons') ?: (FCPATH . 'assets/gis/icons');
        
        $viewPath = APPPATH . 'Views/gis/index.php';
        $this->viewGisContent = file_exists($viewPath) ? file_get_contents($viewPath) : '';
    }

    /** 1. TM-1 tangent pole resolution to tm1.png */
    public function test01ResolveTm1TangentPole()
    {
        $res = $this->visualRegistry->resolveVisual('JTM', 'TM1');
        $this->assertSame('tm1.png', $res['png_file']);
        $this->assertSame('TM_1', $res['symbol_key']);
        $this->assertFalse($res['isFallback']);
        $this->assertNull($res['fallbackReason']);
    }

    /** 2. TM-2 tension pole resolution to tm2.png */
    public function test02ResolveTm2TensionPole()
    {
        $res = $this->visualRegistry->resolveVisual('JTM', 'TM2');
        $this->assertSame('tm2.png', $res['png_file']);
        $this->assertSame('TM_2', $res['symbol_key']);
        $this->assertFalse($res['isFallback']);
        $this->assertNull($res['fallbackReason']);
    }

    /** 3. TM-4 double tension pole resolution to tm4.png */
    public function test03ResolveTm4DoubleTensionPole()
    {
        $res = $this->visualRegistry->resolveVisual('JTM', 'TM4');
        $this->assertSame('tm4.png', $res['png_file']);
        $this->assertSame('TM_4', $res['symbol_key']);
        $this->assertFalse($res['isFallback']);
        $this->assertNull($res['fallbackReason']);
    }

    /** 4. TM-5 angle pole resolution to tm5.png */
    public function test04ResolveTm5AnglePole()
    {
        $res = $this->visualRegistry->resolveVisual('JTM', 'TM5');
        $this->assertSame('tm5.png', $res['png_file']);
        $this->assertSame('TM_5', $res['symbol_key']);
        $this->assertFalse($res['isFallback']);
        $this->assertNull($res['fallbackReason']);
    }

    /** 5. TM-8 portal double pole resolution to tm8.png */
    public function test05ResolveTm8PortalDoublePole()
    {
        $res = $this->visualRegistry->resolveVisual('JTM', 'TM8');
        $this->assertSame('tm8.png', $res['png_file']);
        $this->assertSame('TM_8', $res['symbol_key']);
        $this->assertFalse($res['isFallback']);
        $this->assertNull($res['fallbackReason']);
    }

    /** 6. TM-10 dead-end pole resolution to tm10.png */
    public function test06ResolveTm10DeadEndPole()
    {
        $res = $this->visualRegistry->resolveVisual('JTM', 'TM10');
        $this->assertSame('tm10.png', $res['png_file']);
        $this->assertSame('TM_10', $res['symbol_key']);
        $this->assertFalse($res['isFallback']);
        $this->assertNull($res['fallbackReason']);
    }

    /** 7. TM-11 branch pole (T-Off) resolution to tm11.png */
    public function test07ResolveTm11BranchPole()
    {
        $res = $this->visualRegistry->resolveVisual('JTM', 'TM11');
        $this->assertSame('tm11.png', $res['png_file']);
        $this->assertSame('TM_11', $res['symbol_key']);
        $this->assertFalse($res['isFallback']);
        $this->assertNull($res['fallbackReason']);
    }

    /** 8. GTT2 portal transformer resolution to gtt2-dist.png */
    public function test08ResolveGtt2PortalTransformer()
    {
        $res = $this->visualRegistry->resolveVisual('JTM', 'GTT2');
        $this->assertSame('gtt2-dist.png', $res['png_file']);
        $this->assertSame('GARDU_GTT2_DIST', $res['symbol_key']);
        $this->assertFalse($res['isFallback']);
        $this->assertNull($res['fallbackReason']);
    }

    /** 9. GTT1 / GTT cantilever transformer resolution to gtt1-dist.png */
    public function test09ResolveGtt1CantileverTransformer()
    {
        $res1 = $this->visualRegistry->resolveVisual('JTM', 'GTT1');
        $this->assertSame('gtt1-dist.png', $res1['png_file']);
        $this->assertSame('GARDU_GTT1_DIST', $res1['symbol_key']);
        $this->assertFalse($res1['isFallback']);

        $res2 = $this->visualRegistry->resolveVisual('JTM', 'GTT');
        $this->assertSame('gtt1-dist.png', $res2['png_file']);
        $this->assertSame('GARDU_GTT1_DIST', $res2['symbol_key']);
        $this->assertFalse($res2['isFallback']);
    }

    /** 10. GI substation resolution to gi.png */
    public function test10ResolveGiSubstation()
    {
        $res = $this->visualRegistry->resolveVisual('GARDU', 'GARDU_INDUK');
        $this->assertSame('gi.png', $res['png_file']);
        $this->assertSame('GI', $res['symbol_key']);
        $this->assertFalse($res['isFallback']);
    }

    /** 11. PMS / LBSM manual switch resolution to lbsm.png */
    public function test11ResolvePmsManualSwitch()
    {
        $res = $this->visualRegistry->resolveVisual('JTM', 'PMS');
        $this->assertSame('lbsm.png', $res['png_file']);
        $this->assertSame('LBSM', $res['symbol_key']);
        $this->assertFalse($res['isFallback']);
    }

    /** 12. LBS motorized switch resolution to lbs.png */
    public function test12ResolveLbsMotorizedSwitch()
    {
        $res = $this->visualRegistry->resolveVisual('SWITCH', 'LBS_MOTOR');
        $this->assertSame('lbs.png', $res['png_file']);
        $this->assertSame('LBS', $res['symbol_key']);
        $this->assertFalse($res['isFallback']);
    }

    /** 13. Recloser / PMCB resolution to pmcb-rec.png */
    public function test13ResolveRecloserPmcb()
    {
        $res = $this->visualRegistry->resolveVisual('SWITCH', 'RECLOSER');
        $this->assertSame('pmcb-rec.png', $res['png_file']);
        $this->assertSame('PMCB_REC', $res['symbol_key']);
        $this->assertFalse($res['isFallback']);
    }

    /** 14. Cut-Out FCO resolution to co-branch.png */
    public function test14ResolveCutoutFco()
    {
        $res = $this->visualRegistry->resolveVisual('SWITCH', 'FCO');
        $this->assertSame('co-branch.png', $res['png_file']);
        $this->assertSame('CO_BRANCH', $res['symbol_key']);
        $this->assertFalse($res['isFallback']);
    }

    /** 15. Safe controlled fallback to TM-1 with diagnostic metadata */
    public function test15SafeControlledFallbackWithDiagnostics()
    {
        $res = $this->visualRegistry->resolveVisual('JTM', 'UNKNOWN_CUSTOM_STRUCTURE_999');
        $this->assertSame('tm1.png', $res['png_file']);
        $this->assertSame('TM_1', $res['symbol_key']);
        $this->assertTrue($res['isFallback']);
        $this->assertSame('UNKNOWN_CONSTRUCTION_TYPE', $res['fallbackReason']);
    }

    /** 16. Physical existence of all 24 authentic PNG files */
    public function test16PhysicalExistenceOfAll24PngFiles()
    {
        $expectedFiles = [
            'a3c-70.png', 'a3c-150.png', 'a3c-240.png',
            'a3cs-150.png', 'a3cs-240.png',
            'mvtic-150.png', 'xlpe.png',
            'tm1.png', 'tm2.png', 'tm4.png', 'tm5.png', 'tm8.png', 'tm10.png', 'tm11.png', 'tm11-i3.png',
            'gtt1-dist.png', 'gtt1-i2.png', 'gtt2-dist.png', 'gtt2-i2.png',
            'gi.png', 'lbs.png', 'lbsm.png', 'pmcb-rec.png', 'co-branch.png'
        ];

        $this->assertCount(24, $expectedFiles);

        foreach ($expectedFiles as $file) {
            $fullPath = $this->publicIconDir . DIRECTORY_SEPARATOR . $file;
            $this->assertFileExists($fullPath, "PNG icon {$file} must physically exist in public/assets/gis/icons/");
            $this->assertGreaterThan(0, filesize($fullPath), "PNG icon {$file} must not be empty");
        }
    }

    /** 17. Valid PNG file header magic bytes */
    public function test17ValidPngMagicBytes()
    {
        $samplePng = $this->publicIconDir . DIRECTORY_SEPARATOR . 'tm1.png';
        $handle = fopen($samplePng, 'rb');
        $header = fread($handle, 8);
        fclose($handle);

        $expectedPngHeader = "\x89PNG\r\n\x1a\n";
        $this->assertSame($expectedPngHeader, $header, "tm1.png must have valid PNG magic bytes header");
    }

    /** 18. GisIconConfig master definitions completeness */
    public function test18GisIconConfigCompleteness()
    {
        $icons = $this->iconConfig->icons;
        $this->assertArrayHasKey('JTM_DEFAULT', $icons);
        $this->assertArrayHasKey('JTM_TM1', $icons);
        $this->assertArrayHasKey('JTM_TM2', $icons);
        $this->assertArrayHasKey('JTM_TM4', $icons);
        $this->assertArrayHasKey('JTM_TM5', $icons);
        $this->assertArrayHasKey('JTM_TM8', $icons);
        $this->assertArrayHasKey('JTM_TM10', $icons);
        $this->assertArrayHasKey('JTM_TM11', $icons);
        $this->assertArrayHasKey('GARDU_GTT1_DIST', $icons);
        $this->assertArrayHasKey('GARDU_GTT2_DIST', $icons);
        $this->assertArrayHasKey('GARDU_INDUK', $icons);
        $this->assertArrayHasKey('SWITCH_LBS', $icons);
        $this->assertArrayHasKey('SWITCH_LBSM', $icons);
        $this->assertArrayHasKey('SWITCH_RECLOSER', $icons);
        $this->assertArrayHasKey('SWITCH_CUTOUT', $icons);
    }

    /** 19. GisIconConfig resolveIconKey resolution logic */
    public function test19GisIconConfigResolveIconKey()
    {
        $keyGtt2 = $this->iconConfig->resolveIconKey(['jenis_asset' => 'JTM', 'construction_type' => 'GTT2']);
        $this->assertSame('GARDU_GTT2_DIST', $keyGtt2);

        $keyGtt1 = $this->iconConfig->resolveIconKey(['jenis_asset' => 'JTM', 'construction_type' => 'GTT1']);
        $this->assertSame('GARDU_GTT1_DIST', $keyGtt1);

        $keyPms = $this->iconConfig->resolveIconKey(['jenis_asset' => 'JTM', 'construction_type' => 'PMS']);
        $this->assertSame('SWITCH_LBSM', $keyPms);

        $keyTm2 = $this->iconConfig->resolveIconKey(['jenis_asset' => 'JTM', 'construction_type' => 'TM2']);
        $this->assertSame('JTM_TM2', $keyTm2);

        $keyTm8 = $this->iconConfig->resolveIconKey(['jenis_asset' => 'JTM', 'construction_type' => 'TM8']);
        $this->assertSame('JTM_TM8', $keyTm8);
    }

    /** 20. Frontend index.php resolveAssetIcon implementation & exposure */
    public function test20FrontendResolveAssetIconImplementation()
    {
        $this->assertStringContainsString('function resolveAssetIcon(props, visual)', $this->viewGisContent);
        $this->assertStringContainsString('window.resolveAssetIcon = resolveAssetIcon;', $this->viewGisContent);
        $this->assertStringContainsString('gtt2-dist.png', $this->viewGisContent);
        $this->assertStringContainsString('gtt1-dist.png', $this->viewGisContent);
        $this->assertStringContainsString('lbsm.png', $this->viewGisContent);
        $this->assertStringContainsString('UNKNOWN_CONSTRUCTION_TYPE', $this->viewGisContent);
    }

    /** 21. Frontend marker CSS refinement (halo ring transparency & PNG 34px) */
    public function test21FrontendMarkerCssRefinement()
    {
        $this->assertStringContainsString('.asset-condition-halo {', $this->viewGisContent);
        $this->assertStringContainsString('width: 38px;', $this->viewGisContent);
        $this->assertStringContainsString('height: 38px;', $this->viewGisContent);
        $this->assertStringContainsString('.asset-ring-good { border: 1.5px solid #10b981; background: transparent; }', $this->viewGisContent);
        $this->assertStringContainsString('width: 34px;', $this->viewGisContent);
        $this->assertStringContainsString('height: 34px;', $this->viewGisContent);
    }

    /** 22. Frontend MarkerCluster threshold (zoom 16 unclustering) */
    public function test22FrontendMarkerClusterThreshold()
    {
        $this->assertStringContainsString('disableClusteringAtZoom: 16', $this->viewGisContent);
        $this->assertStringContainsString('maxClusterRadius: 30', $this->viewGisContent);
    }

    /** 23. Frontend GIS_ICON_CACHE & marker pooling preservation */
    public function test23FrontendIconCacheAndMarkerPoolingPreserved()
    {
        $this->assertStringContainsString('var iconKey = `${iconUrl}|${ringClass}|${symbolKey}`;', $this->viewGisContent);
        $this->assertStringContainsString('var customIcon = GIS_ICON_CACHE.get(iconKey);', $this->viewGisContent);
        $this->assertStringContainsString('if (assetId && markerByAssetId.has(assetId))', $this->viewGisContent);
    }

    /** 24. Finding isolation: temuan domain strictly separated from asset icons */
    public function test24FindingLayerStrictIsolation()
    {
        $this->assertStringContainsString('function createTemuanVisualMarker(', $this->viewGisContent);
        $this->assertStringContainsString('findingLayer.addLayer(', $this->viewGisContent);
        $this->assertStringNotContainsString('translinePolylineLayer.addLayer(finding', $this->viewGisContent);
    }

    /** 25. Read-only zero-write code contract: resolver has zero SQL mutations */
    public function test25ResolverServiceZeroWriteContract()
    {
        $serviceCode = file_get_contents(APPPATH . 'Services/AssetVisualRegistryService.php');
        $this->assertStringNotContainsString('INSERT INTO', $serviceCode);
        $this->assertStringNotContainsString('UPDATE assets', $serviceCode);
        $this->assertStringNotContainsString('DELETE FROM', $serviceCode);
        $this->assertStringNotContainsString('DROP TABLE', $serviceCode);
        $this->assertStringNotContainsString('ALTER TABLE', $serviceCode);
    }
}
