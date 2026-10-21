<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\FaultLocatorWorkspaceService;
use App\Services\PlnStandardBomRuleService;
use App\Services\GisTranslineService;
use Config\Database;

class FaultLocatorNetworkContextAndBomTest extends CIUnitTestCase
{
    protected FaultLocatorWorkspaceService $workspaceService;
    protected PlnStandardBomRuleService $bomRuleService;
    protected GisTranslineService $translineService;
    protected $dbConn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbConn = Database::connect('default');
        $this->workspaceService = new FaultLocatorWorkspaceService();
        $this->bomRuleService = new PlnStandardBomRuleService($this->dbConn);
        $this->translineService = new GisTranslineService($this->dbConn);
    }

    public function testGetFeederNetworkContextStructureAndValues(): void
    {
        $feeder = $this->dbConn->table('penyulang')->get()->getRowArray();
        if (!$feeder) {
            $this->markTestSkipped('No feeder found in default database.');
        }

        $penyulangId = (int)$feeder['id'];
        $response = $this->workspaceService->getFeederNetworkContext($penyulangId);

        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('data', $response);

        $data = $response['data'];
        $this->assertArrayHasKey('feeder', $data);
        $this->assertArrayHasKey('conductor', $data);
        $this->assertArrayHasKey('topology', $data);
        $this->assertArrayHasKey('protection_devices', $data);

        $this->assertEquals($penyulangId, $data['feeder']['id']);
        $this->assertArrayHasKey('official_type', $data['conductor']);
        $this->assertArrayHasKey('total_span_meters', $data['topology']);
        $this->assertArrayHasKey('valid_jtm_poles_count', $data['topology']);
        $this->assertArrayHasKey('available', $data['protection_devices']);
        $this->assertIsArray($data['protection_devices']['devices']);
    }

    public function testArchiveProtoTestCasesDryRun(): void
    {
        $result = $this->workspaceService->archiveProtoTestCases(false);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('archived_count', $result);
        if (isset($result['executed'])) {
            $this->assertFalse($result['executed']);
        }
    }

    public function testGetCanonicalBomMaterialSummaryFormat(): void
    {
        if (!$this->dbConn->tableExists('temuan')) {
            $this->markTestSkipped('temuan table not found.');
        }

        $summary = $this->bomRuleService->getCanonicalBomMaterialSummary();

        $this->assertIsArray($summary);
        if (!empty($summary)) {
            $first = $summary[0];
            $this->assertArrayHasKey('material_code', $first);
            $this->assertArrayHasKey('nama_material', $first);
            $this->assertArrayHasKey('satuan', $first);
            $this->assertArrayHasKey('total_qty', $first);
            $this->assertArrayHasKey('total_used_qty', $first);
            $this->assertArrayHasKey('sisa_qty', $first);
        }
    }

    public function testReconcileNonPoleTranslinesDryRun(): void
    {
        if (!$this->dbConn->tableExists('gis_translines')) {
            $this->markTestSkipped('gis_translines table not found.');
        }

        $result = $this->translineService->reconcileNonPoleTranslines(false);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        if ($result['status'] === 'success') {
            $this->assertArrayHasKey('total_violations', $result);
            $this->assertArrayHasKey('executed', $result);
            $this->assertFalse($result['executed']);
        }
    }
}
