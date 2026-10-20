<?php

namespace Tests\Unit;

use App\Services\AssetIntegrityAuditService;
use PHPUnit\Framework\TestCase;

class AssetIntegrityAuditServiceTest extends TestCase
{
    public function testAuditReturnsExpectedStructureAndIntegrityMath(): void
    {
        $service = new AssetIntegrityAuditService();
        $audit   = $service->runAudit();

        $this->assertContains($audit['status'], ['SUCCESS', 'NO_ASSETS_TABLE']);
        $this->assertArrayHasKey('overview', $audit);
        $this->assertArrayHasKey('uniqueness', $audit);
        $this->assertArrayHasKey('feeder_mapping', $audit);
        $this->assertArrayHasKey('penyulang_audit', $audit);
        $this->assertArrayHasKey('reconciliation', $audit);

        // Verify mathematical parity: mapped + unassigned == total_active
        $mapped      = $audit['feeder_mapping']['mapped_to_feeder'];
        $unassigned  = $audit['feeder_mapping']['unassigned_no_feeder'];
        $totalActive = $audit['overview']['total_active'];

        $this->assertSame($totalActive, $mapped + $unassigned, 'Mapped assets plus unassigned assets must strictly equal total active assets');
        $this->assertTrue($audit['overview']['is_pk_unique'], 'Primary keys must be 100% unique');
    }
}
