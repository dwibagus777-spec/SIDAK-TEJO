<?php

namespace Tests\Integration;

use App\Models\InspeksiKubikelChecklistModel;
use App\Models\InspeksiKubikelFotoModel;
use App\Models\InspeksiKubikelHistoriModel;
use App\Models\InspeksiKubikelMaterialModel;
use App\Models\InspeksiKubikelModel;
use App\Models\InspeksiKubikelRekomendasiModel;
use App\Models\InspeksiKubikelTemuanModel;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use mysqli;

final class KubikelMigrationIsolationTest extends CIUnitTestCase
{
    private ?mysqli $admin = null;
    private ?string $databaseName = null;
    private bool $createdDatabase = false;
    private array $dbConfig = [];

    protected function setUp(): void
    {
        parent::setUp();

        $baseConfig = config(Database::class)->test_mariadb;
        if (! in_array(strtolower((string) $baseConfig['hostname']), ['127.0.0.1', 'localhost'], true)
            || $baseConfig['database'] !== 'sidaktejo_test') {
            $this->markTestSkipped('A local sidaktejo_test connection is required before creating a disposable migration schema.');
        }

        $this->admin = @new mysqli(
            $baseConfig['hostname'],
            $baseConfig['username'],
            $baseConfig['password'],
            '',
            (int) ($baseConfig['port'] ?? 3306)
        );
        if ($this->admin->connect_errno !== 0) {
            $this->markTestSkipped('The local MariaDB test service is unavailable; migration integration tests were not run.');
        }

        $this->databaseName = 'sidaktejo_migtest_' . bin2hex(random_bytes(8));
        try {
            $this->admin->query(
                'CREATE DATABASE `' . $this->databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'
            );
            $this->createdDatabase = true;
        } catch (\Throwable) {
            $this->markTestSkipped('The local MariaDB account cannot create a disposable schema; migration integration tests were not run.');
        }

        $this->dbConfig = $baseConfig;
        $this->dbConfig['database'] = $this->databaseName;
        $this->db = Database::connect($this->dbConfig, false);
        $this->db->initialize();
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->db->close();
        }

        if ($this->admin !== null && $this->createdDatabase && $this->databaseName !== null
            && preg_match('/^sidaktejo_migtest_[a-f0-9]{16}$/', $this->databaseName) === 1) {
            $this->admin->query('DROP DATABASE `' . $this->databaseName . '`');
            $this->admin->close();
        }

