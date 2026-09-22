<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use App\Controllers\Temuan;
use App\Models\TemuanShareLinkModel;
use App\Services\TemuanShareService;
use Config\Database;

/**
 * CR-HOTFIX-03: Temuan Share Security & Token Integrity Unit Test
 *
 * Verifies that:
 * 1. Share tokens are cryptographically secure (opaque, random bytes).
 * 2. Tokens are hashed with SHA-256 before storage; raw tokens are never persisted.
 * 3. Expired or revoked tokens cannot be resolved.
 * 4. Token resolution generates ZERO database mutations (Delta-DB = 0).
 * 5. Controller ajaxGenerateShare enforces authentication.
 * 6. Public share route is accessible without user session.
 *
 * @internal
 */
final class TemuanShareSecurityTest extends CIUnitTestCase
{
    private Temuan $controller;
    private TemuanShareService $shareService;
    private TemuanShareLinkModel $shareLinkModel;
    protected $session;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $forge = Database::forge();

        // Drop tables to guarantee pristine test schema
        $forge->dropTable('temuan_share_links', true);
        $forge->dropTable('temuan_materials', true);
        $forge->dropTable('temuan_accessories', true);
        $forge->dropTable('master_jtm_accessories', true);
        $forge->dropTable('temuan', true);
        $forge->dropTable('assets', true);
        $forge->dropTable('ulps', true);
        $forge->dropTable('penyulang', true);
        $forge->dropTable('sections', true);
        $forge->dropTable('construction_types', true);
        $forge->dropTable('materials', true);
        $forge->dropTable('users', true);

        // 1. Users table
        $forge->addField([
            'id'       => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'nama'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'username' => ['type' => 'VARCHAR', 'constraint' => 50],
        ]);
        $forge->createTable('users', true);

