<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use Config\Services;

/**
 * @internal
 * Test check_photo_access() helper with MariaDB test database
 */
final class PhotoAccessTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use 'test_mariadb' database group (MariaDB sidaktejo_test) for isolation
        $this->db = Database::connect('test_mariadb');

        // Verify we're connected to the test database, not production
        $actualDb = $this->db->getDatabase();
        if ($actualDb !== 'sidaktejo_test') {
            $this->markTestSkipped("Test database isolation failed: connected to '$actualDb' instead of 'sidaktejo_test'. Aborting to prevent data contamination.");
            return;
        }
        
        // Verify test database has required tables
        // Note: riwayat_tindak_lanjut table doesn't exist in current schema - check_photo_access handles this gracefully
        $requiredTables = [
            'temuan', 'ulps', 'penyulang', 'sections',
            'tb_eviden_kubikel', 'tb_eviden_trafo', 'tb_foto_eviden',
            'tb_management_trafo', 'assets'
        ];
        
        foreach ($requiredTables as $table) {
            if (!$this->db->tableExists($table)) {
                $this->markTestSkipped("Table '$table' not found in test database. Run migrations first.");
                return;
            }
        }

        // Load helper functions
        helper('app');
    }

    /**
     * Clean up test data after each test
     */
    protected function tearDown(): void
    {
        // Clean up test temuan records
        if (isset($this->db) && $this->db->tableExists('temuan')) {
            $this->db->table('temuan')
                ->where('nomor_temuan LIKE', 'TEST-%')
                ->delete();
        }
        parent::tearDown();
    }

    /**
     * Create a mock session with specified role and ULP
     */
    private function setMockSession(string $role, ?int $ulpId = null): void
    {
        $session = Services::session();
        $session->set([
            'logged_in'    => true,
            'user_id'      => 1,
            'user_role'    => $role,
            'user_ulp_id'  => $ulpId,
            'ulp_id'       => $ulpId,
            'user_name'    => 'Test User',
            'nama_pegawai' => 'Test User',
            'nip'          => '12345',
        ]);
    }

    /**
     * Test 1: Not logged in - should be denied
     */
    public function testNotLoggedIn(): void
    {
        $session = Services::session();
        $session->destroy();
        
        $result = check_photo_access('test.jpg', 'test_mariadb');
        $this->assertFalse($result, 'Not logged in user should be denied');
    }

    /**
     * Test 2: Unknown photo (not in any record) - should be denied
     */
    public function testUnknownPhoto(): void
    {
        $this->setMockSession('inspeksi', 1);
        $result = check_photo_access('nonexistent_' . uniqid() . '.jpg');
        $this->assertFalse($result, 'Unknown photo should be denied');
    }

    /**
     * Test 3: Photo in user's own ULP - should be allowed
     */
    public function testPhotoInOwnUlp(): void
    {
        $this->setMockSession('inspeksi', 1);
        
        // Insert a test temuan with known photo in ULP 1
        $photoName = 'test_own_ulp_' . uniqid() . '.jpg';
        $this->db->table('temuan')->insert([
            'nomor_temuan'   => 'TEST-' . uniqid(),
            'ulp_id'         => 1,
            'penyulang_id'   => 1,
            'section_id'     => 1,
            'jenis_temuan'   => 'KONSTRUKSI',
            'pelaksana'      => 'INSPEKSI',
            'prioritas'      => 'MEDIUM',
            'potensi_gangguan' => 'DGR',
            'tanggal_temuan' => date('Y-m-d'),
            'foto'           => json_encode([$photoName]),
            'foto_path'      => 'foto/',
            'status'         => 'BELUM',
            'created_by'     => 1,
            'created_by_name' => 'Test',
        ]);

        $result = check_photo_access($photoName, 'test_mariadb');
        $this->assertTrue($result, 'Photo in user\'s own ULP should be allowed');
    }

    /**
     * Test 4: Photo in different ULP - should be denied
     */
    public function testPhotoInDifferentUlp(): void
    {
        $this->setMockSession('inspeksi', 1);
        
        // Insert a test temuan with known photo in ULP 2
        $photoName = 'test_diff_ulp_' . uniqid() . '.jpg';
        $this->db->table('temuan')->insert([
            'nomor_temuan'   => 'TEST-' . uniqid(),
            'ulp_id'         => 2,
            'penyulang_id'   => 2,
            'section_id'     => 2,
            'jenis_temuan'   => 'KONSTRUKSI',
            'pelaksana'      => 'INSPEKSI',
            'prioritas'      => 'MEDIUM',
            'potensi_gangguan' => 'DGR',
            'tanggal_temuan' => date('Y-m-d'),
            'foto'           => json_encode([$photoName]),
            'foto_path'      => 'foto/',
            'status'         => 'BELUM',
            'created_by'     => 2,
            'created_by_name' => 'Test',
        ]);

        $result = check_photo_access($photoName, 'test_mariadb');
        $this->assertFalse($result, 'Photo in different ULP should be denied');
    }

    /**
     * Test 5: Cross-ULP role (administrator) - should be allowed
     */
    public function testCrossUlpRoleAdministrator(): void
    {
        $this->setMockSession('administrator', null);
        
        // Insert a test temuan in ULP 2
        $photoName = 'test_admin_' . uniqid() . '.jpg';
        $this->db->table('temuan')->insert([
            'nomor_temuan'   => 'TEST-' . uniqid(),
            'ulp_id'         => 2,
            'penyulang_id'   => 2,
            'section_id'     => 2,
            'jenis_temuan'   => 'KONSTRUKSI',
            'pelaksana'      => 'INSPEKSI',
            'prioritas'      => 'MEDIUM',
            'potensi_gangguan' => 'DGR',
            'tanggal_temuan' => date('Y-m-d'),
            'foto'           => json_encode([$photoName]),
            'foto_path'      => 'foto/',
            'status'         => 'BELUM',
            'created_by'     => 2,
            'created_by_name' => 'Test',
        ]);

        $result = check_photo_access($photoName, 'test_mariadb');
        $this->assertTrue($result, 'Administrator should have access to photo in any ULP');
    }

    /**
     * Test 6: Duplicate photo name across ULPs - should only allow based on user's ULP
     */
    public function testDuplicatePhotoNameAcrossUlps(): void
    {
        $this->setMockSession('inspeksi', 1);
        $photoName = 'duplicate_name_' . uniqid() . '.jpg';
        
        // Insert in ULP 1 (user's ULP)
        $this->db->table('temuan')->insert([
            'nomor_temuan'   => 'TEST-' . uniqid(),
            'ulp_id'         => 1,
            'penyulang_id'   => 1,
            'section_id'     => 1,
            'jenis_temuan'   => 'KONSTRUKSI',
            'pelaksana'      => 'INSPEKSI',
            'prioritas'      => 'MEDIUM',
            'potensi_gangguan' => 'DGR',
            'tanggal_temuan' => date('Y-m-d'),
            'foto'           => json_encode([$photoName]),
            'foto_path'      => 'foto/',
            'status'         => 'BELUM',
            'created_by'     => 1,
            'created_by_name' => 'Test',
        ]);
        
        // Insert same photo name in ULP 2
        $this->db->table('temuan')->insert([
            'nomor_temuan'   => 'TEST-' . uniqid(),
            'ulp_id'         => 2,
            'penyulang_id'   => 2,
            'section_id'     => 2,
            'jenis_temuan'   => 'KONSTRUKSI',
            'pelaksana'      => 'INSPEKSI',
            'prioritas'      => 'MEDIUM',
            'potensi_gangguan' => 'DGR',
            'tanggal_temuan' => date('Y-m-d'),
            'foto'           => json_encode([$photoName]),
            'foto_path'      => 'foto/',
            'status'         => 'BELUM',
            'created_by'     => 2,
            'created_by_name' => 'Test',
        ]);

        $result = check_photo_access($photoName, 'test_mariadb');
        $this->assertTrue($result, 'Photo with same name in user\'s ULP should be allowed');
    }

    /**
     * Test 7: Photo name with SQL injection attempt - should not break query
     */
    public function testSqlInjectionAttempt(): void
    {
        $this->setMockSession('inspeksi', 1);
        
        // Attempt SQL injection via photo name
        $maliciousName = "test'; DROP TABLE temuan;--.jpg";
        $result = check_photo_access($maliciousName, 'test_mariadb');
        $this->assertFalse($result, 'SQL injection attempt should be safely handled (denied as unknown)');
        
        // Verify table still exists
        $tables = $this->db->listTables();
        $this->assertContains('temuan', $tables, 'temuan table should still exist after injection attempt');
    }

