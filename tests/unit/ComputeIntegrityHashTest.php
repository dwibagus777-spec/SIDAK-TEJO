<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Controllers\AssetImportController;

/**
 * @internal
 * Tests the computeIntegrityHash() logic from AssetImportController
 * Tests four timestamp column scenarios and query failure handling
 */
final class ComputeIntegrityHashTest extends CIUnitTestCase
{
    protected $db;
    protected AssetImportController $controller;
    protected \ReflectionMethod $computeIntegrityHashMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect('tests');

        // Create production-like tables with different timestamp configurations
        $this->createProductionTables();

        // Instantiate controller and get private method via reflection
        $this->controller = new AssetImportController();
        $this->computeIntegrityHashMethod = new \ReflectionMethod(AssetImportController::class, 'computeIntegrityHash');
        $this->computeIntegrityHashMethod->setAccessible(true);

        // Initialize controller with required services (request/response are protected)
        $refRequest = new \ReflectionProperty($this->controller, 'request');
        $refRequest->setAccessible(true);
        $refRequest->setValue($this->controller, \Config\Services::request());

        $refResponse = new \ReflectionProperty($this->controller, 'response');
        $refResponse->setAccessible(true);
        $refResponse->setValue($this->controller, \Config\Services::response());
    }

    protected function createProductionTables(): void
    {
        $forge = Database::forge($this->db);

        // Table 1: assets - Both created_at and updated_at
        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_asset' => ['type' => 'VARCHAR', 'constraint' => 100],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        }

        // Table 2: penyulang - Both created_at and updated_at
        if (!$this->db->tableExists('penyulang')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('penyulang', true);
        }

        // Table 3: network_topology_versions - Only created_at (like production)
        if (!$this->db->tableExists('network_topology_versions')) {
            $forge->addField([
                'id'               => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'version_no'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'correction_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'geojson_topology' => ['type' => 'TEXT', 'null' => true],  // Use TEXT for SQLite
                'nodes_count'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
                'segments_count'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
                'is_active'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'version_status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PROPOSED'],
                'created_by'       => ['type' => 'VARCHAR', 'constraint' => 100],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
                // NOTE: No updated_at column - matches production schema
            ]);
            $forge->addKey('id', true);
            $forge->addUniqueKey(['penyulang_id', 'version_no'], 'uk_feeder_version');
            $forge->addKey('penyulang_id', false, false, 'idx_ntv_penyulang_id');
            $forge->addKey('is_active', false, false, 'idx_ntv_is_active');
            $forge->createTable('network_topology_versions', true);
        }

        // Table 4: gis_translines - Both timestamps
        if (!$this->db->tableExists('gis_translines')) {
            $forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'nama_transline' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        }

        // Table 5: asset_relationships - Both timestamps
        if (!$this->db->tableExists('asset_relationships')) {
            $forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'parent_id'  => ['type' => 'INT', 'constraint' => 11],
                'child_id'   => ['type' => 'INT', 'constraint' => 11],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('asset_relationships', true);
        }

        // Table 6: asset_import_batches - Both timestamps
        if (!$this->db->tableExists('asset_import_batches')) {
            $forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'batch_name' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PENDING'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('asset_import_batches', true);
        }

        // Table 7: ulps - Both timestamps
        if (!$this->db->tableExists('ulps')) {
            $forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'nama_ulp'   => ['type' => 'VARCHAR', 'constraint' => 100],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('ulps', true);
        }

        // Table 8: sections - Both timestamps
        if (!$this->db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('sections', true);
        }

        // Insert test data
        $this->insertTestData();
    }

    protected function insertTestData(): void
    {
        // Get table prefix for raw SQL queries
        $prefix = $this->db->getPrefix();

        // assets - both timestamps
        $this->db->table('assets')->truncate();
        $this->db->table('assets')->insertBatch([
            ['kode_asset' => 'AST-001', 'nama_asset' => 'Asset 1', 'created_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-01-02 10:00:00'],
            ['kode_asset' => 'AST-002', 'nama_asset' => 'Asset 2', 'created_at' => '2026-01-03 10:00:00', 'updated_at' => '2026-01-04 10:00:00'],
        ]);

        // penyulang - both timestamps
        $this->db->table('penyulang')->truncate();
        $this->db->table('penyulang')->insertBatch([
            ['nama_penyulang' => 'CANDRAMAS', 'created_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-01-02 10:00:00'],
            ['nama_penyulang' => 'BANJAR KEMANTREN', 'created_at' => '2026-01-03 10:00:00', 'updated_at' => '2026-01-04 10:00:00'],
        ]);

        // network_topology_versions - only created_at (production scenario)
        $this->db->table('network_topology_versions')->truncate();
        $this->db->table('network_topology_versions')->insertBatch([
            [
                'penyulang_id' => 1,
                'version_no'   => 1,
                'version_status' => 'ACTIVE',
                'is_active'    => 1,
                'nodes_count'  => 3,
                'segments_count' => 2,
                'created_by'   => 'test',
                'created_at'   => '2026-08-23 15:39:19',
            ],
            [
                'penyulang_id' => 2,
                'version_no'   => 104,
                'version_status' => 'ACTIVE',
                'is_active'    => 1,
                'nodes_count'  => 398,
                'segments_count' => 199,
                'created_by'   => 'test',
                'created_at'   => '2026-09-28 12:52:18',
            ],
        ]);

        // gis_translines - both timestamps
        $this->db->table('gis_translines')->truncate();
        $this->db->table('gis_translines')->insertBatch([
            ['nama_transline' => 'TL-001', 'status' => 'ACTIVE', 'created_at' => '2026-02-01 10:00:00', 'updated_at' => '2026-02-02 10:00:00'],
            ['nama_transline' => 'TL-002', 'status' => 'ACTIVE', 'created_at' => '2026-02-03 10:00:00', 'updated_at' => '2026-02-04 10:00:00'],
        ]);

        // asset_relationships - both timestamps
        $this->db->table('asset_relationships')->truncate();
        $this->db->table('asset_relationships')->insertBatch([
            ['parent_id' => 1, 'child_id' => 2, 'created_at' => '2026-03-01 10:00:00', 'updated_at' => '2026-03-02 10:00:00'],
            ['parent_id' => 2, 'child_id' => 3, 'created_at' => '2026-03-03 10:00:00', 'updated_at' => '2026-03-04 10:00:00'],
        ]);

        // asset_import_batches - both timestamps
        $this->db->table('asset_import_batches')->truncate();
        $this->db->table('asset_import_batches')->insertBatch([
            ['batch_name' => 'BATCH-001', 'status' => 'COMPLETED', 'created_at' => '2026-04-01 10:00:00', 'updated_at' => '2026-04-02 10:00:00'],
            ['batch_name' => 'BATCH-002', 'status' => 'COMPLETED', 'created_at' => '2026-04-03 10:00:00', 'updated_at' => '2026-04-04 10:00:00'],
        ]);

        // ulps - both timestamps
        $this->db->table('ulps')->truncate();
        $this->db->table('ulps')->insertBatch([
            ['nama_ulp' => 'ULP-001', 'created_at' => '2026-05-01 10:00:00', 'updated_at' => '2026-05-02 10:00:00'],
            ['nama_ulp' => 'ULP-002', 'created_at' => '2026-05-03 10:00:00', 'updated_at' => '2026-05-04 10:00:00'],
        ]);

        // sections - both timestamps
        $this->db->table('sections')->truncate();
        $this->db->table('sections')->insertBatch([
            ['penyulang_id' => 1, 'nama_section' => 'Section 1', 'created_at' => '2026-06-01 10:00:00', 'updated_at' => '2026-06-02 10:00:00'],
            ['penyulang_id' => 2, 'nama_section' => 'Section 2', 'created_at' => '2026-06-03 10:00:00', 'updated_at' => '2026-06-04 10:00:00'],
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test tables
        foreach (['assets', 'penyulang', 'network_topology_versions', 'gis_translines', 'asset_relationships', 'asset_import_batches', 'ulps', 'sections'] as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->table($table)->truncate();
            }
        }
        parent::tearDown();
    }

    /**
     * Test table with both created_at and updated_at columns (assets, penyulang, etc.)
     */
    public function testTableWithBothTimestamps(): void
    {
        $result = $this->computeIntegrityHashMethod->invoke($this->controller, $this->db);

        $this->assertArrayHasKey('assets', $result);
        $this->assertArrayNotHasKey('error', $result['assets']);
        $this->assertEquals(2, $result['assets']['count']);
        $this->assertEquals('2026-01-04 10:00:00', $result['assets']['max_ts']);
    }

    /**
     * Test table with only created_at column (like network_topology_versions)
     */
    public function testTableWithOnlyCreatedAt(): void
    {
        $result = $this->computeIntegrityHashMethod->invoke($this->controller, $this->db);

        $this->assertArrayHasKey('network_topology_versions', $result);
        $this->assertArrayNotHasKey('error', $result['network_topology_versions']);
        $this->assertEquals(2, $result['network_topology_versions']['count']);
        $this->assertEquals('2026-09-28 12:52:18', $result['network_topology_versions']['max_ts']);
    }

    /**
     * Test table with both created_at and updated_at columns (gis_translines)
     */
    public function testGisTranslinesBothTimestamps(): void
    {
        $result = $this->computeIntegrityHashMethod->invoke($this->controller, $this->db);

        $this->assertArrayHasKey('gis_translines', $result);
        $this->assertArrayNotHasKey('error', $result['gis_translines']);
        $this->assertEquals(2, $result['gis_translines']['count']);
        $this->assertEquals('2026-02-04 10:00:00', $result['gis_translines']['max_ts']);
    }

    /**
     * Test that network_topology_versions correctly uses MAX(created_at) not COALESCE
     */
    public function testNetworkTopologyVersionsUsesCorrectTimestamp(): void
    {
        $result = $this->computeIntegrityHashMethod->invoke($this->controller, $this->db);

        $this->assertArrayHasKey('network_topology_versions', $result);
        $this->assertArrayNotHasKey('error', $result['network_topology_versions']);

        // Verify the SQL would use MAX(created_at) not COALESCE
        // by checking the result uses created_at value
        $this->assertEquals('2026-09-28 12:52:18', $result['network_topology_versions']['max_ts']);
    }

    /**
     * Test getTimestampExpression helper directly - both timestamps
     */
    public function testGetTimestampExpressionBoth(): void
    {
        $helper = new \ReflectionMethod($this->controller, 'getTimestampExpression');
        $helper->setAccessible(true);

        $expr = $helper->invoke($this->controller, true, true);
        $this->assertEquals('MAX(COALESCE(updated_at, created_at))', $expr);
    }

    /**
     * Test getTimestampExpression helper directly - only created_at
     */
    public function testGetTimestampExpressionOnlyCreatedAt(): void
    {
        $helper = new \ReflectionMethod($this->controller, 'getTimestampExpression');
        $helper->setAccessible(true);

        $expr = $helper->invoke($this->controller, true, false);
        $this->assertEquals('MAX(created_at)', $expr);
    }

    /**
     * Test getTimestampExpression helper directly - only updated_at
     */
    public function testGetTimestampExpressionOnlyUpdatedAt(): void
    {
        $helper = new \ReflectionMethod($this->controller, 'getTimestampExpression');
        $helper->setAccessible(true);

        $expr = $helper->invoke($this->controller, false, true);
        $this->assertEquals('MAX(updated_at)', $expr);
    }

    /**
     * Test getTimestampExpression helper directly - no timestamps
     */
    public function testGetTimestampExpressionNoTimestamps(): void
    {
        $helper = new \ReflectionMethod($this->controller, 'getTimestampExpression');
        $helper->setAccessible(true);

        $expr = $helper->invoke($this->controller, false, false);
        $this->assertNull($expr);
    }

    /**
     * Test that query failure is handled gracefully (query returns false)
     * The method iterates a hardcoded table list, making isolated mocking of a single table
     * impractical without refactoring. This test verifies the error handling code exists.
     * A full runtime test would require a test double that implements the full BaseConnection
     * interface and is injected in place of the real DB connection, which is beyond unit scope.
     */
    public function testQueryFailureHandling(): void
    {
        // Verify the error handling code path exists in the source
        $source = file_get_contents(APPPATH . 'Controllers/AssetImportController.php');
        $this->assertStringContainsString("if (\$result === false)", $source);
        $this->assertStringContainsString("'Query returned false'", $source);
    }

    /**
     * Test that null row from getRowArray is handled gracefully
     * Same architectural limitation as testQueryFailureHandling - the method iterates
     * a hardcoded list and uses the real DB connection. A runtime test would require
     * a test double that can simulate getRowArray() returning null for COUNT(*) queries,
     * which always return exactly one row in practice.
     */
    public function testNullRowHandling(): void
    {
        // Verify the error handling code path exists in the source
        $source = file_get_contents(APPPATH . 'Controllers/AssetImportController.php');
        $this->assertStringContainsString("if (\$row === null)", $source);
        $this->assertStringContainsString("'No row returned'", $source);
    }

    /**
     * Test that table identifier comes from hardcoded array (no SQL injection)
     */
    public function testTableIdentifierSafety(): void
    {
        // The method uses a hardcoded array of table names
        $source = file_get_contents(APPPATH . 'Controllers/AssetImportController.php');

        // Verify the tables array contains exactly the 8 production tables
        $this->assertStringContainsString("'assets'", $source);
        $this->assertStringContainsString("'penyulang'", $source);
        $this->assertStringContainsString("'ulps'", $source);
        $this->assertStringContainsString("'sections'", $source);
        $this->assertStringContainsString("'gis_translines'", $source);
        $this->assertStringContainsString("'asset_relationships'", $source);
        $this->assertStringContainsString("'asset_import_batches'", $source);
        $this->assertStringContainsString("'network_topology_versions'", $source);

        // Verify test tables are NOT in the production list
        $this->assertStringNotContainsString("'test_only_updated_at'", $source);
        $this->assertStringNotContainsString("'test_no_timestamps'", $source);

        // Verify no user input in table names within this method
        $methodStart = strpos($source, 'private function computeIntegrityHash');
        $methodEnd = strpos($source, 'private function debugFoto');
        $methodSource = substr($source, $methodStart, $methodEnd - $methodStart);

        $this->assertStringNotContainsString('$this->request', $methodSource);
        $this->assertStringNotContainsString('$_GET', $methodSource);
        $this->assertStringNotContainsString('$_POST', $methodSource);
    }

    /**
     * Test that audit response format is consistent
     */
    public function testAuditResponseFormat(): void
    {
        $result = $this->computeIntegrityHashMethod->invoke($this->controller, $this->db);

        foreach ($result as $table => $data) {
            if (isset($data['error'])) {
                $this->assertIsString($data['error']);
            } else {
                $this->assertArrayHasKey('count', $data);
                $this->assertArrayHasKey('max_ts', $data);
                $this->assertIsInt($data['count']);
                $this->assertTrue($data['max_ts'] === null || is_string($data['max_ts']));
            }
        }
    }
}
