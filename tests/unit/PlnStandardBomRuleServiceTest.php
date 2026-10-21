<?php

namespace Tests\Unit;

use App\Services\PlnStandardBomRuleService;
use CodeIgniter\Test\CIUnitTestCase;

class PlnStandardBomRuleServiceTest extends CIUnitTestCase
{
    public function testSanitizeNogaRemovesInspectionNumbers()
    {
        $service = new PlnStandardBomRuleService();

        // Numeric inspection items must become NULL unless real gardu in text
        $this->assertNull($service->sanitizeNoga('526', '526. TM11 LA jebol phase T.'));
        $this->assertNull($service->sanitizeNoga('320', '320. Recloser tiang'));

        // Real gardu numbers must be preserved or extracted
        $this->assertSame('PA 1003', $service->sanitizeNoga('PA 1003', 'Temuan gardu PA 1003'));
        $this->assertSame('PA 806', $service->sanitizeNoga('315', 'Hospot dexxel gtt pa806 phase R'));
    }

    public function testResolveBomFromFindingCase160()
    {
        $service = new PlnStandardBomRuleService();
        $finding160 = [
            'id'             => 160,
            'jenis_temuan'   => 'ANOMALI',
            'detail_temuan'  => '526. TM11 LA jebol phase T. Lokasi depan GI buduran. Zona 1. P. Kahuripan.',
            'conductor_type' => 'AAAC',
            'conductor_size' => '150',
        ];

        $bom = $service->resolveBomFromFinding($finding160);

        $this->assertSame('RESOLVED', $bom['mapping_status']);
        $this->assertSame('ARRESTER', $bom['finding_component_key']);
        $this->assertSame('TM-11', $bom['construction_code']);
        $this->assertNotEmpty($bom['materials']);

        $codes = array_column($bom['materials'], 'canonical_code');
        $this->assertContains('CANON-MAT-002', $codes); // Polymer Arrester 24 kV
        $this->assertContains('CANON-HDW-023', $codes); // PG Clamp
        $this->assertContains('CANON-HDW-015', $codes); // Ground Wire Clamp

        // Since it is single phase T, arrester qty is 1.00
        $laItem = null;
        foreach ($bom['materials'] as $m) {
            if ($m['canonical_code'] === 'CANON-MAT-002') {
                $laItem = $m;
                break;
            }
        }
        $this->assertNotNull($laItem);
        $this->assertEquals(1.0, $laItem['quantity']);
    }

    public function testUnmappedFindingMarkedPerluVerifikasi()
    {
        $service = new PlnStandardBomRuleService();
        $unknownFinding = [
            'id'             => 999,
            'jenis_temuan'   => 'LAIN_LAIN',
            'detail_temuan'  => 'Pemeriksaan visual pohon jarak aman 5 meter',
        ];

        $bom = $service->resolveBomFromFinding($unknownFinding);

        $this->assertSame('PERLU_VERIFIKASI', $bom['mapping_status']);
        $this->assertEmpty($bom['materials']);
        $this->assertStringContainsString('Perlu Verifikasi', $bom['formatted_summary']);
    }

    public function testDeterministicVsAmbiguousCandidateDetection()
    {
        $service = new PlnStandardBomRuleService();

        // 1. Single candidate within 25 meters -> deterministic
        $finding = [
            'id'            => 101,
            'detail_temuan' => 'Hotspot skun jumperan',
            'latitude'      => -7.4300,
            'longitude'     => 112.7300,
            'penyulang_id'  => 1,
        ];

        // 2. Candidate with name match in text -> deterministic even if multiple
        $findingWithName = [
            'id'            => 102,
            'detail_temuan' => 'Hotspot jumperan pada tiang GEDANGAN_42 fasa R',
            'latitude'      => -7.4300,
            'longitude'     => 112.7300,
            'penyulang_id'  => 1,
        ];

        $bomHar = $service->resolveBomFromFinding([
            'id'            => 201,
            'jenis_temuan'  => 'ANOMALI',
            'detail_temuan' => 'Cross arm unp bengkok tertimpa ranting',
            'pelaksana'     => 'HAR Konstruksi',
        ]);
        $this->assertSame('RESOLVED', $bomHar['mapping_status']);
        $this->assertSame('CROSSARM', $bomHar['finding_component_key']);
    }
}
