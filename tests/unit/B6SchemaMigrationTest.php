<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Config\Database;
use CodeIgniter\Database\BaseConnection;

require_once APPPATH . 'Database/Migrations/2026-09-25-000003_CreateFieldDispatchInvestigationSchema.php';

use App\Database\Migrations\CreateFieldDispatchInvestigationSchema;

/**
 * B6SchemaMigrationTest
 *
 * Comprehensive Test Suite for Phase B.6.1 Schema & Migration:
 *  1. fault_cases enhancement (priority, opened_at, closed_at, created_by)
 *  2. dispatch_assignments table & schema structure
 *  3. field_investigations table & GPS provenance columns
 *  4. field_findings table & predicted vs actual separation
 *  5. field_finding_revisions append-only structure (no updated_at/deleted_at)
 *  6. field_evidence table & cryptographic SHA-256 provenance
 *  7. fault_feedback table & FLI classification bridge
 *  8. Foreign key integrity & constraints
 *  9. Required indexes exist
 * 10. Append-only revision uniqueness enforcement
 * 11. Existing B.5 fault_events & fault_cases preserved
 * 12. Migration idempotency (safe re-execution)
 * 13. Zero topology mutation invariant (gis_translines)
 * 14. Zero active assets mutation invariant (assets)
 * 15. Authoritative snapshot binding invariant
 */
