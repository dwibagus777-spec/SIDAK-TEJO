<?php

namespace Tests\Unit;

use App\Services\GlobalAssetDeduplicationService;
use CodeIgniter\Test\CIUnitTestCase;

class GlobalAssetDeduplicationServiceTest extends CIUnitTestCase
{
    protected GlobalAssetDeduplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GlobalAssetDeduplicationService();
    }

    public function testAuditDuplicatesStructure()
    {
        $audit = $this->service->auditDuplicates();
        $this->assertIsArray($audit);
        $this->assertEquals('success', $audit['status']);
        $this->assertArrayHasKey('total_active_assets', $audit);
        $this->assertArrayHasKey('duplicate_groups_count', $audit);
        $this->assertArrayHasKey('totals_summary', $audit);
        $this->assertArrayHasKey('feeder_breakdown', $audit);
    }

    public function testAuditSyntheticDuplicatesStructure()
    {
        $audit = $this->service->auditSyntheticDuplicates();
        $this->assertIsArray($audit);
        $this->assertEquals('success', $audit['status']);
        $this->assertArrayHasKey('total_active_assets', $audit);
        $this->assertArrayHasKey('coordinate_duplicate_groups', $audit);
        $this->assertArrayHasKey('synthetic_duplicates_count', $audit);
        $this->assertArrayHasKey('projected_active_after', $audit);
        $this->assertArrayHasKey('feeder_summary', $audit);
    }

    public function testDryRunExecutionDoesNotMutate()
    {
        $result = $this->service->executeSyntheticDeduplication(false);
        $this->assertIsArray($result);
        $this->assertEquals('DRY_RUN', $result['mode']);
        $this->assertArrayHasKey('duplicate_records_to_soft_delete', $result);
        $this->assertArrayHasKey('projected_active_after', $result);
    }
}
