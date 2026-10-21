<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\GisTranslineService;
use App\Services\BatchTranslineValidationService;
use App\Services\AssetMatrixService;
use App\Services\FaultLocatorWorkspaceService;

/**
 * @internal
 */
final class BatchTranslineValidationServiceTest extends CIUnitTestCase
{
    public function testNonJtmPoleRejection(): void
    {
        // Protection devices and non-poles must be strictly rejected as transline endpoints
        $prohibited = [
            ['id' => 101, 'kode_asset' => 'REC-001', 'jenis_asset' => 'RECLOSER'],
            ['id' => 102, 'kode_asset' => 'PMCB-001', 'jenis_asset' => 'PMCB'],
            ['id' => 103, 'kode_asset' => 'GH-001', 'jenis_asset' => 'GH'],
            ['id' => 104, 'kode_asset' => 'LBSM-001', 'jenis_asset' => 'LBSM'],
            ['id' => 105, 'kode_asset' => 'ASS-001', 'jenis_asset' => 'ASS'],
            ['id' => 106, 'kode_asset' => 'TRAFO-001', 'jenis_asset' => 'TRAFO'],
            ['id' => 107, 'kode_asset' => 'GTT-001', 'jenis_asset' => 'GTT'],
            ['id' => 108, 'kode_asset' => 'ROW-001', 'jenis_asset' => 'POHON_ROW'],
        ];

        foreach ($prohibited as $item) {
            $check = GisTranslineService::isJtmPoleAsset($item);
            $this->assertFalse($check['valid'], "Expected {$item['jenis_asset']} to be rejected as transline endpoint.");
        }
    }

    public function testValidJtmPoleAcceptance(): void
    {
        $validPoles = [
            ['id' => 201, 'kode_asset' => 'AST-POLE-01', 'jenis_asset' => 'TIANG_BETON'],
            ['id' => 202, 'kode_asset' => 'AST-POLE-02', 'jenis_asset' => 'TIANG_BESI'],
            ['id' => 203, 'kode_asset' => 'AST-POLE-03', 'jenis_asset' => 'JTM_TIANG'],
            ['id' => 204, 'kode_asset' => 'TIANG-123', 'jenis_asset' => 'JTM'],
        ];

        foreach ($validPoles as $item) {
            $check = GisTranslineService::isJtmPoleAsset($item);
            $this->assertTrue($check['valid'], "Expected {$item['jenis_asset']} with code {$item['kode_asset']} to be accepted as JTM pole.");
        }
    }

    public function testSingleSegmentRejectsSelfLoopAndInvalidIds(): void
    {
        $service = new BatchTranslineValidationService();

        $res1 = $service->validateSingleSegment(10, 10);
        $this->assertSame('error', $res1['status']);
        $this->assertStringContainsString('self-loop', $res1['message']);

        $res2 = $service->validateSingleSegment(0, 15);
        $this->assertSame('error', $res2['status']);

        $res3 = $service->validateSingleSegment(-5, 15);
        $this->assertSame('error', $res3['status']);
    }

    public function testAssetMatrixContainsStandardKeypointCategories(): void
    {
        $matrix = AssetMatrixService::getMatrix();
        $categories = array_keys($matrix);

        $this->assertContains('RECLOSER', $categories);
        $this->assertContains('PMCB', $categories);
        $this->assertContains('GH', $categories);
        $this->assertContains('LBS', $categories);
        $this->assertContains('ASS', $categories);
    }

    public function testFaultLocatorProtectionDevicesExcludesLbsmAndAss(): void
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('assets')) {
            $this->markTestSkipped('Database assets table not available in test environment.');
        }

        $sampleDev = $db->table('assets')
            ->select('penyulang_id')
            ->whereIn('UPPER(jenis_asset)', ['RECLOSER', 'PMCB', 'GH'])
            ->where('deleted_at IS NULL')
            ->where('penyulang_id >', 0)
            ->get()->getRowArray();

        $feederId = (int)($sampleDev['penyulang_id'] ?? 1);

        $service = new FaultLocatorWorkspaceService();
        $devices = $service->getProtectionDevices($feederId);

        if (empty($devices)) {
            $this->markTestSkipped("No protection devices found for feeder #{$feederId}.");
        }

        foreach ($devices as $dev) {
            $cat = strtoupper((string)($dev['device_category'] ?? $dev['jenis_asset'] ?? ''));
            $this->assertNotSame('LBSM', $cat, 'Fault Locator protection reference must NOT include LBSM.');
            $this->assertNotSame('LBS', $cat, 'Fault Locator protection reference must NOT include LBS.');
            $this->assertNotSame('ASS', $cat, 'Fault Locator protection reference must NOT include ASS.');
            $this->assertNotSame('SECTIONALIZER', $cat, 'Fault Locator protection reference must NOT include SECTIONALIZER.');

            $this->assertContains($cat, ['RECLOSER', 'PMCB', 'GH', 'JTM_RECLOSER', 'JTM_PMCB', 'GARDU_HUBUNG'], 'Protection device must only be RECLOSER, PMCB, or GH.');
        }
    }
}
