<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\InspectionWorkflowService;

/**
 * Class InspectionWorkflowServiceTest
 *
 * Verifies CR-INSPECTION-MOBILE-01 & Inspection Lifecycle State Machine:
 * 1. Lifecycle states: PLANNED_PENDING, IN_PROGRESS, INSPECTED, NOT_PLANNED.
 * 2. Invariant: INSPECTED != HAS_FINDING (clean inspection with 0 findings is valid).
 * 3. Atomic state transitions: PENDING -> IN_PROGRESS -> INSPECTED.
 * 4. Finding accumulation: recordFindingInspection increments finding count properly.
 * 5. Return context contract in GIS views (from=gis_inspection, focus_asset_id, lat, lng, zoom=19).
 */
class InspectionWorkflowServiceTest extends CIUnitTestCase
{
    protected $db;
    protected InspectionWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->setupSchema();
        $this->service = new InspectionWorkflowService($this->db);
    }

    protected function setupSchema(): void
    {
        $forge = Database::forge();

        if (!$this->db->tableExists('asset_inspection_states')) {
            $forge->addField([
                'id'            => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'planning_id'   => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'asset_id'      => ['type' => 'INT', 'constraint' => 11],
                'status'        => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'PLANNED_PENDING'],
                'finding_count' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'inspected_by'  => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'inspected_at'  => ['type' => 'DATETIME', 'null' => true],
                'notes'         => ['type' => 'TEXT', 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('asset_inspection_states', true);
        } else {
            $this->db->table('asset_inspection_states')->emptyTable();
        }
    }

    /**
     * 1. Assert default lifecycle state for unplanned asset is NOT_PLANNED.
     */
    public function testDefaultUnplannedAssetReturnsNotPlanned(): void
    {
        $state = $this->service->getLifecycleState(999, 0);

        $this->assertSame(InspectionWorkflowService::STATE_NOT_PLANNED, $state['status']);
        $this->assertSame(0, $state['finding_count']);
        $this->assertSame('#94a3b8', $state['color']);
    }

    /**
     * 2. Assert Invariant: INSPECTED != HAS_FINDING
     * A clean inspection must transition to INSPECTED with finding_count = 0.
     */
    public function testCleanInspectionWithZeroFindingsSetsInspected(): void
    {
        $assetId = 101;
        $planningId = 5;
        $inspectorId = 12;

        // First initialize as planned pending
        $this->db->table('asset_inspection_states')->insert([
            'asset_id'      => $assetId,
            'planning_id'   => $planningId,
            'status'        => InspectionWorkflowService::STATE_PLANNED_PENDING,
            'finding_count' => 0,
        ]);

        // Operator performs clean inspection (no defects found)
        $result = $this->service->recordCleanInspection($assetId, $planningId, $inspectorId, 'Tiang & isolator bersih');

        $this->assertTrue($result['updated']);
        $this->assertSame(InspectionWorkflowService::STATE_INSPECTED, $result['status']);
        $this->assertSame(0, $result['finding_count'], 'Finding count must remain 0 for clean inspection');

        // Verify read state
        $read = $this->service->getLifecycleState($assetId, $planningId);
        $this->assertSame(InspectionWorkflowService::STATE_INSPECTED, $read['status']);
        $this->assertSame(0, $read['finding_count']);
        $this->assertSame('#16a34a', $read['color']);
        $this->assertNotNull($read['inspected_at']);
    }

    /**
     * 3. Assert recordFindingInspection transitions to INSPECTED and increments finding_count.
     */
    public function testFindingInspectionIncrementsFindingCount(): void
    {
        $assetId = 202;
        $planningId = 5;
        $inspectorId = 12;

        // Initialize as in progress
        $this->db->table('asset_inspection_states')->insert([
            'asset_id'      => $assetId,
            'planning_id'   => $planningId,
            'status'        => InspectionWorkflowService::STATE_IN_PROGRESS,
            'finding_count' => 0,
        ]);

        // Operator records 1st finding
        $res1 = $this->service->recordFindingInspection($assetId, $planningId, $inspectorId, 'Isolator flashover');
        $this->assertSame(InspectionWorkflowService::STATE_INSPECTED, $res1['status']);
        $this->assertSame(1, $res1['finding_count']);

        // Operator records 2nd finding on same asset
        $res2 = $this->service->recordFindingInspection($assetId, $planningId, $inspectorId, 'Arrester pecah');
        $this->assertSame(InspectionWorkflowService::STATE_INSPECTED, $res2['status']);
        $this->assertSame(2, $res2['finding_count']);

        $read = $this->service->getLifecycleState($assetId, $planningId);
        $this->assertSame(2, $read['finding_count']);
    }

    /**
     * 4. Assert markInProgress transitions state properly.
     */
    public function testMarkInProgressTransition(): void
    {
        $assetId = 303;
        $planningId = 10;

        $this->db->table('asset_inspection_states')->insert([
            'asset_id'      => $assetId,
            'planning_id'   => $planningId,
            'status'        => InspectionWorkflowService::STATE_PLANNED_PENDING,
            'finding_count' => 0,
        ]);

        $result = $this->service->markInProgress($assetId, $planningId, 15);
        $this->assertTrue($result['updated']);
        $this->assertSame(InspectionWorkflowService::STATE_IN_PROGRESS, $result['status']);

        $read = $this->service->getLifecycleState($assetId, $planningId);
        $this->assertSame(InspectionWorkflowService::STATE_IN_PROGRESS, $read['status']);
        $this->assertSame('#0284c7', $read['color']);
    }

    /**
     * 5. Verify Mobile GIS Return Context Contract parameters across views and controllers.
     */
    public function testMobileGisReturnContextContract(): void
    {
        $createView = file_get_contents(APPPATH . 'Views/temuan/create.php');
        $detailView = file_get_contents(APPPATH . 'Views/temuan/detail.php');
        $gisView    = file_get_contents(APPPATH . 'Views/gis/index.php');

        // Form create must pass through return contract fields
        $this->assertStringContainsString('name="from"', $createView);
        $this->assertStringContainsString('name="focus_asset_id"', $createView);
        $this->assertStringContainsString('name="context_lat"', $createView);
        $this->assertStringContainsString('name="context_lng"', $createView);
        $this->assertStringContainsString('name="context_zoom"', $createView);

        // Detail view must contain return link to GIS with focus_asset_id and updated
        $this->assertStringContainsString('site_url(\'gis\')', $detailView);
        $this->assertStringContainsString('focus_asset_id', $detailView);
        $this->assertStringContainsString('gisReturnUrl', $detailView);

        // GIS view must parse focus_asset_id and handle updated toast notification
        $this->assertStringContainsString('focus_asset_id', $gisView);
        $this->assertStringContainsString('flyTo', $gisView);
    }
}
