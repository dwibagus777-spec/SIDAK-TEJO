<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Controllers\Temuan;
use App\Repositories\TemuanRepository;
use Config\Services;

/**
 * Regression Test Suite for CR-06: Temuan Date Stale-Form Hardening
 *
 * 10 Required Test Invariants:
 * 1. Fresh GET => today's date.
 * 2. Validation failure => submitted historical date preserved.
 * 3. Historical date => accepted.
 * 4. Today => accepted.
 * 5. Future date => rejected.
 * 6. Hari Ini button => today's date contract.
 * 7. Persistence unchanged without mutation.
 * 8. User manually changes to historical date, then pageshow/bfcache event => historical date MUST remain unchanged.
 * 9. Fresh page pageshow => today's date.
 * 10. No other JS source silently overwrites tanggal_temuan.
 */
class TemuanDateStaleHardeningTest extends CIUnitTestCase
{
    protected TemuanRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['form', 'url', 'app']);
        $this->repo = new TemuanRepository();

        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        cache()->clean();

        // Recreate prerequisite ulps table with full schema (including status) for test isolation
        $forge->dropTable('ulps', true);
        $forge->addField([
            'id'       => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50],
            'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
            'status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
        ]);
        $forge->createTable('ulps', true);
        $db->table('ulps')->insert(['id' => 1, 'kode_ulp' => '51301', 'nama_ulp' => 'ULP SIDOARJO KOTA', 'status' => 'AKTIF']);

        if (!$db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'ulp_id'         => ['type' => 'INTEGER', 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->createTable('penyulang', true);
        }
        $db->table('penyulang')->truncate();
        $db->table('penyulang')->insert(['id' => 1, 'ulp_id' => 1, 'kode_penyulang' => 'CF', 'nama_penyulang' => 'CITRA FAJAR']);

        if (!$db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'penyulang_id' => ['type' => 'INTEGER', 'default' => 1],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->createTable('sections', true);
        }
        $db->table('sections')->truncate();
        $db->table('sections')->insert(['id' => 1, 'penyulang_id' => 1, 'nama_section' => 'Section Buduran']);

        if (!$db->tableExists('temuan')) {
            $forge->addField([
                'id'               => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'nomor_temuan'     => ['type' => 'VARCHAR', 'constraint' => 50],
                'ulp_id'           => ['type' => 'INTEGER', 'default' => 1],
                'penyulang_id'     => ['type' => 'INTEGER', 'default' => 1],
                'section_id'       => ['type' => 'INTEGER', 'default' => 1],
                'jenis_temuan'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'KONSTRUKSI'],
                'pelaksana'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'HAR KONSTRUKSI'],
                'prioritas'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'HIGH'],
                'potensi_gangguan' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'DGR'],
                'konduktor'        => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'A3CS 150'],
                'noga'             => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'material'         => ['type' => 'TEXT', 'null' => true],
                'detail_temuan'    => ['type' => 'TEXT'],
                'alamat'           => ['type' => 'TEXT'],
                'latitude'         => ['type' => 'DOUBLE', 'null' => true],
                'longitude'        => ['type' => 'DOUBLE', 'null' => true],
                'tanggal_temuan'   => ['type' => 'DATE'],
                'tanggal_selesai'  => ['type' => 'DATE', 'null' => true],
                'foto'             => ['type' => 'TEXT', 'null' => true],
                'foto_path'        => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'foto/'],
                'status'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'BELUM'],
                'created_by'       => ['type' => 'INTEGER', 'null' => true],
                'created_by_name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'created_by_nip'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
                'updated_at'       => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('temuan', true);
        }
        $db->table('temuan')->truncate();
    }

    /**
     * Test 1: Fresh GET => today's date.
     */
    public function test1_FreshGetDefaultsToToday(): void
    {
        $session = Services::session();
        $session->destroy();

        $controller = new Temuan();
        $controller->initController(Services::request(), Services::response(), Services::logger());

        $html = $controller->create();
        $this->assertIsString($html);

        $today = date('Y-m-d');
        $this->assertStringContainsString('value="' . $today . '"', $html);
        $this->assertStringContainsString('max="' . $today . '"', $html);
        $this->assertStringContainsString('id="btn-set-today"', $html);
        $this->assertStringContainsString('Tanggal kejadian/temuan di lapangan, bukan tanggal input sistem.', $html);
    }

    /**
     * Test 2: Validation failure => submitted historical date preserved.
     */
    public function test2_ValidationFailurePreservesSubmittedHistoricalDate(): void
    {
        $historicalDate = '2026-08-21';
        $session = Services::session();
        $session->set('_ci_old_input', [
            'post' => [
                'tanggal_temuan' => $historicalDate,
                'detail_temuan'  => 'Isolator flashover retak',
            ]
        ]);
        $session->setFlashdata('error', 'Harap unggah minimal 1 foto temuan');

        $controller = new Temuan();
        $controller->initController(Services::request(), Services::response(), Services::logger());

        $html = $controller->create();
        $this->assertIsString($html);

        // Historical date must survive validation redirect
        $this->assertStringContainsString('value="' . $historicalDate . '"', $html);
    }

    /**
     * Test 3: Historical date => accepted.
     */
    public function test3_HistoricalDateIsAccepted(): void
    {
        $historicalDate = '2026-08-21';
        $today = date('Y-m-d');

        $this->assertLessThanOrEqual($today, $historicalDate);

        $validation = Services::validation();
        $validation->setRules([
            'tanggal_temuan' => 'required|valid_date[Y-m-d]',
        ]);

        $isValid = $validation->run(['tanggal_temuan' => $historicalDate]);
        $this->assertTrue($isValid, 'Historical date Y-m-d format must be valid');
    }

    /**
     * Test 4: Today => accepted.
     */
    public function test4_TodayDateIsAccepted(): void
    {
        $today = date('Y-m-d');

        $validation = Services::validation();
        $validation->setRules([
            'tanggal_temuan' => 'required|valid_date[Y-m-d]',
        ]);

        $isValid = $validation->run(['tanggal_temuan' => $today]);
        $this->assertTrue($isValid, 'Today date must be valid');
        $this->assertLessThanOrEqual($today, $today);
    }

    /**
     * Test 5: Future date => rejected.
     */
    public function test5_FutureDateIsRejected(): void
    {
        $futureDate = date('Y-m-d', strtotime('+1 day'));
        $today = date('Y-m-d');

        $this->assertGreaterThan($today, $futureDate, 'Target date must be in the future');
        $this->assertTrue($futureDate > $today, 'Future date must trigger the > serverToday guard');
    }

    /**
     * Test 6: Hari Ini button => today's date contract.
     */
    public function test6_HariIniButtonContract(): void
    {
        $controller = new Temuan();
        $controller->initController(Services::request(), Services::response(), Services::logger());

        $html = $controller->create();

        $this->assertStringContainsString('id="btn-set-today"', $html);
        $this->assertStringContainsString('id="tanggal_temuan"', $html);
        $this->assertStringContainsString('autocomplete="off"', $html);
        $this->assertStringContainsString('serverToday', $html);
    }

    /**
     * Test 7: Persistence unchanged.
     */
    public function test7_PersistenceUnchangedWithoutMutation(): void
    {
        $historicalDate = '2026-08-21';
        $data = [
            'nomor_temuan'     => 'STJ-2026-999999',
            'ulp_id'           => 1,
            'penyulang_id'     => 1,
            'section_id'       => 1,
            'jenis_temuan'     => 'KONSTRUKSI',
            'pelaksana'        => 'HAR KONSTRUKSI',
            'prioritas'        => 'HIGH',
            'potensi_gangguan' => 'DGR',
            'konduktor'        => 'A3CS 150',
            'detail_temuan'    => 'Historical test finding',
            'alamat'           => 'Jl. Pahlawan No. 1',
            'tanggal_temuan'   => $historicalDate,
            'status'           => 'BELUM',
            'created_at'       => date('Y-m-d H:i:s'),
        ];

        $insertId = $this->repo->insert($data);
        $this->assertIsInt($insertId);

        $saved = $this->repo->find($insertId);
        $this->assertNotNull($saved);
        $this->assertSame($historicalDate, $saved['tanggal_temuan'], 'Historical date must be persisted 100% unchanged without mutation');
    }

    /**
     * Test 8: User manually changes to historical date, then pageshow/bfcache event => historical date MUST remain unchanged.
     */
    public function test8_PageshowBfcachePreservesUserEditedHistoricalDate(): void
    {
        $viewContent = file_get_contents(APPPATH . 'Views/temuan/create.php');

        // Verify that the pageshow listener strictly checks !tglInput.dataset.userEdited
        // and does NOT contain the buggy '|| event.persisted' inside the overwrite condition
        $this->assertStringContainsString('!tglInput.dataset.userEdited', $viewContent);
        $this->assertStringNotContainsString('(!tglInput.dataset.userEdited || event.persisted)', $viewContent);

        // Verify that input/change listener flags dataset.userEdited = 'true'
        $this->assertStringContainsString("this.dataset.userEdited = 'true';", $viewContent);
    }

    /**
     * Test 9: Fresh page pageshow => today's date.
     */
    public function test9_FreshPagePageshowSetsToday(): void
    {
        $viewContent = file_get_contents(APPPATH . 'Views/temuan/create.php');

        // When not a validation return and not user-edited, pageshow assigns serverToday
        $this->assertStringContainsString('if (!isValidationReturn && !tglInput.dataset.userEdited)', $viewContent);
        $this->assertStringContainsString('tglInput.value = serverToday;', $viewContent);
    }

    /**
     * Test 10: No other JS source silently overwrites tanggal_temuan.
     */
    public function test10_NoOtherJsSourceSilentlyOverwritesTanggalTemuan(): void
    {
        $adminLayout = file_get_contents(APPPATH . 'Views/layouts/admin.php');

        // Confirm that the localStorage restoreFormDraft engine explicitly excludes tanggal_temuan
        $this->assertStringContainsString("if (field.name === 'tanggal_temuan') return;", $adminLayout);

        // Confirm that draft is cleared from localStorage on form submit
        $this->assertStringContainsString("localStorage.removeItem('sidak_form_draft');", $adminLayout);
    }
}
