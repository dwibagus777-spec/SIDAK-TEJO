<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ServerSideAssetIngestEngine;
use App\Services\AssetCorpusCompletionService;

/**
 * @internal
 */
final class D416StuckIngestForensicTest extends CIUnitTestCase
{
    private ServerSideAssetIngestEngine $engine;
    private AssetCorpusCompletionService $completionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new ServerSideAssetIngestEngine();
        $this->completionService = new AssetCorpusCompletionService();
    }

    public function testPrepareBatchPreservesExistingBatchUuid(): void
    {
        $rows = [
            [
                'unit' => 'UP3 Sidoarjo',
                'ulp' => 'ULP Sidoarjo Kota',
                'feeder' => 'CANDRAMAS',
                'nama_asset' => 'Tiang TM Test 001',
                'section' => 'Sidoarjo',
                'latitude' => -7.456,
                'longitude' => 112.718,
            ]
        ];

        $batchUuid = 'BATCH-TEST-D416-0001';
        $result = $this->engine->prepareBatch($rows, 'TEST_FILE.csv', 1, $batchUuid);

        $this->assertEquals($batchUuid, $result['batch_uuid']);
        $this->assertEquals('PREPARED', $result['status']);
        $this->assertEquals(1, $result['source_rows']);
    }

    public function testOperationalSummaryUsesActiveAssetsQuery(): void
    {
        $summary = $this->completionService->getOperationalSummary();

        $this->assertArrayHasKey('active_assets', $summary);
        $this->assertArrayHasKey('physical_assets', $summary);
        $this->assertArrayHasKey('deleted_assets', $summary);
        $this->assertArrayHasKey('canonical_pool_total', $summary);
        $this->assertGreaterThanOrEqual(5236, $summary['active_assets']);
    }
}
