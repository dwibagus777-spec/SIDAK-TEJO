<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\VoiceAI\VoiceAIFactory;
use App\Services\VoiceAI\VoiceAIService;
use App\Services\VoiceAI\IntentEngine;

class VoiceAssistantCommandsTest extends CIUnitTestCase
{
    protected IntentEngine $intentEngine;
    protected VoiceAIService $voiceAIService;

    protected function setUp(): void
    {
        parent::setUp();
        $factory = new VoiceAIFactory();
        $this->intentEngine = new IntentEngine($factory->makeAIProvider());
        $this->voiceAIService = new VoiceAIService(
            $factory->makeAIProvider(),
            $factory->makeSTTProvider(),
            $factory->makeTTSProvider(),
            $factory->makeSearchProvider()
        );
    }

    public function testParseBukaTemuanHigh(): void
    {
        $res = $this->intentEngine->parse('buka temuan high');
        $this->assertSame('NAVIGATE_TEMUAN', $res['intent']);
        $this->assertSame('HIGH', $res['params']['prioritas']);
        $this->assertNull($res['params']['penyulang_id']);
    }

    public function testParseBukaTemuanBanjarKemantren(): void
    {
        $res = $this->intentEngine->parse('buka temuan Banjar Kemantren');
        $this->assertSame('NAVIGATE_TEMUAN', $res['intent']);
        $this->assertNull($res['params']['prioritas']);
        $this->assertSame(15, $res['params']['penyulang_id']);
        $this->assertSame('BANJAR KEMANTREN', $res['params']['nama_penyulang']);
    }

    public function testParseBukaTemuanHighBanjarKemantren(): void
    {
        $res = $this->intentEngine->parse('buka temuan high Banjar Kemantren');
        $this->assertSame('NAVIGATE_TEMUAN', $res['intent']);
        $this->assertSame('HIGH', $res['params']['prioritas']);
        $this->assertSame(15, $res['params']['penyulang_id']);
        $this->assertSame('BANJAR KEMANTREN', $res['params']['nama_penyulang']);
    }

    public function testVoiceServiceProcessHigh(): void
    {
        $res = $this->voiceAIService->processRequest(['text' => 'buka temuan high']);
        $this->assertArrayHasKey('action', $res);
        $this->assertSame('NAVIGATE', $res['action']['type']);
        $this->assertStringContainsString('prioritas=HIGH', $res['action']['url']);
        $this->assertSame('HIGH', $res['action']['filters']['prioritas']);
    }

    public function testVoiceServiceProcessBanjarKemantren(): void
    {
        $res = $this->voiceAIService->processRequest(['text' => 'buka temuan Banjar Kemantren']);
        $this->assertArrayHasKey('action', $res);
        $this->assertSame('NAVIGATE', $res['action']['type']);
        $this->assertStringContainsString('penyulang_id=15', $res['action']['url']);
        $this->assertSame(15, $res['action']['filters']['penyulang_id']);
    }

    public function testVoiceServiceProcessCombinedFilters(): void
    {
        $res = $this->voiceAIService->processRequest(['text' => 'buka temuan high Banjar Kemantren']);
        $this->assertArrayHasKey('action', $res);
        $this->assertSame('NAVIGATE', $res['action']['type']);
        $this->assertStringContainsString('prioritas=HIGH', $res['action']['url']);
        $this->assertStringContainsString('penyulang_id=15', $res['action']['url']);
        $this->assertSame('HIGH', $res['action']['filters']['prioritas']);
        $this->assertSame(15, $res['action']['filters']['penyulang_id']);
    }
}
