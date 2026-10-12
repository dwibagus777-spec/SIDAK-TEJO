<?php

namespace Tests\Unit;

use App\Models\InspeksiKubikelRekomendasiModel;
use CodeIgniter\Test\CIUnitTestCase;

final class KubikelInspectionBeyondAccessTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect('tests');
        $forge = \Config\Database::forge('tests');

        $this->dropTables($forge);
        $this->createTables($forge);
        $this->seedRecords();
        helper('app');
    }

    protected function tearDown(): void
    {
        $this->dropTables(\Config\Database::forge('tests'));
        parent::tearDown();
    }

    public function testBeyondRecommendationsAreLimitedToTheAssignedUser(): void
    {
        $model = new InspeksiKubikelRekomendasiModel();

        $assignedToFirstUser = $model->getByPenerimaUser(41);
        $assignedToSecondUser = $model->getByPenerimaUser(42);

        $this->assertSame([1], array_map('intval', array_column($assignedToFirstUser, 'id')));
        $this->assertSame([2], array_map('intval', array_column($assignedToSecondUser, 'id')));
    }

    public function testBeyondPhotoAccessIsLimitedToTheAssignedUser(): void
    {
        $session = session();
        $session->set([
            'logged_in' => true,
            'user_role' => 'beyond_kwh',
            'user_id' => 41,
        ]);

        $this->assertTrue(check_photo_access('kubikel_inspeksi/1/photo-1.jpg', 'tests'));

        $session->set('user_id', 42);

        $this->assertFalse(check_photo_access('kubikel_inspeksi/1/photo-1.jpg', 'tests'));
        $this->assertFalse(check_photo_access('kubikel_inspeksi/2/photo-unassigned-checklist.jpg', 'tests'));
    }

    private function createTables($forge): void
    {
        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'ulp_id' => ['type' => 'INTEGER', 'null' => true],
            'inspector_user_id' => ['type' => 'INTEGER'],
            'nomor_inspeksi' => ['type' => 'VARCHAR', 'constraint' => 50],
            'tgl_inspeksi' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('inspeksi_kubikel', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'inspeksi_kubikel_id' => ['type' => 'INTEGER'],
            'checklist_id' => ['type' => 'INTEGER', 'null' => true],
            'komponen' => ['type' => 'VARCHAR', 'constraint' => 100],
            'fasa' => ['type' => 'VARCHAR', 'constraint' => 20],
            'posisi' => ['type' => 'VARCHAR', 'constraint' => 100],
            'deskripsi_temuan' => ['type' => 'TEXT'],
            'urgensi' => ['type' => 'VARCHAR', 'constraint' => 30],
            'status_temuan' => ['type' => 'VARCHAR', 'constraint' => 30],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('inspeksi_kubikel_temuan', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'temuan_id' => ['type' => 'INTEGER'],
            'penerima' => ['type' => 'VARCHAR', 'constraint' => 30],
            'penerima_user_id' => ['type' => 'INTEGER'],
            'deskripsi' => ['type' => 'TEXT'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30],
            'waktu_kirim' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('inspeksi_kubikel_rekomendasi', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'inspeksi_kubikel_id' => ['type' => 'INTEGER'],
            'checklist_id' => ['type' => 'INTEGER', 'null' => true],
            'path_file' => ['type' => 'VARCHAR', 'constraint' => 255],
        ]);
        $forge->createTable('inspeksi_kubikel_foto', true);
    }

    private function seedRecords(): void
    {
        $this->db->table('inspeksi_kubikel')->insertBatch([
            ['id' => 1, 'ulp_id' => 5, 'inspector_user_id' => 90, 'nomor_inspeksi' => 'IK-001'],
            ['id' => 2, 'ulp_id' => 5, 'inspector_user_id' => 42, 'nomor_inspeksi' => 'IK-002'],
        ]);
        $this->db->table('inspeksi_kubikel_temuan')->insertBatch([
            ['id' => 1, 'inspeksi_kubikel_id' => 1, 'checklist_id' => 70, 'komponen' => 'Pemutus', 'fasa' => 'R', 'posisi' => 'Atas', 'deskripsi_temuan' => 'Temuan satu', 'urgensi' => 'SEDANG', 'status_temuan' => 'OPEN'],
            ['id' => 2, 'inspeksi_kubikel_id' => 2, 'checklist_id' => 71, 'komponen' => 'Pemutus', 'fasa' => 'S', 'posisi' => 'Atas', 'deskripsi_temuan' => 'Temuan dua', 'urgensi' => 'SEDANG', 'status_temuan' => 'OPEN'],
        ]);
        $this->db->table('inspeksi_kubikel_rekomendasi')->insertBatch([
            ['id' => 1, 'temuan_id' => 1, 'penerima' => 'BEYOND_KWH', 'penerima_user_id' => 41, 'deskripsi' => 'Rekomendasi satu', 'status' => 'TERKIRIM'],
            ['id' => 2, 'temuan_id' => 2, 'penerima' => 'BEYOND_KWH', 'penerima_user_id' => 42, 'deskripsi' => 'Rekomendasi dua', 'status' => 'TERKIRIM'],
            ['id' => 3, 'temuan_id' => 1, 'penerima' => 'ULP', 'penerima_user_id' => 41, 'deskripsi' => 'Rekomendasi ULP', 'status' => 'TERKIRIM'],
        ]);
        $this->db->table('inspeksi_kubikel_foto')->insert([
            'id' => 1,
            'inspeksi_kubikel_id' => 1,
            'checklist_id' => 70,
            'path_file' => 'kubikel_inspeksi/1/photo-1.jpg',
        ]);
        $this->db->table('inspeksi_kubikel_foto')->insert([
            'id' => 2,
            'inspeksi_kubikel_id' => 2,
            'checklist_id' => 70,
            'path_file' => 'kubikel_inspeksi/2/photo-unassigned-checklist.jpg',
        ]);
    }

    private function dropTables($forge): void
    {
        foreach ([
            'inspeksi_kubikel_foto',
            'inspeksi_kubikel_rekomendasi',
            'inspeksi_kubikel_temuan',
            'inspeksi_kubikel',
        ] as $table) {
            $forge->dropTable($table, true);
        }
    }
}
