<?php

namespace Tests\Unit;

use App\Services\SafetyHazardAnalysisService;
use PHPUnit\Framework\TestCase;

class SafetyHazardAnalysisServiceTest extends TestCase
{
    public function testRowFindingExplainsElectricalAndFallingBranchHazards(): void
    {
        $result = (new SafetyHazardAnalysisService())->analyze([
            'jenis_temuan' => 'ROW',
            'detail_temuan' => 'Pohon sono mendekati jaringan dan perlu dipangkas',
            'pelaksana' => 'HAR ROW',
        ], 2, [[
            'jenis_temuan' => 'ROW',
            'detail_temuan' => 'Ranting pohon dekat kabel jaringan',
        ]]);

        $categories = array_column($result['hazards'], 'category');
        self::assertContains('K2', $categories);
        self::assertContains('K3', $categories);
        self::assertSame(2, $result['historical_context']['count']);
        self::assertSame(1, $result['historical_context']['hazard_patterns'][0]['count']);
        self::assertNotEmpty($result['sources']);
        self::assertStringContainsStringIgnoringCase('bukan pengganti', $result['notice']);
    }

    public function testDamagedInsulatorIncludesFallingObjectHazard(): void
    {
        $result = (new SafetyHazardAnalysisService())->analyze(['detail_temuan' => 'Pin isolator rusak']);

        self::assertContains('Alat atau komponen jatuh saat pemeriksaan/perbaikan', array_column($result['hazards'], 'hazard'));
    }
}
