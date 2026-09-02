<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Services\TranslineCompletionService;
use App\Services\TranslineNetworkGraphService;
use App\Services\TranslineAutoCompletionService;

/**
 * TL-02 Phase 2.1: Progressive AI Network Completion Test Suite
 *
 * 30 Unit Tests covering:
 * - Dynamic progressive multi-batch execution loop
 * - Graph recalculation & node degree capacity (max 4)
 * - Bearing azimuth & angular delta evaluation
 * - Asset code sequence adjacency evaluation
 * - Geodesic Haversine plausibility & bounds
 * - 24 Safety gates validation
 * - Atomic transaction rollback & exact PK rollback
 * - Zero mutation invariants (assets, temuan, baseline lines)
 * - Deterministic stop conditions & idempotency
 */
class TranslineAiProgressiveCompletionTest extends CIUnitTestCase
{
    protected $db;
    protected TranslineNetworkGraphService $graphService;
    protected TranslineCompletionService $completionService;
    protected TranslineAutoCompletionService $autoService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->setupSchema();
        $this->seedTestData();

        $this->graphService = new TranslineNetworkGraphService($this->db);
        $this->completionService = new TranslineCompletionService($this->db);
        $this->autoService = new TranslineAutoCompletionService($this->db, null, $this->completionService);
    }

    protected function safeAddColumn(string $table, string $column, array $def): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }
        $prefixed = $this->db->prefixTable($table);
        $cols = array_column($this->db->query("PRAGMA table_info({$prefixed})")->getResultArray(), 'name');
        if (!in_array($column, $cols, true)) {
            try {
                Database::forge()->addColumn($table, [$column => $def]);
            } catch (\Throwable $e) {
                // Column already exists
            }
        }
    }

    protected function setupSchema(): void
    {
        $forge = Database::forge();

        if (!$this->db->tableExists('ulps')) {
            $forge->addField([
                'id'       => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('ulps', true);
        }

        if (!$this->db->tableExists('penyulang')) {
            $forge->addField([
                'id'             => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'ulp_id'         => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
                'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('penyulang', true);
        }

        if (!$this->db->tableExists('sections')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('sections', true);
        }

        if (!$this->db->tableExists('assets')) {
            $forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Tiang JTM'],
                'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_BETON'],
                'type'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'ulp_id'               => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'penyulang_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'sequence_no'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'construction_type_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'latitude'             => ['type' => 'DECIMAL', 'constraint' => '10,8', 'null' => true],
                'longitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
                'lokasi'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'parent_asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'status'               => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'NORMAL'],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('assets', true);
        } else {
            $this->safeAddColumn('assets', 'parent_asset_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
        }

        if (!$this->db->tableExists('gis_translines')) {
            $forge->addField([
                'id'                 => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'transline_code'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'penyulang_id'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'section_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'source_asset_id'    => ['type' => 'INT', 'constraint' => 11],
                'target_asset_id'    => ['type' => 'INT', 'constraint' => 11],
                'from_asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'to_asset_id'        => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'geometry'           => ['type' => 'TEXT', 'null' => true],
                'geometry_type'      => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'LineString'],
                'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'conductor_material' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'ALUMINUM_ALLOY'],
                'installation_type'  => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'OVERHEAD'],
                'circuit_config'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '3_PHASE'],
                'distance_meters'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 45.00],
                'length_m'           => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'source'             => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'MANUAL'],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVE'],
                'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by'         => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'MANUAL_IMPORT'],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
                'updated_at'         => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_translines', true);
        } else {
            $this->safeAddColumn('gis_translines', 'section_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
            $this->safeAddColumn('gis_translines', 'source', ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'MANUAL']);
            $this->safeAddColumn('gis_translines', 'from_asset_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
            $this->safeAddColumn('gis_translines', 'to_asset_id', ['type' => 'INT', 'constraint' => 11, 'null' => true]);
            $this->safeAddColumn('gis_translines', 'length_m', ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true]);
            $this->safeAddColumn('gis_translines', 'is_active', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]);
        }

        if (!$this->db->tableExists('gis_transline_proposals')) {
            $forge->addField([
                'id'                      => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id'            => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'section_id'              => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'source_asset_id'         => ['type' => 'INT', 'constraint' => 11],
                'target_asset_id'         => ['type' => 'INT', 'constraint' => 11],
                'natural_key'             => ['type' => 'VARCHAR', 'constraint' => 128],
                'proposed_conductor_type' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
                'proposed_conductor_size' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
                'proposed_distance'       => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 40.00],
                'proposed_geometry'       => ['type' => 'TEXT', 'null' => true],
                'classification'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'AUTO_MATCH'],
                'confidence_score'        => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0.95],
                'evidence_json'           => ['type' => 'TEXT', 'null' => true],
                'status'                  => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'PENDING_REVIEW'],
                'confirmed_transline_id'  => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'proposal_source'         => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'DETERMINISTIC_ENGINE'],
                'engine_version'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'TL-02-V1.0'],
                'reviewed_by'             => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'reviewed_at'             => ['type' => 'DATETIME', 'null' => true],
                'review_note'             => ['type' => 'TEXT', 'null' => true],
                'created_at'              => ['type' => 'DATETIME', 'null' => true],
                'updated_at'              => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'              => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('gis_transline_proposals', true);
        }

        if (!$this->db->tableExists('temuan')) {
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                'asset_id'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'judul'        => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Temuan Anomali'],
                'status'       => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'OPEN'],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('temuan', true);
        }
    }

    protected function seedTestData(): void
    {
        $this->db->table('gis_transline_proposals')->truncate();
        $this->db->table('gis_translines')->truncate();
        $this->db->table('assets')->truncate();
        $this->db->table('sections')->truncate();
        $this->db->table('penyulang')->truncate();
        $this->db->table('ulps')->truncate();
        $this->db->table('temuan')->truncate();

        $this->db->table('ulps')->insert([
            'id' => 1, 'kode_ulp' => 'ULP_SDA', 'nama_ulp' => 'ULP SIDOARJO KOTA'
        ]);

        $this->db->table('penyulang')->insert([
            'id' => 15, 'ulp_id' => 1, 'kode_penyulang' => 'BKM', 'nama_penyulang' => 'BANJAR KEMANTREN'
        ]);

        $this->db->table('sections')->insert([
            'id' => 101, 'penyulang_id' => 15, 'nama_section' => 'MAIN_SECTION'
        ]);

        // Seed linear chain of 25 JTM poles (approx 40m apart extending eastward)
        // Lat: -7.450000, Lon: 112.710000 + (i * 0.000360) ~ 40m
        for ($i = 1; $i <= 25; $i++) {
            $codeNum = sprintf('%03d', $i);
            $this->db->table('assets')->insert([
                'id'           => 1000 + $i,
                'kode_asset'   => "AST-BKM-JTM-{$codeNum}",
                'nama_asset'   => "Tiang BKM {$i}",
                'jenis_asset'  => 'TIANG_BETON',
                'ulp_id'       => 1,
                'penyulang_id' => 15,
                'section_id'   => 101,
                'sequence_no'  => $i,
                'latitude'     => -7.450000,
                'longitude'    => 112.710000 + (($i - 1) * 0.000360),
                'status'       => 'NORMAL',
            ]);
        }

        // Connect first 5 poles with existing manual translines (Poles 1001-1002-1003-1004-1005)
        for ($i = 1; $i <= 4; $i++) {
            $sId = 1000 + $i;
            $tId = 1000 + $i + 1;
            $this->db->table('gis_translines')->insert([
                'id'              => $i,
                'transline_code'  => "TL-MANUAL-15-{$sId}-{$tId}",
                'penyulang_id'    => 15,
                'section_id'      => 101,
                'source_asset_id' => $sId,
                'target_asset_id' => $tId,
                'distance_meters' => 40.0,
                'source'          => 'MANUAL',
                'status'          => 'ACTIVE',
                'is_active'       => 1,
                'created_by'      => 'MANUAL_SURVEY_BASELINE',
            ]);
        }

        // Seed 1 temuan record to verify protected zero write
        $this->db->table('temuan')->insert([
            'id' => 501, 'penyulang_id' => 15, 'asset_id' => 1001, 'judul' => 'Arrester Pecah', 'status' => 'OPEN'
        ]);
    }

    // 1. Bearing azimuth zero deg north
    public function testBearingCalculationZeroDegNorth(): void
    {
        $bearing = $this->completionService->calculateBearing(-7.45000, 112.71000, -7.44000, 112.71000);
        $this->assertEqualsWithDelta(0.0, $bearing, 0.5);
    }

    // 2. Bearing azimuth 90 deg east
    public function testBearingCalculation90DegEast(): void
    {
        $bearing = $this->completionService->calculateBearing(-7.45000, 112.71000, -7.45000, 112.72000);
        $this->assertEqualsWithDelta(90.0, $bearing, 0.5);
    }

    // 3. Bearing delta acute
    public function testBearingDeltaAcute(): void
    {
        $delta = $this->completionService->calculateBearingDelta(85.0, 95.0);
        $this->assertEqualsWithDelta(10.0, $delta, 0.01);
    }

    // 4. Bearing delta wrap around 180
    public function testBearingDeltaWrapAround180(): void
    {
        $delta = $this->completionService->calculateBearingDelta(5.0, 355.0);
        $this->assertEqualsWithDelta(10.0, $delta, 0.01);
    }

    // 5. Asset sequence number extraction valid
    public function testAssetSequenceNumberExtractionValid(): void
    {
        $seq = $this->completionService->parseAssetSequenceNumber('AST-BKM-JTM-042');
        $this->assertSame(42, $seq);
    }

    // 6. Asset sequence number extraction null
    public function testAssetSequenceNumberExtractionNull(): void
    {
        $seq = $this->completionService->parseAssetSequenceNumber(null);
        $this->assertNull($seq);
    }

    // 7. Dynamic candidate scoring anchor bonus
    public function testDynamicCandidateScoringAnchorBonus(): void
    {
        $candidates = $this->completionService->generateNetworkCompletionCandidates(15);
        $newCand = array_filter($candidates['candidates'], fn($c) => !$c['is_existing']);
        $this->assertNotEmpty($newCand);

        // First new candidate should connect to pole 1005 (the terminal anchor)
        $first = array_values($newCand)[0];
        $this->assertTrue(
            $first['source_asset_id'] === 1005 || $first['target_asset_id'] === 1005,
            'First progressive candidate must connect to terminal degree-1 anchor'
        );
        $this->assertContains('ANCHOR_CONTINUATION_EVIDENCE', $first['evidence']);
    }

    // 8. Dynamic candidate scoring sequence adjacency
    public function testDynamicCandidateScoringSequenceAdjacency(): void
    {
        $candidates = $this->completionService->generateNetworkCompletionCandidates(15);
        $c1005to1006 = null;
        foreach ($candidates['candidates'] as $c) {
            if (($c['source_asset_id'] === 1005 && $c['target_asset_id'] === 1006) ||
                ($c['source_asset_id'] === 1006 && $c['target_asset_id'] === 1005)) {
                $c1005to1006 = $c;
                break;
            }
        }
        $this->assertNotNull($c1005to1006);
        $this->assertContains('CONSECUTIVE_CODE_SEQUENCE', $c1005to1006['evidence']);
    }

    // 9. Bearing continuity collinear bonus
    public function testDynamicCandidateScoringBearingContinuityCollinear(): void
    {
        $candidates = $this->completionService->generateNetworkCompletionCandidates(15);
        $c1005to1006 = null;
        foreach ($candidates['candidates'] as $c) {
            if (($c['source_asset_id'] === 1005 && $c['target_asset_id'] === 1006) ||
                ($c['source_asset_id'] === 1006 && $c['target_asset_id'] === 1005)) {
                $c1005to1006 = $c;
                break;
            }
        }
        $this->assertNotNull($c1005to1006);
        $this->assertContains('COLLINEAR_BEARING_CONTINUITY', $c1005to1006['evidence']);
    }

    // 10. Bearing sharp turn warning
    public function testDynamicCandidateScoringBearingSharpTurnZeroBonus(): void
    {
        // Insert a pole at a 90 degree orthogonal angle from pole 1005
        $this->db->table('assets')->insert([
            'id'           => 1999,
            'kode_asset'   => 'AST-BKM-JTM-ORTHO',
            'nama_asset'   => 'Tiang Ortho',
            'ulp_id'       => 1,
            'penyulang_id' => 15,
            'latitude'     => -7.449640, // 40m North instead of East
            'longitude'    => 112.711440,
            'status'       => 'NORMAL',
        ]);

        $candidates = $this->completionService->generateNetworkCompletionCandidates(15);
        $orthoCand = null;
        foreach ($candidates['candidates'] as $c) {
            if (($c['source_asset_id'] === 1005 && $c['target_asset_id'] === 1999) ||
                ($c['source_asset_id'] === 1999 && $c['target_asset_id'] === 1005)) {
                $orthoCand = $c;
                break;
            }
        }
        $this->assertNotNull($orthoCand);
        $this->assertContains('SHARP_BEARING_CHANGE_WARNING', $orthoCand['evidence']);
    }

    // 11. Distance optimal span (20m - 55m)
    public function testDynamicCandidateScoringDistanceOptimal(): void
    {
        $candidates = $this->completionService->generateNetworkCompletionCandidates(15);
        $c1005to1006 = null;
        foreach ($candidates['candidates'] as $c) {
            if (($c['source_asset_id'] === 1005 && $c['target_asset_id'] === 1006) ||
                ($c['source_asset_id'] === 1006 && $c['target_asset_id'] === 1005)) {
                $c1005to1006 = $c;
                break;
            }
        }
        $this->assertNotNull($c1005to1006);
        $this->assertContains('NOMINAL_JTM_SPAN', $c1005to1006['evidence']);
    }

    // 12. Distance extended road span (55m - 85m)
    public function testDynamicCandidateScoringDistanceExtended(): void
    {
        // Insert a pole at 70m distance
        $this->db->table('assets')->insert([
            'id'           => 1888,
            'kode_asset'   => 'AST-BKM-JTM-70M',
            'nama_asset'   => 'Tiang 70m',
            'ulp_id'       => 1,
            'penyulang_id' => 15,
            'latitude'     => -7.450000,
            'longitude'    => 112.711440 + (70.0 / 111320.0),
            'status'       => 'NORMAL',
        ]);

        $candidates = $this->completionService->generateNetworkCompletionCandidates(15);
        $extCand = null;
        foreach ($candidates['candidates'] as $c) {
            if (($c['source_asset_id'] === 1005 && $c['target_asset_id'] === 1888) ||
                ($c['source_asset_id'] === 1888 && $c['target_asset_id'] === 1005)) {
                $extCand = $c;
                break;
            }
        }
        $this->assertNotNull($extCand);
        $this->assertContains('EXTENDED_ROAD_SPAN', $extCand['evidence']);
    }

    // 13. Distance excessive (>100m) blocked
    public function testDynamicCandidateScoringDistanceExcessiveBlocked(): void
    {
        $gate = $this->autoService->validateCandidateGates([
            'source_asset_id' => 1001,
            'target_asset_id' => 1025, // Distance ~ 1000m
            'penyulang_id'    => 15,
        ]);
        $this->assertFalse($gate['valid']);
        $this->assertSame('EXCESSIVE_OR_IMPLAUSIBLE_DISTANCE', $gate['reason']);
    }

    // 14. Degree cap mainline extension allowed (d=1 -> d=2)
    public function testDegreeCapMainlineExtensionAllowed(): void
    {
        $graph = $this->graphService->buildGraphForFeeder(15);
        $this->assertSame(1, $graph['degrees'][1005] ?? 0);

        $candidates = $this->completionService->generateNetworkCompletionCandidates(15);
        $c1005to1006 = null;
        foreach ($candidates['candidates'] as $c) {
            if (($c['source_asset_id'] === 1005 && $c['target_asset_id'] === 1006) ||
                ($c['source_asset_id'] === 1006 && $c['target_asset_id'] === 1005)) {
                $c1005to1006 = $c;
                break;
            }
        }
        $this->assertNotNull($c1005to1006);
        $this->assertSame('AUTO_COMPLETE', $c1005to1006['classification']);
    }

    // 15. Degree cap: degree > 4 blocked from batch
    public function testDegreeCapFourBlockedFromBatch(): void
    {
        // Force node 1005 to have degree 4 by adding lines
        for ($k = 1; $k <= 3; $k++) {
            $dummyId = 2000 + $k;
            $this->db->table('assets')->insert([
                'id'           => $dummyId,
                'kode_asset'   => "AST-BKM-DUMMY-{$k}",
                'nama_asset'   => "Dummy {$k}",
                'ulp_id'       => 1,
                'penyulang_id' => 15,
                'latitude'     => -7.450000 + ($k * 0.0001),
                'longitude'    => 112.711440,
                'status'       => 'NORMAL',
            ]);
            $this->db->table('gis_translines')->insert([
                'id'              => 500 + $k,
                'penyulang_id'    => 15,
                'source_asset_id' => 1005,
                'target_asset_id' => $dummyId,
                'is_active'       => 1,
            ]);
        }

        $graph = $this->graphService->buildGraphForFeeder(15);
        $this->assertSame(4, $graph['degrees'][1005]);

        $candidates = $this->completionService->generateNetworkCompletionCandidates(15);
        $c1005to1006 = null;
        foreach ($candidates['candidates'] as $c) {
            if (($c['source_asset_id'] === 1005 && $c['target_asset_id'] === 1006) ||
                ($c['source_asset_id'] === 1006 && $c['target_asset_id'] === 1005)) {
                $c1005to1006 = $c;
                break;
            }
        }
        $this->assertNotNull($c1005to1006);
        $this->assertSame('BLOCKED', $c1005to1006['classification']);
    }

    // 16. Generate candidates dynamic return structure
    public function testGenerateCandidatesDynamicReturnStructure(): void
    {
        $res = $this->autoService->generateCandidates(15);
        $this->assertArrayHasKey('scope', $res);
        $this->assertArrayHasKey('summary', $res);
        $this->assertArrayHasKey('candidates', $res);
        $this->assertArrayHasKey('new_auto_complete', $res['summary']);
    }

    // 17. Execute batch with zero candidates returns stop true
    public function testExecuteBatchZeroCandidatesReturnsStopTrue(): void
    {
        $res = $this->autoService->executeBatch(9999); // Non-existent feeder
        $this->assertSame('success', $res['status']);
        $this->assertSame('NO_MORE_AUTO_COMPLETE_CANDIDATES', $res['action']);
        $this->assertSame(0, $res['created_count']);
        $this->assertTrue($res['stop']);
    }

    // 18. Execute batch with invalid penyulang ID aborts
    public function testExecuteBatchWithInvalidPenyulangIdAborts(): void
    {
        $res = $this->autoService->executeBatch(0);
        $this->assertSame('error', $res['status']);
        $this->assertSame('ABORT', $res['action']);
    }

    // 19. Execute batch executes up to max batch 10
    public function testExecuteBatchExecutesUpToMaxBatchTen(): void
    {
        $res = $this->autoService->executeBatch(15, ['max_batch' => 10]);
        $this->assertSame('success', $res['status']);
        $this->assertSame('BATCH_COMMITTED', $res['action']);
        $this->assertLessThanOrEqual(10, $res['created_count']);
        $this->assertGreaterThan(0, $res['created_count']);
    }

    // 20. Execute batch creates translines with accurate provenance
    public function testExecuteBatchCreatesTranslinesWithAccurateProvenance(): void
    {
        $res = $this->autoService->executeBatch(15, ['actor_name' => 'ENGINEER_TRANSLINE_AI']);
        $this->assertSame('success', $res['status']);
        $this->assertNotEmpty($res['created_translines']);

        $firstCreated = $res['created_translines'][0];
        $this->assertStringContainsString('ENGINEER_TRANSLINE_AI', $firstCreated['created_by']);
        $this->assertStringContainsString('RUN:', $firstCreated['created_by']);
    }

    // 21. Execute batch sets active status and conductor spec
    public function testExecuteBatchSetsActiveStatusAndConductorSpec(): void
    {
        $res = $this->autoService->executeBatch(15);
        $tlId = $res['created_translines'][0]['id'];

        $row = $this->db->table('gis_translines')->where('id', $tlId)->get()->getRowArray();
        $this->assertSame(1, (int)$row['is_active']);
        $this->assertSame('ACTIVE', $row['status']);
        $this->assertSame('AAAC', $row['conductor_type']);
        $this->assertSame('150 mm²', $row['conductor_size']);
        $this->assertSame('LineString', $row['geometry_type']);
    }

    // 22. Execute batch enforces atomic commit on failure
    public function testExecuteBatchEnforcesAtomicCommitOnFailure(): void
    {
        $countBefore = $this->db->table('gis_translines')->where('penyulang_id', 15)->countAllResults();

        // Attempting to execute with invalid proposal IDs must rollback
        $res = $this->autoService->execute([999999]);
        $this->assertSame('error', $res['status']);

        $countAfter = $this->db->table('gis_translines')->where('penyulang_id', 15)->countAllResults();
        $this->assertSame($countBefore, $countAfter, 'Rollback must ensure 0 phantom writes');
    }

    // 23. Execute batch recomputes graph post execution
    public function testExecuteBatchRecomputesGraphPostExecution(): void
    {
        $res = $this->autoService->executeBatch(15);
        $this->assertArrayHasKey('remaining_auto_complete_count', $res);
        $this->assertArrayHasKey('total_authoritative_translines', $res);
        $this->assertGreaterThan(4, $res['total_authoritative_translines']);
    }

    // 24. Progressive completion multi-batch loop
    public function testProgressiveCompletionMultiBatchLoop(): void
    {
        $initialLines = $this->db->table('gis_translines')->where('penyulang_id', 15)->countAllResults();
        $this->assertSame(4, $initialLines);

        // Run Batch 1
        $batch1 = $this->autoService->executeBatch(15, ['batch_number' => 1]);
        $this->assertSame('success', $batch1['status']);
        $this->assertGreaterThan(0, $batch1['created_count']);

        // Run Batch 2
        $batch2 = $this->autoService->executeBatch(15, ['batch_number' => 2]);
        $this->assertSame('success', $batch2['status']);

        $finalLines = $this->db->table('gis_translines')->where('penyulang_id', 15)->countAllResults();
        $this->assertGreaterThan($initialLines + $batch1['created_count'], $finalLines);
    }

    // 25. Progressive completion loop stops deterministically
    public function testProgressiveCompletionLoopStopsDeterministically(): void
    {
        // Keep only assets up to 1008 so loop reaches end-of-line termination in 3 steps
        $this->db->table('assets')->where('id >', 1008)->delete();

        $iteration = 0;
        $maxIterations = 10;
        $stopped = false;

        while ($iteration < $maxIterations) {
            $iteration++;
            $res = $this->autoService->executeBatch(15, ['batch_number' => $iteration]);
            if (!empty($res['stop']) || $res['created_count'] === 0) {
                $stopped = true;
                break;
            }
        }

        $this->assertTrue($stopped, 'Progressive loop must terminate deterministically');
    }

    // 26. Gate asset endpoint integrity zero temuan
    public function testGateAssetEndpointIntegrityZeroTemuan(): void
    {
        $gate = $this->autoService->validateCandidateGates([
            'source_asset_id' => 'TEMUAN_501',
            'target_asset_id' => 1002,
            'penyulang_id'    => 15,
        ]);
        $this->assertFalse($gate['valid']);
        $this->assertSame('TEMUAN_ENDPOINT_FORBIDDEN', $gate['reason']);
    }

    // 27. Gate cross feeder mismatch blocked
    public function testGateCrossFeederMismatchBlocked(): void
    {
        // Insert asset on feeder 99
        $this->db->table('assets')->insert([
            'id'           => 1777,
            'kode_asset'   => 'AST-CROSS-01',
            'nama_asset'   => 'Cross Feeder Pole',
            'ulp_id'       => 1,
            'penyulang_id' => 99,
            'latitude'     => -7.450000,
            'longitude'    => 112.711440,
            'status'       => 'NORMAL',
        ]);

        $gate = $this->autoService->validateCandidateGates([
            'source_asset_id' => 1005,
            'target_asset_id' => 1777,
            'penyulang_id'    => 15,
        ]);
        $this->assertFalse($gate['valid']);
        $this->assertSame('CROSS_FEEDER_ILLEGAL_RELATIONSHIP', $gate['reason']);
    }

    // 28. Gate cross ULP mismatch blocked
    public function testGateCrossUlpMismatchBlocked(): void
    {
        // Insert asset on ULP 99
        $this->db->table('assets')->insert([
            'id'           => 1666,
            'kode_asset'   => 'AST-CROSS-ULP',
            'nama_asset'   => 'Cross ULP Pole',
            'ulp_id'       => 99,
            'penyulang_id' => 15,
            'latitude'     => -7.450000,
            'longitude'    => 112.711440,
            'status'       => 'NORMAL',
        ]);

        $gate = $this->autoService->validateCandidateGates([
            'source_asset_id' => 1005,
            'target_asset_id' => 1666,
            'penyulang_id'    => 15,
        ]);
        $this->assertFalse($gate['valid']);
        $this->assertSame('CROSS_ULP_ILLEGAL_RELATIONSHIP', $gate['reason']);
    }

    // 29. Protected tables zero mutation
    public function testProtectedTablesZeroMutationAssetsAndTemuan(): void
    {
        $assetsBefore = $this->db->table('assets')->get()->getResultArray();
        $temuanBefore = $this->db->table('temuan')->get()->getResultArray();

        $this->autoService->executeBatch(15);

        $assetsAfter = $this->db->table('assets')->get()->getResultArray();
        $temuanAfter = $this->db->table('temuan')->get()->getResultArray();

        $this->assertSame(count($assetsBefore), count($assetsAfter), 'Assets count must be strictly immutable');
        $this->assertSame(count($temuanBefore), count($temuanAfter), 'Temuan count must be strictly immutable');
        $this->assertEquals($assetsBefore, $assetsAfter, 'Assets content must remain strictly identical');
        $this->assertEquals($temuanBefore, $temuanAfter, 'Temuan content must remain strictly identical');
    }

    // 30. Rollback exact transline ID preserves prior authoritative lines
    public function testRollbackExactTranslineIdPreservesPriorAuthoritativeLines(): void
    {
        $res = $this->autoService->executeBatch(15, ['actor_name' => 'ENGINEER_TRANSLINE_AI']);
        $createdId = $res['created_translines'][0]['id'];
        $runId = $res['run_id'];

        $rollbackRes = $this->autoService->rollback($createdId, $runId);
        $this->assertSame('success', $rollbackRes['status']);
        $this->assertSame('ROLLBACK_COMMITTED', $rollbackRes['action']);

        // Check baseline lines (1-4) are intact
        $baseline1 = $this->db->table('gis_translines')->where('id', 1)->get()->getRowArray();
        $this->assertNotNull($baseline1);
        $this->assertSame('MANUAL_SURVEY_BASELINE', $baseline1['created_by']);
    }
}