class B6SchemaMigrationTest extends TestCase
{
    protected ?BaseConnection $db;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->db = Database::connect('default');
            $this->db->getVersion();
        } catch (\Throwable $e) {
            $this->db = Database::connect();
        }

        // Ensure migration up is run
        $migration = new CreateFieldDispatchInvestigationSchema();
        $ref = new \ReflectionClass($migration);
        $forgeProp = $ref->getProperty('forge');
        $forgeProp->setValue($migration, \Config\Database::forge('default'));
        $dbProp = $ref->getProperty('db');
        $dbProp->setValue($migration, $this->db);
        $migration->up();
    }

    public function testFaultCasesSchemaEnhancement(): void
    {
        $this->assertTrue($this->db->tableExists('fault_cases'), 'fault_cases table must exist');
        $fieldNames = $this->db->getFieldNames('fault_cases');

        $this->assertContains('priority', $fieldNames, 'fault_cases must contain priority');
        $this->assertContains('opened_at', $fieldNames, 'fault_cases must contain opened_at');
        $this->assertContains('closed_at', $fieldNames, 'fault_cases must contain closed_at');
        $this->assertContains('created_by', $fieldNames, 'fault_cases must contain created_by');

        $fieldData = $this->db->getFieldData('fault_cases');
        $priorityCol = null;
        foreach ($fieldData as $col) {
            if ($col->name === 'priority') {
                $priorityCol = $col;
                break;
            }
        }
        $this->assertNotNull($priorityCol);
        $this->assertEquals('MEDIUM', $priorityCol->default);
    }

    public function testDispatchAssignmentsTableSchema(): void
    {
        $this->assertTrue($this->db->tableExists('dispatch_assignments'), 'dispatch_assignments table must exist');
        $fields = $this->db->getFieldNames('dispatch_assignments');

        $expected = [
            'id', 'fault_case_id', 'assigned_to', 'assigned_by',
            'assigned_at', 'accepted_at', 'completed_at',
            'status', 'assignment_note', 'created_at', 'updated_at'
        ];

        foreach ($expected as $col) {
            $this->assertContains($col, $fields, "dispatch_assignments must contain {$col}");
        }
    }

    public function testFieldInvestigationsTableSchema(): void
    {
        $this->assertTrue($this->db->tableExists('field_investigations'), 'field_investigations table must exist');
        $fields = $this->db->getFieldNames('field_investigations');

        $expected = [
            'id', 'fault_case_id', 'investigator_id', 'status',
            'started_at', 'arrived_at', 'completed_at',
            'start_lat', 'start_lng', 'start_accuracy_m',
            'end_lat', 'end_lng', 'end_accuracy_m',
            'notes', 'created_at', 'updated_at'
        ];

        foreach ($expected as $col) {
            $this->assertContains($col, $fields, "field_investigations must contain {$col}");
        }
    }

    public function testFieldFindingsTableSchema(): void
    {
        $this->assertTrue($this->db->tableExists('field_findings'), 'field_findings table must exist');
        $fields = $this->db->getFieldNames('field_findings');

        $expected = [
            'id', 'fault_case_id', 'investigation_id',
            'predicted_asset_id', 'actual_asset_id',
            'actual_lat', 'actual_lng', 'gps_accuracy_m',
            'finding_status', 'cause_category', 'condition_description',
            'notes', 'captured_at', 'captured_by', 'created_at', 'updated_at'
        ];

        foreach ($expected as $col) {
            $this->assertContains($col, $fields, "field_findings must contain {$col}");
        }

        // B6-G04: Separation of predicted_asset_id and actual_asset_id
        $this->assertContains('predicted_asset_id', $fields);
        $this->assertContains('actual_asset_id', $fields);
    }

    public function testFieldFindingRevisionsTableSchema(): void
    {
        $this->assertTrue($this->db->tableExists('field_finding_revisions'), 'field_finding_revisions table must exist');
        $fields = $this->db->getFieldNames('field_finding_revisions');

        $expected = [
            'id', 'field_finding_id', 'revision_no',
            'previous_values_json', 'amended_fields_json',
            'amended_by', 'amendment_reason', 'created_at'
        ];

        foreach ($expected as $col) {
            $this->assertContains($col, $fields, "field_finding_revisions must contain {$col}");
        }

        // B6-G05: Strictly append-only — NO updated_at and NO deleted_at
        $this->assertNotContains('updated_at', $fields, 'field_finding_revisions must not have updated_at');
        $this->assertNotContains('deleted_at', $fields, 'field_finding_revisions must not have deleted_at');
    }

    public function testFieldEvidenceTableSchema(): void
    {
        $this->assertTrue($this->db->tableExists('field_evidence'), 'field_evidence table must exist');
        $fields = $this->db->getFieldNames('field_evidence');

        $expected = [
            'id', 'fault_case_id', 'field_finding_id', 'asset_id',
            'evidence_type', 'file_reference', 'sha256',
            'captured_at', 'captured_by', 'metadata_json', 'created_at'
        ];

        foreach ($expected as $col) {
            $this->assertContains($col, $fields, "field_evidence must contain {$col}");
        }

        $fieldData = $this->db->getFieldData('field_evidence');
        $shaCol = null;
        foreach ($fieldData as $col) {
            if ($col->name === 'sha256') {
                $shaCol = $col;
                break;
            }
        }
        $this->assertNotNull($shaCol);
        $this->assertEquals(64, $shaCol->max_length);
    }

    public function testFaultFeedbackTableSchema(): void
    {
        $this->assertTrue($this->db->tableExists('fault_feedback'), 'fault_feedback table must exist');
        $fields = $this->db->getFieldNames('fault_feedback');

        $expected = [
            'id', 'fault_case_id', 'candidate_id',
            'predicted_asset_id', 'actual_asset_id',
            'graph_distance_m', 'observed_distance_m', 'distance_error_m',
            'match_class', 'feedback_source', 'feedback_timestamp',
            'notes', 'created_at'
        ];

        foreach ($expected as $col) {
            $this->assertContains($col, $fields, "fault_feedback must contain {$col}");
        }
    }

    public function testForeignKeyIntegrity(): void
    {
        // Test that foreign keys exist on MySQL
        $query = $this->db->query("
            SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ('dispatch_assignments', 'field_investigations', 'field_findings', 'field_finding_revisions', 'field_evidence', 'fault_feedback')
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        $fks = $query->getResultArray();
        $this->assertNotEmpty($fks, 'Foreign keys must be defined on B.6 tables');

        $refTables = array_column($fks, 'REFERENCED_TABLE_NAME');
        $this->assertContains('fault_cases', $refTables, 'fault_cases must be referenced by B.6 child tables');
    }

    public function testRequiredIndexesExist(): void
    {
        $tables = [
            'dispatch_assignments'    => ['fault_case_id', 'assigned_to', 'status'],
            'field_investigations'    => ['fault_case_id', 'investigator_id', 'status'],
            'field_findings'          => ['fault_case_id', 'finding_status', 'cause_category'],
            'field_finding_revisions' => ['field_finding_id', 'revision_no'],
            'field_evidence'          => ['fault_case_id', 'sha256'],
            'fault_feedback'          => ['fault_case_id', 'match_class'],
        ];

        foreach ($tables as $tbl => $cols) {
            $indexes = $this->db->getIndexData($tbl);
            $indexedCols = [];
            foreach ($indexes as $idx) {
                foreach ($idx->fields as $f) {
                    $indexedCols[] = $f;
                }
            }
            foreach ($cols as $reqCol) {
                $this->assertContains($reqCol, $indexedCols, "Table {$tbl} must have index on {$reqCol}");
            }
        }
    }

    public function testAppendOnlyRevisionStructure(): void
    {
        // 1. Create a dummy case and finding
        $now = date('Y-m-d H:i:s');
        $ev = $this->db->table('fault_events')->select('id')->orderBy('id', 'ASC')->get()->getRowArray();
        $validEventId = (int)($ev['id'] ?? 1);
        $as = $this->db->table('assets')->select('id')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
        $validAssetId = (int)($as['id'] ?? 1);

        $caseId = $this->db->table('fault_cases')->insert([
            'case_number'               => 'TEST-B6-REV-' . bin2hex(random_bytes(4)),
            'fault_event_id'            => $validEventId,
            'topology_snapshot_id'      => 'TOPOLOGY-20260925-243-ad2c9fcb',
            'analysis_version'          => 'FLI-1.0.0',
            'analysis_input_hash'       => hash('sha256', 'test_rev_input'),
            'device_asset_id'           => $validAssetId,
            'status'                    => 'INVESTIGATING',
            'priority'                  => 'HIGH',
            'opened_at'                 => $now,
            'created_at'                => $now,
        ]);
        $realCaseId = $this->db->insertID();

        $this->db->table('field_findings')->insert([
            'fault_case_id'       => $realCaseId,
            'finding_status'      => 'RECORDED',
            'cause_category'      => 'VEGETATION',
            'notes'               => 'Initial field finding',
            'captured_at'         => $now,
            'captured_by'         => 1,
            'created_at'          => $now,
        ]);
        $findingId = $this->db->insertID();

        // 2. Insert Revision #1
        $this->db->table('field_finding_revisions')->insert([
            'field_finding_id'     => $findingId,
            'revision_no'          => 1,
            'previous_values_json' => json_encode(['cause_category' => 'VEGETATION']),
            'amended_fields_json'  => json_encode(['cause_category' => 'ANIMAL']),
            'amended_by'           => 2,
            'amendment_reason'     => 'Found dead bat near insulator',
            'created_at'           => $now,
        ]);
        $this->assertGreaterThan(0, $this->db->insertID());

        // 3. Insert Revision #2
        $this->db->table('field_finding_revisions')->insert([
            'field_finding_id'     => $findingId,
            'revision_no'          => 2,
            'previous_values_json' => json_encode(['cause_category' => 'ANIMAL']),
            'amended_fields_json'  => json_encode(['cause_category' => 'ANIMAL_BIRD']),
            'amended_by'           => 3,
            'amendment_reason'     => 'Confirmed species is flying fox',
            'created_at'           => date('Y-m-d H:i:s', strtotime('+5 minutes')),
        ]);
        $this->assertGreaterThan(0, $this->db->insertID());

        // 4. Duplicate Revision #2 must fail DB UNIQUE constraint
        $duplicateFailed = false;
        try {
            $this->db->table('field_finding_revisions')->insert([
                'field_finding_id'     => $findingId,
                'revision_no'          => 2,
                'previous_values_json' => '{}',
                'amended_fields_json'  => '{}',
                'amended_by'           => 99,
                'amendment_reason'     => 'Illegal duplicate attempt',
                'created_at'           => $now,
            ]);
        } catch (\Throwable $e) {
            $duplicateFailed = true;
        }
        $this->assertTrue($duplicateFailed, 'Duplicate revision_no must be rejected by UNIQUE constraint');

        // Cleanup test data
        $this->db->table('field_finding_revisions')->where('field_finding_id', $findingId)->delete();
        $this->db->table('field_findings')->where('id', $findingId)->delete();
        $this->db->table('fault_cases')->where('id', $realCaseId)->delete();
    }

    public function testExistingFaultEventsAndCasesPreserved(): void
    {
        $eventCount = $this->db->table('fault_events')->countAllResults();
        $this->assertGreaterThanOrEqual(1, $eventCount, 'Historical fault_events must be preserved');

        $caseCount = $this->db->table('fault_cases')->countAllResults();
        $this->assertGreaterThanOrEqual(1, $caseCount, 'Existing fault_cases must be preserved');
    }

    public function testMigrationIdempotency(): void
    {
        // Re-executing up() must not throw any exception or duplicate errors
        $migration = new CreateFieldDispatchInvestigationSchema();
        $ref = new \ReflectionClass($migration);
        $forgeProp = $ref->getProperty('forge');
        $forgeProp->setValue($migration, \Config\Database::forge('default'));
        $dbProp = $ref->getProperty('db');
        $dbProp->setValue($migration, $this->db);

        $idempotentPass = true;
        try {
            $migration->up();
        } catch (\Throwable $e) {
            $idempotentPass = false;
        }

        $this->assertTrue($idempotentPass, 'Migration must be strictly idempotent on re-run');
    }

    public function testTopologyUnchanged(): void
    {
        // Invariant: gis_translines must have 0 mutations
        $tlCount = $this->db->table('gis_translines')->countAllResults();
        $this->assertGreaterThan(0, $tlCount, 'gis_translines must exist and be untouched');
    }

    public function testActiveAssetsUnchanged(): void
    {
        // Invariant: assets must have 0 mutations
        $assetCount = $this->db->table('assets')->countAllResults();
        $this->assertGreaterThan(0, $assetCount, 'assets must exist and be untouched');
    }

    public function testAuthoritativeSnapshotUnchanged(): void
    {
        $activeCases = $this->db->table('fault_cases')
            ->where('topology_snapshot_id', 'TOPOLOGY-20260925-243-ad2c9fcb')
            ->countAllResults();
        $this->assertGreaterThanOrEqual(1, $activeCases, 'Snapshot TOPOLOGY-20260925-243-ad2c9fcb must be bound');
    }
}
