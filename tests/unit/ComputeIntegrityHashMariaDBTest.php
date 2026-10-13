<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Controllers\AssetImportController;

/**
 * @internal
 * Integration test for computeIntegrityHash() using local MariaDB test database (G1-B)
 * This test verifies the fix works on actual MariaDB with production-like schema
 */
final class ComputeIntegrityHashMariaDBTest extends CIUnitTestCase
{
    protected $db;
    protected AssetImportController $controller;
    protected \ReflectionMethod $computeIntegrityHashMethod;

    protected function setUp(): void
    {
        parent::setUp();

        // Use our MariaDB test database instead of SQLite
        $this->db = Database::connect('test_mariadb');

        // Verify test database has required tables
        $requiredTables = [
            'assets', 'penyulang', 'ulps', 'sections',
            'gis_translines', 'asset_relationships',
            'asset_import_batches', 'network_topology_versions'
        ];

        foreach ($requiredTables as $table) {
            if (!$this->db->tableExists($table)) {
                $this->markTestSkipped("Table '$table' not found in test_mariadb database. Run setup scripts first.");
                return;
            }
        }

        // Instantiate controller and get private method via reflection
        $this->controller = new AssetImportController();
        $this->computeIntegrityHashMethod = new \ReflectionMethod(AssetImportController::class, 'computeIntegrityHash');
        $this->computeIntegrityHashMethod->setAccessible(true);

        // Initialize controller with required services
        $refRequest = new \ReflectionProperty($this->controller, 'request');
        $refRequest->setAccessible(true);
        $refRequest->setValue($this->controller, \Config\Services::request());

        $refResponse = new \ReflectionProperty($this->controller, 'response');
        $refResponse->setAccessible(true);
        $refResponse->setValue($this->controller, \Config\Services::response());
    }

    public function testComputeIntegrityHashAllTablesExist(): void
    {
        $hashes = $this->computeIntegrityHashMethod->invoke($this->controller, $this->db);

        $this->assertIsArray($hashes);

        // All 8 tables should be present in results
        $expectedTables = [
            'assets', 'penyulang', 'ulps', 'sections',
            'gis_translines', 'asset_relationships',
            'asset_import_batches', 'network_topology_versions'
        ];

        foreach ($expectedTables as $table) {
            $this->assertArrayHasKey($table, $hashes, "Table '$table' missing from integrity hash results");
        }
    }

    public function testComputeIntegrityHashNoErrors(): void
    {
        $hashes = $this->computeIntegrityHashMethod->invoke($this->controller, $this->db);

        foreach ($hashes as $table => $data) {
            $this->assertArrayNotHasKey('error', $data, "Table '$table' returned error: " . ($data['error'] ?? 'unknown'));
        }
    }

    public function testComputeIntegrityHashAssetCountsMatchBaselines(): void
    {
        $hashes = $this->computeIntegrityHashMethod->invoke($this->controller, $this->db);

        // Verify asset counts match our synthetic data baselines
        $this->assertEquals(143 + 205 + 204 + 268, $hashes['assets']['count']); // 820 total

        // 3 penyulang (12, 15, 37)
        $this->assertEquals(3, $hashes['penyulang']['count']);

        // 1 ULP
        $this->assertEquals(1, $hashes['ulps']['count']);

        // 3 sections
        $this->assertEquals(3, $hashes['sections']['count']);

        // 254 translines (245 active + 9 inactive)
        $this->assertEquals(254, $hashes['gis_translines']['count']);

        // 100 asset relationships
        $this->assertEquals(100, $hashes['asset_relationships']['count']);

        // 3 import batches
        $this->assertEquals(3, $hashes['asset_import_batches']['count']);

        // 3 network topology versions
        $this->assertEquals(3, $hashes['network_topology_versions']['count']);
    }

    public function testComputeIntegrityHashTimestampHandling(): void
    {
        $hashes = $this->computeIntegrityHashMethod->invoke($this->controller, $this->db);

        // Tables with both created_at and updated_at should have max_ts
        $bothTimestamps = ['assets', 'penyulang', 'ulps', 'sections', 'gis_translines', 'asset_relationships', 'asset_import_batches'];
        foreach ($bothTimestamps as $table) {
            $this->assertNotNull($hashes[$table]['max_ts'] ?? null, "Table '$table' should have max_ts (has both timestamps)");
        }

        // network_topology_versions only has created_at - should still have max_ts from created_at
        $this->assertNotNull($hashes['network_topology_versions']['max_ts'] ?? null, "network_topology_versions should have max_ts from created_at");
    }

    public function testNetworkTopologyVersionsOnlyCreatedAt(): void
    {
        // Verify the schema bug: network_topology_versions only has created_at
        $fields = $this->db->getFieldNames('network_topology_versions');
        $this->assertTrue(in_array('created_at', $fields, true), 'network_topology_versions should have created_at');
        $this->assertFalse(in_array('updated_at', $fields, true), 'network_topology_versions should NOT have updated_at (this is the bug we fixed)');
    }

    public function testGetTimestampExpressionHelper(): void
    {
        $method = new \ReflectionMethod(AssetImportController::class, 'getTimestampExpression');
        $method->setAccessible(true);

        // Both timestamps
        $this->assertEquals('MAX(COALESCE(updated_at, created_at))', $method->invoke($this->controller, true, true));

        // Only created_at
        $this->assertEquals('MAX(created_at)', $method->invoke($this->controller, true, false));

        // Only updated_at
        $this->assertEquals('MAX(updated_at)', $method->invoke($this->controller, false, true));

        // Neither
        $this->assertNull($method->invoke($this->controller, false, false));
    }
}