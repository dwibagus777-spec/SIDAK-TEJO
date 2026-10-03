<?php

namespace Tests\Unit;

use App\Services\AiDataAdapterService;
use App\Services\AutomaticIngestionOrchestrator;
use App\Services\ServerSideAssetIngestEngine;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

require_once APPPATH . 'Database/Migrations/2026-10-02-000001_CreateIdempotentAssetIngestSchema.php';

use App\Database\Migrations\CreateIdempotentAssetIngestSchema;

class AiDataAdapterAndIngestionWorkflowTest extends CIUnitTestCase
{
    protected AiDataAdapterService $adapter;
    protected AutomaticIngestionOrchestrator $orchestrator;
    protected ServerSideAssetIngestEngine $engine;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->adapter = new AiDataAdapterService();
        $this->engine = new ServerSideAssetIngestEngine($this->db);
        $this->orchestrator = new AutomaticIngestionOrchestrator($this->db, $this->adapter, $this->engine);

        $migration = new CreateIdempotentAssetIngestSchema();
        $migration->up();
    }

    public function testAutomaticColumnMappingAndEvidence()
    {
        $rawHeader = ['No Tiang', 'Nama Tiang', 'Lat', 'Long', 'Penyulang', 'ULP', 'Unknown_Column'];
        $result = $this->adapter->analyzeAndMapHeaders($rawHeader);

        $this->assertArrayHasKey('mapping_evidence', $result);
        $this->assertCount(7, $result['mapping_evidence']);

        $evidence = $result['mapping_evidence'];
        $this->assertEquals('kode_asset', $evidence[0]['canonical_field']);
        $this->assertEquals('nama_asset', $evidence[1]['canonical_field']);
        $this->assertEquals('latitude', $evidence[2]['canonical_field']);
        $this->assertEquals('longitude', $evidence[3]['canonical_field']);
        $this->assertEquals('UNMAPPED_COLUMN', $evidence[6]['mapping_method']);
    }

    public function testCanonicalTransformationAndNoFabrication()
    {
        $csvContent = "No Tiang,Nama Tiang,Lat,Long,Penyulang\nAST-001,TIANG_001,-7.445,112.715,FEEDER_A\n";
        $tempFile = WRITEPATH . 'uploads/test_transform_' . bin2hex(random_bytes(4)) . '.csv';
        file_put_contents($tempFile, $csvContent);

        $analysis = $this->adapter->parseAndTransformToCanonical($tempFile);
        @unlink($tempFile);

        $this->assertEquals(1, $analysis['source_rows']);
        $row = $analysis['canonical_rows'][0];

        $this->assertEquals('AST-001', $row['kode_asset']);
        $this->assertEquals('TIANG_001', $row['nama_asset']);
        $this->assertEquals(-7.445, $row['latitude']);
        $this->assertEquals(112.715, $row['longitude']);

        // Safety Invariant: AI MUST NOT fabricate missing physical data
        $this->assertNull($row['parent_asset_id'], 'parent_asset_id must remain NULL when not in source.');
        $this->assertNull($row['section_id'], 'section_id must remain NULL when not in source.');
        $this->assertNull($row['sequence_no'], 'sequence_no must remain NULL when not in source.');
    }

    public function testEndToEndIngestionWorkflowWithPart01()
    {
        $csvPath = 'C:\\Users\\INSPEKSIKOTA\\Downloads\\SIDAK_TEJO_ASSET_IMPORT_PART_01_OF_20.csv';
        if (!file_exists($csvPath)) {
            $this->markTestSkipped("Staged PART 01 CSV not found at {$csvPath}");
        }

        $result = $this->orchestrator->executeEndToEndIngestion($csvPath);

        $this->assertEquals('COMPLETED', $result['status']);
        $this->assertEquals(100, $result['progress']);
        $this->assertEquals(2000, $result['source_rows']);
        $this->assertEquals(0, $result['duplicate_assets']);
        $this->assertEquals(0, $result['duplicate_translines']);
        $this->assertArrayHasKey('new_topology_snapshot', $result);
    }

    public function testRepeatedUploadIdempotencyAndZeroDuplicate()
    {
        $csvPath = 'C:\\Users\\INSPEKSIKOTA\\Downloads\\SIDAK_TEJO_ASSET_IMPORT_PART_01_OF_20.csv';
        if (!file_exists($csvPath)) {
            $this->markTestSkipped("Staged PART 01 CSV not found at {$csvPath}");
        }

        // Run 1
        $res1 = $this->orchestrator->executeEndToEndIngestion($csvPath);
        $this->assertEquals(0, $res1['duplicate_assets']);

        // Run 2 (Replay)
        $res2 = $this->orchestrator->executeEndToEndIngestion($csvPath);
        $this->assertEquals(0, $res2['duplicate_assets']);
        $this->assertGreaterThanOrEqual(2000, $res2['assets_reused']);
    }
}