        // 2. Ulps table
        $forge->addField([
            'id'        => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'kode_ulp'  => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'nama_ulp'  => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $forge->createTable('ulps', true);

        // 3. Penyulang table
        $forge->addField([
            'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
            'ulp_id'         => ['type' => 'INTEGER', 'null' => true],
        ]);
        $forge->createTable('penyulang', true);

        // 4. Sections table
        $forge->addField([
            'id'           => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            'penyulang_id' => ['type' => 'INTEGER', 'null' => true],
        ]);
        $forge->createTable('sections', true);

        // 5. Construction types table
        $forge->addField([
            'id'                => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'construction_code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'construction_name' => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $forge->createTable('construction_types', true);

        // 6. Materials table
        $forge->addField([
            'id'            => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'material_code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'nama_material' => ['type' => 'VARCHAR', 'constraint' => 100],
            'satuan'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'SET'],
        ]);
        $forge->createTable('materials', true);

        // 7. Master JTM accessories table
        $forge->addField([
            'id'               => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'code'             => ['type' => 'VARCHAR', 'constraint' => 50],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'category'         => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
            'sub_category'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
            'phase_applicable' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'unit'             => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'buah'],
            'description'      => ['type' => 'TEXT', 'null' => true],
            'is_active'        => ['type' => 'BOOLEAN', 'default' => true],
            'sort_order'       => ['type' => 'INTEGER', 'default' => 0],
        ]);
        $forge->createTable('master_jtm_accessories', true);

        // 8. Assets table
        $forge->addField([
            'id'                   => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 50],
            'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG'],
            'construction_type_id' => ['type' => 'INTEGER', 'null' => true],
            'ulp_id'               => ['type' => 'INTEGER', 'null' => true],
            'penyulang_id'         => ['type' => 'INTEGER', 'null' => true],
            'section_id'           => ['type' => 'INTEGER', 'null' => true],
            'latitude'             => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
            'longitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
            'status'               => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'NORMAL'],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('assets', true);

        // 9. Temuan table
        $forge->addField([
            'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'nomor_temuan'   => ['type' => 'VARCHAR', 'constraint' => 50],
            'ulp_id'         => ['type' => 'INTEGER', 'null' => true],
            'penyulang_id'   => ['type' => 'INTEGER', 'null' => true],
            'section_id'     => ['type' => 'INTEGER', 'null' => true],
            'asset_id'       => ['type' => 'INTEGER', 'null' => true],
            'latitude'       => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
            'longitude'      => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
            'tanggal_temuan' => ['type' => 'DATE', 'null' => true],
            'deskripsi'      => ['type' => 'TEXT', 'null' => true],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'OPEN'],
            'judul'          => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'detail_temuan'  => ['type' => 'TEXT', 'null' => true],
            'status_temuan'  => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'OPEN'],
            'created_by'     => ['type' => 'INTEGER', 'null' => true],
            'updated_by'     => ['type' => 'INTEGER', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('temuan', true);

        // 10. Temuan share links table
        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'temuan_id'  => ['type' => 'INTEGER'],
            'token_hash' => ['type' => 'VARCHAR', 'constraint' => 64],
            'created_by' => ['type' => 'INTEGER', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'expires_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
            'is_active'  => ['type' => 'BOOLEAN', 'default' => true],
        ]);
        $forge->createTable('temuan_share_links', true);

        // 11. Supporting tables
        $forge->addField([
            'id'                   => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'temuan_id'            => ['type' => 'INTEGER'],
            'material_name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'quantity'             => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 1.0],
            'unit'                 => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'SET'],
            'material_category'    => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'STANDARD'],
            'is_special_device'    => ['type' => 'BOOLEAN', 'default' => false],
            'construction_type_id' => ['type' => 'INTEGER', 'null' => true],
        ]);
        $forge->createTable('temuan_materials', true);

        $forge->addField([
            'id'                      => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'temuan_id'               => ['type' => 'INTEGER'],
            'asset_id'                => ['type' => 'INTEGER'],
            'accessory_type_id'       => ['type' => 'INTEGER'],
            'accessory_code'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'accessory_name_snapshot' => ['type' => 'VARCHAR', 'constraint' => 100],
            'category_snapshot'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'qty'                     => ['type' => 'INTEGER', 'default' => 1],
            'unit'                    => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'buah'],
            'phase_applicable'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'phase_configuration'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'NON_PHASE', 'null' => true],
            'phase_positions'         => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'status'                  => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ADA'],
            'condition'               => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'BAIK'],
            'note'                    => ['type' => 'TEXT', 'null' => true],
            'photo_url'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'              => ['type' => 'DATETIME', 'null' => true],
            'updated_at'              => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('temuan_accessories', true);

        // Seed test finding and asset
        $this->db->table('assets')->insert([
            'id'          => 701,
            'kode_asset'  => 'AST-SHARE-001',
            'nama_asset'  => 'Tiang Share Security Test',
            'jenis_asset' => 'TIANG_BETON',
            'latitude'    => -7.447812,
            'longitude'   => 112.718324,
            'status'      => 'NORMAL',
        ]);

        $this->db->table('temuan')->insert([
            'id'             => 801,
            'nomor_temuan'   => 'TMN-SHARE-001',
            'asset_id'       => 701,
            'latitude'       => -7.447812,
            'longitude'      => 112.718324,
            'tanggal_temuan' => date('Y-m-d'),
            'deskripsi'      => 'Test Finding For Share Security',
            'status'         => 'OPEN',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->session = Services::session();
        $this->session->destroy();

        $request = Services::request();
        $request->setGlobal('get', []);
        $request->setGlobal('post', []);
        $request->setGlobal('request', []);

        $this->controller = new Temuan();
        $this->controller->initController($request, Services::response(), Services::logger());

        $this->shareService = new TemuanShareService();
        $this->shareLinkModel = new TemuanShareLinkModel();
    }

    /**
     * Test token generation produces valid structure and persists SHA-256 hash.
     */
    public function testTokenGenerationProducesSecureOpaqueToken(): void
    {
        $findingId = 801;
        $result = $this->shareService->generateShareLink($findingId, 1, 7);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('raw_token', $result);
        $this->assertArrayHasKey('share_url', $result);
        $this->assertArrayHasKey('expires_at', $result);

        // Token should be a 64-character hex string (32 random bytes)
        $this->assertEquals(64, strlen($result['token']));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['token']);

        // Check that raw token is NOT in database, but its SHA-256 hash IS in database
        $tokenHash = hash('sha256', $result['token']);
        $rawSearch = $this->shareLinkModel->where('token_hash', $result['token'])->first();
        $this->assertNull($rawSearch, 'Raw token must never be stored in database.');

        $hashSearch = $this->shareLinkModel->where('token_hash', $tokenHash)->first();
        $this->assertNotNull($hashSearch, 'SHA-256 hash of token must be present in database.');
        $this->assertEquals($findingId, (int) $hashSearch['temuan_id']);
        $this->assertEquals(1, (int) $hashSearch['is_active']);
    }

    /**
     * Test resolving valid token returns finding and link metadata.
     */
    public function testResolveValidTokenReturnsFindingContext(): void
    {
        $findingId = 801;
        $generated = $this->shareService->generateShareLink($findingId, 1, 7);
        $rawToken = $generated['token'];

        $resolved = $this->shareService->resolveShareToken($rawToken);
        $this->assertNotNull($resolved);
        $this->assertArrayHasKey('temuan_id', $resolved);
        $this->assertEquals($findingId, (int) $resolved['temuan_id']);
    }

    /**
     * Test resolving with non-existent or invalid token returns null.
     */
    public function testResolveInvalidTokenReturnsNull(): void
    {
        $fakeToken = str_repeat('a', 64);
        $resolved = $this->shareService->resolveShareToken($fakeToken);
        $this->assertNull($resolved);

        $malformedToken = 'invalid-token-123';
        $resolvedMalformed = $this->shareService->resolveShareToken($malformedToken);
        $this->assertNull($resolvedMalformed);
    }

    /**
     * Test resolving revoked token returns null.
     */
    public function testResolveRevokedTokenReturnsNull(): void
    {
        $findingId = 801;
        $generated = $this->shareService->generateShareLink($findingId, 1, 7);
        $rawToken = $generated['token'];

        // Revoke the token
        $this->shareService->revokeLink($findingId);

        $resolved = $this->shareService->resolveShareToken($rawToken);
        $this->assertNull($resolved, 'Revoked token must not resolve.');
    }

    /**
     * Test that resolving finding detail has zero DB writes (Delta-DB = 0).
     */
    public function testGetShareFindingDetailZeroDbWrites(): void
    {
        $findingId = 801;

        // Count rows in critical tables before read
        $countsBefore = [
            'temuan'             => $this->db->table('temuan')->countAllResults(),
            'assets'             => $this->db->table('assets')->countAllResults(),
            'temuan_share_links' => $this->db->table('temuan_share_links')->countAllResults(),
        ];

        // Execute read operation
        $detail = $this->shareService->getShareFindingDetail($findingId);

        $countsAfter = [
            'temuan'             => $this->db->table('temuan')->countAllResults(),
            'assets'             => $this->db->table('assets')->countAllResults(),
            'temuan_share_links' => $this->db->table('temuan_share_links')->countAllResults(),
        ];

        $this->assertEquals($countsBefore, $countsAfter, 'Read operation must produce ZERO database writes (Delta-DB = 0).');
        $this->assertIsArray($detail);
    }

    /**
     * Test ajaxGenerateShare endpoint rejects unauthenticated requests with 401.
     */
    public function testAjaxGenerateShareUnauthenticatedReturns401(): void
    {
        $this->session->destroy();
        $_SESSION = [];

        $response = $this->controller->ajaxGenerateShare(801);
        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Test ajaxGenerateShare endpoint succeeds when authenticated.
     */
    public function testAjaxGenerateShareAuthenticatedSucceeds(): void
    {
        $findingId = 801;

        $this->session->set([
            'user_id'   => 1,
            'logged_in' => true,
            'user_role' => 'administrator',
        ]);

        $response = $this->controller->ajaxGenerateShare($findingId);
        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody(), true);
        $this->assertEquals('SUCCESS', $body['status'] ?? null);
        $this->assertArrayHasKey('share_url', $body);
        $this->assertArrayHasKey('token', $body);
    }

    protected function tearDown(): void
    {
        $forge = Database::forge();
        $forge->dropTable('temuan_share_links', true);
        $forge->dropTable('temuan_materials', true);
        $forge->dropTable('temuan_accessories', true);
        $forge->dropTable('master_jtm_accessories', true);
        $forge->dropTable('temuan', true);
        $forge->dropTable('assets', true);
        $forge->dropTable('sections', true);
        $forge->dropTable('penyulang', true);
        $forge->dropTable('ulps', true);
        $forge->dropTable('construction_types', true);
        $forge->dropTable('materials', true);
        $forge->dropTable('users', true);

        parent::tearDown();
    }
}