        parent::tearDown();
    }

    public function testMigrationsFailClosedAndKubikelModelsPerformCrudInDisposableSchema(): void
    {
        require_once APPPATH . 'Database/Migrations/2026-10-11-000001_FixTemuanAssetFk.php';
        require_once APPPATH . 'Database/Migrations/2026-10-12-000001_CreateKubikelInspectionTables.php';

        $this->db->query(
            'CREATE TABLE assets (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB'
        );
        $this->db->query(
            'CREATE TABLE temuan (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                asset_id INT NULL DEFAULT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB'
        );
        $this->db->query('ALTER TABLE assets ENGINE=MyISAM');
        $fkMigration = new \App\Database\Migrations\FixTemuanAssetFk(Database::forge($this->db));
        try {
            $fkMigration->up();
            $this->fail('The FK migration must reject non-InnoDB tables.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Both tables must use InnoDB', $exception->getMessage());
        }
        $this->db->query('ALTER TABLE assets ENGINE=InnoDB');
        $this->db->query('ALTER TABLE assets MODIFY id INT NOT NULL AUTO_INCREMENT');
        try {
            $fkMigration->up();
            $this->fail('The FK migration must reject a signed assets.id.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('assets.id must be a NOT NULL INT UNSIGNED', $exception->getMessage());
        }
        $this->db->query('ALTER TABLE assets MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT');
        $this->db->query('INSERT INTO temuan (asset_id) VALUES (999)');

        try {
            $fkMigration->up();
            $this->fail('The FK migration must stop on orphan rows.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Found 1 orphan', $exception->getMessage());
        }
        $this->assertSame(1, (int) $this->db->table('temuan')->countAllResults());
        $this->db->query('DELETE FROM temuan');

        $this->db->query('INSERT INTO temuan (asset_id) VALUES (-1)');
        try {
            $fkMigration->up();
            $this->fail('The FK migration must stop on negative IDs.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('negative temuan.asset_id', $exception->getMessage());
        }
        $this->assertSame(1, (int) $this->db->table('temuan')->countAllResults());
        $this->db->query('DELETE FROM temuan');

        $this->db->query('INSERT INTO assets (id) VALUES (1)');
        $this->db->query('INSERT INTO temuan (asset_id) VALUES (1)');
        $fkMigration->up();
        $fkMigration->up();
        $assetColumnType = strtolower((string) $this->db->query(
            'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'temuan\' AND COLUMN_NAME = \'asset_id\''
        )->getRowArray()['COLUMN_TYPE']);
        $this->assertMatchesRegularExpression('/^int(?:\\(\\d+\\))? unsigned$/', $assetColumnType);
        $this->assertSame(1, (int) $this->db->query(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'temuan\'
               AND INDEX_NAME = \'idx_temuan_asset_id_fk\' AND COLUMN_NAME = \'asset_id\''
        )->getRowArray()['total']);

        $this->db->query('DELETE FROM assets WHERE id = 1');
        $this->assertNull($this->db->query('SELECT asset_id FROM temuan')->getRowArray()['asset_id']);
        $fkMigration->down();
        $assetColumnType = strtolower((string) $this->db->query(
            'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'temuan\' AND COLUMN_NAME = \'asset_id\''
        )->getRowArray()['COLUMN_TYPE']);
        $this->assertMatchesRegularExpression('/^int(?:\\(\\d+\\))? unsigned$/', $assetColumnType);

        $this->db->query(
            'CREATE TABLE other_assets (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB'
        );
        $this->db->query(
            'ALTER TABLE temuan ADD CONSTRAINT fk_temuan_asset
             FOREIGN KEY (asset_id) REFERENCES other_assets(id)'
        );
        try {
            $fkMigration->up();
            $this->fail('The FK migration must reject a same-named constraint with a different target.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('incompatible foreign key', $exception->getMessage());
        }
        try {
            $fkMigration->down();
            $this->fail('Rollback must not drop a same-named constraint with a different definition.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Refusing to drop', $exception->getMessage());
        }

        $this->db->query('DROP TABLE temuan');
        $this->db->query(
            'CREATE TABLE temuan (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                asset_id INT NULL DEFAULT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB'
        );
        $this->db->query('CREATE TABLE inspeksi_kubikel (id INT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
        $kubikelMigration = new \App\Database\Migrations\CreateKubikelInspectionTables(Database::forge($this->db));
        try {
            $kubikelMigration->up();
            $this->fail('The Kubikel migration must reject partial schemas before creating additional tables.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('partial/existing schema', $exception->getMessage());
        }
        $this->assertFalse($this->db->tableExists('inspeksi_kubikel_checklist', false));
        $this->db->query('DROP TABLE inspeksi_kubikel');

        $kubikelMigration->up();
        $tables = [
            'inspeksi_kubikel',
            'inspeksi_kubikel_checklist',
            'inspeksi_kubikel_foto',
            'inspeksi_kubikel_temuan',
            'inspeksi_kubikel_rekomendasi',
            'inspeksi_kubikel_histori',
            'inspeksi_kubikel_material',
        ];
        foreach ($tables as $table) {
            $this->assertTrue($this->db->tableExists($table, false), $table . ' must be created.');
        }
        $this->assertSame(9, (int) $this->db->query(
            'SELECT COUNT(DISTINCT CONSTRAINT_NAME) AS total
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME LIKE \'inspeksi_kubikel%\'
               AND REFERENCED_TABLE_NAME IS NOT NULL'
        )->getRowArray()['total']);

        $inspection = new InspeksiKubikelModel($this->db);
        $inspectionId = $inspection->insert([
            'nomor_inspeksi' => 'TEST-KUBIKEL-001',
            'inspector_user_id' => 7,
            'tgl_inspeksi' => '2026-10-12',
            'status' => 'DRAFT',
            'status_temuan' => 'TIDAK_ADA',
        ], true);
        $this->assertNotFalse($inspectionId, json_encode($inspection->errors()));
        $inspectionId = (int) $inspectionId;
        $this->assertGreaterThan(0, $inspectionId);

        $checklist = new InspeksiKubikelChecklistModel($this->db);
        $checklistId = (int) $checklist->insert([
            'inspeksi_kubikel_id' => $inspectionId,
            'komponen' => 'TEST',
            'item_pemeriksaan' => 'Pemeriksaan sintetis',
            'fasa' => 'TIDAK_BERFASA',
            'kondisi' => 'BAIK',
            'urgensi' => 'SEDANG',
            'status_pekerjaan' => 'BELUM',
        ], true);
        $this->assertGreaterThan(0, $checklistId);

        $finding = new InspeksiKubikelTemuanModel($this->db);
        $findingId = (int) $finding->insert([
            'inspeksi_kubikel_id' => $inspectionId,
            'checklist_id' => $checklistId,
            'komponen' => 'TEST',
            'deskripsi_temuan' => 'Temuan sintetis',
            'fasa' => 'TIDAK_BERFASA',
            'urgensi' => 'SEDANG',
            'penerima_rekomendasi' => 'BEYOND_KWH',
            'status_temuan' => 'BARU',
        ], true);
        $this->assertGreaterThan(0, $findingId);

        $photo = new InspeksiKubikelFotoModel($this->db);
        $photoId = (int) $photo->insert([
            'inspeksi_kubikel_id' => $inspectionId,
            'checklist_id' => $checklistId,
            'nama_file' => 'test.jpg',
            'path_file' => 'test/test.jpg',
            'mime_type' => 'image/jpeg',
            'kategori' => 'TEMUAN',
        ], true);
        $this->assertGreaterThan(0, $photoId);

        $recommendation = new InspeksiKubikelRekomendasiModel($this->db);
        $recommendationId = (int) $recommendation->insert([
            'temuan_id' => $findingId,
            'penerima' => 'BEYOND_KWH',
            'deskripsi' => 'Rekomendasi sintetis',
            'status' => 'TERKIRIM',
        ], true);
        $this->assertGreaterThan(0, $recommendationId);

        $history = new InspeksiKubikelHistoriModel($this->db);
        $this->assertTrue($history->logPerubahan($inspectionId, 'STATUS', null, 'DRAFT', 7));

        $material = new InspeksiKubikelMaterialModel($this->db);
        $materialId = (int) $material->insert([
            'inspeksi_kubikel_id' => $inspectionId,
            'temuan_id' => $findingId,
            'nama_material' => 'Material sintetis',
            'status' => 'DIRNCANA',
        ], true);
        $this->assertGreaterThan(0, $materialId);

        $this->assertCount(1, $checklist->getByInspeksi($inspectionId));
        $this->assertCount(1, $finding->getByInspeksi($inspectionId));
        $this->assertCount(1, $photo->getByInspeksi($inspectionId));
        $this->assertCount(1, $recommendation->getByTemuan($findingId));
        $this->assertCount(1, $history->getByInspeksi($inspectionId));
        $this->assertCount(1, $material->getByInspeksi($inspectionId));

        $kubikelMigration->down();
        foreach ($tables as $table) {
            $this->assertFalse($this->db->tableExists($table, false), $table . ' must be removed by the test rollback.');
        }
    }
}
