<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\FaultLocatorWorkspaceService;
use App\Services\FaultFeedbackCalibrationService;
use App\Services\FeederNetworkReadinessService;
use Config\Database;

class FaultLocatorCaseAndOutcomeTest extends CIUnitTestCase
{
    protected FaultLocatorWorkspaceService $workspaceService;
    protected FaultFeedbackCalibrationService $calibrationService;
    protected FeederNetworkReadinessService $readinessService;
    protected $dbConn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspaceService = new FaultLocatorWorkspaceService();
        $this->calibrationService = new FaultFeedbackCalibrationService();
        $this->readinessService = new FeederNetworkReadinessService();
        $this->dbConn = Database::connect('default');
    }

    public function testSaveFaultCasePersistsTelemetryAndDefaultStatus(): int
    {
        $uniqueCaseNumber = 'TEST-CASE-' . bin2hex(random_bytes(6));
        $firstAsset = $this->dbConn->table('assets')->where('deleted_at IS NULL')->get()->getRowArray();
        $realAssetId = $firstAsset ? (int)$firstAsset['id'] : null;

        $mockResultData = [
            'case_uuid' => $uniqueCaseNumber,
            'ulp_id'    => 1,
            'feeder_id' => 33, // ECCO
            'source_type' => 'GI',
            'engine_version' => 'FLI-1.0.0-IMPEDANCE-QUADRATIC',
            'calculated_distance_m' => 1250.5,
            'confidence_percent' => 88.0,
            'fault_classification' => [
                'fault_type' => '3_PHASE',
                'current_r'  => 1450.0,
                'current_s'  => 1420.0,
                'current_t'  => 1440.0,
                'current_n'  => 15.0,
            ],
            'electrical_parameters' => [
                'conductor_type' => 'AAAC 150 mm²',
            ],
            'fault_point' => [
                'lat' => -7.4478,
                'lng' => 112.7183,
            ],
            'candidate_assets' => $realAssetId ? [
                [
                    'asset_id' => $realAssetId,
                    'kode_asset' => $firstAsset['kode_asset'] ?? 'TEST-POLE-01',
                    'asset_name' => $firstAsset['nama_asset'] ?? 'Tiang Uji 01',
                ]
            ] : [],
            'missing_parameters' => [],
        ];

        $saveRes = $this->workspaceService->saveFaultCase($mockResultData, ['username' => 'TEST_OPERATOR']);

        $this->assertSame('success', $saveRes['status']);
        $this->assertGreaterThan(0, $saveRes['case_id']);
        $this->assertSame($uniqueCaseNumber, $saveRes['case_number']);

        // Verify in database
        $row = $this->dbConn->table('fault_cases')->where('id', $saveRes['case_id'])->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame('MENUNGGU_PEMERIKSAAN', $row['follow_up_status']);
        $this->assertSame('GI', $row['source_type']);
        $this->assertEqualsWithDelta(1250.5, (float)$row['target_distance_meters'], 0.1);
        $this->assertEqualsWithDelta(1450.0, (float)$row['current_r'], 0.1);
        $this->assertSame('AAAC 150 mm²', $row['conductor_type']);

        return (int)$saveRes['case_id'];
    }

    /**
     * @depends testSaveFaultCasePersistsTelemetryAndDefaultStatus
     */
    public function testRecordFieldOutcomePositiveConfirmed(int $caseId): void
    {
        $firstAsset = $this->dbConn->table('assets')->where('deleted_at IS NULL')->get()->getRowArray();
        $realAssetId = $firstAsset ? (int)$firstAsset['id'] : null;

        $outcomeData = [
            'outcome_type'        => 'CONFIRMED',
            'found_asset_id'      => $realAssetId,
            'component_damaged'   => 'Bunglon menempel pada pin isolator fasa R',
            'actual_distance_m'   => 1240.0,
            'is_component_match'  => 1,
            'technician_name'     => 'TIM_YANTEK_GEDANGAN',
            'finding_notes'       => 'Ditemukan bangkai reptil pada pin isolator tiang.',
        ];

        $res = $this->workspaceService->recordFieldOutcome($caseId, $outcomeData, ['username' => 'INSPEKTOR_A']);

        $this->assertSame('success', $res['status']);
        $this->assertSame('GANGGUAN_DITEMUKAN', $res['follow_up_status']);
        $this->assertGreaterThan(0, $res['finding_id']);

        // Check updated status in fault_cases
        $caseRow = $this->dbConn->table('fault_cases')->where('id', $caseId)->get()->getRowArray();
        $this->assertSame('GANGGUAN_DITEMUKAN', $caseRow['follow_up_status']);

        // Check fault_actual_findings row
        $findingRow = $this->dbConn->table('fault_actual_findings')->where('id', $res['finding_id'])->get()->getRowArray();
        $this->assertNotNull($findingRow);
        $this->assertSame('CONFIRMED', $findingRow['outcome_type']);
        $this->assertSame('Bunglon menempel pada pin isolator fasa R', $findingRow['component_damaged']);
        $this->assertSame($realAssetId, $findingRow['found_asset_id'] !== null ? (int)$findingRow['found_asset_id'] : null);
        $this->assertSame(1, (int)$findingRow['is_component_match']);
    }

    public function testRecordFieldOutcomeNegativeNotFound(): void
    {
        $uniqueCaseNumber = 'TEST-CASE-NEG-' . bin2hex(random_bytes(6));
        $saveRes = $this->workspaceService->saveFaultCase([
            'case_uuid' => $uniqueCaseNumber,
            'feeder_id' => 33,
            'source_type' => 'GI',
            'calculated_distance_m' => 900.0,
            'fault_classification' => ['fault_type' => '2_PHASE'],
            'missing_parameters' => [],
        ]);
        $negCaseId = (int)$saveRes['case_id'];

        $outcomeData = [
            'outcome_type'          => 'NOT_FOUND',
            'inspected_area_notes'  => 'Penyisiran tiang 1 s/d tiang 25 jalur utama',
            'not_found_reason'      => 'ROW_BERSIH_TIDAK_ADA_FLASH',
            'technician_name'       => 'TIM_YANTEK_SIDOARJO',
            'finding_notes'         => 'Tidak ditemukan jejak sentuhan pohon atau flashover',
        ];

        $res = $this->workspaceService->recordFieldOutcome($negCaseId, $outcomeData);

        $this->assertSame('success', $res['status']);
        $this->assertSame('TIDAK_DITEMUKAN', $res['follow_up_status']);

        // Verify in DB that found_asset_id is strictly null for negative outcome
        $finding = $this->dbConn->table('fault_actual_findings')->where('id', $res['finding_id'])->get()->getRowArray();
        $this->assertNotNull($finding);
        $this->assertSame('NOT_FOUND', $finding['outcome_type']);
        $this->assertNull($finding['found_asset_id']);
        $this->assertSame('ROW_BERSIH_TIDAK_ADA_FLASH', $finding['not_found_reason']);
        $this->assertStringContainsString('Penyisiran tiang 1', $finding['inspected_area_notes']);
    }

    public function testControlledAIFeedbackCalibrationThresholdAndInvariants(): void
    {
        $summary = $this->calibrationService->getFeedbackDatasetSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('total_cases', $summary);
        $this->assertArrayHasKey('confirmed_samples', $summary);
        $this->assertArrayHasKey('min_required_samples', $summary);
        $this->assertSame(FaultFeedbackCalibrationService::MIN_CONFIRMED_SAMPLES, $summary['min_required_samples']);

        // Assert segregated datasets exist
        $this->assertArrayHasKey('segregated_datasets', $summary);
        $this->assertArrayHasKey('CONFIRMED', $summary['segregated_datasets']);
        $this->assertArrayHasKey('NOT_FOUND', $summary['segregated_datasets']);
        $this->assertArrayHasKey('AMBIGUOUS_VERIFIED', $summary['segregated_datasets']);

        // Under 15 confirmed samples, calibration mode must be DATA_COLLECTION_MODE
        if ($summary['confirmed_samples'] < 15) {
            $this->assertSame('DATA_COLLECTION_MODE', $summary['calibration_status']);
            $this->assertFalse($summary['is_calibration_unlocked']);
        }
    }

    public function testFeederNetworkReadinessAuditsWithoutFakeAssets(): void
    {
        $audit = $this->readinessService->auditAllFeeders();

        $this->assertSame('success', $audit['status']);
        $this->assertGreaterThan(0, $audit['summary']['total_feeders']);
        $this->assertSame(0, $audit['summary']['cross_feeder_violations']);
        $this->assertSame(0, $audit['summary']['non_pole_transline_violations']);

        // Verify known ready feeder (e.g. ECCO)
        $feederMap = [];
        foreach ($audit['feeders'] as $f) {
            $feederMap[$f['nama_penyulang']] = $f;
        }

        $this->assertArrayHasKey('ECCO', $feederMap);
        $this->assertSame('SIAP_TERVERIFIKASI', $feederMap['ECCO']['readiness_status']);
        $this->assertGreaterThan(0, $feederMap['ECCO']['active_jtm_poles_count']);
        $this->assertGreaterThan(0, $feederMap['ECCO']['translines_count']);

        // Verify unready feeder audit
        $unreadyFeeder = null;
        foreach ($audit['feeders'] as $f) {
            if ($f['readiness_status'] === 'BELUM_SIAP') {
                $unreadyFeeder = $f;
                break;
            }
        }
        $this->assertNotNull($unreadyFeeder, 'Harus ada minimal satu feeder BELUM_SIAP');
        $this->assertSame('BELUM_SIAP', $unreadyFeeder['readiness_status']);
        $this->assertNotEmpty($unreadyFeeder['missing_prerequisites']);
    }
}
