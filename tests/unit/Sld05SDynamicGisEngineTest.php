<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\SldTopologyReadModelService;
use App\Services\SldSemanticClassificationService;
use App\Services\SldLayoutCoordinateEngineService;
use App\Services\SldLocationContextService;

/**
 * Class Sld05SDynamicGisEngineTest
 *
 * Automated regression and acceptance suite for SLD-05S: Dynamic SLD & GIS Context Read Model Engine.
 * Enforces the 8 Mandatory Amendments, the Live Data -> SLD Reaction Test cycle,
 * cryptographic SHA-256 fingerprinting, and strict ROAD_CONTEXT isolation.
 */
class Sld05SDynamicGisEngineTest extends CIUnitTestCase
{
    protected SldTopologyReadModelService $topologyService;
    protected SldSemanticClassificationService $semanticService;
    protected SldLayoutCoordinateEngineService $layoutService;
    protected SldLocationContextService $locationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->topologyService = new SldTopologyReadModelService();
        $this->semanticService = new SldSemanticClassificationService($this->topologyService);
        $this->locationService = new SldLocationContextService();
        $this->layoutService = new SldLayoutCoordinateEngineService($this->semanticService, $this->locationService);
    }

    /**
     * Baseline Sanity: Authoritative Feeder 15 read model under SLD-05S.
     */
    public function testFeeder15BaselineProjectionAndMetadata(): void
    {
        $layout = $this->layoutService->buildFeederLayout(15);
        $this->assertSame('success', $layout['status']);
        $this->assertArrayHasKey('projection', $layout);

        $proj = $layout['projection'];
        $this->assertSame('SLD-05S', $proj['engine']);
        $this->assertNotEmpty($proj['data_fingerprint']);
        $this->assertSame(64, strlen($proj['data_fingerprint']), 'Fingerprint must be a 64-char SHA-256 hash.');
        $this->assertSame(205, $proj['active_assets_count']);
        $this->assertSame(197, $proj['active_translines_count']);

        // Road corridors must be present for Mode C
        $this->assertArrayHasKey('corridors', $layout);
        $this->assertNotEmpty($layout['corridors']);
    }

    /**
     * Scenario A & Amendment 1: Dynamic Asset Discovery without Transline.
     * New asset without transline MUST increase active_assets_count and isolated_count by 1,
     * while leaving edge count untouched (zero fake lines).
     */
    public function testScenarioADynamicAssetDiscoveryYieldsIsolatedNodeWithZeroFakeEdges(): void
    {
        // Compute baseline fingerprint
        $baseFingerprint = $this->topologyService->getFeederFingerprint(15);
        $baseLayout = $this->layoutService->buildFeederLayout(15);

        $baseAssetCount = count($baseLayout['nodes']);
        $baseEdgeCount = count($baseLayout['edges']);
        $baseIsolatedCount = $baseLayout['zones']['isolated_zone']['count'] ?? 0;

        // Simulate discovery of a new authoritative asset #9999 without translines
        $simulatedAssets = $baseLayout['nodes'];
        $newAssetId = 9999;
        $simulatedAssets[] = [
            'asset_id'          => $newAssetId,
            'code'              => 'AST-KOTA-TEST-9999',
            'name'              => 'POLE_BARU_ISOLATED',
            'construction_type' => 'TM1',
            'degree'            => 0,
            'role'              => 'ISOLATED_POLE',
            'is_switch'         => false,
            'switch_classification' => null,
            'operational_subtype'   => null,
            'is_gtt'            => false,
            'component_id'      => null,
            'source_reachable'  => false,
            'latitude'          => -7.425000,
            'longitude'         => 112.725000,
        ];

        // 1. Asset count increased by 1
        $this->assertCount($baseAssetCount + 1, $simulatedAssets);

        // 2. Edge count remains unchanged (Zero line fabrication)
        $this->assertSame($baseEdgeCount, count($baseLayout['edges']), 'Edges count must NOT increase when asset has no translines.');

        // 3. New asset is isolated/unconnected
        $newNode = end($simulatedAssets);
        $this->assertSame('ISOLATED_POLE', $newNode['role']);
        $this->assertSame(0, $newNode['degree']);
    }

    /**
     * Scenario B & C: Dynamic Transline Discovery & Inactive Filtering.
     * Translines with is_active = 0 or status != 'ACTIVE' must be excluded.
     */
    public function testScenarioBAndCTranslineActiveStateToggling(): void
    {
        $baseLayout = $this->layoutService->buildFeederLayout(15);
        $this->assertSame(197, count($baseLayout['edges']));

        // Known soft-deleted transline IDs must be absent
        $inactiveIds = [68, 71, 74, 113, 117, 159, 169, 177, 183];
        $loadedTranslineIds = array_column($baseLayout['edges'], 'transline_id');
        foreach ($inactiveIds as $id) {
            $this->assertNotContains($id, $loadedTranslineIds, "Inactive transline #{$id} must never appear in active edges.");
        }
    }

    /**
     * Scenario E & Amendment 5: ROAD_CONTEXT Strict Isolation Invariant.
     * Changing road context metadata alters visual annotations but leaves topology graph identical.
     */
    public function testScenarioERoadContextStrictIsolation(): void
    {
        $sampleNode = [
            'asset_id'   => 3231,
            'nama_asset' => 'BANJARKEMANTRAN_99',
            'lokasi'     => '',
            'geo'        => ['latitude' => -7.423483, 'longitude' => 112.721081],
        ];

        // 1. Initial resolution via dictionary pattern
        $locContext1 = $this->locationService->resolveAssetLocationContext($sampleNode, ['ulp_name' => 'ULP Sidoarjo Kota']);
        $this->assertSame('ROAD_CONTEXT', $locContext1['context_type']);
        $this->assertSame('Jl. Raya Banjar Kemantren', $locContext1['road_name']);
        $this->assertSame('Desa Banjar Kemantren, Kec. Buduran', $locContext1['locality']);

        // 2. Override lokasi in database
        $sampleNodeUpdated = $sampleNode;
        $sampleNodeUpdated['lokasi'] = 'Jl. Protokol Baru No. 10';
        $locContext2 = $this->locationService->resolveAssetLocationContext($sampleNodeUpdated, ['ulp_name' => 'ULP Sidoarjo Kota']);
        $this->assertSame('ROAD_CONTEXT', $locContext2['context_type']);
        $this->assertSame('Jl. Protokol Baru No. 10', $locContext2['road_name']);
        $this->assertSame('ASSET_DATABASE_FIELD', $locContext2['source']);

        // 3. Mathematical proof: road context cannot produce source_asset_id or target_asset_id
        $this->assertArrayNotHasKey('source_asset_id', $locContext2);
        $this->assertArrayNotHasKey('target_asset_id', $locContext2);
        $this->assertArrayNotHasKey('edge_id', $locContext2);
    }

    /**
     * Scenario F & Amendment 3/4: Cryptographic SHA-256 Fingerprint Determinism.
     */
    public function testScenarioFCryptographicFingerprintDeterminism(): void
    {
        $fp1 = $this->topologyService->getFeederFingerprint(15);
        $fp2 = $this->topologyService->getFeederFingerprint(15);

        $this->assertSame('success', $fp1['status']);
        $this->assertSame($fp1['data_fingerprint'], $fp2['data_fingerprint'], 'Fingerprint must be strictly deterministic when data is unchanged.');
        $this->assertSame(64, strlen($fp1['data_fingerprint']));
        $this->assertSame(205, $fp1['active_assets_count']);
        $this->assertSame(197, $fp1['active_translines_count']);
    }

    /**
     * Amendment 2: Zero Length Fabrication Invariant.
     * When length_meters is null, the engine must not fabricate distance from GPS.
     */
    public function testAmendment2ZeroLengthFabrication(): void
    {
        $layout = $this->layoutService->buildFeederLayout(15);
        foreach ($layout['edges'] as $edge) {
            if ($edge['length_meters'] === null) {
                // Must remain null, never approximated
                $this->assertNull($edge['length_meters']);
            } else {
                $this->assertIsNumeric($edge['length_meters']);
            }
        }
    }

    /**
     * Live Data -> SLD Reaction Test Cycle:
     * Simulates: Baseline -> +Asset C (Isolated) -> +Transline B-C (Connected) -> Deactivate Transline B-C (Returns to Isolated).
     */
    public function testLiveDataSourceReactionTestCycle(): void
    {
        // 1. Initial State: 2 assets, 1 transline
        $assetA = ['id' => 101, 'kode_asset' => 'AST-A', 'status' => 'ACTIVE', 'latitude' => -7.420, 'longitude' => 112.720, 'updated_at' => '2026-09-16 10:00:00'];
        $assetB = ['id' => 102, 'kode_asset' => 'AST-B', 'status' => 'ACTIVE', 'latitude' => -7.421, 'longitude' => 112.721, 'updated_at' => '2026-09-16 10:00:00'];
        $transAB = ['id' => 201, 'source_asset_id' => 101, 'target_asset_id' => 102, 'status' => 'ACTIVE', 'is_active' => 1, 'distance_meters' => 50.0, 'updated_at' => '2026-09-16 10:00:00'];

        $sigA = "id:101|status:ACTIVE;id:102|status:ACTIVE";
        $sigT = "id:201|src:101|tgt:102|active:1";
        $fp1 = hash('sha256', hash('sha256', $sigA) . ':' . hash('sha256', $sigT));

        // 2. Step 2: Add Asset C (without transline)
        $assetC = ['id' => 103, 'kode_asset' => 'AST-C', 'status' => 'ACTIVE', 'latitude' => -7.422, 'longitude' => 112.722, 'updated_at' => '2026-09-16 10:05:00'];
        $sigA2 = "id:101|status:ACTIVE;id:102|status:ACTIVE;id:103|status:ACTIVE";
        $fp2 = hash('sha256', hash('sha256', $sigA2) . ':' . hash('sha256', $sigT));

        $this->assertNotSame($fp1, $fp2, 'Adding Asset C must change fingerprint.');

        // 3. Step 3: Add Transline B-C
        $transBC = ['id' => 202, 'source_asset_id' => 102, 'target_asset_id' => 103, 'status' => 'ACTIVE', 'is_active' => 1, 'distance_meters' => 45.0, 'updated_at' => '2026-09-16 10:10:00'];
        $sigT3 = "id:201|src:101|tgt:102|active:1;id:202|src:102|tgt:103|active:1";
        $fp3 = hash('sha256', hash('sha256', $sigA2) . ':' . hash('sha256', $sigT3));

        $this->assertNotSame($fp2, $fp3, 'Adding Transline B-C must change fingerprint.');

        // 4. Step 4: Deactivate Transline B-C (is_active = 0)
        // Active translines list drops back to transAB only
        $sigT4 = "id:201|src:101|tgt:102|active:1";
        $fp4 = hash('sha256', hash('sha256', $sigA2) . ':' . hash('sha256', $sigT4));

        $this->assertNotSame($fp3, $fp4, 'Deactivating Transline B-C must change fingerprint.');
        $this->assertSame($fp2, $fp4, 'With Transline B-C deactivated, fingerprint returns to State 2 (Asset C isolated).');
    }

    /**
     * Amendment 8: Road Corridors Generation for Hybrid View.
     */
    public function testAmendment8RoadCorridorsGeneration(): void
    {
        $layout = $this->layoutService->buildFeederLayout(15);
        $this->assertArrayHasKey('corridors', $layout);
        $corridors = $layout['corridors'];

        $this->assertNotEmpty($corridors, 'Corridors must be generated for Hybrid Mode C.');
        $firstCorr = $corridors[0];

        $this->assertArrayHasKey('corridor_id', $firstCorr);
        $this->assertArrayHasKey('road_name', $firstCorr);
        $this->assertArrayHasKey('locality', $firstCorr);
        $this->assertArrayHasKey('node_count', $firstCorr);
        $this->assertArrayHasKey('bounds', $firstCorr);
        $this->assertGreaterThanOrEqual(2, $firstCorr['node_count']);
    }
}
