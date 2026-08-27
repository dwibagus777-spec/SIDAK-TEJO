<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\AssetService;
use App\Services\DynamicAssetImportService;

/**
 * @internal
 */
final class AssetImportIntegrityTest extends CIUnitTestCase
{
    private AssetService $assetService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assetService = new AssetService();
    }

    public function testRequiresFeederRelationIdentifiesDistributionGridAssets(): void
    {
        $this->assertTrue($this->assetService->requiresFeederRelation('JTM'));
        $this->assertTrue($this->assetService->requiresFeederRelation('GARDU'));
        $this->assertTrue($this->assetService->requiresFeederRelation('TRAFO'));
        $this->assertTrue($this->assetService->requiresFeederRelation('KUBIKEL'));
        $this->assertTrue($this->assetService->requiresFeederRelation('LBS'));
        $this->assertTrue($this->assetService->requiresFeederRelation('LBSM'));
        $this->assertTrue($this->assetService->requiresFeederRelation('RECLOSER'));

        // Non-feeder assets
        $this->assertFalse($this->assetService->requiresFeederRelation('TIANG'));
        $this->assertFalse($this->assetService->requiresFeederRelation('METER'));
        $this->assertFalse($this->assetService->requiresFeederRelation('GROUNDING'));
    }

    public function testSanitizePenyulangCode(): void
    {
        $this->assertEquals('CNDRMS', $this->assetService->sanitizePenyulangCode('CANDRAMAS'));
        $this->assertEquals('CNDRMS', $this->assetService->sanitizePenyulangCode('Penyulang CANDRAMAS'));
        $this->assertEquals('CNDRMS', $this->assetService->sanitizePenyulangCode('Feeder CANDRAMAS'));
        $this->assertEquals('BYPASS', $this->assetService->sanitizePenyulangCode('BY PASS'));
        $this->assertNull($this->assetService->sanitizePenyulangCode(''));
        $this->assertNull($this->assetService->sanitizePenyulangCode(null));
    }

    public function testGenerateKodeAssetThrowsExceptionWhenFeederMissingForGridAsset(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('feeder/penyulang unresolved');

        $cache = [];
        $this->assetService->generateKodeAsset('JTM', 'ULP Sidoarjo Kota', null, $cache);
    }

    public function testGenerateKodeAssetAllowsGenOnlyForNonFeederAsset(): void
    {
        $cache = [];
        $code = $this->assetService->generateKodeAsset('METER', 'ULP Sidoarjo Kota', null, $cache);
        $this->assertStringContainsString('AST-KOTA-GEN-MTR-', $code);
    }

    public function testGenerateKodeAssetWithValidFeeder(): void
    {
        $cache = [];
        $code = $this->assetService->generateKodeAsset('JTM', 'ULP Sidoarjo Kota', 'CANDRAMAS', $cache);
        $this->assertStringContainsString('AST-KOTA-CNDRMS-JTM-', $code);
    }

    public function testExtractFeederPrefixFromAssetName(): void
    {
        $importService = new DynamicAssetImportService();
        $refMethod = new \ReflectionMethod($importService, 'extractFeederPrefix');
        $refMethod->setAccessible(true);

        $this->assertEquals('CANDRAMAS', $refMethod->invoke($importService, 'CANDRAMAS_011'));
        $this->assertEquals('CANDRAMAS', $refMethod->invoke($importService, 'CANDRAMAS-016'));
        $this->assertEquals('CANDRAMAS', $refMethod->invoke($importService, 'CANDRAMAS 003'));
        $this->assertEquals('CANDRAMAS', $refMethod->invoke($importService, 'CANDRAMAS/02'));
        $this->assertEquals('BY PASS', $refMethod->invoke($importService, 'BY PASS_001'));
        $this->assertNull($refMethod->invoke($importService, ''));
        $this->assertNull($refMethod->invoke($importService, null));
    }

    public function testResolvePenyulangMultiStrategy(): void
    {
        $importService = new DynamicAssetImportService();
        $refMethod = new \ReflectionMethod($importService, 'resolvePenyulang');
        $refMethod->setAccessible(true);

        $mockPenyulangList = [
            ['id' => 42, 'nama_penyulang' => 'CANDRAMAS', 'ulp_id' => 1],
            ['id' => 43, 'nama_penyulang' => 'BY PASS', 'ulp_id' => 1],
            ['id' => 44, 'nama_penyulang' => 'BANJAR KEMANTREN', 'ulp_id' => 2],
        ];

        // Strategy 1: SYSTEM_METADATA_ID
        $res1 = $refMethod->invoke($importService, '', 'AST 001', ['PENYULANG_ID' => '42'], $mockPenyulangList);
        $this->assertEquals(42, $res1['id']);
        $this->assertEquals('SYSTEM_METADATA_ID', $res1['source']);

        // Strategy 2: SYSTEM_METADATA_NAME
        $res2 = $refMethod->invoke($importService, '', 'AST 001', ['PENYULANG_NAME' => 'BY PASS'], $mockPenyulangList);
        $this->assertEquals(43, $res2['id']);
        $this->assertEquals('SYSTEM_METADATA_NAME', $res2['source']);

        // Strategy 3: EXPLICIT_COLUMN with normalization
        $res3 = $refMethod->invoke($importService, 'Penyulang BANJAR KEMANTREN', 'AST 001', [], $mockPenyulangList);
        $this->assertEquals(44, $res3['id']);
        $this->assertEquals('EXPLICIT_COLUMN', $res3['source']);

        // Strategy 4: ASSET_NAME_PREFIX extraction (CANDRAMAS_011 -> CANDRAMAS)
        $res4 = $refMethod->invoke($importService, '', 'CANDRAMAS_011', [], $mockPenyulangList);
        $this->assertEquals(42, $res4['id']);
        $this->assertEquals('ASSET_NAME_PREFIX', $res4['source']);

        // Unresolved returns nulls
        $res5 = $refMethod->invoke($importService, '', 'UNKNOWN_FEEDER_999', [], $mockPenyulangList);
        $this->assertNull($res5['id']);
    }

    public function testHardGateRejectionWhenOneRowIsInvalid(): void
    {
        $importService = new DynamicAssetImportService();

        // Sample rows: row 1 header, row 2 valid (if feeder resolved), row 3 invalid (empty nama_asset)
        $rows = [
            1 => ['A' => 'UP3', 'B' => 'ULP', 'C' => 'Jenis Asset', 'D' => 'Nama Asset', 'E' => 'Penyulang'],
            2 => ['A' => 'UP3 Sidoarjo', 'B' => 'Sidoarjo Kota', 'C' => 'JTM', 'D' => 'CANDRAMAS_011', 'E' => ''],
            3 => ['A' => 'UP3 Sidoarjo', 'B' => 'Sidoarjo Kota', 'C' => 'JTM', 'D' => '', 'E' => 'CANDRAMAS'], // Missing nama_asset
        ];

        $metadata = ['ULP_ID' => '1', 'ULP_NAME' => 'Sidoarjo Kota'];
        $result = $importService->processImport($rows, $metadata, 'test_batch.xlsx');

        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['inserted']);
        $this->assertGreaterThanOrEqual(1, $result['failed']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('DIBATALKAN', $result['message']);
        
        if (!empty($result['error_excel_path'])) {
            $this->assertFileExists($result['error_excel_path']);
            @unlink($result['error_excel_path']); // Clean up
        }
    }
}
