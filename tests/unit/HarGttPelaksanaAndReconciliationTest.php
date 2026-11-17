<?php

namespace Tests\Unit;

use App\Repositories\TemuanRepository;
use App\Services\HarGttReconciliationService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Verifies canonical HAR GTT pelaksana normalization, filter/role scoping queries,
 * and safe transactional + audited HAR GARDU -> HAR GTT reconciliation.
 */
class HarGttPelaksanaAndReconciliationTest extends CIUnitTestCase
{
    protected BaseConnection $conn;
    protected string $auditDir;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['form', 'url', 'app', 'auth']);

        $this->conn     = \Config\Database::connect();
        $forge          = \Config\Database::forge();
        $this->auditDir = sys_get_temp_dir() . '/har_gtt_audit_test_' . uniqid('', true);
        cache()->clean();

        foreach (['activity_logs', 'work_orders', 'temuan', 'sections', 'penyulang', 'ulps', 'assets'] as $tbl) {
            $forge->dropTable($tbl, true);
        }

        $forge->addField([
            'id'       => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
            'up3_id'   => ['type' => 'INTEGER', 'default' => 1],
        ]);
        $forge->createTable('ulps', true);
        $this->conn->table('ulps')->insert(['id' => 1, 'kode_ulp' => 'KRN', 'nama_ulp' => 'ULP Krian', 'up3_id' => 1]);

        $forge->addField([
            'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'ulp_id'         => ['type' => 'INTEGER', 'default' => 1],
            'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $forge->createTable('penyulang', true);
        $this->conn->table('penyulang')->insert(['id' => 10, 'ulp_id' => 1, 'nama_penyulang' => 'HEAVENLAND']);

        $forge->addField([
            'id'           => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'penyulang_id' => ['type' => 'INTEGER', 'default' => 10],
            'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $forge->createTable('sections', true);
        $this->conn->table('sections')->insert(['id' => 100, 'penyulang_id' => 10, 'nama_section' => 'SEC-1']);

        $forge->addField([
            'id'          => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'kode_asset'  => ['type' => 'VARCHAR', 'constraint' => 60],
            'nama_asset'  => ['type' => 'VARCHAR', 'constraint' => 120],
            'jenis_asset' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'GARDU'],
            'latitude'    => ['type' => 'REAL', 'null' => true],
            'longitude'   => ['type' => 'REAL', 'null' => true],
        ]);
        $forge->createTable('assets', true);

        $forge->addField([
            'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'nomor_temuan'   => ['type' => 'VARCHAR', 'constraint' => 80],
            'ulp_id'         => ['type' => 'INTEGER', 'default' => 1],
            'penyulang_id'   => ['type' => 'INTEGER', 'default' => 10],
            'section_id'     => ['type' => 'INTEGER', 'default' => 100],
            'asset_id'       => ['type' => 'INTEGER', 'null' => true],
            'jenis_temuan'   => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'HOTSPOT'],
            'pelaksana'        => ['type' => 'VARCHAR', 'constraint' => 50],
            'prioritas'        => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'EMERGENCY'],
            'potensi_gangguan' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'DGR'],
            'status'           => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'BELUM'],
            'tanggal_temuan'   => ['type' => 'DATE', 'default' => '2026-07-16'],
            'alamat'           => ['type' => 'TEXT', 'null' => true],
            'detail_temuan'    => ['type' => 'TEXT', 'null' => true],
            'foto'           => ['type' => 'TEXT', 'null' => true],
            'foto_path'      => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => 'foto/'],
            'created_by'     => ['type' => 'INTEGER', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('temuan', true);

        $this->conn->table('temuan')->insertBatch([
            ['id' => 1, 'nomor_temuan' => 'STJ/HAR/20260716/0402', 'ulp_id' => 1, 'penyulang_id' => 10, 'section_id' => 100, 'pelaksana' => 'HAR GARDU', 'status' => 'BELUM', 'prioritas' => 'EMERGENCY', 'tanggal_temuan' => '2026-07-16', 'updated_at' => '2026-07-16 10:00:00'],
            ['id' => 2, 'nomor_temuan' => 'STJ-2026-000264', 'ulp_id' => 1, 'penyulang_id' => 10, 'section_id' => 100, 'pelaksana' => 'HAR_GARDU', 'status' => 'BELUM', 'prioritas' => 'HIGH', 'tanggal_temuan' => '2026-11-15', 'updated_at' => '2026-11-15 08:00:00'],
            ['id' => 3, 'nomor_temuan' => 'STJ-2026-000279', 'ulp_id' => 1, 'penyulang_id' => 10, 'section_id' => 100, 'pelaksana' => 'HAR GTT', 'status' => 'BELUM', 'prioritas' => 'MEDIUM', 'tanggal_temuan' => '2026-11-16', 'updated_at' => '2026-11-16 09:00:00'],
            ['id' => 4, 'nomor_temuan' => 'STJ-2026-000100', 'ulp_id' => 1, 'penyulang_id' => 10, 'section_id' => 100, 'pelaksana' => 'PDKB', 'status' => 'BELUM', 'prioritas' => 'EMERGENCY', 'tanggal_temuan' => '2026-06-01', 'updated_at' => '2026-06-01 07:00:00'],
            ['id' => 5, 'nomor_temuan' => 'STJ-2026-000101', 'ulp_id' => 1, 'penyulang_id' => 10, 'section_id' => 100, 'pelaksana' => 'HAR ROW', 'status' => 'SELESAI', 'prioritas' => 'MEDIUM', 'tanggal_temuan' => '2026-05-01', 'updated_at' => '2026-05-01 07:00:00'],
        ]);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'wo_number'  => ['type' => 'VARCHAR', 'constraint' => 60],
            'temuan_id'  => ['type' => 'INTEGER', 'default' => 1],
            'pelaksana'  => ['type' => 'VARCHAR', 'constraint' => 50],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'OPEN'],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('work_orders', true);
        $this->conn->table('work_orders')->insertBatch([
            ['id' => 1, 'wo_number' => 'WO-001', 'temuan_id' => 1, 'pelaksana' => 'HAR GARDU', 'updated_at' => '2026-07-16 11:00:00'],
            ['id' => 2, 'wo_number' => 'WO-002', 'temuan_id' => 4, 'pelaksana' => 'PDKB', 'updated_at' => '2026-06-01 08:00:00'],
        ]);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'user_id'    => ['type' => 'INTEGER', 'null' => true],
            'username'   => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'role'       => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'aktivitas'  => ['type' => 'VARCHAR', 'constraint' => 80],
            'detail'     => ['type' => 'TEXT', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'user_agent' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('activity_logs', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->auditDir)) {
            foreach (glob($this->auditDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->auditDir);
        }
        parent::tearDown();
    }

    public function testHelpersFiltersAndStatsUnifyHarGarduIntoHarGtt(): void
    {
        $this->assertSame('HAR GTT', normalize_pelaksana_label('HAR GARDU'));
        $this->assertSame('HAR GTT', normalize_pelaksana_label('har_gardu'));
        $this->assertSame('PDKB', normalize_pelaksana_label('pdkb'));

        $repo = new TemuanRepository();

        // Filter HAR GTT matches HAR GTT + legacy HAR GARDU / HAR_GARDU without leaking PDKB or HAR ROW
        $resGtt = $repo->getDataTables(['pelaksana' => 'HAR GTT', 'start' => 0, 'length' => 20]);
        $this->assertSame(3, $resGtt['recordsFiltered']);

        // Legacy URL parameter HAR GARDU resolves identically
        $resLegacy = $repo->getDataTables(['pelaksana' => 'HAR GARDU', 'start' => 0, 'length' => 20]);
        $this->assertSame(3, $resLegacy['recordsFiltered']);

        // Dashboard stats populates both har_gtt & har_gardu accurately
        $stats = $repo->getDashboardStats();
        $this->assertSame(3, $stats['har_gtt']);
        $this->assertSame(3, $stats['har_gardu']);
        $this->assertSame(1, $stats['pdkb']);
        $this->assertSame(1, $stats['har_row']);

        // apply_role_scoping preserves access for both har_gardu and har_gtt user accounts
        $rowsRoleGardu = apply_role_scoping($this->conn->table('temuan'), 'har_gardu', 1)->get()->getResultArray();
        $rowsRoleGtt   = apply_role_scoping($this->conn->table('temuan'), 'har_gtt', 1)->get()->getResultArray();
        $this->assertCount(3, $rowsRoleGardu);
        $this->assertCount(3, $rowsRoleGtt);
    }

    public function testAuditedReconciliationIsSelectiveReversibleAndIdempotent(): void
    {
        $svc = new HarGttReconciliationService($this->conn, $this->auditDir);

        // 1. Dry-run preview must not mutate DB
        $preview = $svc->reconcile(false, 'PHPUnit');
        $this->assertFalse($preview['executed']);
        $this->assertSame(2, $preview['temuan_target']);
        $this->assertSame(1, $preview['work_orders_target']);
        $this->assertSame([1, 2], $preview['affected_ids']);

        // 2. Execute reconciliation with audit manifest
        $exec = $svc->reconcile(true, 'PHPUnit');
        $this->assertTrue($exec['executed']);
        $this->assertTrue($exec['changed']);
        $this->assertSame(2, $exec['temuan_updated']);
        $this->assertSame(1, $exec['work_orders_updated']);
        $this->assertNotEmpty($exec['audit_file']);
        $this->assertFileExists($exec['audit_file']);

        $manifest = json_decode((string)file_get_contents($exec['audit_file']), true);
        $this->assertSame('RECONCILE_HAR_GARDU_TO_HAR_GTT', $manifest['action']);
        $this->assertCount(2, $manifest['before_temuan_rows']);
        $this->assertSame('STJ/HAR/20260716/0402', $manifest['before_temuan_rows'][0]['nomor_temuan']);
        $this->assertSame('HAR GARDU', $manifest['before_temuan_rows'][0]['pelaksana']);

        // Unrelated rows must remain intact with original updated_at
        $pdkbRow = $this->conn->table('temuan')->where('id', 4)->get()->getRowArray();
        $rowRow  = $this->conn->table('temuan')->where('id', 5)->get()->getRowArray();
        $gttOld  = $this->conn->table('temuan')->where('id', 3)->get()->getRowArray();
        $this->assertSame('PDKB', $pdkbRow['pelaksana']);
        $this->assertSame('2026-06-01 07:00:00', $pdkbRow['updated_at']);
        $this->assertSame('HAR ROW', $rowRow['pelaksana']);
        $this->assertSame('HAR GTT', $gttOld['pelaksana']);
        $this->assertSame('2026-11-16 09:00:00', $gttOld['updated_at']);

        // Reconciled rows now store canonical HAR GTT
        $rec1 = $this->conn->table('temuan')->where('id', 1)->get()->getRowArray();
        $rec2 = $this->conn->table('temuan')->where('id', 2)->get()->getRowArray();
        $wo1  = $this->conn->table('work_orders')->where('id', 1)->get()->getRowArray();
        $wo2  = $this->conn->table('work_orders')->where('id', 2)->get()->getRowArray();
        $this->assertSame('HAR GTT', $rec1['pelaksana']);
        $this->assertSame('HAR GTT', $rec2['pelaksana']);
        $this->assertSame('HAR GTT', $wo1['pelaksana']);
        $this->assertSame('PDKB', $wo2['pelaksana']);

        // 3. Re-running must be strictly idempotent (no further writes or files)
        $recheck = $svc->reconcile(true, 'PHPUnit');
        $this->assertFalse($recheck['changed']);
        $this->assertSame(0, $recheck['temuan_updated']);
        $this->assertSame(0, $recheck['work_orders_updated']);
        $this->assertNull($recheck['audit_file']);
    }
}
