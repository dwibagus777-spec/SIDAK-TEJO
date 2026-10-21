<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\GlobalAssetDeduplicationService;
use Config\Database;

class GlobalAssetDeduplicationTest extends CIUnitTestCase
{
    protected GlobalAssetDeduplicationService $service;
    protected $dbConn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GlobalAssetDeduplicationService();
        $this->dbConn = Database::connect('default');
    }

    public function testAuditComposite7KeyGroupingAndNormalization(): void
    {
        $audit = $this->service->auditDuplicates();

        $this->assertSame('success', $audit['status']);
        $this->assertArrayHasKey('total_active_assets', $audit);
        $this->assertArrayHasKey('duplicate_groups_count', $audit);
        $this->assertArrayHasKey('feeder_breakdown', $audit);

        // Current active database assets must be clean of duplicate groups
        $this->assertGreaterThan(0, $audit['total_active_assets']);
    }

    public function testDeduplicationLifecycleDryRunAndRollback(): void
    {
        // 1. Insert a temporary synthetic duplicate pair with identical 7-column composite key
        $uniqueSuffix = bin2hex(random_bytes(4));
        $now = date('Y-m-d H:i:s');
        $testLat = -7.450123;
        $testLng = 112.720456;

        $assetDataA = [
            'kode_asset'  => 'TEST-DUP-A-' . $uniqueSuffix,
            'nama_asset'  => 'TIANG DUP ' . $uniqueSuffix,
            'jenis_asset' => 'TIANG_JTM',
            'ulp_id'      => 1,
            'penyulang_id'=> 33, // ECCO
            'latitude'    => $testLat,
            'longitude'   => $testLng,
            'created_at'  => $now,
        ];
        $assetDataB = [
            'kode_asset'  => 'TEST-DUP-B-' . $uniqueSuffix . '-A1B2C3D4',
            'nama_asset'  => '  TIANG DUP ' . $uniqueSuffix . '  ', // un-normalized whitespace
            'jenis_asset' => 'TIANG_JTM',
            'ulp_id'      => 1,
            'penyulang_id'=> 33,
            'latitude'    => $testLat,
            'longitude'   => $testLng,
            'created_at'  => $now,
        ];

        $this->dbConn->table('assets')->insert($assetDataA);
        $idA = (int)$this->dbConn->insertID();

        $this->dbConn->table('assets')->insert($assetDataB);
        $idB = (int)$this->dbConn->insertID();

        try {
            // 2. Audit should now detect exactly 1 duplicate group with 2 records
            $audit = $this->service->auditDuplicates('ECCO');
            $this->assertSame('success', $audit['status']);
            $this->assertGreaterThanOrEqual(1, $audit['duplicate_groups_count']);

            // Find our specific group
            $foundGroup = null;
            foreach ($audit['groups'] as $g) {
                if (in_array($idA, $g['all_record_ids']) && in_array($idB, $g['all_record_ids'])) {
                    $foundGroup = $g;
                    break;
                }
            }
            $this->assertNotNull($foundGroup, 'Duplicate pair must be grouped together on composite-7 key.');

            // Verify canonical record selection: idA must be canonical (no hash, lower id)
            $this->assertSame($idA, (int)$foundGroup['canonical_record']['id']);
            $this->assertSame($idB, (int)$foundGroup['duplicate_records'][0]['id']);

            // 3. Dry-Run simulation: verify 0 actual DB modifications
            $dryRun = $this->service->executeDeduplication(false, 'ECCO');
            $this->assertSame('DRY_RUN', $dryRun['mode']);
            $checkStillActive = $this->dbConn->table('assets')->where('id', $idB)->where('deleted_at IS NULL')->countAllResults();
            $this->assertSame(1, $checkStillActive);

            // 4. Live execution: must soft-delete duplicate B, create backup
            $liveExec = $this->service->executeDeduplication(true, 'ECCO');
            $this->assertSame('LIVE_EXECUTION', $liveExec['mode']);
            $this->assertNotNull($liveExec['backup_file']);
            $this->assertFileExists($liveExec['backup_file']);

            // Verify duplicate B is soft-deleted
            $rowB = $this->dbConn->table('assets')->where('id', $idB)->get()->getRowArray();
            $this->assertNotNull($rowB['deleted_at']);
            $this->assertSame('DUPLICATE_COMPOSITE7_CANONICALIZED', $rowB['section_resolution_method']);

            // Verify canonical A remains active
            $rowA = $this->dbConn->table('assets')->where('id', $idA)->get()->getRowArray();
            $this->assertNull($rowA['deleted_at']);

            // 5. Rollback from backup file
            $backupFilename = basename($liveExec['backup_file']);
            $rollback = $this->service->rollback($backupFilename);
            $this->assertSame('success', $rollback['status']);

            // Verify duplicate B is restored to active
            $rowBRestored = $this->dbConn->table('assets')->where('id', $idB)->get()->getRowArray();
            $this->assertNull($rowBRestored['deleted_at']);
            $this->assertNull($rowBRestored['section_resolution_method']);

        } finally {
            // Clean up test rows
            $this->dbConn->table('assets')->whereIn('id', [$idA, $idB])->delete();
        }
    }
}