/**
     * Test 8: Photo in record with different ULP - should be denied for non-cross-ULP
     * (This is a duplicate test to ensure coverage - testPhotoInDifferentUlp covers this)
     */
    public function testPhotoInDifferentUlpDuplicate(): void
    {
        $this->setMockSession('inspeksi', 1);
        
        // Insert a test temuan with known photo in ULP 2
        $photoName = 'test_diff_ulp_dup_' . uniqid() . '.jpg';
        $this->db->table('temuan')->insert([
            'nomor_temuan'   => 'TEST-' . uniqid(),
            'ulp_id'         => 2,
            'penyulang_id'   => 2,
            'section_id'     => 2,
            'jenis_temuan'   => 'KONSTRUKSI',
            'pelaksana'      => 'INSPEKSI',
            'prioritas'      => 'MEDIUM',
            'potensi_gangguan' => 'DGR',
            'tanggal_temuan' => date('Y-m-d'),
            'foto'           => json_encode([$photoName]),
            'foto_path'      => 'foto/',
            'status'         => 'BELUM',
            'created_by'     => 2,
            'created_by_name' => 'Test',
        ]);
        
        $result = check_photo_access($photoName, 'test_mariadb');
        $this->assertFalse($result, 'Photo in different ULP should be denied for non-cross-ULP user');
    }
}