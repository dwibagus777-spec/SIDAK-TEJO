<?php

namespace Tests\Unit;

use App\Services\ServerSideAssetIngestEngine;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

require_once APPPATH . 'Database/Migrations/2026-10-02-000001_CreateIdempotentAssetIngestSchema.php';

use App\Database\Migrations\CreateIdempotentAssetIngestSchema;

class ServerSideAssetIngestEngineTest extends CIUnitTestCase
{
    protected ServerSideAssetIngestEngine $engine;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->engine = new ServerSideAssetIngestEngine($this->db);
        $this->ensureTables();
    }

    protected function ensureTables(): void
    {
        $migration = new \App\Database\Migrations\CreateIdempotentAssetIngestSchema();
        $migration->up();
    }

    public function testFingerprintDeterminism()
    {
        $row1 = [
            'unit'       => 'UP3 SIDOARJO',
            'ulp'        => 'ULP SIDOARJO KOTA',
            'feeder'     => 'FEEDER_TEST_01',
            'asset_name' => 'ISOLATOR_TUMPU_01',
            'section'    => 'SEC_01',
            'latitude'   => -7.445123,
            'longitude'  => 112.715123,
        ];

        $row2 = [
            'unit'       => 'up3 sidoarjo ',
            'ulp'        => ' ULP SIDOARJO KOTA',
            'feeder'     => 'feeder_test_01',
            'asset_name' => 'isolator_tumpu_01',
            'section'    => 'sec_01 ',
            'lat'        => '-7.445123',
            'long'       => '112.715123',
        ];

        $fp1 = $this->engine->generateSourceFingerprint($row1);
        $fp2 = $this->engine->generateSourceFingerprint($row2);

        $this->assertEquals(64, strlen($fp1));
        $this->assertEquals($fp1, $fp2, 'Fingerprints must be 100% deterministic despite case or whitespace variations.');
    }

    public function testSameSourceRowTwiceIdempotency()
    {
        $rows = [
            [
                'unit'       => 'UP3 SIDOARJO',
                'ulp'        => 'ULP PORONG',
                'feeder'     => 'FEEDER_PORONG_02',
                'asset_name' => 'TIANG_BETON_12M',
                'section'    => 'PORONG_SEC_05',
                'latitude'   => -7.534120,
                'longitude'  => 112.701120,
            ]
        ];

        // Batch 1
        $res1 = $this->engine->prepareBatch($rows, 'PART_01.csv', 1);
        $this->assertEquals(0, $res1['already_processed']);
        $this->assertEquals(1, $res1['source_rows']);

        // Batch 2 (Same row re-submitted in another batch)
        $res2 = $this->engine->prepareBatch($rows, 'PART_01_REPLAY.csv', 1);
        $this->assertEquals(1, $res2['already_processed']);
    }

    public function testDuplicateSourceRowInSameBatch()
    {
        $duplicateRows = [
            [
                'unit'       => 'UP3 SIDOARJO',
                'ulp'        => 'ULP WARU',
                'feeder'     => 'FEEDER_WARU_01',
                'asset_name' => 'TRAFO_250KVA',
                'section'    => 'WARU_SEC_01',
                'latitude'   => -7.361120,
                'longitude'  => 112.741120,
            ],
            [
                'unit'       => 'UP3 SIDOARJO',
                'ulp'        => 'ULP WARU',
                'feeder'     => 'FEEDER_WARU_01',
                'asset_name' => 'TRAFO_250KVA',
                'section'    => 'WARU_SEC_01',
                'latitude'   => -7.361120,
                'longitude'  => 112.741120,
            ]
        ];

        $res = $this->engine->prepareBatch($duplicateRows, 'DUP_BATCH.csv', 1);
        $this->assertEquals(2, $res['source_rows']);
        $this->assertEquals(1, $res['candidate_new_asset']);
        $this->assertEquals(1, $res['source_duplicate']);
    }

    public function testExistingAssetMatch()
    {
        $row = [
            'kode_asset' => 'AST-EXISTING-001',
            'unit'       => 'UP3 SIDOARJO',
            'ulp'        => 'ULP KRIAN',
            'feeder'     => 'FEEDER_KRIAN_01',
            'asset_name' => 'LBS_MANUAL_01',
            'section'    => 'KRIAN_SEC_02',
            'latitude'   => -7.411120,
            'longitude'  => 112.581120,
        ];

        $match = $this->engine->matchExistingAsset($row);
        $this->assertContains($match['status'], ['CANDIDATE_NEW_ASSET', 'MATCHED_EXISTING']);
    }

    public function testConflictAndQuarantineReview()
    {
        $outOfBoundsRow = [
            [
                'unit'       => 'UP3 SIDOARJO',
                'ulp'        => 'ULP KRIAN',
                'feeder'     => 'FEEDER_KRIAN_01',
                'asset_name' => 'INVALID_GPS_ASSET',
                'section'    => 'KRIAN_SEC_99',
                'latitude'   => 45.123456, // Invalid latitude for East Java
                'longitude'  => 112.581120,
            ]
        ];

        $res = $this->engine->prepareBatch($outOfBoundsRow, 'QUARANTINE_BATCH.csv', 1);
        $this->assertEquals(1, $res['quarantine']);
    }

    public function testControlledCommitAndZeroDuplicate()
    {
        $rows = [
            [
                'kode_asset' => 'AST-TEST-COMMIT-' . substr(bin2hex(random_bytes(4)), 0, 6),
                'unit'       => 'UP3 SIDOARJO',
                'ulp'        => 'ULP TANGGULANGIN',
                'feeder'     => 'FEEDER_TGL_01',
                'asset_name' => 'RECLOSER_AUTO_01',
                'section'    => 'TGL_SEC_01',
                'latitude'   => -7.501120,
                'longitude'  => 112.721120,
            ]
        ];

        $prep = $this->engine->prepareBatch($rows, 'COMMIT_TEST.csv', 1);
        $batchUuid = $prep['batch_uuid'];

        $commit = $this->engine->commitBatch($batchUuid);
        $this->assertEquals('COMMITTED', $commit['status']);
        $this->assertEquals(0, $commit['duplicate_created']);
        $this->assertEquals(0, $commit['topology_delta']);
    }

    public function testTopologyImmutabilityAndForensicAudit()
    {
        $forensic = $this->engine->forensicCheck();

        $this->assertArrayHasKey('active_assets', $forensic);
        $this->assertArrayHasKey('physical_assets', $forensic);
        $this->assertArrayHasKey('topology_snapshot', $forensic);
        $this->assertEquals(0, $forensic['topology_delta']);
        $this->assertEquals(0, $forensic['duplicate_created']);
        $this->assertEquals('CLEAN_ZERO_MUTATION', $forensic['forensic_status']);
    }

    public function testTenTimesDeterministicReplay()
    {
        $rows = [
            [
                'unit'       => 'UP3 SIDOARJO',
                'ulp'        => 'ULP SEDATI',
                'feeder'     => 'FEEDER_SEDATI_01',
                'asset_name' => 'ARRESTER_20KV',
                'section'    => 'SEDATI_SEC_01',
                'latitude'   => -7.381120,
                'longitude'  => 112.771120,
            ]
        ];

        for ($i = 1; $i <= 10; $i++) {
            $prep = $this->engine->prepareBatch($rows, "REPLAY_RUN_{$i}.csv", 1);
            $commit = $this->engine->commitBatch($prep['batch_uuid']);
            $this->assertEquals(0, $commit['duplicate_created'], "Replay run {$i} must produce 0 duplicate created.");
        }
    }
}
